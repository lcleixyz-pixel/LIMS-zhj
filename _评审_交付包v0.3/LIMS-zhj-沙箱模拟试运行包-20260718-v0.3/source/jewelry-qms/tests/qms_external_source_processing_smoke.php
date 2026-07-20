<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../app/common.php';

$app = new think\App();
$app->initialize();

use app\service\QmsElementService;

function assert_true(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

function assert_contains(string $needle, string $haystack, string $message): void
{
    if (!str_contains($haystack, $needle)) {
        fwrite(STDERR, $message . PHP_EOL);
        fwrite(STDERR, 'Missing: ' . $needle . PHP_EOL);
        exit(1);
    }
}

QmsElementService::seedAll();

assert_true(
    method_exists(QmsElementService::class, 'externalSourceProcessingRows'),
    'Element service exposes external source processing rows'
);

$rows = QmsElementService::externalSourceProcessingRows();
assert_true(count($rows) >= 5, 'External source processing rows cover registered official sources');

$rowsByCode = [];
foreach ($rows as $row) {
    $rowsByCode[(string)$row['source']->source_code] = $row;
    assert_true(isset($row['archive_status']), 'Source row includes archive status');
    assert_true(isset($row['extraction_status']), 'Source row includes extraction status');
    assert_true(isset($row['clause_count']), 'Source row includes clause count');
    assert_true(isset($row['matched_element_count']), 'Source row includes matched element count');
    assert_true(isset($row['unmatched_clause_count']), 'Source row includes unmatched clause count');
}

assert_true(isset($rowsByCode['GB/T 27025-2019']), 'Processing rows include GB/T 27025 source');
$gb = $rowsByCode['GB/T 27025-2019'];
assert_true($gb['archive_status'] === 'archived', 'GB/T source archive file is available');
assert_true($gb['extraction_status'] === 'extracted', 'GB/T source has extracted clauses');
assert_true((int)$gb['clause_count'] > 80, 'GB/T source exposes complete clause count');
assert_true((int)$gb['matched_element_count'] >= 20, 'GB/T source clauses are matched to unnumbered elements');
assert_true((int)$gb['unmatched_clause_count'] > 0, 'GB/T source row distinguishes unmatched lower-level clauses');
assert_true(str_contains((string)$gb['archive_path'], '参考/2025年最新版CMA和CNAS质量体系/05-GBT 27025-2019'), 'GB/T archive path points to controlled source file');
assert_true((string)$gb['freshness_evidence'] !== '', 'GB/T source keeps freshness evidence');

$controllerSource = file_get_contents(dirname(__DIR__) . '/app/controller/PlanningSource.php') ?: '';
assert_contains('externalSourceProcessingRows', $controllerSource, 'Source controller assigns external source processing rows');

$viewSource = file_get_contents(dirname(__DIR__) . '/app/view/planning_source/index.html') ?: '';
foreach (['归档文件', '条款数', '匹配要素', '未匹配条款', '查新证据'] as $label) {
    assert_contains($label, $viewSource, 'Source page shows ' . $label);
}

echo "qms_external_source_processing_smoke passed\n";
