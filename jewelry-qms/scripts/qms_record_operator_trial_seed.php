<?php
declare(strict_types=1);

$root = dirname(__DIR__);
require $root . '/vendor/autoload.php';
require $root . '/app/common.php';

$app = new think\App();
$app->initialize();

use app\service\TrialModeService;
use app\service\TrialIdentitySeedGuardService;
use think\facade\Db;

if (!TrialModeService::isEnabled()) {
    fwrite(STDERR, "仅允许在 QMS_TRIAL_MODE=true 的隔离试运行环境建立 SIM 记录填报员。\n");
    exit(2);
}

$batch = TrialModeService::trialBatch();
if ($batch !== 'GOV-TRIAL-20260724') {
    fwrite(STDERR, "当前试运行批次不是 GOV-TRIAL-20260724，已停止建立 SIM 记录填报员。\n");
    exit(2);
}

$password = (string)(getenv('SIM_RECORDER_PASSWORD') ?: '');
if (strlen($password) < 12) {
    fwrite(STDERR, "SIM_RECORDER_PASSWORD 未设置或少于 12 位，已停止建立账号。\n");
    exit(2);
}

try {
    $companyId = TrialIdentitySeedGuardService::configuredCompanyId();
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage() . "\n");
    exit(2);
}

$siteId = (string)(Db::name('sites')
    ->where('company_id', $companyId)
    ->where('status', 'active')
    ->where('soft_delete', 0)
    ->order('sort_order', 'asc')
    ->value('id') ?: '');
$now = date('Y-m-d H:i:s');

try {
    $result = Db::transaction(function () use ($companyId, $siteId, $password, $batch, $now): array {
        $position = TrialIdentitySeedGuardService::findReusablePosition(
            $companyId,
            'record_operator'
        );
        $positionPayload = [
            'company_id' => $companyId,
            'code' => 'record_operator',
            'name' => '记录填报员',
            'source' => '8021 SIM 记录填报试运行',
            'description' => '仅用于 GOV-TRIAL-20260724 隔离模拟，不构成真实岗位任命。',
            'review_status' => 'published',
            'publish' => 1,
            'soft_delete' => 0,
            'modified' => $now,
        ];
        if (!$position) {
            $positionId = qms_uuid();
            Db::name('qms_positions')->insert(array_merge($positionPayload, [
                'id' => $positionId,
                'created' => $now,
            ]));
        } else {
            $positionId = (string)$position['id'];
            Db::name('qms_positions')->where('id', $positionId)->update($positionPayload);
        }

        $employee = TrialIdentitySeedGuardService::findReusableEmployee(
            $companyId,
            'SIM-8021-RECORDER'
        );
        $employeePayload = [
            'company_id' => $companyId,
            'primary_site_id' => $siteId !== '' ? $siteId : null,
            'employee_number' => 'SIM-8021-RECORDER',
            'name' => 'SIM 新人记录员',
            'email' => 'sim-recorder@qms.invalid',
            'entry_date' => date('Y-m-d'),
            'publish' => 1,
            'soft_delete' => 0,
            'modified' => $now,
        ];
        if (!$employee) {
            $employeeId = qms_uuid();
            Db::name('employees')->insert(array_merge($employeePayload, [
                'id' => $employeeId,
                'created' => $now,
            ]));
        } else {
            $employeeId = (string)$employee['id'];
            Db::name('employees')->where('id', $employeeId)->update($employeePayload);
        }

        $user = TrialIdentitySeedGuardService::findReusableUser(
            $companyId,
            'sim_recorder',
            $employeeId
        );
        $userPayload = [
            'company_id' => $companyId,
            'employee_id' => $employeeId,
            'username' => 'sim_recorder',
            'password' => password_hash($password, PASSWORD_DEFAULT),
            'name' => 'SIM 新人记录员',
            'email' => 'sim-recorder@qms.invalid',
            'role' => 'staff',
            'is_mr' => 0,
            'is_approver' => 0,
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

        $appointment = TrialIdentitySeedGuardService::findReusableAppointment(
            $companyId,
            'SIM-8021-RECORD-OPERATOR',
            $employeeId,
            $positionId
        );
        $appointmentPayload = [
            'company_id' => $companyId,
            'employee_id' => $employeeId,
            'position_id' => $positionId,
            'site_id' => null,
            'appointment_key' => 'SIM-8021-RECORD-OPERATOR',
            'appointment_type' => 'role',
            'position_name' => '记录填报员',
            'appointment_scope' => '仅用于 8021 隔离环境选择模板、填写本人记录、生成 PDF 和发起更正。',
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
            $appointmentId = qms_uuid();
            Db::name('employee_appointments')->insert(array_merge($appointmentPayload, [
                'id' => $appointmentId,
                'created' => $now,
            ]));
        } else {
            $appointmentId = (string)$appointment['id'];
            Db::name('employee_appointments')->where('id', $appointmentId)->update($appointmentPayload);
        }

        return [
            'position_id' => $positionId,
            'employee_id' => $employeeId,
            'user_id' => $userId,
            'appointment_id' => $appointmentId,
        ];
    });
} catch (Throwable $exception) {
    fwrite(STDERR, "建立 SIM 记录填报员失败：" . $exception->getMessage() . "\n");
    exit(2);
}

echo json_encode([
    'ok' => true,
    'batch' => $batch,
    'username' => 'sim_recorder',
    'position' => 'record_operator',
    'ids' => $result,
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
