<?php
declare(strict_types=1);

namespace app\service;

use think\facade\Db;

final class AuditClosureService
{
    /**
     * @return list<string>
     */
    public static function blockingReasons(string $planId): array
    {
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

        $checklistScheduleIds = array_map(
            'strval',
            Db::name('audit_checklists')
                ->whereIn('audit_schedule_id', $scheduleIds)
                ->where('soft_delete', 0)
                ->distinct(true)
                ->column('audit_schedule_id')
        );
        if (array_diff($scheduleIds, $checklistScheduleIds) !== []) {
            $reasons[] = '仍有日程未形成检查记录';
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

        $findingIds = array_map(static fn (array $row): string => (string)$row['id'], $findings);
        if ($findingIds !== []) {
            $openCapas = Db::name('capas')
                ->where('source_type', 'audit')
                ->whereIn('source_record_id', $findingIds)
                ->where('soft_delete', 0)
                ->where('status', '<>', 'closed')
                ->count();
            if ($openCapas > 0) {
                $reasons[] = '仍有关联 CAPA 未关闭';
            }
        }

        return array_values(array_unique($reasons));
    }
}
