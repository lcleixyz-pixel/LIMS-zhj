<?php
App::uses('AppController', 'Controller');

class DashboardsController extends AppController {

    public $uses = array('Document', 'Equipment', 'Capa', 'CustomerComplaint', 'Nonconformity', 'AuditFinding', 'Calibration', 'NotificationUser');

    public function index() {
        $companyId = $this->Session->read('User.company_id');
        $stats = array(
            'documents_total' => $this->Document->find('count', array('conditions' => array('Document.soft_delete' => 0))),
            'documents_pending' => $this->Document->find('count', array('conditions' => array('Document.status' => array('draft', 'reviewing'), 'Document.soft_delete' => 0))),
            'equipment_total' => $this->Equipment->find('count', array('conditions' => array('Equipment.soft_delete' => 0))),
            'calibration_due' => $this->Equipment->find('count', array('conditions' => array(
                'Equipment.soft_delete' => 0,
                'Equipment.calibration_required' => 1,
                'Equipment.next_calibration_date <=' => date('Y-m-d', strtotime('+30 days'))
            ))),
            'capa_open' => $this->Capa->find('count', array('conditions' => array('Capa.status !=' => 'closed', 'Capa.soft_delete' => 0))),
            'complaints_open' => $this->CustomerComplaint->find('count', array('conditions' => array('CustomerComplaint.status !=' => 'closed', 'CustomerComplaint.soft_delete' => 0))),
            'nc_open' => $this->Nonconformity->find('count', array('conditions' => array('Nonconformity.status !=' => 'closed', 'Nonconformity.soft_delete' => 0))),
            'audit_findings_open' => $this->AuditFinding->find('count', array('conditions' => array('AuditFinding.status !=' => 'closed', 'AuditFinding.soft_delete' => 0)))
        );
        $this->set('stats', $stats);

        $upcomingCalibrations = $this->Equipment->find('all', array(
            'conditions' => array(
                'Equipment.soft_delete' => 0,
                'Equipment.next_calibration_date <=' => date('Y-m-d', strtotime('+30 days'))
            ),
            'order' => array('Equipment.next_calibration_date' => 'ASC'),
            'limit' => 5,
            'recursive' => -1
        ));
        $this->set('upcomingCalibrations', $upcomingCalibrations);
    }
}
