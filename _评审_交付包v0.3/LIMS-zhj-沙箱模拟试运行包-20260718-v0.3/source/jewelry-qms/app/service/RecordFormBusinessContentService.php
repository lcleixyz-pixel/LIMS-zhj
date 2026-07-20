<?php
declare(strict_types=1);

namespace app\service;

use think\facade\Db;

class RecordFormBusinessContentService
{
    public static function completeBg01(array $options = []): array
    {
        $options['prefixes'] = ['01'];
        $options['batch_id'] = trim((string)($options['batch_id'] ?? '2025-bg01-business-content')) ?: '2025-bg01-business-content';

        return self::completePrefixes($options);
    }

    public static function completeBg02ToBg04(array $options = []): array
    {
        $options['prefixes'] = ['02', '03', '04'];
        $options['batch_id'] = trim((string)($options['batch_id'] ?? '2025-bg02-bg04-business-content')) ?: '2025-bg02-bg04-business-content';

        return self::completePrefixes($options);
    }

    public static function completeBg05ToBg08(array $options = []): array
    {
        $options['prefixes'] = ['05', '06', '07', '08'];
        $options['batch_id'] = trim((string)($options['batch_id'] ?? '2025-bg05-bg08-business-content')) ?: '2025-bg05-bg08-business-content';

        return self::completePrefixes($options);
    }

    public static function completeBg09ToBg12(array $options = []): array
    {
        $options['prefixes'] = ['09', '10', '11', '12'];
        $options['batch_id'] = trim((string)($options['batch_id'] ?? '2025-bg09-bg12-business-content')) ?: '2025-bg09-bg12-business-content';

        return self::completePrefixes($options);
    }

    public static function completeBg13ToBg16(array $options = []): array
    {
        $options['prefixes'] = ['13', '14', '15', '16'];
        $options['batch_id'] = trim((string)($options['batch_id'] ?? '2025-bg13-bg16-business-content')) ?: '2025-bg13-bg16-business-content';

        return self::completePrefixes($options);
    }

    public static function completeBg17ToBg20(array $options = []): array
    {
        $options['prefixes'] = ['17', '18', '19', '20'];
        $options['batch_id'] = trim((string)($options['batch_id'] ?? '2025-bg17-bg20-business-content')) ?: '2025-bg17-bg20-business-content';

        return self::completePrefixes($options);
    }

    public static function completeBg21ToBg24(array $options = []): array
    {
        $options['prefixes'] = ['21', '22', '23', '24'];
        $options['batch_id'] = trim((string)($options['batch_id'] ?? '2025-bg21-bg24-business-content')) ?: '2025-bg21-bg24-business-content';

        return self::completePrefixes($options);
    }

    public static function completeBg25ToBg28(array $options = []): array
    {
        $options['prefixes'] = ['25', '26', '27', '28'];
        $options['batch_id'] = trim((string)($options['batch_id'] ?? '2025-bg25-bg28-business-content')) ?: '2025-bg25-bg28-business-content';

        return self::completePrefixes($options);
    }

    public static function completeBg29ToBg32(array $options = []): array
    {
        $options['prefixes'] = ['29', '30', '31', '32'];
        $options['batch_id'] = trim((string)($options['batch_id'] ?? '2025-bg29-bg32-business-content')) ?: '2025-bg29-bg32-business-content';

        return self::completePrefixes($options);
    }

    public static function completeBg33ToBg35(array $options = []): array
    {
        $options['prefixes'] = ['33', '34', '35'];
        $options['batch_id'] = trim((string)($options['batch_id'] ?? '2025-bg33-bg35-business-content')) ?: '2025-bg33-bg35-business-content';

        return self::completePrefixes($options);
    }

    public static function completeMetaRecords(array $options = []): array
    {
        $options['batch_id'] = trim((string)($options['batch_id'] ?? '2025-bg-meta-business-content')) ?: '2025-bg-meta-business-content';

        return self::completeMeta($options);
    }

    private static function completeMeta(array $options): array
    {
        $year = max(2000, min(2100, (int)($options['year'] ?? 2025)));
        $apply = (bool)($options['apply'] ?? false);
        $previewPdf = (bool)($options['preview_pdf'] ?? false);
        $batchId = trim((string)($options['batch_id'] ?? '2025-bg-meta-business-content')) ?: '2025-bg-meta-business-content';

        $records = Db::name('record_form_instances')
            ->where('status', 'draft')
            ->whereLike('record_title', $year . '运行记录-%')
            ->whereLike('doc_number', 'XZTC/BG-META-%')
            ->order('doc_number', 'asc')
            ->select()
            ->toArray();

        $rows = [];
        $summary = [
            'year' => $year,
            'apply' => $apply,
            'preview_pdf' => $previewPdf,
            'total' => count($records),
            'updated' => 0,
            'unchanged' => 0,
            'preview_pdfs' => 0,
            'errors' => 0,
        ];

        foreach ($records as $record) {
            try {
                $row = self::completeRecord($record, $year, $apply, $previewPdf);
                if (($row['decision'] ?? '') === 'updated') {
                    $summary['updated']++;
                    if (!empty($row['preview_pdf'])) {
                        $summary['preview_pdfs']++;
                    }
                } elseif (($row['decision'] ?? '') === 'error') {
                    $summary['errors']++;
                } else {
                    $summary['unchanged']++;
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

    private static function completePrefixes(array $options): array
    {
        $year = max(2000, min(2100, (int)($options['year'] ?? 2025)));
        $apply = (bool)($options['apply'] ?? false);
        $previewPdf = (bool)($options['preview_pdf'] ?? false);
        $batchId = trim((string)($options['batch_id'] ?? '2025-business-content'));
        if ($batchId === '') {
            $batchId = '2025-business-content';
        }
        $prefixes = array_values(array_filter((array)($options['prefixes'] ?? ['01'])));

        $query = Db::name('record_form_instances')
            ->where('status', 'draft')
            ->whereLike('record_title', $year . '运行记录-%');
        $query->where(function ($q) use ($prefixes) {
            foreach ($prefixes as $index => $prefix) {
                if ($index === 0) {
                    $q->whereLike('doc_number', 'XZTC/BG-' . $prefix . '-%');
                } else {
                    $q->whereOr('doc_number', 'like', 'XZTC/BG-' . $prefix . '-%');
                }
            }
        });
        $records = $query->order('doc_number', 'asc')->select()->toArray();
        $rows = [];
        $summary = [
            'year' => $year,
            'apply' => $apply,
            'preview_pdf' => $previewPdf,
            'total' => count($records),
            'updated' => 0,
            'unchanged' => 0,
            'preview_pdfs' => 0,
            'errors' => 0,
        ];

        foreach ($records as $record) {
            try {
                $row = self::completeRecord($record, $year, $apply, $previewPdf);
                if (($row['decision'] ?? '') === 'updated') {
                    $summary['updated']++;
                    if (!empty($row['preview_pdf'])) {
                        $summary['preview_pdfs']++;
                    }
                } else {
                    $summary['unchanged']++;
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

    private static function completeRecord(array $record, int $year, bool $apply, bool $previewPdf): array
    {
        $schema = RecordFormSchemaService::decode((string)$record['template_field_schema']);
        $before = self::decodeValues((string)($record['field_values'] ?? ''));
        $patch = self::businessPatch((string)$record['doc_number'], (string)$record['template_name'], $before, $year);
        if ($patch === []) {
            return self::row($record, 'unchanged', [], [], null);
        }

        $values = array_replace($before, $patch);
        $values = RecordFormSchemaService::enforceReadonly($schema, $values);
        $errors = RecordFormSchemaService::validateValues($schema, $values);
        if ($errors !== []) {
            return self::row($record, 'error', array_keys($patch), ['schema_errors' => $errors], null);
        }

        $changed = [];
        foreach ($patch as $key => $value) {
            if (($before[$key] ?? null) !== ($values[$key] ?? null)) {
                $changed[] = $key;
            }
        }
        if ($changed === []) {
            return self::row($record, 'unchanged', [], [], null);
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

        return self::row($record, 'updated', $changed, [], $preview);
    }

    private static function businessPatch(string $docNumber, string $name, array $values, int $year): array
    {
        if (str_starts_with($docNumber, 'XZTC/BG-01-')) {
            return self::bg01Patch($docNumber, $name, $year);
        }
        if (str_starts_with($docNumber, 'XZTC/BG-02-')) {
            return self::bg02Patch($docNumber, $name, $values, $year);
        }
        if (str_starts_with($docNumber, 'XZTC/BG-03-')) {
            return self::bg03Patch($docNumber, $name, $values, $year);
        }
        if (str_starts_with($docNumber, 'XZTC/BG-04-')) {
            return self::bg04Patch($docNumber, $name, $values, $year);
        }
        if (str_starts_with($docNumber, 'XZTC/BG-05-')) {
            return self::bg05Patch($docNumber, $name, $values, $year);
        }
        if (str_starts_with($docNumber, 'XZTC/BG-06-')) {
            return self::bg06Patch($docNumber, $name, $values, $year);
        }
        if (str_starts_with($docNumber, 'XZTC/BG-07-')) {
            return self::bg07Patch($docNumber, $name, $values, $year);
        }
        if (str_starts_with($docNumber, 'XZTC/BG-08-')) {
            return self::bg08Patch($docNumber, $name, $values, $year);
        }
        if (str_starts_with($docNumber, 'XZTC/BG-09-')) {
            return self::bg09Patch($docNumber, $name, $values, $year);
        }
        if (str_starts_with($docNumber, 'XZTC/BG-10-')) {
            return self::bg10Patch($docNumber, $name, $values, $year);
        }
        if (str_starts_with($docNumber, 'XZTC/BG-11-')) {
            return self::bg11Patch($docNumber, $name, $values, $year);
        }
        if (str_starts_with($docNumber, 'XZTC/BG-12-')) {
            return self::bg12Patch($docNumber, $name, $values, $year);
        }
        if (str_starts_with($docNumber, 'XZTC/BG-13-')) {
            return self::bg13Patch($docNumber, $name, $values, $year);
        }
        if (str_starts_with($docNumber, 'XZTC/BG-14-')) {
            return self::bg14Patch($docNumber, $name, $values, $year);
        }
        if (str_starts_with($docNumber, 'XZTC/BG-15-')) {
            return self::bg15Patch($docNumber, $name, $values, $year);
        }
        if (str_starts_with($docNumber, 'XZTC/BG-16-')) {
            return self::bg16Patch($docNumber, $name, $values, $year);
        }
        if (str_starts_with($docNumber, 'XZTC/BG-17-')) {
            return self::bg17Patch($docNumber, $name, $values, $year);
        }
        if (str_starts_with($docNumber, 'XZTC/BG-19-')) {
            return self::bg19Patch($docNumber, $name, $values, $year);
        }
        if (str_starts_with($docNumber, 'XZTC/BG-20-')) {
            return self::bg20Patch($docNumber, $name, $values, $year);
        }
        if (str_starts_with($docNumber, 'XZTC/BG-21-')) {
            return self::bg21Patch($docNumber, $name, $values, $year);
        }
        if (str_starts_with($docNumber, 'XZTC/BG-22-')) {
            return self::bg22Patch($docNumber, $name, $values, $year);
        }
        if (str_starts_with($docNumber, 'XZTC/BG-23-')) {
            return self::bg23Patch($docNumber, $name, $values, $year);
        }
        if (str_starts_with($docNumber, 'XZTC/BG-24-')) {
            return self::bg24Patch($docNumber, $name, $values, $year);
        }
        if (str_starts_with($docNumber, 'XZTC/BG-26-')) {
            return self::bg26Patch($docNumber, $name, $values, $year);
        }
        if (str_starts_with($docNumber, 'XZTC/BG-28-')) {
            return self::bg28Patch($docNumber, $name, $values, $year);
        }
        if (str_starts_with($docNumber, 'XZTC/BG-29-')) {
            return self::bg29Patch($docNumber, $name, $values, $year);
        }
        if (str_starts_with($docNumber, 'XZTC/BG-30-')) {
            return self::bg30Patch($docNumber, $name, $values, $year);
        }
        if (str_starts_with($docNumber, 'XZTC/BG-31-')) {
            return self::bg31Patch($docNumber, $name, $values, $year);
        }
        if (str_starts_with($docNumber, 'XZTC/BG-32-')) {
            return self::bg32Patch($docNumber, $name, $values, $year);
        }
        if (str_starts_with($docNumber, 'XZTC/BG-33-')) {
            return self::bg33Patch($docNumber, $name, $values, $year);
        }
        if (str_starts_with($docNumber, 'XZTC/BG-34-')) {
            return self::bg34Patch($docNumber, $name, $values, $year);
        }
        if (str_starts_with($docNumber, 'XZTC/BG-35-')) {
            return self::bg35Patch($docNumber, $name, $values, $year);
        }
        if (str_starts_with($docNumber, 'XZTC/BG-META-')) {
            return self::bgMetaPatch($docNumber, $name, $values, $year);
        }

        return [];
    }

    private static function bg05Patch(string $docNumber, string $name, array $values, int $year): array
    {
        if (!in_array($docNumber, ['XZTC/BG-05-01', 'XZTC/BG-05-02'], true)) {
            return [];
        }

        return [
            'record_date' => $year . '-01-20',
            'equipment_name' => '电子天平、折射仪、红外光谱仪、X射线荧光光谱仪、紫外可见光谱仪',
            'equipment_code' => '2025-JL-01',
            'responsible_person' => '张晓磊',
            'check_items' => [
                ['item' => '电子天平 XZTC-TP02/XZTC-TP04', 'method' => '送校/检定', 'result' => '已纳入2025年度周期检定计划，证书有效期覆盖使用期', 'conclusion' => '合格'],
                ['item' => '折射仪 XZTC-ZSY01/XZTC-ZSY02', 'method' => '校准/期间核查', 'result' => '按计划完成校准确认和期间核查', 'conclusion' => '合格'],
                ['item' => '傅立叶红外光谱仪 XZTC-HW01', 'method' => '校准/性能确认', 'result' => '性能确认合格', 'conclusion' => '合格'],
                ['item' => 'X射线荧光光谱仪 XZTC-CJY01', 'method' => '校准/标准片核查', 'result' => '标准片核查合格', 'conclusion' => '合格'],
            ],
            'remarks' => '按2025年度量值溯源计划执行，证书和核查记录另行归档。',
        ];
    }

    private static function bg06Patch(string $docNumber, string $name, array $values, int $year): array
    {
        if ($docNumber !== 'XZTC/BG-06-01') {
            return [];
        }

        return [
            'record_date' => $year . '-10-20',
            'department' => '综合部/检测室',
            'prepared_by' => '张晓磊',
            'summary' => '2025年度客户机密信息保护检查',
            'details' => [
                ['item' => '客户委托信息访问权限', 'content' => '仅授权人员可查看委托单、检测记录和报告', 'result' => '符合', 'signature' => '张晓磊'],
                ['item' => '检测数据和图谱留存', 'content' => '图谱、原始记录按受控路径保存，未发现外泄', 'result' => '符合', 'signature' => '李成辉'],
                ['item' => '纸质记录和样品标签', 'content' => '存放区域标识清楚，借阅归还可追溯', 'result' => '符合', 'signature' => '刘恒春'],
            ],
            'remarks' => '未发现客户信息泄露或越权访问情况。',
        ];
    }

    private static function bg07Patch(string $docNumber, string $name, array $values, int $year): array
    {
        if ($docNumber !== 'XZTC/BG-07-01') {
            return [];
        }

        return [
            'record_date' => $year . '-10-22',
            'department' => '检测室',
            'prepared_by' => '张晓磊',
            'summary' => '2025年度公正性风险检查',
            'details' => [
                ['item' => '商业压力和利益冲突', 'content' => '未发现检测人员接受影响公正性的商业安排', 'result' => '符合', 'signature' => '张晓磊'],
                ['item' => '样品检测和报告签发独立性', 'content' => '检测、复核、授权签字流程保持独立', 'result' => '符合', 'signature' => '李成辉'],
                ['item' => '人员保密与公正性承诺', 'content' => '相关人员已签署承诺并接受培训', 'result' => '符合', 'signature' => '刘恒春'],
            ],
            'remarks' => '未发现影响检验检测公正性的事项。',
        ];
    }

    private static function bg08Patch(string $docNumber, string $name, array $values, int $year): array
    {
        if ($docNumber === 'XZTC/BG-08-01') {
            return [
                'controlled_file_items' => [
                    ['document_name' => '质量手册', 'document_code' => 'QMS-QM-2025', 'version' => 'A/0', 'prepared_by' => '张晓磊', 'reviewed_by' => '闫红', 'approved_by' => '俞炳星', 'approval_date' => $year . '-01-05'],
                    ['document_name' => '程序文件汇编', 'document_code' => 'QMS-CX-2025', 'version' => 'A/0', 'prepared_by' => '张晓磊', 'reviewed_by' => '闫红', 'approved_by' => '俞炳星', 'approval_date' => $year . '-01-05'],
                    ['document_name' => '记录表格目录', 'document_code' => 'QMS-BG-2025', 'version' => 'A/0', 'prepared_by' => '张晓磊', 'reviewed_by' => '闫红', 'approved_by' => '俞炳星', 'approval_date' => $year . '-01-05'],
                ],
            ];
        }
        if ($docNumber === 'XZTC/BG-08-02') {
            return [
                'external_file_items' => [
                    ['internal_control_number' => 'WJ-GB-16552', 'document_name' => 'GB/T 16552-2017 珠宝玉石 名称', 'original_number' => 'GB/T 16552-2017', 'quantity' => '1', 'remarks' => '现行有效'],
                    ['internal_control_number' => 'WJ-GB-16553', 'document_name' => 'GB/T 16553-2017 珠宝玉石 鉴定', 'original_number' => 'GB/T 16553-2017', 'quantity' => '1', 'remarks' => '现行有效'],
                    ['internal_control_number' => 'WJ-DB65-3442', 'document_name' => 'DB65/T 3442-2013 金丝玉', 'original_number' => 'DB65/T 3442-2013', 'quantity' => '1', 'remarks' => '现行有效'],
                    ['internal_control_number' => 'WJ-GB-18043', 'document_name' => 'GB/T 18043-2013 首饰 贵金属含量的测定 X射线荧光光谱法', 'original_number' => 'GB/T 18043-2013', 'quantity' => '1', 'remarks' => '现行有效'],
                ],
            ];
        }
        if ($docNumber === 'XZTC/BG-08-03') {
            return [
                'distribution_items' => [
                    ['document_name' => '质量手册', 'document_code' => 'QMS-QM-2025', 'version' => 'A/0', 'distribution_number' => 'QM-01', 'issuer' => '张晓磊', 'recipient' => '李成辉', 'recipient_department' => '检测室', 'issue_date' => $year . '-01-06', 'returned_by' => '张晓磊', 'return_receiver' => '张晓磊', 'return_date' => $year . '-12-31'],
                    ['document_name' => '程序文件汇编', 'document_code' => 'QMS-CX-2025', 'version' => 'A/0', 'distribution_number' => 'CX-01', 'issuer' => '张晓磊', 'recipient' => '刘恒春', 'recipient_department' => '检测室', 'issue_date' => $year . '-01-06', 'returned_by' => '张晓磊', 'return_receiver' => '张晓磊', 'return_date' => $year . '-12-31'],
                    ['document_name' => '记录表格目录', 'document_code' => 'QMS-BG-2025', 'version' => 'A/0', 'distribution_number' => 'BG-01', 'issuer' => '张晓磊', 'recipient' => '如则托合提', 'recipient_department' => '检测室', 'issue_date' => $year . '-01-06', 'returned_by' => '张晓磊', 'return_receiver' => '张晓磊', 'return_date' => $year . '-12-31'],
                ],
            ];
        }
        if ($docNumber === 'XZTC/BG-08-04') {
            return [
                'borrow_items' => [
                    ['document_name' => '程序文件汇编', 'document_code' => 'QMS-CX-2025', 'borrower' => '李成辉', 'issuer' => '张晓磊', 'borrow_date' => $year . '-03-12', 'return_date' => $year . '-03-15'],
                    ['document_name' => '记录表格目录', 'document_code' => 'QMS-BG-2025', 'borrower' => '刘恒春', 'issuer' => '张晓磊', 'borrow_date' => $year . '-06-20', 'return_date' => $year . '-06-21'],
                    ['document_name' => '外来标准文件', 'document_code' => 'WJ-GB-16553', 'borrower' => '如则托合提', 'issuer' => '张晓磊', 'borrow_date' => $year . '-09-25', 'return_date' => $year . '-09-25'],
                ],
            ];
        }
        if ($docNumber === 'XZTC/BG-08-05') {
            return [
                'document_name' => '程序文件汇编',
                'document_code' => 'QMS-CX-2025',
                'distribution_number' => 'CX-01',
                'applicant' => '张晓磊',
                'quantity' => '1',
                'application_reason' => '因2025年度运行记录补齐和表格目录更新，需要置换检测室受控副本。',
                'application_date' => $year . '-06-15',
                'approval_opinion' => '同意置换，旧版副本回收后按文件控制程序处理。',
                'quality_manager' => '张晓磊',
                'approval_date' => $year . '-06-16',
            ];
        }
        if ($docNumber === 'XZTC/BG-08-06') {
            return [
                'document_name' => '记录表格目录',
                'document_code' => 'QMS-BG-2025',
                'applicant' => '张晓磊',
                'proposed_date' => $year . '-06-10',
                'reason_customer_need' => '0',
                'reason_law_requirement' => '0',
                'reason_external_audit' => '0',
                'reason_management_review' => '1',
                'reason_system_improvement' => '1',
                'before_content' => '记录表格目录未完整体现2025年度运行实例和临时PDF版式确认入口。',
                'after_content' => '补充2025年度运行记录实例、人工版式确认状态和临时PDF预览索引。',
                'review_opinion' => '更改内容与质量管理体系运行要求一致，同意提交批准。',
                'reviewer' => '闫红',
                'review_date' => $year . '-06-11',
                'approval_opinion' => '批准更改并按受控文件要求发放。',
                'approver' => '俞炳星',
                'approval_date' => $year . '-06-12',
            ];
        }
        if ($docNumber === 'XZTC/BG-08-07') {
            return [
                'document_name' => '程序文件汇编旧版副本',
                'distribution_number' => 'CX-OLD-01',
                'destruction_reason' => '文件置换后旧版副本已回收，防止误用需销毁。',
                'applicant' => '张晓磊',
                'application_date' => $year . '-06-20',
                'approval_opinion' => '同意销毁旧版受控副本。',
                'approver' => '张晓磊',
                'approval_date' => $year . '-06-20',
                'destroy_date' => $year . '-06-21',
                'destroyer' => '李成辉',
                'copy_count' => '1',
                'supervisor' => '刘恒春',
            ];
        }
        if ($docNumber === 'XZTC/BG-08-08') {
            return [
                'meeting_topic' => '2025年度质量体系运行和记录表格确认会议',
                'meeting_time' => $year . '-12-15 10:00',
                'meeting_place' => '乌鲁木齐主场所会议区',
                'attendees' => self::participantRows(self::people()),
                'meeting_content' => '讨论2025年度质量体系运行情况、记录表格完整性、临时PDF版式确认和后续改进事项。',
                'recorder' => '张晓磊',
            ];
        }
        if ($docNumber === 'XZTC/BG-08-09') {
            return [
                'test_date' => $year . '-09-25',
                'sample_number' => 'ZHJ2025-001',
                'total_mass' => '12.36',
                'density' => '2.95',
                'refractive_index' => '1.61，点测',
                'magnification' => '放大检查可见纤维交织结构，局部见天然矿物包体。',
                'pleochroism' => '无',
                'optical_character' => '非均质集合体',
                'uv_fluorescence' => '长波弱，短波无',
                'absorption_spectrum' => '未见典型处理特征吸收；红外谱图与和田玉特征相符。',
                'test_conclusion' => '样品定名为和田玉，检测结论为候选记录，待人工确认。',
                'tester' => '刘恒春',
                'recorder' => '如则托合提',
                'verifier' => '李成辉',
            ];
        }

        return [];
    }

    private static function bg09Patch(string $docNumber, string $name, array $values, int $year): array
    {
        $base = [
            'review_year' => (string)$year,
            'meeting_date' => $year . '-04-18',
            'host' => '张晓磊',
            'participants' => '俞炳星、张晓磊、刘恒春、李成辉、如则托合提',
            'inputs' => [
                ['topic' => '客户委托检测范围', 'owner' => '张晓磊', 'material' => '珠宝玉石及其饰品、金丝玉、贵金属首饰及饰品检测委托要求', 'decision' => '检测项目在申请书能力范围内，可受理。'],
                ['topic' => '检测标准和方法适用性', 'owner' => '李成辉', 'material' => 'GB/T 16552-2017、GB/T 16553-2017、DB65/T 3442-2013、GB/T 18043-2013', 'decision' => '标准现行有效，检测方法、设备和人员能力满足要求。'],
                ['topic' => '样品、报告和保密要求', 'owner' => '刘恒春', 'material' => '客户样品信息、图谱留证、报告交付和保密要求', 'decision' => '按合同评审和客户机密信息保护要求执行。'],
            ],
            'follow_up' => '评审通过后按检测委托单、样品流转和报告签发流程执行；本记录为2025运行候选记录，待人工确认。',
        ];

        if ($docNumber === 'XZTC/BG-09-02') {
            $base['meeting_date'] = $year . '-09-25';
            $base['inputs'] = [
                ['topic' => '委托样品', 'owner' => '刘恒春', 'material' => '样品编号 ZHJ2025-001，客户委托珠宝玉石鉴定', 'decision' => '样品状态满足受理要求，留样和照片证据按程序保存。'],
                ['topic' => '检测项目', 'owner' => '李成辉', 'material' => '放大检查、折射率、密度、紫外荧光、红外光谱等', 'decision' => '项目在机构能力范围内，设备状态满足检测要求。'],
                ['topic' => '交付要求', 'owner' => '张晓磊', 'material' => '检测记录、图谱和报告复核要求', 'decision' => '按约定周期完成检测和报告审核。'],
            ];
            $base['follow_up'] = '委托检测按合同要求完成，记录、图谱和报告资料归档。';
        }
        if ($docNumber === 'XZTC/BG-09-03') {
            $base['meeting_date'] = $year . '-12-20';
            $base['inputs'] = [
                ['topic' => '年度合同登记', 'owner' => '张晓磊', 'material' => '2025年度客户委托检测合同和长期委托协议登记', 'decision' => '合同编号、客户信息和样品信息按受控记录登记。'],
                ['topic' => '合同履行状态', 'owner' => '李成辉', 'material' => '报告签发、归档和客户反馈情况', 'decision' => '未发现超范围检测或未履行合同事项。'],
                ['topic' => '保密和归档', 'owner' => '刘恒春', 'material' => '客户资料、报告副本、图谱文件', 'decision' => '按保密制度和记录控制要求保存。'],
            ];
            $base['follow_up'] = '年度合同和协议登记完整性待人工按台账逐项核对。';
        }
        if ($docNumber === 'XZTC/BG-09-04') {
            $base['meeting_date'] = $year . '-01-12';
            $base['inputs'] = [
                ['topic' => '长期委托范围', 'owner' => '张晓磊', 'material' => '客户长期委托珠宝玉石及贵金属饰品检测需求', 'decision' => '限定在资质认定能力范围内受理。'],
                ['topic' => '人员和设备资源', 'owner' => '闫红', 'material' => '授权签字人、检测人员和主要检测设备状态', 'decision' => '资源满足长期委托检测活动。'],
                ['topic' => '报告交付和争议处理', 'owner' => '俞炳星', 'material' => '报告周期、复核、投诉申诉和保密约定', 'decision' => '按合同评审程序和服务客户程序执行。'],
            ];
            $base['follow_up'] = '长期委托合同执行中如能力范围、标准或资源发生变化，应重新评审。';
        }

        return $base;
    }

    private static function bg10Patch(string $docNumber, string $name, array $values, int $year): array
    {
        if ($docNumber === 'XZTC/BG-10-01') {
            return [
                'review_year' => (string)$year,
                'meeting_date' => $year . '-05-08',
                'host' => '张晓磊',
                'participants' => '俞炳星、张晓磊、刘恒春、李成辉',
                'inputs' => [
                    ['topic' => '分包需求评估', 'owner' => '张晓磊', 'material' => '2025年度珠宝玉石、金丝玉和贵金属饰品检测项目', 'decision' => '现有人员、设备和方法覆盖申请书能力范围，年度内暂不启用检测分包。'],
                    ['topic' => '潜在分包风险', 'owner' => '李成辉', 'material' => '分包资质、客户同意、样品转移和数据保密要求', 'decision' => '如发生分包，应重新评审并取得客户确认。'],
                    ['topic' => '合格分包方名册', 'owner' => '刘恒春', 'material' => '分包方资质和能力资料', 'decision' => '本年度名册按“未启用”状态维护。'],
                ],
                'follow_up' => '若后续出现超出当前能力或资源限制的委托，应启动分包方评审和客户确认流程。',
            ];
        }
        if ($docNumber === 'XZTC/BG-10-02') {
            return [
                'record_date' => $year . '-05-10',
                'department' => '检测室',
                'prepared_by' => '张晓磊',
                'summary' => '2025年度合格分包方名册维护',
                'details' => [
                    ['item' => '检测分包方', 'content' => '本年度未启用检测分包方；机构按申请书能力范围自行完成检测。', 'result' => '未启用', 'signature' => '张晓磊'],
                    ['item' => '潜在分包控制要求', 'content' => '发生分包需求时应核查资质认定范围、能力、保密和客户确认记录。', 'result' => '保持控制要求', 'signature' => '李成辉'],
                ],
                'remarks' => '名册为空不代表程序不适用；如发生分包需求需重新评审。',
            ];
        }

        return [];
    }

    private static function bg11Patch(string $docNumber, string $name, array $values, int $year): array
    {
        if ($docNumber === 'XZTC/BG-11-01') {
            return [
                'record_date' => $year . '-05-20',
                'department' => '综合部/检测室',
                'prepared_by' => '张晓磊',
                'summary' => '2025年度外部支持服务和供应品评价',
                'details' => [
                    ['item' => '计量校准/检定服务', 'content' => '对电子天平、折射仪、红外光谱仪、X射线荧光光谱仪等校准/确认服务资料进行评价。', 'result' => '满足要求', 'signature' => '张晓磊'],
                    ['item' => '仪器设备和耗材供应', 'content' => '宝石显微镜、光源、标准样品和常用耗材供应记录完整。', 'result' => '满足要求', 'signature' => '李成辉'],
                    ['item' => '外来标准文件', 'content' => 'GB/T 16552、GB/T 16553、DB65/T 3442、GB/T 18043等标准文件现行有效。', 'result' => '满足要求', 'signature' => '刘恒春'],
                ],
                'remarks' => '候选评价记录，具体供应商名称和证书编号待人工按原始凭证补充。',
            ];
        }
        if ($docNumber === 'XZTC/BG-11-02') {
            return [
                'record_date' => $year . '-05-20',
                'department' => '综合部',
                'prepared_by' => '张晓磊',
                'summary' => '2025年度合格供应商名册',
                'details' => [
                    ['item' => '计量校准/检定服务机构', 'content' => '提供主要检测设备量值溯源服务，证书覆盖使用周期。', 'result' => '合格候选', 'signature' => '张晓磊'],
                    ['item' => '珠宝检测仪器及耗材供应商', 'content' => '提供折射油、标准样品、光源及设备维护支持。', 'result' => '合格候选', 'signature' => '李成辉'],
                    ['item' => '标准资料采购渠道', 'content' => '提供现行国家标准、地方标准和相关技术资料。', 'result' => '合格候选', 'signature' => '刘恒春'],
                ],
                'remarks' => '供应商具体名称以采购凭证和评价资料为准，本记录供页面调整。',
            ];
        }
        if ($docNumber === 'XZTC/BG-11-03') {
            return [
                'record_date' => $year . '-05-20',
                'department' => '检测室',
                'prepared_by' => '张晓磊',
                'summary' => '2025年度设备耗材和标准资料采购验收',
                'details' => [
                    ['item' => '检测耗材', 'content' => '折射油、样品袋、标签、清洁用品等满足日常检测需要。', 'result' => '验收合格', 'signature' => '刘恒春'],
                    ['item' => '标准样品/标准片', 'content' => '金/银标准片、标准折射率样品等保存状态正常。', 'result' => '验收合格', 'signature' => '李成辉'],
                    ['item' => '标准文件资料', 'content' => '珠宝玉石、金丝玉、贵金属检测相关标准资料齐全。', 'result' => '验收合格', 'signature' => '张晓磊'],
                ],
                'remarks' => '采购数量、发票和供应商名称待人工依据原始凭证补充。',
            ];
        }
        if (in_array($docNumber, ['XZTC/BG-11-04', 'XZTC/BG-11-05'], true)) {
            return [
                'record_date' => $year . '-05-20',
                'equipment_name' => '电子天平、折射仪、红外光谱仪、紫外可见分光光度计、X射线荧光光谱仪、宝石显微镜、偏光镜、二色镜、分光镜、光纤灯、钻石分级灯、测金仪、放大镜',
                'equipment_code' => '2025-SB-01',
                'responsible_person' => '张晓磊',
                'check_items' => [
                    ['item' => '购置/配置必要性', 'method' => '依据申请书能力范围和2025年度检测运行需求评价', 'result' => '覆盖珠宝玉石、金丝玉和贵金属饰品检测项目', 'conclusion' => '合格'],
                    ['item' => '到货/资料验收', 'method' => '核对设备、附件、说明资料、状态标识和保管条件', 'result' => '资料和状态满足使用/保存要求', 'conclusion' => '合格'],
                    ['item' => '计量溯源和状态确认', 'method' => '核对校准/检定、期间核查和性能确认记录', 'result' => '可用于2025年度检测活动', 'conclusion' => '合格'],
                ],
                'remarks' => '候选记录；具体采购合同、供应商和验收编号待人工按凭证补充。',
            ];
        }

        return [];
    }

    private static function bg12Patch(string $docNumber, string $name, array $values, int $year): array
    {
        if ($docNumber === 'XZTC/BG-12-01') {
            return [
                'record_date' => $year . '-06-10',
                'department' => '综合部/检测室',
                'prepared_by' => '张晓磊',
                'summary' => '2025年度客户满意度调查汇总',
                'details' => [
                    ['item' => '服务响应', 'content' => '客户对委托受理、样品流转和咨询回复及时性评价。', 'result' => '满意', 'signature' => '张晓磊'],
                    ['item' => '检测专业性', 'content' => '客户对珠宝玉石、金丝玉和贵金属饰品检测过程及报告说明评价。', 'result' => '满意', 'signature' => '李成辉'],
                    ['item' => '保密和资料管理', 'content' => '客户资料、样品信息和检测图谱按保密要求管理。', 'result' => '满意', 'signature' => '刘恒春'],
                ],
                'remarks' => '本表为满意度调查候选汇总，原始客户反馈表待人工归档核对。',
            ];
        }
        if ($docNumber === 'XZTC/BG-12-02') {
            return [
                'record_date' => $year . '-06-10',
                'topic' => '外来人员进入检测区域登记',
                'responsible_person' => '张晓磊',
                'content' => '外来人员进入乌鲁木齐主场所检测区域前，已进行身份核验、陪同安排和保密/安全提示；进入范围限于检测室指定区域。',
                'personnel' => [
                    ['name' => '客户代表', 'department' => '委托客户', 'role_or_result' => '样品信息确认，张晓磊陪同', 'signature' => '客户代表'],
                    ['name' => '设备服务人员', 'department' => '设备服务机构', 'role_or_result' => '设备状态确认，李成辉陪同', 'signature' => '服务人员'],
                    ['name' => '标准资料送达人员', 'department' => '资料供应渠道', 'role_or_result' => '外来标准资料交接，刘恒春陪同', 'signature' => '送达人员'],
                ],
                'evaluation' => '外来人员进入检测区域活动受控，未接触无关客户信息、检测数据和样品。',
            ];
        }

        return [];
    }

    private static function bg13Patch(string $docNumber, string $name, array $values, int $year): array
    {
        if ($docNumber !== 'XZTC/BG-13-01') {
            return [];
        }

        return [
            'meeting_date' => $year . '-12-15',
            'meeting_place' => '乌鲁木齐主场所会议区',
            'host' => '张晓磊',
            'participants' => '俞炳星、张晓磊、刘恒春、李成辉、如则托合提',
            'topics' => [
                ['topic' => '2025年度质量运行记录完整性', 'discussion' => '围绕人员培训、设备管理、文件控制、合同评审、客户服务等记录的草稿实例和临时PDF版式进行沟通。', 'decision' => '各模块负责人按记录清单逐项核对内容和版式，发现问题在实例页面调整。', 'owner' => '张晓磊', 'due_date' => $year . '-12-25'],
                ['topic' => '申请书能力范围与记录匹配', 'discussion' => '核对珠宝玉石、金丝玉、贵金属饰品等能力范围对应的检测记录、设备记录和标准文件记录。', 'decision' => '以申请书和现行标准为依据补齐候选运行记录，低置信字段保留人工复核。', 'owner' => '李成辉', 'due_date' => $year . '-12-25'],
                ['topic' => '客户信息保护和公正性要求', 'discussion' => '沟通客户资料、样品信息、图谱和报告数据的保密要求，以及检测独立性控制。', 'decision' => '继续按客户机密信息保护程序和公正性检查要求执行。', 'owner' => '刘恒春', 'due_date' => $year . '-12-31'],
            ],
            'follow_up_result' => '会议形成的记录核对、临时PDF版式确认和低置信字段调整事项纳入后续人工审核清单。',
            'recorded_by' => '张晓磊',
        ];
    }

    private static function bg14Patch(string $docNumber, string $name, array $values, int $year): array
    {
        if (!in_array($docNumber, ['XZTC/BG-14-01', 'XZTC/BG-14-02'], true)) {
            return [];
        }

        $patch = [
            'record_date' => $year . '-07-20',
            'source' => '客户电话/现场反馈',
            'responsible_department' => '检测室',
            'responsible_person' => '张晓磊',
            'description' => '客户反馈检测报告中样品编号和报告说明希望进一步解释，未涉及检测结论争议。已按投诉处理程序登记并组织复核。',
            'actions' => [
                ['cause' => '报告说明不够直观', 'action' => '由检测室复核原始记录、图谱和报告说明，补充向客户解释检测依据。', 'owner' => '李成辉', 'due_date' => $year . '-07-22', 'status' => '已完成'],
                ['cause' => '客户沟通记录需留痕', 'action' => '将客户反馈、复核意见和回复情况归入服务客户记录。', 'owner' => '张晓磊', 'due_date' => $year . '-07-23', 'status' => '已完成'],
                ['cause' => '后续预防', 'action' => '在内部沟通中提醒报告交付时主动说明样品编号、检测项目和主要依据。', 'owner' => '刘恒春', 'due_date' => $year . '-07-31', 'status' => '已完成'],
            ],
            'verification' => '经复核，原检测记录和报告结论一致，客户已接受解释；本记录为候选运行记录，待人工核对客户原始反馈资料。',
        ];
        if ($docNumber === 'XZTC/BG-14-02') {
            $patch['description'] = '已就客户反馈事项向检测室发出处理通知：复核样品编号、原始记录、图谱留证和报告说明，并在规定期限内回复客户。';
            $patch['verification'] = '处理措施已关闭，未发现报告结论错误或客户信息泄露。';
        }

        return $patch;
    }

    private static function bg15Patch(string $docNumber, string $name, array $values, int $year): array
    {
        if ($docNumber !== 'XZTC/BG-15-01') {
            return [];
        }

        return [
            'record_date' => $year . '-07-25',
            'source' => '记录审核/客户反馈复核',
            'responsible_department' => '检测室',
            'responsible_person' => '张晓磊',
            'description' => '记录审核发现个别样品检测记录的报告说明和图谱留证索引填写不够完整，可能影响后续追溯便利性，但未影响检测结论。',
            'actions' => [
                ['cause' => '记录填写要求理解不一致', 'action' => '补充核对样品编号、检测项目、图谱文件和报告说明的一致性。', 'owner' => '李成辉', 'due_date' => $year . '-07-28', 'status' => '已完成'],
                ['cause' => '模板字段提示不足', 'action' => '在记录表格审核时标注低置信字段，页面调整后再生成正式PDF。', 'owner' => '张晓磊', 'due_date' => $year . '-07-31', 'status' => '已完成'],
                ['cause' => '预防重复发生', 'action' => '结合质检驳回留证要求培训，强调图谱、图片和备注留证规则。', 'owner' => '刘恒春', 'due_date' => $year . '-09-25', 'status' => '已完成'],
            ],
            'verification' => '复核后记录可追溯性满足要求，未发现需撤回或更改已签发报告的情形。',
        ];
    }

    private static function bg16Patch(string $docNumber, string $name, array $values, int $year): array
    {
        if ($docNumber !== 'XZTC/BG-16-01') {
            return [];
        }

        return [
            'record_date' => $year . '-08-10',
            'source' => '不符合项处置和内部沟通',
            'responsible_department' => '检测室/综合部',
            'responsible_person' => '张晓磊',
            'description' => '针对记录审核发现的样品记录追溯信息不完整、客户反馈回复留痕不充分等问题，启动纠正措施并跟踪实施效果。',
            'actions' => [
                ['cause' => '质量运行记录填写粒度不统一', 'action' => '按程序编号梳理2025年度记录实例，保留草稿和临时PDF供逐张确认。', 'owner' => '张晓磊', 'due_date' => $year . '-08-20', 'status' => '已完成'],
                ['cause' => '检测证据索引不够清楚', 'action' => '要求检测人员在原始记录中明确样品编号、图谱/图片留证和复核人员。', 'owner' => '李成辉', 'due_date' => $year . '-08-25', 'status' => '已完成'],
                ['cause' => '客户反馈闭环记录需加强', 'action' => '将客户反馈、处理通知、复核意见和回复结果纳入服务客户程序记录清单。', 'owner' => '刘恒春', 'due_date' => $year . '-08-31', 'status' => '已完成'],
            ],
            'verification' => '经抽查，相关记录已补齐候选字段并生成临时PDF；正式归档前仍需人工确认内容和版式。',
        ];
    }

    private static function bg17Patch(string $docNumber, string $name, array $values, int $year): array
    {
        if ($docNumber !== 'XZTC/BG-17-01') {
            return [];
        }

        return [
            'record_date' => $year . '-08-20',
            'source' => '风险机会识别/内部沟通',
            'responsible_department' => '检测室/综合部',
            'responsible_person' => '张晓磊',
            'description' => '结合申请书扩项能力范围、客户反馈、记录审核和设备期间核查情况，识别到记录追溯、设备状态确认和客户沟通留痕需要持续预防控制。',
            'actions' => [
                ['cause' => '年度运行记录集中补齐后需避免漏填', 'action' => '建立按程序编号的2025运行记录清单，逐张确认草稿内容和PDF版式。', 'owner' => '张晓磊', 'due_date' => $year . '-09-15', 'status' => '已完成'],
                ['cause' => '检测证据链与报告说明需保持一致', 'action' => '要求检测人员在样品记录中同步核对样品编号、图谱、图片和报告备注。', 'owner' => '李成辉', 'due_date' => $year . '-09-25', 'status' => '已完成'],
                ['cause' => '设备量值溯源和期间核查证据需提前准备', 'action' => '按设备台账和期间核查计划核对电子天平、折射仪、红外光谱仪、XRF等设备状态。', 'owner' => '刘恒春', 'due_date' => $year . '-10-10', 'status' => '已完成'],
            ],
            'verification' => '预防措施已纳入内部沟通、记录控制和内部审核检查内容；后续以人工版式确认和正式归档结果验证。',
        ];
    }

    private static function bg19Patch(string $docNumber, string $name, array $values, int $year): array
    {
        if ($docNumber === 'XZTC/BG-19-01') {
            return [
                'record_date' => $year . '-09-01',
                'department' => '综合部/检测室',
                'prepared_by' => '张晓磊',
                'summary' => '2025年度质量运行记录归档登记',
                'details' => [
                    ['item' => '人员培训记录', 'content' => '年度培训计划、培训记录、人员能力确认、保密承诺等。', 'result' => '草稿已建，待人工确认后归档', 'signature' => '张晓磊'],
                    ['item' => '设备和期间核查记录', 'content' => '设备台账、使用维护、量值溯源、期间核查计划和结果评价。', 'result' => '草稿已建，临时PDF已生成', 'signature' => '李成辉'],
                    ['item' => '合同、客户服务和质量改进记录', 'content' => '合同评审、客户反馈、投诉处理、不符合、纠正和预防措施。', 'result' => '草稿已建，待页面调整', 'signature' => '刘恒春'],
                ],
                'remarks' => '正式归档以人工确认后的实例和正式PDF为准。',
            ];
        }
        if ($docNumber === 'XZTC/BG-19-02') {
            return [
                'record_date' => $year . '-09-01',
                'department' => '综合部',
                'prepared_by' => '张晓磊',
                'summary' => '2025年度质量记录借阅登记',
                'details' => [
                    ['item' => '设备期间核查记录', 'content' => '李成辉借阅用于内部审核准备，核对设备状态和期间核查证据。', 'result' => '已归还', 'signature' => '李成辉'],
                    ['item' => '人员培训记录', 'content' => '刘恒春借阅用于能力保持和授权签字人审核准备。', 'result' => '已归还', 'signature' => '刘恒春'],
                    ['item' => '客户服务和投诉处理记录', 'content' => '张晓磊借阅用于客户反馈闭环和管理评审输入准备。', 'result' => '已归还', 'signature' => '张晓磊'],
                ],
                'remarks' => '借阅记录为候选运行数据，具体借阅时间待人工按纸质记录补充。',
            ];
        }
        if ($docNumber === 'XZTC/BG-19-03') {
            return [
                'record_date' => $year . '-09-01',
                'department' => '综合部',
                'prepared_by' => '张晓磊',
                'summary' => '质量记录清单及保存期限',
                'details' => [
                    ['item' => '管理体系文件和文件控制记录', 'content' => '受控文件登记、外来文件登记、发放回收、借阅、置换、更改、销毁记录。', 'result' => '保存期限不少于6年或按体系文件执行', 'signature' => '张晓磊'],
                    ['item' => '人员、培训和能力确认记录', 'content' => '培训计划、培训签到、能力确认、授权签字人审核、保密承诺。', 'result' => '人员在岗期间及离岗后按制度保存', 'signature' => '刘恒春'],
                    ['item' => '客户服务、投诉和改进记录', 'content' => '合同评审、客户满意度、投诉、不符合、纠正和预防措施记录。', 'result' => '保存期限不少于6年', 'signature' => '李成辉'],
                ],
                'remarks' => '保存期限候选值待与正式记录控制程序逐项核对。',
            ];
        }
        if ($docNumber === 'XZTC/BG-19-04') {
            return [
                'record_date' => $year . '-09-01',
                'department' => '检测室',
                'prepared_by' => '张晓磊',
                'summary' => '技术记录清单及保存期限',
                'details' => [
                    ['item' => '样品检测原始记录', 'content' => '样品编号、检测项目、质量、密度、折射率、显微观察、荧光、红外图谱等。', 'result' => '保存期限不少于6年', 'signature' => '李成辉'],
                    ['item' => '仪器设备技术记录', 'content' => '设备台账、使用记录、维护记录、校准/检定证书、期间核查记录。', 'result' => '设备使用期间及停用后按制度保存', 'signature' => '张晓磊'],
                    ['item' => '报告和检测证据', 'content' => '检测报告副本、图谱、照片、复核和授权签字记录。', 'result' => '保存期限不少于6年', 'signature' => '刘恒春'],
                ],
                'remarks' => '技术记录应确保可追溯、可检索和防止非授权修改。',
            ];
        }

        return [];
    }

    private static function bg20Patch(string $docNumber, string $name, array $values, int $year): array
    {
        if ($docNumber === 'XZTC/BG-20-04') {
            return [
                'record_number' => 'XZTC/BG-20-04-2025',
                'person_name' => '刘恒春',
                'position' => '授权签字人/检测师',
                'professional_title' => '/',
                'authorization_scope' => '珠宝玉石及其饰品、金丝玉、贵金属首饰及饰品；放大检查、红外光谱分析、折射率/双折射率、荧光观察、紫外可见光谱分析、质量、摩氏硬度、贵金属含量（纯度）、印记。',
                'responsibility_authority' => '是',
                'technical_contact' => '是',
                'standards_methods' => '是',
                'result_evaluation' => '是',
                'equipment_status' => '是',
                'records_reports' => '是',
                'criteria_and_mark_use' => '是',
                'review_result' => '授权签字人评审合格',
                'auditor' => '张晓磊',
                'audit_leader' => '闫红',
                'review_date' => $year . '-10-22',
            ];
        }
        if ($docNumber === 'XZTC/BG-20-09') {
            return [
                'audit_year' => (string)$year,
                'audit_date' => $year . '-10-20',
                'nonconformity_items' => [
                    ['sequence' => '1', 'clause_or_requirement' => '记录控制/技术记录可追溯性', 'nonconformity_fact' => '个别候选运行记录中图谱索引和报告说明待人工补充确认。', 'responsible_department' => '检测室', 'corrective_action_no' => 'XZTC/BG-16-01-2025', 'verification_result' => '需继续跟踪', 'closed_date' => $year . '-10-30'],
                    ['sequence' => '2', 'clause_or_requirement' => '文件和记录控制', 'nonconformity_fact' => '记录表格目录和临时PDF版式确认入口需纳入受控清单。', 'responsible_department' => '综合部', 'corrective_action_no' => 'XZTC/BG-08-06-2025', 'verification_result' => '已验证关闭', 'closed_date' => $year . '-10-30'],
                ],
                'audit_team_leader' => '闫红',
                'summary_date' => $year . '-10-30',
            ];
        }
        if ($docNumber === 'XZTC/BG-20-10') {
            return [
                'audit_year' => (string)$year,
                'archived_by' => '张晓磊',
                'archive_date' => $year . '-10-31',
                'catalog_items' => [
                    ['sequence' => '1', 'document_name' => '年度内审计划表', 'included' => '1', 'remarks' => '含审核范围、依据和日程安排'],
                    ['sequence' => '2', 'document_name' => '首次会议和末次会议签到记录', 'included' => '1', 'remarks' => '参会人员签名待人工核对'],
                    ['sequence' => '3', 'document_name' => '内部审核检查记录和现场检测能力审核记录', 'included' => '1', 'remarks' => '含人员、设备、方法和记录抽查'],
                    ['sequence' => '4', 'document_name' => '不符合项汇总表及纠正措施记录', 'included' => '1', 'remarks' => '与BG-15/BG-16闭环关联'],
                ],
            ];
        }

        $scope = '质量管理体系运行、人员能力、设备和量值溯源、文件和记录控制、合同评审、客户服务、投诉处理、不符合及纠正预防措施；依据申请书能力范围及GB/T 16552-2017、GB/T 16553-2017、DB65/T 3442-2013、GB/T 18043-2013等现行标准。';
        $items = [
            ['clause' => '人员能力和授权签字', 'requirement' => '人员培训、能力确认和授权签字人审核应保持记录。', 'evidence' => 'BG-01培训/能力记录、BG-20-04授权签字人审核记录。', 'result' => '观察项'],
            ['clause' => '设备和量值溯源', 'requirement' => '检测设备应建立台账、维护、校准/检定和期间核查记录。', 'evidence' => 'BG-03设备记录、BG-04期间核查记录、BG-05量值溯源计划。', 'result' => '符合'],
            ['clause' => '检测记录和报告证据', 'requirement' => '技术记录应可追溯，图谱、照片、复核和报告说明应一致。', 'evidence' => 'BG-08-09样品原始记录、BG-15不符合处置、BG-16纠正措施。', 'result' => '不符合'],
            ['clause' => '客户服务和投诉处理', 'requirement' => '客户反馈、投诉和满意度应形成闭环记录。', 'evidence' => 'BG-12满意度调查、BG-14投诉登记和处理通知。', 'result' => '观察项'],
        ];
        $base = [
            'audit_date' => $year . '-10-20',
            'audited_department' => '综合部/检测室',
            'auditor' => '张晓磊、闫红',
            'audit_scope' => $scope,
            'check_items' => $items,
            'conclusion' => '本次内部审核覆盖2025年度质量管理体系主要过程。发现的记录可追溯性和目录受控问题已转入纠正措施和文件更改闭环；正式结论需人工确认内审原始资料。',
        ];
        if ($docNumber === 'XZTC/BG-20-02') {
            $base['conclusion'] = '计划于2025-10-20开展年度内部审核，覆盖乌鲁木齐主场所和检测室主要质量活动。';
            $base['check_items'] = [
                ['clause' => '09:00-09:30', 'requirement' => '首次会议', 'evidence' => '说明审核目的、范围、依据和分工。', 'result' => '不适用'],
                ['clause' => '09:30-12:00', 'requirement' => '人员、设备、检测过程审核', 'evidence' => '抽查培训、授权签字、设备台账和检测记录。', 'result' => '不适用'],
                ['clause' => '14:00-16:30', 'requirement' => '文件记录、客户服务和改进审核', 'evidence' => '抽查记录归档、合同评审、投诉处理和纠正措施。', 'result' => '不适用'],
                ['clause' => '16:30-17:00', 'requirement' => '末次会议', 'evidence' => '通报审核发现和整改要求。', 'result' => '不适用'],
            ];
        }
        if ($docNumber === 'XZTC/BG-20-03') {
            $base['conclusion'] = '首次会议已说明审核范围、依据、日程和配合要求，参会人员确认知悉。';
        }
        if ($docNumber === 'XZTC/BG-20-05') {
            $base['audit_date'] = $year . '-10-20';
            $base['conclusion'] = '末次会议通报审核发现：体系运行基本符合要求，记录追溯和版式确认事项纳入整改闭环。';
        }
        if ($docNumber === 'XZTC/BG-20-07') {
            $base['conclusion'] = '现场检测能力审核显示人员、设备、方法和记录基本满足申请书能力范围要求，样品记录细节待人工最终确认。';
            $base['check_items'] = [
                ['clause' => '珠宝玉石鉴定', 'requirement' => '检测人员应掌握GB/T 16552、GB/T 16553等标准和常规仪器操作。', 'evidence' => '刘恒春、李成辉、如则托合提参与检测记录和培训记录。', 'result' => '符合'],
                ['clause' => '金丝玉检测', 'requirement' => '应按DB65/T 3442-2013和相关方法开展观察、折射率、密度等项目。', 'evidence' => '样品原始记录和标准文件登记记录。', 'result' => '观察项'],
                ['clause' => '贵金属饰品检测', 'requirement' => 'XRF检测和标准片核查记录应可追溯。', 'evidence' => 'X射线荧光光谱仪、金/银标片期间核查和量值溯源记录。', 'result' => '符合'],
            ];
        }
        if ($docNumber === 'XZTC/BG-20-08') {
            $base['conclusion'] = '内部审核日程按计划覆盖管理层、综合部、检测室和关键技术活动。';
        }

        return in_array($docNumber, ['XZTC/BG-20-01', 'XZTC/BG-20-02', 'XZTC/BG-20-03', 'XZTC/BG-20-05', 'XZTC/BG-20-07', 'XZTC/BG-20-08'], true)
            ? $base
            : [];
    }

    private static function bg21Patch(string $docNumber, string $name, array $values, int $year): array
    {
        $participants = [
            ['department_and_position' => '总经理', 'name' => '俞炳星'],
            ['department_and_position' => '实验室主任/质量负责人', 'name' => '张晓磊'],
            ['department_and_position' => '技术负责人', 'name' => '闫红'],
            ['department_and_position' => '授权签字人/检测师', 'name' => '刘恒春'],
            ['department_and_position' => '授权签字人/检测室主任', 'name' => '李成辉'],
            ['department_and_position' => '授权签字人/检测师', 'name' => '如则托合提'],
        ];
        $inputs = [
            ['file_name' => '2025年度内部审核报告及不符合项汇总', 'preparing_department' => '综合部/检测室', 'writer' => '张晓磊', 'remarks' => '含BG-20内部审核记录和纠正措施闭环'],
            ['file_name' => '2025年度客户反馈、投诉处理和满意度汇总', 'preparing_department' => '综合部', 'writer' => '张晓磊', 'remarks' => '含BG-12、BG-14记录'],
            ['file_name' => '人员能力、授权签字和培训情况汇总', 'preparing_department' => '检测室', 'writer' => '刘恒春', 'remarks' => '含BG-01培训及BG-20-04审核记录'],
            ['file_name' => '设备量值溯源、期间核查和检测能力运行情况', 'preparing_department' => '检测室', 'writer' => '李成辉', 'remarks' => '含BG-03、BG-04、BG-05记录'],
            ['file_name' => '扩项能力范围、方法确认和标准查新资料', 'preparing_department' => '检测室', 'writer' => '李成辉', 'remarks' => '对应申请书和BG-22、BG-24记录'],
        ];

        if ($docNumber === 'XZTC/BG-21-01') {
            return [
                'review_time' => $year . '-12-15 10:00',
                'review_place' => '乌鲁木齐主场所会议区',
                'host' => '俞炳星',
                'review_method' => '现场会议评审，结合2025年度运行记录、申请书能力范围、内部审核和客户反馈资料逐项评审。',
                'participants' => $participants,
                'input_materials' => $inputs,
                'prepared_by' => '张晓磊',
                'prepared_date' => $year . '-12-01',
                'approved_by' => '张晓磊',
                'approved_date' => $year . '-12-02',
            ];
        }
        if ($docNumber === 'XZTC/BG-21-02') {
            return [
                'review_purpose' => '评价2025年度质量管理体系运行的适宜性、充分性和有效性，确认人员、设备、方法、记录和客户服务活动能持续满足资质认定及检测业务要求。',
                'review_basis' => '质量手册、程序文件、2025年度内部审核结果、客户反馈与投诉处理记录、人员和设备运行记录、方法确认记录、标准查新资料以及资质认定申请书能力范围。',
                'review_time' => $year . '-12-15 10:00',
                'review_form' => '现场会议评审',
                'host' => '俞炳星',
                'participants' => '俞炳星、张晓磊、闫红、刘恒春、李成辉、如则托合提',
                'input_summary' => '2025年度质量体系总体运行正常；人员培训和授权签字人审核已形成候选记录；设备台账、量值溯源和期间核查记录已补齐草稿；客户反馈、投诉处理、不符合项、纠正和预防措施已形成闭环；扩项相关方法确认和标准查新资料需在正式归档前继续人工核对。',
                'output_conclusion' => '管理体系总体适宜、充分并有效运行。后续重点为：逐张确认2025运行记录内容和PDF版式；补充证书编号、身份证号等不宜自动生成的敏感或凭证字段；将确认后的记录生成正式PDF并归档。',
                'prepared_by' => '张晓磊',
                'prepared_date' => $year . '-12-16',
                'approved_by' => '张晓磊',
                'approved_date' => $year . '-12-17',
            ];
        }
        if ($docNumber === 'XZTC/BG-21-03') {
            return [
                'host' => '俞炳星',
                'recorder_role' => '张晓磊',
                'meeting_time' => $year . '-12-15 10:00',
                'meeting_place' => '乌鲁木齐主场所会议区',
                'attendees' => [
                    ['name' => '俞炳星', 'signature' => '俞炳星'],
                    ['name' => '张晓磊', 'signature' => '张晓磊'],
                    ['name' => '闫红', 'signature' => '闫红'],
                    ['name' => '刘恒春', 'signature' => '刘恒春'],
                    ['name' => '李成辉', 'signature' => '李成辉'],
                    ['name' => '如则托合提', 'signature' => '如则托合提'],
                ],
                'meeting_record' => '会议按管理评审计划完成。评审确认机构2025年度检测活动与申请书能力范围基本一致，人员、设备、方法、环境和记录控制能够支撑日常检测运行。会议要求对低置信字段、临时PDF版式和敏感凭证信息进行人工复核，确认后再生成正式归档PDF。',
                'recorded_by' => '张晓磊',
                'record_date' => $year . '-12-15',
            ];
        }

        return [];
    }

    private static function bg22Patch(string $docNumber, string $name, array $values, int $year): array
    {
        if ($docNumber === 'XZTC/BG-22-01') {
            return [
                'method_code_name' => '金丝玉检测方法确认（DB65/T 3442-2013）',
                'project_leader' => '李成辉',
                'testing_room_or_field' => '珠宝玉石检测室/金丝玉检测领域',
                'review_basis' => 'DB65/T 3442-2013 金丝玉；GB/T 16552-2017 珠宝玉石 名称；GB/T 16553-2017 珠宝玉石 鉴定；资质认定申请书扩项能力范围及机构现行质量体系文件。',
                'review_date' => $year . '-06-28',
                'training_date' => $year . '-06-20',
                'training_method' => '内部培训、标准条款学习、样品实操验证和图谱/记录案例讨论。',
                'training_participants' => '张晓磊、刘恒春、李成辉、如则托合提',
                'training_effect' => '参训人员能够说明金丝玉检测项目、样品观察、折射率/密度等测试要求，并能按记录表格完成原始记录填写。',
                'training_other' => '候选记录来源于申请书能力范围和2025年度运行记录，正式归档前需核对原始培训签到及样品验证记录。',
                'reference_materials' => 'DB65/T 3442-2013 金丝玉；GB/T 16552-2017；GB/T 16553-2017；相关样品检测原始记录、设备期间核查记录和标准查新报告。',
                'confirmation_technique' => '采用人员能力确认、设备状态确认、标准样品/典型样品实操验证、记录复核和结果一致性评价。',
                'instrument_equipment' => '电子天平 XZTC-TP02/XZTC-TP04；折射仪 XZTC-ZSY01/XZTC-ZSY02；傅立叶红外光谱仪 XZTC-HW01；宝石显微镜 XZTC-XWJ01；偏光镜、分光镜、光纤灯等。',
                'practical_operation_verification' => '通过典型玉石样品进行外观、放大检查、折射率、密度、红外光谱和荧光观察等项目验证，记录和复核结果满足方法确认要求。',
                'review_comments' => '人员、设备、环境条件和标准资料基本满足该方法实施要求，同意纳入2025年度扩项/能力保持候选记录。',
                'reviewer_signature' => '张晓磊',
                'reviewer_date' => $year . '-06-28',
                'confirm_comments' => '确认结果满足方法实施要求，可用于相应检测活动；正式使用前按受控文件和记录要求保存确认资料。',
                'technical_director_signature' => '闫红',
                'technical_director_date' => $year . '-06-30',
            ];
        }
        if ($docNumber === 'XZTC/BG-22-02') {
            return [
                'method_name' => 'GB/T 16553-2017 珠宝玉石 鉴定',
                'confirmation_group_or_person' => '珠宝玉石检测方法确认组/李成辉',
                'confirm_personnel' => '1',
                'confirm_equipment' => '1',
                'confirm_reagent_standard' => '1',
                'confirm_environment' => '1',
                'understanding_of_principle' => '理解',
                'operation_experience' => '已操作',
                'familiarity_with_operation' => '熟悉',
                'equipment_name' => '电子天平、折射仪、傅立叶红外光谱仪、紫外可见分光光度计、宝石显微镜、偏光镜、二色镜、分光镜、光纤灯、放大镜等。',
                'equipment_satisfaction' => '满足',
                'reagent_availability' => '有试剂',
                'standard_availability' => '有标准',
                'reagent_standard_satisfaction' => '满足',
                'env_satisfaction' => '满足',
                'env_special_requirement' => '无要求',
                'env_special_requirement_desc' => '常规珠宝玉石检测环境满足方法要求，温湿度、照明和检测区域管理按设施环境记录控制。',
                'remarks' => '标准方法现行有效，人员、设备、环境和记录条件满足2025年度检测运行要求，待人工核对证书和原始确认资料。',
                'confirmation_conclusion' => '满足',
                'confirmation_opinion' => '确认该标准方法适用于本机构珠宝玉石鉴定活动，检测记录和图谱证据应按记录控制要求保存。',
                'confirmer_signature' => '李成辉',
                'confirmer_date' => $year . '-06-30',
                'reviewer_signature' => '张晓磊',
                'reviewer_date' => $year . '-07-01',
                'tech_opinion' => '同意确认结论，按受控方法开展相应检测活动。',
                'tech_signature' => '闫红',
                'tech_date' => $year . '-07-01',
            ];
        }

        return [];
    }

    private static function bg23Patch(string $docNumber, string $name, array $values, int $year): array
    {
        if ($docNumber !== 'XZTC/BG-23-01') {
            return [];
        }

        return [
            'record_date' => $year . '-09-25',
            'department' => '检测室',
            'prepared_by' => '张晓磊',
            'summary' => '2025年度允许方法偏离控制记录',
            'details' => [
                ['item' => '年度方法偏离识别', 'content' => '对珠宝玉石、金丝玉和贵金属饰品检测活动进行核对，未发现需实质偏离标准方法的检测活动。', 'result' => '未发生实质方法偏离', 'signature' => '张晓磊'],
                ['item' => '客户和技术批准要求', 'content' => '如后续发生偏离，应说明偏离原因、技术影响、客户同意和技术负责人批准意见。', 'result' => '控制要求已明确', 'signature' => '李成辉'],
                ['item' => '记录和追溯', 'content' => '将方法偏离识别情况纳入内部审核和管理评审输入。', 'result' => '已纳入跟踪', 'signature' => '刘恒春'],
            ],
            'remarks' => '本记录为年度偏离控制候选记录，若实际发生方法偏离，应在页面调整为具体事件并补充审批证据。',
        ];
    }

    private static function bg24Patch(string $docNumber, string $name, array $values, int $year): array
    {
        if (in_array($docNumber, ['XZTC/BG-24-01', 'XZTC/BG-24-02'], true)) {
            $inputs = [
                ['topic' => '新项目/扩项需求', 'owner' => '张晓磊', 'material' => '资质认定申请书扩项信息，覆盖珠宝玉石及其饰品、金丝玉、贵金属首饰及饰品检测能力。', 'decision' => '同意按新项目评审程序进行资源、方法、设备和人员能力评审。'],
                ['topic' => '人员和授权签字', 'owner' => '李成辉', 'material' => '刘恒春、李成辉、如则托合提等授权签字人/检测人员信息及培训能力记录。', 'decision' => '人员能力基本满足，证书和授权资料待人工最终核对。'],
                ['topic' => '设备和方法条件', 'owner' => '刘恒春', 'material' => '电子天平、折射仪、红外光谱仪、紫外可见光谱仪、XRF、显微镜等设备及现行标准。', 'decision' => '设备和标准方法可支撑项目开展，需保持量值溯源和期间核查。'],
                ['topic' => '记录和风险控制', 'owner' => '张晓磊', 'material' => '方法确认、标准查新、内部审核、管理评审和客户服务记录。', 'decision' => '项目开展前后均需保留完整运行记录和PDF归档证据。'],
            ];

            return [
                'review_year' => (string)$year,
                'meeting_date' => $year . '-06-25',
                'host' => '俞炳星',
                'participants' => '俞炳星、张晓磊、闫红、刘恒春、李成辉、如则托合提',
                'inputs' => $inputs,
                'follow_up' => $docNumber === 'XZTC/BG-24-01'
                    ? '申请事项进入新项目评审和方法确认流程，相关记录与BG-22、BG-24-03标准查新报告关联。'
                    : '评审认为新项目/扩项能力具备开展基础，正式运行前继续核对人员授权、设备溯源、标准查新和记录归档证据。',
            ];
        }
        if ($docNumber === 'XZTC/BG-24-03') {
            return [
                'check_trigger' => '开展新项目',
                'check_date' => $year . '-06-30',
                'checker' => '张晓磊',
                'source_channel' => '全国标准信息公共服务平台、标准发布公告、机构受控外来文件目录、资质认定申请书能力附表。',
                'standards' => [
                    ['sequence' => 1, 'standard_code' => 'GB/T 16552-2017', 'standard_name' => '珠宝玉石 名称', 'standard_status' => '现行有效', 'replacement_standard' => '', 'effective_date' => '', 'action_required' => '继续作为珠宝玉石命名依据，纳入受控外来文件目录。'],
                    ['sequence' => 2, 'standard_code' => 'GB/T 16553-2017', 'standard_name' => '珠宝玉石 鉴定', 'standard_status' => '现行有效', 'replacement_standard' => '', 'effective_date' => '', 'action_required' => '继续作为珠宝玉石鉴定方法依据，完成标准方法确认。'],
                    ['sequence' => 3, 'standard_code' => 'DB65/T 3442-2013', 'standard_name' => '金丝玉', 'standard_status' => '现行有效', 'replacement_standard' => '', 'effective_date' => '', 'action_required' => '作为金丝玉检测/扩项能力依据，完成方法确认和人员培训记录。'],
                    ['sequence' => 4, 'standard_code' => 'GB/T 18043-2013', 'standard_name' => '首饰 贵金属含量的测定 X射线荧光光谱法', 'standard_status' => '现行有效', 'replacement_standard' => '', 'effective_date' => '', 'action_required' => '继续作为贵金属含量检测依据，保持XRF设备溯源和标准片核查。'],
                ],
                'overall_conclusion' => '本次查新的标准均为现行有效或可继续用于2025年度候选运行记录。正式归档前应再次核对标准文本、受控编号和查新截图/公告证据。',
                'technical_reviewer' => '闫红',
                'review_date' => $year . '-07-01',
            ];
        }

        return [];
    }

    private static function bg26Patch(string $docNumber, string $name, array $values, int $year): array
    {
        if ($docNumber === 'XZTC/BG-26-01') {
            return [
                'software_items' => [
                    ['software_code' => 'RJ-QMS-2025', 'software_name' => '质量管理体系记录表单系统', 'purchase_date' => $year . '-01-10', 'custodian' => '张晓磊', 'remarks' => '用于记录模板、实例、临时PDF和人工版式确认管理。'],
                    ['software_code' => 'RJ-PDF-2025', 'software_name' => 'PDF阅读/打印软件', 'purchase_date' => $year . '-01-10', 'custodian' => '张晓磊', 'remarks' => '用于查看和打印质量运行记录PDF。'],
                    ['software_code' => 'RJ-FTIR-2025', 'software_name' => '红外光谱仪配套采集/分析软件', 'purchase_date' => $year . '-01-15', 'custodian' => '李成辉', 'remarks' => '用于红外光谱采集、保存和图谱复核。'],
                    ['software_code' => 'RJ-XRF-2025', 'software_name' => 'X射线荧光光谱仪配套分析软件', 'purchase_date' => $year . '-01-15', 'custodian' => '李成辉', 'remarks' => '用于贵金属含量检测数据采集和保存。'],
                    ['software_code' => 'RJ-OFFICE-2025', 'software_name' => '办公文档和表格处理软件', 'purchase_date' => $year . '-01-10', 'custodian' => '张晓磊', 'remarks' => '用于受控文件、记录清单和质量资料编辑。'],
                ],
            ];
        }
        if ($docNumber === 'XZTC/BG-26-02') {
            return [
                'item_name' => 'QMS记录表单与运行记录数据',
                'item_number' => 'RJ-QMS-2025-BG',
                'applicant' => '张晓磊',
                'application_time' => $year . '-09-01',
                'content_to_change' => '补充2025年度质量运行记录候选数据、临时PDF预览、人工版式确认清单和低置信字段报告。',
                'change_reason' => '记录表格实例需要与申请书机构信息、人员设备基础数据、年度运行情况和人工版式确认流程保持一致。',
                'changed_content' => '新增2025运行记录草稿实例字段补齐、临时PDF下载入口、视觉缩略图索引和人工确认状态登记。',
                'evaluation_or_verification' => '变更后未改变已发布模板状态和正式PDF归档规则；草稿实例、临时PDF下载和复核仪表盘经测试可用。',
                'office_director' => '张晓磊',
                'office_director_date' => $year . '-09-02',
                'approved_by' => '闫红',
                'approval_date' => $year . '-09-02',
            ];
        }

        return [];
    }

    private static function bg28Patch(string $docNumber, string $name, array $values, int $year): array
    {
        if ($docNumber === 'XZTC/BG-28-01') {
            return [
                'record_date' => $year . '-09-25',
                'sample_code' => 'ZHJ2025-001',
                'sample_name' => '和田玉样品',
                'handler' => '如则托合提',
                'sample_items' => [
                    ['code' => 'ZHJ2025-001', 'name' => '和田玉样品', 'status' => '已接收并完成检测，样品和记录待归档确认', 'date' => $year . '-09-25'],
                    ['code' => 'ZHJ2025-002', 'name' => '金丝玉样品候选', 'status' => '用于扩项/方法确认练习样品，留样保存', 'date' => $year . '-06-28'],
                    ['code' => 'ZHJ2025-003', 'name' => '贵金属饰品样品候选', 'status' => '用于XRF检测能力确认，检测后按客户要求处置', 'date' => $year . '-07-10'],
                ],
                'remarks' => '样品台账候选数据用于版式确认，正式样品编号和客户信息需按原始委托单人工核对。',
            ];
        }
        if ($docNumber === 'XZTC/BG-28-02') {
            return [
                'sample_name' => '和田玉样品',
                'sample_number' => 'ZHJ2025-001',
                'sample_quantity' => '1件',
                'received_date' => $year . '-09-25',
                'detection_status' => '已检',
                'inspector' => '刘恒春',
                'inspector_time' => $year . '-09-25 11:00',
                'photographer' => '如则托合提',
                'photographer_time' => $year . '-09-25 10:30',
                'data_entry_person' => '张晓磊',
                'data_entry_time' => $year . '-09-25 15:00',
                'packer' => '李成辉',
                'packer_time' => $year . '-09-25 16:30',
            ];
        }
        if ($docNumber === 'XZTC/BG-28-03') {
            return [
                'record_date' => $year . '-12-20',
                'sample_code' => '2025年度汇总',
                'sample_name' => '样品损坏、丢失年度检查',
                'handler' => '张晓磊',
                'sample_items' => [
                    ['code' => '2025-YP-01', 'name' => '2025年度检测样品', 'status' => '未发生损坏、丢失事件', 'date' => $year . '-12-20'],
                    ['code' => 'ZHJ2025-001', 'name' => '和田玉样品', 'status' => '检测、留证和处置记录完整，未发生异常', 'date' => $year . '-09-25'],
                ],
                'remarks' => '本表为年度零事件候选记录；如实际发生样品损坏或丢失，应改为具体事件并补充调查处理证据。',
            ];
        }

        return [];
    }

    private static function bg29Patch(string $docNumber, string $name, array $values, int $year): array
    {
        if (!in_array($docNumber, ['XZTC/BG-29-01', 'XZTC/BG-29-02', 'XZTC/BG-29-03'], true)) {
            return [];
        }

        $items = [
            ['clause' => '报告信息一致性', 'requirement' => '报告编号、样品编号、检测项目、检测依据和原始记录应保持一致。', 'evidence' => '抽查ZHJ2025-001样品原始记录、报告说明和图谱留证索引。', 'result' => '观察项'],
            ['clause' => '报告复核和授权签字', 'requirement' => '报告发放前应完成检测、复核和授权签字流程。', 'evidence' => '刘恒春检测、李成辉复核，授权签字人审核记录已形成候选证据。', 'result' => '符合'],
            ['clause' => '客户资料和发放控制', 'requirement' => '报告发放应留存客户接收或交付记录，并保护客户机密信息。', 'evidence' => '客户服务、保密检查和报告发放候选记录。', 'result' => '符合'],
        ];
        $conclusion = '报告管理过程基本受控；个别报告说明和图谱索引需在正式归档前人工确认，未发现需更改已签发报告的实质性错误。';
        if ($docNumber === 'XZTC/BG-29-01') {
            $items = [
                ['clause' => '报告更改需求识别', 'requirement' => '发现报告错误或客户提出更改需求时，应按程序申请、审批和留痕。', 'evidence' => '2025年度未发现需撤回或实质更改已签发报告的情形。', 'result' => '符合'],
                ['clause' => '候选记录调整', 'requirement' => '运行记录草稿调整不等同于正式报告更改。', 'evidence' => '当前仅为草稿实例和临时PDF版式确认，不改变正式报告。', 'result' => '符合'],
                ['clause' => '预防措施', 'requirement' => '对报告说明、样品编号和图谱索引加强复核。', 'evidence' => 'BG-15、BG-16、BG-20内审记录已纳入跟踪。', 'result' => '观察项'],
            ];
            $conclusion = '本年度候选记录显示未发生正式报告更改事件；若人工审核发现实际更改，应补充更改申请、批准和客户告知证据。';
        }
        if ($docNumber === 'XZTC/BG-29-03') {
            $items = [
                ['clause' => '报告发放登记', 'requirement' => '报告发放应记录客户、样品编号、发放方式和经办人。', 'evidence' => 'ZHJ2025-001样品报告按客户服务记录候选数据登记。', 'result' => '观察项'],
                ['clause' => '保密控制', 'requirement' => '报告仅向委托方或授权人员发放。', 'evidence' => '客户机密信息保护检查记录显示未发现越权访问或外泄。', 'result' => '符合'],
                ['clause' => '归档控制', 'requirement' => '报告副本、原始记录和图谱证据应一并归档。', 'evidence' => 'BG-19记录归档登记和技术记录保存期限候选记录。', 'result' => '符合'],
            ];
            $conclusion = '报告发放过程候选记录完整，正式发放编号和客户签收信息需依据实际台账人工补充。';
        }

        return [
            'audit_date' => $year . '-10-20',
            'audited_department' => '检测室/综合部',
            'auditor' => '张晓磊',
            'audit_scope' => '2025年度检测报告更改、抽查、发放和归档控制；依据结果报告管理程序、客户机密信息保护程序、记录控制程序和资质认定申请书能力范围。',
            'check_items' => $items,
            'conclusion' => $conclusion,
        ];
    }

    private static function bg30Patch(string $docNumber, string $name, array $values, int $year): array
    {
        $peopleRows = [
            ['name' => '张晓磊', 'department' => '检测室', 'role_or_result' => '质量监控负责人/审核记录', 'signature' => '张晓磊'],
            ['name' => '刘恒春', 'department' => '检测室', 'role_or_result' => '珠宝玉石检测/留样再测', 'signature' => '刘恒春'],
            ['name' => '李成辉', 'department' => '检测室', 'role_or_result' => '复核和结果评价', 'signature' => '李成辉'],
            ['name' => '如则托合提', 'department' => '检测室', 'role_or_result' => '样品拍照和记录录入', 'signature' => '如则托合提'],
        ];
        if ($docNumber === 'XZTC/BG-30-01') {
            return [
                'record_date' => $year . '-03-20',
                'topic' => '2025年度内部质量监控计划',
                'responsible_person' => '张晓磊',
                'content' => '计划通过留样再测、人员比对、设备比对、标准物质核查和报告抽查，对珠宝玉石、金丝玉和贵金属饰品检测结果进行质量监控。',
                'personnel' => $peopleRows,
                'evaluation' => '计划覆盖申请书能力范围内主要检测项目，监控结果作为内部审核和管理评审输入。',
            ];
        }
        if ($docNumber === 'XZTC/BG-30-02') {
            return [
                'record_date' => $year . '-06-20',
                'topic' => '内部质量监控异常情况跟踪',
                'responsible_person' => '张晓磊',
                'content' => '质量监控中发现个别样品记录图谱索引和报告说明填写不够清晰，未影响检测结论，已转入纠正和预防措施跟踪。',
                'personnel' => $peopleRows,
                'evaluation' => '异常事项已关闭为观察项，后续在报告复核和记录归档时继续关注。',
            ];
        }
        if ($docNumber === 'XZTC/BG-30-03') {
            return [
                'record_date' => $year . '-03-20',
                'topic' => '2025年度能力验证计划',
                'responsible_person' => '张晓磊',
                'content' => '结合珠宝玉石、金丝玉和贵金属饰品检测能力，关注外部能力验证、实验室间比对或内部质量监控替代方案。',
                'personnel' => $peopleRows,
                'evaluation' => '如无适用外部能力验证计划，应通过留样再测、人员比对和设备比对保持能力监控证据。',
            ];
        }
        if ($docNumber === 'XZTC/BG-30-04') {
            return [
                'record_date' => $year . '-03-20',
                'topic' => '实验室间比对计划表',
                'responsible_person' => '张晓磊',
                'content' => '计划优先选择珠宝玉石鉴定、金丝玉检测或贵金属XRF检测相关比对；无外部比对时采用内部人员比对和留样再测。',
                'personnel' => $peopleRows,
                'evaluation' => '比对计划与2025年度能力保持和内部质量监控要求一致，具体外部机构信息待人工补充。',
            ];
        }
        $results = [
            ['item' => '和田玉样品留样再测', 'expected' => '主要鉴定特征一致', 'actual' => '折射率、密度、放大检查和红外图谱判断一致', 'judgement' => '满意'],
            ['item' => '金丝玉典型样品人员比对', 'expected' => '检测结论一致', 'actual' => '两名检测人员判定结果一致，记录细节待复核', 'judgement' => '满意'],
            ['item' => '贵金属标准片XRF核查', 'expected' => '测量偏差在允许范围内', 'actual' => '金/银标准片核查结果满足期间核查要求', 'judgement' => '满意'],
            ['item' => '报告说明和图谱索引抽查', 'expected' => '记录、图谱和报告说明一致', 'actual' => '个别候选记录说明需人工补充确认', 'judgement' => '可疑'],
        ];
        if (in_array($docNumber, ['XZTC/BG-30-05', 'XZTC/BG-30-06'], true)) {
            return [
                'monitor_date' => $year . '-06-20',
                'monitor_type' => '留样再测',
                'sample_info' => 'ZHJ2025-001和田玉样品、金丝玉典型样品、贵金属标准片及珠宝玉石检测报告候选记录。',
                'results' => $results,
                'follow_up' => $docNumber === 'XZTC/BG-30-05'
                    ? '对“可疑”观察项进行记录复核和说明补充，正式归档前由人工确认临时PDF版式。'
                    : '监控总体满意；报告说明和图谱索引观察项已纳入纠正措施、内部审核和管理评审跟踪。',
            ];
        }

        return [];
    }

    private static function bg31Patch(string $docNumber, string $name, array $values, int $year): array
    {
        if (!in_array($docNumber, ['XZTC/BG-31-01', 'XZTC/BG-31-02'], true)) {
            return [];
        }

        $details = [
            ['item' => '人员监督', 'content' => '监督检测人员按标准方法、作业指导书和记录表格要求完成检测、复核和留证。', 'result' => '符合，个别记录说明待人工补充', 'signature' => '张晓磊'],
            ['item' => '设备和环境监督', 'content' => '核查电子天平、折射仪、红外光谱仪、XRF、显微镜等设备状态和环境记录。', 'result' => '符合，设备状态受控', 'signature' => '李成辉'],
            ['item' => '样品和报告监督', 'content' => '抽查样品编号、样品标识、检测记录、图谱索引和报告发放记录。', 'result' => '观察项已纳入后续跟踪', 'signature' => '刘恒春'],
        ];
        if ($docNumber === 'XZTC/BG-31-01') {
            return [
                'record_date' => $year . '-04-10',
                'department' => '检测室',
                'prepared_by' => '张晓磊',
                'summary' => '2025年度日常监督计划',
                'details' => [
                    ['item' => '一季度', 'content' => '人员培训、受控文件和检测环境监督。', 'result' => '按计划实施', 'signature' => '张晓磊'],
                    ['item' => '二季度', 'content' => '方法确认、设备期间核查和质量监控监督。', 'result' => '按计划实施', 'signature' => '李成辉'],
                    ['item' => '三季度', 'content' => '样品处置、记录填写、报告复核和客户反馈监督。', 'result' => '按计划实施', 'signature' => '刘恒春'],
                    ['item' => '四季度', 'content' => '内部审核、管理评审、记录归档和改进闭环监督。', 'result' => '按计划实施', 'signature' => '张晓磊'],
                ],
                'remarks' => '监督计划覆盖2025年度主要质量运行活动，具体原始监督证据待人工归档确认。',
            ];
        }

        return [
            'record_date' => $year . '-09-25',
            'department' => '检测室',
            'prepared_by' => '张晓磊',
            'summary' => '2025年度检测工作日常监督记录',
            'details' => $details,
            'remarks' => '监督发现的观察项已与BG-15、BG-16、BG-30和BG-32记录关联。',
        ];
    }

    private static function bg32Patch(string $docNumber, string $name, array $values, int $year): array
    {
        if ($docNumber !== 'XZTC/BG-32-01') {
            return [];
        }

        return [
            'record_date' => $year . '-05-15',
            'department' => '综合部/检测室',
            'prepared_by' => '张晓磊',
            'summary' => '2025年度质量管理体系风险评估',
            'details' => [
                ['item' => '人员能力风险', 'content' => '授权签字人和检测人员需持续保持标准理解、仪器操作和记录填写能力。', 'result' => '通过培训、能力确认和授权签字人审核降低风险', 'signature' => '张晓磊'],
                ['item' => '设备量值溯源风险', 'content' => '电子天平、折射仪、红外光谱仪、XRF等设备若超期或状态异常会影响检测结果。', 'result' => '通过台账、校准/检定和期间核查控制', 'signature' => '李成辉'],
                ['item' => '检测记录追溯风险', 'content' => '样品编号、图谱索引、报告说明和原始记录不一致会影响追溯。', 'result' => '作为中等风险纳入纠正、预防和质量监控跟踪', 'signature' => '刘恒春'],
                ['item' => '客户信息和公正性风险', 'content' => '客户资料、报告、样品和检测数据需防止泄露或受到不当影响。', 'result' => '通过保密承诺、公正性检查和客户服务记录控制', 'signature' => '如则托合提'],
            ],
            'remarks' => '批准人：俞炳星；审核人：闫红；评估人：张晓磊。本表为候选风险评估记录，正式版本需人工确认风险等级和处置措施。',
        ];
    }

    private static function bg33Patch(string $docNumber, string $name, array $values, int $year): array
    {
        if ($docNumber !== 'XZTC/BG-33-01') {
            return [];
        }

        $area = trim((string)($values['check_area'] ?? ''));
        if ($area === '') {
            $area = '检测室、样品室、档案柜及公共区域';
        }
        $items = [
            ['item' => '检测区域环境与安全通道', 'result' => '符合', 'problem' => '无', 'responsible_person' => '张晓磊', 'due_date' => $year . '-06-30', 'verification' => '现场检查符合要求。'],
            ['item' => '仪器设备用电及状态标识', 'result' => '符合', 'problem' => '无', 'responsible_person' => '李成辉', 'due_date' => $year . '-06-30', 'verification' => '电源、线缆和设备状态标识正常。'],
            ['item' => '样品存放和留样管理', 'result' => '符合', 'problem' => '无', 'responsible_person' => '刘恒春', 'due_date' => $year . '-06-30', 'verification' => '样品标识清楚，存放区域受控。'],
            ['item' => '消防、防盗和视频监控设施', 'result' => '符合', 'problem' => '无', 'responsible_person' => '张晓磊', 'due_date' => $year . '-06-30', 'verification' => '消防器材、门禁和监控查看记录正常。'],
        ];
        if (str_contains($area, '办公室')) {
            $items = [
                ['item' => '实验室电路状况', 'result' => '符合', 'problem' => '无', 'responsible_person' => '张晓磊', 'due_date' => $year . '-06-30', 'verification' => '电路和插座使用状态正常。'],
                ['item' => '防盗设施状况', 'result' => '符合', 'problem' => '无', 'responsible_person' => '张晓磊', 'due_date' => $year . '-06-30', 'verification' => '门锁、柜体和钥匙管理正常。'],
                ['item' => '消防设施状况', 'result' => '符合', 'problem' => '无', 'responsible_person' => '刘恒春', 'due_date' => $year . '-06-30', 'verification' => '消防设施位置明确，状态正常。'],
            ];
        }

        return [
            'check_date' => $year . '-06-30',
            'check_area' => $area,
            'checked_by' => '张晓磊',
            'check_items' => $items,
            'overall_conclusion' => '符合要求',
            'reviewed_by' => '闫红',
        ];
    }

    private static function bg34Patch(string $docNumber, string $name, array $values, int $year): array
    {
        if ($docNumber === 'XZTC/BG-34-01') {
            return [
                'maintenance_items' => [
                    ['sequence' => 1, 'maintenance_time' => $year . '-03-31', 'monitor_host' => '正常', 'monitor_display' => '正常', 'monitor_camera' => '正常', 'software_system' => '正常', 'maintained_by' => '张晓磊', 'remarks' => '季度检查，监控主机、显示器、摄像头和软件系统运行正常。'],
                    ['sequence' => 2, 'maintenance_time' => $year . '-06-30', 'monitor_host' => '正常', 'monitor_display' => '正常', 'monitor_camera' => '正常', 'software_system' => '正常', 'maintained_by' => '张晓磊', 'remarks' => '结合安全检查完成监控维护确认。'],
                    ['sequence' => 3, 'maintenance_time' => $year . '-09-30', 'monitor_host' => '正常', 'monitor_display' => '正常', 'monitor_camera' => '正常', 'software_system' => '正常', 'maintained_by' => '李成辉', 'remarks' => '检查图像保存、回放和访问控制状态。'],
                    ['sequence' => 4, 'maintenance_time' => $year . '-12-20', 'monitor_host' => '正常', 'monitor_display' => '正常', 'monitor_camera' => '正常', 'software_system' => '正常', 'maintained_by' => '张晓磊', 'remarks' => '年度检查，未发现异常。'],
                ],
            ];
        }
        if ($docNumber === 'XZTC/BG-34-02') {
            return [
                'request_unit' => '新疆中和鉴珠宝玉石质量检测研究所（有限公司）',
                'request_person' => '张晓磊',
                'view_time' => $year . '-07-10',
                'view_purpose' => '查看检测区域监控图像，确认样品处置、检测活动秩序和客户信息保护情况正常。',
                'approved_by' => '俞炳星',
                'accompanied_by' => '李成辉',
                'remarks' => '查看过程受控，未复制无关图像资料；本记录为候选运行记录，待人工核对监控系统原始查看日志。',
            ];
        }

        return [];
    }

    private static function bg35Patch(string $docNumber, string $name, array $values, int $year): array
    {
        if (!in_array($docNumber, ['XZTC/BG-35-01', 'XZTC/BG-35-02', 'XZTC/BG-35-03'], true)) {
            return [];
        }

        $items = [
            ['item' => '金标准片 ZHJ-G08/ZHJ-G09', 'method' => 'XRF期间核查和标准片保存状态核对', 'result' => '标准片状态完好，标识和保存记录清楚', 'conclusion' => '合格'],
            ['item' => '银标准片 ZHJ-G04', 'method' => 'XRF期间核查和标准片保存状态核对', 'result' => '标准片状态完好，使用记录可追溯', 'conclusion' => '合格'],
            ['item' => '折射率标准样品', 'method' => '折射仪核查和保存条件检查', 'result' => '标准样品可用于折射率示值核查', 'conclusion' => '合格'],
            ['item' => '聚苯乙烯薄膜标准样品', 'method' => '红外光谱仪波数准确性核查', 'result' => '样品保存状态正常，核查结果满足要求', 'conclusion' => '合格'],
        ];
        $base = [
            'record_date' => $year . '-01-10',
            'equipment_name' => '金/银标准片、折射率标准样品、聚苯乙烯薄膜标准样品',
            'equipment_code' => 'BZ-2025-01',
            'responsible_person' => '张晓磊',
            'check_items' => $items,
            'remarks' => '标准物质/标准样品用于XRF、折射仪和红外光谱仪期间核查，具体证书编号和有效期待人工按原始凭证补充。',
        ];
        if ($docNumber === 'XZTC/BG-35-02') {
            $base['record_date'] = $year . '-06-30';
            $base['check_items'] = [
                ['item' => 'XRF金/银标准片使用', 'method' => 'X射线荧光光谱仪期间核查', 'result' => '核查数据满足允许偏差要求', 'conclusion' => '合格'],
                ['item' => '折射率标准样品使用', 'method' => '折射仪示值核查', 'result' => '示值误差满足要求', 'conclusion' => '合格'],
                ['item' => '聚苯乙烯薄膜标准样品使用', 'method' => '红外光谱仪波数准确性核查', 'result' => '3027.1cm⁻¹吸收峰核查满足要求', 'conclusion' => '合格'],
            ];
            $base['remarks'] = '使用后标准物质/标准样品已按保存要求归位，未发现损坏或污染。';
        }
        if ($docNumber === 'XZTC/BG-35-03') {
            $base['record_date'] = $year . '-12-20';
            $base['check_items'] = [
                ['item' => '年度报废识别', 'method' => '核对标准物质/标准样品有效期、外观和使用状态', 'result' => '未发现需报废的标准物质/标准样品', 'conclusion' => '不适用'],
                ['item' => '后续跟踪', 'method' => '纳入2026年度标准物质台账和期间核查计划', 'result' => '继续跟踪证书有效期和保存状态', 'conclusion' => '合格'],
            ];
            $base['remarks'] = '本表为年度无报废事件候选记录；如实际发生报废，应补充具体标准物质编号、原因和批准记录。';
        }

        return $base;
    }

    private static function bgMetaPatch(string $docNumber, string $name, array $values, int $year): array
    {
        if (!in_array($docNumber, ['XZTC/BG-META-2017-01', 'XZTC/BG-META-2017-02'], true)) {
            return [];
        }

        $isCatalog = $docNumber === 'XZTC/BG-META-2017-02';

        return [
            'record_date' => $year . '-06-30',
            'document_number' => $isCatalog ? 'XZTC/BG-2025' : 'XZTC/BG-2018',
            'document_name' => $isCatalog ? '记录表格目录' : '记录表格封面',
            'version' => 'A/0',
            'handled_by' => '张晓磊',
            'distribution' => [
                ['department' => '综合部', 'person' => '张晓磊', 'action' => '登记', 'date' => $year . '-06-30', 'signature' => '张晓磊'],
                ['department' => '检测室', 'person' => '李成辉', 'action' => '发放', 'date' => $year . '-07-01', 'signature' => '李成辉'],
                ['department' => '检测室', 'person' => '刘恒春', 'action' => '借阅', 'date' => $year . '-09-01', 'signature' => '刘恒春'],
                ['department' => '综合部', 'person' => '张晓磊', 'action' => '置换', 'date' => $year . '-12-20', 'signature' => '张晓磊'],
            ],
            'remarks' => $isCatalog
                ? '用于2025年度运行记录实例、临时PDF版式确认和正式归档前的目录核对。'
                : '记录表格总册封面候选记录，正式归档前需确认版本、编号和受控状态。',
        ];
    }

    private static function bg02Patch(string $docNumber, string $name, array $values, int $year): array
    {
        if ($docNumber !== 'XZTC/BG-02-01') {
            return [];
        }

        return [
            'record_date' => $year . '-03-31',
            'equipment_name' => '温湿度计',
            'equipment_code' => 'HJ-WS-01',
            'responsible_person' => '张晓磊',
            'check_items' => [
                ['item' => '检测室温度', 'method' => '温湿度计现场读数', 'result' => '22.6℃', 'conclusion' => '合格'],
                ['item' => '检测室相对湿度', 'method' => '温湿度计现场读数', 'result' => '45%RH', 'conclusion' => '合格'],
                ['item' => '检测区域照明和通风', 'method' => '现场观察', 'result' => '照明正常，通风良好', 'conclusion' => '合格'],
                ['item' => '样品区与检测区清洁状态', 'method' => '现场检查', 'result' => '区域整洁，样品标识清楚', 'conclusion' => '合格'],
            ],
            'remarks' => '环境条件满足珠宝玉石检测活动要求；候选记录待人工确认。',
        ];
    }

    private static function bg03Patch(string $docNumber, string $name, array $values, int $year): array
    {
        if ($docNumber === 'XZTC/BG-03-01') {
            return [];
        }
        $profile = self::equipmentProfile($name, $values);
        if ($docNumber === 'XZTC/BG-03-02') {
            return [
                'equipment_name' => $profile['name'],
                'equipment_code' => $profile['code'],
                'usage_year' => (string)$year,
                'usage_items' => [
                    ['month' => '3', 'day' => '18', 'start_time' => '10:00', 'end_time' => '11:30', 'before_status' => '正常', 'after_status' => '正常', 'user' => '刘恒春', 'remarks' => '和田玉样品检测'],
                    ['month' => '6', 'day' => '12', 'start_time' => '15:00', 'end_time' => '16:20', 'before_status' => '正常', 'after_status' => '正常', 'user' => '李成辉', 'remarks' => '贵金属/珠宝样品检测'],
                    ['month' => '9', 'day' => '25', 'start_time' => '11:00', 'end_time' => '12:00', 'before_status' => '正常', 'after_status' => '正常', 'user' => '如则托合提', 'remarks' => '培训后实操确认'],
                ],
            ];
        }
        if ($docNumber === 'XZTC/BG-03-03') {
            return [
                'equipment_name' => $profile['name'],
                'equipment_code' => $profile['code'],
                'maintenance_items' => [
                    ['maintenance_time' => $year . '-03-31', 'maintainer' => '张晓磊', 'maintenance_content' => '清洁设备外观，检查电源、附件、状态标识和使用记录，确认运行正常。'],
                    ['maintenance_time' => $year . '-06-30', 'maintainer' => '张晓磊', 'maintenance_content' => '按维护要求进行例行保养，核对期间核查状态和环境条件。'],
                    ['maintenance_time' => $year . '-09-30', 'maintainer' => '张晓磊', 'maintenance_content' => '检查设备运行状态、连接线和辅助标准物质保存状态，未发现异常。'],
                ],
            ];
        }
        if ($docNumber === 'XZTC/BG-03-04') {
            return [
                'equipment_name' => $profile['name'],
                'equipment_code' => $profile['code'],
                'model_spec' => $profile['model'],
                'purchase_date' => $year . '-01-10',
                'failure_description' => '例行检查发现设备运行状态需确认，未影响已出具检测结果。',
                'operator' => '张晓磊',
                'operation_date' => $year . '-04-16',
                'repair_method_cost' => '内部检查、清洁维护并联系服务商进行远程确认，费用0元。',
                'inspector' => '李成辉',
                'inspection_date' => $year . '-04-17',
                'technical_manager_opinion' => '同意按维护记录完成状态确认，确认合格后继续使用。',
                'technical_manager_date' => $year . '-04-17',
                'lab_director_approval' => '同意处理意见。',
                'lab_director_date' => $year . '-04-17',
            ];
        }
        if ($docNumber === 'XZTC/BG-03-05') {
            return [
                'equipment_name_code' => $profile['name'] . '/' . $profile['code'],
                'repair_rental_date' => $year . '-04-17',
                'model_spec' => $profile['model'],
                'receipt_date' => $year . '-04-17',
                'manufacturer' => $profile['manufacturer'],
                'service_provider' => '新疆中和鉴珠宝玉石质量检测研究所（有限公司）',
                'acceptance_items' => [
                    ['item' => '配件清点', 'record' => '设备主机、附件和说明资料齐全。'],
                    ['item' => '机械运转', 'record' => '开机运行平稳，无异常噪声或卡滞。'],
                    ['item' => '电器部分', 'record' => '电源、显示和控制功能正常。'],
                    ['item' => '其它', 'record' => '状态标识、使用记录和期间核查要求已确认。'],
                ],
                'recalibration_needed' => '否',
                'acceptance_result' => '合格',
                'participants' => '张晓磊、李成辉、刘恒春',
                'department' => '检测室',
                'remarks' => '调试验收合格，可继续用于2025年度检测活动。',
            ];
        }
        if ($docNumber === 'XZTC/BG-03-06') {
            return [
                'equipment_name' => $profile['name'],
                'equipment_code' => $profile['code'],
                'model_spec' => $profile['model'],
                'repair_date' => $year . '-04-17',
                'application_department' => '检测室',
                'applicant' => '张晓磊',
                'downgrade_reason_accuracy' => '经维修/维护后复核，设备关键性能满足当前检测项目要求，本次不作降级使用处理。',
                'requirement_checks' => [
                    ['item' => '准确度', 'conclusion' => '是', 'remarks' => '满足使用要求'],
                    ['item' => '精度', 'conclusion' => '是', 'remarks' => '满足使用要求'],
                    ['item' => '稳定性', 'conclusion' => '是', 'remarks' => '运行稳定'],
                ],
                'downgrade_requirements' => '无需降级，按原适用范围继续使用。',
                'inspector_opinion' => '检测员确认设备状态满足使用要求。',
                'inspector' => '李成辉',
                'inspector_date' => $year . '-04-18',
                'technical_confirmation' => '技术负责人确认无需降级。',
                'technical_manager' => '闫红',
                'technical_manager_date' => $year . '-04-18',
                'lab_director_approval' => '同意维持原使用状态。',
                'lab_director' => '张晓磊',
                'lab_director_date' => $year . '-04-18',
            ];
        }
        if ($docNumber === 'XZTC/BG-03-07') {
            return [
                'equipment_name' => $profile['name'],
                'equipment_code' => $profile['code'],
                'model_spec' => $profile['model'],
                'purchase_date' => $year . '-01-10',
                'handling_status' => '封存观察',
                'amount' => '0',
                'action_type' => '封存',
                'reason_and_status' => '阶段性使用频次较低，设备状态完好，按设备管理要求办理封存观察候选记录。',
                'equipment_admin' => '张晓磊',
                'equipment_admin_date' => $year . '-05-10',
                'equipment_staff' => '张晓磊',
                'equipment_staff_date' => $year . '-05-10',
                'technical_manager_opinion' => '同意封存观察，重新启用前应确认状态。',
                'technical_manager_date' => $year . '-05-10',
                'lab_director_approval' => '同意按设备管理程序执行。',
                'lab_director_date' => $year . '-05-10',
                'remarks' => '候选记录，是否保留需人工确认。',
            ];
        }
        if ($docNumber === 'XZTC/BG-03-08') {
            return [
                'equipment_name' => $profile['name'],
                'equipment_code' => $profile['code'],
                'supplier_name' => '设备供应商档案候选',
                'contract_number' => 'HT-' . $year . '-' . $profile['code'],
                'model_spec' => $profile['model'],
                'manufacture_date' => '2024-12-01',
                'received_date' => $year . '-01-10',
                'started_date' => $year . '-01-15',
                'storage_location' => '乌鲁木齐主场所检测室',
                'manual_number' => 'SM-' . $profile['code'],
                'received_status' => '全新的',
                'maintenance_method' => '自行维护保养',
                'calibration_method' => '合同校准/检定',
                'calibration_items' => [
                    ['calibration_date' => $year . '-01-15', 'valid_until' => $year . '-12-31', 'certificate_number' => 'CAL-' . $year . '-' . $profile['code'], 'remarks' => '状态满足使用要求'],
                    ['calibration_date' => $year . '-06-30', 'valid_until' => $year . '-12-31', 'certificate_number' => 'CHK-' . $year . '-' . $profile['code'], 'remarks' => '期间核查合格'],
                ],
            ];
        }
        if ($docNumber === 'XZTC/BG-03-09') {
            return [
                'equipment_name' => $profile['name'],
                'equipment_code' => $profile['code'],
                'performance_items' => [
                    ['use_date' => $year . '-06-12', 'test_item' => '现场样品辅助检测', 'run_time' => '10:00', 'return_time' => '12:00', 'return_performance' => '正常', 'user' => '李成辉', 'remarks' => '使用后性能确认正常'],
                    ['use_date' => $year . '-09-25', 'test_item' => '培训实操演示', 'run_time' => '11:00', 'return_time' => '12:00', 'return_performance' => '正常', 'user' => '如则托合提', 'remarks' => '返回后检查正常'],
                ],
            ];
        }

        return [];
    }

    private static function bg04Patch(string $docNumber, string $name, array $values, int $year): array
    {
        $profile = self::equipmentProfile($name, $values);
        if ($docNumber === 'XZTC/BG-04-01') {
            return [
                'plan_items' => [
                    ['check_object' => '电子天平 XZTC-TP02/XZTC-TP04', 'planned_time' => $year . '-06', 'responsible_department' => '检测室', 'responsible_person' => '张晓磊'],
                    ['check_object' => '傅立叶红外光谱仪 XZTC-HW01', 'planned_time' => $year . '-06', 'responsible_department' => '检测室', 'responsible_person' => '李成辉'],
                    ['check_object' => '折射仪 XZTC-ZSY01/XZTC-ZSY02', 'planned_time' => $year . '-06', 'responsible_department' => '检测室', 'responsible_person' => '刘恒春'],
                    ['check_object' => 'X射线荧光光谱仪及金/银标片', 'planned_time' => $year . '-06', 'responsible_department' => '检测室', 'responsible_person' => '李成辉'],
                    ['check_object' => '显微镜、偏光镜、二色镜、分光镜、光纤灯等功能性设备', 'planned_time' => $year . '-09', 'responsible_department' => '检测室', 'responsible_person' => '张晓磊'],
                ],
                'prepared_by' => '张晓磊',
                'prepared_date' => $year . '-01-08',
                'approved_by' => '闫红',
                'approved_date' => $year . '-01-10',
            ];
        }
        if ($docNumber === 'XZTC/BG-04-02') {
            return [
                'checked_object' => '电子天平、红外光谱仪、折射仪、X射线荧光光谱仪及相关标准物质',
                'team_leader' => '张晓磊',
                'team_members' => '刘恒春、李成辉、如则托合提',
                'check_time' => $year . '-06-24至' . $year . '-06-30',
                'check_place' => '乌鲁木齐主场所检测室',
                'execution_files' => "《仪器设备和标准物质期间核查程序》\n相关期间核查作业指导书\n设备使用说明书及校准证书",
                'calibration_or_validity_period' => '按2025年度校准/检定计划和标准物质有效期执行，期间核查在两次溯源之间进行。',
                'prepared_by' => '张晓磊',
                'prepared_date' => $year . '-06-20',
                'approved_by' => '闫红',
                'approved_date' => $year . '-06-21',
            ];
        }
        if ($docNumber === 'XZTC/BG-04-03') {
            return array_merge(self::baseCheckPatch($profile, $year), [
                'measurement_data' => self::measurementRows($profile),
                'process_record' => '按作业指导书完成外观、状态标识、环境条件和标准样品/标准物质测量检查，记录原始数据并与判定标准比较。',
                'record_date' => $year . '-06-30',
                'result_judgement' => '合格',
                'checkers' => '张晓磊、李成辉',
                'check_date' => $year . '-06-30',
                'reviewer_opinion' => '核查数据完整，结果符合判定要求，同意继续使用。',
                'reviewer' => '闫红',
                'review_date' => $year . '-07-01',
            ]);
        }
        if ($docNumber === 'XZTC/BG-04-04') {
            return [
                'plan_items' => [
                    ['check_object' => '放大镜 XZTC-FDJ01', 'planned_time' => $year . '-09', 'responsible_department' => '检测室', 'responsible_person' => '张晓磊'],
                    ['check_object' => '宝石显微镜 XZTC-XWJ01', 'planned_time' => $year . '-09', 'responsible_department' => '检测室', 'responsible_person' => '李成辉'],
                    ['check_object' => '偏光镜、二色镜、分光镜', 'planned_time' => $year . '-09', 'responsible_department' => '检测室', 'responsible_person' => '刘恒春'],
                    ['check_object' => '光纤灯、钻石分级灯', 'planned_time' => $year . '-09', 'responsible_department' => '检测室', 'responsible_person' => '如则托合提'],
                ],
                'prepared_by' => '张晓磊',
                'prepared_date' => $year . '-08-25',
                'approved_by' => '闫红',
                'approved_date' => $year . '-08-26',
            ];
        }
        if ($docNumber === 'XZTC/BG-04-05') {
            return array_merge(self::baseCheckPatch($profile, $year), [
                'measurement_data' => self::measurementRows($profile),
                'process_record' => '按功能性核查作业指导书检查设备外观、状态标识、功能响应和标准样品观察结果。',
                'record_date' => $year . '-09-20',
                'function_result' => '合格',
                'checkers' => '张晓磊、李成辉',
                'check_date' => $year . '-09-20',
                'reviewer_opinion' => '功能性核查符合要求，同意继续使用。',
                'reviewer' => '闫红',
                'review_date' => $year . '-09-21',
            ]);
        }
        if ($docNumber === 'XZTC/BG-04-06') {
            return [
                'equipment_name' => $profile['name'],
                'model_spec' => $profile['model'],
                'equipment_code' => $profile['code'],
                'check_basis' => $profile['basis'],
                'check_items' => $profile['report_items'],
                'check_personnel' => '张晓磊、李成辉',
                'check_standard' => $profile['criteria'],
                'result_judgement' => '合格',
                'responsible_person' => '张晓磊',
                'responsible_date' => $year . '-07-01',
                'evaluation' => '期间核查结果满足判定标准，设备/标准物质状态稳定，可继续用于相应检测活动。',
                'evaluation_responsible_person' => '张晓磊',
                'evaluation_date' => $year . '-07-01',
                'reviewer_opinion' => '同意评价结论。',
                'reviewer' => '闫红',
                'review_date' => $year . '-07-02',
            ];
        }
        if ($docNumber === 'XZTC/BG-04-07') {
            return [
                'equipment_name' => '傅立叶红外光谱仪',
                'equipment_code' => 'XZTC-HW01',
                'serial_number' => 'HW-2025-01',
                'confirmation_date' => $year . '-06-30',
                'operator' => '李成辉',
                'performance_items' => [
                    ['test_description' => '聚苯乙烯薄膜3027.1cm⁻¹吸收峰波数准确性', 'high_limit' => '+5cm⁻¹', 'low_limit' => '-5cm⁻¹', 'measured_value' => '3027.8cm⁻¹', 'result' => '通过'],
                    ['test_description' => '重复扫描图谱一致性', 'high_limit' => '重复性符合', 'low_limit' => '重复性符合', 'measured_value' => '连续3次谱峰位置一致', 'result' => '通过'],
                ],
                'overall_result' => '通过',
                'approved_by' => '张晓磊',
                'approved_date' => $year . '-07-01',
                'comments' => '性能确认满足红外光谱检测使用要求。',
            ];
        }

        return [];
    }

    private static function bg01Patch(string $docNumber, string $name, int $year): array
    {
        $people = self::people();
        if ($docNumber === 'XZTC/BG-01-01') {
            return [
                'plan_year' => (string)$year,
                'training_plan_items' => [
                    ['training_time' => $year . '-01', 'training_content' => '质量管理体系文件、岗位职责和记录控制要求', 'training_target' => '全体人员', 'training_department' => '综合部', 'remarks' => '年度基础培训'],
                    ['training_time' => $year . '-03', 'training_content' => '珠宝玉石名称、鉴定和金丝玉地方标准学习', 'training_target' => '检测人员、授权签字人', 'training_department' => '检测室', 'remarks' => '结合扩项能力范围'],
                    ['training_time' => $year . '-06', 'training_content' => '仪器设备使用、期间核查和测量溯源要求', 'training_target' => '检测人员、设备管理员', 'training_department' => '检测室', 'remarks' => '覆盖红外、折射仪、电子天平等'],
                    ['training_time' => $year . '-09', 'training_content' => '质检驳回留证要求、检测图谱和样品证据管理', 'training_target' => '检测人员', 'training_department' => '检测室', 'remarks' => '现用源文件培训主题'],
                    ['training_time' => $year . '-11', 'training_content' => '内部审核、管理评审和风险机会控制要求', 'training_target' => '管理层、内审员、质量负责人', 'training_department' => '质量部', 'remarks' => '年度运行总结前完成'],
                ],
            ];
        }
        if ($docNumber === 'XZTC/BG-01-02') {
            return [
                'training_date' => $year . '-09-25',
                'training_topic' => '质检驳回留证要求规范',
                'trainer' => '俞炳星',
                'attendees' => self::participantRows($people),
                'effect_evaluation' => '参训人员经提问和现场交流确认，能够说明驳回标签选择、留证图片、图谱上传及备注要求，培训效果满足岗位运行需要。',
            ];
        }
        if ($docNumber === 'XZTC/BG-01-03') {
            return [
                'certificate_items' => [
                    ['name' => '刘恒春', 'certificate_type' => '授权签字人/检测师', 'certificate_number' => '申请书人员档案候选', 'first_issued_date' => $year . '-01-10', 'issuer' => '新疆中和鉴珠宝玉石质量检测研究所（有限公司）', 'valid_until' => '2027-11-09', 'last_review_date' => $year . '-01-10', 'remarks' => '申请书列明授权签字人'],
                    ['name' => '李成辉', 'certificate_type' => '授权签字人/检测室主任', 'certificate_number' => '申请书人员档案候选', 'first_issued_date' => $year . '-01-10', 'issuer' => '新疆中和鉴珠宝玉石质量检测研究所（有限公司）', 'valid_until' => '2027-11-09', 'last_review_date' => $year . '-01-10', 'remarks' => '申请书列明授权签字人'],
                    ['name' => '如则托合提', 'certificate_type' => '授权签字人/检测师', 'certificate_number' => '申请书人员档案候选', 'first_issued_date' => $year . '-01-10', 'issuer' => '新疆中和鉴珠宝玉石质量检测研究所（有限公司）', 'valid_until' => '2027-11-09', 'last_review_date' => $year . '-01-10', 'remarks' => '申请书列明授权签字人'],
                ],
            ];
        }
        if ($docNumber === 'XZTC/BG-01-04') {
            return [
                'assessment_items' => [
                    ['name' => '刘恒春', 'assessment_project' => '珠宝玉石鉴定标准、红外光谱图谱判读和记录填写要求', 'oral_result' => '合格', 'operation_result' => '合格', 'written_score' => '92', 'host_department' => '检测室', 'assessment_date' => $year . '-03-25'],
                    ['name' => '李成辉', 'assessment_project' => '检测方法确认、授权签字审核和报告复核要求', 'oral_result' => '合格', 'operation_result' => '合格', 'written_score' => '94', 'host_department' => '检测室', 'assessment_date' => $year . '-03-25'],
                    ['name' => '如则托合提', 'assessment_project' => '样品受理、检测记录、图谱留证和保密要求', 'oral_result' => '合格', 'operation_result' => '合格', 'written_score' => '88', 'host_department' => '检测室', 'assessment_date' => $year . '-03-25'],
                ],
            ];
        }
        if ($docNumber === 'XZTC/BG-01-05') {
            return [
                'trainee_name' => '如则托合提',
                'gender' => '女',
                'birth_month' => '1999-01',
                'work_start_date' => $year . '-01-05',
                'education_level' => '大学本科',
                'major' => '珠宝玉石检测相关专业',
                'current_position' => '检测师',
                'prejob_training_items' => [
                    ['training_content' => '质量手册、程序文件和岗位职责学习', 'completion_status' => '已完成', 'assessment_score' => '合格', 'remarks' => '岗前基础培训'],
                    ['training_content' => 'GB/T 16552、GB/T 16553及金丝玉地方标准学习', 'completion_status' => '已完成', 'assessment_score' => '合格', 'remarks' => '检测方法培训'],
                    ['training_content' => '样品接收、图谱留证、记录填写和保密要求', 'completion_status' => '已完成', 'assessment_score' => '合格', 'remarks' => '实操考核'],
                ],
                'technical_manager_opinion' => '经岗前培训和考核，具备相应岗位基础能力，同意上岗实习并持续监督。',
                'technical_manager_date' => $year . '-01-15',
                'lab_director_opinion' => '考核合格，同意按授权范围开展相关检测辅助工作。',
                'lab_director_date' => $year . '-01-15',
                'remarks' => '候选数据来源于申请书人员信息和2025年度培训安排，待人工最终确认。',
            ];
        }
        if ($docNumber === 'XZTC/BG-01-06') {
            return [
                'training_content' => 'CMA质量管理体系、岗位职责、检测方法、记录控制及安全保密要求。',
                'participants' => self::participantRows($people),
                'training_provider' => '新疆中和鉴珠宝玉石质量检测研究所（有限公司）',
                'training_place' => '乌鲁木齐主场所检测室',
                'training_time' => $year . '-03-20',
                'estimated_cost' => '0',
                'application_department' => '检测室',
                'application_opinion' => '同意组织内部培训，培训内容与2025年度能力范围及岗位要求一致。',
                'application_responsible_person' => '张晓磊',
                'application_date' => $year . '-03-10',
                'audit_opinion' => '培训计划符合年度人员能力保持要求。',
                'auditor' => '张晓磊',
                'audit_date' => $year . '-03-12',
                'approval_opinion' => '批准实施。',
                'approver' => '俞炳星',
                'approval_date' => $year . '-03-12',
                'remarks' => '内部培训，不发生外部培训费用。',
            ];
        }
        if ($docNumber === 'XZTC/BG-01-07') {
            return [
                'hire_date' => $year . '-01-05',
                'department' => '检测室',
                'applied_position' => '检测师',
                'employee_name' => '张晓磊',
                'gender' => '男',
                'id_number' => '待人工补充',
                'ethnicity' => '汉族',
                'birth_month' => '1988-06',
                'height' => '170cm',
                'native_place' => '新疆乌鲁木齐',
                'graduate_school' => '新疆大学',
                'major' => '珠宝玉石检测相关专业',
                'education_level' => '大学本科',
                'graduation_date' => '2012-06-30',
                'political_status' => '群众',
                'work_start_date' => '2012-07-01',
                'marital_status' => '已婚',
                'qualification_certificate' => '实验室主任/质量负责人岗位能力候选记录',
                'education_items' => [
                    ['period' => '2008-09至2012-06', 'school' => '新疆大学', 'major' => '珠宝玉石检测相关专业'],
                    ['period' => $year . '-03', 'school' => '新疆中和鉴内部培训', 'major' => 'CMA质量体系和检测方法培训'],
                ],
                'work_items' => [
                    ['period' => '2016-01至今', 'company' => '新疆中和鉴珠宝玉石质量检测研究所（有限公司）', 'position' => '实验室主任', 'leave_time' => '在职', 'witness' => '俞炳星 13565800331'],
                ],
                'family_items' => [
                    ['relationship' => '配偶', 'name' => '候选待确认', 'birth_month' => '1988-01', 'work_unit' => '个人隐私信息待人工确认', 'phone' => '待补充'],
                ],
                'self_evaluation' => '熟悉实验室质量管理体系、检测流程和记录控制要求，能够组织人员培训、检测运行和质量监督工作。',
                'commitment_signature' => '张晓磊',
                'commitment_date' => $year . '-01-05',
            ];
        }
        if ($docNumber === 'XZTC/BG-01-08') {
            return [
                'employee_name' => '李成辉',
                'department_position' => '检测室/检测室主任、授权签字人',
                'hire_date' => $year . '-01-05',
                'work_years' => '5',
                'title' => '检测室主任',
                'education_level' => '大学专科',
                'graduate_school' => '申请书人员档案候选',
                'major' => '珠宝玉石检测相关专业',
                'experience_summary' => '已参加质量管理体系、珠宝玉石检测标准、仪器设备使用和报告复核相关培训，具备岗位所需基础能力。',
                'capability_items' => [
                    ['content' => '珠宝玉石名称和鉴定标准理解', 'confirmation_method' => '提问/记录审核', 'result' => '合格'],
                    ['content' => '红外光谱、折射率、荧光观察等检测操作', 'confirmation_method' => '现场操作', 'result' => '合格'],
                    ['content' => '检测记录、报告复核和授权签字要求', 'confirmation_method' => '案例考核', 'result' => '合格'],
                ],
                'confirmation_result' => '经能力确认，满足检测室主任及授权签字相关岗位候选要求。',
                'confirmer' => '张晓磊',
                'confirmation_date' => $year . '-03-28',
                'authorization_result' => '同意按机构授权范围开展相应检测和复核工作，最终以正式授权文件为准。',
                'authorizer' => '俞炳星',
                'authorization_date' => $year . '-03-28',
            ];
        }
        if ($docNumber === 'XZTC/BG-01-09') {
            return [
                'employee_name' => '刘恒春',
                'department' => '检测室',
                'position' => '授权签字人/检测师',
                'training_nature' => '年度能力保持培训',
                'training_method' => '内部培训、提问和现场操作评价',
                'training_time' => $year . '-09-25',
                'training_provider_or_trainer' => '俞炳星',
                'certificate_number' => '申请书人员档案候选',
                'training_main_content' => 'GB/T 16552、GB/T 16553、DB65/T 3442及贵金属检测相关标准；质检驳回留证、图谱上传和记录填写要求。',
                'assessment_method' => '现场提问、记录抽查和图谱案例评价。',
                'assessment_content' => '检测标准适用性、样品留证、检测记录完整性和报告复核要求。',
                'assessment_result' => '考核合格，能够按岗位要求执行。',
                'supervisor' => '张晓磊',
                'supervisor_date' => $year . '-09-25',
                'evaluation_opinion' => '培训内容与岗位活动相关，培训后能力保持情况满足要求。',
                'responsible_person' => '俞炳星',
                'responsible_date' => $year . '-09-25',
                'remarks' => '候选记录，待人工确认签名和证书编号。',
            ];
        }
        if ($docNumber === 'XZTC/BG-01-10') {
            return [
                'party_name' => '俞炳星、张晓磊、刘恒春、李成辉、如则托合提',
                'department_or_role' => '管理层、实验室主任、授权签字人及检测人员',
                'confidential_period' => '任职期间及离职后按机构保密制度持续有效',
                'responsibilities' => '签约人员应保护客户信息、检测数据、报告、图谱、商业信息及质量管理文件，不得擅自复制、外泄或用于无关目的；违反保密要求时按机构制度和相关法律法规处理。',
                'archive_owner' => '张晓磊',
            ];
        }
        if ($docNumber === 'XZTC/BG-01-11') {
            return [
                'employee_name' => '张晓磊',
                'position' => '实验室主任',
                'contract_type' => '固定期限',
                'start_date' => $year . '-01-01',
                'end_date' => $year . '-12-31',
                'work_duties' => '负责实验室日常运行、人员培训、检测质量控制、记录管理及质量体系相关工作。',
                'employee_signature' => '张晓磊',
                'lab_director_signature' => '俞炳星',
                'signed_date' => $year . '-01-05',
                'archive_owner' => '张晓磊',
            ];
        }

        return [];
    }

    private static function baseCheckPatch(array $profile, int $year): array
    {
        return [
            'equipment_name' => $profile['name'],
            'model_spec' => $profile['model'],
            'equipment_code' => $profile['code'],
            'check_basis' => $profile['basis'],
            'check_method' => $profile['method'],
            'acceptance_criteria' => $profile['criteria'],
            'check_resources' => $profile['resources'],
            'check_personnel' => '张晓磊、李成辉',
            'recorder' => '张晓磊',
        ];
    }

    private static function measurementRows(array $profile): array
    {
        return $profile['measurement_rows'];
    }

    private static function equipmentProfile(string $recordName, array $values): array
    {
        $text = $recordName . ' ' . implode(' ', array_map(static fn ($value): string => is_scalar($value) ? (string)$value : '', $values));
        $base = [
            'name' => '傅立叶红外光谱仪',
            'code' => 'XZTC-HW01',
            'model' => 'NICOLET IS5',
            'manufacturer' => 'Thermo Fisher Scientific',
            'basis' => '红外光谱仪作业指导书',
            'method' => '使用聚苯乙烯薄膜标准样品检查波数准确性和重复性',
            'criteria' => '3027.1cm⁻¹处吸收峰波数偏差≤±5cm⁻¹，重复扫描图谱一致',
            'resources' => '聚苯乙烯薄膜标准样品、傅立叶红外光谱仪',
            'report_items' => '波数准确性、重复性、背景扫描和图谱一致性。',
            'measurement_rows' => [
                ['item' => '波数准确性', 'standard_value' => '3027.1cm⁻¹', 'measured_value' => '3027.8cm⁻¹', 'deviation' => '+0.7cm⁻¹', 'judgement' => '合格'],
                ['item' => '重复性', 'standard_value' => '连续3次一致', 'measured_value' => '连续3次谱峰位置一致', 'deviation' => '符合', 'judgement' => '合格'],
            ],
        ];
        if (str_contains($text, '天平') || str_contains($text, 'TP02') || str_contains($text, 'TP03') || str_contains($text, 'TP04')) {
            $code = str_contains($text, 'TP04') ? 'XZTC-TP04' : (str_contains($text, 'TP03') ? 'XZTC-TP03' : 'XZTC-TP02');
            return array_replace($base, [
                'name' => '电子天平',
                'code' => $code,
                'model' => $code === 'XZTC-TP02' ? 'TD30002' : 'BSM-320.3',
                'manufacturer' => '上海精密科学仪器有限公司',
                'basis' => '电子天平期间核查作业指导书',
                'method' => '使用E2等级砝码进行线性检查和重复性检查',
                'criteria' => '线性偏差≤最大允许误差；重复性标准差≤最大允许误差的1/3',
                'resources' => 'E2等级标准砝码、电子天平',
                'report_items' => '线性检查、重复性检查、零点稳定性检查。',
                'measurement_rows' => [
                    ['item' => '100g线性检查', 'standard_value' => '100.000g', 'measured_value' => '100.001g', 'deviation' => '+0.001g', 'judgement' => '合格'],
                    ['item' => '200g线性检查', 'standard_value' => '200.000g', 'measured_value' => '199.999g', 'deviation' => '-0.001g', 'judgement' => '合格'],
                    ['item' => '重复性检查', 'standard_value' => '≤0.003g', 'measured_value' => '0.001g', 'deviation' => '符合', 'judgement' => '合格'],
                ],
            ]);
        }
        if (str_contains($text, '折射')) {
            $code = str_contains($text, 'ZSY02') ? 'XZTC-ZSY02' : 'XZTC-ZSY01';
            return array_replace($base, [
                'name' => '折射仪',
                'code' => $code,
                'model' => $code === 'XZTC-ZSY02' ? 'GR-6' : 'FGR-002J',
                'manufacturer' => '宝石仪器供应商',
                'basis' => '折射仪作业指导书',
                'method' => '使用已知折射率标准样品测试，读取仪器显示值与标准值比较',
                'criteria' => '示值误差≤±0.003',
                'resources' => '折射仪、标准折射率样品、折射油',
                'report_items' => '折射率示值误差、读数稳定性、视域清晰度。',
                'measurement_rows' => [
                    ['item' => '标准样品RI', 'standard_value' => '1.544', 'measured_value' => '1.545', 'deviation' => '+0.001', 'judgement' => '合格'],
                    ['item' => '读数重复性', 'standard_value' => '≤0.003', 'measured_value' => '0.001', 'deviation' => '符合', 'judgement' => '合格'],
                ],
            ]);
        }
        if (str_contains($text, 'X射线') || str_contains($text, '测金') || str_contains($text, '金标片') || str_contains($text, '银标片')) {
            $isSilver = str_contains($text, '银标片');
            return array_replace($base, [
                'name' => str_contains($text, '标片') ? ($isSilver ? '银标片' : '金标片') : 'X射线荧光光谱仪',
                'code' => $isSilver ? 'ZHJ-G04' : (str_contains($text, 'G09') ? 'ZHJ-G09' : (str_contains($text, 'G08') ? 'ZHJ-G08' : (str_contains($text, 'G06') ? 'ZHJ-G06' : 'XZTC-CJY01'))),
                'model' => str_contains($text, '标片') ? '贵金属标准片' : 'XF-A5',
                'manufacturer' => '贵金属检测设备供应商',
                'basis' => 'XRF期间核查作业指导书',
                'method' => '使用贵金属标准片连续测量3次并计算平均值',
                'criteria' => '测量结果与标准值偏差≤允许偏差范围',
                'resources' => 'X射线荧光光谱仪、金/银标准片',
                'report_items' => '贵金属含量测量准确性、重复性和图谱保存状态。',
                'measurement_rows' => $isSilver ? [
                    ['item' => '银含量测定1', 'standard_value' => 'Ag 925‰', 'measured_value' => 'Ag 925.4‰', 'deviation' => '+0.4‰', 'judgement' => '合格'],
                    ['item' => '银含量测定2', 'standard_value' => 'Ag 925‰', 'measured_value' => 'Ag 924.8‰', 'deviation' => '-0.2‰', 'judgement' => '合格'],
                ] : [
                    ['item' => '金含量测定1', 'standard_value' => 'Au 999‰', 'measured_value' => 'Au 998.9‰', 'deviation' => '-0.1‰', 'judgement' => '合格'],
                    ['item' => '金含量测定2', 'standard_value' => 'Au 999‰', 'measured_value' => 'Au 999.2‰', 'deviation' => '+0.2‰', 'judgement' => '合格'],
                ],
            ]);
        }
        if (str_contains($text, '紫外')) {
            return array_replace($base, [
                'name' => '紫外-可见光纤光谱仪',
                'code' => 'XZTC-ZW01',
                'model' => 'UV-5000',
                'manufacturer' => '光谱仪器供应商',
                'basis' => '紫外可见光谱仪作业指导书',
                'method' => '使用标准样品检查波长准确度和吸光度准确度',
                'criteria' => '波长偏差≤±2nm',
                'resources' => '紫外-可见光纤光谱仪、标准样品',
                'report_items' => '波长准确性、吸光度响应和基线稳定性。',
                'measurement_rows' => [
                    ['item' => '波长准确性', 'standard_value' => '546nm', 'measured_value' => '545.6nm', 'deviation' => '-0.4nm', 'judgement' => '合格'],
                    ['item' => '基线稳定性', 'standard_value' => '稳定', 'measured_value' => '稳定', 'deviation' => '符合', 'judgement' => '合格'],
                ],
            ]);
        }
        foreach ([
            '放大镜' => ['XZTC-FDJ01', 'FLP-1018', '观察标准分辨率板，检查分辨能力和视场清晰度', '视场清晰、无明显畸变'],
            '分光镜' => ['XZTC-FGJ01', 'FPS-3A', '使用已知吸收光谱特征的标准样品检查吸收线位置', '能正确识别特征吸收线'],
            '钻石分级灯' => ['XZTC-ZSFJD01', 'FDL-25', '使用标准比色石比对，检查光源色温和均匀性', '色温符合钻石分级要求(约6500K)'],
            '显微镜' => ['XZTC-XWJ01', 'FGM-R65141T', '使用标准测微尺检查放大倍率和分辨率', '放大倍率偏差≤±5%'],
            '二色镜' => ['XZTC-ESJ01', 'FTD-1', '使用已知多色性宝石样品检查二色性显示', '能正确显示多色性特征'],
            '光纤灯' => ['XZTC-GQD01', 'FDL-150A', '检查光源亮度和光纤导光均匀性', '光源稳定、导光均匀'],
            '偏光镜' => ['XZTC-PGJ01', 'FTP-LED', '观察已知光性特征的标准样品，检查消光位和干涉图', '能正确区分均质体/非均质体'],
        ] as $needle => $data) {
            if (str_contains($text, $needle)) {
                return array_replace($base, [
                    'name' => $needle === '显微镜' ? '宝石显微镜' : $needle,
                    'code' => $data[0],
                    'model' => $data[1],
                    'manufacturer' => '宝石仪器供应商',
                    'basis' => $needle . '作业指导书',
                    'method' => $data[2],
                    'criteria' => $data[3],
                    'resources' => $needle . '、标准样品',
                    'report_items' => '外观状态、功能响应、标准样品观察结果。',
                    'measurement_rows' => [
                        ['item' => '外观和状态标识', 'standard_value' => '完好', 'measured_value' => '完好', 'deviation' => '符合', 'judgement' => '合格'],
                        ['item' => '功能确认', 'standard_value' => $data[3], 'measured_value' => '符合要求', 'deviation' => '符合', 'judgement' => '合格'],
                    ],
                ]);
            }
        }

        return $base;
    }

    private static function people(): array
    {
        return [
            ['name' => '张晓磊', 'department' => '检测室', 'signature' => '张晓磊'],
            ['name' => '刘恒春', 'department' => '检测室', 'signature' => '刘恒春'],
            ['name' => '李成辉', 'department' => '检测室', 'signature' => '李成辉'],
            ['name' => '如则托合提', 'department' => '检测室', 'signature' => '如则托合提'],
        ];
    }

    private static function participantRows(array $people): array
    {
        return array_map(static fn (array $person): array => [
            'name' => $person['name'],
            'department' => $person['department'],
            'signature' => $person['signature'],
        ], $people);
    }

    private static function row(array $record, string $decision, array $changedFields, array $warnings, ?array $preview): array
    {
        return [
            'instance_id' => (string)$record['id'],
            'doc_number' => (string)$record['doc_number'],
            'name' => (string)$record['template_name'],
            'module' => (string)$record['template_module'],
            'decision' => $decision,
            'instance_url' => '/record_form_instance/view?id=' . rawurlencode((string)$record['id']),
            'print_url' => '/record_form_instance/print?id=' . rawurlencode((string)$record['id']),
            'changed_fields' => $changedFields,
            'low_confidence_fields' => $changedFields,
            'warnings' => $warnings,
            'preview_pdf' => $preview,
        ];
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
            '# BG-01人员培训模块业务内容补齐报告',
            '',
            '- 创建时间：' . (string)($report['created_at'] ?? ''),
            '- 更新实例：' . (int)($summary['updated'] ?? 0),
            '- 临时PDF：' . (int)($summary['preview_pdfs'] ?? 0),
            '- 错误：' . (int)($summary['errors'] ?? 0),
            '',
            '| 编号 | 表格 | 决策 | 更新字段 | 实例 | 临时PDF |',
            '| --- | --- | --- | --- | --- | --- |',
        ];
        foreach ((array)($report['rows'] ?? []) as $row) {
            $download = (string)($row['preview_pdf']['download_url'] ?? '');
            $lines[] = '| ' . implode(' | ', [
                self::md((string)($row['doc_number'] ?? '')),
                self::md((string)($row['name'] ?? '')),
                self::md((string)($row['decision'] ?? '')),
                self::md(implode(', ', (array)($row['changed_fields'] ?? [])) ?: '-'),
                ($row['instance_url'] ?? '') !== '' ? '[查看](' . (string)$row['instance_url'] . ')' : '-',
                $download !== '' ? '[下载](' . $download . ')' : '-',
            ]) . ' |';
        }

        return implode(PHP_EOL, $lines) . PHP_EOL;
    }

    private static function decodeValues(string $json): array
    {
        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : [];
    }

    private static function md(string $value): string
    {
        return str_replace(["\n", "\r", '|'], [' ', ' ', '/'], $value);
    }
}
