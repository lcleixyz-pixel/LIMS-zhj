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
        $rows = array_map(static fn(array $row): array => RecordFormReconstructionReviewService::applyForwardChainDecision($row), $rows);

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
            $printKey = (string)($row['print_template_key'] ?? '');
            if ($printKey === '') {
                $printKey = self::printTemplateKey($row['doc_number'], $row['current_name'], $sourceRelativePath, $sourceFileSha1);
            }
            $isFormal = self::isFormalTemplate($row['doc_number'], $row['current_name'], $printKey);
            $hasRegistrySchema = !$isFormal && self::hasRegistrySchema($row['doc_number'], $sourceFileName);
            $schema = $isFormal
                ? self::formalSchemaFor($row['doc_number'], $row['current_name'], $sourceFileName, $printKey)
                : self::schemaFromRegistryOrHeuristic($row['doc_number'], $name, $row['module'], $row['match_conclusion'], $row['suggestion'], $sourceFileName);
            $isFillable = self::isFillableSourceBackedTemplate($row, $schema, $printKey, $sourceFileSha1, $sourceAbsolutePath);
            $isArchiveOnly = self::isArchiveOnlySourceBackedTemplate($row);

            if ($isArchiveOnly) {
                $reviewStatus = 'deferred';
                $reviewNote = '已按正式编号归并并保留审查证据；现行有效标准由 qms_sources/外部依据台账承接，不进入记录表格发布填写路径。';
                $status = 'obsolete';
            } elseif ($isFormal) {
                $reviewStatus = 'completed';
                $reviewNote = '已按高保真打印模板完成，可正式填写。';
                $status = 'published';
            } elseif ($isFillable) {
                $reviewStatus = 'completed';
                $reviewNote = '已通过重构准备审查，具备源文件、字段 schema 和独立打印入口，可先作为非生产重构模板填写；后续可继续逐表优化版式高保真。';
                $status = 'published';
            } elseif ($hasRegistrySchema) {
                $reviewStatus = 'ai_generated';
                $reviewNote = 'AI 已从 Word 原件重建 Schema，待人工复核后可发布。';
                $status = 'draft';
            } else {
                $reviewStatus = 'needs_fidelity';
                $reviewNote = '已按现用2017源文件建立独立打印模板入口，待逐表高保真重构后再开放填写。';
                $status = 'draft';
            }

            $manifest[] = [
                'doc_number' => $row['doc_number'],
                'name' => $name,
                'base_name' => $row['current_name'],
                'module' => $row['module'],
                'version' => 'A/0',
                'status' => $status,
                'review_status' => $reviewStatus,
                'review_note' => $reviewNote,
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
                'original_doc_number' => (string)($row['_original_doc_number'] ?? $row['doc_number']),
                'forward_chain_decision_id' => (string)($row['_forward_chain_decision']['id'] ?? ''),
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
            'retired_superseded' => 0,
            'errors' => [],
        ];

        $summary['retired_generic'] = self::retireGenericTemplates();
        $manifest = self::manifest();

        foreach ($manifest as $entry) {
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

        $summary['retired_superseded'] = self::retireSupersededForwardChainTemplates($manifest);

        return $summary;
    }

    /**
     * Templates required by CNAS/CMA but missing from the current 现用文件.
     * Based on Kimi reference (参考/Kimi_Agent_珠宝检测AI专家/配套资源/02_程序文件清单与模板.md).
     * @return array[]
     */
    public static function gapTemplates(): array
    {
        return [
            // PROC-001 保护客户机密信息
            self::gapEntry('XZTC/BG-06-02', '保密承诺书', '保护客户机密信息程序', 'REC-001-01', '离职后3年'),
            self::gapEntry('XZTC/BG-06-03', '保密协议（外包人员）', '保护客户机密信息程序', 'REC-001-02', '协议终止后3年'),
            self::gapEntry('XZTC/BG-06-04', '泄密事件处理记录', '保护客户机密信息程序', 'REC-001-04', '长期'),
            self::gapEntry('XZTC/BG-06-05', '涉密信息查阅登记表', '保护客户机密信息程序', 'REC-001-05', '3年'),
            // PROC-002 公正性
            self::gapEntry('XZTC/BG-07-02', '公正性声明', '确保公正性程序', 'REC-002-01', '长期'),
            self::gapEntry('XZTC/BG-07-03', '利益冲突申报表', '确保公正性程序', 'REC-002-02', '3年'),
            self::gapEntry('XZTC/BG-07-04', '年度公正性审查报告', '确保公正性程序', 'REC-002-05', '长期'),
            // PROC-003 人员（现用已有01-01~01-09，补充缺项）
            self::gapEntry('XZTC/BG-01-12', '培训签到表', '人员培训程序', 'REC-003-02', '3年'),
            self::gapEntry('XZTC/BG-01-13', '年度培训总结报告', '人员培训程序', 'REC-003-07', '3年'),
            self::gapEntry('XZTC/BG-01-14', 'CGC人员台账', '人员培训程序', 'REC-003-A-01', '长期'),
            self::gapEntry('XZTC/BG-01-15', 'CGC继续教育学时记录', '人员培训程序', 'REC-003-A-02', '长期'),
            // PROC-004 人员监督
            self::gapEntry('XZTC/BG-31-02', '年度监督计划', '检测工作的监督程序', 'REC-004-01', '3年'),
            self::gapEntry('XZTC/BG-31-03', '监督问题汇总表', '检测工作的监督程序', 'REC-004-03', '3年'),
            // PROC-005 设施和环境
            self::gapEntry('XZTC/BG-02-02', '环境检查记录', '设施与环境条件控制和维护程序', 'REC-005-02', '3年'),
            self::gapEntry('XZTC/BG-02-03', '消防/安全检查记录', '设施与环境条件控制和维护程序', 'REC-005-04', '3年'),
            self::gapEntry('XZTC/BG-02-04', '环境偏离处理记录', '设施与环境条件控制和维护程序', 'REC-005-05', '3年'),
            // PROC-007 计量溯源
            self::gapEntry('XZTC/BG-05-02', '校准证书/报告确认记录', '实现测量可溯源程序', 'REC-007-02', '3年'),
            self::gapEntry('XZTC/BG-05-03', '校准结果评估记录', '实现测量可溯源程序', 'REC-007-04', '3年'),
            // PROC-009 合同评审（补充缺项）
            self::gapEntry('XZTC/BG-09-05', '口头委托确认记录', '合同评审程序', 'REC-009-03', '检测报告保存期+1年'),
            // PROC-010 投诉（补充缺项）
            self::gapEntry('XZTC/BG-14-03', '投诉调查记录', '处理投诉程序', 'REC-010-02', '6年'),
            self::gapEntry('XZTC/BG-14-04', '投诉年度汇总分析', '处理投诉程序', 'REC-010-04', '长期'),
            // PROC-012 纠正措施（补充缺项）
            self::gapEntry('XZTC/BG-16-02', '纠正措施要求表', '实施纠正措施程序', 'REC-012-01', '6年'),
            self::gapEntry('XZTC/BG-16-03', '纠正措施验证记录', '实施纠正措施程序', 'REC-012-04', '6年'),
            // PROC-013 预防措施（补充缺项）
            self::gapEntry('XZTC/BG-17-02', '预防措施需求评估表', '实施预防措施程序', 'REC-013-01', '3年'),
            // PROC-014 记录控制（补充缺项）
            self::gapEntry('XZTC/BG-19-05', '记录销毁清单', '记录控制程序', 'REC-014-03', '长期'),
            // PROC-015 内审（补充缺项）
            self::gapEntry('XZTC/BG-20-09', '不符合项报告', '内部管理体系审核程序', 'REC-015-04', '6年'),
            self::gapEntry('XZTC/BG-20-10', '不符合工作及纠正措施跟踪表', '内部管理体系审核程序', 'TB-06', '6年'),
            // PROC-016 管评（补充缺项）
            self::gapEntry('XZTC/BG-21-04', '管理评审通知', '管理评审程序', 'REC-016-02', '6年'),
            self::gapEntry('XZTC/BG-21-05', '管理评审输入资料', '管理评审程序', 'REC-016-02', '6年'),
            self::gapEntry('XZTC/BG-21-06', '改进措施跟踪表', '管理评审程序', 'REC-016-05', '6年'),
            // PROC-017 数据控制
            self::gapEntry('XZTC/BG-34-03', '数据备份记录', '计算机文件及数据控制程序', 'REC-017-01', '3年'),
            self::gapEntry('XZTC/BG-34-04', '数据恢复测试记录', '计算机文件及数据控制程序', 'REC-017-02', '3年'),
            self::gapEntry('XZTC/BG-34-05', '系统权限分配表', '计算机文件及数据控制程序', 'REC-017-03', '3年'),
            // PROC-018 方法选择
            self::gapEntry('XZTC/BG-24-03', '标准查新记录', '检测方法的选择与确认程序', 'REC-018-01', '3年'),
            self::gapEntry('XZTC/BG-24-04', '方法验证报告', '检测方法的选择与确认程序', 'REC-018-02', '长期'),
            self::gapEntry('XZTC/BG-24-05', '方法偏离审批表', '检测方法的选择与确认程序', 'REC-018-05', '3年'),
            // PROC-020 样品管理（补充缺项）
            self::gapEntry('XZTC/BG-28-03', '样品流转单', '样品处置和管理程序', 'REC-020-03', '10年'),
            self::gapEntry('XZTC/BG-28-04', '样品退还签收单', '样品处置和管理程序', 'REC-020-05', '10年'),
            self::gapEntry('XZTC/BG-28-05', '高价值样品登记表', '样品处置和管理程序', 'REC-020-A-01', '10年'),
            self::gapEntry('XZTC/BG-28-06', '高价值样品流转单', '样品处置和管理程序', 'REC-020-A-02', '10年'),
            // PROC-022 质控/能力验证（补充缺项）
            self::gapEntry('XZTC/BG-30-05', '留样再测记录', '检测结果质量控制及能力验证程序', 'REC-022-02', '3年'),
            self::gapEntry('XZTC/BG-30-06', '人员比对记录', '检测结果质量控制及能力验证程序', 'REC-022-03', '3年'),
            self::gapEntry('XZTC/BG-30-07', '设备比对记录', '检测结果质量控制及能力验证程序', 'REC-022-04', '3年'),
            self::gapEntry('XZTC/BG-30-08', '质量控制图', '检测结果质量控制及能力验证程序', 'REC-022-07', '3年'),
            // PROC-023 报告（补充缺项）
            self::gapEntry('XZTC/BG-29-04', '报告审核/批准记录', '结果报告管理程序', 'REC-023-02', '10年'),
            self::gapEntry('XZTC/BG-29-05', '报告修改申请/通知', '结果报告管理程序', 'REC-023-03', '10年'),
            // PROC-024 能力验证
            self::gapEntry('XZTC/BG-30-09', '年度能力验证计划', '检测结果质量控制及能力验证程序', 'REC-024-01', '3年'),
            self::gapEntry('XZTC/BG-30-10', '能力验证参加记录', '检测结果质量控制及能力验证程序', 'REC-024-02', '6年'),
            self::gapEntry('XZTC/BG-30-11', '能力验证结果报告', '检测结果质量控制及能力验证程序', 'REC-024-03', '6年'),
            self::gapEntry('XZTC/BG-30-12', '比对结果不满意处理记录', '检测结果质量控制及能力验证程序', 'REC-024-05', '6年'),
            // PROC-025 标准物质（补充缺项）
            self::gapEntry('XZTC/BG-35-04', '标准物质验收记录', '标准物质管理程序', 'REC-025-02', '标准物质有效期+3年'),
        ];
    }

    public static function seedGapTemplates(): array
    {
        $summary = ['total' => 0, 'created' => 0, 'skipped' => 0, 'errors' => []];

        $registry = RecordFormSchemaRebuilder::loadRegistry();

        foreach (self::gapTemplates() as $entry) {
            $summary['total']++;

            $existing = RecordFormTemplate::where('doc_number', $entry['doc_number'])
                ->where('soft_delete', 0)
                ->find();
            if ($existing) {
                $summary['skipped']++;
                continue;
            }

            $schema = $registry[$entry['doc_number']]['field_schema']
                ?? self::schemaFor($entry['doc_number'], $entry['name'], $entry['module'], '', $entry['reference']);

            try {
                RecordFormTemplate::create([
                    'id' => qms_uuid(),
                    'doc_number' => $entry['doc_number'],
                    'name' => $entry['name'],
                    'module' => $entry['module'],
                    'version' => 'A/0',
                    'print_template_key' => $entry['print_template_key'],
                    'field_schema' => RecordFormSchemaService::encode($schema),
                    'status' => 'draft',
                    'review_status' => 'needs_fidelity',
                    'review_note' => '按 CNAS/Kimi 参考清单补齐（' . $entry['reference'] . '），待人工复核。保存期限：' . $entry['retention'],
                    'publish' => 1,
                    'soft_delete' => 0,
                ]);
                $summary['created']++;
            } catch (\Throwable $e) {
                $summary['errors'][] = $entry['doc_number'] . '：' . $e->getMessage();
            }
        }

        return $summary;
    }

    private static function gapEntry(
        string $docNumber,
        string $name,
        string $module,
        string $reference,
        string $retention
    ): array {
        $printKey = 'rf_' . strtolower(str_replace(['/', '-'], '_', $docNumber)) . '_gap';
        return [
            'doc_number' => $docNumber,
            'name' => $name,
            'module' => $module,
            'reference' => $reference,
            'retention' => $retention,
            'print_template_key' => $printKey,
        ];
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

    private static function isFillableSourceBackedTemplate(
        array $row,
        array $schema,
        string $printKey,
        string $sourceFileSha1,
        string $sourceAbsolutePath
    ): bool {
        $docNumber = (string)($row['doc_number'] ?? '');
        if ($docNumber === '' || str_starts_with($docNumber, '待定-')) {
            return false;
        }
        if ($schema === [] || $sourceFileSha1 === '' || !is_file($sourceAbsolutePath)) {
            return false;
        }
        if ($printKey === '' || $printKey === 'generic_record_form') {
            return false;
        }
        if (preg_match('/\A[a-zA-Z0-9_-]+\z/', $printKey) !== 1) {
            return false;
        }

        return is_file(root_path() . 'app' . DIRECTORY_SEPARATOR . 'record_form_print' . DIRECTORY_SEPARATOR . $printKey . '.php');
    }

    private static function isArchiveOnlySourceBackedTemplate(array $row): bool
    {
        $docNumber = (string)($row['doc_number'] ?? '');
        $originalDocNumber = (string)($row['_original_doc_number'] ?? '');
        $name = (string)($row['current_name'] ?? $row['name'] ?? '');
        $sourceFileName = basename((string)($row['source_file_path'] ?? $row['source_file_name'] ?? ''));

        return $docNumber === 'XZTC/BG-22-03'
            && ($originalDocNumber === '待定-22-01' || str_contains($sourceFileName, '待定-22-01') || str_contains($name, '现行有效标准清单'));
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
            '电子天平-TP02' => ['equipment_name' => '电子天平', 'model_spec' => 'TD30002', 'equipment_code' => 'XZTC-TP02', 'check_basis' => '电子天平期间核查作业指导书', 'check_method' => '使用E2等级砝码对天平进行线性检查和重复性检查', 'acceptance_criteria' => '线性偏差≤最大允许误差；重复性标准差≤最大允许误差的1/3'],
            '电子天平-TP03' => ['equipment_name' => '电子天平', 'model_spec' => 'BSM-320.3', 'equipment_code' => 'XZTC-TP03', 'check_basis' => '电子天平期间核查作业指导书', 'check_method' => '使用E2等级砝码对天平进行线性检查和重复性检查', 'acceptance_criteria' => '线性偏差≤最大允许误差；重复性标准差≤最大允许误差的1/3'],
            '电子天平-TP04' => ['equipment_name' => '电子天平', 'model_spec' => 'BSM-320.3', 'equipment_code' => 'XZTC-TP04', 'check_basis' => '电子天平期间核查作业指导书', 'check_method' => '使用E2等级砝码对天平进行线性检查和重复性检查', 'acceptance_criteria' => '线性偏差≤最大允许误差；重复性标准差≤最大允许误差的1/3'],
            '折射仪-ZSY01' => ['equipment_name' => '折射仪', 'model_spec' => 'FGR-002J', 'equipment_code' => 'XZTC-ZSY01', 'check_basis' => '折射仪作业指导书', 'check_method' => '使用已知折射率标准样品测试，读取仪器显示值与标准值比较', 'acceptance_criteria' => '示值误差≤±0.003'],
            '折射仪-ZSY02' => ['equipment_name' => '折射仪', 'model_spec' => 'GR-6', 'equipment_code' => 'XZTC-ZSY02', 'check_basis' => '折射仪作业指导书', 'check_method' => '使用已知折射率标准样品测试，读取仪器显示值与标准值比较', 'acceptance_criteria' => '示值误差≤±0.003'],
            '测金仪' => ['equipment_name' => 'X射线荧光光谱仪', 'model_spec' => 'XF-A5', 'equipment_code' => 'XZTC-CJY01', 'check_basis' => 'XRF期间核查作业指导书', 'check_method' => '使用已知含量的金标片进行测试，连续测量3次取平均值', 'acceptance_criteria' => '测量结果与标准值偏差≤允许偏差范围'],
            '红外光谱' => ['equipment_name' => '傅立叶红外光谱仪', 'model_spec' => 'NICOLET IS5', 'equipment_code' => 'XZTC-HW01', 'check_basis' => '红外光谱仪作业指导书', 'check_method' => '使用聚苯乙烯薄膜标准样品扫描，检查特征吸收峰波数', 'acceptance_criteria' => '3027.1cm⁻¹处吸收峰波数偏差≤±5cm⁻¹'],
            '紫外' => ['equipment_name' => '紫外-可见光纤光谱仪', 'model_spec' => 'UV-5000', 'equipment_code' => 'XZTC-ZW01', 'check_basis' => '紫外可见光谱仪作业指导书', 'check_method' => '使用标准样品检查波长准确度和吸光度准确度', 'acceptance_criteria' => '波长偏差≤±2nm'],
            '偏光镜' => ['equipment_name' => '偏光镜', 'model_spec' => 'FTP-LED', 'equipment_code' => 'XZTC-PGJ01', 'check_basis' => '偏光镜作业指导书', 'check_method' => '观察已知光性特征的标准样品，检查消光位和干涉图', 'acceptance_criteria' => '能正确区分均质体/非均质体'],
            '二色镜' => ['equipment_name' => '二色镜', 'model_spec' => 'FTD-1', 'equipment_code' => 'XZTC-ESJ01', 'check_basis' => '二色镜作业指导书', 'check_method' => '使用已知多色性宝石样品检查二色性显示', 'acceptance_criteria' => '能正确显示多色性特征'],
            '分光镜' => ['equipment_name' => '分光镜', 'model_spec' => 'FPS-3A', 'equipment_code' => 'XZTC-FGJ01', 'check_basis' => '分光镜作业指导书', 'check_method' => '使用已知吸收光谱特征的标准样品检查吸收线位置', 'acceptance_criteria' => '能正确识别特征吸收线'],
            '放大镜' => ['equipment_name' => '放大镜', 'model_spec' => 'FLP-1018', 'equipment_code' => 'XZTC-FDJ01', 'check_basis' => '放大镜作业指导书', 'check_method' => '观察标准分辨率板，检查分辨能力和视场清晰度', 'acceptance_criteria' => '视场清晰、无明显畸变'],
            '钻石分级灯' => ['equipment_name' => '钻石分级灯', 'model_spec' => 'FDL-25', 'equipment_code' => 'XZTC-ZSFJD01', 'check_basis' => '钻石分级灯作业指导书', 'check_method' => '使用标准比色石比对，检查光源色温和均匀性', 'acceptance_criteria' => '色温符合钻石分级要求(约6500K)'],
            '光纤灯' => ['equipment_name' => '光纤灯', 'model_spec' => 'FDL-150A', 'equipment_code' => 'XZTC-GQD01', 'check_basis' => '光纤灯作业指导书', 'check_method' => '检查光源亮度和光纤导光均匀性', 'acceptance_criteria' => '光源稳定、导光均匀'],
            '显微镜' => ['equipment_name' => '显微镜', 'model_spec' => 'FGM-R65141T', 'equipment_code' => 'XZTC-XWJ01', 'check_basis' => '显微镜作业指导书', 'check_method' => '使用标准测微尺检查放大倍率和分辨率', 'acceptance_criteria' => '放大倍率偏差≤±5%'],
            '金标片G05' => ['equipment_name' => '金标片', 'equipment_code' => 'ZHJ-G05', 'check_basis' => 'XRF期间核查作业指导书', 'check_method' => '使用XRF测试金含量，连续3次取平均', 'acceptance_criteria' => '测量值与标准值偏差在允许范围内'],
            '金标片G06' => ['equipment_name' => '金标片', 'equipment_code' => 'ZHJ-G06', 'check_basis' => 'XRF期间核查作业指导书', 'check_method' => '使用XRF测试金含量，连续3次取平均', 'acceptance_criteria' => '测量值与标准值偏差在允许范围内'],
            '金标片G08' => ['equipment_name' => '金标片', 'equipment_code' => 'ZHJ-G08', 'check_basis' => 'XRF期间核查作业指导书', 'check_method' => '使用XRF测试金含量，连续3次取平均', 'acceptance_criteria' => '测量值与标准值偏差在允许范围内'],
            '金标片G09' => ['equipment_name' => '金标片', 'equipment_code' => 'ZHJ-G09', 'check_basis' => 'XRF期间核查作业指导书', 'check_method' => '使用XRF测试金含量，连续3次取平均', 'acceptance_criteria' => '测量值与标准值偏差在允许范围内'],
            '金标片G10' => ['equipment_name' => '金标片', 'equipment_code' => 'ZHJ-G10', 'check_basis' => 'XRF期间核查作业指导书', 'check_method' => '使用XRF测试金含量，连续3次取平均', 'acceptance_criteria' => '测量值与标准值偏差在允许范围内'],
            '银标片G04' => ['equipment_name' => '银标片', 'equipment_code' => 'ZHJ-G04', 'check_basis' => 'XRF期间核查作业指导书', 'check_method' => '使用XRF测试银含量，连续3次取平均', 'acceptance_criteria' => '测量值与标准值偏差在允许范围内'],
            '银标片G18' => ['equipment_name' => '银标片', 'equipment_code' => 'ZHJ-G18', 'check_basis' => 'XRF期间核查作业指导书', 'check_method' => '使用XRF测试银含量，连续3次取平均', 'acceptance_criteria' => '测量值与标准值偏差在允许范围内'],
            '合成红宝石标样' => ['equipment_name' => '合成红宝石标样', 'check_basis' => '标准物质期间核查作业指导书', 'check_method' => '使用折射仪测试折射率，分光镜检查吸收光谱', 'acceptance_criteria' => '折射率和光谱特征与标准值一致'],
            '尖晶石标样' => ['equipment_name' => '尖晶石标样', 'check_basis' => '标准物质期间核查作业指导书', 'check_method' => '使用折射仪测试折射率，偏光镜检查光性特征', 'acceptance_criteria' => '折射率和光性特征与标准值一致'],
            '锆石标样' => ['equipment_name' => '锆石标样', 'check_basis' => '标准物质期间核查作业指导书', 'check_method' => '使用折射仪测试折射率，偏光镜检查双折射', 'acceptance_criteria' => '折射率和双折射值与标准值一致'],
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

    private static function withReadonly(array $field, array $defaults, string $key): array
    {
        if (($defaults[$key] ?? '') !== '') {
            $field['default'] = $defaults[$key];
            $field['readonly'] = true;
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
            self::withReadonly(['key' => 'equipment_name', 'label' => '名称', 'type' => 'text', 'required' => true], $defaults, 'equipment_name'),
            self::withReadonly(['key' => 'model_spec', 'label' => '型号规格', 'type' => 'text', 'required' => false], $defaults, 'model_spec'),
            self::withReadonly(['key' => 'equipment_code', 'label' => '编号', 'type' => 'text', 'required' => false], $defaults, 'equipment_code'),
            self::withReadonly(['key' => 'check_basis', 'label' => '核查依据', 'type' => 'textarea', 'required' => false], $defaults, 'check_basis'),
            self::withReadonly(['key' => 'check_method', 'label' => '核查方法', 'type' => 'textarea', 'required' => false, 'help_text' => '来源于作业指导书规定的核查操作步骤'], $defaults, 'check_method'),
            self::withReadonly(['key' => 'acceptance_criteria', 'label' => '判定标准', 'type' => 'textarea', 'required' => false, 'help_text' => '来源于作业指导书规定的允许偏差/合格限'], $defaults, 'acceptance_criteria'),
            ['key' => 'check_resources', 'label' => '核查所用仪器设备或标准物质', 'type' => 'textarea', 'required' => false],
            ['key' => 'check_personnel', 'label' => '核查人员', 'type' => 'person', 'required' => false],
            ['key' => 'measurement_data', 'label' => '测量数据', 'type' => 'repeatable_table', 'columns' => [
                ['key' => 'item', 'label' => '核查项目', 'type' => 'text', 'required' => true],
                ['key' => 'standard_value', 'label' => '标准值/参考值', 'type' => 'text', 'required' => false],
                ['key' => 'measured_value', 'label' => '实测值', 'type' => 'text', 'required' => true],
                ['key' => 'deviation', 'label' => '偏差', 'type' => 'text', 'required' => false],
                ['key' => 'judgement', 'label' => '判定', 'type' => 'select', 'required' => false, 'options' => ['合格', '不合格']],
            ]],
            ['key' => 'process_record', 'label' => '核查过程记录', 'type' => 'textarea', 'required' => false],
            ['key' => 'recorder', 'label' => '记录人（设备管理员）', 'type' => 'person', 'required' => false],
            ['key' => 'record_date', 'label' => '记录日期', 'type' => 'date', 'required' => false],
            ['key' => 'result_judgement', 'label' => '核查结果判定', 'type' => 'select', 'required' => false, 'options' => ['合格', '不合格', '有条件使用']],
            ['key' => 'checkers', 'label' => '核查人员签名', 'type' => 'signature', 'required' => false],
            ['key' => 'check_date', 'label' => '核查日期', 'type' => 'date', 'required' => false],
            ['key' => 'reviewer_opinion', 'label' => '审核人意见', 'type' => 'textarea', 'required' => false],
            ['key' => 'reviewer', 'label' => '审核人', 'type' => 'signature', 'required' => false],
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
            self::withReadonly(['key' => 'equipment_name', 'label' => '名称', 'type' => 'text', 'required' => true], $defaults, 'equipment_name'),
            self::withReadonly(['key' => 'model_spec', 'label' => '型号规格', 'type' => 'text', 'required' => false], $defaults, 'model_spec'),
            self::withReadonly(['key' => 'equipment_code', 'label' => '编号', 'type' => 'text', 'required' => false], $defaults, 'equipment_code'),
            self::withReadonly(['key' => 'check_basis', 'label' => '核查依据', 'type' => 'textarea', 'required' => false], $defaults, 'check_basis'),
            ['key' => 'check_items', 'label' => '核查项目', 'type' => 'textarea', 'required' => false],
            ['key' => 'check_personnel', 'label' => '核查人员', 'type' => 'person', 'required' => false],
            self::withReadonly(['key' => 'check_standard', 'label' => '核查标准/判定标准', 'type' => 'textarea', 'required' => false], $defaults, 'acceptance_criteria'),
            ['key' => 'result_judgement', 'label' => '核查结果判定', 'type' => 'select', 'required' => false, 'options' => ['合格', '不合格', '有条件使用']],
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

    private static ?array $registryCache = null;

    private static function registryLookup(string $docNumber, string $sourceFileName): ?array
    {
        if (self::$registryCache === null) {
            self::$registryCache = RecordFormSchemaRebuilder::loadRegistry();
        }

        $stem = pathinfo($sourceFileName, PATHINFO_FILENAME);
        $compositeKey = $docNumber . '::' . $stem;
        if (isset(self::$registryCache[$compositeKey]['field_schema'])) {
            return self::$registryCache[$compositeKey]['field_schema'];
        }

        if (isset(self::$registryCache[$docNumber]['field_schema'])) {
            return self::$registryCache[$docNumber]['field_schema'];
        }

        return null;
    }

    private static function hasRegistrySchema(string $docNumber, string $sourceFileName = ''): bool
    {
        return self::registryLookup($docNumber, $sourceFileName) !== null;
    }

    private static function schemaFromRegistryOrHeuristic(string $docNumber, string $name, string $module, string $matchConclusion, string $suggestion, string $sourceFileName = ''): array
    {
        $fromRegistry = self::registryLookup($docNumber, $sourceFileName);
        if ($fromRegistry !== null) {
            return $fromRegistry;
        }

        return self::schemaFor($docNumber, $name, $module, $matchConclusion, $suggestion);
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
        if (($docNumber === '待定-20-04' || $docNumber === 'XZTC/BG-20-10') && str_contains($name, '内部审核资料封皮目录')) {
            return self::internalAuditArchiveCatalogSchema();
        }
        if ($docNumber === 'XZTC/BG-20-09' && str_contains($name, '不符合项')) {
            return self::internalAuditNonconformitySummarySchema();
        }
        if ($docNumber === 'XZTC/BG-24-03' && str_contains($name, '查新')) {
            return self::standardFreshnessReportSchema();
        }
        if ($docNumber === 'XZTC/BG-04-07' && str_contains($name, '红外')) {
            return self::irPerformanceConfirmationSchema();
        }
        if ($docNumber === 'XZTC/BG-13-01' && str_contains($name, '会议')) {
            return self::internalCommunicationMeetingSchema();
        }
        if ($docNumber === 'XZTC/BG-01-10' && str_contains($name, '保密协议')) {
            return self::confidentialityAgreementSchema();
        }
        if ($docNumber === 'XZTC/BG-01-11' && str_contains($name, '劳动合同')) {
            return self::laborContractSchema();
        }
        if ($docNumber === 'XZTC/BG-33-01' && str_contains($name, '安全检查')) {
            return self::safetyCheckRecordSchema();
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
            ['key' => 'archived_by', 'label' => '归档责任人', 'type' => 'person', 'required' => false],
            ['key' => 'archive_date', 'label' => '资料日期', 'type' => 'date', 'required' => false],
            ['key' => 'catalog_items', 'label' => '内部审核资料目录', 'type' => 'repeatable_table', 'columns' => [
                ['key' => 'sequence', 'label' => '序号', 'type' => 'number', 'required' => false],
                ['key' => 'document_name', 'label' => '资料名称', 'type' => 'text', 'required' => true],
                ['key' => 'included', 'label' => '是否归档', 'type' => 'checkbox', 'required' => false],
                ['key' => 'remarks', 'label' => '备注', 'type' => 'text', 'required' => false],
            ]],
        ];
    }

    private static function internalAuditNonconformitySummarySchema(): array
    {
        return [
            ['key' => 'audit_year', 'label' => '内部审核年度', 'type' => 'text', 'required' => true],
            ['key' => 'audit_date', 'label' => '审核日期', 'type' => 'date', 'required' => false],
            ['key' => 'nonconformity_items', 'label' => '不符合项汇总', 'type' => 'repeatable_table', 'columns' => [
                ['key' => 'sequence', 'label' => '序号', 'type' => 'number', 'required' => false],
                ['key' => 'clause_or_requirement', 'label' => '依据条款/要求', 'type' => 'text', 'required' => true],
                ['key' => 'nonconformity_fact', 'label' => '不符合事实', 'type' => 'textarea', 'required' => true],
                ['key' => 'responsible_department', 'label' => '责任部门', 'type' => 'department', 'required' => false],
                ['key' => 'corrective_action_no', 'label' => '纠正措施记录编号', 'type' => 'text', 'required' => false],
                ['key' => 'verification_result', 'label' => '验证/关闭情况', 'type' => 'select', 'options' => ['未启动', '整改中', '已验证关闭', '需继续跟踪'], 'required' => false],
                ['key' => 'closed_date', 'label' => '关闭日期', 'type' => 'date', 'required' => false],
            ]],
            ['key' => 'audit_team_leader', 'label' => '内审组长', 'type' => 'person', 'required' => false],
            ['key' => 'summary_date', 'label' => '汇总日期', 'type' => 'date', 'required' => false],
        ];
    }

    private static function standardFreshnessReportSchema(): array
    {
        return [
            ['key' => 'check_trigger', 'label' => '查新触发', 'type' => 'select', 'options' => ['开展新项目', '采用新标准', '在用标准定期检查', '标准更新复核', '其他'], 'required' => true],
            ['key' => 'check_date', 'label' => '查新日期', 'type' => 'date', 'required' => true],
            ['key' => 'checker', 'label' => '查新人', 'type' => 'person', 'required' => false],
            ['key' => 'source_channel', 'label' => '查新来源/渠道', 'type' => 'textarea', 'required' => true, 'help_text' => '外部标准来源和有效性状态应同步到 qms_sources 或外部文件台账。'],
            ['key' => 'standards', 'label' => '标准查新明细', 'type' => 'repeatable_table', 'required' => true, 'columns' => [
                ['key' => 'sequence', 'label' => '序号', 'type' => 'number', 'required' => false],
                ['key' => 'standard_code', 'label' => '标准号', 'type' => 'text', 'required' => true],
                ['key' => 'standard_name', 'label' => '标准名称', 'type' => 'text', 'required' => true],
                ['key' => 'standard_status', 'label' => '标准状态', 'type' => 'select', 'options' => ['现行有效', '即将实施', '已废止', '被替代', '需确认'], 'required' => true],
                ['key' => 'replacement_standard', 'label' => '替代标准', 'type' => 'text', 'required' => false],
                ['key' => 'effective_date', 'label' => '新标准执行日期', 'type' => 'date', 'required' => false],
                ['key' => 'action_required', 'label' => '处置要求', 'type' => 'textarea', 'required' => false],
            ]],
            ['key' => 'overall_conclusion', 'label' => '查新结论', 'type' => 'textarea', 'required' => true],
            ['key' => 'technical_reviewer', 'label' => '技术负责人复核', 'type' => 'person', 'required' => false],
            ['key' => 'review_date', 'label' => '复核日期', 'type' => 'date', 'required' => false],
        ];
    }

    private static function irPerformanceConfirmationSchema(): array
    {
        return [
            ['key' => 'equipment_name', 'label' => '仪器名称', 'type' => 'text', 'required' => true, 'default' => '傅立叶红外光谱仪', 'readonly' => true],
            ['key' => 'equipment_code', 'label' => '仪器编号', 'type' => 'text', 'required' => false, 'default' => 'XZTC-HW01', 'readonly' => true],
            ['key' => 'serial_number', 'label' => '序列号', 'type' => 'text', 'required' => false],
            ['key' => 'confirmation_date', 'label' => '确认日期', 'type' => 'date', 'required' => true],
            ['key' => 'operator', 'label' => '操作者', 'type' => 'person', 'required' => false],
            ['key' => 'performance_items', 'label' => '性能确认项目', 'type' => 'repeatable_table', 'required' => true, 'columns' => [
                ['key' => 'test_description', 'label' => '测试描述', 'type' => 'text', 'required' => true],
                ['key' => 'high_limit', 'label' => '高限制', 'type' => 'text', 'required' => false],
                ['key' => 'low_limit', 'label' => '低限制', 'type' => 'text', 'required' => false],
                ['key' => 'measured_value', 'label' => '测得值', 'type' => 'text', 'required' => true],
                ['key' => 'result', 'label' => '结果', 'type' => 'select', 'options' => ['通过', '不通过', '不适用'], 'required' => true],
            ]],
            ['key' => 'overall_result', 'label' => '总体判定', 'type' => 'select', 'options' => ['通过', '不通过', '需复查'], 'required' => true],
            ['key' => 'approved_by', 'label' => '批准人', 'type' => 'person', 'required' => false],
            ['key' => 'approved_date', 'label' => '批准日期', 'type' => 'date', 'required' => false],
            ['key' => 'comments', 'label' => '评论', 'type' => 'textarea', 'required' => false],
        ];
    }

    private static function internalCommunicationMeetingSchema(): array
    {
        return [
            ['key' => 'meeting_date', 'label' => '会议日期', 'type' => 'date', 'required' => true],
            ['key' => 'meeting_place', 'label' => '会议地点', 'type' => 'text', 'required' => false],
            ['key' => 'host', 'label' => '主持人', 'type' => 'person', 'required' => false],
            ['key' => 'participants', 'label' => '参加人员', 'type' => 'textarea', 'required' => false],
            ['key' => 'topics', 'label' => '沟通议题与决议', 'type' => 'repeatable_table', 'columns' => [
                ['key' => 'topic', 'label' => '议题', 'type' => 'text', 'required' => true],
                ['key' => 'discussion', 'label' => '沟通内容', 'type' => 'textarea', 'required' => false],
                ['key' => 'decision', 'label' => '决议/措施', 'type' => 'textarea', 'required' => false],
                ['key' => 'owner', 'label' => '责任人', 'type' => 'person', 'required' => false],
                ['key' => 'due_date', 'label' => '完成期限', 'type' => 'date', 'required' => false],
            ]],
            ['key' => 'follow_up_result', 'label' => '跟踪结果', 'type' => 'textarea', 'required' => false],
            ['key' => 'recorded_by', 'label' => '记录人', 'type' => 'person', 'required' => false],
        ];
    }

    private static function confidentialityAgreementSchema(): array
    {
        return [
            ['key' => 'party_name', 'label' => '承诺/签约人员', 'type' => 'person', 'required' => true],
            ['key' => 'department_or_role', 'label' => '部门/岗位', 'type' => 'text', 'required' => false],
            ['key' => 'confidential_scope', 'label' => '保密范围', 'type' => 'textarea', 'required' => true],
            ['key' => 'confidential_period', 'label' => '保密期限', 'type' => 'text', 'required' => false],
            ['key' => 'responsibilities', 'label' => '责任与违约处理', 'type' => 'textarea', 'required' => false],
            ['key' => 'signatory', 'label' => '签署人', 'type' => 'person', 'required' => true],
            ['key' => 'signed_date', 'label' => '签署日期', 'type' => 'date', 'required' => true],
            ['key' => 'archive_owner', 'label' => '归档责任人', 'type' => 'person', 'required' => false],
        ];
    }

    private static function laborContractSchema(): array
    {
        return [
            ['key' => 'employee_name', 'label' => '员工姓名', 'type' => 'person', 'required' => true],
            ['key' => 'position', 'label' => '岗位/职务', 'type' => 'text', 'required' => true],
            ['key' => 'contract_type', 'label' => '合同类型', 'type' => 'select', 'options' => ['固定期限', '无固定期限', '以完成一定工作任务为期限', '其他'], 'required' => false],
            ['key' => 'start_date', 'label' => '合同起始日期', 'type' => 'date', 'required' => true],
            ['key' => 'end_date', 'label' => '合同终止日期', 'type' => 'date', 'required' => false],
            ['key' => 'work_duties', 'label' => '工作职责', 'type' => 'textarea', 'required' => false],
            ['key' => 'employee_signature', 'label' => '员工签署', 'type' => 'person', 'required' => false],
            ['key' => 'lab_director_signature', 'label' => '实验室主任/授权代表签署', 'type' => 'person', 'required' => false],
            ['key' => 'signed_date', 'label' => '签署日期', 'type' => 'date', 'required' => true],
            ['key' => 'archive_owner', 'label' => '归档责任人', 'type' => 'person', 'required' => false],
        ];
    }

    private static function safetyCheckRecordSchema(): array
    {
        return [
            ['key' => 'check_date', 'label' => '检查日期', 'type' => 'date', 'required' => true],
            ['key' => 'check_area', 'label' => '检查区域', 'type' => 'text', 'required' => true],
            ['key' => 'checked_by', 'label' => '检查人', 'type' => 'person', 'required' => false],
            ['key' => 'check_items', 'label' => '安全检查项目', 'type' => 'repeatable_table', 'required' => true, 'columns' => [
                ['key' => 'item', 'label' => '检查项目', 'type' => 'text', 'required' => true],
                ['key' => 'result', 'label' => '检查结果', 'type' => 'select', 'options' => ['符合', '不符合', '不适用'], 'required' => true],
                ['key' => 'problem', 'label' => '问题描述', 'type' => 'textarea', 'required' => false],
                ['key' => 'responsible_person', 'label' => '责任人', 'type' => 'person', 'required' => false],
                ['key' => 'due_date', 'label' => '整改期限', 'type' => 'date', 'required' => false],
                ['key' => 'verification', 'label' => '验证结果', 'type' => 'textarea', 'required' => false],
            ]],
            ['key' => 'overall_conclusion', 'label' => '总体结论', 'type' => 'select', 'options' => ['符合要求', '存在问题已跟踪', '需立即整改'], 'required' => false],
            ['key' => 'reviewed_by', 'label' => '复核人', 'type' => 'person', 'required' => false],
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

    private static function retireSupersededForwardChainTemplates(array $manifest): int
    {
        $count = 0;
        $retiredIds = [];
        foreach ($manifest as $entry) {
            $candidates = [];
            $originalDocNumber = (string)($entry['original_doc_number'] ?? '');
            $sourceFileSha1 = (string)($entry['source_file_sha1'] ?? '');

            if ($originalDocNumber !== '' && $originalDocNumber !== (string)$entry['doc_number']) {
                $query = RecordFormTemplate::where('soft_delete', 0)
                    ->where('doc_number', $originalDocNumber);
                if ($sourceFileSha1 !== '') {
                    $query->where('source_file_sha1', $sourceFileSha1);
                } else {
                    $query->where('source_file_name', $entry['source_file_name']);
                }
                foreach ($query->select() as $record) {
                    $candidates[(string)$record->id] = $record;
                }
            }

            if ($sourceFileSha1 !== '') {
                $query = RecordFormTemplate::where('soft_delete', 0)
                    ->where('source_file_sha1', $sourceFileSha1)
                    ->where('doc_number', '<>', (string)$entry['doc_number']);
                foreach ($query->select() as $record) {
                    $candidates[(string)$record->id] = $record;
                }
            }

            foreach ($candidates as $recordId => $record) {
                if (isset($retiredIds[$recordId])) {
                    continue;
                }
                $record->save([
                    'status' => 'obsolete',
                    'review_status' => 'deferred',
                    'review_note' => '已由前推编号 ' . (string)$entry['doc_number'] . ' 替代，旧待定模板作废。',
                    'soft_delete' => 1,
                ]);
                $retiredIds[$recordId] = true;
                $count++;
            }
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
