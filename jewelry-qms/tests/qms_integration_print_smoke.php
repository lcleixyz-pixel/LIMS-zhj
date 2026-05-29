<?php
declare(strict_types=1);

$root = dirname(__DIR__);

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

function assert_file(string $path, string $message): void
{
    if (!is_file($path)) {
        fwrite(STDERR, $message . PHP_EOL);
        fwrite(STDERR, 'Missing file: ' . $path . PHP_EOL);
        exit(1);
    }
}

$schema = (string)file_get_contents($root . '/database/jewelry_qms.sql');
$migrationPath = $root . '/database/migrations/20260529_qms_integrations_controlled_print.sql';
$route = (string)file_get_contents($root . '/route/app.php');
$config = (string)file_get_contents($root . '/config/qms.php');
$documentController = (string)file_get_contents($root . '/app/controller/Document.php');
$documentView = (string)file_get_contents($root . '/app/view/document/view.html');

assert_file($migrationPath, 'Phase 4 has an idempotent migration');
$migration = (string)file_get_contents($migrationPath);

assert_contains('CREATE TABLE `controlled_print_logs`', $schema, 'Base schema includes controlled print logs');
assert_contains('CREATE TABLE IF NOT EXISTS `controlled_print_logs`', $migration, 'Migration creates controlled print logs idempotently');
assert_contains('`print_number` varchar(80) NOT NULL', $schema, 'Controlled print logs store print numbers');
assert_contains('`watermark_text` varchar(200) NOT NULL', $schema, 'Controlled print logs store watermark text');
assert_contains('KEY `document_id` (`document_id`)', $schema, 'Controlled print logs can be queried by document');

foreach ([
    "api/v1/employees",
    "api/v1/equipments",
    "api/v1/customers",
    "document/onlyoffice",
    "document/controlledPrint",
] as $path) {
    assert_contains($path, $route, 'Route exposes ' . $path);
}

assert_contains("'integration' =>", $config, 'Config exposes read-only integration settings');
assert_contains("'onlyoffice' =>", $config, 'Config exposes ONLYOFFICE settings');
assert_contains('QMS_API_TOKEN', $config, 'Integration API token can be configured through environment');
assert_contains('ONLYOFFICE_SERVER_URL', $config, 'ONLYOFFICE server URL can be configured through environment');

assert_file($root . '/app/controller/Api.php', 'Read-only API controller exists');
assert_file($root . '/app/model/ControlledPrintLog.php', 'Controlled print log model exists');
assert_file($root . '/app/service/ControlledPrintService.php', 'Controlled print service exists');
assert_file($root . '/app/view/document/onlyoffice.html', 'ONLYOFFICE document entry view exists');
assert_file($root . '/app/view/document/controlled_print.html', 'Controlled print view exists');

$apiController = (string)file_get_contents($root . '/app/controller/Api.php');
$printService = (string)file_get_contents($root . '/app/service/ControlledPrintService.php');
$onlyofficeView = (string)file_get_contents($root . '/app/view/document/onlyoffice.html');
$controlledPrintView = (string)file_get_contents($root . '/app/view/document/controlled_print.html');

foreach (['employees', 'equipments', 'customers', 'authorize'] as $method) {
    assert_contains('function ' . $method, $apiController, 'API controller implements ' . $method . ' endpoint');
}
assert_contains('function onlyoffice', $documentController, 'Document controller exposes ONLYOFFICE entry');
assert_contains('function controlledPrint', $documentController, 'Document controller exposes controlled print entry');
assert_contains('ControlledPrintService::recentLogs', $documentController, 'Document detail loads recent print logs');
assert_contains('/document/onlyoffice', $documentView, 'Document detail links to ONLYOFFICE entry');
assert_contains('/document/controlledPrint', $documentView, 'Document detail links to controlled print');
assert_contains('ONLYOFFICE', $onlyofficeView, 'ONLYOFFICE view is labelled');
assert_contains('DocsAPI.DocEditor', $onlyofficeView, 'ONLYOFFICE view can initialize DocumentEditor when configured');
assert_contains('受控打印', $controlledPrintView, 'Controlled print view is labelled');
assert_contains('watermark', $controlledPrintView, 'Controlled print view renders a watermark');
assert_contains('function createLog', $printService, 'Controlled print service creates print logs');
assert_contains('function watermarkCode', $printService, 'Controlled print service creates stable watermark codes');

require $root . '/vendor/autoload.php';
require $root . '/app/common.php';

$app = new think\App();
$app->initialize();

use app\model\Document as DocumentModel;
use app\service\ControlledPrintService;
use think\facade\Db;

foreach (array_filter(array_map('trim', explode(';', $migration))) as $statement) {
    if ($statement === '' || str_starts_with($statement, '--')) {
        continue;
    }
    Db::execute($statement);
}

$docId = 'smoke-controlled-print-doc';
$cleanup = static function () use ($docId): void {
    Db::table('controlled_print_logs')->where('document_id', $docId)->delete();
    Db::table('documents')->where('id', $docId)->delete();
};
$cleanup();
register_shutdown_function($cleanup);

Db::table('documents')->insert([
    'id' => $docId,
    'company_id' => '00000000-0000-0000-0000-000000000001',
    'level' => 2,
    'doc_number' => 'QMS-SMOKE-PRINT',
    'title' => '受控打印 Smoke 文件',
    'version' => 'A/0',
    'revision' => 0,
    'status' => 'published',
    'file_path' => '',
    'file_name' => '',
    'publish' => 1,
    'soft_delete' => 0,
    'created' => date('Y-m-d H:i:s'),
]);

$doc = DocumentModel::find($docId);
assert_true($doc instanceof DocumentModel, 'Smoke document fixture was inserted');

$printLog = ControlledPrintService::createLog($doc, 2, 'Phase 4 smoke', '127.0.0.1');
assert_true((string)$printLog->document_id === $docId, 'Controlled print log keeps the document id');
assert_true((int)$printLog->copy_count === 2, 'Controlled print log keeps requested copy count');
assert_true(str_contains((string)$printLog->watermark_text, 'QMS-SMOKE-PRINT'), 'Watermark contains document number');
assert_true(Db::table('controlled_print_logs')->where('document_id', $docId)->count() === 1, 'Controlled print log is persisted once');

$recentLogs = ControlledPrintService::recentLogs($docId);
assert_true(count($recentLogs) === 1, 'Controlled print service can query recent logs');

echo "qms_integration_print_smoke passed\n";
