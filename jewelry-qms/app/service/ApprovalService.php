<?php
declare(strict_types=1);

namespace app\service;

use app\model\Approval;
use think\facade\Config;
use think\facade\Session;

class ApprovalService
{
    private const NON_APPROVAL_POSITIONS = [
        'document_controller',
        'authorized_signatory',
        'system_administrator',
    ];

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

    public static function processApproval(string $approvalId, string $status, string $comments = '', ?string $actingUserId = null): bool
    {
        $userId = $actingUserId !== null && $actingUserId !== ''
            ? $actingUserId
            : (string)Session::get('user.id');
        $approval = Approval::where('id', $approvalId)->where('user_id', $userId)->find();
        if (!$approval) {
            return false;
        }
        // Webhook/代签路径已验签签署人身份，跳过岗位任命矩阵；会话路径仍要求有效岗位。
        if ((string)$approval->model_name === 'Document' && ($actingUserId === null || $actingUserId === '')) {
            $employeeId = trim((string)Session::get('user.employee_id', ''));
            $positions = ActionAuthorizationService::activePositionCodes($employeeId);
            if (array_intersect($positions, self::authorizedApprovalPositions((int)$approval->approval_level)) === []) {
                return false;
            }
        }
        if (!in_array($status, ['approved', 'rejected'], true)) {
            return false;
        }
        $approval->status = $status;
        $approval->comments = $comments;
        $approval->approved_on = date('Y-m-d H:i:s');
        $approval->save();
        return true;
    }

    /**
     * 与 Approval 控制器一致：全票通过后写 published/trial_ready，并处理旧版 obsolete。
     * 不经 Document::edit 直写，供 webhook 复用。
     */
    public static function finalizeDocumentIfFullyApproved(\app\model\Document $doc): bool
    {
        if (!self::isFullyApproved('Document', (string)$doc->id, (int)$doc->level)) {
            return false;
        }

        $isTrialDocument = TrialModeService::isSimulationNumber((string)$doc->doc_number);
        $targetStatus = $isTrialDocument ? 'trial_ready' : 'published';
        $update = [
            'status' => $targetStatus,
            'publish' => 1,
        ];
        if (!$isTrialDocument) {
            $update['effective_date'] = $doc->effective_date ?: date('Y-m-d');
        }
        $doc->save($update);
        $supersededId = trim((string)$doc->supersedes_document_id);
        if ($supersededId !== '') {
            $superseded = \app\model\Document::find($supersededId);
            $maySupersede = $superseded && (
                (!$isTrialDocument && (string)$superseded->status === 'published')
                || ($isTrialDocument
                    && TrialModeService::isSimulationNumber((string)$superseded->doc_number)
                    && (string)$superseded->status === 'trial_ready')
            );
            if ($maySupersede) {
                $superseded->save([
                    'status' => 'obsolete',
                    'publish' => 0,
                ]);
            }
        }

        return true;
    }

    /**
     * 文件管理员、授权签字人和系统管理员身份本身不在批准岗位中。
     *
     * @return list<string>
     */
    private static function authorizedApprovalPositions(int $approvalLevel): array
    {
        $allowed = $approvalLevel >= 3
            ? ['quality_manager', 'top_management', 'company_general_manager']
            : ['quality_manager', 'technical_manager'];

        return array_values(array_diff($allowed, self::NON_APPROVAL_POSITIONS));
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
