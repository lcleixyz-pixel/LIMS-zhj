# LIMS 预导入命令 dry-run 报告

生成时间：2026-07-07T11:49:05
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

## 人工评审包

- review_dir: /workspace/.team/交接箱/2026-07-07-第五版候选修订准备/human_review_pack
- status: pending
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
