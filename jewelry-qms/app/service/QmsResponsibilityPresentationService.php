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
            'review_required' => '需要人工确认',
            'aligned' => '一致',
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
}
