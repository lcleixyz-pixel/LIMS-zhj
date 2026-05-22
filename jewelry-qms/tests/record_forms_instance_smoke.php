<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../app/common.php';

use app\controller\RecordFormInstance;
use app\model\RecordFormTemplate;
use think\exception\HttpException;

class SmokeRecordFormTemplate extends RecordFormTemplate
{
    public string $field_schema = '';

    public function __construct()
    {
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
        fwrite(STDERR, 'Actual: ' . $haystack . PHP_EOL);
        exit(1);
    }
}

function assert_throws_http(callable $callback, int $expectedStatus, string $expectedMessage, string $message): void
{
    try {
        $callback();
    } catch (Throwable $exception) {
        if (!$exception instanceof HttpException) {
            fwrite(STDERR, $message . PHP_EOL);
            fwrite(STDERR, 'Expected exception: ' . HttpException::class . PHP_EOL);
            fwrite(STDERR, 'Actual exception: ' . get_class($exception) . PHP_EOL);
            fwrite(STDERR, 'Actual message: ' . $exception->getMessage() . PHP_EOL);
            exit(1);
        }

        assert_same($expectedStatus, $exception->getStatusCode(), $message . ' status');
        assert_contains($expectedMessage, $exception->getMessage(), $message . ' message');
        return;
    }

    fwrite(STDERR, $message . PHP_EOL);
    fwrite(STDERR, 'Expected exception: ' . HttpException::class . PHP_EOL);
    fwrite(STDERR, 'Actual: no exception thrown' . PHP_EOL);
    exit(1);
}

function make_controller(array $postedFields): RecordFormInstance
{
    $controller = (new ReflectionClass(RecordFormInstance::class))->newInstanceWithoutConstructor();
    $request = new class($postedFields) {
        public function __construct(private array $postedFields)
        {
        }

        public function post(string $name = '', mixed $default = null): mixed
        {
            return $name === 'fields/a' ? $this->postedFields : $default;
        }
    };

    $property = new ReflectionProperty(RecordFormInstance::class, 'request');
    $property->setValue($controller, $request);

    return $controller;
}

function invoke_private(RecordFormInstance $controller, string $method, array $args = []): mixed
{
    $reflection = new ReflectionMethod($controller, $method);

    return $reflection->invokeArgs($controller, $args);
}

$controller = make_controller([]);

$template = new SmokeRecordFormTemplate();
$template->field_schema = '{bad json';
assert_throws_http(
    fn () => invoke_private($controller, 'decodeSchema', [$template]),
    422,
    '记录表格字段配置错误：',
    'Bad schema is reported as a controlled client-visible error'
);

assert_throws_http(
    fn () => invoke_private($controller, 'decodeValues', ['{bad json']),
    422,
    '记录字段值损坏：',
    'Bad stored field values are reported as a controlled error'
);
assert_same([], invoke_private($controller, 'decodeValues', [null]), 'Null field values decode as empty');
assert_same([], invoke_private($controller, 'decodeValues', ['  ']), 'Blank field values decode as empty');

$schema = [
    ['key' => 'accepted', 'label' => '确认', 'type' => 'checkbox'],
    ['key' => 'title', 'label' => '标题', 'type' => 'text'],
    [
        'key' => 'rows',
        'label' => '明细',
        'type' => 'repeatable_table',
        'columns' => [
            ['key' => 'name', 'label' => '名称', 'type' => 'text'],
        ],
    ],
];
$postedFields = [
    'accepted' => ['unexpected'],
    'title' => ['unexpected'],
    'rows' => [
        ['name' => ''],
        ['name' => '有效行'],
    ],
];
$values = invoke_private(make_controller($postedFields), 'collectValues', [$schema]);

assert_same('1', $values['accepted'], 'Checkbox array input is normalized to checked');
assert_same('', $values['title'], 'Scalar field array input is not persisted as an array');
assert_same([['name' => '有效行']], $values['rows'], 'Repeatable table still filters empty rows');

echo "record_forms_instance_smoke passed\n";
