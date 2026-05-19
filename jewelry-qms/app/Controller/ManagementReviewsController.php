<?php
App::uses('AppCrudController', 'Controller');
class ManagementReviewsController extends AppCrudController {
    public $uses = array('ManagementReview');
}
