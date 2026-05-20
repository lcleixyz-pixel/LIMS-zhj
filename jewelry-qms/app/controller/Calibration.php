<?php
declare(strict_types=1);

namespace app\controller;

use app\model\Calibration as CalibrationModel;
use app\model\Equipment;
use think\facade\Session;
use think\facade\View;

class Calibration extends BusinessBase
{
    protected string $modelClass = CalibrationModel::class;
    protected string $viewPrefix = 'calibration';
    protected string $pageTitle = '校准记录';

    protected function assignFormContext(): void
    {
        View::assign('equipments', Equipment::where('soft_delete', 0)->select());
        View::assign('resultOptions', ['pass' => '合格', 'fail' => '不合格', 'limited' => '限用']);
    }

    public function add()
    {
        if ($this->request->isPost()) {
            $data = $this->request->post();
            $model = $this->getModel();
            $model->save($data);
            $equipment = Equipment::find($data['equipment_id'] ?? '');
            if ($equipment) {
                $equipment->last_calibration_date = $data['calibration_date'];
                $equipment->next_calibration_date = $data['next_due_date'] ?? date('Y-m-d', strtotime('+' . ($equipment->calibration_cycle_months ?: 12) . ' months', strtotime($data['calibration_date'])));
                if (($data['result'] ?? '') === 'fail') {
                    $equipment->status = 'maintenance';
                } elseif (($data['result'] ?? '') === 'limited') {
                    $equipment->status = 'maintenance';
                } else {
                    $equipment->status = 'active';
                }
                $equipment->save();
            }
            Session::flash('success', '校准记录已保存，设备台账已更新');

            return redirect($this->listRedirectUrl());
        }
        View::assign('pageTitle', $this->pageTitle . ' - 新增');
        $this->assignFormContext();

        return View::fetch($this->viewPrefix . '/add');
    }
}
