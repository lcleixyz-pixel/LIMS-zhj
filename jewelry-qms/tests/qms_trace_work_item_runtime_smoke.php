<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/app/common.php';

use app\service\QmsFileGovernanceWorkbenchService;
use think\facade\Db;

(new think\App())->initialize();

function trace_work_item_runtime_assert(
    bool $condition,
    string $message
): void {
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

function trace_work_item_runtime_active_link_count(): int
{
    return (int)Db::name('qms_document_block_links')
        ->where('publish', 1)
        ->where('soft_delete', 0)
        ->count();
}

function trace_work_item_runtime_assert_url(
    string $url,
    string $blockId,
    string $label
): void {
    trace_work_item_runtime_assert(
        $url !== '',
        $label . ' 不得为空'
    );
    $parts = parse_url($url);
    trace_work_item_runtime_assert(
        is_array($parts)
            && !array_key_exists('scheme', $parts)
            && !array_key_exists('host', $parts)
            && (string)($parts['path'] ?? '')
                === '/planning/structures/links/review',
        $label . ' 必须是站内关系复核入口'
    );

    parse_str((string)($parts['query'] ?? ''), $query);
    trace_work_item_runtime_assert(
        (string)($query['block_id'] ?? '') === $blockId,
        $label . ' 的 block_id 必须与办理卡一致'
    );
    $unexpectedKeys = array_diff(
        array_keys($query),
        ['block_id', 'link_id', 'candidate_kind', 'candidate_id']
    );
    trace_work_item_runtime_assert(
        $unexpectedKeys === [],
        $label . ' 不得携带写表单或其它越界参数'
    );
}

$structuredId = '62ef7ecd-d270-4fc2-bccf-49c2986fa838';
$beforeLinks = trace_work_item_runtime_active_link_count();
$viewModel = QmsFileGovernanceWorkbenchService::detail($structuredId);
$afterLinks = trace_work_item_runtime_active_link_count();

trace_work_item_runtime_assert(
    $beforeLinks === $afterLinks,
    '读取连续办理卡不得新增、修改或删除有效追溯关系'
);
trace_work_item_runtime_assert(
    $viewModel !== [],
    '8021 缺少指定的 CX-03-02 GOV-TRIAL/0.2 结构化文件'
);

$workItems = (array)($viewModel['trace_work_items'] ?? []);
$items = (array)($workItems['items'] ?? []);
trace_work_item_runtime_assert(
    (int)($workItems['block_count'] ?? 0) > 0
        && (int)$workItems['block_count'] === count($items),
    '文件治理工作台应返回真实内容块连续办理卡'
);

$blockIds = array_map(
    static fn(array $item): string => (string)($item['block_id'] ?? ''),
    $items
);
trace_work_item_runtime_assert(
    !in_array('', $blockIds, true)
        && count($blockIds) === count(array_unique($blockIds)),
    '每个内容块只能输出一张有稳定 block_id 的办理卡'
);

$issueCount = array_sum(array_map(
    static fn(array $item): int =>
        count((array)($item['issues'] ?? [])),
    $items
));
trace_work_item_runtime_assert(
    (int)($workItems['issue_count'] ?? -1) === $issueCount,
    '问题总数必须等于每张办理卡的问题数合计'
);

$reviewSeen = false;
$blockedCount = 0;
$reviewCount = 0;
foreach ($items as $item) {
    $priority = (string)($item['priority'] ?? '');
    trace_work_item_runtime_assert(
        in_array($priority, ['blocked', 'review'], true),
        '办理卡优先级只能是 blocked 或 review'
    );
    if ($priority === 'review') {
        $reviewSeen = true;
        $reviewCount++;
    } else {
        trace_work_item_runtime_assert(
            !$reviewSeen,
            'blocked 办理卡必须排在 review 办理卡之前'
        );
        $blockedCount++;
    }
}
trace_work_item_runtime_assert(
    $blockedCount > 0 && $reviewCount > 0,
    '真实样本应同时覆盖阻断卡和普通复核卡'
);

$candidateTrace = (array)($viewModel['candidate_trace'] ?? []);
$candidateUrls = [];
foreach ([
    'external_sources',
    'manual_sections',
    'record_templates',
] as $collection) {
    foreach ((array)($candidateTrace[$collection] ?? []) as $candidate) {
        if (!is_array($candidate)) {
            continue;
        }
        $key = (string)($candidate['candidate_kind'] ?? '')
            . '|' . (string)($candidate['target_id'] ?? '')
            . '|' . (string)($candidate['target_block_id'] ?? '');
        $candidateUrls[$key] = (string)($candidate['review_url'] ?? '');
    }
}

$mixedRelationFound = false;
foreach ($items as $item) {
    $blockId = (string)($item['block_id'] ?? '');
    trace_work_item_runtime_assert_url(
        (string)($item['primary_url'] ?? ''),
        $blockId,
        '办理卡主入口'
    );

    foreach ((array)($item['issues'] ?? []) as $issue) {
        if ((string)($issue['code'] ?? '') === 'mixed_relation') {
            $mixedRelationFound = true;
        }
        trace_work_item_runtime_assert_url(
            (string)($issue['review_url'] ?? ''),
            $blockId,
            '问题复核入口'
        );
    }

    foreach ((array)($item['candidates'] ?? []) as $candidate) {
        $key = (string)($candidate['candidate_kind'] ?? '')
            . '|' . (string)($candidate['target_id'] ?? '')
            . '|' . (string)($candidate['target_block_id'] ?? '');
        trace_work_item_runtime_assert(
            array_key_exists($key, $candidateUrls)
                && (string)($candidate['review_url'] ?? '')
                    === $candidateUrls[$key],
            '候选按钮 URL 必须原样沿用已返回 candidate_trace 的同一候选入口'
        );
        trace_work_item_runtime_assert_url(
            (string)($candidate['review_url'] ?? ''),
            $blockId,
            '候选复核入口'
        );
    }
}

trace_work_item_runtime_assert(
    $mixedRelationFound,
    'CX-03-02 真实样本应保留至少一项 mixed_relation 供连续办理'
);

echo 'qms_trace_work_item_runtime_smoke passed: '
    . json_encode([
        'blocks' => (int)$workItems['block_count'],
        'issues' => (int)$workItems['issue_count'],
        'blocked' => $blockedCount,
        'review' => $reviewCount,
        'links_before' => $beforeLinks,
        'links_after' => $afterLinks,
    ], JSON_UNESCAPED_UNICODE)
    . PHP_EOL;
