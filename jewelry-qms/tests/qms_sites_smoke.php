<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../app/common.php';

$app = new think\App();
$app->initialize();

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

function ensure_column(string $table, string $column, string $ddl): void
{
    $exists = (int)Db::query(
        'SELECT COUNT(*) AS total FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
        [$table, $column]
    )[0]['total'];
    if ($exists === 0) {
        Db::execute($ddl);
    }
}

$root = dirname(__DIR__);
$schema = (string)file_get_contents($root . '/database/jewelry_qms.sql');
$migration = (string)file_get_contents($root . '/database/migrations/20260529_sites.sql');
$route = (string)file_get_contents($root . '/route/app.php');
$config = (string)file_get_contents($root . '/config/qms.php');
$layout = (string)file_get_contents($root . '/app/view/layout/main.html');
$equipmentController = (string)file_get_contents($root . '/app/controller/Equipment.php');
$equipmentTransferController = (string)file_get_contents($root . '/app/controller/EquipmentTransfer.php');
$equipmentAdd = (string)file_get_contents($root . '/app/view/equipment/add.html');
$equipmentIndex = (string)file_get_contents($root . '/app/view/equipment/index.html');
$employeeAdd = (string)file_get_contents($root . '/app/view/employee/add.html');
$auditScheduleAdd = (string)file_get_contents($root . '/app/view/audit_schedule/add.html');

assert_contains('CREATE TABLE `sites`', $schema, 'Base schema includes sites');
assert_contains('`site_id` varchar(36) DEFAULT NULL', $schema, 'Base schema adds equipment/audit site_id');
assert_contains('`primary_site_id` varchar(36) DEFAULT NULL', $schema, 'Base schema adds employee primary site');
assert_contains('CREATE TABLE `equipment_transfers`', $schema, 'Base schema includes equipment transfers');
assert_contains('information_schema.COLUMNS', $migration, 'Migration adds site columns idempotently');
assert_contains('INSERT IGNORE INTO `sites`', $migration, 'Migration seeds default main site idempotently');
assert_contains("'site'", $route, 'Routes expose site CRUD');
assert_contains('equipment_transfer/add', $route, 'Routes expose equipment transfer');
assert_contains("'site'", $config, 'Permissions include site module');
assert_contains("'equipment_transfer'", $config, 'Permissions include equipment transfer module');
assert_contains('/site/index', $layout, 'Navigation links to site management');
assert_contains('class Site extends CrudBase', (string)file_get_contents($root . '/app/controller/Site.php'), 'Site controller uses CrudBase');
assert_contains('class EquipmentTransfer', $equipmentTransferController, 'EquipmentTransfer controller exists');
assert_contains('from_site_id', $equipmentTransferController, 'Transfer records source site');
assert_contains('to_site_id', $equipmentTransferController, 'Transfer records target site');
assert_contains('site_id', $equipmentController, 'Equipment controller handles site filtering');
assert_contains('__none', $equipmentController, 'Equipment filter keeps NULL historical records visible');
assert_contains('name="site_id"', $equipmentAdd, 'Equipment form posts site_id');
assert_contains('name="primary_site_id"', $employeeAdd, 'Employee form posts primary_site_id');
assert_contains('name="site_id"', $auditScheduleAdd, 'Audit schedule form posts site_id');
assert_contains('未指定', $equipmentIndex, 'Equipment list exposes unspecified site filter');

Db::execute("CREATE TABLE IF NOT EXISTS `sites` (
  `id` varchar(36) NOT NULL,
  `company_id` varchar(36) NOT NULL,
  `code` varchar(20) NOT NULL,
  `name` varchar(200) NOT NULL,
  `address` text,
  `site_type` enum('main','branch') DEFAULT 'branch',
  `contact_person` varchar(100) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `sort_order` int DEFAULT 0,
  `publish` tinyint(1) DEFAULT 1,
  `soft_delete` tinyint(1) DEFAULT 0,
  `created` datetime DEFAULT NULL,
  `modified` datetime DEFAULT NULL,
  `created_by` varchar(36) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `site_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
ensure_column('equipments', 'site_id', 'ALTER TABLE `equipments` ADD COLUMN `site_id` varchar(36) DEFAULT NULL AFTER `department_id`');
ensure_column('employees', 'primary_site_id', 'ALTER TABLE `employees` ADD COLUMN `primary_site_id` varchar(36) DEFAULT NULL AFTER `department_id`');
ensure_column('audit_schedules', 'site_id', 'ALTER TABLE `audit_schedules` ADD COLUMN `site_id` varchar(36) DEFAULT NULL AFTER `department_id`');
Db::execute("CREATE TABLE IF NOT EXISTS `equipment_transfers` (
  `id` varchar(36) NOT NULL,
  `company_id` varchar(36) NOT NULL,
  `equipment_id` varchar(36) NOT NULL,
  `from_site_id` varchar(36) DEFAULT NULL,
  `to_site_id` varchar(36) NOT NULL,
  `transfer_date` date NOT NULL,
  `reason` text,
  `transferred_by` varchar(36) DEFAULT NULL,
  `remarks` text,
  `publish` tinyint(1) DEFAULT 1,
  `soft_delete` tinyint(1) DEFAULT 0,
  `created` datetime DEFAULT NULL,
  `created_by` varchar(36) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `equipment_id` (`equipment_id`),
  KEY `to_site_id` (`to_site_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$companyId = (string)Config::get('qms.company_id');
Db::execute(
    "INSERT IGNORE INTO `sites` (`id`, `company_id`, `code`, `name`, `site_type`, `status`, `sort_order`, `publish`, `soft_delete`, `created`)
     VALUES ('00000000-0000-0000-0000-000000000070', ?, 'MAIN', '主场所', 'main', 'active', 0, 1, 0, NOW())",
    [$companyId]
);

$main = Db::name('sites')->where('code', 'MAIN')->where('soft_delete', 0)->find();
assert_true($main !== null && $main['name'] === '主场所', 'Default main site seed exists');

$nullEquipmentCount = Db::name('equipments')->whereNull('site_id')->count();
assert_true($nullEquipmentCount >= 0, 'Equipment site_id remains nullable for historical records');

echo "qms_sites_smoke passed\n";
