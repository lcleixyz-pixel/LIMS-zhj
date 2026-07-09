# LIMS 写库行级预览包

文件状态：只读预览包，不写数据库，不代表人工评审通过或正式写库授权。

## 阅读顺序

1. `04-write-preview-summary.md`：先看总计数和边界。
2. `01-documents-draft-preview.csv`：核对第一阶段 `documents` 草稿行。
3. `02-record-form-templates-draft-preview.csv`：核对 26 个候选记录模板的目标字段、schema 摘要和关联文件解析。
4. `03-qms-sources-upsert-preview.csv`：核对 4 条外来依据的 upsert 预览。

## 关键解释

- `create_draft` 表示正式 apply 时可能创建 draft 行，但本预览不创建。
- `plan_existing_document_revision` 表示同编号既有受控文件已存在，候选稿需走既有文件修订/换版治理路线，不能按新增草稿处理。
- `skip_reference_existing_current` 表示 2022 现行程序只做引用匹配，不自动重建为新文件。
- `candidate_document_created_same_apply` 表示记录模板的配套 `documents` 行会在同一阶段作为候选草稿创建。
- `qms_uuid_at_apply_time` 表示真实主键只会在正式 apply 事务内生成。

## 边界

- 本包只预览 LIMS 第一阶段可能写入的表和字段，不写数据库。
- 本包不代表人工评审通过、受控发布或正式写库授权。
- 真实 apply 仍必须使用正式 human_review_pack 且经用户明确授权。
- 结构化文件、手册块、追溯关系和真实运行记录仍不在第一阶段写入范围内。
- 资质状态口径：已取得 CMA，CNAS 申请中；不得表述为已取得 CNAS。
- jewelry-qms 仍为建设中系统，只进入实施计划、演练和治理准备材料，不写入质量手册正文。
