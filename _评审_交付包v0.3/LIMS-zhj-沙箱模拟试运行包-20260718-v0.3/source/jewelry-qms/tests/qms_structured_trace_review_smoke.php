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

assert_true(method_exists(QmsDocumentStructureService::class, 'blockTraceReviewDetail'), 'Structure service exposes block trace review detail');
assert_true(method_exists(QmsDocumentStructureService::class, 'upsertBlockTraceLink'), 'Structure service can save a reviewed trace link');
assert_true(method_exists(QmsDocumentStructureService::class, 'deleteBlockTraceLink'), 'Structure service can delete a reviewed trace link');

QmsDocumentStructureService::seedAll();

$block = Db::name('qms_document_blocks')
    ->alias('b')
    ->join('qms_structured_documents sd', 'sd.id = b.structured_document_id')
    ->where('sd.doc_number', 'XZTC/CX-26-2022')
    ->where('b.block_type', 'record_requirement')
    ->where('b.soft_delete', 0)
    ->field('b.id,b.structured_document_id')
    ->find();
assert_true((bool)$block, 'XZTC/CX-26-2022 has a record requirement block for trace review');

$positionId = (string)Db::name('qms_positions')->where('code', 'document_controller')->where('soft_delete', 0)->value('id');
assert_true($positionId !== '', 'Document controller position exists');

$blockId = (string)$block['id'];
$structuredId = (string)$block['structured_document_id'];
$reviewNote = '结构化追溯复核 smoke：资料管理员负责计算机软件记录';
$deleteNote = '结构化追溯复核 smoke：删除测试岗位映射';

Db::name('qms_document_block_links')
    ->where('block_id', $blockId)
    ->where('position_id', $positionId)
    ->where('note', $reviewNote)
    ->update(['soft_delete' => 1]);
Db::name('qms_document_change_logs')
    ->where('structured_document_id', $structuredId)
    ->whereIn('revision_note', [$reviewNote, $deleteNote])
    ->update(['soft_delete' => 1]);

$detail = QmsDocumentStructureService::blockTraceReviewDetail($blockId);
assert_true(($detail['block']['id'] ?? '') === $blockId, 'Trace review detail returns the block');
assert_true(count($detail['options']['positions'] ?? []) > 0, 'Trace review detail exposes position options');
assert_true(count($detail['options']['elements'] ?? []) > 0, 'Trace review detail exposes element options');
assert_true(count($detail['options']['record_forms'] ?? []) > 0, 'Trace review detail exposes record form options');

assert_throws(
    fn () => QmsDocumentStructureService::upsertBlockTraceLink($blockId, [
        'relation_type' => 'responsible',
        'position_id' => $positionId,
        'note' => '',
    ]),
    '复核说明不能为空',
    'Trace review cannot save a link without evidence note'
);

$result = QmsDocumentStructureService::upsertBlockTraceLink($blockId, [
    'relation_type' => 'responsible',
    'position_id' => $positionId,
    'confidence' => 'review_required',
    'note' => $reviewNote,
]);
$linkId = (string)($result['link']['id'] ?? '');
try {
    assert_true($linkId !== '', 'Trace review returns the saved link id');
    $link = Db::name('qms_document_block_links')->where('id', $linkId)->find();
    assert_true((string)$link['position_id'] === $positionId, 'Reviewed trace link stores the position');
    assert_true((string)$link['relation_type'] === 'responsible', 'Reviewed trace link stores relation type');
    assert_true((string)$link['confidence'] === 'review_required', 'Reviewed trace link stores review-required confidence');
    assert_true((string)$link['note'] === $reviewNote, 'Reviewed trace link stores the evidence note');

    $log = Db::name('qms_document_change_logs')
        ->where('structured_document_id', $structuredId)
        ->where('block_id', $blockId)
        ->where('revision_note', $reviewNote)
        ->where('soft_delete', 0)
        ->find();
    assert_true((bool)$log, 'Saving reviewed trace link writes a change log');
    assert_contains('trace-link:', (string)$log['new_excerpt'], 'Trace review log records linked target summary');

    $deleteResult = QmsDocumentStructureService::deleteBlockTraceLink($linkId, $deleteNote);
    assert_true(($deleteResult['deleted_link_id'] ?? '') === $linkId, 'Trace review delete returns the deleted link id');
    assert_true((int)Db::name('qms_document_block_links')->where('id', $linkId)->value('soft_delete') === 1, 'Deleting reviewed trace link soft-deletes the row');
    $deleteLog = Db::name('qms_document_change_logs')
        ->where('structured_document_id', $structuredId)
        ->where('block_id', $blockId)
        ->where('revision_note', $deleteNote)
        ->where('soft_delete', 0)
        ->find();
    assert_true((bool)$deleteLog, 'Deleting reviewed trace link writes a change log');
} finally {
    if ($linkId !== '') {
        Db::name('qms_document_block_links')->where('id', $linkId)->update(['soft_delete' => 1]);
    }
    Db::name('qms_document_change_logs')
        ->where('structured_document_id', $structuredId)
        ->whereIn('revision_note', [$reviewNote, $deleteNote])
        ->update(['soft_delete' => 1]);
}

$routeSource = file_get_contents(dirname(__DIR__) . '/route/app.php') ?: '';
assert_contains('planning/structures/links/review', $routeSource, 'Routes expose trace review page');
assert_contains('planning/structures/links/save', $routeSource, 'Routes expose trace link save action');
assert_contains('planning/structures/links/delete', $routeSource, 'Routes expose trace link delete action');

$controllerSource = file_get_contents(dirname(__DIR__) . '/app/controller/PlanningStructure.php') ?: '';
assert_contains('reviewLinks', $controllerSource, 'Structure controller exposes trace review');
assert_contains('saveLink', $controllerSource, 'Structure controller exposes trace link save');
assert_contains('deleteLink', $controllerSource, 'Structure controller exposes trace link delete');

$detailView = file_get_contents(dirname(__DIR__) . '/app/view/planning_structure/view.html') ?: '';
assert_contains('复核追溯', $detailView, 'Structure detail links to trace review');

echo "qms_structured_trace_review_smoke passed\n";
