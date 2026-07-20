<?php
declare(strict_types=1);

namespace app\service;

use RuntimeException;

final class QmsManualProcedureAlignmentReportService
{
    private const CSV_HEADERS = [
        '发现编号',
        '状态',
        '严重性',
        '规则',
        '手册章节',
        '程序编号',
        '手册定位',
        '程序定位',
        '期望',
        '观察',
        '证据',
        '修订方向',
        '追溯来源',
    ];

    public static function write(array $result, string $outputDir, string $prefix): array
    {
        $outputDir = rtrim($outputDir, '/\\');
        $prefix = trim($prefix);
        if ($outputDir === '' || $prefix === '') {
            throw new RuntimeException('报告输出目录和版本化前缀不能为空');
        }
        if (!is_dir($outputDir) && !mkdir($outputDir, 0775, true) && !is_dir($outputDir)) {
            throw new RuntimeException('无法创建报告目录：' . $outputDir);
        }
        $paths = [
            'json' => $outputDir . DIRECTORY_SEPARATOR . $prefix . '.json',
            'csv' => $outputDir . DIRECTORY_SEPARATOR . $prefix . '.csv',
            'markdown' => $outputDir . DIRECTORY_SEPARATOR . $prefix . '.md',
        ];
        foreach ($paths as $path) {
            if (file_exists($path)) {
                throw new RuntimeException('报告已存在，禁止覆盖：' . $path);
            }
        }

        $json = json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
        if (file_put_contents($paths['json'], $json . "\n", LOCK_EX) === false) {
            throw new RuntimeException('无法写入 JSON 报告：' . $paths['json']);
        }
        self::writeCsv($result, $paths['csv']);
        if (file_put_contents($paths['markdown'], self::markdown($result), LOCK_EX) === false) {
            throw new RuntimeException('无法写入 Markdown 报告：' . $paths['markdown']);
        }

        return $paths;
    }

    private static function writeCsv(array $result, string $path): void
    {
        $handle = fopen($path, 'xb');
        if ($handle === false) {
            throw new RuntimeException('无法创建 CSV 报告：' . $path);
        }
        try {
            fputcsv($handle, self::CSV_HEADERS, ',', '"', '\\');
            foreach ((array)($result['findings'] ?? []) as $finding) {
                fputcsv($handle, [
                    (string)$finding['finding_id'],
                    (string)$finding['status'],
                    (string)$finding['severity'],
                    (string)$finding['rule'],
                    (string)$finding['manual_section'],
                    (string)$finding['procedure_number'],
                    (string)$finding['manual_locator'],
                    (string)$finding['procedure_locator'],
                    self::jsonCell((array)$finding['expected']),
                    self::jsonCell((array)$finding['observed']),
                    (string)$finding['evidence_excerpt'],
                    (string)$finding['suggestion'],
                    (string)$finding['trace_source'],
                ], ',', '"', '\\');
            }
        } finally {
            fclose($handle);
        }
    }

    private static function markdown(array $result): string
    {
        $counts = (array)($result['counts'] ?? []);
        $lines = [
            '# 手册—程序一致性校验报告',
            '',
            '- 生成时间：' . (string)($result['generated_at'] ?? ''),
            '- 试点编号：`' . (string)($result['pilot_id'] ?? '') . '`',
            '- 性质：只读校验；本报告不修改手册、程序文件、追溯关系或数据库。',
            '',
            '## 输入版本',
            '',
            '| 类型 | 编号 | 版本 | SHA-256 | 路径 |',
            '|---|---|---|---|---|',
            '| 手册 | `' . self::cell((string)($result['manual']['doc_number'] ?? '')) . '` | '
                . self::cell((string)($result['manual']['version'] ?? '')) . ' | `'
                . self::cell((string)($result['manual']['sha256'] ?? '')) . '` | `'
                . self::cell((string)($result['manual']['path'] ?? '')) . '` |',
        ];
        foreach ((array)($result['procedure_inputs'] ?? []) as $procedure) {
            $lines[] = '| 程序 | `' . self::cell((string)$procedure['doc_number']) . '` | '
                . self::cell((string)$procedure['version']) . ' | `'
                . self::cell((string)$procedure['sha256']) . '` | `'
                . self::cell((string)$procedure['path']) . '` |';
        }
        $lines = array_merge($lines, [
            '',
            '## 汇总',
            '',
            '- 一致：' . (int)($counts['consistent'] ?? 0),
            '- 冲突：' . (int)($counts['conflict'] ?? 0),
            '- 缺失：' . (int)($counts['missing'] ?? 0),
            '- 人工复核：' . (int)($counts['review_required'] ?? 0),
            '- 不适用：' . (int)($counts['not_applicable'] ?? 0),
            '- 追溯缺口：' . count((array)($result['trace_gaps'] ?? [])),
            '- 阻断项：' . count((array)($result['blockers'] ?? [])),
            '',
            '## 阻断项',
            '',
        ]);
        $blockers = (array)($result['blockers'] ?? []);
        if ($blockers === []) {
            $lines[] = '- 无。';
        } else {
            foreach ($blockers as $blocker) {
                $lines[] = '- ' . (string)$blocker;
            }
        }
        $lines[] = '';
        $lines[] = '## 追溯缺口';
        $lines[] = '';
        $traceGaps = (array)($result['trace_gaps'] ?? []);
        if ($traceGaps === []) {
            $lines[] = '- 无。';
        } else {
            foreach ($traceGaps as $docNumber) {
                $lines[] = '- `' . (string)$docNumber . '`：仅按试点清单兜底扫描，需补正式追溯关系。';
            }
        }
        $lines = array_merge($lines, [
            '',
            '## 分级发现',
            '',
            '| 编号 | 状态 | 严重性 | 手册章节 | 程序 | 追溯来源 | 修订方向 |',
            '|---|---|---|---|---|---|---|',
        ]);
        foreach ((array)($result['findings'] ?? []) as $finding) {
            $lines[] = '| ' . self::cell((string)$finding['finding_id'])
                . ' | ' . self::statusLabel((string)$finding['status'])
                . ' | ' . self::cell((string)$finding['severity'])
                . ' | `' . self::cell((string)$finding['manual_section']) . '`'
                . ' | `' . self::cell((string)$finding['procedure_number']) . '`'
                . ' | ' . self::cell((string)$finding['trace_source'])
                . ' | ' . self::cell((string)$finding['suggestion']) . ' |';
        }
        $lines[] = '';
        $lines[] = '## 逐条证据';
        $lines[] = '';
        foreach ((array)($result['findings'] ?? []) as $finding) {
            $lines[] = '### ' . (string)$finding['finding_id'] . ' · ' . self::statusLabel((string)$finding['status']);
            $lines[] = '';
            $lines[] = '- 手册定位：`' . (string)$finding['manual_locator'] . '`';
            $lines[] = '- 程序定位：`' . (string)$finding['procedure_locator'] . '`';
            $lines[] = '- 期望：`' . self::jsonCell((array)$finding['expected']) . '`';
            $lines[] = '- 观察：`' . self::jsonCell((array)$finding['observed']) . '`';
            $lines[] = '- 证据：' . (trim((string)$finding['evidence_excerpt']) === '' ? '未找到明确承接文本。' : trim((string)$finding['evidence_excerpt']));
            $lines[] = '- 修订方向：' . (string)$finding['suggestion'];
            $lines[] = '';
        }
        $lines[] = '> `review_required` 表示证据、岗位别名或适用性尚不足，必须人工复核，不得按通过处理。';

        return implode("\n", $lines) . "\n";
    }

    private static function statusLabel(string $status): string
    {
        return [
            'consistent' => '一致',
            'conflict' => '冲突',
            'missing' => '缺失',
            'review_required' => '人工复核',
            'not_applicable' => '不适用',
        ][$status] ?? $status;
    }

    private static function jsonCell(array $value): string
    {
        return (string)json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    private static function cell(string $value): string
    {
        return str_replace(["|", "\n", "\r"], ['\\|', ' ', ' '], $value);
    }
}
