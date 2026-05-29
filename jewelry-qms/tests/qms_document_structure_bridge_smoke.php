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

assert_true(
    method_exists(QmsDocumentStructureService::class, 'controlledDocumentStructureSummary'),
    'Structure service exposes controlled-document structure summary'
);
assert_true(
    method_exists(QmsDocumentStructureService::class, 'refreshControlledDocumentStructure'),
    'Structure service can refresh structure by controlled document id'
);

$document = Db::name('documents')
    ->where('doc_number', 'QP-26')
    ->where('soft_delete', 0)
    ->find();
assert_true(is_array($document), 'QP-26 controlled document exists');

$summary = QmsDocumentStructureService::controlledDocumentStructureSummary((string)$document['id']);
assert_true((string)($summary['structured_document_id'] ?? '') !== '', 'Controlled document summary links to structured document');
assert_true((string)($summary['document_role'] ?? '') === 'procedure', 'Controlled document summary keeps structure role');
assert_true((string)($summary['view_url'] ?? '') === '/planning/structures/view?id=' . (string)$summary['structured_document_id'], 'Controlled document summary exposes structure detail URL');
assert_true((int)($summary['block_count'] ?? 0) > 0, 'Controlled document summary includes block count');
assert_true((int)($summary['link_count'] ?? 0) > 0, 'Controlled document summary includes trace link count');

$originalStructured = Db::name('qms_structured_documents')->where('id', (string)$summary['structured_document_id'])->find();
$refreshNote = '文件控制桥接 smoke：按受控文件重新同步结构';
$result = QmsDocumentStructureService::refreshControlledDocumentStructure((string)$document['id'], $refreshNote);
try {
    assert_true((string)($result['structured_document']['document_id'] ?? '') === (string)$document['id'], 'Document-level refresh returns matching structured document');
    assert_true((string)($result['structured_document']['status'] ?? '') === 'draft', 'Document-level refresh marks structure draft for review');
    $log = Db::name('qms_document_change_logs')
        ->where('structured_document_id', (string)$summary['structured_document_id'])
        ->where('change_type', 'version_update')
        ->where('revision_note', $refreshNote)
        ->where('soft_delete', 0)
        ->find();
    assert_true(is_array($log), 'Document-level refresh writes version update log');
} finally {
    if (is_array($originalStructured)) {
        Db::name('qms_structured_documents')->where('id', (string)$summary['structured_document_id'])->update([
            'status' => (string)$originalStructured['status'],
            'review_note' => (string)$originalStructured['review_note'],
        ]);
    }
    Db::name('qms_document_change_logs')
        ->where('structured_document_id', (string)$summary['structured_document_id'])
        ->where('revision_note', $refreshNote)
        ->delete();
}

$controllerSource = file_get_contents(dirname(__DIR__) . '/app/controller/Document.php') ?: '';
assert_contains('controlledDocumentStructureSummary', $controllerSource, 'Document detail loads structure summary');
assert_contains('refreshControlledDocumentStructure', $controllerSource, 'Document revision refreshes structure after saving');
assert_contains('structureSummary', $controllerSource, 'Document controller assigns structure summary to view');

$detailView = file_get_contents(dirname(__DIR__) . '/app/view/document/view.html') ?: '';
assert_contains('结构化文件', $detailView, 'Document detail shows structured document section');
assert_contains('planning/structures/view', $detailView, 'Document detail links to structured document detail');
assert_contains('同步结构化', $detailView, 'Document detail names structure sync status');

$reviseView = file_get_contents(dirname(__DIR__) . '/app/view/document/revise.html') ?: '';
assert_contains('/document/revise?id=', $reviseView, 'Document revision form posts to the existing revise route');
assert_contains('name="document_file"', $reviseView, 'Document revision upload field matches controller input');
assert_contains('修订后将同步结构化文件', $reviseView, 'Document revision explains structure synchronization');

echo "qms_document_structure_bridge_smoke passed\n";
