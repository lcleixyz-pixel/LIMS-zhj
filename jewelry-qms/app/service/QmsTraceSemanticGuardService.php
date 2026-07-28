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

    public static function assess(
        array $document,
        array $blockRows,
        array $candidateContext = []
    ): array {
        $profile = self::detectProfile($document, $blockRows);
        if ($profile === [] && $candidateContext !== []) {
            $profile = self::candidateProfile($document, $candidateContext);
        }
        $manual = [
            'confirmed_primary' => [],
            'supporting' => [],
            'pending_review' => [],
            'suspected_mismatch' => [],
        ];
        $manualReasons = [
            'pending_review' => [],
            'suspected_mismatch' => [],
        ];

        foreach ($blockRows as $blockRow) {
            foreach ((array)($blockRow['links'] ?? []) as $link) {
                if (!is_array($link) || trim((string)($link['section_number'] ?? '')) === '') {
                    continue;
                }
                $assessment = self::assessManualLink(
                    $link,
                    (array)($profile['expected_manual_sections'] ?? [])
                );
                $state = (string)$assessment['state'];
                if (isset($manual[$state])) {
                    $manual[$state][] = $link;
                }
                if (
                    isset($manualReasons[$state])
                    && (string)($assessment['reason_code'] ?? '') !== ''
                ) {
                    $manualReasons[$state][] = $assessment;
                }
            }
        }

        $status = 'not_assessed';
        $issues = [];
        if (
            $profile !== []
            && ($profile['candidate_available'] ?? true) === false
        ) {
            $status = 'candidate_unavailable';
            $candidateIssues = array_values(array_filter(array_map(
                'strval',
                (array)($candidateContext['issues'] ?? [])
            )));
            $issues[] = [
                'code' => 'candidate_source_unavailable',
                'severity' => 'medium',
                'message' => $candidateIssues[0]
                    ?? '当前程序没有可用的治理候选来源，请先核对治理装配蓝图。',
            ];
        } elseif ($profile !== []) {
            if ($manual['confirmed_primary'] !== []) {
                $status = 'aligned';
            } elseif ($manual['suspected_mismatch'] !== []) {
                $status = 'suspected_mismatch';
                $reason = self::primaryReason(
                    $manualReasons['suspected_mismatch']
                );
                $issue = [
                    'code' => 'manual_primary_mismatch',
                    'severity' => 'high',
                    'message' => (string)($reason['message'] ?? '')
                        ?: self::mismatchMessage(
                            $profile,
                            $manual['suspected_mismatch']
                        ),
                ];
                $issues[] = self::withReason($issue, $reason);
            } elseif ($manual['pending_review'] !== []) {
                $status = 'review_required';
                $reason = self::primaryReason(
                    $manualReasons['pending_review']
                );
                $issue = [
                    'code' => 'manual_primary_review_required',
                    'severity' => 'medium',
                    'message' => (string)($reason['message'] ?? '')
                        ?: '手册主链候选尚未完成人工复核，不能计入证据链闭合。',
                ];
                $issues[] = self::withReason($issue, $reason);
            } else {
                $status = 'missing_primary';
                $candidateOnly = (bool)($profile['candidate_only'] ?? false);
                $issues[] = [
                    'code' => 'manual_primary_missing',
                    'severity' => 'high',
                    'message' => $candidateOnly
                        ? self::candidateMissingMessage($profile)
                        : '未找到与'
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
        if ($targetKind === 'manual') {
            return (string)self::assessManualLink(
                $link,
                $expectedManualSections
            )['state'];
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

    public static function assessManualLink(
        array $link,
        array $expectedManualSections = []
    ): array {
        $section = trim((string)($link['section_number'] ?? ''));
        $relationType = trim((string)($link['relation_type'] ?? ''));
        if (
            $section === ''
            || in_array($relationType, ['mentions', 'supporting'], true)
        ) {
            return self::manualLinkResult('supporting');
        }

        $matchesExpectedSection = self::matchesExpectedSection(
            $section,
            $expectedManualSections
        );
        $policy = QmsTraceRelationPolicyService::inspectExistingLink($link);
        if (
            $relationType !== 'implements'
            || (bool)($policy['is_mixed'] ?? false)
        ) {
            $expectedText = implode('、', array_values(array_map(
                'strval',
                $expectedManualSections
            )));
            $otherTargets = array_values(array_filter(
                (array)($policy['split_preview'] ?? []),
                static fn(array $row): bool =>
                    (string)($row['target_field'] ?? '') !== 'manual_section_id'
            ));
            $otherTargetLabels = array_values(array_unique(array_filter(array_map(
                static fn(array $row): string =>
                    trim((string)($row['target_kind_label'] ?? '')),
                $otherTargets
            ))));

            if ($matchesExpectedSection) {
                $message = $otherTargetLabels !== []
                    ? $section
                        . ' 章节候选正确，但当前与'
                        . implode('、', $otherTargetLabels)
                        . '等对象混在同一关系中，尚不能作为手册主链。'
                    : $section
                        . ' 章节候选正确，但当前关系用途不是“落实手册”，'
                        . '尚不能作为手册主链。';
                $message .= '请按拆分预览建立独立的 '
                    . $section
                    . ' 手册主链。';
            } else {
                $message = '当前关系同时混装多个对象，且手册章节 '
                    . ($section !== '' ? $section : '未明确')
                    . ($expectedText !== ''
                        ? ' 与建议章节 ' . $expectedText . ' 不一致。'
                        : ' 仍需人工复核。')
                    . '请先按拆分预览拆开，再复核手册章节。';
            }

            return self::manualLinkResult(
                'suspected_mismatch',
                'mixed_relation',
                '关系混装',
                $message,
                '进入对应内容块，先按拆分预览拆开历史混装关系，再确认独立手册主链。'
            );
        }

        if (!$matchesExpectedSection) {
            $expectedText = implode('、', array_values(array_map(
                'strval',
                $expectedManualSections
            )));

            return self::manualLinkResult(
                'suspected_mismatch',
                'wrong_section',
                '章节不匹配',
                '当前手册章节 '
                    . ($section !== '' ? $section : '未明确')
                    . ($expectedText !== ''
                        ? ' 与建议主手册章节 ' . $expectedText . ' 不一致。'
                        : ' 尚未进入建议主手册章节集合。')
                    . ($expectedText !== ''
                        ? '请移除或改为辅助关系，再单独建立 '
                            . $expectedText
                            . ' 手册主链。'
                        : '请复核后重新建立正确的手册主链。'),
                '进入对应内容块，将当前章节移除或改为辅助关系，再单独建立'
                    . ($expectedText !== '' ? ' ' . $expectedText : '正确的')
                    . ' 手册主链。'
            );
        }

        $confidence = trim((string)($link['confidence'] ?? ''));
        $note = (string)($link['note'] ?? '');
        if (
            $confidence !== 'high'
            || str_contains($note, self::INHERITED_REVIEW_MARKER)
        ) {
            return self::manualLinkResult(
                'pending_review',
                'unconfirmed_relation',
                '尚未确认',
                $section
                    . ' 与建议章节一致，但该关系仍是继承或待复核候选，'
                    . '尚未人工确认，暂不计入闭环。',
                '进入对应内容块逐条确认继承关系；仅确认后的独立手册主链计入闭环。'
            );
        }

        return self::manualLinkResult('confirmed_primary');
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

    private static function candidateProfile(array $document, array $candidateContext): array
    {
        $available = (bool)($candidateContext['available'] ?? false);
        $sections = [];
        foreach ((array)($candidateContext['manual_sections'] ?? []) as $section) {
            if (!is_array($section)) {
                continue;
            }
            $sectionNumber = trim((string)($section['section_number'] ?? ''));
            if ($sectionNumber !== '') {
                $sections[$sectionNumber] = $sectionNumber;
            }
        }
        uksort($sections, 'strnatcmp');
        $sourceLabel = trim((string)($candidateContext['source_label'] ?? ''));
        $title = trim((string)($document['title'] ?? ''));

        return [
            'id' => $available
                ? 'governance_blueprint_candidate'
                : 'candidate_unavailable',
            'label' => $title !== '' ? $title : '当前程序',
            'expected_manual_sections' => array_values($sections),
            'expected_manual_sections_text' => implode('、', array_values($sections)),
            'matched_terms' => $sourceLabel !== '' ? [$sourceLabel] : [],
            'matched_terms_text' => $sourceLabel,
            'candidate_only' => true,
            'candidate_available' => $available,
            'candidate_source_label' => $sourceLabel,
        ];
    }

    private static function sectionMatches(string $actual, string $expected): bool
    {
        return $actual === $expected
            || str_starts_with($actual, $expected . '.')
            || str_starts_with($expected, $actual . '.');
    }

    private static function matchesExpectedSection(
        string $section,
        array $expectedManualSections
    ): bool
    {
        if ($expectedManualSections === []) {
            return true;
        }
        foreach ($expectedManualSections as $expected) {
            if (self::sectionMatches($section, (string)$expected)) {
                return true;
            }
        }

        return false;
    }

    private static function manualLinkResult(
        string $state,
        string $reasonCode = '',
        string $reasonLabel = '',
        string $message = '',
        string $recommendedAction = ''
    ): array
    {
        return [
            'state' => $state,
            'reason_code' => $reasonCode,
            'reason_label' => $reasonLabel,
            'message' => $message,
            'recommended_action' => $recommendedAction,
        ];
    }

    private static function primaryReason(array $reasons): array
    {
        $rank = [
            'mixed_relation' => 3,
            'wrong_section' => 2,
            'unconfirmed_relation' => 1,
        ];
        usort(
            $reasons,
            static fn(array $left, array $right): int =>
                ($rank[(string)($right['reason_code'] ?? '')] ?? 0)
                <=>
                ($rank[(string)($left['reason_code'] ?? '')] ?? 0)
        );

        return $reasons[0] ?? [];
    }

    private static function withReason(array $issue, array $reason): array
    {
        if ((string)($reason['reason_code'] ?? '') === '') {
            return $issue;
        }

        $issue['reason_code'] = (string)$reason['reason_code'];
        $issue['reason_label'] = (string)($reason['reason_label'] ?? '');
        $issue['recommended_action'] = (string)(
            $reason['recommended_action'] ?? ''
        );

        return $issue;
    }

    private static function candidateMissingMessage(array $profile): string
    {
        $sections = array_values((array)($profile['expected_manual_sections'] ?? []));
        $source = trim((string)($profile['candidate_source_label'] ?? '治理候选来源'));

        return $sections !== []
            ? '已从' . $source . '找到手册章节候选 '
                . implode('、', $sections)
                . '，但候选不等于确认；当前尚未保存已确认手册主链。'
            : $source . '尚未提供可用的手册章节候选；当前手册主链仍缺失。';
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
