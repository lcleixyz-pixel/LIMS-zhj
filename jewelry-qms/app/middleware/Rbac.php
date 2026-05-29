<?php
declare(strict_types=1);

namespace app\middleware;

use app\service\RbacService;
use think\facade\Session;

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

        if (!RbacService::canAccess($controller)) {
            if ($request->isAjax()) {
                return json(['code' => 403, 'msg' => '无访问权限']);
            }
            Session::flash('error', '您没有访问该模块的权限');

            return redirect('/dashboard/index');
        }

        $writeActions = [
            'add', 'edit', 'delete', 'create', 'seedsamples', 'seedbatch', 'updatereview', 'exportpdf',
            'seed', 'upload', 'renderpackage', 'extractclauses', 'obsolete', 'createpolicy', 'createobjective',
            'updateblock', 'publishdocument', 'savelink', 'deletelink', 'map', 'localelement',
            'uploadevidence', 'revieweffectiveness',
        ];
        if (in_array($action, $writeActions, true) && !RbacService::canWrite($controller)) {
            Session::flash('error', '您没有编辑权限');

            return redirect('/dashboard/index');
        }

        return $next($request);
    }
}
