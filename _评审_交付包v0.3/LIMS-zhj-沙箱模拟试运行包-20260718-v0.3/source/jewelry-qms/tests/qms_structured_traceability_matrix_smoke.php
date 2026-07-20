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

function assert_not_contains(string $needle, string $haystack, string $message): void
{
    if (str_contains($haystack, $needle)) {
        fwrite(STDERR, $message . PHP_EOL);
        fwrite(STDERR, 'Unexpected: ' . $needle . PHP_EOL);
        exit(1);
    }
}

function matrix_row_for_key(array $rows, string $key): ?array
{
    foreach ($rows as $row) {
        if ((string)($row['element']->key ?? '') === $key) {
            return $row;
        }
    }

    return null;
}

QmsElementService::seedAll();
QmsDocumentStructureService::seedAll();

$companyId = (string)Config::get('qms.company_id');
$elementKey = 'smoke_structured_matrix';
$note = '结构块追溯矩阵 smoke：块级证据覆盖记录、岗位和运行模块'
    . "\n字段schema来源：候选schema草稿已人工保存；来源记录要求块：matrix-smoke-block；智能体建议：matrix-smoke-suggestion";
$now = date('Y-m-d H:i:s');

$blockId = (string)Db::name('qms_document_blocks')
    ->alias('b')
    ->join('qms_structured_documents sd', 'sd.id = b.structured_document_id')
    ->where('sd.doc_number', 'XZTC/CX-26-2022')
    ->where('b.block_type', 'record_requirement')
    ->where('b.soft_delete', 0)
    ->value('b.id');
$recordFormId = (string)Db::name('record_form_templates')->where('doc_number', 'XZTC/BG-26-01')->where('soft_delete', 0)->value('id');
$positionId = (string)Db::name('qms_positions')->where('code', 'document_controller')->where('soft_delete', 0)->value('id');
$moduleId = (string)Db::name('qms_business_modules')->where('code', 'record_form_templates')->where('soft_delete', 0)->value('id');

assert_true($blockId !== '', 'Smoke block exists');
assert_true($recordFormId !== '', 'Smoke record form exists');
assert_true($positionId !== '', 'Smoke position exists');
assert_true($moduleId !== '', 'Smoke business module exists');

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
        'name' => '结构块追溯矩阵 smoke 要素',
        'element_type' => 'management',
        'applicability' => 'applicable',
        'status' => 'draft',
        'sort_order' => 9999,
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

    $row = matrix_row_for_key(QmsElementService::traceabilityMatrix(), $elementKey);
    assert_true(is_array($row), 'Traceability matrix includes the smoke element');
    assert_true((int)($row['block_trace_count'] ?? 0) >= 1, 'Traceability matrix reports structured block evidence count');
    assert_true((int)($row['record_schema_source_count'] ?? 0) >= 1, 'Traceability matrix reports record schema source trace count');
    assert_true((int)$row['record_form_count'] >= 1, 'Structured block record link counts as record-form coverage');
    assert_true((int)$row['module_count'] >= 1, 'Structured block module link counts as business-module coverage');
    assert_true((int)$row['responsibility_count'] >= 1, 'Structured block position link counts as responsibility coverage');
    assert_not_contains('缺记录表格', (string)$row['gap_text'], 'Record-form gap is resolved by block-level evidence');
    assert_not_contains('缺运行模块', (string)$row['gap_text'], 'Module gap is resolved by block-level evidence');
    assert_not_contains('缺岗位职责', (string)$row['gap_text'], 'Responsibility gap is resolved by block-level evidence');

    QmsElementService::refreshAgentSuggestions();
    $suggestionContent = (string)Db::name('qms_agent_suggestions')
        ->where('element_id', $elementId)
        ->where('suggestion_type', 'gap')
        ->where('status', 'open')
        ->value('content');
    assert_not_contains('缺记录表格', $suggestionContent, 'Gap suggestion respects structured record evidence');
    assert_not_contains('缺运行模块', $suggestionContent, 'Gap suggestion respects structured module evidence');
    assert_not_contains('缺岗位职责', $suggestionContent, 'Gap suggestion respects structured responsibility evidence');

    $traceabilityView = file_get_contents(dirname(__DIR__) . '/app/view/planning_traceability/index.html') ?: '';
    assert_contains('结构块证据', $traceabilityView, 'Traceability matrix exposes structured block evidence');
    assert_contains('字段来源', $traceabilityView, 'Traceability matrix exposes schema source trace');
} finally {
    Db::name('qms_document_block_links')->where('note', $note)->update(['soft_delete' => 1]);
    Db::name('qms_document_change_logs')->where('revision_note', $note)->update(['soft_delete' => 1]);
    Db::name('qms_agent_suggestions')->where('element_id', $elementId)->delete();
    Db::name('qms_elements')->where('id', $elementId)->update(['soft_delete' => 1]);
}

echo "qms_structured_traceability_matrix_smoke passed\n";
