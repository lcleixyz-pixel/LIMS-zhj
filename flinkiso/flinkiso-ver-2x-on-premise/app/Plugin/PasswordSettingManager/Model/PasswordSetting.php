<?php
App::uses('AppModel', 'Model');
/**
 * Branch Model
 *
 * @property SystemTable $SystemTable
 * @property MasterListOfFormat $MasterListOfFormat
 * @property BranchBenchmark $BranchBenchmark
 * @property CourierRegister $CourierRegister
 * @property Customer $Customer
 * @property DataBackUp $DataBackUp
 * @property DeliveryChallan $DeliveryChallan
 * @property Device $Device
 * @property DocumentAmendmentRecordSheet $DocumentAmendmentRecordSheet
 * @property Employee $Employee
 * @property FireSafetyEquipmentList $FireSafetyEquipmentList
 * @property HousekeepingChecklist $HousekeepingChecklist
 * @property Product $Product
 * @property Report $Report
 * @property User $User
 */
class PasswordSetting extends PasswordSettingManagerAppModel {

/**
 * Validation rules
 *
 * @var array
 */
	public $validate = array(
		'sr_no' => array(
			'numeric' => array(
				'rule' => array('numeric'),
			),
		),
		'user_id' => array(
			'uuid' => array(
				'rule' => array('uuid'),
			),
		),
		'branchid' => array(
			'uuid' => array(
				'rule' => array('uuid'),
			),
		),
		'departmentid' => array(
			'uuid' => array(
				'rule' => array('uuid'),
			),
		),
		'created_by' => array(
			'uuid' => array(
				'rule' => array('uuid'),
			),
		),
		'modified_by' => array(
			'uuid' => array(
				'rule' => array('uuid'),
			),
		),
	);
}
?>