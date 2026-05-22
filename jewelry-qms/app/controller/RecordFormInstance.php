<?php
declare(strict_types=1);

namespace app\controller;

use app\BaseController;
use app\model\RecordFormInstance as InstanceModel;
use app\model\RecordFormTemplate as TemplateModel;
use app\service\FileService;
use app\service\PdfRenderService;
use app\service\RecordFormPrintService;
use app\service\RecordFormSchemaService;
use InvalidArgumentException;
use RuntimeException;
use think\exception\HttpException;
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
        View::assign('items', $items);
        View::assign('pages', $items->render());
        View::assign('filter', ['keyword' => $this->request->param('keyword', '')]);

        return View::fetch('record_form_instance/index');
    }

    public function create()
    {
        $template = $this->findTemplate();
        $schema = $this->decodeSchema($template);

        if ($this->request->isPost()) {
            $values = $this->collectValues($schema);
            $errors = RecordFormSchemaService::validateValues($schema, $values);
            if ($errors !== []) {
                View::assign('errors', $errors);
                View::assign('template', $template);
                View::assign('schema', $schema);
                View::assign('values', $this->prepareFormValues($schema, $values));

                return View::fetch('record_form_instance/create');
            }

            $record = InstanceModel::create([
                'id' => qms_uuid(),
                'template_id' => $template->id,
                'doc_number' => $template->doc_number,
                'record_title' => trim((string)$this->request->post('record_title', $template->name)),
                'field_values' => $this->encodeValues($values),
                'status' => 'draft',
            ]);
            Session::flash('success', '记录草稿已保存');

            return redirect('/record_form_instance/view?id=' . $record->id);
        }

        View::assign('template', $template);
        View::assign('schema', $schema);
        View::assign('values', $this->prepareFormValues($schema, $this->defaultValues($schema)));
        View::assign('errors', []);

        return View::fetch('record_form_instance/create');
    }

    public function edit()
    {
        $record = $this->findInstance();
        if ($record->status === 'locked') {
            Session::flash('warning', '已归档记录不能编辑');

            return redirect('/record_form_instance/view?id=' . $record->id);
        }

        $template = $this->findTemplateById((string)$record->template_id);
        $schema = $this->decodeSchema($template);

        if ($this->request->isPost()) {
            $values = $this->collectValues($schema);
            $errors = RecordFormSchemaService::validateValues($schema, $values);
            if ($errors === []) {
                $record->save([
                    'record_title' => trim((string)$this->request->post('record_title', $record->record_title)),
                    'field_values' => $this->encodeValues($values),
                    'status' => 'draft',
                ]);
                Session::flash('success', '记录已保存');

                return redirect('/record_form_instance/view?id=' . $record->id);
            }

            View::assign('errors', $errors);
            View::assign('values', $this->prepareFormValues($schema, $values));
        } else {
            View::assign('errors', []);
            View::assign('values', $this->prepareFormValues($schema, $this->decodeValues($record->field_values)));
        }

        View::assign('record', $record);
        View::assign('template', $template);
        View::assign('schema', $schema);

        return View::fetch('record_form_instance/edit');
    }

    public function view()
    {
        $record = $this->findInstance();
        $template = $this->findTemplateById((string)$record->template_id);
        View::assign('record', $record);
        View::assign('template', $template);
        View::assign('schema', $this->decodeSchema($template));
        View::assign('values', $this->decodeValues($record->field_values));

        return View::fetch('record_form_instance/view');
    }

    public function print()
    {
        $record = $this->findInstance();

        return $this->renderPrintHtml($record);
    }

    public function internalPrint()
    {
        $id = trim((string)$this->request->param('id', ''));
        if ($id === '') {
            throw new HttpException(404, '记录不存在');
        }

        $expires = trim((string)$this->request->param('expires', ''));
        $token = trim((string)$this->request->param('token', ''));
        if ($expires === '' || $token === '') {
            throw new HttpException(403, '打印链接无效');
        }

        if (!ctype_digit($expires) || (int)$expires < time()) {
            throw new HttpException(403, '打印链接已过期');
        }

        if (!hash_equals($this->pdfToken($id, (int)$expires), $token)) {
            throw new HttpException(403, '打印链接无效');
        }

        $record = $this->findInstanceById($id);

        return $this->renderPrintHtml($record);
    }

    public function exportPdf()
    {
        $record = $this->findInstance();
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

    private function findTemplate(): TemplateModel
    {
        $id = trim((string)$this->request->param('template_id', ''));
        if ($id === '') {
            throw new HttpException(404, '记录表格模板不存在');
        }

        return $this->findTemplateById($id);
    }

    private function findTemplateById(string $id): TemplateModel
    {
        if ($id === '') {
            throw new HttpException(404, '记录表格模板不存在');
        }

        $template = TemplateModel::where('soft_delete', 0)->where('id', $id)->find();
        if (!$template) {
            throw new HttpException(404, '记录表格模板不存在');
        }

        return $template;
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
        $template = $this->findTemplateById((string)$record->template_id);
        $this->decodeSchema($template);
        $values = $this->decodeValues($record->field_values);

        try {
            return RecordFormPrintService::render($template->print_template_key, $template->toArray(), $values);
        } catch (RuntimeException $exception) {
            throw new HttpException(404, '打印预览不可用：' . $exception->getMessage());
        }
    }

    private function pdfToken(string $recordId, int $expires): string
    {
        return hash_hmac('sha256', $recordId . '|' . $expires, $this->pdfTokenSecret());
    }

    private function pdfTokenSecret(): string
    {
        $secret = function_exists('env') ? trim((string)\env('RECORD_FORM_PDF_TOKEN_SECRET', '')) : '';
        if ($secret === '') {
            $rawSecret = getenv('RECORD_FORM_PDF_TOKEN_SECRET');
            $secret = $rawSecret === false ? '' : trim((string)$rawSecret);
        }

        if (strlen($secret) < 32) {
            throw new HttpException(500, 'PDF 签名密钥未配置');
        }

        return $secret;
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

    private function decodeSchema(TemplateModel $template): array
    {
        try {
            return RecordFormSchemaService::decode($template->field_schema);
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
