<?php
declare(strict_types=1);

namespace app\controller;

use app\BaseController;
use app\service\QmsManualProcedureAlignmentService;
use app\service\QmsManualProcedureTraceService;
use app\service\QmsResponsibilityApprovalService;
use app\service\QmsResponsibilityAlignmentService;
use app\service\QmsResponsibilityCatalogService;
use app\service\QmsResponsibilityDraftService;
use app\service\QmsResponsibilityValidationService;
use DomainException;
use Throwable;
use think\facade\Config;
use think\facade\Db;
use think\facade\Log;
use think\facade\Session;
use think\facade\View;

final class PlanningResponsibility extends BaseController
{
    private const MANAGER_ROLES = ['admin', 'quality_manager'];
    private const VIEWS = ['structure', 'staffing', 'approval', 'effective', 'alignment'];

    public function index()
    {
        return $this->render((string)$this->request->get('view', 'structure'));
    }

    public function createInitialDraft()
    {
        if (!$this->request->isPost()) {
            return redirect('/planning/responsibilities');
        }
        try {
            $this->assertManagerWrite();
            $sourceVersionId = trim((string)$this->request->post('source_version_id', ''));
            $version = $sourceVersionId === ''
                ? QmsResponsibilityCatalogService::createInitialDraft()
                : QmsResponsibilityDraftService::cloneEffectiveVersion($sourceVersionId);
            $this->markAudit('success', (string)$version['id'], 'version_id');
            Session::flash('success', '责任链草案已建立，人员未绑定时仍可进行结构管理。');

            return $this->responsibilityRedirect((string)$version['id'], 'structure');
        } catch (Throwable $e) {
            return $this->failure($e, 'structure');
        }
    }

    public function saveAssignment()
    {
        if (!$this->request->isPost()) {
            return redirect('/planning/responsibilities?view=staffing');
        }
        if ((string)$this->request->post('operation', 'save') === 'remove') {
            return $this->removeAssignment();
        }

        $versionId = trim((string)$this->request->post('version_id', ''));
        try {
            $this->assertManagerWrite();
            $competencyId = trim((string)$this->request->post('competency_record_id', ''));
            $certificateId = trim((string)$this->request->post('certificate_id', ''));
            $assignment = QmsResponsibilityDraftService::saveAssignment(
                trim((string)$this->request->post('responsibility_id', '')),
                trim((string)$this->request->post('employee_id', '')),
                $this->nullablePost('site_id'),
                trim((string)$this->request->post('proposed_from', date('Y-m-d'))),
                $this->nullablePost('proposed_until'),
                [
                    'competency_record_ids' => $competencyId === '' ? [] : [$competencyId],
                    'certificate_ids' => $certificateId === '' ? [] : [$certificateId],
                ]
            );
            $this->markAudit('success', (string)$assignment['id'], 'assignment_id');
            Session::flash('success', '人员配置已保存为草案，尚未形成正式任命。');

            return $this->responsibilityRedirect($versionId, 'staffing');
        } catch (Throwable $e) {
            return $this->failure($e, 'staffing', $versionId);
        }
    }

    public function removeAssignment()
    {
        if (!$this->request->isPost()) {
            return redirect('/planning/responsibilities?view=staffing');
        }
        $versionId = trim((string)$this->request->post('version_id', ''));
        $assignmentId = trim((string)$this->request->post('assignment_id', ''));
        try {
            $this->assertManagerWrite();
            QmsResponsibilityDraftService::removeAssignment($assignmentId);
            $this->markAudit('success', $assignmentId, 'assignment_id');
            Session::flash('success', '人员草案绑定已移除。');

            return $this->responsibilityRedirect($versionId, 'staffing');
        } catch (Throwable $e) {
            return $this->failure($e, 'staffing', $versionId);
        }
    }

    public function validateVersion()
    {
        if (!$this->request->isPost()) {
            return redirect('/planning/responsibilities?view=approval');
        }
        $versionId = trim((string)$this->request->post('version_id', ''));
        try {
            $this->assertManagerWrite();
            $mode = (string)$this->request->post('mode', 'structure');
            $result = QmsResponsibilityValidationService::validateVersion($versionId, $mode);
            $this->markAudit('success', $versionId, 'version_id');
            Session::flash('responsibility_validation', $result);
            Session::flash(
                ($result['result'] ?? '') === 'pass' ? 'success' : 'warning',
                '责任链校验完成：' . ($result['result'] ?? 'unknown') . '，发现 ' . count($result['issues'] ?? []) . ' 项。'
            );

            return $this->responsibilityRedirect($versionId, 'approval');
        } catch (Throwable $e) {
            return $this->failure($e, 'approval', $versionId);
        }
    }

    public function submitVersion()
    {
        if (!$this->request->isPost()) {
            return redirect('/planning/responsibilities?view=approval');
        }
        $versionId = trim((string)$this->request->post('version_id', ''));
        try {
            $this->assertManagerWrite();
            QmsResponsibilityApprovalService::submitVersion($versionId);
            $this->markAudit('success', $versionId, 'version_id');
            Session::flash('success', '责任链版本已提交，内容哈希已锁定并按业务身份流转签批。');

            return $this->responsibilityRedirect($versionId, 'approval');
        } catch (Throwable $e) {
            return $this->failure($e, 'approval', $versionId);
        }
    }

    public function registerGeneralManager()
    {
        if (!$this->request->isPost()) {
            return redirect('/planning/responsibilities?view=approval');
        }
        try {
            $this->assertAdmin();
            $appointment = QmsResponsibilityApprovalService::registerCorporateIdentity([
                'position_code' => 'company_general_manager',
                'employee_id' => trim((string)$this->request->post('employee_id', '')),
                'source_document_number' => trim((string)$this->request->post('source_document_number', '')),
                'source_excerpt' => trim((string)$this->request->post('source_excerpt', '')),
                'appointed_at' => trim((string)$this->request->post('appointed_at', date('Y-m-d'))),
            ]);
            $this->markAudit('success', (string)$appointment['id'], 'appointment_id');
            Session::flash('success', '公司总经理既有治理身份及来源证据已登记；该动作不表示由管理员任命。');

            return $this->responsibilityRedirect('', 'approval');
        } catch (Throwable $e) {
            return $this->failure($e, 'approval');
        }
    }

    public function requestLabDirector()
    {
        if (!$this->request->isPost()) {
            return redirect('/planning/responsibilities?view=approval');
        }
        try {
            $this->assertAdmin();
            $approval = QmsResponsibilityApprovalService::requestLabDirectorAppointment(
                trim((string)$this->request->post('employee_id', '')),
                trim((string)$this->request->post('effective_from', date('Y-m-d')))
            );
            $this->markAudit('success', (string)$approval['id'], 'approval_id');
            Session::flash('success', '实验室主任任命申请已流转给公司总经理签批。');

            return $this->responsibilityRedirect('', 'approval');
        } catch (Throwable $e) {
            return $this->failure($e, 'approval');
        }
    }

    public function approve()
    {
        if (!$this->request->isPost()) {
            return redirect('/planning/responsibilities?view=approval');
        }
        $versionId = trim((string)$this->request->post('version_id', ''));
        try {
            $scope = (string)$this->request->post('approval_scope', 'assignment');
            $decision = (string)$this->request->post('decision', '');
            $comments = trim((string)$this->request->post('comments', ''));
            if ($scope === 'governance_bootstrap') {
                $approvalId = trim((string)$this->request->post('approval_id', ''));
                QmsResponsibilityApprovalService::approveBootstrap(
                    $approvalId,
                    $decision,
                    $comments
                );
                $this->markAudit('success', $approvalId, 'approval_id');
            } else {
                $result = QmsResponsibilityApprovalService::approveBatch(
                    trim((string)$this->request->post('batch_key', '')),
                    $decision,
                    $comments
                );
                $approvedVersionId = trim((string)($result['version_id'] ?? $versionId));
                $this->markAudit('success', $approvedVersionId, 'version_id');
            }
            Session::flash('success', '签批决定已记录；业务身份、禁止自批和版本哈希由签批服务实时复核。');

            return $this->responsibilityRedirect($versionId, 'approval');
        } catch (Throwable $e) {
            return $this->failure($e, 'approval', $versionId);
        }
    }

    public function alignment()
    {
        return $this->render('alignment');
    }

    private function render(string $viewMode)
    {
        $viewMode = in_array($viewMode, self::VIEWS, true) ? $viewMode : 'structure';
        $companyId = (string)Config::get('qms.company_id');
        $versions = Db::name('qms_responsibility_chain_versions')
            ->where('company_id', $companyId)->where('soft_delete', 0)
            ->order('version_no', 'desc')->select()->toArray();
        $versionId = trim((string)$this->request->get('version_id', ''));
        if ($versionId === '' && $versions !== []) {
            $versionId = (string)$versions[0]['id'];
        }
        $detail = null;
        if ($versionId !== '') {
            try {
                $detail = QmsResponsibilityDraftService::versionDetail($versionId);
            } catch (Throwable $e) {
                $this->flashThrowable($e, '读取责任链版本失败');
                $versionId = '';
            }
        }

        $employeeId = trim((string)Session::get('user.employee_id', ''));
        $pendingBatch = null;
        if ($detail && (string)$detail['status'] === 'pending_approval' && $employeeId !== '') {
            try {
                $pendingBatch = QmsResponsibilityApprovalService::pendingBatchForApprover($versionId, $employeeId);
            } catch (Throwable $e) {
                if (!$e instanceof DomainException) {
                    Log::error('读取责任链待签批批次失败', ['exception' => $e]);
                }
                $pendingBatch = null;
            }
        }
        $alignmentData = $viewMode === 'alignment'
            ? $this->alignmentData($detail, $versionId)
            : ['state' => 'not_requested', 'findings' => [], 'message' => '', 'version' => []];

        View::assign([
            'pageTitle' => '活动级责任链',
            'viewMode' => $viewMode,
            'versions' => $versions,
            'versionId' => $versionId,
            'detail' => $detail,
            'managerCanEdit' => in_array((string)Session::get('user.role', 'staff'), self::MANAGER_ROLES, true),
            'isAdmin' => (string)Session::get('user.role', 'staff') === 'admin',
            'employees' => Db::name('employees')->where('company_id', $companyId)->where('publish', 1)->where('soft_delete', 0)->order('name')->select()->toArray(),
            'sites' => Db::name('sites')->where('company_id', $companyId)->where('status', 'active')->where('publish', 1)->where('soft_delete', 0)->order('sort_order')->select()->toArray(),
            'competencyEvidence' => Db::name('competency_records')->alias('c')->leftJoin('employees e', 'e.id=c.employee_id')->where('c.company_id', $companyId)->where('c.publish', 1)->where('c.soft_delete', 0)->field('c.id,c.employee_id,c.test_item,c.assessment_date,c.result,e.name employee_name')->order('e.name,c.assessment_date', 'desc')->select()->toArray(),
            'certificateEvidence' => Db::name('employee_certificates')->alias('c')->leftJoin('employees e', 'e.id=c.employee_id')->where('c.company_id', $companyId)->where('c.publish', 1)->where('c.soft_delete', 0)->field('c.id,c.employee_id,c.certificate_type,c.certificate_number,c.status,e.name employee_name')->order('e.name,c.certificate_type')->select()->toArray(),
            'pendingBatch' => $pendingBatch,
            'bootstrapApprovals' => $this->bootstrapApprovals($companyId, $employeeId),
            'approvalHistory' => $this->approvalHistory($companyId, $versionId),
            'effectiveAppointments' => $this->effectiveAppointments(
                $companyId,
                $detail && (string)$detail['status'] === 'effective' ? $versionId : ''
            ),
            'validationResult' => Session::get('responsibility_validation'),
            'alignmentData' => $alignmentData,
        ]);

        return View::fetch('planning_responsibility/index');
    }

    private function alignmentData(?array $detail, string $versionId): array
    {
        if ($detail === null || $versionId === '') {
            return [
                'state' => 'empty',
                'message' => '尚未选择可用于文件对齐的责任链版本。',
                'findings' => [],
                'version' => [],
            ];
        }

        $status = (string)$detail['status'];
        $draftPreview = (string)$this->request->get('draft_preview', '0') === '1';
        if ($status === 'draft' && !$draftPreview) {
            return [
                'state' => 'draft_preview_required',
                'message' => '当前为草案。请明确预览草案后再运行只读文件对齐；预览结果不得作为正式任命或定案证据。',
                'findings' => [],
                'version' => [],
            ];
        }
        if ($status !== 'effective' && !($status === 'draft' && $draftPreview)) {
            return [
                'state' => 'ineligible',
                'message' => '仅有效版本或明确选择预览的草案可用于文件对齐。',
                'findings' => [],
                'version' => [],
            ];
        }

        try {
            $baseline = QmsResponsibilityAlignmentService::baselineForVersion(
                $versionId,
                $status === 'draft' && $draftPreview
            );
            $qmsRoot = dirname(__DIR__, 2);
            $repoRoot = dirname($qmsRoot);
            $inputs = QmsManualProcedureAlignmentService::loadInputs(
                $qmsRoot . '/docs/qms_manual_procedure_alignment_pilot-v0.1.json',
                $repoRoot . '/knowledge/internal/procedures'
            );
            $inputs = QmsResponsibilityAlignmentService::injectBaseline($inputs, $baseline);
            $trace = QmsManualProcedureTraceService::fromDatabase(
                array_values(array_unique(array_map(
                    static fn (array $row): string => (string)$row['manual_section'],
                    (array)$inputs['requirements']
                ))),
                (array)$inputs['pilot_procedures']
            );
            $result = QmsManualProcedureAlignmentService::check($inputs, $trace);
            $targetIds = ['Y13-CX20', 'Y13-CX21', 'Y13-CX32'];
            $findings = [];
            foreach ((array)$result['findings'] as $finding) {
                if (!in_array((string)$finding['finding_id'], $targetIds, true)) {
                    continue;
                }
                $findings[] = $this->alignmentFindingView((array)$finding);
            }

            return [
                'state' => 'ready',
                'message' => $status === 'draft'
                    ? '当前显示草案预览，只用于结构校验，不代表已签批生效。'
                    : '当前显示有效责任链与现行手册、程序文件的只读对齐结果。',
                'findings' => $findings,
                'version' => (array)$baseline['version'],
            ];
        } catch (DomainException $e) {
            return [
                'state' => 'unavailable',
                'message' => $e->getMessage(),
                'findings' => [],
                'version' => [],
            ];
        } catch (Throwable $e) {
            Log::error('读取责任链文件对齐结果失败', ['exception' => $e, 'version_id' => $versionId]);

            return [
                'state' => 'unavailable',
                'message' => '暂时无法读取文件对齐结果，请联系管理员核对只读输入。',
                'findings' => [],
                'version' => [],
            ];
        }
    }

    private function alignmentFindingView(array $finding): array
    {
        $expected = (array)($finding['expected'] ?? []);
        $observed = (array)($finding['observed'] ?? []);
        $status = (string)($finding['status'] ?? 'review_required');

        return [
            'finding_id' => (string)$finding['finding_id'],
            'status' => $status,
            'status_label' => [
                'consistent' => '一致',
                'conflict' => '冲突',
                'missing' => '缺失',
                'review_required' => '人工复核',
                'not_applicable' => '不适用',
            ][$status] ?? $status,
            'status_class' => match ($status) {
                'conflict', 'missing' => 'text-bg-danger',
                'review_required' => 'text-bg-warning',
                'consistent' => 'text-bg-success',
                default => 'text-bg-secondary',
            },
            'expected_role' => (string)($expected['role'] ?? ''),
            'expected_role_code' => (string)($expected['role_code'] ?? ''),
            'observed_roles' => implode('、', array_map('strval', (array)($observed['roles'] ?? []))),
            'observed_position_codes' => implode('、', array_map('strval', (array)($observed['position_codes'] ?? []))),
            'unconfirmed_aliases' => implode('、', array_map('strval', (array)($observed['unconfirmed_aliases'] ?? []))),
            'source_activity_code' => (string)($expected['source_activity_code'] ?? ''),
            'source_step_code' => (string)($expected['source_step_code'] ?? ''),
            'source_responsibility_id' => (string)($expected['source_responsibility_id'] ?? ''),
            'source_refs' => implode('、', array_map('strval', (array)($expected['source_refs'] ?? []))),
            'expected_json' => (string)json_encode($expected, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'observed_json' => (string)json_encode($observed, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ];
    }

    private function bootstrapApprovals(string $companyId, string $employeeId): array
    {
        if ($employeeId === '') {
            return [];
        }

        return Db::name('qms_responsibility_approvals')->alias('a')
            ->leftJoin('employees e', 'e.id=a.subject_employee_id AND e.company_id=a.company_id')
            ->where('a.company_id', $companyId)->where('a.approval_scope', 'governance_bootstrap')
            ->where('a.approver_employee_id', $employeeId)->where('a.decision', 'pending')
            ->where('a.publish', 1)->where('a.soft_delete', 0)
            ->field('a.*,e.name subject_employee_name')->order('a.created')->select()->toArray();
    }

    private function approvalHistory(string $companyId, string $versionId): array
    {
        if ($versionId === '') {
            return [];
        }

        return Db::name('qms_responsibility_approvals')->alias('a')
            ->leftJoin('employees subject', 'subject.id=a.subject_employee_id AND subject.company_id=a.company_id')
            ->leftJoin('employees approver', 'approver.id=a.approver_employee_id AND approver.company_id=a.company_id')
            ->where('a.company_id', $companyId)->where('a.chain_version_id', $versionId)->where('a.soft_delete', 0)
            ->field('a.*,subject.name subject_employee_name,approver.name approver_employee_name')
            ->order('a.created')->select()->toArray();
    }

    private function effectiveAppointments(string $companyId, string $versionId): array
    {
        $query = Db::name('employee_appointments')->alias('ea')
            ->leftJoin('employees e', 'e.id=ea.employee_id AND e.company_id=ea.company_id')
            ->leftJoin('qms_positions p', 'p.id=ea.position_id AND p.company_id=ea.company_id')
            ->leftJoin('sites s', 's.id=ea.site_id AND s.company_id=ea.company_id')
            ->where('ea.company_id', $companyId)->where('ea.source_kind', 'responsibility_chain')
            ->where('ea.status', 'active')->where('ea.publish', 1)->where('ea.soft_delete', 0)
            ->field('ea.*,e.name employee_name,p.name position_display,s.name site_name')
            ->order('e.name,ea.position_name');
        if ($versionId !== '') {
            $query->where('ea.source_chain_version_id', $versionId);
        }

        return $query->select()->toArray();
    }

    private function assertManagerWrite(): void
    {
        if (!in_array((string)Session::get('user.role', 'staff'), self::MANAGER_ROLES, true)) {
            throw new DomainException('仅系统管理员或质量负责人可维护并提交责任链草案。');
        }
    }

    private function assertAdmin(): void
    {
        if ((string)Session::get('user.role', 'staff') !== 'admin') {
            throw new DomainException('只有系统管理员可登记公司治理身份或发起首次主任任命。');
        }
    }

    private function nullablePost(string $name): ?string
    {
        $value = trim((string)$this->request->post($name, ''));

        return $value === '' ? null : $value;
    }

    private function failure(Throwable $e, string $viewMode, string $versionId = '')
    {
        [$recordId, $subjectType, $subjectKey] = $this->auditSubjectFromRequest($versionId);
        $this->markAudit(
            'failed',
            $recordId,
            $subjectType,
            $subjectKey,
            $e instanceof DomainException ? 'domain' : 'internal'
        );
        $this->flashThrowable($e, '执行责任链写操作失败');

        return $this->responsibilityRedirect($versionId, $viewMode);
    }

    private function flashThrowable(Throwable $e, string $context): void
    {
        if ($e instanceof DomainException) {
            Session::flash('error', $e->getMessage());
            return;
        }

        Log::error($context, ['exception' => $e]);
        Session::flash('error', '操作失败，请联系管理员');
    }

    private function markAudit(
        string $outcome,
        string $recordId,
        string $subjectType,
        ?string $subjectKey = null,
        string $failureKind = ''
    ): void {
        $subjectKey = trim((string)($subjectKey ?? $recordId));
        $this->request->withMiddleware([
            'qms_responsibility_audit' => [
                'outcome' => $outcome,
                'record_id' => trim($recordId),
                'subject_type' => $subjectType,
                'subject_key' => $subjectKey,
                'failure_kind' => $failureKind,
            ],
        ]);
    }

    private function auditSubjectFromRequest(string $versionId): array
    {
        $action = strtolower((string)$this->request->action());
        $candidates = match ($action) {
            'saveassignment' => (string)$this->request->post('operation', 'save') === 'remove'
                ? ['assignment_id', 'responsibility_id', 'version_id']
                : ['responsibility_id', 'version_id'],
            'approve' => ['approval_id', 'version_id', 'batch_key'],
            'registergeneralmanager', 'requestlabdirector' => ['employee_id', 'version_id'],
            'createinitialdraft' => ['source_version_id', 'version_id'],
            default => ['version_id', 'assignment_id', 'approval_id', 'responsibility_id', 'batch_key'],
        };
        foreach ($candidates as $field) {
            $value = trim((string)$this->request->post($field, ''));
            if ($value !== '') {
                return [$value, $field, $value];
            }
        }
        if ($versionId !== '') {
            return [$versionId, 'version_id', $versionId];
        }

        $fingerprint = 'request:' . substr(hash('sha256', implode('|', [
            (string)Session::get('user.id', ''),
            (string)$this->request->controller(),
            (string)$this->request->action(),
            (string)Session::get('user.session_id', ''),
        ])), 0, 28);

        return [$fingerprint, 'request_attempt', $fingerprint];
    }

    private function responsibilityRedirect(string $versionId, string $viewMode)
    {
        $query = ['view' => $viewMode];
        if ($versionId !== '') {
            $query['version_id'] = $versionId;
        }

        return redirect('/planning/responsibilities?' . http_build_query($query));
    }
}
