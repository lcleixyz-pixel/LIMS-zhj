<?php
declare(strict_types=1);

namespace app\service;

use app\model\AuditFinding;
use app\model\Capa;
use app\model\CapaSource;
use app\model\CustomerComplaint;
use app\model\Employee;
use app\model\ManagementReview;
use app\model\Nonconformity;
use app\model\ReviewAction;
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
        return array_merge([
            'open_capa' => Capa::where('status', '<>', 'closed')->where('soft_delete', 0)->count(),
            'open_complaints' => CustomerComplaint::where('status', '<>', 'closed')->where('soft_delete', 0)->count(),
            'open_nc' => Nonconformity::where('status', '<>', 'closed')->where('soft_delete', 0)->count(),
            'open_findings' => AuditFinding::where('status', '<>', 'closed')->where('soft_delete', 0)->count(),
            'overdue_actions' => ReviewAction::where('status', 'overdue')->where('soft_delete', 0)->count(),
            'pending_reviews' => ManagementReview::where('status', 'planned')->where('soft_delete', 0)->count(),
        ], QmsElementService::managementReviewMetrics());
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
}
