<?php
declare(strict_types=1);

namespace app\service;

final class GovernedTrialPatchEngine
{
    private const SUPPORTED_OPERATIONS = [
        'replace_exact',
        'insert_after_heading',
        'delete_exact',
        'append_record_requirement',
    ];

    public static function apply(string $baseline, array $patches): array
    {
        $baseline = self::normalizeLineEndings($baseline);
        $conflicts = [];
        $warnings = [];
        $candidates = [];
        $supersededIds = [];

        foreach ($patches as $patch) {
            $supersededId = trim((string)($patch['supersedes_patch_id'] ?? ''));
            if ($supersededId !== '') {
                $supersededIds[$supersededId] = true;
            }
        }

        foreach ($patches as $index => $patch) {
            $patchId = trim((string)($patch['patch_id'] ?? 'PATCH-' . ($index + 1)));
            if (isset($supersededIds[$patchId])) {
                continue;
            }

            $operation = trim((string)($patch['operation'] ?? ''));
            $anchor = self::normalizeLineEndings((string)($patch['anchor'] ?? ''));
            $decisionStatus = trim((string)($patch['decision_status'] ?? ''));

            if ($decisionStatus !== 'signed') {
                $conflicts[] = self::conflict($patchId, 'source_not_signed', '补丁来源未签认，原文保持不变。');
                continue;
            }
            if (!in_array($operation, self::SUPPORTED_OPERATIONS, true)) {
                $conflicts[] = self::conflict($patchId, 'unsupported_operation', '补丁操作不在允许清单中。');
                continue;
            }
            if ($anchor === '') {
                $conflicts[] = self::conflict($patchId, 'anchor_missing', '补丁锚点为空。');
                continue;
            }
            if (
                $operation === 'delete_exact'
                && trim((string)($patch['reason'] ?? '')) === ''
            ) {
                $conflicts[] = self::conflict(
                    $patchId,
                    'deletion_without_signed_reason',
                    '删除操作缺少明确签认理由，原文保持不变。'
                );
                continue;
            }

            $expectedHash = strtolower(trim((string)($patch['expected_old_sha256'] ?? '')));
            $actualHash = hash('sha256', $anchor);
            if ($expectedHash === '' || !hash_equals($expectedHash, $actualHash)) {
                $conflicts[] = self::conflict(
                    $patchId,
                    'old_text_hash_mismatch',
                    '补丁登记的旧文哈希与锚点不一致。'
                );
                continue;
            }

            $occurrences = substr_count($baseline, $anchor);
            if ($occurrences === 0) {
                $conflicts[] = self::conflict($patchId, 'anchor_missing', '现用正文中未找到补丁锚点。');
                continue;
            }
            if ($occurrences > 1) {
                $conflicts[] = self::conflict($patchId, 'anchor_ambiguous', '现用正文中补丁锚点不唯一。');
                continue;
            }

            $start = (int)strpos($baseline, $anchor);
            $end = $start + strlen($anchor);
            $candidates[] = [
                'patch' => $patch,
                'patch_id' => $patchId,
                'operation' => $operation,
                'anchor' => $anchor,
                'start' => $start,
                'end' => $end,
                'replacement' => self::normalizeLineEndings((string)($patch['replacement_markdown'] ?? '')),
            ];
        }

        $overlappingIds = [];
        $candidateCount = count($candidates);
        for ($left = 0; $left < $candidateCount; $left++) {
            for ($right = $left + 1; $right < $candidateCount; $right++) {
                if (!self::overlaps($candidates[$left], $candidates[$right])) {
                    continue;
                }
                $overlappingIds[$candidates[$left]['patch_id']] = true;
                $overlappingIds[$candidates[$right]['patch_id']] = true;
            }
        }
        foreach (array_keys($overlappingIds) as $patchId) {
            $conflicts[] = self::conflict(
                $patchId,
                'patch_overlap',
                '两个已签认补丁修改同一原文区间，且没有明确取代关系。'
            );
        }
        $candidates = array_values(array_filter(
            $candidates,
            static fn(array $candidate): bool => !isset($overlappingIds[$candidate['patch_id']])
        ));

        $content = $baseline;
        usort(
            $candidates,
            static fn(array $left, array $right): int => $right['start'] <=> $left['start']
        );
        foreach ($candidates as $candidate) {
            $replacement = self::replacementFor($candidate);
            $content = substr($content, 0, $candidate['start'])
                . $replacement
                . substr($content, $candidate['end']);
        }

        $preservation = self::preservationCheck($baseline, $content, $candidates);
        if (!$preservation['ok']) {
            $conflicts[] = self::conflict(
                'DOCUMENT',
                'output_preservation_failed',
                '输出未能保持未修改区段的原始顺序。'
            );
        }

        return [
            'content' => $content,
            'baseline_sha256' => hash('sha256', $baseline),
            'content_sha256' => hash('sha256', $content),
            'applied_patches' => array_values(array_map(
                static fn(array $candidate): string => $candidate['patch_id'],
                $candidates
            )),
            'blocking_conflicts' => $conflicts,
            'warnings' => $warnings,
            'preservation_check' => $preservation,
        ];
    }

    private static function replacementFor(array $candidate): string
    {
        $replacement = (string)$candidate['replacement'];
        if ($candidate['operation'] === 'replace_exact') {
            return $replacement;
        }
        if ($candidate['operation'] === 'delete_exact') {
            return '';
        }

        $anchor = (string)$candidate['anchor'];
        if ($replacement === '') {
            return $anchor;
        }

        return $anchor . "\n\n" . $replacement;
    }

    private static function overlaps(array $left, array $right): bool
    {
        return $left['start'] < $right['end'] && $right['start'] < $left['end'];
    }

    private static function preservationCheck(string $baseline, string $content, array $candidates): array
    {
        $changedRanges = [];
        foreach ($candidates as $candidate) {
            if (in_array($candidate['operation'], ['replace_exact', 'delete_exact'], true)) {
                $changedRanges[] = [$candidate['start'], $candidate['end']];
            }
        }
        usort($changedRanges, static fn(array $left, array $right): int => $left[0] <=> $right[0]);

        $segments = [];
        $cursor = 0;
        foreach ($changedRanges as [$start, $end]) {
            if ($start > $cursor) {
                $segments[] = substr($baseline, $cursor, $start - $cursor);
            }
            $cursor = max($cursor, $end);
        }
        if ($cursor < strlen($baseline)) {
            $segments[] = substr($baseline, $cursor);
        }

        $searchOffset = 0;
        foreach ($segments as $segment) {
            if ($segment === '') {
                continue;
            }
            $position = strpos($content, $segment, $searchOffset);
            if ($position === false) {
                return [
                    'ok' => false,
                    'unchanged_segment_count' => count($segments),
                    'failed_segment_sha256' => hash('sha256', $segment),
                ];
            }
            $searchOffset = $position + strlen($segment);
        }

        return [
            'ok' => true,
            'unchanged_segment_count' => count($segments),
            'failed_segment_sha256' => '',
        ];
    }

    private static function conflict(string $patchId, string $type, string $message): array
    {
        return [
            'patch_id' => $patchId,
            'type' => $type,
            'message' => $message,
            'blocking' => true,
        ];
    }

    private static function normalizeLineEndings(string $text): string
    {
        return str_replace(["\r\n", "\r"], "\n", $text);
    }
}
