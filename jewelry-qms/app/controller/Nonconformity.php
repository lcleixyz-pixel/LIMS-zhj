<?php
declare(strict_types=1);

namespace app\controller;

use app\model\Capa;
use app\model\Nonconformity as NonconformityModel;
use app\service\WorkflowService;
use think\facade\Session;
use think\facade\View;

class Nonconformity extends BusinessBase
{
    protected string $modelClass = NonconformityModel::class;
    protected string $viewPrefix = 'nonconformity';
    protected string $pageTitle = '不符合工作';

    protected function assignFormContext(): void
    {
        $this->assignUsers();
        $this->assignStatusLabels('nonconformity');
        View::assign('severityOptions', [
            'minor' => '轻微',
            'major' => '严重',
            'critical' => '危急',
        ]);
        View::assign('sourceOptions', [
            'test' => '检测过程',
            'sample' => '样品',
            'equipment' => '设备',
            'document' => '文件',
            'other' => '其他',
        ]);
        View::assign('dispositionOptions', [
            'continue' => '继续',
            'suspend' => '暂停',
            'recall' => '召回',
            'other' => '其他',
        ]);
    }

    public function add()
    {
        if ($this->request->isPost()) {
            $data = $this->request->post();
            if (empty($data['nc_number'])) {
                $data['nc_number'] = qms_next_number('NC', NonconformityModel::class, 'nc_number');
            }
            if (empty($data['identified_date'])) {
                $data['identified_date'] = date('Y-m-d');
            }
            $model = $this->getModel();
            $model->save($data);
            if (!empty($data['trigger_capa']) && in_array($data['severity'] ?? '', ['major', 'critical'])) {
                WorkflowService::createCapaFromSource(
                    'nc',
                    $model->id,
                    $data['description'],
                    WorkflowService::resolveCapaSourceId('nc'),
                    $data['assigned_to'] ?? null,
                    $data['due_date'] ?? null
                );
            }
            Session::flash('success', '不符合工作已登记');

            return redirect($this->listRedirectUrl());
        }
        View::assign('pageTitle', $this->pageTitle . ' - 新增');
        $this->assignFormContext();

        return View::fetch($this->viewPrefix . '/add');
    }

    public function view()
    {
        $id = $this->request->param('id');
        $record = NonconformityModel::find($id);
        if (!$record) {
            abort(404);
        }
        $this->assignFormContext();
        View::assign('record', $record);
        View::assign('capa', $record->capa_id ? Capa::find($record->capa_id) : null);
        View::assign('pageTitle', $this->pageTitle . ' - 详情');

        return View::fetch($this->viewPrefix . '/view');
    }

    public function createCapa()
    {
        $id = $this->request->param('id');
        $record = NonconformityModel::find($id);
        if (!$record || $record->capa_id) {
            Session::flash('error', '无法创建CAPA');

            return redirect('/nonconformity/view?id=' . $id);
        }
        $capa = WorkflowService::createCapaFromSource(
            'nc',
            $record->id,
            $record->description,
            WorkflowService::resolveCapaSourceId('nc'),
            $record->assigned_to,
            null
        );
        Session::flash('success', "已创建 CAPA {$capa->capa_number}");

        return redirect('/capa/view?id=' . $capa->id);
    }
}
