# LIMS 治理动作矩阵

本矩阵只描述治理路径，不写数据库，不代表受控发布。

## 对象概览

- candidate_manual: 1
- candidate_record_template_document: 26
- current_procedure_reference: 37
- numbered_attachment_form_pending: 1

## 治理阶段

| governance_stage | lims_action | required_gate | write_boundary |
|---|---|---|---|
| 候选准备 | 预导入包和结构化关系 dry-run | 自动验证 findings=0；人工评审仍 pending | 不写数据库 |
| 人工评审 | human_review_pack 与 workbench 供线下审查 | 67 个人审项逐项回填并经预览校验 | 不修改 human_review_pack 原始清单 |
| 受控发布 | 仅在批准后建立受控文件状态 | 审批、培训、旧版处置均完成 | 未获授权不得 apply |
| 实施有效性 | 抽查记录、权限、备份、审计追踪和纠正措施闭环 | 试运行证据和内审/管理评审输入 | jewelry-qms 未确认前不得写入手册正文 |

## 口径闸门

| gate_id | topic | expected_position | status | blocking_if_failed |
|---|---|---|---|---|
| POS-01 | 资质状态 | 已取得 CMA，CNAS 申请中 | locked | yes |
| POS-02 | 现行程序目录 | 以 LIMS 当前导出的 2022 程序清单作为现行程序目录 | locked | yes |
| POS-03 | jewelry-qms 手册正文边界 | 建设中系统，不写入手册正文 | locked | yes |
| POS-04 | 受控发布状态 | 当前全部仍为候选/演练，不代表批准发布 | locked | yes |
| POS-05 | 数据库写入边界 | 本包和 dry-run 均不写数据库 | locked | yes |
