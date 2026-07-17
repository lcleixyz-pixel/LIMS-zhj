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
$rbac = dc_source('app/middleware/Rbac.php');
$migration = dc_source('database/migrations/20260717_gr14_controlled_trial.sql');

dc_check(
    dc_all($print, ["status !== 'published'", '当前正式发布版本才可生成正式受控打印'])
    && str_contains($document, '$this->request->isPost()')
    && str_contains($document, '受控打印必须从文件详情页提交')
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

dc_check(
    str_contains($document, 'TrialModeService::isEnabled()')
    && str_contains($document, "TrialModeService::simulationNumber((string)(\$data['doc_number'] ?? ''))"),
    'DC08',
    '试运行环境新登记文件由服务端强制 SIM 编号'
);

dc_check(
    str_contains($rbac, "\$controller === 'approval' && \$action === 'approve'")
    && str_contains($rbac, '&& !$isAssignedApprovalAction')
    && strpos($rbac, '$isAssignedApprovalAction') < strpos($rbac, 'RbacService::canAccess($controller)'),
    'DC09',
    '已指派审核人可进入统一审批入口，最终岗位和本人校验仍由审批服务执行'
);

dc_check(
    str_contains($print, 'TrialModeService::isEnabled()')
    && str_contains($print, "'试运行/非正式受控副本 '"),
    'DC10',
    '8011 中 SIM 文件打印强制试运行水印，不冒充正式受控副本'
);

dc_check(
    str_contains($document, "\$newVersion === (string)\$doc->version")
    && str_contains($document, '$minorNum++'),
    'DC11',
    '发起修订时新版本号不得与当前版本号重复'
);

dc_check(
    str_contains($view, 'action="/document/submitReview?id={$doc.id}"')
    && !str_contains($view, "confirm('确认提交审核"),
    'DC12',
    '提交审核使用可追溯 POST，页面不再由阻塞式脚本造成按钮卡死'
);

dc_check(
    str_contains($rbac, '$isDocumentRecipientAction')
    && str_contains($rbac, "in_array(\$action, ['confirmreceipt', 'confirmrecall'], true)")
    && substr_count($rbac, '&& !$isDocumentRecipientAction') >= 2,
    'DC13',
    '文件接收人可进入本人确认入口，最终对象归属仍由文件控制服务校验'
);

dc_check(
    str_contains($document, "View::assign('distributionUserNames'")
    && str_contains($view, '$distributionUserNames[$dist.user_id]'),
    'DC14',
    '文件分发列表显示接收人姓名，不暴露内部用户 UUID'
);

dc_check(
    str_contains($migration, "TABLE_NAME = 'documents' AND COLUMN_NAME = 'site_id'")
    && str_contains($authorization, 'documentManageableSiteIds')
    && str_contains($document, "View::assign('sites'")
    && str_contains($view, '适用场所'),
    'DC15',
    '文件按适用场所绑定，文件管理员只能管理本人任命场所'
);

foreach ($passes as $pass) {
    echo "[PASS] {$pass}\n";
}
foreach ($failures as $failure) {
    fwrite(STDERR, "[FAIL] {$failure}\n");
}
exit($failures === [] ? 0 : 1);
