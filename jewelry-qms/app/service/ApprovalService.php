<?php
declare(strict_types=1);

namespace app\service;

use app\model\Approval;
use app\model\Document;
use app\model\User;
use think\facade\Config;
use think\facade\Db;
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
        ?string $approvedBy = null,
        ?int $workflowRound = null
    ): int {
        $companyId = Config::get('qms.company_id');
        $userId = Session::get('user.id');
        $levels = self::getApprovalLevels($level);
        $workflowRound = $workflowRound ?? (self::currentWorkflowRound($modelName, $recordId) + 1);

        if ($levels >= 1 && $preparedBy) {
            Approval::create([
                'id' => qms_uuid(),
                'company_id' => $companyId,
                'model_name' => $modelName,
                'controller_name' => $controller,
                'record' => $recordId,
                'user_id' => $preparedBy,
                'approval_level' => 1,
                'workflow_round' => $workflowRound,
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
                'workflow_round' => $workflowRound,
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
                'workflow_round' => $workflowRound,
                'status' => 'pending',
                'created_by' => $userId,
                'publish' => 1,
                'soft_delete' => 0,
                'record_status' => 1,
            ]);
        }

        return $workflowRound;
    }

    public static function processApproval(string $approvalId, string $status, string $comments = '', ?string $actingUserId = null): bool
    {
        $userId = $actingUserId !== null && $actingUserId !== ''
            ? $actingUserId
            : (string)Session::get('user.id');
        $approval = Approval::where('id', $approvalId)
            ->where('user_id', $userId)
            ->where('record_status', 1)
            ->find();
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
            ->where('record_status', 1)
            ->where('soft_delete', 0)
            ->count();
        return $approved >= $required;
    }

    public static function currentWorkflowRound(string $modelName, string $recordId): int
    {
        return (int)Approval::where('model_name', $modelName)
            ->where('record', $recordId)
            ->where('soft_delete', 0)
            ->max('workflow_round');
    }

    public static function hasActiveDocumentWorkflow(string $recordId): bool
    {
        return Approval::where('model_name', 'Document')
            ->where('record', $recordId)
            ->where('record_status', 1)
            ->where('soft_delete', 0)
            ->count() > 0;
    }

    /**
     * 结束当前文件审批轮次。被明确驳回的审批保留 rejected，其他未处理项仅退出当前轮次。
     */
    public static function closeCurrentDocumentWorkflow(
        string $recordId,
        ?string $rejectedApprovalId = null,
        string $comments = ''
    ): int {
        return Db::transaction(function () use ($recordId, $rejectedApprovalId, $comments): int {
            if ($rejectedApprovalId !== null && $rejectedApprovalId !== '') {
                Approval::where('id', $rejectedApprovalId)
                    ->where('model_name', 'Document')
                    ->where('record', $recordId)
                    ->where('record_status', 1)
                    ->update([
                        'status' => 'rejected',
                        'comments' => $comments,
                        'approved_on' => date('Y-m-d H:i:s'),
                        'modified' => date('Y-m-d H:i:s'),
                    ]);
            }

            return Approval::where('model_name', 'Document')
                ->where('record', $recordId)
                ->where('record_status', 1)
                ->where('soft_delete', 0)
                ->update([
                    'record_status' => 0,
                    'modified' => date('Y-m-d H:i:s'),
                ]);
        });
    }

    /**
     * 驳回后重新建立完整审批轮次，旧轮次保留但不再参与完成判断。
     */
    public static function restartDocumentWorkflow(Document $doc, string $preparedUserId): int
    {
        $reviewedBy = self::userIdForEmployee((string)$doc->reviewed_by);
        $approvedBy = self::userIdForEmployee((string)$doc->approved_by);
        $nextRound = self::currentWorkflowRound('Document', (string)$doc->id) + 1;

        self::closeCurrentDocumentWorkflow((string)$doc->id);

        return self::createWorkflow(
            'document',
            'Document',
            (string)$doc->id,
            (int)$doc->level,
            $preparedUserId,
            $reviewedBy,
            $approvedBy,
            $nextRound
        );
    }

    /**
     * 文件详情页使用的岗位化签批状态，不暴露外部签署工具术语。
     *
     * @return array{
     *   round:int,
     *   stage:string,
     *   stage_label:string,
     *   reviewed:bool,
     *   approved:bool,
     *   rejected:bool,
     *   reject_reason:string,
     *   current_user_level:int
     * }
     */
    public static function documentWorkflowStatus(Document $doc, string $currentUserId = ''): array
    {
        $recordId = (string)$doc->id;
        $round = self::currentWorkflowRound('Document', $recordId);
        $active = Approval::where('model_name', 'Document')
            ->where('record', $recordId)
            ->where('workflow_round', $round)
            ->where('record_status', 1)
            ->where('soft_delete', 0)
            ->order('approval_level', 'asc')
            ->select();

        $reviewed = false;
        $approved = false;
        $currentUserLevel = 0;
        foreach ($active as $row) {
            $level = (int)$row->approval_level;
            if ($level === 2 && (string)$row->status === 'approved') {
                $reviewed = true;
            }
            if ($level === 3 && (string)$row->status === 'approved') {
                $approved = true;
            }
            if ($currentUserId !== '' && (string)$row->user_id === $currentUserId) {
                $currentUserLevel = $level;
            }
        }

        $lastRejected = Approval::where('model_name', 'Document')
            ->where('record', $recordId)
            ->where('status', 'rejected')
            ->where('soft_delete', 0)
            ->order('workflow_round', 'desc')
            ->order('approved_on', 'desc')
            ->find();
        $rejected = (string)$doc->status === 'draft'
            && $lastRejected !== null
            && (int)$lastRejected->workflow_round === $round;
        $rejectReason = $rejected ? trim((string)$lastRejected->comments) : '';

        if (in_array((string)$doc->status, ['trial_ready', 'published'], true)) {
            $stage = 'completed';
            $stageLabel = '签批已完成';
        } elseif ($rejected) {
            $stage = 'rejected';
            $stageLabel = '已驳回，等待修改后重新提交';
        } elseif ((string)$doc->status === 'draft') {
            $stage = 'draft';
            $stageLabel = '草稿，等待提交';
        } elseif ($reviewed) {
            $stage = 'pending_approval';
            $stageLabel = '已审核，等待批准';
        } else {
            $stage = 'pending_review';
            $stageLabel = '已提交，等待审核';
        }

        return [
            'round' => $round,
            'stage' => $stage,
            'stage_label' => $stageLabel,
            'reviewed' => $reviewed,
            'approved' => $approved,
            'rejected' => $rejected,
            'reject_reason' => $rejectReason,
            'current_user_level' => $currentUserLevel,
        ];
    }

    private static function userIdForEmployee(string $employeeId): ?string
    {
        if ($employeeId === '') {
            return null;
        }

        $user = User::where('employee_id', $employeeId)
            ->where('publish', 1)
            ->where('soft_delete', 0)
            ->find();

        return $user ? (string)$user->id : null;
    }
}
