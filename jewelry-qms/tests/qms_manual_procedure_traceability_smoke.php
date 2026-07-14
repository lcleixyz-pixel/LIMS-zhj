<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../app/common.php';

$app = new think\App();
$app->initialize();

use app\service\QmsDocumentStructureService;
use app\service\QmsManualProcedureTraceService;
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

Db::startTrans();
try {
    $companyId = (string)config('qms.company_id');
    $foreignCompanyId = 'ffffffff-ffff-ffff-ffff-ffffffffffff';
    $suffix = strtoupper(substr(str_replace('-', '', qms_uuid()), 0, 8));
    $procedureNumber = 'XZTC/CX-SCOPE-' . $suffix . '-2022';
    $sectionNumber = '8.8.' . substr($suffix, 0, 4);
    $elementId = qms_uuid();
    $now = date('Y-m-d H:i:s');

    $currentDocumentId = qms_uuid();
    $foreignDocumentId = qms_uuid();
    foreach ([
        [$currentDocumentId, $companyId, '当前公司程序'],
        [$foreignDocumentId, $foreignCompanyId, '外部公司同号程序'],
    ] as [$documentId, $ownerCompanyId, $title]) {
        Db::name('documents')->insert([
            'id' => $documentId,
            'company_id' => $ownerCompanyId,
            'level' => 2,
            'doc_number' => $procedureNumber,
            'title' => $title,
            'version' => 'A/0',
            'status' => 'published',
            'publish' => 1,
            'soft_delete' => 0,
            'created' => $now,
            'modified' => $now,
        ]);
    }

    $currentStructuredId = qms_uuid();
    $foreignStructuredId = qms_uuid();
    foreach ([
        [$currentStructuredId, $companyId, '当前公司质量手册'],
        [$foreignStructuredId, $foreignCompanyId, '外部公司质量手册'],
    ] as [$structuredId, $ownerCompanyId, $title]) {
        Db::name('qms_structured_documents')->insert([
            'id' => $structuredId,
            'company_id' => $ownerCompanyId,
            'document_role' => 'quality_manual',
            'doc_number' => 'XZTC/SC',
            'title' => $title,
            'version' => '第四版',
            'source_status' => 'current',
            'status' => 'published',
            'publish' => 1,
            'soft_delete' => 0,
            'created' => $now,
            'modified' => $now,
        ]);
    }

    $currentBlockId = qms_uuid();
    $foreignBlockId = qms_uuid();
    foreach ([
        [$currentBlockId, $companyId, $currentStructuredId, '当前公司手册块'],
        [$foreignBlockId, $foreignCompanyId, $foreignStructuredId, '外部公司手册块'],
    ] as [$blockId, $ownerCompanyId, $structuredId, $title]) {
        Db::name('qms_document_blocks')->insert([
            'id' => $blockId,
            'company_id' => $ownerCompanyId,
            'structured_document_id' => $structuredId,
            'stable_key' => 'scope-' . $blockId,
            'section_number' => $sectionNumber,
            'title' => $title,
            'block_type' => 'section',
            'markdown' => $title,
            'status' => 'effective',
            'publish' => 1,
            'soft_delete' => 0,
            'created' => $now,
            'modified' => $now,
        ]);
    }

    foreach ([
        [$companyId, $currentBlockId, $currentDocumentId],
        [$foreignCompanyId, $foreignBlockId, $foreignDocumentId],
        // Adversarial cross-company rows prove join predicates cannot bridge tenants.
        [$foreignCompanyId, $currentBlockId, $foreignDocumentId],
        [$companyId, $currentBlockId, $foreignDocumentId],
    ] as [$ownerCompanyId, $blockId, $documentId]) {
        Db::name('qms_document_block_links')->insert([
            'id' => qms_uuid(),
            'company_id' => $ownerCompanyId,
            'block_id' => $blockId,
            'element_id' => $elementId,
            'procedure_document_id' => $documentId,
            'relation_type' => 'supporting',
            'confidence' => 'high',
            'publish' => 1,
            'soft_delete' => 0,
            'created' => $now,
            'modified' => $now,
        ]);
    }

    foreach ([
        [$companyId, $currentDocumentId],
        [$foreignCompanyId, $foreignDocumentId],
    ] as [$ownerCompanyId, $documentId]) {
        Db::name('qms_element_documents')->insert([
            'id' => qms_uuid(),
            'company_id' => $ownerCompanyId,
            'element_id' => $elementId,
            'document_id' => $documentId,
            'relation_type' => 'primary',
            'publish' => 1,
            'soft_delete' => 0,
            'created' => $now,
            'modified' => $now,
        ]);
    }

    $legacyTrace = QmsManualProcedureTraceService::fromDatabase([$sectionNumber], [$procedureNumber]);
    assert_same([], $legacyTrace['_blockers'], 'Default trace query ignores same-number published documents from other companies');
    $legacyCandidates = (array)($legacyTrace[$sectionNumber] ?? []);
    assert_true($legacyCandidates !== [], 'Default trace query retains current-company formal and element links');
    foreach ($legacyCandidates as $candidate) {
        assert_same($currentDocumentId, (string)$candidate['procedure_document_id'], 'Default trace candidates remain in the configured company');
    }
    assert_true(!in_array($procedureNumber, $legacyTrace['_unlinked'], true), 'Current-company procedure remains linked');

    $explicitTrace = QmsManualProcedureTraceService::fromDatabase([$sectionNumber], [$procedureNumber], $companyId);
    assert_same($legacyTrace, $explicitTrace, 'Legacy no-company call remains compatible with the configured company');

    $foreignTrace = QmsManualProcedureTraceService::fromDatabase([$sectionNumber], [$procedureNumber], $foreignCompanyId);
    assert_same([], $foreignTrace['_blockers'], 'Explicit foreign-company trace is isolated from current-company documents');
    $foreignCandidates = (array)($foreignTrace[$sectionNumber] ?? []);
    assert_true($foreignCandidates !== [], 'Explicit foreign-company trace retains its own formal and element links');
    foreach ($foreignCandidates as $candidate) {
        assert_same($foreignDocumentId, (string)$candidate['procedure_document_id'], 'Explicit foreign-company trace returns only its own links');
    }
} finally {
    Db::rollback();
}

echo "qms_manual_procedure_traceability_smoke passed\n";
