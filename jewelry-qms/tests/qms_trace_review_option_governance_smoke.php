<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use app\service\QmsTraceReviewOptionService;

function trace_option_governance_assert(
    bool $condition,
    string $message
): void {
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

$result = QmsTraceReviewOptionService::govern(
    [
        'clauses' => [
            [
                'id' => 'clause-history',
                'source_code' => 'CNAS-CL01:2018',
                'version' => '2018',
                'status' => 'published',
                'clause_number' => '6.4',
                'title' => '设备',
            ],
            [
                'id' => 'clause-candidate',
                'source_code' => 'CNAS-CL01:2018',
                'version' => '2018',
                'status' => 'published',
                'clause_number' => '7.1',
                'title' => '要求、标书和合同的评审',
            ],
            [
                'id' => 'clause-invalid',
                'source_code' => 'CNAS-CL01:2018',
                'version' => '2018',
                'status' => 'published',
                'clause_number' => '7.6',
                'title' => '测量不确定度的评�',
            ],
        ],
        'manual_sections' => [
            [
                'id' => 'manual-history',
                'source_doc_number' => 'XZTC/SC',
                'version' => '第四版',
                'status' => 'published',
                'section_number' => '6.4',
                'title' => '设备',
            ],
            [
                'id' => 'manual-candidate',
                'source_doc_number' => 'SIM-XZTC/SC',
                'version' => 'GOV-TRIAL/0.1',
                'status' => 'trial_ready',
                'section_number' => '6.4',
                'title' => '设备',
            ],
        ],
        'procedure_documents' => [
            [
                'id' => 'procedure-old',
                'doc_number' => 'SIM-GOV02-XZTC/CX-03-02-2022',
                'version' => 'A/2',
                'status' => 'trial_ready',
                'title' => '标准物质管理程序',
            ],
            [
                'id' => 'procedure-current',
                'doc_number' => 'SIM-GOV02-XZTC/CX-03-02-2022',
                'version' => 'GOV-TRIAL/0.2',
                'status' => 'trial_ready',
                'title' => '标准物质管理程序',
            ],
            [
                'id' => 'procedure-unique',
                'doc_number' => 'XZTC/CX-01-01-2022',
                'version' => 'A/1',
                'status' => 'published',
                'title' => '文件控制程序',
            ],
        ],
        'positions' => [
            ['id' => 'position-quality', 'code' => 'QA', 'name' => '质量负责人'],
        ],
    ],
    [
        'external_sources' => [
            ['id' => 'clause-candidate', 'available' => true],
        ],
        'manual_sections' => [
            ['id' => 'manual-candidate', 'available' => true],
        ],
    ]
);

$options = (array)($result['options'] ?? []);
$summary = (array)($result['summary'] ?? []);

trace_option_governance_assert(
    array_column($options['clauses'], 'id') === [
        'clause-candidate',
        'clause-history',
    ],
    '乱码条款应被隔离，候选条款应置顶'
);
trace_option_governance_assert(
    ($options['clauses'][0]['is_candidate'] ?? false) === true
        && ($options['clauses'][0]['is_secondary'] ?? true) === false
        && ($options['clauses'][1]['is_secondary'] ?? false) === true,
    '有候选的对象组应默认显示候选并折叠其他对象'
);
trace_option_governance_assert(
    ($summary['clauses']['excluded_invalid'] ?? 0) === 1
        && ($summary['clauses']['candidate'] ?? 0) === 1
        && ($summary['clauses']['secondary'] ?? 0) === 1,
    '条款治理统计应包含候选、历史和乱码隔离数量'
);
trace_option_governance_assert(
    str_contains(
        (string)$options['manual_sections'][0]['governance_label'],
        '★ 本文件候选'
    )
        && str_contains(
            (string)$options['manual_sections'][0]['governance_label'],
            'GOV-TRIAL/0.1'
        )
        && str_contains(
            (string)$options['manual_sections'][0]['governance_label'],
            '试运行就绪'
        ),
    '候选手册章节应显示来源、版本和中文状态'
);
trace_option_governance_assert(
    ($options['procedure_documents'][0]['id'] ?? '')
        === 'procedure-current'
        && ($options['procedure_documents'][0]['is_secondary'] ?? true)
        === false
        && ($options['procedure_documents'][1]['id'] ?? '')
        === 'procedure-unique'
        && ($options['procedure_documents'][2]['id'] ?? '')
        === 'procedure-old'
        && ($options['procedure_documents'][2]['is_secondary'] ?? false)
        === true,
    '重复程序文件应优先 GOV-TRIAL/0.2，并折叠其他版本'
);
trace_option_governance_assert(
    str_contains(
        (string)$options['procedure_documents'][0]['governance_label'],
        'GOV-TRIAL/0.2'
    )
        && str_contains(
            (string)$options['procedure_documents'][0]['governance_label'],
            '试运行就绪'
        ),
    '程序文件应显示版本和中文状态'
);
trace_option_governance_assert(
    ($options['positions'][0]['is_secondary'] ?? true) === false
        && ($options['positions'][0]['status_label'] ?? '')
        === '状态待确认',
    '无候选且不重复的对象应保持默认可见'
);

echo "qms_trace_review_option_governance_smoke passed\n";
