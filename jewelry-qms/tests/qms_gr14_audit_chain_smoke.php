<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$failures = [];
$passes = [];

function read_source(string $path): string
{
    global $root;
    $file = $root . '/' . $path;

    return is_file($file) ? (string)file_get_contents($file) : '';
}

function audit_check(bool $condition, string $id, string $message): void
{
    global $failures, $passes;
    if ($condition) {
        $passes[] = "{$id} {$message}";
    } else {
        $failures[] = "{$id} {$message}";
    }
}

function form_names(string $template, array $fields): bool
{
    foreach ($fields as $field) {
        if (!preg_match('/\bname=["\']' . preg_quote($field, '/') . '["\']/', $template)) {
            return false;
        }
    }

    return true;
}

$controller = read_source('app/controller/AuditPlan.php');
$add = read_source('app/view/audit_plan/add.html');
$edit = read_source('app/view/audit_plan/edit.html');
$view = read_source('app/view/audit_plan/view.html');
$closure = read_source('app/service/AuditClosureService.php');
$routes = read_source('route/app.php');
$scheduleController = read_source('app/controller/AuditSchedule.php');
$scheduleModel = read_source('app/model/AuditSchedule.php');
$scheduleIndex = read_source('app/view/audit_schedule/index.html');
$scheduleView = read_source('app/view/audit_schedule/view.html');
$findingController = read_source('app/controller/AuditFinding.php');
$findingView = read_source('app/view/audit_finding/view.html');
$findingIndex = read_source('app/view/audit_finding/index.html');
$checklistIndex = read_source('app/view/audit_checklist/index.html');
$checklistView = read_source('app/view/audit_checklist/view.html');
$qmsConfig = read_source('config/qms.php');
$workflow = read_source('app/service/WorkflowService.php');

$realFields = ['plan_year', 'title', 'scope', 'criteria'];
$wrongFields = ['name', 'code', 'active', 'department_id', 'responsible_person', 'remark', 'description'];

audit_check(
    form_names($add . $edit, $realFields),
    'AU01',
    '内审计划新增和编辑页使用真实字段'
);

$hasWrong = false;
foreach ($wrongFields as $field) {
    if (preg_match('/\bname=["\']' . preg_quote($field, '/') . '["\']/', $add . $edit)) {
        $hasWrong = true;
    }
}
audit_check(!$hasWrong, 'AU02', '内审计划页面不再提交不存在字段');

audit_check(
    str_contains($controller, "protected array \$writableFields = ['plan_year', 'title', 'scope', 'criteria']")
    && str_contains($controller, "'plan_year' => 'require")
    && str_contains($controller, "'title' => 'require"),
    'AU03',
    '服务端使用字段白名单并校验年度和标题'
);

audit_check(
    str_contains($routes, "Route::post('audit_plan/approve'")
    && str_contains($routes, "Route::post('audit_plan/complete'")
    && str_contains($controller, 'isPost()'),
    'AU04',
    '批准和完成动作只接受 POST'
);

audit_check(
    str_contains($closure, 'class AuditClosureService')
    && str_contains($closure, 'audit_schedules')
    && str_contains($closure, 'audit_findings')
    && str_contains($closure, 'capas')
    && str_contains($closure, "'completed'")
    && str_contains($closure, "'closed'"),
    'AU05',
    '计划关闭前检查日程、发现和 CAPA 状态'
);

audit_check(
    str_contains($view, '检查记录')
    && str_contains($view, '审核发现')
    && str_contains($view, 'CAPA')
    && str_contains($view, '/audit_plan/complete'),
    'AU06',
    '计划详情展示日程到检查记录、发现、CAPA 的追溯与关闭入口'
);

audit_check(
    str_contains($routes, "Route::post('audit_schedule/complete'")
    && str_contains($scheduleController, 'public function complete')
    && str_contains($scheduleController, 'isPost()')
    && str_contains($scheduleView, '/audit_schedule/complete')
    && str_contains($closure, 'scheduleBlockingReasons'),
    'AU07',
    '审核日程具备受阻断条件保护的 POST 完成入口'
);

audit_check(
    str_contains($routes, "Route::post('audit_finding/createCapa'")
    && !str_contains($routes, "Route::get('audit_finding/createCapa'")
    && str_contains($findingController, 'isPost()')
    && str_contains($findingView, '<form method="post" action="/audit_finding/createCapa'),
    'AU08',
    '审核发现创建 CAPA 使用 POST，避免 GET 产生业务写入'
);

audit_check(
    str_contains($workflow, "sourceType === 'audit'")
    && str_contains($workflow, "'status' => 'correcting'")
    && str_contains($workflow, "'status' => 'closed'"),
    'AU09',
    '审核发现随 CAPA 创建和关闭更新为整改中及已关闭'
);

audit_check(
    str_contains($workflow, 'TrialModeService::isEnabled()')
    && str_contains($workflow, 'TrialModeService::simulationNumber($capaNumber)')
    && str_contains($workflow, 'finding_number'),
    'AU10',
    '试运行审核发现创建的 CAPA 由服务端强制 SIM 编号'
);

audit_check(
    str_contains($scheduleModel, 'function department()')
    && str_contains($scheduleController, "View::assign('departmentNames'")
    && str_contains($scheduleController, "View::assign('siteNames'")
    && str_contains($scheduleIndex, '$departmentNames[$item.department_id]')
    && str_contains($scheduleIndex, '$siteNames[$item.site_id]'),
    'AU11',
    '审核日程列表向岗位人员显示部门和场所名称而非内部 UUID'
);

audit_check(
    str_contains($qmsConfig, "'audit_checklist' => [")
    && str_contains($checklistIndex, "qms_status_label('audit_checklist'")
    && str_contains($checklistIndex, '客观证据')
    && str_contains($checklistView, '审核日程')
    && str_contains($checklistView, '检查结果')
    && str_contains($checklistView, '客观证据')
    && !str_contains($checklistView, 'name="fields"')
    && str_contains($scheduleView, "qms_status_label('audit_checklist'")
    && str_contains($scheduleView, 'findingTypes[$f.finding_type]')
    && str_contains($findingIndex, 'finding_number')
    && str_contains($findingIndex, 'findingTypes[$item.finding_type]')
    && str_contains($findingIndex, "qms_status_label('audit_finding'"),
    'AU12',
    '审核检查和发现页面显示岗位可读中文，不暴露内部枚举或空名称'
);

foreach ($passes as $pass) {
    echo "[PASS] {$pass}\n";
}
foreach ($failures as $failure) {
    fwrite(STDERR, "[FAIL] {$failure}\n");
}
exit($failures === [] ? 0 : 1);
