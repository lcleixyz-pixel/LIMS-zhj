# LIMS 第二阶段结构化导入行级预览包

文件状态：只读预览包，不写数据库，不代表第二阶段已导入、人工评审通过或正式写库授权。

## 阅读顺序

1. `04-stage2-preview-summary.md`：先看总计数、依赖和边界。
2. `01-structured-documents-preview.csv`：核对 `qms_structured_documents` 结构化文件预览。
3. `02-document-blocks-preview.csv`：核对 `qms_document_blocks` 手册块预览。
4. `03-document-block-links-preview.csv`：核对 `qms_document_block_links` 到程序、附件/表单、记录模板的链接预览。

## 关键解释

- `write_now=no` 表示本包只展示将来可能动作，不执行写库。
- `plan_manual_structured_refresh_after_revision` 表示质量手册第五版候选稿必须先走既有 `XZTC/SC` 修订/换版路径。
- `candidate_record_template_after_phase1_apply` 表示记录模板链接依赖第一阶段候选模板写入且人工评审通过。
- `manual_revision_human_decision_required` 表示该行仍受质量手册修订/换版人工决策约束。
- `qms_uuid_at_stage2_apply_time` 表示真实主键只会在未来第二阶段正式 apply 事务内生成。

## 边界

- 本包只预览 LIMS 第二阶段结构化导入可能写入的表和字段，不写数据库。
- 本包不代表第二阶段已导入，不代表人工评审通过、受控发布或正式写库授权。
- 第二阶段必须先完成人工评审、第一阶段文件/模板写入、质量手册修订/换版路径确认和人员学习实施确认。
- 真实 apply 仍必须使用正式 human_review_pack 且经用户明确授权。
- 资质状态口径：已取得 CMA，CNAS 申请中；不得表述为已取得 CNAS。
- jewelry-qms 仍为建设中系统，只进入实施计划、演练和治理准备材料，不写入质量手册正文。
