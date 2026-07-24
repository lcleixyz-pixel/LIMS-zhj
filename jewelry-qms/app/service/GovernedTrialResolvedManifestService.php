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
        'rbt214_basis_decision' => '.team/交接箱/2026-07-25-8021治理体系试运行装配/02-RBT214依据身份治理决定-v0.1.md',
        'g2_record_ledger' => 'claude_G2-记录总台账-v0_2定稿.md',
        'trial_blocker_closure' => '.team/交接箱/2026-07-25-8021治理体系试运行装配/03-试运行阻断关闭决定-v0.1.md',
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
            self::managementReviewPatches($baselineMarkdown['XZTC/CX-21-2022'] ?? '', $sources),
            self::rbt214BasisPatches($baselineMarkdown, $sources),
            self::trialBlockerClosurePatches($baselineMarkdown, $sources)
        );

        $manifest = [
            'trial_batch' => self::TRIAL_BATCH,
            'version' => self::VERSION,
            'base_version' => 'GOV-TRIAL/0.1',
            'formal_system_notice' => '仅限8021隔离环境治理试运行；纸质体系仍为唯一正式体系。',
            'baselines' => $baselines,
            'sources' => array_values($sources),
            'patches' => $patches,
            'deferred_signed_items' => [],
            'non_blocking_governance_notes' => [
                'G1其余已签认内容继续按来源清单逐项融入连续正文；当前仅以实际登记并命中的精确补丁计入完成，不阻止8021 SIM链路。',
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
            $relativePath = str_starts_with($fileName, '.team/')
                ? $fileName
                : self::SOURCE_DIR . '/' . $fileName;
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
            if ($docNumber === 'XZTC/SC') {
                continue;
            }
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

    private static function rbt214BasisPatches(array $documents, array $sources): array
    {
        $source = $sources['rbt214_basis_decision'];
        $definitions = [
            [
                'USER-20260725-RBT214-001',
                'XZTC/CX-01-2022',
                '4.1.2.5本公司的授权签字人应满足《检测和校准实验室能力认可准则》、CNAS-CL01-A015:2022和《检验检测机构资质认定能力评价 检验检测机构通用要求》RB/T214-2017的要求。',
                '4.1.2.5 本公司的授权签字人应满足《检验检测机构资质认定管理办法》（2021年修正）和《检验检测机构资质认定评审准则》（市场监管总局公告2023年第21号）的现行适用要求；涉及CNAS申请及相关活动时，还应满足《检测和校准实验室能力认可准则》及CNAS-CL01-A015:2022的适用要求。RB/T 214-2017仅作为历史制度衔接和辅助参考，不作为现行CMA主依据。',
            ],
            [
                'USER-20260725-RBT214-002',
                'XZTC/CX-01-02-2022',
                'b.理解并掌握RB/T 214和管理体系的要求，熟悉本公司的管理体系文件；',
                'b.理解并掌握现行检验检测机构资质认定要求和本公司管理体系要求，熟悉本公司的管理体系文件；RB/T 214-2017仅作历史制度衔接和辅助参考，不作为现行CMA主依据；',
            ],
        ];

        $patches = [];
        foreach ($definitions as [$patchId, $docNumber, $anchor, $replacement]) {
            if (!str_contains((string)($documents[$docNumber] ?? ''), $anchor)) {
                continue;
            }
            $patches[] = self::patch(
                $patchId,
                $docNumber,
                'replace_exact',
                $anchor,
                $replacement,
                $source,
                $source,
                ['6.2', 'CMA依据治理'],
                '机构于2026-07-25确认：RB/T 214-2017仅保留历史/辅助参考身份，不作为现行CMA主依据。',
                '2026-07-25'
            );
        }

        return $patches;
    }

    private static function trialBlockerClosurePatches(array $documents, array $sources): array
    {
        $source = $sources['trial_blocker_closure'];
        $patches = [];
        $manual = (string)($documents['XZTC/SC'] ?? '');
        $resolvedManual = preg_replace(
            '/XZTC\\/CX-([0-9]+(?:-[0-9]+)?)-2018/u',
            'XZTC/CX-$1-2022',
            $manual
        );
        if (is_string($resolvedManual)) {
            $resolvedManual = preg_replace(
                '/7\\.3 抽样\\n.*?(?=\\n7\\.4 样品)/us',
                "7.3 抽样\n\n7.3.1 适用性声明\n\n本公司当前不开展抽样，不对客户送检样品实施取样或抽样，检测结果仅对收到的样品负责。合同评审、样品接收和报告签发时，应保持该能力边界一致；报告按适用要求声明结果仅适用于收到的样品。\n\n7.3.2 状态控制\n\n《抽样控制程序》XZTC/CX-35-2022在本治理试运行版本中作废保留，不作为现行支持性文件；《抽样登记表》和《抽样记录表》不新建、不启用。今后拟开展抽样时，应先完成能力评价、方法与人员确认、文件修订、记录设计、审核批准和受控发布，未经批准不得实施抽样。\n",
                $resolvedManual
            );
        }
        if (is_string($resolvedManual)) {
            foreach ([
                '附录14：程序文件目录',
                '附录15：各岗位任职资格条件',
                '附录16：质量手册条款对照表',
            ] as $heading) {
                $resolvedManual = str_replace($heading, '## ' . $heading, $resolvedManual);
            }
        }
        if (is_string($resolvedManual) && $manual !== '' && $resolvedManual !== $manual) {
            $patches[] = self::patch(
                'USER-20260725-CLOSE-001',
                'XZTC/SC',
                'replace_exact',
                $manual,
                $resolvedManual,
                $source,
                $sources['g1_terminal_approval'],
                ['7.3', '8.3', '附录14-16'],
                '合并执行手册2018程序引用清扫、抽样适用性关闭及附录结构化，避免多个大范围补丁相互覆盖。',
                '2026-07-25'
            );
        }

        $cx35 = (string)($documents['XZTC/CX-35-2022'] ?? '');
        if ($cx35 !== '') {
            $patches[] = self::patch(
                'USER-20260725-CLOSE-002',
                'XZTC/CX-35-2022',
                'replace_exact',
                $cx35,
                "# 抽样控制程序（作废保留）\n\n## 1 状态\n\n本公司当前不开展抽样。本程序在 `GOV-TRIAL/0.2` 中标记为不适用并作废保留，不得用于指导或证明现行检测活动。\n\n## 2 处置\n\n- 不新建、不启用《抽样登记表》和《抽样记录表》；\n- 原程序全文和来源哈希通过修订对照保留，供历史追溯；\n- 系统不得把 BG-35 标准物质记录关联为抽样记录；\n- 今后拟开展抽样时，应先完成能力评价、方法与人员确认、文件和记录修订、审核批准及受控发布。\n\n## 3 依据\n\n本状态依据 G1 已签认的 CX-35 作废决定及 2026-07-25 试运行阻断关闭决定形成，仅适用于 8021 隔离沙箱。\n",
                $source,
                $sources['g1_terminal_approval'],
                ['7.3', '8.3'],
                '关闭CX-35仍呈现可执行抽样步骤及异源污染内容的问题，同时保留原件世系。',
                '2026-07-25'
            );
        }

        $cx0302 = (string)($documents['XZTC/CX-03-02-2022'] ?? '');
        if ($cx0302 !== '' && !str_contains($cx0302, 'XZTC/BG-35-03')) {
            $patches[] = self::patch(
                'USER-20260725-CLOSE-003',
                'XZTC/CX-03-02-2022',
                'replace_exact',
                $cx0302,
                rtrim($cx0302) . "\n\n## 5 相关记录\n\n《标准物质报废申请表》XZTC/BG-35-03，用于记录标准物质报废申请、审核、批准及处置结果。\n",
                $source,
                $source,
                ['6.4', '8.3'],
                '按记录总台账和试运行关闭决定，将BG-35-03恢复为标准物质报废记录，并在程序正文中建立可验证的记录要求。',
                '2026-07-25'
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
        string $reason,
        string $decisionDate = '2026-07-22'
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
            'decision_date' => $decisionDate,
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
