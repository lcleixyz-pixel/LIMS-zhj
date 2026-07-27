<?php
declare(strict_types=1);

function ux_assert_contains(string $needle, string $haystack, string $message): void
{
    if (!str_contains($haystack, $needle)) {
        fwrite(STDERR, $message . PHP_EOL);
        fwrite(STDERR, 'Missing: ' . $needle . PHP_EOL);
        exit(1);
    }
}

function ux_assert_not_contains(string $needle, string $haystack, string $message): void
{
    if (str_contains($haystack, $needle)) {
        fwrite(STDERR, $message . PHP_EOL);
        fwrite(STDERR, 'Unexpected: ' . $needle . PHP_EOL);
        exit(1);
    }
}

$root = dirname(__DIR__);
$read = static fn (string $path): string => (string)file_get_contents($root . '/' . $path);

$migrationPath = $root . '/database/migrations/20260727_equipment_period_checks.sql';
$migration = is_file($migrationPath) ? (string)file_get_contents($migrationPath) : '';
$compose = $read('compose.governance-trial.yaml');
$periodController = $read('app/controller/EquipmentPeriodCheck.php');
$periodIndex = $read('app/view/equipment_period_check/index.html');
$periodAdd = $read('app/view/equipment_period_check/add.html');
$authorization = $read('app/service/ActionAuthorizationService.php');
$governedService = $read('app/service/GovernedChangeService.php');
$governedController = $read('app/controller/GovernedChange.php');
$governedInbox = $read('app/view/governed_change/inbox.html');
$dashboardController = $read('app/controller/Dashboard.php');
$dashboard = $read('app/view/dashboard/index.html');
$complianceController = $read('app/controller/Compliance.php');
$compliance = $read('app/view/compliance/index.html');
$layout = $read('app/view/layout/main.html');
$rbac = $read('app/middleware/Rbac.php');
$training = $read('app/view/training/index.html');
$governedPanel = $read('app/view/common/governed_change_panel.html');
$recordIndex = $read('app/view/record_form_instance/index.html');
$auditPlan = $read('app/view/audit_plan/index.html');
$employee = $read('app/view/employee/index.html');
$user = $read('app/view/user/index.html');
$reviewAction = $read('app/view/review_action/index.html');
$document = $read('app/view/document/view.html');
$login = $read('app/view/login/index.html');
$config = $read('config/qms.php');

ux_assert_contains(
    'CREATE TABLE IF NOT EXISTS `equipment_period_checks`',
    $migration,
    'UX-21: 期间核查计划必须有幂等迁移'
);
ux_assert_contains(
    './database/migrations/20260727_equipment_period_checks.sql:/docker-entrypoint-initdb.d/05-equipment-period-checks.sql:ro',
    $compose,
    'UX-21: 新建 8021 数据卷必须自动装载期间核查迁移'
);
ux_assert_contains("'tableReady' => \$tableReady", $periodController, 'UX-21: 控制器必须向视图提供迁移状态');
ux_assert_contains('功能尚未初始化', $periodIndex, 'UX-21: 缺表时必须显示可理解的降级说明');
ux_assert_contains('name="equipment_id" class="form-select"', $periodAdd, 'UX-21: 期间核查表单必须选择设备而非输入内部 ID');

ux_assert_contains("'governedchange.inbox' => true", $authorization, 'UX-14: 登录用户必须能进入更正申请中心');
ux_assert_contains('inboxRequestsForDisplay', $governedService, 'UX-14: 申请中心必须按身份返回申请');
ux_assert_contains("'canDecide' => \$canDecide", $governedController, 'UX-14: 控制器必须显式传入处理权限');
ux_assert_contains('更正申请中心', $governedInbox, 'UX-14: 统一入口必须使用申请人和审批人都能理解的名称');
ux_assert_contains('{if $canDecide}', $governedInbox, 'UX-14: 审批按钮必须受处理权限控制');

ux_assert_contains("qms_can_action('equipment', 'view')", $layout, 'UX-23: 设备菜单必须使用岗位动作权限');
ux_assert_contains('canViewEquipment', $dashboardController, 'UX-15: 仪表盘必须计算设备查看权限');
ux_assert_contains('{if $canViewEquipment}', $dashboard, 'UX-15: 仪表盘设备入口必须受权限控制');
ux_assert_contains('action_allowed', $complianceController, 'UX-22: 合规入口必须计算目标权限');
ux_assert_contains('请由具备相应岗位权限的人员处理', $compliance, 'UX-22: 无权限缺口必须说明下一步');
ux_assert_contains("'user/changepassword'", $rbac, 'UX-18: 所有登录用户必须能进入本人改密动作');
ux_assert_contains('href="/user/changePassword"', $layout, 'UX-18: 顶部改密入口必须使用本人改密路由');

ux_assert_contains('qmsChartEmptyAction', $dashboard, 'UX-4/5: 图表空态必须使用安全的引导组件');
ux_assert_not_contains('const node = document.getElementById(id);' . PHP_EOL . '    const node = document.getElementById(id);', $dashboard, 'UX-4: 图表初始化不得重复声明变量');
ux_assert_contains("'internal' => '内部培训'", $training, 'UX-9: 培训类型必须中文化');
ux_assert_contains("|| '未关联'", $governedPanel, 'UX-17: 空关联字段必须显示未关联');
ux_assert_contains('已生成（不可直接编辑）', $recordIndex, 'UX-13: PDF 状态必须解释编辑边界');
ux_assert_contains('<th>计划年度</th>', $auditPlan, 'UX-24: 内审计划表头必须使用业务名词');
ux_assert_contains('<th>工号</th>', $employee, 'UX-24: 员工表头必须使用业务名词');
ux_assert_contains('<th>用户名</th>', $user, 'UX-24: 用户表头必须使用业务名词');
ux_assert_contains('<th>责任人</th>', $reviewAction, 'UX-25: 评审决议不得暴露员工 ID 字样');
ux_assert_contains('文件详情 - {$doc.title', $document, 'UX-20: 文件详情标题必须以文件标题为主');
ux_assert_contains('当前页面问答', $layout, 'UX-19: Copilot 上下文模式必须使用用户语言');
ux_assert_contains('role="status"', $login, 'UX-2: 环境信息不得作为紧急警报');
ux_assert_contains('support_contact', $config, 'UX-3: 支持联系人必须可以配置');
ux_assert_contains('supportContact', $login, 'UX-3: 登录页必须在有配置时显示支持联系人');
ux_assert_contains('🔒 已冻结', $governedPanel, 'UX-L1: 冻结状态必须有明确锁定标识');
ux_assert_contains('qms-empty-state', $auditPlan . $read('app/view/capa/index.html'), 'UX-27: 审计点名列表必须使用引导空态');

$genericDeleteConfirmFiles = [];
foreach (glob($root . '/app/view/*/index.html') ?: [] as $path) {
    $content = (string)file_get_contents($path);
    if (str_contains($content, "confirm('确认删除？')") || str_contains($content, "confirm('确认删除？');")) {
        $genericDeleteConfirmFiles[] = str_replace($root . '/', '', $path);
    }
}
if ($genericDeleteConfirmFiles !== []) {
    fwrite(STDERR, 'UX-10: 以下列表仍使用无结果说明的删除确认：' . implode(', ', $genericDeleteConfirmFiles) . PHP_EOL);
    exit(1);
}

fwrite(STDOUT, "qms_ux_audit_remediation_smoke passed\n");
