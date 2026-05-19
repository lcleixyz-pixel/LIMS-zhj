<?php
App::uses('AppCrudController', 'Controller');
class AuditFindingsController extends AppCrudController {
    public $uses = array('AuditFinding');
}
