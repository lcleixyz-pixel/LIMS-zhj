<?php
declare(strict_types=1);

namespace app\service;

use DateTimeImmutable;
use DomainException;
use think\facade\Config;
use think\facade\Db;

final class QmsResponsibilityValidationService
{
    private const MODES = ['structure', 'activation'];
    private const CURRENT_ASSIGNMENT_STATUSES = ['draft', 'pending_approval', 'active'];

    public static function validateVersion(string $versionId, string $mode): array
    {
        if (!in_array($mode, self::MODES, true)) {
            throw new DomainException('责任链校验模式仅支持 structure 或 activation。');
        }

        $companyId = (string)Config::get('qms.company_id');
        $version = Db::name('qms_responsibility_chain_versions')
            ->where('id', $versionId)
            ->where('company_id', $companyId)
            ->where('publish', 1)
            ->where('soft_delete', 0)
            ->find();
        if (!$version) {
            throw new DomainException('责任链版本不存在、未发布或不属于当前公司。');
        }

        $responsibilities = self::responsibilities($versionId, $companyId);
        $assignmentsByResponsibility = self::assignmentsByResponsibility($responsibilities, $companyId);
        $severity = $mode === 'activation' ? 'blocker' : 'warning';
        $issues = [];

        foreach ($responsibilities as $responsibility) {
            $responsibilityId = (string)$responsibility['id'];
            $assignments = $assignmentsByResponsibility[$responsibilityId] ?? [];
            $assignmentMode = (string)$responsibility['assignment_mode'];
            $slotKind = (string)$responsibility['slot_kind'];

            if (
                $assignmentMode === 'named_person'
                && (int)$responsibility['required'] === 1
                && $assignments === []
            ) {
                $issues[] = self::issue(
                    'required_assignment_missing',
                    $severity,
                    '必需的实名岗位尚未绑定人员。',
                    $responsibility
                );
            }

            if (
                ($slotKind === 'activity_role' && trim((string)($responsibility['activity_role_code'] ?? '')) === '')
                || ($slotKind === 'dynamic_owner' && trim((string)($responsibility['dynamic_owner_code'] ?? '')) === '')
            ) {
                $issues[] = self::issue(
                    'runtime_assignment_metadata_missing',
                    $severity,
                    '运行时责任槽缺少角色或责任人代码，无法在活动实例中解析。',
                    $responsibility
                );
            }

            foreach ($assignments as $assignment) {
                self::validateAssignment(
                    $companyId,
                    $responsibility,
                    $assignment,
                    $severity,
                    $issues
                );
            }
        }

        $owners = self::approvalOwners($companyId);
        if ($owners['company_general_manager'] === []) {
            $issues[] = self::issue(
                'company_general_manager_identity_missing',
                $severity,
                '尚无由公司治理证据登记的有效公司总经理身份。'
            );
        } elseif (count($owners['company_general_manager']) > 1) {
            $issues[] = self::issue(
                'approval_owner_ambiguous',
                $severity,
                '存在多个不同人员的有效公司总经理身份，无法唯一确定批准主体。'
            );
        }

        $requiresLabDirector = self::hasLabDirectorApprovedAssignments(
            $responsibilities,
            $assignmentsByResponsibility
        );
        if ($requiresLabDirector && $owners['lab_director'] === []) {
            $issues[] = self::issue(
                'approval_owner_missing',
                $severity,
                '存在需实验室主任批准的实名岗位或活动角色，但尚无有效实验室主任任命。'
            );
        } elseif ($requiresLabDirector && count($owners['lab_director']) > 1) {
            $issues[] = self::issue(
                'approval_owner_ambiguous',
                $severity,
                '存在多个不同人员的有效实验室主任任命，无法唯一确定批准主体。'
            );
        }

        self::validateSelfApproval(
            $responsibilities,
            $assignmentsByResponsibility,
            $owners,
            $severity,
            $issues
        );
        self::validateSeparationRules(
            $responsibilities,
            $assignmentsByResponsibility,
            $severity,
            $issues
        );

        self::sortIssues($issues);
        $result = self::result($issues);
        $isDraft = (string)$version['status'] === 'draft';

        return [
            'version_id' => $versionId,
            'mode' => $mode,
            'result' => $result,
            'can_save' => $isDraft,
            'can_submit' => $isDraft && $result === 'pass',
            'issues' => $issues,
            'checked_at' => date(DATE_ATOM),
        ];
    }

    private static function responsibilities(string $versionId, string $companyId): array
    {
        $rows = Db::name('qms_activity_responsibilities')
            ->alias('r')
            ->join('qms_responsibility_activities a', 'a.id = r.activity_id AND a.company_id = r.company_id')
            ->leftJoin('qms_positions p', 'p.id = r.fixed_position_id AND p.company_id = r.company_id AND p.publish = 1 AND p.soft_delete = 0')
            ->where('a.chain_version_id', $versionId)
            ->where('a.company_id', $companyId)
            ->where('a.publish', 1)
            ->where('a.soft_delete', 0)
            ->where('r.company_id', $companyId)
            ->where('r.publish', 1)
            ->where('r.soft_delete', 0)
            ->field('r.*,a.activity_code,a.sort_order activity_sort_order,p.code fixed_position_code')
            ->order('a.sort_order,r.sort_order,r.step_code,r.id')
            ->select()
            ->toArray();

        foreach ($rows as &$row) {
            $row['eligibility_rule'] = self::decodeJson($row['eligibility_rule'] ?? null);
            $row['rule_codes'] = self::decodeJson($row['rule_codes'] ?? null);
        }
        unset($row);

        return $rows;
    }

    private static function assignmentsByResponsibility(array $responsibilities, string $companyId): array
    {
        $responsibilityIds = array_values(array_filter(array_map(
            static fn (array $responsibility): string => (string)($responsibility['id'] ?? ''),
            $responsibilities
        )));
        if ($responsibilityIds === []) {
            return [];
        }

        $rows = Db::name('qms_responsibility_assignments')
            ->whereIn('responsibility_id', $responsibilityIds)
            ->where('company_id', $companyId)
            ->whereIn('status', self::CURRENT_ASSIGNMENT_STATUSES)
            ->where('publish', 1)
            ->where('soft_delete', 0)
            ->order('responsibility_id,employee_id,site_scope_key,id')
            ->select()
            ->toArray();

        $grouped = [];
        foreach ($rows as $row) {
            $row['competence_snapshot'] = self::decodeJson($row['competence_snapshot'] ?? null);
            $grouped[(string)$row['responsibility_id']][] = $row;
        }

        return $grouped;
    }

    private static function validateAssignment(
        string $companyId,
        array $responsibility,
        array $assignment,
        string $severity,
        array &$issues
    ): void {
        $employeeId = (string)$assignment['employee_id'];
        $employee = Db::name('employees')
            ->where('id', $employeeId)
            ->where('company_id', $companyId)
            ->where('publish', 1)
            ->where('soft_delete', 0)
            ->find();
        if (!$employee) {
            $issues[] = self::issue(
                'employee_inactive',
                $severity,
                '绑定人员不存在、不属于当前公司、未发布或已删除。',
                $responsibility,
                $assignment
            );
        }

        if (!self::siteMatches($companyId, $employee, $assignment)) {
            $issues[] = self::issue(
                'site_mismatch',
                $severity,
                '绑定场所无效，或与人员主场所不一致。',
                $responsibility,
                $assignment
            );
        }

        if (!self::appointmentDatesValid($assignment)) {
            $issues[] = self::issue(
                'appointment_dates_invalid',
                $severity,
                '建议任命日期缺失、格式错误、顺序错误或已过期。',
                $responsibility,
                $assignment
            );
        }

        $eligibilityRule = (array)$responsibility['eligibility_rule'];
        if (($eligibilityRule['evidence_required'] ?? false) !== true) {
            return;
        }

        $snapshot = (array)$assignment['competence_snapshot'];
        $competencyEvidence = self::strictEvidenceIds($snapshot, 'competency_record_ids');
        $certificateEvidence = self::strictEvidenceIds($snapshot, 'certificate_ids');
        $competencyIds = $competencyEvidence['ids'];
        $certificateIds = $certificateEvidence['ids'];
        if ($competencyEvidence['malformed'] || $certificateEvidence['malformed']) {
            $issues[] = self::issue(
                'competence_evidence_not_found',
                $severity,
                '资格证据引用格式无效；证据字段必须是仅包含非空标量 ID 的数组。',
                $responsibility,
                $assignment
            );
            return;
        }
        if ($competencyIds === [] && $certificateIds === []) {
            $issues[] = self::issue(
                'competence_evidence_missing',
                $severity,
                '该人员绑定要求资格证据，但尚未引用能力确认记录或人员证书。',
                $responsibility,
                $assignment
            );
            return;
        }

        if (!$employee || !self::evidenceReferencesValid(
            $companyId,
            $employeeId,
            $competencyIds,
            $certificateIds
        )) {
            $issues[] = self::issue(
                'competence_evidence_not_found',
                $severity,
                '资格证据引用不存在、已失效、未发布、归属其他人员或结论不合格。',
                $responsibility,
                $assignment
            );
        }
    }

    private static function siteMatches(string $companyId, ?array $employee, array $assignment): bool
    {
        $siteId = trim((string)($assignment['site_id'] ?? ''));
        $siteScopeKey = trim((string)($assignment['site_scope_key'] ?? ''));
        if ($siteId === '' && ($siteScopeKey === '' || $siteScopeKey === '*')) {
            return true;
        }
        if ($siteId === '' || $siteScopeKey !== $siteId) {
            return false;
        }

        $site = Db::name('sites')
            ->where('id', $siteId)
            ->where('company_id', $companyId)
            ->where('status', 'active')
            ->where('publish', 1)
            ->where('soft_delete', 0)
            ->find();
        if (!$site) {
            return false;
        }
        if (!$employee) {
            return false;
        }

        $primarySiteId = trim((string)($employee['primary_site_id'] ?? ''));
        return $primarySiteId === '' || $primarySiteId === $siteId;
    }

    private static function appointmentDatesValid(array $assignment): bool
    {
        $from = trim((string)($assignment['proposed_from'] ?? ''));
        $until = trim((string)($assignment['proposed_until'] ?? ''));
        if (!self::isDate($from)) {
            return false;
        }
        if ($until === '') {
            return true;
        }
        if (!self::isDate($until) || $until < $from) {
            return false;
        }

        return $until >= date('Y-m-d');
    }

    private static function isDate(string $value): bool
    {
        if ($value === '') {
            return false;
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        $errors = DateTimeImmutable::getLastErrors();

        return $date !== false
            && $date->format('Y-m-d') === $value
            && (!is_array($errors) || ((int)$errors['warning_count'] === 0 && (int)$errors['error_count'] === 0));
    }

    private static function evidenceReferencesValid(
        string $companyId,
        string $employeeId,
        array $competencyIds,
        array $certificateIds
    ): bool {
        $today = date('Y-m-d');
        if ($competencyIds !== []) {
            $validCompetencyIds = Db::name('competency_records')
                ->whereIn('id', $competencyIds)
                ->where('company_id', $companyId)
                ->where('employee_id', $employeeId)
                ->whereIn('result', ['qualified', 'supervised'])
                ->where('publish', 1)
                ->where('soft_delete', 0)
                ->where(static function ($query) use ($today): void {
                    $query->whereNull('valid_until')->whereOr('valid_until', '>=', $today);
                })
                ->column('id');
            if (array_values(array_unique(array_map('strval', $validCompetencyIds))) !== $competencyIds) {
                $validSet = array_fill_keys(array_map('strval', $validCompetencyIds), true);
                foreach ($competencyIds as $id) {
                    if (!isset($validSet[$id])) {
                        return false;
                    }
                }
            }
        }

        if ($certificateIds !== []) {
            $validCertificateIds = Db::name('employee_certificates')
                ->whereIn('id', $certificateIds)
                ->where('company_id', $companyId)
                ->where('employee_id', $employeeId)
                ->where('status', 'active')
                ->where('publish', 1)
                ->where('soft_delete', 0)
                ->where(static function ($query) use ($today): void {
                    $query->whereNull('valid_until')->whereOr('valid_until', '>=', $today);
                })
                ->column('id');
            $validSet = array_fill_keys(array_map('strval', $validCertificateIds), true);
            foreach ($certificateIds as $id) {
                if (!isset($validSet[$id])) {
                    return false;
                }
            }
        }

        return true;
    }

    private static function approvalOwners(string $companyId): array
    {
        $today = date('Y-m-d');
        $appointments = Db::name('employee_appointments')
            ->alias('ea')
            ->join('employees e', 'e.id = ea.employee_id AND e.company_id = ea.company_id')
            ->leftJoin(
                'qms_positions p',
                "p.id = ea.position_id AND p.company_id = ea.company_id AND p.review_status = 'published' AND p.publish = 1 AND p.soft_delete = 0"
            )
            ->where('ea.company_id', $companyId)
            ->where('ea.appointment_type', 'role')
            ->where('ea.status', 'active')
            ->where('ea.publish', 1)
            ->where('ea.soft_delete', 0)
            ->whereNotNull('ea.appointed_at')
            ->where('ea.appointed_at', '<=', $today)
            ->where(static function ($query) use ($today): void {
                $query->whereNull('ea.valid_until')->whereOr('ea.valid_until', '>=', $today);
            })
            ->where('e.company_id', $companyId)
            ->where('e.publish', 1)
            ->where('e.soft_delete', 0)
            ->field('ea.employee_id,ea.source_kind,p.code position_code')
            ->order('ea.employee_id,ea.id')
            ->select()
            ->toArray();

        $owners = [
            'company_general_manager' => [],
            'lab_director' => [],
        ];
        foreach ($appointments as $appointment) {
            $employeeId = (string)$appointment['employee_id'];
            $positionCode = trim((string)($appointment['position_code'] ?? ''));

            if (
                $positionCode === 'company_general_manager'
                && (string)$appointment['source_kind'] === 'corporate_evidence'
            ) {
                $owners['company_general_manager'][$employeeId] = true;
            }
            if (
                $positionCode === 'lab_director'
                && (string)$appointment['source_kind'] === 'responsibility_chain'
            ) {
                $owners['lab_director'][$employeeId] = true;
            }
        }

        return [
            'company_general_manager' => array_keys($owners['company_general_manager']),
            'lab_director' => array_keys($owners['lab_director']),
        ];
    }

    private static function hasLabDirectorApprovedAssignments(
        array $responsibilities,
        array $assignmentsByResponsibility
    ): bool {
        foreach ($responsibilities as $responsibility) {
            if (($assignmentsByResponsibility[(string)$responsibility['id']] ?? []) === []) {
                continue;
            }
            if (self::requiresLabDirectorApproval($responsibility)) {
                return true;
            }
        }

        return false;
    }

    private static function requiresLabDirectorApproval(array $responsibility): bool
    {
        if ((string)($responsibility['fixed_position_code'] ?? '') === 'lab_director') {
            return false;
        }

        return (string)$responsibility['assignment_mode'] === 'named_person'
            || (string)$responsibility['slot_kind'] === 'activity_role';
    }

    private static function validateSelfApproval(
        array $responsibilities,
        array $assignmentsByResponsibility,
        array $owners,
        string $severity,
        array &$issues
    ): void {
        $generalManagers = array_fill_keys($owners['company_general_manager'], true);
        $labDirectors = array_fill_keys($owners['lab_director'], true);

        foreach ($responsibilities as $responsibility) {
            $assignments = $assignmentsByResponsibility[(string)$responsibility['id']] ?? [];
            foreach ($assignments as $assignment) {
                $employeeId = (string)$assignment['employee_id'];
                $fixedPositionCode = (string)($responsibility['fixed_position_code'] ?? '');
                if ($fixedPositionCode === 'lab_director') {
                    if ($generalManagers !== [] && isset($generalManagers[$employeeId])) {
                        $issues[] = self::issue(
                            'self_approval_conflict',
                            $severity,
                            '公司总经理不得批准自己担任实验室主任。',
                            $responsibility,
                            $assignment
                        );
                    }
                    continue;
                }

                if (
                    self::requiresLabDirectorApproval($responsibility)
                    && $labDirectors !== []
                    && isset($labDirectors[$employeeId])
                ) {
                    $issues[] = self::issue(
                        'self_approval_conflict',
                        $severity,
                        '实验室主任不得批准自己的其他实名岗位或活动角色。',
                        $responsibility,
                        $assignment
                    );
                }
            }
        }
    }

    private static function validateSeparationRules(
        array $responsibilities,
        array $assignmentsByResponsibility,
        string $severity,
        array &$issues
    ): void {
        $responsibilitiesByActivity = [];
        foreach ($responsibilities as $responsibility) {
            $responsibilitiesByActivity[(string)$responsibility['activity_id']][] = $responsibility;
        }

        foreach ($responsibilities as $responsibility) {
            $ruleCodes = (array)$responsibility['rule_codes'];
            $assignments = $assignmentsByResponsibility[(string)$responsibility['id']] ?? [];
            if ($assignments === []) {
                continue;
            }

            if (in_array('no_self_audit', $ruleCodes, true)) {
                $auditedOwners = self::runtimeEmployeeSet(
                    $responsibilitiesByActivity[(string)$responsibility['activity_id']] ?? [],
                    $assignmentsByResponsibility,
                    static fn (array $candidate): bool => (string)($candidate['dynamic_owner_code'] ?? '') === 'audited_activity_owner'
                );
                foreach ($assignments as $assignment) {
                    $employeeId = (string)$assignment['employee_id'];
                    $snapshot = (array)$assignment['competence_snapshot'];
                    $explicitAudited = array_fill_keys(array_merge(
                        self::stringIds($snapshot['audited_employee_ids'] ?? []),
                        self::stringIds($snapshot['audited_owner_employee_ids'] ?? [])
                    ), true);
                    if (isset($auditedOwners[$employeeId]) || isset($explicitAudited[$employeeId])) {
                        $issues[] = self::issue(
                            'self_audit_conflict',
                            $severity,
                            '内审人员与被审核活动责任人重叠，构成自审。',
                            $responsibility,
                            $assignment
                        );
                    }
                }
            }

            if (in_array('separate_executor_verifier', $ruleCodes, true)) {
                $executors = self::runtimeEmployeeSet(
                    $responsibilitiesByActivity[(string)$responsibility['activity_id']] ?? [],
                    $assignmentsByResponsibility,
                    static fn (array $candidate): bool =>
                        (string)($candidate['dynamic_owner_code'] ?? '') === 'risk_treatment_owner'
                        || (
                            (string)$candidate['duty_type'] === 'execute'
                            && (string)$candidate['step_code'] === 'risk_implement'
                        )
                );
                foreach ($assignments as $assignment) {
                    $employeeId = (string)$assignment['employee_id'];
                    $snapshot = (array)$assignment['competence_snapshot'];
                    $explicitExecutors = array_fill_keys(
                        self::stringIds($snapshot['executing_employee_ids'] ?? []),
                        true
                    );
                    if (isset($executors[$employeeId]) || isset($explicitExecutors[$employeeId])) {
                        $issues[] = self::issue(
                            'executor_verifier_conflict',
                            $severity,
                            '风险措施执行人与验证人重叠，未实现独立验证。',
                            $responsibility,
                            $assignment
                        );
                    }
                }
            }
        }
    }

    private static function runtimeEmployeeSet(
        array $activityResponsibilities,
        array $assignmentsByResponsibility,
        callable $matches
    ): array {
        $employeeSet = [];
        foreach ($activityResponsibilities as $candidate) {
            if (!$matches($candidate)) {
                continue;
            }
            foreach ($assignmentsByResponsibility[(string)$candidate['id']] ?? [] as $assignment) {
                $employeeSet[(string)$assignment['employee_id']] = true;
            }
        }

        return $employeeSet;
    }

    private static function issue(
        string $code,
        string $severity,
        string $message,
        ?array $responsibility = null,
        ?array $assignment = null
    ): array {
        return [
            'code' => $code,
            'severity' => $severity,
            'message' => $message,
            'activity_code' => (string)($responsibility['activity_code'] ?? ''),
            'step_code' => (string)($responsibility['step_code'] ?? ''),
            'responsibility_id' => (string)($responsibility['id'] ?? ''),
            'assignment_id' => isset($assignment['id']) ? (string)$assignment['id'] : null,
            'employee_id' => isset($assignment['employee_id']) ? (string)$assignment['employee_id'] : null,
        ];
    }

    private static function sortIssues(array &$issues): void
    {
        $severityRank = ['blocker' => 0, 'warning' => 1];
        usort($issues, static function (array $left, array $right) use ($severityRank): int {
            return [
                $severityRank[(string)$left['severity']] ?? 9,
                (string)$left['activity_code'],
                (string)$left['step_code'],
                (string)$left['code'],
                (string)$left['responsibility_id'],
                (string)($left['assignment_id'] ?? ''),
                (string)($left['employee_id'] ?? ''),
            ] <=> [
                $severityRank[(string)$right['severity']] ?? 9,
                (string)$right['activity_code'],
                (string)$right['step_code'],
                (string)$right['code'],
                (string)$right['responsibility_id'],
                (string)($right['assignment_id'] ?? ''),
                (string)($right['employee_id'] ?? ''),
            ];
        });
    }

    private static function result(array $issues): string
    {
        foreach ($issues as $issue) {
            if ((string)$issue['severity'] === 'blocker') {
                return 'blocker';
            }
        }

        return $issues === [] ? 'pass' : 'warning';
    }

    private static function stringIds(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }
        $ids = [];
        foreach ($value as $id) {
            if (!is_scalar($id)) {
                continue;
            }
            $id = trim((string)$id);
            if ($id !== '') {
                $ids[$id] = true;
            }
        }

        return array_keys($ids);
    }

    private static function strictEvidenceIds(array $snapshot, string $key): array
    {
        if (!array_key_exists($key, $snapshot)) {
            return ['ids' => [], 'malformed' => false];
        }
        if (!is_array($snapshot[$key])) {
            return ['ids' => [], 'malformed' => true];
        }

        $ids = [];
        $malformed = false;
        foreach ($snapshot[$key] as $id) {
            if (!is_scalar($id)) {
                $malformed = true;
                continue;
            }
            $id = trim((string)$id);
            if ($id === '') {
                $malformed = true;
                continue;
            }
            $ids[$id] = true;
        }

        return ['ids' => array_keys($ids), 'malformed' => $malformed];
    }

    private static function decodeJson(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }
        if (!is_string($value) || trim($value) === '') {
            return [];
        }
        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }
}
