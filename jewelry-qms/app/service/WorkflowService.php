<?php
declare(strict_types=1);

namespace app\service;

use app\model\AuditFinding;
use app\model\Calibration;
use app\model\Capa;
use app\model\CapaSource;
use app\model\CustomerComplaint;
use app\model\Employee;
use app\model\ManagementReview;
use app\model\Nonconformity;
use app\model\ReviewAction;
use app\model\Training;
use app\model\User;
use think\facade\Config;
use think\facade\Session;

class WorkflowService
{
    public static function createCapaFromSource(
        string $sourceType,
        string $sourceRecordId,
        string $description,
        ?string $sourceId = null,
        ?string $assignedTo = null,
        ?string $dueDate = null
    ): Capa {
        $capa = Capa::create([
            'id' => qms_uuid(),
            'company_id' => Config::get('qms.company_id'),
            'capa_number' => qms_next_number('CAPA', Capa::class, 'capa_number'),
            'source_id' => $sourceId,
            'source_type' => $sourceType,
            'source_record_id' => $sourceRecordId,
            'description' => $description,
            'assigned_to' => $assignedTo,
            'due_date' => $dueDate,
            'status' => 'open',
            'publish' => 1,
            'soft_delete' => 0,
            'created_by' => Session::get('user.id'),
        ]);

        self::linkCapaToSource($sourceType, $sourceRecordId, $capa->id);

        if ($assignedTo) {
            NotificationService::notifyUsers(
                '新CAPA任务',
                "您被指派处理 CAPA {$capa->capa_number}",
                'general',
                [$assignedTo],
                'capa',
                'view',
                $capa->id,
                $dueDate
            );
        }

        return $capa;
    }

    public static function linkCapaToSource(string $sourceType, string $sourceRecordId, string $capaId): void
    {
        $source = match ($sourceType) {
            'audit' => AuditFinding::find($sourceRecordId),
            'complaint' => CustomerComplaint::find($sourceRecordId),
            'nc' => Nonconformity::find($sourceRecordId),
            default => null,
        };

        if ($source && $source->hasColumn('capa_id')) {
            $source->save(['capa_id' => $capaId]);
        }
    }

    public static function resolveCapaSourceId(string $sourceType): ?string
    {
        $map = [
            'audit' => '内部审核',
            'complaint' => '客户投诉',
            'nc' => '不符合工作',
            'review' => '管理评审',
            'internal' => '日常监督',
        ];
        $name = $map[$sourceType] ?? null;
        if (!$name) {
            return null;
        }

        return CapaSource::where('name', $name)->value('id');
    }

    public static function advanceCapaStatus(Capa $capa, string $action, array $data = []): bool
    {
        $flow = ['open' => 'analyzing', 'analyzing' => 'implementing', 'implementing' => 'verifying', 'verifying' => 'closed'];
        $current = $capa->status;

        if ($action === 'close' && $current === 'verifying') {
            $capa->status = 'closed';
            $capa->verified_by = $data['verified_by'] ?? Session::get('user.id');
            $capa->verified_date = $data['verified_date'] ?? date('Y-m-d');
            $capa->verification = $data['verification'] ?? $capa->verification;
            if ($capa->hasColumn('effectiveness_review_date')) {
                $reviewDate = trim((string)($data['effectiveness_review_date'] ?? ''));
                if ($reviewDate === '') {
                    $days = (int)Config::get('qms.notification.capa_effectiveness_days', 30);
                    $reviewDate = date('Y-m-d', strtotime('+' . max(1, $days) . ' days'));
                }
                $capa->effectiveness_review_date = $reviewDate;
            }
            if ($capa->hasColumn('effectiveness_result') && isset($data['effectiveness_result'])) {
                $capa->effectiveness_result = trim((string)$data['effectiveness_result']) ?: null;
            }
            $capa->save();

            return true;
        }

        if ($action === 'advance' && isset($flow[$current])) {
            foreach ($data as $key => $value) {
                if ($capa->hasColumn($key)) {
                    $capa->$key = $value;
                }
            }
            $capa->status = $flow[$current];
            $capa->save();

            return true;
        }

        return false;
    }

    public static function buildManagementReviewInputs(): array
    {
        $capaPrototype = new Capa();
        $capaEffectivenessDue = 0;
        if ($capaPrototype->hasColumn('effectiveness_review_date') && $capaPrototype->hasColumn('effectiveness_result')) {
            $capaEffectivenessDue = Capa::where('status', 'closed')
                ->where('soft_delete', 0)
                ->whereNotNull('effectiveness_review_date')
                ->where('effectiveness_review_date', '<=', date('Y-m-d'))
                ->where(function ($query) {
                    $query->whereNull('effectiveness_result')->whereOr('effectiveness_result', '');
                })
                ->count();
        }

        $calibrationTotal = Calibration::where('soft_delete', 0)->count();
        $calibrationPass = Calibration::where('soft_delete', 0)->where('result', 'pass')->count();
        $trainingTotal = Training::where('soft_delete', 0)->count();
        $trainingCompleted = Training::where('soft_delete', 0)->where('status', 'completed')->count();

        return array_merge([
            'capa_total' => Capa::where('soft_delete', 0)->count(),
            'capa_open' => Capa::where('status', 'open')->where('soft_delete', 0)->count(),
            'capa_analyzing' => Capa::where('status', 'analyzing')->where('soft_delete', 0)->count(),
            'capa_implementing' => Capa::where('status', 'implementing')->where('soft_delete', 0)->count(),
            'capa_verifying' => Capa::where('status', 'verifying')->where('soft_delete', 0)->count(),
            'capa_closed' => Capa::where('status', 'closed')->where('soft_delete', 0)->count(),
            'capa_effectiveness_due' => $capaEffectivenessDue,
            'open_capa' => Capa::where('status', '<>', 'closed')->where('soft_delete', 0)->count(),
            'complaints_total' => CustomerComplaint::where('soft_delete', 0)->count(),
            'complaints_open' => CustomerComplaint::where('status', '<>', 'closed')->where('soft_delete', 0)->count(),
            'complaints_closed' => CustomerComplaint::where('status', 'closed')->where('soft_delete', 0)->count(),
            'open_complaints' => CustomerComplaint::where('status', '<>', 'closed')->where('soft_delete', 0)->count(),
            'nonconformities_open' => Nonconformity::where('status', '<>', 'closed')->where('soft_delete', 0)->count(),
            'open_nc' => Nonconformity::where('status', '<>', 'closed')->where('soft_delete', 0)->count(),
            'calibrations_total' => $calibrationTotal,
            'calibrations_pass' => $calibrationPass,
            'calibrations_fail' => Calibration::where('soft_delete', 0)->where('result', 'fail')->count(),
            'calibrations_limited' => Calibration::where('soft_delete', 0)->where('result', 'limited')->count(),
            'calibration_pass_rate' => self::percentage($calibrationPass, $calibrationTotal),
            'trainings_total' => $trainingTotal,
            'trainings_completed' => $trainingCompleted,
            'training_completion_rate' => self::percentage($trainingCompleted, $trainingTotal),
            'audit_findings_total' => AuditFinding::where('soft_delete', 0)->count(),
            'audit_findings_open' => AuditFinding::where('status', 'open')->where('soft_delete', 0)->count(),
            'audit_findings_correcting' => AuditFinding::where('status', 'correcting')->where('soft_delete', 0)->count(),
            'audit_findings_verified' => AuditFinding::where('status', 'verified')->where('soft_delete', 0)->count(),
            'audit_findings_closed' => AuditFinding::where('status', 'closed')->where('soft_delete', 0)->count(),
            'open_findings' => AuditFinding::where('status', '<>', 'closed')->where('soft_delete', 0)->count(),
            'overdue_actions' => ReviewAction::where('status', 'overdue')->where('soft_delete', 0)->count(),
            'pending_reviews' => ManagementReview::where('status', 'planned')->where('soft_delete', 0)->count(),
        ], QmsElementService::managementReviewMetrics());
    }

    public static function formatManagementReviewInputs(array $metrics): string
    {
        return implode("\n", [
            'CAPA状态分布：总数 ' . (int)($metrics['capa_total'] ?? 0)
                . '；待处理 ' . (int)($metrics['capa_open'] ?? 0)
                . '；原因分析 ' . (int)($metrics['capa_analyzing'] ?? 0)
                . '；措施实施 ' . (int)($metrics['capa_implementing'] ?? 0)
                . '；效果验证 ' . (int)($metrics['capa_verifying'] ?? 0)
                . '；已关闭 ' . (int)($metrics['capa_closed'] ?? 0)
                . '；待有效性复查 ' . (int)($metrics['capa_effectiveness_due'] ?? 0),
            '投诉和不符合：投诉总数 ' . (int)($metrics['complaints_total'] ?? 0)
                . '；未关闭投诉 ' . (int)($metrics['complaints_open'] ?? ($metrics['open_complaints'] ?? 0))
                . '；已关闭投诉 ' . (int)($metrics['complaints_closed'] ?? 0)
                . '；未关闭不符合 ' . (int)($metrics['nonconformities_open'] ?? ($metrics['open_nc'] ?? 0)),
            '校准合格率：' . self::formatPercent((float)($metrics['calibration_pass_rate'] ?? 0))
                . '（合格 ' . (int)($metrics['calibrations_pass'] ?? 0)
                . ' / 总数 ' . (int)($metrics['calibrations_total'] ?? 0) . '）',
            '培训完成率：' . self::formatPercent((float)($metrics['training_completion_rate'] ?? 0))
                . '（完成 ' . (int)($metrics['trainings_completed'] ?? 0)
                . ' / 总数 ' . (int)($metrics['trainings_total'] ?? 0) . '）',
            '内审发现统计：总数 ' . (int)($metrics['audit_findings_total'] ?? 0)
                . '；待整改 ' . (int)($metrics['audit_findings_open'] ?? 0)
                . '；整改中 ' . (int)($metrics['audit_findings_correcting'] ?? 0)
                . '；已验证 ' . (int)($metrics['audit_findings_verified'] ?? 0)
                . '；已关闭 ' . (int)($metrics['audit_findings_closed'] ?? 0),
            '管理评审决议：逾期 ' . (int)($metrics['overdue_actions'] ?? 0)
                . '；待完成评审 ' . (int)($metrics['pending_reviews'] ?? 0),
            '体系策划追溯：要素 ' . (int)($metrics['planning_elements_total'] ?? 0)
                . '；完整 ' . (int)($metrics['planning_elements_complete'] ?? 0)
                . '；缺口 ' . (int)($metrics['planning_traceability_gaps'] ?? 0)
                . '；需查新依据 ' . (int)($metrics['planning_sources_due'] ?? 0),
        ]);
    }

    public static function recordCapaEffectiveness(string $capaId, string $result, ?string $reviewDate = null): bool
    {
        $capa = Capa::find($capaId);
        if (!$capa || !$capa->hasColumn('effectiveness_result')) {
            return false;
        }

        if ($capa->hasColumn('effectiveness_review_date')) {
            $capa->effectiveness_review_date = $reviewDate ?: date('Y-m-d');
        }
        $capa->effectiveness_result = trim($result);
        $capa->save();

        return true;
    }

    public static function auditorConflict(string $auditorId, ?string $departmentId): bool
    {
        if (!$auditorId || !$departmentId) {
            return false;
        }
        $user = User::find($auditorId);
        if (!$user || !$user->employee_id) {
            return false;
        }
        $employeeDept = Employee::where('id', $user->employee_id)->value('department_id');

        return $employeeDept && $employeeDept === $departmentId;
    }

    protected static function percentage(int $part, int $total): float
    {
        if ($total <= 0) {
            return 0.0;
        }

        return round(($part / $total) * 100, 1);
    }

    protected static function formatPercent(float $value): string
    {
        $rounded = round($value, 1);
        if (abs($rounded - round($rounded)) < 0.0001) {
            return (string)(int)round($rounded) . '%';
        }

        return rtrim(rtrim(number_format($rounded, 1, '.', ''), '0'), '.') . '%';
    }
}
