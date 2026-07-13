<?php
declare(strict_types=1);

namespace app\service;

use app\model\QmsClause;
use app\model\QmsClauseText;
use app\model\QmsSource;
use RuntimeException;
use think\facade\Db;

final class QmsClauseRemediationService
{
    private const SOURCE_CONFIG = [
        'GB/T 27025-2019' => [
            'file' => 'GBT 27025-2019《检测和校准实验室能力的通用要求》.md',
            'numbers' => ['4.1.1', '5.5', '6.3.2', '6.3.5', '7.2.1.6', '7.7.3', '7.8.2.2'],
        ],
        'CNAS-CL01-G001:2024' => [
            'file' => 'CNAS-CL01-G001：2024《检测和校准实验室能力认可准则的应用要求》.md',
            'numbers' => [
                '4.1.4', '5.1', '5.2', '5.5a)', '5.5c)', '6.2.2', '6.2.5', '6.3.1',
                '6.4.1a)', '6.4.3', '6.4.6', '6.4.7', '6.4.10', '6.6.1a)', '6.6.1c)',
                '6.6.2a)', '6.6.2b)', '7.1.7', '7.2.1.5', '7.3.1a)', '7.4.1', '7.4.2',
                '7.5.1', '7.5.2', '7.7.1', '7.8.1.1', '7.8.1.2', '7.8.7.1', '7.10.1',
                '7.10.3', '7.11.2', '8.1.1', '8.1.3', '8.4.2', '8.7.1', '8.8.2', '8.9.1',
            ],
            'inline_numbers' => ['6.6.1c)'],
        ],
        'CNAS-CL01-A015:2018' => [
            'file' => 'CNAS-CL01-A015：2018《检测和校准实验室能力认可准则在珠宝玉石、贵金属检测领域的应用说明》.md',
            'numbers' => [
                '5.3', '5.4', '6.2.1', '6.2.2', '6.2.5c)', '6.3.1', '6.3.3', '6.4.1',
                '6.4.5', '6.5.2', '7.2.1.3', '7.4.1', '7.4.4', '7.5.1', '7.7.1a)',
                '7.7.1j)', '7.7.2', '7.8.2.1',
            ],
            'inline_numbers' => ['7.4.4'],
        ],
    ];

    private const TITLE_OVERRIDES = [
        'GB/T 27025-2019' => [
            '4.1.1' => '公正实施实验室活动',
            '5.5' => '组织结构、职责与权限',
            '6.3.2' => '设施及环境条件要求文件化',
            '6.3.5' => '永久控制之外场所的设施环境控制',
            '7.2.1.6' => '方法开发策划与授权',
            '7.7.3' => '结果有效性监控数据分析',
            '7.8.2.2' => '报告信息责任与客户提供信息声明',
        ],
    ];

    private const EQUIVALENCE_NOTE = '定向文审 T1 补录确认（2026-07-13）：本依据适用；其规范性要求与 GB/T 27025-2019 等同采用，同一要求以 GB/T 27025-2019 条款集作为稳定编号和原文载体，不重复建立 CNAS-CL01 独立条款行。范围编号及 OCR 表现差异另留对账记录。';

    public static function buildRows(string $sourceDir): array
    {
        $sourceDir = rtrim($sourceDir, '/\\');
        if ($sourceDir === '' || !is_dir($sourceDir)) {
            throw new RuntimeException('Markdown 原文目录不存在：' . $sourceDir);
        }

        $rows = [];
        foreach (self::SOURCE_CONFIG as $sourceCode => $config) {
            $path = $sourceDir . DIRECTORY_SEPARATOR . (string)$config['file'];
            if (!is_file($path)) {
                throw new RuntimeException('缺少已核验 Markdown 原文：' . $path);
            }
            $markdown = file_get_contents($path);
            if ($markdown === false || trim($markdown) === '') {
                throw new RuntimeException('Markdown 原文为空：' . $path);
            }
            $markdown = str_replace(["\r\n", "\r"], "\n", $markdown);
            $locations = self::locateApprovedNumbers(
                $markdown,
                (array)$config['numbers'],
                (array)($config['inline_numbers'] ?? [])
            );
            foreach ((array)$config['numbers'] as $number) {
                $location = $locations[$number] ?? null;
                if (!is_array($location)) {
                    throw new RuntimeException($sourceCode . ' 未唯一定位要求原子：' . $number);
                }
                $end = self::findBlockEnd($markdown, (int)$location['offset'], $locations);
                $originalText = self::normalizeOriginalText(substr($markdown, (int)$location['offset'], $end - (int)$location['offset']), $number);
                if (!str_starts_with($originalText, $number . ' ')) {
                    throw new RuntimeException($sourceCode . ' 原文块起点异常：' . $number);
                }
                $rows[] = [
                    'source_code' => $sourceCode,
                    'clause_number' => $number,
                    'title' => self::titleFor($sourceCode, $number, $originalText),
                    'level' => self::clauseLevel($number),
                    'locator' => 'markdown:' . (string)$config['file'] . ':line-' . (int)$location['line'],
                    'original_text' => $originalText,
                    'text_hash' => hash('sha256', $originalText),
                    'extraction_method' => 'reviewed_markdown_requirement_atom',
                    'review_note' => '用户于 2026-07-13 确认按要求原子补录；已排除术语、章节标题和纯引用项。',
                ];
            }
        }

        if (count($rows) !== 62) {
            throw new RuntimeException('要求原子总数校验失败，预期 62，实际 ' . count($rows));
        }

        return $rows;
    }

    public static function buildPlan(string $sourceDir): array
    {
        $rows = self::buildRows($sourceDir);
        $sources = [];
        foreach (self::SOURCE_CONFIG as $sourceCode => $_config) {
            $source = QmsSource::where('source_code', $sourceCode)->where('soft_delete', 0)->find();
            if (!$source) {
                throw new RuntimeException('条款库未登记外部依据：' . $sourceCode);
            }
            $sources[$sourceCode] = $source;
        }
        $cl01 = QmsSource::where('source_code', 'CNAS-CL01:2018')->where('soft_delete', 0)->find();
        if (!$cl01) {
            throw new RuntimeException('条款库未登记外部依据：CNAS-CL01:2018');
        }

        $planned = [];
        $counts = ['insert' => 0, 'existing' => 0, 'conflict' => 0, 'total' => count($rows)];
        foreach ($rows as $row) {
            $source = $sources[(string)$row['source_code']];
            $clause = QmsClause::where('source_id', (string)$source->id)
                ->where('clause_number', (string)$row['clause_number'])
                ->where('soft_delete', 0)
                ->find();
            $status = 'insert';
            $reason = '当前条款库不存在该要求原子';
            if ($clause) {
                $text = QmsClauseText::where('clause_id', (string)$clause->id)->where('soft_delete', 0)->find();
                $storedHash = $text ? hash('sha256', trim((string)$text->original_text)) : '';
                if ($text && hash_equals((string)$row['text_hash'], $storedHash)) {
                    $status = 'existing';
                    $reason = '同编号原文哈希一致';
                } else {
                    $status = 'conflict';
                    $reason = $text ? '同编号原文哈希不一致' : '同编号条款缺少原文记录';
                }
            }
            $counts[$status]++;
            $planned[] = $row + [
                'source_id' => (string)$source->id,
                'status' => $status,
                'reason' => $reason,
            ];
        }

        return [
            'generated_at' => date('Y-m-d H:i:s'),
            'counts' => $counts,
            'equivalence' => [
                'source_code' => 'CNAS-CL01:2018',
                'source_id' => (string)$cl01->id,
                'status' => str_contains((string)$cl01->review_note, self::EQUIVALENCE_NOTE) ? 'existing' : 'append',
                'note' => self::EQUIVALENCE_NOTE,
            ],
            'rows' => $planned,
        ];
    }

    public static function apply(string $sourceDir): array
    {
        $plan = self::buildPlan($sourceDir);
        if ((int)$plan['counts']['conflict'] > 0) {
            throw new RuntimeException('预演存在冲突，禁止写库：' . (int)$plan['counts']['conflict'] . ' 条');
        }

        Db::transaction(function () use ($plan): void {
            foreach ((array)$plan['rows'] as $row) {
                if ((string)$row['status'] !== 'insert') {
                    continue;
                }
                $clause = new QmsClause();
                $clause->id = qms_uuid();
                $clause->save([
                    'source_id' => (string)$row['source_id'],
                    'parent_id' => null,
                    'clause_number' => (string)$row['clause_number'],
                    'title' => (string)$row['title'],
                    'level' => (int)$row['level'],
                    'page_number' => null,
                    'locator' => (string)$row['locator'],
                    'applicability' => 'applicable',
                    'review_status' => 'published',
                    'summary' => '经 T1 核验补录的要求原子；不含术语、目录标题或纯引用项。',
                    'publish' => 1,
                    'soft_delete' => 0,
                ]);

                $text = new QmsClauseText();
                $text->id = qms_uuid();
                $text->save([
                    'clause_id' => (string)$clause->id,
                    'source_id' => (string)$row['source_id'],
                    'clause_number' => (string)$row['clause_number'],
                    'original_text' => (string)$row['original_text'],
                    'locator' => (string)$row['locator'],
                    'page_number' => null,
                    'text_hash' => (string)$row['text_hash'],
                    'extraction_method' => (string)$row['extraction_method'],
                    'review_status' => 'published',
                    'review_note' => (string)$row['review_note'],
                    'publish' => 1,
                    'soft_delete' => 0,
                ]);
            }

            $equivalence = (array)$plan['equivalence'];
            if ((string)$equivalence['status'] === 'append') {
                $source = QmsSource::where('id', (string)$equivalence['source_id'])->where('soft_delete', 0)->find();
                if (!$source) {
                    throw new RuntimeException('CNAS-CL01 依据在写入事务中消失');
                }
                $old = trim((string)$source->review_note);
                $source->save(['review_note' => $old === '' ? (string)$equivalence['note'] : $old . "\n" . (string)$equivalence['note']]);
            }
        });

        return self::buildPlan($sourceDir);
    }

    public static function writeReports(array $plan, string $outputDir, string $prefix): array
    {
        $outputDir = rtrim($outputDir, '/\\');
        if ($outputDir === '') {
            throw new RuntimeException('报告输出目录不能为空');
        }
        if (!is_dir($outputDir) && !mkdir($outputDir, 0775, true) && !is_dir($outputDir)) {
            throw new RuntimeException('无法创建报告目录：' . $outputDir);
        }

        $jsonPath = $outputDir . DIRECTORY_SEPARATOR . $prefix . '.json';
        $csvPath = $outputDir . DIRECTORY_SEPARATOR . $prefix . '.csv';
        $mdPath = $outputDir . DIRECTORY_SEPARATOR . $prefix . '.md';
        file_put_contents($jsonPath, json_encode($plan, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . "\n");

        $csv = fopen($csvPath, 'wb');
        if ($csv === false) {
            throw new RuntimeException('无法写入 CSV：' . $csvPath);
        }
        fputcsv($csv, ['状态', '依据', '条款号', '标题', '定位', 'SHA-256', '处置说明']);
        foreach ((array)$plan['rows'] as $row) {
            fputcsv($csv, [(string)$row['status'], (string)$row['source_code'], (string)$row['clause_number'], (string)$row['title'], (string)$row['locator'], (string)$row['text_hash'], (string)$row['reason']]);
        }
        fclose($csv);

        $counts = (array)$plan['counts'];
        $lines = [
            '# 条款库要求原子补录' . ($prefix === '01-写库前预演' ? '写库前预演' : '写库后复核'),
            '',
            '- 生成时间：' . (string)$plan['generated_at'],
            '- 总要求原子：' . (int)$counts['total'],
            '- 待新增：' . (int)$counts['insert'],
            '- 已存在：' . (int)$counts['existing'],
            '- 冲突：' . (int)$counts['conflict'],
            '- CNAS-CL01 等同说明：' . (string)$plan['equivalence']['status'],
            '',
            '| 状态 | 依据 | 条款号 | 标题 | 定位 |',
            '|---|---|---|---|---|',
        ];
        foreach ((array)$plan['rows'] as $row) {
            $lines[] = '| ' . (string)$row['status'] . ' | ' . (string)$row['source_code'] . ' | ' . (string)$row['clause_number'] . ' | ' . str_replace('|', '\\|', (string)$row['title']) . ' | `' . (string)$row['locator'] . '` |';
        }
        file_put_contents($mdPath, implode("\n", $lines) . "\n");

        return ['json' => $jsonPath, 'csv' => $csvPath, 'markdown' => $mdPath];
    }

    private static function locateApprovedNumbers(string $markdown, array $numbers, array $inlineNumbers): array
    {
        $locations = [];
        foreach ($numbers as $number) {
            $quoted = preg_quote((string)$number, '/');
            $pattern = in_array($number, $inlineNumbers, true)
                ? '/(?<![0-9.])(' . $quoted . ')(?=\h|[\x{4e00}-\x{9fff}])/u'
                : '/^(?:#{1,6}\h*)?(' . $quoted . ')(?=\h|[\x{4e00}-\x{9fff}])/mu';
            $count = preg_match_all($pattern, $markdown, $matches, PREG_OFFSET_CAPTURE);
            if ($count !== 1) {
                throw new RuntimeException('要求编号定位次数不是 1：' . $number . '，实际 ' . (int)$count);
            }
            $offset = (int)$matches[1][0][1];
            $locations[(string)$number] = [
                'offset' => $offset,
                'line' => substr_count(substr($markdown, 0, $offset), "\n") + 1,
            ];
        }
        uasort($locations, static fn (array $a, array $b): int => $a['offset'] <=> $b['offset']);

        return $locations;
    }

    private static function findBlockEnd(string $markdown, int $start, array $approvedLocations): int
    {
        $end = strlen($markdown);
        foreach ($approvedLocations as $location) {
            $offset = (int)$location['offset'];
            if ($offset > $start) {
                $end = min($end, $offset);
            }
        }
        if (preg_match_all('/^(?:#{1,6}\h*)?[1-8](?:\.[0-9]+){0,4}(?:[a-z]\))?(?=\h|[\x{4e00}-\x{9fff}]|$)/mu', $markdown, $matches, PREG_OFFSET_CAPTURE)) {
            foreach ($matches[0] as $match) {
                $offset = (int)$match[1];
                if ($offset > $start) {
                    $end = min($end, $offset);
                }
            }
        }
        if (preg_match_all('/^#{1,6}\h+[^\n]+/mu', $markdown, $headings, PREG_OFFSET_CAPTURE)) {
            foreach ($headings[0] as $heading) {
                $offset = (int)$heading[1];
                if ($offset > $start) {
                    $end = min($end, $offset);
                }
            }
        }

        return $end;
    }

    private static function normalizeOriginalText(string $text, string $number): string
    {
        $text = preg_replace('/!\[[^\]]*\]\([^\)]*\)/u', ' ', $text) ?? $text;
        $text = preg_replace('/^#{1,6}\h*/mu', '', $text) ?? $text;
        $text = preg_replace('/\h+/u', ' ', $text) ?? $text;
        $text = preg_replace('/\s*\n\s*/u', ' ', $text) ?? $text;

        $text = trim($text);
        $text = preg_replace('/^' . preg_quote($number, '/') . '(?=\S)/u', $number . ' ', $text) ?? $text;

        return $text;
    }

    private static function titleFor(string $sourceCode, string $number, string $originalText): string
    {
        $override = self::TITLE_OVERRIDES[$sourceCode][$number] ?? '';
        if ($override !== '') {
            return $override;
        }
        $body = trim(substr($originalText, strlen($number)));
        $firstSentence = preg_split('/(?<=[。；;：:])/u', $body, 2)[0] ?? $body;
        $title = trim($firstSentence);
        if (mb_strlen($title) > 100) {
            $title = mb_substr($title, 0, 100) . '…';
        }

        return $title !== '' ? $title : '要求原子 ' . $number;
    }

    private static function clauseLevel(string $number): int
    {
        $normalized = preg_replace('/[a-z]\)$/u', '', $number) ?? $number;
        return substr_count($normalized, '.') + 1;
    }
}
