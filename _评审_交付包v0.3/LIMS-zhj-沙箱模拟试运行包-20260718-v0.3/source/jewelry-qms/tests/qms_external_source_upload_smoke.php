<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../app/common.php';

$app = new think\App();
$app->initialize();

use app\model\QmsSource;
use app\service\QmsElementService;
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

$testSourceCode = 'CNAS-CL01-A999:2099';
$cleanup = static function () use ($testSourceCode): void {
    $sourceIds = Db::table('qms_sources')->where('source_code', $testSourceCode)->column('id');
    $structuredIds = Db::table('qms_structured_documents')->where('doc_number', $testSourceCode)->column('id');
    if ($structuredIds !== []) {
        $blockIds = Db::table('qms_document_blocks')->whereIn('structured_document_id', $structuredIds)->column('id');
        if ($blockIds !== []) {
            Db::table('qms_document_block_links')->whereIn('block_id', $blockIds)->delete();
            Db::table('qms_document_blocks')->whereIn('id', $blockIds)->delete();
        }
        Db::table('qms_document_change_logs')->whereIn('structured_document_id', $structuredIds)->delete();
        Db::table('qms_structured_documents')->whereIn('id', $structuredIds)->delete();
    }
    Db::table('qms_document_assets')->where('source_kind', 'external_basis')->where('original_path', 'like', '%CNAS-CL01-A999%')->delete();
    if ($sourceIds === []) {
        return;
    }
    $clauseIds = Db::table('qms_clauses')->whereIn('source_id', $sourceIds)->column('id');
    if ($clauseIds !== []) {
        Db::table('qms_clause_texts')->whereIn('clause_id', $clauseIds)->delete();
        Db::table('qms_element_clause_links')->whereIn('clause_id', $clauseIds)->delete();
        Db::table('qms_clauses')->whereIn('id', $clauseIds)->delete();
    }
    Db::table('qms_agent_suggestions')->whereLike('title', '%' . $testSourceCode . '%')->delete();
    Db::table('qms_sources')->whereIn('id', $sourceIds)->delete();
};
$cleanup();
register_shutdown_function($cleanup);

assert_true(
    method_exists(QmsElementService::class, 'registerExternalSourceFile'),
    'Element service can register an uploaded external source file'
);

$fixtureSource = dirname(__DIR__, 2)
    . '/参考/2025年最新版CMA和CNAS质量体系/07-CNAS-CL01-A015：2018 检测和校准实验室能力认可准则在珠宝玉石、贵金属检测领域的应用说明.pdf';
$fixtureDir = dirname(__DIR__) . '/runtime/qms_upload_fixture';
if (!is_dir($fixtureDir)) {
    mkdir($fixtureDir, 0775, true);
}
$fixturePath = $fixtureDir . '/CNAS-CL01-A999：2099 测试领域应用说明.pdf';
copy($fixtureSource, $fixturePath);

$result = QmsElementService::registerExternalSourceFile($fixturePath, 'CNAS-CL01-A999：2099 测试领域应用说明.pdf');
assert_true(isset($result['source']) && $result['source'] instanceof QmsSource, 'Register returns a QMS source model');
assert_true(isset($result['clauses']) && (int)$result['clauses'] > 0, 'Register extracts clauses into the formal clause library');
assert_true(isset($result['archive_path']), 'Register returns normalized archive path');
assert_true(isset($result['structured_document_id']) && (string)$result['structured_document_id'] !== '', 'Register immediately creates a structured markdown document');
assert_true(isset($result['structured_rendered_path']) && is_file(dirname(__DIR__) . '/' . (string)$result['structured_rendered_path']), 'Register renders uploaded source markdown output');

$source = $result['source'];
assert_true((string)$source->source_code === 'CNAS-CL01-A999:2099', 'Uploaded source filename is parsed into normalized source code');
assert_true((string)$source->attachment_file_path === (string)$result['archive_path'], 'Source stores normalized archive path');
assert_true(is_file(dirname(__DIR__) . '/' . $result['archive_path']), 'Uploaded source is copied into fixed archive storage');
assert_true((string)$source->freshness_status === 'current', 'Uploaded source freshness status is current after archive');
assert_true((string)$source->freshness_evidence !== '', 'Uploaded source keeps freshness evidence');

$rows = QmsElementService::externalSourceProcessingRows();
$uploadedRow = null;
foreach ($rows as $row) {
    if ((string)$row['source']->source_code === 'CNAS-CL01-A999:2099') {
        $uploadedRow = $row;
        break;
    }
}
assert_true(is_array($uploadedRow), 'Uploaded source appears in processing rows');
assert_true((string)$uploadedRow['archive_status'] === 'archived', 'Uploaded source processing row is archived');
assert_true((string)$uploadedRow['extraction_status'] === 'extracted', 'Uploaded source processing row is extracted');
assert_true((string)$uploadedRow['structure_status'] === 'rendered', 'Uploaded source processing row shows rendered markdown structure');
assert_true((string)$uploadedRow['structured_document_id'] === (string)$result['structured_document_id'], 'Uploaded source row links to its structured document');

$routeSource = file_get_contents(dirname(__DIR__) . '/route/app.php') ?: '';
assert_contains("planning/sources/upload", $routeSource, 'Routes expose external source upload action');

$controllerSource = file_get_contents(dirname(__DIR__) . '/app/controller/PlanningSource.php') ?: '';
assert_contains('registerExternalSourceFile', $controllerSource, 'Source controller invokes uploaded source registration');

$viewSource = file_get_contents(dirname(__DIR__) . '/app/view/planning_source/index.html') ?: '';
assert_contains('上传依据文件', $viewSource, 'Source page has uploaded source entry');
assert_contains('选择 PDF 或 Word 文件', $viewSource, 'Source page explains upload file format');
assert_contains('Markdown结构', $viewSource, 'Source page shows uploaded source structure status');

echo "qms_external_source_upload_smoke passed\n";
