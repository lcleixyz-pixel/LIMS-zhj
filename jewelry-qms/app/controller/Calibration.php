<?php
declare(strict_types=1);

namespace app\controller;

use app\model\Calibration as CalibrationModel;

class Calibration extends CrudBase
{
    protected string $modelClass = CalibrationModel::class;
    protected string $viewPrefix = 'calibration';
    protected string $pageTitle = '校准记录';
}
