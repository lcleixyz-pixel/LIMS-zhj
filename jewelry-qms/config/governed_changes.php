<?php
declare(strict_types=1);

return [
    /*
     * strategy:
     * - direct: ordinary master/configuration data, direct editing is allowed.
     * - revision: controlled object, changes must create a new revision.
     * - specialized: the module already owns a complete correction workflow.
     * - correction: frozen evidence uses append-only field corrections.
     * - event: lifecycle fields use a dedicated event/transition workflow.
     */
    'subjects' => [
        'document' => [
            'strategy' => 'revision',
            'label' => '体系文件',
        ],
        'record_form_template' => [
            'strategy' => 'revision',
            'label' => '记录表格模板',
        ],
        'record_form_instance' => [
            'strategy' => 'specialized',
            'label' => '记录表格实例',
        ],
        'planning_structure' => ['strategy' => 'revision', 'label' => '体系结构'],
        'planning_source' => ['strategy' => 'revision', 'label' => '外部依据'],
        'planning_element' => ['strategy' => 'revision', 'label' => '体系要素'],
        'planning_objective' => ['strategy' => 'revision', 'label' => '质量目标'],
        'planning_responsibility' => ['strategy' => 'revision', 'label' => '职责链'],
        'planning_change_event' => ['strategy' => 'revision', 'label' => '法规变更事件'],

        'training_plan' => [
            'strategy' => 'correction',
            'label' => '培训计划',
            'model' => \app\model\TrainingPlan::class,
            'frozen_statuses' => ['approved', 'completed'],
        ],
        'training' => [
            'strategy' => 'correction',
            'label' => '培训实施',
            'model' => \app\model\Training::class,
            'frozen_statuses' => ['completed', 'cancelled'],
        ],
        'training_record' => [
            'strategy' => 'correction',
            'label' => '培训记录',
            'model' => \app\model\TrainingRecord::class,
            'parent' => [
                'model' => \app\model\Training::class,
                'table' => 'trainings',
                'foreign_key' => 'training_id',
                'frozen_statuses' => ['completed', 'cancelled'],
            ],
        ],
        'competency_record' => [
            'strategy' => 'correction',
            'label' => '能力评价记录',
            'model' => \app\model\CompetencyRecord::class,
            'always_frozen' => true,
        ],
        'calibration' => [
            'strategy' => 'correction',
            'label' => '校准记录',
            'model' => \app\model\Calibration::class,
            'always_frozen' => true,
        ],
        'equipment_maintenance' => [
            'strategy' => 'correction',
            'label' => '设备维护记录',
            'model' => \app\model\EquipmentMaintenance::class,
            'always_frozen' => true,
        ],
        'equipment_transfer' => [
            'strategy' => 'correction',
            'label' => '设备调拨记录',
            'model' => \app\model\EquipmentTransfer::class,
            'always_frozen' => true,
        ],
        'equipment_authorization' => [
            'strategy' => 'correction',
            'label' => '设备授权记录',
            'model' => \app\model\EquipmentAuthorization::class,
            'always_frozen' => true,
        ],
        'reference_material' => [
            'strategy' => 'event',
            'label' => '标准物质台账',
            'model' => \app\model\ReferenceMaterial::class,
            'protected_fields' => ['status'],
        ],
        'supplier_evaluation' => [
            'strategy' => 'correction',
            'label' => '供应商评价记录',
            'model' => \app\model\SupplierEvaluation::class,
            'always_frozen' => true,
        ],
        'employee_certificate' => [
            'strategy' => 'correction',
            'label' => '人员证书记录',
            'model' => \app\model\EmployeeCertificate::class,
            'always_frozen' => true,
        ],
        'complaint' => [
            'strategy' => 'correction',
            'label' => '投诉记录',
            'model' => \app\model\CustomerComplaint::class,
            'frozen_statuses' => ['closed'],
        ],
        'nonconformity' => [
            'strategy' => 'correction',
            'label' => '不符合记录',
            'model' => \app\model\Nonconformity::class,
            'frozen_statuses' => ['verified', 'closed'],
        ],
        'capa' => [
            'strategy' => 'correction',
            'label' => '纠正措施记录',
            'model' => \app\model\Capa::class,
            'frozen_statuses' => ['closed'],
        ],
        'audit_schedule' => [
            'strategy' => 'correction',
            'label' => '内审实施记录',
            'model' => \app\model\AuditSchedule::class,
            'frozen_statuses' => ['completed'],
        ],
        'audit_checklist' => [
            'strategy' => 'correction',
            'label' => '内审检查记录',
            'model' => \app\model\AuditChecklist::class,
            'parent' => [
                'model' => \app\model\AuditSchedule::class,
                'table' => 'audit_schedules',
                'foreign_key' => 'audit_schedule_id',
                'frozen_statuses' => ['completed'],
            ],
        ],
        'audit_finding' => [
            'strategy' => 'correction',
            'label' => '内审发现记录',
            'model' => \app\model\AuditFinding::class,
            'frozen_statuses' => ['verified', 'closed'],
            'parent' => [
                'model' => \app\model\AuditSchedule::class,
                'table' => 'audit_schedules',
                'foreign_key' => 'audit_schedule_id',
                'frozen_statuses' => ['completed'],
            ],
        ],
        'management_review' => [
            'strategy' => 'correction',
            'label' => '管理评审记录',
            'model' => \app\model\ManagementReview::class,
            'frozen_statuses' => ['completed', 'follow_up'],
        ],
        'review_action' => [
            'strategy' => 'correction',
            'label' => '管理评审措施',
            'model' => \app\model\ReviewAction::class,
            'frozen_statuses' => ['completed'],
        ],

        'equipment' => [
            'strategy' => 'event',
            'label' => '设备台账',
            'model' => \app\model\Equipment::class,
            'protected_fields' => [
                'status',
                'site_id',
                'last_calibration_date',
                'next_calibration_date',
            ],
        ],
        'supplier' => [
            'strategy' => 'event',
            'label' => '供应商台账',
            'model' => \app\model\Supplier::class,
            'protected_fields' => ['status'],
        ],
        'employee' => [
            'strategy' => 'event',
            'label' => '人员档案',
            'model' => \app\model\Employee::class,
            'protected_fields' => ['status', 'department_id', 'position_id'],
        ],
    ],
];
