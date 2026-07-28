<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/app/common.php';

use app\service\QmsFileGovernanceWorkbenchService;
use think\facade\View;

(new think\App())->initialize();

function trace_work_item_render_assert(
    bool $condition,
    string $message
): void {
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

function trace_work_item_render_class_xpath(string $className): string
{
    return 'contains(concat(" ", normalize-space(@class), " "), " '
        . $className . ' ")';
}

function trace_work_item_render_url_multiset(array $urls): array
{
    $urls = array_map(
        static fn(mixed $url): string => (string)$url,
        $urls
    );
    sort($urls, SORT_STRING);

    return $urls;
}

$workbench = QmsFileGovernanceWorkbenchService::detail(
    '62ef7ecd-d270-4fc2-bccf-49c2986fa838'
);
trace_work_item_render_assert(
    $workbench !== [],
    '8021 缺少指定的 CX-03-02 GOV-TRIAL/0.2 结构化文件'
);

View::layout(false);
View::assign('workbench', $workbench);
$html = View::fetch('planning_structure/workbench');

trace_work_item_render_assert(
    !str_contains($html, '{if')
        && !str_contains($html, '{volist'),
    '连续办理页面不得残留未编译模板标签'
);

$document = new DOMDocument();
libxml_use_internal_errors(true);
$loaded = $document->loadHTML(
    '<?xml encoding="utf-8" ?>' . $html,
    LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
);
libxml_clear_errors();
trace_work_item_render_assert(
    $loaded,
    '连续办理页面应能解析为 HTML'
);
$xpath = new DOMXPath($document);

$sectionNodes = $xpath->query(
    '//section['
        . trace_work_item_render_class_xpath('qms-trace-work-items')
        . ']'
);
trace_work_item_render_assert(
    $sectionNodes !== false && $sectionNodes->length === 1,
    '工作台应真实渲染一个内容块连续办理区'
);
$section = $sectionNodes->item(0);
trace_work_item_render_assert(
    $section instanceof DOMElement,
    '内容块连续办理区应为有效 section 元素'
);

$traceWorkItems = (array)($workbench['trace_work_items'] ?? []);
$items = (array)($traceWorkItems['items'] ?? []);
$articleNodes = $xpath->query(
    './/article['
        . trace_work_item_render_class_xpath('qms-trace-work-item')
        . ']',
    $section
);
trace_work_item_render_assert(
    $articleNodes !== false
        && (int)($traceWorkItems['block_count'] ?? 0) > 0
        && $articleNodes->length
            === (int)($traceWorkItems['block_count'] ?? -1)
        && $articleNodes->length === count($items),
    '真实样本应渲染办理卡，且数量必须等于模型 block_count'
);

$expectedCandidateUrls = [];
$renderedCandidateUrls = [];
foreach ($items as $item) {
    $blockId = (string)($item['block_id'] ?? '');
    $articleList = $xpath->query(
        './/article['
            . trace_work_item_render_class_xpath('qms-trace-work-item')
            . ' and @data-block-id="' . $blockId . '"]',
        $section
    );
    trace_work_item_render_assert(
        $articleList !== false && $articleList->length === 1,
        '每个模型 block_id 应对应一张办理卡：' . $blockId
    );
    $article = $articleList->item(0);
    trace_work_item_render_assert(
        $article instanceof DOMElement,
        '内容块办理卡应为有效 article 元素：' . $blockId
    );

    $primaryLinks = $xpath->query(
        './/a[normalize-space(.)="处理此内容块"]',
        $article
    );
    trace_work_item_render_assert(
        $primaryLinks !== false && $primaryLinks->length === 1,
        '每张办理卡应恰有一个可见主入口：' . $blockId
    );
    $primaryLink = $primaryLinks->item(0);
    trace_work_item_render_assert(
        $primaryLink instanceof DOMElement
            && $primaryLink->getAttribute('href')
                === (string)($item['primary_url'] ?? ''),
        '办理卡主入口必须原样使用模型 primary_url：' . $blockId
    );

    foreach ((array)($item['candidates'] ?? []) as $candidate) {
        $expectedCandidateUrls[] = (string)(
            $candidate['review_url'] ?? ''
        );
    }
    $candidateLinks = $xpath->query(
        './/a[normalize-space(.)="带入此候选"]',
        $article
    );
    trace_work_item_render_assert(
        $candidateLinks !== false
            && $candidateLinks->length
                === count((array)($item['candidates'] ?? [])),
        '每张办理卡的候选链接数量必须与模型严格一致：' . $blockId
    );
    foreach ($candidateLinks as $candidateLink) {
        trace_work_item_render_assert(
            $candidateLink instanceof DOMElement,
            '候选办理入口必须是链接元素：' . $blockId
        );
        $renderedCandidateUrls[] = $candidateLink->getAttribute('href');
    }
}

trace_work_item_render_assert(
    $expectedCandidateUrls !== []
        && trace_work_item_render_url_multiset($renderedCandidateUrls)
        === trace_work_item_render_url_multiset($expectedCandidateUrls),
    '真实样本应有候选，且链接 URL 必须与模型 candidates 严格对应'
);
trace_work_item_render_assert(
    $xpath->query('//form')->length === 0,
    '文件治理工作台不得渲染任何 form'
);

foreach ([
    '治理候选',
    '系统设计链条',
    '下一步动作',
] as $existingSectionTitle) {
    trace_work_item_render_assert(
        $xpath->query(
            '//*[self::h4 or self::h5 or self::h6]'
                . '[contains(normalize-space(.), "'
                . $existingSectionTitle . '")]'
        )->length > 0,
        '连续办理区不得覆盖既有区域：' . $existingSectionTitle
    );
}

echo 'qms_trace_work_item_render_smoke passed: '
    . json_encode([
        'blocks' => count($items),
        'candidates' => count($renderedCandidateUrls),
    ], JSON_UNESCAPED_UNICODE)
    . PHP_EOL;
