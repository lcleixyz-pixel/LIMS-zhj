<?php
declare(strict_types=1);

$root = dirname(__DIR__);
require __DIR__ . '/support/qms_responsibility_fixture.php';

use app\controller\PlanningResponsibility;
use app\middleware\AuditLog;
use app\service\QmsResponsibilityApprovalService;
use app\service\QmsResponsibilityCatalogService;
use app\service\QmsResponsibilityDraftService;
use app\service\RbacService;
use think\facade\Db;
use think\facade\Session;

function responsibility_ui_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

function responsibility_ui_contains(string $needle, string $haystack, string $message): void
{
    responsibility_ui_assert(str_contains($haystack, $needle), $message . ' (missing: ' . $needle . ')');
}

$route = (string)file_get_contents($root . '/route/app.php');
$config = (string)file_get_contents($root . '/config/qms.php');
$layout = (string)file_get_contents($root . '/app/view/layout/main.html');
$controllerPath = $root . '/app/controller/PlanningResponsibility.php';
$viewPath = $root . '/app/view/planning_responsibility/index.html';
$employeeController = (string)file_get_contents($root . '/app/controller/Employee.php');
$employeeView = (string)file_get_contents($root . '/app/view/employee/view.html');
$auditLog = (string)file_get_contents($root . '/app/middleware/AuditLog.php');
$rbacMiddleware = (string)file_get_contents($root . '/app/middleware/Rbac.php');

responsibility_ui_assert(is_file($controllerPath), 'Responsibility controller exists');
responsibility_ui_assert(is_file($viewPath), 'Responsibility page exists');
$controller = (string)file_get_contents($controllerPath);
$view = (string)file_get_contents($viewPath);

foreach ([
    'planning/responsibilities',
    'planning/responsibilities/assignments/save',
    'planning/responsibilities/validate',
    'planning/responsibilities/submit',
    'planning/responsibilities/approve',
    'planning/responsibilities/bootstrap/general-manager',
    'planning/responsibilities/bootstrap/lab-director',
    'planning/responsibilities/alignment',
] as $path) {
    responsibility_ui_contains($path, $route, 'Route contains ' . $path);
}
$baseResponsibilityPost = strpos($route, "Route::post('planning/responsibilities',");
responsibility_ui_assert($baseResponsibilityPost !== false, 'Base responsibility POST route exists');
foreach (['assignments/save', 'validate', 'submit', 'approve', 'bootstrap/general-manager', 'bootstrap/lab-director'] as $specificPath) {
    $specificPosition = strpos($route, "Route::post('planning/responsibilities/{$specificPath}'");
    responsibility_ui_assert(
        $specificPosition !== false && $specificPosition < $baseResponsibilityPost,
        'Specific responsibility POST route precedes the base draft-creation route: ' . $specificPath
    );
}

foreach ([
    'index', 'createInitialDraft', 'saveAssignment', 'removeAssignment', 'validateVersion',
    'submitVersion', 'registerGeneralManager', 'requestLabDirector', 'approve', 'alignment',
] as $method) {
    responsibility_ui_contains('function ' . $method . '(', $controller, 'Controller exposes ' . $method);
}

foreach (['责任结构', '人员配置', '校验与签批', '有效责任链', '岗位尚未绑定人员', '运行时指定'] as $label) {
    responsibility_ui_contains($label, $view, 'Responsibility page contains ' . $label);
}
foreach (['structure', 'staffing', 'approval', 'effective', 'alignment'] as $mode) {
    responsibility_ui_contains('view=' . $mode, $view, 'Responsibility page exposes view ' . $mode);
}
responsibility_ui_contains("Route::get('planning/responsibilities/alignment'", $route, 'Alignment endpoint remains GET-only');
foreach ([
    'QmsResponsibilityAlignmentService::baselineForVersion',
    'QmsResponsibilityAlignmentService::injectBaseline',
    'QmsManualProcedureAlignmentService::loadInputs',
    'QmsManualProcedureTraceService::fromDatabase',
    'QmsManualProcedureAlignmentService::check',
] as $readOnlyCall) {
    responsibility_ui_contains($readOnlyCall, $controller, 'Alignment page uses read-only source: ' . $readOnlyCall);
}
responsibility_ui_assert(
    !str_contains($controller, 'QmsManualProcedureAlignmentReportService'),
    'Alignment page does not write report files'
);
responsibility_ui_assert(!str_contains($view, 'name="apply"'), 'Alignment page exposes no apply action');

responsibility_ui_contains('/planning/responsibilities', $layout, 'Planning navigation links responsibility page');
responsibility_ui_contains('planningresponsibility', $config, 'All logged-in roles can be granted responsibility-page read access');
responsibility_ui_contains("['admin', 'quality_manager']", $controller, 'Management writes are explicitly limited');
responsibility_ui_contains('QmsResponsibilityApprovalService::approve', $controller, 'Business approval delegates to approval service');
responsibility_ui_contains('planningresponsibility', $rbacMiddleware, 'RBAC leaves business approval to appointment-aware service');

foreach (['createinitialdraft', 'saveassignment', 'removeassignment', 'validateversion', 'submitversion', 'registergeneralmanager', 'requestlabdirector', 'approve'] as $action) {
    responsibility_ui_contains("'{$action}'", $auditLog, 'Audit log covers ' . $action);
}
responsibility_ui_contains("post('operation'", $auditLog, 'Audit log distinguishes remove operation on the shared assignment route');
foreach (['qms_responsibility_audit', 'outcome=', 'subject_type=', 'subject_key='] as $auditNeedle) {
    responsibility_ui_contains($auditNeedle, $auditLog . $controller, 'Responsibility audit records outcome and subject: ' . $auditNeedle);
}
responsibility_ui_contains('instanceof DomainException', $controller, 'Only domain errors may be shown verbatim');
responsibility_ui_contains("'操作失败，请联系管理员'", $controller, 'Unexpected errors use a generic message');
responsibility_ui_contains('Log::error', $controller, 'Unexpected errors are logged server-side');

foreach (['source_kind', 'source_chain_version_id', 'source_responsibility_id', 'source_approval_id'] as $field) {
    responsibility_ui_contains($field, $employeeController, 'Employee appointment query exposes ' . $field);
}
foreach (['来源类型', '责任链版本', '签批证据', 'legacy_document'] as $label) {
    responsibility_ui_contains($label, $employeeView, 'Employee evidence view contains ' . $label);
}

Session::set('user.role', 'staff');
responsibility_ui_assert(RbacService::canAccess('PlanningResponsibility'), 'staff can read responsibility page');
responsibility_ui_assert(!RbacService::canWrite('PlanningResponsibility'), 'staff receives no generic responsibility write permission');
Session::set('user.role', 'quality_manager');
responsibility_ui_assert(RbacService::canWrite('PlanningResponsibility'), 'quality manager can edit responsibility drafts');
Session::delete('user.role');

$companyId = (string)config('qms.company_id');
$employeeId = (string)Db::name('employees')->where('company_id', $companyId)->where('soft_delete', 0)->value('id');
if ($employeeId !== '') {
    $id = qms_uuid();
    Db::name('employee_appointments')->insert([
        'id' => $id,
        'company_id' => $companyId,
        'employee_id' => $employeeId,
        'appointment_key' => 'ui-legacy-default:' . $id,
        'appointment_type' => 'role',
        'position_name' => '旧导入兼容测试',
        'appointed_at' => date('Y-m-d'),
        'status' => 'active',
        'publish' => 1,
        'soft_delete' => 0,
        'created' => date('Y-m-d H:i:s'),
        'modified' => date('Y-m-d H:i:s'),
    ]);
    responsibility_ui_assert(
        Db::name('employee_appointments')->where('id', $id)->value('source_kind') === 'legacy_document',
        'Old-field appointment insert defaults source_kind to legacy_document'
    );
    Db::name('employee_appointments')->where('id', $id)->delete();
}

function responsibility_ui_user(string $companyId, string $label, string $role): array
{
    $employeeId = responsibility_fixture_row('employees', [
        'company_id' => $companyId,
        'employee_number' => 'UI-' . strtoupper(substr(str_replace('-', '', qms_uuid()), 0, 10)),
        'name' => '页面审计-' . $label,
    ]);
    $userId = responsibility_fixture_row('users', [
        'company_id' => $companyId,
        'employee_id' => $employeeId,
        'username' => 'ui_' . strtolower(substr(str_replace('-', '', qms_uuid()), 0, 12)),
        'password' => password_hash('test-only', PASSWORD_DEFAULT),
        'name' => '页面审计用户-' . $label,
        'role' => $role,
    ]);

    return [
        'employee_id' => $employeeId,
        'user_id' => $userId,
        'role' => $role,
    ];
}

function responsibility_ui_session(array $person, bool $signedSession = false): void
{
    $sessionId = 'UI-' . substr(str_replace('-', '', qms_uuid()), 0, 20);
    if ($signedSession) {
        Db::name('user_sessions')->insert([
            'id' => $sessionId,
            'user_id' => (string)$person['user_id'],
            'start_time' => date('Y-m-d H:i:s'),
            'end_time' => null,
            'ip_address' => '127.0.0.1',
        ]);
    }
    Session::set('user', [
        'id' => (string)$person['user_id'],
        'employee_id' => (string)$person['employee_id'],
        'role' => (string)$person['role'],
        'session_id' => $sessionId,
    ]);
}

function responsibility_ui_run_action(think\App $app, string $action, array $post)
{
    $request = (new app\Request())
        ->setMethod('POST')
        ->setController('PlanningResponsibility')
        ->setAction($action)
        ->withPost($post);
    $app->instance('request', $request);
    $controller = new PlanningResponsibility($app);

    return (new AuditLog())->handle($request, static fn () => $controller->{$action}());
}

function responsibility_ui_history(string $userId, string $action): array
{
    $row = Db::name('histories')->where('user_id', $userId)->where('action', $action)->order('created', 'desc')->find();
    responsibility_ui_assert(is_array($row), 'Audit history exists for ' . $action . ' and ' . $userId);

    return $row;
}

function responsibility_ui_render_alignment(think\App $app, ?string $versionId, bool $draftPreview): string
{
    think\facade\View::layout(false);
    $get = [
        'view' => 'alignment',
        'draft_preview' => $draftPreview ? '1' : '0',
    ];
    if ($versionId !== null && $versionId !== '') {
        $get['version_id'] = $versionId;
    }
    $request = (new app\Request())
        ->setMethod('GET')
        ->setController('PlanningResponsibility')
        ->setAction('alignment')
        ->withGet($get);
    $app->instance('request', $request);
    $controller = new PlanningResponsibility($app);

    return (string)$controller->alignment();
}

catalog_in_transaction(function () use ($app): void {
    $companyId = catalog_company_id();
    $draft = QmsResponsibilityCatalogService::createInitialDraft();
    $versionId = (string)$draft['id'];
    $detail = QmsResponsibilityDraftService::versionDetail($versionId);
    $responsibility = current(array_filter(
        $detail['responsibilities'],
        static fn (array $row): bool => (string)$row['assignment_mode'] === 'named_person'
    ));
    responsibility_ui_assert(is_array($responsibility), 'Audit test has a named-person responsibility');
    $responsibilityId = (string)$responsibility['id'];

    $subject = responsibility_ui_user($companyId, 'SUBJECT', 'staff');
    $staff = responsibility_ui_user($companyId, 'UNAUTHORIZED-SAVE', 'staff');
    responsibility_ui_session($staff);
    responsibility_ui_run_action($app, 'saveAssignment', [
        'version_id' => $versionId,
        'responsibility_id' => $responsibilityId,
        'employee_id' => (string)$subject['employee_id'],
        'proposed_from' => date('Y-m-d'),
    ]);
    $staffFailure = responsibility_ui_history((string)$staff['user_id'], 'saveAssignment');
    responsibility_ui_assert(str_contains((string)$staffFailure['details'], 'outcome=failed'), 'Unauthorized staff save is audited as failed');
    responsibility_ui_assert((string)$staffFailure['record_id'] === $responsibilityId, 'Unauthorized staff save audit points to responsibility');

    $qualityManager = responsibility_ui_user($companyId, 'QUALITY-MANAGER', 'quality_manager');
    responsibility_ui_session($qualityManager);
    responsibility_ui_run_action($app, 'saveAssignment', [
        'version_id' => $versionId,
        'responsibility_id' => $responsibilityId,
        'employee_id' => (string)$subject['employee_id'],
        'proposed_from' => date('Y-m-d'),
    ]);
    $savedAssignment = Db::name('qms_responsibility_assignments')
        ->where('responsibility_id', $responsibilityId)
        ->where('employee_id', (string)$subject['employee_id'])->find();
    $saveSuccess = responsibility_ui_history((string)$qualityManager['user_id'], 'saveAssignment');
    responsibility_ui_assert(str_contains((string)$saveSuccess['details'], 'outcome=success'), 'Successful assignment save is audited as success');
    responsibility_ui_assert((string)$saveSuccess['record_id'] === (string)$savedAssignment['id'], 'Successful assignment audit points to assignment');

    $unappointed = responsibility_ui_user($companyId, 'UNAPPOINTED-APPROVER', 'staff');
    responsibility_ui_session($unappointed, true);
    responsibility_ui_run_action($app, 'approve', [
        'approval_scope' => 'assignment',
        'version_id' => $versionId,
        'batch_key' => hash('sha256', 'no-business-appointment'),
        'decision' => 'approved',
    ]);
    $approvalFailure = responsibility_ui_history((string)$unappointed['user_id'], 'approve');
    responsibility_ui_assert(str_contains((string)$approvalFailure['details'], 'outcome=failed'), 'Unappointed signer is audited as failed');
    responsibility_ui_assert((string)$approvalFailure['record_id'] === $versionId, 'Failed batch signature audit points to version');

    $admin = responsibility_ui_user($companyId, 'ADMIN', 'admin');
    $gm = responsibility_ui_user($companyId, 'GM', 'staff');
    $director = responsibility_ui_user($companyId, 'DIRECTOR', 'staff');
    responsibility_ui_session($admin, true);
    QmsResponsibilityApprovalService::registerCorporateIdentity([
        'position_code' => 'company_general_manager',
        'employee_id' => (string)$gm['employee_id'],
        'source_document_number' => 'UI-GOV-001',
        'source_excerpt' => '公司既有任职证据',
        'appointed_at' => date('Y-m-d'),
    ]);
    $bootstrap = QmsResponsibilityApprovalService::requestLabDirectorAppointment(
        (string)$director['employee_id'],
        date('Y-m-d')
    );
    responsibility_ui_session($gm, true);
    responsibility_ui_run_action($app, 'approve', [
        'approval_scope' => 'governance_bootstrap',
        'approval_id' => (string)$bootstrap['id'],
        'decision' => 'approved',
        'comments' => '同意任命',
    ]);
    $approvalSuccess = responsibility_ui_history((string)$gm['user_id'], 'approve');
    responsibility_ui_assert(str_contains((string)$approvalSuccess['details'], 'outcome=success'), 'Successful business signature is audited as success');
    responsibility_ui_assert((string)$approvalSuccess['record_id'] === (string)$bootstrap['id'], 'Successful bootstrap signature audit points to approval');

    $request = (new app\Request())->setMethod('POST')->setController('PlanningResponsibility')->setAction('submitVersion')->withPost(['version_id' => $versionId]);
    $app->instance('request', $request);
    $controller = new PlanningResponsibility($app);
    $failure = new ReflectionMethod($controller, 'failure');
    Session::delete('error');
    $failure->invoke($controller, new RuntimeException('internal-secret-detail'), 'approval', $versionId);
    responsibility_ui_assert(Session::get('error') === '操作失败，请联系管理员', 'Unexpected exception detail is not flashed');
    responsibility_ui_assert(!str_contains((string)Session::get('error'), 'internal-secret-detail'), 'Internal exception detail is hidden');
    Session::delete('error');
    $failure->invoke($controller, new DomainException('可安全展示的业务错误'), 'approval', $versionId);
    responsibility_ui_assert(Session::get('error') === '可安全展示的业务错误', 'Domain exception remains user-facing');
});

catalog_in_transaction(function () use ($app): void {
    Session::delete('user');
    Session::set('user.role', 'staff');
    $effective = QmsResponsibilityCatalogService::createInitialDraft();
    $effectiveId = (string)$effective['id'];
    $contentHash = QmsResponsibilityDraftService::contentHash($effectiveId);
    Db::name('qms_responsibility_chain_versions')->where('id', $effectiveId)->update([
        'status' => 'effective',
        'content_hash' => $contentHash,
        'effective_at' => date('Y-m-d H:i:s'),
    ]);

    $beforeRead = [
        'versions' => (int)Db::name('qms_responsibility_chain_versions')->count(),
        'activities' => (int)Db::name('qms_responsibility_activities')->count(),
        'responsibilities' => (int)Db::name('qms_activity_responsibilities')->count(),
        'assignments' => (int)Db::name('qms_responsibility_assignments')->count(),
    ];
    $effectiveHtml = responsibility_ui_render_alignment($app, $effectiveId, false);
    foreach (['Y13-CX20', 'Y13-CX21', 'Y13-CX32'] as $findingId) {
        responsibility_ui_contains($findingId, $effectiveHtml, 'Effective alignment HTML renders ' . $findingId);
    }
    foreach ([
        'Y13-CX20' => '冲突',
        'Y13-CX21' => '冲突',
        'Y13-CX32' => '人工复核',
    ] as $findingId => $statusLabel) {
        responsibility_ui_assert(
            preg_match(
                '/<code>' . preg_quote($findingId, '/') . '<\/code>.*?<span class="badge [^"]+">'
                    . preg_quote($statusLabel, '/') . '<\/span>.*?<\/tr>/su',
                $effectiveHtml
            ) === 1,
            $findingId . ' renders its expected status ' . $statusLabel
        );
    }
    foreach (['期望岗位', '观察岗位', '来源责任项', 'baseline_hash'] as $label) {
        responsibility_ui_contains($label, $effectiveHtml, 'Alignment HTML exposes evidence field ' . $label);
    }
    responsibility_ui_contains('冲突', $effectiveHtml, 'Effective alignment HTML renders conflict status');
    responsibility_ui_contains('人工复核', $effectiveHtml, 'Effective alignment HTML renders review-required status');

    $draft = QmsResponsibilityDraftService::cloneEffectiveVersion($effectiveId);
    $draftId = (string)$draft['id'];
    responsibility_ui_assert((int)$effective['version_no'] === 1, 'Default-alignment scenario starts with effective v1');
    responsibility_ui_assert((int)$draft['version_no'] === 2, 'Default-alignment scenario has a newer draft v2');

    $defaultAlignmentHtml = responsibility_ui_render_alignment($app, null, false);
    responsibility_ui_contains('责任链：v1 / effective', $defaultAlignmentHtml, 'Alignment without version_id prefers latest effective version');
    foreach (['Y13-CX20', 'Y13-CX21', 'Y13-CX32'] as $findingId) {
        responsibility_ui_contains($findingId, $defaultAlignmentHtml, 'Default effective alignment renders ' . $findingId);
    }
    responsibility_ui_assert(
        !str_contains($defaultAlignmentHtml, '明确预览草案'),
        'A newer draft is not automatically previewed on the alignment page'
    );

    $draftBlockedHtml = responsibility_ui_render_alignment($app, $draftId, false);
    responsibility_ui_contains('明确预览草案', $draftBlockedHtml, 'Draft alignment requires an explicit preview choice');
    responsibility_ui_assert(
        !str_contains($draftBlockedHtml, 'Y13-CX20'),
        'Draft findings are not rendered without explicit preview'
    );

    $draftPreviewHtml = responsibility_ui_render_alignment($app, $draftId, true);
    foreach (['Y13-CX20', 'Y13-CX21', 'Y13-CX32'] as $findingId) {
        responsibility_ui_contains($findingId, $draftPreviewHtml, 'Explicit draft preview renders ' . $findingId);
    }

    Db::name('qms_responsibility_chain_versions')->where('id', $effectiveId)->update(['status' => 'superseded']);
    $noEffectiveHtml = responsibility_ui_render_alignment($app, null, false);
    responsibility_ui_contains('明确预览草案', $noEffectiveHtml, 'Without an effective version alignment falls back to the latest draft');
    responsibility_ui_assert(
        !str_contains($noEffectiveHtml, 'Y13-CX20'),
        'Fallback to a draft still does not automatically enable draft preview'
    );

    $afterRead = [
        'versions' => (int)Db::name('qms_responsibility_chain_versions')->count(),
        'activities' => (int)Db::name('qms_responsibility_activities')->count(),
        'responsibilities' => (int)Db::name('qms_activity_responsibilities')->count(),
        'assignments' => (int)Db::name('qms_responsibility_assignments')->count(),
    ];
    responsibility_ui_assert(
        $afterRead['versions'] === $beforeRead['versions'] + 1
        && $afterRead['activities'] === $beforeRead['activities'] + 3
        && $afterRead['responsibilities'] === $beforeRead['responsibilities'] + 21
        && $afterRead['assignments'] === $beforeRead['assignments'],
        'Alignment GETs add no data beyond the explicitly created draft clone'
    );
    Session::delete('user.role');
});

echo "qms_responsibility_ui_smoke passed\n";
