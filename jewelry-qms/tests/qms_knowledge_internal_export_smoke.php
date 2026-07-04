<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../app/common.php';

$app = new think\App();
$app->initialize();

use app\service\QmsDocumentStructureService;

function assert_export_true(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

function assert_export_contains(string $needle, string $haystack, string $message): void
{
    if (!str_contains($haystack, $needle)) {
        fwrite(STDERR, $message . PHP_EOL);
        fwrite(STDERR, 'Missing: ' . $needle . PHP_EOL);
        exit(1);
    }
}

$appRoot = dirname(__DIR__);
$workspaceRoot = dirname($appRoot);
if ($workspaceRoot === DIRECTORY_SEPARATOR) {
    $workspaceRoot = '';
}

QmsDocumentStructureService::seedAll();
$summary = QmsDocumentStructureService::exportKnowledgeInternal();

assert_export_true((int)($summary['manual']['exported'] ?? 0) >= 1, 'Knowledge export includes the quality manual');
assert_export_true((int)($summary['procedures']['exported'] ?? 0) >= 37, 'Knowledge export includes current procedure structures');
assert_export_true((int)($summary['enumeration']['total_files'] ?? 0) === 42, 'Export report reads the 2022 procedure directory total');
assert_export_true((int)($summary['enumeration']['numbered_files'] ?? 0) === 38, 'Export report reads the 2022 numbered-file baseline');

$reportPath = $workspaceRoot . '/' . (string)$summary['reports']['markdown'];
$jsonPath = $workspaceRoot . '/' . (string)$summary['reports']['json'];
$indexPath = $workspaceRoot . '/' . (string)$summary['reports']['index'];
assert_export_true(is_file($reportPath), 'Conversion report markdown is exported');
assert_export_true(is_file($jsonPath), 'Conversion report JSON is exported');
assert_export_true(is_file($indexPath), 'Internal export index is exported');

$report = (string)file_get_contents($reportPath);
assert_export_contains('2022 程序目录文件总数：42', $report, 'Report records source file total');
assert_export_contains('2022 程序目录编号文件：38', $report, 'Report records numbered-file baseline');
assert_export_contains('结构化导出程序文件：37', $report, 'Report records exported procedure count');
assert_export_contains('XZTC/CX-05-02-2022', $report, 'Report keeps the numbered non-procedure file visible');
assert_export_contains('编号文件不是程序标题，未作为程序文件结构化导出', $report, 'Report explains numbered attachment boundary');
assert_export_true(!str_contains($report, '源文件文本抽取为空'), 'Legacy .doc files are extracted by the fallback reader');
assert_export_contains('不能手工直改', $report, 'Report keeps the one-way export boundary');

$procedureRow = null;
foreach ((array)$summary['documents'] as $row) {
    if ((string)($row['doc_number'] ?? '') === 'XZTC/CX-26-2022') {
        $procedureRow = $row;
        break;
    }
}
assert_export_true(is_array($procedureRow), 'Export summary includes XZTC/CX-26-2022');
$procedurePath = $workspaceRoot . '/' . (string)$procedureRow['export_path'];
assert_export_true(is_file($procedurePath), 'Procedure card is exported');
$procedureCard = (string)file_get_contents($procedurePath);
assert_export_contains('doc_number: "XZTC/CX-26-2022"', $procedureCard, 'Procedure card keeps controlled number');
assert_export_contains('type: "internal_procedure"', $procedureCard, 'Procedure card uses internal procedure type');
assert_export_contains('generated_from: "qms_structured_documents"', $procedureCard, 'Procedure card records structured DB source');
assert_export_contains('manual_edit: false', $procedureCard, 'Procedure card marks no manual edit');
assert_export_contains('source_path: "现用文件/程序文件/程序文件2022/26-2022计算机文件及数据控制程序.docx"', $procedureCard, 'Procedure card keeps source path');

$legacyDocRow = null;
foreach ((array)$summary['documents'] as $row) {
    if ((string)($row['doc_number'] ?? '') === 'XZTC/CX-07-02-2022') {
        $legacyDocRow = $row;
        break;
    }
}
assert_export_true(is_array($legacyDocRow), 'Export summary includes legacy .doc procedure');
$legacyDocCard = (string)file_get_contents($workspaceRoot . '/' . (string)$legacyDocRow['export_path']);
assert_export_contains('防止商业贿赂的措施', $legacyDocCard, 'Legacy .doc procedure text is exported');

$manualRow = null;
foreach ((array)$summary['documents'] as $row) {
    if ((string)($row['document_role'] ?? '') === 'quality_manual') {
        $manualRow = $row;
        break;
    }
}
assert_export_true(is_array($manualRow), 'Export summary includes quality manual');
$manualPath = $workspaceRoot . '/' . (string)$manualRow['export_path'];
assert_export_true(is_file($manualPath), 'Manual card is exported');
$manualCard = (string)file_get_contents($manualPath);
assert_export_contains('type: "internal_manual"', $manualCard, 'Manual card uses internal manual type');

$firstReport = (string)file_get_contents($reportPath);
$firstProcedureCard = (string)file_get_contents($procedurePath);
$second = QmsDocumentStructureService::exportKnowledgeInternal();
assert_export_true($firstReport === (string)file_get_contents($workspaceRoot . '/' . (string)$second['reports']['markdown']), 'Conversion report export is idempotent');
assert_export_true($firstProcedureCard === (string)file_get_contents($procedurePath), 'Procedure card export is idempotent');

echo "qms_knowledge_internal_export_smoke passed\n";
