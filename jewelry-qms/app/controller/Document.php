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
use app\model\Site;
use app\model\User;
use app\service\ApprovalService;
use app\service\ActionAuthorizationService;
use app\service\ControlledPrintService;
use app\service\DocumentControlService;
use app\service\DocumentStatusGuardService;
use app\service\FieldAuditService;
use app\service\FileService;
use app\service\QmsDocumentStructureService;
use app\service\TrialModeService;
use think\exception\ValidateException;
use think\exception\HttpException;
use think\facade\Config;
use think\facade\Db;
use think\facade\Session;
use think\facade\View;

class Document extends BaseController
{
    public function index()
    {
        $query = DocumentModel::where('soft_delete', 0);
        $manageableSiteIds = ActionAuthorizationService::documentManageableSiteIds();
        if ($manageableSiteIds !== null) {
            $query->where(function ($query) use ($manageableSiteIds) {
                $query->whereNull('site_id');
                if ($manageableSiteIds !== []) {
                    $query->whereOr('site_id', 'in', $manageableSiteIds);
                }
            });
        }

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
        View::assign('siteMap', Site::where('soft_delete', 0)->column('name', 'id'));

        return View::fetch('document/index');
    }

    public function add()
    {
        if ($this->request->isPost()) {
            $data = $this->request->post();
            if (TrialModeService::isEnabled()) {
                $data['doc_number'] = TrialModeService::simulationNumber((string)($data['doc_number'] ?? ''));
            }
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

            if (!empty($_FILES['document_file']['name'] ?? '')) {
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

            Session::flash('success', '文件已创建，当前为草稿。请补充内容后提交审核。');

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
        if ((string)$document->status !== 'draft') {
            Session::flash('warning', '当前状态不可直接编辑。如需修改，请先发起修订或走作废/换版流程。');

            return redirect('/document/view?id=' . $id);
        }

        if ($this->request->isPost()) {
            $data = $this->request->post();
            // 场所属于文件受控边界，不能借编辑动作跨场所转移。
            $data['site_id'] = (string)$document->site_id;

            $statusGuard = new DocumentStatusGuardService();
            $guard = $statusGuard->guardWrite($data, 'Document', 'edit');
            if (!$guard['allowed']) {
                Session::flash('error', '禁止通过编辑直接修改已受控状态。如需变更，请使用发起修订或作废流程。');

                return redirect('/document/edit?id=' . $id);
            }

            $errors = $this->validateDocumentInput($data, (string)$id);
            if ($errors !== []) {
                $this->flashValidationErrors($errors);
                $document->setAttrs($data);
                View::assign('doc', $document);
                View::assign('record', $document);
                $this->_setFormLists();

                return View::fetch('document/edit');
            }

            if (!empty($_FILES['document_file']['name'] ?? '')) {
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
            Session::flash('success', '文件已保存，当前仍为草稿。确认无误后可提交审核。');

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
        View::assign('siteName', $doc->site_id ? (string)(Site::where('id', $doc->site_id)->value('name') ?? '-') : '-');
        View::assign('revisions', $revisions);
        View::assign('approvals', $approvals);
        View::assign('fieldChangeLogs', FieldAuditService::displayLogsFor('Document', (string)$id));
        View::assign('distributions', $distributions);
        View::assign('distributionUserNames', User::where('soft_delete', 0)->column('name', 'id'));
        View::assign('reviews', $reviews);
        View::assign('distributionUsers', User::with('department')
            ->where('users.soft_delete', 0)
            ->where('users.publish', 1)
            ->order('users.name', 'asc')
            ->select());
        View::assign('currentUserEmail', strtolower(trim((string)Session::get('user.email', ''))));
        View::assign('structureSummary', QmsDocumentStructureService::controlledDocumentStructureSummary((string)$doc->id));
        View::assign('printLogs', ControlledPrintService::recentLogs((string)$doc->id, 5));
        $signingEmbeds = [];
        if (in_array((string)$doc->status, ['reviewing', 'draft'], true) && \app\service\DocuSealService::isSigningEnabled()) {
            try {
                $signingEmbeds = (new \app\service\DocuSealService())->latestEmbedsForDocument((string)$doc->id);
            } catch (\Throwable $e) {
                $signingEmbeds = [];
            }
        }
        View::assign('signingEmbeds', $signingEmbeds);
        View::assign('docusealSigningEnabled', \app\service\DocuSealService::isSigningEnabled());

        return View::fetch('document/view');
    }

    public function distribute()
    {
        $id = (string)$this->request->post('id', '');
        $userIds = (array)$this->request->post('user_ids', []);
        $remarks = (string)$this->request->post('remarks', '');
        $count = DocumentControlService::distribute($id, $userIds, null, $remarks);
        Session::flash('success', $count > 0 ? "已分发给 {$count} 位接收人。接收人登录后可在通知中心确认接收。" : '未新增分发记录：所选人员已在本文件分发列表中。');

        return redirect('/document/view?id=' . $id);
    }

    public function confirmReceipt()
    {
        $distributionId = (string)$this->request->post('distribution_id', '');
        $documentId = (string)$this->request->post('document_id', '');
        $ok = DocumentControlService::confirmReceipt($distributionId, Session::get('user.id'));
        Session::flash($ok ? 'success' : 'error', $ok ? '已确认接收。该文件已加入您的受控文件清单。' : '无法确认：该记录可能已被撤销，或您没有权限操作。如有疑问请联系文件管理员。');

        return redirect('/document/view?id=' . $documentId);
    }

    public function confirmRecall()
    {
        $distributionId = (string)$this->request->post('distribution_id', '');
        $documentId = (string)$this->request->post('document_id', '');
        $ok = DocumentControlService::confirmRecall($distributionId, Session::get('user.id'));
        Session::flash($ok ? 'success' : 'error', $ok ? '已确认回收。请按文件管理员要求处理本地保存的副本。' : '无法确认：该记录可能已被撤销，或您没有权限操作。如有疑问请联系文件管理员。');

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
            Session::flash($review ? 'success' : 'error', $review ? '评审记录已保存。本次评审结论将影响文件后续状态，可在评审记录中查看。' : '评审记录保存失败，请检查必填项后重试。如多次失败请联系质量负责人。');

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
        Session::flash($review ? 'success' : 'error', $review ? '文件已作废。原接收人将收到回收确认通知，作废文件不再作为受控文件使用。' : '文件作废失败，请确认您有作废权限后重试。');

        return redirect('/document/view?id=' . $id);
    }

    public function onlyoffice()
    {
        $doc = $this->loadDocument((string)$this->request->param('id', ''));
        $serverUrl = rtrim((string)Config::get('qms.onlyoffice.server_url', ''), '/');
        $enabledConfig = Config::get('qms.onlyoffice.enabled', false);
        $enabled = filter_var($enabledConfig, FILTER_VALIDATE_BOOL) && $serverUrl !== '' && !empty($doc->file_path);
        $fileType = strtolower(pathinfo((string)$doc->file_name, PATHINFO_EXTENSION));
        if ($fileType === '') {
            $fileType = strtolower((string)$doc->file_type) ?: 'docx';
        }

        $downloadUrl = $this->request->domain() . '/document/download?id=' . $doc->id;
        $editorConfig = [
            'documentType' => $this->documentTypeFor($fileType),
            'document' => [
                'fileType' => $fileType,
                'key' => md5((string)$doc->id . '|' . (string)$doc->version . '|' . (string)$doc->modified),
                'title' => (string)$doc->title,
                'url' => $downloadUrl,
            ],
            'editorConfig' => [
                'lang' => 'zh-CN',
                'mode' => 'edit',
                'user' => [
                    'id' => (string)Session::get('user.id', 'local-user'),
                    'name' => (string)Session::get('user.name', 'QMS用户'),
                ],
            ],
        ];

        View::assign('doc', $doc);
        View::assign('onlyofficeReady', $enabled);
        View::assign('onlyofficeServerUrl', $serverUrl);
        View::assign('downloadUrl', $downloadUrl);
        View::assign('editorConfigJson', json_encode($editorConfig, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return View::fetch('document/onlyoffice');
    }

    public function controlledPrint()
    {
        $doc = $this->loadDocument((string)$this->request->param('id', ''));
        if (!$this->request->isPost()) {
            Session::flash('error', '受控打印必须从文件详情页提交，直接打开链接不会产生打印记录。');

            return redirect('/document/view?id=' . $doc->id);
        }
        $copyCount = (int)$this->request->param('copy_count', 1);
        $purpose = trim((string)$this->request->param('purpose', '受控打印'));
        if ($purpose === '') {
            $purpose = '受控打印';
        }

        try {
            $printLog = ControlledPrintService::createLog($doc, $copyCount, $purpose, $this->request->ip());
        } catch (\RuntimeException $exception) {
            Session::flash('error', $exception->getMessage());

            return redirect('/document/view?id=' . $doc->id);
        }

        View::assign('doc', $doc);
        View::assign('printLog', $printLog);
        View::assign('downloadUrl', '/document/download?id=' . $doc->id);

        return View::fetch('document/controlled_print');
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
            if ($newVersion === (string)$doc->version) {
                $minorNum++;
                if ($minorNum > 9) {
                    $majorLetter = chr(ord($majorLetter) + 1);
                    $minorNum = 0;
                }
                $newVersion = $majorLetter . '/' . $minorNum;
            }

            $newId = qms_uuid();
            $newDocument = new DocumentModel();
            $newDocument->id = $newId;
            $newDocument->supersedes_document_id = (string)$doc->id;
            $newDocument->revision_root_id = trim((string)$doc->revision_root_id) ?: (string)$doc->id;
            $newDocument->category_id = $doc->category_id;
            $newDocument->template_id = $doc->template_id;
            $newDocument->level = $doc->level;
            $newDocument->doc_number = TrialModeService::isEnabled()
                ? TrialModeService::simulationNumber((string)$doc->doc_number)
                : $doc->doc_number;
            $newDocument->title = $doc->title;
            $newDocument->version = $newVersion;
            $newDocument->revision = $rev;
            $newDocument->department_id = $doc->department_id;
            $newDocument->site_id = $doc->site_id;
            $newDocument->review_date = $doc->review_date;
            $newDocument->status = 'draft';
            $newDocument->file_path = $doc->file_path;
            $newDocument->file_name = $doc->file_name;
            $newDocument->file_type = $doc->file_type;
            $newDocument->prepared_by = Session::get('user.employee_id');
            $newDocument->reviewed_by = $doc->reviewed_by;
            $newDocument->approved_by = $doc->approved_by;
            $newDocument->change_reason = trim((string)$this->request->post('change_reason', ''));
            $newDocument->publish = 0;

            if (!empty($_FILES['document_file']['name'] ?? '')) {
                $upload = FileService::upload($_FILES['document_file'], 'documents', $newId);
                if ($upload) {
                    $newDocument->file_name = $upload['file_name'];
                    $newDocument->file_path = $upload['file_path'];
                    $newDocument->file_type = $upload['file_type'];
                }
            }

            Db::transaction(function () use ($doc, $newDocument, $newId, $id) {
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
                $newDocument->save();
                ApprovalService::createWorkflow(
                    'document',
                    'Document',
                    $newId,
                    (int)$newDocument->level,
                    (string)Session::get('user.id'),
                    $this->_employeeToUser((string)$newDocument->reviewed_by),
                    $this->_employeeToUser((string)$newDocument->approved_by)
                );
            });
            $message = '已生成修订版本 ' . $newVersion . '。新版本当前为草稿，编辑完成后请提交审核。';
            try {
                $structure = QmsDocumentStructureService::refreshControlledDocumentStructure(
                    $newId,
                    '文件控制修订同步：' . (string)$this->request->post('change_reason', '')
                );
                $message .= '（关联结构化文件已同步）';
            } catch (\Throwable $exception) {
                $message .= '（关联结构化文件将在后台同步：' . $exception->getMessage() . '）';
            }
            Session::flash('success', $message);

            return redirect('/document/view?id=' . $newId);
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
            // D-3：signing 开关开启时尝试 DocuSeal submission；失败不阻断提审，轮次表留痕
            if (\app\service\DocuSealService::isSigningEnabled()) {
                try {
                    (new \app\service\DocuSealService())->startSigningForDocument($doc);
                } catch (\Throwable $e) {
                    try {
                        (new \app\service\DocuSealService())->recordSigningRound(
                            (string)$doc->id,
                            'pending',
                            null,
                            'create_exception:' . $e->getMessage()
                        );
                    } catch (\Throwable $ignored) {
                    }
                }
            }
            Session::flash('success', '文件「' . ($doc->title ?? '') . '」已提交审核。审核人、批准人将依次收到签批通知，您可在本页查看进度。');
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

    private function loadDocument(string $id): DocumentModel
    {
        $doc = DocumentModel::find($id);
        if (!$doc) {
            throw new HttpException(404, '文件不存在');
        }

        return $doc;
    }

    private function documentTypeFor(string $fileType): string
    {
        return match ($fileType) {
            'xls', 'xlsx', 'csv' => 'cell',
            'ppt', 'pptx' => 'slide',
            default => 'word',
        };
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
        $sites = Site::where('soft_delete', 0)->where('status', 'active');
        $manageableSiteIds = ActionAuthorizationService::documentManageableSiteIds();
        if ($manageableSiteIds !== null) {
            $sites->whereIn('id', $manageableSiteIds);
        }

        View::assign('categories', $categories);
        View::assign('templates', $templates);
        View::assign('departments', $departments);
        View::assign('sites', $sites->order('sort_order', 'asc')->select());
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
                'site_id' => 'require',
            ], [
                'doc_number.require' => '文件编号不能为空',
                'title.require' => '文件标题不能为空',
                'level.require' => '请选择文件层级',
                'site_id.require' => '请选择适用场所',
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
