<?php
declare(strict_types=1);

namespace app\controller;

use app\model\AuditPlan as AuditPlanModel;
use app\model\AuditSchedule;
use think\facade\Session;
use think\facade\View;

class AuditPlan extends BusinessBase
{
    protected string $modelClass = AuditPlanModel::class;
    protected string $viewPrefix = 'audit_plan';
    protected string $pageTitle = '内审计划';

    protected function assignFormContext(): void
    {
        $this->assignUsers();
        $this->assignStatusLabels('audit_plan');
    }

    public function view()
    {
        $id = $this->request->param('id');
        $record = AuditPlanModel::find($id);
        if (!$record) {
            abort(404);
        }
        $schedules = AuditSchedule::where('audit_plan_id', $id)->where('soft_delete', 0)->order('audit_date')->select();
        $this->assignFormContext();
        View::assign('record', $record);
        View::assign('schedules', $schedules);
        View::assign('pageTitle', $this->pageTitle . ' - 详情');

        return View::fetch($this->viewPrefix . '/view');
    }

    public function approve()
    {
        $id = $this->request->param('id');
        $record = AuditPlanModel::find($id);
        if ($record && $record->status === 'draft') {
            $record->status = 'approved';
            $record->approved_by = Session::get('user.id');
            $record->approved_date = date('Y-m-d');
            $record->save();
            Session::flash('success', '内审计划已批准');
        }

        return redirect('/audit_plan/view?id=' . $id);
    }
}
