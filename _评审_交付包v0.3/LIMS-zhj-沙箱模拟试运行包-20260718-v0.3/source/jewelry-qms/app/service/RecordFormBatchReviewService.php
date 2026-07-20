<?php
declare(strict_types=1);

namespace app\service;

use think\facade\Db;

class RecordFormBatchReviewService
{
    public static function build(int $year = 2025, bool $writeReport = true): array
    {
        $reportIndex = self::reportIndex($year);
        $confirmations = RecordFormLayoutConfirmationService::all($year);
        $rows = self::recordRows($year, $reportIndex, $confirmations);
        $summary = self::summary($rows, $year);
        $dashboard = [
            'year' => $year,
            'generated_at' => date('Y-m-d H:i:s'),
            'summary' => $summary,
            'rows' => $rows,
            'reports' => array_values($reportIndex['report_paths']),
        ];

        if ($writeReport) {
            $dashboard['report'] = self::writeReport($dashboard);
        }

        return $dashboard;
    }

    public static function filteredRows(array $rows, string $module = '', string $attention = ''): array
    {
        return array_values(array_filter($rows, static function (array $row) use ($module, $attention): bool {
            if ($module !== '' && (string)$row['module'] !== $module) {
                return false;
            }
            if ($attention === 'blank_required' && (int)$row['blank_required_count'] === 0) {
                return false;
            }
            if ($attention === 'low_confidence' && (int)$row['low_confidence_count'] === 0) {
                return false;
            }
            if ($attention === 'ai_candidate' && (int)$row['ai_candidate_count'] === 0) {
                return false;
            }
            if ($attention === 'no_preview_pdf' && (string)$row['preview_pdf_url'] !== '') {
                return false;
            }

            return true;
        }));
    }

    private static function recordRows(int $year, array $reportIndex, array $confirmations = []): array
    {
        $records = Db::name('record_form_instances')
            ->alias('i')
            ->leftJoin('record_form_templates t', 't.id = i.template_id')
            ->where('i.status', 'draft')
            ->whereLike('i.record_title', $year . '运行记录-%')
            ->field([
                'i.id',
                'i.template_id',
                'i.record_title',
                'i.doc_number',
                'i.template_name',
                'i.template_module',
                'i.template_field_schema',
                'i.field_values',
                'i.created',
                't.name' => 'current_template_name',
                't.module' => 'current_module',
                't.field_schema' => 'current_field_schema',
            ])
            ->order('i.doc_number', 'asc')
            ->select()
            ->toArray();

        $rows = [];
        foreach ($records as $record) {
            $id = (string)$record['id'];
            $schema = RecordFormSchemaService::decode((string)($record['current_field_schema'] ?: $record['template_field_schema']));
            $values = self::decodeJson((string)($record['field_values'] ?? ''));
            $blankRequired = self::missingKeys($schema, $values);
            $report = $reportIndex['by_instance'][$id] ?? [];
            $confirmation = is_array($confirmations[$id] ?? null) ? $confirmations[$id] : [];
            $preview = self::latestPreviewPdf($id);
            $lowConfidence = array_values(array_unique(array_merge(
                (array)($report['low_confidence_fields'] ?? []),
                $blankRequired
            )));
            $aiCandidate = array_values(array_unique((array)($report['ai_candidate_fields'] ?? [])));

            $rows[] = [
                'id' => $id,
                'template_id' => (string)$record['template_id'],
                'doc_number' => (string)$record['doc_number'],
                'module' => (string)($record['current_module'] ?: $record['template_module']),
                'name' => (string)($record['current_template_name'] ?: $record['template_name']),
                'record_title' => (string)$record['record_title'],
                'created' => (string)($record['created'] ?? ''),
                'instance_url' => '/record_form_instance/view?id=' . rawurlencode($id),
                'edit_url' => '/record_form_instance/edit?id=' . rawurlencode($id),
                'print_url' => '/record_form_instance/print?id=' . rawurlencode($id),
                'preview_pdf_url' => $preview['url'],
                'preview_pdf_path' => $preview['path'],
                'preview_pdf_file' => $preview['file'],
                'blank_required_fields' => $blankRequired,
                'blank_required_text' => implode(', ', $blankRequired),
                'blank_required_count' => count($blankRequired),
                'low_confidence_fields' => $lowConfidence,
                'low_confidence_text' => implode(', ', $lowConfidence),
                'low_confidence_count' => count($lowConfidence),
                'ai_candidate_fields' => $aiCandidate,
                'ai_candidate_text' => implode(', ', $aiCandidate),
                'ai_candidate_count' => count($aiCandidate),
                'manual_layout_status' => (string)($confirmation['status'] ?? $report['manual_layout_status'] ?? 'pending'),
                'manual_layout_note' => (string)($confirmation['note'] ?? ''),
                'manual_layout_updated_at' => (string)($confirmation['updated_at'] ?? ''),
                'manual_layout_updated_by' => (string)($confirmation['updated_by'] ?? ''),
                'warnings' => array_values(array_unique((array)($report['warnings'] ?? []))),
                'source_markdown_path' => (string)($report['source_markdown_path'] ?? ''),
                'report_batches' => array_values(array_unique((array)($report['report_batches'] ?? []))),
                'field_count' => count($schema),
            ];
        }

        return $rows;
    }

    private static function reportIndex(int $year): array
    {
        $files = glob(root_path() . 'runtime' . DIRECTORY_SEPARATOR . 'record-form-batches' . DIRECTORY_SEPARATOR . (string)$year . DIRECTORY_SEPARATOR . '*' . DIRECTORY_SEPARATOR . 'report.json') ?: [];
        usort($files, static fn (string $a, string $b): int => filemtime($a) <=> filemtime($b));
        $index = ['by_instance' => [], 'report_paths' => []];
        foreach ($files as $file) {
            if (basename(dirname($file)) === 'review-dashboard') {
                continue;
            }
            $report = json_decode((string)file_get_contents($file), true);
            if (!is_array($report)) {
                continue;
            }
            $batchId = (string)($report['batch_id'] ?? basename(dirname($file)));
            $index['report_paths'][] = str_replace(root_path(), '', $file);
            foreach ((array)($report['rows'] ?? []) as $row) {
                $instanceId = (string)($row['instance_id'] ?? $row['id'] ?? '');
                if ($instanceId === '') {
                    continue;
                }
                $current = $index['by_instance'][$instanceId] ?? [];
                $current['low_confidence_fields'] = array_values(array_unique(array_merge(
                    (array)($current['low_confidence_fields'] ?? []),
                    (array)($row['low_confidence_fields'] ?? [])
                )));
                $current['ai_candidate_fields'] = array_values(array_unique(array_merge(
                    (array)($current['ai_candidate_fields'] ?? []),
                    (array)($row['ai_candidate_fields'] ?? [])
                )));
                $current['warnings'] = array_values(array_unique(array_merge(
                    (array)($current['warnings'] ?? []),
                    (array)($row['warnings'] ?? [])
                )));
                $current['report_batches'] = array_values(array_unique(array_merge(
                    (array)($current['report_batches'] ?? []),
                    [$batchId]
                )));
                foreach (['manual_layout_status', 'source_markdown_path'] as $key) {
                    if (!empty($row[$key])) {
                        $current[$key] = $row[$key];
                    }
                }
                $index['by_instance'][$instanceId] = $current;
            }
        }

        return $index;
    }

    private static function latestPreviewPdf(string $recordId): array
    {
        $dir = root_path() . 'runtime' . DIRECTORY_SEPARATOR . 'record-form-preview-pdf' . DIRECTORY_SEPARATOR . $recordId;
        $files = is_dir($dir) ? (glob($dir . DIRECTORY_SEPARATOR . '*.pdf') ?: []) : [];
        if ($files === []) {
            return ['file' => '', 'path' => '', 'url' => ''];
        }
        usort($files, static fn (string $a, string $b): int => filemtime($b) <=> filemtime($a));
        $fullPath = $files[0];
        $file = basename($fullPath);

        return [
            'file' => $file,
            'path' => str_replace(root_path(), '', $fullPath),
            'url' => '/record_form_instance/downloadPreviewPdf?id=' . rawurlencode($recordId) . '&file=' . rawurlencode($file),
        ];
    }

    private static function summary(array $rows, int $year): array
    {
        $moduleCounts = [];
        foreach ($rows as $row) {
            $module = (string)$row['module'];
            if (!isset($moduleCounts[$module])) {
                $moduleCounts[$module] = ['total' => 0, 'blank_required' => 0, 'low_confidence' => 0, 'ai_candidate' => 0, 'no_preview_pdf' => 0];
            }
            $moduleCounts[$module]['total']++;
            if ((int)$row['blank_required_count'] > 0) {
                $moduleCounts[$module]['blank_required']++;
            }
            if ((int)$row['low_confidence_count'] > 0) {
                $moduleCounts[$module]['low_confidence']++;
            }
            if ((int)$row['ai_candidate_count'] > 0) {
                $moduleCounts[$module]['ai_candidate']++;
            }
            if ((string)$row['preview_pdf_url'] === '') {
                $moduleCounts[$module]['no_preview_pdf']++;
            }
        }
        ksort($moduleCounts);

        return [
            'year' => $year,
            'total' => count($rows),
            'blank_required' => count(array_filter($rows, static fn (array $row): bool => (int)$row['blank_required_count'] > 0)),
            'low_confidence' => count(array_filter($rows, static fn (array $row): bool => (int)$row['low_confidence_count'] > 0)),
            'ai_candidate' => count(array_filter($rows, static fn (array $row): bool => (int)$row['ai_candidate_count'] > 0)),
            'with_preview_pdf' => count(array_filter($rows, static fn (array $row): bool => (string)$row['preview_pdf_url'] !== '')),
            'no_preview_pdf' => count(array_filter($rows, static fn (array $row): bool => (string)$row['preview_pdf_url'] === '')),
            'layout_pending' => count(array_filter($rows, static fn (array $row): bool => (string)$row['manual_layout_status'] === 'pending')),
            'layout_accepted' => count(array_filter($rows, static fn (array $row): bool => (string)$row['manual_layout_status'] === 'accepted')),
            'layout_needs_adjustment' => count(array_filter($rows, static fn (array $row): bool => (string)$row['manual_layout_status'] === 'needs_adjustment')),
            'module_counts' => $moduleCounts,
        ];
    }

    private static function writeReport(array $dashboard): array
    {
        $year = (string)($dashboard['year'] ?? date('Y'));
        $relativeDir = 'runtime' . DIRECTORY_SEPARATOR . 'record-form-batches' . DIRECTORY_SEPARATOR . $year . DIRECTORY_SEPARATOR . 'review-dashboard';
        $dir = root_path() . $relativeDir;
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $jsonPath = $dir . DIRECTORY_SEPARATOR . 'report.json';
        $markdownPath = $dir . DIRECTORY_SEPARATOR . 'report.md';
        file_put_contents($jsonPath, json_encode($dashboard, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        file_put_contents($markdownPath, self::markdown($dashboard));

        return [
            'json_path' => str_replace(root_path(), '', $jsonPath),
            'markdown_path' => str_replace(root_path(), '', $markdownPath),
        ];
    }

    private static function markdown(array $dashboard): string
    {
        $summary = $dashboard['summary'] ?? [];
        $lines = [
            '# 2025运行记录版式确认台账',
            '',
            '- 生成时间：' . (string)($dashboard['generated_at'] ?? ''),
            '- 实例数：' . (int)($summary['total'] ?? 0),
            '- 有临时PDF：' . (int)($summary['with_preview_pdf'] ?? 0),
            '- 无临时PDF：' . (int)($summary['no_preview_pdf'] ?? 0),
            '- 留空必填：' . (int)($summary['blank_required'] ?? 0),
            '- 低置信：' . (int)($summary['low_confidence'] ?? 0),
            '- AI候选：' . (int)($summary['ai_candidate'] ?? 0),
            '- 版式待确认：' . (int)($summary['layout_pending'] ?? 0),
            '- 版式已通过：' . (int)($summary['layout_accepted'] ?? 0),
            '- 版式需调整：' . (int)($summary['layout_needs_adjustment'] ?? 0),
            '',
            '## 模块汇总',
            '',
            '| 模块 | 实例 | 留空必填 | 低置信 | AI候选 | 无临时PDF |',
            '| --- | ---: | ---: | ---: | ---: | ---: |',
        ];
        foreach ((array)($summary['module_counts'] ?? []) as $module => $counts) {
            $lines[] = '| ' . implode(' | ', [
                self::mdCell((string)$module),
                (string)(int)($counts['total'] ?? 0),
                (string)(int)($counts['blank_required'] ?? 0),
                (string)(int)($counts['low_confidence'] ?? 0),
                (string)(int)($counts['ai_candidate'] ?? 0),
                (string)(int)($counts['no_preview_pdf'] ?? 0),
            ]) . ' |';
        }
        $lines[] = '';
        $lines[] = '## 逐表确认';
        $lines[] = '';
        $lines[] = '| 编号 | 模块 | 表格 | 实例 | 打印 | 临时PDF | 留空必填 | 低置信 | AI候选 | 版式确认 |';
        $lines[] = '| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |';
        foreach ((array)($dashboard['rows'] ?? []) as $row) {
            $lines[] = '| ' . implode(' | ', [
                self::mdCell((string)$row['doc_number']),
                self::mdCell((string)$row['module']),
                self::mdCell((string)$row['name']),
                '[' . self::mdCell('查看') . '](' . (string)$row['instance_url'] . ')',
                '[' . self::mdCell('打印') . '](' . (string)$row['print_url'] . ')',
                (string)$row['preview_pdf_url'] !== '' ? '[' . self::mdCell('下载') . '](' . (string)$row['preview_pdf_url'] . ')' : '-',
                self::mdCell(implode(', ', (array)$row['blank_required_fields']) ?: '-'),
                self::mdCell(implode(', ', (array)$row['low_confidence_fields']) ?: '-'),
                self::mdCell(implode(', ', (array)$row['ai_candidate_fields']) ?: '-'),
                self::mdCell((string)$row['manual_layout_status']),
            ]) . ' |';
        }

        return implode(PHP_EOL, $lines) . PHP_EOL;
    }

    private static function missingKeys(array $schema, array $values): array
    {
        $missing = [];
        foreach ($schema as $field) {
            $key = (string)($field['key'] ?? '');
            if ($key === '' || empty($field['required'])) {
                continue;
            }
            if (($field['type'] ?? '') === 'repeatable_table') {
                if (($values[$key] ?? []) === []) {
                    $missing[] = $key;
                }
                continue;
            }
            if (trim((string)($values[$key] ?? '')) === '') {
                $missing[] = $key;
            }
        }

        return $missing;
    }

    private static function decodeJson(string $json): array
    {
        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : [];
    }

    private static function mdCell(string $value): string
    {
        return str_replace(["\n", "\r", '|'], [' ', ' ', '/'], $value);
    }
}
