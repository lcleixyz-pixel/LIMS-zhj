<?php
declare(strict_types=1);

namespace app\service;

use app\model\QmsGovernedChange;
use app\model\QmsGovernedChangeRequest;
use InvalidArgumentException;
use RuntimeException;
use think\Model;
use think\facade\Config;
use think\facade\Db;
use think\facade\Session;

final class GovernedChangeService
{
    private const HIDDEN_FIELDS = [
        'id',
        'company_id',
        'password',
        'publish',
        'soft_delete',
        'created',
        'modified',
        'created_by',
        'modified_by',
    ];

    private const FIELD_LABELS = [
        'title' => '标题',
        'name' => '名称',
        'code' => '编号',
        'status' => '状态',
        'training_type' => '培训类型',
        'trainer' => '培训人',
        'training_date' => '培训日期',
        'duration_hours' => '培训学时',
        'content' => '内容',
        'department_id' => '所属部门',
        'employee_id' => '人员',
        'equipment_id' => '设备',
        'supplier_id' => '供应商',
        'evaluation_score' => '评价分数',
        'evaluation_result' => '评价结果',
        'remarks' => '备注',
        'remark' => '备注',
        'description' => '描述',
        'result' => '结果',
        'valid_until' => '有效期至',
        'assessment_date' => '评价日期',
        'review_date' => '评审日期',
        'completion_date' => '完成日期',
    ];

    public static function prepareRequest(string $subjectType, Model $record, array $input): array
    {
        $subjectType = GovernedChangePolicyService::normalizeSubjectType($subjectType);
        $policy = GovernedChangePolicyService::policy($subjectType);
        if (($policy['strategy'] ?? '') !== 'correction') {
            throw new InvalidArgumentException('当前对象不使用字段更正流程。');
        }
        if (!GovernedChangePolicyService::isFrozen($subjectType, $record)) {
            throw new InvalidArgumentException('当前记录尚未冻结，请直接在编辑页修正。');
        }

        $fieldName = trim((string)($input['field_name'] ?? ''));
        $allowedFields = [];
        foreach (self::correctableFields($subjectType, $record) as $field) {
            $allowedFields[(string)$field['name']] = $field;
        }
        if ($fieldName === '' || !isset($allowedFields[$fieldName])) {
            throw new InvalidArgumentException('所选字段不存在或不允许更正，请刷新后重试。');
        }

        $proposedValue = self::scalar($input['proposed_value'] ?? '');
        $reason = trim((string)($input['reason'] ?? ''));
        if ($reason === '') {
            throw new InvalidArgumentException('请填写更正原因。');
        }

        $subjectId = trim((string)($record->getAttr('id') ?? ''));
        $baseValues = $record->getData();
        $currentValues = self::projectValues($baseValues, $subjectId !== ''
            ? self::approvedChanges($subjectType, $subjectId)
            : []);
        $originalValue = self::scalar($currentValues[$fieldName] ?? '');
        if ($proposedValue === $originalValue) {
            throw new InvalidArgumentException('拟更正值与当前有效值相同，无需提交。');
        }

        return [
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'subject_label' => self::subjectLabel($policy, $record),
            'change_kind' => 'correction',
            'field_name' => $fieldName,
            'field_label' => (string)$allowedFields[$fieldName]['label'],
            'original_value' => $originalValue,
            'proposed_value' => $proposedValue,
            'reason' => $reason,
        ];
    }

    public static function createRequest(string $subjectType, Model $record, array $input): QmsGovernedChangeRequest
    {
        if (!self::tablesReady()) {
            throw new RuntimeException('统一更正表尚未初始化，请联系系统管理员。');
        }

        $prepared = self::prepareRequest($subjectType, $record, $input);
        if ($prepared['subject_id'] === '') {
            throw new InvalidArgumentException('当前记录缺少唯一标识，不能提交更正。');
        }

        $duplicate = QmsGovernedChangeRequest::where('company_id', Config::get('qms.company_id'))
            ->where('subject_type', $prepared['subject_type'])
            ->where('subject_id', $prepared['subject_id'])
            ->where('field_name', $prepared['field_name'])
            ->where('status', 'pending')
            ->where('soft_delete', 0)
            ->find();
        if ($duplicate) {
            throw new InvalidArgumentException('该字段已有待处理的更正申请，请先完成审批。');
        }

        $now = date('Y-m-d H:i:s');

        return QmsGovernedChangeRequest::create(array_merge($prepared, [
            'id' => qms_uuid(),
            'company_id' => Config::get('qms.company_id'),
            'status' => 'pending',
            'requested_by' => self::userId(),
            'requested_at' => $now,
            'publish' => 1,
            'soft_delete' => 0,
            'created' => $now,
            'modified' => $now,
        ]));
    }

    public static function decide(string $requestId, string $decision, string $comment = ''): QmsGovernedChangeRequest
    {
        $decision = trim($decision);
        if (!in_array($decision, ['approved', 'rejected'], true)) {
            throw new InvalidArgumentException('请选择批准或驳回。');
        }
        if ($decision === 'rejected' && trim($comment) === '') {
            throw new InvalidArgumentException('驳回时请填写处理意见。');
        }

        return Db::transaction(function () use ($requestId, $decision, $comment): QmsGovernedChangeRequest {
            $request = QmsGovernedChangeRequest::where('company_id', Config::get('qms.company_id'))
                ->where('id', $requestId)
                ->where('soft_delete', 0)
                ->lock(true)
                ->find();
            if (!$request) {
                throw new InvalidArgumentException('更正申请不存在。');
            }
            if ((string)$request->status !== 'pending') {
                throw new InvalidArgumentException('该更正申请已处理，请刷新后查看。');
            }

            $now = date('Y-m-d H:i:s');
            $userId = self::userId();
            $request->save([
                'status' => $decision,
                'decided_by' => $userId,
                'decided_at' => $now,
                'decision_comment' => trim($comment),
                'modified' => $now,
            ]);

            if ($decision === 'approved') {
                QmsGovernedChange::create([
                    'id' => qms_uuid(),
                    'company_id' => Config::get('qms.company_id'),
                    'request_id' => $request->id,
                    'subject_type' => $request->subject_type,
                    'subject_id' => $request->subject_id,
                    'subject_label' => $request->subject_label,
                    'change_kind' => $request->change_kind,
                    'field_name' => $request->field_name,
                    'field_label' => $request->field_label,
                    'old_value' => $request->original_value,
                    'new_value' => $request->proposed_value,
                    'reason' => $request->reason,
                    'registered_by' => $request->requested_by,
                    'registered_at' => $now,
                    'approved_by' => $userId,
                    'approved_at' => $now,
                    'publish' => 1,
                    'soft_delete' => 0,
                    'created' => $now,
                    'modified' => $now,
                ]);
            }

            return $request;
        });
    }

    public static function correctableFields(string $subjectType, Model $record): array
    {
        $policy = GovernedChangePolicyService::policy($subjectType);
        if (($policy['strategy'] ?? '') !== 'correction') {
            return [];
        }

        try {
            $names = array_keys($record->db()->getFields());
        } catch (\Throwable) {
            $names = array_keys($record->getData());
        }

        $fields = [];
        foreach ($names as $name) {
            if (in_array($name, self::HIDDEN_FIELDS, true)) {
                continue;
            }
            $fields[] = [
                'name' => $name,
                'label' => self::fieldLabel($name),
                'value' => self::scalar($record->getAttr($name)),
            ];
        }

        return $fields;
    }

    public static function approvedChanges(string $subjectType, string $subjectId): array
    {
        if (!self::tablesReady() || trim($subjectId) === '') {
            return [];
        }

        return QmsGovernedChange::where('company_id', Config::get('qms.company_id'))
            ->where('subject_type', GovernedChangePolicyService::normalizeSubjectType($subjectType))
            ->where('subject_id', $subjectId)
            ->where('publish', 1)
            ->where('soft_delete', 0)
            ->order('registered_at', 'asc')
            ->order('created', 'asc')
            ->select()
            ->toArray();
    }

    public static function requests(string $subjectType, string $subjectId): array
    {
        if (!self::tablesReady()) {
            return [];
        }

        return QmsGovernedChangeRequest::where('company_id', Config::get('qms.company_id'))
            ->where('subject_type', GovernedChangePolicyService::normalizeSubjectType($subjectType))
            ->where('subject_id', $subjectId)
            ->where('soft_delete', 0)
            ->order('requested_at', 'desc')
            ->select()
            ->toArray();
    }

    public static function pendingRequests(): array
    {
        if (!self::tablesReady()) {
            return [];
        }

        return QmsGovernedChangeRequest::where('company_id', Config::get('qms.company_id'))
            ->where('status', 'pending')
            ->where('soft_delete', 0)
            ->order('requested_at', 'asc')
            ->select()
            ->toArray();
    }

    public static function projectValues(array $baseValues, array $changes): array
    {
        usort($changes, static fn (array $left, array $right): int =>
            strcmp(
                (string)($left['registered_at'] ?? $left['created'] ?? ''),
                (string)($right['registered_at'] ?? $right['created'] ?? '')
            ));
        foreach ($changes as $change) {
            $field = trim((string)($change['field_name'] ?? ''));
            if ($field !== '' && !in_array($field, self::HIDDEN_FIELDS, true)) {
                $baseValues[$field] = $change['new_value'] ?? '';
            }
        }

        return $baseValues;
    }

    public static function tablesReady(): bool
    {
        try {
            return Db::query("SHOW TABLES LIKE 'qms_governed_change_requests'") !== []
                && Db::query("SHOW TABLES LIKE 'qms_governed_changes'") !== [];
        } catch (\Throwable) {
            return false;
        }
    }

    public static function fieldLabel(string $field): string
    {
        return self::FIELD_LABELS[$field] ?? ucwords(str_replace('_', ' ', $field));
    }

    private static function subjectLabel(array $policy, Model $record): string
    {
        $prefix = trim((string)($policy['label'] ?? '业务记录'));
        foreach (['code', 'number', 'title', 'name', 'id'] as $field) {
            try {
                $value = trim(self::scalar($record->getAttr($field)));
            } catch (\Throwable) {
                $value = '';
            }
            if ($value !== '') {
                return $prefix . '｜' . $value;
            }
        }

        return $prefix;
    }

    private static function scalar(mixed $value): string
    {
        if ($value === null) {
            return '';
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        if (is_scalar($value)) {
            return (string)$value;
        }

        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
    }

    private static function userId(): ?string
    {
        $userId = trim((string)Session::get('user.id', ''));

        return $userId !== '' ? $userId : null;
    }
}
