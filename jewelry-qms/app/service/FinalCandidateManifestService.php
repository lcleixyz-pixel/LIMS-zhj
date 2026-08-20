<?php
declare(strict_types=1);

namespace app\service;

final class FinalCandidateManifestService
{
    public const VERSION = 'GOV-TRIAL/0.3';
    public const TRIAL_BATCH = 'GOV-TRIAL-20260820-DOCS';
    public const TRIAL_PREFIX = 'SIM-GOV03-';

    private const MANUAL_FILE = '质量手册（XZTC-SC-2026 第五版）最终确认稿.docx';
    private const DECISION_REGISTER_FILE = '待确认项总清单v1.1 最终确认稿.docx';

    public static function build(string $sourceDir): array
    {
        $sourceDir = rtrim(trim($sourceDir), DIRECTORY_SEPARATOR);
        $files = is_dir($sourceDir) ? (glob($sourceDir . DIRECTORY_SEPARATOR . '*.docx') ?: []) : [];
        sort($files, SORT_NATURAL);

        $documents = [];
        $excluded = [];
        foreach ($files as $path) {
            $name = basename($path);
            $classification = self::classify($name);
            if ($classification === null) {
                $excluded[] = [
                    'file_name' => $name,
                    'absolute_path' => $path,
                    'source_sha256' => hash_file('sha256', $path),
                    'reason' => self::exclusionReason($name),
                ];
                continue;
            }

            $documents[] = $classification + [
                'file_name' => $name,
                'absolute_path' => $path,
                'source_sha256' => hash_file('sha256', $path),
                'trial_doc_number' => self::TRIAL_PREFIX . $classification['canonical_doc_number'],
                'version' => self::VERSION,
                'trial_batch' => self::TRIAL_BATCH,
                'status' => $classification['canonical_doc_number'] === 'XZTC/ZY-1-01-2026' ? 'obsolete' : 'draft',
                'review_class' => $classification['canonical_doc_number'] === 'XZTC/ZY-1-01-2026'
                    ? 'reference_only'
                    : 'review_required',
            ];
        }

        usort(
            $documents,
            static fn(array $left, array $right): int => strnatcmp(
                (string)$left['canonical_doc_number'],
                (string)$right['canonical_doc_number']
            )
        );

        $manifest = [
            'version' => self::VERSION,
            'trial_batch' => self::TRIAL_BATCH,
            'source_dir' => $sourceDir,
            'formal_system_notice' => '仅限8021隔离环境候选试装；纸质体系仍为唯一正式体系。',
            'documents' => $documents,
            'excluded' => $excluded,
        ];
        $manifest['validation'] = self::validate($manifest);

        return $manifest;
    }

    public static function validate(array $manifest): array
    {
        $errors = [];
        $documents = (array)($manifest['documents'] ?? []);
        $excluded = (array)($manifest['excluded'] ?? []);
        $sourceDir = (string)($manifest['source_dir'] ?? '');
        $allFiles = $sourceDir !== '' && is_dir($sourceDir)
            ? (glob(rtrim($sourceDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '*.docx') ?: [])
            : [];

        if (($manifest['version'] ?? '') !== self::VERSION) {
            $errors[] = '候选版本不是 ' . self::VERSION;
        }
        if (($manifest['trial_batch'] ?? '') !== self::TRIAL_BATCH) {
            $errors[] = '候选批次不是 ' . self::TRIAL_BATCH;
        }
        if (count($allFiles) !== 75) {
            $errors[] = '来源DOCX数量应为75，当前为' . count($allFiles);
        }
        if (count($documents) !== 65) {
            $errors[] = '白名单制度文件数量应为65，当前为' . count($documents);
        }
        if (count($excluded) !== 10) {
            $errors[] = '排除材料数量应为10，当前为' . count($excluded);
        }

        $numbers = [];
        $roleCounts = ['quality_manual' => 0, 'procedure' => 0, 'work_instruction' => 0];
        $statusCounts = ['draft' => 0, 'obsolete' => 0];
        foreach ($documents as $document) {
            $number = (string)($document['canonical_doc_number'] ?? '');
            if ($number === '' || isset($numbers[$number])) {
                $errors[] = $number === '' ? '存在空文件编号' : '文件编号重复：' . $number;
            }
            $numbers[$number] = true;
            $role = (string)($document['document_role'] ?? '');
            if (array_key_exists($role, $roleCounts)) {
                $roleCounts[$role]++;
            } else {
                $errors[] = $number . ' 文件角色非法';
            }
            $status = (string)($document['status'] ?? '');
            if (array_key_exists($status, $statusCounts)) {
                $statusCounts[$status]++;
            } else {
                $errors[] = $number . ' 状态非法';
            }
            $path = (string)($document['absolute_path'] ?? '');
            $hash = (string)($document['source_sha256'] ?? '');
            if ($path === '' || !is_file($path)) {
                $errors[] = $number . ' 来源文件不存在';
            } elseif (!preg_match('/^[a-f0-9]{64}$/', $hash) || !hash_equals(hash_file('sha256', $path), $hash)) {
                $errors[] = $number . ' 来源哈希漂移';
            }
            if (($document['trial_doc_number'] ?? '') !== self::TRIAL_PREFIX . $number) {
                $errors[] = $number . ' 试装编号前缀不正确';
            }
        }

        if ($roleCounts !== ['quality_manual' => 1, 'procedure' => 35, 'work_instruction' => 29]) {
            $errors[] = '文件角色数量必须为1份手册、35份程序、29份作业指导书材料';
        }
        if ($statusCounts !== ['draft' => 64, 'obsolete' => 1]) {
            $errors[] = '候选状态数量必须为64份草稿、1份废止留痕';
        }

        return [
            'ok' => $errors === [],
            'errors' => array_values(array_unique($errors)),
            'counts' => [
                'all_docx' => count($allFiles),
                'included' => count($documents),
                'excluded' => count($excluded),
                'quality_manual' => $roleCounts['quality_manual'],
                'procedure' => $roleCounts['procedure'],
                'work_instruction' => $roleCounts['work_instruction'],
                'draft' => $statusCounts['draft'],
                'obsolete' => $statusCounts['obsolete'],
            ],
        ];
    }

    public static function resolveRecommendedTimeMarkers(string $content): array
    {
        $patches = [];
        $sequence = 0;
        $pattern = '/(?<anchor>(?<value>(?:\d+\s*个?\s*工作日内|每(?:日|周|月|季度|半年|年)(?:至少)?\s*(?:\d+|[一二三四五六七八九十]+)?\s*次))(?<marker>＿{1,}|◇))/u';
        $resolved = preg_replace_callback(
            $pattern,
            static function (array $match) use (&$patches, &$sequence): string {
                $sequence++;
                $anchor = (string)$match['anchor'];
                $replacement = (string)$match['value'];
                $patches[] = [
                    'patch_id' => 'TIME-REC-' . str_pad((string)$sequence, 4, '0', STR_PAD_LEFT),
                    'operation' => 'replace_exact',
                    'anchor' => $anchor,
                    'expected_old_sha256' => hash('sha256', $anchor),
                    'replacement' => $replacement,
                    'replacement_sha256' => hash('sha256', $replacement),
                    'decision_status' => 'recommended_candidate',
                    'reason' => '材料正文已给出明确时限，仅清除紧随其后的待裁决标记。',
                ];

                return $replacement;
            },
            $content
        );

        return [
            'content' => is_string($resolved) ? $resolved : $content,
            'patches' => $patches,
        ];
    }

    private static function classify(string $name): ?array
    {
        if ($name === self::MANUAL_FILE) {
            return [
                'canonical_doc_number' => 'XZTC/SC-2026',
                'title' => '质量手册（第五版）',
                'document_role' => 'quality_manual',
                'level' => 1,
            ];
        }
        if (preg_match('/^XZTC-CX-([0-9]+(?:-[0-9]+)?)-2026\s+(.+)\s+最终确认稿\.docx$/u', $name, $match)) {
            return [
                'canonical_doc_number' => 'XZTC/CX-' . $match[1] . '-2026',
                'title' => trim((string)$match[2]),
                'document_role' => 'procedure',
                'level' => 2,
            ];
        }
        if (preg_match('/^XZTC-ZY-([0-9]+-[0-9]+)-2026\s+(.+)\s+最终确认稿\.docx$/u', $name, $match)) {
            return [
                'canonical_doc_number' => 'XZTC/ZY-' . $match[1] . '-2026',
                'title' => trim((string)$match[2]),
                'document_role' => 'work_instruction',
                'level' => 3,
            ];
        }

        return null;
    }

    private static function exclusionReason(string $name): string
    {
        if ($name === self::DECISION_REGISTER_FILE) {
            return '治理裁决与阻断来源，不作为受控文件装入';
        }
        if (str_starts_with($name, 'G5') || str_starts_with($name, 'G6')) {
            return '第二轮表单、字段规则或接口规范材料';
        }

        return '不在65份制度文件白名单';
    }
}
