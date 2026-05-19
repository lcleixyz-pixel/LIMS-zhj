<?php
App::uses('AppController', 'Controller');

class UsersController extends AppController {

    public $uses = array('User', 'Employee', 'Department', 'Company', 'UserSession');

    public function beforeFilter() {
        parent::beforeFilter();
        $this->AuthAllowed = array('login', 'logout');
        if (in_array($this->action, array('login', 'logout'))) {
            return;
        }
    }

    public function login() {
        $this->layout = 'login';
        if ($this->request->is('post')) {
            $user = $this->User->find('first', array(
                'conditions' => array(
                    'User.username' => $this->request->data['User']['username'],
                    'User.publish' => 1,
                    'User.soft_delete' => 0
                ),
                'recursive' => -1
            ));
            if ($user && password_verify($this->request->data['User']['password'], $user['User']['password'])) {
                $this->User->id = $user['User']['id'];
                $this->User->saveField('last_login', date('Y-m-d H:i:s'));
                $sessionId = CakeText::uuid();
                $this->UserSession->create();
                $this->UserSession->save(array(
                    'id' => $sessionId,
                    'user_id' => $user['User']['id'],
                    'start_time' => date('Y-m-d H:i:s'),
                    'ip_address' => env('REMOTE_ADDR')
                ));
                $this->Session->write('User', array(
                    'id' => $user['User']['id'],
                    'username' => $user['User']['username'],
                    'name' => $user['User']['name'],
                    'role' => $user['User']['role'],
                    'company_id' => $user['User']['company_id'],
                    'department_id' => $user['User']['department_id'],
                    'employee_id' => $user['User']['employee_id'],
                    'is_mr' => $user['User']['is_mr'],
                    'user_session_id' => $sessionId
                ));
                $_SESSION['User'] = $this->Session->read('User');
                return $this->redirect(array('controller' => 'dashboards', 'action' => 'index'));
            }
            $this->Session->setFlash('用户名或密码错误', 'default', array('class' => 'alert-danger'));
        }
    }

    public function logout() {
        if ($this->Session->read('User.user_session_id')) {
            $this->UserSession->id = $this->Session->read('User.user_session_id');
            $this->UserSession->saveField('end_time', date('Y-m-d H:i:s'));
        }
        $this->Session->destroy();
        return $this->redirect(array('action' => 'login'));
    }

    public function index() {
        $this->paginate = array(
            'conditions' => array('User.soft_delete' => 0),
            'limit' => 20,
            'order' => array('User.name' => 'ASC')
        );
        $this->set('users', $this->paginate('User'));
    }

    public function add() {
        if ($this->request->is('post')) {
            $this->request->data['User']['id'] = CakeText::uuid();
            $this->request->data['User']['company_id'] = $this->Session->read('User.company_id');
            $this->request->data['User']['password'] = password_hash($this->request->data['User']['password'], PASSWORD_DEFAULT);
            if ($this->User->save($this->request->data)) {
                $this->Session->setFlash('用户已创建', 'default', array('class' => 'alert-success'));
                return $this->redirect(array('action' => 'index'));
            }
            $this->Session->setFlash('保存失败', 'default', array('class' => 'alert-danger'));
        }
        $this->set('departments', $this->Department->find('list', array('conditions' => array('Department.soft_delete' => 0))));
        $this->set('employees', $this->Employee->find('list', array('conditions' => array('Employee.soft_delete' => 0))));
    }

    public function edit($id = null) {
        if (!$id || !$this->User->exists($id)) {
            throw new NotFoundException('用户不存在');
        }
        if ($this->request->is(array('post', 'put'))) {
            if (!empty($this->request->data['User']['password'])) {
                $this->request->data['User']['password'] = password_hash($this->request->data['User']['password'], PASSWORD_DEFAULT);
            } else {
                unset($this->request->data['User']['password']);
            }
            if ($this->User->save($this->request->data)) {
                $this->Session->setFlash('已更新', 'default', array('class' => 'alert-success'));
                return $this->redirect(array('action' => 'index'));
            }
        } else {
            $this->request->data = $this->User->find('first', array('conditions' => array('User.id' => $id)));
            unset($this->request->data['User']['password']);
        }
        $this->set('departments', $this->Department->find('list', array('conditions' => array('Department.soft_delete' => 0))));
        $this->set('employees', $this->Employee->find('list', array('conditions' => array('Employee.soft_delete' => 0))));
    }

    public function change_password() {
        if ($this->request->is('post')) {
            $user = $this->User->find('first', array('conditions' => array('User.id' => $this->Session->read('User.id')), 'recursive' => -1));
            if (password_verify($this->request->data['User']['old_password'], $user['User']['password'])) {
                $this->User->id = $user['User']['id'];
                $this->User->saveField('password', password_hash($this->request->data['User']['new_password'], PASSWORD_DEFAULT));
                $this->Session->setFlash('密码已修改', 'default', array('class' => 'alert-success'));
                return $this->redirect(array('controller' => 'dashboards', 'action' => 'index'));
            }
            $this->Session->setFlash('原密码错误', 'default', array('class' => 'alert-danger'));
        }
    }
}
