<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../app/common.php';

$app = new think\App();
$app->initialize();

use app\model\RecordFormTemplate;
use app\service\RecordFormInstanceTitleService;
use think\facade\Config;
use think\facade\Db;

function assert_true(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

function assert_same(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        fwrite(STDERR, $message . PHP_EOL);
        fwrite(STDERR, 'Expected: ' . var_export($expected, true) . PHP_EOL);
        fwrite(STDERR, 'Actual: ' . var_export($actual, true) . PHP_EOL);
        exit(1);
    }
}

$template = RecordFormTemplate::where('doc_number', 'XZTC/BG-01-02')
    ->where('soft_delete', 0)
    ->where('status', 'published')
    ->order('created', 'asc')
    ->find();
assert_true((bool)$template, 'Published training record template exists');

$ids = [];
$companyId = (string)Config::get('qms.company_id');
$baseTitle = '2099运行记录-' . (string)$template->doc_number . '-' . (string)$template->name;
try {
    foreach ([
        ['title' => $baseTitle, 'status' => 'draft'],
        ['title' => $baseTitle . '-002', 'status' => 'generated'],
        ['title' => $baseTitle . '-008', 'status' => 'voided'],
    ] as $row) {
        $id = qms_uuid();
        $ids[] = $id;
        Db::name('record_form_instances')->insert([
            'id' => $id,
            'company_id' => $companyId,
            'template_id' => (string)$template->id,
            'template_name' => (string)$template->name,
            'template_module' => (string)$template->module,
            'template_version' => (string)$template->version,
            'template_print_template_key' => (string)$template->print_template_key,
            'template_field_schema' => (string)$template->field_schema,
            'doc_number' => (string)$template->doc_number,
            'record_title' => $row['title'],
            'field_values' => '{}',
            'status' => $row['status'],
            'created' => date('Y-m-d H:i:s'),
            'modified' => date('Y-m-d H:i:s'),
        ]);
    }

    $suggestion = RecordFormInstanceTitleService::suggest($template, 2099);
    assert_same(3, $suggestion['sequence'], 'Non-voided records define the next sequence');
    assert_same('003', $suggestion['sequence_label'], 'Sequence is padded');
    assert_same('XZTC/BG-01-02-2099-003', $suggestion['record_instance_number'], 'Instance number combines template number, year, and sequence');
    assert_same($baseTitle . '-003', $suggestion['record_title'], 'Record title appends the next sequence');

    $fresh = RecordFormInstanceTitleService::suggest($template, 2098);
    assert_same(1, $fresh['sequence'], 'A year with no existing records starts at one');
    assert_same('XZTC/BG-01-02-2098-001', $fresh['record_instance_number'], 'Fresh instance number starts with 001');
    assert_same('2098运行记录-' . (string)$template->doc_number . '-' . (string)$template->name . '-001', $fresh['record_title'], 'Fresh record title starts with 001');
} finally {
    if ($ids !== []) {
        Db::name('record_form_instances')->whereIn('id', $ids)->delete();
    }
}

$controllerSource = file_get_contents(dirname(__DIR__) . '/app/controller/RecordFormInstance.php') ?: '';
assert_true(str_contains($controllerSource, 'RecordFormInstanceTitleService'), 'Create flow uses the title suggestion service');
assert_true(str_contains($controllerSource, 'suggested_record_title'), 'Create flow posts the hidden original suggestion for stale-title detection');

$createViewSource = file_get_contents(dirname(__DIR__) . '/app/view/record_form_instance/create.html') ?: '';
assert_true(str_contains($createViewSource, 'name="record_year"'), 'Create page exposes a record year selector');
assert_true(str_contains($createViewSource, '建议编号'), 'Create page displays the suggested instance number');

echo "record_form_instance_title_suggestion_smoke passed\n";
