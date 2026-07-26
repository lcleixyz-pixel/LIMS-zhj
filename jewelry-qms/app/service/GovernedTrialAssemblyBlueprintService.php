<?php
declare(strict_types=1);

namespace app\service;

use RuntimeException;

/**
 * 把已签认治理材料归一为可装配的试运行蓝图。
 *
 * 本服务只读文件、不写数据库；正式写入由 GovernedTrialAssemblyService
 * 在 QMS_TRIAL_MODE=true 的隔离环境内执行。
 */
final class GovernedTrialAssemblyBlueprintService
{
    public const TRIAL_BATCH = 'GOV-TRIAL-20260724';
    public const VERSION = 'GOV-TRIAL/0.1';
    public const APPLICABLE_SITES = '乌鲁木齐实验室；和田实验室';
    public const RETENTION_PERIOD = '不少于6年';

    private const BASE_DIR = '.team/交接箱/2026-07-07-第五版候选修订准备';
    private const PREIMPORT_DIR = self::BASE_DIR . '/lims_preimport_package';
    private const RETAINED_RECORD_NUMBERS = [
        'XZTC/BG-03-01',
        'XZTC/BG-03-04',
        'XZTC/BG-03-05',
        'XZTC/BG-03-06',
        'XZTC/BG-03-07',
        'XZTC/BG-03-08',
        'XZTC/BG-08-02',
        'XZTC/BG-09-03',
    ];

    private const MODULE_PROCEDURE_MAP = [
        '人员培训程序' => 'XZTC/CX-01-2022',
        '人员管理程序' => 'XZTC/CX-01-02-2022',
        '人员监督程序' => 'XZTC/CX-31-2022',
        '设施与环境条件控制和维护程序' => 'XZTC/CX-02-2022',
        '设施和环境条件控制程序' => 'XZTC/CX-02-2022',
        '仪器设备管理程序' => 'XZTC/CX-03-2022',
        '设备管理程序' => 'XZTC/CX-03-2022',
        '标准物质管理程序' => 'XZTC/CX-03-02-2022',
        '仪器设备和标准物质期间核查程序' => 'XZTC/CX-04-2022',
        '文件控制程序' => 'XZTC/CX-08-2022',
        '合同评审程序' => 'XZTC/CX-09-2022',
        '采购服务控制程序' => 'XZTC/CX-11-2022',
        '服务客户程序' => 'XZTC/CX-12-2022',
        '沟通程序' => 'XZTC/CX-13-2022',
        '内部沟通程序' => 'XZTC/CX-13-2022',
        '投诉处理程序' => 'XZTC/CX-14-2022',
        '不符合工作控制程序' => 'XZTC/CX-15-2022',
        '纠正措施程序' => 'XZTC/CX-16-2022',
        '质量目标管理程序' => 'XZTC/CX-18-2022',
        '记录控制程序' => 'XZTC/CX-19-2022',
        '内部审核程序' => 'XZTC/CX-20-2022',
        '管理评审程序' => 'XZTC/CX-21-2022',
        '方法确认与验证程序' => 'XZTC/CX-22-2022',
        '新项目评审程序' => 'XZTC/CX-24-2022',
        '检测工作控制程序' => 'XZTC/CX-25-2022',
        'CX-25 检测工作控制程序' => 'XZTC/CX-25-2022',
        '数据控制程序' => 'XZTC/CX-26-2022',
        '样品处置和管理程序' => 'XZTC/CX-28-2022',
        '结果报告程序' => 'XZTC/CX-29-2022',
        '检测结果质量保证程序' => 'XZTC/CX-30-2022',
        '风险和机遇控制程序' => 'XZTC/CX-32-2022',
        '安全管理程序' => 'XZTC/CX-33-2022',
        '印章管理程序' => 'XZTC/CX-08-2022',
        '标识管理程序' => 'XZTC/CX-25-2022',
        '试剂耗材管理程序' => 'XZTC/CX-11-2022',
        '保密和公正性程序' => 'XZTC/CX-06-2022',
    ];

    private const RESPONSIBLE_POSITIONS = [
        '01' => 'personnel_manager',
        '01-02' => 'laboratory_director',
        '02' => 'technical_manager',
        '03' => 'equipment_manager',
        '03-02' => 'equipment_manager',
        '04' => 'equipment_manager',
        '05' => 'technical_manager',
        '06' => 'quality_manager',
        '07' => 'laboratory_director',
        '07-02' => 'laboratory_director',
        '08' => 'document_controller',
        '09' => 'quality_manager',
        '11' => 'quality_manager',
        '12' => 'quality_manager',
        '13' => 'quality_manager',
        '14' => 'quality_manager',
        '15' => 'quality_manager',
        '16' => 'quality_manager',
        '17' => 'quality_manager',
        '18' => 'quality_manager',
        '19' => 'document_controller',
        '20' => 'quality_manager',
        '21' => 'laboratory_director',
        '22' => 'technical_manager',
        '23' => 'technical_manager',
        '24' => 'technical_manager',
        '25' => 'technical_manager',
        '26' => 'system_administrator',
        '27' => 'technical_manager',
        '28' => 'sample_manager',
        '29' => 'authorized_signatory',
        '30' => 'technical_manager',
        '31' => 'technical_manager',
        '32' => 'quality_manager',
        '33' => 'laboratory_director',
        '34' => 'quality_manager',
        '35' => 'technical_manager',
    ];

    public static function build(): array
    {
        $sources = self::baseSources();
        $manualSections = self::manualSections();
        $procedures = self::procedures($sources);
        $recordTemplates = self::recordTemplates($sources, $procedures, $manualSections);

        $templatesByProcedure = [];
        $templatesBySection = [];
        foreach ($recordTemplates as $template) {
            $canonical = (string)$template['canonical_doc_number'];
            $templatesByProcedure[(string)$template['procedure_doc_number']][] = $canonical;
            $templatesBySection[(string)$template['manual_section_key']][] = $canonical;
        }

        $manualKeysByProcedure = [];
        foreach ($manualSections as &$section) {
            $sectionTemplates = array_values(array_unique($templatesBySection[(string)$section['section_key']] ?? []));
            sort($sectionTemplates);
            $section['record_templates'] = $sectionTemplates;
            foreach ($section['procedure_doc_numbers'] as $procedureNumber) {
                $manualKeysByProcedure[$procedureNumber][] = (string)$section['section_key'];
            }
        }
        unset($section);

        foreach ($procedures as &$procedure) {
            $procedureNumber = (string)$procedure['doc_number'];
            $manualKeys = array_values(array_unique($manualKeysByProcedure[$procedureNumber] ?? []));
            sort($manualKeys);
            $directTemplates = array_values(array_unique($templatesByProcedure[$procedureNumber] ?? []));
            sort($directTemplates);
            $supportingTemplates = $directTemplates;
            foreach ($manualKeys as $manualKey) {
                $supportingTemplates = array_merge($supportingTemplates, $templatesBySection[$manualKey] ?? []);
            }
            $supportingTemplates = array_values(array_unique($supportingTemplates));
            sort($supportingTemplates);
            $procedure['manual_sections'] = $manualKeys;
            $procedure['direct_record_templates'] = $directTemplates;
            $procedure['record_templates'] = $supportingTemplates;
        }
        unset($procedure);

        $blueprint = [
            'trial_batch' => self::TRIAL_BATCH,
            'version' => self::VERSION,
            'status' => 'governance_trial_candidate',
            'formal_system_notice' => '纸质体系仍为唯一正式体系；本批次仅供隔离环境治理试运行与迭代。',
            'counting_note' => '电子运行母版按唯一编号归一为104份；物理文件、重复件和一表多联不再作为电子母版数量。',
            'sources' => array_values($sources),
            'manual_sections' => $manualSections,
            'procedures' => $procedures,
            'record_templates' => $recordTemplates,
            'obsolete_record_templates' => ['XZTC/BG-10-01', 'XZTC/BG-10-02'],
        ];
        $blueprint['validation'] = self::validate($blueprint);

        return $blueprint;
    }

    public static function validate(array $blueprint): array
    {
        $errors = [];
        $templates = $blueprint['record_templates'] ?? [];
        $procedures = $blueprint['procedures'] ?? [];
        $sections = $blueprint['manual_sections'] ?? [];
        $sources = $blueprint['sources'] ?? [];
        $sourceKeys = array_fill_keys(array_map(static fn(array $source): string => (string)($source['source_key'] ?? ''), $sources), true);

        if (count($templates) !== 104) {
            $errors[] = '活动记录电子母版数量不是104';
        }
        if (count($procedures) !== 37) {
            $errors[] = '程序文件数量不是37';
        }
        if (count($sections) !== 29) {
            $errors[] = '手册结构块数量不是29';
        }

        $canonicalNumbers = [];
        foreach ($templates as $template) {
            $canonical = (string)($template['canonical_doc_number'] ?? '');
            $canonicalNumbers[] = $canonical;
            foreach ([
                'doc_number',
                'name',
                'module',
                'procedure_doc_number',
                'manual_section_key',
                'applicable_sites',
                'responsible_position_code',
                'retention_period',
                'print_template_key',
            ] as $field) {
                if (trim((string)($template[$field] ?? '')) === '') {
                    $errors[] = $canonical . ' 缺少字段 ' . $field;
                }
            }
            if (($template['field_schema'] ?? []) === []) {
                $errors[] = $canonical . ' 缺少字段结构';
            }
            foreach ($template['source_evidence'] ?? [] as $sourceKey) {
                if (!isset($sourceKeys[(string)$sourceKey])) {
                    $errors[] = $canonical . ' 引用了不存在的来源 ' . $sourceKey;
                }
            }
        }
        if (count(array_unique($canonicalNumbers)) !== count($canonicalNumbers)) {
            $errors[] = '活动记录电子母版编号重复';
        }
        foreach (['XZTC/BG-10-01', 'XZTC/BG-10-02'] as $obsolete) {
            if (in_array($obsolete, $canonicalNumbers, true)) {
                $errors[] = '已作废模板进入活动范围：' . $obsolete;
            }
        }

        foreach ($procedures as $procedure) {
            if (($procedure['manual_sections'] ?? []) === []) {
                $errors[] = ($procedure['doc_number'] ?? '程序') . ' 无手册落实章节';
            }
            if (($procedure['record_templates'] ?? []) === []) {
                $errors[] = ($procedure['doc_number'] ?? '程序') . ' 无运行证明模板';
            }
        }
        foreach ($sections as $section) {
            if (($section['external_sources'] ?? []) === []) {
                $errors[] = ($section['section_key'] ?? '手册章节') . ' 无外部依据';
            }
            if (($section['procedure_doc_numbers'] ?? []) === []) {
                $errors[] = ($section['section_key'] ?? '手册章节') . ' 无程序落实';
            }
        }
        foreach ($sources as $source) {
            $path = (string)($source['absolute_path'] ?? '');
            if (!is_file($path)) {
                $errors[] = ($source['source_key'] ?? '来源') . ' 原件不存在：' . $path;
                continue;
            }
            if (!preg_match('/^[a-f0-9]{64}$/', (string)($source['sha256'] ?? ''))) {
                $errors[] = ($source['source_key'] ?? '来源') . ' 缺少SHA-256';
            }
        }

        return [
            'ok' => $errors === [],
            'errors' => array_values(array_unique($errors)),
            'counts' => [
                'sources' => count($sources),
                'manual_sections' => count($sections),
                'procedures' => count($procedures),
                'record_templates' => count($templates),
            ],
        ];
    }

    private static function baseSources(): array
    {
        $sources = [];
        foreach (QmsPlanningImportService::officialSourceManifest() as $source) {
            self::registerSource($sources, [
                'source_key' => self::sourceKey((string)$source['source_code']),
                'source_code' => (string)$source['source_code'],
                'name' => (string)$source['name'],
                'source_type' => (string)$source['source_type'],
                'version' => (string)$source['version'],
                'relative_path' => (string)$source['relative_path'],
                'absolute_path' => (string)$source['absolute_path'],
                'relation_scope' => '外部依据基线',
            ]);
        }

        $governanceSources = [
            ['manual_candidate', '质量手册第五版候选稿', self::BASE_DIR . '/10-质量手册第五版候选稿.md', '手册完整正文基线'],
            ['procedure_catalog', '支持性程序预导入目录', self::PREIMPORT_DIR . '/documents_preimport.csv', '37份2022程序目录与原件路径'],
            ['manual_blocks', '手册结构块预导入清单', self::PREIMPORT_DIR . '/manual_blocks_preimport.csv', '29个手册章节至程序关系'],
            ['traceability_matrix', '条款程序记录追溯矩阵', self::PREIMPORT_DIR . '/traceability_matrix_preimport.csv', '手册至程序与记录的治理关系'],
            ['g1_terminal', 'G1签认终局', '.team/交接箱/2026-07-23-G2蓝图签认与交接单落盘/原件/claude_G1p-签认留痕暨G1终局-v1_0.md', 'G1签认依据'],
            ['g1_closeout', 'G1收官纪要', '.team/交接箱/2026-07-23-G2蓝图签认与交接单落盘/原件/claude_G1-批次3签认暨G1收官纪要-v1_0.md', '程序与手册修订收官依据'],
            ['g2_ledger', 'G2记录总台账定稿', '.team/交接箱/2026-07-23-G2蓝图签认与交接单落盘/原件/claude_G2-记录总台账-v0_2定稿.md', '记录范围与去重依据'],
            ['g2_route_a_signoff', 'G2 Route A签认交接单', '.team/交接箱/2026-07-23-G2蓝图签认与交接单落盘/原件/claude_G2-试点蓝图签认与交接单-v1_0.md', 'Route A签认依据'],
            ['g2_batch1_signoff', 'G2扩1批签认留痕', '.team/交接箱/2026-07-24-G2扩1批蓝图签认与仓库侧建模/原件/claude_G2-扩1批蓝图签认留痕-v1_0.md', '扩1批签认依据'],
            ['g2_batch2_blueprint', 'G2扩2批蓝图包', '.team/交接箱/2026-07-24-G2扩2批候选蓝图与仓库侧沙箱建模/原件/claude_G2-扩2批蓝图包-v0_1.md', '扩2批蓝图来源；最终试运行状态由扩4收官签认归一'],
            ['g2_batch3_blueprint', 'G2扩3批蓝图包', '.team/交接箱/2026-07-24-G2扩3批人审通过蓝图与仓库侧沙箱建模/原件/claude_G2-扩3批蓝图包-v0_1.md', '扩3批人审通过蓝图来源'],
            ['g2_batch4_terminal', 'G2扩4批签认暨沙箱侧收官', '.team/交接箱/2026-07-24-G2扩4批签认暨沙箱侧收官/原件/claude_G2-扩4批签认暨沙箱侧收官-v1_0.md', 'G2终态签认与试运行装配依据'],
        ];
        foreach ($governanceSources as [$key, $name, $path, $scope]) {
            self::registerSource($sources, [
                'source_key' => $key,
                'source_code' => strtoupper($key),
                'name' => $name,
                'source_type' => 'governance_evidence',
                'version' => '2026-07',
                'relative_path' => $path,
                'absolute_path' => self::absolutePath($path),
                'relation_scope' => $scope,
            ]);
        }

        return $sources;
    }

    private static function procedures(array &$sources): array
    {
        $rows = self::csvRows(self::absolutePath(self::PREIMPORT_DIR . '/documents_preimport.csv'));
        $procedures = [];
        foreach ($rows as $row) {
            if (($row['action'] ?? '') !== 'reference_existing_current' || ($row['level'] ?? '') !== '2') {
                continue;
            }
            $docNumber = (string)$row['doc_number'];
            $sourceKey = 'procedure_' . strtolower(str_replace(['XZTC/', '/', '-2022'], ['', '_', ''], $docNumber));
            $relativePath = (string)$row['source_stage_file'];
            self::registerSource($sources, [
                'source_key' => $sourceKey,
                'source_code' => $docNumber,
                'name' => (string)$row['title'],
                'source_type' => 'current_procedure_file',
                'version' => '2022',
                'relative_path' => $relativePath,
                'absolute_path' => self::absolutePath($relativePath),
                'relation_scope' => '试运行程序正文基线，叠加G1签认修订依据',
            ]);
            $procedures[] = [
                'doc_number' => $docNumber,
                'trial_doc_number' => 'SIM-' . $docNumber,
                'title' => (string)$row['title'],
                'version' => self::VERSION,
                'source_file_path' => $relativePath,
                'source_evidence' => [$sourceKey, 'procedure_catalog', 'g1_terminal', 'g1_closeout'],
                'manual_sections' => [],
                'direct_record_templates' => [],
                'record_templates' => [],
            ];
        }
        usort($procedures, static fn(array $left, array $right): int => strnatcmp((string)$left['doc_number'], (string)$right['doc_number']));

        return $procedures;
    }

    private static function manualSections(): array
    {
        $rows = self::csvRows(self::absolutePath(self::PREIMPORT_DIR . '/manual_blocks_preimport.csv'));
        $baselineSources = array_map(
            static fn(array $source): string => self::sourceKey((string)$source['source_code']),
            QmsPlanningImportService::officialSourceManifest()
        );
        $sections = [];
        foreach ($rows as $row) {
            $sections[] = [
                'section_key' => (string)$row['stable_key'],
                'section_number' => (string)$row['section_number'],
                'title' => (string)$row['title'],
                'block_type' => (string)$row['block_type'],
                'sort_order' => (int)$row['sort_order'],
                'procedure_doc_numbers' => self::splitList((string)$row['procedure_doc_numbers']),
                'record_templates' => [],
                'external_sources' => $baselineSources,
                'source_evidence' => ['manual_candidate', 'manual_blocks', 'traceability_matrix', 'g1_terminal', 'g1_closeout'],
                'source_locator' => self::BASE_DIR . '/10-质量手册第五版候选稿.md#' . (string)$row['section_number'],
            ];
        }
        usort($sections, static fn(array $left, array $right): int => $left['sort_order'] <=> $right['sort_order']);

        return $sections;
    }

    private static function recordTemplates(array &$sources, array $procedures, array $manualSections): array
    {
        $entries = [];
        foreach (RecordFormFixtureService::templates() as $template) {
            if (in_array((string)($template['doc_number'] ?? ''), ['XZTC/BG-01-02', 'XZTC/BG-01-06'], true)) {
                $entries[] = ['template' => $template, 'origin' => 'route_a', 'source_evidence' => ['g2_ledger', 'g2_route_a_signoff', 'g2_batch4_terminal']];
            }
        }
        foreach ([
            1 => G2ExpansionBatch1BlueprintService::templates(),
            2 => G2ExpansionBatch2BlueprintService::templates(),
            3 => G2ExpansionBatch3BlueprintService::templates(),
            4 => G2ExpansionBatch4BlueprintService::templates(),
        ] as $batch => $templates) {
            $batchSource = match ($batch) {
                1 => 'g2_batch1_signoff',
                2 => 'g2_batch2_blueprint',
                3 => 'g2_batch3_blueprint',
                4 => 'g2_batch4_terminal',
            };
            foreach ($templates as $template) {
                $entries[] = [
                    'template' => $template,
                    'origin' => 'g2_expansion_batch_' . $batch,
                    'source_evidence' => ['g2_ledger', $batchSource, 'g2_batch4_terminal'],
                ];
            }
        }

        $selectedRetained = [];
        foreach (RecordFormBatchTemplateService::manifest() as $template) {
            $number = (string)($template['doc_number'] ?? '');
            if (!in_array($number, self::RETAINED_RECORD_NUMBERS, true) || isset($selectedRetained[$number])) {
                continue;
            }
            $sourceKey = 'retained_' . strtolower(str_replace(['XZTC/', '/', '-'], ['', '_', '_'], $number));
            $relativePath = (string)($template['source_file_path'] ?? '');
            self::registerSource($sources, [
                'source_key' => $sourceKey,
                'source_code' => $number,
                'name' => (string)$template['name'],
                'source_type' => 'current_record_form_file',
                'version' => '2017/A0治理保留',
                'relative_path' => $relativePath,
                'absolute_path' => self::absolutePath($relativePath),
                'relation_scope' => 'G2去重后明确保留的现用记录母版来源',
            ]);
            $entries[] = [
                'template' => $template,
                'origin' => 'retained_current_form',
                'source_evidence' => ['g2_ledger', 'g2_batch4_terminal', $sourceKey],
            ];
            $selectedRetained[$number] = true;
        }
        if (count($selectedRetained) !== count(self::RETAINED_RECORD_NUMBERS)) {
            throw new RuntimeException('G2保留的8份现用记录母版未能完整解析');
        }

        $procedureNumbers = array_fill_keys(array_column($procedures, 'doc_number'), true);
        $manualKeyByProcedure = [];
        foreach ($manualSections as $section) {
            foreach ($section['procedure_doc_numbers'] as $procedureNumber) {
                $manualKeyByProcedure[$procedureNumber] ??= (string)$section['section_key'];
            }
        }

        $result = [];
        foreach ($entries as $entry) {
            $template = $entry['template'];
            $canonical = (string)$template['doc_number'];
            $procedureNumber = self::procedureForTemplate($template);
            if (!isset($procedureNumbers[$procedureNumber])) {
                throw new RuntimeException($canonical . ' 无法映射至37份现行程序目录：' . $procedureNumber);
            }
            $manualSectionKey = (string)($manualKeyByProcedure[$procedureNumber] ?? '');
            if ($manualSectionKey === '') {
                throw new RuntimeException($canonical . ' 对应程序未被手册章节落实：' . $procedureNumber);
            }
            $result[] = [
                'doc_number' => 'SIM-' . $canonical,
                'canonical_doc_number' => $canonical,
                'name' => '[治理试运行] ' . (string)$template['name'],
                'module' => (string)$template['module'],
                'version' => self::VERSION,
                'status' => 'trial_ready',
                'review_status' => 'completed',
                'review_note' => '仅限隔离环境治理试运行；依据G1/G2签认材料装配，不构成正式受控发布或真实运行证据。',
                'print_template_key' => (string)($template['print_template_key'] ?? ''),
                'field_schema' => array_values($template['field_schema'] ?? []),
                'procedure_doc_number' => $procedureNumber,
                'manual_section_key' => $manualSectionKey,
                'applicable_sites' => self::APPLICABLE_SITES,
                'responsible_position_code' => self::responsiblePosition($procedureNumber),
                'retention_period' => self::RETENTION_PERIOD,
                'trial_batch' => self::TRIAL_BATCH,
                'origin' => (string)$entry['origin'],
                'source_evidence' => array_values(array_unique(array_merge(
                    ['manual_candidate', 'g1_terminal'],
                    $entry['source_evidence']
                ))),
            ];
        }
        usort($result, static fn(array $left, array $right): int => strnatcmp((string)$left['canonical_doc_number'], (string)$right['canonical_doc_number']));

        return $result;
    }

    private static function procedureForTemplate(array $template): string
    {
        $canonical = (string)($template['doc_number'] ?? '');
        $module = trim((string)($template['module'] ?? ''));
        if (str_starts_with($canonical, 'XZTC/BG-05-')) {
            return 'XZTC/CX-05-2022';
        }
        if (isset(self::MODULE_PROCEDURE_MAP[$module])) {
            return self::MODULE_PROCEDURE_MAP[$module];
        }

        throw new RuntimeException($canonical . ' 的模块无法映射程序文件：' . $module);
    }

    private static function responsiblePosition(string $procedureNumber): string
    {
        if (!preg_match('/CX-(\d{2}(?:-\d{2})?)-2022$/', $procedureNumber, $match)) {
            return 'quality_manager';
        }

        return self::RESPONSIBLE_POSITIONS[$match[1]] ?? 'quality_manager';
    }

    private static function registerSource(array &$sources, array $source): void
    {
        $path = (string)($source['absolute_path'] ?? '');
        $source['sha256'] = is_file($path) ? (string)hash_file('sha256', $path) : '';
        $sources[(string)$source['source_key']] = $source;
    }

    private static function sourceKey(string $sourceCode): string
    {
        $key = strtolower($sourceCode);
        $key = preg_replace('/[^a-z0-9]+/u', '_', $key) ?? $key;

        return trim($key, '_');
    }

    private static function csvRows(string $path): array
    {
        if (!is_file($path)) {
            throw new RuntimeException('治理装配来源不存在：' . $path);
        }
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new RuntimeException('治理装配来源无法读取：' . $path);
        }
        $header = fgetcsv($handle, null, ',', '"', '\\');
        if (!is_array($header)) {
            fclose($handle);
            return [];
        }
        $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string)$header[0]) ?? (string)$header[0];
        $rows = [];
        while (($values = fgetcsv($handle, null, ',', '"', '\\')) !== false) {
            if (count($values) !== count($header)) {
                continue;
            }
            $rows[] = array_combine($header, $values);
        }
        fclose($handle);

        return $rows;
    }

    private static function splitList(string $value): array
    {
        $items = preg_split('/[；;]+/u', trim($value)) ?: [];

        return array_values(array_filter(array_map('trim', $items), static fn(string $item): bool => $item !== ''));
    }

    private static function absolutePath(string $relativePath): string
    {
        return self::repoRoot() . DIRECTORY_SEPARATOR . ltrim($relativePath, DIRECTORY_SEPARATOR);
    }

    private static function repoRoot(): string
    {
        return rtrim(dirname(__DIR__, 3), DIRECTORY_SEPARATOR);
    }
}
