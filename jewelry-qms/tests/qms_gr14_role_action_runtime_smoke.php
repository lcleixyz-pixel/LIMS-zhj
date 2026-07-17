<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../app/common.php';

$app = new think\App();
$app->initialize();

use app\service\ActionAuthorizationService;
use think\facade\Config;
use think\facade\Db;
use think\facade\Session;

if (
    !str_contains((string)Config::get('qms.environment_label', ''), '8011')
    && getenv('QMS_ALLOW_DESTRUCTIVE_SMOKE') !== '1'
) {
    fwrite(STDERR, "拒绝运行：本测试会创建临时账号，只能在 8011 候选环境执行。\n");
    exit(2);
}

$passes = [];
$failures = [];

function gr14_role_case(bool $condition, string $id, string $message): void
{
    global $passes, $failures;
    if ($condition) {
        $passes[] = $id . ' ' . $message;
    } else {
        $failures[] = $id . ' ' . $message;
    }
}

function gr14_role_login(string $employeeNumber): void
{
    $employee = Db::name('employees')
        ->where('employee_number', $employeeNumber)
        ->where('soft_delete', 0)
        ->find();
    if (!$employee) {
        throw new RuntimeException("未找到员工 {$employeeNumber}");
    }

    $userId = 'gr14-role-' . strtolower($employeeNumber);
    Db::name('users')->where('id', $userId)->delete();
    Db::name('users')->insert([
        'id' => $userId,
        'company_id' => (string)Config::get('qms.company_id'),
        'employee_id' => (string)$employee['id'],
        'username' => 'gr14_role_' . strtolower($employeeNumber),
        'password' => password_hash(bin2hex(random_bytes(24)), PASSWORD_DEFAULT),
        'name' => (string)$employee['name'],
        'role' => 'staff',
        'publish' => 1,
        'soft_delete' => 0,
        'created' => date('Y-m-d H:i:s'),
        'modified' => date('Y-m-d H:i:s'),
    ]);
    Session::set('user', [
        'id' => $userId,
        'employee_id' => (string)$employee['id'],
        'role' => 'staff',
    ]);
}

function gr14_role_allows(string $module, string $action, ?object $record = null): bool
{
    return ActionAuthorizationService::allows($module, $action, $record);
}

function gr14_role_cleanup(): void
{
    Db::name('employee_appointments')->where('appointment_key', 'gr14-role-authorized-signatory-only')->delete();
    Db::name('users')->whereLike('id', 'gr14-role-%')->delete();
    Session::clear();
}
register_shutdown_function('gr14_role_cleanup');

$qualityTemplate = (object)['responsible_position_code' => 'quality_manager'];
$technicalTemplate = (object)['responsible_position_code' => 'technical_manager'];
$urumqi = (string)Db::name('sites')->where('code', 'PLACE01')->value('id');
$hetian = (string)Db::name('sites')->where('code', 'PLACE02')->value('id');

try {
    gr14_role_cleanup();

    gr14_role_login('E002');
    gr14_role_case(
        gr14_role_allows('audit_plan', 'organize')
        && gr14_role_allows('audit_plan', 'approve')
        && gr14_role_allows('management_review', 'complete')
        && gr14_role_allows('record_form_template', 'approve_trial', $qualityTemplate),
        'R01',
        '张晓磊可组织质量体系活动并批准质量类试运行模板'
    );
    gr14_role_case(
        !gr14_role_allows('equipment', 'edit', (object)['site_id' => $urumqi]),
        'R01A',
        '质量负责人不替代设备管理员修改设备'
    );
    gr14_role_case(
        !gr14_role_allows('record_form_template', 'approve_trial', $technicalTemplate),
        'R02',
        '质量负责人不能替代总体技术负责人批准技术类试运行模板'
    );

    gr14_role_login('E005');
    gr14_role_case(
        gr14_role_allows('record_form_template', 'approve_trial', $technicalTemplate)
        && gr14_role_allows('document', 'review')
        && gr14_role_allows(
            'external_evidence_reference',
            'add',
            (object)['_subject_type' => 'quality_event', 'site_id' => $urumqi]
        ),
        'R03',
        '刘恒春可复核技术类模板、技术文件和有明确对象范围的外部证据'
    );
    gr14_role_case(
        !gr14_role_allows('record_form_template', 'approve_trial', $qualityTemplate)
        && !gr14_role_allows('audit_plan', 'organize')
        && !gr14_role_allows('equipment', 'edit', (object)['site_id' => $urumqi]),
        'R04',
        '总体技术负责人不能替代质量负责人或设备管理员执行受控动作'
    );

    foreach (['E006' => [$urumqi, $hetian], 'E008' => [$hetian, $urumqi]] as $employeeNumber => [$ownSite, $otherSite]) {
        gr14_role_login($employeeNumber);
        $ownTrialDocument = (object)['site_id' => $ownSite, 'doc_number' => 'SIM-QP-GR14-RUNTIME'];
        $otherTrialDocument = (object)['site_id' => $otherSite, 'doc_number' => 'SIM-QP-GR14-RUNTIME'];
        gr14_role_case(
            gr14_role_allows('document', 'register', (object)['site_id' => $ownSite])
            && gr14_role_allows('document', 'distribute', $ownTrialDocument)
            && gr14_role_allows('document', 'recall', $ownTrialDocument)
            && gr14_role_allows('document', 'revise', $ownTrialDocument)
            && !gr14_role_allows('document', 'revise', $otherTrialDocument)
            && !gr14_role_allows('document', 'review')
            && !gr14_role_allows('record_form_template', 'approve_trial', $qualityTemplate),
            'R05-' . $employeeNumber,
            "{$employeeNumber} 文件管理员可登记分发召回修订但不能审批"
        );
    }

    gr14_role_login('E010');
    gr14_role_case(
        gr14_role_allows('equipment', 'edit', (object)['site_id' => $urumqi])
        && gr14_role_allows('equipment_maintenance', 'write', (object)['site_id' => $urumqi])
        && !gr14_role_allows('equipment_maintenance', 'write', (object)['site_id' => $hetian])
        && gr14_role_allows('equipment_transfer', 'write', (object)['site_id' => $urumqi, '_to_site_id' => $urumqi])
        && !gr14_role_allows('equipment_transfer', 'write', (object)['site_id' => $urumqi, '_to_site_id' => $hetian]),
        'R06',
        '王胜林只能管理乌鲁木齐设备'
    );
    gr14_role_case(
        gr14_role_allows('equipment_transfer', 'view', (object)['from_site_id' => $urumqi, 'to_site_id' => $urumqi])
        && !gr14_role_allows('equipment_transfer', 'view', (object)['from_site_id' => $hetian, 'to_site_id' => $hetian]),
        'R06A',
        '王胜林不能通过直接 URL 查看纯和田设备调拨记录'
    );

    gr14_role_login('E007');
    gr14_role_case(
        gr14_role_allows('equipment', 'edit', (object)['site_id' => $hetian])
        && gr14_role_allows('equipment_maintenance', 'write', (object)['site_id' => $hetian])
        && !gr14_role_allows('equipment_maintenance', 'write', (object)['site_id' => $urumqi])
        && gr14_role_allows('equipment_transfer', 'write', (object)['site_id' => $hetian, '_to_site_id' => $hetian])
        && !gr14_role_allows('equipment_transfer', 'write', (object)['site_id' => $hetian, '_to_site_id' => $urumqi]),
        'R07',
        '米尔布拉只能管理和田设备'
    );
    gr14_role_case(
        gr14_role_allows('equipment_transfer', 'view', (object)['from_site_id' => $hetian, 'to_site_id' => $hetian])
        && !gr14_role_allows('equipment_transfer', 'view', (object)['from_site_id' => $urumqi, 'to_site_id' => $urumqi]),
        'R07A',
        '米尔布拉不能通过直接 URL 查看纯乌鲁木齐设备调拨记录'
    );

    gr14_role_login('E009');
    gr14_role_case(
        !gr14_role_allows('document', 'review')
        && !gr14_role_allows('record_form_template', 'approve_trial', $qualityTemplate),
        'R08',
        '仅有系统账号角色而无业务任命时不能执行文件或模板审批'
    );

    $e009 = (string)Db::name('employees')->where('employee_number', 'E009')->value('id');
    $signatoryPosition = (string)Db::name('qms_positions')->where('code', 'authorized_signatory')->value('id');
    Db::name('employee_appointments')->insert([
        'id' => 'gr14-role-signatory-only',
        'company_id' => (string)Config::get('qms.company_id'),
        'employee_id' => $e009,
        'position_id' => $signatoryPosition,
        'site_id' => null,
        'appointment_key' => 'gr14-role-authorized-signatory-only',
        'appointment_type' => 'authorization',
        'position_name' => '授权签字人',
        'appointed_at' => '2026-07-17',
        'status' => 'active',
        'publish' => 1,
        'soft_delete' => 0,
        'created' => date('Y-m-d H:i:s'),
        'modified' => date('Y-m-d H:i:s'),
    ]);
    gr14_role_case(
        !gr14_role_allows('document', 'review')
        && !gr14_role_allows('record_form_template', 'approve_trial', $qualityTemplate),
        'R09',
        '授权签字人身份本身不赋予文件或模板审批权'
    );
} catch (Throwable $exception) {
    $failures[] = 'HARNESS ' . get_class($exception) . ': ' . $exception->getMessage();
} finally {
    gr14_role_cleanup();
}

foreach ($passes as $pass) {
    fwrite(STDOUT, "PASS {$pass}\n");
}
foreach ($failures as $failure) {
    fwrite(STDERR, "FAIL {$failure}\n");
}
if ($failures !== []) {
    fwrite(STDERR, sprintf(
        "qms_gr14_role_action_runtime_smoke failed: %d passed, %d failed\n",
        count($passes),
        count($failures)
    ));
    exit(1);
}
fwrite(STDOUT, "qms_gr14_role_action_runtime_smoke passed\n");
