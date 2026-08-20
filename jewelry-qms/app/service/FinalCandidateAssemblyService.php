<?php
declare(strict_types=1);

namespace app\service;

use RuntimeException;

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
        throw new RuntimeException('候选写入服务尚未启用');
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
