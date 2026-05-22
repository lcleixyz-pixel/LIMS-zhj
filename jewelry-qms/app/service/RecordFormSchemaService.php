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
        if (!array_is_list($schema)) {
            throw new InvalidArgumentException('字段配置根节点必须是字段数组');
        }

        $fields = [];
        foreach ($schema as $index => $field) {
            if (!is_array($field)) {
                throw new InvalidArgumentException('字段配置第' . ($index + 1) . '项必须是对象');
            }
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
                if (!is_array($value)) {
                    $errors[$key] = $field['label'] . '必须是明细数组';
                    continue;
                }

                $rows = array_values($value);
                foreach ($rows as $rowIndex => $row) {
                    if (!is_array($row)) {
                        $errors[$key . '.' . $rowIndex] = $field['label'] . '第' . ($rowIndex + 1) . '行必须是对象';
                        continue;
                    }

                    foreach ($field['columns'] as $column) {
                        $cellValue = $row[$column['key']] ?? '';
                        if (($column['required'] ?? false) && self::isBlank($cellValue)) {
                            $errors[$key . '.' . $rowIndex . '.' . $column['key']] =
                                $field['label'] . '第' . ($rowIndex + 1) . '行' . $column['label'] . '不能为空';
                            continue;
                        }

                        $typeError = self::validateFieldType($column, $cellValue);
                        if ($typeError !== null) {
                            $errors[$key . '.' . $rowIndex . '.' . $column['key']] =
                                $field['label'] . '第' . ($rowIndex + 1) . '行' . $typeError;
                        }
                    }
                }

                continue;
            }

            $typeError = self::validateFieldType($field, $value);
            if ($typeError !== null) {
                $errors[$key] = $typeError;
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
        if (preg_match('/\A[a-zA-Z][a-zA-Z0-9_]*\z/', $key) !== 1) {
            throw new InvalidArgumentException('字段 key 只能使用字母、数字和下划线，并以字母开头：' . $key);
        }
        if ($label === '') {
            throw new InvalidArgumentException('字段 label 不能为空');
        }
        if (!in_array($type, self::TYPES, true)) {
            throw new InvalidArgumentException('不支持的字段类型：' . $type);
        }

        $options = self::normalizeOptions($field['options'] ?? []);
        if ($type === 'select' && $options === []) {
            throw new InvalidArgumentException('字段 ' . $key . ' 的 select 类型必须配置 options');
        }

        $normalized = [
            'key' => $key,
            'label' => $label,
            'type' => $type,
            'required' => (bool)($field['required'] ?? false),
            'default' => $field['default'] ?? '',
            'options' => $options,
            'print_bind' => (string)($field['print_bind'] ?? $key),
            'validation' => $field['validation'] ?? [],
            'help_text' => (string)($field['help_text'] ?? ''),
        ];

        if ($type === 'repeatable_table') {
            if (isset($field['columns']) && !is_array($field['columns'])) {
                throw new InvalidArgumentException('字段 ' . $key . ' 的 columns 必须是数组');
            }

            $columns = [];
            foreach (($field['columns'] ?? []) as $index => $column) {
                if (!is_array($column)) {
                    throw new InvalidArgumentException('字段 ' . $key . ' 的第' . ($index + 1) . '列必须是对象');
                }
                $columns[] = self::normalizeField($column);
            }
            $normalized['columns'] = $columns;
        }

        return $normalized;
    }

    private static function normalizeOptions(mixed $options): array
    {
        if ($options === null || $options === '') {
            return [];
        }
        if (!is_array($options)) {
            throw new InvalidArgumentException('字段 options 必须是数组');
        }

        $normalized = [];
        foreach (array_values($options) as $option) {
            if (is_array($option) || is_object($option)) {
                throw new InvalidArgumentException('字段 options 只能包含标量值');
            }

            $value = trim((string)$option);
            if ($value !== '') {
                $normalized[] = $value;
            }
        }

        return array_values(array_unique($normalized));
    }

    private static function validateFieldType(array $field, mixed $value): ?string
    {
        if (self::isBlank($value)) {
            return null;
        }
        if (is_array($value)) {
            return $field['label'] . '必须是标量值';
        }

        $stringValue = trim((string)$value);
        return match ($field['type']) {
            'date' => self::isValidDate($stringValue) ? null : $field['label'] . '必须是有效日期',
            'number' => is_numeric($stringValue) ? null : $field['label'] . '必须是数字',
            'select' => in_array($stringValue, $field['options'] ?? [], true) ? null : $field['label'] . '不在可选范围内',
            'checkbox' => in_array($stringValue, ['0', '1'], true) ? null : $field['label'] . '必须是勾选值',
            default => null,
        };
    }

    private static function isValidDate(string $value): bool
    {
        if (preg_match('/\A\d{4}-\d{2}-\d{2}\z/', $value) !== 1) {
            return false;
        }

        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $date instanceof \DateTimeImmutable && $date->format('Y-m-d') === $value;
    }

    private static function isBlank(mixed $value): bool
    {
        if (is_array($value)) {
            return count($value) === 0;
        }

        return trim((string)$value) === '';
    }
}
