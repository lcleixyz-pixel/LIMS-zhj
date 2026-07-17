<?php
declare(strict_types=1);

namespace app\controller;

use app\model\AuditChecklist;
use app\model\AuditFinding;
use app\model\AuditPlan;
use app\model\AuditSchedule as AuditScheduleModel;
use app\model\Department;
use app\model\Site;
use app\model\User;
use app\service\AuditClosureService;
use app\service\WorkflowService;
use think\facade\Session;
use think\facade\View;

class AuditSchedule extends BusinessBase
{
    protected string $modelClass = AuditScheduleModel::class;
    protected string $viewPrefix = 'audit_schedule';
    protected string $pageTitle = '审核日程';

    public function index()
    {
        $items = AuditScheduleModel::with(['department', 'site'])
            ->where('soft_delete', 0)
            ->order('audit_date', 'desc')
            ->paginate(20);
        View::assign('items', $items);
        View::assign('pages', $items->render());
        View::assign('pageTitle', $this->pageTitle);
        View::assign('departmentNames', Department::where('soft_delete', 0)->column('name', 'id'));
        View::assign('siteNames', Site::where('soft_delete', 0)->column('name', 'id'));
        $this->assignIndexContext();

        return View::fetch($this->viewPrefix . '/index');
    }

    protected function assignFormContext(): void
    {
        $this->assignCommonForm();
        $this->assignStatusLabels('audit_schedule');
        View::assign('auditPlans', AuditPlan::where('soft_delete', 0)->whereIn('status', ['approved', 'in_progress'])->select());
        View::assign('sites', Site::where('soft_delete', 0)->where('status', 'active')->order('sort_order', 'asc')->select());
        View::assign('findingTypes', ['major' => '严重不符合', 'minor' => '一般不符合', 'observation' => '观察项']);
    }

    protected function assignIndexContext(): void
    {
        $this->assignFormContext();
    }

    public function add()
    {
        if ($this->request->isPost()) {
            $data = $this->request->post();
            if (WorkflowService::auditorConflict($data['auditor_id'] ?? '', $data['department_id'] ?? '')) {
                Session::flash('error', '审核员不能审核本部门（回避原则）');

                return redirect((string) $this->request->header('referer', '/audit_schedule/add'));
            }
            $model = $this->getModel();
            $model->save($data);
            $plan = AuditPlan::find($data['audit_plan_id'] ?? '');
            if ($plan && $plan->status === 'approved') {
                $plan->status = 'in_progress';
                $plan->save();
            }
            Session::flash('success', '审核日程已创建');

            return redirect($this->listRedirectUrl());
        }
        View::assign('pageTitle', $this->pageTitle . ' - 新增');
        $this->assignFormContext();

        return View::fetch($this->viewPrefix . '/add');
    }

    public function view()
    {
        $id = $this->request->param('id');
        $record = AuditScheduleModel::find($id);
        if (!$record) {
            abort(404);
        }
        $checklists = AuditChecklist::where('audit_schedule_id', $id)->where('soft_delete', 0)->select();
        $findings = AuditFinding::where('audit_schedule_id', $id)->where('soft_delete', 0)->select();
        $this->assignFormContext();
        View::assign('record', $record);
        View::assign('plan', AuditPlan::find($record->audit_plan_id));
        View::assign('auditor', $record->auditor_id ? User::find($record->auditor_id) : null);
        View::assign('checklists', $checklists);
        View::assign('findings', $findings);
        View::assign('closureBlockers', AuditClosureService::scheduleBlockingReasons((string)$id));
        View::assign('pageTitle', $this->pageTitle . ' - 详情');

        return View::fetch($this->viewPrefix . '/view');
    }

    public function complete()
    {
        $id = (string)$this->request->param('id', '');
        if (!$this->request->isPost()) {
            Session::flash('error', '完成审核日程必须从详情页提交。');

            return redirect('/audit_schedule/view?id=' . $id);
        }
        $record = AuditScheduleModel::where('id', $id)->where('soft_delete', 0)->find();
        if (!$record) {
            abort(404);
        }
        $blockers = AuditClosureService::scheduleBlockingReasons($id);
        if ($blockers !== []) {
            Session::flash('error', '审核日程不能完成：' . implode('；', $blockers));

            return redirect('/audit_schedule/view?id=' . $id);
        }
        $record->save(['status' => 'completed']);
        Session::flash('success', '审核日程已完成，检查记录、发现和 CAPA 均满足关闭条件。');

        return redirect('/audit_schedule/view?id=' . $id);
    }
}
