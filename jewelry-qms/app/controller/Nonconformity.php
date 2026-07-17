<?php
declare(strict_types=1);

namespace app\controller;

use app\model\Capa;
use app\model\Nonconformity as NonconformityModel;
use app\service\WorkflowService;
use app\service\FieldAuditService;
use app\service\ExternalEvidenceReferenceService;
use think\facade\Session;
use think\facade\View;

class Nonconformity extends BusinessBase
{
    protected string $modelClass = NonconformityModel::class;
    protected string $viewPrefix = 'nonconformity';
    protected string $pageTitle = '不符合工作';
    protected array $writableFields = [
        'nc_number',
        'identified_date',
        'source',
        'severity',
        'disposition',
        'report_number',
        'assigned_to',
        'description',
        'impact_assessment',
        'immediate_action',
    ];
    protected array $validateMessages = [
        'identified_date.require' => '发现日期不能为空',
        'identified_date.date' => '发现日期格式不正确',
        'source.require' => '请选择来源',
        'severity.require' => '请选择严重程度',
        'description.require' => '不符合描述不能为空',
    ];

    protected function validationRules(array $data, ?string $recordId = null): array
    {
        $rules = [];
        foreach ([
            'identified_date' => 'require|date',
            'source' => 'require',
            'severity' => 'require',
            'description' => 'require',
        ] as $field => $rule) {
            if ($recordId === null || array_key_exists($field, $data)) {
                $rules[$field] = $rule;
            }
        }

        return $rules;
    }

    protected function assignFormContext(): void
    {
        $this->assignUsers();
        $this->assignStatusLabels('nonconformity');
        View::assign('severityOptions', [
            'minor' => '轻微',
            'major' => '严重',
            'critical' => '危急',
        ]);
        View::assign('sourceOptions', [
            'test' => '检测过程',
            'sample' => '样品',
            'equipment' => '设备',
            'document' => '文件',
            'other' => '其他',
        ]);
        View::assign('dispositionOptions', [
            'continue' => '继续',
            'suspend' => '暂停',
            'recall' => '召回',
            'other' => '其他',
        ]);
    }

    protected function assignIndexContext(): void
    {
        $this->assignFormContext();
    }

    public function add()
    {
        if ($this->request->isPost()) {
            $requestData = $this->request->post();
            $triggerCapa = !empty($requestData['trigger_capa']);
            unset($requestData['trigger_capa']);
            $data = $this->onlyWritable($requestData);
            if (empty($data['nc_number'])) {
                $data['nc_number'] = qms_next_number('NC', NonconformityModel::class, 'nc_number');
            }
            if (empty($data['identified_date'])) {
                $data['identified_date'] = date('Y-m-d');
            }
            $errors = $this->validateFormData($data);
            if ($errors !== []) {
                return $this->renderFormValidationFailure($data, $this->viewPrefix . '/add');
            }
            $data['status'] = 'open';
            $model = $this->getModel();
            $model->save($data);
            if ($triggerCapa && in_array($data['severity'] ?? '', ['major', 'critical'], true)) {
                WorkflowService::createCapaFromSource(
                    'nc',
                    $model->id,
                    $data['description'],
                    WorkflowService::resolveCapaSourceId('nc'),
                    $data['assigned_to'] ?? null,
                    $data['due_date'] ?? null
                );
            }
            Session::flash('success', '不符合工作已登记');

            return redirect($this->listRedirectUrl());
        }
        View::assign('pageTitle', $this->pageTitle . ' - 新增');
        $this->assignFormContext();

        return View::fetch($this->viewPrefix . '/add');
    }

    public function view()
    {
        $id = $this->request->param('id');
        $record = $this->findActiveRecord((string)$id);
        if (!$record) {
            abort(404);
        }
        $this->assignFormContext();
        View::assign('record', $record);
        View::assign('capa', $record->capa_id ? Capa::find($record->capa_id) : null);
        View::assign('fieldChangeLogs', FieldAuditService::displayLogsFor('Nonconformity', (string)$id));
        View::assign('evidenceReferences', ExternalEvidenceReferenceService::forSubject('quality_event', (string)$id));
        View::assign('evidenceSubjectType', 'quality_event');
        View::assign('evidenceSubjectId', (string)$id);
        View::assign('pageTitle', $this->pageTitle . ' - 详情');

        return View::fetch($this->viewPrefix . '/view');
    }

    public function createCapa()
    {
        $id = $this->request->param('id');
        $record = $this->findActiveRecord((string)$id);
        if (!$record || $record->capa_id) {
            Session::flash('error', '无法创建CAPA');

            return redirect('/nonconformity/view?id=' . $id);
        }
        $capa = WorkflowService::createCapaFromSource(
            'nc',
            $record->id,
            $record->description,
            WorkflowService::resolveCapaSourceId('nc'),
            $record->assigned_to,
            null
        );
        Session::flash('success', "已创建 CAPA {$capa->capa_number}");

        return redirect('/capa/view?id=' . $capa->id);
    }
}
