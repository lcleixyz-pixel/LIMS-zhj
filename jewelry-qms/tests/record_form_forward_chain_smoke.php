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

use app\service\RecordFormBatchTemplateService;
use app\service\RecordFormReconstructionReviewService;

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

function packet_by_original(array $review, string $formalNumber, string $originalNumber): array
{
    foreach (($review['items'] ?? []) as $item) {
        if (($item['doc_number'] ?? '') === $formalNumber && ($item['original_doc_number'] ?? '') === $originalNumber) {
            return $item;
        }
    }

    fwrite(STDERR, 'Packet not found: ' . $formalNumber . ' from ' . $originalNumber . PHP_EOL);
    exit(1);
}

$decisionFile = RecordFormReconstructionReviewService::forwardChainDecisionFilePath();
$decoded = json_decode((string)file_get_contents($decisionFile), true);
assert_true(is_array($decoded) && is_array($decoded['decisions'] ?? null), 'Forward-chain decision file is valid JSON');
assert_true(count($decoded['decisions']) >= 10, 'Forward-chain decision file covers direct inclusion records');

$review = RecordFormReconstructionReviewService::reviewAll(null, null, 'both');

$ir = packet_by_original($review, 'XZTC/BG-04-07', '待定-04-02');
assert_same('linked', $ir['forward_chain']['work_instruction']['status'], 'IR performance confirmation links a work instruction');
assert_same('XZTC/ZY-2-13-2018', $ir['forward_chain']['work_instruction']['doc_number'], 'IR performance confirmation links the IR work instruction');
assert_true(in_array('signature_chain', $ir['field_obligations'], true), 'IR performance confirmation requires signature chain coverage');

$standardFreshness = packet_by_original($review, 'XZTC/BG-24-03', '待定-24-01');
assert_true(in_array('XZTC/CX-22-2022', $standardFreshness['forward_chain']['procedure']['related'], true), 'Standard freshness report keeps CX-22 method-validity link');
assert_true(str_contains(implode(' ', $standardFreshness['external_register_boundaries']), 'qms_sources'), 'Standard freshness report keeps qms_sources handoff');
assert_true(in_array('external_register_boundary', $standardFreshness['field_obligations'], true), 'Standard freshness report requires external-register boundary coverage');

$equipmentUsage = packet_by_original($review, 'XZTC/BG-03-02', '待定-03-01');
assert_same('variant_by_equipment', $equipmentUsage['forward_chain']['work_instruction']['status'], 'Annual equipment usage bundle is a device-variant record');

$meeting = packet_by_original($review, 'XZTC/BG-13-01', '待定-13-01');
assert_same('not_applicable', $meeting['forward_chain']['work_instruction']['status'], 'Internal communication meeting record documents work-instruction non-applicability');

$manifest = RecordFormBatchTemplateService::manifest();
$manifestByOriginal = [];
foreach ($manifest as $row) {
    if (($row['original_doc_number'] ?? '') !== '') {
        $manifestByOriginal[$row['original_doc_number'] . '|' . $row['doc_number']] = $row;
    }
}

foreach ([
    '待定-01-01|XZTC/BG-01-10',
    '待定-01-02|XZTC/BG-01-11',
    '待定-03-01|XZTC/BG-03-02',
    '待定-04-02|XZTC/BG-04-07',
    '待定-13-01|XZTC/BG-13-01',
    '待定-16-01|XZTC/BG-16-01',
    '待定-20-01|XZTC/BG-20-09',
    '待定-20-04|XZTC/BG-20-10',
    '待定-24-01|XZTC/BG-24-03',
    '待定-33-01|XZTC/BG-33-01',
] as $identity) {
    assert_true(isset($manifestByOriginal[$identity]), 'Batch manifest uses direct-inclusion formal identity: ' . $identity);
}

$confidentiality = $manifestByOriginal['待定-01-01|XZTC/BG-01-10'];
assert_same(['party_name', 'department_or_role', 'confidential_scope', 'confidential_period', 'responsibilities', 'signatory', 'signed_date', 'archive_owner'], array_column($confidentiality['field_schema'], 'key'), 'Confidentiality agreement has fillable controlled-record fields');

$standardManifest = $manifestByOriginal['待定-24-01|XZTC/BG-24-03'];
assert_same(['check_trigger', 'check_date', 'checker', 'source_channel', 'standards', 'overall_conclusion', 'technical_reviewer', 'review_date'], array_column($standardManifest['field_schema'], 'key'), 'Standard freshness report has fillable source/status/conclusion fields');

echo "record_form_forward_chain_smoke passed\n";
