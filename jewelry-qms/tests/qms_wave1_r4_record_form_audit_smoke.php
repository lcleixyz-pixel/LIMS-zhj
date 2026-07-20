<?php
declare(strict_types=1);

$root = dirname(__DIR__);
require $root . '/vendor/autoload.php';
require $root . '/app/common.php';

$app = new think\App();
$app->initialize();

use app\model\RecordFormInstance;
use app\service\FieldAuditService;
use think\facade\Config;
use think\facade\Db;
use think\facade\Session;

$passes = [];
$failures = [];

function r4_case(bool $ok, string $id, string $msg): void
{
    global $passes, $failures;
    if ($ok) {
        $passes[] = "{$id} {$msg}";
    } else {
        $failures[] = "{$id} {$msg}";
    }
}

$service = (string)file_get_contents($root . '/app/service/FieldAuditService.php');
r4_case(
    str_contains($service, "'RecordFormInstance'")
    && str_contains($service, "'field_values'")
    && str_contains($service, "'record_title'")
    && str_contains($service, "'status'"),
    'S01',
    'FieldAuditService registers RecordFormInstance status/field_values/record_title'
);
r4_case(
    str_contains($service, 'canonicalJson')
    && str_contains($service, "in_array(\$field, self::\$jsonFields, true)"),
    'S02',
    'valuesEquivalent keeps JSON canonical compare for jsonFields'
);

$ref = new ReflectionClass(FieldAuditService::class);
$method = $ref->getMethod('valuesEquivalent');
$method->setAccessible(true);
$same = $method->invoke(null, 'field_values', '{"a":1,"b":2}', ['b' => 2, 'a' => 1]);
$diff = $method->invoke(null, 'field_values', '{"a":1}', ['a' => 2]);
r4_case($same === true, 'T01', 'equivalent JSON string vs array treated as same');
r4_case($diff === false, 'T02', 'different JSON values are not equivalent');

$ids = [
    'instance' => 'b4000000-0000-4000-8000-000000000101',
    'template' => 'b4000000-0000-4000-8000-000000000102',
    'user' => 'b4000000-0000-4000-8000-000000000103',
];
$companyId = (string)Config::get('qms.company_id');
$now = '2026-07-20 10:00:00';

try {
    Db::name('field_change_logs')->whereIn('record_id', array_values($ids))->delete();
    Db::name('record_form_instances')->where('id', $ids['instance'])->delete();
    Db::name('record_form_templates')->where('id', $ids['template'])->delete();
    Db::name('users')->where('id', $ids['user'])->delete();

    Db::name('users')->insert([
        'id' => $ids['user'],
        'company_id' => $companyId,
        'username' => 'wave1_r4_auditor',
        'password' => password_hash('test-only', PASSWORD_DEFAULT),
        'name' => 'R4留痕',
        'role' => 'staff',
        'publish' => 1,
        'soft_delete' => 0,
        'created' => $now,
        'modified' => $now,
    ]);
    Session::set('user', ['id' => $ids['user'], 'role' => 'staff', 'name' => 'R4留痕']);

    Db::name('record_form_templates')->insert([
        'id' => $ids['template'],
        'company_id' => $companyId,
        'doc_number' => 'WAVE1-R4-TPL',
        'name' => 'R4留痕模板',
        'module' => 'test',
        'print_template_key' => 'generic_record_form',
        'field_schema' => '[{"key":"a","label":"A","type":"text","required":false}]',
        'version' => 'A/0',
        'status' => 'published',
        'publish' => 1,
        'soft_delete' => 0,
        'created' => $now,
        'modified' => $now,
    ]);
    Db::name('record_form_instances')->insert([
        'id' => $ids['instance'],
        'company_id' => $companyId,
        'template_id' => $ids['template'],
        'template_name' => 'R4留痕模板',
        'template_module' => 'test',
        'template_version' => 'A/0',
        'template_print_template_key' => 'generic_record_form',
        'template_field_schema' => '[{"key":"a","label":"A","type":"text","required":false}]',
        'doc_number' => 'WAVE1-R4-TPL',
        'record_title' => '原标题',
        'field_values' => '{"a":"1"}',
        'status' => 'draft',
        'created' => $now,
        'modified' => $now,
    ]);

    $record = RecordFormInstance::find($ids['instance']);
    $record->save([
        'status' => 'generated',
        'record_title' => '新标题',
        'field_values' => '{"a":"2"}',
    ]);
    $logs = Db::name('field_change_logs')
        ->where('model_name', 'RecordFormInstance')
        ->where('record_id', $ids['instance'])
        ->order('field_name', 'asc')
        ->column('field_name');
    r4_case(
        $logs === ['field_values', 'record_title', 'status']
        || (count($logs) >= 2 && in_array('status', $logs, true) && in_array('field_values', $logs, true)),
        'T03',
        'RecordFormInstance audited fields produce change logs'
    );

    Db::name('field_change_logs')->where('record_id', $ids['instance'])->delete();
    $record = RecordFormInstance::find($ids['instance']);
    $record->save(['field_values' => '{"a":"2"}']);
    r4_case(
        count(Db::name('field_change_logs')->where('record_id', $ids['instance'])->select()->toArray()) === 0,
        'T04',
        'equivalent JSON field_values does not create false audit row'
    );
} catch (Throwable $exception) {
    r4_case(false, 'Txx', 'runtime audit failed: ' . $exception->getMessage());
} finally {
    Db::name('field_change_logs')->whereIn('record_id', array_values($ids))->delete();
    Db::name('record_form_instances')->where('id', $ids['instance'])->delete();
    Db::name('record_form_templates')->where('id', $ids['template'])->delete();
    Db::name('users')->where('id', $ids['user'])->delete();
    Session::clear();
}

foreach ($passes as $pass) {
    echo "PASS {$pass}\n";
}
foreach ($failures as $failure) {
    fwrite(STDERR, "FAIL {$failure}\n");
}
if ($failures !== []) {
    exit(1);
}
echo "qms_wave1_r4_record_form_audit_smoke passed\n";
