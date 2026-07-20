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
$elementKey = 'smoke_structured_detail';
$note = '结构块证据详情 smoke：要素详情能点回具体内容块';
$now = date('Y-m-d H:i:s');

$block = Db::name('qms_document_blocks')
    ->alias('b')
    ->join('qms_structured_documents sd', 'sd.id = b.structured_document_id')
    ->where('sd.doc_number', 'XZTC/CX-26-2022')
    ->where('b.block_type', 'record_requirement')
    ->where('b.soft_delete', 0)
    ->field('b.id,b.structured_document_id,b.title')
    ->find();
$recordFormId = (string)Db::name('record_form_templates')->where('doc_number', 'XZTC/BG-26-01')->where('soft_delete', 0)->value('id');
$positionId = (string)Db::name('qms_positions')->where('code', 'document_controller')->where('soft_delete', 0)->value('id');
$moduleId = (string)Db::name('qms_business_modules')->where('code', 'record_form_templates')->where('soft_delete', 0)->value('id');

assert_true((bool)$block, 'Smoke block exists');
assert_true($recordFormId !== '', 'Smoke record form exists');
assert_true($positionId !== '', 'Smoke position exists');
assert_true($moduleId !== '', 'Smoke business module exists');

$blockId = (string)$block['id'];
$structuredId = (string)$block['structured_document_id'];
$existingElementId = (string)Db::name('qms_elements')->where('key', $elementKey)->value('id');
$elementId = $existingElementId !== '' ? $existingElementId : qms_uuid();

try {
    Db::name('qms_elements')->where('key', $elementKey)->update(['soft_delete' => 1]);
    Db::name('qms_document_block_links')->where('note', $note)->update(['soft_delete' => 1]);
    Db::name('qms_agent_suggestions')->where('element_id', $elementId)->delete();

    $elementPayload = [
        'id' => $elementId,
        'company_id' => $companyId,
        'key' => $elementKey,
        'name' => '结构块证据详情 smoke 要素',
        'element_type' => 'management',
        'applicability' => 'applicable',
        'status' => 'draft',
        'sort_order' => 9998,
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

    QmsDocumentStructureService::upsertBlockTraceLink($blockId, [
        'element_id' => $elementId,
        'record_form_template_id' => $recordFormId,
        'position_id' => $positionId,
        'business_module_id' => $moduleId,
        'relation_type' => 'supporting',
        'confidence' => 'high',
        'note' => $note,
    ]);

    $detail = QmsElementService::elementDetail($elementId);
    $evidenceRows = $detail['structured_block_evidence'] ?? [];
    assert_true(count($evidenceRows) >= 1, 'Element detail exposes structured block evidence rows');

    $evidence = null;
    foreach ($evidenceRows as $row) {
        if ((string)($row['note'] ?? '') === $note) {
            $evidence = $row;
            break;
        }
    }
    assert_true(is_array($evidence), 'Element detail can locate the reviewed block evidence row');
    assert_true((string)$evidence['block_id'] === $blockId, 'Structured evidence keeps the source block id');
    assert_true((string)$evidence['structured_document_id'] === $structuredId, 'Structured evidence keeps the source document id');
    assert_true((string)$evidence['doc_number'] === 'XZTC/CX-26-2022', 'Structured evidence names the source procedure');
    assert_true((string)$evidence['record_number'] === 'XZTC/BG-26-01', 'Structured evidence names linked record form');
    assert_true((string)$evidence['position_name'] !== '', 'Structured evidence names linked position');
    assert_true((string)$evidence['module_name'] !== '', 'Structured evidence names linked running module');
    assert_true((string)$evidence['review_url'] === '/planning/structures/links/review?block_id=' . $blockId, 'Structured evidence links back to trace review');
    assert_true((string)$evidence['document_url'] === '/planning/structures/view?id=' . $structuredId, 'Structured evidence links back to structured document');

    $view = file_get_contents(dirname(__DIR__) . '/app/view/planning_element/view.html') ?: '';
    assert_contains('结构块证据', $view, 'Element detail page shows structured block evidence section');
    assert_contains('复核追溯', $view, 'Element detail page links to trace review');
    assert_contains('review_url', $view, 'Element detail page renders review links from service data');
} finally {
    Db::name('qms_document_block_links')->where('note', $note)->update(['soft_delete' => 1]);
    Db::name('qms_document_change_logs')->where('revision_note', $note)->update(['soft_delete' => 1]);
    Db::name('qms_agent_suggestions')->where('element_id', $elementId)->delete();
    Db::name('qms_elements')->where('id', $elementId)->update(['soft_delete' => 1]);
}

echo "qms_element_structured_evidence_smoke passed\n";
