<?php
declare(strict_types=1);

namespace app\service;

final class QmsGovernanceQueueService
{
    private const FILTER_STATUSES = [
        'all',
        'aligned',
        'suspected_mismatch',
        'missing_primary',
        'review_required',
        'version_conflict',
    ];

    public static function listing(array $filters = [], string $currentUserId = ''): array
    {
        $resolution = QmsGovernanceVersionResolverService::candidateResolution();
        $snapshots = [];

        foreach ((array)($resolution['by_doc_number'] ?? []) as $candidate) {
            $candidate = is_array($candidate) ? $candidate : [];
            $state = (string)($candidate['state'] ?? '');
            if ($state === 'candidate_conflict') {
                $candidates = array_values((array)($candidate['candidates'] ?? []));
                $snapshots[] = [
                    'version_state' => 'candidate_conflict',
                    'document' => is_array($candidates[0] ?? null) ? $candidates[0] : [],
                ];
                continue;
            }

            $structuredId = trim((string)($candidate['structured_id'] ?? ''));
            $workbench = $structuredId !== ''
                ? QmsFileGovernanceWorkbenchService::detail($structuredId, $currentUserId)
                : [];
            $snapshots[] = [
                'version_state' => 'current_candidate',
                'document' => (array)($candidate['candidate'] ?? []),
                'workbench' => $workbench,
            ];
        }

        return self::fromSnapshots($snapshots, $filters);
    }

    public static function fromSnapshots(array $snapshots, array $filters = []): array
    {
        $normalizedFilters = self::normalizeFilters($filters);
        $rows = [];
        foreach ($snapshots as $snapshot) {
            $row = self::queueRow(is_array($snapshot) ? $snapshot : []);
            if ($row !== []) {
                $rows[] = $row;
            }
        }
        usort(
            $rows,
            static fn(array $left, array $right): int =>
                strnatcasecmp((string)$left['doc_number'], (string)$right['doc_number'])
        );

        $summary = [
            'aligned' => 0,
            'suspected_mismatch' => 0,
            'missing_primary' => 0,
            'review_required' => 0,
            'version_conflict' => 0,
            'blocked' => 0,
            'warning' => 0,
            'completed' => 0,
        ];
        foreach ($rows as $row) {
            $status = (string)($row['semantic_status'] ?? '');
            if (array_key_exists($status, $summary)) {
                $summary[$status]++;
            }
            $level = (string)($row['summary_level'] ?? '');
            if (in_array($level, ['blocked', 'warning', 'completed'], true)) {
                $summary[$level]++;
            }
        }

        $visibleRows = array_values(array_filter(
            $rows,
            static function (array $row) use ($normalizedFilters): bool {
                $status = (string)$normalizedFilters['status'];
                if ($status !== 'all' && (string)$row['semantic_status'] !== $status) {
                    return false;
                }
                $keyword = (string)$normalizedFilters['keyword'];
                if ($keyword === '') {
                    return true;
                }
                $haystack = (string)$row['doc_number'] . ' ' . (string)$row['title'];

                return stripos($haystack, $keyword) !== false;
            }
        ));

        return [
            'scope' => [
                'version' => QmsGovernanceVersionResolverService::candidateVersion(),
                'total' => count($rows),
                'label' => count($rows) . ' 份程序治理队列',
            ],
            'summary' => $summary,
            'filters' => $normalizedFilters,
            'all_rows' => $rows,
            'rows' => $visibleRows,
            'visible_count' => count($visibleRows),
        ];
    }

    public static function nextUnresolved(
        string $currentStructuredId,
        string $currentUserId = ''
    ): array {
        $queue = self::listing([], $currentUserId);

        return self::nextUnresolvedFromRows(
            (array)($queue['all_rows'] ?? []),
            $currentStructuredId
        );
    }

    public static function nextUnresolvedFromRows(
        array $rows,
        string $currentStructuredId
    ): array {
        $rows = array_values($rows);
        if ($rows === []) {
            return [];
        }

        $currentIndex = -1;
        foreach ($rows as $index => $row) {
            if ((string)($row['structured_id'] ?? '') === $currentStructuredId) {
                $currentIndex = $index;
                break;
            }
        }

        $count = count($rows);
        for ($offset = 1; $offset <= $count; $offset++) {
            $index = $currentIndex >= 0
                ? ($currentIndex + $offset) % $count
                : $offset - 1;
            $candidate = is_array($rows[$index] ?? null) ? $rows[$index] : [];
            $structuredId = trim((string)($candidate['structured_id'] ?? ''));
            if (
                $structuredId === ''
                || $structuredId === $currentStructuredId
                || !($candidate['next_eligible'] ?? false)
            ) {
                continue;
            }

            return $candidate;
        }

        return [];
    }

    private static function normalizeFilters(array $filters): array
    {
        $status = trim((string)($filters['status'] ?? 'all'));
        if (!in_array($status, self::FILTER_STATUSES, true)) {
            $status = 'all';
        }

        return [
            'status' => $status,
            'keyword' => trim((string)($filters['keyword'] ?? '')),
        ];
    }

    private static function queueRow(array $snapshot): array
    {
        $versionState = (string)($snapshot['version_state'] ?? 'current_candidate');
        $workbench = (array)($snapshot['workbench'] ?? []);
        $document = (array)($workbench['document'] ?? $snapshot['document'] ?? []);
        if ($document === []) {
            return [];
        }

        if ($versionState === 'candidate_conflict') {
            return self::conflictRow($document);
        }

        $semanticStatus = trim((string)($workbench['semantic_guard']['status'] ?? ''));
        if ($semanticStatus === '') {
            $semanticStatus = 'missing_primary';
        }
        $summary = (array)($workbench['summary'] ?? []);
        $chain = (array)($workbench['chain'] ?? []);
        $recordCoverage = (array)($workbench['record_coverage'] ?? []);
        $record = self::recordStatus($recordCoverage);
        $structuredId = trim((string)($document['id'] ?? ''));
        $documentStatus = trim((string)($document['status'] ?? ''));
        $summaryLevel = trim((string)($summary['level'] ?? 'blocked'));

        return [
            'structured_id' => $structuredId,
            'document_id' => (string)($document['document_id'] ?? ''),
            'doc_number' => (string)($document['doc_number'] ?? ''),
            'title' => (string)($document['title'] ?? ''),
            'version' => (string)($document['version'] ?? ''),
            'document_status' => $documentStatus,
            'modified' => (string)($document['modified'] ?? ''),
            'version_state' => 'current_candidate',
            'version_label' => '当前电子治理候选',
            'semantic_status' => $semanticStatus,
            'semantic_label' => self::semanticLabel($semanticStatus),
            'summary_level' => $summaryLevel,
            'summary_message' => (string)($summary['message'] ?? '当前状态暂不可用。'),
            'next_step' => (string)($summary['next_step'] ?? '进入工作台复核。'),
            'completed_checks' => (int)($summary['completed_checks'] ?? 0),
            'total_checks' => (int)($summary['total_checks'] ?? 0),
            'missing_chain' => array_values((array)($chain['missing'] ?? [])),
            'missing_chain_text' => implode('、', (array)($chain['missing'] ?? [])),
            'record_status' => $record['status'],
            'record_label' => $record['label'],
            'workbench_url' => $structuredId !== ''
                ? '/planning/structures/workbench?id=' . rawurlencode($structuredId)
                : '',
            'next_eligible' => $structuredId !== ''
                && $documentStatus !== 'obsolete'
                && $summaryLevel !== 'completed',
        ];
    }

    private static function conflictRow(array $document): array
    {
        return [
            'structured_id' => '',
            'document_id' => '',
            'doc_number' => (string)($document['doc_number'] ?? ''),
            'title' => (string)($document['title'] ?? ''),
            'version' => (string)($document['version'] ?? ''),
            'document_status' => (string)($document['status'] ?? ''),
            'modified' => (string)($document['modified'] ?? ''),
            'version_state' => 'candidate_conflict',
            'version_label' => '候选冲突',
            'semantic_status' => 'version_conflict',
            'semantic_label' => '候选冲突',
            'summary_level' => 'blocked',
            'summary_message' => '同一文件编号存在多个治理候选，系统未自动选取。',
            'next_step' => '请先保留唯一候选版本，再进入治理工作台。',
            'completed_checks' => 0,
            'total_checks' => 6,
            'missing_chain' => [],
            'missing_chain_text' => '—',
            'record_status' => 'unavailable',
            'record_label' => '待候选裁决',
            'workbench_url' => '',
            'next_eligible' => false,
        ];
    }

    private static function recordStatus(array $coverage): array
    {
        if ((int)($coverage['missing'] ?? 0) > 0) {
            return ['status' => 'missing', 'label' => '记录未挂接'];
        }
        if ((int)($coverage['needs_review'] ?? 0) > 0) {
            return ['status' => 'needs_review', 'label' => '字段待复核'];
        }
        if ((int)($coverage['total'] ?? 0) > 0) {
            return ['status' => 'covered', 'label' => '字段已覆盖'];
        }

        return ['status' => 'not_applicable', 'label' => '无记录要求'];
    }

    private static function semanticLabel(string $status): string
    {
        return match ($status) {
            'aligned' => '已对齐',
            'suspected_mismatch' => '疑似错挂',
            'missing_primary' => '主链缺失',
            'review_required' => '待人工复核',
            default => '状态暂不可用',
        };
    }
}
