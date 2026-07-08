# QMS 治理就绪刷新预览生成报告

生成时间：2026-07-07T22:08:55
来源总览：`.team/交接箱/2026-07-07-第五版候选修订准备/governance_readiness_dashboard`
来源关闭预览：`.team/交接箱/2026-07-07-第五版候选修订准备/governance_closure_decision_preview`
结论：governance_readiness_refresh_preview_no_database_write
readiness：blocked_by_refresh_open_items
ready_for_lims_apply：no

## 计数

- gate_rows: 13
- task_preview_rows: 396
- accepted_task_closures: 0
- refreshed_blocking_tasks: 392
- refreshed_blocking_gates: 11
- database_write_performed: 0

## 边界

- 本预览包只读取 governance_readiness_dashboard 和 governance_closure_decision_preview 推导刷新结果，不写数据库。
- 本预览包不修改 governance_readiness_dashboard、governance_closure_workbench、governance_closure_decision_preview、人工评审包、第二阶段复核工作台或任何现用 Word 文件。
- 本预览包不代表人工评审通过，不代表真实培训完成，不代表受控发布，不代表正式写库授权。
- 只有治理关闭意见预览中 accepted_for_preview 的任务才会在本预览中模拟关闭；其它 pending、空白、缺证据或仍阻断项保持阻断。
- 资质状态口径：已取得 CMA，CNAS 申请中；不得表述为已取得 CNAS。
- 以 LIMS 当前导出的 2022 程序清单作为现行程序目录。
- jewelry-qms 仍为建设中系统，只进入实施计划、演练和治理准备材料，不写入质量手册正文。
