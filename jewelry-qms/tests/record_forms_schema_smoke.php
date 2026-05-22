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

function assert_throws(callable $callback, string $expectedClass, string $message): void
{
    try {
        $callback();
    } catch (Throwable $exception) {
        if ($exception instanceof $expectedClass) {
            return;
        }

        fwrite(STDERR, $message . PHP_EOL);
        fwrite(STDERR, 'Expected exception: ' . $expectedClass . PHP_EOL);
        fwrite(STDERR, 'Actual exception: ' . get_class($exception) . PHP_EOL);
        fwrite(STDERR, 'Actual message: ' . $exception->getMessage() . PHP_EOL);
        exit(1);
    }

    fwrite(STDERR, $message . PHP_EOL);
    fwrite(STDERR, 'Expected exception: ' . $expectedClass . PHP_EOL);
    fwrite(STDERR, 'Actual: no exception thrown' . PHP_EOL);
    exit(1);
}

$schema = [
    [
        'key' => 'training_date',
        'label' => '培训日期',
        'type' => 'date',
        'required' => true,
        'options' => ['上午', '下午'],
        'print_bind' => 'training.date',
        'validation' => ['format' => 'Y-m-d'],
        'help_text' => '请选择培训日期',
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
assert_same(['上午', '下午'], $normalized[0]['options'], 'Keeps options');
assert_same('training.date', $normalized[0]['print_bind'], 'Keeps print bind');
assert_same(['format' => 'Y-m-d'], $normalized[0]['validation'], 'Keeps validation rules');
assert_same('请选择培训日期', $normalized[0]['help_text'], 'Keeps help text');
assert_same('repeatable_table', $normalized[1]['type'], 'Keeps repeatable table type');
assert_same([], $normalized[1]['options'], 'Adds default options');
assert_same('attendees', $normalized[1]['print_bind'], 'Adds default print bind');
assert_same([], $normalized[1]['validation'], 'Adds default validation');
assert_same('', $normalized[1]['help_text'], 'Adds default help text');
assert_same('姓名', $normalized[1]['columns'][0]['label'], 'Keeps nested column label');

$encoded = RecordFormSchemaService::encode($schema);
$decoded = RecordFormSchemaService::decode($encoded);
assert_same($normalized, $decoded, 'Encode and decode round-trip normalized schema');
assert_same([], RecordFormSchemaService::decode(null), 'Decodes null schema as empty');
assert_same([], RecordFormSchemaService::decode('  '), 'Decodes blank schema as empty');

$supportedTypesSchema = [
    ['key' => 'text_field', 'label' => '文本', 'type' => 'text'],
    ['key' => 'textarea_field', 'label' => '多行文本', 'type' => 'textarea'],
    ['key' => 'date_field', 'label' => '日期', 'type' => 'date'],
    ['key' => 'number_field', 'label' => '数字', 'type' => 'number'],
    ['key' => 'select_field', 'label' => '选项', 'type' => 'select'],
    ['key' => 'checkbox_field', 'label' => '勾选', 'type' => 'checkbox'],
    ['key' => 'person_field', 'label' => '人员', 'type' => 'person'],
    ['key' => 'department_field', 'label' => '部门', 'type' => 'department'],
    ['key' => 'signature_field', 'label' => '签名', 'type' => 'signature'],
    [
        'key' => 'table_field',
        'label' => '明细',
        'type' => 'repeatable_table',
        'columns' => [
            ['key' => 'column_text', 'label' => '列文本', 'type' => 'text'],
        ],
    ],
];
$supportedTypes = array_column(RecordFormSchemaService::normalize($supportedTypesSchema), 'type');
assert_same([
    'text',
    'textarea',
    'date',
    'number',
    'select',
    'checkbox',
    'person',
    'department',
    'signature',
    'repeatable_table',
], $supportedTypes, 'Normalizes all supported field types');

assert_throws(
    fn () => RecordFormSchemaService::decode('{bad json'),
    InvalidArgumentException::class,
    'Rejects invalid JSON'
);
assert_throws(
    fn () => RecordFormSchemaService::decode('{"key":"training_date","label":"培训日期"}'),
    InvalidArgumentException::class,
    'Rejects JSON object root'
);
assert_throws(
    fn () => RecordFormSchemaService::decode('["not a field"]'),
    InvalidArgumentException::class,
    'Rejects non-object field items'
);
assert_throws(
    fn () => RecordFormSchemaService::decode('[{"key":"attendees","label":"参训人员","type":"repeatable_table","columns":"not columns"}]'),
    InvalidArgumentException::class,
    'Rejects non-array repeatable table columns'
);
assert_throws(
    fn () => RecordFormSchemaService::normalize([['key' => ' ', 'label' => '空 key']]),
    InvalidArgumentException::class,
    'Rejects blank key'
);
assert_throws(
    fn () => RecordFormSchemaService::normalize([['key' => 'blank_label', 'label' => ' ']]),
    InvalidArgumentException::class,
    'Rejects blank label'
);
assert_throws(
    fn () => RecordFormSchemaService::normalize([['key' => 'bad_type', 'label' => '坏类型', 'type' => 'file']]),
    InvalidArgumentException::class,
    'Rejects unsupported type'
);
assert_throws(
    fn () => RecordFormSchemaService::encode([['key' => 'nan_default', 'label' => '非法默认值', 'default' => NAN]]),
    InvalidArgumentException::class,
    'Reports JSON encode failure explicitly'
);

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
