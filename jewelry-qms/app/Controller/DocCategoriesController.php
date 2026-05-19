<?php
App::uses('AppCrudController', 'Controller');
class DocCategoriesController extends AppCrudController {
    public $uses = array('DocCategory');
}
