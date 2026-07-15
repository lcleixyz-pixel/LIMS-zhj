<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../app/common.php';

$app = new think\App();
$app->initialize();

use think\facade\Config;
use think\facade\Db;

function concurrency_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$companyId = (string)Config::get('qms.company_id');
$runId = qms_uuid();
$sourceKey = 'concurrent_empty_' . substr(str_replace('-', '', $runId), 0, 12);
$sourceItemKey = 'CONCURRENT-EMPTY-CHAIN-2026';
$barrierDirectory = sys_get_temp_dir() . '/regulatory-concurrency-' . $runId;
$processes = [];
$failure = null;

try {
    if (!mkdir($barrierDirectory, 0700, true) && !is_dir($barrierDirectory)) {
        throw new RuntimeException('无法创建并发测试同步目录');
    }
    Db::name('qms_regulatory_monitor_runs')->insert([
        'id' => $runId,
        'company_id' => $companyId,
        'run_code' => 'REG-CONCURRENT-' . substr($runId, 0, 8),
        'trigger_mode' => 'manual',
        'started_at' => '2026-07-14 16:00:00',
        'status' => 'running',
        'created' => '2026-07-14 16:00:00',
        'modified' => '2026-07-14 16:00:00',
    ]);

    $worker = __DIR__ . '/regulatory_candidate_concurrency_worker.php';
    for ($workerNumber = 1; $workerNumber <= 2; $workerNumber++) {
        $pipes = [];
        $process = proc_open(
            [PHP_BINARY, $worker, $runId, $sourceKey, $barrierDirectory, (string)$workerNumber],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            __DIR__
        );
        concurrency_assert(is_resource($process), '无法启动并发候选进程 ' . $workerNumber);
        $processes[] = ['process' => $process, 'pipes' => $pipes, 'worker' => $workerNumber];
    }

    $results = [];
    foreach ($processes as $entry) {
        $stdout = stream_get_contents($entry['pipes'][1]);
        $stderr = stream_get_contents($entry['pipes'][2]);
        fclose($entry['pipes'][1]);
        fclose($entry['pipes'][2]);
        $exitCode = proc_close($entry['process']);
        concurrency_assert(
            $exitCode === 0,
            '并发候选进程 ' . $entry['worker'] . ' 失败：' . trim((string)$stderr)
        );
        $results[] = json_decode(trim((string)$stdout), true, 512, JSON_THROW_ON_ERROR);
    }
    $processes = [];

    $rows = Db::name('qms_external_change_candidates')
        ->where('company_id', $companyId)
        ->where('source_key', $sourceKey)
        ->where('source_item_key', $sourceItemKey)
        ->select()
        ->toArray();
    concurrency_assert(count($rows) === 1, '真实并发同内容最终必须只有一个候选');
    concurrency_assert($rows[0]['supersedes_candidate_id'] === null, '真实并发空链最终必须是单根单链');
    concurrency_assert(count(array_unique(array_column($rows, 'content_hash'))) === 1, '真实并发不得产生重复指纹');
    $statuses = array_column($results, 'status');
    sort($statuses);
    concurrency_assert($statuses === ['existing', 'new'], '两个并发调用必须分别返回 new 与 existing');
    concurrency_assert(
        count(array_unique(array_column($results, 'candidate_id'))) === 1,
        '两个并发调用必须收敛到同一候选'
    );
    concurrency_assert(
        count(glob($barrierDirectory . '/*.retry.*') ?: []) >= 1,
        '真实双连接测试必须实际经过至少一次死锁重试'
    );
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
    Db::name('qms_external_change_candidates')->where('monitor_run_id', $runId)->delete();
    Db::name('qms_regulatory_monitor_runs')->where('id', $runId)->delete();
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

echo "regulatory_candidate_concurrency_smoke passed\n";
