<?php
App::uses('AppController', 'Controller');
App::uses('Folder', 'Utility');
App::uses('File', 'Utility');


class ChdMrm0Child1V1sController extends AppController {

public $components = array('Paginator');

public function _get_system_table_id() {
	$this->loadModel('SystemTable');
	$this->SystemTable->recursive = -1;
	$systemTableId = $this->SystemTable->find('first', array('conditions' => array('SystemTable.system_name' => $this->request->params['controller'])));
	return $systemTableId['SystemTable']['id'];
}

public function _commons($creator = null){

	if($this->action == 'view' || $this->action == 'edit' )$this->set('approvals',$this->get_approvals());

    $companies = $this->ChdMrm0Child1V1->Company->find('list',array('conditions'=>array('Company.publish'=>1,'Company.soft_delete'=>0)));
	$preparedBies = $approvedBies = $this->ChdMrm0Child1V1->PreparedBy->find('list',array('conditions'=>array('PreparedBy.publish'=>1,'PreparedBy.soft_delete'=>0)));
	$createdBies = $modifiedBies = $this->ChdMrm0Child1V1->CreatedBy->find('list',array('conditions'=>array('CreatedBy.publish'=>1,'CreatedBy.soft_delete'=>0)));		
	$count = $this->ChdMrm0Child1V1->find('count');
	$published = $this->ChdMrm0Child1V1->find('count',array('conditions'=>array('ChdMrm0Child1V1.publish'=>1)));
	$unpublished = $this->ChdMrm0Child1V1->find('count',array('conditions'=>array('ChdMrm0Child1V1.publish'=>0)));

	$customTable = $this->ChdMrm0Child1V1->CustomTable->find('first',array('recursive'=>-1,'conditions'=>array('CustomTable.table_name'=>$this->request->controller)));

	$this->set(compact('companies', 'preparedBies', 'approvedBies', 'createdBies', 'modifiedBies','count','publish','unpublished','customTable'));


			$assignedTos = $this->ChdMrm0Child1V1->AssignedTo->find('list',array('conditions'=> array ('AssignedTo.publish' => 1,'AssignedTo.soft_delete'=>0)));		$this->set('customArray',$this->ChdMrm0Child1V1->customArray);		$this->set(compact('assignedTos'));
		if($this->request->params['named']['approval_id']){						
			$this->get_approval($this->request->params['named']['approval_id'],$creator);
			$this->_get_approval_comnments($this->request->params['named']['approval_id'],$creator);
		}
		$this->_get_approver_list();	

	}

public function index($parent_id = null) {	
	$this->_commons($this->Session->read('User.id'));			
	$chdMrm0Child1V1s = $this->ChdMrm0Child1V1->find('all',array('conditions'=>array('ChdMrm0Child1V1.parent_id'=>$parent_id)));
	$this->set(compact('chdMrm0Child1V1s'));
}

public function add($i =  null) {
	if($i)$i = $i+1;
	else $i = 1;
	$this->set('i',$i);
	$this->_commons($this->Session->read('User.id'));
			
}

public function edit($id = null, $i = null) {
	$this->_commons($this->Session->read('User.id'));
	
	$options = array('conditions' => array('ChdMrm0Child1V1.' . $this->ChdMrm0Child1V1->primaryKey => $id));
	$this->request->data = $this->ChdMrm0Child1V1->find('first', $options);	
	$this->set('i',$i);
			
}

public function add_fields($i = null) {		
	$this->_commons($this->Session->read('User.id'));
	$i = $i+1;
	$this->set('i',$i);
}

public function delete($id = null) {
	if(!empty($id)){
		$options = array('conditions' => array('ChdCustomerComplaints0Child1V1.' . $this->ChdMrm0Child1V1->primaryKey => $id));
		$record = $this->ChdCustomerComplaints0Child1V1->find('first', $options);
		if(!empty($record)){
			$path = Configure::read('files'). DS . 'record_files' . DS . $record['ChdCustomerComplaints0Child1V1']['custom_table_id'] . DS . $record['ChdCustomerComplaints0Child1V1']['id'];
	        $cdirToDelete = new Folder($path);
	        if($path != Configure::read('files'). DS . 'record_files' . DS . $record['ChdCustomerComplaints0Child1V1']['custom_table_id'])$cdirToDelete->delete();
		}
        $this->ChdMrm0Child1V1->delete($id);
	}
	$this->Session->setFlash(__('Record Deleted'));
	$this->redirect($this->referer());
}
}