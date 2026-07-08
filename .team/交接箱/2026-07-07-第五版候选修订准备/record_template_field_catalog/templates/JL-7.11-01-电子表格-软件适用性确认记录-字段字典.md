# JL-7.11-01 电子表格/软件适用性确认记录字段字典

文件状态：候选记录模板字段字典，不写数据库，不代表受控发布。

## 模板信息

- 适用条款：7.11
- 关联程序：XZTC/CX-08-2022；XZTC/CX-19-2022；XZTC/CX-26-2022
- 填写责任：技术负责人/文件管理员
- 形成时机：模板或软件投入使用及变更前
- 字段总数：18
- 需人工确认字段：18

## 字段明细

| field_order | field_key | field_label | field_type | required | field_group | trial_value | human_confirmation_required | review_focus |
|---|---|---|---|---|---|---|---|---|
| 1 | record_number | 记录编号 | text | yes | common | SIM-JL-7.11-01-20260707-001 | yes | 候选字段需文件管理员确认 |
| 2 | record_name | 记录名称 | text | yes | common | 电子表格/软件适用性确认记录 | yes | 候选字段需文件管理员确认 |
| 3 | applicable_clause | 适用条款 | text | yes | common | 7.11 | yes | 候选字段需文件管理员确认 |
| 4 | related_procedure | 关联程序 | text | yes | common | XZTC/CX-08-2022；XZTC/CX-19-2022；XZTC/CX-26-2022 | yes | 候选字段需文件管理员确认 |
| 5 | responsible_position | 填写责任 | text | yes | common | 技术负责人/文件管理员 | yes | 候选字段需文件管理员确认 |
| 6 | trigger_time | 形成时机 | text | yes | common | 模板或软件投入使用及变更前 | yes | 候选字段需文件管理员确认 |
| 7 | reviewer | 复核/批准 | text | no | common | 待人工确认 | yes | 需确认保存/保密/签核规则；试填值仍为待确认；候选字段需文件管理员确认 |
| 8 | storage_location | 保存位置 | text | no | common | 待人工确认 | yes | 需确认保存/保密/签核规则；试填值仍为待确认；候选字段需文件管理员确认 |
| 9 | retention_period | 保存期限 | text | no | common | 待人工确认 | yes | 需确认保存/保密/签核规则；试填值仍为待确认；候选字段需文件管理员确认 |
| 10 | confidentiality_level | 保密等级 | select | no | common | 待确认 | yes | 需确认保存/保密/签核规则；试填值仍为待确认；候选字段需文件管理员确认 |
| 11 | correction_rule | 更正规则 | text | yes | common | 保留原始信息、更正原因、更正日期、责任人和复核痕迹；本试填为模拟数据，不作为正式更正规则。SIMULATED_TRIAL_NOT_REAL_RECORD | yes | 候选字段需文件管理员确认 |
| 12 | specific_01_1be7ae4f | 名称 | text | yes | specific | 模拟：名称待按真实业务填写。 | yes | 需过程负责人确认字段含义和现用表单一致性 |
| 13 | specific_02_7c49c8f2 | 用途 | text | yes | specific | 模拟：用途待按真实业务填写。 | yes | 需过程负责人确认字段含义和现用表单一致性 |
| 14 | specific_03_989d1aff | 版本 | text | yes | specific | 模拟：版本待按真实业务填写。 | yes | 需过程负责人确认字段含义和现用表单一致性 |
| 15 | specific_04_148da7ca | 验证方法 | text | yes | specific | 模拟：验证方法待按真实业务填写。 | yes | 需过程负责人确认字段含义和现用表单一致性 |
| 16 | specific_05_560165a6 | 权限 | text | yes | specific | 模拟：权限待按真实业务填写。 | yes | 需过程负责人确认字段含义和现用表单一致性 |
| 17 | specific_06_2aadd3e0 | 备份 | text | yes | specific | 模拟：备份待按真实业务填写。 | yes | 需过程负责人确认字段含义和现用表单一致性 |
| 18 | specific_07_32b3f16d | 结论 | text | yes | specific | 模拟：结论待按真实业务填写。 | yes | 需过程负责人确认字段含义和现用表单一致性 |

## 评审提示

- `record_number`：候选字段需文件管理员确认
- `record_name`：候选字段需文件管理员确认
- `applicable_clause`：候选字段需文件管理员确认
- `related_procedure`：候选字段需文件管理员确认
- `responsible_position`：候选字段需文件管理员确认
- `trigger_time`：候选字段需文件管理员确认
- `reviewer`：需确认保存/保密/签核规则；试填值仍为待确认；候选字段需文件管理员确认
- `storage_location`：需确认保存/保密/签核规则；试填值仍为待确认；候选字段需文件管理员确认
- `retention_period`：需确认保存/保密/签核规则；试填值仍为待确认；候选字段需文件管理员确认
- `confidentiality_level`：需确认保存/保密/签核规则；试填值仍为待确认；候选字段需文件管理员确认
- `correction_rule`：候选字段需文件管理员确认
- `specific_01_1be7ae4f`：需过程负责人确认字段含义和现用表单一致性
- `specific_02_7c49c8f2`：需过程负责人确认字段含义和现用表单一致性
- `specific_03_989d1aff`：需过程负责人确认字段含义和现用表单一致性
- `specific_04_148da7ca`：需过程负责人确认字段含义和现用表单一致性
- `specific_05_560165a6`：需过程负责人确认字段含义和现用表单一致性
- `specific_06_2aadd3e0`：需过程负责人确认字段含义和现用表单一致性
- `specific_07_32b3f16d`：需过程负责人确认字段含义和现用表单一致性

## 边界

- 本字段字典包只用于候选记录模板评审和 LIMS 字段配置准备，不写数据库。
- 本字段字典包不代表受控发布，也不代表真实记录已经形成。
- 字段默认值、保存期限、保密等级、签核规则和 05-02 归属仍需人工评审确认。
- 试填值来自模拟试填包，只验证字段可填性，不得作为真实运行记录导入。

字段明细 CSV 汇总：`02-字段级明细.csv`
