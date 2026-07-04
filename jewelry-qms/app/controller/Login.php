<?php
declare(strict_types=1);

namespace app\controller;

use app\BaseController;
use app\model\User as UserModel;
use app\model\UserSession;
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
            return redirect('/dashboard/index');
        }

        if ($this->request->isPost()) {
            $username = $this->request->post('username', '');
            $password = $this->request->post('password', '');

            $user = UserModel::where('username', $username)
                ->where('publish', 1)
                ->where('soft_delete', 0)
                ->find();

            if ($user && password_verify($password, $user->password)) {
                $user->last_login = date('Y-m-d H:i:s');
                $user->save();

                $sessionId = qms_uuid();
                UserSession::create([
                    'id' => $sessionId,
                    'user_id' => $user->id,
                    'start_time' => date('Y-m-d H:i:s'),
                    'ip_address' => $this->request->ip(),
                ]);

                Session::set('user', [
                    'id' => $user->id,
                    'username' => $user->username,
                    'name' => $user->name,
                    'role' => $user->role,
                    'employee_id' => $user->employee_id,
                    'department_id' => $user->department_id,
                    'is_mr' => $user->is_mr,
                    'session_id' => $sessionId,
                ]);

                return redirect('/dashboard/index');
            }

            View::assign('error', '用户名或密码错误');
        }

        View::assign('systemTitle', Config::get('qms.title', 'QMS'));

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
                $user->save();
                Session::flash('success', '密码已修改');

                return redirect('/dashboard/index');
            }
            View::assign('error', '原密码不正确');
        }

        return View::fetch('login/change_password');
    }
}
