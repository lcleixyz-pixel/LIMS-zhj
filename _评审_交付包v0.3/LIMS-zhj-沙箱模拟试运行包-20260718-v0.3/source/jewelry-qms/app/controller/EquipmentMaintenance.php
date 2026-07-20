<?php
declare(strict_types=1);

namespace app\controller;

use app\model\Equipment;
use app\model\EquipmentMaintenance as EquipmentMaintenanceModel;
use think\facade\Session;
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

    public function view()
    {
        $id = (string)$this->request->param('id', '');
        $record = EquipmentMaintenanceModel::where('id', $id)->where('soft_delete', 0)->find();
        if (!$record) {
            Session::flash('warning', '关联维护保养记录不存在，可能来自已失效的待办提醒。请复核提醒来源或从维护保养列表重新进入。');

            return redirect('/equipment_maintenance/index');
        }

        View::assign('record', $record);
        View::assign('fields', $this->buildViewFields($record));
        View::assign('pageTitle', $this->pageTitle . ' - 详情');
        $this->assignFormContext();

        return View::fetch($this->viewPrefix . '/view');
    }
}
