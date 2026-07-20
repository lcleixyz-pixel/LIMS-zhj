<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/vendor/autoload.php';
require dirname(__DIR__, 2) . '/app/common.php';

use app\service\QmsResponsibilityCatalogService;

$app = new think\App();
$app->initialize();

$barrierPath = (string)($argv[1] ?? '');
$readyPath = (string)($argv[2] ?? '');
if ($barrierPath === '' || $readyPath === '') {
    fwrite(STDERR, "Missing concurrency barrier paths.\n");
    exit(2);
}

file_put_contents($readyPath, 'ready', LOCK_EX);
$deadline = microtime(true) + 15;
while (!is_file($barrierPath)) {
    if (microtime(true) >= $deadline) {
        fwrite(STDERR, "Timed out waiting for concurrency barrier.\n");
        exit(3);
    }
    usleep(10_000);
}

try {
    $version = QmsResponsibilityCatalogService::createInitialDraft();
    echo json_encode([
        'id' => (string)$version['id'],
        'status' => (string)$version['status'],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
} catch (Throwable $exception) {
    fwrite(STDERR, get_class($exception) . ': ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}
