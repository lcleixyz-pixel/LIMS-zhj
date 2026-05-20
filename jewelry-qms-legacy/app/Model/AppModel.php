<?php
App::uses('Model', 'Model');
App::uses('CakeText', 'Utility');

class AppModel extends Model {

    public $actsAs = array('WhoDidIt');

    public function beforeFind($query) {
        if (!isset($query['conditions'])) {
            $query['conditions'] = array();
        }
        if (isset($_SESSION['User']) && array_key_exists('company_id', $this->schema()) && $this->alias !== 'Company') {
            if (substr($_SERVER['REQUEST_URI'], -5) !== 'login') {
                $query['conditions'] = array_merge(
                    array($this->alias . '.company_id' => $_SESSION['User']['company_id']),
                    (array)$query['conditions']
                );
            }
        }
        return $query;
    }

    public function generateUuid() {
        return CakeText::uuid();
    }
}
