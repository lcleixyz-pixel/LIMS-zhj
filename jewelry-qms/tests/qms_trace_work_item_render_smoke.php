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

function trace_work_item_render_assert_compiled(
    string $html,
    string $scenario
): void {
    $templateResiduePattern = '/\{(?:'
        . '\/?(?:if|elseif|else|volist|notempty|empty)\b[^}]*'
        . '|\$[^}]+'
        . ')\}/u';
    trace_work_item_render_assert(
        preg_match($templateResiduePattern, $html) !== 1,
        $scenario . ' 不得残留未编译模板标签或变量'
    );
}

function trace_work_item_render_parse(
    string $html,
    string $scenario
): array {
    $document = new DOMDocument();
    libxml_use_internal_errors(true);
    $loaded = $document->loadHTML(
        '<?xml encoding="utf-8" ?>' . $html,
        LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
    );
    libxml_clear_errors();
    trace_work_item_render_assert(
        $loaded,
        $scenario . ' 应能解析为 HTML'
    );

    return [
        'document' => $document,
        'xpath' => new DOMXPath($document),
    ];
}

function trace_work_item_render_workbench(
    array $workbench,
    string $scenario
): array {
    View::layout(false);
    View::assign('workbench', $workbench);
    $html = View::fetch('planning_structure/workbench');
    trace_work_item_render_assert_compiled($html, $scenario);
    $parsed = trace_work_item_render_parse($html, $scenario);

    return [
        'html' => $html,
        'document' => $parsed['document'],
        'xpath' => $parsed['xpath'],
    ];
}

function trace_work_item_render_section(
    DOMXPath $xpath,
    string $scenario
): DOMElement {
    $sectionNodes = $xpath->query(
        '//section['
            . trace_work_item_render_class_xpath('qms-trace-work-items')
            . ']'
    );
    trace_work_item_render_assert(
        $sectionNodes !== false && $sectionNodes->length === 1,
        $scenario . ' 应真实渲染一个内容块连续办理区'
    );
    $section = $sectionNodes->item(0);
    trace_work_item_render_assert(
        $section instanceof DOMElement,
        $scenario . ' 的连续办理区应为有效 section 元素'
    );

    return $section;
}

function trace_work_item_render_article(
    DOMXPath $xpath,
    DOMElement $section,
    string $blockId
): ?DOMElement {
    $articles = $xpath->query(
        './/article['
            . trace_work_item_render_class_xpath('qms-trace-work-item')
            . ' and @data-block-id="' . $blockId . '"]',
        $section
    );
    if ($articles === false || $articles->length !== 1) {
        return null;
    }
    $article = $articles->item(0);

    return $article instanceof DOMElement ? $article : null;
}

function trace_work_item_render_candidates_match_by_article(
    DOMXPath $xpath,
    DOMElement $section,
    array $items
): bool {
    foreach ($items as $item) {
        $blockId = (string)($item['block_id'] ?? '');
        $article = trace_work_item_render_article(
            $xpath,
            $section,
            $blockId
        );
        if ($article === null) {
            return false;
        }

        $expectedUrls = [];
        foreach ((array)($item['candidates'] ?? []) as $candidate) {
            $expectedUrls[] = (string)($candidate['review_url'] ?? '');
        }

        $candidateLinks = $xpath->query(
            './/a[normalize-space(.)="带入此候选"]',
            $article
        );
        if (
            $candidateLinks === false
            || $candidateLinks->length !== count($expectedUrls)
        ) {
            return false;
        }

        $renderedUrls = [];
        foreach ($candidateLinks as $candidateLink) {
            if (!$candidateLink instanceof DOMElement) {
                return false;
            }
            $url = $candidateLink->getAttribute('href');
            $parts = parse_url($url);
            if (!is_array($parts)) {
                return false;
            }
            parse_str((string)($parts['query'] ?? ''), $query);
            if ((string)($query['block_id'] ?? '') !== $blockId) {
                return false;
            }
            $renderedUrls[] = $url;
        }

        if (
            trace_work_item_render_url_multiset($renderedUrls)
            !== trace_work_item_render_url_multiset($expectedUrls)
        ) {
            return false;
        }
    }

    return true;
}

$workbench = QmsFileGovernanceWorkbenchService::detail(
    '62ef7ecd-d270-4fc2-bccf-49c2986fa838'
);
trace_work_item_render_assert(
    $workbench !== [],
    '8021 缺少指定的 CX-03-02 GOV-TRIAL/0.2 结构化文件'
);

$rendered = trace_work_item_render_workbench($workbench, '真实工作台');
$xpath = $rendered['xpath'];
$section = trace_work_item_render_section($xpath, '真实工作台');
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

$expectedCandidateCount = 0;
$expectedIssueCount = 0;
$primaryAriaLabels = [];
$candidateAriaLabels = [];
foreach ($items as $item) {
    $blockId = (string)($item['block_id'] ?? '');
    $article = trace_work_item_render_article($xpath, $section, $blockId);
    trace_work_item_render_assert(
        $article instanceof DOMElement,
        '每个模型 block_id 应对应一张办理卡：' . $blockId
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
    $primaryAriaLabel = $primaryLink->getAttribute('aria-label');
    trace_work_item_render_assert(
        $primaryAriaLabel !== ''
            && str_contains(
                $primaryAriaLabel,
                (string)($item['section_number'] ?? '')
            )
            && str_contains(
                $primaryAriaLabel,
                (string)($item['title'] ?? '')
            ),
        '主入口 aria-label 应包含章节和内容块标题：' . $blockId
    );
    $primaryAriaLabels[] = $primaryAriaLabel;

    $issues = (array)($item['issues'] ?? []);
    $expectedIssueCount += count($issues);
    $issueDetails = $xpath->query(
        './details['
            . trace_work_item_render_class_xpath(
                'qms-trace-work-item-details'
            )
            . ' and @data-detail-kind="issues"]',
        $article
    );
    trace_work_item_render_assert(
        $issueDetails !== false && $issueDetails->length === 1,
        '每张卡应有一个问题明细 details：' . $blockId
    );
    $issueDetail = $issueDetails->item(0);
    trace_work_item_render_assert(
        $issueDetail instanceof DOMElement
            && !$issueDetail->hasAttribute('open')
            && $xpath->query(
                './summary[normalize-space(.)="查看问题明细（'
                    . count($issues) . '）"]',
                $issueDetail
            )->length === 1,
        '问题明细应默认关闭并显示准确数量：' . $blockId
    );
    foreach ($issues as $issue) {
        $contextLabel = (string)($issue['context_label'] ?? '');
        trace_work_item_render_assert(
            $contextLabel !== ''
                && str_contains(
                    $issueDetail->textContent,
                    '对象：' . $contextLabel
                ),
            '问题明细应显示服务提供的对象说明：' . $blockId
        );
    }

    $candidates = (array)($item['candidates'] ?? []);
    $expectedCandidateCount += count($candidates);
    $candidateDetails = $xpath->query(
        './details['
            . trace_work_item_render_class_xpath(
                'qms-trace-work-item-details'
            )
            . ' and @data-detail-kind="candidates"]',
        $article
    );
    trace_work_item_render_assert(
        $candidateDetails !== false
            && $candidateDetails->length === ($candidates === [] ? 0 : 1),
        '候选明细 details 应与模型候选是否存在一致：' . $blockId
    );
    if ($candidates !== []) {
        $candidateDetail = $candidateDetails->item(0);
        trace_work_item_render_assert(
            $candidateDetail instanceof DOMElement
                && !$candidateDetail->hasAttribute('open')
                && $xpath->query(
                    './summary[normalize-space(.)="查看可带入候选（'
                        . count($candidates) . '）"]',
                    $candidateDetail
                )->length === 1,
            '候选明细应默认关闭并显示准确数量：' . $blockId
        );
        $candidateLinks = $xpath->query(
            './/a[normalize-space(.)="带入此候选"]',
            $candidateDetail
        );
        foreach ($candidates as $candidateIndex => $candidate) {
            $candidateLink = $candidateLinks->item($candidateIndex);
            $candidateAriaLabel = $candidateLink instanceof DOMElement
                ? $candidateLink->getAttribute('aria-label')
                : '';
            trace_work_item_render_assert(
                $candidateAriaLabel !== ''
                    && str_contains(
                        $candidateAriaLabel,
                        (string)($candidate['target_label'] ?? '')
                    )
                    && str_contains(
                        $candidateAriaLabel,
                        (string)($candidate['relation_label'] ?? '')
                    )
                    && str_contains(
                        $candidateAriaLabel,
                        (string)($item['title'] ?? '')
                    ),
                '候选 aria-label 应包含对象、用途和所属内容块：'
                    . $blockId
            );
            $candidateAriaLabels[] = $candidateAriaLabel;
        }
    }

    trace_work_item_render_assert(
        $xpath->query(
            './div['
                . trace_work_item_render_class_xpath(
                    'qms-trace-work-item-actions'
                )
                . ' and following-sibling::details'
                . '[@data-detail-kind="issues"]]',
            $article
        )->length === 1
            && (
                $candidates === []
                || $xpath->query(
                    './div['
                        . trace_work_item_render_class_xpath(
                            'qms-trace-work-item-actions'
                        )
                        . ' and following-sibling::details'
                        . '[@data-detail-kind="candidates"]]',
                    $article
                )->length === 1
            ),
        '主办理动作在 DOM 中应早于问题和候选明细：' . $blockId
    );
}
trace_work_item_render_assert(
    $expectedCandidateCount > 0
        && trace_work_item_render_candidates_match_by_article(
            $xpath,
            $section,
            $items
        ),
    '真实样本候选必须在所属 article 内与模型 URL 严格对应'
);
trace_work_item_render_assert(
    (int)($traceWorkItems['issue_count'] ?? -1) === $expectedIssueCount
        && $expectedIssueCount === 30
        && $expectedCandidateCount === 27,
    '真实页面应保持 30 个问题和 27 个候选的非空门禁'
);
trace_work_item_render_assert(
    count(array_merge($primaryAriaLabels, $candidateAriaLabels))
        === count(array_unique(array_merge(
            $primaryAriaLabels,
            $candidateAriaLabels
        ))),
    '全页主入口和候选入口 aria-label 不得重复'
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

$mutationParsed = trace_work_item_render_parse(
    $rendered['html'],
    '跨卡候选 URL 变异'
);
$mutationXpath = $mutationParsed['xpath'];
$mutationSection = trace_work_item_render_section(
    $mutationXpath,
    '跨卡候选 URL 变异'
);
$mutationArticles = $mutationXpath->query(
    './/article['
        . trace_work_item_render_class_xpath('qms-trace-work-item')
        . ' and .//a[normalize-space(.)="带入此候选"]]',
    $mutationSection
);
trace_work_item_render_assert(
    $mutationArticles !== false && $mutationArticles->length >= 2,
    '真实样本至少两张卡应有候选，才能执行跨卡 URL 变异'
);
$firstMutationLink = $mutationXpath->query(
    './/a[normalize-space(.)="带入此候选"]',
    $mutationArticles->item(0)
)->item(0);
$secondMutationLink = $mutationXpath->query(
    './/a[normalize-space(.)="带入此候选"]',
    $mutationArticles->item(1)
)->item(0);
trace_work_item_render_assert(
    $firstMutationLink instanceof DOMElement
        && $secondMutationLink instanceof DOMElement
        && $firstMutationLink->getAttribute('href')
            !== $secondMutationLink->getAttribute('href'),
    '跨卡变异应选取两个不同的候选 URL'
);
$firstMutationUrl = $firstMutationLink->getAttribute('href');
$firstMutationLink->setAttribute(
    'href',
    $secondMutationLink->getAttribute('href')
);
$secondMutationLink->setAttribute('href', $firstMutationUrl);

$mutatedGlobalUrls = [];
foreach ($mutationArticles as $mutationArticle) {
    $mutationLinks = $mutationXpath->query(
        './/a[normalize-space(.)="带入此候选"]',
        $mutationArticle
    );
    foreach ($mutationLinks as $mutationLink) {
        $mutatedGlobalUrls[] = $mutationLink->getAttribute('href');
    }
}
$expectedGlobalUrls = [];
foreach ($items as $item) {
    foreach ((array)($item['candidates'] ?? []) as $candidate) {
        $expectedGlobalUrls[] = (string)($candidate['review_url'] ?? '');
    }
}
trace_work_item_render_assert(
    trace_work_item_render_url_multiset($mutatedGlobalUrls)
        === trace_work_item_render_url_multiset($expectedGlobalUrls),
    '跨卡 URL 变异应保持全页 multiset 不变'
);
trace_work_item_render_assert(
    !trace_work_item_render_candidates_match_by_article(
        $mutationXpath,
        $mutationSection,
        $items
    ),
    '逐卡契约必须拒绝跨卡交换候选 URL'
);

$withoutPrimary = $workbench;
$withoutPrimaryItems = (array)(
    $withoutPrimary['trace_work_items']['items'] ?? []
);
$withoutPrimaryIndex = array_key_first($withoutPrimaryItems);
trace_work_item_render_assert(
    $withoutPrimaryIndex !== null,
    '真实样本应至少有一张卡用于空主入口场景'
);
$withoutPrimaryBlockId = (string)(
    $withoutPrimaryItems[$withoutPrimaryIndex]['block_id'] ?? ''
);
$withoutPrimaryItems[$withoutPrimaryIndex]['primary_url'] = '';
$withoutPrimary['trace_work_items']['items'] = $withoutPrimaryItems;
$withoutPrimaryRendered = trace_work_item_render_workbench(
    $withoutPrimary,
    '空主入口场景'
);
$withoutPrimaryXpath = $withoutPrimaryRendered['xpath'];
$withoutPrimarySection = trace_work_item_render_section(
    $withoutPrimaryXpath,
    '空主入口场景'
);
foreach ($withoutPrimaryItems as $item) {
    $blockId = (string)($item['block_id'] ?? '');
    $article = trace_work_item_render_article(
        $withoutPrimaryXpath,
        $withoutPrimarySection,
        $blockId
    );
    trace_work_item_render_assert(
        $article instanceof DOMElement,
        '空主入口场景应保留办理卡：' . $blockId
    );
    $primaryLinks = $withoutPrimaryXpath->query(
        './/a[normalize-space(.)="处理此内容块"]',
        $article
    );
    if ($blockId === $withoutPrimaryBlockId) {
        $withoutPrimaryNotices = $withoutPrimaryXpath->query(
            './/*['
                . trace_work_item_render_class_xpath('text-danger')
                . ' and normalize-space(.)="'
                . '当前没有可用办理入口，请返回追溯关系复核配置。'
                . '"]',
            $article
        );
        trace_work_item_render_assert(
            $primaryLinks->length === 0
                && $withoutPrimaryNotices->length === 1,
            'primary_url 为空时应隐藏主链接并显示明确说明'
                . '，当前主链接=' . $primaryLinks->length
                . '，说明=' . $withoutPrimaryNotices->length
        );
        continue;
    }
    $primaryLink = $primaryLinks->item(0);
    trace_work_item_render_assert(
        $primaryLinks->length === 1
            && $primaryLink instanceof DOMElement
            && $primaryLink->getAttribute('href')
                === (string)($item['primary_url'] ?? ''),
        '空主入口场景不得影响其他卡的主链接：' . $blockId
    );
}
trace_work_item_render_assert(
    $withoutPrimaryXpath->query('//form')->length === 0,
    '空主入口场景不得渲染 form'
);

$emptyWorkbench = $workbench;
$emptyWorkbench['trace_work_items'] = [
    'items' => [],
    'block_count' => 0,
    'issue_count' => 0,
];
$emptyRendered = trace_work_item_render_workbench(
    $emptyWorkbench,
    '空列表场景'
);
$emptyXpath = $emptyRendered['xpath'];
$emptySection = trace_work_item_render_section(
    $emptyXpath,
    '空列表场景'
);
trace_work_item_render_assert(
    $emptyXpath->query(
        './/article['
            . trace_work_item_render_class_xpath('qms-trace-work-item')
            . ']',
        $emptySection
    )->length === 0
        && $emptyXpath->query(
            './/*[normalize-space(.)="当前没有需要汇总办理的内容块。"]',
            $emptySection
        )->length === 1,
    '空列表场景应只显示明确空状态'
);
trace_work_item_render_assert(
    $emptyXpath->query('//form')->length === 0,
    '空列表场景不得渲染 form'
);

echo 'qms_trace_work_item_render_smoke passed: '
    . json_encode([
        'blocks' => count($items),
        'issues' => $expectedIssueCount,
        'candidates' => $expectedCandidateCount,
        'mutation_rejected' => true,
        'empty_primary_checked' => $withoutPrimaryBlockId,
        'empty_state_checked' => true,
    ], JSON_UNESCAPED_UNICODE)
    . PHP_EOL;
