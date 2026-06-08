<?php
declare(strict_types=1);

namespace app\service;

use app\model\RecordFormInstance;
use app\model\RecordFormTemplate;
use RuntimeException;
use think\facade\Config;
use think\facade\Db;

class RecordFormSourceInstanceDraftService
{
    public static function seed(array $options = []): array
    {
        $templates = self::queryTemplates($options);
        $limit = max(0, (int)($options['limit'] ?? 0));
        $year = self::targetYear($options);
        $batchId = self::batchId($options, $year);

        $apply = (bool)($options['apply'] ?? false);
        $previewPdf = (bool)($options['preview_pdf'] ?? false);
        $useAi = (bool)($options['ai'] ?? false);
        $createIncomplete = (bool)($options['create_incomplete'] ?? false);
        $skipExisting = (bool)($options['skip_existing'] ?? false);
        $rows = [];
        $summary = [
            'apply' => $apply,
            'preview_pdf' => $previewPdf,
            'ai' => $useAi,
            'year' => $year,
            'batch_id' => $batchId,
            'create_incomplete' => $createIncomplete,
            'skip_existing' => $skipExisting,
            'total' => count($templates),
            'created' => 0,
            'dry_run' => 0,
            'ready_with_gaps' => 0,
            'needs_manual_input' => 0,
            'skipped_existing' => 0,
            'errors' => 0,
            'rows' => [],
            'report' => null,
        ];

        foreach ($templates as $template) {
            try {
                if ($skipExisting && $year !== null && self::hasExistingYearInstance($template, $year)) {
                    $row = self::skippedExistingResult($template, $year);
                    $summary['skipped_existing']++;
                    $rows[] = $row;
                    continue;
                }

                $row = self::prepareTemplate($template, $useAi, $createIncomplete, $year);
                if (($row['decision'] ?? '') === 'ready_with_gaps') {
                    $summary['ready_with_gaps']++;
                }
                if (self::isCreatableDecision((string)($row['decision'] ?? '')) && $apply) {
                    $created = self::createDraftInstance($template, $row['values'], $year);
                    $row['instance_id'] = (string)$created->id;
                    $row['instance_url'] = '/record_form_instance/view?id=' . rawurlencode((string)$created->id);
                    $row['print_url'] = '/record_form_instance/print?id=' . rawurlencode((string)$created->id);
                    if ($previewPdf) {
                        try {
                            $row['preview_pdf'] = self::renderPreviewPdf($created, $template, $row['values']);
                            $row['automatic_checks']['preview_pdf_downloadable'] = !empty($row['preview_pdf']['download_url']);
                        } catch (\Throwable $exception) {
                            $row['automatic_checks']['print_template_renderable'] = false;
                            $row['automatic_checks']['preview_pdf_downloadable'] = false;
                            $row['warnings'][] = '临时预览PDF生成失败：' . $exception->getMessage();
                        }
                    }
                    $summary['created']++;
                    if ($limit > 0 && $summary['created'] >= $limit) {
                        $rows[] = $row;
                        break;
                    }
                } elseif (self::isCreatableDecision((string)($row['decision'] ?? ''))) {
                    $summary['dry_run']++;
                    if ($limit > 0 && $summary['dry_run'] >= $limit) {
                        $rows[] = $row;
                        break;
                    }
                } else {
                    $summary['needs_manual_input']++;
                }
            } catch (\Throwable $exception) {
                $row = [
                    'template_id' => (string)$template->id,
                    'doc_number' => (string)$template->doc_number,
                    'name' => (string)$template->name,
                    'decision' => 'error',
                    'error' => $exception->getMessage(),
                ];
                $summary['errors']++;
            }

            $rows[] = $row;
        }

        $summary['rows'] = $rows;
        $summary['report'] = self::writeBatchReport($summary);

        return $summary;
    }

    public static function prepareTemplate(RecordFormTemplate $template, bool $useAi = false, bool $createIncomplete = false, ?int $year = null): array
    {
        $schema = RecordFormSchemaService::decode((string)$template->field_schema);
        $source = self::sourceMarkdownForTemplate($template);
        $values = self::defaultValues($schema);
        $evidence = [];
        $lowConfidence = [];
        $warnings = [];
        $aiCandidateFields = [];
        $aiCandidateValues = [];

        if ($source['markdown'] === '') {
            $business = self::businessContextValues($template, $schema, $values, $year);
            $values = array_replace_recursive($values, $business['values']);
            $evidence = array_merge($evidence, $business['evidence']);
            $lowConfidence = array_values(array_unique(array_merge($lowConfidence, $business['low_confidence'])));
            $warnings[] = '未找到结构化源文件Markdown摘录';

            return $createIncomplete
                ? self::incompleteDraftResult($template, $schema, $values, $source, $evidence, $lowConfidence, $warnings)
                : self::manualResult($template, $schema, $values, $warnings, $source, $evidence, $lowConfidence);
        }

        $rule = self::extractRuleValues($schema, $source['markdown'], (string)$template->doc_number);
        $values = array_replace_recursive($values, $rule['values']);
        $evidence = $rule['evidence'];
        $lowConfidence = $rule['low_confidence'];
        $warnings = $rule['warnings'];

        $business = self::businessContextValues($template, $schema, $values, $year);
        $values = array_replace_recursive($values, $business['values']);
        $evidence = array_merge($evidence, $business['evidence']);
        $lowConfidence = array_values(array_unique(array_merge($lowConfidence, $business['low_confidence'])));
        $warnings = array_merge($warnings, $business['warnings']);

        if ($useAi) {
            $ai = self::extractAiValues($template, $schema, $source['markdown'], $values);
            $values = array_replace_recursive($values, $ai['values']);
            $evidence = array_merge($evidence, $ai['evidence']);
            $lowConfidence = array_values(array_unique(array_merge($lowConfidence, $ai['low_confidence'])));
            $warnings = array_merge($warnings, $ai['warnings']);
            $aiCandidateFields = array_values(array_unique(array_merge($aiCandidateFields, $ai['candidate_fields'] ?? [])));
            $aiCandidateValues = array_replace_recursive($aiCandidateValues, $ai['candidate_values'] ?? []);
        }

        $values = RecordFormSchemaService::enforceReadonly($schema, $values);
        $errors = RecordFormSchemaService::validateValues($schema, $values);
        if ($errors !== []) {
            if ($createIncomplete) {
                return self::incompleteDraftResult($template, $schema, $values, $source, $evidence, $lowConfidence, array_merge($warnings, array_values($errors)), $aiCandidateFields, $aiCandidateValues);
            }

            return self::manualResult($template, $schema, $values, array_values($errors), $source, $evidence, $lowConfidence, $warnings, $aiCandidateFields, $aiCandidateValues);
        }

        return self::readyResult($template, $schema, $values, $source, $evidence, $lowConfidence, $warnings, [], 'ready', $aiCandidateFields, $aiCandidateValues);
    }

    private static function readyResult(
        RecordFormTemplate $template,
        array $schema,
        array $values,
        array $source,
        array $evidence,
        array $lowConfidence,
        array $warnings,
        array $blankRequiredFields,
        string $decision = 'ready',
        array $aiCandidateFields = [],
        array $aiCandidateValues = []
    ): array {
        return [
            'template_id' => (string)$template->id,
            'doc_number' => (string)$template->doc_number,
            'name' => (string)$template->name,
            'module' => (string)$template->module,
            'decision' => $decision,
            'values' => $values,
            'source_markdown_path' => $source['path'],
            'evidence' => $evidence,
            'low_confidence_fields' => array_values(array_unique($lowConfidence)),
            'ai_candidate_fields' => array_values(array_unique($aiCandidateFields)),
            'ai_candidate_values' => $aiCandidateValues,
            'blank_required_fields' => $blankRequiredFields,
            'manual_layout_status' => 'pending',
            'automatic_checks' => [
                'print_template_renderable' => true,
                'preview_pdf_downloadable' => false,
                'blank_required_count' => count($blankRequiredFields),
                'field_count' => count($schema),
            ],
            'warnings' => $warnings,
        ];
    }

    private static function incompleteDraftResult(
        RecordFormTemplate $template,
        array $schema,
        array $values,
        array $source,
        array $evidence = [],
        array $lowConfidence = [],
        array $warnings = [],
        array $aiCandidateFields = [],
        array $aiCandidateValues = []
    ): array {
        $blankRequired = self::missingKeys($schema, $values);

        return self::readyResult(
            $template,
            $schema,
            $values,
            $source,
            $evidence,
            array_values(array_unique(array_merge($lowConfidence, $blankRequired))),
            $warnings,
            $blankRequired,
            $blankRequired === [] ? 'ready' : 'ready_with_gaps',
            $aiCandidateFields,
            $aiCandidateValues
        );
    }

    private static function queryTemplates(array $options): array
    {
        $module = trim((string)($options['module'] ?? ''));
        $docPrefix = trim((string)($options['doc_prefix'] ?? ''));
        $docNumber = trim((string)($options['doc_number'] ?? ''));
        $templateId = trim((string)($options['template_id'] ?? ''));
        $query = RecordFormTemplate::where('soft_delete', 0)
            ->where('status', 'published')
            ->where('print_template_key', '<>', '')
            ->where('print_template_key', '<>', 'generic_record_form');

        if ($templateId !== '') {
            $query->where('id', $templateId);
        } elseif ($docNumber !== '') {
            $query->where('doc_number', $docNumber);
        } elseif ($docPrefix !== '') {
            $query->where('doc_number', 'like', $docPrefix . '%');
        } elseif ($module !== '') {
            $query->where('module', $module);
        }

        $templates = $query->order('doc_number', 'asc')->order('name', 'asc')->select()->all();

        return array_values(array_filter($templates, static function (RecordFormTemplate $template): bool {
            $key = trim((string)$template->print_template_key);
            return preg_match('/\A[a-zA-Z0-9_-]+\z/', $key) === 1
                && is_file(root_path() . 'app' . DIRECTORY_SEPARATOR . 'record_form_print' . DIRECTORY_SEPARATOR . $key . '.php');
        }));
    }

    private static function createDraftInstance(RecordFormTemplate $template, array $values, ?int $year = null): RecordFormInstance
    {
        return RecordFormInstance::create([
            'id' => qms_uuid(),
            'template_id' => (string)$template->id,
            'template_name' => (string)$template->name,
            'template_module' => (string)$template->module,
            'template_version' => (string)$template->version,
            'template_print_template_key' => (string)$template->print_template_key,
            'template_field_schema' => (string)$template->field_schema,
            'doc_number' => (string)$template->doc_number,
            'record_title' => self::recordTitle($template, $year),
            'field_values' => json_encode($values, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) ?: '{}',
            'status' => 'draft',
        ]);
    }

    private static function renderPreviewPdf(RecordFormInstance $record, RecordFormTemplate $template, array $values): array
    {
        $templateSnapshot = [
            'id' => (string)$template->id,
            'doc_number' => (string)$template->doc_number,
            'name' => (string)$template->name,
            'module' => (string)$template->module,
            'version' => (string)$template->version,
            'status' => (string)$template->status,
            'review_status' => (string)$template->review_status,
            'print_template_key' => (string)$template->print_template_key,
            'field_schema' => (string)$template->field_schema,
            'source_file_name' => (string)$template->source_file_name,
        ];
        $html = RecordFormPrintService::render((string)$template->print_template_key, $templateSnapshot, $values);

        $pdf = PdfRenderService::renderHtmlPreview($html, (string)$record->id, (string)$record->record_title);
        $pdf['download_url'] = '/record_form_instance/downloadPreviewPdf?id=' . rawurlencode((string)$record->id)
            . '&file=' . rawurlencode((string)$pdf['file_name']);

        return $pdf;
    }

    private static function sourceMarkdownForTemplate(RecordFormTemplate $template): array
    {
        $docNumber = str_replace('/', '_', (string)$template->doc_number);
        $base = root_path() . 'runtime' . DIRECTORY_SEPARATOR . 'qms_structured' . DIRECTORY_SEPARATOR . 'record_form' . DIRECTORY_SEPARATOR;
        $boundCandidates = [];
        foreach ([(string)$template->source_file_path, (string)$template->source_file_name] as $boundPath) {
            $boundPath = trim($boundPath);
            if ($boundPath === '') {
                continue;
            }
            $fullPath = str_starts_with($boundPath, DIRECTORY_SEPARATOR) ? $boundPath : root_path() . ltrim($boundPath, '/\\');
            if (!is_file($fullPath)) {
                $fullPath = $base . basename($boundPath);
            }
            if (is_file($fullPath)) {
                $boundCandidates[] = $fullPath;
            }
        }
        foreach (array_values(array_unique($boundCandidates)) as $path) {
            $content = (string)file_get_contents($path);
            $source = self::extractSourceExcerpt($content);
            if (trim($source) !== '') {
                return [
                    'path' => str_replace(root_path(), '', $path),
                    'markdown' => $source,
                ];
            }
        }

        $candidates = glob($base . $docNumber . '*.md') ?: [];
        $matched = self::bestSourceCandidateForTemplate($template, $candidates);
        if ($matched !== null) {
            $content = (string)file_get_contents($matched);
            $source = self::extractSourceExcerpt($content);
            if (trim($source) !== '') {
                return [
                    'path' => str_replace(root_path(), '', $matched),
                    'markdown' => $source,
                ];
            }
        }

        usort($candidates, static function (string $left, string $right): int {
            if ($left === $right) {
                return 0;
            }
            $leftExact = str_ends_with($left, '-A_0.md') ? 0 : 1;
            $rightExact = str_ends_with($right, '-A_0.md') ? 0 : 1;
            return $leftExact <=> $rightExact ?: strcmp($left, $right);
        });

        foreach ($candidates as $path) {
            $content = (string)file_get_contents($path);
            $source = self::extractSourceExcerpt($content);
            if (trim($source) !== '') {
                return [
                    'path' => str_replace(root_path(), '', $path),
                    'markdown' => $source,
                ];
            }
        }

        return ['path' => '', 'markdown' => ''];
    }

    private static function bestSourceCandidateForTemplate(RecordFormTemplate $template, array $candidates): ?string
    {
        $sourceName = self::normalizeSourceToken((string)$template->source_file_name);
        $templateName = self::normalizeSourceToken((string)$template->name);
        $tokens = array_values(array_filter(array_unique([$sourceName, $templateName]), static fn (string $token): bool => mb_strlen($token) >= 8));
        if ($tokens === [] || $candidates === []) {
            return null;
        }

        $bestPath = null;
        $bestScore = 0;
        foreach ($candidates as $path) {
            $candidate = self::normalizeSourceToken(basename($path));
            $score = 0;
            foreach ($tokens as $token) {
                if (str_contains($candidate, $token)) {
                    $score = max($score, mb_strlen($token) + 100);
                } elseif (str_contains($token, $candidate)) {
                    $score = max($score, mb_strlen($candidate));
                } else {
                    similar_text($candidate, $token, $percent);
                    if ($percent >= 65) {
                        $score = max($score, (int)$percent);
                    }
                }
            }
            if ($score > $bestScore) {
                $bestScore = $score;
                $bestPath = $path;
            }
        }

        return $bestScore > 0 ? $bestPath : null;
    }

    private static function normalizeSourceToken(string $value): string
    {
        $value = pathinfo($value, PATHINFO_FILENAME);
        $value = preg_replace('/\AXZTC_BG-\d{2}-\d{2}-A_0[-_]?/i', '', $value) ?? $value;
        $value = preg_replace('/\A\d{2}-\d{2}/u', '', $value) ?? $value;
        $value = preg_replace('/[-_][0-9a-f]{8,40}\z/i', '', $value) ?? $value;
        $value = str_replace(['（', '）'], ['(', ')'], $value);
        $value = preg_replace('/[^\p{Han}a-zA-Z0-9]+/u', '', $value) ?? $value;

        return strtolower($value);
    }

    private static function extractSourceExcerpt(string $markdown): string
    {
        $marker = '### 源文件Markdown摘录';
        $pos = mb_strpos($markdown, $marker);
        if ($pos === false) {
            return '';
        }

        return trim(mb_substr($markdown, $pos + mb_strlen($marker)));
    }

    private static function extractRuleValues(array $schema, string $sourceMarkdown, string $docNumber): array
    {
        if ($docNumber === 'XZTC/BG-01-02') {
            return self::extractTrainingRecordValues($schema, $sourceMarkdown);
        }
        if ($docNumber === 'XZTC/BG-03-01') {
            return self::extractEquipmentLedgerValues($schema, $sourceMarkdown);
        }

        return self::extractGenericValues($schema, $sourceMarkdown);
    }

    private static function extractTrainingRecordValues(array $schema, string $sourceMarkdown): array
    {
        $topic = self::valueAfterLabel($sourceMarkdown, '培训主题');
        if ($topic === '') {
            $topic = self::firstHeadingOrLine($sourceMarkdown);
        }
        $trainer = self::valueAfterLabel($sourceMarkdown, '讲 师');
        $date = self::normalizeDate(self::valueAfterLabel($sourceMarkdown, '时 间'));
        $content = self::textAfterFirst($sourceMarkdown, "姓 名\n\n岗 位", 1600);
        if ($content === '') {
            $content = self::textAfterFirst($sourceMarkdown, '培训内容：', 1600);
        }
        $values = [];
        $evidence = [];
        $low = [];
        $warnings = [];

        foreach (['training_topic' => $topic, 'trainer' => $trainer, 'training_date' => $date, 'training_content' => $content] as $key => $value) {
            if ($value !== '' && self::schemaHasField($schema, $key)) {
                $values[$key] = $value;
                $evidence[] = $key . '=源文件标签抽取';
            }
        }

        if (self::schemaHasField($schema, 'attendees')) {
            $values['attendees'] = [];
            $low[] = 'attendees';
            $warnings[] = '源文件参训人员签名区未识别出明确人员行，保留为空待人工填写。';
        }
        if (self::schemaHasField($schema, 'effect_evaluation')) {
            $values['effect_evaluation'] = '';
            $low[] = 'effect_evaluation';
        }

        return ['values' => $values, 'evidence' => $evidence, 'low_confidence' => $low, 'warnings' => $warnings];
    }

    private static function extractEquipmentLedgerValues(array $schema, string $sourceMarkdown): array
    {
        $field = self::firstRepeatableField($schema);
        if ($field === null) {
            return self::extractGenericValues($schema, $sourceMarkdown);
        }

        $rows = [];
        $tokens = array_map(static fn (string $token): string => trim(preg_replace('/\s+/u', ' ', $token) ?? ''), explode("\x07", $sourceMarkdown));
        $count = count($tokens);
        for ($i = 0; $i < $count - 2; $i++) {
            if (preg_match('/^\d+$/', $tokens[$i]) !== 1 || !str_starts_with($tokens[$i + 1] ?? '', 'XZTC-')) {
                continue;
            }

            $row = [
                'equipment_code' => $tokens[$i + 1] ?? '',
                'equipment_name' => $tokens[$i + 2] ?? '',
                'model_spec' => $tokens[$i + 3] ?? '',
                'manufacturer' => $tokens[$i + 4] ?? '',
                'factory_number' => $tokens[$i + 5] ?? '',
                'purchase_date' => self::normalizeDate($tokens[$i + 6] ?? ''),
                'accuracy' => $tokens[$i + 7] ?? '',
                'measurement_range' => $tokens[$i + 8] ?? '',
                'traceability_method' => self::normalizeSelect($tokens[$i + 9] ?? '', self::columnOptions($field, 'traceability_method')),
                'remarks' => $tokens[$i + 10] ?? '',
            ];
            $rows[] = self::filterRowByColumns($row, $field['columns'] ?? []);
            $i += 10;
        }

        if ($rows === []) {
            return [
                'values' => [$field['key'] => []],
                'evidence' => [],
                'low_confidence' => [$field['key']],
                'warnings' => ['未从设备台账源摘录识别出明细行。'],
            ];
        }

        return [
            'values' => [$field['key'] => $rows],
            'evidence' => [$field['key'] . '=源文件表格抽取' . count($rows) . '行'],
            'low_confidence' => [],
            'warnings' => [],
        ];
    }

    private static function extractGenericValues(array $schema, string $sourceMarkdown): array
    {
        $values = [];
        $low = [];
        $evidence = [];
        foreach ($schema as $field) {
            if (($field['type'] ?? '') === 'repeatable_table') {
                $values[$field['key']] = [];
                $low[] = $field['key'];
                continue;
            }
            $label = (string)($field['label'] ?? '');
            $value = $label !== '' ? self::valueAfterLabel($sourceMarkdown, $label) : '';
            if ($field['type'] === 'date') {
                $value = self::normalizeDate($value);
            }
            if ($value !== '') {
                $values[$field['key']] = $value;
                $evidence[] = $field['key'] . '=源文件标签抽取';
            } elseif (!empty($field['required'])) {
                $low[] = $field['key'];
            }
        }

        return ['values' => $values, 'evidence' => $evidence, 'low_confidence' => $low, 'warnings' => []];
    }

    private static function businessContextValues(RecordFormTemplate $template, array $schema, array $currentValues, ?int $year): array
    {
        $values = [];
        $evidence = [];
        $low = [];
        $warnings = [];
        $company = self::companyContext();
        $mainSite = self::mainSiteContext();
        $applicationProfile = self::applicationProfile();

        foreach ($schema as $field) {
            $key = (string)($field['key'] ?? '');
            $label = (string)($field['label'] ?? '');
            $currentValue = $currentValues[$key] ?? null;
            if ($key === '' || (!self::fieldIsEmpty($currentValue) && !self::needsApplicationCandidate($field, $currentValue, $schema))) {
                continue;
            }

            $candidate = '';
            $candidateEvidence = '机构/系统基础数据';
            $candidateLowConfidence = false;
            if (($field['type'] ?? '') === 'repeatable_table') {
                $tableCandidate = self::applicationTableCandidate($field, $applicationProfile);
                if ($tableCandidate !== []) {
                    $values[$key] = $tableCandidate;
                    $evidence[] = $key . '=资质认定申请书';
                    $low[] = $key;
                }
                continue;
            }

            if ($year !== null && in_array($key, ['year', 'record_year', 'plan_year', 'audit_year'], true)) {
                $candidate = (string)$year;
            } elseif ($year !== null && in_array($key, ['review_year', 'usage_year'], true)) {
                $candidate = (string)$year;
            } elseif (($profileCandidate = self::applicationScalarCandidate($field, $applicationProfile, $template)) !== null) {
                $candidate = $profileCandidate['value'];
                $candidateEvidence = '资质认定申请书';
                $candidateLowConfidence = (bool)($profileCandidate['low_confidence'] ?? true);
            } elseif ($company !== [] && self::matchesAny($key . ' ' . $label, ['company', 'organization', 'lab', '单位', '机构', '实验室'])) {
                $candidate = (string)($company['name'] ?? '');
            } elseif ($mainSite !== [] && self::matchesAny($key . ' ' . $label, ['address', '地址'])) {
                $candidate = (string)($mainSite['address'] ?? '');
            } elseif ($mainSite !== [] && self::matchesAny($key . ' ' . $label, ['phone', '电话'])) {
                $candidate = (string)($mainSite['phone'] ?? '');
            } elseif (in_array($key, ['doc_number', 'record_number', 'form_number'], true)) {
                $candidate = (string)$template->doc_number;
            }

            if ($candidate === '') {
                continue;
            }
            if (($field['type'] ?? '') === 'date') {
                $candidate = self::normalizeDate($candidate);
            }
            if ($candidate === '') {
                continue;
            }

            $values[$key] = $candidate;
            $evidence[] = $key . '=' . $candidateEvidence;
            if ($candidateLowConfidence || empty($field['required'])) {
                $low[] = $key;
            }
        }

        return ['values' => $values, 'evidence' => $evidence, 'low_confidence' => $low, 'warnings' => $warnings];
    }

    private static function applicationProfile(): array
    {
        static $profile = null;
        if ($profile !== null) {
            return $profile;
        }

        $path = root_path() . 'runtime' . DIRECTORY_SEPARATOR . 'application-profile' . DIRECTORY_SEPARATOR . 'qms_application_profile.json';
        if (!is_file($path)) {
            $profile = [];
            return $profile;
        }

        $decoded = json_decode((string)file_get_contents($path), true);
        $profile = is_array($decoded) ? $decoded : [];

        return $profile;
    }

    private static function applicationScalarCandidate(array $field, array $profile, RecordFormTemplate $template): ?array
    {
        if ($profile === []) {
            return null;
        }

        $key = (string)($field['key'] ?? '');
        $label = (string)($field['label'] ?? '');
        $type = (string)($field['type'] ?? 'text');
        $haystack = $key . ' ' . $label;
        $organization = $profile['organization'] ?? [];
        $contacts = $profile['contacts'] ?? [];
        $roles = $profile['role_candidates'] ?? [];

        $value = '';
        $low = false;
        $templateText = (string)$template->name . ' ' . (string)$template->doc_number;
        if ($type === 'select') {
            $value = self::profileSelectCandidate($field);
            $low = $value !== '';
        } elseif ($type === 'checkbox' && str_starts_with($key, 'confirm_')) {
            $value = '1';
            $low = true;
        } elseif (self::matchesAny($haystack, ['social_credit', 'credit_code', '统一社会信用代码', '社会信用代码'])) {
            $value = (string)($organization['social_credit_code'] ?? '');
        } elseif (self::matchesAny($haystack, ['email', '邮箱', '电子邮件'])) {
            $value = (string)($organization['email'] ?? '');
        } elseif (self::matchesAny($haystack, ['address', 'site', '场所', '地址'])) {
            $value = self::applicationAddressForTemplate($template, $profile);
        } elseif (self::matchesAny($haystack, ['company', 'organization', 'lab', 'unit', '单位', '机构', '实验室'])) {
            $value = (string)($organization['name'] ?? '');
        } elseif (self::matchesAny($haystack, ['certificate_number', '资质认定证书编号'])) {
            $value = (string)($profile['certificate_number'] ?? '');
        } elseif (self::matchesAny($haystack, ['authorized', 'authorization_scope', '授权签字'])) {
            $value = implode('、', (array)($profile['capability_keywords'] ?? []));
            $low = true;
        } elseif (self::matchesAny($haystack, ['equipment_name', 'instrument_equipment', 'confirm_equipment', '仪器设备', '主要设备'])) {
            $value = implode('、', (array)($profile['equipment_keywords'] ?? []));
            $low = true;
        } elseif ($key === 'method_name') {
            $value = 'GB/T 16553-2017 珠宝玉石 鉴定';
            $low = true;
        } elseif (self::matchesAny($haystack, ['standard', 'standards', 'method', 'check_basis', 'check_standard', '标准', '方法', '依据'])) {
            $value = implode('；', (array)($profile['standards'] ?? []));
            $low = true;
        } elseif (self::matchesAny($haystack, ['confirm_personnel', 'personnel', 'participants', 'attendees', '人员', '参加'])) {
            $value = implode('、', self::profilePeopleNames($profile));
            $low = true;
        } elseif (str_contains($templateText, '授权签字人') && in_array($key, ['person_name', 'employee_name'], true)) {
            $value = self::firstProfileRole($roles, 'authorized_signatories');
            $low = true;
        } elseif (str_contains($templateText, '授权签字人') && $key === 'position') {
            $value = '检测师';
            $low = true;
        } elseif (str_contains($templateText, '授权签字人') && $key === 'professional_title') {
            $value = '/';
            $low = true;
        } elseif (self::matchesAny($haystack, ['lab_director', '实验室主任'])) {
            $value = (string)($contacts['lab_director']['name'] ?? '');
            $low = true;
        } elseif (self::matchesAny($haystack, ['technical_manager', 'technical_director', 'tech_', '技术负责人'])) {
            $value = self::firstProfileRole($roles, 'technical_manager');
            $low = true;
        } elseif (self::matchesAny($haystack, ['quality_manager', '质量负责人'])) {
            $value = self::firstProfileRole($roles, 'quality_manager');
            $low = true;
        } elseif (self::matchesAny($haystack, ['equipment_admin', '设备管理员'])) {
            $value = self::firstProfileRole($roles, 'equipment_admin');
            $low = true;
        } elseif (self::matchesAny($haystack, ['responsible_person', '负责人', '批准人', 'approver', 'approved_by', 'host', '主持人'])) {
            $value = (string)($contacts['legal_representative']['name'] ?? '');
            $low = true;
        } elseif (self::matchesAny($haystack, ['contact', '联系人'])) {
            $value = (string)($contacts['primary_contact']['name'] ?? '');
        } elseif (self::matchesAny($haystack, ['phone', 'mobile', '电话', '手机'])) {
            $value = (string)($contacts['primary_contact']['mobile'] ?? $contacts['legal_representative']['mobile'] ?? '');
        }

        if ($value === '') {
            return null;
        }

        if ($type === 'select' && !in_array($value, $field['options'] ?? [], true)) {
            return null;
        }
        if ($type === 'checkbox' && !in_array($value, ['0', '1'], true)) {
            return null;
        }

        return ['value' => $value, 'low_confidence' => $low];
    }

    private static function applicationTableCandidate(array $field, array $profile): array
    {
        $key = (string)($field['key'] ?? '');
        $label = (string)($field['label'] ?? '');
        if (!self::matchesAny($key . ' ' . $label, ['standard', 'standards', '标准'])) {
            return [];
        }

        $rows = [];
        foreach (array_values((array)($profile['standards'] ?? [])) as $index => $standard) {
            [$code, $name] = self::splitStandard((string)$standard);
            $row = [
                'sequence' => $index + 1,
                'standard_code' => $code,
                'standard_name' => $name,
                'standard_status' => '现行有效',
                'replacement_standard' => '',
                'effective_date' => '',
                'action_required' => '按资质认定申请书能力附表作为2025运行记录候选，待人工确认。',
            ];
            $rows[] = self::filterRowByColumns($row, $field['columns'] ?? []);
        }

        return $rows;
    }

    private static function splitStandard(string $standard): array
    {
        if (preg_match('/\A((?:GB|DB)[^ ]+(?: [^ ]+)?)(?:\s+(.+))?\z/u', $standard, $match) === 1) {
            return [trim($match[1]), trim((string)($match[2] ?? $standard))];
        }

        return [$standard, $standard];
    }

    private static function applicationAddressForTemplate(RecordFormTemplate $template, array $profile): string
    {
        $name = (string)$template->name . ' ' . (string)$template->source_file_name;
        $organization = $profile['organization'] ?? [];
        if (str_contains($name, '和田') || str_contains($name, '金丝玉')) {
            return (string)($organization['branch_site_address'] ?? $organization['main_site_address'] ?? '');
        }

        return (string)($organization['main_site_address'] ?? $organization['registered_address'] ?? '');
    }

    private static function firstProfileRole(array $roles, string $role): string
    {
        $items = $roles[$role] ?? [];
        return (string)(is_array($items) ? ($items[0] ?? '') : $items);
    }

    private static function profilePeopleNames(array $profile): array
    {
        $names = [];
        foreach ((array)($profile['people'] ?? []) as $person) {
            if (is_array($person) && !empty($person['name'])) {
                $names[] = (string)$person['name'];
            }
        }

        return array_values(array_unique($names));
    }

    private static function profileSelectCandidate(array $field): string
    {
        $key = (string)($field['key'] ?? '');
        $label = (string)($field['label'] ?? '');
        $options = (array)($field['options'] ?? []);
        if ($options === []) {
            return '';
        }

        if (self::matchesAny($key . ' ' . $label, ['review_result', '评审意见'])) {
            foreach (['授权签字人评审合格', '合格', '满足', '是'] as $candidate) {
                if (in_array($candidate, $options, true)) {
                    return $candidate;
                }
            }
        }
        foreach (['是', '满足', '基本满足', '理解', '已操作', '熟悉', '有试剂', '有标准', '不需要标准品', '无要求', '开展新项目', '采用新标准'] as $candidate) {
            if (in_array($candidate, $options, true)) {
                return $candidate;
            }
        }

        return '';
    }

    private static function extractAiValues(RecordFormTemplate $template, array $schema, string $sourceMarkdown, array $currentValues): array
    {
        $missing = self::missingKeys($schema, $currentValues);
        if ($missing === []) {
            return ['values' => [], 'evidence' => [], 'low_confidence' => [], 'warnings' => []];
        }
        try {
            $result = DeepSeekService::chat([
                ['role' => 'system', 'content' => '你是实验室QMS记录表格填表助手。只返回JSON对象，键为字段key，值为从源文件摘录中能直接确认的填充值。不能确认的字段不要输出。'],
                ['role' => 'user', 'content' => json_encode([
                    'doc_number' => (string)$template->doc_number,
                    'name' => (string)$template->name,
                    'missing_keys' => $missing,
                    'schema' => $schema,
                    'current_values' => $currentValues,
                    'source_markdown_excerpt' => mb_substr($sourceMarkdown, 0, 6000),
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: ''],
            ], [
                'company_id' => (string)Config::get('qms.company_id'),
                'temperature' => 0,
                'max_tokens' => 1200,
                'timeout' => 60,
                'response_format' => ['type' => 'json_object'],
            ]);
            $decoded = self::parseAiJsonObject((string)$result['content']);
            if (isset($decoded['values']) && is_array($decoded['values'])) {
                $decoded = $decoded['values'];
            }
            $filtered = self::filterValuesBySchema($decoded, $schema);

            return [
                'values' => $filtered,
                'evidence' => $filtered !== [] ? ['AI辅助候选：' . implode('、', array_keys($filtered))] : [],
                'low_confidence' => array_keys($filtered),
                'candidate_fields' => array_keys($filtered),
                'candidate_values' => $filtered,
                'warnings' => [],
            ];
        } catch (\Throwable $exception) {
            return [
                'values' => [],
                'evidence' => [],
                'low_confidence' => $missing,
                'candidate_fields' => [],
                'candidate_values' => [],
                'warnings' => ['AI辅助抽取未执行或失败：' . $exception->getMessage()],
            ];
        }
    }

    private static function parseAiJsonObject(string $content): array
    {
        $content = trim($content);
        if ($content === '') {
            throw new RuntimeException('AI 返回为空');
        }

        if (preg_match('/```(?:json)?\s*(.*?)```/is', $content, $match) === 1) {
            $content = trim($match[1]);
        }

        $decoded = json_decode($content, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        $start = strpos($content, '{');
        $end = strrpos($content, '}');
        if ($start !== false && $end !== false && $end > $start) {
            $candidate = substr($content, $start, $end - $start + 1);
            $decoded = json_decode($candidate, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        throw new RuntimeException('AI 返回不是 JSON 对象');
    }

    private static function manualResult(
        RecordFormTemplate $template,
        array $schema,
        array $values,
        array $reasons,
        array $source,
        array $evidence = [],
        array $lowConfidence = [],
        array $warnings = [],
        array $aiCandidateFields = [],
        array $aiCandidateValues = []
    ): array {
        return [
            'template_id' => (string)$template->id,
            'doc_number' => (string)$template->doc_number,
            'name' => (string)$template->name,
            'module' => (string)$template->module,
            'decision' => 'needs_manual_input',
            'values' => $values,
            'source_markdown_path' => (string)($source['path'] ?? ''),
            'evidence' => $evidence,
            'low_confidence_fields' => array_values(array_unique(array_merge($lowConfidence, self::missingKeys($schema, $values)))),
            'ai_candidate_fields' => array_values(array_unique($aiCandidateFields)),
            'ai_candidate_values' => $aiCandidateValues,
            'blank_required_fields' => self::missingKeys($schema, $values),
            'manual_layout_status' => 'pending',
            'automatic_checks' => [
                'print_template_renderable' => true,
                'preview_pdf_downloadable' => false,
                'blank_required_count' => count(self::missingKeys($schema, $values)),
                'field_count' => count($schema),
            ],
            'warnings' => array_merge($warnings, $reasons),
        ];
    }

    private static function skippedExistingResult(RecordFormTemplate $template, int $year): array
    {
        $existing = self::existingYearInstance($template, $year);

        return [
            'template_id' => (string)$template->id,
            'doc_number' => (string)$template->doc_number,
            'name' => (string)$template->name,
            'module' => (string)$template->module,
            'decision' => 'skipped_existing',
            'year' => $year,
            'instance_id' => (string)($existing['id'] ?? ''),
            'instance_url' => !empty($existing['id']) ? '/record_form_instance/view?id=' . rawurlencode((string)$existing['id']) : '',
            'manual_layout_status' => 'existing',
            'automatic_checks' => [
                'print_template_renderable' => true,
                'preview_pdf_downloadable' => false,
                'blank_required_count' => null,
                'field_count' => count(RecordFormSchemaService::decode((string)$template->field_schema)),
            ],
            'warnings' => ['已存在同模板' . $year . '运行记录，按 skip_existing 跳过。'],
        ];
    }

    private static function defaultValues(array $schema): array
    {
        $values = [];
        foreach ($schema as $field) {
            $values[$field['key']] = ($field['type'] ?? '') === 'repeatable_table' ? [] : ($field['default'] ?? '');
        }

        return $values;
    }

    private static function targetYear(array $options): ?int
    {
        if (!array_key_exists('year', $options) || trim((string)$options['year']) === '') {
            return null;
        }
        $year = (int)$options['year'];

        return $year > 0 ? $year : null;
    }

    private static function batchId(array $options, ?int $year): string
    {
        $provided = trim((string)($options['batch_id'] ?? ''));
        if ($provided !== '') {
            return self::safeToken($provided);
        }
        if ($year === null) {
            return '';
        }

        return 'batch-' . date('YmdHis') . '-' . substr(str_replace('-', '', qms_uuid()), 0, 8);
    }

    private static function isCreatableDecision(string $decision): bool
    {
        return in_array($decision, ['ready', 'ready_with_gaps'], true);
    }

    private static function hasExistingYearInstance(RecordFormTemplate $template, int $year): bool
    {
        return self::existingYearInstance($template, $year) !== null;
    }

    private static function existingYearInstance(RecordFormTemplate $template, int $year): ?array
    {
        $titlePrefix = $year . '运行记录-' . (string)$template->doc_number . '-' . (string)$template->name;
        $row = Db::name('record_form_instances')
            ->where('template_id', (string)$template->id)
            ->where('status', '<>', 'voided')
            ->where(function ($query) use ($titlePrefix, $year) {
                $query->where('record_title', 'like', $titlePrefix . '%')
                    ->whereOr('field_values', 'like', '%' . (string)$year . '%');
            })
            ->order('created', 'desc')
            ->find();

        return is_array($row) ? $row : null;
    }

    private static function companyContext(): array
    {
        $companyId = (string)Config::get('qms.company_id');
        $row = Db::name('companies')->where('id', $companyId)->find();

        return is_array($row) ? $row : [];
    }

    private static function mainSiteContext(): array
    {
        $companyId = (string)Config::get('qms.company_id');
        $row = Db::name('sites')
            ->where('company_id', $companyId)
            ->where('soft_delete', 0)
            ->where('publish', 1)
            ->orderRaw("CASE WHEN site_type = 'main' THEN 0 ELSE 1 END")
            ->order('sort_order', 'asc')
            ->find();

        return is_array($row) ? $row : [];
    }

    private static function fieldIsEmpty(mixed $value): bool
    {
        if (is_array($value)) {
            return $value === [];
        }

        return trim((string)$value) === '';
    }

    private static function needsApplicationCandidate(array $field, mixed $value, array $schema): bool
    {
        if (is_array($value) || self::fieldIsEmpty($value)) {
            return false;
        }

        $stringValue = trim((string)$value);
        $type = (string)($field['type'] ?? 'text');
        if ($type === 'select' && !in_array($stringValue, $field['options'] ?? [], true)) {
            return true;
        }
        if ($type === 'checkbox' && !in_array($stringValue, ['0', '1'], true)) {
            return true;
        }

        foreach ($schema as $schemaField) {
            $label = trim((string)($schemaField['label'] ?? ''));
            if ($label !== '' && $stringValue === $label) {
                return true;
            }
        }

        return false;
    }

    private static function matchesAny(string $text, array $needles): bool
    {
        $lower = strtolower($text);
        foreach ($needles as $needle) {
            $needle = (string)$needle;
            if ($needle !== '' && (str_contains($text, $needle) || str_contains($lower, strtolower($needle)))) {
                return true;
            }
        }

        return false;
    }

    private static function writeBatchReport(array $summary): ?array
    {
        $year = $summary['year'] ?? null;
        $batchId = (string)($summary['batch_id'] ?? '');
        if ($year === null || $batchId === '') {
            return null;
        }

        $relativeDir = 'runtime' . DIRECTORY_SEPARATOR . 'record-form-batches' . DIRECTORY_SEPARATOR . (string)$year . DIRECTORY_SEPARATOR . $batchId;
        $dir = root_path() . $relativeDir;
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $report = [
            'batch_id' => $batchId,
            'year' => (int)$year,
            'created_at' => date('Y-m-d H:i:s'),
            'summary' => [
                'apply' => (bool)($summary['apply'] ?? false),
                'preview_pdf' => (bool)($summary['preview_pdf'] ?? false),
                'ai' => (bool)($summary['ai'] ?? false),
                'create_incomplete' => (bool)($summary['create_incomplete'] ?? false),
                'skip_existing' => (bool)($summary['skip_existing'] ?? false),
                'total' => (int)($summary['total'] ?? 0),
                'created' => (int)($summary['created'] ?? 0),
                'dry_run' => (int)($summary['dry_run'] ?? 0),
                'ready_with_gaps' => (int)($summary['ready_with_gaps'] ?? 0),
                'needs_manual_input' => (int)($summary['needs_manual_input'] ?? 0),
                'skipped_existing' => (int)($summary['skipped_existing'] ?? 0),
                'errors' => (int)($summary['errors'] ?? 0),
            ],
            'rows' => $summary['rows'] ?? [],
        ];

        $jsonPath = $dir . DIRECTORY_SEPARATOR . 'report.json';
        $markdownPath = $dir . DIRECTORY_SEPARATOR . 'report.md';
        file_put_contents($jsonPath, json_encode($report, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        file_put_contents($markdownPath, self::batchReportMarkdown($report));

        return [
            'json_path' => str_replace(root_path(), '', $jsonPath),
            'markdown_path' => str_replace(root_path(), '', $markdownPath),
        ];
    }

    private static function batchReportMarkdown(array $report): string
    {
        $summary = $report['summary'] ?? [];
        $lines = [
            '# 2025运行记录批次报告',
            '',
            '- 批次：' . (string)($report['batch_id'] ?? ''),
            '- 年度：' . (string)($report['year'] ?? ''),
            '- 创建时间：' . (string)($report['created_at'] ?? ''),
            '- 模板数：' . (int)($summary['total'] ?? 0),
            '- 新建草稿：' . (int)($summary['created'] ?? 0),
            '- 缺口草稿：' . (int)($summary['ready_with_gaps'] ?? 0),
            '- 跳过已有：' . (int)($summary['skipped_existing'] ?? 0),
            '- 待人工输入：' . (int)($summary['needs_manual_input'] ?? 0),
            '- 错误：' . (int)($summary['errors'] ?? 0),
            '',
            '| 编号 | 表格 | 决策 | 实例 | 临时PDF | AI候选字段 | 低置信字段 | 留空必填 | 版式确认 |',
            '| --- | --- | --- | --- | --- | --- | --- | --- | --- |',
        ];

        foreach (($report['rows'] ?? []) as $row) {
            $instance = (string)($row['instance_url'] ?? '');
            $download = (string)($row['preview_pdf']['download_url'] ?? '');
            $lines[] = implode(' | ', [
                self::mdCell((string)($row['doc_number'] ?? '')),
                self::mdCell((string)($row['name'] ?? '')),
                self::mdCell((string)($row['decision'] ?? '')),
                $instance !== '' ? '[' . self::mdCell('查看') . '](' . $instance . ')' : '-',
                $download !== '' ? '[' . self::mdCell('下载') . '](' . $download . ')' : '-',
                self::mdCell(implode(', ', (array)($row['ai_candidate_fields'] ?? [])) ?: '-'),
                self::mdCell(implode(', ', (array)($row['low_confidence_fields'] ?? [])) ?: '-'),
                self::mdCell(implode(', ', (array)($row['blank_required_fields'] ?? [])) ?: '-'),
                self::mdCell((string)($row['manual_layout_status'] ?? 'pending')),
            ]);
        }

        return implode(PHP_EOL, $lines) . PHP_EOL;
    }

    private static function mdCell(string $value): string
    {
        return str_replace(["\n", "\r", '|'], [' ', ' ', '/'], $value);
    }

    private static function missingKeys(array $schema, array $values): array
    {
        $missing = [];
        foreach ($schema as $field) {
            $key = (string)$field['key'];
            if (($field['type'] ?? '') === 'repeatable_table') {
                if (!empty($field['required']) && (($values[$key] ?? []) === [])) {
                    $missing[] = $key;
                }
                continue;
            }
            if (!empty($field['required']) && trim((string)($values[$key] ?? '')) === '') {
                $missing[] = $key;
            }
        }

        return $missing;
    }

    private static function filterValuesBySchema(array $values, array $schema): array
    {
        $allowed = [];
        foreach ($schema as $field) {
            $key = (string)$field['key'];
            if (array_key_exists($key, $values)) {
                $allowed[$key] = $values[$key];
            }
        }

        return $allowed;
    }

    private static function schemaHasField(array $schema, string $key): bool
    {
        foreach ($schema as $field) {
            if (($field['key'] ?? '') === $key) {
                return true;
            }
        }

        return false;
    }

    private static function firstRepeatableField(array $schema): ?array
    {
        foreach ($schema as $field) {
            if (($field['type'] ?? '') === 'repeatable_table') {
                return $field;
            }
        }

        return null;
    }

    private static function columnOptions(array $field, string $columnKey): array
    {
        foreach (($field['columns'] ?? []) as $column) {
            if (($column['key'] ?? '') === $columnKey) {
                return $column['options'] ?? [];
            }
        }

        return [];
    }

    private static function filterRowByColumns(array $row, array $columns): array
    {
        $filtered = [];
        foreach ($columns as $column) {
            $key = (string)($column['key'] ?? '');
            $filtered[$key] = $row[$key] ?? '';
        }

        return $filtered;
    }

    private static function firstHeadingOrLine(string $text): string
    {
        foreach (preg_split('/\R/u', $text) ?: [] as $line) {
            $line = trim((string)$line, " \t\n\r\0\x0B#");
            if ($line !== '' && !str_contains($line, "\x07")) {
                return $line;
            }
        }

        return '';
    }

    private static function valueAfterLabel(string $text, string $label): string
    {
        $lines = preg_split('/\R/u', $text) ?: [];
        foreach ($lines as $index => $line) {
            if (trim((string)$line, " \t\n\r\0\x0B#") !== $label) {
                continue;
            }
            for ($i = $index + 1, $count = count($lines); $i < $count; $i++) {
                $candidate = trim((string)$lines[$i]);
                if ($candidate !== '') {
                    return $candidate;
                }
            }
        }

        return '';
    }

    private static function textAfterFirst(string $text, string $needle, int $limit): string
    {
        $pos = mb_strpos($text, $needle);
        if ($pos === false) {
            return '';
        }
        $value = trim(mb_substr($text, $pos + mb_strlen($needle)));
        if (mb_strlen($value) > $limit) {
            $value = mb_substr($value, 0, $limit) . '...';
        }

        return $value;
    }

    private static function normalizeDate(string $value): string
    {
        $value = trim($value);
        if (preg_match('/(\d{4})[.\/-](\d{1,2})[.\/-](\d{1,2})/u', $value, $match) !== 1) {
            return '';
        }

        return sprintf('%04d-%02d-%02d', (int)$match[1], (int)$match[2], (int)$match[3]);
    }

    private static function normalizeSelect(string $value, array $options): string
    {
        $value = trim($value);
        if ($value === '' || $options === []) {
            return $value;
        }
        foreach ($options as $option) {
            if (str_contains($value, (string)$option) || str_contains((string)$option, $value)) {
                return (string)$option;
            }
        }

        return '';
    }

    private static function safeToken(string $value): string
    {
        $value = preg_replace('/[^a-zA-Z0-9._-]+/', '-', $value) ?? '';
        $value = trim($value, '-._');

        return $value !== '' ? $value : 'batch';
    }

    private static function recordTitle(RecordFormTemplate $template, ?int $year = null): string
    {
        if ($year !== null) {
            return (string)$year . '运行记录-' . (string)$template->doc_number . '-' . (string)$template->name;
        }

        return '基础运行记录-' . (string)$template->doc_number . '-' . (string)$template->name . '-' . date('Ymd');
    }
}
