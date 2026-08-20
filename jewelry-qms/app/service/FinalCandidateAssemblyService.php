<?php
declare(strict_types=1);

namespace app\service;

use DomainException;
use RuntimeException;
use think\facade\Config;
use think\facade\Db;

final class FinalCandidateAssemblyService
{
    public static function preview(string $sourceDir): array
    {
        $manifest = FinalCandidateManifestService::build($sourceDir);
        if (($manifest['validation']['ok'] ?? false) !== true) {
            throw new RuntimeException('候选来源清单校验未通过：' . implode('；', $manifest['validation']['errors'] ?? []));
        }

        $documents = [];
        $patches = [];
        $errors = [];
        foreach ($manifest['documents'] as $document) {
            $body = QmsDocumentStructureService::markdownFromSourcePath((string)$document['absolute_path'], 0);
            if (trim($body) === '') {
                $errors[] = (string)$document['canonical_doc_number'] . ' 未提取到正文';
                continue;
            }
            $resolved = FinalCandidateManifestService::resolveRecommendedTimeMarkers($body);
            foreach ($resolved['patches'] as $patch) {
                $patch['target_doc_number'] = (string)$document['canonical_doc_number'];
                $patch['source_path'] = (string)$document['absolute_path'];
                $patch['source_sha256'] = (string)$document['source_sha256'];
                $patches[] = $patch;
            }
            $resolvedBody = (string)$resolved['content'];
            $documents[] = $document + [
                'resolved_body' => $resolvedBody,
                'resolved_text_sha256' => hash('sha256', $resolvedBody),
                'time_patch_count' => count($resolved['patches']),
                'review_flags' => self::reviewFlags((string)$document['canonical_doc_number'], $resolvedBody),
                'rendered_markdown' => self::renderHeader($document) . $resolvedBody,
            ];
        }

        if (count($documents) !== 65) {
            $errors[] = '成功解析正文数量应为65，当前为' . count($documents);
        }

        return [
            'mode' => 'inspect_only',
            'version' => FinalCandidateManifestService::VERSION,
            'trial_batch' => FinalCandidateManifestService::TRIAL_BATCH,
            'manifest' => $manifest,
            'validation' => ['ok' => $errors === [], 'errors' => $errors],
            'summary' => [
                'documents_planned' => count($documents),
                'draft_planned' => count(array_filter($documents, static fn(array $row): bool => $row['status'] === 'draft')),
                'obsolete_planned' => count(array_filter($documents, static fn(array $row): bool => $row['status'] === 'obsolete')),
                'published_planned' => 0,
                'record_form_templates_planned' => 0,
                'record_instances_planned' => 0,
                'time_patch_count' => count($patches),
                'review_required' => 64,
                'reference_only' => 1,
            ],
            'time_patches' => $patches,
            'decision_shortlist' => self::decisionShortlist(),
            'documents' => $documents,
            'formal_system_notice' => '仅限8021隔离环境候选试装；不构成审核、批准、发布或正式迁移。',
        ];
    }

    public static function apply(string $sourceDir, ?string $outputDir = null): array
    {
        self::assertWritableTrialEnvironment();
        self::assertSchema();
        $preview = self::preview($sourceDir);
        if (($preview['validation']['ok'] ?? false) !== true) {
            throw new RuntimeException('候选正文预览未通过，不得写入');
        }

        $outputDir = self::resolvedOutputDir($outputDir);
        self::writePackage($preview, $outputDir, 'dry-run');
        $protectedBefore = self::protectedFingerprint();
        $oldVersionCounts = self::oldVersionCounts();
        $companyId = (string)Config::get('qms.company_id');

        Db::transaction(function () use ($preview, $outputDir, $companyId, $protectedBefore, $oldVersionCounts): void {
            foreach ($preview['documents'] as $document) {
                self::upsertCandidateDocument($document, $outputDir, $companyId);
            }

            $verification = self::verify();
            if (($verification['ok'] ?? false) !== true) {
                throw new RuntimeException('候选写入事务验证失败：' . implode('；', $verification['errors'] ?? []));
            }
            if (self::protectedFingerprint() !== $protectedBefore) {
                throw new RuntimeException('候选写入触碰了既有记录模板或记录实例，事务已回滚');
            }
            if (self::oldVersionCounts() !== $oldVersionCounts) {
                throw new RuntimeException('候选写入改变了GOV-TRIAL/0.1或0.2，事务已回滚');
            }
        });

        $verification = self::verify();
        $result = $preview;
        $result['mode'] = 'trial_apply';
        $result['source_validation'] = $preview['validation'];
        $result['validation'] = $verification;
        $result['database'] = $verification['counts'] ?? [];
        $result['package'] = self::writePackage($result, $outputDir, 'apply');

        return $result;
    }

    public static function verify(): array
    {
        $errors = [];
        $documents = Db::name('documents')
            ->where('version', FinalCandidateManifestService::VERSION)
            ->whereLike('doc_number', FinalCandidateManifestService::TRIAL_PREFIX . '%')
            ->where('soft_delete', 0)
            ->select()
            ->toArray();
        $structures = Db::name('qms_structured_documents')
            ->where('version', FinalCandidateManifestService::VERSION)
            ->whereLike('doc_number', FinalCandidateManifestService::TRIAL_PREFIX . '%')
            ->where('soft_delete', 0)
            ->select()
            ->toArray();
        $blocks = (int)Db::name('qms_document_blocks')->alias('block')
            ->join('qms_structured_documents structure', 'structure.id=block.structured_document_id')
            ->where('structure.version', FinalCandidateManifestService::VERSION)
            ->whereLike('structure.doc_number', FinalCandidateManifestService::TRIAL_PREFIX . '%')
            ->where('structure.soft_delete', 0)
            ->where('block.soft_delete', 0)
            ->count();
        $drafts = count(array_filter($documents, static fn(array $row): bool => $row['status'] === 'draft'));
        $obsolete = count(array_filter($documents, static fn(array $row): bool => $row['status'] === 'obsolete'));
        $published = count(array_filter($documents, static fn(array $row): bool => $row['status'] === 'published'));
        $nonCandidatePublished = count(array_filter($documents, static fn(array $row): bool => (int)$row['publish'] !== 0));

        if (count($documents) !== 65) {
            $errors[] = '0.3候选文件数量应为65，当前为' . count($documents);
        }
        if (count($structures) !== 65) {
            $errors[] = '0.3结构化候选数量应为65，当前为' . count($structures);
        }
        if ($drafts !== 64 || $obsolete !== 1 || $published !== 0) {
            $errors[] = "候选状态应为64份草稿、1份废止、0份发布，当前为{$drafts}/{$obsolete}/{$published}";
        }
        if ($nonCandidatePublished !== 0) {
            $errors[] = '候选文件publish标志必须全部为0';
        }
        if ($blocks < 65) {
            $errors[] = '每份候选至少应形成一个内容块';
        }
        $templateLinks = (int)Db::name('qms_document_block_links')->alias('link')
            ->join('qms_document_blocks block', 'block.id=link.block_id')
            ->join('qms_structured_documents structure', 'structure.id=block.structured_document_id')
            ->where('structure.version', FinalCandidateManifestService::VERSION)
            ->whereNotNull('link.record_form_template_id')
            ->where('link.soft_delete', 0)
            ->count();
        if ($templateLinks !== 0) {
            $errors[] = '第一轮候选存在记录模板链接';
        }

        return [
            'ok' => $errors === [],
            'errors' => $errors,
            'counts' => [
                'candidate_documents' => count($documents),
                'candidate_structures' => count($structures),
                'candidate_blocks' => $blocks,
                'draft_documents' => $drafts,
                'obsolete_documents' => $obsolete,
                'published_documents' => $published,
                'record_form_templates' => (int)Db::name('record_form_templates')->where('soft_delete', 0)->count(),
                'trial_ready_templates' => (int)Db::name('record_form_templates')->where('status', 'trial_ready')->where('soft_delete', 0)->count(),
                'record_instances' => (int)Db::name('record_form_instances')->count(),
                'template_links' => $templateLinks,
            ],
        ];
    }

    public static function writableEnvironmentErrors(): array
    {
        $errors = [];
        if (!TrialModeService::isEnabled()) {
            $errors[] = 'QMS_TRIAL_MODE 未启用';
        }
        if (TrialModeService::trialBatch() !== GovernedTrialAssemblyBlueprintService::TRIAL_BATCH) {
            $errors[] = 'QMS_TRIAL_BATCH 必须为 ' . GovernedTrialAssemblyBlueprintService::TRIAL_BATCH;
        }
        return $errors;
    }

    public static function writePackage(array $result, string $outputDir, string $mode): array
    {
        $outputDir = rtrim(trim($outputDir), DIRECTORY_SEPARATOR);
        if ($outputDir === '') {
            throw new RuntimeException('候选试装报告目录不能为空');
        }
        if (!is_dir($outputDir) && !mkdir($outputDir, 0775, true) && !is_dir($outputDir)) {
            throw new RuntimeException('无法创建候选试装报告目录：' . $outputDir);
        }
        $bodyDir = $outputDir . '/06-候选连续正文';
        if (!is_dir($bodyDir) && !mkdir($bodyDir, 0775, true) && !is_dir($bodyDir)) {
            throw new RuntimeException('无法创建候选连续正文目录');
        }

        self::writeJson($outputDir . '/01-来源清单-v0.1.json', $result['manifest'] ?? []);
        self::writeText($outputDir . '/02-排除材料清单-v0.1.md', self::excludedMarkdown($result));
        self::writeJson($outputDir . '/03-时限裁决补丁-v0.1.json', [
            'version' => 'v0.1',
            'status' => 'candidate_only',
            'patches' => $result['time_patches'] ?? [],
        ]);
        self::writeText($outputDir . '/04-关键待决事项-v0.1.md', self::decisionMarkdown($result));

        $report = self::withoutBodies($result);
        $report['package_mode'] = $mode;
        self::writeJson($outputDir . '/05-干跑报告-v0.1.json', $report);
        foreach ($result['documents'] ?? [] as $document) {
            $fileName = self::safeToken((string)$document['canonical_doc_number'] . '-' . (string)$document['title']) . '.md';
            self::writeText($bodyDir . '/' . $fileName, (string)$document['rendered_markdown']);
        }
        if ($mode === 'apply') {
            self::writeJson($outputDir . '/07-写入报告-v0.1.json', $report);
        }
        self::writeText($outputDir . '/版本台账.md', self::versionLedger($mode));

        return [
            'mode' => $mode,
            'output_dir' => $outputDir,
            'file_count' => self::recursiveFileCount($outputDir),
        ];
    }

    private static function decisionShortlist(): array
    {
        return [
            [
                'id' => 'cross_document_conflicts',
                'title' => '电子签章、用纸和LIMS验证记录的跨文件冲突',
                'documents' => ['XZTC/CX-26-2026', 'XZTC/ZY-1-05-2026', 'XZTC/ZY-1-06-2026'],
                'disposition' => '保留原文并标记review_required；正式发布前由质量负责人和主任技术负责人裁决。',
            ],
            [
                'id' => 'supervisor_qualification_evidence',
                'title' => '两名监督员任职条件证据未核档',
                'documents' => ['XZTC/CX-31-2026'],
                'disposition' => '不推定人员胜任；补齐学历、经历、培训和授权证据后再关闭。',
            ],
            [
                'id' => 'cx19_retention_rule',
                'title' => '离岗或停用后保存6年的口径待确认',
                'documents' => ['XZTC/CX-19-2026'],
                'disposition' => '候选试装按原文展示，不转成系统强制规则。',
            ],
            [
                'id' => 'ag990_capability_path',
                'title' => 'Ag990标准物质和不确定度证据链未闭合',
                'documents' => ['XZTC/CX-03-02-2026', 'XZTC/ZY-2-15-2026'],
                'disposition' => '业务能力保持阻断，不生成符合性判定规则。',
            ],
            [
                'id' => 'sample_lock_form_number',
                'title' => '留样锁定月度清单表号未核定',
                'documents' => ['XZTC/CX-28-2026'],
                'disposition' => '本轮不装表单；仅保留待定引用。',
            ],
            [
                'id' => 'lims_administrator_role',
                'title' => '质量手册及人员程序尚须补LIMS系统管理员岗位',
                'documents' => ['XZTC/SC-2026', 'XZTC/CX-01-2026'],
                'disposition' => '不自动补写正文；列入人工复核清单。',
            ],
        ];
    }

    private static function reviewFlags(string $docNumber, string $body): array
    {
        $flags = [];
        foreach ([
            'contains_blocking_marker' => '🛑',
            'contains_pending_fact' => '◇',
            'contains_blank_marker' => '＿＿',
            'contains_provisional_value' => '暂定',
        ] as $id => $needle) {
            if (str_contains($body, $needle)) {
                $flags[] = $id;
            }
        }
        foreach (self::decisionShortlist() as $decision) {
            if (in_array($docNumber, $decision['documents'], true)) {
                $flags[] = (string)$decision['id'];
            }
        }

        return array_values(array_unique($flags));
    }

    private static function renderHeader(array $document): string
    {
        return '# [8021候选试装] ' . (string)$document['title'] . "\n\n"
            . '- 候选编号：' . (string)$document['trial_doc_number'] . "\n"
            . '- 原文件编号：' . (string)$document['canonical_doc_number'] . "\n"
            . '- 候选版本：' . FinalCandidateManifestService::VERSION . "\n"
            . '- 状态：' . (string)$document['status'] . '/' . (string)$document['review_class'] . "\n"
            . '- 来源 SHA-256：' . (string)$document['source_sha256'] . "\n"
            . '- 边界：仅用于8021隔离试装；纸质体系仍为唯一正式体系。' . "\n\n---\n\n";
    }

    private static function assertWritableTrialEnvironment(): void
    {
        $errors = self::writableEnvironmentErrors();
        if ($errors !== []) {
            throw new DomainException('8021候选试装拒绝写入：' . implode('；', $errors));
        }
    }

    private static function assertSchema(): void
    {
        foreach ([
            'documents' => ['supersedes_document_id', 'revision_root_id', 'change_reason'],
            'qms_structured_documents' => ['document_role', 'source_status', 'review_note'],
            'qms_document_blocks' => ['stable_key', 'source_locator', 'block_type'],
        ] as $table => $columns) {
            $available = Db::query('SHOW COLUMNS FROM `' . $table . '`');
            $index = array_fill_keys(array_map(static fn(array $row): string => (string)$row['Field'], $available), true);
            foreach ($columns as $column) {
                if (!isset($index[$column])) {
                    throw new RuntimeException('数据库缺少候选试装字段：' . $table . '.' . $column);
                }
            }
        }
    }

    private static function upsertCandidateDocument(array $document, string $outputDir, string $companyId): void
    {
        $existing = Db::name('documents')
            ->where('doc_number', (string)$document['trial_doc_number'])
            ->where('version', FinalCandidateManifestService::VERSION)
            ->where('soft_delete', 0)
            ->find();
        if (is_array($existing)) {
            $reason = json_decode((string)($existing['change_reason'] ?? ''), true);
            $existingHash = is_array($reason) ? (string)($reason['source_sha256'] ?? '') : '';
            if ($existingHash !== '' && !hash_equals($existingHash, (string)$document['source_sha256'])) {
                throw new RuntimeException((string)$document['canonical_doc_number'] . ' 来源哈希漂移，拒绝覆盖0.3候选');
            }
        }

        $prior = self::priorCandidateDocument((string)$document['canonical_doc_number']);
        $documentId = (string)($existing['id'] ?? qms_uuid());
        $priorId = (string)($prior['id'] ?? '');
        $fileName = self::safeToken((string)$document['canonical_doc_number'] . '-' . (string)$document['title']) . '.md';
        $relativeOutput = self::relativeTeamPath($outputDir) . '/06-候选连续正文/' . $fileName;
        $now = date('Y-m-d H:i:s');
        $documentRow = [
            'company_id' => $companyId,
            'level' => (int)$document['level'],
            'doc_number' => (string)$document['trial_doc_number'],
            'title' => '[8021候选试装] ' . (string)$document['title'],
            'version' => FinalCandidateManifestService::VERSION,
            'revision' => 3,
            'effective_date' => null,
            'review_date' => null,
            'status' => (string)$document['status'],
            'file_path' => $relativeOutput,
            'file_name' => $fileName,
            'file_type' => 'md',
            'prepared_by' => null,
            'reviewed_by' => null,
            'approved_by' => null,
            'change_reason' => self::json([
                'notice' => '仅限8021隔离环境候选试装；不得审核、批准、发布或作为正式运行证据。',
                'trial_batch' => FinalCandidateManifestService::TRIAL_BATCH,
                'canonical_doc_number' => (string)$document['canonical_doc_number'],
                'source_snapshot' => '源文件快照/' . (string)$document['file_name'],
                'source_sha256' => (string)$document['source_sha256'],
                'resolved_text_sha256' => (string)$document['resolved_text_sha256'],
                'review_class' => (string)$document['review_class'],
                'review_flags' => (array)$document['review_flags'],
                'time_patch_count' => (int)$document['time_patch_count'],
            ]),
            'supersedes_document_id' => (string)($existing['supersedes_document_id'] ?? '') !== ''
                ? (string)$existing['supersedes_document_id']
                : ($priorId !== '' ? $priorId : null),
            'revision_root_id' => (string)($existing['revision_root_id'] ?? '') !== ''
                ? (string)$existing['revision_root_id']
                : (string)($prior['revision_root_id'] ?? $priorId ?: $documentId),
            'publish' => 0,
            'soft_delete' => 0,
            'modified' => $now,
        ];
        if (is_array($existing)) {
            Db::name('documents')->where('id', $documentId)->update($documentRow);
        } else {
            $documentRow['id'] = $documentId;
            $documentRow['created'] = $now;
            Db::name('documents')->insert($documentRow);
        }

        $existingStructure = Db::name('qms_structured_documents')
            ->where('doc_number', (string)$document['trial_doc_number'])
            ->where('version', FinalCandidateManifestService::VERSION)
            ->where('soft_delete', 0)
            ->find();
        $structuredId = (string)($existingStructure['id'] ?? qms_uuid());
        $structureRow = [
            'company_id' => $companyId,
            'source_asset_id' => null,
            'document_id' => $documentId,
            'document_role' => (string)$document['document_role'],
            'doc_number' => (string)$document['trial_doc_number'],
            'title' => '[8021候选试装] ' . (string)$document['title'],
            'version' => FinalCandidateManifestService::VERSION,
            'source_status' => 'draft',
            'markdown_path' => $relativeOutput,
            'rendered_file_path' => $relativeOutput,
            'render_status' => 'rendered',
            'status' => (string)$document['status'],
            'review_note' => '状态：' . (string)$document['review_class']
                . '；来源SHA-256：' . (string)$document['source_sha256']
                . '；正式发布前须关闭关键待决事项。',
            'publish' => 1,
            'soft_delete' => 0,
            'modified' => $now,
        ];
        if (is_array($existingStructure)) {
            Db::name('qms_structured_documents')->where('id', $structuredId)->update($structureRow);
        } else {
            $structureRow['id'] = $structuredId;
            $structureRow['created'] = $now;
            Db::name('qms_structured_documents')->insert($structureRow);
        }

        self::upsertBlocks($structuredId, $documentId, $companyId, $document);
    }

    private static function upsertBlocks(string $structuredId, string $documentId, string $companyId, array $document): void
    {
        $existing = Db::name('qms_document_blocks')
            ->where('structured_document_id', $structuredId)
            ->select()
            ->toArray();
        $byKey = [];
        foreach ($existing as $row) {
            $byKey[(string)$row['stable_key']] = $row;
        }
        $activeIds = [];
        foreach (self::splitMarkdown((string)$document['resolved_body']) as $index => $block) {
            $stableKey = 'gov03_' . str_pad((string)($index + 1), 3, '0', STR_PAD_LEFT)
                . '_' . substr(hash('sha256', (string)$block['heading']), 0, 12);
            $current = $byKey[$stableKey] ?? null;
            $blockId = (string)($current['id'] ?? qms_uuid());
            $activeIds[] = $blockId;
            $row = [
                'company_id' => $companyId,
                'structured_document_id' => $structuredId,
                'document_id' => $documentId,
                'parent_id' => null,
                'stable_key' => $stableKey,
                'section_number' => (string)$block['section_number'],
                'title' => (string)$block['title'],
                'block_type' => self::blockType((string)$block['title']),
                'markdown' => (string)$block['markdown'],
                'sort_order' => ($index + 1) * 10,
                'source_locator' => (string)$document['source_sha256'] . '#' . (string)$block['heading'],
                'status' => (string)$document['status'],
                'publish' => 1,
                'soft_delete' => 0,
                'modified' => date('Y-m-d H:i:s'),
            ];
            if (is_array($current)) {
                Db::name('qms_document_blocks')->where('id', $blockId)->update($row);
            } else {
                $row['id'] = $blockId;
                $row['created'] = date('Y-m-d H:i:s');
                Db::name('qms_document_blocks')->insert($row);
            }
        }
        $staleIds = array_values(array_diff(array_column($existing, 'id'), $activeIds));
        if ($staleIds !== []) {
            Db::name('qms_document_blocks')->whereIn('id', $staleIds)->update([
                'soft_delete' => 1,
                'modified' => date('Y-m-d H:i:s'),
            ]);
        }
    }

    private static function splitMarkdown(string $markdown): array
    {
        $lines = preg_split('/\R/u', $markdown) ?: [];
        $blocks = [];
        $heading = '正文';
        $buffer = [];
        $flush = static function () use (&$blocks, &$heading, &$buffer): void {
            $content = trim(implode("\n", $buffer));
            if ($content === '') {
                $buffer = [];
                return;
            }
            $sectionNumber = '';
            $title = $heading;
            if (preg_match('/^([0-9]+(?:\.[0-9A-Za-z]+)*)[\.、\s]+(.+)$/u', $heading, $match)) {
                $sectionNumber = (string)$match[1];
                $title = trim((string)$match[2]);
            }
            $blocks[] = [
                'heading' => $heading,
                'section_number' => $sectionNumber,
                'title' => $title,
                'markdown' => ($heading !== '正文' ? '## ' . $heading . "\n\n" : '') . $content,
            ];
            $buffer = [];
        };
        foreach ($lines as $line) {
            if (preg_match('/^##\s+(.+)$/u', trim($line), $match)) {
                $flush();
                $heading = trim((string)$match[1]);
                continue;
            }
            if (str_starts_with(trim($line), '# ')) {
                continue;
            }
            $buffer[] = $line;
        }
        $flush();

        return $blocks !== [] ? $blocks : [[
            'heading' => '正文',
            'section_number' => '',
            'title' => '正文',
            'markdown' => trim($markdown),
        ]];
    }

    private static function blockType(string $title): string
    {
        foreach ([
            '目的' => 'purpose',
            '范围' => 'scope',
            '职责' => 'responsibility',
            '工作程序' => 'process_step',
            '记录' => 'record_requirement',
        ] as $needle => $type) {
            if (str_contains($title, $needle)) {
                return $type;
            }
        }
        return 'text';
    }

    private static function priorCandidateDocument(string $canonical): ?array
    {
        if ($canonical === 'XZTC/SC-2026') {
            $priorNumber = 'SIM-GOV02-XZTC/SC';
        } elseif (str_starts_with($canonical, 'XZTC/CX-')) {
            $priorNumber = 'SIM-GOV02-' . preg_replace('/-2026$/', '-2022', $canonical);
        } else {
            return null;
        }
        $prior = Db::name('documents')
            ->where('doc_number', $priorNumber)
            ->where('version', 'GOV-TRIAL/0.2')
            ->where('soft_delete', 0)
            ->find();
        return is_array($prior) ? $prior : null;
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

    private static function oldVersionCounts(): array
    {
        return [
            'GOV-TRIAL/0.1' => (int)Db::name('qms_structured_documents')
                ->where('version', 'GOV-TRIAL/0.1')->where('soft_delete', 0)->count(),
            'GOV-TRIAL/0.2' => (int)Db::name('qms_structured_documents')
                ->where('version', 'GOV-TRIAL/0.2')->where('soft_delete', 0)->count(),
        ];
    }

    private static function resolvedOutputDir(?string $outputDir): string
    {
        $outputDir = trim((string)$outputDir);
        if ($outputDir !== '') {
            return rtrim($outputDir, DIRECTORY_SEPARATOR);
        }
        if (is_dir('/.team')) {
            return '/.team/交接箱/2026-08-20-8021候选试装';
        }
        return dirname(__DIR__, 3) . '/.team/交接箱/2026-08-20-8021候选试装';
    }

    private static function relativeTeamPath(string $outputDir): string
    {
        if (str_starts_with($outputDir, '/.team/')) {
            return '.team/' . ltrim(substr($outputDir, strlen('/.team/')), '/');
        }
        $marker = DIRECTORY_SEPARATOR . '.team' . DIRECTORY_SEPARATOR;
        $position = strpos($outputDir, $marker);
        if ($position !== false) {
            return '.team/' . str_replace(DIRECTORY_SEPARATOR, '/', substr($outputDir, $position + strlen($marker)));
        }
        throw new RuntimeException('写入报告目录必须位于.team/交接箱内');
    }

    private static function json(array $value): string
    {
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    private static function withoutBodies(array $result): array
    {
        foreach ($result['documents'] ?? [] as $index => $document) {
            unset($document['resolved_body'], $document['rendered_markdown']);
            $result['documents'][$index] = $document;
        }
        return $result;
    }

    private static function excludedMarkdown(array $result): string
    {
        $lines = [
            '# 排除材料清单 v0.1',
            '',
            '> 本清单中的材料不作为本轮8021受控文件装入。',
            '',
            '| 文件 | 原因 | SHA-256 |',
            '|---|---|---|',
        ];
        foreach ((array)($result['manifest']['excluded'] ?? []) as $row) {
            $lines[] = '| ' . str_replace('|', '\\|', (string)$row['file_name'])
                . ' | ' . str_replace('|', '\\|', (string)$row['reason'])
                . ' | `' . (string)$row['source_sha256'] . '` |';
        }
        return implode("\n", $lines) . "\n";
    }

    private static function decisionMarkdown(array $result): string
    {
        $lines = [
            '# 关键待决事项 v0.1',
            '',
            '> 这些事项不阻断8021草稿试装，但阻断审核、发布和正式迁移。',
        ];
        foreach ((array)($result['decision_shortlist'] ?? []) as $index => $row) {
            $lines[] = '';
            $lines[] = '## ' . ($index + 1) . '. ' . (string)$row['title'];
            $lines[] = '';
            $lines[] = '- 编号：`' . (string)$row['id'] . '`';
            $lines[] = '- 涉及文件：' . implode('、', (array)$row['documents']);
            $lines[] = '- 本轮处置：' . (string)$row['disposition'];
        }
        return implode("\n", $lines) . "\n";
    }

    private static function versionLedger(string $mode): string
    {
        return "# 版本台账\n\n"
            . "| 版本 | 日期 | 变更 | 状态 |\n"
            . "|---|---|---|---|\n"
            . "| v0.1 | 2026-08-20 | GOV-TRIAL/0.3 65份制度文件候选试装{$mode}包 | 现行候选 |\n";
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
            throw new RuntimeException('无法写入候选试装材料：' . $path);
        }
    }

    private static function safeToken(string $value): string
    {
        $value = preg_replace('/[^\p{Han}A-Za-z0-9._-]+/u', '-', $value) ?? $value;
        return trim($value, '-');
    }

    private static function recursiveFileCount(string $directory): int
    {
        $count = 0;
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(
            $directory,
            \FilesystemIterator::SKIP_DOTS
        ));
        foreach ($iterator as $item) {
            if ($item->isFile()) {
                $count++;
            }
        }
        return $count;
    }
}
