<?php
declare(strict_types=1);

namespace app\controller;

use app\model\Training as TrainingModel;

class Training extends CrudBase
{
    protected string $modelClass = TrainingModel::class;
    protected string $viewPrefix = 'training';
    protected string $pageTitle = '培训记录';
}
