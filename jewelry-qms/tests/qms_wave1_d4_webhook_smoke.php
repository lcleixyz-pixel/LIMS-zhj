<?php
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/app/service/DocuSealService.php';

use app\service\DocuSealService;

function assert_true(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

$nonceDir = sys_get_temp_dir() . '/qms_docuseal_nonce_' . getmypid();
@mkdir($nonceDir, 0775, true);
$secret = 'wave1-docuseal-test-secret';
$service = new DocuSealService([
    'base_url' => 'http://127.0.0.1:3100',
    'api_key' => 'test',
    'webhook_secret' => $secret,
], $nonceDir);

$body = json_encode(['event' => 'completed', 'document_id' => 'doc-1'], JSON_UNESCAPED_UNICODE);
$ts = (string)time();
$nonce = 'nonce-' . bin2hex(random_bytes(8));
$goodSig = hash_hmac('sha256', $ts . '.' . $nonce . '.' . $body, $secret);

$bad = $service->verifyWebhookSignature($body, 'deadbeef', $ts, $nonce . '-a');
assert_true(($bad['ok'] ?? true) === false && ($bad['error'] ?? '') === 'bad_signature', 'D-4 rejects bad signature');

$expiredTs = (string)(time() - DocuSealService::WEBHOOK_MAX_SKEW_SECONDS - 10);
$expiredSig = hash_hmac('sha256', $expiredTs . '.' . $nonce . '-b.' . $body, $secret);
$expired = $service->verifyWebhookSignature($body, $expiredSig, $expiredTs, $nonce . '-b');
assert_true(($expired['ok'] ?? true) === false && ($expired['error'] ?? '') === 'timestamp_expired', 'D-4 rejects expired timestamp');

$tampered = $service->verifyWebhookSignature($body . 'x', $goodSig, $ts, $nonce . '-c');
assert_true(($tampered['ok'] ?? true) === false && ($tampered['error'] ?? '') === 'bad_signature', 'D-4 rejects tampered body hash');

$nonceReplay = $nonce . '-replay';
$sigReplay = hash_hmac('sha256', $ts . '.' . $nonceReplay . '.' . $body, $secret);
$first = $service->verifyWebhookSignature($body, $sigReplay, $ts, $nonceReplay);
assert_true(($first['ok'] ?? false) === true, 'D-4 accepts first valid webhook');
$second = $service->verifyWebhookSignature($body, $sigReplay, $ts, $nonceReplay);
assert_true(($second['ok'] ?? true) === false && ($second['error'] ?? '') === 'replay', 'D-4 rejects replayed nonce');

$store = $service->storeSignedAsset([
    'document_id' => 'doc-smoke',
    'company_id' => '00000000-0000-0000-0000-000000000001',
    'content' => 'signed-bytes',
    'expected_sha256' => '0000000000000000000000000000000000000000000000000000000000000000',
]);
assert_true(($store['ok'] ?? true) === false && ($store['error'] ?? '') === 'hash_mismatch', 'D-4 rejects tampered content hash on store');

foreach (glob($nonceDir . '/*') ?: [] as $file) {
    @unlink($file);
}
@rmdir($nonceDir);

echo "qms_wave1_d4_webhook_smoke passed\n";
