<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../app/common.php';

use app\service\QmsManualProcedureAlignmentReportService;
use app\service\QmsManualProcedureAlignmentService;
use app\service\QmsManualProcedureTraceService;

function command_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

function command_remove_tree(string $path): void
{
    if (!is_dir($path)) {
        return;
    }
    foreach (array_diff(scandir($path) ?: [], ['.', '..']) as $entry) {
        $target = $path . DIRECTORY_SEPARATOR . $entry;
        is_dir($target) ? command_remove_tree($target) : unlink($target);
    }
    rmdir($path);
}

$fixture = __DIR__ . '/fixtures/qms_manual_procedure_alignment';
$loaded = QmsManualProcedureAlignmentService::loadInputs(
    $fixture . '/pilot-spec.json',
    $fixture . '/procedures'
);
$trace = QmsManualProcedureTraceService::fromSnapshot($fixture . '/trace-snapshot.json');
$result = QmsManualProcedureAlignmentService::check($loaded, $trace);
$tmpDir = sys_get_temp_dir() . '/qms-alignment-command-smoke-' . getmypid();
command_remove_tree($tmpDir);

try {
    $paths = QmsManualProcedureAlignmentReportService::write($result, $tmpDir, '手册程序一致性校验-v0.1');
    command_assert(is_file($paths['json']), 'JSON report is written');
    command_assert(is_file($paths['csv']), 'CSV report is written');
    command_assert(is_file($paths['markdown']), 'Markdown report is written');
    command_assert(str_contains((string)file_get_contents($paths['markdown']), 'Y14'), 'Markdown contains Y14');
    command_assert(str_contains((string)file_get_contents($paths['markdown']), '人工复核'), 'Markdown explains review-required findings');

    try {
        QmsManualProcedureAlignmentReportService::write($result, $tmpDir, '手册程序一致性校验-v0.1');
        command_assert(false, 'Existing report prefix must not be overwritten');
    } catch (RuntimeException $exception) {
        command_assert(str_contains($exception->getMessage(), '报告已存在'), 'Existing report is blocked with a clear message');
    }

    $commandSource = file_get_contents(dirname(__DIR__) . '/app/command/QmsManualProcedureAlignmentCheck.php') ?: '';
    command_assert(str_contains($commandSource, "setName('qms:check-manual-procedure-alignment')"), 'Read-only command is defined');
    command_assert(!str_contains($commandSource, "addOption('apply'"), 'Read-only command has no apply option');

    $consoleSource = file_get_contents(dirname(__DIR__) . '/config/console.php') ?: '';
    command_assert(str_contains($consoleSource, 'QmsManualProcedureAlignmentCheck::class'), 'Read-only command is registered');
} finally {
    command_remove_tree($tmpDir);
}

echo "qms_manual_procedure_alignment_command_smoke passed\n";
