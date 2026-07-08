# LIMS 预导入命令 apply 闸门报告

生成时间：2026-07-07T06:09:22
命令模式：apply
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
- review_pack_items: 0
- review_pack_pending: 0
- findings: 1

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

## 发现项

- [high] ack_human_review_required：正式写入前必须显式提供 --ack-human-reviewed。

## 边界

- dry-run 不写数据库。
- apply 需要 --apply 与 --ack-human-reviewed 同时出现。
- apply 还需要 --review-dir 指向已全部通过的人工评审包。
- reference_existing_current 程序文件只做匹配，不自动创建为已发布文件。
- 本命令不创建真实 record_form_instances 运行记录。
- 结构化块、块级追溯关系仍作为后续受控治理步骤，不在本命令第一阶段写入。
