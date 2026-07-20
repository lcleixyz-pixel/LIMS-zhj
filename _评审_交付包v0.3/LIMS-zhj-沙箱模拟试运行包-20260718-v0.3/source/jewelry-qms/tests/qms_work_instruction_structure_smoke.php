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

function cleanup_work_instruction_smoke(string $documentId, string $docNumber, bool $refreshPackage = false): void
{
    $structuredIds = Db::name('qms_structured_documents')->where('doc_number', $docNumber)->column('id');
    if ($structuredIds !== []) {
        $blockIds = Db::name('qms_document_blocks')->whereIn('structured_document_id', $structuredIds)->column('id');
        if ($blockIds !== []) {
            Db::name('qms_document_block_links')->whereIn('block_id', $blockIds)->delete();
            Db::name('qms_document_blocks')->whereIn('id', $blockIds)->delete();
        }
        Db::name('qms_structured_documents')->whereIn('id', $structuredIds)->delete();
    }
    Db::name('qms_document_change_logs')->where('document_id', $documentId)->delete();
    Db::name('qms_document_assets')->where('document_id', $documentId)->delete();
    Db::name('qms_element_documents')->where('document_id', $documentId)->delete();
    Db::name('documents')->where('id', $documentId)->delete();
    if ($refreshPackage) {
        QmsDocumentStructureService::renderSystemPackage();
    }
}

$documentId = 'smoke-work-instruction-doc';
$docNumber = 'WI-SMOKE-01';
$sourcePath = '现用文件/程序文件/程序文件2022/04-2022仪器设备和标准物质期间核查程序.docx';
$now = date('Y-m-d H:i:s');

try {
    cleanup_work_instruction_smoke($documentId, $docNumber);

    Db::name('documents')->insert([
        'id' => $documentId,
        'company_id' => (string)Config::get('qms.company_id'),
        'category_id' => '00000000-0000-0000-0000-000000000052',
        'template_id' => null,
        'level' => 3,
        'doc_number' => $docNumber,
        'title' => '折射仪作业指导书',
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

    $layers = array_column(QmsDocumentStructureService::structureLayerDefinitions(), 'name');
    assert_true(in_array('作业指导书', $layers, true), 'Structure layers include work instructions');

    QmsDocumentStructureService::seedAll();

    $structured = Db::name('qms_structured_documents')
        ->where('doc_number', $docNumber)
        ->where('version', 'A/0')
        ->where('soft_delete', 0)
        ->find();
    assert_true(is_array($structured), 'Work instruction document is structured');
    assert_true((string)$structured['document_role'] === 'work_instruction', 'Work instruction keeps its own structured role');
    assert_true((string)$structured['document_id'] === $documentId, 'Work instruction structured document links to the source document');
    assert_true((string)$structured['render_status'] === 'rendered', 'Work instruction markdown is rendered');
    assert_true(is_file(dirname(__DIR__) . '/' . (string)$structured['rendered_file_path']), 'Rendered work instruction markdown file exists');

    $asset = Db::name('qms_document_assets')
        ->where('document_id', $documentId)
        ->where('soft_delete', 0)
        ->find();
    assert_true(is_array($asset), 'Work instruction source file is archived as an asset');
    assert_true((string)$asset['source_kind'] === 'work_instruction', 'Work instruction asset uses work_instruction source kind');

    $blocks = Db::name('qms_document_blocks')
        ->where('structured_document_id', (string)$structured['id'])
        ->where('soft_delete', 0)
        ->order('sort_order', 'asc')
        ->select()
        ->toArray();
    assert_true(count($blocks) >= 1, 'Work instruction has modular markdown blocks');
    assert_contains('作业指导书', (string)$blocks[0]['markdown'], 'Work instruction overview block names the document type');

    $package = QmsDocumentStructureService::renderSystemPackage();
    $packageMarkdown = file_get_contents(dirname(__DIR__) . '/' . $package['output_path']) ?: '';
    assert_contains('## 作业指导书', $packageMarkdown, 'System package groups work instructions');
    assert_contains($docNumber . ' 折射仪作业指导书', $packageMarkdown, 'System package includes the work instruction document');

    $indexView = file_get_contents(dirname(__DIR__) . '/app/view/planning_structure/index.html') ?: '';
    assert_contains('作业指导书', $indexView, 'Structure index labels work instructions');
} finally {
    cleanup_work_instruction_smoke($documentId, $docNumber, true);
}

echo "qms_work_instruction_structure_smoke passed\n";
