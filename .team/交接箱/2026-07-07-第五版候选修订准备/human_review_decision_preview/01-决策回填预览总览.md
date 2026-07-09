# LIMS 人工评审决策回填预览总览

本文件用于检查人工评审意见将来能否安全回填；不写数据库，不修改 `human_review_pack/`，不能作为 `--review-dir`。

## 结论

- 预览状态：no_proposed_decisions
- 决策项总数：67
- 拟回填项：0
- 可满足 LIMS 通过语义的候选项：0
- 预览后仍阻断项：67
- 高风险填写问题：0
- 05-02 处置语义待确认项：0

## 边界

- 本预览包只校验 decision_update_template.csv 的拟回填意见，不写数据库。
- 本预览包不修改 human_review_pack/ 中的任何 human_decision。
- 本预览包不能作为 qms:preimport-package --review-dir 使用。
- 本预览包不代表第五版候选稿、记录模板或 LIMS 预导入包已经审核批准。
- 正式回填仍需要人工确认、受控修订记录和用户明确授权。

## 05-02 特别提示

`XZTC/CX-05-02-2022` 的人工输入不是普通 approved/pass，而是“程序附件、记录模板、历史附件保留、作废不导入、待补录为受控文件”等处置结论。预览包会提示该处置结论还需要决定如何映射到正式评审包，避免命令层继续把它视为未通过。

## 本预览包文件

- `01-决策回填预览总览.md`：overview
- `02-待处理与异常预览.md`：status_preview
- `03-源文件影响预览.md`：source_impact
- `decision_update_validation.csv`：validation_csv
- `review_pack_overlay_preview_not_for_import.csv`：overlay_preview
- `decision_preview_manifest.json`：manifest
- `README.md`：readme
