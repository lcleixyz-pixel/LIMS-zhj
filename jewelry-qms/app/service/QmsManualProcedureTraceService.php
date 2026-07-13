<?php
declare(strict_types=1);

namespace app\service;

use RuntimeException;
use think\facade\Db;

final class QmsManualProcedureTraceService
{
    public static function fromSnapshot(string $path): array
    {
        if (!is_file($path)) {
            throw new RuntimeException('追溯快照不存在：' . $path);
        }
        $decoded = json_decode((string)file_get_contents($path), true);
        if (!is_array($decoded) || (string)($decoded['schema_version'] ?? '') !== '1.0') {
            throw new RuntimeException('追溯快照格式无效：' . $path);
        }

        $trace = ['_unlinked' => [], '_blockers' => []];
        foreach ((array)($decoded['links'] ?? []) as $index => $link) {
            if (!is_array($link)) {
                throw new RuntimeException('追溯快照 links[' . $index . '] 不是对象');
            }
            $section = trim((string)($link['manual_section'] ?? ''));
            $procedureNumber = trim((string)($link['procedure_number'] ?? ''));
            if ($section === '' || $procedureNumber === '') {
                throw new RuntimeException('追溯快照 links[' . $index . '] 缺少章节或程序编号');
            }
            self::addCandidate($trace, $section, [
                'procedure_number' => $procedureNumber,
                'procedure_document_id' => (string)($link['procedure_document_id'] ?? ''),
                'procedure_version' => (string)($link['procedure_version'] ?? ''),
                'relation_type' => (string)($link['relation_type'] ?? 'supporting'),
                'confidence' => (string)($link['confidence'] ?? 'high'),
                'trace_source' => 'formal_link',
            ]);
        }
        $trace['_unlinked'] = array_values(array_unique(array_map(
            'strval',
            (array)($decoded['unlinked_pilot_procedures'] ?? [])
        )));

        return $trace;
    }

    public static function fromDatabase(array $manualSections, array $pilotProcedures): array
    {
        $manualSections = array_values(array_unique(array_map('strval', $manualSections)));
        $pilotProcedures = array_values(array_unique(array_map('strval', $pilotProcedures)));
        $trace = ['_unlinked' => [], '_blockers' => []];

        $documentRows = Db::table('documents')
            ->whereIn('doc_number', $pilotProcedures)
            ->where('status', 'published')
            ->where('soft_delete', 0)
            ->field('id,doc_number,version')
            ->select()
            ->toArray();
        $documentsByNumber = [];
        foreach ($documentRows as $row) {
            $documentsByNumber[(string)$row['doc_number']][] = $row;
        }
        foreach ($pilotProcedures as $docNumber) {
            $count = count($documentsByNumber[$docNumber] ?? []);
            if ($count > 1) {
                $trace['_blockers'][] = '当前程序版本不唯一：' . $docNumber . '，published 记录 ' . $count . ' 条';
            }
        }

        $manualRows = Db::table('qms_document_blocks')
            ->alias('b')
            ->join('qms_structured_documents sd', 'sd.id = b.structured_document_id')
            ->join('qms_document_block_links l', 'l.block_id = b.id AND l.soft_delete = 0')
            ->leftJoin('documents d', 'd.id = l.procedure_document_id AND d.soft_delete = 0')
            ->where('sd.document_role', 'quality_manual')
            ->where('sd.doc_number', 'XZTC/SC')
            ->where('sd.source_status', 'current')
            ->whereIn('sd.status', ['structured', 'published'])
            ->where('sd.soft_delete', 0)
            ->where('b.soft_delete', 0)
            ->field('b.section_number manual_section,b.id manual_block_id,l.element_id,l.relation_type,l.confidence,d.id procedure_document_id,d.doc_number procedure_number,d.version procedure_version,d.status procedure_status')
            ->select()
            ->toArray();

        $elementSections = [];
        foreach ($manualRows as $row) {
            $section = trim((string)$row['manual_section']);
            if ($section === '' || !self::sectionIsRequested($section, $manualSections)) {
                continue;
            }
            $elementId = trim((string)($row['element_id'] ?? ''));
            if ($elementId !== '') {
                $elementSections[$elementId][$section] = true;
            }
            $procedureNumber = trim((string)($row['procedure_number'] ?? ''));
            if ($procedureNumber === '' || !in_array($procedureNumber, $pilotProcedures, true)) {
                continue;
            }
            if ((string)($row['procedure_status'] ?? '') !== 'published') {
                continue;
            }
            self::addCandidate($trace, $section, [
                'manual_block_id' => (string)$row['manual_block_id'],
                'procedure_number' => $procedureNumber,
                'procedure_document_id' => (string)$row['procedure_document_id'],
                'procedure_version' => (string)$row['procedure_version'],
                'relation_type' => (string)$row['relation_type'],
                'confidence' => (string)$row['confidence'],
                'trace_source' => 'formal_link',
            ]);
        }

        if ($elementSections !== []) {
            $elementRows = Db::table('qms_element_documents')
                ->alias('ed')
                ->join('documents d', 'd.id = ed.document_id AND d.soft_delete = 0')
                ->whereIn('ed.element_id', array_keys($elementSections))
                ->whereIn('d.doc_number', $pilotProcedures)
                ->where('ed.soft_delete', 0)
                ->where('d.status', 'published')
                ->field('ed.element_id,ed.relation_type,d.id procedure_document_id,d.doc_number procedure_number,d.version procedure_version')
                ->select()
                ->toArray();
            foreach ($elementRows as $row) {
                foreach (array_keys($elementSections[(string)$row['element_id']] ?? []) as $section) {
                    self::addCandidate($trace, (string)$section, [
                        'element_id' => (string)$row['element_id'],
                        'procedure_number' => (string)$row['procedure_number'],
                        'procedure_document_id' => (string)$row['procedure_document_id'],
                        'procedure_version' => (string)$row['procedure_version'],
                        'relation_type' => (string)$row['relation_type'],
                        'confidence' => 'medium',
                        'trace_source' => 'element_mapping',
                    ]);
                }
            }
        }

        $linked = [];
        foreach ($trace as $section => $rows) {
            if (str_starts_with((string)$section, '_')) {
                continue;
            }
            foreach ((array)$rows as $row) {
                $linked[(string)$row['procedure_number']] = true;
            }
        }
        $trace['_unlinked'] = array_values(array_filter(
            $pilotProcedures,
            static fn (string $docNumber): bool => !isset($linked[$docNumber])
        ));

        return $trace;
    }

    private static function sectionIsRequested(string $section, array $requested): bool
    {
        foreach ($requested as $target) {
            if ($target === $section || str_starts_with($target, $section . '.') || str_starts_with($section, $target . '.')) {
                return true;
            }
        }

        return false;
    }

    private static function addCandidate(array &$trace, string $section, array $candidate): void
    {
        $key = implode('|', [
            (string)$candidate['procedure_number'],
            (string)$candidate['trace_source'],
            (string)($candidate['procedure_document_id'] ?? ''),
        ]);
        foreach ((array)($trace[$section] ?? []) as $existing) {
            $existingKey = implode('|', [
                (string)$existing['procedure_number'],
                (string)$existing['trace_source'],
                (string)($existing['procedure_document_id'] ?? ''),
            ]);
            if ($key === $existingKey) {
                return;
            }
        }
        $trace[$section][] = $candidate;
    }
}
