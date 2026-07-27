<?php
declare(strict_types=1);

namespace app\service;

use think\facade\Db;

final class QmsTraceSemanticCandidateService
{
    private const SOURCE_LABEL = '治理装配蓝图 / 本地条款映射';

    private static ?array $blueprintCache = null;

    public static function forDocument(array $document): array
    {
        $candidate = self::fromBlueprint($document, self::blueprint());
        if (!(bool)($candidate['available'] ?? false)) {
            return $candidate;
        }

        return self::enrichFromDatabase($candidate);
    }

    public static function fromBlueprint(array $document, array $blueprint): array
    {
        $canonicalNumber = self::canonicalProcedureNumber(
            (string)($document['doc_number'] ?? '')
        );
        $procedure = null;
        foreach ((array)($blueprint['procedures'] ?? []) as $row) {
            if (
                is_array($row)
                && (string)($row['doc_number'] ?? '') === $canonicalNumber
            ) {
                $procedure = $row;
                break;
            }
        }

        if (!is_array($procedure)) {
            return self::unavailable(
                $canonicalNumber,
                '治理装配蓝图未找到程序：'
                    . ($canonicalNumber !== '' ? $canonicalNumber : '文件编号为空')
            );
        }

        $manualByKey = [];
        foreach ((array)($blueprint['manual_sections'] ?? []) as $section) {
            if (!is_array($section)) {
                continue;
            }
            $sectionKey = trim((string)($section['section_key'] ?? ''));
            if ($sectionKey !== '') {
                $manualByKey[$sectionKey] = $section;
            }
        }

        $manualSections = [];
        foreach ((array)($procedure['manual_sections'] ?? []) as $sectionKey) {
            $section = $manualByKey[(string)$sectionKey] ?? null;
            if (!is_array($section)) {
                continue;
            }
            $sectionNumber = trim((string)($section['section_number'] ?? ''));
            if ($sectionNumber === '') {
                continue;
            }
            $manualSections[$sectionNumber] = [
                'section_key' => (string)$sectionKey,
                'section_number' => $sectionNumber,
                'title' => (string)($section['title'] ?? ''),
                'id' => '',
                'element_id' => '',
                'available' => false,
            ];
        }
        uksort($manualSections, 'strnatcmp');

        $recordTemplates = [];
        foreach ((array)($procedure['record_templates'] ?? []) as $recordNumber) {
            $recordNumber = trim((string)$recordNumber);
            if ($recordNumber === '') {
                continue;
            }
            $recordTemplates[$recordNumber] = [
                'canonical_doc_number' => $recordNumber,
                'doc_number' => str_starts_with($recordNumber, 'SIM-')
                    ? $recordNumber
                    : 'SIM-' . $recordNumber,
                'name' => '',
                'id' => '',
                'available' => false,
            ];
        }
        uksort($recordTemplates, 'strnatcmp');

        $issues = [];
        if ($manualSections === []) {
            $issues[] = '治理蓝图未提供候选手册章节。';
        }
        if ($recordTemplates === []) {
            $issues[] = '治理蓝图未提供候选运行记录。';
        }

        return [
            'available' => true,
            'source_kind' => 'governance_blueprint',
            'source_label' => self::SOURCE_LABEL,
            'canonical_doc_number' => $canonicalNumber,
            'manual_sections' => array_values($manualSections),
            'external_sources' => [],
            'record_templates' => array_values($recordTemplates),
            'review_required' => true,
            'candidate_complete' => false,
            'issues' => $issues,
        ];
    }

    private static function blueprint(): array
    {
        if (self::$blueprintCache === null) {
            self::$blueprintCache = GovernedTrialAssemblyBlueprintService::build();
        }

        return self::$blueprintCache;
    }

    private static function enrichFromDatabase(array $candidate): array
    {
        $issues = array_values((array)($candidate['issues'] ?? []));
        $manualDocumentId = (string)Db::name('documents')
            ->where('doc_number', 'SIM-XZTC/SC')
            ->where('version', GovernedTrialAssemblyBlueprintService::VERSION)
            ->where('soft_delete', 0)
            ->value('id');

        $manualSections = [];
        $elementSections = [];
        foreach ((array)($candidate['manual_sections'] ?? []) as $section) {
            $sectionNumber = (string)($section['section_number'] ?? '');
            $entity = $manualDocumentId !== ''
                ? Db::name('qms_manual_sections')
                    ->where('document_id', $manualDocumentId)
                    ->where('section_number', $sectionNumber)
                    ->where('soft_delete', 0)
                    ->find()
                : null;
            $section['id'] = (string)($entity['id'] ?? '');
            $section['element_id'] = (string)($entity['element_id'] ?? '');
            $section['available'] = is_array($entity);
            if (!$section['available']) {
                $issues[] = '候选手册章节实体待治理：' . $sectionNumber;
            }
            if ((string)$section['element_id'] !== '') {
                $elementSections[(string)$section['element_id']][] = $sectionNumber;
            }
            $manualSections[] = $section;
        }

        $externalSources = self::externalSources($elementSections);
        if ($externalSources === []) {
            $issues[] = '候选手册章节尚未取得可用的本地外部依据映射。';
        }

        $recordTemplates = self::recordTemplates(
            (array)($candidate['record_templates'] ?? []),
            $issues
        );

        $candidate['manual_sections'] = $manualSections;
        $candidate['external_sources'] = $externalSources;
        $candidate['record_templates'] = $recordTemplates;
        $candidate['issues'] = array_values(array_unique($issues));
        $candidate['candidate_complete'] = self::allAvailable($manualSections)
            && self::allAvailable($recordTemplates)
            && $externalSources !== [];

        return $candidate;
    }

    private static function externalSources(array $elementSections): array
    {
        $elementIds = array_keys($elementSections);
        if ($elementIds === []) {
            return [];
        }

        $rows = Db::name('qms_element_clause_links')
            ->alias('map')
            ->join('qms_clauses clause', 'clause.id = map.clause_id')
            ->join('qms_sources source', 'source.id = clause.source_id')
            ->whereIn('map.element_id', $elementIds)
            ->where('map.soft_delete', 0)
            ->where('clause.soft_delete', 0)
            ->where('source.soft_delete', 0)
            ->field(
                'map.element_id,map.clause_id,source.source_code,'
                . 'clause.clause_number,clause.title'
            )
            ->select()
            ->toArray();

        $externalSources = [];
        foreach ($rows as $row) {
            $key = (string)$row['source_code']
                . '|' . (string)$row['clause_number']
                . '|' . (string)$row['clause_id'];
            $manualNumbers = array_values(array_unique(
                $elementSections[(string)$row['element_id']] ?? []
            ));
            sort($manualNumbers, SORT_NATURAL);
            if (isset($externalSources[$key])) {
                $manualNumbers = array_values(array_unique(array_merge(
                    (array)$externalSources[$key]['manual_sections'],
                    $manualNumbers
                )));
                sort($manualNumbers, SORT_NATURAL);
            }
            $externalSources[$key] = [
                'id' => (string)$row['clause_id'],
                'source_code' => (string)$row['source_code'],
                'clause_number' => (string)$row['clause_number'],
                'title' => (string)$row['title'],
                'manual_sections' => $manualNumbers,
                'available' => true,
            ];
        }
        uasort(
            $externalSources,
            static fn(array $left, array $right): int =>
                [
                    (string)$left['source_code'],
                    (string)$left['clause_number'],
                ] <=> [
                    (string)$right['source_code'],
                    (string)$right['clause_number'],
                ]
        );

        return array_values($externalSources);
    }

    private static function recordTemplates(array $candidates, array &$issues): array
    {
        $canonicalNumbers = array_values(array_unique(array_filter(array_map(
            static fn(array $row): string =>
                trim((string)($row['canonical_doc_number'] ?? '')),
            $candidates
        ))));
        $trialNumbers = array_values(array_unique(array_filter(array_map(
            static fn(array $row): string => trim((string)($row['doc_number'] ?? '')),
            $candidates
        ))));
        $rows = [];
        if ($canonicalNumbers !== []) {
            $rows = Db::name('record_form_templates')
                ->whereIn('canonical_doc_number', $canonicalNumbers)
                ->where('soft_delete', 0)
                ->select()
                ->toArray();
        }
        if ($trialNumbers !== []) {
            $rows = array_merge(
                $rows,
                Db::name('record_form_templates')
                    ->whereIn('doc_number', $trialNumbers)
                    ->where('soft_delete', 0)
                    ->select()
                    ->toArray()
            );
        }

        $rowByCanonical = [];
        foreach ($rows as $row) {
            $canonical = trim((string)($row['canonical_doc_number'] ?? ''));
            if ($canonical === '') {
                $docNumber = (string)($row['doc_number'] ?? '');
                $canonical = str_starts_with($docNumber, 'SIM-')
                    ? substr($docNumber, strlen('SIM-'))
                    : $docNumber;
            }
            if ($canonical !== '') {
                $rowByCanonical[$canonical] = $row;
            }
        }

        $result = [];
        foreach ($candidates as $candidate) {
            $canonical = (string)($candidate['canonical_doc_number'] ?? '');
            $row = $rowByCanonical[$canonical] ?? null;
            $candidate['id'] = (string)($row['id'] ?? '');
            $candidate['doc_number'] = (string)(
                $row['doc_number']
                ?? $candidate['doc_number']
                ?? ('SIM-' . $canonical)
            );
            $candidate['name'] = (string)($row['name'] ?? '');
            $candidate['available'] = is_array($row);
            if (!$candidate['available']) {
                $issues[] = '候选记录模板待治理：' . $canonical;
            }
            $result[] = $candidate;
        }

        return $result;
    }

    private static function allAvailable(array $rows): bool
    {
        return $rows !== [] && array_filter(
            $rows,
            static fn(array $row): bool => !($row['available'] ?? false)
        ) === [];
    }

    private static function canonicalProcedureNumber(string $docNumber): string
    {
        $docNumber = trim($docNumber);
        foreach (['SIM-GOV02-', 'SIM-'] as $prefix) {
            if (str_starts_with($docNumber, $prefix)) {
                return substr($docNumber, strlen($prefix));
            }
        }

        return $docNumber;
    }

    private static function unavailable(string $canonicalNumber, string $issue): array
    {
        return [
            'available' => false,
            'source_kind' => 'governance_blueprint',
            'source_label' => self::SOURCE_LABEL,
            'canonical_doc_number' => $canonicalNumber,
            'manual_sections' => [],
            'external_sources' => [],
            'record_templates' => [],
            'review_required' => true,
            'candidate_complete' => false,
            'issues' => [$issue],
        ];
    }
}
