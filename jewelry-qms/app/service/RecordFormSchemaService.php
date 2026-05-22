<?php
declare(strict_types=1);

namespace app\service;

use InvalidArgumentException;

class RecordFormSchemaService
{
    private const TYPES = [
        'text',
        'textarea',
        'date',
        'number',
        'select',
        'checkbox',
        'person',
        'department',
        'signature',
        'repeatable_table',
    ];

    public static function normalize(array $schema): array
    {
        $fields = [];
        foreach ($schema as $field) {
            $fields[] = self::normalizeField($field);
        }

        return $fields;
    }

    public static function decode(?string $json): array
    {
        if ($json === null || trim($json) === '') {
            return [];
        }

        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            throw new InvalidArgumentException('字段配置不是有效 JSON');
        }

        return self::normalize($decoded);
    }

    public static function encode(array $schema): string
    {
        $encoded = json_encode(self::normalize($schema), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        if ($encoded === false) {
            throw new InvalidArgumentException('字段配置编码失败：' . json_last_error_msg());
        }

        return $encoded;
    }

    public static function validateValues(array $schema, array $values): array
    {
        $errors = [];
        foreach ($schema as $field) {
            $key = $field['key'];
            $value = $values[$key] ?? $field['default'];

            if (($field['required'] ?? false) && self::isBlank($value)) {
                $errors[$key] = $field['label'] . '不能为空';
            }

            if ($field['type'] === 'repeatable_table') {
                $rows = is_array($value) ? array_values($value) : [];
                foreach ($rows as $rowIndex => $row) {
                    foreach ($field['columns'] as $column) {
                        $cellValue = is_array($row) ? ($row[$column['key']] ?? '') : '';
                        if (($column['required'] ?? false) && self::isBlank($cellValue)) {
                            $errors[$key . '.' . $rowIndex . '.' . $column['key']] =
                                $field['label'] . '第' . ($rowIndex + 1) . '行' . $column['label'] . '不能为空';
                        }
                    }
                }
            }
        }

        return $errors;
    }

    private static function normalizeField(array $field): array
    {
        $key = trim((string)($field['key'] ?? ''));
        $label = trim((string)($field['label'] ?? ''));
        $type = trim((string)($field['type'] ?? 'text'));

        if ($key === '') {
            throw new InvalidArgumentException('字段 key 不能为空');
        }
        if ($label === '') {
            throw new InvalidArgumentException('字段 label 不能为空');
        }
        if (!in_array($type, self::TYPES, true)) {
            throw new InvalidArgumentException('不支持的字段类型：' . $type);
        }

        $normalized = [
            'key' => $key,
            'label' => $label,
            'type' => $type,
            'required' => (bool)($field['required'] ?? false),
            'default' => $field['default'] ?? '',
            'options' => array_values($field['options'] ?? []),
            'print_bind' => (string)($field['print_bind'] ?? $key),
            'validation' => $field['validation'] ?? [],
            'help_text' => (string)($field['help_text'] ?? ''),
        ];

        if ($type === 'repeatable_table') {
            $columns = [];
            foreach (($field['columns'] ?? []) as $column) {
                $columns[] = self::normalizeField($column);
            }
            $normalized['columns'] = $columns;
        }

        return $normalized;
    }

    private static function isBlank(mixed $value): bool
    {
        if (is_array($value)) {
            return count($value) === 0;
        }

        return trim((string)$value) === '';
    }
}
