<?php
declare(strict_types=1);

require __DIR__ . '/support/qms_responsibility_fixture.php';

use app\service\QmsManualProcedureAlignmentService;
use app\service\QmsManualProcedureTraceService;
use app\service\QmsResponsibilityAlignmentService;
use app\service\QmsResponsibilityApprovalService;
use app\service\QmsResponsibilityCatalogService;
use app\service\QmsResponsibilityDraftService;
use app\service\QmsResponsibilityValidationService;
use think\facade\Db;
use think\facade\Session;

function responsibility_e2e_assert(bool $condition, string $message): void
{
    catalog_assert($condition, 'E2E: ' . $message);
}

/** @return array{employee:array,user:array} */
function responsibility_e2e_person(string $companyId, string $label, string $role): array
{
    $employeeId = responsibility_fixture_row('employees', [
        'company_id' => $companyId,
        'employee_number' => 'E2E-' . strtoupper(substr(str_replace('-', '', qms_uuid()), 0, 10)),
        'name' => '责任链端到端-' . $label,
    ]);
    $userId = responsibility_fixture_row('users', [
        'company_id' => $companyId,
        'employee_id' => $employeeId,
        'username' => 'e2e_' . strtolower(substr(str_replace('-', '', qms_uuid()), 0, 12)),
        'password' => password_hash('test-only', PASSWORD_DEFAULT),
        'name' => '责任链端到端用户-' . $label,
        'role' => $role,
    ]);

    return [
        'employee' => Db::name('employees')->where('id', $employeeId)->find(),
        'user' => Db::name('users')->where('id', $userId)->find(),
    ];
}

function responsibility_e2e_session(array $person): void
{
    $sessionId = 'E2E-' . substr(str_replace('-', '', qms_uuid()), 0, 20);
    Db::name('user_sessions')->insert([
        'id' => $sessionId,
        'user_id' => (string)$person['user']['id'],
        'start_time' => date('Y-m-d H:i:s'),
        'end_time' => null,
        'ip_address' => '127.0.0.1',
    ]);
    Session::set('user', [
        'id' => (string)$person['user']['id'],
        'employee_id' => (string)$person['employee']['id'],
        'role' => (string)$person['user']['role'],
        'session_id' => $sessionId,
    ]);
}

/** @return array<string,string> */
function responsibility_e2e_user_roles(string $companyId): array
{
    $rows = Db::name('users')
        ->where('company_id', $companyId)
        ->where('soft_delete', 0)
        ->field('id,role')
        ->select()
        ->toArray();
    $roles = [];
    foreach ($rows as $row) {
        $roles[(string)$row['id']] = (string)$row['role'];
    }
    ksort($roles, SORT_STRING);

    return $roles;
}

/** Bind only template-level named-person duties; all runtime slots stay unbound. */
function responsibility_e2e_bind_staff(
    string $companyId,
    string $versionId,
    array $generalManager,
    array $labDirector
): array {
    $detail = QmsResponsibilityDraftService::versionDetail($versionId);
    $peopleBySlot = [];
    $createdPeople = [];

    foreach ($detail['responsibilities'] as $responsibility) {
        $mode = (string)$responsibility['assignment_mode'];
        if ($mode !== 'named_person') {
            continue;
        }

        $positionCode = (string)($responsibility['fixed_position_code'] ?? '');
        if ($positionCode === 'company_general_manager') {
            $person = $generalManager;
        } elseif ($positionCode === 'lab_director') {
            $person = $labDirector;
        } else {
            $slotKey = $positionCode;
            if (!isset($peopleBySlot[$slotKey])) {
                $peopleBySlot[$slotKey] = responsibility_e2e_person(
                    $companyId,
                    'SLOT-' . count($peopleBySlot),
                    'staff'
                );
                $createdPeople[] = $peopleBySlot[$slotKey];
            }
            $person = $peopleBySlot[$slotKey];
        }

        $employeeId = (string)$person['employee']['id'];
        $competencyId = responsibility_fixture_row('competency_records', [
            'company_id' => $companyId,
            'employee_id' => $employeeId,
            'test_item' => '责任链端到端资格证据',
            'assessment_date' => date('Y-m-d'),
            'result' => 'qualified',
            'valid_until' => date('Y-m-d', strtotime('+2 years')),
        ]);
        QmsResponsibilityDraftService::saveAssignment(
            (string)$responsibility['id'],
            $employeeId,
            null,
            date('Y-m-d'),
            null,
            ['competency_record_ids' => [$competencyId]]
        );
    }

    return $createdPeople;
}

/** @return array<string,array> */
function responsibility_e2e_findings(array $findings): array
{
    $indexed = [];
    foreach ($findings as $finding) {
        $indexed[(string)$finding['finding_id']] = $finding;
    }

    return $indexed;
}

$evidence = [];

catalog_in_transaction(function () use (&$evidence): void {
    $companyId = catalog_company_id();
    $admin = responsibility_e2e_person($companyId, 'ADMIN', 'admin');
    $qualityManager = responsibility_e2e_person($companyId, 'QUALITY-MANAGER', 'quality_manager');
    $generalManager = responsibility_e2e_person($companyId, 'GENERAL-MANAGER', 'staff');
    $labDirector = responsibility_e2e_person($companyId, 'LAB-DIRECTOR', 'staff');

    responsibility_e2e_session($qualityManager);
    $draft = QmsResponsibilityCatalogService::createInitialDraft();
    $versionId = (string)$draft['id'];
    $structure = QmsResponsibilityValidationService::validateVersion($versionId, 'structure');
    responsibility_e2e_assert(($structure['result'] ?? '') === 'warning', 'empty staffing is a structure warning');
    responsibility_e2e_assert(($structure['can_save'] ?? false) === true, 'empty staffing can still be saved');

    responsibility_e2e_bind_staff($companyId, $versionId, $generalManager, $labDirector);
    $withoutGovernance = QmsResponsibilityValidationService::validateVersion($versionId, 'activation');
    responsibility_e2e_assert(($withoutGovernance['result'] ?? '') === 'blocker', 'missing governance identities block activation');

    responsibility_e2e_session($admin);
    QmsResponsibilityApprovalService::registerCorporateIdentity([
        'position_code' => 'company_general_manager',
        'employee_id' => (string)$generalManager['employee']['id'],
        'source_document_number' => 'E2E-CORP-EVIDENCE',
        'source_excerpt' => '隔离测试中的既有公司治理证据',
        'appointed_at' => date('Y-m-d'),
    ]);
    $bootstrap = QmsResponsibilityApprovalService::requestLabDirectorAppointment(
        (string)$labDirector['employee']['id'],
        date('Y-m-d')
    );
    responsibility_e2e_session($generalManager);
    QmsResponsibilityApprovalService::approveBootstrap((string)$bootstrap['id'], 'approved', '端到端测试批准');

    $activation = QmsResponsibilityValidationService::validateVersion($versionId, 'activation');
    responsibility_e2e_assert(($activation['result'] ?? '') === 'pass', 'governance identities and staffing pass activation');
    responsibility_e2e_assert(($activation['can_submit'] ?? false) === true, 'validated version can be submitted');

    $rolesBefore = responsibility_e2e_user_roles($companyId);
    responsibility_e2e_session($qualityManager);
    $submitted = QmsResponsibilityApprovalService::submitVersion($versionId);
    responsibility_e2e_assert(($submitted['status'] ?? '') === 'pending_approval', 'submission creates a pending approval version');

    $gmBatch = QmsResponsibilityApprovalService::pendingBatchForApprover(
        $versionId,
        (string)$generalManager['employee']['id']
    );
    $directorBatch = QmsResponsibilityApprovalService::pendingBatchForApprover(
        $versionId,
        (string)$labDirector['employee']['id']
    );
    responsibility_e2e_assert(($gmBatch['items'] ?? []) !== [], 'general manager approval batch is present');
    responsibility_e2e_assert(($directorBatch['items'] ?? []) !== [], 'lab director approval batch is present');
    responsibility_e2e_assert(count($gmBatch['items']) === 3, 'general manager receives the three lab-director duties');
    responsibility_e2e_assert(count($directorBatch['items']) === 8, 'lab director receives the eight other named-person duties');

    responsibility_e2e_session($generalManager);
    QmsResponsibilityApprovalService::approveBatch((string)$gmBatch['batch_key'], 'approved', '端到端总经理批次批准');
    responsibility_e2e_session($labDirector);
    QmsResponsibilityApprovalService::approveBatch((string)$directorBatch['batch_key'], 'approved', '端到端主任批次批准');

    responsibility_e2e_assert(
        QmsResponsibilityApprovalService::versionStatus($versionId) === 'effective',
        'all approved batches activate the version'
    );
    $appointmentCount = (int)Db::name('employee_appointments')
        ->where('source_chain_version_id', $versionId)
        ->where('source_kind', 'responsibility_chain')
        ->where('status', 'active')
        ->where('soft_delete', 0)
        ->count();
    $appointmentGroups = Db::name('employee_appointments')
        ->where('source_chain_version_id', $versionId)
        ->where('source_kind', 'responsibility_chain')
        ->where('status', 'active')
        ->where('soft_delete', 0)
        ->field('employee_id,position_id,site_id,appointment_type')
        ->select()
        ->toArray();
    responsibility_e2e_assert($appointmentCount === count($appointmentGroups), 'appointment count matches persisted named-person groups');
    responsibility_e2e_assert($appointmentCount === 4, 'twelve named-person duties aggregate into four effective role appointments');

    $change = QmsResponsibilityDraftService::cloneEffectiveVersion($versionId);
    responsibility_e2e_assert(($change['version']['status'] ?? '') === 'draft', 'change starts as a draft clone');
    responsibility_e2e_assert(
        QmsResponsibilityApprovalService::versionStatus($versionId) === 'effective',
        'old version remains effective while a change draft exists'
    );
    $rolesAfter = responsibility_e2e_user_roles($companyId);
    responsibility_e2e_assert($rolesAfter === $rolesBefore, 'responsibility appointments do not alter RBAC roles');

    $fixture = __DIR__ . '/fixtures/qms_manual_procedure_alignment';
    $loaded = QmsManualProcedureAlignmentService::loadInputs(
        $fixture . '/pilot-spec.json',
        $fixture . '/procedures'
    );
    $trace = QmsManualProcedureTraceService::fromSnapshot($fixture . '/trace-snapshot.json');
    $baseline = QmsResponsibilityAlignmentService::baselineForVersion($versionId);
    $alignedInputs = QmsResponsibilityAlignmentService::injectBaseline($loaded, $baseline);
    $alignment = QmsManualProcedureAlignmentService::check($alignedInputs, $trace);
    $findings = responsibility_e2e_findings($alignment['findings']);
    responsibility_e2e_assert(($findings['Y13-CX20']['status'] ?? '') === 'conflict', 'Y13-CX20 is a conflict');
    responsibility_e2e_assert(($findings['Y13-CX21']['status'] ?? '') === 'conflict', 'Y13-CX21 is a conflict');
    responsibility_e2e_assert(($findings['Y13-CX32']['status'] ?? '') === 'review_required', 'Y13-CX32 requires review');

    $evidence = [
        'structure_empty_staffing' => (string)$structure['result'],
        'activation_without_governance' => (string)$withoutGovernance['result'],
        'activation_with_governance' => (string)$activation['result'],
        'effective_version' => QmsResponsibilityApprovalService::versionStatus($versionId),
        'active_chain_appointments' => $appointmentCount,
        'old_version_during_change' => QmsResponsibilityApprovalService::versionStatus($versionId),
        'rbac_roles_unchanged' => $rolesAfter === $rolesBefore ? 'yes' : 'no',
        'Y13-CX20' => (string)$findings['Y13-CX20']['status'],
        'Y13-CX21' => (string)$findings['Y13-CX21']['status'],
        'Y13-CX32' => (string)$findings['Y13-CX32']['status'],
    ];
});

foreach ($evidence as $key => $value) {
    echo $key . '=' . $value . PHP_EOL;
}
echo "qms responsibility end-to-end smoke: OK\n";
