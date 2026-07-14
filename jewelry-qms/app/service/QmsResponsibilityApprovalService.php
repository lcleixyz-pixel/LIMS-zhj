<?php
declare(strict_types=1);

namespace app\service;

use DateTimeImmutable;
use DomainException;
use Throwable;
use think\facade\Config;
use think\facade\Db;
use think\facade\Session;

final class QmsResponsibilityApprovalService
{
    private const GM_CODE = 'company_general_manager';
    private const DIRECTOR_CODE = 'lab_director';

    public static function registerCorporateIdentity(array $data): array
    {
        $user = self::currentUser(['admin']);
        if (trim((string)($data['position_code'] ?? '')) !== self::GM_CODE) {
            throw new DomainException('仅允许登记公司总经理既有治理身份。');
        }
        $employeeId = trim((string)($data['employee_id'] ?? ''));
        $documentNumber = trim((string)($data['source_document_number'] ?? ''));
        $excerpt = trim((string)($data['source_excerpt'] ?? ''));
        $appointedAt = trim((string)($data['appointed_at'] ?? date('Y-m-d')));
        self::assertDate($appointedAt, '登记生效日期');
        if ($documentNumber === '' || $excerpt === '') {
            throw new DomainException('公司治理身份必须提供来源文件编号和证据摘要。');
        }

        return QmsPositionAliasService::withSeededCatalogLock(
            static function (string $companyId, array $positions) use (
                $employeeId,
                $documentNumber,
                $excerpt,
                $appointedAt,
                $user
            ): array {
                self::assertCurrentUserStillActive($user, $companyId, true);
                $position = $positions[self::GM_CODE] ?? null;
                self::assertPublishedPosition($position, self::GM_CODE, $companyId);
                self::activeEmployeeWithUser($employeeId, $companyId, true);
                $owners = self::businessOwners(self::GM_CODE, $companyId, true);
                if ($owners !== [] && ($owners !== [$employeeId])) {
                    throw new DomainException('已存在其他有效公司总经理，不得并行登记。');
                }

                $key = 'corporate:company_general_manager';
                $existing = Db::name('employee_appointments')
                    ->where('company_id', $companyId)
                    ->where('appointment_key', $key)
                    ->lock(true)
                    ->find();
                $now = date('Y-m-d H:i:s');
                $payload = [
                    'company_id' => $companyId,
                    'employee_id' => $employeeId,
                    'position_id' => (string)$position['id'],
                    'site_id' => null,
                    'appointment_key' => $key,
                    'appointment_type' => 'role',
                    'position_name' => '公司总经理',
                    'appointment_scope' => '登记公司既有治理身份，不表示由管理员任命。',
                    'appointed_at' => $appointedAt,
                    'valid_until' => null,
                    'source_document_number' => $documentNumber,
                    'source_excerpt' => $excerpt,
                    'source_kind' => 'corporate_evidence',
                    'source_chain_version_id' => null,
                    'source_responsibility_id' => null,
                    'source_approval_id' => null,
                    'status' => 'active',
                    'publish' => 1,
                    'soft_delete' => 0,
                    'modified' => $now,
                    'modified_by' => (string)$user['id'],
                ];
                if ($existing) {
                    if ((string)$existing['employee_id'] !== $employeeId) {
                        throw new DomainException('公司总经理稳定登记键已属于其他人员。');
                    }
                    Db::name('employee_appointments')->where('id', (string)$existing['id'])->update($payload);
                    $id = (string)$existing['id'];
                } else {
                    $id = qms_uuid();
                    Db::name('employee_appointments')->insert(array_merge($payload, [
                        'id' => $id,
                        'created' => $now,
                        'created_by' => (string)$user['id'],
                    ]));
                }

                return self::appointmentRow($id, $companyId);
            }
        );
    }

    public static function requestLabDirectorAppointment(string $employeeId, string $effectiveFrom): array
    {
        $user = self::currentUser(['admin']);
        self::assertDate($effectiveFrom, '实验室主任建议生效日期');

        return QmsPositionAliasService::withSeededCatalogLock(
            static function (string $companyId, array $positions) use ($employeeId, $effectiveFrom, $user): array {
                self::assertCurrentUserStillActive($user, $companyId, true);
                self::activeEmployeeWithUser($employeeId, $companyId, true);
                $gmId = self::uniqueBusinessOwner(self::GM_CODE, $companyId, true);
                if ($employeeId === $gmId) {
                    throw new DomainException('公司总经理不得批准自己担任实验室主任。');
                }
                $directors = self::activeRoleOwnersAnySource(self::DIRECTOR_CODE, $companyId, true);
                if ($directors !== [] && $directors !== [$employeeId]) {
                    throw new DomainException('已存在其他有效实验室主任。');
                }
                self::assertPublishedPosition($positions[self::DIRECTOR_CODE] ?? null, self::DIRECTOR_CODE, $companyId);
                $positionId = (string)$positions[self::DIRECTOR_CODE]['id'];
                $batchKey = hash('sha256', implode('|', ['bootstrap', $companyId, $employeeId, $effectiveFrom]));
                $existing = Db::name('qms_responsibility_approvals')
                    ->where('company_id', $companyId)
                    ->where('approval_scope', 'governance_bootstrap')
                    ->where('batch_key', $batchKey)
                    ->where('decision', 'pending')
                    ->where('soft_delete', 0)
                    ->lock(true)
                    ->find();
                if ($existing) {
                    return self::approvalRow((string)$existing['id'], $companyId);
                }
                $id = qms_uuid();
                $now = date('Y-m-d H:i:s');
                Db::name('qms_responsibility_approvals')->insert([
                    'id' => $id,
                    'company_id' => $companyId,
                    'approval_scope' => 'governance_bootstrap',
                    'chain_version_id' => null,
                    'assignment_id' => null,
                    'subject_employee_id' => $employeeId,
                    'subject_position_id' => $positionId,
                    'approver_employee_id' => $gmId,
                    'approver_position_code' => self::GM_CODE,
                    'batch_key' => $batchKey,
                    'decision' => 'pending',
                    'signature_metadata' => self::json([
                        'effective_from' => $effectiveFrom,
                        'source_semantics' => 'company_general_manager_appoints_lab_director',
                    ]),
                    'publish' => 1,
                    'soft_delete' => 0,
                    'created' => $now,
                    'modified' => $now,
                    'created_by' => (string)$user['id'],
                    'modified_by' => (string)$user['id'],
                ]);

                return self::approvalRow($id, $companyId);
            }
        );
    }

    public static function approveBootstrap(string $approvalId, string $decision, string $comments): array
    {
        self::assertDecision($decision);
        $user = self::currentUser();
        $companyId = self::companyId();

        return Db::transaction(static function () use ($approvalId, $decision, $comments, $user, $companyId): array {
            self::lockCompany($companyId);
            self::assertCurrentUserStillActive($user, $companyId, true);
            $approval = Db::name('qms_responsibility_approvals')
                ->where('id', $approvalId)->where('company_id', $companyId)
                ->where('approval_scope', 'governance_bootstrap')->where('soft_delete', 0)
                ->lock(true)->find();
            if (!$approval || (string)$approval['decision'] !== 'pending') {
                throw new DomainException('待签批的实验室主任登记不存在或已处理。');
            }
            $gmId = self::uniqueBusinessOwner(self::GM_CODE, $companyId, true);
            if ((string)$user['employee_id'] !== (string)$approval['approver_employee_id'] || $gmId !== (string)$user['employee_id']) {
                throw new DomainException('仅当前唯一有效公司总经理可签批。');
            }
            if ((string)$approval['subject_employee_id'] === (string)$user['employee_id']) {
                throw new DomainException('不得自我批准实验室主任任命。');
            }
            $baseMetadata = self::decodeJson($approval['signature_metadata'] ?? null);
            $metadata = array_merge($baseMetadata, self::signatureMetadata($user, self::GM_CODE, $decision));
            $now = date('Y-m-d H:i:s');
            Db::name('qms_responsibility_approvals')->where('id', $approvalId)->update([
                'approver_user_id' => (string)$user['id'],
                'decision' => $decision,
                'comments' => trim($comments),
                'signature_metadata' => self::json($metadata),
                'signed_at' => $now,
                'modified' => $now,
                'modified_by' => (string)$user['id'],
            ]);
            if ($decision === 'approved') {
                self::createDirectorAppointment($approval, $metadata, $user, $companyId);
            }

            return self::approvalRow($approvalId, $companyId);
        });
    }

    public static function submitVersion(string $versionId): array
    {
        $user = self::currentUser(['admin', 'quality_manager']);
        $companyId = self::companyId();

        return Db::transaction(static function () use ($versionId, $user, $companyId): array {
            self::lockCompany($companyId);
            self::assertCurrentUserStillActive($user, $companyId, true);
            $version = self::versionRow($versionId, $companyId, true);
            if ((string)$version['status'] === 'pending_approval') {
                return $version;
            }
            if ((string)$version['status'] !== 'draft') {
                throw new DomainException('仅草案版本可提交签批。');
            }
            $validation = QmsResponsibilityValidationService::validateVersion($versionId, 'activation');
            if (($validation['result'] ?? '') !== 'pass' || ($validation['can_submit'] ?? false) !== true) {
                throw new DomainException('责任链激活校验未通过，不得提交。');
            }
            $hash = QmsResponsibilityDraftService::contentHash($versionId);
            if (strlen($hash) !== 64) {
                throw new DomainException('责任链内容哈希无效。');
            }

            $assignments = self::versionAssignments($versionId, $companyId, true, ['draft']);
            $routes = self::approvalRoutes($assignments, $companyId, true);
            $now = date('Y-m-d H:i:s');
            $submissionRound = qms_uuid();
            Db::name('qms_responsibility_approvals')
                ->where('company_id', $companyId)->where('chain_version_id', $versionId)
                ->where('decision', 'pending')->where('soft_delete', 0)->update([
                    'soft_delete' => 1,
                    'comments' => '前一轮未闭合待签批记录，版本重新提交时关闭。',
                    'modified' => $now,
                    'modified_by' => (string)$user['id'],
                ]);
            Db::name('qms_responsibility_chain_versions')->where('id', $versionId)->update([
                'status' => 'pending_approval',
                'content_hash' => $hash,
                'locked_at' => $now,
                'modified' => $now,
                'modified_by' => (string)$user['id'],
            ]);
            if ($assignments !== []) {
                Db::name('qms_responsibility_assignments')
                    ->whereIn('id', array_column($assignments, 'id'))
                    ->where('status', 'draft')->update(['status' => 'pending_approval', 'modified' => $now, 'modified_by' => (string)$user['id']]);
            }
            if (!hash_equals($hash, QmsResponsibilityDraftService::contentHash($versionId))) {
                throw new DomainException('版本锁定前后内容哈希不一致。');
            }

            foreach ($routes as $route) {
                $batchKey = self::batchKey(
                    $companyId,
                    $versionId,
                    (string)$route['approver_employee_id'],
                    (string)$route['approver_position_code'],
                    $submissionRound
                );
                $existing = Db::name('qms_responsibility_approvals')
                    ->where('company_id', $companyId)->where('chain_version_id', $versionId)
                    ->where('assignment_id', (string)$route['assignment_id'])->where('decision', 'pending')->where('soft_delete', 0)
                    ->lock(true)->find();
                if ($existing) {
                    continue;
                }
                Db::name('qms_responsibility_approvals')->insert([
                    'id' => qms_uuid(),
                    'company_id' => $companyId,
                    'approval_scope' => 'assignment',
                    'chain_version_id' => $versionId,
                    'assignment_id' => (string)$route['assignment_id'],
                    'subject_employee_id' => (string)$route['subject_employee_id'],
                    'subject_position_id' => $route['subject_position_id'] ?: null,
                    'approver_employee_id' => (string)$route['approver_employee_id'],
                    'approver_position_code' => (string)$route['approver_position_code'],
                    'batch_key' => $batchKey,
                    'decision' => 'pending',
                    'version_hash' => $hash,
                    'signature_metadata' => self::json(['submission_round' => $submissionRound]),
                    'publish' => 1,
                    'soft_delete' => 0,
                    'created' => $now,
                    'modified' => $now,
                    'created_by' => (string)$user['id'],
                    'modified_by' => (string)$user['id'],
                ]);
            }

            return self::versionRow($versionId, $companyId, false);
        });
    }

    public static function pendingBatchForApprover(string $versionId, string $employeeId): array
    {
        $companyId = self::companyId();
        self::versionPending($versionId, $companyId);
        $rows = self::batchRows($versionId, $employeeId, $companyId, null, false);
        if ($rows === []) {
            throw new DomainException('未找到该人员的待签批批次。');
        }
        $batchKeys = array_values(array_unique(array_column($rows, 'batch_key')));
        if (count($batchKeys) !== 1) {
            throw new DomainException('同一签批人出现多个待签批批次，已失败关闭。');
        }

        return self::formatBatch($rows);
    }

    public static function approveBatch(string $batchKey, string $decision, string $comments): array
    {
        self::assertDecision($decision);
        $user = self::currentUser();
        $companyId = self::companyId();

        return Db::transaction(static function () use ($batchKey, $decision, $comments, $user, $companyId): array {
            self::lockCompany($companyId);
            self::assertCurrentUserStillActive($user, $companyId, true);
            $rows = self::batchRows('', (string)$user['employee_id'], $companyId, $batchKey, true);
            if ($rows === []) {
                throw new DomainException('待签批批次不存在、不属于当前人员或已处理。');
            }
            $versionId = (string)$rows[0]['chain_version_id'];
            $version = self::versionPending($versionId, $companyId, true);
            $approverCode = (string)$rows[0]['approver_position_code'];
            if (!in_array($approverCode, [self::GM_CODE, self::DIRECTOR_CODE], true)) {
                throw new DomainException('签批业务身份无效。');
            }
            if (self::uniqueBusinessOwner($approverCode, $companyId, true) !== (string)$user['employee_id']) {
                throw new DomainException('当前用户不再持有该唯一有效业务签批身份。');
            }
            foreach ($rows as $row) {
                if ((string)$row['approver_position_code'] !== $approverCode || (string)$row['chain_version_id'] !== $versionId) {
                    throw new DomainException('批次内容不一致，已失败关闭。');
                }
                if ((string)$row['subject_employee_id'] === (string)$user['employee_id']) {
                    throw new DomainException('不得签批自己的任命。');
                }
                if (!hash_equals((string)$version['content_hash'], (string)$row['version_hash'])) {
                    throw new DomainException('签批记录与锁定版本哈希不一致。');
                }
            }
            $submissionRound = trim((string)(self::decodeJson($rows[0]['signature_metadata'] ?? null)['submission_round'] ?? ''));
            if ($submissionRound === '') {
                throw new DomainException('签批批次缺少提交轮次标识。');
            }
            foreach ($rows as $row) {
                $rowRound = (string)(self::decodeJson($row['signature_metadata'] ?? null)['submission_round'] ?? '');
                if ($rowRound !== $submissionRound) {
                    throw new DomainException('同一签批批次混入了不同提交轮次。');
                }
            }
            if (!hash_equals((string)$version['content_hash'], QmsResponsibilityDraftService::contentHash($versionId))) {
                throw new DomainException('责任链内容已在签批期间被篡改。');
            }

            $now = date('Y-m-d H:i:s');
            $metadata = array_merge(
                ['submission_round' => $submissionRound],
                self::signatureMetadata($user, $approverCode, $decision)
            );
            Db::name('qms_responsibility_approvals')->whereIn('id', array_column($rows, 'id'))->update([
                'approver_user_id' => (string)$user['id'],
                'decision' => $decision,
                'comments' => trim($comments),
                'signature_metadata' => self::json($metadata),
                'signed_at' => $now,
                'modified' => $now,
                'modified_by' => (string)$user['id'],
            ]);

            if ($decision === 'rejected') {
                self::rejectVersion($versionId, $companyId, $batchKey, $user, $now);
                return ['batch_key' => $batchKey, 'version_id' => $versionId, 'decision' => 'rejected', 'version_status' => 'draft'];
            }

            $pending = (int)Db::name('qms_responsibility_approvals')
                ->where('company_id', $companyId)->where('chain_version_id', $versionId)
                ->where('approval_scope', 'assignment')->where('decision', 'pending')
                ->where('publish', 1)->where('soft_delete', 0)->lock(true)->count();
            if ($pending === 0) {
                self::activateVersion($version, $companyId, $user, $now, $submissionRound);
            }

            return [
                'batch_key' => $batchKey,
                'version_id' => $versionId,
                'decision' => 'approved',
                'version_status' => self::versionStatus($versionId),
            ];
        });
    }

    public static function versionStatus(string $versionId): string
    {
        return (string)self::versionRow($versionId, self::companyId(), false)['status'];
    }

    private static function createDirectorAppointment(array $approval, array $metadata, array $user, string $companyId): void
    {
        $employeeId = (string)$approval['subject_employee_id'];
        self::activeEmployeeWithUser($employeeId, $companyId, true);
        $position = Db::name('qms_positions')->where('id', (string)$approval['subject_position_id'])
            ->where('company_id', $companyId)->where('code', self::DIRECTOR_CODE)
            ->where('review_status', 'published')->where('publish', 1)->where('soft_delete', 0)->lock(true)->find();
        if (!$position) {
            throw new DomainException('实验室主任标准岗位已失效。');
        }
        $owners = self::activeRoleOwnersAnySource(self::DIRECTOR_CODE, $companyId, true);
        if ($owners !== [] && $owners !== [$employeeId]) {
            throw new DomainException('已存在其他有效实验室主任。');
        }
        $key = 'bootstrap:lab_director:' . $employeeId;
        $existing = Db::name('employee_appointments')->where('company_id', $companyId)->where('appointment_key', $key)->lock(true)->find();
        if ($existing && (string)$existing['source_approval_id'] !== (string)$approval['id']) {
            throw new DomainException('实验室主任任命稳定键冲突。');
        }
        if ($existing) {
            return;
        }
        $effectiveFrom = trim((string)($metadata['effective_from'] ?? ''));
        self::assertDate($effectiveFrom, '实验室主任任命生效日期');
        $now = date('Y-m-d H:i:s');
        Db::name('employee_appointments')->insert([
            'id' => qms_uuid(), 'company_id' => $companyId, 'employee_id' => $employeeId,
            'position_id' => (string)$position['id'], 'site_id' => null, 'appointment_key' => $key,
            'appointment_type' => 'role', 'position_name' => '实验室主任',
            'appointment_scope' => '由公司总经理签批的实验室主任任命。',
            'appointed_at' => $effectiveFrom, 'valid_until' => null, 'source_kind' => 'responsibility_chain',
            'source_approval_id' => (string)$approval['id'], 'status' => 'active', 'publish' => 1, 'soft_delete' => 0,
            'created' => $now, 'modified' => $now, 'created_by' => (string)$user['id'], 'modified_by' => (string)$user['id'],
        ]);
    }

    private static function approvalRoutes(array $assignments, string $companyId, bool $lock): array
    {
        $gmId = self::uniqueBusinessOwner(self::GM_CODE, $companyId, $lock);
        $directorId = self::uniqueBusinessOwner(self::DIRECTOR_CODE, $companyId, $lock);
        $routes = [];
        foreach ($assignments as $assignment) {
            $mode = (string)$assignment['assignment_mode'];
            $slotKind = (string)$assignment['slot_kind'];
            $positionCode = (string)($assignment['fixed_position_code'] ?? '');
            if ($mode === 'derived_from_scope' || $slotKind === 'dynamic_owner') {
                continue;
            }
            if ($positionCode === self::GM_CODE) {
                if ((string)$assignment['employee_id'] !== $gmId) {
                    throw new DomainException('公司总经理固定责任必须绑定当前唯一公司治理身份持有人。');
                }
                continue;
            }
            $approverId = $positionCode === self::DIRECTOR_CODE ? $gmId : $directorId;
            $approverCode = $positionCode === self::DIRECTOR_CODE ? self::GM_CODE : self::DIRECTOR_CODE;
            if ((string)$assignment['employee_id'] === $approverId) {
                throw new DomainException('任命对象与应签批人重合，不得自批。');
            }
            $routes[] = [
                'assignment_id' => (string)$assignment['id'],
                'subject_employee_id' => (string)$assignment['employee_id'],
                'subject_position_id' => (string)($assignment['fixed_position_id'] ?? ''),
                'approver_employee_id' => $approverId,
                'approver_position_code' => $approverCode,
            ];
        }
        if ($routes === []) {
            throw new DomainException('责任链没有可签批的实名任命。');
        }
        return $routes;
    }

    private static function assertApprovalEvidenceMatchesRoutes(
        array $approvals,
        array $expectedRoutes,
        string $versionHash,
        string $submissionRound,
        string $companyId
    ): void
    {
        if (count($approvals) !== count($expectedRoutes) || $approvals === []) {
            throw new DomainException('本轮签批证据数量与当前任命路由不一致。');
        }

        $approvalByAssignment = [];
        foreach ($approvals as $approval) {
            $assignmentId = trim((string)($approval['assignment_id'] ?? ''));
            if ($assignmentId === '' || isset($approvalByAssignment[$assignmentId])) {
                throw new DomainException('本轮签批证据缺少任命标识或存在重复任命。');
            }
            $approvalByAssignment[$assignmentId] = $approval;
        }

        $expectedByAssignment = [];
        foreach ($expectedRoutes as $route) {
            $assignmentId = (string)$route['assignment_id'];
            if ($assignmentId === '' || isset($expectedByAssignment[$assignmentId])) {
                throw new DomainException('当前任命路由存在缺失或重复任命。');
            }
            $expectedByAssignment[$assignmentId] = $route;
        }
        if (array_keys($approvalByAssignment) !== array_keys($expectedByAssignment)) {
            $actualIds = array_keys($approvalByAssignment);
            $expectedIds = array_keys($expectedByAssignment);
            sort($actualIds, SORT_STRING);
            sort($expectedIds, SORT_STRING);
            if ($actualIds !== $expectedIds) {
                throw new DomainException('本轮签批证据与当前任命集不一致。');
            }
        }

        foreach ($expectedByAssignment as $assignmentId => $route) {
            $approval = $approvalByAssignment[$assignmentId] ?? null;
            if (!$approval) {
                throw new DomainException('当前任命缺少本轮签批证据。');
            }
            $actualPositionId = trim((string)($approval['subject_position_id'] ?? ''));
            $expectedPositionId = trim((string)($route['subject_position_id'] ?? ''));
            if (
                (string)($approval['approval_scope'] ?? '') !== 'assignment'
                || (string)($approval['subject_employee_id'] ?? '') !== (string)$route['subject_employee_id']
                || $actualPositionId !== $expectedPositionId
                || (string)($approval['approver_employee_id'] ?? '') !== (string)$route['approver_employee_id']
                || (string)($approval['approver_position_code'] ?? '') !== (string)$route['approver_position_code']
                || (string)($approval['decision'] ?? '') !== 'approved'
                || !hash_equals($versionHash, (string)($approval['version_hash'] ?? ''))
            ) {
                throw new DomainException('本轮签批证据的对象、岗位、批准主体或版本哈希与当前路由不一致。');
            }

            $approverUserId = trim((string)($approval['approver_user_id'] ?? ''));
            $signedAt = trim((string)($approval['signed_at'] ?? ''));
            $metadata = self::decodeJson($approval['signature_metadata'] ?? null);
            foreach ([
                'submission_round', 'user_id', 'employee_id', 'session_id', 'user_role',
                'approved_as', 'approver_position_code', 'decision', 'signed_at',
            ] as $key) {
                if (trim((string)($metadata[$key] ?? '')) === '') {
                    throw new DomainException('签批签名元数据不完整：' . $key);
                }
            }
            if (
                $approverUserId === ''
                || $signedAt === ''
                || (string)$metadata['submission_round'] !== $submissionRound
                || (string)$metadata['user_id'] !== $approverUserId
                || (string)$metadata['employee_id'] !== (string)$route['approver_employee_id']
                || (string)$metadata['approved_as'] !== (string)$route['approver_position_code']
                || (string)$metadata['approver_position_code'] !== (string)$route['approver_position_code']
                || (string)$metadata['decision'] !== 'approved'
                || date('Y-m-d H:i:s', strtotime((string)$metadata['signed_at'])) !== $signedAt
            ) {
                throw new DomainException('签批人、业务身份、决定、轮次或签名时间证据不一致。');
            }

            $approverUser = Db::name('users')
                ->where('id', $approverUserId)
                ->where('company_id', $companyId)
                ->where('employee_id', (string)$route['approver_employee_id'])
                ->where('publish', 1)
                ->where('soft_delete', 0)
                ->lock(true)
                ->find();
            if (!$approverUser || (string)$approverUser['role'] !== (string)$metadata['user_role']) {
                throw new DomainException('签批用户已失效或签名角色与当前用户不一致。');
            }
            self::assertSessionValidAtSignature(
                $approverUserId,
                (string)$metadata['session_id'],
                $signedAt,
                true
            );
        }
    }

    private static function rejectVersion(string $versionId, string $companyId, string $batchKey, array $user, string $now): void
    {
        Db::name('qms_responsibility_approvals')
            ->where('company_id', $companyId)->where('chain_version_id', $versionId)
            ->where('decision', 'pending')->where('soft_delete', 0)
            ->where('batch_key', '<>', $batchKey)->update([
                'soft_delete' => 1,
                'comments' => '因其他批次拒绝而关闭。',
                'modified' => $now,
                'modified_by' => (string)$user['id'],
            ]);
        $assignmentIds = self::versionAssignmentIds($versionId, $companyId);
        if ($assignmentIds !== []) {
            Db::name('qms_responsibility_assignments')->whereIn('id', $assignmentIds)->where('status', 'pending_approval')->update([
                'status' => 'draft', 'modified' => $now, 'modified_by' => (string)$user['id'],
            ]);
        }
        Db::name('qms_responsibility_chain_versions')->where('id', $versionId)->where('company_id', $companyId)->update([
            'status' => 'draft', 'content_hash' => null, 'locked_at' => null,
            'modified' => $now, 'modified_by' => (string)$user['id'],
        ]);
    }

    private static function activateVersion(
        array $lockedVersion,
        string $companyId,
        array $user,
        string $now,
        string $submissionRound
    ): void
    {
        $versionId = (string)$lockedVersion['id'];
        $versions = Db::name('qms_responsibility_chain_versions')
            ->where('company_id', $companyId)->where('chain_code', (string)$lockedVersion['chain_code'])
            ->where('soft_delete', 0)->order('version_no,id')->lock(true)->select()->toArray();
        $effective = array_values(array_filter($versions, static fn (array $row): bool => (string)$row['status'] === 'effective'));
        if (count($effective) > 1) {
            throw new DomainException('同一责任链存在多个有效版本，已失败关闭。');
        }
        $currentHash = QmsResponsibilityDraftService::contentHash($versionId);
        if (!hash_equals((string)$lockedVersion['content_hash'], $currentHash)) {
            throw new DomainException('最终生效前版本内容哈希不一致。');
        }
        $assignments = self::versionAssignments($versionId, $companyId, true, ['pending_approval']);
        self::lockActivationDependencies($assignments, $companyId);
        $validation = QmsResponsibilityValidationService::validateVersion($versionId, 'activation');
        if (($validation['result'] ?? '') !== 'pass') {
            throw new DomainException('最终生效前责任链激活校验未通过。');
        }
        $expectedRoutes = self::approvalRoutes($assignments, $companyId, true);
        $approvalHistory = Db::name('qms_responsibility_approvals')
            ->where('company_id', $companyId)->where('chain_version_id', $versionId)
            ->where('approval_scope', 'assignment')->where('soft_delete', 0)->lock(true)->select()->toArray();
        $approvals = array_values(array_filter(
            $approvalHistory,
            static fn (array $row): bool =>
                (string)(self::decodeJson($row['signature_metadata'] ?? null)['submission_round'] ?? '') === $submissionRound
        ));
        self::assertApprovalEvidenceMatchesRoutes(
            $approvals,
            $expectedRoutes,
            (string)$lockedVersion['content_hash'],
            $submissionRound,
            $companyId
        );
        $appointmentGroups = self::appointmentGroups($versionId, $assignments, $approvals);
        try {
            foreach ($appointmentGroups as $group) {
                Db::name('employee_appointments')->insert([
                'id' => qms_uuid(),
                'company_id' => $companyId,
                'employee_id' => (string)$group['employee_id'],
                'position_id' => $group['position_id'] ?: null,
                'site_id' => $group['site_id'] ?: null,
                'appointment_key' => (string)$group['appointment_key'],
                'appointment_type' => (string)$group['appointment_type'],
                'position_name' => (string)$group['position_name'],
                'appointment_scope' => self::json($group['scope']),
                'appointed_at' => (string)$group['appointed_at'],
                'valid_until' => $group['valid_until'] ?: null,
                'source_kind' => 'responsibility_chain',
                'source_chain_version_id' => $versionId,
                'source_responsibility_id' => (string)$group['source_responsibility_id'],
                'source_approval_id' => (string)$group['source_approval_id'],
                'status' => 'active',
                'publish' => 1,
                'soft_delete' => 0,
                'created' => $now,
                'modified' => $now,
                'created_by' => (string)$user['id'],
                'modified_by' => (string)$user['id'],
                ]);
            }
        } catch (Throwable $error) {
            throw new DomainException('生成责任链任命时发生稳定键冲突，本次生效已整体回滚。', 0, $error);
        }

        if ($effective !== []) {
            $oldId = (string)$effective[0]['id'];
            if ($oldId !== $versionId) {
                Db::name('qms_responsibility_chain_versions')->where('id', $oldId)->update([
                    'status' => 'superseded', 'superseded_at' => $now, 'modified' => $now, 'modified_by' => (string)$user['id'],
                ]);
                $oldAssignmentIds = self::versionAssignmentIds($oldId, $companyId);
                if ($oldAssignmentIds !== []) {
                    Db::name('qms_responsibility_assignments')->whereIn('id', $oldAssignmentIds)->where('status', 'active')->update([
                        'status' => 'revoked', 'modified' => $now, 'modified_by' => (string)$user['id'],
                    ]);
                }
                Db::name('employee_appointments')->where('company_id', $companyId)
                    ->where('source_kind', 'responsibility_chain')->where('source_chain_version_id', $oldId)
                    ->where('status', 'active')->update(['status' => 'revoked', 'modified' => $now, 'modified_by' => (string)$user['id']]);
            }
        }

        $assignmentIds = array_column($assignments, 'id');
        if ($assignmentIds !== []) {
            Db::name('qms_responsibility_assignments')->whereIn('id', $assignmentIds)->where('status', 'pending_approval')->update([
                'status' => 'active', 'modified' => $now, 'modified_by' => (string)$user['id'],
            ]);
        }
        Db::name('qms_responsibility_chain_versions')->where('id', $versionId)->where('company_id', $companyId)->update([
            'status' => 'effective', 'effective_at' => $now, 'modified' => $now, 'modified_by' => (string)$user['id'],
        ]);
        $newEffectiveCount = (int)Db::name('qms_responsibility_chain_versions')
            ->where('company_id', $companyId)->where('chain_code', (string)$lockedVersion['chain_code'])
            ->where('status', 'effective')->where('soft_delete', 0)->lock(true)->count();
        if ($newEffectiveCount !== 1 || !hash_equals((string)$lockedVersion['content_hash'], QmsResponsibilityDraftService::contentHash($versionId))) {
            throw new DomainException('新责任链版本生效后唯一性或内容等价性校验失败。');
        }
    }

    private static function appointmentGroups(string $versionId, array $assignments, array $approvals): array
    {
        $approvalByAssignment = [];
        foreach ($approvals as $approval) {
            $approvalByAssignment[(string)$approval['assignment_id']] = $approval;
        }
        $groups = [];
        foreach ($assignments as $assignment) {
            $mode = (string)$assignment['assignment_mode'];
            $slotKind = (string)$assignment['slot_kind'];
            $positionCode = (string)($assignment['fixed_position_code'] ?? '');
            if ($mode === 'derived_from_scope' || $slotKind === 'dynamic_owner' || $positionCode === self::GM_CODE) {
                continue;
            }
            $approval = $approvalByAssignment[(string)$assignment['id']] ?? null;
            if (!$approval) {
                throw new DomainException('任命缺少已批准的签批记录。');
            }
            if ($slotKind === 'fixed_position') {
                $groupKey = implode('|', ['role', $assignment['employee_id'], $assignment['fixed_position_id'], $assignment['site_scope_key']]);
                if (!isset($groups[$groupKey])) {
                    $groups[$groupKey] = [
                        'appointment_key' => 'rc:' . $versionId . ':role:' . $assignment['employee_id'] . ':' . $assignment['fixed_position_id'] . ':' . $assignment['site_scope_key'],
                        'appointment_type' => 'role',
                        'employee_id' => (string)$assignment['employee_id'],
                        'position_id' => (string)$assignment['fixed_position_id'],
                        'site_id' => (string)($assignment['site_id'] ?? ''),
                        'position_name' => (string)($assignment['fixed_position_name'] ?? $positionCode),
                        'appointed_at' => (string)$assignment['proposed_from'],
                        'valid_until' => (string)($assignment['proposed_until'] ?? ''),
                        'source_responsibility_id' => (string)$assignment['responsibility_id'],
                        'source_approval_id' => (string)$approval['id'],
                        'scope' => ['responsibility_ids' => [], 'step_codes' => [], 'approval_ids' => []],
                    ];
                }
                $groups[$groupKey]['scope']['responsibility_ids'][] = (string)$assignment['responsibility_id'];
                $groups[$groupKey]['scope']['step_codes'][] = (string)$assignment['step_code'];
                $groups[$groupKey]['scope']['approval_ids'][] = (string)$approval['id'];
                if ((string)$assignment['proposed_from'] < (string)$groups[$groupKey]['appointed_at']) {
                    $groups[$groupKey]['appointed_at'] = (string)$assignment['proposed_from'];
                }
                $until = (string)($assignment['proposed_until'] ?? '');
                if ($until === '' || (string)$groups[$groupKey]['valid_until'] === '') {
                    $groups[$groupKey]['valid_until'] = '';
                } elseif ($until > (string)$groups[$groupKey]['valid_until']) {
                    $groups[$groupKey]['valid_until'] = $until;
                }
            } elseif ($slotKind === 'activity_role') {
                $groupKey = 'responsibility|' . $assignment['id'];
                $groups[$groupKey] = [
                    'appointment_key' => 'rc:' . $versionId . ':responsibility:' . $assignment['id'],
                    'appointment_type' => 'responsibility',
                    'employee_id' => (string)$assignment['employee_id'],
                    'position_id' => '',
                    'site_id' => (string)($assignment['site_id'] ?? ''),
                    'position_name' => (string)($assignment['activity_role_code'] ?? '活动角色'),
                    'appointed_at' => (string)$assignment['proposed_from'],
                    'valid_until' => (string)($assignment['proposed_until'] ?? ''),
                    'source_responsibility_id' => (string)$assignment['responsibility_id'],
                    'source_approval_id' => (string)$approval['id'],
                    'scope' => [
                        'responsibility_ids' => [(string)$assignment['responsibility_id']],
                        'step_codes' => [(string)$assignment['step_code']],
                        'approval_ids' => [(string)$approval['id']],
                        'activity_role_code' => (string)$assignment['activity_role_code'],
                    ],
                ];
            }
        }
        foreach ($groups as &$group) {
            sort($group['scope']['responsibility_ids'], SORT_STRING);
            sort($group['scope']['step_codes'], SORT_STRING);
            sort($group['scope']['approval_ids'], SORT_STRING);
        }
        unset($group);
        uasort($groups, static fn (array $left, array $right): int => (string)$left['appointment_key'] <=> (string)$right['appointment_key']);
        return array_values($groups);
    }

    private static function lockActivationDependencies(array $assignments, string $companyId): void
    {
        $siteIds = [];
        $competencyIds = [];
        $certificateIds = [];
        $positionIds = [];
        foreach ($assignments as $assignment) {
            $siteIds[] = (string)($assignment['site_id'] ?? '');
            $positionIds[] = (string)($assignment['fixed_position_id'] ?? '');
            $snapshot = self::decodeJson($assignment['competence_snapshot'] ?? null);
            foreach ((array)($snapshot['competency_record_ids'] ?? []) as $id) {
                if (is_scalar($id)) {
                    $competencyIds[] = (string)$id;
                }
            }
            foreach ((array)($snapshot['certificate_ids'] ?? []) as $id) {
                if (is_scalar($id)) {
                    $certificateIds[] = (string)$id;
                }
            }
        }

        $ownerPositionIds = Db::name('qms_positions')
            ->where('company_id', $companyId)
            ->whereIn('code', [self::GM_CODE, self::DIRECTOR_CODE])
            ->column('id');
        $positionIds = self::sortedIds(array_merge($positionIds, array_map('strval', $ownerPositionIds)));

        // Fixed lock order prevents evidence updates from racing the final validation:
        // employees -> sites -> competency -> certificates -> positions -> owner appointments.
        // All company employees are locked because approval-owner candidates can change independently
        // from assignment rows; activation is rare and must prefer correctness over lock breadth.
        Db::name('employees')
            ->where('company_id', $companyId)
            ->order('id')
            ->lock(true)
            ->select();
        self::lockRowsByIds('sites', self::sortedIds($siteIds), $companyId);
        self::lockRowsByIds('competency_records', self::sortedIds($competencyIds), $companyId);
        self::lockRowsByIds('employee_certificates', self::sortedIds($certificateIds), $companyId);
        self::lockRowsByIds('qms_positions', $positionIds, $companyId);
        if ($positionIds !== []) {
            Db::name('employee_appointments')
                ->where('company_id', $companyId)
                ->whereIn('position_id', $positionIds)
                ->order('id')
                ->lock(true)
                ->select();
        }
    }

    private static function lockRowsByIds(string $table, array $ids, string $companyId): void
    {
        if ($ids === []) {
            return;
        }
        Db::name($table)
            ->where('company_id', $companyId)
            ->whereIn('id', $ids)
            ->order('id')
            ->lock(true)
            ->select();
    }

    private static function sortedIds(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map(
            static fn (mixed $id): string => trim((string)$id),
            $ids
        ), static fn (string $id): bool => $id !== '')));
        sort($ids, SORT_STRING);
        return $ids;
    }

    private static function versionAssignments(string $versionId, string $companyId, bool $lock, array $statuses): array
    {
        $query = Db::name('qms_responsibility_assignments')->alias('ra')
            ->join('qms_activity_responsibilities r', 'r.id=ra.responsibility_id AND r.company_id=ra.company_id')
            ->join('qms_responsibility_activities a', 'a.id=r.activity_id AND a.company_id=r.company_id')
            ->leftJoin('qms_positions p', 'p.id=r.fixed_position_id AND p.company_id=r.company_id')
            ->where('a.chain_version_id', $versionId)->where('ra.company_id', $companyId)
            ->where('ra.publish', 1)->where('ra.soft_delete', 0)->whereIn('ra.status', $statuses)
            ->where('r.publish', 1)->where('r.soft_delete', 0)->where('a.publish', 1)->where('a.soft_delete', 0)
            ->field('ra.*,r.slot_kind,r.assignment_mode,r.fixed_position_id,r.activity_role_code,r.dynamic_owner_code,r.step_code,p.code fixed_position_code,p.name fixed_position_name')
            ->order('a.sort_order,r.sort_order,ra.employee_id,ra.site_scope_key,ra.id');
        if ($lock) {
            $query->lock(true);
        }
        return $query->select()->toArray();
    }

    private static function versionAssignmentIds(string $versionId, string $companyId): array
    {
        return array_map('strval', Db::name('qms_responsibility_assignments')->alias('ra')
            ->join('qms_activity_responsibilities r', 'r.id=ra.responsibility_id AND r.company_id=ra.company_id')
            ->join('qms_responsibility_activities a', 'a.id=r.activity_id AND a.company_id=r.company_id')
            ->where('a.chain_version_id', $versionId)->where('ra.company_id', $companyId)->column('ra.id'));
    }

    private static function batchRows(string $versionId, string $employeeId, string $companyId, ?string $batchKey, bool $lock): array
    {
        $query = Db::name('qms_responsibility_approvals')->alias('ap')
            ->join('qms_responsibility_assignments ra', 'ra.id=ap.assignment_id AND ra.company_id=ap.company_id')
            ->join('qms_activity_responsibilities r', 'r.id=ra.responsibility_id AND r.company_id=ra.company_id')
            ->join('qms_responsibility_activities a', 'a.id=r.activity_id AND a.company_id=r.company_id')
            ->join('employees e', 'e.id=ra.employee_id AND e.company_id=ra.company_id')
            ->leftJoin('qms_positions p', 'p.id=r.fixed_position_id AND p.company_id=r.company_id')
            ->where('ap.company_id', $companyId)->where('ap.approval_scope', 'assignment')
            ->where('ap.approver_employee_id', $employeeId)->where('ap.decision', 'pending')
            ->where('ap.publish', 1)->where('ap.soft_delete', 0)
            ->field('ap.*,ra.responsibility_id,ra.competence_snapshot,ra.proposed_from,ra.proposed_until,e.name employee_name,e.employee_number,r.step_code,r.duty_text,r.slot_kind,r.assignment_mode,r.activity_role_code,p.code fixed_position_code,p.name fixed_position_name,a.activity_code,a.name activity_name')
            ->order('ap.batch_key,a.sort_order,r.sort_order,ap.id');
        if ($versionId !== '') {
            $query->where('ap.chain_version_id', $versionId);
        }
        if ($batchKey !== null) {
            $query->where('ap.batch_key', $batchKey);
        }
        if ($lock) {
            $query->lock(true);
        }
        return $query->select()->toArray();
    }

    private static function formatBatch(array $rows): array
    {
        $positionCodes = [];
        $items = [];
        foreach ($rows as $row) {
            $code = trim((string)($row['fixed_position_code'] ?? ''));
            if ($code === '') {
                $code = trim((string)($row['activity_role_code'] ?? ''));
            }
            if ($code !== '') {
                $positionCodes[$code] = true;
            }
            $items[] = [
                'approval_id' => (string)$row['id'],
                'assignment_id' => (string)$row['assignment_id'],
                'responsibility_id' => (string)$row['responsibility_id'],
                'activity_code' => (string)$row['activity_code'],
                'step_code' => (string)$row['step_code'],
                'duty_text' => (string)$row['duty_text'],
                'employee_id' => (string)$row['subject_employee_id'],
                'employee_name' => (string)$row['employee_name'],
                'employee_number' => (string)$row['employee_number'],
                'position_code' => $code,
                'position_name' => (string)($row['fixed_position_name'] ?? $row['activity_role_code'] ?? ''),
                'competence_snapshot' => self::decodeJson($row['competence_snapshot'] ?? null),
                'version_hash' => (string)$row['version_hash'],
            ];
        }
        $positionCodes = array_keys($positionCodes);
        sort($positionCodes, SORT_STRING);
        return [
            'batch_key' => (string)$rows[0]['batch_key'],
            'version_id' => (string)$rows[0]['chain_version_id'],
            'approver' => [
                'employee_id' => (string)$rows[0]['approver_employee_id'],
                'position_code' => (string)$rows[0]['approver_position_code'],
            ],
            'subject_position_codes' => $positionCodes,
            'items' => $items,
        ];
    }

    private static function businessOwners(string $positionCode, string $companyId, bool $lock): array
    {
        if ($positionCode === self::GM_CODE) {
            $query = self::activeRoleOwnerQuery($positionCode, $companyId)
                ->where('ea.source_kind', 'corporate_evidence');
            return self::ownerIds($query, $lock);
        }

        if ($positionCode === self::DIRECTOR_CODE) {
            $effectiveQuery = self::activeRoleOwnerQuery($positionCode, $companyId)
                ->join(
                    'qms_responsibility_chain_versions v',
                    'v.id=ea.source_chain_version_id AND v.company_id=ea.company_id'
                )
                ->where('ea.source_kind', 'responsibility_chain')
                ->where('v.status', 'effective')
                ->where('v.publish', 1)
                ->where('v.soft_delete', 0);
            $effectiveOwners = self::ownerIds($effectiveQuery, $lock);
            if (self::hasEffectiveResponsibilityChain($companyId, $lock)) {
                return $effectiveOwners;
            }

            $bootstrapQuery = self::activeRoleOwnerQuery($positionCode, $companyId)
                ->where('ea.source_kind', 'responsibility_chain')
                ->whereNull('ea.source_chain_version_id');
            return self::ownerIds($bootstrapQuery, $lock);
        }

        return [];
    }

    private static function hasEffectiveResponsibilityChain(string $companyId, bool $lock): bool
    {
        $query = Db::name('qms_responsibility_chain_versions')
            ->where('company_id', $companyId)
            ->where('status', 'effective')
            ->where('publish', 1)
            ->where('soft_delete', 0)
            ->order('id');
        if ($lock) {
            $query->lock(true);
        }

        return (bool)$query->field('id')->find();
    }

    private static function ownerIds(mixed $query, bool $lock): array
    {
        if ($lock) {
            $query->lock(true);
        }
        $ids = array_values(array_unique(array_map(
            'strval',
            $query->order('ea.employee_id,ea.id')->column('ea.employee_id')
        )));
        sort($ids, SORT_STRING);
        return $ids;
    }

    private static function activeRoleOwnersAnySource(string $positionCode, string $companyId, bool $lock): array
    {
        $query = self::activeRoleOwnerQuery($positionCode, $companyId);
        if ($lock) {
            $query->lock(true);
        }
        $ids = array_values(array_unique(array_map('strval', $query->order('ea.employee_id,ea.id')->column('ea.employee_id'))));
        sort($ids, SORT_STRING);
        return $ids;
    }

    private static function activeRoleOwnerQuery(string $positionCode, string $companyId): mixed
    {
        $today = date('Y-m-d');
        $query = Db::name('employee_appointments')->alias('ea')
            ->join('employees e', 'e.id=ea.employee_id AND e.company_id=ea.company_id')
            ->join('qms_positions p', 'p.id=ea.position_id AND p.company_id=ea.company_id')
            ->where('ea.company_id', $companyId)->where('ea.appointment_type', 'role')
            ->where('ea.status', 'active')->where('ea.publish', 1)->where('ea.soft_delete', 0)
            ->whereNotNull('ea.appointed_at')->where('ea.appointed_at', '<=', $today)
            ->where(static function ($query) use ($today): void { $query->whereNull('ea.valid_until')->whereOr('ea.valid_until', '>=', $today); })
            ->where('e.publish', 1)->where('e.soft_delete', 0)
            ->where('p.code', $positionCode)
            ->where('p.review_status', 'published')->where('p.publish', 1)->where('p.soft_delete', 0);
        return $query;
    }

    private static function uniqueBusinessOwner(string $positionCode, string $companyId, bool $lock): string
    {
        $owners = self::businessOwners($positionCode, $companyId, $lock);
        if (count($owners) !== 1) {
            throw new DomainException($positionCode === self::GM_CODE ? '必须有且仅有一名有效公司总经理。' : '必须有且仅有一名有效实验室主任。');
        }
        self::activeEmployeeWithUser($owners[0], $companyId, $lock);
        return $owners[0];
    }

    private static function activeEmployeeWithUser(string $employeeId, string $companyId, bool $lock): array
    {
        $employeeQuery = Db::name('employees')->where('id', $employeeId)->where('company_id', $companyId)
            ->where('publish', 1)->where('soft_delete', 0);
        if ($lock) { $employeeQuery->lock(true); }
        $employee = $employeeQuery->find();
        if (!$employee) {
            throw new DomainException('人员不存在、未发布或不属于当前公司。');
        }
        $number = trim((string)($employee['employee_number'] ?? ''));
        $matching = Db::name('employees')->where('company_id', $companyId)->where('employee_number', $number);
        if ($lock) { $matching->lock(true); }
        $ids = array_map('strval', $matching->column('id'));
        if ($number === '' || count($ids) !== 1 || $ids[0] !== $employeeId) {
            throw new DomainException('员工编号必须在当前公司全部历史人员中唯一。');
        }
        $userQuery = Db::name('users')->where('company_id', $companyId)->where('employee_id', $employeeId)
            ->where('publish', 1)->where('soft_delete', 0);
        if ($lock) { $userQuery->lock(true); }
        $users = $userQuery->select()->toArray();
        if (count($users) !== 1) {
            throw new DomainException('人员必须唯一映射一个有效登录用户。');
        }
        return ['employee' => $employee, 'user' => $users[0]];
    }

    private static function currentUser(array $roles = []): array
    {
        $session = Session::get('user');
        if (!is_array($session)) {
            throw new DomainException('未登录或会话无效。');
        }
        foreach (['id', 'employee_id', 'role', 'session_id'] as $field) {
            if (trim((string)($session[$field] ?? '')) === '') {
                throw new DomainException('当前会话缺少必要身份字段。');
            }
        }
        $companyId = self::companyId();
        $user = Db::name('users')->where('id', (string)$session['id'])->where('company_id', $companyId)
            ->where('publish', 1)->where('soft_delete', 0)->find();
        if (!$user || (string)$user['employee_id'] !== (string)$session['employee_id'] || (string)$user['role'] !== (string)$session['role']) {
            throw new DomainException('会话身份与当前有效用户映射不一致。');
        }
        self::activeEmployeeWithUser((string)$session['employee_id'], $companyId, false);
        self::assertPersistedSession((string)$user['id'], (string)$session['session_id'], false);
        if ($roles !== [] && !in_array((string)$user['role'], $roles, true)) {
            throw new DomainException('当前用户角色无权执行该操作。');
        }
        return array_merge($user, ['session_id' => (string)$session['session_id']]);
    }

    private static function assertCurrentUserStillActive(array $expectedUser, string $companyId, bool $lock): void
    {
        $mapping = self::activeEmployeeWithUser((string)$expectedUser['employee_id'], $companyId, $lock);
        $current = (array)$mapping['user'];
        if (
            (string)$current['id'] !== (string)$expectedUser['id']
            || (string)$current['role'] !== (string)$expectedUser['role']
            || (string)$current['employee_id'] !== (string)$expectedUser['employee_id']
        ) {
            throw new DomainException('写入前用户身份已变更或与会话不一致。');
        }
        self::assertPersistedSession((string)$current['id'], (string)$expectedUser['session_id'], $lock);
    }

    private static function assertPersistedSession(string $userId, string $sessionId, bool $lock): void
    {
        $query = Db::name('user_sessions')
            ->where('id', $sessionId)
            ->where('user_id', $userId)
            ->whereNull('end_time');
        if ($lock) {
            $query->lock(true);
        }
        if (!$query->find()) {
            throw new DomainException('当前会话不存在、已结束或不属于当前用户。');
        }
    }

    private static function assertSessionValidAtSignature(
        string $userId,
        string $sessionId,
        string $signedAt,
        bool $lock
    ): void
    {
        $query = Db::name('user_sessions')
            ->where('id', $sessionId)
            ->where('user_id', $userId);
        if ($lock) {
            $query->lock(true);
        }
        $session = $query->find();
        $startTime = trim((string)($session['start_time'] ?? ''));
        $endTime = trim((string)($session['end_time'] ?? ''));
        if (
            !$session
            || $startTime === ''
            || $startTime > $signedAt
            || ($endTime !== '' && $endTime < $signedAt)
        ) {
            throw new DomainException('历史签名时会话不存在、不属于批准人或在签名时无效。');
        }
    }

    private static function signatureMetadata(array $user, string $positionCode, string $decision): array
    {
        return [
            'user_id' => (string)$user['id'],
            'employee_id' => (string)$user['employee_id'],
            'session_id' => (string)$user['session_id'],
            'user_role' => (string)$user['role'],
            'approved_as' => $positionCode,
            'approver_position_code' => $positionCode,
            'decision' => $decision,
            'signed_at' => date(DATE_ATOM),
        ];
    }

    private static function assertPublishedPosition(?array $position, string $code, string $companyId): void
    {
        if (!$position || (string)($position['code'] ?? '') !== $code) {
            throw new DomainException('标准岗位目录不完整。');
        }
        $row = Db::name('qms_positions')->where('id', (string)$position['id'])->where('company_id', $companyId)
            ->where('code', $code)->where('review_status', 'published')->where('publish', 1)->where('soft_delete', 0)->find();
        if (!$row) { throw new DomainException('标准岗位未发布或已失效。'); }
    }

    private static function versionPending(string $versionId, string $companyId, bool $lock = false): array
    {
        $version = self::versionRow($versionId, $companyId, $lock);
        if ((string)$version['status'] !== 'pending_approval' || strlen((string)$version['content_hash']) !== 64) {
            throw new DomainException('责任链版本不在有效待签批状态。');
        }
        return $version;
    }

    private static function versionRow(string $versionId, string $companyId, bool $lock): array
    {
        $query = Db::name('qms_responsibility_chain_versions')->where('id', $versionId)->where('company_id', $companyId)
            ->where('publish', 1)->where('soft_delete', 0);
        if ($lock) { $query->lock(true); }
        $row = $query->find();
        if (!$row) { throw new DomainException('责任链版本不存在或不属于当前公司。'); }
        return $row;
    }

    private static function approvalRow(string $approvalId, string $companyId): array
    {
        $row = Db::name('qms_responsibility_approvals')->where('id', $approvalId)->where('company_id', $companyId)->find();
        if (!$row) { throw new DomainException('签批记录保存失败。'); }
        return $row;
    }

    private static function appointmentRow(string $appointmentId, string $companyId): array
    {
        $row = Db::name('employee_appointments')->where('id', $appointmentId)->where('company_id', $companyId)->find();
        if (!$row) { throw new DomainException('任命记录保存失败。'); }
        return $row;
    }

    private static function lockCompany(string $companyId): void
    {
        $company = Db::name('companies')->where('id', $companyId)->where('soft_delete', 0)->lock(true)->find();
        if (!$company) { throw new DomainException('当前公司不存在或已删除。'); }
    }

    private static function batchKey(
        string $companyId,
        string $versionId,
        string $employeeId,
        string $positionCode,
        string $submissionRound
    ): string
    {
        return hash('sha256', implode('|', [
            'responsibility', $companyId, $versionId, $employeeId, $positionCode, $submissionRound,
        ]));
    }

    private static function assertDecision(string $decision): void
    {
        if (!in_array($decision, ['approved', 'rejected'], true)) {
            throw new DomainException('签批决定仅允许 approved 或 rejected。');
        }
    }

    private static function assertDate(string $value, string $label): void
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        $errors = DateTimeImmutable::getLastErrors();
        if (!$date || $date->format('Y-m-d') !== $value || (is_array($errors) && ((int)$errors['warning_count'] > 0 || (int)$errors['error_count'] > 0))) {
            throw new DomainException($label . '必须为有效的 YYYY-MM-DD 日期。');
        }
    }

    private static function decodeJson(mixed $value): array
    {
        if (is_array($value)) { return $value; }
        if (!is_string($value) || trim($value) === '') { return []; }
        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : [];
    }

    private static function json(array $value): string
    {
        return (string)json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    private static function companyId(): string
    {
        $companyId = trim((string)Config::get('qms.company_id'));
        if ($companyId === '') { throw new DomainException('未配置当前公司。'); }
        return $companyId;
    }
}
