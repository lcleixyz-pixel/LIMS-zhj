<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../app/common.php';

use app\service\QualityWorkbenchService;

(new think\App())->initialize();

function qms_quality_workbench_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

$service = new QualityWorkbenchService();
$plan = $service->previewSystemProjects();

qms_quality_workbench_assert(count($plan['projects'] ?? []) === 8, '质量工作台必须固定生成8类系统评审项目');

$codes = array_column($plan['projects'], 'project_code');
foreach ([
    'system-document-trace',
    'system-record-forms',
    'system-responsibility',
    'system-equipment',
    'system-personnel',
    'system-audit',
    'system-management-review',
    'system-improvement-risk',
] as $code) {
    qms_quality_workbench_assert(in_array($code, $codes, true), '缺少系统评审项目：' . $code);
}

$labels = QualityWorkbenchService::labels();
qms_quality_workbench_assert(($labels['project_status']['active'] ?? '') === '进行中', 'active 必须显示为进行中');
qms_quality_workbench_assert(($labels['project_status']['blocked'] ?? '') === '被阻断', 'blocked 必须显示为被阻断');
qms_quality_workbench_assert(($labels['task_status']['review_required'] ?? '') === '待人工复核', 'review_required 必须显示为待人工复核');
qms_quality_workbench_assert(($labels['severity']['blocker'] ?? '') === '阻断项', 'blocker 必须显示为阻断项');

$command = (string)file_get_contents(__DIR__ . '/../app/command/QmsQualityWorkbenchRefresh.php');
qms_quality_workbench_assert(str_contains($command, "qms:refresh-quality-workbench"), '必须新增质量工作台刷新命令');
qms_quality_workbench_assert(str_contains($command, 'ack-quality-workbench'), '写入命令必须要求确认参数');

$routes = (string)file_get_contents(__DIR__ . '/../route/app.php');
qms_quality_workbench_assert(str_contains($routes, "quality-workbench', 'QualityWorkbench/index"), '必须登记质量工作台首页路由');
qms_quality_workbench_assert(str_contains($routes, "quality-workbench/projects/view', 'QualityWorkbench/view"), '必须登记项目详情路由');

$menu = (string)file_get_contents(__DIR__ . '/../app/view/layout/main.html');
qms_quality_workbench_assert(str_contains($menu, '质量工作台'), '顶部菜单必须出现质量工作台入口');
qms_quality_workbench_assert(str_contains($menu, "qms_can('qualityworkbench')"), '质量工作台菜单必须受权限控制');

$qmsConfig = (string)file_get_contents(__DIR__ . '/../config/qms.php');
qms_quality_workbench_assert(str_contains($qmsConfig, 'qualityworkbench'), '质量负责人权限必须包含质量工作台');

$schema = implode("\n", QualityWorkbenchService::schemaSql());
qms_quality_workbench_assert(str_contains($schema, 'quality_review_projects'), '必须提供项目表结构');
qms_quality_workbench_assert(str_contains($schema, 'quality_review_tasks'), '必须提供任务表结构');
qms_quality_workbench_assert(str_contains($schema, 'quality_review_events'), '必须提供事件表结构');

echo "qms_quality_workbench_smoke passed\n";
