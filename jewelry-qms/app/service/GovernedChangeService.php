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
        'status',
    ];

    private const FIELD_LABELS = [
        'title' => '标题',
        'name' => '名称',
        'code' => '编号',
        'status' => '状态',
        'training_type' => '培训类型',
        'trainer' => '培训人',
        'training_date' => '培训日期',
        'duration_hours' => '学时',
        'content' => '内容',
        'training_plan_id' => '所属培训计划',
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

        $changeKind = trim((string)($input['change_kind'] ?? 'correction'));
        if (!in_array($changeKind, ['supplement', 'correction', 'void_mark'], true)) {
            throw new InvalidArgumentException('请选择补充、更正或作废标注。');
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
        if ($changeKind === 'void_mark' && trim($proposedValue) === '') {
            $proposedValue = '【作废标注】原值保留，不再作为有效值';
        }
        if ($changeKind === 'supplement' && trim($originalValue) !== '') {
            throw new InvalidArgumentException('该字段已有有效值，请选择“更正原值”；补充仅用于原字段为空的情况。');
        }
        if ($changeKind === 'correction' && trim($originalValue) === '') {
            throw new InvalidArgumentException('该字段尚无原值，请选择“补充内容”。');
        }
        if ($proposedValue === $originalValue) {
            throw new InvalidArgumentException('拟更正值与当前有效值相同，无需提交。');
        }

        return [
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'subject_label' => self::subjectLabel($policy, $record),
            'change_kind' => $changeKind,
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

        $baseValues = [];
        foreach ($names as $name) {
            try {
                $baseValues[$name] = $record->getAttr($name);
            } catch (\Throwable) {
                $baseValues[$name] = $record->getData($name) ?? '';
            }
        }

        $subjectId = trim((string)($record->getAttr('id') ?? ''));
        $values = self::projectValues(
            $baseValues,
            $subjectId !== '' ? self::approvedChanges($subjectType, $subjectId) : []
        );
        $fields = [];
        foreach ($names as $name) {
            if (in_array($name, self::HIDDEN_FIELDS, true)) {
                continue;
            }
            $fields[] = [
                'name' => $name,
                'label' => self::fieldLabel($name),
                'value' => self::scalar($values[$name] ?? ''),
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

    public static function pendingRequestsForDisplay(): array
    {
        $rows = self::pendingRequests();
        self::attachUserLabels($rows, ['requested_by']);

        return $rows;
    }

    public static function inboxRequestsForDisplay(bool $canDecide, string $userId): array
    {
        if (!self::tablesReady()) {
            return [];
        }

        $query = QmsGovernedChangeRequest::where('company_id', Config::get('qms.company_id'))
            ->where('soft_delete', 0);
        if ($canDecide) {
            $query->where('status', 'pending')->order('requested_at', 'asc');
        } else {
            if (trim($userId) === '') {
                return [];
            }
            $query->where('requested_by', $userId)->order('requested_at', 'desc');
        }
        $rows = $query->limit(50)->select()->toArray();
        self::attachUserLabels($rows, ['requested_by', 'decided_by']);

        return $rows;
    }

    public static function eventFields(string $subjectType, Model $record): array
    {
        $policy = GovernedChangePolicyService::policy($subjectType);
        if (($policy['strategy'] ?? '') !== 'event') {
            return [];
        }
        try {
            $available = array_keys($record->db()->getFields());
        } catch (\Throwable) {
            $available = array_keys($record->getData());
        }

        $fields = [];
        foreach ((array)($policy['protected_fields'] ?? []) as $field) {
            if (!in_array($field, $available, true)) {
                continue;
            }
            $options = $field === 'status'
                ? (array)Config::get('qms.statusLabels.' . $subjectType, [])
                : [];
            if ($options === []) {
                continue;
            }
            $fields[] = [
                'name' => $field,
                'label' => self::fieldLabel($field),
                'value' => self::scalar($record->getAttr($field)),
                'options' => $options,
            ];
        }

        return $fields;
    }

    public static function recordEvent(string $subjectType, Model $record, array $input): QmsGovernedChange
    {
        $subjectType = GovernedChangePolicyService::normalizeSubjectType($subjectType);
        $policy = GovernedChangePolicyService::policy($subjectType);
        if (($policy['strategy'] ?? '') !== 'event') {
            throw new InvalidArgumentException('当前对象不使用生命周期事件流程。');
        }

        $fieldName = trim((string)($input['field_name'] ?? ''));
        $allowed = [];
        foreach (self::eventFields($subjectType, $record) as $field) {
            $allowed[(string)$field['name']] = $field;
        }
        if ($fieldName === '' || !isset($allowed[$fieldName])) {
            throw new InvalidArgumentException('所选字段不允许通过此事件变更。');
        }
        $newValue = self::scalar($input['new_value'] ?? '');
        $oldValue = self::scalar($record->getAttr($fieldName));
        $reason = trim((string)($input['reason'] ?? ''));
        if ($reason === '') {
            throw new InvalidArgumentException('请填写办理依据或原因。');
        }
        if ($newValue === '' || $newValue === $oldValue) {
            throw new InvalidArgumentException('请选择与当前状态不同的新值。');
        }
        $options = (array)($allowed[$fieldName]['options'] ?? []);
        if ($options !== [] && !array_key_exists($newValue, $options)) {
            throw new InvalidArgumentException('所选状态不在允许范围内。');
        }

        $subjectId = trim((string)$record->getAttr('id'));
        $now = date('Y-m-d H:i:s');
        $userId = self::userId();

        return Db::transaction(function () use (
            $subjectType,
            $subjectId,
            $policy,
            $record,
            $fieldName,
            $oldValue,
            $newValue,
            $reason,
            $now,
            $userId
        ): QmsGovernedChange {
            $record->save([$fieldName => $newValue]);
            $eventId = qms_uuid();

            return QmsGovernedChange::create([
                'id' => $eventId,
                'company_id' => Config::get('qms.company_id'),
                'request_id' => $eventId,
                'subject_type' => $subjectType,
                'subject_id' => $subjectId,
                'subject_label' => self::subjectLabel($policy, $record),
                'change_kind' => 'event',
                'field_name' => $fieldName,
                'field_label' => self::fieldLabel($fieldName),
                'old_value' => $oldValue,
                'new_value' => $newValue,
                'reason' => $reason,
                'registered_by' => $userId,
                'registered_at' => $now,
                'approved_by' => $userId,
                'approved_at' => $now,
                'publish' => 1,
                'soft_delete' => 0,
                'created' => $now,
                'modified' => $now,
            ]);
        });
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

    public static function panelContextFromPage(array $page): array
    {
        $action = strtolower(trim((string)($page['action'] ?? '')));
        $subjectId = trim((string)($page['record_id'] ?? ''));
        $subjectType = GovernedChangePolicyService::normalizeSubjectType(
            (string)($page['module'] ?? $page['controller'] ?? '')
        );
        if ($action !== 'view' || $subjectId === '' || !GovernedChangePolicyService::supports($subjectType)) {
            return [];
        }

        $policy = GovernedChangePolicyService::policy($subjectType);
        $strategy = (string)($policy['strategy'] ?? 'direct');
        if (!in_array($strategy, ['correction', 'event'], true)) {
            return [];
        }
        $record = GovernedChangePolicyService::findSubject($subjectType, $subjectId);
        if (!$record) {
            return [];
        }

        $requests = $strategy === 'correction' ? self::requests($subjectType, $subjectId) : [];
        $changes = self::approvedChanges($subjectType, $subjectId);
        self::attachUserLabels($requests, ['requested_by', 'decided_by']);
        self::attachUserLabels($changes, ['registered_by', 'approved_by']);

        $pending = array_values(array_filter(
            $requests,
            static fn (array $row): bool => (string)($row['status'] ?? '') === 'pending'
        ));

        $eventFields = $strategy === 'event' ? self::eventFields($subjectType, $record) : [];

        return [
            'enabled' => true,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'subject_label' => self::subjectLabel($policy, $record),
            'strategy' => $strategy,
            'frozen' => $strategy === 'correction'
                ? GovernedChangePolicyService::isFrozen($subjectType, $record)
                : false,
            'protected_fields' => array_values((array)($policy['protected_fields'] ?? [])),
            'protected_field_labels' => array_map(
                static fn (string $field): string => self::fieldLabel($field),
                array_values((array)($policy['protected_fields'] ?? []))
            ),
            'event_fields' => $eventFields,
            'event_field' => $eventFields[0] ?? null,
            'fields' => $strategy === 'correction' ? self::correctableFields($subjectType, $record) : [],
            'requests' => $requests,
            'pending_requests' => $pending,
            'changes' => $changes,
            'can_decide' => $pending !== []
                && ActionAuthorizationService::allows('governedchange', 'decide'),
            'return_url' => '/' . qms_controller_url($subjectType) . '/view?id=' . rawurlencode($subjectId),
        ];
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

    private static function attachUserLabels(array &$rows, array $fields): void
    {
        $ids = [];
        foreach ($rows as $row) {
            foreach ($fields as $field) {
                $id = trim((string)($row[$field] ?? ''));
                if ($id !== '') {
                    $ids[] = $id;
                }
            }
        }
        if ($ids === []) {
            return;
        }

        try {
            $users = Db::name('users')
                ->whereIn('id', array_values(array_unique($ids)))
                ->field('id,name,username')
                ->select()
                ->toArray();
            $labels = [];
            foreach ($users as $user) {
                $label = trim((string)($user['name'] ?? ''));
                $labels[(string)$user['id']] = $label !== ''
                    ? $label
                    : (string)($user['username'] ?? $user['id']);
            }
            foreach ($rows as &$row) {
                foreach ($fields as $field) {
                    $id = trim((string)($row[$field] ?? ''));
                    $row[$field . '_label'] = $id !== '' ? ($labels[$id] ?? $id) : '未记录';
                }
            }
            unset($row);
        } catch (\Throwable) {
            // User labels are display-only; the immutable user IDs remain available.
        }
    }
}
