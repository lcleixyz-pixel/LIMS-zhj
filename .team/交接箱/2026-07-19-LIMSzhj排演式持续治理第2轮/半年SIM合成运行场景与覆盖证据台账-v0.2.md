# LIMS-zhj 第二轮半年 SIM 合成运行场景与覆盖证据台账 v0.2

## 1. 当前状态

| 项目 | 状态 |
|---|---|
| 候选状态 | `ready_to_apply` |
| 演练批次 | `SIM-GOV-R2-20260719` |
| 目标环境 | 8013 主演练 |
| 8013 装载 | **未装载**；等待公共试运行接口代码质量复核和明确放行 |
| 对 v0.1 的关系 | 加法式 SIM 整改覆盖层；不删除、不覆盖冻结的 v0.1 原始证据 |
| 证据性质 | SIM 结构、流程、身份、状态和追溯关系；不主张真实技术结果 |

本候选针对 v0.1 独立复核发现的三个客观缺口：阶段字段可能超出冻结 schema、原始记录与报告结构不足、8013 的依据登记不完整且 5 项既有依据停留在 `due + published`。它同时把“不开展活动”从历史事件声明升级为真实的写前业务策略校验。

## 2. TDD 红灯和整改范围

实现前先建立两项失败证据：

- `qms_rehearsal_six_month_v02_fixture_contract_smoke.php` 因 v0.2 fixture 不存在而失败，日志：`/tmp/r2-v02-red-contract.log`；
- `qms_rehearsal_business_policy_smoke.php` 因写前策略校验器不存在而失败，日志：`/tmp/r2-v02-red-policy.log`。

随后仅新增演练夹具、演练策略校验器和测试，不修改产品业务代码，不向 8013 写入数据。

## 3. v0.2 候选组成

| 对象 | 路径 | 用途 |
|---|---|---|
| 加法式 fixture | `jewelry-qms/tests/fixtures/sim_rehearsal_six_month_run_v02.sql` | 以记录模板/实例注册依据覆盖层、更新 6 个阶段 schema、生成 96 个 v0.2 冻结实例及更正/报告状态历史 |
| 写前策略校验器 | `jewelry-qms/scripts/lib/RehearsalBusinessPolicyValidator.php` | 在持久化回调前拒绝 8 类禁止候选 |
| 策略单测 | `jewelry-qms/tests/qms_rehearsal_business_policy_smoke.php` | 证明 8 个禁止候选不调用持久化回调，合法 SIM 候选可通过 |
| 静态契约 | `jewelry-qms/tests/qms_rehearsal_six_month_v02_fixture_contract_smoke.php` | 检查文件、关键字段、依据身份和 INSERT/upsert 边界 |
| 运行验证草案 | `jewelry-qms/tests/qms_rehearsal_six_month_v02_runtime_smoke.php` | 待放行装载后验证 schema 子集、追溯结构、依据状态和数据库零变化 |

## 4. 阶段字段—schema 一致性

v0.2 的 6 个阶段分别使用独立 schema。每个运行实例必须满足：

> `array_keys(field_values) ⊆ array_column(template_field_schema, "key")`

同时继续执行现有 `RecordFormSchemaService::validateValues()`。所有阶段仅保留 12 个共同追溯字段及本阶段的专用字段，不再把后续阶段字段提前塞进前序记录。

共同追溯字段为：

`run_id`、`chain_id`、`record_id`、`stage`、`sequence`、`simulated_business_date`、`upstream_record_id`、`site_code`、`contract_task_id`、`sample_id`、`method_standard`、`method_version_identity`。

## 5. 原始记录结构性可重现性

每条 `raw_record` 至少包含：

| 维度 | v0.2 字段或控制 |
|---|---|
| 样品、合同、场所 | `sample_id`、`contract_task_id`、`site_code` |
| 方法和版本 | `method_standard`、`method_version_identity` |
| 设备和人员 | `equipment_id`、`employee_id` |
| 时间 | `detection_started_at`、`detection_completed_at` |
| 原始证据 | `raw_evidence_ref` |
| 观察值占位 | `observation_token` |
| 计算适用性 | `calculation_applicability`、`calculation_nonclaim_reason` |
| 结果边界 | `technical_result_claimed=false` |
| 更正与原值保留 | `correction_event_ref` 回链到 `correct_with_original_retained` 历史 |

技术值只使用 `SIM-TOKEN-*`。更正历史同时保留原 token、修正后 token、更正理由、`original_value_retained=true` 和独立复核人。没有标准全文和真实观察值时，不生成真实方法参数、数值结果、限值、不确定度或判定。

## 6. 报告身份、责任和状态链

每条 `report_release` 包含报告标题、实验室和受控场所、SIM 客户、样品、方法与版本、检测日期、结果引用、编制/复核/授权/签发人员及时间、CMA/CNAS 标志状态和完整状态链。

职责固定分离：

| 环节 | 字段 | SIM 责任 |
|---|---|---|
| 编制 | `prepared_by` / `prepared_at` | 场所检测员 |
| 复核 | `reviewed_by` / `reviewed_at` | 总体技术负责人角色 |
| 技术批准/签发 | `authorized_by` / `authorized_at` | 本场所、有效期内且适用范围有效的 `authorized_signatory` |
| 系统发放/交付 | `issued_by` / `issued_at` | 文件管理员；仅表示系统发放或交付动作，不替代技术签发 |

依据 CX-25 4.7.3，授权签字人检查无误后完成检测报告技术批准/签发；质量负责人不得充当报告授权人。职责分离按真实控制要求验证，不凭空要求编制、复核、技术签发和系统发放四人全部互异。四条有序历史区分 `draft → reviewed → technically_authorized → system_issued`，其中最后一步仅为系统发放。`result_reference` 固定为 `SIM-TOKEN-NO-REAL-RESULT`。

## 7. 8013 SIM 依据覆盖层

fixture 通过 `SIM-R2-TPL-BASIS` 及 `SIM-EXTERNAL-BASIS-OVERLAY` 记录实例登记依据身份、版本、来源、有效性、适用性、查新日期及官方 URL，不复制标准全文。它是 LIMS 内的 **SIM 覆盖层**，不是正式 `qms_sources` 发布。

| 分组 | 依据 | v0.2 状态与适用性 |
|---|---|---|
| CMA | 市场监管总局公告 2026 年第 14 号 | `current/published`；一单一库与标志路由 |
| CMA | 市场监管总局公告 2023 年第 21 号 | `current/published`；CMA 排演准则 |
| 通用 | GB/T 27025-2019 | `current/published`；全要素考卷 |
| CNAS | CNAS-RL01:2025 | `current/published`；初次申请筹备、未提交 |
| CNAS | 2026-04-15 规范文件清单 | `current/published`；版本索引 |
| CNAS | CNAS-CL01:2018、G001:2024、A015:2018 | `current/published`；初次申请候选、珠宝领域适用 |
| 库内国标 | GB/T 16552-2017、16553-2017、38821-2020、GB 11887-2012+AMD1、GB/T 18043-2013 | 两场所适用，按用户确认的 CMA 库内 SIM 路由 |
| 库外地方标准 | DB65/T 035-2010、3442-2013、4828-2024 | `CMA=false`，仅保留 CNAS 初次申请候选路径 |

既有 5 项 `due + published` 行保持原样，不插入、不更新。另建 5 条 `SIM-EXTERNAL-BASIS-DISPOSITION` 记录，状态统一为 `excluded_from_sim_overlay_pending_refresh`，同时回链 `source_code` 和“原行未更新”说明。新查新的 16 项只在 SIM 覆盖层中标记为 `current_for_sim_overlay`。fixture 本身不宣称用户确认以外的证书能力。

## 8. 写前业务策略和数据库零变化

`RehearsalBusinessPolicyValidator` 在执行持久化回调前检查：

1. 抽样；
2. 留样；
3. 分包；
4. 钻石分级；
5. GB/T 44914-2024；
6. CNAS 标志或错误 CNAS 状态；
7. DB65 使用 CMA 标志；
8. 非 SIM 人员/用户身份。

任一命中即抛出带 `NEG-01` 至 `NEG-08` 的 `DomainException`。策略单测已证明 8 个禁止候选均未调用持久化回调；待 8013 装载后，运行验证还将用事务内探针和全库指纹证明禁止候选前后数据库零变化。

## 9. 装载前门禁与待执行验证

在明确放行前必须依次满足：

1. 公共 SQL 夹具 allowlist 只允许现有的 `record_form_templates`、`record_form_instances` 和 `histories` SIM INSERT/upsert，`qms_sources` 保持受保护；
2. v0.2 静态契约与 PHP lint 全部通过；
3. 统一装载入口在写入前后执行全库 SIM guard；
4. 装载后运行 v0.2 runtime smoke；
5. 二次装载前后数据库指纹一致；
6. 独立验证者只读复核通过后，状态才可从 `ready_to_apply` 变更为 `sim_applied`。

当前第 1、2 项已通过：公共 SQL lexer/allowlist 接受 7 个语句，静态契约全绿。尚未获得装载通知，因此本台账不宣称 8013 运行通过。

## 10. 结论边界

v0.2 候选已形成，当前结论为：**ready_to_apply，未装载、未运行验证、未通过独立验证**。

它不改变 v0.1 冻结证据，不写 8010/8011/8012，不形成正式文件发布，也不证明真实技术结果、CMA 能力或 CNAS 认可状态。
