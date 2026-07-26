<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$route = (string)file_get_contents($root . '/route/app.php');
$controller = (string)file_get_contents($root . '/app/controller/PlanningStructure.php');
$service = (string)file_get_contents($root . '/app/service/QmsFileGovernanceWorkbenchService.php');

function workbench_ui_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

workbench_ui_assert(
    str_contains($route, "Route::get('planning/structures/workbench'"),
    '应提供只读工作台 GET 路由'
);
workbench_ui_assert(
    str_contains($controller, 'public function workbench()'),
    'PlanningStructure 应提供 workbench 动作'
);
workbench_ui_assert(
    str_contains($service, 'public static function detail('),
    '工作台服务应提供真实数据入口'
);

$workbenchView = (string)@file_get_contents($root . '/app/view/planning_structure/workbench.html');
$indexView = (string)file_get_contents($root . '/app/view/planning_structure/index.html');
$detailView = (string)file_get_contents($root . '/app/view/planning_structure/view.html');

foreach ([
    '系统设计链条',
    '外部依据',
    '手册条款',
    '程序落实方法',
    '运行证据',
    '文件内容与审查材料',
    '下一步动作',
    '纸质体系仍为唯一正式体系',
] as $text) {
    workbench_ui_assert(str_contains($workbenchView, $text), '工作台缺少文案：' . $text);
}

workbench_ui_assert(
    str_contains($workbenchView, 'workbench.chain.external_sources'),
    '外部依据数量必须来自 ViewModel'
);
workbench_ui_assert(
    !str_contains($workbenchView, '<form'),
    'v0.1 工作台不得包含写入表单'
);
workbench_ui_assert(
    str_contains($indexView, '/planning/structures/workbench?id='),
    '结构化文件列表应提供工作台入口'
);
workbench_ui_assert(
    str_contains($detailView, '/planning/structures/workbench?id='),
    '结构化文件详情应提供工作台入口'
);
workbench_ui_assert(
    str_contains($workbenchView, 'col-12 col-lg-8')
    && str_contains($workbenchView, 'col-12 col-lg-4'),
    '工作台应明确提供窄屏单列和宽屏双列布局'
);

echo "qms_file_governance_workbench_ui_smoke passed\n";
