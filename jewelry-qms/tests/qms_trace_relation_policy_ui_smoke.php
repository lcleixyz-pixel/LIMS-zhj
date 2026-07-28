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

echo "qms_trace_relation_policy_ui_smoke passed\n";
