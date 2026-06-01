<?php
declare(strict_types=1);

namespace app\service;

class RecordFormReconstructionReviewService
{
    private const REVIEW_FILE = 'database/schemas/record_form_reconstruction_review.json';
    private const FORWARD_CHAIN_FILE = 'database/schemas/record_form_forward_chain_decisions.json';
    private const SUMMARY_DOC_PREFIX = 'docs/RECORD_FORM_RECONSTRUCTION_REVIEW_';

    public static function reviewFilePath(): string
    {
        return self::appRoot() . self::REVIEW_FILE;
    }

    public static function forwardChainDecisionFilePath(): string
    {
        return self::appRoot() . self::FORWARD_CHAIN_FILE;
    }

    public static function markdownReportPath(?string $date = null): string
    {
        return self::repoRoot() . self::SUMMARY_DOC_PREFIX . ($date ?? date('Ymd')) . '.md';
    }

    public static function reviewAll(?string $moduleFilter = null, ?string $docNumberFilter = null, string $stage = 'both'): array
    {
        $itemsByKey = [];

        foreach (RecordFormSchemaRebuilder::listFiles($moduleFilter) as $path) {
            $packet = self::reviewStructuredFile($path);
            if (!self::matchesFilter($packet, $moduleFilter, $docNumberFilter)) {
                continue;
            }
            $itemsByKey[$packet['identity_key']] = $packet;
        }

        try {
            foreach (RecordFormBatchTemplateService::manifest() as $row) {
                $packet = self::reviewPreparedRecord($row);
                if (!self::matchesFilter($packet, $moduleFilter, $docNumberFilter)) {
                    continue;
                }
                $itemsByKey[$packet['identity_key']] = $itemsByKey[$packet['identity_key']] ?? $packet;
            }
        } catch (\Throwable) {
            // The service remains usable in isolated tests even when manifest inputs are incomplete.
        }

        $items = array_values($itemsByKey);
        usort($items, static fn(array $left, array $right): int => [
            $left['doc_number'],
            $left['name'],
            $left['source_file_name'],
        ] <=> [
            $right['doc_number'],
            $right['name'],
            $right['source_file_name'],
        ]);

        $summary = [
            'ready_for_rebuild' => 0,
            'needs_system_link' => 0,
            'needs_human_scope' => 0,
            'archive_only' => 0,
            'identity_conflict' => 0,
        ];
        foreach ($items as $item) {
            $decision = (string)($item['decision'] ?? 'needs_human_scope');
            $summary[$decision] = ($summary[$decision] ?? 0) + 1;
        }

        return [
            'generated_at' => date('Y-m-d H:i:s'),
            'scope' => [
                'module' => $moduleFilter,
                'doc_number' => $docNumberFilter,
                'stage' => $stage,
                'total' => count($items),
            ],
            'summary' => $summary,
            'items' => $items,
        ];
    }

    public static function reviewStructuredFile(string $mdPath, array $options = []): array
    {
        $content = (string)file_get_contents($mdPath);
        $parsedDocNumber = RecordFormSchemaRebuilder::parseDocNumber($content) ?? '';
        $docNumber = (string)($options['force_doc_number'] ?? $parsedDocNumber);
        $name = RecordFormSchemaRebuilder::parseTemplateName($content) ?? basename($mdPath, '.md');
        $module = RecordFormSchemaRebuilder::parseModule($content) ?? '';
        $sourceMarkdown = RecordFormSchemaRebuilder::extractSourceMarkdown($content);
        $oldSchema = RecordFormSchemaRebuilder::parseExistingSchema($content);
        $identity = RecordFormSchemaRebuilder::analyzeDocNumberIdentity($docNumber, $sourceMarkdown);
        $recordRequirement = self::extractSection($content, '程序记录要求来源');
        $constructionEvidence = self::extractSection($content, 'schema构建依据');
        $elementName = self::extractLineValue($content, '关联要素');
        $sourceStem = pathinfo($mdPath, PATHINFO_FILENAME);
        $forwardRecord = self::applyForwardChainDecision([
            'doc_number' => $docNumber,
            'name' => $name,
            'module' => $module,
            'source_file_name' => basename($mdPath),
            'source_file_path' => $mdPath,
            'reference' => $recordRequirement,
            'suggestion' => $constructionEvidence,
        ]);

        $forwardDecision = is_array($forwardRecord['_forward_chain_decision'] ?? null)
            ? $forwardRecord['_forward_chain_decision']
            : [];
        $canonicalDocNumber = $forwardDecision !== []
            ? (string)($forwardRecord['doc_number'] ?? $docNumber)
            : (string)($identity['doc_number'] ?? $docNumber);
        if ($canonicalDocNumber === '') {
            $canonicalDocNumber = (string)($identity['doc_number'] ?? $docNumber);
        }
        $name = (string)($forwardRecord['name'] ?? $name);
        $module = (string)($forwardRecord['module'] ?? $module);
        $recordRequirement = self::mergeForwardChainText($recordRequirement, $forwardDecision, 'record_requirement');
        $constructionEvidence = self::mergeForwardChainText($constructionEvidence, $forwardDecision, 'reason');

        $packet = self::basePacket([
            'doc_number' => $canonicalDocNumber,
            'original_doc_number' => $docNumber,
            'name' => $name,
            'module' => $module,
            'source_file_name' => basename($mdPath),
            'source_file_stem' => $sourceStem,
            'source_file_sha1' => is_file($mdPath) ? (string)sha1_file($mdPath) : '',
            'source_doc_numbers' => $identity['source_doc_numbers'] ?? [],
            'source_excerpt' => $sourceMarkdown,
            'old_schema' => $oldSchema,
            'record_requirement' => $recordRequirement,
            'construction_evidence' => $constructionEvidence,
            'element_name' => $elementName,
            'renumbered' => !empty($identity['renumbered']) || !empty($forwardRecord['_renumbered']),
            'identity_reason' => (string)($forwardRecord['_identity_reason'] ?? ($identity['reason'] ?? '')),
            'forward_chain_decision' => $forwardDecision,
        ]);

        if (!empty($packet['renumbered']) && empty($packet['record_list']['found'])
            && str_contains($recordRequirement, $docNumber)) {
            $packet['record_list'] = [
                'found' => true,
                'detail' => '程序记录要求使用临时编号 ' . $docNumber . '，已按源摘录正式编号 ' . $canonicalDocNumber . ' 归并。',
            ];
            $packet['evidence_keys'][] = 'record_list';
            $packet['evidence_keys'] = array_values(array_unique($packet['evidence_keys']));
        }

        if (!empty($identity['conflict'])) {
            $packet['decision'] = 'identity_conflict';
            $packet['issues'][] = (string)($identity['reason'] ?? '源摘录记录编号冲突');
            $packet['missing_obligations'][] = 'identity';
            return $packet;
        }

        return self::decidePacket($packet);
    }

    public static function reviewPreparedRecord(array $row): array
    {
        $originalDocNumber = (string)($row['doc_number'] ?? '');
        $canonical = self::canonicalizePreparedRecord($row);
        $docNumber = (string)($canonical['doc_number'] ?? $originalDocNumber);
        $name = (string)($canonical['name'] ?? $canonical['current_name'] ?? $row['name'] ?? $row['current_name'] ?? '');
        $sourceFileName = (string)($canonical['source_file_name'] ?? $row['source_file_name'] ?? basename((string)($row['source_file_path'] ?? '')));
        $sourceStem = $sourceFileName !== '' ? pathinfo($sourceFileName, PATHINFO_FILENAME) : self::identitySafe($name);
        $forwardDecision = is_array($canonical['_forward_chain_decision'] ?? null)
            ? $canonical['_forward_chain_decision']
            : [];

        $packet = self::basePacket([
            'doc_number' => $docNumber,
            'original_doc_number' => (string)($canonical['_original_doc_number'] ?? $originalDocNumber),
            'name' => $name,
            'module' => (string)($canonical['module'] ?? $row['module'] ?? ''),
            'source_file_name' => $sourceFileName,
            'source_file_stem' => $sourceStem,
            'source_file_sha1' => (string)($canonical['source_file_sha1'] ?? $row['source_file_sha1'] ?? ''),
            'source_doc_numbers' => [],
            'source_excerpt' => '',
            'old_schema' => is_array($canonical['field_schema'] ?? $row['field_schema'] ?? null) ? ($canonical['field_schema'] ?? $row['field_schema']) : [],
            'record_requirement' => (string)($canonical['reference'] ?? $row['reference'] ?? ''),
            'construction_evidence' => (string)($canonical['suggestion'] ?? $row['suggestion'] ?? ''),
            'element_name' => '',
            'renumbered' => (bool)($canonical['renumbered'] ?? false),
            'identity_reason' => (string)($canonical['reason'] ?? ''),
            'forward_chain_decision' => $forwardDecision,
        ]);

        if (empty($packet['record_list']['found']) && trim((string)($row['reference'] ?? '')) !== '') {
            $packet['record_list'] = [
                'found' => true,
                'detail' => '导入预览参考记录要求：' . trim((string)$row['reference']),
            ];
            $packet['evidence_keys'][] = 'record_list';
            $packet['evidence_keys'] = array_values(array_unique($packet['evidence_keys']));
        }

        if (($row['import_action'] ?? '') === '跳过') {
            $packet['decision'] = 'archive_only';
            $packet['issues'][] = '导入预览已标记为历史记录非模板。';
            return $packet;
        }

        return self::decidePacket($packet);
    }

    public static function canPublishTemplate(object $template): array
    {
        $missingLayers = [];
        $messages = [];

        if (!in_array((string)($template->review_status ?? ''), ['field_confirmed', 'completed'], true)) {
            $missingLayers[] = 'field_confirmed';
            $messages[] = '字段义务尚未经过 field_confirmed 确认';
        }

        $packet = self::packetForTemplate($template);
        if ($packet === null) {
            $missingLayers[] = 'reconstruction_packet';
            $messages[] = '未找到重构准备审查包';
        }

        $schema = [];
        try {
            $schema = RecordFormSchemaService::decode((string)($template->field_schema ?? '[]'));
        } catch (\Throwable) {
            $missingLayers[] = 'field_schema';
            $messages[] = '字段 schema 不是有效 JSON';
        }

        $coverage = $packet !== null && $schema !== []
            ? self::schemaCoverage($schema, $packet)
            : ['passes' => false, 'missing' => []];

        if (!($coverage['passes'] ?? false) && ($coverage['missing'] ?? []) !== []) {
            $missingLayers = array_merge($missingLayers, (array)$coverage['missing']);
        }

        $missingLayers = array_values(array_unique($missingLayers));
        $allowed = $missingLayers === [];

        return [
            'allowed' => $allowed,
            'decision' => $packet !== null ? (string)($packet['decision'] ?? 'needs_human_scope') : 'needs_human_scope',
            'missing_layers' => $missingLayers,
            'message' => $allowed
                ? '重构准备审查和字段覆盖均已通过。'
                : '建议关注：' . implode('；', $messages ?: ['部分层级未通过自动检查']),
            'packet' => $packet,
            'schema_coverage' => $coverage,
        ];
    }

    public static function assertReadyForRebuild(string $mdPath): array
    {
        $packet = self::reviewStructuredFile($mdPath);
        if (($packet['decision'] ?? '') !== 'ready_for_rebuild') {
            return [
                'ready' => false,
                'packet' => $packet,
                'message' => '重构准备审查未通过：' . implode('；', (array)($packet['issues'] ?? [])),
            ];
        }

        return [
            'ready' => true,
            'packet' => $packet,
            'message' => '重构准备审查通过。',
        ];
    }

    public static function registrySummary(array $packet): array
    {
        return [
            'decision' => (string)($packet['decision'] ?? 'needs_human_scope'),
            'identity_key' => (string)($packet['identity_key'] ?? ''),
            'evidence_keys' => (array)($packet['evidence_keys'] ?? []),
            'missing_obligations' => (array)($packet['missing_obligations'] ?? []),
            'field_obligations' => (array)($packet['field_obligations'] ?? []),
            'forward_chain' => (array)($packet['forward_chain'] ?? []),
            'issues' => (array)($packet['issues'] ?? []),
        ];
    }

    public static function loadForwardChainDecisions(): array
    {
        $path = self::forwardChainDecisionFilePath();
        if (!is_file($path)) {
            return [];
        }

        $decoded = json_decode((string)file_get_contents($path), true);
        return is_array($decoded['decisions'] ?? null) ? $decoded['decisions'] : [];
    }

    public static function applyForwardChainDecision(array $record): array
    {
        $decision = self::findForwardChainDecision($record);
        if ($decision === null) {
            return $record;
        }

        $canonical = is_array($decision['canonical'] ?? null) ? $decision['canonical'] : [];
        $match = is_array($decision['match'] ?? null) ? $decision['match'] : [];
        $matchDocNumber = (string)($match['doc_number'] ?? '');
        $originalDocNumber = (string)($record['_original_doc_number'] ?? ($matchDocNumber !== '' ? $matchDocNumber : ($record['doc_number'] ?? '')));
        $originalName = (string)($record['_original_name'] ?? $record['name'] ?? $record['current_name'] ?? '');
        $docNumber = (string)($canonical['doc_number'] ?? $record['doc_number'] ?? '');
        $name = (string)($canonical['name'] ?? $record['name'] ?? $record['current_name'] ?? '');

        $record['_forward_chain_decision'] = $decision;
        $record['_original_doc_number'] = $originalDocNumber;
        $record['_original_name'] = $originalName;
        $record['_renumbered'] = $docNumber !== '' && $docNumber !== (string)($record['doc_number'] ?? '');
        $record['_identity_reason'] = (string)($decision['reason'] ?? '');
        $record['doc_number'] = $docNumber;
        if (array_key_exists('name', $record)) {
            $record['name'] = $name;
        }
        if (array_key_exists('current_name', $record)) {
            $record['current_name'] = $name;
        }
        if (isset($canonical['module'])) {
            $record['module'] = (string)$canonical['module'];
        }
        if (isset($canonical['print_template_key'])) {
            $record['print_template_key'] = (string)$canonical['print_template_key'];
        }

        $recordRequirement = (string)($decision['record_requirement'] ?? '');
        if ($recordRequirement !== '') {
            $record['reference'] = $recordRequirement;
        }
        if (isset($decision['reason'])) {
            $record['suggestion'] = (string)$decision['reason'];
        }
        $record['match_conclusion'] = '正向链路补全';
        $record['reason'] = (string)($decision['reason'] ?? $record['reason'] ?? '');

        return $record;
    }

    public static function schemaCoverage(array $schema, array $packet): array
    {
        $flat = self::flattenSchema($schema);
        $schemaText = implode(' ', array_map(
            static fn(array $field): string => (string)($field['key'] ?? '') . ' ' . (string)($field['label'] ?? '') . ' ' . (string)($field['type'] ?? ''),
            $flat
        ));
        $identityText = implode(' ', [
            (string)($packet['doc_number'] ?? ''),
            (string)($packet['name'] ?? ''),
            (string)($packet['module'] ?? ''),
            (string)($packet['source_file_name'] ?? ''),
        ]);
        $text = $schemaText . ' ' . $identityText;

        $checks = [
            'field_identity' => trim((string)($packet['doc_number'] ?? '')) !== ''
                && trim((string)($packet['name'] ?? '')) !== '',
            'core_record_data' => count($flat) > 0,
            'conclusion_or_judgement' => self::matchesAny($text, ['结论', '意见', '判定', '评价', '结果', '状态', '确认', '归档', '合格', '批准', '审核', '决议', '措施', '验证', '计划', '登记', '清单', 'conclusion', 'comment', 'result', 'status', 'decision', 'approval', 'approved', 'review', 'select', 'checkbox']),
            'responsible_party' => self::matchesAny($text, ['负责人', '责任人', '确认人', '评审人', '复核', '主持人', 'person', 'signature']),
            'date_or_frequency' => self::matchesAny($text, ['日期', '时间', '年度', 'date', 'time']),
            'signature_chain' => self::matchesAny($text, ['签名', '签字', '签署', '批准', '审核', '复核', '负责人', 'person', 'signature']),
            'external_register_boundary' => true,
        ];

        $missing = [];
        foreach ((array)($packet['field_obligations'] ?? []) as $obligation) {
            if (!($checks[$obligation] ?? true)) {
                $missing[] = $obligation;
            }
        }

        return [
            'passes' => $missing === [],
            'checks' => $checks,
            'missing' => $missing,
            'field_count' => count($flat),
        ];
    }

    public static function saveReview(array $review, bool $writeJson = true, bool $writeMarkdown = true): array
    {
        $paths = [];
        if ($writeJson) {
            $path = self::reviewFilePath();
            $dir = dirname($path);
            if (!is_dir($dir)) {
                mkdir($dir, 0775, true);
            }
            file_put_contents($path, json_encode($review, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n");
            $paths['json'] = $path;
        }

        if ($writeMarkdown) {
            $path = self::markdownReportPath();
            $dir = dirname($path);
            if (!is_dir($dir)) {
                mkdir($dir, 0775, true);
            }
            file_put_contents($path, self::toMarkdown($review));
            $paths['markdown'] = $path;
        }

        return $paths;
    }

    public static function loadReview(): array
    {
        $path = self::reviewFilePath();
        if (!is_file($path)) {
            return [];
        }
        $decoded = json_decode((string)file_get_contents($path), true);
        return is_array($decoded) ? $decoded : [];
    }

    private static function decidePacket(array $packet): array
    {
        if (str_starts_with((string)$packet['doc_number'], '待定-')) {
            $packet['decision'] = 'needs_human_scope';
            $packet['issues'][] = '记录编号仍为临时编号，需人工确认是否补入记录表格目录或转为非模板附件。';
            $packet['missing_obligations'][] = 'identity_scope';
            return $packet;
        }

        if (!$packet['procedure']['found']) {
            $packet['missing_obligations'][] = 'procedure';
            $packet['issues'][] = '未找到归属程序。';
        }
        if (!$packet['record_list']['found']) {
            $packet['missing_obligations'][] = 'record_list';
            $packet['issues'][] = '未找到程序记录清单或记录要求证据。';
        }
        if (!$packet['data_model']['found']) {
            $packet['missing_obligations'][] = 'data_model';
            $packet['issues'][] = '未形成可重构的数据模型边界。';
        }

        if (in_array('procedure', $packet['missing_obligations'], true)
            || in_array('record_list', $packet['missing_obligations'], true)) {
            $packet['decision'] = 'needs_system_link';
            return $packet;
        }

        if ($packet['field_obligations'] === [] || !$packet['data_model']['found']) {
            $packet['decision'] = 'needs_human_scope';
            if ($packet['field_obligations'] === []) {
                $packet['issues'][] = '未能自动识别字段义务，需要人工定义重构范围。';
            }
            return $packet;
        }

        $packet['decision'] = 'ready_for_rebuild';
        return $packet;
    }

    private static function basePacket(array $data): array
    {
        $docNumber = (string)$data['doc_number'];
        $sourceStem = (string)$data['source_file_stem'];
        $module = (string)$data['module'];
        $sourceExcerpt = (string)$data['source_excerpt'];
        $recordRequirement = (string)$data['record_requirement'];
        $constructionEvidence = (string)$data['construction_evidence'];
        $allEvidenceText = implode("\n", [$module, $sourceExcerpt, $recordRequirement, $constructionEvidence, (string)$data['name']]);
        $forwardDecision = is_array($data['forward_chain_decision'] ?? null) ? $data['forward_chain_decision'] : [];
        $fieldObligations = array_values(array_unique(array_merge(
            self::fieldObligations($allEvidenceText),
            (array)($forwardDecision['additional_field_obligations'] ?? [])
        )));
        $manual = self::manualEvidence((string)$data['element_name'], $module, $recordRequirement);
        $procedure = self::procedureEvidence($module, $recordRequirement);
        $recordList = self::recordListEvidence($docNumber, $recordRequirement, $constructionEvidence);
        $dataModel = self::dataModelEvidence((array)$data['old_schema'], $sourceExcerpt);
        $externalBoundaries = self::externalBoundaries($allEvidenceText, $forwardDecision);

        $packet = [
            'identity_key' => $docNumber . '::' . $sourceStem,
            'doc_number' => $docNumber,
            'original_doc_number' => (string)$data['original_doc_number'],
            'name' => (string)$data['name'],
            'module' => $module,
            'source_file_name' => (string)$data['source_file_name'],
            'source_file_sha1' => (string)$data['source_file_sha1'],
            'source_doc_numbers' => (array)$data['source_doc_numbers'],
            'renumbered' => (bool)$data['renumbered'],
            'identity_reason' => (string)$data['identity_reason'],
            'manual' => $manual,
            'procedure' => $procedure,
            'record_list' => $recordList,
            'data_model' => $dataModel,
            'forward_chain' => self::forwardChainEvidence($forwardDecision, $manual, $procedure, $recordList, $docNumber, (string)$data['name'], $module, (string)$data['source_file_name']),
            'field_obligations' => $fieldObligations,
            'responsibility_obligations' => self::responsibilityObligations($allEvidenceText),
            'frequency_obligations' => self::extractRequirementHints($allEvidenceText, ['每', '年度', '定期', '频次', '查新']),
            'retention_obligations' => self::extractRequirementHints($allEvidenceText, ['保存', '保留期限', '长期', '年']),
            'external_register_boundaries' => $externalBoundaries,
            'missing_obligations' => [],
            'issues' => [],
            'evidence_keys' => [],
        ];

        foreach (['manual', 'procedure', 'record_list', 'data_model'] as $key) {
            if (!empty($packet[$key]['found'])) {
                $packet['evidence_keys'][] = $key;
            }
        }

        return $packet;
    }

    private static function manualEvidence(string $elementName, string $module, string $recordRequirement): array
    {
        $detail = $elementName !== '' ? '关联要素：' . $elementName : '';
        if ($detail === '' && self::matchesAny($module . $recordRequirement, ['检测方法', '文件控制', '人员', '设备', '管理评审', '内审'])) {
            $detail = '由归属程序/记录要求反向定位手册或体系要素。';
        }

        return [
            'found' => $detail !== '',
            'detail' => $detail,
        ];
    }

    private static function procedureEvidence(string $module, string $recordRequirement): array
    {
        $docNumber = '';
        if (preg_match('/(XZTC\/CX-\d{2}-\d{4})/u', $module . "\n" . $recordRequirement, $matches)) {
            $docNumber = $matches[1];
        }

        $module = trim($module);
        $found = $docNumber !== '' || ($module !== '' && !str_contains($module, '不存在'));

        return [
            'found' => $found,
            'doc_number' => $docNumber !== '' ? $docNumber : trim($module),
            'detail' => $module !== '' ? $module : ($docNumber !== '' ? $docNumber : ''),
        ];
    }

    private static function recordListEvidence(string $docNumber, string $recordRequirement, string $constructionEvidence): array
    {
        $haystack = $recordRequirement . "\n" . $constructionEvidence;

        return [
            'found' => $docNumber !== '' && str_contains($haystack, $docNumber),
            'detail' => self::shortEvidence($haystack, $docNumber),
        ];
    }

    private static function dataModelEvidence(array $schema, string $sourceExcerpt): array
    {
        return [
            'found' => $schema !== [] || trim($sourceExcerpt) !== '',
            'detail' => $schema !== []
                ? count($schema) . ' 个既有字段 schema；按 record_form_templates.field_schema 承接。'
                : '源表摘录可形成 record_form_templates.field_schema 候选。',
        ];
    }

    private static function fieldObligations(string $text): array
    {
        $obligations = ['field_identity', 'core_record_data'];
        if (self::matchesAny($text, ['结论', '意见', '判定', '评价', '确认'])) {
            $obligations[] = 'conclusion_or_judgement';
        }
        if (self::matchesAny($text, ['负责人', '责任人', '确认人', '评审人', '复核', '主持人', '技术负责人'])) {
            $obligations[] = 'responsible_party';
        }
        if (self::matchesAny($text, ['日期', '时间', '年度', '每半年', '定期'])) {
            $obligations[] = 'date_or_frequency';
        }
        if (self::matchesAny($text, ['签名', '签字', '负责人', '评审人', '确认人', '批准'])) {
            $obligations[] = 'signature_chain';
        }
        if (self::matchesAny($text, ['现行有效标准', '查新', '外部依据', 'qms_sources'])) {
            $obligations[] = 'external_register_boundary';
        }

        return array_values(array_unique($obligations));
    }

    private static function responsibilityObligations(string $text): array
    {
        $items = [];
        foreach (['技术负责人', '质量负责人', '检测部', '评审人', '确认人', '主持人'] as $needle) {
            if (str_contains($text, $needle)) {
                $items[] = $needle;
            }
        }
        return array_values(array_unique($items));
    }

    private static function extractRequirementHints(string $text, array $needles): array
    {
        $items = [];
        foreach (preg_split('/\R/u', $text) ?: [] as $line) {
            $line = trim((string)$line);
            if ($line === '') {
                continue;
            }
            foreach ($needles as $needle) {
                if (str_contains($line, $needle)) {
                    $items[] = mb_substr($line, 0, 160);
                    break;
                }
            }
        }
        return array_values(array_unique($items));
    }

    private static function externalBoundaries(string $text, array $forwardDecision = []): array
    {
        $items = [];
        if (self::matchesAny($text, ['现行有效标准', '查新', '外部依据', '标准网站', '标准化研究所', 'qms_sources'])) {
            $items[] = '外部依据有效性、查新日期、查新结论、证据来源和下次查新由 qms_sources 承接。';
        }

        foreach ((array)($forwardDecision['forward_chain']['operation_guarantee'] ?? []) as $line) {
            $line = (string)$line;
            if (self::matchesAny($line, ['外部', 'qms_sources', '台账', '标准'])) {
                $items[] = $line;
            }
        }

        return array_values(array_unique($items));
    }

    private static function forwardChainEvidence(
        array $decision,
        array $manual,
        array $procedure,
        array $recordList,
        string $docNumber,
        string $name,
        string $module,
        string $sourceFileName
    ): array {
        $chain = is_array($decision['forward_chain'] ?? null) ? $decision['forward_chain'] : [];

        $default = [
            'manual_or_element' => [
                'found' => (bool)($manual['found'] ?? false),
                'detail' => (string)($manual['detail'] ?? ''),
            ],
            'procedure' => [
                'found' => (bool)($procedure['found'] ?? false),
                'doc_number' => (string)($procedure['doc_number'] ?? ''),
                'detail' => (string)($procedure['detail'] ?? ''),
            ],
            'work_instruction' => self::defaultWorkInstructionEvidence($docNumber, $name, $module, $sourceFileName),
            'record_form_template' => [
                'found' => (bool)($recordList['found'] ?? false),
                'doc_number' => $docNumber,
                'detail' => (string)($recordList['detail'] ?? ''),
            ],
            'operation_guarantee' => [],
        ];

        foreach (['manual_or_element', 'procedure', 'work_instruction', 'record_form_template'] as $key) {
            if (isset($chain[$key]) && is_array($chain[$key])) {
                $default[$key] = array_merge($default[$key], $chain[$key]);
            }
        }
        if (isset($chain['operation_guarantee']) && is_array($chain['operation_guarantee'])) {
            $default['operation_guarantee'] = array_values(array_filter(array_map('strval', $chain['operation_guarantee'])));
        }

        return $default;
    }

    private static function defaultWorkInstructionEvidence(string $docNumber, string $name, string $module, string $sourceFileName): array
    {
        $text = $docNumber . ' ' . $name . ' ' . $module . ' ' . $sourceFileName;
        if (self::matchesAny($text, ['红外', '光谱'])) {
            return [
                'found' => true,
                'status' => 'linked',
                'doc_number' => 'XZTC/ZY-2-13-2018',
                'detail' => '红外光谱仪作业指导书提供设备操作和性能确认依据。',
            ];
        }
        if (self::matchesAny($text, ['仪器', '设备', '核查', '标准物质'])) {
            return [
                'found' => true,
                'status' => 'variant_by_equipment',
                'detail' => '按具体设备或标准物质对应作业指导书运行。',
            ];
        }

        return [
            'found' => true,
            'status' => 'not_applicable',
            'detail' => '管理记录或档案记录不需要单独作业指导书。',
        ];
    }

    private static function mergeForwardChainText(string $baseText, array $decision, string $field): string
    {
        $extra = trim((string)($decision[$field] ?? ''));
        if ($extra === '') {
            return $baseText;
        }
        if (str_contains($baseText, $extra)) {
            return $baseText;
        }

        return trim($baseText . "\n" . $extra);
    }

    private static function findForwardChainDecision(array $record): ?array
    {
        foreach (self::loadForwardChainDecisions() as $decision) {
            if (self::matchesForwardChainDecision($record, $decision)) {
                return $decision;
            }
        }

        return null;
    }

    private static function matchesForwardChainDecision(array $record, array $decision): bool
    {
        $match = is_array($decision['match'] ?? null) ? $decision['match'] : [];
        $canonical = is_array($decision['canonical'] ?? null) ? $decision['canonical'] : [];
        $recordDocNumber = (string)($record['doc_number'] ?? '');
        $matchDocNumber = (string)($match['doc_number'] ?? '');
        $canonicalDocNumber = (string)($canonical['doc_number'] ?? '');

        if ($matchDocNumber !== '' && $recordDocNumber !== $matchDocNumber && $recordDocNumber !== $canonicalDocNumber) {
            return false;
        }
        $matchedByCanonicalNumber = $canonicalDocNumber !== ''
            && $recordDocNumber === $canonicalDocNumber
            && $recordDocNumber !== $matchDocNumber;

        $recordName = self::normalizeChineseText((string)($record['name'] ?? $record['current_name'] ?? ''));
        $matchName = self::normalizeChineseText((string)($match['name'] ?? ''));
        $sourceNeedle = (string)($match['source_file_contains'] ?? '');
        $nameMatches = $matchName === '' || $recordName === '' || str_contains($recordName, $matchName) || str_contains($matchName, $recordName);
        $sourceMatches = $sourceNeedle === '';
        if ($sourceNeedle !== '') {
            $sourceText = (string)($record['source_file_name'] ?? '') . "\n"
                . (string)($record['source_file_path'] ?? '') . "\n"
                . (string)($record['name'] ?? $record['current_name'] ?? '');
            $sourceMatches = str_contains($sourceText, $sourceNeedle);
        }

        if ($matchedByCanonicalNumber && $sourceNeedle !== '') {
            return $sourceMatches;
        }
        if ($matchName !== '' && $sourceNeedle !== '') {
            return $nameMatches || $sourceMatches;
        }
        if ($matchName !== '') {
            return $nameMatches;
        }
        if ($sourceNeedle !== '') {
            return $sourceMatches;
        }

        return true;
    }

    private static function packetForTemplate(object $template): ?array
    {
        $review = self::loadReview();
        $items = is_array($review['items'] ?? null) ? $review['items'] : [];
        $sourceStem = pathinfo((string)($template->source_file_name ?? ''), PATHINFO_FILENAME);
        $candidateKey = (string)($template->doc_number ?? '') . '::' . $sourceStem;
        foreach ($items as $item) {
            if (($item['identity_key'] ?? '') === $candidateKey) {
                return $item;
            }
        }

        foreach ($items as $item) {
            if (($item['doc_number'] ?? '') === (string)($template->doc_number ?? '')) {
                return $item;
            }
        }

        return null;
    }

    private static function flattenSchema(array $schema): array
    {
        $flat = [];
        foreach ($schema as $field) {
            if (!is_array($field)) {
                continue;
            }
            $flat[] = $field;
            foreach (($field['columns'] ?? []) as $column) {
                if (is_array($column)) {
                    $flat[] = $column;
                }
            }
        }
        return $flat;
    }

    private static function extractSection(string $markdown, string $heading): string
    {
        $pattern = '/^###\s+' . preg_quote($heading, '/') . '\s*$/mu';
        if (preg_match($pattern, $markdown, $matches, PREG_OFFSET_CAPTURE) !== 1) {
            return '';
        }
        $start = $matches[0][1] + strlen($matches[0][0]);
        $after = substr($markdown, $start);
        $next = strpos($after, "\n### ");
        if ($next !== false) {
            $after = substr($after, 0, $next);
        }
        return trim($after);
    }

    private static function extractLineValue(string $markdown, string $label): string
    {
        if (preg_match('/-\s*' . preg_quote($label, '/') . '\s*[：:]\s*(.+)$/mu', $markdown, $matches) === 1) {
            return trim($matches[1]);
        }
        return '';
    }

    private static function shortEvidence(string $text, string $needle): string
    {
        $pos = strpos($text, $needle);
        if ($pos === false) {
            return '';
        }
        $start = max(0, $pos - 80);
        return trim(mb_substr($text, $start, 180));
    }

    private static function matchesAny(string $text, array $needles): bool
    {
        foreach ($needles as $needle) {
            if ($needle !== '' && str_contains($text, $needle)) {
                return true;
            }
        }
        return false;
    }

    private static function matchesFilter(array $packet, ?string $moduleFilter, ?string $docNumberFilter): bool
    {
        if ($moduleFilter !== null && $moduleFilter !== '' && !str_contains((string)$packet['module'], $moduleFilter)) {
            return false;
        }
        if ($docNumberFilter !== null && $docNumberFilter !== ''
            && (string)$packet['doc_number'] !== $docNumberFilter
            && (string)$packet['original_doc_number'] !== $docNumberFilter) {
            return false;
        }
        return true;
    }

    private static function canonicalizePreparedRecord(array $row): array
    {
        $forwardRecord = self::applyForwardChainDecision($row);
        if (isset($forwardRecord['_forward_chain_decision'])) {
            return array_merge($forwardRecord, [
                'renumbered' => (bool)($forwardRecord['_renumbered'] ?? false),
                'reason' => (string)($forwardRecord['_identity_reason'] ?? $forwardRecord['reason'] ?? ''),
            ]);
        }

        $docNumber = (string)($row['doc_number'] ?? '');
        if (!str_starts_with($docNumber, '待定-')) {
            return ['doc_number' => $docNumber];
        }

        foreach (self::formalManifestRows() as $candidate) {
            if (!self::sameModule((string)($row['module'] ?? ''), (string)($candidate['module'] ?? ''))) {
                continue;
            }
            if (!self::sameRecordPurpose($row, $candidate)) {
                continue;
            }

            return [
                'doc_number' => (string)$candidate['doc_number'],
                'renumbered' => true,
                'reason' => '目录外附件按正式导入清单归并到 ' . (string)$candidate['doc_number'],
            ];
        }

        return ['doc_number' => $docNumber];
    }

    private static function formalManifestRows(): array
    {
        static $rows = null;
        if (is_array($rows)) {
            return $rows;
        }

        $rows = [];
        try {
            foreach (RecordFormBatchTemplateService::manifest() as $row) {
                $docNumber = (string)($row['doc_number'] ?? '');
                if (str_starts_with($docNumber, '待定-')) {
                    continue;
                }
                if (($row['import_action'] ?? '') === '跳过') {
                    continue;
                }
                $rows[] = $row;
            }
        } catch (\Throwable) {
            $rows = [];
        }

        return $rows;
    }

    private static function sameModule(string $left, string $right): bool
    {
        $left = self::normalizeChineseText($left);
        $right = self::normalizeChineseText($right);
        return $left !== '' && $right !== '' && (str_contains($left, $right) || str_contains($right, $left));
    }

    private static function sameRecordPurpose(array $row, array $candidate): bool
    {
        $name = self::normalizeChineseText((string)($row['name'] ?? $row['current_name'] ?? ''));
        $candidateName = self::normalizeChineseText((string)($candidate['name'] ?? $candidate['current_name'] ?? ''));
        if ($name !== '' && $candidateName !== '' && (str_contains($name, $candidateName) || str_contains($candidateName, $name))) {
            return true;
        }

        $reference = self::normalizeChineseText((string)($row['reference'] ?? ''));
        $candidateReference = self::normalizeChineseText((string)($candidate['reference'] ?? ''));
        if (str_contains($reference, '未列直接对应项') || str_contains($candidateReference, '未列直接对应项')) {
            return false;
        }
        return $reference !== '' && $candidateReference !== ''
            && (str_contains($reference, $candidateReference) || str_contains($candidateReference, $reference));
    }

    private static function normalizeChineseText(string $value): string
    {
        return preg_replace('/[^\p{L}\p{N}]+/u', '', $value) ?? '';
    }

    private static function toMarkdown(array $review): string
    {
        $lines = [
            '# 记录表格重构准备审查',
            '',
            '- 生成时间：' . (string)($review['generated_at'] ?? ''),
            '- 范围：' . json_encode($review['scope'] ?? [], JSON_UNESCAPED_UNICODE),
            '',
            '## 汇总',
            '',
        ];

        foreach (($review['summary'] ?? []) as $decision => $count) {
            $lines[] = '- ' . $decision . '：' . $count;
        }

        $lines[] = '';
        $lines[] = '## 需处理项目';
        $lines[] = '';
        $lines[] = '| 编号 | 名称 | 结论 | 缺失义务 | 问题 |';
        $lines[] = '|---|---|---|---|---|';

        foreach (($review['items'] ?? []) as $item) {
            if (($item['decision'] ?? '') === 'ready_for_rebuild') {
                continue;
            }
            $lines[] = '| ' . self::mdCell((string)($item['doc_number'] ?? ''))
                . ' | ' . self::mdCell((string)($item['name'] ?? ''))
                . ' | ' . self::mdCell((string)($item['decision'] ?? ''))
                . ' | ' . self::mdCell(implode('、', (array)($item['missing_obligations'] ?? [])))
                . ' | ' . self::mdCell(implode('；', (array)($item['issues'] ?? [])))
                . ' |';
        }

        return implode("\n", $lines) . "\n";
    }

    private static function mdCell(string $value): string
    {
        return str_replace(["\n", '|'], [' ', '\\|'], $value);
    }

    private static function identitySafe(string $value): string
    {
        $value = preg_replace('/[^\p{L}\p{N}_-]+/u', '_', $value) ?? 'record';
        return trim($value, '_') !== '' ? trim($value, '_') : 'record';
    }

    private static function repoRoot(): string
    {
        return dirname(self::appRoot()) . DIRECTORY_SEPARATOR;
    }

    private static function appRoot(): string
    {
        if (function_exists('root_path')) {
            return root_path();
        }
        return dirname(__DIR__, 2) . DIRECTORY_SEPARATOR;
    }
}
