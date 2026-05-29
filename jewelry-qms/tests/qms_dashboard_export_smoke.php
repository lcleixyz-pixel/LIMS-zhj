<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../app/common.php';

$app = new think\App();
$app->initialize();

use app\controller\CrudBase;
use app\service\DashboardMetricService;

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

$root = dirname(__DIR__);
$dashboardController = (string)file_get_contents($root . '/app/controller/Dashboard.php');
$dashboardView = (string)file_get_contents($root . '/app/view/dashboard/index.html');
$route = (string)file_get_contents($root . '/route/app.php');
$crudBase = (string)file_get_contents($root . '/app/controller/CrudBase.php');
$css = (string)file_get_contents($root . '/public/static/css/qms.css');

assert_true(class_exists(DashboardMetricService::class), 'DashboardMetricService exists');
foreach (['chartData', 'capaTrend', 'calibrationResultDistribution', 'trainingCoverage', 'auditFindingDistribution'] as $method) {
    assert_true(method_exists(DashboardMetricService::class, $method), 'DashboardMetricService supports ' . $method);
}

$chartData = DashboardMetricService::chartData();
foreach (['capaTrend', 'calibrationResults', 'trainingCoverage', 'auditFindings'] as $key) {
    assert_true(array_key_exists($key, $chartData), 'Chart data includes ' . $key);
}

assert_contains('DashboardMetricService::chartData', $dashboardController, 'Dashboard controller assigns chart data');
assert_contains('chartDataJson', $dashboardController, 'Dashboard controller exposes chartDataJson');
assert_contains('echarts.min.js', $dashboardView, 'Dashboard view loads ECharts');
foreach (['capaTrendChart', 'calibrationResultChart', 'trainingCoverageChart', 'auditFindingChart'] as $id) {
    assert_contains($id, $dashboardView, 'Dashboard view includes chart container ' . $id);
}
assert_contains('qms-dashboard-chart', $css, 'Dashboard chart CSS is present');

assert_true(method_exists(CrudBase::class, 'exportCsv'), 'CrudBase supports exportCsv');
assert_contains('exportCsv', $crudBase, 'CrudBase contains exportCsv method');
assert_contains('$path/exportCsv', $route, 'CRUD routes expose exportCsv');
assert_contains('导出CSV', $dashboardView . (string)file_get_contents($root . '/app/view/document/index.html'), 'At least one visible CSV export action exists');

echo "qms_dashboard_export_smoke passed\n";
