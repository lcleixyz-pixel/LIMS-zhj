<?php
declare(strict_types=1);

namespace app\controller;

use app\model\Employee;
use app\model\Training;
use app\model\TrainingRecord as TrainingRecordModel;
use think\facade\View;

class TrainingRecord extends BusinessBase
{
    protected string $modelClass = TrainingRecordModel::class;
    protected string $viewPrefix = 'training_record';
    protected string $pageTitle = '培训记录';

    protected function assignFormContext(): void
    {
        View::assign('trainings', Training::where('soft_delete', 0)->select());
        View::assign('employees', Employee::where('soft_delete', 0)->select());
        View::assign('resultOptions', ['pass' => '合格', 'fail' => '不合格', 'pending' => '待评价']);
    }
}
