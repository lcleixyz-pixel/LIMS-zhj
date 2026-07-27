<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use app\service\GovernedTrialResolvedDocumentService;
use app\service\QmsFileGovernanceWorkbenchService;

function workbench_service_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

$root = dirname(__DIR__);
$structureSource = (string)file_get_contents($root . '/app/service/QmsDocumentStructureService.php');
$resolvedSource = (string)file_get_contents($root . '/app/service/GovernedTrialResolvedDocumentService.php');

preg_match(
    '/private static function linksForBlock\\(string \\$blockId\\): array.*?->toArray\\(\\);/s',
    $structureSource,
    $linksMethod
);
workbench_service_assert($linksMethod !== [], '未找到 linksForBlock 方法源码');
foreach ([
    'l.clause_id',
    'l.manual_section_id',
    'l.procedure_document_id',
    'l.record_form_template_id',
    'l.position_id',
    'l.business_module_id',
] as $requiredField) {
    workbench_service_assert(
        str_contains((string)$linksMethod[0], $requiredField),
        '块级追溯结果缺少对象 ID：' . $requiredField
    );
}

workbench_service_assert(
    str_contains($resolvedSource, 'public static function currentConflictSummary'),
    '治理解析稿服务应提供只读冲突摘要'
);

$summary = GovernedTrialResolvedDocumentService::splitConflictSummary([
    'blocking_conflicts' => [
        ['doc_number' => 'XZTC/CX-03-02-2022', 'message' => '当前文件冲突'],
        ['doc_number' => 'XZTC/CX-21-2022', 'message' => '其他文件冲突'],
    ],
    'warnings' => [
        ['doc_number' => 'SYSTEM', 'message' => '体系提醒'],
    ],
], 'XZTC/CX-03-02-2022');

workbench_service_assert(count($summary['document_blockers']) === 1, '应只保留当前文件阻断');
workbench_service_assert(count($summary['system_notices']) === 2, '其他文件冲突和体系提醒应归入体系提示');

$viewModel = QmsFileGovernanceWorkbenchService::fromSnapshot(
    [
        'document' => [
            'id' => 'structured-cx0302',
            'document_id' => 'document-cx0302',
            'document_role' => 'procedure',
            'doc_number' => 'XZTC/CX-03-02-2022',
            'title' => '标准物质管理程序',
            'version' => 'GOV-TRIAL/0.2',
            'status' => 'draft',
        ],
        'blocks' => [
            [
                'block' => [
                    'id' => 'block-1',
                    'title' => '职责',
                    'block_type' => 'section',
                ],
                'links' => [
                    [
                        'clause_id' => 'clause-64',
                        'source_code' => 'CNAS-CL01',
                        'clause_number' => '6.4',
                        'clause_title' => '设备',
                        'relation_type' => 'basis',
                        'confidence' => 'high',
                        'note' => '外部依据人工复核确认。',
                    ],
                    [
                        'manual_section_id' => 'manual-64',
                        'section_number' => '6.4',
                        'manual_title' => '标准物质',
                        'relation_type' => 'implements',
                        'confidence' => 'high',
                        'note' => '手册落实关系人工复核确认。',
                    ],
                    [
                        'record_form_template_id' => 'form-bg3503',
                        'record_number' => 'XZTC/BG-35-03',
                        'record_name' => '标准物质报废申请表',
                        'relation_type' => 'requires_record',
                        'confidence' => 'high',
                        'note' => '记录要求人工复核确认。',
                    ],
                    [
                        'clause_id' => 'clause-64',
                        'source_code' => 'CNAS-CL01',
                        'clause_number' => '6.4',
                        'clause_title' => '设备',
                        'relation_type' => 'basis',
                        'confidence' => 'high',
                        'note' => '重复外部依据用于去重测试。',
                    ],
                ],
            ],
        ],
    ],
    [
        [
            'block_id' => 'block-1',
            'coverage_status' => 'covered',
            'linked_record_forms' => 1,
            'schema_field_count' => 8,
            'record_form_labels' => 'XZTC/BG-35-03 标准物质报废申请表',
            'trace_review_url' => '/planning/structures/links/review?block_id=block-1',
        ],
    ],
    [
        'is_resolved_trial' => true,
        'continuous_url' => '/continuous',
        'comparison_url' => '/comparison',
        'conflicts_url' => '/conflicts',
    ],
    [
        'available' => true,
        'document_blockers' => [],
        'system_notices' => [],
    ],
    [
        'id' => 'document-cx0302',
        'status' => 'draft',
        'doc_number' => 'SIM-GOV02-XZTC/CX-03-02-2022',
    ],
    [
        'stage' => 'draft',
        'stage_label' => '草稿，等待提交',
    ]
);

workbench_service_assert(count($viewModel['chain']['external_sources']) === 1, '外部依据应按 ID 去重');
workbench_service_assert(count($viewModel['chain']['manual_sections']) === 1, '手册条款应按 ID 去重');
workbench_service_assert(count($viewModel['chain']['record_evidence']) === 1, '记录表格应按 ID 去重');
workbench_service_assert(count($viewModel['chain']['confirmed_external_sources']) === 1, '已确认外部依据主链应单独计数');
workbench_service_assert(count($viewModel['chain']['confirmed_manual_sections']) === 1, '已确认手册主链应单独计数');
workbench_service_assert(count($viewModel['chain']['confirmed_record_evidence']) === 1, '已确认运行证据应单独计数');
workbench_service_assert(
    !array_key_exists('_key', $viewModel['chain']['external_sources'][0]),
    '内部去重键不得暴露给页面'
);
workbench_service_assert($viewModel['record_coverage']['covered'] === 1, '应识别字段已覆盖记录');
workbench_service_assert($viewModel['summary']['level'] === 'ready', '无断链无阻断的草稿应可继续试运行');
workbench_service_assert(
    $viewModel['actions'][0]['url'] === '/document/view?id=document-cx0302',
    '下一步应进入现有文件页提交'
);

$wrongManual = QmsFileGovernanceWorkbenchService::fromSnapshot(
    [
        'document' => [
            'id' => 'structured-cx08',
            'document_id' => 'document-cx08',
            'document_role' => 'procedure',
            'doc_number' => 'XZTC/CX-08-2022',
            'title' => '文件控制程序',
            'status' => 'draft',
        ],
        'blocks' => [
            [
                'block' => [
                    'id' => 'block-cx08-purpose',
                    'title' => '目的',
                    'block_type' => 'purpose',
                    'markdown' => '明确文件控制要求。',
                ],
                'links' => [],
            ],
            [
                'block' => [
                    'id' => 'block-cx08',
                    'title' => '控制要求',
                    'block_type' => 'section',
                    'markdown' => '文件应经过批准、修订、分发、回收和作废控制。',
                ],
                'links' => [
                    [
                        'clause_id' => 'clause-cx08',
                        'source_code' => 'CNAS-CL01',
                        'clause_number' => '8.3',
                        'clause_title' => '管理体系文件的控制',
                        'relation_type' => 'basis',
                        'confidence' => 'high',
                        'note' => '外部依据人工确认。',
                    ],
                    [
                        'manual_section_id' => 'manual-42',
                        'section_number' => '4.2',
                        'manual_title' => '保密性',
                        'relation_type' => 'implements',
                        'confidence' => 'high',
                        'note' => '历史错挂。',
                    ],
                    [
                        'record_form_template_id' => 'form-cx08',
                        'record_number' => 'XZTC/BG-08-02',
                        'record_name' => '文件修订记录',
                        'relation_type' => 'requires_record',
                        'confidence' => 'high',
                        'note' => '记录要求人工确认。',
                    ],
                ],
            ],
        ],
    ],
    [[
        'block_id' => 'block-cx08',
        'coverage_status' => 'covered',
        'linked_record_forms' => 1,
        'schema_field_count' => 4,
        'trace_review_url' => '/planning/structures/links/review?block_id=block-cx08',
    ]],
    [],
    ['document_blockers' => [], 'system_notices' => []],
    ['id' => 'document-cx08', 'status' => 'draft'],
    ['stage' => 'draft', 'stage_label' => '草稿，等待提交']
);
workbench_service_assert($wrongManual['summary']['level'] === 'blocked', '疑似错挂不得显示证据链闭合');
workbench_service_assert(
    in_array('手册主链', $wrongManual['chain']['missing'], true),
    'CX-08 只有 4.2 时应明确缺少手册主链'
);
workbench_service_assert(
    ($wrongManual['semantic_guard']['status'] ?? '') === 'suspected_mismatch',
    '工作台应暴露 CX-08 疑似错挂结论'
);
workbench_service_assert(
    str_contains((string)$wrongManual['summary']['message'], '疑似错挂'),
    '工作台结论文案应明确说明疑似错挂'
);
workbench_service_assert(
    $wrongManual['actions'][0]['url'] === '/planning/structures/links/review?block_id=block-cx08',
    '疑似错挂下一步应直接进入实际含错挂关系的内容块'
);
workbench_service_assert(
    str_contains((string)$wrongManual['actions'][0]['description'], '移除错挂关系')
    && str_contains((string)$wrongManual['actions'][0]['description'], '8.3'),
    '疑似错挂下一步应说明如何拆分并补建正确手册主链'
);

$missingManual = QmsFileGovernanceWorkbenchService::fromSnapshot(
    [
        'document' => [
            'id' => 'structured-missing-manual',
            'document_id' => 'document-missing-manual',
            'document_role' => 'procedure',
            'doc_number' => 'XZTC/CX-TEST-2022',
            'title' => '断链测试程序',
            'status' => 'draft',
        ],
        'blocks' => [[
            'block' => ['id' => 'block-missing-manual', 'title' => '职责', 'block_type' => 'section'],
            'links' => [[
                'clause_id' => 'clause-test',
                'source_code' => 'CNAS-CL01',
                'clause_number' => '7.1',
                'relation_type' => 'basis',
                'confidence' => 'high',
                'note' => '外部依据人工确认。',
                'record_form_template_id' => 'form-test',
                'record_number' => 'XZTC/BG-TEST',
                'record_name' => '测试记录表',
            ]],
        ]],
    ],
    [[
        'block_id' => 'block-missing-manual',
        'coverage_status' => 'covered',
        'linked_record_forms' => 1,
        'schema_field_count' => 3,
        'trace_review_url' => '/planning/structures/links/review?block_id=block-missing-manual',
    ]],
    [],
    ['document_blockers' => [], 'system_notices' => []],
    ['id' => 'document-missing-manual', 'status' => 'draft'],
    ['stage' => 'draft', 'stage_label' => '草稿，等待提交']
);
workbench_service_assert($missingManual['summary']['level'] === 'blocked', '缺少手册条款必须阻断');
workbench_service_assert(
    $missingManual['actions'][0]['url'] === '/planning/structures/links/review?block_id=block-missing-manual',
    '断链下一步应进入内容块追溯复核'
);

$schemaGap = QmsFileGovernanceWorkbenchService::fromSnapshot(
    [
        'document' => [
            'id' => 'structured-schema-gap',
            'document_id' => 'document-schema-gap',
            'document_role' => 'procedure',
            'doc_number' => 'XZTC/CX-GAP-2022',
            'title' => '字段缺口测试程序',
            'status' => 'draft',
        ],
        'blocks' => [[
            'block' => ['id' => 'block-schema-gap', 'title' => '记录', 'block_type' => 'record_requirement'],
            'links' => [[
                'clause_id' => 'clause-gap',
                'source_code' => 'CNAS-CL01',
                'clause_number' => '7.5',
                'relation_type' => 'basis',
                'confidence' => 'high',
                'note' => '外部依据人工确认。',
            ], [
                'manual_section_id' => 'manual-gap',
                'section_number' => '7.5',
                'manual_title' => '技术记录',
                'relation_type' => 'implements',
                'confidence' => 'high',
                'note' => '手册落实关系人工确认。',
            ], [
                'record_form_template_id' => 'form-gap',
                'record_number' => 'XZTC/BG-GAP',
                'record_name' => '字段缺口记录表',
                'relation_type' => 'requires_record',
                'confidence' => 'high',
                'note' => '记录要求人工确认。',
            ]],
        ]],
    ],
    [[
        'block_id' => 'block-schema-gap',
        'coverage_status' => 'gap',
        'linked_record_forms' => 1,
        'schema_field_count' => 0,
        'record_form_labels' => 'XZTC/BG-GAP 字段缺口记录表',
        'trace_review_url' => '/planning/structures/links/review?block_id=block-schema-gap',
    ]],
    [],
    ['document_blockers' => [], 'system_notices' => []],
    ['id' => 'document-schema-gap', 'status' => 'draft'],
    ['stage' => 'draft', 'stage_label' => '草稿，等待提交']
);
workbench_service_assert($schemaGap['record_coverage']['needs_review'] === 1, '已关联但字段为空应进入待复核');
workbench_service_assert($schemaGap['summary']['level'] === 'warning', '字段待复核应提示但不伪造完成');
workbench_service_assert($schemaGap['actions'][0]['type'] === 'record', '字段缺口应优先进入记录支撑复核');

$obsolete = QmsFileGovernanceWorkbenchService::fromSnapshot(
    $viewModel === [] ? [] : [
        'document' => array_merge($viewModel['document'], ['status' => 'obsolete']),
        'blocks' => [[
            'block' => ['id' => 'obsolete-block', 'title' => '历史内容', 'block_type' => 'section'],
            'links' => [[
                'clause_id' => 'obsolete-clause',
                'source_code' => 'CNAS-CL01',
                'clause_number' => '6.4',
                'relation_type' => 'basis',
                'confidence' => 'high',
                'note' => '外部依据人工确认。',
            ], [
                'manual_section_id' => 'obsolete-manual',
                'section_number' => '6.4',
                'manual_title' => '标准物质',
                'relation_type' => 'implements',
                'confidence' => 'high',
                'note' => '手册落实关系人工确认。',
            ], [
                'record_form_template_id' => 'obsolete-form',
                'record_number' => 'XZTC/BG-OLD',
                'record_name' => '历史记录',
                'relation_type' => 'requires_record',
                'confidence' => 'high',
                'note' => '记录要求人工确认。',
            ]],
        ]],
    ],
    [[
        'block_id' => 'obsolete-block',
        'coverage_status' => 'covered',
        'linked_record_forms' => 1,
        'schema_field_count' => 2,
    ]],
    [],
    ['document_blockers' => [], 'system_notices' => []],
    ['id' => 'document-cx0302', 'status' => 'obsolete'],
    ['stage' => 'draft', 'stage_label' => '草稿，等待提交']
);
workbench_service_assert(
    array_filter($obsolete['actions'], static fn(array $action): bool => $action['type'] === 'document') === [],
    '已废止文件不得生成提交签批动作'
);

$candidateDetail = [
    'document' => [
        'id' => 'structured-candidate',
        'document_id' => 'document-candidate',
        'document_role' => 'procedure',
        'doc_number' => 'XZTC/CX-TEST-2022',
        'title' => '人员能力管理程序',
        'status' => 'draft',
    ],
    'blocks' => [[
        'block' => [
            'id' => 'block-candidate',
            'title' => '能力要求',
            'block_type' => 'section',
            'markdown' => '规定人员能力确认和记录要求。',
        ],
        'links' => [],
    ]],
];
$candidateBaseline = QmsFileGovernanceWorkbenchService::fromSnapshot(
    $candidateDetail,
    [],
    [],
    ['document_blockers' => [], 'system_notices' => []],
    ['id' => 'document-candidate', 'status' => 'draft'],
    ['stage' => 'draft', 'stage_label' => '草稿，等待提交']
);
$candidateWorkbench = QmsFileGovernanceWorkbenchService::fromSnapshot(
    $candidateDetail,
    [],
    [],
    ['document_blockers' => [], 'system_notices' => []],
    ['id' => 'document-candidate', 'status' => 'draft'],
    ['stage' => 'draft', 'stage_label' => '草稿，等待提交'],
    [
        'available' => true,
        'source_kind' => 'governance_blueprint',
        'source_label' => '治理装配蓝图 / 本地条款映射',
        'canonical_doc_number' => 'XZTC/CX-TEST-2022',
        'manual_sections' => [
            ['section_number' => '6.2', 'title' => '人员'],
        ],
        'external_sources' => [
            [
                'source_code' => 'CNAS-CL01:2018',
                'clause_number' => '6.2',
                'title' => '人员',
            ],
        ],
        'record_templates' => [
            ['doc_number' => 'XZTC/BG-11-01', 'name' => '人员能力记录'],
        ],
        'review_required' => true,
        'candidate_complete' => true,
        'issues' => [],
    ]
);
workbench_service_assert(
    ($candidateWorkbench['candidate_trace']['review_required'] ?? false) === true,
    '工作台应单独暴露只读候选链'
);
workbench_service_assert(
    ($candidateWorkbench['candidate_trace']['review_url'] ?? '')
        === '/planning/structures/links/review?block_id=block-candidate',
    '候选链应复用现有人工追溯复核入口'
);
workbench_service_assert(
    $candidateWorkbench['chain']['confirmed_external_sources'] === []
        && $candidateWorkbench['chain']['confirmed_manual_sections'] === []
        && $candidateWorkbench['chain']['confirmed_record_evidence'] === [],
    '治理候选不得混入已确认主链'
);
workbench_service_assert(
    $candidateWorkbench['summary']['completed_checks']
        === $candidateBaseline['summary']['completed_checks'],
    '治理候选不得增加闭环证据计数'
);
workbench_service_assert(
    ($candidateWorkbench['semantic_guard']['status'] ?? '') !== 'not_assessed',
    '有治理候选的通用程序应进入语义复核状态'
);

echo "qms_file_governance_workbench_service_smoke passed\n";
