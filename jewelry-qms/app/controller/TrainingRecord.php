<?php
declare(strict_types=1);

namespace app\controller;

use app\model\Employee;
use app\model\Training as TrainingModel;
use app\model\TrainingRecord as TrainingRecordModel;
use think\facade\View;

class TrainingRecord extends CrudBase
{
    protected string $modelClass = TrainingRecordModel::class;
    protected string $viewPrefix = 'training_record';
    protected string $pageTitle = '培训实施记录';

    public function index()
    {
        $items = TrainingRecordModel::with(['training', 'employee'])
            ->order('created', 'desc')
            ->paginate(20);
        View::assign('items', $items);
        View::assign('pages', $items->render());
        View::assign('pageTitle', $this->pageTitle);

        return View::fetch($this->viewPrefix . '/index');
    }

    protected function assignFormContext(): void
    {
        View::assign('trainings', TrainingModel::where('soft_delete', 0)->order('training_date', 'desc')->select());
        View::assign('employees', Employee::where('soft_delete', 0)->select());
    }
}
