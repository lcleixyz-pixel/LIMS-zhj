<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../app/common.php';

$app = new think\App();
$app->initialize();

use app\service\QmsDocumentStructureService;
use think\facade\Config;
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

function assert_contains(string $needle, string $haystack, string $message): void
{
    if (!str_contains($haystack, $needle)) {
        fwrite(STDERR, $message . PHP_EOL);
        fwrite(STDERR, 'Missing: ' . $needle . PHP_EOL);
        exit(1);
    }
}

function inventory_row_for(array $inventory, string $role, string $docNumber): ?array
{
    foreach ($inventory as $row) {
        if ((string)($row['document_role'] ?? '') === $role && (string)($row['doc_number'] ?? '') === $docNumber) {
            return $row;
        }
    }

    return null;
}

function inventory_has_doc_number(array $inventory, string $docNumber): bool
{
    foreach ($inventory as $row) {
        if ((string)($row['doc_number'] ?? '') === $docNumber) {
            return true;
        }
    }

    return false;
}

function impact_row_for_note(array $rows, string $note): ?array
{
    foreach ($rows as $row) {
        if ((string)($row['revision_note'] ?? '') === $note) {
            return $row;
        }
    }

    return null;
}

function block_trace_row_for(array $rows, string $docNumber, string $stableKey): ?array
{
    foreach ($rows as $row) {
        if ((string)($row['doc_number'] ?? '') === $docNumber && (string)($row['block_stable_key'] ?? '') === $stableKey) {
            return $row;
        }
    }

    return null;
}

assert_true(
    method_exists(QmsDocumentStructureService::class, 'renderSystemPackage'),
    'Structure service can render a combined QMS document package'
);
assert_true(
    method_exists(QmsDocumentStructureService::class, 'latestSystemPackageChangeImpactRows'),
    'Structure service exposes latest package change impact rows for UI review'
);

QmsDocumentStructureService::seedAll();
$beforeSummary = QmsDocumentStructureService::systemPackageSummary();
$beforeArchiveCount = (int)($beforeSummary['archive_count'] ?? 0);
$impactBlock = Db::name('qms_document_blocks')
    ->alias('b')
    ->join('qms_structured_documents sd', 'sd.id = b.structured_document_id')
    ->where('sd.doc_number', 'XZTC/CX-26-2022')
    ->where('b.block_type', 'record_requirement')
    ->where('b.soft_delete', 0)
    ->field('b.id,b.title,b.stable_key,sd.id structured_document_id,sd.document_role,sd.doc_number,sd.title document_title,sd.version')
    ->find();
assert_true(is_array($impactBlock), 'XZTC/CX-26-2022 has a block for package change impact navigation smoke');
$impactNote = '组合包影响清单跳转 smoke：XZTC/CX-26-2022 记录要求';
Db::name('qms_document_change_logs')
    ->where('structured_document_id', (string)$impactBlock['structured_document_id'])
    ->where('revision_note', $impactNote)
    ->delete();
Db::name('qms_document_change_logs')->insert([
    'id' => qms_uuid(),
    'company_id' => (string)Config::get('qms.company_id'),
    'structured_document_id' => (string)$impactBlock['structured_document_id'],
    'block_id' => (string)$impactBlock['id'],
    'document_id' => null,
    'change_type' => 'block_update',
    'revision_note' => $impactNote,
    'old_markdown_sha256' => hash('sha256', 'old'),
    'new_markdown_sha256' => hash('sha256', 'new'),
    'old_excerpt' => 'old',
    'new_excerpt' => 'new',
    'rendered_file_path' => '',
    'archive_path' => '',
    'trace_snapshot_json' => json_encode([
        'structured_document' => [
            'id' => (string)$impactBlock['structured_document_id'],
            'document_role' => (string)$impactBlock['document_role'],
            'doc_number' => (string)$impactBlock['doc_number'],
            'title' => (string)$impactBlock['document_title'],
            'version' => (string)$impactBlock['version'],
        ],
        'block' => [
            'id' => (string)$impactBlock['id'],
            'stable_key' => (string)$impactBlock['stable_key'],
            'title' => (string)$impactBlock['title'],
            'block_type' => 'record_requirement',
        ],
        'links' => [[
            'element_name' => '数据控制和信息管理',
            'source_code' => 'GB/T 27025-2019',
            'clause_number' => '7.11',
            'procedure_number' => 'XZTC/CX-26-2022',
            'procedure_title' => '计算机文件及数据控制程序',
            'record_number' => 'XZTC/BG-26-01',
            'record_name' => '计算机软件登记表',
        ]],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    'status_from' => 'structured',
    'status_to' => 'draft',
    'publish' => 1,
    'soft_delete' => 0,
    'created' => date('Y-m-d H:i:s'),
    'modified' => date('Y-m-d H:i:s'),
]);
$summary = QmsDocumentStructureService::renderSystemPackage();

assert_true(is_array($summary), 'Package render returns a summary');
assert_true(isset($summary['output_path']), 'Package summary includes output path');
assert_true(is_file(dirname(__DIR__) . '/' . $summary['output_path']), 'Rendered package markdown file exists');
assert_true(isset($summary['archive_path']), 'Package render summary includes immutable archive path');
assert_true(isset($summary['manifest_path']), 'Package render summary includes archive manifest path');
assert_true(is_file(dirname(__DIR__) . '/' . $summary['archive_path']), 'Rendered package archive markdown file exists');
assert_true(is_file(dirname(__DIR__) . '/' . $summary['manifest_path']), 'Package archive manifest exists');
assert_true((int)$summary['archive_count'] >= $beforeArchiveCount + 1, 'Package archive count increases after rendering');

$roleCounts = [];
foreach (Db::name('qms_structured_documents')
    ->where('soft_delete', 0)
    ->whereIn('status', ['structured', 'published'])
    ->group('document_role')
    ->field('document_role,count(*) count')
    ->select()
    ->toArray() as $row) {
    $roleCounts[(string)$row['document_role']] = (int)$row['count'];
}

assert_same($roleCounts['quality_manual'] ?? 0, (int)$summary['document_count_by_role']['quality_manual'], 'Package summary counts quality manual documents');
assert_same($roleCounts['procedure'] ?? 0, (int)$summary['document_count_by_role']['procedure'], 'Package summary counts procedure documents');
assert_same($roleCounts['external_basis'] ?? 0, (int)$summary['document_count_by_role']['external_basis'], 'Package summary counts external basis documents');
assert_same($roleCounts['work_instruction'] ?? 0, (int)$summary['document_count_by_role']['work_instruction'], 'Package summary counts work instruction documents');
assert_same($roleCounts['record_form'] ?? 0, (int)$summary['document_count_by_role']['record_form'], 'Package summary counts record form documents');

$markdown = file_get_contents(dirname(__DIR__) . '/' . $summary['output_path']) ?: '';
$archiveMarkdown = file_get_contents(dirname(__DIR__) . '/' . $summary['archive_path']) ?: '';
$manifest = json_decode(file_get_contents(dirname(__DIR__) . '/' . $summary['manifest_path']) ?: '[]', true);
assert_true(is_array($manifest), 'Package archive manifest is valid JSON');
assert_true($manifest !== [], 'Package archive manifest has at least one entry');
$latestManifestEntry = end($manifest);
assert_same($summary['archive_path'], (string)($latestManifestEntry['archive_path'] ?? ''), 'Package archive manifest records the latest archive path');
assert_same((int)$summary['total_documents'], (int)($latestManifestEntry['total_documents'] ?? 0), 'Package archive manifest records total document count');
assert_true(isset($latestManifestEntry['content_sha256']), 'Package archive manifest records content hash');
assert_same(hash('sha256', $markdown), (string)$latestManifestEntry['content_sha256'], 'Package archive manifest hash matches current markdown');
assert_same(hash('sha256', $archiveMarkdown), (string)$latestManifestEntry['content_sha256'], 'Package archive manifest hash matches archived markdown');
assert_same((string)$latestManifestEntry['content_sha256'], (string)($summary['latest_content_sha256'] ?? ''), 'Package summary exposes latest content hash');
assert_true(isset($latestManifestEntry['package_version']), 'Package archive manifest records a package version');
assert_same((int)($beforeSummary['latest_package_version'] ?? 0) + 1, (int)$latestManifestEntry['package_version'], 'Package version increments from the previous latest version');
assert_same((int)$latestManifestEntry['package_version'], (int)($summary['latest_package_version'] ?? 0), 'Package summary exposes latest package version');
assert_true(count($manifest) <= 60, 'Package archive manifest keeps a bounded recent detailed history');
assert_true(isset($latestManifestEntry['change_impact_inventory']) && is_array($latestManifestEntry['change_impact_inventory']), 'Package archive manifest records structured change impact inventory');
assert_same(count($latestManifestEntry['change_impact_inventory']), (int)($latestManifestEntry['change_impact_count'] ?? -1), 'Package archive manifest records change impact count');
assert_same((int)($latestManifestEntry['change_impact_count'] ?? -1), (int)($summary['latest_change_impact_count'] ?? -2), 'Package summary exposes latest change impact count');
$impactRows = QmsDocumentStructureService::latestSystemPackageChangeImpactRows();
assert_same((int)$summary['latest_change_impact_count'], count($impactRows), 'Latest package impact rows match summary count');
$impactRow = impact_row_for_note($impactRows, $impactNote);
assert_true(is_array($impactRow), 'Latest package impact rows include the smoke change');
assert_same('/planning/structures/view?id=' . (string)$impactBlock['structured_document_id'], (string)($impactRow['document_url'] ?? ''), 'Impact row links back to structured document detail');
assert_same('/planning/structures/blocks/edit?id=' . (string)$impactBlock['id'], (string)($impactRow['block_edit_url'] ?? ''), 'Impact row links back to block edit page');
assert_same('/planning/structures/links/review?block_id=' . (string)$impactBlock['id'], (string)($impactRow['trace_review_url'] ?? ''), 'Impact row links back to trace review page');
assert_true(in_array('数据控制和信息管理', $impactRow['trace_targets']['elements'] ?? [], true), 'Impact row exposes affected element targets');
assert_true(in_array('XZTC/BG-26-01 计算机软件登记表', $impactRow['trace_targets']['record_forms'] ?? [], true), 'Impact row exposes affected record-form targets');
assert_true(isset($latestManifestEntry['document_inventory']) && is_array($latestManifestEntry['document_inventory']), 'Package archive manifest records included document inventory');
$inventory = $latestManifestEntry['document_inventory'];
assert_same((int)$summary['total_documents'], count($inventory), 'Document inventory count matches total package documents');
$procedureInventory = inventory_row_for($inventory, 'procedure', 'XZTC/CX-26-2022');
assert_true(is_array($procedureInventory), 'Document inventory includes XZTC/CX-26-2022 procedure');
assert_contains('计算机文件及数据控制程序', (string)($procedureInventory['title'] ?? ''), 'Procedure inventory keeps document title');
assert_true((int)($procedureInventory['block_count'] ?? 0) > 0, 'Procedure inventory records structured block count');
assert_true((string)($procedureInventory['source_asset_id'] ?? '') !== '', 'Procedure inventory records source asset id');
assert_same('procedure', (string)($procedureInventory['source_kind'] ?? ''), 'Procedure inventory records source asset kind');
assert_true(str_contains((string)($procedureInventory['source_original_path'] ?? ''), '程序文件'), 'Procedure inventory records source original path');
assert_true((string)($procedureInventory['source_archive_status'] ?? '') === 'archived', 'Procedure inventory records archived source status');
assert_true((string)($procedureInventory['source_archived_path'] ?? '') !== '', 'Procedure inventory records archived source path');
assert_true((string)($procedureInventory['source_file_sha256'] ?? '') !== '', 'Procedure inventory records source file hash');
$procedureRenderedPath = (string)($procedureInventory['rendered_file_path'] ?? '');
assert_true($procedureRenderedPath !== '' && is_file(dirname(__DIR__) . '/' . $procedureRenderedPath), 'Procedure inventory points to rendered markdown');
assert_same(hash('sha256', file_get_contents(dirname(__DIR__) . '/' . $procedureRenderedPath) ?: ''), (string)($procedureInventory['content_sha256'] ?? ''), 'Procedure inventory hash matches rendered markdown');
$recordInventory = inventory_row_for($inventory, 'record_form', 'XZTC/BG-26-01');
assert_true(is_array($recordInventory), 'Document inventory includes the linked computer software register');
assert_contains('计算机软件登记表', (string)($recordInventory['title'] ?? ''), 'Record-form inventory keeps document title');
assert_same('record_form', (string)($recordInventory['source_kind'] ?? ''), 'Record-form inventory records source asset kind');
assert_true((string)($recordInventory['source_archived_path'] ?? '') !== '', 'Record-form inventory records archived source path');
$externalSource = Db::name('qms_sources')
    ->where('source_code', 'CNAS-CL01-A015:2018')
    ->where('soft_delete', 0)
    ->find();
assert_true(is_array($externalSource), 'CNAS A015 external source exists for package inventory freshness trace');
$externalInventory = inventory_row_for($inventory, 'external_basis', 'CNAS-CL01-A015:2018');
assert_true(is_array($externalInventory), 'Document inventory includes CNAS A015 external basis');
assert_same((string)$externalSource['freshness_checked_at'], (string)($externalInventory['freshness_checked_at'] ?? ''), 'External-basis inventory records freshness checked date');
assert_same((string)$externalSource['freshness_result'], (string)($externalInventory['freshness_result'] ?? ''), 'External-basis inventory records freshness result');
assert_same((string)$externalSource['freshness_evidence'], (string)($externalInventory['freshness_evidence'] ?? ''), 'External-basis inventory records freshness evidence');
assert_same((string)$externalSource['next_freshness_due'], (string)($externalInventory['next_freshness_due'] ?? ''), 'External-basis inventory records next freshness due date');
assert_same((string)$externalSource['freshness_status'], (string)($externalInventory['freshness_status'] ?? ''), 'External-basis inventory records freshness status');
assert_true(!inventory_has_doc_number($inventory, 'REF-2025-PROCEDURES'), 'Document inventory excludes draft reference procedure inputs');
assert_true(isset($latestManifestEntry['block_trace_inventory']) && is_array($latestManifestEntry['block_trace_inventory']), 'Package archive manifest records block-level trace inventory');
assert_same(count($latestManifestEntry['block_trace_inventory']), (int)($latestManifestEntry['block_trace_count'] ?? -1), 'Package archive manifest records block trace count');
assert_same((int)($latestManifestEntry['block_trace_count'] ?? -1), (int)($summary['latest_block_trace_count'] ?? -2), 'Package summary exposes latest block trace count');
foreach (array_slice($manifest, 0, -1) as $historyEntry) {
    assert_true(!array_key_exists('block_trace_inventory', (array)$historyEntry), 'Package manifest compacts historical block trace inventory');
    assert_true(!array_key_exists('document_inventory', (array)$historyEntry), 'Package manifest compacts historical document inventory');
}
$blockTraceRows = QmsDocumentStructureService::latestSystemPackageBlockTraceRows();
assert_same((int)$summary['latest_block_trace_count'], count($blockTraceRows), 'Latest package block trace rows match summary count');
$procedureBlockTrace = block_trace_row_for($blockTraceRows, 'XZTC/CX-26-2022', (string)$impactBlock['stable_key']);
assert_true(is_array($procedureBlockTrace), 'Block trace inventory includes the XZTC/CX-26-2022 record requirement block');
assert_same('/planning/structures/view?id=' . (string)$impactBlock['structured_document_id'], (string)($procedureBlockTrace['document_url'] ?? ''), 'Block trace row links back to structured document detail');
assert_same('/planning/structures/blocks/edit?id=' . (string)$impactBlock['id'], (string)($procedureBlockTrace['block_edit_url'] ?? ''), 'Block trace row links back to block edit page');
assert_same('/planning/structures/links/review?block_id=' . (string)$impactBlock['id'], (string)($procedureBlockTrace['trace_review_url'] ?? ''), 'Block trace row links back to trace review page');
assert_true(in_array('数据控制和信息管理', $procedureBlockTrace['trace_targets']['elements'] ?? [], true), 'Block trace row exposes linked element targets');
assert_true(in_array('XZTC/BG-26-01 计算机软件登记表', $procedureBlockTrace['trace_targets']['record_forms'] ?? [], true), 'Block trace row exposes linked record form targets');
assert_contains('# 实验室质量管理体系文件组合包', $markdown, 'Package markdown has a stable title');
assert_contains('# 实验室质量管理体系文件组合包', $archiveMarkdown, 'Package archive markdown has a stable title');
assert_contains('## 条款级追溯索引', $markdown, 'Package includes a clause-level traceability index');
assert_contains('## 内容块级追溯索引', $markdown, 'Package includes a block-level traceability index');
assert_contains('## 组合包变更影响清单', $markdown, 'Package includes a change impact inventory section');
assert_contains('| 无编号要素 | 主外部条款 | 手册章节 | 程序文件 | 记录表格 | 运行模块 | 岗位职责 | 缺口 |', $markdown, 'Traceability index has stable columns');
assert_contains('| 文件 | 内容块 | 块类型 | 要素 | 条款 | 手册章节 | 程序文件 | 记录表格 | 运行模块 | 岗位 | 复核状态 |', $markdown, 'Block traceability index has stable columns');
assert_contains('XZTC/CX-26-2022 计算机文件及数据控制程序', $markdown, 'Block traceability index keeps procedure document context');
assert_contains((string)$impactBlock['stable_key'], $markdown, 'Block traceability index keeps stable block keys for modular editing');
assert_contains('| 人员 | GB/T 27025-2019 6.2', $markdown, 'Traceability index keeps clause number outside the element name');
assert_contains('| 设备 | GB/T 27025-2019 6.4', $markdown, 'Traceability index includes equipment clause mapping');
assert_contains('| 数据控制和信息管理 | GB/T 27025-2019 7.11', $markdown, 'Traceability index includes data-control mapping');
assert_contains('## 质量手册', $markdown, 'Package groups quality manual documents');
assert_contains('## 程序文件', $markdown, 'Package groups procedure documents');
assert_contains('## 记录表格', $markdown, 'Package groups record form documents');
assert_contains('XZTC/CX-26-2022 计算机文件及数据控制程序', $markdown, 'Package includes the computer-data-control procedure');
assert_true(!str_contains($markdown, 'REF-2025-PROCEDURES'), 'Package excludes draft reference procedure inputs');
assert_contains('XZTC/BG-26-01 计算机软件登记表', $markdown, 'Package includes the linked computer software register');
assert_contains('关联程序：XZTC/CX-26-2022 计算机文件及数据控制程序', $markdown, 'Package keeps record-form procedure traceability text');
assert_contains('关联要素：数据控制和信息管理', $markdown, 'Package keeps record-form element traceability text');

$routeSource = file_get_contents(dirname(__DIR__) . '/route/app.php') ?: '';
assert_contains("planning/structures/package", $routeSource, 'Routes expose structure package page');
assert_contains("planning/structures/render-package", $routeSource, 'Routes expose package render action');

$controllerSource = file_get_contents(dirname(__DIR__) . '/app/controller/PlanningStructure.php') ?: '';
assert_contains('renderSystemPackage', $controllerSource, 'Structure controller invokes package renderer');
assert_contains('latestSystemPackageChangeImpactRows', $controllerSource, 'Structure controller loads latest package change impact rows');
assert_contains("'impactRows'", $controllerSource, 'Structure controller assigns impact rows to package view');

$indexView = file_get_contents(dirname(__DIR__) . '/app/view/planning_structure/index.html') ?: '';
assert_contains('体系文件组合包', $indexView, 'Structure index links to the combined package');

$packageView = file_get_contents(dirname(__DIR__) . '/app/view/planning_structure/package.html') ?: '';
assert_contains('外部依据、质量手册、程序文件、作业指导书和记录表格', $packageView, 'Package page describes the full structured document scope');
assert_contains('条款级追溯索引', $packageView, 'Package page exposes the traceability index included in the package');
assert_contains('体系要素', $packageView, 'Package page names elements in the traceability index description');
assert_contains('document_count_by_role.external_basis', $packageView, 'Package page shows external basis count');
assert_contains('document_count_by_role.work_instruction', $packageView, 'Package page shows work instruction count');
assert_contains('内容哈希', $packageView, 'Package page shows the content hash label');
assert_contains('latest_content_sha256', $packageView, 'Package page renders latest content hash from summary');
assert_contains('latest_package_version', $packageView, 'Package page renders latest package version from summary');
assert_contains('latest_change_impact_count', $packageView, 'Package page renders latest change impact count from summary');
assert_contains('latest_block_trace_count', $packageView, 'Package page renders latest block trace count from summary');
assert_contains('内容块级追溯索引', $packageView, 'Package page exposes block-level traceability index');
assert_contains('blockTraceRows', $packageView, 'Package page renders latest block trace rows');
assert_contains('变更影响明细', $packageView, 'Package page shows detailed change impact section');
assert_contains('受影响追溯', $packageView, 'Package page labels affected traceability targets');
assert_contains('<details', $packageView, 'Package page can expand change impact traceability details');
assert_contains('impactRows', $packageView, 'Package page renders latest impact rows');
assert_contains('trace_targets', $packageView, 'Package page renders decoded traceability targets');
assert_contains('block_edit_url', $packageView, 'Package page links block trace rows to block editing');
assert_contains('document_url', $packageView, 'Package page links impact rows to structured document detail');
assert_contains('block_edit_url', $packageView, 'Package page links impact rows to block editing');
assert_contains('trace_review_url', $packageView, 'Package page links impact rows to trace review');
assert_contains('查看结构化文件', $packageView, 'Package page exposes structured document jump action');
assert_contains('编辑内容块', $packageView, 'Package page exposes block edit jump action');
assert_contains('复核追溯', $packageView, 'Package page exposes trace review jump action');
assert_contains('记录表格', $packageView, 'Package page can show impacted record forms');
assert_contains('存档次数', $packageView, 'Package page shows archive count');
assert_contains('最新存档', $packageView, 'Package page shows latest archive path');

echo "qms_structure_package_smoke passed\n";
