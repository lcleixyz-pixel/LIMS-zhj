<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../app/common.php';

$app = new think\App();
$app->initialize();

use app\service\QmsElementService;
use app\service\TrainingEvidenceService;
use think\facade\Config;
use think\facade\Db;

function assert_true(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
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

function ensure_training_deepening_tables(): void
{
    Db::execute(
        "CREATE TABLE IF NOT EXISTS `employee_certificates` (
          `id` varchar(36) NOT NULL,
          `company_id` varchar(36) NOT NULL,
          `employee_id` varchar(36) NOT NULL,
          `certificate_type` varchar(100) NOT NULL,
          `certificate_number` varchar(100) DEFAULT NULL,
          `issuing_authority` varchar(200) DEFAULT NULL,
          `issue_date` date DEFAULT NULL,
          `valid_until` date DEFAULT NULL,
          `review_date` date DEFAULT NULL,
          `status` enum('active','expired','revoked','archived') DEFAULT 'active',
          `remarks` text,
          `publish` tinyint(1) DEFAULT 1,
          `soft_delete` tinyint(1) DEFAULT 0,
          `created` datetime DEFAULT NULL,
          `modified` datetime DEFAULT NULL,
          `created_by` varchar(36) DEFAULT NULL,
          PRIMARY KEY (`id`),
          KEY `employee_id` (`employee_id`),
          KEY `valid_until` (`valid_until`),
          KEY `status` (`status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    foreach ([
        'description' => "ALTER TABLE `training_plans` ADD COLUMN `description` text DEFAULT NULL AFTER `title`",
        'approved_by' => "ALTER TABLE `training_plans` ADD COLUMN `approved_by` varchar(36) DEFAULT NULL AFTER `status`",
        'approved_at' => "ALTER TABLE `training_plans` ADD COLUMN `approved_at` datetime DEFAULT NULL AFTER `approved_by`",
        'completed_at' => "ALTER TABLE `training_plans` ADD COLUMN `completed_at` datetime DEFAULT NULL AFTER `approved_at`",
        'modified' => "ALTER TABLE `training_plans` ADD COLUMN `modified` datetime DEFAULT NULL AFTER `created`",
    ] as $column => $sql) {
        $exists = Db::query(
            "SELECT COUNT(*) AS count FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'training_plans' AND COLUMN_NAME = ?",
            [$column]
        )[0]['count'] ?? 0;
        if ((int)$exists === 0) {
            Db::execute($sql);
        }
    }
}

function cleanup_training_deepening_smoke(array $ids): void
{
    Db::name('file_uploads')->where('record', $ids['certificate'])->delete();
    Db::name('employee_certificates')->where('id', $ids['certificate'])->delete();
    Db::name('training_records')->where('id', $ids['training_record'])->delete();
    Db::name('trainings')->where('id', $ids['training'])->delete();
    Db::name('training_plans')->where('id', $ids['plan'])->delete();
    Db::name('record_form_instances')->where('id', $ids['supervision_instance'])->delete();
    Db::name('record_form_templates')->where('id', $ids['supervision_template'])->delete();
    Db::name('employees')->where('id', $ids['employee'])->delete();
}

$root = dirname(__DIR__);
$schema = (string)file_get_contents($root . '/database/jewelry_qms.sql');
$migration = (string)file_get_contents($root . '/database/migrations/20260529_training_deepening.sql');
$route = (string)file_get_contents($root . '/route/app.php');
$qmsConfig = (string)file_get_contents($root . '/config/qms.php');
$layout = (string)file_get_contents($root . '/app/view/layout/main.html');
$trainingPlanController = (string)file_get_contents($root . '/app/controller/TrainingPlan.php');
$trainingController = (string)file_get_contents($root . '/app/controller/Training.php');
$employeeCertificateController = (string)file_get_contents($root . '/app/controller/EmployeeCertificate.php');
$trainingPlanView = (string)file_get_contents($root . '/app/view/training_plan/view.html');
$trainingView = (string)file_get_contents($root . '/app/view/training/view.html');
$employeeView = (string)file_get_contents($root . '/app/view/employee/view.html');
$certificateView = (string)file_get_contents($root . '/app/view/employee_certificate/view.html');

foreach ([$schema, $migration] as $source) {
    assert_contains('employee_certificates', $source, 'Training deepening schema includes employee_certificates');
    assert_contains('approved_at', $source, 'Training plan schema includes approval timestamp');
    assert_contains('completed_at', $source, 'Training plan schema includes completion timestamp');
}

foreach ([
    'training_plan',
    'training_plan/approve',
    'training_plan/complete',
    'employee_certificate',
    'employee_certificate/uploadAttachment',
    'employee_certificate/downloadAttachment',
] as $needle) {
    assert_contains($needle, $route, 'Route exposes ' . $needle);
}

assert_contains('training_plan', $qmsConfig, 'Training plan permission is configured');
assert_contains('employee_certificate', $qmsConfig, 'Employee certificate permission is configured');
assert_contains('培训计划', $layout, 'Navigation includes training plan');
assert_contains('人员资质证书', $layout, 'Navigation includes employee certificates');
assert_contains('TrainingEvidenceService', $trainingPlanController . $trainingController . $employeeCertificateController, 'Controllers use training evidence service');
assert_contains('计划培训活动', $trainingPlanView, 'Training plan detail shows linked activities');
assert_contains('参训记录', $trainingView, 'Training detail shows attendance records');
assert_contains('人员资质证书', $employeeView, 'Employee detail shows certificates');
assert_contains('监督记录', $employeeView, 'Employee detail shows supervision record instances');
assert_contains('证书附件', $certificateView, 'Certificate detail shows file attachments');

$modules = QmsElementService::businessModuleDefinitions();
$moduleCodes = array_column($modules, 'code');
assert_true(in_array('employee_certificates', $moduleCodes, true), 'QMS modules include employee certificates');
assert_true(in_array('supervision_record_instances', $moduleCodes, true), 'QMS modules include supervision record instances');

ensure_training_deepening_tables();
assert_true(class_exists(TrainingEvidenceService::class), 'TrainingEvidenceService exists');
foreach (['certificateAttachments', 'registerCertificateAttachment', 'employeeCertificateRows', 'supervisionRecordInstances', 'planProgress'] as $method) {
    assert_true(method_exists(TrainingEvidenceService::class, $method), 'TrainingEvidenceService supports ' . $method);
}

$companyId = (string)Config::get('qms.company_id');
$now = date('Y-m-d H:i:s');
$ids = [
    'employee' => 'train-deep-emp',
    'plan' => 'train-deep-plan',
    'training' => 'train-deep-training',
    'training_record' => 'train-deep-record',
    'certificate' => 'train-deep-cert',
    'supervision_template' => 'train-deep-sup-tpl',
    'supervision_instance' => 'train-deep-sup-inst',
];

try {
    cleanup_training_deepening_smoke($ids);
    Db::name('employees')->insert([
        'id' => $ids['employee'],
        'company_id' => $companyId,
        'employee_number' => 'EMP-TRAIN-001',
        'name' => '培训能力冒烟人员',
        'publish' => 1,
        'soft_delete' => 0,
        'created' => $now,
        'modified' => $now,
    ]);
    Db::name('training_plans')->insert([
        'id' => $ids['plan'],
        'company_id' => $companyId,
        'plan_year' => date('Y'),
        'title' => '培训能力冒烟计划',
        'description' => '用于验证培训计划闭环',
        'status' => 'approved',
        'approved_at' => $now,
        'publish' => 1,
        'soft_delete' => 0,
        'created' => $now,
        'modified' => $now,
    ]);
    Db::name('trainings')->insert([
        'id' => $ids['training'],
        'company_id' => $companyId,
        'training_plan_id' => $ids['plan'],
        'title' => '培训能力冒烟活动',
        'training_type' => 'internal',
        'trainer' => '质量负责人',
        'training_date' => date('Y-m-d'),
        'duration_hours' => 2,
        'content' => '能力确认与监督要求',
        'status' => 'completed',
        'publish' => 1,
        'soft_delete' => 0,
        'created' => $now,
        'modified' => $now,
    ]);
    Db::name('training_records')->insert([
        'id' => $ids['training_record'],
        'training_id' => $ids['training'],
        'employee_id' => $ids['employee'],
        'attendance' => 'present',
        'evaluation_score' => 92,
        'evaluation_result' => 'pass',
        'publish' => 1,
        'soft_delete' => 0,
        'created' => $now,
    ]);
    Db::name('employee_certificates')->insert([
        'id' => $ids['certificate'],
        'company_id' => $companyId,
        'employee_id' => $ids['employee'],
        'certificate_type' => '检测人员上岗证',
        'certificate_number' => 'CERT-TRAIN-001',
        'issuing_authority' => '实验室',
        'issue_date' => date('Y-m-d'),
        'valid_until' => date('Y-m-d', strtotime('+1 year')),
        'status' => 'active',
        'publish' => 1,
        'soft_delete' => 0,
        'created' => $now,
        'modified' => $now,
    ]);
    Db::name('record_form_templates')->insert([
        'id' => $ids['supervision_template'],
        'company_id' => $companyId,
        'doc_number' => 'XZTC/BG-31-02',
        'name' => '日常监督记录表',
        'module' => '人员培训程序',
        'version' => 'A/0',
        'field_schema' => '[]',
        'status' => 'published',
        'review_status' => 'completed',
        'print_template_key' => 'rf_xztc_bg_31_02_dbcfe0a49d',
        'publish' => 1,
        'soft_delete' => 0,
        'created' => $now,
        'modified' => $now,
    ]);
    Db::name('record_form_instances')->insert([
        'id' => $ids['supervision_instance'],
        'company_id' => $companyId,
        'template_id' => $ids['supervision_template'],
        'template_name' => '日常监督记录表',
        'template_module' => '人员培训程序',
        'template_version' => 'A/0',
        'template_print_template_key' => 'rf_xztc_bg_31_02_dbcfe0a49d',
        'template_field_schema' => '[]',
        'doc_number' => 'XZTC/BG-31-02',
        'record_title' => '培训能力冒烟人员 日常监督记录',
        'field_values' => json_encode(['supervisee' => '培训能力冒烟人员', 'employee_number' => 'EMP-TRAIN-001'], JSON_UNESCAPED_UNICODE),
        'status' => 'generated',
        'created' => $now,
        'modified' => $now,
    ]);

    $progress = TrainingEvidenceService::planProgress($ids['plan']);
    assert_true($progress['total'] === 1 && $progress['completed'] === 1, 'Plan progress counts linked completed activities');
    assert_true(TrainingEvidenceService::employeeCertificateRows($ids['employee'])->count() === 1, 'Employee certificates are queryable');
    assert_true(TrainingEvidenceService::supervisionRecordInstances($ids['employee'])->count() === 1, 'Supervision record instances are queryable by employee');

    $attachment = TrainingEvidenceService::registerCertificateAttachment($ids['certificate'], [
        'file_name' => 'certificate.pdf',
        'file_path' => 'uploads/employee_certificates/train-deep-cert/certificate.pdf',
        'file_type' => 'pdf',
    ], '证书附件冒烟');
    assert_true($attachment !== null, 'Certificate attachment can be registered');
    assert_true(TrainingEvidenceService::certificateAttachments($ids['certificate'])->count() === 1, 'Certificate attachments are queryable');
} finally {
    cleanup_training_deepening_smoke($ids);
}

echo "qms_training_deepening_smoke passed\n";
