<?php
declare(strict_types=1);

namespace app\service;

use app\model\Document;
use app\model\QmsAgentSuggestion;
use app\model\QmsBusinessModule;
use app\model\QmsBusinessModuleElement;
use app\model\QmsClause;
use app\model\QmsClauseText;
use app\model\QmsElement;
use app\model\QmsElementClauseLink;
use app\model\QmsElementDocument;
use app\model\QmsElementResponsibility;
use app\model\QmsManualSection;
use app\model\QmsPosition;
use app\model\QmsSource;
use app\model\QmsStructuredDocument;
use app\model\RecordFormTemplate;
use think\facade\Config;
use think\facade\Db;

class QmsElementService
{
    public static function defaultElementDefinitions(): array
    {
        return [
            ['key' => 'impartiality', 'name' => '公正性', 'element_type' => 'management', 'primary_clause_number' => '4.1', 'sort_order' => 410],
            ['key' => 'confidentiality', 'name' => '保密性', 'element_type' => 'management', 'primary_clause_number' => '4.2', 'sort_order' => 420],
            ['key' => 'structure', 'name' => '组织结构', 'element_type' => 'management', 'primary_clause_number' => '5', 'sort_order' => 500],
            ['key' => 'resources_general', 'name' => '资源总则', 'element_type' => 'management', 'primary_clause_number' => '6.1', 'sort_order' => 610],
            ['key' => 'personnel', 'name' => '人员', 'element_type' => 'technical', 'primary_clause_number' => '6.2', 'sort_order' => 620],
            ['key' => 'facilities_environment', 'name' => '设施和环境条件', 'element_type' => 'technical', 'primary_clause_number' => '6.3', 'sort_order' => 630],
            ['key' => 'equipment', 'name' => '设备', 'element_type' => 'technical', 'primary_clause_number' => '6.4', 'sort_order' => 640],
            ['key' => 'metrological_traceability', 'name' => '计量溯源性', 'element_type' => 'technical', 'primary_clause_number' => '6.5', 'sort_order' => 650],
            ['key' => 'externally_provided_products', 'name' => '外部提供的产品和服务', 'element_type' => 'management', 'primary_clause_number' => '6.6', 'sort_order' => 660],
            ['key' => 'contract_review', 'name' => '要求、标书和合同评审', 'element_type' => 'management', 'primary_clause_number' => '7.1', 'sort_order' => 710],
            ['key' => 'methods', 'name' => '方法选择、验证和确认', 'element_type' => 'technical', 'primary_clause_number' => '7.2', 'sort_order' => 720],
            ['key' => 'sampling', 'name' => '抽样', 'element_type' => 'technical', 'primary_clause_number' => '7.3', 'sort_order' => 730],
            ['key' => 'item_handling', 'name' => '检测和校准物品处置', 'element_type' => 'technical', 'primary_clause_number' => '7.4', 'sort_order' => 740],
            ['key' => 'technical_records', 'name' => '技术记录', 'element_type' => 'technical', 'primary_clause_number' => '7.5', 'sort_order' => 750],
            ['key' => 'measurement_uncertainty', 'name' => '测量不确定度评定', 'element_type' => 'technical', 'primary_clause_number' => '7.6', 'sort_order' => 760],
            ['key' => 'validity_of_results', 'name' => '结果有效性保证', 'element_type' => 'technical', 'primary_clause_number' => '7.7', 'sort_order' => 770],
            ['key' => 'results_reporting', 'name' => '结果报告', 'element_type' => 'technical', 'primary_clause_number' => '7.8', 'sort_order' => 780],
            ['key' => 'complaints', 'name' => '投诉', 'element_type' => 'management', 'primary_clause_number' => '7.9', 'sort_order' => 790],
            ['key' => 'nonconforming_work', 'name' => '不符合工作', 'element_type' => 'management', 'primary_clause_number' => '7.10', 'sort_order' => 800],
            ['key' => 'data_information', 'name' => '数据控制和信息管理', 'element_type' => 'technical', 'primary_clause_number' => '7.11', 'sort_order' => 810],
            ['key' => 'management_system_options', 'name' => '管理体系方式', 'element_type' => 'management', 'primary_clause_number' => '8.1', 'sort_order' => 820],
            ['key' => 'management_system_documents', 'name' => '管理体系文件', 'element_type' => 'management', 'primary_clause_number' => '8.2', 'sort_order' => 830],
            ['key' => 'document_control', 'name' => '管理体系文件控制', 'element_type' => 'management', 'primary_clause_number' => '8.3', 'sort_order' => 840],
            ['key' => 'record_control', 'name' => '记录控制', 'element_type' => 'management', 'primary_clause_number' => '8.4', 'sort_order' => 850],
            ['key' => 'risk_opportunity', 'name' => '风险和机遇', 'element_type' => 'management', 'primary_clause_number' => '8.5', 'sort_order' => 860],
            ['key' => 'improvement', 'name' => '改进', 'element_type' => 'management', 'primary_clause_number' => '8.6', 'sort_order' => 870],
            ['key' => 'corrective_action', 'name' => '纠正措施', 'element_type' => 'management', 'primary_clause_number' => '8.7', 'sort_order' => 880],
            ['key' => 'internal_audit', 'name' => '内部审核', 'element_type' => 'management', 'primary_clause_number' => '8.8', 'sort_order' => 890],
            ['key' => 'management_review', 'name' => '管理评审', 'element_type' => 'management', 'primary_clause_number' => '8.9', 'sort_order' => 900],
        ];
    }

    public static function businessModuleDefinitions(): array
    {
        return [
            ['code' => 'documents', 'name' => '文件控制', 'controller_name' => 'Document', 'url' => '/document/index', 'element_key' => 'document_control'],
            ['code' => 'record_form_templates', 'name' => '记录表格模板', 'controller_name' => 'RecordFormTemplate', 'url' => '/record_form_template/index', 'element_key' => 'record_control'],
            ['code' => 'record_form_instances', 'name' => '记录填写', 'controller_name' => 'RecordFormInstance', 'url' => '/record_form_instance/index', 'element_key' => 'record_control'],
            ['code' => 'results_reporting', 'name' => '结果报告管理', 'controller_name' => 'RecordFormTemplate', 'url' => '/record_form_template/index?keyword=报告', 'element_key' => 'results_reporting'],
            ['code' => 'training_plans', 'name' => '培训计划', 'controller_name' => 'TrainingPlan', 'url' => '/training_plan/index', 'element_key' => 'personnel'],
            ['code' => 'trainings', 'name' => '培训活动', 'controller_name' => 'Training', 'url' => '/training/index', 'element_key' => 'personnel'],
            ['code' => 'training_records', 'name' => '培训记录', 'controller_name' => 'TrainingRecord', 'url' => '/training_record/index', 'element_key' => 'personnel'],
            ['code' => 'competency_records', 'name' => '能力确认', 'controller_name' => 'CompetencyRecord', 'url' => '/competency_record/index', 'element_key' => 'personnel'],
            ['code' => 'employee_certificates', 'name' => '人员资质证书', 'controller_name' => 'EmployeeCertificate', 'url' => '/employee_certificate/index', 'element_key' => 'personnel'],
            ['code' => 'supervision_record_instances', 'name' => '监督记录实例', 'controller_name' => 'RecordFormInstance', 'url' => '/record_form_instance/index?keyword=监督', 'element_key' => 'personnel'],
            ['code' => 'equipment', 'name' => '设备台账', 'controller_name' => 'Equipment', 'url' => '/equipment/index', 'element_key' => 'equipment'],
            ['code' => 'calibrations', 'name' => '校准记录', 'controller_name' => 'Calibration', 'url' => '/calibration/index', 'element_key' => 'metrological_traceability'],
            ['code' => 'equipment_maintenances', 'name' => '设备维护', 'controller_name' => 'EquipmentMaintenance', 'url' => '/equipment_maintenance/index', 'element_key' => 'equipment'],
            ['code' => 'equipment_authorizations', 'name' => '设备授权使用人', 'controller_name' => 'EquipmentAuthorization', 'url' => '/equipment_authorization/index', 'element_key' => 'equipment'],
            ['code' => 'reference_materials', 'name' => '标准物质台账', 'controller_name' => 'ReferenceMaterial', 'url' => '/reference_material/index', 'element_key' => 'metrological_traceability'],
            ['code' => 'audit_plans', 'name' => '内审计划', 'controller_name' => 'AuditPlan', 'url' => '/audit_plan/index', 'element_key' => 'internal_audit'],
            ['code' => 'audit_findings', 'name' => '审核发现', 'controller_name' => 'AuditFinding', 'url' => '/audit_finding/index', 'element_key' => 'internal_audit'],
            ['code' => 'management_reviews', 'name' => '管理评审', 'controller_name' => 'ManagementReview', 'url' => '/management_review/index', 'element_key' => 'management_review'],
            ['code' => 'capas', 'name' => 'CAPA', 'controller_name' => 'Capa', 'url' => '/capa/index', 'element_key' => 'corrective_action'],
            ['code' => 'complaints', 'name' => '客户投诉', 'controller_name' => 'Complaint', 'url' => '/complaint/index', 'element_key' => 'complaints'],
            ['code' => 'nonconformities', 'name' => '不符合工作', 'controller_name' => 'Nonconformity', 'url' => '/nonconformity/index', 'element_key' => 'nonconforming_work'],
            ['code' => 'suppliers', 'name' => '供应商管理', 'controller_name' => 'Supplier', 'url' => '/supplier/index', 'element_key' => 'externally_provided_products'],
        ];
    }

    public static function traceabilityColumnLabels(): array
    {
        return ['要素名称', '外部条款', '手册章节', '程序文件', '记录表格', '运行模块', '岗位职责', '结构块证据', 'schema来源', '智能体建议'];
    }

    public static function seedAll(): array
    {
        return [
            'sources' => self::seedExternalSources(),
            'elements' => self::seedElements(),
            'documents' => self::seedProcedureDocuments(),
            'manual_sections' => self::seedManualSections(),
            'record_forms' => self::linkRecordFormTemplates(),
            'business_modules' => self::seedBusinessModules(),
            'responsibilities' => self::seedResponsibilities(),
            'suggestions' => self::refreshAgentSuggestions(),
        ];
    }

    public static function seedExternalSources(): array
    {
        $summary = ['sources' => 0, 'clauses' => 0];
        foreach (QmsPlanningImportService::officialSourceManifest() as $entry) {
            $source = QmsSource::where('source_code', $entry['source_code'])->where('soft_delete', 0)->find();
            if (!$source) {
                $source = new QmsSource();
                $source->id = qms_uuid();
            }
            $source->save([
                'source_code' => $entry['source_code'],
                'name' => $entry['name'],
                'source_type' => $entry['source_type'],
                'version' => $entry['version'],
                'effective_date' => $entry['effective_date'],
                'attachment_file_path' => $entry['relative_path'],
                'attachment_file_name' => $entry['file_name'],
                'freshness_checked_at' => date('Y-m-d'),
                'freshness_result' => is_file($entry['absolute_path']) ? '本地正式依据文件可用' : '本地正式依据文件缺失',
                'freshness_evidence' => $entry['relative_path'],
                'next_freshness_due' => date('Y-m-d', strtotime('+1 year')),
                'freshness_status' => is_file($entry['absolute_path']) ? 'current' : 'due',
                'status' => 'published',
                'publish' => 1,
                'soft_delete' => 0,
            ]);
            $summary['sources']++;
            $summary['clauses'] += self::upsertExternalClauses($source);
        }

        return $summary;
    }

    public static function externalSourceProcessingRows(): array
    {
        $rows = [];
        foreach (QmsSource::where('soft_delete', 0)->order('source_code', 'asc')->select() as $source) {
            $sourceId = (string)$source->id;
            $clauseIds = QmsClause::where('source_id', $sourceId)->where('soft_delete', 0)->column('id');
            $clauseCount = count($clauseIds);
            $linkedClauseCount = $clauseIds === []
                ? 0
                : Db::table('qms_element_clause_links')
                    ->whereIn('clause_id', $clauseIds)
                    ->where('soft_delete', 0)
                    ->distinct(true)
                    ->count('clause_id');
            $matchedElementCount = $clauseIds === []
                ? 0
                : Db::table('qms_element_clause_links')
                    ->whereIn('clause_id', $clauseIds)
                    ->where('soft_delete', 0)
                    ->distinct(true)
                    ->count('element_id');
            $archivePath = (string)$source->attachment_file_path;
            $archiveExists = $archivePath !== '' && is_file(self::workspacePath($archivePath));
            $structured = QmsStructuredDocument::where('document_role', 'external_basis')
                ->where('doc_number', (string)$source->source_code)
                ->where('version', (string)$source->version)
                ->where('soft_delete', 0)
                ->find();

            $rows[] = [
                'source' => $source,
                'archive_path' => $archivePath,
                'archive_status' => $archiveExists ? 'archived' : 'missing',
                'extraction_status' => $clauseCount > 0 ? 'extracted' : 'pending',
                'structure_status' => $structured ? (string)$structured->render_status : 'pending',
                'structured_document_id' => $structured ? (string)$structured->id : '',
                'structured_document_url' => $structured ? '/planning/structures/view?id=' . (string)$structured->id : '',
                'markdown_path' => $structured ? (string)$structured->markdown_path : '',
                'clause_count' => $clauseCount,
                'matched_clause_count' => $linkedClauseCount,
                'matched_element_count' => $matchedElementCount,
                'unmatched_clause_count' => max(0, $clauseCount - $linkedClauseCount),
                'freshness_evidence' => (string)$source->freshness_evidence,
            ];
        }

        return $rows;
    }

    public static function registerExternalSourceFile(string $sourceFilePath, string $originalName = '', array $overrides = []): array
    {
        if (!is_file($sourceFilePath)) {
            throw new \RuntimeException('外部依据文件不存在：' . $sourceFilePath);
        }

        $originalName = $originalName !== '' ? $originalName : basename($sourceFilePath);
        $parsed = QmsPlanningImportService::parseSourceFilename($originalName);
        $sourceCode = (string)($overrides['source_code'] ?? $parsed['source_code'] ?? '');
        if ($sourceCode === '') {
            $sourceCode = 'EXT-' . strtoupper(substr(sha1($originalName), 0, 10));
        }
        $name = (string)($overrides['name'] ?? $parsed['name'] ?? pathinfo($originalName, PATHINFO_FILENAME));
        $version = (string)($overrides['version'] ?? $parsed['version'] ?? '');
        $sourceType = (string)($overrides['source_type'] ?? $parsed['source_type'] ?? 'external_standard');
        $effectiveDate = (string)($overrides['effective_date'] ?? '');

        $archivePath = self::archiveExternalSourceFile($sourceFilePath, $sourceCode, $name, $version, $originalName);
        $freshness = self::normalizeSourceFreshnessPayload($overrides, [
            'freshness_checked_at' => date('Y-m-d'),
            'freshness_result' => '用户上传文件已规范化归档',
            'freshness_evidence' => $archivePath,
            'next_freshness_due' => date('Y-m-d', strtotime('+1 year')),
            'freshness_status' => 'current',
        ], false);
        $source = QmsSource::where('source_code', $sourceCode)->where('soft_delete', 0)->find();
        if (!$source) {
            $source = new QmsSource();
            $source->id = qms_uuid();
        }
        $source->save([
            'source_code' => $sourceCode,
            'name' => $name !== '' ? $name : $sourceCode,
            'source_type' => $sourceType,
            'version' => $version,
            'effective_date' => $effectiveDate !== '' ? $effectiveDate : null,
            'attachment_file_path' => $archivePath,
            'attachment_file_name' => basename($archivePath),
            'freshness_checked_at' => $freshness['freshness_checked_at'],
            'freshness_result' => $freshness['freshness_result'],
            'freshness_evidence' => $freshness['freshness_evidence'],
            'next_freshness_due' => $freshness['next_freshness_due'],
            'freshness_status' => $freshness['freshness_status'],
            'status' => 'published',
            'publish' => 1,
            'soft_delete' => 0,
        ]);

        $clauses = self::upsertExternalClauses($source);
        $structured = QmsDocumentStructureService::structureExternalBasisSource($source);

        return [
            'source' => $source,
            'archive_path' => $archivePath,
            'clauses' => $clauses,
            'structured_document_id' => (string)$structured['structured_document_id'],
            'structured_rendered_path' => (string)$structured['rendered_file_path'],
        ];
    }

    public static function updateSourceFreshness(string $sourceId, array $payload): array
    {
        $sourceId = trim($sourceId);
        if ($sourceId === '') {
            throw new \RuntimeException('外部依据不存在');
        }

        $source = QmsSource::where('id', $sourceId)->where('soft_delete', 0)->find();
        if (!$source) {
            throw new \RuntimeException('外部依据不存在');
        }

        $freshness = self::normalizeSourceFreshnessPayload($payload, [], true);
        $oldFreshness = [
            'freshness_checked_at' => (string)$source->freshness_checked_at,
            'freshness_result' => (string)$source->freshness_result,
            'freshness_evidence' => (string)$source->freshness_evidence,
            'next_freshness_due' => (string)$source->next_freshness_due,
            'freshness_status' => (string)$source->freshness_status,
        ];
        $source->save($freshness);
        $structureSummary = QmsDocumentStructureService::refreshExternalBasisFreshness(
            $source,
            $oldFreshness,
            $freshness
        );

        return array_merge(['source' => $source], $structureSummary);
    }

    public static function upsertExternalClauses(QmsSource|array $source): int
    {
        $sourceData = $source instanceof QmsSource ? $source->toArray() : $source;
        $sourceId = (string)($sourceData['id'] ?? '');
        if ($sourceId === '') {
            return 0;
        }
        $count = 0;
        foreach (QmsPlanningImportService::buildRegisteredSourceClauseRows($sourceData) as $row) {
            $number = trim((string)($row['clause_number'] ?? ''));
            if ($number === '') {
                continue;
            }
            $clause = QmsClause::where('source_id', $sourceId)->where('clause_number', $number)->where('soft_delete', 0)->find();
            if (!$clause) {
                $clause = new QmsClause();
                $clause->id = qms_uuid();
            }
            $clause->save([
                'source_id' => $sourceId,
                'parent_id' => self::parentClauseId($sourceId, $number),
                'clause_number' => $number,
                'title' => (string)($row['title'] ?? $number),
                'level' => (int)($row['level'] ?? self::clauseLevel($number)),
                'page_number' => $row['page_number'] ?? null,
                'locator' => (string)($row['locator'] ?? ''),
                'applicability' => (string)($row['applicability'] ?? 'applicable'),
                'review_status' => 'published',
                'summary' => (string)($row['summary'] ?? ''),
                'publish' => 1,
                'soft_delete' => 0,
            ]);

            $text = QmsClauseText::where('clause_id', (string)$clause->id)->where('soft_delete', 0)->find();
            if (!$text) {
                $text = new QmsClauseText();
                $text->id = qms_uuid();
            }
            $original = trim((string)($row['original_text'] ?? ''));
            if ($original === '') {
                $original = $number . ' ' . (string)($row['title'] ?? '');
            }
            $text->save([
                'clause_id' => (string)$clause->id,
                'source_id' => $sourceId,
                'clause_number' => $number,
                'original_text' => $original,
                'locator' => (string)($row['locator'] ?? ''),
                'page_number' => $row['page_number'] ?? null,
                'text_hash' => hash('sha256', $original),
                'extraction_method' => (string)($row['extraction_method'] ?? 'registered_source_text'),
                'review_status' => 'published',
                'review_note' => (string)($row['manual_review_note'] ?? ''),
                'publish' => 1,
                'soft_delete' => 0,
            ]);
            $count++;
        }

        return $count;
    }

    public static function seedElements(): array
    {
        $summary = ['elements' => 0, 'primary_links' => 0];
        $gbSource = QmsSource::where('source_code', 'GB/T 27025-2019')->where('soft_delete', 0)->find();
        foreach (self::defaultElementDefinitions() as $definition) {
            $element = self::elementByKey((string)$definition['key']);
            if (!$element) {
                $element = new QmsElement();
                $element->id = qms_uuid();
            }
            $element->save([
                'key' => $definition['key'],
                'name' => $definition['name'],
                'element_type' => $definition['element_type'],
                'applicability' => 'applicable',
                'source_basis' => 'GB/T 27025-2019 structure baseline',
                'summary' => $definition['name'] . '要素，初始化后可独立维护，不反向影响条款库。',
                'status' => 'effective',
                'sort_order' => (int)$definition['sort_order'],
                'publish' => 1,
                'soft_delete' => 0,
            ]);
            $summary['elements']++;
            if ($gbSource) {
                $clause = QmsClause::where('source_id', (string)$gbSource->id)
                    ->where('clause_number', (string)$definition['primary_clause_number'])
                    ->where('soft_delete', 0)
                    ->find();
                if ($clause && self::upsertClauseLink((string)$element->id, (string)$clause->id, 'equivalent', true, '主27025条款，用于排序')) {
                    $summary['primary_links']++;
                }
            }
        }

        self::seedSupplementClauseLinks();

        return $summary;
    }

    public static function seedProcedureDocuments(): array
    {
        $summary = ['documents' => 0, 'links' => 0];
        foreach (QmsPlanningImportService::buildInternalDocumentBaselines() as $row) {
            $document = Document::where('doc_number', (string)$row['doc_number'])->where('soft_delete', 0)->find();
            if (!$document) {
                $document = new Document();
                $document->id = qms_uuid();
            }
            $document->save([
                'level' => (int)$row['document_level'],
                'doc_number' => (string)$row['doc_number'],
                'title' => (string)$row['title'],
                'version' => (string)($row['version'] ?? 'A/0'),
                'status' => 'published',
                'file_path' => (string)($row['file_path'] ?? ''),
                'file_name' => (string)($row['file_name'] ?? ''),
                'file_type' => (string)($row['file_type'] ?? ''),
                'publish' => 1,
                'soft_delete' => 0,
            ]);
            $summary['documents']++;
            if (!in_array((int)$row['document_level'], [2, 3], true)) {
                continue;
            }
            foreach (self::matchingElementsForProcedure((string)$row['title']) as $element) {
                if (self::upsertElementDocument((string)$element->id, (string)$document->id, 'primary', '按程序文件标题关键词匹配')) {
                    $summary['links']++;
                }
            }
        }

        return $summary;
    }

    public static function seedManualSections(): array
    {
        $manual = Document::whereIn('doc_number', ['XZTC/SC', 'QM-04'])->where('soft_delete', 0)->orderRaw("FIELD(doc_number,'XZTC/SC','QM-04')")->find();
        if (!$manual) {
            self::seedProcedureDocuments();
            $manual = Document::whereIn('doc_number', ['XZTC/SC', 'QM-04'])->where('soft_delete', 0)->orderRaw("FIELD(doc_number,'XZTC/SC','QM-04')")->find();
        }
        $summary = ['manual_sections' => 0];
        foreach (self::defaultElementDefinitions() as $definition) {
            $element = self::elementByKey((string)$definition['key']);
            if (!$element) {
                continue;
            }
            $sectionNumber = (string)$definition['primary_clause_number'];
            $section = QmsManualSection::where('document_id', $manual ? (string)$manual->id : '')
                ->where('section_number', $sectionNumber)
                ->where('soft_delete', 0)
                ->find();
            if (!$section) {
                $section = new QmsManualSection();
                $section->id = qms_uuid();
            }
            $section->save([
                'document_id' => $manual ? (string)$manual->id : null,
                'element_id' => (string)$element->id,
                'section_number' => $sectionNumber,
                'title' => (string)$definition['name'],
                'level' => self::clauseLevel($sectionNumber),
                'summary' => '质量手册沿用 27025 结构的内部章节落点。',
                'status' => 'effective',
                'publish' => 1,
                'soft_delete' => 0,
            ]);
            $summary['manual_sections']++;
        }

        return $summary;
    }

    public static function linkRecordFormTemplates(): array
    {
        $summary = ['linked' => 0, 'unlinked' => 0];
        foreach (RecordFormTemplate::where('soft_delete', 0)->select() as $template) {
            $procedure = self::procedureForRecordForm((string)$template->module);
            $element = self::elementForRecordForm((string)$template->module, (string)$template->name, (string)$template->doc_number);
            if (!$element && $procedure) {
                $element = self::primaryElementForProcedure((string)$procedure->id);
            }
            if (!$element) {
                $summary['unlinked']++;
                continue;
            }
            $procedure = $procedure ?: self::procedureForElement((string)$element->id);
            $template->save([
                'element_id' => (string)$element->id,
                'procedure_doc_id' => $procedure ? (string)$procedure->id : null,
            ]);
            $summary['linked']++;
        }

        return $summary;
    }

    public static function seedBusinessModules(): array
    {
        $summary = ['modules' => 0, 'links' => 0];
        foreach (self::businessModuleDefinitions() as $definition) {
            $element = self::elementByKey((string)$definition['element_key']);
            $module = QmsBusinessModule::where('code', (string)$definition['code'])->where('soft_delete', 0)->find();
            if (!$module) {
                $module = new QmsBusinessModule();
                $module->id = qms_uuid();
            }
            $module->save([
                'code' => $definition['code'],
                'name' => $definition['name'],
                'controller_name' => $definition['controller_name'],
                'primary_element_id' => $element ? (string)$element->id : null,
                'url' => $definition['url'],
                'description' => $definition['name'] . '运行模块，作为体系运行证据入口。',
                'publish' => 1,
                'soft_delete' => 0,
            ]);
            $summary['modules']++;
            if ($element && self::upsertModuleElement((string)$module->id, (string)$element->id, 'primary')) {
                $summary['links']++;
            }
        }

        return $summary;
    }

    public static function businessModuleOptionsForElement(string $elementId): array
    {
        $elementId = trim($elementId);
        $currentLinks = [];
        if ($elementId !== '') {
            foreach (QmsBusinessModuleElement::where('element_id', $elementId)->where('soft_delete', 0)->select() as $link) {
                $currentLinks[(string)$link->module_id] = (string)$link->relation_type;
            }
        }

        $rows = [];
        foreach (QmsBusinessModule::where('soft_delete', 0)->order('code', 'asc')->select() as $module) {
            $moduleId = (string)$module->id;
            $relationType = $currentLinks[$moduleId] ?? '';
            $rows[] = [
                'id' => $moduleId,
                'code' => (string)$module->code,
                'name' => (string)$module->name,
                'url' => (string)$module->url,
                'primary_element_id' => (string)$module->primary_element_id,
                'mapped' => $relationType !== '',
                'relation_type' => $relationType,
                'relation_label' => self::moduleRelationLabel($relationType),
                'is_primary_owner' => (string)$module->primary_element_id === $elementId,
            ];
        }

        return $rows;
    }

    public static function mapBusinessModuleToElement(
        string $moduleId,
        string $elementId,
        string $relationType = 'supporting',
        string $note = ''
    ): array {
        $moduleId = trim($moduleId);
        $elementId = trim($elementId);
        $relationType = trim($relationType) === 'primary' ? 'primary' : 'supporting';

        $module = $moduleId === '' ? null : QmsBusinessModule::where('soft_delete', 0)->find($moduleId);
        if (!$module) {
            throw new \InvalidArgumentException('运行模块不存在或已停用。');
        }
        $element = $elementId === '' ? null : QmsElement::where('soft_delete', 0)->find($elementId);
        if (!$element) {
            throw new \InvalidArgumentException('体系要素不存在或已停用。');
        }

        if ((string)$module->primary_element_id === $elementId) {
            $relationType = 'primary';
        }
        if ($relationType === 'primary') {
            QmsBusinessModuleElement::where('module_id', $moduleId)
                ->where('element_id', '<>', $elementId)
                ->where('relation_type', 'primary')
                ->where('soft_delete', 0)
                ->update(['relation_type' => 'supporting']);
            $module->save(['primary_element_id' => $elementId]);
        }

        self::upsertModuleElement($moduleId, $elementId, $relationType, $note !== '' ? $note : '人工复核后建立的运行模块要素映射。');

        $row = Db::table('qms_business_module_elements')
            ->alias('l')
            ->join('qms_business_modules m', 'm.id = l.module_id')
            ->join('qms_elements e', 'e.id = l.element_id')
            ->where('l.module_id', $moduleId)
            ->where('l.element_id', $elementId)
            ->where('l.soft_delete', 0)
            ->field('l.id,l.module_id,l.element_id,l.relation_type,l.note,m.code module_code,m.name module_name,e.name element_name')
            ->find();

        return is_array($row) ? $row : [];
    }

    public static function seedResponsibilities(): array
    {
        $baseline = QmsPlanningImportService::extractCurrentManualBaseline();
        $summary = ['positions' => 0, 'responsibilities' => 0];
        foreach ((array)($baseline['positions'] ?? []) as $row) {
            $position = QmsPosition::where('code', (string)$row['code'])->where('soft_delete', 0)->find();
            if (!$position) {
                $position = new QmsPosition();
                $position->id = qms_uuid();
            }
            $position->save([
                'code' => (string)$row['code'],
                'name' => (string)$row['name'],
                'source' => (string)($row['source'] ?? 'current_quality_manual_appendix'),
                'review_status' => 'published',
                'publish' => 1,
                'soft_delete' => 0,
            ]);
            $summary['positions']++;
        }

        foreach ((array)($baseline['responsibility_matrix'] ?? []) as $row) {
            $element = self::elementByPrimaryClause((string)$row['clause_number']);
            $position = QmsPosition::where('code', (string)$row['position_code'])->where('soft_delete', 0)->find();
            if (!$element || !$position) {
                continue;
            }
            $responsibility = QmsElementResponsibility::where('element_id', (string)$element->id)
                ->where('position_id', (string)$position->id)
                ->where('responsibility_type', (string)$row['responsibility_type'])
                ->where('soft_delete', 0)
                ->find();
            if (!$responsibility) {
                $responsibility = new QmsElementResponsibility();
                $responsibility->id = qms_uuid();
            }
            $responsibility->save([
                'element_id' => (string)$element->id,
                'position_id' => (string)$position->id,
                'responsibility_type' => (string)$row['responsibility_type'],
                'note' => '来自现用质量手册职责矩阵符号 ' . (string)($row['raw_symbol'] ?? ''),
                'publish' => 1,
                'soft_delete' => 0,
            ]);
            $summary['responsibilities']++;
        }
        $summary['responsibilities'] += self::seedFallbackResponsibilities();

        return $summary;
    }

    public static function coverageStats(): array
    {
        $rows = [];
        foreach (self::orderedElements() as $element) {
            $row = self::coverageRow($element);
            $rows[] = $row;
        }

        return $rows;
    }

    public static function elementDetail(string $elementId): array
    {
        $element = QmsElement::where('soft_delete', 0)->find($elementId);
        if (!$element) {
            return [];
        }

        return [
            'element' => $element,
            'coverage' => self::coverageRow($element),
            'clauses' => self::elementClauses($elementId),
            'manual_sections' => QmsManualSection::where('element_id', $elementId)->where('soft_delete', 0)->order('section_number', 'asc')->select(),
            'documents' => self::elementDocuments($elementId),
            'record_forms' => RecordFormTemplate::where('element_id', $elementId)->where('soft_delete', 0)->order('doc_number', 'asc')->select(),
            'business_modules' => self::elementModules($elementId),
            'responsibilities' => self::elementResponsibilities($elementId),
            'structured_block_evidence' => self::elementStructuredBlockEvidence($element),
            'runtime_evidence' => self::elementRuntimeEvidence($element),
            'suggestions' => QmsAgentSuggestion::where('element_id', $elementId)->where('status', 'open')->select(),
        ];
    }

    public static function traceabilityMatrix(): array
    {
        return self::coverageStats();
    }

    public static function clauseStructuredBlockEvidence(string $clauseId): array
    {
        $rowsById = [];
        foreach (self::structuredBlockEvidenceBaseQuery()
            ->where('l.clause_id', $clauseId)
            ->select()
            ->toArray() as $row) {
            $id = (string)($row['id'] ?? '');
            if ($id !== '') {
                $rowsById[$id] = self::enrichStructuredBlockEvidenceRow($row, '直接条款映射', 0);
            }
        }

        $mappedElements = Db::name('qms_element_clause_links')
            ->alias('l')
            ->join('qms_elements e', 'e.id = l.element_id')
            ->where('l.clause_id', $clauseId)
            ->where('l.soft_delete', 0)
            ->where('e.soft_delete', 0)
            ->field('l.element_id,e.name element_name')
            ->select()
            ->toArray();
        foreach ($mappedElements as $mapping) {
            $elementId = (string)($mapping['element_id'] ?? '');
            $elementName = (string)($mapping['element_name'] ?? '');
            if ($elementId === '') {
                continue;
            }

            $direct = self::directCoverageIdsForElement($elementId);
            foreach (self::structuredBlockTraceRows($elementId, $direct) as $row) {
                $id = (string)($row['id'] ?? '');
                if ($id === '' || isset($rowsById[$id])) {
                    continue;
                }
                $pathName = $elementName !== '' ? $elementName : (string)($row['element_name'] ?? '');
                $rowsById[$id] = self::enrichStructuredBlockEvidenceRow(
                    $row,
                    '经要素映射：' . $pathName,
                    1,
                    $elementId,
                    $pathName
                );
            }
        }

        $rows = array_values($rowsById);
        self::sortStructuredBlockEvidenceRows($rows);

        return $rows;
    }

    public static function managementReviewMetrics(): array
    {
        $rows = self::coverageStats();
        $total = count($rows);
        $complete = count(array_filter($rows, static fn (array $row): bool => $row['gap_count'] === 0));

        return [
            'planning_elements_total' => $total,
            'planning_elements_complete' => $complete,
            'planning_traceability_gaps' => array_sum(array_column($rows, 'gap_count')),
            'planning_sources_due' => QmsSource::where('freshness_status', 'due')->where('soft_delete', 0)->count(),
        ];
    }

    public static function refreshAgentSuggestions(): array
    {
        $coverageSuggestions = 0;
        foreach (self::coverageStats() as $row) {
            if ($row['gap_count'] === 0) {
                continue;
            }
            $title = '补齐' . (string)$row['element']->name . '追溯缺口';
            $content = implode('、', $row['gap_labels']);
            if (self::hasReviewedSuggestion(
                'gap',
                $title,
                (string)$row['element']->id,
                $content
            )) {
                continue;
            }
            $suggestion = QmsAgentSuggestion::where('element_id', (string)$row['element']->id)
                ->where('suggestion_type', 'gap')
                ->where('status', 'open')
                ->find();
            if (!$suggestion) {
                $suggestion = new QmsAgentSuggestion();
                $suggestion->id = qms_uuid();
            }
            $suggestion->save([
                'element_id' => (string)$row['element']->id,
                'suggestion_type' => 'gap',
                'title' => $title,
                'content' => $content,
                'evidence' => '由追溯矩阵覆盖率自动生成，需人工采纳后修改正式体系数据。',
                'status' => 'open',
            ]);
            $coverageSuggestions++;
        }

        $unmatchedClauseSuggestions = self::refreshUnmatchedClauseSuggestions();
        $procedureRecordGapSuggestions = self::refreshProcedureRecordRequirementSuggestions();
        $recordSchemaGapSuggestions = self::refreshRecordSchemaGapSuggestions();

        return [
            'created_or_refreshed' => $coverageSuggestions + $unmatchedClauseSuggestions + $procedureRecordGapSuggestions + $recordSchemaGapSuggestions,
            'coverage_gap_suggestions' => $coverageSuggestions,
            'unmatched_clause_suggestions' => $unmatchedClauseSuggestions,
            'procedure_record_gap_suggestions' => $procedureRecordGapSuggestions,
            'record_schema_gap_suggestions' => $recordSchemaGapSuggestions,
        ];
    }

    public static function openClauseMappingSuggestions(int $limit = 12): array
    {
        return Db::table('qms_agent_suggestions')
            ->whereNull('element_id')
            ->where('suggestion_type', 'mapping')
            ->where('status', 'open')
            ->order('created', 'desc')
            ->limit($limit)
            ->select()
            ->toArray();
    }

    public static function openProcedureRecordSuggestions(int $limit = 12): array
    {
        return Db::table('qms_agent_suggestions')
            ->whereNull('element_id')
            ->where('suggestion_type', 'document')
            ->whereLike('title', '复核程序记录要求：%')
            ->where('status', 'open')
            ->order('created', 'desc')
            ->limit($limit)
            ->select()
            ->toArray();
    }

    public static function openRecordSchemaSuggestions(int $limit = 12): array
    {
        $rows = Db::table('qms_agent_suggestions')
            ->whereNull('element_id')
            ->where('suggestion_type', 'record')
            ->whereLike('title', '复核记录表格schema：%')
            ->where('status', 'open')
            ->order('created', 'desc')
            ->limit($limit)
            ->select()
            ->toArray();

        return array_map(static function (array $row): array {
            $row['record_form_edit_url'] = self::usableRecordFormEditUrl(
                self::evidenceUrlAfterLabel((string)($row['evidence'] ?? ''), '记录表格编辑')
            );
            if ($row['record_form_edit_url'] !== '') {
                $separator = str_contains($row['record_form_edit_url'], '?') ? '&' : '?';
                $row['record_form_edit_url'] .= $separator . 'schema_suggestion_id=' . rawurlencode((string)($row['id'] ?? ''));
            }

            return $row;
        }, $rows);
    }

    public static function recordSchemaDraftSaved(string $templateId, string $blockId, string $suggestionId = ''): array
    {
        $templateId = trim($templateId);
        $blockId = trim($blockId);
        $suggestionId = trim($suggestionId);
        if ($templateId === '' || $blockId === '') {
            throw new \RuntimeException('记录表格或记录要求块参数缺失');
        }

        return Db::transaction(function () use ($templateId, $blockId, $suggestionId) {
            $trace = QmsDocumentStructureService::markRecordFormSchemaDraftApplied($templateId, $blockId, $suggestionId);
            $suggestionRow = [];
            if ($suggestionId !== '') {
                $suggestion = QmsAgentSuggestion::where('id', $suggestionId)
                    ->where('suggestion_type', 'record')
                    ->find();
                if ($suggestion && (string)$suggestion->status === 'open') {
                    $suggestion->save([
                        'status' => 'accepted',
                        'review_note' => '已根据候选schema草稿保存记录表格字段配置，并保留来源追溯。',
                    ]);
                    $suggestionRow = QmsAgentSuggestion::where('id', $suggestionId)->find()?->toArray() ?? [];
                }
            }

            return array_merge($trace, [
                'suggestion' => $suggestionRow,
            ]);
        });
    }

    public static function reviewRecordSchemaDraftFields(string $templateId, string $blockId, array $fieldReviews, string $suggestionId = ''): array
    {
        $templateId = trim($templateId);
        $blockId = trim($blockId);
        $suggestionId = trim($suggestionId);
        if ($templateId === '' || $blockId === '') {
            throw new \RuntimeException('记录表格或记录要求块参数缺失');
        }

        $normalizedReviews = self::normalizeRecordSchemaFieldReviews($fieldReviews);

        return Db::transaction(static function () use ($templateId, $blockId, $normalizedReviews, $suggestionId): array {
            return QmsDocumentStructureService::markRecordSchemaDraftFieldReview($templateId, $blockId, $normalizedReviews, $suggestionId);
        });
    }

    public static function reviewAgentSuggestion(string $suggestionId, string $status, string $reviewNote): array
    {
        $suggestionId = trim($suggestionId);
        $status = trim($status);
        $reviewNote = trim($reviewNote);
        if (!in_array($status, ['accepted', 'rejected'], true)) {
            throw new \RuntimeException('建议处理状态无效');
        }
        if ($reviewNote === '') {
            throw new \RuntimeException('请填写人工复核说明');
        }

        $suggestion = QmsAgentSuggestion::where('id', $suggestionId)->find();
        if (!$suggestion) {
            throw new \RuntimeException('智能体建议不存在');
        }

        $suggestion->save([
            'status' => $status,
            'review_note' => $reviewNote,
        ]);

        return QmsAgentSuggestion::where('id', $suggestionId)->find()?->toArray() ?? [];
    }

    private static function normalizeRecordSchemaFieldReviews(array $fieldReviews): array
    {
        $statusLabels = [
            'accepted' => '采纳',
            'pending' => '暂缓',
            'rejected' => '不采用',
        ];
        $normalized = [];
        foreach ($fieldReviews as $fieldKey => $review) {
            if (!is_array($review)) {
                continue;
            }
            $fieldKey = trim((string)$fieldKey);
            if ($fieldKey === '') {
                continue;
            }
            if (preg_match('/\A[a-zA-Z0-9_.-]+\z/', $fieldKey) !== 1) {
                throw new \RuntimeException('字段标识只能包含字母、数字、下划线、点和短横线');
            }
            $status = trim((string)($review['status'] ?? ''));
            if (!array_key_exists($status, $statusLabels)) {
                throw new \RuntimeException('字段复核状态无效');
            }
            $normalized[] = [
                'field_key' => $fieldKey,
                'status' => $status,
                'status_label' => $statusLabels[$status],
                'note' => trim((string)($review['note'] ?? '')),
            ];
        }

        if ($normalized === []) {
            throw new \RuntimeException('请至少提交一项字段复核意见');
        }

        return $normalized;
    }

    public static function mapClauseToElement(string $clauseId, string $elementId, string $mappingType = 'supplement', string $note = ''): array
    {
        $clause = self::requireActiveClause($clauseId);
        $element = self::requireActiveElement($elementId);
        $mappingType = self::normalizeClauseMappingType($mappingType);
        $note = trim($note);
        if ($note === '') {
            throw new \RuntimeException('请填写人工映射说明');
        }

        return Db::transaction(function () use ($clause, $element, $mappingType, $note) {
            self::upsertClauseLink((string)$element->id, (string)$clause->id, $mappingType, false, $note);

            return self::clauseLinkRow((string)$clause->id, (string)$element->id);
        });
    }

    public static function createLocalSupplementElementForClause(string $clauseId, array $payload): array
    {
        $clause = self::requireActiveClause($clauseId);
        $name = trim((string)($payload['name'] ?? ''));
        if ($name === '') {
            throw new \RuntimeException('请填写本地补充要素名称');
        }
        if (preg_match('/^[0-9]+(\.[0-9]+)*/', $name) === 1) {
            throw new \RuntimeException('要素名称不能使用条款编号');
        }

        $elementType = trim((string)($payload['element_type'] ?? 'management'));
        if (!in_array($elementType, ['management', 'technical'], true)) {
            throw new \RuntimeException('要素类型无效');
        }

        $note = trim((string)($payload['note'] ?? ''));
        if ($note === '') {
            throw new \RuntimeException('请填写新增本地补充要素说明');
        }

        $key = self::localSupplementElementKey((string)$clause->id);
        $summary = trim((string)($payload['summary'] ?? ''));
        $sortOrder = (int)($payload['sort_order'] ?? 9900);
        $sourceLabel = self::clauseSourceLabel($clause);

        return Db::transaction(function () use ($clause, $key, $name, $elementType, $summary, $sortOrder, $sourceLabel, $note) {
            $element = QmsElement::where('key', $key)->find();
            if (!$element) {
                $element = new QmsElement();
                $element->id = qms_uuid();
            }

            $element->save([
                'key' => $key,
                'name' => $name,
                'parent_id' => null,
                'element_type' => $elementType,
                'applicability' => 'applicable',
                'source_basis' => $sourceLabel,
                'summary' => $summary !== ''
                    ? $summary
                    : '由外部补充条款人工确认为本地补充要素：' . $sourceLabel,
                'status' => 'under_review',
                'sort_order' => $sortOrder,
                'publish' => 1,
                'soft_delete' => 0,
            ]);

            self::upsertClauseLink((string)$element->id, (string)$clause->id, 'supplement', false, $note);

            return [
                'element' => QmsElement::where('id', (string)$element->id)->find()?->toArray() ?? [],
                'link' => self::clauseLinkRow((string)$clause->id, (string)$element->id),
            ];
        });
    }

    private static function requireActiveClause(string $clauseId): QmsClause
    {
        $clauseId = trim($clauseId);
        if ($clauseId === '') {
            throw new \RuntimeException('请选择条款');
        }

        $clause = QmsClause::where('id', $clauseId)->where('soft_delete', 0)->find();
        if (!$clause) {
            throw new \RuntimeException('条款不存在');
        }

        return $clause;
    }

    private static function requireActiveElement(string $elementId): QmsElement
    {
        $elementId = trim($elementId);
        if ($elementId === '') {
            throw new \RuntimeException('请选择要素');
        }

        $element = QmsElement::where('id', $elementId)->where('soft_delete', 0)->find();
        if (!$element) {
            throw new \RuntimeException('体系要素不存在');
        }

        return $element;
    }

    private static function normalizeClauseMappingType(string $mappingType): string
    {
        $mappingType = trim($mappingType);
        if ($mappingType === '') {
            $mappingType = 'supplement';
        }
        if (!in_array($mappingType, ['equivalent', 'partial', 'supplement', 'reference'], true)) {
            throw new \RuntimeException('映射类型无效');
        }

        return $mappingType;
    }

    private static function localSupplementElementKey(string $clauseId): string
    {
        return 'local_supplement_' . substr(sha1($clauseId), 0, 12);
    }

    private static function clauseSourceLabel(QmsClause $clause): string
    {
        $source = QmsSource::where('id', (string)$clause->source_id)->where('soft_delete', 0)->find();
        $sourceLabel = $source ? (string)$source->source_code : (string)$clause->source_id;

        return trim($sourceLabel . ' ' . (string)$clause->clause_number . ' ' . (string)$clause->title);
    }

    private static function clauseLinkRow(string $clauseId, string $elementId): array
    {
        return Db::table('qms_element_clause_links')
            ->where('clause_id', $clauseId)
            ->where('element_id', $elementId)
            ->where('soft_delete', 0)
            ->find() ?: [];
    }

    private static function upsertClauseLink(string $elementId, string $clauseId, string $type, bool $primary, string $note = ''): bool
    {
        $link = QmsElementClauseLink::where('element_id', $elementId)->where('clause_id', $clauseId)->where('soft_delete', 0)->find();
        $isNew = !$link;
        if (!$link) {
            $link = new QmsElementClauseLink();
            $link->id = qms_uuid();
        }
        $link->save([
            'element_id' => $elementId,
            'clause_id' => $clauseId,
            'mapping_type' => $type,
            'is_primary' => $primary ? 1 : 0,
            'note' => $note,
            'publish' => 1,
            'soft_delete' => 0,
        ]);

        return $isNew;
    }

    private static function refreshUnmatchedClauseSuggestions(): int
    {
        $count = 0;
        $rows = Db::table('qms_clauses')
            ->alias('c')
            ->join('qms_sources s', 's.id = c.source_id')
            ->leftJoin('qms_element_clause_links l', 'l.clause_id = c.id AND l.soft_delete = 0')
            ->where('c.soft_delete', 0)
            ->whereNull('l.id')
            ->field('c.id,c.clause_number,c.title,s.source_code,s.name source_name')
            ->order('s.source_code', 'asc')
            ->order('c.clause_number', 'asc')
            ->select();

        foreach ($rows as $row) {
            $sourceCode = (string)$row['source_code'];
            $clauseNumber = (string)$row['clause_number'];
            $title = '评估未匹配条款：' . $sourceCode . ' ' . $clauseNumber;
            if (self::hasReviewedSuggestion('mapping', $title, null)) {
                continue;
            }
            $suggestion = QmsAgentSuggestion::where('title', $title)
                ->where('suggestion_type', 'mapping')
                ->where('status', 'open')
                ->find();
            if (!$suggestion) {
                $suggestion = new QmsAgentSuggestion();
                $suggestion->id = qms_uuid();
            }

            $suggestion->save([
                'element_id' => null,
                'suggestion_type' => 'mapping',
                'title' => $title,
                'content' => '条款“' . $clauseNumber . ' ' . (string)$row['title'] . '”尚未映射到无编号要素。建议人工判断：映射到已有要素，或作为本地补充要素候选。',
                'evidence' => '来源：' . $sourceCode . ' ' . (string)$row['source_name']
                    . '；条款ID：' . (string)$row['id']
                    . '。智能体只记录建议/缺口，不自动修改正式体系数据。',
                'status' => 'open',
            ]);
            $count++;
        }

        return $count;
    }

    private static function refreshProcedureRecordRequirementSuggestions(): int
    {
        $count = 0;
        $coverage = QmsDocumentStructureService::procedureRecordRequirementCoverage();
        foreach ($coverage['gap_rows'] ?? [] as $row) {
            $docNumber = trim((string)($row['doc_number'] ?? ''));
            if ($docNumber === '') {
                continue;
            }
            $title = '复核程序记录要求：' . $docNumber;
            $content = '程序文件“' . $docNumber . ' ' . trim((string)($row['title'] ?? '')) . '”存在记录要求覆盖缺口：'
                . (string)($row['gap_text'] ?? '未说明')
                . '。建议人工复核程序正文，确认是否需要新增/修订记录要求块、关联记录表格或补建schema文档。';
            if (self::hasReviewedSuggestion('document', $title, null, $content)) {
                continue;
            }

            $structureUrl = (string)($row['structure_url'] ?? '');
            $evidence = '结构化文件：' . ($structureUrl !== '' ? $structureUrl : '-')
                . '；记录要求块：' . (int)($row['record_requirement_blocks'] ?? 0)
                . '；记录表格：' . (int)($row['linked_record_forms'] ?? 0)
                . '；schema文档：' . (int)($row['record_form_schema_documents'] ?? 0)
                . '。智能体只记录建议/缺口，不自动修改正式体系数据。';

            $suggestion = QmsAgentSuggestion::where('title', $title)
                ->where('suggestion_type', 'document')
                ->where('status', 'open')
                ->find();
            if (!$suggestion) {
                $suggestion = new QmsAgentSuggestion();
                $suggestion->id = qms_uuid();
            }

            $suggestion->save([
                'element_id' => null,
                'suggestion_type' => 'document',
                'title' => $title,
                'content' => $content,
                'evidence' => $evidence,
                'status' => 'open',
            ]);
            $count++;
        }

        return $count;
    }

    private static function refreshRecordSchemaGapSuggestions(): int
    {
        $count = 0;
        $coverage = QmsDocumentStructureService::recordRequirementSchemaCoverage();
        foreach ($coverage['gap_rows'] ?? [] as $row) {
            $docNumber = trim((string)($row['doc_number'] ?? ''));
            $blockTitle = trim((string)($row['block_section_number'] ?? '') . ' ' . (string)($row['block_title'] ?? ''));
            if ($docNumber === '') {
                continue;
            }
            $title = '复核记录表格schema：' . trim($docNumber . ' ' . $blockTitle);
            $schemaDraft = QmsDocumentStructureService::recordRequirementSchemaDraftForBlock((string)($row['block_id'] ?? ''));
            $schemaDraftJson = $schemaDraft === []
                ? '[]'
                : (json_encode($schemaDraft, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) ?: '[]');
            $content = '程序文件“' . $docNumber . ' ' . trim((string)($row['document_title'] ?? '')) . '”的记录要求块“'
                . ($blockTitle !== '' ? $blockTitle : '记录要求')
                . '”存在记录表格schema缺口：' . (string)($row['gap_text'] ?? '未说明')
                . "。建议人工根据程序记录要求补建/修订字段schema、确认schema文档，并复核记录表格关联。\n\n"
                . "候选schema草稿（仅供人工复核，不自动写入正式记录表格）：\n```json\n"
                . $schemaDraftJson
                . "\n```";
            if (self::hasReviewedSuggestion('record', $title, null, $content)) {
                continue;
            }

            $evidence = '结构化文件：' . (string)($row['structure_url'] ?? '-')
                . '；追溯复核：' . (string)($row['trace_review_url'] ?? '-')
                . '；记录表格：' . (string)($row['record_form_labels'] ?? '-')
                . '；记录表格编辑：' . (string)($row['record_form_edit_urls'] ?? '-')
                . '；schema文档：' . (int)($row['schema_documents'] ?? 0)
                . '；字段schema：' . (int)($row['schema_field_count'] ?? 0)
                . '。智能体只记录建议/缺口，不自动修改正式体系数据。';

            $suggestion = QmsAgentSuggestion::where('title', $title)
                ->where('suggestion_type', 'record')
                ->where('status', 'open')
                ->find();
            if (!$suggestion) {
                $suggestion = new QmsAgentSuggestion();
                $suggestion->id = qms_uuid();
            }

            $suggestion->save([
                'element_id' => null,
                'suggestion_type' => 'record',
                'title' => $title,
                'content' => $content,
                'evidence' => $evidence,
                'status' => 'open',
            ]);
            $count++;
        }

        return $count;
    }

    private static function hasReviewedSuggestion(string $suggestionType, string $title, ?string $elementId = null, string $content = ''): bool
    {
        $query = QmsAgentSuggestion::where('suggestion_type', $suggestionType)
            ->where('title', $title)
            ->whereIn('status', ['accepted', 'rejected']);
        if ($elementId === null) {
            $query->whereNull('element_id');
        } else {
            $query->where('element_id', $elementId);
        }
        if ($content !== '') {
            $query->where('content', $content);
        }

        return (bool)$query->find();
    }

    private static function evidenceUrlAfterLabel(string $evidence, string $label): string
    {
        if ($evidence === '' || $label === '') {
            return '';
        }
        if (preg_match('/' . preg_quote($label, '/') . '：([^；。\\s，]+)/u', $evidence, $match) !== 1) {
            return '';
        }

        $url = trim((string)$match[1]);
        return str_starts_with($url, '/') ? $url : '';
    }

    private static function usableRecordFormEditUrl(string $url): string
    {
        if ($url === '') {
            return '';
        }

        $path = (string)(parse_url($url, PHP_URL_PATH) ?: '');
        if ($path !== '/record_form_template/edit') {
            return '';
        }

        parse_str((string)(parse_url($url, PHP_URL_QUERY) ?: ''), $query);
        $templateId = trim((string)($query['id'] ?? ''));
        $blockId = trim((string)($query['schema_draft_block_id'] ?? ''));
        if ($templateId === '' || $blockId === '') {
            return '';
        }

        $templateExists = Db::name('record_form_templates')
            ->where('id', $templateId)
            ->where('soft_delete', 0)
            ->count() === 1;
        if (!$templateExists) {
            return '';
        }

        $blockExists = Db::name('qms_document_blocks')
            ->where('id', $blockId)
            ->where('soft_delete', 0)
            ->count() === 1;

        return $blockExists ? $url : '';
    }

    private static function seedSupplementClauseLinks(): void
    {
        $sourceMap = [];
        foreach (QmsSource::where('soft_delete', 0)->select() as $source) {
            $sourceMap[(string)$source->source_code] = (string)$source->id;
        }
        foreach (QmsPlanningImportService::extractCurrentManualBaseline()['manual_section_clause_mappings'] ?? [] as $mapping) {
            $element = self::elementByPrimaryClause((string)($mapping['section_number'] ?? ''));
            $sourceId = $sourceMap[(string)($mapping['source_code'] ?? '')] ?? '';
            if (!$element || $sourceId === '') {
                continue;
            }
            $clause = QmsClause::where('source_id', $sourceId)
                ->where('clause_number', (string)$mapping['clause_number'])
                ->where('soft_delete', 0)
                ->find();
            if (!$clause) {
                continue;
            }
            $isPrimary = (string)$mapping['source_code'] === 'GB/T 27025-2019';
            self::upsertClauseLink(
                (string)$element->id,
                (string)$clause->id,
                (string)($mapping['mapping_basis'] ?? 'equivalent'),
                $isPrimary,
                (string)($mapping['mapping_source'] ?? '')
            );
        }

        self::seedSameNumberSupplementClauseLinks($sourceMap);
    }

    private static function seedSameNumberSupplementClauseLinks(array $sourceMap): void
    {
        foreach (['CNAS-CL01-G001:2024'] as $sourceCode) {
            $sourceId = $sourceMap[$sourceCode] ?? '';
            if ($sourceId === '') {
                continue;
            }

            foreach (QmsClause::where('source_id', $sourceId)->where('soft_delete', 0)->select() as $clause) {
                $element = self::elementByClauseNumberPrefix((string)$clause->clause_number);
                if (!$element) {
                    continue;
                }
                self::upsertClauseLink(
                    (string)$element->id,
                    (string)$clause->id,
                    'supplement',
                    false,
                    $sourceCode . ' 同号补充要求，按27025主条款归集到无编号要素'
                );
            }
        }
    }

    private static function coverageRow(QmsElement $element): array
    {
        $coverageIds = self::coverageIdsForElement($element);
        $counts = [
            'clause_count' => count($coverageIds['clause_ids']),
            'manual_section_count' => count($coverageIds['manual_section_ids']),
            'document_count' => count($coverageIds['procedure_document_ids']),
            'record_form_count' => count($coverageIds['record_form_template_ids']),
            'module_count' => count($coverageIds['business_module_ids']),
            'responsibility_count' => count($coverageIds['position_ids']),
            'block_trace_count' => count($coverageIds['block_link_ids']),
            'record_schema_source_count' => count($coverageIds['record_schema_source_link_ids']),
            'runtime_evidence_count' => count($coverageIds['record_form_instance_ids']),
            'suggestion_count' => QmsAgentSuggestion::where('element_id', (string)$element->id)->where('status', 'open')->count(),
        ];
        $gapLabels = [];
        foreach ([
            'clause_count' => '缺外部条款',
            'manual_section_count' => '缺手册章节',
            'document_count' => '缺程序文件',
            'record_form_count' => '缺记录表格',
            'module_count' => '缺运行模块',
            'responsibility_count' => '缺岗位职责',
        ] as $key => $label) {
            if ($counts[$key] === 0) {
                $gapLabels[] = $label;
            }
        }

        return array_merge($counts, [
            'element' => $element,
            'primary_clause' => self::primaryClauseLabel((string)$element->id),
            'gap_labels' => $gapLabels,
            'gap_text' => implode('、', $gapLabels),
            'gap_count' => count($gapLabels),
        ]);
    }

    private static function coverageIdsForElement(QmsElement $element): array
    {
        $elementId = (string)$element->id;
        $direct = self::directCoverageIdsForElement($elementId);
        $structured = self::structuredBlockTraceTargets($elementId, $direct);

        return [
            'clause_ids' => self::uniqueIds(array_merge($direct['clause_ids'], $structured['clause_ids'])),
            'manual_section_ids' => self::uniqueIds(array_merge($direct['manual_section_ids'], $structured['manual_section_ids'])),
            'procedure_document_ids' => self::uniqueIds(array_merge($direct['procedure_document_ids'], $structured['procedure_document_ids'])),
            'record_form_template_ids' => self::uniqueIds(array_merge($direct['record_form_template_ids'], $structured['record_form_template_ids'])),
            'business_module_ids' => self::uniqueIds(array_merge($direct['business_module_ids'], $structured['business_module_ids'])),
            'position_ids' => self::uniqueIds(array_merge($direct['position_ids'], $structured['position_ids'])),
            'block_link_ids' => $structured['block_link_ids'],
            'record_schema_source_link_ids' => $structured['record_schema_source_link_ids'],
            'record_form_instance_ids' => self::runtimeEvidenceIdsForTemplates(
                self::uniqueIds(array_merge($direct['record_form_template_ids'], $structured['record_form_template_ids']))
            ),
        ];
    }

    private static function directCoverageIdsForElement(string $elementId): array
    {
        return [
            'clause_ids' => self::stringIds(QmsElementClauseLink::where('element_id', $elementId)->where('soft_delete', 0)->column('clause_id')),
            'manual_section_ids' => self::stringIds(QmsManualSection::where('element_id', $elementId)->where('soft_delete', 0)->column('id')),
            'procedure_document_ids' => self::stringIds(QmsElementDocument::where('element_id', $elementId)->where('soft_delete', 0)->column('document_id')),
            'record_form_template_ids' => self::stringIds(RecordFormTemplate::where('element_id', $elementId)->where('soft_delete', 0)->column('id')),
            'business_module_ids' => self::stringIds(QmsBusinessModuleElement::where('element_id', $elementId)->where('soft_delete', 0)->column('module_id')),
            'position_ids' => self::stringIds(QmsElementResponsibility::where('element_id', $elementId)->where('soft_delete', 0)->column('position_id')),
        ];
    }

    private static function structuredBlockTraceTargets(string $elementId, array $direct): array
    {
        $targets = [
            'block_link_ids' => [],
            'clause_ids' => [],
            'manual_section_ids' => [],
            'procedure_document_ids' => [],
            'record_form_template_ids' => [],
            'business_module_ids' => [],
            'position_ids' => [],
            'record_schema_source_link_ids' => [],
        ];
        foreach (self::structuredBlockTraceRows($elementId, $direct) as $row) {
            $targets['block_link_ids'][] = (string)$row['id'];
            if (QmsDocumentStructureService::schemaSourceNoteFromLinkNote((string)($row['note'] ?? '')) !== '') {
                $targets['record_schema_source_link_ids'][] = (string)$row['id'];
            }
            foreach ([
                'clause_id' => 'clause_ids',
                'manual_section_id' => 'manual_section_ids',
                'procedure_document_id' => 'procedure_document_ids',
                'record_form_template_id' => 'record_form_template_ids',
                'business_module_id' => 'business_module_ids',
                'position_id' => 'position_ids',
            ] as $field => $targetKey) {
                $value = trim((string)($row[$field] ?? ''));
                if ($value !== '') {
                    $targets[$targetKey][] = $value;
                }
            }
        }

        foreach ($targets as $key => $ids) {
            $targets[$key] = self::uniqueIds($ids);
        }

        return $targets;
    }

    private static function elementStructuredBlockEvidence(QmsElement $element): array
    {
        $elementId = (string)$element->id;
        $rows = self::structuredBlockTraceRows($elementId, self::directCoverageIdsForElement($elementId));

        return array_map(
            static fn (array $row): array => self::enrichStructuredBlockEvidenceRow($row, '要素追溯证据', 0),
            $rows
        );
    }

    private static function elementRuntimeEvidence(QmsElement $element): array
    {
        $coverageIds = self::coverageIdsForElement($element);
        $templateIds = $coverageIds['record_form_template_ids'];
        if ($templateIds === []) {
            return [];
        }

        $rows = Db::table('record_form_instances')
            ->alias('i')
            ->join('record_form_templates r', 'r.id = i.template_id')
            ->leftJoin('documents pd', 'pd.id = r.procedure_doc_id')
            ->leftJoin('qms_elements e', 'e.id = r.element_id')
            ->whereIn('i.template_id', $templateIds)
            ->where('r.soft_delete', 0)
            ->field('i.id,i.template_id,i.doc_number,i.record_title,i.status,i.generated_pdf_path,i.generated_pdf_name,i.created,i.modified,r.doc_number template_number,r.name template_name,r.module template_module,r.version template_version,r.element_id,pd.doc_number procedure_number,pd.title procedure_title,e.name element_name')
            ->order('i.created', 'desc')
            ->order('i.record_title', 'asc')
            ->limit(50)
            ->select()
            ->toArray();

        return array_map(static function (array $row): array {
            $instanceId = (string)$row['id'];
            $templateId = (string)$row['template_id'];

            return array_merge($row, [
                'instance_url' => '/record_form_instance/view?id=' . $instanceId,
                'template_url' => '/record_form_template/view?id=' . $templateId,
            ]);
        }, $rows);
    }

    private static function runtimeEvidenceIdsForTemplates(array $templateIds): array
    {
        if ($templateIds === []) {
            return [];
        }

        return self::stringIds(Db::table('record_form_instances')
            ->whereIn('template_id', $templateIds)
            ->column('id'));
    }

    private static function enrichStructuredBlockEvidenceRow(
        array $row,
        string $evidencePath,
        int $pathPriority,
        string $pathElementId = '',
        string $pathElementName = ''
    ): array {
        $blockId = (string)$row['block_id'];
        $structuredDocumentId = (string)$row['structured_document_id'];

        return array_merge($row, [
            'document_url' => '/planning/structures/view?id=' . $structuredDocumentId,
            'review_url' => '/planning/structures/links/review?block_id=' . $blockId,
            'evidence_path' => $evidencePath,
            'path_priority' => $pathPriority,
            'path_element_id' => $pathElementId,
            'path_element_name' => $pathElementName,
        ]);
    }

    private static function structuredBlockTraceRows(string $elementId, array $direct): array
    {
        $rowsById = [];
        $addRows = static function ($query) use (&$rowsById): void {
            foreach ($query->select()->toArray() as $row) {
                $id = (string)($row['id'] ?? '');
                if ($id !== '') {
                    $rowsById[$id] = $row;
                }
            }
        };
        $baseQuery = static fn () => self::structuredBlockEvidenceBaseQuery();

        $addRows($baseQuery()->where('l.element_id', $elementId));
        foreach ([
            'clause_id' => 'clause_ids',
            'manual_section_id' => 'manual_section_ids',
            'procedure_document_id' => 'procedure_document_ids',
            'record_form_template_id' => 'record_form_template_ids',
            'business_module_id' => 'business_module_ids',
        ] as $field => $directKey) {
            if ($direct[$directKey] !== []) {
                $addRows($baseQuery()->whereIn('l.' . $field, $direct[$directKey]));
            }
        }

        $rows = array_values($rowsById);
        self::sortStructuredBlockEvidenceRows($rows);

        return $rows;
    }

    private static function sortStructuredBlockEvidenceRows(array &$rows): void
    {
        usort($rows, static function (array $left, array $right): int {
            return [
                (int)($left['path_priority'] ?? 0),
                (string)($left['doc_number'] ?? ''),
                (string)($left['block_section_number'] ?? ''),
                (string)($left['block_title'] ?? ''),
                (string)($left['id'] ?? ''),
            ] <=> [
                (int)($right['path_priority'] ?? 0),
                (string)($right['doc_number'] ?? ''),
                (string)($right['block_section_number'] ?? ''),
                (string)($right['block_title'] ?? ''),
                (string)($right['id'] ?? ''),
            ];
        });
    }

    private static function structuredBlockEvidenceBaseQuery()
    {
        return Db::name('qms_document_block_links')
            ->alias('l')
            ->join('qms_document_blocks b', 'b.id = l.block_id')
            ->join('qms_structured_documents sd', 'sd.id = b.structured_document_id')
            ->leftJoin('qms_elements e', 'e.id = l.element_id')
            ->leftJoin('qms_clauses c', 'c.id = l.clause_id')
            ->leftJoin('qms_sources s', 's.id = c.source_id')
            ->leftJoin('qms_manual_sections ms', 'ms.id = l.manual_section_id')
            ->leftJoin('documents pd', 'pd.id = l.procedure_document_id')
            ->leftJoin('record_form_templates rft', 'rft.id = l.record_form_template_id')
            ->leftJoin('qms_positions p', 'p.id = l.position_id')
            ->leftJoin('qms_business_modules m', 'm.id = l.business_module_id')
            ->where('l.soft_delete', 0)
            ->where('b.soft_delete', 0)
            ->where('sd.soft_delete', 0)
            ->field('l.id,l.block_id,l.element_id,l.clause_id,l.manual_section_id,l.procedure_document_id,l.record_form_template_id,l.position_id,l.business_module_id,l.relation_type,l.confidence,l.note,e.name element_name,b.structured_document_id,b.title block_title,b.block_type,b.section_number block_section_number,b.stable_key block_stable_key,sd.document_role,sd.doc_number,sd.title document_title,sd.status document_status,s.source_code,c.clause_number,c.title clause_title,ms.section_number manual_section_number,ms.title manual_title,pd.doc_number procedure_number,pd.title procedure_title,rft.doc_number record_number,rft.name record_name,p.name position_name,m.code module_code,m.name module_name,m.url module_url');
    }

    private static function stringIds(array $ids): array
    {
        return self::uniqueIds(array_map('strval', $ids));
    }

    private static function uniqueIds(array $ids): array
    {
        return array_values(array_unique(array_filter(array_map(
            static fn ($id): string => trim((string)$id),
            $ids
        ))));
    }

    private static function orderedElements()
    {
        return QmsElement::where('soft_delete', 0)->order('sort_order', 'asc')->order('name', 'asc')->select();
    }

    private static function elementByKey(string $key): ?QmsElement
    {
        return QmsElement::where('key', $key)->where('soft_delete', 0)->find();
    }

    private static function elementByPrimaryClause(string $number): ?QmsElement
    {
        foreach (self::defaultElementDefinitions() as $definition) {
            if ((string)$definition['primary_clause_number'] === $number) {
                return self::elementByKey((string)$definition['key']);
            }
        }

        return null;
    }

    private static function elementByClauseNumberPrefix(string $number): ?QmsElement
    {
        $number = trim($number);
        if ($number === '') {
            return null;
        }

        $best = null;
        foreach (self::defaultElementDefinitions() as $definition) {
            $primary = (string)$definition['primary_clause_number'];
            if ($number !== $primary && !str_starts_with($number, $primary . '.')) {
                continue;
            }
            if ($best === null || strlen($primary) > strlen((string)$best['primary_clause_number'])) {
                $best = $definition;
            }
        }

        return $best ? self::elementByKey((string)$best['key']) : null;
    }

    private static function parentClauseId(string $sourceId, string $number): ?string
    {
        $parentNumber = self::parentClauseNumber($number);
        if ($parentNumber === null) {
            return null;
        }
        $parent = QmsClause::where('source_id', $sourceId)->where('clause_number', $parentNumber)->where('soft_delete', 0)->find();

        return $parent ? (string)$parent->id : null;
    }

    private static function parentClauseNumber(string $number): ?string
    {
        if (!str_contains($number, '.')) {
            return null;
        }
        $parts = explode('.', $number);
        array_pop($parts);

        return implode('.', $parts);
    }

    private static function clauseLevel(string $number): int
    {
        if (preg_match('/^第/u', $number)) {
            return 1;
        }

        return substr_count($number, '.') + 1;
    }

    private static function matchingElementsForProcedure(string $title): array
    {
        $matches = [];
        foreach (self::elementProcedureKeywords() as $key => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($title, $keyword)) {
                    $element = self::elementByKey($key);
                    if ($element) {
                        $matches[(string)$element->id] = $element;
                    }
                    break;
                }
            }
        }

        return array_values($matches);
    }

    private static function elementProcedureKeywords(): array
    {
        return [
            'personnel' => ['人员培训', '人员'],
            'facilities_environment' => ['设施', '环境'],
            'equipment' => ['仪器设备', '设备'],
            'metrological_traceability' => ['计量溯源', '测量可溯源', '可溯源', '检定', '校准'],
            'externally_provided_products' => ['采购', '供应商', '服务和供应品'],
            'contract_review' => ['合同评审'],
            'methods' => ['方法', '确认', '验证'],
            'sampling' => ['抽样'],
            'item_handling' => ['样品'],
            'technical_records' => ['技术记录', '记录控制'],
            'measurement_uncertainty' => ['测量不确定度', '不确定度'],
            'validity_of_results' => ['质量监控', '质量控制', '能力验证'],
            'results_reporting' => ['报告', '结果报告'],
            'complaints' => ['投诉'],
            'nonconforming_work' => ['不符合'],
            'data_information' => ['计算机', '数据', '信息管理', '电子签名'],
            'management_system_documents' => ['管理体系'],
            'document_control' => ['文件控制'],
            'record_control' => ['记录控制'],
            'risk_opportunity' => ['风险'],
            'improvement' => ['改进'],
            'corrective_action' => ['纠正措施', 'CAPA'],
            'internal_audit' => ['内部审核', '内审', '管理体系审核'],
            'management_review' => ['管理评审'],
            'impartiality' => ['公正性'],
            'confidentiality' => ['保密', '机密'],
            'structure' => ['组织', '结构'],
        ];
    }

    private static function elementForRecordForm(string $module, string $name, string $number): ?QmsElement
    {
        $haystack = $module . ' ' . $name . ' ' . $number;
        foreach ([
            'data_information' => ['计算机', '数据', '信息管理', '电子签名'],
            'item_handling' => ['样品', '留样'],
            'internal_audit' => ['内部管理体系审核', '内部审核', '内审', '授权签字人审核'],
            'results_reporting' => ['结果报告', '报告发放', '报告抽查', '报告更改'],
            'personnel' => ['培训', '能力', '人员'],
            'equipment' => ['设备', '维护'],
            'metrological_traceability' => ['校准', '检定'],
            'record_control' => ['记录'],
            'document_control' => ['文件'],
            'internal_audit' => ['审核', '内审'],
            'management_review' => ['管理评审'],
            'corrective_action' => ['纠正', 'CAPA'],
            'complaints' => ['投诉'],
            'nonconforming_work' => ['不符合'],
        ] as $key => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($haystack, $keyword)) {
                    return self::elementByKey($key);
                }
            }
        }

        return null;
    }

    private static function procedureForRecordForm(string $module): ?Document
    {
        $moduleKey = self::compactLookupText($module);
        if ($moduleKey === '') {
            return null;
        }

        foreach (Document::where('soft_delete', 0)->where('level', 2)->select() as $document) {
            $titleKey = self::compactLookupText((string)$document->title);
            if ($titleKey === '') {
                continue;
            }
            if ($titleKey === $moduleKey || str_contains($titleKey, $moduleKey) || str_contains($moduleKey, $titleKey)) {
                return $document;
            }
        }

        return null;
    }

    private static function primaryElementForProcedure(string $documentId): ?QmsElement
    {
        $link = QmsElementDocument::where('document_id', $documentId)
            ->where('soft_delete', 0)
            ->order('relation_type', 'asc')
            ->find();
        if (!$link) {
            return null;
        }

        return QmsElement::where('id', (string)$link->element_id)->where('soft_delete', 0)->find();
    }

    private static function compactLookupText(string $value): string
    {
        $value = preg_replace('/[\\s　《》〈〉（）()【】\\[\\]、，,。．.：:；;\\-—_]+/u', '', $value) ?? $value;

        return trim($value);
    }

    private static function procedureForElement(string $elementId): ?Document
    {
        $link = QmsElementDocument::where('element_id', $elementId)->where('soft_delete', 0)->find();
        if (!$link) {
            return null;
        }

        return Document::where('id', (string)$link->document_id)->where('soft_delete', 0)->find();
    }

    private static function upsertElementDocument(string $elementId, string $documentId, string $type, string $note): bool
    {
        $link = QmsElementDocument::where('element_id', $elementId)->where('document_id', $documentId)->where('soft_delete', 0)->find();
        $isNew = !$link;
        if (!$link) {
            $link = new QmsElementDocument();
            $link->id = qms_uuid();
        }
        $link->save([
            'element_id' => $elementId,
            'document_id' => $documentId,
            'relation_type' => $type,
            'note' => $note,
            'publish' => 1,
            'soft_delete' => 0,
        ]);

        return $isNew;
    }

    private static function seedFallbackResponsibilities(): int
    {
        $count = 0;
        foreach ([
            'results_reporting' => [
                ['authorized_signatory', 'decision_owner', '报告批准与授权签发'],
                ['technical_manager', 'organizer', '报告技术复核与方法适用性确认'],
            ],
            'management_review' => [
                ['lab_director', 'decision_owner', '主持管理评审并批准改进决议'],
                ['quality_manager', 'organizer', '组织管理评审输入、会议和跟踪验证'],
                ['technical_manager', 'participant', '提供技术运行和能力保障输入'],
            ],
            'internal_audit' => [
                ['quality_manager', 'organizer', '组织内部审核方案和整改跟踪'],
                ['internal_auditor', 'participant', '实施审核并形成审核记录'],
            ],
        ] as $elementKey => $rows) {
            $element = self::elementByKey($elementKey);
            if (!$element) {
                continue;
            }
            foreach ($rows as [$positionCode, $type, $note]) {
                $position = QmsPosition::where('code', $positionCode)->where('soft_delete', 0)->find();
                if (!$position) {
                    continue;
                }
                $responsibility = QmsElementResponsibility::where('element_id', (string)$element->id)
                    ->where('position_id', (string)$position->id)
                    ->where('responsibility_type', $type)
                    ->where('soft_delete', 0)
                    ->find();
                if (!$responsibility) {
                    $responsibility = new QmsElementResponsibility();
                    $responsibility->id = qms_uuid();
                    $count++;
                }
                $responsibility->save([
                    'element_id' => (string)$element->id,
                    'position_id' => (string)$position->id,
                    'responsibility_type' => $type,
                    'note' => $note,
                    'publish' => 1,
                    'soft_delete' => 0,
                ]);
            }
        }

        return $count;
    }

    private static function upsertModuleElement(string $moduleId, string $elementId, string $type, ?string $note = null): bool
    {
        $link = QmsBusinessModuleElement::where('module_id', $moduleId)->where('element_id', $elementId)->find();
        $isNew = !$link;
        if (!$link) {
            $link = new QmsBusinessModuleElement();
            $link->id = qms_uuid();
        }
        $payload = [
            'module_id' => $moduleId,
            'element_id' => $elementId,
            'relation_type' => $type,
            'soft_delete' => 0,
        ];
        if ($note !== null) {
            $payload['note'] = $note;
        }
        $link->save($payload);

        return $isNew;
    }

    private static function moduleRelationLabel(string $relationType): string
    {
        return match ($relationType) {
            'primary' => '主归属',
            'supporting' => '补充映射',
            default => '',
        };
    }

    private static function primaryClauseLabel(string $elementId): string
    {
        $row = Db::table('qms_element_clause_links')
            ->alias('l')
            ->join('qms_clauses c', 'c.id = l.clause_id')
            ->join('qms_sources s', 's.id = c.source_id')
            ->where('l.element_id', $elementId)
            ->where('l.is_primary', 1)
            ->where('l.soft_delete', 0)
            ->field('s.source_code,c.clause_number,c.title')
            ->find();
        if (!$row) {
            return '';
        }

        return (string)$row['source_code'] . ' ' . (string)$row['clause_number'] . ' ' . (string)$row['title'];
    }

    private static function elementClauses(string $elementId)
    {
        return Db::table('qms_element_clause_links')
            ->alias('l')
            ->join('qms_clauses c', 'c.id = l.clause_id')
            ->join('qms_sources s', 's.id = c.source_id')
            ->where('l.element_id', $elementId)
            ->where('l.soft_delete', 0)
            ->field('l.mapping_type,l.is_primary,l.note,s.source_code,c.clause_number,c.title,c.id')
            ->order('l.is_primary', 'desc')
            ->order('s.source_code', 'asc')
            ->order('c.clause_number', 'asc')
            ->select();
    }

    private static function elementDocuments(string $elementId)
    {
        return Db::table('qms_element_documents')
            ->alias('l')
            ->join('documents d', 'd.id = l.document_id')
            ->where('l.element_id', $elementId)
            ->where('l.soft_delete', 0)
            ->field('l.relation_type,l.note,d.id,d.doc_number,d.title,d.level,d.status')
            ->order('d.doc_number', 'asc')
            ->select();
    }

    private static function elementModules(string $elementId)
    {
        $rows = Db::table('qms_business_module_elements')
            ->alias('l')
            ->join('qms_business_modules m', 'm.id = l.module_id')
            ->where('l.element_id', $elementId)
            ->where('l.soft_delete', 0)
            ->field('l.relation_type,m.id,m.code,m.name,m.url,m.controller_name')
            ->order('m.code', 'asc')
            ->select()
            ->toArray();
        foreach ($rows as &$row) {
            $row['relation_label'] = self::moduleRelationLabel((string)($row['relation_type'] ?? ''));
        }
        unset($row);

        return $rows;
    }

    private static function elementResponsibilities(string $elementId)
    {
        return Db::table('qms_element_responsibilities')
            ->alias('r')
            ->join('qms_positions p', 'p.id = r.position_id')
            ->where('r.element_id', $elementId)
            ->where('r.soft_delete', 0)
            ->field('r.responsibility_type,r.note,p.id,p.code,p.name')
            ->order('r.responsibility_type', 'asc')
            ->order('p.code', 'asc')
            ->select();
    }

    private static function workspacePath(string $relativePath): string
    {
        if ($relativePath === '') {
            return '';
        }
        if (str_starts_with($relativePath, DIRECTORY_SEPARATOR)) {
            return $relativePath;
        }

        $projectRoot = dirname(__DIR__, 2);
        $workspaceRoot = dirname(__DIR__, 3);
        foreach ([
            $projectRoot . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . ltrim($relativePath, DIRECTORY_SEPARATOR),
            $workspaceRoot . DIRECTORY_SEPARATOR . ltrim($relativePath, DIRECTORY_SEPARATOR),
            $projectRoot . DIRECTORY_SEPARATOR . ltrim($relativePath, DIRECTORY_SEPARATOR),
        ] as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return $workspaceRoot . DIRECTORY_SEPARATOR . ltrim($relativePath, DIRECTORY_SEPARATOR);
    }

    private static function archiveExternalSourceFile(
        string $sourceFilePath,
        string $sourceCode,
        string $name,
        string $version,
        string $originalName
    ): string {
        $ext = strtolower((string)pathinfo($originalName, PATHINFO_EXTENSION));
        if (!in_array($ext, ['pdf', 'doc', 'docx'], true)) {
            throw new \RuntimeException('外部依据仅支持 PDF、Word 文件');
        }

        $archiveDir = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'runtime' . DIRECTORY_SEPARATOR . 'qms_sources' . DIRECTORY_SEPARATOR . 'archive';
        if (!is_dir($archiveDir)) {
            mkdir($archiveDir, 0775, true);
        }
        $baseName = self::safeToken(trim($sourceCode . '-' . $name . '-' . $version, '-'));
        $target = $archiveDir . DIRECTORY_SEPARATOR . $baseName . '.' . $ext;
        if (!is_file($target) || hash_file('sha256', $target) !== hash_file('sha256', $sourceFilePath)) {
            copy($sourceFilePath, $target);
        }

        return 'runtime/qms_sources/archive/' . basename($target);
    }

    private static function normalizeSourceFreshnessPayload(array $payload, array $defaults = [], bool $strict = true): array
    {
        $checkedAt = self::payloadString($payload, $defaults, 'freshness_checked_at');
        $result = self::payloadString($payload, $defaults, 'freshness_result');
        $evidence = self::payloadString($payload, $defaults, 'freshness_evidence');
        $nextDue = self::payloadString($payload, $defaults, 'next_freshness_due');
        $status = self::payloadString($payload, $defaults, 'freshness_status');
        $status = $status !== '' ? $status : 'unknown';

        if (!in_array($status, ['unknown', 'current', 'due', 'obsolete'], true)) {
            throw new \RuntimeException('查新状态无效');
        }
        if ($strict && $checkedAt === '') {
            throw new \RuntimeException('查新日期不能为空');
        }
        if ($strict && $result === '') {
            throw new \RuntimeException('查新结论不能为空');
        }
        if ($strict && $evidence === '') {
            throw new \RuntimeException('查新证据不能为空');
        }
        if ($checkedAt !== '') {
            self::assertDateString($checkedAt, '查新日期');
        }
        if ($nextDue !== '') {
            self::assertDateString($nextDue, '下次查新日期');
        }

        return [
            'freshness_checked_at' => $checkedAt !== '' ? $checkedAt : null,
            'freshness_result' => $result,
            'freshness_evidence' => $evidence,
            'next_freshness_due' => $nextDue !== '' ? $nextDue : null,
            'freshness_status' => $status,
        ];
    }

    private static function payloadString(array $payload, array $defaults, string $key): string
    {
        $value = trim((string)($payload[$key] ?? ''));
        if ($value !== '') {
            return $value;
        }

        return trim((string)($defaults[$key] ?? ''));
    }

    private static function assertDateString(string $value, string $label): void
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            throw new \RuntimeException($label . '格式应为 YYYY-MM-DD');
        }
        [$year, $month, $day] = array_map('intval', explode('-', $value));
        if (!checkdate($month, $day, $year)) {
            throw new \RuntimeException($label . '不是有效日期');
        }
    }

    private static function safeToken(string $value): string
    {
        $value = trim($value);
        $value = preg_replace('/[^\p{Han}A-Za-z0-9._-]+/u', '_', $value) ?? $value;
        $value = trim($value, '._-');

        return $value !== '' ? mb_substr($value, 0, 180) : substr(sha1($value), 0, 12);
    }
}
