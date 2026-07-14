<?php
declare(strict_types=1);

$root = dirname(__DIR__);
require $root . '/vendor/autoload.php';

use app\service\QmsResponsibilityPresentationService as Presenter;

function presentation_assert_same(string $expected, string $actual, string $message): void
{
    if ($expected !== $actual) {
        fwrite(STDERR, $message . " expected={$expected} actual={$actual}\n");
        exit(1);
    }
}

$cases = [
    ['version', 'draft', '草案'],
    ['version', 'pending_approval', '待签批'],
    ['version', 'effective', '已生效'],
    ['version', 'superseded', '已被新版本替代'],
    ['version', 'revoked', '已撤销'],
    ['assignment', 'active', '有效'],
    ['assignment', 'expired', '已到期'],
    ['validation', 'pass', '可以继续'],
    ['validation', 'warning', '需要补充'],
    ['validation', 'blocker', '暂不能提交'],
    ['alignment', 'conflict', '存在冲突'],
    ['alignment', 'review_required', '需要人工确认'],
    ['alignment', 'aligned', '一致'],
    ['duty', 'organize', '组织'],
    ['duty', 'review', '审核'],
    ['duty', 'approve', '批准'],
    ['duty', 'execute', '执行'],
    ['duty', 'verify', '验证'],
    ['duty', 'record_keep', '记录与归档'],
    ['duty', 'countersign', '会签'],
    ['duty', 'inform', '提供信息'],
    ['assignment_mode', 'named_person', '需提前指定具体人员'],
    ['assignment_mode', 'activity_instance', '在具体活动开始时指定'],
    ['assignment_mode', 'derived_from_scope', '根据活动范围确定后人工核对'],
    ['decision', 'approved', '已批准'],
    ['decision', 'rejected', '已驳回'],
    ['decision', 'pending', '待处理'],
    ['severity', 'warning', '提醒'],
    ['severity', 'blocker', '阻断'],
    ['position', 'company_general_manager', '公司总经理'],
    ['position', 'lab_director', '实验室主任'],
    ['position', 'quality_manager', '质量负责人'],
    ['position', 'technical_manager', '技术负责人'],
    ['position', 'document_controller', '资料管理员'],
    ['role', 'audit_leader', '审核组长'],
    ['role', 'internal_auditor', '内审员'],
    ['role', 'audited_activity_owner', '被审核活动责任人'],
    ['role', 'audit_follow_up_verifier', '整改跟踪验证人'],
    ['role', 'management_review_input_owner', '管理评审输入责任人'],
    ['role', 'management_review_improvement_owner', '改进措施责任人'],
    ['role', 'risk_owner', '风险责任人'],
    ['role', 'risk_treatment_owner', '风险处置责任人'],
    ['role', 'risk_verifier', '风险措施验证人'],
];

foreach ($cases as [$group, $value, $expected]) {
    presentation_assert_same($expected, Presenter::label($group, $value), "label {$group}.{$value}");
}
presentation_assert_same('未识别状态', Presenter::label('version', 'unexpected'), 'unknown value fallback');

echo "qms_responsibility_presentation_smoke passed\n";
