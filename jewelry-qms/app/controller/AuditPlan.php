<?php
declare(strict_types=1);

namespace app\controller;

use app\model\AuditPlan as AuditPlanModel;
use app\model\AuditSchedule;
use app\service\AuditClosureService;
use think\facade\Db;
use think\facade\Session;
use think\facade\View;

class AuditPlan extends BusinessBase
{
    protected string $modelClass = AuditPlanModel::class;
    protected string $viewPrefix = 'audit_plan';
    protected string $pageTitle = '内审计划';
    protected array $writableFields = ['plan_year', 'title', 'scope', 'criteria'];
    protected array $validateRules = [
        'plan_year' => 'require|integer|between:2000,2100',
        'title' => 'require|max:200',
    ];
    protected array $validateMessages = [
        'plan_year.require' => '计划年度不能为空',
        'plan_year.integer' => '计划年度必须为四位数字',
        'plan_year.between' => '计划年度超出允许范围',
        'title.require' => '计划标题不能为空',
        'title.max' => '计划标题不能超过 200 字',
    ];

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
        foreach ($schedules as $schedule) {
            $checklistCount = Db::name('audit_checklists')
                ->where('audit_schedule_id', (string)$schedule->id)
                ->where('soft_delete', 0)
                ->count();
            $findings = Db::name('audit_findings')
                ->where('audit_schedule_id', (string)$schedule->id)
                ->where('soft_delete', 0)
                ->select()
                ->toArray();
            $findingIds = array_map(static fn (array $row): string => (string)$row['id'], $findings);
            $capaCount = $findingIds === [] ? 0 : Db::name('capas')
                ->where('source_type', 'audit')
                ->whereIn('source_record_id', $findingIds)
                ->where('soft_delete', 0)
                ->count();
            $schedule->setAttr('checklist_count', $checklistCount);
            $schedule->setAttr('finding_count', count($findings));
            $schedule->setAttr('capa_count', $capaCount);
        }
        $this->assignFormContext();
        View::assign('record', $record);
        View::assign('schedules', $schedules);
        View::assign('closureBlockers', AuditClosureService::blockingReasons((string)$id));
        View::assign('pageTitle', $this->pageTitle . ' - 详情');

        return View::fetch($this->viewPrefix . '/view');
    }

    public function approve()
    {
        $id = $this->request->param('id');
        if (!$this->request->isPost()) {
            Session::flash('error', '批准内审计划必须从详情页提交。');

            return redirect('/audit_plan/view?id=' . $id);
        }
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

    public function complete()
    {
        $id = (string)$this->request->param('id', '');
        if (!$this->request->isPost()) {
            Session::flash('error', '完成内审计划必须从详情页提交。');

            return redirect('/audit_plan/view?id=' . $id);
        }

        $record = AuditPlanModel::where('id', $id)->where('soft_delete', 0)->find();
        if (!$record) {
            abort(404);
        }
        $blockers = AuditClosureService::blockingReasons($id);
        if ($blockers !== []) {
            Session::flash('error', '内审计划不能完成：' . implode('；', $blockers));

            return redirect('/audit_plan/view?id=' . $id);
        }
        $record->save(['status' => 'completed']);
        Session::flash('success', '内审计划已完成，关联检查记录、发现和 CAPA 均已关闭。');

        return redirect('/audit_plan/view?id=' . $id);
    }
}
