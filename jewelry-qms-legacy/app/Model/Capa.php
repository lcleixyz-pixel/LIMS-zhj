<?php
App::uses('AppModel', 'Model');

class Capa extends AppModel {
    public $useTable = 'capas';
    public $displayField = 'capa_number';
    public $belongsTo = array(
        'CapaSource' => array('foreignKey' => 'source_id'),
        'Employee' => array('foreignKey' => 'assigned_to')
    );
}
