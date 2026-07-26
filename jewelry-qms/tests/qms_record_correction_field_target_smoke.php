<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use app\service\RecordFormCorrectionService;

function correction_target_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

$schema = [
    [
        'key' => 'record_date',
        'label' => '记录日期',
        'type' => 'date',
    ],
    [
        'key' => 'record_items',
        'label' => '记录明细',
        'type' => 'repeatable_table',
        'columns' => [
            ['key' => 'item', 'label' => '事项', 'type' => 'text'],
            ['key' => 'result', 'label' => '处理结果', 'type' => 'text'],
        ],
    ],
];
$values = [
    'record_date' => '2026-07-25',
    'record_items' => [
        ['item' => 'SIM 标准物质报废申请', 'result' => '已完成模拟流程'],
    ],
];

$targets = RecordFormCorrectionService::targets($schema, $values);
correction_target_assert(
    ($targets['field:record_date']['original_value'] ?? null) === '2026-07-25',
    'Ordinary field target must carry its server-side original value'
);
correction_target_assert(
    ($targets['cell:record_items:0:item']['label'] ?? null) === '记录明细 / 第1行 / 事项',
    'Repeatable-table cell target must identify table, row and column'
);
correction_target_assert(
    ($targets['append:record_items']['columns'][0]['key'] ?? null) === 'item',
    'Repeatable-table append target must carry the complete column schema'
);

$preparedField = RecordFormCorrectionService::prepare($schema, $values, [
    'correction_type' => 'amendment',
    'field_path' => 'field:record_date',
    'corrected_value' => '2026-07-26',
]);
correction_target_assert(
    $preparedField['original_content'] === '2026-07-25'
        && $preparedField['corrected_content'] === '2026-07-26'
        && $preparedField['field_label'] === '记录日期',
    'Prepared ordinary-field correction must ignore client-side original values'
);

$preparedRow = RecordFormCorrectionService::prepare($schema, $values, [
    'correction_type' => 'supplement',
    'field_path' => 'append:record_items',
    'row_values' => [
        'item' => '新增处置复核',
        'result' => '待复核',
        'unknown' => 'must be discarded',
    ],
]);
correction_target_assert(
    $preparedRow['target_kind'] === 'append_row'
        && $preparedRow['corrected_content'] === "事项：新增处置复核\n处理结果：待复核"
        && !str_contains($preparedRow['row_payload_json'], 'unknown'),
    'Prepared append-row correction must keep only declared columns'
);

$rejected = false;
try {
    RecordFormCorrectionService::prepare($schema, $values, [
        'correction_type' => 'amendment',
        'field_path' => 'cell:record_items:99:item',
        'corrected_value' => '伪造目标',
    ]);
} catch (InvalidArgumentException $exception) {
    $rejected = str_contains($exception->getMessage(), '不存在');
}
correction_target_assert($rejected, 'Unknown or forged target path must be rejected');

$mergedDecisions = RecordFormCorrectionService::mergeDecisionRows(
    [
        ['request_id' => 'structured-1', 'created' => '2026-07-26 10:00:00', 'is_structured' => true],
    ],
    [
        ['request_id' => 'structured-1', 'created' => '2026-07-26 10:00:00', 'is_structured' => false],
        ['request_id' => 'legacy-1', 'created' => '2026-07-25 10:00:00', 'is_structured' => false],
    ]
);
correction_target_assert(
    count($mergedDecisions) === 2
        && ($mergedDecisions[0]['request_id'] ?? '') === 'structured-1'
        && ($mergedDecisions[1]['request_id'] ?? '') === 'legacy-1',
    'Structured decisions must suppress their notification-shaped compatibility duplicate'
);

$projected = RecordFormCorrectionService::projectForDisplay($schema, $values, [
    [
        'target_kind' => 'table_cell',
        'field_path' => 'cell:record_items:0:result',
        'correction_type' => 'amendment',
        'type_label' => '修改内容（保留原值）',
        'original_content' => '已完成模拟流程',
        'corrected_content' => '已完成字段级复核',
        'correction_reason' => '修正处理结果',
        'registered_by' => 'SIM 编制人',
        'registered_at' => '2026-07-26 10:10:00',
        'approved_by' => 'SIM 批准人',
        'approved_at' => '2026-07-26 10:12:00',
        'request_short_id' => 'request1',
    ],
    [
        'target_kind' => 'append_row',
        'field_path' => 'append:record_items',
        'correction_type' => 'supplement',
        'type_label' => '补充内容',
        'corrected_content' => "事项：新增复核\n处理结果：已补充",
        'row_payload_json' => '{"item":"新增复核","result":"已补充"}',
        'correction_reason' => '补充遗漏明细',
        'registered_by' => 'SIM 编制人',
        'registered_at' => '2026-07-26 10:20:00',
        'approved_by' => 'SIM 批准人',
        'approved_at' => '2026-07-26 10:22:00',
        'request_short_id' => 'request2',
    ],
]);
$originalResultCell = $projected[1]['display_rows'][0]['cells'][1] ?? [];
$appendedRow = $projected[1]['display_rows'][1] ?? [];
correction_target_assert(
    ($projected[0]['field_path'] ?? '') === 'field:record_date',
    'Projected ordinary field must expose its stable correction path for inline annotation'
);
correction_target_assert(
    ($projected[1]['display_rows'][0]['cells'][0]['field_path'] ?? '') === 'cell:record_items:0:item',
    'Projected frozen table cell must expose its stable correction path for inline annotation'
);
correction_target_assert(
    !isset($appendedRow['cells'][0]['field_path']),
    'Projected appended correction row must not pretend to be part of the frozen table row indexes'
);
correction_target_assert(
    ($originalResultCell['original_value'] ?? '') === '已完成模拟流程'
        && ($originalResultCell['has_superseding_annotation'] ?? false) === true
        && ($originalResultCell['annotations'][0]['corrected_content'] ?? '') === '已完成字段级复核',
    'Projected table cell must retain the frozen original value and attach its amendment annotation'
);
correction_target_assert(
    ($appendedRow['is_appended'] ?? false) === true
        && ($appendedRow['cells'][0]['display_value'] ?? '') === '新增复核'
        && ($appendedRow['annotation']['approved_by'] ?? '') === 'SIM 批准人',
    'Projected repeatable table must append a labelled correction row without changing original rows'
);

echo "qms_record_correction_field_target_smoke passed\n";
