<?php
declare(strict_types=1);

namespace app\controller;

use app\model\User as UserModel;
use app\model\Department;
use app\model\Employee;
use think\facade\Session;
use think\facade\View;

class User extends CrudBase
{
    protected string $modelClass = UserModel::class;
    protected string $viewPrefix = 'user';
    protected string $pageTitle = '用户管理';

    public function add()
    {
        if ($this->request->isPost()) {
            $data = $this->request->post();
            $data['password'] = password_hash($data['password'] ?? 'password', PASSWORD_DEFAULT);
            $data['must_change_password'] = 1;
            $model = $this->getModel();
            $model->save($data);
            Session::flash('success', '用户已创建');
            return redirect('/user/index');
        }
        View::assign('departments', Department::where('soft_delete', 0)->select());
        View::assign('employees', Employee::where('soft_delete', 0)->select());
        View::assign('pageTitle', $this->pageTitle . ' - 新增');
        return View::fetch('user/add');
    }

    public function edit()
    {
        $id = $this->request->param('id');
        $model = $this->getModel();
        $record = $model->find($id);
        if (!$record) throw new \think\exception\HttpException(404, '用户不存在');

        if ($this->request->isPost()) {
            $data = $this->request->post();
            if (!empty($data['password'])) {
                $data['password'] = password_hash($data['password'], PASSWORD_DEFAULT);
                $data['must_change_password'] = 1;
            } else {
                unset($data['password']);
            }
            $record->save($data);
            Session::flash('success', '已更新');
            return redirect('/user/index');
        }

        unset($record->password);
        View::assign('record', $record);
        View::assign('departments', Department::where('soft_delete', 0)->select());
        View::assign('employees', Employee::where('soft_delete', 0)->select());
        View::assign('pageTitle', $this->pageTitle . ' - 编辑');
        return View::fetch('user/edit');
    }

    public function changePassword()
    {
        $currentUserId = (string)Session::get('user.id', '');
        $id = (string)$this->request->param('id', '');
        $forcedSelf = (int)Session::get('user.must_change_password', 0) === 1;

        // 强制改密或未传 id：本人改密
        if ($id === '' || $forcedSelf || $id === $currentUserId) {
            return $this->changeOwnPassword($currentUserId, $forcedSelf);
        }

        $record = UserModel::find($id);
        if (!$record) {
            throw new \think\exception\HttpException(404, '用户不存在');
        }

        if ($this->request->isPost()) {
            $pwd = (string)$this->request->post('password', '');
            if (strlen($pwd) < 6) {
                Session::flash('error', '密码至少 6 位');
            } else {
                $record->password = password_hash($pwd, PASSWORD_DEFAULT);
                $record->must_change_password = 1;
                $record->save();
                Session::flash('success', '已重置密码（用户下次登录须改密）');

                return redirect('/user/index');
            }
        }

        View::assign('record', $record);
        View::assign('pageTitle', '重置用户密码');

        return View::fetch('user/reset_password');
    }

    private function changeOwnPassword(string $userId, bool $forced): \think\Response|string
    {
        $record = UserModel::find($userId);
        if (!$record) {
            throw new \think\exception\HttpException(404, '用户不存在');
        }

        if ($this->request->isPost()) {
            $oldPassword = (string)$this->request->post('old_password', '');
            $newPassword = (string)$this->request->post('new_password', '');
            $confirm = (string)$this->request->post('confirm_password', '');

            if (strlen($newPassword) < 6) {
                View::assign('error', '新密码至少 6 位');
            } elseif ($newPassword !== $confirm) {
                View::assign('error', '两次输入的新密码不一致');
            } elseif (!password_verify($oldPassword, $record->password)) {
                View::assign('error', '原密码不正确');
            } else {
                $record->password = password_hash($newPassword, PASSWORD_DEFAULT);
                $record->must_change_password = 0;
                $record->save();
                Session::set('user.must_change_password', 0);
                Session::flash('success', '密码已修改');

                return redirect('/dashboard/index');
            }
        }

        View::assign('record', $record);
        View::assign('forced', $forced);
        View::assign('pageTitle', '修改密码');

        return View::fetch('user/change_password');
    }
}
