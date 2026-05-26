<?php
declare(strict_types=1);

namespace app\service;

use DOMDocument;
use DOMElement;
use DOMXPath;
use RuntimeException;
use ZipArchive;

class QmsPlanningImportService
{
    private const SOURCE_FILES = [
        [
            'source_code' => 'CNAS-CL01:2018',
            'name' => '检测和校准实验室能力认可准则',
            'version' => '2018（2019-02-20第一次修订）',
            'effective_date' => '2018-09-01',
            'relative_path' => '参考/2025年最新版CMA和CNAS质量体系/04-CNAS-CL01 检测和校准实验室能力认可准则 2018 年 09 月 01 日实施.pdf',
            'source_type' => 'external_standard',
        ],
        [
            'source_code' => 'CNAS-CL01-G001:2024',
            'name' => '检测和校准实验室能力认可准则的应用要求',
            'version' => '2024',
            'effective_date' => '2024-07-01',
            'relative_path' => '参考/2025年最新版CMA和CNAS质量体系/03+-CNAS-CL01-G001：2024《检测和校准实验室能力认可准则的应用要求》.pdf',
            'source_type' => 'external_guidance',
        ],
        [
            'source_code' => 'GB/T 27025-2019',
            'name' => '检测和校准实验室能力的通用要求',
            'version' => '2019',
            'effective_date' => '2020-07-01',
            'relative_path' => '参考/2025年最新版CMA和CNAS质量体系/05-GBT 27025-2019 检测和校准实验室能力的通用要求.pdf',
            'source_type' => 'external_standard',
        ],
        [
            'source_code' => '市场监管总局公告2023年第21号',
            'name' => '检验检测机构资质认定评审准则',
            'version' => '2023年第21号公告',
            'effective_date' => '2023-12-01',
            'relative_path' => '参考/2025年最新版CMA和CNAS质量体系/06-新检验检测机构资质认定评审准则2023带附件.docx',
            'source_type' => 'external_regulation',
        ],
    ];

    private const CURRENT_MANUAL_PATH = '现用文件/质量手册（第四版）.docx';
    private const REFERENCE_2025_MANUAL_PATH = '参考/2025年最新版CMA和CNAS质量体系/01-2025年质量手册（CMA和CNAS）(1).docx';
    private const CURRENT_PROCEDURE_DIRS = [
        '现用文件/程序文件/程序文件2022',
        '现用文件/程序文件/程序文件2018',
    ];

    public static function officialSourceManifest(): array
    {
        $items = [];
        foreach (self::SOURCE_FILES as $source) {
            $absolutePath = self::workspacePath($source['relative_path']);
            $items[] = $source + [
                'file_name' => basename($source['relative_path']),
                'file_type' => strtolower((string)pathinfo($source['relative_path'], PATHINFO_EXTENSION)),
                'absolute_path' => $absolutePath,
                'status' => 'published',
                'review_status' => 'published',
            ];
        }

        return $items;
    }

    public static function normalizeResponsibilitySymbol(string $symbol, string $sourceStyle = 'current_manual'): ?string
    {
        $symbol = trim($symbol);
        if ($symbol === '') {
            return null;
        }

        $maps = [
            'current_manual' => [
                '★' => 'decision_owner',
                '●' => 'organizer',
                '○' => 'participant',
            ],
            'reference_manual' => [
                '●' => 'decision_owner',
                '■' => 'organizer',
                '▲' => 'participant',
            ],
        ];

        return $maps[$sourceStyle][$symbol] ?? null;
    }

    public static function parseSourceFilename(string $fileName): array
    {
        $baseName = pathinfo($fileName, PATHINFO_FILENAME);
        $normalized = str_replace(['：', '（', '）', '《', '》', '+'], [':', '(', ')', '', '', ''], $baseName);
        $normalized = preg_replace('/^\s*\d+\s*[-_、.]*/u', '', (string)$normalized);
        $normalized = preg_replace('/\s+/u', ' ', trim((string)$normalized));

        $sourceCode = '';
        $name = '';
        $version = '';
        $sourceType = 'external_standard';

        if (preg_match('/CNAS[-\s]*CL01[-\s]*G001\s*:?\s*(20\d{2})/iu', $normalized, $match)) {
            $version = $match[1];
            $sourceCode = 'CNAS-CL01-G001:' . $version;
            $name = self::cleanSourceTitle((string)preg_replace('/CNAS[-\s]*CL01[-\s]*G001\s*:?\s*20\d{2}/iu', '', $normalized));
            $sourceType = 'external_guidance';
        } elseif (preg_match('/CNAS[-\s]*CL01[-_\s]*(A\d{3})\s*[:：_\-\s]?\s*(20\d{2})/iu', $normalized, $match)) {
            $applicationCode = strtoupper($match[1]);
            $version = $match[2];
            $sourceCode = 'CNAS-CL01-' . $applicationCode . ':' . $version;
            $name = self::cleanSourceTitle((string)preg_replace('/CNAS[-\s]*CL01[-_\s]*A\d{3}\s*[:：_\-\s]?\s*20\d{2}/iu', '', $normalized));
            $sourceType = 'external_guidance';
        } elseif (preg_match('/CNAS[-\s]*CL01(?![-\s]*G001).*?(20\d{2})?/iu', $normalized, $match)) {
            $version = $match[1] ?? self::firstYear($normalized);
            $version = $version !== '' ? $version : '';
            $sourceCode = $version !== '' ? 'CNAS-CL01:' . $version : 'CNAS-CL01';
            $name = self::cleanSourceTitle((string)preg_replace('/CNAS[-\s]*CL01/iu', '', $normalized));
        } elseif (preg_match('/GB\s*[\/-]?\s*T?\s*27025\s*[- ]\s*(20\d{2})/iu', $normalized, $match)) {
            $version = $match[1];
            $sourceCode = 'GB/T 27025-' . $version;
            $name = self::cleanSourceTitle((string)preg_replace('/GB\s*[\/-]?\s*T?\s*27025\s*[- ]\s*20\d{2}/iu', '', $normalized));
        } elseif (str_contains($normalized, '检验检测机构资质认定评审准则') && str_contains($normalized, '2023')) {
            $sourceCode = '市场监管总局公告2023年第21号';
            $name = '检验检测机构资质认定评审准则';
            $version = '2023年第21号公告';
            $sourceType = 'external_regulation';
        } else {
            $name = self::cleanSourceTitle($normalized);
            $version = self::firstYear($normalized);
        }

        return [
            'source_code' => $sourceCode,
            'name' => $name,
            'version' => $version,
            'source_type' => $sourceType,
        ];
    }

    public static function extractCurrentManualBaseline(?string $manualPath = null): array
    {
        $path = $manualPath ?: self::workspacePath(self::CURRENT_MANUAL_PATH);
        $tables = self::extractDocxTables($path);
        $responsibilityTable = self::findTable($tables, ['岗位要素', '实验室主任', '质量负责人']);
        $mappingTable = self::findTable($tables, ['《质量手册》章节', 'CNAS-CL01']);
        $manualMappings = self::manualMappingsFromTable($mappingTable);

        return [
            'manual_path' => $path,
            'positions' => self::positionsFromResponsibilityTable($responsibilityTable),
            'responsibility_matrix' => self::matrixFromResponsibilityTable($responsibilityTable),
            'manual_clause_mappings' => $manualMappings,
            'requirement_elements' => self::requirementElementsFromManualMappings($manualMappings),
            'element_clause_mappings' => self::elementClauseMappingsFromManualMappings($manualMappings),
        ];
    }

    public static function extractReferenceManualSupplements(?string $manualPath = null): array
    {
        $path = $manualPath ?: self::workspacePath(self::REFERENCE_2025_MANUAL_PATH);
        if (!is_file($path)) {
            return [
                'manual_path' => $path,
                'manual_mappings' => [],
                'procedure_mappings' => [],
            ];
        }

        $tables = self::extractDocxTables($path);

        return [
            'manual_path' => $path,
            'manual_mappings' => self::referenceManualMappingsFromTables($tables),
            'procedure_mappings' => self::referenceProcedureMappingsFromTables($tables),
        ];
    }

    public static function buildInternalDocumentBaselines(): array
    {
        $documents = [[
            'document_level' => 1,
            'doc_number' => 'QM-04',
            'title' => '质量手册（第四版）',
            'version' => '第四版',
            'version_year' => '',
            'file_path' => self::CURRENT_MANUAL_PATH,
            'file_name' => basename(self::CURRENT_MANUAL_PATH),
            'file_type' => 'docx',
            'match_confidence' => is_file(self::workspacePath(self::CURRENT_MANUAL_PATH)) ? 'high' : 'missing_file',
            'source_note' => '现用质量手册，直接作为体系要素和手册章节的内部文件基线。',
        ]];

        return array_values(array_merge($documents, self::currentProcedureDocumentBaselines()));
    }

    public static function trainingTraceabilitySample(): array
    {
        return [
            'element_code' => '6.2',
            'element_name' => '人员',
            'clause_number' => '6.2',
            'clause_title' => '人员',
            'manual_section' => [
                'section_number' => '6.2',
                'title' => '人员',
                'source' => '现用文件/质量手册（第四版）.docx',
            ],
            'procedure' => [
                'document_level' => 2,
                'title' => '人员培训程序',
                'match_mode' => 'title_keyword',
            ],
            'record_forms' => [
                ['doc_number' => 'XZTC/BG-01-01', 'name' => '年度人员培训计划表'],
                ['doc_number' => 'XZTC/BG-01-02', 'name' => '人员培训记录表'],
                ['doc_number' => 'XZTC/BG-01-08', 'name' => '人员能力确认表'],
                ['doc_number' => 'XZTC/BG-01-09', 'name' => '人员培训评价表'],
            ],
            'business_modules' => [
                ['module_key' => 'training_plans', 'name' => '培训计划'],
                ['module_key' => 'trainings', 'name' => '培训活动'],
                ['module_key' => 'training_records', 'name' => '培训记录明细'],
                ['module_key' => 'competency_records', 'name' => '能力确认记录'],
            ],
        ];
    }

    public static function buildExternalClauseCandidates(): array
    {
        $candidates = [];
        foreach (self::officialSourceManifest() as $source) {
            if (!is_file($source['absolute_path'])) {
                continue;
            }

            $text = $source['file_type'] === 'pdf'
                ? self::extractPdfText($source['absolute_path'])
                : self::extractDocxPlainText($source['absolute_path']);

            $rows = self::clauseRowsFromText($text);
            if ($rows === [] && $source['source_code'] === 'GB/T 27025-2019') {
                $rows = self::gbt27025FallbackRows();
            }
            $rows = self::sortClauseRows($rows);

            foreach ($rows as $row) {
                $candidates[] = [
                    'candidate_type' => 'clause',
                    'source_code' => $source['source_code'],
                    'payload' => [
                        'source_code' => $source['source_code'],
                        'clause_number' => $row['clause_number'],
                        'title' => $row['title'],
                        'raw_heading' => $row['raw_heading'] ?? '',
                        'title_source' => $row['title_source'] ?? 'original_heading',
                        'level' => self::clauseLevel((string)$row['clause_number']),
                        'locator' => $row['locator'],
                        'sort_key' => self::clauseSortToken((string)$row['clause_number']),
                        'original_text' => $row['original_text'] ?? $row['clause_number'] . ' ' . $row['title'],
                        'review_status' => 'pending_review',
                    ],
                ];
            }
        }

        return $candidates;
    }

    public static function buildRegisteredSourceClauseCandidates(array $source): array
    {
        $path = self::resolveRegisteredSourcePath((string)($source['attachment_file_path'] ?? ''));
        if ($path === null || !is_file($path)) {
            return [];
        }

        $sourceCode = (string)($source['source_code'] ?? '');
        $fileType = strtolower((string)pathinfo((string)($source['attachment_file_name'] ?? $path), PATHINFO_EXTENSION));
        $text = $fileType === 'pdf'
            ? self::extractPdfText($path)
            : self::extractDocxPlainText($path);

        $rows = self::clauseRowsFromText($text);
        if ($rows === [] && $sourceCode === 'GB/T 27025-2019') {
            $rows = self::gbt27025FallbackRows();
        }
        $rows = self::applyModelSummariesToExtractedRows(self::sortClauseRows($rows), $sourceCode);

        return self::clauseCandidatesFromRows($rows, $source, 'registered_source_text');
    }

    public static function applyPublishedReviewTitleBaseline(array $candidates, array $publishedTitles, string $baselineSourceCode = 'CNAS-CL01-A015:2018'): array
    {
        if ($publishedTitles === []) {
            return $candidates;
        }

        foreach ($candidates as &$candidate) {
            if (($candidate['candidate_type'] ?? '') !== 'clause') {
                continue;
            }
            $payload = $candidate['payload'] ?? [];
            if ((string)($payload['source_code'] ?? '') !== $baselineSourceCode) {
                continue;
            }
            $number = (string)($payload['clause_number'] ?? '');
            $publishedTitle = self::cleanReviewTitle((string)($publishedTitles[$number] ?? ''));
            if ($publishedTitle === '') {
                continue;
            }

            $payload['title'] = $publishedTitle;
            $payload['title_source'] = 'published_review_baseline';
            $qualityCheck = (array)($payload['quality_check'] ?? []);
            $qualityCheck['title_source'] = 'published_review_baseline';
            $qualityCheck['published_review_baseline'] = true;
            $payload['quality_check'] = $qualityCheck;
            $note = trim((string)($payload['manual_review_note'] ?? ''));
            $baselineNote = '标题已按同一依据的已发布复核条款同步；原文仍来自本次附件抽取。';
            $payload['manual_review_note'] = $note !== '' ? $baselineNote . ' ' . $note : $baselineNote;
            $candidate['payload'] = $payload;
        }
        unset($candidate);

        return $candidates;
    }

    public static function parseCma2023MarkdownClauses(string $markdown, string $interpretationText = ''): array
    {
        $notes = self::cma2023InterpretationNotes($interpretationText);
        $rows = [];
        $current = null;
        $currentChapter = '';
        $currentArticle = '';
        $currentAttachment = '';

        $flush = static function () use (&$rows, &$current, $notes): void {
            if ($current === null) {
                return;
            }
            $originalText = self::normalizeOriginalClauseText($current['lines']);
            if ($originalText === '') {
                $current = null;
                return;
            }

            $number = (string)$current['clause_number'];
            $title = self::cma2023ReviewTitle($number, (string)$current['title']);
            $originalText = self::sanitizeUtf8($originalText);
            $rows[] = [
                'clause_number' => self::sanitizeUtf8($number),
                'title' => self::sanitizeUtf8($title !== '' ? $title : (string)$current['title']),
                'raw_heading' => self::sanitizeUtf8((string)$current['title']),
                'title_source' => 'cma2023_markdown',
                'level' => (int)$current['level'],
                'parent_number' => self::sanitizeUtf8((string)($current['parent_number'] ?? '')),
                'locator' => self::sanitizeUtf8('md-line:' . ((int)$current['line_number'] + 1)),
                'original_text' => $originalText,
                'review_note' => self::sanitizeUtf8(trim((string)($notes[$number] ?? ''))),
                'extraction_method' => 'cma2023_markdown',
                'review_status' => 'published',
            ];
            $current = null;
        };

        foreach (preg_split('/\R/u', $markdown) ?: [] as $lineNumber => $line) {
            $line = trim(self::normalizeCmaText((string)$line));
            if ($line === '') {
                continue;
            }

            $isHeading = str_starts_with($line, '#');
            $heading = trim(preg_replace('/^#+\s*/u', '', $line) ?? $line);
            if ($heading === '检验检测机构资质认定评审准则' && $isHeading) {
                $flush();
                $currentChapter = '';
                $currentArticle = '';
                $currentAttachment = '';
                continue;
            }

            if ($isHeading && $heading === '市场监管总局关于发布《检验检测机构资质认定评审准则》的公告') {
                $flush();
                $current = [
                    'clause_number' => '公告',
                    'title' => '发布公告',
                    'level' => 1,
                    'parent_number' => '',
                    'line_number' => $lineNumber,
                    'lines' => [$heading],
                ];
                continue;
            }

            if (preg_match('/^附件\s*([0-9]+)$/u', $heading, $match) === 1 && $isHeading) {
                $flush();
                $currentAttachment = '附件' . $match[1];
                $currentChapter = '';
                $currentArticle = '';
                $current = [
                    'clause_number' => $currentAttachment,
                    'title' => $currentAttachment,
                    'level' => 1,
                    'parent_number' => '',
                    'line_number' => $lineNumber,
                    'lines' => [$heading],
                ];
                continue;
            }

            if ($currentAttachment !== '' && $current !== null && (string)$current['clause_number'] === $currentAttachment && (string)$current['title'] === $currentAttachment && $isHeading) {
                $current['title'] = $heading;
                $current['lines'][] = $heading;
                continue;
            }

            if (preg_match('/^(第[一二三四五六七八九十百零〇]+章)\s*(.+)$/u', $heading, $match) === 1) {
                $flush();
                $currentChapter = $match[1];
                $currentArticle = '';
                $currentAttachment = '';
                $current = [
                    'clause_number' => $currentChapter,
                    'title' => trim((string)$match[2]),
                    'level' => 1,
                    'parent_number' => '',
                    'line_number' => $lineNumber,
                    'lines' => [$heading],
                ];
                continue;
            }

            if (preg_match('/^(第[一二三四五六七八九十百零〇]+条)\s*(.*)$/u', $heading, $match) === 1) {
                $flush();
                $currentArticle = $match[1];
                $currentAttachment = '';
                $current = [
                    'clause_number' => $currentArticle,
                    'title' => trim((string)$match[2]),
                    'level' => 2,
                    'parent_number' => $currentChapter,
                    'line_number' => $lineNumber,
                    'lines' => [$heading],
                ];
                continue;
            }

            if ($currentArticle !== '' && preg_match('/^（([一二三四五六七八九十百零〇]+)）\s*(.+)$/u', $heading, $match) === 1) {
                $flush();
                $number = $currentArticle . '（' . $match[1] . '）';
                $current = [
                    'clause_number' => $number,
                    'title' => trim((string)$match[2]),
                    'level' => 3,
                    'parent_number' => $currentArticle,
                    'line_number' => $lineNumber,
                    'lines' => [$heading],
                ];
                continue;
            }

            if ($currentAttachment !== '' && preg_match('/^([0-9]+(?:\.[0-9]+)*)\s+(.+)$/u', $heading, $match) === 1 && ($isHeading || str_contains((string)$match[1], '.'))) {
                $flush();
                $number = $currentAttachment . '.' . $match[1];
                $current = [
                    'clause_number' => $number,
                    'title' => trim((string)$match[2]),
                    'level' => substr_count((string)$match[1], '.') + 2,
                    'parent_number' => self::parentNumberForAttachmentClause($number),
                    'line_number' => $lineNumber,
                    'lines' => [$heading],
                ];
                continue;
            }

            if ($current !== null) {
                $current['lines'][] = $heading;
            }
        }
        $flush();

        return $rows;
    }

    private static function cma2023InterpretationNotes(string $text): array
    {
        $notes = [];
        $currentArticle = '';
        $currentNumber = '';
        $collecting = false;
        $buffer = [];
        $flush = static function () use (&$notes, &$currentNumber, &$buffer): void {
            if ($currentNumber !== '' && $buffer !== []) {
                $notes[$currentNumber] = self::normalizeOriginalClauseText($buffer);
            }
            $buffer = [];
        };

        foreach (preg_split('/\R/u', $text) ?: [] as $line) {
            $line = trim(self::normalizeCmaText((string)$line));
            if ($line === '') {
                continue;
            }
            if (preg_match('/^(第[一二三四五六七八九十百零〇]+章)\b/u', $line) === 1) {
                $flush();
                $currentNumber = '';
                $collecting = false;
                continue;
            }
            if (preg_match('/^(第[一二三四五六七八九十百零〇]+条)\s*/u', $line, $match) === 1) {
                $flush();
                $currentArticle = $match[1];
                $currentNumber = $currentArticle;
                $collecting = false;
                continue;
            }
            if ($currentArticle !== '' && preg_match('/^（([一二三四五六七八九十百零〇]+)）\s*/u', $line, $match) === 1) {
                $flush();
                $currentNumber = $currentArticle . '（' . $match[1] . '）';
                $collecting = false;
                continue;
            }

            if (str_contains($line, '【条文释义】')) {
                $collecting = true;
                $afterMarker = trim((string)preg_replace('/^.*?【条文释义】/u', '', $line));
                if ($afterMarker !== '') {
                    $buffer[] = $afterMarker;
                }
                continue;
            }
            if ($collecting && $currentNumber !== '') {
                $buffer[] = $line;
            }
        }
        $flush();

        return $notes;
    }

    private static function normalizeCmaText(string $text): string
    {
        $text = self::sanitizeUtf8($text);
        $text = strtr($text, [
            '⼀' => '一',
            '⼆' => '二',
            '⼋' => '八',
            '⼗' => '十',
            '⼯' => '工',
            '⽅' => '方',
        ]);

        return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    }

    private static function sanitizeUtf8(string $text): string
    {
        if (mb_check_encoding($text, 'UTF-8')) {
            return $text;
        }
        $cleaned = @iconv('UTF-8', 'UTF-8//IGNORE', $text);
        if ($cleaned !== false && mb_check_encoding($cleaned, 'UTF-8')) {
            return $cleaned;
        }

        return htmlspecialchars_decode(htmlspecialchars($text, ENT_NOQUOTES | ENT_SUBSTITUTE, 'UTF-8'), ENT_NOQUOTES);
    }

    private static function parentNumberForAttachmentClause(string $number): string
    {
        if (!str_contains($number, '.')) {
            return '';
        }
        $parent = preg_replace('/\.[^.]+$/u', '', $number) ?? '';

        return $parent !== $number ? $parent : '';
    }

    private static function cma2023ReviewTitle(string $number, string $fallback): string
    {
        $titles = [
            '公告' => '发布公告',
            '第一条' => '制定目的和依据',
            '第二条' => '适用范围',
            '第三条' => '术语和定义',
            '第四条' => '补充要求适用',
            '第五条' => '告知承诺现场核查',
            '第六条' => '技术评审原则',
            '第七条' => '评审内容',
            '第八条' => '机构法律地位和责任',
            '第八条（一）' => '法律地位和责任',
            '第八条（二）' => '诚信守法自我承诺',
            '第八条（三）' => '公正性和独立性',
            '第八条（四）' => '保密义务和措施',
            '第九条' => '人员',
            '第九条（一）' => '劳动关系和人员管理',
            '第九条（二）' => '人员能力要求',
            '第九条（三）' => '授权签字人要求',
            '第十条' => '场所环境',
            '第十条（一）' => '检验检测场所',
            '第十条（二）' => '环境条件要求',
            '第十一条' => '设备设施',
            '第十一条（一）' => '设备设施配置',
            '第十一条（二）' => '设备检定校准和溯源',
            '第十一条（三）' => '标准物质溯源',
            '第十二条' => '管理体系',
            '第十二条（一）' => '管理体系文件',
            '第十二条（二）' => '合同评审',
            '第十二条（三）' => '服务和供应品采购',
            '第十二条（四）' => '方法验证和确认',
            '第十二条（五）' => '测量不确定度报告',
            '第十二条（六）' => '结果报告',
            '第十二条（七）' => '记录管理',
            '第十二条（八）' => '信息系统管理',
            '第十二条（九）' => '结果质量控制',
            '第十三条' => '特殊要求',
            '第十四条' => '评审方式',
            '第十五条' => '现场评审',
            '第十六条' => '书面审查',
            '第十七条' => '远程评审',
            '第十八条' => '告知承诺核查',
            '第十九条' => '告知承诺现场核查结论',
            '第二十条' => '评审违法违规处理',
            '第二十一条' => '施行日期和废止文件',
            '附件1.4.4.9' => '检验检测能力的确定',
            '附件1.4.4.11' => '评审组内部会',
            '附件1.4.4.12' => '与检验检测机构的沟通',
            '附件3.4.4.9' => '检验检测能力的确定',
            '附件3.4.4.11' => '评审组内部会',
            '附件3.4.4.12' => '与检验检测机构的沟通',
        ];
        if (isset($titles[$number])) {
            return $titles[$number];
        }

        $fallback = self::cleanReviewTitle($fallback);
        $fallback = preg_replace('/[。；;].*$/u', '', $fallback) ?? $fallback;

        return trim($fallback);
    }

    private static function applyModelSummariesToExtractedRows(array $rows, string $sourceCode): array
    {
        $modelRows = self::modelSeededClauseRows($sourceCode);
        if ($modelRows === []) {
            return $rows;
        }

        $modelTitles = [];
        foreach ($modelRows as $row) {
            $modelTitles[(string)$row['clause_number']] = (string)$row['title'];
        }

        foreach ($rows as &$row) {
            $number = (string)($row['clause_number'] ?? '');
            $modelNumber = self::unstarClauseNumber($number);
            $warnings = [];
            $row['raw_heading'] = $row['raw_heading'] ?? $row['title'] ?? '';
            $titleSource = (string)($row['title_source'] ?? '');
            if (self::shouldKeepExtractedTitle($row)) {
                $row['title'] = self::cleanReviewTitle((string)($row['title'] ?? $row['raw_heading'] ?? ''));
                $row['model_outline_match'] = isset($modelTitles[$modelNumber]);
            } elseif (isset($modelTitles[$modelNumber])) {
                $row['title'] = self::normalizeGeneratedReviewTitle($modelTitles[$modelNumber]);
                $row['title_source'] = 'model_summarized';
                $row['model_outline_match'] = true;
            } else {
                $row['title'] = self::simplifyClauseTitle(
                    $number,
                    (string)($row['raw_heading'] ?? $row['title'] ?? ''),
                    (string)($row['original_text'] ?? '')
                );
                $row['title_source'] = 'model_summarized';
                $row['model_outline_match'] = false;
                $warnings[] = '模型条款纲要未匹配到该条款号，请重点核对是否为目录噪声、附录或抽取误识别。';
            }

            $originalText = trim((string)($row['original_text'] ?? ''));
            if ($originalText === '' || $originalText === trim($number . ' ' . (string)($row['raw_heading'] ?? $row['title'] ?? ''))) {
                $warnings[] = '自动抽取原文较短，可能只有标题行。';
            }
            $row['quality_check'] = [
                'original_text_source' => 'registered_source_text',
                'title_source' => $row['title_source'] ?? 'original_heading',
                'model_outline_match' => (bool)($row['model_outline_match'] ?? false),
                'warnings' => $warnings,
            ];
            $titleNote = (string)($row['title_source'] ?? '') === $titleSource && self::shouldKeepExtractedTitle($row)
                ? '标题来自原文识别'
                : '标题由模型生成';
            $row['manual_review_note'] = $titleNote . '；原文来自附件自动抽取。请核对条款号、标题概括和原文完整性。'
                . ($warnings !== [] ? ' 检查提示：' . implode('；', $warnings) : '');
        }
        unset($row);

        return $rows;
    }

    private static function unstarClauseNumber(string $number): string
    {
        return rtrim($number, '*');
    }

    private static function shouldKeepExtractedTitle(array $row): bool
    {
        $source = (string)($row['title_source'] ?? '');
        if (!in_array($source, ['original_heading', 'known_heading', 'appendix_heading'], true)) {
            return false;
        }
        $title = self::cleanReviewTitle((string)($row['title'] ?? $row['raw_heading'] ?? ''));
        if ($title === '') {
            return false;
        }
        $number = (string)($row['clause_number'] ?? '');
        $originalText = trim((string)($row['original_text'] ?? ''));
        $titleLine = trim($number . ' ' . $title);

        return mb_strlen($title) <= 30
            && !preg_match('/[。；;]/u', $title)
            && ($originalText === '' || str_starts_with($originalText, $titleLine) || mb_strlen($originalText) <= mb_strlen($titleLine) + 12);
    }

    private static function cleanReviewTitle(string $title): string
    {
        $title = self::cleanExtractedClauseTitle($title);
        $title = trim(preg_replace('/^(?:第[一二三四五六七八九十百零〇]+条|[0-9]+(?:\.[0-9]+){0,4}[a-z]?\\)?|附录[A-Z])\s*/iu', '', $title) ?? $title);

        return trim($title);
    }

    private static function normalizeGeneratedReviewTitle(string $title): string
    {
        $title = self::cleanReviewTitle($title);
        if (!in_array($title, ['通用要求', '结构要求', '资源要求', '过程要求', '管理体系要求', '特殊要求'], true)) {
            $title = preg_replace('/应用要求$/u', '应用', $title) ?? $title;
            $title = preg_replace('/要求$/u', '', $title) ?? $title;
        }

        return trim($title);
    }

    private static function clauseCandidatesFromRows(array $rows, array $source, string $extractionMethod): array
    {
        $sourceCode = (string)($source['source_code'] ?? '');
        $candidates = [];
        foreach (self::sortClauseRows($rows) as $row) {
            $originalText = trim((string)($row['original_text'] ?? ''));
            if ($originalText === '') {
                $originalText = (string)$row['clause_number'] . ' ' . (string)$row['title'];
            }
            $candidates[] = [
                'candidate_type' => 'clause',
                'source_code' => $sourceCode,
                'payload' => [
                    'source_code' => $sourceCode,
                    'source_id' => (string)($source['id'] ?? ''),
                    'source_status' => (string)($source['status'] ?? ''),
                    'clause_number' => $row['clause_number'],
                    'title' => $row['title'],
                    'raw_heading' => $row['raw_heading'] ?? $row['title'] ?? '',
                    'title_source' => $row['title_source'] ?? 'original_heading',
                    'level' => (int)($row['level'] ?? self::clauseLevel((string)$row['clause_number'])),
                    'locator' => $row['locator'] ?? ($extractionMethod . ':' . $sourceCode . ':' . (string)$row['clause_number']),
                    'sort_key' => $row['sort_key'] ?? self::clauseSortToken((string)$row['clause_number']),
                    'original_text' => $originalText,
                    'extraction_method' => $extractionMethod,
                    'is_key_item' => (bool)($row['is_key_item'] ?? false),
                    'quality_check' => $row['quality_check'] ?? [
                        'original_text_source' => $extractionMethod,
                        'title_source' => $row['title_source'] ?? 'original_heading',
                        'model_outline_match' => false,
                        'warnings' => [],
                    ],
                    'manual_review_note' => $row['manual_review_note'] ?? '',
                    'review_status' => 'pending_review',
                ],
            ];
        }

        return $candidates;
    }

    private static function modelSeededClauseRows(string $sourceCode): array
    {
        return match ($sourceCode) {
            'CNAS-CL01:2018', 'GB/T 27025-2019' => self::iso17025ModelRows(),
            'CNAS-CL01-G001:2024' => self::cnasG001ModelRows(),
            'CNAS-CL01-A015:2018' => self::cnasA015ModelRows(),
            '市场监管总局公告2023年第21号' => self::cma2023ModelRows(),
            default => [],
        };
    }

    private static function rowsFromModelOutline(string $outline): array
    {
        $rows = [];
        foreach (preg_split('/\R/u', trim($outline)) ?: [] as $line) {
            $line = trim((string)$line);
            if ($line === '') {
                continue;
            }
            if (!preg_match('/^(\S+)\s+(.+)$/u', $line, $match)) {
                continue;
            }
            $number = (string)$match[1];
            $title = trim((string)$match[2]);
            $rows[] = [
                'clause_number' => $number,
                'title' => $title,
                'raw_heading' => $title,
                'title_source' => 'model_seeded',
                'locator' => 'model-seeded:' . $number,
            ];
        }

        return $rows;
    }

    private static function iso17025ModelRows(): array
    {
        return self::rowsFromModelOutline(<<<'TEXT'
1 范围
2 规范性引用文件
3 术语和定义
4 通用要求
4.1 公正性
4.1.1 公正性承诺
4.1.2 公正性责任
4.1.3 公正性风险识别
4.1.4 公正性风险消除或降低
4.1.5 公正性持续控制
4.2 保密性
4.2.1 保密责任
4.2.2 信息公开前通知
4.2.3 法律要求披露信息
4.2.4 外部来源信息保密
4.2.5 人员保密要求
5 结构要求
5.1 法律实体
5.2 管理层职责
5.3 实验室活动范围
5.4 活动实施方式
5.5 组织结构和职责权限
5.6 管理体系实施职责
5.7 沟通和变更管理
6 资源要求
6.1 总则
6.2 人员
6.2.1 人员公正与能力
6.2.2 人员能力要求文件化
6.2.3 人员能力保证
6.2.4 人员职责沟通
6.2.5 人员程序和记录
6.2.6 人员授权
6.3 设施和环境条件
6.3.1 设施环境适宜性
6.3.2 设施环境要求文件化
6.3.3 环境条件监控
6.3.4 区域控制
6.3.5 外部场所控制
6.4 设备
6.4.1 设备可获得性
6.4.2 外部设备控制
6.4.3 设备操作程序
6.4.4 设备要求验证
6.4.5 设备测量准确度
6.4.6 设备校准要求
6.4.7 校准方案
6.4.8 校准状态标识
6.4.9 设备隔离和停用
6.4.10 期间核查
6.4.11 校准和修正因子更新
6.4.12 防止设备意外调整
6.4.13 设备记录
6.5 计量溯源性
6.5.1 计量溯源建立
6.5.2 计量溯源实现
6.5.3 无法实现 SI 溯源时的替代方式
6.6 外部提供的产品和服务
6.6.1 外部提供产品和服务适用情形
6.6.2 外部提供产品和服务程序
6.6.3 外部供应方沟通
7 过程要求
7.1 要求、标书和合同评审
7.1.1 评审程序
7.1.2 方法适宜性告知
7.1.3 符合性声明决策规则
7.1.4 合同偏离处理
7.1.5 合同差异处理
7.1.6 合同变更
7.1.7 客户合作
7.1.8 评审记录
7.2 方法选择、验证和确认
7.2.1 方法选择和验证
7.2.1.1 方法和程序适宜性
7.2.1.2 方法版本控制
7.2.1.3 方法选择
7.2.1.4 方法偏离控制
7.2.1.5 方法验证
7.2.1.6 方法开发计划
7.2.1.7 非标准方法协商
7.2.2 方法确认
7.2.2.1 方法确认适用情形
7.2.2.2 方法确认变更
7.2.2.3 方法确认性能
7.2.2.4 方法确认记录
7.3 抽样
7.3.1 抽样计划和方法
7.3.2 抽样方法内容
7.3.3 抽样记录
7.4 检测或校准物品处置
7.4.1 物品运输接收处置保护
7.4.2 物品标识
7.4.3 物品偏离处理
7.4.4 物品环境条件控制
7.5 技术记录
7.5.1 技术记录内容
7.5.2 技术记录修改
7.6 测量不确定度评定
7.6.1 不确定度贡献识别
7.6.2 校准实验室不确定度评定
7.6.3 检测实验室不确定度评定
7.7 结果有效性保证
7.7.1 内部质量控制
7.7.2 外部质量保证
7.7.3 结果有效性数据分析
7.8 结果报告
7.8.1 通用要求
7.8.1.1 结果准确清晰报告
7.8.1.2 报告内容约定
7.8.1.3 简化报告
7.8.2 报告通用信息
7.8.2.1 报告基本信息
7.8.2.2 报告责任
7.8.3 检测报告特定要求
7.8.3.1 检测报告附加信息
7.8.3.2 抽样检测报告信息
7.8.4 校准证书特定要求
7.8.4.1 校准证书附加信息
7.8.4.2 校准前后结果
7.8.4.3 校准周期建议
7.8.5 抽样报告要求
7.8.6 符合性声明报告
7.8.6.1 符合性声明决策规则
7.8.6.2 符合性声明报告内容
7.8.7 意见和解释报告
7.8.7.1 意见解释授权
7.8.7.2 意见解释依据
7.8.7.3 口头意见解释记录
7.8.8 报告修改
7.8.8.1 报告修改标识
7.8.8.2 报告修改重新发布
7.8.8.3 新报告唯一标识
7.9 投诉
7.9.1 投诉过程
7.9.2 投诉过程可获得性
7.9.3 投诉确认和处理责任
7.9.4 投诉信息验证
7.9.5 投诉进展和结果告知
7.9.6 投诉结论独立性
7.9.7 投诉正式通知
7.10 不符合工作
7.10.1 不符合工作程序
7.10.2 不符合工作记录
7.10.3 不符合再发生处理
7.11 数据控制和信息管理
7.11.1 数据和信息访问
7.11.2 信息管理系统确认
7.11.3 信息管理系统保护
7.11.4 外部信息系统管理
7.11.5 数据完整性和系统故障
7.11.6 计算和数据传输检查
8 管理体系要求
8.1 方式
8.1.1 总则
8.1.2 方式 A
8.1.3 方式 B
8.2 管理体系文件
8.2.1 方针目标建立
8.2.2 方针目标能力要求
8.2.3 管理层承诺
8.2.4 文件可获得性
8.2.5 管理体系完整性
8.3 管理体系文件控制
8.3.1 文件控制要求
8.3.2 文件控制活动
8.4 记录控制
8.4.1 记录建立和保存
8.4.2 记录控制要求
8.5 风险和机遇措施
8.5.1 风险机遇考虑
8.5.2 风险机遇措施策划
8.5.3 风险机遇措施相适应
8.6 改进
8.6.1 改进机会识别
8.6.2 客户反馈
8.7 纠正措施
8.7.1 不符合和纠正措施
8.7.2 纠正措施适应性
8.7.3 纠正措施记录
8.8 内部审核
8.8.1 内部审核实施
8.8.2 内部审核方案
8.9 管理评审
8.9.1 管理评审实施
8.9.2 管理评审输入
8.9.3 管理评审输出
附录A 计量溯源性
附录B 管理体系方式
TEXT);
    }

    private static function cnasG001ModelRows(): array
    {
        return self::rowsFromModelOutline(<<<'TEXT'
4 通用要求应用要求
4.1 公正性应用要求
4.1.4 公正性风险持续识别
4.2 保密性应用要求
5 结构要求应用要求
5.1 法律实体名称和责任
5.2 实验室活动负责人员要求
5.3 实验室活动范围说明要求
5.5a) 母体机构组织关系说明要求
5.5c) 文件层级与详略程度要求
6 资源要求应用要求
6.2 人员应用要求
6.3 设施和环境条件应用要求
6.3.1 设施使用权和场地条件
6.4 设备应用要求
6.4.1a) 设备支配权和标准物质
6.4.3 设备管理责任
6.4.4 设备重新投入使用验证
6.4.6 设备校准必要性评估
6.4.7 校准方案内容
6.4.10 期间核查方法和周期
6.5 计量溯源性应用要求
6.6 外部提供产品和服务应用要求
6.6.1a) 外部产品和服务识别控制
6.6.1c) 能力验证等支持服务选择
6.6.2a) 外部产品和服务要求确定
6.6.2b) 外部实验室活动服务选择
7 过程要求应用要求
7.1 要求、标书和合同评审应用要求
7.1.7 客户需求说明
7.2 方法选择、验证和确认应用要求
7.2.1.5 方法验证
7.3 抽样应用要求
7.3.1a) 抽样活动认可边界
7.4 检测或校准物品处置应用要求
7.4.1 样品处理和客户信息安全
7.4.2 样品标识
7.5 技术记录应用要求
7.5.2 技术记录更改控制
7.6 测量不确定度评定应用要求
7.7 结果有效性保证应用要求
7.8 结果报告应用要求
7.8.1.1 报告拆分控制
7.8.1.2 报告副本保存
7.8.7.1 意见和解释控制
7.9 投诉应用要求
7.10 不符合工作应用要求
7.10.3 不符合工作原因分析
7.11 数据控制和信息管理应用要求
7.11.2 LIMS 确认和数据安全
8 管理体系要求应用要求
8.1.1 管理体系覆盖和支持文件
8.1.3 方式 B 管理体系证据
8.2 管理体系文件应用要求
8.3 文件控制应用要求
8.4 记录控制应用要求
8.4.2 技术记录保存期限
8.5 风险和机遇应用要求
8.6 改进应用要求
8.7 纠正措施应用要求
8.7.1 纠正措施根本原因分析
8.8 内部审核应用要求
8.8.2 内部审核策划
8.9 管理评审应用要求
8.9.1 管理评审策划
TEXT);
    }

    private static function cnasA015ModelRows(): array
    {
        return self::rowsFromModelOutline(<<<'TEXT'
1 范围
2 规范性引用文件
3 术语和定义
4 通用要求
5 结构要求
5.4 非固定场所检测控制要求
6 资源要求
6.2 人员
6.2.1 检测人员固定性要求
6.2.2 授权签字人配置要求
6.2.3 人员能力确认要求
6.3 设施和环境条件
6.3.1 珠宝检测实验室环境要求
6.3.2 贵金属检测实验室环境要求
6.4 设备
6.4.1 珠宝玉石检测设备配置要求
6.4.2 贵金属检测设备配置要求
6.4.5 定性检测设备校准要求
7 过程要求
7.2 方法选择、验证和确认
7.2.1.3 检测工作指导书要求
7.4 检测物品处置
7.4.1 样品交接记录要求
7.4.2 样品安保措施要求
7.5 技术记录
7.5.1 检测观测记录要求
7.7 结果有效性保证
7.7.2 实验室间比对与能力验证要求
7.8 结果报告
7.8.1 报告简化内容要求
8 管理体系要求
8.2 管理体系文件要求
8.9 管理评审要求
附录A 珠宝玉石检测能力验证领域和频次
附录B 参考文件
TEXT);
    }

    private static function cma2023ModelRows(): array
    {
        return self::rowsFromModelOutline(<<<'TEXT'
2 评审内容与要求
2.8 机构法律地位和责任
2.8.1 法律地位和责任
2.8.2 诚信守法自我承诺
2.8.3 公正性和独立性
2.8.4 保密义务和措施
2.9 人员
2.9.1 劳动关系和人员管理
2.9.2 人员能力要求
2.9.3 授权签字人要求
2.10 场所环境
2.10.1 检验检测场所
2.10.2 环境条件要求
2.11 设备设施
2.11.1 设备设施配置
2.11.2 设备检定校准和溯源
2.11.3 标准物质溯源
2.12 管理体系
2.12.1 管理体系文件
2.12.2 合同评审
2.12.3 服务和供应品采购
2.12.4 方法验证和确认
2.12.5 测量不确定度报告
2.12.6 结果报告
2.12.7 记录管理
2.12.8 信息系统管理
2.12.9 结果质量控制
2.13 特殊要求
TEXT);
    }

    public static function buildCurrentManualCandidates(): array
    {
        $manual = self::extractCurrentManualBaseline();
        $candidates = [];

        foreach ($manual['positions'] as $position) {
            $candidates[] = [
                'candidate_type' => 'position',
                'source_code' => 'CURRENT-MANUAL',
                'payload' => $position + ['review_status' => 'pending_review'],
            ];
        }

        foreach ($manual['responsibility_matrix'] as $row) {
            $candidates[] = [
                'candidate_type' => 'responsibility',
                'source_code' => 'CURRENT-MANUAL',
                'payload' => $row + ['review_status' => 'pending_review'],
            ];
        }

        foreach ($manual['requirement_elements'] as $element) {
            $candidates[] = [
                'candidate_type' => 'requirement_element',
                'source_code' => 'CURRENT-MANUAL',
                'payload' => $element + ['review_status' => 'pending_review'],
            ];
        }

        foreach ($manual['element_clause_mappings'] as $mapping) {
            $candidates[] = [
                'candidate_type' => 'element_clause_mapping',
                'source_code' => 'CURRENT-MANUAL',
                'payload' => $mapping + ['review_status' => 'pending_review'],
            ];
        }

        foreach ($manual['manual_clause_mappings'] as $mapping) {
            $candidates[] = [
                'candidate_type' => 'document_section',
                'source_code' => 'CURRENT-MANUAL',
                'payload' => $mapping + ['review_status' => 'pending_review'],
            ];
        }

        foreach (self::extractReferenceManualSupplements()['procedure_mappings'] as $mapping) {
            if (($mapping['element_code'] ?? '') === '' || ($mapping['document_title'] ?? '') === '') {
                continue;
            }

            $candidates[] = [
                'candidate_type' => 'trace_link',
                'source_code' => 'REFERENCE-2025-MANUAL',
                'payload' => [
                    'source_type' => 'requirement_element',
                    'source_key' => $mapping['element_code'],
                    'target_type' => 'document',
                    'target_key' => $mapping['document_title'],
                    'target_title' => trim(($mapping['document_number'] ?? '') . ' ' . $mapping['document_title']),
                    'relation_type' => 'controlled_by',
                    'evidence_note' => '参考2025质量手册程序文件目录补充建议，发布前需与现用受控文件核对。',
                    'review_status' => 'pending_review',
                ],
            ];
        }

        return $candidates;
    }

    public static function buildTrainingTraceCandidates(): array
    {
        $sample = self::trainingTraceabilitySample();
        $candidates = [];

        $candidates[] = [
            'candidate_type' => 'trace_link',
            'source_code' => 'SAMPLE-6.2',
            'payload' => [
                'source_type' => 'requirement_element',
                'source_key' => $sample['element_code'],
                'target_type' => 'document_section',
                'target_key' => 'manual:6.2',
                'relation_type' => 'implemented_by',
                'evidence_note' => '人员要素来自现用质量手册附录16，对应现用质量手册 6.2 章节。',
                'review_status' => 'pending_review',
            ],
        ];
        $candidates[] = [
            'candidate_type' => 'trace_link',
            'source_code' => 'SAMPLE-6.2',
            'payload' => [
                'source_type' => 'document_section',
                'source_key' => 'manual:6.2',
                'target_type' => 'document',
                'target_key' => '人员培训程序',
                'relation_type' => 'controlled_by',
                'evidence_note' => '程序文件按标题关键词匹配，发布前需人工确认具体受控文件编号。',
                'review_status' => 'pending_review',
            ],
        ];

        foreach ($sample['record_forms'] as $form) {
            $candidates[] = [
                'candidate_type' => 'trace_link',
                'source_code' => 'SAMPLE-6.2',
                'payload' => [
                    'source_type' => 'document',
                    'source_key' => '人员培训程序',
                    'target_type' => 'record_form_template',
                    'target_key' => $form['doc_number'],
                    'target_title' => $form['name'],
                    'relation_type' => 'evidenced_by',
                    'review_status' => 'pending_review',
                ],
            ];
        }

        $moduleMap = [];
        foreach ($sample['business_modules'] as $module) {
            $moduleMap[$module['module_key']] = $module['name'];
        }
        $formModuleLinks = [
            'XZTC/BG-01-01' => 'training_plans',
            'XZTC/BG-01-02' => 'training_records',
            'XZTC/BG-01-08' => 'competency_records',
            'XZTC/BG-01-09' => 'trainings',
        ];

        foreach ($sample['record_forms'] as $form) {
            $moduleKey = $formModuleLinks[$form['doc_number']] ?? '';
            if ($moduleKey === '') {
                continue;
            }
            $candidates[] = [
                'candidate_type' => 'trace_link',
                'source_code' => 'SAMPLE-6.2',
                'payload' => [
                    'source_type' => 'record_form_template',
                    'source_key' => $form['doc_number'],
                    'target_type' => 'business_module',
                    'target_key' => $moduleKey,
                    'target_title' => $moduleMap[$moduleKey] ?? $moduleKey,
                    'relation_type' => 'operated_by',
                    'evidence_note' => $form['name'] . '进入对应运行模块，作为运行证据入口。',
                    'review_status' => 'pending_review',
                ],
            ];
        }

        return $candidates;
    }

    public static function extractDocxTables(string $path): array
    {
        if (!is_file($path)) {
            throw new RuntimeException('DOCX 文件不存在：' . $path);
        }

        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw new RuntimeException('无法打开 DOCX 文件：' . $path);
        }

        $xml = $zip->getFromName('word/document.xml');
        $zip->close();
        if (!is_string($xml) || trim($xml) === '') {
            throw new RuntimeException('DOCX 缺少 word/document.xml：' . $path);
        }

        $dom = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $loaded = $dom->loadXML($xml);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        if (!$loaded) {
            throw new RuntimeException('DOCX XML 解析失败：' . $path);
        }

        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');

        $tables = [];
        foreach ($xpath->query('//w:tbl') ?: [] as $tableNode) {
            $rows = [];
            foreach ($xpath->query('./w:tr', $tableNode) ?: [] as $rowNode) {
                $row = [];
                foreach ($xpath->query('./w:tc', $rowNode) ?: [] as $cellNode) {
                    if (!$cellNode instanceof DOMElement) {
                        continue;
                    }
                    $row[] = self::cellText($xpath, $cellNode);
                }
                if ($row !== []) {
                    $rows[] = $row;
                }
            }
            if ($rows !== []) {
                $tables[] = $rows;
            }
        }

        return $tables;
    }

    private static function findTable(array $tables, array $needles): array
    {
        foreach ($tables as $table) {
            $flat = implode(' ', array_map(static fn (array $row): string => implode(' ', $row), array_slice($table, 0, 5)));
            $matched = true;
            foreach ($needles as $needle) {
                if (!str_contains($flat, $needle)) {
                    $matched = false;
                    break;
                }
            }
            if ($matched) {
                return $table;
            }
        }

        return [];
    }

    private static function positionsFromResponsibilityTable(array $table): array
    {
        if ($table === []) {
            return [];
        }

        $positions = [];
        foreach (array_slice($table[0], 1) as $index => $name) {
            $name = trim($name);
            if ($name === '') {
                continue;
            }
            $positions[] = [
                'name' => $name,
                'code' => self::positionCode($name),
                'source' => 'current_quality_manual_appendix',
                'sort_order' => $index + 1,
            ];
        }

        return $positions;
    }

    private static function matrixFromResponsibilityTable(array $table): array
    {
        if ($table === []) {
            return [];
        }

        $positions = self::positionsFromResponsibilityTable($table);
        $rows = [];
        foreach (array_slice($table, 1) as $row) {
            $heading = trim((string)($row[0] ?? ''));
            if (!preg_match('/^([0-9]+(?:\.[0-9]+)*)(?:\s+(.+))?$/u', $heading, $match)) {
                continue;
            }
            $clauseNumber = $match[1];
            $clauseTitle = trim((string)($match[2] ?? ''));

            foreach ($positions as $index => $position) {
                $symbol = trim((string)($row[$index + 1] ?? ''));
                $type = self::normalizeResponsibilitySymbol($symbol, 'current_manual');
                if ($type === null) {
                    continue;
                }

                $rows[] = [
                    'clause_number' => $clauseNumber,
                    'clause_title' => $clauseTitle,
                    'position_name' => $position['name'],
                    'position_code' => $position['code'],
                    'responsibility_type' => $type,
                    'raw_symbol' => $symbol,
                    'source_style' => 'current_manual',
                ];
            }
        }

        return $rows;
    }

    private static function manualMappingsFromTable(array $table): array
    {
        if ($table === []) {
            return [];
        }

        $rows = [];
        foreach (array_slice($table, 1) as $row) {
            $manualSection = trim((string)($row[0] ?? ''));
            if (!preg_match('/^[0-9]+(?:\.[0-9]+)*$/u', $manualSection)) {
                continue;
            }

            $rows[] = [
                'manual_section' => $manualSection,
                'element_name' => trim((string)($row[1] ?? '')),
                'cnas_clause' => trim((string)($row[2] ?? '')),
                'cma_clause' => trim((string)($row[3] ?? '')),
                'source' => 'current_quality_manual_clause_mapping',
            ];
        }

        return $rows;
    }

    private static function requirementElementsFromManualMappings(array $manualMappings): array
    {
        $elements = [];
        foreach ($manualMappings as $mapping) {
            $code = trim((string)($mapping['manual_section'] ?? ''));
            if ($code === '' || isset($elements[$code])) {
                continue;
            }

            $elements[$code] = [
                'element_code' => $code,
                'name' => trim((string)($mapping['element_name'] ?? $code)),
                'manual_section' => $code,
                'source_basis' => 'current_quality_manual_appendix16',
            ];
        }

        return array_values($elements);
    }

    private static function elementClauseMappingsFromManualMappings(array $manualMappings): array
    {
        $rows = [];
        $seen = [];
        foreach ($manualMappings as $mapping) {
            $elementCode = trim((string)($mapping['manual_section'] ?? ''));
            if ($elementCode === '') {
                continue;
            }

            foreach (self::sourceClausesForManualMapping($mapping) as $sourceClause) {
                $key = $elementCode . '|' . $sourceClause['source_code'] . '|' . $sourceClause['clause_number'];
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $rows[] = [
                    'element_code' => $elementCode,
                    'element_name' => trim((string)($mapping['element_name'] ?? '')),
                    'manual_section' => $elementCode,
                    'source_code' => $sourceClause['source_code'],
                    'clause_number' => $sourceClause['clause_number'],
                    'clause_title' => $sourceClause['clause_title'],
                    'mapping_basis' => $sourceClause['mapping_basis'] ?? 'equivalent',
                    'mapping_source' => $sourceClause['mapping_source'] ?? 'current_quality_manual_appendix16',
                ];
            }
        }

        return $rows;
    }

    private static function sourceClausesForManualMapping(array $mapping): array
    {
        $rows = [];
        $elementName = trim((string)($mapping['element_name'] ?? ''));
        $cnasClause = trim((string)($mapping['cnas_clause'] ?? ''));
        foreach (self::splitNumericClauseNumbers($cnasClause) as $number) {
            $rows[] = [
                'source_code' => 'CNAS-CL01:2018',
                'clause_number' => $number,
                'clause_title' => $elementName,
                'mapping_basis' => 'equivalent',
                'mapping_source' => 'current_quality_manual_appendix16',
            ];
            $rows[] = [
                'source_code' => 'GB/T 27025-2019',
                'clause_number' => $number,
                'clause_title' => $elementName,
                'mapping_basis' => 'equivalent',
                'mapping_source' => 'current_quality_manual_appendix16',
            ];
            $rows[] = [
                'source_code' => 'CNAS-CL01-A015:2018',
                'clause_number' => $number,
                'clause_title' => $elementName,
                'mapping_basis' => 'supplement',
                'mapping_source' => 'current_quality_manual_appendix16_jewelry_supplement',
            ];
        }

        foreach (self::splitCmaClauseNumbers((string)($mapping['cma_clause'] ?? '')) as $number) {
            $rows[] = [
                'source_code' => '市场监管总局公告2023年第21号',
                'clause_number' => $number,
                'clause_title' => $elementName,
                'mapping_basis' => 'equivalent',
                'mapping_source' => 'current_quality_manual_appendix16',
            ];
        }

        return $rows;
    }

    private static function splitNumericClauseNumbers(string $value): array
    {
        if ($value === '' || $value === '/') {
            return [];
        }

        preg_match_all('/\b[0-9]+(?:\.[0-9]+){0,4}\b/u', $value, $matches);

        return array_values(array_unique($matches[0] ?? []));
    }

    private static function splitCmaClauseNumbers(string $value): array
    {
        $value = trim($value);
        if ($value === '' || $value === '/') {
            return [];
        }

        preg_match_all('/第[一二三四五六七八九十百零〇]+条(?:（[^）]+）)*/u', $value, $matches);
        $numbers = $matches[0] ?? [];

        return $numbers !== [] ? array_values(array_unique($numbers)) : [$value];
    }

    private static function currentProcedureDocumentBaselines(): array
    {
        $byTitle = [];
        foreach (self::CURRENT_PROCEDURE_DIRS as $dir) {
            $absoluteDir = self::workspacePath($dir);
            if (!is_dir($absoluteDir)) {
                continue;
            }

            $files = array_merge(
                glob($absoluteDir . DIRECTORY_SEPARATOR . '*.docx') ?: [],
                glob($absoluteDir . DIRECTORY_SEPARATOR . '*.doc') ?: []
            );
            foreach ($files as $path) {
                $row = self::procedureDocumentBaselineFromPath((string)$path, $dir);
                if ($row === null) {
                    continue;
                }

                $title = $row['title'];
                if (!isset($byTitle[$title]) || (int)$row['version_year'] > (int)$byTitle[$title]['version_year']) {
                    $byTitle[$title] = $row;
                }
            }
        }

        $rows = array_values($byTitle);
        usort($rows, static function (array $left, array $right): int {
            return strnatcmp((string)$left['doc_number'], (string)$right['doc_number']);
        });

        return $rows;
    }

    private static function procedureDocumentBaselineFromPath(string $path, string $relativeDir): ?array
    {
        $fileName = basename($path);
        $baseName = pathinfo($fileName, PATHINFO_FILENAME);
        if (preg_match('/(封面|目录|批准页|修改页)$/u', $baseName)) {
            return null;
        }
        if (!preg_match('/^([0-9]+(?:-[0-9]+)?)\s*[-－]\s*(20[0-9]{2})(.+)$/u', $baseName, $match)) {
            return null;
        }

        $title = trim((string)$match[3]);
        if ($title === '' || !str_ends_with($title, '程序')) {
            return null;
        }

        $versionYear = (string)$match[2];

        return [
            'document_level' => 2,
            'doc_number' => 'QP-' . (string)$match[1],
            'title' => $title,
            'version' => $versionYear,
            'version_year' => $versionYear,
            'file_path' => rtrim($relativeDir, '/\\') . '/' . $fileName,
            'file_name' => $fileName,
            'file_type' => strtolower((string)pathinfo($fileName, PATHINFO_EXTENSION)),
            'match_confidence' => is_file($path) ? 'high' : 'missing_file',
            'source_note' => '本地现用程序文件目录标题匹配；同名程序优先采用较新年份版本。',
        ];
    }

    private static function referenceManualMappingsFromTables(array $tables): array
    {
        $rows = [];
        foreach ($tables as $table) {
            if (!self::tableHeaderContains($table, ['质量手册', 'CNAS', '评审准则'])) {
                continue;
            }
            foreach (array_slice($table, 1) as $row) {
                $manualSection = self::firstNumericClause((string)($row[0] ?? ''));
                if ($manualSection === '') {
                    continue;
                }
                $rows[] = [
                    'manual_section' => $manualSection,
                    'title' => trim((string)($row[0] ?? '')),
                    'cnas_clause' => trim((string)($row[1] ?? '')),
                    'cma_clause' => trim((string)($row[2] ?? '')),
                    'note' => trim((string)($row[3] ?? '')),
                    'mapping_source' => 'reference_quality_manual_2025',
                ];
            }
        }

        return $rows;
    }

    private static function referenceProcedureMappingsFromTables(array $tables): array
    {
        $rows = [];
        foreach ($tables as $table) {
            if (!self::tableHeaderContains($table, ['编号', '文件名称', '对应条款'])) {
                continue;
            }
            foreach (array_slice($table, 1) as $row) {
                $documentNumber = trim((string)($row[1] ?? ''));
                $documentTitle = trim((string)($row[2] ?? ''));
                if ($documentNumber === '' || $documentTitle === '' || !preg_match('/^[A-Z]{1,4}[-－]?[0-9]+/iu', $documentNumber)) {
                    continue;
                }

                $cnasClause = trim((string)($row[3] ?? ''));
                foreach (self::splitNumericClauseNumbers($cnasClause) as $elementCode) {
                    $rows[] = [
                        'element_code' => $elementCode,
                        'document_number' => $documentNumber,
                        'document_title' => $documentTitle,
                        'cnas_clause' => $cnasClause,
                        'cma_clause' => trim((string)($row[4] ?? '')),
                        'mapping_source' => 'reference_quality_manual_2025',
                    ];
                }
            }
        }

        return $rows;
    }

    private static function tableHeaderContains(array $table, array $needles): bool
    {
        $flat = implode(' ', array_map(static fn (array $row): string => implode(' ', $row), array_slice($table, 0, 2)));
        $flat = str_replace(' ', '', $flat);
        foreach ($needles as $needle) {
            if (!str_contains($flat, str_replace(' ', '', $needle))) {
                return false;
            }
        }

        return true;
    }

    private static function firstNumericClause(string $value): string
    {
        preg_match('/\b[0-9]+(?:\.[0-9]+){0,4}\b/u', $value, $match);

        return $match[0] ?? '';
    }

    private static function clauseRowsFromText(string $text): array
    {
        $rows = [];
        $byNumber = [];
        $lines = preg_split('/\R/u', $text) ?: [];
        $lines = self::removeFrontMatterBeforeFirstClause($lines);
        $current = null;

        $flush = static function (?array $block) use (&$rows, &$byNumber): void {
            if ($block === null) {
                return;
            }
            $originalText = self::normalizeOriginalClauseText($block['lines']);
            if ($originalText === '') {
                return;
            }
            $rawTitle = (string)$block['raw_title'];
            if (self::isAppendixNumber((string)$block['clause_number']) && $rawTitle === '') {
                $rawTitle = self::appendixTitleFromLines((string)$block['clause_number'], $block['lines']);
            }
            [$title, $rawHeading, $titleSource] = self::displayTitleForClause(
                (string)$block['clause_number'],
                $rawTitle,
                $originalText
            );
            if ($title === '') {
                return;
            }

            $row = [
                'clause_number' => (string)$block['clause_number'],
                'title' => $title,
                'raw_heading' => $rawHeading,
                'title_source' => $titleSource,
                'locator' => 'text-line:' . ((int)$block['line_number'] + 1),
                'original_text' => $originalText,
                'is_key_item' => (bool)($block['is_key_item'] ?? false),
            ];
            $number = (string)$row['clause_number'];
            if (isset($byNumber[$number])) {
                $existingIndex = $byNumber[$number];
                if (self::clauseRowQualityScore($row) <= self::clauseRowQualityScore($rows[$existingIndex])) {
                    return;
                }
                $rows[$existingIndex] = $row;
                return;
            }

            $byNumber[$number] = count($rows);
            $rows[] = $row;
        };

        foreach ($lines as $lineNumber => $line) {
            $line = trim(preg_replace('/\s+/u', ' ', $line) ?? $line);
            if ($line === '') {
                continue;
            }
            if (self::isPdfBoilerplateLine($line)) {
                continue;
            }
            $start = self::parseClauseStartLine($line);
            if ($start !== null) {
                if ($current !== null && self::isReferenceFileSectionBlock($current) && self::isChildClauseOf((string)$start['clause_number'], (string)$current['clause_number'])) {
                    $current['lines'][] = $line;
                    continue;
                }
                $flush($current);
                $current = [
                    'clause_number' => $start['clause_number'],
                    'raw_title' => $start['raw_title'],
                    'is_key_item' => (bool)($start['is_key_item'] ?? false),
                    'line_number' => $lineNumber,
                    'lines' => [$line],
                ];
                continue;
            }

            if ($current === null || mb_strlen($line) > 240) {
                continue;
            }
            $current['lines'][] = $line;
        }
        $flush($current);

        return $rows;
    }

    private static function isReferenceFileSectionBlock(array $block): bool
    {
        return (string)($block['clause_number'] ?? '') === '2'
            && (bool)preg_match('/(规范性引用文件|引用文件)/u', (string)($block['raw_title'] ?? ''));
    }

    private static function isChildClauseOf(string $childNumber, string $parentNumber): bool
    {
        return str_starts_with($childNumber, $parentNumber . '.');
    }

    private static function removeFrontMatterBeforeFirstClause(array $lines): array
    {
        $hasFrontMatterMarker = false;
        foreach (array_slice($lines, 0, 80) as $line) {
            $line = trim(preg_replace('/\s+/u', ' ', (string)$line) ?? (string)$line);
            if (preg_match('/^(目次|前\s*言)$/u', $line)) {
                $hasFrontMatterMarker = true;
                break;
            }
        }
        if (!$hasFrontMatterMarker) {
            return $lines;
        }

        foreach ($lines as $index => $line) {
            $line = trim(preg_replace('/\s+/u', ' ', (string)$line) ?? (string)$line);
            if (preg_match('/^1(?:\s+|　)范围\s*$/u', $line)) {
                return array_slice($lines, $index);
            }
        }

        return $lines;
    }

    private static function parseClauseStartLine(string $line): ?array
    {
        if (mb_strlen($line) > 260) {
            return null;
        }
        $appendixStart = self::parseAppendixStartLine($line);
        if ($appendixStart !== null) {
            return $appendixStart;
        }
        if (preg_match('/^(第[一二三四五六七八九十百零〇]+条)\s*(.{2,180})$/u', $line, $articleMatch)) {
            return [
                'clause_number' => $articleMatch[1],
                'raw_title' => self::cleanExtractedClauseTitle($articleMatch[2]),
            ];
        }
        if (preg_match('/^([0-9]+(?:\.[0-9]+){0,5}[a-z]\))\s*(.{1,220})$/iu', $line, $letteredMatch)) {
            $rawTitle = self::cleanExtractedClauseTitle($letteredMatch[2]);
            if ($rawTitle === '' || preg_match('/^[。；，、,.]/u', $rawTitle)) {
                return null;
            }

            return [
                'clause_number' => strtolower($letteredMatch[1]),
                'raw_title' => $rawTitle,
            ];
        }
        if (!preg_match('/^([0-9]+(?:\.[0-9]+){0,5})(?:\s+|　)([^0-9].{1,220})$/u', $line, $match)) {
            if (!preg_match('/^([0-9]+(?:\.[0-9]+){0,5})(\*)(?:\s+|　)([^0-9].{1,220})$/u', $line, $starredMatch)) {
                return null;
            }
            $number = rtrim($starredMatch[1], '.') . '*';
            $rawTitle = self::cleanExtractedClauseTitle($starredMatch[3]);
            if ($rawTitle === '' || preg_match('/^[。；，、,.]/u', $rawTitle)) {
                return null;
            }

            return [
                'clause_number' => $number,
                'raw_title' => $rawTitle,
                'is_key_item' => true,
            ];
        }

        $number = rtrim($match[1], '.');
        if (!str_contains($number, '.') && (int)$number > 20) {
            return null;
        }
        $rawTitle = self::cleanExtractedClauseTitle($match[2]);
        if ($rawTitle === '' || preg_match('/^[。；，、,.]/u', $rawTitle)) {
            return null;
        }

        return [
            'clause_number' => $number,
            'raw_title' => $rawTitle,
            'is_key_item' => false,
        ];
    }

    private static function parseAppendixStartLine(string $line): ?array
    {
        if (!preg_match('/^附录\s*([A-ZＡ-Ｚ])(?:\s*[（(][^）)]*[）)])?\s*(.*)$/u', $line, $match)) {
            return null;
        }

        $letter = self::normalizeAppendixLetter((string)$match[1]);
        if ($letter === '') {
            return null;
        }

        $rawTitle = self::cleanExtractedClauseTitle((string)($match[2] ?? ''));
        $rawTitle = preg_replace('/^[：:、.\-—\s]+/u', '', (string)$rawTitle) ?? $rawTitle;

        return [
            'clause_number' => '附录' . $letter,
            'raw_title' => trim((string)$rawTitle),
        ];
    }

    private static function normalizeAppendixLetter(string $letter): string
    {
        $letter = trim($letter);
        if ($letter === '') {
            return '';
        }

        $fullWidthLetters = [
            'Ａ' => 'A', 'Ｂ' => 'B', 'Ｃ' => 'C', 'Ｄ' => 'D', 'Ｅ' => 'E',
            'Ｆ' => 'F', 'Ｇ' => 'G', 'Ｈ' => 'H', 'Ｉ' => 'I', 'Ｊ' => 'J',
            'Ｋ' => 'K', 'Ｌ' => 'L', 'Ｍ' => 'M', 'Ｎ' => 'N', 'Ｏ' => 'O',
            'Ｐ' => 'P', 'Ｑ' => 'Q', 'Ｒ' => 'R', 'Ｓ' => 'S', 'Ｔ' => 'T',
            'Ｕ' => 'U', 'Ｖ' => 'V', 'Ｗ' => 'W', 'Ｘ' => 'X', 'Ｙ' => 'Y',
            'Ｚ' => 'Z',
        ];
        $letter = $fullWidthLetters[$letter] ?? $letter;

        return strtoupper($letter);
    }

    private static function cleanExtractedClauseTitle(string $title): string
    {
        $title = trim($title, " \t\n\r\0\x0B：:");
        $title = trim(preg_replace('/[.．·]{2,}\s*[0-9]+\s*$/u', '', $title) ?? $title);

        return trim($title);
    }

    private static function normalizeOriginalClauseText(array $lines): string
    {
        $text = implode("\n", array_values(array_filter(array_map(
            static fn (string $line): string => trim($line),
            $lines
        ), static fn (string $line): bool => $line !== '' && !self::isPdfBoilerplateLine($line))));
        $text = preg_replace("/\n(?=[^0-9第\n])/u", '', (string)$text) ?? $text;
        $text = preg_replace('/[ \t]+/u', ' ', (string)$text) ?? $text;

        return trim((string)$text);
    }

    private static function isPdfBoilerplateLine(string $line): bool
    {
        $line = trim(preg_replace('/\s+/u', ' ', $line) ?? $line);
        if ($line === '') {
            return true;
        }

        return (bool)preg_match('/^\d{4}\s*年\s*\d{1,2}\s*月\s*\d{1,2}\s*日\s*发布\s*\d{4}\s*年\s*\d{1,2}\s*月\s*\d{1,2}\s*日\s*实施$/u', $line)
            || (bool)preg_match('/^第\s*\d+\s*页\s*(共\s*\d+\s*页)?$/u', $line)
            || (bool)preg_match('/^Page\s+\d+(\s+of\s+\d+)?$/iu', $line)
            || (bool)preg_match('/^(发布日期|实施日期|发布机构|实施机构)\s*[：:]/u', $line)
            || (bool)preg_match('/^(CNAS|GB\/T|RB\/T|ISO\/IEC)[-—\s].*第\s*\d+\s*页\s*(共\s*\d+\s*页)?$/iu', $line)
            || (bool)preg_match('/^(CNAS|GB\/T|RB\/T|ISO\/IEC)[-—\s].*Page\s+\d+(\s+of\s+\d+)?$/iu', $line);
    }

    private static function displayTitleForClause(string $number, string $rawTitle, string $originalText): array
    {
        $rawTitle = self::cleanExtractedClauseTitle($rawTitle);
        $knownTitle = self::knownGbt27025Heading($number);
        if ($knownTitle !== '' && (self::isNoisyOcrHeading($rawTitle) || self::isDecoratedKnownHeading($rawTitle, $knownTitle))) {
            return [$knownTitle, $knownTitle, 'known_heading'];
        }
        if (self::isAppendixNumber($number)) {
            $appendixTitle = self::appendixTitleForClause($number, $rawTitle, $originalText);

            return [$appendixTitle, $appendixTitle, 'appendix_heading'];
        }
        if (self::isClauseHeadingLike($number, $rawTitle)) {
            return [$rawTitle, $rawTitle, 'original_heading'];
        }

        $simplified = self::simplifyClauseTitle($number, $rawTitle, $originalText);

        return [$simplified, '', 'auto_simplified'];
    }

    private static function isNoisyOcrHeading(string $title): bool
    {
        if ($title === '') {
            return true;
        }
        $hanOnly = preg_replace('/[^\p{Han}]/u', '', $title) ?? '';

        return mb_strlen($hanOnly) < 2;
    }

    private static function isDecoratedKnownHeading(string $title, string $knownTitle): bool
    {
        if ($title === $knownTitle || !str_starts_with($title, $knownTitle)) {
            return false;
        }
        $suffix = trim(mb_substr($title, mb_strlen($knownTitle)));

        return $suffix !== '' && (bool)preg_match('/^[（(][^）)]*[A-Za-z][^）)]*[）)]$/u', $suffix);
    }

    private static function knownGbt27025Heading(string $number): string
    {
        $headings = [
            '1' => '范围',
            '2' => '规范性引用文件',
            '3' => '术语和定义',
            '4' => '通用要求',
            '4.1' => '公正性',
            '4.2' => '保密性',
            '5' => '结构要求',
            '6' => '资源要求',
            '6.1' => '总则',
            '6.2' => '人员',
            '6.3' => '设施和环境条件',
            '6.4' => '设备',
            '6.5' => '计量溯源性',
            '6.6' => '外部提供的产品和服务',
            '7' => '过程要求',
            '7.5' => '技术记录',
            '8.9' => '管理评审',
        ];

        return $headings[$number] ?? '';
    }

    private static function isAppendixNumber(string $number): bool
    {
        return (bool)preg_match('/^附录[A-Z]$/u', $number);
    }

    private static function appendixTitleForClause(string $number, string $rawTitle, string $originalText): string
    {
        if ($rawTitle !== '') {
            return $rawTitle;
        }

        $firstLine = strtok($originalText, "\n");
        foreach (preg_split('/\R/u', $originalText) ?: [] as $line) {
            $line = trim(preg_replace('/\s+/u', ' ', $line) ?? $line);
            if ($line === '' || $line === $firstLine) {
                continue;
            }
            if (preg_match('/^[（(](资料性|规范性|参考性)[）)]$/u', $line)) {
                continue;
            }
            if (self::isPdfBoilerplateLine($line)) {
                continue;
            }

            return self::cleanExtractedClauseTitle($line);
        }

        return $number;
    }

    private static function appendixTitleFromLines(string $number, array $lines): string
    {
        foreach (array_slice($lines, 1) as $line) {
            $line = trim(preg_replace('/\s+/u', ' ', (string)$line) ?? (string)$line);
            if ($line === '') {
                continue;
            }
            if (preg_match('/^[（(](资料性|规范性|参考性)[）)]$/u', $line)) {
                continue;
            }
            if (self::isPdfBoilerplateLine($line)) {
                continue;
            }

            return self::cleanExtractedClauseTitle($line);
        }

        return $number;
    }

    private static function isClauseHeadingLike(string $number, string $title): bool
    {
        if ($title === '') {
            return false;
        }
        if (str_starts_with($number, '2.') && mb_strlen($title) <= 90 && !preg_match('/[。；;]/u', $title)) {
            return true;
        }
        if (mb_strlen($title) <= 18 && !preg_match('/[。；;，,：:]/u', $title)) {
            return true;
        }

        return mb_strlen($title) <= 26
            && !preg_match('/(应|需|须|不得|可以|宜|如：|例如|符合|满足|记录|提供)/u', $title)
            && !preg_match('/[。；;]/u', $title);
    }

    private static function simplifyClauseTitle(string $number, string $rawTitle, string $originalText): string
    {
        $text = $rawTitle . "\n" . $originalText;
        $rules = [
            ['patterns' => ['每一项实验室活动', '技术记录', '足够的信息'], 'title' => '技术记录内容'],
            ['patterns' => ['技术记录', '样品', '报告'], 'title' => '技术记录内容'],
            ['patterns' => ['管理层', '时间间隔', '管理体系进行评审'], 'title' => '管理评审实施'],
            ['patterns' => ['管理层', '实验室活动', '全面负责'], 'title' => '实验室活动负责人员'],
            ['patterns' => ['母体机构', '其他活动', '实验室活动之间的关系'], 'title' => '母体机构组织关系说明'],
            ['patterns' => ['文件层级', '类型', '详略程度'], 'title' => '文件层级与详略程度'],
            ['patterns' => ['固定场所外'], 'title' => '非固定场所检测控制'],
            ['patterns' => ['工作指导书'], 'title' => '检测工作指导书'],
            ['patterns' => ['定性检测', '校准'], 'title' => '定性检测设备校准'],
            ['patterns' => ['珠宝检测实验室', '环境'], 'title' => '珠宝检测实验室环境条件'],
            ['patterns' => ['贵金属检测实验室', '环境'], 'title' => '贵金属检测实验室环境条件'],
            ['patterns' => ['授权签字人'], 'title' => '授权签字人配置'],
            ['patterns' => ['人员', '固定'], 'title' => '检测人员固定性'],
            ['patterns' => ['能力', '确认'], 'title' => '人员能力确认'],
            ['patterns' => ['设备', '珠宝玉石'], 'title' => '珠宝玉石检测设备配置'],
            ['patterns' => ['设备', '贵金属'], 'title' => '贵金属检测设备配置'],
            ['patterns' => ['样品', '交接'], 'title' => '样品交接记录'],
            ['patterns' => ['安保'], 'title' => '样品安保措施'],
            ['patterns' => ['观测', '记录'], 'title' => '检测观测记录'],
            ['patterns' => ['比对', '能力验证'], 'title' => '实验室间比对与能力验证'],
            ['patterns' => ['报告', '简化'], 'title' => '报告简化内容'],
            ['patterns' => ['多场所', '管理体系'], 'title' => '多场所管理体系覆盖'],
        ];
        foreach ($rules as $rule) {
            $matched = true;
            foreach ($rule['patterns'] as $pattern) {
                if (!str_contains($text, $pattern)) {
                    $matched = false;
                    break;
                }
            }
            if ($matched) {
                return $rule['title'];
            }
        }

        $firstPhrase = preg_split('/[。；;，,：:]/u', $rawTitle, 2)[0] ?? $rawTitle;
        $firstPhrase = self::cleanReviewTitle((string)$firstPhrase);
        if (mb_strlen($firstPhrase) > 22) {
            $firstPhrase = mb_substr($firstPhrase, 0, 22);
        }
        $firstPhrase = rtrim($firstPhrase, '的之及和与、，。；:：');
        if ($firstPhrase === '') {
            return '条款内容';
        }

        return $firstPhrase;
    }

    private static function clauseRowQualityScore(array $row): int
    {
        $score = mb_strlen((string)($row['original_text'] ?? ''));
        if (($row['title_source'] ?? '') === 'original_heading') {
            $score += 20;
        }
        if (str_contains((string)($row['original_text'] ?? ''), '……')) {
            $score -= 30;
        }

        return $score;
    }

    private static function gbt27025FallbackRows(): array
    {
        $rows = [
            ['1', '范围'],
            ['2', '规范性引用文件'],
            ['3', '术语和定义'],
            ['4', '通用要求'],
            ['4.1', '公正性'],
            ['4.2', '保密性'],
            ['5', '结构要求'],
            ['6', '资源要求'],
            ['6.1', '总则'],
            ['6.2', '人员'],
            ['6.3', '设施和环境条件'],
            ['6.4', '设备'],
            ['6.5', '计量溯源性'],
            ['6.6', '外部提供的产品和服务'],
            ['7', '过程要求'],
            ['7.1', '要求、标书和合同的评审'],
            ['7.2', '方法的选择、验证和确认'],
            ['7.3', '抽样'],
            ['7.4', '检测和校准物品的处置'],
            ['7.5', '技术记录'],
            ['7.6', '测量不确定度的评定'],
            ['7.7', '确保结果的有效性'],
            ['7.8', '结果报告'],
            ['7.9', '投诉'],
            ['7.10', '不符合工作'],
            ['7.11', '数据控制和信息管理'],
            ['8', '管理体系要求'],
            ['8.1', '方式'],
            ['8.2', '管理体系文件'],
            ['8.3', '管理体系文件的控制'],
            ['8.4', '记录控制'],
            ['8.5', '应对风险和机遇的措施'],
            ['8.6', '改进'],
            ['8.7', '纠正措施'],
            ['8.8', '内部审核'],
            ['8.9', '管理评审'],
        ];

        return array_map(
            static fn (array $row): array => [
                'clause_number' => $row[0],
                'title' => $row[1],
                'locator' => 'fallback:gbt27025-structure',
                'original_text' => $row[0] . ' ' . $row[1],
            ],
            $rows
        );
    }

    private static function sortClauseRows(array $rows): array
    {
        usort(
            $rows,
            static fn (array $left, array $right): int => self::compareClauseNumbers(
                (string)($left['clause_number'] ?? ''),
                (string)($right['clause_number'] ?? '')
            )
        );

        return $rows;
    }

    private static function compareClauseNumbers(string $left, string $right): int
    {
        $leftRank = self::clauseNumberRank($left);
        $rightRank = self::clauseNumberRank($right);
        if ($leftRank['type'] !== $rightRank['type']) {
            return $leftRank['type'] <=> $rightRank['type'];
        }

        $leftParts = $leftRank['parts'];
        $rightParts = $rightRank['parts'];
        $length = max(count($leftParts), count($rightParts));
        for ($index = 0; $index < $length; $index++) {
            $leftPart = $leftParts[$index] ?? -1;
            $rightPart = $rightParts[$index] ?? -1;
            if ($leftPart !== $rightPart) {
                return $leftPart <=> $rightPart;
            }
        }

        return strcmp($left, $right);
    }

    private static function clauseNumberRank(string $number): array
    {
        if ($number === '公告') {
            return [
                'type' => 0,
                'parts' => [0],
            ];
        }

        if (preg_match('/^([0-9]+(?:\.[0-9]+)*)([a-z])\)$/u', $number, $match)) {
            $parts = array_map('intval', explode('.', (string)$match[1]));
            $parts[] = 1000 + ord((string)$match[2]) - ord('a') + 1;

            return [
                'type' => 1,
                'parts' => $parts,
            ];
        }

        if (preg_match('/^([0-9]+(?:\.[0-9]+)*)\*$/u', $number, $match)) {
            return [
                'type' => 1,
                'parts' => array_map('intval', explode('.', (string)$match[1])),
            ];
        }

        if (preg_match('/^[0-9]+(?:\.[0-9]+)*$/u', $number)) {
            return [
                'type' => 1,
                'parts' => array_map('intval', explode('.', $number)),
            ];
        }

        if (preg_match('/^第([一二三四五六七八九十百零〇]+)章/u', $number, $match)) {
            return [
                'type' => 2,
                'parts' => [self::chineseInteger((string)$match[1])],
            ];
        }

        if (preg_match('/^第([一二三四五六七八九十百零〇]+)条/u', $number, $match)) {
            $parts = [self::chineseInteger((string)$match[1])];
            if (preg_match('/^第[一二三四五六七八九十百零〇]+条（([一二三四五六七八九十百零〇]+)）/u', $number, $subMatch)) {
                $parts[] = self::chineseInteger((string)$subMatch[1]);
            }

            return [
                'type' => 3,
                'parts' => $parts,
            ];
        }

        if (preg_match('/^附件([0-9]+)(?:\.([0-9]+(?:\.[0-9]+)*))?$/u', $number, $match)) {
            $parts = [(int)$match[1]];
            if (!empty($match[2])) {
                $parts = array_merge($parts, array_map('intval', explode('.', (string)$match[2])));
            }

            return [
                'type' => 4,
                'parts' => $parts,
            ];
        }

        if (preg_match('/^附录([A-Z])$/u', $number, $match)) {
            return [
                'type' => 5,
                'parts' => [ord((string)$match[1]) - ord('A') + 1],
            ];
        }

        return [
            'type' => 9,
            'parts' => [PHP_INT_MAX],
        ];
    }

    private static function clauseLevel(string $number): int
    {
        $number = self::unstarClauseNumber($number);
        if (preg_match('/^([0-9]+(?:\.[0-9]+)*)([a-z])\)$/u', $number, $match)) {
            return substr_count((string)$match[1], '.') + 2;
        }

        return substr_count($number, '.') + 1;
    }

    private static function clauseSortToken(string $number): string
    {
        $rank = self::clauseNumberRank($number);
        $parts = array_pad($rank['parts'], 8, 0);
        $parts = array_map(static fn (int $part): string => str_pad((string)$part, 4, '0', STR_PAD_LEFT), $parts);

        return (string)$rank['type'] . ':' . implode('.', $parts) . ':' . $number;
    }

    public static function clauseDisplaySortToken(string $number): string
    {
        return self::clauseSortToken($number);
    }

    private static function chineseInteger(string $value): int
    {
        $digits = [
            '零' => 0, '〇' => 0, '一' => 1, '二' => 2, '三' => 3, '四' => 4,
            '五' => 5, '六' => 6, '七' => 7, '八' => 8, '九' => 9,
        ];
        if (isset($digits[$value])) {
            return $digits[$value];
        }
        if (str_contains($value, '百')) {
            [$hundreds, $rest] = array_pad(explode('百', $value, 2), 2, '');
            $hundredValue = $hundreds === '' ? 1 : ($digits[$hundreds] ?? 0);

            return $hundredValue * 100 + ($rest !== '' ? self::chineseInteger($rest) : 0);
        }
        if (str_contains($value, '十')) {
            [$tens, $ones] = array_pad(explode('十', $value, 2), 2, '');
            $tenValue = $tens === '' ? 1 : ($digits[$tens] ?? 0);
            $oneValue = $ones === '' ? 0 : ($digits[$ones] ?? 0);

            return $tenValue * 10 + $oneValue;
        }

        return 0;
    }

    private static function resolveRegisteredSourcePath(string $filePath): ?string
    {
        $filePath = trim($filePath);
        if ($filePath === '') {
            return null;
        }
        if (str_starts_with($filePath, DIRECTORY_SEPARATOR) && is_file($filePath)) {
            return $filePath;
        }

        $candidates = [
            self::projectRoot() . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . ltrim($filePath, DIRECTORY_SEPARATOR),
            dirname(self::projectRoot()) . DIRECTORY_SEPARATOR . ltrim($filePath, DIRECTORY_SEPARATOR),
            self::projectRoot() . DIRECTORY_SEPARATOR . ltrim($filePath, DIRECTORY_SEPARATOR),
        ];
        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    private static function extractPdfText(string $path): string
    {
        $pdftotext = self::findExecutable('pdftotext');
        if ($pdftotext === null) {
            return self::readPdfTextSidecar($path);
        }

        $command = escapeshellcmd($pdftotext) . ' -layout ' . escapeshellarg($path) . ' - 2>/dev/null';
        $output = shell_exec($command);
        if (is_string($output) && self::hasMeaningfulExtractedText($output)) {
            return $output;
        }

        $sidecarText = self::readPdfTextSidecar($path);
        if ($sidecarText !== '') {
            return $sidecarText;
        }

        return is_string($output) ? $output : '';
    }

    private static function hasMeaningfulExtractedText(string $text): bool
    {
        $compact = preg_replace('/[\s\f]+/u', '', $text) ?? '';

        return mb_strlen($compact) >= 80 && (bool)preg_match('/[\p{Han}A-Za-z0-9]/u', $compact);
    }

    private static function readPdfTextSidecar(string $path): string
    {
        $withoutExtension = preg_replace('/\.pdf$/iu', '', $path) ?? $path;
        foreach ([$withoutExtension . '.txt', $path . '.txt'] as $sidecarPath) {
            if (!is_file($sidecarPath)) {
                continue;
            }
            $text = (string)file_get_contents($sidecarPath);
            if (trim($text) !== '') {
                return $text;
            }
        }

        return '';
    }

    private static function extractDocxPlainText(string $path): string
    {
        $tables = self::extractDocxTables($path);
        $lines = [];
        foreach ($tables as $table) {
            foreach ($table as $row) {
                $lines[] = implode(' ', array_filter(array_map('trim', $row), static fn (string $cell): bool => $cell !== ''));
            }
        }

        return implode("\n", $lines);
    }

    private static function cellText(DOMXPath $xpath, DOMElement $cellNode): string
    {
        $pieces = [];
        foreach ($xpath->query('.//w:t', $cellNode) ?: [] as $textNode) {
            $pieces[] = $textNode->nodeValue;
        }

        return trim(preg_replace('/\s+/u', ' ', implode('', $pieces)) ?? implode('', $pieces));
    }

    private static function positionCode(string $name): string
    {
        $map = [
            '实验室主任' => 'lab_director',
            '技术负责人' => 'technical_manager',
            '质量负责人' => 'quality_manager',
            '办公室主任' => 'office_manager',
            '检测室主任' => 'testing_room_manager',
            '样品管理员' => 'sample_manager',
            '授权签字人' => 'authorized_signatory',
            '内审员' => 'internal_auditor',
            '监督员' => 'supervisor',
            '资料管理员' => 'document_controller',
            '设备管理员' => 'equipment_manager',
            '检测人员' => 'testing_staff',
        ];

        return $map[$name] ?? substr(sha1($name), 0, 12);
    }

    private static function cleanSourceTitle(string $title): string
    {
        $title = preg_replace('/\b20\d{2}\b/u', '', $title) ?? $title;
        $title = preg_replace('/\d{1,2}\s*月\s*\d{1,2}\s*日/u', '', $title) ?? $title;
        $title = preg_replace('/\d{1,2}\s*日/u', '', $title) ?? $title;
        $title = preg_replace('/年|实施|发布|新|带附件|第一次修订/u', '', $title) ?? $title;
        $title = trim($title, " \t\n\r\0\x0B-_:：()（）");
        $title = preg_replace('/\s+/u', '', $title) ?? $title;

        if (str_contains($title, '珠宝玉石') && str_contains($title, '贵金属') && str_contains($title, '应用说明')) {
            return '检测和校准实验室能力认可准则在珠宝玉石、贵金属检测领域的应用说明';
        }
        if (str_contains($title, '检测和校准实验室能力认可准则的应用要求')) {
            return '检测和校准实验室能力认可准则的应用要求';
        }
        if (str_contains($title, '检测和校准实验室能力认可准则')) {
            return '检测和校准实验室能力认可准则';
        }
        if (str_contains($title, '检测和校准实验室能力的通用要求')) {
            return '检测和校准实验室能力的通用要求';
        }
        if (str_contains($title, '检验检测机构资质认定评审准则')) {
            return '检验检测机构资质认定评审准则';
        }

        return $title;
    }

    private static function firstYear(string $text): string
    {
        return preg_match('/(20\d{2})/u', $text, $match) ? $match[1] : '';
    }

    private static function findExecutable(string $name): ?string
    {
        foreach (['/opt/homebrew/bin/' . $name, '/usr/local/bin/' . $name, '/usr/bin/' . $name] as $path) {
            if (is_executable($path)) {
                return $path;
            }
        }

        $which = trim((string)shell_exec('command -v ' . escapeshellarg($name)));

        return $which !== '' ? $which : null;
    }

    private static function workspacePath(string $relativePath): string
    {
        return dirname(self::projectRoot()) . DIRECTORY_SEPARATOR . $relativePath;
    }

    private static function projectRoot(): string
    {
        return rtrim(dirname(__DIR__, 2), DIRECTORY_SEPARATOR);
    }
}
