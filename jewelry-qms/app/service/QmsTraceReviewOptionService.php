<?php
declare(strict_types=1);

namespace app\service;

final class QmsTraceReviewOptionService
{
    private const CANDIDATE_COLLECTIONS = [
        'clauses' => 'external_sources',
        'manual_sections' => 'manual_sections',
        'record_forms' => 'record_templates',
    ];

    public static function prioritize(
        array $options,
        array $candidateTrace
    ): array {
        foreach ($options as $optionGroup => $rows) {
            $candidateRanks = self::candidateRanks(
                (array)($candidateTrace[
                    self::CANDIDATE_COLLECTIONS[(string)$optionGroup] ?? ''
                ] ?? [])
            );
            $decorated = [];
            foreach ((array)$rows as $index => $rawRow) {
                if (!is_array($rawRow)) {
                    continue;
                }
                $row = $rawRow;
                $id = trim((string)($row['id'] ?? ''));
                $isCandidate = $id !== '' && isset($candidateRanks[$id]);
                $row['is_candidate'] = $isCandidate;
                $row['_trace_option_index'] = (int)$index;
                $row['_trace_candidate_rank'] = $isCandidate
                    ? (int)$candidateRanks[$id]
                    : PHP_INT_MAX;
                $decorated[] = $row;
            }
            usort(
                $decorated,
                static fn(array $left, array $right): int => [
                    (bool)$left['is_candidate'] ? 0 : 1,
                    (int)$left['_trace_candidate_rank'],
                    (int)$left['_trace_option_index'],
                ] <=> [
                    (bool)$right['is_candidate'] ? 0 : 1,
                    (int)$right['_trace_candidate_rank'],
                    (int)$right['_trace_option_index'],
                ]
            );
            foreach ($decorated as &$row) {
                unset(
                    $row['_trace_option_index'],
                    $row['_trace_candidate_rank']
                );
            }
            unset($row);
            $options[(string)$optionGroup] = $decorated;
        }

        return $options;
    }

    private static function candidateRanks(array $candidates): array
    {
        $ranks = [];
        foreach ($candidates as $candidate) {
            if (!is_array($candidate)) {
                continue;
            }
            $id = trim((string)($candidate['id'] ?? ''));
            if (
                $id === ''
                || !(bool)($candidate['available'] ?? true)
                || isset($ranks[$id])
            ) {
                continue;
            }
            $ranks[$id] = count($ranks);
        }

        return $ranks;
    }
}
