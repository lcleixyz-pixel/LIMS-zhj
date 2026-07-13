<?php
declare(strict_types=1);

namespace app\service;

use DateTimeImmutable;
use DomainException;
use think\facade\Config;
use think\facade\Db;
use think\facade\Session;

final class QmsResponsibilityDraftService
{
    private const GLOBAL_SITE_SCOPE = '*';

    public static function versionDetail(string $versionId): array
    {
        $companyId = self::companyId();
        $version = Db::name('qms_responsibility_chain_versions')
            ->where('id', $versionId)
            ->where('company_id', $companyId)
            ->where('soft_delete', 0)
            ->find();
        if (!$version) {
            throw new DomainException('责任链版本不存在。');
        }

        $activities = Db::name('qms_responsibility_activities')
            ->where('chain_version_id', $versionId)
            ->where('company_id', $companyId)
            ->where('soft_delete', 0)
            ->order('sort_order,activity_code')
            ->select()
            ->toArray();
        $activityIds = array_column($activities, 'id');

        $responsibilities = [];
        if ($activityIds !== []) {
            $responsibilities = Db::name('qms_activity_responsibilities')
                ->alias('r')
                ->join('qms_responsibility_activities a', 'a.id = r.activity_id AND a.company_id = r.company_id')
                ->leftJoin('qms_positions p', 'p.id = r.fixed_position_id AND p.company_id = r.company_id')
                ->whereIn('r.activity_id', $activityIds)
                ->where('r.company_id', $companyId)
                ->where('r.soft_delete', 0)
                ->where('a.soft_delete', 0)
                ->field('r.*,a.activity_code,a.name activity_name,a.sort_order activity_sort_order,p.code fixed_position_code,p.name fixed_position_name')
                ->order('a.sort_order,r.sort_order,r.step_code')
                ->select()
                ->toArray();
        }

        $responsibilityIds = array_column($responsibilities, 'id');
        $assignments = [];
        if ($responsibilityIds !== []) {
            $assignments = Db::name('qms_responsibility_assignments')
                ->alias('ra')
                ->leftJoin('employees e', 'e.id = ra.employee_id AND e.company_id = ra.company_id')
                ->leftJoin('sites s', 's.id = ra.site_id AND s.company_id = ra.company_id')
                ->whereIn('ra.responsibility_id', $responsibilityIds)
                ->where('ra.company_id', $companyId)
                ->where('ra.soft_delete', 0)
                ->field('ra.*,e.name employee_name,e.employee_number,s.name site_name,s.code site_code')
                ->order('ra.responsibility_id,ra.employee_id,ra.site_scope_key')
                ->select()
                ->toArray();
        }

        $assignmentsByResponsibility = [];
        foreach ($assignments as &$assignment) {
            $assignment['competence_snapshot'] = self::decodeJson($assignment['competence_snapshot'] ?? null);
            $assignment['validation_details'] = self::decodeJson($assignment['validation_details'] ?? null);
            $assignmentsByResponsibility[(string)$assignment['responsibility_id']][] = $assignment;
        }
        unset($assignment);

        $responsibilitiesByActivity = [];
        $namedPersonUnbound = 0;
        $dynamicSlots = [];
        foreach ($responsibilities as &$responsibility) {
            foreach (['eligibility_rule', 'rule_codes', 'source_refs'] as $jsonField) {
                $responsibility[$jsonField] = self::decodeJson($responsibility[$jsonField] ?? null);
            }
            $responsibility['assignments'] = $assignmentsByResponsibility[(string)$responsibility['id']] ?? [];
            $responsibilitiesByActivity[(string)$responsibility['activity_id']][] = $responsibility;

            if ((string)$responsibility['assignment_mode'] === 'named_person') {
                if ((int)$responsibility['required'] === 1 && $responsibility['assignments'] === []) {
                    $namedPersonUnbound++;
                }
                continue;
            }
            $dynamicSlots[] = [
                'responsibility_id' => (string)$responsibility['id'],
                'activity_code' => (string)$responsibility['activity_code'],
                'step_code' => (string)$responsibility['step_code'],
                'slot_kind' => (string)$responsibility['slot_kind'],
                'assignment_mode' => (string)$responsibility['assignment_mode'],
                'display_status' => '运行时指定',
            ];
        }
        unset($responsibility);

        foreach ($activities as &$activity) {
            $activity['source_refs'] = self::decodeJson($activity['source_refs'] ?? null);
            $activity['responsibilities'] = $responsibilitiesByActivity[(string)$activity['id']] ?? [];
            $activity['responsibility_count'] = count($activity['responsibilities']);
        }
        unset($activity);

        return [
            'id' => (string)$version['id'],
            'chain_code' => (string)$version['chain_code'],
            'version_no' => (int)$version['version_no'],
            'status' => (string)$version['status'],
            'replaces_version_id' => $version['replaces_version_id'] === null ? null : (string)$version['replaces_version_id'],
            'version' => $version,
            'activities' => $activities,
            'responsibilities' => $responsibilities,
            'assignments' => $assignments,
            'named_person_unbound' => $namedPersonUnbound,
            'dynamic_slots' => $dynamicSlots,
            'structure_test_allowed' => true,
        ];
    }

    public static function saveAssignment(
        string $responsibilityId,
        string $employeeId,
        ?string $siteId,
        string $proposedFrom,
        ?string $proposedUntil,
        array $competenceEvidence
    ): array {
        self::assertDate($proposedFrom, '建议生效日期');
        if ($proposedUntil !== null && $proposedUntil !== '') {
            self::assertDate($proposedUntil, '建议失效日期');
            if ($proposedUntil < $proposedFrom) {
                throw new DomainException('建议失效日期不得早于建议生效日期。');
            }
        } else {
            $proposedUntil = null;
        }

        return Db::transaction(function () use (
            $responsibilityId,
            $employeeId,
            $siteId,
            $proposedFrom,
            $proposedUntil,
            $competenceEvidence
        ): array {
            $companyId = self::companyId();
            $context = self::responsibilityContext($responsibilityId, $companyId, true);
            self::assertDraftVersion($context);
            if ((string)$context['assignment_mode'] === 'derived_from_scope') {
                throw new DomainException('该动态责任槽在活动运行时指定，不得保存为永久人员任命。');
            }

            $employee = Db::name('employees')
                ->where('id', $employeeId)
                ->where('company_id', $companyId)
                ->where('publish', 1)
                ->where('soft_delete', 0)
                ->lock(true)
                ->find();
            if (!$employee) {
                throw new DomainException('人员不存在、未发布或不属于当前公司。');
            }

            $siteScopeKey = self::GLOBAL_SITE_SCOPE;
            if ($siteId !== null && $siteId !== '') {
                $site = Db::name('sites')
                    ->where('id', $siteId)
                    ->where('company_id', $companyId)
                    ->where('status', 'active')
                    ->where('publish', 1)
                    ->where('soft_delete', 0)
                    ->lock(true)
                    ->find();
                if (!$site) {
                    throw new DomainException('场所不存在、未启用或不属于当前公司。');
                }
                $siteScopeKey = $siteId;
            } else {
                $siteId = null;
            }

            $snapshot = self::normalizeValue($competenceEvidence);
            $now = date('Y-m-d H:i:s');
            $actorId = self::actorId();
            $existing = Db::name('qms_responsibility_assignments')
                ->where('responsibility_id', $responsibilityId)
                ->where('company_id', $companyId)
                ->where('employee_id', $employeeId)
                ->where('site_scope_key', $siteScopeKey)
                ->lock(true)
                ->find();

            $payload = [
                'company_id' => $companyId,
                'responsibility_id' => $responsibilityId,
                'employee_id' => $employeeId,
                'site_id' => $siteId,
                'site_scope_key' => $siteScopeKey,
                'proposed_from' => $proposedFrom,
                'proposed_until' => $proposedUntil,
                'competence_snapshot' => self::encodeJson($snapshot),
                'validation_status' => null,
                'validation_details' => null,
                'status' => 'draft',
                'publish' => 1,
                'soft_delete' => 0,
                'modified' => $now,
                'modified_by' => $actorId,
            ];
            if ($existing) {
                if ((string)$existing['status'] !== 'draft') {
                    throw new DomainException('已存在非草案人员绑定，不得覆盖历史证据。');
                }
                $assignmentId = (string)$existing['id'];
                Db::name('qms_responsibility_assignments')->where('id', $assignmentId)->update($payload);
            } else {
                $assignmentId = qms_uuid();
                Db::name('qms_responsibility_assignments')->insert(array_merge($payload, [
                    'id' => $assignmentId,
                    'created' => $now,
                    'created_by' => $actorId,
                ]));
            }

            return self::assignmentRow($assignmentId, $companyId);
        });
    }

    public static function removeAssignment(string $assignmentId): void
    {
        Db::transaction(function () use ($assignmentId): void {
            $companyId = self::companyId();
            $assignment = Db::name('qms_responsibility_assignments')
                ->alias('ra')
                ->join('qms_activity_responsibilities r', 'r.id = ra.responsibility_id AND r.company_id = ra.company_id')
                ->join('qms_responsibility_activities a', 'a.id = r.activity_id AND a.company_id = r.company_id')
                ->join('qms_responsibility_chain_versions v', 'v.id = a.chain_version_id AND v.company_id = a.company_id')
                ->where('ra.id', $assignmentId)
                ->where('ra.company_id', $companyId)
                ->where('ra.soft_delete', 0)
                ->field('ra.id,ra.status,v.status version_status')
                ->lock(true)
                ->find();
            if (!$assignment) {
                throw new DomainException('人员草案绑定不存在。');
            }
            if ((string)$assignment['version_status'] !== 'draft') {
                throw new DomainException('仅草案版本允许移除人员绑定。');
            }
            if ((string)$assignment['status'] !== 'draft') {
                throw new DomainException('仅草案状态的人员绑定可移除。');
            }

            Db::name('qms_responsibility_assignments')->where('id', $assignmentId)->update([
                'soft_delete' => 1,
                'modified' => date('Y-m-d H:i:s'),
                'modified_by' => self::actorId(),
            ]);
        });
    }

    public static function cloneEffectiveVersion(string $versionId): array
    {
        return QmsPositionAliasService::withSeededCatalogLock(function (string $companyId) use ($versionId): array {
            $source = Db::name('qms_responsibility_chain_versions')
                ->where('id', $versionId)
                ->where('company_id', $companyId)
                ->where('soft_delete', 0)
                ->lock(true)
                ->find();
            if (!$source || (string)$source['status'] !== 'effective') {
                throw new DomainException('仅有效版本可克隆为下一个草案。');
            }

            $existingDraft = Db::name('qms_responsibility_chain_versions')
                ->where('company_id', $companyId)
                ->where('chain_code', (string)$source['chain_code'])
                ->where('replaces_version_id', $versionId)
                ->where('status', 'draft')
                ->where('soft_delete', 0)
                ->lock(true)
                ->find();
            if ($existingDraft) {
                return self::versionDetail((string)$existingDraft['id']);
            }

            $maxVersion = (int)Db::name('qms_responsibility_chain_versions')
                ->where('company_id', $companyId)
                ->where('chain_code', (string)$source['chain_code'])
                ->where('soft_delete', 0)
                ->lock(true)
                ->max('version_no');
            $now = date('Y-m-d H:i:s');
            $actorId = self::actorId();
            $newVersionId = qms_uuid();
            Db::name('qms_responsibility_chain_versions')->insert([
                'id' => $newVersionId,
                'company_id' => $companyId,
                'chain_code' => (string)$source['chain_code'],
                'version_no' => $maxVersion + 1,
                'status' => 'draft',
                'content_hash' => null,
                'replaces_version_id' => $versionId,
                'publish' => 1,
                'soft_delete' => 0,
                'created' => $now,
                'modified' => $now,
                'created_by' => $actorId,
                'modified_by' => $actorId,
            ]);

            $activityMap = [];
            $activities = Db::name('qms_responsibility_activities')
                ->where('chain_version_id', $versionId)
                ->where('company_id', $companyId)
                ->where('soft_delete', 0)
                ->order('sort_order,activity_code')
                ->select()
                ->toArray();
            foreach ($activities as $activity) {
                $newActivityId = qms_uuid();
                $activityMap[(string)$activity['id']] = $newActivityId;
                Db::name('qms_responsibility_activities')->insert([
                    'id' => $newActivityId,
                    'company_id' => $companyId,
                    'chain_version_id' => $newVersionId,
                    'activity_code' => (string)$activity['activity_code'],
                    'name' => (string)$activity['name'],
                    'element_key' => $activity['element_key'],
                    'site_scope' => (string)$activity['site_scope'],
                    'source_refs' => $activity['source_refs'],
                    'sort_order' => (int)$activity['sort_order'],
                    'publish' => 1,
                    'soft_delete' => 0,
                    'created' => $now,
                    'modified' => $now,
                    'created_by' => $actorId,
                    'modified_by' => $actorId,
                ]);
            }

            $responsibilityMap = [];
            if ($activityMap !== []) {
                $responsibilities = Db::name('qms_activity_responsibilities')
                    ->whereIn('activity_id', array_keys($activityMap))
                    ->where('company_id', $companyId)
                    ->where('soft_delete', 0)
                    ->order('activity_id,sort_order,step_code')
                    ->select()
                    ->toArray();
                foreach ($responsibilities as $responsibility) {
                    $newResponsibilityId = qms_uuid();
                    $responsibilityMap[(string)$responsibility['id']] = $newResponsibilityId;
                    Db::name('qms_activity_responsibilities')->insert([
                        'id' => $newResponsibilityId,
                        'company_id' => $companyId,
                        'activity_id' => $activityMap[(string)$responsibility['activity_id']],
                        'step_code' => (string)$responsibility['step_code'],
                        'duty_type' => (string)$responsibility['duty_type'],
                        'duty_text' => (string)$responsibility['duty_text'],
                        'slot_kind' => (string)$responsibility['slot_kind'],
                        'assignment_mode' => (string)$responsibility['assignment_mode'],
                        'fixed_position_id' => $responsibility['fixed_position_id'],
                        'activity_role_code' => $responsibility['activity_role_code'],
                        'dynamic_owner_code' => $responsibility['dynamic_owner_code'],
                        'required' => (int)$responsibility['required'],
                        'eligibility_rule' => $responsibility['eligibility_rule'],
                        'rule_codes' => $responsibility['rule_codes'],
                        'source_refs' => $responsibility['source_refs'],
                        'sort_order' => (int)$responsibility['sort_order'],
                        'publish' => 1,
                        'soft_delete' => 0,
                        'created' => $now,
                        'modified' => $now,
                        'created_by' => $actorId,
                        'modified_by' => $actorId,
                    ]);
                }
            }

            if ($responsibilityMap !== []) {
                $assignments = Db::name('qms_responsibility_assignments')
                    ->whereIn('responsibility_id', array_keys($responsibilityMap))
                    ->where('company_id', $companyId)
                    ->where('soft_delete', 0)
                    ->whereNotIn('status', ['revoked', 'expired'])
                    ->order('responsibility_id,employee_id,site_scope_key')
                    ->select()
                    ->toArray();
                foreach ($assignments as $assignment) {
                    Db::name('qms_responsibility_assignments')->insert([
                        'id' => qms_uuid(),
                        'company_id' => $companyId,
                        'responsibility_id' => $responsibilityMap[(string)$assignment['responsibility_id']],
                        'employee_id' => (string)$assignment['employee_id'],
                        'site_id' => $assignment['site_id'],
                        'site_scope_key' => (string)$assignment['site_scope_key'],
                        'proposed_from' => $assignment['proposed_from'],
                        'proposed_until' => $assignment['proposed_until'],
                        'competence_snapshot' => $assignment['competence_snapshot'],
                        'validation_status' => null,
                        'validation_details' => null,
                        'status' => 'draft',
                        'publish' => 1,
                        'soft_delete' => 0,
                        'created' => $now,
                        'modified' => $now,
                        'created_by' => $actorId,
                        'modified_by' => $actorId,
                    ]);
                }
            }

            return self::versionDetail($newVersionId);
        });
    }

    public static function contentHash(string $versionId): string
    {
        $detail = self::versionDetail($versionId);
        $content = [];
        foreach ($detail['activities'] as $activity) {
            $activityContent = [
                'activity_code' => (string)$activity['activity_code'],
                'name' => (string)$activity['name'],
                'element_key' => (string)($activity['element_key'] ?? ''),
                'site_scope' => (string)$activity['site_scope'],
                'source_refs' => self::normalizeValue((array)$activity['source_refs']),
                'sort_order' => (int)$activity['sort_order'],
                'responsibilities' => [],
            ];
            foreach ($activity['responsibilities'] as $responsibility) {
                $assignments = [];
                foreach ($responsibility['assignments'] as $assignment) {
                    $assignments[] = [
                        'employee_id' => (string)$assignment['employee_id'],
                        'site_scope_key' => (string)$assignment['site_scope_key'],
                        'proposed_from' => (string)($assignment['proposed_from'] ?? ''),
                        'proposed_until' => (string)($assignment['proposed_until'] ?? ''),
                        'competence_snapshot' => self::normalizeValue((array)$assignment['competence_snapshot']),
                    ];
                }
                usort($assignments, static fn (array $left, array $right): int => self::stableJson($left) <=> self::stableJson($right));

                $activityContent['responsibilities'][] = [
                    'step_code' => (string)$responsibility['step_code'],
                    'duty_type' => (string)$responsibility['duty_type'],
                    'duty_text' => (string)$responsibility['duty_text'],
                    'slot_kind' => (string)$responsibility['slot_kind'],
                    'assignment_mode' => (string)$responsibility['assignment_mode'],
                    'fixed_position_code' => (string)($responsibility['fixed_position_code'] ?? ''),
                    'activity_role_code' => (string)($responsibility['activity_role_code'] ?? ''),
                    'dynamic_owner_code' => (string)($responsibility['dynamic_owner_code'] ?? ''),
                    'required' => (int)$responsibility['required'],
                    'eligibility_rule' => self::normalizeValue((array)$responsibility['eligibility_rule']),
                    'rule_codes' => self::normalizeValue((array)$responsibility['rule_codes']),
                    'source_refs' => self::normalizeValue((array)$responsibility['source_refs']),
                    'sort_order' => (int)$responsibility['sort_order'],
                    'assignments' => $assignments,
                ];
            }
            usort(
                $activityContent['responsibilities'],
                static fn (array $left, array $right): int => [$left['sort_order'], $left['step_code']] <=> [$right['sort_order'], $right['step_code']]
            );
            $content[] = $activityContent;
        }
        usort(
            $content,
            static fn (array $left, array $right): int => [$left['sort_order'], $left['activity_code']] <=> [$right['sort_order'], $right['activity_code']]
        );

        return hash('sha256', self::stableJson($content));
    }

    private static function responsibilityContext(string $responsibilityId, string $companyId, bool $lock): array
    {
        $query = Db::name('qms_activity_responsibilities')
            ->alias('r')
            ->join('qms_responsibility_activities a', 'a.id = r.activity_id AND a.company_id = r.company_id')
            ->join('qms_responsibility_chain_versions v', 'v.id = a.chain_version_id AND v.company_id = a.company_id')
            ->where('r.id', $responsibilityId)
            ->where('r.company_id', $companyId)
            ->where('r.soft_delete', 0)
            ->where('a.soft_delete', 0)
            ->where('v.soft_delete', 0)
            ->field('r.*,v.id version_id,v.status version_status');
        if ($lock) {
            $query->lock(true);
        }
        $context = $query->find();
        if (!$context) {
            throw new DomainException('责任项不存在或不属于当前公司。');
        }

        return $context;
    }

    private static function assertDraftVersion(array $context): void
    {
        if ((string)$context['version_status'] !== 'draft') {
            throw new DomainException('仅草案版本允许维护人员绑定。');
        }
    }

    private static function assignmentRow(string $assignmentId, string $companyId): array
    {
        $row = Db::name('qms_responsibility_assignments')
            ->where('id', $assignmentId)
            ->where('company_id', $companyId)
            ->where('soft_delete', 0)
            ->find();
        if (!$row) {
            throw new DomainException('人员草案绑定保存失败。');
        }
        $row['competence_snapshot'] = self::decodeJson($row['competence_snapshot'] ?? null);
        $row['validation_details'] = self::decodeJson($row['validation_details'] ?? null);

        return $row;
    }

    private static function assertDate(string $value, string $label): void
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        $errors = DateTimeImmutable::getLastErrors();
        if (
            !$date
            || $date->format('Y-m-d') !== $value
            || (is_array($errors) && ((int)$errors['warning_count'] > 0 || (int)$errors['error_count'] > 0))
        ) {
            throw new DomainException($label . '必须为有效的 YYYY-MM-DD 日期。');
        }
    }

    private static function normalizeValue(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }
        $normalized = [];
        foreach ($value as $key => $item) {
            $normalized[$key] = self::normalizeValue($item);
        }
        if (array_is_list($normalized)) {
            usort($normalized, static fn (mixed $left, mixed $right): int => self::stableJson($left) <=> self::stableJson($right));
        } else {
            ksort($normalized, SORT_STRING);
        }

        return $normalized;
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

    private static function encodeJson(array $value): string
    {
        return (string)json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    private static function stableJson(mixed $value): string
    {
        return (string)json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    private static function companyId(): string
    {
        return (string)Config::get('qms.company_id');
    }

    private static function actorId(): ?string
    {
        return Session::has('user.id') ? (string)Session::get('user.id') : null;
    }
}
