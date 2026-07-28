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
$qmsCss = (string)file_get_contents($root . '/public/static/css/qms.css');

foreach ([
    '按内容块连续办理',
    '系统只负责归并和导航',
    '本块要做',
    '处理此内容块',
    '带入此候选',
    'workbench.trace_work_items',
    'qms-trace-work-items',
    'qms-trace-work-item',
    '当前没有需要汇总办理的内容块。',
    '当前没有可用办理入口，请返回追溯关系复核配置。',
    '<article',
    '<ol class="qms-trace-work-item-steps">',
    '<details',
    '<summary>查看问题明细（',
    '<summary>查看可带入候选（',
    'data-detail-kind="issues"',
    'data-detail-kind="candidates"',
    'aria-label="处理此内容块：',
    'aria-label="带入此候选：',
    '对象：{$traceIssue.context_label}',
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

foreach ([
    'workbench.trace_work_items.block_count',
    'workbench.trace_work_items.issue_count',
    'traceItem.section_number',
    'traceItem.title',
    'traceItem.block_id',
    'traceItem.block_type_label',
    'traceItem.priority',
    'traceItem.issue_count',
    'traceItem.issues',
    'traceIssue.label',
    'traceIssue.severity',
    'traceIssue.message',
    'traceIssue.context_label',
    'traceItem.steps',
    'traceStep.label',
    'traceStep.description',
    'traceItem.candidates',
    'traceCandidate.candidate_kind_label',
    'traceCandidate.target_label',
    'traceCandidate.relation_label',
    'traceCandidate.recommendation_reason',
    'traceCandidate.review_url',
    'traceItem.review_url',
] as $traceViewModelPath) {
    workbench_ui_assert(
        str_contains($workbenchView, $traceViewModelPath),
        '连续办理卡应直接消费 ViewModel 字段：' . $traceViewModelPath
    );
}
workbench_ui_assert(
    !str_contains($workbenchView, 'traceItem.primary_url'),
    '连续办理卡模板不得保留含混的 primary_url 别名'
);
workbench_ui_assert(
    str_contains(
        $workbenchView,
        'data-severity="{$traceIssue.severity}"'
    )
        && str_contains(
            $workbenchView,
            'class="qms-trace-work-item-issue-label"'
        ),
    '每个问题项应暴露语义严重级别，问题标签应使用独立 class'
);

$semanticReasonPosition = strpos($workbenchView, '自动识别原因');
$traceWorkItemsPosition = strpos(
    $workbenchView,
    '<section class="qms-trace-work-items'
);
$candidateTracePosition = strpos($workbenchView, '治理候选（不计闭环）');
workbench_ui_assert(
    $semanticReasonPosition !== false
        && $traceWorkItemsPosition !== false
        && $candidateTracePosition !== false
        && $semanticReasonPosition < $traceWorkItemsPosition
        && $traceWorkItemsPosition < $candidateTracePosition,
    '连续办理区应位于语义原因之后、治理候选之前'
);

$traceActionsPosition = strpos(
    $workbenchView,
    '<div class="qms-trace-work-item-actions">'
);
$traceIssueDetailsPosition = strpos(
    $workbenchView,
    'data-detail-kind="issues"'
);
$traceCandidateDetailsPosition = strpos(
    $workbenchView,
    'data-detail-kind="candidates"'
);
workbench_ui_assert(
    $traceActionsPosition !== false
        && $traceIssueDetailsPosition !== false
        && $traceCandidateDetailsPosition !== false
        && $traceActionsPosition < $traceIssueDetailsPosition
        && $traceActionsPosition < $traceCandidateDetailsPosition,
    '主办理动作源码顺序应早于问题和候选折叠明细'
);
workbench_ui_assert(
    preg_match('/<details\b[^>]*\sopen(?:\s|=|>)/i', $workbenchView)
        !== 1,
    '问题和候选 details 默认不得展开'
);
workbench_ui_assert(
    preg_match(
        '/aria-label="带入此候选：[^"]*\{\$traceCandidate\.target_id\}/u',
        $workbenchView
    ) !== 1,
    '候选入口读屏名称不得包含 target_id 等内部标识'
);

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
    preg_match('/<\s*form\b/i', $workbenchView) !== 1,
    'v0.1 工作台不得包含写入表单'
);
workbench_ui_assert(
    preg_match(
        '/method\s*=\s*(?:"\s*post\s*"|\'\s*post\s*\'|post\b)/i',
        $workbenchView
    ) !== 1
        && preg_match('/<\s*script\b/i', $workbenchView) !== 1,
    '连续办理工作台不得包含 POST 或脚本'
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

$traceCssPosition = strpos(
    $qmsCss,
    '.qms-trace-work-items .card-header'
);
workbench_ui_assert(
    $traceCssPosition !== false,
    'qms.css 应提供连续办理命名空间样式'
);
$traceCss = substr($qmsCss, $traceCssPosition);
foreach ([
    '.qms-trace-work-items-list',
    '.qms-trace-work-item',
    '.qms-trace-work-item-actions',
    '.qms-trace-work-item[data-priority="blocked"]',
    '.qms-trace-work-item[data-priority="review"]',
    '.qms-trace-work-item-issue-label',
    '.qms-trace-work-item-issue[data-severity="blocked"]',
    '.qms-trace-work-item-issue[data-severity="review"]',
    '.qms-trace-work-item-details',
    '.qms-trace-work-item-details > summary',
    '.qms-trace-work-item-details > summary:focus-visible',
    '.qms-trace-work-items a:focus-visible',
    '@media (max-width: 767.98px)',
] as $traceCssContract) {
    workbench_ui_assert(
        str_contains($traceCss, $traceCssContract),
        '连续办理样式缺少：' . $traceCssContract
    );
}
workbench_ui_assert(
    preg_match(
        '/\.qms-trace-work-item-issue\[data-severity="blocked"\]'
            . ' \.qms-trace-work-item-issue-label\s*\{[^}]*'
            . 'color\s*:\s*var\(--qms-bad\)\s*;/s',
        $traceCss
    ) === 1
        && preg_match(
            '/\.qms-trace-work-item-issue\[data-severity="review"\]'
                . ' \.qms-trace-work-item-issue-label\s*\{[^}]*'
                . 'color\s*:\s*#7a4510\s*;/si',
            $traceCss
        ) === 1,
    '问题标签应按严重级别使用高对比颜色：blocked 为危险色，review 为深警告色'
);
workbench_ui_assert(
    preg_match(
        '/\.qms-trace-work-items-list\s*\{[^}]*'
            . 'grid-template-columns\s*:\s*minmax\(0,\s*1fr\)/s',
        $traceCss
    ) === 1,
    '连续办理卡列表应明确保持单列'
);
foreach (['blocked', 'review'] as $priority) {
    workbench_ui_assert(
        preg_match(
            '/\.qms-trace-work-item\[data-priority="'
                . $priority
                . '"\]\s*\{[^}]*border-left-color\s*:/s',
            $traceCss
        ) === 1,
        $priority . ' 办理卡应有命名空间内的左边框颜色'
    );
}
workbench_ui_assert(
    preg_match(
        '/@media\s*\(max-width:\s*767\.98px\)\s*\{'
            . '(?s:.*\.qms-trace-work-item-actions[^}]*'
            . 'flex-direction\s*:\s*column)'
            . '(?s:.*\.qms-trace-work-item-actions \.btn[^}]*'
            . 'width\s*:\s*100%)/',
        $traceCss
    ) === 1,
    '窄屏下办理动作应纵向排列且按钮占满宽度'
);
workbench_ui_assert(
    preg_match(
        '/\.qms-trace-work-item-details\s*>\s*summary\s*\{[^}]*'
            . 'min-height\s*:\s*44px[^}]*padding\s*:/s',
        $traceCss
    ) === 1,
    '折叠摘要应有清楚间距和至少 44px 触控区域'
);
workbench_ui_assert(
    preg_match(
        '/\.qms-trace-work-item-meta\s*\{[^}]*'
            . 'color\s*:\s*var\(--qms-ink\)\s*;/s',
        $traceCss
    ) === 1
        && preg_match(
            '/\.qms-trace-work-item-priority\s*\{[^}]*'
                . 'color\s*:\s*var\(--qms-ink\)\s*;/s',
            $traceCss
        ) === 1
        && preg_match(
            '/\.qms-trace-work-item\[data-priority="blocked"\]'
                . ' \.qms-trace-work-item-priority\s*\{[^}]*'
                . 'color\s*:\s*var\(--qms-bad\)\s*;/s',
            $traceCss
        ) === 1,
    '12px 元信息与 review 优先级应使用高对比正文色，blocked 使用高对比危险色'
);
$metaAndPriorityCss = '';
foreach ([
    '.qms-trace-work-item-meta',
    '.qms-trace-work-item-priority',
] as $contrastSelector) {
    if (
        preg_match(
            '/' . preg_quote($contrastSelector, '/')
                . '\s*\{([^}]*)\}/s',
            $traceCss,
            $contrastRule
        ) === 1
    ) {
        $metaAndPriorityCss .= (string)$contrastRule[1];
    }
}
workbench_ui_assert(
    !str_contains($metaAndPriorityCss, 'var(--qms-ink-2)')
        && !str_contains($metaAndPriorityCss, 'var(--qms-warn)')
        && !str_contains(strtolower($metaAndPriorityCss), '#718096')
        && !str_contains(strtolower($metaAndPriorityCss), '#b7791f'),
    '小号元信息和 review 标签不得使用低对比灰色或警示色'
);
$traceRuleCount = preg_match_all(
    '/([^{}]+)\{([^{}]*)\}/s',
    $traceCss,
    $traceRules,
    PREG_SET_ORDER
);
workbench_ui_assert(
    $traceRuleCount !== false && $traceRuleCount > 0,
    '应能解析连续办理命名空间样式规则'
);
foreach ($traceRules as $traceRule) {
    $selector = (string)($traceRule[1] ?? '');
    if (!str_contains($selector, '.qms-trace-work-item')) {
        continue;
    }
    workbench_ui_assert(
        preg_match(
            '/\b(?:animation|transition)(?:-[a-z-]+)?\s*:/i',
            (string)($traceRule[2] ?? '')
        ) !== 1,
        '连续办理命名空间不得新增动画或过渡：'
            . trim($selector)
    );
}

echo "qms_file_governance_workbench_ui_smoke passed\n";
