<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../app/common.php';

if (!function_exists('root_path')) {
    function root_path(): string
    {
        return dirname(__DIR__) . DIRECTORY_SEPARATOR;
    }
}

use app\service\RecordFormReconstructionReviewService;
use app\service\RecordFormSchemaRebuilder;

function assert_true(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

function assert_same($expected, $actual, string $message): void
{
    if ($expected !== $actual) {
        fwrite(STDERR, $message . PHP_EOL);
        fwrite(STDERR, 'Expected: ' . var_export($expected, true) . PHP_EOL);
        fwrite(STDERR, 'Actual: ' . var_export($actual, true) . PHP_EOL);
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

function find_packet(array $review, string $docNumber, string $sourceNeedle = ''): array
{
    foreach (($review['items'] ?? []) as $item) {
        if (($item['doc_number'] ?? '') !== $docNumber) {
            continue;
        }
        if ($sourceNeedle !== '' && !str_contains((string)($item['source_file_name'] ?? ''), $sourceNeedle)
            && !str_contains((string)($item['original_doc_number'] ?? ''), $sourceNeedle)) {
            continue;
        }
        return $item;
    }

    fwrite(STDERR, 'Packet not found: ' . $docNumber . ' ' . $sourceNeedle . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__);

$fullReview = RecordFormReconstructionReviewService::reviewAll(null, null, 'both');
assert_same(0, $fullReview['summary']['needs_system_link'], 'Full reconstruction review has no system-link gaps after forward-chain completion');
assert_same(0, $fullReview['summary']['needs_human_scope'], 'Full reconstruction review has no human-scope gaps after direct inclusion decisions');
assert_same(0, $fullReview['summary']['identity_conflict'], 'Full reconstruction review has no identity conflicts after direct inclusion decisions');

$review = RecordFormReconstructionReviewService::reviewAll('检测方法', null, 'both');
assert_true(isset($review['items']) && is_array($review['items']), 'Reconstruction review returns item packets');

$itemsByKey = [];
foreach ($review['items'] as $item) {
    $itemsByKey[$item['identity_key']] = $item;
}

assert_true(isset($itemsByKey['XZTC/BG-22-01::XZTC_BG-22-01-A_0']), 'BG-22-01 packet exists under composite identity');
assert_true(isset($itemsByKey['XZTC/BG-22-02::XZTC_BG-22-02-A_0']), 'BG-22-02 packet exists under composite identity');
assert_true(isset($itemsByKey['XZTC/BG-22-03::待定-22-01-A_0']), 'Draft standard list is canonicalized under BG-22-03 composite identity');

$bg2201 = $itemsByKey['XZTC/BG-22-01::XZTC_BG-22-01-A_0'];
assert_same('ready_for_rebuild', $bg2201['decision'], 'BG-22-01 is ready for reconstruction from CX-22 evidence');
assert_contains('XZTC/CX-22-2022', $bg2201['procedure']['doc_number'], 'BG-22-01 keeps procedure context');
assert_true($bg2201['record_list']['found'], 'BG-22-01 has record-list evidence');
assert_true($bg2201['manual']['found'], 'BG-22-01 has manual/element context');
assert_true(in_array('field_identity', $bg2201['field_obligations'], true), 'BG-22-01 carries identity field obligation');
assert_true(in_array('signature_chain', $bg2201['field_obligations'], true), 'BG-22-01 carries signature-chain obligation');

$bg2203 = $itemsByKey['XZTC/BG-22-03::待定-22-01-A_0'];
assert_same('待定-22-01', $bg2203['original_doc_number'], 'BG-22-03 keeps provisional original number');
assert_same('ready_for_rebuild', $bg2203['decision'], 'BG-22-03 standard validity list is ready for reconstruction');
assert_contains('qms_sources', implode(' ', $bg2203['external_register_boundaries']), 'BG-22-03 explains qms_sources freshness handoff');

$badIdentity = RecordFormReconstructionReviewService::reviewStructuredFile(
    $root . '/runtime/qms_structured/record_form/XZTC_BG-22-01-A_0.md',
    ['force_doc_number' => 'XZTC/BG-22-99']
);
assert_same('identity_conflict', $badIdentity['decision'], 'Non-provisional source record mismatch blocks reconstruction');

$missingSystem = RecordFormReconstructionReviewService::reviewPreparedRecord([
    'doc_number' => 'XZTC/BG-99-99',
    'name' => '无体系链路测试表',
    'module' => '不存在程序',
    'source_file_name' => 'missing.docx',
    'field_schema' => [],
]);
assert_same('needs_system_link', $missingSystem['decision'], 'Missing procedure and record-list evidence requires system link work');
assert_true(in_array('procedure', $missingSystem['missing_obligations'], true), 'Missing packet names procedure obligation');
assert_true(in_array('record_list', $missingSystem['missing_obligations'], true), 'Missing packet names record-list obligation');

$correctionPacket = RecordFormReconstructionReviewService::reviewPreparedRecord([
    'doc_number' => '待定-16-01',
    'name' => '实施纠正措施记录表',
    'module' => '实施纠正措施程序',
    'source_file_name' => '实施纠正措施记录表.docx',
    'reference' => 'PROC-012 纠正措施实施记录（REC-012-03，保存6年）；PROC-012 纠正措施验证记录（REC-012-04，保存6年）',
    'import_action' => '人工确认',
    'field_schema' => [['key' => 'record_date', 'label' => '记录日期', 'type' => 'date', 'required' => true]],
]);
assert_same('XZTC/BG-16-01', $correctionPacket['doc_number'], 'Directory-extra corrective action record canonicalizes to formal import number');
assert_same('待定-16-01', $correctionPacket['original_doc_number'], 'Canonicalized corrective action record keeps provisional source number');
assert_same('ready_for_rebuild', $correctionPacket['decision'], 'Canonicalized corrective action record can enter reconstruction');

$confidentialityPacket = RecordFormReconstructionReviewService::reviewPreparedRecord([
    'doc_number' => '待定-01-01',
    'name' => '保密协议',
    'module' => '人员培训程序',
    'source_file_name' => '保密协议.docx',
    'reference' => '参考清单未列直接对应项',
    'import_action' => '人工确认',
    'field_schema' => [['key' => 'record_date', 'label' => '记录日期', 'type' => 'date', 'required' => true]],
]);
assert_same('XZTC/BG-01-10', $confidentialityPacket['doc_number'], 'Confidentiality agreement is directly included under a formal record number');
assert_same('待定-01-01', $confidentialityPacket['original_doc_number'], 'Confidentiality agreement keeps provisional source number');
assert_same('ready_for_rebuild', $confidentialityPacket['decision'], 'Confidentiality agreement can enter reconstruction after forward-chain completion');
assert_same('not_applicable', $confidentialityPacket['forward_chain']['work_instruction']['status'], 'Confidentiality agreement records work-instruction non-applicability');

$expectedDirectInclusions = [
    ['XZTC/BG-01-10', '待定-01-01', '保密协议'],
    ['XZTC/BG-01-11', '待定-01-02', '劳动合同'],
    ['XZTC/BG-03-02', '待定-03-01', '新疆中和鉴珠宝玉石质量检测研究所'],
    ['XZTC/BG-04-07', '待定-04-02', '红外性能确认'],
    ['XZTC/BG-13-01', '待定-13-01', '会议记录'],
    ['XZTC/BG-20-09', '待定-20-01', '不符合项汇总表'],
    ['XZTC/BG-20-10', '待定-20-04', '内部审核资料封皮目录'],
    ['XZTC/BG-24-03', '待定-24-01', '标准查新报告'],
    ['XZTC/BG-33-01', '待定-33-01', '安 全 检 查 记 录 表'],
];
foreach ($expectedDirectInclusions as [$formalNumber, $originalNumber, $sourceNeedle]) {
    $packet = find_packet($fullReview, $formalNumber, $sourceNeedle);
    assert_same($originalNumber, $packet['original_doc_number'], $formalNumber . ' keeps original provisional number');
    assert_same('ready_for_rebuild', $packet['decision'], $formalNumber . ' is ready after forward-chain completion');
    assert_true($packet['record_list']['found'], $formalNumber . ' has record-list evidence after forward-chain completion');
    assert_true($packet['forward_chain']['procedure']['found'], $formalNumber . ' has procedure evidence in forward chain');
}

$schemaRegistry = RecordFormSchemaRebuilder::loadRegistry();
assert_true(isset($schemaRegistry['XZTC/BG-22-01']['reconstruction_review']), 'Schema registry stores reconstruction review summary');
assert_true(isset($schemaRegistry['XZTC/BG-22-01']['schema_coverage']), 'Schema registry stores schema coverage summary');
assert_same('ready_for_rebuild', $schemaRegistry['XZTC/BG-22-01']['reconstruction_review']['decision'], 'Registry review summary keeps ready decision');
assert_true($schemaRegistry['XZTC/BG-22-01']['schema_coverage']['passes'], 'Registry coverage passes for reviewed BG-22-01 schema');

$controllerSource = file_get_contents($root . '/app/controller/RecordFormTemplate.php') ?: '';
assert_contains('RecordFormReconstructionReviewService::canPublishTemplate', $controllerSource, 'Template completion is gated by reconstruction review');
assert_contains('field_confirmed', $controllerSource, 'Template completion requires field-confirmed state before publish');

$rebuilderSource = file_get_contents($root . '/app/service/RecordFormSchemaRebuilder.php') ?: '';
assert_contains('reconstruction_packet', $rebuilderSource, 'Schema rebuild receives reconstruction packet context');
assert_contains('schema_coverage', $rebuilderSource, 'Schema rebuild writes schema coverage summary');

$commandSource = file_get_contents($root . '/app/command/RecordFormReconstructionReview.php') ?: '';
assert_contains("record_form:reconstruction_review", $commandSource, 'Reconstruction review CLI is implemented');

$consoleSource = file_get_contents($root . '/config/console.php') ?: '';
assert_contains('RecordFormReconstructionReview::class', $consoleSource, 'Reconstruction review command is registered');

echo "record_form_reconstruction_review_smoke passed\n";
