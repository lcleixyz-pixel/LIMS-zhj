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

        if (!RbacService::canAccess($controller)) {
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
        // 责任链签批以实时员工任命为业务身份，不以 RBAC 页面角色代替。
        // 这里只放行该控制器的 approve；无有效总经理/实验室主任任命仍会被 ApprovalService 拒绝。
        $isBusinessResponsibilityApproval = $controller === 'planningresponsibility' && $action === 'approve';
        if (in_array($action, $writeActions, true) && !$isBusinessResponsibilityApproval && !RbacService::canWrite($controller)) {
            SecurityAuditService::recordAccessDenied($request, 'write_access');
            Session::flash('error', '您没有编辑权限');

            return redirect('/dashboard/index');
        }

        return $next($request);
    }
}
