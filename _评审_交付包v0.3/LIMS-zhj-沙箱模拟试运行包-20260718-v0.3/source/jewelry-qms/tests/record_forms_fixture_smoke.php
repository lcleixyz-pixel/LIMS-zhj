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

function fixture_sample_values(array $schema): array
{
    $values = [];
    foreach ($schema as $field) {
        if ($field['type'] === 'repeatable_table') {
            $row = [];
            foreach (($field['columns'] ?? []) as $column) {
                $row[$column['key']] = match ($column['type']) {
                    'date' => '2026-05-22',
                    'number' => '1',
                    'checkbox' => '1',
                    'select' => (string)($column['options'][0] ?? ''),
                    default => $column['label'] . '样例',
                };
            }
            $values[$field['key']] = [$row];
            continue;
        }

        $values[$field['key']] = match ($field['type']) {
            'date' => '2026-05-22',
            'number' => '1',
            'checkbox' => '1',
            'select' => (string)($field['options'][0] ?? ''),
            default => $field['label'] . '样例',
        };
    }

    return $values;
}

$templates = RecordFormFixtureService::templates();
assert_same(9, count($templates), 'Fixture service exposes the formal personnel templates');

$expectedKeys = [
    'rf_xztc_bg_01_01_5325a1b0bd',
    'training_record',
    'rf_xztc_bg_01_03_5fa5a364df',
    'rf_xztc_bg_01_04_5fb52565ba',
    'rf_xztc_bg_01_05_66b005b382',
    'rf_xztc_bg_01_06_f268e9aaf1',
    'rf_xztc_bg_01_07_a0956d356f',
    'rf_xztc_bg_01_08_6fcb518418',
    'rf_xztc_bg_01_09_5f54bbf750',
];
assert_same($expectedKeys, array_column($templates, 'print_template_key'), 'Fixture templates preserve formal print keys');

foreach ($templates as $template) {
    assert_same('completed', $template['review_status'] ?? '', 'Formal fixture templates are completed');
    $encoded = RecordFormSchemaService::encode($template['field_schema']);
    $decoded = RecordFormSchemaService::decode($encoded);
    assert_same(RecordFormSchemaService::normalize($template['field_schema']), $decoded, 'Fixture schema round-trips for ' . $template['doc_number']);

    $html = RecordFormPrintService::render(
        $template['print_template_key'],
        $template,
        fixture_sample_values($template['field_schema'])
    );
    assert_contains($template['name'], $html, 'Rendered HTML includes template name for ' . $template['print_template_key']);
    assert_contains($template['doc_number'], $html, 'Rendered HTML includes doc number for ' . $template['print_template_key']);
}

echo "record_forms_fixture_smoke passed\n";
