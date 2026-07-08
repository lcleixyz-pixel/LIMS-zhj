# governance_closure_execution_pack

用途：把治理关闭工作台转成可分派、可签核、可回填的闭环执行包。

## 文件

- `governance_closure_execution_manifest.json`: manifest
- `00-治理闭环执行包总览.md`: overview
- `01-闭环执行批次.csv`: execution_batches
- `02-岗位签核页模板.csv`: signature_register
- `03-交接复核清单.csv`: handoff_checklist
- `04-回填路径索引.csv`: route_index
- `05-阻断批次摘要.md`: blocking_summary
- `README.md`: readme

## 使用顺序

1. 先看 `01-闭环执行批次.csv`，按 owner_role 和 gate_id 分派任务。
2. 在 `02-岗位签核页模板.csv` 中补齐真实责任人、复核人和完成日期。
3. 依据 `03-交接复核清单.csv` 回填治理关闭工作台的证据和拟关闭意见。
4. 用 `04-回填路径索引.csv` 核对每个 closure_item_id 对应的回填路径。
5. 重新生成 `governance_closure_decision_preview/` 和 `governance_readiness_refresh_preview/`。

## 边界

- 本执行包只读取 governance_closure_workbench 生成批次、签核和回填路径，不写数据库。
- 本执行包不修改 governance_closure_workbench、governance_closure_decision_preview、governance_readiness_dashboard 或任何现用 Word 文件。
- 本执行包不代表人工评审通过，不代表真实培训完成，不代表受控发布，不代表正式写库授权。
- 所有签核、交接复核和回填路径默认 pending；只有人工填写责任人、复核人和日期后才可进入关闭意见预览。
- 资质状态口径：已取得 CMA，CNAS 申请中；不得表述为已取得 CNAS。
- 以 LIMS 当前导出的 2022 程序清单作为现行程序目录。
- jewelry-qms 仍为建设中系统，只进入实施计划、演练和治理准备材料，不写入质量手册正文。

## 当前状态

- readiness: blocked_by_unsigned_execution
- pending_signature_rows: 50
- pending_handoff_checks: 60
- pending_route_items: 396
