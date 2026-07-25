<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use app\service\RecordFormCurrentStateService;

function current_state_assert(bool $condition, string $message): void
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
            ['key' => 'remark', 'label' => '备注', 'type' => 'text'],
        ],
    ],
];
$originalValues = [
    'record_date' => '2026-07-25',
    'record_items' => [
        ['item' => '原事项', 'result' => '待处理', 'remark' => '原备注'],
    ],
];
$originalHash = hash('sha256', json_encode($originalValues, JSON_UNESCAPED_UNICODE));
$corrections = [
    [
        'target_kind' => 'field_value',
        'field_path' => 'field:record_date',
        'field_key' => 'record_date',
        'corrected_content' => '2026-07-26',
    ],
    [
        'target_kind' => 'table_cell',
        'field_path' => 'cell:record_items:0:remark',
        'field_key' => 'record_items',
        'row_index' => 0,
        'column_key' => 'remark',
        'corrected_content' => '第一次更正',
    ],
    [
        'target_kind' => 'legacy_note',
        'field_path' => '',
        'corrected_content' => '历史自由文本不得猜测写入字段',
    ],
    [
        'target_kind' => 'table_cell',
        'field_path' => 'cell:record_items:0:remark',
        'field_key' => 'record_items',
        'row_index' => 0,
        'column_key' => 'remark',
        'corrected_content' => '最终备注',
    ],
    [
        'target_kind' => 'append_row',
        'field_path' => 'append:record_items',
        'field_key' => 'record_items',
        'row_payload_json' => json_encode([
            'item' => '补充事项',
            'result' => '已完成',
            'remark' => '新增行',
            'undeclared' => '不得写入',
        ], JSON_UNESCAPED_UNICODE),
    ],
];

$currentValues = RecordFormCurrentStateService::apply($schema, $originalValues, $corrections);

current_state_assert(
    hash('sha256', json_encode($originalValues, JSON_UNESCAPED_UNICODE)) === $originalHash,
    'Current-state projection must not mutate the frozen original values'
);
current_state_assert(
    ($currentValues['record_date'] ?? null) === '2026-07-26',
    'Ordinary field correction must become the current value'
);
current_state_assert(
    ($currentValues['record_items'][0]['remark'] ?? null) === '最终备注',
    'Later approved correction on the same table cell must win'
);
current_state_assert(
    count($currentValues['record_items'] ?? []) === 2,
    'Approved append-row correction must add one complete row'
);
current_state_assert(
    ($currentValues['record_items'][1]['item'] ?? null) === '补充事项'
        && ($currentValues['record_items'][1]['result'] ?? null) === '已完成',
    'Appended row must retain declared table-column values'
);
current_state_assert(
    !array_key_exists('undeclared', $currentValues['record_items'][1] ?? []),
    'Appended row must discard values outside the frozen table schema'
);
current_state_assert(
    !in_array('历史自由文本不得猜测写入字段', $currentValues, true),
    'Legacy free-text correction must stay in revision history instead of guessing a field'
);
current_state_assert(
    RecordFormCurrentStateService::revisionNumber(count($corrections)) === 'R5',
    'Current-state PDF revision must follow the approved correction count'
);

$decoratedHtml = RecordFormCurrentStateService::decorateHtml(
    '<html><body><main>最终内容</main></body></html>',
    count($corrections),
    '2026-07-26 03:38:12'
);
current_state_assert(
    str_contains($decoratedHtml, '当前状态 PDF')
        && str_contains($decoratedHtml, '更正版次 R5')
        && str_contains($decoratedHtml, '已包含 5 条批准更正'),
    'Current-state PDF HTML must visibly identify its revision and included corrections'
);

echo "qms_record_current_state_smoke passed\n";
