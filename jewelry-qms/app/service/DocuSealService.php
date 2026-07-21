<?php
declare(strict_types=1);

namespace app\service;

use think\facade\Config;
use think\facade\Db;

/**
 * DocuSeal 自托管签批集成（HTTP + HMAC webhook）。
 * 开源路径：template_id + send_email=false → embed_src；原生 webhook 头为 timestamp.signature。
 */
class DocuSealService
{
    public const MAX_REJECT_ROUNDS = 3;
    public const WEBHOOK_MAX_SKEW_SECONDS = 300;

    private string $baseUrl;
    private string $publicBaseUrl;
    private string $apiKey;
    private string $webhookSecret;
    private int $templateId;
    private bool $sendEmail;
    private string $nonceDir;

    private bool $mockHttp;

    public function __construct(?array $config = null, ?string $nonceDir = null)
    {
        $cfg = $config ?? (array)Config::get('qms.docuseal', []);
        $this->baseUrl = rtrim((string)($cfg['base_url'] ?? 'http://127.0.0.1:3100'), '/');
        $this->publicBaseUrl = rtrim((string)($cfg['public_base_url'] ?? $this->baseUrl), '/');
        $this->apiKey = (string)($cfg['api_key'] ?? '');
        $this->webhookSecret = (string)($cfg['webhook_secret'] ?? '');
        $this->templateId = (int)($cfg['template_id'] ?? 0);
        $this->sendEmail = (bool)($cfg['send_email'] ?? false);
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
     * D-3：提审时尝试建立 DocuSeal submission（embed，默认不发邮件）；失败不抛错，仍落 signing round。
     *
     * @return array{ok: bool, submission_id?: string|null, content_sha256?: string, embeds?: list<array{email: string, role: string, embed_src: string, slug?: string}>, error?: string|null, round?: int|null}
     */
    public function startSigningForDocument(\app\model\Document $doc): array
    {
        $content = $this->resolveDocumentBytes($doc);
        $sha256 = hash('sha256', $content);
        $signers = $this->resolveSignerRoles($doc);
        if ($signers === []) {
            $round = $this->recordSigningRound((string)$doc->id, 'pending', null, 'create_failed:no_signer_emails');

            return [
                'ok' => false,
                'submission_id' => null,
                'content_sha256' => $sha256,
                'embeds' => [],
                'error' => 'no_signer_emails',
                'round' => $round['round'] ?? null,
            ];
        }

        if (!$this->mockHttp && $this->templateId <= 0) {
            $round = $this->recordSigningRound((string)$doc->id, 'pending', null, 'create_failed:missing_template_id');

            return [
                'ok' => false,
                'submission_id' => null,
                'content_sha256' => $sha256,
                'embeds' => [],
                'error' => 'missing_template_id',
                'round' => $round['round'] ?? null,
            ];
        }

        $values = [
            'doc_number' => (string)$doc->doc_number,
            'title' => (string)$doc->title,
            'version' => (string)($doc->version ?? ''),
            'change_reason' => (string)($doc->change_reason ?? ''),
            'content_sha256' => $sha256,
        ];
        $meta = [
            'document_id' => (string)$doc->id,
            'company_id' => (string)$doc->company_id,
            'content_sha256' => $sha256,
            'doc_number' => (string)$doc->doc_number,
            'version' => (string)($doc->version ?? ''),
        ];

        $submitters = [];
        foreach ($signers as $i => $signer) {
            $row = [
                'role' => $signer['role'],
                'email' => $signer['email'],
                'metadata' => $meta,
                'send_email' => $this->sendEmail,
            ];
            if ($i === 0) {
                $row['values'] = $values;
            }
            $submitters[] = $row;
        }

        $payload = [
            'template_id' => $this->templateId,
            'send_email' => $this->sendEmail,
            'order' => 'preserved',
            'submitters' => $submitters,
        ];

        $created = $this->createSubmission($payload);
        $submissionId = (string)($created['submission_id'] ?? '');
        $embeds = $this->normalizeEmbeds((array)($created['embeds'] ?? []));
        $notePayload = [
            'event' => ($created['ok'] ?? false) ? 'submission_created_embed' : 'create_failed',
            'error' => $created['error'] ?? null,
            'content_sha256' => $sha256,
            'embeds' => $embeds,
        ];
        $note = json_encode($notePayload, JSON_UNESCAPED_UNICODE);

        $round = $this->recordSigningRound(
            (string)$doc->id,
            'pending',
            $submissionId !== '' ? $submissionId : null,
            $note !== false ? $note : 'submission_created_embed'
        );

        return [
            'ok' => (bool)($created['ok'] ?? false),
            'submission_id' => $submissionId !== '' ? $submissionId : null,
            'content_sha256' => $sha256,
            'embeds' => $embeds,
            'error' => $created['error'] ?? null,
            'round' => $round['round'] ?? null,
        ];
    }

    /**
     * @return list<array{role: string, email: string}>
     */
    public function resolveSignerRoles(\app\model\Document $doc): array
    {
        $roles = [
            ['role' => 'Reviewer', 'employee_id' => trim((string)$doc->reviewed_by)],
            ['role' => 'Approver', 'employee_id' => trim((string)$doc->approved_by)],
        ];
        $out = [];
        foreach ($roles as $role) {
            if ($role['employee_id'] === '') {
                continue;
            }
            $user = Db::name('users')
                ->where('employee_id', $role['employee_id'])
                ->where('soft_delete', 0)
                ->find();
            $email = strtolower(trim((string)($user['email'] ?? '')));
            if ($email === '') {
                continue;
            }
            $out[] = ['role' => $role['role'], 'email' => $email];
        }

        return $out;
    }

    /**
     * @return list<string>
     */
    public function resolveSignerEmails(\app\model\Document $doc): array
    {
        $emails = [];
        foreach ($this->resolveSignerRoles($doc) as $signer) {
            if (!in_array($signer['email'], $emails, true)) {
                $emails[] = $signer['email'];
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
     * @return array{ok: bool, submission_id?: string, embeds?: list<array<string, string>>, raw?: mixed, error?: string|null}
     */
    public function createSubmission(array $payload): array
    {
        if ($this->mockHttp) {
            $submissionId = 'mock-sub-' . substr(hash('sha256', json_encode($payload) ?: ''), 0, 16);
            $embeds = [];
            foreach ((array)($payload['submitters'] ?? []) as $i => $submitter) {
                if (!is_array($submitter)) {
                    continue;
                }
                $email = strtolower(trim((string)($submitter['email'] ?? '')));
                $role = (string)($submitter['role'] ?? 'Reviewer');
                $slug = 'mock-' . ($i + 1);
                $embeds[] = [
                    'email' => $email,
                    'role' => $role,
                    'slug' => $slug,
                    'embed_src' => $this->publicBaseUrl . '/s/' . $slug,
                ];
            }

            return [
                'ok' => true,
                'submission_id' => $submissionId,
                'embeds' => $embeds,
                'raw' => ['id' => $submissionId, 'mock' => true, 'submitters' => $embeds],
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

        return $this->parseSubmissionCreateResponse($decoded);
    }

    /**
     * @param array<mixed> $decoded
     * @return array{ok: bool, submission_id?: string, embeds?: list<array<string, string>>, raw?: mixed, error?: string|null}
     */
    private function parseSubmissionCreateResponse(array $decoded): array
    {
        $rows = array_is_list($decoded) ? $decoded : [(array)$decoded];
        $embeds = [];
        $submissionId = '';
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            if ($submissionId === '') {
                $submissionId = (string)($row['submission_id'] ?? $row['id'] ?? '');
            }
            $embedSrc = trim((string)($row['embed_src'] ?? ''));
            $slug = trim((string)($row['slug'] ?? ''));
            if ($embedSrc === '' && $slug !== '') {
                $embedSrc = $this->publicBaseUrl . '/s/' . $slug;
            }
            if ($embedSrc === '') {
                continue;
            }
            $embeds[] = [
                'email' => strtolower(trim((string)($row['email'] ?? ''))),
                'role' => (string)($row['role'] ?? ''),
                'slug' => $slug,
                'embed_src' => $this->rewritePublicEmbedUrl($embedSrc),
            ];
        }

        return [
            'ok' => $submissionId !== '' && $embeds !== [],
            'submission_id' => $submissionId,
            'embeds' => $embeds,
            'raw' => $decoded,
            'error' => ($submissionId === '' || $embeds === []) ? 'missing_submission_or_embeds' : null,
        ];
    }

    private function rewritePublicEmbedUrl(string $url): string
    {
        $parts = parse_url($url);
        if (!is_array($parts) || empty($parts['path'])) {
            return $url;
        }
        $public = parse_url($this->publicBaseUrl);
        if (!is_array($public) || empty($public['host'])) {
            return $url;
        }
        $scheme = $public['scheme'] ?? 'http';
        $host = $public['host'];
        $port = isset($public['port']) ? ':' . $public['port'] : '';
        $path = $parts['path'] . (isset($parts['query']) ? '?' . $parts['query'] : '');

        return $scheme . '://' . $host . $port . $path;
    }

    /**
     * @param list<array<string, mixed>> $embeds
     * @return list<array{email: string, role: string, embed_src: string, slug: string}>
     */
    private function normalizeEmbeds(array $embeds): array
    {
        $out = [];
        foreach ($embeds as $row) {
            if (!is_array($row)) {
                continue;
            }
            $src = trim((string)($row['embed_src'] ?? ''));
            if ($src === '') {
                continue;
            }
            $out[] = [
                'email' => strtolower(trim((string)($row['email'] ?? ''))),
                'role' => (string)($row['role'] ?? ''),
                'slug' => (string)($row['slug'] ?? ''),
                'embed_src' => $this->rewritePublicEmbedUrl($src),
            ];
        }

        return $out;
    }

    /**
     * 读取文档最近一轮 pending 的 embed 链接（从 note JSON）。
     *
     * @return list<array{email: string, role: string, embed_src: string, slug?: string}>
     */
    public function latestEmbedsForDocument(string $documentId): array
    {
        $row = Db::name('document_signing_rounds')
            ->where('document_id', $documentId)
            ->where('decision', 'pending')
            ->order('round_no', 'desc')
            ->find();
        if (!$row) {
            return [];
        }
        $note = trim((string)($row['note'] ?? ''));
        if ($note === '' || $note[0] !== '{') {
            return [];
        }
        $decoded = json_decode($note, true);
        if (!is_array($decoded) || !isset($decoded['embeds']) || !is_array($decoded['embeds'])) {
            return [];
        }

        return $this->normalizeEmbeds($decoded['embeds']);
    }

    /**
     * 验签：兼容 (A) 旧自定义 timestamp+nonce 头；(B) DocuSeal 原生 X-Docuseal-Signature=timestamp.sig。
     *
     * @return array{ok: bool, mode?: string, error?: string}
     */
    public function verifyWebhookSignature(
        string $rawBody,
        string $signature,
        string $timestamp = '',
        string $nonce = '',
        ?int $now = null
    ): array {
        $now = $now ?? time();
        if ($this->webhookSecret === '') {
            return ['ok' => false, 'error' => 'missing_webhook_secret'];
        }

        // 原生：单个头 timestamp.hex
        if ($timestamp === '' && $nonce === '' && str_contains($signature, '.')) {
            return $this->verifyNativeWebhookSignature($rawBody, $signature, $now);
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
            // 兼容误把原生头拆开传入
            $native = $this->verifyNativeWebhookSignature($rawBody, $timestamp . '.' . $signature, $now);
            if ($native['ok'] ?? false) {
                return $native;
            }

            return ['ok' => false, 'error' => 'bad_signature'];
        }

        $noncePath = $this->nonceDir . '/' . hash('sha256', $nonce) . '.nonce';
        if (is_file($noncePath)) {
            return ['ok' => false, 'error' => 'replay'];
        }
        @file_put_contents($noncePath, (string)$now, LOCK_EX);

        return ['ok' => true, 'mode' => 'legacy'];
    }

    /**
     * DocuSeal 原生：HMAC-SHA256(secret, "timestamp.body")，头格式 timestamp.hexdigest。
     *
     * @return array{ok: bool, mode?: string, error?: string}
     */
    public function verifyNativeWebhookSignature(string $rawBody, string $header, ?int $now = null): array
    {
        $now = $now ?? time();
        if ($this->webhookSecret === '') {
            return ['ok' => false, 'error' => 'missing_webhook_secret'];
        }
        $parts = explode('.', $header, 2);
        if (count($parts) !== 2 || !ctype_digit($parts[0]) || $parts[1] === '') {
            return ['ok' => false, 'error' => 'invalid_native_header'];
        }
        $ts = (int)$parts[0];
        if (abs($now - $ts) > self::WEBHOOK_MAX_SKEW_SECONDS) {
            return ['ok' => false, 'error' => 'timestamp_expired'];
        }
        $expected = hash_hmac('sha256', $parts[0] . '.' . $rawBody, $this->webhookSecret);
        if (!hash_equals($expected, $parts[1])) {
            return ['ok' => false, 'error' => 'bad_signature'];
        }

        $nonceKey = hash('sha256', $header . '|' . hash('sha256', $rawBody));
        $noncePath = $this->nonceDir . '/' . $nonceKey . '.nonce';
        if (is_file($noncePath)) {
            return ['ok' => false, 'error' => 'replay'];
        }
        @file_put_contents($noncePath, (string)$now, LOCK_EX);

        return ['ok' => true, 'mode' => 'native'];
    }

    /**
     * 将 DocuSeal 原生 event_type/data 归一为内部处理结构。
     *
     * @param array<string, mixed> $payload
     * @return array{event: string, document_id: string, company_id: string, submission_id: string, emails: list<string>, content: string, content_sha256: string, filename: string, raw: array<string, mixed>}
     */
    public function normalizeWebhookPayload(array $payload): array
    {
        $eventType = strtolower((string)($payload['event_type'] ?? $payload['event'] ?? $payload['status'] ?? ''));
        $data = isset($payload['data']) && is_array($payload['data']) ? $payload['data'] : $payload;

        $event = match (true) {
            in_array($eventType, ['form.declined', 'submission.declined', 'rejected', 'declined'], true) => 'rejected',
            in_array($eventType, ['form.completed', 'submission.completed', 'completed', 'signed', 'approved'], true) => 'completed',
            default => $eventType,
        };

        $documentId = (string)($payload['document_id'] ?? '');
        $companyId = (string)($payload['company_id'] ?? '');
        $submissionId = (string)($payload['submission_id'] ?? $data['id'] ?? $payload['id'] ?? '');

        $emails = [];
        $metaSources = [];
        if (isset($data['email'])) {
            $emails[] = strtolower(trim((string)$data['email']));
        }
        if (isset($data['metadata']) && is_array($data['metadata'])) {
            $metaSources[] = $data['metadata'];
        }
        foreach (['submitters', 'signers'] as $key) {
            if (!isset($data[$key]) || !is_array($data[$key])) {
                continue;
            }
            foreach ($data[$key] as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $email = strtolower(trim((string)($row['email'] ?? '')));
                if ($email !== '' && !in_array($email, $emails, true)) {
                    $emails[] = $email;
                }
                if (isset($row['metadata']) && is_array($row['metadata'])) {
                    $metaSources[] = $row['metadata'];
                }
            }
        }
        foreach ($metaSources as $meta) {
            if ($documentId === '' && !empty($meta['document_id'])) {
                $documentId = (string)$meta['document_id'];
            }
            if ($companyId === '' && !empty($meta['company_id'])) {
                $companyId = (string)$meta['company_id'];
            }
        }
        if ($documentId === '' && !empty($data['metadata']['document_id'])) {
            $documentId = (string)$data['metadata']['document_id'];
        }

        $content = '';
        $filename = 'signed.pdf';
        $contentB64 = (string)($payload['signed_content_base64'] ?? '');
        if ($contentB64 !== '') {
            $decoded = base64_decode($contentB64, true);
            $content = $decoded !== false ? $decoded : '';
        } elseif (isset($payload['signed_content'])) {
            $content = (string)$payload['signed_content'];
        } else {
            $url = (string)($data['combined_document_url'] ?? '');
            if ($url === '' && isset($data['documents'][0]['url'])) {
                $url = (string)$data['documents'][0]['url'];
                $filename = (string)($data['documents'][0]['name'] ?? $filename);
            }
            if ($url !== '') {
                $fetched = $this->httpGetBinary($url);
                if ($fetched !== null) {
                    $content = $fetched;
                }
            }
        }

        $sha = strtolower((string)($payload['content_sha256'] ?? $data['metadata']['content_sha256'] ?? ''));
        if ($sha === '' && $content !== '') {
            $sha = hash('sha256', $content);
        }

        return [
            'event' => $event,
            'document_id' => $documentId,
            'company_id' => $companyId,
            'submission_id' => $submissionId,
            'emails' => $emails,
            'content' => $content,
            'content_sha256' => $sha,
            'filename' => $filename,
            'raw' => $payload,
        ];
    }

    public function httpGetBinary(string $url): ?string
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 60,
        ]);
        $response = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($response === false || $status >= 400) {
            return null;
        }

        return (string)$response;
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

        $hasMetadataCol = $this->assetsHasMetadataJsonColumn();
        $row = [
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
        ];
        if ($hasMetadataCol) {
            $row['metadata_json'] = json_encode($meta, JSON_UNESCAPED_UNICODE);
        }
        Db::name('qms_document_assets')->insert($row);

        return ['ok' => true, 'asset_id' => $assetId];
    }

    private function assetsHasMetadataJsonColumn(): bool
    {
        static $cached = null;
        if ($cached !== null) {
            return $cached;
        }
        try {
            $cols = Db::query("SHOW COLUMNS FROM `qms_document_assets` LIKE 'metadata_json'");
            $cached = is_array($cols) && $cols !== [];
        } catch (\Throwable $e) {
            $cached = false;
        }

        return $cached;
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
