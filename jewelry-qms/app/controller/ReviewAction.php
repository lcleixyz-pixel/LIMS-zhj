<?php
declare(strict_types=1);

namespace app\controller;

use app\model\ManagementReview;
use app\model\ReviewAction as ReviewActionModel;
use app\service\WorkflowService;
use think\facade\Session;
use think\facade\View;

class ReviewAction extends BusinessBase
{
    protected string $modelClass = ReviewActionModel::class;
    protected string $viewPrefix = 'review_action';
    protected string $pageTitle = '评审决议';

    protected function assignFormContext(): void
    {
        $this->assignUsers();
        $this->assignStatusLabels('review_action');
        View::assign('managementReviews', ManagementReview::where('soft_delete', 0)->select());
    }

    public function verify()
    {
        $id = $this->request->param('id');
        $record = ReviewActionModel::find($id);
        if ($this->request->isPost() && $record) {
            $record->status = 'completed';
            $record->completion_date = $this->request->post('completion_date', date('Y-m-d'));
            $record->verification = $this->request->post('verification', '');
            $record->save();
            Session::flash('success', '决议已验证完成');

            return redirect('/review_action/view?id=' . $id);
        }
        View::assign('record', $record);

        return View::fetch($this->viewPrefix . '/verify');
    }

    public function createCapa()
    {
        $id = $this->request->param('id');
        $record = ReviewActionModel::find($id);
        if (!$record) {
            return redirect('/review_action/index');
        }
        $capa = WorkflowService::createCapaFromSource(
            'review',
            $record->id,
            $record->action_item,
            WorkflowService::resolveCapaSourceId('review'),
            $record->responsible_id,
            $record->due_date
        );
        Session::flash('success', "已创建 CAPA {$capa->capa_number}");

        return redirect('/capa/view?id=' . $capa->id);
    }
}
