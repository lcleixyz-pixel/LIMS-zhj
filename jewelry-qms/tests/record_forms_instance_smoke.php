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
use app\model\RecordFormInstance as InstanceModel;
use app\model\RecordFormTemplate;
use app\service\PdfRenderService;
use app\service\RecordFormSchemaService;
use think\exception\HttpException;

class SmokeRecordFormTemplate extends RecordFormTemplate
{
    public string $field_schema = '';

    public function __construct()
    {
    }
}

class SmokeRecordFormInstance extends InstanceModel
{
    public string $id = '';
    public string $template_id = '';
    public string $doc_number = '';
    public string $record_title = '';
    public string $status = '';
    public string $template_name = '';
    public string $template_module = '';
    public string $template_version = '';
    public string $template_print_template_key = '';
    public string $template_field_schema = '';

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

function method_source(string $className, string $methodName): string
{
    $method = new ReflectionMethod($className, $methodName);
    $fileName = $method->getFileName();
    if ($fileName === false) {
        fwrite(STDERR, 'Unable to locate source for ' . $className . '::' . $methodName . PHP_EOL);
        exit(1);
    }

    $lines = file($fileName);
    if ($lines === false) {
        fwrite(STDERR, 'Unable to read source file: ' . $fileName . PHP_EOL);
        exit(1);
    }

    return implode('', array_slice($lines, $method->getStartLine() - 1, $method->getEndLine() - $method->getStartLine() + 1));
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

$snapshotRecord = new SmokeRecordFormInstance();
$snapshotRecord->id = 'record-1';
$snapshotRecord->template_id = 'template-1';
$snapshotRecord->doc_number = 'XZTC/BG-01-02';
$snapshotRecord->record_title = '已填人员培训记录';
$snapshotRecord->template_name = '人员培训记录表快照';
$snapshotRecord->template_module = '人员培训程序';
$snapshotRecord->template_version = 'A/0';
$snapshotRecord->template_print_template_key = 'training_record';
$snapshotRecord->template_field_schema = RecordFormSchemaService::encode([
    ['key' => 'training_date', 'label' => '培训日期', 'type' => 'date'],
]);
$snapshot = invoke_private($controller, 'templateForRecord', [$snapshotRecord]);
assert_same('人员培训记录表快照', $snapshot['name'], 'Record uses stored template snapshot name');
assert_same('training_record', $snapshot['print_template_key'], 'Record uses stored print template snapshot');
assert_same('培训日期', invoke_private($controller, 'decodeSchema', [$snapshot])[0]['label'], 'Stored template snapshot schema is decodable');

$templateForRecordSource = method_source(RecordFormInstance::class, 'templateForRecord');
assert_contains('hasTemplateSnapshot', $templateForRecordSource, 'Record template lookup prefers stored snapshots');
assert_contains('backfillTemplateSnapshot', $templateForRecordSource, 'Record template lookup backfills legacy records instead of silently staying live-bound');

$backfillSource = method_source(RecordFormInstance::class, 'backfillTemplateSnapshot');
assert_contains('记录缺少模板快照', $backfillSource, 'Missing snapshot without a template is a controlled manual-repair error');
assert_contains("'template_field_schema' =>", $backfillSource, 'Backfill persists the template schema snapshot');

$lockedRecord = new SmokeRecordFormInstance();
$lockedRecord->status = 'locked';
assert_same(false, invoke_private($controller, 'canExportPdf', [$lockedRecord]), 'Locked records cannot export PDF');
$voidedRecord = new SmokeRecordFormInstance();
$voidedRecord->status = 'voided';
assert_same(false, invoke_private($controller, 'canExportPdf', [$voidedRecord]), 'Voided records cannot export PDF');
$draftRecord = new SmokeRecordFormInstance();
$draftRecord->status = 'draft';
assert_same(true, invoke_private($controller, 'canExportPdf', [$draftRecord]), 'Draft records can export PDF');

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

$exportPdfSource = method_source(RecordFormInstance::class, 'exportPdf');
assert_contains('consumePdfActionToken', $exportPdfSource, 'PDF export requires a one-time action token');
assert_contains('canExportPdf($record)', $exportPdfSource, 'PDF export checks lifecycle status');
assert_contains('renderPrintHtml($record)', $exportPdfSource, 'PDF export renders print HTML in-process');
assert_contains('PdfRenderService::renderHtml', $exportPdfSource, 'PDF export renders from local HTML');
assert_not_contains('internalPrint', $exportPdfSource, 'PDF export avoids nested internalPrint HTTP requests');
assert_not_contains('PdfRenderService::renderUrl', $exportPdfSource, 'PDF export avoids URL rendering under the built-in server');

$createSource = method_source(RecordFormInstance::class, 'create');
assert_contains('findTemplate(true)', $createSource, 'Record creation requires a published template');
assert_contains('template_field_schema', $createSource, 'Record creation stores a template schema snapshot');
assert_contains('template_print_template_key', $createSource, 'Record creation stores a print template snapshot');

$editSource = method_source(RecordFormInstance::class, 'edit');
assert_contains("'generated_pdf_path' => null", $editSource, 'Editing a generated record clears stale PDF path');
assert_contains("'generated_pdf_name' => null", $editSource, 'Editing a generated record clears stale PDF name');

$routeSource = file_get_contents(dirname(__DIR__) . '/route/app.php');
assert_contains("Route::post('record_form_instance/exportPdf'", $routeSource ?: '', 'PDF export route is POST-only');
assert_not_contains("Route::get('record_form_instance/exportPdf'", $routeSource ?: '', 'PDF export route is no longer GET');
assert_not_contains('internalPrint', $routeSource ?: '', 'Unused internal print route is not exposed');

$migrationSource = file_get_contents(dirname(__DIR__) . '/database/migrations/20260522_record_form_instance_snapshots.sql');
assert_contains('information_schema.COLUMNS', $migrationSource ?: '', 'Snapshot migration checks existing columns before altering');
assert_contains('ADD COLUMN `template_field_schema`', $migrationSource ?: '', 'Snapshot migration adds schema snapshot column');
assert_contains('UPDATE `record_form_instances` AS r', $migrationSource ?: '', 'Snapshot migration backfills existing instances');

$auditSource = file_get_contents(dirname(__DIR__) . '/app/middleware/AuditLog.php');
assert_contains("'create'", $auditSource ?: '', 'Audit log tracks record creation POST actions');
assert_contains("'exportpdf'", $auditSource ?: '', 'Audit log tracks PDF export POST actions');
assert_contains("'seedsamples'", $auditSource ?: '', 'Audit log tracks sample template seeding');
assert_contains('resolveRecordId', $auditSource ?: '', 'Audit log resolves record ids from redirects');
assert_contains("getHeader('Location')", $auditSource ?: '', 'Audit log can capture created record id from redirect location');

$templateControllerSource = file_get_contents(dirname(__DIR__) . '/app/controller/RecordFormTemplate.php');
assert_contains('printTemplateExists', $templateControllerSource ?: '', 'Published templates require an existing print template');
assert_contains('发布模板前必须配置可用的打印模板键', $templateControllerSource ?: '', 'Missing print template receives a diagnostic validation message');

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
