<?php
declare(strict_types=1);

$root = dirname(__DIR__);
require $root . '/vendor/autoload.php';
require $root . '/app/common.php';

$app = new think\App();
$app->initialize();

use app\service\RbacService;
use think\facade\Db;
use think\facade\Session;

function responsibility_ui_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

function responsibility_ui_contains(string $needle, string $haystack, string $message): void
{
    responsibility_ui_assert(str_contains($haystack, $needle), $message . ' (missing: ' . $needle . ')');
}

$route = (string)file_get_contents($root . '/route/app.php');
$config = (string)file_get_contents($root . '/config/qms.php');
$layout = (string)file_get_contents($root . '/app/view/layout/main.html');
$controllerPath = $root . '/app/controller/PlanningResponsibility.php';
$viewPath = $root . '/app/view/planning_responsibility/index.html';
$employeeController = (string)file_get_contents($root . '/app/controller/Employee.php');
$employeeView = (string)file_get_contents($root . '/app/view/employee/view.html');
$auditLog = (string)file_get_contents($root . '/app/middleware/AuditLog.php');
$rbacMiddleware = (string)file_get_contents($root . '/app/middleware/Rbac.php');

responsibility_ui_assert(is_file($controllerPath), 'Responsibility controller exists');
responsibility_ui_assert(is_file($viewPath), 'Responsibility page exists');
$controller = (string)file_get_contents($controllerPath);
$view = (string)file_get_contents($viewPath);

foreach ([
    'planning/responsibilities',
    'planning/responsibilities/assignments/save',
    'planning/responsibilities/validate',
    'planning/responsibilities/submit',
    'planning/responsibilities/approve',
    'planning/responsibilities/bootstrap/general-manager',
    'planning/responsibilities/bootstrap/lab-director',
    'planning/responsibilities/alignment',
] as $path) {
    responsibility_ui_contains($path, $route, 'Route contains ' . $path);
}
$baseResponsibilityPost = strpos($route, "Route::post('planning/responsibilities',");
responsibility_ui_assert($baseResponsibilityPost !== false, 'Base responsibility POST route exists');
foreach (['assignments/save', 'validate', 'submit', 'approve', 'bootstrap/general-manager', 'bootstrap/lab-director'] as $specificPath) {
    $specificPosition = strpos($route, "Route::post('planning/responsibilities/{$specificPath}'");
    responsibility_ui_assert(
        $specificPosition !== false && $specificPosition < $baseResponsibilityPost,
        'Specific responsibility POST route precedes the base draft-creation route: ' . $specificPath
    );
}

foreach ([
    'index', 'createInitialDraft', 'saveAssignment', 'removeAssignment', 'validateVersion',
    'submitVersion', 'registerGeneralManager', 'requestLabDirector', 'approve', 'alignment',
] as $method) {
    responsibility_ui_contains('function ' . $method . '(', $controller, 'Controller exposes ' . $method);
}

foreach (['责任结构', '人员配置', '校验与签批', '有效责任链', '岗位尚未绑定人员', '运行时指定'] as $label) {
    responsibility_ui_contains($label, $view, 'Responsibility page contains ' . $label);
}
foreach (['structure', 'staffing', 'approval', 'effective', 'alignment'] as $mode) {
    responsibility_ui_contains('view=' . $mode, $view, 'Responsibility page exposes view ' . $mode);
}

responsibility_ui_contains('/planning/responsibilities', $layout, 'Planning navigation links responsibility page');
responsibility_ui_contains('planningresponsibility', $config, 'All logged-in roles can be granted responsibility-page read access');
responsibility_ui_contains("['admin', 'quality_manager']", $controller, 'Management writes are explicitly limited');
responsibility_ui_contains('QmsResponsibilityApprovalService::approve', $controller, 'Business approval delegates to approval service');
responsibility_ui_contains('planningresponsibility', $rbacMiddleware, 'RBAC leaves business approval to appointment-aware service');

foreach (['createinitialdraft', 'saveassignment', 'removeassignment', 'validateversion', 'submitversion', 'registergeneralmanager', 'requestlabdirector', 'approve'] as $action) {
    responsibility_ui_contains("'{$action}'", $auditLog, 'Audit log covers ' . $action);
}
responsibility_ui_contains("post('operation'", $auditLog, 'Audit log distinguishes remove operation on the shared assignment route');

foreach (['source_kind', 'source_chain_version_id', 'source_responsibility_id', 'source_approval_id'] as $field) {
    responsibility_ui_contains($field, $employeeController, 'Employee appointment query exposes ' . $field);
}
foreach (['来源类型', '责任链版本', '签批证据', 'legacy_document'] as $label) {
    responsibility_ui_contains($label, $employeeView, 'Employee evidence view contains ' . $label);
}

Session::set('user.role', 'staff');
responsibility_ui_assert(RbacService::canAccess('PlanningResponsibility'), 'staff can read responsibility page');
responsibility_ui_assert(!RbacService::canWrite('PlanningResponsibility'), 'staff receives no generic responsibility write permission');
Session::set('user.role', 'quality_manager');
responsibility_ui_assert(RbacService::canWrite('PlanningResponsibility'), 'quality manager can edit responsibility drafts');
Session::delete('user.role');

$companyId = (string)config('qms.company_id');
$employeeId = (string)Db::name('employees')->where('company_id', $companyId)->where('soft_delete', 0)->value('id');
if ($employeeId !== '') {
    $id = qms_uuid();
    Db::name('employee_appointments')->insert([
        'id' => $id,
        'company_id' => $companyId,
        'employee_id' => $employeeId,
        'appointment_key' => 'ui-legacy-default:' . $id,
        'appointment_type' => 'role',
        'position_name' => '旧导入兼容测试',
        'appointed_at' => date('Y-m-d'),
        'status' => 'active',
        'publish' => 1,
        'soft_delete' => 0,
        'created' => date('Y-m-d H:i:s'),
        'modified' => date('Y-m-d H:i:s'),
    ]);
    responsibility_ui_assert(
        Db::name('employee_appointments')->where('id', $id)->value('source_kind') === 'legacy_document',
        'Old-field appointment insert defaults source_kind to legacy_document'
    );
    Db::name('employee_appointments')->where('id', $id)->delete();
}

echo "qms_responsibility_ui_smoke passed\n";
