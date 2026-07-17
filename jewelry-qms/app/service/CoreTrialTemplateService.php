<?php
declare(strict_types=1);

namespace app\service;

use app\model\RecordFormTemplate;
use RuntimeException;

final class CoreTrialTemplateService
{
    /**
     * @return array{total:int,created:int,updated:int,skipped:int,errors:list<string>}
     */
    public static function prepare(): array
    {
        if (!TrialModeService::isEnabled()) {
            throw new RuntimeException('当前环境未开启受控试运行模式');
        }

        $summary = ['total' => 0, 'created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => []];
        foreach (self::definitions() as $definition) {
            $summary['total']++;
            try {
                $data = self::templateData($definition);
                $existing = RecordFormTemplate::where('doc_number', $definition['trial_doc_number'])
                    ->where('soft_delete', 0)
                    ->find();
                if ($existing) {
                    if ((string)$existing->status !== 'draft') {
                        $summary['skipped']++;
                        continue;
                    }
                    $existing->save($data);
                    $summary['updated']++;
                    continue;
                }

                $data['id'] = qms_uuid();
                $data['status'] = 'draft';
                RecordFormTemplate::create($data);
                $summary['created']++;
            } catch (\Throwable $exception) {
                $summary['errors'][] = $definition['canonical_doc_number'] . '：' . $exception->getMessage();
            }
        }

        return $summary;
    }

    /**
     * @return list<array<string,string>>
     */
    public static function definitions(): array
    {
        return [
            self::definition('XZTC/BG-02-01', 'SIM-TPL-BG-02-01', 'equipment_manager', '3年'),
            self::definition('XZTC/BG-05-01', 'SIM-TPL-BG-05-01', 'equipment_manager', '3年'),
            self::definition('XZTC/BG-20-01', 'SIM-TPL-BG-20-01', 'quality_manager', '6年'),
            self::definition('XZTC/BG-20-02', 'SIM-TPL-BG-20-02', 'quality_manager', '6年'),
            self::definition('XZTC/BG-20-06', 'SIM-TPL-BG-20-06', 'quality_manager', '3年'),
            self::definition('XZTC/BG-20-08', 'SIM-TPL-BG-20-08', 'quality_manager', '3年'),
            self::definition('XZTC/BG-31-02', 'SIM-TPL-BG-31-02', 'quality_manager', '3年'),
            self::definition('XZTC/BG-30-01', 'SIM-TPL-BG-30-01', 'technical_manager', '3年'),
            self::definition('XZTC/BG-30-02', 'SIM-TPL-BG-30-02', 'technical_manager', '3年'),
            self::definition('XZTC/BG-30-04', 'SIM-TPL-BG-30-04', 'technical_manager', '6年'),
            self::definition('XZTC/BG-21-02', 'SIM-TPL-MR-REPORT', 'quality_manager', '6年'),
            self::definition('XZTC/BG-19-01', 'SIM-TPL-BG-19-01', 'document_controller', '3年'),
            self::definition('XZTC/BG-19-02', 'SIM-TPL-BG-19-02', 'document_controller', '3年'),
            self::definition('XZTC/BG-19-03', 'SIM-TPL-BG-19-03', 'document_controller', '每年更新，历史版本至少保存3年'),
            self::definition('XZTC/BG-19-04', 'SIM-TPL-BG-19-04', 'document_controller', '每年更新，历史版本至少保存3年'),
        ];
    }

    private static function definition(
        string $canonicalDocNumber,
        string $trialDocNumber,
        string $responsiblePosition,
        string $retentionPeriod
    ): array {
        return [
            'canonical_doc_number' => $canonicalDocNumber,
            'trial_doc_number' => $trialDocNumber,
            'responsible_position_code' => $responsiblePosition,
            'retention_period' => $retentionPeriod,
        ];
    }

    private static function templateData(array $definition): array
    {
        if ($definition['canonical_doc_number'] === 'XZTC/BG-20-06') {
            return self::auditChecklistTemplateData($definition);
        }

        $source = RecordFormTemplate::where('doc_number', $definition['canonical_doc_number'])
            ->where('soft_delete', 0)
            ->order('status', 'desc')
            ->find();
        if (!$source) {
            throw new RuntimeException('未找到可追溯的现行模板');
        }

        return [
            'document_id' => $source->document_id,
            'element_id' => $source->element_id,
            'procedure_doc_id' => $source->procedure_doc_id,
            'doc_number' => $definition['trial_doc_number'],
            'canonical_doc_number' => $definition['canonical_doc_number'],
            'trial_of_template_id' => (string)$source->id,
            'name' => '[试运行] ' . (string)$source->name,
            'module' => (string)$source->module,
            'applicable_sites' => '乌鲁木齐实验室；和田实验室',
            'responsible_position_code' => $definition['responsible_position_code'],
            'retention_period' => $definition['retention_period'],
            'source_file_path' => $source->source_file_path,
            'source_file_name' => $source->source_file_name,
            'source_file_sha1' => $source->source_file_sha1,
            'print_template_key' => (string)$source->print_template_key,
            'field_schema' => (string)$source->field_schema,
            'version' => 'TRIAL/0.1',
            'status' => 'draft',
            'review_status' => 'completed',
            'review_note' => 'G-R14 第一批核心模板；仅供受控试运行，正式编号和发布状态不变。',
            'reviewed_at' => date('Y-m-d H:i:s'),
            'trial_batch' => TrialModeService::trialBatch(),
            'trial_note' => '待责任岗位人工批准进入 trial_ready。',
            'publish' => 1,
            'soft_delete' => 0,
        ];
    }

    private static function auditChecklistTemplateData(array $definition): array
    {
        $reference = RecordFormTemplate::where('doc_number', 'XZTC/BG-20-08')
            ->where('soft_delete', 0)
            ->find();
        if (!$reference) {
            throw new RuntimeException('缺少内审程序追溯基准 XZTC/BG-20-08');
        }

        return [
            'document_id' => null,
            'element_id' => $reference->element_id,
            'procedure_doc_id' => $reference->procedure_doc_id,
            'doc_number' => $definition['trial_doc_number'],
            'canonical_doc_number' => $definition['canonical_doc_number'],
            'trial_of_template_id' => null,
            'name' => '[试运行] 内部审核检查记录表',
            'module' => '内部管理体系审核程序',
            'applicable_sites' => '乌鲁木齐实验室；和田实验室',
            'responsible_position_code' => $definition['responsible_position_code'],
            'retention_period' => $definition['retention_period'],
            'print_template_key' => 'rf_xztc_bg_20_06_gr14',
            'field_schema' => RecordFormSchemaService::encode(self::auditChecklistSchema()),
            'version' => 'TRIAL/0.1',
            'status' => 'draft',
            'review_status' => 'completed',
            'review_note' => '依据 XZTC/CX-20-2022 记录要求重建；正式启用前须核对原表。',
            'reviewed_at' => date('Y-m-d H:i:s'),
            'trial_batch' => TrialModeService::trialBatch(),
            'trial_note' => '缺失模板重建候选，待质量负责人人工批准。',
            'publish' => 1,
            'soft_delete' => 0,
        ];
    }

    private static function auditChecklistSchema(): array
    {
        return [
            self::field('audit_date', '审核日期', 'date', true),
            self::field('audited_site', '受审核场所', 'text', true),
            self::field('audited_department', '受审核部门', 'department', true),
            self::field('auditor', '审核员', 'person', true),
            self::field('audit_scope', '审核范围/依据', 'textarea', true),
            [
                ...self::field('check_items', '检查/发现明细', 'repeatable_table', true),
                'columns' => [
                    self::field('clause', '条款/过程', 'text', true),
                    self::field('requirement', '要求', 'textarea', true),
                    self::field('evidence', '客观证据/事实', 'textarea', true),
                    [
                        ...self::field('result', '结果', 'select', true),
                        'options' => ['符合', '不符合', '观察项', '不适用'],
                    ],
                    self::field('finding_number', '发现编号', 'text', false),
                    self::field('capa_number', '关联 CAPA 编号', 'text', false),
                ],
            ],
            self::field('conclusion', '审核结论/整改要求', 'textarea', true),
        ];
    }

    private static function field(string $key, string $label, string $type, bool $required): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'type' => $type,
            'required' => $required,
            'readonly' => false,
            'default' => '',
            'options' => [],
            'print_bind' => $key,
            'validation' => [],
            'help_text' => '',
        ];
    }
}
