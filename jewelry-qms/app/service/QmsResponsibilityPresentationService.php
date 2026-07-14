<?php
declare(strict_types=1);

namespace app\service;

final class QmsResponsibilityPresentationService
{
    private const LABELS = [
        'version' => [
            'draft' => '草案',
            'pending_approval' => '待签批',
            'effective' => '已生效',
            'superseded' => '已被新版本替代',
            'revoked' => '已撤销',
        ],
        'assignment' => [
            'draft' => '草案',
            'pending_approval' => '待签批',
            'active' => '有效',
            'revoked' => '已撤销',
            'expired' => '已到期',
        ],
        'validation' => [
            'pass' => '可以继续',
            'warning' => '需要补充',
            'blocker' => '暂不能提交',
        ],
        'alignment' => [
            'conflict' => '存在冲突',
            'missing' => '职责缺失',
            'review_required' => '需要人工确认',
            'aligned' => '一致',
            'consistent' => '一致',
            'not_applicable' => '不适用',
        ],
        'duty' => [
            'organize' => '组织',
            'review' => '审核',
            'approve' => '批准',
            'execute' => '执行',
            'verify' => '验证',
            'record_keep' => '记录与归档',
            'countersign' => '会签',
            'inform' => '提供信息',
        ],
        'assignment_mode' => [
            'named_person' => '需提前指定具体人员',
            'activity_instance' => '在具体活动开始时指定',
            'derived_from_scope' => '根据活动范围确定后人工核对',
        ],
        'decision' => [
            'approved' => '已批准',
            'rejected' => '已驳回',
            'pending' => '待处理',
        ],
        'severity' => [
            'warning' => '提醒',
            'blocker' => '阻断',
        ],
        'position' => [
            'company_general_manager' => '公司总经理',
            'lab_director' => '实验室主任',
            'quality_manager' => '质量负责人',
            'technical_manager' => '技术负责人',
            'document_controller' => '资料管理员',
        ],
        'role' => [
            'audit_leader' => '审核组长',
            'internal_auditor' => '内审员',
            'audited_activity_owner' => '被审核活动责任人',
            'audit_follow_up_verifier' => '整改跟踪验证人',
            'management_review_input_owner' => '管理评审输入责任人',
            'management_review_improvement_owner' => '改进措施责任人',
            'risk_owner' => '风险责任人',
            'risk_treatment_owner' => '风险处置责任人',
            'risk_verifier' => '风险措施验证人',
        ],
    ];

    public static function label(string $group, string $value): string
    {
        return self::LABELS[$group][$value] ?? '未识别状态';
    }

    public static function versions(array $versions): array
    {
        foreach ($versions as &$version) {
            $version['status_label'] = self::label('version', (string)($version['status'] ?? ''));
        }
        unset($version);

        return $versions;
    }

    public static function detail(?array $detail): ?array
    {
        if ($detail === null) {
            return null;
        }

        $detail['status_label'] = self::label('version', (string)($detail['status'] ?? ''));
        $responsibilityCount = 0;
        $namedPersonCount = 0;
        $runtimeCount = 0;
        $activities = (array)($detail['activities'] ?? []);
        foreach ($activities as &$activity) {
            $responsibilities = (array)($activity['responsibilities'] ?? []);
            foreach ($responsibilities as &$responsibility) {
                $responsibility['duty_type_label'] = self::label(
                    'duty',
                    (string)($responsibility['duty_type'] ?? '')
                );
                $responsibility['assignment_mode_label'] = self::label(
                    'assignment_mode',
                    (string)($responsibility['assignment_mode'] ?? '')
                );
                $roleCode = (string)(
                    $responsibility['activity_role_code']
                    ?? $responsibility['dynamic_owner_code']
                    ?? ''
                );
                $fixedPositionName = trim((string)($responsibility['fixed_position_name'] ?? ''));
                $responsibility['responsible_party_label'] = $fixedPositionName !== ''
                    ? $fixedPositionName
                    : self::label('role', $roleCode);
                $responsibility['source_refs_label'] = implode(
                    '、',
                    array_map('strval', (array)($responsibility['source_refs'] ?? []))
                );
                $assignments = (array)($responsibility['assignments'] ?? []);
                foreach ($assignments as &$assignment) {
                    $assignment['status_label'] = self::label(
                        'assignment',
                        (string)($assignment['status'] ?? '')
                    );
                }
                unset($assignment);
                $responsibility['assignments'] = $assignments;

                $responsibilityCount++;
                if ((string)($responsibility['assignment_mode'] ?? '') === 'named_person') {
                    $namedPersonCount++;
                } else {
                    $runtimeCount++;
                }
            }
            unset($responsibility);
            $activity['responsibilities'] = $responsibilities;
        }
        unset($activity);
        $detail['activities'] = $activities;

        $detail['summary'] = [
            'activity_count' => count((array)($detail['activities'] ?? [])),
            'responsibility_count' => $responsibilityCount,
            'named_person_count' => $namedPersonCount,
            'runtime_count' => $runtimeCount,
        ];

        return $detail;
    }

    public static function validation(?array $validation): ?array
    {
        if ($validation === null) {
            return null;
        }

        $validation['result_label'] = self::label(
            'validation',
            (string)($validation['result'] ?? '')
        );
        $issues = (array)($validation['issues'] ?? []);
        foreach ($issues as &$issue) {
            $issue['severity_label'] = self::label(
                'severity',
                (string)($issue['severity'] ?? '')
            );
        }
        unset($issue);
        $validation['issues'] = $issues;

        return $validation;
    }

    public static function approvalHistory(array $rows): array
    {
        foreach ($rows as &$row) {
            $row['decision_label'] = self::label('decision', (string)($row['decision'] ?? ''));
            $row['approver_position_label'] = self::label(
                'position',
                (string)($row['approver_position_code'] ?? '')
            );
        }
        unset($row);

        return $rows;
    }

    public static function pendingBatch(?array $batch): ?array
    {
        if ($batch === null) {
            return null;
        }

        $batch['approver']['position_label'] = self::label(
            'position',
            (string)($batch['approver']['position_code'] ?? '')
        );

        return $batch;
    }

    public static function effectiveAppointments(array $rows): array
    {
        foreach ($rows as &$row) {
            $row['evidence_label'] = (string)($row['source_approval_id'] ?? '') === ''
                ? '待核对签批依据'
                : '签批依据完整';
        }
        unset($row);

        return $rows;
    }

    public static function alignment(array $alignment): array
    {
        $findings = (array)($alignment['findings'] ?? []);
        foreach ($findings as &$finding) {
            $finding['status_label'] = self::label(
                'alignment',
                (string)($finding['status'] ?? '')
            );
            $finding['finding_label'] = [
                'Y13-CX20' => '内部审核职责对齐',
                'Y13-CX21' => '管理评审职责对齐',
                'Y13-CX32' => '风险管理职责对齐',
            ][(string)($finding['finding_id'] ?? '')] ?? '职责对齐检查';
        }
        unset($finding);
        $alignment['findings'] = $findings;
        if (isset($alignment['version']['status'])) {
            $alignment['version']['status_label'] = self::label(
                'version',
                (string)$alignment['version']['status']
            );
        }

        return $alignment;
    }
}
