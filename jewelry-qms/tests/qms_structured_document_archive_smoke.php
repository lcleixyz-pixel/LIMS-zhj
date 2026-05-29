<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../app/common.php';

$app = new think\App();
$app->initialize();

use app\service\QmsDocumentStructureService;
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

QmsDocumentStructureService::seedAll();

$structured = Db::name('qms_structured_documents')
    ->where('document_role', 'procedure')
    ->where('doc_number', 'QP-26')
    ->where('soft_delete', 0)
    ->find();
assert_true((bool)$structured, 'QP-26 structured document exists');

$detail = QmsDocumentStructureService::structuredDocumentDetail((string)$structured['id']);
assert_true(isset($detail['render_archive']), 'Structured document detail includes render archive summary');

$archive = $detail['render_archive'];
assert_true(isset($archive['manifest_path']), 'Render archive summary includes manifest path');
assert_true(isset($archive['latest_archive_path']), 'Render archive summary includes latest archive path');
assert_true((int)($archive['archive_count'] ?? 0) >= 1, 'Render archive summary has at least one archive entry');
assert_true(is_file(dirname(__DIR__) . '/' . $archive['manifest_path']), 'Structured document render manifest exists');
assert_true(is_file(dirname(__DIR__) . '/' . $archive['latest_archive_path']), 'Structured document latest render archive exists');

$manifest = json_decode(file_get_contents(dirname(__DIR__) . '/' . $archive['manifest_path']) ?: '[]', true);
assert_true(is_array($manifest) && $manifest !== [], 'Structured document render manifest is valid JSON');
$latest = end($manifest);
assert_true(isset($latest['content_sha256']), 'Structured document render manifest records content hash');
assert_true(isset($latest['rendered_file_path']), 'Structured document render manifest records current rendered file path');
assert_true(isset($latest['block_count']), 'Structured document render manifest records block count');

$currentMarkdown = file_get_contents(dirname(__DIR__) . '/' . $latest['rendered_file_path']) ?: '';
$archiveMarkdown = file_get_contents(dirname(__DIR__) . '/' . $latest['archive_path']) ?: '';
assert_contains('# QP-26 计算机文件及数据控制程序', $currentMarkdown, 'Current rendered procedure markdown has QP-26 title');
assert_contains('# QP-26 计算机文件及数据控制程序', $archiveMarkdown, 'Archived rendered procedure markdown has QP-26 title');
assert_true(hash('sha256', $currentMarkdown) === (string)$latest['content_sha256'], 'Render manifest hash matches current rendered markdown');

$viewSource = file_get_contents(dirname(__DIR__) . '/app/view/planning_structure/view.html') ?: '';
assert_contains('渲染存档', $viewSource, 'Structured document detail page shows render archive section');
assert_contains('最新存档', $viewSource, 'Structured document detail page shows latest render archive path');

echo "qms_structured_document_archive_smoke passed\n";
