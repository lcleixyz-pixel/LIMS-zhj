<?php
declare(strict_types=1);

namespace app\service;

use think\facade\Config;
use think\facade\Db;
use think\facade\Session;

final class ActionAuthorizationService
{
    private const RECORD_FORM_MANAGER_POSITIONS = [
        'quality_manager',
        'document_controller',
        'technical_manager',
        'testing_room_manager',
        'internal_auditor',
    ];

    /** @var array<string,array{id:string,employee_id:string,role:string}|null> */
    private static array $identityCache = [];

    /** @var array<string,list<string>> */
    private static array $activePositionCodesCache = [];

    /** @var array<string,array{profile:bool,active:bool}> */
    private static array $recordOperatorContextCache = [];

    public static function clearRequestCache(): void
    {
        self::$identityCache = [];
        self::$activePositionCodesCache = [];
        self::$recordOperatorContextCache = [];
    }

    public static function allows(string $module, string $action, ?object $record = null): bool
    {
        $identity = self::currentIdentity();
        if ($identity === null) {
            return false;
        }

        $module = self::normalize($module);
        $action = self::normalize($action);
        $employeeId = $identity['employee_id'];
        $userId = $identity['id'];
        if (self::recordValue($record, '_authorization_denied') === '1') {
            return false;
        }

        return match ($module . '.' . $action) {
            'complaint.register', 'complaint.list' => true,
            'complaint.view' => self::isOwnRecord($record, $userId)
                || self::canManageComplaint($employeeId, $record),
            'complaint.advance', 'complaint.manage', 'complaint.createcapa', 'complaint.close'
                => self::canManageComplaint($employeeId, $record),

            'nonconformity.register' => self::hasPositionAtSite(
                $employeeId,
                ['quality_manager', 'site_quality_coordinator', 'technical_manager', 'testing_room_manager'],
                self::recordSiteId($record) ?? self::employeeSiteId($employeeId)
            ),
            'nonconformity.dispose' => self::hasPositionAtSite(
                $employeeId,
                ['quality_manager', 'site_quality_coordinator', 'technical_manager'],
                self::recordSiteId($record)
            ),
            'nonconformity.close' => self::hasGlobalPosition($employeeId, ['quality_manager']),

            'equipment.view' => self::canViewEquipment($employeeId, $userId, $record),
            'equipment.edit' => self::canManageEquipment($employeeId, $record),
            'equipmentmaintenance.write' => self::canManageEquipment($employeeId, $record),
            'equipmenttransfer.view' => self::canViewEquipmentTransfer($employeeId, $record),
            'equipmenttransfer.write' => self::canTransferEquipment($employeeId, $record),

            'recordformtemplate.reviewlist' => self::hasAnyPosition(
                $employeeId,
                ['quality_manager', 'document_controller', 'internal_auditor']
            ),
            'recordformtemplate.draft' => self::hasAnyPosition(
                $employeeId,
                ['quality_manager', 'document_controller']
            ),
            'recordformtemplate.publish' => self::hasGlobalPosition(
                $employeeId,
                ['quality_manager', 'template_approver']
            ),
            'recordformtemplate.approvetrial' => self::canApproveTrialTemplate($employeeId, $record),

            'recordforminstance.create'
                => self::isActiveRecordOperatorIdentity($identity)
                    || self::canManageRecordFormInstances($employeeId),
            'recordforminstance.edit', 'recordforminstance.exportpdf'
                => (
                    self::isActiveRecordOperatorIdentity($identity)
                    && self::isOwnRecord($record, $userId)
                )
                    || self::canManageRecordFormInstances($employeeId),
            'recordforminstance.requestcorrection'
                => !self::isRecordOperatorProfileIdentity($identity)
                    || (
                        self::isActiveRecordOperatorIdentity($identity)
                        && self::isOwnRecord($record, $userId)
                    ),
            'recordforminstance.view'
                => !self::isRecordOperatorProfileIdentity($identity)
                    || self::isOwnRecord($record, $userId),
            'recordforminstance.reviewlist'
                => self::canManageRecordFormInstances($employeeId),
            'recordforminstance.decidecorrection'
                => self::canDecideRecordCorrection($employeeId),
            'recordforminstance.registercorrection'
                => self::canRegisterRecordCorrection($employeeId),
            'governedchange.request' => true,
            'governedchange.event' => true,
            'governedchange.inbox' => true,
            'governedchange.decide' => self::canDecideRecordCorrection($employeeId),
            'qualityworkbench.govern'
                => in_array($identity['role'], ['admin', 'quality_manager', 'auditor'], true)
                    || self::hasAnyPosition(
                        $employeeId,
                        ['quality_manager', 'document_controller', 'internal_auditor', 'technical_manager']
                    ),
            'qualityworkbench.refresh', 'qualityworkbench.transitiontask', 'qualityworkbench.acceptproject'
                => self::hasGlobalPosition($employeeId, ['quality_manager']),

            'auditplan.organize', 'auditplan.approve', 'auditplan.complete'
                => self::hasGlobalPosition($employeeId, ['quality_manager']),
            'auditschedule.organize'
                => self::hasGlobalPosition($employeeId, ['quality_manager']),
            'auditschedule.complete'
                => self::hasGlobalPosition($employeeId, ['quality_manager']),
            'auditchecklist.write', 'auditfinding.write'
                => self::canExecuteAudit($employeeId, $userId, $record),
            'auditfinding.view'
                => self::canViewAudit($employeeId, $userId, $record),

            'managementreview.organize', 'managementreview.complete'
                => self::hasGlobalPosition($employeeId, ['quality_manager']),

            'document.register', 'document.distribute', 'document.recall', 'document.submitreview'
                => self::trialDocumentMutationAllowed($action, $record)
                    && self::canManageDocument($employeeId, $record),
            'document.revise'
                => self::trialDocumentMutationAllowed($action, $record)
                    && self::canManageDocument($employeeId, $record),
            'document.controlledprint'
                => self::hasGlobalPosition($employeeId, ['quality_manager'])
                    || self::canManageDocument($employeeId, $record),
            'document.review'
                => self::trialDocumentMutationAllowed($action, $record)
                    && self::hasAnyPosition($employeeId, ['quality_manager', 'technical_manager']),
            'externalevidencereference.list'
                => self::hasGlobalPosition($employeeId, ['quality_manager']),
            'externalevidencereference.add'
                => self::canAddExternalEvidence($employeeId, $userId, $record),

            'capa.view' => self::hasGlobalPosition($employeeId, ['quality_manager', 'capa_verifier'])
                || self::recordValue($record, 'assigned_to') === $userId,
            'capa.editmeasures', 'capa.advance' => self::hasGlobalPosition($employeeId, ['quality_manager'])
                || self::recordValue($record, 'assigned_to') === $userId,
            'capa.verify', 'capa.close' => self::hasGlobalPosition(
                $employeeId,
                ['quality_manager', 'capa_verifier']
            ),

            'competencyrecord.write' => self::hasPositionAtSite(
                $employeeId,
                ['quality_manager', 'technical_manager'],
                self::recordSiteId($record)
            ),
            default => false,
        };
    }

    /**
     * @return list<string>
     */
    public static function activePositionCodes(string $employeeId, ?string $siteId = null): array
    {
        $employeeId = trim($employeeId);
        if ($employeeId === '') {
            return [];
        }
        $siteId = $siteId !== null ? trim($siteId) : null;
        $cacheKey = implode('|', [
            (string)Config::get('qms.company_id'),
            $employeeId,
            $siteId ?? '*',
            date('Y-m-d'),
        ]);
        if (array_key_exists($cacheKey, self::$activePositionCodesCache)) {
            return self::$activePositionCodesCache[$cacheKey];
        }

        $query = Db::name('employee_appointments')->alias('ea')
            ->join('qms_positions p', 'p.id = ea.position_id')
            ->where('ea.company_id', (string)Config::get('qms.company_id'))
            ->where('p.company_id', (string)Config::get('qms.company_id'))
            ->where('ea.employee_id', $employeeId)
            ->where('ea.status', 'active')
            ->where('ea.publish', 1)
            ->where('ea.soft_delete', 0)
            ->where('p.review_status', 'published')
            ->where('p.publish', 1)
            ->where('p.soft_delete', 0)
            ->where(function ($query) {
                $query->whereNull('ea.appointed_at')->whereOr('ea.appointed_at', '<=', date('Y-m-d'));
            })
            ->where(function ($query) {
                $query->whereNull('ea.valid_until')->whereOr('ea.valid_until', '>=', date('Y-m-d'));
            });

        if ($siteId !== null && $siteId !== '') {
            $query->where(function ($query) use ($siteId) {
                $query->whereNull('ea.site_id')->whereOr('ea.site_id', $siteId);
            });
        }

        $codes = array_map('strval', $query->distinct(true)->column('p.code'));
        sort($codes);

        self::$activePositionCodesCache[$cacheKey] = array_values(array_unique($codes));

        return self::$activePositionCodesCache[$cacheKey];
    }

    public static function isRestrictedRecordOperator(): bool
    {
        $identity = self::currentIdentity();

        return $identity !== null && self::isRecordOperatorProfileIdentity($identity);
    }

    public static function canRecordOperatorFill(): bool
    {
        $identity = self::currentIdentity();

        return $identity !== null && self::isActiveRecordOperatorIdentity($identity);
    }

    /**
     * @return array{all:bool,user_id:string}
     */
    public static function recordFormVisibilityScope(): array
    {
        $identity = self::currentIdentity();
        if ($identity === null) {
            return ['all' => false, 'user_id' => ''];
        }

        return [
            'all' => !self::isRecordOperatorProfileIdentity($identity),
            'user_id' => $identity['id'],
        ];
    }

    public static function canViewRecordFormInstance(?object $record): bool
    {
        $identity = self::currentIdentity();
        if ($identity === null || $record === null) {
            return false;
        }
        if (!self::isRecordOperatorProfileIdentity($identity)) {
            return true;
        }

        return self::isOwnRecord($record, $identity['id']);
    }

    public static function requestDecision(string $controller, string $action, object $request): ?bool
    {
        $policy = self::requestPolicy($controller, $action, $request);
        if ($policy === null) {
            return null;
        }

        return self::allows($policy['module'], $policy['action'], $policy['record']);
    }

    /**
     * @return array{all:bool,user_id:string,site_ids:list<string>}
     */
    public static function complaintVisibilityScope(): array
    {
        $identity = self::currentIdentity();
        if ($identity === null) {
            return ['all' => false, 'user_id' => '', 'site_ids' => []];
        }
        if (self::hasGlobalPosition($identity['employee_id'], ['quality_manager'])) {
            return ['all' => true, 'user_id' => $identity['id'], 'site_ids' => []];
        }

        return [
            'all' => false,
            'user_id' => $identity['id'],
            'site_ids' => self::appointedSiteIds($identity['employee_id'], ['site_quality_coordinator']),
        ];
    }

    /**
     * Null means institution-wide visibility; an empty array means no visible site.
     *
     * @return list<string>|null
     */
    public static function equipmentVisibleSiteIds(): ?array
    {
        $identity = self::currentIdentity();
        if ($identity === null) {
            return [];
        }
        if (self::hasGlobalPosition($identity['employee_id'], ['quality_manager'])) {
            return null;
        }

        return self::appointedSiteIds(
            $identity['employee_id'],
            ['internal_auditor', 'equipment_manager', 'technical_manager']
        );
    }

    /**
     * @return list<string>
     */
    public static function equipmentTransferVisibleSiteIds(): array
    {
        $identity = self::currentIdentity();
        if ($identity === null) {
            return [];
        }

        return self::appointedSiteIds($identity['employee_id'], ['equipment_manager']);
    }

    /**
     * Null means institution-wide management; an empty array means no managed site.
     *
     * @return list<string>|null
     */
    public static function documentManageableSiteIds(): ?array
    {
        $identity = self::currentIdentity();
        if ($identity === null) {
            return [];
        }
        if (self::hasGlobalPosition($identity['employee_id'], ['quality_manager'])) {
            return null;
        }

        return self::appointedSiteIds($identity['employee_id'], ['document_controller']);
    }

    private static function canManageComplaint(string $employeeId, ?object $record): bool
    {
        if (self::hasGlobalPosition($employeeId, ['quality_manager'])) {
            return true;
        }

        return self::hasPositionAtSite(
            $employeeId,
            ['site_quality_coordinator'],
            self::recordSiteId($record)
        );
    }

    private static function canViewEquipment(string $employeeId, string $userId, ?object $record): bool
    {
        if (self::hasGlobalPosition($employeeId, ['quality_manager'])) {
            return true;
        }
        $siteId = self::recordSiteId($record);
        if ($record === null) {
            return self::hasAnyPosition(
                $employeeId,
                ['internal_auditor', 'equipment_manager', 'technical_manager']
            );
        }
        if (self::hasPositionAtSite($employeeId, ['equipment_manager', 'technical_manager'], $siteId)) {
            return true;
        }

        return self::recordValue($record, 'created_by') !== $userId
            && self::hasPositionAtSite($employeeId, ['internal_auditor'], $siteId);
    }

    private static function canManageEquipment(string $employeeId, ?object $record): bool
    {
        if ($record === null) {
            return self::hasAnyPosition($employeeId, ['equipment_manager']);
        }

        return self::hasPositionAtSite(
            $employeeId,
            ['equipment_manager'],
            self::recordSiteId($record)
        );
    }

    private static function canTransferEquipment(string $employeeId, ?object $record): bool
    {
        if ($record === null) {
            return self::hasAnyPosition($employeeId, ['equipment_manager']);
        }

        $fromSiteId = self::recordSiteId($record);
        $toSiteId = trim(self::recordValue($record, '_to_site_id'));
        if ($fromSiteId === null || $toSiteId === '') {
            return false;
        }

        return self::hasPositionAtSite($employeeId, ['equipment_manager'], $fromSiteId)
            && self::hasPositionAtSite($employeeId, ['equipment_manager'], $toSiteId);
    }

    private static function canViewEquipmentTransfer(string $employeeId, ?object $record): bool
    {
        if ($record === null) {
            return self::hasAnyPosition($employeeId, ['equipment_manager']);
        }

        $visibleSiteIds = self::appointedSiteIds($employeeId, ['equipment_manager']);
        $transferSiteIds = array_filter([
            self::recordValue($record, 'from_site_id'),
            self::recordValue($record, 'to_site_id'),
        ]);

        return array_intersect($visibleSiteIds, $transferSiteIds) !== [];
    }

    private static function canManageDocument(string $employeeId, ?object $record): bool
    {
        if (self::hasGlobalPosition($employeeId, ['quality_manager'])) {
            return true;
        }
        if ($record === null) {
            return self::hasAnyPosition($employeeId, ['document_controller']);
        }

        return self::hasPositionAtSite(
            $employeeId,
            ['document_controller'],
            self::recordSiteId($record)
        );
    }

    private static function trialDocumentMutationAllowed(string $action, ?object $record): bool
    {
        if (!TrialModeService::isEnabled() || $record === null) {
            return true;
        }
        $documentNumber = self::recordValue($record, 'doc_number');
        if ($documentNumber === '' && $action === 'register') {
            return true;
        }
        if (TrialModeService::isSimulationNumber($documentNumber)) {
            return true;
        }

        return in_array($action, ['revise', 'controlledprint'], true);
    }

    private static function canExecuteAudit(string $employeeId, string $userId, ?object $schedule): bool
    {
        if (self::recordValue($schedule, '_authorization_denied') === '1') {
            return false;
        }
        if ($schedule !== null && self::recordValue($schedule, 'status') === 'completed') {
            return false;
        }
        if (self::hasGlobalPosition($employeeId, ['quality_manager'])) {
            return true;
        }
        if ($schedule === null || self::recordValue($schedule, 'auditor_id') !== $userId) {
            return false;
        }

        return self::hasPositionAtSite(
            $employeeId,
            ['internal_auditor'],
            self::recordSiteId($schedule)
        );
    }

    private static function canViewAudit(string $employeeId, string $userId, ?object $schedule): bool
    {
        if (self::recordValue($schedule, '_authorization_denied') === '1') {
            return false;
        }
        if (self::hasGlobalPosition($employeeId, ['quality_manager'])) {
            return true;
        }
        if ($schedule === null) {
            return self::hasAnyPosition($employeeId, ['internal_auditor']);
        }
        if (self::recordValue($schedule, 'auditor_id') !== $userId) {
            return false;
        }

        return self::hasPositionAtSite(
            $employeeId,
            ['internal_auditor'],
            self::recordSiteId($schedule)
        );
    }

    private static function canAddExternalEvidence(string $employeeId, string $userId, ?object $subject): bool
    {
        if (self::hasGlobalPosition($employeeId, ['quality_manager'])) {
            return true;
        }
        if ($subject === null) {
            return false;
        }

        $subjectType = self::recordValue($subject, '_subject_type');
        if ($subjectType === 'audit') {
            return self::canExecuteAudit($employeeId, $userId, $subject);
        }
        if ($subjectType === 'management_review') {
            return false;
        }
        if ($subjectType === 'capa' && self::recordValue($subject, 'assigned_to') === $userId) {
            return true;
        }

        return self::hasPositionAtSite(
            $employeeId,
            ['technical_manager', 'site_quality_coordinator'],
            self::recordSiteId($subject)
        );
    }

    private static function canApproveTrialTemplate(string $employeeId, ?object $record): bool
    {
        $responsiblePosition = self::recordValue($record, 'responsible_position_code');
        if (in_array($responsiblePosition, ['technical_manager', 'equipment_manager'], true)) {
            return self::hasGlobalPosition($employeeId, ['technical_manager']);
        }

        return self::hasGlobalPosition($employeeId, ['quality_manager']);
    }

    private static function canDecideRecordCorrection(string $employeeId): bool
    {
        return self::hasAnyPosition($employeeId, ['quality_manager', 'top_management'])
            || self::hasGlobalPosition($employeeId, ['quality_manager', 'top_management']);
    }

    private static function canRegisterRecordCorrection(string $employeeId): bool
    {
        return self::hasAnyPosition(
            $employeeId,
            ['quality_manager', 'document_controller', 'technical_manager', 'top_management']
        )
            || self::hasGlobalPosition(
                $employeeId,
                ['quality_manager', 'document_controller', 'technical_manager', 'top_management']
            );
    }

    private static function canManageRecordFormInstances(string $employeeId): bool
    {
        return self::hasAnyPosition($employeeId, self::RECORD_FORM_MANAGER_POSITIONS)
            || self::hasGlobalPosition($employeeId, ['quality_manager']);
    }

    /**
     * @param array{id:string,employee_id:string,role:string} $identity
     */
    private static function isRecordOperatorProfileIdentity(array $identity): bool
    {
        return self::recordOperatorContext($identity)['profile'];
    }

    /**
     * @param array{id:string,employee_id:string,role:string} $identity
     */
    private static function isActiveRecordOperatorIdentity(array $identity): bool
    {
        return self::recordOperatorContext($identity)['active'];
    }

    /**
     * @param array{id:string,employee_id:string,role:string} $identity
     * @return array{profile:bool,active:bool}
     */
    private static function recordOperatorContext(array $identity): array
    {
        $cacheKey = implode('|', [
            (string)Config::get('qms.company_id'),
            $identity['id'],
            $identity['employee_id'],
            $identity['role'],
        ]);
        if (array_key_exists($cacheKey, self::$recordOperatorContextCache)) {
            return self::$recordOperatorContextCache[$cacheKey];
        }
        if ($identity['role'] !== 'staff') {
            return self::$recordOperatorContextCache[$cacheKey] = [
                'profile' => false,
                'active' => false,
            ];
        }

        $historicalRecordOperator = Db::name('employee_appointments')->alias('ea')
            ->join('qms_positions p', 'p.id = ea.position_id')
            ->where('ea.company_id', (string)Config::get('qms.company_id'))
            ->where('p.company_id', (string)Config::get('qms.company_id'))
            ->where('ea.employee_id', $identity['employee_id'])
            ->where('p.code', 'record_operator')
            ->count() > 0;
        if (!$historicalRecordOperator) {
            return self::$recordOperatorContextCache[$cacheKey] = [
                'profile' => false,
                'active' => false,
            ];
        }

        $activePositionCodes = self::activePositionCodes($identity['employee_id']);
        $profile = array_intersect(
            self::RECORD_FORM_MANAGER_POSITIONS,
            $activePositionCodes
        ) === [];

        return self::$recordOperatorContextCache[$cacheKey] = [
            'profile' => $profile,
            'active' => $profile && in_array('record_operator', $activePositionCodes, true),
        ];
    }

    private static function hasAnyPosition(string $employeeId, array $codes): bool
    {
        return array_intersect($codes, self::activePositionCodes($employeeId)) !== [];
    }

    private static function hasGlobalPosition(string $employeeId, array $codes): bool
    {
        return self::appointmentQuery($employeeId, $codes)->whereNull('ea.site_id')->count() > 0;
    }

    private static function hasPositionAtSite(string $employeeId, array $codes, ?string $siteId): bool
    {
        if ($siteId === null || trim($siteId) === '') {
            return self::hasGlobalPosition($employeeId, $codes);
        }

        $siteId = trim($siteId);

        return self::appointmentQuery($employeeId, $codes)
            ->where(function ($query) use ($siteId) {
                $query->whereNull('ea.site_id')->whereOr('ea.site_id', $siteId);
            })
            ->count() > 0;
    }

    private static function appointmentQuery(string $employeeId, array $codes)
    {
        return Db::name('employee_appointments')->alias('ea')
            ->join('qms_positions p', 'p.id = ea.position_id')
            ->where('ea.company_id', (string)Config::get('qms.company_id'))
            ->where('p.company_id', (string)Config::get('qms.company_id'))
            ->where('ea.employee_id', $employeeId)
            ->whereIn('p.code', $codes)
            ->where('ea.status', 'active')
            ->where('ea.publish', 1)
            ->where('ea.soft_delete', 0)
            ->where('p.review_status', 'published')
            ->where('p.publish', 1)
            ->where('p.soft_delete', 0)
            ->where(function ($query) {
                $query->whereNull('ea.appointed_at')->whereOr('ea.appointed_at', '<=', date('Y-m-d'));
            })
            ->where(function ($query) {
                $query->whereNull('ea.valid_until')->whereOr('ea.valid_until', '>=', date('Y-m-d'));
            });
    }

    /**
     * @return list<string>
     */
    private static function appointedSiteIds(string $employeeId, array $codes): array
    {
        $query = self::appointmentQuery($employeeId, $codes);
        if ((clone $query)->whereNull('ea.site_id')->count() > 0) {
            return array_map(
                'strval',
                Db::name('sites')
                    ->where('company_id', (string)Config::get('qms.company_id'))
                    ->where('status', 'active')
                    ->where('publish', 1)
                    ->where('soft_delete', 0)
                    ->column('id')
            );
        }

        return array_values(array_unique(array_filter(array_map(
            'strval',
            $query->whereNotNull('ea.site_id')->column('ea.site_id')
        ))));
    }

    /**
     * @return array{id:string,employee_id:string,role:string}|null
     */
    private static function currentIdentity(): ?array
    {
        $userId = trim((string)Session::get('user.id', ''));
        $employeeId = trim((string)Session::get('user.employee_id', ''));
        $sessionRole = trim((string)Session::get('user.role', ''));
        if ($userId === '' || $employeeId === '') {
            return null;
        }
        $cacheKey = implode('|', [$userId, $employeeId, $sessionRole]);
        if (array_key_exists($cacheKey, self::$identityCache)) {
            return self::$identityCache[$cacheKey];
        }

        $user = Db::name('users')
            ->where('id', $userId)
            ->where('employee_id', $employeeId)
            ->where('publish', 1)
            ->where('soft_delete', 0)
            ->find();
        if (!$user) {
            return self::$identityCache[$cacheKey] = null;
        }

        $employee = Db::name('employees')
            ->where('id', $employeeId)
            ->where('company_id', (string)Config::get('qms.company_id'))
            ->where('publish', 1)
            ->where('soft_delete', 0)
            ->find();
        if (!$employee) {
            return self::$identityCache[$cacheKey] = null;
        }

        return self::$identityCache[$cacheKey] = [
            'id' => $userId,
            'employee_id' => $employeeId,
            'role' => (string)$user['role'],
        ];
    }

    /**
     * @return array{module:string,action:string,record:?object}|null
     */
    private static function requestPolicy(string $controller, string $action, object $request): ?array
    {
        $controller = self::normalize($controller);
        $action = self::normalize($action);
        $module = $controller;
        $policyAction = null;
        $record = null;

        if ($controller === 'complaint') {
            $policyAction = match ($action) {
                'index' => 'list',
                'add' => 'register',
                'view' => 'view',
                'edit', 'delete' => 'manage',
                'advance' => 'advance',
                'createcapa' => 'create_capa',
                default => null,
            };
            if (in_array($action, ['view', 'edit', 'delete', 'advance', 'createcapa'], true)) {
                $record = self::tableRecord('customer_complaints', self::requestId($request));
            }
        } elseif ($controller === 'equipment') {
            $policyAction = in_array($action, ['index', 'view'], true)
                ? 'view'
                : (in_array($action, ['add', 'edit', 'delete'], true) ? 'edit' : null);
            if (in_array($action, ['view', 'edit', 'delete'], true)) {
                $record = self::tableRecord('equipments', self::requestId($request));
            } elseif ($action === 'add') {
                $siteId = trim((string)$request->post('site_id', ''));
                $record = (object)[
                    'site_id' => $siteId !== '' ? $siteId : self::employeeSiteId(
                        (string)Session::get('user.employee_id', '')
                    ),
                ];
            }
        } elseif ($controller === 'equipmentperiodcheck') {
            $module = 'equipment';
            $policyAction = $action === 'index' ? 'view' : ($action === 'add' ? 'edit' : null);
            if ($action === 'add') {
                $equipmentId = trim((string)$request->post(
                    'equipment_id',
                    (string)$request->param('equipment_id', '')
                ));
                $record = $equipmentId !== '' ? self::tableRecord('equipments', $equipmentId) : null;
            }
        } elseif ($controller === 'equipmentmaintenance') {
            if (in_array($action, ['add', 'edit', 'delete'], true)) {
                $policyAction = 'write';
                $equipmentId = trim((string)$request->post('equipment_id', ''));
                if ($equipmentId === '') {
                    $equipmentId = trim((string)$request->param('equipment_id', ''));
                }
                if ($equipmentId === '' && in_array($action, ['edit', 'delete'], true)) {
                    $maintenance = self::tableRecord('equipment_maintenances', self::requestId($request));
                    $equipmentId = self::recordValue($maintenance, 'equipment_id');
                }
                $record = self::tableRecord('equipments', $equipmentId);
            }
        } elseif ($controller === 'equipmenttransfer') {
            $policyAction = in_array($action, ['index', 'view'], true) ? 'view' : ($action === 'add' ? 'write' : null);
            if ($action === 'add' && $request->isPost()) {
                $record = self::tableRecord(
                    'equipments',
                    trim((string)$request->post('equipment_id', ''))
                );
                if ($record !== null) {
                    $record->_to_site_id = trim((string)$request->post('to_site_id', ''));
                }
            } elseif ($action === 'view') {
                $record = self::tableRecord('equipment_transfers', self::requestId($request));
            }
        } elseif ($controller === 'recordformtemplate') {
            $policyAction = match ($action) {
                'review' => 'review_list',
                'add', 'edit', 'delete', 'createrevision', 'reviewschemadraftfields', 'preparecoretrialtemplates' => 'draft',
                'updatereview', 'obsolete' => 'publish',
                'approvetrial' => 'approve_trial',
                default => null,
            };
            if ($action === 'approvetrial') {
                $record = self::tableRecord('record_form_templates', self::requestId($request));
            }
        } elseif ($controller === 'recordforminstance') {
            $policyAction = match ($action) {
                'create' => 'create',
                'edit' => 'edit',
                'exportpdf' => 'export_pdf',
                'downloadcurrentpackage', 'downloadcurrentpdf' => 'export_pdf',
                'requestcorrection' => 'request_correction',
                'view', 'print', 'printcorrections', 'downloadpdf', 'downloadpreviewpdf' => 'view',
                'reviewdashboard', 'updatelayoutstatus', 'reviewartifact' => 'review_list',
                'decidecorrection' => 'decide_correction',
                'registercorrection' => 'register_correction',
                default => null,
            };
            if (in_array($action, [
                'edit',
                'exportpdf',
                'view',
                'print',
                'printcorrections',
                'downloadcurrentpackage',
                'downloadcurrentpdf',
                'downloadpdf',
                'downloadpreviewpdf',
                'requestcorrection',
                'decidecorrection',
                'registercorrection',
            ], true)) {
                $record = self::recordFormInstanceRecord(self::requestId($request))
                    ?? self::authorizationDeniedRecord();
            }
        } elseif ($controller === 'governedchange') {
            $policyAction = match ($action) {
                'request' => 'request',
                'event' => 'event',
                'inbox' => 'inbox',
                'decide' => 'decide',
                default => null,
            };
        } elseif ($controller === 'qualityworkbench') {
            $policyAction = match ($action) {
                'projects', 'view' => 'govern',
                'refresh' => 'refresh',
                'transitiontask' => 'transitiontask',
                'acceptproject' => 'acceptproject',
                default => null,
            };
        } elseif ($controller === 'auditplan') {
            $policyAction = match ($action) {
                'add', 'edit', 'delete' => 'organize',
                'approve' => 'approve',
                'complete' => 'complete',
                default => null,
            };
            if (in_array($action, ['edit', 'delete', 'approve', 'complete'], true)) {
                $record = self::tableRecord('audit_plans', self::requestId($request))
                    ?? self::authorizationDeniedRecord();
            }
        } elseif ($controller === 'auditschedule') {
            $policyAction = match ($action) {
                'add', 'edit', 'delete' => 'organize',
                'complete' => 'complete',
                default => null,
            };
            if ($policyAction !== null) {
                $record = $action === 'add'
                    ? (object)['site_id' => trim((string)$request->post('site_id', ''))]
                    : (
                        self::tableRecord('audit_schedules', self::requestId($request))
                        ?? self::authorizationDeniedRecord()
                    );
            }
        } elseif ($controller === 'auditchecklist') {
            if (in_array($action, ['add', 'edit', 'delete'], true)) {
                $policyAction = 'write';
                $scheduleId = trim((string)$request->post('audit_schedule_id', ''));
                if ($scheduleId === '' && in_array($action, ['edit', 'delete'], true)) {
                    $checklist = self::tableRecord('audit_checklists', self::requestId($request));
                    if ($checklist === null) {
                        $record = self::authorizationDeniedRecord();
                    } else {
                        $scheduleId = self::recordValue($checklist, 'audit_schedule_id');
                    }
                }
                if ($record === null && $scheduleId !== '') {
                    $record = self::tableRecord('audit_schedules', $scheduleId)
                        ?? self::authorizationDeniedRecord();
                } elseif ($record === null && $action === 'add' && $request->isPost()) {
                    $record = self::authorizationDeniedRecord();
                }
            }
        } elseif ($controller === 'auditfinding') {
            if (in_array($action, ['add', 'edit', 'delete', 'createcapa', 'uploadevidence', 'view', 'downloadevidence'], true)) {
                $policyAction = in_array($action, ['view', 'downloadevidence'], true) ? 'view' : 'write';
                $scheduleId = trim((string)$request->post('audit_schedule_id', ''));
                if ($scheduleId === '') {
                    $finding = self::tableRecord('audit_findings', self::requestId($request));
                    if ($finding === null && $action !== 'add') {
                        $record = self::authorizationDeniedRecord();
                    } else {
                        $scheduleId = self::recordValue($finding, 'audit_schedule_id');
                    }
                }
                if ($record === null && $scheduleId !== '') {
                    $record = self::tableRecord('audit_schedules', $scheduleId)
                        ?? self::authorizationDeniedRecord();
                } elseif ($record === null && $action === 'add' && $request->isPost()) {
                    $record = self::authorizationDeniedRecord();
                }
            }
        } elseif ($controller === 'managementreview') {
            $policyAction = match ($action) {
                'add', 'edit', 'delete' => 'organize',
                'complete' => 'complete',
                default => null,
            };
            if (in_array($action, ['edit', 'delete', 'complete'], true)) {
                $record = self::tableRecord('management_reviews', self::requestId($request));
            }
        } elseif ($controller === 'document') {
            $policyAction = match ($action) {
                'add', 'edit' => 'register',
                'distribute' => 'distribute',
                'obsolete' => 'recall',
                'revise' => 'revise',
                'submitreview' => 'submit_review',
                'controlledprint' => 'controlled_print',
                'review' => 'review',
                default => null,
            };
            if ($policyAction !== null && $action !== 'add') {
                $record = self::tableRecord('documents', self::requestId($request));
            } elseif ($action === 'add' && $request->isPost()) {
                $record = (object)['site_id' => trim((string)$request->post('site_id', ''))];
            }
        } elseif ($controller === 'externalevidencereference') {
            $policyAction = match ($action) {
                'index' => 'list',
                'add' => 'add',
                default => null,
            };
            if ($action === 'add') {
                $record = self::externalEvidenceSubjectRecord(
                    trim((string)$request->post('subject_type', '')),
                    trim((string)$request->post('subject_id', ''))
                );
            }
        } elseif ($controller === 'capa') {
            $policyAction = match ($action) {
                'view' => 'view',
                'edit' => 'edit_measures',
                'advance' => self::normalize((string)$request->post('action', 'advance')) === 'close'
                    ? 'close'
                    : 'advance',
                'revieweffectiveness' => 'verify',
                default => null,
            };
            if ($policyAction !== null) {
                $record = self::tableRecord('capas', self::requestId($request));
            }
        } elseif ($controller === 'competencyrecord') {
            if (in_array($action, ['add', 'edit', 'delete'], true)) {
                $policyAction = 'write';
                $employeeId = trim((string)$request->post('employee_id', ''));
                if ($employeeId === '' && in_array($action, ['edit', 'delete'], true)) {
                    $competency = self::tableRecord('competency_records', self::requestId($request));
                    $employeeId = self::recordValue($competency, 'employee_id');
                }
                $record = $employeeId !== ''
                    ? self::tableRecord('employees', $employeeId)
                    : (object)['primary_site_id' => self::employeeSiteId(
                        (string)Session::get('user.employee_id', '')
                    )];
            }
        } elseif ($controller === 'nonconformity') {
            $policyAction = match ($action) {
                'add' => 'register',
                'edit' => 'dispose',
                'delete' => 'close',
                'createcapa' => 'dispose',
                default => null,
            };
            if ($policyAction !== null && $action !== 'add') {
                $record = self::tableRecord('nonconformities', self::requestId($request));
            }
        }

        return $policyAction === null
            ? null
            : ['module' => $module, 'action' => $policyAction, 'record' => $record];
    }

    private static function requestId(object $request): string
    {
        $id = trim((string)$request->param('id', ''));

        return $id !== '' ? $id : trim((string)$request->post('id', ''));
    }

    private static function tableRecord(string $table, string $id): ?object
    {
        if ($id === '') {
            return null;
        }
        if (in_array($table, ['audit_schedules', 'audit_checklists', 'audit_findings'], true)) {
            $query = Db::name($table)->alias('t');
            if ($table === 'audit_schedules') {
                $query->join('audit_plans p', 'p.id = t.audit_plan_id');
            } else {
                $query->join('audit_schedules s', 's.id = t.audit_schedule_id')
                    ->join('audit_plans p', 'p.id = s.audit_plan_id')
                    ->where('s.soft_delete', 0);
            }
            $row = $query
                ->where('t.id', $id)
                ->where('t.soft_delete', 0)
                ->where('p.company_id', (string)Config::get('qms.company_id'))
                ->where('p.soft_delete', 0)
                ->field('t.*')
                ->find();

            return $row ? (object)$row : null;
        }

        $query = Db::name($table)->where('id', $id)->where('soft_delete', 0);
        $companyTables = [
            'audit_plans',
            'capas',
            'customer_complaints',
            'documents',
            'employees',
            'equipment_maintenances',
            'equipment_transfers',
            'equipments',
            'management_reviews',
            'nonconformities',
            'record_form_templates',
        ];
        if (in_array($table, $companyTables, true)) {
            $query->where('company_id', (string)Config::get('qms.company_id'));
        }
        $row = $query->find();

        return $row ? (object)$row : null;
    }

    private static function recordFormInstanceRecord(string $id): ?object
    {
        if ($id === '') {
            return null;
        }

        $row = Db::name('record_form_instances')
            ->where('id', $id)
            ->where('company_id', (string)Config::get('qms.company_id'))
            ->find();

        return $row ? (object)$row : null;
    }

    private static function externalEvidenceSubjectRecord(string $subjectType, string $subjectId): ?object
    {
        $tables = [
            'quality_event' => 'nonconformities',
            'complaint' => 'customer_complaints',
            'capa' => 'capas',
            'management_review' => 'management_reviews',
        ];
        if ($subjectType === 'audit') {
            $finding = self::tableRecord('audit_findings', $subjectId);
            $schedule = $finding
                ? self::tableRecord('audit_schedules', self::recordValue($finding, 'audit_schedule_id'))
                : null;
            $planId = self::recordValue($schedule, 'audit_plan_id');
            if (
                $schedule === null
                || $planId === ''
                || Db::name('audit_plans')
                    ->where('id', $planId)
                    ->where('company_id', (string)Config::get('qms.company_id'))
                    ->where('soft_delete', 0)
                    ->count() === 0
            ) {
                return null;
            }
            if ($schedule !== null) {
                $schedule->_subject_type = 'audit';
            }
            return $schedule;
        }

        $table = $tables[$subjectType] ?? '';
        $record = $table !== '' ? self::tableRecord($table, $subjectId) : null;
        if ($record !== null) {
            $record->_subject_type = $subjectType;
        }

        return $record;
    }

    private static function recordSiteId(?object $record): ?string
    {
        if ($record === null) {
            return null;
        }
        $siteId = self::recordValue($record, 'site_id');
        if ($siteId !== '') {
            return $siteId;
        }
        $primarySiteId = self::recordValue($record, 'primary_site_id');
        if ($primarySiteId !== '') {
            return $primarySiteId;
        }
        $employeeId = self::recordValue($record, 'employee_id');
        if ($employeeId !== '') {
            $siteId = (string)Db::name('employees')->where('id', $employeeId)->value('primary_site_id');
            if ($siteId !== '') {
                return $siteId;
            }
        }
        $creatorId = self::recordValue($record, 'created_by');
        if ($creatorId !== '') {
            $siteId = (string)Db::name('users')->alias('u')
                ->join('employees e', 'e.id = u.employee_id')
                ->where('u.id', $creatorId)
                ->value('e.primary_site_id');
        }

        return $siteId !== '' ? $siteId : null;
    }

    private static function authorizationDeniedRecord(): object
    {
        return (object)['_authorization_denied' => 1];
    }

    private static function employeeSiteId(string $employeeId): ?string
    {
        $siteId = trim((string)Db::name('employees')
            ->where('id', trim($employeeId))
            ->where('publish', 1)
            ->where('soft_delete', 0)
            ->value('primary_site_id'));

        return $siteId !== '' ? $siteId : null;
    }

    private static function isOwnRecord(?object $record, string $userId): bool
    {
        return $record !== null && in_array(
            $userId,
            [self::recordValue($record, 'created_by'), self::recordValue($record, 'assigned_to')],
            true
        );
    }

    private static function recordValue(?object $record, string $field): string
    {
        if ($record === null) {
            return '';
        }
        if (method_exists($record, 'getAttr')) {
            return trim((string)$record->getAttr($field));
        }

        return trim((string)($record->{$field} ?? ''));
    }

    private static function normalize(string $value): string
    {
        return strtolower(str_replace(['_', '-', '\\', '/'], '', trim($value)));
    }
}
