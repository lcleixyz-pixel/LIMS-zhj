<?php
declare(strict_types=1);

namespace app\service;

use DomainException;
use RuntimeException;
use think\facade\Config;
use think\facade\Db;

final class QmsCx0302GovernanceClosureService
{
    public const TRIAL_BATCH = 'GOV-TRIAL-20260724';
    public const STRUCTURE_DOC_NUMBER = 'SIM-GOV02-XZTC/CX-03-02-2022';
    public const STRUCTURE_VERSION = 'GOV-TRIAL/0.2';
    public const TEMPLATE_CANONICAL_NUMBER = 'XZTC/BG-35-03';
    public const SOURCE_FILE_PATH = '现用文件/记录表格/记录表格2017/35标准物质管理程序/35-03标准物质报废申请表.doc';

    public static function preview(): array
    {
        $structure = Db::name('qms_structured_documents')
            ->where('doc_number', self::STRUCTURE_DOC_NUMBER)
            ->where('version', self::STRUCTURE_VERSION)
            ->where('soft_delete', 0)
            ->find();
        $block = is_array($structure)
            ? Db::name('qms_document_blocks')
                ->where('structured_document_id', (string)$structure['id'])
                ->whereLike('title', '%工作程序%')
                ->where('soft_delete', 0)
                ->order('sort_order', 'asc')
                ->find()
            : null;
        $template = Db::name('record_form_templates')
            ->where('trial_batch', self::TRIAL_BATCH)
            ->where('canonical_doc_number', self::TEMPLATE_CANONICAL_NUMBER)
            ->where('soft_delete', 0)
            ->find();

        $manualSections = [];
        foreach (['6.4', '6.5'] as $sectionNumber) {
            $manualSections[$sectionNumber] = Db::name('qms_manual_sections')
                ->alias('section')
                ->join('documents document', 'document.id = section.document_id')
                ->where('document.doc_number', 'SIM-XZTC/SC')
                ->where('document.version', 'GOV-TRIAL/0.1')
                ->where('section.section_number', $sectionNumber)
                ->where('section.soft_delete', 0)
                ->field('section.id,section.element_id,section.section_number,section.title')
                ->find();
        }

        $clauses = [];
        foreach (self::clauseSpecifications() as $key => $specification) {
            $clauses[$key] = Db::name('qms_clauses')
                ->alias('clause')
                ->join('qms_sources source', 'source.id = clause.source_id')
                ->where('source.source_code', $specification['source_code'])
                ->where('clause.clause_number', $specification['clause_number'])
                ->where('clause.soft_delete', 0)
                ->field('clause.id,source.source_code,clause.clause_number,clause.title')
                ->find();
        }

        $expectedSchema = self::expectedSchema();
        $currentSchema = is_array($template)
            ? RecordFormSchemaService::decode((string)($template['field_schema'] ?? ''))
            : [];
        $expectedKeys = self::schemaKeys($expectedSchema);
        $currentKeys = self::schemaKeys($currentSchema);
        $sourceAbsolutePath = self::workspaceRoot() . '/' . self::SOURCE_FILE_PATH;

        $plannedLinks = [];
        if (is_array($block)) {
            foreach (self::linkSpecifications($manualSections, $clauses) as $link) {
                $link['linked'] = self::traceLinkExists((string)$block['id'], $link);
                $plannedLinks[] = $link;
            }
        }

        $schemaDocuments = is_array($template)
            ? (int)Db::name('qms_document_assets')
                ->alias('asset')
                ->join('qms_structured_documents structure', 'structure.source_asset_id = asset.id')
                ->where('asset.source_kind', 'record_form')
                ->where('asset.record_form_template_id', (string)$template['id'])
                ->where('asset.soft_delete', 0)
                ->where('structure.soft_delete', 0)
                ->count()
            : 0;

        $blockingErrors = [];
        if (!is_array($structure)) {
            $blockingErrors[] = self::STRUCTURE_DOC_NUMBER . ' ' . self::STRUCTURE_VERSION . ' 不存在';
        }
        if (!is_array($block)) {
            $blockingErrors[] = 'CX-03-02 连续正文缺少“工作程序”内容块';
        }
        if (!is_array($template)) {
            $blockingErrors[] = '8021 缺少 BG-35-03 试运行模板';
        }
        if (!is_file($sourceAbsolutePath)) {
            $blockingErrors[] = 'BG-35-03 现用纸质源表不存在';
        }
        foreach ($manualSections as $sectionNumber => $manualSection) {
            if (!is_array($manualSection)) {
                $blockingErrors[] = '质量手册 ' . $sectionNumber . ' 章节不存在';
            }
        }
        foreach ($clauses as $key => $clause) {
            if (!is_array($clause)) {
                $specification = self::clauseSpecifications()[$key];
                $blockingErrors[] = $specification['source_code'] . ' ' . $specification['clause_number'] . ' 条款不存在';
            }
        }

        return [
            'mode' => 'preview',
            'target' => [
                'structure_id' => is_array($structure) ? (string)$structure['id'] : '',
                'block_id' => is_array($block) ? (string)$block['id'] : '',
                'template_id' => is_array($template) ? (string)$template['id'] : '',
                'doc_number' => self::STRUCTURE_DOC_NUMBER,
                'version' => self::STRUCTURE_VERSION,
            ],
            'environment' => [
                'trial_mode_enabled' => TrialModeService::isEnabled(),
                'trial_batch' => TrialModeService::trialBatch(),
                'database' => (string)Config::get('database.connections.mysql.database', ''),
            ],
            'schema' => [
                'current_keys' => $currentKeys,
                'expected_keys' => $expectedKeys,
                'matches' => $currentKeys === $expectedKeys,
                'source_file_path' => self::SOURCE_FILE_PATH,
                'source_exists' => is_file($sourceAbsolutePath),
                'source_matches' => is_array($template)
                    && (string)($template['source_file_path'] ?? '') === self::SOURCE_FILE_PATH,
                'structured_document_count' => $schemaDocuments,
            ],
            'planned_links' => $plannedLinks,
            'blocking_errors' => $blockingErrors,
            'ready_to_apply' => $blockingErrors === [],
            'formal_system_notice' => '仅限8021隔离环境治理试运行；纸质体系仍为唯一正式体系。',
        ];
    }

    public static function apply(): array
    {
        self::assertWritableEnvironment();
        $preview = self::preview();
        if (($preview['ready_to_apply'] ?? false) !== true) {
            throw new RuntimeException('CX-03-02 治理闭环存在阻断：' . implode('；', $preview['blocking_errors'] ?? []));
        }

        $target = $preview['target'];
        $templateId = (string)$target['template_id'];
        $sourceAbsolutePath = self::workspaceRoot() . '/' . self::SOURCE_FILE_PATH;
        $expectedSchema = self::expectedSchema();
        $reviewMarker = '2026-07-27 CX-03-02治理闭环：BG-35-03字段逐项依据现用纸质表，外部依据和手册落实关系经定向复核补齐。';

        $structureSummary = Db::transaction(function () use (
            $templateId,
            $sourceAbsolutePath,
            $expectedSchema,
            $reviewMarker,
            $preview
        ): array {
            $template = Db::name('record_form_templates')->where('id', $templateId)->lock(true)->find();
            if (!is_array($template)) {
                throw new RuntimeException('BG-35-03 试运行模板在写入前已不存在');
            }
            $reviewNote = trim((string)($template['review_note'] ?? ''));
            if (!str_contains($reviewNote, $reviewMarker)) {
                $reviewNote = $reviewNote === '' ? $reviewMarker : $reviewNote . "\n" . $reviewMarker;
            }
            Db::name('record_form_templates')->where('id', $templateId)->update([
                'field_schema' => RecordFormSchemaService::encode($expectedSchema),
                'source_file_path' => self::SOURCE_FILE_PATH,
                'source_file_name' => basename(self::SOURCE_FILE_PATH),
                'source_file_sha1' => (string)sha1_file($sourceAbsolutePath),
                'review_status' => 'completed',
                'review_note' => $reviewNote,
                'modified' => date('Y-m-d H:i:s'),
            ]);

            foreach ($preview['planned_links'] as $link) {
                unset($link['linked'], $link['label']);
                QmsDocumentStructureService::upsertBlockTraceLink((string)$preview['target']['block_id'], $link);
            }

            return QmsDocumentStructureService::structureRecordFormTemplate($templateId);
        });

        $verification = self::verify();
        if (($verification['ok'] ?? false) !== true) {
            throw new RuntimeException('CX-03-02 治理闭环写入后验证失败：' . implode('；', $verification['errors'] ?? []));
        }

        return [
            'mode' => 'trial_apply',
            'target' => $preview['target'],
            'record_form_structure' => $structureSummary,
            'verification' => $verification,
            'formal_system_notice' => $preview['formal_system_notice'],
        ];
    }

    public static function verify(): array
    {
        $preview = self::preview();
        $errors = $preview['blocking_errors'] ?? [];
        if (($preview['schema']['matches'] ?? false) !== true) {
            $errors[] = 'BG-35-03 字段结构仍未对应现用纸质源表';
        }
        if (($preview['schema']['source_matches'] ?? false) !== true) {
            $errors[] = 'BG-35-03 尚未回链现用纸质源表';
        }
        if ((int)($preview['schema']['structured_document_count'] ?? 0) < 1) {
            $errors[] = 'BG-35-03 尚未生成结构化 schema 文档';
        }
        foreach ($preview['planned_links'] as $link) {
            if (($link['linked'] ?? false) !== true) {
                $errors[] = '缺少追溯关系：' . (string)($link['label'] ?? '未知关系');
            }
        }

        return [
            'ok' => $errors === [],
            'errors' => array_values(array_unique($errors)),
            'schema' => $preview['schema'],
            'planned_links' => $preview['planned_links'],
        ];
    }

    private static function assertWritableEnvironment(): void
    {
        $errors = [];
        if (!TrialModeService::isEnabled()) {
            $errors[] = 'QMS_TRIAL_MODE 未启用';
        }
        if (TrialModeService::trialBatch() !== self::TRIAL_BATCH) {
            $errors[] = 'QMS_TRIAL_BATCH 不是 ' . self::TRIAL_BATCH;
        }
        if ((string)Config::get('database.connections.mysql.database', '') !== 'jewelry_qms') {
            $errors[] = '数据库名称不是8021隔离栈预期的 jewelry_qms';
        }
        if ($errors !== []) {
            throw new DomainException('CX-03-02 治理闭环拒绝写入：' . implode('；', $errors));
        }
    }

    private static function expectedSchema(): array
    {
        foreach (G2ExpansionBatch4BlueprintService::templates() as $template) {
            if ((string)($template['doc_number'] ?? '') === self::TEMPLATE_CANONICAL_NUMBER) {
                return (array)($template['field_schema'] ?? []);
            }
        }

        throw new RuntimeException('G2扩4批蓝图缺少 BG-35-03');
    }

    private static function schemaKeys(array $schema): array
    {
        return array_values(array_map(
            static fn(array $field): string => (string)($field['key'] ?? ''),
            $schema
        ));
    }

    private static function clauseSpecifications(): array
    {
        return [
            'cma_traceability' => [
                'source_code' => '市场监管总局公告2023年第21号',
                'clause_number' => '2.11.3',
                'manual_section_number' => '6.5',
                'note' => '现行CMA直接依据：标准物质溯源要求；用于复核标准物质来源、证书和可追溯信息。',
            ],
            'cnas_reference_material' => [
                'source_code' => 'CNAS-CL01-G001:2024',
                'clause_number' => '6.4.1a)',
                'manual_section_number' => '6.4',
                'note' => 'CNAS应用要求：设备范围包括标准物质；用于落实标准物质获得、控制和处置。',
            ],
            'cnas_traceability' => [
                'source_code' => 'CNAS-CL01:2018',
                'clause_number' => '6.5.2',
                'manual_section_number' => '6.5',
                'note' => 'CNAS认可准则：计量溯源实现要求；用于落实有证标准物质等适用路径。',
            ],
        ];
    }

    private static function linkSpecifications(array $manualSections, array $clauses): array
    {
        $links = [];
        foreach ($manualSections as $sectionNumber => $section) {
            if (!is_array($section)) {
                continue;
            }
            $links[] = [
                'label' => '质量手册 ' . $sectionNumber . ' ' . (string)$section['title'],
                'element_id' => (string)($section['element_id'] ?? ''),
                'manual_section_id' => (string)$section['id'],
                'relation_type' => 'implements',
                'confidence' => 'high',
                'note' => '治理试运行定向复核：CX-03-02工作程序落实质量手册'
                    . $sectionNumber . '《' . (string)$section['title'] . '》。',
            ];
        }
        foreach (self::clauseSpecifications() as $key => $specification) {
            $clause = $clauses[$key] ?? null;
            $section = $manualSections[$specification['manual_section_number']] ?? null;
            if (!is_array($clause)) {
                continue;
            }
            $links[] = [
                'label' => (string)$clause['source_code'] . ' ' . (string)$clause['clause_number'],
                'element_id' => is_array($section) ? (string)($section['element_id'] ?? '') : '',
                'clause_id' => (string)$clause['id'],
                'relation_type' => 'basis',
                'confidence' => 'high',
                'note' => '治理试运行定向复核：' . (string)$specification['note'],
            ];
        }

        return $links;
    }

    private static function traceLinkExists(string $blockId, array $link): bool
    {
        $query = Db::name('qms_document_block_links')
            ->where('block_id', $blockId)
            ->where('relation_type', (string)$link['relation_type'])
            ->where('soft_delete', 0);
        foreach ([
            'element_id',
            'clause_id',
            'manual_section_id',
            'procedure_document_id',
            'record_form_template_id',
            'position_id',
            'business_module_id',
        ] as $field) {
            if (!empty($link[$field])) {
                $query->where($field, (string)$link[$field]);
            }
        }

        return $query->count() > 0;
    }

    private static function workspaceRoot(): string
    {
        return rtrim(dirname(__DIR__, 3), DIRECTORY_SEPARATOR);
    }
}
