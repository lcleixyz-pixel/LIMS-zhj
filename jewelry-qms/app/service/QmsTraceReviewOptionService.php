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

    private const STATUS_LABELS = [
        'draft' => '草稿',
        'trial_ready' => '试运行就绪',
        'published' => '已发布',
        'obsolete' => '已作废',
    ];

    public static function govern(
        array $options,
        array $candidateTrace
    ): array {
        $governed = [];
        $summary = [];

        foreach ($options as $optionGroup => $rows) {
            $optionGroup = (string)$optionGroup;
            $candidateRanks = self::candidateRanks(
                (array)($candidateTrace[
                    self::CANDIDATE_COLLECTIONS[$optionGroup] ?? ''
                ] ?? [])
            );
            $decorated = [];
            $excludedInvalid = 0;

            foreach ((array)$rows as $index => $rawRow) {
                if (!is_array($rawRow)) {
                    continue;
                }
                if (self::containsReplacementCharacter($rawRow)) {
                    $excludedInvalid++;
                    continue;
                }

                $row = $rawRow;
                $id = trim((string)($row['id'] ?? ''));
                $isCandidate = $id !== '' && isset($candidateRanks[$id]);
                $row['is_candidate'] = $isCandidate;
                $row['is_secondary'] = false;
                $row['version_label'] = trim((string)(
                    $row['version'] ?? ''
                )) ?: '版本待确认';
                $row['status'] = trim((string)($row['status'] ?? ''));
                $row['status_label'] = self::statusLabel($row['status']);
                $row['source_label'] = self::sourceLabel(
                    $optionGroup,
                    $row
                );
                $row['_trace_option_index'] = (int)$index;
                $row['_trace_candidate_rank'] = $isCandidate
                    ? (int)$candidateRanks[$id]
                    : PHP_INT_MAX;
                $decorated[] = $row;
            }

            $hasCandidate = count(array_filter(
                $decorated,
                static fn(array $row): bool =>
                    (bool)($row['is_candidate'] ?? false)
            )) > 0;
            if (
                $hasCandidate
                && isset(self::CANDIDATE_COLLECTIONS[$optionGroup])
            ) {
                foreach ($decorated as &$row) {
                    $row['is_secondary'] = !$row['is_candidate'];
                }
                unset($row);
            } elseif (in_array(
                $optionGroup,
                ['procedure_documents', 'record_forms'],
                true
            )) {
                self::markDuplicateVersions($optionGroup, $decorated);
            }

            foreach ($decorated as &$row) {
                $row['governance_label'] = self::governanceLabel(
                    $optionGroup,
                    $row
                );
            }
            unset($row);

            usort(
                $decorated,
                static fn(array $left, array $right): int => [
                    (bool)$left['is_candidate'] ? 0 : 1,
                    (bool)$left['is_secondary'] ? 1 : 0,
                    (int)$left['_trace_candidate_rank'],
                    (int)$left['_trace_option_index'],
                ] <=> [
                    (bool)$right['is_candidate'] ? 0 : 1,
                    (bool)$right['is_secondary'] ? 1 : 0,
                    (int)$right['_trace_candidate_rank'],
                    (int)$right['_trace_option_index'],
                ]
            );

            $candidateCount = 0;
            $secondaryCount = 0;
            foreach ($decorated as &$row) {
                $candidateCount += (bool)$row['is_candidate'] ? 1 : 0;
                $secondaryCount += (bool)$row['is_secondary'] ? 1 : 0;
                unset(
                    $row['_trace_option_index'],
                    $row['_trace_candidate_rank']
                );
            }
            unset($row);

            $governed[$optionGroup] = $decorated;
            $summary[$optionGroup] = [
                'candidate' => $candidateCount,
                'current' => count($decorated) - $secondaryCount,
                'secondary' => $secondaryCount,
                'excluded_invalid' => $excludedInvalid,
            ];
        }

        return [
            'options' => $governed,
            'summary' => $summary,
        ];
    }

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

    private static function containsReplacementCharacter(array $row): bool
    {
        foreach ($row as $value) {
            if (is_scalar($value) && str_contains((string)$value, '�')) {
                return true;
            }
        }

        return false;
    }

    private static function statusLabel(string $status): string
    {
        return self::STATUS_LABELS[$status] ?? '状态待确认';
    }

    private static function sourceLabel(
        string $optionGroup,
        array $row
    ): string {
        return match ($optionGroup) {
            'clauses' => trim((string)($row['source_code'] ?? ''))
                ?: '外部依据待确认',
            'manual_sections' => trim((string)(
                $row['source_doc_number'] ?? ''
            )) ?: '手册来源待确认',
            'procedure_documents' => trim((string)(
                $row['doc_number'] ?? ''
            )) ?: '程序编号待确认',
            'record_forms' => trim((string)($row['doc_number'] ?? ''))
                ?: '记录编号待确认',
            default => trim((string)(
                $row['code'] ?? $row['name'] ?? ''
            )),
        };
    }

    private static function governanceLabel(
        string $optionGroup,
        array $row
    ): string {
        $prefix = (bool)($row['is_candidate'] ?? false)
            ? '★ 本文件候选 · '
            : '';
        $versionAndStatus = (string)$row['version_label']
            . ' / ' . (string)$row['status_label'];

        $label = match ($optionGroup) {
            'clauses' => implode(' / ', array_filter([
                (string)$row['source_label'],
                $versionAndStatus,
                trim((string)($row['clause_number'] ?? ''))
                    . ' ' . trim((string)($row['title'] ?? '')),
            ])),
            'manual_sections' => implode(' / ', array_filter([
                (string)$row['source_label'],
                $versionAndStatus,
                trim((string)($row['section_number'] ?? ''))
                    . ' ' . trim((string)($row['title'] ?? '')),
            ])),
            'procedure_documents' => implode(' / ', array_filter([
                (string)$row['source_label'],
                $versionAndStatus,
                trim((string)($row['title'] ?? '')),
            ])),
            'record_forms' => implode(' / ', array_filter([
                (string)$row['source_label'],
                $versionAndStatus,
                trim((string)($row['name'] ?? '')),
            ])),
            'elements', 'positions' => trim(implode(' ', array_filter([
                trim((string)($row['code'] ?? '')),
                trim((string)($row['name'] ?? '')),
            ]))),
            'modules' => trim(implode(' ', array_filter([
                trim((string)($row['code'] ?? '')),
                trim((string)($row['name'] ?? '')),
            ]))),
            default => trim((string)(
                $row['name'] ?? $row['title'] ?? $row['id'] ?? ''
            )),
        };

        return $prefix . $label;
    }

    private static function markDuplicateVersions(
        string $optionGroup,
        array &$rows
    ): void {
        $groups = [];
        foreach ($rows as $index => $row) {
            $key = self::duplicateKey($optionGroup, $row);
            if ($key !== '') {
                $groups[$key][] = (int)$index;
            }
        }

        foreach ($groups as $indexes) {
            if (count($indexes) < 2) {
                continue;
            }
            $winner = $indexes[0];
            foreach (array_slice($indexes, 1) as $index) {
                if (self::versionPreference($rows[$index])
                    < self::versionPreference($rows[$winner])) {
                    $winner = $index;
                }
            }
            foreach ($indexes as $index) {
                $rows[$index]['is_secondary'] = $index !== $winner;
            }
        }
    }

    private static function duplicateKey(
        string $optionGroup,
        array $row
    ): string {
        if ($optionGroup === 'record_forms') {
            $number = trim((string)(
                $row['canonical_doc_number'] ?? ''
            ));
            if ($number === '') {
                $number = preg_replace(
                    '/^SIM-/i',
                    '',
                    trim((string)($row['doc_number'] ?? ''))
                ) ?? '';
            }

            return strtolower($number);
        }

        return strtolower(trim((string)($row['doc_number'] ?? '')))
            . '|' . strtolower(trim((string)($row['title'] ?? '')));
    }

    private static function versionPreference(array $row): array
    {
        $version = trim((string)($row['version'] ?? ''));
        $status = trim((string)($row['status'] ?? ''));

        return [
            $version === 'GOV-TRIAL/0.2' ? 0 : 1,
            $status === 'obsolete' ? 1 : 0,
            match ($status) {
                'published' => 0,
                'trial_ready' => 1,
                'draft' => 2,
                default => 3,
            },
        ];
    }
}
