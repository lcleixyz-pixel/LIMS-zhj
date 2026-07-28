<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$controller = (string)file_get_contents(
    $root . '/app/controller/PlanningStructure.php'
);
$structureService = (string)file_get_contents(
    $root . '/app/service/QmsDocumentStructureService.php'
);
$view = (string)file_get_contents(
    $root . '/app/view/planning_structure/link_review.html'
);

function candidate_prefill_assert(
    bool $condition,
    string $message
): void {
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

foreach ([
    "param('candidate_kind', '')",
    "param('candidate_id', '')",
] as $queryInput) {
    candidate_prefill_assert(
        str_contains($controller, $queryInput),
        '控制器应只读取候选类型和候选 ID：' . $queryInput
    );
}
candidate_prefill_assert(
    str_contains($structureService, 'blockTraceReviewDetail(')
        && str_contains($structureService, 'array $prefillQuery = []'),
    '追溯复核详情应显式接收可选预填参数'
);
foreach ([
    'QmsTraceSemanticCandidateService::forDocument',
    'QmsTraceCandidateRoutingService::route',
    'QmsTraceCandidateRoutingService::resolvePrefill',
    "'prefill' => \$prefill",
] as $integrationPoint) {
    candidate_prefill_assert(
        str_contains($structureService, $integrationPoint),
        '复核详情缺少候选安全重算：' . $integrationPoint
    );
}

foreach ([
    '已带入治理候选',
    '系统尚未保存',
    '建议办理到',
    '建议用途',
    '推荐理由',
    'detail.prefill.recommendation_reason',
    'detail.prefill.target_field',
    'detail.prefill.target_id',
] as $viewContract) {
    candidate_prefill_assert(
        str_contains($view, $viewContract),
        '追溯复核页缺少预填交互：' . $viewContract
    );
}
candidate_prefill_assert(
    str_contains($view, '该候选已变化，请返回治理工作台重新进入')
        || str_contains($view, 'detail.prefill.error'),
    '无效或过期候选应显示中文返回路径'
);
candidate_prefill_assert(
    str_contains($view, 'value="review_required"')
        && str_contains($view, '等待复核'),
    '候选带入后必须保留等待复核状态'
);

echo "qms_trace_candidate_prefill_smoke passed\n";
