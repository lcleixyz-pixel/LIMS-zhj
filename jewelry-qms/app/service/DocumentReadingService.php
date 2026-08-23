<?php
declare(strict_types=1);

namespace app\service;

use RuntimeException;
use think\facade\Db;

final class DocumentReadingService
{
    private const RELATION_GROUPS = [
        'basis' => ['key' => 'basis', 'title' => '依据什么'],
        'implements' => ['key' => 'documents', 'title' => '落实到哪些文件'],
        'supporting' => ['key' => 'documents', 'title' => '落实到哪些文件'],
        'mentions' => ['key' => 'documents', 'title' => '落实到哪些文件'],
        'requires_record' => ['key' => 'records', 'title' => '需要哪些表格'],
        'responsible' => ['key' => 'responsibility', 'title' => '谁负责'],
        'renders_to' => ['key' => 'entry', 'title' => '在系统哪里办理'],
    ];

    public static function reader(string $documentId): array
    {
        $documentId = trim($documentId);
        $document = Db::name('documents')
            ->where('id', $documentId)
            ->where('soft_delete', 0)
            ->find();
        if (!is_array($document)) {
            throw new RuntimeException('文件不存在');
        }

        $structure = Db::name('qms_structured_documents')
            ->where('document_id', $documentId)
            ->where('soft_delete', 0)
            ->order('modified', 'desc')
            ->find();
        $sections = [];
        $relationGroups = [];
        if (is_array($structure)) {
            [$sections, $relationGroups] = self::structuredSections($structure);
        }

        $sourceAsset = DocumentSourceAssetService::assetForDocument($documentId) ?: [];

        return [
            'document' => self::presentDocument($document),
            'structure' => $structure ?: [],
            'sections' => $sections,
            'relation_groups' => $relationGroups,
            'relationship_count' => array_sum(array_map(
                static fn(array $group): int => count((array)($group['items'] ?? [])),
                $relationGroups
            )),
            'source_asset' => $sourceAsset,
            'source_available' => (bool)($sourceAsset['source_available'] ?? false),
            'operation' => DocumentOperationModeService::presentation(),
            'full_trace_url' => '/planning/traceability?document_id=' . rawurlencode($documentId),
            'information_url' => '/document/view?id=' . rawurlencode($documentId),
        ];
    }

    public static function bodyMatchingDocumentIds(string $keyword): array
    {
        $keyword = trim($keyword);
        if ($keyword === '') {
            return [];
        }

        return array_values(array_unique(array_map(
            'strval',
            Db::name('qms_document_blocks')
                ->where('soft_delete', 0)
                ->whereLike('markdown', '%' . $keyword . '%')
                ->column('document_id')
        )));
    }

    private static function structuredSections(array $structure): array
    {
        $detail = QmsDocumentStructureService::structuredDocumentDetail((string)$structure['id']);
        $blockRows = [];
        foreach ((array)($detail['blocks'] ?? []) as $row) {
            $block = is_object($row['block'] ?? null)
                ? $row['block']->toArray()
                : (array)($row['block'] ?? []);
            $blockRows[] = ['block' => $block, 'links' => (array)($row['links'] ?? [])];
        }
        $semantic = QmsTraceSemanticGuardService::assess($structure, $blockRows);
        $expectedManualSections = (array)($semantic['profile']['expected_manual_sections'] ?? []);
        $sections = [];
        $relations = [];
        foreach ($blockRows as $index => $row) {
            $block = (array)$row['block'];
            $anchor = self::anchor((string)($block['stable_key'] ?? ''), $index);
            $sectionTitle = trim(implode(' ', array_filter([
                (string)($block['section_number'] ?? ''),
                (string)($block['title'] ?? ''),
            ]))) ?: '正文';
            $links = [];
            foreach ((array)$row['links'] as $link) {
                if (!is_array($link)) {
                    continue;
                }
                $link['governance_state'] = QmsTraceSemanticGuardService::combinedLinkState(
                    $link,
                    $expectedManualSections
                );
                $link['relation_policy'] = QmsTraceRelationPolicyService::inspectExistingLink($link);
                $decorated = self::decoratedLink($link);
                $links[] = $decorated;
                self::mergeRelation($relations, $decorated, $anchor, $sectionTitle);
            }
            $sections[] = [
                'anchor' => $anchor,
                'number' => (string)($block['section_number'] ?? ''),
                'title' => (string)($block['title'] ?? '正文'),
                'label' => $sectionTitle,
                'html' => QmsReadableMarkdownService::toHtml((string)($block['markdown'] ?? '')),
                'links' => $links,
            ];
        }

        return [$sections, self::groupRelations($relations)];
    }

    private static function decoratedLink(array $link): array
    {
        $presentation = QmsTraceLinkPresentationService::build([$link]);
        $decorated = (array)($presentation['priority'][0] ?? []);
        if ($decorated === []) {
            $decorated = (array)($presentation['groups'][0]['links'][0] ?? $link);
        }
        $state = (string)($decorated['governance_state'] ?? 'supporting');
        $confidence = (string)($decorated['confidence'] ?? '');
        $isMixed = (bool)($decorated['relation_policy']['is_mixed'] ?? false);
        $confirmed = !$isMixed
            && $state !== 'suspected_mismatch'
            && $state !== 'pending_review'
            && $confidence === 'high';
        $decorated['reader_state_label'] = $confirmed ? '已确认' : '待复核';
        $decorated['reader_state_class'] = $confirmed ? 'is-confirmed' : 'is-review';

        return $decorated;
    }

    private static function mergeRelation(
        array &$relations,
        array $link,
        string $sectionAnchor,
        string $sectionTitle
    ): void {
        $relationType = (string)($link['relation_type'] ?? 'supporting');
        $definition = self::RELATION_GROUPS[$relationType] ?? self::RELATION_GROUPS['supporting'];
        $targetIds = [];
        foreach (['element_id', 'clause_id', 'manual_section_id', 'procedure_document_id', 'record_form_template_id', 'position_id', 'business_module_id'] as $field) {
            $value = trim((string)($link[$field] ?? ''));
            if ($value !== '') {
                $targetIds[] = $field . ':' . $value;
            }
        }
        $key = $definition['key'] . '|' . $relationType . '|' . implode('|', $targetIds);
        if (!isset($relations[$key])) {
            $relations[$key] = [
                'group_key' => $definition['key'],
                'group_title' => $definition['title'],
                'relation_type' => $relationType,
                'relation_label' => (string)($link['relation_label'] ?? '关联关系'),
                'state_label' => (string)($link['reader_state_label'] ?? '待复核'),
                'state_class' => (string)($link['reader_state_class'] ?? 'is-review'),
                'targets' => (array)($link['targets'] ?? []),
                'note' => trim((string)($link['note'] ?? '')),
                'sections' => [],
            ];
        }
        $relations[$key]['sections'][$sectionAnchor] = $sectionTitle;
        if ((string)($relations[$key]['state_label'] ?? '') === '已确认'
            && (string)($link['reader_state_label'] ?? '') !== '已确认'
        ) {
            $relations[$key]['state_label'] = '待复核';
            $relations[$key]['state_class'] = 'is-review';
        }
    }

    private static function groupRelations(array $relations): array
    {
        $groups = [];
        foreach ($relations as $relation) {
            $relation['sections'] = array_map(
                static fn(string $title, string $anchor): array => ['anchor' => $anchor, 'title' => $title],
                $relation['sections'],
                array_keys($relation['sections'])
            );
            $key = (string)$relation['group_key'];
            if (!isset($groups[$key])) {
                $groups[$key] = [
                    'key' => $key,
                    'title' => (string)$relation['group_title'],
                    'items' => [],
                ];
            }
            $groups[$key]['items'][] = $relation;
        }

        $ordered = [];
        foreach (['basis', 'documents', 'records', 'responsibility', 'entry'] as $key) {
            if (isset($groups[$key])) {
                $ordered[] = $groups[$key];
            }
        }

        return $ordered;
    }

    private static function presentDocument(array $document): array
    {
        $document['display_title'] = preg_replace(
            '/^\[8021(?:候选试装|测试正式)\]\s*/u',
            '',
            (string)($document['title'] ?? '')
        ) ?: (string)($document['title'] ?? '');
        $document['status_label'] = DocumentPresentationService::statusLabel(
            (string)($document['status'] ?? ''),
            TrialModeService::isEnabled()
        );

        return $document;
    }

    private static function anchor(string $stableKey, int $index): string
    {
        $stableKey = preg_replace('/[^A-Za-z0-9_-]+/', '-', trim($stableKey)) ?? '';
        return 'section-' . ($stableKey !== '' ? trim($stableKey, '-') : (string)($index + 1));
    }

}
