# LIMS 修订动作说明

文件状态：候选治理准备材料；不写数据库，不代表人工评审通过、受控发布或写库授权。

本说明只用于人工评审和后续开发/执行确认，不触发任何写库。`XZTC/SC` 第五版候选稿不得按同编号新增草稿直接写入，后续应确认既有文件修订/换版路径。资质状态仍为已取得 CMA，CNAS 申请中。

## 可用系统路径

- `documents` 表已有 `version`、`revision`、`status`、`change_reason`、`publish` 字段，可承接既有文件修订状态。
- `document_revisions` 表可保存修订前版本、修订号、文件路径、文件名和变更原因。
- `Document::revise` 当前会在事务中创建 `document_revisions` 快照，再把既有文件更新为 draft 修订版本。
- 修订后可调用 `QmsDocumentStructureService::refreshControlledDocumentStructure` 刷新结构化文件草稿，但后续块级追溯仍应人工复核。

## 行级动作

| action_id | target_table_or_module | action_type | blocked_by | expected_record_effect_after_authorized_revision |
|---|---|---|---|---|
| ACT-01 | documents | update_existing_document_after_authorization | human_review_pending; revision_path_pending; user_authorization_required | existing XZTC/SC set to draft with new version/revision and change_reason |
| ACT-02 | document_revisions | archive_previous_version_snapshot | human_review_pending; user_authorization_required | previous version, revision, file_path, file_name and change_reason preserved |
| ACT-03 | qms_structured_documents | refresh_structured_document_as_draft | human_review_pending; manual_revision_authorization_required | structured quality manual draft refreshed for later block and trace review |
| ACT-04 | qms_document_blocks/qms_document_block_links | deferred_traceability_refresh | human_review_pending; stage2_manual_review_required | manual blocks and trace links reviewed before controlled use |
| ACT-05 | document_distributions/document_reviews/approval evidence | controlled_release_evidence_after_approval | controlled_release_not_approved | approval, distribution, training and old-version disposition evidence retained |

## 不允许事项

- 不允许把 `XZTC/SC` 第五版候选稿作为同编号新增草稿或同编号新增 `documents` draft 行直接写入。
- 不允许在真实人工评审和用户授权前更新既有 `documents` 记录。
- 不允许把本包中的路径预览当成受控发布、培训完成或旧版作废证据。
