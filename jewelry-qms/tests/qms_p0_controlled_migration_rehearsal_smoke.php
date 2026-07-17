<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../app/common.php';

$app = new think\App();
$app->initialize();

use app\service\P0PreflightService;
use think\facade\Db;

$phase = '';
foreach ($argv as $argument) {
    if (str_starts_with($argument, '--phase=')) {
        $phase = substr($argument, strlen('--phase='));
    }
}
if (!in_array($phase, ['migrated', 'rolled_back', 'remigrated'], true)) {
    fwrite(STDERR, "phase must be migrated, rolled_back, or remigrated\n");
    exit(2);
}

$passes = [];
$failures = [];
function b6_rehearsal_case(bool $condition, string $id, string $message): void
{
    global $passes, $failures;
    if ($condition) {
        $passes[] = $id . ' ' . $message;
    } else {
        $failures[] = $id . ' ' . $message;
    }
}

$database = (string)(Db::query('SELECT DATABASE() AS name')[0]['name'] ?? '');
if ($database !== 'jewelry_qms_p0_r13b6') {
    fwrite(STDERR, "B6 rehearsal guard rejected database: {$database}\n");
    exit(2);
}

$preflight = P0PreflightService::scan();
b6_rehearsal_case(($preflight['blocked'] ?? true) === false, 'F01', 'P0 只读预检通过');

$indexCount = (int)Db::query(
    "SELECT COUNT(DISTINCT INDEX_NAME) count FROM information_schema.STATISTICS
     WHERE TABLE_SCHEMA = DATABASE() AND INDEX_NAME IN (
       'uq_complaint_company_number',
       'uq_capa_company_number',
       'uq_nc_company_number',
       'uq_capa_company_source_record'
     )"
)[0]['count'];
$appointmentCount = (int)Db::name('employee_appointments')
    ->where('source_document_number', 'ORG-APPOINT-2026-01')
    ->where('soft_delete', 0)
    ->count();
$newPeople = Db::name('employees')
    ->whereIn('employee_number', ['E012', 'E013'])
    ->where('soft_delete', 0)
    ->column('employee_number');
sort($newPeople);
$admin = Db::name('users')->alias('u')
    ->leftJoin('employees e', 'e.id = u.employee_id')
    ->where('u.username', 'admin')
    ->where('u.soft_delete', 0)
    ->field('e.name,e.soft_delete employee_soft_delete')
    ->find();
$liuQuality = (int)Db::name('employee_appointments')->alias('ea')
    ->join('employees e', 'e.id = ea.employee_id')
    ->join('qms_positions p', 'p.id = ea.position_id')
    ->where('e.name', '刘恒春')
    ->where('p.code', 'quality_manager')
    ->where('ea.status', 'active')
    ->where('ea.soft_delete', 0)
    ->count();
$siteCodes = Db::name('sites')
    ->where('publish', 1)
    ->where('soft_delete', 0)
    ->order('code')
    ->column('code');
$testResidue = (int)Db::query(
    "SELECT
       (SELECT COUNT(*) FROM customer_complaints WHERE complaint_number LIKE 'B5-%' OR complaint_number LIKE 'SIM-%')
       + (SELECT COUNT(*) FROM capas WHERE capa_number LIKE 'B5-%' OR capa_number LIKE 'SIM-%')
       + (SELECT COUNT(*) FROM equipments WHERE equipment_number LIKE 'B5-%' OR equipment_number LIKE 'SIM-%')
       + (SELECT COUNT(*) FROM record_form_templates WHERE doc_number LIKE 'B5-%' OR doc_number LIKE 'SIM-%')
       AS count"
)[0]['count'];

if ($phase === 'rolled_back') {
    b6_rehearsal_case($appointmentCount === 0, 'F08', '行级回退撤销本包任命');
    b6_rehearsal_case($newPeople === [], 'F03', '行级回退撤销本包新增人员');
    b6_rehearsal_case(
        ($admin['name'] ?? '') === '张晓磊' && (int)($admin['employee_soft_delete'] ?? 0) === 1,
        'F05',
        '行级回退恢复 admin 原员工关联'
    );
} else {
    b6_rehearsal_case($indexCount === 4, 'F02', '四项唯一约束存在');
    b6_rehearsal_case($newPeople === ['E012', 'E013'], 'F03', '两名人员按确认编号建立');
    b6_rehearsal_case($appointmentCount === 25, 'F04', '25 项任命精确建立');
    b6_rehearsal_case(
        ($admin['name'] ?? '') === '张晓磊' && (int)($admin['employee_soft_delete'] ?? 1) === 0,
        'F05',
        'admin 指向在用张晓磊'
    );
    b6_rehearsal_case($liuQuality === 0, 'F06', '刘恒春没有常设质量负责人');
    b6_rehearsal_case($siteCodes === ['PLACE01', 'PLACE02'], 'F07', '场所仍为 PLACE01/PLACE02');
    if ($phase === 'remigrated') {
        b6_rehearsal_case(
            $appointmentCount === 25 && count($newPeople) === 2,
            'F09',
            '再迁移不重复新增'
        );
    }
}
b6_rehearsal_case($testResidue === 0, 'F10', '没有 B5/SIM 测试业务记录');

foreach ($passes as $pass) {
    fwrite(STDOUT, "PASS {$pass}\n");
}
foreach ($failures as $failure) {
    fwrite(STDERR, "FAIL {$failure}\n");
}
if ($failures !== []) {
    fwrite(STDERR, sprintf(
        "qms_p0_controlled_migration_rehearsal_smoke failed (%s): %d passed, %d failed\n",
        $phase,
        count($passes),
        count($failures)
    ));
    exit(1);
}
fwrite(STDOUT, "qms_p0_controlled_migration_rehearsal_smoke passed: {$phase}\n");
