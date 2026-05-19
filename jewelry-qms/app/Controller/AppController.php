<?php
App::uses('Controller', 'Controller');
App::uses('Folder', 'Utility');
App::uses('File', 'Utility');

class AppController extends Controller {

    public $components = array('Session', 'RequestHandler', 'Workflow');
    public $helpers = array('Html', 'Form', 'Session', 'Paginator', 'Time');

    public function beforeFilter() {
        if (!$this->Session->read('User.id') && $this->name !== 'CakeError') {
            $isLogin = ($this->name === 'Users' && $this->action === 'login');
            if (!$isLogin) {
                $this->redirect(array('controller' => 'users', 'action' => 'login'));
            }
        }
        if ($this->request->is('post') || $this->request->is('put')) {
            array_walk_recursive($this->request->data, function (&$val) {
                if (is_string($val)) {
                    $val = trim($val);
                }
            });
        }
    }

    public function beforeRender() {
        if ($this->Session->read('User.id')) {
            $this->_loadCommonLists();
            $this->_loadNotifications();
            $this->set('companyName', $this->_getCompanyName());
        }
        $this->set('controllerName', $this->request->params['controller']);
        $this->set('actionName', $this->action);
        $this->set('docLevels', Configure::read('QMS.docLevels'));
    }

    protected function _getCompanyName() {
        $this->loadModel('Company');
        $c = $this->Company->find('first', array(
            'conditions' => array('Company.id' => $this->Session->read('User.company_id')),
            'recursive' => -1
        ));
        return $c ? $c['Company']['name'] : Configure::read('QMS.title');
    }

    protected function _loadCommonLists() {
        $this->loadModel('Department');
        $this->loadModel('Employee');
        $this->loadModel('User');
        $this->set('departments', $this->Department->find('list', array(
            'conditions' => array('Department.publish' => 1, 'Department.soft_delete' => 0)
        )));
        $this->set('employees', $this->Employee->find('list', array(
            'conditions' => array('Employee.publish' => 1, 'Employee.soft_delete' => 0)
        )));
        $this->set('approvers', $this->User->find('list', array(
            'conditions' => array('User.is_approver' => 1, 'User.publish' => 1, 'User.soft_delete' => 0),
            'fields' => array('User.id', 'User.name')
        )));
    }

    protected function _loadNotifications() {
        $this->loadModel('NotificationUser');
        $count = $this->NotificationUser->find('count', array(
            'conditions' => array(
                'NotificationUser.user_id' => $this->Session->read('User.id'),
                'NotificationUser.status' => 0
            )
        ));
        $this->set('notificationCount', $count);
    }

    protected function _checkAccess($action = null) {
        if ($this->Session->read('User.role') === 'admin') {
            return true;
        }
        return true;
    }

    public function delete($id = null) {
        $model = $this->modelClass;
        $this->$model->id = $id;
        $this->$model->save(array('soft_delete' => 1), false);
        $this->Session->setFlash('已删除', 'default', array('class' => 'alert-success'));
        $this->redirect(array('action' => 'index'));
    }

    public function restore($id = null) {
        $model = $this->modelClass;
        $this->$model->id = $id;
        $this->$model->save(array('soft_delete' => 0), false);
        $this->Session->setFlash('已恢复', 'default', array('class' => 'alert-success'));
        $this->redirect(array('action' => 'index'));
    }

    protected function _uploadFile($file, $subdir, $recordId) {
        if (empty($file['name']) || $file['error'] !== UPLOAD_ERR_OK) {
            return false;
        }
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $allowed = array('doc', 'docx', 'pdf', 'xls', 'xlsx');
        if (!in_array(strtolower($ext), $allowed)) {
            return false;
        }
        $companyId = $this->Session->read('User.company_id');
        $dir = WWW_ROOT . 'files' . DS . $companyId . DS . $subdir . DS . $recordId . DS;
        if (!is_dir($dir)) {
            new Folder($dir, true);
        }
        $safeName = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $file['name']);
        $path = $dir . $safeName;
        if (move_uploaded_file($file['tmp_name'], $path)) {
            return array('file_name' => $file['name'], 'file_path' => 'files/' . $companyId . '/' . $subdir . '/' . $recordId . '/' . $safeName, 'file_type' => $ext);
        }
        return false;
    }
}
