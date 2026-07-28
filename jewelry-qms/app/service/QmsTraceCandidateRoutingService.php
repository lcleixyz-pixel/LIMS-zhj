<?php
declare(strict_types=1);

namespace app\service;

final class QmsTraceCandidateRoutingService
{
    private const DEFINITIONS = [
        'external_source' => [
            'collection' => 'external_sources',
            'relation_type' => 'basis',
            'relation_label' => '主链：外部依据',
            'target_field' => 'clause_id',
            'kind_label' => '外部依据',
            'block_priority' => [
                'purpose',
                'control_requirement',
                'process_step',
            ],
        ],
        'manual_section' => [
            'collection' => 'manual_sections',
            'relation_type' => 'implements',
            'relation_label' => '主链：落实手册',
            'target_field' => 'manual_section_id',
            'kind_label' => '手册章节',
            'block_priority' => [
                'process_step',
                'control_requirement',
                'purpose',
            ],
        ],
        'record_template' => [
            'collection' => 'record_templates',
            'relation_type' => 'requires_record',
            'relation_label' => '主链：运行记录',
            'target_field' => 'record_form_template_id',
            'kind_label' => '运行记录',
            'block_priority' => [
                'record_requirement',
                'process_step',
            ],
        ],
    ];

    public static function route(array $candidateTrace, array $blocks): array
    {
        $summary = ['total' => 0, 'routable' => 0, 'blocked' => 0];
        foreach (self::DEFINITIONS as $candidateKind => $definition) {
            $collection = (string)$definition['collection'];
            $targetBlock = self::selectBlock(
                $blocks,
                (array)$definition['block_priority']
            );
            $rows = [];
            foreach ((array)($candidateTrace[$collection] ?? []) as $rawRow) {
                if (!is_array($rawRow)) {
                    continue;
                }
                $row = $rawRow;
                $targetId = trim((string)($row['id'] ?? ''));
                $entityAvailable = $targetId !== ''
                    && (bool)($row['available'] ?? true);
                $blockAvailable = $targetBlock !== [];
                $routable = $entityAvailable && $blockAvailable;
                $routingIssue = '';
                if (!$entityAvailable) {
                    $routingIssue = '候选对象尚未入库，暂不能带入复核。';
                } elseif (!$blockAvailable) {
                    $routingIssue = '当前文件没有可进入的内容块，暂不能带入复核。';
                }

                $targetLabel = self::targetLabel($candidateKind, $row);
                $row = array_merge($row, [
                    'candidate_kind' => $candidateKind,
                    'candidate_kind_label' => (string)$definition['kind_label'],
                    'relation_type' => (string)$definition['relation_type'],
                    'relation_label' => (string)$definition['relation_label'],
                    'target_field' => (string)$definition['target_field'],
                    'target_id' => $targetId,
                    'target_label' => $targetLabel,
                    'target_block_id' => (string)($targetBlock['id'] ?? ''),
                    'target_block_title' => (string)($targetBlock['title'] ?? ''),
                    'target_block_type' => (string)($targetBlock['block_type'] ?? ''),
                    'recommendation_reason' => $blockAvailable
                        ? self::recommendationReason(
                            $candidateKind,
                            $targetLabel,
                            (string)($targetBlock['title'] ?? '')
                        )
                        : '',
                    'routable' => $routable,
                    'routing_issue' => $routingIssue,
                    'review_url' => $routable
                        ? self::reviewUrl(
                            (string)$targetBlock['id'],
                            $candidateKind,
                            $targetId
                        )
                        : '',
                ]);
                $rows[] = $row;
                $summary['total']++;
                $summary[$routable ? 'routable' : 'blocked']++;
            }
            $candidateTrace[$collection] = $rows;
        }
        $candidateTrace['routing_summary'] = $summary;

        return $candidateTrace;
    }

    public static function resolvePrefill(
        array $routedCandidateTrace,
        string $blockId,
        array $query
    ): array {
        $candidateKind = trim((string)($query['candidate_kind'] ?? ''));
        $candidateId = trim((string)($query['candidate_id'] ?? ''));
        if ($candidateKind === '' && $candidateId === '') {
            return self::emptyPrefill(false);
        }
        if ($candidateKind === '' || $candidateId === '') {
            return self::invalidPrefill('候选参数不完整，请返回治理工作台重新进入。');
        }

        $definition = self::DEFINITIONS[$candidateKind] ?? null;
        if (!is_array($definition)) {
            return self::invalidPrefill('候选类型已变化，请返回治理工作台重新进入。');
        }

        $candidate = null;
        foreach ((array)(
            $routedCandidateTrace[(string)$definition['collection']] ?? []
        ) as $row) {
            if (
                is_array($row)
                && (string)($row['id'] ?? '') === $candidateId
            ) {
                $candidate = $row;
                break;
            }
        }
        if (!is_array($candidate)) {
            return self::invalidPrefill(
                '该候选已变化，请返回治理工作台重新进入。'
            );
        }
        if (!(bool)($candidate['routable'] ?? false)) {
            return self::invalidPrefill(
                (string)(
                    $candidate['routing_issue']
                    ?? '该候选暂不能带入复核。'
                )
            );
        }
        if (
            trim($blockId) === ''
            || (string)($candidate['target_block_id'] ?? '') !== trim($blockId)
        ) {
            return self::invalidPrefill(
                '建议内容块已变化，请返回治理工作台重新进入。'
            );
        }

        return [
            'requested' => true,
            'available' => true,
            'error' => '',
            'source_label' => (string)(
                $routedCandidateTrace['source_label']
                ?? '治理装配蓝图 / 本地条款映射'
            ),
            'candidate_kind' => $candidateKind,
            'candidate_kind_label' => (string)(
                $candidate['candidate_kind_label'] ?? ''
            ),
            'relation_type' => (string)($candidate['relation_type'] ?? ''),
            'relation_label' => (string)($candidate['relation_label'] ?? ''),
            'target_field' => (string)($candidate['target_field'] ?? ''),
            'target_id' => (string)($candidate['target_id'] ?? ''),
            'target_label' => (string)($candidate['target_label'] ?? ''),
            'target_block_id' => (string)(
                $candidate['target_block_id'] ?? ''
            ),
            'target_block_title' => (string)(
                $candidate['target_block_title'] ?? ''
            ),
            'target_block_type' => (string)(
                $candidate['target_block_type'] ?? ''
            ),
            'recommendation_reason' => (string)(
                $candidate['recommendation_reason'] ?? ''
            ),
        ];
    }

    private static function selectBlock(array $blocks, array $priority): array
    {
        $candidates = [];
        foreach ($blocks as $rawBlock) {
            if (!is_array($rawBlock)) {
                continue;
            }
            $block = is_array($rawBlock['block'] ?? null)
                ? (array)$rawBlock['block']
                : $rawBlock;
            $blockId = trim((string)($block['id'] ?? ''));
            if ($blockId === '') {
                continue;
            }
            $block['id'] = $blockId;
            $block['title'] = trim((string)($block['title'] ?? ''))
                ?: '未命名内容块';
            $block['block_type'] = trim((string)($block['block_type'] ?? ''));
            $block['sort_order'] = (int)($block['sort_order'] ?? 0);
            $typeRank = array_search(
                (string)$block['block_type'],
                $priority,
                true
            );
            $block['_routing_rank'] = $typeRank === false
                ? count($priority)
                : (int)$typeRank;
            $candidates[] = $block;
        }
        usort($candidates, static function (array $left, array $right): int {
            return [
                (int)$left['_routing_rank'],
                (int)$left['sort_order'],
                (string)$left['id'],
            ] <=> [
                (int)$right['_routing_rank'],
                (int)$right['sort_order'],
                (string)$right['id'],
            ];
        });
        if ($candidates === []) {
            return [];
        }
        unset($candidates[0]['_routing_rank']);

        return $candidates[0];
    }

    private static function targetLabel(string $candidateKind, array $row): string
    {
        if ($candidateKind === 'external_source') {
            return trim(
                (string)($row['source_code'] ?? '') . ' '
                . (string)($row['clause_number'] ?? '') . ' '
                . (string)($row['title'] ?? '')
            );
        }
        if ($candidateKind === 'manual_section') {
            return trim(
                (string)($row['section_number'] ?? '') . ' '
                . (string)($row['title'] ?? '')
            );
        }

        return trim(
            (string)($row['doc_number'] ?? '') . ' '
            . (string)($row['name'] ?? '')
        );
    }

    private static function recommendationReason(
        string $candidateKind,
        string $targetLabel,
        string $blockTitle
    ): string {
        if ($candidateKind === 'external_source') {
            return '治理装配蓝图将“' . $targetLabel
                . '”映射为本程序候选外部依据，建议在“'
                . $blockTitle . '”内容块核对其适用性。';
        }
        if ($candidateKind === 'manual_section') {
            return '治理装配蓝图将“' . $targetLabel
                . '”列为本程序候选手册主链，建议在“'
                . $blockTitle . '”内容块核对程序落实情况。';
        }

        return '治理装配蓝图将“' . $targetLabel
            . '”列为本程序候选运行记录，建议在“'
            . $blockTitle . '”内容块核对记录支撑情况。';
    }

    private static function reviewUrl(
        string $blockId,
        string $candidateKind,
        string $candidateId
    ): string {
        return '/planning/structures/links/review?' . http_build_query([
            'block_id' => $blockId,
            'candidate_kind' => $candidateKind,
            'candidate_id' => $candidateId,
        ]);
    }

    private static function emptyPrefill(bool $requested): array
    {
        return [
            'requested' => $requested,
            'available' => false,
            'error' => '',
        ];
    }

    private static function invalidPrefill(string $error): array
    {
        return [
            'requested' => true,
            'available' => false,
            'error' => $error,
        ];
    }
}
