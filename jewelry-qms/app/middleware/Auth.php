<?php
declare(strict_types=1);

namespace app\middleware;

use app\model\NotificationUser;
use think\facade\Config;
use think\facade\Session;
use think\facade\View;

class Auth
{
    protected array $except = [
        'login/index',
        'login/logout',
        'login/changepassword',
    ];

    public function handle($request, \Closure $next)
    {
        $controller = strtolower($request->controller());
        $action = strtolower($request->action());
        $route = $controller . '/' . $action;

        if (in_array($route, $this->except, true)) {
            return $next($request);
        }

        if (!Session::has('user.id')) {
            if ($request->isAjax()) {
                return json(['code' => 401, 'msg' => '请先登录']);
            }
            return redirect('/login/index');
        }

        $qmsConfig = Config::get('qms', []);
        $notificationCount = NotificationUser::where('user_id', Session::get('user.id'))
            ->where('status', 0)
            ->count();

        View::layout('layout/main');
        View::assign([
            'docLevels' => $qmsConfig['docLevels'] ?? [],
            'roles' => $qmsConfig['roles'] ?? [],
            'user' => Session::get('user'),
            'systemTitle' => $qmsConfig['title'] ?? 'QMS',
            'systemVersion' => $qmsConfig['version'] ?? '1.0',
            'notificationCount' => $notificationCount,
        ]);

        return $next($request);
    }
}
