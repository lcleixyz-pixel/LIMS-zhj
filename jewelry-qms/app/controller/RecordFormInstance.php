<?php
declare(strict_types=1);

namespace app\controller;

use app\BaseController;
use app\model\Department as DepartmentModel;
use app\model\Employee as EmployeeModel;
use app\model\RecordFormInstance as InstanceModel;
use app\model\RecordFormTemplate as TemplateModel;
use app\model\User;
use app\service\FileService;
use app\service\ActionAuthorizationService;
use app\service\NotificationService;
use app\service\PdfRenderService;
use app\service\RecordFormBatchReviewService;
use app\service\RecordFormCorrectionService;
use app\service\RecordFormCurrentPackageService;
use app\service\RecordFormCurrentStateService;
use app\service\RecordFormLayoutConfirmationService;
use app\service\RecordFormInstanceTitleService;
use app\service\RecordFormPrintService;
use app\service\RecordFormSchemaService;
use app\service\TrialModeService;
use InvalidArgumentException;
use RuntimeException;
use think\exception\HttpException;
use think\facade\Config;
use think\facade\Db;
use think\facade\Session;
use think\facade\View;

class RecordFormInstance extends BaseController
{
    public function index()
    {
        $query = InstanceModel::with('template');

        if ($keyword = trim((string)$this->request->param('keyword', ''))) {
            $query->where(function ($q) use ($keyword) {
                $q->where('doc_number', 'like', '%' . $keyword . '%')
                    ->whereOr('record_title', 'like', '%' . $keyword . '%');
            });
        }

        $items = $query->order('created', 'desc')->paginate(20);
        $this->decorateInstanceRows($items);

        View::assign('items', $items);
        View::assign('pages', $items->render());
        View::assign('filter', ['keyword' => $this->request->param('keyword', '')]);

        return View::fetch('record_form_instance/index');
    }

    private function decorateInstanceRows(iterable $items): void
    {
        $userIds = [];
        $recordIds = [];
        foreach ($items as $item) {
            $recordIds[] = (string)$item->id;
            foreach (['created_by', 'modified_by'] as $field) {
                $userId = trim((string)$item->{$field});
                if ($userId !== '') {
                    $userIds[] = $userId;
                }
            }
        }

        $userLabels = [];
        if ($userIds !== []) {
            try {
                $users = Db::name('users')
                    ->whereIn('id', array_values(array_unique($userIds)))
                    ->select()
                    ->toArray();
                foreach ($users as $user) {
                    $label = trim((string)($user['name'] ?? ''));
                    if ($label === '') {
                        $label = trim((string)($user['username'] ?? ''));
                    }
                    $userLabels[(string)$user['id']] = $label !== '' ? $label : (string)$user['id'];
                }
            } catch (\Throwable $exception) {
                $userLabels = [];
            }
        }

        $correctionSummaries = [];
        if ($recordIds !== [] && $this->recordCorrectionTableExists()) {
            try {
                $rows = Db::name('record_form_corrections')
                    ->field('record_id,COUNT(*) AS correction_count,MAX(registered_at) AS latest_correction_at')
                    ->whereIn('record_id', array_values(array_unique($recordIds)))
                    ->where('publish', 1)
                    ->where('soft_delete', 0)
                    ->group('record_id')
                    ->select()
                    ->toArray();
                foreach ($rows as $row) {
                    $correctionSummaries[(string)$row['record_id']] = [
                        'count' => (int)($row['correction_count'] ?? 0),
                        'latest_at' => (string)($row['latest_correction_at'] ?? ''),
                    ];
                }
            } catch (\Throwable $exception) {
                $correctionSummaries = [];
            }
        }

        foreach ($items as $item) {
            $status = (string)$item->status;
            $correctionSummary = $correctionSummaries[(string)$item->id] ?? ['count' => 0, 'latest_at' => ''];
            $correctionCount = (int)$correctionSummary['count'];
            $item->setAttr('pdf_token', $this->canExportPdf($item) ? $this->issuePdfActionToken((string)$item->id) : '');
            $item->setAttr('can_edit', $this->canEditRecord($item));
            $item->setAttr('status_label', self::recordStatusLabels()[$status] ?? $status);
            $item->setAttr('filler_label', $userLabels[(string)$item->created_by] ?? ((string)$item->created_by !== '' ? (string)$item->created_by : '未记录'));
            $item->setAttr('reviewer_label', in_array($status, ['locked', 'voided'], true)
                ? ($userLabels[(string)$item->modified_by] ?? ((string)$item->modified_by !== '' ? (string)$item->modified_by : '未记录'))
                : '待审核');
            $item->setAttr('correction_count', $correctionCount);
            $item->setAttr('latest_correction_at', (string)$correctionSummary['latest_at']);
            $item->setAttr('has_corrections', $correctionCount > 0);
            $currentPdf = $correctionCount > 0
                ? RecordFormCurrentStateService::findLatest((string)$item->id, $correctionCount)
                : null;
            $item->setAttr('has_current_pdf', $currentPdf !== null);
            $item->setAttr(
                'has_current_package',
                $correctionCount > 0 && trim((string)$item->generated_pdf_path) !== ''
            );
        }
    }

    private static function recordStatusLabels(): array
    {
        return [
            'draft' => '草稿',
            'generated' => '已形成PDF',
            'locked' => '已归档',
            'voided' => '已作废',
        ];
    }

    public function reviewDashboard()
    {
        $year = max(2000, min(2100, (int)$this->request->get('year', 2025)));
        $module = trim((string)$this->request->get('module', ''));
        $attention = trim((string)$this->request->get('attention', ''));
        $dashboard = RecordFormBatchReviewService::build($year);
        $rows = RecordFormBatchReviewService::filteredRows($dashboard['rows'], $module, $attention);
        $pdfAudit = $this->pdfAuditReport($year);
        $visualReview = $this->pdfVisualReviewReport($year);

        View::assign('year', $year);
        View::assign('dashboard', $dashboard);
        View::assign('summary', $dashboard['summary']);
        View::assign('rows', $rows);
        View::assign('moduleCounts', $dashboard['summary']['module_counts'] ?? []);
        View::assign('filter', ['module' => $module, 'attention' => $attention]);
        View::assign('pdfAudit', $pdfAudit);
        View::assign('visualReview', $visualReview);
        View::assign('returnUrl', $this->request->url());

        return View::fetch('record_form_instance/review_dashboard');
    }

    public function updateLayoutStatus()
    {
        $year = max(2000, min(2100, (int)$this->request->post('year', 2025)));
        $id = trim((string)$this->request->post('id', ''));
        $status = trim((string)$this->request->post('status', 'pending'));
        $note = trim((string)$this->request->post('note', ''));
        if ($id === '') {
            throw new HttpException(404, '记录实例不存在');
        }
        $record = InstanceModel::where('id', $id)->find();
        if (!$record) {
            throw new HttpException(404, '记录实例不存在');
        }

        RecordFormLayoutConfirmationService::set(
            $year,
            $id,
            $status,
            $note,
            (string)Session::get('user.name', Session::get('user.username', ''))
        );
        Session::flash('success', '版式确认状态已更新，可在年度运行确认页面查看确认结果。');

        $returnUrl = trim((string)$this->request->post('return_url', ''));
        if ($returnUrl === '' || !str_starts_with($returnUrl, '/record_form_instance/reviewDashboard')) {
            $returnUrl = '/record_form_instance/reviewDashboard?year=' . $year;
        }

        return redirect($returnUrl);
    }

    public function create()
    {
        if (trim((string)$this->request->param('template_id', '')) === '') {
            Session::flash('warning', '请先选择记录模板，再填写记录。可进入「记录填报 → 记录模板」选择。');

            return redirect('/record_form_template/index');
        }

        $template = $this->findTemplate();
        if (!$this->isTemplateFillable($template)) {
            Session::flash('warning', '当前模板未发布或未完成复核，暂不可填写。请联系质量负责人处理。');

            return redirect('/record_form_template/view?id=' . $template->id);
        }

        $schema = $this->decodeSchema($template);
        $recordYear = $this->selectedRecordYear();

        if ($this->request->isPost()) {
            $values = $this->collectValues($schema);
            $values = RecordFormSchemaService::enforceReadonly($schema, $values);
            $errors = RecordFormSchemaService::validateValues($schema, $values);
            if ($errors !== []) {
                $this->assignRecordFormEditorContext($template, $schema, $this->prepareFormValues($schema, $values), $errors);
                $this->assignRecordTitleSuggestionContext(
                    $template,
                    $recordYear,
                    trim((string)$this->request->post('record_title', ''))
                );

                return View::fetch('record_form_instance/create');
            }

            $snapshot = $this->snapshotTemplate($template);
            $postedTitle = trim((string)$this->request->post('record_title', ''));
            $postedSuggestion = trim((string)$this->request->post('suggested_record_title', ''));
            $currentSuggestion = RecordFormInstanceTitleService::suggest($template, $recordYear);
            $recordTitle = $postedTitle;
            if ($recordTitle === '' || ($postedSuggestion !== '' && $recordTitle === $postedSuggestion)) {
                $recordTitle = (string)$currentSuggestion['record_title'];
            }

            $isSimulation = TrialModeService::isSimulationTemplate($template);
            if ($isSimulation) {
                $recordTitle = TrialModeService::simulationNumber($recordTitle);
            }
            $record = InstanceModel::create([
                'id' => qms_uuid(),
                'template_id' => $template->id,
                'template_name' => $snapshot['name'],
                'template_module' => $snapshot['module'],
                'template_version' => $snapshot['version'],
                'template_print_template_key' => $snapshot['print_template_key'],
                'template_field_schema' => $snapshot['field_schema'],
                'doc_number' => $isSimulation
                    ? TrialModeService::simulationNumber((string)$template->doc_number)
                    : $template->doc_number,
                'record_title' => $recordTitle,
                'field_values' => $this->encodeValues($values),
                'status' => 'draft',
                'is_simulation' => $isSimulation ? 1 : 0,
                'trial_batch' => $isSimulation ? TrialModeService::trialBatch() : null,
            ]);
            Session::flash('success', '记录草稿已保存。请继续填写或确认无误后生成 PDF。');

            return redirect('/record_form_instance/view?id=' . $record->id);
        }

        $this->assignRecordFormEditorContext(
            $template,
            $schema,
            $this->prepareFormValues($schema, $this->defaultValues($schema)),
            []
        );
        $this->assignRecordTitleSuggestionContext($template, $recordYear);

        return View::fetch('record_form_instance/create');
    }

    private function pdfAuditReport(int $year): array
    {
        $jsonPath = root_path() . 'runtime' . DIRECTORY_SEPARATOR . 'record-form-batches' . DIRECTORY_SEPARATOR
            . (string)$year . DIRECTORY_SEPARATOR . 'pdf-layout-audit' . DIRECTORY_SEPARATOR . 'report.json';
        $markdownPath = root_path() . 'runtime' . DIRECTORY_SEPARATOR . 'record-form-batches' . DIRECTORY_SEPARATOR
            . (string)$year . DIRECTORY_SEPARATOR . 'pdf-layout-audit' . DIRECTORY_SEPARATOR . 'report.md';
        $summary = [];
        if (is_file($jsonPath)) {
            $decoded = json_decode((string)file_get_contents($jsonPath), true);
            if (is_array($decoded)) {
                $summary = (array)($decoded['summary'] ?? []);
            }
        }

        return [
            'exists' => is_file($markdownPath),
            'markdown_path' => is_file($markdownPath) ? str_replace(root_path(), '', $markdownPath) : '',
            'summary' => $summary,
        ];
    }

    private function pdfVisualReviewReport(int $year): array
    {
        $jsonPath = root_path() . 'runtime' . DIRECTORY_SEPARATOR . 'record-form-batches' . DIRECTORY_SEPARATOR
            . (string)$year . DIRECTORY_SEPARATOR . 'pdf-visual-review' . DIRECTORY_SEPARATOR . 'report.json';
        $htmlPath = root_path() . 'runtime' . DIRECTORY_SEPARATOR . 'record-form-batches' . DIRECTORY_SEPARATOR
            . (string)$year . DIRECTORY_SEPARATOR . 'pdf-visual-review' . DIRECTORY_SEPARATOR . 'index.html';
        $summary = [];
        if (is_file($jsonPath)) {
            $decoded = json_decode((string)file_get_contents($jsonPath), true);
            if (is_array($decoded)) {
                $summary = (array)($decoded['summary'] ?? []);
            }
        }

        return [
            'exists' => is_file($htmlPath),
            'html_path' => is_file($htmlPath) ? str_replace(root_path(), '', $htmlPath) : '',
            'html_url' => is_file($htmlPath)
                ? '/record_form_instance/reviewArtifact?year=' . $year . '&batch=pdf-visual-review&file=index.html'
                : '',
            'summary' => $summary,
        ];
    }

    public function edit()
    {
        $record = $this->findInstance();
        if (!$this->canEditRecord($record)) {
            Session::flash('warning', '记录已锁定，不能直接编辑。如需更正，请点击「申请更正」按钮或联系质量负责人。');

            return redirect('/record_form_instance/view?id=' . $record->id);
        }

        $template = $this->templateForRecord($record);
        $schema = $this->decodeSchema($template);

        if ($this->request->isPost()) {
            $values = $this->collectValues($schema);
            $values = RecordFormSchemaService::enforceReadonly($schema, $values);
            $errors = RecordFormSchemaService::validateValues($schema, $values);
            if ($errors === []) {
                $recordTitle = trim((string)$this->request->post('record_title', $record->record_title));
                if ((bool)$record->is_simulation) {
                    $recordTitle = TrialModeService::simulationNumber($recordTitle);
                }
                $record->save([
                    'record_title' => $recordTitle,
                    'field_values' => $this->encodeValues($values),
                    'status' => 'draft',
                    'generated_html_path' => null,
                    'generated_pdf_path' => null,
                    'generated_pdf_name' => null,
                ]);
                Session::flash('success', '记录已保存，当前仍为草稿。确认无误后可生成 PDF。');

                return redirect('/record_form_instance/view?id=' . $record->id);
            }

            $preparedValues = $this->prepareFormValues($schema, $values);
            $preparedErrors = $errors;
        } else {
            $preparedValues = $this->prepareFormValues($schema, $this->decodeValues($record->field_values));
            $preparedErrors = [];
        }

        View::assign('record', $record);
        $this->assignRecordFormEditorContext($template, $schema, $preparedValues, $preparedErrors);

        return View::fetch('record_form_instance/edit');
    }

    public function view()
    {
        $record = $this->findInstance();
        $template = $this->templateForRecord($record);
        $schema = $this->decodeSchema($template);
        $values = $this->decodeValues($record->field_values);
        $recordCorrections = $this->recordCorrectionsFor((string)$record->id);
        View::assign('record', $record);
        View::assign('template', $template);
        View::assign('schema', $schema);
        View::assign('values', $values);
        View::assign(
            'annotatedSchema',
            RecordFormCorrectionService::projectForDisplay($schema, $values, $recordCorrections)
        );
        View::assign('correctionTargets', array_values(RecordFormCorrectionService::targets($schema, $values)));
        View::assign(
            'correctionTargetsJson',
            json_encode(
                array_values(RecordFormCorrectionService::targets($schema, $values)),
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                    | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
            )
        );
        View::assign('canExportPdf', $this->canExportPdf($record));
        View::assign('canEdit', $this->canEditRecord($record));
        View::assign('pdfToken', $this->canExportPdf($record) ? $this->issuePdfActionToken((string)$record->id) : '');
        View::assign('previewPdfFiles', $this->previewPdfFiles((string)$record->id));
        View::assign('correctionRequests', $this->correctionRequestsFor((string)$record->id));
        View::assign('correctionDecisions', $this->correctionDecisionsFor((string)$record->id));
        View::assign('recordCorrections', $recordCorrections);
        View::assign(
            'currentPdf',
            $recordCorrections !== []
                ? RecordFormCurrentStateService::findLatest((string)$record->id, count($recordCorrections))
                : null
        );
        View::assign('latestCorrectionAt', $recordCorrections !== []
            ? (string)($recordCorrections[array_key_last($recordCorrections)]['registered_at'] ?? '')
            : '');
        View::assign('approvedCorrectionRequests', $this->approvedCorrectionRequestsFor((string)$record->id));
        View::assign('correctionTypeLabels', self::correctionTypeLabels());
        View::assign(
            'canDecideCorrection',
            ActionAuthorizationService::allows('record_form_instance', 'decideCorrection', $record)
        );
        View::assign(
            'canRegisterCorrection',
            ActionAuthorizationService::allows('record_form_instance', 'registerCorrection', $record)
        );

        return View::fetch('record_form_instance/view');
    }

    public function requestCorrection()
    {
        $record = $this->findInstance();
        if (!$this->request->isPost()) {
            Session::flash('warning', '请在记录详情页填写更正原因后提交申请。');

            return redirect('/record_form_instance/view?id=' . $record->id);
        }

        if (!$this->recordCorrectionRequestTableExists()) {
            Session::flash('error', '字段级更正申请表尚未完成数据库初始化，请联系系统管理员。');

            return redirect('/record_form_instance/view?id=' . $record->id);
        }

        $reason = trim((string)$this->request->post('reason', ''));
        if ($reason === '') {
            Session::flash('error', '请填写更正原因。');

            return redirect('/record_form_instance/view?id=' . $record->id);
        }

        try {
            $template = $this->templateForRecord($record);
            $prepared = RecordFormCorrectionService::prepare(
                $this->decodeSchema($template),
                $this->decodeValues($record->field_values),
                [
                    'correction_type' => $this->request->post('correction_type', 'supplement'),
                    'field_path' => $this->request->post('field_path', ''),
                    'corrected_value' => $this->request->post('corrected_value', ''),
                    'row_values' => $this->request->post('row_values', []),
                ]
            );
        } catch (InvalidArgumentException $exception) {
            Session::flash('error', $exception->getMessage());

            return redirect('/record_form_instance/view?id=' . $record->id);
        }

        $qualityManagerIds = $this->qualityManagerUserIds();
        $recipientIds = array_values(array_unique(array_filter(array_merge(
            $qualityManagerIds,
            $this->recordCorrectionApproverUserIds()
        ))));
        $requestId = qms_uuid();
        $now = date('Y-m-d H:i:s');
        $userId = (string)Session::get('user.id', '');

        Db::transaction(function () use ($record, $prepared, $reason, $recipientIds, $requestId, $now, $userId): void {
            Db::name('record_form_correction_requests')->insert([
                'id' => $requestId,
                'company_id' => (string)Config::get('qms.company_id'),
                'record_id' => (string)$record->id,
                'status' => 'pending',
                'correction_type' => (string)$prepared['correction_type'],
                'target_kind' => (string)$prepared['target_kind'],
                'field_path' => (string)$prepared['field_path'],
                'field_key' => (string)$prepared['field_key'],
                'field_label' => (string)$prepared['field_label'],
                'row_index' => $prepared['row_index'],
                'column_key' => (string)$prepared['column_key'],
                'column_label' => (string)$prepared['column_label'],
                'original_content' => (string)$prepared['original_content'],
                'corrected_content' => (string)$prepared['corrected_content'],
                'row_payload_json' => (string)$prepared['row_payload_json'],
                'reason' => $reason,
                'requested_by' => $userId,
                'requested_at' => $now,
                'publish' => 1,
                'soft_delete' => 0,
                'created' => $now,
                'modified' => $now,
                'created_by' => $userId,
                'modified_by' => $userId,
            ]);

            if ($recipientIds !== []) {
                NotificationService::notifyUsers(
                    '记录更正申请',
                    "记录「{$record->record_title}」申请字段级更正；位置：{$prepared['field_label']}；原值：{$prepared['original_content']}；拟更正值：{$prepared['corrected_content']}；原因：{$reason}；申请ID：{$requestId}",
                    'record_form_instance',
                    $recipientIds,
                    'record_form_instance',
                    'view',
                    (string)$record->id,
                    null,
                    'record_correction_request:' . $requestId
                );
            }
        });

        Session::flash('success', '字段级更正申请已提交。审核人将核对具体字段、原值和拟更正值；批准后系统会自动追加到更正记录链。');

        return redirect('/record_form_instance/view?id=' . $record->id);
    }

    public function registerCorrection()
    {
        $record = $this->findInstance();
        if (!$this->request->isPost()) {
            Session::flash('warning', '请在记录详情页登记更正内容。');

            return redirect('/record_form_instance/view?id=' . $record->id);
        }

        if (!$this->recordCorrectionTableExists()) {
            Session::flash('error', '记录更正追加层尚未完成数据库初始化，请联系系统管理员。');

            return redirect('/record_form_instance/view?id=' . $record->id);
        }

        if (!ActionAuthorizationService::allows('record_form_instance', 'registerCorrection', $record)) {
            Session::flash('error', '当前账号无权登记更正内容，请使用质量负责人、文控/技术负责人或批准人账号。');

            return redirect('/record_form_instance/view?id=' . $record->id);
        }

        $requestId = trim((string)$this->request->post('correction_request_id', ''));
        if ($requestId === '') {
            Session::flash('error', '请选择已批准的更正申请。');

            return redirect('/record_form_instance/view?id=' . $record->id);
        }

        $approvedRequest = $this->approvedCorrectionRequestForRegistration((string)$record->id, $requestId);
        if ($approvedRequest === []) {
            Session::flash('error', '所选更正申请尚未批准，不能登记更正内容。');

            return redirect('/record_form_instance/view?id=' . $record->id);
        }

        $type = trim((string)$this->request->post('correction_type', 'supplement'));
        $typeLabels = self::correctionTypeLabels();
        if (!isset($typeLabels[$type])) {
            Session::flash('error', '请选择有效的更正类型。');

            return redirect('/record_form_instance/view?id=' . $record->id);
        }

        $originalContent = trim((string)$this->request->post('original_content', ''));
        $correctedContent = trim((string)$this->request->post('corrected_content', ''));
        $reason = trim((string)$this->request->post('correction_reason', ''));
        if ($correctedContent === '') {
            Session::flash('error', '请填写更正后内容或补充内容。');

            return redirect('/record_form_instance/view?id=' . $record->id);
        }
        if ($type !== 'supplement' && $originalContent === '') {
            Session::flash('error', '修改或作废标注需要填写原内容，确保原始数据保留可追溯。');

            return redirect('/record_form_instance/view?id=' . $record->id);
        }
        if ($reason === '') {
            $reason = (string)($approvedRequest['request_reason'] ?? '按已批准更正申请登记');
        }

        $now = date('Y-m-d H:i:s');
        Db::name('record_form_corrections')->insert([
            'id' => qms_uuid(),
            'company_id' => (string)Config::get('qms.company_id'),
            'record_id' => (string)$record->id,
            'correction_request_id' => $requestId,
            'decision_notification_id' => (string)($approvedRequest['decision_notification_id'] ?? ''),
            'correction_type' => $type,
            'original_content' => $originalContent,
            'corrected_content' => $correctedContent,
            'correction_reason' => $reason,
            'registered_by' => (string)Session::get('user.id', ''),
            'registered_at' => $now,
            'approved_by' => (string)($approvedRequest['approved_by'] ?? ''),
            'approved_at' => (string)($approvedRequest['approved_at'] ?? ''),
            'publish' => 1,
            'soft_delete' => 0,
            'created' => $now,
            'modified' => $now,
            'created_by' => (string)Session::get('user.id', ''),
            'modified_by' => (string)Session::get('user.id', ''),
        ]);

        $currentPdfReady = false;
        try {
            $this->refreshCurrentStatePdf($record);
            $currentPdfReady = true;
        } catch (\Throwable $exception) {
            $currentPdfReady = false;
        }
        $success = '更正内容已追加登记。原记录和原 PDF 未被修改。';
        $success .= $currentPdfReady
            ? '当前状态 PDF 已同步生成。'
            : '当前状态 PDF 暂未生成；下次下载时系统会自动补生成。';
        Session::flash('success', $success);

        return redirect('/record_form_instance/view?id=' . $record->id);
    }

    public function decideCorrection()
    {
        $record = $this->findInstance();
        if (!$this->request->isPost()) {
            Session::flash('warning', '请在记录详情页处理更正申请。');

            return redirect('/record_form_instance/view?id=' . $record->id);
        }

        if (!ActionAuthorizationService::allows('record_form_instance', 'decideCorrection', $record)) {
            Session::flash('error', '当前账号无权处理更正申请，请使用质量负责人或批准人账号。');

            return redirect('/record_form_instance/view?id=' . $record->id);
        }

        $decision = trim((string)$this->request->post('decision', ''));
        $decisionLabels = [
            'approved' => '批准更正',
            'rejected' => '驳回申请',
        ];
        if (!isset($decisionLabels[$decision])) {
            Session::flash('error', '请选择批准更正或驳回申请。');

            return redirect('/record_form_instance/view?id=' . $record->id);
        }

        $correctionRequestId = trim((string)$this->request->post('correction_request_id', ''));
        if ($correctionRequestId === '') {
            Session::flash('error', '请选择要处理的更正申请。');

            return redirect('/record_form_instance/view?id=' . $record->id);
        }
        $correctionRequest = $this->correctionRequestForDecision((string)$record->id, $correctionRequestId);
        if ($correctionRequest === []) {
            Session::flash('error', '所选更正申请不存在或不属于当前记录，请刷新后重试。');

            return redirect('/record_form_instance/view?id=' . $record->id);
        }

        $comment = trim((string)$this->request->post('comment', ''));
        if ($comment === '') {
            $comment = '无补充意见';
        }

        $handlerLabel = trim((string)Session::get('user.name', Session::get('user.username', '')));
        if ($handlerLabel === '') {
            $handlerLabel = '当前处理人';
        }
        $handlerId = (string)Session::get('user.id', '');
        $decidedAt = date('Y-m-d H:i:s');

        $currentPdfReady = null;
        if ((bool)($correctionRequest['is_structured'] ?? false)) {
            $applied = Db::transaction(function () use (
                $record,
                $correctionRequestId,
                $decision,
                $comment,
                $handlerId,
                $decidedAt
            ): bool {
                $requestRow = Db::name('record_form_correction_requests')
                    ->where('id', $correctionRequestId)
                    ->where('record_id', (string)$record->id)
                    ->where('publish', 1)
                    ->where('soft_delete', 0)
                    ->lock(true)
                    ->find();
                if (!$requestRow || (string)($requestRow['status'] ?? '') !== 'pending') {
                    return false;
                }

                Db::name('record_form_correction_requests')
                    ->where('id', $correctionRequestId)
                    ->where('status', 'pending')
                    ->update([
                        'status' => $decision,
                        'decided_by' => $handlerId,
                        'decided_at' => $decidedAt,
                        'decision_comment' => $comment,
                        'modified' => $decidedAt,
                        'modified_by' => $handlerId,
                    ]);

                if ($decision === 'approved') {
                    $this->appendApprovedCorrection($record, $requestRow, $handlerId, $decidedAt);
                }

                return true;
            });
            if (!$applied) {
                Session::flash('error', '该更正申请已被处理，请刷新页面查看最新结果。');

                return redirect('/record_form_instance/view?id=' . $record->id);
            }
            if ($decision === 'approved') {
                try {
                    $this->refreshCurrentStatePdf($record);
                    $currentPdfReady = true;
                } catch (\Throwable $exception) {
                    $currentPdfReady = false;
                }
            }
        }

        $recipientIds = array_values(array_unique(array_filter(array_merge(
            $this->qualityManagerUserIds(),
            $this->recordCorrectionRequesterUserIds((string)$record->id),
            [(string)Session::get('user.id', '')]
        ))));

        NotificationService::notifyUsers(
            '记录更正申请处理结果',
            "记录「{$record->record_title}」更正申请处理结果：{$decisionLabels[$decision]}；对应申请：{$correctionRequest['label']}；申请ID：{$correctionRequest['id']}；处理意见：{$comment}；处理人：{$handlerLabel}",
            'record_form_instance',
            $recipientIds,
            'record_form_instance',
            'view',
            (string)$record->id
        );

        $success = '更正申请已处理：' . $decisionLabels[$decision] . '。';
        if ((bool)($correctionRequest['is_structured'] ?? false) && $decision === 'approved') {
            $success .= '批准后已自动追加到更正记录链，无需再次抄写登记。';
            $success .= $currentPdfReady
                ? '当前状态 PDF 已同步生成。'
                : '当前状态 PDF 暂未生成；下次下载时系统会自动补生成。';
        } else {
            $success .= '处理结果已留存在记录详情页。';
        }
        Session::flash('success', $success);

        return redirect('/record_form_instance/view?id=' . $record->id);
    }

    private function appendApprovedCorrection(
        InstanceModel $record,
        array $request,
        string $approvedBy,
        string $approvedAt
    ): void {
        $requestId = (string)($request['id'] ?? '');
        if ($requestId === '') {
            throw new RuntimeException('字段级更正申请缺少唯一标识');
        }
        if (Db::name('record_form_corrections')
            ->where('record_id', (string)$record->id)
            ->where('correction_request_id', $requestId)
            ->where('soft_delete', 0)
            ->count() > 0) {
            return;
        }

        Db::name('record_form_corrections')->insert([
            'id' => qms_uuid(),
            'company_id' => (string)Config::get('qms.company_id'),
            'record_id' => (string)$record->id,
            'correction_request_id' => $requestId,
            'correction_type' => (string)($request['correction_type'] ?? 'supplement'),
            'target_kind' => (string)($request['target_kind'] ?? 'field_value'),
            'field_path' => (string)($request['field_path'] ?? ''),
            'field_key' => (string)($request['field_key'] ?? ''),
            'field_label' => (string)($request['field_label'] ?? ''),
            'row_index' => $request['row_index'] ?? null,
            'column_key' => (string)($request['column_key'] ?? ''),
            'column_label' => (string)($request['column_label'] ?? ''),
            'row_payload_json' => (string)($request['row_payload_json'] ?? ''),
            'original_content' => (string)($request['original_content'] ?? ''),
            'corrected_content' => (string)($request['corrected_content'] ?? ''),
            'correction_reason' => (string)($request['reason'] ?? ''),
            'registered_by' => (string)($request['requested_by'] ?? ''),
            'registered_at' => $approvedAt,
            'approved_by' => $approvedBy,
            'approved_at' => $approvedAt,
            'publish' => 1,
            'soft_delete' => 0,
            'created' => $approvedAt,
            'modified' => $approvedAt,
            'created_by' => (string)($request['requested_by'] ?? ''),
            'modified_by' => $approvedBy,
        ]);
    }

    private function correctionRequestsFor(string $recordId): array
    {
        if ($recordId === '') {
            return [];
        }
        if ($this->recordCorrectionRequestTableExists()) {
            $rows = Db::name('record_form_correction_requests')
                ->alias('r')
                ->leftJoin('users u', 'u.id = r.requested_by')
                ->field('r.*,u.name AS requester_name,u.username AS requester_username')
                ->where('r.record_id', $recordId)
                ->where('r.status', 'pending')
                ->where('r.publish', 1)
                ->where('r.soft_delete', 0)
                ->order('r.requested_at', 'desc')
                ->limit(10)
                ->select()
                ->toArray();

            return array_map(function (array $row): array {
                $requester = trim((string)($row['requester_name'] ?? ''));
                if ($requester === '') {
                    $requester = trim((string)($row['requester_username'] ?? ''));
                }
                $id = (string)($row['id'] ?? '');
                $created = (string)($row['requested_at'] ?? '');
                $reason = trim((string)($row['reason'] ?? ''));
                $fieldLabel = trim((string)($row['field_label'] ?? ''));

                return [
                    'id' => $id,
                    'reason' => $reason !== '' ? $reason : '未记录原因',
                    'created' => $created,
                    'recipient_count' => count(array_unique(array_merge(
                        $this->qualityManagerUserIds(),
                        $this->recordCorrectionApproverUserIds()
                    ))),
                    'short_id' => self::shortId($id),
                    'field_label' => $fieldLabel !== '' ? $fieldLabel : '未记录更正位置',
                    'original_content' => (string)($row['original_content'] ?? ''),
                    'corrected_content' => (string)($row['corrected_content'] ?? ''),
                    'type_label' => self::correctionTypeLabels()[(string)($row['correction_type'] ?? '')] ?? '更正',
                    'requester' => $requester !== '' ? $requester : '未记录申请人',
                    'option_label' => $created . '｜' . ($fieldLabel !== '' ? $fieldLabel : '未记录更正位置')
                        . '｜' . self::shortId($id),
                    'is_structured' => true,
                ];
            }, $rows);
        }

        $rows = Db::name('notifications')
            ->alias('n')
            ->leftJoin('notification_users nu', 'nu.notification_id = n.id')
            ->field('n.id,n.message,n.created,COUNT(nu.user_id) AS recipient_count')
            ->where('n.title', '记录更正申请')
            ->where('n.link_controller', 'record_form_instance')
            ->where('n.link_action', 'view')
            ->where('n.link_id', $recordId)
            ->group('n.id,n.message,n.created')
            ->order('n.created', 'desc')
            ->limit(5)
            ->select()
            ->toArray();

        return array_map(static function (array $row): array {
            $message = trim((string)($row['message'] ?? ''));
            $reason = $message;
            $marker = '原因：';
            $position = mb_strpos($message, $marker);
            if ($position !== false) {
                $reason = mb_substr($message, $position + mb_strlen($marker));
            }

            return [
                'id' => (string)($row['id'] ?? ''),
                'reason' => $reason !== '' ? $reason : '未记录原因',
                'created' => (string)($row['created'] ?? ''),
                'recipient_count' => (int)($row['recipient_count'] ?? 0),
                'short_id' => self::shortId((string)($row['id'] ?? '')),
                'option_label' => self::correctionRequestLabel(
                    (string)($row['created'] ?? ''),
                    $reason !== '' ? $reason : '未记录原因',
                    (string)($row['id'] ?? '')
                ),
                'field_label' => '历史自由文本申请',
                'original_content' => '',
                'corrected_content' => '',
                'type_label' => '历史流程',
                'requester' => '未记录',
                'is_structured' => false,
            ];
        }, $rows);
    }

    private function correctionRequestForDecision(string $recordId, string $requestId): array
    {
        if ($recordId === '' || $requestId === '') {
            return [];
        }

        if ($this->recordCorrectionRequestTableExists()) {
            $structured = Db::name('record_form_correction_requests')
                ->where('id', $requestId)
                ->where('record_id', $recordId)
                ->where('status', 'pending')
                ->where('publish', 1)
                ->where('soft_delete', 0)
                ->find();
            if ($structured) {
                return [
                    'id' => (string)($structured['id'] ?? ''),
                    'created' => (string)($structured['requested_at'] ?? ''),
                    'reason' => (string)($structured['reason'] ?? ''),
                    'label' => (string)($structured['field_label'] ?? '未记录更正位置'),
                    'field_label' => (string)($structured['field_label'] ?? '未记录更正位置'),
                    'original_content' => (string)($structured['original_content'] ?? ''),
                    'corrected_content' => (string)($structured['corrected_content'] ?? ''),
                    'is_structured' => true,
                ];
            }
        }

        $row = Db::name('notifications')
            ->field('id,message,created')
            ->where('id', $requestId)
            ->where('title', '记录更正申请')
            ->where('link_controller', 'record_form_instance')
            ->where('link_action', 'view')
            ->where('link_id', $recordId)
            ->where('publish', 1)
            ->where('soft_delete', 0)
            ->find();
        if (!$row) {
            return [];
        }

        $message = trim((string)($row['message'] ?? ''));
        $reason = $message;
        $marker = '原因：';
        $position = mb_strpos($message, $marker);
        if ($position !== false) {
            $reason = mb_substr($message, $position + mb_strlen($marker));
        }
        if (trim($reason) === '') {
            $reason = '未记录原因';
        }

        return [
            'id' => (string)($row['id'] ?? ''),
            'created' => (string)($row['created'] ?? ''),
            'reason' => $reason,
            'label' => self::correctionRequestLabel(
                (string)($row['created'] ?? ''),
                $reason,
                (string)($row['id'] ?? '')
            ),
            'field_label' => '历史自由文本申请',
            'original_content' => '',
            'corrected_content' => '',
            'is_structured' => false,
        ];
    }

    private function correctionDecisionsFor(string $recordId): array
    {
        if ($recordId === '') {
            return [];
        }

        $structuredDecisions = [];
        if ($this->recordCorrectionRequestTableExists()) {
            $structuredRows = Db::name('record_form_correction_requests')
                ->alias('r')
                ->leftJoin('users u', 'u.id = r.decided_by')
                ->field('r.*,u.name AS handler_name,u.username AS handler_username')
                ->where('r.record_id', $recordId)
                ->whereIn('r.status', ['approved', 'rejected'])
                ->where('r.publish', 1)
                ->where('r.soft_delete', 0)
                ->order('r.decided_at', 'desc')
                ->limit(10)
                ->select()
                ->toArray();
            $structuredDecisions = array_map(static function (array $row): array {
                $handler = trim((string)($row['handler_name'] ?? ''));
                if ($handler === '') {
                    $handler = trim((string)($row['handler_username'] ?? ''));
                }
                $approved = (string)($row['status'] ?? '') === 'approved';
                $requestId = (string)($row['id'] ?? '');

                return [
                    'id' => $requestId,
                    'decision' => $approved ? '批准更正' : '驳回申请',
                    'is_approved' => $approved,
                    'is_structured' => true,
                    'request_id' => $requestId,
                    'decision_notification_id' => '',
                    'request_label' => (string)($row['field_label'] ?? '未记录更正位置'),
                    'request_short_id' => self::shortId($requestId),
                    'field_label' => (string)($row['field_label'] ?? '未记录更正位置'),
                    'original_content' => (string)($row['original_content'] ?? ''),
                    'corrected_content' => (string)($row['corrected_content'] ?? ''),
                    'comment' => (string)($row['decision_comment'] ?? '无补充意见'),
                    'handler' => $handler !== '' ? $handler : '未记录处理人',
                    'approved_by' => (string)($row['decided_by'] ?? ''),
                    'approved_at' => (string)($row['decided_at'] ?? ''),
                    'created' => (string)($row['decided_at'] ?? ''),
                ];
            }, $structuredRows);
        }

        $rows = Db::name('notifications')
            ->alias('n')
            ->leftJoin('users u', 'u.id = n.created_by')
            ->field('n.id,n.message,n.created,u.name AS handler_name,u.username AS handler_username,n.created_by')
            ->where('n.title', '记录更正申请处理结果')
            ->where('n.link_controller', 'record_form_instance')
            ->where('n.link_action', 'view')
            ->where('n.link_id', $recordId)
            ->order('n.created', 'desc')
            ->limit(5)
            ->select()
            ->toArray();

        $legacyDecisions = array_map(static function (array $row): array {
            $message = trim((string)($row['message'] ?? ''));
            $decision = self::messageSegment($message, '处理结果：', '；');
            $requestLabel = self::messageSegment($message, '对应申请：', '；');
            $requestId = self::messageSegment($message, '申请ID：', '；');
            $comment = self::messageSegment($message, '处理意见：', '；');
            $handler = trim((string)($row['handler_name'] ?? ''));
            if ($handler === '') {
                $handler = trim((string)($row['handler_username'] ?? ''));
            }
            if ($handler === '') {
                $handler = self::messageSegment($message, '处理人：', '；');
            }

            return [
                'id' => (string)($row['id'] ?? ''),
                'decision' => $decision !== '' ? $decision : '已处理',
                'is_approved' => $decision === '批准更正',
                'is_structured' => false,
                'request_id' => $requestId,
                'decision_notification_id' => (string)($row['id'] ?? ''),
                'request_label' => $requestLabel !== '' ? $requestLabel : '未记录对应申请',
                'request_short_id' => self::shortId($requestId),
                'field_label' => '历史自由文本申请',
                'original_content' => '',
                'corrected_content' => '',
                'comment' => $comment !== '' ? $comment : '无补充意见',
                'handler' => $handler !== '' ? $handler : '未记录处理人',
                'approved_by' => (string)($row['created_by'] ?? ''),
                'approved_at' => (string)($row['created'] ?? ''),
                'created' => (string)($row['created'] ?? ''),
            ];
        }, $rows);

        return array_slice(
            RecordFormCorrectionService::mergeDecisionRows($structuredDecisions, $legacyDecisions),
            0,
            15
        );
    }

    private function approvedCorrectionRequestsFor(string $recordId): array
    {
        $requests = [];
        foreach ($this->correctionDecisionsFor($recordId) as $decision) {
            if (!($decision['is_approved'] ?? false)) {
                continue;
            }
            if ((bool)($decision['is_structured'] ?? false)) {
                continue;
            }
            $requestId = trim((string)($decision['request_id'] ?? ''));
            if ($requestId === '' || isset($requests[$requestId])) {
                continue;
            }
            $request = $this->correctionRequestForDecision($recordId, $requestId);
            if ($request === []) {
                continue;
            }

            $requests[$requestId] = [
                'id' => $requestId,
                'label' => (string)$request['label'],
                'request_reason' => (string)$request['reason'],
                'decision_notification_id' => (string)($decision['decision_notification_id'] ?? ''),
                'approved_by' => (string)($decision['approved_by'] ?? ''),
                'approved_at' => (string)($decision['approved_at'] ?? ''),
                'approved_label' => (string)($decision['handler'] ?? '未记录批准人') . '｜' . (string)($decision['approved_at'] ?? ''),
            ];
        }

        return array_values($requests);
    }

    private function approvedCorrectionRequestForRegistration(string $recordId, string $requestId): array
    {
        foreach ($this->approvedCorrectionRequestsFor($recordId) as $request) {
            if ((string)($request['id'] ?? '') === $requestId) {
                return $request;
            }
        }

        return [];
    }

    private function recordCorrectionsFor(string $recordId): array
    {
        if ($recordId === '' || !$this->recordCorrectionTableExists()) {
            return [];
        }

        $rows = Db::name('record_form_corrections')
            ->alias('c')
            ->leftJoin('users ru', 'ru.id = c.registered_by')
            ->leftJoin('users au', 'au.id = c.approved_by')
            ->field('c.*,ru.name AS registered_name,ru.username AS registered_username,au.name AS approved_name,au.username AS approved_username')
            ->where('c.record_id', $recordId)
            ->where('c.publish', 1)
            ->where('c.soft_delete', 0)
            ->order('c.registered_at', 'asc')
            ->select()
            ->toArray();
        $typeLabels = self::correctionTypeLabels();

        return array_map(static function (array $row) use ($typeLabels): array {
            $registeredBy = trim((string)($row['registered_name'] ?? ''));
            if ($registeredBy === '') {
                $registeredBy = trim((string)($row['registered_username'] ?? ''));
            }
            $approvedBy = trim((string)($row['approved_name'] ?? ''));
            if ($approvedBy === '') {
                $approvedBy = trim((string)($row['approved_username'] ?? ''));
            }
            $requestId = (string)($row['correction_request_id'] ?? '');

            return [
                'id' => (string)($row['id'] ?? ''),
                'type' => (string)($row['correction_type'] ?? ''),
                'type_label' => $typeLabels[(string)($row['correction_type'] ?? '')] ?? (string)($row['correction_type'] ?? '更正'),
                'target_kind' => (string)($row['target_kind'] ?? 'legacy_note'),
                'field_path' => (string)($row['field_path'] ?? ''),
                'field_label' => trim((string)($row['field_label'] ?? '')) !== ''
                    ? (string)$row['field_label']
                    : '整表补充说明',
                'row_payload_json' => (string)($row['row_payload_json'] ?? ''),
                'request_id' => $requestId,
                'request_short_id' => self::shortId($requestId),
                'original_content' => (string)($row['original_content'] ?? ''),
                'corrected_content' => (string)($row['corrected_content'] ?? ''),
                'correction_reason' => (string)($row['correction_reason'] ?? ''),
                'registered_by' => $registeredBy !== '' ? $registeredBy : '未记录',
                'registered_at' => (string)($row['registered_at'] ?? ''),
                'approved_by' => $approvedBy !== '' ? $approvedBy : '未记录',
                'approved_at' => (string)($row['approved_at'] ?? ''),
            ];
        }, $rows);
    }

    private static function correctionTypeLabels(): array
    {
        return [
            'supplement' => '补充内容',
            'amendment' => '修改内容（保留原值）',
            'void_mark' => '作废标注（不删除原内容）',
        ];
    }

    private static function messageSegment(string $message, string $startMarker, string $endMarker): string
    {
        $start = mb_strpos($message, $startMarker);
        if ($start === false) {
            return '';
        }

        $tail = mb_substr($message, $start + mb_strlen($startMarker));
        $end = mb_strpos($tail, $endMarker);
        if ($end !== false) {
            $tail = mb_substr($tail, 0, $end);
        }

        return trim($tail);
    }

    private static function correctionRequestLabel(string $created, string $reason, string $id): string
    {
        $created = trim($created) !== '' ? trim($created) : '未记录时间';
        $reason = trim($reason) !== '' ? trim($reason) : '未记录原因';
        if (mb_strlen($reason) > 60) {
            $reason = mb_substr($reason, 0, 60) . '…';
        }

        $shortId = self::shortId($id);
        $suffix = $shortId !== '' ? '（申请编号 ' . $shortId . '）' : '';

        return $created . '｜' . $reason . $suffix;
    }

    private static function shortId(string $id): string
    {
        $id = trim($id);
        if ($id === '') {
            return '';
        }

        return substr($id, 0, 8);
    }

    private function recordCorrectionTableExists(): bool
    {
        static $exists = null;
        if ($exists !== null) {
            return $exists;
        }

        try {
            $rows = Db::query(
                'SELECT COUNT(*) AS c FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?',
                ['record_form_corrections']
            );
            $exists = (int)($rows[0]['c'] ?? 0) > 0;

            return $exists;
        } catch (\Throwable $exception) {
            $exists = false;

            return false;
        }
    }

    private function recordCorrectionRequestTableExists(): bool
    {
        static $exists = null;
        if ($exists !== null) {
            return $exists;
        }

        try {
            $rows = Db::query(
                'SELECT COUNT(*) AS c FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?',
                ['record_form_correction_requests']
            );
            $exists = (int)($rows[0]['c'] ?? 0) > 0;

            return $exists;
        } catch (\Throwable $exception) {
            $exists = false;

            return false;
        }
    }

    /**
     * @return list<string>
     */
    private function qualityManagerUserIds(): array
    {
        return array_map('strval', User::where('role', 'quality_manager')
            ->where('publish', 1)
            ->where('soft_delete', 0)
            ->column('id'));
    }

    /**
     * @return list<string>
     */
    private function recordCorrectionApproverUserIds(): array
    {
        $companyId = (string)Config::get('qms.company_id');
        $query = Db::name('users')
            ->alias('u')
            ->leftJoin('employee_appointments ea', 'ea.employee_id = u.employee_id')
            ->leftJoin('qms_positions p', 'p.id = ea.position_id')
            ->where('u.company_id', $companyId)
            ->where('u.publish', 1)
            ->where('u.soft_delete', 0)
            ->where(function ($query) {
                $query->where('u.is_approver', 1)
                    ->whereOr(function ($query) {
                        $query->where('p.company_id', (string)Config::get('qms.company_id'))
                            ->where('p.code', 'top_management')
                            ->where('p.review_status', 'published')
                            ->where('p.publish', 1)
                            ->where('p.soft_delete', 0)
                            ->where('ea.status', 'active')
                            ->where('ea.publish', 1)
                            ->where('ea.soft_delete', 0)
                            ->where(function ($query) {
                                $query->whereNull('ea.appointed_at')->whereOr('ea.appointed_at', '<=', date('Y-m-d'));
                            })
                            ->where(function ($query) {
                                $query->whereNull('ea.valid_until')->whereOr('ea.valid_until', '>=', date('Y-m-d'));
                            });
                    });
            });

        return array_values(array_unique(array_map('strval', $query->column('u.id'))));
    }

    /**
     * @return list<string>
     */
    private function recordCorrectionRequesterUserIds(string $recordId): array
    {
        if ($recordId === '') {
            return [];
        }

        $ids = array_map('strval', Db::name('notifications')
            ->where('title', '记录更正申请')
            ->where('link_controller', 'record_form_instance')
            ->where('link_action', 'view')
            ->where('link_id', $recordId)
            ->whereNotNull('created_by')
            ->column('created_by'));
        if ($this->recordCorrectionRequestTableExists()) {
            $ids = array_merge($ids, array_map('strval', Db::name('record_form_correction_requests')
                ->where('record_id', $recordId)
                ->whereNotNull('requested_by')
                ->column('requested_by')));
        }

        return array_values(array_unique(array_filter($ids)));
    }

    public function print()
    {
        $record = $this->findInstance();

        return $this->renderPrintHtml($record);
    }

    public function printCorrections()
    {
        $record = $this->findInstance();
        $corrections = $this->recordCorrectionsFor((string)$record->id);
        if ($corrections === []) {
            Session::flash('warning', '当前记录尚无已登记的更正内容，暂无更正说明页可打印。');

            return redirect('/record_form_instance/view?id=' . $record->id);
        }

        return $this->renderCorrectionPrintHtml($record, $corrections);
    }

    public function downloadCurrentPackage()
    {
        $record = $this->findInstance();
        $corrections = $this->recordCorrectionsFor((string)$record->id);
        if ($corrections === []) {
            throw new HttpException(404, '当前记录没有已批准更正，请直接下载原始 PDF');
        }

        $relativeOriginalPath = trim((string)$record->generated_pdf_path);
        if ($relativeOriginalPath === '') {
            throw new HttpException(409, '原始 PDF 尚未生成，不能制作当前完整记录包');
        }
        $publicRoot = realpath(public_path());
        $originalPdfPath = realpath(public_path() . ltrim($relativeOriginalPath, '/\\'));
        if (
            $publicRoot === false
            || $originalPdfPath === false
            || !str_starts_with($originalPdfPath, $publicRoot . DIRECTORY_SEPARATOR)
            || !is_file($originalPdfPath)
        ) {
            throw new HttpException(404, '原始 PDF 文件不存在，不能制作当前完整记录包');
        }

        $appendix = PdfRenderService::renderHtmlTemporary(
            $this->renderCorrectionPrintHtml($record, $corrections),
            (string)$record->id,
            '更正附页'
        );
        try {
            $latestCorrectionAt = (string)($corrections[array_key_last($corrections)]['registered_at'] ?? '');
            $package = RecordFormCurrentPackageService::build(
                (string)$record->id,
                (string)$record->record_title,
                $originalPdfPath,
                (string)($record->generated_pdf_name ?: basename($originalPdfPath)),
                (string)$appendix['absolute_path'],
                count($corrections),
                $latestCorrectionAt
            );
        } finally {
            $appendixPath = (string)($appendix['absolute_path'] ?? '');
            if ($appendixPath !== '' && is_file($appendixPath)) {
                @unlink($appendixPath);
            }
        }

        FileService::downloadAbsolute((string)$package['file_path'], (string)$package['file_name']);
    }

    public function downloadCurrentPdf()
    {
        $record = $this->findInstance();
        $corrections = $this->recordCorrectionsFor((string)$record->id);
        if ($corrections === []) {
            throw new HttpException(404, '当前记录没有已批准更正，请直接下载原始 PDF');
        }

        try {
            $pdf = $this->refreshCurrentStatePdf($record, $corrections);
        } catch (RuntimeException $exception) {
            throw new HttpException(500, '当前状态 PDF 暂时无法生成：' . $exception->getMessage());
        }

        FileService::download((string)$pdf['file_path'], (string)$pdf['file_name']);
    }

    public function exportPdf()
    {
        $record = $this->findInstance();
        if (!$this->consumePdfActionToken((string)$record->id)) {
            throw new HttpException(403, 'PDF 生成请求无效，请刷新页面后重试');
        }

        if (!$this->canExportPdf($record)) {
            Session::flash('warning', '已归档或已作废记录不能重新生成 PDF。如需变更请发起更正或换版。');

            return redirect('/record_form_instance/view?id=' . $record->id);
        }

        if (!class_exists(PdfRenderService::class)) {
            Session::flash('warning', 'PDF 渲染服务尚未配置，暂不可用。请联系管理员确认服务状态。');

            return redirect('/record_form_instance/view?id=' . $record->id);
        }

        $html = $this->renderPrintHtml($record);
        $pdf = PdfRenderService::renderHtml($html, $record->id, $record->record_title);
        $record->save([
            'generated_pdf_path' => $pdf['file_path'],
            'generated_pdf_name' => $pdf['file_name'],
            'status' => 'generated',
        ]);
        Session::flash('success', '记录 PDF 已生成，记录状态已锁定为「已生成PDF」。如需更正请走作废/换版/更正流程。');

        return redirect('/record_form_instance/view?id=' . $record->id);
    }

    public function downloadPdf()
    {
        $record = $this->findInstance();
        if (!$record->generated_pdf_path) {
            throw new HttpException(404, 'PDF 尚未生成');
        }

        FileService::download($record->generated_pdf_path, $record->generated_pdf_name ?: $record->record_title . '.pdf');
    }

    public function downloadPreviewPdf()
    {
        $record = $this->findInstance();
        $fileName = basename(trim((string)$this->request->param('file', '')));
        if ($fileName === '' || !str_ends_with(strtolower($fileName), '.pdf')) {
            throw new HttpException(404, '临时预览 PDF 不存在');
        }

        $dir = $this->previewPdfDirectory((string)$record->id);
        $fullPath = $dir . $fileName;
        if (!is_file($fullPath)) {
            throw new HttpException(404, '临时预览 PDF 不存在');
        }

        FileService::downloadAbsolute($fullPath, $fileName);
    }

    public function reviewArtifact()
    {
        $year = max(2000, min(2100, (int)$this->request->param('year', 2025)));
        $batch = trim((string)$this->request->param('batch', 'pdf-visual-review'));
        $file = trim((string)$this->request->param('file', ''));
        if ($batch === '' || preg_match('/^[a-zA-Z0-9._-]+$/', $batch) !== 1 || $file === '') {
            throw new HttpException(404, '审核产物不存在');
        }
        if (str_contains($file, '..') || str_starts_with($file, '/') || str_starts_with($file, '\\')) {
            throw new HttpException(404, '审核产物不存在');
        }
        if (preg_match('/^[a-zA-Z0-9._\/-]+$/', $file) !== 1) {
            throw new HttpException(404, '审核产物不存在');
        }

        $base = root_path() . 'runtime' . DIRECTORY_SEPARATOR . 'record-form-batches' . DIRECTORY_SEPARATOR
            . (string)$year . DIRECTORY_SEPARATOR . $batch . DIRECTORY_SEPARATOR;
        $realBase = realpath($base);
        $fullPath = realpath($base . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $file));
        if (!$fullPath || !$realBase || !str_starts_with($fullPath, $realBase . DIRECTORY_SEPARATOR) || !is_file($fullPath)) {
            throw new HttpException(404, '审核产物不存在');
        }

        FileService::previewAbsolute($fullPath, basename($fullPath));
    }

    private function findTemplate(bool $requirePublished = false): TemplateModel
    {
        $id = trim((string)$this->request->param('template_id', ''));
        if ($id === '') {
            throw new HttpException(404, '记录表格模板不存在');
        }

        return $this->findTemplateById($id, $requirePublished);
    }

    private function findTemplateById(string $id, bool $requirePublished = false, bool $includeDeleted = false): TemplateModel
    {
        if ($id === '') {
            throw new HttpException(404, '记录表格模板不存在');
        }

        $query = TemplateModel::where('id', $id);
        if (!$includeDeleted) {
            $query->where('soft_delete', 0);
        }

        $template = $query->find();
        if (!$template) {
            throw new HttpException(404, '记录表格模板不存在');
        }
        if ($requirePublished && !$this->isTemplateFillable($template)) {
            throw new HttpException(403, '只有已完成高保真复核的已发布记录表格模板可填写');
        }

        return $template;
    }

    private function isTemplateFillable(TemplateModel $template): bool
    {
        $printTemplateKey = trim((string)$template->print_template_key);

        return TrialModeService::isTemplateUsable($template)
            && $printTemplateKey !== ''
            && $printTemplateKey !== 'generic_record_form'
            && $this->printTemplateExists($printTemplateKey);
    }

    private function printTemplateExists(string $printTemplateKey): bool
    {
        if (preg_match('/\A[a-zA-Z0-9_-]+\z/', $printTemplateKey) !== 1) {
            return false;
        }

        $path = root_path() . 'app' . DIRECTORY_SEPARATOR . 'record_form_print' . DIRECTORY_SEPARATOR . $printTemplateKey . '.php';

        return is_file($path);
    }

    private function findInstance(): InstanceModel
    {
        $id = trim((string)$this->request->param('id', ''));
        if ($id === '') {
            throw new HttpException(404, '记录不存在');
        }

        return $this->findInstanceById($id);
    }

    private function findInstanceById(string $id): InstanceModel
    {
        $record = InstanceModel::where('id', $id)->find();
        if (!$record) {
            throw new HttpException(404, '记录不存在');
        }

        return $record;
    }

    private function renderPrintHtml(InstanceModel $record): string
    {
        $template = $this->templateForRecord($record);
        $this->decodeSchema($template);
        $values = $this->decodeValues($record->field_values);

        try {
            $html = RecordFormPrintService::render((string)$template['print_template_key'], $template, $values);

            return TrialModeService::watermarkHtml($html, (bool)$record->is_simulation);
        } catch (RuntimeException $exception) {
            throw new HttpException(404, '打印预览不可用：' . $exception->getMessage());
        }
    }

    /**
     * @param array<int,array<string,mixed>>|null $corrections
     * @return array{file_name:string,file_path:string,absolute_path:string}
     */
    private function refreshCurrentStatePdf(InstanceModel $record, ?array $corrections = null): array
    {
        if (trim((string)$record->generated_pdf_path) === '') {
            throw new RuntimeException('原始 PDF 尚未生成');
        }

        $corrections ??= $this->recordCorrectionsFor((string)$record->id);
        $correctionCount = count($corrections);
        if ($correctionCount < 1) {
            throw new RuntimeException('当前记录没有已批准更正');
        }

        $existing = RecordFormCurrentStateService::findLatest((string)$record->id, $correctionCount);
        if ($existing !== null) {
            return $existing;
        }

        return PdfRenderService::renderCurrentHtml(
            $this->renderCurrentStatePrintHtml($record, $corrections),
            (string)$record->id,
            (string)$record->record_title,
            $correctionCount
        );
    }

    /**
     * @param array<int,array<string,mixed>> $corrections
     */
    private function renderCurrentStatePrintHtml(InstanceModel $record, array $corrections): string
    {
        $template = $this->templateForRecord($record);
        $schema = $this->decodeSchema($template);
        $originalValues = $this->decodeValues($record->field_values);
        $currentValues = RecordFormCurrentStateService::apply($schema, $originalValues, $corrections);

        try {
            $html = RecordFormPrintService::render(
                (string)$template['print_template_key'],
                $template,
                $currentValues
            );
            $latestCorrectionAt = (string)($corrections[array_key_last($corrections)]['registered_at'] ?? '');
            $html = RecordFormCurrentStateService::decorateHtml(
                $html,
                count($corrections),
                $latestCorrectionAt
            );

            return TrialModeService::watermarkHtml($html, (bool)$record->is_simulation);
        } catch (RuntimeException $exception) {
            throw new RuntimeException('当前状态 PDF 打印内容不可用：' . $exception->getMessage(), 0, $exception);
        }
    }

    private function renderCorrectionPrintHtml(InstanceModel $record, array $corrections): string
    {
        View::assign('record', $record);
        View::assign('corrections', $corrections);
        View::assign('printedAt', date('Y-m-d H:i:s'));

        return View::fetch('record_form_instance/correction_print');
    }

    private function templateForRecord(InstanceModel $record): array
    {
        if ($this->hasTemplateSnapshot($record)) {
            return $this->snapshotFromRecord($record);
        }

        return $this->backfillTemplateSnapshot($record);
    }

    private function hasTemplateSnapshot(InstanceModel $record): bool
    {
        return trim((string)$record->template_field_schema) !== ''
            && trim((string)$record->template_print_template_key) !== '';
    }

    private function snapshotFromRecord(InstanceModel $record): array
    {
        return [
            'id' => (string)$record->template_id,
            'doc_number' => (string)$record->doc_number,
            'name' => (string)($record->template_name ?: $record->record_title),
            'module' => (string)($record->template_module ?: ''),
            'version' => (string)($record->template_version ?: ''),
            'status' => 'published',
            'review_status' => 'completed',
            'print_template_key' => (string)$record->template_print_template_key,
            'field_schema' => (string)$record->template_field_schema,
        ];
    }

    private function backfillTemplateSnapshot(InstanceModel $record): array
    {
        try {
            $snapshot = $this->snapshotTemplate($this->findTemplateById((string)$record->template_id, false, true));
        } catch (HttpException $exception) {
            throw new HttpException(409, '记录缺少模板快照，且原模板不存在，请人工补齐后再查看或打印');
        }

        $record->save([
            'template_name' => $snapshot['name'],
            'template_module' => $snapshot['module'],
            'template_version' => $snapshot['version'],
            'template_print_template_key' => $snapshot['print_template_key'],
            'template_field_schema' => $snapshot['field_schema'],
        ]);

        return $snapshot;
    }

    private function snapshotTemplate(TemplateModel $template): array
    {
        return [
            'id' => (string)$template->id,
            'doc_number' => (string)$template->doc_number,
            'name' => (string)$template->name,
            'module' => (string)$template->module,
            'version' => (string)$template->version,
            'status' => (string)$template->status,
            'review_status' => (string)$template->review_status,
            'print_template_key' => (string)$template->print_template_key,
            'field_schema' => (string)$template->field_schema,
        ];
    }

    private function canExportPdf(InstanceModel $record): bool
    {
        return (string)$record->status === 'draft';
    }

    private function canEditRecord(InstanceModel $record): bool
    {
        return (string)$record->status === 'draft'
            && (!(bool)$record->is_simulation || TrialModeService::isEnabled());
    }

    private function previewPdfFiles(string $recordId): array
    {
        $files = glob($this->previewPdfDirectory($recordId) . '*.pdf') ?: [];
        rsort($files);

        return array_map(static function (string $path) use ($recordId): array {
            $fileName = basename($path);

            return [
                'file_name' => $fileName,
                'download_url' => '/record_form_instance/downloadPreviewPdf?id=' . rawurlencode($recordId)
                    . '&file=' . rawurlencode($fileName),
                'modified' => date('Y-m-d H:i:s', (int)filemtime($path)),
            ];
        }, $files);
    }

    private function previewPdfDirectory(string $recordId): string
    {
        return root_path() . 'runtime' . DIRECTORY_SEPARATOR . 'record-form-preview-pdf' . DIRECTORY_SEPARATOR . $recordId . DIRECTORY_SEPARATOR;
    }

    private function isTerminalStatus(string $status): bool
    {
        return in_array($status, ['locked', 'voided'], true);
    }

    private function issuePdfActionToken(string $recordId): string
    {
        $tokens = Session::get('record_form_pdf_tokens', []);
        if (!is_array($tokens)) {
            $tokens = [];
        }

        $token = bin2hex(random_bytes(16));
        $tokens[$recordId] = $token;
        Session::set('record_form_pdf_tokens', $tokens);

        return $token;
    }

    private function consumePdfActionToken(string $recordId): bool
    {
        $provided = trim((string)$this->request->post('pdf_token', ''));
        $tokens = Session::get('record_form_pdf_tokens', []);
        if (!is_array($tokens)) {
            return false;
        }

        $expected = (string)($tokens[$recordId] ?? '');
        unset($tokens[$recordId]);
        Session::set('record_form_pdf_tokens', $tokens);

        return $provided !== '' && $expected !== '' && hash_equals($expected, $provided);
    }

    private function defaultValues(array $schema): array
    {
        $values = [];
        foreach ($schema as $field) {
            $values[$field['key']] = $field['type'] === 'repeatable_table' ? [] : ($field['default'] ?? '');
        }

        return $values;
    }

    private function collectValues(array $schema): array
    {
        $posted = $this->request->post('fields/a', []);
        $values = [];

        foreach ($schema as $field) {
            $key = $field['key'];
            if ($field['type'] === 'repeatable_table') {
                $values[$key] = $this->collectRows($posted[$key] ?? [], $field['columns'] ?? []);
                continue;
            }

            if ($field['type'] === 'checkbox') {
                $values[$key] = $this->normalizeCheckboxValue($posted[$key] ?? null);
                continue;
            }

            $values[$key] = $this->normalizeScalarValue($posted[$key] ?? '');
        }

        return $values;
    }

    private function collectRows(mixed $postedRows, array $columns): array
    {
        if (!is_array($postedRows)) {
            return [];
        }

        $rows = [];
        foreach ($postedRows as $postedRow) {
            if (!is_array($postedRow)) {
                continue;
            }

            $row = [];
            $hasValue = false;
            foreach ($columns as $column) {
                $columnKey = $column['key'];
                if ($column['type'] === 'checkbox') {
                    $value = $this->normalizeCheckboxValue($postedRow[$columnKey] ?? null);
                } else {
                    $value = $this->normalizeScalarValue($postedRow[$columnKey] ?? '');
                }
                $row[$columnKey] = $value;
                if (trim($value) !== '' && !($column['type'] === 'checkbox' && $value === '0')) {
                    $hasValue = true;
                }
            }

            if ($hasValue) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    private function normalizeCheckboxValue(mixed $value): string
    {
        if (is_array($value)) {
            foreach ($value as $item) {
                $normalized = $this->normalizeScalarValue($item);
                if (trim($normalized) !== '' && $normalized !== '0') {
                    return '1';
                }
            }

            return '0';
        }

        return $value !== null && trim((string)$value) !== '' && (string)$value !== '0' ? '1' : '0';
    }

    private function normalizeScalarValue(mixed $value): string
    {
        if (is_array($value)) {
            return '';
        }

        return (string)$value;
    }

    private function prepareFormValues(array $schema, array $values): array
    {
        foreach ($schema as $field) {
            $key = $field['key'];
            if ($field['type'] !== 'repeatable_table') {
                $values[$key] = $values[$key] ?? ($field['default'] ?? '');
                continue;
            }

            $rows = $values[$key] ?? [];
            $rows = is_array($rows) ? array_values($rows) : [];
            while (count($rows) < 5) {
                $rows[] = [];
            }
            $values[$key] = $rows;
        }

        return $values;
    }

    private function assignRecordFormEditorContext(TemplateModel|array $template, array $schema, array $values, array $errors): void
    {
        View::assign('template', $template);
        View::assign('schema', $this->decorateSchemaForEditor($schema));
        View::assign('values', $values);
        View::assign('errors', $errors);
        View::assign('employeeOptions', $this->employeeOptions());
        View::assign('departmentOptions', $this->departmentOptions());
    }

    private function selectedRecordYear(): int
    {
        $defaultYear = (int)date('Y');
        $year = $this->request->post('record_year', $this->request->param('year', $defaultYear));

        return RecordFormInstanceTitleService::normalizeYear((int)$year);
    }

    private function assignRecordTitleSuggestionContext(TemplateModel $template, int $year, ?string $recordTitleValue = null): void
    {
        $suggestion = RecordFormInstanceTitleService::suggest($template, $year);
        $currentYear = (int)date('Y');
        $yearOptions = range($currentYear + 1, $currentYear - 2);
        $yearOptions[] = 2025;
        $yearOptions[] = $year;
        $yearOptions = array_values(array_unique(array_map(
            static fn (int $item): int => RecordFormInstanceTitleService::normalizeYear($item),
            $yearOptions
        )));
        rsort($yearOptions);

        $recordTitleValue = trim((string)($recordTitleValue ?? ''));
        View::assign('recordYear', $year);
        View::assign('recordYearOptions', $yearOptions);
        View::assign('recordTitleSuggestion', $suggestion);
        View::assign('recordTitleValue', $recordTitleValue !== '' ? $recordTitleValue : (string)$suggestion['record_title']);
    }

    private function decorateSchemaForEditor(array $schema): array
    {
        foreach ($schema as &$field) {
            if (($field['type'] ?? '') !== 'repeatable_table') {
                continue;
            }

            $personColumn = $this->firstPersonColumn($field['columns'] ?? []);
            if ($personColumn === '') {
                continue;
            }

            $field['employee_picker'] = true;
            $field['employee_name_column'] = $personColumn;
            $field['employee_department_column'] = $this->firstDepartmentColumn($field['columns'] ?? []);
        }
        unset($field);

        return $schema;
    }

    private function firstPersonColumn(array $columns): string
    {
        foreach ($columns as $column) {
            if (($column['type'] ?? '') === 'person') {
                return (string)$column['key'];
            }
        }

        return '';
    }

    private function firstDepartmentColumn(array $columns): string
    {
        foreach ($columns as $column) {
            if (($column['type'] ?? '') === 'department') {
                return (string)$column['key'];
            }
        }

        foreach ($columns as $column) {
            if (($column['key'] ?? '') === 'department') {
                return 'department';
            }
        }

        return '';
    }

    private function employeeOptions(): array
    {
        $departments = DepartmentModel::where('soft_delete', 0)->column('name', 'id');
        $employees = EmployeeModel::where('soft_delete', 0)
            ->where('publish', 1)
            ->order('employee_number')
            ->order('name')
            ->select();

        $options = [];
        foreach ($employees as $employee) {
            $options[] = [
                'id' => (string)$employee->id,
                'name' => (string)$employee->name,
                'employee_number' => (string)($employee->employee_number ?? ''),
                'department_name' => (string)($departments[(string)$employee->department_id] ?? ''),
            ];
        }

        return $options;
    }

    private function departmentOptions(): array
    {
        $departments = DepartmentModel::where('soft_delete', 0)
            ->where('publish', 1)
            ->order('name')
            ->select();

        $options = [];
        foreach ($departments as $department) {
            $options[] = [
                'id' => (string)$department->id,
                'name' => (string)$department->name,
            ];
        }

        return $options;
    }

    private function decodeSchema(TemplateModel|array $template): array
    {
        $fieldSchema = is_array($template) ? (string)($template['field_schema'] ?? '') : (string)$template->field_schema;
        try {
            return RecordFormSchemaService::decode($fieldSchema);
        } catch (InvalidArgumentException $exception) {
            throw new HttpException(422, '记录表格字段配置错误：' . $exception->getMessage());
        }
    }

    private function decodeValues(?string $json): array
    {
        if ($json === null || trim($json) === '') {
            return [];
        }

        $decoded = json_decode($json, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new HttpException(422, '记录字段值损坏：' . json_last_error_msg());
        }

        if (!is_array($decoded)) {
            throw new HttpException(422, '记录字段值损坏：字段值根节点必须是对象');
        }

        return $decoded;
    }

    private function encodeValues(array $values): string
    {
        $encoded = json_encode($values, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        if ($encoded === false) {
            throw new HttpException(500, '记录字段编码失败：' . json_last_error_msg());
        }

        return $encoded;
    }
}
