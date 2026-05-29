<?php
declare(strict_types=1);

namespace app\controller;

use app\model\Training as TrainingModel;
use app\model\TrainingPlan;
use app\model\TrainingRecord;
use app\service\TrainingEvidenceService;
use think\facade\Session;
use think\facade\View;

class Training extends BusinessBase
{
    protected string $modelClass = TrainingModel::class;
    protected string $viewPrefix = 'training';
    protected string $pageTitle = '培训活动';

    protected function assignFormContext(): void
    {
        $this->assignDepartments();
        $this->assignStatusLabels('training');
        View::assign('trainingPlans', TrainingPlan::where('soft_delete', 0)->select());
        $typeLabels = ['internal' => '内部培训', 'external' => '外部培训', 'on_job' => '在岗培训'];
        $typeOptions = [];
        foreach ($typeLabels as $value => $label) {
            $typeOptions[] = ['value' => $value, 'label' => $label];
        }
        View::assign('typeLabels', $typeLabels);
        View::assign('typeOptions', $typeOptions);
        View::assign('currentPlanId', (string)$this->request->param('training_plan_id', ''));
        View::assign('today', date('Y-m-d'));
    }

    public function view()
    {
        $id = $this->request->param('id');
        $record = TrainingModel::find($id);
        if (!$record) {
            abort(404);
        }
        $records = TrainingRecord::where('training_id', $id)->where('soft_delete', 0)->select();
        $this->assignFormContext();
        View::assign('record', $record);
        View::assign('trainingPlan', $record->training_plan_id ? TrainingPlan::find($record->training_plan_id) : null);
        View::assign('trainingRecords', $records);
        View::assign('planProgress', $record->training_plan_id ? TrainingEvidenceService::planProgress((string)$record->training_plan_id) : null);
        View::assign('pageTitle', $this->pageTitle . ' - 详情');

        return View::fetch($this->viewPrefix . '/view');
    }

    public function complete()
    {
        $id = $this->request->param('id');
        $record = TrainingModel::find($id);
        if ($record) {
            $record->status = 'completed';
            $record->save();
            Session::flash('success', '培训已标记完成');
        }

        return redirect('/training/view?id=' . $id);
    }
}
