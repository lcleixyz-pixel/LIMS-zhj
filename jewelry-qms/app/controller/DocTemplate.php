<?php
declare(strict_types=1);

namespace app\controller;

use app\model\DocTemplate as DocTemplateModel;
use app\service\FileService;
use think\facade\Session;
use think\facade\View;
use think\exception\HttpException;

class DocTemplate extends CrudBase
{
    protected string $modelClass = DocTemplateModel::class;
    protected string $viewPrefix = 'doc_template';
    protected string $pageTitle = '文件模板';

    public function add()
    {
        if ($this->request->isPost()) {
            $data = $this->request->post();
            $model = $this->getModel();
            $newId = $data['id'] ?? qms_uuid();

            $file = $this->request->file('template_file');
            if ($file) {
                $upload = FileService::upload($_FILES['template_file'], 'templates', $newId);
                if ($upload) {
                    $data['file_name'] = $upload['file_name'];
                    $data['file_path'] = $upload['file_path'];
                    $data['file_type'] = $upload['file_type'];
                }
            }

            $data['id'] = $newId;
            $model->save($data);
            Session::flash('success', '模板已创建');
            return redirect('/doc_template/index');
        }
        View::assign('pageTitle', $this->pageTitle . ' - 新增');
        return View::fetch('doc_template/add');
    }

    public function download()
    {
        $id = $this->request->param('id');
        $template = DocTemplateModel::find($id);
        if (!$template || empty($template->file_path)) {
            throw new HttpException(404, '模板文件不存在');
        }
        FileService::download($template->file_path, $template->file_name);
    }
}
