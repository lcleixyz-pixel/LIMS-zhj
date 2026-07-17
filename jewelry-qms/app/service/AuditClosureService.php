<?php
declare(strict_types=1);

namespace app\service;

use DomainException;
use think\facade\Config;
use think\facade\Db;

final class AuditClosureService
{
    /**
     * @return list<string>
     */
    public static function scheduleBlockingReasons(string $scheduleId): array
    {
        if (!self::scheduleBelongsToCompany($scheduleId)) {
            return ['审核日程不存在或不属于当前机构'];
        }
        $reasons = self::checklistBlockingReasons($scheduleId);

        $findings = Db::name('audit_findings')
            ->where('audit_schedule_id', $scheduleId)
            ->where('soft_delete', 0)
            ->select()
            ->toArray();
        foreach ($findings as $finding) {
            if ((string)$finding['status'] !== 'closed') {
                $reasons[] = '仍有审核发现未关闭';
                break;
            }
        }

        $reasons = array_merge($reasons, self::findingCapaBlockingReasons($findings));

        return array_values(array_unique($reasons));
    }

    /**
     * @return list<string>
     */
    public static function blockingReasons(string $planId): array
    {
        if (Db::name('audit_plans')
            ->where('id', $planId)
            ->where('company_id', (string)Config::get('qms.company_id'))
            ->where('soft_delete', 0)
            ->count() === 0
        ) {
            return ['审核计划不存在或不属于当前机构'];
        }
        $schedules = Db::name('audit_schedules')
            ->where('audit_plan_id', $planId)
            ->where('soft_delete', 0)
            ->select()
            ->toArray();
        if ($schedules === []) {
            return ['尚未建立审核日程'];
        }

        $reasons = [];
        $scheduleIds = array_map(static fn (array $row): string => (string)$row['id'], $schedules);
        foreach ($schedules as $schedule) {
            if ((string)$schedule['status'] !== 'completed') {
                $reasons[] = '仍有未完成的审核日程';
                break;
            }
        }

        foreach ($scheduleIds as $scheduleId) {
            $reasons = array_merge($reasons, self::checklistBlockingReasons($scheduleId));
        }

        $findings = Db::name('audit_findings')
            ->whereIn('audit_schedule_id', $scheduleIds)
            ->where('soft_delete', 0)
            ->select()
            ->toArray();
        foreach ($findings as $finding) {
            if ((string)$finding['status'] !== 'closed') {
                $reasons[] = '仍有审核发现未关闭';
                break;
            }
        }

        $reasons = array_merge($reasons, self::findingCapaBlockingReasons($findings));

        return array_values(array_unique($reasons));
    }

    public static function assertScheduleWritable(string $scheduleId): void
    {
        $status = (string)Db::name('audit_schedules')->alias('s')
            ->join('audit_plans p', 'p.id = s.audit_plan_id')
            ->where('s.id', trim($scheduleId))
            ->where('p.company_id', (string)Config::get('qms.company_id'))
            ->where('s.soft_delete', 0)
            ->where('p.soft_delete', 0)
            ->value('s.status');
        if ($status === '') {
            throw new DomainException('审核日程不存在');
        }
        if ($status === 'completed') {
            throw new DomainException('已完成审核日程的检查记录和发现已锁定，不得增删改');
        }
    }

    /**
     * @return list<string>
     */
    private static function checklistBlockingReasons(string $scheduleId): array
    {
        $checklists = Db::name('audit_checklists')
            ->where('audit_schedule_id', $scheduleId)
            ->where('soft_delete', 0)
            ->select()
            ->toArray();
        if ($checklists === []) {
            return ['尚未形成审核检查记录'];
        }

        $reasons = [];
        $hasNonconform = false;
        foreach ($checklists as $checklist) {
            if (trim((string)($checklist['check_item'] ?? '')) === '') {
                $reasons[] = '审核检查项未填写';
            }
            $result = trim((string)($checklist['result'] ?? ''));
            if ($result === '') {
                $reasons[] = '检查结果未填写';
            }
            if (trim((string)($checklist['evidence'] ?? '')) === '') {
                $reasons[] = '客观证据未填写';
            }
            $hasNonconform = $hasNonconform || $result === 'nonconform';
        }
        if ($hasNonconform && Db::name('audit_findings')
            ->where('audit_schedule_id', $scheduleId)
            ->where('soft_delete', 0)
            ->count() === 0
        ) {
            $reasons[] = '不符合检查结果未登记审核发现';
        }

        return array_values(array_unique($reasons));
    }

    private static function scheduleBelongsToCompany(string $scheduleId): bool
    {
        return Db::name('audit_schedules')->alias('s')
            ->join('audit_plans p', 'p.id = s.audit_plan_id')
            ->where('s.id', trim($scheduleId))
            ->where('p.company_id', (string)Config::get('qms.company_id'))
            ->where('s.soft_delete', 0)
            ->where('p.soft_delete', 0)
            ->count() > 0;
    }

    /**
     * @param list<array<string,mixed>> $findings
     * @return list<string>
     */
    private static function findingCapaBlockingReasons(array $findings): array
    {
        $reasons = [];
        foreach ($findings as $finding) {
            if ((string)($finding['finding_type'] ?? '') === 'observation') {
                continue;
            }

            $findingId = (string)($finding['id'] ?? '');
            $capaId = trim((string)($finding['capa_id'] ?? ''));
            if ($capaId === '') {
                $reasons[] = '非观察项审核发现未关联 CAPA';
                continue;
            }

            $capa = Db::name('capas')
                ->where('id', $capaId)
                ->where('source_type', 'audit')
                ->where('source_record_id', $findingId)
                ->where('soft_delete', 0)
                ->find();
            if (!$capa) {
                $reasons[] = '审核发现与 CAPA 双向关联不一致';
                continue;
            }
            if ((string)($capa['status'] ?? '') !== 'closed') {
                $reasons[] = '仍有关联 CAPA 未关闭';
                continue;
            }
            if (
                trim((string)($capa['verification'] ?? '')) === ''
                || trim((string)($capa['verified_by'] ?? '')) === ''
                || trim((string)($capa['verified_date'] ?? '')) === ''
            ) {
                $reasons[] = 'CAPA 验证记录不完整';
            }
            if (trim((string)($capa['effectiveness_result'] ?? '')) === '') {
                $reasons[] = 'CAPA 有效性评价不完整';
            }
        }

        return array_values(array_unique($reasons));
    }
}
