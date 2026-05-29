<?php
declare(strict_types=1);

namespace app\service;

use app\model\RecordFormTemplate;
use RuntimeException;

class RecordFormBatchTemplateService
{
    private const PREVIEW_PATH = 'docs/import-preview/record-forms-import-preview.md';
    private const SOURCE_SUBDIR = 'record-form-sources';
    private const FORMAL_PRINT_KEYS = [
        'XZTC/BG-01-01|年度人员培训计划表' => 'rf_xztc_bg_01_01_5325a1b0bd',
        'XZTC/BG-01-02|人员培训记录表' => 'training_record',
        'XZTC/BG-01-03|检测人员持证登记表' => 'rf_xztc_bg_01_03_5fa5a364df',
        'XZTC/BG-01-04|人员考核记录表' => 'rf_xztc_bg_01_04_5fb52565ba',
        'XZTC/BG-01-05|岗前培训考核记录表' => 'rf_xztc_bg_01_05_66b005b382',
        'XZTC/BG-01-06|培训申请表' => 'rf_xztc_bg_01_06_f268e9aaf1',
        'XZTC/BG-01-07|人员档案登记表' => 'rf_xztc_bg_01_07_a0956d356f',
        'XZTC/BG-01-08|人员能力确认表' => 'rf_xztc_bg_01_08_6fcb518418',
        'XZTC/BG-01-09|人员培训评价表' => 'rf_xztc_bg_01_09_5f54bbf750',
    ];
    private const CORE_MODULES = [
        '人员培训程序',
        '仪器设备管理程序',
        '仪器设备和标准物质期间核查程序',
        '文件控制程序',
        '内部管理体系审核程序',
        '管理评审程序',
        '检测结果质量控制及能力验证程序',
    ];

    public static function manifest(): array
    {
        $path = self::repoRoot() . self::PREVIEW_PATH;
        if (!is_file($path)) {
            throw new RuntimeException('记录表格导入预览清单不存在：' . self::PREVIEW_PATH);
        }

        $text = (string)file_get_contents($path);
        $rows = array_merge(
            self::parseSection($text, '导入清单'),
            self::parseSection($text, '人工确认清单')
        );
        $rows = array_values(array_filter($rows, static fn (array $row): bool => in_array($row['import_action'], ['导入', '人工确认'], true)));

        $duplicateCounts = [];
        foreach ($rows as $row) {
            $key = $row['doc_number'] . '|' . $row['current_name'];
            $duplicateCounts[$key] = ($duplicateCounts[$key] ?? 0) + 1;
        }

        $manifest = [];
        foreach ($rows as $index => $row) {
            $sourceRelativePath = $row['source_file_path'];
            $sourceAbsolutePath = self::repoRoot() . $sourceRelativePath;
            $sourceFileName = basename($sourceRelativePath);
            $name = self::templateName($row['current_name'], $sourceFileName, ($duplicateCounts[$row['doc_number'] . '|' . $row['current_name']] ?? 0) > 1);
            $sourceFileSha1 = is_file($sourceAbsolutePath) ? (string)sha1_file($sourceAbsolutePath) : '';
            $printKey = self::printTemplateKey($row['doc_number'], $row['current_name'], $sourceRelativePath, $sourceFileSha1);
            $isFormal = self::isFormalTemplate($row['doc_number'], $row['current_name'], $printKey);
            $schema = $isFormal
                ? self::formalSchemaFor($row['doc_number'], $row['current_name'], $sourceFileName, $printKey)
                : self::schemaFor($row['doc_number'], $name, $row['module'], $row['match_conclusion'], $row['suggestion']);

            $manifest[] = [
                'doc_number' => $row['doc_number'],
                'name' => $name,
                'base_name' => $row['current_name'],
                'module' => $row['module'],
                'version' => 'A/0',
                'status' => $isFormal ? 'published' : 'draft',
                'review_status' => $isFormal ? 'completed' : 'needs_fidelity',
                'review_note' => $isFormal
                    ? '已按高保真打印模板完成，可正式填写。'
                    : '已按现用2017源文件建立独立打印模板入口，待逐表高保真重构后再开放填写。',
                'print_template_key' => $printKey,
                'field_schema' => $schema,
                'source_file_path' => $sourceRelativePath,
                'source_absolute_path' => $sourceAbsolutePath,
                'source_file_name' => $sourceFileName,
                'source_file_sha1' => $sourceFileSha1,
                'reference' => $row['reference'],
                'match_conclusion' => $row['match_conclusion'],
                'import_action' => $row['import_action'],
                'suggestion' => $row['suggestion'],
                'reason' => $row['reason'],
                'sort_weight' => self::sortWeight($row['module'], $row['doc_number'], $index),
            ];
        }

        usort($manifest, static function (array $left, array $right): int {
            return [$left['sort_weight'], $left['doc_number'], $left['name'], $left['source_file_name']]
                <=> [$right['sort_weight'], $right['doc_number'], $right['name'], $right['source_file_name']];
        });

        return $manifest;
    }

    public static function seed(): array
    {
        $summary = [
            'total' => 0,
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'retired_generic' => 0,
            'errors' => [],
        ];

        $summary['retired_generic'] = self::retireGenericTemplates();

        foreach (self::manifest() as $entry) {
            $summary['total']++;
            if (!is_file($entry['source_absolute_path'])) {
                $summary['skipped']++;
                $summary['errors'][] = $entry['doc_number'] . ' ' . $entry['source_file_name'] . ' 原始附件不存在';
                continue;
            }

            try {
                $encodedSchema = RecordFormSchemaService::encode($entry['field_schema']);
                $record = self::findExisting($entry);
                $isNew = !$record;
                if (!$record) {
                    $record = new RecordFormTemplate();
                    $record->id = qms_uuid();
                }

                $source = self::copySourceFile($entry, (string)$record->id);
                $record->save([
                    'doc_number' => $entry['doc_number'],
                    'name' => $entry['name'],
                    'module' => $entry['module'],
                    'print_template_key' => $entry['print_template_key'],
                    'field_schema' => $encodedSchema,
                    'version' => $entry['version'],
                    'status' => $entry['status'],
                    'review_status' => $entry['review_status'],
                    'review_note' => $entry['review_note'],
                    'source_file_path' => $source['file_path'],
                    'source_file_name' => $source['file_name'],
                    'source_file_sha1' => $entry['source_file_sha1'],
                    'publish' => 1,
                    'soft_delete' => 0,
                ]);

                $summary[$isNew ? 'created' : 'updated']++;
            } catch (\Throwable $exception) {
                $summary['skipped']++;
                $summary['errors'][] = $entry['doc_number'] . ' ' . $entry['source_file_name'] . '：' . $exception->getMessage();
            }
        }

        return $summary;
    }

    private static function parseSection(string $markdown, string $sectionTitle): array
    {
        $start = strpos($markdown, '## ' . $sectionTitle);
        if ($start === false) {
            return [];
        }

        $next = strpos($markdown, "\n## ", $start + 1);
        $section = substr($markdown, $start, $next === false ? null : $next - $start);
        $rows = [];

        foreach (explode("\n", $section) as $line) {
            $line = trim($line);
            if ($line === '' || !str_starts_with($line, '|') || str_starts_with($line, '| ---') || str_starts_with($line, '| 现用编号 ')) {
                continue;
            }

            $cells = array_map('trim', explode('|', trim($line, '|')));
            if (count($cells) < 9) {
                continue;
            }

            $rows[] = [
                'doc_number' => $cells[0],
                'current_name' => $cells[1],
                'source_file_path' => $cells[2],
                'module' => $cells[3],
                'reference' => $cells[4],
                'match_conclusion' => $cells[5],
                'import_action' => $cells[6],
                'suggestion' => $cells[7],
                'reason' => $cells[8],
            ];
        }

        return $rows;
    }

    private static function templateName(string $name, string $sourceFileName, bool $hasDuplicateName): string
    {
        if (!$hasDuplicateName) {
            return $name;
        }

        $variant = self::variantLabel($name, $sourceFileName);
        if ($variant === '') {
            return $name;
        }

        return $name . '（' . $variant . '）';
    }

    private static function variantLabel(string $name, string $sourceFileName): string
    {
        $base = pathinfo($sourceFileName, PATHINFO_FILENAME);
        $base = preg_replace('/^\d{2}-\d{2}/u', '', $base) ?? $base;
        $base = trim($base, " \t\n\r\0\x0B-_.《》()");
        $base = str_replace($name, '', $base);
        $base = trim($base, " \t\n\r\0\x0B-_.《》()");

        if ($base === '') {
            return '';
        }

        return mb_substr($base, 0, 48, 'UTF-8');
    }

    private static function printTemplateKey(string $docNumber, string $baseName, string $sourceRelativePath, string $sourceFileSha1): string
    {
        $formalKey = self::FORMAL_PRINT_KEYS[$docNumber . '|' . $baseName] ?? '';
        if ($formalKey !== '') {
            return $formalKey;
        }

        $stem = strtolower((string)preg_replace('/[^a-zA-Z0-9]+/', '_', $docNumber));
        $stem = trim($stem, '_');
        $hashSource = $docNumber . '|' . $baseName . '|' . $sourceRelativePath . '|' . $sourceFileSha1;

        return 'rf_' . ($stem === '' ? 'record_form' : $stem) . '_' . substr(sha1($hashSource), 0, 10);
    }

    private static function isFormalTemplate(string $docNumber, string $baseName, string $printKey): bool
    {
        if ((self::FORMAL_PRINT_KEYS[$docNumber . '|' . $baseName] ?? '') === $printKey) {
            return true;
        }

        return (
            self::isEquipmentFormalDocNumber($docNumber)
            && preg_match('/\Arf_xztc_bg_(03|04)_/', $printKey) === 1
        ) || (
            self::isDocumentControlFormalDocNumber($docNumber)
            && str_starts_with($printKey, 'rf_xztc_bg_08_')
        );
    }

    private static function formalSchemaFor(string $docNumber, string $baseName, string $sourceFileName, string $printKey): array
    {
        foreach (RecordFormFixtureService::templates() as $template) {
            if (($template['doc_number'] ?? '') === $docNumber && ($template['name'] ?? '') === $baseName) {
                return $template['field_schema'];
            }
        }

        if (self::isEquipmentFormalDocNumber($docNumber)) {
            return self::equipmentFormalSchemaFor($docNumber, $sourceFileName, $printKey);
        }
        if (self::isDocumentControlFormalDocNumber($docNumber)) {
            return self::documentControlFormalSchemaFor($docNumber);
        }

        return self::generalSchema();
    }

    private static function isEquipmentFormalDocNumber(string $docNumber): bool
    {
        if (preg_match('/\AXZTC\/BG-03-0[1-9]\z/', $docNumber) === 1) {
            return true;
        }

        return preg_match('/\AXZTC\/BG-04-0[1-6]\z/', $docNumber) === 1;
    }

    private static function isDocumentControlFormalDocNumber(string $docNumber): bool
    {
        return preg_match('/\AXZTC\/BG-08-0[1-9]\z/', $docNumber) === 1;
    }

    private static function documentControlFormalSchemaFor(string $docNumber): array
    {
        return match ($docNumber) {
            'XZTC/BG-08-01' => self::controlledFileRegisterSchema(),
            'XZTC/BG-08-02' => self::externalFileRegisterSchema(),
            'XZTC/BG-08-03' => self::fileDistributionRecoverySchema(),
            'XZTC/BG-08-04' => self::fileBorrowRegisterSchema(),
            'XZTC/BG-08-05' => self::fileReplacementRequestSchema(),
            'XZTC/BG-08-06' => self::fileChangeApprovalSchema(),
            'XZTC/BG-08-07' => self::fileDestructionRecordSchema(),
            'XZTC/BG-08-08' => self::meetingSignInRecordSchema(),
            'XZTC/BG-08-09' => self::sampleOriginalRecordSchema(),
            default => self::documentControlSchema(),
        };
    }

    private static function equipmentFormalSchemaFor(string $docNumber, string $sourceFileName, string $printKey): array
    {
        return match ($docNumber) {
            'XZTC/BG-03-01' => self::equipmentRegisterSchema(),
            'XZTC/BG-03-02' => self::equipmentUsageSchema(),
            'XZTC/BG-03-03' => self::equipmentMaintenanceSchema(),
            'XZTC/BG-03-04' => self::equipmentRepairSchema(),
            'XZTC/BG-03-05' => self::equipmentAcceptanceSchema(),
            'XZTC/BG-03-06' => self::equipmentDowngradeSchema(),
            'XZTC/BG-03-07' => self::equipmentScrapSealSchema(),
            'XZTC/BG-03-08' => self::equipmentHistorySchema(),
            'XZTC/BG-03-09' => self::fieldEquipmentPerformanceSchema(),
            'XZTC/BG-04-01', 'XZTC/BG-04-04' => self::periodCheckPlanSchema(),
            'XZTC/BG-04-02' => self::periodCheckSchemeSchema(),
            'XZTC/BG-04-03' => self::periodCheckRecordSchema($sourceFileName),
            'XZTC/BG-04-05' => self::functionCheckRecordSchema($sourceFileName),
            'XZTC/BG-04-06' => self::periodCheckReportSchema($sourceFileName),
            default => self::equipmentSchema(),
        };
    }

    private static function equipmentDefaultsFromFileName(string $sourceFileName): array
    {
        $map = [
            '电子天平-TP02' => ['equipment_name' => '电子天平', 'model_spec' => 'TD30002', 'equipment_code' => 'XZTC-TP02', 'check_basis' => '作业指导书'],
            '电子天平-TP03' => ['equipment_name' => '电子天平', 'model_spec' => 'BSM-320.3', 'equipment_code' => 'XZTC-TP03', 'check_basis' => '作业指导书'],
            '电子天平-TP04' => ['equipment_name' => '电子天平', 'model_spec' => 'BSM-320.3', 'equipment_code' => 'XZTC-TP04', 'check_basis' => '作业指导书'],
            '折射仪-ZSY01' => ['equipment_name' => '折射仪', 'model_spec' => 'FGR-002J', 'equipment_code' => 'XZTC-ZSY01', 'check_basis' => '折射仪作业指导书'],
            '折射仪-ZSY02' => ['equipment_name' => '折射仪', 'model_spec' => 'GR-6', 'equipment_code' => 'XZTC-ZSY02', 'check_basis' => '折射仪作业指导书'],
            '测金仪' => ['equipment_name' => 'X射线荧光光谱仪', 'model_spec' => 'XF-A5', 'equipment_code' => 'XZTC-CJY01', 'check_basis' => '作业指导书'],
            '红外光谱' => ['equipment_name' => '傅立叶红外光谱仪', 'model_spec' => 'NICOLET IS5', 'equipment_code' => 'XZTC-HW01', 'check_basis' => '红外光谱仪作业指导书'],
            '紫外' => ['equipment_name' => '紫外-可见光纤光谱仪', 'model_spec' => 'UV-5000', 'equipment_code' => 'XZTC-ZW01', 'check_basis' => '紫外可见光谱仪作业指导书'],
            '偏光镜' => ['equipment_name' => '偏光镜', 'model_spec' => 'FTP-LED', 'equipment_code' => 'XZTC-PGJ01', 'check_basis' => '偏光镜作业指导书'],
            '二色镜' => ['equipment_name' => '二色镜', 'model_spec' => 'FTD-1', 'equipment_code' => 'XZTC-ESJ01', 'check_basis' => '二色镜作业指导书'],
            '分光镜' => ['equipment_name' => '分光镜', 'model_spec' => 'FPS-3A', 'equipment_code' => 'XZTC-FGJ01', 'check_basis' => '分光镜作业指导书'],
            '放大镜' => ['equipment_name' => '放大镜', 'model_spec' => 'FLP-1018', 'equipment_code' => 'XZTC-FDJ01', 'check_basis' => '放大镜作业指导书'],
            '钻石分级灯' => ['equipment_name' => '钻石分级灯', 'model_spec' => 'FDL-25', 'equipment_code' => 'XZTC-ZSFJD01', 'check_basis' => '钻石分级灯作业指导书'],
            '光纤灯' => ['equipment_name' => '光纤灯', 'model_spec' => 'FDL-150A', 'equipment_code' => 'XZTC-GQD01', 'check_basis' => '光纤灯作业指导书'],
            '显微镜' => ['equipment_name' => '显微镜', 'model_spec' => 'FGM-R65141T', 'equipment_code' => 'XZTC-XWJ01', 'check_basis' => '显微镜作业指导书'],
            '金标片G05' => ['equipment_name' => '金标片', 'equipment_code' => 'ZHJ-G05'],
            '金标片G06' => ['equipment_name' => '金标片', 'equipment_code' => 'ZHJ-G06'],
            '金标片G08' => ['equipment_name' => '金标片', 'equipment_code' => 'ZHJ-G08'],
            '金标片G09' => ['equipment_name' => '金标片', 'equipment_code' => 'ZHJ-G09'],
            '金标片G10' => ['equipment_name' => '金标片', 'equipment_code' => 'ZHJ-G10'],
            '银标片G04' => ['equipment_name' => '银标片', 'equipment_code' => 'ZHJ-G04'],
            '银标片G18' => ['equipment_name' => '银标片', 'equipment_code' => 'ZHJ-G18'],
            '合成红宝石标样' => ['equipment_name' => '合成红宝石标样', 'check_basis' => '标准物质期间核查作业指导书'],
            '尖晶石标样' => ['equipment_name' => '尖晶石标样', 'check_basis' => '标准物质期间核查作业指导书'],
            '锆石标样' => ['equipment_name' => '锆石标样', 'check_basis' => '标准物质期间核查作业指导书'],
        ];

        foreach ($map as $needle => $defaults) {
            if (str_contains($sourceFileName, $needle)) {
                return $defaults;
            }
        }

        return [];
    }

    private static function withDefault(array $field, array $defaults, string $key): array
    {
        if (($defaults[$key] ?? '') !== '') {
            $field['default'] = $defaults[$key];
        }

        return $field;
    }

    private static function equipmentRegisterSchema(): array
    {
        return [
            ['key' => 'equipment_items', 'label' => '仪器设备台账明细', 'type' => 'repeatable_table', 'columns' => [
                ['key' => 'equipment_code', 'label' => '设备编号', 'type' => 'text', 'required' => true],
                ['key' => 'equipment_name', 'label' => '名称', 'type' => 'text', 'required' => true],
                ['key' => 'model_spec', 'label' => '规格型号', 'type' => 'text', 'required' => false],
                ['key' => 'manufacturer', 'label' => '生产厂', 'type' => 'text', 'required' => false],
                ['key' => 'factory_number', 'label' => '出厂编号', 'type' => 'text', 'required' => false],
                ['key' => 'purchase_date', 'label' => '购进日期', 'type' => 'date', 'required' => false],
                ['key' => 'accuracy', 'label' => '扩展不确定度/最大允差/准确度等级', 'type' => 'text', 'required' => false],
                ['key' => 'measurement_range', 'label' => '测量范围', 'type' => 'text', 'required' => false],
                ['key' => 'traceability_method', 'label' => '溯源方式', 'type' => 'select', 'options' => ['送校', '自校', '送检', '自检', '比对', '其他'], 'required' => false],
                ['key' => 'remarks', 'label' => '备注', 'type' => 'text', 'required' => false],
            ]],
        ];
    }

    private static function equipmentUsageSchema(): array
    {
        return [
            ['key' => 'equipment_name', 'label' => '仪器名称', 'type' => 'text', 'required' => true],
            ['key' => 'equipment_code', 'label' => '设备编号', 'type' => 'text', 'required' => true],
            ['key' => 'usage_year', 'label' => '年度', 'type' => 'text', 'required' => false],
            ['key' => 'usage_items', 'label' => '使用记录明细', 'type' => 'repeatable_table', 'columns' => [
                ['key' => 'month', 'label' => '月', 'type' => 'text', 'required' => false],
                ['key' => 'day', 'label' => '日', 'type' => 'text', 'required' => false],
                ['key' => 'start_time', 'label' => '开始时间', 'type' => 'text', 'required' => false],
                ['key' => 'end_time', 'label' => '停止时间', 'type' => 'text', 'required' => false],
                ['key' => 'before_status', 'label' => '使用前性能', 'type' => 'select', 'options' => ['正常', '异常'], 'required' => false],
                ['key' => 'after_status', 'label' => '使用后性能', 'type' => 'select', 'options' => ['正常', '异常'], 'required' => false],
                ['key' => 'user', 'label' => '使用人', 'type' => 'person', 'required' => false],
                ['key' => 'remarks', 'label' => '备注', 'type' => 'text', 'required' => false],
            ]],
        ];
    }

    private static function equipmentMaintenanceSchema(): array
    {
        return [
            ['key' => 'equipment_name', 'label' => '仪器', 'type' => 'text', 'required' => true],
            ['key' => 'equipment_code', 'label' => '编号', 'type' => 'text', 'required' => true],
            ['key' => 'maintenance_items', 'label' => '保养维护明细', 'type' => 'repeatable_table', 'columns' => [
                ['key' => 'maintenance_time', 'label' => '时间', 'type' => 'date', 'required' => false],
                ['key' => 'maintainer', 'label' => '保养维护人', 'type' => 'person', 'required' => false],
                ['key' => 'maintenance_content', 'label' => '保养维护情况', 'type' => 'textarea', 'required' => false],
            ]],
        ];
    }

    private static function equipmentRepairSchema(): array
    {
        return [
            ['key' => 'equipment_name', 'label' => '仪器设备名称', 'type' => 'text', 'required' => true],
            ['key' => 'equipment_code', 'label' => '设备编号', 'type' => 'text', 'required' => true],
            ['key' => 'model_spec', 'label' => '规格型号', 'type' => 'text', 'required' => false],
            ['key' => 'purchase_date', 'label' => '购置日期', 'type' => 'date', 'required' => false],
            ['key' => 'failure_description', 'label' => '故障描述', 'type' => 'textarea', 'required' => false],
            ['key' => 'operator', 'label' => '操作人', 'type' => 'person', 'required' => false],
            ['key' => 'operation_date', 'label' => '操作日期', 'type' => 'date', 'required' => false],
            ['key' => 'repair_method_cost', 'label' => '维修方式及费用', 'type' => 'textarea', 'required' => false],
            ['key' => 'inspector', 'label' => '检测员', 'type' => 'person', 'required' => false],
            ['key' => 'inspection_date', 'label' => '检测日期', 'type' => 'date', 'required' => false],
            ['key' => 'technical_manager_opinion', 'label' => '技术负责人审核意见', 'type' => 'textarea', 'required' => false],
            ['key' => 'technical_manager_date', 'label' => '技术负责人日期', 'type' => 'date', 'required' => false],
            ['key' => 'lab_director_approval', 'label' => '实验室主任审批', 'type' => 'textarea', 'required' => false],
            ['key' => 'lab_director_date', 'label' => '实验室主任日期', 'type' => 'date', 'required' => false],
        ];
    }

    private static function equipmentAcceptanceSchema(): array
    {
        return [
            ['key' => 'equipment_name_code', 'label' => '名称及编号', 'type' => 'text', 'required' => true],
            ['key' => 'repair_rental_date', 'label' => '维修、租借日期', 'type' => 'date', 'required' => false],
            ['key' => 'model_spec', 'label' => '型号', 'type' => 'text', 'required' => false],
            ['key' => 'receipt_date', 'label' => '接收日期', 'type' => 'date', 'required' => false],
            ['key' => 'manufacturer', 'label' => '制造厂', 'type' => 'text', 'required' => false],
            ['key' => 'service_provider', 'label' => '维修、租借服务商/单位', 'type' => 'text', 'required' => false],
            ['key' => 'acceptance_items', 'label' => '项目情况记录', 'type' => 'repeatable_table', 'columns' => [
                ['key' => 'item', 'label' => '项目', 'type' => 'select', 'options' => ['配件清点', '机械运转', '电器部分', '其它'], 'required' => false],
                ['key' => 'record', 'label' => '情况记录', 'type' => 'textarea', 'required' => false],
            ]],
            ['key' => 'recalibration_needed', 'label' => '是否需要重新检定（校准）', 'type' => 'select', 'options' => ['是', '否'], 'required' => false],
            ['key' => 'acceptance_result', 'label' => '验收意见', 'type' => 'select', 'options' => ['合格', '不合格'], 'required' => false],
            ['key' => 'participants', 'label' => '参加验收人员签名', 'type' => 'textarea', 'required' => false],
            ['key' => 'department', 'label' => '所属部门', 'type' => 'department', 'required' => false],
            ['key' => 'remarks', 'label' => '备注', 'type' => 'textarea', 'required' => false],
        ];
    }

    private static function equipmentDowngradeSchema(): array
    {
        return [
            ['key' => 'equipment_name', 'label' => '仪器设备名称', 'type' => 'text', 'required' => true],
            ['key' => 'equipment_code', 'label' => '仪器设备编号', 'type' => 'text', 'required' => true],
            ['key' => 'model_spec', 'label' => '规格型号', 'type' => 'text', 'required' => false],
            ['key' => 'repair_date', 'label' => '维修日期', 'type' => 'date', 'required' => false],
            ['key' => 'application_department', 'label' => '申请部门', 'type' => 'department', 'required' => false],
            ['key' => 'applicant', 'label' => '申请人', 'type' => 'person', 'required' => false],
            ['key' => 'downgrade_reason_accuracy', 'label' => '降级使用原因及现实精度', 'type' => 'textarea', 'required' => false],
            ['key' => 'requirement_checks', 'label' => '规范符合性确认', 'type' => 'repeatable_table', 'columns' => [
                ['key' => 'item', 'label' => '项目', 'type' => 'select', 'options' => ['准确度', '精度', '灵敏度', '稳定性', '其他'], 'required' => false],
                ['key' => 'conclusion', 'label' => '是否符合规范要求', 'type' => 'select', 'options' => ['是', '否', '不适用'], 'required' => false],
                ['key' => 'remarks', 'label' => '说明', 'type' => 'text', 'required' => false],
            ]],
            ['key' => 'downgrade_requirements', 'label' => '降级使用项目精度等要求', 'type' => 'textarea', 'required' => false],
            ['key' => 'inspector_opinion', 'label' => '检测员意见', 'type' => 'textarea', 'required' => false],
            ['key' => 'inspector', 'label' => '检测员签名', 'type' => 'person', 'required' => false],
            ['key' => 'inspector_date', 'label' => '检测员日期', 'type' => 'date', 'required' => false],
            ['key' => 'technical_confirmation', 'label' => '技术负责人确认意见', 'type' => 'textarea', 'required' => false],
            ['key' => 'technical_manager', 'label' => '技术负责人签名', 'type' => 'person', 'required' => false],
            ['key' => 'technical_manager_date', 'label' => '技术负责人日期', 'type' => 'date', 'required' => false],
            ['key' => 'lab_director_approval', 'label' => '实验室主任审批意见', 'type' => 'textarea', 'required' => false],
            ['key' => 'lab_director', 'label' => '实验室主任签名', 'type' => 'person', 'required' => false],
            ['key' => 'lab_director_date', 'label' => '实验室主任日期', 'type' => 'date', 'required' => false],
        ];
    }

    private static function equipmentScrapSealSchema(): array
    {
        return [
            ['key' => 'equipment_name', 'label' => '设备名称', 'type' => 'text', 'required' => true],
            ['key' => 'equipment_code', 'label' => '设备编号', 'type' => 'text', 'required' => true],
            ['key' => 'model_spec', 'label' => '规格型号', 'type' => 'text', 'required' => false],
            ['key' => 'purchase_date', 'label' => '购置日期', 'type' => 'date', 'required' => false],
            ['key' => 'handling_status', 'label' => '处理情况', 'type' => 'text', 'required' => false],
            ['key' => 'amount', 'label' => '金额（元）', 'type' => 'number', 'required' => false],
            ['key' => 'action_type', 'label' => '报废/封存', 'type' => 'select', 'options' => ['报废', '封存'], 'required' => false],
            ['key' => 'reason_and_status', 'label' => '报废/封存原因及技术状况', 'type' => 'textarea', 'required' => false],
            ['key' => 'equipment_admin', 'label' => '设备管理员', 'type' => 'person', 'required' => false],
            ['key' => 'equipment_admin_date', 'label' => '设备管理员日期', 'type' => 'date', 'required' => false],
            ['key' => 'equipment_staff', 'label' => '设备员', 'type' => 'person', 'required' => false],
            ['key' => 'equipment_staff_date', 'label' => '设备员日期', 'type' => 'date', 'required' => false],
            ['key' => 'technical_manager_opinion', 'label' => '技术负责人审核意见', 'type' => 'textarea', 'required' => false],
            ['key' => 'technical_manager_date', 'label' => '技术负责人日期', 'type' => 'date', 'required' => false],
            ['key' => 'lab_director_approval', 'label' => '实验室主任审批意见', 'type' => 'textarea', 'required' => false],
            ['key' => 'lab_director_date', 'label' => '实验室主任日期', 'type' => 'date', 'required' => false],
            ['key' => 'remarks', 'label' => '备注', 'type' => 'textarea', 'required' => false],
        ];
    }

    private static function equipmentHistorySchema(): array
    {
        return [
            ['key' => 'equipment_name', 'label' => '设备名称', 'type' => 'text', 'required' => true],
            ['key' => 'equipment_code', 'label' => '设备编号', 'type' => 'text', 'required' => true],
            ['key' => 'supplier_name', 'label' => '供应商名称', 'type' => 'text', 'required' => false],
            ['key' => 'contract_number', 'label' => '合同编号', 'type' => 'text', 'required' => false],
            ['key' => 'model_spec', 'label' => '规格型号', 'type' => 'text', 'required' => false],
            ['key' => 'manufacture_date', 'label' => '出厂日期', 'type' => 'date', 'required' => false],
            ['key' => 'received_date', 'label' => '接收日期', 'type' => 'date', 'required' => false],
            ['key' => 'started_date', 'label' => '启用日期', 'type' => 'date', 'required' => false],
            ['key' => 'storage_location', 'label' => '存放地点', 'type' => 'text', 'required' => false],
            ['key' => 'manual_number', 'label' => '说明书编号', 'type' => 'text', 'required' => false],
            ['key' => 'received_status', 'label' => '接收状态', 'type' => 'select', 'options' => ['全新的', '用过的', '经过改装'], 'required' => false],
            ['key' => 'maintenance_method', 'label' => '维护方式', 'type' => 'select', 'options' => ['合同维护保养', '自行维护保养'], 'required' => false],
            ['key' => 'calibration_method', 'label' => '校准/检定方式', 'type' => 'select', 'options' => ['合同校准/检定', '自行校准/验证'], 'required' => false],
            ['key' => 'calibration_items', 'label' => '校准/检定记录', 'type' => 'repeatable_table', 'columns' => [
                ['key' => 'calibration_date', 'label' => '校准/检定日期', 'type' => 'date', 'required' => false],
                ['key' => 'valid_until', 'label' => '有效期', 'type' => 'date', 'required' => false],
                ['key' => 'certificate_number', 'label' => '证书编号', 'type' => 'text', 'required' => false],
                ['key' => 'remarks', 'label' => '备注', 'type' => 'text', 'required' => false],
            ]],
        ];
    }

    private static function fieldEquipmentPerformanceSchema(): array
    {
        return [
            ['key' => 'equipment_name', 'label' => '设备名称', 'type' => 'text', 'required' => true],
            ['key' => 'equipment_code', 'label' => '设备编号', 'type' => 'text', 'required' => true],
            ['key' => 'performance_items', 'label' => '现场试验设备性能检查明细', 'type' => 'repeatable_table', 'columns' => [
                ['key' => 'use_date', 'label' => '使用日期', 'type' => 'date', 'required' => false],
                ['key' => 'test_item', 'label' => '检测项目', 'type' => 'text', 'required' => false],
                ['key' => 'run_time', 'label' => '运行时间', 'type' => 'text', 'required' => false],
                ['key' => 'return_time', 'label' => '运回时间', 'type' => 'text', 'required' => false],
                ['key' => 'return_performance', 'label' => '运回性能', 'type' => 'select', 'options' => ['正常', '异常'], 'required' => false],
                ['key' => 'user', 'label' => '使用人', 'type' => 'person', 'required' => false],
                ['key' => 'remarks', 'label' => '备注', 'type' => 'text', 'required' => false],
            ]],
        ];
    }

    private static function periodCheckPlanSchema(): array
    {
        return [
            ['key' => 'plan_items', 'label' => '核查计划明细', 'type' => 'repeatable_table', 'columns' => [
                ['key' => 'check_object', 'label' => '被核查仪器设备或标准物质名称和编号', 'type' => 'text', 'required' => true],
                ['key' => 'planned_time', 'label' => '核查计划实施时间', 'type' => 'text', 'required' => false],
                ['key' => 'responsible_department', 'label' => '责任部门', 'type' => 'department', 'required' => false],
                ['key' => 'responsible_person', 'label' => '责任人', 'type' => 'person', 'required' => false],
            ]],
            ['key' => 'prepared_by', 'label' => '编制人（设备管理员）', 'type' => 'person', 'required' => false],
            ['key' => 'prepared_date', 'label' => '编制日期', 'type' => 'date', 'required' => false],
            ['key' => 'approved_by', 'label' => '审核/批准人（技术负责人）', 'type' => 'person', 'required' => false],
            ['key' => 'approved_date', 'label' => '审核/批准日期', 'type' => 'date', 'required' => false],
        ];
    }

    private static function periodCheckSchemeSchema(): array
    {
        return [
            ['key' => 'checked_object', 'label' => '被核查设备或标准物质', 'type' => 'text', 'required' => true],
            ['key' => 'team_leader', 'label' => '核查组长', 'type' => 'person', 'required' => false],
            ['key' => 'team_members', 'label' => '核查组员', 'type' => 'textarea', 'required' => false],
            ['key' => 'check_time', 'label' => '核查时间', 'type' => 'text', 'required' => false],
            ['key' => 'check_place', 'label' => '核查地点', 'type' => 'text', 'required' => false],
            ['key' => 'execution_files', 'label' => '执行文件', 'type' => 'textarea', 'required' => false, 'default' => "《仪器设备和标准物质期间核查程序》\n期间核查作业指导书"],
            ['key' => 'calibration_or_validity_period', 'label' => '检定周期时间或标准物质有效期', 'type' => 'textarea', 'required' => false],
            ['key' => 'prepared_by', 'label' => '编制人（设备管理员）', 'type' => 'person', 'required' => false],
            ['key' => 'prepared_date', 'label' => '编制日期', 'type' => 'date', 'required' => false],
            ['key' => 'approved_by', 'label' => '审核/批准人（技术负责人）', 'type' => 'person', 'required' => false],
            ['key' => 'approved_date', 'label' => '审核/批准日期', 'type' => 'date', 'required' => false],
        ];
    }

    private static function periodCheckRecordSchema(string $sourceFileName): array
    {
        $defaults = self::equipmentDefaultsFromFileName($sourceFileName);

        return [
            self::withDefault(['key' => 'equipment_name', 'label' => '名称', 'type' => 'text', 'required' => true], $defaults, 'equipment_name'),
            self::withDefault(['key' => 'model_spec', 'label' => '型号规格', 'type' => 'text', 'required' => false], $defaults, 'model_spec'),
            self::withDefault(['key' => 'equipment_code', 'label' => '编号', 'type' => 'text', 'required' => false], $defaults, 'equipment_code'),
            self::withDefault(['key' => 'check_basis', 'label' => '核查依据', 'type' => 'textarea', 'required' => false], $defaults, 'check_basis'),
            ['key' => 'check_resources', 'label' => '核查所用仪器设备或标准物质', 'type' => 'textarea', 'required' => false],
            ['key' => 'check_personnel', 'label' => '核查人员', 'type' => 'textarea', 'required' => false],
            ['key' => 'process_record', 'label' => '核查过程记录', 'type' => 'textarea', 'required' => false],
            ['key' => 'recorder', 'label' => '记录人（设备管理员）', 'type' => 'person', 'required' => false],
            ['key' => 'record_date', 'label' => '记录日期', 'type' => 'date', 'required' => false],
            ['key' => 'result_judgement', 'label' => '核查结果判定', 'type' => 'textarea', 'required' => false],
            ['key' => 'checkers', 'label' => '核查人员签名', 'type' => 'textarea', 'required' => false],
            ['key' => 'check_date', 'label' => '核查日期', 'type' => 'date', 'required' => false],
            ['key' => 'reviewer_opinion', 'label' => '审核人意见', 'type' => 'textarea', 'required' => false],
            ['key' => 'reviewer', 'label' => '审核人', 'type' => 'person', 'required' => false],
            ['key' => 'review_date', 'label' => '审核日期', 'type' => 'date', 'required' => false],
        ];
    }

    private static function functionCheckRecordSchema(string $sourceFileName): array
    {
        $schema = self::periodCheckRecordSchema($sourceFileName);
        foreach ($schema as &$field) {
            if ($field['key'] === 'result_judgement') {
                $field['key'] = 'function_result';
                $field['label'] = '功能性核查结果';
                $field['print_bind'] = 'function_result';
            }
        }
        unset($field);

        return $schema;
    }

    private static function periodCheckReportSchema(string $sourceFileName): array
    {
        $defaults = self::equipmentDefaultsFromFileName($sourceFileName);

        return [
            self::withDefault(['key' => 'equipment_name', 'label' => '名称', 'type' => 'text', 'required' => true], $defaults, 'equipment_name'),
            self::withDefault(['key' => 'model_spec', 'label' => '型号规格', 'type' => 'text', 'required' => false], $defaults, 'model_spec'),
            self::withDefault(['key' => 'equipment_code', 'label' => '编号', 'type' => 'text', 'required' => false], $defaults, 'equipment_code'),
            self::withDefault(['key' => 'check_basis', 'label' => '核查依据', 'type' => 'textarea', 'required' => false], $defaults, 'check_basis'),
            ['key' => 'check_items', 'label' => '核查项目', 'type' => 'textarea', 'required' => false],
            ['key' => 'check_personnel', 'label' => '核查人员', 'type' => 'textarea', 'required' => false],
            ['key' => 'check_standard', 'label' => '核查标准', 'type' => 'textarea', 'required' => false],
            ['key' => 'result_judgement', 'label' => '核查结果判定', 'type' => 'textarea', 'required' => false],
            ['key' => 'responsible_person', 'label' => '负责人', 'type' => 'person', 'required' => false],
            ['key' => 'responsible_date', 'label' => '负责人日期', 'type' => 'date', 'required' => false],
            ['key' => 'evaluation', 'label' => '期间核查评价', 'type' => 'textarea', 'required' => false],
            ['key' => 'evaluation_responsible_person', 'label' => '评价负责人', 'type' => 'person', 'required' => false],
            ['key' => 'evaluation_date', 'label' => '评价日期', 'type' => 'date', 'required' => false],
            ['key' => 'reviewer_opinion', 'label' => '审核人意见', 'type' => 'textarea', 'required' => false],
            ['key' => 'reviewer', 'label' => '审核人', 'type' => 'person', 'required' => false],
            ['key' => 'review_date', 'label' => '审核日期', 'type' => 'date', 'required' => false],
        ];
    }

    private static function schemaFor(string $docNumber, string $name, string $module, string $matchConclusion, string $suggestion): array
    {
        $text = $docNumber . ' ' . $name . ' ' . $module . ' ' . $matchConclusion . ' ' . $suggestion;

        if ($docNumber === 'XZTC/BG-34-01' && str_contains($name, '监控维护管理')) {
            return self::monitorMaintenanceSchema();
        }
        if ($docNumber === 'XZTC/BG-34-02' && str_contains($name, '监控信息图像查看')) {
            return self::monitorImageViewSchema();
        }
        if ($docNumber === 'XZTC/BG-21-01' && str_contains($name, '管理评审计划')) {
            return self::managementReviewPlanSchema();
        }
        if ($docNumber === 'XZTC/BG-21-02' && str_contains($name, '管理评审报告')) {
            return self::managementReviewReportSchema();
        }
        if ($docNumber === 'XZTC/BG-21-03' && str_contains($name, '管理评审')) {
            return self::managementReviewMeetingRecordSchema();
        }
        if ($docNumber === 'XZTC/BG-26-01' && str_contains($name, '计算机软件登记')) {
            return self::computerSoftwareRegisterSchema();
        }
        if ($docNumber === 'XZTC/BG-26-02' && str_contains($name, '计算机内容变更')) {
            return self::computerContentChangeRequestSchema();
        }
        if ($docNumber === 'XZTC/BG-20-04' && str_contains($name, '授权签字人审核')) {
            return self::authorizedSignerReviewSchema();
        }
        if ($docNumber === '待定-20-04' && str_contains($name, '内部审核资料封皮目录')) {
            return self::internalAuditArchiveCatalogSchema();
        }
        if ($docNumber === 'XZTC/BG-28-02' && str_contains($name, '样品标识卡')) {
            return self::sampleIdentificationCardSchema();
        }
        if (str_contains($module, '人员') || str_contains($text, '培训') || str_contains($text, '人员')) {
            return self::personnelSchema();
        }
        if (str_contains($module, '仪器设备') || str_contains($module, '标准物质') || str_contains($text, '设备') || str_contains($text, '核查') || str_contains($text, '校准') || str_contains($text, '溯源')) {
            return self::equipmentSchema();
        }
        if (str_contains($module, '文件控制') || str_contains($text, '文件') || str_contains($text, '资料')) {
            return self::documentControlSchema();
        }
        if (str_contains($module, '内部管理体系审核') || str_contains($text, '内审') || str_contains($text, '审核')) {
            return self::auditSchema();
        }
        if (str_contains($module, '管理评审') || str_contains($text, '管评') || str_contains($text, '评审')) {
            return self::managementReviewSchema();
        }
        if (str_contains($module, '质量控制') || str_contains($text, '质量监控') || str_contains($text, '比对') || str_contains($text, '能力验证')) {
            return self::qualityControlSchema();
        }
        if (str_contains($module, '样品') || str_contains($text, '样品')) {
            return self::sampleSchema();
        }
        if (str_contains($text, '投诉') || str_contains($text, '不符合') || str_contains($text, '纠正') || str_contains($text, '预防')) {
            return self::improvementSchema();
        }

        return self::generalSchema();
    }

    private static function personnelSchema(): array
    {
        return [
            ['key' => 'record_date', 'label' => '记录日期', 'type' => 'date', 'required' => true],
            ['key' => 'topic', 'label' => '主题/事项', 'type' => 'text', 'required' => true],
            ['key' => 'responsible_person', 'label' => '负责人', 'type' => 'person', 'required' => false],
            ['key' => 'content', 'label' => '内容说明', 'type' => 'textarea', 'required' => false],
            ['key' => 'personnel', 'label' => '人员明细', 'type' => 'repeatable_table', 'columns' => [
                ['key' => 'name', 'label' => '姓名', 'type' => 'person', 'required' => true],
                ['key' => 'department', 'label' => '部门', 'type' => 'department', 'required' => false],
                ['key' => 'role_or_result', 'label' => '岗位/结果', 'type' => 'text', 'required' => false],
                ['key' => 'signature', 'label' => '签名', 'type' => 'signature', 'required' => false],
            ]],
            ['key' => 'evaluation', 'label' => '评价/结论', 'type' => 'textarea', 'required' => false],
        ];
    }

    private static function equipmentSchema(): array
    {
        return [
            ['key' => 'record_date', 'label' => '记录日期', 'type' => 'date', 'required' => true],
            ['key' => 'equipment_name', 'label' => '设备/标准物质名称', 'type' => 'text', 'required' => true],
            ['key' => 'equipment_code', 'label' => '编号', 'type' => 'text', 'required' => false],
            ['key' => 'responsible_person', 'label' => '负责人', 'type' => 'person', 'required' => false],
            ['key' => 'check_items', 'label' => '项目明细', 'type' => 'repeatable_table', 'columns' => [
                ['key' => 'item', 'label' => '项目', 'type' => 'text', 'required' => true],
                ['key' => 'method', 'label' => '方法/依据', 'type' => 'text', 'required' => false],
                ['key' => 'result', 'label' => '结果', 'type' => 'text', 'required' => false],
                ['key' => 'conclusion', 'label' => '结论', 'type' => 'select', 'options' => ['合格', '不合格', '不适用'], 'required' => false],
            ]],
            ['key' => 'remarks', 'label' => '备注', 'type' => 'textarea', 'required' => false],
        ];
    }

    private static function documentControlSchema(): array
    {
        return [
            ['key' => 'record_date', 'label' => '记录日期', 'type' => 'date', 'required' => true],
            ['key' => 'document_number', 'label' => '文件编号', 'type' => 'text', 'required' => false],
            ['key' => 'document_name', 'label' => '文件名称', 'type' => 'text', 'required' => true],
            ['key' => 'version', 'label' => '版本/状态', 'type' => 'text', 'required' => false],
            ['key' => 'handled_by', 'label' => '经办人', 'type' => 'person', 'required' => false],
            ['key' => 'distribution', 'label' => '发放/回收/借阅明细', 'type' => 'repeatable_table', 'columns' => [
                ['key' => 'department', 'label' => '部门', 'type' => 'department', 'required' => false],
                ['key' => 'person', 'label' => '人员', 'type' => 'person', 'required' => false],
                ['key' => 'action', 'label' => '事项', 'type' => 'select', 'options' => ['发放', '回收', '借阅', '置换', '作废', '登记'], 'required' => false],
                ['key' => 'date', 'label' => '日期', 'type' => 'date', 'required' => false],
                ['key' => 'signature', 'label' => '签名', 'type' => 'signature', 'required' => false],
            ]],
            ['key' => 'remarks', 'label' => '备注', 'type' => 'textarea', 'required' => false],
        ];
    }

    private static function controlledFileRegisterSchema(): array
    {
        return [
            ['key' => 'controlled_file_items', 'label' => '内部受控文件登记明细', 'type' => 'repeatable_table', 'columns' => [
                ['key' => 'document_name', 'label' => '文件名称', 'type' => 'text', 'required' => true],
                ['key' => 'document_code', 'label' => '文件控制编号', 'type' => 'text', 'required' => true],
                ['key' => 'version', 'label' => '版本号', 'type' => 'text', 'required' => false],
                ['key' => 'prepared_by', 'label' => '编制人', 'type' => 'person', 'required' => false],
                ['key' => 'reviewed_by', 'label' => '审核人', 'type' => 'person', 'required' => false],
                ['key' => 'approved_by', 'label' => '批准人', 'type' => 'person', 'required' => false],
                ['key' => 'approval_date', 'label' => '批准日期', 'type' => 'date', 'required' => false],
            ]],
        ];
    }

    private static function externalFileRegisterSchema(): array
    {
        return [
            ['key' => 'external_file_items', 'label' => '外来文件资料登记明细', 'type' => 'repeatable_table', 'columns' => [
                ['key' => 'internal_control_number', 'label' => '内部控制编号', 'type' => 'text', 'required' => false],
                ['key' => 'document_name', 'label' => '文件名称', 'type' => 'text', 'required' => true],
                ['key' => 'original_number', 'label' => '文件原编号', 'type' => 'text', 'required' => false],
                ['key' => 'quantity', 'label' => '数量', 'type' => 'number', 'required' => false],
                ['key' => 'remarks', 'label' => '备注', 'type' => 'text', 'required' => false],
            ]],
        ];
    }

    private static function fileDistributionRecoverySchema(): array
    {
        return [
            ['key' => 'distribution_items', 'label' => '文件发放回收登记明细', 'type' => 'repeatable_table', 'columns' => [
                ['key' => 'document_name', 'label' => '文件名称', 'type' => 'text', 'required' => true],
                ['key' => 'document_code', 'label' => '文件控制编号', 'type' => 'text', 'required' => false],
                ['key' => 'version', 'label' => '版本', 'type' => 'text', 'required' => false],
                ['key' => 'distribution_number', 'label' => '发放编号', 'type' => 'text', 'required' => false],
                ['key' => 'issuer', 'label' => '发放人', 'type' => 'person', 'required' => false],
                ['key' => 'recipient', 'label' => '签收人', 'type' => 'person', 'required' => false],
                ['key' => 'recipient_department', 'label' => '签收部门', 'type' => 'department', 'required' => false],
                ['key' => 'issue_date', 'label' => '发放日期', 'type' => 'date', 'required' => false],
                ['key' => 'returned_by', 'label' => '交回人', 'type' => 'person', 'required' => false],
                ['key' => 'return_receiver', 'label' => '回收签收人', 'type' => 'person', 'required' => false],
                ['key' => 'return_date', 'label' => '回收日期', 'type' => 'date', 'required' => false],
            ]],
        ];
    }

    private static function fileBorrowRegisterSchema(): array
    {
        return [
            ['key' => 'borrow_items', 'label' => '文件借阅登记明细', 'type' => 'repeatable_table', 'columns' => [
                ['key' => 'document_name', 'label' => '文件名称', 'type' => 'text', 'required' => true],
                ['key' => 'document_code', 'label' => '文件控制编号', 'type' => 'text', 'required' => false],
                ['key' => 'borrower', 'label' => '借阅人', 'type' => 'person', 'required' => false],
                ['key' => 'issuer', 'label' => '发放人', 'type' => 'person', 'required' => false],
                ['key' => 'borrow_date', 'label' => '借阅日期', 'type' => 'date', 'required' => false],
                ['key' => 'return_date', 'label' => '归还日期', 'type' => 'date', 'required' => false],
            ]],
        ];
    }

    private static function fileReplacementRequestSchema(): array
    {
        return [
            ['key' => 'document_name', 'label' => '文件名称', 'type' => 'text', 'required' => true],
            ['key' => 'document_code', 'label' => '文件控制编号', 'type' => 'text', 'required' => false],
            ['key' => 'distribution_number', 'label' => '发放编号', 'type' => 'text', 'required' => false],
            ['key' => 'applicant', 'label' => '申请人', 'type' => 'person', 'required' => false],
            ['key' => 'quantity', 'label' => '数量', 'type' => 'number', 'required' => false],
            ['key' => 'application_reason', 'label' => '申请理由', 'type' => 'textarea', 'required' => false],
            ['key' => 'application_date', 'label' => '申请日期', 'type' => 'date', 'required' => false],
            ['key' => 'approval_opinion', 'label' => '批准意见', 'type' => 'textarea', 'required' => false],
            ['key' => 'quality_manager', 'label' => '批准人（质量负责人）', 'type' => 'person', 'required' => false],
            ['key' => 'approval_date', 'label' => '批准日期', 'type' => 'date', 'required' => false],
        ];
    }

    private static function fileChangeApprovalSchema(): array
    {
        return [
            ['key' => 'document_name', 'label' => '文件名称', 'type' => 'text', 'required' => true],
            ['key' => 'document_code', 'label' => '文件控制编号', 'type' => 'text', 'required' => false],
            ['key' => 'applicant', 'label' => '申请人', 'type' => 'person', 'required' => false],
            ['key' => 'proposed_date', 'label' => '提出日期', 'type' => 'date', 'required' => false],
            ['key' => 'reason_customer_need', 'label' => '客户需求', 'type' => 'checkbox', 'required' => false],
            ['key' => 'reason_law_requirement', 'label' => '法律法规要求', 'type' => 'checkbox', 'required' => false],
            ['key' => 'reason_external_audit', 'label' => '外部审核提出', 'type' => 'checkbox', 'required' => false],
            ['key' => 'reason_management_review', 'label' => '管理评审提出', 'type' => 'checkbox', 'required' => false],
            ['key' => 'reason_system_improvement', 'label' => '完善体系文件', 'type' => 'checkbox', 'required' => false],
            ['key' => 'before_content', 'label' => '修改前内容', 'type' => 'textarea', 'required' => false],
            ['key' => 'after_content', 'label' => '修改后内容', 'type' => 'textarea', 'required' => false],
            ['key' => 'review_opinion', 'label' => '审核意见', 'type' => 'textarea', 'required' => false],
            ['key' => 'reviewer', 'label' => '审核人（签字）', 'type' => 'person', 'required' => false],
            ['key' => 'review_date', 'label' => '审核日期', 'type' => 'date', 'required' => false],
            ['key' => 'approval_opinion', 'label' => '批准意见', 'type' => 'textarea', 'required' => false],
            ['key' => 'approver', 'label' => '批准人（签字）', 'type' => 'person', 'required' => false],
            ['key' => 'approval_date', 'label' => '批准日期', 'type' => 'date', 'required' => false],
        ];
    }

    private static function fileDestructionRecordSchema(): array
    {
        return [
            ['key' => 'document_name', 'label' => '文件名称', 'type' => 'text', 'required' => true],
            ['key' => 'distribution_number', 'label' => '发放编号', 'type' => 'text', 'required' => false],
            ['key' => 'destruction_reason', 'label' => '文件销毁原因', 'type' => 'textarea', 'required' => false],
            ['key' => 'applicant', 'label' => '申请人（资料管理员）', 'type' => 'person', 'required' => false],
            ['key' => 'application_date', 'label' => '申请日期', 'type' => 'date', 'required' => false],
            ['key' => 'approval_opinion', 'label' => '审批意见', 'type' => 'textarea', 'required' => false],
            ['key' => 'approver', 'label' => '批准人（质量负责人）', 'type' => 'person', 'required' => false],
            ['key' => 'approval_date', 'label' => '批准日期', 'type' => 'date', 'required' => false],
            ['key' => 'destroy_date', 'label' => '销毁日期', 'type' => 'date', 'required' => false],
            ['key' => 'destroyer', 'label' => '销毁人', 'type' => 'person', 'required' => false],
            ['key' => 'copy_count', 'label' => '销毁文件份数', 'type' => 'number', 'required' => false],
            ['key' => 'supervisor', 'label' => '监销人', 'type' => 'person', 'required' => false],
        ];
    }

    private static function meetingSignInRecordSchema(): array
    {
        return [
            ['key' => 'meeting_topic', 'label' => '会议主题', 'type' => 'text', 'required' => true],
            ['key' => 'meeting_time', 'label' => '时间', 'type' => 'text', 'required' => false],
            ['key' => 'meeting_place', 'label' => '地点', 'type' => 'text', 'required' => false],
            ['key' => 'attendees', 'label' => '参会签到明细', 'type' => 'repeatable_table', 'columns' => [
                ['key' => 'name', 'label' => '姓名', 'type' => 'person', 'required' => false],
                ['key' => 'department', 'label' => '部门', 'type' => 'department', 'required' => false],
                ['key' => 'signature', 'label' => '签名', 'type' => 'signature', 'required' => false],
            ]],
            ['key' => 'meeting_content', 'label' => '会议内容', 'type' => 'textarea', 'required' => false],
            ['key' => 'recorder', 'label' => '记录人', 'type' => 'person', 'required' => false],
        ];
    }

    private static function sampleOriginalRecordSchema(): array
    {
        return [
            ['key' => 'test_date', 'label' => '日期', 'type' => 'date', 'required' => false],
            ['key' => 'sample_number', 'label' => '样品编号', 'type' => 'text', 'required' => true],
            ['key' => 'total_mass', 'label' => '总质量（g）', 'type' => 'number', 'required' => false],
            ['key' => 'density', 'label' => '密度（g/cm³）', 'type' => 'text', 'required' => false],
            ['key' => 'refractive_index', 'label' => '折射率/双折射率', 'type' => 'text', 'required' => false],
            ['key' => 'magnification', 'label' => '放大检查', 'type' => 'textarea', 'required' => false],
            ['key' => 'pleochroism', 'label' => '多色性', 'type' => 'text', 'required' => false],
            ['key' => 'optical_character', 'label' => '光性特征', 'type' => 'text', 'required' => false],
            ['key' => 'uv_fluorescence', 'label' => '紫外荧光', 'type' => 'text', 'required' => false],
            ['key' => 'absorption_spectrum', 'label' => '吸收光谱', 'type' => 'textarea', 'required' => false],
            ['key' => 'test_conclusion', 'label' => '检测结论', 'type' => 'textarea', 'required' => false],
            ['key' => 'tester', 'label' => '检测员', 'type' => 'person', 'required' => false],
            ['key' => 'recorder', 'label' => '记录员', 'type' => 'person', 'required' => false],
            ['key' => 'verifier', 'label' => '校核员', 'type' => 'person', 'required' => false],
        ];
    }

    private static function auditSchema(): array
    {
        return [
            ['key' => 'audit_date', 'label' => '审核日期', 'type' => 'date', 'required' => true],
            ['key' => 'audited_department', 'label' => '受审核部门', 'type' => 'department', 'required' => false],
            ['key' => 'auditor', 'label' => '审核员', 'type' => 'person', 'required' => false],
            ['key' => 'audit_scope', 'label' => '审核范围/依据', 'type' => 'textarea', 'required' => false],
            ['key' => 'check_items', 'label' => '检查/发现明细', 'type' => 'repeatable_table', 'columns' => [
                ['key' => 'clause', 'label' => '条款/过程', 'type' => 'text', 'required' => false],
                ['key' => 'requirement', 'label' => '要求', 'type' => 'textarea', 'required' => false],
                ['key' => 'evidence', 'label' => '证据/事实', 'type' => 'textarea', 'required' => false],
                ['key' => 'result', 'label' => '结果', 'type' => 'select', 'options' => ['符合', '不符合', '观察项', '不适用'], 'required' => false],
            ]],
            ['key' => 'conclusion', 'label' => '结论/整改要求', 'type' => 'textarea', 'required' => false],
        ];
    }

    private static function managementReviewSchema(): array
    {
        return [
            ['key' => 'review_year', 'label' => '评审年度', 'type' => 'text', 'required' => true],
            ['key' => 'meeting_date', 'label' => '会议日期', 'type' => 'date', 'required' => false],
            ['key' => 'host', 'label' => '主持人', 'type' => 'person', 'required' => false],
            ['key' => 'participants', 'label' => '参加人员', 'type' => 'textarea', 'required' => false],
            ['key' => 'inputs', 'label' => '输入/议题明细', 'type' => 'repeatable_table', 'columns' => [
                ['key' => 'topic', 'label' => '主题', 'type' => 'text', 'required' => true],
                ['key' => 'owner', 'label' => '责任人', 'type' => 'person', 'required' => false],
                ['key' => 'material', 'label' => '资料/输入', 'type' => 'textarea', 'required' => false],
                ['key' => 'decision', 'label' => '决议/措施', 'type' => 'textarea', 'required' => false],
            ]],
            ['key' => 'follow_up', 'label' => '跟踪验证', 'type' => 'textarea', 'required' => false],
        ];
    }

    private static function managementReviewPlanSchema(): array
    {
        return [
            ['key' => 'review_time', 'label' => '评审时间', 'type' => 'text', 'required' => true],
            ['key' => 'review_place', 'label' => '评审地点', 'type' => 'text', 'required' => false],
            ['key' => 'host', 'label' => '主持人', 'type' => 'person', 'required' => true],
            ['key' => 'review_method', 'label' => '评审方式', 'type' => 'text', 'required' => false],
            ['key' => 'participants', 'label' => '参加评审人员名单', 'type' => 'repeatable_table', 'columns' => [
                ['key' => 'department_and_position', 'label' => '部门和职务', 'type' => 'text', 'required' => false],
                ['key' => 'name', 'label' => '姓名', 'type' => 'person', 'required' => false],
            ]],
            ['key' => 'input_materials', 'label' => '管理评审输入文件准备明细', 'type' => 'repeatable_table', 'columns' => [
                ['key' => 'file_name', 'label' => '输入文件名称', 'type' => 'text', 'required' => true],
                ['key' => 'preparing_department', 'label' => '准备部门', 'type' => 'department', 'required' => false],
                ['key' => 'writer', 'label' => '编写人员', 'type' => 'person', 'required' => false],
                ['key' => 'remarks', 'label' => '备注', 'type' => 'text', 'required' => false],
            ]],
            ['key' => 'prepared_by', 'label' => '编制人（质量负责人）', 'type' => 'person', 'required' => false],
            ['key' => 'prepared_date', 'label' => '编制日期', 'type' => 'date', 'required' => false],
            ['key' => 'approved_by', 'label' => '批准人（实验室主任）', 'type' => 'person', 'required' => false],
            ['key' => 'approved_date', 'label' => '批准日期', 'type' => 'date', 'required' => false],
        ];
    }

    private static function managementReviewReportSchema(): array
    {
        return [
            ['key' => 'review_purpose', 'label' => '评审目的', 'type' => 'textarea', 'required' => true],
            ['key' => 'review_basis', 'label' => '评审依据', 'type' => 'textarea', 'required' => true],
            ['key' => 'review_time', 'label' => '评审时间', 'type' => 'text', 'required' => false],
            ['key' => 'review_form', 'label' => '评审形式', 'type' => 'text', 'required' => false],
            ['key' => 'host', 'label' => '评审主持人', 'type' => 'person', 'required' => true],
            ['key' => 'participants', 'label' => '参加部门及人员', 'type' => 'textarea', 'required' => false],
            ['key' => 'input_summary', 'label' => '管理评审综述（输入信息摘要）', 'type' => 'textarea', 'required' => true],
            ['key' => 'output_conclusion', 'label' => '管理评审结论（输出信息）', 'type' => 'textarea', 'required' => true],
            ['key' => 'prepared_by', 'label' => '编制人（质量负责人）', 'type' => 'person', 'required' => false],
            ['key' => 'prepared_date', 'label' => '编制日期', 'type' => 'date', 'required' => false],
            ['key' => 'approved_by', 'label' => '批准人（实验室主任）', 'type' => 'person', 'required' => false],
            ['key' => 'approved_date', 'label' => '批准日期', 'type' => 'date', 'required' => false],
        ];
    }

    private static function managementReviewMeetingRecordSchema(): array
    {
        return [
            ['key' => 'host', 'label' => '主持人', 'type' => 'person', 'required' => true],
            ['key' => 'recorder_role', 'label' => '记录及汇总', 'type' => 'person', 'required' => false],
            ['key' => 'meeting_time', 'label' => '时间', 'type' => 'text', 'required' => true],
            ['key' => 'meeting_place', 'label' => '地点', 'type' => 'text', 'required' => false],
            ['key' => 'attendees', 'label' => '参加人员签到', 'type' => 'repeatable_table', 'columns' => [
                ['key' => 'name', 'label' => '姓名', 'type' => 'person', 'required' => false],
                ['key' => 'signature', 'label' => '签名', 'type' => 'signature', 'required' => false],
            ]],
            ['key' => 'meeting_record', 'label' => '会议记录', 'type' => 'textarea', 'required' => true],
            ['key' => 'recorded_by', 'label' => '记录人', 'type' => 'person', 'required' => false],
            ['key' => 'record_date', 'label' => '记录日期', 'type' => 'date', 'required' => false],
        ];
    }

    private static function computerSoftwareRegisterSchema(): array
    {
        return [
            ['key' => 'software_items', 'label' => '计算机软件登记明细', 'type' => 'repeatable_table', 'columns' => [
                ['key' => 'software_code', 'label' => '软件编号', 'type' => 'text', 'required' => true],
                ['key' => 'software_name', 'label' => '软件名称', 'type' => 'text', 'required' => true],
                ['key' => 'purchase_date', 'label' => '购置日期', 'type' => 'date', 'required' => false],
                ['key' => 'custodian', 'label' => '保管人', 'type' => 'person', 'required' => false],
                ['key' => 'remarks', 'label' => '备注', 'type' => 'text', 'required' => false],
            ]],
        ];
    }

    private static function computerContentChangeRequestSchema(): array
    {
        return [
            ['key' => 'item_name', 'label' => '名称', 'type' => 'text', 'required' => true],
            ['key' => 'item_number', 'label' => '编号', 'type' => 'text', 'required' => false],
            ['key' => 'applicant', 'label' => '申请人', 'type' => 'person', 'required' => true],
            ['key' => 'application_time', 'label' => '申请时间', 'type' => 'date', 'required' => false],
            ['key' => 'content_to_change', 'label' => '需变更的内容', 'type' => 'textarea', 'required' => true],
            ['key' => 'change_reason', 'label' => '变更理由', 'type' => 'textarea', 'required' => true],
            ['key' => 'changed_content', 'label' => '变更后内容', 'type' => 'textarea', 'required' => true],
            ['key' => 'evaluation_or_verification', 'label' => '评价或验证结论', 'type' => 'textarea', 'required' => false],
            ['key' => 'office_director', 'label' => '办公室主任', 'type' => 'person', 'required' => false],
            ['key' => 'office_director_date', 'label' => '办公室主任日期', 'type' => 'date', 'required' => false],
            ['key' => 'approved_by', 'label' => '批准人（技术负责人）', 'type' => 'person', 'required' => false],
            ['key' => 'approval_date', 'label' => '批准日期', 'type' => 'date', 'required' => false],
        ];
    }

    private static function authorizedSignerReviewSchema(): array
    {
        $yesNo = ['是', '否'];

        return [
            ['key' => 'record_number', 'label' => '编号', 'type' => 'text', 'required' => false],
            ['key' => 'person_name', 'label' => '姓名', 'type' => 'person', 'required' => true],
            ['key' => 'position', 'label' => '职务', 'type' => 'text', 'required' => false],
            ['key' => 'professional_title', 'label' => '职称', 'type' => 'text', 'required' => false],
            ['key' => 'authorization_scope', 'label' => '授权签字的范围', 'type' => 'textarea', 'required' => true],
            ['key' => 'responsibility_authority', 'label' => '具有相应职责和权利，对检测结果完整性和准确性负责', 'type' => 'select', 'options' => $yesNo, 'required' => true],
            ['key' => 'technical_contact', 'label' => '与检测技术接触紧密，掌握检测项目限制范围', 'type' => 'select', 'options' => $yesNo, 'required' => true],
            ['key' => 'standards_methods', 'label' => '熟悉检测标准、测试方法及测试规程', 'type' => 'select', 'options' => $yesNo, 'required' => true],
            ['key' => 'result_evaluation', 'label' => '有能力对相关检测结果进行评定并了解不确定度', 'type' => 'select', 'options' => $yesNo, 'required' => true],
            ['key' => 'equipment_status', 'label' => '了解设备维护保养及定期检定规定并掌握设备状态', 'type' => 'select', 'options' => $yesNo, 'required' => true],
            ['key' => 'records_reports', 'label' => '十分熟悉记录、报告及其核查程序', 'type' => 'select', 'options' => $yesNo, 'required' => true],
            ['key' => 'criteria_and_mark_use', 'label' => '了解评审准则、实验室义务及标识标志使用规定', 'type' => 'select', 'options' => $yesNo, 'required' => true],
            ['key' => 'review_result', 'label' => '评审意见', 'type' => 'select', 'options' => ['授权签字人评审合格', '授权签字人评审不合格'], 'required' => true],
            ['key' => 'auditor', 'label' => '内审员签名', 'type' => 'person', 'required' => false],
            ['key' => 'audit_leader', 'label' => '内审组长签名', 'type' => 'person', 'required' => false],
            ['key' => 'review_date', 'label' => '日期', 'type' => 'date', 'required' => false],
        ];
    }

    private static function internalAuditArchiveCatalogSchema(): array
    {
        return [
            ['key' => 'audit_year', 'label' => '内部审核年度', 'type' => 'text', 'required' => true],
            ['key' => 'archive_date', 'label' => '资料日期', 'type' => 'date', 'required' => false],
            ['key' => 'catalog_items', 'label' => '内部审核资料目录', 'type' => 'repeatable_table', 'columns' => [
                ['key' => 'sequence', 'label' => '序号', 'type' => 'number', 'required' => false],
                ['key' => 'document_name', 'label' => '资料名称', 'type' => 'text', 'required' => true],
                ['key' => 'included', 'label' => '是否归档', 'type' => 'checkbox', 'required' => false],
                ['key' => 'remarks', 'label' => '备注', 'type' => 'text', 'required' => false],
            ]],
        ];
    }

    private static function sampleIdentificationCardSchema(): array
    {
        return [
            ['key' => 'sample_name', 'label' => '样品名称', 'type' => 'text', 'required' => true],
            ['key' => 'sample_number', 'label' => '样品编号', 'type' => 'text', 'required' => true],
            ['key' => 'sample_quantity', 'label' => '样品数量', 'type' => 'text', 'required' => false],
            ['key' => 'received_date', 'label' => '来样日期', 'type' => 'date', 'required' => false],
            ['key' => 'detection_status', 'label' => '检测状态', 'type' => 'select', 'options' => ['待检', '在检', '已检', '留样'], 'required' => true],
            ['key' => 'inspector', 'label' => '检测员（签名）', 'type' => 'person', 'required' => false],
            ['key' => 'inspector_time', 'label' => '检测员时间', 'type' => 'text', 'required' => false],
            ['key' => 'photographer', 'label' => '拍照员（签名）', 'type' => 'person', 'required' => false],
            ['key' => 'photographer_time', 'label' => '拍照员时间', 'type' => 'text', 'required' => false],
            ['key' => 'data_entry_person', 'label' => '录入员（签名）', 'type' => 'person', 'required' => false],
            ['key' => 'data_entry_time', 'label' => '录入员时间', 'type' => 'text', 'required' => false],
            ['key' => 'packer', 'label' => '打包员（签名）', 'type' => 'person', 'required' => false],
            ['key' => 'packer_time', 'label' => '打包员时间', 'type' => 'text', 'required' => false],
        ];
    }

    private static function qualityControlSchema(): array
    {
        return [
            ['key' => 'monitor_date', 'label' => '监控日期', 'type' => 'date', 'required' => true],
            ['key' => 'monitor_type', 'label' => '监控类型', 'type' => 'select', 'options' => ['留样再测', '人员比对', '设备比对', '标准物质核查', '能力验证', '其他'], 'required' => false],
            ['key' => 'sample_info', 'label' => '样品/项目信息', 'type' => 'textarea', 'required' => false],
            ['key' => 'results', 'label' => '结果明细', 'type' => 'repeatable_table', 'columns' => [
                ['key' => 'item', 'label' => '项目', 'type' => 'text', 'required' => true],
                ['key' => 'expected', 'label' => '预期/参考值', 'type' => 'text', 'required' => false],
                ['key' => 'actual', 'label' => '实测结果', 'type' => 'text', 'required' => false],
                ['key' => 'judgement', 'label' => '判定', 'type' => 'select', 'options' => ['满意', '可疑', '不满意', '不适用'], 'required' => false],
            ]],
            ['key' => 'follow_up', 'label' => '后续措施', 'type' => 'textarea', 'required' => false],
        ];
    }

    private static function sampleSchema(): array
    {
        return [
            ['key' => 'record_date', 'label' => '记录日期', 'type' => 'date', 'required' => true],
            ['key' => 'sample_code', 'label' => '样品编号', 'type' => 'text', 'required' => false],
            ['key' => 'sample_name', 'label' => '样品名称', 'type' => 'text', 'required' => false],
            ['key' => 'handler', 'label' => '经办人', 'type' => 'person', 'required' => false],
            ['key' => 'sample_items', 'label' => '样品明细', 'type' => 'repeatable_table', 'columns' => [
                ['key' => 'code', 'label' => '编号', 'type' => 'text', 'required' => false],
                ['key' => 'name', 'label' => '名称', 'type' => 'text', 'required' => false],
                ['key' => 'status', 'label' => '状态/处置', 'type' => 'text', 'required' => false],
                ['key' => 'date', 'label' => '日期', 'type' => 'date', 'required' => false],
            ]],
            ['key' => 'remarks', 'label' => '备注', 'type' => 'textarea', 'required' => false],
        ];
    }

    private static function improvementSchema(): array
    {
        return [
            ['key' => 'record_date', 'label' => '记录日期', 'type' => 'date', 'required' => true],
            ['key' => 'source', 'label' => '来源', 'type' => 'text', 'required' => false],
            ['key' => 'responsible_department', 'label' => '责任部门', 'type' => 'department', 'required' => false],
            ['key' => 'responsible_person', 'label' => '责任人', 'type' => 'person', 'required' => false],
            ['key' => 'description', 'label' => '事实描述', 'type' => 'textarea', 'required' => false],
            ['key' => 'actions', 'label' => '措施明细', 'type' => 'repeatable_table', 'columns' => [
                ['key' => 'cause', 'label' => '原因/问题', 'type' => 'textarea', 'required' => false],
                ['key' => 'action', 'label' => '措施', 'type' => 'textarea', 'required' => false],
                ['key' => 'owner', 'label' => '责任人', 'type' => 'person', 'required' => false],
                ['key' => 'due_date', 'label' => '完成期限', 'type' => 'date', 'required' => false],
                ['key' => 'status', 'label' => '状态', 'type' => 'select', 'options' => ['未开始', '进行中', '已完成', '已验证'], 'required' => false],
            ]],
            ['key' => 'verification', 'label' => '验证结论', 'type' => 'textarea', 'required' => false],
        ];
    }

    private static function generalSchema(): array
    {
        return [
            ['key' => 'record_date', 'label' => '记录日期', 'type' => 'date', 'required' => true],
            ['key' => 'department', 'label' => '部门', 'type' => 'department', 'required' => false],
            ['key' => 'prepared_by', 'label' => '填写人', 'type' => 'person', 'required' => false],
            ['key' => 'summary', 'label' => '事项摘要', 'type' => 'text', 'required' => false],
            ['key' => 'details', 'label' => '记录明细', 'type' => 'repeatable_table', 'columns' => [
                ['key' => 'item', 'label' => '项目', 'type' => 'text', 'required' => false],
                ['key' => 'content', 'label' => '内容', 'type' => 'textarea', 'required' => false],
                ['key' => 'result', 'label' => '结果/结论', 'type' => 'text', 'required' => false],
                ['key' => 'signature', 'label' => '签名', 'type' => 'signature', 'required' => false],
            ]],
            ['key' => 'remarks', 'label' => '备注', 'type' => 'textarea', 'required' => false],
        ];
    }

    private static function monitorMaintenanceSchema(): array
    {
        return [
            ['key' => 'maintenance_items', 'label' => '监控维护管理明细', 'type' => 'repeatable_table', 'columns' => [
                ['key' => 'sequence', 'label' => '序号', 'type' => 'number', 'required' => false],
                ['key' => 'maintenance_time', 'label' => '维护管理时间', 'type' => 'date', 'required' => true],
                ['key' => 'monitor_host', 'label' => '监控主机', 'type' => 'select', 'options' => ['正常', '异常'], 'required' => false],
                ['key' => 'monitor_display', 'label' => '监控显示器', 'type' => 'select', 'options' => ['正常', '异常'], 'required' => false],
                ['key' => 'monitor_camera', 'label' => '监控摄像头', 'type' => 'select', 'options' => ['正常', '异常'], 'required' => false],
                ['key' => 'software_system', 'label' => '软件系统', 'type' => 'select', 'options' => ['正常', '异常'], 'required' => false],
                ['key' => 'maintained_by', 'label' => '维护管理人', 'type' => 'person', 'required' => false],
                ['key' => 'remarks', 'label' => '备注', 'type' => 'textarea', 'required' => false],
            ]],
        ];
    }

    private static function monitorImageViewSchema(): array
    {
        return [
            ['key' => 'request_unit', 'label' => '申请查看单位', 'type' => 'text', 'required' => true],
            ['key' => 'request_person', 'label' => '申请查看人员', 'type' => 'person', 'required' => true],
            ['key' => 'view_time', 'label' => '调取时间', 'type' => 'date', 'required' => true],
            ['key' => 'view_purpose', 'label' => '调取用途', 'type' => 'textarea', 'required' => true],
            ['key' => 'approved_by', 'label' => '批准人', 'type' => 'signature', 'required' => true],
            ['key' => 'accompanied_by', 'label' => '陪同人', 'type' => 'signature', 'required' => false],
            ['key' => 'remarks', 'label' => '备注', 'type' => 'textarea', 'required' => false],
        ];
    }

    private static function findExisting(array $entry): ?RecordFormTemplate
    {
        $exact = RecordFormTemplate::where('soft_delete', 0)
            ->where('doc_number', $entry['doc_number'])
            ->where('name', $entry['name'])
            ->where('source_file_name', $entry['source_file_name'])
            ->find();
        if ($exact) {
            return $exact;
        }

        if (($entry['source_file_sha1'] ?? '') !== '') {
            $byHash = RecordFormTemplate::where('soft_delete', 0)
                ->where('doc_number', $entry['doc_number'])
                ->where('name', $entry['name'])
                ->where('source_file_sha1', $entry['source_file_sha1'])
                ->find();
            if ($byHash) {
                return $byHash;
            }
        }

        return RecordFormTemplate::where('soft_delete', 0)
            ->where('doc_number', $entry['doc_number'])
            ->where('name', $entry['name'])
            ->where(function ($query) {
                $query->whereNull('source_file_name')->whereOr('source_file_name', '');
            })
            ->find();
    }

    public static function retireGenericTemplates(): int
    {
        $records = RecordFormTemplate::where('soft_delete', 0)
            ->where('print_template_key', 'generic_record_form')
            ->select();

        $count = 0;
        foreach ($records as $record) {
            $record->save([
                'status' => 'obsolete',
                'review_status' => 'deferred',
                'review_note' => trim((string)$record->review_note) === ''
                    ? '统一 generic_record_form 已废弃，改用逐表高保真模板入口。'
                    : (string)$record->review_note,
                'soft_delete' => 1,
            ]);
            $count++;
        }

        return $count;
    }

    private static function copySourceFile(array $entry, string $recordId): array
    {
        $ext = strtolower(pathinfo($entry['source_file_name'], PATHINFO_EXTENSION));
        $safeName = sha1($entry['source_file_path']) . ($ext === '' ? '' : '.' . $ext);
        $dir = rtrim(public_path(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR
            . 'uploads' . DIRECTORY_SEPARATOR . self::SOURCE_SUBDIR . DIRECTORY_SEPARATOR . $recordId . DIRECTORY_SEPARATOR;
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new RuntimeException('受控附件目录创建失败');
        }

        $target = $dir . $safeName;
        if (!copy($entry['source_absolute_path'], $target)) {
            throw new RuntimeException('受控附件复制失败');
        }

        return [
            'file_name' => $entry['source_file_name'],
            'file_path' => 'uploads/' . self::SOURCE_SUBDIR . '/' . $recordId . '/' . $safeName,
        ];
    }

    private static function sortWeight(string $module, string $docNumber, int $index): string
    {
        $coreIndex = array_search($module, self::CORE_MODULES, true);
        $group = $coreIndex === false ? 99 : $coreIndex;

        return str_pad((string)$group, 2, '0', STR_PAD_LEFT) . '-' . $docNumber . '-' . str_pad((string)$index, 4, '0', STR_PAD_LEFT);
    }

    private static function repoRoot(): string
    {
        return dirname(root_path()) . DIRECTORY_SEPARATOR;
    }
}
