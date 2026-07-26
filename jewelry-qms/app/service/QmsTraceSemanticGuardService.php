<?php
declare(strict_types=1);

namespace app\service;

final class QmsTraceSemanticGuardService
{
    private const INHERITED_REVIEW_MARKER = '待0.2逐块复核';

    private const PROFILES = [
        'data_information_control' => [
            'label' => '数据和信息管理',
            'expected_manual_sections' => ['7.11'],
            'title_terms' => ['计算机', '数据控制', '信息管理', 'LIMS'],
            'evidence_terms' => ['数据', '信息', '软件', '备份', '权限', '审计'],
        ],
        'record_control' => [
            'label' => '记录控制',
            'expected_manual_sections' => ['8.4'],
            'title_terms' => ['记录控制'],
            'evidence_terms' => ['记录', '保存', '归档', '检索', '更正', '销毁'],
        ],
        'document_control' => [
            'label' => '文件控制',
            'expected_manual_sections' => ['8.3'],
            'title_terms' => ['文件控制'],
            'evidence_terms' => ['文件', '批准', '修订', '版本', '分发', '回收', '作废'],
        ],
    ];

    public static function assess(array $document, array $blockRows): array
    {
        $profile = self::detectProfile($document, $blockRows);
        $manual = [
            'confirmed_primary' => [],
            'supporting' => [],
            'pending_review' => [],
            'suspected_mismatch' => [],
        ];

        foreach ($blockRows as $blockRow) {
            foreach ((array)($blockRow['links'] ?? []) as $link) {
                if (!is_array($link) || trim((string)($link['section_number'] ?? '')) === '') {
                    continue;
                }
                $state = self::linkState(
                    $link,
                    'manual',
                    (array)($profile['expected_manual_sections'] ?? [])
                );
                if (isset($manual[$state])) {
                    $manual[$state][] = $link;
                }
            }
        }

        $status = 'not_assessed';
        $issues = [];
        if ($profile !== []) {
            if ($manual['confirmed_primary'] !== []) {
                $status = 'aligned';
            } elseif ($manual['suspected_mismatch'] !== []) {
                $status = 'suspected_mismatch';
                $issues[] = [
                    'code' => 'manual_primary_mismatch',
                    'severity' => 'high',
                    'message' => self::mismatchMessage($profile, $manual['suspected_mismatch']),
                ];
            } elseif ($manual['pending_review'] !== []) {
                $status = 'review_required';
                $issues[] = [
                    'code' => 'manual_primary_review_required',
                    'severity' => 'medium',
                    'message' => '手册主链候选尚未完成人工复核，不能计入证据链闭合。',
                ];
            } else {
                $status = 'missing_primary';
                $issues[] = [
                    'code' => 'manual_primary_missing',
                    'severity' => 'high',
                    'message' => '未找到与'
                        . (string)$profile['label']
                        . '主题匹配的手册主链，建议优先复核 '
                        . implode('、', (array)$profile['expected_manual_sections'])
                        . '。',
                ];
            }
        }

        return [
            'status' => $status,
            'profile' => $profile,
            'manual' => $manual,
            'issues' => $issues,
        ];
    }

    public static function linkState(
        array $link,
        string $targetKind,
        array $expectedManualSections = []
    ): string {
        $relationType = (string)($link['relation_type'] ?? '');
        if ($targetKind === 'external' && $relationType !== 'basis') {
            return 'supporting';
        }
        if ($targetKind === 'record' && $relationType !== 'requires_record') {
            return 'supporting';
        }
        if (
            $targetKind === 'manual'
            && in_array($relationType, ['mentions', 'supporting'], true)
        ) {
            return 'supporting';
        }
        if ($targetKind === 'manual' && $relationType !== 'implements') {
            return 'suspected_mismatch';
        }
        if ($targetKind === 'manual' && $expectedManualSections !== []) {
            $section = trim((string)($link['section_number'] ?? ''));
            $matchesExpectedSection = false;
            foreach ($expectedManualSections as $expected) {
                if (self::sectionMatches($section, (string)$expected)) {
                    $matchesExpectedSection = true;
                    break;
                }
            }
            if (!$matchesExpectedSection) {
                return 'suspected_mismatch';
            }
        }

        $confidence = trim((string)($link['confidence'] ?? ''));
        $note = (string)($link['note'] ?? '');
        if (
            $confidence !== 'high'
            || str_contains($note, self::INHERITED_REVIEW_MARKER)
        ) {
            return 'pending_review';
        }

        if ($targetKind === 'external') {
            return 'confirmed_primary';
        }
        if ($targetKind === 'record') {
            return 'confirmed_primary';
        }
        if ($targetKind !== 'manual') {
            return 'supporting';
        }

        return 'confirmed_primary';
    }

    public static function combinedLinkState(
        array $link,
        array $expectedManualSections = []
    ): string {
        $states = [];
        if (
            trim((string)($link['clause_id'] ?? '')) !== ''
            || trim((string)($link['clause_number'] ?? '')) !== ''
        ) {
            $states[] = self::linkState($link, 'external');
        }
        if (
            trim((string)($link['manual_section_id'] ?? '')) !== ''
            || trim((string)($link['section_number'] ?? '')) !== ''
        ) {
            $states[] = self::linkState($link, 'manual', $expectedManualSections);
        }
        if (
            trim((string)($link['record_form_template_id'] ?? '')) !== ''
            || trim((string)($link['record_number'] ?? '')) !== ''
        ) {
            $states[] = self::linkState($link, 'record');
        }
        if ($states === []) {
            return 'supporting';
        }

        $rank = [
            'suspected_mismatch' => 4,
            'pending_review' => 3,
            'confirmed_primary' => 2,
            'supporting' => 1,
        ];
        usort(
            $states,
            static fn(string $left, string $right): int =>
                ($rank[$right] ?? 0) <=> ($rank[$left] ?? 0)
        );

        return (string)$states[0];
    }

    private static function detectProfile(array $document, array $blockRows): array
    {
        $title = trim((string)($document['title'] ?? ''));
        $body = $title;
        foreach ($blockRows as $blockRow) {
            $block = is_array($blockRow) ? (array)($blockRow['block'] ?? []) : [];
            $body .= "\n" . (string)($block['title'] ?? '') . "\n" . (string)($block['markdown'] ?? '');
        }

        foreach (self::PROFILES as $id => $definition) {
            $titleMatches = array_values(array_filter(
                (array)$definition['title_terms'],
                static fn(string $term): bool => $term !== '' && str_contains($title, $term)
            ));
            if ($titleMatches === []) {
                continue;
            }
            $evidenceTerms = array_values(array_filter(
                (array)$definition['evidence_terms'],
                static fn(string $term): bool => $term !== '' && str_contains($body, $term)
            ));

            return [
                'id' => $id,
                'label' => (string)$definition['label'],
                'expected_manual_sections' => array_values((array)$definition['expected_manual_sections']),
                'expected_manual_sections_text' => implode(
                    '、',
                    array_values((array)$definition['expected_manual_sections'])
                ),
                'matched_terms' => array_values(array_unique(array_merge($titleMatches, $evidenceTerms))),
                'matched_terms_text' => implode(
                    '、',
                    array_values(array_unique(array_merge($titleMatches, $evidenceTerms)))
                ),
            ];
        }

        return [];
    }

    private static function sectionMatches(string $actual, string $expected): bool
    {
        return $actual === $expected
            || str_starts_with($actual, $expected . '.')
            || str_starts_with($expected, $actual . '.');
    }

    private static function mismatchMessage(array $profile, array $links): string
    {
        $current = array_values(array_unique(array_filter(array_map(
            static fn(array $link): string => trim((string)($link['section_number'] ?? '')),
            $links
        ))));

        return '疑似错挂：当前手册关系 '
            . ($current !== [] ? implode('、', $current) : '未明确')
            . ' 不能替代'
            . (string)$profile['label']
            . '主链；建议复核 '
            . implode('、', (array)$profile['expected_manual_sections'])
            . '。';
    }
}
