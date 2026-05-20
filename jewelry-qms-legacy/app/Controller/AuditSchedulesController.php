<?php
App::uses('AppCrudController', 'Controller');
class AuditSchedulesController extends AppCrudController {
    public $uses = array('AuditSchedule');
}
