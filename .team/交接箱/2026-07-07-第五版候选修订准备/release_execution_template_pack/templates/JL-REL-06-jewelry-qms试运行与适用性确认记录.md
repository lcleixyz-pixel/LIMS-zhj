# JL-REL-06 jewelry-qms试运行与适用性确认记录

文件状态：发布执行记录候选模板，不写数据库，不代表受控发布或真实记录形成。

## 模板信息

- 适用条款：7.11、8.3、8.4
- 关联程序：XZTC/CX-08-2022；XZTC/CX-19-2022；XZTC/CX-26-2022
- 填写责任：质量负责人/系统管理员/文件管理员
- 形成时机：jewelry-qms 试运行、功能变更或拟纳入体系运行前
- 来源演练文件：07-实施有效性检查清单.csv
- 来源演练行数：4

## 字段与模拟试填

| order | key | label | type | required | trial_value | review_focus |
|---|---|---|---|---|---|---|
| 1 | record_number | 记录编号 | text | yes | SIM-JL-REL-06-20260707-001 | 通用治理字段，正式启用前需由文件管理员确认。 |
| 2 | record_name | 记录名称 | text | yes | jewelry-qms试运行与适用性确认记录 | 通用治理字段，正式启用前需由文件管理员确认。 |
| 3 | applicable_clause | 适用条款 | text | yes | 7.11、8.3、8.4 | 通用治理字段，正式启用前需由文件管理员确认。 |
| 4 | related_procedure | 关联程序 | text | yes | XZTC/CX-08-2022；XZTC/CX-19-2022；XZTC/CX-26-2022 | 通用治理字段，正式启用前需由文件管理员确认。 |
| 5 | responsible_position | 填写责任 | text | yes | 质量负责人/系统管理员/文件管理员 | 通用治理字段，正式启用前需由文件管理员确认。 |
| 6 | trigger_time | 形成时机 | text | yes | jewelry-qms 试运行、功能变更或拟纳入体系运行前 | 通用治理字段，正式启用前需由文件管理员确认。 |
| 7 | reviewer | 复核/批准 | text | no | 待人工确认 | 通用治理字段，正式启用前需由文件管理员确认。 |
| 8 | approval_status | 审批/确认状态 | select | yes | pending; SIMULATED_TRIAL_NOT_REAL_RECORD | 通用治理字段，正式启用前需由文件管理员确认。 |
| 9 | evidence_reference | 证据位置 | text | yes | 07-实施有效性检查清单.csv | 通用治理字段，正式启用前需由文件管理员确认。 |
| 10 | storage_location | 保存位置 | text | no | 待人工确认 | 通用治理字段，正式启用前需由文件管理员确认。 |
| 11 | retention_period | 保存期限 | text | no | 待人工确认 | 通用治理字段，正式启用前需由文件管理员确认。 |
| 12 | confidentiality_level | 保密等级 | select | no | 待确认 | 通用治理字段，正式启用前需由文件管理员确认。 |
| 13 | correction_rule | 更正规则 | text | yes | 保留原始信息、更正原因、更正日期、责任人和复核痕迹。 | 通用治理字段，正式启用前需由文件管理员确认。 |
| 14 | not_real_record_marker | 非真实记录标识 | text | yes | SIMULATED_TRIAL_NOT_REAL_RECORD | 通用治理字段，正式启用前需由文件管理员确认。 |
| 15 | system_function | 系统功能 | text | yes | 模拟：系统功能待按真实发布执行填写。SIMULATED_TRIAL_NOT_REAL_RECORD | 需人工确认字段含义、填写责任、保存和复核要求。 |
| 16 | validation_scope | 适用性确认范围 | text | yes | 模拟：适用性确认范围待按真实发布执行填写。SIMULATED_TRIAL_NOT_REAL_RECORD | 需人工确认字段含义、填写责任、保存和复核要求。 |
| 17 | access_control_check | 权限控制检查 | text | yes | 模拟：权限控制检查待按真实发布执行填写。SIMULATED_TRIAL_NOT_REAL_RECORD | 需人工确认字段含义、填写责任、保存和复核要求。 |
| 18 | audit_trail_check | 审计追踪检查 | text | yes | 模拟：审计追踪检查待按真实发布执行填写。SIMULATED_TRIAL_NOT_REAL_RECORD | 需人工确认字段含义、填写责任、保存和复核要求。 |
| 19 | backup_restore_check | 备份恢复检查 | text | yes | 模拟：备份恢复检查待按真实发布执行填写。SIMULATED_TRIAL_NOT_REAL_RECORD | 需人工确认字段含义、填写责任、保存和复核要求。 |
| 20 | go_live_recommendation | 纳入体系运行建议 | select | yes | 模拟：纳入体系运行建议待按真实发布执行填写。SIMULATED_TRIAL_NOT_REAL_RECORD | 需人工确认字段含义、填写责任、保存和复核要求。 |

## 边界

- 本包仅用于受控发布执行记录模板评审和 LIMS 字段配置准备，不写数据库。
- 本包不代表第五版候选稿、记录模板、培训记录、旧版处置或 jewelry-qms 已经批准、受控发布或正式运行。
- 模拟试填均带 SIMULATED_TRIAL_NOT_REAL_RECORD 标识，不得作为真实运行记录。
- 资质状态按已取得 CMA、CNAS 申请中处理；不得写成已取得 CNAS。
- jewelry-qms 仍为建设中系统，仅在实施计划、试运行和适用性确认模板中出现，不写入质量手册正文。
