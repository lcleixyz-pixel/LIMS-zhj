<?php
declare(strict_types=1);

namespace app\controller;

use app\BaseController;
use app\model\Approval as ApprovalModel;
use app\model\Document as DocumentModel;
use app\model\User;
use app\service\ApprovalService;
use app\service\DocuSealService;
use app\service\TrialModeService;
use DomainException;
use think\facade\Db;
use think\Response;

/**
 * DocuSeal webhook：无 Auth，但强制验签 + 时间窗 + nonce 防重放。
 * D-5：completed 后经 ApprovalService::processApproval 推进，不直写 status。
 */
class DocuSealWebhook extends BaseController
{
    protected array $middleware = [];

    public function handle(): Response
    {
        $raw = (string)$this->request->getContent();
        $signature = (string)$this->request->header('X-Docuseal-Signature', '');
        $timestamp = (string)$this->request->header('X-Docuseal-Timestamp', '');
        $nonce = (string)$this->request->header('X-Docuseal-Nonce', '');

        $service = new DocuSealService();
        $verify = $service->verifyWebhookSignature($raw, $signature, $timestamp, $nonce);
        if (!$verify['ok']) {
            return json(['ok' => false, 'error' => $verify['error'] ?? 'unauthorized'], 401);
        }

        $payload = json_decode($raw, true);
        if (!is_array($payload)) {
            return json(['ok' => false, 'error' => 'invalid_json'], 400);
        }

        $event = strtolower((string)($payload['event'] ?? $payload['status'] ?? ''));
        $documentId = (string)($payload['document_id'] ?? '');
        $companyId = (string)($payload['company_id'] ?? '');
        $submissionId = (string)($payload['submission_id'] ?? $payload['id'] ?? '');

        if ($documentId === '') {
            return json(['ok' => false, 'error' => 'missing_document_id'], 400);
        }

        if (in_array($event, ['rejected', 'declined'], true)) {
            $round = $service->recordSigningRound($documentId, 'rejected', $submissionId, (string)($payload['note'] ?? ''));
            if (!$round['ok']) {
                return json(['ok' => false, 'error' => $round['error'] ?? 'reject_failed'], 409);
            }

            return json(['ok' => true, 'decision' => 'rejected', 'round' => $round['round'] ?? null]);
        }

        if (in_array($event, ['completed', 'signed', 'approved'], true)) {
            $contentB64 = (string)($payload['signed_content_base64'] ?? '');
            $content = $contentB64 !== '' ? (string)base64_decode($contentB64, true) : (string)($payload['signed_content'] ?? '');
            $expectedHash = strtolower((string)($payload['content_sha256'] ?? ''));

            $stored = $service->storeSignedAsset([
                'document_id' => $documentId,
                'company_id' => $companyId !== '' ? $companyId : (string)Db::name('documents')->where('id', $documentId)->value('company_id'),
                'original_name' => (string)($payload['filename'] ?? 'signed.pdf'),
                'content' => $content,
                'expected_sha256' => $expectedHash,
                'submission_id' => $submissionId,
                'metadata' => ['event' => $event],
            ]);
            if (!$stored['ok']) {
                return json(['ok' => false, 'error' => $stored['error'] ?? 'store_failed'], 400);
            }

            $service->recordSigningRound($documentId, 'approved', $submissionId);

            $approvalResult = $this->advanceApprovalsFromWebhook($documentId, $payload);

            return json([
                'ok' => true,
                'decision' => 'approved',
                'asset_id' => $stored['asset_id'] ?? null,
                'approval' => $approvalResult,
            ]);
        }

        return json(['ok' => true, 'ignored' => true, 'event' => $event]);
    }

    /**
     * 按签署人邮箱反查 QMS 用户，复用 processApproval → finalizeDocumentIfFullyApproved。
     *
     * @param array<string, mixed> $payload
     * @return array{processed: int, finalized: bool, errors: list<string>}
     */
    private function advanceApprovalsFromWebhook(string $documentId, array $payload): array
    {
        $emails = $this->collectSignerEmails($payload);
        $processed = 0;
        $errors = [];
        $finalized = false;

        $doc = DocumentModel::find($documentId);
        if (!$doc) {
            return ['processed' => 0, 'finalized' => false, 'errors' => ['document_not_found']];
        }

        try {
            TrialModeService::assertDocumentApprovalAllowed($doc);
        } catch (DomainException $e) {
            return ['processed' => 0, 'finalized' => false, 'errors' => [$e->getMessage()]];
        }

        foreach ($emails as $email) {
            $user = User::where('email', $email)->where('soft_delete', 0)->find();
            if (!$user) {
                $errors[] = 'user_not_found:' . $email;
                continue;
            }
            $approval = ApprovalModel::where('model_name', 'Document')
                ->where('record', $documentId)
                ->where('user_id', $user->id)
                ->where('status', 'pending')
                ->where('soft_delete', 0)
                ->order('created', 'asc')
                ->find();
            if (!$approval) {
                $errors[] = 'pending_approval_missing:' . $email;
                continue;
            }
            if (!ApprovalService::processApproval((string)$approval->id, 'approved', 'DocuSeal webhook completed', (string)$user->id)) {
                $errors[] = 'process_approval_failed:' . $email;
                continue;
            }
            $processed++;
        }

        if ($processed > 0) {
            $doc = DocumentModel::find($documentId);
            if ($doc) {
                Db::transaction(function () use ($doc, &$finalized) {
                    $finalized = ApprovalService::finalizeDocumentIfFullyApproved($doc);
                });
            }
        }

        return ['processed' => $processed, 'finalized' => $finalized, 'errors' => $errors];
    }

    /**
     * @param array<string, mixed> $payload
     * @return list<string>
     */
    private function collectSignerEmails(array $payload): array
    {
        $emails = [];
        $single = strtolower(trim((string)($payload['signer_email'] ?? $payload['email'] ?? '')));
        if ($single !== '') {
            $emails[] = $single;
        }
        foreach (['signers', 'submitters'] as $key) {
            if (!isset($payload[$key]) || !is_array($payload[$key])) {
                continue;
            }
            foreach ($payload[$key] as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $email = strtolower(trim((string)($row['email'] ?? '')));
                if ($email !== '' && !in_array($email, $emails, true)) {
                    $emails[] = $email;
                }
            }
        }

        return $emails;
    }
}
