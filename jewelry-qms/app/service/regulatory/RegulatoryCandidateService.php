<?php
declare(strict_types=1);

namespace app\service\regulatory;

use Closure;
use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;
use RuntimeException;
use Throwable;
use think\facade\Db;

final class RegulatoryCandidateService
{
    public const RULE_VERSION = 'candidate-fingerprint-v1';

    private const TITLE_MAX_LENGTH = 300;
    private const ANNOUNCEMENT_NUMBER_MAX_LENGTH = 120;
    private const SOURCE_URL_MAX_LENGTH = 500;
    private const NORMALIZED_URL_MAX_LENGTH = 1000;
    private const SOURCE_ITEM_KEY_MAX_LENGTH = 255;

    private Closure $clock;
    private Closure $candidateInserter;
    private Closure $retryBackoff;

    public function __construct(
        ?callable $clock = null,
        ?callable $candidateInserter = null,
        ?callable $retryBackoff = null
    ) {
        $this->clock = Closure::fromCallable(
            $clock ?? static fn (): DateTimeImmutable => new DateTimeImmutable('now')
        );
        $this->candidateInserter = Closure::fromCallable(
            $candidateInserter ?? static function (array $data): void {
                Db::name('qms_external_change_candidates')->insert($data);
            }
        );
        $this->retryBackoff = Closure::fromCallable(
            $retryBackoff ?? static function (int $attempt): void {
                usleep($attempt * 10_000);
            }
        );
    }

    public static function normalizeAnnouncementNumber(string $value): string
    {
        $normalized = preg_replace('/[\p{Z}\s]+/u', ' ', trim($value));
        if (!is_string($normalized)) {
            throw new InvalidArgumentException('announcement_number 包含无效字符');
        }

        return mb_strtoupper(trim($normalized), 'UTF-8');
    }

    public function sourceItemKey(array $item): string
    {
        $announcementNumber = self::normalizeAnnouncementNumber(
            (string)($item['announcement_number'] ?? '')
        );
        if ($announcementNumber !== '') {
            $this->assertMaxLength(
                'announcement_number',
                $announcementNumber,
                self::ANNOUNCEMENT_NUMBER_MAX_LENGTH
            );
            return $announcementNumber;
        }

        $canonicalUrl = trim((string)($item['canonical_url'] ?? ''));
        if ($canonicalUrl === '') {
            throw new InvalidArgumentException('announcement_number 为空时 canonical_url 必填');
        }
        $this->assertMaxLength('source_item_key', $canonicalUrl, self::SOURCE_ITEM_KEY_MAX_LENGTH);

        return $canonicalUrl;
    }

    public function contentHash(array $item): string
    {
        $payload = [
            'title' => $this->normalizeText((string)($item['title'] ?? '')),
            'announcement_number' => self::normalizeAnnouncementNumber(
                (string)($item['announcement_number'] ?? '')
            ),
            'published_date' => $this->normalizeDate($item['published_date'] ?? null, 'published_date'),
            'summary' => $this->normalizeText((string)($item['summary'] ?? '')),
            'evidence_body_summary' => $this->normalizeText((string)(
                $item['evidence_summary']
                ?? $item['evidence']['body_summary']
                ?? $item['evidence']['raw_text']
                ?? ''
            )),
            'attachments' => $this->canonicalAttachments(
                (array)($item['attachments'] ?? $item['evidence']['attachments'] ?? [])
            ),
        ];

        return hash('sha256', $this->encodeJson($this->canonicalize($payload)));
    }

    /**
     * @return array{status: string, new_count: int, existing_count: int, candidate: array<string, mixed>}
     */
    public function record(
        string $companyId,
        string $monitorRunId,
        string $sourceKey,
        string $sourceMode,
        array $item
    ): array {
        $companyId = trim($companyId);
        $monitorRunId = trim($monitorRunId);
        $sourceKey = trim($sourceKey);
        if ($companyId === '' || $monitorRunId === '' || $sourceKey === '') {
            throw new InvalidArgumentException('company_id、monitor_run_id 和 source_key 必填');
        }
        if (!in_array($sourceMode, ['html_list', 'manual_only'], true)) {
            throw new InvalidArgumentException('source_mode 无效');
        }
        $this->assertMaxLength('source_key', $sourceKey, 100);

        $normalized = $this->normalizeItem($item);
        $sourceItemKey = $this->sourceItemKey($normalized);
        $contentHash = $this->contentHash($item);
        $seenAt = $this->now();

        return $this->withDeadlockRetry(
            fn (): array => Db::transaction(
                fn (): array => $this->recordInTransaction(
                    $companyId,
                    $monitorRunId,
                    $sourceKey,
                    $sourceMode,
                    $normalized,
                    $sourceItemKey,
                    $contentHash,
                    $seenAt
                )
            )
        );
    }

    private function recordInTransaction(
        string $companyId,
        string $monitorRunId,
        string $sourceKey,
        string $sourceMode,
        array $normalized,
        string $sourceItemKey,
        string $contentHash,
        string $seenAt
    ): array {
        $versions = $this->lockVersionChain($companyId, $sourceKey, $sourceItemKey);
        $previous = $this->resolveChainTail($versions);
        foreach ($versions as $version) {
            if (hash_equals((string)$version['content_hash'], $contentHash)) {
                return $this->existingResult($version, $seenAt);
            }
        }

        $candidate = [
            'id' => qms_uuid(),
            'company_id' => $companyId,
            'monitor_run_id' => $monitorRunId,
            'source_key' => $sourceKey,
            'source_mode' => $sourceMode,
            'source_item_key' => $sourceItemKey,
            'source_url' => $normalized['canonical_url'],
            'normalized_url' => $normalized['canonical_url'],
            'title' => $normalized['title'],
            'announcement_number' => $normalized['announcement_number'],
            'document_type' => $normalized['document_type'],
            'published_date' => $normalized['published_date'],
            'effective_date' => $normalized['effective_date'],
            'first_seen_at' => $seenAt,
            'last_seen_at' => $seenAt,
            'content_hash' => $contentHash,
            'evidence_summary' => $normalized['evidence_summary'],
            'evidence_refs' => $this->encodeJson($normalized['evidence_refs']),
            'evidence_json' => $this->encodeJson($normalized['evidence_json']),
            'supersedes_candidate_id' => $previous !== null ? (string)$previous['id'] : null,
            'relevance' => 'unknown',
            'preliminary_applicability' => 'needs_review',
            'impact_analysis' => null,
            'analysis_rule_version' => null,
            'analysis_confidence' => null,
            'analysis_rationale' => null,
            'review_status' => 'pending',
            'publish' => 1,
            'soft_delete' => 0,
            'created' => $seenAt,
            'modified' => $seenAt,
        ];

        try {
            ($this->candidateInserter)($candidate);
        } catch (Throwable $exception) {
            if (!$this->isUniqueConstraintViolation($exception)) {
                throw $exception;
            }
            $racedVersions = $this->lockVersionChain($companyId, $sourceKey, $sourceItemKey);
            $this->resolveChainTail($racedVersions);
            foreach ($racedVersions as $raced) {
                if (hash_equals((string)$raced['content_hash'], $contentHash)) {
                    return $this->existingResult($raced, $seenAt);
                }
            }

            throw $exception;
        }

        $stored = $this->findExisting(
            $companyId,
            $sourceKey,
            $sourceItemKey,
            $contentHash
        );
        if (!is_array($stored)) {
            throw new RuntimeException('候选写入后无法读取');
        }

        return [
            'status' => 'new',
            'new_count' => 1,
            'existing_count' => 0,
            'candidate' => $this->decodeCandidate($stored),
        ];
    }

    private function withDeadlockRetry(callable $operation): array
    {
        $maximumAttempts = 3;
        for ($attempt = 1; $attempt <= $maximumAttempts; $attempt++) {
            try {
                return $operation();
            } catch (Throwable $exception) {
                if (!$this->isDeadlockOrSerializationFailure($exception)) {
                    throw $exception;
                }
                if ($attempt === $maximumAttempts) {
                    throw new RuntimeException(
                        '候选并发冲突，已重试 3 次仍未成功，请稍后重试',
                        0,
                        $exception
                    );
                }
                ($this->retryBackoff)($attempt);
            }
        }

        throw new RuntimeException('候选并发重试进入不可达状态');
    }

    private function isDeadlockOrSerializationFailure(Throwable $exception): bool
    {
        for ($current = $exception; $current instanceof Throwable; $current = $current->getPrevious()) {
            $code = (string)$current->getCode();
            $message = $current->getMessage();
            if ($code === '1213'
                || $code === '40001'
                || str_contains($message, 'SQLSTATE[40001]')
                || (preg_match('/\b1213\b/u', $message) === 1
                    && stripos($message, 'deadlock') !== false)
            ) {
                return true;
            }
        }

        return false;
    }

    private function normalizeItem(array $item): array
    {
        $title = $this->normalizeText((string)($item['title'] ?? ''));
        if ($title === '') {
            throw new InvalidArgumentException('title 必填');
        }
        $this->assertMaxLength('title', $title, self::TITLE_MAX_LENGTH);

        $canonicalUrl = trim((string)($item['canonical_url'] ?? ''));
        if ($canonicalUrl === '') {
            throw new InvalidArgumentException('source_url/canonical_url 必填');
        }
        $this->assertMaxLength('source_url', $canonicalUrl, self::SOURCE_URL_MAX_LENGTH);
        $this->assertMaxLength('normalized_url', $canonicalUrl, self::NORMALIZED_URL_MAX_LENGTH);

        $announcementNumber = self::normalizeAnnouncementNumber(
            (string)($item['announcement_number'] ?? '')
        );
        if ($announcementNumber !== '') {
            $this->assertMaxLength(
                'announcement_number',
                $announcementNumber,
                self::ANNOUNCEMENT_NUMBER_MAX_LENGTH
            );
        }

        $documentType = $this->normalizeNullableText($item['document_type'] ?? null);
        if ($documentType !== null) {
            $this->assertMaxLength('document_type', $documentType, 80);
        }
        $summary = $this->normalizeText((string)($item['summary'] ?? ''));
        $evidenceBody = $this->normalizeText((string)(
            $item['evidence_summary']
            ?? $item['evidence']['body_summary']
            ?? $item['evidence']['raw_text']
            ?? ''
        ));

        return array_merge($item, [
            'title' => $title,
            'canonical_url' => $canonicalUrl,
            'announcement_number' => $announcementNumber !== '' ? $announcementNumber : null,
            'document_type' => $documentType,
            'published_date' => $this->normalizeDate($item['published_date'] ?? null, 'published_date'),
            'effective_date' => $this->normalizeDate($item['effective_date'] ?? null, 'effective_date'),
            'evidence_summary' => $summary !== '' ? $summary : ($evidenceBody !== '' ? $evidenceBody : null),
            'evidence_refs' => array_values((array)($item['evidence_refs'] ?? $item['attachments'] ?? [])),
            'evidence_json' => $item,
        ]);
    }

    private function findExisting(
        string $companyId,
        string $sourceKey,
        string $sourceItemKey,
        string $contentHash
    ): ?array {
        $query = Db::name('qms_external_change_candidates')
            ->where('company_id', $companyId)
            ->where('source_key', $sourceKey)
            ->where('source_item_key', $sourceItemKey)
            ->where('content_hash', $contentHash);
        $row = $query->find();

        return is_array($row) ? $row : null;
    }

    /** @return array<int, array<string, mixed>> */
    private function lockVersionChain(string $companyId, string $sourceKey, string $sourceItemKey): array
    {
        // This current read locks the whole item chain/range so concurrent new versions wait,
        // then see the version committed by the transaction that acquired the lock first.
        $result = Db::name('qms_external_change_candidates')
            ->field(['id', 'content_hash', 'supersedes_candidate_id', 'last_seen_at'])
            ->where('company_id', $companyId)
            ->where('source_key', $sourceKey)
            ->where('source_item_key', $sourceItemKey)
            ->order('id', 'asc')
            ->lock(true)
            ->select();
        if (is_object($result) && method_exists($result, 'toArray')) {
            $result = $result->toArray();
        }
        if (!is_array($result)) {
            throw new RuntimeException('候选版本链数据完整性错误：锁定结果不可读');
        }

        return array_values($result);
    }

    /** @param array<int, array<string, mixed>> $versions */
    private function resolveChainTail(array $versions): ?array
    {
        if ($versions === []) {
            return null;
        }

        $byId = [];
        foreach ($versions as $version) {
            $id = trim((string)($version['id'] ?? ''));
            if ($id === '' || isset($byId[$id])) {
                throw new RuntimeException('候选版本链数据完整性错误：存在空或重复版本标识');
            }
            $byId[$id] = $version;
        }

        $referencedAsParent = [];
        $childByParent = [];
        $rootIds = [];
        foreach ($byId as $id => $version) {
            $parentId = trim((string)($version['supersedes_candidate_id'] ?? ''));
            if ($parentId === '') {
                $rootIds[] = $id;
                continue;
            }
            if ($parentId === $id) {
                throw new RuntimeException('候选版本链数据完整性错误：检测到自引用环');
            }
            if (!isset($byId[$parentId])) {
                throw new RuntimeException('候选版本链数据完整性错误：检测到断链');
            }
            if (isset($childByParent[$parentId])) {
                throw new RuntimeException('候选版本链数据完整性错误：检测到分叉');
            }
            $childByParent[$parentId] = $id;
            $referencedAsParent[$parentId] = true;
        }

        $fullyVisited = [];
        foreach (array_keys($byId) as $startId) {
            $path = [];
            $cursorId = $startId;
            while (true) {
                if (isset($path[$cursorId])) {
                    throw new RuntimeException('候选版本链数据完整性错误：检测到环');
                }
                if (isset($fullyVisited[$cursorId])) {
                    break;
                }
                $path[$cursorId] = true;
                $parentId = trim((string)($byId[$cursorId]['supersedes_candidate_id'] ?? ''));
                if ($parentId === '') {
                    break;
                }
                $cursorId = $parentId;
            }
            foreach (array_keys($path) as $visitedId) {
                $fullyVisited[$visitedId] = true;
            }
        }

        $tailIds = array_values(array_filter(
            array_keys($byId),
            static fn (string $id): bool => !isset($referencedAsParent[$id])
        ));
        if (count($tailIds) !== 1) {
            throw new RuntimeException('候选版本链数据完整性错误：链尾数量不是一');
        }
        if (count($rootIds) !== 1) {
            throw new RuntimeException('候选版本链数据完整性错误：根版本数量不是一');
        }

        $visited = [];
        $cursorId = $tailIds[0];
        while (true) {
            if (isset($visited[$cursorId])) {
                throw new RuntimeException('候选版本链数据完整性错误：检测到环');
            }
            $visited[$cursorId] = true;
            $parentId = trim((string)($byId[$cursorId]['supersedes_candidate_id'] ?? ''));
            if ($parentId === '') {
                break;
            }
            if (!isset($byId[$parentId])) {
                throw new RuntimeException('候选版本链数据完整性错误：检测到断链');
            }
            $cursorId = $parentId;
        }
        if (count($visited) !== count($byId)) {
            throw new RuntimeException('候选版本链数据完整性错误：存在未连接版本');
        }

        return $byId[$tailIds[0]];
    }

    private function existingResult(array $existing, string $seenAt): array
    {
        $lastSeen = (string)($existing['last_seen_at'] ?? '');
        if ($lastSeen === '' || strcmp($seenAt, $lastSeen) > 0) {
            Db::name('qms_external_change_candidates')
                ->where('id', (string)$existing['id'])
                ->update(['last_seen_at' => $seenAt, 'modified' => $seenAt]);
        }
        $stored = Db::name('qms_external_change_candidates')
            ->where('id', (string)$existing['id'])
            ->find();
        if (!is_array($stored)) {
            throw new RuntimeException('既有候选更新后无法读取');
        }

        return [
            'status' => 'existing',
            'new_count' => 0,
            'existing_count' => 1,
            'candidate' => $this->decodeCandidate($stored),
        ];
    }

    private function decodeCandidate(array $candidate): array
    {
        foreach (['evidence_refs', 'evidence_json', 'impact_analysis'] as $field) {
            if (is_string($candidate[$field] ?? null) && $candidate[$field] !== '') {
                $candidate[$field] = json_decode($candidate[$field], true, 512, JSON_THROW_ON_ERROR);
            }
        }

        return $candidate;
    }

    private function canonicalAttachments(array $attachments): array
    {
        $canonical = [];
        foreach ($attachments as $attachment) {
            $canonical[] = $this->canonicalize($this->withoutObservationTimes($attachment));
        }
        usort(
            $canonical,
            fn (mixed $left, mixed $right): int => strcmp($this->encodeJson($left), $this->encodeJson($right))
        );

        return $canonical;
    }

    private function withoutObservationTimes(mixed $value): mixed
    {
        if (is_object($value)) {
            $value = get_object_vars($value);
        }
        if (!is_array($value)) {
            return $value;
        }
        $result = [];
        foreach ($value as $key => $item) {
            if (in_array((string)$key, ['fetched_at', 'first_seen_at', 'last_seen_at'], true)) {
                continue;
            }
            $result[$key] = $this->withoutObservationTimes($item);
        }

        return $result;
    }

    private function canonicalize(mixed $value): mixed
    {
        if (is_object($value)) {
            $value = get_object_vars($value);
        }
        if (!is_array($value)) {
            return $value;
        }
        foreach ($value as $key => $item) {
            $value[$key] = $this->canonicalize($item);
        }
        if (!array_is_list($value)) {
            ksort($value, SORT_STRING);
        }

        return $value;
    }

    private function normalizeDate(mixed $value, string $field): ?string
    {
        if ($value === null || trim((string)$value) === '') {
            return null;
        }
        $date = trim((string)$value);
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        $errors = DateTimeImmutable::getLastErrors();
        if (!$parsed instanceof DateTimeImmutable
            || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))
            || $parsed->format('Y-m-d') !== $date
        ) {
            throw new InvalidArgumentException($field . ' 必须为有效 YYYY-MM-DD 日期');
        }

        return $date;
    }

    private function normalizeText(string $value): string
    {
        $normalized = preg_replace('/[\p{Z}\s]+/u', ' ', trim($value));
        if (!is_string($normalized)) {
            throw new InvalidArgumentException('文本包含无效字符');
        }

        return trim($normalized);
    }

    private function normalizeNullableText(mixed $value): ?string
    {
        $normalized = $this->normalizeText((string)($value ?? ''));
        return $normalized !== '' ? $normalized : null;
    }

    private function assertMaxLength(string $field, string $value, int $maximum): void
    {
        if (mb_strlen($value, 'UTF-8') > $maximum) {
            throw new InvalidArgumentException($field . ' 超过最大长度 ' . $maximum);
        }
    }

    private function encodeJson(mixed $value): string
    {
        return json_encode(
            $value,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR
        );
    }

    private function now(): string
    {
        $value = ($this->clock)();
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }
        $parsed = new DateTimeImmutable((string)$value);
        return $parsed->format('Y-m-d H:i:s');
    }

    private function isUniqueConstraintViolation(Throwable $exception): bool
    {
        $message = $exception->getMessage();
        return in_array((string)$exception->getCode(), ['1062', '23000'], true)
            || str_contains($message, 'SQLSTATE[23000]')
            || str_contains($message, 'Duplicate entry');
    }
}
