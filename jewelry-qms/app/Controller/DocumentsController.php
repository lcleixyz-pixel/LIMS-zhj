<?php
App::uses('AppController', 'Controller');

class DocumentsController extends AppController {

    public $uses = array('Document', 'DocCategory', 'DocTemplate', 'DocumentRevision', 'Approval', 'Department');

    public function index() {
        $conditions = array('Document.soft_delete' => 0);
        if (!empty($this->request->query['level'])) {
            $conditions['Document.level'] = $this->request->query['level'];
        }
        if (!empty($this->request->query['status'])) {
            $conditions['Document.status'] = $this->request->query['status'];
        }
        $this->paginate = array(
            'conditions' => $conditions,
            'limit' => 20,
            'order' => array('Document.doc_number' => 'ASC'),
            'contain' => array('DocCategory', 'Department')
        );
        $this->set('documents', $this->paginate());
        $this->set('categories', $this->DocCategory->find('list', array('conditions' => array('DocCategory.soft_delete' => 0))));
    }

    public function view($id = null) {
        $doc = $this->Document->find('first', array(
            'conditions' => array('Document.id' => $id),
            'contain' => array('DocCategory', 'Department', 'DocumentRevision')
        ));
        if (!$doc) {
            throw new NotFoundException('文件不存在');
        }
        $approvals = $this->Approval->find('all', array(
            'conditions' => array('Approval.record' => $id, 'Approval.model_name' => 'Document', 'Approval.soft_delete' => 0),
            'order' => array('Approval.approval_level' => 'ASC')
        ));
        $this->set(compact('doc', 'approvals'));
    }

    public function add() {
        if ($this->request->is('post')) {
            $id = CakeText::uuid();
            $this->request->data['Document']['id'] = $id;
            $this->request->data['Document']['company_id'] = $this->Session->read('User.company_id');
            $this->request->data['Document']['status'] = 'draft';
            $this->request->data['Document']['prepared_by'] = $this->Session->read('User.employee_id');
            $this->request->data['Document']['created_by'] = $this->Session->read('User.id');

            if (!empty($_FILES['document_file']['name'])) {
                $upload = $this->_uploadFile($_FILES['document_file'], 'documents', $id);
                if ($upload) {
                    $this->request->data['Document']['file_name'] = $upload['file_name'];
                    $this->request->data['Document']['file_path'] = $upload['file_path'];
                    $this->request->data['Document']['file_type'] = $upload['file_type'];
                }
            }

            if ($this->Document->save($this->request->data)) {
                $level = $this->request->data['Document']['level'];
                $this->Workflow->createWorkflow(
                    'documents',
                    'Document',
                    $id,
                    $level,
                    $this->Session->read('User.id'),
                    !empty($this->request->data['Document']['reviewed_by']) ? $this->_employeeToUser($this->request->data['Document']['reviewed_by']) : null,
                    !empty($this->request->data['Document']['approved_by']) ? $this->_employeeToUser($this->request->data['Document']['approved_by']) : null
                );
                $this->Session->setFlash('文件已创建', 'default', array('class' => 'alert-success'));
                return $this->redirect(array('action' => 'view', $id));
            }
            $this->Session->setFlash('保存失败', 'default', array('class' => 'alert-danger'));
        }
        $this->_setFormLists();
    }

    public function edit($id = null) {
        if (!$id || !$this->Document->exists($id)) {
            throw new NotFoundException('文件不存在');
        }
        $doc = $this->Document->findById($id);
        if ($doc['Document']['status'] === 'published') {
            $this->Session->setFlash('已发布文件请使用修订功能', 'default', array('class' => 'alert-warning'));
            return $this->redirect(array('action' => 'view', $id));
        }
        if ($this->request->is(array('post', 'put'))) {
            if (!empty($_FILES['document_file']['name'])) {
                $upload = $this->_uploadFile($_FILES['document_file'], 'documents', $id);
                if ($upload) {
                    $this->request->data['Document']['file_name'] = $upload['file_name'];
                    $this->request->data['Document']['file_path'] = $upload['file_path'];
                    $this->request->data['Document']['file_type'] = $upload['file_type'];
                }
            }
            if ($this->Document->save($this->request->data)) {
                $this->Session->setFlash('已更新', 'default', array('class' => 'alert-success'));
                return $this->redirect(array('action' => 'view', $id));
            }
        } else {
            $this->request->data = $doc;
        }
        $this->_setFormLists();
    }

    public function revise($id = null) {
        $doc = $this->Document->findById($id);
        if (!$doc) {
            throw new NotFoundException('文件不存在');
        }
        if ($this->request->is('post')) {
            $rev = (int)$doc['Document']['revision'] + 1;
            $versionLetter = chr(ord('A') + floor(($rev - 1) / 10));
            $versionNum = $rev % 10;
            $newVersion = $versionLetter . '/' . $versionNum;

            $this->DocumentRevision->create();
            $this->DocumentRevision->save(array(
                'id' => CakeText::uuid(),
                'document_id' => $id,
                'version' => $doc['Document']['version'],
                'revision' => $doc['Document']['revision'],
                'file_path' => $doc['Document']['file_path'],
                'file_name' => $doc['Document']['file_name'],
                'change_reason' => $this->request->data['Document']['change_reason'],
                'created_by' => $this->Session->read('User.id'),
                'created' => date('Y-m-d H:i:s')
            ));

            $update = array(
                'id' => $id,
                'version' => $newVersion,
                'revision' => $rev,
                'status' => 'draft',
                'change_reason' => $this->request->data['Document']['change_reason'],
                'publish' => 0
            );
            if (!empty($_FILES['document_file']['name'])) {
                $upload = $this->_uploadFile($_FILES['document_file'], 'documents', $id);
                if ($upload) {
                    $update['file_name'] = $upload['file_name'];
                    $update['file_path'] = $upload['file_path'];
                    $update['file_type'] = $upload['file_type'];
                }
            }
            $this->Document->save($update, false);
            $this->Session->setFlash('已发起修订，版本 ' . $newVersion, 'default', array('class' => 'alert-success'));
            return $this->redirect(array('action' => 'view', $id));
        }
        $this->set('doc', $doc);
    }

    public function submit_review($id = null) {
        $doc = $this->Document->findById($id);
        if ($doc) {
            $this->Document->id = $id;
            $this->Document->saveField('status', 'reviewing');
            $this->Session->setFlash('已提交审核', 'default', array('class' => 'alert-success'));
        }
        $this->redirect(array('action' => 'view', $id));
    }

    public function approve($approvalId = null) {
        if ($this->request->is('post')) {
            $status = $this->request->data['Approval']['status'];
            $comments = $this->request->data['Approval']['comments'];
            if ($this->Workflow->processApproval($approvalId, $status, $comments)) {
                $approval = $this->Approval->findById($approvalId);
                if ($status === 'approved' && $approval['Approval']['model_name'] === 'Document') {
                    $doc = $this->Document->findById($approval['Approval']['record']);
                    if ($this->Workflow->isFullyApproved('Document', $approval['Approval']['record'], $doc['Document']['level'])) {
                        $this->Document->id = $approval['Approval']['record'];
                        $this->Document->save(array('status' => 'published', 'publish' => 1), false);
                    }
                }
                $this->Session->setFlash('审批已处理', 'default', array('class' => 'alert-success'));
            } else {
                $this->Session->setFlash('审批失败', 'default', array('class' => 'alert-danger'));
            }
        }
        $this->redirect($this->referer());
    }

    public function download($id = null) {
        $doc = $this->Document->findById($id);
        if (!$doc || empty($doc['Document']['file_path'])) {
            throw new NotFoundException('文件不存在');
        }
        $path = WWW_ROOT . $doc['Document']['file_path'];
        if (!file_exists($path)) {
            throw new NotFoundException('文件未找到');
        }
        $this->response->file($path, array('download' => true, 'name' => $doc['Document']['file_name']));
        return $this->response;
    }

    protected function _setFormLists() {
        $this->set('categories', $this->DocCategory->find('list', array(
            'conditions' => array('DocCategory.soft_delete' => 0),
            'order' => array('DocCategory.level' => 'ASC', 'DocCategory.sort_order' => 'ASC')
        )));
        $this->set('templates', $this->DocTemplate->find('list', array('conditions' => array('DocTemplate.soft_delete' => 0))));
        $this->set('departments', $this->Department->find('list', array('conditions' => array('Department.soft_delete' => 0))));
    }

    protected function _employeeToUser($employeeId) {
        $User = ClassRegistry::init('User');
        $u = $User->find('first', array(
            'conditions' => array('User.employee_id' => $employeeId),
            'fields' => array('User.id'),
            'recursive' => -1
        ));
        return $u ? $u['User']['id'] : null;
    }
}
