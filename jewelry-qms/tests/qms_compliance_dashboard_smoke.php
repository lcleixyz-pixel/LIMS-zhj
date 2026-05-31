<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../app/common.php';

$app = new think\App();
$app->initialize();

use app\service\ComplianceCheckService;
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

function assert_not_contains(string $needle, string $haystack, string $message): void
{
    if (str_contains($haystack, $needle)) {
        fwrite(STDERR, $message . PHP_EOL);
        fwrite(STDERR, 'Unexpected: ' . $needle . PHP_EOL);
        exit(1);
    }
}

function ensure_compliance_table(string $name, string $ddl): void
{
    $exists = (int)Db::query(
        'SELECT COUNT(*) AS total FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
        [$name]
    )[0]['total'];
    if ($exists === 0) {
        Db::execute($ddl);
    }
}

$root = dirname(__DIR__);
$schema = (string)file_get_contents($root . '/database/jewelry_qms.sql');
$migrationPath = $root . '/database/migrations/20260530_compliance_dashboard.sql';
$migration = is_file($migrationPath) ? (string)file_get_contents($migrationPath) : '';
$console = (string)file_get_contents($root . '/config/console.php');
$route = (string)file_get_contents($root . '/route/app.php');
$rbac = (string)file_get_contents($root . '/app/middleware/Rbac.php');
$config = (string)file_get_contents($root . '/config/qms.php');
$layout = (string)file_get_contents($root . '/app/view/layout/main.html');

assert_contains('CREATE TABLE `compliance_checks`', $schema, 'Base schema defines compliance_checks');
assert_contains('CREATE TABLE `compliance_snapshots`', $schema, 'Base schema defines compliance_snapshots');
assert_contains('CREATE TABLE `compliance_check_results`', $schema, 'Base schema defines compliance_check_results');
assert_contains("enum('pass','fail','warning','insufficient_data','not_applicable')", $schema, 'Compliance results store five-state semantics');
assert_contains('CREATE TABLE IF NOT EXISTS `compliance_checks`', $migration, 'Migration creates compliance_checks idempotently');
assert_contains('CREATE TABLE IF NOT EXISTS `compliance_snapshots`', $migration, 'Migration creates compliance_snapshots idempotently');
assert_contains('CREATE TABLE IF NOT EXISTS `compliance_check_results`', $migration, 'Migration creates compliance_check_results idempotently');

assert_true(class_exists(ComplianceCheckService::class), 'ComplianceCheckService exists');
foreach ([
    'runFullAssessment',
    'getLatestScorecard',
    'getAllGaps',
    'getGapsByDimension',
    'scoreTrend',
    'seedDefaultChecks',
] as $method) {
    assert_true(method_exists(ComplianceCheckService::class, $method), 'ComplianceCheckService supports ' . $method);
}

$service = (string)file_get_contents($root . '/app/service/ComplianceCheckService.php');
assert_contains('checkRegistry', $service, 'ComplianceCheckService uses a fixed check registry');
assert_contains('insufficient_data', $service, 'Compliance service distinguishes insufficient data');
assert_contains('not_applicable', $service, 'Compliance service distinguishes not applicable checks');
assert_contains('qms_element_clause_links', $service, 'Compliance service uses real clause-element traceability');
assert_contains('qms_element_documents', $service, 'Compliance service uses real element-document traceability');
assert_contains('qms_document_block_links', $service, 'Compliance service uses real document block traceability');
assert_contains("status', 'locked'", $service, 'Runtime evidence checks locked record form instances');
assert_contains("valid_until", $service, 'Compliance service checks valid_until fields');
assert_contains("'qualified'", $service, 'Competency check requires qualified result');
assert_not_contains('planning_traceability', $service, 'Compliance service does not reference old planning_traceability table');
assert_not_contains('expiry_date', $service, 'Compliance service does not reference non-existent expiry_date fields');

assert_contains('app\\command\\ComplianceAssess', $console, 'Console registers ComplianceAssess command');
assert_contains("Route::get('compliance/index'", $route, 'Routes expose compliance index');
assert_contains("Route::post('compliance/refresh'", $route, 'Routes expose manual compliance refresh');
assert_contains("Route::get('compliance/dimension'", $route, 'Routes expose compliance dimension detail');
assert_contains("Route::post('compliance/seed'", $route, 'Routes expose compliance rule seed');
assert_contains("'compliance'", $config, 'RBAC permissions include compliance module');
assert_contains("'refresh'", $rbac, 'RBAC treats compliance refresh as write action');
assert_contains("'seed'", $rbac, 'RBAC treats compliance seed as write action');
assert_contains('/compliance/index', $layout, 'Main navigation exposes audit readiness dashboard');

assert_true(class_exists(\app\controller\Compliance::class), 'Compliance controller exists and matches RBAC module name');
assert_true(class_exists(\app\command\ComplianceAssess::class), 'ComplianceAssess command exists');
assert_true(is_file($root . '/app/view/compliance/index.html'), 'Compliance index view exists');
assert_true(is_file($root . '/app/view/compliance/dimension.html'), 'Compliance dimension view exists');

$view = (string)file_get_contents($root . '/app/view/compliance/index.html');
assert_contains('审核准备驾驶舱', $view, 'Compliance index view uses the expected Chinese title');
assert_contains('数据不足', $view, 'Compliance index view marks insufficient data gaps');
assert_contains('暂无检查项', $view, 'Compliance index view labels dimensions without checks');
assert_contains('echarts.min.js', $view, 'Compliance index view renders ECharts trend chart');
assert_not_contains('AI 建议区', $view, 'Compliance index view does not show an AI placeholder');

$dimensionView = (string)file_get_contents($root . '/app/view/compliance/dimension.html');
assert_contains('暂无检查项', $dimensionView, 'Compliance dimension view explains dimensions without checks');

ensure_compliance_table('compliance_checks', "CREATE TABLE IF NOT EXISTS `compliance_checks` (
  `id` varchar(36) NOT NULL,
  `company_id` varchar(36) NOT NULL,
  `clause_number` varchar(20) DEFAULT NULL,
  `element_key` varchar(50) DEFAULT NULL,
  `dimension` enum('personnel','equipment','material','method','environment','document','record','management') NOT NULL,
  `check_code` varchar(100) NOT NULL,
  `check_name` varchar(200) NOT NULL,
  `check_description` text,
  `severity` enum('critical','major','minor') NOT NULL DEFAULT 'major',
  `weight` decimal(5,2) NOT NULL DEFAULT 1.00,
  `suggestion_template` text,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int NOT NULL DEFAULT 0,
  `soft_delete` tinyint(1) DEFAULT 0,
  `created` datetime DEFAULT NULL,
  `modified` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `company_check_code` (`company_id`,`check_code`),
  KEY `idx_dimension` (`dimension`),
  KEY `idx_element_key` (`element_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
ensure_compliance_table('compliance_snapshots', "CREATE TABLE IF NOT EXISTS `compliance_snapshots` (
  `id` varchar(36) NOT NULL,
  `company_id` varchar(36) NOT NULL,
  `snapshot_time` datetime NOT NULL,
  `trigger_type` enum('scheduled','manual') NOT NULL DEFAULT 'manual',
  `total_score` decimal(5,2) NOT NULL DEFAULT 0.00,
  `dimension_scores` json NOT NULL,
  `summary` json NOT NULL,
  `created_by` varchar(36) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_company_time` (`company_id`,`snapshot_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
ensure_compliance_table('compliance_check_results', "CREATE TABLE IF NOT EXISTS `compliance_check_results` (
  `id` varchar(36) NOT NULL,
  `snapshot_id` varchar(36) NOT NULL,
  `check_id` varchar(36) NOT NULL,
  `check_code` varchar(100) NOT NULL,
  `dimension` varchar(30) NOT NULL,
  `status` enum('pass','fail','warning','insufficient_data','not_applicable') NOT NULL,
  `score` decimal(5,4) DEFAULT NULL,
  `total_checked` int NOT NULL DEFAULT 0,
  `fail_count` int NOT NULL DEFAULT 0,
  `warning_count` int NOT NULL DEFAULT 0,
  `fail_items` json DEFAULT NULL,
  `checked_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_snapshot` (`snapshot_id`),
  KEY `idx_check_code` (`check_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$companyId = (string)Config::get('qms.company_id');
$seeded = ComplianceCheckService::seedDefaultChecks($companyId);
$seededAgain = ComplianceCheckService::seedDefaultChecks($companyId);
assert_true($seeded >= 0, 'Compliance default checks seed without error');
assert_true($seededAgain === 0, 'Compliance default checks seed idempotently');
$checkCount = Db::name('compliance_checks')->where('company_id', $companyId)->where('soft_delete', 0)->count();
assert_true($checkCount >= 16, 'Compliance default registry stores at least 16 checks');

$beforeSnapshots = Db::name('compliance_snapshots')->where('company_id', $companyId)->count();
$assessment = ComplianceCheckService::runFullAssessment($companyId, 'manual', null);
$afterSnapshots = Db::name('compliance_snapshots')->where('company_id', $companyId)->count();
assert_true($afterSnapshots === $beforeSnapshots + 1, 'Full assessment creates one new snapshot');
assert_true(array_key_exists('total_score', $assessment), 'Assessment returns total_score');
assert_true(array_key_exists('dimension_scores', $assessment), 'Assessment returns dimension_scores');
assert_true(array_key_exists('summary', $assessment), 'Assessment returns summary');
assert_true(array_key_exists('insufficient_data', $assessment['summary']), 'Assessment summary tracks insufficient data');
assert_true((float)$assessment['total_score'] >= 0.0 && (float)$assessment['total_score'] <= 100.0, 'Assessment score is a percentage');

$latest = ComplianceCheckService::getLatestScorecard($companyId);
assert_true(is_array($latest) && (string)$latest['snapshot_id'] === (string)$assessment['snapshot_id'], 'Latest scorecard returns the newest snapshot');
$gaps = ComplianceCheckService::getAllGaps($companyId);
foreach ($gaps as $gap) {
    assert_true(in_array($gap['status'], ['fail', 'insufficient_data'], true), 'All gap rows are fail or insufficient_data');
}
$trend = ComplianceCheckService::scoreTrend($companyId, 5);
assert_true(array_key_exists('labels', $trend) && array_key_exists('scores', $trend), 'Score trend returns chart labels and scores');

echo "qms_compliance_dashboard_smoke passed\n";
