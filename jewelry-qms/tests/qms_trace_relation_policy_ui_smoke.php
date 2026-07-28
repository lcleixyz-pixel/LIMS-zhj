<?php
declare(strict_types=1);

$viewPath = dirname(__DIR__) . '/app/view/planning_structure/link_review.html';
$view = file_get_contents($viewPath) ?: '';

function trace_relation_policy_ui_assert_contains(string $needle, string $haystack, string $message): void
{
    if (!str_contains($haystack, $needle)) {
        fwrite(STDERR, $message . PHP_EOL);
        fwrite(STDERR, 'Missing: ' . $needle . PHP_EOL);
        exit(1);
    }
}

trace_relation_policy_ui_assert_contains(
    'id="relation_policy_help"',
    $view,
    '新增关系表单应显示当前用途的中文说明'
);
trace_relation_policy_ui_assert_contains(
    'id="relation_target_kind"',
    $view,
    '辅助关系和仅提及时应先选择对象类别'
);
foreach ([
    'element_id',
    'clause_id',
    'manual_section_id',
    'procedure_document_id',
    'record_form_template_id',
    'position_id',
    'business_module_id',
] as $targetField) {
    trace_relation_policy_ui_assert_contains(
        'data-relation-target="' . $targetField . '"',
        $view,
        '新增关系表单应为 ' . $targetField . ' 提供用途驱动容器'
    );
}
trace_relation_policy_ui_assert_contains(
    'function applyRelationPolicy',
    $view,
    '页面应根据关系用途刷新可选对象'
);
trace_relation_policy_ui_assert_contains(
    'field.disabled = !visible',
    $view,
    '隐藏对象字段必须禁用，避免继续提交旧值'
);
trace_relation_policy_ui_assert_contains(
    '历史混装，需拆分',
    $view,
    '历史混装关系应显示业务可读警告'
);
trace_relation_policy_ui_assert_contains(
    '拆分预览',
    $view,
    '历史混装关系应显示只读拆分预览'
);
trace_relation_policy_ui_assert_contains(
    '$link.relation_policy.is_mixed',
    $view,
    '历史混装关系应隐藏整体确认表单'
);
trace_relation_policy_ui_assert_contains(
    '$detail.prefill.relation_type',
    $view,
    '候选带入后应预选关系用途'
);
trace_relation_policy_ui_assert_contains(
    '$detail.prefill.target_field',
    $view,
    '候选带入后应只预选对应的唯一对象字段'
);
trace_relation_policy_ui_assert_contains(
    '$detail.prefill.recommendation_reason',
    $view,
    '候选带入后应预填可修改的推荐理由'
);
trace_relation_policy_ui_assert_contains(
    'applyRelationPolicy();',
    $view,
    '候选预填仍必须经过现有用途驱动字段切换'
);
trace_relation_policy_ui_assert_contains(
    'data-searchable-trace-select',
    $view,
    '证明对象选择框应启用零依赖中文搜索'
);
trace_relation_policy_ui_assert_contains(
    "input.type = 'search'",
    $view,
    '搜索控件应使用原生搜索输入'
);
trace_relation_policy_ui_assert_contains(
    '输入编号或名称搜索',
    $view,
    '搜索控件应提供中文操作提示'
);
trace_relation_policy_ui_assert_contains(
    '{$option.governance_label}',
    $view,
    '本文件候选、版本和状态应使用统一治理标签'
);
trace_relation_policy_ui_assert_contains(
    'searchInput.disabled = !visible',
    $view,
    '隐藏对象的搜索框必须同步禁用'
);
trace_relation_policy_ui_assert_contains(
    "setAttribute('aria-live', 'polite')",
    $view,
    '搜索结果说明应能被辅助技术感知'
);
trace_relation_policy_ui_assert_contains(
    '$detail.default_relation_type',
    $view,
    '无候选预填时应根据内容块类型选择默认用途'
);

echo "qms_trace_relation_policy_ui_smoke passed\n";
