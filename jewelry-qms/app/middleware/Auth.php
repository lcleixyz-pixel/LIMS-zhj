<?php
declare(strict_types=1);

namespace app\middleware;

use app\model\QmsExternalChangeCandidate;
use app\model\NotificationUser;
use app\service\ActionAuthorizationService;
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

        if ((int)Session::get('user.must_change_password', 0) === 1) {
            $allowedWhileForced = [
                'user/changepassword',
                'login/changepassword',
                'login/logout',
                'logout/index',
            ];
            if (!in_array($route, $allowedWhileForced, true) && $controller !== 'logout') {
                return redirect('/user/changePassword');
            }
        }

        $qmsConfig = Config::get('qms', []);
        $notificationCount = NotificationUser::where('user_id', Session::get('user.id'))
            ->where('status', 0)
            ->count();
        try {
            $pendingRegulatoryCandidateCount = QmsExternalChangeCandidate::where('soft_delete', 0)
                ->where('review_status', 'pending')
                ->count();
        } catch (\Throwable $exception) {
            $pendingRegulatoryCandidateCount = 0;
        }

        View::layout('layout/main');
        View::assign([
            'docLevels' => $qmsConfig['docLevels'] ?? [],
            'roles' => $qmsConfig['roles'] ?? [],
            'user' => Session::get('user'),
            'systemTitle' => $qmsConfig['title'] ?? 'QMS',
            'systemVersion' => $qmsConfig['version'] ?? '1.0',
            'environmentLabel' => $qmsConfig['environment_label'] ?? '',
            'environmentNotice' => $qmsConfig['environment_notice'] ?? '',
            'notificationCount' => $notificationCount,
            'pendingRegulatoryCandidateCount' => $pendingRegulatoryCandidateCount,
            'canViewEquipmentMenu' => ActionAuthorizationService::allows('equipment', 'view'),
        ]);

        return $next($request);
    }
}
