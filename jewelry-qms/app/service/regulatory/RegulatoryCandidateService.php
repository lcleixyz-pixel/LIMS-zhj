<?php
declare(strict_types=1);

namespace app\service\regulatory;

use Closure;
use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;
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

    public function __construct(?callable $clock = null, ?callable $candidateInserter = null)
    {
        $this->clock = Closure::fromCallable(
            $clock ?? static fn (): DateTimeImmutable => new DateTimeImmutable('now')
        );
        $this->candidateInserter = Closure::fromCallable(
            $candidateInserter ?? static function (array $data): void {
                Db::name('qms_external_change_candidates')->insert($data);
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

        return Db::transaction(function () use (
            $companyId,
            $monitorRunId,
            $sourceKey,
            $sourceMode,
            $normalized,
            $sourceItemKey,
            $contentHash,
            $seenAt
        ): array {
            $existing = $this->findExisting(
                $companyId,
                $sourceKey,
                $sourceItemKey,
                $contentHash,
                true
            );
            if (is_array($existing)) {
                return $this->existingResult($existing, $seenAt);
            }

            $previous = Db::name('qms_external_change_candidates')
                ->where('company_id', $companyId)
                ->where('source_key', $sourceKey)
                ->where('source_item_key', $sourceItemKey)
                ->where('content_hash', '<>', $contentHash)
                ->order(['first_seen_at' => 'desc', 'created' => 'desc', 'id' => 'desc'])
                ->lock(true)
                ->find();

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
                'supersedes_candidate_id' => is_array($previous) ? (string)$previous['id'] : null,
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
                $raced = $this->findExisting(
                    $companyId,
                    $sourceKey,
                    $sourceItemKey,
                    $contentHash,
                    true
                );
                if (!is_array($raced)) {
                    throw $exception;
                }

                return $this->existingResult($raced, $seenAt);
            }

            $stored = $this->findExisting(
                $companyId,
                $sourceKey,
                $sourceItemKey,
                $contentHash,
                false
            );
            if (!is_array($stored)) {
                throw new \RuntimeException('候选写入后无法读取');
            }

            return [
                'status' => 'new',
                'new_count' => 1,
                'existing_count' => 0,
                'candidate' => $this->decodeCandidate($stored),
            ];
        });
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
        string $contentHash,
        bool $lock
    ): ?array {
        $query = Db::name('qms_external_change_candidates')
            ->where('company_id', $companyId)
            ->where('source_key', $sourceKey)
            ->where('source_item_key', $sourceItemKey)
            ->where('content_hash', $contentHash);
        if ($lock) {
            $query->lock(true);
        }
        $row = $query->find();

        return is_array($row) ? $row : null;
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
            throw new \RuntimeException('既有候选更新后无法读取');
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
