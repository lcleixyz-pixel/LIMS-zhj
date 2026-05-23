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

function equipment_sample_values(array $schema): array
{
    $values = [];
    foreach ($schema as $field) {
        $default = $field['default'] ?? '';
        if ($field['type'] === 'repeatable_table') {
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
$equipmentRows = [];
$pendingRows = [];
foreach ($manifest as $entry) {
    if (in_array($entry['module'], ['仪器设备管理程序', '仪器设备和标准物质期间核查程序'], true)) {
        if (str_starts_with($entry['doc_number'], 'XZTC/BG-03-') || str_starts_with($entry['doc_number'], 'XZTC/BG-04-')) {
            $equipmentRows[] = $entry;
        } else {
            $pendingRows[] = $entry;
        }
    }
}

assert_same(48, count($equipmentRows), 'Equipment and period-check numbered entries are formalized as the second batch');
assert_same(2, count($pendingRows), 'Directory-outside equipment attachments remain pending for manual review');

foreach ($equipmentRows as $entry) {
    assert_same('published', $entry['status'], $entry['doc_number'] . ' ' . $entry['name'] . ' is published');
    assert_same('completed', $entry['review_status'], $entry['doc_number'] . ' ' . $entry['name'] . ' is completed');

    $schema = RecordFormSchemaService::decode(RecordFormSchemaService::encode($entry['field_schema']));
    assert_same(array_column($entry['field_schema'], 'key'), array_column($schema, 'key'), $entry['print_template_key'] . ' schema round trips');
}

foreach ($pendingRows as $entry) {
    assert_same('draft', $entry['status'], $entry['doc_number'] . ' stays draft');
    assert_same('needs_fidelity', $entry['review_status'], $entry['doc_number'] . ' stays needs_fidelity');
}

$byPrintKey = [];
foreach ($equipmentRows as $entry) {
    $byPrintKey[$entry['print_template_key']] = $entry;
}

$expectedSchemas = [
    'rf_xztc_bg_03_01_1786e97624' => ['equipment_items'],
    'rf_xztc_bg_03_02_647643cad8' => ['equipment_name', 'equipment_code', 'usage_year', 'usage_items'],
    'rf_xztc_bg_03_04_9afe87fd58' => ['equipment_name', 'equipment_code', 'model_spec', 'purchase_date', 'failure_description', 'operator', 'operation_date', 'repair_method_cost', 'inspector', 'inspection_date', 'technical_manager_opinion', 'technical_manager_date', 'lab_director_approval', 'lab_director_date'],
    'rf_xztc_bg_03_08_038ec7b048' => ['equipment_name', 'equipment_code', 'supplier_name', 'contract_number', 'model_spec', 'manufacture_date', 'received_date', 'started_date', 'storage_location', 'manual_number', 'received_status', 'maintenance_method', 'calibration_method', 'calibration_items'],
    'rf_xztc_bg_04_01_b5c6503f71' => ['plan_items', 'prepared_by', 'prepared_date', 'approved_by', 'approved_date'],
    'rf_xztc_bg_04_02_543a179a95' => ['checked_object', 'team_leader', 'team_members', 'check_time', 'check_place', 'execution_files', 'calibration_or_validity_period', 'prepared_by', 'prepared_date', 'approved_by', 'approved_date'],
    'rf_xztc_bg_04_03_97e1588ba9' => ['equipment_name', 'model_spec', 'equipment_code', 'check_basis', 'check_resources', 'check_personnel', 'process_record', 'recorder', 'record_date', 'result_judgement', 'checkers', 'check_date', 'reviewer_opinion', 'reviewer', 'review_date'],
    'rf_xztc_bg_04_05_6729ed2c60' => ['equipment_name', 'model_spec', 'equipment_code', 'check_basis', 'check_resources', 'check_personnel', 'process_record', 'recorder', 'record_date', 'function_result', 'checkers', 'check_date', 'reviewer_opinion', 'reviewer', 'review_date'],
    'rf_xztc_bg_04_06_01d9f5d025' => ['equipment_name', 'model_spec', 'equipment_code', 'check_basis', 'check_items', 'check_personnel', 'check_standard', 'result_judgement', 'responsible_person', 'responsible_date', 'evaluation', 'evaluation_responsible_person', 'evaluation_date', 'reviewer_opinion', 'reviewer', 'review_date'],
];

foreach ($expectedSchemas as $printKey => $keys) {
    $entry = $byPrintKey[$printKey] ?? null;
    if ($entry === null) {
        fwrite(STDERR, 'Missing expected formal equipment template: ' . $printKey . PHP_EOL);
        exit(1);
    }

    assert_same($keys, array_column($entry['field_schema'], 'key'), $printKey . ' field keys');
}

$renderChecks = [
    'rf_xztc_bg_03_01_1786e97624' => ['仪器设备台账', '扩展不确定度/最大允差/准确度等级', '设备编号样例'],
    'rf_xztc_bg_03_04_9afe87fd58' => ['仪器设备维修申报表', '故障描述', '技术负责人审核意见'],
    'rf_xztc_bg_03_08_038ec7b048' => ['仪器设备履历表', '接收状态', '校准/检定日期'],
    'rf_xztc_bg_04_03_97e1588ba9' => ['仪器设备和标准物质期间核查记录表', '电子天平', 'XZTC-TP02'],
    'rf_xztc_bg_04_05_6729ed2c60' => ['仪器设备功能性核查记录表', '偏光镜', '功能性核查结果'],
    'rf_xztc_bg_04_06_01d9f5d025' => ['仪器设备和标准物质期间核查报告', '电子天平', '期间核查评价'],
];

foreach ($renderChecks as $printKey => $needles) {
    $entry = $byPrintKey[$printKey];
    $html = RecordFormPrintService::render($printKey, $entry, equipment_sample_values($entry['field_schema']));

    assert_not_contains('高保真重构草稿', $html, $printKey . ' formal print has no draft watermark');
    assert_contains('break-inside: avoid', $html, $printKey . ' protects rows from splitting across pages');
    foreach ($needles as $needle) {
        assert_contains($needle, $html, $printKey . ' print includes expected text');
    }
}

echo "record_forms_equipment_fidelity_smoke passed\n";
