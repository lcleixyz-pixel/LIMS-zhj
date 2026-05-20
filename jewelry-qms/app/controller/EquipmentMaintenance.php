<?php
declare(strict_types=1);

namespace app\controller;

use app\model\Equipment as EquipmentModel;
use app\model\EquipmentMaintenance as EquipmentMaintenanceModel;
use think\facade\View;

class EquipmentMaintenance extends CrudBase
{
    protected string $modelClass = EquipmentMaintenanceModel::class;
    protected string $viewPrefix = 'equipment_maintenance';
    protected string $pageTitle = '设备维护保养';

    public function index()
    {
        $items = EquipmentMaintenanceModel::with(['equipment'])
            ->order('maintenance_date', 'desc')
            ->paginate(20);
        View::assign('items', $items);
        View::assign('pages', $items->render());
        View::assign('pageTitle', $this->pageTitle);

        return View::fetch($this->viewPrefix . '/index');
    }

    protected function assignFormContext(): void
    {
        View::assign(
            'equipments',
            EquipmentModel::where('soft_delete', 0)->order('equipment_number', 'asc')->select()
        );
    }
}
