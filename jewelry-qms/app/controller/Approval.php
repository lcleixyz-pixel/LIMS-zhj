<?php
declare(strict_types=1);

namespace app\controller;

use app\BaseController;
use app\model\Approval as ApprovalModel;
use app\model\Document as DocumentModel;
use app\service\ApprovalService;
use think\facade\Session;

class Approval extends BaseController
{
    public function approve()
    {
        if ($this->request->isPost()) {
            $id = $this->request->param('id');
            $status = $this->request->post('status');
            $comments = $this->request->post('comments', '');

            if (ApprovalService::processApproval($id, $status, $comments)) {
                $approval = ApprovalModel::find($id);
                if ($status === 'approved' && $approval && $approval->model_name === 'Document') {
                    $doc = DocumentModel::find($approval->record);
                    if ($doc && ApprovalService::isFullyApproved('Document', $approval->record, (int) $doc->level)) {
                        $doc->status = 'published';
                        $doc->publish = 1;
                        $doc->save();
                    }
                }
                Session::flash('success', '?????');
            } else {
                Session::flash('error', '????????');
            }
        }

        return redirect((string) $this->request->header('referer', '/dashboard/index'));
    }
}
