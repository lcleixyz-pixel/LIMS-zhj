# LIMS 第一阶段写库行级预览汇总

生成时间：2026-07-07T12:33:02
结论：write_preview_no_database_write
命令模式：apply-rehearsal

## 计数

- documents_preview_rows: 65
- documents_create_draft_rows: 27
- documents_revision_required_rows: 1
- documents_skip_reference_rows: 37
- record_template_preview_rows: 26
- record_template_create_draft_rows: 26
- source_preview_rows: 4
- source_create_rows: 4
- source_update_rows: 0

## 预览文件

- manifest: `write_preview_manifest.json`
- documents_preview: `01-documents-draft-preview.csv`
- record_templates_preview: `02-record-form-templates-draft-preview.csv`
- sources_preview: `03-qms-sources-upsert-preview.csv`
- summary: `04-write-preview-summary.md`
- readme: `README.md`

## 边界

- 本包只预览 LIMS 第一阶段可能写入的表和字段，不写数据库。
- 本包不代表人工评审通过、受控发布或正式写库授权。
- 真实 apply 仍必须使用正式 human_review_pack 且经用户明确授权。
- 结构化文件、手册块、追溯关系和真实运行记录仍不在第一阶段写入范围内。
- 资质状态口径：已取得 CMA，CNAS 申请中；不得表述为已取得 CNAS。
- jewelry-qms 仍为建设中系统，只进入实施计划、演练和治理准备材料，不写入质量手册正文。
