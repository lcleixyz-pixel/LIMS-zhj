<?php
declare(strict_types=1);

namespace app\controller;

use app\model\TrainingPlan as TrainingPlanModel;
use app\service\TrainingEvidenceService;
use think\exception\HttpException;
use think\facade\Session;
use think\facade\View;

class TrainingPlan extends BusinessBase
{
    protected string $modelClass = TrainingPlanModel::class;
    protected string $viewPrefix = 'training_plan';
    protected string $pageTitle = '培训计划';

    protected function assignFormContext(): void
    {
        $this->assignStatusLabels('training_plan');
        $years = [];
        $current = (int)date('Y');
        for ($year = $current - 1; $year <= $current + 2; $year++) {
            $years[] = $year;
        }
        View::assign('yearOptions', $years);
        View::assign('currentYear', $current);
        View::assign('typeLabels', ['internal' => '内部培训', 'external' => '外部培训', 'on_job' => '在岗培训']);
    }

    public function view()
    {
        $id = (string)$this->request->param('id', '');
        $record = TrainingPlanModel::find($id);
        if (!$record) {
            throw new HttpException(404, '培训计划不存在');
        }

        $this->assignFormContext();
        View::assign('record', $record);
        View::assign('trainingRows', TrainingEvidenceService::trainingRowsForPlan($id));
        View::assign('planProgress', TrainingEvidenceService::planProgress($id));
        View::assign('pageTitle', $this->pageTitle . ' - 详情');

        return View::fetch($this->viewPrefix . '/view');
    }

    public function approve()
    {
        return $this->changeStatus('approved', '培训计划已批准', [
            'approved_by' => Session::get('user.id'),
            'approved_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function complete()
    {
        return $this->changeStatus('completed', '培训计划已完成', [
            'completed_at' => date('Y-m-d H:i:s'),
        ]);
    }

    protected function changeStatus(string $status, string $message, array $extra): \think\Response
    {
        $id = (string)$this->request->param('id', '');
        $record = TrainingPlanModel::find($id);
        if (!$record) {
            throw new HttpException(404, '培训计划不存在');
        }

        $record->save(array_merge(['status' => $status], $extra));
        Session::flash('success', $message);

        return redirect('/training_plan/view?id=' . $id);
    }
}
