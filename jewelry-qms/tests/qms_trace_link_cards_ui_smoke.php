<?php
declare(strict_types=1);

function trace_link_cards_ui_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

$template = file_get_contents(
    dirname(__DIR__) . '/app/view/planning_structure/link_review.html'
);
$css = file_get_contents(dirname(__DIR__) . '/public/static/css/qms.css');

trace_link_cards_ui_assert(
    is_string($template) && is_string($css),
    '应能读取追溯复核页面和样式'
);

foreach ([
    'qms-trace-priority',
    'qms-trace-group-grid',
    'qms-trace-link-card',
    '需优先处理',
    '主链证据',
    '确认或调整关系',
    'detail.link_presentation.priority',
    'detail.link_presentation.groups',
] as $needle) {
    trace_link_cards_ui_assert(
        str_contains($template, $needle),
        '追溯关系卡片页面缺少：' . $needle
    );
}

trace_link_cards_ui_assert(
    !str_contains($template, '<table'),
    '当前追溯关系不应继续使用横向宽表'
);

foreach ([
    '/planning/structures/links/save',
    '/planning/structures/links/delete',
    'name="link_id"',
    'name="relation_type"',
    'name="confidence"',
    'name="review_note"',
] as $needle) {
    trace_link_cards_ui_assert(
        str_contains($template, $needle),
        '卡片改造必须保留原有办理能力：' . $needle
    );
}

foreach ([
    '.qms-trace-group-grid',
    '.qms-trace-link-card',
    '@media (max-width: 767.98px)',
    'grid-template-columns: 1fr;',
] as $needle) {
    trace_link_cards_ui_assert(
        str_contains($css, $needle),
        '追溯关系卡片样式缺少：' . $needle
    );
}

echo 'qms_trace_link_cards_ui_smoke passed' . PHP_EOL;
