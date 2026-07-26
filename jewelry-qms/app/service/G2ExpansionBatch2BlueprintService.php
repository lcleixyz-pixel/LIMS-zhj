<?php
declare(strict_types=1);

namespace app\service;

final class G2ExpansionBatch2BlueprintService
{
    public const PRINT_TEMPLATE_KEY = 'g2_expansion_batch2_record';

    public static function templates(): array
    {
        return [
            self::template('XZTC/BG-02-01', '检测环境监控记录表', '设施与环境条件控制和维护程序', [
                self::field('monitor_month', '监控月份'),
                self::field('monitor_site', '监控场所'),
                self::field('requirement_value', '要求值预印栏'),
                self::field('daily_monitor_items', '月度逐日监控网格(逐日实测)', 'repeatable_table', [], [
                    self::column('date', '日期'),
                    self::column('temperature', '温度实测'),
                    self::column('humidity', '湿度实测'),
                    self::column('other_condition', '其他环境条件'),
                    self::column('conformity_judgment', '符合性判定'),
                    self::column('exception_disposal', '异常处置记录指引'),
                    self::column('recorder', '记录人'),
                ]),
            ]),
            self::template('XZTC/BG-03-02', '仪器设备使用记录表', '仪器设备管理程序', [
                self::field('equipment_usage_items', '设备使用明细', 'repeatable_table', [], [
                    self::column('use_date', '使用日期'),
                    self::column('equipment_name', '设备名称'),
                    self::column('equipment_number', '设备编号'),
                    self::column('linked_sample_or_commission_number', '关联样品/委托编号'),
                    self::column('use_content', '使用内容'),
                    self::column('before_status', '使用前状态'),
                    self::column('after_status', '使用后状态'),
                    self::column('operator', '使用人'),
                    self::column('remarks', '备注'),
                ]),
            ], [
                'migration_note' => '游离编号XZTC-ZW01已填件归档时注记“应为03-02”。',
            ]),
            self::template('XZTC/BG-03-03', '保养、维护记录表', '仪器设备管理程序', [
                self::field('maintenance_items', '保养维护明细', 'repeatable_table', [], [
                    self::column('maintenance_date', '保养/维护日期'),
                    self::column('equipment_name', '设备名称'),
                    self::column('equipment_number', '设备编号'),
                    self::column('plan_or_cycle_basis', '依据保养计划/周期'),
                    self::column('maintenance_content', '保养/维护内容'),
                    self::column('result', '结果'),
                    self::column('operator', '执行人'),
                    self::column('reviewer', '确认人'),
                ]),
            ]),
            self::template('XZTC/BG-04-03', '期间核查记录表', '仪器设备和标准物质期间核查程序', [
                self::field('equipment_info_note', '设备信息栏说明'),
                self::field('check_basis', '核查依据'),
                self::field('check_items', '核查项目明细', 'repeatable_table', [], [
                    self::column('item', '核查项目'),
                    self::column('standard_value', '标准值'),
                    self::column('measured_value', '实测值'),
                    self::column('tolerance', '允差'),
                    self::column('conclusion', '结论判定'),
                ]),
                self::field('checker', '核查人'),
                self::field('check_date', '核查日期', 'date'),
            ], [
                'master_note' => '04系母版化：仅保留一张空白母版，设备信息由设备档案带出(LIMS)/据实填写(纸质)。',
                'prefill_issue_note' => '预填实例整体移出至设备工作底稿管理；两处数据错误随移出清单登记。',
            ]),
            self::template('XZTC/BG-04-05', '功能性核查记录表', '仪器设备和标准物质期间核查程序', [
                self::field('equipment_info_note', '设备信息栏说明'),
                self::field('function_check_items', '小型光学仪器功能项', 'repeatable_table', [], [
                    self::column('function_item', '功能项'),
                    self::column('polarizing_scope', '偏光镜'),
                    self::column('dichroscope', '二色镜'),
                    self::column('other_optical_instrument', '其他小型光学仪器'),
                    self::column('result', '勾选/结果'),
                    self::column('conclusion', '结论判定'),
                ]),
                self::field('checker', '核查人'),
                self::field('check_date', '核查日期', 'date'),
            ], [
                'master_note' => '04系母版化：仅保留一张空白母版；小型光学仪器功能项采用勾选式设计。',
            ]),
            self::template('XZTC/BG-04-06', '期间核查报告', '仪器设备和标准物质期间核查程序', [
                self::field('equipment_info_note', '设备信息栏说明'),
                self::field('check_record_reference', '关联期间核查记录'),
                self::field('check_summary', '核查结果摘要', 'textarea'),
                self::field('equipment_status_disposal_opinion', '核查结论对设备状态的处置意见', 'select', ['继续使用', '限用', '停用']),
                self::field('linked_action_note', '联动03-06/03-07说明', 'textarea'),
                self::field('reporter', '报告人'),
                self::field('approval_opinion', '批准意见', 'textarea'),
            ], [
                'master_note' => '04系母版化：仅保留一张空白母版；与04-03成对，预填实例移出设备工作底稿。',
                'prefill_issue_note' => '04-06两份折射仪预填文件曾出现编号互换，作为预填实例失控证据留痕。',
            ]),
            self::template('XZTC/BG-35-01', '标准物质台账', '标准物质管理程序', [
                self::field('reference_material_items', '标准物质台账明细', 'repeatable_table', [], [
                    self::column('material_number', '标准物质编号'),
                    self::column('material_name', '标准物质名称'),
                    self::column('certificate_number', '证书号'),
                    self::column('valid_until', '有效期'),
                    self::column('current_status', '当前状态(在用/停用/报废)'),
                    self::column('storage_position', '存放位置'),
                    self::column('keeper', '保管人'),
                    self::column('remarks', '备注'),
                ]),
            ], [
                'blocking_note' => 'UF-09暂缓：编号迁移仍按阻断维持不动，本批只正字，不迁号。',
            ]),
            self::template('XZTC/BG-35-02', '标准物质使用记录表', '标准物质管理程序', [
                self::field('alias_note', '登记别名'),
                self::field('reference_material_usage_items', '标准物质使用明细', 'repeatable_table', [], [
                    self::column('use_date', '使用日期'),
                    self::column('material_number', '标准物质编号'),
                    self::column('material_name', '标准物质名称'),
                    self::column('linked_sample_or_commission_number', '关联样品/委托编号'),
                    self::column('before_status', '使用前状态'),
                    self::column('after_status', '使用后状态'),
                    self::column('usage_amount', '使用量'),
                    self::column('user', '使用人'),
                    self::column('remarks', '备注'),
                ]),
            ], [
                'alias_note' => '登记别名：领用记录；使用前后状态列保留。',
            ]),
        ];
    }

    public static function sampleValues(string $docNumber, string $usageSite): array
    {
        $siteName = $usageSite === 'hetian' ? '和田' : '乌鲁木齐';
        $sampleNumber = $usageSite === 'hetian' ? 'HT-2026-0724-02' : 'WLMQ-2026-0724-02';
        $equipmentNumber = $usageSite === 'hetian' ? 'HT-ZSY-01' : 'WLMQ-TY-01';

        return [
            'usage_site' => $usageSite,
            'monitor_site' => $siteName,
            'equipment_info_note' => '由设备档案带出(LIMS)/据实填写(纸质)',
        ] + match ($docNumber) {
            'XZTC/BG-02-01' => [
                'monitor_month' => '2026-07',
                'requirement_value' => '温度20-25℃；湿度45%-65%RH',
                'daily_monitor_items' => [[
                    'date' => '2026-07-24',
                    'temperature' => '23.2℃',
                    'humidity' => '52%RH',
                    'other_condition' => '照明正常',
                    'conformity_judgment' => '符合',
                    'exception_disposal' => '无；超限时按不符合工作程序处理',
                    'recorder' => '环境监控员',
                ]],
            ],
            'XZTC/BG-03-02' => [
                'equipment_usage_items' => [[
                    'use_date' => '2026-07-24',
                    'equipment_name' => '折射仪',
                    'equipment_number' => $equipmentNumber,
                    'linked_sample_or_commission_number' => $sampleNumber,
                    'use_content' => '折射率检测',
                    'before_status' => '正常',
                    'after_status' => '正常',
                    'operator' => '检测员',
                    'remarks' => '与检测活动互链',
                ]],
            ],
            'XZTC/BG-03-03' => [
                'maintenance_items' => [[
                    'maintenance_date' => '2026-07-24',
                    'equipment_name' => '电子天平',
                    'equipment_number' => $equipmentNumber,
                    'plan_or_cycle_basis' => '月度保养计划/每月一次',
                    'maintenance_content' => '清洁、水平检查、状态确认',
                    'result' => '符合',
                    'operator' => '设备管理员',
                    'reviewer' => '技术负责人',
                ]],
            ],
            'XZTC/BG-04-03' => [
                'check_basis' => '期间核查作业指导书',
                'check_items' => [[
                    'item' => '折射率核查',
                    'standard_value' => '1.540',
                    'measured_value' => '1.540',
                    'tolerance' => '±0.002',
                    'conclusion' => '合格',
                ]],
                'checker' => '核查员',
                'check_date' => '2026-07-24',
            ],
            'XZTC/BG-04-05' => [
                'function_check_items' => [[
                    'function_item' => '偏振片旋转与视域明暗变化',
                    'polarizing_scope' => '√',
                    'dichroscope' => '',
                    'other_optical_instrument' => '',
                    'result' => '正常',
                    'conclusion' => '合格',
                ]],
                'checker' => '核查员',
                'check_date' => '2026-07-24',
            ],
            'XZTC/BG-04-06' => [
                'check_record_reference' => 'BG-04-03-' . $equipmentNumber,
                'check_summary' => '期间核查结果满足允差要求。',
                'equipment_status_disposal_opinion' => '继续使用',
                'linked_action_note' => '若结论为限用/停用，联动BG-03-06或BG-03-07。',
                'reporter' => '核查员',
                'approval_opinion' => '同意继续使用。',
            ],
            'XZTC/BG-35-01' => [
                'reference_material_items' => [[
                    'material_number' => 'BZ-' . ($usageSite === 'hetian' ? 'HT' : 'WLMQ') . '-001',
                    'material_name' => '金标片',
                    'certificate_number' => 'CERT-2026-001',
                    'valid_until' => '2027-07-24',
                    'current_status' => '在用',
                    'storage_position' => $siteName . '标准物质柜',
                    'keeper' => '标准物质管理员',
                    'remarks' => '只正字不迁号',
                ]],
            ],
            'XZTC/BG-35-02' => [
                'alias_note' => '领用记录',
                'reference_material_usage_items' => [[
                    'use_date' => '2026-07-24',
                    'material_number' => 'BZ-' . ($usageSite === 'hetian' ? 'HT' : 'WLMQ') . '-001',
                    'material_name' => '金标片',
                    'linked_sample_or_commission_number' => $sampleNumber,
                    'before_status' => '在用/完好',
                    'after_status' => '在用/完好',
                    'usage_amount' => '1次',
                    'user' => '检测员',
                    'remarks' => '保留量值维护证据',
                ]],
            ],
            default => [],
        };
    }

    private static function template(string $docNumber, string $name, string $module, array $schema, array $meta = []): array
    {
        return $meta + [
            'doc_number' => $docNumber,
            'name' => $name,
            'module' => $module,
            'print_template_key' => self::PRINT_TEMPLATE_KEY,
            'version' => 'A/0',
            'status' => 'candidate_pending_human_review',
            'review_status' => 'g2_expansion_batch2_candidate_modelled',
            'retention' => '不少于6年',
            'field_schema' => $schema,
        ];
    }

    private static function field(string $key, string $label, string $type = 'text', array $options = [], array $columns = []): array
    {
        $field = ['key' => $key, 'label' => $label, 'type' => $type, 'required' => false];
        if ($options !== []) {
            $field['options'] = $options;
        }
        if ($columns !== []) {
            $field['columns'] = $columns;
        }

        return $field;
    }

    private static function column(string $key, string $label): array
    {
        return ['key' => $key, 'label' => $label, 'type' => 'text', 'required' => false];
    }
}
