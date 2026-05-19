<?php
App::uses('AppCrudController', 'Controller');
class AuditPlansController extends AppCrudController {
    public $uses = array('AuditPlan');
}
