<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use app\service\QmsTraceReviewOptionService;

function trace_review_option_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

$options = [
    'clauses' => [
        ['id' => 'clause-a', 'title' => 'A'],
        ['id' => 'clause-candidate-2', 'title' => '候选二'],
        ['id' => 'clause-candidate-1', 'title' => '候选一'],
        ['id' => 'clause-b', 'title' => 'B'],
    ],
    'manual_sections' => [
        ['id' => 'manual-a', 'title' => 'A'],
        ['id' => 'manual-candidate', 'title' => '候选'],
    ],
    'record_forms' => [
        ['id' => 'record-a', 'name' => 'A'],
        ['id' => 'record-candidate', 'name' => '候选'],
    ],
    'positions' => [
        ['id' => 'position-a', 'name' => '岗位 A'],
    ],
];
$candidateTrace = [
    'external_sources' => [
        ['id' => 'clause-candidate-1', 'available' => true],
        ['id' => 'clause-candidate-2', 'available' => true],
        ['id' => '', 'available' => false],
    ],
    'manual_sections' => [
        ['id' => 'manual-candidate', 'available' => true],
    ],
    'record_templates' => [
        ['id' => 'record-candidate', 'available' => true],
    ],
];

$prioritized = QmsTraceReviewOptionService::prioritize(
    $options,
    $candidateTrace
);

trace_review_option_assert(
    array_column($prioritized['clauses'], 'id') === [
        'clause-candidate-1',
        'clause-candidate-2',
        'clause-a',
        'clause-b',
    ],
    '外部条款候选应按候选来源顺序置顶，其他条款保持原顺序'
);
trace_review_option_assert(
    ($prioritized['clauses'][0]['is_candidate'] ?? false) === true
        && ($prioritized['clauses'][1]['is_candidate'] ?? false) === true,
    '外部条款候选应有候选标识'
);
trace_review_option_assert(
    ($prioritized['clauses'][2]['is_candidate'] ?? true) === false,
    '非候选外部条款不应误标为候选'
);
trace_review_option_assert(
    ($prioritized['manual_sections'][0]['id'] ?? '') === 'manual-candidate'
        && ($prioritized['manual_sections'][0]['is_candidate'] ?? false),
    '手册章节候选应置顶并标记'
);
trace_review_option_assert(
    ($prioritized['record_forms'][0]['id'] ?? '') === 'record-candidate'
        && ($prioritized['record_forms'][0]['is_candidate'] ?? false),
    '记录模板候选应置顶并标记'
);
trace_review_option_assert(
    ($prioritized['positions'][0]['is_candidate'] ?? true) === false,
    '没有语义候选来源的对象也应取得明确的非候选标识'
);

echo "qms_trace_review_option_priority_smoke passed\n";
