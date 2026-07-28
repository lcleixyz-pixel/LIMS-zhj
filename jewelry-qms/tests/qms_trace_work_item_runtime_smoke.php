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
    string $label,
    string $candidateKind = '',
    string $candidateId = ''
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
    $expectedQuery = ['block_id' => $blockId];
    if ($candidateKind !== '' || $candidateId !== '') {
        $expectedQuery['candidate_kind'] = $candidateKind;
        $expectedQuery['candidate_id'] = $candidateId;
    }
    ksort($query);
    ksort($expectedQuery);
    trace_work_item_runtime_assert(
        $query === $expectedQuery,
        $label . ' 只能使用严格 block_id 或候选三元组查询形状'
    );
}

function trace_work_item_runtime_candidate_multiset(array $candidates): array
{
    $keys = [];
    foreach ($candidates as $candidate) {
        if (!is_array($candidate)) {
            continue;
        }
        $keys[] = implode("\x1F", [
            (string)($candidate['candidate_kind'] ?? ''),
            (string)($candidate['target_id'] ?? ''),
            (string)($candidate['target_block_id'] ?? ''),
            (string)($candidate['review_url'] ?? ''),
        ]);
    }
    sort($keys, SORT_STRING);

    return $keys;
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
$candidateCollections = [
    'external_sources' => 'external_source',
    'manual_sections' => 'manual_section',
    'record_templates' => 'record_template',
];
$itemBlockIds = array_fill_keys($blockIds, true);
$routableCandidateCount = 0;
$expectedCandidates = [];
$expectedCollectionCounts = array_fill_keys(
    array_keys($candidateCollections),
    0
);
foreach ($candidateCollections as $collection => $candidateKind) {
    foreach ((array)($candidateTrace[$collection] ?? []) as $candidate) {
        if (!is_array($candidate)) {
            continue;
        }
        if (!(bool)($candidate['routable'] ?? false)) {
            continue;
        }
        $routableCandidateCount++;
        $targetBlockId = (string)($candidate['target_block_id'] ?? '');
        if (!isset($itemBlockIds[$targetBlockId])) {
            continue;
        }
        trace_work_item_runtime_assert(
            (string)($candidate['candidate_kind'] ?? '')
                === $candidateKind,
            $collection . ' 的候选类型必须稳定'
        );
        $expectedCandidates[] = $candidate;
        $expectedCollectionCounts[$collection]++;
    }
}
trace_work_item_runtime_assert(
    $routableCandidateCount > 0,
    '真实 candidate_trace 必须至少有一个可办理候选'
);

$mixedRelationFound = false;
$actualCandidates = [];
$actualCollectionCounts = array_fill_keys(
    array_keys($candidateCollections),
    0
);
foreach ($items as $item) {
    $blockId = (string)($item['block_id'] ?? '');
    trace_work_item_runtime_assert_url(
        (string)($item['review_url'] ?? ''),
        $blockId,
        '办理卡主入口'
    );
    trace_work_item_runtime_assert(
        (int)($item['issue_count'] ?? -1)
            === count((array)($item['issues'] ?? []))
            && !array_key_exists('primary_url', $item),
        '每张卡必须使用 issue_count 与 review_url 单一模型契约'
    );

    foreach ((array)($item['issues'] ?? []) as $issue) {
        if ((string)($issue['code'] ?? '') === 'mixed_relation') {
            $mixedRelationFound = true;
        }
        trace_work_item_runtime_assert_url(
            (string)($issue['review_url'] ?? ''),
            $blockId,
            '问题复核入口',
            (string)($issue['candidate_kind'] ?? ''),
            (string)($issue['target_id'] ?? '')
        );
    }

    foreach ((array)($item['candidates'] ?? []) as $candidate) {
        $actualCandidates[] = $candidate;
        foreach ($candidateCollections as $collection => $candidateKind) {
            if (
                (string)($candidate['candidate_kind'] ?? '')
                    === $candidateKind
            ) {
                $actualCollectionCounts[$collection]++;
            }
        }
        trace_work_item_runtime_assert_url(
            (string)($candidate['review_url'] ?? ''),
            $blockId,
            '候选复核入口',
            (string)($candidate['candidate_kind'] ?? ''),
            (string)($candidate['target_id'] ?? '')
        );
    }
}

trace_work_item_runtime_assert(
    $mixedRelationFound,
    'CX-03-02 真实样本应保留至少一项 mixed_relation 供连续办理'
);
trace_work_item_runtime_assert(
    count($actualCandidates) > 0,
    '真实办理卡必须至少带入一个候选，候选校验不得空跑'
);
foreach ($expectedCollectionCounts as $collection => $expectedCount) {
    if ($expectedCount === 0) {
        continue;
    }
    trace_work_item_runtime_assert(
        (int)$actualCollectionCounts[$collection] > 0,
        $collection . ' 有预期候选时必须实际执行办理卡候选比较'
    );
}
trace_work_item_runtime_assert(
    trace_work_item_runtime_candidate_multiset($expectedCandidates)
        === trace_work_item_runtime_candidate_multiset($actualCandidates),
    '办理卡候选必须按类型、目标、内容块和原始 URL 与候选链多重集严格相等'
);

echo 'qms_trace_work_item_runtime_smoke passed: '
    . json_encode([
        'blocks' => (int)$workItems['block_count'],
        'issues' => (int)$workItems['issue_count'],
        'candidates' => count($actualCandidates),
        'routable_candidates' => $routableCandidateCount,
        'blocked' => $blockedCount,
        'review' => $reviewCount,
        'links_before' => $beforeLinks,
        'links_after' => $afterLinks,
    ], JSON_UNESCAPED_UNICODE)
    . PHP_EOL;
