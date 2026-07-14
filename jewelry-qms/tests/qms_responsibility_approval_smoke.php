<?php
declare(strict_types=1);

require __DIR__ . '/support/qms_responsibility_fixture.php';

use app\service\QmsResponsibilityApprovalService;
use app\service\QmsResponsibilityCatalogService;
use app\service\QmsResponsibilityDraftService;
use think\facade\Db;
use think\facade\Session;

$approvalReflection = new ReflectionClass(QmsResponsibilityApprovalService::class);
$approvalPublicMethods = array_map(
    static fn (ReflectionMethod $method): string => $method->getName(),
    array_filter(
        $approvalReflection->getMethods(ReflectionMethod::IS_PUBLIC),
        static fn (ReflectionMethod $method): bool => $method->getDeclaringClass()->getName() === QmsResponsibilityApprovalService::class
    )
);
sort($approvalPublicMethods, SORT_STRING);
$expectedApprovalPublicMethods = [
    'approveBatch', 'approveBootstrap', 'pendingBatchForApprover', 'registerCorporateIdentity',
    'requestLabDirectorAppointment', 'submitVersion', 'versionStatus',
];
sort($expectedApprovalPublicMethods, SORT_STRING);
catalog_assert($approvalPublicMethods === $expectedApprovalPublicMethods, 'Approval service exposes only the seven agreed public interfaces');

function approval_session(array $user, string $state = 'active'): void
{
    $sessionId = 'SIG-' . substr(str_replace('-', '', qms_uuid()), 0, 20);
    if ($state !== 'missing') {
        Db::name('user_sessions')->insert([
            'id' => $sessionId,
            'user_id' => (string)$user['id'],
            'start_time' => date('Y-m-d H:i:s'),
            'end_time' => $state === 'ended' ? date('Y-m-d H:i:s') : null,
            'ip_address' => '127.0.0.1',
        ]);
    }
    Session::set('user', [
        'id' => (string)$user['id'],
        'employee_id' => (string)($user['employee_id'] ?? ''),
        'role' => (string)$user['role'],
        'session_id' => $sessionId,
    ]);
}

function approval_employee(string $companyId, string $label, bool $withUser = true, string $role = 'staff'): array
{
    $employeeId = responsibility_fixture_row('employees', [
        'company_id' => $companyId,
        'employee_number' => 'APR-' . strtoupper(substr(qms_uuid(), 0, 8)),
        'name' => '审批测试-' . $label,
    ]);
    $user = null;
    if ($withUser) {
        $userId = responsibility_fixture_row('users', [
            'company_id' => $companyId,
            'employee_id' => $employeeId,
            'username' => 'apr_' . strtolower(str_replace('-', '', substr(qms_uuid(), 0, 12))),
            'password' => password_hash('test-only', PASSWORD_DEFAULT),
            'name' => '审批用户-' . $label,
            'role' => $role,
        ]);
        $user = Db::name('users')->where('id', $userId)->find();
    }

    return [
        'employee' => Db::name('employees')->where('id', $employeeId)->find(),
        'user' => $user,
    ];
}

function approval_positions(string $companyId): array
{
    $rows = Db::name('qms_positions')->where('company_id', $companyId)->where('soft_delete', 0)->select()->toArray();
    $positions = [];
    foreach ($rows as $row) {
        $positions[(string)$row['code']] = $row;
    }
    return $positions;
}

function approval_prepare_version(string $companyId, string $gmId, string $directorId): array
{
    $version = QmsResponsibilityCatalogService::createInitialDraft();
    $detail = QmsResponsibilityDraftService::versionDetail((string)$version['id']);
    $positions = approval_positions($companyId);
    $staff = [];
    $staffByPosition = [];
    $activityRoleBound = false;
    $dynamicResponsibility = null;
    foreach ($detail['responsibilities'] as $responsibility) {
        $mode = (string)$responsibility['assignment_mode'];
        if ($mode === 'derived_from_scope') {
            $dynamicResponsibility ??= $responsibility;
            continue;
        }
        if ($mode === 'activity_instance' && $activityRoleBound) {
            continue;
        }

        $positionCode = (string)($responsibility['fixed_position_code'] ?? '');
        if ($positionCode === 'company_general_manager') {
            $subjectId = $gmId;
        } elseif ($positionCode === 'lab_director') {
            $subjectId = $directorId;
        } else {
            $reuseKey = $positionCode !== '' ? $positionCode : 'activity-role-' . count($staff);
            if (!isset($staffByPosition[$reuseKey])) {
                $staffByPosition[$reuseKey] = approval_employee($companyId, 'DR-' . count($staff), true, 'staff');
                $staff[] = $staffByPosition[$reuseKey];
            }
            $person = $staffByPosition[$reuseKey];
            $subjectId = (string)$person['employee']['id'];
        }
        $competencyId = responsibility_fixture_row('competency_records', [
            'company_id' => $companyId,
            'employee_id' => $subjectId,
            'test_item' => '责任链资格证据',
            'assessment_date' => date('Y-m-d'),
            'result' => 'qualified',
            'valid_until' => date('Y-m-d', strtotime('+2 years')),
        ]);
        QmsResponsibilityDraftService::saveAssignment(
            (string)$responsibility['id'],
            $subjectId,
            null,
            date('Y-m-d'),
            null,
            ['competency_record_ids' => [$competencyId]]
        );
        if ($mode === 'activity_instance') {
            $activityRoleBound = true;
        }
    }

    // Corrupt legacy/imported data can contain a direct row for a runtime dynamic slot.
    // Submission and activation must never turn it into a permanent appointment.
    $illegalDynamicPerson = approval_employee($companyId, 'ILLEGAL-DYNAMIC', true, 'staff');
    $illegalDynamicCompetency = responsibility_fixture_row('competency_records', [
        'company_id' => $companyId,
        'employee_id' => (string)$illegalDynamicPerson['employee']['id'],
        'test_item' => '运行时动态责任测试证据',
        'assessment_date' => date('Y-m-d'),
        'result' => 'qualified',
        'valid_until' => date('Y-m-d', strtotime('+2 years')),
    ]);
    responsibility_fixture_row('qms_responsibility_assignments', [
        'company_id' => $companyId,
        'responsibility_id' => (string)$dynamicResponsibility['id'],
        'employee_id' => (string)$illegalDynamicPerson['employee']['id'],
        'site_id' => null,
        'site_scope_key' => '*',
        'proposed_from' => date('Y-m-d'),
        'proposed_until' => null,
        'competence_snapshot' => json_encode(['competency_record_ids' => [$illegalDynamicCompetency]], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'status' => 'draft',
    ]);

    return [
        'version' => $version,
        'staff' => $staff,
        'positions' => $positions,
        'dynamic_responsibility_id' => (string)$dynamicResponsibility['id'],
    ];
}

function approval_pending_for(string $versionId, string $employeeId): array
{
    return QmsResponsibilityApprovalService::pendingBatchForApprover($versionId, $employeeId);
}

/** Test-side copy of the documented deterministic appointment-key formula. */
function approval_expected_appointment_keys(string $versionId): array
{
    $rows = Db::name('qms_responsibility_assignments')
        ->alias('ra')
        ->join('qms_activity_responsibilities r', 'r.id=ra.responsibility_id')
        ->join('qms_responsibility_activities a', 'a.id=r.activity_id')
        ->leftJoin('qms_positions p', 'p.id=r.fixed_position_id')
        ->where('a.chain_version_id', $versionId)
        ->where('ra.soft_delete', 0)
        ->where('ra.status', 'pending_approval')
        ->field('ra.*,r.slot_kind,r.assignment_mode,r.fixed_position_id position_id,r.activity_role_code,p.code position_code')
        ->select()->toArray();
    $keys = [];
    foreach ($rows as $row) {
        if ((string)$row['assignment_mode'] === 'derived_from_scope' || (string)$row['position_code'] === 'company_general_manager') {
            continue;
        }
        if ((string)$row['slot_kind'] === 'fixed_position') {
            $keys['role|' . $row['employee_id'] . '|' . $row['position_id'] . '|' . $row['site_scope_key']] =
                'rc:' . $versionId . ':role:' . $row['employee_id'] . ':' . $row['position_id'] . ':' . $row['site_scope_key'];
        } elseif ((string)$row['slot_kind'] === 'activity_role') {
            $keys['responsibility|' . $row['id']] = 'rc:' . $versionId . ':responsibility:' . $row['id'];
        }
    }
    $keys = array_values($keys);
    sort($keys, SORT_STRING);
    return $keys;
}

function approval_concurrency_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function approval_wait_for_file(string $path, float $timeoutSeconds): void
{
    $deadline = microtime(true) + $timeoutSeconds;
    while (!is_file($path) && microtime(true) < $deadline) {
        usleep(20_000);
    }
    approval_concurrency_assert(is_file($path), 'Activation worker did not reach the approval call');
}

/** @return array{stdout:string,stderr:string,exit_code:int} */
function approval_wait_for_process($process, array $pipes, float $timeoutSeconds): array
{
    $deadline = microtime(true) + $timeoutSeconds;
    $lastStatus = proc_get_status($process);
    while (($lastStatus['running'] ?? false) && microtime(true) < $deadline) {
        usleep(20_000);
        $lastStatus = proc_get_status($process);
    }
    if ($lastStatus['running'] ?? false) {
        proc_terminate($process);
        throw new RuntimeException('Activation worker did not finish after the competing update committed');
    }

    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $closeCode = proc_close($process);
    $exitCode = (int)($lastStatus['exitcode'] ?? $closeCode);

    return ['stdout' => trim($stdout), 'stderr' => trim($stderr), 'exit_code' => $exitCode];
}

function approval_cleanup_concurrency_fixture(string $companyId, string $versionId, array $employeeIds): void
{
    $activityIds = $versionId === '' ? [] : array_map(
        'strval',
        Db::name('qms_responsibility_activities')->where('chain_version_id', $versionId)->column('id')
    );
    $responsibilityIds = $activityIds === [] ? [] : array_map(
        'strval',
        Db::name('qms_activity_responsibilities')->whereIn('activity_id', $activityIds)->column('id')
    );

    if ($versionId !== '') {
        Db::name('employee_appointments')->where('source_chain_version_id', $versionId)->delete();
        Db::name('qms_responsibility_approvals')->where('chain_version_id', $versionId)->delete();
    }
    if ($employeeIds !== []) {
        Db::name('employee_appointments')->where('company_id', $companyId)->whereIn('employee_id', $employeeIds)->delete();
        Db::name('qms_responsibility_approvals')->where('company_id', $companyId)
            ->where(function ($query) use ($employeeIds): void {
                $query->whereIn('subject_employee_id', $employeeIds)->whereOr('approver_employee_id', 'in', $employeeIds);
            })->delete();
    }
    if ($responsibilityIds !== []) {
        Db::name('qms_responsibility_assignments')->whereIn('responsibility_id', $responsibilityIds)->delete();
        Db::name('qms_activity_responsibilities')->whereIn('id', $responsibilityIds)->delete();
    }
    if ($activityIds !== []) {
        Db::name('qms_responsibility_activities')->whereIn('id', $activityIds)->delete();
    }
    if ($versionId !== '') {
        Db::name('qms_responsibility_chain_versions')->where('id', $versionId)->delete();
    }
    if ($employeeIds !== []) {
        $userIds = array_map('strval', Db::name('users')->whereIn('employee_id', $employeeIds)->column('id'));
        if ($userIds !== []) {
            Db::name('user_sessions')->whereIn('user_id', $userIds)->delete();
            Db::name('users')->whereIn('id', $userIds)->delete();
        }
        Db::name('competency_records')->whereIn('employee_id', $employeeIds)->delete();
        Db::name('employee_certificates')->whereIn('employee_id', $employeeIds)->delete();
        Db::name('employees')->whereIn('id', $employeeIds)->delete();
    }
}

catalog_in_transaction(function (): void {
    $companyId = catalog_company_id();
    $admin = approval_employee($companyId, 'ADMIN', true, 'admin');
    $qualityManager = approval_employee($companyId, 'QM', true, 'quality_manager');
    $gm = approval_employee($companyId, 'GM', true, 'staff');
    $director = approval_employee($companyId, 'DIRECTOR', true, 'staff');
    $noUser = approval_employee($companyId, 'NO-USER', false);

    approval_session($admin['user'], 'missing');
    responsibility_assert_throws(
        fn () => QmsResponsibilityApprovalService::registerCorporateIdentity([
            'position_code' => 'company_general_manager',
            'employee_id' => (string)$gm['employee']['id'],
            'appointed_at' => date('Y-m-d'),
            'source_document_number' => 'CORP-NO-SESSION',
            'source_excerpt' => '不存在的会话不得写入',
        ]),
        'Missing persisted user session blocks writes'
    );
    approval_session($admin['user'], 'ended');
    responsibility_assert_throws(
        fn () => QmsResponsibilityApprovalService::registerCorporateIdentity([
            'position_code' => 'company_general_manager',
            'employee_id' => (string)$gm['employee']['id'],
            'appointed_at' => date('Y-m-d'),
            'source_document_number' => 'CORP-ENDED-SESSION',
            'source_excerpt' => '已结束的会话不得写入',
        ]),
        'Ended persisted user session blocks writes'
    );

    approval_session($gm['user']);
    responsibility_assert_throws(
        fn () => QmsResponsibilityApprovalService::registerCorporateIdentity([
            'position_code' => 'company_general_manager',
            'employee_id' => (string)$gm['employee']['id'],
            'appointed_at' => date('Y-m-d'),
            'source_document_number' => 'CORP-001',
            'source_excerpt' => '既有公司治理证据',
        ]),
        'Non-admin cannot register corporate identity'
    );

    approval_session($admin['user']);
    responsibility_assert_throws(
        fn () => QmsResponsibilityApprovalService::registerCorporateIdentity([
            'position_code' => 'company_general_manager',
            'employee_id' => (string)$noUser['employee']['id'],
            'appointed_at' => date('Y-m-d'),
            'source_document_number' => 'CORP-NO-USER',
            'source_excerpt' => '无登录账号不可登记',
        ]),
        'Corporate identity requires an active mapped user'
    );
    $gmAppointment = QmsResponsibilityApprovalService::registerCorporateIdentity([
        'position_code' => 'company_general_manager',
        'employee_id' => (string)$gm['employee']['id'],
        'appointed_at' => date('Y-m-d'),
        'source_document_number' => 'CORP-001',
        'source_excerpt' => '既有公司治理证据',
    ]);
    catalog_assert(($gmAppointment['source_kind'] ?? '') === 'corporate_evidence', 'GM is registered from corporate evidence');
    $sameGmAppointment = QmsResponsibilityApprovalService::registerCorporateIdentity([
        'position_code' => 'company_general_manager',
        'employee_id' => (string)$gm['employee']['id'],
        'appointed_at' => date('Y-m-d'),
        'source_document_number' => 'CORP-001-UPDATED',
        'source_excerpt' => '更新同一既有事实证据',
    ]);
    catalog_assert($sameGmAppointment['id'] === $gmAppointment['id'], 'GM registration is idempotent for the same employee');
    catalog_assert((int)Db::name('qms_responsibility_approvals')->where('approval_scope', 'governance_bootstrap')->count() === 0, 'GM registration does not create reverse approval');
    $otherGm = approval_employee($companyId, 'OTHER-GM', true, 'staff');
    responsibility_assert_throws(
        fn () => QmsResponsibilityApprovalService::registerCorporateIdentity([
            'position_code' => 'company_general_manager',
            'employee_id' => (string)$otherGm['employee']['id'],
            'appointed_at' => date('Y-m-d'),
            'source_document_number' => 'CORP-002',
            'source_excerpt' => '不得并行登记第二名总经理',
        ]),
        'A different active corporate GM is blocked'
    );

    approval_session($qualityManager['user']);
    responsibility_assert_throws(
        fn () => QmsResponsibilityApprovalService::requestLabDirectorAppointment((string)$director['employee']['id'], date('Y-m-d')),
        'Non-admin cannot request director bootstrap'
    );
    approval_session($admin['user']);
    responsibility_assert_throws(
        fn () => QmsResponsibilityApprovalService::requestLabDirectorAppointment((string)$noUser['employee']['id'], date('Y-m-d')),
        'Director candidate requires an active mapped user'
    );
    responsibility_assert_throws(
        fn () => QmsResponsibilityApprovalService::requestLabDirectorAppointment((string)$gm['employee']['id'], date('Y-m-d')),
        'GM cannot be their own director subject'
    );
    $rejectedDirector = approval_employee($companyId, 'REJECTED-DIRECTOR', true, 'staff');
    $rejectedBootstrap = QmsResponsibilityApprovalService::requestLabDirectorAppointment((string)$rejectedDirector['employee']['id'], date('Y-m-d'));
    approval_session($gm['user']);
    QmsResponsibilityApprovalService::approveBootstrap((string)$rejectedBootstrap['id'], 'rejected', '不予任命');
    catalog_assert(
        (int)Db::name('employee_appointments')->where('source_approval_id', (string)$rejectedBootstrap['id'])->count() === 0,
        'Rejected director bootstrap creates no appointment'
    );
    approval_session($admin['user']);
    $positions = approval_positions($companyId);
    $legacyDirector = approval_employee($companyId, 'LEGACY-DIRECTOR', true, 'staff');
    $legacyDirectorAppointmentId = responsibility_fixture_row('employee_appointments', [
        'company_id' => $companyId,
        'employee_id' => (string)$legacyDirector['employee']['id'],
        'position_id' => (string)$positions['lab_director']['id'],
        'appointment_key' => 'LEGACY-DIRECTOR-' . qms_uuid(),
        'appointment_type' => 'role',
        'position_name' => '实验室主任',
        'appointed_at' => date('Y-m-d'),
        'source_kind' => 'legacy_document',
        'status' => 'active',
    ]);
    responsibility_assert_throws(
        fn () => QmsResponsibilityApprovalService::requestLabDirectorAppointment((string)$director['employee']['id'], date('Y-m-d')),
        'A different active legacy director blocks a new bootstrap request'
    );
    catalog_assert(
        (int)Db::name('qms_responsibility_approvals')
            ->where('approval_scope', 'governance_bootstrap')
            ->where('subject_employee_id', (string)$director['employee']['id'])
            ->where('decision', 'pending')->where('soft_delete', 0)->count() === 0,
        'Blocked legacy director conflict creates no pending bootstrap'
    );
    Db::name('employee_appointments')->where('id', $legacyDirectorAppointmentId)->update(['status' => 'inactive']);
    $sameCandidateLegacyId = responsibility_fixture_row('employee_appointments', [
        'company_id' => $companyId,
        'employee_id' => (string)$director['employee']['id'],
        'position_id' => (string)$positions['lab_director']['id'],
        'appointment_key' => 'LEGACY-SAME-DIRECTOR-' . qms_uuid(),
        'appointment_type' => 'role',
        'position_name' => '实验室主任',
        'appointed_at' => date('Y-m-d'),
        'source_kind' => 'legacy_document',
        'status' => 'active',
    ]);
    $bootstrap = QmsResponsibilityApprovalService::requestLabDirectorAppointment((string)$director['employee']['id'], date('Y-m-d'));
    catalog_assert(($bootstrap['approver_employee_id'] ?? '') === (string)$gm['employee']['id'], 'Director bootstrap routes to the unique GM');

    approval_session($admin['user']);
    responsibility_assert_throws(
        fn () => QmsResponsibilityApprovalService::approveBootstrap((string)$bootstrap['id'], 'approved', '管理员不能代签'),
        'Admin role alone cannot sign as GM'
    );
    approval_session($gm['user']);
    Db::name('employee_appointments')->where('id', $legacyDirectorAppointmentId)->update(['status' => 'active']);
    responsibility_assert_throws(
        fn () => QmsResponsibilityApprovalService::approveBootstrap((string)$bootstrap['id'], 'approved', '不应越过新出现的主任冲突'),
        'A different active legacy director also blocks at final bootstrap approval'
    );
    catalog_assert(
        Db::name('qms_responsibility_approvals')->where('id', (string)$bootstrap['id'])->value('decision') === 'pending'
        && (int)Db::name('employee_appointments')->where('source_approval_id', (string)$bootstrap['id'])->count() === 0,
        'Blocked bootstrap approval remains pending and creates no appointment'
    );
    Db::name('employee_appointments')->where('id', $legacyDirectorAppointmentId)->update(['status' => 'inactive']);
    $approvedBootstrap = QmsResponsibilityApprovalService::approveBootstrap((string)$bootstrap['id'], 'approved', '同意任命');
    catalog_assert(($approvedBootstrap['decision'] ?? '') === 'approved', 'Staff GM can approve the bootstrap');
    $directorAppointment = Db::name('employee_appointments')
        ->where('source_approval_id', (string)$bootstrap['id'])
        ->where('employee_id', (string)$director['employee']['id'])
        ->find();
    catalog_assert(($directorAppointment['source_kind'] ?? '') === 'responsibility_chain', 'Director appointment is chain sourced');
    catalog_assert(($directorAppointment['appointed_at'] ?? '') === date('Y-m-d'), 'Director appointment keeps requested effective date');
    catalog_assert(Db::name('employee_appointments')->where('id', $sameCandidateLegacyId)->value('status') === 'active', 'Same-person legacy director evidence can coexist while controlled evidence is added');
    $bootstrapMetadata = json_decode((string)$approvedBootstrap['signature_metadata'], true);
    catalog_assert(($bootstrapMetadata['approved_as'] ?? '') === 'company_general_manager', 'Bootstrap signature records business identity');
    catalog_assert(($bootstrapMetadata['session_id'] ?? '') !== '', 'Bootstrap signature records session id');

    $prepared = approval_prepare_version($companyId, (string)$gm['employee']['id'], (string)$director['employee']['id']);
    $versionId = (string)$prepared['version']['id'];
    $preparedDetail = QmsResponsibilityDraftService::versionDetail($versionId);
    catalog_assert(count($preparedDetail['activities']) === 3 && count($preparedDetail['responsibilities']) === 21, 'Submission fixture covers all three activities and twenty-one duties');
    approval_session($qualityManager['user']);
    $submitted = QmsResponsibilityApprovalService::submitVersion($versionId);
    catalog_assert(($submitted['status'] ?? '') === 'pending_approval', 'Submission locks version pending approval');
    catalog_assert(strlen((string)$submitted['content_hash']) === 64, 'Submission stores a SHA-256 content hash');
    catalog_assert(
        (int)Db::name('qms_responsibility_assignments')->alias('ra')
            ->join('qms_activity_responsibilities r', 'r.id=ra.responsibility_id')
            ->join('qms_responsibility_activities a', 'a.id=r.activity_id')
            ->where('a.chain_version_id', $versionId)->where('ra.status', 'pending_approval')->count()
        === count(QmsResponsibilityDraftService::versionDetail($versionId)['assignments']),
        'All current draft assignments become pending'
    );
    $approvalCount = (int)Db::name('qms_responsibility_approvals')->where('chain_version_id', $versionId)->where('soft_delete', 0)->count();
    $sameSubmitted = QmsResponsibilityApprovalService::submitVersion($versionId);
    catalog_assert(($sameSubmitted['status'] ?? '') === 'pending_approval', 'Repeated submit is idempotent');
    catalog_assert((int)Db::name('qms_responsibility_approvals')->where('chain_version_id', $versionId)->where('soft_delete', 0)->count() === $approvalCount, 'Repeated submit creates no duplicate approvals');

    $gmBatch = approval_pending_for($versionId, (string)$gm['employee']['id']);
    catalog_assert(($gmBatch['subject_position_codes'] ?? []) === ['lab_director'], 'GM batch contains only lab director assignments');
    $directorBatch = approval_pending_for($versionId, (string)$director['employee']['id']);
    catalog_assert(!in_array('lab_director', $directorBatch['subject_position_codes'] ?? [], true), 'Director batch excludes lab director');
    catalog_assert(!in_array('company_general_manager', $directorBatch['subject_position_codes'] ?? [], true), 'Director batch excludes corporate GM');
    foreach (array_merge($gmBatch['items'], $directorBatch['items']) as $item) {
        foreach (['assignment_id', 'responsibility_id', 'employee_id', 'position_code', 'position_name', 'competence_snapshot', 'version_hash'] as $key) {
            catalog_assert(array_key_exists($key, $item), 'Pending item contains evidence field ' . $key);
        }
        catalog_assert((string)$item['assignment_id'] !== '' && (string)$item['responsibility_id'] !== '' && (string)$item['employee_id'] !== '', 'Pending item links assignment, responsibility and employee');
        catalog_assert((string)$item['position_code'] !== '' && (string)$item['position_name'] !== '', 'Pending item identifies a fixed position or activity role');
        catalog_assert((string)$item['version_hash'] === (string)$submitted['content_hash'], 'Pending item is pinned to the locked version hash');
    }

    approval_session($gm['user']);
    $selfApprovalId = (string)$gmBatch['items'][0]['approval_id'];
    $originalSubjectId = (string)Db::name('qms_responsibility_approvals')->where('id', $selfApprovalId)->value('subject_employee_id');
    Db::name('qms_responsibility_approvals')->where('id', $selfApprovalId)->update(['subject_employee_id' => (string)$gm['employee']['id']]);
    responsibility_assert_throws(
        fn () => QmsResponsibilityApprovalService::approveBatch((string)$gmBatch['batch_key'], 'approved', '签批阶段二次防自批'),
        'Approval stage independently blocks a tampered self-approval row'
    );
    catalog_assert(
        (int)Db::name('qms_responsibility_approvals')->where('batch_key', (string)$gmBatch['batch_key'])->where('decision', 'pending')->where('soft_delete', 0)->count() === count($gmBatch['items']),
        'Self-approval block leaves the whole batch pending'
    );
    Db::name('qms_responsibility_approvals')->where('id', $selfApprovalId)->update(['subject_employee_id' => $originalSubjectId]);
    QmsResponsibilityApprovalService::approveBatch((string)$gmBatch['batch_key'], 'approved', '总经理批准');
    approval_session($director['user']);

    $atRiskItem = $directorBatch['items'][0];
    Db::name('employees')->where('id', (string)$atRiskItem['employee_id'])->update(['publish' => 0]);
    responsibility_assert_throws(
        fn () => QmsResponsibilityApprovalService::approveBatch((string)$directorBatch['batch_key'], 'approved', '人员失效时不得生效'),
        'Final activation revalidates employee status under lock'
    );
    catalog_assert(
        (int)Db::name('qms_responsibility_approvals')->where('batch_key', (string)$directorBatch['batch_key'])->where('decision', 'pending')->where('soft_delete', 0)->count() === count($directorBatch['items']),
        'Employee invalidation rolls the final batch back to pending'
    );
    Db::name('employees')->where('id', (string)$atRiskItem['employee_id'])->update(['publish' => 1]);

    $competencyIdAtRisk = (string)($atRiskItem['competence_snapshot']['competency_record_ids'][0] ?? '');
    catalog_assert($competencyIdAtRisk !== '', 'Final validation fixture contains qualification evidence');
    Db::name('competency_records')->where('id', $competencyIdAtRisk)->update(['soft_delete' => 1]);
    responsibility_assert_throws(
        fn () => QmsResponsibilityApprovalService::approveBatch((string)$directorBatch['batch_key'], 'approved', '资格失效时不得生效'),
        'Final activation revalidates qualification evidence under lock'
    );
    catalog_assert(
        (int)Db::name('qms_responsibility_approvals')->where('batch_key', (string)$directorBatch['batch_key'])->where('decision', 'pending')->where('soft_delete', 0)->count() === count($directorBatch['items']),
        'Qualification invalidation rolls the final batch back to pending'
    );
    Db::name('competency_records')->where('id', $competencyIdAtRisk)->update(['soft_delete' => 0]);

    $approvedGmRow = Db::name('qms_responsibility_approvals')->where('id', (string)$gmBatch['items'][0]['approval_id'])->find();
    $fakeApprovalId = qms_uuid();
    $now = date('Y-m-d H:i:s');
    Db::name('qms_responsibility_approvals')->insert(array_merge($approvedGmRow, [
        'id' => $fakeApprovalId,
        'batch_key' => hash('sha256', 'forged-other-batch|' . $versionId),
        'comments' => '伪造的另一批已批准记录',
        'created' => $now,
        'modified' => $now,
    ]));
    responsibility_assert_throws(
        fn () => QmsResponsibilityApprovalService::approveBatch((string)$directorBatch['batch_key'], 'approved', '伪造批次存在时不得生效'),
        'Final activation rejects an extra forged approved row from the same submission round'
    );
    catalog_assert(
        Db::name('qms_responsibility_approvals')->where('id', $fakeApprovalId)->value('decision') === 'approved'
        && (int)Db::name('qms_responsibility_approvals')->where('batch_key', (string)$directorBatch['batch_key'])->where('decision', 'pending')->where('soft_delete', 0)->count() === count($directorBatch['items']),
        'Forged approval rejection rolls back only the attempted final signature'
    );
    Db::name('qms_responsibility_approvals')->where('id', $fakeApprovalId)->delete();

    $gmSignedMetadata = json_decode(
        (string)Db::name('qms_responsibility_approvals')
            ->where('id', (string)$gmBatch['items'][0]['approval_id'])
            ->value('signature_metadata'),
        true
    );
    Db::name('user_sessions')->where('id', (string)$gmSignedMetadata['session_id'])->update([
        'end_time' => date('Y-m-d H:i:s'),
    ]);

    QmsResponsibilityApprovalService::approveBatch((string)$directorBatch['batch_key'], 'approved', '主任批准');
    catalog_assert(QmsResponsibilityApprovalService::versionStatus($versionId) === 'effective', 'A historically valid GM signature remains valid after its session ends');
    $versionAssignmentCount = count(QmsResponsibilityDraftService::versionDetail($versionId)['assignments']);
    catalog_assert(
        (int)Db::name('qms_responsibility_assignments')->alias('ra')
            ->join('qms_activity_responsibilities r', 'r.id=ra.responsibility_id')
            ->join('qms_responsibility_activities a', 'a.id=r.activity_id')
            ->where('a.chain_version_id', $versionId)->where('ra.status', 'active')->where('ra.soft_delete', 0)->count() === $versionAssignmentCount,
        'Every submitted assignment becomes active on first activation'
    );
    $chainAppointments = Db::name('employee_appointments')->where('source_chain_version_id', $versionId)->where('status', 'active')->select()->toArray();
    catalog_assert($chainAppointments !== [], 'Activation creates chain appointments');
    catalog_assert(count(array_filter($chainAppointments, static fn (array $row): bool => (string)$row['appointment_type'] === 'responsibility')) === 1, 'One prebound activity role creates one responsibility appointment');
    $fixedAssignmentCount = (int)Db::name('qms_responsibility_assignments')->alias('ra')
        ->join('qms_activity_responsibilities r', 'r.id=ra.responsibility_id')
        ->join('qms_responsibility_activities a', 'a.id=r.activity_id')
        ->leftJoin('qms_positions p', 'p.id=r.fixed_position_id')
        ->where('a.chain_version_id', $versionId)->where('r.slot_kind', 'fixed_position')
        ->where('p.code', '<>', 'company_general_manager')->where('ra.soft_delete', 0)->count();
    $fixedAppointmentCount = count(array_filter($chainAppointments, static fn (array $row): bool => (string)$row['appointment_type'] === 'role'));
    catalog_assert($fixedAppointmentCount < $fixedAssignmentCount, 'Repeated fixed duties are aggregated by employee, position, site and version');
    catalog_assert(count(array_filter($chainAppointments, static fn (array $row): bool => (string)$row['employee_id'] === (string)$gm['employee']['id'])) === 0, 'Corporate GM duty creates no reverse chain appointment');
    catalog_assert(count(array_filter($chainAppointments, static fn (array $row): bool => (string)$row['source_responsibility_id'] === (string)$prepared['dynamic_responsibility_id'])) === 0, 'Dynamic slots create no appointments even if corrupt input contains a direct row');
    $hasAggregatedScope = false;
    foreach ($chainAppointments as $appointment) {
        catalog_assert((string)$appointment['source_chain_version_id'] === $versionId, 'Chain appointment references the effective version');
        catalog_assert((string)$appointment['source_responsibility_id'] !== '' && (string)$appointment['source_approval_id'] !== '', 'Chain appointment carries non-empty responsibility and approval evidence');
        catalog_assert((int)Db::name('qms_activity_responsibilities')->where('id', (string)$appointment['source_responsibility_id'])->count() === 1, 'Appointment responsibility evidence resolves to a real row');
        catalog_assert((int)Db::name('qms_responsibility_approvals')->where('id', (string)$appointment['source_approval_id'])->where('decision', 'approved')->count() === 1, 'Appointment approval evidence resolves to an approved row');
        $scope = json_decode((string)$appointment['appointment_scope'], true);
        catalog_assert(
            is_array($scope)
            && ($scope['responsibility_ids'] ?? []) !== []
            && ($scope['step_codes'] ?? []) !== []
            && ($scope['approval_ids'] ?? []) !== [],
            'Appointment scope lists all linked responsibilities, steps and approvals'
        );
        $sortedApprovalIds = $scope['approval_ids'];
        sort($sortedApprovalIds, SORT_STRING);
        catalog_assert($sortedApprovalIds === $scope['approval_ids'], 'Appointment scope approval ids use stable sorting');
        catalog_assert(count($scope['approval_ids']) === count($scope['responsibility_ids']), 'Every aggregated responsibility has approval evidence');
        foreach ($scope['responsibility_ids'] as $scopeResponsibilityId) {
            catalog_assert((int)Db::name('qms_activity_responsibilities')->where('id', (string)$scopeResponsibilityId)->count() === 1, 'Every aggregated responsibility id resolves');
        }
        foreach ($scope['approval_ids'] as $scopeApprovalId) {
            $scopeApproval = Db::name('qms_responsibility_approvals')->where('id', (string)$scopeApprovalId)->where('decision', 'approved')->find();
            catalog_assert((bool)$scopeApproval, 'Every aggregated approval id resolves to an approved row');
            $scopeAssignmentResponsibility = Db::name('qms_responsibility_assignments')
                ->where('id', (string)$scopeApproval['assignment_id'])->value('responsibility_id');
            catalog_assert(in_array((string)$scopeAssignmentResponsibility, $scope['responsibility_ids'], true), 'Every aggregated approval belongs to a responsibility in the same scope');
        }
        $primaryApproval = Db::name('qms_responsibility_approvals')->where('id', (string)$appointment['source_approval_id'])->find();
        $primaryResponsibilityId = Db::name('qms_responsibility_assignments')
            ->where('id', (string)$primaryApproval['assignment_id'])->value('responsibility_id');
        catalog_assert((string)$primaryResponsibilityId === (string)$appointment['source_responsibility_id'], 'Primary responsibility and approval form one deterministic pair');
        $hasAggregatedScope = $hasAggregatedScope || count($scope['responsibility_ids']) > 1;
    }
    catalog_assert($hasAggregatedScope, 'At least one fixed appointment scope preserves multiple aggregated duties');
    $signatureRows = Db::name('qms_responsibility_approvals')->where('chain_version_id', $versionId)->where('decision', 'approved')->select()->toArray();
    foreach ($signatureRows as $signatureRow) {
        $metadata = json_decode((string)$signatureRow['signature_metadata'], true);
        catalog_assert(($metadata['session_id'] ?? '') !== '' && ($metadata['employee_id'] ?? '') !== '', 'Each signature records session and employee');
    }

    // Legacy insert remains backward compatible through the database default.
    $legacyId = responsibility_fixture_row('employee_appointments', [
        'company_id' => $companyId,
        'employee_id' => (string)$prepared['staff'][0]['employee']['id'],
        'appointment_key' => 'LEGACY-' . qms_uuid(),
        'appointment_type' => 'role',
        'position_name' => '旧字段任命',
        'appointed_at' => date('Y-m-d'),
        'status' => 'active',
    ]);
    catalog_assert(Db::name('employee_appointments')->where('id', $legacyId)->value('source_kind') === 'legacy_document', 'Old appointment insert defaults to legacy_document');

    // A rejected batch returns a cloned change version to editable draft and closes sibling pending rows.
    $rejectClone = QmsResponsibilityDraftService::cloneEffectiveVersion($versionId);
    $rejectId = (string)$rejectClone['version']['id'];
    approval_session($qualityManager['user']);
    QmsResponsibilityApprovalService::submitVersion($rejectId);
    $rejectBatch = approval_pending_for($rejectId, (string)$gm['employee']['id']);
    $oldDirectorBatch = approval_pending_for($rejectId, (string)$director['employee']['id']);
    approval_session($gm['user']);
    QmsResponsibilityApprovalService::approveBatch((string)$rejectBatch['batch_key'], 'rejected', '退回修改');
    $rejectedVersion = Db::name('qms_responsibility_chain_versions')->where('id', $rejectId)->find();
    catalog_assert($rejectedVersion['status'] === 'draft' && $rejectedVersion['content_hash'] === null && $rejectedVersion['locked_at'] === null, 'Rejection unlocks the version');
    $rejectAssignmentCount = count(QmsResponsibilityDraftService::versionDetail($rejectId)['assignments']);
    catalog_assert(
        (int)Db::name('qms_responsibility_assignments')->alias('ra')
            ->join('qms_activity_responsibilities r', 'r.id=ra.responsibility_id')
            ->join('qms_responsibility_activities a', 'a.id=r.activity_id')
            ->where('a.chain_version_id', $rejectId)->where('ra.status', 'draft')->where('ra.soft_delete', 0)->count() === $rejectAssignmentCount,
        'Rejection returns every current assignment to draft'
    );
    catalog_assert((int)Db::name('qms_responsibility_approvals')->where('chain_version_id', $rejectId)->where('decision', 'pending')->where('soft_delete', 0)->count() === 0, 'Rejection leaves no readable pending approvals');
    $oldRejectedApprovalId = (string)Db::name('qms_responsibility_approvals')
        ->where('chain_version_id', $rejectId)->where('batch_key', (string)$rejectBatch['batch_key'])->where('decision', 'rejected')->value('id');
    catalog_assert($oldRejectedApprovalId !== '', 'Rejected approval history remains identifiable');
    catalog_assert(
        (int)Db::name('qms_responsibility_approvals')->where('batch_key', (string)$oldDirectorBatch['batch_key'])->where('decision', 'pending')->where('soft_delete', 1)->count() === count($oldDirectorBatch['items']),
        'Sibling pending approvals are soft-closed after rejection'
    );

    approval_session($qualityManager['user']);
    $resubmittedReject = QmsResponsibilityApprovalService::submitVersion($rejectId);
    $newRejectGmBatch = approval_pending_for($rejectId, (string)$gm['employee']['id']);
    $newRejectDirectorBatch = approval_pending_for($rejectId, (string)$director['employee']['id']);
    catalog_assert((string)$newRejectGmBatch['batch_key'] !== (string)$rejectBatch['batch_key'], 'Resubmission creates a new approval round and batch key');
    catalog_assert((string)$newRejectDirectorBatch['batch_key'] !== (string)$oldDirectorBatch['batch_key'], 'Both approver batches use the new approval round');
    catalog_assert(
        Db::name('qms_responsibility_approvals')->where('id', $oldRejectedApprovalId)->value('decision') === 'rejected'
        && (int)Db::name('qms_responsibility_approvals')->where('id', $oldRejectedApprovalId)->value('soft_delete') === 0,
        'Old rejected decision remains visible as history after resubmission'
    );
    catalog_assert(
        (int)Db::name('qms_responsibility_approvals')->where('batch_key', (string)$oldDirectorBatch['batch_key'])->where('decision', 'pending')->where('soft_delete', 1)->count() === count($oldDirectorBatch['items']),
        'Old sibling pending rows remain closed and cannot reappear in pending batch reads'
    );
    catalog_assert((string)$resubmittedReject['content_hash'] !== '' && (string)$resubmittedReject['content_hash'] === (string)$newRejectGmBatch['items'][0]['version_hash'], 'Resubmitted round is pinned to its new locked hash');
    approval_session($gm['user']);
    QmsResponsibilityApprovalService::approveBatch((string)$newRejectGmBatch['batch_key'], 'approved', '重提后总经理批准');
    approval_session($director['user']);
    QmsResponsibilityApprovalService::approveBatch((string)$newRejectDirectorBatch['batch_key'], 'approved', '重提后主任批准');
    catalog_assert(QmsResponsibilityApprovalService::versionStatus($rejectId) === 'effective', 'Old rejected history does not block resubmitted round final activation');
    catalog_assert(QmsResponsibilityApprovalService::versionStatus($versionId) === 'superseded', 'Resubmitted version supersedes the first effective version');
    $effectiveVersionId = $rejectId;

    // Hash tampering prevents approval.
    $tamperClone = QmsResponsibilityDraftService::cloneEffectiveVersion($effectiveVersionId);
    $tamperId = (string)$tamperClone['version']['id'];
    approval_session($qualityManager['user']);
    QmsResponsibilityApprovalService::submitVersion($tamperId);
    $tamperBatch = approval_pending_for($tamperId, (string)$gm['employee']['id']);
    $tamperedAssignmentId = (string)Db::name('qms_responsibility_assignments')->alias('ra')
        ->join('qms_activity_responsibilities r', 'r.id=ra.responsibility_id')
        ->join('qms_responsibility_activities a', 'a.id=r.activity_id')
        ->where('a.chain_version_id', $tamperId)->order('ra.id')->value('ra.id');
    Db::name('qms_responsibility_assignments')->where('id', $tamperedAssignmentId)
        ->update(['proposed_until' => date('Y-m-d', strtotime('+10 years'))]);
    approval_session($gm['user']);
    responsibility_assert_throws(
        fn () => QmsResponsibilityApprovalService::approveBatch((string)$tamperBatch['batch_key'], 'approved', '不应通过'),
        'Tampered content hash blocks approval'
    );
    Db::name('qms_responsibility_chain_versions')->where('id', $tamperId)->update(['soft_delete' => 1]);

    // A predictable conflict on the second generated appointment proves atomic rollback, then retry succeeds.
    $v2 = QmsResponsibilityDraftService::cloneEffectiveVersion($effectiveVersionId);
    $v2Id = (string)$v2['version']['id'];
    approval_session($qualityManager['user']);
    QmsResponsibilityApprovalService::submitVersion($v2Id);
    $v2GmBatch = approval_pending_for($v2Id, (string)$gm['employee']['id']);
    $v2DirectorBatch = approval_pending_for($v2Id, (string)$director['employee']['id']);
    approval_session($gm['user']);
    QmsResponsibilityApprovalService::approveBatch((string)$v2GmBatch['batch_key'], 'approved', '批准 v2');
    $predictedKeys = approval_expected_appointment_keys($v2Id);
    catalog_assert(count($predictedKeys) >= 2, 'Fixture has at least two generated appointment groups');
    $conflictId = responsibility_fixture_row('employee_appointments', [
        'company_id' => $companyId,
        'employee_id' => (string)$prepared['staff'][0]['employee']['id'],
        'appointment_key' => (string)$predictedKeys[1],
        'appointment_type' => 'role',
        'position_name' => '事务故障注入',
        'appointed_at' => date('Y-m-d'),
        'status' => 'active',
    ]);
    approval_session($director['user']);
    responsibility_assert_throws(
        fn () => QmsResponsibilityApprovalService::approveBatch((string)$v2DirectorBatch['batch_key'], 'approved', '应整体回滚'),
        'Second appointment conflict rolls the final approval transaction back'
    );
    catalog_assert(QmsResponsibilityApprovalService::versionStatus($v2Id) === 'pending_approval', 'Failed final activation leaves new version pending');
    catalog_assert(QmsResponsibilityApprovalService::versionStatus($effectiveVersionId) === 'effective', 'Failed final activation leaves old version effective');
    catalog_assert((int)Db::name('employee_appointments')->where('source_chain_version_id', $v2Id)->count() === 0, 'Failed final activation creates no new appointments');
    catalog_assert((int)Db::name('qms_responsibility_assignments')->alias('ra')->join('qms_activity_responsibilities r', 'r.id=ra.responsibility_id')->join('qms_responsibility_activities a', 'a.id=r.activity_id')->where('a.chain_version_id', $v2Id)->where('ra.status', 'pending_approval')->count() > 0, 'Failed activation leaves assignments pending');
    catalog_assert((int)Db::name('employee_appointments')->where('source_chain_version_id', $effectiveVersionId)->where('status', 'active')->count() > 0, 'Failed activation leaves old appointments active');
    catalog_assert(Db::name('qms_responsibility_approvals')->where('batch_key', (string)$v2DirectorBatch['batch_key'])->where('soft_delete', 0)->value('decision') === 'pending', 'Failed final signature remains pending');

    Db::name('employee_appointments')->where('id', $conflictId)->delete();
    QmsResponsibilityApprovalService::approveBatch((string)$v2DirectorBatch['batch_key'], 'approved', '重试通过');
    catalog_assert(QmsResponsibilityApprovalService::versionStatus($v2Id) === 'effective', 'Retry activates v2');
    catalog_assert(QmsResponsibilityApprovalService::versionStatus($effectiveVersionId) === 'superseded', 'Retry supersedes the previous effective version');
    $oldAssignmentTotal = count(QmsResponsibilityDraftService::versionDetail($effectiveVersionId)['assignments']);
    catalog_assert(
        (int)Db::name('qms_responsibility_assignments')->alias('ra')
            ->join('qms_activity_responsibilities r', 'r.id=ra.responsibility_id')
            ->join('qms_responsibility_activities a', 'a.id=r.activity_id')
            ->where('a.chain_version_id', $effectiveVersionId)->where('ra.status', 'revoked')->where('ra.soft_delete', 0)->count() === $oldAssignmentTotal,
        'Successful replacement revokes every assignment from the previous effective version'
    );
    catalog_assert((int)Db::name('employee_appointments')->where('source_chain_version_id', $effectiveVersionId)->where('status', 'revoked')->count() > 0, 'Retry revokes old chain appointments');
    catalog_assert(Db::name('employee_appointments')->where('id', $legacyId)->value('status') === 'active', 'Supersession never revokes unrelated legacy appointments');
    catalog_assert((int)Db::name('employee_appointments')->where('source_kind', 'corporate_evidence')->where('status', 'active')->count() === 1, 'Corporate identity remains active after supersession');
});

// Two real database connections prove that a concurrent evidence update and final activation serialize.
$companyId = catalog_company_id();
$employeesBefore = array_map('strval', Db::name('employees')->where('company_id', $companyId)->column('id'));
$concurrencyVersionId = '';
$concurrencyEmployeeIds = [];
$competingConnection = null;
$worker = null;
$workerPipes = [];
$readyPath = sys_get_temp_dir() . '/qms-activation-' . str_replace('-', '', qms_uuid()) . '.ready';
$concurrencyFailure = null;
try {
    $admin = approval_employee($companyId, 'CONCURRENT-ADMIN', true, 'admin');
    $qualityManager = approval_employee($companyId, 'CONCURRENT-QM', true, 'quality_manager');
    $gm = approval_employee($companyId, 'CONCURRENT-GM', true, 'staff');
    $director = approval_employee($companyId, 'CONCURRENT-DIRECTOR', true, 'staff');

    approval_session($admin['user']);
    QmsResponsibilityApprovalService::registerCorporateIdentity([
        'position_code' => 'company_general_manager',
        'employee_id' => (string)$gm['employee']['id'],
        'appointed_at' => date('Y-m-d'),
        'source_document_number' => 'CONCURRENT-CORP-' . substr(qms_uuid(), 0, 8),
        'source_excerpt' => '并发生效测试公司治理证据',
    ]);
    $bootstrap = QmsResponsibilityApprovalService::requestLabDirectorAppointment(
        (string)$director['employee']['id'],
        date('Y-m-d')
    );
    approval_session($gm['user']);
    QmsResponsibilityApprovalService::approveBootstrap((string)$bootstrap['id'], 'approved', '并发测试主任任命');

    $prepared = approval_prepare_version(
        $companyId,
        (string)$gm['employee']['id'],
        (string)$director['employee']['id']
    );
    $concurrencyVersionId = (string)$prepared['version']['id'];
    approval_session($qualityManager['user']);
    QmsResponsibilityApprovalService::submitVersion($concurrencyVersionId);
    $gmBatch = approval_pending_for($concurrencyVersionId, (string)$gm['employee']['id']);
    $directorBatch = approval_pending_for($concurrencyVersionId, (string)$director['employee']['id']);
    approval_session($gm['user']);
    QmsResponsibilityApprovalService::approveBatch((string)$gmBatch['batch_key'], 'approved', '并发测试总经理批准');
    approval_session($director['user']);
    $directorSession = Session::get('user');
    approval_concurrency_assert(is_array($directorSession), 'Director session payload is available to the worker');

    $targetEmployeeId = (string)($directorBatch['items'][0]['employee_id'] ?? '');
    approval_concurrency_assert($targetEmployeeId !== '', 'Concurrency fixture has an assignment employee to invalidate');
    $concurrencyEmployeeIds = array_values(array_diff(
        array_map('strval', Db::name('employees')->where('company_id', $companyId)->column('id')),
        $employeesBefore
    ));

    $dbHost = (string)(getenv('DB_HOST') ?: 'db');
    $dbPort = (string)(getenv('DB_PORT') ?: '3306');
    $dbName = (string)(getenv('DB_NAME') ?: 'jewelry_qms');
    $dbUser = (string)(getenv('DB_USER') ?: 'root');
    $dbPass = (string)(getenv('DB_PASS') ?: '');
    $competingConnection = new PDO(
        "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4",
        $dbUser,
        $dbPass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    $competingConnection->beginTransaction();
    $update = $competingConnection->prepare('UPDATE employees SET publish = 0 WHERE id = ?');
    $update->execute([$targetEmployeeId]);
    approval_concurrency_assert($update->rowCount() === 1, 'Competing connection holds an uncommitted employee invalidation');

    $command = [
        PHP_BINARY,
        __DIR__ . '/support/qms_responsibility_activation_worker.php',
        $readyPath,
        base64_encode(json_encode($directorSession, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)),
        (string)$directorBatch['batch_key'],
    ];
    $worker = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $workerPipes);
    approval_concurrency_assert(is_resource($worker), 'Activation worker process starts');
    approval_wait_for_file($readyPath, 5.0);
    usleep(750_000);
    $blockedStatus = proc_get_status($worker);
    approval_concurrency_assert(
        (bool)($blockedStatus['running'] ?? false),
        'Final activation waits for the in-flight employee evidence update'
    );

    $competingConnection->commit();
    $workerResult = approval_wait_for_process($worker, $workerPipes, 10.0);
    $worker = null;
    $workerPipes = [];
    approval_concurrency_assert($workerResult['exit_code'] === 0, 'Activation worker exits cleanly: ' . $workerResult['stderr']);
    $workerPayload = json_decode($workerResult['stdout'], true, 512, JSON_THROW_ON_ERROR);
    approval_concurrency_assert(
        ($workerPayload['result'] ?? '') === 'blocked',
        'Activation reruns validation after the lock and rejects the newly invalid employee'
    );
    approval_concurrency_assert(
        QmsResponsibilityApprovalService::versionStatus($concurrencyVersionId) === 'pending_approval',
        'Rejected stale activation leaves the version pending approval'
    );
    approval_concurrency_assert(
        (int)Db::name('qms_responsibility_approvals')
            ->where('batch_key', (string)$directorBatch['batch_key'])
            ->where('decision', 'pending')->where('soft_delete', 0)->count() === count($directorBatch['items']),
        'Rejected stale activation rolls the final batch back to pending'
    );
    approval_concurrency_assert(
        (int)Db::name('employee_appointments')->where('source_chain_version_id', $concurrencyVersionId)->count() === 0,
        'Rejected stale activation generates no appointments'
    );
} catch (Throwable $exception) {
    $concurrencyFailure = $exception;
} finally {
    if ($competingConnection instanceof PDO && $competingConnection->inTransaction()) {
        $competingConnection->rollBack();
    }
    if (is_resource($worker)) {
        proc_terminate($worker);
        foreach ($workerPipes as $pipe) {
            if (is_resource($pipe)) {
                fclose($pipe);
            }
        }
        proc_close($worker);
    }
    @unlink($readyPath);
    approval_cleanup_concurrency_fixture($companyId, $concurrencyVersionId, $concurrencyEmployeeIds);
}
if ($concurrencyFailure instanceof Throwable) {
    fwrite(STDERR, $concurrencyFailure->getMessage() . PHP_EOL);
    exit(1);
}

echo "qms responsibility approval smoke: OK\n";
