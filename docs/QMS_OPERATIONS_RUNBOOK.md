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
