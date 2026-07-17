<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../app/common.php';

$app = new think\App();
$app->initialize();

use app\middleware\Rbac;
use app\model\CompetencyRecord;
use app\model\CustomerComplaint;
use app\model\Equipment;
use app\model\Nonconformity;
use app\model\QmsExternalChangeCandidate;
use app\service\FieldAuditService;
use think\facade\Config;
use think\facade\Db;
use think\facade\Session;

$passes = [];
$failures = [];

function audit_case(bool $condition, string $id, string $message): void
{
    global $passes, $failures;
    if ($condition) {
        $passes[] = $id . ' ' . $message;
    } else {
        $failures[] = $id . ' ' . $message;
    }
}

function audit_uuid(string $suffix): string
{
    return 'b3000000-0000-4000-8000-' . str_pad($suffix, 12, '0', STR_PAD_LEFT);
}

function audit_logs(string $model, string $recordId): array
{
    return Db::name('field_change_logs')
        ->where('model_name', $model)
        ->where('record_id', $recordId)
        ->order('field_name', 'asc')
        ->select()
        ->toArray();
}

function audit_display_logs(string $model, string $recordId): array
{
    if (!method_exists(FieldAuditService::class, 'displayLogsFor')) {
        return [];
    }

    return FieldAuditService::displayLogsFor($model, $recordId);
}

function audit_cleanup(array $ids): void
{
    Db::execute('DROP TRIGGER IF EXISTS p0_r13b3_force_audit_failure');
    Db::name('field_change_logs')->whereIn('record_id', array_values($ids))->delete();
    Db::name('histories')->where('user_id', $ids['user'])->delete();
    Db::name('qms_external_change_candidates')->where('id', $ids['candidate'])->delete();
    Db::name('qms_regulatory_monitor_runs')->where('id', $ids['run'])->delete();
    Db::name('competency_records')->where('id', $ids['competency'])->delete();
    Db::name('customer_complaints')->where('id', $ids['complaint'])->delete();
    Db::name('nonconformities')->where('id', $ids['nc'])->delete();
    Db::name('equipments')->whereIn('id', [$ids['equipment'], $ids['rollback_equipment']])->delete();
    Db::name('employee_appointments')->where('id', $ids['appointment'])->delete();
    Db::name('users')->where('id', $ids['user'])->delete();
    Db::name('employees')->where('id', $ids['employee'])->delete();
}

$ids = [
    'employee' => audit_uuid('101'),
    'user' => audit_uuid('102'),
    'appointment' => audit_uuid('103'),
    'equipment' => audit_uuid('201'),
    'rollback_equipment' => audit_uuid('202'),
    'nc' => audit_uuid('301'),
    'complaint' => audit_uuid('401'),
    'competency' => audit_uuid('501'),
    'run' => audit_uuid('601'),
    'candidate' => audit_uuid('602'),
];
$companyId = (string)Config::get('qms.company_id');
$now = '2026-07-17 10:00:00';
$adminId = '00000000-0000-0000-0000-000000000040';

try {
    audit_cleanup($ids);

    Db::name('employees')->insert([
        'id' => $ids['employee'],
        'company_id' => $companyId,
        'department_id' => '00000000-0000-0000-0000-000000000010',
        'employee_number' => 'P0R13B3-E001',
        'name' => '留痕测试人员',
        'publish' => 1,
        'soft_delete' => 0,
        'created' => $now,
        'modified' => $now,
    ]);
    Db::name('users')->insert([
        'id' => $ids['user'],
        'company_id' => $companyId,
        'employee_id' => $ids['employee'],
        'department_id' => '00000000-0000-0000-0000-000000000010',
        'username' => 'p0_r13b3_auditor',
        'password' => password_hash('test-only', PASSWORD_DEFAULT),
        'name' => '留痕测试人员',
        'role' => 'staff',
        'publish' => 1,
        'soft_delete' => 0,
        'created' => $now,
        'modified' => $now,
    ]);
    Db::name('employee_appointments')->insert([
        'id' => $ids['appointment'],
        'company_id' => $companyId,
        'employee_id' => $ids['employee'],
        'appointment_key' => 'P0R13B3-QUALITY-MANAGER',
        'appointment_type' => 'role',
        'position_name' => '质量负责人',
        'appointed_at' => '2026-01-01',
        'status' => 'active',
        'publish' => 1,
        'soft_delete' => 0,
        'created' => $now,
        'modified' => $now,
    ]);
    Session::set('user', [
        'id' => $ids['user'],
        'employee_id' => $ids['employee'],
        'role' => 'staff',
        'name' => '留痕测试人员',
    ]);

    Db::name('equipments')->insert([
        'id' => $ids['equipment'],
        'company_id' => $companyId,
        'equipment_number' => 'P0R13B3-EQ-001',
        'name' => '留痕测试设备',
        'status' => 'active',
        'last_calibration_date' => '2026-06-01',
        'next_calibration_date' => '2027-06-01',
        'calibration_required' => 1,
        'publish' => 1,
        'soft_delete' => 0,
        'created' => $now,
        'modified' => $now,
    ]);

    $equipment = Equipment::find($ids['equipment']);
    $equipment->save([
        'status' => 'active',
        'next_calibration_date' => '2027-06-01 00:00:00',
    ]);
    audit_case(
        count(audit_logs('Equipment', $ids['equipment'])) === 0,
        'T01',
        '无变化状态和等价日期保存不生成字段日志'
    );

    $equipment = Equipment::find($ids['equipment']);
    $equipment->save(['status' => 'maintenance']);
    $equipmentLogs = audit_logs('Equipment', $ids['equipment']);
    audit_case(
        count($equipmentLogs) === 1
        && ($equipmentLogs[0]['field_name'] ?? '') === 'status'
        && ($equipmentLogs[0]['old_value'] ?? '') === 'active'
        && ($equipmentLogs[0]['new_value'] ?? '') === 'maintenance',
        'T02',
        '设备只改状态时恰好记录一条真实变化'
    );

    Db::name('nonconformities')->insert([
        'id' => $ids['nc'],
        'company_id' => $companyId,
        'nc_number' => 'P0R13B3-NC-001',
        'source' => 'test',
        'description' => '留痕测试不符合',
        'identified_date' => '2026-07-17',
        'severity' => 'major',
        'impact_assessment' => '原影响',
        'assigned_to' => null,
        'status' => 'open',
        'publish' => 1,
        'soft_delete' => 0,
        'record_status' => 0,
        'created' => $now,
        'modified' => $now,
    ]);
    $nc = Nonconformity::find($ids['nc']);
    $nc->save([
        'impact_assessment' => '新影响',
        'assigned_to' => $adminId,
    ]);
    $ncLogs = audit_logs('Nonconformity', $ids['nc']);
    audit_case(
        count($ncLogs) === 2
        && array_column($ncLogs, 'field_name') === ['assigned_to', 'impact_assessment']
        && ($ncLogs[1]['old_value'] ?? '') === '原影响'
        && ($ncLogs[1]['new_value'] ?? '') === '新影响',
        'T03',
        '不符合修改两个字段时日志数量和前后值精确'
    );

    Db::name('customer_complaints')->insert([
        'id' => $ids['complaint'],
        'company_id' => $companyId,
        'complaint_number' => 'P0R13B3-CP-001',
        'customer_name' => '留痕测试客户',
        'received_date' => '2026-07-17',
        'description' => '留痕测试投诉',
        'status' => 'received',
        'publish' => 1,
        'soft_delete' => 0,
        'record_status' => 0,
        'created' => $now,
        'modified' => $now,
    ]);
    $complaint = CustomerComplaint::find($ids['complaint']);
    $complaint->save([
        'status' => 'investigating',
        'investigation' => '已核对原始记录',
    ]);
    $complaintLogs = audit_logs('CustomerComplaint', $ids['complaint']);
    audit_case(
        count($complaintLogs) === 2
        && array_column($complaintLogs, 'field_name') === ['investigation', 'status'],
        'T04',
        '投诉推进同时记录状态和调查内容'
    );

    Db::name('competency_records')->insert([
        'id' => $ids['competency'],
        'company_id' => $companyId,
        'employee_id' => $ids['employee'],
        'test_item' => '折射率测定',
        'assessment_date' => '2026-07-17',
        'assessor_id' => $ids['user'],
        'result' => 'pending',
        'publish' => 1,
        'soft_delete' => 0,
        'created' => $now,
        'modified' => $now,
    ]);
    $competency = CompetencyRecord::find($ids['competency']);
    $competency->save(['result' => 'qualified']);
    $competencyDisplay = audit_display_logs('CompetencyRecord', $ids['competency']);
    audit_case(
        count($competencyDisplay) === 1
        && ($competencyDisplay[0]['field_label'] ?? '') === '评价结果'
        && ($competencyDisplay[0]['old_value_display'] ?? '') === '待评价'
        && ($competencyDisplay[0]['new_value_display'] ?? '') === '合格',
        'T05',
        '能力确认结果变更以中文字段和中文状态展示'
    );

    Db::name('qms_regulatory_monitor_runs')->insert([
        'id' => $ids['run'],
        'company_id' => $companyId,
        'run_code' => 'P0R13B3-RUN-001',
        'trigger_mode' => 'manual',
        'started_at' => $now,
        'status' => 'completed',
        'publish' => 1,
        'soft_delete' => 0,
        'created' => $now,
        'modified' => $now,
    ]);
    Db::name('qms_external_change_candidates')->insert([
        'id' => $ids['candidate'],
        'company_id' => $companyId,
        'monitor_run_id' => $ids['run'],
        'source_key' => 'samr',
        'source_mode' => 'manual_only',
        'source_item_key' => 'P0R13B3-CANDIDATE',
        'source_url' => 'https://example.invalid/p0-r13b3',
        'title' => '留痕测试法规候选',
        'first_seen_at' => $now,
        'last_seen_at' => $now,
        'content_hash' => str_repeat('a', 64),
        'review_status' => 'pending',
        'publish' => 1,
        'soft_delete' => 0,
        'created' => $now,
        'modified' => $now,
    ]);
    $candidate = QmsExternalChangeCandidate::find($ids['candidate']);
    $candidate->save([
        'review_status' => 'confirmed_applicable',
        'reviewed_by' => $ids['user'],
        'reviewed_at' => '2026-07-17 11:00:00',
        'review_comment' => '人工确认适用',
    ]);
    $candidateLogs = audit_logs('QmsExternalChangeCandidate', $ids['candidate']);
    audit_case(
        count($candidateLogs) === 4
        && array_column($candidateLogs, 'field_name') === [
            'review_comment',
            'review_status',
            'reviewed_at',
            'reviewed_by',
        ],
        'T06',
        '法规候选人工复核的状态、人员、时间和备注完整留痕'
    );

    $readableLog = audit_display_logs('CompetencyRecord', $ids['competency'])[0] ?? [];
    audit_case(
        ($readableLog['changed_by_name'] ?? '') === '留痕测试人员'
        && str_contains((string)($readableLog['changed_by_position'] ?? ''), '质量负责人')
        && ($readableLog['changed_by_display'] ?? '') !== $ids['user'],
        'T07',
        '详情历史显示操作人姓名和岗位而非裸 UUID'
    );

    Db::name('field_change_logs')->where('record_id', $ids['equipment'])->delete();
    $equipment = Equipment::find($ids['equipment']);
    $equipment->save(['last_calibration_date' => '2026-06-01 00:00:00']);
    audit_case(
        count(audit_logs('Equipment', $ids['equipment'])) === 0,
        'T08',
        '同一日期的等价格式不生成假变更'
    );

    Db::name('equipments')->insert([
        'id' => $ids['rollback_equipment'],
        'company_id' => $companyId,
        'equipment_number' => 'P0R13B3-EQ-ROLLBACK',
        'name' => '审计失败回滚设备',
        'status' => 'active',
        'calibration_required' => 1,
        'publish' => 1,
        'soft_delete' => 0,
        'created' => $now,
        'modified' => $now,
    ]);
    Db::execute(
        "CREATE TRIGGER p0_r13b3_force_audit_failure BEFORE INSERT ON field_change_logs"
        . " FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'forced field audit failure'"
    );
    $auditFailureMessage = '';
    try {
        $rollbackEquipment = Equipment::find($ids['rollback_equipment']);
        $rollbackEquipment->save(['status' => 'maintenance']);
    } catch (Throwable $exception) {
        $auditFailureMessage = $exception->getMessage();
    } finally {
        Db::execute('DROP TRIGGER IF EXISTS p0_r13b3_force_audit_failure');
    }
    $rollbackStatus = (string)Db::name('equipments')
        ->where('id', $ids['rollback_equipment'])
        ->value('status');
    audit_case(
        $rollbackStatus === 'active'
        && str_contains($auditFailureMessage, '系统未能保存完整变更记录'),
        'T09',
        '审计写入失败时关键业务写入不落库并返回中文错误'
    );

    Db::name('field_change_logs')->where('record_id', $ids['equipment'])->delete();
    Db::name('histories')->where('user_id', $ids['user'])->delete();
    $request = (new app\Request())
        ->setMethod('POST')
        ->setController('Equipment')
        ->setAction('edit')
        ->withPost(['id' => $ids['equipment'], 'status' => 'decommissioned']);
    $callbackInvoked = false;
    (new Rbac())->handle($request, static function () use (&$callbackInvoked) {
        $callbackInvoked = true;
        return response('unexpected');
    });
    $securityRows = Db::name('histories')
        ->where('user_id', $ids['user'])
        ->where('action', 'access_denied')
        ->select()
        ->toArray();
    audit_case(
        $callbackInvoked === false
        && count($securityRows) === 1
        && count(audit_logs('Equipment', $ids['equipment'])) === 0
        && str_contains((string)($securityRows[0]['details'] ?? ''), 'outcome=failed'),
        'T10',
        '越权失败只进入安全审计，不进入字段历史'
    );
} finally {
    audit_cleanup($ids);
    Session::clear();
}

foreach ($passes as $pass) {
    echo "PASS {$pass}\n";
}
foreach ($failures as $failure) {
    fwrite(STDERR, "FAIL {$failure}\n");
}

if ($failures !== []) {
    fwrite(STDERR, sprintf(
        "qms_p0_field_audit_smoke failed: %d passed, %d failed\n",
        count($passes),
        count($failures)
    ));
    exit(1);
}

echo "qms_p0_field_audit_smoke passed: T01-T10\n";
