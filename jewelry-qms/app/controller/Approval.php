<?php
declare(strict_types=1);

namespace app\controller;

use app\BaseController;
use app\model\Approval as ApprovalModel;
use app\model\Document as DocumentModel;
use app\service\ApprovalService;
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

            if (ApprovalService::processApproval($id, $status, $comments)) {
                $approval = ApprovalModel::find($id);
                if ($status === 'approved' && $approval && $approval->model_name === 'Document') {
                    $doc = DocumentModel::find($approval->record);
                    if ($doc && ApprovalService::isFullyApproved('Document', $approval->record, (int) $doc->level)) {
                        Db::transaction(function () use ($doc) {
                            $doc->save([
                                'status' => 'published',
                                'publish' => 1,
                                'effective_date' => $doc->effective_date ?: date('Y-m-d'),
                            ]);
                            $supersededId = trim((string)$doc->supersedes_document_id);
                            if ($supersededId !== '') {
                                DocumentModel::where('id', $supersededId)
                                    ->where('status', 'published')
                                    ->update([
                                        'status' => 'obsolete',
                                        'publish' => 0,
                                    ]);
                            }
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
