<?php
declare(strict_types=1);

namespace app\service;

final class QmsTraceWorkItemService
{
    private const CANDIDATE_COLLECTIONS = [
        'external_sources',
        'manual_sections',
        'record_templates',
    ];

    private const AUXILIARY_RELATION_TYPES = [
        'supporting',
        'mentions',
        'responsible',
        'renders_to',
    ];

    private const BLOCK_TYPE_LABELS = [
        'purpose' => '目的',
        'scope' => '范围',
        'responsibility' => '职责',
        'process_step' => '过程步骤',
        'control_requirement' => '控制要求',
        'record_requirement' => '记录要求',
    ];

    private const STEPS = [
        [
            'key' => 'review_existing',
            'label' => '1. 核对当前关系',
            'description' => '先核对当前内容块已有关系及其复核状态。',
        ],
        [
            'key' => 'resolve_issues',
            'label' => '2. 处理发现项',
            'description' => '先拆分混装或纠正错挂，再逐条确认待复核关系。',
        ],
        [
            'key' => 'confirm_primary',
            'label' => '3. 确认主链',
            'description' => '对照候选补齐主链并人工确认，完成后返回工作台复核。',
        ],
    ];

    public static function build(array $blocks, array $candidateTrace): array
    {
        $expectedManualSections = self::expectedManualSections(
            $candidateTrace
        );
        $candidatesByBlock = self::candidatesByBlock($candidateTrace);
        $items = [];

        foreach (self::mergeBlockRows($blocks) as $rawBlockRow) {
            $block = (array)$rawBlockRow['block'];
            $blockId = trim((string)($block['id'] ?? ''));

            $issues = [];
            $confirmedPrimary = [];
            $mixedReviewUrls = [];
            $mismatchReviewUrls = [];
            $otherReviewUrls = [];
            foreach ((array)($rawBlockRow['links'] ?? []) as $rawLink) {
                if (!is_array($rawLink)) {
                    continue;
                }
                $link = $rawLink;
                $policy = QmsTraceRelationPolicyService::inspectExistingLink(
                    $link
                );
                $linkId = trim((string)($link['id'] ?? ''));
                $relationType = trim(
                    (string)($link['relation_type'] ?? '')
                );
                $reviewUrl = self::validGetUrl(
                    (string)($link['review_url'] ?? ''),
                    $blockId,
                    (string)($link['review_method'] ?? 'GET')
                );

                if ((bool)($policy['is_mixed'] ?? false)) {
                    self::pushIssue($issues, [
                        'code' => 'mixed_relation',
                        'label' => '关系混装',
                        'severity' => 'blocked',
                        'message' => '同一关系挂接了多个主要对象，需先按拆分预览拆开。',
                        'link_id' => $linkId,
                        'relation_type' => $relationType,
                        'relation_label' => self::relationLabel($relationType),
                        'target_summary' => self::stableTargetSummary($link),
                        'review_url' => $reviewUrl,
                    ], self::existingIssueKey(
                        'mixed_relation',
                        $linkId,
                        $link
                    ));
                    if ($reviewUrl !== '') {
                        $mixedReviewUrls[] = $reviewUrl;
                    }
                    continue;
                }

                $state = QmsTraceSemanticGuardService::combinedLinkState(
                    $link,
                    $expectedManualSections
                );
                if ($state === 'confirmed_primary') {
                    self::rememberConfirmedPrimary(
                        $confirmedPrimary,
                        $link
                    );
                } elseif ($state === 'suspected_mismatch') {
                    self::pushIssue($issues, [
                        'code' => 'suspected_mismatch',
                        'label' => '疑似错挂',
                        'severity' => 'blocked',
                        'message' => '当前主链对象与治理候选不一致，需人工核对后纠正或改为辅助关系。',
                        'link_id' => $linkId,
                        'relation_type' => $relationType,
                        'relation_label' => self::relationLabel($relationType),
                        'target_summary' => self::stableTargetSummary($link),
                        'review_url' => $reviewUrl,
                    ], self::existingIssueKey(
                        'suspected_mismatch',
                        $linkId,
                        $link
                    ));
                    if ($reviewUrl !== '') {
                        $mismatchReviewUrls[] = $reviewUrl;
                    }
                } elseif (
                    $state === 'pending_review'
                    || (
                        !in_array(
                            $relationType,
                            self::AUXILIARY_RELATION_TYPES,
                            true
                        )
                        && $state !== 'confirmed_primary'
                    )
                ) {
                    self::pushIssue($issues, [
                        'code' => 'pending_review',
                        'label' => '待复核',
                        'severity' => 'review',
                        'message' => '当前主链关系尚未人工确认，暂不计入证据链闭合。',
                        'link_id' => $linkId,
                        'relation_type' => $relationType,
                        'relation_label' => self::relationLabel($relationType),
                        'target_summary' => self::stableTargetSummary($link),
                        'review_url' => $reviewUrl,
                    ], self::existingIssueKey(
                        'pending_review',
                        $linkId,
                        $link
                    ));
                    if ($reviewUrl !== '') {
                        $otherReviewUrls[] = $reviewUrl;
                    }
                }
            }

            $candidates = (array)($candidatesByBlock[$blockId] ?? []);
            $candidateReviewUrls = [];
            foreach ($candidates as $candidate) {
                $candidateReviewUrl = self::validGetUrl(
                    (string)($candidate['review_url'] ?? ''),
                    $blockId,
                    'GET'
                );
                if ($candidateReviewUrl !== '') {
                    $candidateReviewUrls[] = $candidateReviewUrl;
                }

                $confirmationKey = self::confirmationKey(
                    (string)($candidate['relation_type'] ?? ''),
                    (string)($candidate['target_field'] ?? ''),
                    (string)($candidate['target_id'] ?? '')
                );
                if (
                    $confirmationKey !== ''
                    && isset($confirmedPrimary[$confirmationKey])
                ) {
                    continue;
                }

                $candidateKind = trim(
                    (string)($candidate['candidate_kind'] ?? '')
                );
                $targetId = trim(
                    (string)($candidate['target_id'] ?? '')
                );
                self::pushIssue($issues, [
                    'code' => 'missing_primary',
                    'label' => '缺少已确认主链',
                    'severity' => 'review',
                    'message' => '该候选尚未形成同对象、同用途的已确认主链。',
                    'candidate_kind' => $candidateKind,
                    'candidate_kind_label' => (string)(
                        $candidate['candidate_kind_label'] ?? ''
                    ),
                    'target_id' => $targetId,
                    'target_label' => (string)(
                        $candidate['target_label']
                        ?? '候选对象信息待补充'
                    ),
                    'review_url' => $candidateReviewUrl,
                ], 'missing_primary|' . $candidateKind . '|' . $targetId);
            }

            if ($issues === []) {
                continue;
            }
            $issues = array_values($issues);
            usort(
                $issues,
                static fn(array $left, array $right): int =>
                    [
                        -self::issueRank((string)$left['code']),
                        (string)$left['_issue_key'],
                    ]
                    <=>
                    [
                        -self::issueRank((string)$right['code']),
                        (string)$right['_issue_key'],
                    ]
            );
            $itemRank = self::issueRank(
                (string)($issues[0]['code'] ?? '')
            );
            foreach ($issues as &$issue) {
                unset($issue['_issue_key']);
            }
            unset($issue);

            $blockType = trim(
                (string)($block['block_type'] ?? '')
            );
            $items[] = [
                'block_id' => $blockId,
                'section_number' => trim(
                    (string)($block['section_number'] ?? '')
                ),
                'title' => trim((string)($block['title'] ?? ''))
                    ?: '未命名内容块',
                'block_type_label' => self::BLOCK_TYPE_LABELS[$blockType]
                    ?? '内容块',
                'priority' => $itemRank >= 2 ? 'blocked' : 'review',
                'issues' => $issues,
                'steps' => self::STEPS,
                'candidates' => $candidates,
                'primary_url' => self::firstUrl(
                    $mixedReviewUrls,
                    $mismatchReviewUrls,
                    $candidateReviewUrls,
                    $otherReviewUrls
                ),
                '_sort_rank' => $itemRank,
                '_sort_order' => (int)($block['sort_order'] ?? 0),
            ];
        }

        usort($items, static function (array $left, array $right): int {
            $rankComparison = (int)$right['_sort_rank']
                <=> (int)$left['_sort_rank'];
            if ($rankComparison !== 0) {
                return $rankComparison;
            }
            $orderComparison = (int)$left['_sort_order']
                <=> (int)$right['_sort_order'];
            if ($orderComparison !== 0) {
                return $orderComparison;
            }
            $sectionComparison = strnatcmp(
                (string)$left['section_number'],
                (string)$right['section_number']
            );
            if ($sectionComparison !== 0) {
                return $sectionComparison;
            }
            $titleComparison = strcmp(
                (string)$left['title'],
                (string)$right['title']
            );
            if ($titleComparison !== 0) {
                return $titleComparison;
            }

            return strcmp(
                (string)$left['block_id'],
                (string)$right['block_id']
            );
        });

        $issueCount = 0;
        foreach ($items as &$item) {
            $issueCount += count((array)$item['issues']);
            unset($item['_sort_rank'], $item['_sort_order']);
        }
        unset($item);

        return [
            'items' => array_values($items),
            'block_count' => count($items),
            'issue_count' => $issueCount,
        ];
    }

    private static function mergeBlockRows(array $blocks): array
    {
        $merged = [];
        foreach ($blocks as $rawBlockRow) {
            if (!is_array($rawBlockRow)) {
                continue;
            }
            $block = is_array($rawBlockRow['block'] ?? null)
                ? (array)$rawBlockRow['block']
                : $rawBlockRow;
            $blockId = trim((string)($block['id'] ?? ''));
            if ($blockId === '') {
                continue;
            }
            if (!isset($merged[$blockId])) {
                $merged[$blockId] = [
                    'block' => $block,
                    'links' => [],
                ];
            }
            foreach ((array)($rawBlockRow['links'] ?? []) as $link) {
                if (is_array($link)) {
                    $merged[$blockId]['links'][] = $link;
                }
            }
        }

        return array_values($merged);
    }

    private static function expectedManualSections(
        array $candidateTrace
    ): array {
        $sections = [];
        foreach ((array)($candidateTrace['manual_sections'] ?? []) as $row) {
            if (!is_array($row)) {
                continue;
            }
            $section = trim((string)($row['section_number'] ?? ''));
            if ($section !== '') {
                $sections[$section] = $section;
            }
        }
        uksort($sections, 'strnatcmp');

        return array_values($sections);
    }

    private static function candidatesByBlock(array $candidateTrace): array
    {
        $byBlock = [];
        foreach (self::CANDIDATE_COLLECTIONS as $collection) {
            foreach ((array)($candidateTrace[$collection] ?? []) as $row) {
                if (
                    !is_array($row)
                    || !(bool)($row['routable'] ?? false)
                ) {
                    continue;
                }
                $blockId = trim(
                    (string)($row['target_block_id'] ?? '')
                );
                if ($blockId === '') {
                    continue;
                }
                $candidate = self::candidate($row);
                $candidate['review_url'] = self::validGetUrl(
                    (string)($row['review_url'] ?? ''),
                    $blockId,
                    (string)($row['review_method'] ?? 'GET')
                );
                $key = (string)$candidate['candidate_kind']
                    . '|' . (string)$candidate['target_id']
                    . '|' . $blockId;
                if (!isset($byBlock[$blockId][$key])) {
                    $byBlock[$blockId][$key] = $candidate;
                }
            }
        }

        foreach ($byBlock as &$candidates) {
            $candidates = array_values($candidates);
        }
        unset($candidates);

        return $byBlock;
    }

    private static function candidate(array $row): array
    {
        return [
            'candidate_kind' => trim(
                (string)($row['candidate_kind'] ?? '')
            ),
            'candidate_kind_label' => trim(
                (string)($row['candidate_kind_label'] ?? '')
            ),
            'relation_type' => trim(
                (string)($row['relation_type'] ?? '')
            ),
            'relation_label' => trim(
                (string)($row['relation_label'] ?? '')
            ),
            'target_field' => trim(
                (string)($row['target_field'] ?? '')
            ),
            'target_id' => trim(
                (string)($row['target_id'] ?? '')
            ),
            'target_label' => trim(
                (string)($row['target_label'] ?? '')
            ) ?: '候选对象信息待补充',
            'target_block_id' => trim(
                (string)($row['target_block_id'] ?? '')
            ),
            'target_block_title' => trim(
                (string)($row['target_block_title'] ?? '')
            ) ?: '候选对象信息待补充',
            'target_block_type' => trim(
                (string)($row['target_block_type'] ?? '')
            ),
            'recommendation_reason' => trim(
                (string)($row['recommendation_reason'] ?? '')
            ),
            'routable' => true,
            'routing_issue' => trim(
                (string)($row['routing_issue'] ?? '')
            ),
            'review_url' => trim(
                (string)($row['review_url'] ?? '')
            ),
        ];
    }

    private static function rememberConfirmedPrimary(
        array &$confirmed,
        array $link
    ): void {
        $relationType = trim(
            (string)($link['relation_type'] ?? '')
        );
        if (in_array($relationType, self::AUXILIARY_RELATION_TYPES, true)) {
            return;
        }
        foreach ([
            'clause_id',
            'manual_section_id',
            'record_form_template_id',
        ] as $targetField) {
            $key = self::confirmationKey(
                $relationType,
                $targetField,
                (string)($link[$targetField] ?? '')
            );
            if ($key !== '') {
                $confirmed[$key] = true;
            }
        }
    }

    private static function confirmationKey(
        string $relationType,
        string $targetField,
        string $targetId
    ): string {
        $relationType = trim($relationType);
        $targetField = trim($targetField);
        $targetId = trim($targetId);
        if (
            $relationType === ''
            || $targetField === ''
            || $targetId === ''
        ) {
            return '';
        }

        return $relationType . '|' . $targetField . '|' . $targetId;
    }

    private static function pushIssue(
        array &$issues,
        array $issue,
        string $key
    ): void {
        if ($key === '' || isset($issues[$key])) {
            return;
        }
        $issue['_issue_key'] = $key;
        $issues[$key] = $issue;
    }

    private static function existingIssueKey(
        string $code,
        string $linkId,
        array $link
    ): string {
        return $code . '|'
            . ($linkId !== ''
                ? 'id:' . $linkId
                : 'target:' . self::stableTargetSummary($link));
    }

    private static function stableTargetSummary(array $link): string
    {
        $parts = [
            'relation_type='
                . trim((string)($link['relation_type'] ?? '')),
        ];
        $targetFound = false;
        foreach ([
            'clause_id',
            'manual_section_id',
            'record_form_template_id',
            'element_id',
            'procedure_document_id',
            'position_id',
            'business_module_id',
        ] as $field) {
            $value = trim((string)($link[$field] ?? ''));
            if ($value !== '') {
                $parts[] = $field . '=' . $value;
                $targetFound = true;
            }
        }
        if (!$targetFound) {
            foreach ([
                'source_code',
                'clause_number',
                'section_number',
                'record_number',
                'procedure_number',
                'position_name',
                'module_code',
            ] as $field) {
                $value = trim((string)($link[$field] ?? ''));
                if ($value !== '') {
                    $parts[] = $field . '=' . $value;
                }
            }
        }

        return implode('|', $parts);
    }

    private static function relationLabel(string $relationType): string
    {
        $definitions = QmsTraceRelationPolicyService::definitions();

        return (string)(
            $definitions[$relationType]['label']
            ?? '待确认关系'
        );
    }

    private static function issueRank(string $code): int
    {
        return match ($code) {
            'mixed_relation' => 3,
            'suspected_mismatch' => 2,
            'pending_review' => 1,
            'missing_primary' => 0,
            default => 0,
        };
    }

    private static function validGetUrl(
        string $url,
        string $blockId,
        string $method
    ): string {
        if (
            $url === ''
            || strtoupper(trim($method)) !== 'GET'
            || self::hasUrlAmbiguity($url)
        ) {
            return '';
        }

        $parts = parse_url($url);
        if (
            !is_array($parts)
            || array_key_exists('scheme', $parts)
            || array_key_exists('host', $parts)
            || (string)($parts['path'] ?? '')
                !== '/planning/structures/links/review'
        ) {
            return '';
        }

        $query = (string)($parts['query'] ?? '');
        parse_str($query, $parameters);
        if (
            !isset($parameters['block_id'])
            || !is_string($parameters['block_id'])
            || $parameters['block_id'] !== $blockId
        ) {
            return '';
        }

        return $url;
    }

    private static function hasUrlAmbiguity(string $url): bool
    {
        $decoded = $url;
        for ($depth = 0; $depth < 3; $depth++) {
            if (
                str_contains($decoded, '\\')
                || preg_match('/[\x00-\x1F\x7F]/', $decoded) === 1
            ) {
                return true;
            }
            $next = rawurldecode($decoded);
            if ($next === $decoded) {
                break;
            }
            $decoded = $next;
        }

        return str_contains($decoded, '\\')
            || preg_match('/[\x00-\x1F\x7F]/', $decoded) === 1;
    }

    private static function firstUrl(array ...$groups): string
    {
        foreach ($groups as $urls) {
            foreach ($urls as $url) {
                if (trim((string)$url) !== '') {
                    return (string)$url;
                }
            }
        }

        return '';
    }
}
