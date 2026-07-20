# Task1 沙箱门禁代码质量复核 v0.2

## 1. 复核结论

**结论：NOT APPROVED。**

本轮整改已完整关闭原报告的 P1-02、P2-01、P2-02、P2-03，并关闭 P3-01；P1-01 和 P1-03 只完成了部分修复，仍可被独立复现，因此 Task1 暂不能标记为 `verified_candidate`。

当前阻断点不是“旧测试没有通过”，而是现有测试没有覆盖仍存在的安全边界：

1. 顶层 `UPDATE/DELETE` 已禁止，但 `INSERT ... SELECT`、带真实业务载荷的 `INSERT`、以及记录模板的 `ON DUPLICATE KEY UPDATE` 仍可绕过 SIM 语义和基线保护。
2. 整树回滚已改成精确文件回滚，但真实写路径没有取得同一把锁；快照开始后、`captureOwnedChanges()` 前产生的并发证据仍会被误判为本次种子文件并删除。

本复核未修改 8010—8014。需要写库的验证全部在一次性隔离栈 `lims-zhj-task1-review-temp-20260719` 中执行；验证结束后容器、网络和数据卷均已删除。

## 2. 原问题逐项关闭结果

| 原编号 | v0.2 状态 | 结论 |
|---|---|---|
| P1-01 任意 DML 与 SIM 载荷绕过 | **保留，未关闭** | 顶层 `UPDATE/DELETE` 已关闭；`INSERT` 解析仍可绕过 |
| P1-02 验证/执行 TOCTOU 与版本漂移 | **已关闭** | 不可变快照、符号链接拒绝、前后 SHA256 与运行产物哈希门禁均成立 |
| P1-03 文件回滚并发证据丢失 | **保留，未关闭** | 整树删除已消除；真实风险窗口仍会删除并发文件 |
| P2-01 指纹只含结构和行数 | **已关闭** | 已按表、按稳定顺序计算完整行内容哈希 |
| P2-02 排演身份未绑定数据库 | **已关闭** | run/role/compose project/server UUID 四项绑定并能拒绝错连 |
| P2-03 文件事务可接受任意绝对根 | **已关闭** | 三个允许根固定白名单，任意绝对根实测被拒绝 |
| P3-01 备份清理失败静默 | **已关闭** | 清理异常已写 `error_log` |

## 3. 仍然阻断的 P1

### P1-01 `INSERT` 白名单只检查目标表和首列名，无法保证“只新增 SIM 证据”或保护现用基线

**相关代码：**

- `jewelry-qms/scripts/validate_sim_rehearsal_fixture.php:175-239`
- `jewelry-qms/scripts/validate_sim_rehearsal_fixture.php:241-289`
- `jewelry-qms/app/service/RehearsalDataGuardService.php:140-163`
- `jewelry-qms/deploy/rehearsal/assert-sim-operational-data.sql:126-177`

整改已经做到：

- 允许的顶层语句缩小为 `INSERT`、`SET`；
- `UPDATE`、`DELETE` 及事务/DDL 控制词被拒绝；
- `INSERT` 目标表设有白名单；
- 首列必须写成 `id`；
- 只有 `record_form_templates`、`record_form_instances` 允许 `ON DUPLICATE KEY UPDATE`。

但解析器处理的是去掉字符串值后的文本，只确认“表名”和“第一列名”，没有确认：

- 第一列的实际值以 `SIM-` 开头；
- 语句使用 `VALUES` 而不是 `SELECT`；
- 公开业务编号、姓名、联系人、客户、供应商等载荷是 SIM 数据；
- upsert 的冲突目标一定是本轮 SIM 行，而不是已有基线行；
- upsert 只更新允许列且不会改变候选/现用状态。

以下三条独立探针均被当前验证器接受：

```sql
INSERT INTO employees (id, employee_number, name, company_id)
SELECT 'SIM-X','SIM-E-X',name,company_id FROM employees LIMIT 1;

INSERT INTO record_form_templates (id, doc_number, name)
VALUES ('SIM-X','BASE','x')
ON DUPLICATE KEY UPDATE name='OVERWRITTEN';

INSERT INTO suppliers
    (id, company_id, supplier_number, name, contact_person)
VALUES
    ('SIM-X','c','REAL-001','Real Supplier','Real Person');
```

验证器对三条探针均输出：

```text
[PASS] rehearsal fixture SQL lexer/allowlist accepted 1 statement(s)
```

进一步在一次性隔离库中插入第三类探针后，SQL 后置守卫执行成功，且真实载荷仍存在：

```text
SIM-REVIEW-REAL-PAYLOAD|REAL-001|Real Supplier|Real Person
```

这证明“技术主键以 SIM 开头”仍被错误等同于“整行是 SIM 数据”。因此原 P1-01 尚未关闭。

**建议修复：**

1. 拒绝 `INSERT ... SELECT`，只允许语法受控的显式 `VALUES`。
2. 对每个允许表定义“必需列、允许列、SIM 标识列、禁止真实身份列”，解析列与每行值的一一对应关系。
3. `id` 及人员、设备、任命、业务编号、客户/供应商等身份字段必须能在写入前验证 `SIM-` 或受控虚拟值。
4. 如需幂等，不使用通用 `ON DUPLICATE KEY UPDATE`；改为只针对本轮 run ID 的受控写入服务，或先验证数据库中冲突行本身属于同一 run。
5. 记录模板候选必须写入 SIM 覆盖层，不能用 upsert 改写现用模板基线。
6. 把以上三条探针加入门禁测试，并证明在调用 MySQL 前拒绝。

### P1-03 文件变更捕获窗口仍会把并发证据认作种子文件并在回滚时删除

**相关代码：**

- `jewelry-qms/app/service/RehearsalFileTransactionService.php:86-116`
- `jewelry-qms/app/service/RehearsalFileTransactionService.php:118-156`
- `jewelry-qms/app/service/RehearsalFileTransactionService.php:186-213`
- `jewelry-qms/app/service/CurrentFilesSeedService.php:103-148`
- `jewelry-qms/tests/qms_rehearsal_content_seed_atomicity_runtime_smoke.php:88-195`

整改已经消除了“删除整棵共享目录再恢复”的高风险实现，并新增一把排他 `flock`。但是：

- 应用页面和其他文件写入路径没有读取或取得这把锁；
- 文件事务在构造时保存初始快照；
- 种子完成文件写入后才调用 `captureOwnedChanges()`；
- 初始快照之后、捕获之前出现的任何新文件都会被判为本次事务拥有；
- 失败回滚会删除这些文件。

现有并发测试是在 `captureOwnedChanges()` 已完成之后，才通过 `failure_injector` 创建“外部证据”。它验证的是捕获之后的安全窗口，没有覆盖真正危险的“初始快照—捕获”窗口。

独立复现步骤是在一次性隔离栈中：

1. `begin()` 创建初始快照并取得文件事务锁；
2. 模拟未使用该锁的页面写入，创建外部 SIM 证据；
3. 调用 `captureOwnedChanges()`；
4. 调用 `rollback()`。

实际结果：

```text
DELETED
```

因此原 P1-03 仍未关闭。

**建议修复：**

1. 所有会写入这三个目录的真实路径必须取得同一把共享/排他锁，不能只让种子持锁。
2. 更稳妥的结构仍是“本次运行专属 staging 目录 + 成功后原子发布”；事务从一开始就知道自己拥有哪些文件，不通过前后目录差异推断所有权。
3. 若保留差异捕获，种子每次写文件时应即时 `trackPath()`，不得把捕获窗口内全部新文件认作自己拥有。
4. 新增真实风险窗口测试：外部进程在 `begin()` 后、`captureOwnedChanges()` 前写文件，失败回滚后该文件及哈希必须保留。

## 4. 已关闭项的证据

### P1-02 已关闭：快照、符号链接和运行产物绑定

实现包括：

- `snapshot_sim_rehearsal_fixture.py` 使用 `lstat`、`O_NOFOLLOW`、设备号/ inode/mtime/ctime/大小复核；
- 快照文件以 `0600` 创建；
- 验证和执行读取同一快照；
- 验证后、执行后均复核 SHA256；
- 夹具本身的符号链接在 Shell 和 Python 两层拒绝；
- 宿主机验证器、SQL 守卫与容器运行产物哈希不一致时拒绝执行。

`qms_rehearsal_fixture_snapshot_smoke.sh` 实测通过：

```text
[PASS] rehearsal runtime artifact hashes match
[PASS] fixture snapshot is immutable, rejects symlinks, and binds runtime artifacts
```

首次用旧默认镜像启动复核临时栈时，合法夹具被正确拒绝并输出：

```text
runtime artifact hash mismatch: scripts/validate_sim_rehearsal_fixture.php
```

这从反面证明版本漂移门禁真实生效。随后使用当前源码构建独立镜像，合法夹具才被允许。

### P2-01 已关闭：完整内容指纹

`qms_rehearsal_database_fingerprint_lib.php`：

- 对每张表按主键排序；无主键时按全部列排序；
- 对列名、NULL 状态和 base64 后的值逐项哈希；
- 汇总为每表哈希和全库 `content_sha256`。

同一行只修改 `details`、不改变行数时，实测指纹发生变化：

```text
[PASS] database fingerprint detects same-row-count content mutation
```

旧全量种子在任何副作用前被拒绝，且完整内容哈希不变：

```text
[PASS] full seed rejected before schema or content side effects
schema_sha256=63d3118acfc7029ee123ff9010c7d04bed5da4acc17c5a2b6b1f79e1c5435f79
content_sha256=68aa7f383c829512b6cbf5d8995a595401350100172d6087d80a190d4a95cd3b
table_count=83
```

### P2-02 已关闭：数据库 marker 绑定

初始化脚本把以下内容写入唯一 marker：

- `run_id`
- `role`
- `compose_project`
- MySQL `@@server_uuid`

破坏性服务会读取数据库 marker 并逐项用 `hash_equals` 比对。正确环境实测：

```text
[PASS] process rehearsal identity matches the database marker and server UUID
```

把进程中的 compose project 改为错误值后，读取同一数据库实测：

```text
EXPECTED_REJECT rehearsal database marker mismatch: compose_project
```

### P2-03 已关闭：文件根白名单

文件事务只允许：

- `public/uploads/record-form-sources`
- `runtime/qms_archive`
- `runtime/qms_structured`

任意临时绝对目录实测被拒绝：

```text
[PASS] arbitrary file transaction roots are rejected
```

### P3-01 已关闭：清理失败可见

备份清理异常不再完全静默，已通过 `error_log` 留下诊断信息。它仍不应阻断已提交的数据库事务，这一处理符合原建议的最低审计要求。

## 5. 运行复核汇总

| 复核项 | 结果 | 说明 |
|---|---|---|
| 4 条旧破坏性 DML | PASS | 顶层 `UPDATE/DELETE` 均拒绝 |
| 3 条新 `INSERT` 绕过探针 | **FAIL** | 均被验证器接受 |
| 真实供应商载荷 + SQL 后置守卫 | **FAIL** | 守卫通过且真实载荷保留 |
| 快照源文件替换 | PASS | 快照不变 |
| 符号链接夹具 | PASS | 拒绝 |
| 宿主机/容器版本哈希漂移 | PASS | 拒绝 |
| 并发文件在捕获之后写入 | PASS | 保留 |
| 并发文件在捕获之前写入 | **FAIL** | 回滚后被删除 |
| 同行数内容变化指纹 | PASS | 检出 |
| DB marker 正向 | PASS | 通过 |
| DB marker 错 compose project | PASS | 拒绝 |
| 任意文件事务绝对根 | PASS | 拒绝 |
| 旧全量种子 | PASS | 副作用前拒绝，内容哈希不变 |
| `sim_rehearsal_six_month_run.sql` | PASS | 23 条语句验证、执行及前后守卫通过 |
| `sim_rehearsal_six_month_run_v02.sql` | 待处理 | 当前在 `qms_sources` 目标表处被白名单拒绝 |

`v02` 夹具的拒绝不应通过简单扩大白名单解决。需先决定外部依据候选应由内容种子/候选覆盖层装载，还是确属业务夹具职责，然后为该路径建立专门的列级和状态门禁。

## 6. 临时环境与清理

写库测试环境：

```text
compose project: lims-zhj-task1-review-temp-20260719
run id: SIM-TASK1-REVIEW-20260719
role: main
host port: 18015
image: lims-zhj-task1-review-current:local
```

测试结束后已执行隔离栈销毁，并确认：

```text
[PASS] temporary review stack and volumes removed
```

没有对 8010、8011、8012、8013、8014 执行写操作。

## 7. 下一次复核门禁

下一次仅在以下两项同时满足后进入：

1. 新 `INSERT` 绕过探针全部在调用 MySQL 前被拒绝，同时合法半年夹具仍能通过；
2. 在真实危险窗口写入的并发 SIM 证据经失败回滚后仍存在且哈希不变。

在此之前，Task1 应保持 `returned`。
