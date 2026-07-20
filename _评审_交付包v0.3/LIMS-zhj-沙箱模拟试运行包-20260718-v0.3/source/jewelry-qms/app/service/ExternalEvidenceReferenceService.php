<?php
declare(strict_types=1);

namespace app\service;

use app\model\ExternalEvidenceReference;
use InvalidArgumentException;
use think\facade\Config;

final class ExternalEvidenceReferenceService
{
    private const SUBJECT_TABLES = [
        'quality_event' => 'nonconformities',
        'audit' => 'audit_findings',
        'complaint' => 'customer_complaints',
        'capa' => 'capas',
        'management_review' => 'management_reviews',
    ];

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

    private const SAFE_QUERY_KEYS = ['id', 'number', 'record', 'view', 'lang', 'page', 'type'];
    private const SENSITIVE_PATH_SEGMENTS = [
        'token', 'secret', 'auth', 'password', 'passwd', 'signature',
        'access_key', 'access-key', 'apikey', 'api-key',
    ];
    private const CREDENTIAL_VALUE_PATTERNS = [
        '/^sk[-_][A-Za-z0-9_-]+$/i',
        '/^(?:sk|pk|rk)_(?:live|test|prod)_[A-Za-z0-9_-]+$/i',
        '/^xox[baprs]-[A-Za-z0-9-]+$/i',
        '/^AIza[0-9A-Za-z_-]+$/',
        '/^(?:ghp|gho|ghu|ghs|ghr)_[A-Za-z0-9_]+$/i',
        '/^github_pat_[A-Za-z0-9_]+$/i',
        '/^(?:AKIA|ASIA)[A-Z0-9]{12,}$/',
        '/^ya29\\.[A-Za-z0-9_-]+$/',
        '/^Bearer\\s+\\S+$/i',
        '/^[A-Za-z0-9_-]{8,}\\.[A-Za-z0-9_-]{8,}\\.[A-Za-z0-9_-]{6,}$/',
    ];

    public static function create(string $subjectType, string $subjectId, array $input): ExternalEvidenceReference
    {
        $subjectType = trim($subjectType);
        $subjectId = trim($subjectId);
        if (!in_array($subjectType, self::SUBJECT_TYPES, true) || $subjectId === '' || !self::subjectExists($subjectType, $subjectId)) {
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
        $urlParts = parse_url($url);
        if (!is_array($urlParts) || empty($urlParts['host'])) {
            throw new InvalidArgumentException('只读链接格式无效');
        }
        $scheme = strtolower((string)($urlParts['scheme'] ?? ''));
        $host = strtolower((string)$urlParts['host']);
        $isLoopback = in_array($host, ['localhost', '127.0.0.1', '::1'], true);
        if ($scheme !== 'https' && !($scheme === 'http' && $isLoopback)) {
            throw new InvalidArgumentException('只读链接必须使用 HTTPS；仅本机回环地址允许 HTTP');
        }
        if (!empty($urlParts['user']) || !empty($urlParts['pass'])) {
            throw new InvalidArgumentException('只读链接不得包含账号凭据');
        }
        if (array_key_exists('fragment', $urlParts)) {
            throw new InvalidArgumentException('只读链接不得包含片段');
        }
        foreach (array_filter(explode('/', rawurldecode((string)($urlParts['path'] ?? '')))) as $segment) {
            if (
                in_array(strtolower($segment), self::SENSITIVE_PATH_SEGMENTS, true)
                || preg_match('/(?:token|secret|auth|password|passwd|signature|access[_-]?key)[=:_-]/i', $segment)
                || self::looksLikeCredential($segment)
                || (
                    strlen($segment) >= 48
                    && !preg_match('/^[0-9a-f]{8}-[0-9a-f-]{27,}$/i', $segment)
                    && preg_match('/^[A-Za-z0-9+_\\-=]+$/', $segment)
                )
            ) {
                throw new InvalidArgumentException('只读链接路径疑似包含临时凭据');
            }
        }
        parse_str((string)($urlParts['query'] ?? ''), $queryParameters);
        foreach ($queryParameters as $key => $value) {
            if (!in_array(strtolower((string)$key), self::SAFE_QUERY_KEYS, true)) {
                throw new InvalidArgumentException('只读链接仅允许受控查询参数');
            }
            $stringValue = is_scalar($value) ? (string)$value : '';
            if (self::looksLikeCredential($stringValue)) {
                throw new InvalidArgumentException('只读链接参数值疑似包含临时凭据');
            }
            if (strlen($stringValue) >= 64 && preg_match('/^[A-Za-z0-9+_\\-=]+$/', $stringValue)) {
                throw new InvalidArgumentException('只读链接不得包含高熵临时凭据');
            }
        }

        $citedAt = str_replace('T', ' ', trim((string)($data['cited_at'] ?? '')));
        if ($citedAt !== '' && strtotime($citedAt) === false) {
            throw new InvalidArgumentException('引用时间格式不正确');
        }
        $data['cited_at'] = $citedAt !== '' ? $citedAt : date('Y-m-d H:i:s');
        $data['checksum_summary'] = self::limit(trim((string)($data['checksum_summary'] ?? '')), 255);
        $data['notes'] = self::limit(trim((string)($data['notes'] ?? '')), 500);
        $data['subject_type'] = $subjectType;
        $data['subject_id'] = $subjectId;
        $data['company_id'] = (string)Config::get('qms.company_id');
        $data['publish'] = 1;
        $data['soft_delete'] = 0;

        return ExternalEvidenceReference::create($data);
    }

    public static function forSubject(string $subjectType, string $subjectId)
    {
        return ExternalEvidenceReference::where('subject_type', trim($subjectType))
            ->where('subject_id', trim($subjectId))
            ->where('company_id', (string)Config::get('qms.company_id'))
            ->where('soft_delete', 0)
            ->order('cited_at', 'desc')
            ->select();
    }

    public static function subjectUrl(string $subjectType, string $subjectId): string
    {
        $paths = [
            'quality_event' => '/nonconformity/view',
            'audit' => '/audit_finding/view',
            'complaint' => '/complaint/view',
            'capa' => '/capa/view',
            'management_review' => '/management_review/view',
        ];

        return ($paths[$subjectType] ?? '/dashboard/index') . '?id=' . rawurlencode($subjectId);
    }

    private static function subjectExists(string $subjectType, string $subjectId): bool
    {
        $table = self::SUBJECT_TABLES[$subjectType] ?? '';
        if ($table === '') {
            return false;
        }

        if ($subjectType === 'audit') {
            return \think\facade\Db::name('audit_findings')->alias('f')
                ->join('audit_schedules s', 's.id = f.audit_schedule_id')
                ->join('audit_plans p', 'p.id = s.audit_plan_id')
                ->where('f.id', $subjectId)
                ->where('p.company_id', (string)Config::get('qms.company_id'))
                ->where('f.soft_delete', 0)
                ->where('s.soft_delete', 0)
                ->where('p.soft_delete', 0)
                ->count() > 0;
        }

        return \think\facade\Db::name($table)
            ->where('id', $subjectId)
            ->where('company_id', (string)Config::get('qms.company_id'))
            ->where('soft_delete', 0)
            ->count() > 0;
    }

    private static function limit(string $value, int $length): string
    {
        return function_exists('mb_substr') ? mb_substr($value, 0, $length, 'UTF-8') : substr($value, 0, $length);
    }

    private static function looksLikeCredential(string $value): bool
    {
        foreach (self::CREDENTIAL_VALUE_PATTERNS as $pattern) {
            if (preg_match($pattern, trim($value))) {
                return true;
            }
        }

        return false;
    }
}
