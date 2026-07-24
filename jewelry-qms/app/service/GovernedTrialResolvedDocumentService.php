<?php
declare(strict_types=1);

namespace app\service;

use RuntimeException;

final class GovernedTrialResolvedDocumentService
{
    public const DEFAULT_OUTPUT = '.team/交接箱/2026-07-25-8021治理体系试运行装配/GOV-TRIAL-0.2';

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
            $status = ($result['blocking_conflicts'] ?? []) === [] ? 'trial_ready' : 'draft';
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

        return dirname(__DIR__, 3) . '/' . trim($path, '/');
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
