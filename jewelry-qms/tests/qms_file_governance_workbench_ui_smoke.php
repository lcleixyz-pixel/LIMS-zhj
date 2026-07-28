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
$linkReviewView = (string)file_get_contents($root . '/app/view/planning_structure/link_review.html');

foreach ([
    '按内容块连续办理',
    '系统只负责归并和导航',
    '本块要做',
    '处理此内容块',
    '带入此候选',
    'workbench.trace_work_items',
    'qms-trace-work-items',
    'qms-trace-work-item',
    '治理候选（不计闭环）',
    '候选·待复核',
    '候选来源',
    '建议办理到',
    '建议用途',
    '带入此候选',
    '系统设计链条',
    '外部依据',
    '手册条款',
    '程序落实方法',
    '运行证据',
    '已确认主链',
    '辅助关系',
    '等待复核',
    '疑似错挂',
    '自动识别依据',
    '自动识别原因',
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
foreach ([
    'workbench.candidate_trace',
    'candidateSource.routing_issue',
    'candidateSection.routing_issue',
    'candidateRecord.routing_issue',
    'workbench.chain.confirmed_external_sources',
    'workbench.chain.confirmed_manual_sections',
    'workbench.chain.confirmed_record_evidence',
    'workbench.semantic_guard',
] as $viewModelPath) {
    workbench_ui_assert(
        str_contains($workbenchView, $viewModelPath),
        '工作台应展示语义防错数据：' . $viewModelPath
    );
}
workbench_ui_assert(
    str_contains($workbenchView, 'section.reason_label'),
    '手册条款异常标签应消费服务提供的原因短标签'
);
workbench_ui_assert(
    !str_contains($workbenchView, '语义防错提示'),
    '工作台不应重复堆叠同一语义警告'
);
workbench_ui_assert(
    !str_contains($workbenchView, '<form'),
    'v0.1 工作台不得包含写入表单'
);
workbench_ui_assert(
    !str_contains($workbenchView, '>进入人工追溯复核<'),
    '有逐条候选入口后不应继续保留含糊的文件级复核按钮'
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
workbench_ui_assert(
    str_contains($linkReviewView, 'name="link_id"')
    && str_contains($linkReviewView, '确认或调整关系')
    && str_contains($linkReviewView, 'link.governance_state')
    && str_contains($linkReviewView, '移除此错误手册挂接'),
    '追溯复核页应能直接确认主链或调整为辅助关系'
);

echo "qms_file_governance_workbench_ui_smoke passed\n";
