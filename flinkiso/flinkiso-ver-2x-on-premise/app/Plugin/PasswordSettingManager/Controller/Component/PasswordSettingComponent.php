<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */
App::uses('Component', 'Controller');
App::uses('CakeSession', 'Model/Datasource');
class PasswordSettingComponent extends Component {
       
  ///  public $uses = array('PasswordSettingManager.PasswordSetting', 'Company');
//    public $controller = null;
//	public $settings = array();
//
//        public function __construct(ComponentCollection $collection, $settings = array()) {
//		parent::__construct($collection, $settings);
//		//$this->controller = $collection->getController();
//		//$this->settings = $settings;
//	}

    
    function password_policy(){
 

        $this->loadModel('PasswordSetting');
       $this->PasswordSetting->recursive =-1;
       $this->loadModel('Company');
        $this->Company->recursive =-1;
        if ($this->request->is('post') || $this->request->is('put')) {
            
            $this->request->data['Company']['id']=$this->Session->read('User.company_id');
            $this->request->data['Company']['activate_password_setting']=$this->request->data['PasswordSetting']['activate_password_setting'];
            $this->Company->save($this->request->data['Company'], false);
            if($this->request->data['PasswordSetting']['activate_password_setting'] == 1){
                if(isset($id)){
                    $this->request->data['PasswordSetting']['id'] = $id;
                }
               
                
                $this->PasswordSetting->save($this->request->data);
                $this->Session->setFlash(__('Password policy setup done successfully.'), 'default', array('class' => 'alert-success'));
                $this->redirect(array('controller' => 'users', 'action' => 'dashboard'));
            }
          
        }
        $this->data =  $this->PasswordSetting->find('first');
        $user_company_id = $this->Session->read('User.company_id');
        $new_array= array();
        $new_array =  $this->data;
        $companies = $this->Company->find('first', array('conditions'=>array('id'=>$user_company_id)));
        $new_array['PasswordSetting']['activate_password_setting']=$companies['Company']['activate_password_setting']; 
        $this->set('PasswordSetting', $new_array['PasswordSetting']);
    }
}