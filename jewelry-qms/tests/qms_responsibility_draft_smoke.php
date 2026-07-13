<?php
declare(strict_types=1);

require __DIR__ . '/support/qms_responsibility_fixture.php';

use app\service\QmsResponsibilityCatalogService;
use app\service\QmsResponsibilityDraftService;
use think\facade\Db;

catalog_in_transaction(function (): void {
    $version = QmsResponsibilityCatalogService::createInitialDraft();
    $versionId = (string)$version['id'];
    $detail = QmsResponsibilityDraftService::versionDetail($versionId);

    catalog_assert(($detail['version']['id'] ?? '') === $versionId, 'Version detail identifies the draft');
    catalog_assert(count($detail['activities'] ?? []) === 3, 'Version detail contains three activities');
    catalog_assert(count($detail['responsibilities'] ?? []) === 21, 'Version detail contains twenty-one duties');
    catalog_assert(($detail['named_person_unbound'] ?? 0) > 0, 'Named-person duties report unbound people');
    catalog_assert(($detail['structure_test_allowed'] ?? false) === true, 'Unbound people do not block structure testing');
    catalog_assert(count($detail['dynamic_slots'] ?? []) > 0, 'Dynamic slots are listed separately');
    foreach ($detail['dynamic_slots'] as $dynamicSlot) {
        catalog_assert(($dynamicSlot['display_status'] ?? '') === '运行时指定', 'Dynamic slot displays runtime assignment');
    }

    $companyId = catalog_company_id();
    $siteId = responsibility_fixture_row('sites', [
        'company_id' => $companyId,
        'code' => 'DRAFT-' . substr(qms_uuid(), 0, 8),
        'name' => '责任链草案隔离场所',
        'site_type' => 'branch',
        'status' => 'active',
        'sort_order' => 999,
    ]);
    $employeeId = responsibility_fixture_row('employees', [
        'company_id' => $companyId,
        'primary_site_id' => $siteId,
        'employee_number' => 'DRAFT-' . substr(qms_uuid(), 0, 8),
        'name' => '责任链草案测试人员',
    ]);
    $competencyId = responsibility_fixture_row('competency_records', [
        'company_id' => $companyId,
        'employee_id' => $employeeId,
        'test_item' => '责任链草案胜任证据',
        'assessment_date' => '2026-07-14',
        'result' => 'qualified',
    ]);

    $namedDuty = null;
    $dynamicDuty = null;
    $activityRoleDuty = null;
    foreach ($detail['responsibilities'] as $responsibility) {
        if (($responsibility['assignment_mode'] ?? '') === 'named_person' && $namedDuty === null) {
            $namedDuty = $responsibility;
        }
        if (($responsibility['assignment_mode'] ?? '') === 'derived_from_scope' && $dynamicDuty === null) {
            $dynamicDuty = $responsibility;
        }
        if (($responsibility['assignment_mode'] ?? '') === 'activity_instance' && $activityRoleDuty === null) {
            $activityRoleDuty = $responsibility;
        }
    }
    catalog_assert(is_array($namedDuty), 'Fixture has a named-person duty');
    catalog_assert(is_array($dynamicDuty), 'Fixture has a derived dynamic duty');
    catalog_assert(is_array($activityRoleDuty), 'Fixture has an activity-role duty');

    responsibility_assert_throws(
        fn () => QmsResponsibilityDraftService::saveAssignment(
            (string)$dynamicDuty['id'],
            $employeeId,
            null,
            '2026-07-14',
            null,
            []
        ),
        'Derived dynamic owner cannot become a permanent assignment'
    );

    $activityRoleAssignment = QmsResponsibilityDraftService::saveAssignment(
        (string)$activityRoleDuty['id'],
        $employeeId,
        null,
        '2026-07-14',
        null,
        ['competency_record_ids' => [$competencyId]]
    );
    catalog_assert(($activityRoleAssignment['status'] ?? '') === 'draft', 'Optional activity-role template assignment remains draft');
    QmsResponsibilityDraftService::removeAssignment((string)$activityRoleAssignment['id']);

    $hashBefore = QmsResponsibilityDraftService::contentHash($versionId);
    catalog_assert($hashBefore === QmsResponsibilityDraftService::contentHash($versionId), 'Content hash is stable across repeated reads');

    $assignment = QmsResponsibilityDraftService::saveAssignment(
        (string)$namedDuty['id'],
        $employeeId,
        $siteId,
        '2026-07-14',
        '2027-07-14',
        ['competency_record_ids' => [$competencyId], 'note' => '已核验']
    );
    catalog_assert(($assignment['status'] ?? '') === 'draft', 'Saved assignment remains a draft');
    catalog_assert(($assignment['site_scope_key'] ?? '') === $siteId, 'Specific site becomes its scope key');
    catalog_assert(
        in_array($competencyId, $assignment['competence_snapshot']['competency_record_ids'] ?? [], true),
        'Competence snapshot preserves competency record ids'
    );
    catalog_assert($hashBefore !== QmsResponsibilityDraftService::contentHash($versionId), 'Person assignment changes content hash');

    $sameAssignment = QmsResponsibilityDraftService::saveAssignment(
        (string)$namedDuty['id'],
        $employeeId,
        $siteId,
        '2026-07-15',
        '2027-07-15',
        ['competency_record_ids' => [$competencyId], 'note' => '重复保存更新']
    );
    catalog_assert($sameAssignment['id'] === $assignment['id'], 'Duplicate responsibility, employee and scope updates idempotently');
    catalog_assert(
        (int)Db::name('qms_responsibility_assignments')
            ->where('responsibility_id', (string)$namedDuty['id'])
            ->where('employee_id', $employeeId)
            ->where('site_scope_key', $siteId)
            ->where('soft_delete', 0)
            ->count() === 1,
        'Idempotent save does not duplicate assignment rows'
    );

    $secondEmployeeId = responsibility_fixture_row('employees', [
        'company_id' => $companyId,
        'primary_site_id' => $siteId,
        'employee_number' => 'DRAFT-' . substr(qms_uuid(), 0, 8),
        'name' => '责任链第二人员',
    ]);
    $globalAssignment = QmsResponsibilityDraftService::saveAssignment(
        (string)$namedDuty['id'],
        $secondEmployeeId,
        null,
        '2026-07-14',
        null,
        []
    );
    catalog_assert(($globalAssignment['site_scope_key'] ?? '') === '*', 'Empty site uses the global scope key');
    QmsResponsibilityDraftService::removeAssignment((string)$globalAssignment['id']);
    catalog_assert(
        (int)Db::name('qms_responsibility_assignments')->where('id', (string)$globalAssignment['id'])->value('soft_delete') === 1,
        'Draft assignment can be removed without physical history loss'
    );
    $globalAssignment = QmsResponsibilityDraftService::saveAssignment(
        (string)$namedDuty['id'],
        $secondEmployeeId,
        null,
        '2026-07-14',
        null,
        []
    );
    catalog_assert(($globalAssignment['id'] ?? '') !== '', 'Removed draft assignment can be restored idempotently');

    responsibility_assert_throws(
        fn () => QmsResponsibilityDraftService::saveAssignment((string)$namedDuty['id'], $employeeId, $siteId, '2026-07-15', '2026-07-14', []),
        'Invalid proposed date range is blocked'
    );

    $foreignCompanyId = responsibility_fixture_row('companies', [
        'name' => '责任链草案跨公司隔离机构',
    ]);
    $foreignSiteId = responsibility_fixture_row('sites', [
        'company_id' => $foreignCompanyId,
        'code' => 'FOREIGN-' . substr(qms_uuid(), 0, 8),
        'name' => '其他公司场所',
        'site_type' => 'branch',
        'status' => 'active',
        'sort_order' => 999,
    ]);
    $foreignEmployeeId = responsibility_fixture_row('employees', [
        'company_id' => $foreignCompanyId,
        'primary_site_id' => $foreignSiteId,
        'employee_number' => 'FOREIGN-' . substr(qms_uuid(), 0, 8),
        'name' => '其他公司人员',
    ]);
    responsibility_assert_throws(
        fn () => QmsResponsibilityDraftService::saveAssignment((string)$namedDuty['id'], $foreignEmployeeId, null, '2026-07-14', null, []),
        'Employee from another company is blocked'
    );
    responsibility_assert_throws(
        fn () => QmsResponsibilityDraftService::saveAssignment((string)$namedDuty['id'], $employeeId, $foreignSiteId, '2026-07-14', null, []),
        'Site from another company is blocked'
    );

    Db::name('qms_activity_responsibilities')->where('id', (string)$namedDuty['id'])->update(['soft_delete' => 1]);
    responsibility_assert_throws(
        fn () => QmsResponsibilityDraftService::removeAssignment((string)$assignment['id']),
        'Soft-deleted responsibility blocks assignment removal'
    );
    catalog_assert(
        (int)Db::name('qms_responsibility_assignments')->where('id', (string)$assignment['id'])->value('soft_delete') === 0,
        'Blocked removal preserves the draft assignment'
    );
    Db::name('qms_activity_responsibilities')->where('id', (string)$namedDuty['id'])->update(['soft_delete' => 0]);

    Db::name('qms_responsibility_chain_versions')->where('id', $versionId)->update([
        'status' => 'effective',
        'effective_at' => date('Y-m-d H:i:s'),
    ]);
    responsibility_assert_throws(
        fn () => QmsResponsibilityDraftService::saveAssignment((string)$namedDuty['id'], $employeeId, $siteId, '2026-07-14', null, []),
        'Non-draft version blocks saving assignments'
    );
    responsibility_assert_throws(
        fn () => QmsResponsibilityDraftService::removeAssignment((string)$assignment['id']),
        'Non-draft version blocks removing assignments'
    );

    $effectiveHash = QmsResponsibilityDraftService::contentHash($versionId);
    $clone = QmsResponsibilityDraftService::cloneEffectiveVersion($versionId);
    catalog_assert(($clone['version_no'] ?? 0) === 2, 'Clone summary exposes the next version number');
    catalog_assert(($clone['replaces_version_id'] ?? '') === $versionId, 'Clone summary exposes the replaced version');
    catalog_assert(($clone['version']['version_no'] ?? 0) === 2, 'Effective version clones to the next number');
    catalog_assert(($clone['version']['status'] ?? '') === 'draft', 'Cloned version is a draft');
    catalog_assert(($clone['version']['replaces_version_id'] ?? '') === $versionId, 'Clone records the replaced version');
    catalog_assert(count($clone['activities'] ?? []) === 3, 'Clone contains all three activities');
    catalog_assert(count($clone['responsibilities'] ?? []) === 21, 'Clone contains all twenty-one duties');
    catalog_assert(count($clone['assignments'] ?? []) === 2, 'Clone contains existing person assignments');
    foreach ($clone['assignments'] as $clonedAssignment) {
        catalog_assert(($clonedAssignment['status'] ?? '') === 'draft', 'Cloned person assignment returns to draft');
    }
    catalog_assert(
        (string)Db::name('qms_responsibility_chain_versions')->where('id', $versionId)->value('status') === 'effective',
        'Source version remains effective after cloning'
    );
    catalog_assert(QmsResponsibilityDraftService::contentHash((string)$clone['version']['id']) === $effectiveHash, 'Clone has the same business content hash');

    $clonedNamedDuty = null;
    foreach ($clone['responsibilities'] as $clonedResponsibility) {
        if (
            ($clonedResponsibility['activity_code'] ?? '') === ($namedDuty['activity_code'] ?? '')
            && ($clonedResponsibility['step_code'] ?? '') === ($namedDuty['step_code'] ?? '')
        ) {
            $clonedNamedDuty = $clonedResponsibility;
            break;
        }
    }
    catalog_assert(is_array($clonedNamedDuty), 'Clone preserves the named responsibility business key');
    $clonedScopedAssignment = Db::name('qms_responsibility_assignments')
        ->where('responsibility_id', (string)$clonedNamedDuty['id'])
        ->where('employee_id', $employeeId)
        ->where('site_scope_key', $siteId)
        ->where('soft_delete', 0)
        ->find();
    catalog_assert(is_array($clonedScopedAssignment), 'Clone contains the site-scoped named assignment');

    $replacementSiteId = qms_uuid();
    Db::name('sites')->where('id', $siteId)->update(['id' => $replacementSiteId]);
    $replacementEmployeeId = responsibility_fixture_row('employees', [
        'company_id' => $companyId,
        'primary_site_id' => $replacementSiteId,
        'employee_number' => (string)Db::name('employees')->where('id', $employeeId)->value('employee_number'),
        'name' => '责任链同业务键替换人员',
    ]);
    Db::name('qms_responsibility_assignments')->where('id', (string)$clonedScopedAssignment['id'])->update([
        'employee_id' => $replacementEmployeeId,
        'site_id' => $replacementSiteId,
        'site_scope_key' => $replacementSiteId,
    ]);
    catalog_assert(
        QmsResponsibilityDraftService::contentHash((string)$clone['version']['id']) === $effectiveHash,
        'Changing database UUIDs while keeping employee number and site code preserves the content hash'
    );
    Db::name('employees')->where('id', $replacementEmployeeId)->update(['employee_number' => null]);
    responsibility_assert_throws(
        fn () => QmsResponsibilityDraftService::contentHash((string)$clone['version']['id']),
        'Missing employee business key blocks stable content hashing'
    );
    Db::name('employees')->where('id', $replacementEmployeeId)->update(['employee_number' => 'DRAFT-BUSINESS-KEY-CHANGED']);
    $missingSiteId = qms_uuid();
    Db::name('qms_responsibility_assignments')->where('id', (string)$clonedScopedAssignment['id'])->update([
        'site_id' => $missingSiteId,
        'site_scope_key' => $missingSiteId,
    ]);
    responsibility_assert_throws(
        fn () => QmsResponsibilityDraftService::contentHash((string)$clone['version']['id']),
        'Missing site business key blocks stable content hashing'
    );
    Db::name('qms_responsibility_assignments')->where('id', (string)$clonedScopedAssignment['id'])->update([
        'site_id' => $replacementSiteId,
        'site_scope_key' => $replacementSiteId,
    ]);
    catalog_assert(
        QmsResponsibilityDraftService::contentHash((string)$clone['version']['id']) !== $effectiveHash,
        'Changing the employee business key changes the content hash'
    );

    $sameClone = QmsResponsibilityDraftService::cloneEffectiveVersion($versionId);
    catalog_assert($sameClone['version']['id'] === $clone['version']['id'], 'Cloning the same effective version is idempotent');
    responsibility_assert_throws(
        fn () => QmsResponsibilityDraftService::cloneEffectiveVersion((string)$clone['version']['id']),
        'A non-effective version cannot be cloned'
    );
});

echo "qms_responsibility_draft_smoke passed\n";
