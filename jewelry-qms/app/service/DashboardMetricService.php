<?php
declare(strict_types=1);

namespace app\service;

use app\model\AuditFinding;
use app\model\Calibration;
use app\model\Capa;
use app\model\Employee;
use think\facade\Db;

class DashboardMetricService
{
    public static function chartData(): array
    {
        return [
            'capaTrend' => self::capaTrend(),
            'calibrationResults' => self::calibrationResultDistribution(),
            'trainingCoverage' => self::trainingCoverage(),
            'auditFindings' => self::auditFindingDistribution(),
        ];
    }

    public static function capaTrend(int $months = 6): array
    {
        $labels = [];
        $opened = [];
        $closed = [];
        for ($i = $months - 1; $i >= 0; $i--) {
            $start = date('Y-m-01 00:00:00', strtotime("-{$i} months"));
            $end = date('Y-m-t 23:59:59', strtotime($start));
            $labels[] = date('Y-m', strtotime($start));
            $opened[] = Capa::where('soft_delete', 0)->whereBetweenTime('created', $start, $end)->count();
            $closed[] = Capa::where('soft_delete', 0)->where('status', 'closed')->whereBetweenTime('modified', $start, $end)->count();
        }

        return [
            'labels' => $labels,
            'opened' => $opened,
            'closed' => $closed,
        ];
    }

    public static function calibrationResultDistribution(): array
    {
        $labels = ['合格', '不合格', '限用'];
        $map = ['pass', 'fail', 'limited'];
        $values = [];
        foreach ($map as $status) {
            $values[] = Calibration::where('soft_delete', 0)->where('result', $status)->count();
        }

        return [
            'labels' => $labels,
            'values' => $values,
        ];
    }

    public static function trainingCoverage(): array
    {
        $employees = Employee::where('soft_delete', 0)->where('publish', 1)->count();
        $covered = (int)Db::name('training_records')
            ->where('soft_delete', 0)
            ->where('attendance', 'present')
            ->distinct(true)
            ->count('employee_id');
        $covered = min($covered, $employees);

        return [
            'labels' => ['已覆盖', '未覆盖'],
            'values' => [$covered, max(0, $employees - $covered)],
            'rate' => $employees > 0 ? round($covered * 100 / $employees, 1) : 0.0,
        ];
    }

    public static function auditFindingDistribution(): array
    {
        $labels = ['待整改', '整改中', '已验证', '已关闭'];
        $map = ['open', 'correcting', 'verified', 'closed'];
        $values = [];
        foreach ($map as $status) {
            $values[] = AuditFinding::where('soft_delete', 0)->where('status', $status)->count();
        }

        return [
            'labels' => $labels,
            'values' => $values,
        ];
    }
}
