# LIMS 预导入包

生成时间：2026-07-09T04:42:08
状态：预导入草案，不写数据库，不代表受控发布。

## 包内容

- 文件控制预导入：40 条
- 结构化文件预导入：40 条
- 记录模板预导入：1 条
- 条款追溯矩阵：1 条
- 手册块级索引：1 条
- 外来依据台账候选：4 条
- LIMS 2022 清单项：38 个（程序 37 个，编号附件/表单 1 个）

## 使用边界

1. 本包是给 LIMS 治理导入前评审用的 CSV/JSON，不是数据库迁移脚本。
2. 现有 `ImportService` 只能直接处理基础文档 CSV；记录模板、结构化文件和块级追溯仍需后续开发或人工确认后导入。
3. 所有记录模板字段均为候选 schema，正式启用前必须与现用表单或试填结果比对。
4. 质量手册第五版仍为候选草案，不能作为现行受控文件执行。

## 文件清单

- `documents_preimport.csv`：documents
- `structured_documents_preimport.csv`：structured_documents
- `record_form_templates_preimport.csv`：record_form_templates
- `traceability_matrix_preimport.csv`：traceability_matrix
- `manual_blocks_preimport.csv`：manual_blocks
- `external_sources_preimport.csv`：external_sources
- `preimport_manifest.json`：包元数据和计数。
