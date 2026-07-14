<?php
declare(strict_types=1);

namespace app\controller;

use app\BaseController;
use app\service\QmsResponsibilityApprovalService;
use app\service\QmsResponsibilityCatalogService;
use app\service\QmsResponsibilityDraftService;
use app\service\QmsResponsibilityValidationService;
use DomainException;
use Throwable;
use think\facade\Config;
use think\facade\Db;
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
            QmsResponsibilityDraftService::saveAssignment(
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
        try {
            $this->assertManagerWrite();
            QmsResponsibilityDraftService::removeAssignment(
                trim((string)$this->request->post('assignment_id', ''))
            );
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
            QmsResponsibilityApprovalService::registerCorporateIdentity([
                'position_code' => 'company_general_manager',
                'employee_id' => trim((string)$this->request->post('employee_id', '')),
                'source_document_number' => trim((string)$this->request->post('source_document_number', '')),
                'source_excerpt' => trim((string)$this->request->post('source_excerpt', '')),
                'appointed_at' => trim((string)$this->request->post('appointed_at', date('Y-m-d'))),
            ]);
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
            QmsResponsibilityApprovalService::requestLabDirectorAppointment(
                trim((string)$this->request->post('employee_id', '')),
                trim((string)$this->request->post('effective_from', date('Y-m-d')))
            );
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
                QmsResponsibilityApprovalService::approveBootstrap(
                    trim((string)$this->request->post('approval_id', '')),
                    $decision,
                    $comments
                );
            } else {
                QmsResponsibilityApprovalService::approveBatch(
                    trim((string)$this->request->post('batch_key', '')),
                    $decision,
                    $comments
                );
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
                Session::flash('error', $e->getMessage());
                $versionId = '';
            }
        }

        $employeeId = trim((string)Session::get('user.employee_id', ''));
        $pendingBatch = null;
        if ($detail && (string)$detail['status'] === 'pending_approval' && $employeeId !== '') {
            try {
                $pendingBatch = QmsResponsibilityApprovalService::pendingBatchForApprover($versionId, $employeeId);
            } catch (Throwable $e) {
                $pendingBatch = null;
            }
        }

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
        ]);

        return View::fetch('planning_responsibility/index');
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
        Session::flash('error', $e->getMessage());

        return $this->responsibilityRedirect($versionId, $viewMode);
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
