<?php
declare(strict_types=1);

namespace app\service;

use app\model\QmsStructuredDocument;

final class QmsGovernanceVersionResolverService
{
    public const DOCUMENT_PREFIX = 'SIM-GOV02-XZTC/CX-';

    public static function candidateVersion(): string
    {
        return GovernedTrialResolvedManifestService::VERSION;
    }

    public static function candidateResolution(): array
    {
        return self::resolveCandidateRecords(self::candidateStructuredDocuments());
    }

    public static function candidateStructuredDocuments(): array
    {
        return QmsStructuredDocument::where('soft_delete', 0)
            ->where('document_role', 'procedure')
            ->where('version', self::candidateVersion())
            ->whereLike('doc_number', self::DOCUMENT_PREFIX . '%')
            ->order('doc_number', 'asc')
            ->order('modified', 'desc')
            ->select()
            ->toArray();
    }

    public static function resolveCandidateRecords(iterable $records): array
    {
        $groups = [];
        foreach ($records as $value) {
            $row = self::row($value);
            $docNumber = trim((string)($row['doc_number'] ?? ''));
            if (
                $docNumber === ''
                || !str_starts_with($docNumber, self::DOCUMENT_PREFIX)
                || (string)($row['document_role'] ?? '') !== 'procedure'
                || (string)($row['version'] ?? '') !== self::candidateVersion()
                || (int)($row['soft_delete'] ?? 0) !== 0
            ) {
                continue;
            }
            $groups[$docNumber][] = $row;
        }

        ksort($groups, SORT_NATURAL);
        $resolved = [];
        foreach ($groups as $docNumber => $candidates) {
            $count = count($candidates);
            if ($count === 1) {
                $candidate = $candidates[0];
                $resolved[$docNumber] = [
                    'state' => 'current_candidate',
                    'candidate_count' => 1,
                    'structured_id' => (string)($candidate['id'] ?? ''),
                    'document_id' => (string)($candidate['document_id'] ?? ''),
                    'candidate' => $candidate,
                    'candidates' => $candidates,
                ];
                continue;
            }
            $resolved[$docNumber] = [
                'state' => 'candidate_conflict',
                'candidate_count' => $count,
                'structured_id' => '',
                'document_id' => '',
                'candidate' => [],
                'candidates' => array_values($candidates),
            ];
        }

        return [
            'candidate_version' => self::candidateVersion(),
            'candidate_count' => count($resolved),
            'by_doc_number' => $resolved,
        ];
    }

    public static function classifyControlledDocuments(
        iterable $documents,
        array $resolution = []
    ): array {
        if ($resolution === []) {
            $resolution = self::candidateResolution();
        }
        $candidateIndex = (array)($resolution['by_doc_number'] ?? []);
        $roles = [];
        foreach ($documents as $value) {
            $document = self::row($value);
            $id = trim((string)($document['id'] ?? ''));
            if ($id === '') {
                continue;
            }
            $docNumber = trim((string)($document['doc_number'] ?? ''));
            $candidate = (array)($candidateIndex[$docNumber] ?? []);
            if ($candidate === []) {
                $roles[$id] = self::role('standard', '');
                continue;
            }
            if ((string)($candidate['state'] ?? '') === 'candidate_conflict') {
                $roles[$id] = self::role('candidate_conflict', '候选冲突');
                continue;
            }
            if (
                (string)($candidate['document_id'] ?? '') !== ''
                && hash_equals((string)$candidate['document_id'], $id)
            ) {
                $roles[$id] = self::role('current_candidate', '当前电子治理候选');
                continue;
            }
            $roles[$id] = self::role('source_version', '纸质现用来源');
        }

        return $roles;
    }

    private static function role(string $role, string $label): array
    {
        return [
            'role' => $role,
            'label' => $label,
            'candidate_version' => self::candidateVersion(),
        ];
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
}
