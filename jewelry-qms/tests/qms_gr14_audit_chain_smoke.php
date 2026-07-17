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

foreach ($passes as $pass) {
    echo "[PASS] {$pass}\n";
}
foreach ($failures as $failure) {
    fwrite(STDERR, "[FAIL] {$failure}\n");
}
exit($failures === [] ? 0 : 1);
