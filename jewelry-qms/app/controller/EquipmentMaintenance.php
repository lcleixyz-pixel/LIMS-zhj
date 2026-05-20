<?php
declare(strict_types=1);

namespace app\controller;

use app\model\Equipment;
use app\model\EquipmentMaintenance as EquipmentMaintenanceModel;
use think\facade\View;

class EquipmentMaintenance extends BusinessBase
{
    protected string $modelClass = EquipmentMaintenanceModel::class;
    protected string $viewPrefix = 'equipment_maintenance';
    protected string $pageTitle = '维护保养';

    protected function assignFormContext(): void
    {
        View::assign('equipments', Equipment::where('soft_delete', 0)->select());
        View::assign('typeOptions', [
            'routine' => '例行保养',
            'repair' => '维修',
            'verification' => '期间核查',
        ]);
    }
}
