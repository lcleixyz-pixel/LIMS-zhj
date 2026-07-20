<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$failures = [];
$passes = [];

function ct_source(string $path): string
{
    global $root;
    $file = $root . '/' . $path;

    return is_file($file) ? (string)file_get_contents($file) : '';
}

function ct_check(bool $condition, string $id, string $message): void
{
    global $failures, $passes;
    if ($condition) {
        $passes[] = "{$id} {$message}";
    } else {
        $failures[] = "{$id} {$message}";
    }
}

$service = ct_source('app/service/CoreTrialTemplateService.php');
$print = ct_source('app/record_form_print/rf_xztc_bg_20_06_gr14.php');
$controller = ct_source('app/controller/RecordFormTemplate.php');
$view = ct_source('app/view/record_form_template/view.html');
$routes = ct_source('route/app.php');

$canonicalNumbers = [
    'XZTC/BG-02-01', 'XZTC/BG-05-01',
    'XZTC/BG-20-01', 'XZTC/BG-20-02', 'XZTC/BG-20-06', 'XZTC/BG-20-08',
    'XZTC/BG-31-02',
    'XZTC/BG-30-01', 'XZTC/BG-30-02', 'XZTC/BG-30-04',
    'XZTC/BG-21-02',
    'XZTC/BG-19-01', 'XZTC/BG-19-02', 'XZTC/BG-19-03', 'XZTC/BG-19-04',
];
foreach ($canonicalNumbers as $number) {
    ct_check(str_contains($service, "'{$number}'"), 'CT-' . substr($number, -5), "{$number} 在核心模板矩阵中");
}

ct_check(
    str_contains($service, 'SIM-TPL-MR-REPORT')
    && str_contains($service, 'canonical_doc_number')
    && str_contains($service, 'trial_of_template_id'),
    'CT01',
    '试运行编号与正式编号显式映射且管理评审使用临时编号'
);

ct_check(
    str_contains($service, "'status' => 'draft'")
    && str_contains($service, "if ((string)\$existing->status !== 'draft')")
    && !str_contains($service, "'status' => 'trial_ready'"),
    'CT02',
    '准备动作只建立草稿，不绕过人工试运行批准'
);

ct_check(
    str_contains($service, 'procedure_doc_id')
    && str_contains($service, 'applicable_sites')
    && str_contains($service, 'responsible_position_code')
    && str_contains($service, 'retention_period')
    && str_contains($service, 'field_schema')
    && str_contains($service, 'print_template_key'),
    'CT03',
    '每个模板携带来源、场所、岗位、期限、字段契约和打印模板'
);

ct_check(
    str_contains($service, 'rf_xztc_bg_20_06_gr14')
    && str_contains($service, '检查/发现明细')
    && str_contains($print, '_reconstructed_record_form.php'),
    'CT04',
    '缺失的 BG-20-06 使用独立字段契约和打印入口重建'
);

ct_check(
    str_contains($controller, 'prepareCoreTrialTemplates')
    && str_contains($controller, 'CoreTrialTemplateService::prepare')
    && str_contains($routes, "Route::post('record_form_template/prepareCoreTrialTemplates'")
    && str_contains($view, '/record_form_template/approveTrial'),
    'CT05',
    '核心模板准备和逐项人工批准均有受控入口'
);

ct_check(
    str_contains($controller, "View::assign('procedureDocument'")
    && str_contains($view, '来源程序')
    && str_contains($view, 'procedureDocument.doc_number')
    && str_contains($view, 'procedureDocument.title'),
    'CT06',
    '模板详情直接展示来源程序，关系图尚未建立时仍可追溯'
);

foreach ($passes as $pass) {
    echo "[PASS] {$pass}\n";
}
foreach ($failures as $failure) {
    fwrite(STDERR, "[FAIL] {$failure}\n");
}
exit($failures === [] ? 0 : 1);
