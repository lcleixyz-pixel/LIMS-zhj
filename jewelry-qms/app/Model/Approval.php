<?php
App::uses('AppModel', 'Model');

class Approval extends AppModel {
    public $belongsTo = array(
        'User' => array('foreignKey' => 'user_id')
    );
}
