<?php
//App::uses('Component', 'Controller');
App::import('Controller','users');
class PasswordSettingsController extends PasswordSettingManagerAppController {

public function _get_system_table_id() {

        $this->loadModel('SystemTable');
        $this->SystemTable->recursive = -1;
        $systemTableId = $this->SystemTable->find('first', array('conditions' => array('SystemTable.system_name' => $this->request->params['controller'])));
        return $systemTableId['SystemTable']['id'];
    }
    
    //Function to set password policy
    function password_policy($model = null , $id = null, $edit = null){
        $this->layout = 'ajax';
        $this->loadModel('PasswordSetting');
        $this->PasswordSetting->recursive =-1;
        $this->loadModel('Company');
        $this->Company->recursive =-1;
        if ($this->request->is('post') || $this->request->is('put')) {

            $this->request->data['Company']['id']=$this->Session->read('User.company_id');
            $this->request->data['Company']['activate_password_setting']=$this->request->data['PasswordSetting']['activate_password_setting'];
            $this->request->data['Company']['two_way_authentication']=$this->request->data['PasswordSetting']['two_way_authentication'];
            $this->Company->save($this->request->data['Company'], false);
            if($this->request->data['PasswordSetting']['activate_password_setting'] == 1){
                if(isset($id)){
                    $this->request->data['PasswordSetting']['id'] = $id;
                }


                $this->PasswordSetting->save($this->request->data);
                $this->Session->setFlash(__('Password Policy has been activated.'), 'default', array('class' => 'alert-success'));
                return $this->redirect(array('controller'=>'settings', 'action' => 'password_setting', $this->Session->read('User.company_id'),'plugin' => false));
            } else {
                  $this->Session->setFlash(__('Password Policy has been deactivated.'), 'default', array('class' => 'alert-success'));
                    return $this->redirect(array('controller'=>'settings', 'action' => 'password_setting', $this->Session->read('User.company_id'),'plugin' => false));
            }

        }
        $this->data =  $this->PasswordSetting->find('first');
        $user_company_id = $this->Session->read('User.company_id');
        $new_array= array();
        $new_array =  $this->data;
        $companies = $this->Company->find('first', array('recursive'=>-1, 'fields'=>array('Company.id','Company.activate_password_setting','Company.two_way_authentication'), 'conditions'=>array('id'=>$user_company_id)));
        
        $new_array['PasswordSetting']['activate_password_setting']=$companies['Company']['activate_password_setting'];
        $twoway=$companies['Company']['two_way_authentication'];
        
        $this->set('actPwdSetting', $new_array['PasswordSetting']['activate_password_setting']);
        $this->set('twoway', $twoway);
        $this->set('PasswordSetting',$this->data['PasswordSetting']);

        

    }

    //Function to display password policy and apply client-side password setting validation
    function display_policy(){        
        $this->loadModel('PasswordSetting');
        $this->loadModel('Company');
        $this->PasswordSetting->recursive =-1;
        $this->Company->recursive =-1;
        $password_setting =  $this->PasswordSetting->find('first');
        $companies = $this->Company->find('first');
        $password_setting['PasswordSetting']['activate_password_setting'] = $companies['Company']['activate_password_setting'];        
        return $password_setting['PasswordSetting'];

    }

    //Function to check server-side password setting validation
    function check_password_validation($password, $old_passowrd, $username){ 
        
            $password_setting = $this->display_policy();

            if($password_setting['password_same_username'] == 1){


                    if($password == $username){
                       $result['valid'] = 0;
                       $result['message'] = "Password should not be same as username, please try again";
                       return $result;
                    }
            }

            if($password_setting['password_min_len']){
                        if(strlen($password) < $password_setting['password_min_len']){
                            $result['valid'] = 0;
                            $result['message'] = 'Password should be atleast '.$password_setting['password_min_len'].' character long, please try again';
                            return $result;
                        }


            }

            if($password_setting['password_max_len']){
                       if(strlen($password) > $password_setting['password_max_len']){
                            $result['valid'] = 0;
                            $result['message'] = 'Password should be maximum '.$password_setting['password_max_len'].' character long, please try again';
                            return $result;
                        }
            }

            if($password_setting['password_special_character'] == 1){
                        if(!preg_match('/[^a-z_\-0-9]/i', $password)){
                            $result['valid'] = 0;
                            $result['message'] = 'Password must contain special characters, please try again';
                            return $result;
                        }
            }else if($password_setting['password_special_character'] == 2){
                        if(preg_match('/[^a-z_\-0-9]/i', $password)){
                            $result['valid'] = 0;
                            $result['message'] = 'Password should not contain any special characters, please try again';
                            return $result;
                        }
            }

            if($password_setting['password_uppercase_start'] == 1){
                        $upper_case_pwd = str_split($password, 1);
                        preg_match_all("/[A-Z]/", $upper_case_pwd[0], $pwd_caps_match);
                        if(!count($pwd_caps_match[0])){
                            $result['valid'] = 0;
                            $result['message'] = 'Password must start with UPPERCASE character';
                            return $result;

                        }
            } else if($password_setting['password_uppercase_start'] == 2){
                        $upper_case_pwd = str_split($password, 1);
                        preg_match_all("/[A-Z]/", $upper_case_pwd[0], $pwd_caps_match);
                        if(count($pwd_caps_match[0])){
                            $result['valid'] = 0;
                            $result['message'] = 'Password should not start with UPPERCASE character';
                            return $result;
                        }

            }

            if($password_setting['password_uppercase_length']){

                        preg_match_all("/[A-Z]/", $password, $caps_match);
                        $caps_count = count($caps_match [0]);
                        if($caps_count < $password_setting['password_uppercase_length']){
                            $result['valid'] = 0;
                            $result['message'] = 'Password should contain at least '. $password_setting['password_uppercase_length'].' UPPERCASE characters';
                            return $result;

                        }
            }
            if($password_setting['password_repeat']){

                        $old_pwd = json_decode($old_passowrd);
                        $encoded_pwd = Security::hash($password,'md5',true);
                        if(is_array($old_pwd)){
                            if(in_array($encoded_pwd, $old_pwd)){
                                $result['valid'] = 0;
                                $result['message'] = 'Last '.$password_setting['password_repeat'].' passwords can not be repeated ';
                                return $result;

                            }
                        }else{
                            $result['valid'] = 1;
                        }                        

            }
            $result['valid'] = 1;
            return $result;
    }

    //Function to get password repeat length
    function get_password_repeat_len(){

        $this->loadModel('PasswordSetting');
        $this->PasswordSetting->recursive =-1;
        $password_setting =  $this->PasswordSetting->find('first');
        return $password_setting['PasswordSetting']['password_repeat'];
    }

    //Function to get password change remind
    function get_password_change_remind($pwd_last_modified=null){

        $pwd_last_modified= urldecode($pwd_last_modified); // die;
        $this->loadModel('PasswordSetting');
        $this->PasswordSetting->recursive =-1;
        $password_setting =  $this->PasswordSetting->find('first');
        if($password_setting['PasswordSetting']['password_change_remind']>0){
            if($pwd_last_modified!=null){
                $diff = abs(strtotime(date('Y-m-d H:i:s'))-strtotime($pwd_last_modified));
                $years = floor($diff / (365*60*60*24));
                $months = floor(($diff) / (30*60*60*24));
                $days = floor($diff/ (60*60*24));

                if($password_setting['PasswordSetting']['password_change_remind'] == 1 && $days>7){

                    $result['valid']=false;
                    $result['msg']="Password should be change weekly, Please change your password first";
                    return $result;
                }else  if($password_setting['PasswordSetting']['password_change_remind'] == 2 && $days>30){
                    $result['valid']=false;
                    $result['msg']="Password should be change monthly, Please change your password first";
                    return $result;
                }else  if($password_setting['PasswordSetting']['password_change_remind'] == 3 && $days>365){
                    $result['valid']=false;
                    $result['msg']="Password should be change yearly, Please change your password first";
                    return $result;
                }
            }
        }
         $result['valid']=true;
          return $result;
    }
}
?>
