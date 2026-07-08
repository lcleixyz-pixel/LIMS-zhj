# LIMS 预导入命令 dry-run 报告

生成时间：2026-07-07T21:30:37
命令模式：dry-run
结论：passed
预导入包：`/workspace/.team/交接箱/2026-07-07-第五版候选修订准备/lims_preimport_package`

## 计数

- documents: 65
- structured_documents: 65
- record_form_templates: 26
- traceability_rows: 29
- manual_blocks: 29
- external_sources: 4
- candidate_document_rows: 28
- reference_current_document_rows: 37
- review_pack_items: 67
- review_pack_pending: 67
- field_catalog_templates: 26
- field_catalog_fields: 437
- release_plan_objects: 65
- release_plan_training_items: 29
- release_execution_templates: 6
- release_execution_fields: 120
- release_execution_trial_instances: 6
- manual_revision_gates: 9
- manual_revision_decisions: 5
- manual_revision_pending_decisions: 5
- staff_training_source_items: 29
- staff_training_tasks: 88
- staff_training_questions: 12
- staff_training_feedback_rows: 8
- staff_training_pending_tasks: 88
- staff_training_pending_questions: 12
- stage2_review_items: 154
- stage2_review_pending_decisions: 154
- stage2_review_approved_decisions: 0
- stage2_review_preview_items: 154
- stage2_review_preview_blocking_items: 154
- governance_readiness_gates: 13
- governance_readiness_blocking_gates: 12
- governance_readiness_tasks: 396
- governance_readiness_blocking_tasks: 392
- governance_closure_items: 396
- governance_closure_open_blocking_items: 392
- governance_closure_accepted_closures: 0
- governance_closure_execution_batches: 60
- governance_closure_execution_signatures: 50
- governance_closure_execution_pending_signatures: 50
- governance_closure_execution_pending_routes: 396
- governance_closure_preview_items: 396
- governance_closure_preview_blocking_items: 392
- governance_closure_preview_accepted: 0
- governance_readiness_refresh_tasks: 396
- governance_readiness_refresh_blocking_tasks: 392
- governance_readiness_refresh_accepted_closures: 0
- findings: 0

## LIMS 对接判断

- existing_document_matches: 38
- existing_reference_current_documents: 37
- missing_reference_current_documents: 0
- candidate_documents_would_create_or_skip: 28
- existing_record_template_matches: 0
- record_templates_would_create_or_skip: 26
- external_sources_would_upsert: 4
- structured_documents_deferred: 65
- manual_blocks_deferred: 29
- traceability_links_deferred: 29
- review_pack_status: pending
- review_pack_pending_decisions: 67
- review_pack_unapproved_decisions: 0
- field_catalog_status: passed
- field_catalog_templates: 26
- field_catalog_fields: 437
- field_catalog_human_confirmation_fields: 437
- release_plan_status: passed
- release_plan_objects: 65
- release_plan_training_items: 29
- release_plan_release_allowed_now: 0
- release_execution_status: passed
- release_execution_templates: 6
- release_execution_fields: 120
- release_execution_trial_instances: 6
- manual_revision_status: passed
- manual_revision_target_doc_number: XZTC/SC
- manual_revision_pending_human_decisions: 5
- manual_revision_existing_route: existing_document_revision_required
- staff_training_status: passed
- staff_training_source_items: 29
- staff_training_tasks: 88
- staff_training_questions: 12
- staff_training_pending_tasks: 88
- staff_training_pending_questions: 12
- staff_training_blank_feedback_decisions: 8
- stage2_review_status: pending
- stage2_review_items: 154
- stage2_review_pending_decisions: 154
- stage2_review_approved_decisions: 0
- stage2_review_revise_decisions: 0
- stage2_review_remove_decisions: 0
- stage2_review_preview_status: passed
- stage2_review_preview_readiness: no_proposed_decisions
- stage2_review_preview_items: 154
- stage2_review_preview_blocking_items: 154
- governance_readiness_status: passed
- governance_readiness_readiness: blocked_by_governance_open_items
- governance_readiness_ready_for_lims_apply: no
- governance_readiness_gates: 13
- governance_readiness_blocking_gates: 12
- governance_readiness_tasks: 396
- governance_readiness_blocking_tasks: 392
- governance_closure_status: passed
- governance_closure_readiness: blocked_by_open_closures
- governance_closure_ready_for_governance_readiness_refresh: no
- governance_closure_ready_for_lims_apply: no
- governance_closure_items: 396
- governance_closure_open_blocking_items: 392
- governance_closure_accepted_closures: 0
- governance_closure_execution_status: passed
- governance_closure_execution_readiness: blocked_by_unsigned_execution
- governance_closure_execution_ready_for_preview: no
- governance_closure_execution_batches: 60
- governance_closure_execution_signature_rows: 50
- governance_closure_execution_pending_signatures: 50
- governance_closure_execution_pending_handoffs: 60
- governance_closure_execution_pending_routes: 396
- governance_closure_execution_blocking_routes: 392
- governance_closure_preview_status: passed
- governance_closure_preview_readiness: blocked_by_open_closures
- governance_closure_preview_ready_for_governance_readiness_refresh: no
- governance_closure_preview_ready_for_lims_apply: no
- governance_closure_preview_items: 396
- governance_closure_preview_blocking_items: 392
- governance_closure_preview_accepted: 0
- governance_readiness_refresh_status: passed
- governance_readiness_refresh_readiness: blocked_by_refresh_open_items
- governance_readiness_refresh_ready_for_lims_apply: no
- governance_readiness_refresh_gates: 13
- governance_readiness_refresh_tasks: 396
- governance_readiness_refresh_blocking_tasks: 392
- governance_readiness_refresh_blocking_gates: 11
- governance_readiness_refresh_accepted_closures: 0

## 人工评审包

- review_dir: /workspace/.team/交接箱/2026-07-07-第五版候选修订准备/human_review_pack
- status: pending
- manifest_status: human_review_required_no_database_write
- is_simulated: 0
- simulation_marker_rows: 0
- total_decisions: 67
- approved_decisions: 0
- pending_decisions: 67
- unapproved_decisions: 0
- required_gates: 11
- approved_required_gates: 0

## 第二阶段结构化导入预检

- status: blocked_by_human_review
- review_pack_status: pending
- target_tables: {"qms_structured_documents":"available","qms_document_blocks":"available","qms_document_block_links":"available"}
- structured_documents_planned: 65
- manual_blocks_planned: 29
- traceability_rows_planned: 29
- procedure_block_links_planned: 93
- attachment_form_block_links_planned: 2
- record_template_block_links_planned: 30
- manual_traceability_clause_mismatches: 0
- pending_human_decisions: 67
- unapproved_human_decisions: 0

## 记录模板字段字典

- field_catalog_dir: /workspace/.team/交接箱/2026-07-07-第五版候选修订准备/record_template_field_catalog
- status: passed
- record_templates: 26
- source_record_templates: 26
- field_detail_rows: 437
- template_markdown_files: 26
- human_confirmation_fields: 437

## 受控发布治理演练

- release_plan_dir: /workspace/.team/交接箱/2026-07-07-第五版候选修订准备/controlled_release_rehearsal
- status: passed
- release_objects: 65
- approval_items: 28
- training_items: 29
- obsolete_items: 28
- position_gates: 5
- effectiveness_items: 4
- candidate_manual_objects: 1
- current_procedure_references: 37
- candidate_record_template_documents: 26
- attachment_form_pending: 1
- release_allowed_now: 0

## 发布执行记录模板

- release_execution_dir: /workspace/.team/交接箱/2026-07-07-第五版候选修订准备/release_execution_template_pack
- status: passed
- templates: 6
- expected_templates: 6
- field_detail_rows: 120
- trial_instances: 6
- template_markdown_files: 6
- source_release_objects: 65
- source_approval_items: 28
- source_training_items: 29
- source_obsolete_items: 28
- source_effectiveness_items: 4

## 质量手册修订换版路径

- manual_revision_dir: /workspace/.team/交接箱/2026-07-07-第五版候选修订准备/manual_revision_path_pack
- status: passed
- target_doc_number: XZTC/SC
- existing_manual_rows: 1
- revision_gates: 9
- lims_action_preview_rows: 5
- human_decision_gates: 5
- pending_human_decisions: 5
- existing_lims_manual_status: published
- existing_manual_preview_action: plan_existing_document_revision
- revision_route_decision: existing_document_revision_required

## 机构人员学习实施包

- staff_training_dir: /workspace/.team/交接箱/2026-07-07-第五版候选修订准备/staff_training_implementation_pack
- status: passed
- training_source_items: 29
- role_learning_tasks: 88
- learning_materials: 12
- comprehension_questions: 12
- feedback_rows: 8
- role_cards: 10
- required_before_effective_source_items: 28
- pending_learning_tasks: 88
- pending_questions: 12
- blank_feedback_decisions: 8
- database_write_performed: 0
- source_release_training_items: 29

## 第二阶段结构化导入人工复核工作台

- stage2_review_dir: /workspace/.team/交接箱/2026-07-07-第五版候选修订准备/stage2_structured_review_workbench
- status: pending
- manifest_status: stage2_structured_review_workbench_no_database_write
- block_review_rows: 29
- link_review_rows: 125
- clause_summary_rows: 29
- target_backreference_rows: 64
- decision_items: 154
- approved_decisions: 0
- pending_decisions: 154
- revise_decisions: 0
- remove_decisions: 0
- invalid_decisions: 0
- missing_review_comments: 0
- database_write_performed: 0

## 第二阶段复核意见回填预览包

- stage2_review_preview_dir: /workspace/.team/交接箱/2026-07-07-第五版候选修订准备/stage2_structured_review_decision_preview
- status: passed
- manifest_status: stage2_review_decision_preview_no_database_write
- readiness: no_proposed_decisions
- decision_items: 154
- proposed_decisions: 0
- not_proposed: 154
- pending_decisions: 0
- accepted_for_preview: 0
- invalid_decisions: 0
- missing_review_comments: 0
- blocking_items: 154
- scope_summary_rows: 4
- database_write_performed: 0

## 治理就绪总览包

- governance_readiness_dir: /workspace/.team/交接箱/2026-07-07-第五版候选修订准备/governance_readiness_dashboard
- status: passed
- manifest_status: governance_readiness_no_database_write
- readiness: blocked_by_governance_open_items
- ready_for_lims_apply: no
- gate_rows: 13
- blocking_gates: 12
- human_task_rows: 396
- blocking_tasks: 392
- database_write_performed: 0

## 治理关闭工作台

- governance_closure_dir: /workspace/.team/交接箱/2026-07-07-第五版候选修订准备/governance_closure_workbench
- status: passed
- manifest_status: governance_closure_workbench_no_database_write
- readiness: blocked_by_open_closures
- ready_for_governance_readiness_refresh: no
- ready_for_lims_apply: no
- gate_rows: 13
- role_task_batches: 60
- evidence_rows: 396
- closure_rows: 396
- blocking_closure_items: 392
- open_blocking_items: 392
- accepted_closures: 0
- pending_closures: 396
- invalid_closure_rows: 0
- database_write_performed: 0

## 治理闭环执行包

- governance_closure_execution_dir: /workspace/.team/交接箱/2026-07-07-第五版候选修订准备/governance_closure_execution_pack
- status: passed
- manifest_status: governance_closure_execution_pack_no_database_write
- readiness: blocked_by_unsigned_execution
- ready_for_governance_closure_preview: no
- ready_for_lims_apply: no
- execution_batches: 60
- signature_rows: 50
- handoff_checks: 60
- route_rows: 396
- source_closure_items: 396
- blocking_route_items: 392
- pending_signature_rows: 50
- pending_handoff_checks: 60
- pending_route_items: 396
- database_write_performed: 0

## 治理关闭意见回填预览包

- governance_closure_preview_dir: /workspace/.team/交接箱/2026-07-07-第五版候选修订准备/governance_closure_decision_preview
- status: passed
- manifest_status: governance_closure_decision_preview_no_database_write
- readiness: blocked_by_open_closures
- ready_for_governance_readiness_refresh: no
- ready_for_lims_apply: no
- decision_items: 396
- proposed_closures: 0
- not_proposed: 396
- accepted_for_preview: 0
- invalid_closures: 0
- missing_required_fields: 0
- blocking_items: 392
- database_write_performed: 0

## 治理就绪刷新预览包

- governance_readiness_refresh_dir: /workspace/.team/交接箱/2026-07-07-第五版候选修订准备/governance_readiness_refresh_preview
- status: passed
- manifest_status: governance_readiness_refresh_preview_no_database_write
- readiness: blocked_by_refresh_open_items
- ready_for_lims_apply: no
- gate_rows: 13
- task_preview_rows: 396
- accepted_task_closures: 0
- refreshed_blocking_tasks: 392
- refreshed_blocking_gates: 11
- database_write_performed: 0

## 发现项

未发现阻断 dry-run 的问题。该结论不代表已写入 LIMS 或已发布受控文件。

## 边界

- dry-run 不写数据库。
- apply 需要 --apply 与 --ack-human-reviewed 同时出现。
- apply 还需要 --review-dir 指向已全部通过的人工评审包。
- reference_existing_current 程序文件只做匹配，不自动创建为已发布文件。
- 本命令不创建真实 record_form_instances 运行记录。
- 结构化文件、手册块和块级追溯关系仍作为后续受控治理步骤；--stage2-check 只做预检，不写入这些表。
- 字段字典校验只检查候选模板 schema 与人工评审材料一致性，不写数据库，不代表模板已受控发布。
- 受控发布治理演练只检查审批、培训、旧版处置和实施有效性准备，不写数据库，不代表批准或发布。
- 发布执行记录模板校验只检查候选模板结构、字段、模拟试填和边界，不写数据库，不代表形成真实运行记录。
- 质量手册修订/换版路径校验只检查 XZTC/SC 既有受控文件的修订路线，不写数据库，不代表批准发布。
- 机构人员学习实施包校验只检查学习任务、理解确认和反馈模板准备度，不写数据库，不代表真实培训完成或形成真实培训记录。
- 第二阶段结构化导入人工复核工作台校验只读取复核决策，不写数据库，不代表第二阶段已导入或人工评审通过。
- 第二阶段复核意见回填预览包校验只读取预览结果，不修改复核工作台，不写数据库，不代表第二阶段已导入。
- 治理就绪总览包只汇总全量治理闸门和人工处理任务，不写数据库，不代表人工评审通过、真实培训完成、受控发布或正式写库授权。
- 治理关闭工作台只读取拟关闭意见和证据回填状态，不写数据库，不代表人工评审通过、真实培训完成、受控发布或正式写库授权。
- 治理闭环执行包只检查执行批次、岗位签核、交接复核和回填路径，不写数据库，不代表人工评审通过、真实培训完成、受控发布或正式写库授权。
- 治理关闭意见回填预览包只读取关闭工作台的拟关闭结果，不修改工作台，不写数据库，不代表人工评审通过、真实培训完成、受控发布或正式写库授权。
- 治理就绪刷新预览包只模拟刷新后总闸门状态，不修改治理总览，不写数据库，不代表人工评审通过、真实培训完成、受控发布或正式写库授权。
- jewelry-qms 建设中系统只进入实施计划、演练和治理准备材料，不写入质量手册正文。
