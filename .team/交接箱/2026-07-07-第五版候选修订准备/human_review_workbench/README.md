# 人工评审工作台总览

本工作台只用于人工评审准备，不写数据库，不代表审核批准。

## 当前状态

- 总决策项：67
- 条款评审：29
- 记录模板评审：26
- 05-02 归属判定：1
- apply 前闸门：11
- 当前 pending：67
- 可分配角色数：10

## 建议评审节奏

1. 先由文件管理员核对候选手册、修订说明、程序目录和文件控制边界。
2. 再由质量负责人/最高管理者核对 4.1、4.2、5、8 章相关条款和职责。
3. 再由技术负责人/过程负责人核对 6、7 章条款、记录模板字段字典和试填样表。
4. 单独确认 `XZTC/CX-05-02-2022` 的归属。
5. 先把线下意见整理到 `decision_update_template.csv` 的 `new_human_decision` 和 `review_comment`。
6. 运行或委托 Codex 运行 `human_review_decision_preview/` 回填预览，确认没有非法决策、缺少说明或 05-02 语义冲突。
7. 预览通过并经用户明确授权后，才考虑把正式意见回填到 `human_review_pack/`，再运行 LIMS dry-run 和 apply 闸门验证。

## 本工作台文件

- `00-人工评审总览.md`：overview
- `01-按角色评审清单.md`：role_checklist
- `02-条款评审工作台.md`：clause_workbench
- `03-记录模板评审工作台.md`：template_workbench
- `04-05-02归属判定工作台.md`：attachment_workbench
- `05-apply前闸门工作台.md`：gate_workbench
- `decision_update_template.csv`：decision_template
- `workbench_manifest.json`：manifest
