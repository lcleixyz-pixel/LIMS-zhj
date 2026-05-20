<?php
declare(strict_types=1);

namespace app\controller;

use app\BaseController;
use think\exception\HttpException;
use think\facade\Session;
use think\facade\View;

class CrudBase extends BaseController
{
    protected string $modelClass = '';
    protected string $viewPrefix = '';
    protected string $pageTitle = '';

    /** 表单页（add/edit GET）追加模板变量 */
    protected function assignFormContext(): void
    {
    }

    protected function getModel()
    {
        if ($this->modelClass === '') {
            throw new \RuntimeException('modelClass not set in ' . static::class);
        }

        return new ($this->modelClass)();
    }

    protected function listRedirectUrl(): string
    {
        $name = $this->request->controller();
        $path = qms_controller_url($name);

        return '/' . $path . '/index';
    }

    public function index()
    {
        $class = $this->modelClass;
        /** @var \think\Model $prototype */
        $prototype = $this->getModel();
        $orderField = $prototype->hasColumn('created') ? 'created' : 'id';

        if ($prototype->hasColumn('soft_delete')) {
            $query = $class::where('soft_delete', 0);
        } else {
            $query = $class::whereRaw('1=1');
        }

        $items = $query->order($orderField, 'desc')->paginate(20);
        View::assign('items', $items);
        View::assign('pages', $items->render());
        View::assign('pageTitle', $this->pageTitle);

        return View::fetch($this->viewPrefix . '/index');
    }

    public function add()
    {
        if ($this->request->isPost()) {
            $data = $this->request->post();
            $model = $this->getModel();
            $model->save($data);
            Session::flash('success', '保存成功');

            return redirect($this->listRedirectUrl());
        }
        View::assign('pageTitle', $this->pageTitle . ' - 新增');
        $this->assignFormContext();

        return View::fetch($this->viewPrefix . '/add');
    }

    public function edit()
    {
        $id = $this->request->param('id');
        $model = $this->getModel();
        $record = $model->find($id);
        if (!$record) {
            throw new HttpException(404, '记录不存在');
        }

        if ($this->request->isPost()) {
            $data = $this->request->post();
            $record->save($data);
            Session::flash('success', '已更新');

            return redirect($this->listRedirectUrl());
        }

        View::assign('record', $record);
        View::assign('pageTitle', $this->pageTitle . ' - 编辑');
        $this->assignFormContext();

        return View::fetch($this->viewPrefix . '/edit');
    }

    public function view()
    {
        $id = $this->request->param('id');
        $model = $this->getModel();
        $record = $model->find($id);
        if (!$record) {
            throw new HttpException(404, '记录不存在');
        }
        View::assign('record', $record);
        View::assign('pageTitle', $this->pageTitle . ' - 详情');
        $this->assignFormContext();

        return View::fetch($this->viewPrefix . '/view');
    }

    public function delete()
    {
        $id = $this->request->param('id');
        $model = $this->getModel();
        $record = $model->find($id);
        if (!$record) {
            throw new HttpException(404, '记录不存在');
        }
        if ($model->hasColumn('soft_delete')) {
            $record->soft_delete = 1;
            $record->save();
        } else {
            $record->delete();
        }
        Session::flash('success', '已删除');

        return redirect($this->listRedirectUrl());
    }
}
