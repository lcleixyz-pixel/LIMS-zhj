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

$schema = file_get_contents(dirname(__DIR__) . '/database/migrations/20260523_qms_planning.sql') ?: '';
assert_contains('CREATE TABLE IF NOT EXISTS `qms_document_change_logs`', $schema, 'Planning migration defines structured document change logs');
assert_contains('`revision_note` text NOT NULL', $schema, 'Change logs require a revision note');
assert_contains('`archive_path` varchar(500) DEFAULT NULL', $schema, 'Change logs store render archive path');
assert_contains('`trace_snapshot_json` mediumtext', $schema, 'Change logs store traceability impact snapshots');

QmsDocumentStructureService::seedAll();

$block = Db::name('qms_document_blocks')
    ->alias('b')
    ->join('qms_structured_documents sd', 'sd.id = b.structured_document_id')
    ->where('sd.doc_number', 'XZTC/CX-26-2022')
    ->where('b.block_type', 'record_requirement')
    ->where('b.soft_delete', 0)
    ->field('b.id,b.markdown,sd.id structured_document_id,sd.status structured_status,sd.review_note')
    ->find();

assert_true((bool)$block, 'XZTC/CX-26-2022 has a record requirement block for change-control testing');

$blockId = (string)$block['id'];
$structuredId = (string)$block['structured_document_id'];
$originalMarkdown = (string)$block['markdown'];
$originalStatus = (string)$block['structured_status'];
$originalReviewNote = (string)$block['review_note'];
$revisionNote = '结构化变更控制 smoke：补充记录要求说明';
$marker = '<!-- structured-change-control-' . date('YmdHis') . ' -->';

assert_throws(
    fn () => QmsDocumentStructureService::updateBlockMarkdown($blockId, $originalMarkdown . "\n\n无说明修改\n", ''),
    '修订说明不能为空',
    'Block edits cannot be saved without a revision note'
);

$beforeLogCount = Db::name('qms_document_change_logs')
    ->where('structured_document_id', $structuredId)
    ->where('block_id', $blockId)
    ->where('soft_delete', 0)
    ->count();

$result = QmsDocumentStructureService::updateBlockMarkdown(
    $blockId,
    rtrim($originalMarkdown) . "\n\n" . $marker . "\n",
    $revisionNote
);

try {
    assert_true(($result['structured_document']['status'] ?? '') === 'draft', 'Editing a structured block marks the parent document as draft');
    assert_contains($revisionNote, (string)($result['structured_document']['review_note'] ?? ''), 'Parent document review note keeps the revision note');

    $afterLogCount = Db::name('qms_document_change_logs')
        ->where('structured_document_id', $structuredId)
        ->where('block_id', $blockId)
        ->where('soft_delete', 0)
        ->count();
    assert_true($afterLogCount >= $beforeLogCount + 1, 'Editing a block writes a change log row');

    $log = Db::name('qms_document_change_logs')
        ->where('structured_document_id', $structuredId)
        ->where('block_id', $blockId)
        ->where('soft_delete', 0)
        ->order('created', 'desc')
        ->find();
    assert_true((bool)$log, 'Latest change log row can be queried');
    assert_true((string)$log['revision_note'] === $revisionNote, 'Change log stores the revision note');
    assert_true((string)$log['old_markdown_sha256'] === hash('sha256', $originalMarkdown), 'Change log stores old markdown hash');
    assert_true((string)$log['new_markdown_sha256'] === hash('sha256', rtrim($originalMarkdown) . "\n\n" . $marker . "\n"), 'Change log stores new markdown hash');
    assert_true((string)$log['archive_path'] !== '', 'Change log stores the render archive path');
    assert_true(is_file(dirname(__DIR__) . '/' . (string)$log['archive_path']), 'Change log archive path points to an existing file');
    $snapshot = json_decode((string)($log['trace_snapshot_json'] ?? ''), true);
    assert_true(is_array($snapshot), 'Change log stores a JSON traceability snapshot');
    assert_true((string)($snapshot['block']['id'] ?? '') === $blockId, 'Traceability snapshot identifies the edited block');
    assert_true((string)($snapshot['structured_document']['id'] ?? '') === $structuredId, 'Traceability snapshot identifies the structured document');
    assert_true(count($snapshot['links'] ?? []) > 0, 'Traceability snapshot includes affected block links');
    $snapshotText = json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    assert_contains('数据控制和信息管理', $snapshotText, 'Traceability snapshot includes affected element names');

    $detail = QmsDocumentStructureService::structuredDocumentDetail($structuredId);
    assert_true(count($detail['change_logs'] ?? []) > 0, 'Structured document detail exposes change logs');
    assert_contains($revisionNote, (string)($detail['change_logs'][0]['revision_note'] ?? ''), 'Detail change log includes the revision note');
    assert_true(is_array($detail['change_logs'][0]['trace_snapshot'] ?? null), 'Structured document detail exposes decoded traceability snapshots');
} finally {
    QmsDocumentStructureService::updateBlockMarkdown($blockId, $originalMarkdown, '恢复 smoke 测试内容');
    Db::name('qms_document_change_logs')
        ->where('structured_document_id', $structuredId)
        ->where('block_id', $blockId)
        ->whereIn('revision_note', [$revisionNote, '恢复 smoke 测试内容'])
        ->update(['soft_delete' => 1]);
    Db::name('qms_structured_documents')->where('id', $structuredId)->update([
        'status' => $originalStatus,
        'review_note' => $originalReviewNote,
    ]);
}

$editView = file_get_contents(dirname(__DIR__) . '/app/view/planning_structure/block_edit.html') ?: '';
assert_contains('修订说明', $editView, 'Block edit page asks for a revision note');

$detailView = file_get_contents(dirname(__DIR__) . '/app/view/planning_structure/view.html') ?: '';
assert_contains('变更记录', $detailView, 'Structure detail page shows change logs');

echo "qms_structured_change_control_smoke passed\n";
