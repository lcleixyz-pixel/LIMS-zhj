<?php
App::uses('AppController', 'Controller');

class AppCrudController extends AppController {

    public $paginate = array('limit' => 20, 'order' => array('created' => 'DESC'));

    public function index() {
        $model = $this->modelClass;
        $this->paginate['conditions'] = array($model . '.soft_delete' => 0);
        $this->set(Inflector::variable(Inflector::pluralize($model)), $this->paginate($model));
        $this->set('pageTitle', $this->_pageTitle());
    }

    public function view($id = null) {
        $model = $this->modelClass;
        $record = $this->$model->find('first', array('conditions' => array($model . '.id' => $id)));
        if (!$record) {
            throw new NotFoundException('记录不存在');
        }
        $this->set(Inflector::variable($model), $record);
        $this->set('pageTitle', $this->_pageTitle() . ' - 详情');
    }

    public function add() {
        $model = $this->modelClass;
        if ($this->request->is('post')) {
            $this->request->data[$model]['id'] = CakeText::uuid();
            if ($this->$model->save($this->request->data)) {
                $this->Session->setFlash('保存成功', 'default', array('class' => 'alert-success'));
                return $this->redirect(array('action' => 'index'));
            }
            $this->Session->setFlash('保存失败', 'default', array('class' => 'alert-danger'));
        }
        $this->set('pageTitle', $this->_pageTitle() . ' - 新增');
    }

    public function edit($id = null) {
        $model = $this->modelClass;
        if (!$id || !$this->$model->exists($id)) {
            throw new NotFoundException('记录不存在');
        }
        if ($this->request->is(array('post', 'put'))) {
            if ($this->$model->save($this->request->data)) {
                $this->Session->setFlash('已更新', 'default', array('class' => 'alert-success'));
                return $this->redirect(array('action' => 'index'));
            }
        } else {
            $this->request->data = $this->$model->find('first', array('conditions' => array($model . '.id' => $id)));
        }
        $this->set('pageTitle', $this->_pageTitle() . ' - 编辑');
    }

    protected function _pageTitle() {
        return Inflector::humanize(Inflector::underscore($this->modelClass));
    }
}
