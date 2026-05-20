<?php
return [
    'title' => '珠宝检测实验室质量管理系统',
    'version' => '2.0.0',
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

    'upload' => [
        'allowed_extensions' => ['doc', 'docx', 'pdf', 'xls', 'xlsx', 'jpg', 'png'],
        'max_size' => 20 * 1024 * 1024,
    ],
];
