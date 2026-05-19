<?php
App::uses('AppCrudController', 'Controller');
class CustomerComplaintsController extends AppCrudController {
    public $uses = array('CustomerComplaint');
}
