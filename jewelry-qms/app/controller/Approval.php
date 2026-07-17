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
                    if ($doc && ApprovalService::isFullyApproved('Document', $approval->record, (int) $doc->level)) {
                        Db::transaction(function () use ($doc) {
                            $isTrialDocument = TrialModeService::isSimulationNumber((string)$doc->doc_number);
                            $targetStatus = $isTrialDocument ? 'trial_ready' : 'published';
                            $update = [
                                'status' => $targetStatus,
                                'publish' => 1,
                            ];
                            if (!$isTrialDocument) {
                                $update['effective_date'] = $doc->effective_date ?: date('Y-m-d');
                            }
                            $doc->save($update);
                            $supersededId = trim((string)$doc->supersedes_document_id);
                            if ($supersededId !== '') {
                                $superseded = DocumentModel::find($supersededId);
                                $maySupersede = $superseded && (
                                    (!$isTrialDocument && (string)$superseded->status === 'published')
                                    || ($isTrialDocument
                                        && TrialModeService::isSimulationNumber((string)$superseded->doc_number)
                                        && (string)$superseded->status === 'trial_ready')
                                );
                                if ($maySupersede) {
                                    $superseded->save([
                                        'status' => 'obsolete',
                                        'publish' => 0,
                                    ]);
                                }
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
