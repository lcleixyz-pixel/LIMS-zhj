<?php
App::uses('AppCrudController', 'Controller');

class DocTemplatesController extends AppCrudController {

    public $uses = array('DocTemplate');

    public function add() {
        if ($this->request->is('post')) {
            $id = CakeText::uuid();
            $this->request->data['DocTemplate']['id'] = $id;
            if (!empty($_FILES['template_file']['name'])) {
                $upload = $this->_uploadFile($_FILES['template_file'], 'templates', $id);
                if ($upload) {
                    $this->request->data['DocTemplate']['file_name'] = $upload['file_name'];
                    $this->request->data['DocTemplate']['file_path'] = $upload['file_path'];
                }
            }
            if ($this->DocTemplate->save($this->request->data)) {
                $this->Session->setFlash('模板已上传', 'default', array('class' => 'alert-success'));
                return $this->redirect(array('action' => 'index'));
            }
        }
        $this->set('docLevels', Configure::read('QMS.docLevels'));
        $this->set('pageTitle', '文件模板 - 新增');
    }

    public function download($id = null) {
        $t = $this->DocTemplate->findById($id);
        if (!$t || empty($t['DocTemplate']['file_path'])) {
            throw new NotFoundException();
        }
        $path = WWW_ROOT . $t['DocTemplate']['file_path'];
        if (!file_exists($path)) {
            throw new NotFoundException('文件未找到');
        }
        $this->response->file($path, array('download' => true, 'name' => $t['DocTemplate']['file_name']));
        return $this->response;
    }

    protected function _pageTitle() {
        return '文件模板';
    }
}
