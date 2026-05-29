<?php
declare(strict_types=1);

namespace app\controller;

use app\model\Calibration;
use app\model\Equipment as EquipmentModel;
use app\model\EquipmentMaintenance;
use app\service\FieldAuditService;
use think\facade\View;

class Equipment extends BusinessBase
{
    protected string $modelClass = EquipmentModel::class;
    protected string $viewPrefix = 'equipment';
    protected string $pageTitle = '设备台账';

    protected function assignFormContext(): void
    {
        $this->assignDepartments();
        $this->assignStatusLabels('equipment');
    }

    public function index()
    {
        $query = EquipmentModel::where('soft_delete', 0);
        if ($this->request->get('status')) {
            $query->where('status', $this->request->get('status'));
        }
        if ($this->request->get('due') === '1') {
            $query->where('calibration_required', 1)
                ->where('next_calibration_date', '<=', date('Y-m-d', strtotime('+30 days')));
        }
        $items = $query->order('next_calibration_date', 'asc')->paginate(20);
        $this->assignStatusLabels('equipment');
        View::assign('items', $items);
        View::assign('pages', $items->render());
        View::assign('pageTitle', $this->pageTitle);

        return View::fetch($this->viewPrefix . '/index');
    }

    public function view()
    {
        $id = $this->request->param('id');
        $record = EquipmentModel::find($id);
        if (!$record) {
            abort(404);
        }
        $calibrations = Calibration::where('equipment_id', $id)->where('soft_delete', 0)->order('calibration_date', 'desc')->limit(10)->select();
        $maintenances = EquipmentMaintenance::where('equipment_id', $id)->where('soft_delete', 0)->order('maintenance_date', 'desc')->limit(5)->select();
        $daysUntil = null;
        if ($record->next_calibration_date) {
            $daysUntil = (int) ((strtotime($record->next_calibration_date) - time()) / 86400);
        }
        $this->assignFormContext();
        View::assign('record', $record);
        View::assign('calibrations', $calibrations);
        View::assign('maintenances', $maintenances);
        View::assign('daysUntil', $daysUntil);
        View::assign('fieldChangeLogs', FieldAuditService::logsFor('Equipment', (string)$id));
        View::assign('pageTitle', $this->pageTitle . ' - 详情');

        return View::fetch($this->viewPrefix . '/view');
    }
}
