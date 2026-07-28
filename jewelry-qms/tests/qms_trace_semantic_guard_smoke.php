<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use app\service\QmsTraceSemanticGuardService;

function trace_semantic_guard_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

function trace_semantic_guard_block(string $section, string $relation, string $confidence, string $note = ''): array
{
    return [[
        'block' => [
            'id' => 'block-1',
            'title' => '控制要求',
            'markdown' => '文件应经过批准、修订、分发、回收和作废控制。',
        ],
        'links' => [[
            'id' => 'link-' . str_replace('.', '-', $section),
            'manual_section_id' => 'manual-' . str_replace('.', '-', $section),
            'section_number' => $section,
            'manual_title' => $section === '4.2' ? '保密性' : '管理体系文件的控制',
            'relation_type' => $relation,
            'confidence' => $confidence,
            'note' => $note,
        ]],
    ]];
}

$mixedCorrectReason = QmsTraceSemanticGuardService::assessManualLink([
    'manual_section_id' => 'manual-62',
    'section_number' => '6.2',
    'manual_title' => '人员',
    'relation_type' => 'requires_record',
    'record_form_template_id' => 'form-11-01',
    'record_number' => 'XZTC/BG-11-01',
    'record_name' => '人员能力记录',
    'confidence' => 'high',
    'note' => '历史混装关系。',
], ['6.2']);
trace_semantic_guard_assert(
    ($mixedCorrectReason['reason_code'] ?? '') === 'mixed_relation',
    '候选章节正确但夹在运行记录关系中时应识别为关系混装'
);
trace_semantic_guard_assert(
    ($mixedCorrectReason['reason_label'] ?? '') === '关系混装',
    '关系混装应提供简短中文标签'
);
trace_semantic_guard_assert(
    str_contains((string)($mixedCorrectReason['message'] ?? ''), '6.2 章节候选正确')
        && str_contains((string)($mixedCorrectReason['message'] ?? ''), '混在同一关系中'),
    '关系混装提示应明确章节候选正确而关系形态错误'
);
trace_semantic_guard_assert(
    str_contains((string)($mixedCorrectReason['recommended_action'] ?? ''), '拆分预览'),
    '关系混装应引导用户按拆分预览处理'
);

$wrongSectionReason = QmsTraceSemanticGuardService::assessManualLink([
    'manual_section_id' => 'manual-42',
    'section_number' => '4.2',
    'manual_title' => '保密性',
    'relation_type' => 'implements',
    'confidence' => 'high',
    'note' => '独立手册关系。',
], ['8.3']);
trace_semantic_guard_assert(
    ($wrongSectionReason['reason_code'] ?? '') === 'wrong_section',
    '独立落实关系指向非候选章节时应识别为章节不匹配'
);
trace_semantic_guard_assert(
    ($wrongSectionReason['reason_label'] ?? '') === '章节不匹配',
    '章节不匹配应提供简短中文标签'
);
trace_semantic_guard_assert(
    str_contains((string)($wrongSectionReason['recommended_action'] ?? ''), '改为辅助关系'),
    '章节不匹配应提供调整关系或重建主链的动作'
);

$unconfirmedReason = QmsTraceSemanticGuardService::assessManualLink([
    'manual_section_id' => 'manual-62',
    'section_number' => '6.2',
    'manual_title' => '人员',
    'relation_type' => 'implements',
    'confidence' => 'high',
    'note' => '由GOV-TRIAL/0.1追溯关系继承，待0.2逐块复核。',
], ['6.2']);
trace_semantic_guard_assert(
    ($unconfirmedReason['reason_code'] ?? '') === 'unconfirmed_relation',
    '候选章节吻合但仍待人工复核时应识别为尚未确认'
);
trace_semantic_guard_assert(
    ($unconfirmedReason['reason_label'] ?? '') === '尚未确认',
    '尚未确认应提供简短中文标签'
);
trace_semantic_guard_assert(
    str_contains((string)($unconfirmedReason['message'] ?? ''), '与建议章节一致')
        && str_contains((string)($unconfirmedReason['message'] ?? ''), '暂不计入闭环'),
    '尚未确认提示应说明章节正确但证据尚不能计入闭环'
);

$cx08Wrong = QmsTraceSemanticGuardService::assess(
    [
        'doc_number' => 'XZTC/CX-08-2022',
        'title' => '文件控制程序',
    ],
    trace_semantic_guard_block('4.2', 'implements', 'high')
);
trace_semantic_guard_assert($cx08Wrong['profile']['id'] === 'document_control', 'CX-08 应识别为文件控制主题');
trace_semantic_guard_assert($cx08Wrong['profile']['expected_manual_sections'] === ['8.3'], 'CX-08 主手册候选应为 8.3');
trace_semantic_guard_assert($cx08Wrong['status'] === 'suspected_mismatch', 'CX-08 仅挂 4.2 应提示疑似错挂');
trace_semantic_guard_assert(count($cx08Wrong['manual']['confirmed_primary']) === 0, '4.2 不得冒充 CX-08 主链');
trace_semantic_guard_assert(count($cx08Wrong['manual']['suspected_mismatch']) === 1, '4.2 应列入疑似错挂');
trace_semantic_guard_assert(
    ($cx08Wrong['issues'][0]['reason_code'] ?? '') === 'wrong_section',
    '独立错误章节的顶层问题应标记为章节不匹配'
);

$cx08Correct = QmsTraceSemanticGuardService::assess(
    [
        'doc_number' => 'XZTC/CX-08-2022',
        'title' => '文件控制程序',
    ],
    array_merge(
        trace_semantic_guard_block('8.3', 'implements', 'high'),
        trace_semantic_guard_block('4.2', 'supporting', 'high')
    )
);
trace_semantic_guard_assert($cx08Correct['status'] === 'aligned', '8.3 主链加 4.2 辅助关系应判为已对齐');
trace_semantic_guard_assert(count($cx08Correct['manual']['confirmed_primary']) === 1, '8.3 应计入已确认主链');
trace_semantic_guard_assert(count($cx08Correct['manual']['supporting']) === 1, '4.2 应保留为辅助关系');

$cx19 = QmsTraceSemanticGuardService::assess(
    [
        'doc_number' => 'XZTC/CX-19-2022',
        'title' => '记录控制程序',
    ],
    trace_semantic_guard_block('4.2', 'implements', 'high')
);
trace_semantic_guard_assert($cx19['profile']['expected_manual_sections'] === ['8.4'], 'CX-19 主手册候选应为 8.4');
trace_semantic_guard_assert($cx19['status'] === 'suspected_mismatch', 'CX-19 仅挂 4.2 应提示疑似错挂');

$cx26 = QmsTraceSemanticGuardService::assess(
    [
        'doc_number' => 'XZTC/CX-26-2022',
        'title' => '计算机文件及数据控制程序',
    ],
    trace_semantic_guard_block('4.2', 'implements', 'high')
);
trace_semantic_guard_assert($cx26['profile']['expected_manual_sections'] === ['7.11'], 'CX-26 主手册候选应为 7.11');
trace_semantic_guard_assert($cx26['status'] === 'suspected_mismatch', 'CX-26 仅挂 4.2 应提示疑似错挂');

$inherited = QmsTraceSemanticGuardService::assess(
    [
        'doc_number' => 'XZTC/CX-08-2022',
        'title' => '文件控制程序',
    ],
    trace_semantic_guard_block(
        '8.3',
        'implements',
        'high',
        '由GOV-TRIAL/0.1追溯关系继承，待0.2逐块复核。'
    )
);
trace_semantic_guard_assert($inherited['status'] === 'review_required', '继承的 8.3 即使原置信度为 high 也必须待复核');
trace_semantic_guard_assert(count($inherited['manual']['pending_review']) === 1, '继承关系应列入待复核');
trace_semantic_guard_assert(count($inherited['manual']['confirmed_primary']) === 0, '继承关系不得计入已确认主链');
trace_semantic_guard_assert(
    ($inherited['issues'][0]['reason_code'] ?? '') === 'unconfirmed_relation',
    '正确但继承待复核的顶层问题应标记为尚未确认'
);

$inheritedWrong = QmsTraceSemanticGuardService::assess(
    [
        'doc_number' => 'XZTC/CX-08-2022',
        'title' => '文件控制程序',
    ],
    trace_semantic_guard_block(
        '4.2',
        'implements',
        'high',
        '由GOV-TRIAL/0.1追溯关系继承，待0.2逐块复核。'
    )
);
trace_semantic_guard_assert(
    $inheritedWrong['status'] === 'suspected_mismatch',
    '明显错挂不得被继承待复核状态掩盖'
);
trace_semantic_guard_assert(
    count($inheritedWrong['manual']['suspected_mismatch']) === 1,
    '继承的错误手册章节仍应列入疑似错挂'
);

$mixedTargetWrong = QmsTraceSemanticGuardService::assess(
    [
        'doc_number' => 'XZTC/CX-08-2022',
        'title' => '文件控制程序',
    ],
    trace_semantic_guard_block(
        '4.2',
        'requires_record',
        'high',
        '由GOV-TRIAL/0.1追溯关系继承，待0.2逐块复核。'
    )
);
trace_semantic_guard_assert(
    $mixedTargetWrong['status'] === 'suspected_mismatch',
    '记录证据关系中夹带的错误手册章节应识别为疑似错挂'
);
trace_semantic_guard_assert(
    QmsTraceSemanticGuardService::combinedLinkState(
        trace_semantic_guard_block(
            '4.2',
            'requires_record',
            'high',
            '由GOV-TRIAL/0.1追溯关系继承，待0.2逐块复核。'
        )[0]['links'][0] + [
            'record_form_template_id' => 'form-cx08',
            'record_number' => 'XZTC/BG-08-02',
        ],
        ['8.3']
    ) === 'suspected_mismatch',
    '复核页应按整条混合关系显示最严重的疑似错挂状态'
);

$unknown = QmsTraceSemanticGuardService::assess(
    [
        'doc_number' => 'XZTC/CX-99-2022',
        'title' => '其他管理程序',
    ],
    trace_semantic_guard_block('6.1', 'implements', 'high')
);
trace_semantic_guard_assert($unknown['status'] === 'not_assessed', '未知主题不得猜测预期手册章节');

$genericCandidate = [
    'available' => true,
    'source_label' => '治理装配蓝图 / 本地条款映射',
    'manual_sections' => [
        ['section_number' => '6.2', 'title' => '人员'],
        ['section_number' => '8.4', 'title' => '记录控制'],
    ],
];
$mixedCorrectBlocks = trace_semantic_guard_block(
    '6.2',
    'requires_record',
    'high',
    '由GOV-TRIAL/0.1追溯关系继承，待0.2逐块复核。'
);
$mixedCorrectBlocks[0]['links'][0] += [
    'record_form_template_id' => 'form-11-01',
    'record_number' => 'XZTC/BG-11-01',
    'record_name' => '人员能力记录',
];
$mixedCorrectAssessment = QmsTraceSemanticGuardService::assess(
    [
        'doc_number' => 'XZTC/CX-11-2022',
        'title' => '人员管理程序',
    ],
    $mixedCorrectBlocks,
    $genericCandidate
);
trace_semantic_guard_assert(
    $mixedCorrectAssessment['status'] === 'suspected_mismatch',
    '正确章节夹在记录关系中仍不能计入主链'
);
trace_semantic_guard_assert(
    ($mixedCorrectAssessment['issues'][0]['reason_code'] ?? '') === 'mixed_relation',
    '正确章节夹在记录关系中的顶层问题应标记为关系混装'
);
trace_semantic_guard_assert(
    str_contains(
        (string)($mixedCorrectAssessment['issues'][0]['message'] ?? ''),
        '6.2 章节候选正确'
    )
        && str_contains(
            (string)($mixedCorrectAssessment['issues'][0]['message'] ?? ''),
            '混在同一关系中'
        ),
    '关系混装顶层提示不得再把正确章节说成错挂'
);
trace_semantic_guard_assert(
    str_contains(
        (string)($mixedCorrectAssessment['issues'][0]['recommended_action'] ?? ''),
        '拆分预览'
    ),
    '关系混装顶层问题应提供拆分动作'
);

$genericInherited = QmsTraceSemanticGuardService::assess(
    [
        'doc_number' => 'XZTC/CX-11-2022',
        'title' => '人员管理程序',
    ],
    trace_semantic_guard_block(
        '6.2',
        'implements',
        'high',
        '由GOV-TRIAL/0.1追溯关系继承，待0.2逐块复核。'
    ),
    $genericCandidate
);
trace_semantic_guard_assert(
    $genericInherited['status'] === 'review_required',
    '通用继承候选应等待人工复核'
);
trace_semantic_guard_assert(
    ($genericInherited['profile']['candidate_only'] ?? false) === true,
    '通用语义档案应明确标记为候选'
);
trace_semantic_guard_assert(
    ($genericInherited['profile']['expected_manual_sections'] ?? []) === ['6.2', '8.4'],
    '通用语义档案应保留有来源的候选章节'
);

$genericMissing = QmsTraceSemanticGuardService::assess(
    [
        'doc_number' => 'XZTC/CX-11-2022',
        'title' => '人员管理程序',
    ],
    [],
    $genericCandidate
);
trace_semantic_guard_assert(
    $genericMissing['status'] === 'missing_primary',
    '有候选但尚未保存主链时应明确主链缺失'
);
trace_semantic_guard_assert(
    str_contains((string)($genericMissing['issues'][0]['message'] ?? ''), '候选不等于确认'),
    '通用缺链提示应防止把候选误当成确认'
);

$candidateUnavailable = QmsTraceSemanticGuardService::assess(
    [
        'doc_number' => 'XZTC/CX-99-2022',
        'title' => '未入蓝图程序',
    ],
    [],
    [
        'available' => false,
        'source_label' => '治理装配蓝图 / 本地条款映射',
        'issues' => ['治理装配蓝图未找到程序：XZTC/CX-99-2022'],
    ]
);
trace_semantic_guard_assert(
    $candidateUnavailable['status'] === 'candidate_unavailable',
    '候选来源缺失时应给出明确状态'
);
trace_semantic_guard_assert(
    str_contains(
        (string)($candidateUnavailable['issues'][0]['message'] ?? ''),
        'XZTC/CX-99-2022'
    ),
    '候选不可用提示应保留具体原因'
);

echo "qms_trace_semantic_guard_smoke passed\n";
