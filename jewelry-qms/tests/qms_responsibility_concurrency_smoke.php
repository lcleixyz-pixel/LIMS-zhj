<?php
declare(strict_types=1);

require __DIR__ . '/support/qms_responsibility_fixture.php';

use think\facade\Db;

function concurrency_cleanup_chain(): void
{
    $versionIds = Db::name('qms_responsibility_chain_versions')
        ->where('company_id', catalog_company_id())
        ->where('chain_code', 'core_governance')
        ->column('id');
    if ($versionIds === []) {
        return;
    }

    $activityIds = Db::name('qms_responsibility_activities')
        ->whereIn('chain_version_id', $versionIds)
        ->column('id');
    $responsibilityIds = $activityIds === []
        ? []
        : Db::name('qms_activity_responsibilities')->whereIn('activity_id', $activityIds)->column('id');

    if ($responsibilityIds !== []) {
        Db::name('qms_responsibility_assignments')->whereIn('responsibility_id', $responsibilityIds)->delete();
        Db::name('qms_activity_responsibilities')->whereIn('id', $responsibilityIds)->delete();
    }
    Db::name('qms_responsibility_approvals')->whereIn('chain_version_id', $versionIds)->delete();
    if ($activityIds !== []) {
        Db::name('qms_responsibility_activities')->whereIn('id', $activityIds)->delete();
    }
    Db::name('qms_responsibility_chain_versions')->whereIn('id', $versionIds)->delete();
}

function concurrency_wait_until_ready(array $readyPaths): void
{
    $deadline = microtime(true) + 10;
    do {
        $ready = array_filter($readyPaths, static fn (string $path): bool => is_file($path));
        if (count($ready) === count($readyPaths)) {
            return;
        }
        usleep(10_000);
    } while (microtime(true) < $deadline);

    throw new RuntimeException('Concurrent workers did not become ready.');
}

function concurrency_wait_until_finished(array &$processes): void
{
    $deadline = microtime(true) + 20;
    do {
        $allFinished = true;
        foreach ($processes as &$processInfo) {
            if (!is_resource($processInfo['process'])) {
                continue;
            }
            $status = proc_get_status($processInfo['process']);
            if (($status['running'] ?? false) === true) {
                $allFinished = false;
                continue;
            }
            $processInfo['exit_code'] = (int)($status['exitcode'] ?? -1);
        }
        unset($processInfo);
        if ($allFinished) {
            return;
        }
        usleep(10_000);
    } while (microtime(true) < $deadline);

    foreach ($processes as $processInfo) {
        if (is_resource($processInfo['process'])) {
            $status = proc_get_status($processInfo['process']);
            if (($status['running'] ?? false) === true) {
                proc_terminate($processInfo['process']);
            }
        }
    }
    throw new RuntimeException('Concurrent workers exceeded the 20-second timeout.');
}

concurrency_cleanup_chain();

$token = bin2hex(random_bytes(8));
$barrierPath = sys_get_temp_dir() . '/qms-responsibility-barrier-' . $token;
$readyPaths = [
    sys_get_temp_dir() . '/qms-responsibility-ready-a-' . $token,
    sys_get_temp_dir() . '/qms-responsibility-ready-b-' . $token,
];
$workerPath = __DIR__ . '/support/qms_responsibility_concurrent_worker.php';
$processes = [];

try {
    foreach ($readyPaths as $readyPath) {
        $pipes = [];
        $process = proc_open(
            [PHP_BINARY, $workerPath, $barrierPath, $readyPath],
            [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ],
            $pipes
        );
        if (!is_resource($process)) {
            throw new RuntimeException('Unable to start concurrent worker.');
        }
        fclose($pipes[0]);
        $processes[] = [
            'process' => $process,
            'stdout' => $pipes[1],
            'stderr' => $pipes[2],
            'exit_code' => null,
        ];
    }

    concurrency_wait_until_ready($readyPaths);
    file_put_contents($barrierPath, 'go', LOCK_EX);
    concurrency_wait_until_finished($processes);

    $results = [];
    foreach ($processes as $index => &$processInfo) {
        $stdout = stream_get_contents($processInfo['stdout']);
        $stderr = stream_get_contents($processInfo['stderr']);
        fclose($processInfo['stdout']);
        fclose($processInfo['stderr']);
        $closeCode = proc_close($processInfo['process']);
        $exitCode = is_int($processInfo['exit_code']) && $processInfo['exit_code'] >= 0
            ? $processInfo['exit_code']
            : $closeCode;
        $processInfo['process'] = null;
        catalog_assert($exitCode === 0, 'Concurrent worker exits successfully: ' . trim((string)$stderr));
        $result = json_decode(trim((string)$stdout), true);
        catalog_assert(is_array($result) && (string)($result['id'] ?? '') !== '', 'Concurrent worker returns a version id');
        $results[$index] = $result;
    }
    unset($processInfo);

    catalog_assert($results[0]['id'] === $results[1]['id'], 'Concurrent initialization returns the same version id');
    $versionId = (string)$results[0]['id'];
    catalog_assert(
        (int)Db::name('qms_responsibility_chain_versions')
            ->where('company_id', catalog_company_id())
            ->where('chain_code', 'core_governance')
            ->where('soft_delete', 0)
            ->count() === 1,
        'Concurrent initialization creates one version'
    );
    catalog_assert(
        (int)Db::name('qms_responsibility_activities')->where('chain_version_id', $versionId)->where('soft_delete', 0)->count() === 3,
        'Concurrent initialization creates three activities'
    );
    catalog_assert(
        (int)Db::name('qms_activity_responsibilities')->alias('r')
            ->join('qms_responsibility_activities a', 'a.id = r.activity_id')
            ->where('a.chain_version_id', $versionId)
            ->where('r.soft_delete', 0)
            ->count() === 21,
        'Concurrent initialization creates twenty-one duties'
    );
} finally {
    foreach ($processes as $processInfo) {
        if (is_resource($processInfo['process'])) {
            $status = proc_get_status($processInfo['process']);
            if (($status['running'] ?? false) === true) {
                proc_terminate($processInfo['process']);
            }
            foreach (['stdout', 'stderr'] as $streamName) {
                if (is_resource($processInfo[$streamName])) {
                    fclose($processInfo[$streamName]);
                }
            }
            proc_close($processInfo['process']);
        }
    }
    @unlink($barrierPath);
    foreach ($readyPaths as $readyPath) {
        @unlink($readyPath);
    }
    concurrency_cleanup_chain();
}

echo "qms_responsibility_concurrency_smoke passed\n";
