<?php
declare(strict_types=1);

namespace app\service;

use app\model\Document;

final class QmsFileGovernanceWorkbenchService
{
    public static function detail(string $structuredId, string $currentUserId = ''): array
    {
        $detail = QmsDocumentStructureService::structuredDocumentDetail($structuredId);
        if ($detail === []) {
            return [];
        }

        $structured = self::row($detail['document'] ?? []);
        $schemaCoverage = QmsDocumentStructureService::recordRequirementSchemaCoverage();
        $schemaRows = array_values(array_filter(
            (array)($schemaCoverage['rows'] ?? []),
            static fn(array $row): bool =>
                (string)($row['structured_document_id'] ?? '') === $structuredId
        ));
        $artifacts = GovernedTrialResolvedDocumentService::resolvedArtifactLinks($structured);
        $conflicts = GovernedTrialResolvedDocumentService::currentConflictSummary(
            (string)($structured['doc_number'] ?? '')
        );

        $controlledDocument = [];
        $workflow = [
            'stage' => 'unlinked',
            'stage_label' => '尚未关联受控文件，暂不能进入签批。',
        ];
        $documentId = trim((string)($structured['document_id'] ?? ''));
        if ($documentId !== '') {
            $document = Document::where('id', $documentId)
                ->where('soft_delete', 0)
                ->find();
            if ($document) {
                $controlledDocument = $document->toArray();
                try {
                    $workflow = ApprovalService::documentWorkflowStatus($document, $currentUserId);
                } catch (\Throwable) {
                    $workflow = [
                        'stage' => 'unavailable',
                        'stage_label' => '签批状态暂时无法读取。',
                    ];
                }
            }
        }

        return self::fromSnapshot(
            $detail,
            $schemaRows,
            $artifacts,
            $conflicts,
            $controlledDocument,
            $workflow
        );
    }

    public static function fromSnapshot(
        array $detail,
        array $schemaRows,
        array $artifacts,
        array $conflicts,
        array $controlledDocument,
        array $workflow
    ): array {
        $document = self::row($detail['document'] ?? []);
        if ($document === []) {
            return [];
        }

        $externalSources = [];
        $manualSections = [];
        $procedureBlocks = [];
        $recordEvidence = [];
        $businessModules = [];
        $firstReviewUrl = '';

        foreach ((array)($detail['blocks'] ?? []) as $blockRow) {
            $block = self::row(is_array($blockRow) ? ($blockRow['block'] ?? []) : []);
            if ($block === []) {
                continue;
            }
            $blockId = trim((string)($block['id'] ?? ''));
            $reviewUrl = $blockId !== ''
                ? '/planning/structures/links/review?block_id=' . rawurlencode($blockId)
                : '';
            if ($firstReviewUrl === '' && $reviewUrl !== '') {
                $firstReviewUrl = $reviewUrl;
            }
            self::pushUnique($procedureBlocks, [
                'id' => $blockId,
                'section_number' => (string)($block['section_number'] ?? ''),
                'title' => (string)($block['title'] ?? ''),
                'block_type' => (string)($block['block_type'] ?? ''),
                'review_url' => $reviewUrl,
            ], $blockId !== '' ? $blockId : (string)($block['title'] ?? ''));

            foreach ((array)(is_array($blockRow) ? ($blockRow['links'] ?? []) : []) as $rawLink) {
                $link = self::row($rawLink);
                if ($link === []) {
                    continue;
                }

                if (trim((string)($link['clause_number'] ?? '')) !== '') {
                    self::pushUnique($externalSources, [
                        'id' => (string)($link['clause_id'] ?? ''),
                        'source_code' => (string)($link['source_code'] ?? ''),
                        'clause_number' => (string)($link['clause_number'] ?? ''),
                        'title' => (string)($link['clause_title'] ?? ''),
                    ], self::targetKey(
                        (string)($link['clause_id'] ?? ''),
                        (string)($link['source_code'] ?? '') . '|'
                            . (string)($link['clause_number'] ?? '') . '|'
                            . (string)($link['clause_title'] ?? '')
                    ));
                }

                if (trim((string)($link['section_number'] ?? '')) !== '') {
                    self::pushUnique($manualSections, [
                        'id' => (string)($link['manual_section_id'] ?? ''),
                        'section_number' => (string)($link['section_number'] ?? ''),
                        'title' => (string)($link['manual_title'] ?? ''),
                    ], self::targetKey(
                        (string)($link['manual_section_id'] ?? ''),
                        (string)($link['section_number'] ?? '') . '|'
                            . (string)($link['manual_title'] ?? '')
                    ));
                }

                if (trim((string)($link['record_number'] ?? '')) !== '') {
                    self::pushUnique($recordEvidence, [
                        'id' => (string)($link['record_form_template_id'] ?? ''),
                        'doc_number' => (string)($link['record_number'] ?? ''),
                        'name' => (string)($link['record_name'] ?? ''),
                    ], self::targetKey(
                        (string)($link['record_form_template_id'] ?? ''),
                        (string)($link['record_number'] ?? '') . '|'
                            . (string)($link['record_name'] ?? '')
                    ));
                }

                if (trim((string)($link['module_code'] ?? '')) !== '') {
                    self::pushUnique($businessModules, [
                        'id' => (string)($link['business_module_id'] ?? ''),
                        'code' => (string)($link['module_code'] ?? ''),
                        'name' => (string)($link['module_name'] ?? ''),
                        'url' => (string)($link['module_url'] ?? ''),
                    ], self::targetKey(
                        (string)($link['business_module_id'] ?? ''),
                        (string)($link['module_code'] ?? '') . '|'
                            . (string)($link['module_name'] ?? '')
                    ));
                }
            }
        }

        $recordCoverage = self::recordCoverage($schemaRows);
        $documentBlockers = array_values((array)($conflicts['document_blockers'] ?? []));
        $systemNotices = array_values((array)($conflicts['system_notices'] ?? []));
        $workflowStage = (string)($workflow['stage'] ?? '');

        $missingChain = [];
        if ($externalSources === []) {
            $missingChain[] = '外部依据';
        }
        if ($manualSections === []) {
            $missingChain[] = '手册条款';
        }
        if ($procedureBlocks === []) {
            $missingChain[] = '程序落实方法';
        }
        if ($recordEvidence === [] && ($recordCoverage['total'] > 0 || $schemaRows === [])) {
            $missingChain[] = '运行证据';
        }

        $actions = self::actions(
            $document,
            $controlledDocument,
            $workflow,
            $recordCoverage,
            $documentBlockers,
            $artifacts,
            $missingChain,
            $firstReviewUrl
        );
        $level = self::summaryLevel(
            $missingChain,
            $recordCoverage,
            $documentBlockers,
            $workflowStage
        );
        $checks = self::checkCounts(
            $externalSources,
            $manualSections,
            $procedureBlocks,
            $recordEvidence,
            $recordCoverage,
            $documentBlockers,
            $workflowStage
        );

        $documentId = trim((string)($controlledDocument['id'] ?? $document['document_id'] ?? ''));
        $document['structure_url'] = '/planning/structures/view?id='
            . rawurlencode((string)($document['id'] ?? ''));
        $document['workbench_url'] = '/planning/structures/workbench?id='
            . rawurlencode((string)($document['id'] ?? ''));
        $document['document_url'] = $documentId !== ''
            ? '/document/view?id=' . rawurlencode($documentId)
            : '';
        $document['revision_url'] = $documentId !== ''
            ? '/document/revise?id=' . rawurlencode($documentId)
            : '';

        return [
            'document' => $document,
            'boundary_notice' => '仅限8021治理试运行；纸质体系仍为唯一正式体系。',
            'summary' => [
                'level' => $level,
                'message' => self::summaryMessage($level, $missingChain, $recordCoverage, $documentBlockers),
                'next_step' => (string)($actions[0]['description'] ?? '当前没有待办理事项。'),
                'completed_checks' => $checks['completed'],
                'total_checks' => $checks['total'],
            ],
            'chain' => [
                'external_sources' => array_values($externalSources),
                'manual_sections' => array_values($manualSections),
                'procedure_blocks' => array_values($procedureBlocks),
                'record_evidence' => array_values($recordEvidence),
                'business_modules' => array_values($businessModules),
                'missing' => $missingChain,
            ],
            'record_coverage' => $recordCoverage,
            'artifacts' => $artifacts,
            'conflicts' => [
                'available' => (bool)($conflicts['available'] ?? false),
                'document_blockers' => $documentBlockers,
                'system_notices' => $systemNotices,
            ],
            'signing' => $workflow,
            'controlled_document' => $controlledDocument,
            'actions' => array_slice($actions, 0, 3),
        ];
    }

    private static function recordCoverage(array $schemaRows): array
    {
        $summary = [
            'total' => count($schemaRows),
            'covered' => 0,
            'needs_review' => 0,
            'missing' => 0,
            'rows' => [],
        ];
        foreach ($schemaRows as $rawRow) {
            $row = self::row($rawRow);
            $linked = (int)($row['linked_record_forms'] ?? 0);
            $status = (string)($row['coverage_status'] ?? '');
            if ($status === 'covered') {
                $normalized = 'covered';
                $summary['covered']++;
            } elseif ($linked > 0) {
                $normalized = 'needs_review';
                $summary['needs_review']++;
            } else {
                $normalized = 'missing';
                $summary['missing']++;
            }
            $row['workbench_status'] = $normalized;
            $summary['rows'][] = $row;
        }

        return $summary;
    }

    private static function actions(
        array $document,
        array $controlledDocument,
        array $workflow,
        array $recordCoverage,
        array $documentBlockers,
        array $artifacts,
        array $missingChain,
        string $firstReviewUrl
    ): array {
        $actions = [];
        if ($missingChain !== []) {
            $actions[] = [
                'type' => 'trace',
                'label' => '补齐追溯链',
                'description' => '缺少' . implode('、', $missingChain) . '，请先复核内容块追溯。',
                'url' => $firstReviewUrl,
                'enabled' => $firstReviewUrl !== '',
                'disabled_reason' => $firstReviewUrl === '' ? '尚无可进入的内容块。' : '',
            ];
        }

        $coverageIssue = null;
        foreach ($recordCoverage['rows'] as $row) {
            if (($row['workbench_status'] ?? '') !== 'covered') {
                $coverageIssue = $row;
                break;
            }
        }
        if (is_array($coverageIssue)) {
            $url = (string)($coverageIssue['trace_review_url'] ?? '');
            $actions[] = [
                'type' => 'record',
                'label' => '前往记录支撑复核',
                'description' => (string)($coverageIssue['record_form_labels'] ?? $coverageIssue['block_title'] ?? '记录字段仍需确认'),
                'url' => $url,
                'enabled' => $url !== '',
                'disabled_reason' => $url === '' ? '该记录要求尚无复核入口。' : '',
            ];
        }

        if ($documentBlockers !== []) {
            $url = (string)($artifacts['conflicts_url'] ?? '');
            $actions[] = [
                'type' => 'conflict',
                'label' => '查看冲突证据',
                'description' => (string)($documentBlockers[0]['message'] ?? '本文件仍有阻断冲突。'),
                'url' => $url,
                'enabled' => $url !== '',
                'disabled_reason' => $url === '' ? '冲突审查材料尚未生成。' : '',
            ];
        }

        $documentId = trim((string)($controlledDocument['id'] ?? $document['document_id'] ?? ''));
        $documentStatus = (string)($controlledDocument['status'] ?? $document['status'] ?? '');
        if (
            $documentId !== ''
            && $documentStatus !== 'obsolete'
            && $missingChain === []
            && $recordCoverage['missing'] === 0
            && $recordCoverage['needs_review'] === 0
            && $documentBlockers === []
        ) {
            $actions[] = [
                'type' => 'document',
                'label' => ($workflow['stage'] ?? '') === 'completed' ? '查看签批证据' : '进入文件签批',
                'description' => (string)($workflow['stage_label'] ?? '进入现有文件页继续办理。'),
                'url' => '/document/view?id=' . rawurlencode($documentId),
                'enabled' => true,
                'disabled_reason' => '',
            ];
        }

        if ($actions === []) {
            $actions[] = [
                'type' => 'complete',
                'label' => '查看结构化文件',
                'description' => $documentStatus === 'obsolete'
                    ? '该文件已废止保留，不进入提交或发布链。'
                    : '当前没有待办理事项。',
                'url' => '/planning/structures/view?id=' . rawurlencode((string)($document['id'] ?? '')),
                'enabled' => true,
                'disabled_reason' => '',
            ];
        }

        return $actions;
    }

    private static function summaryLevel(
        array $missingChain,
        array $recordCoverage,
        array $documentBlockers,
        string $workflowStage
    ): string {
        if ($missingChain !== []) {
            return 'blocked';
        }
        if (
            $recordCoverage['missing'] > 0
            || $recordCoverage['needs_review'] > 0
            || $documentBlockers !== []
        ) {
            return 'warning';
        }
        if ($workflowStage === 'completed') {
            return 'completed';
        }

        return 'ready';
    }

    private static function summaryMessage(
        string $level,
        array $missingChain,
        array $recordCoverage,
        array $documentBlockers
    ): string {
        if ($level === 'blocked') {
            return '证据链尚未闭合：缺少' . implode('、', $missingChain) . '。';
        }
        if ($documentBlockers !== []) {
            return '本文件存在阻断冲突，暂不能提交签批。';
        }
        if ($recordCoverage['missing'] > 0 || $recordCoverage['needs_review'] > 0) {
            return '可继续治理试运行，但记录支撑仍需复核。';
        }
        if ($level === 'completed') {
            return '证据链和试运行签批均已完成。';
        }

        return '证据链已闭合，可进入现有文件页继续试运行签批。';
    }

    private static function checkCounts(
        array $externalSources,
        array $manualSections,
        array $procedureBlocks,
        array $recordEvidence,
        array $recordCoverage,
        array $documentBlockers,
        string $workflowStage
    ): array {
        $completed = 0;
        $completed += $externalSources !== [] ? 1 : 0;
        $completed += $manualSections !== [] ? 1 : 0;
        $completed += $procedureBlocks !== [] ? 1 : 0;
        $completed += $recordEvidence !== []
            && $recordCoverage['missing'] === 0
            && $recordCoverage['needs_review'] === 0 ? 1 : 0;
        $completed += $documentBlockers === [] ? 1 : 0;
        $completed += $workflowStage === 'completed' ? 1 : 0;

        return ['completed' => $completed, 'total' => 6];
    }

    private static function row(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }
        if (is_object($value) && method_exists($value, 'toArray')) {
            $row = $value->toArray();

            return is_array($row) ? $row : [];
        }

        return [];
    }

    private static function pushUnique(array &$target, array $row, string $key): void
    {
        if ($key === '' || array_key_exists($key, $target)) {
            return;
        }
        $target[$key] = $row;
    }

    private static function targetKey(string $id, string $fallback): string
    {
        $id = trim($id);

        return $id !== '' ? $id : trim($fallback);
    }
}
