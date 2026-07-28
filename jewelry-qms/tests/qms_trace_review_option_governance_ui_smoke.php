<?php
declare(strict_types=1);

$viewPath = dirname(__DIR__)
    . '/app/view/planning_structure/link_review.html';
$view = (string)file_get_contents($viewPath);

function trace_option_ui_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

trace_option_ui_assert(
    str_contains($view, 'data-secondary='),
    '追溯选项应输出历史/其他版本标记'
);
trace_option_ui_assert(
    str_contains($view, '显示历史/其他版本'),
    '页面应提供中文的历史/其他版本展开入口'
);
trace_option_ui_assert(
    str_contains($view, '疑似乱码外部条款')
        && str_contains($view, '已有历史关系未删除'),
    '页面应解释乱码条款只禁止新增、不会删除历史关系'
);
trace_option_ui_assert(
    str_contains($view, 'option_governance_summary.clauses'),
    '页面应使用服务端乱码隔离统计'
);
trace_option_ui_assert(
    substr_count($view, '{$option.governance_label}') >= 7,
    '七类证明对象均应使用治理后的中文标签'
);
trace_option_ui_assert(
    str_contains($view, 'option.selected')
        && str_contains($view, 'showSecondary'),
    '关闭历史范围时应保留当前已选的历史对象'
);
trace_option_ui_assert(
    str_contains($view, '历史/其他版本已隐藏'),
    '搜索结果应明确告知当前隐藏的历史/其他版本数量'
);

echo "qms_trace_review_option_governance_ui_smoke passed\n";
