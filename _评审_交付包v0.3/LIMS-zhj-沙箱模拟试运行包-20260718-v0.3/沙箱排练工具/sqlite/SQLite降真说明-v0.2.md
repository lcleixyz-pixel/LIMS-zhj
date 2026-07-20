# SQLite 降真说明 v0.2

## 1. 结论口径

SQLite 路线可作为“沙箱排练场”，用于快速模拟整季流程、存档读档和 AI 自动跑量。

它不等价于生产 MySQL，也不替代最终保真复核。

## 2. 为什么仍然值得做

- SQLite 数据库是单文件，复制即可存档；
- 与 PHP 进程同环境时，fakeclock 更容易覆盖时间；
- 在网络受限沙箱中无需安装 MySQL 服务；
- 适合快速发现页面、流程、角色、表格、溯源链和提醒逻辑问题。

## 3. 必须复核的兼容点

根据 G4-R12 源码快速普查，SQLite 路线至少要关注：

| 类型 | 风险 |
|---|---|
| 初始化 SQL | `database/jewelry_qms.sql` 中有 `NOW()` |
| 迁移 SQL | 部分迁移含 `NOW()`、`REGEXP`、MySQL 索引语法 |
| DDL | `ALTER TABLE ... ADD KEY`、`DROP INDEX` 等需转换 |
| 聚合 | `GROUP_CONCAT(... ORDER BY ... SEPARATOR ',')` 需验证或改写 |
| ORM 配置 | 当前 `config/database.php` 默认 MySQL，SQLite 需单独配置 |

## 4. 推荐判定标准

沙箱排练输出应分级：

- `sandbox-pass`：SQLite/fakeclock 场景通过，可进入真栈复核；
- `sandbox-noise`：仅由 SQLite 降真差异引起，不直接判系统缺陷；
- `real-stack-required`：涉及 SQL、权限、并发、文件上传、PDF、时间一致性等关键点，必须用 MySQL/Docker 真栈复核；
- `product-defect`：与存储引擎无关的页面、流程、表格、角色或质量管理逻辑缺陷。

