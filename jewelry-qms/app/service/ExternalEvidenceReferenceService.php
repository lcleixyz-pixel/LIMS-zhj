<?php
declare(strict_types=1);

namespace app\service;

use app\model\ExternalEvidenceReference;
use InvalidArgumentException;

final class ExternalEvidenceReferenceService
{
    private const SUBJECT_TYPES = [
        'quality_event',
        'audit',
        'complaint',
        'capa',
        'management_review',
    ];

    private const WRITABLE_FIELDS = [
        'source_system',
        'object_type',
        'external_number',
        'display_name',
        'readonly_url',
        'cited_at',
        'checksum_summary',
        'notes',
    ];

    public static function create(string $subjectType, string $subjectId, array $input): ExternalEvidenceReference
    {
        $subjectType = trim($subjectType);
        $subjectId = trim($subjectId);
        if (!in_array($subjectType, self::SUBJECT_TYPES, true) || $subjectId === '') {
            throw new InvalidArgumentException('外部证据关联对象无效');
        }

        $data = array_intersect_key($input, array_flip(self::WRITABLE_FIELDS));
        foreach (['source_system', 'object_type', 'external_number', 'display_name', 'readonly_url'] as $required) {
            $data[$required] = trim((string)($data[$required] ?? ''));
            if ($data[$required] === '') {
                throw new InvalidArgumentException('外部证据字段不能为空：' . $required);
            }
        }
        $url = (string)$data['readonly_url'];
        if (!str_starts_with($url, 'http://') && !str_starts_with($url, 'https://')) {
            throw new InvalidArgumentException('只读链接必须使用 http:// 或 https://');
        }

        $data['cited_at'] = trim((string)($data['cited_at'] ?? '')) ?: date('Y-m-d H:i:s');
        $data['checksum_summary'] = self::limit(trim((string)($data['checksum_summary'] ?? '')), 255);
        $data['notes'] = self::limit(trim((string)($data['notes'] ?? '')), 500);
        $data['subject_type'] = $subjectType;
        $data['subject_id'] = $subjectId;
        $data['publish'] = 1;
        $data['soft_delete'] = 0;

        return ExternalEvidenceReference::create($data);
    }

    public static function forSubject(string $subjectType, string $subjectId)
    {
        return ExternalEvidenceReference::where('subject_type', trim($subjectType))
            ->where('subject_id', trim($subjectId))
            ->where('soft_delete', 0)
            ->order('cited_at', 'desc')
            ->select();
    }

    private static function limit(string $value, int $length): string
    {
        return function_exists('mb_substr') ? mb_substr($value, 0, $length, 'UTF-8') : substr($value, 0, $length);
    }
}
