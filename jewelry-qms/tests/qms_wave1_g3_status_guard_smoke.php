<?php
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/app/service/DocumentStatusGuardService.php';
require_once $root . '/app/service/LoginThrottleService.php';

use app\service\DocumentStatusGuardService;
use app\service\LoginThrottleService;

function assert_true(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

$guard = new DocumentStatusGuardService();

$blocked = $guard->guardWrite(['status' => 'approved'], 'Document', 'edit');
assert_true(($blocked['result'] ?? '') === 'blocked' && ($blocked['allowed'] ?? true) === false, 'G-3 blocks Document/edit → approved');

$blockedPub = $guard->guardWrite(['status' => 'published'], 'AiAssistant', 'confirm');
assert_true(($blockedPub['result'] ?? '') === 'blocked', 'G-3 blocks AI path → published');

$blockedEff = $guard->guardWrite(['status' => 'effective'], 'Document', 'edit');
assert_true(($blockedEff['result'] ?? '') === 'blocked', 'G-3 blocks effective alias');

$okDraft = $guard->guardWrite(['status' => 'draft'], 'Document', 'edit');
assert_true(($okDraft['allowed'] ?? false) === true, 'G-3 allows draft');

$okApproval = $guard->guardWrite(['status' => 'approved'], 'Approval', 'approve');
assert_true(($okApproval['allowed'] ?? false) === true, 'G-3 allows Approval/approve');

$stripped = $guard->stripProtectedStatus(['status' => 'approved', 'title' => 'x']);
assert_true(!isset($stripped['status']) && ($stripped['title'] ?? '') === 'x', 'G-3 strip removes protected status');

$throttleDir = sys_get_temp_dir() . '/qms_login_throttle_' . getmypid();
@mkdir($throttleDir, 0775, true);
$throttle = new LoginThrottleService($throttleDir);
$ip = '203.0.113.9';
assert_true(!$throttle->isLocked($ip), 'throttle starts unlocked');
for ($i = 0; $i < LoginThrottleService::MAX_ATTEMPTS; $i++) {
    $throttle->recordFailure($ip);
}
assert_true($throttle->isLocked($ip), 'throttle locks after max failures');
$throttle->clear($ip);
assert_true(!$throttle->isLocked($ip), 'throttle clear unlocks');
foreach (glob($throttleDir . '/*') ?: [] as $file) {
    @unlink($file);
}
@rmdir($throttleDir);

echo "qms_wave1_g3_status_guard_smoke passed\n";
