<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use app\service\QmsGovernanceQueueService;

function governance_queue_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

function governance_queue_snapshot(
    string $id,
    string $docNumber,
    string $title,
    string $semanticStatus,
    string $summaryLevel,
    array $missingChain = [],
    string $documentStatus = 'draft',
    int $covered = 0,
    int $needsReview = 0,
    int $missing = 0,
    string $versionState = 'current_candidate'
): array {
    return [
        'version_state' => $versionState,
        'workbench' => [
            'document' => [
                'id' => $id,
                'document_id' => 'controlled-' . $id,
                'doc_number' => $docNumber,
                'title' => $title,
                'version' => 'GOV-TRIAL/0.2',
                'status' => $documentStatus,
                'modified' => '2026-07-27 10:00:00',
            ],
            'semantic_guard' => [
                'status' => $semanticStatus,
            ],
            'summary' => [
                'level' => $summaryLevel,
                'message' => '当前结论',
                'next_step' => '处理下一项',
                'completed_checks' => $summaryLevel === 'completed' ? 6 : 3,
                'total_checks' => 6,
            ],
            'chain' => [
                'missing' => $missingChain,
            ],
            'record_coverage' => [
                'total' => $covered + $needsReview + $missing,
                'covered' => $covered,
                'needs_review' => $needsReview,
                'missing' => $missing,
            ],
        ],
    ];
}

$snapshots = [
    governance_queue_snapshot(
        'structured-cx01',
        'SIM-GOV02-XZTC/CX-01-2022',
        '人员培训程序',
        'suspected_mismatch',
        'blocked',
        ['手册主链']
    ),
    governance_queue_snapshot(
        'structured-cx08',
        'SIM-GOV02-XZTC/CX-08-2022',
        '文件控制程序',
        'aligned',
        'warning',
        [],
        'draft',
        0,
        1
    ),
    governance_queue_snapshot(
        'structured-cx13',
        'SIM-GOV02-XZTC/CX-13-2022',
        '内部沟通程序',
        'missing_primary',
        'blocked',
        ['外部依据主链', '手册主链', '运行证据主链'],
        'draft',
        0,
        0,
        1
    ),
    governance_queue_snapshot(
        'structured-cx35',
        'SIM-GOV02-XZTC/CX-35-2022',
        '抽样控制程序',
        'missing_primary',
        'blocked',
        ['外部依据主链'],
        'obsolete'
    ),
    [
        'version_state' => 'candidate_conflict',
        'document' => [
            'id' => '',
            'doc_number' => 'SIM-GOV02-XZTC/CX-99-2022',
            'title' => '重复候选程序',
            'version' => 'GOV-TRIAL/0.2',
            'status' => 'draft',
            'modified' => '2026-07-27 10:00:00',
        ],
    ],
];

$queue = QmsGovernanceQueueService::fromSnapshots($snapshots);
governance_queue_assert(($queue['scope']['total'] ?? 0) === 5, '队列应保留全部候选和冲突项');
governance_queue_assert(
    ($queue['summary']['aligned'] ?? 0) === 1
    && ($queue['summary']['suspected_mismatch'] ?? 0) === 1
    && ($queue['summary']['missing_primary'] ?? 0) === 2
    && ($queue['summary']['version_conflict'] ?? 0) === 1,
    '队列状态统计不正确'
);
governance_queue_assert(($queue['visible_count'] ?? 0) === 5, '默认应显示全部队列项');

$suspected = QmsGovernanceQueueService::fromSnapshots($snapshots, [
    'status' => 'suspected_mismatch',
]);
governance_queue_assert(
    ($suspected['visible_count'] ?? 0) === 1
    && ($suspected['rows'][0]['structured_id'] ?? '') === 'structured-cx01',
    '疑似错挂筛选应只保留对应文件'
);

$keyword = QmsGovernanceQueueService::fromSnapshots($snapshots, [
    'keyword' => '文件控制',
]);
governance_queue_assert(
    ($keyword['visible_count'] ?? 0) === 1
    && ($keyword['rows'][0]['structured_id'] ?? '') === 'structured-cx08',
    '关键词应同时检索文件编号和标题'
);

$nextAfterCx01 = QmsGovernanceQueueService::nextUnresolvedFromRows(
    $queue['all_rows'],
    'structured-cx01'
);
governance_queue_assert(
    ($nextAfterCx01['structured_id'] ?? '') === 'structured-cx08',
    '下一份未完成应按稳定顺序前进'
);

$nextAfterCx13 = QmsGovernanceQueueService::nextUnresolvedFromRows(
    $queue['all_rows'],
    'structured-cx13'
);
governance_queue_assert(
    ($nextAfterCx13['structured_id'] ?? '') === 'structured-cx01',
    '队尾后应回绕，并跳过已废止与候选冲突'
);

echo "qms_governance_queue_service_smoke passed\n";
