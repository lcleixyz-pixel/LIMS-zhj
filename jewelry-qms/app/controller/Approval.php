<?php
declare(strict_types=1);

namespace app\controller;

use app\BaseController;
use app\model\Approval as ApprovalModel;
use app\model\Document as DocumentModel;
use app\service\ApprovalService;
use app\service\DocuSealService;
use app\service\TrialModeService;
use DomainException;
use think\facade\Db;
use think\facade\Session;

class Approval extends BaseController
{
    public function approve()
    {
        if ($this->request->isPost()) {
            $id = $this->request->param('id');
            $status = $this->request->post('status');
            $comments = $this->request->post('comments', '');
            $pendingApproval = ApprovalModel::find($id);
            $pendingDocument = $pendingApproval && (string)$pendingApproval->model_name === 'Document'
                ? DocumentModel::find($pendingApproval->record)
                : null;
            if ($status === 'approved' && $pendingDocument) {
                try {
                    TrialModeService::assertDocumentApprovalAllowed($pendingDocument);
                } catch (DomainException $exception) {
                    Session::flash('error', $exception->getMessage());

                    return redirect((string)$this->request->header('referer', '/dashboard/index'));
                }
            }

            if (ApprovalService::processApproval($id, $status, $comments)) {
                $approval = ApprovalModel::find($id);
                if ($status === 'approved' && $approval && $approval->model_name === 'Document') {
                    $doc = DocumentModel::find($approval->record);
                    if ($doc) {
                        Db::transaction(function () use ($doc) {
                            ApprovalService::finalizeDocumentIfFullyApproved($doc);
                        });
                    }
                } elseif ($status === 'rejected' && $approval && $approval->model_name === 'Document') {
                    $doc = DocumentModel::find($approval->record);
                    if ($doc) {
                        $rejected = (new DocuSealService())->rejectDocumentWorkflow(
                            (string)$doc->id,
                            null,
                            [(string)Session::get('user.email', '')],
                            $comments !== '' ? $comments : '签批人已驳回'
                        );
                        if (!($rejected['ok'] ?? false)) {
                            Session::flash('error', '驳回决定已记录，但文件未能退回草稿，请联系质量负责人核对。');

                            return redirect((string)$this->request->header('referer', '/dashboard/index'));
                        }
                    }
                }
                Session::flash(
                    'success',
                    $status === 'rejected'
                        ? '文件已驳回并退回草稿。编制人修改后可重新提交。'
                        : '签批已完成。系统已保存本次结果并进入下一环节。'
                );
            } else {
                Session::flash('error', '审批处理失败，请检查权限或状态');
            }
        }

        return redirect((string) $this->request->header('referer', '/dashboard/index'));
    }
}
