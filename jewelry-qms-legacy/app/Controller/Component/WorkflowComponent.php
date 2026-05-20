<?php
App::uses('Component', 'Controller');

class WorkflowComponent extends Component {

    public $components = array('Session');

    /**
     * 根据文件层级获取审批级数: 1-2级 手册/程序三级
     */
    public function getApprovalLevels($level) {
        $rules = Configure::read('QMS.approvalRules');
        if (isset($rules[$level])) {
            return (int)$rules[$level];
        }
        return 2;
    }

    public function createWorkflow($controller, $modelName, $recordId, $level, $preparedBy, $reviewedBy = null, $approvedBy = null) {
        $Approval = ClassRegistry::init('Approval');
        $companyId = $this->Session->read('User.company_id');
        $userId = $this->Session->read('User.id');
        $levels = $this->getApprovalLevels($level);

        if ($levels >= 1 && $preparedBy) {
            $Approval->create();
            $Approval->save(array(
                'company_id' => $companyId,
                'model_name' => $modelName,
                'controller_name' => $controller,
                'record' => $recordId,
                'user_id' => $preparedBy,
                'approval_level' => 1,
                'status' => 'approved',
                'approved_on' => date('Y-m-d H:i:s'),
                'created_by' => $userId,
                'publish' => 1,
                'soft_delete' => 0,
                'record_status' => 1
            ));
        }
        if ($levels >= 2 && $reviewedBy) {
            $Approval->create();
            $Approval->save(array(
                'company_id' => $companyId,
                'model_name' => $modelName,
                'controller_name' => $controller,
                'record' => $recordId,
                'user_id' => $reviewedBy,
                'approval_level' => 2,
                'status' => 'pending',
                'created_by' => $userId,
                'publish' => 1,
                'soft_delete' => 0,
                'record_status' => 1
            ));
        }
        if ($levels >= 3 && $approvedBy) {
            $Approval->create();
            $Approval->save(array(
                'company_id' => $companyId,
                'model_name' => $modelName,
                'controller_name' => $controller,
                'record' => $recordId,
                'user_id' => $approvedBy,
                'approval_level' => 3,
                'status' => 'pending',
                'created_by' => $userId,
                'publish' => 1,
                'soft_delete' => 0,
                'record_status' => 1
            ));
        }
    }

    public function processApproval($approvalId, $status, $comments = '') {
        $Approval = ClassRegistry::init('Approval');
        $approval = $Approval->find('first', array(
            'conditions' => array('Approval.id' => $approvalId, 'Approval.user_id' => $this->Session->read('User.id')),
            'recursive' => -1
        ));
        if (!$approval) {
            return false;
        }
        $Approval->id = $approvalId;
        $Approval->save(array(
            'status' => $status,
            'comments' => $comments,
            'approved_on' => date('Y-m-d H:i:s')
        ));
        return true;
    }

    public function isFullyApproved($modelName, $recordId, $level) {
        $Approval = ClassRegistry::init('Approval');
        $required = $this->getApprovalLevels($level);
        $approved = $Approval->find('count', array(
            'conditions' => array(
                'Approval.model_name' => $modelName,
                'Approval.record' => $recordId,
                'Approval.status' => 'approved',
                'Approval.soft_delete' => 0
            )
        ));
        return $approved >= $required;
    }
}
