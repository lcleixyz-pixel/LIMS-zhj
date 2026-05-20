<?php
declare(strict_types=1);

namespace app\controller;

use app\BaseController;
use app\model\Approval;
use app\model\DocCategory;
use app\model\Document as DocumentModel;
use app\model\DocumentRevision;
use app\model\DocTemplate;
use app\model\User;
use app\service\ApprovalService;
use app\service\FileService;
use think\exception\HttpException;
use think\facade\Session;
use think\facade\View;

class Document extends BaseController
{
    public function index()
    {
        $query = DocumentModel::with(['docCategory', 'department'])->where('soft_delete', 0);

        if ($level = $this->request->param('level')) {
            $query->where('level', $level);
        }
        if ($status = $this->request->param('status')) {
            $query->where('status', $status);
        }
        if ($keyword = trim((string) $this->request->param('keyword', ''))) {
            $query->where(function ($q) use ($keyword) {
                $q->where('doc_number', 'like', '%' . $keyword . '%')
                    ->whereOr('title', 'like', '%' . $keyword . '%');
            });
        }

        $documents = $query->order('doc_number', 'asc')->paginate(20);
        $categories = DocCategory::where('soft_delete', 0)->select();

        View::assign('documents', $documents);
        View::assign('pages', $documents->render());
        View::assign('categories', $categories);
        View::assign('filter', [
            'level' => $this->request->param('level', ''),
            'status' => $this->request->param('status', ''),
            'keyword' => $this->request->param('keyword', ''),
        ]);

        return View::fetch('document/index');
    }

    public function add()
    {
        if ($this->request->isPost()) {
            $data = $this->request->post();
            $id = qms_uuid();

            $document = new DocumentModel();
            $document->id = $id;
            $document->status = 'draft';
            $document->prepared_by = Session::get('user.employee_id');

            if (!empty($_FILES['document_file']['name'])) {
                $upload = FileService::upload($_FILES['document_file'], 'documents', $id);
                if ($upload) {
                    $document->file_name = $upload['file_name'];
                    $document->file_path = $upload['file_path'];
                    $document->file_type = $upload['file_type'];
                }
            }

            $document->save($data);

            $reviewedBy = null;
            $approvedBy = null;
            if (!empty($data['reviewed_by'])) {
                $reviewedBy = $this->_employeeToUser($data['reviewed_by']);
            }
            if (!empty($data['approved_by'])) {
                $approvedBy = $this->_employeeToUser($data['approved_by']);
            }

            ApprovalService::createWorkflow(
                'document',
                'Document',
                $id,
                (int) $data['level'],
                Session::get('user.id'),
                $reviewedBy,
                $approvedBy
            );

            Session::flash('success', '文件已创建');

            return redirect('/document/view?id=' . $id);
        }

        $this->_setFormLists();

        return View::fetch('document/add');
    }

    public function edit()
    {
        $id = $this->request->param('id');
        $document = DocumentModel::find($id);
        if (!$document) {
            throw new HttpException(404, '文件不存在');
        }
        if ($document->status === 'published') {
            Session::flash('warning', '已发布的文件不能直接编辑，请使用修订流程');

            return redirect('/document/view?id=' . $id);
        }

        if ($this->request->isPost()) {
            $data = $this->request->post();
            if (!empty($_FILES['document_file']['name'])) {
                $upload = FileService::upload($_FILES['document_file'], 'documents', $id);
                if ($upload) {
                    $data['file_name'] = $upload['file_name'];
                    $data['file_path'] = $upload['file_path'];
                    $data['file_type'] = $upload['file_type'];
                }
            }
            $document->save($data);
            Session::flash('success', '已保存');

            return redirect('/document/view?id=' . $id);
        }

        View::assign('doc', $document);
        View::assign('record', $document);
        $this->_setFormLists();

        return View::fetch('document/edit');
    }

    public function view()
    {
        $id = $this->request->param('id');
        $doc = DocumentModel::with(['docCategory', 'department', 'documentRevisions'])->find($id);
        if (!$doc) {
            throw new HttpException(404, '文件不存在');
        }

        $approvals = Approval::with('user')
            ->where('record', $id)
            ->where('model_name', 'Document')
            ->where('soft_delete', 0)
            ->order('approval_level', 'asc')
            ->select();

        View::assign('doc', $doc);
        View::assign('approvals', $approvals);

        return View::fetch('document/view');
    }

    public function revise()
    {
        $id = $this->request->param('id');
        $doc = DocumentModel::find($id);
        if (!$doc) {
            throw new HttpException(404, '文件不存在');
        }

        if ($this->request->isPost()) {
            $rev = (int) $doc->revision + 1;
            $majorLetter = chr(ord('A') + (int) (($rev - 1) / 10));
            $minorNum = ($rev - 1) % 10;
            $newVersion = $majorLetter . '/' . $minorNum;

            DocumentRevision::create([
                'id' => qms_uuid(),
                'document_id' => $id,
                'version' => $doc->version,
                'revision' => $doc->revision,
                'file_path' => $doc->file_path,
                'file_name' => $doc->file_name,
                'change_reason' => $this->request->post('change_reason', ''),
                'created_by' => Session::get('user.id'),
                'created' => date('Y-m-d H:i:s'),
            ]);

            $update = [
                'version' => $newVersion,
                'revision' => $rev,
                'status' => 'draft',
                'change_reason' => $this->request->post('change_reason', ''),
                'publish' => 0,
            ];

            if (!empty($_FILES['document_file']['name'])) {
                $upload = FileService::upload($_FILES['document_file'], 'documents', $id);
                if ($upload) {
                    $update['file_name'] = $upload['file_name'];
                    $update['file_path'] = $upload['file_path'];
                    $update['file_type'] = $upload['file_type'];
                }
            }

            $doc->save($update);
            Session::flash('success', '已生成修订版本 ' . $newVersion);

            return redirect('/document/view?id=' . $id);
        }

        View::assign('doc', $doc);

        return View::fetch('document/revise');
    }

    public function submitReview()
    {
        $id = $this->request->param('id');
        $doc = DocumentModel::find($id);
        if ($doc) {
            $doc->status = 'reviewing';
            $doc->save();
            if ($doc->reviewed_by) {
                \app\service\NotificationService::notifyApprovalPending($doc->reviewed_by, $doc->title, $doc->id);
            }
            Session::flash('success', '已提交审核');
        }

        return redirect('/document/view?id=' . $id);
    }

    public function download()
    {
        $id = $this->request->param('id');
        $doc = DocumentModel::find($id);
        if (!$doc || empty($doc->file_path)) {
            throw new HttpException(404, '附件不存在');
        }
        FileService::download($doc->file_path, $doc->file_name);
    }

    protected function _setFormLists()
    {
        $categories = DocCategory::where('soft_delete', 0)
            ->order('level', 'asc')
            ->order('sort_order', 'asc')
            ->select();
        $templates = DocTemplate::where('soft_delete', 0)->select();
        $departments = \app\model\Department::where('soft_delete', 0)->select();
        $employees = \app\model\Employee::where('soft_delete', 0)->select();

        View::assign('categories', $categories);
        View::assign('templates', $templates);
        View::assign('departments', $departments);
        View::assign('employees', $employees);
    }

    protected function _employeeToUser(?string $employeeId): ?string
    {
        if (!$employeeId) {
            return null;
        }
        $user = User::where('employee_id', $employeeId)->find();

        return $user ? $user->id : null;
    }
}
