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

if (!function_exists('public_path')) {
    function public_path(): string
    {
        return dirname(__DIR__) . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR;
    }
}

use app\controller\RecordFormInstance;
use app\model\RecordFormTemplate;
use app\service\PdfRenderService;
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

function assert_not_contains(string $needle, string $haystack, string $message): void
{
    if (str_contains($haystack, $needle)) {
        fwrite(STDERR, $message . PHP_EOL);
        fwrite(STDERR, 'Unexpected: ' . $needle . PHP_EOL);
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

function make_controller(array $postedFields, array $params = []): RecordFormInstance
{
    $controller = (new ReflectionClass(RecordFormInstance::class))->newInstanceWithoutConstructor();
    $request = new class($postedFields, $params) {
        public function __construct(private array $postedFields, private array $params)
        {
        }

        public function post(string $name = '', mixed $default = null): mixed
        {
            return $name === 'fields/a' ? $this->postedFields : $default;
        }

        public function param(string $name = '', mixed $default = null): mixed
        {
            return $this->params[$name] ?? $default;
        }
    };

    $property = new ReflectionProperty(RecordFormInstance::class, 'request');
    $property->setValue($controller, $request);

    return $controller;
}

function invoke_private(object $object, string $method, array $args = []): mixed
{
    $reflection = new ReflectionMethod($object, $method);

    return $reflection->invokeArgs($object, $args);
}

putenv('RECORD_FORM_PDF_TOKEN_SECRET');

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

assert_throws_http(
    fn () => invoke_private(make_controller([]), 'pdfToken', ['record-1', time() + 300]),
    500,
    'PDF 签名密钥未配置',
    'PDF token generation fails closed when signing secret is missing'
);

putenv('RECORD_FORM_PDF_TOKEN_SECRET=record-form-smoke-secret-32-chars-ok');

$expires = time() + 300;
$token = invoke_private(make_controller([]), 'pdfToken', ['record-1', $expires]);
assert_same(64, strlen($token), 'PDF token uses a SHA-256 HMAC hex digest');

assert_throws_http(
    fn () => make_controller([], ['id' => 'record-1', 'expires' => (string)$expires])->internalPrint(),
    403,
    '打印链接无效',
    'Internal print rejects missing token before loading a record'
);

assert_throws_http(
    fn () => make_controller([], [
        'id' => 'record-1',
        'expires' => (string)(time() - 1),
        'token' => $token,
    ])->internalPrint(),
    403,
    '打印链接已过期',
    'Internal print rejects expired token before loading a record'
);

assert_throws_http(
    fn () => make_controller([], [
        'id' => 'record-1',
        'expires' => (string)$expires,
        'token' => str_repeat('0', 64),
    ])->internalPrint(),
    403,
    '打印链接无效',
    'Internal print rejects mismatched token before loading a record'
);

$summary = invoke_private(
    (new ReflectionClass(PdfRenderService::class))->newInstanceWithoutConstructor(),
    'summarizeRenderError',
    [root_path() . 'scripts/render-record-pdf.mjs failed at ' . public_path() . 'uploads/file.pdf']
);
assert_not_contains(root_path(), $summary, 'PDF render error summary hides app root');
assert_not_contains(public_path(), $summary, 'PDF render error summary hides public root');
assert_contains('[app-root]', $summary, 'PDF render error summary keeps app-root placeholder');
assert_contains('[public-root]', $summary, 'PDF render error summary keeps public-root placeholder');

echo "record_forms_instance_smoke passed\n";
