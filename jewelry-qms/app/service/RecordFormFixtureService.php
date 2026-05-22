<?php
declare(strict_types=1);

namespace app\service;

use app\model\RecordFormTemplate;

class RecordFormFixtureService
{
    public static function seed(): int
    {
        $count = 0;
        foreach (self::templates() as $row) {
            $existing = RecordFormTemplate::where('doc_number', $row['doc_number'])
                ->where('soft_delete', 0)
                ->find();
            $row['field_schema'] = RecordFormSchemaService::encode($row['field_schema']);

            if ($existing) {
                $existing->save($row);
            } else {
                $row['id'] = qms_uuid();
                RecordFormTemplate::create($row);
            }

            $count++;
        }

        return $count;
    }

    public static function templates(): array
    {
        return [
            [
                'doc_number' => 'XZTC/BG-01-02',
                'name' => '人员培训记录表',
                'module' => '人员培训程序',
                'print_template_key' => 'training_record',
                'version' => 'A/0',
                'status' => 'published',
                'field_schema' => [
                    ['key' => 'training_date', 'label' => '培训日期', 'type' => 'date', 'required' => true],
                    ['key' => 'training_topic', 'label' => '培训主题', 'type' => 'text', 'required' => true],
                    ['key' => 'trainer', 'label' => '培训讲师', 'type' => 'text', 'required' => true],
                    ['key' => 'training_content', 'label' => '培训内容', 'type' => 'textarea', 'required' => false],
                    ['key' => 'attendees', 'label' => '参训人员', 'type' => 'repeatable_table', 'columns' => [
                        ['key' => 'name', 'label' => '姓名', 'type' => 'text', 'required' => true],
                        ['key' => 'department', 'label' => '部门', 'type' => 'text', 'required' => false],
                        ['key' => 'signature', 'label' => '签名', 'type' => 'signature', 'required' => false],
                    ]],
                    ['key' => 'effect_evaluation', 'label' => '效果评价', 'type' => 'textarea', 'required' => false],
                ],
            ],
            [
                'doc_number' => 'XZTC/BG-04-03',
                'name' => '仪器设备和标准物质期间核查记录表',
                'module' => '仪器设备和标准物质期间核查程序',
                'print_template_key' => 'periodic_check',
                'version' => 'A/0',
                'status' => 'published',
                'field_schema' => [
                    ['key' => 'equipment_name', 'label' => '设备或标准物质名称', 'type' => 'text', 'required' => true],
                    ['key' => 'equipment_code', 'label' => '编号', 'type' => 'text', 'required' => true],
                    ['key' => 'check_date', 'label' => '核查日期', 'type' => 'date', 'required' => true],
                    ['key' => 'check_items', 'label' => '核查项目', 'type' => 'repeatable_table', 'columns' => [
                        ['key' => 'item', 'label' => '项目', 'type' => 'text', 'required' => true],
                        ['key' => 'method', 'label' => '方法', 'type' => 'text', 'required' => false],
                        ['key' => 'result', 'label' => '结果', 'type' => 'text', 'required' => true],
                        ['key' => 'conclusion', 'label' => '结论', 'type' => 'select', 'options' => ['合格', '不合格'], 'required' => true],
                    ]],
                    ['key' => 'checker', 'label' => '核查人', 'type' => 'text', 'required' => true],
                ],
            ],
            [
                'doc_number' => 'XZTC/BG-20-07',
                'name' => '现场检测能力审核记录表',
                'module' => '内部管理体系审核程序',
                'print_template_key' => 'audit_checklist',
                'version' => 'A/0',
                'status' => 'published',
                'field_schema' => [
                    ['key' => 'audit_date', 'label' => '审核日期', 'type' => 'date', 'required' => true],
                    ['key' => 'audited_department', 'label' => '受审核部门', 'type' => 'text', 'required' => true],
                    ['key' => 'auditor', 'label' => '审核员', 'type' => 'text', 'required' => true],
                    ['key' => 'check_items', 'label' => '检查内容', 'type' => 'repeatable_table', 'columns' => [
                        ['key' => 'clause', 'label' => '条款', 'type' => 'text', 'required' => true],
                        ['key' => 'requirement', 'label' => '检查要求', 'type' => 'textarea', 'required' => true],
                        ['key' => 'evidence', 'label' => '审核证据', 'type' => 'textarea', 'required' => false],
                        ['key' => 'result', 'label' => '结果', 'type' => 'select', 'options' => ['符合', '不符合', '观察项'], 'required' => true],
                    ]],
                ],
            ],
            [
                'doc_number' => 'XZTC/BG-21-01',
                'name' => '管理评审计划表',
                'module' => '管理评审程序',
                'print_template_key' => 'management_review_plan',
                'version' => 'A/0',
                'status' => 'published',
                'field_schema' => [
                    ['key' => 'review_year', 'label' => '评审年度', 'type' => 'text', 'required' => true],
                    ['key' => 'meeting_date', 'label' => '会议日期', 'type' => 'date', 'required' => true],
                    ['key' => 'host', 'label' => '主持人', 'type' => 'text', 'required' => true],
                    ['key' => 'participants', 'label' => '参加人员', 'type' => 'textarea', 'required' => true],
                    ['key' => 'inputs', 'label' => '评审输入', 'type' => 'repeatable_table', 'columns' => [
                        ['key' => 'topic', 'label' => '输入主题', 'type' => 'text', 'required' => true],
                        ['key' => 'owner', 'label' => '责任人', 'type' => 'text', 'required' => false],
                        ['key' => 'material', 'label' => '资料要求', 'type' => 'textarea', 'required' => false],
                    ]],
                ],
            ],
            [
                'doc_number' => 'XZTC/BG-30-05',
                'name' => '内部质量监控记录表',
                'module' => '检测结果质量控制及能力验证程序',
                'print_template_key' => 'quality_control_record',
                'version' => 'A/0',
                'status' => 'published',
                'field_schema' => [
                    ['key' => 'monitor_date', 'label' => '监控日期', 'type' => 'date', 'required' => true],
                    ['key' => 'monitor_type', 'label' => '监控类型', 'type' => 'select', 'options' => ['留样再测', '人员比对', '设备比对', '标准物质核查', '能力验证'], 'required' => true],
                    ['key' => 'sample_info', 'label' => '样品或项目信息', 'type' => 'textarea', 'required' => true],
                    ['key' => 'results', 'label' => '监控结果', 'type' => 'repeatable_table', 'columns' => [
                        ['key' => 'item', 'label' => '项目', 'type' => 'text', 'required' => true],
                        ['key' => 'expected', 'label' => '预期或参考值', 'type' => 'text', 'required' => false],
                        ['key' => 'actual', 'label' => '实测结果', 'type' => 'text', 'required' => true],
                        ['key' => 'judgement', 'label' => '判定', 'type' => 'select', 'options' => ['满意', '可疑', '不满意'], 'required' => true],
                    ]],
                    ['key' => 'follow_up', 'label' => '后续措施', 'type' => 'textarea', 'required' => false],
                ],
            ],
        ];
    }
}
