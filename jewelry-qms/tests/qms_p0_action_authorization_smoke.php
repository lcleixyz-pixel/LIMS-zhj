<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../app/common.php';

$app = new think\App();
$app->initialize();

use app\middleware\Rbac;
use app\service\ActionAuthorizationService;
use think\facade\Config;
use think\facade\Db;
use think\facade\Session;

$passes = [];
$failures = [];

function action_case(bool $condition, string $id, string $message): void
{
    global $passes, $failures;
    if ($condition) {
        $passes[] = $id . ' ' . $message;
    } else {
        $failures[] = $id . ' ' . $message;
    }
}

function action_uuid(string $suffix): string
{
    return 'b4000000-0000-4000-8000-' . str_pad($suffix, 12, '0', STR_PAD_LEFT);
}

function action_cleanup(): void
{
    Db::name('histories')->whereLike('record_id', 'b4000000-%')->delete();
    Db::name('capas')->where('id', action_uuid('505'))->delete();
    Db::name('employee_appointments')->whereLike('appointment_key', 'p0-r13b4-%')->delete();
    Db::name('users')->whereLike('username', 'p0_r13b4_%')->delete();
    Db::name('employees')->whereLike('employee_number', 'P0-R13B4-%')->delete();
    Db::name('qms_positions')->whereLike('id', 'b4000000-%')->delete();
    Db::name('sites')->whereLike('id', 'b4000000-%')->delete();
}

function action_seed_position(string $id, string $code, string $name): void
{
    if (Db::name('qms_positions')->where('code', $code)->where('soft_delete', 0)->find()) {
        return;
    }

    Db::name('qms_positions')->insert([
        'id' => $id,
        'company_id' => (string)Config::get('qms.company_id'),
        'code' => $code,
        'name' => $name,
        'review_status' => 'published',
        'publish' => 1,
        'soft_delete' => 0,
        'created' => '2026-07-17 00:00:00',
        'modified' => '2026-07-17 00:00:00',
    ]);
}

function action_seed_identity(
    string $suffix,
    string $role = 'staff',
    ?string $siteId = null,
    ?string $positionCode = null,
    string $appointmentStatus = 'active',
    ?string $validUntil = null
): array {
    $employeeId = action_uuid('1' . str_pad($suffix, 2, '0', STR_PAD_LEFT));
    $userId = action_uuid('2' . str_pad($suffix, 2, '0', STR_PAD_LEFT));
    Db::name('employees')->insert([
        'id' => $employeeId,
        'company_id' => (string)Config::get('qms.company_id'),
        'primary_site_id' => $siteId,
        'employee_number' => 'P0-R13B4-' . $suffix,
        'name' => 'B4 测试人员 ' . $suffix,
        'publish' => 1,
        'soft_delete' => 0,
        'created' => '2026-07-17 00:00:00',
        'modified' => '2026-07-17 00:00:00',
    ]);
    Db::name('users')->insert([
        'id' => $userId,
        'company_id' => (string)Config::get('qms.company_id'),
        'employee_id' => $employeeId,
        'username' => 'p0_r13b4_' . $suffix,
        'password' => password_hash('password', PASSWORD_DEFAULT),
        'name' => 'B4 测试人员 ' . $suffix,
        'role' => $role,
        'publish' => 1,
        'soft_delete' => 0,
        'created' => '2026-07-17 00:00:00',
        'modified' => '2026-07-17 00:00:00',
    ]);
    if ($positionCode !== null) {
        $positionId = (string)Db::name('qms_positions')->where('code', $positionCode)->value('id');
        Db::name('employee_appointments')->insert([
            'id' => action_uuid('3' . str_pad($suffix, 2, '0', STR_PAD_LEFT)),
            'company_id' => (string)Config::get('qms.company_id'),
            'employee_id' => $employeeId,
            'position_id' => $positionId,
            'site_id' => $siteId,
            'appointment_key' => 'p0-r13b4-' . $suffix,
            'appointment_type' => 'role',
            'position_name' => $positionCode,
            'appointed_at' => '2026-01-01',
            'valid_until' => $validUntil,
            'status' => $appointmentStatus,
            'publish' => 1,
            'soft_delete' => 0,
            'created' => '2026-07-17 00:00:00',
            'modified' => '2026-07-17 00:00:00',
        ]);
    }

    return [
        'id' => $userId,
        'employee_id' => $employeeId,
        'role' => $role,
        'site_id' => $siteId,
    ];
}

function action_login(array $identity): void
{
    Session::set('user', [
        'id' => $identity['id'],
        'employee_id' => $identity['employee_id'],
        'role' => $identity['role'],
        'session_id' => action_uuid('9' . substr((string)$identity['id'], -2)),
    ]);
}

function action_allows(string $module, string $action, ?object $record = null): bool
{
    if (!class_exists(ActionAuthorizationService::class)) {
        return false;
    }

    return ActionAuthorizationService::allows($module, $action, $record);
}

final class ActionFakeRequest
{
    public function __construct(
        private string $controller,
        private string $action,
        private array $params = [],
        private array $posts = []
    ) {
    }

    public function controller(): string
    {
        return $this->controller;
    }

    public function action(): string
    {
        return $this->action;
    }

    public function isAjax(): bool
    {
        return false;
    }

    public function isPost(): bool
    {
        return true;
    }

    public function method(): string
    {
        return 'POST';
    }

    public function param(string $key, mixed $default = null): mixed
    {
        return $this->params[$key] ?? $default;
    }

    public function post(string $key = '', mixed $default = null): mixed
    {
        if ($key === '') {
            return $this->posts;
        }

        return $this->posts[$key] ?? $default;
    }
}

$siteMain = action_uuid('001');
$siteBranch = action_uuid('002');
$positions = [
    'quality_manager' => '质量负责人',
    'site_quality_coordinator' => '场所质量协调人',
    'internal_auditor' => '内审员',
    'equipment_manager' => '设备管理员',
    'document_controller' => '资料管理员',
    'technical_manager' => '技术负责人',
];

try {
    action_cleanup();
    foreach ([[$siteMain, 'B4MAIN', '乌鲁木齐'], [$siteBranch, 'B4HETIAN', '和田']] as [$id, $code, $name]) {
        Db::name('sites')->insert([
            'id' => $id,
            'company_id' => (string)Config::get('qms.company_id'),
            'code' => $code,
            'name' => $name,
            'status' => 'active',
            'publish' => 1,
            'soft_delete' => 0,
            'created' => '2026-07-17 00:00:00',
            'modified' => '2026-07-17 00:00:00',
        ]);
    }
    $positionIndex = 1;
    foreach ($positions as $code => $name) {
        action_seed_position(action_uuid('4' . str_pad((string)$positionIndex++, 2, '0', STR_PAD_LEFT)), $code, $name);
    }

    $staff = action_seed_identity('01', 'staff', $siteMain);
    $qualityManager = action_seed_identity('02', 'quality_manager', null, 'quality_manager');
    $coordinatorMain = action_seed_identity('03', 'staff', $siteMain, 'site_quality_coordinator');
    $auditorMain = action_seed_identity('04', 'auditor', $siteMain, 'internal_auditor');
    $equipmentMain = action_seed_identity('05', 'staff', $siteMain, 'equipment_manager');
    $equipmentBranch = action_seed_identity('06', 'staff', $siteBranch, 'equipment_manager');
    $documentController = action_seed_identity('07', 'staff', $siteMain, 'document_controller');
    $technicalMain = action_seed_identity('08', 'department_head', $siteMain, 'technical_manager');
    $assignee = action_seed_identity('09', 'staff', $siteMain);
    $adminOnly = action_seed_identity('10', 'admin', $siteMain);
    $expiredCoordinator = action_seed_identity(
        '11',
        'staff',
        $siteMain,
        'site_quality_coordinator',
        'active',
        '2025-12-31'
    );

    $ownComplaint = (object)[
        'id' => action_uuid('501'),
        'created_by' => $staff['id'],
        'assigned_to' => $staff['id'],
    ];
    $mainComplaint = (object)[
        'id' => action_uuid('502'),
        'created_by' => $staff['id'],
        'assigned_to' => $coordinatorMain['id'],
    ];
    $mainEquipment = (object)[
        'id' => action_uuid('503'),
        'site_id' => $siteMain,
        'created_by' => $equipmentMain['id'],
    ];
    $branchEquipment = (object)[
        'id' => action_uuid('504'),
        'site_id' => $siteBranch,
        'created_by' => $equipmentBranch['id'],
    ];
    $assignedCapa = (object)[
        'id' => action_uuid('505'),
        'assigned_to' => $assignee['id'],
        'status' => 'implementing',
    ];
    Db::name('capas')->insert([
        'id' => $assignedCapa->id,
        'company_id' => (string)Config::get('qms.company_id'),
        'capa_number' => 'P0R13B4-CAPA-ASSIGNED',
        'description' => '岗位动作授权测试 CAPA',
        'assigned_to' => $assignedCapa->assigned_to,
        'status' => $assignedCapa->status,
        'publish' => 1,
        'soft_delete' => 0,
        'record_status' => 0,
        'created' => '2026-07-17 00:00:00',
        'modified' => '2026-07-17 00:00:00',
    ]);
    $mainEmployee = (object)[
        'id' => $staff['employee_id'],
        'primary_site_id' => $siteMain,
    ];
    $branchEmployee = (object)[
        'id' => $equipmentBranch['employee_id'],
        'primary_site_id' => $siteBranch,
    ];

    action_login($staff);
    action_case(
        action_allows('complaint', 'register')
        && action_allows('complaint', 'view', $ownComplaint),
        'A01',
        '一般人员允许登记投诉且只可查看本人经办记录'
    );
    action_case(
        !action_allows('complaint', 'advance', $mainComplaint),
        'A02',
        '一般人员不得推进投诉'
    );

    action_login($qualityManager);
    action_case(
        action_allows('complaint', 'advance', $mainComplaint)
        && action_allows('complaint', 'create_capa', $mainComplaint),
        'A03',
        '质量负责人允许推进投诉和创建 CAPA'
    );

    action_login($coordinatorMain);
    action_case(
        action_allows('complaint', 'advance', $mainComplaint)
        && !action_allows('complaint', 'advance', (object)[
            'id' => action_uuid('506'),
            'created_by' => $equipmentBranch['id'],
        ]),
        'A04',
        '场所质量协调人只可处理任命场所投诉'
    );

    action_login($auditorMain);
    action_case(
        action_allows('equipment', 'view', $mainEquipment),
        'A05',
        '被派内审员允许只读任命范围设备证据'
    );
    action_case(
        !action_allows('equipment', 'edit', $mainEquipment)
        && !action_allows('equipment_maintenance', 'write', $mainEquipment),
        'A06',
        '内审员不得编辑设备或写维护记录'
    );

    action_login($equipmentMain);
    action_case(
        action_allows('equipment_maintenance', 'write', $mainEquipment)
        && !action_allows('equipment_maintenance', 'write', $branchEquipment),
        'A07',
        '乌鲁木齐设备管理员只可写本场所维护记录'
    );

    action_login($equipmentBranch);
    action_case(
        action_allows('equipment_maintenance', 'write', $branchEquipment)
        && !action_allows('equipment_maintenance', 'write', $mainEquipment),
        'A08',
        '和田设备管理员只可写本场所维护记录'
    );

    action_login($documentController);
    action_case(
        action_allows('record_form_template', 'review_list'),
        'A09',
        '资料管理员允许查看模板复核清单'
    );
    action_case(
        !action_allows('record_form_template', 'publish'),
        'A10',
        '无批准任命的资料管理员不得发布模板'
    );

    action_login($qualityManager);
    action_case(
        action_allows('record_form_template', 'publish'),
        'A11',
        '质量负责人允许批准发布模板'
    );

    action_login($assignee);
    $assigneeViewCalled = false;
    $assigneeViewResult = (new Rbac())->handle(
        new ActionFakeRequest('Capa', 'view', ['id' => $assignedCapa->id]),
        function () use (&$assigneeViewCalled): string {
            $assigneeViewCalled = true;

            return 'allowed';
        }
    );
    action_case(
        action_allows('capa', 'view', $assignedCapa)
        && action_allows('capa', 'edit_measures', $assignedCapa)
        && $assigneeViewCalled
        && $assigneeViewResult === 'allowed',
        'A12',
        'CAPA 被指派责任人可查看所分派记录并编辑措施'
    );
    action_case(
        !action_allows('capa', 'close', $assignedCapa),
        'A13',
        '未获验证权限的 CAPA 责任人不得关闭'
    );

    action_login($technicalMain);
    action_case(
        action_allows('competency_record', 'write', $mainEmployee)
        && !action_allows('competency_record', 'write', $branchEmployee),
        'A14',
        '技术负责人只可维护任命场所人员能力确认'
    );

    action_login($staff);
    $nextCalled = false;
    $status = 200;
    $deniedBody = '';
    $returnedResponse = false;
    try {
        $deniedResponse = (new Rbac())->handle(
            new ActionFakeRequest('Complaint', 'advance', ['id' => $mainComplaint->id]),
            function () use (&$nextCalled): string {
                $nextCalled = true;

                return 'unexpected';
            }
        );
        if ($deniedResponse instanceof \think\Response) {
            $returnedResponse = true;
            $status = $deniedResponse->getCode();
            $deniedBody = (string)$deniedResponse->getContent();
        }
    } catch (Throwable $exception) {
        $status = method_exists($exception, 'getStatusCode')
            ? (int)$exception->getStatusCode()
            : (int)$exception->getCode();
        $deniedBody = $exception->getMessage();
    }
    action_case(
        $status === 403
        && $returnedResponse
        && !$nextCalled
        && str_contains($deniedBody, '无此动作权限')
        && str_contains($deniedBody, '岗位')
        && !str_contains($deniedBody, '系统发生错误')
        && !str_contains($deniedBody, 'PDOException'),
        'A15',
        '越权直接 POST 返回友好 HTTP 403 且不进入业务动作'
    );

    action_login($expiredCoordinator);
    action_case(
        !action_allows('complaint', 'advance', $mainComplaint),
        'A16',
        '任命过期后立即失去动作权限'
    );
} catch (Throwable $exception) {
    $failures[] = 'HARNESS ' . get_class($exception) . ': ' . $exception->getMessage();
} finally {
    action_cleanup();
    Session::clear();
}

foreach ($passes as $pass) {
    fwrite(STDOUT, "PASS {$pass}\n");
}
foreach ($failures as $failure) {
    fwrite(STDERR, "FAIL {$failure}\n");
}

if ($failures !== []) {
    fwrite(STDERR, sprintf(
        "qms_p0_action_authorization_smoke failed: %d passed, %d failed\n",
        count($passes),
        count($failures)
    ));
    exit(1);
}

fwrite(STDOUT, "qms_p0_action_authorization_smoke passed: A01-A16\n");
