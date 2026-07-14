<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/vendor/autoload.php';
require dirname(__DIR__, 2) . '/app/common.php';

use app\service\QmsResponsibilityApprovalService;
use think\facade\Session;

$app = new think\App();
$app->initialize();

$readyPath = (string)($argv[1] ?? '');
$userPayload = json_decode((string)base64_decode((string)($argv[2] ?? ''), true), true);
$batchKey = (string)($argv[3] ?? '');
if ($readyPath === '' || !is_array($userPayload) || $batchKey === '') {
    fwrite(STDERR, "Missing activation worker arguments.\n");
    exit(2);
}

Session::set('user', $userPayload);
file_put_contents($readyPath, 'ready', LOCK_EX);

try {
    $result = QmsResponsibilityApprovalService::approveBatch($batchKey, 'approved', '并发锁验证');
    echo json_encode([
        'result' => 'approved',
        'version_status' => (string)($result['version_status'] ?? ''),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
} catch (DomainException $exception) {
    echo json_encode([
        'result' => 'blocked',
        'message' => $exception->getMessage(),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
}
