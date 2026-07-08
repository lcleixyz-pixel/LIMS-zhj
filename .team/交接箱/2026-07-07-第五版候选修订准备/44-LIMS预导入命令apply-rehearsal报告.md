# LIMS 预导入命令 apply-rehearsal 非写库演练报告

生成时间：2026-07-07T12:22:50
命令模式：apply-rehearsal
结论：rehearsal_ready
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
- review_pack_pending: 0
- field_catalog_templates: 26
- field_catalog_fields: 437
- release_plan_objects: 65
- release_plan_training_items: 29
- release_execution_templates: 6
- release_execution_fields: 120
- release_execution_trial_instances: 6
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
- review_pack_status: approved
- review_pack_pending_decisions: 0
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

## 人工评审包

- review_dir: /workspace/.team/交接箱/2026-07-07-第五版候选修订准备/human_review_simulation_pack
- status: approved
- manifest_status: human_review_simulation_no_database_write
- is_simulated: 1
- simulation_marker_rows: 67
- total_decisions: 67
- approved_decisions: 67
- pending_decisions: 0
- unapproved_decisions: 0
- required_gates: 11
- approved_required_gates: 11

## 第二阶段结构化导入预检

- status: ready_after_phase1_apply
- review_pack_status: approved
- target_tables: {"qms_structured_documents":"available","qms_document_blocks":"available","qms_document_block_links":"available"}
- structured_documents_planned: 65
- manual_blocks_planned: 29
- traceability_rows_planned: 29
- procedure_block_links_planned: 93
- attachment_form_block_links_planned: 2
- record_template_block_links_planned: 30
- manual_traceability_clause_mismatches: 0
- pending_human_decisions: 0
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

## Apply-Rehearsal 演练计划

- database_write_performed: 0
- documents_would_evaluate: 65
- record_templates_would_evaluate: 26
- external_sources_would_evaluate: 4
- structured_documents_still_deferred: 65
- manual_blocks_still_deferred: 29
- traceability_links_still_deferred: 29
- simulated_review_pack_used: 1

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
- apply-rehearsal 只验证真实 apply 前的同等闸门，不进入数据库事务，不创建 draft 文件、记录模板或外来依据。
- 若使用模拟人审包，该结果只能证明命令链路可演练，不能替代真实人工评审或用户授权。
