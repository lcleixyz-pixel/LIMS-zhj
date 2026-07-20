<?php
declare(strict_types=1);

namespace app\controller;

use app\model\Employee;
use app\model\EmployeeCertificate as EmployeeCertificateModel;
use app\service\FileService;
use app\service\TrainingEvidenceService;
use think\exception\HttpException;
use think\facade\Session;
use think\facade\View;

class EmployeeCertificate extends BusinessBase
{
    protected string $modelClass = EmployeeCertificateModel::class;
    protected string $viewPrefix = 'employee_certificate';
    protected string $pageTitle = '人员资质证书';

    protected function assignFormContext(): void
    {
        View::assign('employees', Employee::where('soft_delete', 0)->where('publish', 1)->select());
        View::assign('currentEmployeeId', (string)$this->request->param('employee_id', ''));
        View::assign('today', date('Y-m-d'));
        $this->assignStatusLabels('employee_certificate');
    }

    public function view()
    {
        $id = (string)$this->request->param('id', '');
        $record = EmployeeCertificateModel::find($id);
        if (!$record) {
            throw new HttpException(404, '证书记录不存在');
        }

        $this->assignFormContext();
        View::assign('record', $record);
        View::assign('employee', $record->employee_id ? Employee::find($record->employee_id) : null);
        View::assign('certificateFiles', TrainingEvidenceService::certificateAttachments($id));
        View::assign('pageTitle', $this->pageTitle . ' - 详情');

        return View::fetch($this->viewPrefix . '/view');
    }

    public function uploadAttachment()
    {
        $id = (string)$this->request->post('id', '');
        $record = EmployeeCertificateModel::find($id);
        if (!$record) {
            throw new HttpException(404, '证书记录不存在');
        }

        $comment = trim((string)$this->request->post('comment', ''));
        $attachment = TrainingEvidenceService::uploadCertificateAttachment($_FILES['certificate_file'] ?? [], $id, $comment);
        Session::flash($attachment ? 'success' : 'error', $attachment ? '证书附件已上传' : '证书附件上传失败，请检查格式和大小');

        return redirect('/employee_certificate/view?id=' . $id);
    }

    public function downloadAttachment()
    {
        $id = (string)$this->request->param('id', '');
        $fileId = (string)$this->request->param('file_id', '');
        $attachment = TrainingEvidenceService::findCertificateAttachment($id, $fileId);
        if (!$attachment) {
            throw new HttpException(404, '附件不存在');
        }

        FileService::download((string)$attachment->file_dir, (string)$attachment->file_details);
    }
}
