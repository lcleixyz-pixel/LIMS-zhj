<?php
declare(strict_types=1);

namespace app\controller;

use app\model\Equipment as EquipmentModel;

class Equipment extends CrudBase
{
    protected string $modelClass = EquipmentModel::class;
    protected string $viewPrefix = 'equipment';
    protected string $pageTitle = '设备台账';
}
