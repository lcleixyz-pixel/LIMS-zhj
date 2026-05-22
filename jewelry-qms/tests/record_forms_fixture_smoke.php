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

use app\service\RecordFormFixtureService;
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

function sample_values(string $printTemplateKey): array
{
    return match ($printTemplateKey) {
        'training_record' => [
            'training_date' => '2026-05-22',
            'training_topic' => '记录表格填写要求',
            'trainer' => '质量负责人',
            'training_content' => '模板 smoke',
            'attendees' => [['name' => '张三', 'department' => '检测室', 'signature' => '张三']],
            'effect_evaluation' => '符合要求',
        ],
        'periodic_check' => [
            'equipment_name' => '电子天平',
            'equipment_code' => 'EQ-001',
            'check_date' => '2026-05-22',
            'check_items' => [['item' => '示值误差', 'method' => '标准砝码', 'result' => '0.01g', 'conclusion' => '合格']],
            'checker' => '设备管理员',
        ],
        'audit_checklist' => [
            'audit_date' => '2026-05-22',
            'audited_department' => '检测室',
            'auditor' => '内审员',
            'check_items' => [['clause' => '6.2', 'requirement' => '人员能力', 'evidence' => '培训记录', 'result' => '符合']],
        ],
        'management_review_plan' => [
            'review_year' => '2026',
            'meeting_date' => '2026-05-22',
            'host' => '最高管理者',
            'participants' => '质量负责人',
            'inputs' => [['topic' => '质量目标', 'owner' => '质量负责人', 'material' => '年度统计']],
        ],
        'quality_control_record' => [
            'monitor_date' => '2026-05-22',
            'monitor_type' => '留样再测',
            'sample_info' => '样品 A',
            'results' => [['item' => '含量', 'expected' => '1.00', 'actual' => '1.01', 'judgement' => '满意']],
            'follow_up' => '持续监控',
        ],
    };
}

$templates = RecordFormFixtureService::templates();
assert_same(5, count($templates), 'Fixture service exposes exactly five templates');

$expectedKeys = [
    'training_record',
    'periodic_check',
    'audit_checklist',
    'management_review_plan',
    'quality_control_record',
];
assert_same($expectedKeys, array_column($templates, 'print_template_key'), 'Fixture templates preserve planned print keys');

foreach ($templates as $template) {
    $encoded = RecordFormSchemaService::encode($template['field_schema']);
    $decoded = RecordFormSchemaService::decode($encoded);
    assert_same(RecordFormSchemaService::normalize($template['field_schema']), $decoded, 'Fixture schema round-trips for ' . $template['doc_number']);

    $html = RecordFormPrintService::render(
        $template['print_template_key'],
        $template,
        sample_values($template['print_template_key'])
    );
    assert_contains($template['name'], $html, 'Rendered HTML includes template name for ' . $template['print_template_key']);
    assert_contains($template['doc_number'], $html, 'Rendered HTML includes doc number for ' . $template['print_template_key']);
}

echo "record_forms_fixture_smoke passed\n";
