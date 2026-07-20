# 半年 SIM 合成运行验证记录 v0.1

## 1. 验证对象

- 环境：8013 主演练；
- 运行批次：`SIM-GOV-R2-20260719`；
- 夹具：`jewelry-qms/tests/fixtures/sim_rehearsal_six_month_run.sql`；
- 静态契约：`jewelry-qms/tests/qms_rehearsal_six_month_fixture_contract_smoke.php`；
- 运行验证：`jewelry-qms/tests/qms_rehearsal_six_month_runtime_smoke.php`；
- 统一入口：`jewelry-qms/scripts/apply_sim_rehearsal_fixture.sh`。

## 2. 红—绿记录

| 阶段 | 结果 | 说明 |
|---|---:|---|
| 数据夹具实现前 | 4 通过 / 27 失败 | SIM 总门禁和禁止活动空记录先通过；场所、任命、链条和专项证据因尚不存在按预期失败 |
| schema 兼容断言加入后 | 279 通过 / 133 失败 | 7 个模板及 103 个冻结快照的旧 schema 对象根节点被现有解析器拒绝 |
| 字段值—schema 一致性断言加入后 | 454 通过 / 61 失败 | `false` 布尔被误设必填、治理来源索引使用数组，现有值校验器按预期拒绝 |
| 语义化 schema、字段值、CMA JSON 布尔和 MySQL 消歧修正后 | 515 通过 / 0 失败 | 全部业务链、模板、快照、字段值、标志分流、专项证据及授权反向测试通过 |

中间还检出并修正两项夹具问题：

1. MySQL `IF` 最初将 CMA 布尔写成 `0/1`，改为 JSON 原生 `true/false`；
2. MySQL 8.4 对 `INSERT ... SELECT ... CROSS JOIN ... ON DUPLICATE KEY UPDATE` 存在解析歧义，在 `ON DUPLICATE` 前加入无副作用 `WHERE 1=1`。

统一门禁自身的 MySQL 8.4 临时表复用问题由公共试运行接口任务修正；首次失败事务完整回滚，本支线未绕过门禁写入。

## 3. 运行结果

| 项目 | 结果 |
|---|---:|
| 两场所 | 2 |
| 本支线 SIM 人员 | 17 |
| SIM 岗位任命 | 18 |
| 完整业务链 | 16 |
| 业务链阶段证据 | 96 |
| 运行治理证据 | 7 |
| 写前拒绝事件 | 8 |
| 两场所×八方法能力身份记录 | 16 |
| 最终运行断言 | 515/515 |

报告标志分流：

| 方法类 | 报告阶段数 | CMA 标志状态 | CNAS 标志状态 |
|---|---:|---|---|
| 5 项国标 | 10 | `true`（仅 SIM 库内路径） | `false` |
| 3 项 DB65 | 6 | `false` | `false` |

额外核验：

- 含 GB/T 44914-2024 的业务实例：0；
- 非 SIM 用户：0；
- 非 SIM 设备：0；
- 全部运行表 ID / 特殊身份字段通过统一 SIM guard；
- 夹具第二次运行前后数据库结构与各表行数指纹一致：`fd392baf0d6626d93528e92df755ae6c0cf65823222a1ee40344a754bdae1f6f`。

## 4. 结论边界

“角色权限＋半年 SIM 合成运行”支线验证通过，可以作为后续文件自治治理、独立验证、内审、CAPA、管理评审和模拟外审的冻结运行输入。

本结论不代表：

- 真实人员能力、设备状态或检测结果已被验证；
- SIM 报告成为正式检测报告；
- 机构已取得 CNAS 认可；
- 本轮完整排演已最终通过。
