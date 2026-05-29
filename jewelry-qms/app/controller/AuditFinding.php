<?php
declare(strict_types=1);

namespace app\controller;

use app\model\AuditFinding as AuditFindingModel;
use app\model\AuditSchedule;
use app\model\Capa;
use app\service\WorkflowService;
use think\facade\Session;
use think\facade\View;

class AuditFinding extends BusinessBase
{
    protected string $modelClass = AuditFindingModel::class;
    protected string $viewPrefix = 'audit_finding';
    protected string $pageTitle = '审核发现';
    protected array $validateRules = [
        'audit_schedule_id' => 'require',
        'description' => 'require',
        'due_date' => 'date',
    ];
    protected array $validateMessages = [
        'audit_schedule_id.require' => '请选择审核日程',
        'description.require' => '发现描述不能为空',
        'due_date.date' => '截止日期格式不正确',
    ];

    protected function assignFormContext(): void
    {
        $this->assignCommonForm();
        $this->assignStatusLabels('audit_finding');
        View::assign('auditSchedules', AuditSchedule::where('soft_delete', 0)->select());
        View::assign('findingTypes', ['major' => '严重不符合', 'minor' => '一般不符合', 'observation' => '观察项']);
    }

    public function add()
    {
        if ($this->request->isPost()) {
            $data = $this->request->post();
            if (empty($data['finding_number'])) {
                $data['finding_number'] = qms_next_number('AF', AuditFindingModel::class, 'finding_number');
            }
            $errors = $this->validateFormData($data);
            if ($errors !== []) {
                return $this->renderFormValidationFailure($data, $this->viewPrefix . '/add');
            }
            $model = $this->getModel();
            $model->save($data);
            Session::flash('success', '审核发现已登记');

            return redirect($this->listRedirectUrl());
        }
        View::assign('pageTitle', $this->pageTitle . ' - 新增');
        $this->assignFormContext();

        return View::fetch($this->viewPrefix . '/add');
    }

    public function view()
    {
        $id = $this->request->param('id');
        $record = AuditFindingModel::find($id);
        if (!$record) {
            abort(404);
        }
        $this->assignFormContext();
        View::assign('record', $record);
        View::assign('capa', $record->capa_id ? Capa::find($record->capa_id) : null);
        View::assign('schedule', AuditSchedule::find($record->audit_schedule_id));
        View::assign('pageTitle', $this->pageTitle . ' - 详情');

        return View::fetch($this->viewPrefix . '/view');
    }

    public function createCapa()
    {
        $id = $this->request->param('id');
        $record = AuditFindingModel::find($id);
        if (!$record || $record->capa_id) {
            Session::flash('error', '无法创建CAPA');

            return redirect('/audit_finding/view?id=' . $id);
        }
        $capa = WorkflowService::createCapaFromSource(
            'audit',
            $record->id,
            $record->description,
            WorkflowService::resolveCapaSourceId('audit'),
            $record->responsible_id,
            $record->due_date
        );
        Session::flash('success', "已创建 CAPA {$capa->capa_number}");

        return redirect('/capa/view?id=' . $capa->id);
    }
}
