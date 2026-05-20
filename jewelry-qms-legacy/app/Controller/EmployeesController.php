<?php
App::uses('AppCrudController', 'Controller');
class EmployeesController extends AppCrudController {
    public $uses = array('Employee');
}
