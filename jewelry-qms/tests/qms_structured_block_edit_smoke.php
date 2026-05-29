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

assert_true(
    method_exists(QmsDocumentStructureService::class, 'updateBlockMarkdown'),
    'Structure service exposes block-level markdown editing'
);

QmsDocumentStructureService::seedAll();

$block = Db::name('qms_document_blocks')
    ->alias('b')
    ->join('qms_structured_documents sd', 'sd.id = b.structured_document_id')
    ->where('sd.doc_number', 'QP-26')
    ->where('b.block_type', 'record_requirement')
    ->where('b.soft_delete', 0)
    ->field('b.id,b.markdown,b.stable_key,b.source_locator,sd.id structured_document_id,sd.rendered_file_path')
    ->find();

assert_true((bool)$block, 'QP-26 has a record requirement block to edit');

$blockId = (string)$block['id'];
$originalMarkdown = (string)$block['markdown'];
$marker = '<!-- structured-block-edit-smoke-' . date('YmdHis') . ' -->';
$editedMarkdown = rtrim($originalMarkdown) . "\n\n" . $marker . "\n";
$beforeArchiveCount = (int)(QmsDocumentStructureService::structuredDocumentDetail((string)$block['structured_document_id'])['render_archive']['archive_count'] ?? 0);

$result = QmsDocumentStructureService::updateBlockMarkdown($blockId, $editedMarkdown, '结构化内容块编辑 smoke');
try {
    assert_true(($result['block']['id'] ?? '') === $blockId, 'Edit result returns the edited block');
    assert_true(($result['structured_document']['id'] ?? '') === (string)$block['structured_document_id'], 'Edit result returns the parent structured document');
    assert_true(($result['render_archive']['archive_count'] ?? 0) >= $beforeArchiveCount + 1, 'Editing a block creates a new render archive entry');

    $reloaded = Db::name('qms_document_blocks')->where('id', $blockId)->find();
    assert_contains($marker, (string)$reloaded['markdown'], 'Edited markdown is stored on the block');
    assert_true((string)$reloaded['stable_key'] === (string)$block['stable_key'], 'Editing keeps the stable block key');
    assert_true((string)$reloaded['source_locator'] === (string)$block['source_locator'], 'Editing keeps the source locator');

    $linkCount = Db::name('qms_document_block_links')
        ->where('block_id', $blockId)
        ->where('soft_delete', 0)
        ->count();
    assert_true($linkCount > 0, 'Editing markdown keeps existing block traceability links');

    $renderedPath = (string)Db::name('qms_structured_documents')
        ->where('id', (string)$block['structured_document_id'])
        ->value('rendered_file_path');
    $renderedMarkdown = file_get_contents(dirname(__DIR__) . '/' . $renderedPath) ?: '';
    assert_contains($marker, $renderedMarkdown, 'Edited markdown is rendered into the structured document output');
} finally {
    QmsDocumentStructureService::updateBlockMarkdown($blockId, $originalMarkdown, '恢复结构化内容块编辑 smoke');
    Db::name('qms_document_change_logs')
        ->where('structured_document_id', (string)$block['structured_document_id'])
        ->where('block_id', $blockId)
        ->whereIn('revision_note', ['结构化内容块编辑 smoke', '恢复结构化内容块编辑 smoke'])
        ->update(['soft_delete' => 1]);
}

$routeSource = file_get_contents(dirname(__DIR__) . '/route/app.php') ?: '';
assert_contains('planning/structures/blocks/edit', $routeSource, 'Routes expose the block edit form');
assert_contains('planning/structures/blocks/update', $routeSource, 'Routes expose the block update action');

$controllerSource = file_get_contents(dirname(__DIR__) . '/app/controller/PlanningStructure.php') ?: '';
assert_contains('editBlock', $controllerSource, 'Structure controller exposes block editing');
assert_contains('updateBlock', $controllerSource, 'Structure controller exposes block update');

$viewSource = file_get_contents(dirname(__DIR__) . '/app/view/planning_structure/view.html') ?: '';
assert_contains('编辑内容块', $viewSource, 'Structure detail links to block editing');

echo "qms_structured_block_edit_smoke passed\n";
