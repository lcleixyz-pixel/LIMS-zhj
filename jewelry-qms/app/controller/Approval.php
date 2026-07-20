<?php
declare(strict_types=1);

namespace app\controller;

use app\BaseController;
use app\model\Approval as ApprovalModel;
use app\model\Document as DocumentModel;
use app\service\ApprovalService;
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
                }
                Session::flash('success', '审批已处理');
            } else {
                Session::flash('error', '审批处理失败，请检查权限或状态');
            }
        }

        return redirect((string) $this->request->header('referer', '/dashboard/index'));
    }
}
