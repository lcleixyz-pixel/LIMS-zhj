<?php
declare(strict_types=1);

namespace app\service;

use DomainException;
use think\facade\Config;
use think\facade\Db;

class QmsResponsibilityCatalogService
{
    private const CHAIN_CODE = 'core_governance';

    public static function createInitialDraft(): array
    {
        return Db::transaction(static fn (): array => self::createInitialDraftInTransaction());
    }

    private static function createInitialDraftInTransaction(): array
    {
        $positions = QmsPositionAliasService::seedCatalog();
        $companyId = (string)Config::get('qms.company_id');

        $effective = Db::name('qms_responsibility_chain_versions')
            ->where('company_id', $companyId)
            ->where('chain_code', self::CHAIN_CODE)
            ->where('status', 'effective')
            ->where('soft_delete', 0)
            ->find();
        if ($effective) {
            throw new DomainException('核心治理责任链已有有效版本，不得重新创建 v1 草案。');
        }

        $existing = Db::name('qms_responsibility_chain_versions')
            ->where('company_id', $companyId)
            ->where('chain_code', self::CHAIN_CODE)
            ->where('version_no', 1)
            ->where('soft_delete', 0)
            ->find();
        if ($existing) {
            return self::versionSummary((string)$existing['id']);
        }

        $now = date('Y-m-d H:i:s');
        $versionId = qms_uuid();
        Db::name('qms_responsibility_chain_versions')->insert([
            'id' => $versionId,
            'company_id' => $companyId,
            'chain_code' => self::CHAIN_CODE,
            'version_no' => 1,
            'status' => 'draft',
            'publish' => 1,
            'soft_delete' => 0,
            'created' => $now,
            'modified' => $now,
        ]);

        foreach (self::activityDefinitions() as $activityDefinition) {
            $activityId = qms_uuid();
            Db::name('qms_responsibility_activities')->insert([
                'id' => $activityId,
                'company_id' => $companyId,
                'chain_version_id' => $versionId,
                'activity_code' => (string)$activityDefinition['activity_code'],
                'name' => (string)$activityDefinition['name'],
                'element_key' => (string)$activityDefinition['element_key'],
                'site_scope' => 'all',
                'source_refs' => self::encodeJson($activityDefinition['source_refs']),
                'sort_order' => (int)$activityDefinition['sort_order'],
                'publish' => 1,
                'soft_delete' => 0,
                'created' => $now,
                'modified' => $now,
            ]);

            foreach ((array)$activityDefinition['responsibilities'] as $responsibility) {
                $slotKind = (string)$responsibility['slot_kind'];
                $positionCode = (string)($responsibility['position_code'] ?? '');
                $fixedPositionId = null;
                if ($slotKind === 'fixed_position') {
                    $fixedPositionId = (string)($positions[$positionCode]['id'] ?? '');
                    if ($fixedPositionId === '') {
                        throw new DomainException('责任模板引用了未登记的岗位：' . $positionCode);
                    }
                }

                Db::name('qms_activity_responsibilities')->insert([
                    'id' => qms_uuid(),
                    'company_id' => $companyId,
                    'activity_id' => $activityId,
                    'step_code' => (string)$responsibility['step_code'],
                    'duty_type' => (string)$responsibility['duty_type'],
                    'duty_text' => (string)$responsibility['duty_text'],
                    'slot_kind' => $slotKind,
                    'assignment_mode' => (string)$responsibility['assignment_mode'],
                    'fixed_position_id' => $fixedPositionId,
                    'activity_role_code' => $slotKind === 'activity_role' ? (string)$responsibility['slot_code'] : null,
                    'dynamic_owner_code' => $slotKind === 'dynamic_owner' ? (string)$responsibility['slot_code'] : null,
                    'required' => (int)$responsibility['required'],
                    'eligibility_rule' => self::encodeJson($responsibility['eligibility_rule']),
                    'rule_codes' => self::encodeJson($responsibility['rule_codes']),
                    'source_refs' => self::encodeJson($responsibility['source_refs']),
                    'sort_order' => (int)$responsibility['sort_order'],
                    'publish' => 1,
                    'soft_delete' => 0,
                    'created' => $now,
                    'modified' => $now,
                ]);
            }
        }

        return self::versionSummary($versionId);
    }

    private static function activityDefinitions(): array
    {
        return [
            [
                'activity_code' => 'internal_audit',
                'name' => '内部审核',
                'element_key' => 'internal_audit',
                'source_refs' => ['CNAS-CL01:2018 8.8'],
                'sort_order' => 10,
                'responsibilities' => [
                    self::fixedDuty('ia_annual_plan', 'organize', '制定内部审核年度计划，组织整改跟踪。', 'quality_manager', 10, ['CNAS-CL01:2018 8.8.2']),
                    self::fixedDuty('ia_plan_approval', 'approve', '批准内部审核年度计划的范围和资源。', 'lab_director', 20, ['CNAS-CL01:2018 8.8.1', 'CNAS-CL01:2018 8.8.2']),
                    self::runtimeDuty('ia_lead_audit', 'organize', '编制实施计划，组织审核并形成报告。', 'activity_role', 'audit_leader', 'activity_instance', 30, ['CNAS-CL01:2018 8.8.2']),
                    self::runtimeDuty('ia_audit_execution', 'execute', '执行内部审核，不得审核自己的工作。', 'activity_role', 'internal_auditor', 'activity_instance', 40, ['CNAS-CL01:2018 8.8.2'], ['no_self_audit']),
                    self::runtimeDuty('ia_correction', 'execute', '被审核活动的责任岗位实施更正、纠正和纠正措施。', 'dynamic_owner', 'audited_activity_owner', 'derived_from_scope', 50, ['CNAS-CL01:2018 8.8.2']),
                    self::runtimeDuty('ia_follow_up_verification', 'verify', '由审核组长或指定内审员跟踪验证整改结果。', 'activity_role', 'audit_follow_up_verifier', 'activity_instance', 60, ['CNAS-CL01:2018 8.8.2']),
                    self::fixedDuty('ia_archive', 'record_keep', '归档内部审核计划、记录、报告和整改验证证据。', 'document_controller', 70, ['CNAS-CL01:2018 8.8.2']),
                ],
            ],
            [
                'activity_code' => 'management_review',
                'name' => '管理评审',
                'element_key' => 'management_review',
                'source_refs' => ['CNAS-CL01:2018 8.9'],
                'sort_order' => 20,
                'responsibilities' => [
                    self::fixedDuty('mr_coordination', 'organize', '制定管理评审计划，协调输入，编制报告并跟踪措施。', 'quality_manager', 10, ['CNAS-CL01:2018 8.9.1', 'CNAS-CL01:2018 8.9.2', 'CNAS-CL01:2018 8.9.3']),
                    self::runtimeDuty('mr_provide_inputs', 'inform', '各相关责任岗位按职责提供管理评审输入。', 'dynamic_owner', 'management_review_input_owner', 'derived_from_scope', 20, ['CNAS-CL01:2018 8.9.2']),
                    self::fixedDuty('mr_preside_approve', 'approve', '主持管理评审，作出决定并批准管理评审报告。', 'lab_director', 30, ['CNAS-CL01:2018 8.9.1', 'CNAS-CL01:2018 8.9.3']),
                    self::runtimeDuty('mr_improvement', 'execute', '指定责任岗位实施管理评审改进措施。', 'dynamic_owner', 'management_review_improvement_owner', 'derived_from_scope', 40, ['CNAS-CL01:2018 8.9.3']),
                    self::fixedDuty('mr_verify_close', 'verify', '验证改进措施的实施和有效性并关闭。', 'quality_manager', 50, ['CNAS-CL01:2018 8.9.3']),
                    self::fixedDuty('mr_archive', 'record_keep', '归档管理评审计划、输入、报告及措施验证证据。', 'document_controller', 60, ['CNAS-CL01:2018 8.9.3']),
                ],
            ],
            [
                'activity_code' => 'risk_management',
                'name' => '风险管理',
                'element_key' => 'risks_opportunities',
                'source_refs' => ['CNAS-CL01:2018 8.5'],
                'sort_order' => 30,
                'responsibilities' => [
                    self::runtimeDuty('risk_identify_report', 'execute', '各岗位或风险责任岗位识别并报告风险。', 'dynamic_owner', 'risk_owner', 'derived_from_scope', 10, ['CNAS-CL01:2018 8.5.1']),
                    self::fixedDuty('risk_analyse_assess', 'organize', '组织风险分析与评估，提出处置措施。', 'quality_manager', 20, ['CNAS-CL01:2018 8.5.1', 'CNAS-CL01:2018 8.5.2']),
                    self::fixedDuty('risk_technical_countersign', 'countersign', '对风险技术影响及技术措施进行会签。', 'technical_manager', 30, ['CNAS-CL01:2018 8.5.2']),
                    self::fixedDuty('risk_general_approval', 'approve', '批准一般风险的处置措施和风险报告。', 'lab_director', 40, ['CNAS-CL01:2018 8.5.2']),
                    self::runtimeDuty('risk_implement', 'execute', '指定风险责任岗位实施风险处置措施。', 'dynamic_owner', 'risk_treatment_owner', 'derived_from_scope', 50, ['CNAS-CL01:2018 8.5.2']),
                    self::runtimeDuty('risk_verify', 'verify', '由质量负责人或独立验证人验证风险措施的实施和有效性。', 'activity_role', 'risk_verifier', 'activity_instance', 60, ['CNAS-CL01:2018 8.5.2'], ['separate_executor_verifier']),
                    self::fixedDuty('risk_major_approval', 'approve', '批准涉及重大预算或法律事项的风险处置。', 'company_general_manager', 70, ['CNAS-CL01:2018 8.5.2']),
                    self::fixedDuty('risk_archive', 'record_keep', '归档风险识别、评估、批准、实施和验证证据。', 'document_controller', 80, ['CNAS-CL01:2018 8.5.3']),
                ],
            ],
        ];
    }

    private static function fixedDuty(
        string $stepCode,
        string $dutyType,
        string $dutyText,
        string $positionCode,
        int $sortOrder,
        array $sourceRefs,
        array $ruleCodes = []
    ): array {
        return [
            'step_code' => $stepCode,
            'duty_type' => $dutyType,
            'duty_text' => $dutyText,
            'slot_kind' => 'fixed_position',
            'assignment_mode' => 'named_person',
            'position_code' => $positionCode,
            'slot_code' => '',
            'required' => 1,
            'eligibility_rule' => ['evidence_required' => true],
            'rule_codes' => $ruleCodes,
            'source_refs' => $sourceRefs,
            'sort_order' => $sortOrder,
        ];
    }

    private static function runtimeDuty(
        string $stepCode,
        string $dutyType,
        string $dutyText,
        string $slotKind,
        string $slotCode,
        string $assignmentMode,
        int $sortOrder,
        array $sourceRefs,
        array $ruleCodes = []
    ): array {
        return [
            'step_code' => $stepCode,
            'duty_type' => $dutyType,
            'duty_text' => $dutyText,
            'slot_kind' => $slotKind,
            'assignment_mode' => $assignmentMode,
            'position_code' => '',
            'slot_code' => $slotCode,
            'required' => 1,
            'eligibility_rule' => [
                'evidence_required' => true,
                'resolved_at_activity_instance' => true,
            ],
            'rule_codes' => $ruleCodes,
            'source_refs' => $sourceRefs,
            'sort_order' => $sortOrder,
        ];
    }

    private static function versionSummary(string $versionId): array
    {
        $version = Db::name('qms_responsibility_chain_versions')
            ->where('id', $versionId)
            ->where('soft_delete', 0)
            ->find();
        if (!$version) {
            throw new DomainException('责任链版本不存在。');
        }

        $activities = Db::name('qms_responsibility_activities')
            ->alias('a')
            ->leftJoin('qms_activity_responsibilities r', 'r.activity_id = a.id AND r.soft_delete = 0')
            ->where('a.chain_version_id', $versionId)
            ->where('a.soft_delete', 0)
            ->field('a.id,a.activity_code,a.name,a.sort_order,COUNT(r.id) responsibility_count')
            ->group('a.id,a.activity_code,a.name,a.sort_order')
            ->order('a.sort_order')
            ->select()
            ->toArray();

        return [
            'id' => (string)$version['id'],
            'chain_code' => (string)$version['chain_code'],
            'version_no' => (int)$version['version_no'],
            'status' => (string)$version['status'],
            'activities' => array_map(static fn (array $activity): array => [
                'id' => (string)$activity['id'],
                'activity_code' => (string)$activity['activity_code'],
                'name' => (string)$activity['name'],
                'responsibility_count' => (int)$activity['responsibility_count'],
            ], $activities),
        ];
    }

    private static function encodeJson(array $value): string
    {
        return (string)json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }
}
