# JL-7.2-01 方法验证/确认记录字段字典

文件状态：候选记录模板字段字典，不写数据库，不代表受控发布。

## 模板信息

- 适用条款：7.2
- 关联程序：XZTC/CX-22-2022；XZTC/CX-23-2022；XZTC/CX-24-2022；XZTC/CX-27-2022
- 填写责任：技术负责人
- 形成时机：方法投入使用、变更或扩项前
- 字段总数：17
- 需人工确认字段：17

## 字段明细

| field_order | field_key | field_label | field_type | required | field_group | trial_value | human_confirmation_required | review_focus |
|---|---|---|---|---|---|---|---|---|
| 1 | record_number | 记录编号 | text | yes | common | SIM-JL-7.2-01-20260707-001 | yes | 候选字段需文件管理员确认 |
| 2 | record_name | 记录名称 | text | yes | common | 方法验证/确认记录 | yes | 候选字段需文件管理员确认 |
| 3 | applicable_clause | 适用条款 | text | yes | common | 7.2 | yes | 候选字段需文件管理员确认 |
| 4 | related_procedure | 关联程序 | text | yes | common | XZTC/CX-22-2022；XZTC/CX-23-2022；XZTC/CX-24-2022；XZTC/CX-27-2022 | yes | 候选字段需文件管理员确认 |
| 5 | responsible_position | 填写责任 | text | yes | common | 技术负责人 | yes | 候选字段需文件管理员确认 |
| 6 | trigger_time | 形成时机 | text | yes | common | 方法投入使用、变更或扩项前 | yes | 候选字段需文件管理员确认 |
| 7 | reviewer | 复核/批准 | text | no | common | 待人工确认 | yes | 需确认保存/保密/签核规则；试填值仍为待确认；候选字段需文件管理员确认 |
| 8 | storage_location | 保存位置 | text | no | common | 待人工确认 | yes | 需确认保存/保密/签核规则；试填值仍为待确认；候选字段需文件管理员确认 |
| 9 | retention_period | 保存期限 | text | no | common | 待人工确认 | yes | 需确认保存/保密/签核规则；试填值仍为待确认；候选字段需文件管理员确认 |
| 10 | confidentiality_level | 保密等级 | select | no | common | 待确认 | yes | 需确认保存/保密/签核规则；试填值仍为待确认；候选字段需文件管理员确认 |
| 11 | correction_rule | 更正规则 | text | yes | common | 保留原始信息、更正原因、更正日期、责任人和复核痕迹；本试填为模拟数据，不作为正式更正规则。SIMULATED_TRIAL_NOT_REAL_RECORD | yes | 候选字段需文件管理员确认 |
| 12 | specific_01_b77e2ac8 | 方法 | text | yes | specific | 模拟：方法待按真实业务填写。 | yes | 需过程负责人确认字段含义和现用表单一致性 |
| 13 | specific_02_baabf9e0 | 适用范围 | text | yes | specific | 模拟：适用范围待按真实业务填写。 | yes | 需过程负责人确认字段含义和现用表单一致性 |
| 14 | specific_03_2662cbff | 验证项目 | text | yes | specific | 模拟：验证项目待按真实业务填写。 | yes | 需过程负责人确认字段含义和现用表单一致性 |
| 15 | specific_04_0a2c91ce | 结果 | text | yes | specific | 模拟：结果待按真实业务填写。 | yes | 需过程负责人确认字段含义和现用表单一致性 |
| 16 | specific_05_32b3f16d | 结论 | text | yes | specific | 模拟：结论待按真实业务填写。 | yes | 需过程负责人确认字段含义和现用表单一致性 |
| 17 | specific_06_62e26d21 | 批准 | text | yes | specific | 模拟：批准待按真实业务填写。 | yes | 需过程负责人确认字段含义和现用表单一致性 |

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
- `specific_01_b77e2ac8`：需过程负责人确认字段含义和现用表单一致性
- `specific_02_baabf9e0`：需过程负责人确认字段含义和现用表单一致性
- `specific_03_2662cbff`：需过程负责人确认字段含义和现用表单一致性
- `specific_04_0a2c91ce`：需过程负责人确认字段含义和现用表单一致性
- `specific_05_32b3f16d`：需过程负责人确认字段含义和现用表单一致性
- `specific_06_62e26d21`：需过程负责人确认字段含义和现用表单一致性

## 边界

- 本字段字典包只用于候选记录模板评审和 LIMS 字段配置准备，不写数据库。
- 本字段字典包不代表受控发布，也不代表真实记录已经形成。
- 字段默认值、保存期限、保密等级、签核规则和 05-02 归属仍需人工评审确认。
- 试填值来自模拟试填包，只验证字段可填性，不得作为真实运行记录导入。

字段明细 CSV 汇总：`02-字段级明细.csv`
