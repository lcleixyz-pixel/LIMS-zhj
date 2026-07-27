<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use app\service\QmsTraceSemanticCandidateService;

function trace_candidate_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

$blueprint = [
    'manual_sections' => [
        [
            'section_key' => 'manual_84',
            'section_number' => '8.4',
            'title' => '记录控制',
        ],
        [
            'section_key' => 'manual_62',
            'section_number' => '6.2',
            'title' => '人员',
        ],
    ],
    'procedures' => [[
        'doc_number' => 'XZTC/CX-11-2022',
        'manual_sections' => ['manual_84', 'manual_62', 'manual_84'],
        'record_templates' => [
            'XZTC/BG-11-02',
            'XZTC/BG-11-01',
            'XZTC/BG-11-01',
        ],
    ]],
];

$candidate = QmsTraceSemanticCandidateService::fromBlueprint(
    ['doc_number' => 'SIM-GOV02-XZTC/CX-11-2022'],
    $blueprint
);
trace_candidate_assert($candidate['available'] === true, '应匹配治理蓝图程序');
trace_candidate_assert(
    $candidate['canonical_doc_number'] === 'XZTC/CX-11-2022',
    '应把治理解析稿编号归一为程序母编号'
);
trace_candidate_assert(
    array_column($candidate['manual_sections'], 'section_number') === ['6.2', '8.4'],
    '候选手册章节应去重并稳定排序'
);
trace_candidate_assert(
    array_column($candidate['record_templates'], 'canonical_doc_number')
        === ['XZTC/BG-11-01', 'XZTC/BG-11-02'],
    '候选记录应去重并稳定排序'
);
trace_candidate_assert(
    ($candidate['source_kind'] ?? '') === 'governance_blueprint'
        && ($candidate['review_required'] ?? false) === true,
    '治理蓝图只能提供待复核候选'
);

$trialCandidate = QmsTraceSemanticCandidateService::fromBlueprint(
    ['doc_number' => 'SIM-XZTC/CX-11-2022'],
    $blueprint
);
trace_candidate_assert(
    $trialCandidate['canonical_doc_number'] === 'XZTC/CX-11-2022',
    '应兼容SIM程序编号'
);

$unavailable = QmsTraceSemanticCandidateService::fromBlueprint(
    ['doc_number' => 'SIM-GOV02-XZTC/CX-99-2022'],
    $blueprint
);
trace_candidate_assert($unavailable['available'] === false, '蓝图外程序应明确候选不可用');
trace_candidate_assert(
    ($unavailable['issues'][0] ?? '') !== '',
    '候选不可用时应给出可读原因'
);

echo "qms_trace_semantic_candidate_smoke passed\n";
