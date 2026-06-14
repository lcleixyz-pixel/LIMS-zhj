<?php
declare(strict_types=1);

namespace app\service;

use think\facade\Db;

class PageContextBuilder
{
    private const ALLOWED_CONTROLLERS = [
        'employee', 'training', 'competencyrecord', 'employeecertificate',
        'referencematerial', 'equipment', 'compliance', 'document',
        'dashboard', 'aiassistant', 'aichat', 'aisettings',
        'recordformtemplate', 'recordforminstance', 'planningstructure',
    ];

    public static function fromRequestPayload(string $companyId, array $pageMeta, string $contextMode): array
    {
        $controller = self::resolveController($pageMeta);
        $action = strtolower((string)($pageMeta['action'] ?? 'index'));
        if ($action === '') {
            $action = 'index';
        }

        $context = self::fromPageMeta(
            $companyId,
            $controller,
            $action,
            ($pageMeta['record_id'] ?? '') !== '' ? (string)$pageMeta['record_id'] : null,
            $contextMode,
            (string)($pageMeta['title'] ?? ''),
            (string)($pageMeta['route'] ?? '')
        );
        $clientModule = trim((string)($pageMeta['module'] ?? ''));
        if ($clientModule !== '') {
            $context['page']['module'] = $clientModule;
        }

        return $context;
    }

    public static function resolveController(array $pageMeta): string
    {
        $controller = self::normalizeControllerKey((string)($pageMeta['controller'] ?? ''));
        if ($controller !== '') {
            return $controller;
        }

        $module = self::normalizeControllerKey((string)($pageMeta['module'] ?? ''));
        if ($module !== '') {
            return $module;
        }

        $route = trim((string)($pageMeta['route'] ?? ''));
        if ($route !== '' && str_contains($route, '/')) {
            return self::normalizeControllerKey(explode('/', $route, 2)[0]);
        }

        return 'dashboard';
    }

    public static function normalizeControllerKey(string $value): string
    {
        $value = strtolower(trim($value));
        if ($value === '') {
            return '';
        }

        return str_replace('_', '', $value);
    }

    public static function fromPageMeta(
        string $companyId,
        string $controller,
        string $action,
        ?string $recordId,
        string $contextMode = 'context',
        string $title = '',
        string $route = ''
    ): array {
        $controller = strtolower($controller);
        $action = strtolower($action);
        $route = $route !== '' ? $route : $controller . '/' . $action;
        $module = str_contains($route, '/') ? explode('/', $route, 2)[0] : $controller;

        $context = [
            'page' => [
                'controller' => $controller,
                'action' => $action,
                'route' => $route,
                'record_id' => $recordId,
                'module' => $module,
                'title' => $title !== '' ? $title : self::defaultTitle($controller, $action),
            ],
            'record_summary' => null,
            'form_schema' => null,
            'compliance_hints' => [],
        ];

        if (in_array($action, ['add', 'edit'], true)) {
            $context['form_schema'] = self::formSchemaFor($controller, $action);
        }

        if ($contextMode === 'general') {
            return $context;
        }

        if ($contextMode === 'context' || $contextMode === 'expert') {
            $context['record_summary'] = self::recordSummary($companyId, $controller, $recordId, $action);
            $context['compliance_hints'] = self::complianceHints($companyId, $contextMode === 'expert' ? 10 : 5);
        }

        if ($contextMode === 'expert') {
            $context['expert_placeholder'] = true;
            $context['expert_notice'] = '评审专家完整工具调用将在后续版本启用；当前基于合规驾驶舱摘要提供只读建议。';
        }

        return $context;
    }

    public static function buildFromRequest(string $companyId, string $controller, string $action, ?string $recordId): array
    {
        return self::fromPageMeta($companyId, strtolower($controller), strtolower($action), $recordId, 'context');
    }

    public static function complianceHints(string $companyId, int $limit = 5): array
    {
        $gaps = ComplianceCheckService::getAllGaps($companyId);
        $labels = ComplianceCheckService::dimensionLabels();
        $hints = [];
        foreach (array_slice($gaps, 0, max(1, $limit)) as $gap) {
            $dimension = (string)($gap['dimension'] ?? '');
            $hints[] = [
                'dimension' => $dimension,
                'message' => ($labels[$dimension] ?? $dimension) . '：' . (string)($gap['check_name'] ?? '') . ' - ' . (string)($gap['status'] ?? ''),
            ];
        }

        return $hints;
    }

    public static function formSchemaFor(string $controller, string $action): ?array
    {
        if (!in_array($action, ['add', 'edit'], true)) {
            return null;
        }

        return match (strtolower($controller)) {
            'training' => [
                'module' => 'training',
                'allowed_fields' => ['title', 'training_date', 'trainer', 'training_type', 'duration_hours', 'content', 'training_plan_id', 'department_id', 'status'],
                'fields' => [
                    ['name' => 'title', 'label' => '培训主题', 'type' => 'text'],
                    ['name' => 'training_date', 'label' => '培训日期', 'type' => 'date'],
                    ['name' => 'trainer', 'label' => '培训师', 'type' => 'text'],
                    ['name' => 'training_type', 'label' => '培训类型', 'type' => 'select'],
                    ['name' => 'duration_hours', 'label' => '时长(小时)', 'type' => 'number'],
                    ['name' => 'content', 'label' => '培训内容', 'type' => 'textarea'],
                ],
            ],
            'competencyrecord' => [
                'module' => 'competency_record',
                'allowed_fields' => ['employee_id', 'test_item', 'method_standard', 'assessment_date', 'assessor_id', 'result', 'authorization_scope', 'valid_until'],
                'fields' => [
                    ['name' => 'employee_id', 'label' => '员工', 'type' => 'select'],
                    ['name' => 'test_item', 'label' => '检测项目/方法', 'type' => 'text'],
                    ['name' => 'method_standard', 'label' => '标准方法', 'type' => 'text'],
                    ['name' => 'assessment_date', 'label' => '评估日期', 'type' => 'date'],
                    ['name' => 'result', 'label' => '结论', 'type' => 'select'],
                    ['name' => 'valid_until', 'label' => '有效期至', 'type' => 'date'],
                ],
            ],
            'employeecertificate' => [
                'module' => 'employee_certificate',
                'allowed_fields' => ['employee_id', 'certificate_type', 'certificate_number', 'issuing_authority', 'issue_date', 'valid_until', 'status', 'remarks'],
                'fields' => [
                    ['name' => 'employee_id', 'label' => '员工', 'type' => 'select'],
                    ['name' => 'certificate_type', 'label' => '证书类型', 'type' => 'text'],
                    ['name' => 'certificate_number', 'label' => '证书编号', 'type' => 'text'],
                    ['name' => 'valid_until', 'label' => '有效期至', 'type' => 'date'],
                ],
            ],
            'referencematerial' => [
                'module' => 'reference_material',
                'allowed_fields' => ['code', 'name', 'lot_number', 'manufacturer', 'traceability_certificate_number', 'valid_until', 'storage_location', 'status', 'remarks'],
                'fields' => [
                    ['name' => 'code', 'label' => '编号', 'type' => 'text'],
                    ['name' => 'name', 'label' => '名称', 'type' => 'text'],
                    ['name' => 'valid_until', 'label' => '有效期至', 'type' => 'date'],
                ],
            ],
            default => null,
        };
    }

    private static function recordSummary(string $companyId, string $controller, ?string $recordId, string $action = ''): ?array
    {
        if ($controller === 'recordformtemplate') {
            return self::recordFormTemplateSummary();
        }

        if ($controller === 'planningstructure') {
            return self::planningStructureSummary($recordId, $action);
        }

        if ($recordId === null || $recordId === '') {
            return null;
        }

        return match ($controller) {
            'employee' => self::employeeSummary($companyId, $recordId),
            'training' => self::trainingSummary($recordId),
            'equipment' => self::equipmentSummary($companyId, $recordId),
            'recordforminstance' => self::recordFormInstanceSummary($recordId),
            default => ['record_id' => $recordId],
        };
    }

    private static function recordFormTemplateSummary(): array
    {
        $reviewRows = Db::name('record_form_templates')
            ->where('soft_delete', 0)
            ->field('review_status,COUNT(*) total')
            ->group('review_status')
            ->select()
            ->toArray();
        $statusRows = Db::name('record_form_templates')
            ->where('soft_delete', 0)
            ->field('status,COUNT(*) total')
            ->group('status')
            ->select()
            ->toArray();
        $schemaCoverage = QmsDocumentStructureService::recordRequirementSchemaCoverage();

        return [
            'module' => 'record_form_template',
            'workflow' => '模板库 -> 复核 -> 填写实例 -> PDF归档',
            'review_counts' => self::countMap($reviewRows, 'review_status'),
            'status_counts' => self::countMap($statusRows, 'status'),
            'record_requirement_blocks' => (int)($schemaCoverage['total_requirement_blocks'] ?? 0),
            'schema_covered_blocks' => (int)($schemaCoverage['schema_covered_blocks'] ?? 0),
            'schema_gap_blocks' => (int)($schemaCoverage['gap_blocks'] ?? 0),
            'schema_gap_examples' => array_slice($schemaCoverage['gap_rows'] ?? [], 0, 5),
            'copilot_prompts' => [
                '对照程序记录要求缺哪些字段？',
                '哪些模板还不能填写？',
                '这个记录表格发布前还缺什么复核证据？',
            ],
        ];
    }

    private static function planningStructureSummary(?string $recordId, string $action): array
    {
        $recordCoverage = QmsDocumentStructureService::procedureRecordRequirementCoverage();
        $schemaCoverage = QmsDocumentStructureService::recordRequirementSchemaCoverage();
        $packageSummary = QmsDocumentStructureService::systemPackageSummary();
        $summary = [
            'module' => 'planning_structure',
            'workflow' => '结构化文件 -> 块级追溯 -> 变更影响预检 -> 修订草稿 -> 复核发布',
            'action' => $action,
            'total_procedures' => (int)($recordCoverage['total_procedures'] ?? 0),
            'record_requirement_gap_procedures' => (int)($recordCoverage['gap_procedures'] ?? 0),
            'schema_gap_blocks' => (int)($schemaCoverage['gap_blocks'] ?? 0),
            'latest_change_impact_count' => (int)($packageSummary['latest_change_impact_count'] ?? 0),
            'latest_block_trace_count' => (int)($packageSummary['latest_block_trace_count'] ?? 0),
            'record_requirement_gap_examples' => array_slice($recordCoverage['gap_rows'] ?? [], 0, 5),
            'copilot_prompts' => [
                '这条记录要求是否已有模板？',
                '改这份程序会影响哪些 BG 记录表格？',
                '建议同步复核哪些结构化内容块？',
            ],
        ];

        if ($recordId !== null && $recordId !== '') {
            $preview = QmsDocumentStructureService::changeControlImpactPreview($recordId);
            $summary['selected_document'] = $preview['selected_document'] ?? [];
            $summary['selected_change_impact'] = $preview['summary'] ?? [];
        }

        return $summary;
    }

    private static function countMap(array $rows, string $keyField): array
    {
        $counts = [];
        foreach ($rows as $row) {
            $key = (string)($row[$keyField] ?? '');
            if ($key === '') {
                $key = 'pending';
            }
            $counts[$key] = (int)($row['total'] ?? 0);
        }

        return $counts;
    }

    private static function employeeSummary(string $companyId, string $recordId): ?array
    {
        $employee = Db::name('employees')
            ->where('company_id', $companyId)
            ->where('id', $recordId)
            ->where('soft_delete', 0)
            ->field('id,employee_number,name')
            ->find();
        if (!$employee) {
            return null;
        }

        return [
            'employee_number' => (string)$employee['employee_number'],
            'name' => (string)$employee['name'],
            'appointments' => Db::name('employee_appointments')->where('employee_id', $recordId)->where('soft_delete', 0)->count(),
            'training_records' => Db::name('training_records')->alias('r')->join('trainings t', 't.id = r.training_id')->where('r.employee_id', $recordId)->where('r.soft_delete', 0)->count(),
            'competency_records' => Db::name('competency_records')->where('employee_id', $recordId)->where('soft_delete', 0)->count(),
        ];
    }

    private static function trainingSummary(string $recordId): ?array
    {
        $row = Db::name('trainings')->where('id', $recordId)->where('soft_delete', 0)->field('title,training_date,status')->find();

        return $row ? [
            'title' => (string)$row['title'],
            'training_date' => (string)($row['training_date'] ?? ''),
            'status' => (string)($row['status'] ?? ''),
        ] : null;
    }

    private static function recordFormInstanceSummary(string $recordId): ?array
    {
        $record = Db::name('record_form_instances')
            ->where('id', $recordId)
            ->field('id,template_id,template_name,template_module,template_version,doc_number,record_title,field_values,status,created,modified')
            ->find();
        if (!is_array($record)) {
            return null;
        }

        $values = self::decodeJson((string)($record['field_values'] ?? ''));
        $year = self::recordYearFromInstance($record, $values);
        $summary = [
            'module' => 'record_form_instance',
            'workflow' => '记录实例 -> 草稿填写 -> 人工核对 -> PDF归档',
            'current_instance' => [
                'id' => (string)$record['id'],
                'doc_number' => (string)$record['doc_number'],
                'record_title' => (string)$record['record_title'],
                'status' => (string)$record['status'],
                'template_id' => (string)$record['template_id'],
                'template_name' => (string)($record['template_name'] ?? ''),
                'template_module' => (string)($record['template_module'] ?? ''),
                'template_version' => (string)($record['template_version'] ?? ''),
                'year' => $year,
                'field_values' => self::compactValue($values),
            ],
            'copilot_prompts' => [
                '根据当前记录内容生成填写建议',
                '指出这份记录还缺哪些关键字段',
                '根据当前年度培训计划生成培训记录草稿',
            ],
        ];

        $trainingContext = self::trainingRecordContextForYear($year, (string)$record['doc_number']);
        if ($trainingContext !== null) {
            $summary['related_training_records'] = $trainingContext;
        }

        return $summary;
    }

    private static function trainingRecordContextForYear(?int $year, string $docNumber): ?array
    {
        if ($year === null || !in_array($docNumber, ['XZTC/BG-01-01', 'XZTC/BG-01-02'], true)) {
            return null;
        }

        $annualPlanTemplate = self::recordFormTemplateBrief('XZTC/BG-01-01');
        $trainingRecordTemplate = self::recordFormTemplateBrief('XZTC/BG-01-02');
        $annualPlans = self::recordFormInstancesForYear('XZTC/BG-01-01', $year, 3);
        $trainingRecords = self::recordFormInstancesForYear('XZTC/BG-01-02', $year, 5);

        return [
            'year' => $year,
            'source_plan_doc_number' => 'XZTC/BG-01-01',
            'target_record_doc_number' => 'XZTC/BG-01-02',
            'source_plan_template' => $annualPlanTemplate,
            'target_training_record_template' => $trainingRecordTemplate,
            'annual_plan_instances' => $annualPlans,
            'existing_training_record_instances' => $trainingRecords,
            'draft_guidance' => '可根据 annual_plan_instances[].field_values.training_plan_items 逐项起草 XZTC/BG-01-02 人员培训记录；只给草稿建议，不自动保存。',
        ];
    }

    private static function recordFormTemplateBrief(string $docNumber): ?array
    {
        $template = Db::name('record_form_templates')
            ->where('doc_number', $docNumber)
            ->where('soft_delete', 0)
            ->where('status', 'published')
            ->field('id,doc_number,name,module,version,field_schema,print_template_key')
            ->order('created', 'asc')
            ->find();
        if (!is_array($template)) {
            return null;
        }

        $schema = RecordFormSchemaService::decode((string)($template['field_schema'] ?? ''));

        return [
            'id' => (string)$template['id'],
            'doc_number' => (string)$template['doc_number'],
            'name' => (string)$template['name'],
            'module' => (string)($template['module'] ?? ''),
            'version' => (string)($template['version'] ?? ''),
            'print_template_key' => (string)($template['print_template_key'] ?? ''),
            'fields' => array_map(static function (array $field): array {
                return [
                    'key' => (string)($field['key'] ?? ''),
                    'label' => (string)($field['label'] ?? ''),
                    'type' => (string)($field['type'] ?? 'text'),
                    'required' => (bool)($field['required'] ?? false),
                ];
            }, array_slice($schema, 0, 12)),
        ];
    }

    private static function recordFormInstancesForYear(string $docNumber, int $year, int $limit): array
    {
        $rows = Db::name('record_form_instances')
            ->where('doc_number', $docNumber)
            ->where('status', '<>', 'voided')
            ->where('record_title', 'like', $year . '运行记录-%')
            ->field('id,doc_number,record_title,status,field_values,created,modified')
            ->order('modified', 'desc')
            ->limit(max(1, $limit))
            ->select()
            ->toArray();

        return array_map(static function (array $row): array {
            return [
                'id' => (string)$row['id'],
                'doc_number' => (string)$row['doc_number'],
                'record_title' => (string)$row['record_title'],
                'status' => (string)$row['status'],
                'field_values' => self::compactValue(self::decodeJson((string)($row['field_values'] ?? ''))),
            ];
        }, $rows);
    }

    private static function recordYearFromInstance(array $record, array $values): ?int
    {
        foreach (['plan_year', 'year', 'review_year'] as $key) {
            $candidate = (int)($values[$key] ?? 0);
            if ($candidate >= 2000 && $candidate <= 2100) {
                return $candidate;
            }
        }

        $title = (string)($record['record_title'] ?? '');
        if (preg_match('/(20\d{2})运行记录/u', $title, $matches) === 1) {
            return (int)$matches[1];
        }

        return null;
    }

    private static function decodeJson(string $json): array
    {
        $json = trim($json);
        if ($json === '') {
            return [];
        }

        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : [];
    }

    private static function compactValue(mixed $value, int $depth = 0): mixed
    {
        if (!is_array($value)) {
            $text = (string)$value;
            return mb_strlen($text) > 500 ? mb_substr($text, 0, 500) . '…' : $value;
        }

        $limit = $depth === 0 ? 12 : 8;
        $out = [];
        $count = 0;
        foreach ($value as $key => $item) {
            if ($count >= $limit) {
                $out['_truncated'] = true;
                break;
            }
            $out[$key] = self::compactValue($item, $depth + 1);
            $count++;
        }

        return $out;
    }

    private static function equipmentSummary(string $companyId, string $recordId): ?array
    {
        $row = Db::name('equipments')
            ->where('company_id', $companyId)
            ->where('id', $recordId)
            ->where('soft_delete', 0)
            ->field('equipment_number,name,status,next_calibration_date')
            ->find();

        return $row ? [
            'equipment_number' => (string)$row['equipment_number'],
            'name' => (string)$row['name'],
            'status' => (string)$row['status'],
            'next_calibration_date' => (string)($row['next_calibration_date'] ?? ''),
        ] : null;
    }

    private static function defaultTitle(string $controller, string $action): string
    {
        return $controller . '/' . $action;
    }
}
