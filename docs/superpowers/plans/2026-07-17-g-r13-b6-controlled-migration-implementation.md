# G-R13-B6 受控迁移包实现计划

> **面向 AI 代理的工作者：** 必需子技能：使用 superpowers:executing-plans 逐任务实现此计划。步骤使用复选框（`- [ ]`）语法跟踪进度。

**目标：** 实现一个只读采集现状、验证人工确认、生成受控迁移/回退包并在隔离 MySQL 库完成迁移—回退—再迁移演练的命令。

**架构：** 新增一个无 HTTP 入口的 ThinkPHP 控制台命令，由专用服务读取确认 JSON、查询当前连接数据库的组织指纹并生成有 SHA256 的目录包。SQL 分为只读预检、结构完整性、组织数据事务、只读验收、行级回退和紧急结构回退；生产授权始终为 false，现行执行必须另走 B7。

**技术栈：** PHP 8.4、ThinkPHP 8、MySQL 8.4、现有 `think` 控制台、原生 JSON/SHA256、项目 smoke 测试。

---

## 文件职责

- 创建 `jewelry-qms/app/service/P0ControlledMigrationPackageService.php`：确认校验、数据库指纹、25 项目标模型、SQL/证据/清单生成。
- 创建 `jewelry-qms/app/command/P0BuildControlledMigrationPackage.php`：命令参数、退出码和面向操作者的摘要。
- 修改 `jewelry-qms/config/console.php`：注册 `qms:p0-build-controlled-migration-package`。
- 创建 `jewelry-qms/database/fixtures/g_r13_b6_confirmation.template.json`：保持 pending 的人工确认输入模板。
- 创建 `jewelry-qms/tests/qms_p0_controlled_migration_package_smoke.php`：E01～E12 失败边界、包结构、SQL 安全和幂等性回归。
- 创建 `jewelry-qms/tests/qms_p0_controlled_migration_rehearsal_smoke.php`：在专用数据库执行迁移、回退、再迁移，验证 F01～F10。
- 创建 `.team/交接箱/2026-07-17-模拟试运行幕1至幕2只读综合验收/G-R13-B6-受控迁移实现-v0.1/`：执行记录、演练记录和进度卡。
- 创建 `.team/交接箱/2026-07-17-模拟试运行幕1至幕2只读综合验收/版本台账-v0.10.md`：只增不改登记 B6 实现。

### 任务 1：固化确认契约与包安全失败测试

**文件：**
- 创建：`jewelry-qms/tests/qms_p0_controlled_migration_package_smoke.php`
- 创建：`jewelry-qms/database/fixtures/g_r13_b6_confirmation.template.json`

- [ ] **步骤 1：编写确认模板**

模板使用固定字段：

```json
{
  "schema_version": "g-r13-b6-confirmation-v0.1",
  "status": "pending",
  "target_database": "",
  "company_id": "",
  "document_number": "",
  "effective_date": "",
  "source_excerpt": "",
  "people": {
    "hetian_document_controller": {"formal_name": "", "employee_number": ""},
    "hetian_equipment_manager": {"formal_name": "米尔布拉", "employee_number": ""}
  },
  "reviews": {
    "quality_manager": {"name": "张晓磊", "decision": "pending", "date": ""},
    "technical_manager": {"name": "刘恒春", "decision": "pending", "date": ""},
    "top_management": {"name": "俞炳星", "decision": "pending", "date": ""}
  },
  "rehearsal_marker": ""
}
```

- [ ] **步骤 2：编写 E01～E12 失败/输出测试**

测试直接调用预期 API：

```php
$invalid = json_decode((string)file_get_contents($templatePath), true);
try {
    P0ControlledMigrationPackageService::build($invalid, $outputDir, true);
    migration_case(false, 'E01', 'pending 确认必须阻断');
} catch (DomainException $exception) {
    migration_case(str_contains($exception->getMessage(), 'status'), 'E01', 'pending 确认必须阻断');
}

$confirmation = valid_rehearsal_confirmation();
$summary = P0ControlledMigrationPackageService::build($confirmation, $outputDir, true);
migration_case($summary['production_apply_authorized'] === false, 'E02', '演练包永不取得生产授权');
migration_case(count($summary['appointment_keys']) === 25, 'E03', '目标任命固定为 25 项');
```

E01～E12 覆盖：

1. pending 确认阻断；
2. 演练包生产授权恒为 false；
3. 25 项目标任命；
4. 两场所复用且无 MAIN/HETIAN；
5. 刘恒春无 `quality_manager`；
6. 缺姓名、编号、文件号、日期、摘录任一项阻断；
7. 三方复核必须均为 approved；
8. 非隔离库在 rehearsal 模式阻断；
9. 包必须生成 6 个 SQL；
10. manifest 和全包 SHA256 完整；
11. SQL 不包含密码和测试业务记录；
12. 重复生成内容稳定，除生成时间外语义一致。

- [ ] **步骤 3：运行测试确认正确红灯**

运行：

```bash
docker exec -e DB_NAME=jewelry_qms_p0_r13b6 qms-r13b6-app \
  php tests/qms_p0_controlled_migration_package_smoke.php
```

预期：FAIL，原因是 `P0ControlledMigrationPackageService` 尚不存在。

- [ ] **步骤 4：提交红灯**

```bash
git add jewelry-qms/tests/qms_p0_controlled_migration_package_smoke.php \
  jewelry-qms/database/fixtures/g_r13_b6_confirmation.template.json
git commit -m "test(p0): 固化受控迁移包失败契约"
```

### 任务 2：实现迁移包服务

**文件：**
- 创建：`jewelry-qms/app/service/P0ControlledMigrationPackageService.php`
- 测试：`jewelry-qms/tests/qms_p0_controlled_migration_package_smoke.php`

- [ ] **步骤 1：定义公开 API 与确认校验**

```php
final class P0ControlledMigrationPackageService
{
    public const REHEARSAL_MARKER = 'B6_REHEARSAL_ONLY_NOT_REAL_APPROVAL';

    public static function build(array $confirmation, string $outputDir, bool $rehearsal): array
    {
        self::validateConfirmation($confirmation, $rehearsal);
        $state = self::captureCurrentState();
        self::validateCurrentState($confirmation, $state, $rehearsal);
        return self::writePackage($confirmation, $state, $outputDir, $rehearsal);
    }
}
```

校验采用白名单字段、严格日期、员工编号唯一、三方 `approved` 和 exact rehearsal marker。错误统一抛出 `DomainException`，不得静默修正。

- [ ] **步骤 2：实现只读现状采集**

只查询：

- 当前数据库名；
- 公司；
- `PLACE01`、`PLACE02`；
- 目标 9 人的活动/软删除员工行；
- `admin` 当前员工关联；
- 目标岗位；
- 当前活动任命；
- P0 预检结果；
- 允许对象计数。

输出 ID、编号、名称、状态和计数，不输出邮箱、电话或密码。

- [ ] **步骤 3：实现 25 项目标模型**

使用一个私有常量数组保存 B5 已批准关系。`appointment_key` 格式：

```text
organization:<employee_number>:<position_code>:<GLOBAL|PLACE01|PLACE02>
```

固定 ID 使用 SHA256 派生的 UUID 形式，确保重跑稳定，不使用 `UUID()`。

- [ ] **步骤 4：生成 6 个 SQL**

- `00-preflight-readonly.sql`：数据库/公司/场所/人员/任命/管理员/P0 检查，仅 SELECT 和 SIGNAL。
- `10-schema-integrity.sql`：复用 `20260717_p0_record_integrity.sql` 的四项约束逻辑。
- `20-organization-migration.sql`：数据库硬闸门、事务、行锁、两名人员、三个岗位、25 项任命和 admin 改指；冲突即 SIGNAL。
- `30-postflight-readonly.sql`：输出允许对象计数和异常计数。
- `90-row-rollback.sql`：按固定 ID 和 before-state 还原，不按姓名模糊删除。
- `91-schema-rollback-emergency-only.sql`：只含四项索引存在性检查和显式删除，文件头标记需另行批准。

- [ ] **步骤 5：生成证据与 SHA256**

写入：

- `before-state.json`
- `after-state.expected.json`
- `migration-diff.expected.json`
- `00-manifest.json`
- `SHA256SUMS.txt`
- `snapshot/README-v0.1.md`
- `02-执行手册-v0.1.md`

manifest 固定：

```json
{
  "production_apply_authorized": false,
  "requires_separate_b7_approval": true
}
```

- [ ] **步骤 6：运行 E01～E12，修到绿灯**

预期：

```text
qms_p0_controlled_migration_package_smoke passed: E01-E12
```

- [ ] **步骤 7：提交服务**

```bash
git add jewelry-qms/app/service/P0ControlledMigrationPackageService.php
git commit -m "feat(p0): 生成受控迁移与回退包"
```

### 任务 3：实现控制台命令

**文件：**
- 创建：`jewelry-qms/app/command/P0BuildControlledMigrationPackage.php`
- 修改：`jewelry-qms/config/console.php`
- 测试：`jewelry-qms/tests/qms_p0_controlled_migration_package_smoke.php`

- [ ] **步骤 1：扩展红灯测试**

断言命令类、注册项和选项存在：

```php
migration_case(
    str_contains($consoleSource, 'P0BuildControlledMigrationPackage::class'),
    'E13',
    '控制台注册迁移包命令'
);
```

运行测试，预期 E13 失败。

- [ ] **步骤 2：实现命令**

命令名：

```text
qms:p0-build-controlled-migration-package
```

必需选项：

- `--confirmation`
- `--output`

可选：

- `--rehearsal`，默认 false。

成功输出 JSON 摘要；确认或状态错误返回 2；文件系统错误返回 3；不接受 apply 选项。

- [ ] **步骤 3：验证命令**

运行：

```bash
php think list | rg 'qms:p0-build-controlled-migration-package'
php tests/qms_p0_controlled_migration_package_smoke.php
```

预期：命令可见，E01～E13 通过。

- [ ] **步骤 4：提交命令**

```bash
git add jewelry-qms/app/command/P0BuildControlledMigrationPackage.php \
  jewelry-qms/config/console.php \
  jewelry-qms/tests/qms_p0_controlled_migration_package_smoke.php
git commit -m "feat(p0): 增加受控迁移包生成命令"
```

### 任务 4：隔离数据库迁移—回退—再迁移

**文件：**
- 创建：`jewelry-qms/tests/qms_p0_controlled_migration_rehearsal_smoke.php`
- 修改：`jewelry-qms/app/service/P0ControlledMigrationPackageService.php`（仅修复演练暴露的问题）

- [ ] **步骤 0：建立明确命名的隔离环境**

从现行数据库执行只读 `mysqldump --no-create-db`，导入新建的
`jewelry_qms_p0_r13b6`；启动仅连接该库、仅绑定 B6 worktree 的
`qms-r13b6-app`。导入后先核对 `SELECT DATABASE()`，任何名称不一致都停止。

- [ ] **步骤 1：编写 F01～F10 演练测试**

测试要求数据库名严格为 `jewelry_qms_p0_r13b6`，并接受生成包路径参数：

```text
F01 预检通过
F02 四项唯一约束存在
F03 两名人员按确认编号建立
F04 25 项任命精确匹配
F05 admin 指向在用张晓磊
F06 刘恒春无质量负责人
F07 场所仍为 PLACE01/PLACE02
F08 行级回退恢复 before-state
F09 再迁移不重复新增
F10 无 B5/SIM/验收业务记录
```

- [ ] **步骤 2：运行测试确认红灯**

在只生成包、尚未执行 SQL 的库上运行，预期 F02～F05 失败。

- [ ] **步骤 3：执行迁移并转绿**

严格执行：

```bash
mysql < sql/00-preflight-readonly.sql
mysql < sql/10-schema-integrity.sql
mysql < sql/20-organization-migration.sql
mysql < sql/30-postflight-readonly.sql
php tests/qms_p0_controlled_migration_rehearsal_smoke.php --phase=migrated
mysql < sql/90-row-rollback.sql
php tests/qms_p0_controlled_migration_rehearsal_smoke.php --phase=rolled_back
mysql < sql/20-organization-migration.sql
php tests/qms_p0_controlled_migration_rehearsal_smoke.php --phase=remigrated
```

- [ ] **步骤 4：执行 P0 回归**

```bash
php tests/qms_p0_field_contract_smoke.php
php tests/qms_p0_workflow_link_smoke.php
php tests/qms_p0_field_audit_smoke.php
php tests/qms_p0_action_authorization_smoke.php
php tests/qms_p0_preflight_smoke.php
```

预期：C01～C12、L01～L10、T01～T10、A01～A16 全部通过。

- [ ] **步骤 5：提交演练测试与修复**

```bash
git add jewelry-qms/tests/qms_p0_controlled_migration_rehearsal_smoke.php \
  jewelry-qms/app/service/P0ControlledMigrationPackageService.php
git commit -m "test(p0): 验证迁移回退与重复执行"
```

### 任务 5：固化交接与最终验证

**文件：**
- 创建：`.team/交接箱/2026-07-17-模拟试运行幕1至幕2只读综合验收/G-R13-B6-受控迁移实现-v0.1/G-R13-B6执行记录-v0.1.md`
- 创建：同目录 `隔离迁移回退演练记录-v0.1.md`
- 创建：同目录 `G-R13-B6实现阶段进度卡-v0.1.md`
- 创建：`.team/交接箱/2026-07-17-模拟试运行幕1至幕2只读综合验收/版本台账-v0.10.md`

- [ ] **步骤 1：保存演练包和 SHA256**

只保存无密码的生成包、日志摘要和校验文件；不得保存数据库完整快照到 Git 或聊天。

- [ ] **步骤 2：销毁隔离环境**

删除 `qms-r13b6-app`、`jewelry_qms_p0_r13b6` 和临时确认文件。保留 committed 测试和无秘密交接证据。

- [ ] **步骤 3：新鲜最终验证**

```bash
git diff --check
git status --short
curl http://127.0.0.1:8010/login/index
mysql jewelry_qms -e 'SELECT COUNT(*) FROM employee_appointments WHERE soft_delete=0'
```

验收：

- B6 定向测试通过；
- 隔离资源为 0；
- 现行任命仍为 0；
- 8010 HTTP 200；
- 未部署云端；
- 分支只包含计划内文件。

- [ ] **步骤 4：提交交接记录**

```bash
git add '.team/交接箱/2026-07-17-模拟试运行幕1至幕2只读综合验收/G-R13-B6-受控迁移实现-v0.1'
git commit -m "docs(p0): 记录受控迁移隔离演练"
```

## 计划自检

- 规格覆盖：人工确认、预检、快照说明、六类 SQL、迁移、回退、再迁移、SHA256、交接均有任务。
- 占位符：命令、类名、文件名、退出码和测试编号均已明确。
- 依赖顺序：失败测试 → 服务 → 命令 → 隔离数据库演练 → 交接。
- 范围：不包含 B7 现行库执行、云端部署、账号开放或模拟业务数据迁入。
