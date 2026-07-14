<?php
declare(strict_types=1);

require __DIR__ . '/support/qms_responsibility_fixture.php';

use app\service\QmsResponsibilityCatalogService;
use app\service\QmsResponsibilityValidationService;
use think\facade\Db;

function validation_codes(array $result): array
{
    return array_values(array_map(
        static fn (array $issue): string => (string)($issue['code'] ?? ''),
        (array)($result['issues'] ?? [])
    ));
}

function validation_has_code(array $result, string $code): bool
{
    return in_array($code, validation_codes($result), true);
}

function validation_assert_no_code(array $result, string $code, string $message): void
{
    catalog_assert(!validation_has_code($result, $code), $message . ': ' . json_encode($result['issues'] ?? [], JSON_UNESCAPED_UNICODE));
}

function validation_responsibilities(string $versionId): array
{
    $rows = Db::name('qms_activity_responsibilities')
        ->alias('r')
        ->join('qms_responsibility_activities a', 'a.id = r.activity_id AND a.company_id = r.company_id')
        ->where('a.chain_version_id', $versionId)
        ->where('a.soft_delete', 0)
        ->where('r.soft_delete', 0)
        ->field('r.*,a.activity_code')
        ->order('a.sort_order,r.sort_order,r.step_code')
        ->select()
        ->toArray();

    $byStep = [];
    foreach ($rows as $row) {
        $byStep[(string)$row['step_code']] = $row;
    }

    return $byStep;
}

function validation_employee(string $companyId, ?string $primarySiteId, string $label, array $overrides = []): string
{
    return responsibility_fixture_row('employees', array_merge([
        'company_id' => $companyId,
        'primary_site_id' => $primarySiteId,
        'employee_number' => 'VAL-' . strtoupper(substr(qms_uuid(), 0, 8)),
        'name' => '责任链校验-' . $label,
    ], $overrides));
}

function validation_assignment(array $responsibility, string $employeeId, array $overrides = []): string
{
    return responsibility_fixture_row('qms_responsibility_assignments', array_merge([
        'company_id' => (string)$responsibility['company_id'],
        'responsibility_id' => (string)$responsibility['id'],
        'employee_id' => $employeeId,
        'site_id' => null,
        'site_scope_key' => '*',
        'proposed_from' => date('Y-m-d'),
        'proposed_until' => null,
        'competence_snapshot' => json_encode([], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'validation_status' => null,
        'validation_details' => null,
        'status' => 'draft',
    ], $overrides));
}

function validation_competency(string $companyId, string $employeeId, array $overrides = []): string
{
    return responsibility_fixture_row('competency_records', array_merge([
        'company_id' => $companyId,
        'employee_id' => $employeeId,
        'test_item' => '责任链校验胜任能力',
        'assessment_date' => date('Y-m-d'),
        'result' => 'qualified',
        'valid_until' => date('Y-m-d', strtotime('+1 year')),
    ], $overrides));
}

function validation_certificate(string $companyId, string $employeeId, array $overrides = []): string
{
    return responsibility_fixture_row('employee_certificates', array_merge([
        'company_id' => $companyId,
        'employee_id' => $employeeId,
        'certificate_type' => '责任链校验证书',
        'certificate_number' => 'CERT-' . strtoupper(substr(qms_uuid(), 0, 8)),
        'issue_date' => date('Y-m-d'),
        'valid_until' => date('Y-m-d', strtotime('+1 year')),
        'status' => 'active',
    ], $overrides));
}

function validation_appointment(
    string $companyId,
    string $employeeId,
    string $positionId,
    string $positionName,
    string $sourceKind = 'responsibility_chain',
    array $overrides = []
): string {
    return responsibility_fixture_row('employee_appointments', array_merge([
        'company_id' => $companyId,
        'employee_id' => $employeeId,
        'position_id' => $positionId,
        'site_id' => null,
        'appointment_key' => 'VAL-' . qms_uuid(),
        'appointment_type' => 'role',
        'position_name' => $positionName,
        'appointment_scope' => '责任链校验夹具',
        'appointed_at' => date('Y-m-d'),
        'valid_until' => null,
        'source_kind' => $sourceKind,
        'status' => 'active',
    ], $overrides));
}

function validation_reset(string $versionId): void
{
    $responsibilityIds = Db::name('qms_activity_responsibilities')
        ->alias('r')
        ->join('qms_responsibility_activities a', 'a.id = r.activity_id AND a.company_id = r.company_id')
        ->where('a.chain_version_id', $versionId)
        ->column('r.id');
    if ($responsibilityIds !== []) {
        Db::name('qms_responsibility_assignments')->whereIn('responsibility_id', $responsibilityIds)->delete();
        Db::name('qms_activity_responsibilities')->whereIn('id', $responsibilityIds)->update(['required' => 0]);
    }
    Db::name('employee_appointments')->where('company_id', catalog_company_id())->delete();
}

function validation_enable_required(array $responsibility): void
{
    Db::name('qms_activity_responsibilities')->where('id', (string)$responsibility['id'])->update(['required' => 1]);
}

function validation_add_approval_owners(string $companyId, array $positions, string $siteId): array
{
    $gmEmployeeId = validation_employee($companyId, $siteId, '公司总经理');
    $labDirectorEmployeeId = validation_employee($companyId, $siteId, '实验室主任');
    validation_appointment(
        $companyId,
        $gmEmployeeId,
        (string)$positions['company_general_manager']['id'],
        '公司总经理',
        'corporate_evidence'
    );
    validation_appointment(
        $companyId,
        $labDirectorEmployeeId,
        (string)$positions['lab_director']['id'],
        '实验室主任'
    );

    return [$gmEmployeeId, $labDirectorEmployeeId];
}

function validation_snapshot(array $fields): string
{
    return (string)json_encode($fields, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
}

catalog_in_transaction(function (): void {
    responsibility_assert_throws(
        fn () => QmsResponsibilityValidationService::validateVersion('missing', 'unknown'),
        'Unknown validation mode is rejected'
    );

    $version = QmsResponsibilityCatalogService::createInitialDraft();
    $versionId = (string)$version['id'];
    $companyId = catalog_company_id();
    $responsibilities = validation_responsibilities($versionId);
    $positions = [];
    foreach (Db::name('qms_positions')->where('company_id', $companyId)->where('soft_delete', 0)->select()->toArray() as $position) {
        $positions[(string)$position['code']] = $position;
    }

    $siteId = responsibility_fixture_row('sites', [
        'company_id' => $companyId,
        'code' => 'VAL-' . strtoupper(substr(qms_uuid(), 0, 8)),
        'name' => '责任链校验主场所',
        'site_type' => 'main',
        'status' => 'active',
        'sort_order' => 998,
    ]);
    $otherSiteId = responsibility_fixture_row('sites', [
        'company_id' => $companyId,
        'code' => 'VAL-' . strtoupper(substr(qms_uuid(), 0, 8)),
        'name' => '责任链校验其他场所',
        'site_type' => 'branch',
        'status' => 'active',
        'sort_order' => 999,
    ]);

    // Empty initial draft: fixed named-person duties warn in structure mode and block activation.
    Db::name('qms_responsibility_assignments')
        ->whereIn('responsibility_id', array_column($responsibilities, 'id'))
        ->delete();
    Db::name('employee_appointments')->where('company_id', $companyId)->delete();
    $structure = QmsResponsibilityValidationService::validateVersion($versionId, 'structure');
    catalog_assert($structure['version_id'] === $versionId, 'Validation returns the requested version id');
    catalog_assert($structure['mode'] === 'structure', 'Validation returns structure mode');
    catalog_assert($structure['result'] === 'warning', 'Empty structure draft is a warning');
    catalog_assert($structure['can_save'] === true, 'Structure warnings do not block saving');
    catalog_assert($structure['can_submit'] === false, 'Structure warnings are not submission-ready');
    catalog_assert(validation_has_code($structure, 'required_assignment_missing'), 'Empty structure reports missing named-person bindings');
    foreach ($structure['issues'] as $issue) {
        catalog_assert((string)$issue['severity'] === 'warning', 'Structure issues are warnings');
        foreach (['code', 'severity', 'message', 'activity_code', 'step_code', 'responsibility_id'] as $key) {
            catalog_assert(array_key_exists($key, $issue), 'Issue contains stable key ' . $key);
        }
    }
    catalog_assert(DateTimeImmutable::createFromFormat(DATE_ATOM, (string)$structure['checked_at']) !== false, 'checked_at uses DATE_ATOM');

    $activation = QmsResponsibilityValidationService::validateVersion($versionId, 'activation');
    catalog_assert($activation['result'] === 'blocker', 'Empty activation is blocked');
    catalog_assert($activation['can_save'] === true, 'Activation blockers can return to draft editing');
    catalog_assert($activation['can_submit'] === false, 'Activation blockers prevent submission');
    catalog_assert(validation_has_code($activation, 'required_assignment_missing'), 'Activation reports missing named-person bindings');
    catalog_assert(validation_has_code($activation, 'company_general_manager_identity_missing'), 'Activation requires a corporate GM identity');

    $dynamicIds = [];
    foreach ($responsibilities as $responsibility) {
        if (in_array((string)$responsibility['assignment_mode'], ['activity_instance', 'derived_from_scope'], true)) {
            $dynamicIds[] = (string)$responsibility['id'];
        }
    }
    foreach ($activation['issues'] as $issue) {
        if ((string)$issue['code'] === 'required_assignment_missing') {
            catalog_assert(!in_array((string)$issue['responsibility_id'], $dynamicIds, true), 'Runtime slots are never reported as missing people');
        }
    }

    // With fixed duties optional and valid GM identity, unbound runtime slots pass.
    validation_reset($versionId);
    $gmOnly = validation_employee($companyId, $siteId, '仅总经理');
    validation_appointment($companyId, $gmOnly, (string)$positions['company_general_manager']['id'], '公司总经理', 'corporate_evidence');
    $runtimePass = QmsResponsibilityValidationService::validateVersion($versionId, 'activation');
    validation_assert_no_code($runtimePass, 'required_assignment_missing', 'Unbound runtime slots do not require template people');
    catalog_assert($runtimePass['result'] === 'pass', 'Runtime-only template can activate when its governance identity exists');

    // Valid employee, site, date and both supported evidence kinds pass.
    validation_reset($versionId);
    [, $labDirectorId] = validation_add_approval_owners($companyId, $positions, $siteId);
    $subjectId = validation_employee($companyId, $siteId, '有效受任人');
    $competencyId = validation_competency($companyId, $subjectId);
    $certificateId = validation_certificate($companyId, $subjectId);
    validation_enable_required($responsibilities['ia_annual_plan']);
    $validAssignmentId = validation_assignment($responsibilities['ia_annual_plan'], $subjectId, [
        'site_id' => $siteId,
        'site_scope_key' => $siteId,
        'proposed_from' => date('Y-m-d'),
        'proposed_until' => date('Y-m-d', strtotime('+1 year')),
        'competence_snapshot' => validation_snapshot([
            'competency_record_ids' => [$competencyId],
            'certificate_ids' => [$certificateId],
        ]),
    ]);
    $valid = QmsResponsibilityValidationService::validateVersion($versionId, 'activation');
    catalog_assert($valid['result'] === 'pass', 'Valid employee, site, dates, evidence and owners pass activation: ' . json_encode($valid['issues'], JSON_UNESCAPED_UNICODE));
    catalog_assert($valid['can_submit'] === true, 'Valid activation can submit');
    validation_assert_no_code($valid, 'competence_evidence_missing', 'Valid evidence is not missing');
    validation_assert_no_code($valid, 'competence_evidence_not_found', 'Valid evidence references resolve');
    validation_assert_no_code($valid, 'approval_owner_missing', 'Valid lab director owner resolves');
    Db::name('qms_responsibility_assignments')->where('id', $validAssignmentId)->update([
        'competence_snapshot' => validation_snapshot(['certificate_ids' => [$certificateId]]),
    ]);
    $certificateOnly = QmsResponsibilityValidationService::validateVersion($versionId, 'activation');
    catalog_assert($certificateOnly['result'] === 'pass', 'A valid employee certificate alone satisfies the supported evidence reference check');

    // Missing and invalid evidence are distinguished.
    validation_reset($versionId);
    validation_add_approval_owners($companyId, $positions, $siteId);
    validation_enable_required($responsibilities['ia_annual_plan']);
    $evidenceEmployeeId = validation_employee($companyId, $siteId, '证据缺失人员');
    $evidenceAssignmentId = validation_assignment($responsibilities['ia_annual_plan'], $evidenceEmployeeId);
    $missingEvidence = QmsResponsibilityValidationService::validateVersion($versionId, 'activation');
    catalog_assert(validation_has_code($missingEvidence, 'competence_evidence_missing'), 'Evidence-required binding with no references is blocked');
    Db::name('qms_responsibility_assignments')->where('id', $evidenceAssignmentId)->update([
        'competence_snapshot' => validation_snapshot(['competency_record_ids' => [qms_uuid()]]),
    ]);
    $invalidEvidence = QmsResponsibilityValidationService::validateVersion($versionId, 'activation');
    catalog_assert(validation_has_code($invalidEvidence, 'competence_evidence_not_found'), 'Missing evidence reference is distinguished');
    $validEvidenceId = validation_competency($companyId, $evidenceEmployeeId);
    Db::name('qms_responsibility_assignments')->where('id', $evidenceAssignmentId)->update([
        'competence_snapshot' => validation_snapshot(['competency_record_ids' => [$validEvidenceId, []]]),
    ]);
    $nestedEvidence = QmsResponsibilityValidationService::validateVersion($versionId, 'activation');
    catalog_assert(validation_has_code($nestedEvidence, 'competence_evidence_not_found'), 'A valid evidence id plus a nested malformed item is rejected');
    Db::name('qms_responsibility_assignments')->where('id', $evidenceAssignmentId)->update([
        'competence_snapshot' => validation_snapshot(['competency_record_ids' => $validEvidenceId]),
    ]);
    $nonArrayEvidence = QmsResponsibilityValidationService::validateVersion($versionId, 'activation');
    catalog_assert(validation_has_code($nonArrayEvidence, 'competence_evidence_not_found'), 'A non-array evidence field is rejected as malformed');
    validation_assert_no_code($nonArrayEvidence, 'competence_evidence_missing', 'Malformed non-empty evidence is not misclassified as an empty snapshot');

    // Employee, site and dates are validated independently.
    validation_reset($versionId);
    validation_add_approval_owners($companyId, $positions, $siteId);
    validation_enable_required($responsibilities['ia_annual_plan']);
    $inactiveEmployeeId = validation_employee($companyId, $siteId, '未发布人员', ['publish' => 0]);
    validation_assignment($responsibilities['ia_annual_plan'], $inactiveEmployeeId, ['competence_snapshot' => validation_snapshot(['certificate_ids' => [qms_uuid()]])]);
    $inactive = QmsResponsibilityValidationService::validateVersion($versionId, 'activation');
    catalog_assert(validation_has_code($inactive, 'employee_inactive'), 'Unpublished assignment employee is inactive');

    validation_reset($versionId);
    validation_add_approval_owners($companyId, $positions, $siteId);
    validation_enable_required($responsibilities['ia_annual_plan']);
    $siteEmployeeId = validation_employee($companyId, $siteId, '场所不匹配人员');
    $siteEvidenceId = validation_competency($companyId, $siteEmployeeId);
    validation_assignment($responsibilities['ia_annual_plan'], $siteEmployeeId, [
        'site_id' => $otherSiteId,
        'site_scope_key' => $otherSiteId,
        'competence_snapshot' => validation_snapshot(['competency_record_ids' => [$siteEvidenceId]]),
    ]);
    $siteMismatch = QmsResponsibilityValidationService::validateVersion($versionId, 'activation');
    catalog_assert(validation_has_code($siteMismatch, 'site_mismatch'), 'Primary site mismatch is blocked');

    validation_reset($versionId);
    validation_add_approval_owners($companyId, $positions, $siteId);
    validation_enable_required($responsibilities['ia_annual_plan']);
    $dateEmployeeId = validation_employee($companyId, $siteId, '日期错误人员');
    $dateEvidenceId = validation_competency($companyId, $dateEmployeeId);
    $dateAssignmentId = validation_assignment($responsibilities['ia_annual_plan'], $dateEmployeeId, [
        'proposed_from' => null,
        'proposed_until' => date('Y-m-d', strtotime('-1 day')),
        'competence_snapshot' => validation_snapshot(['competency_record_ids' => [$dateEvidenceId]]),
    ]);
    $invalidDates = QmsResponsibilityValidationService::validateVersion($versionId, 'activation');
    catalog_assert(validation_has_code($invalidDates, 'appointment_dates_invalid'), 'Missing/expired appointment dates are blocked');
    Db::name('qms_responsibility_assignments')->where('id', $dateAssignmentId)->update([
        'proposed_from' => date('Y-m-d'),
        'proposed_until' => null,
    ]);
    $openEndedDates = QmsResponsibilityValidationService::validateVersion($versionId, 'activation');
    validation_assert_no_code($openEndedDates, 'appointment_dates_invalid', 'Valid open-ended appointment is accepted');

    // Approval owners: GM is always required; lab director is required for non-lab assignments.
    validation_reset($versionId);
    $ownersMissing = QmsResponsibilityValidationService::validateVersion($versionId, 'activation');
    catalog_assert(validation_has_code($ownersMissing, 'company_general_manager_identity_missing'), 'Missing GM corporate identity is explicit');
    $gmEmployeeId = validation_employee($companyId, $siteId, '审批总经理');
    validation_appointment($companyId, $gmEmployeeId, (string)$positions['company_general_manager']['id'], '公司总经理', 'corporate_evidence');
    validation_enable_required($responsibilities['ia_annual_plan']);
    $nonLabEmployeeId = validation_employee($companyId, $siteId, '非主任受任人');
    $nonLabEvidenceId = validation_competency($companyId, $nonLabEmployeeId);
    validation_assignment($responsibilities['ia_annual_plan'], $nonLabEmployeeId, [
        'competence_snapshot' => validation_snapshot(['competency_record_ids' => [$nonLabEvidenceId]]),
    ]);
    $labOwnerMissing = QmsResponsibilityValidationService::validateVersion($versionId, 'activation');
    catalog_assert(validation_has_code($labOwnerMissing, 'approval_owner_missing'), 'Non-lab assignment requires active lab director approval owner');

    // Approval-owner appointments must be current role appointments from the correct governance source.
    foreach ([
        ['label' => '缺少生效日', 'overrides' => ['appointed_at' => null]],
        ['label' => '未来生效', 'overrides' => ['appointed_at' => date('Y-m-d', strtotime('+1 day'))]],
        ['label' => '已经过期', 'overrides' => ['valid_until' => date('Y-m-d', strtotime('-1 day'))]],
        ['label' => '非岗位任命', 'overrides' => ['appointment_type' => 'authorization']],
    ] as $invalidOwnerCase) {
        validation_reset($versionId);
        $invalidGmId = validation_employee($companyId, $siteId, '无效总经理-' . $invalidOwnerCase['label']);
        validation_appointment(
            $companyId,
            $invalidGmId,
            (string)$positions['company_general_manager']['id'],
            '公司总经理',
            'corporate_evidence',
            $invalidOwnerCase['overrides']
        );
        $invalidOwner = QmsResponsibilityValidationService::validateVersion($versionId, 'activation');
        catalog_assert(
            validation_has_code($invalidOwner, 'company_general_manager_identity_missing'),
            $invalidOwnerCase['label'] . '的总经理任命不构成有效批准主体'
        );
    }

    validation_reset($versionId);
    $nameOnlyGmId = validation_employee($companyId, $siteId, '仅名称总经理');
    validation_appointment($companyId, $nameOnlyGmId, '', '公司总经理', 'corporate_evidence');
    $nameOnlyGmOwner = QmsResponsibilityValidationService::validateVersion($versionId, 'activation');
    catalog_assert(
        validation_has_code($nameOnlyGmOwner, 'company_general_manager_identity_missing'),
        'Company GM name without the standard position id does not authorize approval'
    );

    validation_reset($versionId);
    $obsoleteGmId = validation_employee($companyId, $siteId, '岗位已废止总经理');
    validation_appointment($companyId, $obsoleteGmId, (string)$positions['company_general_manager']['id'], '公司总经理', 'corporate_evidence');
    Db::name('qms_positions')->where('id', (string)$positions['company_general_manager']['id'])->update(['review_status' => 'obsolete']);
    $obsoletePositionOwner = QmsResponsibilityValidationService::validateVersion($versionId, 'activation');
    catalog_assert(validation_has_code($obsoletePositionOwner, 'company_general_manager_identity_missing'), 'Obsolete GM position does not authorize approval');
    Db::name('qms_positions')->where('id', (string)$positions['company_general_manager']['id'])->update(['review_status' => 'published']);

    validation_reset($versionId);
    $deletedGmId = validation_employee($companyId, $siteId, '岗位已删除总经理');
    validation_appointment($companyId, $deletedGmId, (string)$positions['company_general_manager']['id'], '公司总经理', 'corporate_evidence');
    Db::name('qms_positions')->where('id', (string)$positions['company_general_manager']['id'])->update(['soft_delete' => 1]);
    $deletedPositionOwner = QmsResponsibilityValidationService::validateVersion($versionId, 'activation');
    catalog_assert(validation_has_code($deletedPositionOwner, 'company_general_manager_identity_missing'), 'Soft-deleted GM position does not authorize approval');
    Db::name('qms_positions')->where('id', (string)$positions['company_general_manager']['id'])->update(['soft_delete' => 0]);

    validation_reset($versionId);
    $validGmId = validation_employee($companyId, $siteId, '有效总经理');
    validation_appointment($companyId, $validGmId, (string)$positions['company_general_manager']['id'], '公司总经理', 'corporate_evidence');
    $legacyLabId = validation_employee($companyId, $siteId, '旧文件实验室主任');
    validation_appointment($companyId, $legacyLabId, (string)$positions['lab_director']['id'], '实验室主任', 'legacy_document');
    validation_enable_required($responsibilities['ia_annual_plan']);
    $legacySubjectId = validation_employee($companyId, $siteId, '旧主任审批受任人');
    $legacySubjectEvidenceId = validation_competency($companyId, $legacySubjectId);
    validation_assignment($responsibilities['ia_annual_plan'], $legacySubjectId, [
        'competence_snapshot' => validation_snapshot(['competency_record_ids' => [$legacySubjectEvidenceId]]),
    ]);
    $legacyDirectorOwner = QmsResponsibilityValidationService::validateVersion($versionId, 'activation');
    catalog_assert(validation_has_code($legacyDirectorOwner, 'approval_owner_missing'), 'Legacy-document lab director is not a responsibility-chain approval owner');

    // Distinct approval owners must be unique; duplicate rows for one person do not create false ambiguity.
    validation_reset($versionId);
    $singleGmId = validation_employee($companyId, $siteId, '重复任命同一总经理');
    validation_appointment($companyId, $singleGmId, (string)$positions['company_general_manager']['id'], '公司总经理', 'corporate_evidence');
    validation_appointment($companyId, $singleGmId, (string)$positions['company_general_manager']['id'], '公司总经理', 'corporate_evidence');
    $sameGmTwice = QmsResponsibilityValidationService::validateVersion($versionId, 'activation');
    validation_assert_no_code($sameGmTwice, 'approval_owner_ambiguous', 'Duplicate appointments for the same GM employee are deduplicated');

    $secondGmId = validation_employee($companyId, $siteId, '第二总经理');
    validation_appointment($companyId, $secondGmId, (string)$positions['company_general_manager']['id'], '公司总经理', 'corporate_evidence');
    $multipleGmOwners = QmsResponsibilityValidationService::validateVersion($versionId, 'activation');
    catalog_assert(validation_has_code($multipleGmOwners, 'approval_owner_ambiguous'), 'Multiple distinct GM approval owners are ambiguous');
    catalog_assert($multipleGmOwners['can_submit'] === false, 'Ambiguous GM owners block submission');

    validation_reset($versionId);
    $uniqueGmId = validation_employee($companyId, $siteId, '唯一总经理');
    validation_appointment($companyId, $uniqueGmId, (string)$positions['company_general_manager']['id'], '公司总经理', 'corporate_evidence');
    foreach (['第一实验室主任', '第二实验室主任'] as $directorLabel) {
        $directorId = validation_employee($companyId, $siteId, $directorLabel);
        validation_appointment($companyId, $directorId, (string)$positions['lab_director']['id'], '实验室主任', 'responsibility_chain');
    }
    validation_enable_required($responsibilities['ia_annual_plan']);
    $ambiguousLabSubjectId = validation_employee($companyId, $siteId, '多主任审批受任人');
    $ambiguousLabEvidenceId = validation_competency($companyId, $ambiguousLabSubjectId);
    validation_assignment($responsibilities['ia_annual_plan'], $ambiguousLabSubjectId, [
        'competence_snapshot' => validation_snapshot(['competency_record_ids' => [$ambiguousLabEvidenceId]]),
    ]);
    $multipleLabOwners = QmsResponsibilityValidationService::validateVersion($versionId, 'activation');
    catalog_assert(validation_has_code($multipleLabOwners, 'approval_owner_ambiguous'), 'Multiple distinct lab director approval owners are ambiguous');
    catalog_assert($multipleLabOwners['can_submit'] === false, 'Ambiguous lab director owners block submission');

    // Self approval uses active appointment identity, never RBAC role.
    validation_reset($versionId);
    [$gmEmployeeId, $labDirectorId] = validation_add_approval_owners($companyId, $positions, $siteId);
    validation_enable_required($responsibilities['ia_plan_approval']);
    $gmEvidenceId = validation_competency($companyId, $gmEmployeeId);
    validation_assignment($responsibilities['ia_plan_approval'], $gmEmployeeId, [
        'competence_snapshot' => validation_snapshot(['competency_record_ids' => [$gmEvidenceId]]),
    ]);
    $gmSelfApproval = QmsResponsibilityValidationService::validateVersion($versionId, 'activation');
    catalog_assert(validation_has_code($gmSelfApproval, 'self_approval_conflict'), 'GM cannot appoint self as lab director');

    validation_reset($versionId);
    [$gmEmployeeId, $labDirectorId] = validation_add_approval_owners($companyId, $positions, $siteId);
    validation_enable_required($responsibilities['ia_annual_plan']);
    $labEvidenceId = validation_competency($companyId, $labDirectorId);
    validation_assignment($responsibilities['ia_annual_plan'], $labDirectorId, [
        'competence_snapshot' => validation_snapshot(['competency_record_ids' => [$labEvidenceId]]),
    ]);
    $labSelfApproval = QmsResponsibilityValidationService::validateVersion($versionId, 'activation');
    catalog_assert(validation_has_code($labSelfApproval, 'self_approval_conflict'), 'Lab director cannot approve own non-director assignment');

    // Synthetic activity-instance evidence is inserted directly into the validation fixture.
    // This verifies the rule evaluator only; it does not claim a real internal-audit UI gate exists.
    validation_reset($versionId);
    validation_add_approval_owners($companyId, $positions, $siteId);
    $auditorId = validation_employee($companyId, $siteId, '内审员');
    $auditorEvidenceId = validation_competency($companyId, $auditorId);
    validation_assignment($responsibilities['ia_audit_execution'], $auditorId, [
        'competence_snapshot' => validation_snapshot(['competency_record_ids' => [$auditorEvidenceId]]),
    ]);
    $auditWithoutScope = QmsResponsibilityValidationService::validateVersion($versionId, 'activation');
    validation_assert_no_code($auditWithoutScope, 'self_audit_conflict', 'Auditor role alone does not imply self-audit');
    validation_assignment($responsibilities['ia_correction'], $auditorId, [
        'competence_snapshot' => validation_snapshot(['competency_record_ids' => [$auditorEvidenceId]]),
    ]);
    $selfAudit = QmsResponsibilityValidationService::validateVersion($versionId, 'activation');
    catalog_assert(validation_has_code($selfAudit, 'self_audit_conflict'), 'Rule evaluator detects overlap in synthetic internal-audit activity evidence');
    Db::name('qms_responsibility_assignments')
        ->where('responsibility_id', (string)$responsibilities['ia_correction']['id'])
        ->delete();
    Db::name('qms_responsibility_assignments')
        ->where('responsibility_id', (string)$responsibilities['ia_audit_execution']['id'])
        ->update(['competence_snapshot' => validation_snapshot([
            'competency_record_ids' => [$auditorEvidenceId],
            'audited_employee_ids' => [$auditorId],
        ])]);
    $selfAuditSnapshot = QmsResponsibilityValidationService::validateVersion($versionId, 'activation');
    catalog_assert(validation_has_code($selfAuditSnapshot, 'self_audit_conflict'), 'Rule evaluator detects an explicit audited employee in synthetic activity evidence');

    // Synthetic risk-activity evidence is likewise fixture-only. The first version records
    // this rule but does not yet wire it into a real risk-activity close action.
    validation_reset($versionId);
    validation_add_approval_owners($companyId, $positions, $siteId);
    $riskEmployeeId = validation_employee($companyId, $siteId, '风险执行验证同人');
    $riskEvidenceId = validation_competency($companyId, $riskEmployeeId);
    validation_assignment($responsibilities['risk_verify'], $riskEmployeeId, [
        'competence_snapshot' => validation_snapshot(['competency_record_ids' => [$riskEvidenceId]]),
    ]);
    validation_assignment($responsibilities['risk_implement'], $riskEmployeeId, [
        'competence_snapshot' => validation_snapshot(['competency_record_ids' => [$riskEvidenceId]]),
    ]);
    $sameRiskPerson = QmsResponsibilityValidationService::validateVersion($versionId, 'activation');
    catalog_assert(validation_has_code($sameRiskPerson, 'executor_verifier_conflict'), 'Rule evaluator detects the same executor and verifier in synthetic risk-activity evidence');

    $otherRiskEmployeeId = validation_employee($companyId, $siteId, '风险独立验证人');
    $otherRiskEvidenceId = validation_competency($companyId, $otherRiskEmployeeId);
    Db::name('qms_responsibility_assignments')
        ->where('responsibility_id', (string)$responsibilities['risk_verify']['id'])
        ->delete();
    validation_assignment($responsibilities['risk_verify'], $otherRiskEmployeeId, [
        'competence_snapshot' => validation_snapshot(['competency_record_ids' => [$otherRiskEvidenceId]]),
    ]);
    $differentRiskPeople = QmsResponsibilityValidationService::validateVersion($versionId, 'activation');
    validation_assert_no_code($differentRiskPeople, 'executor_verifier_conflict', 'Rule evaluator accepts separated people in synthetic risk-activity evidence');

    Db::name('qms_responsibility_assignments')
        ->where('responsibility_id', (string)$responsibilities['risk_verify']['id'])
        ->delete();
    validation_assignment($responsibilities['risk_verify'], $riskEmployeeId, [
        'competence_snapshot' => validation_snapshot(['competency_record_ids' => [$riskEvidenceId]]),
    ]);
    Db::name('qms_activity_responsibilities')
        ->where('id', (string)$responsibilities['risk_verify']['id'])
        ->update(['rule_codes' => json_encode([], JSON_UNESCAPED_UNICODE)]);
    $withoutSeparationRule = QmsResponsibilityValidationService::validateVersion($versionId, 'activation');
    validation_assert_no_code($withoutSeparationRule, 'executor_verifier_conflict', 'Overlap without separation rule is not inferred');
    Db::name('qms_activity_responsibilities')
        ->where('id', (string)$responsibilities['risk_verify']['id'])
        ->update(['rule_codes' => $responsibilities['risk_verify']['rule_codes']]);

    // Only a draft version can be edited or submitted, even when validation has no issues.
    validation_reset($versionId);
    $statusGateGmId = validation_employee($companyId, $siteId, '版本状态门总经理');
    validation_appointment($companyId, $statusGateGmId, (string)$positions['company_general_manager']['id'], '公司总经理', 'corporate_evidence');
    $draftStatus = QmsResponsibilityValidationService::validateVersion($versionId, 'activation');
    catalog_assert($draftStatus['result'] === 'pass', 'Status-gate fixture is otherwise valid');
    catalog_assert($draftStatus['can_save'] === true && $draftStatus['can_submit'] === true, 'Valid draft remains editable and submittable');
    foreach (['effective', 'superseded', 'revoked'] as $closedStatus) {
        Db::name('qms_responsibility_chain_versions')->where('id', $versionId)->update(['status' => $closedStatus]);
        $closedVersion = QmsResponsibilityValidationService::validateVersion($versionId, 'activation');
        catalog_assert($closedVersion['result'] === 'pass', 'Closed version status does not add issue noise: ' . $closedStatus);
        catalog_assert($closedVersion['can_save'] === false, 'Closed version cannot be edited: ' . $closedStatus);
        catalog_assert($closedVersion['can_submit'] === false, 'Closed version cannot be submitted: ' . $closedStatus);
    }
    Db::name('qms_responsibility_chain_versions')->where('id', $versionId)->update(['status' => 'draft']);

    // Validation is stable and read-only: rows, statuses and modified timestamps remain untouched.
    $trackedResponsibilityIds = array_column($responsibilities, 'id');
    $before = [
        'versions' => Db::name('qms_responsibility_chain_versions')->where('id', $versionId)->find(),
        'responsibilities' => Db::name('qms_activity_responsibilities')->whereIn('id', $trackedResponsibilityIds)->order('id')->select()->toArray(),
        'assignments' => Db::name('qms_responsibility_assignments')->whereIn('responsibility_id', $trackedResponsibilityIds)->order('id')->select()->toArray(),
        'appointments' => Db::name('employee_appointments')->where('company_id', $companyId)->order('id')->select()->toArray(),
        'approvals' => Db::name('qms_responsibility_approvals')->where('chain_version_id', $versionId)->order('id')->select()->toArray(),
    ];
    $firstRead = QmsResponsibilityValidationService::validateVersion($versionId, 'activation');
    $secondRead = QmsResponsibilityValidationService::validateVersion($versionId, 'activation');
    $after = [
        'versions' => Db::name('qms_responsibility_chain_versions')->where('id', $versionId)->find(),
        'responsibilities' => Db::name('qms_activity_responsibilities')->whereIn('id', $trackedResponsibilityIds)->order('id')->select()->toArray(),
        'assignments' => Db::name('qms_responsibility_assignments')->whereIn('responsibility_id', $trackedResponsibilityIds)->order('id')->select()->toArray(),
        'appointments' => Db::name('employee_appointments')->where('company_id', $companyId)->order('id')->select()->toArray(),
        'approvals' => Db::name('qms_responsibility_approvals')->where('chain_version_id', $versionId)->order('id')->select()->toArray(),
    ];
    catalog_assert($before === $after, 'Validation performs no writes to version, duty, assignment, appointment or approval tables');
    unset($firstRead['checked_at'], $secondRead['checked_at']);
    catalog_assert($firstRead === $secondRead, 'Repeated validation has stable issue order and output apart from checked_at');
});

echo "qms_responsibility_validation_smoke passed\n";
