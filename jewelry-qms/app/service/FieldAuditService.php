<?php
declare(strict_types=1);

namespace app\service;

use app\exception\FieldAuditException;
use think\Model;
use think\facade\Db;
use think\facade\Log;
use think\facade\Session;

class FieldAuditService
{
    protected static array $auditFields = [
        'Document' => ['status', 'version', 'revision', 'effective_date', 'approved_by'],
        'Capa' => [
            'status', 'root_cause', 'corrective_action', 'preventive_action', 'assigned_to', 'due_date',
            'verification', 'verified_by', 'verified_date', 'effectiveness_review_date', 'effectiveness_result',
        ],
        'Equipment' => ['status', 'next_calibration_date', 'last_calibration_date', 'site_id'],
        'AuditFinding' => ['description', 'responsible_id', 'due_date', 'status', 'capa_id'],
        'Nonconformity' => [
            'source', 'severity', 'description', 'impact_assessment', 'immediate_action',
            'disposition', 'assigned_to', 'status', 'capa_id',
        ],
        'CustomerComplaint' => [
            'customer_name', 'report_number', 'description', 'investigation', 'handling', 'response',
            'assigned_to', 'due_date', 'status', 'capa_id', 'closed_date',
        ],
        'CompetencyRecord' => [
            'employee_id', 'test_item', 'method_standard', 'assessment_date', 'assessor_id',
            'result', 'authorization_scope', 'valid_until',
        ],
        'QmsExternalChangeCandidate' => [
            'review_status', 'reviewed_by', 'reviewed_at', 'review_comment',
            'promoted_event_id', 'promoted_at',
        ],
        'QmsExternalChangeEvent' => ['status', 'old_source_id', 'new_source_id', 'effective_date', 'graph_snapshot_hash', 'close_reason'],
    ];

    protected static array $sensitiveFields = ['password'];
    protected static array $jsonFields = ['field_values', 'participants'];
    protected static ?bool $tableReady = null;

    public static function shouldAuditModel(Model $model): bool
    {
        return self::auditFieldsFor($model) !== [];
    }

    public static function auditFieldsFor(Model|string $model): array
    {
        $modelName = self::modelName($model);

        return self::$auditFields[$modelName] ?? [];
    }

    public static function modelDisplayName(Model|string $model): string
    {
        return self::modelName($model);
    }

    public static function capture(Model $model): void
    {
        if (!self::shouldAuditModel($model)) {
            return;
        }

        try {
            if (!self::isTableReady()) {
                throw new \RuntimeException('field_change_logs table is unavailable');
            }

            $modelName = self::modelName($model);
            $recordId = (string)($model->getData('id') ?? '');
            if ($recordId === '') {
                return;
            }

            $origin = $model->getOrigin();
            $changed = $model->getChangedData();
            $allowedFields = array_flip(self::$auditFields[$modelName] ?? []);
            $now = date('Y-m-d H:i:s');
            $rows = [];

            foreach ($changed as $field => $newValue) {
                if (!isset($allowedFields[$field]) || in_array($field, self::$sensitiveFields, true)) {
                    continue;
                }
                $oldValue = $origin[$field] ?? null;
                if (self::valuesEquivalent((string)$field, $oldValue, $newValue)) {
                    continue;
                }
                $rows[] = [
                    'id' => qms_uuid(),
                    'model_name' => $modelName,
                    'record_id' => $recordId,
                    'field_name' => $field,
                    'old_value' => self::formatAuditValue($field, $oldValue),
                    'new_value' => self::formatAuditValue($field, $newValue),
                    'changed_by' => Session::get('user.id'),
                    'changed_at' => $now,
                ];
            }

            if ($rows !== []) {
                Db::name('field_change_logs')->insertAll($rows);
            }
        } catch (FieldAuditException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            Log::error('Field audit capture failed: ' . $exception->getMessage());
            throw new FieldAuditException($exception);
        }
    }

    public static function logsFor(string $modelName, string $recordId, int $limit = 50): array
    {
        try {
            if (!self::isTableReady()) {
                return [];
            }

            return Db::name('field_change_logs')
                ->where('model_name', $modelName)
                ->where('record_id', $recordId)
                ->order('changed_at', 'desc')
                ->order('id', 'desc')
                ->limit($limit)
                ->select()
                ->toArray();
        } catch (\Throwable $exception) {
            Log::error('Field audit query failed: ' . $exception->getMessage());

            return [];
        }
    }

    public static function displayLogsFor(string $modelName, string $recordId, int $limit = 50): array
    {
        $rows = self::logsFor($modelName, $recordId, $limit);
        if ($rows === []) {
            return [];
        }

        $userIds = array_values(array_unique(array_filter(array_map(
            static fn (array $row): string => trim((string)($row['changed_by'] ?? '')),
            $rows
        ))));
        $users = $userIds === []
            ? []
            : Db::name('users')->whereIn('id', $userIds)->column('name,employee_id', 'id');
        $employeeIds = array_values(array_unique(array_filter(array_map(
            static fn (array $row): string => trim((string)($row['employee_id'] ?? '')),
            $users
        ))));
        $positions = [];
        if ($employeeIds !== []) {
            $appointments = Db::name('employee_appointments')
                ->whereIn('employee_id', $employeeIds)
                ->where('status', 'active')
                ->where('publish', 1)
                ->where('soft_delete', 0)
                ->where(function ($query) {
                    $query->whereNull('appointed_at')->whereOr('appointed_at', '<=', date('Y-m-d'));
                })
                ->where(function ($query) {
                    $query->whereNull('valid_until')->whereOr('valid_until', '>=', date('Y-m-d'));
                })
                ->order('appointed_at', 'desc')
                ->select()
                ->toArray();
            foreach ($appointments as $appointment) {
                $employeeId = (string)$appointment['employee_id'];
                $positionName = trim((string)$appointment['position_name']);
                if ($positionName !== '' && !in_array($positionName, $positions[$employeeId] ?? [], true)) {
                    $positions[$employeeId][] = $positionName;
                }
            }
        }

        foreach ($rows as &$row) {
            $field = (string)$row['field_name'];
            $userId = trim((string)($row['changed_by'] ?? ''));
            $user = $users[$userId] ?? [];
            $employeeId = trim((string)($user['employee_id'] ?? ''));
            $name = trim((string)($user['name'] ?? ''));
            $position = implode('、', $positions[$employeeId] ?? []);
            $row['field_label'] = self::fieldLabel($modelName, $field);
            $row['old_value_display'] = self::displayValue($modelName, $field, $row['old_value'] ?? null);
            $row['new_value_display'] = self::displayValue($modelName, $field, $row['new_value'] ?? null);
            $row['changed_by_name'] = $name !== '' ? $name : '未知用户';
            $row['changed_by_position'] = $position !== '' ? $position : '岗位未登记';
            $row['changed_by_display'] = $row['changed_by_name'] . '（' . $row['changed_by_position'] . '）';
        }
        unset($row);

        return $rows;
    }

    public static function formatAuditValue(string $field, mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        if (in_array($field, self::$jsonFields, true) || is_array($value) || is_object($value)) {
            return '[已变更]';
        }

        $text = (string)$value;
        if (mb_strlen($text, 'UTF-8') > 500) {
            return mb_substr($text, 0, 500, 'UTF-8') . '[...截断]';
        }

        return $text;
    }

    private static function valuesEquivalent(string $field, mixed $old, mixed $new): bool
    {
        $oldEmpty = $old === null || $old === '';
        $newEmpty = $new === null || $new === '';
        if ($oldEmpty || $newEmpty) {
            return $oldEmpty && $newEmpty;
        }
        if (str_ends_with($field, '_date') || in_array($field, ['effective_date', 'valid_until'], true)) {
            return substr((string)$old, 0, 10) === substr((string)$new, 0, 10);
        }
        if (is_bool($old) || is_bool($new)) {
            return (int)$old === (int)$new;
        }
        if (is_array($old) || is_array($new) || is_object($old) || is_object($new)) {
            return json_encode($old, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                === json_encode($new, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        return (string)$old === (string)$new;
    }

    private static function fieldLabel(string $modelName, string $field): string
    {
        $labels = [
            'status' => '状态',
            'source' => '来源',
            'severity' => '严重程度',
            'description' => '描述',
            'impact_assessment' => '影响评估',
            'immediate_action' => '即时措施',
            'disposition' => '处置决定',
            'assigned_to' => '责任人',
            'capa_id' => '关联 CAPA',
            'customer_name' => '客户名称',
            'report_number' => '报告编号',
            'investigation' => '调查情况',
            'handling' => '处理措施',
            'response' => '客户反馈',
            'due_date' => '处理期限',
            'closed_date' => '关闭日期',
            'employee_id' => '被评价人员',
            'test_item' => '检测项目',
            'method_standard' => '方法/标准',
            'assessment_date' => '评价日期',
            'assessor_id' => '评价人',
            'result' => '评价结果',
            'authorization_scope' => '授权范围',
            'valid_until' => '有效期至',
            'review_status' => '人工确认状态',
            'reviewed_by' => '确认人',
            'reviewed_at' => '确认时间',
            'review_comment' => '确认备注',
            'promoted_event_id' => '正式变更事件',
            'promoted_at' => '转入时间',
            'root_cause' => '根本原因',
            'corrective_action' => '纠正措施',
            'preventive_action' => '预防措施',
            'verification' => '效果验证',
            'verified_by' => '验证人',
            'verified_date' => '验证日期',
            'effectiveness_review_date' => '有效性复查日期',
            'effectiveness_result' => '有效性复查结果',
            'next_calibration_date' => '下次校准日期',
            'last_calibration_date' => '上次校准日期',
            'site_id' => '场所',
        ];

        return $labels[$field] ?? ucwords(str_replace('_', ' ', $field));
    }

    private static function displayValue(string $modelName, string $field, mixed $value): string
    {
        if ($value === null || $value === '') {
            return '-';
        }
        $text = (string)$value;
        $statusLabels = [
            'CompetencyRecord.result' => [
                'pending' => '待评价',
                'qualified' => '合格',
                'unqualified' => '不合格',
                'supervised' => '监督下操作',
            ],
            'QmsExternalChangeCandidate.review_status' => [
                'pending' => '待人工确认',
                'confirmed_applicable' => '确认适用',
                'confirmed_not_applicable' => '确认不适用',
                'deferred' => '暂缓',
                'promoted' => '已转正式变更事件',
            ],
        ];
        $mapKey = $modelName . '.' . $field;
        if (isset($statusLabels[$mapKey][$text])) {
            return $statusLabels[$mapKey][$text];
        }
        if ($field === 'status') {
            $module = [
                'Equipment' => 'equipment',
                'Nonconformity' => 'nonconformity',
                'CustomerComplaint' => 'complaint',
                'Capa' => 'capa',
                'AuditFinding' => 'audit_finding',
                'QmsExternalChangeEvent' => 'planning_change_event',
            ][$modelName] ?? '';
            return $module !== '' ? qms_status_label($module, $text) : $text;
        }
        if (in_array($field, ['assigned_to', 'assessor_id', 'reviewed_by', 'verified_by', 'approved_by'], true)) {
            return (string)(Db::name('users')->where('id', $text)->value('name') ?: '未知用户');
        }
        if ($field === 'employee_id') {
            return (string)(Db::name('employees')->where('id', $text)->value('name') ?: '未知人员');
        }
        if ($field === 'site_id') {
            return (string)(Db::name('sites')->where('id', $text)->value('name') ?: '未知场所');
        }
        if ($field === 'capa_id') {
            return (string)(Db::name('capas')->where('id', $text)->value('capa_number') ?: '未知 CAPA');
        }
        if ($field === 'promoted_event_id') {
            return (string)(Db::name('qms_external_change_events')->where('id', $text)->value('event_code') ?: '未知变更事件');
        }

        return $text;
    }

    protected static function modelName(Model|string $model): string
    {
        if (is_string($model)) {
            $parts = explode('\\', $model);

            return end($parts) ?: $model;
        }

        $class = get_class($model);
        $parts = explode('\\', $class);

        return end($parts) ?: $class;
    }

    protected static function isTableReady(): bool
    {
        if (self::$tableReady !== null) {
            return self::$tableReady;
        }

        try {
            Db::name('field_change_logs')->limit(1)->select();
            self::$tableReady = true;
        } catch (\Throwable $exception) {
            self::$tableReady = false;
        }

        return self::$tableReady;
    }
}
