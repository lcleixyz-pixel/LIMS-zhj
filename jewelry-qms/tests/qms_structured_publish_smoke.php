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
    method_exists(QmsDocumentStructureService::class, 'publishStructuredDocument'),
    'Structure service exposes structured document publish review'
);

QmsDocumentStructureService::seedAll();

$block = Db::name('qms_document_blocks')
    ->alias('b')
    ->join('qms_structured_documents sd', 'sd.id = b.structured_document_id')
    ->where('sd.doc_number', 'QP-26')
    ->where('b.block_type', 'record_requirement')
    ->where('b.soft_delete', 0)
    ->field('b.id,b.markdown,sd.id structured_document_id,sd.status structured_status,sd.review_note')
    ->find();
assert_true((bool)$block, 'QP-26 has a record requirement block for publish workflow testing');

$blockId = (string)$block['id'];
$structuredId = (string)$block['structured_document_id'];
$originalMarkdown = (string)$block['markdown'];
$originalStatus = (string)$block['structured_status'];
$originalReviewNote = (string)$block['review_note'];
$marker = '<!-- structured-publish-smoke-' . date('YmdHis') . ' -->';
$revisionNote = '发布 smoke：草稿修改后等待复核';
$publishNote = '发布 smoke：复核通过，可以进入组合包';

QmsDocumentStructureService::updateBlockMarkdown(
    $blockId,
    rtrim($originalMarkdown) . "\n\n" . $marker . "\n",
    $revisionNote
);

try {
    $draft = Db::name('qms_structured_documents')->where('id', $structuredId)->find();
    assert_true((string)$draft['status'] === 'draft', 'Editing leaves the structured document as draft');

    $draftPackage = QmsDocumentStructureService::renderSystemPackage();
    $draftPackageMarkdown = file_get_contents(dirname(__DIR__) . '/' . (string)$draftPackage['output_path']) ?: '';
    assert_not_contains($marker, $draftPackageMarkdown, 'Draft structured documents are excluded from the system package');

    assert_throws(
        fn () => QmsDocumentStructureService::publishStructuredDocument($structuredId, ''),
        '发布说明不能为空',
        'Publishing requires a review note'
    );

    $result = QmsDocumentStructureService::publishStructuredDocument($structuredId, $publishNote);
    assert_true(($result['structured_document']['status'] ?? '') === 'published', 'Publishing marks the structured document as published');
    assert_contains($publishNote, (string)($result['structured_document']['review_note'] ?? ''), 'Published document keeps the review note');

    $log = Db::name('qms_document_change_logs')
        ->where('structured_document_id', $structuredId)
        ->where('change_type', 'status_change')
        ->where('revision_note', $publishNote)
        ->where('soft_delete', 0)
        ->order('created', 'desc')
        ->find();
    assert_true((bool)$log, 'Publishing writes a status change log');
    assert_true((string)$log['status_from'] === 'draft', 'Publish log records source draft status');
    assert_true((string)$log['status_to'] === 'published', 'Publish log records published status');
    assert_true((string)$log['archive_path'] !== '', 'Publish log points to a render archive');
    assert_true(is_file(dirname(__DIR__) . '/' . (string)$log['archive_path']), 'Publish archive path exists');

    $publishedPackage = QmsDocumentStructureService::renderSystemPackage();
    $publishedPackageMarkdown = file_get_contents(dirname(__DIR__) . '/' . (string)$publishedPackage['output_path']) ?: '';
    assert_contains($marker, $publishedPackageMarkdown, 'Published structured documents are included in the system package');

    $detail = QmsDocumentStructureService::structuredDocumentDetail($structuredId);
    assert_contains($publishNote, (string)($detail['change_logs'][0]['revision_note'] ?? ''), 'Structure detail exposes the publish log');
} finally {
    QmsDocumentStructureService::updateBlockMarkdown($blockId, $originalMarkdown, '恢复发布 smoke 测试内容');
    Db::name('qms_structured_documents')->where('id', $structuredId)->update([
        'status' => $originalStatus,
        'review_note' => $originalReviewNote,
    ]);
    Db::name('qms_document_change_logs')
        ->where('structured_document_id', $structuredId)
        ->where('block_id', $blockId)
        ->whereIn('revision_note', [$revisionNote, '恢复发布 smoke 测试内容'])
        ->update(['soft_delete' => 1]);
    Db::name('qms_document_change_logs')
        ->where('structured_document_id', $structuredId)
        ->where('change_type', 'status_change')
        ->where('revision_note', $publishNote)
        ->update(['soft_delete' => 1]);
}

$routeSource = file_get_contents(dirname(__DIR__) . '/route/app.php') ?: '';
assert_contains('planning/structures/publish', $routeSource, 'Routes expose structured document publishing');

$controllerSource = file_get_contents(dirname(__DIR__) . '/app/controller/PlanningStructure.php') ?: '';
assert_contains('publishDocument', $controllerSource, 'Structure controller exposes publish action');

$detailView = file_get_contents(dirname(__DIR__) . '/app/view/planning_structure/view.html') ?: '';
assert_contains('发布结构化文件', $detailView, 'Structure detail page exposes publish action');
assert_contains('发布说明', $detailView, 'Structure detail page asks for publish note');

echo "qms_structured_publish_smoke passed\n";
