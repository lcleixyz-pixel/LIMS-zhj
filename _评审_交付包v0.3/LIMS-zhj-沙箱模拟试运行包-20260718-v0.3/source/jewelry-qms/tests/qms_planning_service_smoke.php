<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../app/common.php';

if (!function_exists('root_path')) {
    function root_path(): string
    {
        return dirname(__DIR__) . DIRECTORY_SEPARATOR;
    }
}

use app\service\QmsElementService;
use app\service\QmsDocumentStructureService;
use app\service\QmsPlanningImportService;
use app\service\RecordFormBatchTemplateService;

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

function assert_array_has_key_strict(string $key, array $array, string $message): void
{
    if (!array_key_exists($key, $array)) {
        fwrite(STDERR, $message . PHP_EOL);
        fwrite(STDERR, 'Missing key: ' . $key . PHP_EOL);
        exit(1);
    }
}

$sources = QmsPlanningImportService::officialSourceManifest();
assert_same(
    ['CNAS-CL01:2018', 'CNAS-CL01-G001:2024', 'CNAS-CL01-A015:2018', 'GB/T 27025-2019', '市场监管总局公告2023年第21号'],
    array_column($sources, 'source_code'),
    'Official source manifest uses normalized official identifiers'
);
foreach ($sources as $source) {
    assert_true(is_file($source['absolute_path']), 'Official source file exists: ' . $source['source_code']);
}

$parsedGb = QmsPlanningImportService::parseSourceFilename('05-GBT 27025-2019 检测和校准实验室能力的通用要求.pdf');
assert_same('GB/T 27025-2019', $parsedGb['source_code'], 'Parses GB/T official code from local file name');
assert_same('检测和校准实验室能力的通用要求', $parsedGb['name'], 'Parses GB/T title from local file name');

$gbSource = array_values(array_filter($sources, fn (array $row): bool => $row['source_code'] === 'GB/T 27025-2019'))[0];
$gbRows = QmsPlanningImportService::buildRegisteredSourceClauseRows([
    'id' => 'source-gbt27025',
    'source_code' => 'GB/T 27025-2019',
    'status' => 'published',
    'attachment_file_path' => $gbSource['relative_path'],
    'attachment_file_name' => $gbSource['file_name'],
]);
$gbByNumber = [];
foreach ($gbRows as $row) {
    $gbByNumber[(string)$row['clause_number']] = $row;
}
assert_true(count($gbRows) > 80, 'GB/T 27025 extracts a complete clause layer');
assert_same('人员', $gbByNumber['6.2']['title'] ?? '', 'Clause 6.2 title stays on the clause layer');
assert_same('技术记录内容', $gbByNumber['7.5.1']['title'] ?? '', 'Generated review titles still summarize extracted text');
assert_true(
    str_contains((string)($gbByNumber['7.5.1']['original_text'] ?? ''), '实验室应确保每一项实验室活动的技术记录包含结果报告和足够的信息'),
    'Clause rows keep automatically extracted original text'
);

$elements = QmsElementService::defaultElementDefinitions();
$elementByKey = [];
foreach ($elements as $row) {
    assert_array_has_key_strict('key', $row, 'Element definitions use invisible keys');
    assert_array_has_key_strict('name', $row, 'Element definitions expose names');
    assert_array_has_key_strict('primary_clause_number', $row, 'Element definitions remember primary 27025 clause for sorting');
    assert_true(!array_key_exists('code', $row), 'Element definitions do not expose business code');
    assert_true(!preg_match('/^\d+(\\.\\d+)*$/', (string)$row['name']), 'Element names are not numeric clause labels');
    $elementByKey[(string)$row['key']] = $row;
}

foreach ([
    'personnel' => ['人员', '6.2'],
    'equipment' => ['设备', '6.4'],
    'results_reporting' => ['结果报告', '7.8'],
    'internal_audit' => ['内部审核', '8.8'],
    'management_review' => ['管理评审', '8.9'],
] as $key => [$name, $primaryClause]) {
    assert_array_has_key_strict($key, $elementByKey, 'Default elements include ' . $key);
    assert_same($name, $elementByKey[$key]['name'], 'Element ' . $key . ' uses unnumbered Chinese name');
    assert_same($primaryClause, $elementByKey[$key]['primary_clause_number'], 'Element ' . $key . ' maps to primary 27025 clause for order');
}

$manualBaseline = QmsPlanningImportService::extractCurrentManualBaseline();
assert_array_has_key_strict('manual_sections', $manualBaseline, 'Current manual baseline exposes manual sections, not numbered elements');
assert_array_has_key_strict('manual_section_clause_mappings', $manualBaseline, 'Current manual baseline maps manual sections to external clauses');
assert_true(!array_key_exists('requirement_elements', $manualBaseline), 'Current manual baseline no longer exposes legacy requirement_elements');
assert_true(!array_key_exists('element_clause_mappings', $manualBaseline), 'Current manual baseline no longer exposes legacy element_clause_mappings');
$manualSection = $manualBaseline['manual_sections'][0] ?? [];
assert_array_has_key_strict('section_number', $manualSection, 'Manual section baseline carries a section number');
assert_array_has_key_strict('title', $manualSection, 'Manual section baseline carries a title');
assert_true(!array_key_exists('element_code', $manualSection), 'Manual section baseline does not label section numbers as element codes');
$sectionClauseMapping = $manualBaseline['manual_section_clause_mappings'][0] ?? [];
assert_array_has_key_strict('section_number', $sectionClauseMapping, 'Manual section clause mapping carries section_number');
assert_true(!array_key_exists('element_code', $sectionClauseMapping), 'Manual section clause mapping does not label section numbers as element codes');

$currentManualCandidates = QmsPlanningImportService::buildCurrentManualCandidates();
foreach ($currentManualCandidates as $candidate) {
    assert_true(($candidate['candidate_type'] ?? '') !== 'requirement_element', 'Current manual candidate flow no longer emits requirement_element candidates');
    $payload = (array)($candidate['payload'] ?? []);
    assert_true(($payload['source_type'] ?? '') !== 'requirement_element', 'Current manual trace candidates no longer use requirement_element source type');
    assert_true(!array_key_exists('element_code', $payload), 'Current manual candidate payload does not expose element_code');
}

$trainingTraceSample = QmsPlanningImportService::trainingTraceabilitySample();
assert_array_has_key_strict('element_key', $trainingTraceSample, 'Training trace sample uses the invisible element key');
assert_true(!array_key_exists('element_code', $trainingTraceSample), 'Training trace sample does not expose a numbered element code');
$trainingTraceCandidates = QmsPlanningImportService::buildTrainingTraceCandidates();
assert_same('qms_element', $trainingTraceCandidates[0]['payload']['source_type'] ?? '', 'Training trace starts from an unnumbered element node');
assert_same('personnel', $trainingTraceCandidates[0]['payload']['source_key'] ?? '', 'Training trace identifies the element by invisible key');

$modules = QmsElementService::businessModuleDefinitions();
$moduleCodes = array_column($modules, 'code');
foreach (['trainings', 'equipment', 'calibrations', 'management_reviews', 'capas'] as $moduleCode) {
    assert_true(in_array($moduleCode, $moduleCodes, true), 'Business module baseline includes ' . $moduleCode);
}

$structureLayers = QmsDocumentStructureService::structureLayerDefinitions();
assert_same(
    ['external_basis', 'quality_manual', 'procedure', 'work_instruction', 'record_form'],
    array_column($structureLayers, 'key'),
    'Structured document layers follow the external basis to manual to procedure/work-instruction to record form chain'
);
assert_true(
    str_contains((string)$structureLayers[1]['workflow'], '归档 -> Markdown结构化 -> 要素匹配 -> 程序关联 -> 渲染输出'),
    'Quality manual layer describes archive, markdown, element matching, procedure linking, and rendering'
);
assert_true(
    str_contains((string)$structureLayers[2]['workflow'], '职责分配 -> 记录表格关联'),
    'Procedure layer owns responsibility and record form links'
);
assert_true(
    str_contains((string)$structureLayers[3]['workflow'], '三级文件归档 -> Markdown结构化'),
    'Work instruction layer describes level-three file archive and markdown structuring'
);

$manualBlocks = QmsDocumentStructureService::manualBlockBlueprints();
assert_true(count($manualBlocks) >= count($elements), 'Manual block blueprints cover the default unnumbered element set');
$manualByElement = [];
foreach ($manualBlocks as $block) {
    assert_array_has_key_strict('stable_key', $block, 'Manual block has stable key');
    assert_array_has_key_strict('element_key', $block, 'Manual block links by invisible element key');
    assert_array_has_key_strict('markdown', $block, 'Manual block has markdown body');
    assert_true(!str_contains((string)$block['title'], (string)$block['section_number'] . ' '), 'Manual block title keeps section number out of the element title');
    $manualByElement[(string)$block['element_key']] = $block;
}
foreach (['personnel', 'equipment', 'results_reporting', 'internal_audit', 'management_review'] as $key) {
    assert_array_has_key_strict($key, $manualByElement, 'Manual markdown layer includes ' . $key);
}

$manualSourceBlocks = QmsDocumentStructureService::manualSourceBlockBlueprints([
    'doc_number' => 'QM-04',
    'title' => '质量手册（第四版）',
    'file_path' => '现用文件/质量手册（第四版）.docx',
]);
$manualSourceBySection = [];
foreach ($manualSourceBlocks as $block) {
    $manualSourceBySection[(string)$block['section_number']] = $block;
}
foreach (['6.2', '6.4', '8.8', '8.9'] as $sectionNumber) {
    assert_array_has_key_strict($sectionNumber, $manualSourceBySection, 'Manual source markdown is split into section ' . $sectionNumber);
    assert_true((string)$manualSourceBySection[$sectionNumber]['source_locator'] !== '', 'Manual source block keeps source locator for ' . $sectionNumber);
}
assert_same('人员', $manualSourceBySection['6.2']['title'], 'Manual source section 6.2 keeps the source title');
assert_true(str_contains((string)$manualSourceBySection['6.2']['markdown'], '岗位任职资格条件'), 'Manual source section 6.2 keeps personnel requirements');
assert_true(str_contains((string)$manualSourceBySection['6.4']['markdown'], '仪器设备'), 'Manual source section 6.4 keeps equipment content');
assert_true(str_contains((string)$manualSourceBySection['8.8']['markdown'], '内部审核'), 'Manual source section 8.8 keeps internal audit content');

$procedureBlueprint = QmsDocumentStructureService::procedureBlockBlueprint([
    'doc_number' => 'QP-01',
    'title' => '人员培训程序',
    'file_path' => '现用文件/程序文件/程序文件2022/01-2022人员培训程序.docx',
    'file_type' => 'docx',
], $elementByKey['personnel']);
assert_same('control_requirement', $procedureBlueprint['block_type'], 'Procedure element blueprint is a control requirement block');
assert_true(str_contains($procedureBlueprint['markdown'], '人员培训程序'), 'Procedure blueprint keeps source procedure title');
assert_true(str_contains($procedureBlueprint['markdown'], '记录表格'), 'Procedure blueprint reserves record-form linkage');

$resolvedRecordPath = QmsDocumentStructureService::resolveRecordFormSourcePath([
    'doc_number' => 'XZTC/BG-01-01',
    'name' => '年度人员培训计划表',
    'source_file_name' => '01-01《年度人员培训计划表》.doc',
    'source_file_path' => 'uploads/record-form-sources/missing/old.doc',
]);
assert_same(
    '现用文件/记录表格/记录表格2017/01人员培训程序/01-01《年度人员培训计划表》.doc',
    $resolvedRecordPath,
    'Record form source resolver replaces stale upload path with the current controlled source file'
);
$duplicateRecordPath = QmsDocumentStructureService::resolveRecordFormSourcePath([
    'doc_number' => '待定-16-01',
    'name' => '实施纠正措施记录表',
    'source_file_name' => '',
    'source_file_path' => 'uploads/record-form-sources/missing/duplicate.doc',
]);
assert_same(
    '现用文件/记录表格/记录表格2017/16实施纠正措施程序/16-01实施纠正措施记录表.doc',
    $duplicateRecordPath,
    'Record form source resolver allows duplicate template rows to share one controlled source file'
);

$procedureMarkdown = QmsDocumentStructureService::markdownFromSourcePath('现用文件/程序文件/程序文件2022/01-2022人员培训程序.docx', 80);
assert_true(str_contains($procedureMarkdown, '# 人员培训程序'), 'Procedure markdown extraction keeps the source title');
assert_true(str_contains($procedureMarkdown, '## 目的'), 'Procedure markdown extraction promotes purpose section');
assert_true(str_contains($procedureMarkdown, '## 职责'), 'Procedure markdown extraction promotes responsibility section');
assert_true(str_contains($procedureMarkdown, '《年度人员培训计划表》'), 'Procedure markdown extraction keeps record-form references');

$procedureSourceBlocks = QmsDocumentStructureService::procedureSourceBlockBlueprints([
    'doc_number' => 'QP-01',
    'title' => '人员培训程序',
    'file_path' => '现用文件/程序文件/程序文件2022/01-2022人员培训程序.docx',
]);
$sourceBlockByTitle = [];
foreach ($procedureSourceBlocks as $block) {
    $sourceBlockByTitle[(string)$block['title']] = $block;
}
foreach (['目的', '范围', '职责', '工作程序', '相关文件', '记录'] as $title) {
    assert_array_has_key_strict($title, $sourceBlockByTitle, 'Procedure source markdown is split into ' . $title . ' block');
}
assert_same('purpose', $sourceBlockByTitle['目的']['block_type'], 'Purpose source block uses schema-supported block type');
assert_same('responsibility', $sourceBlockByTitle['职责']['block_type'], 'Responsibility source block uses schema-supported block type');
assert_same('record_requirement', $sourceBlockByTitle['记录']['block_type'], 'Record source block uses schema-supported block type');
assert_true(str_contains((string)$sourceBlockByTitle['职责']['markdown'], '实验室主任'), 'Responsibility source block keeps role text');
assert_same(
    ['lab_director', 'technical_manager', 'quality_manager', 'office_manager', 'document_controller'],
    array_column(QmsDocumentStructureService::positionMentionsForMarkdown((string)$sourceBlockByTitle['职责']['markdown']), 'position_code'),
    'Responsibility source block extracts mentioned positions from the procedure text'
);
assert_true(str_contains((string)$sourceBlockByTitle['记录']['markdown'], 'XZTC/BG-01-01'), 'Record source block keeps record form number');
assert_true(str_contains((string)$sourceBlockByTitle['记录']['markdown'], '《年度人员培训计划表》'), 'Record source block keeps record form title');

$standardMaterialBlocks = QmsDocumentStructureService::procedureSourceBlockBlueprints([
    'doc_number' => 'QP-03-02',
    'title' => '标准物质管理程序',
    'file_path' => '现用文件/程序文件/程序文件2022/03-02-2022标准物质管理程序.docx',
]);
$standardMaterialByTitle = [];
foreach ($standardMaterialBlocks as $block) {
    $standardMaterialByTitle[(string)$block['title']] = $block;
}
foreach (['目的', '范围', '职责', '工作程序', '相关文件'] as $title) {
    assert_array_has_key_strict($title, $standardMaterialByTitle, 'Number-dot procedure source heading is split into ' . $title . ' block');
}
assert_true(str_contains((string)$standardMaterialByTitle['职责']['markdown'], '设备管理员负责标准物质管理'), 'Number-dot responsibility block keeps standard-material role text');

$antiBriberyBlocks = QmsDocumentStructureService::procedureSourceBlockBlueprints([
    'doc_number' => 'QP-07-02',
    'title' => '防止商业贿赂程序',
    'file_path' => '现用文件/程序文件/程序文件2022/07-02-2022防止商业贿赂程序.doc',
]);
$antiBriberyTitles = array_column($antiBriberyBlocks, 'title');
foreach (['目的', '范围', '职责', '工作程序', '相关文件'] as $title) {
    assert_true(in_array($title, $antiBriberyTitles, true), 'Procedure heading alias is split into ' . $title . ' block');
}

$monitorBlocks = QmsDocumentStructureService::procedureSourceBlockBlueprints([
    'doc_number' => 'QP-34',
    'title' => '内部监控管理程序',
    'file_path' => '现用文件/程序文件/程序文件2022/34-2022内部监控管理程序.doc',
]);
$monitorByTitle = [];
foreach ($monitorBlocks as $block) {
    $monitorByTitle[(string)$block['title']] = $block;
}
assert_array_has_key_strict('记录', $monitorByTitle, 'Unnumbered related-record heading is split into record block');
assert_same('record_requirement', $monitorByTitle['记录']['block_type'], 'Related-record heading uses record requirement block type');
assert_true(str_contains((string)$monitorByTitle['记录']['markdown'], '监控维护管理记录表'), 'Related-record block keeps referenced monitoring record form');
assert_true(
    in_array('testing_room_manager', array_column(QmsDocumentStructureService::positionMentionsForMarkdown((string)$monitorByTitle['职责']['markdown']), 'position_code'), true),
    'Monitoring procedure responsibility block extracts testing room manager from source text'
);

$monitorMaintenanceTemplate = array_values(array_filter(
    RecordFormBatchTemplateService::manifest(),
    fn (array $row): bool => $row['doc_number'] === 'XZTC/BG-34-01' && str_contains($row['name'], '监控维护管理记录')
))[0] ?? null;
assert_true(is_array($monitorMaintenanceTemplate), 'Monitoring maintenance template exists in batch manifest');
$monitorSchemaMarkdown = QmsDocumentStructureService::recordFormSchemaMarkdown($monitorMaintenanceTemplate);
assert_true(str_contains($monitorSchemaMarkdown, '### 字段schema'), 'Record form structure markdown renders schema fields');
assert_true(str_contains($monitorSchemaMarkdown, 'maintenance_items'), 'Record form structure markdown renders the schema field key');
assert_true(str_contains($monitorSchemaMarkdown, '维护管理时间'), 'Record form structure markdown renders procedure-required column labels');
assert_true(str_contains($monitorSchemaMarkdown, '监控摄像头'), 'Record form structure markdown renders monitoring source-table columns');

echo "qms_planning_service_smoke passed\n";
