<?php
declare(strict_types=1);

namespace app\service;

use app\model\Approval;
use think\facade\Config;
use think\facade\Session;

class ApprovalService
{
    public static function getApprovalLevels(int $level): int
    {
        $rules = Config::get('qms.approvalRules', []);
        return $rules[$level] ?? 2;
    }

    public static function createWorkflow(
        string $controller,
        string $modelName,
        string $recordId,
        int    $level,
        string $preparedBy,
        ?string $reviewedBy = null,
        ?string $approvedBy = null
    ): void {
        $companyId = Config::get('qms.company_id');
        $userId = Session::get('user.id');
        $levels = self::getApprovalLevels($level);

        if ($levels >= 1 && $preparedBy) {
            Approval::create([
                'id' => qms_uuid(),
                'company_id' => $companyId,
                'model_name' => $modelName,
                'controller_name' => $controller,
                'record' => $recordId,
                'user_id' => $preparedBy,
                'approval_level' => 1,
                'status' => 'approved',
                'approved_on' => date('Y-m-d H:i:s'),
                'created_by' => $userId,
                'publish' => 1,
                'soft_delete' => 0,
                'record_status' => 1,
            ]);
        }

        if ($levels >= 2 && $reviewedBy) {
            Approval::create([
                'id' => qms_uuid(),
                'company_id' => $companyId,
                'model_name' => $modelName,
                'controller_name' => $controller,
                'record' => $recordId,
                'user_id' => $reviewedBy,
                'approval_level' => 2,
                'status' => 'pending',
                'created_by' => $userId,
                'publish' => 1,
                'soft_delete' => 0,
                'record_status' => 1,
            ]);
        }

        if ($levels >= 3 && $approvedBy) {
            Approval::create([
                'id' => qms_uuid(),
                'company_id' => $companyId,
                'model_name' => $modelName,
                'controller_name' => $controller,
                'record' => $recordId,
                'user_id' => $approvedBy,
                'approval_level' => 3,
                'status' => 'pending',
                'created_by' => $userId,
                'publish' => 1,
                'soft_delete' => 0,
                'record_status' => 1,
            ]);
        }
    }

    public static function processApproval(string $approvalId, string $status, string $comments = ''): bool
    {
        $userId = Session::get('user.id');
        $approval = Approval::where('id', $approvalId)->where('user_id', $userId)->find();
        if (!$approval) {
            return false;
        }
        $approval->status = $status;
        $approval->comments = $comments;
        $approval->approved_on = date('Y-m-d H:i:s');
        $approval->save();
        return true;
    }

    public static function isFullyApproved(string $modelName, string $recordId, int $level): bool
    {
        $required = self::getApprovalLevels($level);
        $approved = Approval::where('model_name', $modelName)
            ->where('record', $recordId)
            ->where('status', 'approved')
            ->where('soft_delete', 0)
            ->count();
        return $approved >= $required;
    }
}
