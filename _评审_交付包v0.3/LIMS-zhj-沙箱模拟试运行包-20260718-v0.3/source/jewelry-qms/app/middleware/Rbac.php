<?php
declare(strict_types=1);

namespace app\middleware;

use app\service\RbacService;
use app\service\ActionAuthorizationService;
use app\service\SecurityAuditService;
use think\Response;
use think\facade\Session;
use think\facade\View;

class Rbac
{
    protected array $except = [
        'login/index',
        'login/logout',
        'login/changepassword',
        'dashboard/index',
        'notification/index',
        'notification/read',
    ];

    public function handle($request, \Closure $next)
    {
        $controller = strtolower($request->controller());
        $action = strtolower($request->action());
        $route = $controller . '/' . $action;

        if (in_array($route, $this->except, true)) {
            return $next($request);
        }

        $actionDecision = ActionAuthorizationService::requestDecision($controller, $action, $request);
        if ($actionDecision === false) {
            SecurityAuditService::recordAccessDenied($request, 'action_access');
            if ($request->isAjax()) {
                return json(['code' => 403, 'msg' => '无此动作权限，请联系质量负责人确认岗位任命'], 403);
            }

            return Response::create(View::fetch('error/403'), 'html', 403);
        }
        if ($actionDecision === true) {
            return $next($request);
        }

        // 责任链和文件审批均先允许命中统一入口，再由业务服务校验本人及有效岗位；
        // 不能让通用模块权限先于业务动作授权把已指派审核人挡在门外。
        $isBusinessResponsibilityApproval = $controller === 'planningresponsibility' && $action === 'approve';
        $isAssignedApprovalAction = $controller === 'approval' && $action === 'approve';
        $isDocumentRecipientAction = $controller === 'document'
            && in_array($action, ['confirmreceipt', 'confirmrecall'], true);
        if (!$isBusinessResponsibilityApproval
            && !$isAssignedApprovalAction
            && !$isDocumentRecipientAction
            && !RbacService::canAccess($controller)) {
            SecurityAuditService::recordAccessDenied($request, 'module_access');
            if ($request->isAjax()) {
                return json(['code' => 403, 'msg' => '无访问权限']);
            }
            Session::flash('error', '您没有访问该模块的权限');

            return redirect('/dashboard/index');
        }

        $writeActions = [
            'add', 'edit', 'delete', 'create', 'seedsamples', 'seedbatch', 'updatereview', 'updatelayoutstatus', 'exportpdf',
            'seed', 'upload', 'renderpackage', 'extractclauses', 'obsolete', 'createpolicy', 'createobjective',
            'updateblock', 'publishdocument', 'savelink', 'deletelink', 'map', 'localelement',
            'savechangerequest', 'updatechangerequest', 'transition', 'uploadattachment',
            'distribute', 'confirmreceipt', 'confirmrecall', 'review',
            'uploadevidence', 'revieweffectiveness',
            'uploadcertificate',
            'approve', 'complete', 'refresh',
            'extract', 'confirm', 'reject',
            'save', 'test', 'send', 'create', 'purge',
        ];
        if (in_array($action, $writeActions, true)
            && !$isBusinessResponsibilityApproval
            && !$isAssignedApprovalAction
            && !$isDocumentRecipientAction
            && !RbacService::canWrite($controller)) {
            SecurityAuditService::recordAccessDenied($request, 'write_access');
            Session::flash('error', '您没有编辑权限');

            return redirect('/dashboard/index');
        }

        return $next($request);
    }
}
