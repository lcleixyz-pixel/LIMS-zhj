<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../app/common.php';

$app = new think\App();
$app->initialize();

use app\service\EquipmentEvidenceService;
use app\service\QmsElementService;
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

function ensure_equipment_deepening_tables(): void
{
    Db::execute(
        "CREATE TABLE IF NOT EXISTS `reference_materials` (
          `id` varchar(36) NOT NULL,
          `company_id` varchar(36) NOT NULL,
          `code` varchar(50) NOT NULL,
          `name` varchar(200) NOT NULL,
          `lot_number` varchar(100) DEFAULT NULL,
          `manufacturer` varchar(200) DEFAULT NULL,
          `traceability_certificate_number` varchar(100) DEFAULT NULL,
          `valid_until` date DEFAULT NULL,
          `storage_location` varchar(200) DEFAULT NULL,
          `status` enum('active','expired','depleted','discarded') DEFAULT 'active',
          `remarks` text,
          `publish` tinyint(1) DEFAULT 1,
          `soft_delete` tinyint(1) DEFAULT 0,
          `created` datetime DEFAULT NULL,
          `modified` datetime DEFAULT NULL,
          `created_by` varchar(36) DEFAULT NULL,
          PRIMARY KEY (`id`),
          UNIQUE KEY `reference_material_code` (`code`),
          KEY `valid_until` (`valid_until`),
          KEY `status` (`status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
    Db::execute(
        "CREATE TABLE IF NOT EXISTS `equipment_authorizations` (
          `id` varchar(36) NOT NULL,
          `company_id` varchar(36) NOT NULL,
          `equipment_id` varchar(36) NOT NULL,
          `employee_id` varchar(36) NOT NULL,
          `authorized_date` date NOT NULL,
          `valid_until` date DEFAULT NULL,
          `authorization_scope` text,
          `authorized_by` varchar(36) DEFAULT NULL,
          `status` enum('active','revoked','expired') DEFAULT 'active',
          `remarks` text,
          `publish` tinyint(1) DEFAULT 1,
          `soft_delete` tinyint(1) DEFAULT 0,
          `created` datetime DEFAULT NULL,
          `modified` datetime DEFAULT NULL,
          `created_by` varchar(36) DEFAULT NULL,
          PRIMARY KEY (`id`),
          KEY `equipment_id` (`equipment_id`),
          KEY `employee_id` (`employee_id`),
          KEY `valid_until` (`valid_until`),
          KEY `status` (`status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
}

function cleanup_equipment_deepening_smoke(array $ids): void
{
    Db::name('file_uploads')->where('record', $ids['calibration'])->delete();
    Db::name('equipment_authorizations')->where('equipment_id', $ids['equipment'])->delete();
    Db::name('reference_materials')->where('id', $ids['reference_material'])->delete();
    Db::name('record_form_instances')->where('id', $ids['instance'])->delete();
    Db::name('record_form_templates')->where('id', $ids['template'])->delete();
    Db::name('equipment_maintenances')->where('equipment_id', $ids['equipment'])->delete();
    Db::name('calibrations')->where('id', $ids['calibration'])->delete();
    Db::name('equipments')->where('id', $ids['equipment'])->delete();
    Db::name('employees')->where('id', $ids['employee'])->delete();
}

$root = dirname(__DIR__);
$schema = (string)file_get_contents($root . '/database/jewelry_qms.sql');
$migration = (string)file_get_contents($root . '/database/migrations/20260529_equipment_deepening.sql');
$route = (string)file_get_contents($root . '/route/app.php');
$qmsConfig = (string)file_get_contents($root . '/config/qms.php');
$layout = (string)file_get_contents($root . '/app/view/layout/main.html');
$equipmentController = (string)file_get_contents($root . '/app/controller/Equipment.php');
$calibrationController = (string)file_get_contents($root . '/app/controller/Calibration.php');
$equipmentView = (string)file_get_contents($root . '/app/view/equipment/view.html');
$calibrationView = (string)file_get_contents($root . '/app/view/calibration/view.html');

foreach ([$schema, $migration] as $source) {
    assert_contains('reference_materials', $source, 'Equipment deepening schema includes reference_materials');
    assert_contains('equipment_authorizations', $source, 'Equipment deepening schema includes equipment_authorizations');
}
assert_contains('calibration/uploadCertificate', $route, 'Route exposes calibration certificate upload');
assert_contains('calibration/downloadCertificate', $route, 'Route exposes calibration certificate download');
assert_contains('reference_material', $route, 'Routes expose reference material CRUD');
assert_contains('equipment_authorization', $route, 'Routes expose equipment authorization CRUD');
assert_contains('reference_material', $qmsConfig, 'Reference material permission is configured');
assert_contains('equipment_authorization', $qmsConfig, 'Equipment authorization permission is configured');
assert_contains('标准物质台账', $layout, 'Navigation includes reference material ledger');
assert_contains('授权使用人', $layout, 'Navigation includes equipment authorization ledger');
assert_contains('EquipmentEvidenceService', $equipmentController, 'Equipment controller uses evidence service');
assert_contains('EquipmentEvidenceService', $calibrationController, 'Calibration controller uses evidence service');
assert_contains('期间核查记录表格', $equipmentView, 'Equipment detail shows periodic check record instances');
assert_contains('授权使用人', $equipmentView, 'Equipment detail shows authorized users');
assert_contains('校准证书附件', $calibrationView, 'Calibration detail shows certificate attachments');

$modules = QmsElementService::businessModuleDefinitions();
$moduleCodes = array_column($modules, 'code');
assert_true(in_array('reference_materials', $moduleCodes, true), 'QMS business modules include reference materials');
assert_true(in_array('equipment_authorizations', $moduleCodes, true), 'QMS business modules include equipment authorizations');

ensure_equipment_deepening_tables();
assert_true(class_exists(EquipmentEvidenceService::class), 'EquipmentEvidenceService exists');
foreach (['periodicCheckInstances', 'registerCalibrationCertificate', 'calibrationCertificateAttachments', 'equipmentAuthorizationRows'] as $method) {
    assert_true(method_exists(EquipmentEvidenceService::class, $method), 'EquipmentEvidenceService supports ' . $method);
}

$companyId = (string)Config::get('qms.company_id');
$ids = [
    'equipment' => 'equip-deep-smoke-eq',
    'employee' => 'equip-deep-smoke-emp',
    'template' => 'equip-deep-smoke-template',
    'instance' => 'equip-deep-smoke-instance',
    'calibration' => 'equip-deep-smoke-calibration',
    'reference_material' => 'equip-deep-smoke-rm',
];
$now = date('Y-m-d H:i:s');

try {
    cleanup_equipment_deepening_smoke($ids);
    Db::name('equipments')->insert([
        'id' => $ids['equipment'],
        'company_id' => $companyId,
        'equipment_number' => 'EQ-SMOKE-001',
        'name' => '设备深化冒烟仪器',
        'model' => 'M-1',
        'calibration_required' => 1,
        'calibration_cycle_months' => 12,
        'status' => 'active',
        'publish' => 1,
        'soft_delete' => 0,
        'created' => $now,
        'modified' => $now,
    ]);
    Db::name('employees')->insert([
        'id' => $ids['employee'],
        'company_id' => $companyId,
        'employee_number' => 'EMP-SMOKE-001',
        'name' => '授权使用人',
        'publish' => 1,
        'soft_delete' => 0,
        'created' => $now,
        'modified' => $now,
    ]);
    Db::name('record_form_templates')->insert([
        'id' => $ids['template'],
        'company_id' => $companyId,
        'doc_number' => 'XZTC/BG-04-03',
        'name' => '仪器设备和标准物质期间核查记录表',
        'module' => '仪器设备和标准物质期间核查程序',
        'version' => 'A/0',
        'field_schema' => '[]',
        'status' => 'published',
        'review_status' => 'completed',
        'print_template_key' => 'periodic_check',
        'publish' => 1,
        'soft_delete' => 0,
        'created' => $now,
        'modified' => $now,
    ]);
    Db::name('record_form_instances')->insert([
        'id' => $ids['instance'],
        'company_id' => $companyId,
        'template_id' => $ids['template'],
        'template_name' => '仪器设备和标准物质期间核查记录表',
        'template_module' => '仪器设备和标准物质期间核查程序',
        'template_version' => 'A/0',
        'template_print_template_key' => 'periodic_check',
        'template_field_schema' => '[]',
        'doc_number' => 'XZTC/BG-04-03',
        'record_title' => 'EQ-SMOKE-001 期间核查记录',
        'field_values' => json_encode(['equipment_code' => 'EQ-SMOKE-001', 'equipment_name' => '设备深化冒烟仪器'], JSON_UNESCAPED_UNICODE),
        'status' => 'generated',
        'created' => $now,
        'modified' => $now,
    ]);
    Db::name('equipment_maintenances')->insert([
        'id' => 'equip-deep-smoke-maint',
        'equipment_id' => $ids['equipment'],
        'maintenance_date' => date('Y-m-d'),
        'maintenance_type' => 'verification',
        'description' => '期间核查维护记录',
        'performed_by' => '质量员',
        'next_due_date' => date('Y-m-d', strtotime('+6 months')),
        'publish' => 1,
        'soft_delete' => 0,
        'created' => $now,
    ]);
    Db::name('calibrations')->insert([
        'id' => $ids['calibration'],
        'equipment_id' => $ids['equipment'],
        'calibration_date' => date('Y-m-d'),
        'next_due_date' => date('Y-m-d', strtotime('+1 year')),
        'calibration_org' => '计量机构',
        'certificate_number' => 'CERT-SMOKE-001',
        'result' => 'pass',
        'publish' => 1,
        'soft_delete' => 0,
        'created' => $now,
    ]);
    Db::name('reference_materials')->insert([
        'id' => $ids['reference_material'],
        'company_id' => $companyId,
        'code' => 'RM-SMOKE-001',
        'name' => '标准物质冒烟样',
        'lot_number' => 'LOT-1',
        'traceability_certificate_number' => 'RM-CERT-001',
        'valid_until' => date('Y-m-d', strtotime('+1 year')),
        'status' => 'active',
        'publish' => 1,
        'soft_delete' => 0,
        'created' => $now,
        'modified' => $now,
    ]);
    Db::name('equipment_authorizations')->insert([
        'id' => 'equip-deep-smoke-auth',
        'company_id' => $companyId,
        'equipment_id' => $ids['equipment'],
        'employee_id' => $ids['employee'],
        'authorized_date' => date('Y-m-d'),
        'valid_until' => date('Y-m-d', strtotime('+1 year')),
        'authorization_scope' => '允许操作该设备开展检测',
        'status' => 'active',
        'publish' => 1,
        'soft_delete' => 0,
        'created' => $now,
        'modified' => $now,
    ]);

    $periodic = EquipmentEvidenceService::periodicCheckInstances($ids['equipment']);
    assert_true(count($periodic) === 1 && $periodic[0]['id'] === $ids['instance'], 'Periodic check record instance is matched by equipment code');

    $certificate = EquipmentEvidenceService::registerCalibrationCertificate(
        $ids['calibration'],
        'uploads/calibrations/' . $ids['calibration'] . '/certificate.pdf',
        '校准证书.pdf',
        '证书原件扫描件'
    );
    assert_true($certificate->model_name === 'Calibration', 'Calibration certificate uses Calibration model name');
    $attachments = EquipmentEvidenceService::calibrationCertificateAttachments($ids['calibration']);
    assert_true(count($attachments) === 1, 'Calibration certificate attachments are listed from file_uploads');

    $authorizations = EquipmentEvidenceService::equipmentAuthorizationRows($ids['equipment']);
    assert_true(count($authorizations) === 1, 'Equipment authorization rows are listed');
    assert_true((string)$authorizations[0]['employee_name'] === '授权使用人', 'Equipment authorization resolves employee name');
} finally {
    cleanup_equipment_deepening_smoke($ids);
}

echo "qms_equipment_deepening_smoke passed\n";
