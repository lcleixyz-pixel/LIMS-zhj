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
        $id = $this->request->param('id');
        $record = UserModel::find($id);
        if (!$record) {
            throw new \think\exception\HttpException(404, '用户不存在');
        }

        if ($this->request->isPost()) {
            $pwd = (string) $this->request->post('password', '');
            if (strlen($pwd) < 6) {
                Session::flash('error', '密码至少 6 位');
            } else {
                $record->password = password_hash($pwd, PASSWORD_DEFAULT);
                $record->save();
                Session::flash('success', '已重置密码');

                return redirect('/user/index');
            }
        }

        View::assign('record', $record);
        View::assign('pageTitle', '重置用户密码');

        return View::fetch('user/reset_password');
    }
}
