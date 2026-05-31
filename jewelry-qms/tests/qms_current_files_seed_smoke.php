<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../app/common.php';

$app = new think\App();
$app->initialize();

use app\service\CurrentFilesSeedService;
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

function scalar_count(string $table, array $where = []): int
{
    $query = Db::name($table);
    foreach ($where as $field => $value) {
        $query->where($field, $value);
    }

    return (int)$query->count();
}

$root = dirname(__DIR__);
$workspaceRoot = dirname($root);
$sourceRoot = $workspaceRoot . '/现用文件';
$urumqiEquipment = '/Users/lc.leixyz/Downloads/仪器设备（标准物质）配置信息 (2).xlsx';
$hetianEquipment = '/Users/lc.leixyz/Downloads/仪器设备（标准物质）配置信息 (3).xlsx';

assert_true(is_file($sourceRoot . '/质量手册（第四版）.docx'), 'Current quality manual exists');
assert_true(is_file($sourceRoot . '/作业指导书181201.docx'), 'Current work instruction bundle exists');
assert_true(is_file($urumqiEquipment), 'Urumqi equipment configuration workbook exists');
assert_true(is_file($hetianEquipment), 'Hetian equipment configuration workbook exists');

$schema = (string)file_get_contents($root . '/database/jewelry_qms.sql');
$console = (string)file_get_contents($root . '/config/console.php');
$equipmentView = (string)file_get_contents($root . '/app/view/equipment/view.html');
$employeeView = (string)file_get_contents($root . '/app/view/employee/view.html');

assert_contains('CREATE TABLE `employee_appointments`', $schema, 'Base schema includes employee appointments');
assert_contains('`traceability_method` varchar(50) DEFAULT NULL', $schema, 'Base schema stores equipment traceability method');
assert_contains('`traceability_due_date` date DEFAULT NULL', $schema, 'Base schema stores equipment traceability due date');
assert_contains('CurrentFilesSeed::class', $console, 'Console registers current files seed command');
assert_contains('溯源方式', $equipmentView, 'Equipment detail displays traceability method');
assert_contains('岗位任命', $employeeView, 'Employee detail displays appointments');

$summary = CurrentFilesSeedService::seed([
    'apply' => true,
    'source_root' => $sourceRoot,
    'urumqi_equipment_path' => $urumqiEquipment,
    'hetian_equipment_path' => $hetianEquipment,
]);
$snapshot = [
    'employees' => scalar_count('employees', ['soft_delete' => 0]),
    'appointments' => scalar_count('employee_appointments', ['soft_delete' => 0]),
    'work_instructions' => Db::name('documents')->where('level', 3)->whereLike('doc_number', 'XZTC/ZY-%')->where('soft_delete', 0)->count(),
    'equipment' => scalar_count('equipments', ['soft_delete' => 0]),
];
CurrentFilesSeedService::seed([
    'apply' => true,
    'source_root' => $sourceRoot,
    'urumqi_equipment_path' => $urumqiEquipment,
    'hetian_equipment_path' => $hetianEquipment,
]);
$secondSnapshot = [
    'employees' => scalar_count('employees', ['soft_delete' => 0]),
    'appointments' => scalar_count('employee_appointments', ['soft_delete' => 0]),
    'work_instructions' => Db::name('documents')->where('level', 3)->whereLike('doc_number', 'XZTC/ZY-%')->where('soft_delete', 0)->count(),
    'equipment' => scalar_count('equipments', ['soft_delete' => 0]),
];
assert_true($snapshot === $secondSnapshot, 'Current files seed is idempotent');

$companyName = (string)Db::name('companies')->where('soft_delete', 0)->value('name');
assert_true($companyName === '新疆中和鉴珠宝玉石质量检测研究所（有限公司）', 'Company name is seeded from the quality manual');

$urumqiSite = Db::name('sites')->where('code', 'MAIN')->where('soft_delete', 0)->find();
$hetianSite = Db::name('sites')->where('code', 'HETIAN')->where('soft_delete', 0)->find();
assert_true(is_array($urumqiSite) && $urumqiSite['name'] === '乌鲁木齐实验室', 'MAIN site is updated to Urumqi laboratory');
assert_true(is_array($hetianSite) && $hetianSite['name'] === '和田实验室', 'Hetian site is seeded');

assert_true((int)Db::name('qms_quality_policies')->where('is_current', 1)->where('soft_delete', 0)->count() >= 1, 'Current quality policy is seeded');
assert_true((int)Db::name('qms_quality_objectives')->where('soft_delete', 0)->count() >= 3, 'Quality objectives are seeded');

foreach (['俞炳星', '张晓磊', '曹红', '李成辉', '陈辉', '付丽', '许莉', '如则托合提', '米尔布拉', '史广', '许库尔', '毛天一', '王胜林'] as $name) {
    assert_true((int)Db::name('employees')->where('name', $name)->where('soft_delete', 0)->count() === 1, 'Employee seeded: ' . $name);
}
assert_true((int)Db::name('employee_appointments')->where('soft_delete', 0)->count() >= 25, 'Appointments are seeded from the manual');

assert_true((int)Db::name('documents')->where('doc_number', 'XZTC/SC')->where('soft_delete', 0)->count() === 1, 'Quality manual uses actual controlled number');
assert_true((int)Db::name('documents')->where('doc_number', 'XZTC/CX-01-2022')->where('soft_delete', 0)->count() === 1, 'Procedure uses XZTC/CX controlled number');
assert_true((int)Db::name('documents')->where('level', 3)->whereLike('doc_number', 'XZTC/ZY-%')->where('soft_delete', 0)->count() === 27, '27 work instructions are seeded as level-3 documents');
assert_true((int)Db::name('qms_structured_documents')->where('document_role', 'work_instruction')->whereLike('doc_number', 'XZTC/ZY-%')->where('soft_delete', 0)->count() >= 27, 'Work instructions are structured');

$urumqiEquipmentRow = Db::name('equipments')->where('equipment_number', 'XZTC-CJY01')->where('soft_delete', 0)->find();
$hetianEquipmentRow = Db::name('equipments')->where('equipment_number', 'XZTCH-CJY01')->where('soft_delete', 0)->find();
assert_true(is_array($urumqiEquipmentRow) && (string)$urumqiEquipmentRow['site_id'] === (string)$urumqiSite['id'], 'Urumqi workbook equipment maps to Urumqi site');
assert_true(is_array($hetianEquipmentRow) && (string)$hetianEquipmentRow['site_id'] === (string)$hetianSite['id'], 'Hetian workbook equipment maps to Hetian site');
assert_true((int)Db::name('equipments')->where('soft_delete', 0)->count() >= 23, 'Equipment configuration imports at least 23 devices');
assert_true((string)$urumqiEquipmentRow['traceability_method'] === '校准', 'Equipment traceability method is imported');
assert_true((string)$urumqiEquipmentRow['next_calibration_date'] === '2026-06-30', 'Calibration due date is synced only for calibration equipment');

assert_true((int)($summary['reference_materials']['created'] ?? 0) === 0, 'Standard materials are not created without formal ledger evidence');
assert_true(count($summary['missing_evidence'] ?? []) > 0, 'Seed report lists missing standard material evidence');

echo "qms_current_files_seed_smoke passed\n";
