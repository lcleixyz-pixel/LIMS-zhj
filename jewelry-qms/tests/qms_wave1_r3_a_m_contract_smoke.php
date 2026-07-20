<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$failures = [];
$passes = [];

function w1m(bool $ok, string $id, string $msg): void
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

$template = src('app/controller/RecordFormTemplate.php');
$view = src('app/view/record_form_template/view.html');
$employeeView = src('app/view/employee/view.html');
$instanceView = src('app/view/record_form_instance/view.html');
$route = src('route/app.php');
$dashboard = src('app/controller/PlanningDashboard.php');
$periodCtrl = src('app/controller/EquipmentPeriodCheck.php');
$backfill = src('scripts/qms_document_metadata_backfill.php');

w1m(
    str_contains($template, '字段配置不能为空')
    && str_contains($template, 'RecordFormSchemaService::decode'),
    'M01',
    'R-3 empty field_schema rejected in validateTemplateInput'
);
w1m(
    str_contains($view, 'repeatable_table')
    && str_contains($view, 'field.columns')
    && str_contains($view, '子表列'),
    'M02',
    'R-3 template view expands table/repeater columns'
);
w1m(
    str_contains($employeeView, '新增监督记录')
    && str_contains($employeeView, 'employee_id='),
    'M03',
    'F-4b-04 supervision create entry present'
);
w1m(
    str_contains($periodCtrl, '机制入口')
    && str_contains($route, 'EquipmentPeriodCheck/index')
    && str_contains($route, 'EquipmentPeriodCheck/add'),
    'M04',
    'F-3a-02 EquipmentPeriodCheck stub + routes'
);
w1m(
    str_contains($dashboard, 'function batchDecide')
    && str_contains($route, 'planning/suggestions/batchDecide'),
    'M05',
    'Planning batchDecide route and method exist'
);
w1m(
    str_contains($instanceView, "qms_can_action('record_form_instance', 'edit'")
    && str_contains($instanceView, "qms_can_action('record_form_instance', 'exportPdf'"),
    'M06',
    'record_form_instance write buttons guarded by qms_can_action'
);
w1m(
    str_contains($backfill, 'dry-run')
    && str_contains($backfill, 'QMS_METADATA_BACKFILL_APPLY')
    && str_contains($backfill, 'effective_date'),
    'M07',
    'document metadata backfill script defaults to dry-run'
);

foreach ($passes as $pass) {
    echo "PASS {$pass}\n";
}
foreach ($failures as $failure) {
    fwrite(STDERR, "FAIL {$failure}\n");
}
if ($failures !== []) {
    exit(1);
}
echo "qms_wave1_r3_a_m_contract_smoke passed\n";
