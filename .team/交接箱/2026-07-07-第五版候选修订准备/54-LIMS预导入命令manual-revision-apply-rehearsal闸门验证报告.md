# LIMS 预导入命令 apply-rehearsal 非写库演练报告

生成时间：2026-07-07T19:07:15
命令模式：apply-rehearsal
结论：blocked
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
- field_catalog_templates: 0
- field_catalog_fields: 0
- release_plan_objects: 0
- release_plan_training_items: 0
- release_execution_templates: 0
- release_execution_fields: 0
- release_execution_trial_instances: 0
- manual_revision_gates: 9
- manual_revision_decisions: 5
- manual_revision_pending_decisions: 5
- findings: 2

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
- field_catalog_status: not_provided
- field_catalog_templates: 0
- field_catalog_fields: 0
- field_catalog_human_confirmation_fields: 0
- release_plan_status: not_provided
- release_plan_objects: 0
- release_plan_training_items: 0
- release_plan_release_allowed_now: 0
- release_execution_status: not_provided
- release_execution_templates: 0
- release_execution_fields: 0
- release_execution_trial_instances: 0
- manual_revision_status: passed
- manual_revision_target_doc_number: XZTC/SC
- manual_revision_pending_human_decisions: 5
- manual_revision_existing_route: existing_document_revision_required

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

## 发现项

- [high] human_review_pack_not_approved：人工评审包尚未全部通过：pending=67，unapproved=0。
- [high] manual_revision_human_decisions_pending：质量手册修订/换版路径仍有人工决策未关闭：pending=5。

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
- jewelry-qms 建设中系统只进入实施计划、演练和治理准备材料，不写入质量手册正文。
