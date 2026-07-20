<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$failures = [];
$passes = [];

function w1r5(bool $ok, string $id, string $msg): void
{
    global $passes, $failures;
    if ($ok) {
        $passes[] = "{$id} {$msg}";
    } else {
        $failures[] = "{$id} {$msg}";
    }
}

function src(string $rel): string
{
    global $root;
    $path = $root . '/' . $rel;

    return is_file($path) ? (string)file_get_contents($path) : '';
}

$employee = src('app/controller/Employee.php');
$calibration = src('app/controller/Calibration.php');
$candidate = src('app/controller/PlanningRegulatoryCandidate.php');
$migration = src('database/migrations/20260720_wave1_uniqueness.sql');

w1r5(
    str_contains($employee, 'uniqueModelFieldRule')
    && str_contains($employee, 'employee_number')
    && str_contains($employee, 'email')
    && str_contains($employee, '员工编号已存在')
    && str_contains($employee, '邮箱已存在'),
    'R501',
    'Employee add/edit uniqueness checks for employee_number and email'
);
w1r5(
    str_contains($calibration, 'validateFormData')
    && str_contains($calibration, 'calibration_org')
    && str_contains($calibration, 'certificate_number')
    && str_contains($calibration, "'pass', '合格'"),
    'R502',
    'Calibration requires org/certificate when result is pass/合格'
);
w1r5(
    str_contains($candidate, 'validateReferencedClauseNumbers')
    && str_contains($candidate, 'qms_clauses')
    && str_contains($candidate, '条款号不存在于现行条款库'),
    'R503',
    'PlanningRegulatoryCandidate rejects unknown clause numbers'
);
w1r5(
    str_contains($migration, 'uq_employees_employee_number')
    && str_contains($migration, 'equipment_period_checks'),
    'R504',
    'Migration SQL adds uniqueness index and period-check stub table'
);

require $root . '/vendor/autoload.php';
require $root . '/app/common.php';
$app = new think\App();
$app->initialize();

use app\controller\Calibration;
use app\controller\Employee;
use think\facade\Config;
use think\facade\Db;

$employeeCtrl = new ReflectionClass(Employee::class);
$rulesMethod = $employeeCtrl->getMethod('validationRules');
$rulesMethod->setAccessible(true);
$employeeInstance = $employeeCtrl->newInstanceWithoutConstructor();
$rules = $rulesMethod->invoke($employeeInstance, [], null);
w1r5(
    isset($rules['employee_number']) && isset($rules['email']),
    'R505',
    'Employee validationRules exposes uniqueness closures'
);

$calCtrl = new ReflectionClass(Calibration::class);
$validate = $calCtrl->getMethod('validateFormData');
$validate->setAccessible(true);
$calInstance = $calCtrl->newInstanceWithoutConstructor();
$errors = $validate->invoke($calInstance, [
    'equipment_id' => 'x',
    'calibration_date' => '2026-07-20',
    'result' => 'pass',
    'calibration_org' => '',
    'certificate_number' => '',
], null);
w1r5(
    count($errors) >= 2
    && str_contains(implode('|', $errors), '校准机构')
    && str_contains(implode('|', $errors), '证书编号'),
    'R506',
    'Calibration validateFormData blocks pass without org/certificate'
);

$ids = [
    'e1' => 'b5000000-0000-4000-8000-000000000201',
    'e2' => 'b5000000-0000-4000-8000-000000000202',
];
$companyId = (string)Config::get('qms.company_id');
$now = '2026-07-20 11:00:00';
try {
    Db::name('employees')->whereIn('id', array_values($ids))->delete();
    Db::name('employees')->insert([
        'id' => $ids['e1'],
        'company_id' => $companyId,
        'employee_number' => 'WAVE1-R5-DUP',
        'name' => '查重甲',
        'email' => 'wave1-r5-dup@example.invalid',
        'publish' => 1,
        'soft_delete' => 0,
        'created' => $now,
        'modified' => $now,
    ]);
    $uniqueNumber = $rulesMethod->invoke($employeeInstance, [], null)['employee_number'];
    $uniqueEmail = $rulesMethod->invoke($employeeInstance, [], null)['email'];
    $numberResult = $uniqueNumber('WAVE1-R5-DUP');
    $emailResult = $uniqueEmail('wave1-r5-dup@example.invalid');
    w1r5($numberResult !== true, 'R507', 'duplicate employee_number rejected by unique rule');
    w1r5($emailResult !== true, 'R508', 'duplicate email rejected by unique rule');
} catch (Throwable $exception) {
    w1r5(false, 'R5xx', 'runtime uniqueness check failed: ' . $exception->getMessage());
} finally {
    Db::name('employees')->whereIn('id', array_values($ids))->delete();
}

foreach ($passes as $pass) {
    echo "PASS {$pass}\n";
}
foreach ($failures as $failure) {
    fwrite(STDERR, "FAIL {$failure}\n");
}
if ($failures !== []) {
    exit(1);
}
echo "qms_wave1_r5_uniqueness_smoke passed\n";
