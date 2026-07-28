<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use app\service\QmsTraceSemanticCandidateService;

function trace_candidate_version_assert(
    bool $condition,
    string $message
): void {
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

$rows = [
    [
        'id' => 'current-02',
        'doc_number' => 'SIM-XZTC/BG-02-01',
        'canonical_doc_number' => 'XZTC/BG-02-01',
        'version' => 'GOV-TRIAL/0.2',
        'status' => 'trial_ready',
    ],
    [
        'id' => 'old-02',
        'doc_number' => 'SIM-XZTC/BG-02-01',
        'canonical_doc_number' => 'XZTC/BG-02-01',
        'version' => 'GOV-TRIAL/0.1',
        'status' => 'obsolete',
    ],
    [
        'id' => 'current-30',
        'doc_number' => 'SIM-XZTC/BG-30-04',
        'canonical_doc_number' => 'XZTC/BG-30-04',
        'version' => 'GOV-TRIAL/0.2',
        'status' => 'draft',
    ],
    [
        'id' => 'old-30',
        'doc_number' => 'SIM-XZTC/BG-30-04',
        'canonical_doc_number' => 'XZTC/BG-30-04',
        'version' => 'GOV-TRIAL/0.1',
        'status' => 'trial_ready',
    ],
];

$forward = QmsTraceSemanticCandidateService::resolveRecordTemplateRows(
    $rows
);
$reverse = QmsTraceSemanticCandidateService::resolveRecordTemplateRows(
    array_reverse($rows)
);

foreach ([$forward, $reverse] as $resolved) {
    trace_candidate_version_assert(
        ($resolved['XZTC/BG-02-01']['id'] ?? '') === 'current-02',
        'BG-02-01 应稳定解析到 GOV-TRIAL/0.2，不受数据库顺序影响'
    );
    trace_candidate_version_assert(
        ($resolved['XZTC/BG-30-04']['id'] ?? '') === 'current-30',
        'BG-30-04 应稳定解析到治理目标版本，即使其状态仍为草稿'
    );
}

echo "qms_trace_semantic_candidate_version_smoke passed\n";
