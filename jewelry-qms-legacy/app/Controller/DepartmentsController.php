<?php
App::uses('AppCrudController', 'Controller');
class DepartmentsController extends AppCrudController {
    public $uses = array('Department');
}
