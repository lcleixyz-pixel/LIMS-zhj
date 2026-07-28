<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use app\service\QmsTraceWorkItemService;

function trace_work_item_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

function trace_work_item_group_assert(
    string $group,
    bool $condition,
    string $message
): void {
    $selectedGroup = trim((string)getenv('TRACE_WORK_ITEM_TEST_GROUP'));
    if ($selectedGroup !== '' && $selectedGroup !== $group) {
        return;
    }
    trace_work_item_assert($condition, '[' . $group . '] ' . $message);
}

function trace_work_item_candidate(array $overrides): array
{
    return array_merge([
        'candidate_kind' => 'manual_section',
        'candidate_kind_label' => '手册章节',
        'relation_type' => 'implements',
        'relation_label' => '主链：落实手册',
        'target_field' => 'manual_section_id',
        'target_id' => '',
        'target_label' => '',
        'target_block_id' => '',
        'target_block_title' => '',
        'target_block_type' => '',
        'routable' => true,
        'routing_issue' => '',
        'review_url' => '',
    ], $overrides);
}

$blocks = [
    [
        'block' => [
            'id' => 'block-pending',
            'section_number' => '7.1',
            'title' => '外部依据',
            'block_type' => 'purpose',
            'sort_order' => 5,
        ],
        'links' => [[
            'id' => 'link-pending',
            'clause_id' => 'clause-71',
            'source_code' => 'CNAS-CL01:2018',
            'clause_number' => '7.1',
            'clause_title' => '要求、标书和合同的评审',
            'relation_type' => 'basis',
            'confidence' => 'review_required',
            'note' => '待人工复核。',
        ], [
            'id' => 'link-supporting',
            'position_id' => 'position-1',
            'position_name' => '合同评审员',
            'relation_type' => 'supporting',
            'confidence' => 'review_required',
        ]],
    ],
    [
        'block' => [
            'id' => 'block-mismatch',
            'section_number' => '5.2',
            'title' => '文件控制方法',
            'block_type' => 'process_step',
            'sort_order' => 10,
        ],
        'links' => [[
            'id' => 'link-mismatch',
            'manual_section_id' => 'manual-42',
            'section_number' => '4.2',
            'manual_title' => '保密性',
            'relation_type' => 'implements',
            'confidence' => 'high',
            'note' => '历史独立关系。',
            'review_url' => '/planning/structures/links/review?block_id=block-mismatch&link_id=link-mismatch',
        ]],
    ],
    [
        'block' => [
            'id' => 'block-mixed',
            'section_number' => '6.2',
            'title' => '人员能力记录',
            'block_type' => 'record_requirement',
            'sort_order' => 30,
        ],
        'links' => [
            [
                'id' => 'link-mixed',
                'manual_section_id' => 'manual-62',
                'section_number' => '6.2',
                'manual_title' => '人员',
                'record_form_template_id' => 'record-1',
                'record_number' => 'XZTC/BG-11-01',
                'record_name' => '人员能力记录',
                'relation_type' => 'requires_record',
                'confidence' => 'review_required',
                'note' => '历史混装关系。',
                'review_url' => '/planning/structures/links/review?block_id=block-mixed&link_id=link-mixed',
            ],
            [
                'id' => 'link-mixed',
                'manual_section_id' => 'manual-62',
                'section_number' => '6.2',
                'manual_title' => '人员',
                'record_form_template_id' => 'record-1',
                'record_number' => 'XZTC/BG-11-01',
                'record_name' => '人员能力记录',
                'relation_type' => 'requires_record',
                'confidence' => 'review_required',
                'note' => '重复的历史混装关系。',
                'review_url' => '/planning/structures/links/review?block_id=block-mixed&link_id=link-mixed',
            ],
        ],
    ],
    [
        'block' => [
            'id' => 'block-confirmed',
            'section_number' => '8.4',
            'title' => '记录归档',
            'block_type' => 'control_requirement',
            'sort_order' => 1,
        ],
        'links' => [[
            'id' => 'link-confirmed',
            'record_form_template_id' => 'record-confirmed',
            'record_number' => 'XZTC/BG-19-01',
            'record_name' => '记录归档清单',
            'relation_type' => 'requires_record',
            'confidence' => 'high',
            'note' => '人工确认。',
        ]],
    ],
    [
        'block' => [
            'id' => '',
            'section_number' => '9.9',
            'title' => '没有 ID 的内容块',
            'block_type' => 'scope',
            'sort_order' => 0,
        ],
        'links' => [[
            'id' => 'link-no-block',
            'clause_id' => 'clause-no-block',
            'relation_type' => 'basis',
            'confidence' => 'review_required',
        ]],
    ],
];

$mixedCandidate = trace_work_item_candidate([
    'candidate_kind' => 'record_template',
    'candidate_kind_label' => '运行记录',
    'relation_type' => 'requires_record',
    'relation_label' => '主链：运行记录',
    'target_field' => 'record_form_template_id',
    'target_id' => 'record-1',
    'target_label' => '',
    'target_block_id' => 'block-mixed',
    'target_block_title' => '候选路由标题',
    'target_block_type' => 'record_requirement',
    'review_url' => '/planning/structures/links/review?block_id=block-mixed&candidate_kind=record_template&candidate_id=record-1',
]);
$candidateTrace = [
    'manual_sections' => [
        trace_work_item_candidate([
            'target_id' => 'manual-52',
            'section_number' => '5.2',
            'target_label' => '5.2 文件控制',
            'target_block_id' => 'block-mismatch',
            'target_block_title' => '文件控制方法',
            'target_block_type' => 'process_step',
            'review_url' => '/planning/structures/links/review?block_id=block-mismatch&candidate_kind=manual_section&candidate_id=manual-52',
        ]),
    ],
    'external_sources' => [
        trace_work_item_candidate([
            'candidate_kind' => 'external_source',
            'candidate_kind_label' => '外部依据',
            'relation_type' => 'basis',
            'relation_label' => '主链：外部依据',
            'target_field' => 'clause_id',
            'target_id' => 'clause-71',
            'target_label' => 'CNAS-CL01:2018 7.1 要求、标书和合同的评审',
            'target_block_id' => 'block-pending',
            'target_block_title' => '外部依据',
            'target_block_type' => 'purpose',
            'review_url' => '/planning/structures/links/review?block_id=block-pending&candidate_kind=external_source&candidate_id=clause-71',
        ]),
        trace_work_item_candidate([
            'candidate_kind' => 'external_source',
            'candidate_kind_label' => '外部依据',
            'relation_type' => 'basis',
            'relation_label' => '主链：外部依据',
            'target_field' => 'clause_id',
            'target_id' => 'clause-blocked',
            'target_label' => '不可路由候选',
            'target_block_id' => 'block-pending',
            'target_block_title' => '外部依据',
            'target_block_type' => 'purpose',
            'routable' => false,
            'routing_issue' => '候选对象尚未入库。',
            'review_url' => '/must-not-appear',
        ]),
    ],
    'record_templates' => [
        $mixedCandidate,
        $mixedCandidate,
        trace_work_item_candidate([
            'candidate_kind' => 'record_template',
            'candidate_kind_label' => '运行记录',
            'relation_type' => 'requires_record',
            'relation_label' => '主链：运行记录',
            'target_field' => 'record_form_template_id',
            'target_id' => 'record-confirmed',
            'target_label' => 'XZTC/BG-19-01 记录归档清单',
            'target_block_id' => 'block-confirmed',
            'target_block_title' => '记录归档',
            'target_block_type' => 'control_requirement',
            'review_url' => '/planning/structures/links/review?block_id=block-confirmed&candidate_kind=record_template&candidate_id=record-confirmed',
        ]),
    ],
];

$result = QmsTraceWorkItemService::build($blocks, $candidateTrace);
$items = (array)($result['items'] ?? []);

trace_work_item_assert(
    ($result['block_count'] ?? 0) === 3
        && count($items) === 3,
    '只应输出混装、疑似错挂和待复核三个有问题的内容块'
);
trace_work_item_assert(
    array_column($items, 'block_id') === [
        'block-mixed',
        'block-mismatch',
        'block-pending',
    ],
    '办理卡应按混装、疑似错挂、待复核稳定排序'
);

$byBlock = [];
foreach ($items as $item) {
    $byBlock[(string)($item['block_id'] ?? '')] = $item;
}
$mixed = $byBlock['block-mixed'];
$mismatch = $byBlock['block-mismatch'];
$pending = $byBlock['block-pending'];

foreach ($items as $item) {
    foreach ((array)($item['issues'] ?? []) as $issue) {
        trace_work_item_assert(
            trim((string)($issue['context_label'] ?? '')) !== '',
            '每个问题都应提供非空的人可读对象说明'
        );
    }
}

trace_work_item_assert(
    array_column((array)$mixed['issues'], 'code') === [
        'mixed_relation',
        'missing_primary',
    ],
    '混装卡应同时显示关系混装和缺少已确认主链'
);
trace_work_item_assert(
    (($mismatch['issues'][0]['code'] ?? '') === 'suspected_mismatch'),
    '疑似错挂应是错挂卡的首要问题'
);
trace_work_item_assert(
    array_column((array)$pending['issues'], 'code')[0] === 'pending_review',
    '主链候选尚未确认时应先显示待复核问题'
);
trace_work_item_assert(
    ($mixed['priority'] ?? '') === 'blocked'
        && ($mismatch['priority'] ?? '') === 'blocked'
        && ($pending['priority'] ?? '') === 'review',
    '阻断问题和普通复核问题应使用稳定优先级'
);

$mixedIssuesByCode = [];
foreach ((array)$mixed['issues'] as $issue) {
    $mixedIssuesByCode[(string)($issue['code'] ?? '')] = $issue;
}
trace_work_item_assert(
    ($mixedIssuesByCode['mixed_relation']['context_label'] ?? '')
        === '手册章节：6.2 人员；运行记录：XZTC/BG-11-01 人员能力记录'
        && ($mixedIssuesByCode['missing_primary']['context_label'] ?? '')
            === '候选对象信息待补充'
        && count(array_unique(array_column(
            (array)$mixed['issues'],
            'context_label'
        ))) === count((array)$mixed['issues']),
    '混装问题应列出多个人可读对象，缺主链问题应使用候选 target_label，且同卡问题对象可区分'
);
trace_work_item_assert(
    ($mismatch['issues'][0]['context_label'] ?? '')
        === '手册章节：4.2 保密性'
        && ($pending['issues'][0]['context_label'] ?? '')
            === '外部依据：CNAS-CL01:2018 7.1 要求、标书和合同的评审',
    '既有关系问题应优先使用编号和标题形成对象说明'
);

$mixedCandidates = (array)($mixed['candidates'] ?? []);
trace_work_item_assert(
    count($mixedCandidates) === 1,
    '重复候选应按候选类型、目标 ID 和内容块 ID 去重'
);
trace_work_item_assert(
    ($mixedCandidates[0]['target_label'] ?? '')
        === '候选对象信息待补充',
    '候选缺少标题或目标标签时应使用中文回退'
);
trace_work_item_assert(
    ($mixedCandidates[0]['candidate_kind'] ?? '') === 'record_template'
        && ($mixedCandidates[0]['candidate_kind_label'] ?? '') === '运行记录'
        && ($mixedCandidates[0]['relation_type'] ?? '') === 'requires_record'
        && ($mixedCandidates[0]['relation_label'] ?? '') === '主链：运行记录'
        && ($mixedCandidates[0]['target_field'] ?? '') === 'record_form_template_id'
        && ($mixedCandidates[0]['target_id'] ?? '') === 'record-1'
        && ($mixedCandidates[0]['target_block_id'] ?? '') === 'block-mixed'
        && ($mixedCandidates[0]['target_block_title'] ?? '') === '候选路由标题'
        && ($mixedCandidates[0]['target_block_type'] ?? '') === 'record_requirement'
        && ($mixedCandidates[0]['routable'] ?? false) === true
        && ($mixedCandidates[0]['routing_issue'] ?? 'x') === ''
        && str_contains(
            (string)($mixedCandidates[0]['review_url'] ?? ''),
            'candidate_id=record-1'
        ),
    '候选卡应保留全部真实路由字段'
);
trace_work_item_assert(
    count((array)($pending['candidates'] ?? [])) === 1
        && !str_contains(
            json_encode($pending, JSON_UNESCAPED_UNICODE) ?: '',
            '不可路由候选'
        )
        && !str_contains(
            json_encode($pending, JSON_UNESCAPED_UNICODE) ?: '',
            'must-not-appear'
        ),
    '不可路由候选不得进入候选列表或生成按钮'
);
trace_work_item_assert(
    !isset($byBlock['block-confirmed'])
        && !isset($byBlock['']),
    '已有同目标已确认主链的内容块和无 ID 内容块不得输出'
);

$expectedSteps = [
    [
        'key' => 'review_existing',
        'label' => '1. 核对当前关系',
        'description' => '先核对当前内容块已有关系及其复核状态。',
    ],
    [
        'key' => 'resolve_issues',
        'label' => '2. 处理发现项',
        'description' => '先拆分混装或纠正错挂，再逐条确认待复核关系。',
    ],
    [
        'key' => 'confirm_primary',
        'label' => '3. 确认主链',
        'description' => '对照候选补齐主链并人工确认，完成后返回工作台复核。',
    ],
];
trace_work_item_assert(
    ($mixed['steps'] ?? []) === $expectedSteps
        && ($mismatch['steps'] ?? []) === $expectedSteps
        && ($pending['steps'] ?? []) === $expectedSteps,
    '中文办理步骤应由服务按固定顺序输出'
);
trace_work_item_assert(
    ($mixed['block_type_label'] ?? '') === '记录要求'
        && ($mismatch['block_type_label'] ?? '') === '过程步骤'
        && ($pending['block_type_label'] ?? '') === '目的',
    '内容块类型应转换为稳定中文标签'
);
trace_work_item_assert(
    ($mixed['primary_url'] ?? '') ===
        '/planning/structures/links/review?block_id=block-mixed&link_id=link-mixed'
        && ($mismatch['primary_url'] ?? '') ===
        '/planning/structures/links/review?block_id=block-mismatch&link_id=link-mismatch'
        && str_contains(
            (string)($pending['primary_url'] ?? ''),
            'candidate_id=clause-71'
        ),
    '主入口应优先使用同块问题关系 GET 地址，其次使用可路由候选地址'
);

$issueCount = array_sum(array_map(
    static fn(array $item): int => count((array)($item['issues'] ?? [])),
    $items
));
trace_work_item_assert(
    ($result['issue_count'] ?? -1) === $issueCount
        && $issueCount === 6,
    '问题计数应等于所有办理卡问题数之和，且问题与候选均应去重'
);
foreach ($items as $item) {
    foreach ((array)($item['issues'] ?? []) as $issue) {
        trace_work_item_assert(
            !array_key_exists('_issue_key', $issue),
            '输出前应移除问题去重内部键'
        );
    }
}

$mergeBlocks = [
    [
        'block' => [
            'id' => 'block-merge',
            'section_number' => '7.1',
            'title' => '归并内容块',
            'block_type' => 'purpose',
            'sort_order' => 10,
        ],
        'links' => [[
            'id' => 'link-merge',
            'clause_id' => 'clause-merge',
            'clause_number' => '7.1',
            'relation_type' => 'basis',
            'confidence' => 'review_required',
        ]],
    ],
    [
        'block' => [
            'id' => 'block-merge',
            'section_number' => '7.1',
            'title' => '归并内容块',
            'block_type' => 'purpose',
            'sort_order' => 10,
        ],
        'links' => [[
            'id' => 'link-merge',
            'clause_id' => 'clause-merge',
            'clause_number' => '7.1',
            'relation_type' => 'basis',
            'confidence' => 'review_required',
        ]],
    ],
];
$mergeTrace = [
    'external_sources' => [
        trace_work_item_candidate([
            'candidate_kind' => 'external_source',
            'candidate_kind_label' => '外部依据',
            'relation_type' => 'basis',
            'relation_label' => '主链：外部依据',
            'target_field' => 'clause_id',
            'target_id' => 'clause-merge',
            'target_label' => '7.1 归并候选',
            'target_block_id' => 'block-merge',
            'target_block_title' => '归并内容块',
            'target_block_type' => 'purpose',
            'review_url' => '/planning/structures/links/review?block_id=block-merge&candidate_kind=external_source&candidate_id=clause-merge',
        ]),
    ],
];
$mergeResult = QmsTraceWorkItemService::build($mergeBlocks, $mergeTrace);
trace_work_item_group_assert(
    'merge',
    ($mergeResult['block_count'] ?? 0) === 1
        && count((array)($mergeResult['items'] ?? [])) === 1
        && count((array)($mergeResult['items'][0]['issues'] ?? [])) === 2
        && count((array)($mergeResult['items'][0]['candidates'] ?? [])) === 1,
    '相同 block_id 的多行必须归并为一张卡，并合并去重问题与候选'
);

$relationResult = QmsTraceWorkItemService::build([
    [
        'block' => [
            'id' => 'block-relations',
            'section_number' => '8.3',
            'title' => '关系分类',
            'block_type' => 'control_requirement',
            'sort_order' => 10,
        ],
        'links' => [
            [
                'id' => 'link-basis-review',
                'clause_id' => 'clause-review',
                'relation_type' => 'basis',
                'confidence' => 'review_required',
            ],
            [
                'id' => 'link-manual-review',
                'manual_section_id' => 'manual-review',
                'section_number' => '8.3',
                'relation_type' => 'implements',
                'confidence' => 'review_required',
            ],
            [
                'id' => 'link-record-review',
                'record_form_template_id' => 'record-review',
                'record_number' => 'XZTC/BG-08-01',
                'relation_type' => 'requires_record',
                'confidence' => 'review_required',
            ],
            [
                'id' => 'link-future-primary',
                'procedure_document_id' => 'procedure-future',
                'procedure_number' => 'XZTC/CX-FUTURE',
                'relation_type' => 'future_primary',
                'confidence' => 'high',
            ],
            [
                'id' => 'link-supporting-safe',
                'clause_id' => 'clause-supporting',
                'relation_type' => 'supporting',
                'confidence' => 'review_required',
            ],
            [
                'id' => 'link-mentions-safe',
                'manual_section_id' => 'manual-mentions',
                'section_number' => '4.2',
                'relation_type' => 'mentions',
                'confidence' => 'review_required',
            ],
            [
                'id' => 'link-responsible-safe',
                'position_id' => 'position-safe',
                'relation_type' => 'responsible',
                'confidence' => 'review_required',
            ],
            [
                'id' => 'link-renders-safe',
                'business_module_id' => 'module-safe',
                'relation_type' => 'renders_to',
                'confidence' => 'review_required',
            ],
        ],
    ],
], []);
$relationIssues = (array)($relationResult['items'][0]['issues'] ?? []);
trace_work_item_group_assert(
    'relations',
    count($relationIssues) === 4
        && array_values(array_unique(array_column(
            $relationIssues,
            'code'
        ))) === ['pending_review']
        && array_column($relationIssues, 'link_id') === [
            'link-basis-review',
            'link-future-primary',
            'link-manual-review',
            'link-record-review',
        ],
    '三类未确认主链和未知关系应待复核，四类明确辅助关系不得误报'
);
trace_work_item_group_assert(
    'relations',
    count(array_unique(array_column(
        $relationIssues,
        'context_label'
    ))) === 4
        && !str_contains(
            implode('；', array_column($relationIssues, 'context_label')),
            '_id='
        ),
    '同卡不同关系问题应有可区分对象说明，且不得直接展示内部字段名'
);

$fallbackContextResult = QmsTraceWorkItemService::build([
    [
        'block' => [
            'id' => 'block-context-fallback',
            'section_number' => '9.1',
            'title' => '对象说明回退',
            'block_type' => 'control_requirement',
            'sort_order' => 10,
        ],
        'links' => [[
            'id' => 'link-context-fallback',
            'clause_id' => 'd93c9d72-53ef-4b69-86ce-a9aa15f98931',
            'relation_type' => 'basis',
            'confidence' => 'review_required',
        ]],
    ],
], []);
trace_work_item_group_assert(
    'relations',
    ($fallbackContextResult['items'][0]['issues'][0]['context_label'] ?? '')
        === '关联对象信息待补充',
    '没有人可读对象字段时应使用明确中文回退，不得显示 UUID'
);

$genericTitleContextResult = QmsTraceWorkItemService::build([
    [
        'block' => [
            'id' => 'block-context-title',
            'section_number' => '9.2',
            'title' => '通用标题对象说明',
            'block_type' => 'control_requirement',
            'sort_order' => 10,
        ],
        'links' => [[
            'id' => 'link-context-title',
            'clause_id' => 'clause-context-title',
            'source_code' => 'GB/T 27025-2019',
            'clause_number' => '8.4',
            'title' => '记录控制',
            'relation_type' => 'basis',
            'confidence' => 'review_required',
        ]],
    ],
], []);
trace_work_item_group_assert(
    'relations',
    (
        $genericTitleContextResult['items'][0]['issues'][0]['context_label']
        ?? ''
    ) === '外部依据：GB/T 27025-2019 8.4 记录控制',
    '单一对象应在编号后保留已有通用 title'
);

$candidateOrderResult = QmsTraceWorkItemService::build([
    [
        'block' => [
            'id' => 'block-candidate-order',
            'section_number' => '6.2',
            'title' => '候选顺序',
            'block_type' => 'process_step',
            'sort_order' => 10,
        ],
        'links' => [],
    ],
], [
    'manual_sections' => [
        trace_work_item_candidate([
            'target_id' => 'manual-order',
            'section_number' => '6.2',
            'target_label' => '6.2 人员',
            'target_block_id' => 'block-candidate-order',
            'target_block_title' => '候选顺序',
            'target_block_type' => 'process_step',
            'recommendation_reason' => '手册候选理由',
            'review_url' => '/planning/structures/links/review?block_id=block-candidate-order&candidate_kind=manual_section&candidate_id=manual-order',
        ]),
    ],
    'external_sources' => [
        trace_work_item_candidate([
            'candidate_kind' => 'external_source',
            'candidate_kind_label' => '外部依据',
            'relation_type' => 'basis',
            'relation_label' => '主链：外部依据',
            'target_field' => 'clause_id',
            'target_id' => 'external-order',
            'target_label' => 'CNAS-CL01 6.2',
            'target_block_id' => 'block-candidate-order',
            'target_block_title' => '候选顺序',
            'target_block_type' => 'process_step',
            'recommendation_reason' => '外部依据候选理由',
            'review_url' => '/planning/structures/links/review?block_id=block-candidate-order&candidate_kind=external_source&candidate_id=external-order',
        ]),
    ],
    'record_templates' => [
        trace_work_item_candidate([
            'candidate_kind' => 'record_template',
            'candidate_kind_label' => '运行记录',
            'relation_type' => 'requires_record',
            'relation_label' => '主链：运行记录',
            'target_field' => 'record_form_template_id',
            'target_id' => 'record-order',
            'target_label' => 'XZTC/BG-11-01 人员能力记录',
            'target_block_id' => 'block-candidate-order',
            'target_block_title' => '候选顺序',
            'target_block_type' => 'process_step',
            'recommendation_reason' => '运行记录候选理由',
            'review_url' => '/planning/structures/links/review?block_id=block-candidate-order&candidate_kind=record_template&candidate_id=record-order',
        ]),
    ],
]);
$orderedCandidates = (array)(
    $candidateOrderResult['items'][0]['candidates'] ?? []
);
trace_work_item_group_assert(
    'candidates',
    array_column($orderedCandidates, 'candidate_kind') === [
        'external_source',
        'manual_section',
        'record_template',
    ]
        && array_column($orderedCandidates, 'recommendation_reason') === [
            '外部依据候选理由',
            '手册候选理由',
            '运行记录候选理由',
        ],
    '候选应按外部依据、手册、运行记录排序并保留推荐理由'
);

$noLinkIdResult = QmsTraceWorkItemService::build([
    [
        'block' => [
            'id' => 'block-no-link-id',
            'section_number' => '7.1',
            'title' => '稳定摘要去重',
            'block_type' => 'purpose',
            'sort_order' => 10,
        ],
        'links' => [
            [
                'clause_id' => 'clause-no-link-id',
                'clause_number' => '7.1',
                'relation_type' => 'basis',
                'confidence' => 'review_required',
            ],
            [
                'clause_id' => 'clause-no-link-id',
                'clause_number' => '7.1',
                'relation_type' => 'basis',
                'confidence' => 'review_required',
            ],
        ],
    ],
], []);
trace_work_item_group_assert(
    'edges',
    count((array)($noLinkIdResult['items'][0]['issues'] ?? [])) === 1,
    '无 link_id 的相同问题应按稳定目标摘要去重'
);

$typeBlocks = [];
$typeFixtures = [
    ['type-purpose', 'purpose', 20, '1', '目的'],
    ['type-scope', 'scope', 10, '10', '范围'],
    ['type-responsibility', 'responsibility', 10, '2', 'B 职责'],
    ['type-process', 'process_step', 10, '2', 'A 过程'],
    ['type-control', 'control_requirement', 30, '3', '控制要求'],
    ['type-record', 'record_requirement', 40, '4', '记录要求'],
    ['type-default', 'future_block', 50, '5', '未知类型'],
];
foreach ($typeFixtures as [$id, $type, $order, $section, $title]) {
    $typeBlocks[] = [
        'block' => [
            'id' => $id,
            'section_number' => $section,
            'title' => $title,
            'block_type' => $type,
            'sort_order' => $order,
        ],
        'links' => [[
            'id' => 'link-' . $id,
            'clause_id' => 'clause-' . $id,
            'relation_type' => 'basis',
            'confidence' => 'review_required',
        ]],
    ];
}
$typeResult = QmsTraceWorkItemService::build($typeBlocks, []);
$typeItems = (array)($typeResult['items'] ?? []);
trace_work_item_group_assert(
    'edges',
    array_column($typeItems, 'block_id') === [
        'type-process',
        'type-responsibility',
        'type-scope',
        'type-purpose',
        'type-control',
        'type-record',
        'type-default',
    ],
    '同级卡应按 sort_order、自然 section、title 稳定排序'
);
$labelsByType = [];
foreach ($typeItems as $typeItem) {
    $labelsByType[(string)$typeItem['block_id']] =
        (string)$typeItem['block_type_label'];
}
trace_work_item_group_assert(
    'edges',
    $labelsByType === [
        'type-process' => '过程步骤',
        'type-responsibility' => '职责',
        'type-scope' => '范围',
        'type-purpose' => '目的',
        'type-control' => '控制要求',
        'type-record' => '记录要求',
        'type-default' => '内容块',
    ],
    '六类已知内容块和未知类型都应有稳定中文映射'
);

$unsafeUrlResult = QmsTraceWorkItemService::build([
    [
        'block' => [
            'id' => 'block-unsafe-url',
            'section_number' => '7.1',
            'title' => '不安全入口',
            'block_type' => 'purpose',
            'sort_order' => 10,
        ],
        'links' => [[
            'id' => 'link-unsafe-url',
            'clause_id' => 'clause-unsafe-url',
            'relation_type' => 'basis',
            'confidence' => 'review_required',
            'review_method' => 'GET',
            'review_url' => 'https://example.test/review?block_id=block-unsafe-url',
        ]],
    ],
], [
    'external_sources' => [
        trace_work_item_candidate([
            'candidate_kind' => 'external_source',
            'candidate_kind_label' => '外部依据',
            'relation_type' => 'basis',
            'relation_label' => '主链：外部依据',
            'target_field' => 'clause_id',
            'target_id' => 'candidate-post',
            'target_label' => 'POST 候选',
            'target_block_id' => 'block-unsafe-url',
            'target_block_title' => '不安全入口',
            'target_block_type' => 'purpose',
            'review_method' => 'POST',
            'review_url' => '/planning/structures/links/review?block_id=block-unsafe-url&candidate_id=candidate-post',
        ]),
        trace_work_item_candidate([
            'candidate_kind' => 'external_source',
            'candidate_kind_label' => '外部依据',
            'relation_type' => 'basis',
            'relation_label' => '主链：外部依据',
            'target_field' => 'clause_id',
            'target_id' => 'candidate-host',
            'target_label' => '主机候选',
            'target_block_id' => 'block-unsafe-url',
            'target_block_title' => '不安全入口',
            'target_block_type' => 'purpose',
            'review_url' => '//example.test/review',
        ]),
        trace_work_item_candidate([
            'candidate_kind' => 'external_source',
            'candidate_kind_label' => '外部依据',
            'relation_type' => 'basis',
            'relation_label' => '主链：外部依据',
            'target_field' => 'clause_id',
            'target_id' => 'candidate-scheme',
            'target_label' => '绝对地址候选',
            'target_block_id' => 'block-unsafe-url',
            'target_block_title' => '不安全入口',
            'target_block_type' => 'purpose',
            'review_url' => 'https://example.test/review',
        ]),
        trace_work_item_candidate([
            'candidate_kind' => 'external_source',
            'candidate_kind_label' => '外部依据',
            'relation_type' => 'basis',
            'relation_label' => '主链：外部依据',
            'target_field' => 'clause_id',
            'target_id' => 'candidate-no-slash',
            'target_label' => '无斜杠候选',
            'target_block_id' => 'block-unsafe-url',
            'target_block_title' => '不安全入口',
            'target_block_type' => 'purpose',
            'review_url' => 'planning/structures/links/review',
        ]),
    ],
]);
$unsafeUrlItem = (array)($unsafeUrlResult['items'][0] ?? []);
trace_work_item_group_assert(
    'edges',
    ($unsafeUrlItem['primary_url'] ?? 'unexpected') === ''
        && array_values(array_unique(array_column(
            (array)($unsafeUrlItem['candidates'] ?? []),
            'review_url'
        ))) === [''],
    '非 GET、带 scheme/host 或不以斜杠开头的入口必须拒绝'
);

$existingGetResult = QmsTraceWorkItemService::build([
    [
        'block' => [
            'id' => 'block-existing-get',
            'section_number' => '7.1',
            'title' => '已有 GET 入口',
            'block_type' => 'purpose',
            'sort_order' => 10,
        ],
        'links' => [[
            'id' => 'link-existing-get',
            'clause_id' => 'clause-existing-get',
            'relation_type' => 'basis',
            'confidence' => 'review_required',
            'review_method' => 'GET',
            'review_url' => '/planning/structures/links/review?block_id=block-existing-get&link_id=link-existing-get',
        ]],
    ],
], []);
trace_work_item_group_assert(
    'edges',
    ($existingGetResult['items'][0]['primary_url'] ?? '') ===
        '/planning/structures/links/review?block_id=block-existing-get&link_id=link-existing-get',
    '合法入口应只沿用同块已有 GET review_url'
);

$strictUrlRows = [];
$strictUrlCases = [
    'logout' => '/logout?block_id=block-strict-url',
    'missing-block' => '/planning/structures/links/review?candidate_id=missing-block',
    'wrong-block' => '/planning/structures/links/review?block_id=other-block',
    'raw-backslash' => '/\\example.com?block_id=block-strict-url',
    'encoded-crlf' => '/planning/structures/links/review?block_id=block-strict-url&next=%0d%0a',
    'encoded-backslash' => '/planning/structures/links/review?block_id=block-strict-url&next=%5clogout',
    'triple-encoded-cr' => '/planning/structures/links/review?block_id=block-strict-url&next=%25250d',
    'triple-encoded-backslash' => '/planning/structures/links/review?block_id=block-strict-url&next=%25255c',
    'actual-control' => "/planning/structures/links/review?block_id=block-strict-url&next=\r\n",
];
foreach ($strictUrlCases as $caseId => $reviewUrl) {
    $strictUrlRows[] = trace_work_item_candidate([
        'candidate_kind' => 'external_source',
        'candidate_kind_label' => '外部依据',
        'relation_type' => 'basis',
        'relation_label' => '主链：外部依据',
        'target_field' => 'clause_id',
        'target_id' => 'strict-' . $caseId,
        'target_label' => '严格入口 ' . $caseId,
        'target_block_id' => 'block-strict-url',
        'target_block_title' => '严格入口边界',
        'target_block_type' => 'purpose',
        'review_method' => 'GET',
        'review_url' => $reviewUrl,
    ]);
}
$strictUrlResult = QmsTraceWorkItemService::build([
    [
        'block' => [
            'id' => 'block-strict-url',
            'section_number' => '7.1',
            'title' => '严格入口边界',
            'block_type' => 'purpose',
            'sort_order' => 10,
        ],
        'links' => [],
    ],
], [
    'external_sources' => $strictUrlRows,
]);
$strictUrlItem = (array)($strictUrlResult['items'][0] ?? []);
trace_work_item_group_assert(
    'url_boundary',
    ($strictUrlItem['primary_url'] ?? 'unexpected') === ''
        && array_values(array_unique(array_column(
            (array)($strictUrlItem['candidates'] ?? []),
            'review_url'
        ))) === [''],
    '只允许同块 planning 关系复核 GET 地址，拒绝越权路径、缺块参数和控制字符歧义'
);

echo "qms_trace_work_item_service_smoke passed\n";
