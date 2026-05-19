<?php
App::uses('AppCrudController', 'Controller');
class SuppliersController extends AppCrudController {
    public $uses = array('Supplier');
}
