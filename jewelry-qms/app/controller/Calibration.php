<?php
declare(strict_types=1);

namespace app\controller;

use app\model\Calibration as CalibrationModel;
use app\model\Equipment;
use app\service\EquipmentEvidenceService;
use app\service\FileService;
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

    protected function resultLabels(): array
    {
        return ['pass' => '合格', 'fail' => '不合格', 'limited' => '限用'];
    }

    protected function assignResultOptions(): void
    {
        $labels = $this->resultLabels();
        $options = [];
        foreach ($labels as $value => $label) {
            $options[] = [
                'value' => $value,
                'label' => $label,
            ];
        }

        View::assign('resultLabels', $labels);
        View::assign('resultOptions', $options);
    }

    protected function assignFormContext(): void
    {
        View::assign('equipments', Equipment::where('soft_delete', 0)->select());
        View::assign('currentEquipmentId', (string)$this->request->param('equipment_id', ''));
        View::assign('today', date('Y-m-d'));
        $this->assignResultOptions();
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

    public function view()
    {
        $id = (string)$this->request->param('id', '');
        $record = CalibrationModel::find($id);
        if (!$record) {
            abort(404, '记录不存在');
        }

        View::assign('record', $record);
        View::assign('equipment', $record->equipment_id ? Equipment::find($record->equipment_id) : null);
        View::assign('certificateFiles', EquipmentEvidenceService::calibrationCertificateAttachments($id));
        $this->assignResultOptions();
        View::assign('pageTitle', $this->pageTitle . ' - 详情');

        return View::fetch($this->viewPrefix . '/view');
    }

    public function uploadCertificate()
    {
        $id = (string)$this->request->post('id', '');
        $record = CalibrationModel::find($id);
        if (!$record) {
            abort(404, '记录不存在');
        }

        $comment = trim((string)$this->request->post('comment', ''));
        $attachment = EquipmentEvidenceService::uploadCalibrationCertificate($_FILES['certificate_file'] ?? [], $id, $comment);
        Session::flash($attachment ? 'success' : 'error', $attachment ? '校准证书附件已上传' : '证书附件上传失败，请检查格式和大小');

        return redirect('/calibration/view?id=' . $id);
    }

    public function downloadCertificate()
    {
        $id = (string)$this->request->param('id', '');
        $fileId = (string)$this->request->param('file_id', '');
        $attachment = EquipmentEvidenceService::findCalibrationCertificate($id, $fileId);
        if (!$attachment) {
            abort(404, '附件不存在');
        }

        FileService::download((string)$attachment->file_dir, (string)$attachment->file_details);
    }
}
