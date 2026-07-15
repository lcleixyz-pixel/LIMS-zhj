<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../app/common.php';

$app = new think\App();
$app->initialize();

use app\service\regulatory\RegulatoryCandidateService;
use think\facade\Config;
use think\facade\Db;

[$script, $runId, $sourceKey, $barrierDirectory, $workerId] = $argv + [null, null, null, null, null];
if (!is_string($runId) || !is_string($sourceKey) || !is_string($barrierDirectory) || !is_string($workerId)) {
    fwrite(STDERR, "concurrency worker arguments are incomplete\n");
    exit(2);
}

$inserter = static function (array $data) use ($barrierDirectory, $workerId): void {
    $readyPath = $barrierDirectory . '/' . $workerId . '.ready';
    if (file_put_contents($readyPath, 'ready') === false) {
        throw new RuntimeException('无法写入并发同步标记');
    }
    $deadline = microtime(true) + 3.0;
    while (count(glob($barrierDirectory . '/*.ready') ?: []) < 2) {
        if (microtime(true) >= $deadline) {
            throw new RuntimeException('并发同步等待超时');
        }
        usleep(5_000);
    }
    Db::name('qms_external_change_candidates')->insert($data);
};

$service = new RegulatoryCandidateService(
    static fn (): DateTimeImmutable => new DateTimeImmutable('2026-07-14 16:00:00'),
    $inserter,
    static function (int $attempt) use ($barrierDirectory, $workerId): void {
        file_put_contents($barrierDirectory . '/' . $workerId . '.retry.' . $attempt, 'retry');
        usleep($attempt * 5_000);
    }
);
$item = [
    'title' => '真实空链并发候选',
    'announcement_number' => 'CONCURRENT-EMPTY-CHAIN-2026',
    'canonical_url' => 'https://www.samr.gov.cn/concurrent/empty-chain.html',
    'published_date' => '2026-07-14',
    'summary' => '两个独立 PHP 进程同时写入同一候选。',
    'evidence' => ['raw_text' => '真实双连接并发证据'],
];

try {
    $result = $service->record(
        (string)Config::get('qms.company_id'),
        $runId,
        $sourceKey,
        'html_list',
        $item
    );
    echo json_encode([
        'status' => $result['status'],
        'candidate_id' => $result['candidate']['id'],
    ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . PHP_EOL;
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage() . PHP_EOL);
    exit(1);
}
