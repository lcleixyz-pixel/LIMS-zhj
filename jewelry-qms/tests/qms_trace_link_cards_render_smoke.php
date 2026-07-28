<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/app/common.php';

use app\service\QmsDocumentStructureService;
use think\facade\Db;
use think\facade\View;

(new think\App())->initialize();

function trace_link_cards_render_assert(
    bool $condition,
    string $message
): void {
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

function trace_link_cards_render(
    string $blockId
): array {
    $detail = QmsDocumentStructureService::blockTraceReviewDetail($blockId);
    trace_link_cards_render_assert(
        $detail !== [],
        '8021 缺少追溯关系模板渲染样本：' . $blockId
    );

    View::layout(false);
    View::assign('detail', $detail);
    $html = View::fetch('planning_structure/link_review');

    trace_link_cards_render_assert(
        !str_contains($html, '{volist')
            && !str_contains($html, '{if'),
        '追溯关系页面不得残留未编译模板标签'
    );

    $document = new DOMDocument();
    libxml_use_internal_errors(true);
    $loaded = $document->loadHTML(
        '<?xml encoding="utf-8" ?>' . $html,
        LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
    );
    libxml_clear_errors();
    trace_link_cards_render_assert(
        $loaded,
        '追溯关系页面应能解析为 HTML'
    );

    return [
        'detail' => $detail,
        'html' => $html,
        'xpath' => new DOMXPath($document),
    ];
}

function trace_link_cards_class_xpath(string $className): string
{
    return 'contains(concat(" ", normalize-space(@class), " "), " '
        . $className . ' ")';
}

$beforeLinks = (int)Db::name('qms_document_block_links')
    ->where('publish', 1)
    ->where('soft_delete', 0)
    ->count();

$mixed = trace_link_cards_render(
    'af8ee423-1dad-429f-9399-336922ba7946'
);
$mixedCards = $mixed['xpath']->query(
    '//*[' . trace_link_cards_class_xpath(
        'qms-trace-link-card-priority'
    ) . ']'
);
trace_link_cards_render_assert(
    $mixedCards !== false && $mixedCards->length === 1,
    '历史混装样本应真实渲染一张优先处理卡片'
);
$mixedCard = $mixedCards->item(0);
trace_link_cards_render_assert(
    $mixedCard instanceof DOMElement,
    '历史混装卡片应为有效 HTML 元素'
);
trace_link_cards_render_assert(
    $mixed['xpath']->query(
        './/form[@action="/planning/structures/links/save"]',
        $mixedCard
    )->length === 0,
    '历史混装卡片不得渲染整体确认表单'
);
trace_link_cards_render_assert(
    $mixed['xpath']->query(
        './/form[@action="/planning/structures/links/delete"]'
            . '//input[@name="review_note" and @required]',
        $mixedCard
    )->length === 1,
    '历史混装卡片应保留必填删除说明'
);
trace_link_cards_render_assert(
    str_contains($mixed['html'], '查看拆分预览'),
    '历史混装卡片应真实渲染拆分预览'
);

$normal = trace_link_cards_render(
    '0019e50f-06e0-44e8-8033-d6058a1d2254'
);
$normalCards = $normal['xpath']->query(
    '//*[' . trace_link_cards_class_xpath('qms-trace-link-card')
        . ' and not(' . trace_link_cards_class_xpath(
            'qms-trace-link-card-priority'
        ) . ')]'
);
trace_link_cards_render_assert(
    $normalCards !== false && $normalCards->length === 1,
    '普通关系样本应真实渲染一张业务分组卡片'
);
$normalCard = $normalCards->item(0);
trace_link_cards_render_assert(
    $normalCard instanceof DOMElement,
    '普通关系卡片应为有效 HTML 元素'
);
trace_link_cards_render_assert(
    $normal['xpath']->query(
        './/form[@action="/planning/structures/links/save"]'
            . '//input[@name="link_id"]',
        $normalCard
    )->length === 1,
    '普通关系卡片应保留确认或调整表单'
);
foreach (['relation_type', 'confidence', 'note'] as $field) {
    trace_link_cards_render_assert(
        $normal['xpath']->query(
            './/*[@name="' . $field . '"]',
            $normalCard
        )->length === 1,
        '普通关系卡片的确认表单缺少字段：' . $field
    );
}
trace_link_cards_render_assert(
    $normal['xpath']->query(
        './/form[@action="/planning/structures/links/delete"]'
            . '//input[@name="review_note" and @required]',
        $normalCard
    )->length === 1,
    '普通关系卡片应保留必填删除说明'
);

$afterLinks = (int)Db::name('qms_document_block_links')
    ->where('publish', 1)
    ->where('soft_delete', 0)
    ->count();
trace_link_cards_render_assert(
    $beforeLinks === $afterLinks,
    '真实渲染追溯关系页面不得新增、修改或删除关系'
);

echo 'qms_trace_link_cards_render_smoke passed: '
    . json_encode([
        'mixed_total' => (int)$mixed['detail']['link_presentation']['total'],
        'normal_total' => (int)$normal['detail']['link_presentation']['total'],
        'links_before' => $beforeLinks,
        'links_after' => $afterLinks,
    ], JSON_UNESCAPED_UNICODE)
    . PHP_EOL;
