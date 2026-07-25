<?php
declare(strict_types=1);

namespace app\service;

use InvalidArgumentException;

class RecordFormCorrectionService
{
    private const CORRECTION_TYPES = ['supplement', 'amendment', 'void_mark'];

    /**
     * Build an allow-list of correctable locations from the frozen template snapshot and record values.
     *
     * @return array<string,array<string,mixed>>
     */
    public static function targets(array $schema, array $values): array
    {
        $targets = [];
        foreach ($schema as $field) {
            $fieldKey = trim((string)($field['key'] ?? ''));
            $fieldLabel = trim((string)($field['label'] ?? $fieldKey));
            if ($fieldKey === '') {
                continue;
            }

            if (($field['type'] ?? '') !== 'repeatable_table') {
                $path = 'field:' . $fieldKey;
                $targets[$path] = [
                    'target_kind' => 'field_value',
                    'field_path' => $path,
                    'field_key' => $fieldKey,
                    'field_label' => $fieldLabel,
                    'label' => $fieldLabel,
                    'original_value' => self::displayValue($values[$fieldKey] ?? ''),
                    'input_type' => self::inputType($field),
                    'options' => array_values((array)($field['options'] ?? [])),
                ];
                continue;
            }

            $columns = self::columns($field);
            $rows = is_array($values[$fieldKey] ?? null) ? array_values($values[$fieldKey]) : [];
            foreach ($rows as $rowIndex => $row) {
                if (!is_array($row)) {
                    continue;
                }
                foreach ($columns as $column) {
                    $columnKey = (string)$column['key'];
                    $columnLabel = (string)$column['label'];
                    $path = 'cell:' . $fieldKey . ':' . $rowIndex . ':' . $columnKey;
                    $targets[$path] = [
                        'target_kind' => 'table_cell',
                        'field_path' => $path,
                        'field_key' => $fieldKey,
                        'field_label' => $fieldLabel,
                        'row_index' => $rowIndex,
                        'column_key' => $columnKey,
                        'column_label' => $columnLabel,
                        'label' => $fieldLabel . ' / 第' . ($rowIndex + 1) . '行 / ' . $columnLabel,
                        'original_value' => self::displayValue($row[$columnKey] ?? ''),
                        'input_type' => self::inputType($column),
                        'options' => array_values((array)($column['options'] ?? [])),
                    ];
                }
            }

            $path = 'append:' . $fieldKey;
            $targets[$path] = [
                'target_kind' => 'append_row',
                'field_path' => $path,
                'field_key' => $fieldKey,
                'field_label' => $fieldLabel,
                'label' => $fieldLabel . ' / 新增一行',
                'original_value' => '（新增行，无原值）',
                'columns' => $columns,
            ];
        }

        return $targets;
    }

    /**
     * Validate user input against the server-side target allow-list and return an immutable snapshot.
     *
     * @return array<string,mixed>
     */
    public static function prepare(array $schema, array $values, array $input): array
    {
        $fieldPath = trim((string)($input['field_path'] ?? ''));
        $targets = self::targets($schema, $values);
        if ($fieldPath === '' || !isset($targets[$fieldPath])) {
            throw new InvalidArgumentException('所选更正位置不存在，请刷新后重新选择。');
        }

        $type = trim((string)($input['correction_type'] ?? 'supplement'));
        if (!in_array($type, self::CORRECTION_TYPES, true)) {
            throw new InvalidArgumentException('请选择有效的更正类型。');
        }

        $target = $targets[$fieldPath];
        if (($target['target_kind'] ?? '') === 'append_row') {
            if ($type !== 'supplement') {
                throw new InvalidArgumentException('新增表格行只能使用“补充内容”。');
            }

            return self::prepareRow($target, $input);
        }

        $corrected = self::scalar($input['corrected_value'] ?? '');
        if ($type === 'void_mark' && trim($corrected) === '') {
            $corrected = '【作废标注】原值保留，不再作为有效值';
        }
        if (trim($corrected) === '') {
            throw new InvalidArgumentException('请填写拟更正或补充的字段值。');
        }

        $original = (string)($target['original_value'] ?? '');
        if ($type !== 'supplement' && trim($original) === '') {
            throw new InvalidArgumentException('当前字段没有原值，请改用“补充内容”。');
        }

        return [
            'correction_type' => $type,
            'target_kind' => (string)$target['target_kind'],
            'field_path' => $fieldPath,
            'field_key' => (string)($target['field_key'] ?? ''),
            'field_label' => (string)($target['label'] ?? ''),
            'row_index' => $target['row_index'] ?? null,
            'column_key' => (string)($target['column_key'] ?? ''),
            'column_label' => (string)($target['column_label'] ?? ''),
            'original_content' => $original,
            'corrected_content' => $corrected,
            'row_payload_json' => '',
        ];
    }

    /**
     * Merge durable structured decisions with legacy notification-shaped decisions.
     * A structured request also emits a notification, so the notification copy must not be rendered twice.
     *
     * @return list<array<string,mixed>>
     */
    public static function mergeDecisionRows(array $structured, array $legacy): array
    {
        $structuredIds = [];
        foreach ($structured as $row) {
            $requestId = trim((string)($row['request_id'] ?? ''));
            if ($requestId !== '') {
                $structuredIds[$requestId] = true;
            }
        }

        $legacy = array_values(array_filter($legacy, static function (array $row) use ($structuredIds): bool {
            $requestId = trim((string)($row['request_id'] ?? ''));

            return $requestId === '' || !isset($structuredIds[$requestId]);
        }));
        $rows = array_merge($structured, $legacy);
        usort($rows, static fn (array $left, array $right): int =>
            strcmp((string)($right['created'] ?? ''), (string)($left['created'] ?? '')));

        return $rows;
    }

    /**
     * @return array<string,mixed>
     */
    private static function prepareRow(array $target, array $input): array
    {
        $posted = is_array($input['row_values'] ?? null) ? $input['row_values'] : [];
        $row = [];
        $lines = [];
        $hasValue = false;
        foreach ((array)($target['columns'] ?? []) as $column) {
            $key = (string)($column['key'] ?? '');
            if ($key === '') {
                continue;
            }
            $value = self::scalar($posted[$key] ?? '');
            $row[$key] = $value;
            if (trim($value) !== '') {
                $hasValue = true;
            }
            $lines[] = (string)($column['label'] ?? $key) . '：' . ($value !== '' ? $value : '—');
        }
        if (!$hasValue) {
            throw new InvalidArgumentException('新增表格行至少需要填写一个字段。');
        }

        $encoded = json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($encoded === false) {
            throw new InvalidArgumentException('新增行内容编码失败，请检查填写内容。');
        }

        return [
            'correction_type' => 'supplement',
            'target_kind' => 'append_row',
            'field_path' => (string)$target['field_path'],
            'field_key' => (string)$target['field_key'],
            'field_label' => (string)$target['label'],
            'row_index' => null,
            'column_key' => '',
            'column_label' => '',
            'original_content' => '',
            'corrected_content' => implode("\n", $lines),
            'row_payload_json' => $encoded,
        ];
    }

    /**
     * @return list<array<string,mixed>>
     */
    private static function columns(array $field): array
    {
        $columns = [];
        foreach ((array)($field['columns'] ?? []) as $column) {
            if (!is_array($column)) {
                continue;
            }
            $key = trim((string)($column['key'] ?? ''));
            if ($key === '') {
                continue;
            }
            $columns[] = [
                'key' => $key,
                'label' => trim((string)($column['label'] ?? $key)),
                'type' => (string)($column['type'] ?? 'text'),
                'input_type' => self::inputType($column),
                'options' => array_values((array)($column['options'] ?? [])),
            ];
        }

        return $columns;
    }

    private static function inputType(array $field): string
    {
        return match ((string)($field['type'] ?? 'text')) {
            'date' => 'date',
            'number' => 'number',
            'textarea' => 'textarea',
            'select' => 'select',
            'checkbox' => 'checkbox',
            default => 'text',
        };
    }

    private static function displayValue(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? '是' : '否';
        }
        if (is_array($value)) {
            $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            return $encoded !== false ? $encoded : '';
        }

        return (string)$value;
    }

    private static function scalar(mixed $value): string
    {
        if (is_array($value) || is_object($value)) {
            return '';
        }

        return trim((string)$value);
    }
}
