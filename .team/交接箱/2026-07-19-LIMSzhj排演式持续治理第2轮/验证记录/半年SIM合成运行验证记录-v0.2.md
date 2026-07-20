# 半年 SIM 合成运行验证记录 v0.2

## 1. 验证状态

| 项目 | 结果 |
|---|---|
| 状态 | `ready_to_apply` |
| 8013 写入 | 0；本记录形成时未装载 v0.2 fixture |
| 已完成 | TDD 红测、策略单元测试、候选 fixture、运行验证脚本、PHP lint、静态契约和公共 SQL allowlist |
| 未完成 | 8013 装载、运行验证、幂等验证、独立验证 |

## 2. 验证对象

- Fixture：`jewelry-qms/tests/fixtures/sim_rehearsal_six_month_run_v02.sql`
- 写前策略：`jewelry-qms/scripts/lib/RehearsalBusinessPolicyValidator.php`
- 策略测试：`jewelry-qms/tests/qms_rehearsal_business_policy_smoke.php`
- 静态契约：`jewelry-qms/tests/qms_rehearsal_six_month_v02_fixture_contract_smoke.php`
- 运行验证：`jewelry-qms/tests/qms_rehearsal_six_month_v02_runtime_smoke.php`

## 3. 红灯证据

| 红测 | 实现前预期失败 | 证据 |
|---|---|---|
| v0.2 fixture 契约 | `FAIL v0.2 additive SIM fixture exists` | `/tmp/r2-v02-red-contract.log` |
| 写前业务策略 | 缺少 `RehearsalBusinessPolicyValidator.php`，PHP 终止 | `/tmp/r2-v02-red-policy.log` |

两项红测均在对应实现之前产生。

## 4. 已完成的绿灯

写前业务策略单元测试结果：

> `qms_rehearsal_business_policy_smoke passed: 9 assertions`

覆盖 8 个禁止候选和 1 个允许的国标 SIM 报告候选。8 个拒绝用例均在持久化回调执行前抛出 `DomainException`，回调调用数为 0。

静态验证结果：

- 4 个 v0.2 PHP 文件通过 `php -l`；
- v0.2 fixture 静态契约全部通过；
- 公共 SQL lexer/allowlist 接受 7 个语句；
- fixture 仅触及已允许的 `record_form_templates`、`record_form_instances` 和 `histories`；
- 不存在对 `qms_sources` 的 INSERT/upsert/UPDATE。

## 5. 待装载后验证的断言

运行验证草案将检查：

- 96 个冻结业务阶段仍完整；
- 每个 `field_values` 的键均为对应冻结 schema 的子集；
- 每个实例继续通过 `RecordFormSchemaService::validateValues()`；
- 16 条原始记录具有结构性可重现字段、SIM token、无真实结果声明及原值保留更正历史；
- 16 条报告记录由本场所有效 `authorized_signatory` 完成技术批准/签发，系统发放动作单列，不强制四人互异；
- 16 项核心依据在独立 SIM 覆盖层中具有来源、有效性、适用性、官方 URL 和查新日期；
- 原有 5 项 `due + published` 行保持不变，同时存在 5 条 `excluded_from_sim_overlay_pending_refresh` 处置记录；
- 8 个禁止候选由真实策略校验器在写前拒绝；
- 禁止探针执行前后数据库指纹完全一致；
- `SIM-R2-V02-FORBID-*` 记录数保持为 0。

## 6. 当前等待项

公共静态 SQL 门禁已经通过，且 `qms_sources` 内容基线保护保持不变。本支线仍不自行装载或绕过统一入口，等待公共试运行接口代码质量复核和主任务明确的 8013 装载通知。

## 7. 当前结论

候选文件已编制到可静态复核状态，写前业务策略单元测试通过；由于尚未装载 8013，**不得宣称 v0.2 运行通过或整改关闭**。
