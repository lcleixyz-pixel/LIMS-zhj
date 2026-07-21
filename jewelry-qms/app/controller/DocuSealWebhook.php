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
 * 兼容原生 X-Docuseal-Signature=timestamp.sig 与旧自定义三头格式。
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

        $normalized = $service->normalizeWebhookPayload($payload);
        $event = $normalized['event'];
        $documentId = $normalized['document_id'];
        $submissionId = $normalized['submission_id'];

        if ($documentId === '') {
            return json(['ok' => false, 'error' => 'missing_document_id'], 400);
        }

        if ($event === 'rejected') {
            $round = $service->recordSigningRound($documentId, 'rejected', $submissionId !== '' ? $submissionId : null, (string)($payload['note'] ?? $event));
            if (!$round['ok']) {
                return json(['ok' => false, 'error' => $round['error'] ?? 'reject_failed'], 409);
            }

            return json(['ok' => true, 'decision' => 'rejected', 'round' => $round['round'] ?? null]);
        }

        if ($event === 'completed') {
            $companyId = $normalized['company_id'] !== ''
                ? $normalized['company_id']
                : (string)Db::name('documents')->where('id', $documentId)->value('company_id');

            $assetId = null;
            if ($normalized['content'] !== '' && $normalized['content_sha256'] !== '') {
                $stored = $service->storeSignedAsset([
                    'document_id' => $documentId,
                    'company_id' => $companyId,
                    'original_name' => $normalized['filename'] !== '' ? $normalized['filename'] : 'signed.pdf',
                    'content' => $normalized['content'],
                    'expected_sha256' => $normalized['content_sha256'],
                    'submission_id' => $submissionId,
                    'metadata' => ['event' => $event, 'mode' => $verify['mode'] ?? ''],
                ]);
                if (!$stored['ok']) {
                    return json(['ok' => false, 'error' => $stored['error'] ?? 'store_failed'], 400);
                }
                $assetId = $stored['asset_id'] ?? null;
                $service->recordSigningRound($documentId, 'approved', $submissionId !== '' ? $submissionId : null, 'webhook_completed');
            }

            $approvalResult = $this->advanceApprovalsFromWebhook($documentId, $normalized['emails'], $payload);

            return json([
                'ok' => true,
                'decision' => 'approved',
                'asset_id' => $assetId,
                'approval' => $approvalResult,
            ]);
        }

        return json(['ok' => true, 'ignored' => true, 'event' => $event]);
    }

    /**
     * @param list<string> $emails
     * @param array<string, mixed> $payload
     * @return array{processed: int, finalized: bool, errors: list<string>}
     */
    private function advanceApprovalsFromWebhook(string $documentId, array $emails, array $payload): array
    {
        if ($emails === []) {
            $emails = $this->collectSignerEmails($payload);
        }
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
        $data = isset($payload['data']) && is_array($payload['data']) ? $payload['data'] : $payload;
        $single = strtolower(trim((string)($payload['signer_email'] ?? $payload['email'] ?? $data['email'] ?? '')));
        if ($single !== '') {
            $emails[] = $single;
        }
        foreach (['signers', 'submitters'] as $key) {
            $rows = $payload[$key] ?? $data[$key] ?? null;
            if (!is_array($rows)) {
                continue;
            }
            foreach ($rows as $row) {
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
