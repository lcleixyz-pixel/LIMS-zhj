<?php
declare(strict_types=1);

namespace app\controller;

use app\model\ManagementReview as ManagementReviewModel;
use app\model\ReviewAction;
use app\service\ManagementReviewInputService;
use app\service\ExternalEvidenceReferenceService;
use app\service\TrialModeService;
use think\facade\Session;
use think\facade\View;

class ManagementReview extends BusinessBase
{
    protected string $modelClass = ManagementReviewModel::class;
    protected string $viewPrefix = 'management_review';
    protected string $pageTitle = '管理评审';
    protected array $writableFields = [
        'review_number', 'review_date', 'title', 'participants', 'inputs',
        'outputs', 'resolutions', 'chairperson_id',
    ];
    protected array $validateRules = [
        'review_date' => 'require|date',
        'title' => 'require|max:200',
    ];
    protected array $validateMessages = [
        'review_date.require' => '评审日期不能为空',
        'review_date.date' => '评审日期格式不正确',
        'title.require' => '评审主题不能为空',
        'title.max' => '评审主题不能超过 200 字',
    ];

    protected function assignFormContext(): void
    {
        $this->assignUsers();
        $this->assignStatusLabels('management_review');
    }

    public function add()
    {
        if ($this->request->isPost()) {
            $data = $this->onlyWritable($this->request->post());
            if (empty($data['review_number'])) {
                $data['review_number'] = qms_next_number('MR', ManagementReviewModel::class, 'review_number');
            }
            if (TrialModeService::isEnabled()) {
                $data['review_number'] = TrialModeService::simulationNumber((string)$data['review_number']);
            }
            $errors = $this->validateFormData($data);
            if ($errors !== []) {
                return $this->renderFormValidationFailure($data, $this->viewPrefix . '/add');
            }
            $snapshot = ManagementReviewInputService::snapshot();
            $data['inputs'] = trim((string)($data['inputs'] ?? '')) . "\n\n【系统自动汇总】\n"
                . ManagementReviewInputService::format($snapshot);
            $data['input_snapshot'] = json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $model = $this->getModel();
            $model->save($data);
            Session::flash('success', '管理评审已创建');

            return redirect($this->listRedirectUrl());
        }
        View::assign('pageTitle', $this->pageTitle . ' - 新增');
        $snapshot = ManagementReviewInputService::snapshot();
        View::assign('inputCategories', $snapshot['categories']);
        $this->assignFormContext();

        return View::fetch($this->viewPrefix . '/add');
    }

    public function view()
    {
        $id = $this->request->param('id');
        $record = ManagementReviewModel::find($id);
        if (!$record) {
            abort(404);
        }
        $actions = ReviewAction::where('management_review_id', $id)->where('soft_delete', 0)->select();
        $snapshot = json_decode((string)$record->input_snapshot, true);
        if (!is_array($snapshot) || !isset($snapshot['categories'])) {
            $snapshot = ManagementReviewInputService::snapshot();
        }
        $this->assignFormContext();
        View::assign('record', $record);
        View::assign('actions', $actions);
        View::assign('inputCategories', $snapshot['categories']);
        View::assign('evidenceReferences', ExternalEvidenceReferenceService::forSubject('management_review', (string)$id));
        View::assign('evidenceSubjectType', 'management_review');
        View::assign('evidenceSubjectId', (string)$id);
        View::assign('pageTitle', $this->pageTitle . ' - 详情');

        return View::fetch($this->viewPrefix . '/view');
    }

    public function complete()
    {
        $id = $this->request->param('id');
        if (!$this->request->isPost()) {
            Session::flash('error', '请从管理评审详情页提交完成动作。');

            return redirect('/management_review/view?id=' . $id);
        }
        $record = ManagementReviewModel::find($id);
        if ($record) {
            $record->status = 'completed';
            $record->save();
            Session::flash('success', '管理评审已标记完成');
        }

        return redirect('/management_review/view?id=' . $id);
    }
}
