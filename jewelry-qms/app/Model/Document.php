<?php
App::uses('AppModel', 'Model');

class Document extends AppModel {
    public $displayField = 'title';
    public $belongsTo = array(
        'DocCategory' => array('foreignKey' => 'category_id'),
        'Department' => array('foreignKey' => 'department_id'),
        'DocTemplate' => array('foreignKey' => 'template_id')
    );
    public $hasMany = array(
        'DocumentRevision' => array('foreignKey' => 'document_id', 'order' => array('DocumentRevision.created' => 'DESC'))
    );
}
