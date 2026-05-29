<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../app/common.php';

$app = new think\App();
$app->initialize();

use app\service\QmsDocumentStructureService;
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

QmsElementService::seedAll();
QmsDocumentStructureService::seedAll();

$clause = Db::name('qms_clauses')
    ->alias('c')
    ->join('qms_sources s', 's.id = c.source_id')
    ->where('s.source_code', 'GB/T 27025-2019')
    ->where('c.clause_number', '7.11')
    ->where('c.soft_delete', 0)
    ->field('c.id,c.clause_number,c.title')
    ->find();
$block = Db::name('qms_document_blocks')
    ->alias('b')
    ->join('qms_structured_documents sd', 'sd.id = b.structured_document_id')
    ->where('sd.doc_number', 'QP-26')
    ->where('b.block_type', 'record_requirement')
    ->where('b.soft_delete', 0)
    ->field('b.id,b.structured_document_id,b.title')
    ->find();

assert_true((bool)$clause, 'GB/T 27025 7.11 clause exists');
assert_true((bool)$block, 'QP-26 record requirement block exists');

$clauseId = (string)$clause['id'];
$blockId = (string)$block['id'];
$structuredId = (string)$block['structured_document_id'];
$note = '条款结构块证据 smoke：7.11 对应计算机文件及数据控制程序记录要求';

try {
    Db::name('qms_document_block_links')->where('note', $note)->update(['soft_delete' => 1]);
    Db::name('qms_document_change_logs')->where('revision_note', $note)->update(['soft_delete' => 1]);

    QmsDocumentStructureService::upsertBlockTraceLink($blockId, [
        'clause_id' => $clauseId,
        'relation_type' => 'basis',
        'confidence' => 'high',
        'note' => $note,
    ]);

    assert_true(method_exists(QmsElementService::class, 'clauseStructuredBlockEvidence'), 'Element service exposes clause structured block evidence');
    $evidenceRows = QmsElementService::clauseStructuredBlockEvidence($clauseId);
    assert_true(count($evidenceRows) >= 1, 'Clause detail can read structured block evidence rows');

    $evidence = null;
    foreach ($evidenceRows as $row) {
        if ((string)($row['note'] ?? '') === $note) {
            $evidence = $row;
            break;
        }
    }
    assert_true(is_array($evidence), 'Clause detail can locate the reviewed block evidence row');
    assert_true((string)$evidence['clause_id'] === $clauseId, 'Clause evidence keeps the source clause id');
    assert_true((string)$evidence['block_id'] === $blockId, 'Clause evidence keeps the source block id');
    assert_true((string)$evidence['structured_document_id'] === $structuredId, 'Clause evidence keeps the structured document id');
    assert_true((string)$evidence['doc_number'] === 'QP-26', 'Clause evidence names the source procedure');
    assert_true((string)$evidence['review_url'] === '/planning/structures/links/review?block_id=' . $blockId, 'Clause evidence links back to trace review');
    assert_true((string)$evidence['document_url'] === '/planning/structures/view?id=' . $structuredId, 'Clause evidence links back to structured document');

    $controllerSource = file_get_contents(dirname(__DIR__) . '/app/controller/PlanningClause.php') ?: '';
    assert_contains('clauseStructuredBlockEvidence', $controllerSource, 'Clause controller assigns structured block evidence');

    $viewSource = file_get_contents(dirname(__DIR__) . '/app/view/planning_clause/view.html') ?: '';
    assert_contains('结构块证据', $viewSource, 'Clause detail page shows structured block evidence section');
    assert_contains('复核追溯', $viewSource, 'Clause detail page links to trace review');
    assert_contains('review_url', $viewSource, 'Clause detail page renders review links from service data');
} finally {
    Db::name('qms_document_block_links')->where('note', $note)->update(['soft_delete' => 1]);
    Db::name('qms_document_change_logs')->where('revision_note', $note)->update(['soft_delete' => 1]);
}

echo "qms_clause_structured_evidence_smoke passed\n";
