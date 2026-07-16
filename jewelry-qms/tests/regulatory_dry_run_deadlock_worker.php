<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../app/common.php';

$app = new think\App();
$app->initialize();

use app\service\regulatory\RegulatoryCandidateService;
use app\service\regulatory\RegulatoryTransactionAbortedException;
use think\facade\Config;
use think\facade\Db;

[$script, $runId, $sourceKey, $firstCandidateId, $secondKey, $barrierDirectory, $workerId] = $argv
    + [null, null, null, null, null, null, null];
foreach ([$runId, $sourceKey, $firstCandidateId, $secondKey, $barrierDirectory, $workerId] as $argument) {
    if (!is_string($argument) || $argument === '') {
        fwrite(STDERR, "dry-run deadlock worker arguments are incomplete\n");
        exit(2);
    }
}

$companyId = (string)Config::get('qms.company_id');
$retryBackoff = static function (int $attempt) use ($barrierDirectory, $workerId): void {
    file_put_contents($barrierDirectory . '/' . $workerId . '.retry.' . $attempt, 'retry');
};
$candidateService = new RegulatoryCandidateService(
    clock: static fn (): DateTimeImmutable => new DateTimeImmutable('2026-07-15 16:00:00'),
    retryBackoff: $retryBackoff,
    ownsTransaction: false
);
$item = static fn (string $itemKey): array => [
    'title' => '法规 dry-run 死锁条目 ' . $itemKey,
    'announcement_number' => $itemKey,
    'canonical_url' => 'https://www.samr.gov.cn/dry-run-deadlock/' . rawurlencode($itemKey) . '.html',
    'published_date' => '2026-07-14',
    'summary' => '真实双进程反向锁序测试',
    'evidence' => ['raw_text' => '真实双进程反向锁序测试'],
];

$status = 'completed';
$databaseCode = '';
Db::startTrans();
try {
    Db::name('qms_regulatory_monitor_runs')->insert([
        'id' => $runId,
        'company_id' => $companyId,
        'run_code' => 'REG-DRYLOCK-' . substr(str_replace('-', '', $runId), 0, 12),
        'trigger_mode' => 'manual',
        'started_at' => '2026-07-15 16:00:00',
        'status' => 'running',
        'created' => '2026-07-15 16:00:00',
        'modified' => '2026-07-15 16:00:00',
    ]);

    $locked = Db::name('qms_external_change_candidates')
        ->where('id', $firstCandidateId)
        ->lock(true)
        ->find();
    if (!is_array($locked)) {
        throw new RuntimeException('无法按主键锁定第一条候选');
    }
    file_put_contents($barrierDirectory . '/' . $workerId . '.ready', 'ready');
    $deadline = microtime(true) + 5.0;
    while (count(glob($barrierDirectory . '/*.ready') ?: []) < 2) {
        if (microtime(true) >= $deadline) {
            throw new RuntimeException('反向锁序同步超时');
        }
        usleep(5_000);
    }
    $candidateService->record($companyId, $runId, $sourceKey, 'html_list', $item($secondKey));
} catch (RegulatoryTransactionAbortedException $exception) {
    $status = 'transaction_aborted';
    for ($current = $exception; $current instanceof Throwable; $current = $current->getPrevious()) {
        if (in_array((string)$current->getCode(), ['1213', '40001'], true)
            || str_contains($current->getMessage(), '1213')
            || str_contains($current->getMessage(), '40001')
        ) {
            $databaseCode = str_contains($current->getMessage(), '40001') ? '40001' : '1213';
            break;
        }
    }
} finally {
    Db::rollback();
}

echo json_encode([
    'status' => $status,
    'database_code' => $databaseCode,
], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . PHP_EOL;
