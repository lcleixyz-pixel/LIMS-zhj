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

use app\service\RecordFormFixtureService;
use app\service\RecordFormPrintService;

$outputDir = $argv[1] ?? '';
if ($outputDir === '') {
    fwrite(STDERR, "Usage: php scripts/g2_route_a_sample_build.php <output-dir>\n");
    exit(2);
}

if (!is_dir($outputDir) && !mkdir($outputDir, 0775, true) && !is_dir($outputDir)) {
    fwrite(STDERR, "Cannot create output dir: {$outputDir}\n");
    exit(2);
}

function g2_template(string $docNumber): array
{
    foreach (RecordFormFixtureService::templates() as $template) {
        if (($template['doc_number'] ?? '') === $docNumber) {
            return $template;
        }
    }

    throw new RuntimeException('Template not found: ' . $docNumber);
}

function g2_training_record_values(string $usageSite): array
{
    return [
        'usage_site' => $usageSite,
        'training_topic' => $usageSite === 'hetian' ? '和田场所记录表格填写要求' : '乌鲁木齐场所记录表格填写要求',
        'training_category' => '内部',
        'training_date' => '2026-07-23',
        'training_place' => $usageSite === 'hetian' ? '和田实验室会议室' : '乌鲁木齐实验室会议室',
        'trainer' => '质量负责人',
        'training_provider' => '',
        'training_materials' => 'CX-01 人员培训程序；G2 Route A 蓝图；记录总台账 v0.2',
        'training_target_scope' => '检测人员、资料管理员',
        'assessment_method' => '提问',
        'attendees' => [
            ['name' => $usageSite === 'hetian' ? '和田试填人员' : '乌市试填人员', 'position' => '检测员', 'signature' => '试填签名'],
        ],
        'training_content' => '按蓝图试填人员培训记录表，验证参加人员逐人一行、归档标识和双号呈现。',
        'assessment_result' => '试填通过，真实业务验收待机构签字。',
        'effect_evaluation_note' => '效果评价衔接至 BG-01-09。',
        'recorder' => '资料管理员',
        'record_date' => '2026-07-23',
        'archive_note' => '复印件入个人技术档案。',
    ];
}

function g2_training_application_values(string $usageSite): array
{
    return [
        'usage_site' => $usageSite,
        'applicant' => $usageSite === 'hetian' ? '和田申请人' : '乌市申请人',
        'application_department' => '检测室',
        'application_date' => '2026-07-23',
        'training_category' => '外委',
        'training_content' => 'CMA/CNAS 记录控制与报告标志分流专项培训',
        'participants' => [
            ['name' => $usageSite === 'hetian' ? '和田试填人员' : '乌市试填人员', 'department' => '检测室', 'signature' => '试填签名'],
        ],
        'training_provider' => '外部培训机构',
        'training_place' => $usageSite === 'hetian' ? '和田' : '乌鲁木齐',
        'training_time' => '2026-08',
        'estimated_cost' => '2000',
        'expected_capability' => '培训后能按新主路径填写记录并识别库内外项目的报告标志限制。',
        'technical_manager_review_opinion' => '外委名单和培训目标符合能力保持要求，同意提交批准。',
        'technical_manager' => '技术负责人',
        'technical_manager_review_date' => '2026-07-23',
        'lab_director_approval_opinion' => '批准。',
        'lab_director' => '实验室主任',
        'lab_director_approval_date' => '2026-07-23',
        'remarks' => '仓库侧样张；真实试填和签字由机构侧完成。',
    ];
}

$cases = [
    'BG-01-02-wulumuqi' => [g2_template('XZTC/BG-01-02'), g2_training_record_values('wulumuqi')],
    'BG-01-02-hetian' => [g2_template('XZTC/BG-01-02'), g2_training_record_values('hetian')],
    'BG-01-06-wulumuqi' => [g2_template('XZTC/BG-01-06'), g2_training_application_values('wulumuqi')],
    'BG-01-06-hetian' => [g2_template('XZTC/BG-01-06'), g2_training_application_values('hetian')],
];

$manifest = [];
foreach ($cases as $name => [$template, $values]) {
    $html = RecordFormPrintService::render((string)$template['print_template_key'], $template, $values);
    $file = $outputDir . DIRECTORY_SEPARATOR . $name . '.html';
    file_put_contents($file, $html);
    $manifest[] = [
        'case' => $name,
        'doc_number' => $template['doc_number'],
        'display_doc_number' => RecordFormPrintService::displayDocNumber($template, $values),
        'print_template_key' => $template['print_template_key'],
        'field_count' => count($template['field_schema']),
        'html_file' => basename($file),
        'sha256' => hash_file('sha256', $file),
    ];
}

file_put_contents(
    $outputDir . DIRECTORY_SEPARATOR . 'route-a-sample-manifest.json',
    json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n"
);

echo "g2_route_a_sample_build generated " . count($manifest) . " samples\n";
