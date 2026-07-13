<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../app/common.php';

$app = new think\App();
$app->initialize();

use app\service\QmsClauseRemediationService;
use think\facade\Db;

function live_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

$sourceDir = trim((string)getenv('T1_SOURCE_DIR'));
live_assert($sourceDir !== '' && is_dir($sourceDir), 'T1_SOURCE_DIR must point to the reviewed Markdown sources');

$plan = QmsClauseRemediationService::buildPlan($sourceDir);
live_assert((int)$plan['counts']['total'] === 62, 'Live verification covers 62 approved requirement atoms');
live_assert((int)$plan['counts']['insert'] === 0, 'No approved requirement atom remains to insert');
live_assert((int)$plan['counts']['existing'] === 62, 'All 62 approved requirement atoms exist');
live_assert((int)$plan['counts']['conflict'] === 0, 'No approved requirement atom conflicts with reviewed source text');
live_assert((string)$plan['equivalence']['status'] === 'existing', 'CNAS-CL01 equivalence note exists');

$expectedCounts = [
    'GB/T 27025-2019' => 185,
    'CNAS-CL01-G001:2024' => 37,
    'CNAS-CL01-A015:2018' => 18,
    'CNAS-CL01:2018' => 0,
    '市场监管总局公告2023年第21号' => 28,
];
foreach ($expectedCounts as $sourceCode => $expected) {
    $actual = Db::name('qms_clauses')->alias('c')
        ->join('qms_sources s', 's.id = c.source_id')
        ->where('s.source_code', $sourceCode)
        ->where('s.soft_delete', 0)
        ->where('c.soft_delete', 0)
        ->count();
    live_assert((int)$actual === $expected, $sourceCode . ' clause count expected ' . $expected . ', got ' . (int)$actual);
}

$duplicates = Db::query(
    'SELECT c.source_id, c.clause_number, COUNT(*) AS duplicate_count '
    . 'FROM qms_clauses c WHERE c.soft_delete = 0 '
    . 'GROUP BY c.source_id, c.clause_number HAVING COUNT(*) > 1'
);
live_assert($duplicates === [], 'No active duplicate source/clause number exists');

foreach ((array)$plan['rows'] as $row) {
    $stored = Db::name('qms_clause_texts')->alias('t')
        ->join('qms_clauses c', 'c.id = t.clause_id')
        ->join('qms_sources s', 's.id = c.source_id')
        ->where('s.source_code', (string)$row['source_code'])
        ->where('c.clause_number', (string)$row['clause_number'])
        ->where('c.soft_delete', 0)
        ->where('t.soft_delete', 0)
        ->field('t.original_text,t.text_hash,t.extraction_method')
        ->find();
    live_assert(is_array($stored), 'Clause text exists: ' . (string)$row['source_code'] . ' ' . (string)$row['clause_number']);
    live_assert((string)$stored['text_hash'] === (string)$row['text_hash'], 'Stored hash matches reviewed source: ' . (string)$row['source_code'] . ' ' . (string)$row['clause_number']);
    live_assert(hash('sha256', trim((string)$stored['original_text'])) === (string)$stored['text_hash'], 'Stored text matches stored hash: ' . (string)$row['source_code'] . ' ' . (string)$row['clause_number']);
    live_assert((string)$stored['extraction_method'] === 'reviewed_markdown_requirement_atom', 'Stored extraction method is controlled: ' . (string)$row['source_code'] . ' ' . (string)$row['clause_number']);
}

$gbDefinitions = Db::name('qms_clauses')->alias('c')
    ->join('qms_sources s', 's.id = c.source_id')
    ->where('s.source_code', 'GB/T 27025-2019')
    ->whereIn('c.clause_number', ['3.1', '3.2', '3.3', '3.4', '3.5', '3.6', '3.7', '3.8', '3.9'])
    ->where('c.soft_delete', 0)
    ->count();
live_assert((int)$gbDefinitions === 0, 'GB 3.1-3.9 definitions were not inserted');

$a015References = Db::name('qms_clauses')->alias('c')
    ->join('qms_sources s', 's.id = c.source_id')
    ->where('s.source_code', 'CNAS-CL01-A015:2018')
    ->whereIn('c.clause_number', ['2.1', '2.2', '2.3', '2.4', '2.5', '2.6', '2.7', '2.8'])
    ->where('c.soft_delete', 0)
    ->count();
live_assert((int)$a015References === 0, 'A015 reference-file pointers were not inserted');

echo "qms_clause_remediation_live_verify passed\n";
