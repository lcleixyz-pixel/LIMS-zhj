# governance_closure_workbench

用途：把治理就绪总览中的人工任务转成可人工关闭、可证据回填、可由 LIMS 命令层识别的工作台。

## 文件

- `governance_closure_workbench_manifest.json`：manifest
- `00-治理关闭工作台总览.md`：overview
- `01-总闸门关闭矩阵.csv`：gate_closure_matrix
- `02-按角色任务包.csv`：role_task_pack
- `03-证据采集模板.csv`：evidence_template
- `04-拟关闭回填模板.csv`：closure_template
- `05-优先关闭批次.md`：priority_batches
- `README.md`：readme

## 使用边界

- 本工作台只读取 governance_readiness_dashboard 生成治理关闭和证据回填清单，不写数据库。
- 本工作台不修改 governance_readiness_dashboard、human_review_pack、stage2_structured_review_workbench 或任何现用 Word 文件。
- 本工作台不代表人工评审通过，不代表真实培训完成，不代表受控发布，不代表正式写库授权。
- 所有 closure_status 为空或 pending 的阻断项保持阻断；关闭必须由人工补齐证据、意见、复核人和日期。
- 资质状态口径：已取得 CMA，CNAS 申请中；不得表述为已取得 CNAS。
- 以 LIMS 当前导出的 2022 程序清单作为现行程序目录。
- jewelry-qms 仍为建设中系统，只进入实施计划、演练和治理准备材料，不写入质量手册正文。

## 初始状态

- closure_rows: 396
- open_blocking_items: 392
- 任何真实关闭都需要人工填写，不能由本脚本自动确认。
