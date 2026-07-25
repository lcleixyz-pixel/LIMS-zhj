<?php
declare(strict_types=1);

namespace app\service;

use RuntimeException;

class RecordFormCurrentStateService
{
    /**
     * Apply approved field-addressable corrections to a copy of frozen values.
     * Legacy free-text corrections intentionally remain revision-history only.
     *
     * @return array<string,mixed>
     */
    public static function apply(array $schema, array $values, array $corrections): array
    {
        $current = $values;
        $fields = self::fields($schema);

        foreach ($corrections as $correction) {
            if (!is_array($correction)) {
                continue;
            }

            $kind = trim((string)($correction['target_kind'] ?? 'legacy_note'));
            $path = trim((string)($correction['field_path'] ?? ''));
            if ($kind === 'field_value') {
                $fieldKey = trim((string)($correction['field_key'] ?? ''));
                if ($fieldKey === '' && str_starts_with($path, 'field:')) {
                    $fieldKey = substr($path, strlen('field:'));
                }
                if ($fieldKey !== '' && isset($fields[$fieldKey]) && $fields[$fieldKey]['type'] !== 'repeatable_table') {
                    $current[$fieldKey] = (string)($correction['corrected_content'] ?? '');
                }
                continue;
            }

            if ($kind === 'table_cell') {
                [$fieldKey, $rowIndex, $columnKey] = self::cellTarget($correction, $path);
                if (
                    $fieldKey === ''
                    || $rowIndex < 0
                    || $columnKey === ''
                    || !isset($fields[$fieldKey])
                    || $fields[$fieldKey]['type'] !== 'repeatable_table'
                    || !isset($fields[$fieldKey]['columns'][$columnKey])
                    || !isset($current[$fieldKey][$rowIndex])
                    || !is_array($current[$fieldKey][$rowIndex])
                ) {
                    continue;
                }
                $current[$fieldKey][$rowIndex][$columnKey] = (string)($correction['corrected_content'] ?? '');
                continue;
            }

            if ($kind !== 'append_row') {
                continue;
            }

            $fieldKey = trim((string)($correction['field_key'] ?? ''));
            if ($fieldKey === '' && str_starts_with($path, 'append:')) {
                $fieldKey = substr($path, strlen('append:'));
            }
            if (
                $fieldKey === ''
                || !isset($fields[$fieldKey])
                || $fields[$fieldKey]['type'] !== 'repeatable_table'
            ) {
                continue;
            }

            $payload = json_decode((string)($correction['row_payload_json'] ?? ''), true);
            if (!is_array($payload)) {
                continue;
            }
            $row = [];
            foreach (array_keys($fields[$fieldKey]['columns']) as $columnKey) {
                $row[$columnKey] = $payload[$columnKey] ?? '';
            }
            if (!isset($current[$fieldKey]) || !is_array($current[$fieldKey])) {
                $current[$fieldKey] = [];
            }
            $current[$fieldKey][] = $row;
        }

        return $current;
    }

    public static function revisionNumber(int $correctionCount): string
    {
        return 'R' . max(0, $correctionCount);
    }

    /**
     * @return array{file_name:string,file_path:string,absolute_path:string}|null
     */
    public static function findLatest(string $recordId, int $correctionCount): ?array
    {
        $recordId = self::normalizeRecordId($recordId);
        if ($correctionCount < 1) {
            return null;
        }

        $relativeDir = 'uploads/record-form-current-pdf/' . $recordId;
        $absoluteDir = rtrim(public_path(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR
            . 'uploads' . DIRECTORY_SEPARATOR . 'record-form-current-pdf' . DIRECTORY_SEPARATOR
            . $recordId . DIRECTORY_SEPARATOR;
        $pattern = $absoluteDir . '*_current_' . self::revisionNumber($correctionCount) . '_*.pdf';
        $matches = glob($pattern) ?: [];
        if ($matches === []) {
            return null;
        }
        usort($matches, static fn (string $left, string $right): int =>
            ((int)filemtime($right)) <=> ((int)filemtime($left)));
        $absolutePath = $matches[0];

        return [
            'file_name' => basename($absolutePath),
            'file_path' => $relativeDir . '/' . basename($absolutePath),
            'absolute_path' => $absolutePath,
        ];
    }

    public static function decorateHtml(
        string $html,
        int $correctionCount,
        string $latestCorrectionAt,
        ?string $generatedAt = null
    ): string {
        $revision = htmlspecialchars(self::revisionNumber($correctionCount), ENT_QUOTES, 'UTF-8');
        $latest = htmlspecialchars(trim($latestCorrectionAt) !== '' ? $latestCorrectionAt : '未记录', ENT_QUOTES, 'UTF-8');
        $generated = htmlspecialchars($generatedAt ?? date('Y-m-d H:i:s'), ENT_QUOTES, 'UTF-8');
        $count = max(0, $correctionCount);
        $banner = '<section style="box-sizing:border-box;margin:8mm 10mm 4mm;padding:3mm 4mm;'
            . 'border:1.5px solid #2463a6;background:#eef6ff;color:#173f6b;font-family:sans-serif;'
            . 'font-size:11px;line-height:1.6;page-break-inside:avoid">'
            . '<strong style="font-size:14px">当前状态 PDF</strong>'
            . '<span style="margin-left:10px">更正版次 ' . $revision . '</span>'
            . '<span style="margin-left:10px">已包含 ' . $count . ' 条批准更正</span>'
            . '<br><span>最近更正：' . $latest . '</span>'
            . '<span style="margin-left:10px">生成时间：' . $generated . '</span>'
            . '<br><span>正文展示已批准更正后的最终值；原值与变更依据请查阅系统修订记录。</span>'
            . '</section>';

        $decorated = preg_replace('/<body([^>]*)>/i', '$0' . $banner, $html, 1, $replaced);
        if ($decorated === null) {
            throw new RuntimeException('当前状态 PDF 标识生成失败');
        }

        return $replaced > 0 ? $decorated : $banner . $html;
    }

    /**
     * @return array<string,array{type:string,columns:array<string,bool>}>
     */
    private static function fields(array $schema): array
    {
        $fields = [];
        foreach ($schema as $field) {
            if (!is_array($field)) {
                continue;
            }
            $key = trim((string)($field['key'] ?? ''));
            if ($key === '') {
                continue;
            }
            $columns = [];
            foreach ((array)($field['columns'] ?? []) as $column) {
                if (!is_array($column)) {
                    continue;
                }
                $columnKey = trim((string)($column['key'] ?? ''));
                if ($columnKey !== '') {
                    $columns[$columnKey] = true;
                }
            }
            $fields[$key] = [
                'type' => (string)($field['type'] ?? ''),
                'columns' => $columns,
            ];
        }

        return $fields;
    }

    /**
     * @return array{0:string,1:int,2:string}
     */
    private static function cellTarget(array $correction, string $path): array
    {
        $fieldKey = trim((string)($correction['field_key'] ?? ''));
        $rowIndex = isset($correction['row_index']) ? (int)$correction['row_index'] : -1;
        $columnKey = trim((string)($correction['column_key'] ?? ''));
        if (($fieldKey === '' || $rowIndex < 0 || $columnKey === '') && str_starts_with($path, 'cell:')) {
            $parts = explode(':', $path, 4);
            if (count($parts) === 4 && ctype_digit($parts[2])) {
                $fieldKey = $fieldKey !== '' ? $fieldKey : $parts[1];
                $rowIndex = $rowIndex >= 0 ? $rowIndex : (int)$parts[2];
                $columnKey = $columnKey !== '' ? $columnKey : $parts[3];
            }
        }

        return [$fieldKey, $rowIndex, $columnKey];
    }

    private static function normalizeRecordId(string $recordId): string
    {
        $recordId = trim($recordId);
        if ($recordId === '' || preg_match('/\A[a-zA-Z0-9_-]+\z/', $recordId) !== 1) {
            throw new RuntimeException('非法记录标识');
        }

        return $recordId;
    }
}
