<?php
declare(strict_types=1);

namespace app\controller;

use app\BaseController;
use app\model\User as UserModel;
use app\model\UserSession;
use app\service\LoginThrottleService;
use think\facade\Config;
use think\facade\Session;
use think\facade\View;

class Login extends BaseController
{
    protected array $middleware = [];

    protected function initialize()
    {
        View::layout('layout/login');
    }

    public function index()
    {
        if (Session::has('user.id')) {
            return $this->postLoginRedirect();
        }

        $throttle = new LoginThrottleService();
        $ip = (string)$this->request->ip();

        if ($this->request->isPost()) {
            if ($throttle->isLocked($ip)) {
                $secs = $throttle->remainingLockSeconds($ip);
                View::assign('error', '登录失败次数过多，请 ' . max(1, (int)ceil($secs / 60)) . ' 分钟后再试');
            } else {
                $username = $this->request->post('username', '');
                $password = $this->request->post('password', '');

                $user = UserModel::where('username', $username)
                    ->where('publish', 1)
                    ->where('soft_delete', 0)
                    ->find();

                if ($user && password_verify($password, $user->password)) {
                    $throttle->clear($ip);
                    $user->last_login = date('Y-m-d H:i:s');
                    $user->save();

                    $sessionId = qms_uuid();
                    UserSession::create([
                        'id' => $sessionId,
                        'user_id' => $user->id,
                        'start_time' => date('Y-m-d H:i:s'),
                        'ip_address' => $ip,
                    ]);

                    Session::set('user', [
                        'id' => $user->id,
                        'username' => $user->username,
                        'name' => $user->name,
                        'email' => strtolower(trim((string)$user->email)),
                        'role' => $user->role,
                        'employee_id' => $user->employee_id,
                        'department_id' => $user->department_id,
                        'is_mr' => $user->is_mr,
                        'session_id' => $sessionId,
                        'must_change_password' => (int)($user->must_change_password ?? 0),
                    ]);

                    return $this->postLoginRedirect();
                }

                $throttle->recordFailure($ip);
                View::assign('error', '用户名或密码错误。连续多次失败将临时锁定账户，请谨慎输入。');
            }
        }

        $qmsConfig = Config::get('qms', []);
        View::assign([
            'systemTitle' => $qmsConfig['title'] ?? 'QMS',
            'environmentLabel' => $qmsConfig['environment_label'] ?? '',
            'environmentNotice' => $qmsConfig['environment_notice'] ?? '',
            'supportContact' => $qmsConfig['support_contact'] ?? '',
        ]);

        return View::fetch('login/index');
    }

    public function logout()
    {
        $sessionId = Session::get('user.session_id');
        if ($sessionId) {
            $session = UserSession::find($sessionId);
            if ($session) {
                $session->end_time = date('Y-m-d H:i:s');
                $session->save();
            }
        }
        Session::clear();

        return redirect('/login/index');
    }

    public function changePassword()
    {
        if ($this->request->isPost()) {
            $oldPassword = $this->request->post('old_password', '');
            $newPassword = $this->request->post('new_password', '');

            $user = UserModel::find(Session::get('user.id'));
            if ($user && password_verify($oldPassword, $user->password)) {
                $user->password = password_hash($newPassword, PASSWORD_DEFAULT);
                $user->must_change_password = 0;
                $user->save();
                Session::set('user.must_change_password', 0);
                Session::flash('success', '密码已修改，请使用新密码重新登录。');

                return redirect('/dashboard/index');
            }
            View::assign('error', '原密码不正确，请确认后重试。如忘记密码请联系系统管理员。');
        }

        return View::fetch('login/change_password');
    }

    private function postLoginRedirect()
    {
        if ((int)Session::get('user.must_change_password', 0) === 1) {
            return redirect('/user/changePassword');
        }

        return redirect('/dashboard/index');
    }
}
