<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../app/common.php';

$app = new think\App();
$app->initialize();

use app\service\QmsDocumentStructureService;
use think\facade\Db;

function assert_same($expected, $actual, string $message): void
{
    if ($expected !== $actual) {
        fwrite(STDERR, $message . PHP_EOL);
        fwrite(STDERR, 'Expected: ' . var_export($expected, true) . PHP_EOL);
        fwrite(STDERR, 'Actual: ' . var_export($actual, true) . PHP_EOL);
        exit(1);
    }
}

function assert_true(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

QmsDocumentStructureService::seedAll();

$manualRows = Db::name('qms_document_blocks')
    ->alias('b')
    ->join('qms_structured_documents sd', 'sd.id = b.structured_document_id')
    ->join('qms_document_block_links l', 'l.block_id = b.id AND l.soft_delete = 0')
    ->join('documents d', 'd.id = l.procedure_document_id')
    ->join('qms_elements e', 'e.id = l.element_id')
    ->where('sd.document_role', 'quality_manual')
    ->where('sd.doc_number', 'XZTC/SC')
    ->where('b.soft_delete', 0)
    ->where('d.soft_delete', 0)
    ->whereIn('b.section_number', ['6.2', '6.4', '7.8', '8.8', '8.9'])
    ->field('b.section_number,e.key element_key,d.doc_number procedure_number,d.title procedure_title,l.relation_type,l.note')
    ->order('b.section_number', 'asc')
    ->order('d.doc_number', 'asc')
    ->select()
    ->toArray();

$bySection = [];
foreach ($manualRows as $row) {
    $bySection[(string)$row['section_number']][] = (string)$row['procedure_number'];
    assert_same('supporting', (string)$row['relation_type'], 'Manual-to-procedure links use supporting relation');
    assert_true(str_contains((string)$row['note'], '程序文件承接质量手册章节控制要求'), 'Manual-to-procedure link explains the evidence boundary');
}

foreach ($bySection as $section => $numbers) {
    $bySection[$section] = array_values(array_unique($numbers));
}

assert_true(in_array('XZTC/CX-01-2022', $bySection['6.2'] ?? [], true), 'Manual personnel section links to personnel training procedure');
assert_true(in_array('XZTC/CX-03-2022', $bySection['6.4'] ?? [], true), 'Manual equipment section links to equipment management procedure');
assert_true(in_array('XZTC/CX-29-2022', $bySection['7.8'] ?? [], true), 'Manual results-reporting section links to report procedure');
assert_true(in_array('XZTC/CX-20-2022', $bySection['8.8'] ?? [], true), 'Manual internal-audit section links to internal audit procedure');
assert_true(in_array('XZTC/CX-21-2022', $bySection['8.9'] ?? [], true), 'Manual management-review section links to management review procedure');

$detail = QmsDocumentStructureService::structuredDocumentDetail((string)Db::name('qms_structured_documents')
    ->where('document_role', 'quality_manual')
    ->where('doc_number', 'XZTC/SC')
    ->where('soft_delete', 0)
    ->value('id'));
$personnelBlock = array_values(array_filter(
    $detail['blocks'] ?? [],
    fn (array $row): bool => (string)$row['block']->section_number === '6.2'
))[0] ?? null;
assert_true(is_array($personnelBlock), 'Manual detail exposes the personnel section block');
$procedureLabels = array_values(array_filter(
    array_map(fn (array $link): string => trim((string)($link['procedure_number'] ?? '') . ' ' . (string)($link['procedure_title'] ?? '')), $personnelBlock['links']),
    fn (string $label): bool => $label !== ''
));
assert_true(in_array('XZTC/CX-01-2022 人员培训程序', $procedureLabels, true), 'Manual detail rows expose linked procedure labels');

echo "qms_manual_procedure_traceability_smoke passed\n";
