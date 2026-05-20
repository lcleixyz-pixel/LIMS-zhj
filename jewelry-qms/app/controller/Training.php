<?php
declare(strict_types=1);

namespace app\controller;

use app\model\Training as TrainingModel;
use app\model\TrainingPlan;
use app\model\TrainingRecord;
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
        View::assign('typeOptions', ['internal' => '内部培训', 'external' => '外部培训', 'on_job' => '在岗培训']);
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
        View::assign('trainingRecords', $records);
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
