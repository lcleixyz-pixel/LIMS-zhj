<?php
declare(strict_types=1);

namespace app\controller;

use app\BaseController;
use app\model\Approval;
use app\model\Department;
use app\model\DocCategory;
use app\model\Document as DocumentModel;
use app\model\DocumentDistribution;
use app\model\DocumentRevision;
use app\model\DocumentReview;
use app\model\DocTemplate;
use app\model\User;
use app\service\ApprovalService;
use app\service\DocumentControlService;
use app\service\FieldAuditService;
use app\service\FileService;
use app\service\QmsDocumentStructureService;
use think\exception\ValidateException;
use think\exception\HttpException;
use think\facade\Db;
use think\facade\Session;
use think\facade\View;

class Document extends BaseController
{
    public function index()
    {
        $query = DocumentModel::where('soft_delete', 0);

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
            $errors = $this->validateDocumentInput($data);
            if ($errors !== []) {
                $this->flashValidationErrors($errors);
                View::assign('form', $data);
                $this->_setFormLists();

                return View::fetch('document/add');
            }

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

            Db::transaction(function () use ($document, $data, $id) {
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
            });

            Session::flash('success', '文件已创建');

            return redirect('/document/view?id=' . $id);
        }

        $this->_setFormLists();
        View::assign('form', [
            'version' => '1.0',
        ]);

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
            $errors = $this->validateDocumentInput($data, (string)$id);
            if ($errors !== []) {
                $this->flashValidationErrors($errors);
                $document->setAttrs($data);
                View::assign('doc', $document);
                View::assign('record', $document);
                $this->_setFormLists();

                return View::fetch('document/edit');
            }

            if (!empty($_FILES['document_file']['name'])) {
                $upload = FileService::upload($_FILES['document_file'], 'documents', $id);
                if ($upload) {
                    $data['file_name'] = $upload['file_name'];
                    $data['file_path'] = $upload['file_path'];
                    $data['file_type'] = $upload['file_type'];
                }
            }
            Db::transaction(function () use ($document, $data) {
                $document->save($data);
            });
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
        $doc = DocumentModel::find($id);
        if (!$doc) {
            throw new HttpException(404, '文件不存在');
        }

        $categoryName = '-';
        if (!empty($doc->category_id)) {
            $category = DocCategory::find($doc->category_id);
            $categoryName = $category ? $category->name : '-';
        }

        $departmentName = '-';
        if (!empty($doc->department_id)) {
            $department = Department::find($doc->department_id);
            $departmentName = $department ? $department->name : '-';
        }

        $revisions = DocumentRevision::where('document_id', $id)
            ->order('created', 'desc')
            ->select();

        $approvals = Approval::with('user')
            ->where('record', $id)
            ->where('model_name', 'Document')
            ->where('soft_delete', 0)
            ->order('approval_level', 'asc')
            ->select();

        $distributions = DocumentDistribution::with('user')
            ->where('document_id', $id)
            ->where('soft_delete', 0)
            ->order('distributed_at', 'desc')
            ->select();

        $reviews = DocumentReview::with('reviewer')
            ->where('document_id', $id)
            ->where('soft_delete', 0)
            ->order('review_date', 'desc')
            ->order('created', 'desc')
            ->select();

        View::assign('doc', $doc);
        View::assign('categoryName', $categoryName);
        View::assign('departmentName', $departmentName);
        View::assign('revisions', $revisions);
        View::assign('approvals', $approvals);
        View::assign('fieldChangeLogs', FieldAuditService::logsFor('Document', (string)$id));
        View::assign('distributions', $distributions);
        View::assign('reviews', $reviews);
        View::assign('distributionUsers', User::where('soft_delete', 0)->where('publish', 1)->order('name', 'asc')->select());
        View::assign('structureSummary', QmsDocumentStructureService::controlledDocumentStructureSummary((string)$doc->id));

        return View::fetch('document/view');
    }

    public function distribute()
    {
        $id = (string)$this->request->post('id', '');
        $userIds = (array)$this->request->post('user_ids', []);
        $remarks = (string)$this->request->post('remarks', '');
        $count = DocumentControlService::distribute($id, $userIds, null, $remarks);
        Session::flash('success', $count > 0 ? "已分发给 {$count} 位接收人" : '没有新增分发记录');

        return redirect('/document/view?id=' . $id);
    }

    public function confirmReceipt()
    {
        $distributionId = (string)$this->request->post('distribution_id', '');
        $documentId = (string)$this->request->post('document_id', '');
        $ok = DocumentControlService::confirmReceipt($distributionId, Session::get('user.id'));
        Session::flash($ok ? 'success' : 'error', $ok ? '已确认接收' : '无法确认该分发记录');

        return redirect('/document/view?id=' . $documentId);
    }

    public function confirmRecall()
    {
        $distributionId = (string)$this->request->post('distribution_id', '');
        $documentId = (string)$this->request->post('document_id', '');
        $ok = DocumentControlService::confirmRecall($distributionId, Session::get('user.id'));
        Session::flash($ok ? 'success' : 'error', $ok ? '已确认回收' : '无法确认该分发记录');

        return redirect('/document/view?id=' . $documentId);
    }

    public function review()
    {
        $id = (string)$this->request->param('id', '');
        $doc = DocumentModel::find($id);
        if (!$doc) {
            throw new HttpException(404, '文件不存在');
        }

        if ($this->request->isPost()) {
            $result = (string)$this->request->post('result', '');
            $note = trim((string)$this->request->post('review_note', ''));
            $nextReviewDate = (string)$this->request->post('next_review_date', '');
            $review = DocumentControlService::recordReview($id, $result, $note, $nextReviewDate !== '' ? $nextReviewDate : null, Session::get('user.id'));
            Session::flash($review ? 'success' : 'error', $review ? '评审记录已保存' : '评审记录保存失败');

            return redirect('/document/view?id=' . $id);
        }

        View::assign('doc', $doc);
        View::assign('record', $doc);

        return View::fetch('document/review');
    }

    public function obsolete()
    {
        $id = (string)$this->request->post('id', '');
        $note = trim((string)$this->request->post('review_note', ''));
        $review = DocumentControlService::recordReview($id, 'obsolete', $note !== '' ? $note : '文件作废并发起回收确认', null, Session::get('user.id'));
        Session::flash($review ? 'success' : 'error', $review ? '文件已作废，回收确认通知已发出' : '文件作废失败');

        return redirect('/document/view?id=' . $id);
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

            Db::transaction(function () use ($doc, $update, $id) {
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
                $doc->save($update);
            });
            $message = '已生成修订版本 ' . $newVersion;
            try {
                $structure = QmsDocumentStructureService::refreshControlledDocumentStructure(
                    (string)$doc->id,
                    '文件控制修订同步：' . (string)$this->request->post('change_reason', '')
                );
                $message .= '，结构化文件已同步为草稿：' . (string)($structure['structured_document']['rendered_file_path'] ?? '');
            } catch (\Throwable $exception) {
                $message .= '；结构化同步待处理：' . $exception->getMessage();
            }
            Session::flash('success', $message);

            return redirect('/document/view?id=' . $id);
        }

        View::assign('doc', $doc);
        View::assign('record', $doc);

        return View::fetch('document/revise');
    }

    public function submitReview()
    {
        $id = $this->request->param('id');
        $doc = DocumentModel::find($id);
        if ($doc) {
            Db::transaction(function () use ($doc) {
                $doc->status = 'reviewing';
                $doc->save();
            });
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
        View::assign('reviewers', $employees);
        View::assign('approvers', $employees);
    }

    protected function _employeeToUser(?string $employeeId): ?string
    {
        if (!$employeeId) {
            return null;
        }
        $user = User::where('employee_id', $employeeId)->find();

        return $user ? $user->id : null;
    }

    private function validateDocumentInput(array $data, ?string $recordId = null): array
    {
        try {
            $this->validate($data, [
                'doc_number' => [
                    'require',
                    $this->uniqueDocumentNumberRule($recordId),
                ],
                'title' => 'require',
                'level' => 'require',
            ], [
                'doc_number.require' => '文件编号不能为空',
                'title.require' => '文件标题不能为空',
                'level.require' => '请选择文件层级',
            ], true);
        } catch (ValidateException $exception) {
            $error = $exception->getError();
            return is_array($error) ? array_values($error) : [(string)$error];
        }

        return [];
    }

    private function uniqueDocumentNumberRule(?string $recordId): \Closure
    {
        return function ($value) use ($recordId) {
            $value = trim((string)$value);
            if ($value === '') {
                return true;
            }

            $query = DocumentModel::where('doc_number', $value)->where('soft_delete', 0);
            if ($recordId !== null && $recordId !== '') {
                $query->where('id', '<>', $recordId);
            }

            return $query->count() === 0 ? true : '文件编号已存在';
        };
    }

    private function flashValidationErrors(array $errors): void
    {
        Session::flash('validation_errors', $errors);
    }
}
