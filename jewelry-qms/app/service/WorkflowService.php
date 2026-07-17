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
use think\db\exception\DuplicateException;
use think\facade\Config;
use think\facade\Db;
use think\facade\Log;
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
        try {
            [$capa, $created] = Db::transaction(function () use (
                $sourceType,
                $sourceRecordId,
                $description,
                $sourceId,
                $assignedTo,
                $dueDate
            ): array {
                $source = self::resolveCapaSourceRecord($sourceType, $sourceRecordId);
                $existing = self::findCapaBySource($sourceType, $sourceRecordId, true);
                if ($existing) {
                    self::assertSourceLinkConsistent($source, (string)$existing->id);
                    return [$existing, false];
                }

                $capaNumber = qms_next_number('CAPA', Capa::class, 'capa_number');
                if (
                    TrialModeService::isEnabled()
                    && $sourceType === 'audit'
                    && $source instanceof AuditFinding
                    && str_starts_with(strtoupper((string)$source->finding_number), 'SIM-')
                ) {
                    $capaNumber = TrialModeService::simulationNumber($capaNumber);
                }
                $capa = Capa::create([
                    'id' => qms_uuid(),
                    'company_id' => Config::get('qms.company_id'),
                    'capa_number' => $capaNumber,
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

                self::linkResolvedCapaSource($source, (string)$capa->id);
                return [$capa, true];
            });
        } catch (DuplicateException $exception) {
            // 并发点击可能同时通过“尚未创建”检查；唯一约束胜出后返回已落库的同一来源 CAPA。
            $capa = Db::transaction(function () use ($sourceType, $sourceRecordId, $exception): Capa {
                $source = self::resolveCapaSourceRecord($sourceType, $sourceRecordId);
                $existing = self::findCapaBySource($sourceType, $sourceRecordId, true);
                if (!$existing) {
                    throw $exception;
                }
                self::assertSourceLinkConsistent($source, (string)$existing->id);
                return $existing;
            });
            $created = false;
        }

        if ($sourceType === 'audit' && $created) {
            AuditFinding::where('id', $sourceRecordId)
                ->where('soft_delete', 0)
                ->update(['status' => 'correcting']);
        }

        if ($created && $assignedTo) {
            try {
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
            } catch (\Throwable $exception) {
                Log::error('CAPA 已创建，但通知发送失败', [
                    'capa_id' => (string)$capa->id,
                    'exception' => $exception::class,
                ]);
            }
        }

        return $capa;
    }

    private static function findCapaBySource(
        string $sourceType,
        string $sourceRecordId,
        bool $lock = false
    ): ?Capa {
        $query = Capa::where('company_id', Config::get('qms.company_id'))
            ->where('source_type', $sourceType)
            ->where('source_record_id', $sourceRecordId)
            ->where('soft_delete', 0);
        if ($lock) {
            $query->lock(true);
        }

        return $query->find();
    }

    public static function linkCapaToSource(string $sourceType, string $sourceRecordId, string $capaId): void
    {
        $source = self::resolveCapaSourceRecord($sourceType, $sourceRecordId);
        self::linkResolvedCapaSource($source, $capaId);
    }

    private static function resolveCapaSourceRecord(string $sourceType, string $sourceRecordId): ?object
    {
        $modelClass = match ($sourceType) {
            'audit' => AuditFinding::class,
            'complaint' => CustomerComplaint::class,
            'nc' => Nonconformity::class,
            'review' => ReviewAction::class,
            'internal' => null,
            default => throw new \InvalidArgumentException('不支持的 CAPA 来源类型'),
        };
        if ($modelClass === null) {
            if (trim($sourceRecordId) === '') {
                throw new \InvalidArgumentException('CAPA 来源记录不能为空');
            }
            return null;
        }
        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $sourceRecordId) !== 1) {
            throw new \InvalidArgumentException('CAPA 来源记录标识格式不正确');
        }
        $source = $modelClass::where('id', $sourceRecordId)->where('soft_delete', 0)->find();
        if (!$source) {
            throw new \RuntimeException('CAPA 来源记录不存在或已停用');
        }

        return $source;
    }

    private static function assertSourceLinkConsistent(?object $source, string $capaId): void
    {
        if (!$source || !$source->hasColumn('capa_id')) {
            return;
        }
        $linkedId = trim((string)$source->getAttr('capa_id'));
        if ($linkedId !== '' && $linkedId !== $capaId) {
            throw new \RuntimeException('来源记录已关联其他 CAPA');
        }
        if ($linkedId === '') {
            self::linkResolvedCapaSource($source, $capaId);
        }
    }

    private static function linkResolvedCapaSource(?object $source, string $capaId): void
    {
        if ($source && $source->hasColumn('capa_id')) {
            $source->save(['capa_id' => $capaId]);
        }
    }

    public static function capaSourceContext(Capa $capa): array
    {
        $sourceId = trim((string)$capa->source_record_id);
        $type = (string)$capa->source_type;
        $source = match ($type) {
            'audit' => AuditFinding::find($sourceId),
            'complaint' => CustomerComplaint::find($sourceId),
            'nc' => Nonconformity::find($sourceId),
            'review' => ReviewAction::find($sourceId),
            default => null,
        };

        return match ($type) {
            'audit' => [
                'type_label' => '内部审核',
                'record_label' => $source ? (string)$source->finding_number : '来源记录不可用',
                'url' => $source ? '/audit_finding/view?id=' . $sourceId : null,
            ],
            'complaint' => [
                'type_label' => '客户投诉',
                'record_label' => $source ? (string)$source->complaint_number : '来源记录不可用',
                'url' => $source ? '/complaint/view?id=' . $sourceId : null,
            ],
            'nc' => [
                'type_label' => '不符合工作',
                'record_label' => $source ? (string)$source->nc_number : '来源记录不可用',
                'url' => $source ? '/nonconformity/view?id=' . $sourceId : null,
            ],
            'review' => [
                'type_label' => '管理评审',
                'record_label' => $source ? mb_strimwidth((string)$source->action_item, 0, 48, '…') : '来源记录不可用',
                'url' => $source ? '/management_review/view?id=' . $source->management_review_id : null,
            ],
            'internal' => [
                'type_label' => '日常监督',
                'record_label' => $sourceId !== '' ? $sourceId : '-',
                'url' => null,
            ],
            default => [
                'type_label' => $type !== '' ? $type : '未标明',
                'record_label' => '来源类型不可识别',
                'url' => null,
            ],
        };
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
            if ((string)$capa->source_type === 'audit') {
                AuditFinding::where('id', (string)$capa->source_record_id)
                    ->where('soft_delete', 0)
                    ->update(['status' => 'closed']);
            }

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
        $calibrationPass = Calibration::where('soft_delete', 0)->whereIn('result', [
            'pass', 'qualified', 'conform', '合格', '通过',
        ])->count();
        $calibrationFail = Calibration::where('soft_delete', 0)->whereIn('result', [
            'fail', 'failed', 'unqualified', 'nonconform', '不合格', '失败',
        ])->count();
        $calibrationLimited = Calibration::where('soft_delete', 0)->whereIn('result', [
            'limited', 'restricted', '限用',
        ])->count();
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
            'calibrations_fail' => $calibrationFail,
            'calibrations_limited' => $calibrationLimited,
            'calibration_pass_rate' => self::percentage($calibrationPass, $calibrationTotal),
            'calibration_status_label' => $calibrationTotal > 0 ? '已形成' : '未形成/待补充',
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
            '校准合格率：' . ((int)($metrics['calibrations_total'] ?? 0) > 0
                ? self::formatPercent((float)($metrics['calibration_pass_rate'] ?? 0))
                    . '（合格/通过 ' . (int)($metrics['calibrations_pass'] ?? 0)
                    . ' / 总数 ' . (int)($metrics['calibrations_total'] ?? 0) . '）'
                : '未形成/待补充'),
            '培训完成率：' . ((int)($metrics['trainings_total'] ?? 0) > 0
                ? self::formatPercent((float)($metrics['training_completion_rate'] ?? 0))
                    . '（完成 ' . (int)($metrics['trainings_completed'] ?? 0)
                    . ' / 总数 ' . (int)($metrics['trainings_total'] ?? 0) . '）'
                : '未形成/待补充'),
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
