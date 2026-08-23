<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../app/common.php';

$app = new think\App();
$app->initialize();

use app\service\ActionAuthorizationService;
use app\service\RbacService;
use app\service\TrialIdentitySeedGuardService;
use app\service\TrialModeService;
use think\facade\Config;
use think\facade\Db;
use think\facade\Session;

if (
    (!TrialModeService::isEnabled()
        || TrialModeService::trialBatch() !== 'GOV-TRIAL-20260724')
    && getenv('QMS_ALLOW_DESTRUCTIVE_SMOKE') !== '1'
) {
    fwrite(STDERR, "拒绝运行：本测试会创建后立即清理临时账号，只能在 8021 治理试运行环境执行。\n");
    exit(2);
}

$passes = [];
$failures = [];
$createdPosition = false;

final class RecordOperatorFakeRequest
{
    public function __construct(private readonly string $id = '')
    {
    }

    public function param(string $name, mixed $default = ''): mixed
    {
        return $name === 'id' ? $this->id : $default;
    }

    public function post(string $name, mixed $default = ''): mixed
    {
        return $default;
    }

    public function isPost(): bool
    {
        return false;
    }
}

function record_operator_case(bool $condition, string $id, string $message): void
{
    global $passes, $failures;
    if ($condition) {
        $passes[] = $id . ' ' . $message;
    } else {
        $failures[] = $id . ' ' . $message;
    }
}

function record_operator_cleanup(): void
{
    global $createdPosition;

    Db::name('record_form_instances')
        ->whereIn('id', [
            'd8000000-0000-4000-8000-000000000005',
            'd8000000-0000-4000-8000-000000000006',
        ])
        ->delete();
    Db::name('employee_appointments')
        ->whereIn('appointment_key', [
            'smoke-record-operator',
            'smoke-record-operator-narrow',
            'smoke-record-operator-privileged',
        ])
        ->delete();
    Db::name('users')
        ->where('username', 'smoke_record_operator')
        ->delete();
    Db::name('employees')
        ->where('employee_number', 'SMOKE-RECORD-OPERATOR')
        ->delete();
    if ($createdPosition) {
        Db::name('qms_positions')
            ->where('id', 'd8000000-0000-4000-8000-000000000001')
            ->delete();
    }
    Session::clear();
    ActionAuthorizationService::clearRequestCache();
}

function record_operator_login_existing(string $username): void
{
    $user = Db::name('users')
        ->where('username', $username)
        ->where('publish', 1)
        ->where('soft_delete', 0)
        ->find();
    if (!$user) {
        throw new RuntimeException("未找到既有回归账号 {$username}");
    }

    Session::set('user', [
        'id' => (string)$user['id'],
        'employee_id' => (string)$user['employee_id'],
        'username' => (string)$user['username'],
        'name' => (string)$user['name'],
        'role' => (string)$user['role'],
    ]);
}

register_shutdown_function('record_operator_cleanup');

try {
    record_operator_cleanup();

    $position = Db::name('qms_positions')
        ->where('code', 'record_operator')
        ->where('soft_delete', 0)
        ->find();
    if (!$position) {
        Db::name('qms_positions')->insert([
            'id' => 'd8000000-0000-4000-8000-000000000001',
            'company_id' => (string)Config::get('qms.company_id'),
            'code' => 'record_operator',
            'name' => '记录填报员',
            'review_status' => 'published',
            'publish' => 1,
            'soft_delete' => 0,
            'created' => '2026-07-28 00:00:00',
            'modified' => '2026-07-28 00:00:00',
        ]);
        $createdPosition = true;
        $position = Db::name('qms_positions')
            ->where('id', 'd8000000-0000-4000-8000-000000000001')
            ->find();
    }

    $employeeId = 'd8000000-0000-4000-8000-000000000002';
    $userId = 'd8000000-0000-4000-8000-000000000003';
    Db::name('employees')->insert([
        'id' => $employeeId,
        'company_id' => (string)Config::get('qms.company_id'),
        'employee_number' => 'SMOKE-RECORD-OPERATOR',
        'name' => '记录填报员测试账号',
        'publish' => 1,
        'soft_delete' => 0,
        'created' => '2026-07-28 00:00:00',
        'modified' => '2026-07-28 00:00:00',
    ]);
    Db::name('users')->insert([
        'id' => $userId,
        'company_id' => (string)Config::get('qms.company_id'),
        'employee_id' => $employeeId,
        'username' => 'smoke_record_operator',
        'password' => password_hash(bin2hex(random_bytes(24)), PASSWORD_DEFAULT),
        'name' => '记录填报员测试账号',
        'role' => 'staff',
        'publish' => 1,
        'soft_delete' => 0,
        'created' => '2026-07-28 00:00:00',
        'modified' => '2026-07-28 00:00:00',
    ]);
    Db::name('employee_appointments')->insert([
        'id' => 'd8000000-0000-4000-8000-000000000004',
        'company_id' => (string)Config::get('qms.company_id'),
        'employee_id' => $employeeId,
        'position_id' => (string)$position['id'],
        'site_id' => null,
        'appointment_key' => 'smoke-record-operator',
        'appointment_type' => 'role',
        'position_name' => '记录填报员',
        'appointed_at' => '2026-07-28',
        'status' => 'active',
        'publish' => 1,
        'soft_delete' => 0,
        'created' => '2026-07-28 00:00:00',
        'modified' => '2026-07-28 00:00:00',
    ]);
    Session::set('user', [
        'id' => $userId,
        'employee_id' => $employeeId,
        'username' => 'smoke_record_operator',
        'name' => '记录填报员测试账号',
        'role' => 'staff',
    ]);

    $ownRecord = (object)['id' => 'own-record', 'created_by' => $userId];
    $otherRecord = (object)['id' => 'other-record', 'created_by' => 'another-user'];
    $templateId = (string)(Db::name('record_form_templates')->value('id') ?: '');
    if ($templateId === '') {
        throw new RuntimeException('未找到可用于记录路由回归的表格模板');
    }
    $recordBase = [
        'company_id' => (string)Config::get('qms.company_id'),
        'template_id' => $templateId,
        'template_name' => '记录填报员路由回归',
        'template_version' => 'TEST',
        'template_field_schema' => '[]',
        'field_values' => '{}',
        'status' => 'draft',
        'is_simulation' => 1,
        'trial_batch' => 'GOV-TRIAL-20260724',
        'created' => '2026-07-28 00:00:00',
        'modified' => '2026-07-28 00:00:00',
    ];
    Db::name('record_form_instances')->insert(array_merge($recordBase, [
        'id' => 'd8000000-0000-4000-8000-000000000005',
        'doc_number' => 'SMOKE-RECORD-OPERATOR-OWN',
        'record_title' => '本人记录路由回归',
        'created_by' => $userId,
        'modified_by' => $userId,
    ]));
    Db::name('record_form_instances')->insert(array_merge($recordBase, [
        'id' => 'd8000000-0000-4000-8000-000000000006',
        'doc_number' => 'SMOKE-RECORD-OPERATOR-OTHER',
        'record_title' => '他人记录路由回归',
        'created_by' => 'another-user',
        'modified_by' => 'another-user',
    ]));

    record_operator_case(
        RbacService::isRestrictedRecordOperator(),
        'RO01',
        '有效记录填报员任命会启用独立最小权限模式'
    );
    record_operator_case(
        RbacService::canAccess('dashboard')
        && RbacService::canAccess('qualityworkbench')
        && RbacService::canAccess('document')
        && RbacService::canAccess('record_form_template')
        && RbacService::canAccess('record_form_instance')
        && RbacService::canAccess('notification'),
        'RO02',
        '记录填报员可访问我的工作、查文件、记录模板、本人记录和通知'
    );
    record_operator_case(
        RbacService::canAccess('document')
        && RbacService::canAccess('qualityworkbench')
        && !RbacService::canAccess('planning_responsibility')
        && !RbacService::canAccess('complaint')
        && !RbacService::canAccess('calendar'),
        'RO03',
        '记录填报员可日常查文件，但不能进入岗位责任、投诉和综合待办'
    );
    record_operator_case(
        RbacService::canWrite('record_form_instance')
        && !RbacService::canWrite('record_form_template')
        && !RbacService::canWrite('document'),
        'RO04',
        '记录填报员只能写记录实例，不能改模板或体系文件'
    );
    record_operator_case(
        ActionAuthorizationService::allows('record_form_instance', 'create')
        && ActionAuthorizationService::allows('record_form_instance', 'edit', $ownRecord)
        && ActionAuthorizationService::allows('record_form_instance', 'export_pdf', $ownRecord),
        'RO05',
        '记录填报员可新建、编辑和生成本人草稿'
    );
    record_operator_case(
        !ActionAuthorizationService::allows('record_form_instance', 'edit', $otherRecord)
        && !ActionAuthorizationService::allows('record_form_instance', 'export_pdf', $otherRecord)
        && !ActionAuthorizationService::allows('record_form_instance', 'review_list')
        && !ActionAuthorizationService::allows('record_form_template', 'review_list'),
        'RO06',
        '记录填报员不能操作他人记录或进入年度确认和模板复核'
    );

    $scope = ActionAuthorizationService::recordFormVisibilityScope();
    record_operator_case(
        $scope === ['all' => false, 'user_id' => $userId],
        'RO07',
        '记录列表的数据范围被收紧为本人'
    );
    record_operator_case(
        ActionAuthorizationService::canViewRecordFormInstance($ownRecord)
        && !ActionAuthorizationService::canViewRecordFormInstance($otherRecord)
        && ActionAuthorizationService::allows('record_form_instance', 'view', $ownRecord)
        && !ActionAuthorizationService::allows('record_form_instance', 'view', $otherRecord),
        'RO08',
        '直接访问记录详情时仍执行本人范围校验'
    );

    $ownRequest = new RecordOperatorFakeRequest('d8000000-0000-4000-8000-000000000005');
    $otherRequest = new RecordOperatorFakeRequest('d8000000-0000-4000-8000-000000000006');
    $readActions = [
        'view',
        'print',
        'printCorrections',
        'downloadCurrentPackage',
        'downloadPdf',
        'downloadPreviewPdf',
    ];
    $ownReadAllowed = true;
    $otherReadDenied = true;
    foreach ($readActions as $readAction) {
        $ownReadAllowed = $ownReadAllowed
            && ActionAuthorizationService::requestDecision(
                'RecordFormInstance',
                $readAction,
                $ownRequest
            ) === true;
        $otherReadDenied = $otherReadDenied
            && ActionAuthorizationService::requestDecision(
                'RecordFormInstance',
                $readAction,
                $otherRequest
            ) === false;
    }
    record_operator_case(
        $ownReadAllowed
        && $otherReadDenied
        && ActionAuthorizationService::requestDecision(
            'RecordFormInstance',
            'edit',
            $ownRequest
        ) === true
        && ActionAuthorizationService::requestDecision(
            'RecordFormInstance',
            'exportPdf',
            $ownRequest
        ) === true
        && ActionAuthorizationService::requestDecision(
            'RecordFormInstance',
            'downloadCurrentPackage',
            $ownRequest
        ) === true
        && ActionAuthorizationService::requestDecision(
            'RecordFormInstance',
            'downloadCurrentPdf',
            $ownRequest
        ) === true
        && ActionAuthorizationService::requestDecision(
            'RecordFormInstance',
            'requestCorrection',
            $ownRequest
        ) === true,
        'RO09',
        '详情、打印、下载、更正、编辑和导出路由均执行本人范围校验'
    );

    $invalidAppointmentStates = [
        'inactive' => ['status' => 'inactive'],
        'expired' => ['status' => 'expired', 'valid_until' => '2026-07-27'],
        'revoked' => ['status' => 'revoked'],
        'unpublished' => ['status' => 'active', 'publish' => 0],
        'soft-deleted' => ['status' => 'active', 'soft_delete' => 1],
    ];
    foreach ($invalidAppointmentStates as $stateName => $stateChanges) {
        Db::name('employee_appointments')
            ->where('id', 'd8000000-0000-4000-8000-000000000004')
            ->update(array_merge([
                'status' => 'active',
                'valid_until' => null,
                'publish' => 1,
                'soft_delete' => 0,
                'modified' => '2026-07-28 00:01:00',
            ], $stateChanges));
        ActionAuthorizationService::clearRequestCache();
        record_operator_case(
            RbacService::isRestrictedRecordOperator()
            && RbacService::canAccess('record_form_instance')
            && !RbacService::canAccess('record_form_template')
            && RbacService::canAccess('document')
            && RbacService::canAccess('qualityworkbench')
            && !RbacService::canAccess('complaint')
            && !RbacService::canWrite('record_form_instance')
            && !ActionAuthorizationService::canRecordOperatorFill()
            && ActionAuthorizationService::recordFormVisibilityScope()
                === ['all' => false, 'user_id' => $userId]
            && !ActionAuthorizationService::allows('record_form_instance', 'create')
            && !ActionAuthorizationService::allows('record_form_instance', 'edit', $ownRecord)
            && !ActionAuthorizationService::allows(
                'record_form_instance',
                'export_pdf',
                $ownRecord
            )
            && ActionAuthorizationService::allows('record_form_instance', 'view', $ownRecord)
            && !ActionAuthorizationService::allows(
                'record_form_instance',
                'view',
                $otherRecord
            )
            && ActionAuthorizationService::requestDecision(
                'RecordFormInstance',
                'downloadCurrentPackage',
                $ownRequest
            ) === false
            && ActionAuthorizationService::requestDecision(
                'RecordFormInstance',
                'downloadCurrentPdf',
                $ownRequest
            ) === false
            && ActionAuthorizationService::requestDecision(
                'RecordFormInstance',
                'requestCorrection',
                $ownRequest
            ) === false
            && ActionAuthorizationService::requestDecision(
                'RecordFormInstance',
                'view',
                $ownRequest
            ) === true,
            'RO10-' . $stateName,
            "任命状态 {$stateName} 时保留日常查文件和本人历史记录只读权限"
        );
    }

    $equipmentManagerPositionId = (string)(Db::name('qms_positions')
        ->where('code', 'equipment_manager')
        ->where('review_status', 'published')
        ->where('publish', 1)
        ->where('soft_delete', 0)
        ->value('id') ?: '');
    if ($equipmentManagerPositionId === '') {
        throw new RuntimeException('未找到可用于专项岗位回归的设备管理员岗位');
    }
    Db::name('employee_appointments')->insert([
        'id' => 'd8000000-0000-4000-8000-000000000008',
        'company_id' => (string)Config::get('qms.company_id'),
        'employee_id' => $employeeId,
        'position_id' => $equipmentManagerPositionId,
        'site_id' => null,
        'appointment_key' => 'smoke-record-operator-narrow',
        'appointment_type' => 'role',
        'position_name' => '设备管理员',
        'appointed_at' => '2026-07-28',
        'status' => 'active',
        'publish' => 1,
        'soft_delete' => 0,
        'created' => '2026-07-28 00:01:30',
        'modified' => '2026-07-28 00:01:30',
    ]);
    ActionAuthorizationService::clearRequestCache();
    record_operator_case(
        RbacService::isRestrictedRecordOperator()
        && RbacService::canAccess('document')
        && RbacService::canAccess('qualityworkbench')
        && ActionAuthorizationService::recordFormVisibilityScope()
            === ['all' => false, 'user_id' => $userId]
        && ActionAuthorizationService::allows('equipment', 'view')
        && ActionAuthorizationService::allows('record_form_instance', 'view', $ownRecord)
        && !ActionAuthorizationService::allows(
            'record_form_instance',
            'view',
            $otherRecord
        ),
        'RO11',
        '设备管理员等专项岗位只叠加专项动作，不解除本人记录范围'
    );
    Db::name('employee_appointments')
        ->where('appointment_key', 'smoke-record-operator-narrow')
        ->delete();

    $technicalManagerPositionId = (string)(Db::name('qms_positions')
        ->where('code', 'technical_manager')
        ->where('review_status', 'published')
        ->where('publish', 1)
        ->where('soft_delete', 0)
        ->value('id') ?: '');
    if ($technicalManagerPositionId === '') {
        throw new RuntimeException('未找到可用于复核权限升级的技术负责人岗位');
    }
    Db::name('employee_appointments')->insert([
        'id' => 'd8000000-0000-4000-8000-000000000007',
        'company_id' => (string)Config::get('qms.company_id'),
        'employee_id' => $employeeId,
        'position_id' => $technicalManagerPositionId,
        'site_id' => null,
        'appointment_key' => 'smoke-record-operator-privileged',
        'appointment_type' => 'role',
        'position_name' => '技术负责人',
        'appointed_at' => '2026-07-28',
        'status' => 'active',
        'publish' => 1,
        'soft_delete' => 0,
        'created' => '2026-07-28 00:02:00',
        'modified' => '2026-07-28 00:02:00',
    ]);
    ActionAuthorizationService::clearRequestCache();
    record_operator_case(
        !RbacService::isRestrictedRecordOperator()
        && RbacService::canAccess('document')
        && ActionAuthorizationService::allows('document', 'review'),
        'RO12',
        '另有全量记录管理岗位任命时按新岗位权限工作，不被历史记录员身份锁死'
    );

    record_operator_login_existing('sim_preparer');
    record_operator_case(
        RbacService::canAccess('document')
        && ActionAuthorizationService::allows('record_form_template', 'draft')
        && ActionAuthorizationService::allows('record_form_instance', 'create'),
        'RO13',
        '既有 SIM 编制人仍可管理文件、维护模板和建立记录'
    );

    record_operator_login_existing('sim_reviewer');
    record_operator_case(
        RbacService::canAccess('document')
        && ActionAuthorizationService::allows('document', 'review')
        && ActionAuthorizationService::allows('record_form_instance', 'create'),
        'RO14',
        '既有 SIM 技术审核人仍可审核文件和建立记录'
    );

    $configuredCompanyId = TrialIdentitySeedGuardService::configuredCompanyId();
    $originalEmployeeName = (string)Db::name('employees')
        ->where('id', $employeeId)
        ->value('name');
    $guardRejected = false;
    try {
        Db::transaction(function () use ($configuredCompanyId, $employeeId): void {
            Db::name('employees')->where('id', $employeeId)->update([
                'name' => 'SHOULD-ROLLBACK',
            ]);
            TrialIdentitySeedGuardService::findReusableUser(
                $configuredCompanyId,
                'smoke_record_operator',
                'wrong-employee-id'
            );
        });
    } catch (RuntimeException $exception) {
        $guardRejected = str_contains($exception->getMessage(), '拒绝覆盖');
    }
    $employeeNameAfterConflict = (string)Db::name('employees')
        ->where('id', $employeeId)
        ->value('name');
    $seedSource = (string)file_get_contents(
        dirname(__DIR__) . '/scripts/qms_record_operator_trial_seed.php'
    );
    record_operator_case(
        $configuredCompanyId === (string)Config::get('qms.company_id')
        && $guardRejected
        && $employeeNameAfterConflict === $originalEmployeeName
        && str_contains(
            $seedSource,
            'TrialIdentitySeedGuardService::configuredCompanyId()'
        )
        && str_contains(
            $seedSource,
            'TrialIdentitySeedGuardService::findReusableUser('
        ),
        'RO15',
        '种子脚本固定配置机构，真实冲突会拒绝覆盖并回滚同事务变更'
    );
} catch (Throwable $exception) {
    $failures[] = 'HARNESS ' . get_class($exception) . ': ' . $exception->getMessage();
} finally {
    record_operator_cleanup();
}

foreach ($passes as $pass) {
    fwrite(STDOUT, "PASS {$pass}\n");
}
foreach ($failures as $failure) {
    fwrite(STDERR, "FAIL {$failure}\n");
}
if ($failures !== []) {
    fwrite(STDERR, sprintf(
        "qms_record_operator_access_smoke failed: %d passed, %d failed\n",
        count($passes),
        count($failures)
    ));
    exit(1);
}

fwrite(STDOUT, "qms_record_operator_access_smoke passed\n");
