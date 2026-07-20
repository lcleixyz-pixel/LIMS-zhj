<?php
declare(strict_types=1);

namespace app\service;

use think\facade\Config;
use think\facade\Db;

/**
 * DocuSeal 自托管签批集成（HTTP + HMAC webhook）。
 */
class DocuSealService
{
    public const MAX_REJECT_ROUNDS = 3;
    public const WEBHOOK_MAX_SKEW_SECONDS = 300;

    private string $baseUrl;
    private string $apiKey;
    private string $webhookSecret;
    private string $nonceDir;

    private bool $mockHttp;

    public function __construct(?array $config = null, ?string $nonceDir = null)
    {
        $cfg = $config ?? (array)Config::get('qms.docuseal', []);
        $this->baseUrl = rtrim((string)($cfg['base_url'] ?? 'http://127.0.0.1:3100'), '/');
        $this->apiKey = (string)($cfg['api_key'] ?? '');
        $this->webhookSecret = (string)($cfg['webhook_secret'] ?? '');
        $this->mockHttp = (bool)($cfg['mock'] ?? false)
            || filter_var(getenv('DOCUSEAL_MOCK') ?: '0', FILTER_VALIDATE_BOOLEAN)
            || str_contains(strtolower($this->baseUrl), 'mock');
        $this->nonceDir = $nonceDir ?? (runtime_path() . 'docuseal_nonce');
        if (!is_dir($this->nonceDir)) {
            @mkdir($this->nonceDir, 0775, true);
        }
    }

    public static function isSigningEnabled(): bool
    {
        return (bool)Config::get('qms.docuseal.signing_enabled', false);
    }

    /**
     * D-3：提审时尝试建立 DocuSeal submission；失败不抛错，仍落 signing round 留痕。
     *
     * @return array{ok: bool, submission_id?: string, content_sha256?: string, error?: string, round?: int}
     */
    public function startSigningForDocument(\app\model\Document $doc): array
    {
        $content = $this->resolveDocumentBytes($doc);
        $sha256 = hash('sha256', $content);
        $signers = $this->resolveSignerEmails($doc);
        $payload = [
            'name' => (string)$doc->title,
            'send_email' => true,
            'documents' => [[
                'name' => (string)($doc->file_name ?: ($doc->doc_number . '.pdf')),
                'file' => base64_encode($content),
            ]],
            'submitters' => array_map(static fn (string $email): array => [
                'email' => $email,
                'role' => 'Reviewer',
            ], $signers),
            'metadata' => [
                'document_id' => (string)$doc->id,
                'company_id' => (string)$doc->company_id,
                'content_sha256' => $sha256,
                'doc_number' => (string)$doc->doc_number,
                'version' => (string)($doc->version ?? ''),
            ],
        ];

        $created = $this->createSubmission($payload);
        $submissionId = (string)($created['submission_id'] ?? '');
        $note = ($created['ok'] ?? false)
            ? 'submission_created'
            : ('create_failed:' . (string)($created['error'] ?? 'unknown'));

        $round = $this->recordSigningRound(
            (string)$doc->id,
            'pending',
            $submissionId !== '' ? $submissionId : null,
            $note
        );

        return [
            'ok' => (bool)($created['ok'] ?? false),
            'submission_id' => $submissionId !== '' ? $submissionId : null,
            'content_sha256' => $sha256,
            'error' => $created['error'] ?? null,
            'round' => $round['round'] ?? null,
        ];
    }

    /**
     * @return list<string>
     */
    public function resolveSignerEmails(\app\model\Document $doc): array
    {
        $emails = [];
        foreach ([(string)$doc->reviewed_by, (string)$doc->approved_by] as $employeeId) {
            $employeeId = trim($employeeId);
            if ($employeeId === '') {
                continue;
            }
            $user = Db::name('users')
                ->where('employee_id', $employeeId)
                ->where('soft_delete', 0)
                ->find();
            $email = strtolower(trim((string)($user['email'] ?? '')));
            if ($email !== '' && !in_array($email, $emails, true)) {
                $emails[] = $email;
            }
        }

        return $emails;
    }

    public function resolveDocumentBytes(\app\model\Document $doc): string
    {
        $path = trim((string)($doc->file_path ?? ''));
        if ($path !== '' && is_file($path)) {
            $bytes = file_get_contents($path);

            return $bytes !== false ? $bytes : '';
        }

        return "QMS Document\n"
            . 'id=' . (string)$doc->id . "\n"
            . 'number=' . (string)$doc->doc_number . "\n"
            . 'title=' . (string)$doc->title . "\n"
            . 'version=' . (string)($doc->version ?? '') . "\n";
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{ok: bool, submission_id?: string, raw?: mixed, error?: string}
     */
    public function createSubmission(array $payload): array
    {
        if ($this->mockHttp) {
            $submissionId = 'mock-sub-' . substr(hash('sha256', json_encode($payload) ?: ''), 0, 16);

            return [
                'ok' => true,
                'submission_id' => $submissionId,
                'raw' => ['id' => $submissionId, 'mock' => true],
            ];
        }

        $url = $this->baseUrl . '/api/submissions';
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE);
        if ($body === false) {
            return ['ok' => false, 'error' => 'invalid_payload'];
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'X-Auth-Token: ' . $this->apiKey,
            ],
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_TIMEOUT => 30,
        ]);
        $response = curl_exec($ch);
        $errno = curl_errno($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($errno !== 0 || $response === false) {
            return ['ok' => false, 'error' => 'http_transport_error'];
        }

        $decoded = json_decode((string)$response, true);
        if ($status >= 400 || !is_array($decoded)) {
            return ['ok' => false, 'error' => 'http_status_' . $status, 'raw' => $decoded ?: $response];
        }

        $submissionId = (string)($decoded['id'] ?? $decoded['submission_id'] ?? '');

        return [
            'ok' => $submissionId !== '',
            'submission_id' => $submissionId,
            'raw' => $decoded,
            'error' => $submissionId === '' ? 'missing_submission_id' : null,
        ];
    }

    /**
     * 验签：HMAC-SHA256(timestamp.nonce.body, secret) 与签名比对；时间窗 + nonce 防重放。
     *
     * @return array{ok: bool, error?: string}
     */
    public function verifyWebhookSignature(
        string $rawBody,
        string $signature,
        string $timestamp,
        string $nonce,
        ?int $now = null
    ): array {
        $now = $now ?? time();
        if ($this->webhookSecret === '') {
            return ['ok' => false, 'error' => 'missing_webhook_secret'];
        }
        if ($signature === '' || $timestamp === '' || $nonce === '') {
            return ['ok' => false, 'error' => 'missing_headers'];
        }
        if (!ctype_digit($timestamp)) {
            return ['ok' => false, 'error' => 'invalid_timestamp'];
        }
        $ts = (int)$timestamp;
        if (abs($now - $ts) > self::WEBHOOK_MAX_SKEW_SECONDS) {
            return ['ok' => false, 'error' => 'timestamp_expired'];
        }

        $expected = hash_hmac('sha256', $timestamp . '.' . $nonce . '.' . $rawBody, $this->webhookSecret);
        if (!hash_equals($expected, $signature)) {
            return ['ok' => false, 'error' => 'bad_signature'];
        }

        $noncePath = $this->nonceDir . '/' . hash('sha256', $nonce) . '.nonce';
        if (is_file($noncePath)) {
            return ['ok' => false, 'error' => 'replay'];
        }
        @file_put_contents($noncePath, (string)$now, LOCK_EX);

        return ['ok' => true];
    }

    /**
     * 校验已签件内容哈希，并写入 qms_document_assets（source_kind=signed_document）。
     *
     * @param array{document_id: string, company_id: string, original_name?: string, content: string, expected_sha256: string, submission_id?: string, metadata?: array} $input
     * @return array{ok: bool, asset_id?: string, error?: string}
     */
    public function storeSignedAsset(array $input): array
    {
        $content = (string)($input['content'] ?? '');
        $expected = strtolower((string)($input['expected_sha256'] ?? ''));
        $actual = hash('sha256', $content);
        if ($expected === '' || !hash_equals($expected, $actual)) {
            return ['ok' => false, 'error' => 'hash_mismatch'];
        }

        $documentId = (string)($input['document_id'] ?? '');
        $companyId = (string)($input['company_id'] ?? '');
        if ($documentId === '' || $companyId === '') {
            return ['ok' => false, 'error' => 'missing_document'];
        }

        $assetId = qms_uuid();
        $name = (string)($input['original_name'] ?? ('signed-' . $documentId . '.pdf'));
        $meta = $input['metadata'] ?? [];
        if (!is_array($meta)) {
            $meta = [];
        }
        $meta['submission_id'] = (string)($input['submission_id'] ?? '');
        $meta['content_sha256'] = $actual;
        $meta['signed_at'] = date('c');

        $archiveDir = runtime_path() . 'docuseal_signed';
        if (!is_dir($archiveDir)) {
            @mkdir($archiveDir, 0775, true);
        }
        $archivedPath = $archiveDir . '/' . $assetId . '.bin';
        @file_put_contents($archivedPath, $content, LOCK_EX);

        Db::name('qms_document_assets')->insert([
            'id' => $assetId,
            'company_id' => $companyId,
            'source_kind' => 'signed_document',
            'document_id' => $documentId,
            'original_name' => $name,
            'original_path' => $archivedPath,
            'normalized_name' => $name,
            'archived_path' => $archivedPath,
            'file_type' => pathinfo($name, PATHINFO_EXTENSION) ?: 'pdf',
            'file_sha256' => $actual,
            'archive_status' => 'archived',
            'review_status' => 'published',
            'source_note' => json_encode($meta, JSON_UNESCAPED_UNICODE),
            'publish' => 1,
            'soft_delete' => 0,
            'created' => date('Y-m-d H:i:s'),
            'modified' => date('Y-m-d H:i:s'),
        ]);

        return ['ok' => true, 'asset_id' => $assetId];
    }

    /**
     * 记录签批轮次；驳回累计达到上限则拒绝继续。
     *
     * @return array{ok: bool, round?: int, reject_count?: int, error?: string}
     */
    public function recordSigningRound(string $documentId, string $decision, ?string $submissionId = null, ?string $note = null): array
    {
        $decision = strtolower($decision);
        if (!in_array($decision, ['pending', 'approved', 'rejected'], true)) {
            return ['ok' => false, 'error' => 'invalid_decision'];
        }

        $rejectCount = (int)Db::name('document_signing_rounds')
            ->where('document_id', $documentId)
            ->where('decision', 'rejected')
            ->count();

        if ($decision === 'rejected' && $rejectCount >= self::MAX_REJECT_ROUNDS) {
            return ['ok' => false, 'error' => 'reject_limit', 'reject_count' => $rejectCount];
        }

        $round = (int)Db::name('document_signing_rounds')->where('document_id', $documentId)->max('round_no') + 1;
        Db::name('document_signing_rounds')->insert([
            'id' => qms_uuid(),
            'document_id' => $documentId,
            'round_no' => $round,
            'decision' => $decision,
            'submission_id' => $submissionId,
            'note' => $note,
            'created' => date('Y-m-d H:i:s'),
        ]);

        if ($decision === 'rejected') {
            $rejectCount++;
        }

        return ['ok' => true, 'round' => $round, 'reject_count' => $rejectCount];
    }

    public function getWebhookSecret(): string
    {
        return $this->webhookSecret;
    }
}
