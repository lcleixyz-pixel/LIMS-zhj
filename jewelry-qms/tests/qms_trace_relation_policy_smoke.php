<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use app\service\QmsTraceRelationPolicyService;

function trace_relation_policy_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

function trace_relation_policy_throws(callable $callback, string $needle, string $message): void
{
    try {
        $callback();
    } catch (Throwable $exception) {
        trace_relation_policy_assert(
            str_contains($exception->getMessage(), $needle),
            $message . '；实际提示：' . $exception->getMessage()
        );
        return;
    }

    trace_relation_policy_assert(false, $message . '；预期抛出包含“' . $needle . '”的异常');
}

trace_relation_policy_assert(
    class_exists(QmsTraceRelationPolicyService::class),
    '应提供人工追溯关系用途策略服务'
);

$definitions = QmsTraceRelationPolicyService::definitions();
trace_relation_policy_assert(count($definitions) === 7, '应定义七种人工追溯关系用途');
trace_relation_policy_assert(
    ($definitions['implements']['required_target'] ?? '') === 'manual_section_id',
    '落实手册必须以手册章节为主要对象'
);
trace_relation_policy_assert(
    ($definitions['basis']['optional_targets'] ?? []) === ['element_id'],
    '外部依据只允许附带一个要素分类'
);

QmsTraceRelationPolicyService::validatePayload([
    'relation_type' => 'basis',
    'clause_id' => 'clause-1',
    'element_id' => 'element-1',
]);
QmsTraceRelationPolicyService::validatePayload([
    'relation_type' => 'implements',
    'manual_section_id' => 'manual-83',
]);

trace_relation_policy_throws(
    static fn() => QmsTraceRelationPolicyService::validatePayload([
        'relation_type' => 'implements',
        'record_form_template_id' => 'record-1',
    ]),
    '主链：落实手册必须选择手册章节',
    '缺少关系用途必选对象时应给出中文提示'
);

trace_relation_policy_throws(
    static fn() => QmsTraceRelationPolicyService::validatePayload([
        'relation_type' => 'implements',
        'manual_section_id' => 'manual-83',
        'record_form_template_id' => 'record-1',
    ]),
    '主链：落实手册只能选择手册章节',
    '落实手册不得同时夹带记录表格'
);

trace_relation_policy_throws(
    static fn() => QmsTraceRelationPolicyService::validatePayload([
        'relation_type' => 'supporting',
        'procedure_document_id' => 'procedure-1',
        'business_module_id' => 'module-1',
    ]),
    '辅助关系一次只能选择一个追溯对象',
    '辅助关系不得混装两个主要对象'
);

$validBasisInspection = QmsTraceRelationPolicyService::inspectExistingLink([
    'clause_id' => 'clause-1',
    'element_id' => 'element-1',
    'relation_type' => 'basis',
]);
trace_relation_policy_assert(
    ($validBasisInspection['is_mixed'] ?? true) === false,
    '外部依据附带一个要素属于合法关系，不应误报为历史混装'
);

$inspection = QmsTraceRelationPolicyService::inspectExistingLink([
    'clause_id' => 'clause-1',
    'clause_number' => '8.3',
    'source_code' => 'CNAS-CL01',
    'manual_section_id' => 'manual-83',
    'section_number' => '8.3',
    'manual_title' => '管理体系文件的控制',
    'record_form_template_id' => 'record-1',
    'record_number' => 'XZTC/BG-08-01',
    'record_name' => '受控文件清单',
    'relation_type' => 'requires_record',
]);
trace_relation_policy_assert(($inspection['is_mixed'] ?? false) === true, '三个对象同处一行应识别为历史混装');
trace_relation_policy_assert(($inspection['target_count'] ?? 0) === 3, '历史混装应统计三个对象');
trace_relation_policy_assert(count($inspection['split_preview'] ?? []) === 3, '历史混装应生成三条拆分预览');
trace_relation_policy_assert(
    ($inspection['split_preview'][0]['relation_type'] ?? '') === 'basis',
    '外部条款拆分预览应使用外部依据'
);
trace_relation_policy_assert(
    ($inspection['split_preview'][1]['relation_type'] ?? '') === 'implements',
    '手册章节拆分预览应使用落实手册'
);
trace_relation_policy_assert(
    ($inspection['split_preview'][2]['relation_type'] ?? '') === 'requires_record',
    '记录表格拆分预览应使用运行记录'
);

echo "qms_trace_relation_policy_smoke passed\n";
