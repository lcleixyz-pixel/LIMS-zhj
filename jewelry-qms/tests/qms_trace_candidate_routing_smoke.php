<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use app\service\QmsTraceCandidateRoutingService;

function candidate_routing_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

$candidate = [
    'available' => true,
    'source_kind' => 'governance_blueprint',
    'source_label' => '治理装配蓝图 / 本地条款映射',
    'external_sources' => [[
        'id' => 'clause-62',
        'source_code' => 'CNAS-CL01:2018',
        'clause_number' => '6.2',
        'title' => '人员',
        'available' => true,
    ]],
    'manual_sections' => [[
        'id' => 'manual-62',
        'section_number' => '6.2',
        'title' => '人员',
        'available' => true,
    ]],
    'record_templates' => [[
        'id' => 'record-bg1101',
        'doc_number' => 'SIM-XZTC/BG-11-01',
        'name' => '人员能力记录',
        'available' => true,
    ], [
        'id' => '',
        'doc_number' => 'SIM-XZTC/BG-11-02',
        'name' => '待治理记录',
        'available' => false,
    ]],
];

$blocks = [[
    'id' => 'block-control',
    'title' => '控制要求',
    'block_type' => 'control_requirement',
    'sort_order' => 20,
], [
    'id' => 'block-record',
    'title' => '相关记录',
    'block_type' => 'record_requirement',
    'sort_order' => 40,
], [
    'id' => 'block-process',
    'title' => '工作程序',
    'block_type' => 'process_step',
    'sort_order' => 30,
], [
    'id' => 'block-purpose',
    'title' => '目的',
    'block_type' => 'purpose',
    'sort_order' => 10,
]];

$routed = QmsTraceCandidateRoutingService::route($candidate, $blocks);

$external = $routed['external_sources'][0] ?? [];
candidate_routing_assert(
    ($external['target_block_id'] ?? '') === 'block-purpose'
        && ($external['relation_type'] ?? '') === 'basis'
        && ($external['target_field'] ?? '') === 'clause_id',
    '外部依据应进入目的块并预填外部依据用途'
);
candidate_routing_assert(
    str_contains((string)($external['review_url'] ?? ''), 'block_id=block-purpose')
        && str_contains((string)($external['review_url'] ?? ''), 'candidate_kind=external_source')
        && str_contains((string)($external['review_url'] ?? ''), 'candidate_id=clause-62'),
    '外部依据应生成只包含候选类型和 ID 的定向复核地址'
);

$manual = $routed['manual_sections'][0] ?? [];
candidate_routing_assert(
    ($manual['target_block_id'] ?? '') === 'block-process'
        && ($manual['relation_type'] ?? '') === 'implements'
        && ($manual['target_field'] ?? '') === 'manual_section_id',
    '手册章节应进入工作程序块'
);

$record = $routed['record_templates'][0] ?? [];
candidate_routing_assert(
    ($record['target_block_id'] ?? '') === 'block-record'
        && ($record['relation_type'] ?? '') === 'requires_record'
        && ($record['target_field'] ?? '') === 'record_form_template_id',
    '运行记录应进入记录要求块'
);

$unavailableRecord = $routed['record_templates'][1] ?? [];
candidate_routing_assert(
    ($unavailableRecord['routable'] ?? true) === false
        && str_contains((string)($unavailableRecord['routing_issue'] ?? ''), '尚未入库'),
    '没有实体 ID 的候选不得生成可执行入口'
);
candidate_routing_assert(
    ($routed['routing_summary']['routable'] ?? 0) === 3
        && ($routed['routing_summary']['blocked'] ?? 0) === 1,
    '路由摘要应区分可带入和暂不可带入候选'
);

$prefill = QmsTraceCandidateRoutingService::resolvePrefill(
    $routed,
    'block-process',
    ['candidate_kind' => 'manual_section', 'candidate_id' => 'manual-62']
);
candidate_routing_assert(
    ($prefill['requested'] ?? false) === true
        && ($prefill['available'] ?? false) === true
        && ($prefill['target_field'] ?? '') === 'manual_section_id'
        && ($prefill['target_id'] ?? '') === 'manual-62'
        && ($prefill['relation_type'] ?? '') === 'implements',
    '合法手册候选应生成唯一对象的安全预填'
);

$wrongBlock = QmsTraceCandidateRoutingService::resolvePrefill(
    $routed,
    'block-record',
    ['candidate_kind' => 'manual_section', 'candidate_id' => 'manual-62']
);
candidate_routing_assert(
    ($wrongBlock['available'] ?? true) === false
        && str_contains((string)($wrongBlock['error'] ?? ''), '建议内容块已变化'),
    '候选不得被改送其他内容块'
);

$forged = QmsTraceCandidateRoutingService::resolvePrefill(
    $routed,
    'block-process',
    ['candidate_kind' => 'manual_section', 'candidate_id' => 'manual-forged']
);
candidate_routing_assert(
    ($forged['available'] ?? true) === false
        && str_contains((string)($forged['error'] ?? ''), '候选已变化'),
    '不属于当前文件的候选 ID 不得生成预填'
);

$plain = QmsTraceCandidateRoutingService::resolvePrefill($routed, 'block-process', []);
candidate_routing_assert(
    ($plain['requested'] ?? true) === false
        && ($plain['available'] ?? true) === false,
    '普通无参数复核入口不得误触发预填'
);

echo "qms_trace_candidate_routing_smoke passed\n";
