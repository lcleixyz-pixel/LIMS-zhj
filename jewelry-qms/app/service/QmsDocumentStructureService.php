<?php
declare(strict_types=1);

namespace app\service;

use DOMDocument;
use DOMElement;
use DOMXPath;
use app\model\Document;
use app\model\QmsBusinessModule;
use app\model\QmsClause;
use app\model\QmsDocumentAsset;
use app\model\QmsDocumentBlock;
use app\model\QmsDocumentBlockLink;
use app\model\QmsElement;
use app\model\QmsElementClauseLink;
use app\model\QmsManualSection;
use app\model\QmsPosition;
use app\model\QmsSource;
use app\model\QmsStructuredDocument;
use app\model\RecordFormTemplate;
use app\service\RecordFormSchemaService;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use think\facade\Config;
use think\facade\Db;
use think\facade\Session;
use ZipArchive;

class QmsDocumentStructureService
{
    private const SYSTEM_PACKAGE_MANIFEST_RETENTION = 50;

    public static function structureLayerDefinitions(): array
    {
        return [
            [
                'key' => 'external_basis',
                'name' => '外部依据',
                'workflow' => '上传 -> 查新 -> 规范化重命名归档 -> 文本抽取 -> 条款库 -> 要素匹配/新增建议',
            ],
            [
                'key' => 'quality_manual',
                'name' => '质量手册',
                'workflow' => '归档 -> Markdown结构化 -> 要素匹配 -> 程序关联 -> 渲染输出',
            ],
            [
                'key' => 'procedure',
                'name' => '程序文件',
                'workflow' => '现用和参考程序归档 -> Markdown结构化 -> 职责分配 -> 记录表格关联 -> 渲染输出',
            ],
            [
                'key' => 'work_instruction',
                'name' => '作业指导书',
                'workflow' => '三级文件归档 -> Markdown结构化 -> 要素/程序关联 -> 运行要求和记录证据挂接 -> 渲染输出',
            ],
            [
                'key' => 'record_form',
                'name' => '记录表格',
                'workflow' => '程序记录要求 -> schema构建 -> 字段复核 -> 运行记录 -> 证据归档',
            ],
        ];
    }

    public static function manualBlockBlueprints(): array
    {
        $blocks = [];
        foreach (QmsElementService::defaultElementDefinitions() as $definition) {
            $sectionNumber = (string)$definition['primary_clause_number'];
            $name = (string)$definition['name'];
            $blocks[] = [
                'stable_key' => 'manual:' . str_replace('.', '_', $sectionNumber),
                'element_key' => (string)$definition['key'],
                'section_number' => $sectionNumber,
                'title' => $name,
                'block_type' => 'section',
                'sort_order' => (int)$definition['sort_order'],
                'markdown' => "## {$sectionNumber} {$name}\n\n"
                    . "- 体系要素：{$name}\n"
                    . "- 内容来源：现用质量手册章节框架\n"
                    . "- 结构化用途：作为该要素在质量手册中的章节落点，并连接外部条款、程序文件和记录表格。\n",
            ];
        }

        return $blocks;
    }

    public static function procedureBlockBlueprint(array $procedure, array|object $element): array
    {
        $elementName = is_array($element) ? (string)($element['name'] ?? '') : (string)($element->name ?? '');
        $elementKey = is_array($element) ? (string)($element['key'] ?? '') : (string)($element->key ?? '');
        $docNumber = (string)($procedure['doc_number'] ?? '');
        $title = (string)($procedure['title'] ?? '');
        $documentTypeLabel = (string)($procedure['document_type_label'] ?? '程序文件');
        $stablePrefix = (string)($procedure['stable_prefix'] ?? 'procedure');

        return [
            'stable_key' => $stablePrefix . ':' . self::stableToken($docNumber) . ':element:' . self::stableToken($elementKey !== '' ? $elementKey : $elementName),
            'element_key' => $elementKey,
            'title' => '要素落实：' . $elementName,
            'block_type' => 'control_requirement',
            'sort_order' => 200,
            'markdown' => "### 要素落实：{$elementName}\n\n"
                . "- {$documentTypeLabel}：{$docNumber} {$title}\n"
                . "- 结构化重点：从程序正文拆分目的、范围、职责、流程步骤、控制要求和记录要求。\n"
                . "- 记录表格：按程序文件中的记录要求建立或关联 schema，并在运行模块中形成证据。\n",
        ];
    }

    public static function resolveRecordFormSourcePath(array|RecordFormTemplate $template): string
    {
        $sourcePath = is_array($template)
            ? (string)($template['source_file_path'] ?? '')
            : (string)$template->source_file_path;
        if ($sourcePath !== '' && is_file(self::workspacePath($sourcePath))) {
            return $sourcePath;
        }

        $index = self::recordFormSourceIndex();
        foreach (self::recordFormLookupKeys($template) as $key) {
            if (isset($index[$key])) {
                return $index[$key];
            }
        }

        foreach (self::recordFormLookupKeys($template) as $key) {
            if ($key === '' || mb_strlen($key) < 4) {
                continue;
            }
            foreach ($index as $indexedKey => $relativePath) {
                $indexedKey = (string)$indexedKey;
                if (str_contains($indexedKey, $key) || str_contains($key, $indexedKey)) {
                    return $relativePath;
                }
            }
        }

        if ($sourcePath !== '') {
            return $sourcePath;
        }

        $docNumber = is_array($template)
            ? (string)($template['doc_number'] ?? '')
            : (string)$template->doc_number;

        return 'record_form_schema/' . self::safeToken($docNumber !== '' ? $docNumber : 'record-form') . '.json';
    }

    public static function markdownFromSourcePath(string $relativePath, int $maxLines = 120): string
    {
        $lines = self::sourceLinesFromPath($relativePath);

        return self::documentLinesToMarkdown($lines, $maxLines);
    }

    public static function recordFormSchemaMarkdown(array|RecordFormTemplate $template, string $sourceMarkdown = ''): string
    {
        $data = is_array($template) ? $template : [
            'doc_number' => (string)$template->doc_number,
            'name' => (string)$template->name,
            'field_schema' => (string)$template->field_schema,
        ];
        $docNumber = (string)($data['doc_number'] ?? '');
        $name = (string)($data['name'] ?? '');
        $rawSchema = $data['field_schema'] ?? [];
        $schema = is_string($rawSchema)
            ? RecordFormSchemaService::decode($rawSchema)
            : RecordFormSchemaService::normalize(is_array($rawSchema) ? $rawSchema : []);
        $procedureLabel = self::linkedProcedureLabelForRecordForm($template);
        $elementLabel = self::linkedElementLabelForRecordForm($template);

        $markdown = "## 表格schema：{$name}\n\n"
            . "- 表格编号：{$docNumber}\n"
            . "- 关联程序：" . ($procedureLabel !== '' ? $procedureLabel : '未关联程序文件') . "\n"
            . "- 关联要素：" . ($elementLabel !== '' ? $elementLabel : '未关联体系要素') . "\n"
            . "- 字段schema：严格按程序文件记录要求维护。\n";
        if ($schema !== []) {
            $markdown .= "\n### 字段schema\n\n" . self::schemaFieldsToMarkdown($schema) . "\n";
        }
        $profileMarkdown = self::recordFormSchemaRequirementProfileMarkdown($template, $schema);
        if ($profileMarkdown !== '') {
            $markdown .= "\n### schema构建依据\n\n" . $profileMarkdown . "\n";
        }
        $requirementMarkdown = self::recordFormRequirementSourceMarkdown($template);
        if ($requirementMarkdown !== '') {
            $markdown .= "\n### 程序记录要求来源\n\n" . $requirementMarkdown . "\n";
        }
        if ($sourceMarkdown !== '') {
            $markdown .= "\n### 源文件Markdown摘录\n\n" . $sourceMarkdown . "\n";
        }

        return $markdown;
    }

    private static function recordFormSchemaRequirementProfileMarkdown(array|RecordFormTemplate $template, array $schema): string
    {
        $templateId = is_array($template)
            ? (string)($template['id'] ?? '')
            : (string)($template->id ?? '');
        $docNumber = is_array($template)
            ? (string)($template['doc_number'] ?? '')
            : (string)($template->doc_number ?? '');
        $name = is_array($template)
            ? (string)($template['name'] ?? '')
            : (string)($template->name ?? '');

        if ($docNumber === '' && $name === '' && $schema === []) {
            return '';
        }

        $evidenceRows = $templateId !== '' ? self::recordFormRequirementEvidence($templateId) : [];
        $fieldSummaries = self::flattenSchemaFieldSummaries($schema);
        $detailFields = self::filterSchemaFields($fieldSummaries, static function (array $field): bool {
            return (string)$field['type'] === 'repeatable_table'
                || str_contains((string)$field['label'], '明细')
                || str_contains((string)$field['label'], '清单');
        });
        $responsibilityFields = self::filterSchemaFields($fieldSummaries, static function (array $field): bool {
            $label = (string)$field['label'];
            return (string)$field['type'] === 'person'
                || str_contains($label, '责任')
                || str_contains($label, '负责人')
                || str_contains($label, '保管')
                || str_contains($label, '审核')
                || str_contains($label, '批准')
                || str_contains($label, '签字')
                || str_contains($label, '确认')
                || str_contains($label, '核查人员')
                || str_contains($label, '记录人');
        });
        $dateFields = self::filterSchemaFields($fieldSummaries, static function (array $field): bool {
            $label = (string)$field['label'];
            return (string)$field['type'] === 'date'
                || str_contains($label, '日期')
                || str_contains($label, '时间');
        });

        $lines = [];
        $lines[] = '- 程序要求：' . self::recordFormRequirementMatchSummary($evidenceRows, $docNumber, $name);
        $lines[] = '- 明细字段：' . self::schemaFieldSummaryText($detailFields);
        $lines[] = '- 责任/保管字段：' . self::schemaFieldSummaryText($responsibilityFields);
        $lines[] = '- 日期字段：' . self::schemaFieldSummaryText($dateFields);
        $lines[] = '- 频次要求：' . (self::explicitRequirementValue($evidenceRows, 'frequency') ?: '程序记录要求未明确，需人工复核');
        $lines[] = '- 保留期限：' . (self::explicitRequirementValue($evidenceRows, 'retention') ?: '程序记录要求未明确，需人工复核');

        return implode("\n", $lines);
    }

    private static function flattenSchemaFieldSummaries(array $fields): array
    {
        $summaries = [];
        foreach ($fields as $field) {
            if (!is_array($field)) {
                continue;
            }
            $summary = [
                'key' => (string)($field['key'] ?? ''),
                'label' => (string)($field['label'] ?? ''),
                'type' => (string)($field['type'] ?? ''),
            ];
            if ($summary['key'] !== '' || $summary['label'] !== '') {
                $summaries[] = $summary;
            }
            if (!empty($field['columns']) && is_array($field['columns'])) {
                $summaries = array_merge($summaries, self::flattenSchemaFieldSummaries($field['columns']));
            }
        }

        return $summaries;
    }

    private static function filterSchemaFields(array $fields, callable $predicate): array
    {
        $filtered = [];
        foreach ($fields as $field) {
            if (!$predicate($field)) {
                continue;
            }
            $key = (string)($field['key'] ?? '');
            if ($key === '') {
                continue;
            }
            $filtered[$key] = $field;
        }

        return array_values($filtered);
    }

    private static function schemaFieldSummaryText(array $fields): string
    {
        if ($fields === []) {
            return '未在当前schema中识别，需人工复核';
        }

        $parts = [];
        foreach (array_slice($fields, 0, 8) as $field) {
            $parts[] = '`' . (string)$field['key'] . '` ' . (string)$field['label'];
        }
        if (count($fields) > 8) {
            $parts[] = '等' . count($fields) . '项';
        }

        return implode('；', $parts);
    }

    private static function recordFormRequirementMatchSummary(array $rows, string $docNumber, string $name): string
    {
        $needleNumber = self::compactLookupText($docNumber);
        $needleName = self::compactLookupText($name);
        foreach ($rows as $row) {
            $markdown = self::requirementMarkdownWithoutGeneratedHints((string)($row['markdown'] ?? ''));
            $haystack = self::compactLookupText($markdown);
            if (($needleNumber !== '' && str_contains($haystack, $needleNumber))
                || ($needleName !== '' && str_contains($haystack, $needleName))) {
                return '已匹配 ' . trim($docNumber . ' ' . $name);
            }
        }

        return '未在程序记录要求中直接匹配，需人工复核 ' . trim($docNumber . ' ' . $name);
    }

    private static function explicitRequirementValue(array $rows, string $type): string
    {
        foreach ($rows as $row) {
            $markdown = self::requirementMarkdownWithoutGeneratedHints((string)($row['markdown'] ?? ''));
            $value = self::explicitMarkdownRequirementValue($markdown, $type);
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    private static function explicitMarkdownRequirementValue(string $markdown, string $type): string
    {
        foreach (preg_split('/\R/u', $markdown) ?: [] as $line) {
            $line = trim((string)$line);
            if ($line === '') {
                continue;
            }
            if ($type === 'frequency'
                && preg_match('/(?:频次|周期|每年|每月|每季度|每半年|每次|发生时|定期|必要时)[：:，,、\\s]*(.+)?$/u', $line, $match) === 1) {
                return trim($match[0], " \t\n\r\0\x0B-：:，,、");
            }
            if ($type === 'retention'
                && preg_match('/(?:保存期限|保留期限|保存\\s*\\d+\\s*年|保存期|长期保存|永久保存)[：:，,、\\s]*(.+)?$/u', $line, $match) === 1) {
                return trim($match[0], " \t\n\r\0\x0B-：:，,、");
            }
            if ($type === 'responsibility'
                && preg_match('/(?:责任人|负责人|记录人|填写人|保管人|审核人|批准人)[：:，,、\\s]*(.+)?$/u', $line, $match) === 1) {
                return trim($match[0], " \t\n\r\0\x0B-：:，,、");
            }
        }

        return '';
    }

    private static function requirementMarkdownWithoutGeneratedHints(string $markdown): string
    {
        $lines = [];
        foreach (preg_split('/\R/u', str_replace(["\r\n", "\r"], "\n", $markdown)) ?: [] as $line) {
            if (str_contains((string)$line, 'schema来源')) {
                continue;
            }
            $lines[] = (string)$line;
        }

        return implode("\n", $lines);
    }

    private static function recordFormRequirementSourceMarkdown(array|RecordFormTemplate $template): string
    {
        $templateId = is_array($template)
            ? (string)($template['id'] ?? '')
            : (string)($template->id ?? '');
        if ($templateId === '') {
            return '';
        }

        $rows = self::recordFormRequirementEvidence($templateId);
        if ($rows === []) {
            return '';
        }

        $lines = [];
        foreach ($rows as $row) {
            $procedure = trim((string)($row['procedure_number'] ?? '') . ' ' . (string)($row['procedure_title'] ?? ''));
            if ($procedure === '') {
                $procedure = trim((string)($row['doc_number'] ?? '') . ' ' . (string)($row['document_title'] ?? ''));
            }
            $blockTitle = trim((string)($row['block_section_number'] ?? '') . ' ' . (string)($row['block_title'] ?? ''));
            $blockKey = (string)($row['block_stable_key'] ?? '');
            $markdown = trim((string)($row['markdown'] ?? ''));

            $lines[] = '- 程序文件：' . ($procedure !== '' ? $procedure : '未命名程序文件');
            $lines[] = '  - 内容块：' . ($blockTitle !== '' ? $blockTitle : '记录要求');
            if ($blockKey !== '') {
                $lines[] = '  - 稳定键：`' . $blockKey . '`';
            }
            if ($markdown !== '') {
                $lines[] = "  - 要求摘录：\n" . self::indentMarkdownExcerpt($markdown, 4);
            }
        }

        return implode("\n", $lines);
    }

    private static function indentMarkdownExcerpt(string $markdown, int $spaces): string
    {
        $markdown = trim(str_replace(["\r\n", "\r"], "\n", $markdown));
        if (mb_strlen($markdown) > 1200) {
            $markdown = mb_substr($markdown, 0, 1200) . "\n...";
        }

        $indent = str_repeat(' ', $spaces);
        $lines = [];
        foreach (explode("\n", $markdown) as $line) {
            $lines[] = $indent . $line;
        }

        return implode("\n", $lines);
    }

    private static function linkedProcedureLabelForRecordForm(array|RecordFormTemplate $template): string
    {
        $procedureId = is_array($template)
            ? (string)($template['procedure_doc_id'] ?? '')
            : (string)($template->procedure_doc_id ?? '');
        if ($procedureId === '') {
            return '';
        }

        $document = Document::where('id', $procedureId)->where('soft_delete', 0)->find();
        if (!$document) {
            return '';
        }

        return trim((string)$document->doc_number . ' ' . (string)$document->title);
    }

    private static function linkedElementLabelForRecordForm(array|RecordFormTemplate $template): string
    {
        $elementId = is_array($template)
            ? (string)($template['element_id'] ?? '')
            : (string)($template->element_id ?? '');
        if ($elementId === '') {
            return '';
        }

        $element = QmsElement::where('id', $elementId)->where('soft_delete', 0)->find();
        if (!$element) {
            return '';
        }

        return (string)$element->name;
    }

    public static function positionMentionsForMarkdown(string $markdown): array
    {
        $compactMarkdown = self::compactLookupText($markdown);
        $mentions = [];
        foreach (self::positionAliasDefinitions() as $code => $definition) {
            foreach ((array)$definition['aliases'] as $alias) {
                $compactAlias = self::compactLookupText((string)$alias);
                if ($compactAlias === '' || !str_contains($compactMarkdown, $compactAlias)) {
                    continue;
                }
                $mentions[$code] = [
                    'position_code' => $code,
                    'position_name' => (string)$definition['name'],
                    'evidence' => (string)$alias,
                ];
                break;
            }
        }

        return array_values($mentions);
    }

    public static function manualSourceBlockBlueprints(array $manual): array
    {
        $docNumber = (string)($manual['doc_number'] ?? '');
        $filePath = (string)($manual['file_path'] ?? '');
        $lines = self::sourceLinesFromPath($filePath);
        if ($lines === []) {
            return [];
        }

        $definitions = [];
        foreach (self::manualBlockBlueprints() as $blueprint) {
            $definitions[(string)$blueprint['section_number']] = $blueprint;
        }

        $blocks = [];
        $started = false;
        $currentNumber = '';
        $currentTitle = '';
        $currentLines = [];
        $flush = function () use (&$blocks, &$currentNumber, &$currentTitle, &$currentLines, $definitions, $docNumber, $filePath): void {
            if ($currentNumber === '' || !isset($definitions[$currentNumber])) {
                return;
            }
            $body = trim(implode("\n\n", array_filter($currentLines)));
            if ($body === '') {
                return;
            }
            $definition = $definitions[$currentNumber];
            $blocks[] = [
                'stable_key' => (string)$definition['stable_key'],
                'element_key' => (string)$definition['element_key'],
                'section_number' => $currentNumber,
                'title' => $currentTitle !== '' ? $currentTitle : (string)$definition['title'],
                'block_type' => 'section',
                'sort_order' => (int)$definition['sort_order'],
                'source_locator' => $filePath . '#' . $currentNumber,
                'markdown' => "## {$currentNumber} " . ($currentTitle !== '' ? $currentTitle : (string)$definition['title']) . "\n\n" . $body . "\n",
            ];
        };

        foreach ($lines as $rawLine) {
            $line = self::normalizeSourceLine((string)$rawLine);
            if ($line === '') {
                continue;
            }
            if (!$started) {
                $started = (bool)preg_match('/^1\.\s*前言$/u', $line);
                if (!$started) {
                    continue;
                }
            }

            if (preg_match('/^附录\d+[:：]/u', $line)) {
                $flush();
                $currentNumber = '';
                $currentTitle = '';
                $currentLines = [];
                break;
            }

            $heading = self::manualSectionHeading($line);
            if ($heading !== null && isset($definitions[$heading['number']])) {
                $flush();
                $currentNumber = $heading['number'];
                $currentTitle = $heading['title'];
                $currentLines = [];
                continue;
            }

            if ($currentNumber !== '') {
                $currentLines[] = $line;
            }
        }
        $flush();

        return $blocks;
    }

    public static function procedureSourceBlockBlueprints(array $procedure): array
    {
        $docNumber = (string)($procedure['doc_number'] ?? '');
        $filePath = (string)($procedure['file_path'] ?? '');
        $stablePrefix = (string)($procedure['stable_prefix'] ?? 'procedure');
        $markdown = self::markdownFromSourcePath($filePath, 260);
        if ($markdown === '') {
            return [];
        }

        $blocks = [];
        foreach (self::splitMarkdownSections($markdown) as $heading => $body) {
            $section = self::procedureSectionDefinition($heading);
            if ($section === null) {
                continue;
            }
            $blocks[] = [
                'stable_key' => $stablePrefix . ':' . self::stableToken($docNumber) . ':source:' . $section['key'],
                'title' => (string)$heading,
                'block_type' => (string)$section['block_type'],
                'sort_order' => (int)$section['sort_order'],
                'source_locator' => $filePath . '#' . $heading,
                'markdown' => '### ' . (string)$heading . "\n\n" . trim($body) . "\n",
            ];
        }

        return $blocks;
    }

    public static function referenceProcedurePackageBlocks(array $package): array
    {
        $docNumber = (string)($package['doc_number'] ?? '');
        $filePath = (string)($package['file_path'] ?? '');
        $markdown = self::markdownFromSourcePath($filePath, 0);
        if ($markdown === '') {
            return [];
        }

        $lines = array_values(array_filter(
            array_map(static fn (string $line): string => trim($line), preg_split('/\R/u', $markdown) ?: []),
            static fn (string $line): bool => $line !== ''
        ));
        $catalog = self::referenceProcedureCatalog($lines);
        if ($catalog === []) {
            return [];
        }

        $starts = self::referenceProcedureBodyStarts($lines, $catalog);
        $blocks = [];
        foreach ($catalog as $entry) {
            $number = (string)$entry['number'];
            if (!isset($starts[$number])) {
                continue;
            }
            $start = (int)$starts[$number];
            $end = count($lines);
            foreach ($starts as $candidateNumber => $candidateStart) {
                if ($candidateNumber === $number || (int)$candidateStart <= $start) {
                    continue;
                }
                $end = min($end, (int)$candidateStart);
            }
            $bodyLines = array_slice($lines, $start, max(1, $end - $start));
            $bodyLines = self::trimReferenceProcedureBody((string)$entry['title'], $bodyLines);
            $title = $number . ' ' . (string)$entry['title'];
            $stableDocToken = self::stableToken($docNumber !== '' ? $docNumber : 'reference-procedure');
            $blocks[] = [
                'stable_key' => 'reference_procedure:' . $stableDocToken . ':procedure:' . self::stableToken($number),
                'section_number' => $number,
                'title' => $title,
                'block_type' => 'control_requirement',
                'sort_order' => 300 + ((int)$entry['index'] * 10),
                'source_locator' => $filePath . '#' . $number,
                'markdown' => '## ' . $title . "\n\n" . trim(implode("\n\n", $bodyLines)) . "\n",
            ];
        }

        return $blocks;
    }

    public static function referenceProcedureComparisonRows(): array
    {
        $rows = [];
        foreach (QmsPlanningImportService::referenceProcedureDocumentBaselines() as $package) {
            $structured = QmsStructuredDocument::where('document_role', (string)($package['document_role'] ?? 'procedure'))
                ->where('doc_number', (string)($package['doc_number'] ?? ''))
                ->where('version', (string)($package['version'] ?? ''))
                ->where('soft_delete', 0)
                ->find();
            foreach (self::referenceProcedurePackageBlocks($package) as $referenceBlock) {
                $procedure = self::currentProcedureForReferenceTitle((string)$referenceBlock['title']);
                if (!$procedure) {
                    continue;
                }
                $coverage = self::procedureStructureCoverage((string)$procedure['id']);
                $referenceDbBlock = $structured
                    ? QmsDocumentBlock::where('structured_document_id', (string)$structured->id)
                        ->where('stable_key', (string)$referenceBlock['stable_key'])
                        ->where('soft_delete', 0)
                        ->find()
                    : null;
                $expectedLabels = self::referenceProcedureExpectedLabels((string)$referenceBlock['markdown']);
                $coveredLabels = array_values(array_intersect($expectedLabels, $coverage['covered_labels']));
                $rows[] = [
                    'reference_title' => (string)$referenceBlock['title'],
                    'reference_section_number' => (string)$referenceBlock['section_number'],
                    'reference_structured_document_id' => $structured ? (string)$structured->id : null,
                    'reference_block_id' => $referenceDbBlock ? (string)$referenceDbBlock->id : null,
                    'current_procedure_id' => (string)$procedure['id'],
                    'current_procedure_number' => (string)$procedure['doc_number'],
                    'current_procedure_title' => (string)$procedure['title'],
                    'current_structured_document_id' => $coverage['structured_document_id'],
                    'match_source' => (string)($procedure['_match_source'] ?? 'auto'),
                    'manual_match_id' => (string)($procedure['_manual_match_id'] ?? ''),
                    'expected_labels' => $expectedLabels,
                    'covered_labels' => $coveredLabels,
                    'missing_labels' => array_values(array_diff($expectedLabels, $coveredLabels)),
                ];
            }
        }

        return $rows;
    }

    public static function referenceProcedureUnmatchedRows(): array
    {
        $rows = [];
        foreach (QmsPlanningImportService::referenceProcedureDocumentBaselines() as $package) {
            $structured = QmsStructuredDocument::where('document_role', (string)($package['document_role'] ?? 'procedure'))
                ->where('doc_number', (string)($package['doc_number'] ?? ''))
                ->where('version', (string)($package['version'] ?? ''))
                ->where('soft_delete', 0)
                ->find();
            foreach (self::referenceProcedurePackageBlocks($package) as $referenceBlock) {
                if (self::currentProcedureForReferenceTitle((string)$referenceBlock['title'])) {
                    continue;
                }
                $referenceDbBlock = $structured
                    ? QmsDocumentBlock::where('structured_document_id', (string)$structured->id)
                        ->where('stable_key', (string)$referenceBlock['stable_key'])
                        ->where('soft_delete', 0)
                        ->find()
                    : null;
                $rows[] = [
                    'reference_title' => (string)$referenceBlock['title'],
                    'reference_section_number' => (string)$referenceBlock['section_number'],
                    'reference_structured_document_id' => $structured ? (string)$structured->id : null,
                    'reference_block_id' => $referenceDbBlock ? (string)$referenceDbBlock->id : null,
                    'expected_labels' => self::referenceProcedureExpectedLabels((string)$referenceBlock['markdown']),
                ];
            }
        }

        return $rows;
    }

    public static function saveReferenceProcedureManualMatch(string $referenceTitle, string $procedureDocumentId, string $reviewNote): array
    {
        self::ensureReferenceProcedureMatchTable();
        $referenceTitle = trim($referenceTitle);
        $procedureDocumentId = trim($procedureDocumentId);
        $reviewNote = trim($reviewNote);
        if ($referenceTitle === '') {
            throw new \RuntimeException('参考程序不能为空');
        }
        if ($procedureDocumentId === '') {
            throw new \RuntimeException('请选择现用程序文件');
        }
        if ($reviewNote === '') {
            throw new \RuntimeException('复核说明不能为空');
        }

        $procedure = Document::where('id', $procedureDocumentId)
            ->where('level', 2)
            ->where('soft_delete', 0)
            ->field('id,doc_number,title')
            ->find();
        if (!$procedure) {
            throw new \RuntimeException('现用程序文件不存在');
        }

        $reference = self::referenceProcedureBlockForTitle($referenceTitle);
        if ($reference === []) {
            throw new \RuntimeException('参考程序不存在');
        }

        $block = $reference['block'];
        $package = $reference['package'];
        $structured = QmsStructuredDocument::where('document_role', (string)($package['document_role'] ?? 'procedure'))
            ->where('doc_number', (string)($package['doc_number'] ?? ''))
            ->where('version', (string)($package['version'] ?? ''))
            ->where('soft_delete', 0)
            ->find();
        $referenceDbBlock = $structured
            ? QmsDocumentBlock::where('structured_document_id', (string)$structured->id)
                ->where('stable_key', (string)$block['stable_key'])
                ->where('soft_delete', 0)
                ->find()
            : null;

        $now = date('Y-m-d H:i:s');
        Db::name('qms_reference_procedure_matches')
            ->where('reference_doc_number', (string)($package['doc_number'] ?? ''))
            ->where('reference_section_number', (string)$block['section_number'])
            ->where('status', 'active')
            ->where('soft_delete', 0)
            ->update([
                'status' => 'retired',
                'soft_delete' => 1,
                'modified' => $now,
            ]);

        $payload = [
            'id' => qms_uuid(),
            'company_id' => (string)Config::get('qms.company_id'),
            'reference_doc_number' => (string)($package['doc_number'] ?? ''),
            'reference_section_number' => (string)$block['section_number'],
            'reference_title' => (string)$block['title'],
            'reference_block_id' => $referenceDbBlock ? (string)$referenceDbBlock->id : null,
            'procedure_document_id' => (string)$procedure->id,
            'match_source' => 'manual',
            'status' => 'active',
            'review_note' => $reviewNote,
            'created' => $now,
            'modified' => $now,
            'created_by' => Session::get('user.id') ?: null,
            'modified_by' => Session::get('user.id') ?: null,
            'soft_delete' => 0,
        ];
        Db::name('qms_reference_procedure_matches')->insert($payload);

        if ($referenceDbBlock) {
            self::upsertReferenceProcedureSuggestion($referenceDbBlock, $block);
            self::upsertReferenceProcedureComparisonSuggestion($referenceDbBlock, $block);
            self::upsertReferenceProcedureUnmatchedSuggestion($referenceDbBlock, $block);
        }

        return [
            'manual_match' => $payload,
            'comparison_rows' => self::referenceProcedureComparisonRows(),
        ];
    }

    private static function referenceProcedureComparisonsForDocument(QmsStructuredDocument $structured): array
    {
        if ((string)$structured->source_status !== 'reference') {
            return [];
        }

        $structuredId = (string)$structured->id;

        return array_values(array_filter(
            self::referenceProcedureComparisonRows(),
            static fn (array $row): bool => (string)($row['reference_structured_document_id'] ?? '') === $structuredId
        ));
    }

    private static function referenceProcedureUnmatchedForDocument(QmsStructuredDocument $structured): array
    {
        if ((string)$structured->source_status !== 'reference') {
            return [];
        }

        $structuredId = (string)$structured->id;

        return array_values(array_filter(
            self::referenceProcedureUnmatchedRows(),
            static fn (array $row): bool => (string)($row['reference_structured_document_id'] ?? '') === $structuredId
        ));
    }

    private static function documentSuggestionsForStructuredDocument(QmsStructuredDocument $structured): array
    {
        if ((string)$structured->source_status !== 'reference') {
            return [];
        }

        $titles = [];
        foreach (self::referenceProcedureComparisonsForDocument($structured) as $row) {
            $referenceTitle = (string)($row['reference_title'] ?? '');
            if ($referenceTitle === '') {
                continue;
            }
            $titles[] = '对照参考程序：' . $referenceTitle;
            $titles[] = '块级对照参考程序：' . $referenceTitle;
        }
        foreach (self::referenceProcedureUnmatchedForDocument($structured) as $row) {
            $referenceTitle = (string)($row['reference_title'] ?? '');
            if ($referenceTitle !== '') {
                $titles[] = '人工匹配参考程序：' . $referenceTitle;
            }
        }
        $titles = array_values(array_unique($titles));
        if ($titles === []) {
            return [];
        }

        return Db::name('qms_agent_suggestions')
            ->where('suggestion_type', 'document')
            ->where('status', 'open')
            ->whereIn('title', $titles)
            ->order('created', 'desc')
            ->select()
            ->toArray();
    }

    public static function seedAll(): array
    {
        self::ensureDocumentAssetIndexes();
        self::ensureStructuredDocumentIndexes();
        self::ensureChangeLogTable();
        self::ensureReferenceProcedureMatchTable();
        QmsElementService::seedAll();

        $summary = [
            'assets' => 0,
            'structured_documents' => 0,
            'blocks' => 0,
            'links' => 0,
            'rendered' => 0,
        ];

        $externalBasisSummary = self::seedExternalBasisStructures();
        $summary['assets'] += $externalBasisSummary['assets'];
        $summary['structured_documents'] += $externalBasisSummary['structured_documents'];
        $summary['blocks'] += $externalBasisSummary['blocks'];
        $summary['links'] += $externalBasisSummary['links'];
        $summary['rendered'] += $externalBasisSummary['rendered'];
        foreach (QmsPlanningImportService::buildInternalDocumentBaselines() as $row) {
            $document = Document::where('doc_number', (string)$row['doc_number'])->where('soft_delete', 0)->find();
            if (!$document) {
                continue;
            }
            $role = self::documentRoleForLevel((int)$row['document_level']);
            $asset = self::upsertAsset($role, $row, $document);
            $summary['assets']++;
            $structured = self::upsertStructuredDocument($role, $row, $document, $asset);
            $summary['structured_documents']++;
            $blockSummary = $role === 'quality_manual'
                ? self::seedManualBlocks($structured, $document, $row)
                : self::seedProcedureBlocks(
                    $structured,
                    $document,
                    array_merge($row, [
                        'document_type_label' => self::documentRoleLabel($role),
                        'stable_prefix' => self::documentRoleStablePrefix($role),
                    ]),
                    $role
                );
            $summary['blocks'] += $blockSummary['blocks'];
            $summary['links'] += $blockSummary['links'];
            if (self::renderStructuredDocument($structured)) {
                $summary['rendered']++;
            }
        }
        $referenceProcedureSummary = self::seedReferenceProcedureStructures();
        $summary['assets'] += $referenceProcedureSummary['assets'];
        $summary['structured_documents'] += $referenceProcedureSummary['structured_documents'];
        $summary['blocks'] += $referenceProcedureSummary['blocks'];
        $summary['links'] += $referenceProcedureSummary['links'];
        $summary['rendered'] += $referenceProcedureSummary['rendered'];

        $recordSummary = self::seedRecordFormStructures();
        $summary['assets'] += $recordSummary['assets'];
        $summary['structured_documents'] += $recordSummary['structured_documents'];
        $summary['blocks'] += $recordSummary['blocks'];
        $summary['links'] += $recordSummary['links'];
        $summary['rendered'] += $recordSummary['rendered'];

        $summary['assets'] = QmsDocumentAsset::where('soft_delete', 0)->count();
        $summary['structured_documents'] = QmsStructuredDocument::where('soft_delete', 0)->count();
        $summary['blocks'] = QmsDocumentBlock::where('soft_delete', 0)->count();
        $summary['links'] = QmsDocumentBlockLink::where('soft_delete', 0)->count();
        $summary['rendered'] = QmsStructuredDocument::where('soft_delete', 0)->where('render_status', 'rendered')->count();

        return $summary;
    }

    public static function structuredDocumentRows(): array
    {
        $rows = [];
        foreach (QmsStructuredDocument::where('soft_delete', 0)->order('document_role', 'asc')->order('doc_number', 'asc')->select() as $structured) {
            $asset = $structured->source_asset_id
                ? QmsDocumentAsset::where('id', (string)$structured->source_asset_id)->where('soft_delete', 0)->find()
                : null;
            $blockIds = QmsDocumentBlock::where('structured_document_id', (string)$structured->id)->where('soft_delete', 0)->column('id');
            $rows[] = [
                'document' => $structured,
                'asset' => $asset,
                'block_count' => count($blockIds),
                'link_count' => $blockIds === [] ? 0 : QmsDocumentBlockLink::whereIn('block_id', $blockIds)->where('soft_delete', 0)->count(),
            ];
        }

        return $rows;
    }

    public static function controlledDocumentStructureCoverage(): array
    {
        $byLevel = [];
        foreach ([1 => '质量手册', 2 => '程序文件', 3 => '作业指导书'] as $level => $label) {
            $byLevel[$level] = [
                'level' => $level,
                'label' => $label,
                'total_documents' => 0,
                'structured_documents' => 0,
                'missing_documents' => 0,
            ];
        }

        $missingRows = [];
        $total = 0;
        $structuredCount = 0;
        foreach (Document::where('soft_delete', 0)
            ->whereIn('level', [1, 2, 3])
            ->order('level', 'asc')
            ->order('doc_number', 'asc')
            ->select() as $document) {
            $level = (int)$document->level;
            if (!isset($byLevel[$level])) {
                continue;
            }
            $total++;
            $byLevel[$level]['total_documents']++;

            $structured = QmsStructuredDocument::where('document_id', (string)$document->id)
                ->where('soft_delete', 0)
                ->order('modified', 'desc')
                ->find();
            if ($structured) {
                $structuredCount++;
                $byLevel[$level]['structured_documents']++;
                continue;
            }

            $byLevel[$level]['missing_documents']++;
            $filePath = (string)$document->file_path;
            $missingRows[] = [
                'document_id' => (string)$document->id,
                'level' => $level,
                'level_label' => (string)$byLevel[$level]['label'],
                'doc_number' => (string)$document->doc_number,
                'title' => (string)$document->title,
                'version' => (string)$document->version,
                'source_file_path' => $filePath,
                'source_file_status' => $filePath === ''
                    ? 'not_set'
                    : (is_file(self::workspacePath($filePath)) ? 'available' : 'missing'),
                'document_url' => '/document/view?id=' . (string)$document->id,
                'seed_url' => '/planning/structures/seed',
            ];
        }

        return [
            'total_documents' => $total,
            'structured_documents' => $structuredCount,
            'missing_documents' => $total - $structuredCount,
            'coverage_percent' => $total > 0 ? round($structuredCount * 100 / $total, 1) : 100.0,
            'by_level' => $byLevel,
            'missing_rows' => $missingRows,
        ];
    }

    public static function procedureRecordRequirementCoverage(): array
    {
        $rows = [];
        $covered = 0;
        foreach (Document::where('soft_delete', 0)
            ->where('level', 2)
            ->order('doc_number', 'asc')
            ->select() as $document) {
            $structured = QmsStructuredDocument::where('document_id', (string)$document->id)
                ->where('document_role', 'procedure')
                ->where('soft_delete', 0)
                ->order('modified', 'desc')
                ->find();
            $recordBlockIds = $structured
                ? QmsDocumentBlock::where('structured_document_id', (string)$structured->id)
                    ->where('block_type', 'record_requirement')
                    ->where('soft_delete', 0)
                    ->column('id')
                : [];
            $recordFormIds = $recordBlockIds === []
                ? []
                : QmsDocumentBlockLink::whereIn('block_id', $recordBlockIds)
                    ->whereNotNull('record_form_template_id')
                    ->where('soft_delete', 0)
                    ->distinct(true)
                    ->column('record_form_template_id');
            $recordFormIds = array_values(array_filter(array_unique(array_map('strval', $recordFormIds))));
            $schemaCount = self::recordFormSchemaDocumentCount($recordFormIds);
            $gapReasons = [];
            if (!$structured) {
                $gapReasons[] = '未生成结构化文档';
            }
            if (count($recordBlockIds) === 0) {
                $gapReasons[] = '未识别记录要求块';
            }
            if (count($recordBlockIds) > 0 && count($recordFormIds) === 0) {
                $gapReasons[] = '记录要求未关联记录表格';
            }
            if (count($recordFormIds) > 0 && $schemaCount < count($recordFormIds)) {
                $gapReasons[] = 'schema文档未覆盖全部关联表格';
            }

            $status = $gapReasons === [] ? 'covered' : 'gap';
            if ($status === 'covered') {
                $covered++;
            }
            $rows[] = [
                'document_id' => (string)$document->id,
                'doc_number' => (string)$document->doc_number,
                'title' => (string)$document->title,
                'version' => (string)$document->version,
                'structured_document_id' => $structured ? (string)$structured->id : '',
                'structure_url' => $structured ? '/planning/structures/view?id=' . (string)$structured->id : '',
                'record_requirement_blocks' => count($recordBlockIds),
                'linked_record_forms' => count($recordFormIds),
                'record_form_schema_documents' => $schemaCount,
                'coverage_status' => $status,
                'gap_text' => $gapReasons === [] ? '已覆盖' : implode('；', $gapReasons),
            ];
        }

        $total = count($rows);

        return [
            'total_procedures' => $total,
            'covered_procedures' => $covered,
            'gap_procedures' => $total - $covered,
            'coverage_percent' => $total > 0 ? round($covered * 100 / $total, 1) : 100.0,
            'rows' => $rows,
            'gap_rows' => array_values(array_filter($rows, static fn (array $row): bool => (string)$row['coverage_status'] === 'gap')),
        ];
    }

    public static function recordRequirementSchemaCoverage(): array
    {
        $rows = [];
        $covered = 0;
        $linked = 0;
        $blocks = Db::table('qms_document_blocks')
            ->alias('b')
            ->join('qms_structured_documents sd', 'sd.id = b.structured_document_id')
            ->leftJoin('documents d', 'd.id = b.document_id')
            ->where('b.soft_delete', 0)
            ->where('sd.soft_delete', 0)
            ->where('sd.document_role', 'procedure')
            ->where('b.block_type', 'record_requirement')
            ->field('b.id block_id,b.title block_title,b.section_number,b.markdown,b.stable_key,b.sort_order,b.structured_document_id,sd.doc_number,sd.title document_title,sd.version,d.id document_id,d.title procedure_title')
            ->order('sd.doc_number', 'asc')
            ->order('b.sort_order', 'asc')
            ->order('b.title', 'asc')
            ->select()
            ->toArray();

        foreach ($blocks as $block) {
            $blockId = (string)$block['block_id'];
            $forms = self::recordFormsForRequirementBlock($blockId);
            $formIds = array_values(array_filter(array_unique(array_map(static fn (array $form): string => (string)$form['id'], $forms))));
            $schemaDocuments = self::recordFormSchemaDocumentCount($formIds);
            $fieldCount = 0;
            $schemaIssues = [];
            foreach ($forms as $form) {
                try {
                    $fieldCount += count(self::flattenSchemaFieldSummaries(
                        RecordFormSchemaService::decode((string)($form['field_schema'] ?? '[]'))
                    ));
                } catch (\Throwable $exception) {
                    $schemaIssues[] = '字段schema解析失败';
                }
            }

            $gapReasons = [];
            if ($forms === []) {
                $gapReasons[] = '记录要求未关联记录表格';
            } else {
                $linked++;
                if ($schemaDocuments < count($formIds)) {
                    $gapReasons[] = 'schema文档缺失';
                }
                if ($fieldCount === 0) {
                    $gapReasons[] = '字段schema为空';
                }
            }
            $gapReasons = array_values(array_unique(array_merge($gapReasons, $schemaIssues)));
            $status = $gapReasons === [] ? 'covered' : 'gap';
            if ($status === 'covered') {
                $covered++;
            }

            $rows[] = [
                'block_id' => $blockId,
                'structured_document_id' => (string)$block['structured_document_id'],
                'document_id' => (string)($block['document_id'] ?? ''),
                'doc_number' => (string)$block['doc_number'],
                'document_title' => (string)$block['document_title'],
                'procedure_title' => (string)($block['procedure_title'] ?? $block['document_title']),
                'version' => (string)($block['version'] ?? ''),
                'block_title' => (string)$block['block_title'],
                'block_section_number' => (string)($block['section_number'] ?? ''),
                'block_stable_key' => (string)$block['stable_key'],
                'linked_record_forms' => count($formIds),
                'record_form_labels' => self::recordFormLabels($forms),
                'record_form_targets' => self::recordFormTargetRows($forms, $blockId),
                'record_form_edit_urls' => self::recordFormEditUrlText($forms, $blockId),
                'schema_documents' => $schemaDocuments,
                'schema_field_count' => $fieldCount,
                'coverage_status' => $status,
                'gap_text' => $gapReasons === [] ? '已覆盖' : implode('；', $gapReasons),
                'structure_url' => '/planning/structures/view?id=' . (string)$block['structured_document_id'],
                'trace_review_url' => '/planning/structures/links/review?block_id=' . $blockId,
            ];
        }

        $total = count($rows);

        return [
            'total_requirement_blocks' => $total,
            'linked_requirement_blocks' => $linked,
            'schema_covered_blocks' => $covered,
            'gap_blocks' => $total - $covered,
            'coverage_percent' => $total > 0 ? round($covered * 100 / $total, 1) : 100.0,
            'rows' => $rows,
            'gap_rows' => array_values(array_filter($rows, static fn (array $row): bool => (string)$row['coverage_status'] === 'gap')),
        ];
    }

    public static function recordRequirementSchemaDraftForBlock(string $blockId): array
    {
        $blockId = trim($blockId);
        if ($blockId === '') {
            return [];
        }
        $block = QmsDocumentBlock::where('id', $blockId)
            ->where('block_type', 'record_requirement')
            ->where('soft_delete', 0)
            ->find();
        if (!$block) {
            return [];
        }

        $markdown = (string)$block->markdown;
        $retention = self::explicitMarkdownRequirementValue($markdown, 'retention');
        $frequency = self::explicitMarkdownRequirementValue($markdown, 'frequency');
        $forms = self::recordFormsForRequirementBlock($blockId);
        $recordLabel = self::recordFormLabels($forms);

        $draft = [
            [
                'key' => 'record_date',
                'label' => '记录日期',
                'type' => 'date',
                'required' => true,
                'help_text' => '按程序记录要求填写记录形成日期。',
            ],
            [
                'key' => 'responsible_person',
                'label' => '责任人',
                'type' => 'person',
                'required' => true,
                'help_text' => '由人工按程序职责确认填写、审核或保管责任。',
            ],
            [
                'key' => 'record_summary',
                'label' => '记录内容摘要',
                'type' => 'textarea',
                'required' => false,
                'help_text' => $recordLabel !== '-' ? '来源记录表格：' . $recordLabel : '按程序记录要求补充记录内容。',
            ],
            [
                'key' => 'retention_period',
                'label' => '保存期限',
                'type' => 'text',
                'required' => false,
                'default' => $retention,
                'help_text' => $retention !== '' ? '由程序记录要求识别，需人工复核。' : '程序记录要求未明确时由人工确认。',
            ],
        ];
        if ($frequency !== '') {
            $draft[] = [
                'key' => 'record_frequency',
                'label' => '记录频次',
                'type' => 'text',
                'required' => false,
                'default' => $frequency,
                'help_text' => '由程序记录要求识别，需人工复核。',
            ];
        }

        return RecordFormSchemaService::normalize($draft);
    }

    public static function recordRequirementSchemaFieldChecklistForBlock(string $blockId): array
    {
        $blockId = trim($blockId);
        if ($blockId === '') {
            return [];
        }
        $block = QmsDocumentBlock::where('id', $blockId)
            ->where('block_type', 'record_requirement')
            ->where('soft_delete', 0)
            ->find();
        if (!$block) {
            return [];
        }

        $markdown = (string)$block->markdown;
        $forms = self::recordFormsForRequirementBlock($blockId);
        $recordLabel = self::recordFormLabels($forms);
        $responsibility = self::explicitMarkdownRequirementValue($markdown, 'responsibility');
        $retention = self::explicitMarkdownRequirementValue($markdown, 'retention');
        $frequency = self::explicitMarkdownRequirementValue($markdown, 'frequency');

        $sourceText = [
            'record_date' => '默认字段：记录形成日期，需人工确认是否满足程序记录要求。',
            'responsible_person' => $responsibility !== '' ? $responsibility : '程序记录要求未明确责任人，需人工复核。',
            'record_summary' => $recordLabel !== '-' ? '记录表格：' . $recordLabel : '程序记录要求未明确记录表格，需人工复核。',
            'retention_period' => $retention !== '' ? $retention : '程序记录要求未明确保存期限，需人工复核。',
            'record_frequency' => $frequency !== '' ? $frequency : '程序记录要求未明确记录频次，需人工复核。',
        ];
        $reviewHints = [
            'record_date' => '确认记录日期是否应细分为填写、审核或批准日期。',
            'responsible_person' => '确认责任人是否需要拆分为填写人、审核人、批准人或保管人。',
            'record_summary' => '确认摘要字段是否需要替换为具体业务明细字段。',
            'retention_period' => '确认保存期限与程序、法规或客户要求一致。',
            'record_frequency' => '确认频次是否需要进入表单字段或仅作为执行规则。',
        ];

        $rows = [];
        foreach (self::recordRequirementSchemaDraftForBlock($blockId) as $field) {
            $key = (string)($field['key'] ?? '');
            if ($key === '') {
                continue;
            }
            $rows[] = [
                'field_key' => $key,
                'field_label' => (string)($field['label'] ?? ''),
                'field_type' => (string)($field['type'] ?? ''),
                'required_label' => !empty($field['required']) ? '必填' : '选填',
                'source_text' => $sourceText[$key] ?? (string)($field['help_text'] ?? '需人工复核程序记录要求。'),
                'review_hint' => $reviewHints[$key] ?? '按程序记录要求人工确认。',
            ];
        }

        return $rows;
    }

    public static function structureExternalBasisSource(QmsSource|string $source): array
    {
        $source = $source instanceof QmsSource
            ? $source
            : QmsSource::where('id', $source)->where('soft_delete', 0)->find();
        if (!$source) {
            throw new \RuntimeException('外部依据不存在，无法结构化');
        }

        $row = [
            'file_path' => (string)$source->attachment_file_path,
            'file_name' => (string)$source->attachment_file_name,
            'file_type' => strtolower((string)pathinfo((string)$source->attachment_file_name, PATHINFO_EXTENSION)),
            'doc_number' => (string)$source->source_code,
            'title' => (string)$source->name,
            'version' => (string)$source->version,
            'source_note' => '外部依据登记文件，条款内容进入 qms_clauses。',
        ];
        $asset = self::upsertAsset('external_basis', $row, null, $source);
        $structured = self::upsertStructuredDocument('external_basis', $row, null, $asset);
        $blockSummary = self::seedExternalBasisBlocks($structured, $source);
        $rendered = self::renderStructuredDocument($structured);
        $structured = QmsStructuredDocument::where('id', (string)$structured->id)->find() ?: $structured;

        return [
            'assets' => 1,
            'structured_documents' => 1,
            'blocks' => (int)$blockSummary['blocks'],
            'links' => (int)$blockSummary['links'],
            'rendered' => $rendered ? 1 : 0,
            'asset_id' => (string)$asset->id,
            'structured_document_id' => (string)$structured->id,
            'rendered_file_path' => (string)$structured->rendered_file_path,
            'render_status' => (string)$structured->render_status,
        ];
    }

    public static function refreshExternalBasisFreshness(QmsSource|string $source, array $oldFreshness = [], array $newFreshness = []): array
    {
        self::ensureChangeLogTable();
        $source = $source instanceof QmsSource
            ? $source
            : QmsSource::where('id', $source)->where('soft_delete', 0)->find();
        if (!$source) {
            throw new \RuntimeException('外部依据不存在，无法刷新查新结构');
        }

        $previous = QmsStructuredDocument::where('document_role', 'external_basis')
            ->where('doc_number', (string)$source->source_code)
            ->where('version', (string)$source->version)
            ->where('soft_delete', 0)
            ->find();
        $oldRenderedContent = $previous ? self::renderedMarkdownForPackage($previous) : '';
        $statusFrom = $previous ? (string)$previous->status : 'structured';

        $summary = self::structureExternalBasisSource($source);
        $structured = QmsStructuredDocument::where('id', (string)$summary['structured_document_id'])
            ->where('soft_delete', 0)
            ->find();
        if (!$structured) {
            throw new \RuntimeException('外部依据结构化文件刷新后无法载入');
        }

        $newRenderedContent = self::renderedMarkdownForPackage($structured);
        $archiveSummary = self::structuredDocumentRenderArchiveSummary($structured);
        self::createSourceRefreshChangeLog(
            $structured,
            self::externalBasisFreshnessRevisionNote($source, $oldFreshness, $newFreshness),
            $oldRenderedContent,
            $newRenderedContent,
            $statusFrom,
            (string)$structured->status,
            $archiveSummary
        );

        return array_merge($summary, [
            'change_type' => 'version_update',
            'revision_note' => self::externalBasisFreshnessRevisionNote($source, $oldFreshness, $newFreshness),
        ]);
    }

    public static function controlledDocumentStructureSummary(string $documentId): array
    {
        $documentId = trim($documentId);
        if ($documentId === '') {
            return [];
        }
        $structured = QmsStructuredDocument::where('document_id', $documentId)
            ->where('soft_delete', 0)
            ->order('modified', 'desc')
            ->find();
        if (!$structured) {
            return [];
        }
        $blockIds = QmsDocumentBlock::where('structured_document_id', (string)$structured->id)
            ->where('soft_delete', 0)
            ->column('id');
        $linkCount = $blockIds === []
            ? 0
            : QmsDocumentBlockLink::whereIn('block_id', $blockIds)->where('soft_delete', 0)->count();

        return [
            'structured_document_id' => (string)$structured->id,
            'document_role' => (string)$structured->document_role,
            'doc_number' => (string)$structured->doc_number,
            'title' => (string)$structured->title,
            'version' => (string)$structured->version,
            'status' => (string)$structured->status,
            'render_status' => (string)$structured->render_status,
            'rendered_file_path' => (string)$structured->rendered_file_path,
            'markdown_path' => (string)$structured->markdown_path,
            'block_count' => count($blockIds),
            'link_count' => (int)$linkCount,
            'view_url' => '/planning/structures/view?id=' . (string)$structured->id,
            'can_refresh' => in_array((string)$structured->document_role, ['quality_manual', 'procedure', 'work_instruction'], true),
        ];
    }

    public static function refreshControlledDocumentStructure(string $documentId, string $refreshNote): array
    {
        $summary = self::controlledDocumentStructureSummary($documentId);
        if ($summary === []) {
            throw new \RuntimeException('该受控文件尚未生成结构化文档');
        }

        return self::refreshStructuredDocumentFromSource((string)$summary['structured_document_id'], $refreshNote);
    }

    public static function structuredDocumentDetail(string $id): array
    {
        $structured = QmsStructuredDocument::where('id', $id)->where('soft_delete', 0)->find();
        if (!$structured) {
            return [];
        }
        $blocks = [];
        foreach (QmsDocumentBlock::where('structured_document_id', $id)->where('soft_delete', 0)->order('sort_order', 'asc')->select() as $block) {
            $blocks[] = [
                'block' => $block,
                'links' => self::linksForBlock((string)$block->id),
            ];
        }

        return [
            'document' => $structured,
            'asset' => $structured->source_asset_id ? QmsDocumentAsset::where('id', (string)$structured->source_asset_id)->find() : null,
            'blocks' => $blocks,
            'render_archive' => self::structuredDocumentRenderArchiveSummary($structured),
            'change_logs' => self::changeLogsForStructuredDocument((string)$structured->id),
            'reference_procedure_comparisons' => self::referenceProcedureComparisonsForDocument($structured),
            'reference_procedure_unmatched' => self::referenceProcedureUnmatchedForDocument($structured),
            'manual_match_options' => self::referenceProcedureManualMatchOptions(),
            'document_suggestions' => self::documentSuggestionsForStructuredDocument($structured),
        ];
    }

    public static function blockEditDetail(string $blockId): array
    {
        $block = QmsDocumentBlock::where('id', $blockId)->where('soft_delete', 0)->find();
        if (!$block) {
            return [];
        }
        $structured = QmsStructuredDocument::where('id', (string)$block->structured_document_id)->where('soft_delete', 0)->find();
        if (!$structured) {
            return [];
        }

        return [
            'document' => $structured,
            'block' => $block,
            'links' => self::linksForBlock((string)$block->id),
            'render_archive' => self::structuredDocumentRenderArchiveSummary($structured),
            'change_logs' => self::changeLogsForStructuredDocument((string)$structured->id),
        ];
    }

    public static function blockTraceReviewDetail(string $blockId): array
    {
        $block = QmsDocumentBlock::where('id', $blockId)->where('soft_delete', 0)->find();
        if (!$block) {
            return [];
        }
        $structured = QmsStructuredDocument::where('id', (string)$block->structured_document_id)->where('soft_delete', 0)->find();
        if (!$structured) {
            return [];
        }

        return [
            'document' => $structured->toArray(),
            'block' => $block->toArray(),
            'links' => self::linksForBlock((string)$block->id),
            'options' => self::traceReviewOptions(),
            'change_logs' => self::changeLogsForStructuredDocument((string)$structured->id),
        ];
    }

    public static function recordFormRequirementEvidence(string $templateId): array
    {
        $template = RecordFormTemplate::where('id', $templateId)->where('soft_delete', 0)->find();
        if (!$template) {
            return [];
        }

        $query = Db::table('qms_document_block_links')
            ->alias('l')
            ->join('qms_document_blocks b', 'b.id = l.block_id')
            ->join('qms_structured_documents sd', 'sd.id = b.structured_document_id')
            ->leftJoin('qms_elements e', 'e.id = l.element_id')
            ->leftJoin('documents pd', 'pd.id = l.procedure_document_id')
            ->leftJoin('qms_business_modules m', 'm.id = l.business_module_id')
            ->where('l.record_form_template_id', $templateId)
            ->where('l.soft_delete', 0)
            ->where('b.soft_delete', 0)
            ->where('sd.soft_delete', 0)
            ->where('sd.document_role', 'procedure')
            ->where('b.block_type', 'record_requirement')
            ->where('l.relation_type', 'requires_record')
            ->field('l.id,l.block_id,l.element_id,l.procedure_document_id,l.record_form_template_id,l.business_module_id,l.relation_type,l.confidence,l.note,b.structured_document_id,b.title block_title,b.block_type,b.section_number block_section_number,b.markdown,b.stable_key block_stable_key,sd.document_role,sd.doc_number,sd.title document_title,sd.status document_status,e.name element_name,pd.doc_number procedure_number,pd.title procedure_title,m.code module_code,m.name module_name,m.url module_url')
            ->order('sd.doc_number', 'asc')
            ->order('b.sort_order', 'asc')
            ->order('b.title', 'asc');
        if ((string)($template->procedure_doc_id ?? '') !== '') {
            $query->where('l.procedure_document_id', (string)$template->procedure_doc_id);
        }

        $rows = $query->select()
            ->toArray();

        return array_map(static function (array $row): array {
            $blockId = (string)$row['block_id'];
            $structuredDocumentId = (string)$row['structured_document_id'];

            return array_merge($row, [
                'document_url' => '/planning/structures/view?id=' . $structuredDocumentId,
                'review_url' => '/planning/structures/links/review?block_id=' . $blockId,
                'schema_source_note' => self::schemaSourceNoteFromLinkNote((string)($row['note'] ?? '')),
            ]);
        }, $rows);
    }

    public static function schemaSourceNoteFromLinkNote(string $note): string
    {
        $note = trim($note);
        if ($note === '') {
            return '';
        }
        foreach (preg_split('/\\R/u', $note) ?: [] as $line) {
            $line = trim((string)$line);
            if (str_starts_with($line, '字段schema来源：')) {
                return $line;
            }
        }

        return '';
    }

    public static function markRecordFormSchemaDraftApplied(string $templateId, string $blockId, string $suggestionId = ''): array
    {
        $templateId = trim($templateId);
        $blockId = trim($blockId);
        $suggestionId = trim($suggestionId);
        if ($templateId === '' || $blockId === '') {
            throw new \RuntimeException('记录表格或记录要求块参数缺失');
        }

        $template = RecordFormTemplate::where('id', $templateId)->where('soft_delete', 0)->find();
        if (!$template) {
            throw new \RuntimeException('记录表格模板不存在');
        }

        $links = QmsDocumentBlockLink::where('block_id', $blockId)
            ->where('record_form_template_id', $templateId)
            ->where('relation_type', 'requires_record')
            ->where('soft_delete', 0)
            ->select();
        if (count($links) === 0) {
            throw new \RuntimeException('记录表格与记录要求块未建立正式追溯关系');
        }

        $traceLine = '字段schema来源：候选schema草稿已人工保存；来源记录要求块：' . $blockId;
        if ($suggestionId !== '') {
            $traceLine .= '；智能体建议：' . $suggestionId;
        }

        $updated = 0;
        foreach ($links as $link) {
            $note = trim((string)$link->note);
            if (str_contains($note, $traceLine)) {
                continue;
            }
            $link->note = $note === '' ? $traceLine : $note . "\n" . $traceLine;
            $link->save();
            $updated++;
        }

        return [
            'template_id' => $templateId,
            'block_id' => $blockId,
            'suggestion_id' => $suggestionId,
            'updated_links' => $updated,
            'trace_note' => $traceLine,
        ];
    }

    public static function markRecordSchemaDraftFieldReview(string $templateId, string $blockId, array $fieldReviews, string $suggestionId = ''): array
    {
        $templateId = trim($templateId);
        $blockId = trim($blockId);
        $suggestionId = trim($suggestionId);
        if ($templateId === '' || $blockId === '') {
            throw new \RuntimeException('记录表格或记录要求块参数缺失');
        }
        if ($fieldReviews === []) {
            throw new \RuntimeException('请至少提交一项字段复核意见');
        }

        $template = RecordFormTemplate::where('id', $templateId)->where('soft_delete', 0)->find();
        if (!$template) {
            throw new \RuntimeException('记录表格模板不存在');
        }

        $links = QmsDocumentBlockLink::where('block_id', $blockId)
            ->where('record_form_template_id', $templateId)
            ->where('relation_type', 'requires_record')
            ->where('soft_delete', 0)
            ->select();
        if (count($links) === 0) {
            throw new \RuntimeException('记录表格与记录要求块未建立正式追溯关系');
        }

        $reviewParts = [];
        foreach ($fieldReviews as $review) {
            $part = (string)$review['field_key'] . '=' . (string)$review['status_label'];
            $note = trim((string)($review['note'] ?? ''));
            if ($note !== '') {
                $part .= '（' . $note . '）';
            }
            $reviewParts[] = $part;
        }

        $traceLine = '字段schema复核：' . implode('；', $reviewParts) . '；来源记录要求块：' . $blockId;
        if ($suggestionId !== '') {
            $traceLine .= '；智能体建议：' . $suggestionId;
        }

        $updated = 0;
        foreach ($links as $link) {
            $note = trim((string)$link->note);
            if (str_contains($note, $traceLine)) {
                continue;
            }
            $link->note = $note === '' ? $traceLine : $note . "\n" . $traceLine;
            $link->save();
            $updated++;
        }

        return [
            'template_id' => $templateId,
            'block_id' => $blockId,
            'suggestion_id' => $suggestionId,
            'reviewed_fields' => count($fieldReviews),
            'updated_links' => $updated,
            'review_note' => $traceLine,
        ];
    }

    public static function upsertBlockTraceLink(string $blockId, array $data): array
    {
        self::ensureChangeLogTable();
        $block = QmsDocumentBlock::where('id', $blockId)->where('soft_delete', 0)->find();
        if (!$block) {
            throw new \RuntimeException('结构化内容块不存在');
        }
        $structured = QmsStructuredDocument::where('id', (string)$block->structured_document_id)->where('soft_delete', 0)->find();
        if (!$structured) {
            throw new \RuntimeException('结构化文件不存在');
        }

        $payload = self::normalizeTraceLinkPayload($data);
        if ((string)$payload['note'] === '') {
            throw new \RuntimeException('复核说明不能为空');
        }
        if (!self::tracePayloadHasTarget($payload)) {
            throw new \RuntimeException('至少选择一个追溯对象');
        }

        $linkId = (string)($data['link_id'] ?? '');
        $link = $linkId !== ''
            ? QmsDocumentBlockLink::where('id', $linkId)->where('block_id', $blockId)->find()
            : null;
        if (!$link) {
            $link = self::findMatchingTraceLink($blockId, $payload) ?: new QmsDocumentBlockLink();
            if (empty($link->id)) {
                $link->id = qms_uuid();
            }
        }
        $link->save(array_merge($payload, [
            'block_id' => $blockId,
            'publish' => 1,
            'soft_delete' => 0,
        ]));

        $saved = QmsDocumentBlockLink::where('id', (string)$link->id)->find();
        self::createTraceReviewLog(
            $structured,
            $block,
            (string)$payload['note'],
            'trace-link: ' . self::traceLinkSummary($saved ? $saved->toArray() : array_merge($payload, ['id' => (string)$link->id])),
            (string)$structured->status,
            (string)$structured->status
        );

        return [
            'link' => $saved ? $saved->toArray() : [],
            'detail' => self::blockTraceReviewDetail($blockId),
        ];
    }

    public static function deleteBlockTraceLink(string $linkId, string $reviewNote): array
    {
        self::ensureChangeLogTable();
        $reviewNote = trim($reviewNote);
        if ($reviewNote === '') {
            throw new \RuntimeException('复核说明不能为空');
        }
        $link = QmsDocumentBlockLink::where('id', $linkId)->where('soft_delete', 0)->find();
        if (!$link) {
            throw new \RuntimeException('追溯关系不存在');
        }
        $block = QmsDocumentBlock::where('id', (string)$link->block_id)->where('soft_delete', 0)->find();
        if (!$block) {
            throw new \RuntimeException('结构化内容块不存在');
        }
        $structured = QmsStructuredDocument::where('id', (string)$block->structured_document_id)->where('soft_delete', 0)->find();
        if (!$structured) {
            throw new \RuntimeException('结构化文件不存在');
        }
        $summary = self::traceLinkSummary($link->toArray());
        $link->save(['soft_delete' => 1]);
        self::createTraceReviewLog(
            $structured,
            $block,
            $reviewNote,
            'trace-link-deleted: ' . $summary,
            (string)$structured->status,
            (string)$structured->status
        );

        return [
            'deleted_link_id' => $linkId,
            'detail' => self::blockTraceReviewDetail((string)$block->id),
        ];
    }

    public static function updateBlockMarkdown(string $blockId, string $markdown, string $revisionNote = ''): array
    {
        self::ensureChangeLogTable();
        $block = QmsDocumentBlock::where('id', $blockId)->where('soft_delete', 0)->find();
        if (!$block) {
            throw new \RuntimeException('结构化内容块不存在');
        }
        $markdown = str_replace(["\r\n", "\r"], "\n", $markdown);
        if (trim($markdown) === '') {
            throw new \RuntimeException('Markdown 内容不能为空');
        }
        $revisionNote = trim($revisionNote);
        if ($revisionNote === '') {
            throw new \RuntimeException('修订说明不能为空');
        }
        $structured = QmsStructuredDocument::where('id', (string)$block->structured_document_id)->where('soft_delete', 0)->find();
        if (!$structured) {
            throw new \RuntimeException('结构化文件不存在');
        }

        $oldMarkdown = (string)$block->markdown;
        $oldStatus = (string)$structured->status;
        $block->save([
            'markdown' => $markdown,
            'status' => 'effective',
        ]);
        self::renderStructuredDocument($structured);

        $reloadedStructured = QmsStructuredDocument::where('id', (string)$structured->id)->where('soft_delete', 0)->find();
        if (!$reloadedStructured) {
            throw new \RuntimeException('结构化文件重新载入失败');
        }
        $archiveSummary = self::structuredDocumentRenderArchiveSummary($reloadedStructured);
        $reviewNote = self::appendReviewNote((string)$reloadedStructured->review_note, $revisionNote, '结构化内容块修订');
        $reloadedStructured->save([
            'status' => 'draft',
            'review_note' => $reviewNote,
        ]);
        self::createChangeLog(
            $reloadedStructured,
            $block,
            $oldMarkdown,
            $markdown,
            $revisionNote,
            $oldStatus,
            'draft',
            $archiveSummary
        );

        $reloadedStructured = QmsStructuredDocument::where('id', (string)$structured->id)->where('soft_delete', 0)->find();
        $reloadedBlock = QmsDocumentBlock::where('id', $blockId)->where('soft_delete', 0)->find();

        return [
            'structured_document' => $reloadedStructured ? $reloadedStructured->toArray() : [],
            'block' => $reloadedBlock ? $reloadedBlock->toArray() : [],
            'render_archive' => $reloadedStructured ? self::structuredDocumentRenderArchiveSummary($reloadedStructured) : [],
            'change_logs' => $reloadedStructured ? self::changeLogsForStructuredDocument((string)$reloadedStructured->id) : [],
        ];
    }

    public static function publishStructuredDocument(string $structuredDocumentId, string $publishNote): array
    {
        self::ensureChangeLogTable();
        $publishNote = trim($publishNote);
        if ($publishNote === '') {
            throw new \RuntimeException('发布说明不能为空');
        }
        $structured = QmsStructuredDocument::where('id', $structuredDocumentId)->where('soft_delete', 0)->find();
        if (!$structured) {
            throw new \RuntimeException('结构化文件不存在');
        }
        if ((string)$structured->status === 'obsolete') {
            throw new \RuntimeException('已作废结构化文件不能发布');
        }

        $oldStatus = (string)$structured->status;
        self::renderStructuredDocument($structured);
        $reloaded = QmsStructuredDocument::where('id', $structuredDocumentId)->where('soft_delete', 0)->find();
        if (!$reloaded) {
            throw new \RuntimeException('结构化文件重新载入失败');
        }
        $archiveSummary = self::structuredDocumentRenderArchiveSummary($reloaded);
        $reloaded->save([
            'status' => 'published',
            'review_note' => self::appendReviewNote((string)$reloaded->review_note, $publishNote, '结构化文件发布复核'),
        ]);
        self::createDocumentStatusChangeLog($reloaded, $publishNote, $oldStatus, 'published', $archiveSummary);

        $published = QmsStructuredDocument::where('id', $structuredDocumentId)->where('soft_delete', 0)->find();

        return [
            'structured_document' => $published ? $published->toArray() : [],
            'render_archive' => $published ? self::structuredDocumentRenderArchiveSummary($published) : [],
            'change_logs' => $published ? self::changeLogsForStructuredDocument((string)$published->id) : [],
        ];
    }

    public static function refreshStructuredDocumentFromSource(string $structuredDocumentId, string $refreshNote): array
    {
        self::ensureChangeLogTable();
        $refreshNote = trim($refreshNote);
        if ($refreshNote === '') {
            throw new \RuntimeException('重建说明不能为空');
        }
        $structured = QmsStructuredDocument::where('id', $structuredDocumentId)->where('soft_delete', 0)->find();
        if (!$structured) {
            throw new \RuntimeException('结构化文件不存在');
        }
        $role = (string)$structured->document_role;
        if (!in_array($role, ['quality_manual', 'procedure', 'work_instruction'], true)) {
            throw new \RuntimeException('当前文件类型不支持从内部源文件重建');
        }
        $document = $structured->document_id
            ? Document::where('id', (string)$structured->document_id)->where('soft_delete', 0)->find()
            : null;
        if (!$document) {
            throw new \RuntimeException('结构化文件未关联受控内部文件');
        }

        $sourcePath = (string)$document->file_path;
        if ($sourcePath === '' || !is_file(self::workspacePath($sourcePath))) {
            throw new \RuntimeException('受控源文件不存在，无法重建结构');
        }

        $oldStatus = (string)$structured->status;
        $oldRenderedContent = self::renderedMarkdownForPackage($structured);
        $linkSnapshot = self::snapshotStructuredDocumentLinks((string)$structured->id);
        $row = [
            'document_level' => (int)$document->level,
            'document_role' => $role,
            'doc_number' => (string)$document->doc_number,
            'title' => (string)$document->title,
            'version' => (string)$document->version,
            'file_path' => $sourcePath,
            'file_name' => (string)($document->file_name ?: basename($sourcePath)),
            'file_type' => (string)($document->file_type ?: strtolower((string)pathinfo($sourcePath, PATHINFO_EXTENSION))),
            'source_note' => '从受控内部文件重新结构化生成，需人工复核后发布。',
            'document_type_label' => self::documentRoleLabel($role),
            'stable_prefix' => self::documentRoleStablePrefix($role),
        ];
        $asset = self::upsertAsset($role, $row, $document);
        $structured->save(['source_asset_id' => (string)$asset->id]);

        $summary = $role === 'quality_manual'
            ? self::seedManualBlocks($structured, $document, $row)
            : self::seedProcedureBlocks($structured, $document, $row, $role);
        self::restoreStructuredDocumentLinks((string)$structured->id, $linkSnapshot);

        self::renderStructuredDocument($structured);
        $reloaded = QmsStructuredDocument::where('id', $structuredDocumentId)->where('soft_delete', 0)->find();
        if (!$reloaded) {
            throw new \RuntimeException('结构化文件重新载入失败');
        }
        $archiveSummary = self::structuredDocumentRenderArchiveSummary($reloaded);
        $newRenderedContent = self::renderedMarkdownForPackage($reloaded);
        $reloaded->save([
            'status' => 'draft',
            'review_note' => self::appendReviewNote((string)$reloaded->review_note, $refreshNote, '源文件重新结构化'),
        ]);
        self::createSourceRefreshChangeLog(
            $reloaded,
            $refreshNote,
            $oldRenderedContent,
            $newRenderedContent,
            $oldStatus,
            'draft',
            $archiveSummary
        );

        $refreshed = QmsStructuredDocument::where('id', $structuredDocumentId)->where('soft_delete', 0)->find();
        $blockCount = QmsDocumentBlock::where('structured_document_id', $structuredDocumentId)->where('soft_delete', 0)->count();
        $blockIds = QmsDocumentBlock::where('structured_document_id', $structuredDocumentId)->where('soft_delete', 0)->column('id');
        $linkCount = $blockIds === []
            ? 0
            : QmsDocumentBlockLink::whereIn('block_id', $blockIds)->where('soft_delete', 0)->count();

        return [
            'structured_document' => $refreshed ? $refreshed->toArray() : [],
            'blocks' => (int)$blockCount,
            'links' => (int)$linkCount,
            'rebuilt_blocks' => (int)($summary['blocks'] ?? 0),
            'rebuilt_links' => (int)($summary['links'] ?? 0),
            'render_archive' => $refreshed ? self::structuredDocumentRenderArchiveSummary($refreshed) : [],
            'change_logs' => $refreshed ? self::changeLogsForStructuredDocument((string)$refreshed->id) : [],
        ];
    }

    public static function systemPackageSummary(): array
    {
        $outputPath = self::systemPackageOutputPath();
        $absolutePath = self::appPath($outputPath);
        $manifestPath = self::systemPackageArchiveManifestPath();
        $manifest = self::systemPackageArchiveManifest();
        $latestArchive = $manifest !== [] ? (array)end($manifest) : [];
        $latestArchivePath = (string)($latestArchive['archive_path'] ?? '');

        return [
            'output_path' => $outputPath,
            'exists' => is_file($absolutePath),
            'updated_at' => is_file($absolutePath) ? date('Y-m-d H:i:s', (int)filemtime($absolutePath)) : '',
            'manifest_path' => $manifestPath,
            'archive_path' => $latestArchivePath,
            'latest_archive_path' => $latestArchivePath,
            'latest_archive_at' => (string)($latestArchive['generated_at'] ?? ''),
            'latest_content_sha256' => (string)($latestArchive['content_sha256'] ?? ''),
            'latest_package_version' => (int)($latestArchive['package_version'] ?? 0),
            'latest_change_impact_count' => (int)($latestArchive['change_impact_count'] ?? 0),
            'latest_block_trace_count' => (int)($latestArchive['block_trace_count'] ?? 0),
            'archive_count' => (int)($latestArchive['package_version'] ?? count($manifest)),
            'document_count_by_role' => self::structuredDocumentCountsByRole(),
            'total_documents' => self::packageEligibleStructuredDocumentQuery()->count(),
        ];
    }

    public static function latestSystemPackageChangeImpactRows(): array
    {
        $manifest = self::systemPackageArchiveManifest();
        if ($manifest === []) {
            return [];
        }

        $latestArchive = (array)end($manifest);
        $rows = $latestArchive['change_impact_inventory'] ?? [];
        if (!is_array($rows)) {
            return [];
        }

        return self::normalizePackageChangeImpactRows($rows);
    }

    public static function latestSystemPackageBlockTraceRows(): array
    {
        $manifest = self::systemPackageArchiveManifest();
        if ($manifest === []) {
            return [];
        }

        $latestArchive = (array)end($manifest);
        $rows = $latestArchive['block_trace_inventory'] ?? [];
        if (!is_array($rows)) {
            return [];
        }

        return self::normalizePackageBlockTraceRows($rows);
    }

    public static function renderSystemPackage(): array
    {
        $outputPath = self::systemPackageOutputPath();
        $absolutePath = self::appPath($outputPath);
        $dir = dirname($absolutePath);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $counts = self::structuredDocumentCountsByRole();
        $generatedAt = date('Y-m-d H:i:s');
        $manifest = self::systemPackageArchiveManifest();
        $previousArchive = $manifest !== [] ? (array)end($manifest) : [];
        $previousPackageVersion = (int)($previousArchive['package_version'] ?? 0);
        $packageVersion = ($previousPackageVersion > 0 ? $previousPackageVersion : count($manifest)) + 1;
        $changeImpactInventory = self::systemPackageChangeImpactInventory($previousArchive, $generatedAt);
        $blockTraceInventory = self::systemPackageBlockTraceInventory();
        $content = "# 实验室质量管理体系文件组合包\n\n"
            . "- 生成时间：" . $generatedAt . "\n"
            . "- 包版本：" . $packageVersion . "\n"
            . "- 来源：已结构化并渲染的外部依据、质量手册、程序文件、作业指导书和记录表格 Markdown。\n"
            . "- 组合规则：保留每份文件的原始编号、标题、结构化内容和块级追溯文本。\n\n";
        $content .= self::systemPackageTraceabilityIndexMarkdown();
        $content .= self::systemPackageBlockTraceIndexMarkdown($blockTraceInventory);
        $content .= self::systemPackageChangeImpactMarkdown($changeImpactInventory);

        foreach (self::systemPackageRoleDefinitions() as $role => $label) {
            $documents = self::packageEligibleStructuredDocumentQuery()
                ->where('document_role', $role)
                ->order('doc_number', 'asc')
                ->order('title', 'asc')
                ->select();
            $content .= "## {$label}\n\n";
            $content .= "- 文档数量：" . (int)($counts[$role] ?? 0) . "\n\n";
            if (count($documents) === 0) {
                $content .= "_暂无结构化文档。_\n\n";
                continue;
            }

            foreach ($documents as $document) {
                $content .= "### " . trim((string)$document->doc_number . ' ' . (string)$document->title) . "\n\n";
                $content .= "- 版本：" . ((string)$document->version !== '' ? (string)$document->version : '-') . "\n";
                $content .= "- 渲染文件：" . ((string)$document->rendered_file_path !== '' ? (string)$document->rendered_file_path : '-') . "\n\n";
                $markdown = self::renderedMarkdownForPackage($document);
                $content .= trim($markdown !== '' ? $markdown : '（暂无渲染内容）') . "\n\n";
                $content .= "---\n\n";
            }
        }

        file_put_contents($absolutePath, $content);
        self::archiveSystemPackage($content, $counts, $generatedAt, $outputPath, $packageVersion, $changeImpactInventory, $blockTraceInventory);

        return self::systemPackageSummary();
    }

    private static function systemPackageTraceabilityIndexMarkdown(): string
    {
        $rows = QmsElementService::traceabilityMatrix();
        if ($rows === []) {
            return "## 条款级追溯索引\n\n_暂无追溯矩阵数据。_\n\n";
        }

        $markdown = "## 条款级追溯索引\n\n"
            . "| 无编号要素 | 主外部条款 | 手册章节 | 程序文件 | 记录表格 | 运行模块 | 岗位职责 | 缺口 |\n"
            . "| --- | --- | ---: | ---: | ---: | ---: | ---: | --- |\n";

        foreach ($rows as $row) {
            $element = $row['element'] ?? null;
            $elementName = is_object($element) ? (string)($element->name ?? '') : (string)($element['name'] ?? '');
            $markdown .= '| '
                . self::markdownTableCell($elementName) . ' | '
                . self::markdownTableCell((string)($row['primary_clause'] ?? '')) . ' | '
                . (int)($row['manual_section_count'] ?? 0) . ' | '
                . (int)($row['document_count'] ?? 0) . ' | '
                . (int)($row['record_form_count'] ?? 0) . ' | '
                . (int)($row['module_count'] ?? 0) . ' | '
                . (int)($row['responsibility_count'] ?? 0) . ' | '
                . self::markdownTableCell((string)($row['gap_text'] ?? '') ?: '完整') . " |\n";
        }

        return $markdown . "\n";
    }

    private static function systemPackageBlockTraceIndexMarkdown(array $inventory): string
    {
        $markdown = "## 内容块级追溯索引\n\n";
        if ($inventory === []) {
            return $markdown . "_暂无内容块追溯数据。_\n\n";
        }

        $markdown .= "| 文件 | 内容块 | 块类型 | 要素 | 条款 | 手册章节 | 程序文件 | 记录表格 | 运行模块 | 岗位 | 复核状态 |\n"
            . "| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |\n";
        foreach ($inventory as $row) {
            $document = trim((string)($row['doc_number'] ?? '') . ' ' . (string)($row['document_title'] ?? ''));
            $block = trim((string)($row['block_section_number'] ?? '') . ' ' . (string)($row['block_title'] ?? ''));
            $stableKey = (string)($row['block_stable_key'] ?? '');
            $targets = (array)($row['trace_targets'] ?? []);
            $markdown .= '| '
                . self::markdownTableCell($document !== '' ? $document : '-') . ' | '
                . self::markdownTableCell(trim($block . ' ' . $stableKey)) . ' | '
                . self::markdownTableCell((string)($row['block_type'] ?? '')) . ' | '
                . self::markdownTableCell(implode('、', (array)($targets['elements'] ?? []))) . ' | '
                . self::markdownTableCell(implode('、', (array)($targets['clauses'] ?? []))) . ' | '
                . self::markdownTableCell(implode('、', (array)($targets['manual_sections'] ?? []))) . ' | '
                . self::markdownTableCell(implode('、', (array)($targets['procedures'] ?? []))) . ' | '
                . self::markdownTableCell(implode('、', (array)($targets['record_forms'] ?? []))) . ' | '
                . self::markdownTableCell(implode('、', (array)($targets['modules'] ?? []))) . ' | '
                . self::markdownTableCell(implode('、', (array)($targets['positions'] ?? []))) . ' | '
                . self::markdownTableCell((string)($row['review_status'] ?? '')) . " |\n";
        }

        return $markdown . "\n";
    }

    private static function systemPackageChangeImpactMarkdown(array $inventory): string
    {
        $markdown = "## 组合包变更影响清单\n\n";
        if ($inventory === []) {
            return $markdown . "_本次组合包未识别到自上一版以来纳入的结构化变更。_\n\n";
        }

        $markdown .= "| 时间 | 文件 | 内容块 | 类型 | 状态 | 修订说明 | 影响追溯 |\n"
            . "| --- | --- | --- | --- | --- | --- | --- |\n";
        foreach ($inventory as $row) {
            $document = trim((string)($row['doc_number'] ?? '') . ' ' . (string)($row['document_title'] ?? ''));
            $block = trim((string)($row['block_title'] ?? '') . ' ' . (string)($row['block_stable_key'] ?? ''));
            $markdown .= '| '
                . self::markdownTableCell((string)($row['created'] ?? '')) . ' | '
                . self::markdownTableCell($document !== '' ? $document : '-') . ' | '
                . self::markdownTableCell($block !== '' ? $block : '-') . ' | '
                . self::markdownTableCell((string)($row['change_type'] ?? '')) . ' | '
                . self::markdownTableCell(trim((string)($row['status_from'] ?? '') . ' -> ' . (string)($row['status_to'] ?? ''), ' ->')) . ' | '
                . self::markdownTableCell((string)($row['revision_note'] ?? '')) . ' | '
                . self::markdownTableCell(self::changeImpactTraceSummary((array)($row['trace_snapshot'] ?? []))) . " |\n";
        }

        return $markdown . "\n";
    }

    private static function markdownTableCell(string $value): string
    {
        $value = trim(str_replace(["\r", "\n"], ' ', $value));
        if ($value === '') {
            return '-';
        }

        return str_replace('|', '\\|', $value);
    }

    private static function systemPackageRoleDefinitions(): array
    {
        return [
            'external_basis' => '外部依据',
            'quality_manual' => '质量手册',
            'procedure' => '程序文件',
            'work_instruction' => '作业指导书',
            'record_form' => '记录表格',
        ];
    }

    private static function documentRoleForLevel(int $level): string
    {
        return match ($level) {
            1 => 'quality_manual',
            3 => 'work_instruction',
            default => 'procedure',
        };
    }

    private static function documentRoleLabel(string $role): string
    {
        return self::systemPackageRoleDefinitions()[$role] ?? '程序文件';
    }

    private static function documentRoleStablePrefix(string $role): string
    {
        return $role === 'work_instruction' ? 'work_instruction' : 'procedure';
    }

    private static function structuredDocumentCountsByRole(): array
    {
        $counts = array_fill_keys(array_keys(self::systemPackageRoleDefinitions()), 0);
        foreach (self::packageEligibleStructuredDocumentQuery()->group('document_role')->field('document_role,count(*) count')->select() as $row) {
            $counts[(string)$row['document_role']] = (int)$row['count'];
        }

        return $counts;
    }

    private static function packageEligibleStructuredDocumentQuery()
    {
        return QmsStructuredDocument::where('soft_delete', 0)
            ->whereIn('status', ['structured', 'published']);
    }

    private static function renderedMarkdownForPackage(QmsStructuredDocument $document): string
    {
        $path = (string)$document->rendered_file_path;
        if ($path === '') {
            return '';
        }

        $absolutePath = self::appPath($path);
        if (!is_file($absolutePath)) {
            return '';
        }

        return (string)file_get_contents($absolutePath);
    }

    private static function sourceForPackageInventory(QmsStructuredDocument $document, ?QmsDocumentAsset $asset): ?QmsSource
    {
        if ((string)$document->document_role !== 'external_basis') {
            return null;
        }

        if ($asset && (string)$asset->source_id !== '') {
            $source = QmsSource::where('id', (string)$asset->source_id)->where('soft_delete', 0)->find();
            if ($source) {
                return $source;
            }
        }

        $docNumber = (string)$document->doc_number;
        if ($docNumber === '') {
            return null;
        }

        return QmsSource::where('source_code', $docNumber)->where('soft_delete', 0)->find();
    }

    private static function sourceFreshnessInventory(?QmsSource $source): array
    {
        return [
            'freshness_checked_at' => $source ? (string)$source->freshness_checked_at : '',
            'freshness_result' => $source ? (string)$source->freshness_result : '',
            'freshness_evidence' => $source ? (string)$source->freshness_evidence : '',
            'next_freshness_due' => $source ? (string)$source->next_freshness_due : '',
            'freshness_status' => $source ? (string)$source->freshness_status : '',
        ];
    }

    private static function recordFormSchemaDocumentCount(array $recordFormTemplateIds): int
    {
        $recordFormTemplateIds = array_values(array_filter(array_unique(array_map('strval', $recordFormTemplateIds))));
        if ($recordFormTemplateIds === []) {
            return 0;
        }

        return (int)Db::name('qms_structured_documents')
            ->alias('sd')
            ->join('qms_document_assets a', 'a.id = sd.source_asset_id')
            ->where('sd.document_role', 'record_form')
            ->where('sd.soft_delete', 0)
            ->where('a.soft_delete', 0)
            ->whereIn('a.record_form_template_id', $recordFormTemplateIds)
            ->distinct(true)
            ->count('a.record_form_template_id');
    }

    private static function recordFormsForRequirementBlock(string $blockId): array
    {
        if ($blockId === '') {
            return [];
        }

        return Db::table('qms_document_block_links')
            ->alias('l')
            ->join('record_form_templates rft', 'rft.id = l.record_form_template_id')
            ->where('l.block_id', $blockId)
            ->where('l.relation_type', 'requires_record')
            ->where('l.soft_delete', 0)
            ->where('rft.soft_delete', 0)
            ->whereNotNull('l.record_form_template_id')
            ->field('rft.id,rft.doc_number,rft.name,rft.field_schema,rft.status,rft.review_status')
            ->order('rft.doc_number', 'asc')
            ->order('rft.name', 'asc')
            ->select()
            ->toArray();
    }

    private static function recordFormLabels(array $forms): string
    {
        if ($forms === []) {
            return '-';
        }

        $labels = [];
        foreach ($forms as $form) {
            $labels[] = trim((string)($form['doc_number'] ?? '') . ' ' . (string)($form['name'] ?? ''));
        }

        return implode('；', array_values(array_filter(array_unique($labels))));
    }

    private static function recordFormTargetRows(array $forms, string $schemaDraftBlockId = ''): array
    {
        $rows = [];
        foreach ($forms as $form) {
            $id = (string)($form['id'] ?? '');
            if ($id === '') {
                continue;
            }
            $label = trim((string)($form['doc_number'] ?? '') . ' ' . (string)($form['name'] ?? ''));
            $editUrl = '/record_form_template/edit?id=' . $id;
            if ($schemaDraftBlockId !== '') {
                $editUrl .= '&schema_draft_block_id=' . rawurlencode($schemaDraftBlockId);
            }
            $rows[] = [
                'id' => $id,
                'label' => $label !== '' ? $label : $id,
                'edit_url' => $editUrl,
                'view_url' => '/record_form_template/view?id=' . $id,
            ];
        }

        return $rows;
    }

    private static function recordFormEditUrlText(array $forms, string $schemaDraftBlockId = ''): string
    {
        $urls = [];
        foreach (self::recordFormTargetRows($forms, $schemaDraftBlockId) as $row) {
            $urls[] = (string)$row['edit_url'];
        }

        return $urls === [] ? '-' : implode('，', array_values(array_unique($urls)));
    }

    private static function systemPackageDocumentInventory(): array
    {
        $inventory = [];
        foreach (self::systemPackageRoleDefinitions() as $role => $label) {
            $documents = self::packageEligibleStructuredDocumentQuery()
                ->where('document_role', $role)
                ->order('doc_number', 'asc')
                ->order('title', 'asc')
                ->select();
            foreach ($documents as $document) {
                $renderedMarkdown = self::renderedMarkdownForPackage($document);
                $structuredDocumentId = (string)$document->id;
                $asset = (string)$document->source_asset_id !== ''
                    ? QmsDocumentAsset::where('id', (string)$document->source_asset_id)->where('soft_delete', 0)->find()
                    : null;
                $source = self::sourceForPackageInventory($document, $asset);
                $inventory[] = array_merge([
                    'structured_document_id' => $structuredDocumentId,
                    'document_role' => (string)$document->document_role,
                    'doc_number' => (string)$document->doc_number,
                    'title' => (string)$document->title,
                    'version' => (string)$document->version,
                    'status' => (string)$document->status,
                    'render_status' => (string)$document->render_status,
                    'rendered_file_path' => (string)$document->rendered_file_path,
                    'content_sha256' => $renderedMarkdown !== '' ? hash('sha256', $renderedMarkdown) : '',
                    'block_count' => QmsDocumentBlock::where('structured_document_id', $structuredDocumentId)
                        ->where('soft_delete', 0)
                        ->count(),
                    'source_asset_id' => $asset ? (string)$asset->id : '',
                    'source_kind' => $asset ? (string)$asset->source_kind : '',
                    'source_original_name' => $asset ? (string)$asset->original_name : '',
                    'source_original_path' => $asset ? (string)$asset->original_path : '',
                    'source_normalized_name' => $asset ? (string)$asset->normalized_name : '',
                    'source_archived_path' => $asset ? (string)$asset->archived_path : '',
                    'source_archive_status' => $asset ? (string)$asset->archive_status : '',
                    'source_file_sha256' => $asset ? (string)$asset->file_sha256 : '',
                ], self::sourceFreshnessInventory($source));
            }
        }

        return $inventory;
    }

    private static function systemPackageBlockTraceInventory(): array
    {
        $rows = Db::table('qms_document_blocks')
            ->alias('b')
            ->join('qms_structured_documents sd', 'sd.id = b.structured_document_id')
            ->where('b.soft_delete', 0)
            ->where('sd.soft_delete', 0)
            ->whereIn('sd.status', ['structured', 'published'])
            ->whereIn('sd.document_role', array_keys(self::systemPackageRoleDefinitions()))
            ->field('b.id block_id,b.stable_key block_stable_key,b.section_number block_section_number,b.title block_title,b.block_type,b.source_locator,b.sort_order,sd.id structured_document_id,sd.document_role,sd.doc_number,sd.title document_title,sd.version document_version,sd.status document_status')
            ->order('sd.document_role', 'asc')
            ->order('sd.doc_number', 'asc')
            ->order('b.sort_order', 'asc')
            ->order('b.title', 'asc')
            ->select()
            ->toArray();

        $inventory = [];
        foreach ($rows as $row) {
            $links = self::linksForBlock((string)$row['block_id']);
            $traceSnapshot = ['links' => $links];
            $targets = self::changeImpactTraceTargets($traceSnapshot);
            $inventory[] = [
                'structured_document_id' => (string)$row['structured_document_id'],
                'document_role' => (string)$row['document_role'],
                'doc_number' => (string)$row['doc_number'],
                'document_title' => (string)$row['document_title'],
                'document_version' => (string)$row['document_version'],
                'document_status' => (string)$row['document_status'],
                'block_id' => (string)$row['block_id'],
                'block_stable_key' => (string)$row['block_stable_key'],
                'block_section_number' => (string)$row['block_section_number'],
                'block_title' => (string)$row['block_title'],
                'block_type' => (string)$row['block_type'],
                'source_locator' => (string)$row['source_locator'],
                'link_count' => count($links),
                'review_status' => count($links) > 0 ? '已关联' : '待复核',
                'trace_targets' => $targets,
                'trace_summary' => self::changeImpactTraceSummary($traceSnapshot),
                'document_url' => '/planning/structures/view?id=' . (string)$row['structured_document_id'],
                'block_edit_url' => '/planning/structures/blocks/edit?id=' . (string)$row['block_id'],
                'trace_review_url' => '/planning/structures/links/review?block_id=' . (string)$row['block_id'],
            ];
        }

        return $inventory;
    }

    private static function systemPackageChangeImpactInventory(array $previousArchive, string $generatedAt): array
    {
        self::ensureChangeLogTable();
        $query = Db::table('qms_document_change_logs')
            ->alias('l')
            ->join('qms_structured_documents sd', 'sd.id = l.structured_document_id')
            ->leftJoin('qms_document_blocks b', 'b.id = l.block_id')
            ->where('l.soft_delete', 0)
            ->where('sd.soft_delete', 0)
            ->whereIn('sd.status', ['structured', 'published'])
            ->where('l.created', '<=', $generatedAt)
            ->field('l.id,l.change_type,l.revision_note,l.old_markdown_sha256,l.new_markdown_sha256,l.rendered_file_path,l.archive_path,l.trace_snapshot_json,l.status_from,l.status_to,l.created,sd.id structured_document_id,sd.document_role,sd.doc_number,sd.title document_title,sd.version document_version,b.id block_id,b.title block_title,b.stable_key block_stable_key');
        $previousGeneratedAt = (string)($previousArchive['generated_at'] ?? '');
        if ($previousGeneratedAt !== '') {
            $query->where('l.created', '>=', $previousGeneratedAt);
        }

        $rows = $query->order('l.created', 'asc')
            ->order('l.id', 'asc')
            ->limit(200)
            ->select()
            ->toArray();

        return self::normalizePackageChangeImpactRows($rows);
    }

    private static function normalizePackageChangeImpactRows(array $rows): array
    {
        foreach ($rows as &$row) {
            if (!is_array($row)) {
                $row = [];
            }
            $row['trace_snapshot'] = self::decodeTraceSnapshot((string)($row['trace_snapshot_json'] ?? ''));
            $structuredId = (string)($row['structured_document_id'] ?? ($row['trace_snapshot']['structured_document']['id'] ?? ''));
            $blockId = (string)($row['block_id'] ?? ($row['trace_snapshot']['block']['id'] ?? ''));
            $row['document_url'] = $structuredId !== '' ? '/planning/structures/view?id=' . $structuredId : '';
            $row['block_edit_url'] = $blockId !== '' ? '/planning/structures/blocks/edit?id=' . $blockId : '';
            $row['trace_review_url'] = $blockId !== '' ? '/planning/structures/links/review?block_id=' . $blockId : '';
            $row['trace_targets'] = self::changeImpactTraceTargets((array)$row['trace_snapshot']);
            $row['trace_summary'] = self::changeImpactTraceSummary((array)$row['trace_snapshot']);
        }
        unset($row);

        return $rows;
    }

    private static function normalizePackageBlockTraceRows(array $rows): array
    {
        foreach ($rows as &$row) {
            if (!is_array($row)) {
                $row = [];
            }
            $structuredId = (string)($row['structured_document_id'] ?? '');
            $blockId = (string)($row['block_id'] ?? '');
            $row['document_url'] = (string)($row['document_url'] ?? ($structuredId !== '' ? '/planning/structures/view?id=' . $structuredId : ''));
            $row['block_edit_url'] = (string)($row['block_edit_url'] ?? ($blockId !== '' ? '/planning/structures/blocks/edit?id=' . $blockId : ''));
            $row['trace_review_url'] = (string)($row['trace_review_url'] ?? ($blockId !== '' ? '/planning/structures/links/review?block_id=' . $blockId : ''));
            $row['trace_targets'] = is_array($row['trace_targets'] ?? null) ? $row['trace_targets'] : [];
            $row['trace_summary'] = (string)($row['trace_summary'] ?? self::changeImpactTraceSummary(['links' => []]));
        }
        unset($row);

        return $rows;
    }

    private static function systemPackageOutputPath(): string
    {
        return 'runtime/qms_structured/system_package/qms_system_package.md';
    }

    private static function archiveSystemPackage(
        string $content,
        array $counts,
        string $generatedAt,
        string $outputPath,
        int $packageVersion,
        array $changeImpactInventory,
        array $blockTraceInventory
    ): string
    {
        $archiveDir = 'runtime/qms_structured/system_package/archive';
        $absoluteArchiveDir = self::appPath($archiveDir);
        if (!is_dir($absoluteArchiveDir)) {
            mkdir($absoluteArchiveDir, 0775, true);
        }

        $archivePath = $archiveDir . '/qms_system_package_'
            . date('Ymd_His')
            . '_' . substr(str_replace('.', '', uniqid('', true)), -8)
            . '.md';
        file_put_contents(self::appPath($archivePath), $content, LOCK_EX);

        $manifestPath = self::systemPackageArchiveManifestPath();
        $manifest = self::compactSystemPackageManifestHistory(self::systemPackageArchiveManifest());
        $manifest[] = [
            'generated_at' => $generatedAt,
            'package_version' => $packageVersion,
            'output_path' => $outputPath,
            'archive_path' => $archivePath,
            'content_sha256' => hash('sha256', $content),
            'total_documents' => self::packageEligibleStructuredDocumentQuery()->count(),
            'document_count_by_role' => $counts,
            'change_impact_count' => count($changeImpactInventory),
            'change_impact_inventory' => $changeImpactInventory,
            'block_trace_count' => count($blockTraceInventory),
            'block_trace_inventory' => $blockTraceInventory,
            'document_inventory' => self::systemPackageDocumentInventory(),
        ];
        if (count($manifest) > self::SYSTEM_PACKAGE_MANIFEST_RETENTION) {
            $manifest = array_slice($manifest, -self::SYSTEM_PACKAGE_MANIFEST_RETENTION);
        }

        file_put_contents(
            self::appPath($manifestPath),
            json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            LOCK_EX
        );

        return $archivePath;
    }

    private static function compactSystemPackageManifestHistory(array $manifest): array
    {
        return array_map(static function ($entry): array {
            $entry = is_array($entry) ? $entry : [];
            unset(
                $entry['change_impact_inventory'],
                $entry['document_inventory'],
                $entry['block_trace_inventory']
            );

            return $entry;
        }, $manifest);
    }

    private static function systemPackageArchiveManifestPath(): string
    {
        return 'runtime/qms_structured/system_package/archive/manifest.json';
    }

    private static function systemPackageArchiveManifest(): array
    {
        $manifestPath = self::appPath(self::systemPackageArchiveManifestPath());
        if (!is_file($manifestPath)) {
            return [];
        }

        $manifest = json_decode((string)file_get_contents($manifestPath), true);
        return is_array($manifest) ? array_values($manifest) : [];
    }

    private static function seedExternalBasisStructures(): array
    {
        $summary = ['assets' => 0, 'structured_documents' => 0, 'blocks' => 0, 'links' => 0, 'rendered' => 0];
        foreach (QmsSource::where('soft_delete', 0)->select() as $source) {
            $sourceSummary = self::structureExternalBasisSource($source);
            $summary['assets'] += (int)$sourceSummary['assets'];
            $summary['structured_documents'] += (int)$sourceSummary['structured_documents'];
            $summary['blocks'] += (int)$sourceSummary['blocks'];
            $summary['links'] += (int)$sourceSummary['links'];
            $summary['rendered'] += (int)$sourceSummary['rendered'];
        }

        return $summary;
    }

    private static function seedExternalBasisBlocks(QmsStructuredDocument $structured, QmsSource $source): array
    {
        $summary = ['blocks' => 0, 'links' => 0];
        $sourceCode = (string)$source->source_code;
        $sourceToken = self::stableToken($sourceCode);
        $clauseCount = QmsClause::where('source_id', (string)$source->id)->where('soft_delete', 0)->count();
        $overview = self::upsertBlock($structured, null, [
            'stable_key' => 'external_basis:' . $sourceToken . ':overview',
            'title' => (string)$source->name,
            'block_type' => 'section',
            'sort_order' => 100,
            'source_locator' => (string)$source->attachment_file_path,
            'markdown' => '# ' . $sourceCode . ' ' . (string)$source->name . "\n\n"
                . "- 来源类型：" . (string)$source->source_type . "\n"
                . "- 版本：" . ((string)$source->version !== '' ? (string)$source->version : '-') . "\n"
                . "- 实施日期：" . ((string)$source->effective_date !== '' ? (string)$source->effective_date : '-') . "\n"
                . "- 查新状态：" . ((string)$source->freshness_status !== '' ? (string)$source->freshness_status : '-') . "\n"
                . "- 查新日期：" . ((string)$source->freshness_checked_at !== '' ? (string)$source->freshness_checked_at : '-') . "\n"
                . "- 查新结论：" . ((string)$source->freshness_result !== '' ? (string)$source->freshness_result : '-') . "\n"
                . "- 查新证据：" . ((string)$source->freshness_evidence !== '' ? (string)$source->freshness_evidence : '-') . "\n"
                . "- 下次查新：" . ((string)$source->next_freshness_due !== '' ? (string)$source->next_freshness_due : '-') . "\n"
                . "- 归档文件：" . ((string)$source->attachment_file_path !== '' ? (string)$source->attachment_file_path : '-') . "\n"
                . "- 正式条款数：" . (int)$clauseCount . "\n",
        ]);
        self::resetBlockLinks($overview);
        $summary['blocks']++;

        $index = 0;
        foreach (QmsClause::where('source_id', (string)$source->id)->where('soft_delete', 0)->order('clause_number', 'asc')->select() as $clause) {
            $index++;
            $clauseNumber = (string)$clause->clause_number;
            $clauseTitle = (string)$clause->title;
            $text = Db::name('qms_clause_texts')
                ->where('clause_id', (string)$clause->id)
                ->where('soft_delete', 0)
                ->value('original_text');
            $markdown = "### 条款 {$clauseNumber} {$clauseTitle}\n\n"
                . "- 外部依据：{$sourceCode} " . (string)$source->name . "\n"
                . "- 条款编号：{$clauseNumber}\n"
                . "- 适用性：" . (string)$clause->applicability . "\n"
                . "- 定位：" . ((string)$clause->locator !== '' ? (string)$clause->locator : '-') . "\n";
            if ((string)$clause->summary !== '') {
                $markdown .= "- 摘要：" . (string)$clause->summary . "\n";
            }
            if ((string)$text !== '') {
                $markdown .= "\n#### 原文摘录\n\n" . trim((string)$text) . "\n";
            }

            $block = self::upsertBlock($structured, null, [
                'stable_key' => 'external_basis:' . $sourceToken . ':clause:' . self::stableToken($clauseNumber),
                'section_number' => $clauseNumber,
                'title' => '条款 ' . $clauseNumber . ' ' . $clauseTitle,
                'block_type' => 'clause_trace',
                'sort_order' => 1000 + $index,
                'source_locator' => (string)$clause->locator,
                'markdown' => $markdown,
            ]);
            self::resetBlockLinks($block);
            $summary['blocks']++;
            self::createBlockLink($block, [
                'clause_id' => (string)$clause->id,
                'relation_type' => 'basis',
                'confidence' => 'high',
                'note' => '外部依据结构化条款块对应正式条款库记录。',
            ]);
            $summary['links']++;
        }

        return $summary;
    }

    private static function seedReferenceProcedureStructures(): array
    {
        $summary = ['assets' => 0, 'structured_documents' => 0, 'blocks' => 0, 'links' => 0, 'rendered' => 0];
        foreach (QmsPlanningImportService::referenceProcedureDocumentBaselines() as $row) {
            $sourceKind = (string)($row['source_kind'] ?? 'reference_file');
            $role = (string)($row['document_role'] ?? 'procedure');
            $asset = self::upsertAsset($sourceKind, $row);
            $summary['assets']++;
            $structured = self::upsertStructuredDocument($role, $row, null, $asset);
            $summary['structured_documents']++;
            $blockSummary = self::seedReferenceProcedureBlocks($structured, $row);
            $summary['blocks'] += $blockSummary['blocks'];
            $summary['links'] += $blockSummary['links'];
            if (self::renderStructuredDocument($structured)) {
                $summary['rendered']++;
            }
        }

        return $summary;
    }

    private static function seedReferenceProcedureBlocks(QmsStructuredDocument $structured, array $row): array
    {
        $summary = ['blocks' => 0, 'links' => 0];
        $docNumber = (string)($row['doc_number'] ?? '');
        $title = (string)($row['title'] ?? '');
        $filePath = (string)($row['file_path'] ?? '');
        $sourceMarkdown = self::markdownFromSourcePath($filePath, 260);
        $token = self::stableToken($docNumber !== '' ? $docNumber : $title);

        $overview = self::upsertBlock($structured, null, [
            'stable_key' => 'reference_procedure:' . $token . ':overview',
            'title' => $title,
            'block_type' => 'section',
            'sort_order' => 100,
            'source_locator' => $filePath,
            'markdown' => '# ' . trim($docNumber . ' ' . $title) . "\n\n"
                . "- 文件定位：参考程序文件，不进入正式体系文件组合包。\n"
                . "- 来源文件：" . ($filePath !== '' ? $filePath : '-') . "\n"
                . "- 结构化用途：用于与现用质量手册和现用程序文件逐块比对，形成缺口建议或人工修订参考。\n"
                . "- 控制边界：参考文件不得自动覆盖现用受控文件、条款库或要素映射。\n",
        ]);
        self::resetBlockLinks($overview);
        $summary['blocks']++;

        if ($sourceMarkdown !== '') {
            $sourceBlock = self::upsertBlock($structured, null, [
                'stable_key' => 'reference_procedure:' . $token . ':source_excerpt',
                'title' => '参考程序文件源文摘录',
                'block_type' => 'text',
                'sort_order' => 200,
                'source_locator' => $filePath,
                'markdown' => "## 参考程序文件源文摘录\n\n" . $sourceMarkdown . "\n",
            ]);
            self::resetBlockLinks($sourceBlock);
            $summary['blocks']++;
        }
        foreach (self::referenceProcedurePackageBlocks($row) as $referenceBlock) {
            $block = self::upsertBlock($structured, null, $referenceBlock);
            self::resetBlockLinks($block);
            $summary['blocks']++;
            self::upsertReferenceProcedureSuggestion($block, $referenceBlock);
            self::upsertReferenceProcedureComparisonSuggestion($block, $referenceBlock);
            self::upsertReferenceProcedureUnmatchedSuggestion($block, $referenceBlock);
        }

        return $summary;
    }

    private static function seedManualBlocks(QmsStructuredDocument $structured, Document $document, array $row = []): array
    {
        $summary = ['blocks' => 0, 'links' => 0];
        $sourceBlocks = [];
        foreach (self::manualSourceBlockBlueprints(array_merge($row, [
            'doc_number' => (string)$document->doc_number,
            'title' => (string)$document->title,
        ])) as $sourceBlock) {
            $sourceBlocks[(string)$sourceBlock['section_number']] = $sourceBlock;
        }
        foreach (self::manualBlockBlueprints() as $blueprint) {
            if (isset($sourceBlocks[(string)$blueprint['section_number']])) {
                $blueprint = array_merge($blueprint, $sourceBlocks[(string)$blueprint['section_number']]);
            }
            $element = QmsElement::where('key', (string)$blueprint['element_key'])->where('soft_delete', 0)->find();
            $manualSection = $element
                ? QmsManualSection::where('document_id', (string)$document->id)->where('element_id', (string)$element->id)->where('soft_delete', 0)->find()
                : null;
            $block = self::upsertBlock($structured, $document, $blueprint);
            self::resetBlockLinks($block);
            $summary['blocks']++;
            if ($element) {
                self::createBlockLink($block, [
                    'element_id' => (string)$element->id,
                    'manual_section_id' => $manualSection ? (string)$manualSection->id : null,
                    'relation_type' => 'implements',
                    'confidence' => 'high',
                    'note' => '质量手册章节结构化块落实该体系要素。',
                ]);
                $summary['links']++;
                $clause = self::primaryClauseForElement((string)$element->id);
                if ($clause) {
                    self::createBlockLink($block, [
                        'element_id' => (string)$element->id,
                        'clause_id' => (string)$clause->id,
                        'relation_type' => 'basis',
                        'confidence' => 'high',
                        'note' => '主27025条款作为该手册章节的外部依据。',
                    ]);
                    $summary['links']++;
                }
                foreach (self::procedureDocumentsForElement((string)$element->id) as $procedureDocument) {
                    self::createBlockLink($block, [
                        'element_id' => (string)$element->id,
                        'manual_section_id' => $manualSection ? (string)$manualSection->id : null,
                        'procedure_document_id' => (string)$procedureDocument['id'],
                        'relation_type' => 'supporting',
                        'confidence' => (string)$procedureDocument['confidence'],
                        'note' => '程序文件承接质量手册章节控制要求；来自要素-程序文件映射：' . (string)$procedureDocument['note'],
                    ]);
                    $summary['links']++;
                }
            }
        }

        return $summary;
    }

    private static function seedProcedureBlocks(QmsStructuredDocument $structured, Document $document, array $row, string $role = 'procedure'): array
    {
        $summary = ['blocks' => 0, 'links' => 0];
        $documentTypeLabel = (string)($row['document_type_label'] ?? self::documentRoleLabel($role));
        $stablePrefix = (string)($row['stable_prefix'] ?? self::documentRoleStablePrefix($role));
        $overviewMarkdown = '# ' . (string)$document->doc_number . ' ' . (string)$document->title . "\n\n"
            . "- 原始文件：" . (string)($row['file_path'] ?? '') . "\n"
            . "- 文件类型：{$documentTypeLabel}\n"
            . "- Markdown结构：按目的、范围、职责、控制要求和记录要求拆分为独立章节块。\n"
            . "- 块级追溯：每个内容块可独立关联条款、要素、岗位、记录表格和运行模块。\n";
        $overview = self::upsertBlock($structured, $document, [
            'stable_key' => $stablePrefix . ':' . self::stableToken((string)$document->doc_number) . ':overview',
            'title' => (string)$document->title,
            'block_type' => 'section',
            'sort_order' => 100,
            'source_locator' => (string)($row['file_path'] ?? ''),
            'markdown' => $overviewMarkdown,
        ]);
        self::resetBlockLinks($overview);
        $summary['blocks']++;

        $elementIds = Db::table('qms_element_documents')
            ->where('document_id', (string)$document->id)
            ->where('soft_delete', 0)
            ->column('element_id');
        foreach (self::procedureSourceBlockBlueprints(array_merge($row, [
            'doc_number' => (string)$document->doc_number,
            'title' => (string)$document->title,
        ])) as $sourceBlueprint) {
            $sourceBlock = self::upsertBlock($structured, $document, $sourceBlueprint);
            self::resetBlockLinks($sourceBlock);
            $summary['blocks']++;
            foreach ($elementIds as $elementId) {
                self::createBlockLink($sourceBlock, [
                    'element_id' => (string)$elementId,
                    'procedure_document_id' => (string)$document->id,
                    'relation_type' => 'implements',
                    'confidence' => 'medium',
                    'note' => '程序源文件章节块落实该体系要素，需后续逐段确认。',
                ]);
                $summary['links']++;
                if ((string)$sourceBlueprint['block_type'] === 'responsibility') {
                    foreach (self::responsibilitiesForElement((string)$elementId) as $position) {
                        self::createBlockLink($sourceBlock, [
                            'element_id' => (string)$elementId,
                            'position_id' => (string)$position['position_id'],
                            'relation_type' => 'responsible',
                            'confidence' => 'medium',
                            'note' => (string)$position['note'],
                        ]);
                        $summary['links']++;
                    }
                    foreach (self::positionMentionsForMarkdown((string)$sourceBlueprint['markdown']) as $mention) {
                        $position = QmsPosition::where('code', (string)$mention['position_code'])->where('soft_delete', 0)->find();
                        if (!$position) {
                            continue;
                        }
                        self::createBlockLink($sourceBlock, [
                            'element_id' => (string)$elementId,
                            'position_id' => (string)$position->id,
                            'relation_type' => 'responsible',
                            'confidence' => 'high',
                            'note' => '来自程序文件职责源文：' . (string)$mention['evidence'],
                        ]);
                        $summary['links']++;
                    }
                }
            }
            if ((string)$sourceBlueprint['block_type'] === 'record_requirement') {
                foreach (self::recordTemplatesReferencedByMarkdown($document, (string)$sourceBlueprint['markdown']) as $template) {
                self::createBlockLink($sourceBlock, [
                    'element_id' => $template->element_id ? (string)$template->element_id : null,
                    'procedure_document_id' => (string)$document->id,
                    'record_form_template_id' => (string)$template->id,
                    'relation_type' => 'requires_record',
                    'confidence' => 'high',
                    'note' => '程序文件记录章节直接引用该记录表格。',
                ]);
                $summary['links']++;
                $summary['links'] += self::createRecordTemplateModuleLinks($sourceBlock, $template, (string)$template->element_id);
            }
        }
        }
        foreach ($elementIds as $elementId) {
            $element = QmsElement::where('id', (string)$elementId)->where('soft_delete', 0)->find();
            if (!$element) {
                continue;
            }
            $block = self::upsertBlock($structured, $document, self::procedureBlockBlueprint($row, $element));
            self::resetBlockLinks($block);
            $summary['blocks']++;
            self::createBlockLink($block, [
                'element_id' => (string)$element->id,
                'procedure_document_id' => (string)$document->id,
                'relation_type' => 'implements',
                'confidence' => 'medium',
                'note' => '程序文件标题与要素关键词匹配，需后续逐段复核。',
            ]);
            $summary['links']++;
            foreach (self::responsibilitiesForElement((string)$element->id) as $position) {
                self::createBlockLink($block, [
                    'element_id' => (string)$element->id,
                    'position_id' => (string)$position['position_id'],
                    'relation_type' => 'responsible',
                    'confidence' => 'medium',
                    'note' => (string)$position['note'],
                ]);
                $summary['links']++;
            }
            foreach (RecordFormTemplate::where('procedure_doc_id', (string)$document->id)->where('element_id', (string)$element->id)->where('soft_delete', 0)->select() as $template) {
                $recordBlock = self::upsertBlock($structured, $document, [
                    'stable_key' => $stablePrefix . ':' . self::stableToken((string)$document->doc_number) . ':record:' . self::stableToken((string)$template->doc_number),
                    'title' => '记录要求：' . (string)$template->name,
                    'block_type' => 'record_requirement',
                    'sort_order' => 400,
                    'markdown' => "### 记录要求：" . (string)$template->name . "\n\n"
                        . "- 记录表格：" . (string)$template->doc_number . ' ' . (string)$template->name . "\n"
                        . "- schema来源：按程序文件记录要求复核字段、责任人、频次和保留期限。\n",
                ]);
                self::resetBlockLinks($recordBlock);
                $summary['blocks']++;
                self::createBlockLink($recordBlock, [
                    'element_id' => (string)$element->id,
                    'procedure_document_id' => (string)$document->id,
                    'record_form_template_id' => (string)$template->id,
                    'relation_type' => 'requires_record',
                    'confidence' => 'medium',
                    'note' => '程序文件记录要求连接记录表格 schema。',
                ]);
                $summary['links']++;
                $summary['links'] += self::createRecordTemplateModuleLinks($recordBlock, $template, (string)$element->id);
            }
        }

        return $summary;
    }

    private static function seedRecordFormStructures(): array
    {
        $summary = ['assets' => 0, 'structured_documents' => 0, 'blocks' => 0, 'links' => 0, 'rendered' => 0];
        foreach (RecordFormTemplate::where('soft_delete', 0)->order('doc_number', 'asc')->select() as $template) {
            $resolvedPath = self::resolveRecordFormSourcePath($template);
            $resolvedName = $resolvedPath !== '' ? basename($resolvedPath) : '';
            if ($resolvedPath !== '' && is_file(self::workspacePath($resolvedPath))
                && ($resolvedPath !== (string)$template->source_file_path || $resolvedName !== (string)$template->source_file_name)) {
                $template->save([
                    'source_file_path' => $resolvedPath,
                    'source_file_name' => $resolvedName,
                ]);
            }
            $row = [
                'doc_number' => (string)$template->doc_number,
                'title' => (string)$template->name,
                'version' => (string)$template->version,
                'file_path' => $resolvedPath,
                'file_name' => $resolvedName !== '' ? $resolvedName : (string)($template->source_file_name ?: $template->name . '.schema.json'),
                'file_type' => $resolvedName !== '' ? strtolower((string)pathinfo($resolvedName, PATHINFO_EXTENSION)) : ($template->source_file_name ? strtolower((string)pathinfo((string)$template->source_file_name, PATHINFO_EXTENSION)) : 'json'),
                'source_note' => '记录表格模板按程序文件记录要求形成字段 schema。',
            ];
            $asset = self::upsertAsset('record_form', $row, null, null, $template);
            $summary['assets']++;
            $structured = self::upsertStructuredDocument('record_form', $row, null, $asset);
            $summary['structured_documents']++;
            $sourceMarkdown = self::markdownFromSourcePath($resolvedPath, 80);
            $schemaMarkdown = self::recordFormSchemaMarkdown($template, $sourceMarkdown);
            $block = self::upsertBlock($structured, null, [
                'stable_key' => 'record_form:' . self::stableToken((string)$template->doc_number) . ':schema',
                'title' => '表格schema：' . (string)$template->name,
                'block_type' => 'form_schema',
                'sort_order' => 100,
                'source_locator' => $resolvedPath,
                'markdown' => $schemaMarkdown,
            ]);
            self::resetBlockLinks($block);
            $summary['blocks']++;
            self::createBlockLink($block, [
                'element_id' => $template->element_id ? (string)$template->element_id : null,
                'procedure_document_id' => $template->procedure_doc_id ? (string)$template->procedure_doc_id : null,
                'record_form_template_id' => (string)$template->id,
                'relation_type' => 'renders_to',
                'confidence' => 'high',
                'note' => '记录表格结构化块对应运行记录 schema。',
            ]);
            $summary['links']++;
            $summary['links'] += self::createRecordTemplateModuleLinks($block, $template, (string)$template->element_id);
            if (self::renderStructuredDocument($structured)) {
                $summary['rendered']++;
            }
        }

        return $summary;
    }

    private static function upsertAsset(string $sourceKind, array $row, ?Document $document = null, ?QmsSource $source = null, ?RecordFormTemplate $template = null): QmsDocumentAsset
    {
        self::ensureDocumentAssetIndexes();

        $originalPath = (string)($row['file_path'] ?? '');
        $originalName = (string)($row['file_name'] ?? basename($originalPath));
        if ($originalPath === '' && $template) {
            $originalPath = 'record_form_schema/' . (string)$template->doc_number . '.json';
        }
        $fileType = strtolower((string)($row['file_type'] ?? pathinfo($originalName, PATHINFO_EXTENSION)));
        $normalizedName = self::normalizedFileName((string)($row['doc_number'] ?? ''), (string)($row['title'] ?? $originalName), (string)($row['version'] ?? ''), $fileType);
        $absolutePath = self::workspacePath($originalPath);
        $archivedPath = self::archiveFile($absolutePath, $sourceKind, $normalizedName);

        $asset = null;
        $sharedPathAsset = false;
        if ($template) {
            $asset = QmsDocumentAsset::where('source_kind', $sourceKind)
                ->where('record_form_template_id', (string)$template->id)
                ->where('soft_delete', 0)
                ->find();
        } elseif ($document) {
            $asset = QmsDocumentAsset::where('source_kind', $sourceKind)
                ->where('document_id', (string)$document->id)
                ->where('soft_delete', 0)
                ->find();
        } elseif ($source) {
            $asset = QmsDocumentAsset::where('source_kind', $sourceKind)
                ->where('source_id', (string)$source->id)
                ->where('soft_delete', 0)
                ->find();
        }
        if (!$asset && !$template && $originalPath !== '') {
            $asset = QmsDocumentAsset::where('source_kind', $sourceKind)->where('original_path', $originalPath)->where('soft_delete', 0)->find();
        }
        if ($asset && !$template && $originalPath !== '') {
            $pathAsset = QmsDocumentAsset::where('source_kind', $sourceKind)
                ->where('original_path', $originalPath)
                ->where('soft_delete', 0)
                ->find();
            if ($pathAsset && (string)$pathAsset->id !== (string)$asset->id) {
                $asset->save([
                    'publish' => 0,
                    'soft_delete' => 1,
                ]);
                $asset = $pathAsset;
                $sharedPathAsset = true;
            }
        }
        if (!$asset) {
            $asset = new QmsDocumentAsset();
            $asset->id = qms_uuid();
        }
        $recordFormTemplateId = $template ? (string)$template->id : null;
        if ($sharedPathAsset && (string)($asset->record_form_template_id ?? '') !== '') {
            $recordFormTemplateId = (string)$asset->record_form_template_id;
        }
        $assetNormalizedName = $sharedPathAsset && (string)($asset->normalized_name ?? '') !== ''
            ? (string)$asset->normalized_name
            : $normalizedName;
        $assetArchivedPath = $sharedPathAsset && (string)($asset->archived_path ?? '') !== ''
            ? (string)$asset->archived_path
            : $archivedPath;
        $asset->save([
            'source_kind' => $sourceKind,
            'document_id' => $document ? (string)$document->id : null,
            'source_id' => $source ? (string)$source->id : null,
            'record_form_template_id' => $recordFormTemplateId,
            'original_name' => $originalName !== '' ? $originalName : $normalizedName,
            'original_path' => $originalPath,
            'normalized_name' => $assetNormalizedName,
            'archived_path' => $assetArchivedPath,
            'file_type' => $fileType,
            'file_sha256' => is_file($absolutePath) ? hash_file('sha256', $absolutePath) : null,
            'archive_status' => is_file(self::appPath((string)$assetArchivedPath)) ? 'archived' : (is_file($absolutePath) ? 'pending' : 'missing'),
            'extracted_at' => date('Y-m-d H:i:s'),
            'extracted_text_hash' => hash('sha256', (string)($row['title'] ?? '') . '|' . $originalPath),
            'review_status' => 'structured',
            'source_note' => (string)($row['source_note'] ?? ''),
            'publish' => 1,
            'soft_delete' => 0,
        ]);

        return $asset;
    }

    private static function upsertStructuredDocument(string $role, array $row, ?Document $document, QmsDocumentAsset $asset): QmsStructuredDocument
    {
        self::ensureStructuredDocumentIndexes();

        $docNumber = (string)($row['doc_number'] ?? '');
        $version = (string)($row['version'] ?? '');
        $assetId = (string)$asset->id;
        $structured = $assetId !== ''
            ? QmsStructuredDocument::where('document_role', $role)
                ->where('source_asset_id', $assetId)
                ->where('soft_delete', 0)
                ->find()
            : null;
        if (!$structured && $role !== 'record_form') {
            $structured = QmsStructuredDocument::where('document_role', $role)
                ->where('doc_number', $docNumber)
                ->where('version', $version)
                ->where('soft_delete', 0)
                ->find();
        }
        if (!$structured) {
            $structured = new QmsStructuredDocument();
            $structured->id = qms_uuid();
        }
        $structured->save([
            'source_asset_id' => (string)$asset->id,
            'document_id' => $document ? (string)$document->id : null,
            'document_role' => $role,
            'doc_number' => $docNumber,
            'title' => (string)($row['title'] ?? ''),
            'version' => $version,
            'source_status' => (string)($row['source_status'] ?? 'current'),
            'status' => (string)($row['structured_status'] ?? 'structured'),
            'review_note' => (string)($row['review_note'] ?? '由现用文件归档与策划骨架生成，后续逐块复核后发布。'),
            'publish' => 1,
            'soft_delete' => 0,
        ]);

        return $structured;
    }

    private static function upsertBlock(QmsStructuredDocument $structured, ?Document $document, array $blueprint): QmsDocumentBlock
    {
        $block = QmsDocumentBlock::where('structured_document_id', (string)$structured->id)
            ->where('stable_key', (string)$blueprint['stable_key'])
            ->where('soft_delete', 0)
            ->find();
        if (!$block) {
            $block = new QmsDocumentBlock();
            $block->id = qms_uuid();
        }
        $block->save([
            'structured_document_id' => (string)$structured->id,
            'document_id' => $document ? (string)$document->id : null,
            'stable_key' => (string)$blueprint['stable_key'],
            'section_number' => (string)($blueprint['section_number'] ?? ''),
            'title' => (string)$blueprint['title'],
            'block_type' => (string)($blueprint['block_type'] ?? 'text'),
            'markdown' => (string)$blueprint['markdown'],
            'sort_order' => (int)($blueprint['sort_order'] ?? 0),
            'source_locator' => (string)($blueprint['source_locator'] ?? ''),
            'status' => 'effective',
            'publish' => 1,
            'soft_delete' => 0,
        ]);

        return $block;
    }

    private static function resetBlockLinks(QmsDocumentBlock $block): void
    {
        QmsDocumentBlockLink::where('block_id', (string)$block->id)->where('soft_delete', 0)->update(['soft_delete' => 1]);
    }

    private static function createBlockLink(QmsDocumentBlock $block, array $data): void
    {
        $relationType = (string)($data['relation_type'] ?? 'implements');
        $query = QmsDocumentBlockLink::where('block_id', (string)$block->id)->where('relation_type', $relationType);
        foreach (['element_id', 'clause_id', 'manual_section_id', 'procedure_document_id', 'record_form_template_id', 'position_id', 'business_module_id'] as $field) {
            if (!empty($data[$field])) {
                $query->where($field, (string)$data[$field]);
            }
        }
        $link = $query->find();
        if (!$link) {
            $link = new QmsDocumentBlockLink();
            $link->id = qms_uuid();
        }
        $link->save([
            'block_id' => (string)$block->id,
            'element_id' => $data['element_id'] ?? null,
            'clause_id' => $data['clause_id'] ?? null,
            'manual_section_id' => $data['manual_section_id'] ?? null,
            'procedure_document_id' => $data['procedure_document_id'] ?? null,
            'record_form_template_id' => $data['record_form_template_id'] ?? null,
            'position_id' => $data['position_id'] ?? null,
            'business_module_id' => $data['business_module_id'] ?? null,
            'relation_type' => $relationType,
            'confidence' => (string)($data['confidence'] ?? 'medium'),
            'note' => (string)($data['note'] ?? ''),
            'publish' => 1,
            'soft_delete' => 0,
        ]);
    }

    private static function createRecordTemplateModuleLinks(QmsDocumentBlock $block, RecordFormTemplate $template, string $elementId = ''): int
    {
        $count = 0;
        foreach (self::businessModulesForRecordTemplate($template, $elementId) as $module) {
            self::createBlockLink($block, [
                'element_id' => $elementId !== '' ? $elementId : ($template->element_id ? (string)$template->element_id : null),
                'procedure_document_id' => $template->procedure_doc_id ? (string)$template->procedure_doc_id : null,
                'record_form_template_id' => (string)$template->id,
                'business_module_id' => (string)$module->id,
                'relation_type' => 'supporting',
                'confidence' => 'medium',
                'note' => '记录表格在该运行模块中维护模板、填写记录或形成运行证据。',
            ]);
            $count++;
        }

        return $count;
    }

    private static function businessModulesForRecordTemplate(RecordFormTemplate $template, string $elementId = ''): array
    {
        $modules = [];
        foreach (['record_form_templates', 'record_form_instances'] as $code) {
            $module = QmsBusinessModule::where('code', $code)->where('soft_delete', 0)->find();
            if ($module) {
                $modules[(string)$module->id] = $module;
            }
        }

        $elementId = $elementId !== '' ? $elementId : ($template->element_id ? (string)$template->element_id : '');
        if ($elementId !== '') {
            foreach (QmsBusinessModule::where('primary_element_id', $elementId)->where('soft_delete', 0)->select() as $module) {
                $modules[(string)$module->id] = $module;
            }
        }

        uasort($modules, static fn (QmsBusinessModule $a, QmsBusinessModule $b): int => strcmp((string)$a->code, (string)$b->code));

        return array_values($modules);
    }

    private static function renderStructuredDocument(QmsStructuredDocument $structured): bool
    {
        $dir = self::appPath('runtime/qms_structured/' . (string)$structured->document_role);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $relativePath = 'runtime/qms_structured/' . (string)$structured->document_role . '/' . self::structuredDocumentStorageToken($structured) . '.md';
        $blocks = QmsDocumentBlock::where('structured_document_id', (string)$structured->id)->where('soft_delete', 0)->order('sort_order', 'asc')->select();
        $content = '# ' . (string)$structured->doc_number . ' ' . (string)$structured->title . "\n\n";
        foreach ($blocks as $block) {
            $content .= trim((string)$block->markdown) . "\n\n";
        }
        file_put_contents(self::appPath($relativePath), $content);
        self::archiveStructuredDocumentRender($structured, $content, $relativePath, count($blocks));
        $structured->save([
            'markdown_path' => $relativePath,
            'rendered_file_path' => $relativePath,
            'render_status' => 'rendered',
        ]);

        return true;
    }

    private static function structuredDocumentRenderArchiveSummary(QmsStructuredDocument $structured): array
    {
        $manifestPath = self::structuredDocumentArchiveManifestPath($structured);
        $manifest = self::structuredDocumentArchiveManifest($structured);
        $latest = $manifest !== [] ? (array)end($manifest) : [];

        return [
            'manifest_path' => $manifestPath,
            'archive_count' => count($manifest),
            'latest_archive_path' => (string)($latest['archive_path'] ?? ''),
            'latest_archive_at' => (string)($latest['generated_at'] ?? ''),
            'latest_content_sha256' => (string)($latest['content_sha256'] ?? ''),
        ];
    }

    private static function changeLogsForStructuredDocument(string $structuredDocumentId, int $limit = 20): array
    {
        self::ensureChangeLogTable();

        $rows = Db::table('qms_document_change_logs')
            ->alias('l')
            ->leftJoin('qms_document_blocks b', 'b.id = l.block_id')
            ->where('l.structured_document_id', $structuredDocumentId)
            ->where('l.soft_delete', 0)
            ->field('l.id,l.change_type,l.revision_note,l.old_markdown_sha256,l.new_markdown_sha256,l.rendered_file_path,l.archive_path,l.trace_snapshot_json,l.status_from,l.status_to,l.created,b.title block_title,b.stable_key block_stable_key')
            ->order('l.created', 'desc')
            ->order('l.change_type', 'desc')
            ->limit($limit)
            ->select()
            ->toArray();
        foreach ($rows as &$row) {
            $row['trace_snapshot'] = self::decodeTraceSnapshot((string)($row['trace_snapshot_json'] ?? ''));
        }
        unset($row);

        return $rows;
    }

    private static function traceReviewOptions(): array
    {
        return [
            'elements' => QmsElement::where('soft_delete', 0)->order('sort_order', 'asc')->field('id,name')->select()->toArray(),
            'clauses' => Db::table('qms_clauses')
                ->alias('c')
                ->leftJoin('qms_sources s', 's.id = c.source_id')
                ->where('c.soft_delete', 0)
                ->field('c.id,s.source_code,c.clause_number,c.title')
                ->order('s.source_code', 'asc')
                ->order('c.clause_number', 'asc')
                ->limit(600)
                ->select()
                ->toArray(),
            'manual_sections' => QmsManualSection::where('soft_delete', 0)->order('section_number', 'asc')->field('id,section_number,title')->select()->toArray(),
            'procedure_documents' => Document::where('soft_delete', 0)->where('level', 2)->order('doc_number', 'asc')->field('id,doc_number,title')->select()->toArray(),
            'record_forms' => RecordFormTemplate::where('soft_delete', 0)->order('doc_number', 'asc')->field('id,doc_number,name')->select()->toArray(),
            'positions' => QmsPosition::where('soft_delete', 0)->order('code', 'asc')->field('id,code,name')->select()->toArray(),
            'modules' => QmsBusinessModule::where('soft_delete', 0)->order('code', 'asc')->field('id,code,name')->select()->toArray(),
        ];
    }

    private static function normalizeTraceLinkPayload(array $data): array
    {
        $relationType = (string)($data['relation_type'] ?? 'implements');
        if (!in_array($relationType, ['basis', 'implements', 'mentions', 'responsible', 'requires_record', 'renders_to', 'supporting'], true)) {
            throw new \RuntimeException('追溯关系类型无效');
        }
        $confidence = (string)($data['confidence'] ?? 'review_required');
        if (!in_array($confidence, ['high', 'medium', 'low', 'review_required'], true)) {
            throw new \RuntimeException('追溯置信度无效');
        }

        $payload = [
            'element_id' => self::nullableId($data['element_id'] ?? null),
            'clause_id' => self::nullableId($data['clause_id'] ?? null),
            'manual_section_id' => self::nullableId($data['manual_section_id'] ?? null),
            'procedure_document_id' => self::nullableId($data['procedure_document_id'] ?? null),
            'record_form_template_id' => self::nullableId($data['record_form_template_id'] ?? null),
            'position_id' => self::nullableId($data['position_id'] ?? null),
            'business_module_id' => self::nullableId($data['business_module_id'] ?? null),
            'relation_type' => $relationType,
            'confidence' => $confidence,
            'note' => trim((string)($data['note'] ?? '')),
        ];

        return $payload;
    }

    private static function nullableId(mixed $value): ?string
    {
        $value = trim((string)$value);

        return $value !== '' ? $value : null;
    }

    private static function tracePayloadHasTarget(array $payload): bool
    {
        foreach (['element_id', 'clause_id', 'manual_section_id', 'procedure_document_id', 'record_form_template_id', 'position_id', 'business_module_id'] as $field) {
            if (!empty($payload[$field])) {
                return true;
            }
        }

        return false;
    }

    private static function findMatchingTraceLink(string $blockId, array $payload): ?QmsDocumentBlockLink
    {
        $query = QmsDocumentBlockLink::where('block_id', $blockId)->where('relation_type', (string)$payload['relation_type']);
        foreach (['element_id', 'clause_id', 'manual_section_id', 'procedure_document_id', 'record_form_template_id', 'position_id', 'business_module_id'] as $field) {
            if (!empty($payload[$field])) {
                $query->where($field, (string)$payload[$field]);
            }
        }

        return $query->find();
    }

    private static function traceLinkSummary(array $link): string
    {
        $parts = [(string)($link['relation_type'] ?? 'implements')];
        foreach ([
            'element_id' => 'element',
            'clause_id' => 'clause',
            'manual_section_id' => 'manual',
            'procedure_document_id' => 'procedure',
            'record_form_template_id' => 'record',
            'position_id' => 'position',
            'business_module_id' => 'module',
        ] as $field => $label) {
            if (!empty($link[$field])) {
                $parts[] = $label . '=' . (string)$link[$field];
            }
        }
        if (!empty($link['note'])) {
            $parts[] = 'note=' . (string)$link['note'];
        }

        return implode('; ', $parts);
    }

    private static function createTraceReviewLog(
        QmsStructuredDocument $structured,
        QmsDocumentBlock $block,
        string $revisionNote,
        string $newExcerpt,
        string $statusFrom,
        string $statusTo
    ): void {
        self::ensureChangeLogTable();
        $now = date('Y-m-d H:i:s');
        $userId = Session::has('user.id') ? (string)Session::get('user.id') : null;

        Db::name('qms_document_change_logs')->insert([
            'id' => qms_uuid(),
            'company_id' => (string)Config::get('qms.company_id'),
            'structured_document_id' => (string)$structured->id,
            'block_id' => (string)$block->id,
            'document_id' => $block->document_id ? (string)$block->document_id : null,
            'change_type' => 'block_update',
            'revision_note' => $revisionNote,
            'old_markdown_sha256' => null,
            'new_markdown_sha256' => null,
            'old_excerpt' => null,
            'new_excerpt' => self::excerpt($newExcerpt),
            'rendered_file_path' => (string)$structured->rendered_file_path,
            'archive_path' => '',
            'trace_snapshot_json' => self::traceSnapshotJson($structured, $block),
            'status_from' => $statusFrom,
            'status_to' => $statusTo,
            'publish' => 1,
            'soft_delete' => 0,
            'created' => $now,
            'modified' => $now,
            'created_by' => $userId,
            'modified_by' => $userId,
        ]);
    }

    private static function createChangeLog(
        QmsStructuredDocument $structured,
        QmsDocumentBlock $block,
        string $oldMarkdown,
        string $newMarkdown,
        string $revisionNote,
        string $statusFrom,
        string $statusTo,
        array $archiveSummary
    ): void {
        self::ensureChangeLogTable();
        $now = date('Y-m-d H:i:s');
        $userId = Session::has('user.id') ? (string)Session::get('user.id') : null;

        Db::name('qms_document_change_logs')->insert([
            'id' => qms_uuid(),
            'company_id' => (string)Config::get('qms.company_id'),
            'structured_document_id' => (string)$structured->id,
            'block_id' => (string)$block->id,
            'document_id' => $block->document_id ? (string)$block->document_id : null,
            'change_type' => 'block_update',
            'revision_note' => $revisionNote,
            'old_markdown_sha256' => hash('sha256', $oldMarkdown),
            'new_markdown_sha256' => hash('sha256', $newMarkdown),
            'old_excerpt' => self::excerpt($oldMarkdown),
            'new_excerpt' => self::excerpt($newMarkdown),
            'rendered_file_path' => (string)$structured->rendered_file_path,
            'archive_path' => (string)($archiveSummary['latest_archive_path'] ?? ''),
            'trace_snapshot_json' => self::traceSnapshotJson($structured, $block),
            'status_from' => $statusFrom,
            'status_to' => $statusTo,
            'publish' => 1,
            'soft_delete' => 0,
            'created' => $now,
            'modified' => $now,
            'created_by' => $userId,
            'modified_by' => $userId,
        ]);
    }

    private static function createDocumentStatusChangeLog(
        QmsStructuredDocument $structured,
        string $revisionNote,
        string $statusFrom,
        string $statusTo,
        array $archiveSummary
    ): void {
        self::ensureChangeLogTable();
        $now = date('Y-m-d H:i:s');
        $userId = Session::has('user.id') ? (string)Session::get('user.id') : null;
        $renderedPath = (string)$structured->rendered_file_path;
        $renderedContent = $renderedPath !== '' && is_file(self::appPath($renderedPath))
            ? (string)file_get_contents(self::appPath($renderedPath))
            : '';

        Db::name('qms_document_change_logs')->insert([
            'id' => qms_uuid(),
            'company_id' => (string)Config::get('qms.company_id'),
            'structured_document_id' => (string)$structured->id,
            'block_id' => null,
            'document_id' => $structured->document_id ? (string)$structured->document_id : null,
            'change_type' => 'status_change',
            'revision_note' => $revisionNote,
            'old_markdown_sha256' => null,
            'new_markdown_sha256' => $renderedContent !== '' ? hash('sha256', $renderedContent) : null,
            'old_excerpt' => null,
            'new_excerpt' => $renderedContent !== '' ? self::excerpt($renderedContent) : null,
            'rendered_file_path' => $renderedPath,
            'archive_path' => (string)($archiveSummary['latest_archive_path'] ?? ''),
            'status_from' => $statusFrom,
            'status_to' => $statusTo,
            'publish' => 1,
            'soft_delete' => 0,
            'created' => $now,
            'modified' => $now,
            'created_by' => $userId,
            'modified_by' => $userId,
        ]);
    }

    private static function externalBasisFreshnessRevisionNote(QmsSource $source, array $oldFreshness, array $newFreshness): string
    {
        $changes = [];
        foreach ([
            'freshness_checked_at' => '查新日期',
            'freshness_result' => '查新结论',
            'freshness_evidence' => '查新证据',
            'next_freshness_due' => '下次查新',
            'freshness_status' => '查新状态',
        ] as $field => $label) {
            $oldValue = trim((string)($oldFreshness[$field] ?? ''));
            $newValue = trim((string)($newFreshness[$field] ?? ''));
            if ($oldValue !== $newValue) {
                $changes[] = $label . '：' . ($newValue !== '' ? $newValue : '-');
            }
        }

        if ($changes === []) {
            $changes[] = '查新记录复核，无字段变化';
        }

        return '外部依据查新更新：' . (string)$source->source_code . '；' . implode('；', $changes);
    }

    private static function createSourceRefreshChangeLog(
        QmsStructuredDocument $structured,
        string $revisionNote,
        string $oldRenderedContent,
        string $newRenderedContent,
        string $statusFrom,
        string $statusTo,
        array $archiveSummary
    ): void {
        self::ensureChangeLogTable();
        $now = date('Y-m-d H:i:s');
        $userId = Session::has('user.id') ? (string)Session::get('user.id') : null;

        Db::name('qms_document_change_logs')->insert([
            'id' => qms_uuid(),
            'company_id' => (string)Config::get('qms.company_id'),
            'structured_document_id' => (string)$structured->id,
            'block_id' => null,
            'document_id' => $structured->document_id ? (string)$structured->document_id : null,
            'change_type' => 'version_update',
            'revision_note' => $revisionNote,
            'old_markdown_sha256' => $oldRenderedContent !== '' ? hash('sha256', $oldRenderedContent) : null,
            'new_markdown_sha256' => $newRenderedContent !== '' ? hash('sha256', $newRenderedContent) : null,
            'old_excerpt' => $oldRenderedContent !== '' ? self::excerpt($oldRenderedContent) : null,
            'new_excerpt' => $newRenderedContent !== '' ? self::excerpt($newRenderedContent) : null,
            'rendered_file_path' => (string)$structured->rendered_file_path,
            'archive_path' => (string)($archiveSummary['latest_archive_path'] ?? ''),
            'status_from' => $statusFrom,
            'status_to' => $statusTo,
            'publish' => 1,
            'soft_delete' => 0,
            'created' => $now,
            'modified' => $now,
            'created_by' => $userId,
            'modified_by' => $userId,
        ]);
    }

    private static function snapshotStructuredDocumentLinks(string $structuredDocumentId): array
    {
        $snapshot = [];
        $rows = Db::table('qms_document_block_links')
            ->alias('l')
            ->join('qms_document_blocks b', 'b.id = l.block_id')
            ->where('b.structured_document_id', $structuredDocumentId)
            ->where('b.soft_delete', 0)
            ->where('l.soft_delete', 0)
            ->field('b.stable_key,l.element_id,l.clause_id,l.manual_section_id,l.procedure_document_id,l.record_form_template_id,l.position_id,l.business_module_id,l.relation_type,l.confidence,l.note')
            ->select()
            ->toArray();
        foreach ($rows as $row) {
            $stableKey = (string)($row['stable_key'] ?? '');
            if ($stableKey === '') {
                continue;
            }
            $snapshot[$stableKey][] = [
                'element_id' => $row['element_id'] ?: null,
                'clause_id' => $row['clause_id'] ?: null,
                'manual_section_id' => $row['manual_section_id'] ?: null,
                'procedure_document_id' => $row['procedure_document_id'] ?: null,
                'record_form_template_id' => $row['record_form_template_id'] ?: null,
                'position_id' => $row['position_id'] ?: null,
                'business_module_id' => $row['business_module_id'] ?: null,
                'relation_type' => (string)$row['relation_type'],
                'confidence' => (string)$row['confidence'],
                'note' => (string)$row['note'],
            ];
        }

        return $snapshot;
    }

    private static function restoreStructuredDocumentLinks(string $structuredDocumentId, array $snapshot): void
    {
        if ($snapshot === []) {
            return;
        }
        $blocks = QmsDocumentBlock::where('structured_document_id', $structuredDocumentId)
            ->where('soft_delete', 0)
            ->select();
        $blocksByStableKey = [];
        foreach ($blocks as $block) {
            $blocksByStableKey[(string)$block->stable_key] = $block;
        }

        foreach ($snapshot as $stableKey => $links) {
            $block = $blocksByStableKey[(string)$stableKey] ?? null;
            if (!$block) {
                continue;
            }
            foreach ((array)$links as $link) {
                if (!self::tracePayloadHasTarget((array)$link)) {
                    continue;
                }
                self::createBlockLink($block, (array)$link);
            }
        }
    }

    private static function appendReviewNote(string $existing, string $revisionNote, string $prefix): string
    {
        $line = '[' . date('Y-m-d H:i') . '] ' . $prefix . '：' . $revisionNote;
        $combined = trim($line . ($existing !== '' ? "\n" . $existing : ''));

        return mb_substr($combined, 0, 2000);
    }

    private static function excerpt(string $markdown): string
    {
        $text = trim(preg_replace('/\s+/u', ' ', $markdown) ?? $markdown);

        return mb_substr($text, 0, 500);
    }

    private static function archiveStructuredDocumentRender(
        QmsStructuredDocument $structured,
        string $content,
        string $renderedFilePath,
        int $blockCount
    ): string {
        $contentHash = hash('sha256', $content);
        $manifest = self::structuredDocumentArchiveManifest($structured);
        $latest = $manifest !== [] ? (array)end($manifest) : [];
        $latestArchivePath = (string)($latest['archive_path'] ?? '');
        if (
            (string)($latest['content_sha256'] ?? '') === $contentHash
            && $latestArchivePath !== ''
            && is_file(self::appPath($latestArchivePath))
        ) {
            return $latestArchivePath;
        }

        $archiveDir = self::structuredDocumentArchiveDir($structured);
        $absoluteArchiveDir = self::appPath($archiveDir);
        if (!is_dir($absoluteArchiveDir)) {
            mkdir($absoluteArchiveDir, 0775, true);
        }

        $baseToken = self::structuredDocumentStorageToken($structured);
        $archivePath = $archiveDir . '/' . $baseToken
            . '_' . date('Ymd_His')
            . '_' . substr(str_replace('.', '', uniqid('', true)), -8)
            . '.md';
        file_put_contents(self::appPath($archivePath), $content, LOCK_EX);

        $manifest[] = [
            'generated_at' => date('Y-m-d H:i:s'),
            'rendered_file_path' => $renderedFilePath,
            'archive_path' => $archivePath,
            'document_role' => (string)$structured->document_role,
            'doc_number' => (string)$structured->doc_number,
            'title' => (string)$structured->title,
            'version' => (string)$structured->version,
            'block_count' => $blockCount,
            'content_sha256' => $contentHash,
        ];

        file_put_contents(
            self::appPath(self::structuredDocumentArchiveManifestPath($structured)),
            json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            LOCK_EX
        );

        return $archivePath;
    }

    private static function structuredDocumentArchiveManifest(QmsStructuredDocument $structured): array
    {
        $manifestPath = self::appPath(self::structuredDocumentArchiveManifestPath($structured));
        if (!is_file($manifestPath)) {
            return [];
        }

        $manifest = json_decode((string)file_get_contents($manifestPath), true);
        return is_array($manifest) ? array_values($manifest) : [];
    }

    private static function structuredDocumentArchiveManifestPath(QmsStructuredDocument $structured): string
    {
        return self::structuredDocumentArchiveDir($structured) . '/manifest.json';
    }

    private static function structuredDocumentArchiveDir(QmsStructuredDocument $structured): string
    {
        return 'runtime/qms_structured/' . (string)$structured->document_role
            . '/archive/'
            . self::structuredDocumentStorageToken($structured);
    }

    private static function linksForBlock(string $blockId): array
    {
        return Db::table('qms_document_block_links')
            ->alias('l')
            ->leftJoin('qms_elements e', 'e.id = l.element_id')
            ->leftJoin('qms_clauses c', 'c.id = l.clause_id')
            ->leftJoin('qms_sources s', 's.id = c.source_id')
            ->leftJoin('qms_manual_sections ms', 'ms.id = l.manual_section_id')
            ->leftJoin('documents pd', 'pd.id = l.procedure_document_id')
            ->leftJoin('record_form_templates rft', 'rft.id = l.record_form_template_id')
            ->leftJoin('qms_positions p', 'p.id = l.position_id')
            ->leftJoin('qms_business_modules m', 'm.id = l.business_module_id')
            ->where('l.block_id', $blockId)
            ->where('l.soft_delete', 0)
            ->field('l.id,l.relation_type,l.confidence,l.note,e.name element_name,s.source_code,c.clause_number,c.title clause_title,ms.section_number,ms.title manual_title,pd.doc_number procedure_number,pd.title procedure_title,rft.doc_number record_number,rft.name record_name,p.name position_name,m.code module_code,m.name module_name,m.url module_url')
            ->select()
            ->toArray();
    }

    private static function traceSnapshotJson(QmsStructuredDocument $structured, QmsDocumentBlock $block): string
    {
        return json_encode([
            'structured_document' => [
                'id' => (string)$structured->id,
                'document_role' => (string)$structured->document_role,
                'doc_number' => (string)$structured->doc_number,
                'title' => (string)$structured->title,
                'version' => (string)$structured->version,
                'status' => (string)$structured->status,
            ],
            'block' => [
                'id' => (string)$block->id,
                'stable_key' => (string)$block->stable_key,
                'section_number' => (string)$block->section_number,
                'title' => (string)$block->title,
                'block_type' => (string)$block->block_type,
                'source_locator' => (string)$block->source_locator,
            ],
            'links' => self::linksForBlock((string)$block->id),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
    }

    private static function decodeTraceSnapshot(string $json): array
    {
        if (trim($json) === '') {
            return [];
        }

        $decoded = json_decode($json, true);
        return is_array($decoded) ? $decoded : [];
    }

    private static function changeImpactTraceSummary(array $snapshot): string
    {
        $targets = self::changeImpactTraceTargets($snapshot);
        $labels = [
            'elements' => '要素',
            'clauses' => '条款',
            'manual_sections' => '手册章节',
            'procedures' => '程序',
            'record_forms' => '表格',
            'positions' => '岗位',
            'modules' => '运行模块',
        ];

        $parts = [];
        foreach ($labels as $key => $label) {
            $items = $targets[$key] ?? [];
            if ($items === []) {
                continue;
            }
            $parts[] = $label . '：' . implode('、', array_slice($items, 0, 4));
        }

        return $parts !== [] ? implode('；', $parts) : '无已登记追溯对象';
    }

    private static function changeImpactTraceTargets(array $snapshot): array
    {
        $links = $snapshot['links'] ?? [];
        $groups = [
            'elements' => [],
            'clauses' => [],
            'manual_sections' => [],
            'procedures' => [],
            'record_forms' => [],
            'positions' => [],
            'modules' => [],
        ];
        if (!is_array($links) || $links === []) {
            return $groups;
        }

        foreach ($links as $link) {
            if (!is_array($link)) {
                continue;
            }
            self::pushUnique($groups['elements'], (string)($link['element_name'] ?? ''));
            self::pushUnique($groups['clauses'], trim((string)($link['source_code'] ?? '') . ' ' . (string)($link['clause_number'] ?? '')));
            self::pushUnique($groups['manual_sections'], trim((string)($link['section_number'] ?? '') . ' ' . (string)($link['manual_title'] ?? '')));
            self::pushUnique($groups['procedures'], trim((string)($link['procedure_number'] ?? '') . ' ' . (string)($link['procedure_title'] ?? '')));
            self::pushUnique($groups['record_forms'], trim((string)($link['record_number'] ?? '') . ' ' . (string)($link['record_name'] ?? '')));
            self::pushUnique($groups['positions'], (string)($link['position_name'] ?? ''));
            self::pushUnique($groups['modules'], trim((string)($link['module_code'] ?? '') . ' ' . (string)($link['module_name'] ?? '')));
        }

        return $groups;
    }

    private static function pushUnique(array &$items, string $value): void
    {
        $value = trim($value);
        if ($value === '' || in_array($value, $items, true)) {
            return;
        }

        $items[] = $value;
    }

    private static function primaryClauseForElement(string $elementId): ?QmsClause
    {
        $link = QmsElementClauseLink::where('element_id', $elementId)->where('is_primary', 1)->where('soft_delete', 0)->find();
        if (!$link) {
            return null;
        }

        return QmsClause::where('id', (string)$link->clause_id)->where('soft_delete', 0)->find();
    }

    private static function responsibilitiesForElement(string $elementId): array
    {
        return Db::table('qms_element_responsibilities')
            ->alias('r')
            ->leftJoin('qms_positions p', 'p.id = r.position_id')
            ->where('r.element_id', $elementId)
            ->where('r.soft_delete', 0)
            ->field('r.position_id,r.responsibility_type,r.note,p.name position_name')
            ->select()
            ->toArray();
    }

    private static function procedureDocumentsForElement(string $elementId): array
    {
        return Db::table('qms_element_documents')
            ->alias('l')
            ->join('documents d', 'd.id = l.document_id')
            ->where('l.element_id', $elementId)
            ->where('l.soft_delete', 0)
            ->where('d.soft_delete', 0)
            ->where('d.level', 2)
            ->field('d.id,d.doc_number,d.title,l.relation_type,l.note')
            ->order('d.doc_number', 'asc')
            ->select()
            ->map(function (array $row): array {
                return [
                    'id' => (string)$row['id'],
                    'doc_number' => (string)$row['doc_number'],
                    'title' => (string)$row['title'],
                    'confidence' => (string)$row['relation_type'] === 'primary' ? 'high' : 'medium',
                    'note' => (string)($row['note'] ?? ''),
                ];
            })
            ->toArray();
    }

    private static function positionAliasDefinitions(): array
    {
        return [
            'lab_director' => [
                'name' => '实验室主任',
                'aliases' => ['实验室主任', '最高管理者'],
            ],
            'technical_manager' => [
                'name' => '技术负责人',
                'aliases' => ['技术负责人', '技术主管'],
            ],
            'quality_manager' => [
                'name' => '质量负责人',
                'aliases' => ['质量负责人', '质量主管'],
            ],
            'office_manager' => [
                'name' => '办公室主任',
                'aliases' => ['办公室主任', '办公室负责人', '办公室'],
            ],
            'document_controller' => [
                'name' => '资料管理员',
                'aliases' => ['资料管理员', '资料员', '文件管理员', '档案管理员'],
            ],
            'equipment_manager' => [
                'name' => '设备管理员',
                'aliases' => ['设备管理员', '仪器设备管理员'],
            ],
            'sample_manager' => [
                'name' => '样品管理员',
                'aliases' => ['样品管理员'],
            ],
            'testing_room_manager' => [
                'name' => '检测室主任',
                'aliases' => ['检测室主任'],
            ],
            'testing_staff' => [
                'name' => '检测人员',
                'aliases' => ['检测人员', '检测员'],
            ],
            'authorized_signatory' => [
                'name' => '授权签字人',
                'aliases' => ['授权签字人', '授权签字员'],
            ],
            'internal_auditor' => [
                'name' => '内审员',
                'aliases' => ['内审员', '内部审核员', '审核员'],
            ],
            'supervisor' => [
                'name' => '监督员',
                'aliases' => ['监督员'],
            ],
        ];
    }

    private static function splitMarkdownSections(string $markdown): array
    {
        $sections = [];
        $heading = '';
        $buffer = [];
        foreach (preg_split('/\R/u', $markdown) ?: [] as $line) {
            $trimmed = trim((string)$line);
            if (preg_match('/^##\s+(.+)$/u', $trimmed, $match)) {
                if ($heading !== '' && trim(implode("\n", $buffer)) !== '') {
                    $sections[$heading] = trim(implode("\n", $buffer));
                }
                $heading = trim((string)$match[1]);
                $buffer = [];
                continue;
            }
            if ($heading === '' || str_starts_with($trimmed, '# ')) {
                continue;
            }
            $buffer[] = $trimmed;
        }
        if ($heading !== '' && trim(implode("\n", $buffer)) !== '') {
            $sections[$heading] = trim(implode("\n", $buffer));
        }

        return $sections;
    }

    private static function procedureSectionDefinition(string $heading): ?array
    {
        $section = self::sectionHeading($heading) ?? trim($heading);
        $definitions = [
            '目的' => ['key' => 'purpose', 'block_type' => 'purpose', 'sort_order' => 120],
            '范围' => ['key' => 'scope', 'block_type' => 'scope', 'sort_order' => 130],
            '职责' => ['key' => 'responsibility', 'block_type' => 'responsibility', 'sort_order' => 140],
            '工作程序' => ['key' => 'process', 'block_type' => 'process_step', 'sort_order' => 150],
            '相关文件' => ['key' => 'related_documents', 'block_type' => 'text', 'sort_order' => 160],
            '引用文件' => ['key' => 'related_documents', 'block_type' => 'text', 'sort_order' => 160],
            '记录' => ['key' => 'records', 'block_type' => 'record_requirement', 'sort_order' => 170],
            '质量记录' => ['key' => 'records', 'block_type' => 'record_requirement', 'sort_order' => 170],
        ];

        return $definitions[$section] ?? null;
    }

    private static function recordTemplatesReferencedByMarkdown(Document $document, string $markdown): array
    {
        $templates = [];
        $compactMarkdown = self::compactLookupText($markdown);
        foreach (RecordFormTemplate::where('soft_delete', 0)->select() as $template) {
            $candidates = [
                (string)$template->doc_number,
                (string)$template->name,
                (string)$template->source_file_name,
            ];
            $docNumber = (string)$template->doc_number;
            if (!str_starts_with($docNumber, '待定') && preg_match('/(\d{2}-\d{2,3})$/u', $docNumber, $match)) {
                $candidates[] = (string)$match[1];
            }
            foreach (array_unique(array_filter($candidates)) as $candidate) {
                if (str_contains($markdown, (string)$candidate) || str_contains($compactMarkdown, self::compactLookupText((string)$candidate))) {
                    $templates[(string)$template->id] = $template;
                    break;
                }
            }
        }

        return array_values($templates);
    }

    private static function schemaFieldsToMarkdown(array $fields, int $depth = 0): string
    {
        $lines = [];
        $indent = str_repeat('  ', $depth);
        foreach ($fields as $field) {
            $required = ($field['required'] ?? false) ? '必填' : '选填';
            $lines[] = $indent . '- `' . (string)$field['key'] . '` ' . (string)$field['label']
                . '（' . (string)$field['type'] . '，' . $required . '）';
            if (($field['type'] ?? '') === 'repeatable_table' && !empty($field['columns']) && is_array($field['columns'])) {
                $lines[] = self::schemaFieldsToMarkdown($field['columns'], $depth + 1);
            }
        }

        return implode("\n", array_filter($lines));
    }

    private static function manualSectionHeading(string $line): ?array
    {
        if (!preg_match('/^([4-8](?:\.\d+)?)(?!\.\d)(?:\.|\s)+(.+)$/u', $line, $match)) {
            return null;
        }
        $number = rtrim((string)$match[1], '.');
        $title = trim((string)$match[2]);
        $title = preg_replace('/\s+\d+$/u', '', $title) ?? $title;
        $title = trim($title);
        if ($title === '' || preg_match('/^\d+$/u', $title)) {
            return null;
        }

        return [
            'number' => $number,
            'title' => $title,
        ];
    }

    private static function recordFormSourceIndex(): array
    {
        static $index = null;
        if (is_array($index)) {
            return $index;
        }

        $index = [];
        $base = self::workspacePath('现用文件/记录表格');
        if (!is_dir($base)) {
            return $index;
        }

        $workspaceRoot = dirname(self::appRoot());
        $paths = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($base, \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $fileInfo) {
            if (!$fileInfo->isFile()) {
                continue;
            }
            $fileName = $fileInfo->getFilename();
            if (str_starts_with($fileName, '~$')) {
                continue;
            }
            $extension = strtolower($fileInfo->getExtension());
            if (!in_array($extension, ['doc', 'docx', 'xls', 'xlsx', 'pdf'], true)) {
                continue;
            }
            $absolutePath = $fileInfo->getPathname();
            $relativePath = ltrim(str_replace($workspaceRoot . DIRECTORY_SEPARATOR, '', $absolutePath), DIRECTORY_SEPARATOR);
            $paths[] = str_replace(DIRECTORY_SEPARATOR, '/', $relativePath);
        }
        sort($paths, SORT_NATURAL);

        foreach ($paths as $relativePath) {
            $fileName = basename($relativePath);
            $nameWithoutExtension = (string)pathinfo($fileName, PATHINFO_FILENAME);
            $keys = [
                self::sourceKey($fileName),
                self::sourceKey($nameWithoutExtension),
            ];
            if (preg_match('/^(\d{2}-\d{2,3})/u', $nameWithoutExtension, $match)) {
                $keys[] = self::sourceKey($match[1]);
            }
            $titleOnly = preg_replace('/^\d{2}-\d{2,3}/u', '', $nameWithoutExtension) ?? $nameWithoutExtension;
            $keys[] = self::sourceKey($titleOnly);

            foreach (array_unique(array_filter($keys)) as $key) {
                if (!isset($index[$key])) {
                    $index[$key] = $relativePath;
                }
            }
        }

        return $index;
    }

    private static function recordFormLookupKeys(array|RecordFormTemplate $template): array
    {
        $data = is_array($template) ? $template : [
            'doc_number' => (string)$template->doc_number,
            'name' => (string)$template->name,
            'source_file_name' => (string)$template->source_file_name,
            'source_file_path' => (string)$template->source_file_path,
        ];

        $keys = [];
        foreach (['source_file_name', 'source_file_path', 'name', 'doc_number'] as $field) {
            $value = (string)($data[$field] ?? '');
            if ($value === '') {
                continue;
            }
            $keys[] = self::sourceKey($value);
            $keys[] = self::sourceKey((string)pathinfo(str_replace('\\', '/', $value), PATHINFO_FILENAME));
        }
        $docNumber = (string)($data['doc_number'] ?? '');
        if (preg_match('/(\d{2}-\d{2,3})$/u', $docNumber, $match)) {
            $keys[] = self::sourceKey($match[1]);
        }

        return array_values(array_unique(array_filter($keys)));
    }

    private static function sourceKey(string $value): string
    {
        $value = str_replace('\\', '/', trim($value));
        $value = basename($value);
        $value = preg_replace('/\.(docx?|xlsx?|pdf|json)$/iu', '', $value) ?? $value;
        $value = preg_replace('/[《》〈〉“”"\'\s　_\-\/\\\\（）()【】\\[\\]：:；;，,。.]+/u', '', $value) ?? $value;
        $value = preg_replace('/[^\p{Han}A-Za-z0-9]+/u', '', $value) ?? $value;

        return mb_strtolower($value, 'UTF-8');
    }

    private static function referenceProcedureCatalog(array $lines): array
    {
        $catalog = [];
        foreach ($lines as $line) {
            if (!preg_match('/^\s*(\d+)\s*\|\s*(CX[-－]?\d{2})\s*\|\s*[^|]+\|\s*(.+?)\s*$/u', (string)$line, $match)) {
                continue;
            }
            $number = str_replace('－', '-', strtoupper((string)$match[2]));
            $catalog[$number] = [
                'index' => (int)$match[1],
                'number' => $number,
                'title' => trim((string)$match[3]),
            ];
        }

        uasort($catalog, static fn (array $left, array $right): int => (int)$left['index'] <=> (int)$right['index']);

        return array_values($catalog);
    }

    private static function referenceProcedureBodyStarts(array $lines, array $catalog): array
    {
        $starts = [];
        $searchOffset = self::referenceProcedureCatalogEndOffset($lines);
        foreach ($catalog as $entry) {
            $number = (string)$entry['number'];
            $titleKey = self::referenceProcedureTitleKey((string)$entry['title']);
            for ($index = $searchOffset; $index < count($lines); $index++) {
                $line = trim((string)$lines[$index]);
                if ($line === '' || str_contains($line, '|')) {
                    continue;
                }
                if (self::referenceProcedureTitleKey($line) === $titleKey) {
                    $starts[$number] = $index;
                    $searchOffset = $index + 1;
                    break;
                }
            }
        }

        return $starts;
    }

    private static function referenceProcedureCatalogEndOffset(array $lines): int
    {
        $lastCatalogLine = 0;
        foreach ($lines as $index => $line) {
            if (preg_match('/^\s*\d+\s*\|\s*CX[-－]?\d{2}\s*\|/u', (string)$line)) {
                $lastCatalogLine = (int)$index;
            }
        }

        return $lastCatalogLine + 1;
    }

    private static function referenceProcedureTitleKey(string $title): string
    {
        return str_replace('与', '和', self::compactLookupText($title));
    }

    private static function referenceProcedureBlockForTitle(string $referenceTitle): array
    {
        foreach (QmsPlanningImportService::referenceProcedureDocumentBaselines() as $package) {
            foreach (self::referenceProcedurePackageBlocks($package) as $block) {
                if ((string)$block['title'] === $referenceTitle) {
                    return [
                        'package' => $package,
                        'block' => $block,
                    ];
                }
            }
        }

        return [];
    }

    private static function referenceProcedureManualMatchOptions(): array
    {
        return [
            'procedures' => Document::where('level', 2)
                ->where('soft_delete', 0)
                ->field('id,doc_number,title')
                ->order('doc_number', 'asc')
                ->select()
                ->toArray(),
        ];
    }

    private static function manualReferenceProcedureMatchForTitle(string $referenceTitle): ?array
    {
        self::ensureReferenceProcedureMatchTable();
        $sectionNumber = '';
        if (preg_match('/^(CX[-－]?\d{2})\s+/u', $referenceTitle, $match)) {
            $sectionNumber = str_replace('－', '-', strtoupper((string)$match[1]));
        }

        $match = Db::name('qms_reference_procedure_matches')
            ->where('reference_title', $referenceTitle)
            ->where('status', 'active')
            ->where('soft_delete', 0)
            ->order('modified', 'desc')
            ->find();
        if (!is_array($match) && $sectionNumber !== '') {
            $match = Db::name('qms_reference_procedure_matches')
                ->where('reference_section_number', $sectionNumber)
                ->where('status', 'active')
                ->where('soft_delete', 0)
                ->order('modified', 'desc')
                ->find();
        }

        if (!is_array($match)) {
            return null;
        }
        $procedure = Document::where('id', (string)$match['procedure_document_id'])
            ->where('level', 2)
            ->where('soft_delete', 0)
            ->field('id,doc_number,title')
            ->find();
        if (!$procedure) {
            return null;
        }

        return array_merge($procedure->toArray(), [
            '_match_source' => 'manual',
            '_manual_match_id' => (string)$match['id'],
            '_manual_review_note' => (string)$match['review_note'],
        ]);
    }

    private static function referenceProcedureSignificantKey(string $title): string
    {
        $title = trim((string)preg_replace('/^CX[-－]?\d+\s*/iu', '', $title));
        $key = self::referenceProcedureTitleKey($title);
        foreach (['程序', '管理', '控制', '保证', '评定', '处理', '实施', '的', '与', '和'] as $word) {
            $key = str_replace($word, '', $key);
        }

        return $key;
    }

    private static function hasReliableReferenceProcedureTitleOverlap(string $referenceTitle, string $currentTitle): bool
    {
        $referenceKey = self::referenceProcedureSignificantKey($referenceTitle);
        $currentKey = self::referenceProcedureSignificantKey($currentTitle);
        if (mb_strlen($referenceKey, 'UTF-8') < 2 || mb_strlen($currentKey, 'UTF-8') < 2) {
            return false;
        }
        if (str_contains($referenceKey, $currentKey) || str_contains($currentKey, $referenceKey)) {
            return true;
        }

        return count(array_intersect(
            self::referenceProcedureTitleBigrams($referenceKey),
            self::referenceProcedureTitleBigrams($currentKey)
        )) >= 2;
    }

    private static function referenceProcedureTitleBigrams(string $key): array
    {
        $bigrams = [];
        $length = mb_strlen($key, 'UTF-8');
        for ($index = 0; $index < $length - 1; $index++) {
            $bigrams[] = mb_substr($key, $index, 2, 'UTF-8');
        }

        return array_values(array_unique($bigrams));
    }

    private static function trimReferenceProcedureBody(string $title, array $bodyLines): array
    {
        $bodyLines = array_values($bodyLines);
        if ($bodyLines !== [] && self::referenceProcedureTitleKey((string)$bodyLines[0]) === self::referenceProcedureTitleKey($title)) {
            array_shift($bodyLines);
        }

        return $bodyLines;
    }

    private static function referenceProcedureExpectedLabels(string $markdown): array
    {
        $labels = [];
        $normalizers = [
            '目的' => '目的',
            '范围' => '范围',
            '职责' => '职责',
            '工作程序' => '工作程序',
            '记录' => '记录要求',
            '质量记录' => '记录要求',
        ];
        foreach (preg_split('/\R/u', $markdown) ?: [] as $line) {
            $line = trim((string)$line);
            if (!preg_match('/^\d+\s*[、.．]\s*(目的|范围|职责|工作程序|记录|质量记录)\s*$/u', $line, $match)) {
                continue;
            }
            $label = $normalizers[(string)$match[1]] ?? '';
            if ($label !== '') {
                $labels[] = $label;
            }
        }

        $labels = array_values(array_unique($labels));
        if ($labels !== []) {
            return array_values(array_intersect(self::procedureCoverageLabels(), $labels));
        }

        return self::procedureCoverageLabels();
    }

    private static function procedureCoverageLabels(): array
    {
        return ['目的', '范围', '职责', '工作程序', '记录要求'];
    }

    private static function procedureStructureCoverage(string $procedureDocumentId): array
    {
        $structured = QmsStructuredDocument::where('document_role', 'procedure')
            ->where('document_id', $procedureDocumentId)
            ->where('soft_delete', 0)
            ->find();
        if (!$structured) {
            return [
                'structured_document_id' => null,
                'covered_labels' => [],
            ];
        }

        $blockTypes = QmsDocumentBlock::where('structured_document_id', (string)$structured->id)
            ->where('soft_delete', 0)
            ->column('block_type');
        $coverageByType = [
            'purpose' => '目的',
            'scope' => '范围',
            'responsibility' => '职责',
            'process_step' => '工作程序',
            'record_requirement' => '记录要求',
        ];
        $covered = [];
        foreach ($coverageByType as $blockType => $label) {
            if (in_array($blockType, $blockTypes, true)) {
                $covered[] = $label;
            }
        }

        return [
            'structured_document_id' => (string)$structured->id,
            'covered_labels' => $covered,
        ];
    }

    private static function upsertReferenceProcedureSuggestion(QmsDocumentBlock $block, array $referenceBlock): void
    {
        $referenceTitle = (string)$referenceBlock['title'];
        $procedure = self::currentProcedureForReferenceTitle($referenceTitle);
        if (!$procedure) {
            self::deleteOpenReferenceProcedureComparisonSuggestions($referenceTitle);
            return;
        }

        $title = '对照参考程序：' . $referenceTitle;
        $matchEvidence = (string)($procedure['_match_source'] ?? 'auto') === 'manual'
            ? '；匹配方式：人工匹配；复核说明：' . (string)($procedure['_manual_review_note'] ?? '')
            : '';
        $suggestion = Db::name('qms_agent_suggestions')
            ->where('suggestion_type', 'document')
            ->where('title', $title)
            ->where('status', 'open')
            ->find();
        $now = date('Y-m-d H:i:s');
        $payload = [
            'company_id' => (string)Config::get('qms.company_id'),
            'element_id' => null,
            'suggestion_type' => 'document',
            'title' => $title,
            'content' => '参考程序：' . $referenceTitle
                . '；现用程序：' . trim((string)$procedure['doc_number'] . ' ' . (string)$procedure['title'])
                . '。建议逐块对照目的、范围、职责、工作程序、记录要求，仅供人工复核。',
            'evidence' => '参考程序结构块：' . (string)$block->id
                . '；源定位：' . (string)$block->source_locator
                . $matchEvidence
                . '。智能体只记录建议/缺口，不自动修改正式体系数据。',
            'status' => 'open',
            'modified' => $now,
        ];
        if ($suggestion) {
            Db::name('qms_agent_suggestions')->where('id', (string)$suggestion['id'])->update($payload);
            return;
        }

        $payload['id'] = qms_uuid();
        $payload['created'] = $now;
        Db::name('qms_agent_suggestions')->insert($payload);
    }

    private static function upsertReferenceProcedureComparisonSuggestion(QmsDocumentBlock $block, array $referenceBlock): void
    {
        $referenceTitle = (string)$referenceBlock['title'];
        $procedure = self::currentProcedureForReferenceTitle($referenceTitle);
        if (!$procedure) {
            self::deleteOpenReferenceProcedureComparisonSuggestions($referenceTitle);
            return;
        }

        $expectedLabels = self::referenceProcedureExpectedLabels((string)$referenceBlock['markdown']);
        $coverage = self::procedureStructureCoverage((string)$procedure['id']);
        $coveredLabels = array_values(array_intersect($expectedLabels, $coverage['covered_labels']));
        $missingLabels = array_values(array_diff($expectedLabels, $coveredLabels));
        $currentTitle = trim((string)$procedure['doc_number'] . ' ' . (string)$procedure['title']);
        $title = '块级对照参考程序：' . $referenceTitle;
        $matchEvidence = (string)($procedure['_match_source'] ?? 'auto') === 'manual'
            ? '；匹配方式：人工匹配；复核说明：' . (string)($procedure['_manual_review_note'] ?? '')
            : '';
        $suggestion = Db::name('qms_agent_suggestions')
            ->where('suggestion_type', 'document')
            ->where('title', $title)
            ->where('status', 'open')
            ->find();
        $now = date('Y-m-d H:i:s');
        $payload = [
            'company_id' => (string)Config::get('qms.company_id'),
            'element_id' => null,
            'suggestion_type' => 'document',
            'title' => $title,
            'content' => '参考程序：' . $referenceTitle
                . '；现用程序：' . $currentTitle
                . '。已覆盖：' . ($coveredLabels === [] ? '无' : implode('、', $coveredLabels))
                . '；待补齐：' . ($missingLabels === [] ? '无' : implode('、', $missingLabels))
                . '。建议人工对照参考块正文和现用程序块正文后再修订。',
            'evidence' => '参考程序结构块：' . (string)$block->id
                . '；现用程序结构文档：' . (string)($coverage['structured_document_id'] ?? '')
                . '；源定位：' . (string)$block->source_locator
                . $matchEvidence
                . '。智能体只记录建议/缺口，不自动修改正式体系数据。',
            'status' => 'open',
            'modified' => $now,
        ];
        if ($suggestion) {
            Db::name('qms_agent_suggestions')->where('id', (string)$suggestion['id'])->update($payload);
            return;
        }

        $payload['id'] = qms_uuid();
        $payload['created'] = $now;
        Db::name('qms_agent_suggestions')->insert($payload);
    }

    private static function upsertReferenceProcedureUnmatchedSuggestion(QmsDocumentBlock $block, array $referenceBlock): void
    {
        $referenceTitle = (string)$referenceBlock['title'];
        if (self::currentProcedureForReferenceTitle($referenceTitle)) {
            Db::name('qms_agent_suggestions')
                ->where('suggestion_type', 'document')
                ->where('title', '人工匹配参考程序：' . $referenceTitle)
                ->where('status', 'open')
                ->delete();
            return;
        }

        $title = '人工匹配参考程序：' . $referenceTitle;
        $suggestion = Db::name('qms_agent_suggestions')
            ->where('suggestion_type', 'document')
            ->where('title', $title)
            ->where('status', 'open')
            ->find();
        $now = date('Y-m-d H:i:s');
        $payload = [
            'company_id' => (string)Config::get('qms.company_id'),
            'element_id' => null,
            'suggestion_type' => 'document',
            'title' => $title,
            'content' => '参考程序：' . $referenceTitle
                . '；未找到可信现用程序匹配。建议人工判断是否新增现用程序、关联到已有程序，或仅作为参考修订材料。',
            'evidence' => '参考程序结构块：' . (string)$block->id
                . '；源定位：' . (string)$block->source_locator
                . '。智能体只记录建议/缺口，不自动修改正式体系数据。',
            'status' => 'open',
            'modified' => $now,
        ];
        if ($suggestion) {
            Db::name('qms_agent_suggestions')->where('id', (string)$suggestion['id'])->update($payload);
            return;
        }

        $payload['id'] = qms_uuid();
        $payload['created'] = $now;
        Db::name('qms_agent_suggestions')->insert($payload);
    }

    private static function deleteOpenReferenceProcedureComparisonSuggestions(string $referenceTitle): void
    {
        Db::name('qms_agent_suggestions')
            ->where('suggestion_type', 'document')
            ->whereIn('title', [
                '对照参考程序：' . $referenceTitle,
                '块级对照参考程序：' . $referenceTitle,
            ])
            ->where('status', 'open')
            ->delete();
    }

    private static function currentProcedureForReferenceTitle(string $referenceTitle): ?array
    {
        $manualMatch = self::manualReferenceProcedureMatchForTitle($referenceTitle);
        if ($manualMatch !== null) {
            return $manualMatch;
        }

        $title = trim((string)preg_replace('/^CX[-－]?\d+\s*/iu', '', $referenceTitle));
        if ($title === '') {
            return null;
        }

        $exact = Document::where('level', 2)
            ->where('title', $title)
            ->where('soft_delete', 0)
            ->field('id,doc_number,title')
            ->find();
        if ($exact) {
            return array_merge($exact->toArray(), ['_match_source' => 'auto']);
        }

        $referenceKey = self::referenceProcedureTitleKey($title);
        $best = null;
        $bestScore = 0;
        foreach (Document::where('level', 2)->where('soft_delete', 0)->field('id,doc_number,title')->select() as $procedure) {
            $currentKey = self::referenceProcedureTitleKey((string)$procedure->title);
            $score = 0;
            if ($currentKey !== '' && ($currentKey === $referenceKey || str_contains($currentKey, $referenceKey) || str_contains($referenceKey, $currentKey))) {
                $score = 100;
            } elseif (self::hasReliableReferenceProcedureTitleOverlap($title, (string)$procedure->title)) {
                similar_text($referenceKey, $currentKey, $percent);
                $score = (int)$percent;
            }
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $procedure->toArray();
            }
        }

        return $bestScore >= 74 && is_array($best)
            ? array_merge($best, ['_match_source' => 'auto'])
            : null;
    }

    private static function sourceLinesFromPath(string $relativePath): array
    {
        $absolutePath = self::workspacePath($relativePath);
        if ($relativePath === '' || !is_file($absolutePath)) {
            return [];
        }

        $extension = strtolower((string)pathinfo($absolutePath, PATHINFO_EXTENSION));
        $lines = [];
        if ($extension === 'docx') {
            $lines = self::extractDocxTextLines($absolutePath);
        }
        if ($lines === []) {
            $lines = self::extractTextWithTextutil($absolutePath);
        }
        if ($lines === [] && in_array($extension, ['txt', 'md'], true)) {
            $content = file_get_contents($absolutePath);
            $lines = $content === false ? [] : (preg_split('/\R/u', $content) ?: []);
        }

        return $lines;
    }

    private static function compactLookupText(string $value): string
    {
        $value = preg_replace('/\.(docx?|xlsx?|pdf|json)$/iu', '', $value) ?? $value;
        $value = preg_replace('/[^\p{Han}A-Za-z0-9]+/u', '', $value) ?? $value;

        return mb_strtolower($value, 'UTF-8');
    }

    private static function extractDocxTextLines(string $path): array
    {
        if (!class_exists(ZipArchive::class)) {
            return self::extractTextWithTextutil($path);
        }

        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            return self::extractTextWithTextutil($path);
        }
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();
        if (!is_string($xml) || $xml === '') {
            return self::extractTextWithTextutil($path);
        }

        $dom = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $loaded = $dom->loadXML($xml);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        if (!$loaded) {
            return self::extractTextWithTextutil($path);
        }

        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
        $body = $xpath->query('//w:body')->item(0);
        if (!$body) {
            return self::extractTextWithTextutil($path);
        }

        $lines = [];
        foreach ($body->childNodes as $node) {
            if (!$node instanceof DOMElement) {
                continue;
            }
            if ($node->localName === 'p') {
                $lines[] = self::docxNodeText($xpath, $node);
                continue;
            }
            if ($node->localName === 'tbl') {
                foreach ($xpath->query('.//w:tr', $node) as $row) {
                    if (!$row instanceof DOMElement) {
                        continue;
                    }
                    $cells = [];
                    foreach ($xpath->query('./w:tc', $row) as $cell) {
                        if ($cell instanceof DOMElement) {
                            $text = self::normalizeSourceLine(self::docxNodeText($xpath, $cell));
                            if ($text !== '') {
                                $cells[] = $text;
                            }
                        }
                    }
                    if ($cells !== []) {
                        $lines[] = implode(' | ', $cells);
                    }
                }
            }
        }

        return $lines !== [] ? $lines : self::extractTextWithTextutil($path);
    }

    private static function extractTextWithTextutil(string $path): array
    {
        $output = shell_exec('textutil -convert txt -stdout ' . escapeshellarg($path) . ' 2>/dev/null');
        if (!is_string($output) || $output === '') {
            return [];
        }

        return preg_split('/\R/u', $output) ?: [];
    }

    private static function documentLinesToMarkdown(array $lines, int $maxLines): string
    {
        $output = [];
        $title = '';
        foreach ($lines as $rawLine) {
            $line = self::normalizeSourceLine((string)$rawLine);
            if ($line === '' || self::isDocumentMetaLine($line)) {
                continue;
            }
            if ($title === '' && self::sectionHeading($line) === null) {
                $title = $line;
                $output[] = '# ' . $line;
                continue;
            }
            if ($title !== '' && $line === $title) {
                continue;
            }
            $heading = self::sectionHeading($line);
            if ($heading !== null) {
                $headingLine = '## ' . $heading;
                if (($output[count($output) - 1] ?? '') !== $headingLine) {
                    $output[] = $headingLine;
                }
                continue;
            }
            $output[] = $line;
        }

        if ($maxLines > 0 && count($output) > $maxLines) {
            $selected = array_slice($output, 0, $maxLines);
            $seen = array_fill_keys($selected, true);
            foreach (array_slice($output, $maxLines) as $line) {
                if (!str_contains($line, '《') || isset($seen[$line])) {
                    continue;
                }
                $selected[] = $line;
                $seen[$line] = true;
                if (count($selected) >= $maxLines + 20) {
                    break;
                }
            }
            $output = $selected;
        }

        return trim(implode("\n\n", $output));
    }

    private static function normalizeSourceLine(string $line): string
    {
        $line = preg_replace('/[\x{200E}\x{200F}\x{202A}-\x{202E}]/u', '', $line) ?? $line;
        $line = str_replace(["\xc2\xa0", "\t"], ' ', $line);
        $line = preg_replace('/^[\s　•·●○▪-]+/u', '', $line) ?? $line;
        $line = preg_replace('/[ \t　]+/u', ' ', $line) ?? $line;

        return trim($line);
    }

    private static function docxNodeText(DOMXPath $xpath, DOMElement $node): string
    {
        $text = '';
        foreach ($xpath->query('.//w:t | .//w:tab | .//w:br', $node) as $part) {
            if (!$part instanceof DOMElement) {
                continue;
            }
            if ($part->localName === 'tab') {
                $text .= ' ';
            } elseif ($part->localName === 'br') {
                $text .= "\n";
            } else {
                $text .= $part->textContent;
            }
        }

        return $text;
    }

    private static function isDocumentMetaLine(string $line): bool
    {
        return (bool)preg_match('/^(程序文件|质量手册)\s+编号：/u', $line);
    }

    private static function sectionHeading(string $line): ?string
    {
        $candidate = preg_replace('/^\d+(?:\.\d+)*[、.．]?\s*/u', '', $line) ?? $line;
        $candidate = preg_replace('/^[一二三四五六七八九十]+[、.．]\s*/u', '', $candidate) ?? $candidate;
        $candidate = trim($candidate, " ：:;；");
        $aliases = [
            '适用范围' => '范围',
            '程序、内容和要求' => '工作程序',
            '程序内容和要求' => '工作程序',
            '程序及内容和要求' => '工作程序',
            '内容和要求' => '工作程序',
            '相关记录' => '记录',
            '记录表格' => '记录',
        ];
        $candidate = $aliases[$candidate] ?? $candidate;
        $headings = ['目的', '范围', '职责', '工作程序', '相关文件', '记录', '引用文件', '定义', '术语和定义', '质量记录', '附录'];

        return in_array($candidate, $headings, true) ? $candidate : null;
    }

    private static function archiveFile(string $absolutePath, string $sourceKind, string $normalizedName): string
    {
        if (!is_file($absolutePath)) {
            return '';
        }
        $relative = 'runtime/qms_archive/' . $sourceKind . '/' . $normalizedName;
        $target = self::appPath($relative);
        $dir = dirname($target);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        if (!is_file($target) || hash_file('sha256', $target) !== hash_file('sha256', $absolutePath)) {
            copy($absolutePath, $target);
        }

        return $relative;
    }

    private static function normalizedFileName(string $number, string $title, string $version, string $extension): string
    {
        $base = trim($number . '-' . $title . '-' . $version, '-');
        $base = self::safeToken($base);

        return $base . ($extension !== '' ? '.' . $extension : '');
    }

    private static function safeToken(string $value): string
    {
        $value = trim($value);
        $value = preg_replace('/[^\p{Han}A-Za-z0-9._-]+/u', '_', $value) ?? $value;
        $value = trim($value, '._-');

        return $value !== '' ? mb_substr($value, 0, 180) : substr(sha1($value), 0, 12);
    }

    private static function structuredDocumentStorageToken(QmsStructuredDocument $structured): string
    {
        $base = trim((string)$structured->doc_number . '-' . (string)$structured->version, '-');
        if (self::requiresSourceSpecificStorageToken($structured)) {
            $assetId = (string)$structured->source_asset_id;
            $suffix = trim((string)$structured->title . '-' . substr($assetId, 0, 8), '-');
            $base = trim($base . '-' . $suffix, '-');
        }

        return self::safeToken($base !== '' ? $base : (string)$structured->id);
    }

    private static function requiresSourceSpecificStorageToken(QmsStructuredDocument $structured): bool
    {
        if ((string)$structured->document_role !== 'record_form') {
            return false;
        }
        $docNumber = (string)$structured->doc_number;
        if ($docNumber === '') {
            return false;
        }

        $templateCount = RecordFormTemplate::where('doc_number', $docNumber)
            ->where('version', (string)$structured->version)
            ->where('soft_delete', 0)
            ->count();
        if ((int)$templateCount > 1) {
            return true;
        }

        return QmsStructuredDocument::where('document_role', 'record_form')
            ->where('doc_number', $docNumber)
            ->where('version', (string)$structured->version)
            ->where('soft_delete', 0)
            ->count() > 1;
    }

    private static function stableToken(string $value): string
    {
        $value = trim($value);
        $value = preg_replace('/[^A-Za-z0-9_]+/u', '_', $value) ?? $value;
        $value = trim($value, '_');

        return $value !== '' ? strtolower($value) : substr(sha1($value), 0, 12);
    }

    private static function ensureStructuredDocumentIndexes(): void
    {
        static $ensured = false;
        if ($ensured) {
            return;
        }

        self::deduplicateStructuredDocumentsBySourceAsset();

        $oldUnique = Db::query(
            "SELECT COUNT(*) count
             FROM information_schema.statistics
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'qms_structured_documents'
               AND INDEX_NAME = 'structured_document'"
        );
        if ((int)($oldUnique[0]['count'] ?? 0) > 0) {
            Db::execute('ALTER TABLE `qms_structured_documents` DROP INDEX `structured_document`');
        }

        $assetUnique = Db::query(
            "SELECT COUNT(*) count
             FROM information_schema.statistics
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'qms_structured_documents'
               AND INDEX_NAME = 'structured_document_source_asset'"
        );
        if ((int)($assetUnique[0]['count'] ?? 0) === 0) {
            Db::execute('ALTER TABLE `qms_structured_documents` ADD UNIQUE KEY `structured_document_source_asset` (`document_role`,`source_asset_id`)');
        }

        $lookup = Db::query(
            "SELECT COUNT(*) count
             FROM information_schema.statistics
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'qms_structured_documents'
               AND INDEX_NAME = 'structured_document_lookup'"
        );
        if ((int)($lookup[0]['count'] ?? 0) === 0) {
            Db::execute('ALTER TABLE `qms_structured_documents` ADD KEY `structured_document_lookup` (`document_role`,`doc_number`,`version`)');
        }

        $ensured = true;
    }

    private static function ensureDocumentAssetIndexes(): void
    {
        static $ensured = false;
        if ($ensured) {
            return;
        }

        self::deduplicateDocumentAssetsByTemplate();

        $oldUnique = Db::query(
            "SELECT COUNT(*) count
             FROM information_schema.statistics
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'qms_document_assets'
               AND INDEX_NAME = 'asset_original_path'
               AND NON_UNIQUE = 0"
        );
        if ((int)($oldUnique[0]['count'] ?? 0) > 0) {
            Db::execute('ALTER TABLE `qms_document_assets` DROP INDEX `asset_original_path`');
        }

        $pathLookup = Db::query(
            "SELECT COUNT(*) count
             FROM information_schema.statistics
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'qms_document_assets'
               AND INDEX_NAME = 'asset_original_path_lookup'"
        );
        if ((int)($pathLookup[0]['count'] ?? 0) === 0) {
            Db::execute('ALTER TABLE `qms_document_assets` ADD KEY `asset_original_path_lookup` (`source_kind`,`original_path`)');
        }

        $templateUnique = Db::query(
            "SELECT COUNT(*) count
             FROM information_schema.statistics
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'qms_document_assets'
               AND INDEX_NAME = 'asset_record_form_template'"
        );
        if ((int)($templateUnique[0]['count'] ?? 0) === 0) {
            Db::execute('ALTER TABLE `qms_document_assets` ADD UNIQUE KEY `asset_record_form_template` (`source_kind`,`record_form_template_id`)');
        }

        $ensured = true;
    }

    private static function deduplicateDocumentAssetsByTemplate(): void
    {
        $groups = Db::query(
            "SELECT source_kind, record_form_template_id, GROUP_CONCAT(id ORDER BY soft_delete ASC, modified DESC SEPARATOR ',') ids
             FROM qms_document_assets
             WHERE record_form_template_id IS NOT NULL
             GROUP BY source_kind, record_form_template_id
             HAVING COUNT(*) > 1"
        );
        foreach ($groups as $group) {
            $ids = array_values(array_filter(explode(',', (string)($group['ids'] ?? ''))));
            if (count($ids) < 2) {
                continue;
            }
            $keepId = (string)$ids[0];
            foreach ($ids as $id) {
                if ((string)$id === $keepId) {
                    continue;
                }
                QmsDocumentAsset::where('id', (string)$id)->update([
                    'record_form_template_id' => null,
                    'publish' => 0,
                    'soft_delete' => 1,
                ]);
            }
        }
    }

    private static function deduplicateStructuredDocumentsBySourceAsset(): void
    {
        $groups = Db::query(
            "SELECT source_asset_id, GROUP_CONCAT(id ORDER BY soft_delete ASC, modified DESC SEPARATOR ',') ids
             FROM qms_structured_documents
             WHERE source_asset_id IS NOT NULL
             GROUP BY source_asset_id
             HAVING COUNT(*) > 1"
        );
        foreach ($groups as $group) {
            $assetId = (string)($group['source_asset_id'] ?? '');
            $ids = array_values(array_filter(explode(',', (string)($group['ids'] ?? ''))));
            if ($assetId === '' || count($ids) < 2) {
                continue;
            }

            $asset = QmsDocumentAsset::where('id', $assetId)->where('soft_delete', 0)->find();
            $template = $asset && (string)($asset->record_form_template_id ?? '') !== ''
                ? RecordFormTemplate::where('id', (string)$asset->record_form_template_id)->where('soft_delete', 0)->find()
                : null;
            $keepId = (string)$ids[0];
            if ($template) {
                foreach ($ids as $id) {
                    $row = QmsStructuredDocument::where('id', $id)->find();
                    if (!$row) {
                        continue;
                    }
                    if ((string)$row->doc_number === (string)$template->doc_number
                        && (string)$row->title === (string)$template->name) {
                        $keepId = (string)$id;
                        break;
                    }
                }
            }

            foreach ($ids as $id) {
                if ((string)$id === $keepId) {
                    continue;
                }
                QmsStructuredDocument::where('id', (string)$id)->update([
                    'source_asset_id' => null,
                    'publish' => 0,
                    'soft_delete' => 1,
                ]);
            }
        }
    }

    private static function ensureChangeLogTable(): void
    {
        static $ensured = false;
        if ($ensured) {
            return;
        }

        Db::execute("CREATE TABLE IF NOT EXISTS `qms_document_change_logs` (
  `id` varchar(36) NOT NULL,
  `company_id` varchar(36) NOT NULL,
  `structured_document_id` varchar(36) NOT NULL,
  `block_id` varchar(36) DEFAULT NULL,
  `document_id` varchar(36) DEFAULT NULL,
  `change_type` enum('block_update','render','status_change','version_update') DEFAULT 'block_update',
  `revision_note` text NOT NULL,
  `old_markdown_sha256` char(64) DEFAULT NULL,
  `new_markdown_sha256` char(64) DEFAULT NULL,
  `old_excerpt` text,
  `new_excerpt` text,
  `rendered_file_path` varchar(500) DEFAULT NULL,
  `archive_path` varchar(500) DEFAULT NULL,
  `trace_snapshot_json` mediumtext,
  `status_from` varchar(80) DEFAULT NULL,
  `status_to` varchar(80) DEFAULT NULL,
  `publish` tinyint(1) DEFAULT 1,
  `soft_delete` tinyint(1) DEFAULT 0,
  `created` datetime DEFAULT NULL,
  `modified` datetime DEFAULT NULL,
  `created_by` varchar(36) DEFAULT NULL,
  `modified_by` varchar(36) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `structured_document_id` (`structured_document_id`),
  KEY `block_id` (`block_id`),
  KEY `document_id` (`document_id`),
  KEY `change_type` (`change_type`),
  KEY `created` (`created`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $traceSnapshotColumn = Db::query(
            "SELECT COUNT(*) count
             FROM information_schema.columns
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'qms_document_change_logs'
               AND COLUMN_NAME = 'trace_snapshot_json'"
        );
        if ((int)($traceSnapshotColumn[0]['count'] ?? 0) === 0) {
            Db::execute('ALTER TABLE `qms_document_change_logs` ADD COLUMN `trace_snapshot_json` mediumtext DEFAULT NULL AFTER `archive_path`');
        }

        $ensured = true;
    }

    private static function ensureReferenceProcedureMatchTable(): void
    {
        static $ensured = false;
        if ($ensured) {
            return;
        }

        Db::execute("CREATE TABLE IF NOT EXISTS `qms_reference_procedure_matches` (
  `id` varchar(36) NOT NULL,
  `company_id` varchar(36) NOT NULL,
  `reference_doc_number` varchar(80) NOT NULL,
  `reference_section_number` varchar(80) NOT NULL,
  `reference_title` varchar(300) NOT NULL,
  `reference_block_id` varchar(36) DEFAULT NULL,
  `procedure_document_id` varchar(36) NOT NULL,
  `match_source` enum('manual') DEFAULT 'manual',
  `status` enum('active','retired') DEFAULT 'active',
  `review_note` text NOT NULL,
  `created` datetime DEFAULT NULL,
  `modified` datetime DEFAULT NULL,
  `created_by` varchar(36) DEFAULT NULL,
  `modified_by` varchar(36) DEFAULT NULL,
  `soft_delete` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `reference_manual_match` (`reference_doc_number`,`reference_section_number`,`status`,`soft_delete`),
  KEY `procedure_document_id` (`procedure_document_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $ensured = true;
    }

    private static function workspacePath(string $relativePath): string
    {
        if ($relativePath === '') {
            return '';
        }
        if (str_starts_with($relativePath, DIRECTORY_SEPARATOR)) {
            return $relativePath;
        }

        $workspacePath = dirname(self::appRoot()) . DIRECTORY_SEPARATOR . $relativePath;
        if (file_exists($workspacePath)) {
            return $workspacePath;
        }
        $publicPath = self::appRoot() . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . $relativePath;
        if (file_exists($publicPath)) {
            return $publicPath;
        }

        return self::appRoot() . DIRECTORY_SEPARATOR . $relativePath;
    }

    private static function appPath(string $relativePath): string
    {
        return self::appRoot() . DIRECTORY_SEPARATOR . ltrim($relativePath, DIRECTORY_SEPARATOR);
    }

    private static function appRoot(): string
    {
        return rtrim(dirname(__DIR__, 2), DIRECTORY_SEPARATOR);
    }
}
