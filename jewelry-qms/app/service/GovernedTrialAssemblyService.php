<?php
declare(strict_types=1);

namespace app\service;

use DomainException;
use RuntimeException;
use think\facade\Config;
use think\facade\Db;

final class GovernedTrialAssemblyService
{
    private const SAMPLE_TEMPLATE_NUMBERS = [
        'XZTC/BG-01-02',
        'XZTC/BG-09-02',
        'XZTC/BG-02-01',
        'XZTC/BG-08-09',
        'XZTC/BG-20-06',
    ];

    public static function inspect(): array
    {
        $blueprint = GovernedTrialAssemblyBlueprintService::build();

        return [
            'mode' => 'inspect_only',
            'trial_mode_enabled' => TrialModeService::isEnabled(),
            'configured_trial_batch' => TrialModeService::trialBatch(),
            'validation' => $blueprint['validation'],
            'counts' => $blueprint['validation']['counts'],
            'formal_system_notice' => $blueprint['formal_system_notice'],
            'counting_note' => $blueprint['counting_note'],
        ];
    }

    public static function apply(bool $seedSamples = false): array
    {
        self::assertWritableTrialEnvironment();
        self::assertSchema();
        $blueprint = GovernedTrialAssemblyBlueprintService::build();
        if (($blueprint['validation']['ok'] ?? false) !== true) {
            throw new RuntimeException('治理试运行蓝图自检未通过：' . implode('；', $blueprint['validation']['errors'] ?? []));
        }

        Db::transaction(function () use ($blueprint, $seedSamples): void {
            $companyId = (string)Config::get('qms.company_id');
            $sourceMap = [];
            foreach ($blueprint['sources'] as $source) {
                $sourceMap[(string)$source['source_key']] = $source;
            }

            $documentIds = self::upsertTrialDocuments($blueprint, $sourceMap, $companyId);
            QmsElementService::seedExternalSources();
            QmsElementService::seedElements();
            self::ensureCmaClauseLinks($companyId);
            QmsElementService::seedResponsibilities();
            [$templateIds, $elementIds] = self::upsertTrialTemplates($blueprint, $sourceMap, $documentIds, $companyId);
            self::upsertStructuresAndLinks($blueprint, $sourceMap, $documentIds, $templateIds, $elementIds, $companyId);
            if ($seedSamples) {
                self::upsertSimulationInstances($blueprint, $templateIds, $companyId);
            }
        });

        $verification = self::verify();
        if (($verification['ok'] ?? false) !== true) {
            throw new RuntimeException('治理试运行装配后验证未通过：' . implode('；', $verification['errors'] ?? []));
        }

        return $verification + [
            'mode' => 'trial_apply',
            'trial_batch' => GovernedTrialAssemblyBlueprintService::TRIAL_BATCH,
            'formal_system_notice' => '仅限隔离环境治理试运行；纸质体系仍为唯一正式体系。',
        ];
    }

    public static function procedureLinkSpecifications(
        array $procedure,
        string $manualSectionId,
        string $elementId,
        string $procedureDocumentId,
        array $recordTemplateIds,
        string $positionId,
        array $basisClauseIds = []
    ): array {
        $specifications = [];
        if ($manualSectionId !== '') {
            $specifications[] = [
                'element_id' => $elementId !== '' ? $elementId : null,
                'manual_section_id' => $manualSectionId,
                'relation_type' => 'implements',
                'confidence' => 'high',
                'note' => '治理试运行：程序主链落实对应手册章节。',
            ];
        }
        if ($positionId !== '') {
            $specifications[] = [
                'position_id' => $positionId,
                'relation_type' => 'responsible',
                'confidence' => 'high',
                'note' => '治理试运行：程序责任岗位独立记录。',
            ];
        }
        foreach (array_values(array_unique($basisClauseIds)) as $clauseId) {
            if ((string)$clauseId === '') {
                continue;
            }
            $specifications[] = [
                'element_id' => $elementId !== '' ? $elementId : null,
                'clause_id' => (string)$clauseId,
                'relation_type' => 'basis',
                'confidence' => 'high',
                'note' => '治理试运行：程序主链回溯适用外部依据。',
            ];
        }
        foreach ((array)($procedure['record_templates'] ?? []) as $recordNumber) {
            $templateId = (string)($recordTemplateIds[(string)$recordNumber] ?? '');
            if ($templateId === '') {
                continue;
            }
            $specifications[] = [
                'procedure_document_id' => $procedureDocumentId,
                'record_form_template_id' => $templateId,
                'relation_type' => 'requires_record',
                'confidence' => 'high',
                'note' => '治理试运行：程序运行由该SIM记录模板提供证据。',
            ];
        }

        return $specifications;
    }

    public static function verify(): array
    {
        self::assertSchema();
        $batch = GovernedTrialAssemblyBlueprintService::TRIAL_BATCH;
        $errors = [];
        $trialDocuments = (int)Db::name('documents')
            ->whereLike('doc_number', 'SIM-XZTC/%')
            ->where('version', GovernedTrialAssemblyBlueprintService::VERSION)
            ->where('soft_delete', 0)
            ->count();
        $trialTemplates = (int)Db::name('record_form_templates')
            ->where('trial_batch', $batch)
            ->where('status', 'trial_ready')
            ->where('soft_delete', 0)
            ->count();
        $simulationInstances = (int)Db::name('record_form_instances')
            ->where('trial_batch', $batch)
            ->where('is_simulation', 1)
            ->whereLike('doc_number', 'SIM-GOV-20260724-%')
            ->count();

        if ($trialDocuments !== 38) {
            $errors[] = 'SIM手册/程序数量应为38，当前为' . $trialDocuments;
        }
        if ($trialTemplates !== 104) {
            $errors[] = '活动模板数量应为104，当前为' . $trialTemplates;
        }
        if ($simulationInstances !== 10) {
            $errors[] = '代表性SIM记录数量应为10，当前为' . $simulationInstances;
        }

        $templates = Db::name('record_form_templates')
            ->where('trial_batch', $batch)
            ->where('status', 'trial_ready')
            ->where('soft_delete', 0)
            ->select()
            ->toArray();
        foreach ($templates as $template) {
            $readinessErrors = TrialModeService::readinessErrors($template);
            if ($readinessErrors !== []) {
                $errors[] = (string)$template['doc_number'] . ' 未就绪：' . implode('、', $readinessErrors);
            }
            $procedureId = (string)($template['procedure_doc_id'] ?? '');
            $templateId = (string)$template['id'];
            $procedureLinkExists = Db::name('qms_document_block_links')->alias('link')
                ->join('qms_document_blocks block', 'block.id = link.block_id')
                ->join('qms_structured_documents structure', 'structure.id = block.structured_document_id')
                ->where('link.record_form_template_id', $templateId)
                ->where('link.procedure_document_id', $procedureId)
                ->where('structure.document_role', 'procedure')
                ->where('structure.version', GovernedTrialAssemblyBlueprintService::VERSION)
                ->where('link.soft_delete', 0)
                ->where('block.soft_delete', 0)
                ->where('structure.soft_delete', 0)
                ->count() > 0;
            if (!$procedureLinkExists) {
                $errors[] = (string)$template['doc_number'] . ' 缺少程序块至记录模板链接';
            }
            $manualLinkExists = Db::name('qms_document_block_links')->alias('link')
                ->join('qms_document_blocks block', 'block.id = link.block_id')
                ->join('qms_structured_documents structure', 'structure.id = block.structured_document_id')
                ->where('link.record_form_template_id', $templateId)
                ->where('structure.document_role', 'quality_manual')
                ->where('structure.version', GovernedTrialAssemblyBlueprintService::VERSION)
                ->where('link.soft_delete', 0)
                ->where('block.soft_delete', 0)
                ->where('structure.soft_delete', 0)
                ->count() > 0;
            if (!$manualLinkExists) {
                $errors[] = (string)$template['doc_number'] . ' 缺少手册块至记录模板链接';
            }
        }

        $procedureWithoutRecord = Db::name('qms_structured_documents')->alias('structure')
            ->join('qms_document_blocks block', 'block.structured_document_id = structure.id')
            ->leftJoin(
                'qms_document_block_links link',
                "link.block_id = block.id AND link.record_form_template_id IS NOT NULL AND link.soft_delete = 0"
            )
            ->where('structure.document_role', 'procedure')
            ->where('structure.version', GovernedTrialAssemblyBlueprintService::VERSION)
            ->where('structure.soft_delete', 0)
            ->where('block.soft_delete', 0)
            ->group('structure.id')
            ->having('COUNT(link.id) = 0')
            ->count();
        if ((int)$procedureWithoutRecord !== 0) {
            $errors[] = '存在' . (int)$procedureWithoutRecord . '份程序无运行证明记录链接';
        }

        $manualWithoutBasis = Db::name('qms_document_blocks')->alias('block')
            ->join('qms_structured_documents structure', 'structure.id = block.structured_document_id')
            ->leftJoin(
                'qms_document_block_links link',
                "link.block_id = block.id AND link.clause_id IS NOT NULL AND link.relation_type = 'basis' AND link.soft_delete = 0"
            )
            ->where('structure.document_role', 'quality_manual')
            ->where('structure.version', GovernedTrialAssemblyBlueprintService::VERSION)
            ->where('structure.soft_delete', 0)
            ->where('block.soft_delete', 0)
            ->group('block.id')
            ->having('COUNT(link.id) = 0')
            ->count();
        if ((int)$manualWithoutBasis !== 0) {
            $errors[] = '存在' . (int)$manualWithoutBasis . '个手册章节无外部依据条款链接';
        }

        $officialSources = array_column(QmsPlanningImportService::officialSourceManifest(), 'source_code');
        foreach ($officialSources as $sourceCode) {
            $basisCount = Db::name('qms_document_block_links')->alias('link')
                ->join('qms_document_blocks block', 'block.id = link.block_id')
                ->join('qms_structured_documents structure', 'structure.id = block.structured_document_id')
                ->join('qms_clauses clause', 'clause.id = link.clause_id')
                ->join('qms_sources source', 'source.id = clause.source_id')
                ->where('source.source_code', (string)$sourceCode)
                ->where('structure.document_role', 'quality_manual')
                ->where('structure.version', GovernedTrialAssemblyBlueprintService::VERSION)
                ->where('link.relation_type', 'basis')
                ->where('link.soft_delete', 0)
                ->count();
            if ((int)$basisCount === 0) {
                $errors[] = '外部依据未回链到试运行手册：' . $sourceCode;
            }
        }

        $instances = Db::name('record_form_instances')
            ->where('trial_batch', $batch)
            ->where('is_simulation', 1)
            ->whereLike('doc_number', 'SIM-GOV-20260724-%')
            ->select()
            ->toArray();
        foreach ($instances as $instance) {
            $path = (string)($instance['generated_html_path'] ?? '');
            if ($path === '' || !is_file($path)) {
                $errors[] = (string)$instance['doc_number'] . ' 缺少可打印HTML';
            }
        }

        return [
            'ok' => $errors === [],
            'errors' => array_values(array_unique($errors)),
            'counts' => [
                'trial_documents' => $trialDocuments,
                'trial_templates' => $trialTemplates,
                'simulation_instances' => $simulationInstances,
            ],
        ];
    }

    public static function nonSimulationFingerprint(): array
    {
        $payloads = [
            'documents' => Db::name('documents')
                ->field('id,doc_number,title,version,status,publish,soft_delete,modified')
                ->where('doc_number', 'not like', 'SIM-%')
                ->order('id')
                ->select()
                ->toArray(),
            'record_form_templates' => Db::name('record_form_templates')
                ->field('id,doc_number,name,version,status,publish,soft_delete,modified')
                ->where('doc_number', 'not like', 'SIM-%')
                ->order('id')
                ->select()
                ->toArray(),
            'record_form_instances' => Db::name('record_form_instances')
                ->field('id,doc_number,record_title,status,modified')
                ->where('is_simulation', 0)
                ->order('id')
                ->select()
                ->toArray(),
        ];
        $result = [];
        foreach ($payloads as $table => $rows) {
            $result[$table] = [
                'count' => count($rows),
                'sha256' => hash('sha256', json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
            ];
        }

        return $result;
    }

    private static function assertWritableTrialEnvironment(): void
    {
        if (!TrialModeService::isEnabled()) {
            throw new DomainException('治理试运行装配拒绝写入：QMS_TRIAL_MODE 未启用');
        }
        if (TrialModeService::trialBatch() !== GovernedTrialAssemblyBlueprintService::TRIAL_BATCH) {
            throw new DomainException(
                '治理试运行装配拒绝写入：QMS_TRIAL_BATCH 必须为 '
                . GovernedTrialAssemblyBlueprintService::TRIAL_BATCH
            );
        }
    }

    private static function assertSchema(): void
    {
        foreach ([
            'record_form_templates' => [
                'trial_batch',
                'canonical_doc_number',
                'trial_of_template_id',
                'applicable_sites',
                'responsible_position_code',
                'retention_period',
            ],
            'record_form_instances' => ['is_simulation', 'trial_batch'],
            'documents' => ['supersedes_document_id', 'revision_root_id'],
        ] as $table => $columns) {
            $available = Db::query('SHOW COLUMNS FROM `' . $table . '`');
            $available = array_fill_keys(array_map(static fn(array $row): string => (string)$row['Field'], $available), true);
            foreach ($columns as $column) {
                if (!isset($available[$column])) {
                    throw new RuntimeException('数据库未应用受控试运行迁移：' . $table . '.' . $column);
                }
            }
        }
    }

    private static function upsertTrialDocuments(array $blueprint, array $sourceMap, string $companyId): array
    {
        $documentIds = [];
        $manualSources = self::evidenceDetails(
            ['manual_candidate', 'g1_terminal', 'g1_closeout'],
            $sourceMap
        );
        $manualId = self::upsertDocument([
            'company_id' => $companyId,
            'level' => 1,
            'doc_number' => 'SIM-XZTC/SC',
            'title' => '[治理试运行] 质量手册第五版候选（G1签认叠加）',
            'version' => GovernedTrialAssemblyBlueprintService::VERSION,
            'status' => 'trial_ready',
            'file_path' => (string)$sourceMap['manual_candidate']['relative_path'],
            'file_name' => basename((string)$sourceMap['manual_candidate']['relative_path']),
            'file_type' => 'md',
            'change_reason' => json_encode([
                'notice' => '第五版完整候选稿叠加G1签认差异，仅供隔离治理试运行。',
                'sources' => $manualSources,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'publish' => 1,
            'soft_delete' => 0,
        ], 'XZTC/SC');
        $documentIds['XZTC/SC'] = $manualId;

        foreach ($blueprint['procedures'] as $procedure) {
            $canonical = (string)$procedure['doc_number'];
            $evidence = self::evidenceDetails($procedure['source_evidence'], $sourceMap);
            $documentIds[$canonical] = self::upsertDocument([
                'company_id' => $companyId,
                'level' => 2,
                'doc_number' => (string)$procedure['trial_doc_number'],
                'title' => '[治理试运行] ' . (string)$procedure['title'],
                'version' => GovernedTrialAssemblyBlueprintService::VERSION,
                'status' => 'trial_ready',
                'file_path' => (string)$procedure['source_file_path'],
                'file_name' => basename((string)$procedure['source_file_path']),
                'file_type' => strtolower((string)pathinfo((string)$procedure['source_file_path'], PATHINFO_EXTENSION)),
                'change_reason' => json_encode([
                    'notice' => '2022程序正文叠加G1签认修订依据，仅供隔离治理试运行。',
                    'sources' => $evidence,
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'publish' => 1,
                'soft_delete' => 0,
            ], $canonical);
        }

        return $documentIds;
    }

    private static function upsertDocument(array $row, string $canonicalNumber): string
    {
        if (!str_starts_with((string)$row['doc_number'], 'SIM-')) {
            throw new DomainException('治理试运行只允许写入SIM文件');
        }
        $current = Db::name('documents')
            ->where('doc_number', $canonicalNumber)
            ->where('soft_delete', 0)
            ->order('publish', 'desc')
            ->find();
        $existing = Db::name('documents')
            ->where('doc_number', (string)$row['doc_number'])
            ->where('soft_delete', 0)
            ->find();
        $now = date('Y-m-d H:i:s');
        $row['supersedes_document_id'] = (string)($current['id'] ?? '');
        $row['revision_root_id'] = (string)($current['revision_root_id'] ?? $current['id'] ?? '');
        $row['effective_date'] = null;
        $row['approved_by'] = null;
        $row['modified'] = $now;
        if (is_array($existing)) {
            Db::name('documents')->where('id', (string)$existing['id'])->update($row);
            return (string)$existing['id'];
        }
        $id = qms_uuid();
        $row['id'] = $id;
        $row['created'] = $now;
        Db::name('documents')->insert($row);

        return $id;
    }

    private static function upsertTrialTemplates(
        array $blueprint,
        array $sourceMap,
        array $documentIds,
        string $companyId
    ): array {
        $templateIds = [];
        $elementIds = [];
        foreach (QmsElementService::defaultElementDefinitions() as $definition) {
            $element = Db::name('qms_elements')
                ->where('key', (string)$definition['key'])
                ->where('soft_delete', 0)
                ->find();
            if (is_array($element)) {
                $elementIds[(string)$definition['primary_clause_number']] = (string)$element['id'];
            }
        }
        $sectionNumberByKey = array_column($blueprint['manual_sections'], 'section_number', 'section_key');

        foreach ($blueprint['record_templates'] as $template) {
            $docNumber = (string)$template['doc_number'];
            if (!str_starts_with($docNumber, 'SIM-')) {
                throw new DomainException('治理试运行只允许写入SIM模板');
            }
            $canonical = (string)$template['canonical_doc_number'];
            $current = Db::name('record_form_templates')
                ->where('doc_number', $canonical)
                ->where('soft_delete', 0)
                ->order('publish', 'desc')
                ->find();
            $existing = Db::name('record_form_templates')
                ->where('doc_number', $docNumber)
                ->where('trial_batch', GovernedTrialAssemblyBlueprintService::TRIAL_BATCH)
                ->where('status', 'trial_ready')
                ->where('soft_delete', 0)
                ->find();
            $sectionNumber = (string)($sectionNumberByKey[(string)$template['manual_section_key']] ?? '');
            $sourceEvidence = self::evidenceDetails($template['source_evidence'], $sourceMap);
            $primarySource = end($sourceEvidence);
            $sourcePath = is_array($primarySource) ? (string)($primarySource['relative_path'] ?? '') : '';
            $row = [
                'company_id' => $companyId,
                'document_id' => null,
                'element_id' => (string)($elementIds[$sectionNumber] ?? ''),
                'procedure_doc_id' => (string)$documentIds[(string)$template['procedure_doc_number']],
                'doc_number' => $docNumber,
                'canonical_doc_number' => $canonical,
                'trial_of_template_id' => (string)($current['id'] ?? ''),
                'name' => (string)$template['name'],
                'module' => (string)$template['module'],
                'applicable_sites' => (string)$template['applicable_sites'],
                'responsible_position_code' => (string)$template['responsible_position_code'],
                'retention_period' => (string)$template['retention_period'],
                'source_file_path' => $sourcePath,
                'source_file_name' => $sourcePath !== '' ? basename($sourcePath) : '',
                'source_file_sha1' => $sourcePath !== '' && is_file(self::absolutePath($sourcePath))
                    ? (string)sha1_file(self::absolutePath($sourcePath))
                    : null,
                'print_template_key' => (string)$template['print_template_key'],
                'field_schema' => RecordFormSchemaService::encode($template['field_schema']),
                'version' => (string)$template['version'],
                'status' => 'trial_ready',
                'trial_batch' => GovernedTrialAssemblyBlueprintService::TRIAL_BATCH,
                'trial_approved_by' => null,
                'trial_approved_at' => null,
                'trial_note' => json_encode([
                    'notice' => '已由用户确认装配至隔离系统治理试运行；不代表正式发布或真实人员签批。',
                    'sources' => $sourceEvidence,
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'review_status' => 'completed',
                'review_note' => (string)$template['review_note'],
                'reviewed_at' => date('Y-m-d H:i:s'),
                'publish' => 1,
                'soft_delete' => 0,
                'modified' => date('Y-m-d H:i:s'),
            ];
            if (is_array($existing)) {
                Db::name('record_form_templates')->where('id', (string)$existing['id'])->update($row);
                $templateIds[$canonical] = (string)$existing['id'];
            } else {
                $id = qms_uuid();
                $row['id'] = $id;
                $row['created'] = date('Y-m-d H:i:s');
                Db::name('record_form_templates')->insert($row);
                $templateIds[$canonical] = $id;
            }
        }

        return [$templateIds, $elementIds];
    }

    private static function upsertStructuresAndLinks(
        array $blueprint,
        array $sourceMap,
        array $documentIds,
        array $templateIds,
        array $elementIds,
        string $companyId
    ): void {
        $manualStructureId = self::upsertStructuredDocument([
            'company_id' => $companyId,
            'document_id' => $documentIds['XZTC/SC'],
            'document_role' => 'quality_manual',
            'doc_number' => 'SIM-XZTC/SC',
            'title' => '[治理试运行] 质量手册第五版候选（G1签认叠加）',
            'version' => GovernedTrialAssemblyBlueprintService::VERSION,
            'source_status' => 'draft',
            'markdown_path' => (string)$sourceMap['manual_candidate']['relative_path'],
            'render_status' => 'not_rendered',
            'status' => 'structured',
            'review_note' => '完整候选正文+G1签认差异叠加；仅供隔离治理试运行。',
            'publish' => 1,
            'soft_delete' => 0,
        ]);

        $manualSectionIds = [];
        $manualBlockIds = [];
        $manualSectionNumberByKey = [];
        foreach ($blueprint['manual_sections'] as $section) {
            $sectionNumber = (string)$section['section_number'];
            $manualSectionNumberByKey[(string)$section['section_key']] = $sectionNumber;
            $manualSection = Db::name('qms_manual_sections')
                ->where('document_id', $documentIds['XZTC/SC'])
                ->where('section_number', $sectionNumber)
                ->where('soft_delete', 0)
                ->find();
            $elementId = (string)($elementIds[$sectionNumber] ?? $manualSection['element_id'] ?? '');
            if (!is_array($manualSection)) {
                $manualSectionId = qms_uuid();
                Db::name('qms_manual_sections')->insert([
                    'id' => $manualSectionId,
                    'company_id' => $companyId,
                    'document_id' => $documentIds['XZTC/SC'],
                    'element_id' => $elementId !== '' ? $elementId : null,
                    'parent_id' => null,
                    'section_number' => $sectionNumber,
                    'title' => (string)$section['title'],
                    'level' => substr_count($sectionNumber, '.') + 1,
                    'summary' => '治理试运行手册章节；完整正文见第五版候选稿，修订依据见G1签认材料。',
                    'status' => 'draft',
                    'publish' => 1,
                    'soft_delete' => 0,
                    'created' => date('Y-m-d H:i:s'),
                    'modified' => date('Y-m-d H:i:s'),
                ]);
            } else {
                $manualSectionId = (string)$manualSection['id'];
                Db::name('qms_manual_sections')->where('id', $manualSectionId)->update([
                    'element_id' => $elementId !== '' ? $elementId : null,
                    'title' => (string)$section['title'],
                    'summary' => '治理试运行手册章节；完整正文见第五版候选稿，修订依据见G1签认材料。',
                    'status' => 'draft',
                    'modified' => date('Y-m-d H:i:s'),
                ]);
            }
            $manualSectionIds[$sectionNumber] = $manualSectionId;
            $manualBlockIds[(string)$section['section_key']] = self::upsertBlock([
                'company_id' => $companyId,
                'structured_document_id' => $manualStructureId,
                'document_id' => $documentIds['XZTC/SC'],
                'stable_key' => (string)$section['section_key'],
                'section_number' => $sectionNumber,
                'title' => (string)$section['title'],
                'block_type' => 'control_requirement',
                'markdown' => self::manualBlockMarkdown($section, $sourceMap),
                'sort_order' => (int)$section['sort_order'],
                'source_locator' => (string)$section['source_locator'],
                'status' => 'draft',
                'publish' => 1,
                'soft_delete' => 0,
            ]);
            if ($elementId !== '') {
                $elementIds[$sectionNumber] = $elementId;
            }
        }

        $procedureStructureIds = [];
        $procedureBlockIds = [];
        foreach ($blueprint['procedures'] as $procedure) {
            $canonical = (string)$procedure['doc_number'];
            $procedureStructureIds[$canonical] = self::upsertStructuredDocument([
                'company_id' => $companyId,
                'document_id' => $documentIds[$canonical],
                'document_role' => 'procedure',
                'doc_number' => (string)$procedure['trial_doc_number'],
                'title' => '[治理试运行] ' . (string)$procedure['title'],
                'version' => GovernedTrialAssemblyBlueprintService::VERSION,
                'source_status' => 'draft',
                'markdown_path' => (string)$procedure['source_file_path'],
                'render_status' => 'not_rendered',
                'status' => 'structured',
                'review_note' => '2022程序正文+G1签认差异叠加；运行记录链接由本批次装配。',
                'publish' => 1,
                'soft_delete' => 0,
            ]);
            $procedureBlockIds[$canonical] = self::upsertBlock([
                'company_id' => $companyId,
                'structured_document_id' => $procedureStructureIds[$canonical],
                'document_id' => $documentIds[$canonical],
                'stable_key' => 'governed_trial_' . strtolower(str_replace(['XZTC/', '/', '-'], ['', '_', '_'], $canonical)),
                'section_number' => '运行落实',
                'title' => (string)$procedure['title'] . '运行落实',
                'block_type' => 'record_requirement',
                'markdown' => self::procedureBlockMarkdown($procedure),
                'sort_order' => 1,
                'source_locator' => (string)$procedure['source_file_path'],
                'status' => 'draft',
                'publish' => 1,
                'soft_delete' => 0,
            ]);
        }

        $allBlockIds = array_values(array_merge($manualBlockIds, $procedureBlockIds));
        if ($allBlockIds !== []) {
            Db::name('qms_document_block_links')->whereIn('block_id', $allBlockIds)->delete();
        }
        $links = [];
        $procedureByNumber = array_column($blueprint['procedures'], null, 'doc_number');
        $templateByNumber = array_column($blueprint['record_templates'], null, 'canonical_doc_number');

        foreach ($blueprint['manual_sections'] as $section) {
            $blockId = $manualBlockIds[(string)$section['section_key']];
            $sectionNumber = (string)$section['section_number'];
            $elementId = (string)($elementIds[$sectionNumber] ?? '');
            $manualSectionId = (string)($manualSectionIds[$sectionNumber] ?? '');
            foreach ($section['procedure_doc_numbers'] as $procedureNumber) {
                $links[] = self::linkRow($companyId, $blockId, [
                    'element_id' => $elementId,
                    'manual_section_id' => $manualSectionId,
                    'procedure_document_id' => (string)$documentIds[$procedureNumber],
                    'relation_type' => 'implements',
                    'confidence' => 'high',
                    'note' => '治理试运行：手册章节落实到SIM程序文件。',
                ]);
            }
            foreach ($section['record_templates'] as $recordNumber) {
                $template = $templateByNumber[$recordNumber] ?? null;
                if (!is_array($template)) {
                    continue;
                }
                $links[] = self::linkRow($companyId, $blockId, [
                    'element_id' => $elementId,
                    'manual_section_id' => $manualSectionId,
                    'procedure_document_id' => (string)$documentIds[(string)$template['procedure_doc_number']],
                    'record_form_template_id' => (string)$templateIds[$recordNumber],
                    'relation_type' => 'requires_record',
                    'confidence' => 'high',
                    'note' => '治理试运行：记录模板作为本章节运行证明。',
                ]);
            }
            foreach (self::basisClauseIds($elementId) as $clauseId) {
                $links[] = self::linkRow($companyId, $blockId, [
                    'element_id' => $elementId,
                    'clause_id' => $clauseId,
                    'manual_section_id' => $manualSectionId,
                    'relation_type' => 'basis',
                    'confidence' => 'high',
                    'note' => '治理试运行：本地归档外部依据条款回链。',
                ]);
            }
        }

        foreach ($blueprint['procedures'] as $procedure) {
            $canonical = (string)$procedure['doc_number'];
            $blockId = $procedureBlockIds[$canonical];
            $manualKey = (string)(
                $procedure['primary_manual_section_key']
                ?? $procedure['manual_sections'][0]
                ?? ''
            );
            $sectionNumber = (string)($manualSectionNumberByKey[$manualKey] ?? '');
            $elementId = (string)($elementIds[$sectionNumber] ?? '');
            $positionId = self::positionIdForProcedure($procedure, $templateByNumber);
            foreach (self::procedureLinkSpecifications(
                $procedure,
                (string)($manualSectionIds[$sectionNumber] ?? ''),
                $elementId,
                (string)$documentIds[$canonical],
                $templateIds,
                $positionId,
                self::basisClauseIds($elementId)
            ) as $specification) {
                $links[] = self::linkRow($companyId, $blockId, $specification);
            }
        }

        if ($links !== []) {
            Db::name('qms_document_block_links')->insertAll($links);
        }
    }

    private static function upsertStructuredDocument(array $row): string
    {
        $existing = Db::name('qms_structured_documents')
            ->where('document_role', (string)$row['document_role'])
            ->where('doc_number', (string)$row['doc_number'])
            ->where('version', (string)$row['version'])
            ->where('soft_delete', 0)
            ->find();
        $row['modified'] = date('Y-m-d H:i:s');
        if (is_array($existing)) {
            Db::name('qms_structured_documents')->where('id', (string)$existing['id'])->update($row);
            return (string)$existing['id'];
        }
        $id = qms_uuid();
        $row['id'] = $id;
        $row['created'] = date('Y-m-d H:i:s');
        Db::name('qms_structured_documents')->insert($row);

        return $id;
    }

    private static function upsertBlock(array $row): string
    {
        $existing = Db::name('qms_document_blocks')
            ->where('structured_document_id', (string)$row['structured_document_id'])
            ->where('stable_key', (string)$row['stable_key'])
            ->where('soft_delete', 0)
            ->find();
        $row['modified'] = date('Y-m-d H:i:s');
        if (is_array($existing)) {
            Db::name('qms_document_blocks')->where('id', (string)$existing['id'])->update($row);
            return (string)$existing['id'];
        }
        $id = qms_uuid();
        $row['id'] = $id;
        $row['created'] = date('Y-m-d H:i:s');
        Db::name('qms_document_blocks')->insert($row);

        return $id;
    }

    private static function linkRow(string $companyId, string $blockId, array $fields): array
    {
        return array_merge([
            'id' => qms_uuid(),
            'company_id' => $companyId,
            'block_id' => $blockId,
            'element_id' => null,
            'clause_id' => null,
            'manual_section_id' => null,
            'procedure_document_id' => null,
            'record_form_template_id' => null,
            'position_id' => null,
            'business_module_id' => null,
            'relation_type' => 'implements',
            'confidence' => 'high',
            'note' => '',
            'publish' => 1,
            'soft_delete' => 0,
            'created' => date('Y-m-d H:i:s'),
            'modified' => date('Y-m-d H:i:s'),
        ], $fields);
    }

    private static function basisClauseIds(string $elementId): array
    {
        if ($elementId === '') {
            return [];
        }
        $rows = Db::name('qms_element_clause_links')->alias('map')
            ->join('qms_clauses clause', 'clause.id = map.clause_id')
            ->join('qms_sources source', 'source.id = clause.source_id')
            ->where('map.element_id', $elementId)
            ->where('map.soft_delete', 0)
            ->where('clause.soft_delete', 0)
            ->where('source.soft_delete', 0)
            ->field('source.source_code,map.clause_id')
            ->order('source.source_code')
            ->select()
            ->toArray();
        $firstBySource = [];
        foreach ($rows as $row) {
            $firstBySource[(string)$row['source_code']] ??= (string)$row['clause_id'];
        }

        return array_values($firstBySource);
    }

    private static function ensureCmaClauseLinks(string $companyId): void
    {
        $source = Db::name('qms_sources')
            ->where('source_code', '市场监管总局公告2023年第21号')
            ->where('soft_delete', 0)
            ->find();
        if (!is_array($source)) {
            return;
        }
        $elementMap = [];
        foreach (Db::name('qms_elements')->where('soft_delete', 0)->select()->toArray() as $element) {
            $elementMap[(string)$element['key']] = (string)$element['id'];
        }
        $prefixMap = [
            '2.12.9' => 'validity_of_results',
            '2.12.8' => 'data_information',
            '2.12.7' => 'record_control',
            '2.12.6' => 'results_reporting',
            '2.12.5' => 'measurement_uncertainty',
            '2.12.4' => 'methods',
            '2.12.3' => 'externally_provided_products',
            '2.12.2' => 'contract_review',
            '2.12.1' => 'management_system_documents',
            '2.11.3' => 'metrological_traceability',
            '2.11.2' => 'metrological_traceability',
            '2.11.1' => 'equipment',
            '2.10' => 'facilities_environment',
            '2.9' => 'personnel',
            '2.8.4' => 'confidentiality',
            '2.8.3' => 'impartiality',
            '2.8' => 'structure',
            '2.13' => 'management_system_options',
            '2.12' => 'management_system_documents',
            '2' => 'management_system_options',
        ];
        $clauses = Db::name('qms_clauses')
            ->where('source_id', (string)$source['id'])
            ->where('soft_delete', 0)
            ->select()
            ->toArray();
        foreach ($clauses as $clause) {
            $number = rtrim((string)$clause['clause_number'], '*');
            $elementKey = '';
            foreach ($prefixMap as $prefix => $candidate) {
                if ($number === $prefix || str_starts_with($number, $prefix . '.')) {
                    $elementKey = $candidate;
                    break;
                }
            }
            $elementId = (string)($elementMap[$elementKey] ?? '');
            if ($elementId === '') {
                continue;
            }
            $existing = Db::name('qms_element_clause_links')
                ->where('element_id', $elementId)
                ->where('clause_id', (string)$clause['id'])
                ->where('soft_delete', 0)
                ->find();
            $row = [
                'company_id' => $companyId,
                'element_id' => $elementId,
                'clause_id' => (string)$clause['id'],
                'mapping_type' => 'reference',
                'is_primary' => 0,
                'note' => '治理试运行：按2023版资质认定评审准则条款主题映射至对应17025体系要素。',
                'publish' => 1,
                'soft_delete' => 0,
                'modified' => date('Y-m-d H:i:s'),
            ];
            if (is_array($existing)) {
                Db::name('qms_element_clause_links')->where('id', (string)$existing['id'])->update($row);
            } else {
                $row['id'] = qms_uuid();
                $row['created'] = date('Y-m-d H:i:s');
                Db::name('qms_element_clause_links')->insert($row);
            }
        }
    }

    private static function positionIdForProcedure(array $procedure, array $templateByNumber): string
    {
        foreach ($procedure['record_templates'] as $recordNumber) {
            $code = (string)($templateByNumber[$recordNumber]['responsible_position_code'] ?? '');
            if ($code === '') {
                continue;
            }
            $position = Db::name('qms_positions')->where('code', $code)->where('soft_delete', 0)->find();
            if (is_array($position)) {
                return (string)$position['id'];
            }
        }

        return '';
    }

    private static function upsertSimulationInstances(array $blueprint, array $templateIds, string $companyId): void
    {
        $templates = array_column($blueprint['record_templates'], null, 'canonical_doc_number');
        $outputDir = self::sampleOutputDir();
        if (!is_dir($outputDir) && !mkdir($outputDir, 0775, true) && !is_dir($outputDir)) {
            throw new RuntimeException('无法创建治理试运行样例目录：' . $outputDir);
        }
        foreach (self::SAMPLE_TEMPLATE_NUMBERS as $canonical) {
            $template = $templates[$canonical] ?? null;
            if (!is_array($template)) {
                throw new RuntimeException('代表性SIM模板不存在：' . $canonical);
            }
            foreach (['wulumuqi', 'hetian'] as $site) {
                $siteLabel = $site === 'hetian' ? '和田' : '乌鲁木齐';
                $values = self::sampleValues($template, $site);
                $html = RecordFormPrintService::render(
                    (string)$template['print_template_key'],
                    $template,
                    $values
                );
                $html = TrialModeService::watermarkHtml($html, true);
                $short = str_replace('XZTC/BG-', 'BG-', $canonical);
                $filePath = $outputDir . DIRECTORY_SEPARATOR . $short . '-' . $site . '.html';
                if (file_put_contents($filePath, $html) === false) {
                    throw new RuntimeException('无法写入治理试运行样例：' . $filePath);
                }
                $instanceNumber = 'SIM-GOV-20260724-' . strtoupper($site) . '-' . str_replace('XZTC/BG-', 'BG-', $canonical);
                $existing = Db::name('record_form_instances')
                    ->where('doc_number', $instanceNumber)
                    ->where('trial_batch', GovernedTrialAssemblyBlueprintService::TRIAL_BATCH)
                    ->find();
                $row = [
                    'company_id' => $companyId,
                    'template_id' => (string)$templateIds[$canonical],
                    'template_name' => (string)$template['name'],
                    'template_module' => (string)$template['module'],
                    'template_version' => (string)$template['version'],
                    'template_print_template_key' => (string)$template['print_template_key'],
                    'template_field_schema' => RecordFormSchemaService::encode($template['field_schema']),
                    'doc_number' => $instanceNumber,
                    'record_title' => '[SIM][' . $siteLabel . '] ' . (string)$template['name'],
                    'field_values' => json_encode($values, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'status' => 'generated',
                    'is_simulation' => 1,
                    'trial_batch' => GovernedTrialAssemblyBlueprintService::TRIAL_BATCH,
                    'generated_html_path' => $filePath,
                    'generated_pdf_path' => null,
                    'generated_pdf_name' => null,
                    'modified' => date('Y-m-d H:i:s'),
                ];
                if (is_array($existing)) {
                    Db::name('record_form_instances')->where('id', (string)$existing['id'])->update($row);
                } else {
                    $row['id'] = qms_uuid();
                    $row['created'] = date('Y-m-d H:i:s');
                    Db::name('record_form_instances')->insert($row);
                }
            }
        }
    }

    private static function sampleValues(array $template, string $site): array
    {
        $canonical = (string)$template['canonical_doc_number'];
        $values = match ((string)$template['origin']) {
            'g2_expansion_batch_1' => G2ExpansionBatch1BlueprintService::sampleValues($canonical, $site),
            'g2_expansion_batch_2' => G2ExpansionBatch2BlueprintService::sampleValues($canonical, $site),
            'g2_expansion_batch_3' => G2ExpansionBatch3BlueprintService::sampleValues($canonical, $site),
            'g2_expansion_batch_4' => G2ExpansionBatch4BlueprintService::sampleValues($canonical, $site),
            default => [],
        };
        $values['usage_site'] = $site;
        $values['test_site'] ??= $site === 'hetian' ? '和田' : '乌鲁木齐';
        foreach ($template['field_schema'] as $field) {
            $key = (string)($field['key'] ?? '');
            if ($key === '' || array_key_exists($key, $values)) {
                continue;
            }
            $values[$key] = self::sampleFieldValue($field, $site);
        }

        return $values;
    }

    private static function sampleFieldValue(array $field, string $site): mixed
    {
        $type = (string)($field['type'] ?? 'text');
        if ($type === 'repeatable_table') {
            $row = [];
            foreach ($field['columns'] ?? [] as $column) {
                $row[(string)$column['key']] = 'SIM-' . ($site === 'hetian' ? 'HT' : 'WLMQ');
            }
            return [$row];
        }
        if ($type === 'date') {
            return '2026-07-24';
        }
        if ($type === 'select' && ($field['options'] ?? []) !== []) {
            return (string)$field['options'][0];
        }
        if ($type === 'textarea') {
            return 'SIM治理试运行填值，仅用于验证字段、流程和打印。';
        }

        return 'SIM-' . ($site === 'hetian' ? '和田' : '乌鲁木齐');
    }

    private static function sampleOutputDir(): string
    {
        $configured = trim((string)getenv('QMS_GOVERNANCE_TRIAL_OUTPUT_DIR'));
        if ($configured !== '') {
            return rtrim($configured, DIRECTORY_SEPARATOR);
        }
        $runtime = function_exists('runtime_path')
            ? rtrim((string)runtime_path(), DIRECTORY_SEPARATOR)
            : dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'runtime';

        return $runtime . DIRECTORY_SEPARATOR . 'governance-trial' . DIRECTORY_SEPARATOR
            . GovernedTrialAssemblyBlueprintService::TRIAL_BATCH;
    }

    private static function manualBlockMarkdown(array $section, array $sourceMap): string
    {
        $sourceLines = [];
        foreach ($section['external_sources'] as $sourceKey) {
            $source = $sourceMap[$sourceKey] ?? null;
            if (is_array($source)) {
                $sourceLines[] = '- ' . (string)$source['source_code'] . '：' . (string)$source['name']
                    . '（SHA-256 ' . (string)$source['sha256'] . '）';
            }
        }

        return '## ' . (string)$section['section_number'] . ' ' . (string)$section['title'] . "\n\n"
            . "治理状态：第五版完整候选正文叠加G1签认差异，仅供隔离试运行。\n\n"
            . "外部依据：\n" . implode("\n", $sourceLines) . "\n\n"
            . '落实程序：' . implode('；', $section['procedure_doc_numbers']) . "\n\n"
            . '运行记录：' . implode('；', $section['record_templates']);
    }

    private static function procedureBlockMarkdown(array $procedure): string
    {
        return '## 运行落实' . "\n\n"
            . '正文来源：' . (string)$procedure['source_file_path'] . "\n\n"
            . "修订方式：2022程序正文叠加G1签认差异，仅供隔离治理试运行。\n\n"
            . '对应手册结构块：' . implode('；', $procedure['manual_sections']) . "\n\n"
            . '直接记录：' . implode('；', $procedure['direct_record_templates']) . "\n\n"
            . '支撑记录：' . implode('；', $procedure['record_templates']);
    }

    private static function evidenceDetails(array $keys, array $sourceMap): array
    {
        $details = [];
        foreach ($keys as $key) {
            $source = $sourceMap[(string)$key] ?? null;
            if (!is_array($source)) {
                continue;
            }
            $details[] = [
                'source_key' => (string)$source['source_key'],
                'relative_path' => (string)$source['relative_path'],
                'sha256' => (string)$source['sha256'],
            ];
        }

        return $details;
    }

    private static function absolutePath(string $relativePath): string
    {
        return rtrim(dirname(__DIR__, 3), DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR
            . ltrim($relativePath, DIRECTORY_SEPARATOR);
    }
}
