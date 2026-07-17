<?php
declare(strict_types=1);

namespace app\controller;

use app\model\AuditFinding as AuditFindingModel;
use app\model\AuditSchedule;
use app\model\Capa;
use app\service\FieldAuditService;
use app\service\FileAttachmentService;
use app\service\FileService;
use app\service\ExternalEvidenceReferenceService;
use app\service\AuditClosureService;
use app\service\TrialModeService;
use app\service\WorkflowService;
use think\exception\HttpException;
use think\facade\Db;
use think\facade\Session;
use think\facade\View;
use DomainException;

class AuditFinding extends BusinessBase
{
    protected string $modelClass = AuditFindingModel::class;
    protected string $viewPrefix = 'audit_finding';
    protected string $pageTitle = '审核发现';
    protected array $writableFields = [
        'finding_type', 'clause', 'description', 'department_id',
        'responsible_id', 'due_date',
    ];
    protected array $createWritableFields = [
        'audit_schedule_id', 'finding_type', 'clause', 'description',
        'department_id', 'responsible_id', 'due_date',
    ];
    protected array $validateRules = [
        'audit_schedule_id' => 'require',
        'description' => 'require',
        'due_date' => 'date',
    ];
    protected array $validateMessages = [
        'audit_schedule_id.require' => '请选择审核日程',
        'description.require' => '发现描述不能为空',
        'due_date.date' => '截止日期格式不正确',
    ];

    protected function findActiveRecord(string $id): ?object
    {
        return AuditFindingModel::alias('f')
            ->join('audit_schedules s', 's.id = f.audit_schedule_id')
            ->join('audit_plans p', 'p.id = s.audit_plan_id')
            ->where('f.id', $id)
            ->where('p.company_id', (string)\think\facade\Config::get('qms.company_id'))
            ->where('f.soft_delete', 0)
            ->where('s.soft_delete', 0)
            ->where('p.soft_delete', 0)
            ->field('f.*')
            ->find();
    }

    public function index()
    {
        $items = AuditFindingModel::alias('f')
            ->join('audit_schedules s', 's.id = f.audit_schedule_id')
            ->join('audit_plans p', 'p.id = s.audit_plan_id')
            ->where('p.company_id', (string)\think\facade\Config::get('qms.company_id'))
            ->where('f.soft_delete', 0)
            ->where('s.soft_delete', 0)
            ->where('p.soft_delete', 0)
            ->field('f.*')
            ->order('f.created', 'desc')
            ->paginate(20);
        View::assign('items', $items);
        View::assign('pages', $items->render());
        View::assign('pageTitle', $this->pageTitle);
        $this->assignIndexContext();

        return View::fetch($this->viewPrefix . '/index');
    }

    protected function assignFormContext(): void
    {
        $this->assignCommonForm();
        $this->assignStatusLabels('audit_finding');
        View::assign(
            'auditSchedules',
            AuditSchedule::alias('s')
                ->join('audit_plans p', 'p.id = s.audit_plan_id')
                ->where('p.company_id', (string)\think\facade\Config::get('qms.company_id'))
                ->where('p.soft_delete', 0)
                ->where('s.soft_delete', 0)
                ->where('s.status', '<>', 'completed')
                ->field('s.*')
                ->select()
        );
        View::assign('findingTypes', ['major' => '严重不符合', 'minor' => '一般不符合', 'observation' => '观察项']);
    }

    protected function assignIndexContext(): void
    {
        $this->assignFormContext();
    }

    public function add()
    {
        if ($this->request->isPost()) {
            $data = $this->onlyWritable($this->request->post());
            try {
                AuditClosureService::assertScheduleWritable((string)($data['audit_schedule_id'] ?? ''));
            } catch (DomainException $exception) {
                throw new HttpException(409, $exception->getMessage());
            }
            $findingNumber = qms_next_number('AF', AuditFindingModel::class, 'finding_number');
            if (TrialModeService::isEnabled()) {
                $findingNumber = TrialModeService::simulationNumber($findingNumber);
            }
            $data['finding_number'] = $findingNumber;
            $data['status'] = 'open';
            $errors = $this->validateFormData($data);
            if ($errors !== []) {
                return $this->renderFormValidationFailure($data, $this->viewPrefix . '/add');
            }
            $model = $this->getModel();
            $model->save($data);
            Session::flash('success', '审核发现已登记');

            return redirect($this->listRedirectUrl());
        }
        View::assign('pageTitle', $this->pageTitle . ' - 新增');
        $this->assignFormContext();

        return View::fetch($this->viewPrefix . '/add');
    }

    public function delete()
    {
        throw new HttpException(405, '审核发现不得直接删除；请通过 CAPA 验证和关闭流程保留完整留痕');
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
        View::assign('schedule', AuditSchedule::find($record->audit_schedule_id));
        View::assign('fieldChangeLogs', FieldAuditService::displayLogsFor('AuditFinding', (string)$id));
        View::assign('evidenceFiles', FileAttachmentService::attachmentsFor('AuditFinding', (string)$record->id));
        View::assign('evidenceReferences', ExternalEvidenceReferenceService::forSubject('audit', (string)$id));
        View::assign('evidenceSubjectType', 'audit');
        View::assign('evidenceSubjectId', (string)$id);
        View::assign('pageTitle', $this->pageTitle . ' - 详情');

        return View::fetch($this->viewPrefix . '/view');
    }

    public function createCapa()
    {
        $id = $this->request->param('id');
        if (!$this->request->isPost()) {
            Session::flash('error', '创建 CAPA 必须从审核发现详情页提交。');

            return redirect('/audit_finding/view?id=' . $id);
        }
        $record = $this->findActiveRecord((string)$id);
        if (!$record || $record->capa_id) {
            Session::flash('error', '无法创建CAPA');

            return redirect('/audit_finding/view?id=' . $id);
        }
        $capa = Db::transaction(function () use ($record) {
            return WorkflowService::createCapaFromSource(
                'audit',
                $record->id,
                $record->description,
                WorkflowService::resolveCapaSourceId('audit'),
                $record->responsible_id,
                $record->due_date
            );
        });
        Session::flash('success', "已创建 CAPA {$capa->capa_number}");

        return redirect('/capa/view?id=' . $capa->id);
    }

    public function uploadEvidence()
    {
        $id = (string)$this->request->post('id', '');
        $record = $this->findActiveRecord($id);
        if (!$record) {
            abort(404);
        }

        $comment = trim((string)$this->request->post('comment', ''));
        $attachment = FileAttachmentService::upload(
            $_FILES['evidence_file'] ?? [],
            'AuditFinding',
            $id,
            'audit-findings',
            $comment
        );
        Session::flash($attachment ? 'success' : 'error', $attachment ? '整改证据附件已上传' : '附件上传失败，请检查格式和大小');

        return redirect('/audit_finding/view?id=' . $id);
    }

    public function downloadEvidence()
    {
        $id = (string)$this->request->param('id', '');
        $fileId = (string)$this->request->param('file_id', '');
        $record = $this->findActiveRecord($id);
        if (!$record) {
            abort(404, '审核发现不存在');
        }
        $attachment = FileAttachmentService::findAttachment($fileId, 'AuditFinding', $id);
        if (!$attachment) {
            abort(404, '附件不存在');
        }

        FileService::download((string)$attachment->file_dir, (string)$attachment->file_details);
    }
}
