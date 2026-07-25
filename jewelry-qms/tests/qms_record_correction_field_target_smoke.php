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

echo "qms_record_correction_field_target_smoke passed\n";
