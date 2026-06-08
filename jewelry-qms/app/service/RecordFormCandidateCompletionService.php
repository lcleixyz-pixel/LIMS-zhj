<?php
declare(strict_types=1);

namespace app\service;

use think\facade\Db;

class RecordFormCandidateCompletionService
{
    public static function complete(array $options = []): array
    {
        $year = max(2000, min(2100, (int)($options['year'] ?? 2025)));
        $apply = (bool)($options['apply'] ?? false);
        $previewPdf = (bool)($options['preview_pdf'] ?? false);
        $batchId = trim((string)($options['batch_id'] ?? '2025-candidate-completion'));
        if ($batchId === '') {
            $batchId = '2025-candidate-completion';
        }

        $rows = [];
        $records = self::records($year);
        $summary = [
            'year' => $year,
            'apply' => $apply,
            'preview_pdf' => $previewPdf,
            'batch_id' => $batchId,
            'total' => count($records),
            'updated' => 0,
            'unchanged' => 0,
            'candidate_fields' => 0,
            'still_blank_required' => 0,
            'preview_pdfs' => 0,
            'errors' => 0,
        ];

        foreach ($records as $record) {
            try {
                $row = self::completeRecord($record, $year, $apply, $previewPdf);
                if (($row['decision'] ?? '') === 'updated') {
                    $summary['updated']++;
                    $summary['candidate_fields'] += count((array)($row['ai_candidate_fields'] ?? []));
                    if (!empty($row['preview_pdf'])) {
                        $summary['preview_pdfs']++;
                    }
                } else {
                    $summary['unchanged']++;
                }
                if (!empty($row['blank_required_fields_after'])) {
                    $summary['still_blank_required']++;
                }
                $rows[] = $row;
            } catch (\Throwable $exception) {
                $summary['errors']++;
                $rows[] = [
                    'instance_id' => (string)($record['id'] ?? ''),
                    'doc_number' => (string)($record['doc_number'] ?? ''),
                    'name' => (string)($record['template_name'] ?? ''),
                    'decision' => 'error',
                    'error' => $exception->getMessage(),
                ];
            }
        }

        $report = self::writeReport($batchId, $year, $summary, $rows);
        $summary['rows'] = $rows;
        $summary['report'] = $report;

        return $summary;
    }

    private static function records(int $year): array
    {
        return Db::name('record_form_instances')
            ->where('status', 'draft')
            ->whereLike('record_title', $year . '运行记录-%')
            ->order('doc_number', 'asc')
            ->order('record_title', 'asc')
            ->select()
            ->toArray();
    }

    private static function completeRecord(array $record, int $year, bool $apply, bool $previewPdf): array
    {
        $schema = RecordFormSchemaService::decode((string)$record['template_field_schema']);
        $values = self::decodeValues((string)($record['field_values'] ?? ''));
        $beforeMissing = self::missingRequired($schema, $values);
        if ($beforeMissing === []) {
            return self::row($record, 'unchanged', [], [], [], $beforeMissing, $beforeMissing);
        }

        $candidateFields = [];
        $evidence = [];
        foreach ($schema as $field) {
            $key = (string)$field['key'];
            if (!in_array($key, $beforeMissing, true) || !self::isBlank($values[$key] ?? null)) {
                continue;
            }
            $candidate = self::candidateForField($field, $record, $values, $year);
            if ($candidate === null || self::isBlank($candidate)) {
                continue;
            }
            $values[$key] = $candidate;
            $candidateFields[] = $key;
            $evidence[] = $key . '=候选补全';
        }

        if ($candidateFields === []) {
            return self::row($record, 'unchanged', [], [], [], $beforeMissing, $beforeMissing);
        }

        $values = RecordFormSchemaService::enforceReadonly($schema, $values);
        $afterMissing = self::missingRequired($schema, $values);
        $errors = RecordFormSchemaService::validateValues($schema, $values);
        if ($errors !== []) {
            foreach (array_keys($errors) as $errorKey) {
                if (!str_contains((string)$errorKey, '.')) {
                    unset($values[$errorKey]);
                }
            }
            $afterMissing = self::missingRequired($schema, $values);
        }

        $preview = null;
        if ($apply) {
            Db::name('record_form_instances')->where('id', (string)$record['id'])->update([
                'field_values' => json_encode($values, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) ?: '{}',
                'modified' => date('Y-m-d H:i:s'),
            ]);
            $record['field_values'] = json_encode($values, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) ?: '{}';

            if ($previewPdf) {
                $preview = self::renderPreviewPdf($record, $values);
            }
        }

        return self::row($record, 'updated', $candidateFields, $candidateFields, $evidence, $beforeMissing, $afterMissing, $preview);
    }

    private static function row(
        array $record,
        string $decision,
        array $candidateFields,
        array $lowConfidenceFields,
        array $evidence,
        array $beforeMissing,
        array $afterMissing,
        ?array $preview = null
    ): array {
        return [
            'instance_id' => (string)$record['id'],
            'template_id' => (string)$record['template_id'],
            'doc_number' => (string)$record['doc_number'],
            'name' => (string)$record['template_name'],
            'module' => (string)$record['template_module'],
            'decision' => $decision,
            'instance_url' => '/record_form_instance/view?id=' . rawurlencode((string)$record['id']),
            'print_url' => '/record_form_instance/print?id=' . rawurlencode((string)$record['id']),
            'manual_layout_status' => 'pending',
            'ai_candidate_fields' => array_values(array_unique($candidateFields)),
            'low_confidence_fields' => array_values(array_unique($lowConfidenceFields)),
            'blank_required_fields_before' => $beforeMissing,
            'blank_required_fields_after' => $afterMissing,
            'evidence' => $evidence,
            'preview_pdf' => $preview,
            'warnings' => $candidateFields === [] && $beforeMissing !== [] ? ['仍有必填字段无法可靠生成候选值，需人工填写。'] : [],
        ];
    }

    private static function candidateForField(array $field, array $record, array $values, int $year): mixed
    {
        $type = (string)$field['type'];
        if ($type === 'repeatable_table') {
            return self::candidateRows($field, $record, $year);
        }

        $key = (string)$field['key'];
        $label = (string)$field['label'];
        $doc = (string)$record['doc_number'];
        $name = (string)$record['template_name'];
        $text = strtolower($key . ' ' . $label . ' ' . $name);

        if ($type === 'date') {
            return self::dateCandidate($doc, $key, $year);
        }
        if ($type === 'select') {
            return self::selectCandidate($field);
        }
        if ($type === 'checkbox') {
            return '1';
        }
        if (in_array($type, ['person', 'signature'], true)) {
            return self::personCandidate($key, $label);
        }

        if (str_contains($text, 'equipment_code') || str_contains($label, '仪器编号') || str_contains($label, '设备编号')) {
            return self::equipmentNumberCandidate($values, $record);
        }
        if (str_contains($text, 'employee_name') || str_contains($text, 'trainee_name') || str_contains($label, '姓名')) {
            return self::personCandidate($key, $label);
        }
        if ($key === 'position' || str_contains($label, '岗位')) {
            return '检测师';
        }
        if ($key === 'training_content') {
            return 'CMA质量管理体系、岗位职责、检测方法、记录控制及安全保密要求。';
        }
        if ($key === 'topic') {
            return self::topicCandidate($doc, $name);
        }
        if ($key === 'sample_number') {
            return 'ZHJ2025-001';
        }
        if ($key === 'sample_name') {
            return '和田玉样品';
        }
        if ($key === 'document_name') {
            return '质量手册及程序文件';
        }
        if ($key === 'input_summary') {
            return '管理体系运行情况、内外部审核结果、人员设备资源、客户反馈、风险机会及质量目标完成情况。';
        }
        if ($key === 'output_conclusion') {
            return '管理体系总体运行有效，资源配置满足检测活动需要，需持续完善记录完整性和风险闭环跟踪。';
        }
        if ($key === 'meeting_record') {
            return '按管理评审计划完成输入事项评审，形成改进措施并跟踪验证。';
        }
        if ($key === 'source_channel') {
            return '全国标准信息公共服务平台、标准发布公告及现行受控文件目录。';
        }
        if ($key === 'overall_conclusion') {
            return '所查标准现行有效，适用于本机构2025年度检测能力范围，后续按受控文件要求执行。';
        }
        if ($key === 'item_name') {
            return 'QMS记录表单与运行记录数据';
        }
        if ($key === 'applicant') {
            return self::personCandidate($key, $label);
        }
        if ($key === 'content_to_change') {
            return '补充2025年度质量运行记录候选数据和打印版式确认记录。';
        }
        if ($key === 'change_reason') {
            return '记录表格实例需与实际机构信息、人员设备基础数据和年度运行要求保持一致。';
        }
        if ($key === 'changed_content') {
            return '完善记录实例字段、临时PDF预览和人工确认台账。';
        }
        if ($key === 'view_purpose') {
            return '查看检测区域监控图像，确认样品处置和检测活动秩序正常。';
        }
        if (str_contains($text, 'check_area')) {
            return '检测室、样品室、档案柜及公共区域';
        }

        return self::genericTextCandidate($key, $label, $doc, $name);
    }

    private static function candidateRows(array $field, array $record, int $year): array
    {
        $key = (string)$field['key'];
        $label = (string)$field['label'];
        $columns = (array)($field['columns'] ?? []);
        if (str_contains($key . $label, 'performance') || str_contains($label, '性能确认')) {
            return [
                self::rowByColumns($columns, ['test_description' => '红外光谱仪波数准确性确认', 'high_limit' => '允许偏差范围内', 'low_limit' => '允许偏差范围内', 'measured_value' => '符合标准谱图要求', 'result' => '通过']),
                self::rowByColumns($columns, ['test_description' => '红外光谱仪重复性确认', 'high_limit' => '重复测试一致', 'low_limit' => '重复测试一致', 'measured_value' => '重复性符合要求', 'result' => '通过']),
            ];
        }
        if (str_contains($key . $label, 'check_items') || str_contains($label, '检查项目')) {
            return [
                self::rowByColumns($columns, ['item' => '检测区域环境与安全通道', 'result' => '符合', 'problem' => '', 'responsible_person' => '张晓磊', 'verification' => '符合要求']),
                self::rowByColumns($columns, ['item' => '仪器设备用电及标识状态', 'result' => '符合', 'problem' => '', 'responsible_person' => '张晓磊', 'verification' => '符合要求']),
                self::rowByColumns($columns, ['item' => '样品存放与留样管理', 'result' => '符合', 'problem' => '', 'responsible_person' => '张晓磊', 'verification' => '符合要求']),
            ];
        }

        return [self::rowByColumns($columns, [])];
    }

    private static function rowByColumns(array $columns, array $preferred): array
    {
        $row = [];
        foreach ($columns as $column) {
            $key = (string)$column['key'];
            if (array_key_exists($key, $preferred)) {
                $row[$key] = $preferred[$key];
                continue;
            }
            $row[$key] = self::cellCandidate($column);
        }

        return $row;
    }

    private static function cellCandidate(array $field): string
    {
        $type = (string)$field['type'];
        if ($type === 'select') {
            return self::selectCandidate($field);
        }
        if ($type === 'date') {
            return '2025-06-30';
        }
        if (in_array($type, ['person', 'signature'], true)) {
            return '张晓磊';
        }

        $key = (string)$field['key'];
        $label = (string)$field['label'];
        if (str_contains($key . $label, 'content') || str_contains($label, '内容')) {
            return '按质量管理体系和岗位要求完成。';
        }
        if (str_contains($key . $label, 'result') || str_contains($label, '结果')) {
            return '符合要求';
        }
        if (str_contains($key . $label, 'name') || str_contains($label, '姓名')) {
            return '张晓磊';
        }

        return '/';
    }

    private static function dateCandidate(string $docNumber, string $key, int $year): string
    {
        if (in_array($key, ['start_date'], true)) {
            return $year . '-01-01';
        }
        if (in_array($key, ['signed_date'], true)) {
            return $year . '-01-05';
        }
        if (str_contains($key, 'audit')) {
            return $year . '-10-20';
        }
        if (str_contains($key, 'review') || str_contains($key, 'meeting')) {
            return $year . '-12-15';
        }
        if (str_contains($key, 'monitor')) {
            return $year . '-06-20';
        }
        if (str_contains($key, 'check') || str_contains($key, 'confirmation')) {
            return $year . '-06-30';
        }

        $prefix = '';
        if (preg_match('/BG-(\d{2})/i', $docNumber, $match) === 1) {
            $prefix = $match[1];
        }
        $map = [
            '01' => '01-15', '02' => '03-31', '03' => '04-15', '04' => '06-30',
            '05' => '01-20', '06' => '04-20', '07' => '04-25', '08' => '06-15',
            '10' => '05-10', '11' => '05-20', '12' => '06-10', '13' => '06-30',
            '14' => '07-20', '15' => '07-25', '16' => '08-10', '17' => '08-20',
            '19' => '09-01', '20' => '10-20', '21' => '12-15', '22' => '09-20',
            '23' => '09-25', '24' => '10-10', '26' => '11-10', '28' => '02-18',
            '29' => '12-01', '30' => '03-20', '31' => '04-10', '32' => '05-15',
            '33' => '06-05', '34' => '07-10', '35' => '01-10',
        ];

        return $year . '-' . ($map[$prefix] ?? '06-30');
    }

    private static function selectCandidate(array $field): string
    {
        $options = (array)($field['options'] ?? []);
        foreach (['通过', '符合', '符合要求', '满足', '合格', '已检', '留样', '基本满足'] as $candidate) {
            if (in_array($candidate, $options, true)) {
                return $candidate;
            }
        }

        return (string)($options[0] ?? '');
    }

    private static function personCandidate(string $key, string $label): string
    {
        $text = strtolower($key . ' ' . $label);
        if (str_contains($text, 'tech') || str_contains($label, '技术')) {
            return '闫红';
        }
        if (str_contains($text, 'reviewer') || str_contains($label, '复核')) {
            return '张晓磊';
        }
        if (str_contains($text, 'confirmer') || str_contains($label, '确认')) {
            return '李成辉';
        }
        if (str_contains($text, 'signatory') || str_contains($label, '签字')) {
            return '刘恒春';
        }

        return '张晓磊';
    }

    private static function equipmentNumberCandidate(array $values, array $record): string
    {
        $name = (string)($values['equipment_name'] ?? '') . ' ' . (string)$record['template_name'];
        if (str_contains($name, '红外')) {
            return 'XZTC-HW01';
        }
        if (str_contains($name, 'X射线') || str_contains($name, '荧光')) {
            return 'XZTC-CJY01';
        }
        if (str_contains($name, '天平')) {
            return 'XZTCH-TP02';
        }
        if (str_contains($name, '折射')) {
            return 'XZTCH-ZSY01';
        }

        return 'XZTC-CJY01';
    }

    private static function topicCandidate(string $docNumber, string $name): string
    {
        if (str_contains($docNumber, 'BG-30-01')) {
            return '2025年度内部质量监控计划';
        }
        if (str_contains($docNumber, 'BG-30-02')) {
            return '内部质量监控异常情况跟踪';
        }
        if (str_contains($docNumber, 'BG-30-03')) {
            return '2025年度能力验证计划';
        }
        if (str_contains($docNumber, 'BG-12-02')) {
            return '外来人员进入检测区域登记';
        }

        return $name;
    }

    private static function genericTextCandidate(string $key, string $label, string $docNumber, string $name): string
    {
        if (str_contains($key . $label, 'time') || str_contains($label, '时间')) {
            return self::dateCandidate($docNumber, $key, 2025) . ' 10:00';
        }
        if (str_contains($key . $label, 'result') || str_contains($key . $label, 'conclusion') || str_contains($label, '结论')) {
            return '符合要求';
        }
        if (str_contains($key . $label, 'purpose') || str_contains($label, '目的')) {
            return '确认2025年度质量管理体系运行状态满足要求。';
        }

        return $name . '候选记录';
    }

    private static function renderPreviewPdf(array $record, array $values): array
    {
        $template = [
            'id' => (string)$record['template_id'],
            'doc_number' => (string)$record['doc_number'],
            'name' => (string)$record['template_name'],
            'module' => (string)$record['template_module'],
            'version' => (string)$record['template_version'],
            'status' => 'published',
            'review_status' => '',
            'print_template_key' => (string)$record['template_print_template_key'],
            'field_schema' => (string)$record['template_field_schema'],
            'source_file_name' => '',
        ];
        $html = RecordFormPrintService::render((string)$record['template_print_template_key'], $template, $values);
        $pdf = PdfRenderService::renderHtmlPreview($html, (string)$record['id'], (string)$record['record_title']);
        $pdf['download_url'] = '/record_form_instance/downloadPreviewPdf?id=' . rawurlencode((string)$record['id'])
            . '&file=' . rawurlencode((string)$pdf['file_name']);

        return $pdf;
    }

    private static function writeReport(string $batchId, int $year, array $summary, array $rows): array
    {
        $relativeDir = 'runtime' . DIRECTORY_SEPARATOR . 'record-form-batches' . DIRECTORY_SEPARATOR . (string)$year . DIRECTORY_SEPARATOR . $batchId;
        $dir = root_path() . $relativeDir;
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $report = [
            'batch_id' => $batchId,
            'year' => $year,
            'created_at' => date('Y-m-d H:i:s'),
            'summary' => $summary,
            'rows' => $rows,
        ];
        $jsonPath = $dir . DIRECTORY_SEPARATOR . 'report.json';
        $markdownPath = $dir . DIRECTORY_SEPARATOR . 'report.md';
        file_put_contents($jsonPath, json_encode($report, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        file_put_contents($markdownPath, self::markdown($report));

        return [
            'json_path' => str_replace(root_path(), '', $jsonPath),
            'markdown_path' => str_replace(root_path(), '', $markdownPath),
        ];
    }

    private static function markdown(array $report): string
    {
        $summary = $report['summary'] ?? [];
        $lines = [
            '# 2025运行记录候选补全报告',
            '',
            '- 批次：' . (string)($report['batch_id'] ?? ''),
            '- 创建时间：' . (string)($report['created_at'] ?? ''),
            '- 更新实例：' . (int)($summary['updated'] ?? 0),
            '- 候选字段：' . (int)($summary['candidate_fields'] ?? 0),
            '- 仍有留空必填：' . (int)($summary['still_blank_required'] ?? 0),
            '- 临时PDF：' . (int)($summary['preview_pdfs'] ?? 0),
            '',
            '| 编号 | 表格 | 决策 | 实例 | 临时PDF | 候选字段 | 补全前留空 | 补全后留空 |',
            '| --- | --- | --- | --- | --- | --- | --- | --- |',
        ];
        foreach ((array)($report['rows'] ?? []) as $row) {
            $instance = (string)($row['instance_url'] ?? '');
            $download = (string)($row['preview_pdf']['download_url'] ?? '');
            $lines[] = '| ' . implode(' | ', [
                self::md((string)($row['doc_number'] ?? '')),
                self::md((string)($row['name'] ?? '')),
                self::md((string)($row['decision'] ?? '')),
                $instance !== '' ? '[查看](' . $instance . ')' : '-',
                $download !== '' ? '[下载](' . $download . ')' : '-',
                self::md(implode(', ', (array)($row['ai_candidate_fields'] ?? [])) ?: '-'),
                self::md(implode(', ', (array)($row['blank_required_fields_before'] ?? [])) ?: '-'),
                self::md(implode(', ', (array)($row['blank_required_fields_after'] ?? [])) ?: '-'),
            ]) . ' |';
        }

        return implode(PHP_EOL, $lines) . PHP_EOL;
    }

    private static function missingRequired(array $schema, array $values): array
    {
        $missing = [];
        foreach ($schema as $field) {
            $key = (string)$field['key'];
            if (empty($field['required'])) {
                continue;
            }
            if (self::isBlank($values[$key] ?? null)) {
                $missing[] = $key;
            }
        }

        return $missing;
    }

    private static function decodeValues(string $json): array
    {
        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : [];
    }

    private static function isBlank(mixed $value): bool
    {
        if (is_array($value)) {
            return count($value) === 0;
        }

        return trim((string)$value) === '';
    }

    private static function md(string $value): string
    {
        return str_replace(["\n", "\r", '|'], [' ', ' ', '/'], $value);
    }
}
