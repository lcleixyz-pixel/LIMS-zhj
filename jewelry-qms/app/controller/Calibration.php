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
    protected array $validateRules = [
        'equipment_id' => 'require',
        'calibration_date' => 'require|date',
        'next_due_date' => 'date',
    ];
    protected array $validateMessages = [
        'equipment_id.require' => '请选择校准设备',
        'calibration_date.require' => '校准日期不能为空',
        'calibration_date.date' => '校准日期格式不正确',
        'next_due_date.date' => '下次到期日期格式不正确',
    ];

    protected function assignFormContext(): void
    {
        View::assign('equipments', Equipment::where('soft_delete', 0)->select());
        View::assign('resultOptions', ['pass' => '合格', 'fail' => '不合格', 'limited' => '限用']);
    }

    public function add()
    {
        if ($this->request->isPost()) {
            $data = $this->request->post();
            $errors = $this->validateFormData($data);
            if ($errors !== []) {
                return $this->renderFormValidationFailure($data, $this->viewPrefix . '/add');
            }
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
        View::assign('form', [
            'result' => 'pass',
        ]);
        $this->assignDefaultFormContext();
        $this->assignFormContext();

        return View::fetch($this->viewPrefix . '/add');
    }
}
