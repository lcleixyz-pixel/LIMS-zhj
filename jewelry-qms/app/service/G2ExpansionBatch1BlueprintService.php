<?php
declare(strict_types=1);

namespace app\service;

final class G2ExpansionBatch1BlueprintService
{
    public const PRINT_TEMPLATE_KEY = 'g2_expansion_batch1_record';

    public static function templates(): array
    {
        return [
            self::template('XZTC/BG-09-02', '检测委托合同单', '合同评审程序', [
                self::field('commission_number', '委托单号'),
                self::field('customer_name', '客户名称'),
                self::field('sample_name', '样品名称'),
                self::field('sample_quantity', '样品数量'),
                self::field('test_items', '检测项目', 'textarea'),
                self::field('judgment_rule_agreement', '判定规则约定栏(S3)', 'textarea'),
                self::field('report_mark_status', '报告标志状态(CMA/无标志)', 'select', ['CMA', '无标志']),
                self::field('out_of_scope_notice_signature', '库外客户告知确认签字栏(N1/N3)'),
                self::field('test_site', '检测场所', 'select', ['乌鲁木齐', '和田']),
                self::field('client_signature', '客户签字'),
                self::field('accepted_by', '受理人'),
                self::field('accepted_date', '受理日期', 'date'),
            ]),
            self::template('XZTC/BG-09-01', '合同评审记录表', '合同评审程序', [
                self::field('review_number', '评审编号'),
                self::field('customer_requirement', '客户要求/合同摘要', 'textarea'),
                self::field('standards_and_scope_status', '所用标准及库内外状态(E3扩展列)', 'textarea'),
                self::field('test_site', '检测场所', 'select', ['乌鲁木齐', '和田']),
                self::field('proposed_report_mark', '拟用标志', 'select', ['CMA', '无标志']),
                self::field('out_of_scope_notice_confirmation', '库外告知确认'),
                self::field('is_subcontracted', '是否分包(固定否)', 'select', ['否']),
                self::field('review_conclusion', '评审结论', 'textarea'),
                self::field('reviewer', '评审人'),
                self::field('review_date', '评审日期(N3/N4签认)'),
            ]),
            self::template('XZTC/BG-28-01', '样品台账', '样品处置和管理程序', [
                self::field('sample_register_items', '样品台账明细', 'repeatable_table', [], [
                    self::column('sample_number', '样品编号'),
                    self::column('sample_name', '样品名称'),
                    self::column('quantity', '数量'),
                    self::column('received_date', '接收日期'),
                    self::column('receiver', '接收人'),
                    self::column('claimant', '领取人'),
                    self::column('returned_by', '还样人'),
                    self::column('claim_time', '领取时间'),
                    self::column('overdue_notice_date', '逾期通知日期'),
                    self::column('disposal_record', '处置记录'),
                    self::column('bg_09_05_link_note', '备注/BG-09-05联动标记'),
                ]),
            ]),
            self::template('XZTC/BG-28-02', '样品标识卡', '样品处置和管理程序', [
                self::field('sample_number', '样品编号'),
                self::field('sample_name', '样品名称'),
                self::field('status', '样品状态(待检/在检/完检/待领/已退回或处置)', 'select', ['待检', '在检', '完检', '待领', '已退回或处置']),
                self::field('storage_position', '存放位置'),
                self::field('test_item', '检测项目'),
                self::field('received_date', '接收日期', 'date'),
                self::field('handler', '经办人'),
                self::field('print_form_note', '印制形式说明', 'textarea'),
            ], [
                'merge_note' => '样品标识卡与样品标识卡(六联)收为单一母版；六联仅作为印制形式说明。',
            ]),
            self::template('XZTC/BG-28-03', '样品损坏丢失报告表', '样品处置和管理程序', [
                self::field('report_number', '报告编号'),
                self::field('sample_number', '样品编号'),
                self::field('sample_name', '样品名称'),
                self::field('damage_or_loss_description', '损坏/丢失情况说明', 'textarea'),
                self::field('cause_analysis', '原因分析', 'textarea'),
                self::field('customer_notice_and_result', '客户告知与处理结果', 'textarea'),
                self::field('reported_by', '报告人'),
                self::field('report_date', '报告日期', 'date'),
                self::field('approved_by', '批准人'),
            ]),
            self::template('XZTC/BG-28-04', '样品领取记录', '样品处置和管理程序', [
                self::field('sample_number', '样品编号'),
                self::field('sample_name', '样品名称'),
                self::field('quantity', '数量'),
                self::field('checked_by', '核对人(样品管理员)'),
                self::field('claimant_signature', '领取人签名'),
                self::field('claim_date', '领取日期', 'date'),
                self::field('image_check_mark', '影像核对标记'),
            ], [
                'acceptance_gate' => '新表；两场所各真实试用不少于1次。',
                'confidentiality_note' => '独立单联、一单一纸；避免客户在样品台账上看到其他客户信息。',
            ]),
            self::template('XZTC/BG-29-03', '报告发放登记表', '结果报告程序', [
                self::field('issue_items', '报告发放明细', 'repeatable_table', [], [
                    self::column('report_number', '报告编号'),
                    self::column('customer_name', '客户名称'),
                    self::column('issue_method', '发放方式(自取/邮寄/电子)'),
                    self::column('issue_date', '发放日期'),
                    self::column('receiver', '领取/接收人'),
                    self::column('receiver_signature', '领取/接收人签名'),
                    self::column('operator', '经办人'),
                    self::column('sample_claim_joint_note', '与28-04同场合并办理注记'),
                ]),
            ]),
            self::template('XZTC/BG-09-05', '库外项目过渡合同台账', '合同评审程序', [
                self::field('contract_transition_items', '库外项目过渡合同台账主表(合同级,一合同一行)', 'repeatable_table', [], [
                    self::column('contract_number', '合同/委托编号'),
                    self::column('customer_name', '客户名称'),
                    self::column('contract_sign_date', '合同签署日期'),
                    self::column('out_of_scope_standard_registration', '库外标准登记'),
                    self::column('issued_report_count', '已出报告数量'),
                    self::column('last_report_date', '末份报告日期'),
                    self::column('completion_confirmation', '完成确认'),
                    self::column('shutdown_close_confirmation', '停用关闭确认'),
                    self::column('freeze_zone', '冻结区'),
                ]),
                self::field('sample_transition_appendix', '台账明细附页(可选,样品级过程联动BG-28-01)', 'repeatable_table', [], [
                    self::column('transition_number', '过渡编号'),
                    self::column('sample_number', '样品编号'),
                    self::column('sample_name', '样品名称'),
                    self::column('scope_status', '库内外状态'),
                    self::column('linked_sample_register', 'BG-28-01联动标记'),
                    self::column('process_note', '过程备注'),
                ]),
            ], [
                'acceptance_gate' => '新表；两场所各真实试用不少于1次。',
                'renumber_note' => 'BG-09-03已被合同、协议登记表占用；本表按签认改号为BG-09-05。',
                'rebuild_note' => 'R-2返工：主表按模板A重建为合同级台账；样品级过程信息降为可选附页。',
            ]),
        ];
    }

    public static function sampleValues(string $docNumber, string $usageSite): array
    {
        $siteName = $usageSite === 'hetian' ? '和田' : '乌鲁木齐';
        $common = [
            'usage_site' => $usageSite,
            'test_site' => $siteName,
            'customer_name' => $siteName . '试点客户',
            'sample_number' => $siteName === '和田' ? 'HT-2026-0724-01' : 'WLMQ-2026-0724-01',
            'sample_name' => '和田玉样品',
            'quantity' => '1件',
        ];

        return $common + match ($docNumber) {
            'XZTC/BG-09-02' => [
                'commission_number' => 'WT-2026-0724-' . ($usageSite === 'hetian' ? 'HT' : 'WLMQ'),
                'test_items' => '折射率、密度、放大检查',
                'judgment_rule_agreement' => '按客户确认的判定规则执行；库外项目不得使用CMA标志。',
                'report_mark_status' => $usageSite === 'hetian' ? '无标志' : 'CMA',
                'out_of_scope_notice_signature' => $usageSite === 'hetian' ? '客户已确认库外项目无标志报告' : '不适用',
                'client_signature' => '客户签名',
                'accepted_by' => '受理员',
                'accepted_date' => '2026-07-24',
            ],
            'XZTC/BG-09-01' => [
                'review_number' => 'PS-2026-0724-' . ($usageSite === 'hetian' ? 'HT' : 'WLMQ'),
                'customer_requirement' => '客户要求完成常规珠宝玉石检测并出具报告。',
                'standards_and_scope_status' => $usageSite === 'hetian' ? 'GB/T 16552；库外过渡确认' : 'GB/T 16552；库内',
                'proposed_report_mark' => $usageSite === 'hetian' ? '无标志' : 'CMA',
                'out_of_scope_notice_confirmation' => $usageSite === 'hetian' ? '已告知并签字确认' : '不适用',
                'is_subcontracted' => '否',
                'review_conclusion' => '能力、资源和标志使用条件已评审。',
                'reviewer' => '合同评审人',
                'review_date' => '2026-07-24',
            ],
            'XZTC/BG-28-01' => [
                'sample_register_items' => [[
                    'sample_number' => $common['sample_number'],
                    'sample_name' => $common['sample_name'],
                    'quantity' => '1件',
                    'received_date' => '2026-07-24',
                    'receiver' => '样品管理员',
                    'claimant' => '',
                    'returned_by' => '',
                    'claim_time' => '',
                    'overdue_notice_date' => '2026-08-24',
                    'disposal_record' => '待检测完成后处置',
                    'bg_09_05_link_note' => $usageSite === 'hetian' ? '关联BG-09-05过渡台账' : '无',
                ]],
            ],
            'XZTC/BG-28-02' => [
                'status' => '在检',
                'storage_position' => $siteName . '样品柜A-01',
                'test_item' => '常规鉴定',
                'received_date' => '2026-07-24',
                'handler' => '样品管理员',
                'print_form_note' => '六联为印制形式说明，内容同源，不另设母版。',
            ],
            'XZTC/BG-28-03' => [
                'report_number' => 'YPBG-2026-0724-' . ($usageSite === 'hetian' ? 'HT' : 'WLMQ'),
                'damage_or_loss_description' => '样品包装轻微破损，样品本体未见异常。',
                'cause_analysis' => '交接时外包装磨损，已拍照留证。',
                'customer_notice_and_result' => '已告知客户并确认继续检测。',
                'reported_by' => '样品管理员',
                'report_date' => '2026-07-24',
                'approved_by' => '质量负责人',
            ],
            'XZTC/BG-28-04' => [
                'checked_by' => '样品管理员',
                'claimant_signature' => '客户签名',
                'claim_date' => '2026-07-24',
                'image_check_mark' => '已核对交付影像',
            ],
            'XZTC/BG-29-03' => [
                'issue_items' => [[
                    'report_number' => 'BG-2026-0724-' . ($usageSite === 'hetian' ? 'HT' : 'WLMQ'),
                    'customer_name' => $common['customer_name'],
                    'issue_method' => $usageSite === 'hetian' ? '电子' : '自取',
                    'issue_date' => '2026-07-24',
                    'receiver' => '客户接收人',
                    'receiver_signature' => '客户签名',
                    'operator' => '报告发放人',
                    'sample_claim_joint_note' => '与BG-28-04同场办理',
                ]],
            ],
            'XZTC/BG-09-05' => [
                'contract_transition_items' => [[
                    'contract_number' => 'HT-2026-0528-' . ($usageSite === 'hetian' ? 'HT' : 'WLMQ'),
                    'customer_name' => $common['customer_name'],
                    'contract_sign_date' => '2026-05-28',
                    'out_of_scope_standard_registration' => '客户指定方法-登记号KWBZ-2026-001',
                    'issued_report_count' => '2',
                    'last_report_date' => '2026-06-20',
                    'completion_confirmation' => '已完成过渡期内合同约定报告',
                    'shutdown_close_confirmation' => '2026-07-24确认停用关闭',
                    'freeze_zone' => '关闭后冻结：不得新增报告，不得改用CMA标志；仅保留追溯查询',
                ]],
                'sample_transition_appendix' => [[
                    'transition_number' => 'GW-2026-0724-' . ($usageSite === 'hetian' ? 'HT' : 'WLMQ'),
                    'sample_number' => $common['sample_number'],
                    'sample_name' => $common['sample_name'],
                    'scope_status' => '库外过渡',
                    'linked_sample_register' => '已回填BG-28-01备注',
                    'process_note' => '样品级过程信息仅作附页，不替代合同级关闭冻结主表',
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
            'status' => 'blueprint_signed_sandbox',
            'review_status' => 'g2_expansion_batch1_modelled',
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
