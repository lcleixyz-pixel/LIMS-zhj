<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../app/common.php';

if (!function_exists('root_path')) {
    function root_path(): string
    {
        return dirname(__DIR__) . DIRECTORY_SEPARATOR;
    }
}

use app\service\RecordFormBatchTemplateService;
use app\service\RecordFormFixtureService;
use app\service\RecordFormPrintService;
use app\service\RecordFormSchemaService;

function assert_same($expected, $actual, string $message): void
{
    if ($expected !== $actual) {
        fwrite(STDERR, $message . PHP_EOL);
        fwrite(STDERR, 'Expected: ' . var_export($expected, true) . PHP_EOL);
        fwrite(STDERR, 'Actual: ' . var_export($actual, true) . PHP_EOL);
        exit(1);
    }
}

function assert_contains(string $needle, string $haystack, string $message): void
{
    if (!str_contains($haystack, $needle)) {
        fwrite(STDERR, $message . PHP_EOL);
        fwrite(STDERR, 'Missing: ' . $needle . PHP_EOL);
        exit(1);
    }
}

function assert_not_contains(string $needle, string $haystack, string $message): void
{
    if (str_contains($haystack, $needle)) {
        fwrite(STDERR, $message . PHP_EOL);
        fwrite(STDERR, 'Unexpected: ' . $needle . PHP_EOL);
        exit(1);
    }
}

function personnel_values_for(string $printTemplateKey): array
{
    return match ($printTemplateKey) {
        'rf_xztc_bg_01_01_5325a1b0bd' => [
            'plan_year' => '2026',
            'training_plan_items' => [
                [
                    'training_time' => '2026-03',
                    'training_content' => 'CMA 记录表格填写要求',
                    'training_target' => '检测人员',
                    'training_department' => '检测室',
                    'remarks' => '年度计划',
                ],
            ],
        ],
        'training_record' => [
            'training_date' => '2026-05-22',
            'training_topic' => '记录表格填写要求',
            'trainer' => '质量负责人',
            'training_content' => '模板 smoke',
            'attendees' => [['name' => '张三', 'department' => '检测室', 'signature' => '张三']],
            'effect_evaluation' => '符合要求',
        ],
        'rf_xztc_bg_01_03_5fa5a364df' => [
            'certificate_items' => [
                [
                    'name' => '李四',
                    'certificate_type' => '检验检测人员证',
                    'certificate_number' => 'CERT-2026-001',
                    'first_issued_date' => '2024-01-15',
                    'issuer' => '认证机构',
                    'valid_until' => '2028-01-14',
                    'last_review_date' => '2026-01-10',
                    'remarks' => '有效',
                ],
            ],
        ],
        'rf_xztc_bg_01_04_5fb52565ba' => [
            'assessment_items' => [
                [
                    'name' => '王五',
                    'assessment_project' => '紫外可见分光光度计操作',
                    'oral_result' => '合格',
                    'operation_result' => '合格',
                    'written_score' => '92',
                    'host_department' => '检测室',
                    'assessment_date' => '2026-05-20',
                ],
            ],
        ],
        'rf_xztc_bg_01_05_66b005b382' => [
            'trainee_name' => '赵六',
            'gender' => '男',
            'birth_month' => '1995-06',
            'work_start_date' => '2020-07',
            'education_level' => '本科',
            'major' => '宝石学',
            'current_position' => '检测员',
            'prejob_training_items' => [
                ['training_content' => '职业道德教育', 'completion_status' => '已完成', 'assessment_score' => '合格', 'remarks' => ''],
            ],
            'technical_manager_opinion' => '同意上岗前考核',
            'technical_manager_date' => '2026-05-21',
            'lab_director_opinion' => '考核合格',
            'lab_director_date' => '2026-05-22',
            'remarks' => '无',
        ],
        'rf_xztc_bg_01_06_f268e9aaf1' => [
            'training_content' => 'CMA 体系换版培训',
            'participants' => [
                ['name' => '检测人员甲', 'department' => '检测室', 'signature' => '检测人员甲'],
                ['name' => '检测人员乙', 'department' => '质量部', 'signature' => '检测人员乙'],
            ],
            'training_provider' => '外部培训机构',
            'training_place' => '会议室',
            'training_time' => '2026-06-10',
            'estimated_cost' => '2000',
            'application_department' => '检测室',
            'application_opinion' => '申请参加培训',
            'application_responsible_person' => '申请负责人',
            'application_date' => '2026-05-20',
            'audit_opinion' => '同意',
            'auditor' => '技术负责人',
            'audit_date' => '2026-05-21',
            'approval_opinion' => '批准',
            'approver' => '实验室主任',
            'approval_date' => '2026-05-22',
            'remarks' => '按计划执行',
        ],
        'rf_xztc_bg_01_07_a0956d356f' => [
            'hire_date' => '2026-01-02',
            'department' => '检测室',
            'applied_position' => '检测员',
            'employee_name' => '钱七',
            'gender' => '女',
            'ethnicity' => '汉族',
            'birth_month' => '1996-08',
            'height' => '165cm',
            'native_place' => '广东',
            'graduate_school' => '中国地质大学',
            'major' => '宝石学',
            'education_level' => '本科',
            'graduation_date' => '2019-06',
            'political_status' => '群众',
            'work_start_date' => '2019-07',
            'registered_address' => '广州市',
            'marital_status' => '未婚',
            'id_number' => '440000199608080000',
            'address' => '广州市天河区',
            'phone' => '13800000000',
            'backup_phone' => '13900000000',
            'qualification_certificate' => '检验检测人员证',
            'email' => 'test@example.com',
            'education_items' => [['period' => '2015-2019', 'school' => '中国地质大学', 'major' => '宝石学']],
            'work_items' => [['period' => '2019-2025', 'company' => '检测机构', 'position' => '检测员', 'leave_time' => '2025-12', 'witness' => '证明人']],
            'family_items' => [['relationship' => '父亲', 'name' => '钱某', 'birth_month' => '1970-01', 'work_unit' => '单位', 'phone' => '13700000000']],
            'self_evaluation' => '具备岗位要求。',
            'commitment_signature' => '钱七',
            'commitment_date' => '2026-01-02',
        ],
        'rf_xztc_bg_01_08_6fcb518418' => [
            'employee_name' => '孙八',
            'department_position' => '检测室/检测员',
            'hire_date' => '2024-03-01',
            'work_years' => '3',
            'title' => '助理工程师',
            'education_level' => '本科',
            'graduate_school' => '中国地质大学',
            'major' => '宝石学',
            'experience_summary' => '完成体系培训和岗位实操培训。',
            'capability_items' => [
                ['content' => '职业道德基础知识、相关法律法规、资质认定准则。', 'confirmation_method' => '提问+实操', 'result' => '合格'],
            ],
            'confirmation_result' => '确认具备岗位能力',
            'confirmer' => '技术负责人',
            'confirmation_date' => '2026-05-22',
            'authorization_result' => '授权开展相关检测工作',
            'authorizer' => '实验室主任',
            'authorization_date' => '2026-05-23',
        ],
        'rf_xztc_bg_01_09_5f54bbf750' => [
            'employee_name' => '周九',
            'department' => '检测室',
            'position' => '检测员',
            'training_nature' => '外部培训',
            'training_method' => '线下',
            'training_time' => '2026-05-15',
            'training_provider_or_trainer' => '培训机构',
            'certificate_number' => 'PX-2026-001',
            'training_main_content' => 'CMA 体系要求与记录控制。',
            'assessment_method' => '考试',
            'assessment_content' => '体系要求',
            'assessment_result' => '合格',
            'supervisor' => '监督员',
            'supervisor_date' => '2026-05-20',
            'evaluation_opinion' => '培训达到预期。',
            'responsible_person' => '负责人',
            'responsible_date' => '2026-05-22',
            'remarks' => '归档',
        ],
    };
}

$expected = [
    'XZTC/BG-01-01|年度人员培训计划表' => [
        'print_key' => 'rf_xztc_bg_01_01_5325a1b0bd',
        'keys' => ['plan_year', 'training_plan_items'],
        'needles' => ['年度人员培训计划表', '培训对象', 'CMA 记录表格填写要求'],
    ],
    'XZTC/BG-01-02|人员培训记录表' => [
        'print_key' => 'training_record',
        'keys' => ['training_date', 'training_topic', 'trainer', 'training_content', 'attendees', 'effect_evaluation'],
        'needles' => ['人员培训记录表', '参训人员', '记录表格填写要求'],
    ],
    'XZTC/BG-01-03|检测人员持证登记表' => [
        'print_key' => 'rf_xztc_bg_01_03_5fa5a364df',
        'keys' => ['certificate_items'],
        'needles' => ['检测人员持证登记表', '证书号码', 'CERT-2026-001'],
    ],
    'XZTC/BG-01-04|人员考核记录表' => [
        'print_key' => 'rf_xztc_bg_01_04_5fb52565ba',
        'keys' => ['assessment_items'],
        'needles' => ['人员考核记录表', '考核方式', '紫外可见分光光度计操作'],
    ],
    'XZTC/BG-01-05|岗前培训考核记录表' => [
        'print_key' => 'rf_xztc_bg_01_05_66b005b382',
        'keys' => ['trainee_name', 'gender', 'birth_month', 'work_start_date', 'education_level', 'major', 'current_position', 'prejob_training_items', 'technical_manager_opinion', 'technical_manager_date', 'lab_director_opinion', 'lab_director_date', 'remarks'],
        'needles' => ['岗前培训考核记录表', '职业道德教育', '技术负责人意见'],
    ],
    'XZTC/BG-01-06|培训申请表' => [
        'print_key' => 'rf_xztc_bg_01_06_f268e9aaf1',
        'keys' => ['training_content', 'participants', 'training_provider', 'training_place', 'training_time', 'estimated_cost', 'application_department', 'application_opinion', 'application_responsible_person', 'application_date', 'audit_opinion', 'auditor', 'audit_date', 'approval_opinion', 'approver', 'approval_date', 'remarks'],
        'needles' => ['培训申请表', '申请培训内容', 'CMA 体系换版培训'],
    ],
    'XZTC/BG-01-07|人员档案登记表' => [
        'print_key' => 'rf_xztc_bg_01_07_a0956d356f',
        'keys' => ['hire_date', 'department', 'applied_position', 'employee_name', 'gender', 'ethnicity', 'birth_month', 'height', 'native_place', 'graduate_school', 'major', 'education_level', 'graduation_date', 'political_status', 'work_start_date', 'registered_address', 'marital_status', 'id_number', 'address', 'phone', 'backup_phone', 'qualification_certificate', 'email', 'education_items', 'work_items', 'family_items', 'self_evaluation', 'commitment_signature', 'commitment_date'],
        'needles' => ['人员档案登记表', '教育及培训经历', '中国地质大学'],
    ],
    'XZTC/BG-01-08|人员能力确认表' => [
        'print_key' => 'rf_xztc_bg_01_08_6fcb518418',
        'keys' => ['employee_name', 'department_position', 'hire_date', 'work_years', 'title', 'education_level', 'graduate_school', 'major', 'experience_summary', 'capability_items', 'confirmation_result', 'confirmer', 'confirmation_date', 'authorization_result', 'authorizer', 'authorization_date'],
        'needles' => ['人员能力确认表', '能力确认情况', '确认具备岗位能力'],
    ],
    'XZTC/BG-01-09|人员培训评价表' => [
        'print_key' => 'rf_xztc_bg_01_09_5f54bbf750',
        'keys' => ['employee_name', 'department', 'position', 'training_nature', 'training_method', 'training_time', 'training_provider_or_trainer', 'certificate_number', 'training_main_content', 'assessment_method', 'assessment_content', 'assessment_result', 'supervisor', 'supervisor_date', 'evaluation_opinion', 'responsible_person', 'responsible_date', 'remarks'],
        'needles' => ['人员培训评价表', '培训的主要内容', '培训达到预期'],
    ],
];

$fixturesByIdentity = [];
foreach (RecordFormFixtureService::templates() as $template) {
    $fixturesByIdentity[$template['doc_number'] . '|' . $template['name']] = $template;
}
assert_same(array_keys($expected), array_keys($fixturesByIdentity), 'Personnel fixture exposes exactly the nine formal personnel templates');

$manifestByIdentity = [];
foreach (RecordFormBatchTemplateService::manifest() as $entry) {
    $identity = $entry['doc_number'] . '|' . $entry['base_name'];
    if (isset($expected[$identity])) {
        $manifestByIdentity[$identity] = $entry;
    }
}
assert_same(array_keys($expected), array_keys($manifestByIdentity), 'Batch manifest keeps all nine personnel records as formal entries');

foreach ($expected as $identity => $rules) {
    $fixture = $fixturesByIdentity[$identity];
    $entry = $manifestByIdentity[$identity];

    assert_same($rules['print_key'], $fixture['print_template_key'], $identity . ' fixture print key');
    assert_same($rules['print_key'], $entry['print_template_key'], $identity . ' manifest print key');
    assert_same('published', $entry['status'], $identity . ' is published after high-fidelity reconstruction');
    assert_same('completed', $entry['review_status'], $identity . ' review status is completed');
    assert_same($rules['keys'], array_column($entry['field_schema'], 'key'), $identity . ' field schema keys');

    $decoded = RecordFormSchemaService::decode(RecordFormSchemaService::encode($entry['field_schema']));
    assert_same($rules['keys'], array_column($decoded, 'key'), $identity . ' schema round trips');

    $html = RecordFormPrintService::render(
        $entry['print_template_key'],
        $entry,
        personnel_values_for($entry['print_template_key'])
    );

    assert_contains($entry['doc_number'], $html, $identity . ' print includes doc number');
    assert_not_contains('高保真重构草稿', $html, $identity . ' formal print has no draft watermark');
    assert_contains('break-inside: avoid', $html, $identity . ' print protects table rows from splitting');

    foreach ($rules['needles'] as $needle) {
        assert_contains($needle, $html, $identity . ' print includes expected label/value');
    }
}

echo "record_forms_personnel_fidelity_smoke passed\n";
