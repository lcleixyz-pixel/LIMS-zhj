<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../app/common.php';

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

$schema = [
    [
        'key' => 'training_date',
        'label' => '培训日期',
        'type' => 'date',
        'required' => true,
    ],
    [
        'key' => 'attendees',
        'label' => '参训人员',
        'type' => 'repeatable_table',
        'columns' => [
            ['key' => 'name', 'label' => '姓名', 'type' => 'text', 'required' => true],
            ['key' => 'signature', 'label' => '签名', 'type' => 'signature', 'required' => false],
        ],
    ],
];

$normalized = RecordFormSchemaService::normalize($schema);
assert_same('training_date', $normalized[0]['key'], 'Keeps field key');
assert_same('date', $normalized[0]['type'], 'Keeps field type');
assert_same(true, $normalized[0]['required'], 'Keeps required flag');
assert_same('', $normalized[0]['default'], 'Adds default value');
assert_same('repeatable_table', $normalized[1]['type'], 'Keeps repeatable table type');
assert_same('姓名', $normalized[1]['columns'][0]['label'], 'Keeps nested column label');

$values = [
    'training_date' => '2026-05-22',
    'attendees' => [
        ['name' => '张三', 'signature' => '张三'],
    ],
];
$errors = RecordFormSchemaService::validateValues($normalized, $values);
assert_same([], $errors, 'Accepts valid values');

$badValues = [
    'training_date' => '',
    'attendees' => [
        ['name' => '', 'signature' => ''],
    ],
];
$badErrors = RecordFormSchemaService::validateValues($normalized, $badValues);
assert_same('培训日期不能为空', $badErrors['training_date'], 'Reports missing required date');
assert_same('参训人员第1行姓名不能为空', $badErrors['attendees.0.name'], 'Reports missing required table cell');

echo "record_forms_schema_smoke passed\n";
