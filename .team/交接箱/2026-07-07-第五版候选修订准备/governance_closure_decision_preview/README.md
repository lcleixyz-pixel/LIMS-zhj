# governance_closure_decision_preview

用途：读取治理关闭工作台中的拟关闭意见和证据采集字段，生成不写库的关闭回填预览。

## 文件

- `governance_closure_decision_preview_manifest.json`：manifest
- `00-治理关闭意见回填预览总览.md`：overview
- `01-拟关闭决策预览.csv`：decision_preview
- `02-仍阻断关闭项.csv`：blocking_items
- `03-按闸门关闭统计.csv`：gate_summary
- `README.md`：readme

## 使用边界

- 本预览包只读取 governance_closure_workbench 中的证据采集和拟关闭回填意见，不写数据库。
- 本预览包不修改 governance_closure_workbench、governance_readiness_dashboard、人工评审包、第二阶段复核工作台或任何现用 Word 文件。
- 本预览包不代表人工评审通过，不代表真实培训完成，不代表受控发布，不代表正式写库授权。
- 所有空白、pending、rejected、缺证据、缺意见、缺复核人或缺日期的阻断项保持阻断。
- 资质状态口径：已取得 CMA，CNAS 申请中；不得表述为已取得 CNAS。
- 以 LIMS 当前导出的 2022 程序清单作为现行程序目录。
- jewelry-qms 仍为建设中系统，只进入实施计划、演练和治理准备材料，不写入质量手册正文。

## 初始状态

- decision_items: 396
- blocking_items: 392
- 任何真实关闭都需要人工填写，不能由本脚本自动确认。
