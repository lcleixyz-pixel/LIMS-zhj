<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../app/common.php';

$app = new think\App();
$app->initialize();

use think\facade\Db;

$passes = [];
$failures = [];

function organization_case(bool $condition, string $id, string $message): void
{
    global $passes, $failures;
    if ($condition) {
        $passes[] = $id . ' ' . $message;
    } else {
        $failures[] = $id . ' ' . $message;
    }
}

$databaseName = (string)(Db::query('SELECT DATABASE() AS database_name')[0]['database_name'] ?? '');
if ($databaseName !== 'jewelry_qms_p0_r13b5') {
    fwrite(STDERR, "B5 fixture guard rejected database: {$databaseName}\n");
    exit(2);
}

$desired = [
    ['俞炳星', 'company_general_manager', 'GLOBAL'],
    ['俞炳星', 'top_management', 'GLOBAL'],
    ['俞炳星', 'authorized_signatory', 'GLOBAL'],
    ['俞炳星', 'internal_auditor', 'GLOBAL'],
    ['俞炳星', 'supervisor', 'PLACE02'],
    ['张晓磊', 'quality_manager', 'GLOBAL'],
    ['张晓磊', 'top_management', 'GLOBAL'],
    ['张晓磊', 'authorized_signatory', 'GLOBAL'],
    ['张晓磊', 'internal_auditor', 'GLOBAL'],
    ['张晓磊', 'supervisor', 'PLACE01'],
    ['张晓磊', 'system_administrator', 'GLOBAL'],
    ['刘恒春', 'site_quality_coordinator', 'GLOBAL'],
    ['刘恒春', 'technical_manager', 'GLOBAL'],
    ['刘恒春', 'authorized_signatory', 'PLACE01'],
    ['刘恒春', 'authorized_signatory', 'PLACE02'],
    ['刘恒春', 'internal_auditor', 'GLOBAL'],
    ['曹红', 'technical_manager', 'PLACE01'],
    ['曹红', 'authorized_signatory', 'PLACE01'],
    ['曹红', 'internal_auditor', 'GLOBAL'],
    ['李成辉', 'technical_manager', 'PLACE02'],
    ['李成辉', 'authorized_signatory', 'PLACE02'],
    ['付丽', 'document_controller', 'PLACE01'],
    ['王胜林', 'equipment_manager', 'PLACE01'],
    ['如则托合提', 'document_controller', 'PLACE02'],
    ['米尔布拉', 'equipment_manager', 'PLACE02'],
];

$rows = Db::name('employee_appointments')->alias('ea')
    ->join('employees e', 'e.id = ea.employee_id')
    ->join('qms_positions p', 'p.id = ea.position_id')
    ->leftJoin('sites s', 's.id = ea.site_id')
    ->whereLike('ea.appointment_key', 'b5-candidate-%')
    ->where('ea.status', 'active')
    ->where('ea.publish', 1)
    ->where('ea.soft_delete', 0)
    ->field('e.name employee_name,p.code position_code,COALESCE(s.code,"GLOBAL") site_code,ea.appointment_scope,ea.source_document_number')
    ->select()
    ->toArray();

$actualKeys = [];
foreach ($rows as $row) {
    $actualKeys[] = implode('|', [
        (string)$row['employee_name'],
        (string)$row['position_code'],
        (string)$row['site_code'],
    ]);
}
sort($actualKeys);

$desiredKeys = array_map(
    static fn (array $row): string => implode('|', $row),
    $desired
);
sort($desiredKeys);

organization_case(
    $actualKeys === $desiredKeys,
    'D01',
    '确认的 25 项人员—岗位—场所候选任命精确匹配'
);

$activeNames = Db::name('employees')
    ->whereIn('name', ['如则托合提', '米尔布拉'])
    ->where('publish', 1)
    ->where('soft_delete', 0)
    ->column('name');
sort($activeNames);
organization_case(
    $activeNames === ['如则托合提', '米尔布拉'],
    'D02',
    '现行库缺失的两名人员仅在隔离库形成候选人员'
);

$siteRows = Db::name('sites')
    ->where('publish', 1)
    ->where('soft_delete', 0)
    ->order('code', 'asc')
    ->column('name', 'code');
organization_case(
    count($siteRows) === 2
    && ($siteRows['PLACE01'] ?? '') === '乌鲁木齐实验室'
    && ($siteRows['PLACE02'] ?? '') === '和田实验室',
    'D03',
    '复用现行 PLACE01/PLACE02 场所且不生成重复场所'
);

$admin = Db::name('users')->alias('u')
    ->join('employees e', 'e.id = u.employee_id')
    ->where('u.username', 'admin')
    ->field('e.name,e.soft_delete employee_soft_delete')
    ->find();
organization_case(
    ($admin['name'] ?? '') === '张晓磊' && (int)($admin['employee_soft_delete'] ?? 1) === 0,
    'D04',
    'LIMS 系统管理员关联到在用的张晓磊员工记录'
);

$staleRows = Db::name('employee_appointments')->alias('ea')
    ->join('employees e', 'e.id = ea.employee_id')
    ->join('qms_positions p', 'p.id = ea.position_id')
    ->whereLike('ea.appointment_key', 'b5-candidate-%')
    ->where(function ($query) {
        $query
            ->where(function ($query) {
                $query->where('e.name', '曹红')->where('p.code', 'document_controller');
            })
            ->whereOr(function ($query) {
                $query->where('e.name', '张晓磊')->where('p.code', 'equipment_manager');
            })
            ->whereOr(function ($query) {
                $query->where('e.name', '李成辉')->where('p.code', 'document_controller');
            })
            ->whereOr(function ($query) {
                $query->where('e.name', '俞炳星')->where('p.code', 'quality_manager');
            });
    })
    ->count();
organization_case(
    $staleRows === 0,
    'D05',
    '旧种子中四项过时岗位不进入候选任命'
);

$liuPositions = Db::name('employee_appointments')->alias('ea')
    ->join('employees e', 'e.id = ea.employee_id')
    ->join('qms_positions p', 'p.id = ea.position_id')
    ->whereLike('ea.appointment_key', 'b5-candidate-%')
    ->where('e.name', '刘恒春')
    ->where('ea.status', 'active')
    ->column('p.code');
organization_case(
    in_array('site_quality_coordinator', $liuPositions, true)
    && in_array('technical_manager', $liuPositions, true)
    && count(array_filter($liuPositions, static fn (string $code): bool => $code === 'authorized_signatory')) === 2
    && !in_array('quality_manager', $liuPositions, true),
    'D06',
    '刘恒春按总体技术、场所协调和两场所签字授权建模，不自动取得质量负责人权限'
);

$sourceMissing = count(array_filter(
    $rows,
    static fn (array $row): bool => trim((string)$row['source_document_number']) === ''
));
organization_case(
    $sourceMissing === 0,
    'D07',
    '每项候选任命均标注确认来源'
);

foreach ($passes as $pass) {
    fwrite(STDOUT, "PASS {$pass}\n");
}
foreach ($failures as $failure) {
    fwrite(STDERR, "FAIL {$failure}\n");
}

if ($failures !== []) {
    fwrite(STDERR, sprintf(
        "qms_p0_confirmed_organization_smoke failed: %d passed, %d failed\n",
        count($passes),
        count($failures)
    ));
    exit(1);
}

fwrite(STDOUT, "qms_p0_confirmed_organization_smoke passed: D01-D07\n");
