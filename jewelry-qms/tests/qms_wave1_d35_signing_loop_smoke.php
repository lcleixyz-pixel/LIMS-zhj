<?php
declare(strict_types=1);

/**
 * D-3/D-5 回环：startSigning → mock submission → processApproval 链路 + 驳回上限 + G-3 不破。
 */
$root = dirname(__DIR__);
require $root . '/vendor/autoload.php';
require $root . '/app/common.php';

$app = new think\App();
$app->initialize();

use app\model\Approval;
use app\model\Document;
use app\model\User;
use app\service\ApprovalService;
use app\service\DocumentStatusGuardService;
use app\service\DocuSealService;
use think\facade\Config;
use think\facade\Db;

function assert_true(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

Config::set([
    'signing_enabled' => true,
    'base_url' => 'http://docuseal.mock',
    'public_base_url' => 'http://127.0.0.1:3100',
    'api_key' => 'mock',
    'webhook_secret' => 'wave1-d35-secret',
    'template_id' => 1,
    'send_email' => false,
    'mock' => true,
], 'qms.docuseal');
// 本 smoke 用单票闭环验证 D-5 链路（生产仍按 approvalRules）
Config::set(['approvalRules' => [1 => 1, 2 => 1, 3 => 1, 4 => 1]], 'qms');

$companyId = (string)(Db::name('companies')->where('soft_delete', 0)->value('id') ?: '');
if ($companyId === '') {
    $companyId = '00000000-0000-0000-0000-000000000001';
}

$user = User::where('soft_delete', 0)->where('email', '<>', '')->order('created', 'asc')->find();
assert_true($user !== null && trim((string)$user->email) !== '', 'need a user with email for D-5 mapping');
$employeeId = trim((string)$user->employee_id);
assert_true($employeeId !== '', 'signer user must have employee_id');

$docId = qms_uuid();
$docNumber = 'D35-' . substr(str_replace('-', '', $docId), 0, 8);
$now = date('Y-m-d H:i:s');

Db::name('documents')->insert([
    'id' => $docId,
    'company_id' => $companyId,
    'doc_number' => $docNumber,
    'title' => 'D35 signing loop smoke',
    'version' => '1.0',
    'level' => 1,
    'status' => 'draft',
    'reviewed_by' => $employeeId,
    'approved_by' => $employeeId,
    'publish' => 0,
    'soft_delete' => 0,
    'created' => $now,
    'modified' => $now,
]);

$approvalId = qms_uuid();
Db::name('approvals')->insert([
    'id' => $approvalId,
    'company_id' => $companyId,
    'model_name' => 'Document',
    'controller_name' => 'Document',
    'record' => $docId,
    'user_id' => $user->id,
    'approval_level' => 1,
    'status' => 'pending',
    'soft_delete' => 0,
    'created' => $now,
    'modified' => $now,
]);

$doc = Document::find($docId);
assert_true($doc !== null, 'document inserted');

$service = new DocuSealService([
    'signing_enabled' => true,
    'base_url' => 'http://docuseal.mock',
    'public_base_url' => 'http://127.0.0.1:3100',
    'api_key' => 'mock',
    'webhook_secret' => 'wave1-d35-secret',
    'template_id' => 1,
    'send_email' => false,
    'mock' => true,
]);

$started = $service->startSigningForDocument($doc);
assert_true(($started['ok'] ?? false) === true, 'D-3 mock createSubmission ok');
assert_true(($started['submission_id'] ?? '') !== '', 'D-3 submission_id present');
assert_true(($started['content_sha256'] ?? '') !== '', 'D-3 content sha present');
assert_true(is_array($started['embeds'] ?? null) && count($started['embeds']) >= 1, 'D-3 embed links present');
assert_true(str_contains((string)($started['embeds'][0]['embed_src'] ?? ''), '/s/'), 'D-3 embed_src path');

$pendingRound = (int)Db::name('document_signing_rounds')
    ->where('document_id', $docId)
    ->where('decision', 'pending')
    ->count();
assert_true($pendingRound >= 1, 'D-3 records pending signing round');

$note = (string)Db::name('document_signing_rounds')
    ->where('document_id', $docId)
    ->where('decision', 'pending')
    ->order('round_no', 'desc')
    ->value('note');
assert_true(str_contains($note, 'submission_created_embed') || str_contains($note, 'embed_src'), 'D-3 note stores embed payload');

$embedsReload = $service->latestEmbedsForDocument($docId);
assert_true(count($embedsReload) >= 1, 'latestEmbedsForDocument reads note JSON');

$downloaded = $service->fetchCompletedSubmissionDocument((string)$started['submission_id']);
assert_true(($downloaded['ok'] ?? false) === true, 'D-5 fetches the completed signed document');
$content = (string)($downloaded['content'] ?? '');
$sha = (string)($downloaded['content_sha256'] ?? '');
assert_true($content !== '' && hash('sha256', $content) === $sha, 'D-5 completed document hash matches');
$stored = $service->storeSignedAsset([
    'document_id' => $docId,
    'company_id' => $companyId,
    'content' => $content,
    'expected_sha256' => $sha,
    'submission_id' => (string)$started['submission_id'],
    'original_name' => (string)($downloaded['filename'] ?? 'signed-d35.pdf'),
]);
assert_true(($stored['ok'] ?? false) === true, 'D-5 storeSignedAsset ok');

$service->recordSigningRound($docId, 'approved', (string)$started['submission_id']);

assert_true(
    ApprovalService::processApproval($approvalId, 'approved', 'DocuSeal webhook completed', (string)$user->id),
    'D-5 processApproval as signer user'
);
$finalized = ApprovalService::finalizeDocumentIfFullyApproved(Document::find($docId));
assert_true($finalized === true, 'D-5 finalizeDocumentIfFullyApproved');

$docAfter = Document::find($docId);
assert_true((string)$docAfter->status === 'published', 'D-5 status via finalize not direct write: ' . $docAfter->status);

$assetCount = (int)Db::name('qms_document_assets')
    ->where('document_id', $docId)
    ->where('source_kind', 'signed_document')
    ->count();
assert_true($assetCount >= 1, 'signed asset stored');

// 驳回 3 轮后拒绝继续
$rejectDoc = qms_uuid();
for ($i = 0; $i < DocuSealService::MAX_REJECT_ROUNDS; $i++) {
    $r = $service->recordSigningRound($rejectDoc, 'rejected', 'rej-' . $i);
    assert_true(($r['ok'] ?? false) === true, 'reject round ' . ($i + 1));
}
$blocked = $service->recordSigningRound($rejectDoc, 'rejected', 'rej-overflow');
assert_true(($blocked['ok'] ?? true) === false && ($blocked['error'] ?? '') === 'reject_limit', 'reject limit after 3');

// G-3 不破：直写 approved 仍阻断
$guard = new DocumentStatusGuardService();
$blockedWrite = $guard->guardWrite(['status' => 'approved'], 'Document', 'edit');
assert_true(($blockedWrite['allowed'] ?? true) === false, 'G-3 still blocks Document edit → approved');

// 清理
Db::name('qms_document_assets')->where('document_id', $docId)->delete();
Db::name('document_signing_rounds')->whereIn('document_id', [$docId, $rejectDoc])->delete();
Db::name('approvals')->where('id', $approvalId)->delete();
Db::name('documents')->where('id', $docId)->delete();

echo "qms_wave1_d35_signing_loop_smoke passed\n";
