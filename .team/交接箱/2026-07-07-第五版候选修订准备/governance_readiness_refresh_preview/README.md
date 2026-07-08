# governance_readiness_refresh_preview

用途：读取治理就绪总览和治理关闭意见回填预览，生成不写库、不回写源文件的总闸门刷新预览。

## 文件

- `governance_readiness_refresh_preview_manifest.json`：manifest
- `00-治理就绪刷新预览总览.md`：overview
- `01-总闸门刷新预览.csv`：gate_refresh_preview
- `02-人工任务刷新预览.csv`：task_refresh_preview
- `03-仍阻断任务清单.csv`：blocking_tasks
- `04-刷新差异摘要.csv`：change_summary
- `README.md`：readme

## 使用边界

- 本预览包只读取 governance_readiness_dashboard 和 governance_closure_decision_preview 推导刷新结果，不写数据库。
- 本预览包不修改 governance_readiness_dashboard、governance_closure_workbench、governance_closure_decision_preview、人工评审包、第二阶段复核工作台或任何现用 Word 文件。
- 本预览包不代表人工评审通过，不代表真实培训完成，不代表受控发布，不代表正式写库授权。
- 只有治理关闭意见预览中 accepted_for_preview 的任务才会在本预览中模拟关闭；其它 pending、空白、缺证据或仍阻断项保持阻断。
- 资质状态口径：已取得 CMA，CNAS 申请中；不得表述为已取得 CNAS。
- 以 LIMS 当前导出的 2022 程序清单作为现行程序目录。
- jewelry-qms 仍为建设中系统，只进入实施计划、演练和治理准备材料，不写入质量手册正文。

## 初始状态

- task_preview_rows: 396
- accepted_task_closures: 0
- refreshed_blocking_tasks: 392
- 当前预览不会替代真实人工关闭、真实培训、受控发布或正式写库授权。
