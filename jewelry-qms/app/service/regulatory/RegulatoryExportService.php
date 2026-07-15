<?php
declare(strict_types=1);

namespace app\service\regulatory;

use DomainException;
use InvalidArgumentException;
use OutOfBoundsException;
use RuntimeException;
use think\facade\Config;
use think\facade\Db;
use think\facade\Session;
use UnexpectedValueException;

final class RegulatoryExportService
{
    public const SCHEMA_VERSION = '1.0';

    private const EXPORT_ROLES = ['admin', 'quality_manager'];

    private const FORBIDDEN_KEYS = [
        'password',
        'cookie',
        'authorization',
        'dsn',
        'mobile',
        'idcard',
    ];

    private const MAX_SCAN_NODES = 4096;
    private const MAX_SCAN_BYTES = 2_000_000;

    private const IMPACT_KEYS = [
        'cma_scope_mark',
        'qms_documents',
        'personnel_authorization',
        'equipment_calibration',
        'lims_rules',
        'training',
    ];

    private const CANDIDATE_FIELDS = [
        'id',
        'source_key',
        'source_mode',
        'source_url',
        'normalized_url',
        'title',
        'announcement_number',
        'document_type',
        'published_date',
        'effective_date',
        'first_seen_at',
        'last_seen_at',
        'content_hash',
        'evidence_summary',
        'evidence_refs',
        'relevance',
        'preliminary_applicability',
        'impact_analysis',
        'analysis_rule_version',
        'analysis_confidence',
        'review_status',
        'reviewed_at',
    ];

    private int $scanNodes = 0;
    private int $scanBytes = 0;

    /** @return array<string, mixed> */
    public function exportCandidate(string $candidateId): array
    {
        $this->assertEnabled();
        $this->assertRole();
        $candidateId = $this->assertCandidateId($candidateId);
        $candidate = Db::name('qms_external_change_candidates')
            ->field(self::CANDIDATE_FIELDS)
            ->where($this->visibilityScope())
            ->where('id', $candidateId)
            ->find();
        if (!is_array($candidate)) {
            throw new OutOfBoundsException('法规候选不存在或无权导出');
        }

        $sourceKey = trim((string)$candidate['source_key']);
        $registry = new RegulatorySourceRegistry();
        $sources = $registry->all();
        if (!is_array($sources[$sourceKey] ?? null)) {
            throw new UnexpectedValueException('法规候选来源不在批准清单');
        }
        $source = $sources[$sourceKey];
        $allowedHosts = $registry->allowedHosts($sourceKey);
        $canonicalUrl = $this->approvedHttpsUrl(
            trim((string)($candidate['normalized_url'] ?: $candidate['source_url'])),
            $allowedHosts
        );
        if ($canonicalUrl === null) {
            throw new UnexpectedValueException('法规候选官方来源链接无法安全导出');
        }

        $packet = [
            'schema_version' => self::SCHEMA_VERSION,
            'candidate' => [
                'id' => (string)$candidate['id'],
                'title' => (string)$candidate['title'],
                'announcement_number' => $this->nullableString($candidate['announcement_number']),
                'document_type' => $this->nullableString($candidate['document_type']),
                'published_date' => $this->nullableString($candidate['published_date']),
                'effective_date' => $this->nullableString($candidate['effective_date']),
                'first_seen_at' => (string)$candidate['first_seen_at'],
                'last_seen_at' => (string)$candidate['last_seen_at'],
                'content_hash' => (string)$candidate['content_hash'],
                'relevance' => (string)$candidate['relevance'],
                'preliminary_applicability' => (string)$candidate['preliminary_applicability'],
                'analysis_rule_version' => $this->nullableString($candidate['analysis_rule_version']),
                'analysis_confidence' => $this->normalizedConfidence($candidate['analysis_confidence']),
            ],
            'source' => [
                'source_key' => $sourceKey,
                'source_mode' => (string)$candidate['source_mode'],
                'source_name' => trim((string)($source['name'] ?? '')),
                'canonical_url' => $canonicalUrl,
                'evidence' => [
                    'summary' => trim((string)($candidate['evidence_summary'] ?? '')),
                    'references' => $this->evidenceReferences(
                        $this->decodedArray($candidate['evidence_refs']),
                        $allowedHosts
                    ),
                ],
            ],
            'impact_assessment' => $this->impactAssessment(
                $this->decodedArray($candidate['impact_analysis'])
            ),
            'review' => [
                'status' => (string)$candidate['review_status'],
                'reviewed_at' => $this->nullableString($candidate['reviewed_at']),
            ],
        ];
        $this->scanNodes = 0;
        $this->scanBytes = 0;
        $this->assertNoSensitiveContent($packet);

        return $packet;
    }

    public function filename(string $candidateId): string
    {
        return 'candidate-' . $this->assertCandidateId($candidateId) . '-review-packet-v' . self::SCHEMA_VERSION . '.json';
    }

    /** @return array<string, mixed> */
    private function visibilityScope(): array
    {
        $companyId = trim((string)Config::get('qms.company_id'));
        if ($companyId === '') {
            throw new RuntimeException('法规监测缺少 company_id 配置');
        }

        return ['company_id' => $companyId, 'publish' => 1, 'soft_delete' => 0];
    }

    private function assertEnabled(): void
    {
        if (!filter_var(Config::get('qms.regulatory_monitor.enabled', false), FILTER_VALIDATE_BOOL)) {
            throw new RuntimeException('法规监测功能未启用');
        }
    }

    private function assertRole(): void
    {
        $role = trim((string)Session::get('user.role', 'staff'));
        if (!in_array($role, self::EXPORT_ROLES, true)) {
            throw new DomainException('无权导出法规候选复核包');
        }
    }

    private function assertCandidateId(string $candidateId): string
    {
        $candidateId = trim($candidateId);
        if (preg_match('/\A[A-Za-z0-9][A-Za-z0-9_-]{0,35}\z/D', $candidateId) !== 1) {
            throw new InvalidArgumentException('候选 ID 必须是 1–36 位安全标识符');
        }

        return $candidateId;
    }

    /** @return array<mixed> */
    private function decodedArray(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }
        if ($value === null || $value === '') {
            return [];
        }
        if (!is_string($value)) {
            throw new UnexpectedValueException('法规候选导出字段格式无效');
        }
        try {
            $decoded = json_decode($value, true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new UnexpectedValueException('法规候选导出字段格式无效', 0, $exception);
        }
        if (!is_array($decoded)) {
            throw new UnexpectedValueException('法规候选导出字段格式无效');
        }

        return $decoded;
    }

    /** @param list<string> $allowedHosts */
    private function approvedHttpsUrl(string $url, array $allowedHosts): ?string
    {
        if ($url === '' || preg_match('/[\x00-\x1F\x7F]/', $url) === 1) {
            return null;
        }
        $parts = parse_url($url);
        if (!is_array($parts)
            || array_key_exists('user', $parts)
            || array_key_exists('pass', $parts)
            || strtolower((string)($parts['scheme'] ?? '')) !== 'https'
            || !in_array(strtolower((string)($parts['host'] ?? '')), $allowedHosts, true)
            || (int)($parts['port'] ?? 443) !== 443
        ) {
            return null;
        }

        return $url;
    }

    /**
     * @param array<mixed> $references
     * @param list<string> $allowedHosts
     * @return list<array{kind: ?string, label: ?string, locator: ?string, url: ?string}>
     */
    private function evidenceReferences(array $references, array $allowedHosts): array
    {
        $result = [];
        foreach ($references as $reference) {
            if (!is_array($reference)) {
                continue;
            }
            $url = $this->scalarString($reference['url'] ?? null);
            $safeUrl = $url !== null ? $this->approvedHttpsUrl($url, $allowedHosts) : null;
            $item = [
                'kind' => $this->scalarString($reference['kind'] ?? null),
                'label' => $this->scalarString($reference['label'] ?? null),
                'locator' => $this->scalarString($reference['locator'] ?? null),
                'url' => $safeUrl,
            ];
            if (array_filter($item, static fn (?string $value): bool => $value !== null) !== []) {
                $result[] = $item;
            }
        }
        usort($result, static fn (array $left, array $right): int => strcmp(
            json_encode($left, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            json_encode($right, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)
        ));

        return $result;
    }

    /** @param array<mixed> $analysis @return array<string, array<string, mixed>> */
    private function impactAssessment(array $analysis): array
    {
        $result = [];
        foreach (self::IMPACT_KEYS as $impactKey) {
            $item = is_array($analysis[$impactKey] ?? null) ? $analysis[$impactKey] : [];
            $conclusion = trim((string)($item['conclusion'] ?? 'no_match'));
            if (!in_array($conclusion, ['likely', 'possible', 'no_match'], true)) {
                $conclusion = 'no_match';
            }
            $evidence = [];
            foreach (is_array($item['evidence'] ?? null) ? $item['evidence'] : [] as $entry) {
                $summary = is_array($entry)
                    ? $this->scalarString($entry['summary'] ?? null)
                    : $this->scalarString($entry);
                if ($summary !== null) {
                    $evidence[] = $summary;
                }
            }
            $ruleIds = [];
            foreach (is_array($item['rule_ids'] ?? null) ? $item['rule_ids'] : [] as $ruleId) {
                $value = $this->scalarString($ruleId);
                if ($value !== null) {
                    $ruleIds[] = $value;
                }
            }
            $evidence = array_values(array_unique($evidence));
            $ruleIds = array_values(array_unique($ruleIds));
            sort($evidence, SORT_STRING);
            sort($ruleIds, SORT_STRING);
            $result[$impactKey] = [
                'conclusion' => $conclusion,
                'evidence' => $evidence,
                'rule_ids' => $ruleIds,
                'confidence' => $this->normalizedConfidence($item['confidence'] ?? null),
            ];
        }

        return $result;
    }

    private function normalizedConfidence(mixed $value): float
    {
        if (!is_numeric($value)) {
            return 0.0;
        }

        return round(max(0.0, min(1.0, (float)$value)), 4);
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $value = trim((string)$value);

        return $value === '' ? null : $value;
    }

    private function scalarString(mixed $value): ?string
    {
        if (!is_scalar($value)) {
            return null;
        }

        return $this->nullableString((string)$value);
    }

    private function assertNoSensitiveContent(mixed $value, int $depth = 0): void
    {
        if ($depth > 20) {
            throw new UnexpectedValueException('法规候选导出内容无法安全检查');
        }
        $this->consumeScanBudget($value);
        if (is_array($value)) {
            foreach ($value as $key => $item) {
                if (is_string($key) && $this->isForbiddenKey($key)) {
                    throw new UnexpectedValueException('法规候选导出内容包含敏感信息');
                }
                $this->assertNoSensitiveContent($item, $depth + 1);
            }
            return;
        }
        if (!is_string($value)) {
            return;
        }

        $normalized = $this->normalizedSensitiveRepresentation($value);
        $this->assertEmbeddedJsonIsSafe($normalized, $depth);
        $this->assertPercentEncodedCopyIsSafe($normalized, $depth);
        $scanCopy = str_replace(['\\"', "\\'"], ['"', "'"], $normalized);
        if (preg_match_all(
            '/["\']?([A-Za-z][A-Za-z0-9_-]{0,50})["\']?\s*[:=]/',
            $scanCopy,
            $keyMatches
        ) > 0) {
            foreach ($keyMatches[1] as $key) {
                if ($this->isForbiddenKey((string)$key)) {
                    throw new UnexpectedValueException('法规候选导出内容包含敏感信息');
                }
            }
        }

        $credentialPatterns = [
            '/\bauthorization\s*[:=]\s*(?:(?:bearer|basic|digest)\s+)?\S+/i',
            '/\bbearer\s+[A-Za-z0-9._~+\/=\-]{8,}/i',
            '/\b(?:set-cookie|cookie)\s*[:=]\s*\S+/i',
            '/\b(?:dsn|database_url)\s*[:=]\s*\S+/i',
            '/\b(?:mysql|postgres(?:ql)?|sqlsrv|oracle|mongodb(?:\+srv)?|redis):(?:\/\/|host=)\S+/i',
            '/\b(?:server|host)\s*=\s*[^;\s]+\s*;\s*(?:database|dbname)\s*=/i',
            '/\bpassword\s*[:=]\s*\S+/i',
        ];
        foreach ($credentialPatterns as $pattern) {
            if (preg_match($pattern, $scanCopy) === 1) {
                throw new UnexpectedValueException('法规候选导出内容包含敏感信息');
            }
        }
        if (preg_match(
            '/(?<![A-Za-z0-9])(?:\+?86[\s-]*)?1[3-9]\d(?:[\s-]*\d){8}(?![A-Za-z0-9])/',
            $scanCopy
        ) === 1
            || $this->containsChineseIdCard($scanCopy)
        ) {
            throw new UnexpectedValueException('法规候选导出内容包含敏感信息');
        }
    }

    private function assertEmbeddedJsonIsSafe(string $value, int $depth): void
    {
        $trimmed = trim($value);
        $looksLikeString = str_starts_with($trimmed, '"') && str_ends_with($trimmed, '"');
        $looksLikeObject = str_starts_with($trimmed, '{')
            && str_ends_with($trimmed, '}')
            && preg_match('/["\'][^"\']+["\']\s*:/', $trimmed) === 1;
        $looksLikeArray = str_starts_with($trimmed, '[')
            && str_ends_with($trimmed, ']')
            && preg_match('/^\[\s*(?:[\[{"\']|-?\d|true\b|false\b|null\b)/i', $trimmed) === 1;
        $looksLikeJson = $looksLikeString || $looksLikeObject || $looksLikeArray;
        if (!$looksLikeJson) {
            return;
        }
        try {
            $decoded = json_decode($trimmed, true, 16, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            if ($looksLikeString && !$looksLikeObject && !$looksLikeArray) {
                return;
            }
            throw new UnexpectedValueException('法规候选导出内容无法安全检查', 0, $exception);
        }
        if (is_array($decoded) || is_string($decoded)) {
            $this->assertNoSensitiveContent($decoded, $depth + 1);
        }
    }

    private function assertPercentEncodedCopyIsSafe(string $value, int $depth): void
    {
        if (preg_match('/%[0-9A-Fa-f]{2}/', $value) !== 1) {
            return;
        }
        $decoded = rawurldecode($value);
        if ($decoded !== $value) {
            $this->assertNoSensitiveContent($decoded, $depth + 1);
        }
    }

    private function normalizedSensitiveRepresentation(string $value): string
    {
        $normalized = strtr($value, [
            '“' => '"', '”' => '"', '＂' => '"',
            '‘' => "'", '’' => "'", '＇' => "'",
            '：' => ':', '＝' => '=',
            '－' => '-', '—' => '-', '–' => '-', '−' => '-',
            '＿' => '_', "\u{00A0}" => ' ', '　' => ' ',
        ]);
        for ($pass = 0; $pass < 2; $pass++) {
            $decoded = preg_replace_callback(
                '/\\\\u00([0-7][0-9A-Fa-f])/i',
                static fn (array $match): string => chr((int)hexdec($match[1])),
                $normalized
            );
            if (!is_string($decoded) || $decoded === $normalized) {
                break;
            }
            $normalized = $decoded;
        }

        return $normalized;
    }

    private function consumeScanBudget(mixed $value): void
    {
        $this->scanNodes++;
        if (is_string($value)) {
            $this->scanBytes += strlen($value);
        }
        if ($this->scanNodes > self::MAX_SCAN_NODES || $this->scanBytes > self::MAX_SCAN_BYTES) {
            throw new UnexpectedValueException('法规候选导出内容无法安全检查');
        }
    }

    private function isForbiddenKey(string $key): bool
    {
        $normalized = strtolower((string)preg_replace('/[^A-Za-z0-9]/', '', $key));

        return in_array($normalized, self::FORBIDDEN_KEYS, true);
    }

    private function containsChineseIdCard(string $value): bool
    {
        $matched = preg_match_all(
            '/(?<![A-Za-z0-9])(\d{17}[0-9Xx])(?![A-Za-z0-9])/',
            $value,
            $matches
        );
        if (!is_int($matched) || $matched < 1) {
            return false;
        }

        foreach ($matches[1] as $candidate) {
            if ($this->validChineseIdCard((string)$candidate)) {
                return true;
            }
        }

        return false;
    }

    private function validChineseIdCard(string $candidate): bool
    {
        $birthday = \DateTimeImmutable::createFromFormat('!Ymd', substr($candidate, 6, 8));
        $dateErrors = \DateTimeImmutable::getLastErrors();
        if ($birthday === false
            || (is_array($dateErrors) && ($dateErrors['warning_count'] > 0 || $dateErrors['error_count'] > 0))
            || $birthday->format('Ymd') !== substr($candidate, 6, 8)
        ) {
            return false;
        }

        $weights = [7, 9, 10, 5, 8, 4, 2, 1, 6, 3, 7, 9, 10, 5, 8, 4, 2];
        $checks = ['1', '0', 'X', '9', '8', '7', '6', '5', '4', '3', '2'];
        $sum = 0;
        foreach ($weights as $index => $weight) {
            $sum += ((int)$candidate[$index]) * $weight;
        }

        return strtoupper($candidate[17]) === $checks[$sum % 11];
    }
}
