<?php
return [
    'title' => '珠宝检测实验室质量管理系统',
    'version' => '2.1.0',
    'company_id' => '00000000-0000-0000-0000-000000000001',

    'docLevels' => [
        1 => '质量手册',
        2 => '程序文件',
        3 => '作业指导书',
        4 => '记录表格',
    ],

    'approvalRules' => [
        1 => 3,
        2 => 3,
        3 => 2,
        4 => 2,
    ],

    'roles' => [
        'admin' => '系统管理员',
        'quality_manager' => '质量负责人',
        'auditor' => '内审员',
        'department_head' => '部门负责人',
        'staff' => '一般人员',
    ],

    'permissions' => [
        'admin' => ['*'],
        'quality_manager' => [
            'dashboard', 'document', 'record_form_template', 'record_form_instance', 'approval', 'doc_category', 'doc_template',
            'compliance', 'ai_assistant', 'ai_chat',
            'planningdashboard', 'planningelement', 'planningsource', 'planningclause', 'planningstructure', 'planningtraceability', 'planningobjective',
            'audit_plan', 'audit_schedule', 'audit_finding', 'audit_checklist',
            'management_review', 'review_action', 'capa', 'nonconformity', 'complaint',
            'equipment', 'equipment_maintenance', 'equipment_authorization', 'calibration', 'reference_material',
            'training_plan', 'training', 'training_record', 'competency_record', 'employee_certificate',
            'supplier', 'supplier_evaluation', 'import', 'notification',
            'department', 'employee', 'site', 'equipment_transfer', 'user',
        ],
        'auditor' => [
            'dashboard', 'compliance', 'document', 'record_form_template', 'record_form_instance', 'audit_plan', 'audit_schedule', 'audit_finding', 'audit_checklist',
            'capa', 'nonconformity', 'complaint', 'notification',
        ],
        'department_head' => [
            'dashboard', 'document', 'record_form_template', 'record_form_instance', 'capa', 'nonconformity', 'complaint',
            'equipment', 'equipment_maintenance', 'equipment_transfer', 'equipment_authorization', 'calibration', 'reference_material',
            'training_plan', 'training', 'training_record', 'competency_record', 'employee_certificate', 'notification',
        ],
        'staff' => [
            'dashboard', 'document', 'record_form_template', 'record_form_instance', 'complaint', 'notification',
        ],
    ],

    'statusLabels' => [
        'capa' => [
            'open' => '待处理',
            'analyzing' => '原因分析',
            'implementing' => '措施实施',
            'verifying' => '效果验证',
            'closed' => '已关闭',
        ],
        'audit_plan' => [
            'draft' => '草稿',
            'approved' => '已批准',
            'in_progress' => '进行中',
            'completed' => '已完成',
        ],
        'audit_schedule' => [
            'planned' => '已计划',
            'in_progress' => '审核中',
            'completed' => '已完成',
        ],
        'audit_finding' => [
            'open' => '待整改',
            'correcting' => '整改中',
            'verified' => '已验证',
            'closed' => '已关闭',
        ],
        'management_review' => [
            'planned' => '计划中',
            'completed' => '已完成',
            'follow_up' => '跟踪中',
        ],
        'review_action' => [
            'open' => '待执行',
            'in_progress' => '执行中',
            'completed' => '已完成',
            'overdue' => '已逾期',
        ],
        'complaint' => [
            'received' => '已受理',
            'investigating' => '调查中',
            'handling' => '处理中',
            'responded' => '已回复',
            'closed' => '已关闭',
        ],
        'nonconformity' => [
            'open' => '待评估',
            'evaluating' => '评估中',
            'correcting' => '纠正中',
            'verified' => '已验证',
            'closed' => '已关闭',
        ],
        'equipment' => [
            'active' => '合格',
            'calibrating' => '校准中',
            'maintenance' => '限用',
            'decommissioned' => '报废',
        ],
        'reference_material' => [
            'active' => '在用',
            'expired' => '已过期',
            'depleted' => '已用尽',
            'discarded' => '已报废',
        ],
        'equipment_authorization' => [
            'active' => '有效',
            'revoked' => '已撤销',
            'expired' => '已过期',
        ],
        'supplier' => [
            'pending' => '待评价',
            'qualified' => '合格',
            'suspended' => '暂停',
            'removed' => '黑名单',
        ],
        'training' => [
            'planned' => '计划中',
            'completed' => '已完成',
            'cancelled' => '已取消',
        ],
        'training_plan' => [
            'draft' => '草稿',
            'approved' => '已批准',
            'completed' => '已完成',
        ],
        'employee_certificate' => [
            'active' => '有效',
            'expired' => '已过期',
            'revoked' => '已撤销',
            'archived' => '已归档',
        ],
    ],

    'upload' => [
        'allowed_extensions' => ['doc', 'docx', 'pdf', 'xls', 'xlsx', 'csv', 'jpg', 'png'],
        'max_size' => 20 * 1024 * 1024,
    ],

    'notification' => [
        'calibration_days' => 30,
        'capa_overdue_days' => 0,
        'capa_effectiveness_days' => 30,
    ],

    'integration' => [
        'api_token' => env('QMS_API_TOKEN', ''),
    ],

    'onlyoffice' => [
        'enabled' => env('ONLYOFFICE_ENABLED', false),
        'server_url' => env('ONLYOFFICE_SERVER_URL', ''),
        'jwt_secret' => env('ONLYOFFICE_JWT_SECRET', ''),
    ],

    'ai' => [
        'provider' => 'deepseek',
        'api_key' => env('DEEPSEEK_API_KEY', ''),
        'base_url' => 'https://api.deepseek.com',
        'model' => 'deepseek-v4-flash',
        'max_tokens' => 4096,
        'chat_max_tokens' => 2048,
        'chat_timeout' => 180,
        'temperature' => 0.1,
    ],
];
