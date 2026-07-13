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

function approval_session(array $user): void
{
    Session::set('user', [
        'id' => (string)$user['id'],
        'employee_id' => (string)($user['employee_id'] ?? ''),
        'role' => (string)$user['role'],
        'session_id' => 'SIG-' . substr(qms_uuid(), 0, 8),
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

catalog_in_transaction(function (): void {
    $companyId = catalog_company_id();
    $admin = approval_employee($companyId, 'ADMIN', true, 'admin');
    $qualityManager = approval_employee($companyId, 'QM', true, 'quality_manager');
    $gm = approval_employee($companyId, 'GM', true, 'staff');
    $director = approval_employee($companyId, 'DIRECTOR', true, 'staff');
    $noUser = approval_employee($companyId, 'NO-USER', false);

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
    $bootstrap = QmsResponsibilityApprovalService::requestLabDirectorAppointment((string)$director['employee']['id'], date('Y-m-d'));
    catalog_assert(($bootstrap['approver_employee_id'] ?? '') === (string)$gm['employee']['id'], 'Director bootstrap routes to the unique GM');

    approval_session($admin['user']);
    responsibility_assert_throws(
        fn () => QmsResponsibilityApprovalService::approveBootstrap((string)$bootstrap['id'], 'approved', '管理员不能代签'),
        'Admin role alone cannot sign as GM'
    );
    approval_session($gm['user']);
    $approvedBootstrap = QmsResponsibilityApprovalService::approveBootstrap((string)$bootstrap['id'], 'approved', '同意任命');
    catalog_assert(($approvedBootstrap['decision'] ?? '') === 'approved', 'Staff GM can approve the bootstrap');
    $directorAppointment = Db::name('employee_appointments')
        ->where('source_approval_id', (string)$bootstrap['id'])
        ->where('employee_id', (string)$director['employee']['id'])
        ->find();
    catalog_assert(($directorAppointment['source_kind'] ?? '') === 'responsibility_chain', 'Director appointment is chain sourced');
    catalog_assert(($directorAppointment['appointed_at'] ?? '') === date('Y-m-d'), 'Director appointment keeps requested effective date');
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

    approval_session($gm['user']);
    QmsResponsibilityApprovalService::approveBatch((string)$gmBatch['batch_key'], 'approved', '总经理批准');
    approval_session($director['user']);
    QmsResponsibilityApprovalService::approveBatch((string)$directorBatch['batch_key'], 'approved', '主任批准');
    catalog_assert(QmsResponsibilityApprovalService::versionStatus($versionId) === 'effective', 'Last approval activates the version');
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
    approval_session($gm['user']);
    QmsResponsibilityApprovalService::approveBatch((string)$rejectBatch['batch_key'], 'rejected', '退回修改');
    $rejectedVersion = Db::name('qms_responsibility_chain_versions')->where('id', $rejectId)->find();
    catalog_assert($rejectedVersion['status'] === 'draft' && $rejectedVersion['content_hash'] === null && $rejectedVersion['locked_at'] === null, 'Rejection unlocks the version');
    catalog_assert((int)Db::name('qms_responsibility_approvals')->where('chain_version_id', $rejectId)->where('decision', 'pending')->where('soft_delete', 0)->count() === 0, 'Rejection leaves no readable pending approvals');

    // Hash tampering prevents approval.
    $tamperClone = QmsResponsibilityDraftService::cloneEffectiveVersion($versionId);
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
    $v2 = QmsResponsibilityDraftService::cloneEffectiveVersion($versionId);
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
    catalog_assert(QmsResponsibilityApprovalService::versionStatus($versionId) === 'effective', 'Failed final activation leaves old version effective');
    catalog_assert((int)Db::name('employee_appointments')->where('source_chain_version_id', $v2Id)->count() === 0, 'Failed final activation creates no new appointments');
    catalog_assert((int)Db::name('qms_responsibility_assignments')->alias('ra')->join('qms_activity_responsibilities r', 'r.id=ra.responsibility_id')->join('qms_responsibility_activities a', 'a.id=r.activity_id')->where('a.chain_version_id', $v2Id)->where('ra.status', 'pending_approval')->count() > 0, 'Failed activation leaves assignments pending');
    catalog_assert((int)Db::name('employee_appointments')->where('source_chain_version_id', $versionId)->where('status', 'active')->count() > 0, 'Failed activation leaves old appointments active');
    catalog_assert(Db::name('qms_responsibility_approvals')->where('batch_key', (string)$v2DirectorBatch['batch_key'])->where('soft_delete', 0)->value('decision') === 'pending', 'Failed final signature remains pending');

    Db::name('employee_appointments')->where('id', $conflictId)->delete();
    QmsResponsibilityApprovalService::approveBatch((string)$v2DirectorBatch['batch_key'], 'approved', '重试通过');
    catalog_assert(QmsResponsibilityApprovalService::versionStatus($v2Id) === 'effective', 'Retry activates v2');
    catalog_assert(QmsResponsibilityApprovalService::versionStatus($versionId) === 'superseded', 'Retry supersedes v1');
    catalog_assert((int)Db::name('employee_appointments')->where('source_chain_version_id', $versionId)->where('status', 'revoked')->count() > 0, 'Retry revokes old chain appointments');
    catalog_assert((int)Db::name('employee_appointments')->where('source_kind', 'corporate_evidence')->where('status', 'active')->count() === 1, 'Corporate identity remains active after supersession');
});

echo "qms responsibility approval smoke: OK\n";
