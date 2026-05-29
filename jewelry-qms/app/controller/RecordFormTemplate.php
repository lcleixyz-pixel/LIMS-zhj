<?php
declare(strict_types=1);

namespace app\controller;

use app\BaseController;
use app\model\RecordFormTemplate as TemplateModel;
use app\service\FileService;
use app\service\QmsDocumentStructureService;
use app\service\QmsElementService;
use app\service\RecordFormBatchTemplateService;
use app\service\RecordFormFixtureService;
use app\service\RecordFormPrintService;
use app\service\RecordFormSchemaService;
use InvalidArgumentException;
use RuntimeException;
use think\exception\HttpException;
use think\facade\Session;
use think\facade\View;

class RecordFormTemplate extends BaseController
{
    private const REVIEW_STATUSES = [
        'pending' => '待复核',
        'field_confirmed' => '字段已确认',
        'needs_fidelity' => '需高保真',
        'deferred' => '暂缓',
        'completed' => '已完成',
    ];

    public function index()
    {
        $query = TemplateModel::where('soft_delete', 0);

        if ($keyword = trim((string)$this->request->param('keyword', ''))) {
            $query->where(function ($q) use ($keyword) {
                $q->where('doc_number', 'like', '%' . $keyword . '%')
                    ->whereOr('name', 'like', '%' . $keyword . '%')
                    ->whereOr('module', 'like', '%' . $keyword . '%');
            });
        }
        if ($status = trim((string)$this->request->param('status', ''))) {
            $query->where('status', $status);
        }

        $items = $query->order('doc_number', 'asc')->paginate(20);
        $this->decorateTemplateRows($items);
        View::assign('items', $items);
        View::assign('pages', $items->render());
        View::assign('filter', [
            'keyword' => $this->request->param('keyword', ''),
            'status' => $this->request->param('status', ''),
        ]);
        View::assign('canCreateInstances', $this->canCreateInstances());

        return View::fetch('record_form_template/index');
    }

    public function review()
    {
        $query = TemplateModel::where('soft_delete', 0);

        if ($keyword = trim((string)$this->request->param('keyword', ''))) {
            $query->where(function ($q) use ($keyword) {
                $q->where('doc_number', 'like', '%' . $keyword . '%')
                    ->whereOr('name', 'like', '%' . $keyword . '%')
                    ->whereOr('module', 'like', '%' . $keyword . '%')
                    ->whereOr('source_file_name', 'like', '%' . $keyword . '%');
            });
        }
        if ($status = trim((string)$this->request->param('status', ''))) {
            $query->where('status', $status);
        }
        if ($reviewStatus = trim((string)$this->request->param('review_status', ''))) {
            $query->where('review_status', $reviewStatus);
        }

        $items = $query->order('doc_number', 'asc')->order('name', 'asc')->paginate(30);
        $this->decorateReviewRows($items);

        View::assign('items', $items);
        View::assign('pages', $items->render());
        View::assign('filter', [
            'keyword' => $this->request->param('keyword', ''),
            'status' => $this->request->param('status', ''),
            'review_status' => $this->request->param('review_status', ''),
        ]);
        View::assign('reviewStatusOptions', $this->reviewStatusOptions());
        View::assign('reviewSummary', $this->reviewSummary());

        return View::fetch('record_form_template/review');
    }

    public function updateReview()
    {
        if (!$this->request->isPost()) {
            Session::flash('warning', '请从复核清单更新模板复核状态。');

            return redirect('/record_form_template/review');
        }

        $record = $this->findTemplate();
        $reviewStatus = trim((string)$this->request->post('review_status', 'pending'));
        if (!array_key_exists($reviewStatus, self::REVIEW_STATUSES)) {
            Session::flash('warning', '复核状态无效。');

            return redirect('/record_form_template/review');
        }

        $record->save([
            'review_status' => $reviewStatus,
            'review_note' => trim((string)$this->request->post('review_note', '')),
            'reviewed_at' => date('Y-m-d H:i:s'),
        ]);
        Session::flash('success', '模板复核状态已更新');

        return redirect('/record_form_template/review');
    }

    public function add()
    {
        if ($this->request->isPost()) {
            $data = $this->request->post();
            $id = qms_uuid();
            $errors = $this->validateTemplateInput($data);
            if ($errors !== []) {
                $this->flashValidationErrors($errors);
                View::assign('form', $data);

                return View::fetch('record_form_template/add');
            }

            try {
                $schema = RecordFormSchemaService::decode((string)($data['field_schema'] ?? '[]'));
            } catch (InvalidArgumentException $exception) {
                $this->flashValidationErrors([$exception->getMessage()]);
                View::assign('form', $data);

                return View::fetch('record_form_template/add');
            }

            $record = new TemplateModel();
            $record->id = $id;
            $record->doc_number = trim((string)($data['doc_number'] ?? ''));
            $record->name = trim((string)($data['name'] ?? ''));
            $record->module = trim((string)($data['module'] ?? ''));
            $record->print_template_key = trim((string)($data['print_template_key'] ?? ''));
            $record->field_schema = RecordFormSchemaService::encode($schema);
            $record->version = trim((string)($data['version'] ?? 'A/0'));
            $record->status = $data['status'] ?? 'draft';

            if ($this->hasUploadedSourceFile()) {
                $upload = FileService::upload($_FILES['source_file'], 'record-form-sources', $id);
                if (!$upload) {
                    Session::flash('warning', '原始附件上传失败，请检查文件类型、大小或重新选择文件。');
                    View::assign('form', $data);

                    return View::fetch('record_form_template/add');
                }

                $record->source_file_name = $upload['file_name'];
                $record->source_file_path = $upload['file_path'];
            }

            $record->save();
            Session::flash('success', '记录表格模板已创建');

            return redirect('/record_form_template/view?id=' . $id);
        }

        View::assign('form', [
            'version' => 'A/0',
            'status' => 'draft',
            'field_schema' => '[]',
        ]);

        return View::fetch('record_form_template/add');
    }

    public function edit()
    {
        $record = $this->findTemplate();
        $schemaDraftBlockId = trim((string)$this->request->param('schema_draft_block_id', ''));
        $schemaSuggestionId = trim((string)$this->request->param('schema_suggestion_id', ''));
        View::assign('schemaDraftNotice', '');
        View::assign('schemaDraftChecklist', []);
        View::assign('schemaDraftBlockId', $schemaDraftBlockId);
        View::assign('schemaSuggestionId', $schemaSuggestionId);
        if ($this->request->isPost()) {
            $data = $this->request->post();
            $errors = $this->validateTemplateInput($data);
            if ($errors !== []) {
                $this->flashValidationErrors($errors);
                $record->setAttrs($data);
                View::assign('record', $record);

                return View::fetch('record_form_template/edit');
            }

            try {
                $schema = RecordFormSchemaService::decode((string)($data['field_schema'] ?? '[]'));
            } catch (InvalidArgumentException $exception) {
                $this->flashValidationErrors([$exception->getMessage()]);
                $record->setAttrs($data);
                View::assign('record', $record);

                return View::fetch('record_form_template/edit');
            }

            $update = [
                'doc_number' => trim((string)($data['doc_number'] ?? '')),
                'name' => trim((string)($data['name'] ?? '')),
                'module' => trim((string)($data['module'] ?? '')),
                'print_template_key' => trim((string)($data['print_template_key'] ?? '')),
                'field_schema' => RecordFormSchemaService::encode($schema),
                'version' => trim((string)($data['version'] ?? 'A/0')),
                'status' => $data['status'] ?? 'draft',
            ];

            if ($this->hasUploadedSourceFile()) {
                $upload = FileService::upload($_FILES['source_file'], 'record-form-sources', $record->id);
                if (!$upload) {
                    Session::flash('warning', '原始附件上传失败，请检查文件类型、大小或重新选择文件。');
                    $record->setAttrs($data);
                    View::assign('record', $record);

                    return View::fetch('record_form_template/edit');
                }

                $update['source_file_name'] = $upload['file_name'];
                $update['source_file_path'] = $upload['file_path'];
            }

            $record->save($update);
            $traceMessage = '';
            if ($schemaDraftBlockId !== '') {
                try {
                    QmsElementService::recordSchemaDraftSaved((string)$record->id, $schemaDraftBlockId, $schemaSuggestionId);
                    $traceMessage = '，已记录schema来源追溯';
                } catch (RuntimeException $exception) {
                    Session::flash('warning', '记录表格模板已更新，但schema来源追溯记录失败：' . $exception->getMessage());

                    return redirect('/record_form_template/view?id=' . $record->id);
                }
            }

            Session::flash('success', '记录表格模板已更新' . $traceMessage);

            return redirect('/record_form_template/view?id=' . $record->id);
        }

        if ($schemaDraftBlockId !== '') {
            $draft = QmsDocumentStructureService::recordRequirementSchemaDraftForBlock($schemaDraftBlockId);
            if ($draft !== []) {
                $record->setAttr('field_schema', RecordFormSchemaService::encode($draft));
                View::assign('schemaDraftChecklist', QmsDocumentStructureService::recordRequirementSchemaFieldChecklistForBlock($schemaDraftBlockId));
                View::assign('schemaDraftNotice', '已根据程序记录要求生成候选schema草稿。请人工复核字段、类型、责任和保存期限；未点击保存前不会修改正式记录表格模板。');
            } else {
                View::assign('schemaDraftNotice', '未能从当前记录要求块生成候选schema草稿，请先复核结构化文件和记录表格关联。');
            }
        }

        View::assign('record', $record);

        return View::fetch('record_form_template/edit');
    }

    public function reviewSchemaDraftFields()
    {
        if (!$this->request->isPost()) {
            Session::flash('warning', '请从记录表格编辑页提交字段复核意见。');

            return redirect('/record_form_template/index');
        }

        $record = $this->findTemplate();
        $schemaDraftBlockId = trim((string)$this->request->post('schema_draft_block_id', ''));
        $schemaSuggestionId = trim((string)$this->request->post('schema_suggestion_id', ''));
        $fieldReviews = $this->request->post('field_reviews/a', []);
        $redirectUrl = '/record_form_template/edit?id=' . rawurlencode((string)$record->id);
        if ($schemaDraftBlockId !== '') {
            $redirectUrl .= '&schema_draft_block_id=' . rawurlencode($schemaDraftBlockId);
        }
        if ($schemaSuggestionId !== '') {
            $redirectUrl .= '&schema_suggestion_id=' . rawurlencode($schemaSuggestionId);
        }

        try {
            $summary = QmsElementService::reviewRecordSchemaDraftFields((string)$record->id, $schemaDraftBlockId, $fieldReviews, $schemaSuggestionId);
            Session::flash('success', '字段复核意见已记录 ' . (int)($summary['reviewed_fields'] ?? 0) . ' 项，正式字段配置未修改。');
        } catch (RuntimeException $exception) {
            Session::flash('warning', '字段复核记录失败：' . $exception->getMessage());
        }

        return redirect($redirectUrl);
    }

    public function view()
    {
        $record = $this->findTemplate();
        $record->setAttr('fillable', $this->isTemplateFillable($record));
        View::assign('record', $record);
        View::assign('schema', RecordFormSchemaService::decode($record->field_schema));
        View::assign('requirementEvidence', QmsDocumentStructureService::recordFormRequirementEvidence((string)$record->id));
        View::assign('canCreateInstances', $this->canCreateInstances());

        return View::fetch('record_form_template/view');
    }

    public function preview()
    {
        $record = $this->findTemplate();
        $schema = RecordFormSchemaService::decode($record->field_schema);
        $values = [];

        foreach ($schema as $field) {
            if ($field['type'] === 'repeatable_table') {
                $values[$field['key']] = [
                    array_fill_keys(array_column($field['columns'] ?? [], 'key'), ''),
                ];
                continue;
            }

            $values[$field['key']] = $field['default'] ?? '';
        }

        try {
            return RecordFormPrintService::render($record->print_template_key, $record->toArray(), $values);
        } catch (RuntimeException $exception) {
            throw new HttpException(404, '打印预览不可用：' . $exception->getMessage());
        }
    }

    public function source()
    {
        $record = $this->findTemplate();
        if (!$record->source_file_path) {
            throw new HttpException(404, '原始附件不存在');
        }

        FileService::download($record->source_file_path, $record->source_file_name ?: $record->name);
    }

    public function sourcePreview()
    {
        $record = $this->findTemplate();
        if (!$record->source_file_path) {
            throw new HttpException(404, '原始附件不存在');
        }

        FileService::preview($record->source_file_path, $record->source_file_name ?: $record->name);
    }

    public function delete()
    {
        if (!$this->request->isPost()) {
            Session::flash('warning', '请从模板列表使用删除按钮操作。');

            return redirect('/record_form_template/index');
        }

        $record = $this->findTemplate();
        $record->soft_delete = 1;
        $record->save();
        Session::flash('success', '记录表格模板已删除');

        return redirect('/record_form_template/index');
    }

    public function seedSamples()
    {
        if (!class_exists(RecordFormFixtureService::class)) {
            Session::flash('warning', '样板模板服务尚未接入，请在 Task 7 完成后再写入样板。');

            return redirect('/record_form_template/index');
        }

        $count = RecordFormFixtureService::seed();
        Session::flash('success', '已写入样板模板 ' . $count . ' 条');

        return redirect('/record_form_template/index');
    }

    public function seedBatch()
    {
        $summary = RecordFormBatchTemplateService::seed();
        $message = sprintf(
            '批量建立模板完成：总计 %d，新增 %d，更新 %d，跳过 %d，作废旧generic %d',
            $summary['total'],
            $summary['created'],
            $summary['updated'],
            $summary['skipped'],
            $summary['retired_generic'] ?? 0
        );
        if (($summary['errors'] ?? []) !== []) {
            $message .= '；问题：' . implode('；', array_slice($summary['errors'], 0, 3));
        }

        Session::flash($summary['skipped'] > 0 ? 'warning' : 'success', $message);

        return redirect('/record_form_template/index');
    }

    private function findTemplate(): TemplateModel
    {
        $id = trim((string)$this->request->param('id', ''));
        if ($id === '') {
            throw new HttpException(404, '记录表格模板不存在');
        }

        $record = TemplateModel::where('soft_delete', 0)->where('id', $id)->find();
        if (!$record) {
            throw new HttpException(404, '记录表格模板不存在');
        }

        return $record;
    }

    private function reviewStatusOptions(): array
    {
        $options = [];
        foreach (self::REVIEW_STATUSES as $value => $label) {
            $options[] = [
                'value' => $value,
                'label' => $label,
            ];
        }

        return $options;
    }

    private function decorateReviewRows(iterable $items): void
    {
        foreach ($items as $item) {
            $stats = $this->templateFieldStats((string)$item->field_schema);
            $reviewStatus = (string)($item->review_status ?: 'pending');
            $item->setAttr('field_count', $stats['field_count']);
            $item->setAttr('repeatable_count', $stats['repeatable_count']);
            $item->setAttr('schema_issue', $stats['schema_issue']);
            $item->setAttr('review_status_value', array_key_exists($reviewStatus, self::REVIEW_STATUSES) ? $reviewStatus : 'pending');
            $item->setAttr('review_status_label', self::REVIEW_STATUSES[$item->review_status_value] ?? self::REVIEW_STATUSES['pending']);
            $item->setAttr('fillable', $this->isTemplateFillable($item));
        }
    }

    private function decorateTemplateRows(iterable $items): void
    {
        foreach ($items as $item) {
            $item->setAttr('fillable', $this->isTemplateFillable($item));
        }
    }

    private function isTemplateFillable(TemplateModel $template): bool
    {
        $printTemplateKey = trim((string)$template->print_template_key);

        return $template->status === 'published'
            && (string)($template->review_status ?? '') === 'completed'
            && $printTemplateKey !== ''
            && $printTemplateKey !== 'generic_record_form'
            && $this->printTemplateExists($printTemplateKey);
    }

    private function templateFieldStats(string $fieldSchema): array
    {
        try {
            $schema = RecordFormSchemaService::decode($fieldSchema);
        } catch (InvalidArgumentException $exception) {
            return [
                'field_count' => 0,
                'repeatable_count' => 0,
                'schema_issue' => $exception->getMessage(),
            ];
        }

        $fieldCount = 0;
        $repeatableCount = 0;
        foreach ($schema as $field) {
            $fieldCount++;
            if (($field['type'] ?? '') === 'repeatable_table') {
                $repeatableCount++;
                $fieldCount += count($field['columns'] ?? []);
            }
        }

        return [
            'field_count' => $fieldCount,
            'repeatable_count' => $repeatableCount,
            'schema_issue' => '',
        ];
    }

    private function reviewSummary(): array
    {
        $summary = [];
        foreach (self::REVIEW_STATUSES as $value => $label) {
            $summary[] = [
                'value' => $value,
                'label' => $label,
                'count' => TemplateModel::where('soft_delete', 0)->where('review_status', $value)->count(),
            ];
        }

        return $summary;
    }

    private function validateTemplateInput(array $data): array
    {
        $errors = [];

        if (trim((string)($data['doc_number'] ?? '')) === '') {
            $errors[] = '编号不能为空';
        }
        if (trim((string)($data['name'] ?? '')) === '') {
            $errors[] = '名称不能为空';
        }

        $printTemplateKey = trim((string)($data['print_template_key'] ?? ''));
        if ($printTemplateKey === '') {
            $errors[] = '打印模板键不能为空';
        } elseif (preg_match('/\A[a-zA-Z0-9_-]+\z/', $printTemplateKey) !== 1) {
            $errors[] = '打印模板键只能包含字母、数字、下划线和短横线';
        } elseif (($data['status'] ?? '') === 'published' && !$this->printTemplateExists($printTemplateKey)) {
            $errors[] = '发布模板前必须配置可用的打印模板键';
        }

        return $errors;
    }

    private function flashValidationErrors(array $errors): void
    {
        Session::flash('validation_errors', $errors);
    }

    private function printTemplateExists(string $printTemplateKey): bool
    {
        $path = root_path() . 'app' . DIRECTORY_SEPARATOR . 'record_form_print' . DIRECTORY_SEPARATOR . $printTemplateKey . '.php';

        return is_file($path);
    }

    private function hasUploadedSourceFile(): bool
    {
        return isset($_FILES['source_file'])
            && trim((string)($_FILES['source_file']['name'] ?? '')) !== ''
            && (int)($_FILES['source_file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
    }

    private function canCreateInstances(): bool
    {
        return class_exists(\app\controller\RecordFormInstance::class);
    }
}
