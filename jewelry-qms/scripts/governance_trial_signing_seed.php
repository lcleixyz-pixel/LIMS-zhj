<?php
declare(strict_types=1);

$root = dirname(__DIR__);
require $root . '/vendor/autoload.php';
require $root . '/app/common.php';

$app = new think\App();
$app->initialize();

use app\service\ApprovalService;
use app\service\TrialModeService;
use think\facade\Config;
use think\facade\Db;

if (!TrialModeService::isEnabled()) {
    fwrite(STDERR, "仅允许在 QMS_TRIAL_MODE=true 的隔离试运行环境建立 SIM 签批身份。\n");
    exit(2);
}

$batch = TrialModeService::trialBatch();
if ($batch !== 'GOV-TRIAL-20260724') {
    fwrite(STDERR, "当前试运行批次不是 GOV-TRIAL-20260724，已停止建立 SIM 身份。\n");
    exit(2);
}

$passwordEnv = [
    'preparer' => 'SIM_PREPARER_PASSWORD',
    'reviewer' => 'SIM_REVIEWER_PASSWORD',
    'approver' => 'SIM_APPROVER_PASSWORD',
];
$passwords = [];
foreach ($passwordEnv as $role => $envName) {
    $password = (string)(getenv($envName) ?: '');
    if (strlen($password) < 12) {
        fwrite(STDERR, $envName . " 未设置或少于 12 位，已停止建立账号。\n");
        exit(2);
    }
    $passwords[$role] = $password;
}

$companyId = (string)(Db::name('companies')->where('soft_delete', 0)->value('id') ?: '');
if ($companyId === '') {
    fwrite(STDERR, "未找到试运行机构。\n");
    exit(2);
}
$now = date('Y-m-d H:i:s');
$siteId = (string)(Db::name('sites')
    ->where('company_id', $companyId)
    ->where('status', 'active')
    ->where('soft_delete', 0)
    ->order('sort_order', 'asc')
    ->value('id') ?: '');
$categoryId = (string)(Db::name('doc_categories')
    ->where('soft_delete', 0)
    ->order('level', 'asc')
    ->order('sort_order', 'asc')
    ->value('id') ?: '');
$sampleFilePath = runtime_path() . 'record-form-smoke.pdf';
if (!is_file($sampleFilePath)) {
    fwrite(STDERR, "未找到 SIM 签批演练 PDF：{$sampleFilePath}\n");
    exit(2);
}

$accounts = [
    'preparer' => [
        'username' => 'sim_preparer',
        'employee_number' => 'SIM-8021-PREPARER',
        'name' => 'SIM 编制人（文件管理员）',
        'email' => 'sim-preparer@qms.invalid',
        'app_role' => 'quality_manager',
        'position_code' => 'document_controller',
        'position_name' => '文件管理员',
        'appointment_key' => 'SIM-8021-DOCUMENT-PREPARER',
        'scope' => '仅用于 8021 隔离环境编制、修改和提交 SIM 文件。',
    ],
    'reviewer' => [
        'username' => 'sim_reviewer',
        'employee_number' => 'SIM-8021-REVIEWER',
        'name' => 'SIM 审核人（技术负责人）',
        'email' => 'sim-reviewer@qms.invalid',
        'app_role' => 'staff',
        'position_code' => 'technical_manager',
        'position_name' => '技术负责人',
        'appointment_key' => 'SIM-8021-DOCUMENT-REVIEWER',
        'scope' => '仅用于 8021 隔离环境审核或驳回 SIM 文件。',
    ],
    'approver' => [
        'username' => 'sim_approver',
        'employee_number' => 'SIM-8021-APPROVER',
        'name' => 'SIM 批准人（最高管理者）',
        'email' => 'sim-approver@qms.invalid',
        'app_role' => 'staff',
        'position_code' => 'top_management',
        'position_name' => '最高管理者',
        'appointment_key' => 'SIM-8021-DOCUMENT-APPROVER',
        'scope' => '仅用于 8021 隔离环境批准或驳回 SIM 文件。',
    ],
];

$ids = Db::transaction(function () use (
    $accounts,
    $passwords,
    $companyId,
    $siteId,
    $categoryId,
    $sampleFilePath,
    $batch,
    $now
): array {
    $employeeIds = [];
    $userIds = [];

    foreach ($accounts as $role => $account) {
        $position = Db::name('qms_positions')
            ->where('company_id', $companyId)
            ->where('code', $account['position_code'])
            ->where('soft_delete', 0)
            ->find();
        if (!$position) {
            $positionId = qms_uuid();
            Db::name('qms_positions')->insert([
                'id' => $positionId,
                'company_id' => $companyId,
                'code' => $account['position_code'],
                'name' => $account['position_name'],
                'source' => '8021 SIM 电子签批试运行',
                'description' => '仅用于 GOV-TRIAL-20260724 隔离模拟，不构成真实任命。',
                'review_status' => 'published',
                'publish' => 1,
                'soft_delete' => 0,
                'created' => $now,
                'modified' => $now,
            ]);
        } else {
            $positionId = (string)$position['id'];
        }

        $employee = Db::name('employees')
            ->where('company_id', $companyId)
            ->where('employee_number', $account['employee_number'])
            ->where('soft_delete', 0)
            ->find();
        if (!$employee) {
            $employeeId = qms_uuid();
            Db::name('employees')->insert([
                'id' => $employeeId,
                'company_id' => $companyId,
                'primary_site_id' => $siteId !== '' ? $siteId : null,
                'employee_number' => $account['employee_number'],
                'name' => $account['name'],
                'email' => $account['email'],
                'entry_date' => date('Y-m-d'),
                'publish' => 1,
                'soft_delete' => 0,
                'created' => $now,
                'modified' => $now,
            ]);
        } else {
            $employeeId = (string)$employee['id'];
            Db::name('employees')->where('id', $employeeId)->update([
                'name' => $account['name'],
                'email' => $account['email'],
                'primary_site_id' => $siteId !== '' ? $siteId : null,
                'publish' => 1,
                'modified' => $now,
            ]);
        }
        $employeeIds[$role] = $employeeId;

        $user = Db::name('users')->where('username', $account['username'])->find();
        $userPayload = [
            'company_id' => $companyId,
            'employee_id' => $employeeId,
            'username' => $account['username'],
            'password' => password_hash($passwords[$role], PASSWORD_DEFAULT),
            'name' => $account['name'],
            'email' => $account['email'],
            'role' => $account['app_role'],
            'is_mr' => 0,
            'is_approver' => $role === 'approver' ? 1 : 0,
            'publish' => 1,
            'soft_delete' => 0,
            'modified' => $now,
        ];
        if (!$user) {
            $userId = qms_uuid();
            Db::name('users')->insert(array_merge($userPayload, [
                'id' => $userId,
                'created' => $now,
            ]));
        } else {
            $userId = (string)$user['id'];
            Db::name('users')->where('id', $userId)->update($userPayload);
        }
        $userIds[$role] = $userId;

        $appointment = Db::name('employee_appointments')
            ->where('company_id', $companyId)
            ->where('appointment_key', $account['appointment_key'])
            ->where('soft_delete', 0)
            ->find();
        $appointmentPayload = [
            'company_id' => $companyId,
            'employee_id' => $employeeId,
            'position_id' => $positionId,
            'site_id' => null,
            'appointment_key' => $account['appointment_key'],
            'appointment_type' => 'role',
            'position_name' => $account['position_name'],
            'appointment_scope' => $account['scope'],
            'appointed_at' => date('Y-m-d'),
            'valid_until' => null,
            'source_document_number' => $batch,
            'source_excerpt' => 'SIMULATED_TRIAL_ONLY：用户已授权在 8021 隔离环境建立模拟岗位。',
            'source_kind' => 'corporate_evidence',
            'status' => 'active',
            'publish' => 1,
            'soft_delete' => 0,
            'modified' => $now,
        ];
        if (!$appointment) {
            Db::name('employee_appointments')->insert(array_merge($appointmentPayload, [
                'id' => qms_uuid(),
                'created' => $now,
            ]));
        } else {
            Db::name('employee_appointments')->where('id', (string)$appointment['id'])
                ->update($appointmentPayload);
        }
    }

    $document = Db::name('documents')
        ->where('company_id', $companyId)
        ->where('doc_number', 'SIM-SIGN-20260724')
        ->where('status', '<>', 'obsolete')
        ->where('soft_delete', 0)
        ->order('modified', 'desc')
        ->find();
    if (!$document) {
        $documentId = qms_uuid();
        Db::name('documents')->insert([
            'id' => $documentId,
            'company_id' => $companyId,
            'category_id' => $categoryId !== '' ? $categoryId : null,
            'level' => 1,
            'doc_number' => 'SIM-SIGN-20260724',
            'title' => 'SIM 体系文件电子签批演练件',
            'version' => 'A/0',
            'site_id' => $siteId !== '' ? $siteId : null,
            'status' => 'draft',
            'prepared_by' => $employeeIds['preparer'],
            'reviewed_by' => $employeeIds['reviewer'],
            'approved_by' => $employeeIds['approver'],
            'change_reason' => '仅用于验证编制、审核、批准、驳回、重提和回调，不属于正式受控文件。',
            'file_name' => 'record-form-smoke.pdf',
            'file_path' => $sampleFilePath,
            'publish' => 0,
            'soft_delete' => 0,
            'created' => $now,
            'modified' => $now,
            'created_by' => $userIds['preparer'],
        ]);
    } else {
        $documentId = (string)$document['id'];
        Db::name('documents')->where('id', $documentId)->update([
            'prepared_by' => $employeeIds['preparer'],
            'reviewed_by' => $employeeIds['reviewer'],
            'approved_by' => $employeeIds['approver'],
            'site_id' => $siteId !== '' ? $siteId : null,
            'file_name' => 'record-form-smoke.pdf',
            'file_path' => $sampleFilePath,
            'modified' => $now,
        ]);
    }

    if ((int)Db::name('approvals')->where('model_name', 'Document')
        ->where('record', $documentId)->where('soft_delete', 0)->count() === 0) {
        ApprovalService::createWorkflow(
            'document',
            'Document',
            $documentId,
            1,
            $userIds['preparer'],
            $userIds['reviewer'],
            $userIds['approver']
        );
    }

    return [
        'employee_ids' => $employeeIds,
        'user_ids' => $userIds,
        'document_id' => $documentId,
    ];
});

echo json_encode([
    'ok' => true,
    'batch' => $batch,
    'accounts' => array_column($accounts, 'username'),
    'document_number' => 'SIM-SIGN-20260724',
    'document_id' => $ids['document_id'],
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
