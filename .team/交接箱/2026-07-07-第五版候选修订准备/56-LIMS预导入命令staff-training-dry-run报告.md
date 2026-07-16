# LIMS 预导入命令 dry-run 报告

生成时间：2026-07-07T19:28:49
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
- review_pack_items: 0
- review_pack_pending: 0
- field_catalog_templates: 0
- field_catalog_fields: 0
- release_plan_objects: 0
- release_plan_training_items: 0
- release_execution_templates: 0
- release_execution_fields: 0
- release_execution_trial_instances: 0
- manual_revision_gates: 0
- manual_revision_decisions: 0
- manual_revision_pending_decisions: 0
- staff_training_source_items: 29
- staff_training_tasks: 88
- staff_training_questions: 12
- staff_training_feedback_rows: 8
- staff_training_pending_tasks: 88
- staff_training_pending_questions: 12
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
- review_pack_status: not_provided
- review_pack_pending_decisions: 0
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
- manual_revision_status: not_provided
- manual_revision_target_doc_number:
- manual_revision_pending_human_decisions: 0
- manual_revision_existing_route:
- staff_training_status: passed
- staff_training_source_items: 29
- staff_training_tasks: 88
- staff_training_questions: 12
- staff_training_pending_tasks: 88
- staff_training_pending_questions: 12
- staff_training_blank_feedback_decisions: 8

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
- source_release_training_items: 0

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
- jewelry-qms 建设中系统只进入实施计划、演练和治理准备材料，不写入质量手册正文。
