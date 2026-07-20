<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$failures = [];
$passes = [];

function ee_source(string $path): string
{
    global $root;
    $file = $root . '/' . $path;

    return is_file($file) ? (string)file_get_contents($file) : '';
}

function ee_check(bool $condition, string $id, string $message): void
{
    global $failures, $passes;
    if ($condition) {
        $passes[] = "{$id} {$message}";
    } else {
        $failures[] = "{$id} {$message}";
    }
}

$controller = ee_source('app/controller/ExternalEvidenceReference.php');
$service = ee_source('app/service/ExternalEvidenceReferenceService.php');
$partial = ee_source('app/view/common/external_evidence_references.html');
$routes = ee_source('route/app.php');
$controllers = implode("\n", array_map('ee_source', [
    'app/controller/AuditFinding.php',
    'app/controller/Complaint.php',
    'app/controller/Capa.php',
    'app/controller/Nonconformity.php',
    'app/controller/ManagementReview.php',
]));
$views = implode("\n", array_map('ee_source', [
    'app/view/audit_finding/view.html',
    'app/view/complaint/view.html',
    'app/view/capa/view.html',
    'app/view/nonconformity/view.html',
    'app/view/management_review/view.html',
]));

ee_check(
    str_contains($controller, 'class ExternalEvidenceReference')
    && str_contains($controller, 'ExternalEvidenceReferenceService::create')
    && str_contains($routes, "Route::post('external_evidence_reference/add'"),
    'EE01',
    '外部证据通过统一 POST 入口登记'
);

ee_check(
    str_contains($service, 'SUBJECT_TABLES')
    && str_contains($service, 'subjectExists')
    && str_contains($service, 'array_intersect_key'),
    'EE02',
    '服务端验证关联对象并仅接受白名单字段'
);

ee_check(
    str_contains($partial, '来源系统')
    && str_contains($partial, '外部编号')
    && str_contains($partial, '校验摘要')
    && str_contains($partial, '只读链接')
    && str_contains($partial, 'target="_blank"'),
    'EE03',
    '统一组件展示约定元数据和只读链接'
);

foreach (['audit', 'complaint', 'capa', 'quality_event', 'management_review'] as $subjectType) {
    ee_check(
        str_contains($controllers, "'{$subjectType}'"),
        'EE-' . strtoupper(substr($subjectType, 0, 3)),
        "{$subjectType} 详情绑定外部证据"
    );
}

ee_check(
    substr_count($views, 'common/external_evidence_references') >= 5,
    'EE04',
    '五类质量活动详情页均嵌入统一证据组件'
);

foreach ($passes as $pass) {
    echo "[PASS] {$pass}\n";
}
foreach ($failures as $failure) {
    fwrite(STDERR, "[FAIL] {$failure}\n");
}
exit($failures === [] ? 0 : 1);
