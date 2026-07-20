# Task1 沙箱门禁代码质量复核 v0.1

## 1. 复核结论

**结论：NOT APPROVED。**

规格检查虽已通过，但本次代码质量复核发现 3 项 P1 阻断问题。它们会让夹具在“验证通过、前后 SIM 守卫也通过”的情况下删除或改写沙箱基线，或使验证对象与实际执行对象不一致；文件回滚还可能丢失并发产生的 SIM 证据。因此 Task1 暂不应作为后续主演练、密封盲测和最终“不变性”结论的可信门禁。

本复核只读取代码并执行不连接数据库的校验/语法检查，没有调用夹具执行器，没有修改 8010—8014 的数据库或数据卷。

## 2. P1 阻断问题

### P1-01 夹具允许对任意表执行无约束 `UPDATE/DELETE`，后置守卫无法阻止基线被删除或内容被改成真实数据

**证据位置：**

- `jewelry-qms/scripts/validate_sim_rehearsal_fixture.php:181-230`
- `jewelry-qms/app/service/RehearsalDataGuardService.php:18-106`
- `jewelry-qms/app/service/RehearsalDataGuardService.php:140-163`
- `jewelry-qms/deploy/rehearsal/assert-sim-operational-data.sql:126-177`

验证器把 `INSERT`、`UPDATE`、`DELETE`、`SELECT`、`SET` 全部列为允许语句，但没有限制目标表、操作类型、影响行或 `WHERE` 条件。后置守卫只检查：

- `employees.employee_number`、`users.username`、`equipments.equipment_number`、`employee_appointments.appointment_key` 是否以 `SIM-` 开头；
- 其他运行表的 `id` 是否以 `sim-` 开头；
- 内容参考表只做“表已分类”检查，不检查任何行内容。

因此以下语句均被当前验证器接受：

```sql
DELETE FROM documents;
UPDATE employees SET name='REAL-NAME';
DELETE FROM employees;
UPDATE customer_complaints SET complaint_number='REAL-001';
```

只读验证器实测四条语句均返回：

```text
[PASS] rehearsal fixture SQL lexer/allowlist accepted 1 statement(s)
```

其中：

- `DELETE FROM documents` 会清空现用文件起跑基线；`documents` 在内容参考白名单中，后置守卫仍会通过。
- `DELETE FROM employees` 删除全部 SIM 人员后，非 SIM 数量为 0，后置守卫仍会通过。
- 修改 `employees.name` 不改变 `employee_number`，可把真实姓名写入仍被判为 SIM 的行。
- 修改投诉编号、客户名、联系方式或表单 `field_values` 等业务载荷时，只要行 `id` 保持 `SIM-`，守卫不会发现。

这与“只允许内容种子与 SIM 夹具”“不修改现用文件”“无非 SIM 业务数据”的门禁目标不一致。

**建议修复：**

1. 建立“表 × 动作 × 允许列”白名单，默认拒绝。
2. 禁止夹具直接修改/删除现用文件基线表；候选覆盖层应写入专用 SIM 表或专用版本关系。
3. `UPDATE/DELETE` 必须有可机器验证的 SIM 主键谓词，禁止全表语句；执行后还应核对受影响行数上限。
4. 对有业务公开编号或敏感载荷的表，检查 `complaint_number`、`doc_number`、`code`、人员/客户/联系人字段及 JSON 载荷，而不只检查技术主键。
5. 增加上述四类反向测试，并证明被拒绝且未调用 MySQL。

### P1-02 验证和执行两次读取夹具，存在 TOCTOU；且未强制验证器版本与实际执行版本一致

**证据位置：**

- `jewelry-qms/scripts/apply_sim_rehearsal_fixture.sh:11-16`
- `jewelry-qms/scripts/apply_sim_rehearsal_fixture.sh:22-25`
- `jewelry-qms/scripts/apply_sim_rehearsal_fixture.sh:30-36`
- `jewelry-qms/compose.rehearsal.yaml:27-65`

执行器先在第 22 行打开宿主机夹具交给容器内验证器，随后在第 33 行再次打开同一路径交给 MySQL。两次读取之间没有不可变快照、文件描述符绑定或哈希复核。另有两个可利用边界：

- 路径检查只验证父目录及 `-f`，没有拒绝夹具文件自身是符号链接；
- 已运行 `app` 容器内的验证器来自镜像，实际执行的夹具与 SQL 守卫来自当前宿主机工作树，执行器没有校验两边版本哈希。

因此文件可以在验证后、执行前被替换，或通过符号链接改变目标；也可能出现“旧镜像验证器验证、新工作树守卫/夹具执行”的版本漂移。当前抽查时宿主机与 8013 容器哈希碰巧一致：

```text
validator host/container:
0da6dc1272326244023452228aff1860334b78e8375440624acb7f3e385d72cd

guard host/container:
cc863039a9abe535b9ebbc05e53d10e052a5282404479a60e3e2816dd0f8774d
```

但脚本没有强制这一条件，后续改代码不重建镜像即可产生漂移。

**建议修复：**

1. 拒绝符号链接，并把夹具复制到权限受限的临时快照；验证和执行必须读取同一份快照。
2. 在验证后、执行前及执行结束记录并复核 SHA256。
3. 将验证器和 SQL 守卫作为同一不可变构建产物，执行器先核对镜像内外预期哈希；不一致即拒绝。
4. 新增测试：验证期间替换原文件、符号链接夹具、容器验证器哈希漂移，三种情况均应在调用 MySQL 前失败。

### P1-03 文件回滚以“删除整棵共享目录后恢复快照”实现，未锁定 8013，失败时会丢失并发生成的 SIM 证据

**证据位置：**

- `jewelry-qms/app/service/CurrentFilesSeedService.php:103-155`
- `jewelry-qms/app/service/RehearsalFileTransactionService.php:39-76`
- `jewelry-qms/app/service/RehearsalFileTransactionService.php:87-108`

内容种子开始时复制三个完整目录；若数据库事务或后置守卫失败，`rollback()` 对每个根目录执行 `removeTree($root)`，再从旧快照复制回来。整个过程没有互斥锁、维护状态或应用请求隔离。

8013 是仍在提供页面操作的主演练应用。若角色在快照之后、失败回滚之前生成记录表附件、结构化文件或归档文件，这些并发文件不在快照中，却会随整棵目录删除而永久丢失。数据库事务只能回滚种子连接自己的数据库变更，无法保护其他请求写入的文件，最终还会造成数据库与文件证据不一致。

**建议修复：**

1. 内容种子运行时取得跨进程排他锁，并让相关页面写入检查同一维护锁；无法锁定则拒绝运行。
2. 更稳妥的做法是写入操作专属 staging 目录，成功后按文件原子替换；失败只删除本次 staging/本次生成文件，不删除共享根目录。
3. 记录本次创建、覆盖、删除的精确文件清单和原哈希，按清单补偿，而不是整树恢复。
4. 增加并发写入回归测试：在种子快照后写入一份外部 SIM 证据，触发种子失败后该证据必须仍存在且哈希不变。

## 3. P2 重要问题

### P2-01 “数据库指纹”只包含结构和各表行数，无法证明数据未变化

**证据位置：**

- `jewelry-qms/tests/qms_rehearsal_database_fingerprint.php:18-42`
- `jewelry-qms/tests/qms_rehearsal_full_seed_no_side_effect_runtime_smoke.php:22-54`
- `jewelry-qms/tests/qms_rehearsal_fixture_runner_runtime_smoke.sh:14-46`

当前指纹对每张表只记录 `COUNT(*)`，不记录行内容。任何原地 `UPDATE`、等量删除再插入、主键或业务字段替换都会保持相同指纹。测试输出“database fingerprint stayed unchanged”因此会形成超出证据能力的假绿结论。

**建议修复：** 对每张表按稳定主键排序后计算规范化行哈希，或生成事务一致性逻辑备份的 SHA256；对时间戳等明确允许变化的字段单独声明排除规则。测试文案也应准确说明实际比较范围。

### P2-02 排演身份只由进程环境变量自我声明，没有绑定数据库实例或启动时 SIM 标记

**证据位置：**

- `jewelry-qms/app/service/TrialModeService.php:24-53`
- `jewelry-qms/config/qms.php:6-13`

`isRehearsalEnvironment()` 只要求试运行开关为真、运行 ID 和标签非空、角色为 `main|blind`。任意字符串都可通过，且没有核对当前数据库是否由 `03-sim-bootstrap.sql` 初始化、数据库内运行 ID/角色/实例 nonce 是否与进程一致。

这是一项纵深防护缺口：如果应用进程误连其他数据库但带有这些环境变量，内容种子和文件事务仍会认为自己处于安全沙箱。

**建议修复：** 在数据库初始化时写入不可复用的 rehearsal marker（运行 ID、角色、compose 项目、实例 nonce、建立时间），每次破坏性动作同时核对进程配置、数据库 marker 和预期角色；8013/8014 marker 不得互换。

### P2-03 文件事务公共入口允许任意绝对目录，误用时可能删除应用外的可写目录

**证据位置：**

- `jewelry-qms/app/service/RehearsalFileTransactionService.php:29-76`
- `jewelry-qms/app/service/RehearsalFileTransactionService.php:87-99`
- `jewelry-qms/app/service/RehearsalFileTransactionService.php:227-231`

`begin()` 只拒绝空路径、根目录、相对路径和符号链接；`/app`、`/tmp` 或其他可写绝对目录都可作为根。回滚时该根会被整棵删除。当前唯一调用者传入了三个预期目录，但公共 API 对后续调用没有安全边界。

**建议修复：** 服务内部固定允许根，或要求所有根经 `realpath` 后位于明确的 rehearsal runtime/uploads 前缀；同时拒绝父子根重叠。

## 4. P3 改进项

### P3-01 提交后的备份清理失败被静默吞掉，可能在系统临时目录残留完整受控文件副本

**证据位置：**

- `jewelry-qms/app/service/RehearsalFileTransactionService.php:78-85`
- `jewelry-qms/app/service/RehearsalFileTransactionService.php:118-125`

`cleanupBackup()` 捕获所有异常且不记录。提交仍可成功，但 `/tmp/qms-rehearsal-file-transaction-*` 可能残留质量文件副本，且主流程没有可审计告警。

**建议修复：** 提交成功与备份清理结果分开记录；清理失败至少写入安全日志和治理台账，并提供启动时清扫机制。

## 5. 本次只读验证记录

执行日期：2026-07-19。

执行了以下不连接数据库的检查：

- 向容器内 SQL 验证器输入 4 条宽泛 DML，均被接受，证实 P1-01；
- 对 6 个 PHP 文件执行 `php -l`，均无语法错误；
- 对夹具执行器及相关 Shell 测试执行 `bash -n`，退出码为 0；
- 执行 `git diff --check`，退出码为 0；
- 比对当前宿主机与 8013 容器内验证器、SQL 守卫 SHA256，当前一致。

未执行会写数据库的 `qms_rehearsal_*_runtime_smoke`，未执行任何夹具，未访问或修改 8010—8014 数据。

## 6. 复核门禁

完成 P1-01、P1-02、P1-03 修复后，应由原独立复核角色重新执行：

1. 新增反向测试的红—绿证据；
2. 全套 Task1 门禁测试；
3. 全内容哈希的不变性验证；
4. 文件并发回滚验证；
5. 本代码质量复核的逐项关闭。

在此之前，Task1 状态应保持 `returned`，不得标记为 `verified_candidate`。
