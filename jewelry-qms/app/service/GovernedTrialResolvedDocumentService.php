<?php
declare(strict_types=1);

namespace app\service;

use DomainException;
use RuntimeException;
use think\facade\Config;
use think\facade\Db;

final class GovernedTrialResolvedDocumentService
{
    public const DEFAULT_OUTPUT = '.team/交接箱/2026-07-25-8021治理体系试运行装配/GOV-TRIAL-0.2';

    public static function inspect(): array
    {
        $preview = self::preview();

        return [
            'mode' => 'inspect_only',
            'trial_mode_enabled' => TrialModeService::isEnabled(),
            'configured_trial_batch' => TrialModeService::trialBatch(),
            'summary' => $preview['summary'],
            'output_path' => self::absoluteOutputPath(self::DEFAULT_OUTPUT),
        ];
    }

    public static function resolveDownloadPath(string $relativePath): ?string
    {
        $relativePath = str_replace('\\', '/', trim($relativePath));
        if (!str_starts_with($relativePath, self::DEFAULT_OUTPUT . '/')) {
            return null;
        }

        $allowedRoot = realpath(self::workspaceRoot() . '/' . self::DEFAULT_OUTPUT);
        $realPath = realpath(self::workspaceRoot() . '/' . $relativePath);
        if (
            $allowedRoot === false
            || $realPath === false
            || !is_file($realPath)
            || !str_starts_with($realPath, $allowedRoot . '/')
        ) {
            return null;
        }

        return $realPath;
    }

    public static function writableEnvironmentErrors(bool $trialMode, string $trialBatch, string $databaseName): array
    {
        $errors = [];
        if (!$trialMode) {
            $errors[] = 'QMS_TRIAL_MODE 未启用';
        }
        if ($trialBatch !== 'GOV-TRIAL-20260724') {
            $errors[] = 'QMS_TRIAL_BATCH 不是8021隔离栈批次 GOV-TRIAL-20260724';
        }
        if ($databaseName !== 'jewelry_qms') {
            $errors[] = '数据库名称不是8021隔离栈预期的 jewelry_qms';
        }

        return $errors;
    }

    public static function apply(?string $outputPath = null): array
    {
        $databaseName = (string)Config::get('database.connections.mysql.database', '');
        $environmentErrors = self::writableEnvironmentErrors(
            TrialModeService::isEnabled(),
            TrialModeService::trialBatch(),
            $databaseName
        );
        if ($environmentErrors !== []) {
            throw new DomainException('连续解析稿拒绝写入：' . implode('；', $environmentErrors));
        }

        $preview = self::preview();
        $written = self::writePackage($preview, $outputPath);
        $companyId = (string)Config::get('qms.company_id');
        Db::transaction(function () use ($preview, $companyId): void {
            self::alignTrialRecordGovernance($companyId);
            foreach ($preview['documents'] as $document) {
                self::upsertResolvedDocument($document, $preview, $companyId);
            }
        });

        $verification = self::verifyDatabaseAssembly();
        if (($verification['ok'] ?? false) !== true) {
            throw new RuntimeException('GOV-TRIAL/0.2数据库装配验证失败：' . implode('；', $verification['errors'] ?? []));
        }

        return [
            'mode' => 'trial_apply',
            'trial_batch' => GovernedTrialResolvedManifestService::TRIAL_BATCH,
            'version' => GovernedTrialResolvedManifestService::VERSION,
            'package' => $written,
            'verification' => $verification,
            'summary' => $preview['summary'],
            'formal_system_notice' => '仅限8021隔离环境治理试运行；纸质体系仍为唯一正式体系。',
        ];
    }

    public static function verifyDatabaseAssembly(): array
    {
        $errors = [];
        $structures = Db::name('qms_structured_documents')
            ->where('version', GovernedTrialResolvedManifestService::VERSION)
            ->where('soft_delete', 0)
            ->select()
            ->toArray();
        if (count($structures) !== 38) {
            $errors[] = '0.2结构化文件应为38份，当前为' . count($structures);
        }
        $structureIds = array_values(array_map(
            static fn(array $row): string => (string)$row['id'],
            $structures
        ));
        $blockCount = $structureIds === []
            ? 0
            : (int)Db::name('qms_document_blocks')
                ->whereIn('structured_document_id', $structureIds)
                ->where('soft_delete', 0)
                ->count();
        if ($blockCount <= 38) {
            $errors[] = '连续正文未按实际标题拆分为多个内容块';
        }
        $recordLinkMismatches = $structureIds === [] ? 0 : self::recordLinkMismatchCount($structureIds);
        if ($recordLinkMismatches !== 0) {
            $errors[] = '存在' . $recordLinkMismatches . '条记录表关系未在对应记录块正文中出现';
        }
        foreach ($structures as $structure) {
            if (($structure['render_status'] ?? '') !== 'rendered') {
                $errors[] = (string)$structure['doc_number'] . ' 未标记为已渲染';
            }
            $path = self::workspaceRoot() . '/' . ltrim((string)($structure['rendered_file_path'] ?? ''), '/');
            if (!is_file($path)) {
                $errors[] = (string)$structure['doc_number'] . ' 连续正文文件不存在';
            }
        }
        $v01Count = (int)Db::name('qms_structured_documents')
            ->where('version', 'GOV-TRIAL/0.1')
            ->where('soft_delete', 0)
            ->count();
        if ($v01Count !== 38) {
            $errors[] = '0.1世系应保留38份，当前为' . $v01Count;
        }
        $bg3503 = Db::name('record_form_templates')->alias('template')
            ->leftJoin('documents procedure', 'procedure.id = template.procedure_doc_id')
            ->where('template.trial_batch', 'GOV-TRIAL-20260724')
            ->where('template.canonical_doc_number', 'XZTC/BG-35-03')
            ->where('template.soft_delete', 0)
            ->field('template.name,procedure.doc_number procedure_doc_number')
            ->find();
        if (
            !is_array($bg3503)
            || ($bg3503['name'] ?? '') !== '[治理试运行] 标准物质报废申请表'
            || ($bg3503['procedure_doc_number'] ?? '') !== 'SIM-XZTC/CX-03-02-2022'
        ) {
            $errors[] = 'BG-35-03未闭合到标准物质管理程序';
        }
        $bg3503ResolvedLinks = (int)Db::name('qms_document_block_links')->alias('link')
            ->join('qms_document_blocks block', 'block.id = link.block_id')
            ->join('qms_structured_documents structure', 'structure.id = block.structured_document_id')
            ->join('record_form_templates template', 'template.id = link.record_form_template_id')
            ->where('structure.version', GovernedTrialResolvedManifestService::VERSION)
            ->where('structure.doc_number', 'SIM-GOV02-XZTC/CX-03-02-2022')
            ->where('template.canonical_doc_number', 'XZTC/BG-35-03')
            ->where('block.soft_delete', 0)
            ->where('link.soft_delete', 0)
            ->count();
        if ($bg3503ResolvedLinks < 1) {
            $errors[] = 'BG-35-03未在0.2标准物质管理程序中建立记录追溯关系';
        }
        $samplingTemplates = (int)Db::name('record_form_templates')
            ->where('trial_batch', 'GOV-TRIAL-20260724')
            ->where('soft_delete', 0)
            ->whereLike('name', '%抽样%')
            ->count();
        if ($samplingTemplates !== 0) {
            $errors[] = '不开展抽样时不得保留活动抽样记录模板';
        }

        return [
            'ok' => $errors === [],
            'errors' => $errors,
            'structured_documents' => count($structures),
            'document_blocks' => $blockCount,
            'record_link_mismatches' => $recordLinkMismatches,
            'base_version_documents' => $v01Count,
            'bg3503_resolved_links' => $bg3503ResolvedLinks,
            'sampling_templates' => $samplingTemplates,
        ];
    }

    private static function alignTrialRecordGovernance(string $companyId): void
    {
        $template = Db::name('record_form_templates')
            ->where('trial_batch', 'GOV-TRIAL-20260724')
            ->where('canonical_doc_number', 'XZTC/BG-35-03')
            ->where('soft_delete', 0)
            ->find();
        $procedure = Db::name('documents')
            ->where('doc_number', 'SIM-XZTC/CX-03-02-2022')
            ->where('version', 'GOV-TRIAL/0.1')
            ->where('soft_delete', 0)
            ->find();
        $structure = Db::name('qms_structured_documents')
            ->where('doc_number', 'SIM-XZTC/CX-03-02-2022')
            ->where('version', 'GOV-TRIAL/0.1')
            ->where('soft_delete', 0)
            ->find();
        if (!is_array($template) || !is_array($procedure) || !is_array($structure)) {
            throw new RuntimeException('BG-35-03治理纠正失败：0.1模板或标准物质管理程序不存在');
        }

        $templateId = (string)$template['id'];
        $procedureId = (string)$procedure['id'];
        $now = date('Y-m-d H:i:s');
        Db::name('record_form_templates')->where('id', $templateId)->update([
            'name' => '[治理试运行] 标准物质报废申请表',
            'module' => '标准物质管理程序',
            'procedure_doc_id' => $procedureId,
            'review_note' => '按记录总台账v0.2和2026-07-25试运行阻断关闭决定纠正：BG-35-03属于标准物质管理记录。',
            'modified' => $now,
        ]);
        Db::name('qms_document_block_links')
            ->where('record_form_template_id', $templateId)
            ->where('soft_delete', 0)
            ->update([
                'procedure_document_id' => $procedureId,
                'modified' => $now,
            ]);

        $wrongProcedureLinkIds = Db::name('qms_document_block_links')->alias('link')
            ->join('qms_document_blocks block', 'block.id = link.block_id')
            ->join('qms_structured_documents structure', 'structure.id = block.structured_document_id')
            ->where('link.record_form_template_id', $templateId)
            ->where('structure.version', 'GOV-TRIAL/0.1')
            ->where('structure.document_role', 'procedure')
            ->where('structure.doc_number', '<>', 'SIM-XZTC/CX-03-02-2022')
            ->where('block.soft_delete', 0)
            ->where('link.soft_delete', 0)
            ->column('link.id');
        if ($wrongProcedureLinkIds !== []) {
            Db::name('qms_document_block_links')->whereIn('id', $wrongProcedureLinkIds)->update([
                'soft_delete' => 1,
                'modified' => $now,
            ]);
        }

        $correctBlock = Db::name('qms_document_blocks')
            ->where('structured_document_id', (string)$structure['id'])
            ->where('soft_delete', 0)
            ->order('sort_order', 'asc')
            ->find();
        if (!is_array($correctBlock)) {
            throw new RuntimeException('BG-35-03治理纠正失败：标准物质管理程序没有可追溯内容块');
        }
        $correctLink = Db::name('qms_document_block_links')
            ->where('block_id', (string)$correctBlock['id'])
            ->where('record_form_template_id', $templateId)
            ->where('soft_delete', 0)
            ->find();
        if (!is_array($correctLink)) {
            Db::name('qms_document_block_links')->insert([
                'id' => qms_uuid(),
                'company_id' => $companyId,
                'block_id' => (string)$correctBlock['id'],
                'procedure_document_id' => $procedureId,
                'record_form_template_id' => $templateId,
                'relation_type' => 'requires_record',
                'confidence' => 'high',
                'note' => '治理试运行纠正：BG-35-03由标准物质管理程序要求并提供运行证据。',
                'publish' => 1,
                'soft_delete' => 0,
                'created' => $now,
                'modified' => $now,
            ]);
        }
    }

    public static function resolvedArtifactLinks(array|object $structured): array
    {
        $data = is_array($structured) ? $structured : $structured->toArray();
        if (($data['version'] ?? '') !== GovernedTrialResolvedManifestService::VERSION) {
            return [];
        }
        $id = rawurlencode((string)$data['id']);
        $isObsolete = ($data['status'] ?? '') === 'obsolete'
            || str_contains((string)($data['doc_number'] ?? ''), 'CX-35-2022');
        $blockingCount = self::currentPackageBlockingCount();
        $canSubmit = !$isObsolete && $blockingCount === 0 && ($data['status'] ?? '') === 'draft';

        return [
            'is_resolved_trial' => true,
            'can_submit' => $canSubmit,
            'notice_class' => $canSubmit ? 'alert-success' : 'alert-warning',
            'blocking_message' => $isObsolete
                ? '本文件已在试运行版本中作废保留，不进入审核或发布链路。'
                : ($blockingCount === 0
                    ? '当前无阻断冲突，可在8021发起SIM审核；纸质体系仍为唯一正式体系。'
                    : '存在阻断冲突，不能提交审核；请先查看冲突审查并完成裁决。'),
            'continuous_url' => '/planning/structures/resolved-artifact?id=' . $id . '&kind=continuous',
            'comparison_url' => '/planning/structures/resolved-artifact?id=' . $id . '&kind=comparison',
            'conflicts_url' => '/planning/structures/resolved-artifact?id=' . $id . '&kind=conflicts',
        ];
    }

    public static function resolvedArtifact(string $structuredId, string $kind): array
    {
        $structure = Db::name('qms_structured_documents')
            ->where('id', $structuredId)
            ->where('version', GovernedTrialResolvedManifestService::VERSION)
            ->where('soft_delete', 0)
            ->find();
        if (!is_array($structure)) {
            return [];
        }
        $continuousPath = (string)$structure['markdown_path'];
        $root = dirname(dirname($continuousPath));
        $fileName = basename($continuousPath);
        $paths = [
            'continuous' => $continuousPath,
            'comparison' => $root . '/修订对照/' . $fileName,
            'conflicts' => $root . '/冲突审查/冲突总表.md',
        ];
        if (!isset($paths[$kind])) {
            return [];
        }
        $relativePath = $paths[$kind];
        $absolutePath = self::workspaceRoot() . '/' . ltrim($relativePath, '/');
        $allowedRoot = realpath(self::workspaceRoot() . '/' . ltrim(self::DEFAULT_OUTPUT, '/'));
        $realPath = realpath($absolutePath);
        if ($allowedRoot === false || $realPath === false || !str_starts_with($realPath, $allowedRoot . '/')) {
            return [];
        }
        $content = file_get_contents($realPath);
        if (!is_string($content)) {
            return [];
        }

        return [
            'title' => [
                'continuous' => '连续正文',
                'comparison' => '修订对照',
                'conflicts' => '冲突审查',
            ][$kind],
            'doc_number' => (string)$structure['doc_number'],
            'document_title' => (string)$structure['title'],
            'content' => $content,
            'relative_path' => $relativePath,
            'back_url' => '/planning/structures/view?id=' . rawurlencode($structuredId),
        ];
    }

    public static function preview(): array
    {
        $manifest = GovernedTrialResolvedManifestService::build();
        if (($manifest['validation']['ok'] ?? false) !== true) {
            throw new RuntimeException(
                '解析稿清单校验未通过：' . implode('；', $manifest['validation']['errors'] ?? [])
            );
        }

        $patchesByDocument = [];
        foreach ($manifest['patches'] as $patch) {
            $patchesByDocument[(string)$patch['target_doc_number']][] = $patch;
        }

        $documents = [];
        $resolvedBodies = [];
        $patchesApplied = 0;
        $patchConflicts = [];
        foreach ($manifest['baselines'] as $baseline) {
            $docNumber = (string)$baseline['doc_number'];
            $body = QmsDocumentStructureService::markdownFromSourcePath(
                (string)$baseline['relative_path'],
                0
            );
            if (!hash_equals((string)$baseline['text_sha256'], hash('sha256', $body))) {
                $result = [
                    'content' => $body,
                    'applied_patches' => [],
                    'blocking_conflicts' => [[
                        'patch_id' => 'BASELINE',
                        'type' => 'baseline_hash_drift',
                        'message' => '基线转换文本哈希已漂移，未应用任何补丁。',
                        'blocking' => true,
                    ]],
                    'warnings' => [],
                    'preservation_check' => ['ok' => false],
                ];
            } else {
                $result = GovernedTrialPatchEngine::apply($body, $patchesByDocument[$docNumber] ?? []);
            }

            $patchesApplied += count($result['applied_patches'] ?? []);
            foreach ($result['blocking_conflicts'] ?? [] as $conflict) {
                $conflict['doc_number'] = $docNumber;
                $patchConflicts[] = $conflict;
            }
            $resolvedBody = (string)$result['content'];
            $resolvedBodies[$docNumber] = $resolvedBody;
            $status = $docNumber === 'XZTC/CX-35-2022'
                ? 'obsolete'
                : (($result['blocking_conflicts'] ?? []) === [] ? 'trial_ready' : 'draft');
            $rendered = self::renderDocumentHeader(
                $baseline,
                $status,
                count($result['blocking_conflicts'] ?? [])
            ) . $resolvedBody;

            $documents[] = [
                'doc_number' => $docNumber,
                'title' => (string)$baseline['title'],
                'document_role' => (string)$baseline['document_role'],
                'source_version' => (string)$baseline['source_version'],
                'resolved_version' => GovernedTrialResolvedManifestService::VERSION,
                'status' => $status,
                'source_path' => (string)$baseline['relative_path'],
                'source_sha256' => (string)$baseline['source_sha256'],
                'baseline_text_sha256' => (string)$baseline['text_sha256'],
                'resolved_text_sha256' => hash('sha256', $resolvedBody),
                'registered_patches' => array_values(array_map(
                    static fn(array $patch): string => (string)$patch['patch_id'],
                    $patchesByDocument[$docNumber] ?? []
                )),
                'applied_patches' => $result['applied_patches'] ?? [],
                'blocking_conflicts' => $result['blocking_conflicts'] ?? [],
                'warnings' => $result['warnings'] ?? [],
                'preservation_check' => $result['preservation_check'] ?? ['ok' => false],
                'resolved_body' => $resolvedBody,
                'rendered_markdown' => $rendered,
            ];
        }

        $crossReview = GovernedTrialConflictReviewService::review($resolvedBodies);
        $deferredConflicts = [];
        foreach ($manifest['deferred_signed_items'] ?? [] as $index => $message) {
            $deferredConflicts[] = [
                'patch_id' => 'DEFERRED-' . str_pad((string)($index + 1), 3, '0', STR_PAD_LEFT),
                'type' => 'signed_item_not_anchored',
                'doc_number' => 'SYSTEM',
                'message' => (string)$message,
                'blocking' => true,
            ];
        }
        $blockingConflicts = array_merge(
            $patchConflicts,
            $crossReview['blocking_conflicts'] ?? [],
            $deferredConflicts
        );
        $warnings = $crossReview['warnings'] ?? [];
        foreach ($manifest['non_blocking_governance_notes'] ?? [] as $message) {
            $warnings[] = [
                'type' => 'trial_governance_backlog',
                'doc_number' => 'SYSTEM',
                'message' => (string)$message,
                'blocking' => false,
            ];
        }
        $manual = $resolvedBodies['XZTC/SC'] ?? '';
        $appendixChecks = [
            'appendix_14' => str_contains($manual, '附录14'),
            'appendix_15' => str_contains($manual, '附录15'),
            'appendix_16' => str_contains($manual, '附录16'),
        ];

        return [
            'manifest' => $manifest,
            'documents' => $documents,
            'blocking_conflicts' => $blockingConflicts,
            'warnings' => $warnings,
            'summary' => [
                'trial_batch' => GovernedTrialResolvedManifestService::TRIAL_BATCH,
                'version' => GovernedTrialResolvedManifestService::VERSION,
                'document_count' => count($documents),
                'manual_count' => count(array_filter(
                    $documents,
                    static fn(array $document): bool => $document['document_role'] === 'quality_manual'
                )),
                'procedure_count' => count(array_filter(
                    $documents,
                    static fn(array $document): bool => $document['document_role'] === 'procedure'
                )),
                'patches_registered' => count($manifest['patches']),
                'patches_applied' => $patchesApplied,
                'blocking_conflicts' => count($blockingConflicts),
                'warnings' => count($warnings),
                'appendix_checks' => $appendixChecks,
                'batch_trial_ready' => $blockingConflicts === [] && !in_array(false, $appendixChecks, true),
                'formal_system_notice' => (string)$manifest['formal_system_notice'],
            ],
        ];
    }

    public static function writePackage(array $preview, ?string $outputPath = null): array
    {
        $target = self::absoluteOutputPath($outputPath ?: self::DEFAULT_OUTPUT);
        $temporary = $target . '.tmp-' . getmypid() . '-' . bin2hex(random_bytes(4));
        $backup = $target . '.previous-' . getmypid();
        $preservedFiles = [];
        foreach (['实施验收记录.md'] as $fileName) {
            $existingPath = $target . '/' . $fileName;
            if (!is_file($existingPath)) {
                continue;
            }
            $content = file_get_contents($existingPath);
            if (!is_string($content)) {
                throw new RuntimeException('无法读取需要保留的人工验收文件：' . $existingPath);
            }
            $preservedFiles[$fileName] = $content;
        }

        self::mkdir($temporary . '/连续正文');
        self::mkdir($temporary . '/修订对照');
        self::mkdir($temporary . '/冲突审查');

        foreach ($preview['documents'] ?? [] as $document) {
            $token = self::safeToken((string)$document['doc_number'] . '-' . (string)$document['title']);
            $markdown = (string)$document['rendered_markdown'];
            self::write($temporary . '/连续正文/' . $token . '.md', $markdown);
            self::write($temporary . '/连续正文/' . $token . '.html', self::renderHtml($document));
            self::write(
                $temporary . '/修订对照/' . $token . '.md',
                self::renderRevisionComparison($document, $preview['manifest']['patches'] ?? [])
            );
        }

        self::write(
            $temporary . '/冲突审查/冲突总表.json',
            self::json([
                'blocking_conflicts' => $preview['blocking_conflicts'] ?? [],
                'warnings' => $preview['warnings'] ?? [],
            ])
        );
        self::write($temporary . '/冲突审查/冲突总表.md', self::renderConflictReport($preview));
        self::write($temporary . '/装配清单.json', self::json(self::packageManifest($preview)));
        self::write($temporary . '/验证报告.md', self::renderValidationReport($preview));
        self::write($temporary . '/版本台账.md', self::renderVersionLedger($preview));
        foreach ($preservedFiles as $fileName => $content) {
            self::write($temporary . '/' . $fileName, $content);
        }

        if (is_dir($backup)) {
            self::removeTree($backup);
        }
        if (is_dir($target) && !rename($target, $backup)) {
            self::removeTree($temporary);
            throw new RuntimeException('无法备份既有解析稿目录：' . $target);
        }
        if (!rename($temporary, $target)) {
            if (is_dir($backup)) {
                rename($backup, $target);
            }
            throw new RuntimeException('无法原子替换解析稿目录：' . $target);
        }
        if (is_dir($backup)) {
            self::removeTree($backup);
        }

        return [
            'output_path' => $target,
            'document_count' => count($preview['documents'] ?? []),
            'blocking_conflicts' => count($preview['blocking_conflicts'] ?? []),
            'batch_trial_ready' => (bool)($preview['summary']['batch_trial_ready'] ?? false),
        ];
    }

    private static function renderDocumentHeader(array $baseline, string $status, int $conflicts): string
    {
        return "# " . (string)$baseline['doc_number'] . ' ' . (string)$baseline['title'] . "\n\n"
            . "> **SIM｜治理试运行候选**\n"
            . "> \n"
            . "> 纸质体系仍为唯一正式体系；本文不是正式受控文件，不得作为评审证据或现场运行依据。\n\n"
            . "- 基线版本：" . (string)$baseline['source_version'] . "\n"
            . "- 解析稿版本：" . GovernedTrialResolvedManifestService::VERSION . "\n"
            . "- 生成批次：" . GovernedTrialResolvedManifestService::TRIAL_BATCH . "\n"
            . "- 文件状态：{$status}\n"
            . "- 本文件阻断冲突：{$conflicts}\n"
            . "- 生成方式：现用全文 + 已签认精确补丁；未命中内容保持原文。\n"
            . "- 基线原件 SHA-256：" . (string)$baseline['source_sha256'] . "\n\n"
            . "---\n\n";
    }

    private static function renderRevisionComparison(array $document, array $allPatches): string
    {
        $patchIndex = [];
        foreach ($allPatches as $patch) {
            $patchIndex[(string)$patch['patch_id']] = $patch;
        }
        $lines = [
            '# ' . (string)$document['doc_number'] . ' 修订对照',
            '',
            '- 文件：' . (string)$document['title'],
            '- 解析稿版本：' . (string)$document['resolved_version'],
            '- 状态：' . (string)$document['status'],
            '- 基线正文 SHA-256：' . (string)$document['baseline_text_sha256'],
            '- 解析正文 SHA-256：' . (string)$document['resolved_text_sha256'],
            '',
        ];
        $registered = $document['registered_patches'] ?? [];
        if ($registered === []) {
            $lines[] = '_本轮没有可安全自动落点的已签认补丁，正文按基线完整保留。_';
        }
        foreach ($registered as $patchId) {
            $patch = $patchIndex[(string)$patchId] ?? [];
            $applied = in_array($patchId, $document['applied_patches'] ?? [], true);
            $lines[] = '## ' . (string)$patchId;
            $lines[] = '';
            $lines[] = '- 结果：' . ($applied ? '已应用' : '已阻断，保留原文');
            $lines[] = '- 依据：' . (string)($patch['source_path'] ?? '');
            $lines[] = '- 签认：' . (string)($patch['approval_source_path'] ?? '');
            $lines[] = '- 原因：' . (string)($patch['reason'] ?? '');
            $lines[] = '';
            $lines[] = '### 原文';
            $lines[] = '';
            $lines[] = '```text';
            $lines[] = (string)($patch['anchor'] ?? '');
            $lines[] = '```';
            $lines[] = '';
            $lines[] = '### 修订后';
            $lines[] = '';
            $lines[] = '```text';
            $lines[] = (string)($patch['replacement_markdown'] ?? '');
            $lines[] = '```';
            $lines[] = '';
        }

        return implode("\n", $lines) . "\n";
    }

    private static function renderConflictReport(array $preview): string
    {
        $conflicts = $preview['blocking_conflicts'] ?? [];
        $warnings = $preview['warnings'] ?? [];
        $lines = [
            '# GOV-TRIAL/0.2 冲突总表',
            '',
            '> 阻断项未关闭前，批次不得进入模拟签批；原文继续保留。',
            '',
            '## 阻断冲突',
            '',
            '| 类型 | 文件 | 补丁 | 说明 |',
            '|---|---|---|---|',
        ];
        foreach ($conflicts as $row) {
            $lines[] = '| ' . self::cell((string)($row['type'] ?? ''))
                . ' | ' . self::cell((string)($row['doc_number'] ?? ''))
                . ' | ' . self::cell((string)($row['patch_id'] ?? ''))
                . ' | ' . self::cell((string)($row['message'] ?? '')) . ' |';
        }
        if ($conflicts === []) {
            $lines[] = '| - | - | - | 无 |';
        }
        $lines[] = '';
        $lines[] = '## 提醒';
        $lines[] = '';
        foreach ($warnings as $warning) {
            $lines[] = '- [' . (string)($warning['type'] ?? 'warning') . '] '
                . (string)($warning['doc_number'] ?? '') . '：' . (string)($warning['message'] ?? '');
        }
        if ($warnings === []) {
            $lines[] = '_无提醒。_';
        }

        return implode("\n", $lines) . "\n";
    }

    private static function renderValidationReport(array $preview): string
    {
        $summary = $preview['summary'] ?? [];
        $appendices = $summary['appendix_checks'] ?? [];

        return "# GOV-TRIAL/0.2 验证报告\n\n"
            . "- 文件总数：" . (int)($summary['document_count'] ?? 0) . "\n"
            . "- 质量手册：" . (int)($summary['manual_count'] ?? 0) . "\n"
            . "- 程序文件：" . (int)($summary['procedure_count'] ?? 0) . "\n"
            . "- 登记补丁：" . (int)($summary['patches_registered'] ?? 0) . "\n"
            . "- 已应用补丁：" . (int)($summary['patches_applied'] ?? 0) . "\n"
            . "- 阻断冲突：" . (int)($summary['blocking_conflicts'] ?? 0) . "\n"
            . "- 附录14：" . (!empty($appendices['appendix_14']) ? '存在' : '缺失') . "\n"
            . "- 附录15：" . (!empty($appendices['appendix_15']) ? '存在' : '缺失') . "\n"
            . "- 附录16：" . (!empty($appendices['appendix_16']) ? '存在' : '缺失') . "\n"
            . "- 批次可进入模拟签批：" . (!empty($summary['batch_trial_ready']) ? '是' : '否') . "\n\n"
            . "> " . (string)($summary['formal_system_notice'] ?? '') . "\n";
    }

    private static function renderVersionLedger(array $preview): string
    {
        $lines = [
            '# GOV-TRIAL 版本台账',
            '',
            '| 版本 | 批次 | 用途 | 状态 |',
            '|---|---|---|---|',
            '| GOV-TRIAL/0.1 | GOV-TRIAL-20260724 | 条款/关系块试运行装配 | 保留，不覆盖 |',
            '| GOV-TRIAL/0.2 | GOV-TRIAL-20260725 | 现用全文叠加已签认补丁的连续解析稿 | '
                . (!empty($preview['summary']['batch_trial_ready']) ? 'trial_ready' : 'draft（存在阻断）') . ' |',
            '',
            '0.2 以 0.1 为前序试运行对象，但正文基线重新回到现用文件原件及其 SHA-256；不把 0.1 的合成落实块当作正文。',
            '',
        ];

        return implode("\n", $lines);
    }

    private static function packageManifest(array $preview): array
    {
        return [
            'trial_batch' => $preview['summary']['trial_batch'] ?? '',
            'version' => $preview['summary']['version'] ?? '',
            'base_version' => $preview['manifest']['base_version'] ?? '',
            'formal_system_notice' => $preview['summary']['formal_system_notice'] ?? '',
            'summary' => $preview['summary'] ?? [],
            'sources' => $preview['manifest']['sources'] ?? [],
            'documents' => array_values(array_map(
                static function (array $document): array {
                    unset($document['resolved_body'], $document['rendered_markdown']);
                    return $document;
                },
                $preview['documents'] ?? []
            )),
        ];
    }

    private static function upsertResolvedDocument(array $document, array $preview, string $companyId): void
    {
        $canonical = (string)$document['doc_number'];
        $trialNumber = 'SIM-GOV02-' . $canonical;
        $priorStructure = Db::name('qms_structured_documents')
            ->where('document_role', (string)$document['document_role'])
            ->where('doc_number', 'SIM-' . $canonical)
            ->where('version', 'GOV-TRIAL/0.1')
            ->where('soft_delete', 0)
            ->find();
        $priorDocumentId = (string)($priorStructure['document_id'] ?? '');
        $priorDocument = $priorDocumentId !== ''
            ? Db::name('documents')->where('id', $priorDocumentId)->find()
            : null;
        $existing = Db::name('documents')
            ->where('doc_number', $trialNumber)
            ->where('version', GovernedTrialResolvedManifestService::VERSION)
            ->where('soft_delete', 0)
            ->find();
        $documentId = (string)($existing['id'] ?? qms_uuid());
        $fileName = self::safeToken($canonical . '-' . (string)$document['title']) . '.md';
        $relativePath = self::DEFAULT_OUTPUT . '/连续正文/' . $fileName;
        $now = date('Y-m-d H:i:s');
        $documentStatus = ($preview['summary']['batch_trial_ready'] ?? false)
            ? (string)$document['status']
            : 'draft';
        $isObsolete = $documentStatus === 'obsolete';
        $documentRow = [
            'company_id' => $companyId,
            'level' => $document['document_role'] === 'quality_manual' ? 1 : 2,
            'doc_number' => $trialNumber,
            'title' => '[治理试运行解析稿] ' . (string)$document['title'],
            'version' => GovernedTrialResolvedManifestService::VERSION,
            'revision' => 2,
            'effective_date' => null,
            'status' => $documentStatus,
            'file_path' => $relativePath,
            'file_name' => $fileName,
            'file_type' => 'md',
            'approved_by' => null,
            'change_reason' => self::json([
                'notice' => ($preview['summary']['batch_trial_ready'] ?? false)
                    ? '现用全文叠加已签认精确补丁；当前无SIM阻断，仍不得作为正式受控文件。'
                    : '现用全文叠加已签认精确补丁；存在批次级阻断冲突，不得提交审核。',
                'trial_batch' => GovernedTrialResolvedManifestService::TRIAL_BATCH,
                'source_sha256' => (string)$document['source_sha256'],
                'resolved_text_sha256' => (string)$document['resolved_text_sha256'],
                'blocking_conflicts' => $preview['blocking_conflicts'] ?? [],
            ]),
            'supersedes_document_id' => $priorDocumentId !== '' ? $priorDocumentId : null,
            'revision_root_id' => (string)($priorDocument['revision_root_id'] ?? $priorDocumentId ?: $documentId),
            'publish' => 1,
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
            ->where('document_role', (string)$document['document_role'])
            ->where('doc_number', $trialNumber)
            ->where('version', GovernedTrialResolvedManifestService::VERSION)
            ->where('soft_delete', 0)
            ->find();
        $structuredId = (string)($existingStructure['id'] ?? qms_uuid());
        $structureRow = [
            'company_id' => $companyId,
            'source_asset_id' => null,
            'document_id' => $documentId,
            'document_role' => (string)$document['document_role'],
            'doc_number' => $trialNumber,
            'title' => '[治理试运行解析稿] ' . (string)$document['title'],
            'version' => GovernedTrialResolvedManifestService::VERSION,
            'source_status' => 'draft',
            'markdown_path' => $relativePath,
            'rendered_file_path' => $relativePath,
            'render_status' => 'rendered',
            'status' => $isObsolete ? 'obsolete' : 'draft',
            'review_note' => ($isObsolete
                ? '本文件已按不开展抽样决定作废保留，不进入审核发布链路。来源哈希：'
                : (($preview['summary']['batch_trial_ready'] ?? false)
                    ? '由现用全文与已签认精确补丁生成；当前无SIM阻断，可用于试运行审核。来源哈希：'
                    : '由现用全文与已签认精确补丁生成；批次冲突未关闭前禁止提交审核。来源哈希：'))
                . (string)$document['source_sha256'],
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

        self::upsertResolvedBlocks(
            $structuredId,
            $documentId,
            $companyId,
            (string)$document['resolved_body'],
            (string)$document['source_path'],
            is_array($priorStructure) ? (string)$priorStructure['id'] : '',
            $isObsolete
        );
    }

    private static function upsertResolvedBlocks(
        string $structuredId,
        string $documentId,
        string $companyId,
        string $markdown,
        string $sourcePath,
        string $priorStructuredId,
        bool $obsolete = false
    ): void {
        $existingBlocks = Db::name('qms_document_blocks')
            ->where('structured_document_id', $structuredId)
            ->select()
            ->toArray();
        $existingIds = array_column($existingBlocks, 'id');
        if ($existingIds !== []) {
            Db::name('qms_document_block_links')->whereIn('block_id', $existingIds)->delete();
            Db::name('qms_document_blocks')->whereIn('id', $existingIds)->update([
                'soft_delete' => 1,
                'modified' => date('Y-m-d H:i:s'),
            ]);
        }

        $blocks = self::splitResolvedMarkdown($markdown);
        $firstBlockId = '';
        $recordBlocks = [];
        foreach ($blocks as $index => $block) {
            $stableKey = 'resolved_' . str_pad((string)($index + 1), 3, '0', STR_PAD_LEFT)
                . '_' . substr(hash('sha256', (string)$block['heading']), 0, 12);
            $existing = null;
            foreach ($existingBlocks as $candidate) {
                if (($candidate['stable_key'] ?? '') === $stableKey) {
                    $existing = $candidate;
                    break;
                }
            }
            $blockId = (string)($existing['id'] ?? qms_uuid());
            if ($firstBlockId === '') {
                $firstBlockId = $blockId;
            }
            $blockType = self::resolvedBlockType((string)$block['title']);
            if ($blockType === 'record_requirement') {
                $recordBlocks[$blockId] = (string)$block['markdown'];
            }
            $row = [
                'company_id' => $companyId,
                'structured_document_id' => $structuredId,
                'document_id' => $documentId,
                'parent_id' => null,
                'stable_key' => $stableKey,
                'section_number' => (string)$block['section_number'],
                'title' => (string)$block['title'],
                'block_type' => $blockType,
                'markdown' => (string)$block['markdown'],
                'sort_order' => ($index + 1) * 10,
                'source_locator' => $sourcePath . '#' . (string)$block['heading'],
                'status' => $obsolete ? 'obsolete' : 'draft',
                'publish' => 1,
                'soft_delete' => 0,
                'modified' => date('Y-m-d H:i:s'),
            ];
            if (is_array($existing)) {
                Db::name('qms_document_blocks')->where('id', $blockId)->update($row);
            } else {
                $row['id'] = $blockId;
                $row['created'] = date('Y-m-d H:i:s');
                Db::name('qms_document_blocks')->insert($row);
            }
        }

        if (!$obsolete && $firstBlockId !== '' && $priorStructuredId !== '') {
            self::copyPriorTraceLinks($priorStructuredId, $firstBlockId, $recordBlocks, $companyId);
        }
    }

    private static function currentPackageBlockingCount(): int
    {
        $path = self::workspaceRoot() . '/' . self::DEFAULT_OUTPUT . '/冲突审查/冲突总表.json';
        if (!is_file($path)) {
            return 1;
        }
        $decoded = json_decode((string)file_get_contents($path), true);

        return is_array($decoded) ? count($decoded['blocking_conflicts'] ?? []) : 1;
    }

    private static function copyPriorTraceLinks(
        string $priorStructuredId,
        string $newBlockId,
        array $recordBlocks,
        string $companyId
    ): void {
        $rows = Db::name('qms_document_block_links')->alias('link')
            ->join('qms_document_blocks block', 'block.id = link.block_id')
            ->leftJoin('record_form_templates template', 'template.id = link.record_form_template_id')
            ->where('block.structured_document_id', $priorStructuredId)
            ->where('block.soft_delete', 0)
            ->where('link.soft_delete', 0)
            ->field(
                'link.element_id,link.clause_id,link.manual_section_id,link.procedure_document_id,'
                . 'link.record_form_template_id,link.position_id,link.business_module_id,'
                . 'link.relation_type,link.confidence,link.note,'
                . 'template.doc_number template_doc_number,'
                . 'template.canonical_doc_number template_canonical_doc_number,'
                . 'template.name template_name'
            )
            ->select()
            ->toArray();
        $deduplicated = [];
        foreach ($rows as $row) {
            $key = hash('sha256', json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            $deduplicated[$key] = $row;
        }
        $insert = [];
        foreach ($deduplicated as $row) {
            $linkRow = $row;
            unset(
                $linkRow['template_doc_number'],
                $linkRow['template_canonical_doc_number'],
                $linkRow['template_name']
            );
            if (!empty($row['record_form_template_id'])) {
                $destinations = [];
                foreach ($recordBlocks as $blockId => $blockMarkdown) {
                    if (self::recordTemplateMentioned($blockMarkdown, $row)) {
                        $destinations[] = $blockId;
                    }
                }
            } else {
                $destinations = [$newBlockId];
            }
            foreach (array_values(array_unique($destinations)) as $destinationBlockId) {
                $insert[] = array_merge($linkRow, [
                    'id' => qms_uuid(),
                    'company_id' => $companyId,
                    'block_id' => $destinationBlockId,
                    'note' => trim((string)($row['note'] ?? '') . '；由GOV-TRIAL/0.1追溯关系继承，待0.2逐块复核。', '；'),
                    'publish' => 1,
                    'soft_delete' => 0,
                    'created' => date('Y-m-d H:i:s'),
                    'modified' => date('Y-m-d H:i:s'),
                ]);
            }
        }
        if ($insert !== []) {
            Db::name('qms_document_block_links')->insertAll($insert);
        }
    }

    private static function recordTemplateMentioned(string $markdown, array $template): bool
    {
        foreach ([
            (string)($template['template_canonical_doc_number'] ?? ''),
            (string)($template['template_doc_number'] ?? ''),
            (string)($template['template_name'] ?? ''),
        ] as $needle) {
            if ($needle !== '' && str_contains($markdown, $needle)) {
                return true;
            }
        }

        return false;
    }

    private static function recordLinkMismatchCount(array $structureIds): int
    {
        $rows = Db::name('qms_document_block_links')->alias('link')
            ->join('qms_document_blocks block', 'block.id = link.block_id')
            ->join('record_form_templates template', 'template.id = link.record_form_template_id')
            ->whereIn('block.structured_document_id', $structureIds)
            ->where('block.block_type', 'record_requirement')
            ->where('block.soft_delete', 0)
            ->where('link.soft_delete', 0)
            ->field(
                'block.markdown,template.doc_number template_doc_number,'
                . 'template.canonical_doc_number template_canonical_doc_number,'
                . 'template.name template_name'
            )
            ->select()
            ->toArray();
        $mismatches = 0;
        foreach ($rows as $row) {
            if (!self::recordTemplateMentioned((string)$row['markdown'], $row)) {
                $mismatches++;
            }
        }

        return $mismatches;
    }

    private static function splitResolvedMarkdown(string $markdown): array
    {
        $lines = preg_split('/\n/u', $markdown) ?: [];
        $blocks = [];
        $current = [];
        $heading = '正文';
        foreach ($lines as $line) {
            if (preg_match('/^(#{1,6})\s+(.+)$/u', $line, $matches)) {
                if ($current !== []) {
                    $blocks[] = self::resolvedBlock($heading, implode("\n", $current));
                }
                $heading = trim((string)$matches[2]);
                $current = [$line];
                continue;
            }
            $current[] = $line;
        }
        if ($current !== []) {
            $blocks[] = self::resolvedBlock($heading, implode("\n", $current));
        }

        return array_values(array_filter(
            $blocks,
            static fn(array $block): bool => trim((string)$block['markdown']) !== ''
        ));
    }

    private static function resolvedBlock(string $heading, string $markdown): array
    {
        preg_match('/^([0-9]+(?:\.[0-9]+)*)\s*(.*)$/u', $heading, $matches);

        return [
            'heading' => $heading,
            'section_number' => (string)($matches[1] ?? ''),
            'title' => trim((string)($matches[2] ?? $heading)) ?: $heading,
            'markdown' => trim($markdown),
        ];
    }

    private static function resolvedBlockType(string $title): string
    {
        if (str_contains($title, '目的')) {
            return 'purpose';
        }
        if (str_contains($title, '范围')) {
            return 'scope';
        }
        if (str_contains($title, '职责')) {
            return 'responsibility';
        }
        if (str_contains($title, '记录')) {
            return 'record_requirement';
        }
        if (str_contains($title, '程序') || str_contains($title, '流程')) {
            return 'process_step';
        }

        return 'control_requirement';
    }

    private static function renderHtml(array $document): string
    {
        $title = htmlspecialchars((string)$document['doc_number'] . ' ' . (string)$document['title'], ENT_QUOTES, 'UTF-8');
        $body = htmlspecialchars((string)$document['rendered_markdown'], ENT_QUOTES, 'UTF-8');

        return "<!doctype html>\n<html lang=\"zh-CN\"><head><meta charset=\"utf-8\">"
            . "<meta name=\"viewport\" content=\"width=device-width,initial-scale=1\">"
            . "<title>{$title}</title><style>"
            . "body{max-width:980px;margin:40px auto;padding:0 24px;color:#17202a;background:#f7f8fa;font:16px/1.75 system-ui,sans-serif}"
            . "pre{white-space:pre-wrap;word-break:break-word;background:#fff;padding:32px;border:1px solid #dfe4ea;border-radius:12px}"
            . "</style></head><body><pre>{$body}</pre></body></html>\n";
    }

    private static function json(array $data): string
    {
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        if (!is_string($json)) {
            throw new RuntimeException('JSON编码失败');
        }

        return $json . "\n";
    }

    private static function absoluteOutputPath(string $path): string
    {
        if (str_starts_with($path, '/')) {
            return rtrim($path, '/');
        }

        return self::workspaceRoot() . '/' . trim($path, '/');
    }

    private static function workspaceRoot(): string
    {
        return dirname(__DIR__, 3);
    }

    private static function safeToken(string $value): string
    {
        $value = preg_replace('/[^\p{Han}A-Za-z0-9._-]+/u', '-', $value) ?? $value;
        return trim($value, '-');
    }

    private static function cell(string $value): string
    {
        return str_replace(["\r", "\n", '|'], ['', '<br>', '\\|'], $value);
    }

    private static function mkdir(string $path): void
    {
        if (!is_dir($path) && !mkdir($path, 0775, true) && !is_dir($path)) {
            throw new RuntimeException('无法创建目录：' . $path);
        }
    }

    private static function write(string $path, string $content): void
    {
        if (file_put_contents($path, $content) === false) {
            throw new RuntimeException('无法写入文件：' . $path);
        }
    }

    private static function removeTree(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $item) {
            if ($item->isDir()) {
                rmdir($item->getPathname());
            } else {
                unlink($item->getPathname());
            }
        }
        rmdir($path);
    }
}
