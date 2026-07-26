<?php
declare(strict_types=1);

namespace app\service;

final class G2ExpansionBatch4BlueprintService
{
    public const PRINT_TEMPLATE_KEY = 'g2_expansion_batch4_record';

    private const GROUP_A = '甲组|年度与收尾重点项';
    private const GROUP_B = '乙组|通用件套用';

    public static function templates(): array
    {
        return array_merge(self::focusTemplates(), self::genericTemplates());
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
                        $row[(string)$column['key']] = self::sampleText((string)$column['label'], $siteName, $docNumber);
                    }
                    $values[$key] = [$row];
                } else {
                    $values[$key] = self::sampleText((string)$field['label'], $siteName, $docNumber);
                }
            }
        }

        return $values;
    }

    private static function focusTemplates(): array
    {
        return [
            self::template('XZTC/BG-08-09', '样品检测原始记录', 'CX-25 检测工作控制程序', self::GROUP_A, [
                self::field('sample_number', '样品编号(=证书号)'),
                self::field('sample_name', '样品名称'),
                self::field('commission_number', '委托单号'),
                self::field('method', '检测项目与方法(标准号)'),
                self::field('equipment_number', '仪器设备编号'),
                self::field('environment', '环境条件'),
                self::field('observation_data', '观测数据与图谱粘贴区', 'textarea'),
                self::field('sample_image', '样品影像', 'textarea'),
                self::field('tester_signature', '检测人签名'),
                self::field('reviewer_signature', '校核人签名'),
                self::field('test_date', '检测日期'),
            ], [
                'blueprint_note' => '08-09升格挂CX-25；编号暂不迁移，G3清扫统一裁决；切换时点待与29系报告链一并裁决。',
            ]),
            self::template('XZTC/BG-20-01', '内部审核报告', '内部审核程序', self::GROUP_A, [
                self::field('audit_basis', '审核依据(GL011:2025对齐)', 'textarea'),
                self::field('audit_conclusion', '审核结论', 'textarea'),
                self::field('nonconformity_summary', '不符合及整改摘要', 'textarea'),
            ]),
            self::template('XZTC/BG-20-02', '内部审核年度计划', '内部审核程序', self::GROUP_A, [
                self::table('audit_plan_items', '年度内审计划明细', ['月份', '审核范围', '审核依据(GL011:2025对齐)', '审核组成员', '备注']),
            ]),
            self::template('XZTC/BG-20-03', '内部审核首次会议签到表', '内部审核程序', self::GROUP_A, [
                self::table('sign_items', '首次会议签到明细', ['姓名', '部门/岗位', '会议角色', '签名']),
            ]),
            self::template('XZTC/BG-20-04', '授权签字人审核表', '内部审核程序', self::GROUP_A, [
                self::field('authorized_signer', '授权签字人'),
                self::field('review_basis', '审核依据(GL011:2025对齐)', 'textarea'),
                self::field('review_conclusion', '审核结论', 'textarea'),
            ]),
            self::template('XZTC/BG-20-05', '内部审核末次会议签到表', '内部审核程序', self::GROUP_A, [
                self::table('sign_items', '末次会议签到明细', ['姓名', '部门/岗位', '会议角色', '签名']),
            ]),
            self::template('XZTC/BG-20-06', '内部审核检查记录表', '内部审核程序', self::GROUP_A, [
                self::table('audit_check_items', '检查记录明细', ['条款', '查证方法', '证据', '判定']),
            ], [
                'blueprint_note' => '从已填件剥离补建空白母版；G4与检查表接口继续沿用四列结构。',
            ]),
            self::template('XZTC/BG-20-07', '现场能力审核表', '内部审核程序', self::GROUP_A, [
                self::table('onsite_capability_items', '现场能力审核明细', ['场所', '项目', '查证方法', '证据', '判定']),
            ]),
            self::template('XZTC/BG-20-08', '内部审核日程计划', '内部审核程序', self::GROUP_A, [
                self::table('audit_schedule_items', '日程计划明细', ['日期', '时段', '审核区域/条款', '审核员', '陪同人']),
            ]),
            self::template('XZTC/BG-21-01', '管理评审计划表', '管理评审程序', self::GROUP_A, [
                self::field('approval_person', '批准人(总经理)'),
                self::table('review_plan_items', '管理评审计划明细', ['评审输入', '责任部门', '提交期限', '备注']),
            ], [
                'blueprint_note' => '批准人栏改总经理。',
            ]),
            self::template('XZTC/BG-21-02', '管理评审报告', '管理评审程序', self::GROUP_A, [
                self::field('review_conclusion', '报告结论', 'textarea'),
                self::table('output_decision_items', '输出决议明细', ['决议事项', '责任人', '期限', '验证方式']),
            ], [
                'blueprint_note' => '报告结论与输出决议结构化。',
            ]),
            self::template('XZTC/BG-21-03', '管理评审会议签到表', '管理评审程序', self::GROUP_A, [
                self::table('sign_items', '管理评审签到明细', ['姓名', '部门/岗位', '会议角色', '签名']),
            ]),
            self::template('XZTC/BG-21-04', '管理评审验证记录表', '管理评审程序', self::GROUP_A, [
                self::table('verification_items', '决议验证明细', ['决议事项', '责任人', '期限', '验证结果']),
            ], [
                'blueprint_note' => '扩4批新建记录。',
            ]),
            self::template('XZTC/BG-23-01', '纠正措施处理单', '纠正措施程序', self::GROUP_A, [
                self::field('approval_stage', '申报审批', 'textarea'),
                self::field('implementation_stage', '实施', 'textarea'),
                self::field('effect_review_stage', '效果审核', 'textarea'),
            ], [
                'blueprint_note' => '申报审批、实施、效果审核三段一表闭环。',
            ]),
            self::template('XZTC/BG-24-01', '新项目评审表', '新项目评审程序', self::GROUP_A, [
                self::table('capacity_items', '能力确认要素勾选', ['人员', '设备', '材料', '方法验证', '环境', '批准意见']),
                self::field('first_review_object', '首个待评审对象注记'),
            ], [
                'blueprint_note' => 'GB/T 44914为首个待评审对象注记。',
            ]),
            self::template('XZTC/BG-24-02', '新项目批准表', '新项目评审程序', self::GROUP_A, [
                self::table('approval_chain_items', '批准链明细', ['能力确认要素', '审核意见', '批准人', '批准日期']),
            ], [
                'blueprint_note' => '增能力确认要素勾选与批准链。',
            ]),
            self::template('XZTC/BG-02-02', '设施和环境条件要求一览表', '设施和环境条件控制程序', self::GROUP_A, [
                self::table('environment_requirement_items', '设施环境要求明细', ['区域', '参数', '要求值', '依据(标准或A015)', '监控方式', '对应记录(02-01)']),
            ], [
                'blueprint_note' => '扩4批新建记录。',
            ]),
        ];
    }

    private static function genericTemplates(): array
    {
        $items = [
            ['XZTC/BG-04-01', '文件发放回收记录表', '文件控制程序'],
            ['XZTC/BG-04-02', '受控文件清单', '文件控制程序'],
            ['XZTC/BG-04-04', '文件更改申请表', '文件控制程序'],
            ['XZTC/BG-05-01', '量值溯源计划表', '设备管理程序'],
            ['XZTC/BG-05-02', '计量溯源结果确认表', '设备管理程序', '记录目录内05-02为正身；CX-05-02程序清单重复行已删除，UF-02关闭。'],
            ['XZTC/BG-06-01', '设备使用记录表', '设备管理程序'],
            ['XZTC/BG-07-01', '标准物质管理记录表', '标准物质管理程序'],
            ['XZTC/BG-08-01', '检测委托单', '检测工作控制程序'],
            ['XZTC/BG-08-03', '样品接收记录', '检测工作控制程序'],
            ['XZTC/BG-08-04', '样品流转记录', '检测工作控制程序'],
            ['XZTC/BG-08-05', '样品退还记录', '检测工作控制程序'],
            ['XZTC/BG-08-06', '检测任务单', '检测工作控制程序'],
            ['XZTC/BG-08-07', '检测复核记录', '检测工作控制程序'],
            ['XZTC/BG-08-08', '样品异常情况记录', '检测工作控制程序'],
            ['XZTC/BG-09-04', '合同定期重评审记录表', '合同评审程序', '补定期重评审日期栏。'],
            ['XZTC/BG-11-01', '采购申请表', '采购服务控制程序'],
            ['XZTC/BG-11-02', '供应商评价表', '采购服务控制程序'],
            ['XZTC/BG-11-03', '合格供应商名录', '采购服务控制程序'],
            ['XZTC/BG-11-04', '采购验收记录表', '采购服务控制程序'],
            ['XZTC/BG-11-05', '外部服务评价表', '采购服务控制程序'],
            ['XZTC/BG-12-01', '服务客户反馈记录表', '服务客户程序'],
            ['XZTC/BG-12-02', '投诉处理记录表', '投诉处理程序'],
            ['XZTC/BG-13-01', '会议记录表', '沟通程序', '未编号会议记录表归位13-01。'],
            ['XZTC/BG-14-01', '不符合工作记录表', '不符合工作控制程序'],
            ['XZTC/BG-14-02', '不符合工作处置记录表', '不符合工作控制程序'],
            ['XZTC/BG-15-01', '风险和机遇评估表', '风险和机遇控制程序'],
            ['XZTC/BG-16-01', '内部沟通记录表', '内部沟通程序', '两个未编号变体合并回母版。'],
            ['XZTC/BG-17-01', '保密承诺记录表', '保密和公正性程序'],
            ['XZTC/BG-19-01', '记录清单', '记录控制程序'],
            ['XZTC/BG-19-02', '记录借阅复制登记表', '记录控制程序'],
            ['XZTC/BG-19-03', '质量记录保存期限表', '记录控制程序', '行级保存期限不少于6年。'],
            ['XZTC/BG-19-04', '技术记录保存期限表', '记录控制程序', '行级保存期限不少于6年。'],
            ['XZTC/BG-26-01', '数据控制检查记录表', '数据控制程序'],
            ['XZTC/BG-26-02', '信息系统备份记录表', '数据控制程序'],
            ['XZTC/BG-29-01', '报告发放登记表', '结果报告程序', '补7.8.7.2报告召回条款字段。'],
            ['XZTC/BG-29-02', '报告更改记录表', '结果报告程序'],
            ['XZTC/BG-32-01', '安全检查记录表', '安全管理程序'],
            ['XZTC/BG-33-01', '印章使用登记表', '印章管理程序', '带空格重复件作废。'],
            ['XZTC/BG-34-01', '标识管理检查记录表', '标识管理程序'],
            ['XZTC/BG-34-02', '标识更换记录表', '标识管理程序'],
            ['XZTC/BG-35-03', '标准物质报废申请表', '标准物质管理程序', '按2026-07-25治理试运行阻断关闭决定，BG-35-03恢复为标准物质管理记录。'],
            ['XZTC/BG-03-09', '年度质量目标完成情况统计表', '质量目标管理程序'],
        ];

        return array_map(
            static fn(array $item): array => self::template($item[0], $item[1], $item[2], self::GROUP_B, self::genericSchema($item[0]), isset($item[3]) ? ['blueprint_note' => $item[3]] : []),
            $items
        );
    }

    private static function genericSchema(string $docNumber): array
    {
        $schema = [
            self::field('record_date', '记录日期'),
            self::field('responsible_person', '责任人'),
            self::table('record_items', '记录明细', ['事项', '内容', '证据/编号', '处理结果', '备注']),
        ];

        return match ($docNumber) {
            'XZTC/BG-09-04' => [
                self::field('contract_number', '合同编号'),
                self::field('regular_reevaluation_date', '定期重评审日期'),
                self::field('reevaluation_conclusion', '重评审结论', 'textarea'),
            ],
            'XZTC/BG-13-01' => [
                self::field('meeting_topic', '会议主题'),
                self::table('meeting_items', '会议记录明细', ['议题', '讨论记录', '形成决定', '责任人']),
            ],
            'XZTC/BG-16-01' => [
                self::field('variant_merge_note', '未编号变体合并说明', 'textarea'),
                self::table('communication_items', '沟通记录明细', ['沟通事项', '参加人员', '沟通结论', '后续动作']),
            ],
            'XZTC/BG-19-03', 'XZTC/BG-19-04' => [
                self::table('retention_items', '保存期限明细', ['记录类别', '记录名称', '保存期限(不少于6年)', '保存地点', '责任人']),
            ],
            'XZTC/BG-29-01' => [
                self::field('report_number', '报告编号'),
                self::field('issue_date', '发放日期'),
                self::field('recall_clause_fields', '7.8.7.2报告召回条款字段', 'textarea'),
                self::field('recall_handling_result', '召回/更正处理结果', 'textarea'),
            ],
            'XZTC/BG-33-01' => [
                self::field('duplicate_void_note', '带空格重复件作废说明', 'textarea'),
                self::table('seal_items', '印章使用明细', ['用印事项', '文件编号', '批准人', '经办人']),
            ],
            default => $schema,
        };
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
            'review_status' => 'g2_expansion_batch4_modelled',
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
            'columns' => array_map(
                static fn(string $label): array => ['key' => self::keyFromLabel($label), 'label' => $label, 'type' => 'text', 'required' => false],
                $columns
            ),
        ];
    }

    private static function keyFromLabel(string $label): string
    {
        return 'c_' . substr(hash('sha1', $label), 0, 10);
    }

    private static function sampleText(string $label, string $siteName, string $docNumber): string
    {
        return match (true) {
            str_contains($label, '证书号') => $siteName . '-CERT-20260724-001',
            str_contains($label, '委托单号') => $siteName . '-WT-20260724-001',
            str_contains($label, '标准号') => 'GB/T 16552-2017 / GB/T 44914待评审注记',
            str_contains($label, '仪器设备编号') => $siteName . '-EQ-FTIR-01',
            str_contains($label, '图谱粘贴区') => $siteName . '样例观测数据；图谱粘贴区预留',
            str_contains($label, '样品影像') => $siteName . '样品影像粘贴区预留',
            str_contains($label, 'GL011:2025') => '已按GL011:2025对齐检查项',
            str_contains($label, '条款') => 'CNAS-CL01:2018 7.8.7.2',
            str_contains($label, '查证方法') => '文件抽查/记录追溯/现场访谈',
            str_contains($label, '证据') => $siteName . '-EVID-20260724',
            str_contains($label, '判定') => '符合',
            str_contains($label, '总经理') => '总经理签批栏',
            str_contains($label, '决议事项') => '完善年度收尾记录闭环',
            str_contains($label, '验证结果') => '已验证有效',
            str_contains($label, '申报审批') => '申报原因、影响评估、审批意见已闭环',
            str_contains($label, '实施') => '责任人按期实施并留存证据',
            str_contains($label, '效果审核') => '经审核措施有效',
            str_contains($label, '人员') => '具备授权人员',
            str_contains($label, '设备') => '设备状态满足',
            str_contains($label, '材料') => '标准物质/耗材满足',
            str_contains($label, '方法验证') => '方法验证完成',
            str_contains($label, '环境') => '环境条件满足',
            str_contains($label, '首个待评审对象') => 'GB/T 44914为首个待评审对象',
            str_contains($label, '区域') => $siteName . '检测区',
            str_contains($label, '要求值') => '按标准或A015执行',
            str_contains($label, '对应记录') => 'BG-02-01',
            str_contains($label, '05-02') || str_contains($label, '计量溯源') => 'BG-05-02正身确认，UF-02已关闭',
            str_contains($label, '定期重评审日期') => '2027-07-24',
            str_contains($label, '未编号变体') => '两个未编号变体合并回BG-16-01母版',
            str_contains($label, '保存期限(不少于6年)') => '不少于6年',
            str_contains($label, '7.8.7.2') => '报告更改、召回、替换、客户通知、原报告作废标识字段齐备',
            str_contains($label, '带空格重复件') => 'BG-33-01带空格重复件作废，保留正身母版',
            default => $siteName . '样例-' . $label,
        };
    }
}
