<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../app/common.php';

$app = new think\App();
$app->initialize();

use app\service\QmsDocumentStructureService;
use think\facade\Config;
use think\facade\Db;

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

function cleanup_controlled_document_coverage_smoke(string $documentId, string $docNumber): void
{
    $structuredIds = Db::name('qms_structured_documents')->where('document_id', $documentId)->column('id');
    if ($structuredIds !== []) {
        $blockIds = Db::name('qms_document_blocks')->whereIn('structured_document_id', $structuredIds)->column('id');
        if ($blockIds !== []) {
            Db::name('qms_document_block_links')->whereIn('block_id', $blockIds)->delete();
            Db::name('qms_document_blocks')->whereIn('id', $blockIds)->delete();
        }
        Db::name('qms_structured_documents')->whereIn('id', $structuredIds)->delete();
    }
    Db::name('qms_document_assets')->where('document_id', $documentId)->delete();
    Db::name('qms_element_documents')->where('document_id', $documentId)->delete();
    Db::name('documents')->where('id', $documentId)->delete();
    Db::name('qms_document_change_logs')->where('document_id', $documentId)->delete();
    Db::name('qms_structured_documents')->where('doc_number', $docNumber)->delete();
}

assert_true(
    method_exists(QmsDocumentStructureService::class, 'controlledDocumentStructureCoverage'),
    'Structure service exposes controlled-document structure coverage'
);

QmsDocumentStructureService::seedAll();

$documentId = 'smoke-structure-coverage-doc';
$docNumber = 'WI-COVERAGE-SMOKE';
$sourcePath = '现用文件/程序文件/程序文件2022/04-2022仪器设备和标准物质期间核查程序.docx';
$now = date('Y-m-d H:i:s');

try {
    cleanup_controlled_document_coverage_smoke($documentId, $docNumber);
    Db::name('documents')->insert([
        'id' => $documentId,
        'company_id' => (string)Config::get('qms.company_id'),
        'category_id' => '00000000-0000-0000-0000-000000000052',
        'template_id' => null,
        'level' => 3,
        'doc_number' => $docNumber,
        'title' => '覆盖缺口作业指导书',
        'version' => 'A/0',
        'revision' => 0,
        'department_id' => null,
        'effective_date' => null,
        'review_date' => null,
        'status' => 'published',
        'file_path' => $sourcePath,
        'file_name' => basename($sourcePath),
        'file_type' => 'docx',
        'publish' => 1,
        'soft_delete' => 0,
        'record_status' => 1,
        'created' => $now,
        'modified' => $now,
    ]);

    $coverage = QmsDocumentStructureService::controlledDocumentStructureCoverage();
    assert_true((int)($coverage['total_documents'] ?? 0) >= 1, 'Coverage reports total controlled documents');
    assert_true((int)($coverage['missing_documents'] ?? 0) >= 1, 'Coverage reports missing structure count');
    assert_true(isset($coverage['by_level'][3]), 'Coverage groups work instructions by level');
    assert_true((int)($coverage['by_level'][3]['missing_documents'] ?? 0) >= 1, 'Coverage counts missing work instructions');

    $missing = null;
    foreach ($coverage['missing_rows'] ?? [] as $row) {
        if ((string)($row['doc_number'] ?? '') === $docNumber) {
            $missing = $row;
            break;
        }
    }
    assert_true(is_array($missing), 'Coverage missing rows include the unstructured controlled document');
    assert_true((string)$missing['level_label'] === '作业指导书', 'Coverage missing row labels work instruction level');
    assert_true((string)$missing['source_file_status'] === 'available', 'Coverage missing row records source file availability');
    assert_true((string)$missing['seed_url'] === '/planning/structures/seed', 'Coverage missing row points to structure generation action');

    $controllerSource = file_get_contents(dirname(__DIR__) . '/app/controller/PlanningStructure.php') ?: '';
    assert_contains('controlledDocumentStructureCoverage', $controllerSource, 'Structure controller assigns controlled-document coverage');
    assert_contains("'coverage'", $controllerSource, 'Structure controller exposes coverage to index view');

    $viewSource = file_get_contents(dirname(__DIR__) . '/app/view/planning_structure/index.html') ?: '';
    assert_contains('受控文件结构覆盖', $viewSource, 'Structure index shows controlled-document coverage');
    assert_contains('缺口文件', $viewSource, 'Structure index labels missing controlled documents');
    assert_contains('生成结构化骨架', $viewSource, 'Structure index keeps the remediation action visible');
} finally {
    cleanup_controlled_document_coverage_smoke($documentId, $docNumber);
}

echo "qms_controlled_document_structure_coverage_smoke passed\n";
