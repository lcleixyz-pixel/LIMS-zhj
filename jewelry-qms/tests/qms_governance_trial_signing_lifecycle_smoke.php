<?php
declare(strict_types=1);

$root = dirname(__DIR__);
require $root . '/vendor/autoload.php';
require $root . '/app/common.php';

$app = new think\App();
$app->initialize();

use app\model\Document;
use app\service\ApprovalService;
use app\service\DocuSealService;
use think\facade\Config;
use think\facade\Db;

function signing_lifecycle_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

signing_lifecycle_assert(
    method_exists(ApprovalService::class, 'restartDocumentWorkflow'),
    'missing ApprovalService::restartDocumentWorkflow'
);
signing_lifecycle_assert(
    method_exists(ApprovalService::class, 'closeCurrentDocumentWorkflow'),
    'missing ApprovalService::closeCurrentDocumentWorkflow'
);
signing_lifecycle_assert(
    method_exists(DocuSealService::class, 'rejectDocumentWorkflow'),
    'missing DocuSealService::rejectDocumentWorkflow'
);
signing_lifecycle_assert(
    method_exists(DocuSealService::class, 'canStartAnotherSigningRound'),
    'missing DocuSealService::canStartAnotherSigningRound'
);

Config::set([
    'enabled' => true,
    'batch' => 'GOV-TRIAL-20260724',
], 'qms.trial_mode');
Config::set([
    'signing_enabled' => true,
    'base_url' => 'http://docuseal.mock',
    'public_base_url' => 'http://127.0.0.1:3101',
    'api_key' => 'mock',
    'webhook_secret' => 'governance-trial-secret',
    'template_id' => 1,
    'send_email' => false,
    'mock' => true,
], 'qms.docuseal');

$companyId = (string)(Db::name('companies')->where('soft_delete', 0)->value('id') ?: '');
signing_lifecycle_assert($companyId !== '', 'company required');

$suffix = substr(str_replace('-', '', qms_uuid()), 0, 10);
$now = date('Y-m-d H:i:s');
$employeeIds = [
    'preparer' => qms_uuid(),
    'reviewer' => qms_uuid(),
    'approver' => qms_uuid(),
];
$userIds = [
    'preparer' => qms_uuid(),
    'reviewer' => qms_uuid(),
    'approver' => qms_uuid(),
];
$emails = [
    'preparer' => "sim-preparer-{$suffix}@qms.invalid",
    'reviewer' => "sim-reviewer-{$suffix}@qms.invalid",
    'approver' => "sim-approver-{$suffix}@qms.invalid",
];
$docId = qms_uuid();
$docNumber = 'SIM-SIGN-LIFECYCLE-' . strtoupper($suffix);

try {
    foreach (['preparer', 'reviewer', 'approver'] as $role) {
        Db::name('employees')->insert([
            'id' => $employeeIds[$role],
            'company_id' => $companyId,
            'employee_number' => 'SIM-' . strtoupper($role) . '-' . $suffix,
            'name' => 'SIM ' . ucfirst($role),
            'email' => $emails[$role],
            'publish' => 1,
            'soft_delete' => 0,
            'created' => $now,
            'modified' => $now,
        ]);
        Db::name('users')->insert([
            'id' => $userIds[$role],
            'company_id' => $companyId,
            'employee_id' => $employeeIds[$role],
            'username' => 'sim_' . $role . '_' . $suffix,
            'password' => password_hash('OnlyForLifecycle@8021!', PASSWORD_DEFAULT),
            'name' => 'SIM ' . ucfirst($role),
            'email' => $emails[$role],
            'role' => $role === 'preparer' ? 'quality_manager' : 'staff',
            'is_approver' => $role === 'approver' ? 1 : 0,
            'publish' => 1,
            'soft_delete' => 0,
            'created' => $now,
            'modified' => $now,
        ]);
    }

    Db::name('documents')->insert([
        'id' => $docId,
        'company_id' => $companyId,
        'level' => 1,
        'doc_number' => $docNumber,
        'title' => 'SIM 电子签批生命周期测试',
        'version' => 'A/0',
        'status' => 'draft',
        'prepared_by' => $employeeIds['preparer'],
        'reviewed_by' => $employeeIds['reviewer'],
        'approved_by' => $employeeIds['approver'],
        'publish' => 0,
        'soft_delete' => 0,
        'created' => $now,
        'modified' => $now,
    ]);

    ApprovalService::createWorkflow(
        'document',
        'Document',
        $docId,
        1,
        $userIds['preparer'],
        $userIds['reviewer'],
        $userIds['approver']
    );

    $roundOne = ApprovalService::currentWorkflowRound('Document', $docId);
    signing_lifecycle_assert($roundOne === 1, 'initial workflow round must be 1');
    signing_lifecycle_assert(
        (int)Db::name('approvals')->where('record', $docId)->where('record_status', 1)
            ->where('workflow_round', 1)->count() === 3,
        'initial workflow must contain preparer, reviewer and approver'
    );

    $reviewApprovalId = (string)Db::name('approvals')
        ->where('record', $docId)
        ->where('workflow_round', 1)
        ->where('approval_level', 2)
        ->value('id');
    signing_lifecycle_assert(
        ApprovalService::processApproval($reviewApprovalId, 'approved', '审核同意', $userIds['reviewer']),
        'reviewer approval failed'
    );
    signing_lifecycle_assert(
        !ApprovalService::isFullyApproved('Document', $docId, 1),
        'reviewer completion must not finalize before approver'
    );

    $service = new DocuSealService([
        'signing_enabled' => true,
        'base_url' => 'http://docuseal.mock',
        'public_base_url' => 'http://127.0.0.1:3101',
        'api_key' => 'mock',
        'webhook_secret' => 'governance-trial-secret',
        'template_id' => 1,
        'send_email' => false,
        'mock' => true,
    ]);
    $rejected = $service->rejectDocumentWorkflow(
        $docId,
        'sim-submission-round-1',
        [$emails['approver']],
        '批准人要求补充修订说明'
    );
    signing_lifecycle_assert(($rejected['ok'] ?? false) === true, 'rejection workflow failed');
    signing_lifecycle_assert(
        (string)Db::name('documents')->where('id', $docId)->value('status') === 'draft',
        'rejection must return document to draft'
    );
    signing_lifecycle_assert(
        (int)Db::name('approvals')->where('record', $docId)->where('record_status', 1)->count() === 0,
        'rejection must close all current approvals'
    );

    $roundTwo = ApprovalService::restartDocumentWorkflow(
        Document::find($docId),
        $userIds['preparer']
    );
    signing_lifecycle_assert($roundTwo === 2, 'resubmission must create workflow round 2');
    Db::name('documents')->where('id', $docId)->update(['status' => 'reviewing', 'modified' => date('Y-m-d H:i:s')]);
    signing_lifecycle_assert(
        (int)Db::name('approvals')->where('record', $docId)->where('record_status', 1)
            ->where('workflow_round', 2)->count() === 3,
        'resubmitted workflow must have three active approvals'
    );

    $roundTwoReview = (string)Db::name('approvals')
        ->where('record', $docId)
        ->where('record_status', 1)
        ->where('workflow_round', 2)
        ->where('approval_level', 2)
        ->value('id');
    $roundTwoApprove = (string)Db::name('approvals')
        ->where('record', $docId)
        ->where('record_status', 1)
        ->where('workflow_round', 2)
        ->where('approval_level', 3)
        ->value('id');
    signing_lifecycle_assert(
        ApprovalService::processApproval($roundTwoReview, 'approved', '重新审核同意', $userIds['reviewer']),
        'round 2 reviewer approval failed'
    );
    signing_lifecycle_assert(
        !ApprovalService::isFullyApproved('Document', $docId, 1),
        'old round approval must not count toward round 2'
    );
    signing_lifecycle_assert(
        ApprovalService::processApproval($roundTwoApprove, 'approved', '重新批准同意', $userIds['approver']),
        'round 2 approver approval failed'
    );
    signing_lifecycle_assert(
        ApprovalService::finalizeDocumentIfFullyApproved(Document::find($docId)),
        'round 2 full approval must finalize'
    );
    signing_lifecycle_assert(
        (string)Db::name('documents')->where('id', $docId)->value('status') === 'trial_ready',
        'SIM document must finish as trial_ready'
    );
    $service->recordSigningRound($docId, 'rejected', 'sim-limit-2');
    $service->recordSigningRound($docId, 'rejected', 'sim-limit-3');
    signing_lifecycle_assert(
        !$service->canStartAnotherSigningRound($docId),
        'three rejected rounds must block another resubmission'
    );
} finally {
    Db::name('qms_document_assets')->where('document_id', $docId)->delete();
    if (Db::query("SHOW TABLES LIKE 'document_signing_rounds'") !== []) {
        Db::name('document_signing_rounds')->where('document_id', $docId)->delete();
    }
    Db::name('approvals')->where('record', $docId)->delete();
    Db::name('documents')->where('id', $docId)->delete();
    Db::name('users')->whereIn('id', array_values($userIds))->delete();
    Db::name('employees')->whereIn('id', array_values($employeeIds))->delete();
}

echo "qms_governance_trial_signing_lifecycle_smoke passed\n";
