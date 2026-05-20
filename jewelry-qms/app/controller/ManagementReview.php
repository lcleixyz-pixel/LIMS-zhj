<?php
declare(strict_types=1);

namespace app\controller;

use app\model\ManagementReview as ManagementReviewModel;
use app\model\ReviewAction;
use app\service\WorkflowService;
use think\facade\Session;
use think\facade\View;

class ManagementReview extends BusinessBase
{
    protected string $modelClass = ManagementReviewModel::class;
    protected string $viewPrefix = 'management_review';
    protected string $pageTitle = '管理评审';

    protected function assignFormContext(): void
    {
        $this->assignUsers();
        $this->assignStatusLabels('management_review');
    }

    public function add()
    {
        if ($this->request->isPost()) {
            $data = $this->request->post();
            if (empty($data['review_number'])) {
                $data['review_number'] = qms_next_number('MR', ManagementReviewModel::class, 'review_number');
            }
            $inputs = WorkflowService::buildManagementReviewInputs();
            $data['inputs'] = ($data['inputs'] ?? '') . "\n\n【系统自动汇总】\n"
                . "未关闭CAPA: {$inputs['open_capa']}\n"
                . "未关闭投诉: {$inputs['open_complaints']}\n"
                . "未关闭不符合: {$inputs['open_nc']}\n"
                . "未关闭审核发现: {$inputs['open_findings']}\n"
                . "逾期决议: {$inputs['overdue_actions']}";
            $model = $this->getModel();
            $model->save($data);
            Session::flash('success', '管理评审已创建');

            return redirect($this->listRedirectUrl());
        }
        View::assign('pageTitle', $this->pageTitle . ' - 新增');
        View::assign('reviewInputs', WorkflowService::buildManagementReviewInputs());
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
        $this->assignFormContext();
        View::assign('record', $record);
        View::assign('actions', $actions);
        View::assign('pageTitle', $this->pageTitle . ' - 详情');

        return View::fetch($this->viewPrefix . '/view');
    }

    public function complete()
    {
        $id = $this->request->param('id');
        $record = ManagementReviewModel::find($id);
        if ($record) {
            $record->status = 'completed';
            $record->save();
            Session::flash('success', '管理评审已标记完成');
        }

        return redirect('/management_review/view?id=' . $id);
    }
}
