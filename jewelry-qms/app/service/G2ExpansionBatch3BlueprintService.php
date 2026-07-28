<?php
declare(strict_types=1);

namespace app\service;

final class G2ExpansionBatch3BlueprintService
{
    public const PRINT_TEMPLATE_KEY = 'g2_expansion_batch3_record';

    public static function templates(): array
    {
        return [
            self::template('XZTC/BG-01-01', '年度人员培训计划表', '人员培训程序', '甲组|人员与监督', [
                self::table('training_plan_items', '培训计划明细', [
                    ['label' => '培训时间', 'type' => 'month'],
                    '培训内容',
                    ['label' => '培训对象', 'type' => 'person', 'multiple' => true],
                    ['label' => '培训部门', 'type' => 'department', 'multiple' => true],
                    '预期目标',
                    '完成情况回填',
                ]),
            ]),
            self::template('XZTC/BG-01-03', '检测人员持证登记表', '人员培训程序', '甲组|人员与监督', [
                self::table('certificate_items', '持证登记明细', ['姓名', '证别', '证书号码', '发证单位', '有效期', '证书复审提醒期', '备注']),
            ]),
            self::template('XZTC/BG-01-04', '人员考核记录表', '人员培训程序', '甲组|人员与监督', [
                self::table('assessment_items', '考核记录明细', ['姓名', '考核项目', '考核方式', '考核时间', '考核成绩', '考核结论(合格/需再培训)', '备注']),
            ]),
            self::template('XZTC/BG-01-05', '岗前培训考核记录表', '人员培训程序', '甲组|人员与监督', [
                self::field('necessary_identity_items', '个人必要项(敏感信息最小化)', 'textarea'),
                self::field('confidential_archive_note', '档案联保密存放注', 'textarea'),
                self::table('prejob_items', '岗前培训考核明细', ['培训内容', '完成情况', '考核结果', '考核结论', '备注']),
            ]),
            self::template('XZTC/BG-01-07', '人员档案登记表', '人员管理程序', '甲组|人员与监督', [
                self::field('technical_archive_index', '技术档案要素索引(教育/培训/资格确认/授权/监督)', 'textarea'),
                self::table('archive_items', '技术档案索引明细', ['要素', '证据名称', '证据编号/日期', '存放位置', '更新状态']),
            ]),
            self::template('XZTC/BG-01-08', '人员能力确认表', '人员管理程序', '甲组|人员与监督', [
                self::field('blank_master_note', '空白母版重制说明', 'textarea'),
                self::field('archived_filled_forms_note', '已填件归档B1说明', 'textarea'),
                self::table('capability_items', '能力确认明细', ['姓名', '确认项目', '确认方式', '确认结果', '授权范围(方法)', '授权范围(场所)', '有效期']),
            ], [
                'master_note' => '库内现件为已填件(含真实人员信息)，已填件归档B1；本表为空白母版重制。',
            ]),
            self::template('XZTC/BG-01-09', '人员培训评价表', '人员培训程序', '甲组|人员与监督', [
                self::field('training_topic', '培训主题'),
                self::field('expected_goal_reference', '对照01-06预期目标的达成评价', 'textarea'),
                self::field('evaluation_conclusion', '评价结论', 'textarea'),
            ]),
            self::template('XZTC/BG-31-01', '年度监督计划', '人员监督程序', '甲组|人员与监督', [
                self::table('supervision_plan_items', '监督计划明细', ['监督内容', '监督频率', '监督员分工(俞总监督/曹乌市/李和田)', '计划月份', '备注']),
            ]),
            self::template('XZTC/BG-31-02', '日常监督记录', '人员监督程序', '甲组|人员与监督', [
                self::field('supervised_person', '被监督人'),
                self::field('supervision_findings', '监督发现', 'textarea'),
                self::field('handling_and_verification', '处理及验证', 'textarea'),
                self::field('supervisor_signature', '监督员签名'),
            ]),
            self::template('XZTC/BG-30-01', '质控计划', '检测结果质量保证程序', '乙组|质控与方法', [
                self::table('quality_control_plan_items', '质控计划明细', ['项目', '监控方法选项', '频次', '责任人', '计划时间']),
            ], [
                'correction_note' => '留样复测改为客户再送样复测/影像比对复核；留样复测仅限N10例外暂存样品。',
            ]),
            self::template('XZTC/BG-30-05', '内部质量监控记录表', '检测结果质量保证程序', '乙组|质控与方法', [
                self::field('monitoring_item', '监控项目'),
                self::field('implementation_record', '实施记录', 'textarea'),
                self::field('result_evaluation_conclusion', '结果评价与结论(满意/可疑/不满意及措施)', 'textarea'),
            ], [
                'correction_note' => '30-am：撤回新建BG-30-03实施及评价记录表；改为修订BG-30-05补结果评价与结论栏。',
            ]),
            self::template('XZTC/BG-30-06', '监控结果报告', '检测结果质量保证程序', '乙组|质控与方法', [
                self::field('monitoring_summary', '监控结果摘要', 'textarea'),
                self::field('conclusion', '结论', 'textarea'),
                self::field('corrective_action_link', '结论联动纠正措施程序', 'textarea'),
            ]),
            self::template('XZTC/BG-30-02', '异常记录', '检测结果质量保证程序', '乙组|质控与方法', [
                self::field('exception_description', '异常情况描述', 'textarea'),
                self::field('cause_analysis', '原因分析', 'textarea'),
                self::field('handling_result', '处理结果', 'textarea'),
            ]),
            self::template('XZTC/BG-30-03', '能力验证计划表', '检测结果质量保证程序', '乙组|质控与方法', [
                self::field('pt_requirement_note', 'CNAS-RL02申请认可前能力验证要求注记', 'textarea'),
                self::table('pt_plan_items', '能力验证计划明细', ['项目', '提供机构', '计划时间', '参加人员', '状态']),
            ], [
                'correction_note' => 'BG-30-03号已被能力验证计划表占用，不再新建实施及评价记录表。',
            ]),
            self::template('XZTC/BG-30-04', '实验室间比对计划表', '检测结果质量保证程序', '乙组|质控与方法', [
                self::table('comparison_plan_items', '实验室间比对计划明细', ['比对项目', '比对机构', '计划时间', '负责人', '备注']),
            ]),
            self::template('XZTC/BG-22-01', '非标方法确认记录表', '方法确认与验证程序', '乙组|质控与方法', [
                self::field('method_name', '方法名称'),
                self::field('confirmation_stage', '确认阶段', 'textarea'),
                self::field('verification_stage', '验证阶段', 'textarea'),
                self::field('conclusion', '结论', 'textarea'),
            ]),
            self::template('XZTC/BG-22-02', '标准方法验证记录表', '方法确认与验证程序', '乙组|质控与方法', [
                self::field('standard_method_name', '标准方法名称'),
                self::field('verification_content', '验证内容', 'textarea'),
                self::field('verification_conclusion', '验证结论', 'textarea'),
            ], [
                'terminology_note' => '标准方法用验证，非标方法用确认；“确认”措辞按CL01改“验证”。',
            ]),
            self::template('XZTC/BG-22-03', '现行有效标准清单', '方法确认与验证程序', '乙组|质控与方法', [
                self::table('standard_list_items', '现行有效标准清单明细', ['标准编号', '标准名称', 'CMA在库状态', 'CNAS申请状态', '库属性月查日期与查新人(T3)', '启用证据链索引(N7)', '备注']),
            ]),
            self::template('XZTC/BG-22-04', '标准查新报告', '方法确认与验证程序', '乙组|质控与方法', [
                self::field('search_scope', '查新范围', 'textarea'),
                self::field('search_result', '查新结果', 'textarea'),
                self::field('conclusion', '结论联动22-03更新', 'textarea'),
            ], [
                'correction_note' => '原未编号标准查新报告归位BG-22-04；22-01/02/03已占。',
            ]),
        ];
    }

    public static function sampleValues(string $docNumber, string $usageSite): array
    {
        $siteName = $usageSite === 'hetian' ? '和田' : '乌鲁木齐';
        $values = ['usage_site' => $usageSite];
        foreach (self::templates() as $template) {
            if (($template['doc_number'] ?? '') !== $docNumber) {
                continue;
            }
            foreach ($template['field_schema'] as $field) {
                $key = (string)$field['key'];
                if (($field['type'] ?? '') === 'repeatable_table') {
                    $row = [];
                    foreach (($field['columns'] ?? []) as $column) {
                        $row[(string)$column['key']] = self::sampleText((string)$column['label'], $siteName);
                    }
                    $values[$key] = [$row];
                } else {
                    $values[$key] = self::sampleText((string)$field['label'], $siteName);
                }
            }
        }

        return $values;
    }

    private static function template(string $docNumber, string $name, string $module, string $group, array $schema, array $meta = []): array
    {
        return $meta + [
            'doc_number' => $docNumber,
            'name' => $name,
            'module' => $module,
            'group' => $group,
            'print_template_key' => self::PRINT_TEMPLATE_KEY,
            'version' => 'A/0',
            'status' => 'human_review_approved',
            'review_status' => 'g2_expansion_batch3_modelled',
            'retention' => '不少于6年',
            'field_schema' => $schema,
        ];
    }

    private static function field(string $key, string $label, string $type = 'text'): array
    {
        return ['key' => $key, 'label' => $label, 'type' => $type, 'required' => false];
    }

    private static function table(string $key, string $label, array $columns): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'type' => 'repeatable_table',
            'required' => false,
            'columns' => array_map(static function (string|array $column): array {
                $definition = is_string($column) ? ['label' => $column] : $column;
                $columnLabel = trim((string)($definition['label'] ?? ''));

                return [
                    'key' => (string)($definition['key'] ?? self::keyFromLabel($columnLabel)),
                    'label' => $columnLabel,
                    'type' => (string)($definition['type'] ?? 'text'),
                    'required' => (bool)($definition['required'] ?? false),
                    'multiple' => (bool)($definition['multiple'] ?? false),
                ];
            }, $columns),
        ];
    }

    private static function keyFromLabel(string $label): string
    {
        return 'c_' . substr(hash('sha1', $label), 0, 10);
    }

    private static function sampleText(string $label, string $siteName): string
    {
        return match (true) {
            str_contains($label, '监督员分工') => '俞总监督/曹乌市/李和田',
            str_contains($label, '监控方法选项') => '客户再送样复测/影像比对复核；留样复测仅限N10例外暂存样品',
            str_contains($label, 'CNAS-RL02') => '已按CNAS-RL02申请认可前能力验证要求制定计划',
            str_contains($label, 'CMA在库状态') => '在库',
            str_contains($label, 'CNAS申请状态') => '申请中',
            str_contains($label, '库属性月查') => '2026-07-24/查新人',
            str_contains($label, '启用证据链') => 'N7-EVIDENCE-20260724',
            str_contains($label, '授权范围') => $siteName . '场所/常规检测/2027-07-24',
            str_contains($label, '保存') => '不少于6年',
            default => $siteName . '样例-' . $label,
        };
    }
}
