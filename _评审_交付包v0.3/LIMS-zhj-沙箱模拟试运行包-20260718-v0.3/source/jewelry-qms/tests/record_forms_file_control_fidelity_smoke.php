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
use app\service\RecordFormPrintService;
use app\service\RecordFormSchemaService;

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

function assert_not_contains(string $needle, string $haystack, string $message): void
{
    if (str_contains($haystack, $needle)) {
        fwrite(STDERR, $message . PHP_EOL);
        fwrite(STDERR, 'Unexpected: ' . $needle . PHP_EOL);
        exit(1);
    }
}

function file_control_sample_values(array $schema): array
{
    $values = [];
    foreach ($schema as $field) {
        $default = $field['default'] ?? '';
        if (($field['type'] ?? '') === 'repeatable_table') {
            $row = [];
            foreach (($field['columns'] ?? []) as $column) {
                $columnDefault = $column['default'] ?? '';
                $row[$column['key']] = $columnDefault !== '' ? $columnDefault : match ($column['type']) {
                    'date' => '2026-05-23',
                    'number' => '1',
                    'checkbox' => '1',
                    'select' => (string)($column['options'][0] ?? ''),
                    default => $column['label'] . '样例',
                };
            }
            $values[$field['key']] = [$row];
            continue;
        }

        $values[$field['key']] = $default !== '' ? $default : match ($field['type']) {
            'date' => '2026-05-23',
            'number' => '1',
            'checkbox' => '1',
            'select' => (string)($field['options'][0] ?? ''),
            default => $field['label'] . '样例',
        };
    }

    return $values;
}

$manifest = RecordFormBatchTemplateService::manifest();
$fileControlRows = array_values(array_filter(
    $manifest,
    static fn (array $entry): bool => $entry['module'] === '文件控制程序'
));

assert_same(9, count($fileControlRows), 'File control module keeps the nine current 08-series forms');

$byPrintKey = [];
foreach ($fileControlRows as $entry) {
    assert_same('published', $entry['status'], $entry['doc_number'] . ' ' . $entry['name'] . ' is published');
    assert_same('completed', $entry['review_status'], $entry['doc_number'] . ' ' . $entry['name'] . ' is completed');

    $schema = RecordFormSchemaService::decode(RecordFormSchemaService::encode($entry['field_schema']));
    assert_same(array_column($entry['field_schema'], 'key'), array_column($schema, 'key'), $entry['print_template_key'] . ' schema round trips');
    $byPrintKey[$entry['print_template_key']] = $entry;
}

$expectedSchemas = [
    'rf_xztc_bg_08_01_a2f0e40186' => ['controlled_file_items'],
    'rf_xztc_bg_08_02_2dcbcefe6b' => ['external_file_items'],
    'rf_xztc_bg_08_03_dac7aff278' => ['distribution_items'],
    'rf_xztc_bg_08_04_db3e4e4c37' => ['borrow_items'],
    'rf_xztc_bg_08_05_6c6daa8aea' => ['document_name', 'document_code', 'distribution_number', 'applicant', 'quantity', 'application_reason', 'application_date', 'approval_opinion', 'quality_manager', 'approval_date'],
    'rf_xztc_bg_08_06_ae24ae756b' => ['document_name', 'document_code', 'applicant', 'proposed_date', 'reason_customer_need', 'reason_law_requirement', 'reason_external_audit', 'reason_management_review', 'reason_system_improvement', 'before_content', 'after_content', 'review_opinion', 'reviewer', 'review_date', 'approval_opinion', 'approver', 'approval_date'],
    'rf_xztc_bg_08_07_650e085f47' => ['document_name', 'distribution_number', 'destruction_reason', 'applicant', 'application_date', 'approval_opinion', 'approver', 'approval_date', 'destroy_date', 'destroyer', 'copy_count', 'supervisor'],
    'rf_xztc_bg_08_08_4ef143977a' => ['meeting_topic', 'meeting_time', 'meeting_place', 'attendees', 'meeting_content', 'recorder'],
    'rf_xztc_bg_08_09_7e3e2d39ed' => ['test_date', 'sample_number', 'total_mass', 'density', 'refractive_index', 'magnification', 'pleochroism', 'optical_character', 'uv_fluorescence', 'absorption_spectrum', 'test_conclusion', 'tester', 'recorder', 'verifier'],
];

foreach ($expectedSchemas as $printKey => $keys) {
    $entry = $byPrintKey[$printKey] ?? null;
    if ($entry === null) {
        fwrite(STDERR, 'Missing expected formal file-control template: ' . $printKey . PHP_EOL);
        exit(1);
    }

    assert_same($keys, array_column($entry['field_schema'], 'key'), $printKey . ' field keys');
}

$renderChecks = [
    'rf_xztc_bg_08_01_a2f0e40186' => ['内部受控文件登记表', '文件控制编号', '质量记录和技术记录'],
    'rf_xztc_bg_08_03_dac7aff278' => ['文件发放回收登记表', '发放编号', '发放人、签收人、发放日期'],
    'rf_xztc_bg_08_05_6c6daa8aea' => ['体系文件置换申请表', '申请理由', '批准人（质量负责人）'],
    'rf_xztc_bg_08_06_ae24ae756b' => ['体系文件更改审批表', '客户需求', '修改前内容'],
    'rf_xztc_bg_08_08_4ef143977a' => ['会议签到记录表', '会议主题', '会议内容'],
    'rf_xztc_bg_08_09_7e3e2d39ed' => ['样品检测原始记录', '样品编号', '检测结论'],
];

foreach ($renderChecks as $printKey => $needles) {
    $entry = $byPrintKey[$printKey];
    $html = RecordFormPrintService::render($printKey, $entry, file_control_sample_values($entry['field_schema']));

    assert_not_contains('高保真重构草稿', $html, $printKey . ' formal print has no draft watermark');
    assert_contains('break-inside: avoid', $html, $printKey . ' protects rows from splitting across pages');
    foreach ($needles as $needle) {
        assert_contains($needle, $html, $printKey . ' print includes expected text');
    }
}

echo "record_forms_file_control_fidelity_smoke passed\n";
