<?php
declare(strict_types=1);

namespace app\controller;

use app\BaseController;
use app\model\RecordFormTemplate as TemplateModel;
use app\service\FileService;
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
        View::assign('items', $items);
        View::assign('pages', $items->render());
        View::assign('filter', [
            'keyword' => $this->request->param('keyword', ''),
            'status' => $this->request->param('status', ''),
        ]);
        View::assign('canCreateInstances', $this->canCreateInstances());

        return View::fetch('record_form_template/index');
    }

    public function add()
    {
        if ($this->request->isPost()) {
            $data = $this->request->post();
            $id = qms_uuid();
            $errors = $this->validateTemplateInput($data);
            if ($errors !== []) {
                Session::flash('warning', implode('；', $errors));
                View::assign('form', $data);

                return View::fetch('record_form_template/add');
            }

            try {
                $schema = RecordFormSchemaService::decode((string)($data['field_schema'] ?? '[]'));
            } catch (InvalidArgumentException $exception) {
                Session::flash('warning', $exception->getMessage());
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
        if ($this->request->isPost()) {
            $data = $this->request->post();
            $errors = $this->validateTemplateInput($data);
            if ($errors !== []) {
                Session::flash('warning', implode('；', $errors));
                $record->setAttrs($data);
                View::assign('record', $record);

                return View::fetch('record_form_template/edit');
            }

            try {
                $schema = RecordFormSchemaService::decode((string)($data['field_schema'] ?? '[]'));
            } catch (InvalidArgumentException $exception) {
                Session::flash('warning', $exception->getMessage());
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
            Session::flash('success', '记录表格模板已更新');

            return redirect('/record_form_template/view?id=' . $record->id);
        }

        View::assign('record', $record);

        return View::fetch('record_form_template/edit');
    }

    public function view()
    {
        $record = $this->findTemplate();
        View::assign('record', $record);
        View::assign('schema', RecordFormSchemaService::decode($record->field_schema));
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
        }

        return $errors;
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
