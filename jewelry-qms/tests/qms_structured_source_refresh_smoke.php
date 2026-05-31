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

function assert_not_contains(string $needle, string $haystack, string $message): void
{
    if (str_contains($haystack, $needle)) {
        fwrite(STDERR, $message . PHP_EOL);
        fwrite(STDERR, 'Unexpected: ' . $needle . PHP_EOL);
        exit(1);
    }
}

function assert_throws(callable $callback, string $needle, string $message): void
{
    try {
        $callback();
    } catch (Throwable $exception) {
        assert_contains($needle, $exception->getMessage(), $message);
        return;
    }

    fwrite(STDERR, $message . PHP_EOL);
    fwrite(STDERR, 'Expected exception containing: ' . $needle . PHP_EOL);
    exit(1);
}

assert_true(
    method_exists(QmsDocumentStructureService::class, 'refreshStructuredDocumentFromSource'),
    'Structure service exposes source-file refresh'
);

QmsDocumentStructureService::seedAll();

$block = Db::name('qms_document_blocks')
    ->alias('b')
    ->join('qms_structured_documents sd', 'sd.id = b.structured_document_id')
    ->where('sd.doc_number', 'XZTC/CX-26-2022')
    ->where('sd.document_role', 'procedure')
    ->where('b.block_type', 'record_requirement')
    ->where('b.stable_key', 'like', 'procedure:xztc_cx_26_2022:%')
    ->where('b.source_locator', '<>', '')
    ->where('b.soft_delete', 0)
    ->field('b.id,b.markdown,b.stable_key,sd.id structured_document_id,sd.status structured_status,sd.review_note')
    ->find();
assert_true(is_array($block), 'XZTC/CX-26-2022 has a record requirement block for source refresh');

$blockId = (string)$block['id'];
$structuredId = (string)$block['structured_document_id'];
$originalStatus = (string)$block['structured_status'];
$originalReviewNote = (string)$block['review_note'];
$marker = '<!-- source-refresh-smoke-' . date('YmdHis') . ' -->';
$manualNote = '源文件重建 smoke：人工复核岗位映射必须保留';
$refreshNote = '源文件重建 smoke：按现用受控程序文件重新抽取结构块';
$restoreNote = '恢复源文件重建 smoke 状态';

$positionId = (string)Db::name('qms_positions')->where('code', 'document_controller')->where('soft_delete', 0)->value('id');
assert_true($positionId !== '', 'Document controller position exists');

Db::name('qms_document_block_links')
    ->where('block_id', $blockId)
    ->where('note', $manualNote)
    ->delete();
Db::name('qms_document_change_logs')
    ->where('structured_document_id', $structuredId)
    ->whereIn('revision_note', [$manualNote, $refreshNote, $restoreNote, '源文件重建 smoke：先写入偏离源文件的测试内容'])
    ->delete();

$manualLink = QmsDocumentStructureService::upsertBlockTraceLink($blockId, [
    'relation_type' => 'responsible',
    'position_id' => $positionId,
    'confidence' => 'review_required',
    'note' => $manualNote,
]);
$manualLinkId = (string)($manualLink['link']['id'] ?? '');
assert_true($manualLinkId !== '', 'Manual trace link is saved before source refresh');

QmsDocumentStructureService::updateBlockMarkdown(
    $blockId,
    rtrim((string)$block['markdown']) . "\n\n" . $marker . "\n",
    '源文件重建 smoke：先写入偏离源文件的测试内容'
);

try {
    assert_throws(
        fn () => QmsDocumentStructureService::refreshStructuredDocumentFromSource($structuredId, ''),
        '重建说明不能为空',
        'Source refresh requires a review note'
    );

    $result = QmsDocumentStructureService::refreshStructuredDocumentFromSource($structuredId, $refreshNote);
    assert_true((string)($result['structured_document']['id'] ?? '') === $structuredId, 'Refresh returns the structured document');
    assert_true((string)($result['structured_document']['status'] ?? '') === 'draft', 'Source refresh leaves the document in draft review');
    assert_true((int)($result['blocks'] ?? 0) > 0, 'Source refresh rebuilds markdown blocks');
    assert_true((int)($result['links'] ?? 0) > 0, 'Source refresh rebuilds trace links');

    $refreshedBlock = Db::name('qms_document_blocks')->where('id', $blockId)->find();
    assert_true(is_array($refreshedBlock), 'Original stable block still exists after refresh');
    assert_true((string)$refreshedBlock['stable_key'] === (string)$block['stable_key'], 'Source refresh keeps stable block key');
    assert_not_contains($marker, (string)$refreshedBlock['markdown'], 'Source refresh replaces manually diverged markdown with source text');
    assert_contains('计算机软件登记表', (string)$refreshedBlock['markdown'], 'Source refresh keeps source record requirement content');

    $manualLinkAfter = Db::name('qms_document_block_links')
        ->where('block_id', $blockId)
        ->where('position_id', $positionId)
        ->where('note', $manualNote)
        ->where('soft_delete', 0)
        ->find();
    assert_true(is_array($manualLinkAfter), 'Source refresh preserves manually reviewed trace links on stable blocks');

    $log = Db::name('qms_document_change_logs')
        ->where('structured_document_id', $structuredId)
        ->where('change_type', 'version_update')
        ->where('revision_note', $refreshNote)
        ->where('soft_delete', 0)
        ->find();
    assert_true(is_array($log), 'Source refresh writes a version update log');
    assert_true((string)$log['archive_path'] !== '', 'Source refresh log points to a render archive');
    assert_true(is_file(dirname(__DIR__) . '/' . (string)$log['archive_path']), 'Source refresh archive file exists');

    $renderedPath = (string)Db::name('qms_structured_documents')->where('id', $structuredId)->value('rendered_file_path');
    $renderedMarkdown = file_get_contents(dirname(__DIR__) . '/' . $renderedPath) ?: '';
    assert_not_contains($marker, $renderedMarkdown, 'Rendered output follows refreshed source markdown');
} finally {
    if ($manualLinkId !== '') {
        Db::name('qms_document_block_links')->where('id', $manualLinkId)->delete();
    }
    Db::name('qms_structured_documents')->where('id', $structuredId)->update([
        'status' => $originalStatus,
        'review_note' => $originalReviewNote,
    ]);
    Db::name('qms_document_change_logs')
        ->where('structured_document_id', $structuredId)
        ->whereIn('revision_note', [$manualNote, $refreshNote, $restoreNote, '源文件重建 smoke：先写入偏离源文件的测试内容'])
        ->delete();
    QmsDocumentStructureService::refreshStructuredDocumentFromSource($structuredId, $restoreNote);
    Db::name('qms_structured_documents')->where('id', $structuredId)->update([
        'status' => $originalStatus,
        'review_note' => $originalReviewNote,
    ]);
    Db::name('qms_document_change_logs')
        ->where('structured_document_id', $structuredId)
        ->where('revision_note', $restoreNote)
        ->delete();
}

$routeSource = file_get_contents(dirname(__DIR__) . '/route/app.php') ?: '';
assert_contains('planning/structures/refresh-source', $routeSource, 'Routes expose source refresh action');

$controllerSource = file_get_contents(dirname(__DIR__) . '/app/controller/PlanningStructure.php') ?: '';
assert_contains('refreshSource', $controllerSource, 'Structure controller exposes source refresh action');

$detailView = file_get_contents(dirname(__DIR__) . '/app/view/planning_structure/view.html') ?: '';
assert_contains('从源文件重建结构', $detailView, 'Structure detail page exposes source refresh action');
assert_contains('重建说明', $detailView, 'Structure detail page requires a source refresh note');

echo "qms_structured_source_refresh_smoke passed\n";
