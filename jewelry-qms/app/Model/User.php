<?php
App::uses('AppModel', 'Model');

class User extends AppModel {
    public $displayField = 'name';
    public $belongsTo = array(
        'Employee' => array('foreignKey' => 'employee_id'),
        'Department' => array('foreignKey' => 'department_id'),
        'Company' => array('foreignKey' => 'company_id')
    );
}
