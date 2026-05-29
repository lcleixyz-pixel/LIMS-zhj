<?php
declare(strict_types=1);

namespace app\controller;

use app\model\Capa as CapaModel;
use app\model\CapaSource;
use app\model\User;
use app\service\FieldAuditService;
use app\service\WorkflowService;
use think\facade\Db;
use think\facade\Session;
use think\facade\View;

class Capa extends BusinessBase
{
    protected string $modelClass = CapaModel::class;
    protected string $viewPrefix = 'capa';
    protected string $pageTitle = 'CAPA';

    protected function assignFormContext(): void
    {
        $this->assignCommonForm();
        $this->assignStatusLabels('capa');
        View::assign('capaSources', CapaSource::where('soft_delete', 0)->select());
        View::assign('sourceTypes', [
            'audit' => '内部审核',
            'complaint' => '客户投诉',
            'nc' => '不符合工作',
            'review' => '管理评审',
            'internal' => '日常监督',
        ]);
    }

    public function add()
    {
        if ($this->request->isPost()) {
            $data = $this->request->post();
            if (empty($data['capa_number'])) {
                $data['capa_number'] = qms_next_number('CAPA', CapaModel::class, 'capa_number');
            }
            if (empty($data['source_id']) && !empty($data['source_type'])) {
                $data['source_id'] = WorkflowService::resolveCapaSourceId($data['source_type']);
            }
            $model = $this->getModel();
            $model->save($data);
            Session::flash('success', 'CAPA已创建');

            return redirect($this->listRedirectUrl());
        }
        View::assign('pageTitle', $this->pageTitle . ' - 新增');
        $this->assignFormContext();

        return View::fetch($this->viewPrefix . '/add');
    }

    public function view()
    {
        $id = $this->request->param('id');
        $record = CapaModel::find($id);
        if (!$record) {
            abort(404, '记录不存在');
        }
        $this->assignFormContext();
        View::assign('record', $record);
        View::assign('assignee', $record->assigned_to ? User::find($record->assigned_to) : null);
        View::assign('verifier', $record->verified_by ? User::find($record->verified_by) : null);
        View::assign('fieldChangeLogs', FieldAuditService::logsFor('Capa', (string)$id));
        View::assign('pageTitle', $this->pageTitle . ' - 详情');

        return View::fetch($this->viewPrefix . '/view');
    }

    public function advance()
    {
        $id = $this->request->param('id');
        $record = CapaModel::find($id);
        if (!$record) {
            abort(404);
        }
        if ($this->request->isPost()) {
            $action = $this->request->post('action', 'advance');
            $data = $this->request->post();
            $advanced = Db::transaction(function () use ($record, $action, $data) {
                return WorkflowService::advanceCapaStatus($record, $action, $data);
            });
            if ($advanced) {
                Session::flash('success', '状态已更新');
            } else {
                Session::flash('error', '状态更新失败');
            }

            return redirect('/capa/view?id=' . $id);
        }
        $this->assignFormContext();
        View::assign('record', $record);

        return View::fetch($this->viewPrefix . '/advance');
    }
}
