<?php
declare(strict_types=1);

namespace app\service\regulatory;

use Closure;
use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;
use RuntimeException;
use Throwable;
use think\facade\Config;
use think\facade\Db;

final class RegulatoryMonitorService
{
    public const EXECUTION_VERSION = 'regulatory-monitor-v1';

    private const SOURCE_ERROR_MAX_LENGTH = 500;
    private const ERROR_SUMMARY_MAX_LENGTH = 1000;

    private RegulatorySourceRegistry $registry;
    private RegulatoryCandidateService $candidateService;
    private Closure $sourceFetcher;
    private Closure $clock;

    public function __construct(
        ?RegulatorySourceRegistry $registry = null,
        ?callable $sourceFetcher = null,
        ?callable $clock = null,
        ?RegulatoryCandidateService $candidateService = null
    ) {
        $this->registry = $registry ?? new RegulatorySourceRegistry();
        $this->clock = Closure::fromCallable(
            $clock ?? static fn (): DateTimeImmutable => new DateTimeImmutable('now')
        );
        $this->candidateService = $candidateService ?? new RegulatoryCandidateService($clock);
        $registryForFetcher = $this->registry;
        $this->sourceFetcher = Closure::fromCallable(
            $sourceFetcher ?? static function (array $source) use ($registryForFetcher): string {
                return $registryForFetcher
                    ->httpClientFor((string)$source['key'])
                    ->fetch((string)$source['entry_url']);
            }
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function run(
        string $triggerMode = 'manual',
        ?array $sourceKeys = null,
        ?string $since = null
    ): array
    {
        if (!in_array($triggerMode, ['scheduled', 'manual'], true)) {
            throw new InvalidArgumentException('trigger_mode 必须是 scheduled 或 manual');
        }
        $sourceKeys = $this->resolveSourceKeys($sourceKeys);
        $since = $this->normalizeSince($since);
        $companyId = trim((string)Config::get('qms.company_id'));
        if ($companyId === '') {
            throw new RuntimeException('法规监测缺少 company_id 配置');
        }

        $runId = qms_uuid();
        $startedAt = $this->now();
        $sourceConfigVersion = $this->sourceConfigVersion();
        Db::name('qms_regulatory_monitor_runs')->insert([
            'id' => $runId,
            'company_id' => $companyId,
            'run_code' => 'REG-MON-' . str_replace(['-', ':', ' '], '', $startedAt) . '-' . substr($runId, 0, 8),
            'trigger_mode' => $triggerMode,
            'started_at' => $startedAt,
            'status' => 'running',
            'execution_version' => self::EXECUTION_VERSION,
            'source_config_version' => $sourceConfigVersion,
            'rule_version' => RegulatoryCandidateService::RULE_VERSION,
            'publish' => 1,
            'soft_delete' => 0,
            'created' => $startedAt,
            'modified' => $startedAt,
        ]);

        $successCount = 0;
        $failureCount = 0;
        $automaticSourceCount = 0;
        $manualVerificationCount = 0;
        $itemSuccessTotal = 0;
        $candidateNewCount = 0;
        $candidateExistingCount = 0;
        $sourceResults = [];
        $errors = [];

        foreach ($sourceKeys as $sourceKey) {
            $sourceResult = [
                'source_key' => $sourceKey,
                'name' => null,
                'mode' => null,
                'status' => 'failed',
                'item_count' => 0,
                'processed_count' => 0,
                'item_success_count' => 0,
                'item_failure_count' => 0,
                'candidate_new_count' => 0,
                'candidate_existing_count' => 0,
                'filter_skipped_count' => 0,
                'missing_published_date_count' => 0,
                'requires_manual_verification' => false,
                'message' => null,
                'error' => null,
            ];
            try {
                $source = $this->registry->source($sourceKey);
                $sourceResult['name'] = (string)($source['name'] ?? $sourceKey);
                $sourceResult['mode'] = (string)$source['mode'];
                if ((string)$source['mode'] === 'manual_only') {
                    $manualResult = $this->registry->adapterFor($sourceKey)->parse('', $source);
                    $manualVerificationCount++;
                    $sourceResult['status'] = 'manual_verification';
                    $sourceResult['requires_manual_verification'] = true;
                    $sourceResult['message'] = $this->boundedText(
                        (string)($manualResult['message'] ?? '需人工核验'),
                        self::SOURCE_ERROR_MAX_LENGTH
                    );
                    $sourceResults[] = $sourceResult;
                    continue;
                }

                $automaticSourceCount++;
                $body = ($this->sourceFetcher)($source);
                if (!is_string($body)) {
                    throw new RuntimeException('来源 fetcher 必须返回 HTML 字符串');
                }
                $parsed = $this->registry->adapterFor($sourceKey)->parse($body, $source);
                $items = (array)($parsed['items'] ?? []);
                $sourceResult['item_count'] = count($items);
                $itemErrors = [];
                foreach ($items as $item) {
                    $countedAsProcessed = false;
                    try {
                        if (!is_array($item)) {
                            throw new RuntimeException('来源适配器返回了无效候选条目');
                        }
                        if ($since !== null) {
                            $observedPublishedDate = $item['published_date']
                                ?? $item['evidence']['observed_published_date']
                                ?? null;
                            $disposition = $this->sinceDisposition($observedPublishedDate, $since);
                            if ($disposition === 'filtered_before_since') {
                                $sourceResult['filter_skipped_count']++;
                                continue;
                            }
                            if ($disposition === 'included_missing_date_manual_confirmation') {
                                $sourceResult['missing_published_date_count']++;
                                $sourceResult['requires_manual_verification'] = true;
                                $sourceResult['message'] = '已保留发布日期缺失或非法的条目，需人工确认 since 边界';
                                $item['monitor_filter'] = [
                                    'since' => $since,
                                    'disposition' => 'included_missing_date_manual_confirmation',
                                    'reason' => 'published_date_missing_or_invalid',
                                    'observed_published_date' => trim((string)$observedPublishedDate) !== ''
                                        ? trim((string)$observedPublishedDate)
                                        : null,
                                ];
                                $item['published_date'] = null;
                            }
                        }
                        $sourceResult['processed_count']++;
                        $countedAsProcessed = true;
                        $recorded = $this->candidateService->record(
                            $companyId,
                            $runId,
                            $sourceKey,
                            (string)$source['mode'],
                            $item
                        );
                        $sourceResult['item_success_count']++;
                        $sourceResult['candidate_new_count'] += (int)$recorded['new_count'];
                        $sourceResult['candidate_existing_count'] += (int)$recorded['existing_count'];
                    } catch (Throwable $itemException) {
                        if ($itemException instanceof RegulatoryTransactionAbortedException) {
                            throw $itemException;
                        }
                        if (!$countedAsProcessed) {
                            $sourceResult['processed_count']++;
                        }
                        $sourceResult['item_failure_count']++;
                        $itemErrors[] = '条目 ' . $sourceResult['processed_count'] . ': '
                            . $this->sanitizeError($itemException->getMessage());
                    }
                }
                $itemSuccessTotal += (int)$sourceResult['item_success_count'];
                if ($sourceResult['item_failure_count'] > 0) {
                    $failureCount++;
                    $sourceResult['status'] = $sourceResult['item_success_count'] > 0
                        ? 'partial_failed'
                        : 'failed';
                    $sourceResult['error'] = $this->sanitizeError(implode(' | ', $itemErrors));
                    $errors[] = $sourceKey . ': ' . $sourceResult['error'];
                } else {
                    $successCount++;
                    $sourceResult['status'] = 'success';
                }
            } catch (Throwable $exception) {
                if ($exception instanceof RegulatoryTransactionAbortedException) {
                    throw $exception;
                }
                $failureCount++;
                $sanitized = $this->sanitizeError($exception->getMessage());
                $sourceResult['error'] = $sanitized;
                $errors[] = $sourceKey . ': ' . $sanitized;
            }
            $candidateNewCount += (int)$sourceResult['candidate_new_count'];
            $candidateExistingCount += (int)$sourceResult['candidate_existing_count'];
            $sourceResults[] = $sourceResult;
        }

        $status = $failureCount === 0
            ? 'completed'
            : (($successCount > 0 || $itemSuccessTotal > 0) ? 'partial_failed' : 'failed');
        $finishedAt = $this->now();
        $sourceStats = [
            'source_count' => count($sourceKeys),
            'automatic_source_count' => $automaticSourceCount,
            'success_count' => $successCount,
            'failure_count' => $failureCount,
            'manual_verification_count' => $manualVerificationCount,
            'sources' => $sourceResults,
        ];
        $candidateStats = [
            'candidate_new_count' => $candidateNewCount,
            'candidate_existing_count' => $candidateExistingCount,
        ];
        $result = [
            'run_id' => $runId,
            'status' => $status,
            'source_count' => count($sourceKeys),
            'success_count' => $successCount,
            'failure_count' => $failureCount,
            'manual_verification_count' => $manualVerificationCount,
            'candidate_new_count' => $candidateNewCount,
            'candidate_existing_count' => $candidateExistingCount,
            'started_at' => $startedAt,
            'finished_at' => $finishedAt,
            'collector_version' => self::EXECUTION_VERSION,
            'source_config_version' => $sourceConfigVersion,
            'rule_version' => RegulatoryCandidateService::RULE_VERSION,
            'since' => $since,
            'sources' => $sourceResults,
        ];
        $errorSummary = $errors === []
            ? null
            : $this->sanitizeError(implode(' | ', $errors), self::ERROR_SUMMARY_MAX_LENGTH);

        Db::name('qms_regulatory_monitor_runs')->where('id', $runId)->update([
            'finished_at' => $finishedAt,
            'source_stats' => $this->encodeJson($sourceStats),
            'candidate_stats' => $this->encodeJson($candidateStats),
            'status' => $status,
            'result_json' => $this->encodeJson($result),
            'error_summary' => $errorSummary,
            'modified' => $finishedAt,
        ]);

        return $result;
    }

    /** @return list<string> */
    private function resolveSourceKeys(?array $sourceKeys): array
    {
        $selected = $sourceKeys ?? array_keys($this->registry->all());
        if ($selected === []) {
            throw new InvalidArgumentException('source_keys 不能为空；省略参数表示全部来源');
        }

        $resolved = [];
        foreach ($selected as $sourceKey) {
            if (!is_string($sourceKey)) {
                throw new InvalidArgumentException('source_keys 包含无效或未批准的来源');
            }
            $sourceKey = trim($sourceKey);
            if (preg_match('/\A[a-z][a-z0-9_]{0,99}\z/D', $sourceKey) !== 1) {
                throw new InvalidArgumentException('source_keys 包含无效或未批准的来源');
            }
            try {
                $this->registry->source($sourceKey);
            } catch (Throwable $exception) {
                throw new InvalidArgumentException('source_keys 包含无效或未批准的来源', 0, $exception);
            }
            $resolved[$sourceKey] = $sourceKey;
        }

        return array_values($resolved);
    }

    private function normalizeSince(?string $since): ?string
    {
        if ($since === null) {
            return null;
        }
        if (preg_match('/\A\d{4}-\d{2}-\d{2}\z/D', $since) !== 1) {
            throw new InvalidArgumentException('since 必须是严格的 YYYY-MM-DD 日期');
        }
        [$year, $month, $day] = array_map('intval', explode('-', $since));
        if (!checkdate($month, $day, $year)) {
            throw new InvalidArgumentException('since 必须是有效日期');
        }

        return $since;
    }

    private function sinceDisposition(mixed $publishedDate, string $since): string
    {
        $publishedDate = trim((string)$publishedDate);
        if (preg_match('/\A\d{4}-\d{2}-\d{2}\z/D', $publishedDate) !== 1) {
            return 'included_missing_date_manual_confirmation';
        }
        [$year, $month, $day] = array_map('intval', explode('-', $publishedDate));
        if (!checkdate($month, $day, $year)) {
            return 'included_missing_date_manual_confirmation';
        }

        return strcmp($publishedDate, $since) < 0 ? 'filtered_before_since' : 'included';
    }

    private function sanitizeError(string $message, int $maximum = self::SOURCE_ERROR_MAX_LENGTH): string
    {
        $message = mb_convert_kana($message, 'as', 'UTF-8');
        $message = strtr($message, [
            '“' => '"',
            '”' => '"',
            '‘' => "'",
            '’' => "'",
        ]);
        $message = preg_split('/(?:Stack trace:|\n\s*#0\b)/i', $message, 2)[0] ?? $message;
        $patterns = [
            '/\b(?:authorization|proxy-authorization|cookie|set-cookie)\s*:\s*[^\r\n]*/iu' => '[REDACTED]',
            '/(?<![\p{L}\p{N}_])["\']?(?:db_dsn|dsn|database_(?:url|dsn))["\']?(?![\p{L}\p{N}_])\s*[:=]\s*[^\r\n]*/iu' => '[REDACTED]',
            '#(?<![\p{L}\p{N}_])(?:mysql|mariadb|pgsql|postgres(?:ql)?|sqlsrv|oci|sqlite|odbc|firebird|ibm|informix|cubrid|mongodb|redis):(?://)?[^\r\n]*#iu' => '[REDACTED]',
            '#\b(?:https?|ftp)://[^\s/@:]+:[^\s/@]+@[^\r\n]*#iu' => '[REDACTED]',
            '/\b(?:authorization|proxy-authorization|cookie|set-cookie|password|passwd|pwd|token|api[_-]?key|secret|dsn|database_(?:url|dsn)|db_(?:dsn|host|name|user|pass(?:word)?))\s*[=:]\s*(?:"[^"]*"|\'[^\']*\'|[^\s,;]+)/iu' => '[REDACTED]',
            '/\bbearer\s+[^\s,;]+/iu' => '[REDACTED]',
            '/\b(?:authorization|proxy-authorization|cookie|set-cookie|password|passwd|pwd|token|api[_-]?key|secret|dsn|database_(?:url|dsn)|db_(?:dsn|host|name|user|pass(?:word)?))\b/iu' => '[REDACTED]',
        ];
        foreach ($patterns as $pattern => $replacement) {
            $message = (string)preg_replace($pattern, $replacement, $message);
        }
        $message = (string)preg_replace('/\s+/u', ' ', trim($message));
        if ($message === '') {
            $message = '来源处理失败（错误详情已脱敏）';
        }

        return $this->boundedText($message, $maximum);
    }

    private function sourceConfigVersion(): string
    {
        $path = dirname(__DIR__, 3) . '/config/regulatory_sources.php';
        $hash = is_file($path) ? hash_file('sha256', $path) : false;
        return 'source-config-' . substr(is_string($hash) ? $hash : 'unknown', 0, 16);
    }

    private function boundedText(string $value, int $maximum): string
    {
        return mb_strlen($value, 'UTF-8') <= $maximum
            ? $value
            : mb_substr($value, 0, $maximum, 'UTF-8');
    }

    private function encodeJson(mixed $value): string
    {
        return json_encode(
            $value,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        );
    }

    private function now(): string
    {
        $value = ($this->clock)();
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }
        return (new DateTimeImmutable((string)$value))->format('Y-m-d H:i:s');
    }
}
