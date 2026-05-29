<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../app/common.php';

$app = new think\App();
$app->initialize();

use app\service\QmsDocumentStructureService;
use app\service\QmsElementService;
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

QmsElementService::seedAll();
QmsDocumentStructureService::seedAll();

$companyId = (string)Config::get('qms.company_id');
$elementKey = 'smoke_clause_inferred_evidence';
$note = '条款经要素推导证据 smoke：7.11 经临时要素指向 QP-26 记录要求';
$now = date('Y-m-d H:i:s');

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
$existingElementId = (string)Db::name('qms_elements')->where('key', $elementKey)->value('id');
$elementId = $existingElementId !== '' ? $existingElementId : qms_uuid();
$existingClauseLinkId = (string)Db::name('qms_element_clause_links')
    ->where('element_id', $elementId)
    ->where('clause_id', $clauseId)
    ->value('id');
$clauseLinkId = $existingClauseLinkId !== '' ? $existingClauseLinkId : qms_uuid();

try {
    Db::name('qms_document_block_links')->where('note', $note)->update(['soft_delete' => 1]);
    Db::name('qms_document_change_logs')->where('revision_note', $note)->update(['soft_delete' => 1]);
    Db::name('qms_element_clause_links')->where('id', $clauseLinkId)->update(['soft_delete' => 1]);
    Db::name('qms_elements')->where('key', $elementKey)->update(['soft_delete' => 1]);
    Db::name('qms_agent_suggestions')->where('element_id', $elementId)->delete();

    $elementPayload = [
        'id' => $elementId,
        'company_id' => $companyId,
        'key' => $elementKey,
        'name' => '条款经要素推导证据 smoke 要素',
        'element_type' => 'management',
        'applicability' => 'applicable',
        'status' => 'draft',
        'sort_order' => 9997,
        'publish' => 1,
        'soft_delete' => 0,
        'created' => $now,
        'modified' => $now,
    ];
    if ($existingElementId !== '') {
        unset($elementPayload['id'], $elementPayload['created']);
        Db::name('qms_elements')->where('id', $elementId)->update($elementPayload);
    } else {
        Db::name('qms_elements')->insert($elementPayload);
    }

    $clauseLinkPayload = [
        'id' => $clauseLinkId,
        'company_id' => $companyId,
        'element_id' => $elementId,
        'clause_id' => $clauseId,
        'mapping_type' => 'reference',
        'is_primary' => 0,
        'note' => $note,
        'publish' => 1,
        'soft_delete' => 0,
        'created' => $now,
        'modified' => $now,
    ];
    if ($existingClauseLinkId !== '') {
        unset($clauseLinkPayload['id'], $clauseLinkPayload['created']);
        Db::name('qms_element_clause_links')->where('id', $clauseLinkId)->update($clauseLinkPayload);
    } else {
        Db::name('qms_element_clause_links')->insert($clauseLinkPayload);
    }

    QmsDocumentStructureService::upsertBlockTraceLink($blockId, [
        'element_id' => $elementId,
        'relation_type' => 'supporting',
        'confidence' => 'high',
        'note' => $note,
    ]);

    $evidenceRows = QmsElementService::clauseStructuredBlockEvidence($clauseId);
    $evidence = null;
    foreach ($evidenceRows as $row) {
        if ((string)($row['note'] ?? '') === $note) {
            $evidence = $row;
            break;
        }
    }

    assert_true(is_array($evidence), 'Clause detail can infer structured block evidence through mapped elements');
    assert_true((string)$evidence['clause_id'] === '', 'Inferred evidence does not require the block link to carry clause_id');
    assert_true((string)$evidence['element_id'] === $elementId, 'Inferred evidence keeps the mapped element id');
    assert_true((string)$evidence['element_name'] === '条款经要素推导证据 smoke 要素', 'Inferred evidence names the mapped element');
    assert_true((string)$evidence['block_id'] === $blockId, 'Inferred evidence keeps the source block id');
    assert_true((string)$evidence['structured_document_id'] === $structuredId, 'Inferred evidence keeps the structured document id');
    assert_true((string)$evidence['doc_number'] === 'QP-26', 'Inferred evidence names the source procedure');
    assert_true((string)$evidence['review_url'] === '/planning/structures/links/review?block_id=' . $blockId, 'Inferred evidence links back to trace review');
    assert_true((string)$evidence['document_url'] === '/planning/structures/view?id=' . $structuredId, 'Inferred evidence links back to structured document');
    assert_contains('经要素映射', (string)($evidence['evidence_path'] ?? ''), 'Inferred evidence labels the trace path');

    $viewSource = file_get_contents(dirname(__DIR__) . '/app/view/planning_clause/view.html') ?: '';
    assert_contains('追溯路径', $viewSource, 'Clause detail page shows trace path for structured evidence');
    assert_contains('evidence_path', $viewSource, 'Clause detail page renders the evidence path from service data');
} finally {
    Db::name('qms_document_block_links')->where('note', $note)->update(['soft_delete' => 1]);
    Db::name('qms_document_change_logs')->where('revision_note', $note)->update(['soft_delete' => 1]);
    Db::name('qms_element_clause_links')->where('id', $clauseLinkId)->update(['soft_delete' => 1]);
    Db::name('qms_agent_suggestions')->where('element_id', $elementId)->delete();
    Db::name('qms_elements')->where('id', $elementId)->update(['soft_delete' => 1]);
}

echo "qms_clause_inferred_structured_evidence_smoke passed\n";
