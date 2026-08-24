<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../app/common.php';

(new think\App())->initialize();

function quality_workbench_path_b_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

$routes = (string)file_get_contents(__DIR__ . '/../route/app.php');
$menu = (string)file_get_contents(__DIR__ . '/../app/view/layout/main.html');
$home = (string)file_get_contents(__DIR__ . '/../app/view/quality_workbench/index.html');
$controller = (string)file_get_contents(__DIR__ . '/../app/controller/QualityWorkbench.php');
$authorization = (string)file_get_contents(__DIR__ . '/../app/service/ActionAuthorizationService.php');
$qmsConfig = require __DIR__ . '/../config/qms.php';

$detailPosition = strpos($routes, "quality-workbench/projects/view', 'QualityWorkbench/view");
$listPosition = strpos($routes, "quality-workbench/projects', 'QualityWorkbench/projects");
$homePosition = strpos($routes, "quality-workbench', 'QualityWorkbench/index");
quality_workbench_path_b_assert(
    is_int($detailPosition) && is_int($listPosition) && is_int($homePosition)
        && $detailPosition < $listPosition && $listPosition < $homePosition,
    '质量工作台路由必须按详情、列表、首页顺序登记'
);

foreach (['我的工作', '查文件', '查记录', '质量管理'] as $label) {
    quality_workbench_path_b_assert(str_contains($menu, $label), '顶部入口缺少：' . $label);
}
quality_workbench_path_b_assert(str_contains($menu, "qms_can_action('qualityworkbench', 'govern')"), '质量管理入口必须单独授权');
quality_workbench_path_b_assert(in_array('qualityworkbench', $qmsConfig['permissions']['staff'] ?? [], true), '普通员工必须能进入我的工作');
quality_workbench_path_b_assert(in_array('qualityworkbench', $qmsConfig['position_permissions']['record_operator'] ?? [], true), '记录填报员必须能进入我的工作');
quality_workbench_path_b_assert(str_contains($authorization, "'qualityworkbench.govern'"), '评审项目读取必须有质量治理授权');

foreach (['今天需要处理', '最近阅读文件', '快速查文件', '快速查记录', '与我相关的提醒'] as $label) {
    quality_workbench_path_b_assert(str_contains($home, $label), '我的工作首页缺少：' . $label);
}
quality_workbench_path_b_assert(!str_contains($home, '结构化块'), '普通员工首页不得出现结构化块术语');
quality_workbench_path_b_assert(!str_contains($home, '要素矩阵'), '普通员工首页不得出现要素矩阵术语');
quality_workbench_path_b_assert(!str_contains($home, 'qms-my-work__notice'), '运行依据必须由日常层统一显示，不得在首页重复');
quality_workbench_path_b_assert(str_contains($controller, "Session::get('recent_document_reads'"), '我的工作必须读取最近阅读文件');
quality_workbench_path_b_assert(str_contains($controller, "Db::name('notification_users')"), '与我相关的提醒必须按当前用户读取通知');
quality_workbench_path_b_assert(str_contains($home, '$myReminders'), '普通员工首页不得用全局项目事件冒充个人提醒');

echo "qms_quality_workbench_path_b_smoke passed\n";
