<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../app/common.php';

$app = new think\App();
$app->initialize();

class ValidationSmokeController extends \app\controller\CrudBase
{
    protected string $modelClass = \app\model\Department::class;
    protected string $viewPrefix = 'department';
    protected string $pageTitle = '测试';

    protected array $validateRules = [
        'name' => 'require',
        'due_date' => 'date',
    ];

    protected array $validateMessages = [
        'name.require' => '名称必填',
        'due_date.date' => '日期格式错误',
    ];
}

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
$crud = (string)file_get_contents($root . '/app/controller/CrudBase.php');
$layout = (string)file_get_contents($root . '/app/view/layout/main.html');
$equipment = (string)file_get_contents($root . '/app/controller/Equipment.php');
$capa = (string)file_get_contents($root . '/app/controller/Capa.php');
$calibration = (string)file_get_contents($root . '/app/controller/Calibration.php');
$calibrationAdd = (string)file_get_contents($root . '/app/view/calibration/add.html');
$auditFinding = (string)file_get_contents($root . '/app/controller/AuditFinding.php');
$complaint = (string)file_get_contents($root . '/app/controller/Complaint.php');
$document = (string)file_get_contents($root . '/app/controller/Document.php');
$recordTemplate = (string)file_get_contents($root . '/app/controller/RecordFormTemplate.php');
$planningSource = (string)file_get_contents($root . '/app/controller/PlanningSource.php');

assert_contains('protected array $validateRules', $crud, 'CrudBase exposes validateRules');
assert_contains('protected array $validateMessages', $crud, 'CrudBase exposes validateMessages');
assert_contains('validateFormData($data', $crud, 'CrudBase validates add/edit payloads before save');
assert_contains("Session::flash('validation_errors'", $crud, 'CrudBase flashes field validation errors');
assert_contains('validation_errors', $layout, 'Layout renders validation errors');
assert_contains('请修正', $layout, 'Layout uses a friendly validation heading');

assert_contains('equipment_number', $equipment, 'Equipment validates equipment number');
assert_contains('uniqueModelFieldRule', $equipment, 'Equipment validates unique equipment number');
assert_contains('capa_number', $capa, 'CAPA validates CAPA number');
assert_contains('description', $capa, 'CAPA validates description');
assert_contains('due_date', $capa, 'CAPA validates due date format');
assert_contains('equipment_id', $calibration, 'Calibration validates equipment selection');
assert_contains('calibration_date', $calibration, 'Calibration validates calibration date');
assert_contains('name="equipment_id"', $calibrationAdd, 'Calibration form posts equipment_id');
assert_contains('name="calibration_date"', $calibrationAdd, 'Calibration form posts calibration_date');
assert_contains('description', $auditFinding, 'AuditFinding validates description');
assert_contains('customer_name', $complaint, 'Complaint validates customer name');
assert_contains('validateDocumentInput', $document, 'Document custom controller has validation');
assert_contains('doc_number', $document, 'Document validates doc number');
assert_contains('uniqueDocumentNumberRule', $document, 'Document validates unique doc number');
assert_contains("Session::flash('validation_errors'", $recordTemplate, 'RecordFormTemplate uses unified validation errors');
assert_contains('请选择外部依据文件', $planningSource, 'PlanningSource validates required upload file');
assert_contains('外部依据仅支持 PDF、Word 文件', $planningSource, 'PlanningSource validates upload file type');

$controller = new ValidationSmokeController($app);
$method = new ReflectionMethod($controller, 'validateFormData');

$errors = $method->invoke($controller, ['due_date' => 'bad-date'], null);
assert_true(in_array('名称必填', $errors, true), 'Runtime validation catches required fields');
assert_true(in_array('日期格式错误', $errors, true), 'Runtime validation catches invalid date format');

$errors = $method->invoke($controller, ['name' => '有效名称', 'due_date' => '2026-05-29'], null);
assert_true($errors === [], 'Runtime validation accepts valid data');

echo "qms_validation_smoke passed\n";
