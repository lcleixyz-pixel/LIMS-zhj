# LIMS 命令复核清单

本清单用于后续复跑 `qms:preimport-package` 时确认所有治理包都被命令层读取。当前仍不得正式 apply。

## 建议 dry-run 参数

- `--review-dir human_review_pack`
- `--field-catalog-dir record_template_field_catalog`
- `--release-plan-dir controlled_release_rehearsal`
- `--release-execution-dir release_execution_template_pack`
- `--manual-revision-dir manual_revision_path_pack`
- `--staff-training-dir staff_training_implementation_pack`
- `--stage2-review-dir stage2_structured_review_workbench`
- `--stage2-review-preview-dir stage2_structured_review_decision_preview`
- `--governance-readiness-dir governance_readiness_dashboard`
- `--stage2-check`

## 期望状态

- governance_readiness_status：passed
- governance_readiness_ready_for_lims_apply：no
- governance_readiness_blocking_tasks：392
- dry-run 可以通过结构检查，但 apply/apply-rehearsal 应在阻断任务未关闭时返回 blocked。

## 不允许事项

- 本包只汇总现有候选文件、模板、评审、发布演练、学习实施和第二阶段复核状态，不写数据库。
- 本包不修改 human_review_pack、stage2_structured_review_workbench 或任何现用 Word 文件。
- 本包不代表人工评审通过、真实培训完成、受控发布或正式写库授权。
- 资质状态口径：已取得 CMA，CNAS 申请中；不得表述为已取得 CNAS。
- LIMS 当前导出的 2022 程序清单仍作为现行程序目录。
- jewelry-qms 仍为建设中系统，只进入实施计划、演练和治理准备材料，不写入质量手册正文。
