<?php
declare(strict_types=1);

namespace app\service;

use DomainException;
use think\facade\Config;
use think\facade\Db;

final class QmsResponsibilityAlignmentService
{
    private const PILOT_STEP_BY_REQUIREMENT = [
        'Y13-CX20' => 'ia_annual_plan',
        'Y13-CX21' => 'mr_preside_approve',
        'Y13-CX32' => 'risk_general_approval',
    ];

    public static function baselineForVersion(string $versionId, bool $draftPreview = false): array
    {
        $versionId = trim($versionId);
        if ($versionId === '') {
            throw new DomainException('责任链版本 ID 不能为空。');
        }

        $companyId = (string)Config::get('qms.company_id');
        return Db::transaction(
            static fn (): array => self::readBaseline($versionId, $draftPreview, $companyId)
        );
    }

    private static function readBaseline(string $versionId, bool $draftPreview, string $companyId): array
    {
        $version = Db::name('qms_responsibility_chain_versions')
            ->where('id', $versionId)
            ->where('company_id', $companyId)
            ->where('publish', 1)
            ->where('soft_delete', 0)
            ->find();
        if (!$version) {
            throw new DomainException('责任链版本不存在或不属于当前公司。');
        }

        $status = (string)$version['status'];
        if ($status !== 'effective' && !($draftPreview && $status === 'draft')) {
            throw new DomainException('责任链对齐只接受有效版本，草案必须由页面明确进入预览模式。');
        }

        $contentHash = trim((string)($version['content_hash'] ?? ''));
        if ($status === 'draft') {
            $contentHash = QmsResponsibilityDraftService::contentHash($versionId);
        } elseif (preg_match('/^[a-f0-9]{64}$/i', $contentHash) !== 1) {
            throw new DomainException('有效责任链版本缺少合法的锁定内容哈希。');
        } elseif (!hash_equals($contentHash, QmsResponsibilityDraftService::contentHash($versionId))) {
            throw new DomainException('有效责任链内容与锁定哈希不一致，禁止作为文件对齐基准。');
        }

        $activities = Db::name('qms_responsibility_activities')
            ->where('chain_version_id', $versionId)
            ->where('company_id', $companyId)
            ->where('publish', 1)
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
                ->where('r.publish', 1)
                ->where('r.soft_delete', 0)
                ->where('a.publish', 1)
                ->where('a.soft_delete', 0)
                ->field('r.id,r.activity_id,r.step_code,r.duty_type,r.duty_text,r.slot_kind,r.assignment_mode,r.fixed_position_id,r.activity_role_code,r.dynamic_owner_code,r.source_refs,r.sort_order,a.activity_code,a.name activity_name,p.code position_code,p.name position_name')
                ->order('a.sort_order,r.sort_order,r.step_code')
                ->select()
                ->toArray();
        }

        $responsibilitiesByActivity = [];
        $normalizedResponsibilities = [];
        foreach ($responsibilities as $responsibility) {
            $normalized = [
                'responsibility_id' => (string)$responsibility['id'],
                'activity_code' => (string)$responsibility['activity_code'],
                'activity_name' => (string)$responsibility['activity_name'],
                'step_code' => (string)$responsibility['step_code'],
                'duty_type' => (string)$responsibility['duty_type'],
                'duty_text' => (string)$responsibility['duty_text'],
                'slot_kind' => (string)$responsibility['slot_kind'],
                'assignment_mode' => (string)$responsibility['assignment_mode'],
                'position_code' => self::nullableString($responsibility['position_code'] ?? null),
                'position_name' => self::nullableString($responsibility['position_name'] ?? null),
                'activity_role_code' => self::nullableString($responsibility['activity_role_code'] ?? null),
                'dynamic_owner_code' => self::nullableString($responsibility['dynamic_owner_code'] ?? null),
                'source_refs' => self::decodeJson($responsibility['source_refs'] ?? null),
            ];
            $normalizedResponsibilities[] = $normalized;
            $responsibilitiesByActivity[(string)$responsibility['activity_id']][] = $normalized;
        }

        $normalizedActivities = [];
        foreach ($activities as $activity) {
            $normalizedActivities[] = [
                'activity_id' => (string)$activity['id'],
                'activity_code' => (string)$activity['activity_code'],
                'activity_name' => (string)$activity['name'],
                'element_key' => self::nullableString($activity['element_key'] ?? null),
                'site_scope' => (string)$activity['site_scope'],
                'source_refs' => self::decodeJson($activity['source_refs'] ?? null),
                'responsibilities' => $responsibilitiesByActivity[(string)$activity['id']] ?? [],
            ];
        }

        $aliasRows = Db::name('qms_position_aliases')
            ->alias('a')
            ->join('qms_positions p', 'p.id = a.position_id AND p.company_id = a.company_id')
            ->where('a.company_id', $companyId)
            ->where('a.source_scope', 'position_catalog')
            ->where('a.site_scope_key', '*')
            ->where('a.publish', 1)
            ->where('a.soft_delete', 0)
            ->where('p.publish', 1)
            ->where('p.soft_delete', 0)
            ->field('a.alias,a.confirmation_status,a.source_scope,a.site_scope_key,p.code position_code,p.name position_name')
            ->order('p.code,a.alias')
            ->select()
            ->toArray();
        $aliases = [];
        $positions = [];
        foreach ($aliasRows as $row) {
            $alias = (string)$row['alias'];
            $positionCode = (string)$row['position_code'];
            $aliases[$alias] = [
                'position_code' => $positionCode,
                'position_name' => (string)$row['position_name'],
                'confirmation_status' => (string)$row['confirmation_status'],
                'source_scope' => (string)$row['source_scope'],
                'site_scope_key' => (string)$row['site_scope_key'],
            ];
            $positions[$positionCode] ??= [
                'position_code' => $positionCode,
                'position_name' => (string)$row['position_name'],
                'aliases' => [],
            ];
            $positions[$positionCode]['aliases'][] = $alias;
        }

        return [
            'version' => [
                'id' => $versionId,
                'chain_code' => (string)$version['chain_code'],
                'version_no' => (int)$version['version_no'],
                'status' => $status,
                'content_hash' => $contentHash,
            ],
            'activities' => $normalizedActivities,
            'responsibilities' => $normalizedResponsibilities,
            'aliases' => $aliases,
            'role_catalog' => [
                'positions' => $positions,
                'aliases' => $aliases,
            ],
        ];
    }

    public static function injectBaseline(array $inputs, array $baseline): array
    {
        $responsibilitiesByStep = [];
        foreach ((array)($baseline['responsibilities'] ?? []) as $responsibility) {
            $responsibilitiesByStep[(string)($responsibility['step_code'] ?? '')] = (array)$responsibility;
        }

        $requirements = [];
        foreach ((array)($inputs['requirements'] ?? []) as $requirement) {
            if ((string)($requirement['rule'] ?? '') !== 'responsibility_chain') {
                $requirements[] = $requirement;
                continue;
            }

            $findingId = (string)($requirement['id'] ?? '');
            $stepCode = self::PILOT_STEP_BY_REQUIREMENT[$findingId] ?? '';
            if ($stepCode === '') {
                $requirements[] = $requirement;
                continue;
            }
            $source = $responsibilitiesByStep[$stepCode] ?? [];
            if ($source === []) {
                throw new DomainException($findingId . ' 未在责任链基准中找到来源责任项：' . $stepCode);
            }
            $positionCode = trim((string)($source['position_code'] ?? ''));
            $positionName = trim((string)($source['position_name'] ?? ''));
            if ($positionCode === '' || $positionName === '') {
                throw new DomainException($findingId . ' 的来源责任项不是可比较的正式岗位：' . $stepCode);
            }

            $requirement['expected'] = array_replace((array)$requirement['expected'], [
                'role' => $positionName,
                'role_code' => $positionCode,
                'responsibility_type' => (string)$source['duty_type'],
                'responsibility_action' => (string)$source['duty_text'],
                'source_activity_code' => (string)$source['activity_code'],
                'source_step_code' => $stepCode,
                'source_responsibility_id' => (string)$source['responsibility_id'],
                'source_refs' => (array)$source['source_refs'],
            ]);
            $requirements[] = $requirement;
        }

        $inputs['requirements'] = $requirements;
        $inputs['role_catalog'] = (array)($baseline['role_catalog'] ?? []);
        $inputs['responsibility_chain_version'] = (array)($baseline['version'] ?? []);

        return $inputs;
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

    private static function nullableString(mixed $value): ?string
    {
        $value = trim((string)($value ?? ''));

        return $value === '' ? null : $value;
    }
}
