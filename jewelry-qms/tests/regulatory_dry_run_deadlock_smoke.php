<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../app/common.php';

$app = new think\App();
$app->initialize();

use app\service\regulatory\RegulatoryCandidateService;
use think\facade\Config;
use think\facade\Db;

function drylock_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$commandSource = (string)file_get_contents(__DIR__ . '/../app/command/MonitorRegulatoryChanges.php');
drylock_assert(
    str_contains($commandSource, 'ownsTransaction: false'),
    'Dry-run command must give transaction ownership exclusively to the outer preview transaction'
);

$companyId = (string)Config::get('qms.company_id');
$seedRunId = qms_uuid();
$workerRunIds = [qms_uuid(), qms_uuid()];
$sourceKey = 'drylock_' . substr(str_replace('-', '', $seedRunId), 0, 12);
$itemKeys = [
    'DRYLOCK-A-' . strtoupper(substr(str_replace('-', '', $seedRunId), 0, 8)),
    'DRYLOCK-B-' . strtoupper(substr(str_replace('-', '', $seedRunId), 0, 8)),
];
$candidateIds = [qms_uuid(), qms_uuid()];
$barrierDirectory = sys_get_temp_dir() . '/regulatory-drylock-' . $seedRunId;
$processes = [];
$failure = null;
$countsBefore = [
    'runs' => Db::name('qms_regulatory_monitor_runs')->count(),
    'candidates' => Db::name('qms_external_change_candidates')->count(),
    'notifications' => Db::name('notifications')->count(),
];

$items = array_map(static fn (string $itemKey): array => [
    'title' => '法规 dry-run 死锁条目 ' . $itemKey,
    'announcement_number' => $itemKey,
    'canonical_url' => 'https://www.samr.gov.cn/dry-run-deadlock/' . rawurlencode($itemKey) . '.html',
    'published_date' => '2026-07-14',
    'summary' => '真实双进程反向锁序测试',
    'evidence' => ['raw_text' => '真实双进程反向锁序测试'],
], $itemKeys);
$hashService = new RegulatoryCandidateService();

try {
    drylock_assert(mkdir($barrierDirectory, 0700, true), '无法创建 dry-run 死锁同步目录');
    Db::name('qms_regulatory_monitor_runs')->insert([
        'id' => $seedRunId,
        'company_id' => $companyId,
        'run_code' => 'REG-DRYLOCK-SEED-' . substr(str_replace('-', '', $seedRunId), 0, 8),
        'trigger_mode' => 'manual',
        'started_at' => '2026-07-14 16:00:00',
        'status' => 'completed',
        'created' => '2026-07-14 16:00:00',
        'modified' => '2026-07-14 16:00:00',
    ]);
    foreach ($items as $index => $item) {
        Db::name('qms_external_change_candidates')->insert([
            'id' => $candidateIds[$index],
            'company_id' => $companyId,
            'monitor_run_id' => $seedRunId,
            'source_key' => $sourceKey,
            'source_mode' => 'html_list',
            'source_item_key' => $itemKeys[$index],
            'source_url' => $item['canonical_url'],
            'normalized_url' => $item['canonical_url'],
            'title' => $item['title'],
            'announcement_number' => $itemKeys[$index],
            'published_date' => '2026-07-14',
            'first_seen_at' => '2026-07-14 16:00:00',
            'last_seen_at' => '2026-07-14 16:00:00',
            'content_hash' => $hashService->contentHash($item),
            'evidence_refs' => '[]',
            'evidence_json' => '{}',
            'relevance' => 'unknown',
            'preliminary_applicability' => 'needs_review',
            'review_status' => 'pending',
            'publish' => 1,
            'soft_delete' => 0,
            'created' => '2026-07-14 16:00:00',
            'modified' => '2026-07-14 16:00:00',
        ]);
    }

    $worker = __DIR__ . '/regulatory_dry_run_deadlock_worker.php';
    $orders = [[$candidateIds[0], $itemKeys[1]], [$candidateIds[1], $itemKeys[0]]];
    foreach ($orders as $index => [$firstCandidateId, $secondKey]) {
        $pipes = [];
        $process = proc_open(
            [PHP_BINARY, $worker, $workerRunIds[$index], $sourceKey, $firstCandidateId, $secondKey, $barrierDirectory, (string)($index + 1)],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            __DIR__
        );
        drylock_assert(is_resource($process), '无法启动 dry-run 死锁工作进程');
        $processes[] = ['process' => $process, 'pipes' => $pipes, 'worker' => $index + 1];
    }

    $results = [];
    foreach ($processes as $entry) {
        $stdout = stream_get_contents($entry['pipes'][1]);
        $stderr = stream_get_contents($entry['pipes'][2]);
        fclose($entry['pipes'][1]);
        fclose($entry['pipes'][2]);
        $exitCode = proc_close($entry['process']);
        drylock_assert($exitCode === 0, 'dry-run 死锁工作进程失败：' . trim((string)$stderr));
        try {
            $results[] = json_decode(trim((string)$stdout), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException(
                'dry-run 死锁工作进程输出无效：stdout=' . trim((string)$stdout)
                . ' stderr=' . trim((string)$stderr),
                0,
                $exception
            );
        }
    }
    $processes = [];

    $statuses = array_column($results, 'status');
    sort($statuses);
    drylock_assert(
        $statuses === ['completed', 'transaction_aborted'],
        '反向锁序必须产生一个真实事务中止：' . json_encode($results, JSON_UNESCAPED_UNICODE)
    );
    $aborted = array_values(array_filter($results, static fn (array $row): bool => $row['status'] === 'transaction_aborted'))[0];
    drylock_assert(in_array($aborted['database_code'], ['1213', '40001'], true), '事务中止必须源自真实 1213/40001');
    drylock_assert(count(glob($barrierDirectory . '/*.retry.*') ?: []) === 0, 'ambient dry-run 事务中止后不得重试');
    foreach ($workerRunIds as $workerRunId) {
        drylock_assert(
            Db::name('qms_regulatory_monitor_runs')->where('id', $workerRunId)->count() === 0,
            'dry-run 死锁后不得残留 run'
        );
    }
    $storedCandidates = Db::name('qms_external_change_candidates')
        ->where('source_key', $sourceKey)
        ->order('source_item_key', 'asc')
        ->select()
        ->toArray();
    drylock_assert(count($storedCandidates) === 2, 'dry-run 死锁后不得新增候选');
    foreach ($storedCandidates as $candidate) {
        drylock_assert($candidate['last_seen_at'] === '2026-07-14 16:00:00', 'dry-run 死锁后不得 autocommit 候选更新');
    }
} catch (Throwable $exception) {
    $failure = $exception;
} finally {
    foreach ($processes as $entry) {
        foreach ($entry['pipes'] as $pipe) {
            if (is_resource($pipe)) {
                fclose($pipe);
            }
        }
        if (is_resource($entry['process'])) {
            proc_terminate($entry['process']);
            proc_close($entry['process']);
        }
    }
    Db::name('qms_external_change_candidates')->where('source_key', $sourceKey)->delete();
    Db::name('qms_regulatory_monitor_runs')->whereIn('id', array_merge([$seedRunId], $workerRunIds))->delete();
    foreach (glob($barrierDirectory . '/*') ?: [] as $path) {
        @unlink($path);
    }
    if (is_dir($barrierDirectory)) {
        @rmdir($barrierDirectory);
    }
}

if ($failure instanceof Throwable) {
    fwrite(STDERR, $failure->getMessage() . PHP_EOL);
    exit(1);
}
drylock_assert(Db::name('qms_regulatory_monitor_runs')->count() === $countsBefore['runs'], 'dry-run 死锁测试必须清理 run');
drylock_assert(Db::name('qms_external_change_candidates')->count() === $countsBefore['candidates'], 'dry-run 死锁测试必须清理 candidate');
drylock_assert(Db::name('notifications')->count() === $countsBefore['notifications'], 'dry-run 死锁测试必须保持通知零变化');

echo "regulatory_dry_run_deadlock_smoke passed\n";
