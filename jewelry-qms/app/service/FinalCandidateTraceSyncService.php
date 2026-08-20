<?php
declare(strict_types=1);

namespace app\service;

use DomainException;
use RuntimeException;
use think\facade\Config;
use think\facade\Db;

final class FinalCandidateTraceSyncService
{
    private const REPORT_VERSION = 'v0.1';
    private const FORMAL_TITLE_PREFIX = '[8021测试正式] ';
    private const LEGACY_TITLE_PREFIX = '[8021候选试装] ';
    private const ALLOWED_BLOCK_RELATIONS = ['basis', 'implements', 'requires_record'];

    public static function preview(): array
    {
        self::assertSchema();

        $counts = self::counts();
        $documentPlan = self::planElementDocumentMigration();
        $blockPlan = self::planBlockLinkMigration();
        $errors = [];
        if (($counts['candidate_documents'] ?? 0) !== 65) {
            $errors[] = 'GOV-TRIAL/0.3 测试正式制度应为65份，当前为' . (string)($counts['candidate_documents'] ?? 0);
        }
        if (($counts['candidate_structures'] ?? 0) !== 65) {
            $errors[] = 'GOV-TRIAL/0.3 结构化制度应为65份，当前为' . (string)($counts['candidate_structures'] ?? 0);
        }
        if (($counts['active_elements'] ?? 0) !== 29) {
            $errors[] = '质量要素应为29个，当前为' . (string)($counts['active_elements'] ?? 0);
        }
        if (($counts['trial_ready_templates'] ?? 0) !== 104) {
            $errors[] = 'trial_ready 表单应保持104张，当前为' . (string)($counts['trial_ready_templates'] ?? 0);
        }

        return [
            'mode' => 'inspect_only',
            'report_version' => self::REPORT_VERSION,
            'version' => FinalCandidateManifestService::VERSION,
            'trial_batch' => FinalCandidateManifestService::TRIAL_BATCH,
            'counts' => $counts,
            'planned' => [
                'element_documents' => $documentPlan['summary'],
                'block_links' => $blockPlan['summary'],
            ],
            'validation' => [
                'ok' => $errors === [],
                'errors' => $errors,
            ],
            'formal_system_notice' => '仅限8021测试环境内的测试正式视图；纸质体系仍是唯一正式体系，不构成8010发布或真实运行迁移。',
        ];
    }

    public static function apply(?string $outputDir = null): array
    {
        self::assertWritableTrialEnvironment();
        self::assertSchema();

        $before = self::protectedFingerprint();
        $outputDir = self::resolvedOutputDir($outputDir);
        $result = [];

        Db::transaction(function () use (&$result, $before): void {
            $preview = self::preview();
            if (($preview['validation']['ok'] ?? false) !== true) {
                throw new RuntimeException('链路同步预览未通过：' . implode('；', $preview['validation']['errors'] ?? []));
            }

            $documentPlan = self::planElementDocumentMigration();
            $blockPlan = self::planBlockLinkMigration();

            self::formalizeCandidateRows();
            $documentMigration = self::applyElementDocumentMigration($documentPlan['rows']);
            $blockMigration = self::applyBlockLinkMigration($blockPlan['rows']);
            $supplement = self::applySupplementalReviewLinks();
            $hidden = self::hideNonCandidateFormalRows();

            $verification = self::verifyFormalTrace();
            if (($verification['ok'] ?? false) !== true) {
                throw new RuntimeException('8021链路同步事务验证失败：' . implode('；', $verification['errors'] ?? []));
            }
            if (self::protectedFingerprint() !== $before) {
                throw new RuntimeException('链路同步触碰了既有记录模板或记录实例，事务已回滚');
            }

            $result = [
                'mode' => 'trial_trace_apply',
                'report_version' => self::REPORT_VERSION,
                'version' => FinalCandidateManifestService::VERSION,
                'trial_batch' => FinalCandidateManifestService::TRIAL_BATCH,
                'before' => $preview['counts'] ?? [],
                'migration' => [
                    'element_documents' => $documentMigration,
                    'block_links' => $blockMigration,
                    'supplemental_review_links' => $supplement,
                    'hidden_old_view_rows' => $hidden,
                ],
                'validation' => $verification,
                'formal_system_notice' => '仅限8021测试环境内的测试正式视图；纸质体系仍是唯一正式体系，不构成8010发布或真实运行迁移。',
            ];
        });

        $result['package'] = self::writeReport($result, $outputDir);
        return $result;
    }

    public static function verifyFormalTrace(): array
    {
        $counts = self::counts();
        $errors = [];
        if (($counts['candidate_documents'] ?? 0) !== 65) {
            $errors[] = '现行0.3制度应为65份，当前为' . (string)($counts['candidate_documents'] ?? 0);
        }
        if (($counts['candidate_published_documents'] ?? 0) !== 65) {
            $errors[] = '65份0.3制度必须均为8021测试正式published，当前为' . (string)($counts['candidate_published_documents'] ?? 0);
        }
        if (($counts['candidate_structures'] ?? 0) !== 65) {
            $errors[] = '现行0.3结构化制度应为65份，当前为' . (string)($counts['candidate_structures'] ?? 0);
        }
        if (($counts['candidate_blocks'] ?? 0) !== 315) {
            $errors[] = '现行0.3候选解析块应为315个，当前为' . (string)($counts['candidate_blocks'] ?? 0);
        }
        if (($counts['candidate_published_structures'] ?? 0) !== 65) {
            $errors[] = '65份0.3结构化制度必须均为published，当前为' . (string)($counts['candidate_published_structures'] ?? 0);
        }
        if (($counts['active_non_candidate_documents'] ?? 0) !== 0) {
            $errors[] = '现行测试视图仍有旧版本制度：' . (string)$counts['active_non_candidate_documents'];
        }
        if (($counts['active_non_candidate_structures'] ?? 0) !== 0) {
            $errors[] = '现行测试视图仍有旧版本结构化制度：' . (string)$counts['active_non_candidate_structures'];
        }
        if (($counts['candidate_element_documents'] ?? 0) < 25) {
            $errors[] = '候选制度-要素链路不足，当前为' . (string)($counts['candidate_element_documents'] ?? 0);
        }
        if (($counts['old_active_element_documents'] ?? 0) !== 0) {
            $errors[] = '仍有旧版本制度-要素链路处于现行视图：' . (string)$counts['old_active_element_documents'];
        }
        if (($counts['candidate_block_links'] ?? 0) <= 0) {
            $errors[] = '候选章节链路为空';
        }
        if (($counts['candidate_manual_block_links'] ?? 0) < 29) {
            $errors[] = '质量手册待复核章节链路不足，当前为' . (string)($counts['candidate_manual_block_links'] ?? 0);
        }
        if (($counts['candidate_work_instruction_element_documents'] ?? 0) < 28) {
            $errors[] = '非废止作业指导书要素对应不足，当前为' . (string)($counts['candidate_work_instruction_element_documents'] ?? 0);
        }
        if (($counts['candidate_work_instruction_block_linked_documents'] ?? 0) < 28) {
            $errors[] = '非废止作业指导书待复核章节链路不足，当前为' . (string)($counts['candidate_work_instruction_block_linked_documents'] ?? 0);
        }
        if (($counts['candidate_template_block_links'] ?? 0) !== 0) {
            $errors[] = '候选章节不得直接挂接记录表单模板：' . (string)$counts['candidate_template_block_links'];
        }
        if (($counts['old_active_block_links'] ?? 0) !== 0) {
            $errors[] = '仍有旧版本章节链路处于现行视图：' . (string)$counts['old_active_block_links'];
        }
        if (($counts['trial_ready_templates'] ?? 0) !== 104) {
            $errors[] = 'trial_ready 表单数量必须保持104，当前为' . (string)$counts['trial_ready_templates'];
        }

        return [
            'ok' => $errors === [],
            'errors' => $errors,
            'counts' => $counts,
        ];
    }

    private static function assertWritableTrialEnvironment(): void
    {
        $errors = FinalCandidateAssemblyService::writableEnvironmentErrors();
        if ($errors !== []) {
            throw new DomainException('8021链路同步拒绝写入：' . implode('；', $errors));
        }
    }

    private static function assertSchema(): void
    {
        foreach ([
            'documents' => ['id', 'doc_number', 'title', 'version', 'status', 'publish', 'soft_delete'],
            'qms_structured_documents' => ['id', 'document_id', 'doc_number', 'title', 'version', 'status', 'source_status', 'publish', 'soft_delete'],
            'qms_document_blocks' => ['id', 'structured_document_id', 'document_id', 'title', 'block_type', 'publish', 'soft_delete'],
            'qms_document_block_links' => ['id', 'block_id', 'element_id', 'clause_id', 'procedure_document_id', 'record_form_template_id', 'relation_type', 'confidence', 'publish', 'soft_delete'],
            'qms_element_documents' => ['id', 'element_id', 'document_id', 'relation_type', 'publish', 'soft_delete'],
        ] as $table => $columns) {
            $available = Db::query('SHOW COLUMNS FROM `' . $table . '`');
            $index = array_fill_keys(array_map(static fn(array $row): string => (string)$row['Field'], $available), true);
            foreach ($columns as $column) {
                if (!isset($index[$column])) {
                    throw new RuntimeException('数据库缺少链路同步字段：' . $table . '.' . $column);
                }
            }
        }
    }

    private static function counts(): array
    {
        $candidateDocumentBase = static fn() => Db::name('documents')
            ->where('version', FinalCandidateManifestService::VERSION)
            ->whereLike('doc_number', FinalCandidateManifestService::TRIAL_PREFIX . '%')
            ->where('soft_delete', 0);
        $candidateStructureBase = static fn() => Db::name('qms_structured_documents')
            ->where('version', FinalCandidateManifestService::VERSION)
            ->whereLike('doc_number', FinalCandidateManifestService::TRIAL_PREFIX . '%')
            ->where('soft_delete', 0);

        return [
            'candidate_documents' => (int)$candidateDocumentBase()->count(),
            'candidate_published_documents' => (int)$candidateDocumentBase()->where('status', 'published')->where('publish', 1)->count(),
            'candidate_structures' => (int)$candidateStructureBase()->count(),
            'candidate_published_structures' => (int)$candidateStructureBase()->where('status', 'published')->where('publish', 1)->count(),
            'candidate_blocks' => self::candidateBlockQuery()->count(),
            'active_non_candidate_documents' => self::nonCandidateDocumentQuery()->count(),
            'active_non_candidate_structures' => self::nonCandidateStructureQuery()->count(),
            'active_elements' => (int)Db::name('qms_elements')->where('publish', 1)->where('soft_delete', 0)->count(),
            'element_clause_links' => (int)Db::name('qms_element_clause_links')->where('publish', 1)->where('soft_delete', 0)->count(),
            'candidate_element_documents' => self::candidateElementDocumentQuery()->count(),
            'old_active_element_documents' => self::oldElementDocumentQuery()->count(),
            'candidate_block_links' => self::candidateBlockLinkQuery()->count(),
            'candidate_manual_block_links' => self::candidateManualBlockLinkQuery()->count(),
            'candidate_work_instruction_element_documents' => self::candidateWorkInstructionElementDocumentQuery(),
            'candidate_work_instruction_block_linked_documents' => self::candidateWorkInstructionBlockLinkedDocuments(),
            'candidate_template_block_links' => self::candidateBlockLinkQuery()->whereNotNull('link.record_form_template_id')->count(),
            'old_active_block_links' => self::oldBlockLinkQuery()->count(),
            'record_form_templates' => (int)Db::name('record_form_templates')->where('soft_delete', 0)->count(),
            'trial_ready_templates' => (int)Db::name('record_form_templates')->where('status', 'trial_ready')->where('soft_delete', 0)->count(),
            'record_instances' => (int)Db::name('record_form_instances')->count(),
        ];
    }

    private static function candidateBlockQuery()
    {
        return Db::name('qms_document_blocks')->alias('block')
            ->join('qms_structured_documents structure', 'structure.id=block.structured_document_id')
            ->where('structure.version', FinalCandidateManifestService::VERSION)
            ->whereLike('structure.doc_number', FinalCandidateManifestService::TRIAL_PREFIX . '%')
            ->where('structure.soft_delete', 0)
            ->where('block.soft_delete', 0);
    }

    private static function candidateBlockLinkQuery()
    {
        return Db::name('qms_document_block_links')->alias('link')
            ->join('qms_document_blocks block', 'block.id=link.block_id')
            ->join('qms_structured_documents structure', 'structure.id=block.structured_document_id')
            ->where('structure.version', FinalCandidateManifestService::VERSION)
            ->whereLike('structure.doc_number', FinalCandidateManifestService::TRIAL_PREFIX . '%')
            ->where('structure.soft_delete', 0)
            ->where('block.soft_delete', 0)
            ->where('link.soft_delete', 0)
            ->where('link.publish', 1);
    }

    private static function candidateManualBlockLinkQuery()
    {
        return self::candidateBlockLinkQuery()
            ->where('structure.document_role', 'quality_manual');
    }

    private static function candidateWorkInstructionElementDocumentQuery()
    {
        return Db::name('qms_element_documents')->alias('link')
            ->join('documents document', 'document.id=link.document_id')
            ->join('qms_structured_documents structure', 'structure.document_id=document.id')
            ->where('document.version', FinalCandidateManifestService::VERSION)
            ->whereLike('document.doc_number', FinalCandidateManifestService::TRIAL_PREFIX . 'XZTC/ZY-%')
            ->where('document.soft_delete', 0)
            ->where('structure.document_role', 'work_instruction')
            ->whereNotLike('document.doc_number', FinalCandidateManifestService::TRIAL_PREFIX . 'XZTC/ZY-1-01-%')
            ->where('link.soft_delete', 0)
            ->where('link.publish', 1)
            ->count('DISTINCT document.id');
    }

    private static function candidateWorkInstructionBlockLinkedDocuments(): int
    {
        return (int)Db::name('qms_document_block_links')->alias('link')
            ->join('qms_document_blocks block', 'block.id=link.block_id')
            ->join('qms_structured_documents structure', 'structure.id=block.structured_document_id')
            ->where('structure.version', FinalCandidateManifestService::VERSION)
            ->whereLike('structure.doc_number', FinalCandidateManifestService::TRIAL_PREFIX . 'XZTC/ZY-%')
            ->where('structure.document_role', 'work_instruction')
            ->whereNotLike('structure.doc_number', FinalCandidateManifestService::TRIAL_PREFIX . 'XZTC/ZY-1-01-%')
            ->where('structure.soft_delete', 0)
            ->where('block.soft_delete', 0)
            ->where('link.soft_delete', 0)
            ->where('link.publish', 1)
            ->count('DISTINCT structure.id');
    }

    private static function oldBlockLinkQuery()
    {
        return Db::name('qms_document_block_links')->alias('link')
            ->join('qms_document_blocks block', 'block.id=link.block_id')
            ->join('qms_structured_documents structure', 'structure.id=block.structured_document_id')
            ->where('block.soft_delete', 0)
            ->where('link.soft_delete', 0)
            ->where('link.publish', 1)
            ->whereRaw('NOT (structure.version = ? AND structure.doc_number LIKE ?)', [
                FinalCandidateManifestService::VERSION,
                FinalCandidateManifestService::TRIAL_PREFIX . '%',
            ]);
    }

    private static function candidateElementDocumentQuery()
    {
        return Db::name('qms_element_documents')->alias('link')
            ->join('documents document', 'document.id=link.document_id')
            ->where('document.version', FinalCandidateManifestService::VERSION)
            ->whereLike('document.doc_number', FinalCandidateManifestService::TRIAL_PREFIX . '%')
            ->where('document.soft_delete', 0)
            ->where('link.soft_delete', 0)
            ->where('link.publish', 1);
    }

    private static function oldElementDocumentQuery()
    {
        return Db::name('qms_element_documents')->alias('link')
            ->join('documents document', 'document.id=link.document_id')
            ->where('link.soft_delete', 0)
            ->where('link.publish', 1)
            ->whereRaw('NOT (document.version = ? AND document.doc_number LIKE ?)', [
                FinalCandidateManifestService::VERSION,
                FinalCandidateManifestService::TRIAL_PREFIX . '%',
            ]);
    }

    private static function nonCandidateDocumentQuery()
    {
        return Db::name('documents')
            ->where('soft_delete', 0)
            ->whereRaw('NOT (version = ? AND doc_number LIKE ?)', [
                FinalCandidateManifestService::VERSION,
                FinalCandidateManifestService::TRIAL_PREFIX . '%',
            ]);
    }

    private static function nonCandidateStructureQuery()
    {
        return Db::name('qms_structured_documents')
            ->where('soft_delete', 0)
            ->whereRaw('NOT (version = ? AND doc_number LIKE ?)', [
                FinalCandidateManifestService::VERSION,
                FinalCandidateManifestService::TRIAL_PREFIX . '%',
            ]);
    }

    private static function planElementDocumentMigration(): array
    {
        $candidateByCore = self::candidateDocumentsByCore();
        $rows = [];
        $skipped = [];
        $sources = Db::name('qms_element_documents')->alias('link')
            ->join('documents document', 'document.id=link.document_id')
            ->field('link.*,document.doc_number AS source_doc_number,document.version AS source_version')
            ->where('link.soft_delete', 0)
            ->where('link.publish', 1)
            ->whereRaw('NOT (document.version = ? AND document.doc_number LIKE ?)', [
                FinalCandidateManifestService::VERSION,
                FinalCandidateManifestService::TRIAL_PREFIX . '%',
            ])
            ->select()
            ->toArray();
        foreach ($sources as $source) {
            $core = self::docCore((string)$source['source_doc_number']);
            $candidate = $candidateByCore[$core] ?? null;
            if (!is_array($candidate)) {
                $skipped[] = [
                    'source_doc_number' => (string)$source['source_doc_number'],
                    'source_version' => (string)$source['source_version'],
                    'reason' => 'no_candidate_doc_number_match',
                ];
                continue;
            }
            $rows[] = [
                'source' => $source,
                'candidate_document_id' => (string)$candidate['id'],
                'candidate_doc_number' => (string)$candidate['doc_number'],
            ];
        }

        return [
            'rows' => $rows,
            'skipped' => $skipped,
            'summary' => [
                'source_links' => count($sources),
                'mappable_links' => count($rows),
                'skipped_links' => count($skipped),
                'target_documents' => count(array_unique(array_column($rows, 'candidate_doc_number'))),
            ],
        ];
    }

    private static function planBlockLinkMigration(): array
    {
        $candidateStructures = self::candidateStructuresByCore();
        $candidateBlocks = self::candidateBlocksByStructure();
        $documentIdMap = self::sourceDocumentIdToCandidateId();
        $rows = [];
        $skipped = [];
        $sources = Db::name('qms_document_block_links')->alias('link')
            ->join('qms_document_blocks block', 'block.id=link.block_id')
            ->join('qms_structured_documents structure', 'structure.id=block.structured_document_id')
            ->field('link.*,block.title AS source_block_title,block.block_type AS source_block_type,structure.doc_number AS source_doc_number,structure.version AS source_version')
            ->where('link.soft_delete', 0)
            ->where('link.publish', 1)
            ->whereRaw('NOT (structure.version = ? AND structure.doc_number LIKE ?)', [
                FinalCandidateManifestService::VERSION,
                FinalCandidateManifestService::TRIAL_PREFIX . '%',
            ])
            ->select()
            ->toArray();
        foreach ($sources as $source) {
            $relationType = (string)$source['relation_type'];
            if (!in_array($relationType, self::ALLOWED_BLOCK_RELATIONS, true)) {
                $skipped[] = self::skipBlockRow($source, 'relation_type_not_in_candidate_scope');
                continue;
            }
            if (self::idOrNull($source['record_form_template_id'] ?? null) !== null) {
                $skipped[] = self::skipBlockRow($source, 'record_form_template_requires_human_review');
                continue;
            }
            $core = self::docCore((string)$source['source_doc_number']);
            $targetStructure = $candidateStructures[$core] ?? null;
            if (!is_array($targetStructure)) {
                $skipped[] = self::skipBlockRow($source, 'no_candidate_structure_match');
                continue;
            }
            $targetBlock = self::matchCandidateBlock(
                $candidateBlocks[(string)$targetStructure['id']] ?? [],
                (string)$source['source_block_type'],
                (string)$source['source_block_title']
            );
            if (!is_array($targetBlock)) {
                $skipped[] = self::skipBlockRow($source, 'no_candidate_block_title_match');
                continue;
            }
            $procedureDocumentId = self::idOrNull($source['procedure_document_id'] ?? null);
            $rows[] = [
                'source' => $source,
                'candidate_block_id' => (string)$targetBlock['id'],
                'candidate_doc_number' => (string)$targetStructure['doc_number'],
                'procedure_document_id' => $procedureDocumentId !== null ? ($documentIdMap[$procedureDocumentId] ?? null) : null,
                'procedure_document_unmapped' => $procedureDocumentId !== null && !isset($documentIdMap[$procedureDocumentId]),
            ];
        }

        return [
            'rows' => $rows,
            'skipped' => $skipped,
            'summary' => [
                'source_links' => count($sources),
                'mappable_links' => count($rows),
                'skipped_links' => count($skipped),
                'skipped_requires_record_template_links' => count(array_filter(
                    $skipped,
                    static fn(array $row): bool => ($row['reason'] ?? '') === 'record_form_template_requires_human_review'
                )),
                'target_documents' => count(array_unique(array_column($rows, 'candidate_doc_number'))),
            ],
        ];
    }

    private static function formalizeCandidateRows(): void
    {
        $now = date('Y-m-d H:i:s');
        foreach (Db::name('documents')
            ->where('version', FinalCandidateManifestService::VERSION)
            ->whereLike('doc_number', FinalCandidateManifestService::TRIAL_PREFIX . '%')
            ->select()
            ->toArray() as $document) {
            Db::name('documents')->where('id', (string)$document['id'])->update([
                'title' => self::formalTitle((string)$document['title']),
                'status' => 'published',
                'publish' => 1,
                'soft_delete' => 0,
                'effective_date' => '2026-08-20',
                'modified' => $now,
            ]);
        }

        foreach (Db::name('qms_structured_documents')
            ->where('version', FinalCandidateManifestService::VERSION)
            ->whereLike('doc_number', FinalCandidateManifestService::TRIAL_PREFIX . '%')
            ->select()
            ->toArray() as $structure) {
            Db::name('qms_structured_documents')->where('id', (string)$structure['id'])->update([
                'title' => self::formalTitle((string)$structure['title']),
                'source_status' => 'current',
                'render_status' => 'rendered',
                'status' => 'published',
                'publish' => 1,
                'soft_delete' => 0,
                'modified' => $now,
            ]);
        }

        Db::name('qms_document_blocks')->alias('block')
            ->join('qms_structured_documents structure', 'structure.id=block.structured_document_id')
            ->where('structure.version', FinalCandidateManifestService::VERSION)
            ->whereLike('structure.doc_number', FinalCandidateManifestService::TRIAL_PREFIX . '%')
            ->whereLike('block.stable_key', 'gov03_%')
            ->update([
                'block.status' => 'effective',
                'block.publish' => 1,
                'block.soft_delete' => 0,
                'block.modified' => $now,
            ]);

        self::hideStaleCandidateBlocks();
    }

    private static function applyElementDocumentMigration(array $rows): array
    {
        $created = 0;
        $updated = 0;
        $now = date('Y-m-d H:i:s');
        $companyId = (string)Config::get('qms.company_id');
        foreach ($rows as $row) {
            $source = $row['source'];
            $relationType = in_array((string)$source['relation_type'], ['primary', 'reference'], true)
                ? (string)$source['relation_type']
                : 'reference';
            $existing = Db::name('qms_element_documents')
                ->where('element_id', (string)$source['element_id'])
                ->where('document_id', (string)$row['candidate_document_id'])
                ->where('relation_type', $relationType)
                ->find();
            $payload = [
                'company_id' => $companyId,
                'element_id' => (string)$source['element_id'],
                'document_id' => (string)$row['candidate_document_id'],
                'relation_type' => $relationType,
                'note' => self::appendNote(
                    (string)($source['note'] ?? ''),
                    '8021链路同步：由' . (string)$source['source_doc_number'] . '/' . (string)$source['source_version'] . '机械迁移；仅限测试环境。'
                ),
                'publish' => 1,
                'soft_delete' => 0,
                'modified' => $now,
            ];
            if (is_array($existing)) {
                Db::name('qms_element_documents')->where('id', (string)$existing['id'])->update($payload);
                $updated++;
            } else {
                $payload['id'] = qms_uuid();
                $payload['created'] = $now;
                Db::name('qms_element_documents')->insert($payload);
                $created++;
            }
        }

        return [
            'created' => $created,
            'updated' => $updated,
            'source_links' => count($rows),
            'target_documents' => count(array_unique(array_column($rows, 'candidate_doc_number'))),
        ];
    }

    private static function applyBlockLinkMigration(array $rows): array
    {
        $created = 0;
        $updated = 0;
        $reviewRequired = 0;
        $procedureUnmapped = 0;
        $now = date('Y-m-d H:i:s');
        $companyId = (string)Config::get('qms.company_id');
        foreach ($rows as $row) {
            $source = $row['source'];
            if (($row['procedure_document_unmapped'] ?? false) === true) {
                $procedureUnmapped++;
            }
            $confidence = (string)($source['confidence'] ?? 'medium');
            if ((string)$source['relation_type'] === 'requires_record') {
                $confidence = 'review_required';
                $reviewRequired++;
            }
            $payload = [
                'company_id' => $companyId,
                'block_id' => (string)$row['candidate_block_id'],
                'element_id' => self::idOrNull($source['element_id'] ?? null),
                'clause_id' => self::idOrNull($source['clause_id'] ?? null),
                'manual_section_id' => self::idOrNull($source['manual_section_id'] ?? null),
                'procedure_document_id' => $row['procedure_document_id'] ?? null,
                'record_form_template_id' => null,
                'position_id' => self::idOrNull($source['position_id'] ?? null),
                'business_module_id' => self::idOrNull($source['business_module_id'] ?? null),
                'relation_type' => (string)$source['relation_type'],
                'confidence' => in_array($confidence, ['high', 'medium', 'low', 'review_required'], true) ? $confidence : 'medium',
                'note' => self::appendNote(
                    (string)($source['note'] ?? ''),
                    '8021链路同步：由' . (string)$source['source_doc_number'] . '/' . (string)$source['source_version'] . '章节“' . (string)$source['source_block_title'] . '”机械迁移；记录表单模板不自动挂接。'
                ),
                'publish' => 1,
                'soft_delete' => 0,
                'modified' => $now,
            ];
            $existing = self::findExistingBlockLink($payload);
            if (is_array($existing)) {
                Db::name('qms_document_block_links')->where('id', (string)$existing['id'])->update($payload);
                $updated++;
            } else {
                $payload['id'] = qms_uuid();
                $payload['created'] = $now;
                Db::name('qms_document_block_links')->insert($payload);
                $created++;
            }
        }

        return [
            'created' => $created,
            'updated' => $updated,
            'source_links' => count($rows),
            'target_documents' => count(array_unique(array_column($rows, 'candidate_doc_number'))),
            'review_required_links' => $reviewRequired,
            'procedure_document_unmapped_links' => $procedureUnmapped,
        ];
    }

    private static function applySupplementalReviewLinks(): array
    {
        $companyId = (string)Config::get('qms.company_id');
        $elements = self::activeElementsByKey();
        $createdElementDocuments = 0;
        $updatedElementDocuments = 0;
        $createdBlockLinks = 0;
        $updatedBlockLinks = 0;
        $manual = self::candidateStructureByDocNumber(FinalCandidateManifestService::TRIAL_PREFIX . 'XZTC/SC-2026');
        if (is_array($manual)) {
            $blocks = self::blocksByTitle((string)$manual['id']);
            $manualDocumentId = (string)$manual['document_id'];
            foreach ($elements as $key => $element) {
                $block = self::manualBlockForElement($key, $blocks);
                if (!is_array($block)) {
                    continue;
                }
                $elementResult = self::upsertElementDocument(
                    $companyId,
                    (string)$element['id'],
                    $manualDocumentId,
                    'reference',
                    '8021补链复核：质量手册总览覆盖该要素；待质量负责人逐条复核后再调整为正式确认链路。'
                );
                $createdElementDocuments += $elementResult['created'];
                $updatedElementDocuments += $elementResult['updated'];
                $blockResult = self::upsertReviewBlockLink(
                    $companyId,
                    (string)$block['id'],
                    (string)$element['id'],
                    'basis',
                    '8021补链复核：质量手册“' . (string)$block['title'] . '”与要素“' . (string)$element['name'] . '”建立待复核总览链路。'
                );
                $createdBlockLinks += $blockResult['created'];
                $updatedBlockLinks += $blockResult['updated'];
            }
        }

        $workInstructionDocs = self::candidateWorkInstructions();
        foreach ($workInstructionDocs as $document) {
            $elementKeys = self::workInstructionElementKeys((string)$document['doc_number'], (string)$document['title']);
            if ($elementKeys === []) {
                continue;
            }
            $blocks = self::blocksByTitle((string)$document['structured_document_id']);
            $primaryBlock = $blocks['目的'] ?? $blocks['范围'] ?? $blocks['正文'] ?? null;
            $secondaryBlock = $blocks['范围'] ?? $primaryBlock;
            foreach (array_values(array_unique($elementKeys)) as $index => $key) {
                $element = $elements[$key] ?? null;
                if (!is_array($element)) {
                    continue;
                }
                $elementResult = self::upsertElementDocument(
                    $companyId,
                    (string)$element['id'],
                    (string)$document['document_id'],
                    $index === 0 ? 'primary' : 'reference',
                    '8021补链复核：按作业指导书题名和用途映射到要素“' . (string)$element['name'] . '”；待技术/质量负责人复核。'
                );
                $createdElementDocuments += $elementResult['created'];
                $updatedElementDocuments += $elementResult['updated'];
                $block = $index === 0 ? $primaryBlock : $secondaryBlock;
                if (!is_array($block)) {
                    continue;
                }
                $blockResult = self::upsertReviewBlockLink(
                    $companyId,
                    (string)$block['id'],
                    (string)$element['id'],
                    'implements',
                    '8021补链复核：按作业指导书“' . (string)$document['title'] . '”题名/用途建立待复核要素链路；不自动挂接记录表单。'
                );
                $createdBlockLinks += $blockResult['created'];
                $updatedBlockLinks += $blockResult['updated'];
            }
        }

        return [
            'element_documents_created' => $createdElementDocuments,
            'element_documents_updated' => $updatedElementDocuments,
            'block_links_created' => $createdBlockLinks,
            'block_links_updated' => $updatedBlockLinks,
            'manual_elements_review_required' => is_array($manual) ? count($elements) : 0,
            'work_instruction_documents_review_required' => count($workInstructionDocs),
            'notice' => '补链均为8021测试环境review_required/待人工复核，不自动挂接记录表单模板。',
        ];
    }

    private static function upsertElementDocument(string $companyId, string $elementId, string $documentId, string $relationType, string $note): array
    {
        $now = date('Y-m-d H:i:s');
        $existing = Db::name('qms_element_documents')
            ->where('element_id', $elementId)
            ->where('document_id', $documentId)
            ->where('relation_type', $relationType)
            ->find();
        $payload = [
            'company_id' => $companyId,
            'element_id' => $elementId,
            'document_id' => $documentId,
            'relation_type' => $relationType,
            'note' => self::appendNote((string)($existing['note'] ?? ''), $note),
            'publish' => 1,
            'soft_delete' => 0,
            'modified' => $now,
        ];
        if (is_array($existing)) {
            Db::name('qms_element_documents')->where('id', (string)$existing['id'])->update($payload);
            return ['created' => 0, 'updated' => 1];
        }
        $payload['id'] = qms_uuid();
        $payload['created'] = $now;
        Db::name('qms_element_documents')->insert($payload);
        return ['created' => 1, 'updated' => 0];
    }

    private static function upsertReviewBlockLink(string $companyId, string $blockId, string $elementId, string $relationType, string $note): array
    {
        $now = date('Y-m-d H:i:s');
        $payload = [
            'company_id' => $companyId,
            'block_id' => $blockId,
            'element_id' => $elementId,
            'clause_id' => null,
            'manual_section_id' => null,
            'procedure_document_id' => null,
            'record_form_template_id' => null,
            'position_id' => null,
            'business_module_id' => null,
            'relation_type' => $relationType,
            'confidence' => 'review_required',
            'note' => $note,
            'publish' => 1,
            'soft_delete' => 0,
            'modified' => $now,
        ];
        $existing = self::findExistingBlockLink($payload);
        if (is_array($existing)) {
            $payload['note'] = self::appendNote((string)($existing['note'] ?? ''), $note);
            Db::name('qms_document_block_links')->where('id', (string)$existing['id'])->update($payload);
            return ['created' => 0, 'updated' => 1];
        }
        $payload['id'] = qms_uuid();
        $payload['created'] = $now;
        Db::name('qms_document_block_links')->insert($payload);
        return ['created' => 1, 'updated' => 0];
    }

    private static function hideNonCandidateFormalRows(): array
    {
        $now = date('Y-m-d H:i:s');
        $oldDocs = self::nonCandidateDocumentQuery()->column('id');
        $oldStructures = self::nonCandidateStructureQuery()->column('id');
        $oldBlockLinks = self::oldBlockLinkQuery()->column('link.id');
        $oldElementDocuments = self::oldElementDocumentQuery()->column('link.id');

        if ($oldElementDocuments !== []) {
            Db::name('qms_element_documents')->whereIn('id', $oldElementDocuments)->update([
                'publish' => 0,
                'soft_delete' => 1,
                'modified' => $now,
            ]);
        }
        if ($oldBlockLinks !== []) {
            Db::name('qms_document_block_links')->whereIn('id', $oldBlockLinks)->update([
                'publish' => 0,
                'soft_delete' => 1,
                'modified' => $now,
            ]);
        }
        if ($oldDocs !== []) {
            Db::name('documents')->whereIn('id', $oldDocs)->update([
                'status' => 'obsolete',
                'publish' => 0,
                'soft_delete' => 1,
                'modified' => $now,
            ]);
        }
        if ($oldStructures !== []) {
            Db::name('qms_structured_documents')->whereIn('id', $oldStructures)->update([
                'status' => 'obsolete',
                'source_status' => 'obsolete',
                'publish' => 0,
                'soft_delete' => 1,
                'modified' => $now,
            ]);
        }

        return [
            'documents' => count($oldDocs),
            'structures' => count($oldStructures),
            'element_documents' => count($oldElementDocuments),
            'block_links' => count($oldBlockLinks),
        ];
    }

    private static function hideStaleCandidateBlocks(): void
    {
        $now = date('Y-m-d H:i:s');
        $staleIds = Db::name('qms_document_blocks')->alias('block')
            ->join('qms_structured_documents structure', 'structure.id=block.structured_document_id')
            ->where('structure.version', FinalCandidateManifestService::VERSION)
            ->whereLike('structure.doc_number', FinalCandidateManifestService::TRIAL_PREFIX . '%')
            ->whereNotLike('block.stable_key', 'gov03_%')
            ->column('block.id');
        if ($staleIds === []) {
            return;
        }
        Db::name('qms_document_block_links')->whereIn('block_id', $staleIds)->update([
            'publish' => 0,
            'soft_delete' => 1,
            'modified' => $now,
        ]);
        Db::name('qms_document_blocks')->whereIn('id', $staleIds)->update([
            'status' => 'obsolete',
            'publish' => 0,
            'soft_delete' => 1,
            'modified' => $now,
        ]);
    }

    private static function activeElementsByKey(): array
    {
        $rows = Db::name('qms_elements')
            ->where('publish', 1)
            ->where('soft_delete', 0)
            ->order('sort_order')
            ->select()
            ->toArray();
        $result = [];
        foreach ($rows as $row) {
            $result[(string)$row['key']] = $row;
        }
        return $result;
    }

    private static function candidateStructureByDocNumber(string $docNumber): ?array
    {
        $row = Db::name('qms_structured_documents')
            ->where('version', FinalCandidateManifestService::VERSION)
            ->where('doc_number', $docNumber)
            ->where('soft_delete', 0)
            ->find();
        return is_array($row) ? $row : null;
    }

    private static function blocksByTitle(string $structuredDocumentId): array
    {
        $rows = Db::name('qms_document_blocks')
            ->where('structured_document_id', $structuredDocumentId)
            ->where('soft_delete', 0)
            ->whereLike('stable_key', 'gov03_%')
            ->order('sort_order')
            ->select()
            ->toArray();
        $result = [];
        foreach ($rows as $row) {
            $result[(string)$row['title']] = $row;
        }
        return $result;
    }

    private static function manualBlockForElement(string $elementKey, array $blocks): ?array
    {
        if (in_array($elementKey, ['impartiality', 'confidentiality'], true)) {
            return $blocks['目的'] ?? $blocks['正文'] ?? null;
        }
        if (in_array($elementKey, [
            'structure',
            'personnel',
            'internal_audit',
            'management_review',
            'management_system_documents',
            'document_control',
            'management_system_options',
        ], true)) {
            return $blocks['附录'] ?? $blocks['正文'] ?? null;
        }
        return $blocks['范围'] ?? $blocks['正文'] ?? null;
    }

    private static function candidateWorkInstructions(): array
    {
        return Db::name('qms_structured_documents')->alias('structure')
            ->join('documents document', 'document.id=structure.document_id')
            ->field('structure.id AS structured_document_id,structure.doc_number,structure.title,structure.document_id,document.id')
            ->where('structure.version', FinalCandidateManifestService::VERSION)
            ->whereLike('structure.doc_number', FinalCandidateManifestService::TRIAL_PREFIX . 'XZTC/ZY-%')
            ->where('structure.document_role', 'work_instruction')
            ->whereNotLike('structure.doc_number', FinalCandidateManifestService::TRIAL_PREFIX . 'XZTC/ZY-1-01-%')
            ->where('structure.soft_delete', 0)
            ->where('document.soft_delete', 0)
            ->order('structure.doc_number')
            ->select()
            ->toArray();
    }

    private static function workInstructionElementKeys(string $docNumber, string $title): array
    {
        $core = self::docCore($docNumber);
        $map = [
            'XZTC/ZY-1-02' => ['data_information', 'technical_records'],
            'XZTC/ZY-1-03' => ['technical_records', 'results_reporting'],
            'XZTC/ZY-1-04' => ['data_information', 'technical_records', 'results_reporting'],
            'XZTC/ZY-1-05' => ['results_reporting', 'document_control'],
            'XZTC/ZY-1-06' => ['results_reporting', 'document_control'],
            'XZTC/ZY-2-01' => ['equipment', 'metrological_traceability'],
            'XZTC/ZY-2-02' => ['methods', 'equipment'],
            'XZTC/ZY-2-03' => ['methods', 'equipment'],
            'XZTC/ZY-2-04' => ['methods', 'equipment'],
            'XZTC/ZY-2-05' => ['methods', 'equipment'],
            'XZTC/ZY-2-06' => ['methods', 'equipment'],
            'XZTC/ZY-2-07' => ['methods', 'equipment'],
            'XZTC/ZY-2-08' => ['methods', 'equipment'],
            'XZTC/ZY-2-09' => ['equipment', 'metrological_traceability'],
            'XZTC/ZY-2-10' => ['facilities_environment', 'equipment'],
            'XZTC/ZY-2-11' => ['methods', 'equipment'],
            'XZTC/ZY-2-12' => ['methods', 'equipment'],
            'XZTC/ZY-2-13' => ['methods', 'equipment'],
            'XZTC/ZY-2-14' => ['methods', 'equipment', 'metrological_traceability'],
            'XZTC/ZY-2-15' => ['methods', 'equipment', 'measurement_uncertainty'],
            'XZTC/ZY-2-16' => ['methods', 'equipment'],
            'XZTC/ZY-2-17' => ['methods', 'equipment'],
            'XZTC/ZY-2-18' => ['methods', 'equipment'],
            'XZTC/ZY-3-01' => ['equipment', 'validity_of_results', 'metrological_traceability'],
            'XZTC/ZY-3-02' => ['equipment', 'validity_of_results', 'metrological_traceability'],
            'XZTC/ZY-4-01' => ['methods', 'item_handling', 'technical_records'],
            'XZTC/ZY-4-02' => ['methods', 'item_handling'],
            'XZTC/ZY-4-03' => ['methods', 'item_handling'],
        ];
        if (isset($map[$core])) {
            return $map[$core];
        }
        if (str_contains($title, '期间核查')) {
            return ['equipment', 'validity_of_results', 'metrological_traceability'];
        }
        if (str_contains($title, '证书')) {
            return ['results_reporting', 'document_control'];
        }
        if (str_contains($title, '数据') || str_contains($title, '图片')) {
            return ['data_information', 'technical_records'];
        }
        if (str_contains($title, '检测')) {
            return ['methods', 'item_handling'];
        }
        if (str_contains($title, '仪') || str_contains($title, '镜') || str_contains($title, '灯') || str_contains($title, '天平')) {
            return ['methods', 'equipment'];
        }
        return [];
    }

    private static function candidateDocumentsByCore(): array
    {
        $rows = Db::name('documents')
            ->where('version', FinalCandidateManifestService::VERSION)
            ->whereLike('doc_number', FinalCandidateManifestService::TRIAL_PREFIX . '%')
            ->select()
            ->toArray();
        $result = [];
        foreach ($rows as $row) {
            $result[self::docCore((string)$row['doc_number'])] = $row;
        }
        return $result;
    }

    private static function candidateStructuresByCore(): array
    {
        $rows = Db::name('qms_structured_documents')
            ->where('version', FinalCandidateManifestService::VERSION)
            ->whereLike('doc_number', FinalCandidateManifestService::TRIAL_PREFIX . '%')
            ->select()
            ->toArray();
        $result = [];
        foreach ($rows as $row) {
            $result[self::docCore((string)$row['doc_number'])] = $row;
        }
        return $result;
    }

    private static function candidateBlocksByStructure(): array
    {
        $rows = self::candidateBlockQuery()
            ->field('block.*')
            ->whereLike('block.stable_key', 'gov03_%')
            ->select()
            ->toArray();
        $result = [];
        foreach ($rows as $row) {
            $structuredId = (string)$row['structured_document_id'];
            $result[$structuredId]['by_key'][self::blockKey((string)$row['block_type'], (string)$row['title'])] = $row;
            $result[$structuredId]['by_type'][(string)$row['block_type']][] = $row;
        }
        return $result;
    }

    private static function sourceDocumentIdToCandidateId(): array
    {
        $candidateByCore = self::candidateDocumentsByCore();
        $rows = Db::name('documents')->field('id,doc_number')->select()->toArray();
        $map = [];
        foreach ($rows as $row) {
            $candidate = $candidateByCore[self::docCore((string)$row['doc_number'])] ?? null;
            if (is_array($candidate)) {
                $map[(string)$row['id']] = (string)$candidate['id'];
            }
        }
        return $map;
    }

    private static function matchCandidateBlock(array $index, string $sourceType, string $sourceTitle): ?array
    {
        $key = self::blockKey($sourceType, $sourceTitle);
        if (isset($index['by_key'][$key]) && is_array($index['by_key'][$key])) {
            return $index['by_key'][$key];
        }
        $typeRows = $index['by_type'][$sourceType] ?? [];
        if (count($typeRows) === 1) {
            return $typeRows[0];
        }
        return null;
    }

    private static function findExistingBlockLink(array $payload): ?array
    {
        $query = Db::name('qms_document_block_links')
            ->where('block_id', (string)$payload['block_id'])
            ->where('relation_type', (string)$payload['relation_type']);
        foreach ([
            'element_id',
            'clause_id',
            'manual_section_id',
            'procedure_document_id',
            'record_form_template_id',
            'position_id',
            'business_module_id',
        ] as $field) {
            $value = $payload[$field] ?? null;
            if ($value === null || $value === '') {
                $query->whereNull($field);
            } else {
                $query->where($field, (string)$value);
            }
        }
        $existing = $query->find();
        return is_array($existing) ? $existing : null;
    }

    private static function protectedFingerprint(): array
    {
        $payloads = [
            'record_form_templates' => Db::name('record_form_templates')
                ->field('id,doc_number,version,status,trial_batch,publish,soft_delete,modified')
                ->order('id')->select()->toArray(),
            'record_form_instances' => Db::name('record_form_instances')
                ->field('id,doc_number,status,is_simulation,trial_batch,modified')
                ->order('id')->select()->toArray(),
        ];
        $result = [];
        foreach ($payloads as $table => $rows) {
            $result[$table] = [
                'count' => count($rows),
                'sha256' => hash('sha256', self::json($rows)),
            ];
        }
        return $result;
    }

    private static function skipBlockRow(array $source, string $reason): array
    {
        return [
            'source_doc_number' => (string)($source['source_doc_number'] ?? ''),
            'source_version' => (string)($source['source_version'] ?? ''),
            'source_block_title' => (string)($source['source_block_title'] ?? ''),
            'source_block_type' => (string)($source['source_block_type'] ?? ''),
            'relation_type' => (string)($source['relation_type'] ?? ''),
            'reason' => $reason,
        ];
    }

    private static function docCore(string $docNumber): string
    {
        $value = strtoupper(trim($docNumber));
        $value = preg_replace('/^SIM-GOV0[0-9]-/u', '', $value) ?? $value;
        $value = preg_replace('/-(?:19|20)[0-9]{2}$/u', '', $value) ?? $value;
        return $value;
    }

    private static function blockKey(string $type, string $title): string
    {
        return trim($type) . ':' . self::normalizeTitle($title);
    }

    private static function normalizeTitle(string $title): string
    {
        $value = trim($title);
        $value = preg_replace('/^[0-9]+(?:\.[0-9A-Za-z]+)*[\.、\s]+/u', '', $value) ?? $value;
        $value = preg_replace('/[：:；;，,。\s]+/u', '', $value) ?? $value;
        return mb_strtolower($value, 'UTF-8');
    }

    private static function idOrNull(mixed $value): ?string
    {
        $string = trim((string)$value);
        return $string === '' ? null : $string;
    }

    private static function formalTitle(string $title): string
    {
        $title = trim($title);
        if (str_starts_with($title, self::FORMAL_TITLE_PREFIX)) {
            return $title;
        }
        if (str_starts_with($title, self::LEGACY_TITLE_PREFIX)) {
            return self::FORMAL_TITLE_PREFIX . trim(substr($title, strlen(self::LEGACY_TITLE_PREFIX)));
        }
        return self::FORMAL_TITLE_PREFIX . $title;
    }

    private static function appendNote(string $old, string $addition): string
    {
        $old = trim($old);
        if (str_contains($old, $addition)) {
            return $old;
        }
        $note = $old === '' ? $addition : $old . "\n" . $addition;
        return mb_substr($note, 0, 1200, 'UTF-8');
    }

    private static function resolvedOutputDir(?string $outputDir): string
    {
        $outputDir = trim((string)$outputDir);
        if ($outputDir !== '') {
            return rtrim($outputDir, DIRECTORY_SEPARATOR);
        }
        if (is_dir('/.team')) {
            return '/.team/交接箱/2026-08-21-8021链路同步';
        }
        return dirname(__DIR__, 3) . '/.team/交接箱/2026-08-21-8021链路同步';
    }

    private static function writeReport(array $result, string $outputDir): array
    {
        if (!is_dir($outputDir) && !mkdir($outputDir, 0775, true) && !is_dir($outputDir)) {
            throw new RuntimeException('无法创建链路同步报告目录：' . $outputDir);
        }
        self::writeJson($outputDir . '/01-链路同步报告-v0.1.json', $result);
        self::writeText($outputDir . '/02-链路同步验收说明-v0.1.md', self::markdownReport($result));
        return [
            'output_dir' => $outputDir,
            'files' => [
                '01-链路同步报告-v0.1.json',
                '02-链路同步验收说明-v0.1.md',
            ],
        ];
    }

    private static function markdownReport(array $result): string
    {
        $counts = (array)($result['validation']['counts'] ?? []);
        $migration = (array)($result['migration'] ?? []);
        $supplement = (array)($migration['supplemental_review_links'] ?? []);
        return "# 8021链路同步验收说明 v0.1\n\n"
            . "- 范围：仅8021测试环境测试正式视图。\n"
            . "- 边界：纸质体系仍是唯一正式体系；不触碰8010、不迁移真实记录、不自动挂接记录表单模板。\n"
            . "- 0.3测试正式制度：{$counts['candidate_documents']}份。\n"
            . "- 质量要素：{$counts['active_elements']}个；要素-条款基础链路：{$counts['element_clause_links']}条。\n"
            . "- 0.3制度-要素链路：{$counts['candidate_element_documents']}条。\n"
            . "- 0.3章节链路：{$counts['candidate_block_links']}条；其中记录表单模板直连：{$counts['candidate_template_block_links']}条。\n"
            . "- 隐藏旧版本现行制度：{$counts['active_non_candidate_documents']}份；隐藏旧章节链路后剩余：{$counts['old_active_block_links']}条。\n\n"
            . "## 本次迁移\n\n"
            . "- 制度-要素：新增 " . (string)($migration['element_documents']['created'] ?? 0)
            . "，更新 " . (string)($migration['element_documents']['updated'] ?? 0) . "。\n"
            . "- 章节链路：新增 " . (string)($migration['block_links']['created'] ?? 0)
            . "，更新 " . (string)($migration['block_links']['updated'] ?? 0) . "。\n"
            . "- `requires_record` 且带真实表单模板ID的链路未自动迁移，保留为人工复核事项。\n\n"
            . "## 补链复核\n\n"
            . "- 补充制度—要素对应：新增 " . (string)($supplement['element_documents_created'] ?? 0)
            . "，更新 " . (string)($supplement['element_documents_updated'] ?? 0) . "。\n"
            . "- 补充待复核章节链路：新增 " . (string)($supplement['block_links_created'] ?? 0)
            . "，更新 " . (string)($supplement['block_links_updated'] ?? 0) . "。\n"
            . "- 质量手册待复核覆盖要素：" . (string)($counts['candidate_manual_block_links'] ?? 0) . " 个。\n"
            . "- 非废止作业指导书要素对应：" . (string)($counts['candidate_work_instruction_element_documents'] ?? 0) . " 份。\n"
            . "- 非废止作业指导书待复核章节链路：" . (string)($counts['candidate_work_instruction_block_linked_documents'] ?? 0) . " 份。\n"
            . "- 补链状态：均为 `review_required` 或备注待人工复核，不代表正式批准。\n";
    }

    private static function writeJson(string $path, array $data): void
    {
        self::writeText(
            $path,
            json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . PHP_EOL
        );
    }

    private static function writeText(string $path, string $content): void
    {
        if (file_put_contents($path, $content) === false) {
            throw new RuntimeException('无法写入链路同步材料：' . $path);
        }
    }

    private static function json(array $value): string
    {
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }
}
