<?php
declare(strict_types=1);

namespace app\service;

use app\model\QmsExternalChangeEvent;
use think\facade\Db;

class ExternalChangeEventService
{
    public const STATUS_REGISTERED = 'registered';
    public const STATUS_ASSESSING = 'assessing';
    public const STATUS_REVISING = 'revising';
    public const STATUS_CLOSED = 'closed';
    public const STATUS_EXEMPTED = 'exempted';

    public static function sourceKindLabels(): array
    {
        return [
            'cnas' => 'CNAS',
            'samr' => '市场监管总局/CMA',
            'standard_platform' => '标准公开平台',
            'gb' => '国家标准',
            'internal' => '内部查新',
            'other' => '其他',
        ];
    }

    public static function statusLabels(): array
    {
        return [
            self::STATUS_REGISTERED => '已登记',
            self::STATUS_ASSESSING => '影响评估中',
            self::STATUS_REVISING => '修订处理中',
            self::STATUS_CLOSED => '已关闭',
            self::STATUS_EXEMPTED => '不适用归档',
        ];
    }

    public static function nextEventCode(): string
    {
        $year = date('Y');
        $prefix = 'QMS-CHG-' . $year . '-';
        $last = QmsExternalChangeEvent::where('event_code', 'like', $prefix . '%')
            ->order('event_code', 'desc')
            ->value('event_code');
        $seq = 1;
        if ($last && preg_match('/-(\d+)$/', (string)$last, $match)) {
            $seq = (int)$match[1] + 1;
        }

        return $prefix . str_pad((string)$seq, 3, '0', STR_PAD_LEFT);
    }

    public static function normalizeInput(array $data, bool $isCreate = true): array
    {
        $allowed = [
            'event_code', 'source_kind', 'source_name', 'source_url', 'announcement_number',
            'old_source_id', 'new_source_id', 'old_version', 'new_version',
            'published_date', 'effective_date', 'event_summary', 'graph_snapshot_hash',
            'status', 'close_reason',
        ];
        $normalized = [];
        foreach ($allowed as $field) {
            if (array_key_exists($field, $data)) {
                $normalized[$field] = is_string($data[$field]) ? trim($data[$field]) : $data[$field];
            }
        }

        foreach (['old_source_id', 'new_source_id', 'published_date', 'effective_date'] as $nullableField) {
            if (($normalized[$nullableField] ?? '') === '') {
                $normalized[$nullableField] = null;
            }
        }

        if ($isCreate) {
            if (($normalized['event_code'] ?? '') === '') {
                $normalized['event_code'] = self::nextEventCode();
            }
            if (($normalized['status'] ?? '') === '') {
                $normalized['status'] = self::STATUS_REGISTERED;
            }
            if (($normalized['graph_snapshot_hash'] ?? '') === '') {
                $normalized['graph_snapshot_hash'] = self::currentGraphSnapshotHash();
            }
        } else {
            unset($normalized['event_code'], $normalized['status'], $normalized['graph_snapshot_hash']);
        }

        return $normalized;
    }

    public static function validateData(array $data, bool $isCreate = true): array
    {
        $errors = [];
        $kindLabels = self::sourceKindLabels();
        $statusLabels = self::statusLabels();

        if (trim((string)($data['source_kind'] ?? '')) === '') {
            $errors[] = '请选择来源类型';
        } elseif (!array_key_exists((string)$data['source_kind'], $kindLabels)) {
            $errors[] = '来源类型不在允许范围内';
        }

        if (trim((string)($data['source_name'] ?? '')) === '') {
            $errors[] = '请填写公告/依据名称';
        }

        if (trim((string)($data['event_summary'] ?? '')) === '') {
            $errors[] = '请填写变更摘要';
        }

        if ($isCreate && trim((string)($data['event_code'] ?? '')) !== '') {
            $exists = QmsExternalChangeEvent::where('event_code', trim((string)$data['event_code']))
                ->where('soft_delete', 0)
                ->count();
            if ((int)$exists > 0) {
                $errors[] = '事件编号已存在';
            }
        }

        if (isset($data['status']) && $data['status'] !== '' && !array_key_exists((string)$data['status'], $statusLabels)) {
            $errors[] = '事件状态不在允许范围内';
        }

        foreach (['published_date' => '发布日期', 'effective_date' => '生效日期'] as $field => $label) {
            $value = trim((string)($data[$field] ?? ''));
            if ($value !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
                $errors[] = $label . '格式应为 YYYY-MM-DD';
            }
        }

        return $errors;
    }

    public static function transition(QmsExternalChangeEvent $event, string $action, string $closeReason = ''): QmsExternalChangeEvent
    {
        $target = match ($action) {
            'assess' => self::STATUS_ASSESSING,
            'revise' => self::STATUS_REVISING,
            'close' => self::STATUS_CLOSED,
            'exempt' => self::STATUS_EXEMPTED,
            'reopen' => self::STATUS_REGISTERED,
            default => '',
        };

        if ($target === '') {
            throw new \InvalidArgumentException('不支持的状态动作');
        }

        $data = ['status' => $target];
        if (in_array($target, [self::STATUS_CLOSED, self::STATUS_EXEMPTED], true)) {
            $data['close_reason'] = $closeReason !== '' ? $closeReason : ($target === self::STATUS_CLOSED ? '影响评估已闭环' : '评估为不适用');
        }
        if ($target === self::STATUS_REGISTERED) {
            $data['close_reason'] = null;
        }

        $event->save($data);

        return $event;
    }

    public static function currentGraphSnapshotHash(): string
    {
        $basis = [
            'qms_sources' => self::tableMetric('qms_sources'),
            'qms_clauses' => self::tableMetric('qms_clauses'),
            'qms_document_blocks' => self::tableMetric('qms_document_blocks'),
            'qms_document_block_links' => self::tableMetric('qms_document_block_links'),
            'record_form_templates' => self::tableMetric('record_form_templates'),
        ];

        return hash('sha256', json_encode($basis, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: serialize($basis));
    }

    private static function tableMetric(string $table): array
    {
        try {
            return [
                'rows' => (int)Db::name($table)->where('soft_delete', 0)->count(),
                'max_modified' => (string)(Db::name($table)->where('soft_delete', 0)->max('modified') ?: ''),
                'max_created' => (string)(Db::name($table)->where('soft_delete', 0)->max('created') ?: ''),
            ];
        } catch (\Throwable $exception) {
            return ['rows' => 0, 'max_modified' => '', 'max_created' => '', 'unavailable' => true];
        }
    }
}
