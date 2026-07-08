# QMS 治理关闭工作台生成报告

生成时间：2026-07-07T21:46:53
来源总览：`.team/交接箱/2026-07-07-第五版候选修订准备/governance_readiness_dashboard`
结论：governance_closure_workbench_no_database_write
readiness：blocked_by_open_closures
ready_for_governance_readiness_refresh：no
ready_for_lims_apply：no

## 计数

- gate_rows: 13
- role_task_batches: 60
- evidence_rows: 396
- closure_rows: 396
- blocking_closure_items: 392
- open_blocking_items: 392
- accepted_closures: 0
- pending_closures: 396
- database_write_performed: 0

## 边界

- 本工作台只读取 governance_readiness_dashboard 生成治理关闭和证据回填清单，不写数据库。
- 本工作台不修改 governance_readiness_dashboard、human_review_pack、stage2_structured_review_workbench 或任何现用 Word 文件。
- 本工作台不代表人工评审通过，不代表真实培训完成，不代表受控发布，不代表正式写库授权。
- 所有 closure_status 为空或 pending 的阻断项保持阻断；关闭必须由人工补齐证据、意见、复核人和日期。
- 资质状态口径：已取得 CMA，CNAS 申请中；不得表述为已取得 CNAS。
- 以 LIMS 当前导出的 2022 程序清单作为现行程序目录。
- jewelry-qms 仍为建设中系统，只进入实施计划、演练和治理准备材料，不写入质量手册正文。
