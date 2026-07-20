<?php
declare(strict_types=1);

namespace app\controller;

use app\model\Employee;
use app\model\Equipment;
use app\model\EquipmentAuthorization as EquipmentAuthorizationModel;
use think\facade\View;

class EquipmentAuthorization extends BusinessBase
{
    protected string $modelClass = EquipmentAuthorizationModel::class;
    protected string $viewPrefix = 'equipment_authorization';
    protected string $pageTitle = '设备授权使用人';

    protected function assignFormContext(): void
    {
        View::assign('equipments', Equipment::where('soft_delete', 0)->select());
        View::assign('employees', Employee::where('soft_delete', 0)->where('publish', 1)->select());
        View::assign('currentEquipmentId', (string)$this->request->param('equipment_id', ''));
        View::assign('today', date('Y-m-d'));
        $this->assignUsers();
        $this->assignStatusLabels('equipment_authorization');
    }
}
