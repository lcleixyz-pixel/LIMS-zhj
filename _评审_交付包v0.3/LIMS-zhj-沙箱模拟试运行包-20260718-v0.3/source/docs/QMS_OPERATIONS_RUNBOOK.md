# 运行、验证与维护说明

本文档说明 `jewelry-qms` 在本机开发、验证、运行产物清理和常见维护中的操作方式。它不替代生产部署指南，生产部署仍以 [DEPLOYMENT.md](DEPLOYMENT.md) 为准。

## 1. 本机开发服务

进入主项目目录：

```bash
cd /Users/lc.leixyz/LIMS-zhj/jewelry-qms
```

启动开发服务：

```bash
php think run -H 127.0.0.1 -p 8010
```

访问地址：

```text
http://127.0.0.1:8010
```

默认账号：

```text
admin / password
```

如果需要让服务留在后台，可使用 tmux：

```bash
tmux new-session -d -s jewelry-qms-8010 -c /Users/lc.leixyz/LIMS-zhj/jewelry-qms 'php think run -H 127.0.0.1 -p 8010'
```

查看监听：

```bash
lsof -nP -iTCP:8010 -sTCP:LISTEN
```

停止监听进程：

```bash
lsof -tiTCP:8010 -sTCP:LISTEN | xargs kill
```

## 2. 数据库初始化

创建数据库：

```bash
mysql -u root -p -e "CREATE DATABASE IF NOT EXISTS jewelry_qms CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

导入初始化脚本：

```bash
mysql -u root -p jewelry_qms < database/jewelry_qms.sql
```

数据库连接通过 `.env` 配置，业务配置位于 `config/qms.php`。

## 3. 策划中心初始化

登录后推荐从页面执行初始化：

1. 进入 `/planning/index`。
2. 点击“初始化策划骨架”。
3. 进入 `/planning/sources`，初始化或上传外部依据。
4. 抽取条款。
5. 进入 `/planning/structures`，初始化文件结构化。
6. 进入 `/planning/structures/package`，渲染系统包。

对应服务方法包括：

- `QmsElementService::seedAll()`
- `QmsElementService::upsertExternalClauses()`
- `QmsDocumentStructureService::seedAll()`
- `QmsDocumentStructureService::renderSystemPackage()`

## 4. 常用验证命令

策划中心 smoke：

```bash
for f in tests/qms_*_smoke.php; do php "$f" || exit $?; done
```

记录表格批量模板 smoke：

```bash
php tests/record_forms_batch_smoke.php
```

文档链接检查可使用 `rg` 快速查找：

```bash
rg -n "qms_import_batches|qms_import_candidates|QmsTraceLink|QmsRequirementElement" app route tests docs
```

页面 smoke：

```bash
tmp_cookie=$(mktemp)
curl -sS -o /tmp/qms-login.html -w '%{http_code}' -c "$tmp_cookie" -b "$tmp_cookie" \
  -L -X POST -d 'username=admin&password=password' \
  http://127.0.0.1:8010/login/index
curl -sS -o /tmp/qms-package.html -w '%{http_code}' -c "$tmp_cookie" -b "$tmp_cookie" \
  http://127.0.0.1:8010/planning/structures/package
curl -sS -o /tmp/qms-trace.html -w '%{http_code}' -c "$tmp_cookie" -b "$tmp_cookie" \
  http://127.0.0.1:8010/planning/traceability
rm -f "$tmp_cookie"
```

检查页面是否出现 ThinkPHP 错误标记：

```bash
rg -n "系统发生错误|Fatal error|Parse error|Stack trace" /tmp/qms-package.html /tmp/qms-trace.html
```

## 5. 运行产物边界

`jewelry-qms/runtime/` 是运行产物目录，默认不纳入 Git。

重要子目录：

| 路径 | 说明 | 清理策略 |
|------|------|----------|
| `runtime/log/` | ThinkPHP 开发日志 | 可清理 |
| `runtime/qms_archive/` | 正式归档文件 | 谨慎保留 |
| `runtime/qms_sources/` | 外部依据处理归档 | 谨慎保留 |
| `runtime/qms_structured/` | 结构化 Markdown、系统包、归档 | 仅清理明确的开发历史 |
| `runtime/session/` | 会话 | 可清理 |
| `runtime/temp/` | 临时文件 | 可清理 |

## 6. 保守瘦身流程

只清理开发日志和系统包历史，不动正式归档：

```bash
cd /Users/lc.leixyz/LIMS-zhj/jewelry-qms

lsof -tiTCP:8010 -sTCP:LISTEN | xargs kill

find runtime/log -type f -name '*.log' -delete
find runtime/log -type d -empty -delete
mkdir -p runtime/log
```

系统包归档建议只保留最新 10 份。清理时应同步重写：

```text
runtime/qms_structured/system_package/archive/manifest.json
```

保留当前系统包：

```text
runtime/qms_structured/system_package/qms_system_package.md
```

不要清理：

- `runtime/qms_archive/`
- `runtime/qms_sources/`
- 现用文件
- 参考文件
- 数据库迁移
- 源码和测试

## 7. Git 提交前检查

建议提交前执行：

```bash
git status --short
git diff --check
```

如果有暂存内容：

```bash
git diff --cached --check
```

不得提交：

- `.env`
- `vendor/`
- `runtime/`
- `public/uploads/`
- 大型临时压缩包
- Office 临时锁文件，例如 `.~*.docx`

## 8. 常见问题

### 8010 端口已占用

```bash
lsof -nP -iTCP:8010 -sTCP:LISTEN
```

确认是旧开发服务后停止：

```bash
lsof -tiTCP:8010 -sTCP:LISTEN | xargs kill
```

### 页面 500 或系统错误

1. 临时确认 `.env` 中 `APP_DEBUG`。
2. 查看 `runtime/log/`。
3. 用 `rg` 搜索错误堆栈中的控制器、服务或字段名。
4. 优先运行对应 smoke 测试复现。

### 系统包页面变慢

通常是系统包归档或开发日志膨胀。先查看大小：

```bash
du -sh . runtime runtime/log runtime/qms_structured/system_package/archive
```

按保守瘦身流程清理运行产物。

### 要素页面出现编号

这是设计违规。要素页面不应把 `6.2`、`7.8` 等显示为要素编号。编号应只作为关联条款或手册章节信息出现。

### 条款抽取后没有映射

进入 **条款库** 查看未匹配条款：

- 可映射到已有要素。
- 确无归属时创建本地补充要素。
- 智能体建议仅供人工复核，不自动写正式映射。

## 9. 建议保留的验证证据

每次较大变更后，建议在 PR 或变更记录中写明：

- 执行的 smoke 测试命令。
- 页面 smoke 的 HTTP 状态。
- 系统包 manifest 数量。
- 运行目录清理前后大小。
- 是否新增外部依据、结构化文件或记录表格 schema。

## 10. 系统失效应急响应与恢复（CL01 7.11.3 e）

本流程适用于 LIMS 无法访问、持续严重报错、数据疑似损坏、异常丢失或未经授权变更等可能影响实验室服务能力或数据完整性的情形。目标是控制影响、维持必要业务、恢复可信状态，并留下系统失效、应急措施和纠正措施记录。

### 10.1 发现、报告与初步隔离

1. 发现者立即停止受影响功能的录入、审批、发布、导入和批量操作，保留报错页面、发生时间和当时正在执行的操作。
2. 立即报告系统管理员和质量负责人；涉及检测结果或客户交付时，同时通知相关业务负责人。
3. 系统管理员记录影响范围：受影响时间段、用户、模块、样品或记录、最后确认正常的时间，以及是否存在数据完整性风险。
4. 原因未判明前，不得反复重启、覆盖数据库、删除日志、清理运行目录或执行未经验证的恢复脚本。确需隔离时，应先保全日志、数据库状态和相关文件副本。
5. 质量负责人根据影响决定暂停相关实验室活动、限制系统访问，或启用 10.2 的替代流程。

### 10.2 应急期间的纸面或离线替代

- 使用现行受控记录模板的打印版或经质量负责人确认的离线副本，保持原有编号、样品标识、时间、执行人和复核人信息。
- 每份临时记录标注“系统失效期间临时记录”，记录开始和结束时间；不得使用来源不明或失效版本的表单。
- 临时记录按时间顺序保存，纸质原件或只读电子原件不得在补录后销毁。
- 无法保证数据完整性、计算正确性或结果授权时，应暂停相关结果出具，不以临时流程绕过必要复核。

### 10.3 备份、恢复与恢复验证

1. 系统管理员确认故障边界后，优先在隔离环境验证修复或恢复方案；需要数据库恢复时，使用已确认的备份副本和受控命令。
2. 恢复记录至少包括：事件编号、备份文件及时间点、操作人、执行命令或步骤、开始和结束时间、异常信息和回退方式。
3. 恢复完成后，由系统管理员执行技术核验，由质量负责人或其指定业务人员执行独立业务核验。核验至少覆盖登录与角色权限、受控文件、关键记录数量和抽样内容、审计或变更留痕、记录填写与检索。
4. 核验发现差异时继续保持隔离，不得直接开放业务使用。只有技术核验和业务核验均通过，并由质量负责人批准后，才可恢复服务。
5. 备份恢复方法及既有演练证据见 `.team/交接箱/2026-07-11-体系可信度A2A3演练/A2-备份恢复演练记录.md`。

### 10.4 临时记录补录与对账

- 系统恢复后，由原执行人或经授权人员补录临时记录，并标注“应急补录”、原始记录编号、原始发生时间、补录时间和补录人。
- 另一名授权人员逐项核对系统记录与纸面或离线原件；核对完成前不得把补录数据作为最终受控证据。
- 补录后保留原始临时记录，并建立原件与系统记录之间的可追溯关系。禁止未经逐项核对的批量导入。

### 10.5 事件记录与纠正措施

- 系统管理员形成系统失效事件记录，内容包括现象、影响、时间线、应急措施、数据保护措施、恢复步骤、恢复验证和批准恢复结论。
- 质量负责人评估事件是否构成不符合；需要时进入 CAPA，分析根本原因，制定纠正措施，明确责任人和期限，并验证措施有效性后关闭。
- 纠正措施可包括配置或代码修复、备份策略调整、权限收紧、监控告警、操作规程修订和人员培训，但不得只记录“已恢复”而省略原因及防止再发措施。
- 事件记录、临时记录、恢复证据、补录对账和 CAPA 记录按相应受控记录保存期限归档。

### 10.6 职责

- 发现者：停止风险操作、保留现场信息并立即报告。
- 系统管理员：隔离、证据保全、技术恢复和技术核验。
- 质量负责人：决定业务暂停或替代方式、组织业务核验、批准恢复并决定是否启动 CAPA。
- 业务负责人或授权复核人：核对受影响记录及应急补录内容。

> 本章节目前是技术备忘。正式受控发布前，应由机构确认职责和记录保存要求，并至少完成一次系统失效桌面推演或等效演练；制度文本本身不能替代演练证据。
