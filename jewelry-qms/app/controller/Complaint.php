<?php
declare(strict_types=1);

namespace app\controller;

use app\model\Capa;
use app\model\CustomerComplaint;
use app\service\WorkflowService;
use think\facade\Session;
use think\facade\View;

class Complaint extends BusinessBase
{
    protected string $modelClass = CustomerComplaint::class;
    protected string $viewPrefix = 'complaint';
    protected string $pageTitle = '客户投诉';

    protected function assignFormContext(): void
    {
        $this->assignUsers();
        $this->assignStatusLabels('complaint');
    }

    public function add()
    {
        if ($this->request->isPost()) {
            $data = $this->request->post();
            if (empty($data['complaint_number'])) {
                $data['complaint_number'] = qms_next_number('CP', CustomerComplaint::class, 'complaint_number');
            }
            if (empty($data['received_date'])) {
                $data['received_date'] = date('Y-m-d');
            }
            $model = $this->getModel();
            $model->save($data);
            Session::flash('success', '投诉已登记');

            return redirect($this->listRedirectUrl());
        }
        View::assign('pageTitle', $this->pageTitle . ' - 新增');
        $this->assignFormContext();

        return View::fetch($this->viewPrefix . '/add');
    }

    public function view()
    {
        $id = $this->request->param('id');
        $record = CustomerComplaint::find($id);
        if (!$record) {
            abort(404);
        }
        $this->assignFormContext();
        View::assign('record', $record);
        View::assign('capa', $record->capa_id ? Capa::find($record->capa_id) : null);
        View::assign('pageTitle', $this->pageTitle . ' - 详情');

        return View::fetch($this->viewPrefix . '/view');
    }

    public function advance()
    {
        $id = $this->request->param('id');
        $record = CustomerComplaint::find($id);
        if (!$record) {
            abort(404);
        }
        $flow = ['received' => 'investigating', 'investigating' => 'handling', 'handling' => 'responded', 'responded' => 'closed'];
        if ($this->request->isPost()) {
            $data = $this->request->post();
            foreach (['investigation', 'handling', 'response'] as $field) {
                if (!empty($data[$field])) {
                    $record->$field = $data[$field];
                }
            }
            if (isset($flow[$record->status])) {
                $record->status = $flow[$record->status];
            }
            if ($record->status === 'closed') {
                $record->closed_date = date('Y-m-d');
            }
            $record->save();
            Session::flash('success', '投诉状态已更新');

            return redirect('/complaint/view?id=' . $id);
        }
        $this->assignFormContext();
        View::assign('record', $record);

        return View::fetch($this->viewPrefix . '/advance');
    }

    public function createCapa()
    {
        $id = $this->request->param('id');
        $record = CustomerComplaint::find($id);
        if (!$record || $record->capa_id) {
            Session::flash('error', '无法创建CAPA');

            return redirect('/complaint/view?id=' . $id);
        }
        $capa = WorkflowService::createCapaFromSource(
            'complaint',
            $record->id,
            $record->description,
            WorkflowService::resolveCapaSourceId('complaint'),
            $record->assigned_to,
            $record->due_date
        );
        Session::flash('success', "已创建 CAPA {$capa->capa_number}");

        return redirect('/capa/view?id=' . $capa->id);
    }
}
