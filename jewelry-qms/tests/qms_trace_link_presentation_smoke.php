<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use app\service\QmsTraceLinkPresentationService;

function trace_link_presentation_assert(
    bool $condition,
    string $message
): void {
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

$links = [
    [
        'id' => 'mixed-link',
        'relation_type' => 'requires_record',
        'governance_state' => 'pending_review',
        'procedure_document_id' => 'procedure-1',
        'procedure_number' => 'XZTC/CX-03-02-2022',
        'procedure_title' => '标准物质管理程序',
        'record_form_template_id' => 'record-1',
        'record_number' => 'XZTC/BG-35-03',
        'record_name' => '标准物质报废申请表',
        'relation_policy' => [
            'is_mixed' => true,
            'target_count' => 2,
            'split_preview' => [],
        ],
    ],
    [
        'id' => 'basis-link',
        'relation_type' => 'basis',
        'governance_state' => 'confirmed_primary',
        'clause_id' => 'clause-1',
        'source_code' => 'CNAS-CL01:2018',
        'clause_number' => '7.1',
        'clause_title' => '要求、标书和合同的评审',
        'note' => '外部依据已人工核对。',
        'relation_policy' => ['is_mixed' => false],
    ],
    [
        'id' => 'manual-link',
        'relation_type' => 'implements',
        'governance_state' => 'suspected_mismatch',
        'manual_section_id' => 'manual-1',
        'section_number' => '6.4',
        'manual_title' => '设备',
        'relation_policy' => ['is_mixed' => false],
    ],
    [
        'id' => 'record-link',
        'relation_type' => 'requires_record',
        'governance_state' => 'pending_review',
        'record_form_template_id' => 'record-2',
        'record_number' => 'XZTC/BG-35-02',
        'record_name' => '标准物质使用记录表',
        'relation_policy' => ['is_mixed' => false],
    ],
    [
        'id' => 'responsible-link',
        'relation_type' => 'responsible',
        'governance_state' => 'supporting',
        'position_id' => 'position-1',
        'position_name' => '标准物质管理员',
        'relation_policy' => ['is_mixed' => false],
    ],
    [
        'id' => 'execution-link',
        'relation_type' => 'renders_to',
        'governance_state' => 'supporting',
        'business_module_id' => 'module-1',
        'module_code' => 'RM',
        'module_name' => '标准物质台账',
        'relation_policy' => ['is_mixed' => false],
    ],
    [
        'id' => 'mention-link',
        'relation_type' => 'mentions',
        'governance_state' => 'supporting',
        'element_id' => 'element-1',
        'element_name' => '资源要求',
        'relation_policy' => ['is_mixed' => false],
    ],
    [
        'id' => 'unknown-link',
        'relation_type' => 'legacy_unknown',
        'governance_state' => '',
        'procedure_document_id' => 'procedure-missing-label',
        'relation_policy' => ['is_mixed' => false],
    ],
];

$presentation = QmsTraceLinkPresentationService::build($links);

trace_link_presentation_assert(
    ($presentation['total'] ?? 0) === count($links),
    '展示模型不得丢失任何关系'
);
trace_link_presentation_assert(
    count((array)($presentation['priority'] ?? [])) === 1
        && ($presentation['priority'][0]['id'] ?? '') === 'mixed-link',
    '历史混装关系应只进入优先处理区'
);

$groups = (array)($presentation['groups'] ?? []);
trace_link_presentation_assert(
    array_column($groups, 'key') === [
        'primary',
        'responsibility',
        'execution',
        'supporting',
    ],
    '业务分组顺序必须稳定'
);
$groupsByKey = [];
foreach ($groups as $group) {
    $groupsByKey[(string)$group['key']] = $group;
}
trace_link_presentation_assert(
    count($groupsByKey['primary']['links']) === 3
        && count($groupsByKey['responsibility']['links']) === 1
        && count($groupsByKey['execution']['links']) === 1
        && count($groupsByKey['supporting']['links']) === 2,
    '各类关系应进入对应业务分组'
);

$basis = $groupsByKey['primary']['links'][0];
trace_link_presentation_assert(
    ($basis['relation_label'] ?? '') === '主链：外部依据'
        && ($basis['state_label'] ?? '') === '已确认主链'
        && ($basis['state_class'] ?? '') === 'badge-status-effective',
    '关系用途和治理状态应转换为中文标签'
);
trace_link_presentation_assert(
    ($basis['targets'][0]['label'] ?? '') === '外部条款'
        && str_contains(
            (string)($basis['targets'][0]['value'] ?? ''),
            'CNAS-CL01:2018 7.1'
        ),
    '外部条款卡片应显示依据编号、条款号和名称'
);

$unknown = $groupsByKey['supporting']['links'][1];
trace_link_presentation_assert(
    ($unknown['relation_label'] ?? '') === '待确认关系'
        && ($unknown['state_label'] ?? '') === '状态待确认',
    '未知历史类型和状态应安全降级为待确认'
);
trace_link_presentation_assert(
    count((array)($unknown['targets'] ?? [])) === 1
        && ($unknown['targets'][0]['label'] ?? '') === '程序文件'
        && ($unknown['targets'][0]['value'] ?? '')
            === '对象信息待补充',
    '有对象 ID 但名称缺失时应提示补充信息，且不输出空对象'
);

echo "qms_trace_link_presentation_smoke passed\n";
