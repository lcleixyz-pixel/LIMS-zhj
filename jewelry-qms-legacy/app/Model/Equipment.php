<?php
App::uses('AppModel', 'Model');

class Equipment extends AppModel {
    public $useTable = 'equipments';
    public $displayField = 'name';
    public $belongsTo = array('Department' => array('foreignKey' => 'department_id'));
    public $hasMany = array('Calibration', 'EquipmentMaintenance');
}
