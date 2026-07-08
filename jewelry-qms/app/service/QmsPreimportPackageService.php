<?php
declare(strict_types=1);

namespace app\service;

use think\facade\Config;
use think\facade\Db;

class QmsPreimportPackageService
{
    private const REQUIRED_FILES = [
        'documents' => 'documents_preimport.csv',
        'structured_documents' => 'structured_documents_preimport.csv',
        'record_form_templates' => 'record_form_templates_preimport.csv',
        'traceability_matrix' => 'traceability_matrix_preimport.csv',
        'manual_blocks' => 'manual_blocks_preimport.csv',
        'external_sources' => 'external_sources_preimport.csv',
    ];

    private const REQUIRED_SCHEMA_KEYS = [
        'record_number',
        'record_name',
        'applicable_clause',
        'related_procedure',
        'responsible_position',
        'trigger_time',
        'correction_rule',
    ];

    private const REQUIRED_REVIEW_FILES = [
        'manual_clause_review' => 'manual_clause_review_checklist.csv',
        'record_template_review' => 'record_template_review_checklist.csv',
        'attachment_disposition' => 'attachment_form_disposition.csv',
        'preapply_gate_register' => 'preapply_gate_register.csv',
    ];

    private const STAGE2_REQUIRED_TABLES = [
        'qms_structured_documents',
        'qms_document_blocks',
        'qms_document_block_links',
    ];

    private const REQUIRED_FIELD_CATALOG_FILES = [
        'manifest' => 'field_catalog_manifest.json',
        'template_index' => '01-模板字段索引.csv',
        'field_detail' => '02-字段级明细.csv',
        'overview' => '00-字段字典总览.md',
        'common_matrix' => '03-通用字段覆盖矩阵.md',
    ];

    private const FIELD_CATALOG_COMMON_KEYS = [
        'record_number',
        'record_name',
        'applicable_clause',
        'related_procedure',
        'responsible_position',
        'trigger_time',
        'reviewer',
        'storage_location',
        'retention_period',
        'confidentiality_level',
        'correction_rule',
    ];

    private const REQUIRED_RELEASE_PLAN_FILES = [
        'manifest' => 'release_rehearsal_manifest.json',
        'release_objects' => '01-发布对象清单.csv',
        'approval_rehearsal' => '02-审批签核演练清单.csv',
        'training_rehearsal' => '03-培训宣贯演练清单.csv',
        'obsolete_disposition' => '04-旧版处置演练清单.csv',
        'position_gates' => '06-口径闸门检查表.csv',
        'effectiveness_checks' => '07-实施有效性检查清单.csv',
        'overview' => '00-受控发布演练总览.md',
        'matrix' => '05-LIMS治理动作矩阵.md',
        'readme' => 'README.md',
    ];

    private const REQUIRED_RELEASE_POSITION_GATES = [
        'POS-01',
        'POS-02',
        'POS-03',
        'POS-04',
        'POS-05',
    ];

    private const REQUIRED_RELEASE_EXECUTION_FILES = [
        'manifest' => 'release_execution_template_manifest.json',
        'overview' => '00-发布执行记录模板总览.md',
        'template_index' => '01-发布执行记录模板索引.csv',
        'field_detail' => '02-发布执行字段明细.csv',
        'trial_csv' => '03-发布执行模拟试填.csv',
        'trial_json' => '03-发布执行模拟试填.json',
        'readme' => 'README.md',
    ];

    private const EXPECTED_RELEASE_EXECUTION_TEMPLATE_CODES = [
        'JL-REL-01',
        'JL-REL-02',
        'JL-REL-03',
        'JL-REL-04',
        'JL-REL-05',
        'JL-REL-06',
    ];

    private const RELEASE_EXECUTION_COMMON_FIELD_KEYS = [
        'record_number',
        'record_name',
        'applicable_clause',
        'related_procedure',
        'responsible_position',
        'trigger_time',
        'reviewer',
        'approval_status',
        'evidence_reference',
        'storage_location',
        'retention_period',
        'confidentiality_level',
        'correction_rule',
        'not_real_record_marker',
    ];

    private const REVIEW_SIMULATION_MARKER = 'SIMULATED_APPROVAL_NOT_REAL_REVIEW';

    private const REQUIRED_MANUAL_REVISION_FILES = [
        'manifest' => 'manual_revision_path_manifest.json',
        'overview' => '00-质量手册修订换版路径总览.md',
        'existing_manual' => '01-既有质量手册记录核对.csv',
        'revision_checklist' => '02-修订换版路径闸门清单.csv',
        'lims_action_preview' => '03-LIMS修订动作预览.csv',
        'human_decision_gates' => '04-人工决策闸门.csv',
        'lims_action_notes' => '05-LIMS修订动作说明.md',
        'readme' => 'README.md',
    ];

    private const REQUIRED_MANUAL_REVISION_GATES = [
        'MR-01',
        'MR-02',
        'MR-03',
        'MR-04',
        'MR-05',
        'MR-06',
        'MR-07',
        'MR-08',
        'MR-09',
    ];

    private const REQUIRED_MANUAL_REVISION_DECISIONS = [
        'MRD-01',
        'MRD-02',
        'MRD-03',
        'MRD-04',
        'MRD-05',
    ];

    private const REQUIRED_STAFF_TRAINING_FILES = [
        'manifest' => 'staff_training_manifest.json',
        'overview' => '00-机构人员学习实施总览.md',
        'role_matrix' => '01-岗位学习任务矩阵.csv',
        'material_index' => '02-学习材料入口清单.csv',
        'question_bank' => '03-理解确认题库.csv',
        'feedback_template' => '04-问题反馈与修订回填模板.csv',
        'lims_boundary' => '05-jewelry-qms试运行学习边界确认.md',
        'training_record_template' => '06-体系文件学习实施与理解确认记录候选模板.md',
        'readme' => 'README.md',
        'role_cards_dir' => 'role_cards',
    ];

    private const STAFF_TRAINING_MARKER = 'SIMULATED_TRAINING_NOT_REAL_RECORD';

    private const REQUIRED_STAGE2_REVIEW_FILES = [
        'manifest' => 'stage2_review_workbench_manifest.json',
        'overview' => '00-第二阶段结构化导入人工复核总览.md',
        'block_review_matrix' => '01-手册块复核矩阵.csv',
        'link_review_matrix' => '02-块级链接复核矩阵.csv',
        'clause_target_summary' => '03-按条款目标统计.csv',
        'target_backreference' => '04-目标文件记录反查清单.csv',
        'decision_template' => '05-人工复核意见回填模板.csv',
        'readme' => 'README.md',
    ];

    private const STAGE2_REVIEW_REQUIRED_GUARDRAILS = [
        '不写数据库',
        '不代表第二阶段已导入',
        '不代表人工评审通过',
        '已取得 CMA',
        'CNAS 申请中',
        'jewelry-qms 仍为建设中系统',
        '不写入质量手册正文',
    ];

    private const REQUIRED_STAGE2_REVIEW_PREVIEW_FILES = [
        'manifest' => 'stage2_review_decision_preview_manifest.json',
        'overview' => '00-第二阶段结构化复核意见回填预览总览.md',
        'decision_preview' => '01-拟回填决策预览.csv',
        'blocking_items' => '02-仍阻断项清单.csv',
        'scope_summary' => '03-按范围统计.csv',
        'readme' => 'README.md',
    ];

    private const STAGE2_REVIEW_PREVIEW_REQUIRED_GUARDRAILS = [
        '不写数据库',
        '不修改 stage2_structured_review_workbench',
        '不代表第二阶段已导入',
        '不代表人工评审通过',
        '已取得 CMA',
        'CNAS 申请中',
        'jewelry-qms 仍为建设中系统',
        '不写入质量手册正文',
    ];

    private const REQUIRED_GOVERNANCE_READINESS_FILES = [
        'manifest' => 'governance_readiness_manifest.json',
        'overview' => '00-治理就绪总览.md',
        'gate_register' => '01-总闸门清单.csv',
        'human_task_register' => '02-人工处理任务清单.csv',
        'command_checklist' => '03-LIMS命令复核清单.md',
        'readme' => 'README.md',
    ];

    private const GOVERNANCE_READINESS_REQUIRED_GUARDRAILS = [
        '不写数据库',
        '不修改 human_review_pack',
        '不代表人工评审通过',
        '已取得 CMA',
        'CNAS 申请中',
        '2022 程序清单',
        'jewelry-qms 仍为建设中系统',
        '不写入质量手册正文',
    ];

    private const REQUIRED_GOVERNANCE_GATE_IDS = [
        'GR-01',
        'GR-02',
        'GR-03',
        'GR-04',
        'GR-05',
        'GR-06',
        'GR-07',
        'GR-08',
        'GR-09',
        'GR-10',
        'GR-11',
        'GR-12',
        'GR-13',
    ];

    private const REQUIRED_GOVERNANCE_CLOSURE_FILES = [
        'manifest' => 'governance_closure_workbench_manifest.json',
        'overview' => '00-治理关闭工作台总览.md',
        'gate_closure_matrix' => '01-总闸门关闭矩阵.csv',
        'role_task_pack' => '02-按角色任务包.csv',
        'evidence_template' => '03-证据采集模板.csv',
        'closure_template' => '04-拟关闭回填模板.csv',
        'priority_batches' => '05-优先关闭批次.md',
        'readme' => 'README.md',
    ];

    private const GOVERNANCE_CLOSURE_REQUIRED_GUARDRAILS = [
        '不写数据库',
        '不修改 governance_readiness_dashboard',
        '不代表人工评审通过',
        '不代表真实培训完成',
        '不代表受控发布',
        '已取得 CMA',
        'CNAS 申请中',
        '2022 程序清单',
        'jewelry-qms 仍为建设中系统',
        '不写入质量手册正文',
    ];

    private const REQUIRED_GOVERNANCE_CLOSURE_EXECUTION_FILES = [
        'manifest' => 'governance_closure_execution_manifest.json',
        'overview' => '00-治理闭环执行包总览.md',
        'execution_batches' => '01-闭环执行批次.csv',
        'signature_register' => '02-岗位签核页模板.csv',
        'handoff_checklist' => '03-交接复核清单.csv',
        'route_index' => '04-回填路径索引.csv',
        'blocking_summary' => '05-阻断批次摘要.md',
        'readme' => 'README.md',
    ];

    private const GOVERNANCE_CLOSURE_EXECUTION_REQUIRED_GUARDRAILS = [
        '不写数据库',
        '不修改 governance_closure_workbench',
        '不代表人工评审通过',
        '不代表真实培训完成',
        '不代表受控发布',
        '已取得 CMA',
        'CNAS 申请中',
        '2022 程序清单',
        'jewelry-qms 仍为建设中系统',
        '不写入质量手册正文',
    ];

    private const REQUIRED_GOVERNANCE_CLOSURE_PILOT_FILES = [
        'manifest' => 'governance_closure_pilot_manifest.json',
        'overview' => '00-治理关闭最小试点总览.md',
        'pilot_batches' => '01-试点批次选择.csv',
        'pilot_evidence' => '02-试点证据填写页.csv',
        'pilot_handoff' => '03-试点签核交接页.csv',
        'rerun_commands' => '04-试点复跑命令清单.md',
        'readme' => 'README.md',
    ];

    private const GOVERNANCE_CLOSURE_PILOT_REQUIRED_GUARDRAILS = [
        '不写数据库',
        '不修改 governance_closure_execution_pack',
        '不代表人工评审通过',
        '不代表真实培训完成',
        '不代表受控发布',
        '已取得 CMA',
        'CNAS 申请中',
        '2022 程序清单',
        'jewelry-qms 仍为建设中系统',
        '不写入质量手册正文',
    ];

    private const REQUIRED_GOVERNANCE_CLOSURE_PILOT_RETURN_FILES = [
        'manifest' => 'governance_closure_pilot_return_manifest.json',
        'overview' => '00-试点回填预览总览.md',
        'mapping' => '01-试点证据到源工作台映射.csv',
        'source_preview' => '02-拟回填源行预览.csv',
        'missing_fields' => '03-仍缺字段清单.csv',
        'rerun_path' => '04-复跑路径清单.md',
        'readme' => 'README.md',
    ];

    private const GOVERNANCE_CLOSURE_PILOT_RETURN_REQUIRED_GUARDRAILS = [
        '不写数据库',
        '不修改 governance_closure_pilot_pack',
        '不代表人工评审通过',
        '不代表真实培训完成',
        '不代表受控发布',
        '已取得 CMA',
        'CNAS 申请中',
        '2022 程序清单',
        'jewelry-qms 仍为建设中系统',
        '不写入质量手册正文',
    ];

    private const REQUIRED_GOVERNANCE_CLOSURE_PILOT_SOURCE_UPDATE_FILES = [
        'manifest' => 'governance_closure_pilot_source_update_manifest.json',
        'overview' => '00-源工作台回填补丁预演总览.md',
        'patch_preview' => '01-源工作台回填补丁预览.csv',
        'blocked_patches' => '02-阻断补丁清单.csv',
        'manual_instructions' => '03-人工回填操作说明.md',
        'readme' => 'README.md',
    ];

    private const GOVERNANCE_CLOSURE_PILOT_SOURCE_UPDATE_REQUIRED_GUARDRAILS = [
        '不写数据库',
        '不修改 governance_closure_pilot_return_preview',
        '不修改 governance_closure_workbench',
        '不代表人工评审通过',
        '不代表真实培训完成',
        '不代表受控发布',
        '已取得 CMA',
        'CNAS 申请中',
        '2022 程序清单',
        'jewelry-qms 仍为建设中系统',
        '不写入质量手册正文',
    ];

    private const REQUIRED_GOVERNANCE_CLOSURE_PILOT_OPERATOR_WORKBOOK_FILES = [
        'manifest' => 'governance_closure_pilot_operator_workbook_manifest.json',
        'overview' => '00-试点人工执行工作簿总览.md',
        'master' => '01-试点执行主清单.csv',
        'field_checklist' => '02-逐字段填写清单.csv',
        'handoff_checklist' => '03-签核交接核对表.csv',
        'rerun' => '04-复跑与回填确认清单.md',
        'readme' => 'README.md',
        'task_card_dir' => 'task_cards',
    ];

    private const GOVERNANCE_CLOSURE_PILOT_OPERATOR_WORKBOOK_REQUIRED_GUARDRAILS = [
        '不写数据库',
        '不修改试点包',
        '不代表人工评审通过',
        '不代表真实培训完成',
        '不代表受控发布',
        '已取得 CMA',
        'CNAS 申请中',
        '2022 程序清单',
        'jewelry-qms 仍为建设中系统',
        '不写入质量手册正文',
    ];

    private const REQUIRED_GOVERNANCE_CLOSURE_PILOT_OPERATOR_HANDBACK_FILES = [
        'manifest' => 'governance_closure_pilot_operator_handback_manifest.json',
        'overview' => '00-真实执行交回总览.md',
        'master' => '01-真实执行交回主清单.csv',
        'field_checklist' => '02-真实逐字段交回清单.csv',
        'handoff_checklist' => '03-真实签核交接交回表.csv',
        'acceptance' => '04-交回验收与复跑说明.md',
        'readme' => 'README.md',
        'task_card_dir' => 'task_cards',
    ];

    private const GOVERNANCE_CLOSURE_PILOT_OPERATOR_HANDBACK_FORBIDDEN_MARKERS = [
        'SIMULATED',
        '模拟完成',
        'SIMULATED_COMPLETION_NOT_REAL_EXECUTION',
        'SIMULATED_PERSON_NOT_REAL_EXECUTOR',
        'SIMULATED_REVIEWER_NOT_REAL_REVIEW',
    ];

    private const GOVERNANCE_CLOSURE_PILOT_OPERATOR_HANDBACK_REQUIRED_GUARDRAILS = [
        '不写数据库',
        '不修改 governance_closure_pilot_operator_workbook',
        '真实人员交回',
        '不代表人工评审通过',
        '不代表真实培训完成',
        '不代表受控发布',
        '已取得 CMA',
        'CNAS 申请中',
        '2022 程序清单',
        'jewelry-qms 仍为建设中系统',
        '不写入质量手册正文',
    ];

    private const REQUIRED_GOVERNANCE_CLOSURE_PILOT_OPERATOR_COMPLETION_SIMULATION_FILES = [
        'manifest' => 'governance_closure_pilot_operator_completion_simulation_manifest.json',
        'overview' => '00-模拟完成总览.md',
        'master' => '01-模拟完成主清单.csv',
        'field_checklist' => '02-模拟逐字段完成清单.csv',
        'handoff_checklist' => '03-模拟签核交接完成表.csv',
        'rerun' => '04-复跑验证提示.md',
        'readme' => 'README.md',
        'task_card_dir' => 'task_cards',
    ];

    private const GOVERNANCE_CLOSURE_PILOT_OPERATOR_COMPLETION_SIMULATION_MARKER = 'SIMULATED_COMPLETION_NOT_REAL_EXECUTION';

    private const GOVERNANCE_CLOSURE_PILOT_OPERATOR_COMPLETION_SIMULATION_REQUIRED_GUARDRAILS = [
        '不写数据库',
        '不修改 governance_closure_pilot_operator_workbook',
        '模拟完成',
        '不代表真实执行完成',
        '不代表人工评审通过',
        '不代表真实培训完成',
        '不代表受控发布',
        '已取得 CMA',
        'CNAS 申请中',
        '2022 程序清单',
        'jewelry-qms 仍为建设中系统',
        '不写入质量手册正文',
    ];

    private const REQUIRED_GOVERNANCE_CLOSURE_PREVIEW_FILES = [
        'manifest' => 'governance_closure_decision_preview_manifest.json',
        'overview' => '00-治理关闭意见回填预览总览.md',
        'decision_preview' => '01-拟关闭决策预览.csv',
        'blocking_items' => '02-仍阻断关闭项.csv',
        'gate_summary' => '03-按闸门关闭统计.csv',
        'readme' => 'README.md',
    ];

    private const GOVERNANCE_CLOSURE_PREVIEW_REQUIRED_GUARDRAILS = [
        '不写数据库',
        '不修改 governance_closure_workbench',
        '不代表人工评审通过',
        '不代表真实培训完成',
        '不代表受控发布',
        '已取得 CMA',
        'CNAS 申请中',
        '2022 程序清单',
        'jewelry-qms 仍为建设中系统',
        '不写入质量手册正文',
    ];

    private const REQUIRED_GOVERNANCE_READINESS_REFRESH_FILES = [
        'manifest' => 'governance_readiness_refresh_preview_manifest.json',
        'overview' => '00-治理就绪刷新预览总览.md',
        'gate_refresh_preview' => '01-总闸门刷新预览.csv',
        'task_refresh_preview' => '02-人工任务刷新预览.csv',
        'blocking_tasks' => '03-仍阻断任务清单.csv',
        'change_summary' => '04-刷新差异摘要.csv',
        'readme' => 'README.md',
    ];

    private const GOVERNANCE_READINESS_REFRESH_REQUIRED_GUARDRAILS = [
        '不写数据库',
        '不修改 governance_readiness_dashboard',
        '不代表人工评审通过',
        '不代表真实培训完成',
        '不代表受控发布',
        '已取得 CMA',
        'CNAS 申请中',
        '2022 程序清单',
        'jewelry-qms 仍为建设中系统',
        '不写入质量手册正文',
    ];

    public static function inspect(
        string $packageDir,
        ?string $reviewDir = null,
        bool $stage2Check = false,
        ?string $fieldCatalogDir = null,
        ?string $releasePlanDir = null,
        ?string $releaseExecutionDir = null,
        ?string $manualRevisionDir = null,
        ?string $staffTrainingDir = null,
        ?string $stage2ReviewDir = null,
        ?string $stage2ReviewPreviewDir = null,
        ?string $governanceReadinessDir = null,
        ?string $governanceClosureDir = null,
        ?string $governanceClosurePreviewDir = null,
        ?string $governanceReadinessRefreshDir = null,
        ?string $governanceClosureExecutionDir = null,
        ?string $governanceClosurePilotDir = null,
        ?string $governanceClosurePilotReturnDir = null,
        ?string $governanceClosurePilotSourceUpdateDir = null,
        ?string $governanceClosurePilotOperatorWorkbookDir = null,
        ?string $governanceClosurePilotOperatorHandbackDir = null,
        ?string $governanceClosurePilotOperatorCompletionSimulationDir = null
    ): array
    {
        return self::buildSummary($packageDir, false, false, $reviewDir, $stage2Check, $fieldCatalogDir, $releasePlanDir, $releaseExecutionDir, $manualRevisionDir, $staffTrainingDir, $stage2ReviewDir, $stage2ReviewPreviewDir, $governanceReadinessDir, $governanceClosureDir, $governanceClosurePreviewDir, $governanceReadinessRefreshDir, $governanceClosureExecutionDir, $governanceClosurePilotDir, $governanceClosurePilotReturnDir, $governanceClosurePilotSourceUpdateDir, $governanceClosurePilotOperatorWorkbookDir, $governanceClosurePilotOperatorHandbackDir, $governanceClosurePilotOperatorCompletionSimulationDir);
    }

    public static function apply(
        string $packageDir,
        bool $ackHumanReviewed,
        ?string $reviewDir = null,
        bool $stage2Check = false,
        ?string $fieldCatalogDir = null,
        ?string $releasePlanDir = null,
        ?string $releaseExecutionDir = null,
        ?string $manualRevisionDir = null,
        ?string $staffTrainingDir = null,
        ?string $stage2ReviewDir = null,
        ?string $stage2ReviewPreviewDir = null,
        ?string $governanceReadinessDir = null,
        ?string $governanceClosureDir = null,
        ?string $governanceClosurePreviewDir = null,
        ?string $governanceReadinessRefreshDir = null,
        ?string $governanceClosureExecutionDir = null,
        ?string $governanceClosurePilotDir = null,
        ?string $governanceClosurePilotReturnDir = null,
        ?string $governanceClosurePilotSourceUpdateDir = null,
        ?string $governanceClosurePilotOperatorWorkbookDir = null,
        ?string $governanceClosurePilotOperatorHandbackDir = null,
        ?string $governanceClosurePilotOperatorCompletionSimulationDir = null
    ): array {
        $summary = self::buildSummary($packageDir, true, $ackHumanReviewed, $reviewDir, $stage2Check, $fieldCatalogDir, $releasePlanDir, $releaseExecutionDir, $manualRevisionDir, $staffTrainingDir, $stage2ReviewDir, $stage2ReviewPreviewDir, $governanceReadinessDir, $governanceClosureDir, $governanceClosurePreviewDir, $governanceReadinessRefreshDir, $governanceClosureExecutionDir, $governanceClosurePilotDir, $governanceClosurePilotReturnDir, $governanceClosurePilotSourceUpdateDir, $governanceClosurePilotOperatorWorkbookDir, $governanceClosurePilotOperatorHandbackDir, $governanceClosurePilotOperatorCompletionSimulationDir);
        if (!$ackHumanReviewed) {
            $summary['status'] = 'blocked';
            $summary['findings'][] = [
                'severity' => 'high',
                'id' => 'ack_human_review_required',
                'message' => '正式写入前必须显式提供 --ack-human-reviewed。',
            ];
            $summary['counts']['findings'] = count($summary['findings']);
            return $summary;
        }
        if (!$reviewDir) {
            $summary['status'] = 'blocked';
            $summary['findings'][] = [
                'severity' => 'high',
                'id' => 'human_review_pack_required',
                'message' => '正式写入前必须提供 --review-dir 指向已完成人工评审的 human_review_pack。',
            ];
            $summary['counts']['findings'] = count($summary['findings']);
            return $summary;
        }
        if (self::hasBlockingFinding($summary)) {
            $summary['status'] = 'blocked';
            $summary['counts']['findings'] = count($summary['findings']);
            return $summary;
        }

        $rows = $summary['_rows'];
        $companyId = (string)Config::get('qms.company_id');
        $now = date('Y-m-d H:i:s');
        $applied = [
            'documents_created' => 0,
            'record_templates_created' => 0,
            'sources_upserted' => 0,
            'deferred_structured_documents' => count($rows['structured_documents']),
            'deferred_traceability_rows' => count($rows['traceability_matrix']),
            'deferred_manual_blocks' => count($rows['manual_blocks']),
        ];

        Db::transaction(function () use ($rows, $companyId, $now, &$applied): void {
            $documentIdByNumber = self::documentIdByNumber(array_column($rows['documents'], 'doc_number'));
            foreach ($rows['documents'] as $row) {
                $action = (string)($row['action'] ?? '');
                $docNumber = trim((string)($row['doc_number'] ?? ''));
                if ($docNumber === '' || $action === 'reference_existing_current' || isset($documentIdByNumber[$docNumber])) {
                    continue;
                }
                $id = qms_uuid();
                Db::name('documents')->insert([
                    'id' => $id,
                    'company_id' => $companyId,
                    'level' => (int)($row['level'] ?? 2),
                    'doc_number' => $docNumber,
                    'title' => (string)($row['title'] ?? ''),
                    'version' => (string)($row['version'] ?? '候选'),
                    'status' => 'draft',
                    'publish' => 0,
                    'soft_delete' => 0,
                    'change_reason' => (string)($row['change_reason'] ?? ''),
                    'created' => $now,
                    'modified' => $now,
                ]);
                $documentIdByNumber[$docNumber] = $id;
                $applied['documents_created']++;
            }

            $existingTemplateIds = self::recordTemplateIdByNumber(array_column($rows['record_form_templates'], 'doc_number'));
            foreach ($rows['record_form_templates'] as $row) {
                $docNumber = trim((string)($row['doc_number'] ?? ''));
                if ($docNumber === '' || isset($existingTemplateIds[$docNumber])) {
                    continue;
                }
                $procedureId = self::firstExistingProcedureDocumentId((string)($row['procedure_doc_numbers'] ?? ''), $documentIdByNumber);
                Db::name('record_form_templates')->insert([
                    'id' => qms_uuid(),
                    'company_id' => $companyId,
                    'document_id' => $documentIdByNumber[$docNumber] ?? null,
                    'procedure_doc_id' => $procedureId,
                    'doc_number' => $docNumber,
                    'name' => (string)($row['name'] ?? ''),
                    'module' => (string)($row['module'] ?? 'QMS候选记录模板'),
                    'print_template_key' => (string)($row['print_template_key'] ?? 'generic_record_form'),
                    'field_schema' => (string)($row['field_schema_json'] ?? '[]'),
                    'version' => (string)($row['version'] ?? '候选'),
                    'status' => 'draft',
                    'review_status' => 'pending',
                    'review_note' => (string)($row['review_note'] ?? ''),
                    'publish' => 0,
                    'soft_delete' => 0,
                    'created' => $now,
                    'modified' => $now,
                ]);
                $applied['record_templates_created']++;
            }

            foreach ($rows['external_sources'] as $row) {
                $sourceCode = trim((string)($row['source_code'] ?? ''));
                if ($sourceCode === '') {
                    continue;
                }
                $payload = [
                    'company_id' => $companyId,
                    'source_code' => $sourceCode,
                    'name' => (string)($row['name'] ?? ''),
                    'source_type' => (string)($row['source_type'] ?? 'external_standard'),
                    'version' => (string)($row['version'] ?? ''),
                    'freshness_checked_at' => self::nullableDate((string)($row['freshness_checked_at'] ?? '')),
                    'freshness_result' => (string)($row['freshness_result'] ?? ''),
                    'freshness_evidence' => (string)($row['freshness_evidence'] ?? ''),
                    'freshness_status' => (string)($row['freshness_status'] ?? 'unknown'),
                    'status' => (string)($row['status'] ?? 'draft'),
                    'publish' => ((string)($row['status'] ?? '') === 'obsolete') ? 0 : 1,
                    'soft_delete' => 0,
                    'modified' => $now,
                ];
                $existingId = Db::name('qms_sources')
                    ->where('source_code', $sourceCode)
                    ->where('soft_delete', 0)
                    ->value('id');
                if ($existingId) {
                    Db::name('qms_sources')->where('id', $existingId)->update($payload);
                } else {
                    $payload['id'] = qms_uuid();
                    $payload['created'] = $now;
                    Db::name('qms_sources')->insert($payload);
                }
                $applied['sources_upserted']++;
            }
        });

        unset($summary['_rows']);
        $summary['status'] = 'applied';
        $summary['applied'] = $applied;
        $summary['boundary'][] = '本次 apply 不创建正式运行记录；结构化块和追溯关系仍需后续人工确认后导入。';
        return $summary;
    }

    public static function rehearseApply(
        string $packageDir,
        bool $ackHumanReviewed,
        ?string $reviewDir = null,
        bool $stage2Check = false,
        ?string $fieldCatalogDir = null,
        ?string $releasePlanDir = null,
        ?string $releaseExecutionDir = null,
        ?string $manualRevisionDir = null,
        ?string $staffTrainingDir = null,
        ?string $stage2ReviewDir = null,
        ?string $stage2ReviewPreviewDir = null,
        ?string $governanceReadinessDir = null,
        ?string $governanceClosureDir = null,
        ?string $governanceClosurePreviewDir = null,
        ?string $governanceReadinessRefreshDir = null,
        ?string $governanceClosureExecutionDir = null,
        ?string $governanceClosurePilotDir = null,
        ?string $governanceClosurePilotReturnDir = null,
        ?string $governanceClosurePilotSourceUpdateDir = null,
        ?string $governanceClosurePilotOperatorWorkbookDir = null,
        ?string $governanceClosurePilotOperatorHandbackDir = null,
        ?string $governanceClosurePilotOperatorCompletionSimulationDir = null
    ): array {
        $summary = self::buildSummary($packageDir, true, $ackHumanReviewed, $reviewDir, $stage2Check, $fieldCatalogDir, $releasePlanDir, $releaseExecutionDir, $manualRevisionDir, $staffTrainingDir, $stage2ReviewDir, $stage2ReviewPreviewDir, $governanceReadinessDir, $governanceClosureDir, $governanceClosurePreviewDir, $governanceReadinessRefreshDir, $governanceClosureExecutionDir, $governanceClosurePilotDir, $governanceClosurePilotReturnDir, $governanceClosurePilotSourceUpdateDir, $governanceClosurePilotOperatorWorkbookDir, $governanceClosurePilotOperatorHandbackDir, $governanceClosurePilotOperatorCompletionSimulationDir, true);
        $summary['mode'] = 'apply-rehearsal';
        if (!$ackHumanReviewed) {
            $summary['status'] = 'blocked';
            $summary['findings'][] = [
                'severity' => 'high',
                'id' => 'ack_human_review_required',
                'message' => 'apply-rehearsal 也必须显式提供 --ack-human-reviewed，以验证真实 apply 的同等人工确认闸门。',
            ];
            $summary['counts']['findings'] = count($summary['findings']);
            return $summary;
        }
        if (!$reviewDir) {
            $summary['status'] = 'blocked';
            $summary['findings'][] = [
                'severity' => 'high',
                'id' => 'human_review_pack_required',
                'message' => 'apply-rehearsal 必须提供 --review-dir，以验证人审包通过条件。',
            ];
            $summary['counts']['findings'] = count($summary['findings']);
            return $summary;
        }
        if (self::hasBlockingFinding($summary)) {
            $summary['status'] = 'blocked';
            $summary['counts']['findings'] = count($summary['findings']);
            return $summary;
        }

        $rows = (array)($summary['_rows'] ?? []);
        $summary['status'] = 'rehearsal_ready';
        $summary['rehearsal_plan'] = [
            'database_write_performed' => 0,
            'documents_would_evaluate' => count((array)($rows['documents'] ?? [])),
            'record_templates_would_evaluate' => count((array)($rows['record_form_templates'] ?? [])),
            'external_sources_would_evaluate' => count((array)($rows['external_sources'] ?? [])),
            'structured_documents_still_deferred' => count((array)($rows['structured_documents'] ?? [])),
            'manual_blocks_still_deferred' => count((array)($rows['manual_blocks'] ?? [])),
            'traceability_links_still_deferred' => count((array)($rows['traceability_matrix'] ?? [])),
            'simulated_review_pack_used' => (int)($summary['review_pack']['is_simulated'] ?? 0),
        ];
        $summary['boundary'][] = 'apply-rehearsal 只验证真实 apply 前的同等闸门，不进入数据库事务，不创建 draft 文件、记录模板或外来依据。';
        $summary['boundary'][] = '若使用模拟人审包，该结果只能证明命令链路可演练，不能替代真实人工评审或用户授权。';
        return $summary;
    }

    public static function renderMarkdown(array $summary): string
    {
        $mode = (string)($summary['mode'] ?? 'dry-run');
        $title = match ($mode) {
            'apply' => '# LIMS 预导入命令 apply 闸门报告',
            'apply-rehearsal' => '# LIMS 预导入命令 apply-rehearsal 非写库演练报告',
            default => '# LIMS 预导入命令 dry-run 报告',
        };
        $lines = [
            $title,
            '',
            '生成时间：' . (string)($summary['generated_at'] ?? ''),
            '命令模式：' . $mode,
            '结论：' . (string)($summary['status'] ?? ''),
            '预导入包：`' . (string)($summary['package_dir'] ?? '') . '`',
            '',
            '## 计数',
            '',
        ];
        foreach ((array)($summary['counts'] ?? []) as $key => $value) {
            $lines[] = '- ' . $key . ': ' . (string)$value;
        }
        $lines[] = '';
        $lines[] = '## LIMS 对接判断';
        $lines[] = '';
        foreach ((array)($summary['readiness'] ?? []) as $key => $value) {
            $lines[] = '- ' . $key . ': ' . (is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : (string)$value);
        }
        if (!empty($summary['review_pack'])) {
            $lines[] = '';
            $lines[] = '## 人工评审包';
            $lines[] = '';
            foreach ((array)$summary['review_pack'] as $key => $value) {
                if ($key === 'findings') {
                    continue;
                }
                $lines[] = '- ' . $key . ': ' . (is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : (string)$value);
            }
        }
        if (!empty($summary['stage2_readiness'])) {
            $lines[] = '';
            $lines[] = '## 第二阶段结构化导入预检';
            $lines[] = '';
            foreach ((array)$summary['stage2_readiness'] as $key => $value) {
                if ($key === 'findings') {
                    continue;
                }
                $lines[] = '- ' . $key . ': ' . (is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : (string)$value);
            }
            if (!empty($summary['stage2_readiness']['findings'])) {
                $lines[] = '';
                $lines[] = '### 第二阶段发现项';
                $lines[] = '';
                foreach ((array)$summary['stage2_readiness']['findings'] as $finding) {
                    $lines[] = '- [' . (string)($finding['severity'] ?? '-') . '] '
                        . (string)($finding['id'] ?? '-') . '：' . (string)($finding['message'] ?? '');
                }
            }
        }
        if (!empty($summary['field_catalog'])) {
            $lines[] = '';
            $lines[] = '## 记录模板字段字典';
            $lines[] = '';
            foreach ((array)$summary['field_catalog'] as $key => $value) {
                if ($key === 'findings') {
                    continue;
                }
                $lines[] = '- ' . $key . ': ' . (is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : (string)$value);
            }
            if (!empty($summary['field_catalog']['findings'])) {
                $lines[] = '';
                $lines[] = '### 字段字典发现项';
                $lines[] = '';
                foreach ((array)$summary['field_catalog']['findings'] as $finding) {
                    $lines[] = '- [' . (string)($finding['severity'] ?? '-') . '] '
                        . (string)($finding['id'] ?? '-') . '：' . (string)($finding['message'] ?? '');
                }
            }
        }
        if (!empty($summary['release_plan'])) {
            $lines[] = '';
            $lines[] = '## 受控发布治理演练';
            $lines[] = '';
            foreach ((array)$summary['release_plan'] as $key => $value) {
                if ($key === 'findings') {
                    continue;
                }
                $lines[] = '- ' . $key . ': ' . (is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : (string)$value);
            }
            if (!empty($summary['release_plan']['findings'])) {
                $lines[] = '';
                $lines[] = '### 受控发布演练发现项';
                $lines[] = '';
                foreach ((array)$summary['release_plan']['findings'] as $finding) {
                    $lines[] = '- [' . (string)($finding['severity'] ?? '-') . '] '
                        . (string)($finding['id'] ?? '-') . '：' . (string)($finding['message'] ?? '');
                }
            }
        }
        if (!empty($summary['release_execution'])) {
            $lines[] = '';
            $lines[] = '## 发布执行记录模板';
            $lines[] = '';
            foreach ((array)$summary['release_execution'] as $key => $value) {
                if ($key === 'findings') {
                    continue;
                }
                $lines[] = '- ' . $key . ': ' . (is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : (string)$value);
            }
            if (!empty($summary['release_execution']['findings'])) {
                $lines[] = '';
                $lines[] = '### 发布执行模板发现项';
                $lines[] = '';
                foreach ((array)$summary['release_execution']['findings'] as $finding) {
                    $lines[] = '- [' . (string)($finding['severity'] ?? '-') . '] '
                        . (string)($finding['id'] ?? '-') . '：' . (string)($finding['message'] ?? '');
                }
            }
        }
        if (!empty($summary['manual_revision'])) {
            $lines[] = '';
            $lines[] = '## 质量手册修订换版路径';
            $lines[] = '';
            foreach ((array)$summary['manual_revision'] as $key => $value) {
                if ($key === 'findings') {
                    continue;
                }
                $lines[] = '- ' . $key . ': ' . (is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : (string)$value);
            }
            if (!empty($summary['manual_revision']['findings'])) {
                $lines[] = '';
                $lines[] = '### 手册修订路径发现项';
                $lines[] = '';
                foreach ((array)$summary['manual_revision']['findings'] as $finding) {
                    $lines[] = '- [' . (string)($finding['severity'] ?? '-') . '] '
                        . (string)($finding['id'] ?? '-') . '：' . (string)($finding['message'] ?? '');
                }
            }
        }
        if (!empty($summary['staff_training'])) {
            $lines[] = '';
            $lines[] = '## 机构人员学习实施包';
            $lines[] = '';
            foreach ((array)$summary['staff_training'] as $key => $value) {
                if ($key === 'findings') {
                    continue;
                }
                $lines[] = '- ' . $key . ': ' . (is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : (string)$value);
            }
            if (!empty($summary['staff_training']['findings'])) {
                $lines[] = '';
                $lines[] = '### 机构人员学习实施包发现项';
                $lines[] = '';
                foreach ((array)$summary['staff_training']['findings'] as $finding) {
                    $lines[] = '- [' . (string)($finding['severity'] ?? '-') . '] '
                        . (string)($finding['id'] ?? '-') . '：' . (string)($finding['message'] ?? '');
                }
            }
        }
        if (!empty($summary['stage2_review'])) {
            $lines[] = '';
            $lines[] = '## 第二阶段结构化导入人工复核工作台';
            $lines[] = '';
            foreach ((array)$summary['stage2_review'] as $key => $value) {
                if ($key === 'findings') {
                    continue;
                }
                $lines[] = '- ' . $key . ': ' . (is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : (string)$value);
            }
            if (!empty($summary['stage2_review']['findings'])) {
                $lines[] = '';
                $lines[] = '### 第二阶段人工复核发现项';
                $lines[] = '';
                foreach ((array)$summary['stage2_review']['findings'] as $finding) {
                    $lines[] = '- [' . (string)($finding['severity'] ?? '-') . '] '
                        . (string)($finding['id'] ?? '-') . '：' . (string)($finding['message'] ?? '');
                }
            }
        }
        if (!empty($summary['stage2_review_preview'])) {
            $lines[] = '';
            $lines[] = '## 第二阶段复核意见回填预览包';
            $lines[] = '';
            foreach ((array)$summary['stage2_review_preview'] as $key => $value) {
                if ($key === 'findings') {
                    continue;
                }
                $lines[] = '- ' . $key . ': ' . (is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : (string)$value);
            }
            if (!empty($summary['stage2_review_preview']['findings'])) {
                $lines[] = '';
                $lines[] = '### 第二阶段复核意见预览发现项';
                $lines[] = '';
                foreach ((array)$summary['stage2_review_preview']['findings'] as $finding) {
                    $lines[] = '- [' . (string)($finding['severity'] ?? '-') . '] '
                        . (string)($finding['id'] ?? '-') . '：' . (string)($finding['message'] ?? '');
                }
            }
        }
        if (!empty($summary['governance_readiness'])) {
            $lines[] = '';
            $lines[] = '## 治理就绪总览包';
            $lines[] = '';
            foreach ((array)$summary['governance_readiness'] as $key => $value) {
                if ($key === 'findings') {
                    continue;
                }
                $lines[] = '- ' . $key . ': ' . (is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : (string)$value);
            }
            if (!empty($summary['governance_readiness']['findings'])) {
                $lines[] = '';
                $lines[] = '### 治理就绪总览发现项';
                $lines[] = '';
                foreach ((array)$summary['governance_readiness']['findings'] as $finding) {
                    $lines[] = '- [' . (string)($finding['severity'] ?? '-') . '] '
                        . (string)($finding['id'] ?? '-') . '：' . (string)($finding['message'] ?? '');
                }
            }
        }
        if (!empty($summary['governance_closure'])) {
            $lines[] = '';
            $lines[] = '## 治理关闭工作台';
            $lines[] = '';
            foreach ((array)$summary['governance_closure'] as $key => $value) {
                if ($key === 'findings') {
                    continue;
                }
                $lines[] = '- ' . $key . ': ' . (is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : (string)$value);
            }
            if (!empty($summary['governance_closure']['findings'])) {
                $lines[] = '';
                $lines[] = '### 治理关闭工作台发现项';
                $lines[] = '';
                foreach ((array)$summary['governance_closure']['findings'] as $finding) {
                    $lines[] = '- [' . (string)($finding['severity'] ?? '-') . '] '
                        . (string)($finding['id'] ?? '-') . '：' . (string)($finding['message'] ?? '');
                }
            }
        }
        if (!empty($summary['governance_closure_execution'])) {
            $lines[] = '';
            $lines[] = '## 治理闭环执行包';
            $lines[] = '';
            foreach ((array)$summary['governance_closure_execution'] as $key => $value) {
                if ($key === 'findings') {
                    continue;
                }
                $lines[] = '- ' . $key . ': ' . (is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : (string)$value);
            }
            if (!empty($summary['governance_closure_execution']['findings'])) {
                $lines[] = '';
                $lines[] = '### 治理闭环执行包发现项';
                $lines[] = '';
                foreach ((array)$summary['governance_closure_execution']['findings'] as $finding) {
                    $lines[] = '- [' . (string)($finding['severity'] ?? '-') . '] '
                        . (string)($finding['id'] ?? '-') . '：' . (string)($finding['message'] ?? '');
                }
            }
        }
        if (!empty($summary['governance_closure_pilot'])) {
            $lines[] = '';
            $lines[] = '## 治理关闭最小试点包';
            $lines[] = '';
            foreach ((array)$summary['governance_closure_pilot'] as $key => $value) {
                if ($key === 'findings') {
                    continue;
                }
                $lines[] = '- ' . $key . ': ' . (is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : (string)$value);
            }
            if (!empty($summary['governance_closure_pilot']['findings'])) {
                $lines[] = '';
                $lines[] = '### 治理关闭最小试点包发现项';
                $lines[] = '';
                foreach ((array)$summary['governance_closure_pilot']['findings'] as $finding) {
                    $lines[] = '- [' . (string)($finding['severity'] ?? '-') . '] '
                        . (string)($finding['id'] ?? '-') . '：' . (string)($finding['message'] ?? '');
                }
            }
        }
        if (!empty($summary['governance_closure_pilot_return'])) {
            $lines[] = '';
            $lines[] = '## 治理关闭试点回填预览包';
            $lines[] = '';
            foreach ((array)$summary['governance_closure_pilot_return'] as $key => $value) {
                if ($key === 'findings') {
                    continue;
                }
                $lines[] = '- ' . $key . ': ' . (is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : (string)$value);
            }
            if (!empty($summary['governance_closure_pilot_return']['findings'])) {
                $lines[] = '';
                $lines[] = '### 治理关闭试点回填预览包发现项';
                $lines[] = '';
                foreach ((array)$summary['governance_closure_pilot_return']['findings'] as $finding) {
                    $lines[] = '- [' . (string)($finding['severity'] ?? '-') . '] '
                        . (string)($finding['id'] ?? '-') . '：' . (string)($finding['message'] ?? '');
                }
            }
        }
        if (!empty($summary['governance_closure_pilot_source_update'])) {
            $lines[] = '';
            $lines[] = '## 治理关闭试点源工作台回填补丁预演包';
            $lines[] = '';
            foreach ((array)$summary['governance_closure_pilot_source_update'] as $key => $value) {
                if ($key === 'findings') {
                    continue;
                }
                $lines[] = '- ' . $key . ': ' . (is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : (string)$value);
            }
            if (!empty($summary['governance_closure_pilot_source_update']['findings'])) {
                $lines[] = '';
                $lines[] = '### 治理关闭试点源工作台回填补丁预演发现项';
                $lines[] = '';
                foreach ((array)$summary['governance_closure_pilot_source_update']['findings'] as $finding) {
                    $lines[] = '- [' . (string)($finding['severity'] ?? '-') . '] '
                        . (string)($finding['id'] ?? '-') . '：' . (string)($finding['message'] ?? '');
                }
            }
        }
        if (!empty($summary['governance_closure_pilot_operator_workbook'])) {
            $lines[] = '';
            $lines[] = '## 治理关闭试点人工执行工作簿';
            $lines[] = '';
            foreach ((array)$summary['governance_closure_pilot_operator_workbook'] as $key => $value) {
                if ($key === 'findings') {
                    continue;
                }
                $lines[] = '- ' . $key . ': ' . (is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : (string)$value);
            }
            if (!empty($summary['governance_closure_pilot_operator_workbook']['findings'])) {
                $lines[] = '';
                $lines[] = '### 治理关闭试点人工执行工作簿发现项';
                $lines[] = '';
                foreach ((array)$summary['governance_closure_pilot_operator_workbook']['findings'] as $finding) {
                    $lines[] = '- [' . (string)($finding['severity'] ?? '-') . '] '
                        . (string)($finding['id'] ?? '-') . '：' . (string)($finding['message'] ?? '');
                }
            }
        }
        if (!empty($summary['governance_closure_pilot_operator_handback'])) {
            $lines[] = '';
            $lines[] = '## 治理关闭试点真实执行交回包';
            $lines[] = '';
            foreach ((array)$summary['governance_closure_pilot_operator_handback'] as $key => $value) {
                if ($key === 'findings') {
                    continue;
                }
                $lines[] = '- ' . $key . ': ' . (is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : (string)$value);
            }
            if (!empty($summary['governance_closure_pilot_operator_handback']['findings'])) {
                $lines[] = '';
                $lines[] = '### 治理关闭试点真实执行交回包发现项';
                $lines[] = '';
                foreach ((array)$summary['governance_closure_pilot_operator_handback']['findings'] as $finding) {
                    $lines[] = '- [' . (string)($finding['severity'] ?? '-') . '] '
                        . (string)($finding['id'] ?? '-') . '：' . (string)($finding['message'] ?? '');
                }
            }
        }
        if (!empty($summary['governance_closure_pilot_operator_completion_simulation'])) {
            $lines[] = '';
            $lines[] = '## 治理关闭试点人工执行模拟完成包';
            $lines[] = '';
            foreach ((array)$summary['governance_closure_pilot_operator_completion_simulation'] as $key => $value) {
                if ($key === 'findings') {
                    continue;
                }
                $lines[] = '- ' . $key . ': ' . (is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : (string)$value);
            }
            if (!empty($summary['governance_closure_pilot_operator_completion_simulation']['findings'])) {
                $lines[] = '';
                $lines[] = '### 治理关闭试点人工执行模拟完成包发现项';
                $lines[] = '';
                foreach ((array)$summary['governance_closure_pilot_operator_completion_simulation']['findings'] as $finding) {
                    $lines[] = '- [' . (string)($finding['severity'] ?? '-') . '] '
                        . (string)($finding['id'] ?? '-') . '：' . (string)($finding['message'] ?? '');
                }
            }
        }
        if (!empty($summary['governance_closure_preview'])) {
            $lines[] = '';
            $lines[] = '## 治理关闭意见回填预览包';
            $lines[] = '';
            foreach ((array)$summary['governance_closure_preview'] as $key => $value) {
                if ($key === 'findings') {
                    continue;
                }
                $lines[] = '- ' . $key . ': ' . (is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : (string)$value);
            }
            if (!empty($summary['governance_closure_preview']['findings'])) {
                $lines[] = '';
                $lines[] = '### 治理关闭意见预览发现项';
                $lines[] = '';
                foreach ((array)$summary['governance_closure_preview']['findings'] as $finding) {
                    $lines[] = '- [' . (string)($finding['severity'] ?? '-') . '] '
                        . (string)($finding['id'] ?? '-') . '：' . (string)($finding['message'] ?? '');
                }
            }
        }
        if (!empty($summary['governance_readiness_refresh'])) {
            $lines[] = '';
            $lines[] = '## 治理就绪刷新预览包';
            $lines[] = '';
            foreach ((array)$summary['governance_readiness_refresh'] as $key => $value) {
                if ($key === 'findings') {
                    continue;
                }
                $lines[] = '- ' . $key . ': ' . (is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : (string)$value);
            }
            if (!empty($summary['governance_readiness_refresh']['findings'])) {
                $lines[] = '';
                $lines[] = '### 治理就绪刷新预览发现项';
                $lines[] = '';
                foreach ((array)$summary['governance_readiness_refresh']['findings'] as $finding) {
                    $lines[] = '- [' . (string)($finding['severity'] ?? '-') . '] '
                        . (string)($finding['id'] ?? '-') . '：' . (string)($finding['message'] ?? '');
                }
            }
        }
        if (!empty($summary['applied'])) {
            $lines[] = '';
            $lines[] = '## 写入结果';
            $lines[] = '';
            foreach ((array)$summary['applied'] as $key => $value) {
                $lines[] = '- ' . $key . ': ' . (string)$value;
            }
        }
        if (!empty($summary['rehearsal_plan'])) {
            $lines[] = '';
            $lines[] = '## Apply-Rehearsal 演练计划';
            $lines[] = '';
            foreach ((array)$summary['rehearsal_plan'] as $key => $value) {
                $lines[] = '- ' . $key . ': ' . (is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : (string)$value);
            }
        }
        if (!empty($summary['write_preview'])) {
            $lines[] = '';
            $lines[] = '## LIMS 第一阶段写库行级预览包';
            $lines[] = '';
            foreach ((array)$summary['write_preview'] as $key => $value) {
                $lines[] = '- ' . $key . ': ' . (is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : (string)$value);
            }
        }
        if (!empty($summary['stage2_preview'])) {
            $lines[] = '';
            $lines[] = '## LIMS 第二阶段结构化导入行级预览包';
            $lines[] = '';
            foreach ((array)$summary['stage2_preview'] as $key => $value) {
                $lines[] = '- ' . $key . ': ' . (is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : (string)$value);
            }
        }
        $lines[] = '';
        $lines[] = '## 发现项';
        $lines[] = '';
        if (!empty($summary['findings'])) {
            foreach ((array)$summary['findings'] as $finding) {
                $lines[] = '- [' . (string)($finding['severity'] ?? '-') . '] '
                    . (string)($finding['id'] ?? '-') . '：' . (string)($finding['message'] ?? '');
            }
        } else {
            $lines[] = '未发现阻断 dry-run 的问题。该结论不代表已写入 LIMS 或已发布受控文件。';
        }
        $lines[] = '';
        $lines[] = '## 边界';
        $lines[] = '';
        foreach ((array)($summary['boundary'] ?? []) as $boundary) {
            $lines[] = '- ' . (string)$boundary;
        }
        $lines[] = '';
        return implode("\n", $lines);
    }

    public static function writeReports(array $summary, ?string $jsonOut, ?string $mdOut): void
    {
        $safe = $summary;
        unset($safe['_rows']);
        if ($jsonOut) {
            self::ensureDirectory(dirname($jsonOut));
            file_put_contents($jsonOut, json_encode($safe, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n");
        }
        if ($mdOut) {
            self::ensureDirectory(dirname($mdOut));
            file_put_contents($mdOut, self::renderMarkdown($safe));
        }
    }

    public static function writePreviewPackage(array $summary, string $outputDir): array
    {
        if (empty($summary['_rows']) || !is_array($summary['_rows'])) {
            throw new \RuntimeException('生成写库预览包需要保留 _rows；请在 dry-run 或 apply-rehearsal 阶段调用。');
        }
        $outputDir = rtrim($outputDir, '/\\');
        self::ensureDirectory($outputDir);

        $rows = (array)$summary['_rows'];
        $companyId = (string)Config::get('qms.company_id');
        $generatedAt = date('Y-m-d\TH:i:s');
        $documentRows = (array)($rows['documents'] ?? []);
        $recordTemplateRows = (array)($rows['record_form_templates'] ?? []);
        $sourceRows = (array)($rows['external_sources'] ?? []);

        $documentCodes = array_values(array_filter(array_map(
            static fn(array $row): string => trim((string)($row['doc_number'] ?? '')),
            $documentRows
        )));
        $recordCodes = array_values(array_filter(array_map(
            static fn(array $row): string => trim((string)($row['doc_number'] ?? '')),
            $recordTemplateRows
        )));
        $sourceCodes = array_values(array_filter(array_map(
            static fn(array $row): string => trim((string)($row['source_code'] ?? '')),
            $sourceRows
        )));

        $existingDocuments = self::existingDocumentRows($documentCodes);
        $existingTemplates = self::existingRecordTemplateRows($recordCodes);
        $existingSources = self::existingSourceRows($sourceCodes);
        $knownDocumentSources = [];
        foreach ($existingDocuments as $code => $row) {
            $knownDocumentSources[(string)$code] = 'existing_document:' . (string)($row['id'] ?? '');
        }

        $documentPreview = [];
        foreach ($documentRows as $row) {
            $docNumber = trim((string)($row['doc_number'] ?? ''));
            $sourceAction = (string)($row['action'] ?? '');
            $previewAction = 'create_draft';
            if ($docNumber === '') {
                $previewAction = 'skip_blank_doc_number';
            } elseif ($sourceAction === 'reference_existing_current') {
                $previewAction = 'skip_reference_existing_current';
            } elseif (
                isset($existingDocuments[$docNumber])
                && (string)($row['import_mode'] ?? '') === 'manual_review_then_revision_flow'
            ) {
                $previewAction = 'plan_existing_document_revision';
            } elseif (isset($existingDocuments[$docNumber])) {
                $previewAction = 'skip_existing_document';
            } else {
                $knownDocumentSources[$docNumber] = 'candidate_document_created_same_apply';
            }
            $documentPreview[] = [
                'preview_action' => $previewAction,
                'target_table' => 'documents',
                'company_id' => $companyId,
                'id_policy' => $previewAction === 'create_draft' ? 'qms_uuid_at_apply_time' : '',
                'level' => (string)($row['level'] ?? '2'),
                'doc_number' => $docNumber,
                'title' => (string)($row['title'] ?? ''),
                'version' => (string)($row['version'] ?? '候选'),
                'status' => $previewAction === 'create_draft' ? 'draft' : (string)($row['status'] ?? ''),
                'publish' => $previewAction === 'create_draft' ? '0' : (string)($row['publish'] ?? ''),
                'soft_delete' => $previewAction === 'create_draft' ? '0' : '',
                'change_reason' => (string)($row['change_reason'] ?? ''),
                'existing_match' => isset($existingDocuments[$docNumber]) ? 'yes' : 'no',
                'existing_status' => (string)($existingDocuments[$docNumber]['status'] ?? ''),
                'source_stage_file' => (string)($row['source_stage_file'] ?? ''),
                'import_mode' => (string)($row['import_mode'] ?? ''),
                'created_policy' => $previewAction === 'create_draft' ? 'apply_time' : '',
                'modified_policy' => $previewAction === 'create_draft' ? 'apply_time' : '',
            ];
        }

        $recordTemplatePreview = [];
        foreach ($recordTemplateRows as $row) {
            $docNumber = trim((string)($row['doc_number'] ?? ''));
            $schema = json_decode((string)($row['field_schema_json'] ?? '[]'), true);
            $schema = is_array($schema) ? $schema : [];
            $procedureResolution = self::firstResolvableDocumentPreview(
                (string)($row['procedure_doc_numbers'] ?? ''),
                $knownDocumentSources
            );
            $previewAction = 'create_draft';
            if ($docNumber === '') {
                $previewAction = 'skip_blank_doc_number';
            } elseif (isset($existingTemplates[$docNumber])) {
                $previewAction = 'skip_existing_record_template';
            }
            $recordTemplatePreview[] = [
                'preview_action' => $previewAction,
                'target_table' => 'record_form_templates',
                'company_id' => $companyId,
                'id_policy' => $previewAction === 'create_draft' ? 'qms_uuid_at_apply_time' : '',
                'document_id_resolution' => (string)($knownDocumentSources[$docNumber] ?? 'not_resolved_before_apply'),
                'procedure_doc_id_resolution' => $procedureResolution,
                'doc_number' => $docNumber,
                'name' => (string)($row['name'] ?? ''),
                'module' => (string)($row['module'] ?? 'QMS候选记录模板'),
                'print_template_key' => (string)($row['print_template_key'] ?? 'generic_record_form'),
                'field_schema_length' => (string)count($schema),
                'field_schema_sha1' => sha1((string)($row['field_schema_json'] ?? '[]')),
                'version' => (string)($row['version'] ?? '候选'),
                'status' => $previewAction === 'create_draft' ? 'draft' : (string)($row['status'] ?? ''),
                'review_status' => $previewAction === 'create_draft' ? 'pending' : (string)($row['review_status'] ?? ''),
                'publish' => $previewAction === 'create_draft' ? '0' : '',
                'soft_delete' => $previewAction === 'create_draft' ? '0' : '',
                'existing_match' => isset($existingTemplates[$docNumber]) ? 'yes' : 'no',
                'existing_status' => (string)($existingTemplates[$docNumber]['status'] ?? ''),
                'review_note' => (string)($row['review_note'] ?? ''),
                'created_policy' => $previewAction === 'create_draft' ? 'apply_time' : '',
                'modified_policy' => $previewAction === 'create_draft' ? 'apply_time' : '',
            ];
        }

        $sourcePreview = [];
        foreach ($sourceRows as $row) {
            $sourceCode = trim((string)($row['source_code'] ?? ''));
            $previewAction = isset($existingSources[$sourceCode]) ? 'update_existing_source' : 'create_source';
            if ($sourceCode === '') {
                $previewAction = 'skip_blank_source_code';
            }
            $status = (string)($row['status'] ?? 'draft');
            $sourcePreview[] = [
                'preview_action' => $previewAction,
                'target_table' => 'qms_sources',
                'company_id' => $companyId,
                'id_policy' => $previewAction === 'create_source' ? 'qms_uuid_at_apply_time' : (string)($existingSources[$sourceCode]['id'] ?? ''),
                'source_code' => $sourceCode,
                'name' => (string)($row['name'] ?? ''),
                'source_type' => (string)($row['source_type'] ?? 'external_standard'),
                'version' => (string)($row['version'] ?? ''),
                'freshness_checked_at' => (string)self::nullableDate((string)($row['freshness_checked_at'] ?? '')),
                'freshness_result' => (string)($row['freshness_result'] ?? ''),
                'freshness_evidence' => (string)($row['freshness_evidence'] ?? ''),
                'freshness_status' => (string)($row['freshness_status'] ?? 'unknown'),
                'status' => $status,
                'publish' => $status === 'obsolete' ? '0' : '1',
                'soft_delete' => '0',
                'existing_match' => isset($existingSources[$sourceCode]) ? 'yes' : 'no',
                'modified_policy' => 'apply_time',
                'created_policy' => $previewAction === 'create_source' ? 'apply_time' : '',
            ];
        }

        $files = [
            'manifest' => 'write_preview_manifest.json',
            'documents_preview' => '01-documents-draft-preview.csv',
            'record_templates_preview' => '02-record-form-templates-draft-preview.csv',
            'sources_preview' => '03-qms-sources-upsert-preview.csv',
            'summary' => '04-write-preview-summary.md',
            'readme' => 'README.md',
        ];
        self::writeCsvFile($outputDir . DIRECTORY_SEPARATOR . $files['documents_preview'], $documentPreview, [
            'preview_action', 'target_table', 'company_id', 'id_policy', 'level', 'doc_number', 'title', 'version',
            'status', 'publish', 'soft_delete', 'change_reason', 'existing_match', 'existing_status',
            'source_stage_file', 'import_mode', 'created_policy', 'modified_policy',
        ]);
        self::writeCsvFile($outputDir . DIRECTORY_SEPARATOR . $files['record_templates_preview'], $recordTemplatePreview, [
            'preview_action', 'target_table', 'company_id', 'id_policy', 'document_id_resolution',
            'procedure_doc_id_resolution', 'doc_number', 'name', 'module', 'print_template_key',
            'field_schema_length', 'field_schema_sha1', 'version', 'status', 'review_status', 'publish',
            'soft_delete', 'existing_match', 'existing_status', 'review_note', 'created_policy', 'modified_policy',
        ]);
        self::writeCsvFile($outputDir . DIRECTORY_SEPARATOR . $files['sources_preview'], $sourcePreview, [
            'preview_action', 'target_table', 'company_id', 'id_policy', 'source_code', 'name', 'source_type',
            'version', 'freshness_checked_at', 'freshness_result', 'freshness_evidence', 'freshness_status',
            'status', 'publish', 'soft_delete', 'existing_match', 'modified_policy', 'created_policy',
        ]);

        $manifest = [
            'generated_at' => $generatedAt,
            'status' => 'write_preview_no_database_write',
            'mode' => (string)($summary['mode'] ?? 'dry-run'),
            'package_dir' => (string)($summary['package_dir'] ?? ''),
            'preview_dir' => $outputDir,
            'guardrails' => [
                '本包只预览 LIMS 第一阶段可能写入的表和字段，不写数据库。',
                '本包不代表人工评审通过、受控发布或正式写库授权。',
                '真实 apply 仍必须使用正式 human_review_pack 且经用户明确授权。',
                '结构化文件、手册块、追溯关系和真实运行记录仍不在第一阶段写入范围内。',
                '资质状态口径：已取得 CMA，CNAS 申请中；不得表述为已取得 CNAS。',
                'jewelry-qms 仍为建设中系统，只进入实施计划、演练和治理准备材料，不写入质量手册正文。',
            ],
            'database_write_performed' => 0,
            'counts' => [
                'documents_preview_rows' => count($documentPreview),
                'documents_create_draft_rows' => self::countPreviewAction($documentPreview, 'create_draft'),
                'documents_revision_required_rows' => self::countPreviewAction($documentPreview, 'plan_existing_document_revision'),
                'documents_skip_reference_rows' => self::countPreviewAction($documentPreview, 'skip_reference_existing_current'),
                'record_template_preview_rows' => count($recordTemplatePreview),
                'record_template_create_draft_rows' => self::countPreviewAction($recordTemplatePreview, 'create_draft'),
                'source_preview_rows' => count($sourcePreview),
                'source_create_rows' => self::countPreviewAction($sourcePreview, 'create_source'),
                'source_update_rows' => self::countPreviewAction($sourcePreview, 'update_existing_source'),
            ],
            'files' => $files,
        ];
        file_put_contents($outputDir . DIRECTORY_SEPARATOR . $files['manifest'], json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n");
        file_put_contents($outputDir . DIRECTORY_SEPARATOR . $files['summary'], self::renderWritePreviewSummary($manifest));
        file_put_contents($outputDir . DIRECTORY_SEPARATOR . $files['readme'], self::renderWritePreviewReadme($manifest));
        return [
            'preview_dir' => $outputDir,
            'status' => 'passed',
            'documents_preview_rows' => count($documentPreview),
            'documents_create_draft_rows' => self::countPreviewAction($documentPreview, 'create_draft'),
            'documents_revision_required_rows' => self::countPreviewAction($documentPreview, 'plan_existing_document_revision'),
            'record_template_preview_rows' => count($recordTemplatePreview),
            'record_template_create_draft_rows' => self::countPreviewAction($recordTemplatePreview, 'create_draft'),
            'source_preview_rows' => count($sourcePreview),
            'database_write_performed' => 0,
        ];
    }

    public static function writeStage2PreviewPackage(array $summary, string $outputDir): array
    {
        if (empty($summary['_rows']) || !is_array($summary['_rows'])) {
            throw new \RuntimeException('生成第二阶段结构化导入预览包需要保留 _rows；请在 dry-run 或 apply-rehearsal 阶段调用。');
        }
        $outputDir = rtrim($outputDir, '/\\');
        self::ensureDirectory($outputDir);

        $rows = (array)$summary['_rows'];
        $companyId = (string)Config::get('qms.company_id');
        $generatedAt = date('Y-m-d\TH:i:s');
        $documentRows = (array)($rows['documents'] ?? []);
        $structuredRows = (array)($rows['structured_documents'] ?? []);
        $blockRows = (array)($rows['manual_blocks'] ?? []);
        $recordTemplateRows = (array)($rows['record_form_templates'] ?? []);

        $documentCodes = array_values(array_filter(array_map(
            static fn(array $row): string => trim((string)($row['doc_number'] ?? '')),
            $documentRows
        )));
        $recordCodes = array_values(array_filter(array_map(
            static fn(array $row): string => trim((string)($row['doc_number'] ?? '')),
            $recordTemplateRows
        )));
        $existingDocuments = self::existingDocumentRows($documentCodes);
        $existingTemplates = self::existingRecordTemplateRows($recordCodes);
        $existingStructured = self::existingStructuredDocumentRows($structuredRows);

        $knownDocumentSources = [];
        foreach ($documentRows as $row) {
            $docNumber = trim((string)($row['doc_number'] ?? ''));
            if ($docNumber === '') {
                continue;
            }
            if (isset($existingDocuments[$docNumber])) {
                $knownDocumentSources[$docNumber] = 'existing_document:' . (string)($existingDocuments[$docNumber]['id'] ?? '');
                continue;
            }
            if ((string)($row['action'] ?? '') !== 'reference_existing_current') {
                $knownDocumentSources[$docNumber] = 'candidate_document_after_phase1_apply';
            }
        }

        $knownTemplateSources = [];
        foreach ($recordTemplateRows as $row) {
            $docNumber = trim((string)($row['doc_number'] ?? ''));
            if ($docNumber === '') {
                continue;
            }
            $knownTemplateSources[$docNumber] = isset($existingTemplates[$docNumber])
                ? 'existing_record_template:' . (string)($existingTemplates[$docNumber]['id'] ?? '')
                : 'candidate_record_template_after_phase1_apply';
        }

        $structuredPreview = [];
        $knownStructuredSources = [];
        foreach ($structuredRows as $row) {
            $docNumber = trim((string)($row['doc_number'] ?? ''));
            $role = (string)($row['document_role'] ?? '');
            $version = (string)($row['version'] ?? '');
            $key = self::structuredDocumentKey($role, $docNumber, $version);
            $existing = $existingStructured[$key] ?? null;
            $action = (string)($row['action'] ?? '');
            if ($docNumber === 'XZTC/SC' && $action === 'revision_candidate') {
                $previewAction = 'plan_manual_structured_refresh_after_revision';
                $phaseDependency = 'manual_revision_human_decision_required';
            } elseif ($action === 'reference_existing_current') {
                $previewAction = $existing ? 'refresh_existing_structured_reference' : 'create_structured_reference';
                $phaseDependency = 'existing_document_available';
            } else {
                $previewAction = $existing ? 'refresh_existing_structured_candidate' : 'create_structured_candidate_after_phase1';
                $phaseDependency = 'phase1_apply_and_human_review_required';
            }
            $structuredResolution = $existing
                ? 'existing_structured_document:' . (string)($existing['id'] ?? '')
                : 'candidate_structured_document_at_stage2_apply';
            if ($docNumber !== '') {
                $knownStructuredSources[$docNumber] = $structuredResolution;
            }
            $statusPlan = (string)($row['status'] ?? 'structured');
            $structuredPreview[] = [
                'preview_action' => $previewAction,
                'target_table' => 'qms_structured_documents',
                'write_now' => 'no',
                'company_id' => $companyId,
                'id_policy' => $existing ? (string)($existing['id'] ?? '') : 'qms_uuid_at_stage2_apply_time',
                'document_id_resolution' => (string)($knownDocumentSources[$docNumber] ?? 'not_resolved_before_apply'),
                'document_role' => $role,
                'doc_number' => $docNumber,
                'title' => (string)($row['title'] ?? ''),
                'version' => $version,
                'source_status' => (string)($row['source_status'] ?? ''),
                'markdown_path' => (string)($row['markdown_path'] ?? ''),
                'render_status_plan' => 'not_rendered',
                'status_plan' => $statusPlan,
                'publish_plan' => $statusPlan === 'draft' ? '0' : 'after_human_review',
                'soft_delete_plan' => '0',
                'existing_structured_match' => $existing ? 'yes' : 'no',
                'existing_structured_status' => (string)($existing['status'] ?? ''),
                'phase_dependency' => $phaseDependency,
                'review_note' => (string)($row['review_note'] ?? ''),
            ];
        }

        $blockPreview = [];
        $linkPreview = [];
        $procedureLinks = 0;
        $attachmentLinks = 0;
        $recordLinks = 0;
        foreach ($blockRows as $row) {
            $structuredDocNumber = trim((string)($row['structured_doc_number'] ?? ''));
            $stableKey = trim((string)($row['stable_key'] ?? ''));
            $blockPreview[] = [
                'preview_action' => 'create_manual_block_after_manual_revision',
                'target_table' => 'qms_document_blocks',
                'write_now' => 'no',
                'company_id' => $companyId,
                'id_policy' => 'qms_uuid_at_stage2_apply_time',
                'structured_document_resolution' => (string)($knownStructuredSources[$structuredDocNumber] ?? 'not_resolved_before_apply'),
                'document_id_resolution' => (string)($knownDocumentSources[$structuredDocNumber] ?? 'not_resolved_before_apply'),
                'stable_key' => $stableKey,
                'section_number' => (string)($row['section_number'] ?? ''),
                'title' => (string)($row['title'] ?? ''),
                'block_type' => (string)($row['block_type'] ?? 'control_requirement'),
                'markdown_policy' => 'extract_from_candidate_manual_after_human_review',
                'sort_order' => (string)($row['sort_order'] ?? '0'),
                'source_locator' => (string)($row['source_locator'] ?? ''),
                'status_plan' => 'effective_after_approval',
                'publish_plan' => 'after_human_review',
                'soft_delete_plan' => '0',
                'link_confidence' => (string)($row['link_confidence'] ?? ''),
                'phase_dependency' => 'manual_revision_human_decision_required',
            ];

            foreach (self::splitCodes((string)($row['procedure_doc_numbers'] ?? '')) as $code) {
                $procedureLinks++;
                $linkPreview[] = self::stage2LinkPreviewRow(
                    $companyId,
                    $stableKey,
                    'procedure_document',
                    $code,
                    (string)($knownDocumentSources[$code] ?? 'not_resolved_before_apply'),
                    (string)($row['link_relation_type'] ?? 'implements'),
                    (string)($row['link_confidence'] ?? 'review_required'),
                    'procedure_doc_numbers'
                );
            }
            foreach (self::splitCodes((string)($row['attachment_form_doc_numbers'] ?? '')) as $code) {
                $attachmentLinks++;
                $linkPreview[] = self::stage2LinkPreviewRow(
                    $companyId,
                    $stableKey,
                    'attachment_form_document',
                    $code,
                    (string)($knownDocumentSources[$code] ?? 'not_resolved_before_apply'),
                    (string)($row['link_relation_type'] ?? 'implements'),
                    (string)($row['link_confidence'] ?? 'review_required'),
                    'attachment_form_doc_numbers'
                );
            }
            foreach (self::splitCodes((string)($row['record_template_numbers'] ?? '')) as $code) {
                $recordLinks++;
                $linkPreview[] = self::stage2LinkPreviewRow(
                    $companyId,
                    $stableKey,
                    'record_form_template',
                    $code,
                    (string)($knownTemplateSources[$code] ?? 'not_resolved_before_apply'),
                    'requires_record',
                    (string)($row['link_confidence'] ?? 'review_required'),
                    'record_template_numbers'
                );
            }
        }

        $files = [
            'manifest' => 'stage2_preview_manifest.json',
            'structured_documents_preview' => '01-structured-documents-preview.csv',
            'document_blocks_preview' => '02-document-blocks-preview.csv',
            'document_block_links_preview' => '03-document-block-links-preview.csv',
            'summary' => '04-stage2-preview-summary.md',
            'readme' => 'README.md',
        ];
        self::writeCsvFile($outputDir . DIRECTORY_SEPARATOR . $files['structured_documents_preview'], $structuredPreview, [
            'preview_action', 'target_table', 'write_now', 'company_id', 'id_policy', 'document_id_resolution',
            'document_role', 'doc_number', 'title', 'version', 'source_status', 'markdown_path', 'render_status_plan',
            'status_plan', 'publish_plan', 'soft_delete_plan', 'existing_structured_match', 'existing_structured_status',
            'phase_dependency', 'review_note',
        ]);
        self::writeCsvFile($outputDir . DIRECTORY_SEPARATOR . $files['document_blocks_preview'], $blockPreview, [
            'preview_action', 'target_table', 'write_now', 'company_id', 'id_policy', 'structured_document_resolution',
            'document_id_resolution', 'stable_key', 'section_number', 'title', 'block_type', 'markdown_policy',
            'sort_order', 'source_locator', 'status_plan', 'publish_plan', 'soft_delete_plan', 'link_confidence',
            'phase_dependency',
        ]);
        self::writeCsvFile($outputDir . DIRECTORY_SEPARATOR . $files['document_block_links_preview'], $linkPreview, [
            'preview_action', 'target_table', 'write_now', 'company_id', 'id_policy', 'block_id_resolution',
            'target_type', 'target_code', 'target_id_resolution', 'relation_type', 'confidence', 'source_column',
            'publish_plan', 'soft_delete_plan', 'phase_dependency',
        ]);

        $manifest = [
            'generated_at' => $generatedAt,
            'status' => 'stage2_write_preview_no_database_write',
            'mode' => (string)($summary['mode'] ?? 'dry-run'),
            'package_dir' => (string)($summary['package_dir'] ?? ''),
            'preview_dir' => $outputDir,
            'guardrails' => [
                '本包只预览 LIMS 第二阶段结构化导入可能写入的表和字段，不写数据库。',
                '本包不代表第二阶段已导入，不代表人工评审通过、受控发布或正式写库授权。',
                '第二阶段必须先完成人工评审、第一阶段文件/模板写入、质量手册修订/换版路径确认和人员学习实施确认。',
                '真实 apply 仍必须使用正式 human_review_pack 且经用户明确授权。',
                '资质状态口径：已取得 CMA，CNAS 申请中；不得表述为已取得 CNAS。',
                'jewelry-qms 仍为建设中系统，只进入实施计划、演练和治理准备材料，不写入质量手册正文。',
            ],
            'database_write_performed' => 0,
            'counts' => [
                'structured_documents_preview_rows' => count($structuredPreview),
                'document_blocks_preview_rows' => count($blockPreview),
                'document_block_links_preview_rows' => count($linkPreview),
                'procedure_document_links' => $procedureLinks,
                'attachment_form_document_links' => $attachmentLinks,
                'record_form_template_links' => $recordLinks,
                'manual_revision_dependency_rows' => count(array_filter(
                    $structuredPreview,
                    static fn(array $row): bool => (string)($row['phase_dependency'] ?? '') === 'manual_revision_human_decision_required'
                )) + count($blockPreview) + count($linkPreview),
            ],
            'files' => $files,
        ];
        file_put_contents($outputDir . DIRECTORY_SEPARATOR . $files['manifest'], json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n");
        file_put_contents($outputDir . DIRECTORY_SEPARATOR . $files['summary'], self::renderStage2PreviewSummary($manifest));
        file_put_contents($outputDir . DIRECTORY_SEPARATOR . $files['readme'], self::renderStage2PreviewReadme($manifest));

        return [
            'preview_dir' => $outputDir,
            'status' => 'passed',
            'structured_documents_preview_rows' => count($structuredPreview),
            'document_blocks_preview_rows' => count($blockPreview),
            'document_block_links_preview_rows' => count($linkPreview),
            'procedure_document_links' => $procedureLinks,
            'attachment_form_document_links' => $attachmentLinks,
            'record_form_template_links' => $recordLinks,
            'database_write_performed' => 0,
        ];
    }

    private static function stage2LinkPreviewRow(
        string $companyId,
        string $stableKey,
        string $targetType,
        string $targetCode,
        string $targetResolution,
        string $relationType,
        string $confidence,
        string $sourceColumn
    ): array {
        return [
            'preview_action' => 'create_block_link_after_block_apply',
            'target_table' => 'qms_document_block_links',
            'write_now' => 'no',
            'company_id' => $companyId,
            'id_policy' => 'qms_uuid_at_stage2_apply_time',
            'block_id_resolution' => $stableKey === '' ? 'not_resolved_before_apply' : $stableKey . ' -> candidate_block_at_stage2_apply',
            'target_type' => $targetType,
            'target_code' => $targetCode,
            'target_id_resolution' => $targetResolution,
            'relation_type' => $relationType,
            'confidence' => $confidence,
            'source_column' => $sourceColumn,
            'publish_plan' => 'after_human_review',
            'soft_delete_plan' => '0',
            'phase_dependency' => 'manual_revision_human_decision_required',
        ];
    }

    private static function writeCsvFile(string $path, array $rows, array $fieldnames): void
    {
        self::ensureDirectory(dirname($path));
        $handle = fopen($path, 'w');
        if (!$handle) {
            throw new \RuntimeException('无法写入 CSV：' . $path);
        }
        fputcsv($handle, $fieldnames, ',', '"', '');
        foreach ($rows as $row) {
            $line = [];
            foreach ($fieldnames as $field) {
                $line[] = (string)($row[$field] ?? '');
            }
            fputcsv($handle, $line, ',', '"', '');
        }
        fclose($handle);
    }

    private static function countPreviewAction(array $rows, string $action): int
    {
        return count(array_filter(
            $rows,
            static fn(array $row): bool => (string)($row['preview_action'] ?? '') === $action
        ));
    }

    private static function firstResolvableDocumentPreview(string $procedureCodes, array $knownDocumentSources): string
    {
        foreach (self::splitCodes($procedureCodes) as $code) {
            if (isset($knownDocumentSources[$code])) {
                return $code . ' -> ' . (string)$knownDocumentSources[$code];
            }
        }
        return 'not_resolved_before_apply';
    }

    private static function renderWritePreviewSummary(array $manifest): string
    {
        $lines = [
            '# LIMS 第一阶段写库行级预览汇总',
            '',
            '生成时间：' . (string)($manifest['generated_at'] ?? ''),
            '结论：' . (string)($manifest['status'] ?? ''),
            '命令模式：' . (string)($manifest['mode'] ?? ''),
            '',
            '## 计数',
            '',
        ];
        foreach ((array)($manifest['counts'] ?? []) as $key => $value) {
            $lines[] = '- ' . $key . ': ' . (string)$value;
        }
        $lines[] = '';
        $lines[] = '## 预览文件';
        $lines[] = '';
        foreach ((array)($manifest['files'] ?? []) as $key => $filename) {
            $lines[] = '- ' . $key . ': `' . (string)$filename . '`';
        }
        $lines[] = '';
        $lines[] = '## 边界';
        $lines[] = '';
        foreach ((array)($manifest['guardrails'] ?? []) as $guardrail) {
            $lines[] = '- ' . (string)$guardrail;
        }
        $lines[] = '';
        return implode("\n", $lines);
    }

    private static function renderWritePreviewReadme(array $manifest): string
    {
        $lines = [
            '# LIMS 写库行级预览包',
            '',
            '文件状态：只读预览包，不写数据库，不代表人工评审通过或正式写库授权。',
            '',
            '## 阅读顺序',
            '',
            '1. `04-write-preview-summary.md`：先看总计数和边界。',
            '2. `01-documents-draft-preview.csv`：核对第一阶段 `documents` 草稿行。',
            '3. `02-record-form-templates-draft-preview.csv`：核对 26 个候选记录模板的目标字段、schema 摘要和关联文件解析。',
            '4. `03-qms-sources-upsert-preview.csv`：核对 4 条外来依据的 upsert 预览。',
            '',
            '## 关键解释',
            '',
            '- `create_draft` 表示正式 apply 时可能创建 draft 行，但本预览不创建。',
            '- `plan_existing_document_revision` 表示同编号既有受控文件已存在，候选稿需走既有文件修订/换版治理路线，不能按新增草稿处理。',
            '- `skip_reference_existing_current` 表示 2022 现行程序只做引用匹配，不自动重建为新文件。',
            '- `candidate_document_created_same_apply` 表示记录模板的配套 `documents` 行会在同一阶段作为候选草稿创建。',
            '- `qms_uuid_at_apply_time` 表示真实主键只会在正式 apply 事务内生成。',
            '',
            '## 边界',
            '',
        ];
        foreach ((array)($manifest['guardrails'] ?? []) as $guardrail) {
            $lines[] = '- ' . (string)$guardrail;
        }
        $lines[] = '';
        return implode("\n", $lines);
    }

    private static function renderStage2PreviewSummary(array $manifest): string
    {
        $lines = [
            '# LIMS 第二阶段结构化导入行级预览汇总',
            '',
            '生成时间：' . (string)($manifest['generated_at'] ?? ''),
            '结论：' . (string)($manifest['status'] ?? ''),
            '命令模式：' . (string)($manifest['mode'] ?? ''),
            '',
            '## 计数',
            '',
        ];
        foreach ((array)($manifest['counts'] ?? []) as $key => $value) {
            $lines[] = '- ' . $key . ': ' . (string)$value;
        }
        $lines[] = '';
        $lines[] = '## 预览文件';
        $lines[] = '';
        foreach ((array)($manifest['files'] ?? []) as $key => $filename) {
            $lines[] = '- ' . $key . ': `' . (string)$filename . '`';
        }
        $lines[] = '';
        $lines[] = '## 边界';
        $lines[] = '';
        foreach ((array)($manifest['guardrails'] ?? []) as $guardrail) {
            $lines[] = '- ' . (string)$guardrail;
        }
        $lines[] = '';
        return implode("\n", $lines);
    }

    private static function renderStage2PreviewReadme(array $manifest): string
    {
        $lines = [
            '# LIMS 第二阶段结构化导入行级预览包',
            '',
            '文件状态：只读预览包，不写数据库，不代表第二阶段已导入、人工评审通过或正式写库授权。',
            '',
            '## 阅读顺序',
            '',
            '1. `04-stage2-preview-summary.md`：先看总计数、依赖和边界。',
            '2. `01-structured-documents-preview.csv`：核对 `qms_structured_documents` 结构化文件预览。',
            '3. `02-document-blocks-preview.csv`：核对 `qms_document_blocks` 手册块预览。',
            '4. `03-document-block-links-preview.csv`：核对 `qms_document_block_links` 到程序、附件/表单、记录模板的链接预览。',
            '',
            '## 关键解释',
            '',
            '- `write_now=no` 表示本包只展示将来可能动作，不执行写库。',
            '- `plan_manual_structured_refresh_after_revision` 表示质量手册第五版候选稿必须先走既有 `XZTC/SC` 修订/换版路径。',
            '- `candidate_record_template_after_phase1_apply` 表示记录模板链接依赖第一阶段候选模板写入且人工评审通过。',
            '- `manual_revision_human_decision_required` 表示该行仍受质量手册修订/换版人工决策约束。',
            '- `qms_uuid_at_stage2_apply_time` 表示真实主键只会在未来第二阶段正式 apply 事务内生成。',
            '',
            '## 边界',
            '',
        ];
        foreach ((array)($manifest['guardrails'] ?? []) as $guardrail) {
            $lines[] = '- ' . (string)$guardrail;
        }
        $lines[] = '';
        return implode("\n", $lines);
    }

    private static function buildSummary(
        string $packageDir,
        bool $applyMode,
        bool $ackHumanReviewed,
        ?string $reviewDir = null,
        bool $stage2Check = false,
        ?string $fieldCatalogDir = null,
        ?string $releasePlanDir = null,
        ?string $releaseExecutionDir = null,
        ?string $manualRevisionDir = null,
        ?string $staffTrainingDir = null,
        ?string $stage2ReviewDir = null,
        ?string $stage2ReviewPreviewDir = null,
        ?string $governanceReadinessDir = null,
        ?string $governanceClosureDir = null,
        ?string $governanceClosurePreviewDir = null,
        ?string $governanceReadinessRefreshDir = null,
        ?string $governanceClosureExecutionDir = null,
        ?string $governanceClosurePilotDir = null,
        ?string $governanceClosurePilotReturnDir = null,
        ?string $governanceClosurePilotSourceUpdateDir = null,
        ?string $governanceClosurePilotOperatorWorkbookDir = null,
        ?string $governanceClosurePilotOperatorHandbackDir = null,
        ?string $governanceClosurePilotOperatorCompletionSimulationDir = null,
        bool $applyRehearsal = false
    ): array {
        $packageDir = rtrim($packageDir, '/\\');
        $reviewDir = $reviewDir ? rtrim($reviewDir, '/\\') : null;
        $fieldCatalogDir = $fieldCatalogDir ? rtrim($fieldCatalogDir, '/\\') : null;
        $releasePlanDir = $releasePlanDir ? rtrim($releasePlanDir, '/\\') : null;
        $releaseExecutionDir = $releaseExecutionDir ? rtrim($releaseExecutionDir, '/\\') : null;
        $manualRevisionDir = $manualRevisionDir ? rtrim($manualRevisionDir, '/\\') : null;
        $staffTrainingDir = $staffTrainingDir ? rtrim($staffTrainingDir, '/\\') : null;
        $stage2ReviewDir = $stage2ReviewDir ? rtrim($stage2ReviewDir, '/\\') : null;
        $stage2ReviewPreviewDir = $stage2ReviewPreviewDir ? rtrim($stage2ReviewPreviewDir, '/\\') : null;
        $governanceReadinessDir = $governanceReadinessDir ? rtrim($governanceReadinessDir, '/\\') : null;
        $governanceClosureDir = $governanceClosureDir ? rtrim($governanceClosureDir, '/\\') : null;
        $governanceClosurePreviewDir = $governanceClosurePreviewDir ? rtrim($governanceClosurePreviewDir, '/\\') : null;
        $governanceReadinessRefreshDir = $governanceReadinessRefreshDir ? rtrim($governanceReadinessRefreshDir, '/\\') : null;
        $governanceClosureExecutionDir = $governanceClosureExecutionDir ? rtrim($governanceClosureExecutionDir, '/\\') : null;
        $governanceClosurePilotDir = $governanceClosurePilotDir ? rtrim($governanceClosurePilotDir, '/\\') : null;
        $governanceClosurePilotReturnDir = $governanceClosurePilotReturnDir ? rtrim($governanceClosurePilotReturnDir, '/\\') : null;
        $governanceClosurePilotSourceUpdateDir = $governanceClosurePilotSourceUpdateDir ? rtrim($governanceClosurePilotSourceUpdateDir, '/\\') : null;
        $governanceClosurePilotOperatorWorkbookDir = $governanceClosurePilotOperatorWorkbookDir ? rtrim($governanceClosurePilotOperatorWorkbookDir, '/\\') : null;
        $governanceClosurePilotOperatorHandbackDir = $governanceClosurePilotOperatorHandbackDir ? rtrim($governanceClosurePilotOperatorHandbackDir, '/\\') : null;
        $governanceClosurePilotOperatorCompletionSimulationDir = $governanceClosurePilotOperatorCompletionSimulationDir ? rtrim($governanceClosurePilotOperatorCompletionSimulationDir, '/\\') : null;
        $findings = [];
        $rows = [];
        $manifest = [];
        $manifestPath = $packageDir . DIRECTORY_SEPARATOR . 'preimport_manifest.json';
        if (!is_file($manifestPath)) {
            $findings[] = ['severity' => 'high', 'id' => 'missing_manifest', 'message' => '缺少 preimport_manifest.json。'];
        } else {
            $manifest = json_decode((string)file_get_contents($manifestPath), true) ?: [];
        }

        foreach (self::REQUIRED_FILES as $key => $filename) {
            $path = $packageDir . DIRECTORY_SEPARATOR . $filename;
            if (!is_file($path)) {
                $findings[] = ['severity' => 'high', 'id' => 'missing_' . $key, 'message' => '缺少文件：' . $filename];
                $rows[$key] = [];
                continue;
            }
            $rows[$key] = self::readCsv($path);
        }

        self::checkManifestCounts($manifest, $rows, $findings);
        self::checkRecordSchemas($rows['record_form_templates'] ?? [], $findings);
        self::checkTraceabilityRows($rows['traceability_matrix'] ?? [], $findings);
        self::checkManualBlockRows($rows['manual_blocks'] ?? [], $findings);

        $documentCodes = array_values(array_filter(array_map(static fn(array $row): string => trim((string)($row['doc_number'] ?? '')), $rows['documents'] ?? [])));
        $recordCodes = array_values(array_filter(array_map(static fn(array $row): string => trim((string)($row['doc_number'] ?? '')), $rows['record_form_templates'] ?? [])));
        $sourceCodes = array_values(array_filter(array_map(static fn(array $row): string => trim((string)($row['source_code'] ?? '')), $rows['external_sources'] ?? [])));
        $existingDocuments = self::existingDocumentRows($documentCodes);
        $existingTemplates = self::existingRecordTemplateRows($recordCodes);
        $existingSources = self::existingSourceRows($sourceCodes);

        $referenceProcedureCodes = [];
        $candidateDocumentCodes = [];
        foreach ($rows['documents'] ?? [] as $row) {
            $action = (string)($row['action'] ?? '');
            $docNumber = trim((string)($row['doc_number'] ?? ''));
            if ($docNumber === '') {
                $findings[] = ['severity' => 'high', 'id' => 'blank_document_code', 'message' => 'documents_preimport 存在空文件编号。'];
                continue;
            }
            if ($action === 'reference_existing_current') {
                $referenceProcedureCodes[] = $docNumber;
            } else {
                $candidateDocumentCodes[] = $docNumber;
            }
        }
        $missingReferenceProcedures = array_values(array_diff($referenceProcedureCodes, array_keys($existingDocuments)));
        if ($missingReferenceProcedures !== []) {
            $findings[] = [
                'severity' => 'medium',
                'id' => 'missing_reference_current_documents',
                'message' => 'LIMS 当前库未匹配到部分 2022 程序文件编号：' . implode('、', array_slice($missingReferenceProcedures, 0, 12))
                    . (count($missingReferenceProcedures) > 12 ? ' 等' : ''),
            ];
        }

        $reviewPack = null;
        if ($reviewDir) {
            $reviewPack = self::inspectReviewPack($reviewDir);
            foreach ((array)($reviewPack['findings'] ?? []) as $finding) {
                $findings[] = $finding;
            }
            if ($applyMode && (string)($reviewPack['status'] ?? '') !== 'approved') {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'human_review_pack_not_approved',
                    'message' => '人工评审包尚未全部通过：pending=' . (string)($reviewPack['pending_decisions'] ?? 0)
                        . '，unapproved=' . (string)($reviewPack['unapproved_decisions'] ?? 0) . '。',
                ];
            }
            if ($applyMode && !$applyRehearsal && !empty($reviewPack['is_simulated'])) {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'simulated_human_review_pack_not_allowed_for_apply',
                    'message' => '检测到模拟人审包标识 ' . self::REVIEW_SIMULATION_MARKER . '；模拟包只能用于 --apply-rehearsal，不得作为正式 --apply 的 --review-dir。',
                ];
            }
        }

        $stage2Readiness = null;
        if ($stage2Check) {
            $stage2Readiness = self::inspectStage2Readiness(
                $rows,
                $reviewPack,
                $existingDocuments,
                $existingTemplates,
                $documentCodes,
                $recordCodes
            );
            foreach ((array)($stage2Readiness['findings'] ?? []) as $finding) {
                if (($finding['severity'] ?? '') === 'high') {
                    $findings[] = $finding;
                }
            }
        }

        $fieldCatalog = null;
        if ($fieldCatalogDir) {
            $fieldCatalog = self::inspectFieldCatalog(
                $fieldCatalogDir,
                $rows['record_form_templates'] ?? []
            );
            foreach ((array)($fieldCatalog['findings'] ?? []) as $finding) {
                $findings[] = $finding;
            }
        }

        $releasePlan = null;
        if ($releasePlanDir) {
            $releasePlan = self::inspectReleasePlan(
                $releasePlanDir,
                $rows['documents'] ?? []
            );
            foreach ((array)($releasePlan['findings'] ?? []) as $finding) {
                $findings[] = $finding;
            }
        }

        $releaseExecution = null;
        if ($releaseExecutionDir) {
            $releaseExecution = self::inspectReleaseExecutionTemplates(
                $releaseExecutionDir,
                $releasePlan
            );
            foreach ((array)($releaseExecution['findings'] ?? []) as $finding) {
                $findings[] = $finding;
            }
        }

        $manualRevision = null;
        if ($manualRevisionDir) {
            $manualRevision = self::inspectManualRevisionPath(
                $manualRevisionDir,
                $rows['documents'] ?? [],
                $existingDocuments
            );
            foreach ((array)($manualRevision['findings'] ?? []) as $finding) {
                $findings[] = $finding;
            }
            if ($applyMode && (int)($manualRevision['pending_human_decisions'] ?? 0) > 0) {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'manual_revision_human_decisions_pending',
                    'message' => '质量手册修订/换版路径仍有人工决策未关闭：pending=' . (string)($manualRevision['pending_human_decisions'] ?? 0) . '。',
                ];
            }
        }

        $staffTraining = null;
        if ($staffTrainingDir) {
            $staffTraining = self::inspectStaffTrainingPack($staffTrainingDir, $releasePlan);
            foreach ((array)($staffTraining['findings'] ?? []) as $finding) {
                $findings[] = $finding;
            }
            if (
                $applyMode
                && (
                    (int)($staffTraining['pending_learning_tasks'] ?? 0) > 0
                    || (int)($staffTraining['pending_questions'] ?? 0) > 0
                    || (int)($staffTraining['blank_feedback_decisions'] ?? 0) > 0
                )
            ) {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'staff_training_confirmations_pending',
                    'message' => '机构人员学习实施包仍有学习/理解确认/反馈回填未关闭：pending_tasks='
                        . (string)($staffTraining['pending_learning_tasks'] ?? 0)
                        . '，pending_questions=' . (string)($staffTraining['pending_questions'] ?? 0)
                        . '，blank_feedback_decisions=' . (string)($staffTraining['blank_feedback_decisions'] ?? 0) . '。',
                ];
            }
        }

        $stage2Review = null;
        if ($stage2ReviewDir) {
            $stage2Review = self::inspectStage2ReviewWorkbench($stage2ReviewDir);
            foreach ((array)($stage2Review['findings'] ?? []) as $finding) {
                $findings[] = $finding;
            }
            if ($applyMode && (string)($stage2Review['status'] ?? '') !== 'approved') {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'stage2_review_not_approved',
                    'message' => '第二阶段结构化导入人工复核尚未全部通过：pending='
                        . (string)($stage2Review['pending_decisions'] ?? 0)
                        . '，revise=' . (string)($stage2Review['revise_decisions'] ?? 0)
                        . '，remove=' . (string)($stage2Review['remove_decisions'] ?? 0)
                        . '，invalid=' . (string)($stage2Review['invalid_decisions'] ?? 0)
                        . '，missing_comments=' . (string)($stage2Review['missing_review_comments'] ?? 0) . '。',
                ];
            }
        }

        $stage2ReviewPreview = null;
        if ($stage2ReviewPreviewDir) {
            $stage2ReviewPreview = self::inspectStage2ReviewDecisionPreview($stage2ReviewPreviewDir);
            foreach ((array)($stage2ReviewPreview['findings'] ?? []) as $finding) {
                $findings[] = $finding;
            }
            if ($applyMode && (int)($stage2ReviewPreview['blocking_items'] ?? 0) > 0) {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'stage2_review_preview_has_blocking_items',
                    'message' => '第二阶段复核意见回填预览仍存在阻断项：blocking_items='
                        . (string)($stage2ReviewPreview['blocking_items'] ?? 0)
                        . '，readiness=' . (string)($stage2ReviewPreview['readiness'] ?? '') . '。',
                ];
            }
            if ($applyMode && (string)($stage2ReviewPreview['status'] ?? '') !== 'passed') {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'stage2_review_preview_not_passed',
                    'message' => '第二阶段复核意见回填预览包尚未通过命令层校验：status='
                        . (string)($stage2ReviewPreview['status'] ?? 'missing') . '。',
                ];
            }
        }

        $governanceReadiness = null;
        if ($governanceReadinessDir) {
            $governanceReadiness = self::inspectGovernanceReadinessDashboard($governanceReadinessDir);
            foreach ((array)($governanceReadiness['findings'] ?? []) as $finding) {
                $findings[] = $finding;
            }
            if ($applyMode && (string)($governanceReadiness['status'] ?? '') !== 'passed') {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'governance_readiness_not_passed',
                    'message' => '治理就绪总览包尚未通过命令层校验：status='
                        . (string)($governanceReadiness['status'] ?? 'missing') . '。',
                ];
            }
            if ($applyMode && (int)($governanceReadiness['blocking_tasks'] ?? 0) > 0) {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'governance_readiness_has_blocking_tasks',
                    'message' => '治理就绪总览仍存在阻断任务：blocking_tasks='
                        . (string)($governanceReadiness['blocking_tasks'] ?? 0)
                        . '，readiness=' . (string)($governanceReadiness['readiness'] ?? '') . '。',
                ];
            }
            if ($applyMode && (string)($governanceReadiness['ready_for_lims_apply'] ?? '') !== 'yes') {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'governance_readiness_not_ready_for_apply',
                    'message' => '治理就绪总览未达到 ready_for_lims_apply=yes，不能进入正式 apply 或 apply-rehearsal。',
                ];
            }
        }

        $governanceClosure = null;
        if ($governanceClosureDir) {
            $governanceClosure = self::inspectGovernanceClosureWorkbench($governanceClosureDir);
            foreach ((array)($governanceClosure['findings'] ?? []) as $finding) {
                $findings[] = $finding;
            }
            if ($applyMode && (string)($governanceClosure['status'] ?? '') !== 'passed') {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'governance_closure_not_passed',
                    'message' => '治理关闭工作台尚未通过命令层校验：status='
                        . (string)($governanceClosure['status'] ?? 'missing') . '。',
                ];
            }
            if ($applyMode && (int)($governanceClosure['open_blocking_items'] ?? 0) > 0) {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'governance_closure_has_open_blocking_items',
                    'message' => '治理关闭工作台仍存在未关闭阻断项：open_blocking_items='
                        . (string)($governanceClosure['open_blocking_items'] ?? 0)
                        . '，readiness=' . (string)($governanceClosure['readiness'] ?? '') . '。',
                ];
            }
            if (
                $applyMode
                && (string)($governanceClosure['ready_for_governance_readiness_refresh'] ?? '') !== 'yes'
            ) {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'governance_closure_not_ready_for_refresh',
                    'message' => '治理关闭工作台未达到 ready_for_governance_readiness_refresh=yes，不能进入正式 apply 或 apply-rehearsal。',
                ];
            }
        }

        $governanceClosureExecution = null;
        if ($governanceClosureExecutionDir) {
            $governanceClosureExecution = self::inspectGovernanceClosureExecutionPack($governanceClosureExecutionDir);
            foreach ((array)($governanceClosureExecution['findings'] ?? []) as $finding) {
                $findings[] = $finding;
            }
            if ($applyMode && (string)($governanceClosureExecution['status'] ?? '') !== 'passed') {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'governance_closure_execution_not_passed',
                    'message' => '治理闭环执行包尚未通过命令层校验：status='
                        . (string)($governanceClosureExecution['status'] ?? 'missing') . '。',
                ];
            }
            if ($applyMode && (int)($governanceClosureExecution['pending_signature_rows'] ?? 0) > 0) {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'governance_closure_execution_signatures_pending',
                    'message' => '治理闭环执行包仍有岗位签核未完成：pending_signature_rows='
                        . (string)($governanceClosureExecution['pending_signature_rows'] ?? 0)
                        . '，readiness=' . (string)($governanceClosureExecution['readiness'] ?? '') . '。',
                ];
            }
            if ($applyMode && (int)($governanceClosureExecution['pending_handoff_checks'] ?? 0) > 0) {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'governance_closure_execution_handoffs_pending',
                    'message' => '治理闭环执行包仍有交接复核未完成：pending_handoff_checks='
                        . (string)($governanceClosureExecution['pending_handoff_checks'] ?? 0)
                        . '。',
                ];
            }
            if ($applyMode && (int)($governanceClosureExecution['pending_route_items'] ?? 0) > 0) {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'governance_closure_execution_routes_pending',
                    'message' => '治理闭环执行包仍有回填路径未完成：pending_route_items='
                        . (string)($governanceClosureExecution['pending_route_items'] ?? 0)
                        . '。',
                ];
            }
            if (
                $applyMode
                && (string)($governanceClosureExecution['ready_for_governance_closure_preview'] ?? '') !== 'yes'
            ) {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'governance_closure_execution_not_ready_for_preview',
                    'message' => '治理闭环执行包未达到 ready_for_governance_closure_preview=yes，不能进入正式 apply 或 apply-rehearsal。',
                ];
            }
        }

        $governanceClosurePilot = null;
        if ($governanceClosurePilotDir) {
            $governanceClosurePilot = self::inspectGovernanceClosurePilotPack($governanceClosurePilotDir);
            foreach ((array)($governanceClosurePilot['findings'] ?? []) as $finding) {
                $findings[] = $finding;
            }
            if ($applyMode && (string)($governanceClosurePilot['status'] ?? '') !== 'passed') {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'governance_closure_pilot_not_passed',
                    'message' => '治理关闭最小试点包尚未通过命令层校验：status='
                        . (string)($governanceClosurePilot['status'] ?? 'missing') . '。',
                ];
            }
            if ($applyMode && (int)($governanceClosurePilot['pending_pilot_evidence'] ?? 0) > 0) {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'governance_closure_pilot_evidence_pending',
                    'message' => '治理关闭最小试点包仍有证据填写未完成：pending_pilot_evidence='
                        . (string)($governanceClosurePilot['pending_pilot_evidence'] ?? 0)
                        . '，readiness=' . (string)($governanceClosurePilot['readiness'] ?? '') . '。',
                ];
            }
            if ($applyMode && (int)($governanceClosurePilot['pending_pilot_handoffs'] ?? 0) > 0) {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'governance_closure_pilot_handoffs_pending',
                    'message' => '治理关闭最小试点包仍有签核/交接未完成：pending_pilot_handoffs='
                        . (string)($governanceClosurePilot['pending_pilot_handoffs'] ?? 0) . '。',
                ];
            }
            if (
                $applyMode
                && (string)($governanceClosurePilot['ready_for_governance_closure_preview'] ?? '') !== 'yes'
            ) {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'governance_closure_pilot_not_ready_for_preview',
                    'message' => '治理关闭最小试点包未达到 ready_for_governance_closure_preview=yes，不能进入正式 apply 或 apply-rehearsal。',
                ];
            }
        }

        $governanceClosurePilotReturn = null;
        if ($governanceClosurePilotReturnDir) {
            $governanceClosurePilotReturn = self::inspectGovernanceClosurePilotReturnPreview($governanceClosurePilotReturnDir);
            foreach ((array)($governanceClosurePilotReturn['findings'] ?? []) as $finding) {
                $findings[] = $finding;
            }
            if ($applyMode && (string)($governanceClosurePilotReturn['status'] ?? '') !== 'passed') {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'governance_closure_pilot_return_not_passed',
                    'message' => '治理关闭试点回填预览尚未通过命令层校验：status='
                        . (string)($governanceClosurePilotReturn['status'] ?? 'missing') . '。',
                ];
            }
            if ($applyMode && (int)($governanceClosurePilotReturn['missing_field_rows'] ?? 0) > 0) {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'governance_closure_pilot_return_missing_fields',
                    'message' => '治理关闭试点回填预览仍有缺字段：missing_field_rows='
                        . (string)($governanceClosurePilotReturn['missing_field_rows'] ?? 0)
                        . '，readiness=' . (string)($governanceClosurePilotReturn['readiness'] ?? '') . '。',
                ];
            }
            if ($applyMode && (int)($governanceClosurePilotReturn['blocking_return_items'] ?? 0) > 0) {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'governance_closure_pilot_return_has_blocking_items',
                    'message' => '治理关闭试点回填预览仍存在阻断回填项：blocking_return_items='
                        . (string)($governanceClosurePilotReturn['blocking_return_items'] ?? 0) . '。',
                ];
            }
            if (
                $applyMode
                && (string)($governanceClosurePilotReturn['ready_for_governance_closure_preview'] ?? '') !== 'yes'
            ) {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'governance_closure_pilot_return_not_ready_for_preview',
                    'message' => '治理关闭试点回填预览未达到 ready_for_governance_closure_preview=yes，不能进入正式 apply 或 apply-rehearsal。',
                ];
            }
        }

        $governanceClosurePilotSourceUpdate = null;
        if ($governanceClosurePilotSourceUpdateDir) {
            $governanceClosurePilotSourceUpdate = self::inspectGovernanceClosurePilotSourceUpdateRehearsal($governanceClosurePilotSourceUpdateDir);
            foreach ((array)($governanceClosurePilotSourceUpdate['findings'] ?? []) as $finding) {
                $findings[] = $finding;
            }
            if ($applyMode && (string)($governanceClosurePilotSourceUpdate['status'] ?? '') !== 'passed') {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'governance_closure_pilot_source_update_not_passed',
                    'message' => '治理关闭试点源工作台回填补丁预演尚未通过命令层校验：status='
                        . (string)($governanceClosurePilotSourceUpdate['status'] ?? 'missing') . '。',
                ];
            }
            if ($applyMode && (int)($governanceClosurePilotSourceUpdate['blocked_patch_rows'] ?? 0) > 0) {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'governance_closure_pilot_source_update_has_blocked_patches',
                    'message' => '治理关闭试点源工作台回填补丁预演仍存在阻断补丁：blocked_patch_rows='
                        . (string)($governanceClosurePilotSourceUpdate['blocked_patch_rows'] ?? 0)
                        . '，readiness=' . (string)($governanceClosurePilotSourceUpdate['readiness'] ?? '') . '。',
                ];
            }
            if (
                $applyMode
                && (string)($governanceClosurePilotSourceUpdate['ready_for_source_workbench_update'] ?? '') !== 'yes'
            ) {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'governance_closure_pilot_source_update_not_ready_for_source_update',
                    'message' => '治理关闭试点源工作台回填补丁预演未达到 ready_for_source_workbench_update=yes，不能进入正式 apply 或 apply-rehearsal。',
                ];
            }
        }

        $governanceClosurePilotOperatorWorkbook = null;
        if ($governanceClosurePilotOperatorWorkbookDir) {
            $governanceClosurePilotOperatorWorkbook = self::inspectGovernanceClosurePilotOperatorWorkbook($governanceClosurePilotOperatorWorkbookDir);
            foreach ((array)($governanceClosurePilotOperatorWorkbook['findings'] ?? []) as $finding) {
                $findings[] = $finding;
            }
            if ($applyMode && (string)($governanceClosurePilotOperatorWorkbook['status'] ?? '') !== 'passed') {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'governance_closure_pilot_operator_workbook_not_passed',
                    'message' => '治理关闭试点人工执行工作簿尚未通过命令层校验：status='
                        . (string)($governanceClosurePilotOperatorWorkbook['status'] ?? 'missing') . '。',
                ];
            }
            if ($applyMode && (int)($governanceClosurePilotOperatorWorkbook['pending_workbook_items'] ?? 0) > 0) {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'governance_closure_pilot_operator_workbook_items_pending',
                    'message' => '治理关闭试点人工执行工作簿仍有主任务未完成：pending_workbook_items='
                        . (string)($governanceClosurePilotOperatorWorkbook['pending_workbook_items'] ?? 0) . '。',
                ];
            }
            if ($applyMode && (int)($governanceClosurePilotOperatorWorkbook['pending_field_items'] ?? 0) > 0) {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'governance_closure_pilot_operator_workbook_fields_pending',
                    'message' => '治理关闭试点人工执行工作簿仍有逐字段填写项未完成：pending_field_items='
                        . (string)($governanceClosurePilotOperatorWorkbook['pending_field_items'] ?? 0) . '。',
                ];
            }
            if ($applyMode && (int)($governanceClosurePilotOperatorWorkbook['pending_handoff_items'] ?? 0) > 0) {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'governance_closure_pilot_operator_workbook_handoffs_pending',
                    'message' => '治理关闭试点人工执行工作簿仍有签核交接项未完成：pending_handoff_items='
                        . (string)($governanceClosurePilotOperatorWorkbook['pending_handoff_items'] ?? 0) . '。',
                ];
            }
            if (
                $applyMode
                && (string)($governanceClosurePilotOperatorWorkbook['ready_for_pilot_return_preview'] ?? '') !== 'yes'
            ) {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'governance_closure_pilot_operator_workbook_not_ready_for_return_preview',
                    'message' => '治理关闭试点人工执行工作簿未达到 ready_for_pilot_return_preview=yes，不能进入正式 apply 或 apply-rehearsal。',
                ];
            }
        }

        $governanceClosurePilotOperatorHandback = null;
        if ($governanceClosurePilotOperatorHandbackDir) {
            $governanceClosurePilotOperatorHandback = self::inspectGovernanceClosurePilotOperatorHandback($governanceClosurePilotOperatorHandbackDir);
            foreach ((array)($governanceClosurePilotOperatorHandback['findings'] ?? []) as $finding) {
                $findings[] = $finding;
            }
            if ($applyMode && (string)($governanceClosurePilotOperatorHandback['status'] ?? '') !== 'passed') {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'governance_closure_pilot_operator_handback_not_passed',
                    'message' => '治理关闭试点真实执行交回包尚未通过命令层校验：status='
                        . (string)($governanceClosurePilotOperatorHandback['status'] ?? 'missing') . '。',
                ];
            }
            if ($applyMode && (int)($governanceClosurePilotOperatorHandback['pending_workbook_items'] ?? 0) > 0) {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'governance_closure_pilot_operator_handback_items_pending',
                    'message' => '治理关闭试点真实执行交回包仍有主任务未完成：pending_workbook_items='
                        . (string)($governanceClosurePilotOperatorHandback['pending_workbook_items'] ?? 0) . '。',
                ];
            }
            if ($applyMode && (int)($governanceClosurePilotOperatorHandback['pending_field_items'] ?? 0) > 0) {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'governance_closure_pilot_operator_handback_fields_pending',
                    'message' => '治理关闭试点真实执行交回包仍有逐字段交回项未完成：pending_field_items='
                        . (string)($governanceClosurePilotOperatorHandback['pending_field_items'] ?? 0) . '。',
                ];
            }
            if ($applyMode && (int)($governanceClosurePilotOperatorHandback['pending_handoff_items'] ?? 0) > 0) {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'governance_closure_pilot_operator_handback_handoffs_pending',
                    'message' => '治理关闭试点真实执行交回包仍有签核交接项未完成：pending_handoff_items='
                        . (string)($governanceClosurePilotOperatorHandback['pending_handoff_items'] ?? 0) . '。',
                ];
            }
            if (
                $applyMode
                && (string)($governanceClosurePilotOperatorHandback['ready_for_pilot_return_preview'] ?? '') !== 'yes'
            ) {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'governance_closure_pilot_operator_handback_not_ready_for_return_preview',
                    'message' => '治理关闭试点真实执行交回包未达到 ready_for_pilot_return_preview=yes，不能进入正式 apply 或 apply-rehearsal。',
                ];
            }
        }

        $governanceClosurePilotOperatorCompletionSimulation = null;
        if ($governanceClosurePilotOperatorCompletionSimulationDir) {
            $governanceClosurePilotOperatorCompletionSimulation = self::inspectGovernanceClosurePilotOperatorCompletionSimulation($governanceClosurePilotOperatorCompletionSimulationDir);
            foreach ((array)($governanceClosurePilotOperatorCompletionSimulation['findings'] ?? []) as $finding) {
                $findings[] = $finding;
            }
            if ($applyMode && (string)($governanceClosurePilotOperatorCompletionSimulation['status'] ?? '') !== 'passed') {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'governance_closure_pilot_operator_completion_simulation_not_passed',
                    'message' => '治理关闭试点人工执行模拟完成包尚未通过命令层校验：status='
                        . (string)($governanceClosurePilotOperatorCompletionSimulation['status'] ?? 'missing') . '。',
                ];
            }
            if ($applyMode && !$applyRehearsal && !empty($governanceClosurePilotOperatorCompletionSimulation['is_simulated'])) {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'governance_closure_pilot_operator_completion_simulation_not_allowed_for_apply',
                    'message' => '检测到模拟完成包标识 ' . self::GOVERNANCE_CLOSURE_PILOT_OPERATOR_COMPLETION_SIMULATION_MARKER . '；模拟包只能用于 dry-run 或 --apply-rehearsal，不得作为正式 --apply 的真实执行证据。',
                ];
            }
            if (
                $applyMode
                && (string)($governanceClosurePilotOperatorCompletionSimulation['ready_for_pilot_return_preview'] ?? '') !== 'yes'
            ) {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'governance_closure_pilot_operator_completion_simulation_not_ready_for_return_preview',
                    'message' => '治理关闭试点人工执行模拟完成包未达到 ready_for_pilot_return_preview=yes，不能进入正式 apply 或 apply-rehearsal。',
                ];
            }
        }

        $governanceClosurePreview = null;
        if ($governanceClosurePreviewDir) {
            $governanceClosurePreview = self::inspectGovernanceClosureDecisionPreview($governanceClosurePreviewDir);
            foreach ((array)($governanceClosurePreview['findings'] ?? []) as $finding) {
                $findings[] = $finding;
            }
            if ($applyMode && (string)($governanceClosurePreview['status'] ?? '') !== 'passed') {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'governance_closure_preview_not_passed',
                    'message' => '治理关闭意见回填预览包尚未通过命令层校验：status='
                        . (string)($governanceClosurePreview['status'] ?? 'missing') . '。',
                ];
            }
            if ($applyMode && (int)($governanceClosurePreview['blocking_items'] ?? 0) > 0) {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'governance_closure_preview_has_blocking_items',
                    'message' => '治理关闭意见回填预览仍存在阻断项：blocking_items='
                        . (string)($governanceClosurePreview['blocking_items'] ?? 0)
                        . '，readiness=' . (string)($governanceClosurePreview['readiness'] ?? '') . '。',
                ];
            }
            if (
                $applyMode
                && (string)($governanceClosurePreview['ready_for_governance_readiness_refresh'] ?? '') !== 'yes'
            ) {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'governance_closure_preview_not_ready_for_refresh',
                    'message' => '治理关闭意见回填预览未达到 ready_for_governance_readiness_refresh=yes，不能进入正式 apply 或 apply-rehearsal。',
                ];
            }
        }

        $governanceReadinessRefresh = null;
        if ($governanceReadinessRefreshDir) {
            $governanceReadinessRefresh = self::inspectGovernanceReadinessRefreshPreview($governanceReadinessRefreshDir);
            foreach ((array)($governanceReadinessRefresh['findings'] ?? []) as $finding) {
                $findings[] = $finding;
            }
            if ($applyMode && (string)($governanceReadinessRefresh['status'] ?? '') !== 'passed') {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'governance_readiness_refresh_not_passed',
                    'message' => '治理就绪刷新预览包尚未通过命令层校验：status='
                        . (string)($governanceReadinessRefresh['status'] ?? 'missing') . '。',
                ];
            }
            if ($applyMode && (int)($governanceReadinessRefresh['refreshed_blocking_tasks'] ?? 0) > 0) {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'governance_readiness_refresh_has_blocking_tasks',
                    'message' => '治理就绪刷新预览仍存在阻断任务：refreshed_blocking_tasks='
                        . (string)($governanceReadinessRefresh['refreshed_blocking_tasks'] ?? 0)
                        . '，readiness=' . (string)($governanceReadinessRefresh['readiness'] ?? '') . '。',
                ];
            }
            if ($applyMode && (string)($governanceReadinessRefresh['ready_for_lims_apply'] ?? '') !== 'yes') {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'governance_readiness_refresh_not_ready_for_apply',
                    'message' => '治理就绪刷新预览未达到 ready_for_lims_apply=yes，不能进入正式 apply 或 apply-rehearsal。',
                ];
            }
        }

        $summary = [
            'generated_at' => date('Y-m-d\TH:i:s'),
            'mode' => $applyRehearsal ? 'apply-rehearsal' : ($applyMode ? 'apply' : 'dry-run'),
            'package_dir' => $packageDir,
            'review_dir' => $reviewDir,
            'field_catalog_dir' => $fieldCatalogDir,
            'release_plan_dir' => $releasePlanDir,
            'release_execution_dir' => $releaseExecutionDir,
            'manual_revision_dir' => $manualRevisionDir,
            'staff_training_dir' => $staffTrainingDir,
            'stage2_review_dir' => $stage2ReviewDir,
            'stage2_review_preview_dir' => $stage2ReviewPreviewDir,
            'governance_readiness_dir' => $governanceReadinessDir,
            'governance_closure_dir' => $governanceClosureDir,
            'governance_closure_execution_dir' => $governanceClosureExecutionDir,
            'governance_closure_pilot_dir' => $governanceClosurePilotDir,
            'governance_closure_pilot_return_dir' => $governanceClosurePilotReturnDir,
            'governance_closure_pilot_source_update_dir' => $governanceClosurePilotSourceUpdateDir,
            'governance_closure_pilot_operator_workbook_dir' => $governanceClosurePilotOperatorWorkbookDir,
            'governance_closure_pilot_operator_handback_dir' => $governanceClosurePilotOperatorHandbackDir,
            'governance_closure_pilot_operator_completion_simulation_dir' => $governanceClosurePilotOperatorCompletionSimulationDir,
            'governance_closure_preview_dir' => $governanceClosurePreviewDir,
            'governance_readiness_refresh_dir' => $governanceReadinessRefreshDir,
            'status' => self::hasHighFinding($findings) ? 'failed' : 'passed',
            'ack_human_reviewed' => $ackHumanReviewed,
            'counts' => [
                'documents' => count($rows['documents'] ?? []),
                'structured_documents' => count($rows['structured_documents'] ?? []),
                'record_form_templates' => count($rows['record_form_templates'] ?? []),
                'traceability_rows' => count($rows['traceability_matrix'] ?? []),
                'manual_blocks' => count($rows['manual_blocks'] ?? []),
                'external_sources' => count($rows['external_sources'] ?? []),
                'candidate_document_rows' => count($candidateDocumentCodes),
                'reference_current_document_rows' => count($referenceProcedureCodes),
                'review_pack_items' => (int)($reviewPack['total_decisions'] ?? 0),
                'review_pack_pending' => (int)($reviewPack['pending_decisions'] ?? 0),
                'field_catalog_templates' => (int)($fieldCatalog['record_templates'] ?? 0),
                'field_catalog_fields' => (int)($fieldCatalog['field_detail_rows'] ?? 0),
                'release_plan_objects' => (int)($releasePlan['release_objects'] ?? 0),
                'release_plan_training_items' => (int)($releasePlan['training_items'] ?? 0),
                'release_execution_templates' => (int)($releaseExecution['templates'] ?? 0),
                'release_execution_fields' => (int)($releaseExecution['field_detail_rows'] ?? 0),
                'release_execution_trial_instances' => (int)($releaseExecution['trial_instances'] ?? 0),
                'manual_revision_gates' => (int)($manualRevision['revision_gates'] ?? 0),
                'manual_revision_decisions' => (int)($manualRevision['human_decision_gates'] ?? 0),
                'manual_revision_pending_decisions' => (int)($manualRevision['pending_human_decisions'] ?? 0),
                'staff_training_source_items' => (int)($staffTraining['training_source_items'] ?? 0),
                'staff_training_tasks' => (int)($staffTraining['role_learning_tasks'] ?? 0),
                'staff_training_questions' => (int)($staffTraining['comprehension_questions'] ?? 0),
                'staff_training_feedback_rows' => (int)($staffTraining['feedback_rows'] ?? 0),
                'staff_training_pending_tasks' => (int)($staffTraining['pending_learning_tasks'] ?? 0),
                'staff_training_pending_questions' => (int)($staffTraining['pending_questions'] ?? 0),
                'stage2_review_items' => (int)($stage2Review['decision_items'] ?? 0),
                'stage2_review_pending_decisions' => (int)($stage2Review['pending_decisions'] ?? 0),
                'stage2_review_approved_decisions' => (int)($stage2Review['approved_decisions'] ?? 0),
                'stage2_review_preview_items' => (int)($stage2ReviewPreview['decision_items'] ?? 0),
                'stage2_review_preview_blocking_items' => (int)($stage2ReviewPreview['blocking_items'] ?? 0),
                'governance_readiness_gates' => (int)($governanceReadiness['gate_rows'] ?? 0),
                'governance_readiness_blocking_gates' => (int)($governanceReadiness['blocking_gates'] ?? 0),
                'governance_readiness_tasks' => (int)($governanceReadiness['human_task_rows'] ?? 0),
                'governance_readiness_blocking_tasks' => (int)($governanceReadiness['blocking_tasks'] ?? 0),
                'governance_closure_items' => (int)($governanceClosure['closure_rows'] ?? 0),
                'governance_closure_open_blocking_items' => (int)($governanceClosure['open_blocking_items'] ?? 0),
                'governance_closure_accepted_closures' => (int)($governanceClosure['accepted_closures'] ?? 0),
                'governance_closure_execution_batches' => (int)($governanceClosureExecution['execution_batches'] ?? 0),
                'governance_closure_execution_signatures' => (int)($governanceClosureExecution['signature_rows'] ?? 0),
                'governance_closure_execution_pending_signatures' => (int)($governanceClosureExecution['pending_signature_rows'] ?? 0),
                'governance_closure_execution_pending_routes' => (int)($governanceClosureExecution['pending_route_items'] ?? 0),
                'governance_closure_pilot_batches' => (int)($governanceClosurePilot['pilot_batches'] ?? 0),
                'governance_closure_pilot_evidence_rows' => (int)($governanceClosurePilot['pilot_evidence_rows'] ?? 0),
                'governance_closure_pilot_pending_evidence' => (int)($governanceClosurePilot['pending_pilot_evidence'] ?? 0),
                'governance_closure_pilot_pending_handoffs' => (int)($governanceClosurePilot['pending_pilot_handoffs'] ?? 0),
                'governance_closure_pilot_return_items' => (int)($governanceClosurePilotReturn['mapping_rows'] ?? 0),
                'governance_closure_pilot_return_missing_fields' => (int)($governanceClosurePilotReturn['missing_field_rows'] ?? 0),
                'governance_closure_pilot_return_blocking_items' => (int)($governanceClosurePilotReturn['blocking_return_items'] ?? 0),
                'governance_closure_pilot_source_update_patch_rows' => (int)($governanceClosurePilotSourceUpdate['patch_rows'] ?? 0),
                'governance_closure_pilot_source_update_blocked_patches' => (int)($governanceClosurePilotSourceUpdate['blocked_patch_rows'] ?? 0),
                'governance_closure_pilot_source_update_ready_patches' => (int)($governanceClosurePilotSourceUpdate['ready_patch_rows'] ?? 0),
                'governance_closure_pilot_operator_workbook_items' => (int)($governanceClosurePilotOperatorWorkbook['pilot_items'] ?? 0),
                'governance_closure_pilot_operator_workbook_pending_items' => (int)($governanceClosurePilotOperatorWorkbook['pending_workbook_items'] ?? 0),
                'governance_closure_pilot_operator_workbook_field_items' => (int)($governanceClosurePilotOperatorWorkbook['field_fill_items'] ?? 0),
                'governance_closure_pilot_operator_workbook_pending_fields' => (int)($governanceClosurePilotOperatorWorkbook['pending_field_items'] ?? 0),
                'governance_closure_pilot_operator_workbook_handoff_items' => (int)($governanceClosurePilotOperatorWorkbook['handoff_check_items'] ?? 0),
                'governance_closure_pilot_operator_workbook_pending_handoffs' => (int)($governanceClosurePilotOperatorWorkbook['pending_handoff_items'] ?? 0),
                'governance_closure_pilot_operator_handback_items' => (int)($governanceClosurePilotOperatorHandback['pilot_items'] ?? 0),
                'governance_closure_pilot_operator_handback_pending_items' => (int)($governanceClosurePilotOperatorHandback['pending_workbook_items'] ?? 0),
                'governance_closure_pilot_operator_handback_field_items' => (int)($governanceClosurePilotOperatorHandback['field_fill_items'] ?? 0),
                'governance_closure_pilot_operator_handback_pending_fields' => (int)($governanceClosurePilotOperatorHandback['pending_field_items'] ?? 0),
                'governance_closure_pilot_operator_handback_handoff_items' => (int)($governanceClosurePilotOperatorHandback['handoff_check_items'] ?? 0),
                'governance_closure_pilot_operator_handback_pending_handoffs' => (int)($governanceClosurePilotOperatorHandback['pending_handoff_items'] ?? 0),
                'governance_closure_pilot_operator_completion_simulation_items' => (int)($governanceClosurePilotOperatorCompletionSimulation['pilot_items'] ?? 0),
                'governance_closure_pilot_operator_completion_simulation_field_items' => (int)($governanceClosurePilotOperatorCompletionSimulation['field_fill_items'] ?? 0),
                'governance_closure_pilot_operator_completion_simulation_handoff_items' => (int)($governanceClosurePilotOperatorCompletionSimulation['handoff_check_items'] ?? 0),
                'governance_closure_pilot_operator_completion_simulation_pending_items' => (int)($governanceClosurePilotOperatorCompletionSimulation['pending_workbook_items'] ?? 0),
                'governance_closure_pilot_operator_completion_simulation_marker_rows' => (int)($governanceClosurePilotOperatorCompletionSimulation['simulation_marker_rows'] ?? 0),
                'governance_closure_preview_items' => (int)($governanceClosurePreview['decision_items'] ?? 0),
                'governance_closure_preview_blocking_items' => (int)($governanceClosurePreview['blocking_items'] ?? 0),
                'governance_closure_preview_accepted' => (int)($governanceClosurePreview['accepted_for_preview'] ?? 0),
                'governance_readiness_refresh_tasks' => (int)($governanceReadinessRefresh['task_preview_rows'] ?? 0),
                'governance_readiness_refresh_blocking_tasks' => (int)($governanceReadinessRefresh['refreshed_blocking_tasks'] ?? 0),
                'governance_readiness_refresh_accepted_closures' => (int)($governanceReadinessRefresh['accepted_task_closures'] ?? 0),
                'findings' => count($findings),
            ],
            'readiness' => [
                'existing_document_matches' => count($existingDocuments),
                'existing_reference_current_documents' => count($referenceProcedureCodes) - count($missingReferenceProcedures),
                'missing_reference_current_documents' => count($missingReferenceProcedures),
                'candidate_documents_would_create_or_skip' => count($candidateDocumentCodes),
                'existing_record_template_matches' => count($existingTemplates),
                'record_templates_would_create_or_skip' => count($recordCodes),
                'external_sources_would_upsert' => count($sourceCodes),
                'structured_documents_deferred' => count($rows['structured_documents'] ?? []),
                'manual_blocks_deferred' => count($rows['manual_blocks'] ?? []),
                'traceability_links_deferred' => count($rows['traceability_matrix'] ?? []),
                'review_pack_status' => (string)($reviewPack['status'] ?? ($reviewDir ? 'missing' : 'not_provided')),
                'review_pack_pending_decisions' => (int)($reviewPack['pending_decisions'] ?? 0),
                'review_pack_unapproved_decisions' => (int)($reviewPack['unapproved_decisions'] ?? 0),
                'field_catalog_status' => (string)($fieldCatalog['status'] ?? ($fieldCatalogDir ? 'missing' : 'not_provided')),
                'field_catalog_templates' => (int)($fieldCatalog['record_templates'] ?? 0),
                'field_catalog_fields' => (int)($fieldCatalog['field_detail_rows'] ?? 0),
                'field_catalog_human_confirmation_fields' => (int)($fieldCatalog['human_confirmation_fields'] ?? 0),
                'release_plan_status' => (string)($releasePlan['status'] ?? ($releasePlanDir ? 'missing' : 'not_provided')),
                'release_plan_objects' => (int)($releasePlan['release_objects'] ?? 0),
                'release_plan_training_items' => (int)($releasePlan['training_items'] ?? 0),
                'release_plan_release_allowed_now' => (int)($releasePlan['release_allowed_now'] ?? 0),
                'release_execution_status' => (string)($releaseExecution['status'] ?? ($releaseExecutionDir ? 'missing' : 'not_provided')),
                'release_execution_templates' => (int)($releaseExecution['templates'] ?? 0),
                'release_execution_fields' => (int)($releaseExecution['field_detail_rows'] ?? 0),
                'release_execution_trial_instances' => (int)($releaseExecution['trial_instances'] ?? 0),
                'manual_revision_status' => (string)($manualRevision['status'] ?? ($manualRevisionDir ? 'missing' : 'not_provided')),
                'manual_revision_target_doc_number' => (string)($manualRevision['target_doc_number'] ?? ''),
                'manual_revision_pending_human_decisions' => (int)($manualRevision['pending_human_decisions'] ?? 0),
                'manual_revision_existing_route' => (string)($manualRevision['revision_route_decision'] ?? ''),
                'staff_training_status' => (string)($staffTraining['status'] ?? ($staffTrainingDir ? 'missing' : 'not_provided')),
                'staff_training_source_items' => (int)($staffTraining['training_source_items'] ?? 0),
                'staff_training_tasks' => (int)($staffTraining['role_learning_tasks'] ?? 0),
                'staff_training_questions' => (int)($staffTraining['comprehension_questions'] ?? 0),
                'staff_training_pending_tasks' => (int)($staffTraining['pending_learning_tasks'] ?? 0),
                'staff_training_pending_questions' => (int)($staffTraining['pending_questions'] ?? 0),
                'staff_training_blank_feedback_decisions' => (int)($staffTraining['blank_feedback_decisions'] ?? 0),
                'stage2_review_status' => (string)($stage2Review['status'] ?? ($stage2ReviewDir ? 'missing' : 'not_provided')),
                'stage2_review_items' => (int)($stage2Review['decision_items'] ?? 0),
                'stage2_review_pending_decisions' => (int)($stage2Review['pending_decisions'] ?? 0),
                'stage2_review_approved_decisions' => (int)($stage2Review['approved_decisions'] ?? 0),
                'stage2_review_revise_decisions' => (int)($stage2Review['revise_decisions'] ?? 0),
                'stage2_review_remove_decisions' => (int)($stage2Review['remove_decisions'] ?? 0),
                'stage2_review_preview_status' => (string)($stage2ReviewPreview['status'] ?? ($stage2ReviewPreviewDir ? 'missing' : 'not_provided')),
                'stage2_review_preview_readiness' => (string)($stage2ReviewPreview['readiness'] ?? ''),
                'stage2_review_preview_items' => (int)($stage2ReviewPreview['decision_items'] ?? 0),
                'stage2_review_preview_blocking_items' => (int)($stage2ReviewPreview['blocking_items'] ?? 0),
                'governance_readiness_status' => (string)($governanceReadiness['status'] ?? ($governanceReadinessDir ? 'missing' : 'not_provided')),
                'governance_readiness_readiness' => (string)($governanceReadiness['readiness'] ?? ''),
                'governance_readiness_ready_for_lims_apply' => (string)($governanceReadiness['ready_for_lims_apply'] ?? ''),
                'governance_readiness_gates' => (int)($governanceReadiness['gate_rows'] ?? 0),
                'governance_readiness_blocking_gates' => (int)($governanceReadiness['blocking_gates'] ?? 0),
                'governance_readiness_tasks' => (int)($governanceReadiness['human_task_rows'] ?? 0),
                'governance_readiness_blocking_tasks' => (int)($governanceReadiness['blocking_tasks'] ?? 0),
                'governance_closure_status' => (string)($governanceClosure['status'] ?? ($governanceClosureDir ? 'missing' : 'not_provided')),
                'governance_closure_readiness' => (string)($governanceClosure['readiness'] ?? ''),
                'governance_closure_ready_for_governance_readiness_refresh' => (string)($governanceClosure['ready_for_governance_readiness_refresh'] ?? ''),
                'governance_closure_ready_for_lims_apply' => (string)($governanceClosure['ready_for_lims_apply'] ?? ''),
                'governance_closure_items' => (int)($governanceClosure['closure_rows'] ?? 0),
                'governance_closure_open_blocking_items' => (int)($governanceClosure['open_blocking_items'] ?? 0),
                'governance_closure_accepted_closures' => (int)($governanceClosure['accepted_closures'] ?? 0),
                'governance_closure_execution_status' => (string)($governanceClosureExecution['status'] ?? ($governanceClosureExecutionDir ? 'missing' : 'not_provided')),
                'governance_closure_execution_readiness' => (string)($governanceClosureExecution['readiness'] ?? ''),
                'governance_closure_execution_ready_for_preview' => (string)($governanceClosureExecution['ready_for_governance_closure_preview'] ?? ''),
                'governance_closure_execution_batches' => (int)($governanceClosureExecution['execution_batches'] ?? 0),
                'governance_closure_execution_signature_rows' => (int)($governanceClosureExecution['signature_rows'] ?? 0),
                'governance_closure_execution_pending_signatures' => (int)($governanceClosureExecution['pending_signature_rows'] ?? 0),
                'governance_closure_execution_pending_handoffs' => (int)($governanceClosureExecution['pending_handoff_checks'] ?? 0),
                'governance_closure_execution_pending_routes' => (int)($governanceClosureExecution['pending_route_items'] ?? 0),
                'governance_closure_execution_blocking_routes' => (int)($governanceClosureExecution['blocking_route_items'] ?? 0),
                'governance_closure_pilot_status' => (string)($governanceClosurePilot['status'] ?? ($governanceClosurePilotDir ? 'missing' : 'not_provided')),
                'governance_closure_pilot_readiness' => (string)($governanceClosurePilot['readiness'] ?? ''),
                'governance_closure_pilot_ready_for_preview' => (string)($governanceClosurePilot['ready_for_governance_closure_preview'] ?? ''),
                'governance_closure_pilot_batches' => (int)($governanceClosurePilot['pilot_batches'] ?? 0),
                'governance_closure_pilot_evidence_rows' => (int)($governanceClosurePilot['pilot_evidence_rows'] ?? 0),
                'governance_closure_pilot_handoff_rows' => (int)($governanceClosurePilot['pilot_handoff_rows'] ?? 0),
                'governance_closure_pilot_pending_evidence' => (int)($governanceClosurePilot['pending_pilot_evidence'] ?? 0),
                'governance_closure_pilot_pending_handoffs' => (int)($governanceClosurePilot['pending_pilot_handoffs'] ?? 0),
                'governance_closure_pilot_blocking_items' => (int)($governanceClosurePilot['blocking_pilot_items'] ?? 0),
                'governance_closure_pilot_return_status' => (string)($governanceClosurePilotReturn['status'] ?? ($governanceClosurePilotReturnDir ? 'missing' : 'not_provided')),
                'governance_closure_pilot_return_readiness' => (string)($governanceClosurePilotReturn['readiness'] ?? ''),
                'governance_closure_pilot_return_ready_for_preview' => (string)($governanceClosurePilotReturn['ready_for_governance_closure_preview'] ?? ''),
                'governance_closure_pilot_return_items' => (int)($governanceClosurePilotReturn['mapping_rows'] ?? 0),
                'governance_closure_pilot_return_source_preview_rows' => (int)($governanceClosurePilotReturn['source_preview_rows'] ?? 0),
                'governance_closure_pilot_return_missing_fields' => (int)($governanceClosurePilotReturn['missing_field_rows'] ?? 0),
                'governance_closure_pilot_return_ready_items' => (int)($governanceClosurePilotReturn['ready_return_items'] ?? 0),
                'governance_closure_pilot_return_blocking_items' => (int)($governanceClosurePilotReturn['blocking_return_items'] ?? 0),
                'governance_closure_pilot_source_update_status' => (string)($governanceClosurePilotSourceUpdate['status'] ?? ($governanceClosurePilotSourceUpdateDir ? 'missing' : 'not_provided')),
                'governance_closure_pilot_source_update_readiness' => (string)($governanceClosurePilotSourceUpdate['readiness'] ?? ''),
                'governance_closure_pilot_source_update_ready_for_source_workbench_update' => (string)($governanceClosurePilotSourceUpdate['ready_for_source_workbench_update'] ?? ''),
                'governance_closure_pilot_source_update_ready_for_preview' => (string)($governanceClosurePilotSourceUpdate['ready_for_governance_closure_preview'] ?? ''),
                'governance_closure_pilot_source_update_patch_rows' => (int)($governanceClosurePilotSourceUpdate['patch_rows'] ?? 0),
                'governance_closure_pilot_source_update_blocked_patches' => (int)($governanceClosurePilotSourceUpdate['blocked_patch_rows'] ?? 0),
                'governance_closure_pilot_source_update_ready_patches' => (int)($governanceClosurePilotSourceUpdate['ready_patch_rows'] ?? 0),
                'governance_closure_pilot_source_update_manual_update_candidates' => (int)($governanceClosurePilotSourceUpdate['manual_update_candidate_rows'] ?? 0),
                'governance_closure_pilot_operator_workbook_status' => (string)($governanceClosurePilotOperatorWorkbook['status'] ?? ($governanceClosurePilotOperatorWorkbookDir ? 'missing' : 'not_provided')),
                'governance_closure_pilot_operator_workbook_readiness' => (string)($governanceClosurePilotOperatorWorkbook['readiness'] ?? ''),
                'governance_closure_pilot_operator_workbook_ready_for_pilot_return_preview' => (string)($governanceClosurePilotOperatorWorkbook['ready_for_pilot_return_preview'] ?? ''),
                'governance_closure_pilot_operator_workbook_ready_for_source_workbench_update' => (string)($governanceClosurePilotOperatorWorkbook['ready_for_source_workbench_update'] ?? ''),
                'governance_closure_pilot_operator_workbook_items' => (int)($governanceClosurePilotOperatorWorkbook['pilot_items'] ?? 0),
                'governance_closure_pilot_operator_workbook_pending_items' => (int)($governanceClosurePilotOperatorWorkbook['pending_workbook_items'] ?? 0),
                'governance_closure_pilot_operator_workbook_field_items' => (int)($governanceClosurePilotOperatorWorkbook['field_fill_items'] ?? 0),
                'governance_closure_pilot_operator_workbook_pending_fields' => (int)($governanceClosurePilotOperatorWorkbook['pending_field_items'] ?? 0),
                'governance_closure_pilot_operator_workbook_handoff_items' => (int)($governanceClosurePilotOperatorWorkbook['handoff_check_items'] ?? 0),
                'governance_closure_pilot_operator_workbook_pending_handoffs' => (int)($governanceClosurePilotOperatorWorkbook['pending_handoff_items'] ?? 0),
                'governance_closure_pilot_operator_handback_status' => (string)($governanceClosurePilotOperatorHandback['status'] ?? ($governanceClosurePilotOperatorHandbackDir ? 'missing' : 'not_provided')),
                'governance_closure_pilot_operator_handback_readiness' => (string)($governanceClosurePilotOperatorHandback['readiness'] ?? ''),
                'governance_closure_pilot_operator_handback_ready_for_pilot_return_preview' => (string)($governanceClosurePilotOperatorHandback['ready_for_pilot_return_preview'] ?? ''),
                'governance_closure_pilot_operator_handback_ready_for_source_workbench_update' => (string)($governanceClosurePilotOperatorHandback['ready_for_source_workbench_update'] ?? ''),
                'governance_closure_pilot_operator_handback_ready_for_lims_apply' => (string)($governanceClosurePilotOperatorHandback['ready_for_lims_apply'] ?? ''),
                'governance_closure_pilot_operator_handback_items' => (int)($governanceClosurePilotOperatorHandback['pilot_items'] ?? 0),
                'governance_closure_pilot_operator_handback_field_items' => (int)($governanceClosurePilotOperatorHandback['field_fill_items'] ?? 0),
                'governance_closure_pilot_operator_handback_handoff_items' => (int)($governanceClosurePilotOperatorHandback['handoff_check_items'] ?? 0),
                'governance_closure_pilot_operator_handback_pending_items' => (int)($governanceClosurePilotOperatorHandback['pending_workbook_items'] ?? 0),
                'governance_closure_pilot_operator_handback_pending_fields' => (int)($governanceClosurePilotOperatorHandback['pending_field_items'] ?? 0),
                'governance_closure_pilot_operator_handback_pending_handoffs' => (int)($governanceClosurePilotOperatorHandback['pending_handoff_items'] ?? 0),
                'governance_closure_pilot_operator_handback_completed_fields' => (int)($governanceClosurePilotOperatorHandback['completed_field_items'] ?? 0),
                'governance_closure_pilot_operator_handback_completed_handoffs' => (int)($governanceClosurePilotOperatorHandback['completed_handoff_items'] ?? 0),
                'governance_closure_pilot_operator_completion_simulation_status' => (string)($governanceClosurePilotOperatorCompletionSimulation['status'] ?? ($governanceClosurePilotOperatorCompletionSimulationDir ? 'missing' : 'not_provided')),
                'governance_closure_pilot_operator_completion_simulation_readiness' => (string)($governanceClosurePilotOperatorCompletionSimulation['readiness'] ?? ''),
                'governance_closure_pilot_operator_completion_simulation_ready_for_pilot_return_preview' => (string)($governanceClosurePilotOperatorCompletionSimulation['ready_for_pilot_return_preview'] ?? ''),
                'governance_closure_pilot_operator_completion_simulation_ready_for_source_workbench_update' => (string)($governanceClosurePilotOperatorCompletionSimulation['ready_for_source_workbench_update'] ?? ''),
                'governance_closure_pilot_operator_completion_simulation_ready_for_lims_apply' => (string)($governanceClosurePilotOperatorCompletionSimulation['ready_for_lims_apply'] ?? ''),
                'governance_closure_pilot_operator_completion_simulation_is_simulated' => (int)($governanceClosurePilotOperatorCompletionSimulation['is_simulated'] ?? 0),
                'governance_closure_pilot_operator_completion_simulation_items' => (int)($governanceClosurePilotOperatorCompletionSimulation['pilot_items'] ?? 0),
                'governance_closure_pilot_operator_completion_simulation_field_items' => (int)($governanceClosurePilotOperatorCompletionSimulation['field_fill_items'] ?? 0),
                'governance_closure_pilot_operator_completion_simulation_handoff_items' => (int)($governanceClosurePilotOperatorCompletionSimulation['handoff_check_items'] ?? 0),
                'governance_closure_pilot_operator_completion_simulation_pending_items' => (int)($governanceClosurePilotOperatorCompletionSimulation['pending_workbook_items'] ?? 0),
                'governance_closure_pilot_operator_completion_simulation_pending_fields' => (int)($governanceClosurePilotOperatorCompletionSimulation['pending_field_items'] ?? 0),
                'governance_closure_pilot_operator_completion_simulation_pending_handoffs' => (int)($governanceClosurePilotOperatorCompletionSimulation['pending_handoff_items'] ?? 0),
                'governance_closure_pilot_operator_completion_simulation_marker_rows' => (int)($governanceClosurePilotOperatorCompletionSimulation['simulation_marker_rows'] ?? 0),
                'governance_closure_preview_status' => (string)($governanceClosurePreview['status'] ?? ($governanceClosurePreviewDir ? 'missing' : 'not_provided')),
                'governance_closure_preview_readiness' => (string)($governanceClosurePreview['readiness'] ?? ''),
                'governance_closure_preview_ready_for_governance_readiness_refresh' => (string)($governanceClosurePreview['ready_for_governance_readiness_refresh'] ?? ''),
                'governance_closure_preview_ready_for_lims_apply' => (string)($governanceClosurePreview['ready_for_lims_apply'] ?? ''),
                'governance_closure_preview_items' => (int)($governanceClosurePreview['decision_items'] ?? 0),
                'governance_closure_preview_blocking_items' => (int)($governanceClosurePreview['blocking_items'] ?? 0),
                'governance_closure_preview_accepted' => (int)($governanceClosurePreview['accepted_for_preview'] ?? 0),
                'governance_readiness_refresh_status' => (string)($governanceReadinessRefresh['status'] ?? ($governanceReadinessRefreshDir ? 'missing' : 'not_provided')),
                'governance_readiness_refresh_readiness' => (string)($governanceReadinessRefresh['readiness'] ?? ''),
                'governance_readiness_refresh_ready_for_lims_apply' => (string)($governanceReadinessRefresh['ready_for_lims_apply'] ?? ''),
                'governance_readiness_refresh_gates' => (int)($governanceReadinessRefresh['gate_rows'] ?? 0),
                'governance_readiness_refresh_tasks' => (int)($governanceReadinessRefresh['task_preview_rows'] ?? 0),
                'governance_readiness_refresh_blocking_tasks' => (int)($governanceReadinessRefresh['refreshed_blocking_tasks'] ?? 0),
                'governance_readiness_refresh_blocking_gates' => (int)($governanceReadinessRefresh['refreshed_blocking_gates'] ?? 0),
                'governance_readiness_refresh_accepted_closures' => (int)($governanceReadinessRefresh['accepted_task_closures'] ?? 0),
            ],
            'review_pack' => $reviewPack,
            'stage2_readiness' => $stage2Readiness,
            'field_catalog' => $fieldCatalog,
            'release_plan' => $releasePlan,
            'release_execution' => $releaseExecution,
            'manual_revision' => $manualRevision,
            'staff_training' => $staffTraining,
            'stage2_review' => $stage2Review,
            'stage2_review_preview' => $stage2ReviewPreview,
            'governance_readiness' => $governanceReadiness,
            'governance_closure' => $governanceClosure,
            'governance_closure_execution' => $governanceClosureExecution,
            'governance_closure_pilot' => $governanceClosurePilot,
            'governance_closure_pilot_return' => $governanceClosurePilotReturn,
            'governance_closure_pilot_source_update' => $governanceClosurePilotSourceUpdate,
            'governance_closure_pilot_operator_workbook' => $governanceClosurePilotOperatorWorkbook,
            'governance_closure_pilot_operator_handback' => $governanceClosurePilotOperatorHandback,
            'governance_closure_pilot_operator_completion_simulation' => $governanceClosurePilotOperatorCompletionSimulation,
            'governance_closure_preview' => $governanceClosurePreview,
            'governance_readiness_refresh' => $governanceReadinessRefresh,
            'findings' => $findings,
            'boundary' => [
                'dry-run 不写数据库。',
                'apply 需要 --apply 与 --ack-human-reviewed 同时出现。',
                'apply 还需要 --review-dir 指向已全部通过的人工评审包。',
                'reference_existing_current 程序文件只做匹配，不自动创建为已发布文件。',
                '本命令不创建真实 record_form_instances 运行记录。',
                '结构化文件、手册块和块级追溯关系仍作为后续受控治理步骤；--stage2-check 只做预检，不写入这些表。',
                '字段字典校验只检查候选模板 schema 与人工评审材料一致性，不写数据库，不代表模板已受控发布。',
                '受控发布治理演练只检查审批、培训、旧版处置和实施有效性准备，不写数据库，不代表批准或发布。',
                '发布执行记录模板校验只检查候选模板结构、字段、模拟试填和边界，不写数据库，不代表形成真实运行记录。',
                '质量手册修订/换版路径校验只检查 XZTC/SC 既有受控文件的修订路线，不写数据库，不代表批准发布。',
                '机构人员学习实施包校验只检查学习任务、理解确认和反馈模板准备度，不写数据库，不代表真实培训完成或形成真实培训记录。',
                '第二阶段结构化导入人工复核工作台校验只读取复核决策，不写数据库，不代表第二阶段已导入或人工评审通过。',
                '第二阶段复核意见回填预览包校验只读取预览结果，不修改复核工作台，不写数据库，不代表第二阶段已导入。',
                '治理就绪总览包只汇总全量治理闸门和人工处理任务，不写数据库，不代表人工评审通过、真实培训完成、受控发布或正式写库授权。',
                '治理关闭工作台只读取拟关闭意见和证据回填状态，不写数据库，不代表人工评审通过、真实培训完成、受控发布或正式写库授权。',
                '治理闭环执行包只检查执行批次、岗位签核、交接复核和回填路径，不写数据库，不代表人工评审通过、真实培训完成、受控发布或正式写库授权。',
                '治理关闭最小试点包只抽取少量执行批次供人工试跑闭环，不写数据库，不代表人工评审通过、真实培训完成、受控发布或正式写库授权。',
                '治理关闭试点回填预览包只检查试点结果能否人工回填源工作台，不修改源工作台，不写数据库，不代表人工评审通过、真实培训完成、受控发布或正式写库授权。',
                '治理关闭试点源工作台回填补丁预演只展示将来可能人工回填的源表字段，不修改治理关闭工作台，不写数据库，不代表正式回填授权。',
                '治理关闭试点人工执行工作簿只整理试点人工执行、逐字段填写和签核交接状态，不修改试点包、源工作台或数据库，不代表真实执行完成。',
                '治理关闭试点人工执行模拟完成包只用于验证未来人工补齐后的命令链路，不修改真实工作簿、源工作台或数据库，不代表真实执行完成。',
                '治理关闭意见回填预览包只读取关闭工作台的拟关闭结果，不修改工作台，不写数据库，不代表人工评审通过、真实培训完成、受控发布或正式写库授权。',
                '治理就绪刷新预览包只模拟刷新后总闸门状态，不修改治理总览，不写数据库，不代表人工评审通过、真实培训完成、受控发布或正式写库授权。',
                'jewelry-qms 建设中系统只进入实施计划、演练和治理准备材料，不写入质量手册正文。',
            ],
            '_rows' => $rows,
        ];
        return $summary;
    }

    private static function readCsv(string $path): array
    {
        $handle = fopen($path, 'r');
        if (!$handle) {
            return [];
        }
        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            rewind($handle);
        }
        $header = null;
        $rows = [];
        while (($data = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
            if ($header === null) {
                $header = array_map(static fn($value): string => trim((string)$value), $data);
                continue;
            }
            if (count($data) < count($header)) {
                $data = array_pad($data, count($header), '');
            }
            $rows[] = array_combine($header, array_slice($data, 0, count($header)));
        }
        fclose($handle);
        return $rows;
    }

    private static function checkManifestCounts(array $manifest, array $rows, array &$findings): void
    {
        $counts = (array)($manifest['counts'] ?? []);
        $map = [
            'documents' => 'documents',
            'structured_documents' => 'structured_documents',
            'record_form_templates' => 'record_form_templates',
            'traceability_rows' => 'traceability_matrix',
            'manual_blocks' => 'manual_blocks',
            'external_sources' => 'external_sources',
        ];
        foreach ($map as $manifestKey => $rowKey) {
            if (!array_key_exists($manifestKey, $counts)) {
                continue;
            }
            $expected = (int)$counts[$manifestKey];
            $actual = count($rows[$rowKey] ?? []);
            if ($expected !== $actual) {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'count_mismatch_' . $rowKey,
                    'message' => $rowKey . ' 计数不一致：manifest=' . $expected . '，csv=' . $actual,
                ];
            }
        }
    }

    private static function checkRecordSchemas(array $rows, array &$findings): void
    {
        foreach ($rows as $index => $row) {
            $docNumber = (string)($row['doc_number'] ?? ('第' . ($index + 2) . '行'));
            $schema = json_decode((string)($row['field_schema_json'] ?? ''), true);
            if (!is_array($schema)) {
                $findings[] = ['severity' => 'high', 'id' => 'invalid_record_schema', 'message' => $docNumber . ' field_schema_json 不是合法 JSON 数组。'];
                continue;
            }
            $keys = [];
            foreach ($schema as $field) {
                if (is_array($field) && isset($field['key'])) {
                    $keys[] = (string)$field['key'];
                }
            }
            $missing = array_values(array_diff(self::REQUIRED_SCHEMA_KEYS, $keys));
            if ($missing !== []) {
                $findings[] = ['severity' => 'high', 'id' => 'record_schema_missing_required_keys', 'message' => $docNumber . ' 缺少通用字段：' . implode('、', $missing)];
            }
        }
    }

    private static function checkTraceabilityRows(array $rows, array &$findings): void
    {
        foreach ($rows as $row) {
            if ((string)($row['human_review_required'] ?? '') !== 'yes') {
                $findings[] = ['severity' => 'medium', 'id' => 'traceability_missing_human_gate', 'message' => (string)($row['clause'] ?? '-') . ' 未标明人工复核闸门。'];
            }
            if ((string)($row['relation_confidence'] ?? '') !== 'review_required') {
                $findings[] = ['severity' => 'medium', 'id' => 'traceability_confidence_not_review_required', 'message' => (string)($row['clause'] ?? '-') . ' 未保持 review_required。'];
            }
        }
    }

    private static function checkManualBlockRows(array $rows, array &$findings): void
    {
        foreach ($rows as $row) {
            if ((string)($row['link_confidence'] ?? '') !== 'review_required') {
                $findings[] = ['severity' => 'medium', 'id' => 'manual_block_confidence_not_review_required', 'message' => (string)($row['stable_key'] ?? '-') . ' 未保持 review_required。'];
            }
        }
    }

    private static function inspectManualRevisionPath(string $manualRevisionDir, array $documentRows, array $existingDocuments): array
    {
        $findings = [];
        $manualRevisionDir = rtrim($manualRevisionDir, '/\\');
        $manifestPath = $manualRevisionDir . DIRECTORY_SEPARATOR . self::REQUIRED_MANUAL_REVISION_FILES['manifest'];
        $manifest = [];
        if (!is_file($manifestPath)) {
            $findings[] = [
                'severity' => 'high',
                'id' => 'missing_manual_revision_manifest',
                'message' => '质量手册修订路径包缺少 manual_revision_path_manifest.json。',
            ];
        } else {
            $manifest = json_decode((string)file_get_contents($manifestPath), true) ?: [];
            if ((string)($manifest['status'] ?? '') !== 'manual_revision_path_no_database_write') {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'invalid_manual_revision_manifest_status',
                    'message' => '质量手册修订路径包 manifest 状态必须为 manual_revision_path_no_database_write。',
                ];
            }
            $guardrails = implode("\n", (array)($manifest['guardrails'] ?? []));
            foreach ([
                '不写数据库',
                '不代表人工评审通过',
                '不得按同编号新增草稿直接写入',
                '既有文件修订/换版治理路径',
                '已取得 CMA',
                'CNAS 申请中',
                'jewelry-qms 仍为建设中系统',
                '不写入质量手册正文',
            ] as $marker) {
                if (!str_contains($guardrails, $marker)) {
                    $findings[] = [
                        'severity' => 'high',
                        'id' => 'manual_revision_manifest_missing_guardrail',
                        'message' => '质量手册修订路径包 manifest 缺少边界标识：' . $marker,
                    ];
                }
            }
        }

        $files = (array)($manifest['files'] ?? []);
        foreach (self::REQUIRED_MANUAL_REVISION_FILES as $key => $defaultFilename) {
            $filename = (string)($files[$key] ?? $defaultFilename);
            $path = $manualRevisionDir . DIRECTORY_SEPARATOR . $filename;
            if (!is_file($path)) {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'missing_manual_revision_' . $key,
                    'message' => '质量手册修订路径包缺少文件：' . $filename,
                ];
            }
        }

        foreach (self::forbiddenDatabaseArtifacts($manualRevisionDir) as $path) {
            $findings[] = [
                'severity' => 'high',
                'id' => 'manual_revision_forbidden_database_artifact',
                'message' => '质量手册修订路径包不应包含数据库/SQL 文件：' . basename((string)$path),
            ];
        }

        $existingManualPath = $manualRevisionDir . DIRECTORY_SEPARATOR . (string)($files['existing_manual'] ?? self::REQUIRED_MANUAL_REVISION_FILES['existing_manual']);
        $revisionChecklistPath = $manualRevisionDir . DIRECTORY_SEPARATOR . (string)($files['revision_checklist'] ?? self::REQUIRED_MANUAL_REVISION_FILES['revision_checklist']);
        $limsActionPreviewPath = $manualRevisionDir . DIRECTORY_SEPARATOR . (string)($files['lims_action_preview'] ?? self::REQUIRED_MANUAL_REVISION_FILES['lims_action_preview']);
        $humanDecisionPath = $manualRevisionDir . DIRECTORY_SEPARATOR . (string)($files['human_decision_gates'] ?? self::REQUIRED_MANUAL_REVISION_FILES['human_decision_gates']);

        $existingManualRows = is_file($existingManualPath) ? self::readCsv($existingManualPath) : [];
        $revisionRows = is_file($revisionChecklistPath) ? self::readCsv($revisionChecklistPath) : [];
        $actionRows = is_file($limsActionPreviewPath) ? self::readCsv($limsActionPreviewPath) : [];
        $decisionRows = is_file($humanDecisionPath) ? self::readCsv($humanDecisionPath) : [];

        $targetDocNumber = trim((string)($manifest['target_doc_number'] ?? 'XZTC/SC'));
        if ($targetDocNumber !== 'XZTC/SC') {
            $findings[] = [
                'severity' => 'high',
                'id' => 'manual_revision_unexpected_target_doc_number',
                'message' => '质量手册修订路径包 target_doc_number 应为 XZTC/SC，当前为：' . ($targetDocNumber ?: '-'),
            ];
        }

        if (count($existingManualRows) !== 1) {
            $findings[] = [
                'severity' => 'high',
                'id' => 'manual_revision_existing_manual_count_invalid',
                'message' => '既有质量手册记录核对应只有 1 行 XZTC/SC，当前为 ' . count($existingManualRows) . ' 行。',
            ];
        }

        $existingManualRow = $existingManualRows[0] ?? [];
        foreach ([
            'doc_number' => 'XZTC/SC',
            'preview_action' => 'plan_existing_document_revision',
            'existing_match' => 'yes',
            'existing_status' => 'published',
            'candidate_status' => 'draft',
            'candidate_publish' => '0',
            'import_mode' => 'manual_review_then_revision_flow',
            'revision_route_decision' => 'existing_document_revision_required',
            'no_write_marker' => 'NO_DATABASE_WRITE_REHEARSAL_ONLY',
        ] as $field => $expected) {
            if ((string)($existingManualRow[$field] ?? '') !== $expected) {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'manual_revision_existing_manual_field_invalid',
                    'message' => '既有质量手册记录核对字段 ' . $field . ' 应为 ' . $expected . '，当前为 ' . (string)($existingManualRow[$field] ?? '-') . '。',
                ];
            }
        }

        if (!isset($existingDocuments['XZTC/SC'])) {
            $findings[] = [
                'severity' => 'high',
                'id' => 'manual_revision_lims_existing_manual_missing',
                'message' => 'LIMS 当前 documents 未匹配到既有 XZTC/SC 质量手册，不能走修订/换版路径。',
            ];
        } elseif ((string)($existingDocuments['XZTC/SC']['status'] ?? '') !== 'published') {
            $findings[] = [
                'severity' => 'high',
                'id' => 'manual_revision_lims_existing_manual_not_published',
                'message' => 'LIMS 当前 XZTC/SC 状态不是 published，当前为：' . (string)($existingDocuments['XZTC/SC']['status'] ?? '-'),
            ];
        }

        $candidateRows = array_values(array_filter(
            $documentRows,
            static fn(array $row): bool => trim((string)($row['doc_number'] ?? '')) === 'XZTC/SC'
        ));
        if (count($candidateRows) !== 1) {
            $findings[] = [
                'severity' => 'high',
                'id' => 'manual_revision_preimport_candidate_count_invalid',
                'message' => 'documents_preimport 中应只有 1 行 XZTC/SC 候选手册，当前为 ' . count($candidateRows) . ' 行。',
            ];
        } else {
            $candidateRow = $candidateRows[0];
            foreach ([
                'action' => 'revision_candidate',
                'status' => 'draft',
                'publish' => '0',
                'import_mode' => 'manual_review_then_revision_flow',
            ] as $field => $expected) {
                if ((string)($candidateRow[$field] ?? '') !== $expected) {
                    $findings[] = [
                        'severity' => 'high',
                        'id' => 'manual_revision_preimport_candidate_field_invalid',
                        'message' => 'documents_preimport XZTC/SC 字段 ' . $field . ' 应为 ' . $expected . '，当前为 ' . (string)($candidateRow[$field] ?? '-') . '。',
                    ];
                }
            }
        }

        $gateIds = array_values(array_filter(array_map(
            static fn(array $row): string => trim((string)($row['gate_id'] ?? '')),
            $revisionRows
        )));
        $missingGateIds = array_values(array_diff(self::REQUIRED_MANUAL_REVISION_GATES, $gateIds));
        if ($missingGateIds !== []) {
            $findings[] = [
                'severity' => 'high',
                'id' => 'manual_revision_missing_gate_ids',
                'message' => '质量手册修订路径包缺少闸门：' . implode('、', $missingGateIds),
            ];
        }
        foreach ($revisionRows as $row) {
            if ((string)($row['blocking_if_unresolved'] ?? '') !== 'yes') {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'manual_revision_gate_not_blocking',
                    'message' => '修订换版闸门 ' . (string)($row['gate_id'] ?? '-') . ' 未标记 blocking_if_unresolved=yes。',
                ];
            }
        }

        $decisionIds = array_values(array_filter(array_map(
            static fn(array $row): string => trim((string)($row['decision_id'] ?? '')),
            $decisionRows
        )));
        $missingDecisionIds = array_values(array_diff(self::REQUIRED_MANUAL_REVISION_DECISIONS, $decisionIds));
        if ($missingDecisionIds !== []) {
            $findings[] = [
                'severity' => 'high',
                'id' => 'manual_revision_missing_decision_ids',
                'message' => '质量手册修订路径包缺少人工决策：' . implode('、', $missingDecisionIds),
            ];
        }
        $pendingHumanDecisions = 0;
        foreach ($decisionRows as $row) {
            $status = (string)($row['decision_status'] ?? '');
            if ($status === 'pending') {
                $pendingHumanDecisions++;
            }
            if ((string)($row['blocking_if_unresolved'] ?? '') !== 'yes') {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'manual_revision_decision_not_blocking',
                    'message' => '人工决策 ' . (string)($row['decision_id'] ?? '-') . ' 未标记 blocking_if_unresolved=yes。',
                ];
            }
        }

        foreach ($actionRows as $row) {
            if ((string)($row['allowed_now'] ?? '') !== 'no' || (string)($row['write_now'] ?? '') !== 'no') {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'manual_revision_action_not_blocked',
                    'message' => 'LIMS 修订动作 ' . (string)($row['action_id'] ?? '-') . ' 必须保持 allowed_now=no 且 write_now=no。',
                ];
            }
        }
        $actionModules = array_values(array_filter(array_map(
            static fn(array $row): string => trim((string)($row['target_table_or_module'] ?? '')),
            $actionRows
        )));
        foreach (['documents', 'document_revisions', 'qms_structured_documents', 'qms_document_blocks/qms_document_block_links', 'document_distributions/document_reviews/approval evidence'] as $expectedModule) {
            if (!in_array($expectedModule, $actionModules, true)) {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'manual_revision_missing_action_module',
                    'message' => 'LIMS 修订动作预览缺少模块：' . $expectedModule,
                ];
            }
        }

        $manifestCounts = (array)($manifest['counts'] ?? []);
        foreach ([
            'existing_manual_rows' => count($existingManualRows),
            'revision_gates' => count($revisionRows),
            'lims_action_preview_rows' => count($actionRows),
            'human_decision_gates' => count($decisionRows),
            'pending_human_decisions' => $pendingHumanDecisions,
        ] as $key => $actual) {
            if (isset($manifestCounts[$key]) && (int)$manifestCounts[$key] !== $actual) {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'manual_revision_manifest_count_mismatch_' . $key,
                    'message' => '质量手册修订路径包 manifest ' . $key . '=' . (string)$manifestCounts[$key] . '，实际 ' . $actual . '。',
                ];
            }
        }
        if (isset($manifestCounts['database_write_performed']) && (int)$manifestCounts['database_write_performed'] !== 0) {
            $findings[] = [
                'severity' => 'high',
                'id' => 'manual_revision_database_write_marker_invalid',
                'message' => '质量手册修订路径包必须保持 database_write_performed=0。',
            ];
        }

        return [
            'manual_revision_dir' => $manualRevisionDir,
            'status' => self::hasHighFinding($findings) ? 'invalid' : 'passed',
            'target_doc_number' => $targetDocNumber,
            'existing_manual_rows' => count($existingManualRows),
            'revision_gates' => count($revisionRows),
            'lims_action_preview_rows' => count($actionRows),
            'human_decision_gates' => count($decisionRows),
            'pending_human_decisions' => $pendingHumanDecisions,
            'existing_lims_manual_status' => (string)($existingDocuments['XZTC/SC']['status'] ?? 'missing'),
            'existing_manual_preview_action' => (string)($existingManualRow['preview_action'] ?? ''),
            'revision_route_decision' => (string)($existingManualRow['revision_route_decision'] ?? ''),
            'findings' => $findings,
        ];
    }

    private static function inspectStaffTrainingPack(string $staffTrainingDir, ?array $releasePlan): array
    {
        $findings = [];
        $staffTrainingDir = rtrim($staffTrainingDir, '/\\');
        $manifestPath = $staffTrainingDir . DIRECTORY_SEPARATOR . self::REQUIRED_STAFF_TRAINING_FILES['manifest'];
        $manifest = [];
        $allowTestCompletion = false;
        if (!is_file($manifestPath)) {
            $findings[] = [
                'severity' => 'high',
                'id' => 'missing_staff_training_manifest',
                'message' => '机构人员学习实施包缺少 staff_training_manifest.json。',
            ];
        } else {
            $manifest = json_decode((string)file_get_contents($manifestPath), true) ?: [];
            $allowTestCompletion = (string)($manifest['test_completion_for_rehearsal'] ?? '') === 'yes'
                && array_key_exists('production_record', $manifest)
                && (bool)$manifest['production_record'] === false;
            if ((string)($manifest['status'] ?? '') !== 'staff_training_implementation_no_database_write') {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'invalid_staff_training_manifest_status',
                    'message' => '机构人员学习实施包 manifest 状态必须为 staff_training_implementation_no_database_write。',
                ];
            }
            $guardrails = implode("\n", (array)($manifest['guardrails'] ?? []));
            foreach ([
                '不写数据库',
                '不代表真实培训完成',
                '不代表真实培训记录',
                '不代表人工评审通过',
                '已取得 CMA',
                'CNAS 申请中',
                'jewelry-qms 仍为建设中系统',
                '不写入质量手册正文',
                self::STAFF_TRAINING_MARKER,
            ] as $marker) {
                if (!str_contains($guardrails, $marker)) {
                    $findings[] = [
                        'severity' => 'high',
                        'id' => 'staff_training_manifest_missing_guardrail',
                        'message' => '机构人员学习实施包 manifest 缺少边界标识：' . $marker,
                    ];
                }
            }
        }

        $files = (array)($manifest['files'] ?? []);
        foreach (self::REQUIRED_STAFF_TRAINING_FILES as $key => $defaultFilename) {
            $filename = (string)($files[$key] ?? $defaultFilename);
            $path = $staffTrainingDir . DIRECTORY_SEPARATOR . $filename;
            if ($key === 'role_cards_dir') {
                if (!is_dir($path)) {
                    $findings[] = [
                        'severity' => 'high',
                        'id' => 'missing_staff_training_role_cards_dir',
                        'message' => '机构人员学习实施包缺少岗位卡目录：' . $filename,
                    ];
                }
                continue;
            }
            if (!is_file($path)) {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'missing_staff_training_' . $key,
                    'message' => '机构人员学习实施包缺少文件：' . $filename,
                ];
            }
        }

        foreach (self::forbiddenDatabaseArtifacts($staffTrainingDir) as $path) {
            $findings[] = [
                'severity' => 'high',
                'id' => 'staff_training_forbidden_database_artifact',
                'message' => '机构人员学习实施包不应包含数据库/SQL 文件：' . basename((string)$path),
            ];
        }

        $roleMatrixPath = $staffTrainingDir . DIRECTORY_SEPARATOR . (string)($files['role_matrix'] ?? self::REQUIRED_STAFF_TRAINING_FILES['role_matrix']);
        $materialIndexPath = $staffTrainingDir . DIRECTORY_SEPARATOR . (string)($files['material_index'] ?? self::REQUIRED_STAFF_TRAINING_FILES['material_index']);
        $questionBankPath = $staffTrainingDir . DIRECTORY_SEPARATOR . (string)($files['question_bank'] ?? self::REQUIRED_STAFF_TRAINING_FILES['question_bank']);
        $feedbackTemplatePath = $staffTrainingDir . DIRECTORY_SEPARATOR . (string)($files['feedback_template'] ?? self::REQUIRED_STAFF_TRAINING_FILES['feedback_template']);
        $roleCardsDir = $staffTrainingDir . DIRECTORY_SEPARATOR . (string)($files['role_cards_dir'] ?? self::REQUIRED_STAFF_TRAINING_FILES['role_cards_dir']);

        $roleRows = is_file($roleMatrixPath) ? self::readCsv($roleMatrixPath) : [];
        $materialRows = is_file($materialIndexPath) ? self::readCsv($materialIndexPath) : [];
        $questionRows = is_file($questionBankPath) ? self::readCsv($questionBankPath) : [];
        $feedbackRows = is_file($feedbackTemplatePath) ? self::readCsv($feedbackTemplatePath) : [];
        $roleCards = is_dir($roleCardsDir) ? (glob($roleCardsDir . DIRECTORY_SEPARATOR . '*.md') ?: []) : [];

        $sourceItems = [];
        $requiredBeforeEffectiveSourceItems = [];
        $pendingLearningTasks = 0;
        $learningTaskIds = [];
        foreach ($roleRows as $row) {
            $taskId = trim((string)($row['learning_task_id'] ?? ''));
            $sourceItemId = trim((string)($row['source_training_item_id'] ?? ''));
            if ($taskId === '' || $sourceItemId === '' || trim((string)($row['role_group'] ?? '')) === '' || trim((string)($row['topic'] ?? '')) === '' || trim((string)($row['source_object'] ?? '')) === '') {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'staff_training_blank_role_matrix_identity',
                    'message' => '岗位学习任务矩阵存在空 learning_task_id/source_training_item_id/role_group/topic/source_object。',
                ];
            }
            if ($taskId !== '') {
                if (isset($learningTaskIds[$taskId])) {
                    $findings[] = [
                        'severity' => 'high',
                        'id' => 'staff_training_duplicate_learning_task_id',
                        'message' => '岗位学习任务矩阵存在重复 learning_task_id：' . $taskId,
                    ];
                }
                $learningTaskIds[$taskId] = true;
            }
            if ($sourceItemId !== '') {
                $sourceItems[$sourceItemId] = true;
            }
            $requiredBeforeEffective = (string)($row['required_before_effective'] ?? '');
            if (!in_array($requiredBeforeEffective, ['yes', 'no'], true)) {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'staff_training_invalid_required_before_effective',
                    'message' => ($taskId ?: '-') . ' required_before_effective 必须为 yes/no。',
                ];
            }
            if ($requiredBeforeEffective === 'yes') {
                $requiredBeforeEffectiveSourceItems[$sourceItemId] = true;
                if ((string)($row['blocks_release_if_pending'] ?? '') !== 'yes') {
                    $findings[] = [
                        'severity' => 'high',
                        'id' => 'staff_training_required_task_not_blocking',
                        'message' => ($taskId ?: '-') . ' required_before_effective=yes 时必须 blocks_release_if_pending=yes。',
                    ];
                }
            }
            $confirmationStatus = (string)($row['human_confirmation_status'] ?? '');
            if ($confirmationStatus === 'pending') {
                $pendingLearningTasks++;
            } elseif ($allowTestCompletion && $confirmationStatus === 'completed') {
                // Explicit non-production rehearsal fixture: lets apply-rehearsal exercise downstream gates.
            } else {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'staff_training_task_confirmation_not_pending',
                    'message' => ($taskId ?: '-') . ' human_confirmation_status 必须保持 pending，不能模拟真实完成。',
                ];
            }
            if ((string)($row['not_real_record'] ?? '') !== 'yes' || (string)($row['not_real_record_marker'] ?? '') !== self::STAFF_TRAINING_MARKER) {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'staff_training_task_missing_not_real_marker',
                    'message' => ($taskId ?: '-') . ' 未标记为非真实培训记录或缺少 ' . self::STAFF_TRAINING_MARKER . '。',
                ];
            }
        }

        $materialIds = [];
        foreach ($materialRows as $row) {
            $materialId = trim((string)($row['material_id'] ?? ''));
            if ($materialId === '' || trim((string)($row['category'] ?? '')) === '' || trim((string)($row['title'] ?? '')) === '' || trim((string)($row['path'] ?? '')) === '' || trim((string)($row['primary_audience'] ?? '')) === '') {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'staff_training_blank_material_identity',
                    'message' => '学习材料入口清单存在空 material_id/category/title/path/primary_audience。',
                ];
            }
            if ($materialId !== '') {
                if (isset($materialIds[$materialId])) {
                    $findings[] = [
                        'severity' => 'high',
                        'id' => 'staff_training_duplicate_material_id',
                        'message' => '学习材料入口清单存在重复 material_id：' . $materialId,
                    ];
                }
                $materialIds[$materialId] = true;
            }
            if (!in_array((string)($row['must_read_before_effective'] ?? ''), ['yes', 'no'], true)) {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'staff_training_invalid_material_required_flag',
                    'message' => ($materialId ?: '-') . ' must_read_before_effective 必须为 yes/no。',
                ];
            }
        }

        $pendingQuestions = 0;
        $questionIds = [];
        foreach ($questionRows as $row) {
            $questionId = trim((string)($row['question_id'] ?? ''));
            if ($questionId === '' || trim((string)($row['topic'] ?? '')) === '' || trim((string)($row['question'] ?? '')) === '' || trim((string)($row['expected_focus'] ?? '')) === '' || trim((string)($row['responsible_roles'] ?? '')) === '') {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'staff_training_blank_question_identity',
                    'message' => '理解确认题库存在空 question_id/topic/question/expected_focus/responsible_roles。',
                ];
            }
            if ($questionId !== '') {
                if (isset($questionIds[$questionId])) {
                    $findings[] = [
                        'severity' => 'high',
                        'id' => 'staff_training_duplicate_question_id',
                        'message' => '理解确认题库存在重复 question_id：' . $questionId,
                    ];
                }
                $questionIds[$questionId] = true;
            }
            $confirmationStatus = (string)($row['confirmation_status'] ?? '');
            if ($confirmationStatus === 'pending') {
                $pendingQuestions++;
            } elseif ($allowTestCompletion && $confirmationStatus === 'completed') {
                // Explicit non-production rehearsal fixture: lets apply-rehearsal exercise downstream gates.
            } else {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'staff_training_question_confirmation_not_pending',
                    'message' => ($questionId ?: '-') . ' confirmation_status 必须保持 pending，不能模拟真实完成。',
                ];
            }
            if ((string)($row['not_real_record'] ?? '') !== 'yes') {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'staff_training_question_missing_not_real_marker',
                    'message' => ($questionId ?: '-') . ' 未标记 not_real_record=yes。',
                ];
            }
        }
        if (count($questionRows) < 12) {
            $findings[] = [
                'severity' => 'high',
                'id' => 'staff_training_question_bank_too_small',
                'message' => '理解确认题库至少应保留 12 题，当前为 ' . count($questionRows) . ' 题。',
            ];
        }

        $blankFeedbackDecisions = 0;
        $feedbackIds = [];
        foreach ($feedbackRows as $row) {
            $feedbackId = trim((string)($row['feedback_id'] ?? ''));
            if ($feedbackId === '' || trim((string)($row['feedback_topic'] ?? '')) === '' || trim((string)($row['source_material'] ?? '')) === '' || trim((string)($row['issue_type'] ?? '')) === '' || trim((string)($row['responsible_role'] ?? '')) === '') {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'staff_training_blank_feedback_identity',
                    'message' => '问题反馈与修订回填模板存在空 feedback_id/feedback_topic/source_material/issue_type/responsible_role。',
                ];
            }
            if ($feedbackId !== '') {
                if (isset($feedbackIds[$feedbackId])) {
                    $findings[] = [
                        'severity' => 'high',
                        'id' => 'staff_training_duplicate_feedback_id',
                        'message' => '问题反馈与修订回填模板存在重复 feedback_id：' . $feedbackId,
                    ];
                }
                $feedbackIds[$feedbackId] = true;
            }
            if (
                trim((string)($row['human_decision'] ?? '')) === ''
                && trim((string)($row['review_comment'] ?? '')) === ''
                && trim((string)($row['proposed_change'] ?? '')) === ''
            ) {
                $blankFeedbackDecisions++;
            } elseif ($allowTestCompletion) {
                // Explicit non-production rehearsal fixture: lets apply-rehearsal exercise downstream gates.
            } else {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'staff_training_feedback_decision_prefilled',
                    'message' => ($feedbackId ?: '-') . ' 反馈回填模板不得预填 proposed_change/human_decision/review_comment。',
                ];
            }
            if ((string)($row['blocking_if_unresolved'] ?? '') !== 'yes') {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'staff_training_feedback_not_blocking',
                    'message' => ($feedbackId ?: '-') . ' blocking_if_unresolved 必须为 yes。',
                ];
            }
            if ((string)($row['not_real_record'] ?? '') !== 'yes') {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'staff_training_feedback_missing_not_real_marker',
                    'message' => ($feedbackId ?: '-') . ' 未标记 not_real_record=yes。',
                ];
            }
        }

        if (count($roleCards) === 0) {
            $findings[] = [
                'severity' => 'high',
                'id' => 'staff_training_role_cards_empty',
                'message' => '机构人员学习实施包岗位卡目录没有 Markdown 岗位卡。',
            ];
        }

        $manifestCounts = (array)($manifest['counts'] ?? []);
        $actualCounts = [
            'training_source_items' => count($sourceItems),
            'role_learning_tasks' => count($roleRows),
            'learning_materials' => count($materialRows),
            'comprehension_questions' => count($questionRows),
            'feedback_rows' => count($feedbackRows),
            'role_cards' => count($roleCards),
            'required_before_effective_source_items' => count(array_filter(array_keys($requiredBeforeEffectiveSourceItems))),
        ];
        foreach ($actualCounts as $key => $actual) {
            if (isset($manifestCounts[$key]) && (int)$manifestCounts[$key] !== $actual) {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'staff_training_manifest_count_mismatch_' . $key,
                    'message' => '机构人员学习实施包 manifest ' . $key . '=' . (string)$manifestCounts[$key] . '，实际 ' . $actual . '。',
                ];
            }
        }
        if (isset($manifestCounts['database_write_performed']) && (int)$manifestCounts['database_write_performed'] !== 0) {
            $findings[] = [
                'severity' => 'high',
                'id' => 'staff_training_database_write_marker_invalid',
                'message' => '机构人员学习实施包必须保持 database_write_performed=0。',
            ];
        }
        if ($releasePlan !== null && (int)($releasePlan['training_items'] ?? 0) !== count($sourceItems)) {
            $findings[] = [
                'severity' => 'high',
                'id' => 'staff_training_release_plan_count_mismatch',
                'message' => '机构人员学习实施包 source_training_item_id 数量应与受控发布演练培训项一致：staff='
                    . count($sourceItems) . '，release_plan=' . (string)($releasePlan['training_items'] ?? 0) . '。',
            ];
        }

        $docFiles = [
            (string)($files['overview'] ?? self::REQUIRED_STAFF_TRAINING_FILES['overview']),
            (string)($files['lims_boundary'] ?? self::REQUIRED_STAFF_TRAINING_FILES['lims_boundary']),
            (string)($files['training_record_template'] ?? self::REQUIRED_STAFF_TRAINING_FILES['training_record_template']),
            (string)($files['readme'] ?? self::REQUIRED_STAFF_TRAINING_FILES['readme']),
        ];
        foreach ($roleCards as $path) {
            $docFiles[] = substr((string)$path, strlen($staffTrainingDir) + 1);
        }
        foreach (array_values(array_unique($docFiles)) as $filename) {
            $path = $staffTrainingDir . DIRECTORY_SEPARATOR . $filename;
            if (!is_file($path)) {
                continue;
            }
            $text = (string)file_get_contents($path);
            if (preg_match('/真实培训已完成|正式培训记录|已批准发布|可以写库|准许写库|(本公司|公司|实验室).{0,12}(已取得|已获|获得)\s*CNAS|CNAS\s*认可证书/u', $text)) {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'staff_training_doc_overstates_status',
                    'message' => basename($path) . ' 疑似包含越权状态表述。',
                ];
            }
        }

        return [
            'staff_training_dir' => $staffTrainingDir,
            'status' => self::hasHighFinding($findings) ? 'invalid' : 'passed',
            'training_source_items' => count($sourceItems),
            'role_learning_tasks' => count($roleRows),
            'learning_materials' => count($materialRows),
            'comprehension_questions' => count($questionRows),
            'feedback_rows' => count($feedbackRows),
            'role_cards' => count($roleCards),
            'required_before_effective_source_items' => count(array_filter(array_keys($requiredBeforeEffectiveSourceItems))),
            'pending_learning_tasks' => $pendingLearningTasks,
            'pending_questions' => $pendingQuestions,
            'blank_feedback_decisions' => $blankFeedbackDecisions,
            'database_write_performed' => (int)($manifestCounts['database_write_performed'] ?? 0),
            'source_release_training_items' => (int)($releasePlan['training_items'] ?? 0),
            'findings' => $findings,
        ];
    }

    private static function inspectReleasePlan(string $releasePlanDir, array $documentRows): array
    {
        $findings = [];
        $releasePlanDir = rtrim($releasePlanDir, '/\\');
        $manifestPath = $releasePlanDir . DIRECTORY_SEPARATOR . self::REQUIRED_RELEASE_PLAN_FILES['manifest'];
        $manifest = [];
        if (!is_file($manifestPath)) {
            $findings[] = [
                'severity' => 'high',
                'id' => 'missing_release_plan_manifest',
                'message' => '受控发布演练包缺少 release_rehearsal_manifest.json。',
            ];
        } else {
            $manifest = json_decode((string)file_get_contents($manifestPath), true) ?: [];
            if ((string)($manifest['status'] ?? '') !== 'release_rehearsal_no_database_write') {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'invalid_release_plan_manifest_status',
                    'message' => '受控发布演练包 manifest 状态必须为 release_rehearsal_no_database_write。',
                ];
            }
            $guardrails = implode("\n", (array)($manifest['guardrails'] ?? []));
            foreach (['不写数据库', '已取得 CMA', 'CNAS 申请中', 'LIMS 当前导出的 2022 程序清单', 'jewelry-qms 目前只作为建设中系统'] as $marker) {
                if (!str_contains($guardrails, $marker)) {
                    $findings[] = [
                        'severity' => 'high',
                        'id' => 'release_plan_manifest_missing_guardrail',
                        'message' => '受控发布演练包 manifest 缺少边界标识：' . $marker,
                    ];
                }
            }
        }

        foreach (self::REQUIRED_RELEASE_PLAN_FILES as $key => $filename) {
            $path = $releasePlanDir . DIRECTORY_SEPARATOR . $filename;
            if (!is_file($path)) {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'missing_release_plan_' . $key,
                    'message' => '受控发布演练包缺少文件：' . $filename,
                ];
            }
        }

        foreach (array_merge(glob($releasePlanDir . DIRECTORY_SEPARATOR . '*.sql') ?: [], glob($releasePlanDir . DIRECTORY_SEPARATOR . '*.db') ?: []) as $path) {
            $findings[] = [
                'severity' => 'high',
                'id' => 'release_plan_forbidden_database_artifact',
                'message' => '受控发布演练包不应包含数据库/SQL 文件：' . basename((string)$path),
            ];
        }

        $releaseRows = is_file($releasePlanDir . DIRECTORY_SEPARATOR . self::REQUIRED_RELEASE_PLAN_FILES['release_objects'])
            ? self::readCsv($releasePlanDir . DIRECTORY_SEPARATOR . self::REQUIRED_RELEASE_PLAN_FILES['release_objects'])
            : [];
        $approvalRows = is_file($releasePlanDir . DIRECTORY_SEPARATOR . self::REQUIRED_RELEASE_PLAN_FILES['approval_rehearsal'])
            ? self::readCsv($releasePlanDir . DIRECTORY_SEPARATOR . self::REQUIRED_RELEASE_PLAN_FILES['approval_rehearsal'])
            : [];
        $trainingRows = is_file($releasePlanDir . DIRECTORY_SEPARATOR . self::REQUIRED_RELEASE_PLAN_FILES['training_rehearsal'])
            ? self::readCsv($releasePlanDir . DIRECTORY_SEPARATOR . self::REQUIRED_RELEASE_PLAN_FILES['training_rehearsal'])
            : [];
        $obsoleteRows = is_file($releasePlanDir . DIRECTORY_SEPARATOR . self::REQUIRED_RELEASE_PLAN_FILES['obsolete_disposition'])
            ? self::readCsv($releasePlanDir . DIRECTORY_SEPARATOR . self::REQUIRED_RELEASE_PLAN_FILES['obsolete_disposition'])
            : [];
        $gateRows = is_file($releasePlanDir . DIRECTORY_SEPARATOR . self::REQUIRED_RELEASE_PLAN_FILES['position_gates'])
            ? self::readCsv($releasePlanDir . DIRECTORY_SEPARATOR . self::REQUIRED_RELEASE_PLAN_FILES['position_gates'])
            : [];
        $effectivenessRows = is_file($releasePlanDir . DIRECTORY_SEPARATOR . self::REQUIRED_RELEASE_PLAN_FILES['effectiveness_checks'])
            ? self::readCsv($releasePlanDir . DIRECTORY_SEPARATOR . self::REQUIRED_RELEASE_PLAN_FILES['effectiveness_checks'])
            : [];

        if (count($releaseRows) !== count($documentRows)) {
            $findings[] = [
                'severity' => 'high',
                'id' => 'release_plan_object_count_mismatch',
                'message' => '发布对象清单应与 documents_preimport 行数一致：release=' . count($releaseRows) . '，source=' . count($documentRows),
            ];
        }

        $typeCounts = [];
        $releaseAllowedNow = 0;
        foreach ($releaseRows as $row) {
            $type = (string)($row['object_type'] ?? '');
            $typeCounts[$type] = (int)($typeCounts[$type] ?? 0) + 1;
            if ((string)($row['release_allowed_now'] ?? '') !== 'no') {
                $releaseAllowedNow++;
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'release_plan_allows_release_before_review',
                    'message' => (string)($row['doc_number'] ?? '-') . ' release_allowed_now 必须为 no。',
                ];
            }
            if (!str_contains((string)($row['qualification_scope_note'] ?? ''), 'CNAS 申请中')) {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'release_plan_row_missing_cnas_boundary',
                    'message' => (string)($row['doc_number'] ?? '-') . ' 缺少 CNAS 申请中边界。',
                ];
            }
        }

        foreach ([
            'candidate_manual' => 1,
            'current_procedure_reference' => 37,
            'candidate_record_template_document' => 26,
            'numbered_attachment_form_pending' => 1,
        ] as $type => $expected) {
            if ((int)($typeCounts[$type] ?? 0) !== $expected) {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'release_plan_type_count_mismatch_' . $type,
                    'message' => $type . ' 应为 ' . $expected . '，实际 ' . (int)($typeCounts[$type] ?? 0) . '。',
                ];
            }
        }

        foreach ($approvalRows as $row) {
            if ((string)($row['human_decision'] ?? '') !== 'pending') {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'release_plan_approval_not_pending',
                    'message' => (string)($row['approval_item_id'] ?? '-') . ' 必须保持 pending。',
                ];
            }
            if ((string)($row['blocking_if_unresolved'] ?? '') !== 'yes') {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'release_plan_approval_not_blocking',
                    'message' => (string)($row['approval_item_id'] ?? '-') . ' 未设置阻断。',
                ];
            }
        }

        $gateIds = [];
        foreach ($gateRows as $row) {
            $gateIds[] = (string)($row['gate_id'] ?? '');
            if ((string)($row['blocking_if_failed'] ?? '') !== 'yes') {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'release_plan_gate_not_blocking',
                    'message' => (string)($row['gate_id'] ?? '-') . ' 未设置失败阻断。',
                ];
            }
        }
        $missingGateIds = array_values(array_diff(self::REQUIRED_RELEASE_POSITION_GATES, $gateIds));
        if ($missingGateIds !== []) {
            $findings[] = [
                'severity' => 'high',
                'id' => 'release_plan_missing_position_gates',
                'message' => '受控发布演练包缺少口径闸门：' . implode('、', $missingGateIds),
            ];
        }

        $trainingSources = array_map(static fn(array $row): string => (string)($row['source_object'] ?? ''), $trainingRows);
        if (!in_array('14-jewelry-qms实施计划与验证方案.md', $trainingSources, true)) {
            $findings[] = [
                'severity' => 'high',
                'id' => 'release_plan_missing_jewelry_qms_training_boundary',
                'message' => '培训清单缺少 jewelry-qms 建设中系统边界培训项。',
            ];
        }
        $obsoleteObjects = implode("\n", array_map(static fn(array $row): string => (string)($row['object'] ?? ''), $obsoleteRows));
        if (!str_contains($obsoleteObjects, '质量手册第四版')) {
            $findings[] = [
                'severity' => 'high',
                'id' => 'release_plan_missing_manual_obsolete_disposition',
                'message' => '旧版处置清单缺少质量手册第四版处置项。',
            ];
        }
        $effectivenessProcesses = implode("\n", array_map(static fn(array $row): string => (string)($row['process'] ?? ''), $effectivenessRows));
        if (!str_contains($effectivenessProcesses, 'jewelry-qms 试运行')) {
            $findings[] = [
                'severity' => 'high',
                'id' => 'release_plan_missing_jewelry_qms_effectiveness_check',
                'message' => '实施有效性清单缺少 jewelry-qms 试运行检查项。',
            ];
        }

        foreach (['overview', 'matrix', 'readme'] as $key) {
            $path = $releasePlanDir . DIRECTORY_SEPARATOR . self::REQUIRED_RELEASE_PLAN_FILES[$key];
            if (!is_file($path)) {
                continue;
            }
            $text = (string)file_get_contents($path);
            foreach (['不写数据库', '不代表受控发布'] as $marker) {
                if (!str_contains($text, $marker)) {
                    $findings[] = [
                        'severity' => 'high',
                        'id' => 'release_plan_doc_missing_guardrail',
                        'message' => basename($path) . ' 缺少边界标识：' . $marker,
                    ];
                }
            }
            if (preg_match('/已批准发布|可以写库|准许写库|正式运行记录|(本公司|公司|实验室).{0,12}(已取得|已获|获得)\s*CNAS|CNAS\s*认可证书/u', $text)) {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'release_plan_doc_overstates_status',
                    'message' => basename($path) . ' 疑似包含越权状态表述。',
                ];
            }
        }

        $manifestCounts = (array)($manifest['counts'] ?? []);
        foreach ([
            'release_objects' => count($releaseRows),
            'approval_items' => count($approvalRows),
            'training_items' => count($trainingRows),
            'obsolete_items' => count($obsoleteRows),
            'position_gates' => count($gateRows),
            'effectiveness_items' => count($effectivenessRows),
        ] as $key => $actual) {
            if (isset($manifestCounts[$key]) && (int)$manifestCounts[$key] !== $actual) {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'release_plan_manifest_count_mismatch_' . $key,
                    'message' => '受控发布演练包 manifest ' . $key . '=' . (string)$manifestCounts[$key] . '，实际 ' . $actual . '。',
                ];
            }
        }

        return [
            'release_plan_dir' => $releasePlanDir,
            'status' => self::hasHighFinding($findings) ? 'invalid' : 'passed',
            'release_objects' => count($releaseRows),
            'approval_items' => count($approvalRows),
            'training_items' => count($trainingRows),
            'obsolete_items' => count($obsoleteRows),
            'position_gates' => count($gateRows),
            'effectiveness_items' => count($effectivenessRows),
            'candidate_manual_objects' => (int)($typeCounts['candidate_manual'] ?? 0),
            'current_procedure_references' => (int)($typeCounts['current_procedure_reference'] ?? 0),
            'candidate_record_template_documents' => (int)($typeCounts['candidate_record_template_document'] ?? 0),
            'attachment_form_pending' => (int)($typeCounts['numbered_attachment_form_pending'] ?? 0),
            'release_allowed_now' => $releaseAllowedNow,
            'findings' => $findings,
        ];
    }

    private static function inspectReleaseExecutionTemplates(string $releaseExecutionDir, ?array $releasePlan): array
    {
        $findings = [];
        $releaseExecutionDir = rtrim($releaseExecutionDir, '/\\');
        $manifestPath = $releaseExecutionDir . DIRECTORY_SEPARATOR . self::REQUIRED_RELEASE_EXECUTION_FILES['manifest'];
        $manifest = [];
        if (!is_file($manifestPath)) {
            $findings[] = [
                'severity' => 'high',
                'id' => 'missing_release_execution_manifest',
                'message' => '发布执行记录模板包缺少 release_execution_template_manifest.json。',
            ];
        } else {
            $manifest = json_decode((string)file_get_contents($manifestPath), true) ?: [];
            if ((string)($manifest['status'] ?? '') !== 'release_execution_templates_no_database_write') {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'invalid_release_execution_manifest_status',
                    'message' => '发布执行记录模板包 manifest 状态必须为 release_execution_templates_no_database_write。',
                ];
            }
            $guardrails = implode("\n", (array)($manifest['guardrails'] ?? []));
            foreach (['不写数据库', '不代表第五版候选稿', 'SIMULATED_TRIAL_NOT_REAL_RECORD', '已取得 CMA', 'CNAS 申请中', 'jewelry-qms 仍为建设中系统'] as $marker) {
                if (!str_contains($guardrails, $marker)) {
                    $findings[] = [
                        'severity' => 'high',
                        'id' => 'release_execution_manifest_missing_guardrail',
                        'message' => '发布执行记录模板包 manifest 缺少边界标识：' . $marker,
                    ];
                }
            }
        }

        $files = (array)($manifest['files'] ?? []);
        foreach (self::REQUIRED_RELEASE_EXECUTION_FILES as $key => $defaultFilename) {
            $filename = (string)($files[$key] ?? $defaultFilename);
            $path = $releaseExecutionDir . DIRECTORY_SEPARATOR . $filename;
            if (!is_file($path)) {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'missing_release_execution_' . $key,
                    'message' => '发布执行记录模板包缺少文件：' . $filename,
                ];
            }
        }

        foreach (array_merge(
            glob($releaseExecutionDir . DIRECTORY_SEPARATOR . '*.sql') ?: [],
            glob($releaseExecutionDir . DIRECTORY_SEPARATOR . '*.db') ?: [],
            glob($releaseExecutionDir . DIRECTORY_SEPARATOR . 'templates' . DIRECTORY_SEPARATOR . '*.sql') ?: [],
            glob($releaseExecutionDir . DIRECTORY_SEPARATOR . 'templates' . DIRECTORY_SEPARATOR . '*.db') ?: []
        ) as $path) {
            $findings[] = [
                'severity' => 'high',
                'id' => 'release_execution_forbidden_database_artifact',
                'message' => '发布执行记录模板包不应包含数据库/SQL 文件：' . basename((string)$path),
            ];
        }

        $indexPath = $releaseExecutionDir . DIRECTORY_SEPARATOR . (string)($files['template_index'] ?? self::REQUIRED_RELEASE_EXECUTION_FILES['template_index']);
        $detailPath = $releaseExecutionDir . DIRECTORY_SEPARATOR . (string)($files['field_detail'] ?? self::REQUIRED_RELEASE_EXECUTION_FILES['field_detail']);
        $trialPath = $releaseExecutionDir . DIRECTORY_SEPARATOR . (string)($files['trial_csv'] ?? self::REQUIRED_RELEASE_EXECUTION_FILES['trial_csv']);
        $indexRows = is_file($indexPath) ? self::readCsv($indexPath) : [];
        $detailRows = is_file($detailPath) ? self::readCsv($detailPath) : [];
        $trialRows = is_file($trialPath) ? self::readCsv($trialPath) : [];

        $templateCodes = [];
        foreach ($indexRows as $row) {
            $code = trim((string)($row['template_code'] ?? ''));
            if ($code === '') {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'release_execution_blank_template_code',
                    'message' => '发布执行记录模板索引存在空 template_code。',
                ];
                continue;
            }
            if (isset($templateCodes[$code])) {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'release_execution_duplicate_template_code',
                    'message' => '发布执行记录模板索引存在重复 template_code：' . $code,
                ];
            }
            $templateCodes[$code] = true;
        }
        $missingTemplates = array_values(array_diff(self::EXPECTED_RELEASE_EXECUTION_TEMPLATE_CODES, array_keys($templateCodes)));
        if ($missingTemplates !== []) {
            $findings[] = [
                'severity' => 'high',
                'id' => 'release_execution_missing_template_codes',
                'message' => '发布执行记录模板包缺少模板：' . implode('、', $missingTemplates),
            ];
        }
        $extraTemplates = array_values(array_diff(array_keys($templateCodes), self::EXPECTED_RELEASE_EXECUTION_TEMPLATE_CODES));
        if ($extraTemplates !== []) {
            $findings[] = [
                'severity' => 'high',
                'id' => 'release_execution_extra_template_codes',
                'message' => '发布执行记录模板包存在非预期模板：' . implode('、', $extraTemplates),
            ];
        }

        $detailsByCode = [];
        foreach ($detailRows as $row) {
            $code = trim((string)($row['template_code'] ?? ''));
            $key = trim((string)($row['field_key'] ?? ''));
            if ($code === '' || $key === '') {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'release_execution_blank_field_identity',
                    'message' => '发布执行字段明细存在空 template_code 或 field_key。',
                ];
                continue;
            }
            $detailsByCode[$code][] = $row;
            if (!in_array((string)($row['required'] ?? ''), ['yes', 'no'], true)) {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'release_execution_invalid_required_flag',
                    'message' => $code . '/' . $key . ' required 必须为 yes/no。',
                ];
            }
            if (!in_array((string)($row['field_group'] ?? ''), ['common', 'specific'], true)) {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'release_execution_invalid_field_group',
                    'message' => $code . '/' . $key . ' field_group 必须为 common/specific。',
                ];
            }
        }

        foreach ($indexRows as $row) {
            $code = trim((string)($row['template_code'] ?? ''));
            if ($code === '') {
                continue;
            }
            $detailsForCode = (array)($detailsByCode[$code] ?? []);
            $fieldKeys = array_values(array_filter(array_map(
                static fn(array $item): string => trim((string)($item['field_key'] ?? '')),
                $detailsForCode
            )));
            $missingCommon = array_values(array_diff(self::RELEASE_EXECUTION_COMMON_FIELD_KEYS, $fieldKeys));
            if ($missingCommon !== []) {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'release_execution_missing_common_fields',
                    'message' => $code . ' 缺少通用字段：' . implode('、', $missingCommon),
                ];
            }
            if (!is_numeric((string)($row['field_count'] ?? ''))) {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'release_execution_invalid_field_count',
                    'message' => $code . ' field_count 不是数字。',
                ];
            } elseif ((int)$row['field_count'] !== count($detailsForCode)) {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'release_execution_field_count_mismatch',
                    'message' => $code . ' 字段数不一致：index=' . (string)$row['field_count'] . '，detail=' . count($detailsForCode),
                ];
            }
            $markdownFile = trim((string)($row['markdown_file'] ?? ''));
            if ($markdownFile === '' || !is_file($releaseExecutionDir . DIRECTORY_SEPARATOR . $markdownFile)) {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'release_execution_missing_template_markdown',
                    'message' => $code . ' 缺少模板 Markdown：' . $markdownFile,
                ];
            }
        }

        $trialCodes = [];
        foreach ($trialRows as $row) {
            $code = trim((string)($row['template_code'] ?? ''));
            if ($code !== '') {
                $trialCodes[$code] = true;
            }
            if ((string)($row['not_real_record'] ?? '') !== 'yes') {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'release_execution_trial_not_marked_not_real',
                    'message' => ($code ?: '-') . ' 模拟试填未标记 not_real_record=yes。',
                ];
            }
            $values = json_decode((string)($row['field_values_json'] ?? '{}'), true);
            if (!is_array($values)) {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'release_execution_invalid_trial_json',
                    'message' => ($code ?: '-') . ' field_values_json 不是合法 JSON 对象。',
                ];
                continue;
            }
            if (!str_contains(json_encode($values, JSON_UNESCAPED_UNICODE) ?: '', 'SIMULATED_TRIAL_NOT_REAL_RECORD')) {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'release_execution_trial_missing_simulated_marker',
                    'message' => ($code ?: '-') . ' 模拟试填缺少 SIMULATED_TRIAL_NOT_REAL_RECORD 标识。',
                ];
            }
            $fieldKeys = array_values(array_filter(array_map(
                static fn(array $item): string => trim((string)($item['field_key'] ?? '')),
                (array)($detailsByCode[$code] ?? [])
            )));
            $missingValues = array_values(array_diff($fieldKeys, array_keys($values)));
            if ($missingValues !== []) {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'release_execution_trial_missing_field_values',
                    'message' => ($code ?: '-') . ' 模拟试填缺少字段值：' . implode('、', array_slice($missingValues, 0, 12)),
                ];
            }
        }
        $trialMissing = array_values(array_diff(array_keys($templateCodes), array_keys($trialCodes)));
        $trialExtra = array_values(array_diff(array_keys($trialCodes), array_keys($templateCodes)));
        if ($trialMissing !== [] || $trialExtra !== []) {
            $findings[] = [
                'severity' => 'high',
                'id' => 'release_execution_trial_template_mismatch',
                'message' => '模拟试填模板与索引不一致：trial_missing=' . implode('、', $trialMissing) . '；index_missing=' . implode('、', $trialExtra),
            ];
        }

        $templateDocCount = count(glob($releaseExecutionDir . DIRECTORY_SEPARATOR . 'templates' . DIRECTORY_SEPARATOR . '*.md') ?: []);
        $manifestCounts = (array)($manifest['counts'] ?? []);
        foreach ([
            'templates' => count($indexRows),
            'fields' => count($detailRows),
            'trial_instances' => count($trialRows),
            'template_markdown_files' => $templateDocCount,
        ] as $key => $actual) {
            if (isset($manifestCounts[$key]) && (int)$manifestCounts[$key] !== $actual) {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'release_execution_manifest_count_mismatch_' . $key,
                    'message' => '发布执行记录模板包 manifest ' . $key . '=' . (string)$manifestCounts[$key] . '，实际 ' . $actual . '。',
                ];
            }
        }
        if ($releasePlan !== null) {
            foreach ([
                'source_release_objects' => 'release_objects',
                'source_approval_items' => 'approval_items',
                'source_training_items' => 'training_items',
                'source_obsolete_items' => 'obsolete_items',
                'source_effectiveness_items' => 'effectiveness_items',
            ] as $manifestKey => $releasePlanKey) {
                if (isset($manifestCounts[$manifestKey]) && (int)$manifestCounts[$manifestKey] !== (int)($releasePlan[$releasePlanKey] ?? 0)) {
                    $findings[] = [
                        'severity' => 'high',
                        'id' => 'release_execution_source_count_mismatch_' . $manifestKey,
                        'message' => '发布执行记录模板包 manifest ' . $manifestKey . '=' . (string)$manifestCounts[$manifestKey]
                            . '，受控发布演练为 ' . (string)($releasePlan[$releasePlanKey] ?? 0) . '。',
                    ];
                }
            }
        }

        $docFiles = [
            (string)($files['overview'] ?? self::REQUIRED_RELEASE_EXECUTION_FILES['overview']),
            (string)($files['readme'] ?? self::REQUIRED_RELEASE_EXECUTION_FILES['readme']),
        ];
        foreach ($indexRows as $row) {
            $markdownFile = trim((string)($row['markdown_file'] ?? ''));
            if ($markdownFile !== '') {
                $docFiles[] = $markdownFile;
            }
        }
        foreach (array_values(array_unique($docFiles)) as $filename) {
            $path = $releaseExecutionDir . DIRECTORY_SEPARATOR . $filename;
            if (!is_file($path)) {
                continue;
            }
            $text = (string)file_get_contents($path);
            foreach (['不写数据库', '不代表受控发布', 'SIMULATED_TRIAL_NOT_REAL_RECORD'] as $marker) {
                if (!str_contains($text, $marker)) {
                    $findings[] = [
                        'severity' => 'high',
                        'id' => 'release_execution_doc_missing_guardrail',
                        'message' => basename($path) . ' 缺少边界标识：' . $marker,
                    ];
                }
            }
            if (preg_match('/已批准发布|可以写库|准许写库|正式运行记录|(本公司|公司|实验室).{0,12}(已取得|已获|获得)\s*CNAS|CNAS\s*认可证书/u', $text)) {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'release_execution_doc_overstates_status',
                    'message' => basename($path) . ' 疑似包含越权状态表述。',
                ];
            }
        }

        return [
            'release_execution_dir' => $releaseExecutionDir,
            'status' => self::hasHighFinding($findings) ? 'invalid' : 'passed',
            'templates' => count($indexRows),
            'expected_templates' => count(self::EXPECTED_RELEASE_EXECUTION_TEMPLATE_CODES),
            'field_detail_rows' => count($detailRows),
            'trial_instances' => count($trialRows),
            'template_markdown_files' => $templateDocCount,
            'source_release_objects' => (int)($manifestCounts['source_release_objects'] ?? 0),
            'source_approval_items' => (int)($manifestCounts['source_approval_items'] ?? 0),
            'source_training_items' => (int)($manifestCounts['source_training_items'] ?? 0),
            'source_obsolete_items' => (int)($manifestCounts['source_obsolete_items'] ?? 0),
            'source_effectiveness_items' => (int)($manifestCounts['source_effectiveness_items'] ?? 0),
            'findings' => $findings,
        ];
    }

    private static function inspectFieldCatalog(string $fieldCatalogDir, array $recordTemplateRows): array
    {
        $findings = [];
        $fieldCatalogDir = rtrim($fieldCatalogDir, '/\\');
        $manifestPath = $fieldCatalogDir . DIRECTORY_SEPARATOR . self::REQUIRED_FIELD_CATALOG_FILES['manifest'];
        $manifest = [];
        if (!is_file($manifestPath)) {
            $findings[] = [
                'severity' => 'high',
                'id' => 'missing_field_catalog_manifest',
                'message' => '字段字典包缺少 field_catalog_manifest.json。',
            ];
        } else {
            $manifest = json_decode((string)file_get_contents($manifestPath), true) ?: [];
            if ((string)($manifest['status'] ?? '') !== 'field_catalog_no_database_write') {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'invalid_field_catalog_manifest_status',
                    'message' => '字段字典包 manifest 状态必须为 field_catalog_no_database_write。',
                ];
            }
            $guardrails = implode("\n", (array)($manifest['guardrails'] ?? []));
            foreach (['不写数据库', '不代表受控发布', '不代表真实记录'] as $marker) {
                if (!str_contains($guardrails, $marker)) {
                    $findings[] = [
                        'severity' => 'high',
                        'id' => 'field_catalog_manifest_missing_guardrail',
                        'message' => '字段字典包 manifest 缺少边界标识：' . $marker,
                    ];
                }
            }
        }

        foreach (self::REQUIRED_FIELD_CATALOG_FILES as $key => $filename) {
            $path = $fieldCatalogDir . DIRECTORY_SEPARATOR . $filename;
            if (!is_file($path)) {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'missing_field_catalog_' . $key,
                    'message' => '字段字典包缺少文件：' . $filename,
                ];
            }
        }

        $indexPath = $fieldCatalogDir . DIRECTORY_SEPARATOR . self::REQUIRED_FIELD_CATALOG_FILES['template_index'];
        $detailPath = $fieldCatalogDir . DIRECTORY_SEPARATOR . self::REQUIRED_FIELD_CATALOG_FILES['field_detail'];
        $indexRows = is_file($indexPath) ? self::readCsv($indexPath) : [];
        $detailRows = is_file($detailPath) ? self::readCsv($detailPath) : [];

        $sourceSchemas = [];
        foreach ($recordTemplateRows as $row) {
            $code = trim((string)($row['doc_number'] ?? ''));
            if ($code === '') {
                continue;
            }
            $schema = json_decode((string)($row['field_schema_json'] ?? ''), true);
            $sourceSchemas[$code] = is_array($schema) ? $schema : [];
        }

        $indexByCode = [];
        foreach ($indexRows as $row) {
            $code = trim((string)($row['template_code'] ?? ''));
            if ($code === '') {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'field_catalog_blank_template_code',
                    'message' => '字段字典模板索引存在空 template_code。',
                ];
                continue;
            }
            if (isset($indexByCode[$code])) {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'field_catalog_duplicate_template_code',
                    'message' => '字段字典模板索引存在重复 template_code：' . $code,
                ];
            }
            $indexByCode[$code] = $row;
        }

        $detailsByCode = [];
        foreach ($detailRows as $row) {
            $code = trim((string)($row['template_code'] ?? ''));
            $key = trim((string)($row['field_key'] ?? ''));
            if ($code === '' || $key === '') {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'field_catalog_blank_field_identity',
                    'message' => '字段字典明细存在空 template_code 或 field_key。',
                ];
                continue;
            }
            $detailsByCode[$code][] = $row;
        }

        $missingCatalogTemplates = array_values(array_diff(array_keys($sourceSchemas), array_keys($indexByCode)));
        if ($missingCatalogTemplates !== []) {
            $findings[] = [
                'severity' => 'high',
                'id' => 'field_catalog_missing_templates',
                'message' => '字段字典缺少模板：' . implode('、', array_slice($missingCatalogTemplates, 0, 12)),
            ];
        }
        $extraCatalogTemplates = array_values(array_diff(array_keys($indexByCode), array_keys($sourceSchemas)));
        if ($extraCatalogTemplates !== []) {
            $findings[] = [
                'severity' => 'high',
                'id' => 'field_catalog_extra_templates',
                'message' => '字段字典包含非预导入模板：' . implode('、', array_slice($extraCatalogTemplates, 0, 12)),
            ];
        }

        foreach ($sourceSchemas as $code => $schema) {
            $sourceKeys = [];
            foreach ($schema as $field) {
                if (is_array($field) && isset($field['key'])) {
                    $sourceKeys[] = (string)$field['key'];
                }
            }
            $detailRowsForCode = (array)($detailsByCode[$code] ?? []);
            $detailKeys = array_values(array_filter(array_map(static fn(array $row): string => trim((string)($row['field_key'] ?? '')), $detailRowsForCode)));
            $missingFields = array_values(array_diff($sourceKeys, $detailKeys));
            $extraFields = array_values(array_diff($detailKeys, $sourceKeys));
            if ($missingFields !== []) {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'field_catalog_missing_fields',
                    'message' => $code . ' 字段字典缺少字段：' . implode('、', array_slice($missingFields, 0, 12)),
                ];
            }
            if ($extraFields !== []) {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'field_catalog_extra_fields',
                    'message' => $code . ' 字段字典包含源 schema 不存在字段：' . implode('、', array_slice($extraFields, 0, 12)),
                ];
            }
            if (count($detailRowsForCode) !== count($schema)) {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'field_catalog_field_count_mismatch',
                    'message' => $code . ' 字段数量不一致：catalog=' . count($detailRowsForCode) . '，schema=' . count($schema),
                ];
            }
            $missingCommon = array_values(array_diff(self::FIELD_CATALOG_COMMON_KEYS, $detailKeys));
            if ($missingCommon !== []) {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'field_catalog_missing_common_fields',
                    'message' => $code . ' 字段字典缺少通用治理字段：' . implode('、', $missingCommon),
                ];
            }
            foreach ($detailRowsForCode as $row) {
                if ((string)($row['trial_value_present'] ?? '') !== 'yes') {
                    $findings[] = [
                        'severity' => 'high',
                        'id' => 'field_catalog_trial_value_missing',
                        'message' => $code . '/' . (string)($row['field_key'] ?? '-') . ' 未被全量试填覆盖。',
                    ];
                }
            }
            $templateDoc = (string)($indexByCode[$code]['catalog_markdown_file'] ?? '');
            if ($templateDoc === '' || !is_file($fieldCatalogDir . DIRECTORY_SEPARATOR . $templateDoc)) {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'field_catalog_missing_template_doc',
                    'message' => $code . ' 缺少逐模板字段字典 Markdown。',
                ];
            }
        }

        $templateDocs = glob($fieldCatalogDir . DIRECTORY_SEPARATOR . 'templates' . DIRECTORY_SEPARATOR . '*.md') ?: [];
        $manifestCounts = (array)($manifest['counts'] ?? []);
        if (isset($manifestCounts['record_templates']) && (int)$manifestCounts['record_templates'] !== count($indexRows)) {
            $findings[] = [
                'severity' => 'high',
                'id' => 'field_catalog_manifest_template_count_mismatch',
                'message' => '字段字典 manifest 模板数与索引不一致：manifest=' . (string)$manifestCounts['record_templates'] . '，index=' . count($indexRows),
            ];
        }
        if (isset($manifestCounts['fields']) && (int)$manifestCounts['fields'] !== count($detailRows)) {
            $findings[] = [
                'severity' => 'high',
                'id' => 'field_catalog_manifest_field_count_mismatch',
                'message' => '字段字典 manifest 字段数与明细不一致：manifest=' . (string)$manifestCounts['fields'] . '，detail=' . count($detailRows),
            ];
        }

        return [
            'field_catalog_dir' => $fieldCatalogDir,
            'status' => self::hasHighFinding($findings) ? 'invalid' : 'passed',
            'record_templates' => count($indexRows),
            'source_record_templates' => count($sourceSchemas),
            'field_detail_rows' => count($detailRows),
            'template_markdown_files' => count($templateDocs),
            'human_confirmation_fields' => count(array_filter(
                $detailRows,
                static fn(array $row): bool => (string)($row['human_confirmation_required'] ?? '') === 'yes'
            )),
            'findings' => $findings,
        ];
    }

    private static function inspectStage2ReviewWorkbench(string $stage2ReviewDir): array
    {
        $findings = [];
        $stage2ReviewDir = rtrim($stage2ReviewDir, '/\\');
        $manifestPath = $stage2ReviewDir . DIRECTORY_SEPARATOR . self::REQUIRED_STAGE2_REVIEW_FILES['manifest'];
        $manifest = [];
        $manifestStatus = 'missing';
        if (!is_file($manifestPath)) {
            $findings[] = [
                'severity' => 'high',
                'id' => 'missing_stage2_review_manifest',
                'message' => '第二阶段人工复核工作台缺少 stage2_review_workbench_manifest.json。',
            ];
        } else {
            $manifest = json_decode((string)file_get_contents($manifestPath), true) ?: [];
            $manifestStatus = (string)($manifest['status'] ?? '');
            if ($manifestStatus !== 'stage2_structured_review_workbench_no_database_write') {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'invalid_stage2_review_manifest_status',
                    'message' => '第二阶段人工复核工作台 manifest 状态不符合预期。',
                ];
            }
            $guardrailText = implode("\n", array_map(static fn($value): string => (string)$value, (array)($manifest['guardrails'] ?? [])));
            foreach (self::STAGE2_REVIEW_REQUIRED_GUARDRAILS as $marker) {
                if (!str_contains($guardrailText, $marker)) {
                    $findings[] = [
                        'severity' => 'high',
                        'id' => 'stage2_review_manifest_missing_guardrail',
                        'message' => '第二阶段人工复核工作台 manifest 缺少边界标识：' . $marker,
                    ];
                }
            }
        }

        $files = (array)($manifest['files'] ?? []);
        $paths = [];
        foreach (self::REQUIRED_STAGE2_REVIEW_FILES as $key => $defaultFilename) {
            $filename = (string)($files[$key] ?? $defaultFilename);
            $path = $stage2ReviewDir . DIRECTORY_SEPARATOR . $filename;
            $paths[$key] = $path;
            if (!is_file($path)) {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'missing_stage2_review_' . $key,
                    'message' => '第二阶段人工复核工作台缺少文件：' . $filename,
                ];
            }
        }

        foreach (glob($stage2ReviewDir . DIRECTORY_SEPARATOR . '*.sql') ?: [] as $path) {
            $findings[] = ['severity' => 'high', 'id' => 'stage2_review_forbidden_database_artifact', 'message' => '第二阶段人工复核工作台不应包含 SQL 文件：' . basename($path)];
        }
        foreach (glob($stage2ReviewDir . DIRECTORY_SEPARATOR . '*.db') ?: [] as $path) {
            $findings[] = ['severity' => 'high', 'id' => 'stage2_review_forbidden_database_artifact', 'message' => '第二阶段人工复核工作台不应包含数据库文件：' . basename($path)];
        }

        $blockRows = is_file($paths['block_review_matrix'] ?? '') ? self::readCsv($paths['block_review_matrix']) : [];
        $linkRows = is_file($paths['link_review_matrix'] ?? '') ? self::readCsv($paths['link_review_matrix']) : [];
        $summaryRows = is_file($paths['clause_target_summary'] ?? '') ? self::readCsv($paths['clause_target_summary']) : [];
        $targetRows = is_file($paths['target_backreference'] ?? '') ? self::readCsv($paths['target_backreference']) : [];
        $decisionRows = is_file($paths['decision_template'] ?? '') ? self::readCsv($paths['decision_template']) : [];

        $decisionIds = [];
        $approved = 0;
        $pending = 0;
        $revise = 0;
        $remove = 0;
        $invalid = 0;
        $missingComments = 0;
        foreach ($decisionRows as $index => $row) {
            $line = $index + 2;
            $decisionId = trim((string)($row['decision_item_id'] ?? ''));
            if ($decisionId === '') {
                $findings[] = ['severity' => 'high', 'id' => 'stage2_review_blank_decision_id', 'message' => '人工复核意见回填模板第 ' . $line . ' 行 decision_item_id 为空。'];
            } elseif (isset($decisionIds[$decisionId])) {
                $findings[] = ['severity' => 'high', 'id' => 'stage2_review_duplicate_decision_id', 'message' => '人工复核意见回填模板存在重复 decision_item_id：' . $decisionId];
            }
            $decisionIds[$decisionId] = true;

            if ((string)($row['not_imported'] ?? '') !== 'yes') {
                $findings[] = ['severity' => 'high', 'id' => 'stage2_review_decision_not_marked_not_imported', 'message' => ($decisionId ?: '第 ' . $line . ' 行') . ' 必须保留 not_imported=yes。'];
            }

            $rawDecision = trim((string)($row['proposed_human_decision'] ?? ''));
            $normalized = self::normalizeStage2ReviewDecision($rawDecision);
            $allowed = self::stage2AllowedDecisions((string)($row['allowed_decisions'] ?? ''));
            $comment = trim((string)($row['review_comment'] ?? ''));
            if ($rawDecision === '' || $normalized === 'pending') {
                $pending++;
                continue;
            }
            if (!isset($allowed[$normalized])) {
                $invalid++;
                $findings[] = ['severity' => 'high', 'id' => 'stage2_review_invalid_decision', 'message' => ($decisionId ?: '第 ' . $line . ' 行') . ' 的拟决策不在 allowed_decisions 范围内：' . $rawDecision];
                continue;
            }
            if ($comment === '') {
                $missingComments++;
                $findings[] = ['severity' => 'high', 'id' => 'stage2_review_missing_comment', 'message' => ($decisionId ?: '第 ' . $line . ' 行') . ' 已填写拟决策但缺少 review_comment。'];
                continue;
            }
            if ($normalized === 'approved') {
                $approved++;
            } elseif ($normalized === 'revise') {
                $revise++;
            } elseif ($normalized === 'remove') {
                $remove++;
            } else {
                $invalid++;
                $findings[] = ['severity' => 'high', 'id' => 'stage2_review_unknown_decision', 'message' => ($decisionId ?: '第 ' . $line . ' 行') . ' 的拟决策未被命令层识别：' . $rawDecision];
            }
        }

        $blockIds = [];
        foreach ($blockRows as $row) {
            $reviewId = trim((string)($row['review_item_id'] ?? ''));
            if ($reviewId !== '') {
                $blockIds[$reviewId] = true;
            }
        }
        $linkIds = [];
        foreach ($linkRows as $row) {
            $reviewId = trim((string)($row['review_item_id'] ?? ''));
            if ($reviewId !== '') {
                $linkIds[$reviewId] = true;
            }
        }
        $expectedIds = $blockIds + $linkIds;
        $missingDecisionIds = array_values(array_diff(array_keys($expectedIds), array_keys($decisionIds)));
        $extraDecisionIds = array_values(array_diff(array_keys($decisionIds), array_keys($expectedIds)));
        if ($missingDecisionIds !== []) {
            $findings[] = [
                'severity' => 'high',
                'id' => 'stage2_review_missing_decision_items',
                'message' => '二阶段回填模板缺少复核项：' . implode('、', array_slice($missingDecisionIds, 0, 12)),
            ];
        }
        if ($extraDecisionIds !== []) {
            $findings[] = [
                'severity' => 'high',
                'id' => 'stage2_review_extra_decision_items',
                'message' => '二阶段回填模板存在额外复核项：' . implode('、', array_slice($extraDecisionIds, 0, 12)),
            ];
        }

        $manifestCounts = (array)($manifest['counts'] ?? []);
        $actualCounts = [
            'block_review_rows' => count($blockRows),
            'link_review_rows' => count($linkRows),
            'clause_summary_rows' => count($summaryRows),
            'target_backreference_rows' => count($targetRows),
            'decision_rows' => count($decisionRows),
            'database_write_performed' => 0,
        ];
        foreach ($actualCounts as $key => $actual) {
            if (!array_key_exists($key, $manifestCounts)) {
                continue;
            }
            if ((int)$manifestCounts[$key] !== $actual) {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'stage2_review_count_mismatch_' . $key,
                    'message' => '第二阶段人工复核工作台 ' . $key . ' 计数不一致：manifest=' . (string)$manifestCounts[$key] . '，actual=' . (string)$actual,
                ];
            }
        }

        if (self::hasHighFinding($findings)) {
            $status = 'invalid';
        } elseif (count($decisionRows) === 0) {
            $status = 'empty';
        } elseif ($pending > 0) {
            $status = 'pending';
        } elseif ($revise > 0 || $remove > 0) {
            $status = 'changes_required';
        } else {
            $status = 'approved';
        }

        return [
            'stage2_review_dir' => $stage2ReviewDir,
            'status' => $status,
            'manifest_status' => $manifestStatus,
            'block_review_rows' => count($blockRows),
            'link_review_rows' => count($linkRows),
            'clause_summary_rows' => count($summaryRows),
            'target_backreference_rows' => count($targetRows),
            'decision_items' => count($decisionRows),
            'approved_decisions' => $approved,
            'pending_decisions' => $pending,
            'revise_decisions' => $revise,
            'remove_decisions' => $remove,
            'invalid_decisions' => $invalid,
            'missing_review_comments' => $missingComments,
            'database_write_performed' => 0,
            'findings' => $findings,
        ];
    }

    private static function inspectStage2ReviewDecisionPreview(string $previewDir): array
    {
        $findings = [];
        $previewDir = rtrim($previewDir, '/\\');
        $manifestPath = $previewDir . DIRECTORY_SEPARATOR . self::REQUIRED_STAGE2_REVIEW_PREVIEW_FILES['manifest'];
        $manifest = [];
        $manifestStatus = 'missing';
        $readiness = '';
        if (!is_file($manifestPath)) {
            $findings[] = [
                'severity' => 'high',
                'id' => 'missing_stage2_review_preview_manifest',
                'message' => '第二阶段复核意见回填预览包缺少 stage2_review_decision_preview_manifest.json。',
            ];
        } else {
            $manifest = json_decode((string)file_get_contents($manifestPath), true) ?: [];
            $manifestStatus = (string)($manifest['status'] ?? '');
            $readiness = (string)($manifest['readiness'] ?? '');
            if ($manifestStatus !== 'stage2_review_decision_preview_no_database_write') {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'invalid_stage2_review_preview_manifest_status',
                    'message' => '第二阶段复核意见回填预览包 manifest 状态不符合预期。',
                ];
            }
            $guardrailText = implode("\n", array_map(static fn($value): string => (string)$value, (array)($manifest['guardrails'] ?? [])));
            foreach (self::STAGE2_REVIEW_PREVIEW_REQUIRED_GUARDRAILS as $marker) {
                if (!str_contains($guardrailText, $marker)) {
                    $findings[] = [
                        'severity' => 'high',
                        'id' => 'stage2_review_preview_manifest_missing_guardrail',
                        'message' => '第二阶段复核意见回填预览包 manifest 缺少边界标识：' . $marker,
                    ];
                }
            }
        }

        $files = (array)($manifest['files'] ?? []);
        $paths = [];
        foreach (self::REQUIRED_STAGE2_REVIEW_PREVIEW_FILES as $key => $defaultFilename) {
            $filename = (string)($files[$key] ?? $defaultFilename);
            $path = $previewDir . DIRECTORY_SEPARATOR . $filename;
            $paths[$key] = $path;
            if (!is_file($path)) {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'missing_stage2_review_preview_' . $key,
                    'message' => '第二阶段复核意见回填预览包缺少文件：' . $filename,
                ];
            }
        }

        foreach (glob($previewDir . DIRECTORY_SEPARATOR . '*.sql') ?: [] as $path) {
            $findings[] = ['severity' => 'high', 'id' => 'stage2_review_preview_forbidden_database_artifact', 'message' => '第二阶段复核意见回填预览包不应包含 SQL 文件：' . basename($path)];
        }
        foreach (glob($previewDir . DIRECTORY_SEPARATOR . '*.db') ?: [] as $path) {
            $findings[] = ['severity' => 'high', 'id' => 'stage2_review_preview_forbidden_database_artifact', 'message' => '第二阶段复核意见回填预览包不应包含数据库文件：' . basename($path)];
        }

        $decisionRows = is_file($paths['decision_preview'] ?? '') ? self::readCsv($paths['decision_preview']) : [];
        $blockingRows = is_file($paths['blocking_items'] ?? '') ? self::readCsv($paths['blocking_items']) : [];
        $summaryRows = is_file($paths['scope_summary'] ?? '') ? self::readCsv($paths['scope_summary']) : [];

        $decisionIds = [];
        $actualCounts = [
            'decision_rows' => count($decisionRows),
            'proposed_decisions' => 0,
            'not_proposed' => 0,
            'pending_decisions' => 0,
            'accepted_for_preview' => 0,
            'invalid_decisions' => 0,
            'missing_review_comments' => 0,
            'blocking_items' => 0,
            'database_write_performed' => (int)($manifest['counts']['database_write_performed'] ?? 0),
        ];
        foreach ($decisionRows as $index => $row) {
            $line = $index + 2;
            $decisionId = trim((string)($row['decision_item_id'] ?? ''));
            if ($decisionId === '') {
                $findings[] = ['severity' => 'high', 'id' => 'stage2_review_preview_blank_decision_id', 'message' => '拟回填决策预览第 ' . $line . ' 行 decision_item_id 为空。'];
            } elseif (isset($decisionIds[$decisionId])) {
                $findings[] = ['severity' => 'high', 'id' => 'stage2_review_preview_duplicate_decision_id', 'message' => '拟回填决策预览存在重复 decision_item_id：' . $decisionId];
            }
            $decisionIds[$decisionId] = true;
            if ((string)($row['not_imported'] ?? '') !== 'yes') {
                $findings[] = ['severity' => 'high', 'id' => 'stage2_review_preview_not_marked_not_imported', 'message' => ($decisionId ?: '第 ' . $line . ' 行') . ' 必须保留 not_imported=yes。'];
            }
            if (trim((string)($row['proposed_human_decision'] ?? '')) !== '') {
                $actualCounts['proposed_decisions']++;
            }
            $previewResult = (string)($row['preview_result'] ?? '');
            if (array_key_exists($previewResult, [
                'not_proposed' => true,
                'pending' => true,
                'accepted_for_preview' => true,
                'invalid_decision' => true,
                'missing_review_comment' => true,
            ])) {
                $counterKey = match ($previewResult) {
                    'pending' => 'pending_decisions',
                    'accepted_for_preview' => 'accepted_for_preview',
                    'invalid_decision' => 'invalid_decisions',
                    'missing_review_comment' => 'missing_review_comments',
                    default => 'not_proposed',
                };
                $actualCounts[$counterKey]++;
            }
            if ((string)($row['will_remain_blocking'] ?? '') === 'yes') {
                $actualCounts['blocking_items']++;
            }
            if ($previewResult === 'accepted_for_preview' && trim((string)($row['review_comment'] ?? '')) === '') {
                $findings[] = ['severity' => 'high', 'id' => 'stage2_review_preview_accepted_without_comment', 'message' => ($decisionId ?: '第 ' . $line . ' 行') . ' 被视为可预览回填但缺少 review_comment。'];
            }
        }

        $manifestCounts = (array)($manifest['counts'] ?? []);
        foreach ($actualCounts as $key => $actual) {
            if (!array_key_exists($key, $manifestCounts)) {
                continue;
            }
            if ((int)$manifestCounts[$key] !== $actual) {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'stage2_review_preview_count_mismatch_' . $key,
                    'message' => '第二阶段复核意见回填预览包 ' . $key . ' 计数不一致：manifest=' . (string)$manifestCounts[$key] . '，actual=' . (string)$actual,
                ];
            }
        }
        if (count($blockingRows) !== $actualCounts['blocking_items']) {
            $findings[] = [
                'severity' => 'high',
                'id' => 'stage2_review_preview_blocking_count_mismatch',
                'message' => '仍阻断项清单行数与拟回填决策预览不一致：blocking_csv=' . count($blockingRows) . '，actual=' . (string)$actualCounts['blocking_items'],
            ];
        }
        $summaryTotal = 0;
        foreach ($summaryRows as $row) {
            $summaryTotal += (int)($row['decision_rows'] ?? 0);
        }
        if ($summaryRows !== [] && $summaryTotal !== $actualCounts['decision_rows']) {
            $findings[] = [
                'severity' => 'high',
                'id' => 'stage2_review_preview_summary_count_mismatch',
                'message' => '按范围统计合计与拟回填决策预览不一致：summary=' . $summaryTotal . '，actual=' . (string)$actualCounts['decision_rows'],
            ];
        }
        if ($actualCounts['database_write_performed'] !== 0) {
            $findings[] = ['severity' => 'high', 'id' => 'stage2_review_preview_database_write_flagged', 'message' => '第二阶段复核意见回填预览包 database_write_performed 必须为 0。'];
        }
        if ($actualCounts['invalid_decisions'] > 0 || $actualCounts['missing_review_comments'] > 0) {
            $findings[] = [
                'severity' => 'high',
                'id' => 'stage2_review_preview_contains_invalid_rows',
                'message' => '第二阶段复核意见回填预览包存在非法决策或缺少 review_comment 的行。',
            ];
        }

        return [
            'stage2_review_preview_dir' => $previewDir,
            'status' => self::hasHighFinding($findings) ? 'invalid' : 'passed',
            'manifest_status' => $manifestStatus,
            'readiness' => $readiness,
            'decision_items' => $actualCounts['decision_rows'],
            'proposed_decisions' => $actualCounts['proposed_decisions'],
            'not_proposed' => $actualCounts['not_proposed'],
            'pending_decisions' => $actualCounts['pending_decisions'],
            'accepted_for_preview' => $actualCounts['accepted_for_preview'],
            'invalid_decisions' => $actualCounts['invalid_decisions'],
            'missing_review_comments' => $actualCounts['missing_review_comments'],
            'blocking_items' => $actualCounts['blocking_items'],
            'scope_summary_rows' => count($summaryRows),
            'database_write_performed' => $actualCounts['database_write_performed'],
            'findings' => $findings,
        ];
    }

    private static function inspectGovernanceReadinessDashboard(string $dashboardDir): array
    {
        $findings = [];
        $dashboardDir = rtrim($dashboardDir, '/\\');
        $manifestPath = $dashboardDir . DIRECTORY_SEPARATOR . self::REQUIRED_GOVERNANCE_READINESS_FILES['manifest'];
        $manifest = [];
        $manifestStatus = 'missing';
        $readiness = '';
        $readyForApply = '';
        if (!is_file($manifestPath)) {
            $findings[] = [
                'severity' => 'high',
                'id' => 'missing_governance_readiness_manifest',
                'message' => '治理就绪总览包缺少 governance_readiness_manifest.json。',
            ];
        } else {
            $manifest = json_decode((string)file_get_contents($manifestPath), true) ?: [];
            $manifestStatus = (string)($manifest['status'] ?? '');
            $readiness = (string)($manifest['readiness'] ?? '');
            $readyForApply = (string)($manifest['ready_for_lims_apply'] ?? '');
            if ($manifestStatus !== 'governance_readiness_no_database_write') {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'invalid_governance_readiness_manifest_status',
                    'message' => '治理就绪总览包 manifest 状态不符合预期。',
                ];
            }
            $guardrailText = implode("\n", array_map(static fn($value): string => (string)$value, (array)($manifest['guardrails'] ?? [])));
            foreach (self::GOVERNANCE_READINESS_REQUIRED_GUARDRAILS as $marker) {
                if (!str_contains($guardrailText, $marker)) {
                    $findings[] = [
                        'severity' => 'high',
                        'id' => 'governance_readiness_manifest_missing_guardrail',
                        'message' => '治理就绪总览包 manifest 缺少边界标识：' . $marker,
                    ];
                }
            }
        }

        $files = (array)($manifest['files'] ?? []);
        $paths = [];
        foreach (self::REQUIRED_GOVERNANCE_READINESS_FILES as $key => $defaultFilename) {
            $filename = (string)($files[$key] ?? $defaultFilename);
            $path = $dashboardDir . DIRECTORY_SEPARATOR . $filename;
            $paths[$key] = $path;
            if (!is_file($path)) {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'missing_governance_readiness_' . $key,
                    'message' => '治理就绪总览包缺少文件：' . $filename,
                ];
            }
        }

        foreach (self::forbiddenDatabaseArtifacts($dashboardDir) as $path) {
            $findings[] = [
                'severity' => 'high',
                'id' => 'governance_readiness_forbidden_database_artifact',
                'message' => '治理就绪总览包不应包含数据库/SQL 文件：' . basename((string)$path),
            ];
        }

        $gateRows = is_file($paths['gate_register'] ?? '') ? self::readCsv($paths['gate_register']) : [];
        $taskRows = is_file($paths['human_task_register'] ?? '') ? self::readCsv($paths['human_task_register']) : [];

        $gateIds = [];
        $blockingGates = 0;
        foreach ($gateRows as $index => $row) {
            $line = $index + 2;
            $gateId = trim((string)($row['gate_id'] ?? ''));
            if ($gateId === '') {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'governance_readiness_blank_gate_id',
                    'message' => '总闸门清单第 ' . $line . ' 行 gate_id 为空。',
                ];
            } elseif (isset($gateIds[$gateId])) {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'governance_readiness_duplicate_gate_id',
                    'message' => '总闸门清单存在重复 gate_id：' . $gateId,
                ];
            }
            $gateIds[$gateId] = true;
            if ((string)($row['not_real_record'] ?? '') !== 'yes') {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'governance_readiness_gate_not_real_marker_missing',
                    'message' => ($gateId ?: '第 ' . $line . ' 行') . ' 必须保留 not_real_record=yes。',
                ];
            }
            if (!in_array((string)($row['blocks_apply'] ?? ''), ['yes', 'no'], true)) {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'governance_readiness_gate_blocks_apply_invalid',
                    'message' => ($gateId ?: '第 ' . $line . ' 行') . ' blocks_apply 必须为 yes/no。',
                ];
            }
            if ((int)($row['blocking_items'] ?? 0) > 0 && (string)($row['blocks_apply'] ?? '') === 'yes') {
                $blockingGates++;
            }
        }

        $missingGateIds = array_values(array_diff(self::REQUIRED_GOVERNANCE_GATE_IDS, array_keys($gateIds)));
        if ($missingGateIds !== []) {
            $findings[] = [
                'severity' => 'high',
                'id' => 'governance_readiness_missing_required_gates',
                'message' => '治理总览缺少必备闸门：' . implode('、', $missingGateIds),
            ];
        }

        $blockingTasks = 0;
        $taskIds = [];
        foreach ($taskRows as $index => $row) {
            $line = $index + 2;
            $taskId = trim((string)($row['task_id'] ?? ''));
            if ($taskId === '') {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'governance_readiness_blank_task_id',
                    'message' => '人工处理任务清单第 ' . $line . ' 行 task_id 为空。',
                ];
            } elseif (isset($taskIds[$taskId])) {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'governance_readiness_duplicate_task_id',
                    'message' => '人工处理任务清单存在重复 task_id：' . $taskId,
                ];
            }
            $taskIds[$taskId] = true;
            $gateId = trim((string)($row['gate_id'] ?? ''));
            if ($gateId === '' || !isset($gateIds[$gateId])) {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'governance_readiness_task_unknown_gate',
                    'message' => ($taskId ?: '第 ' . $line . ' 行') . ' 引用未知 gate_id：' . $gateId,
                ];
            }
            if ((string)($row['not_real_record'] ?? '') !== 'yes') {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'governance_readiness_task_not_real_marker_missing',
                    'message' => ($taskId ?: '第 ' . $line . ' 行') . ' 必须保留 not_real_record=yes。',
                ];
            }
            if (!in_array((string)($row['blocking_if_unresolved'] ?? ''), ['yes', 'no'], true)) {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'governance_readiness_task_blocking_flag_invalid',
                    'message' => ($taskId ?: '第 ' . $line . ' 行') . ' blocking_if_unresolved 必须为 yes/no。',
                ];
            }
            if (
                (string)($row['blocking_if_unresolved'] ?? '') === 'yes'
                && in_array((string)($row['current_status'] ?? ''), ['', 'pending', 'pending_human_review'], true)
            ) {
                $blockingTasks++;
            }
        }

        $manifestCounts = (array)($manifest['counts'] ?? []);
        $actualCounts = [
            'gate_rows' => count($gateRows),
            'blocking_gates' => $blockingGates,
            'human_task_rows' => count($taskRows),
            'blocking_tasks' => $blockingTasks,
            'database_write_performed' => (int)($manifestCounts['database_write_performed'] ?? 0),
        ];
        foreach ($actualCounts as $key => $actual) {
            if (!array_key_exists($key, $manifestCounts)) {
                continue;
            }
            if ((int)$manifestCounts[$key] !== $actual) {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'governance_readiness_count_mismatch_' . $key,
                    'message' => '治理就绪总览包 ' . $key . ' 计数不一致：manifest=' . (string)$manifestCounts[$key] . '，actual=' . (string)$actual,
                ];
            }
        }
        if ($actualCounts['database_write_performed'] !== 0) {
            $findings[] = [
                'severity' => 'high',
                'id' => 'governance_readiness_database_write_flagged',
                'message' => '治理就绪总览包 database_write_performed 必须为 0。',
            ];
        }
        if ($blockingTasks > 0 && $readyForApply !== 'no') {
            $findings[] = [
                'severity' => 'high',
                'id' => 'governance_readiness_ready_flag_conflicts_with_blocking_tasks',
                'message' => '仍有阻断任务时 ready_for_lims_apply 必须为 no。',
            ];
        }

        foreach (['overview', 'command_checklist', 'readme'] as $key) {
            $path = (string)($paths[$key] ?? '');
            if (!is_file($path)) {
                continue;
            }
            $text = (string)file_get_contents($path);
            foreach (['不写数据库', '不代表人工评审通过', '不写入质量手册正文'] as $marker) {
                if (!str_contains($text, $marker)) {
                    $findings[] = [
                        'severity' => 'high',
                        'id' => 'governance_readiness_doc_missing_guardrail',
                        'message' => basename($path) . ' 缺少边界标识：' . $marker,
                    ];
                }
            }
        }

        return [
            'governance_readiness_dir' => $dashboardDir,
            'status' => self::hasHighFinding($findings) ? 'invalid' : 'passed',
            'manifest_status' => $manifestStatus,
            'readiness' => $readiness,
            'ready_for_lims_apply' => $readyForApply,
            'gate_rows' => $actualCounts['gate_rows'],
            'blocking_gates' => $actualCounts['blocking_gates'],
            'human_task_rows' => $actualCounts['human_task_rows'],
            'blocking_tasks' => $actualCounts['blocking_tasks'],
            'database_write_performed' => $actualCounts['database_write_performed'],
            'findings' => $findings,
        ];
    }

    private static function inspectGovernanceClosureWorkbench(string $workbenchDir): array
    {
        $findings = [];
        $workbenchDir = rtrim($workbenchDir, '/\\');
        $manifestPath = $workbenchDir . DIRECTORY_SEPARATOR . self::REQUIRED_GOVERNANCE_CLOSURE_FILES['manifest'];
        $manifest = [];
        $manifestStatus = 'missing';
        $readiness = '';
        $readyForRefresh = '';
        $readyForApply = '';
        if (!is_file($manifestPath)) {
            $findings[] = [
                'severity' => 'high',
                'id' => 'missing_governance_closure_manifest',
                'message' => '治理关闭工作台缺少 governance_closure_workbench_manifest.json。',
            ];
        } else {
            $manifest = json_decode((string)file_get_contents($manifestPath), true) ?: [];
            $manifestStatus = (string)($manifest['status'] ?? '');
            $readiness = (string)($manifest['readiness'] ?? '');
            $readyForRefresh = (string)($manifest['ready_for_governance_readiness_refresh'] ?? '');
            $readyForApply = (string)($manifest['ready_for_lims_apply'] ?? '');
            if ($manifestStatus !== 'governance_closure_workbench_no_database_write') {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'invalid_governance_closure_manifest_status',
                    'message' => '治理关闭工作台 manifest 状态不符合预期。',
                ];
            }
            $guardrailText = implode("\n", array_map(static fn($value): string => (string)$value, (array)($manifest['guardrails'] ?? [])));
            foreach (self::GOVERNANCE_CLOSURE_REQUIRED_GUARDRAILS as $marker) {
                if (!str_contains($guardrailText, $marker)) {
                    $findings[] = [
                        'severity' => 'high',
                        'id' => 'governance_closure_manifest_missing_guardrail',
                        'message' => '治理关闭工作台 manifest 缺少边界标识：' . $marker,
                    ];
                }
            }
        }

        $files = (array)($manifest['files'] ?? []);
        $paths = [];
        foreach (self::REQUIRED_GOVERNANCE_CLOSURE_FILES as $key => $defaultFilename) {
            $filename = (string)($files[$key] ?? $defaultFilename);
            $path = $workbenchDir . DIRECTORY_SEPARATOR . $filename;
            $paths[$key] = $path;
            if (!is_file($path)) {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'missing_governance_closure_' . $key,
                    'message' => '治理关闭工作台缺少文件：' . $filename,
                ];
            }
        }

        foreach (self::forbiddenDatabaseArtifacts($workbenchDir) as $path) {
            $findings[] = [
                'severity' => 'high',
                'id' => 'governance_closure_forbidden_database_artifact',
                'message' => '治理关闭工作台不应包含数据库/SQL 文件：' . basename((string)$path),
            ];
        }

        $gateRows = is_file($paths['gate_closure_matrix'] ?? '') ? self::readCsv($paths['gate_closure_matrix']) : [];
        $roleRows = is_file($paths['role_task_pack'] ?? '') ? self::readCsv($paths['role_task_pack']) : [];
        $evidenceRows = is_file($paths['evidence_template'] ?? '') ? self::readCsv($paths['evidence_template']) : [];
        $closureRows = is_file($paths['closure_template'] ?? '') ? self::readCsv($paths['closure_template']) : [];

        $evidenceIds = [];
        foreach ($evidenceRows as $index => $row) {
            $line = $index + 2;
            $closureId = trim((string)($row['closure_item_id'] ?? ''));
            if ($closureId !== '') {
                $evidenceIds[$closureId] = true;
            }
            if ((string)($row['not_real_record'] ?? '') !== 'yes') {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'governance_closure_evidence_not_real_marker_missing',
                    'message' => ($closureId ?: '证据采集第 ' . $line . ' 行') . ' 必须保留 not_real_record=yes。',
                ];
            }
            if (!in_array((string)($row['blocks_apply'] ?? ''), ['yes', 'no'], true)) {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'governance_closure_evidence_blocks_apply_invalid',
                    'message' => ($closureId ?: '证据采集第 ' . $line . ' 行') . ' blocks_apply 必须为 yes/no。',
                ];
            }
        }

        foreach ($gateRows as $index => $row) {
            $line = $index + 2;
            if ((string)($row['not_real_record'] ?? '') !== 'yes') {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'governance_closure_gate_not_real_marker_missing',
                    'message' => '总闸门关闭矩阵第 ' . $line . ' 行必须保留 not_real_record=yes。',
                ];
            }
        }

        $closureIds = [];
        $acceptedClosures = 0;
        $pendingClosures = 0;
        $openBlockingItems = 0;
        $invalidClosureRows = 0;
        foreach ($closureRows as $index => $row) {
            $line = $index + 2;
            $closureId = trim((string)($row['closure_item_id'] ?? ''));
            if ($closureId === '') {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'governance_closure_blank_closure_item_id',
                    'message' => '拟关闭回填模板第 ' . $line . ' 行 closure_item_id 为空。',
                ];
            } elseif (isset($closureIds[$closureId])) {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'governance_closure_duplicate_closure_item_id',
                    'message' => '拟关闭回填模板存在重复 closure_item_id：' . $closureId,
                ];
            }
            if ($closureId !== '') {
                $closureIds[$closureId] = true;
            }
            if ($closureId !== '' && !isset($evidenceIds[$closureId])) {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'governance_closure_missing_evidence_row',
                    'message' => $closureId . ' 缺少对应证据采集模板行。',
                ];
            }
            if ((string)($row['not_real_record'] ?? '') !== 'yes') {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'governance_closure_not_real_marker_missing',
                    'message' => ($closureId ?: '拟关闭第 ' . $line . ' 行') . ' 必须保留 not_real_record=yes。',
                ];
            }
            if ((string)($row['not_imported'] ?? '') !== 'yes') {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'governance_closure_not_imported_marker_missing',
                    'message' => ($closureId ?: '拟关闭第 ' . $line . ' 行') . ' 必须保留 not_imported=yes。',
                ];
            }
            if (!in_array((string)($row['blocks_apply'] ?? ''), ['yes', 'no'], true)) {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'governance_closure_blocks_apply_invalid',
                    'message' => ($closureId ?: '拟关闭第 ' . $line . ' 行') . ' blocks_apply 必须为 yes/no。',
                ];
            }

            $blocksApply = (string)($row['blocks_apply'] ?? '') === 'yes';
            $status = self::normalizeGovernanceClosureStatus((string)($row['proposed_closure_status'] ?? ''));
            if ($status === 'pending') {
                $pendingClosures++;
                if ($blocksApply) {
                    $openBlockingItems++;
                }
                continue;
            }
            if (!in_array($status, ['closed', 'not_applicable', 'waived', 'rejected'], true)) {
                $invalidClosureRows++;
                if ($blocksApply) {
                    $openBlockingItems++;
                }
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'governance_closure_unknown_status',
                    'message' => ($closureId ?: '拟关闭第 ' . $line . ' 行') . ' proposed_closure_status 不在允许范围内：' . $status,
                ];
                continue;
            }
            if ($status === 'rejected') {
                $invalidClosureRows++;
                if ($blocksApply) {
                    $openBlockingItems++;
                }
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'governance_closure_rejected_or_reopened',
                    'message' => ($closureId ?: '拟关闭第 ' . $line . ' 行') . ' 仍为 rejected/reopen 状态，不能视为已关闭。',
                ];
                continue;
            }

            $missingFields = [];
            foreach (['evidence_reference', 'closure_comment', 'reviewer', 'review_date'] as $field) {
                if (trim((string)($row[$field] ?? '')) === '') {
                    $missingFields[] = $field;
                }
            }
            if ($missingFields !== []) {
                $invalidClosureRows++;
                if ($blocksApply) {
                    $openBlockingItems++;
                }
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'governance_closure_missing_required_fields',
                    'message' => ($closureId ?: '拟关闭第 ' . $line . ' 行') . ' 已填写关闭状态但缺少必填字段：' . implode('、', $missingFields),
                ];
                continue;
            }
            $acceptedClosures++;
        }

        $manifestCounts = (array)($manifest['counts'] ?? []);
        $actualCounts = [
            'gate_rows' => count($gateRows),
            'role_task_batches' => count($roleRows),
            'evidence_rows' => count($evidenceRows),
            'closure_rows' => count($closureRows),
            'blocking_closure_items' => count(array_filter($closureRows, static fn($row): bool => (string)($row['blocks_apply'] ?? '') === 'yes')),
            'open_blocking_items' => $openBlockingItems,
            'accepted_closures' => $acceptedClosures,
            'pending_closures' => $pendingClosures,
            'database_write_performed' => (int)($manifestCounts['database_write_performed'] ?? 0),
        ];
        foreach ($actualCounts as $key => $actual) {
            if (!array_key_exists($key, $manifestCounts)) {
                continue;
            }
            if ((int)$manifestCounts[$key] !== $actual) {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'governance_closure_count_mismatch_' . $key,
                    'message' => '治理关闭工作台 ' . $key . ' 计数不一致：manifest=' . (string)$manifestCounts[$key] . '，actual=' . (string)$actual,
                ];
            }
        }
        if ($actualCounts['database_write_performed'] !== 0) {
            $findings[] = [
                'severity' => 'high',
                'id' => 'governance_closure_database_write_flagged',
                'message' => '治理关闭工作台 database_write_performed 必须为 0。',
            ];
        }
        if ($openBlockingItems > 0 && $readyForRefresh !== 'no') {
            $findings[] = [
                'severity' => 'high',
                'id' => 'governance_closure_ready_refresh_conflicts_with_open_items',
                'message' => '仍有未关闭阻断项时 ready_for_governance_readiness_refresh 必须为 no。',
            ];
        }
        if ($readyForApply === 'yes') {
            $findings[] = [
                'severity' => 'high',
                'id' => 'governance_closure_cannot_authorize_lims_apply',
                'message' => '治理关闭工作台不能单独声明 ready_for_lims_apply=yes。',
            ];
        }

        foreach (['overview', 'priority_batches', 'readme'] as $key) {
            $path = (string)($paths[$key] ?? '');
            if (!is_file($path)) {
                continue;
            }
            $text = (string)file_get_contents($path);
            foreach (['不写数据库', '不代表人工评审通过', '不写入质量手册正文'] as $marker) {
                if (!str_contains($text, $marker)) {
                    $findings[] = [
                        'severity' => 'high',
                        'id' => 'governance_closure_doc_missing_guardrail',
                        'message' => basename($path) . ' 缺少边界标识：' . $marker,
                    ];
                }
            }
        }

        return [
            'governance_closure_dir' => $workbenchDir,
            'status' => self::hasHighFinding($findings) ? 'invalid' : 'passed',
            'manifest_status' => $manifestStatus,
            'readiness' => $readiness,
            'ready_for_governance_readiness_refresh' => $readyForRefresh,
            'ready_for_lims_apply' => $readyForApply,
            'gate_rows' => $actualCounts['gate_rows'],
            'role_task_batches' => $actualCounts['role_task_batches'],
            'evidence_rows' => $actualCounts['evidence_rows'],
            'closure_rows' => $actualCounts['closure_rows'],
            'blocking_closure_items' => $actualCounts['blocking_closure_items'],
            'open_blocking_items' => $actualCounts['open_blocking_items'],
            'accepted_closures' => $actualCounts['accepted_closures'],
            'pending_closures' => $actualCounts['pending_closures'],
            'invalid_closure_rows' => $invalidClosureRows,
            'database_write_performed' => $actualCounts['database_write_performed'],
            'findings' => $findings,
        ];
    }

    private static function inspectGovernanceClosureExecutionPack(string $executionDir): array
    {
        $findings = [];
        $executionDir = rtrim($executionDir, '/\\');
        $manifestPath = $executionDir . DIRECTORY_SEPARATOR . self::REQUIRED_GOVERNANCE_CLOSURE_EXECUTION_FILES['manifest'];
        $manifest = [];
        $manifestStatus = 'missing';
        $readiness = '';
        $readyForPreview = '';
        $readyForApply = '';
        if (!is_file($manifestPath)) {
            $findings[] = [
                'severity' => 'high',
                'id' => 'missing_governance_closure_execution_manifest',
                'message' => '治理闭环执行包缺少 governance_closure_execution_manifest.json。',
            ];
        } else {
            $manifest = json_decode((string)file_get_contents($manifestPath), true) ?: [];
            $manifestStatus = (string)($manifest['status'] ?? '');
            $readiness = (string)($manifest['readiness'] ?? '');
            $readyForPreview = (string)($manifest['ready_for_governance_closure_preview'] ?? '');
            $readyForApply = (string)($manifest['ready_for_lims_apply'] ?? '');
            if ($manifestStatus !== 'governance_closure_execution_pack_no_database_write') {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'invalid_governance_closure_execution_manifest_status',
                    'message' => '治理闭环执行包 manifest 状态不符合预期。',
                ];
            }
            $guardrailText = implode("\n", array_map(static fn($value): string => (string)$value, (array)($manifest['guardrails'] ?? [])));
            foreach (self::GOVERNANCE_CLOSURE_EXECUTION_REQUIRED_GUARDRAILS as $marker) {
                if (!str_contains($guardrailText, $marker)) {
                    $findings[] = [
                        'severity' => 'high',
                        'id' => 'governance_closure_execution_manifest_missing_guardrail',
                        'message' => '治理闭环执行包 manifest 缺少边界标识：' . $marker,
                    ];
                }
            }
        }

        $files = (array)($manifest['files'] ?? []);
        $paths = [];
        foreach (self::REQUIRED_GOVERNANCE_CLOSURE_EXECUTION_FILES as $key => $defaultFilename) {
            $filename = (string)($files[$key] ?? $defaultFilename);
            $path = $executionDir . DIRECTORY_SEPARATOR . $filename;
            $paths[$key] = $path;
            if (!is_file($path)) {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'missing_governance_closure_execution_' . $key,
                    'message' => '治理闭环执行包缺少文件：' . $filename,
                ];
            }
        }

        foreach (self::forbiddenDatabaseArtifacts($executionDir) as $path) {
            $findings[] = [
                'severity' => 'high',
                'id' => 'governance_closure_execution_forbidden_database_artifact',
                'message' => '治理闭环执行包不应包含数据库/SQL 文件：' . basename((string)$path),
            ];
        }

        $batchRows = is_file($paths['execution_batches'] ?? '') ? self::readCsv($paths['execution_batches']) : [];
        $signatureRows = is_file($paths['signature_register'] ?? '') ? self::readCsv($paths['signature_register']) : [];
        $handoffRows = is_file($paths['handoff_checklist'] ?? '') ? self::readCsv($paths['handoff_checklist']) : [];
        $routeRows = is_file($paths['route_index'] ?? '') ? self::readCsv($paths['route_index']) : [];

        $batchIds = [];
        $blockingBatchSum = 0;
        foreach ($batchRows as $index => $row) {
            $line = $index + 2;
            $batchId = trim((string)($row['execution_batch_id'] ?? ''));
            if ($batchId === '') {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'governance_closure_execution_blank_batch_id',
                    'message' => '闭环执行批次第 ' . $line . ' 行 execution_batch_id 为空。',
                ];
            } elseif (isset($batchIds[$batchId])) {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'governance_closure_execution_duplicate_batch_id',
                    'message' => '闭环执行批次存在重复 execution_batch_id：' . $batchId,
                ];
            }
            if ($batchId !== '') {
                $batchIds[$batchId] = true;
            }
            foreach (['not_real_record' => 'not_real_record=yes', 'not_imported' => 'not_imported=yes'] as $field => $label) {
                if ((string)($row[$field] ?? '') !== 'yes') {
                    $findings[] = [
                        'severity' => 'high',
                        'id' => 'governance_closure_execution_batch_marker_missing',
                        'message' => ($batchId ?: '闭环执行批次第 ' . $line . ' 行') . ' 必须保留 ' . $label . '。',
                    ];
                }
            }
            if (!in_array((string)($row['execution_status'] ?? ''), ['pending', 'completed', 'rejected'], true)) {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'governance_closure_execution_status_invalid',
                    'message' => ($batchId ?: '闭环执行批次第 ' . $line . ' 行') . ' execution_status 必须为 pending/completed/rejected。',
                ];
            }
            $blockingBatchSum += max((int)($row['blocking_count'] ?? 0), 0);
        }

        $signatureIds = [];
        $pendingSignatureRows = 0;
        foreach ($signatureRows as $index => $row) {
            $line = $index + 2;
            $signatureId = trim((string)($row['signature_id'] ?? ''));
            if ($signatureId === '') {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'governance_closure_execution_blank_signature_id',
                    'message' => '岗位签核第 ' . $line . ' 行 signature_id 为空。',
                ];
            } elseif (isset($signatureIds[$signatureId])) {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'governance_closure_execution_duplicate_signature_id',
                    'message' => '岗位签核存在重复 signature_id：' . $signatureId,
                ];
            }
            if ($signatureId !== '') {
                $signatureIds[$signatureId] = true;
            }
            foreach (['not_real_record' => 'not_real_record=yes', 'not_imported' => 'not_imported=yes'] as $field => $label) {
                if ((string)($row[$field] ?? '') !== 'yes') {
                    $findings[] = [
                        'severity' => 'high',
                        'id' => 'governance_closure_execution_signature_marker_missing',
                        'message' => ($signatureId ?: '岗位签核第 ' . $line . ' 行') . ' 必须保留 ' . $label . '。',
                    ];
                }
            }
            $status = (string)($row['signature_status'] ?? '');
            if (!in_array($status, ['pending', 'completed', 'rejected'], true)) {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'governance_closure_execution_signature_status_invalid',
                    'message' => ($signatureId ?: '岗位签核第 ' . $line . ' 行') . ' signature_status 必须为 pending/completed/rejected。',
                ];
            }
            if ($status === 'pending') {
                $pendingSignatureRows++;
            }
            if ($status === 'completed') {
                $missingFields = [];
                foreach (['assigned_person', 'reviewer', 'actual_finish_date'] as $field) {
                    if (trim((string)($row[$field] ?? '')) === '') {
                        $missingFields[] = $field;
                    }
                }
                if ($missingFields !== []) {
                    $findings[] = [
                        'severity' => 'high',
                        'id' => 'governance_closure_execution_completed_signature_missing_fields',
                        'message' => ($signatureId ?: '岗位签核第 ' . $line . ' 行') . ' 已 completed 但缺少字段：' . implode('、', $missingFields),
                    ];
                }
            }
        }

        $pendingHandoffChecks = 0;
        $handoffIds = [];
        foreach ($handoffRows as $index => $row) {
            $line = $index + 2;
            $checkId = trim((string)($row['handoff_check_id'] ?? ''));
            if ($checkId === '') {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'governance_closure_execution_blank_handoff_id',
                    'message' => '交接复核第 ' . $line . ' 行 handoff_check_id 为空。',
                ];
            } elseif (isset($handoffIds[$checkId])) {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'governance_closure_execution_duplicate_handoff_id',
                    'message' => '交接复核存在重复 handoff_check_id：' . $checkId,
                ];
            }
            if ($checkId !== '') {
                $handoffIds[$checkId] = true;
            }
            foreach (['not_real_record' => 'not_real_record=yes', 'not_imported' => 'not_imported=yes'] as $field => $label) {
                if ((string)($row[$field] ?? '') !== 'yes') {
                    $findings[] = [
                        'severity' => 'high',
                        'id' => 'governance_closure_execution_handoff_marker_missing',
                        'message' => ($checkId ?: '交接复核第 ' . $line . ' 行') . ' 必须保留 ' . $label . '。',
                    ];
                }
            }
            if (!in_array((string)($row['check_status'] ?? ''), ['pending', 'completed', 'rejected'], true)) {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'governance_closure_execution_handoff_status_invalid',
                    'message' => ($checkId ?: '交接复核第 ' . $line . ' 行') . ' check_status 必须为 pending/completed/rejected。',
                ];
            }
            if ((string)($row['check_status'] ?? '') === 'pending') {
                $pendingHandoffChecks++;
            }
            if (!in_array((string)($row['blocks_apply'] ?? ''), ['yes', 'no'], true)) {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'governance_closure_execution_handoff_blocks_apply_invalid',
                    'message' => ($checkId ?: '交接复核第 ' . $line . ' 行') . ' blocks_apply 必须为 yes/no。',
                ];
            }
            $batchId = trim((string)($row['execution_batch_id'] ?? ''));
            if ($batchId !== '' && !isset($batchIds[$batchId])) {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'governance_closure_execution_handoff_unknown_batch',
                    'message' => ($checkId ?: '交接复核第 ' . $line . ' 行') . ' 指向不存在的 execution_batch_id。',
                ];
            }
        }

        $routeIds = [];
        $pendingRouteItems = 0;
        $blockingRouteItems = 0;
        $routesWithoutBatch = 0;
        foreach ($routeRows as $index => $row) {
            $line = $index + 2;
            $closureId = trim((string)($row['closure_item_id'] ?? ''));
            if ($closureId === '') {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'governance_closure_execution_blank_closure_id',
                    'message' => '回填路径第 ' . $line . ' 行 closure_item_id 为空。',
                ];
            } elseif (isset($routeIds[$closureId])) {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'governance_closure_execution_duplicate_closure_id',
                    'message' => '回填路径存在重复 closure_item_id：' . $closureId,
                ];
            }
            if ($closureId !== '') {
                $routeIds[$closureId] = true;
            }
            foreach (['not_real_record' => 'not_real_record=yes', 'not_imported' => 'not_imported=yes'] as $field => $label) {
                if ((string)($row[$field] ?? '') !== 'yes') {
                    $findings[] = [
                        'severity' => 'high',
                        'id' => 'governance_closure_execution_route_marker_missing',
                        'message' => ($closureId ?: '回填路径第 ' . $line . ' 行') . ' 必须保留 ' . $label . '。',
                    ];
                }
            }
            if (!in_array((string)($row['route_status'] ?? ''), ['pending', 'ready', 'rejected'], true)) {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'governance_closure_execution_route_status_invalid',
                    'message' => ($closureId ?: '回填路径第 ' . $line . ' 行') . ' route_status 必须为 pending/ready/rejected。',
                ];
            }
            if ((string)($row['route_status'] ?? '') === 'pending') {
                $pendingRouteItems++;
            }
            if ((string)($row['blocks_apply'] ?? '') === 'yes') {
                $blockingRouteItems++;
            } elseif ((string)($row['blocks_apply'] ?? '') !== 'no') {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'governance_closure_execution_route_blocks_apply_invalid',
                    'message' => ($closureId ?: '回填路径第 ' . $line . ' 行') . ' blocks_apply 必须为 yes/no。',
                ];
            }
            $batchId = trim((string)($row['execution_batch_id'] ?? ''));
            if ($batchId === '' || !isset($batchIds[$batchId])) {
                $routesWithoutBatch++;
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'governance_closure_execution_route_unknown_batch',
                    'message' => ($closureId ?: '回填路径第 ' . $line . ' 行') . ' 未匹配到有效 execution_batch_id。',
                ];
            }
        }

        $manifestCounts = (array)($manifest['counts'] ?? []);
        $actualCounts = [
            'execution_batches' => count($batchRows),
            'signature_rows' => count($signatureRows),
            'handoff_checks' => count($handoffRows),
            'route_rows' => count($routeRows),
            'source_closure_items' => count($routeRows),
            'blocking_route_items' => $blockingRouteItems,
            'pending_signature_rows' => $pendingSignatureRows,
            'pending_handoff_checks' => $pendingHandoffChecks,
            'pending_route_items' => $pendingRouteItems,
            'database_write_performed' => (int)($manifestCounts['database_write_performed'] ?? 0),
        ];
        foreach ($actualCounts as $key => $actual) {
            if (!array_key_exists($key, $manifestCounts)) {
                continue;
            }
            if ((int)$manifestCounts[$key] !== $actual) {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'governance_closure_execution_count_mismatch_' . $key,
                    'message' => '治理闭环执行包 ' . $key . ' 计数不一致：manifest=' . (string)$manifestCounts[$key] . '，actual=' . (string)$actual,
                ];
            }
        }
        if ($blockingBatchSum !== $blockingRouteItems) {
            $findings[] = [
                'severity' => 'high',
                'id' => 'governance_closure_execution_blocking_sum_mismatch',
                'message' => '执行批次 blocking_count 合计 ' . (string)$blockingBatchSum . '，回填路径 blocking_route_items 实际 ' . (string)$blockingRouteItems . '。',
            ];
        }
        if ($routesWithoutBatch > 0) {
            $findings[] = [
                'severity' => 'high',
                'id' => 'governance_closure_execution_routes_without_batch',
                'message' => (string)$routesWithoutBatch . ' 条回填路径未匹配到执行批次。',
            ];
        }
        if ($actualCounts['database_write_performed'] !== 0) {
            $findings[] = [
                'severity' => 'high',
                'id' => 'governance_closure_execution_database_write_flagged',
                'message' => '治理闭环执行包 database_write_performed 必须为 0。',
            ];
        }
        if (($pendingSignatureRows > 0 || $pendingHandoffChecks > 0 || $pendingRouteItems > 0) && $readyForPreview !== 'no') {
            $findings[] = [
                'severity' => 'high',
                'id' => 'governance_closure_execution_ready_preview_conflicts_with_pending_items',
                'message' => '仍有 pending 签核/交接/路径时 ready_for_governance_closure_preview 必须为 no。',
            ];
        }
        if ($readyForApply === 'yes') {
            $findings[] = [
                'severity' => 'high',
                'id' => 'governance_closure_execution_cannot_authorize_lims_apply',
                'message' => '治理闭环执行包不能单独声明 ready_for_lims_apply=yes。',
            ];
        }

        foreach (['overview', 'blocking_summary', 'readme'] as $key) {
            $path = (string)($paths[$key] ?? '');
            if (!is_file($path)) {
                continue;
            }
            $text = (string)file_get_contents($path);
            foreach (['不写数据库', '不代表人工评审通过', '不写入质量手册正文'] as $marker) {
                if (!str_contains($text, $marker)) {
                    $findings[] = [
                        'severity' => 'high',
                        'id' => 'governance_closure_execution_doc_missing_guardrail',
                        'message' => basename($path) . ' 缺少边界标识：' . $marker,
                    ];
                }
            }
        }

        return [
            'governance_closure_execution_dir' => $executionDir,
            'status' => self::hasHighFinding($findings) ? 'invalid' : 'passed',
            'manifest_status' => $manifestStatus,
            'readiness' => $readiness,
            'ready_for_governance_closure_preview' => $readyForPreview,
            'ready_for_lims_apply' => $readyForApply,
            'execution_batches' => $actualCounts['execution_batches'],
            'signature_rows' => $actualCounts['signature_rows'],
            'handoff_checks' => $actualCounts['handoff_checks'],
            'route_rows' => $actualCounts['route_rows'],
            'source_closure_items' => $actualCounts['source_closure_items'],
            'blocking_route_items' => $actualCounts['blocking_route_items'],
            'pending_signature_rows' => $actualCounts['pending_signature_rows'],
            'pending_handoff_checks' => $actualCounts['pending_handoff_checks'],
            'pending_route_items' => $actualCounts['pending_route_items'],
            'database_write_performed' => $actualCounts['database_write_performed'],
            'findings' => $findings,
        ];
    }

    private static function inspectGovernanceClosurePilotPack(string $pilotDir): array
    {
        $findings = [];
        $pilotDir = rtrim($pilotDir, '/\\');
        $manifestPath = $pilotDir . DIRECTORY_SEPARATOR . self::REQUIRED_GOVERNANCE_CLOSURE_PILOT_FILES['manifest'];
        $manifest = [];
        $manifestStatus = 'missing';
        $readiness = '';
        $readyForPreview = '';
        $readyForApply = '';
        if (!is_file($manifestPath)) {
            $findings[] = [
                'severity' => 'high',
                'id' => 'missing_governance_closure_pilot_manifest',
                'message' => '治理关闭最小试点包缺少 governance_closure_pilot_manifest.json。',
            ];
        } else {
            $manifest = json_decode((string)file_get_contents($manifestPath), true) ?: [];
            $manifestStatus = (string)($manifest['status'] ?? '');
            $readiness = (string)($manifest['readiness'] ?? '');
            $readyForPreview = (string)($manifest['ready_for_governance_closure_preview'] ?? '');
            $readyForApply = (string)($manifest['ready_for_lims_apply'] ?? '');
            if ($manifestStatus !== 'governance_closure_pilot_pack_no_database_write') {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'invalid_governance_closure_pilot_manifest_status',
                    'message' => '治理关闭最小试点包 manifest 状态不符合预期。',
                ];
            }
            $guardrailText = implode("\n", array_map(static fn($value): string => (string)$value, (array)($manifest['guardrails'] ?? [])));
            foreach (self::GOVERNANCE_CLOSURE_PILOT_REQUIRED_GUARDRAILS as $marker) {
                if (!str_contains($guardrailText, $marker)) {
                    $findings[] = [
                        'severity' => 'high',
                        'id' => 'governance_closure_pilot_manifest_missing_guardrail',
                        'message' => '治理关闭最小试点包 manifest 缺少边界标识：' . $marker,
                    ];
                }
            }
        }

        $files = (array)($manifest['files'] ?? []);
        $paths = [];
        foreach (self::REQUIRED_GOVERNANCE_CLOSURE_PILOT_FILES as $key => $defaultFilename) {
            $filename = (string)($files[$key] ?? $defaultFilename);
            $path = $pilotDir . DIRECTORY_SEPARATOR . $filename;
            $paths[$key] = $path;
            if (!is_file($path)) {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'missing_governance_closure_pilot_' . $key,
                    'message' => '治理关闭最小试点包缺少文件：' . $filename,
                ];
            }
        }

        foreach (self::forbiddenDatabaseArtifacts($pilotDir) as $path) {
            $findings[] = [
                'severity' => 'high',
                'id' => 'governance_closure_pilot_forbidden_database_artifact',
                'message' => '治理关闭最小试点包不应包含数据库/SQL 文件：' . basename((string)$path),
            ];
        }

        $batchRows = is_file($paths['pilot_batches'] ?? '') ? self::readCsv($paths['pilot_batches']) : [];
        $evidenceRows = is_file($paths['pilot_evidence'] ?? '') ? self::readCsv($paths['pilot_evidence']) : [];
        $handoffRows = is_file($paths['pilot_handoff'] ?? '') ? self::readCsv($paths['pilot_handoff']) : [];

        $batchIds = [];
        $pendingPilotBatches = 0;
        foreach ($batchRows as $index => $row) {
            $line = $index + 2;
            $pilotBatchId = trim((string)($row['pilot_batch_id'] ?? ''));
            if ($pilotBatchId === '') {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'governance_closure_pilot_blank_batch_id',
                    'message' => '试点批次第 ' . $line . ' 行 pilot_batch_id 为空。',
                ];
            } elseif (isset($batchIds[$pilotBatchId])) {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'governance_closure_pilot_duplicate_batch_id',
                    'message' => '试点批次存在重复 pilot_batch_id：' . $pilotBatchId,
                ];
            }
            if ($pilotBatchId !== '') {
                $batchIds[$pilotBatchId] = true;
            }
            foreach (['not_real_record' => 'not_real_record=yes', 'not_imported' => 'not_imported=yes'] as $field => $label) {
                if ((string)($row[$field] ?? '') !== 'yes') {
                    $findings[] = [
                        'severity' => 'high',
                        'id' => 'governance_closure_pilot_batch_marker_missing',
                        'message' => ($pilotBatchId ?: '试点批次第 ' . $line . ' 行') . ' 必须保留 ' . $label . '。',
                    ];
                }
            }
            $status = (string)($row['pilot_status'] ?? '');
            if (!in_array($status, ['pending', 'completed', 'rejected'], true)) {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'governance_closure_pilot_batch_status_invalid',
                    'message' => ($pilotBatchId ?: '试点批次第 ' . $line . ' 行') . ' pilot_status 必须为 pending/completed/rejected。',
                ];
            }
            if ($status === 'pending') {
                $pendingPilotBatches++;
            }
        }

        $evidenceIds = [];
        $pendingPilotEvidence = 0;
        $blockingPilotItems = 0;
        foreach ($evidenceRows as $index => $row) {
            $line = $index + 2;
            $evidenceId = trim((string)($row['pilot_evidence_id'] ?? ''));
            if ($evidenceId === '') {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'governance_closure_pilot_blank_evidence_id',
                    'message' => '试点证据第 ' . $line . ' 行 pilot_evidence_id 为空。',
                ];
            } elseif (isset($evidenceIds[$evidenceId])) {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'governance_closure_pilot_duplicate_evidence_id',
                    'message' => '试点证据存在重复 pilot_evidence_id：' . $evidenceId,
                ];
            }
            if ($evidenceId !== '') {
                $evidenceIds[$evidenceId] = true;
            }
            foreach (['not_real_record' => 'not_real_record=yes', 'not_imported' => 'not_imported=yes'] as $field => $label) {
                if ((string)($row[$field] ?? '') !== 'yes') {
                    $findings[] = [
                        'severity' => 'high',
                        'id' => 'governance_closure_pilot_evidence_marker_missing',
                        'message' => ($evidenceId ?: '试点证据第 ' . $line . ' 行') . ' 必须保留 ' . $label . '。',
                    ];
                }
            }
            $pilotBatchId = trim((string)($row['pilot_batch_id'] ?? ''));
            if ($pilotBatchId === '' || !isset($batchIds[$pilotBatchId])) {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'governance_closure_pilot_evidence_unknown_batch',
                    'message' => ($evidenceId ?: '试点证据第 ' . $line . ' 行') . ' 指向不存在的 pilot_batch_id。',
                ];
            }
            $status = (string)($row['evidence_status'] ?? '');
            if (!in_array($status, ['pending', 'ready', 'rejected'], true)) {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'governance_closure_pilot_evidence_status_invalid',
                    'message' => ($evidenceId ?: '试点证据第 ' . $line . ' 行') . ' evidence_status 必须为 pending/ready/rejected。',
                ];
            }
            if ($status === 'pending') {
                $pendingPilotEvidence++;
            }
            if ($status === 'ready') {
                $missingFields = [];
                foreach (['evidence_reference', 'evidence_summary', 'closure_comment', 'reviewer', 'review_date'] as $field) {
                    if (trim((string)($row[$field] ?? '')) === '') {
                        $missingFields[] = $field;
                    }
                }
                if ($missingFields !== []) {
                    $findings[] = [
                        'severity' => 'high',
                        'id' => 'governance_closure_pilot_ready_evidence_missing_fields',
                        'message' => ($evidenceId ?: '试点证据第 ' . $line . ' 行') . ' 已 ready 但缺少字段：' . implode('、', $missingFields),
                    ];
                }
            }
            if ((string)($row['blocks_apply'] ?? '') === 'yes') {
                $blockingPilotItems++;
            } elseif ((string)($row['blocks_apply'] ?? '') !== 'no') {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'governance_closure_pilot_blocks_apply_invalid',
                    'message' => ($evidenceId ?: '试点证据第 ' . $line . ' 行') . ' blocks_apply 必须为 yes/no。',
                ];
            }
        }

        $handoffIds = [];
        $pendingPilotHandoffs = 0;
        foreach ($handoffRows as $index => $row) {
            $line = $index + 2;
            $handoffId = trim((string)($row['pilot_handoff_id'] ?? ''));
            if ($handoffId === '') {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'governance_closure_pilot_blank_handoff_id',
                    'message' => '试点签核交接第 ' . $line . ' 行 pilot_handoff_id 为空。',
                ];
            } elseif (isset($handoffIds[$handoffId])) {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'governance_closure_pilot_duplicate_handoff_id',
                    'message' => '试点签核交接存在重复 pilot_handoff_id：' . $handoffId,
                ];
            }
            if ($handoffId !== '') {
                $handoffIds[$handoffId] = true;
            }
            foreach (['not_real_record' => 'not_real_record=yes', 'not_imported' => 'not_imported=yes'] as $field => $label) {
                if ((string)($row[$field] ?? '') !== 'yes') {
                    $findings[] = [
                        'severity' => 'high',
                        'id' => 'governance_closure_pilot_handoff_marker_missing',
                        'message' => ($handoffId ?: '试点签核交接第 ' . $line . ' 行') . ' 必须保留 ' . $label . '。',
                    ];
                }
            }
            $pilotBatchId = trim((string)($row['pilot_batch_id'] ?? ''));
            if ($pilotBatchId === '' || !isset($batchIds[$pilotBatchId])) {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'governance_closure_pilot_handoff_unknown_batch',
                    'message' => ($handoffId ?: '试点签核交接第 ' . $line . ' 行') . ' 指向不存在的 pilot_batch_id。',
                ];
            }
            foreach (['signature_status', 'handoff_status'] as $statusField) {
                $status = (string)($row[$statusField] ?? '');
                if (!in_array($status, ['pending', 'completed', 'rejected'], true)) {
                    $findings[] = [
                        'severity' => 'high',
                        'id' => 'governance_closure_pilot_handoff_status_invalid',
                        'message' => ($handoffId ?: '试点签核交接第 ' . $line . ' 行') . ' ' . $statusField . ' 必须为 pending/completed/rejected。',
                    ];
                }
            }
            if ((string)($row['handoff_status'] ?? '') === 'pending') {
                $pendingPilotHandoffs++;
            }
            if ((string)($row['signature_status'] ?? '') === 'completed' || (string)($row['handoff_status'] ?? '') === 'completed') {
                $missingFields = [];
                foreach (['assigned_person', 'reviewer', 'actual_finish_date'] as $field) {
                    if (trim((string)($row[$field] ?? '')) === '') {
                        $missingFields[] = $field;
                    }
                }
                if ($missingFields !== []) {
                    $findings[] = [
                        'severity' => 'high',
                        'id' => 'governance_closure_pilot_completed_handoff_missing_fields',
                        'message' => ($handoffId ?: '试点签核交接第 ' . $line . ' 行') . ' 已 completed 但缺少字段：' . implode('、', $missingFields),
                    ];
                }
            }
        }

        $manifestCounts = (array)($manifest['counts'] ?? []);
        $actualCounts = [
            'pilot_batches' => count($batchRows),
            'pilot_evidence_rows' => count($evidenceRows),
            'pilot_handoff_rows' => count($handoffRows),
            'blocking_pilot_items' => $blockingPilotItems,
            'pending_pilot_batches' => $pendingPilotBatches,
            'pending_pilot_evidence' => $pendingPilotEvidence,
            'pending_pilot_handoffs' => $pendingPilotHandoffs,
            'database_write_performed' => (int)($manifestCounts['database_write_performed'] ?? 0),
        ];
        foreach ($actualCounts as $key => $actual) {
            if (!array_key_exists($key, $manifestCounts)) {
                continue;
            }
            if ((int)$manifestCounts[$key] !== $actual) {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'governance_closure_pilot_count_mismatch_' . $key,
                    'message' => '治理关闭最小试点包 ' . $key . ' 计数不一致：manifest=' . (string)$manifestCounts[$key] . '，actual=' . (string)$actual,
                ];
            }
        }
        if ($actualCounts['database_write_performed'] !== 0) {
            $findings[] = [
                'severity' => 'high',
                'id' => 'governance_closure_pilot_database_write_flagged',
                'message' => '治理关闭最小试点包 database_write_performed 必须为 0。',
            ];
        }
        if (($pendingPilotBatches > 0 || $pendingPilotEvidence > 0 || $pendingPilotHandoffs > 0) && $readyForPreview !== 'no') {
            $findings[] = [
                'severity' => 'high',
                'id' => 'governance_closure_pilot_ready_preview_conflicts_with_pending_items',
                'message' => '仍有 pending 试点批次/证据/交接时 ready_for_governance_closure_preview 必须为 no。',
            ];
        }
        if ($readyForApply === 'yes') {
            $findings[] = [
                'severity' => 'high',
                'id' => 'governance_closure_pilot_cannot_authorize_lims_apply',
                'message' => '治理关闭最小试点包不能单独声明 ready_for_lims_apply=yes。',
            ];
        }

        foreach (['overview', 'rerun_commands', 'readme'] as $key) {
            $path = (string)($paths[$key] ?? '');
            if (!is_file($path)) {
                continue;
            }
            $text = (string)file_get_contents($path);
            foreach (['不写数据库', '不代表人工评审通过', '不写入质量手册正文'] as $marker) {
                if (!str_contains($text, $marker)) {
                    $findings[] = [
                        'severity' => 'high',
                        'id' => 'governance_closure_pilot_doc_missing_guardrail',
                        'message' => basename($path) . ' 缺少边界标识：' . $marker,
                    ];
                }
            }
        }

        return [
            'governance_closure_pilot_dir' => $pilotDir,
            'status' => self::hasHighFinding($findings) ? 'invalid' : 'passed',
            'manifest_status' => $manifestStatus,
            'readiness' => $readiness,
            'ready_for_governance_closure_preview' => $readyForPreview,
            'ready_for_lims_apply' => $readyForApply,
            'pilot_batches' => $actualCounts['pilot_batches'],
            'pilot_evidence_rows' => $actualCounts['pilot_evidence_rows'],
            'pilot_handoff_rows' => $actualCounts['pilot_handoff_rows'],
            'blocking_pilot_items' => $actualCounts['blocking_pilot_items'],
            'pending_pilot_batches' => $actualCounts['pending_pilot_batches'],
            'pending_pilot_evidence' => $actualCounts['pending_pilot_evidence'],
            'pending_pilot_handoffs' => $actualCounts['pending_pilot_handoffs'],
            'database_write_performed' => $actualCounts['database_write_performed'],
            'findings' => $findings,
        ];
    }

    private static function inspectGovernanceClosurePilotReturnPreview(string $previewDir): array
    {
        $findings = [];
        $previewDir = rtrim($previewDir, '/\\');
        $manifestPath = $previewDir . DIRECTORY_SEPARATOR . self::REQUIRED_GOVERNANCE_CLOSURE_PILOT_RETURN_FILES['manifest'];
        $manifest = [];
        $manifestStatus = 'missing';
        $readiness = '';
        $readyForPreview = '';
        $readyForApply = '';
        if (!is_file($manifestPath)) {
            $findings[] = [
                'severity' => 'high',
                'id' => 'missing_governance_closure_pilot_return_manifest',
                'message' => '治理关闭试点回填预览缺少 governance_closure_pilot_return_manifest.json。',
            ];
        } else {
            $manifest = json_decode((string)file_get_contents($manifestPath), true) ?: [];
            $manifestStatus = (string)($manifest['status'] ?? '');
            $readiness = (string)($manifest['readiness'] ?? '');
            $readyForPreview = (string)($manifest['ready_for_governance_closure_preview'] ?? '');
            $readyForApply = (string)($manifest['ready_for_lims_apply'] ?? '');
            if ($manifestStatus !== 'governance_closure_pilot_return_preview_no_database_write') {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'invalid_governance_closure_pilot_return_manifest_status',
                    'message' => '治理关闭试点回填预览 manifest 状态不符合预期。',
                ];
            }
            $guardrailText = implode("\n", array_map(static fn($value): string => (string)$value, (array)($manifest['guardrails'] ?? [])));
            foreach (self::GOVERNANCE_CLOSURE_PILOT_RETURN_REQUIRED_GUARDRAILS as $marker) {
                if (!str_contains($guardrailText, $marker)) {
                    $findings[] = [
                        'severity' => 'high',
                        'id' => 'governance_closure_pilot_return_manifest_missing_guardrail',
                        'message' => '治理关闭试点回填预览 manifest 缺少边界标识：' . $marker,
                    ];
                }
            }
        }

        $files = (array)($manifest['files'] ?? []);
        $paths = [];
        foreach (self::REQUIRED_GOVERNANCE_CLOSURE_PILOT_RETURN_FILES as $key => $defaultFilename) {
            $filename = (string)($files[$key] ?? $defaultFilename);
            $path = $previewDir . DIRECTORY_SEPARATOR . $filename;
            $paths[$key] = $path;
            if (!is_file($path)) {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'missing_governance_closure_pilot_return_' . $key,
                    'message' => '治理关闭试点回填预览缺少文件：' . $filename,
                ];
            }
        }

        foreach (self::forbiddenDatabaseArtifacts($previewDir) as $path) {
            $findings[] = [
                'severity' => 'high',
                'id' => 'governance_closure_pilot_return_forbidden_database_artifact',
                'message' => '治理关闭试点回填预览不应包含数据库/SQL 文件：' . basename((string)$path),
            ];
        }

        $mappingRows = is_file($paths['mapping'] ?? '') ? self::readCsv($paths['mapping']) : [];
        $sourcePreviewRows = is_file($paths['source_preview'] ?? '') ? self::readCsv($paths['source_preview']) : [];
        $missingRows = is_file($paths['missing_fields'] ?? '') ? self::readCsv($paths['missing_fields']) : [];

        $returnIds = [];
        $readyReturnItems = 0;
        $blockingReturnItems = 0;
        foreach ($mappingRows as $index => $row) {
            $line = $index + 2;
            $returnId = trim((string)($row['return_item_id'] ?? ''));
            if ($returnId === '') {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'governance_closure_pilot_return_blank_return_id',
                    'message' => '试点回填映射第 ' . $line . ' 行 return_item_id 为空。',
                ];
            } elseif (isset($returnIds[$returnId])) {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'governance_closure_pilot_return_duplicate_return_id',
                    'message' => '试点回填映射存在重复 return_item_id：' . $returnId,
                ];
            }
            if ($returnId !== '') {
                $returnIds[$returnId] = true;
            }
            foreach (['not_real_record' => 'not_real_record=yes', 'not_imported' => 'not_imported=yes'] as $field => $label) {
                if ((string)($row[$field] ?? '') !== 'yes') {
                    $findings[] = [
                        'severity' => 'high',
                        'id' => 'governance_closure_pilot_return_mapping_marker_missing',
                        'message' => ($returnId ?: '试点回填映射第 ' . $line . ' 行') . ' 必须保留 ' . $label . '。',
                    ];
                }
            }
            foreach (['source_evidence_row_found', 'source_closure_row_found', 'pilot_handoff_found'] as $field) {
                if ((string)($row[$field] ?? '') !== 'yes') {
                    $findings[] = [
                        'severity' => 'high',
                        'id' => 'governance_closure_pilot_return_mapping_missing_' . $field,
                        'message' => ($returnId ?: '试点回填映射第 ' . $line . ' 行') . ' 未完成映射字段：' . $field,
                    ];
                }
            }
            $status = (string)($row['return_status'] ?? '');
            if (!in_array($status, ['ready', 'blocked'], true)) {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'governance_closure_pilot_return_status_invalid',
                    'message' => ($returnId ?: '试点回填映射第 ' . $line . ' 行') . ' return_status 必须为 ready/blocked。',
                ];
            }
            if ($status === 'ready') {
                $readyReturnItems++;
            } else {
                $blockingReturnItems++;
            }
            if (!in_array((string)($row['blocks_apply'] ?? ''), ['yes', 'no'], true)) {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'governance_closure_pilot_return_blocks_apply_invalid',
                    'message' => ($returnId ?: '试点回填映射第 ' . $line . ' 行') . ' blocks_apply 必须为 yes/no。',
                ];
            }
        }

        $readySourcePreviewRows = 0;
        foreach ($sourcePreviewRows as $index => $row) {
            $line = $index + 2;
            foreach (['not_real_record' => 'not_real_record=yes', 'not_imported' => 'not_imported=yes'] as $field => $label) {
                if ((string)($row[$field] ?? '') !== 'yes') {
                    $findings[] = [
                        'severity' => 'high',
                        'id' => 'governance_closure_pilot_return_source_preview_marker_missing',
                        'message' => '拟回填源行第 ' . $line . ' 行必须保留 ' . $label . '。',
                    ];
                }
            }
            $returnId = trim((string)($row['return_item_id'] ?? ''));
            if ($returnId === '' || !isset($returnIds[$returnId])) {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'governance_closure_pilot_return_source_preview_unknown_return',
                    'message' => '拟回填源行第 ' . $line . ' 行指向不存在的 return_item_id。',
                ];
            }
            if (!in_array((string)($row['target_file'] ?? ''), [
                'governance_closure_workbench/03-证据采集模板.csv',
                'governance_closure_workbench/04-拟关闭回填模板.csv',
            ], true)) {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'governance_closure_pilot_return_target_file_invalid',
                    'message' => '拟回填源行第 ' . $line . ' 行 target_file 不在允许范围内。',
                ];
            }
            if (!in_array((string)($row['ready_for_manual_source_update'] ?? ''), ['yes', 'no'], true)) {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'governance_closure_pilot_return_ready_flag_invalid',
                    'message' => '拟回填源行第 ' . $line . ' 行 ready_for_manual_source_update 必须为 yes/no。',
                ];
            }
            if ((string)($row['ready_for_manual_source_update'] ?? '') === 'yes') {
                $readySourcePreviewRows++;
            }
        }

        $missingIds = [];
        foreach ($missingRows as $index => $row) {
            $line = $index + 2;
            $missingId = trim((string)($row['missing_id'] ?? ''));
            if ($missingId === '') {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'governance_closure_pilot_return_blank_missing_id',
                    'message' => '缺字段第 ' . $line . ' 行 missing_id 为空。',
                ];
            } elseif (isset($missingIds[$missingId])) {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'governance_closure_pilot_return_duplicate_missing_id',
                    'message' => '缺字段存在重复 missing_id：' . $missingId,
                ];
            }
            if ($missingId !== '') {
                $missingIds[$missingId] = true;
            }
            foreach (['not_real_record' => 'not_real_record=yes', 'not_imported' => 'not_imported=yes'] as $field => $label) {
                if ((string)($row[$field] ?? '') !== 'yes') {
                    $findings[] = [
                        'severity' => 'high',
                        'id' => 'governance_closure_pilot_return_missing_marker_missing',
                        'message' => ($missingId ?: '缺字段第 ' . $line . ' 行') . ' 必须保留 ' . $label . '。',
                    ];
                }
            }
            $returnId = trim((string)($row['return_item_id'] ?? ''));
            if ($returnId === '' || !isset($returnIds[$returnId])) {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'governance_closure_pilot_return_missing_unknown_return',
                    'message' => ($missingId ?: '缺字段第 ' . $line . ' 行') . ' 指向不存在的 return_item_id。',
                ];
            }
            if (trim((string)($row['missing_field'] ?? '')) === '') {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'governance_closure_pilot_return_missing_field_blank',
                    'message' => ($missingId ?: '缺字段第 ' . $line . ' 行') . ' missing_field 为空。',
                ];
            }
        }

        $manifestCounts = (array)($manifest['counts'] ?? []);
        $actualCounts = [
            'pilot_evidence_rows' => count($mappingRows),
            'mapping_rows' => count($mappingRows),
            'source_preview_rows' => count($sourcePreviewRows),
            'missing_field_rows' => count($missingRows),
            'ready_return_items' => $readyReturnItems,
            'blocking_return_items' => $blockingReturnItems,
            'database_write_performed' => (int)($manifestCounts['database_write_performed'] ?? 0),
        ];
        foreach ($actualCounts as $key => $actual) {
            if (!array_key_exists($key, $manifestCounts)) {
                continue;
            }
            if ((int)$manifestCounts[$key] !== $actual) {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'governance_closure_pilot_return_count_mismatch_' . $key,
                    'message' => '治理关闭试点回填预览 ' . $key . ' 计数不一致：manifest=' . (string)$manifestCounts[$key] . '，actual=' . (string)$actual,
                ];
            }
        }
        if ($actualCounts['database_write_performed'] !== 0) {
            $findings[] = [
                'severity' => 'high',
                'id' => 'governance_closure_pilot_return_database_write_flagged',
                'message' => '治理关闭试点回填预览 database_write_performed 必须为 0。',
            ];
        }
        if ($readySourcePreviewRows > 0 && $readySourcePreviewRows !== $readyReturnItems * 2) {
            $findings[] = [
                'severity' => 'high',
                'id' => 'governance_closure_pilot_return_ready_preview_row_mismatch',
                'message' => 'ready 的源行预览数应等于 ready_return_items * 2。',
            ];
        }
        if (($blockingReturnItems > 0 || count($missingRows) > 0) && $readyForPreview !== 'no') {
            $findings[] = [
                'severity' => 'high',
                'id' => 'governance_closure_pilot_return_ready_preview_conflicts_with_missing_fields',
                'message' => '仍有阻断项或缺字段时 ready_for_governance_closure_preview 必须为 no。',
            ];
        }
        if ($readyForApply === 'yes') {
            $findings[] = [
                'severity' => 'high',
                'id' => 'governance_closure_pilot_return_cannot_authorize_lims_apply',
                'message' => '治理关闭试点回填预览不能单独声明 ready_for_lims_apply=yes。',
            ];
        }

        foreach (['overview', 'rerun_path', 'readme'] as $key) {
            $path = (string)($paths[$key] ?? '');
            if (!is_file($path)) {
                continue;
            }
            $text = (string)file_get_contents($path);
            foreach (['不写数据库', '不代表人工评审通过', '不写入质量手册正文'] as $marker) {
                if (!str_contains($text, $marker)) {
                    $findings[] = [
                        'severity' => 'high',
                        'id' => 'governance_closure_pilot_return_doc_missing_guardrail',
                        'message' => basename($path) . ' 缺少边界标识：' . $marker,
                    ];
                }
            }
        }

        return [
            'governance_closure_pilot_return_dir' => $previewDir,
            'status' => self::hasHighFinding($findings) ? 'invalid' : 'passed',
            'manifest_status' => $manifestStatus,
            'readiness' => $readiness,
            'ready_for_governance_closure_preview' => $readyForPreview,
            'ready_for_lims_apply' => $readyForApply,
            'mapping_rows' => $actualCounts['mapping_rows'],
            'source_preview_rows' => $actualCounts['source_preview_rows'],
            'missing_field_rows' => $actualCounts['missing_field_rows'],
            'ready_return_items' => $actualCounts['ready_return_items'],
            'blocking_return_items' => $actualCounts['blocking_return_items'],
            'database_write_performed' => $actualCounts['database_write_performed'],
            'findings' => $findings,
        ];
    }

    private static function inspectGovernanceClosurePilotSourceUpdateRehearsal(string $rehearsalDir): array
    {
        $findings = [];
        $rehearsalDir = rtrim($rehearsalDir, '/\\');
        $manifestPath = $rehearsalDir . DIRECTORY_SEPARATOR . self::REQUIRED_GOVERNANCE_CLOSURE_PILOT_SOURCE_UPDATE_FILES['manifest'];
        $manifest = [];
        $manifestStatus = 'missing';
        $readiness = '';
        $readyForSourceUpdate = '';
        $readyForPreview = '';
        $readyForApply = '';
        if (!is_file($manifestPath)) {
            $findings[] = [
                'severity' => 'high',
                'id' => 'missing_governance_closure_pilot_source_update_manifest',
                'message' => '治理关闭试点源工作台回填补丁预演缺少 manifest。',
            ];
        } else {
            $manifest = json_decode((string)file_get_contents($manifestPath), true) ?: [];
            $manifestStatus = (string)($manifest['status'] ?? '');
            $readiness = (string)($manifest['readiness'] ?? '');
            $readyForSourceUpdate = (string)($manifest['ready_for_source_workbench_update'] ?? '');
            $readyForPreview = (string)($manifest['ready_for_governance_closure_preview'] ?? '');
            $readyForApply = (string)($manifest['ready_for_lims_apply'] ?? '');
            if ($manifestStatus !== 'governance_closure_pilot_source_update_rehearsal_no_write') {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'invalid_governance_closure_pilot_source_update_manifest_status',
                    'message' => '治理关闭试点源工作台回填补丁预演 manifest 状态不符合预期。',
                ];
            }
            $guardrailText = implode("\n", array_map(static fn($value): string => (string)$value, (array)($manifest['guardrails'] ?? [])));
            foreach (self::GOVERNANCE_CLOSURE_PILOT_SOURCE_UPDATE_REQUIRED_GUARDRAILS as $marker) {
                if (!str_contains($guardrailText, $marker)) {
                    $findings[] = [
                        'severity' => 'high',
                        'id' => 'governance_closure_pilot_source_update_manifest_missing_guardrail',
                        'message' => '治理关闭试点源工作台回填补丁预演 manifest 缺少边界标识：' . $marker,
                    ];
                }
            }
        }

        $files = (array)($manifest['files'] ?? []);
        $paths = [];
        foreach (self::REQUIRED_GOVERNANCE_CLOSURE_PILOT_SOURCE_UPDATE_FILES as $key => $defaultFilename) {
            $filename = (string)($files[$key] ?? $defaultFilename);
            $path = $rehearsalDir . DIRECTORY_SEPARATOR . $filename;
            $paths[$key] = $path;
            if (!is_file($path)) {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'missing_governance_closure_pilot_source_update_' . $key,
                    'message' => '治理关闭试点源工作台回填补丁预演缺少文件：' . $filename,
                ];
            }
        }

        foreach (self::forbiddenDatabaseArtifacts($rehearsalDir) as $path) {
            $findings[] = [
                'severity' => 'high',
                'id' => 'governance_closure_pilot_source_update_forbidden_database_artifact',
                'message' => '治理关闭试点源工作台回填补丁预演不应包含数据库/SQL 文件：' . basename((string)$path),
            ];
        }

        $patchRows = is_file($paths['patch_preview'] ?? '') ? self::readCsv($paths['patch_preview']) : [];
        $blockedRows = is_file($paths['blocked_patches'] ?? '') ? self::readCsv($paths['blocked_patches']) : [];
        $allowedTargets = [
            'governance_closure_workbench/03-证据采集模板.csv' => [
                'evidence_reference' => true,
                'evidence_owner' => true,
                'evidence_date' => true,
                'evidence_result' => true,
            ],
            'governance_closure_workbench/04-拟关闭回填模板.csv' => [
                'evidence_reference' => true,
                'closure_comment' => true,
                'reviewer' => true,
                'review_date' => true,
                'proposed_closure_status' => true,
                'closure_result' => true,
                'blocks_apply' => true,
            ],
        ];

        $patchIds = [];
        $readyPatchRows = 0;
        $blockedPatchRows = 0;
        $manualUpdateCandidateRows = 0;
        foreach ($patchRows as $index => $row) {
            $line = $index + 2;
            $patchId = trim((string)($row['patch_id'] ?? ''));
            if ($patchId === '') {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'governance_closure_pilot_source_update_blank_patch_id',
                    'message' => '源工作台回填补丁第 ' . $line . ' 行 patch_id 为空。',
                ];
            } elseif (isset($patchIds[$patchId])) {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'governance_closure_pilot_source_update_duplicate_patch_id',
                    'message' => '源工作台回填补丁存在重复 patch_id：' . $patchId,
                ];
            }
            if ($patchId !== '') {
                $patchIds[$patchId] = true;
            }
            foreach (['not_real_record' => 'not_real_record=yes', 'not_imported' => 'not_imported=yes', 'no_source_modified' => 'no_source_modified=yes'] as $field => $label) {
                if ((string)($row[$field] ?? '') !== 'yes') {
                    $findings[] = [
                        'severity' => 'high',
                        'id' => 'governance_closure_pilot_source_update_marker_missing',
                        'message' => ($patchId ?: '源工作台回填补丁第 ' . $line . ' 行') . ' 必须保留 ' . $label . '。',
                    ];
                }
            }
            $targetFile = (string)($row['target_file'] ?? '');
            $targetField = (string)($row['target_field'] ?? '');
            if (!isset($allowedTargets[$targetFile])) {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'governance_closure_pilot_source_update_target_file_invalid',
                    'message' => ($patchId ?: '源工作台回填补丁第 ' . $line . ' 行') . ' target_file 不在允许范围内。',
                ];
            } elseif (!isset($allowedTargets[$targetFile][$targetField])) {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'governance_closure_pilot_source_update_target_field_invalid',
                    'message' => ($patchId ?: '源工作台回填补丁第 ' . $line . ' 行') . ' target_field 不在允许范围内：' . $targetField,
                ];
            }
            $action = (string)($row['patch_action'] ?? '');
            if (!in_array($action, ['blocked_no_update', 'manual_update_candidate', 'no_change_candidate'], true)) {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'governance_closure_pilot_source_update_action_invalid',
                    'message' => ($patchId ?: '源工作台回填补丁第 ' . $line . ' 行') . ' patch_action 不合法。',
                ];
            }
            $updateReady = (string)($row['update_ready'] ?? '');
            if (!in_array($updateReady, ['yes', 'no'], true)) {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'governance_closure_pilot_source_update_ready_invalid',
                    'message' => ($patchId ?: '源工作台回填补丁第 ' . $line . ' 行') . ' update_ready 必须为 yes/no。',
                ];
            }
            if ($action === 'blocked_no_update') {
                $blockedPatchRows++;
                if (trim((string)($row['block_reason'] ?? '')) === '') {
                    $findings[] = [
                        'severity' => 'high',
                        'id' => 'governance_closure_pilot_source_update_block_reason_blank',
                        'message' => ($patchId ?: '源工作台回填补丁第 ' . $line . ' 行') . ' 为阻断补丁但 block_reason 为空。',
                    ];
                }
            }
            if ($action === 'manual_update_candidate') {
                $manualUpdateCandidateRows++;
            }
            if ($updateReady === 'yes') {
                $readyPatchRows++;
                if ($action !== 'manual_update_candidate') {
                    $findings[] = [
                        'severity' => 'high',
                        'id' => 'governance_closure_pilot_source_update_ready_action_mismatch',
                        'message' => ($patchId ?: '源工作台回填补丁第 ' . $line . ' 行') . ' update_ready=yes 时 patch_action 应为 manual_update_candidate。',
                    ];
                }
            }
        }

        $blockedIds = [];
        foreach ($blockedRows as $row) {
            $blockedIds[(string)($row['patch_id'] ?? '')] = true;
        }
        $expectedBlockedIds = [];
        foreach ($patchRows as $row) {
            if ((string)($row['patch_action'] ?? '') === 'blocked_no_update') {
                $expectedBlockedIds[(string)($row['patch_id'] ?? '')] = true;
            }
        }
        if (array_keys($blockedIds) !== array_keys($expectedBlockedIds)) {
            $findings[] = [
                'severity' => 'high',
                'id' => 'governance_closure_pilot_source_update_blocked_register_mismatch',
                'message' => '阻断补丁清单与补丁预览中的 blocked_no_update 行不一致。',
            ];
        }

        $manifestCounts = (array)($manifest['counts'] ?? []);
        $actualCounts = [
            'source_preview_rows' => (int)($manifestCounts['source_preview_rows'] ?? 0),
            'missing_field_rows' => (int)($manifestCounts['missing_field_rows'] ?? 0),
            'patch_rows' => count($patchRows),
            'ready_patch_rows' => $readyPatchRows,
            'blocked_patch_rows' => $blockedPatchRows,
            'manual_update_candidate_rows' => $manualUpdateCandidateRows,
            'source_workbench_modified' => (int)($manifestCounts['source_workbench_modified'] ?? 0),
            'database_write_performed' => (int)($manifestCounts['database_write_performed'] ?? 0),
        ];
        foreach ($actualCounts as $key => $actual) {
            if (!array_key_exists($key, $manifestCounts)) {
                continue;
            }
            if ((int)$manifestCounts[$key] !== $actual) {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'governance_closure_pilot_source_update_count_mismatch_' . $key,
                    'message' => '治理关闭试点源工作台回填补丁预演 ' . $key . ' 计数不一致：manifest=' . (string)$manifestCounts[$key] . '，actual=' . (string)$actual,
                ];
            }
        }
        if ($actualCounts['source_workbench_modified'] !== 0) {
            $findings[] = [
                'severity' => 'high',
                'id' => 'governance_closure_pilot_source_update_modified_source_flagged',
                'message' => '治理关闭试点源工作台回填补丁预演 source_workbench_modified 必须为 0。',
            ];
        }
        if ($actualCounts['database_write_performed'] !== 0) {
            $findings[] = [
                'severity' => 'high',
                'id' => 'governance_closure_pilot_source_update_database_write_flagged',
                'message' => '治理关闭试点源工作台回填补丁预演 database_write_performed 必须为 0。',
            ];
        }
        if ($blockedPatchRows > 0 && $readyForSourceUpdate !== 'no') {
            $findings[] = [
                'severity' => 'high',
                'id' => 'governance_closure_pilot_source_update_ready_conflicts_with_blocked',
                'message' => '仍有阻断补丁时 ready_for_source_workbench_update 必须为 no。',
            ];
        }
        if ($readyForSourceUpdate !== 'yes' && $readyForPreview === 'yes') {
            $findings[] = [
                'severity' => 'high',
                'id' => 'governance_closure_pilot_source_update_preview_conflicts_with_update',
                'message' => '源工作台更新未 ready 时 ready_for_governance_closure_preview 必须为 no。',
            ];
        }
        if ($readyForApply === 'yes') {
            $findings[] = [
                'severity' => 'high',
                'id' => 'governance_closure_pilot_source_update_cannot_authorize_lims_apply',
                'message' => '治理关闭试点源工作台回填补丁预演不能单独声明 ready_for_lims_apply=yes。',
            ];
        }

        foreach (['overview', 'manual_instructions', 'readme'] as $key) {
            $path = (string)($paths[$key] ?? '');
            if (!is_file($path)) {
                continue;
            }
            $text = (string)file_get_contents($path);
            foreach (['不写数据库', '不代表人工评审通过', '不写入质量手册正文'] as $marker) {
                if (!str_contains($text, $marker)) {
                    $findings[] = [
                        'severity' => 'high',
                        'id' => 'governance_closure_pilot_source_update_doc_missing_guardrail',
                        'message' => basename($path) . ' 缺少边界标识：' . $marker,
                    ];
                }
            }
        }

        return [
            'governance_closure_pilot_source_update_dir' => $rehearsalDir,
            'status' => self::hasHighFinding($findings) ? 'invalid' : 'passed',
            'manifest_status' => $manifestStatus,
            'readiness' => $readiness,
            'ready_for_source_workbench_update' => $readyForSourceUpdate,
            'ready_for_governance_closure_preview' => $readyForPreview,
            'ready_for_lims_apply' => $readyForApply,
            'source_preview_rows' => $actualCounts['source_preview_rows'],
            'missing_field_rows' => $actualCounts['missing_field_rows'],
            'patch_rows' => $actualCounts['patch_rows'],
            'ready_patch_rows' => $actualCounts['ready_patch_rows'],
            'blocked_patch_rows' => $actualCounts['blocked_patch_rows'],
            'manual_update_candidate_rows' => $actualCounts['manual_update_candidate_rows'],
            'source_workbench_modified' => $actualCounts['source_workbench_modified'],
            'database_write_performed' => $actualCounts['database_write_performed'],
            'findings' => $findings,
        ];
    }

    private static function inspectGovernanceClosurePilotOperatorWorkbook(string $workbookDir): array
    {
        $findings = [];
        $workbookDir = rtrim($workbookDir, '/\\');
        $manifestPath = $workbookDir . DIRECTORY_SEPARATOR . self::REQUIRED_GOVERNANCE_CLOSURE_PILOT_OPERATOR_WORKBOOK_FILES['manifest'];
        $manifest = [];
        $manifestStatus = 'missing';
        $readiness = '';
        $readyForReturnPreview = '';
        $readyForSourceUpdate = '';
        $readyForApply = '';
        if (!is_file($manifestPath)) {
            $findings[] = [
                'severity' => 'high',
                'id' => 'missing_governance_closure_pilot_operator_workbook_manifest',
                'message' => '治理关闭试点人工执行工作簿缺少 manifest。',
            ];
        } else {
            $manifest = json_decode((string)file_get_contents($manifestPath), true) ?: [];
            $manifestStatus = (string)($manifest['status'] ?? '');
            $readiness = (string)($manifest['readiness'] ?? '');
            $readyForReturnPreview = (string)($manifest['ready_for_pilot_return_preview'] ?? '');
            $readyForSourceUpdate = (string)($manifest['ready_for_source_workbench_update'] ?? '');
            $readyForApply = (string)($manifest['ready_for_lims_apply'] ?? '');
            if ($manifestStatus !== 'governance_closure_pilot_operator_workbook_no_write') {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'invalid_governance_closure_pilot_operator_workbook_manifest_status',
                    'message' => '治理关闭试点人工执行工作簿 manifest 状态不符合预期。',
                ];
            }
            $guardrailText = implode("\n", array_map(static fn($value): string => (string)$value, (array)($manifest['guardrails'] ?? [])));
            foreach (self::GOVERNANCE_CLOSURE_PILOT_OPERATOR_WORKBOOK_REQUIRED_GUARDRAILS as $marker) {
                if (!str_contains($guardrailText, $marker)) {
                    $findings[] = [
                        'severity' => 'high',
                        'id' => 'governance_closure_pilot_operator_workbook_manifest_missing_guardrail',
                        'message' => '治理关闭试点人工执行工作簿 manifest 缺少边界标识：' . $marker,
                    ];
                }
            }
        }

        $files = (array)($manifest['files'] ?? []);
        $paths = [];
        foreach (self::REQUIRED_GOVERNANCE_CLOSURE_PILOT_OPERATOR_WORKBOOK_FILES as $key => $defaultFilename) {
            $filename = (string)($files[$key] ?? $defaultFilename);
            $path = $workbookDir . DIRECTORY_SEPARATOR . $filename;
            $paths[$key] = $path;
            if ($key === 'task_card_dir') {
                if (!is_dir($path)) {
                    $findings[] = [
                        'severity' => 'high',
                        'id' => 'missing_governance_closure_pilot_operator_workbook_task_card_dir',
                        'message' => '治理关闭试点人工执行工作簿缺少 task_cards 目录。',
                    ];
                }
                continue;
            }
            if (!is_file($path)) {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'missing_governance_closure_pilot_operator_workbook_' . $key,
                    'message' => '治理关闭试点人工执行工作簿缺少文件：' . $filename,
                ];
            }
        }

        foreach (self::forbiddenDatabaseArtifacts($workbookDir) as $path) {
            $findings[] = [
                'severity' => 'high',
                'id' => 'governance_closure_pilot_operator_workbook_forbidden_database_artifact',
                'message' => '治理关闭试点人工执行工作簿不应包含数据库/SQL 文件：' . basename((string)$path),
            ];
        }

        $masterRows = is_file($paths['master'] ?? '') ? self::readCsv($paths['master']) : [];
        $fieldRows = is_file($paths['field_checklist'] ?? '') ? self::readCsv($paths['field_checklist']) : [];
        $handoffRows = is_file($paths['handoff_checklist'] ?? '') ? self::readCsv($paths['handoff_checklist']) : [];
        $taskCards = is_dir($paths['task_card_dir'] ?? '') ? (glob($paths['task_card_dir'] . DIRECTORY_SEPARATOR . '*.md') ?: []) : [];

        $itemIds = [];
        $pendingItems = 0;
        foreach ($masterRows as $index => $row) {
            $line = $index + 2;
            $itemId = trim((string)($row['workbook_item_id'] ?? ''));
            $label = $itemId !== '' ? $itemId : '主清单第 ' . $line . ' 行';
            if ($itemId === '') {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'governance_closure_pilot_operator_workbook_blank_item_id',
                    'message' => '试点执行主清单 workbook_item_id 为空。',
                ];
            } elseif (isset($itemIds[$itemId])) {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'governance_closure_pilot_operator_workbook_duplicate_item_id',
                    'message' => '试点执行主清单 workbook_item_id 重复：' . $itemId,
                ];
            }
            if ($itemId !== '') {
                $itemIds[$itemId] = true;
            }
            foreach (['not_imported' => 'not_imported=yes', 'not_real_record' => 'not_real_record=yes'] as $field => $labelText) {
                if ((string)($row[$field] ?? '') !== 'yes') {
                    $findings[] = [
                        'severity' => 'high',
                        'id' => 'governance_closure_pilot_operator_workbook_marker_missing',
                        'message' => $label . ' 必须保留 ' . $labelText . '。',
                    ];
                }
            }
            $workbookStatus = (string)($row['workbook_status'] ?? '');
            if (!in_array($workbookStatus, ['pending', 'ready_for_return_preview'], true)) {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'governance_closure_pilot_operator_workbook_status_invalid',
                    'message' => $label . ' workbook_status 不合法。',
                ];
            }
            if ($workbookStatus === 'pending') {
                $pendingItems++;
                if ((string)($row['blocks_apply'] ?? '') !== 'yes') {
                    $findings[] = [
                        'severity' => 'high',
                        'id' => 'governance_closure_pilot_operator_workbook_pending_not_blocking',
                        'message' => $label . ' pending 时 blocks_apply 应为 yes。',
                    ];
                }
            }
        }

        $fieldIds = [];
        $pendingFields = 0;
        foreach ($fieldRows as $index => $row) {
            $line = $index + 2;
            $fieldId = trim((string)($row['field_task_id'] ?? ''));
            $label = $fieldId !== '' ? $fieldId : '字段清单第 ' . $line . ' 行';
            if ($fieldId === '') {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'governance_closure_pilot_operator_workbook_blank_field_task_id',
                    'message' => '逐字段填写清单 field_task_id 为空。',
                ];
            } elseif (isset($fieldIds[$fieldId])) {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'governance_closure_pilot_operator_workbook_duplicate_field_task_id',
                    'message' => '逐字段填写清单 field_task_id 重复：' . $fieldId,
                ];
            }
            if ($fieldId !== '') {
                $fieldIds[$fieldId] = true;
            }
            $workbookItemId = trim((string)($row['workbook_item_id'] ?? ''));
            if ($workbookItemId === '' || !isset($itemIds[$workbookItemId])) {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'governance_closure_pilot_operator_workbook_field_unknown_item',
                    'message' => $label . ' 指向不存在的 workbook_item_id。',
                ];
            }
            foreach (['not_imported' => 'not_imported=yes', 'not_real_record' => 'not_real_record=yes'] as $field => $labelText) {
                if ((string)($row[$field] ?? '') !== 'yes') {
                    $findings[] = [
                        'severity' => 'high',
                        'id' => 'governance_closure_pilot_operator_workbook_field_marker_missing',
                        'message' => $label . ' 必须保留 ' . $labelText . '。',
                    ];
                }
            }
            $fieldStatus = (string)($row['field_status'] ?? '');
            if (!in_array($fieldStatus, ['pending', 'completed'], true)) {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'governance_closure_pilot_operator_workbook_field_status_invalid',
                    'message' => $label . ' field_status 不合法。',
                ];
            }
            if ($fieldStatus === 'pending') {
                $pendingFields++;
                if ((string)($row['blocks_apply'] ?? '') !== 'yes') {
                    $findings[] = [
                        'severity' => 'high',
                        'id' => 'governance_closure_pilot_operator_workbook_field_pending_not_blocking',
                        'message' => $label . ' pending 时 blocks_apply 应为 yes。',
                    ];
                }
            }
        }

        $pendingHandoffs = 0;
        $handoffItemIds = [];
        foreach ($handoffRows as $index => $row) {
            $line = $index + 2;
            $handoffId = trim((string)($row['pilot_handoff_id'] ?? ''));
            $label = $handoffId !== '' ? $handoffId : '签核交接第 ' . $line . ' 行';
            $workbookItemId = trim((string)($row['workbook_item_id'] ?? ''));
            if ($workbookItemId !== '') {
                $handoffItemIds[$workbookItemId] = true;
            }
            if ($workbookItemId === '' || !isset($itemIds[$workbookItemId])) {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'governance_closure_pilot_operator_workbook_handoff_unknown_item',
                    'message' => $label . ' 指向不存在的 workbook_item_id。',
                ];
            }
            foreach (['not_imported' => 'not_imported=yes', 'not_real_record' => 'not_real_record=yes'] as $field => $labelText) {
                if ((string)($row[$field] ?? '') !== 'yes') {
                    $findings[] = [
                        'severity' => 'high',
                        'id' => 'governance_closure_pilot_operator_workbook_handoff_marker_missing',
                        'message' => $label . ' 必须保留 ' . $labelText . '。',
                    ];
                }
            }
            $signatureStatus = (string)($row['signature_status'] ?? '');
            if (!in_array($signatureStatus, ['pending', 'completed', 'rejected'], true)) {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'governance_closure_pilot_operator_workbook_signature_status_invalid',
                    'message' => $label . ' signature_status 不合法。',
                ];
            }
            $handoffStatus = (string)($row['handoff_status'] ?? '');
            if (!in_array($handoffStatus, ['pending', 'completed', 'rejected'], true)) {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'governance_closure_pilot_operator_workbook_handoff_status_invalid',
                    'message' => $label . ' handoff_status 不合法。',
                ];
            }
            if ((string)($row['workbook_status'] ?? '') !== 'completed') {
                $pendingHandoffs++;
                if ((string)($row['blocks_apply'] ?? '') !== 'yes') {
                    $findings[] = [
                        'severity' => 'high',
                        'id' => 'governance_closure_pilot_operator_workbook_handoff_pending_not_blocking',
                        'message' => $label . ' 未完成时 blocks_apply 应为 yes。',
                    ];
                }
            }
        }

        $handoffItemKeys = array_keys($handoffItemIds);
        $itemKeys = array_keys($itemIds);
        sort($handoffItemKeys);
        sort($itemKeys);
        if ($handoffItemKeys !== [] && $handoffItemKeys !== $itemKeys) {
            $findings[] = [
                'severity' => 'high',
                'id' => 'governance_closure_pilot_operator_workbook_handoff_item_set_mismatch',
                'message' => '签核交接核对表未覆盖全部试点主任务。',
            ];
        }

        $manifestCounts = (array)($manifest['counts'] ?? []);
        $actualCounts = [
            'pilot_items' => count($masterRows),
            'field_fill_items' => count($fieldRows),
            'handoff_check_items' => count($handoffRows),
            'task_cards' => count($taskCards),
            'pending_workbook_items' => $pendingItems,
            'pending_field_items' => $pendingFields,
            'pending_handoff_items' => $pendingHandoffs,
            'source_missing_fields' => (int)($manifestCounts['source_missing_fields'] ?? 0),
            'source_blocked_patches' => (int)($manifestCounts['source_blocked_patches'] ?? 0),
            'source_workbench_modified' => (int)($manifestCounts['source_workbench_modified'] ?? 0),
            'database_write_performed' => (int)($manifestCounts['database_write_performed'] ?? 0),
        ];
        foreach ($actualCounts as $key => $actual) {
            if (!array_key_exists($key, $manifestCounts)) {
                continue;
            }
            if ((int)$manifestCounts[$key] !== $actual) {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'governance_closure_pilot_operator_workbook_count_mismatch_' . $key,
                    'message' => '治理关闭试点人工执行工作簿 ' . $key . ' 计数不一致：manifest=' . (string)$manifestCounts[$key] . '，actual=' . (string)$actual,
                ];
            }
        }
        if ($actualCounts['source_workbench_modified'] !== 0) {
            $findings[] = [
                'severity' => 'high',
                'id' => 'governance_closure_pilot_operator_workbook_source_modified_flagged',
                'message' => '治理关闭试点人工执行工作簿 source_workbench_modified 必须为 0。',
            ];
        }
        if ($actualCounts['database_write_performed'] !== 0) {
            $findings[] = [
                'severity' => 'high',
                'id' => 'governance_closure_pilot_operator_workbook_database_write_flagged',
                'message' => '治理关闭试点人工执行工作簿 database_write_performed 必须为 0。',
            ];
        }
        if (($pendingItems > 0 || $pendingFields > 0 || $pendingHandoffs > 0) && $readyForReturnPreview !== 'no') {
            $findings[] = [
                'severity' => 'high',
                'id' => 'governance_closure_pilot_operator_workbook_ready_conflicts_with_pending',
                'message' => '仍有 pending 项时 ready_for_pilot_return_preview 必须为 no。',
            ];
        }
        if ($readyForReturnPreview !== 'yes' && $readyForSourceUpdate === 'yes') {
            $findings[] = [
                'severity' => 'high',
                'id' => 'governance_closure_pilot_operator_workbook_source_update_conflicts_with_return',
                'message' => '工作簿未 ready 时 ready_for_source_workbench_update 必须为 no。',
            ];
        }
        if ($readyForApply === 'yes') {
            $findings[] = [
                'severity' => 'high',
                'id' => 'governance_closure_pilot_operator_workbook_cannot_authorize_lims_apply',
                'message' => '治理关闭试点人工执行工作簿不能单独声明 ready_for_lims_apply=yes。',
            ];
        }

        foreach (['overview', 'rerun', 'readme'] as $key) {
            $path = (string)($paths[$key] ?? '');
            if (!is_file($path)) {
                continue;
            }
            $text = (string)file_get_contents($path);
            foreach (['不写数据库', '不代表人工评审通过', '不写入质量手册正文'] as $marker) {
                if (!str_contains($text, $marker)) {
                    $findings[] = [
                        'severity' => 'high',
                        'id' => 'governance_closure_pilot_operator_workbook_doc_missing_guardrail',
                        'message' => basename($path) . ' 缺少边界标识：' . $marker,
                    ];
                }
            }
        }

        return [
            'governance_closure_pilot_operator_workbook_dir' => $workbookDir,
            'status' => self::hasHighFinding($findings) ? 'invalid' : 'passed',
            'manifest_status' => $manifestStatus,
            'readiness' => $readiness,
            'ready_for_pilot_return_preview' => $readyForReturnPreview,
            'ready_for_source_workbench_update' => $readyForSourceUpdate,
            'ready_for_lims_apply' => $readyForApply,
            'pilot_items' => $actualCounts['pilot_items'],
            'field_fill_items' => $actualCounts['field_fill_items'],
            'handoff_check_items' => $actualCounts['handoff_check_items'],
            'task_cards' => $actualCounts['task_cards'],
            'pending_workbook_items' => $actualCounts['pending_workbook_items'],
            'pending_field_items' => $actualCounts['pending_field_items'],
            'pending_handoff_items' => $actualCounts['pending_handoff_items'],
            'source_missing_fields' => $actualCounts['source_missing_fields'],
            'source_blocked_patches' => $actualCounts['source_blocked_patches'],
            'source_workbench_modified' => $actualCounts['source_workbench_modified'],
            'database_write_performed' => $actualCounts['database_write_performed'],
            'findings' => $findings,
        ];
    }

    private static function containsGovernanceClosurePilotOperatorHandbackForbiddenMarker(string $value): bool
    {
        $upperValue = strtoupper($value);
        foreach (self::GOVERNANCE_CLOSURE_PILOT_OPERATOR_HANDBACK_FORBIDDEN_MARKERS as $marker) {
            if (str_contains($upperValue, strtoupper($marker))) {
                return true;
            }
        }
        return false;
    }

    private static function inspectGovernanceClosurePilotOperatorHandback(string $handbackDir): array
    {
        $findings = [];
        $handbackDir = rtrim($handbackDir, '/\\');
        $manifestPath = $handbackDir . DIRECTORY_SEPARATOR . self::REQUIRED_GOVERNANCE_CLOSURE_PILOT_OPERATOR_HANDBACK_FILES['manifest'];
        $manifest = [];
        $manifestStatus = 'missing';
        if (!is_file($manifestPath)) {
            $findings[] = [
                'severity' => 'high',
                'id' => 'missing_governance_closure_pilot_operator_handback_manifest',
                'message' => '治理关闭试点真实执行交回包缺少 manifest。',
            ];
        } else {
            $manifest = json_decode((string)file_get_contents($manifestPath), true) ?: [];
            $manifestStatus = (string)($manifest['status'] ?? '');
            if ($manifestStatus !== 'governance_closure_pilot_operator_handback_no_write') {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'invalid_governance_closure_pilot_operator_handback_manifest_status',
                    'message' => '治理关闭试点真实执行交回包 manifest 状态不符合预期。',
                ];
            }
            $guardrailText = implode("\n", array_map(static fn($value): string => (string)$value, (array)($manifest['guardrails'] ?? [])));
            foreach (self::GOVERNANCE_CLOSURE_PILOT_OPERATOR_HANDBACK_REQUIRED_GUARDRAILS as $marker) {
                if (!str_contains($guardrailText, $marker)) {
                    $findings[] = [
                        'severity' => 'high',
                        'id' => 'governance_closure_pilot_operator_handback_manifest_missing_guardrail',
                        'message' => '治理关闭试点真实执行交回包 manifest 缺少边界标识：' . $marker,
                    ];
                }
            }
        }

        $files = (array)($manifest['files'] ?? []);
        $paths = [];
        foreach (self::REQUIRED_GOVERNANCE_CLOSURE_PILOT_OPERATOR_HANDBACK_FILES as $key => $defaultFilename) {
            $filename = (string)($files[$key] ?? $defaultFilename);
            $path = $handbackDir . DIRECTORY_SEPARATOR . $filename;
            $paths[$key] = $path;
            if ($key === 'task_card_dir') {
                if (!is_dir($path)) {
                    $findings[] = [
                        'severity' => 'high',
                        'id' => 'missing_governance_closure_pilot_operator_handback_task_card_dir',
                        'message' => '治理关闭试点真实执行交回包缺少 task_cards 目录。',
                    ];
                }
                continue;
            }
            if (!is_file($path)) {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'missing_governance_closure_pilot_operator_handback_' . $key,
                    'message' => '治理关闭试点真实执行交回包缺少文件：' . $filename,
                ];
            }
        }

        foreach (self::forbiddenDatabaseArtifacts($handbackDir) as $path) {
            $findings[] = [
                'severity' => 'high',
                'id' => 'governance_closure_pilot_operator_handback_forbidden_database_artifact',
                'message' => '治理关闭试点真实执行交回包不应包含数据库/SQL 文件：' . basename((string)$path),
            ];
        }

        $masterRows = is_file($paths['master'] ?? '') ? self::readCsv($paths['master']) : [];
        $fieldRows = is_file($paths['field_checklist'] ?? '') ? self::readCsv($paths['field_checklist']) : [];
        $handoffRows = is_file($paths['handoff_checklist'] ?? '') ? self::readCsv($paths['handoff_checklist']) : [];
        $taskCards = is_dir($paths['task_card_dir'] ?? '') ? (glob($paths['task_card_dir'] . DIRECTORY_SEPARATOR . '*.md') ?: []) : [];

        $itemIds = [];
        $pendingItems = 0;
        foreach ($masterRows as $index => $row) {
            $line = $index + 2;
            $itemId = trim((string)($row['workbook_item_id'] ?? ''));
            $label = (string)($row['handback_item_id'] ?? ($itemId !== '' ? $itemId : '真实执行交回主清单第 ' . $line . ' 行'));
            if ($itemId === '') {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'governance_closure_pilot_operator_handback_blank_item_id',
                    'message' => '真实执行交回主清单 workbook_item_id 为空。',
                ];
            } elseif (isset($itemIds[$itemId])) {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'governance_closure_pilot_operator_handback_duplicate_item_id',
                    'message' => '真实执行交回主清单 workbook_item_id 重复：' . $itemId,
                ];
            }
            if ($itemId !== '') {
                $itemIds[$itemId] = true;
            }
            foreach (['real_execution_required', 'not_imported', 'not_lims_record_yet'] as $field) {
                if ((string)($row[$field] ?? '') !== 'yes') {
                    $findings[] = [
                        'severity' => 'high',
                        'id' => 'governance_closure_pilot_operator_handback_marker_missing',
                        'message' => $label . ' 必须保留 ' . $field . '=yes。',
                    ];
                }
            }
            foreach (['evidence_status', 'signature_status', 'handoff_status', 'handback_status'] as $field) {
                if (!in_array((string)($row[$field] ?? ''), ['pending', 'completed', 'rejected'], true)) {
                    $findings[] = [
                        'severity' => 'high',
                        'id' => 'governance_closure_pilot_operator_handback_master_status_invalid',
                        'message' => $label . ' ' . $field . ' 不合法。',
                    ];
                }
            }
            $isCompleted = true;
            foreach (['evidence_status', 'signature_status', 'handoff_status', 'handback_status'] as $field) {
                if ((string)($row[$field] ?? '') !== 'completed') {
                    $isCompleted = false;
                }
            }
            if (!$isCompleted) {
                $pendingItems++;
                if ((string)($row['blocks_apply'] ?? '') !== 'yes') {
                    $findings[] = [
                        'severity' => 'high',
                        'id' => 'governance_closure_pilot_operator_handback_pending_not_blocking',
                        'message' => $label . ' 未完成时 blocks_apply 应为 yes。',
                    ];
                }
            }
        }

        $fieldIds = [];
        $pendingFields = 0;
        $completedFields = 0;
        foreach ($fieldRows as $index => $row) {
            $line = $index + 2;
            $fieldId = trim((string)($row['field_task_id'] ?? ''));
            $label = (string)($row['handback_field_id'] ?? ($fieldId !== '' ? $fieldId : '真实逐字段交回清单第 ' . $line . ' 行'));
            if ($fieldId === '') {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'governance_closure_pilot_operator_handback_blank_field_id',
                    'message' => '真实逐字段交回清单 field_task_id 为空。',
                ];
            } elseif (isset($fieldIds[$fieldId])) {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'governance_closure_pilot_operator_handback_duplicate_field_id',
                    'message' => '真实逐字段交回清单 field_task_id 重复：' . $fieldId,
                ];
            }
            if ($fieldId !== '') {
                $fieldIds[$fieldId] = true;
            }
            $workbookItemId = trim((string)($row['workbook_item_id'] ?? ''));
            if ($workbookItemId === '' || !isset($itemIds[$workbookItemId])) {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'governance_closure_pilot_operator_handback_field_unknown_item',
                    'message' => $label . ' 指向不存在的 workbook_item_id。',
                ];
            }
            foreach (['real_execution_required', 'not_imported', 'not_lims_record_yet'] as $field) {
                if ((string)($row[$field] ?? '') !== 'yes') {
                    $findings[] = [
                        'severity' => 'high',
                        'id' => 'governance_closure_pilot_operator_handback_field_marker_missing',
                        'message' => $label . ' 必须保留 ' . $field . '=yes。',
                    ];
                }
            }
            $fieldStatus = (string)($row['field_status'] ?? '');
            if (!in_array($fieldStatus, ['pending', 'completed', 'rejected'], true)) {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'governance_closure_pilot_operator_handback_field_status_invalid',
                    'message' => $label . ' field_status 不合法。',
                ];
            }
            $realValue = trim((string)($row['real_input_value'] ?? ''));
            if (self::containsGovernanceClosurePilotOperatorHandbackForbiddenMarker($realValue)) {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'governance_closure_pilot_operator_handback_field_contains_simulation_marker',
                    'message' => $label . ' real_input_value 含模拟标识，不能作为真实交回。',
                ];
            }
            if ($fieldStatus === 'completed') {
                $completedFields++;
                if ($realValue === '') {
                    $findings[] = [
                        'severity' => 'high',
                        'id' => 'governance_closure_pilot_operator_handback_field_completed_without_real_value',
                        'message' => $label . ' 标为 completed 但 real_input_value 为空。',
                    ];
                }
                if ((string)($row['blocks_apply'] ?? '') !== 'no') {
                    $findings[] = [
                        'severity' => 'high',
                        'id' => 'governance_closure_pilot_operator_handback_field_completed_still_blocking',
                        'message' => $label . ' completed 时 blocks_apply 应为 no。',
                    ];
                }
            } else {
                $pendingFields++;
                if ((string)($row['blocks_apply'] ?? '') !== 'yes') {
                    $findings[] = [
                        'severity' => 'high',
                        'id' => 'governance_closure_pilot_operator_handback_field_pending_not_blocking',
                        'message' => $label . ' 未完成时 blocks_apply 应为 yes。',
                    ];
                }
            }
        }

        $handoffItemIds = [];
        $pendingHandoffs = 0;
        $completedHandoffs = 0;
        foreach ($handoffRows as $index => $row) {
            $line = $index + 2;
            $workbookItemId = trim((string)($row['workbook_item_id'] ?? ''));
            $label = (string)($row['handback_handoff_id'] ?? ((string)($row['pilot_handoff_id'] ?? '') !== '' ? (string)$row['pilot_handoff_id'] : '真实签核交接交回表第 ' . $line . ' 行'));
            if ($workbookItemId !== '') {
                $handoffItemIds[$workbookItemId] = true;
            }
            if ($workbookItemId === '' || !isset($itemIds[$workbookItemId])) {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'governance_closure_pilot_operator_handback_handoff_unknown_item',
                    'message' => $label . ' 指向不存在的 workbook_item_id。',
                ];
            }
            foreach (['real_execution_required', 'not_imported', 'not_lims_record_yet'] as $field) {
                if ((string)($row[$field] ?? '') !== 'yes') {
                    $findings[] = [
                        'severity' => 'high',
                        'id' => 'governance_closure_pilot_operator_handback_handoff_marker_missing',
                        'message' => $label . ' 必须保留 ' . $field . '=yes。',
                    ];
                }
            }
            foreach (['signature_status', 'handoff_status', 'handback_status'] as $field) {
                if (!in_array((string)($row[$field] ?? ''), ['pending', 'completed', 'rejected'], true)) {
                    $findings[] = [
                        'severity' => 'high',
                        'id' => 'governance_closure_pilot_operator_handback_handoff_status_invalid',
                        'message' => $label . ' ' . $field . ' 不合法。',
                    ];
                }
            }
            foreach (['assigned_person', 'reviewer', 'actual_finish_date'] as $field) {
                if (self::containsGovernanceClosurePilotOperatorHandbackForbiddenMarker((string)($row[$field] ?? ''))) {
                    $findings[] = [
                        'severity' => 'high',
                        'id' => 'governance_closure_pilot_operator_handback_handoff_contains_simulation_marker',
                        'message' => $label . ' ' . $field . ' 含模拟标识，不能作为真实交回。',
                    ];
                }
            }
            $handoffCompleted = true;
            foreach (['signature_status', 'handoff_status', 'handback_status'] as $field) {
                if ((string)($row[$field] ?? '') !== 'completed') {
                    $handoffCompleted = false;
                }
            }
            if ($handoffCompleted) {
                $completedHandoffs++;
                foreach (['assigned_person', 'reviewer', 'actual_finish_date'] as $field) {
                    if (trim((string)($row[$field] ?? '')) === '') {
                        $findings[] = [
                            'severity' => 'high',
                            'id' => 'governance_closure_pilot_operator_handback_handoff_completed_without_required_value',
                            'message' => $label . ' 标为 completed 但 ' . $field . ' 为空。',
                        ];
                    }
                }
                if ((string)($row['blocks_apply'] ?? '') !== 'no') {
                    $findings[] = [
                        'severity' => 'high',
                        'id' => 'governance_closure_pilot_operator_handback_handoff_completed_still_blocking',
                        'message' => $label . ' completed 时 blocks_apply 应为 no。',
                    ];
                }
            } else {
                $pendingHandoffs++;
                if ((string)($row['blocks_apply'] ?? '') !== 'yes') {
                    $findings[] = [
                        'severity' => 'high',
                        'id' => 'governance_closure_pilot_operator_handback_handoff_pending_not_blocking',
                        'message' => $label . ' 未完成时 blocks_apply 应为 yes。',
                    ];
                }
            }
        }

        $handoffItemKeys = array_keys($handoffItemIds);
        $itemKeys = array_keys($itemIds);
        sort($handoffItemKeys);
        sort($itemKeys);
        if ($handoffItemKeys !== [] && $handoffItemKeys !== $itemKeys) {
            $findings[] = [
                'severity' => 'high',
                'id' => 'governance_closure_pilot_operator_handback_handoff_item_set_mismatch',
                'message' => '真实签核交接交回表未覆盖全部试点主任务。',
            ];
        }

        $manifestCounts = (array)($manifest['counts'] ?? []);
        $actualCounts = [
            'pilot_items' => count($masterRows),
            'field_fill_items' => count($fieldRows),
            'handoff_check_items' => count($handoffRows),
            'task_cards' => count($taskCards),
            'pending_workbook_items' => $pendingItems,
            'pending_field_items' => $pendingFields,
            'pending_handoff_items' => $pendingHandoffs,
            'completed_field_items' => $completedFields,
            'completed_handoff_items' => $completedHandoffs,
            'source_workbench_modified' => (int)($manifestCounts['source_workbench_modified'] ?? 0),
            'database_write_performed' => (int)($manifestCounts['database_write_performed'] ?? 0),
        ];
        foreach (['pilot_items', 'field_fill_items', 'handoff_check_items', 'task_cards'] as $key) {
            if (array_key_exists($key, $manifestCounts) && (int)$manifestCounts[$key] !== $actualCounts[$key]) {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'governance_closure_pilot_operator_handback_count_mismatch_' . $key,
                    'message' => '治理关闭试点真实执行交回包 ' . $key . ' 计数不一致：manifest=' . (string)$manifestCounts[$key] . '，actual=' . (string)$actualCounts[$key],
                ];
            }
        }
        if ($actualCounts['source_workbench_modified'] !== 0) {
            $findings[] = [
                'severity' => 'high',
                'id' => 'governance_closure_pilot_operator_handback_source_modified_flagged',
                'message' => '治理关闭试点真实执行交回包 source_workbench_modified 必须为 0。',
            ];
        }
        if ($actualCounts['database_write_performed'] !== 0) {
            $findings[] = [
                'severity' => 'high',
                'id' => 'governance_closure_pilot_operator_handback_database_write_flagged',
                'message' => '治理关闭试点真实执行交回包 database_write_performed 必须为 0。',
            ];
        }

        $readyForReturnPreview = (!self::hasHighFinding($findings) && $pendingItems === 0 && $pendingFields === 0 && $pendingHandoffs === 0) ? 'yes' : 'no';
        $readiness = $readyForReturnPreview === 'yes' ? 'operator_handback_ready_for_return_preview' : 'operator_handback_pending_real_execution';

        foreach (['overview', 'acceptance', 'readme'] as $key) {
            $path = (string)($paths[$key] ?? '');
            if (!is_file($path)) {
                continue;
            }
            $text = (string)file_get_contents($path);
            foreach (['真实', '不写数据库', '不代表人工评审通过', '不写入质量手册正文'] as $marker) {
                if (!str_contains($text, $marker)) {
                    $findings[] = [
                        'severity' => 'high',
                        'id' => 'governance_closure_pilot_operator_handback_doc_missing_guardrail',
                        'message' => basename($path) . ' 缺少边界标识：' . $marker,
                    ];
                }
            }
        }

        return [
            'governance_closure_pilot_operator_handback_dir' => $handbackDir,
            'status' => self::hasHighFinding($findings) ? 'invalid' : 'passed',
            'manifest_status' => $manifestStatus,
            'readiness' => $readiness,
            'ready_for_pilot_return_preview' => $readyForReturnPreview,
            'ready_for_source_workbench_update' => 'no',
            'ready_for_lims_apply' => 'no',
            'pilot_items' => $actualCounts['pilot_items'],
            'field_fill_items' => $actualCounts['field_fill_items'],
            'handoff_check_items' => $actualCounts['handoff_check_items'],
            'task_cards' => $actualCounts['task_cards'],
            'pending_workbook_items' => $actualCounts['pending_workbook_items'],
            'pending_field_items' => $actualCounts['pending_field_items'],
            'pending_handoff_items' => $actualCounts['pending_handoff_items'],
            'completed_field_items' => $actualCounts['completed_field_items'],
            'completed_handoff_items' => $actualCounts['completed_handoff_items'],
            'source_workbench_modified' => $actualCounts['source_workbench_modified'],
            'database_write_performed' => $actualCounts['database_write_performed'],
            'findings' => $findings,
        ];
    }

    private static function inspectGovernanceClosurePilotOperatorCompletionSimulation(string $simulationDir): array
    {
        $findings = [];
        $simulationDir = rtrim($simulationDir, '/\\');
        $manifestPath = $simulationDir . DIRECTORY_SEPARATOR . self::REQUIRED_GOVERNANCE_CLOSURE_PILOT_OPERATOR_COMPLETION_SIMULATION_FILES['manifest'];
        $manifest = [];
        $manifestStatus = 'missing';
        $readiness = '';
        $readyForReturnPreview = '';
        $readyForSourceUpdate = '';
        $readyForApply = '';
        $isSimulated = 0;
        if (!is_file($manifestPath)) {
            $findings[] = [
                'severity' => 'high',
                'id' => 'missing_governance_closure_pilot_operator_completion_simulation_manifest',
                'message' => '治理关闭试点人工执行模拟完成包缺少 manifest。',
            ];
        } else {
            $manifest = json_decode((string)file_get_contents($manifestPath), true) ?: [];
            $manifestStatus = (string)($manifest['status'] ?? '');
            $readiness = (string)($manifest['readiness'] ?? '');
            $readyForReturnPreview = (string)($manifest['ready_for_pilot_return_preview'] ?? '');
            $readyForSourceUpdate = (string)($manifest['ready_for_source_workbench_update'] ?? '');
            $readyForApply = (string)($manifest['ready_for_lims_apply'] ?? '');
            $isSimulated = (int)($manifest['is_simulated'] ?? 0);
            if ($manifestStatus !== 'governance_closure_pilot_operator_completion_simulation_no_write') {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'invalid_governance_closure_pilot_operator_completion_simulation_manifest_status',
                    'message' => '治理关闭试点人工执行模拟完成包 manifest 状态不符合预期。',
                ];
            }
            $guardrailText = implode("\n", array_map(static fn($value): string => (string)$value, (array)($manifest['guardrails'] ?? [])));
            foreach (self::GOVERNANCE_CLOSURE_PILOT_OPERATOR_COMPLETION_SIMULATION_REQUIRED_GUARDRAILS as $marker) {
                if (!str_contains($guardrailText, $marker)) {
                    $findings[] = [
                        'severity' => 'high',
                        'id' => 'governance_closure_pilot_operator_completion_simulation_manifest_missing_guardrail',
                        'message' => '治理关闭试点人工执行模拟完成包 manifest 缺少边界标识：' . $marker,
                    ];
                }
            }
        }

        $files = (array)($manifest['files'] ?? []);
        $paths = [];
        foreach (self::REQUIRED_GOVERNANCE_CLOSURE_PILOT_OPERATOR_COMPLETION_SIMULATION_FILES as $key => $defaultFilename) {
            $filename = (string)($files[$key] ?? $defaultFilename);
            $path = $simulationDir . DIRECTORY_SEPARATOR . $filename;
            $paths[$key] = $path;
            if ($key === 'task_card_dir') {
                if (!is_dir($path)) {
                    $findings[] = [
                        'severity' => 'high',
                        'id' => 'missing_governance_closure_pilot_operator_completion_simulation_task_card_dir',
                        'message' => '治理关闭试点人工执行模拟完成包缺少 task_cards 目录。',
                    ];
                }
                continue;
            }
            if (!is_file($path)) {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'missing_governance_closure_pilot_operator_completion_simulation_' . $key,
                    'message' => '治理关闭试点人工执行模拟完成包缺少文件：' . $filename,
                ];
            }
        }

        foreach (self::forbiddenDatabaseArtifacts($simulationDir) as $path) {
            $findings[] = [
                'severity' => 'high',
                'id' => 'governance_closure_pilot_operator_completion_simulation_forbidden_database_artifact',
                'message' => '治理关闭试点人工执行模拟完成包不应包含数据库/SQL 文件：' . basename((string)$path),
            ];
        }

        $masterRows = is_file($paths['master'] ?? '') ? self::readCsv($paths['master']) : [];
        $fieldRows = is_file($paths['field_checklist'] ?? '') ? self::readCsv($paths['field_checklist']) : [];
        $handoffRows = is_file($paths['handoff_checklist'] ?? '') ? self::readCsv($paths['handoff_checklist']) : [];
        $taskCards = is_dir($paths['task_card_dir'] ?? '') ? (glob($paths['task_card_dir'] . DIRECTORY_SEPARATOR . '*.md') ?: []) : [];

        $itemIds = [];
        $pendingItems = 0;
        $simulationMarkerRows = 0;
        foreach ($masterRows as $index => $row) {
            $line = $index + 2;
            $itemId = trim((string)($row['workbook_item_id'] ?? ''));
            $label = (string)($row['simulation_item_id'] ?? ($itemId !== '' ? $itemId : '模拟完成主清单第 ' . $line . ' 行'));
            if ($itemId === '') {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'governance_closure_pilot_operator_completion_simulation_blank_item_id',
                    'message' => '模拟完成主清单 workbook_item_id 为空。',
                ];
            } elseif (isset($itemIds[$itemId])) {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'governance_closure_pilot_operator_completion_simulation_duplicate_item_id',
                    'message' => '模拟完成主清单 workbook_item_id 重复：' . $itemId,
                ];
            }
            if ($itemId !== '') {
                $itemIds[$itemId] = true;
            }
            if (
                (string)($row['not_imported'] ?? '') === 'yes'
                && (string)($row['not_real_record'] ?? '') === 'yes'
                && (string)($row['simulated_completion'] ?? '') === 'yes'
                && (string)($row['simulation_marker'] ?? '') === self::GOVERNANCE_CLOSURE_PILOT_OPERATOR_COMPLETION_SIMULATION_MARKER
            ) {
                $simulationMarkerRows++;
            } else {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'governance_closure_pilot_operator_completion_simulation_marker_missing',
                    'message' => $label . ' 必须保留模拟完成和非真实记录标识。',
                ];
            }
            foreach (['evidence_status', 'signature_status', 'handoff_status'] as $field) {
                if ((string)($row[$field] ?? '') !== 'completed') {
                    $findings[] = [
                        'severity' => 'high',
                        'id' => 'governance_closure_pilot_operator_completion_simulation_master_status_invalid',
                        'message' => $label . ' ' . $field . ' 应为 completed。',
                    ];
                }
            }
            if ((string)($row['workbook_status'] ?? '') !== 'ready_for_return_preview') {
                $pendingItems++;
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'governance_closure_pilot_operator_completion_simulation_master_not_ready',
                    'message' => $label . ' workbook_status 应为 ready_for_return_preview。',
                ];
            }
            if ((string)($row['blocks_apply'] ?? '') !== 'no') {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'governance_closure_pilot_operator_completion_simulation_master_blocks_apply_invalid',
                    'message' => $label . ' blocks_apply 应为 no。',
                ];
            }
        }

        $fieldIds = [];
        $pendingFields = 0;
        foreach ($fieldRows as $index => $row) {
            $line = $index + 2;
            $fieldId = trim((string)($row['field_task_id'] ?? ''));
            $label = (string)($row['simulation_field_id'] ?? ($fieldId !== '' ? $fieldId : '模拟逐字段完成清单第 ' . $line . ' 行'));
            if ($fieldId === '') {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'governance_closure_pilot_operator_completion_simulation_blank_field_id',
                    'message' => '模拟逐字段完成清单 field_task_id 为空。',
                ];
            } elseif (isset($fieldIds[$fieldId])) {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'governance_closure_pilot_operator_completion_simulation_duplicate_field_id',
                    'message' => '模拟逐字段完成清单 field_task_id 重复：' . $fieldId,
                ];
            }
            if ($fieldId !== '') {
                $fieldIds[$fieldId] = true;
            }
            $workbookItemId = trim((string)($row['workbook_item_id'] ?? ''));
            if ($workbookItemId === '' || !isset($itemIds[$workbookItemId])) {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'governance_closure_pilot_operator_completion_simulation_field_unknown_item',
                    'message' => $label . ' 指向不存在的 workbook_item_id。',
                ];
            }
            if (
                (string)($row['not_imported'] ?? '') === 'yes'
                && (string)($row['not_real_record'] ?? '') === 'yes'
                && (string)($row['simulated_completion'] ?? '') === 'yes'
                && (string)($row['simulation_marker'] ?? '') === self::GOVERNANCE_CLOSURE_PILOT_OPERATOR_COMPLETION_SIMULATION_MARKER
            ) {
                $simulationMarkerRows++;
            } else {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'governance_closure_pilot_operator_completion_simulation_field_marker_missing',
                    'message' => $label . ' 必须保留模拟完成和非真实记录标识。',
                ];
            }
            if ((string)($row['field_status'] ?? '') !== 'completed') {
                $pendingFields++;
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'governance_closure_pilot_operator_completion_simulation_field_not_completed',
                    'message' => $label . ' field_status 应为 completed。',
                ];
            }
            if ((string)($row['blocks_apply'] ?? '') !== 'no') {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'governance_closure_pilot_operator_completion_simulation_field_blocks_apply_invalid',
                    'message' => $label . ' blocks_apply 应为 no。',
                ];
            }
            if (trim((string)($row['simulated_input_value'] ?? '')) === '') {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'governance_closure_pilot_operator_completion_simulation_field_value_blank',
                    'message' => $label . ' simulated_input_value 为空。',
                ];
            }
        }

        $pendingHandoffs = 0;
        $handoffItemIds = [];
        foreach ($handoffRows as $index => $row) {
            $line = $index + 2;
            $workbookItemId = trim((string)($row['workbook_item_id'] ?? ''));
            $label = (string)($row['simulation_handoff_id'] ?? ((string)($row['pilot_handoff_id'] ?? '') !== '' ? (string)$row['pilot_handoff_id'] : '模拟签核交接完成表第 ' . $line . ' 行'));
            if ($workbookItemId !== '') {
                $handoffItemIds[$workbookItemId] = true;
            }
            if ($workbookItemId === '' || !isset($itemIds[$workbookItemId])) {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'governance_closure_pilot_operator_completion_simulation_handoff_unknown_item',
                    'message' => $label . ' 指向不存在的 workbook_item_id。',
                ];
            }
            if (
                (string)($row['not_imported'] ?? '') === 'yes'
                && (string)($row['not_real_record'] ?? '') === 'yes'
                && (string)($row['simulated_completion'] ?? '') === 'yes'
                && (string)($row['simulation_marker'] ?? '') === self::GOVERNANCE_CLOSURE_PILOT_OPERATOR_COMPLETION_SIMULATION_MARKER
            ) {
                $simulationMarkerRows++;
            } else {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'governance_closure_pilot_operator_completion_simulation_handoff_marker_missing',
                    'message' => $label . ' 必须保留模拟完成和非真实记录标识。',
                ];
            }
            $handoffPending = false;
            foreach (['signature_status', 'handoff_status', 'workbook_status'] as $field) {
                if ((string)($row[$field] ?? '') !== 'completed') {
                    $handoffPending = true;
                    $findings[] = [
                        'severity' => 'high',
                        'id' => 'governance_closure_pilot_operator_completion_simulation_handoff_status_invalid',
                        'message' => $label . ' ' . $field . ' 应为 completed。',
                    ];
                }
            }
            if ($handoffPending) {
                $pendingHandoffs++;
            }
            if ((string)($row['blocks_apply'] ?? '') !== 'no') {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'governance_closure_pilot_operator_completion_simulation_handoff_blocks_apply_invalid',
                    'message' => $label . ' blocks_apply 应为 no。',
                ];
            }
            if ((string)($row['assigned_person'] ?? '') !== 'SIMULATED_PERSON_NOT_REAL_EXECUTOR') {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'governance_closure_pilot_operator_completion_simulation_handoff_assignee_invalid',
                    'message' => $label . ' assigned_person 必须保留模拟执行人标识。',
                ];
            }
            if ((string)($row['reviewer'] ?? '') !== 'SIMULATED_REVIEWER_NOT_REAL_REVIEW') {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'governance_closure_pilot_operator_completion_simulation_handoff_reviewer_invalid',
                    'message' => $label . ' reviewer 必须保留模拟复核人标识。',
                ];
            }
        }

        $handoffItemKeys = array_keys($handoffItemIds);
        $itemKeys = array_keys($itemIds);
        sort($handoffItemKeys);
        sort($itemKeys);
        if ($handoffItemKeys !== [] && $handoffItemKeys !== $itemKeys) {
            $findings[] = [
                'severity' => 'high',
                'id' => 'governance_closure_pilot_operator_completion_simulation_handoff_item_set_mismatch',
                'message' => '模拟签核交接完成表未覆盖全部试点主任务。',
            ];
        }

        $manifestCounts = (array)($manifest['counts'] ?? []);
        $actualCounts = [
            'pilot_items' => count($masterRows),
            'field_fill_items' => count($fieldRows),
            'handoff_check_items' => count($handoffRows),
            'task_cards' => count($taskCards),
            'pending_workbook_items' => $pendingItems,
            'pending_field_items' => $pendingFields,
            'pending_handoff_items' => $pendingHandoffs,
            'simulated_completion_rows' => count($masterRows) + count($fieldRows) + count($handoffRows),
            'simulation_marker_rows' => $simulationMarkerRows,
            'source_missing_fields' => (int)($manifestCounts['source_missing_fields'] ?? 0),
            'source_blocked_patches' => (int)($manifestCounts['source_blocked_patches'] ?? 0),
            'source_workbench_modified' => (int)($manifestCounts['source_workbench_modified'] ?? 0),
            'database_write_performed' => (int)($manifestCounts['database_write_performed'] ?? 0),
        ];
        foreach ($actualCounts as $key => $actual) {
            if (!array_key_exists($key, $manifestCounts)) {
                continue;
            }
            if ((int)$manifestCounts[$key] !== $actual) {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'governance_closure_pilot_operator_completion_simulation_count_mismatch_' . $key,
                    'message' => '治理关闭试点人工执行模拟完成包 ' . $key . ' 计数不一致：manifest=' . (string)$manifestCounts[$key] . '，actual=' . (string)$actual,
                ];
            }
        }
        if ($actualCounts['source_workbench_modified'] !== 0) {
            $findings[] = [
                'severity' => 'high',
                'id' => 'governance_closure_pilot_operator_completion_simulation_source_modified_flagged',
                'message' => '治理关闭试点人工执行模拟完成包 source_workbench_modified 必须为 0。',
            ];
        }
        if ($actualCounts['database_write_performed'] !== 0) {
            $findings[] = [
                'severity' => 'high',
                'id' => 'governance_closure_pilot_operator_completion_simulation_database_write_flagged',
                'message' => '治理关闭试点人工执行模拟完成包 database_write_performed 必须为 0。',
            ];
        }
        if ($readiness !== 'operator_completion_simulation_ready_for_return_preview') {
            $findings[] = [
                'severity' => 'high',
                'id' => 'governance_closure_pilot_operator_completion_simulation_readiness_invalid',
                'message' => '治理关闭试点人工执行模拟完成包 readiness 不符合预期。',
            ];
        }
        if ($readyForReturnPreview !== 'yes') {
            $findings[] = [
                'severity' => 'high',
                'id' => 'governance_closure_pilot_operator_completion_simulation_not_ready_for_return',
                'message' => '治理关闭试点人工执行模拟完成包 ready_for_pilot_return_preview 应为 yes。',
            ];
        }
        if ($readyForSourceUpdate === 'yes') {
            $findings[] = [
                'severity' => 'high',
                'id' => 'governance_closure_pilot_operator_completion_simulation_source_update_conflict',
                'message' => '治理关闭试点人工执行模拟完成包不得声明 ready_for_source_workbench_update=yes。',
            ];
        }
        if ($readyForApply === 'yes') {
            $findings[] = [
                'severity' => 'high',
                'id' => 'governance_closure_pilot_operator_completion_simulation_cannot_authorize_lims_apply',
                'message' => '治理关闭试点人工执行模拟完成包不能单独声明 ready_for_lims_apply=yes。',
            ];
        }
        if ($isSimulated !== 1) {
            $findings[] = [
                'severity' => 'high',
                'id' => 'governance_closure_pilot_operator_completion_simulation_marker_flag_invalid',
                'message' => '治理关闭试点人工执行模拟完成包 is_simulated 必须为 1。',
            ];
        }

        foreach (['overview', 'rerun', 'readme'] as $key) {
            $path = (string)($paths[$key] ?? '');
            if (!is_file($path)) {
                continue;
            }
            $text = (string)file_get_contents($path);
            foreach (['模拟完成', '不写数据库', '不代表真实执行完成', '不写入质量手册正文'] as $marker) {
                if (!str_contains($text, $marker)) {
                    $findings[] = [
                        'severity' => 'high',
                        'id' => 'governance_closure_pilot_operator_completion_simulation_doc_missing_guardrail',
                        'message' => basename($path) . ' 缺少边界标识：' . $marker,
                    ];
                }
            }
        }

        return [
            'governance_closure_pilot_operator_completion_simulation_dir' => $simulationDir,
            'status' => self::hasHighFinding($findings) ? 'invalid' : 'passed',
            'manifest_status' => $manifestStatus,
            'readiness' => $readiness,
            'ready_for_pilot_return_preview' => $readyForReturnPreview,
            'ready_for_source_workbench_update' => $readyForSourceUpdate,
            'ready_for_lims_apply' => $readyForApply,
            'is_simulated' => $isSimulated,
            'pilot_items' => $actualCounts['pilot_items'],
            'field_fill_items' => $actualCounts['field_fill_items'],
            'handoff_check_items' => $actualCounts['handoff_check_items'],
            'task_cards' => $actualCounts['task_cards'],
            'pending_workbook_items' => $actualCounts['pending_workbook_items'],
            'pending_field_items' => $actualCounts['pending_field_items'],
            'pending_handoff_items' => $actualCounts['pending_handoff_items'],
            'simulated_completion_rows' => $actualCounts['simulated_completion_rows'],
            'simulation_marker_rows' => $actualCounts['simulation_marker_rows'],
            'source_missing_fields' => $actualCounts['source_missing_fields'],
            'source_blocked_patches' => $actualCounts['source_blocked_patches'],
            'source_workbench_modified' => $actualCounts['source_workbench_modified'],
            'database_write_performed' => $actualCounts['database_write_performed'],
            'findings' => $findings,
        ];
    }

    private static function inspectGovernanceClosureDecisionPreview(string $previewDir): array
    {
        $findings = [];
        $previewDir = rtrim($previewDir, '/\\');
        $manifestPath = $previewDir . DIRECTORY_SEPARATOR . self::REQUIRED_GOVERNANCE_CLOSURE_PREVIEW_FILES['manifest'];
        $manifest = [];
        $manifestStatus = 'missing';
        $readiness = '';
        $readyForRefresh = '';
        $readyForApply = '';
        if (!is_file($manifestPath)) {
            $findings[] = [
                'severity' => 'high',
                'id' => 'missing_governance_closure_preview_manifest',
                'message' => '治理关闭意见回填预览包缺少 governance_closure_decision_preview_manifest.json。',
            ];
        } else {
            $manifest = json_decode((string)file_get_contents($manifestPath), true) ?: [];
            $manifestStatus = (string)($manifest['status'] ?? '');
            $readiness = (string)($manifest['readiness'] ?? '');
            $readyForRefresh = (string)($manifest['ready_for_governance_readiness_refresh'] ?? '');
            $readyForApply = (string)($manifest['ready_for_lims_apply'] ?? '');
            if ($manifestStatus !== 'governance_closure_decision_preview_no_database_write') {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'invalid_governance_closure_preview_manifest_status',
                    'message' => '治理关闭意见回填预览包 manifest 状态不符合预期。',
                ];
            }
            $guardrailText = implode("\n", array_map(static fn($value): string => (string)$value, (array)($manifest['guardrails'] ?? [])));
            foreach (self::GOVERNANCE_CLOSURE_PREVIEW_REQUIRED_GUARDRAILS as $marker) {
                if (!str_contains($guardrailText, $marker)) {
                    $findings[] = [
                        'severity' => 'high',
                        'id' => 'governance_closure_preview_manifest_missing_guardrail',
                        'message' => '治理关闭意见回填预览包 manifest 缺少边界标识：' . $marker,
                    ];
                }
            }
        }

        $files = (array)($manifest['files'] ?? []);
        $paths = [];
        foreach (self::REQUIRED_GOVERNANCE_CLOSURE_PREVIEW_FILES as $key => $defaultFilename) {
            $filename = (string)($files[$key] ?? $defaultFilename);
            $path = $previewDir . DIRECTORY_SEPARATOR . $filename;
            $paths[$key] = $path;
            if (!is_file($path)) {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'missing_governance_closure_preview_' . $key,
                    'message' => '治理关闭意见回填预览包缺少文件：' . $filename,
                ];
            }
        }

        foreach (self::forbiddenDatabaseArtifacts($previewDir) as $path) {
            $findings[] = [
                'severity' => 'high',
                'id' => 'governance_closure_preview_forbidden_database_artifact',
                'message' => '治理关闭意见回填预览包不应包含数据库/SQL 文件：' . basename((string)$path),
            ];
        }

        $decisionRows = is_file($paths['decision_preview'] ?? '') ? self::readCsv($paths['decision_preview']) : [];
        $blockingRows = is_file($paths['blocking_items'] ?? '') ? self::readCsv($paths['blocking_items']) : [];
        $summaryRows = is_file($paths['gate_summary'] ?? '') ? self::readCsv($paths['gate_summary']) : [];

        $decisionIds = [];
        $expectedBlockingIds = [];
        $proposedClosures = 0;
        $notProposed = 0;
        $acceptedForPreview = 0;
        $invalidClosures = 0;
        $missingRequiredFields = 0;
        $blockingItems = 0;
        foreach ($decisionRows as $index => $row) {
            $line = $index + 2;
            $closureId = trim((string)($row['closure_item_id'] ?? ''));
            if ($closureId === '') {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'governance_closure_preview_blank_closure_item_id',
                    'message' => '拟关闭决策预览第 ' . $line . ' 行 closure_item_id 为空。',
                ];
            } elseif (isset($decisionIds[$closureId])) {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'governance_closure_preview_duplicate_closure_item_id',
                    'message' => '拟关闭决策预览存在重复 closure_item_id：' . $closureId,
                ];
            }
            if ($closureId !== '') {
                $decisionIds[$closureId] = true;
            }
            if ((string)($row['not_imported'] ?? '') !== 'yes') {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'governance_closure_preview_not_imported_marker_missing',
                    'message' => ($closureId ?: '拟关闭决策预览第 ' . $line . ' 行') . ' 必须保留 not_imported=yes。',
                ];
            }
            if (!in_array((string)($row['will_remain_blocking'] ?? ''), ['yes', 'no'], true)) {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'governance_closure_preview_blocking_flag_invalid',
                    'message' => ($closureId ?: '拟关闭决策预览第 ' . $line . ' 行') . ' will_remain_blocking 必须为 yes/no。',
                ];
            }
            if (trim((string)($row['proposed_closure_status'] ?? '')) !== '') {
                $proposedClosures++;
            }
            $previewResult = (string)($row['preview_result'] ?? '');
            if ($previewResult === 'not_proposed') {
                $notProposed++;
            } elseif ($previewResult === 'accepted_for_preview') {
                $acceptedForPreview++;
                $missingFields = [];
                foreach ([
                    'closure_evidence_reference',
                    'evidence_template_reference',
                    'evidence_owner',
                    'evidence_date',
                    'evidence_result',
                    'closure_comment',
                    'reviewer',
                    'review_date',
                ] as $field) {
                    if (trim((string)($row[$field] ?? '')) === '') {
                        $missingFields[] = $field;
                    }
                }
                if ($missingFields !== []) {
                    $findings[] = [
                        'severity' => 'high',
                        'id' => 'governance_closure_preview_accepted_missing_evidence',
                        'message' => ($closureId ?: '拟关闭决策预览第 ' . $line . ' 行') . ' 已接受但缺少字段：' . implode('、', $missingFields),
                    ];
                }
            } elseif (in_array($previewResult, ['invalid_closure_status', 'rejected_or_reopened'], true)) {
                $invalidClosures++;
            } elseif ($previewResult === 'missing_required_fields') {
                $missingRequiredFields++;
            } else {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'governance_closure_preview_unknown_result',
                    'message' => ($closureId ?: '拟关闭决策预览第 ' . $line . ' 行') . ' preview_result 未识别：' . $previewResult,
                ];
            }
            if ((string)($row['will_remain_blocking'] ?? '') === 'yes') {
                $blockingItems++;
                if ($closureId !== '') {
                    $expectedBlockingIds[$closureId] = true;
                }
            }
        }

        $actualBlockingIds = [];
        foreach ($blockingRows as $row) {
            $closureId = trim((string)($row['closure_item_id'] ?? ''));
            if ($closureId !== '') {
                $actualBlockingIds[$closureId] = true;
            }
        }
        if (array_keys($expectedBlockingIds) !== array_keys($actualBlockingIds)) {
            ksort($expectedBlockingIds);
            ksort($actualBlockingIds);
            if (array_keys($expectedBlockingIds) !== array_keys($actualBlockingIds)) {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'governance_closure_preview_blocking_register_mismatch',
                    'message' => '仍阻断关闭项清单与拟关闭决策预览中的 will_remain_blocking=yes 不一致。',
                ];
            }
        }

        $summaryBlocking = 0;
        foreach ($summaryRows as $row) {
            $summaryBlocking += (int)($row['blocking_items'] ?? 0);
        }
        if ($summaryRows !== [] && $summaryBlocking !== $blockingItems) {
            $findings[] = [
                'severity' => 'high',
                'id' => 'governance_closure_preview_summary_blocking_mismatch',
                'message' => '按闸门统计 blocking_items=' . (string)$summaryBlocking . '，实际 ' . (string)$blockingItems . '。',
            ];
        }

        $manifestCounts = (array)($manifest['counts'] ?? []);
        $actualCounts = [
            'decision_items' => count($decisionRows),
            'proposed_closures' => $proposedClosures,
            'not_proposed' => $notProposed,
            'accepted_for_preview' => $acceptedForPreview,
            'invalid_closures' => $invalidClosures,
            'missing_required_fields' => $missingRequiredFields,
            'blocking_items' => $blockingItems,
            'database_write_performed' => (int)($manifestCounts['database_write_performed'] ?? 0),
        ];
        foreach ($actualCounts as $key => $actual) {
            if (!array_key_exists($key, $manifestCounts)) {
                continue;
            }
            if ((int)$manifestCounts[$key] !== $actual) {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'governance_closure_preview_count_mismatch_' . $key,
                    'message' => '治理关闭意见回填预览包 ' . $key . ' 计数不一致：manifest=' . (string)$manifestCounts[$key] . '，actual=' . (string)$actual,
                ];
            }
        }
        if ($actualCounts['database_write_performed'] !== 0) {
            $findings[] = [
                'severity' => 'high',
                'id' => 'governance_closure_preview_database_write_flagged',
                'message' => '治理关闭意见回填预览包 database_write_performed 必须为 0。',
            ];
        }
        if ($blockingItems > 0 && $readyForRefresh !== 'no') {
            $findings[] = [
                'severity' => 'high',
                'id' => 'governance_closure_preview_ready_refresh_conflicts_with_blocking_items',
                'message' => '仍有阻断项时 ready_for_governance_readiness_refresh 必须为 no。',
            ];
        }
        if ($readyForApply === 'yes') {
            $findings[] = [
                'severity' => 'high',
                'id' => 'governance_closure_preview_cannot_authorize_lims_apply',
                'message' => '治理关闭意见回填预览包不能单独声明 ready_for_lims_apply=yes。',
            ];
        }

        foreach (['overview', 'readme'] as $key) {
            $path = (string)($paths[$key] ?? '');
            if (!is_file($path)) {
                continue;
            }
            $text = (string)file_get_contents($path);
            foreach (['不写数据库', '不代表人工评审通过', '不写入质量手册正文'] as $marker) {
                if (!str_contains($text, $marker)) {
                    $findings[] = [
                        'severity' => 'high',
                        'id' => 'governance_closure_preview_doc_missing_guardrail',
                        'message' => basename($path) . ' 缺少边界标识：' . $marker,
                    ];
                }
            }
        }

        return [
            'governance_closure_preview_dir' => $previewDir,
            'status' => self::hasHighFinding($findings) ? 'invalid' : 'passed',
            'manifest_status' => $manifestStatus,
            'readiness' => $readiness,
            'ready_for_governance_readiness_refresh' => $readyForRefresh,
            'ready_for_lims_apply' => $readyForApply,
            'decision_items' => $actualCounts['decision_items'],
            'proposed_closures' => $actualCounts['proposed_closures'],
            'not_proposed' => $actualCounts['not_proposed'],
            'accepted_for_preview' => $actualCounts['accepted_for_preview'],
            'invalid_closures' => $actualCounts['invalid_closures'],
            'missing_required_fields' => $actualCounts['missing_required_fields'],
            'blocking_items' => $actualCounts['blocking_items'],
            'database_write_performed' => $actualCounts['database_write_performed'],
            'findings' => $findings,
        ];
    }

    private static function inspectGovernanceReadinessRefreshPreview(string $previewDir): array
    {
        $findings = [];
        $previewDir = rtrim($previewDir, '/\\');
        $manifestPath = $previewDir . DIRECTORY_SEPARATOR . self::REQUIRED_GOVERNANCE_READINESS_REFRESH_FILES['manifest'];
        $manifest = [];
        $manifestStatus = 'missing';
        $readiness = '';
        $readyForApply = '';
        if (!is_file($manifestPath)) {
            $findings[] = [
                'severity' => 'high',
                'id' => 'missing_governance_readiness_refresh_manifest',
                'message' => '治理就绪刷新预览包缺少 governance_readiness_refresh_preview_manifest.json。',
            ];
        } else {
            $manifest = json_decode((string)file_get_contents($manifestPath), true) ?: [];
            $manifestStatus = (string)($manifest['status'] ?? '');
            $readiness = (string)($manifest['readiness'] ?? '');
            $readyForApply = (string)($manifest['ready_for_lims_apply'] ?? '');
            if ($manifestStatus !== 'governance_readiness_refresh_preview_no_database_write') {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'invalid_governance_readiness_refresh_manifest_status',
                    'message' => '治理就绪刷新预览包 manifest 状态不符合预期。',
                ];
            }
            $guardrailText = implode("\n", array_map(static fn($value): string => (string)$value, (array)($manifest['guardrails'] ?? [])));
            foreach (self::GOVERNANCE_READINESS_REFRESH_REQUIRED_GUARDRAILS as $marker) {
                if (!str_contains($guardrailText, $marker)) {
                    $findings[] = [
                        'severity' => 'high',
                        'id' => 'governance_readiness_refresh_manifest_missing_guardrail',
                        'message' => '治理就绪刷新预览包 manifest 缺少边界标识：' . $marker,
                    ];
                }
            }
        }

        $files = (array)($manifest['files'] ?? []);
        $paths = [];
        foreach (self::REQUIRED_GOVERNANCE_READINESS_REFRESH_FILES as $key => $defaultFilename) {
            $filename = (string)($files[$key] ?? $defaultFilename);
            $path = $previewDir . DIRECTORY_SEPARATOR . $filename;
            $paths[$key] = $path;
            if (!is_file($path)) {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'missing_governance_readiness_refresh_' . $key,
                    'message' => '治理就绪刷新预览包缺少文件：' . $filename,
                ];
            }
        }

        foreach (self::forbiddenDatabaseArtifacts($previewDir) as $path) {
            $findings[] = [
                'severity' => 'high',
                'id' => 'governance_readiness_refresh_forbidden_database_artifact',
                'message' => '治理就绪刷新预览包不应包含数据库/SQL 文件：' . basename((string)$path),
            ];
        }

        $gateRows = is_file($paths['gate_refresh_preview'] ?? '') ? self::readCsv($paths['gate_refresh_preview']) : [];
        $taskRows = is_file($paths['task_refresh_preview'] ?? '') ? self::readCsv($paths['task_refresh_preview']) : [];
        $blockingRows = is_file($paths['blocking_tasks'] ?? '') ? self::readCsv($paths['blocking_tasks']) : [];

        $taskIds = [];
        $acceptedTaskClosures = 0;
        $refreshedBlockingTasks = 0;
        $expectedBlockingIds = [];
        foreach ($taskRows as $index => $row) {
            $line = $index + 2;
            $taskId = trim((string)($row['task_id'] ?? ''));
            if ($taskId === '') {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'governance_readiness_refresh_blank_task_id',
                    'message' => '人工任务刷新预览第 ' . $line . ' 行 task_id 为空。',
                ];
            } elseif (isset($taskIds[$taskId])) {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'governance_readiness_refresh_duplicate_task_id',
                    'message' => '人工任务刷新预览存在重复 task_id：' . $taskId,
                ];
            }
            if ($taskId !== '') {
                $taskIds[$taskId] = true;
            }
            foreach (['not_real_record' => 'not_real_record=yes', 'not_imported' => 'not_imported=yes'] as $field => $label) {
                if ((string)($row[$field] ?? '') !== 'yes') {
                    $findings[] = [
                        'severity' => 'high',
                        'id' => 'governance_readiness_refresh_task_marker_missing',
                        'message' => ($taskId ?: '人工任务刷新预览第 ' . $line . ' 行') . ' 必须保留 ' . $label . '。',
                    ];
                }
            }
            foreach (['accepted_for_refresh', 'blocking_after_refresh'] as $field) {
                if (!in_array((string)($row[$field] ?? ''), ['yes', 'no'], true)) {
                    $findings[] = [
                        'severity' => 'high',
                        'id' => 'governance_readiness_refresh_task_flag_invalid',
                        'message' => ($taskId ?: '人工任务刷新预览第 ' . $line . ' 行') . ' ' . $field . ' 必须为 yes/no。',
                    ];
                }
            }
            if ((string)($row['accepted_for_refresh'] ?? '') === 'yes') {
                $acceptedTaskClosures++;
                if ((string)($row['closure_preview_result'] ?? '') !== 'accepted_for_preview') {
                    $findings[] = [
                        'severity' => 'high',
                        'id' => 'governance_readiness_refresh_accepted_without_preview',
                        'message' => ($taskId ?: '人工任务刷新预览第 ' . $line . ' 行') . ' 被刷新关闭，但 closure_preview_result 不是 accepted_for_preview。',
                    ];
                }
            }
            if ((string)($row['blocking_after_refresh'] ?? '') === 'yes') {
                $refreshedBlockingTasks++;
                if ($taskId !== '') {
                    $expectedBlockingIds[$taskId] = true;
                }
            }
        }

        $actualBlockingIds = [];
        foreach ($blockingRows as $row) {
            $taskId = trim((string)($row['task_id'] ?? ''));
            if ($taskId !== '') {
                $actualBlockingIds[$taskId] = true;
            }
        }
        ksort($expectedBlockingIds);
        ksort($actualBlockingIds);
        if (array_keys($expectedBlockingIds) !== array_keys($actualBlockingIds)) {
            $findings[] = [
                'severity' => 'high',
                'id' => 'governance_readiness_refresh_blocking_register_mismatch',
                'message' => '仍阻断任务清单与人工任务刷新预览中的 blocking_after_refresh=yes 不一致。',
            ];
        }

        $gateTaskSum = 0;
        $gateAcceptedSum = 0;
        $gateBlockingSum = 0;
        $refreshedBlockingGates = 0;
        foreach ($gateRows as $index => $row) {
            $line = $index + 2;
            $gateId = trim((string)($row['gate_id'] ?? ''));
            foreach (['not_real_record' => 'not_real_record=yes', 'not_imported' => 'not_imported=yes'] as $field => $label) {
                if ((string)($row[$field] ?? '') !== 'yes') {
                    $findings[] = [
                        'severity' => 'high',
                        'id' => 'governance_readiness_refresh_gate_marker_missing',
                        'message' => ($gateId ?: '总闸门刷新预览第 ' . $line . ' 行') . ' 必须保留 ' . $label . '。',
                    ];
                }
            }
            if (!in_array((string)($row['ready_for_refresh'] ?? ''), ['yes', 'no'], true)) {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'governance_readiness_refresh_gate_ready_flag_invalid',
                    'message' => ($gateId ?: '总闸门刷新预览第 ' . $line . ' 行') . ' ready_for_refresh 必须为 yes/no。',
                ];
            }
            $taskRowsCount = (int)($row['task_rows'] ?? 0);
            $acceptedCount = (int)($row['accepted_task_closures'] ?? 0);
            $blockingCount = (int)($row['open_blocking_tasks_after_refresh'] ?? 0);
            $gateTaskSum += $taskRowsCount;
            $gateAcceptedSum += $acceptedCount;
            $gateBlockingSum += $blockingCount;
            if ($blockingCount > 0) {
                $refreshedBlockingGates++;
                if ((string)($row['ready_for_refresh'] ?? '') !== 'no') {
                    $findings[] = [
                        'severity' => 'high',
                        'id' => 'governance_readiness_refresh_gate_ready_conflict',
                        'message' => ($gateId ?: '总闸门刷新预览第 ' . $line . ' 行') . ' 仍有阻断任务时 ready_for_refresh 必须为 no。',
                    ];
                }
            }
        }
        if ($gateTaskSum !== count($taskRows)) {
            $findings[] = [
                'severity' => 'high',
                'id' => 'governance_readiness_refresh_gate_task_sum_mismatch',
                'message' => '总闸门 task_rows 合计 ' . (string)$gateTaskSum . '，人工任务刷新预览实际 ' . (string)count($taskRows) . '。',
            ];
        }
        if ($gateAcceptedSum !== $acceptedTaskClosures) {
            $findings[] = [
                'severity' => 'high',
                'id' => 'governance_readiness_refresh_gate_accepted_sum_mismatch',
                'message' => '总闸门 accepted_task_closures 合计 ' . (string)$gateAcceptedSum . '，实际 ' . (string)$acceptedTaskClosures . '。',
            ];
        }
        if ($gateBlockingSum !== $refreshedBlockingTasks) {
            $findings[] = [
                'severity' => 'high',
                'id' => 'governance_readiness_refresh_gate_blocking_sum_mismatch',
                'message' => '总闸门 open_blocking_tasks_after_refresh 合计 ' . (string)$gateBlockingSum . '，实际 ' . (string)$refreshedBlockingTasks . '。',
            ];
        }

        $manifestCounts = (array)($manifest['counts'] ?? []);
        $actualCounts = [
            'gate_rows' => count($gateRows),
            'task_preview_rows' => count($taskRows),
            'accepted_task_closures' => $acceptedTaskClosures,
            'refreshed_blocking_tasks' => $refreshedBlockingTasks,
            'refreshed_blocking_gates' => $refreshedBlockingGates,
            'database_write_performed' => (int)($manifestCounts['database_write_performed'] ?? 0),
        ];
        foreach ($actualCounts as $key => $actual) {
            if (!array_key_exists($key, $manifestCounts)) {
                continue;
            }
            if ((int)$manifestCounts[$key] !== $actual) {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'governance_readiness_refresh_count_mismatch_' . $key,
                    'message' => '治理就绪刷新预览包 ' . $key . ' 计数不一致：manifest=' . (string)$manifestCounts[$key] . '，actual=' . (string)$actual,
                ];
            }
        }
        if ($actualCounts['database_write_performed'] !== 0) {
            $findings[] = [
                'severity' => 'high',
                'id' => 'governance_readiness_refresh_database_write_flagged',
                'message' => '治理就绪刷新预览包 database_write_performed 必须为 0。',
            ];
        }
        if ($refreshedBlockingTasks > 0 && $readyForApply !== 'no') {
            $findings[] = [
                'severity' => 'high',
                'id' => 'governance_readiness_refresh_ready_flag_conflicts_with_blocking_tasks',
                'message' => '仍有刷新后阻断任务时 ready_for_lims_apply 必须为 no。',
            ];
        }

        foreach (['overview', 'readme'] as $key) {
            $path = (string)($paths[$key] ?? '');
            if (!is_file($path)) {
                continue;
            }
            $text = (string)file_get_contents($path);
            foreach (['不写数据库', '不代表人工评审通过', '不写入质量手册正文'] as $marker) {
                if (!str_contains($text, $marker)) {
                    $findings[] = [
                        'severity' => 'high',
                        'id' => 'governance_readiness_refresh_doc_missing_guardrail',
                        'message' => basename($path) . ' 缺少边界标识：' . $marker,
                    ];
                }
            }
        }

        return [
            'governance_readiness_refresh_dir' => $previewDir,
            'status' => self::hasHighFinding($findings) ? 'invalid' : 'passed',
            'manifest_status' => $manifestStatus,
            'readiness' => $readiness,
            'ready_for_lims_apply' => $readyForApply,
            'gate_rows' => $actualCounts['gate_rows'],
            'task_preview_rows' => $actualCounts['task_preview_rows'],
            'accepted_task_closures' => $actualCounts['accepted_task_closures'],
            'refreshed_blocking_tasks' => $actualCounts['refreshed_blocking_tasks'],
            'refreshed_blocking_gates' => $actualCounts['refreshed_blocking_gates'],
            'database_write_performed' => $actualCounts['database_write_performed'],
            'findings' => $findings,
        ];
    }

    private static function normalizeGovernanceClosureStatus(string $status): string
    {
        $normalized = strtolower(str_replace([' ', '-'], ['_', '_'], trim($status)));
        return match ($normalized) {
            '', 'pending', 'open' => 'pending',
            'closed', 'close', 'done', 'resolved' => 'closed',
            'not_applicable', 'na', 'n/a' => 'not_applicable',
            'waived', 'waive' => 'waived',
            'rejected', 'reject', 'reopen' => 'rejected',
            default => match (trim($status)) {
                '待确认', '待关闭' => 'pending',
                '已关闭', '完成', '通过' => 'closed',
                '不适用' => 'not_applicable',
                '豁免' => 'waived',
                '退回' => 'rejected',
                default => $normalized,
            },
        };
    }

    private static function normalizeStage2ReviewDecision(string $decision): string
    {
        $decision = trim($decision);
        if ($decision === '') {
            return '';
        }
        $key = strtolower(str_replace(' ', '_', $decision));
        $map = [
            'approved' => 'approved',
            'approve' => 'approved',
            'accepted' => 'approved',
            'accept' => 'approved',
            'pass' => 'approved',
            'passed' => 'approved',
            'yes' => 'approved',
            '同意' => 'approved',
            '通过' => 'approved',
            '批准' => 'approved',
            '确认通过' => 'approved',
            'revise' => 'revise',
            'revision' => 'revise',
            'needs_revision' => 'revise',
            'need_revision' => 'revise',
            '修改' => 'revise',
            '修订' => 'revise',
            '需修订' => 'revise',
            '需要修订' => 'revise',
            '退回修改' => 'revise',
            'remove' => 'remove',
            'removed' => 'remove',
            'delete' => 'remove',
            '作废' => 'remove',
            '删除' => 'remove',
            '移除' => 'remove',
            '不导入' => 'remove',
            'pending' => 'pending',
            '待定' => 'pending',
            '待确认' => 'pending',
        ];
        return (string)($map[$key] ?? $map[$decision] ?? $decision);
    }

    private static function stage2AllowedDecisions(string $allowedDecisions): array
    {
        $allowed = [];
        foreach (explode('|', $allowedDecisions) as $decision) {
            $normalized = self::normalizeStage2ReviewDecision((string)$decision);
            if ($normalized !== '') {
                $allowed[$normalized] = true;
            }
        }
        if ($allowed === []) {
            $allowed = ['approved' => true, 'revise' => true, 'remove' => true, 'pending' => true];
        }
        return $allowed;
    }

    private static function inspectReviewPack(string $reviewDir): array
    {
        $findings = [];
        $reviewDir = rtrim($reviewDir, '/\\');
        $manifestPath = $reviewDir . DIRECTORY_SEPARATOR . 'human_review_manifest.json';
        $manifestStatus = 'missing';
        $isSimulated = false;
        $simulationMarkerRows = 0;
        if (!is_file($manifestPath)) {
            $findings[] = [
                'severity' => 'high',
                'id' => 'missing_human_review_manifest',
                'message' => '人工评审包缺少 human_review_manifest.json。',
            ];
        } else {
            $manifest = json_decode((string)file_get_contents($manifestPath), true) ?: [];
            $manifestStatus = (string)($manifest['status'] ?? '');
            $isSimulated = $manifestStatus === 'human_review_simulation_no_database_write'
                || (string)($manifest['simulation_marker'] ?? '') === self::REVIEW_SIMULATION_MARKER;
            if (!in_array($manifestStatus, ['human_review_required_no_database_write', 'human_review_simulation_no_database_write'], true)) {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'invalid_human_review_manifest_status',
                    'message' => '人工评审包 manifest 状态不符合预期。',
                ];
            }
        }

        $total = 0;
        $approved = 0;
        $pending = 0;
        $unapproved = 0;
        $requiredGates = 0;
        $approvedRequiredGates = 0;

        foreach (self::REQUIRED_REVIEW_FILES as $key => $filename) {
            $path = $reviewDir . DIRECTORY_SEPARATOR . $filename;
            if (!is_file($path)) {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'missing_human_review_' . $key,
                    'message' => '人工评审包缺少文件：' . $filename,
                ];
                continue;
            }
            foreach (self::readCsv($path) as $row) {
                $decision = trim((string)($row['human_decision'] ?? ''));
                if (str_contains(implode("\n", array_map(static fn($value): string => (string)$value, $row)), self::REVIEW_SIMULATION_MARKER)) {
                    $isSimulated = true;
                    $simulationMarkerRows++;
                }
                if ($decision === '') {
                    continue;
                }
                $total++;
                if (self::isApprovedDecision($decision)) {
                    $approved++;
                    if ($key === 'preapply_gate_register' && (string)($row['required_before_apply'] ?? '') === 'yes') {
                        $approvedRequiredGates++;
                    }
                    continue;
                }
                if ($decision === 'pending') {
                    $pending++;
                } else {
                    $unapproved++;
                }
                if ($key === 'preapply_gate_register' && (string)($row['required_before_apply'] ?? '') === 'yes') {
                    $requiredGates++;
                }
            }
        }

        $status = 'approved';
        if ($findings !== []) {
            $status = 'invalid';
        } elseif ($total === 0) {
            $status = 'empty';
        } elseif ($pending > 0 || $unapproved > 0) {
            $status = 'pending';
        }

        return [
            'review_dir' => $reviewDir,
            'status' => $status,
            'manifest_status' => $manifestStatus,
            'is_simulated' => $isSimulated ? 1 : 0,
            'simulation_marker_rows' => $simulationMarkerRows,
            'total_decisions' => $total,
            'approved_decisions' => $approved,
            'pending_decisions' => $pending,
            'unapproved_decisions' => $unapproved,
            'required_gates' => $requiredGates + $approvedRequiredGates,
            'approved_required_gates' => $approvedRequiredGates,
            'findings' => $findings,
        ];
    }

    private static function isApprovedDecision(string $decision): bool
    {
        $decision = strtolower(trim($decision));
        return in_array($decision, ['approved', 'accepted', 'pass', 'passed', 'yes', '同意', '通过', '批准', '确认通过'], true);
    }

    private static function inspectStage2Readiness(
        array $rows,
        ?array $reviewPack,
        array $existingDocuments,
        array $existingTemplates,
        array $documentCodes,
        array $recordCodes
    ): array {
        $findings = [];
        $tableStatus = [];
        foreach (self::STAGE2_REQUIRED_TABLES as $table) {
            $available = self::tableExists($table);
            $tableStatus[$table] = $available ? 'available' : 'missing';
            if (!$available) {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'stage2_table_missing',
                    'message' => '第二阶段目标表不存在：' . $table,
                ];
            }
        }

        $documentCodeSet = array_fill_keys(array_values(array_unique($documentCodes)), true);
        foreach (array_keys($existingDocuments) as $code) {
            $documentCodeSet[(string)$code] = true;
        }
        $recordCodeSet = array_fill_keys(array_values(array_unique($recordCodes)), true);
        foreach (array_keys($existingTemplates) as $code) {
            $recordCodeSet[(string)$code] = true;
        }

        $structuredDocNumbers = [];
        foreach ((array)($rows['structured_documents'] ?? []) as $row) {
            $docNumber = trim((string)($row['doc_number'] ?? ''));
            if ($docNumber !== '') {
                $structuredDocNumbers[$docNumber] = true;
            }
        }

        $stableKeys = [];
        $manualClauses = [];
        $procedureLinks = 0;
        $attachmentLinks = 0;
        $recordLinks = 0;
        foreach ((array)($rows['manual_blocks'] ?? []) as $row) {
            $stableKey = trim((string)($row['stable_key'] ?? ''));
            $sectionNumber = trim((string)($row['section_number'] ?? ''));
            if ($sectionNumber !== '') {
                $manualClauses[$sectionNumber] = true;
            }
            if ($stableKey === '') {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'stage2_blank_stable_key',
                    'message' => 'manual_blocks_preimport 存在空 stable_key。',
                ];
            } elseif (isset($stableKeys[$stableKey])) {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'stage2_duplicate_stable_key',
                    'message' => 'manual_blocks_preimport 存在重复 stable_key：' . $stableKey,
                ];
            }
            $stableKeys[$stableKey] = true;

            $structuredDocNumber = trim((string)($row['structured_doc_number'] ?? ''));
            if ($structuredDocNumber === '' || !isset($structuredDocNumbers[$structuredDocNumber])) {
                $findings[] = [
                    'severity' => 'high',
                    'id' => 'stage2_unresolved_structured_document',
                    'message' => ($stableKey ?: '-') . ' 未能匹配 structured_documents_preimport 中的文件编号。',
                ];
            }

            $procedureLinks += self::countResolvableCodes(
                self::splitCodes((string)($row['procedure_doc_numbers'] ?? '')),
                $documentCodeSet,
                $findings,
                'stage2_missing_procedure_document',
                ($stableKey ?: '-') . ' 引用的程序文件未在 LIMS 当前文件或预导入文件中找到：'
            );
            $attachmentLinks += self::countResolvableCodes(
                self::splitCodes((string)($row['attachment_form_doc_numbers'] ?? '')),
                $documentCodeSet,
                $findings,
                'stage2_missing_attachment_form_document',
                ($stableKey ?: '-') . ' 引用的附件/表单文件未在 LIMS 当前文件或预导入文件中找到：'
            );
            $recordLinks += self::countResolvableCodes(
                self::splitCodes((string)($row['record_template_numbers'] ?? '')),
                $recordCodeSet,
                $findings,
                'stage2_missing_record_template',
                ($stableKey ?: '-') . ' 引用的记录模板未在 LIMS 当前模板或预导入模板中找到：'
            );
        }

        $traceClauses = [];
        foreach ((array)($rows['traceability_matrix'] ?? []) as $row) {
            $clause = trim((string)($row['clause'] ?? ''));
            if ($clause !== '') {
                $traceClauses[$clause] = true;
            }
        }
        $manualMissingTrace = array_values(array_diff(array_keys($manualClauses), array_keys($traceClauses)));
        $traceMissingManual = array_values(array_diff(array_keys($traceClauses), array_keys($manualClauses)));
        if ($manualMissingTrace !== []) {
            $findings[] = [
                'severity' => 'high',
                'id' => 'stage2_manual_block_without_traceability',
                'message' => '手册块缺少追溯矩阵对应条款：' . implode('、', array_slice($manualMissingTrace, 0, 12)),
            ];
        }
        if ($traceMissingManual !== []) {
            $findings[] = [
                'severity' => 'high',
                'id' => 'stage2_traceability_without_manual_block',
                'message' => '追溯矩阵缺少手册块对应条款：' . implode('、', array_slice($traceMissingManual, 0, 12)),
            ];
        }

        $reviewStatus = (string)($reviewPack['status'] ?? 'not_provided');
        if (self::hasHighFinding($findings)) {
            $status = 'invalid';
        } elseif ($reviewStatus === 'approved') {
            $status = 'ready_after_phase1_apply';
        } else {
            $status = 'blocked_by_human_review';
        }

        return [
            'status' => $status,
            'review_pack_status' => $reviewStatus,
            'target_tables' => $tableStatus,
            'structured_documents_planned' => count((array)($rows['structured_documents'] ?? [])),
            'manual_blocks_planned' => count((array)($rows['manual_blocks'] ?? [])),
            'traceability_rows_planned' => count((array)($rows['traceability_matrix'] ?? [])),
            'procedure_block_links_planned' => $procedureLinks,
            'attachment_form_block_links_planned' => $attachmentLinks,
            'record_template_block_links_planned' => $recordLinks,
            'manual_traceability_clause_mismatches' => count($manualMissingTrace) + count($traceMissingManual),
            'pending_human_decisions' => (int)($reviewPack['pending_decisions'] ?? 0),
            'unapproved_human_decisions' => (int)($reviewPack['unapproved_decisions'] ?? 0),
            'findings' => $findings,
        ];
    }

    private static function splitCodes(string $value): array
    {
        $parts = preg_split('/[;；,，、]/u', $value) ?: [];
        $codes = [];
        foreach ($parts as $part) {
            $code = trim((string)$part);
            if ($code !== '') {
                $codes[] = $code;
            }
        }
        return array_values(array_unique($codes));
    }

    private static function forbiddenDatabaseArtifacts(string $dir): array
    {
        if (!is_dir($dir)) {
            return [];
        }
        $paths = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if (!$file instanceof \SplFileInfo || !$file->isFile()) {
                continue;
            }
            $extension = strtolower((string)$file->getExtension());
            if (in_array($extension, ['sql', 'db'], true)) {
                $paths[] = $file->getPathname();
            }
        }
        return $paths;
    }

    private static function countResolvableCodes(
        array $codes,
        array $knownCodes,
        array &$findings,
        string $findingId,
        string $messagePrefix
    ): int {
        $count = 0;
        foreach ($codes as $code) {
            if (!isset($knownCodes[$code])) {
                $findings[] = [
                    'severity' => 'high',
                    'id' => $findingId,
                    'message' => $messagePrefix . $code,
                ];
                continue;
            }
            $count++;
        }
        return $count;
    }

    private static function tableExists(string $table): bool
    {
        $rows = Db::query(
            'SELECT COUNT(*) AS total FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?',
            [$table]
        );
        return ((int)($rows[0]['total'] ?? 0)) > 0;
    }

    private static function existingDocumentRows(array $codes): array
    {
        return self::rowsByCode('documents', 'doc_number', $codes, 'id,doc_number,title,status,level,version');
    }

    private static function existingRecordTemplateRows(array $codes): array
    {
        return self::rowsByCode('record_form_templates', 'doc_number', $codes, 'id,doc_number,name,status,review_status,version');
    }

    private static function existingSourceRows(array $codes): array
    {
        return self::rowsByCode('qms_sources', 'source_code', $codes, 'id,source_code,name,status,freshness_status,version');
    }

    private static function existingStructuredDocumentRows(array $rows): array
    {
        $docNumbers = array_values(array_unique(array_filter(array_map(
            static fn(array $row): string => trim((string)($row['doc_number'] ?? '')),
            $rows
        ))));
        if ($docNumbers === []) {
            return [];
        }
        $existingRows = Db::name('qms_structured_documents')
            ->whereIn('doc_number', $docNumbers)
            ->where('soft_delete', 0)
            ->field('id,document_role,doc_number,version,status')
            ->select()
            ->toArray();
        $result = [];
        foreach ($existingRows as $row) {
            $result[self::structuredDocumentKey(
                (string)($row['document_role'] ?? ''),
                (string)($row['doc_number'] ?? ''),
                (string)($row['version'] ?? '')
            )] = $row;
        }
        return $result;
    }

    private static function structuredDocumentKey(string $role, string $docNumber, string $version): string
    {
        return $role . '|' . $docNumber . '|' . $version;
    }

    private static function rowsByCode(string $table, string $field, array $codes, string $select): array
    {
        $codes = array_values(array_unique(array_filter($codes)));
        if ($codes === []) {
            return [];
        }
        $rows = Db::name($table)
            ->whereIn($field, $codes)
            ->where('soft_delete', 0)
            ->field($select)
            ->select()
            ->toArray();
        $result = [];
        foreach ($rows as $row) {
            $result[(string)$row[$field]] = $row;
        }
        return $result;
    }

    private static function documentIdByNumber(array $codes): array
    {
        $rows = self::existingDocumentRows($codes);
        $result = [];
        foreach ($rows as $code => $row) {
            $result[$code] = (string)$row['id'];
        }
        return $result;
    }

    private static function recordTemplateIdByNumber(array $codes): array
    {
        $rows = self::existingRecordTemplateRows($codes);
        $result = [];
        foreach ($rows as $code => $row) {
            $result[$code] = (string)$row['id'];
        }
        return $result;
    }

    private static function firstExistingProcedureDocumentId(string $procedureCodes, array $documentIdByNumber): ?string
    {
        foreach (preg_split('/[;；]/u', $procedureCodes) ?: [] as $code) {
            $code = trim((string)$code);
            if ($code !== '' && isset($documentIdByNumber[$code])) {
                return $documentIdByNumber[$code];
            }
        }
        return null;
    }

    private static function nullableDate(string $value): ?string
    {
        $value = trim($value);
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : null;
    }

    private static function hasHighFinding(array $findings): bool
    {
        foreach ($findings as $finding) {
            if (($finding['severity'] ?? '') === 'high') {
                return true;
            }
        }
        return false;
    }

    private static function hasBlockingFinding(array $summary): bool
    {
        if (self::hasHighFinding((array)($summary['findings'] ?? []))) {
            return true;
        }
        return ((int)($summary['readiness']['missing_reference_current_documents'] ?? 0)) > 0;
    }

    private static function ensureDirectory(string $dir): void
    {
        if ($dir !== '' && !is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
    }
}
