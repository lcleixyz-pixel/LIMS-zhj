<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$view = (string)file_get_contents($root . '/app/view/document/view.html');
$controller = (string)file_get_contents($root . '/app/controller/Document.php');
$loginController = (string)file_get_contents($root . '/app/controller/Login.php');
$approvalController = (string)file_get_contents($root . '/app/controller/Approval.php');
$webhookController = (string)file_get_contents($root . '/app/controller/DocuSealWebhook.php');
$approvalService = (string)file_get_contents($root . '/app/service/ApprovalService.php');
$fieldAuditService = (string)file_get_contents($root . '/app/service/FieldAuditService.php');
$friendlyPresentation = $view . "\n" . $approvalService;

function signing_ui_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

foreach ([
    '我的任务',
    '已审核，等待批准',
    '修改后重新提交',
    '审核人（我）',
    '批准人（我）',
    '打开签字页',
] as $requiredText) {
    signing_ui_assert(
        str_contains($friendlyPresentation, $requiredText),
        'missing friendly signing text: ' . $requiredText
    );
}

foreach ([
    'DocuSeal 签批',
    'embed，免 SMTP',
    '不依赖邮件',
] as $forbiddenText) {
    signing_ui_assert(
        !str_contains($view, $forbiddenText),
        'technical wording exposed in business view: ' . $forbiddenText
    );
}

signing_ui_assert(
    str_contains($view, 'for="approval-comments-{$row.id}"'),
    'approval comment input must have an explicit label'
);
signing_ui_assert(
    str_contains($view, 'aria-current="step"'),
    'current workflow step must be exposed to assistive technology'
);
signing_ui_assert(
    !str_contains($view, '{*'),
    'template comments must not leak into the rendered page'
);
signing_ui_assert(
    !str_contains($view, '<iframe'),
    'cross-origin signing page must not be embedded as a broken iframe'
);
signing_ui_assert(
    str_contains($controller, "'signingStatus'")
        && str_contains($controller, 'ApprovalService::documentWorkflowStatus'),
    'controller must provide signingStatus presentation data'
);
signing_ui_assert(
    str_contains($loginController, "'email' => strtolower(trim((string)")
        && str_contains($controller, "User::where('id',"),
    'current signer email must survive login and support existing sessions'
);
signing_ui_assert(
    str_contains($controller, 'ApprovalService::hasActiveDocumentWorkflow')
        && str_contains($controller, 'ApprovalService::restartDocumentWorkflow')
        && str_contains($controller, 'canStartAnotherSigningRound'),
    'document resubmission must start a fresh workflow round'
);
signing_ui_assert(
    str_contains($webhookController, 'rejectDocumentWorkflow'),
    'webhook rejection must use the shared rejection workflow'
);
signing_ui_assert(
    !str_contains($webhookController, 'DocuSeal webhook completed')
        && str_contains($webhookController, '在线签字已完成'),
    'webhook completion must use business language'
);
signing_ui_assert(
    str_contains($webhookController, 'fetchCompletedSubmissionDocument')
        && str_contains($webhookController, "'signed_document'"),
    'completed signing must archive the signed PDF as a controlled asset'
);
signing_ui_assert(
    str_contains($fieldAuditService, '系统自动处理'),
    'background status changes must not appear as an unknown user'
);
signing_ui_assert(
    str_contains($approvalController, 'rejectDocumentWorkflow'),
    'manual rejection must use the shared rejection workflow'
);

echo "qms_governance_trial_signing_ui_smoke passed\n";
