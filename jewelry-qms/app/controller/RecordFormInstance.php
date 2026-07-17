<?php
declare(strict_types=1);

namespace app\controller;

use app\BaseController;
use app\model\Department as DepartmentModel;
use app\model\Employee as EmployeeModel;
use app\model\RecordFormInstance as InstanceModel;
use app\model\RecordFormTemplate as TemplateModel;
use app\service\FileService;
use app\service\PdfRenderService;
use app\service\RecordFormBatchReviewService;
use app\service\RecordFormLayoutConfirmationService;
use app\service\RecordFormInstanceTitleService;
use app\service\RecordFormPrintService;
use app\service\RecordFormSchemaService;
use app\service\TrialModeService;
use InvalidArgumentException;
use RuntimeException;
use think\exception\HttpException;
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
        foreach ($items as $item) {
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

        foreach ($items as $item) {
            $status = (string)$item->status;
            $item->setAttr('pdf_token', $this->canExportPdf($item) ? $this->issuePdfActionToken((string)$item->id) : '');
            $item->setAttr('can_edit', $this->canEditRecord($item));
            $item->setAttr('status_label', self::recordStatusLabels()[$status] ?? $status);
            $item->setAttr('filler_label', $userLabels[(string)$item->created_by] ?? ((string)$item->created_by !== '' ? (string)$item->created_by : '未记录'));
            $item->setAttr('reviewer_label', in_array($status, ['locked', 'voided'], true)
                ? ($userLabels[(string)$item->modified_by] ?? ((string)$item->modified_by !== '' ? (string)$item->modified_by : '未记录'))
                : '待审核');
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
        Session::flash('success', '版式确认状态已更新');

        $returnUrl = trim((string)$this->request->post('return_url', ''));
        if ($returnUrl === '' || !str_starts_with($returnUrl, '/record_form_instance/reviewDashboard')) {
            $returnUrl = '/record_form_instance/reviewDashboard?year=' . $year;
        }

        return redirect($returnUrl);
    }

    public function create()
    {
        if (trim((string)$this->request->param('template_id', '')) === '') {
            Session::flash('warning', '请先选择记录模板，再填写记录。');

            return redirect('/record_form_template/index');
        }

        $template = $this->findTemplate();
        if (!$this->isTemplateFillable($template)) {
            Session::flash('warning', '只有已完成高保真复核的已发布记录表格模板可填写');

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
            Session::flash('success', '记录草稿已保存');

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
            Session::flash('warning', '已形成 PDF、已归档或已作废记录不能直接编辑；如需更正，请先走作废/换版/更正流程。');

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
                Session::flash('success', '记录已保存');

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
        View::assign('record', $record);
        View::assign('template', $template);
        View::assign('schema', $this->decodeSchema($template));
        View::assign('values', $this->decodeValues($record->field_values));
        View::assign('canExportPdf', $this->canExportPdf($record));
        View::assign('canEdit', $this->canEditRecord($record));
        View::assign('pdfToken', $this->canExportPdf($record) ? $this->issuePdfActionToken((string)$record->id) : '');
        View::assign('previewPdfFiles', $this->previewPdfFiles((string)$record->id));

        return View::fetch('record_form_instance/view');
    }

    public function print()
    {
        $record = $this->findInstance();

        return $this->renderPrintHtml($record);
    }

    public function exportPdf()
    {
        $record = $this->findInstance();
        if (!$this->consumePdfActionToken((string)$record->id)) {
            throw new HttpException(403, 'PDF 生成请求无效，请刷新页面后重试');
        }

        if (!$this->canExportPdf($record)) {
            Session::flash('warning', '已归档或已作废记录不能重新生成 PDF。');

            return redirect('/record_form_instance/view?id=' . $record->id);
        }

        if (!class_exists(PdfRenderService::class)) {
            Session::flash('warning', 'PDF 渲染服务尚未接入，请在 Task6 完成后再生成 PDF。');

            return redirect('/record_form_instance/view?id=' . $record->id);
        }

        $html = $this->renderPrintHtml($record);
        $pdf = PdfRenderService::renderHtml($html, $record->id, $record->record_title);
        $record->save([
            'generated_pdf_path' => $pdf['file_path'],
            'generated_pdf_name' => $pdf['file_name'],
            'status' => 'generated',
        ]);
        Session::flash('success', 'PDF 已生成');

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
        return !$this->isTerminalStatus((string)$record->status);
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
