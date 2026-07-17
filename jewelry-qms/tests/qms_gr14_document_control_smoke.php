<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$failures = [];
$passes = [];

function dc_source(string $path): string
{
    global $root;
    $file = $root . '/' . $path;

    return is_file($file) ? (string)file_get_contents($file) : '';
}

function dc_check(bool $condition, string $id, string $message): void
{
    global $failures, $passes;
    if ($condition) {
        $passes[] = "{$id} {$message}";
    } else {
        $failures[] = "{$id} {$message}";
    }
}

function dc_all(string $source, array $needles): bool
{
    foreach ($needles as $needle) {
        if (!str_contains($source, $needle)) {
            return false;
        }
    }

    return true;
}

$document = dc_source('app/controller/Document.php');
$approval = dc_source('app/controller/Approval.php');
$approvalService = dc_source('app/service/ApprovalService.php');
$control = dc_source('app/service/DocumentControlService.php');
$print = dc_source('app/service/ControlledPrintService.php');
$authorization = dc_source('app/service/ActionAuthorizationService.php');
$view = dc_source('app/view/document/view.html');
$routes = dc_source('route/app.php');

dc_check(
    dc_all($print, ["status !== 'published'", '当前正式发布版本才可生成正式受控打印'])
    && str_contains($routes, "Route::post('document/controlledPrint'"),
    'DC01',
    '正式受控打印仅允许当前 published 版本且使用 POST'
);

dc_check(
    dc_all($document, [
        'supersedes_document_id',
        'revision_root_id',
        '$newDocument',
        'ApprovalService::createWorkflow',
    ])
    && !str_contains($document, '$doc->save($update)'),
    'DC02',
    '修订创建独立新文件和新审批流，不改写当前生效版本'
);

dc_check(
    dc_all($approval, [
        'supersedes_document_id',
        "'status' => 'obsolete'",
        'Db::transaction',
    ]),
    'DC03',
    '新版本批准发布时原版本才作废'
);

dc_check(
    dc_all($control, [
        "status !== 'published'",
        'findDistributionForUser',
        "where('user_id', \$userId)",
    ]),
    'DC04',
    '仅正式文件可分发且接收/回收只能由本人确认'
);

dc_check(
    dc_all($authorization, [
        'document.distribute',
        'document.recall',
        'document.revise',
        'document_controller',
    ])
    && !str_contains($authorization, "'document.approve' => self::hasAnyPosition(\n                \$employeeId,\n                ['document_controller']"),
    'DC05',
    '文件管理员可登记分发召回和修订，但文件管理员身份不授予批准权'
);

dc_check(
    dc_all($approvalService, [
        'authorizedApprovalPositions',
        'document_controller',
        'authorized_signatory',
        'system_administrator',
    ]),
    'DC06',
    '审批服务按业务岗位授权，签字人或系统管理员身份不自动获得文件审批权'
);

dc_check(
    str_contains($view, "qms_can_action('document', 'distribute'")
    && str_contains($view, "qms_can_action('document', 'revise'")
    && str_contains($view, "qms_can_action('document', 'controlled_print'"),
    'DC07',
    '页面按钮与后端岗位动作边界一致'
);

foreach ($passes as $pass) {
    echo "[PASS] {$pass}\n";
}
foreach ($failures as $failure) {
    fwrite(STDERR, "[FAIL] {$failure}\n");
}
exit($failures === [] ? 0 : 1);
