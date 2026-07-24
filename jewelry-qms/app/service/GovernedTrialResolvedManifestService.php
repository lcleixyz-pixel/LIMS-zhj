<?php
declare(strict_types=1);

namespace app\service;

final class GovernedTrialResolvedManifestService
{
    public const VERSION = 'GOV-TRIAL/0.2';
    public const TRIAL_BATCH = 'GOV-TRIAL-20260725';

    private const SOURCE_DIR = '.team/交接箱/2026-07-23-G2蓝图签认与交接单落盘/原件';

    private const SOURCE_FILES = [
        'manual_batch2_v01' => 'claude_G1-候选稿34合册-手册批次②修订对照-v0_1.md',
        'manual_batch2_v02' => 'claude_G1-候选稿34合册-手册批次②修订对照-v0_2.md',
        'manual_batch2_approval' => 'claude_G1-批次2-人审签认留痕-v1_0.md',
        'procedure_points' => 'claude_G1-候选稿5-程序层改写点清单-v0_1.md',
        'sample_batch_v02' => 'claude_G1-批次2b-CX28专项与小补-v0_2.md',
        'sample_batch_approval' => 'claude_G1-批次2b-人审签认留痕-v1_0.md',
        'numbering_final' => 'claude_G1-批次3签认暨G1收官纪要-v1_0.md',
        'management_review_content' => 'claude_G1p1-CX21管评专项修订对照-v0_1.md',
        'scope_content' => 'claude_G1p2-能力边界与标志分流专项-v0_2.md',
        'g1_terminal_approval' => 'claude_G1p-签认留痕暨G1终局-v1_0.md',
        'transition_content' => 'claude_G1p3-增补-v0_2.md',
        'transition_approval' => 'claude_G1p3签认暨G2开闸包-v1_0.md',
    ];

    public static function build(): array
    {
        $baselines = self::baselines();
        $sources = self::sources();
        $baselineMarkdown = [];
        foreach ($baselines as $baseline) {
            $baselineMarkdown[(string)$baseline['doc_number']] = QmsDocumentStructureService::markdownFromSourcePath(
                (string)$baseline['relative_path'],
                0
            );
        }

        $patches = array_merge(
            self::numberingPatches($baselineMarkdown, $sources),
            self::managementReviewPatches($baselineMarkdown['XZTC/CX-21-2022'] ?? '', $sources)
        );

        $manifest = [
            'trial_batch' => self::TRIAL_BATCH,
            'version' => self::VERSION,
            'base_version' => 'GOV-TRIAL/0.1',
            'formal_system_notice' => '仅限8021隔离环境治理试运行；纸质体系仍为唯一正式体系。',
            'baselines' => $baselines,
            'sources' => array_values($sources),
            'patches' => $patches,
            'deferred_signed_items' => [
                '其余G1签认项因尚未形成可唯一命中的机器锚点，不自动改写；由冲突审查逐项阻断或提醒。',
                'CX-35作废、附录补挂和记录表号迁移须与文件状态及G2记录总台账联动，不在文本补丁中静默处理。',
            ],
        ];
        $manifest['validation'] = self::validate($manifest);

        return $manifest;
    }

    public static function validate(array $manifest): array
    {
        $errors = [];
        if (($manifest['version'] ?? '') !== self::VERSION) {
            $errors[] = '解析稿版本不正确';
        }
        if (count($manifest['baselines'] ?? []) !== 38) {
            $errors[] = '基线文件数量必须为38';
        }

        $sourceIndex = [];
        foreach ($manifest['sources'] ?? [] as $source) {
            $path = (string)($source['absolute_path'] ?? '');
            $hash = (string)($source['sha256'] ?? '');
            if ($path === '' || !is_file($path)) {
                $errors[] = (string)($source['source_key'] ?? '来源') . ' 原件不存在';
                continue;
            }
            if (!preg_match('/^[a-f0-9]{64}$/', $hash) || hash_file('sha256', $path) !== $hash) {
                $errors[] = (string)($source['source_key'] ?? '来源') . ' 哈希漂移';
            }
            $sourceIndex[(string)($source['relative_path'] ?? '')] = $hash;
        }

        $required = [
            'patch_id',
            'target_doc_number',
            'operation',
            'anchor',
            'expected_old_sha256',
            'replacement_markdown',
            'source_path',
            'source_sha256',
            'approval_source_path',
            'approval_source_sha256',
            'decision_status',
            'decision_date',
            'clause_refs',
            'reason',
        ];
        $patchIds = [];
        foreach ($manifest['patches'] ?? [] as $patch) {
            $patchId = (string)($patch['patch_id'] ?? '未编号补丁');
            if (isset($patchIds[$patchId])) {
                $errors[] = $patchId . ' 重复';
            }
            $patchIds[$patchId] = true;
            foreach ($required as $field) {
                if (!array_key_exists($field, $patch)) {
                    $errors[] = $patchId . ' 缺少字段 ' . $field;
                }
            }
            if (($patch['decision_status'] ?? '') !== 'signed') {
                $errors[] = $patchId . ' 未签认，不得自动应用';
            }
            foreach ([
                'source_path' => 'source_sha256',
                'approval_source_path' => 'approval_source_sha256',
            ] as $pathField => $hashField) {
                $path = (string)($patch[$pathField] ?? '');
                $hash = (string)($patch[$hashField] ?? '');
                if (!isset($sourceIndex[$path]) || !hash_equals((string)$sourceIndex[$path], $hash)) {
                    $errors[] = $patchId . ' 的' . $pathField . '未通过来源清单校验';
                }
            }
            $anchor = (string)($patch['anchor'] ?? '');
            if (!hash_equals(hash('sha256', $anchor), (string)($patch['expected_old_sha256'] ?? ''))) {
                $errors[] = $patchId . ' 旧文哈希与锚点不一致';
            }
        }

        return [
            'ok' => $errors === [],
            'errors' => array_values(array_unique($errors)),
            'counts' => [
                'baselines' => count($manifest['baselines'] ?? []),
                'sources' => count($manifest['sources'] ?? []),
                'patches' => count($manifest['patches'] ?? []),
            ],
        ];
    }

    private static function baselines(): array
    {
        $workspaceRoot = self::workspaceRoot();
        $sourceRoot = $workspaceRoot . '/现用文件';
        $manualPath = $sourceRoot . '/质量手册（第四版）.docx';
        $manualMarkdown = QmsDocumentStructureService::markdownFromSourcePath('现用文件/质量手册（第四版）.docx', 0);
        $baselines = [[
            'doc_number' => 'XZTC/SC',
            'title' => '质量手册（第四版）',
            'document_role' => 'quality_manual',
            'source_version' => '第四版',
            'relative_path' => '现用文件/质量手册（第四版）.docx',
            'absolute_path' => $manualPath,
            'source_sha256' => hash_file('sha256', $manualPath),
            'text_sha256' => hash('sha256', $manualMarkdown),
            'line_count' => substr_count($manualMarkdown, "\n") + 1,
            'table_row_count' => self::tableRowCount($manualMarkdown),
        ]];

        $enumeration = CurrentFilesSeedService::enumerateProcedureFiles($sourceRoot);
        foreach ($enumeration['included'] ?? [] as $entry) {
            if (($entry['document_kind'] ?? '') !== 'procedure') {
                continue;
            }
            $relativePath = (string)$entry['relative_path'];
            $absolutePath = $workspaceRoot . '/' . $relativePath;
            $markdown = QmsDocumentStructureService::markdownFromSourcePath($relativePath, 0);
            $baselines[] = [
                'doc_number' => (string)$entry['doc_number'],
                'title' => (string)$entry['title'],
                'document_role' => 'procedure',
                'source_version' => (string)$entry['version'],
                'relative_path' => $relativePath,
                'absolute_path' => $absolutePath,
                'source_sha256' => hash_file('sha256', $absolutePath),
                'text_sha256' => hash('sha256', $markdown),
                'line_count' => substr_count($markdown, "\n") + 1,
                'table_row_count' => self::tableRowCount($markdown),
            ];
        }

        return $baselines;
    }

    private static function sources(): array
    {
        $sources = [];
        foreach (self::SOURCE_FILES as $key => $fileName) {
            $relativePath = self::SOURCE_DIR . '/' . $fileName;
            $absolutePath = self::workspaceRoot() . '/' . $relativePath;
            $sources[$key] = [
                'source_key' => $key,
                'relative_path' => $relativePath,
                'absolute_path' => $absolutePath,
                'sha256' => is_file($absolutePath) ? hash_file('sha256', $absolutePath) : '',
            ];
        }

        return $sources;
    }

    private static function numberingPatches(array $documents, array $sources): array
    {
        $patches = [];
        $sequence = 0;
        foreach ($documents as $docNumber => $manual) {
            foreach (self::numberingPatchRanges((string)$manual) as $range) {
                $anchor = substr((string)$manual, $range['start'], $range['end'] - $range['start']);
                $replacement = preg_replace(
                    '/XZTC\/CX-([0-9]+(?:-[0-9]+)?)-2018/u',
                    'XZTC/CX-$1-2022',
                    $anchor
                );
                if (!is_string($replacement) || $replacement === $anchor) {
                    continue;
                }
                $sequence++;
                $patches[] = self::patch(
                    'G1-K2-' . str_pad((string)$sequence, 3, '0', STR_PAD_LEFT),
                    (string)$docNumber,
                    'replace_exact',
                    $anchor,
                    $replacement,
                    $sources['numbering_final'],
                    $sources['numbering_final'],
                    ['8.3'],
                    '批次③K1/K2已签认：程序交叉引用统一去年份化并校准到现用2022版。'
                );
            }
        }

        return $patches;
    }

    private static function numberingPatchRanges(string $manual): array
    {
        $lines = preg_split('/\n/u', $manual) ?: [];
        $ranges = [];
        foreach ($lines as $index => $line) {
            if (!preg_match('/XZTC\/CX-[0-9]+(?:-[0-9]+)?-2018/u', $line)) {
                continue;
            }
            $startLine = $index;
            $endLine = $index;
            $anchor = $line;
            while (substr_count($manual, $anchor) !== 1 && ($startLine > 0 || $endLine < count($lines) - 1)) {
                if ($startLine > 0) {
                    $startLine--;
                }
                if ($endLine < count($lines) - 1) {
                    $endLine++;
                }
                $anchor = implode("\n", array_slice($lines, $startLine, $endLine - $startLine + 1));
            }
            if (substr_count($manual, $anchor) !== 1) {
                continue;
            }
            $start = (int)strpos($manual, $anchor);
            $ranges[] = ['start' => $start, 'end' => $start + strlen($anchor)];
        }

        usort($ranges, static fn(array $left, array $right): int => $left['start'] <=> $right['start']);
        $merged = [];
        foreach ($ranges as $range) {
            $last = count($merged) - 1;
            if ($last >= 0 && $range['start'] < $merged[$last]['end']) {
                $merged[$last]['end'] = max($merged[$last]['end'], $range['end']);
                continue;
            }
            $merged[] = $range;
        }

        return $merged;
    }

    private static function managementReviewPatches(string $baseline, array $sources): array
    {
        $definitions = [
            ['M1-001', '3.1 实验室主任应：', "3.1 总经理（最高管理者）应：\n\n3.1a 实验室主任应：协助总经理组织管理评审，督办评审输出的落实。", ['4.2', '8.9']],
            ['M1-002', '3.1.1 负责按期主持管理评审，批准管理评审报告。', '3.1.1 负责按期主持管理评审，批准管理评审报告。', ['4.2', '8.9']],
            ['M2-001', '4.1.2 特殊情况下，如：组织机构发生重大变化、发生重大质量事故等，实验室主任可决定增加管理评审的频次。', '4.1.2 特殊情况下，如：组织机构发生重大变化、发生重大质量事故等，总经理可决定（实验室主任可提议）增加管理评审的频次。', ['8.9']],
            ['M3-001', '4.3.1 质量负责人根据实验室主任指示，负责制定《管理评审计划表》并经实验室主任批准，明确评审时间、评审内容、参加人员和评审的具体输入要求。', '4.3.1 质量负责人根据总经理指示，负责制定《管理评审计划表》并经总经理批准，明确评审时间、评审内容、参加人员和评审的具体输入要求。', ['8.9']],
            ['M4-001', '4.4.1 由实验室主任主持评审工作，确定主题报告人，报告人应简明地说明主要观点、结论及建议。', '4.4.1 由总经理主持评审工作，实验室主任作为必须参加人；总经理确定主题报告人，报告人应简明地说明主要观点、结论及建议。', ['8.9']],
            ['M4-002', '4.4.3 由实验室主任针对主要存在问题提出结论性意见，并做总结性发言。', '4.4.3 由总经理针对主要存在问题提出结论性意见，并做总结性发言。', ['8.9']],
            ['M6-001', '4.7.2 质量负责人负责协调解决落实改进措施中的问题，确保各项改进措施在规定的时间内实施，负责改进措施实施的监督和跟踪验证。并向实验室主任报告。', '4.7.2 质量负责人负责协调解决落实改进措施中的问题，确保各项改进措施在规定的时间内实施，负责改进措施实施的监督和跟踪验证，并向总经理报告（抄送实验室主任）。', ['8.9']],
            [
                'M7-001',
                "《年度内审计划表》 XZTC/BG-20-02\n\n《内部审核日程计划表》 XZTC/BG-20-08\n\n《内部审核检查记录表》 XZTC/BG-20-06",
                "《管理评审计划表》 XZTC/BG-21-01\n\n《管理评审报告》 XZTC/BG-21-02\n\n《管理评审会议签到/记录表》 XZTC/BG-21-03\n\n《管理评审验证记录表》 XZTC/BG-21-04",
                ['8.9'],
            ],
        ];

        $patches = [];
        foreach ($definitions as [$suffix, $anchor, $replacement, $clauses]) {
            if (!str_contains($baseline, $anchor)) {
                continue;
            }
            $patches[] = self::patch(
                'G1-P1-' . $suffix,
                'XZTC/CX-21-2022',
                'replace_exact',
                $anchor,
                $replacement,
                $sources['management_review_content'],
                $sources['g1_terminal_approval'],
                $clauses,
                'G1-p1 M1～M8及G1终局签认：管理评审主持、批准和记录链统一。'
            );
        }

        return $patches;
    }

    private static function patch(
        string $patchId,
        string $target,
        string $operation,
        string $anchor,
        string $replacement,
        array $contentSource,
        array $approvalSource,
        array $clauses,
        string $reason
    ): array {
        return [
            'patch_id' => $patchId,
            'target_doc_number' => $target,
            'operation' => $operation,
            'anchor' => $anchor,
            'expected_old_sha256' => hash('sha256', $anchor),
            'replacement_markdown' => $replacement,
            'source_path' => (string)$contentSource['relative_path'],
            'source_sha256' => (string)$contentSource['sha256'],
            'approval_source_path' => (string)$approvalSource['relative_path'],
            'approval_source_sha256' => (string)$approvalSource['sha256'],
            'decision_status' => 'signed',
            'decision_date' => '2026-07-22',
            'supersedes_patch_id' => '',
            'clause_refs' => $clauses,
            'reason' => $reason,
        ];
    }

    private static function tableRowCount(string $markdown): int
    {
        return count(array_filter(
            preg_split('/\n/u', $markdown) ?: [],
            static fn(string $line): bool => str_starts_with(trim($line), '|')
        ));
    }

    private static function workspaceRoot(): string
    {
        return dirname(__DIR__, 3);
    }
}
