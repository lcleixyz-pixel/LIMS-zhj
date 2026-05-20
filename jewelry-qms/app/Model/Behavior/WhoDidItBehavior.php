<?php
class WhoDidItBehavior extends ModelBehavior {

    protected $_defaults = array(
        'auth_session' => 'Auth',
        'user_model' => 'User',
        'created_by_field' => 'created_by',
        'modified_by_field' => 'modified_by',
        'department_id_field' => 'departmentid',
        'company_id_field' => 'company_id',
        'auto_bind' => true
    );

    public function setup(Model $model, $config = array()) {
        $this->settings[$model->alias] = $this->_defaults;
        $this->settings[$model->alias] = array_merge($this->settings[$model->alias], (array)$config);

        $hasFieldCreatedBy = $model->hasField($this->settings[$model->alias]['created_by_field']);
        $hasFieldModifiedBy = $model->hasField($this->settings[$model->alias]['modified_by_field']);

        $this->settings[$model->alias]['has_created_by'] = $hasFieldCreatedBy;
        $this->settings[$model->alias]['has_modified_by'] = $hasFieldModifiedBy;

        if ($this->settings[$model->alias]['auto_bind']) {
            if ($hasFieldCreatedBy) {
                $model->bindModel(array('belongsTo' => array(
                    'CreatedBy' => array(
                        'className' => $this->settings[$model->alias]['user_model'],
                        'foreignKey' => $this->settings[$model->alias]['created_by_field'],
                        'fields' => array('id', 'name'),
                    )
                )), false);
            }
            if ($hasFieldModifiedBy) {
                $model->bindModel(array('belongsTo' => array(
                    'ModifiedBy' => array(
                        'className' => $this->settings[$model->alias]['user_model'],
                        'foreignKey' => $this->settings[$model->alias]['modified_by_field'],
                        'fields' => array('id', 'name'),
                    )
                )), false);
            }
        }
    }

    public function beforeSave(Model $model, $options = array()) {
        if (!$this->settings[$model->alias]['has_created_by'] && !$this->settings[$model->alias]['has_modified_by']) {
            return true;
        }

        if (!isset($_SESSION['User'])) {
            return true;
        }

        $userId = $_SESSION['User']['id'];
        $deptId = isset($_SESSION['User']['department_id']) ? $_SESSION['User']['department_id'] : null;
        $compId = isset($_SESSION['User']['company_id']) ? $_SESSION['User']['company_id'] : null;

        if (!$userId) {
            return true;
        }

        $data = array($this->settings[$model->alias]['modified_by_field'] => $userId);
        if (!$model->exists()) {
            $data[$this->settings[$model->alias]['created_by_field']] = $userId;
            if ($deptId !== null) {
                $data[$this->settings[$model->alias]['department_id_field']] = $deptId;
            }
            if ($compId !== null) {
                $data[$this->settings[$model->alias]['company_id_field']] = $compId;
            }
        }
        $model->set($data);
        return true;
    }
}
