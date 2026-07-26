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
                        'manual_section_id' => 'manual-64',
                        'section_number' => '6.4',
                        'manual_title' => '标准物质',
                        'record_form_template_id' => 'form-bg3503',
                        'record_number' => 'XZTC/BG-35-03',
                        'record_name' => '标准物质报废申请表',
                    ],
                    [
                        'clause_id' => 'clause-64',
                        'source_code' => 'CNAS-CL01',
                        'clause_number' => '6.4',
                        'clause_title' => '设备',
                        'manual_section_id' => 'manual-64',
                        'section_number' => '6.4',
                        'manual_title' => '标准物质',
                        'record_form_template_id' => 'form-bg3503',
                        'record_number' => 'XZTC/BG-35-03',
                        'record_name' => '标准物质报废申请表',
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
                'manual_section_id' => 'manual-gap',
                'section_number' => '7.5',
                'manual_title' => '技术记录',
                'record_form_template_id' => 'form-gap',
                'record_number' => 'XZTC/BG-GAP',
                'record_name' => '字段缺口记录表',
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
                'manual_section_id' => 'obsolete-manual',
                'section_number' => '6.4',
                'manual_title' => '标准物质',
                'record_form_template_id' => 'obsolete-form',
                'record_number' => 'XZTC/BG-OLD',
                'record_name' => '历史记录',
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

echo "qms_file_governance_workbench_service_smoke passed\n";
