# 版本管理与 Git 规范

## 1. 仓库模型

本工作区采用单仓库（Monorepo）管理：

- `jewelry-qms/`：主开发目录，ThinkPHP 8 珠宝检测实验室 QMS
- `jewelry-qms-legacy/`：CakePHP 旧版归档
- `flinkiso/`：FlinkISO On-Premise 参考快照
- `flinkiso-lite-master/`：FlinkISO Lite 参考快照
- `docs/`：跨项目文档

参考项目目录视为只读快照。主项目开发、部署和版本说明均以 `jewelry-qms/` 当前代码为准。

## 2. 分支策略

| 分支 | 用途 |
|------|------|
| `main` | 稳定可部署版本 |
| `develop` | 日常开发集成（可选） |
| `feature/*` | 功能分支，如 `feature/doc-import` |
| `fix/*` | 缺陷修复 |
| `codex/*` | Codex 辅助修改分支 |

合并至 `main` 前须确认：

- `jewelry-qms` 可安装依赖
- `database/jewelry_qms.sql` 可导入
- 默认部署路径和文档已更新
- 不包含 `.env`、上传文件、运行时缓存或真实密码

## 3. 版本号（SemVer）

格式：`MAJOR.MINOR.PATCH`

| 递增 | 场景 |
|------|------|
| MAJOR | 不兼容的数据库结构、API、审批规则破坏性变更 |
| MINOR | 新模块、新字段、向后兼容功能 |
| PATCH | Bug 修复、文档、样式 |

当前版本：

| 组件 | 当前版本 | 说明 |
|------|----------|------|
| 工作区 | 2.1.0 | ThinkPHP 8 主项目 + P1 业务深化 |
| jewelry-qms | 2.1.0 | 与 `config/qms.php` 和 `CHANGELOG.md` 保持一致 |

历史标签：

- `v1.0.0`：工作区首次纳入 FlinkISO 参考项目和 Jewelry QMS 初版

后续标签示例：

```bash
git tag -a v2.1.0 -m "Jewelry QMS 2.1.0"
git push origin v2.1.0
```

## 4. 提交信息规范

```text
<type>(<scope>): <subject>

<body>
```

| type | 说明 |
|------|------|
| `feat` | 新功能 |
| `fix` | 修复 |
| `docs` | 仅文档 |
| `refactor` | 重构 |
| `chore` | 构建、忽略规则、依赖等维护 |

scope 示例：

- `jewelry-qms`
- `docs`
- `flinkiso-lite`

示例：

```text
feat(jewelry-qms): 文件控制支持按层级筛选
docs: 对齐 ThinkPHP 8 部署说明
```

## 5. 变更记录

在项目根目录维护 `CHANGELOG.md`。发生以下变更时应同步更新：

- 业务功能变化
- 数据库结构变化
- 部署方式变化
- 版本号变化
- 重要修复或安全调整

## 6. 勿提交内容

见根目录 `.gitignore` 与 `jewelry-qms/.gitignore`：

- `.env`、`.env.*`
- `jewelry-qms/vendor/`
- `jewelry-qms/runtime/*`
- `jewelry-qms/public/uploads/*`
- 参考项目旧版 `tmp/cache`、`tmp/logs`、`webroot/files` 运行产物
- 含真实密码的本地配置
- 大型临时压缩包

## 7. 远程仓库与上传

当前仓库已配置 GitHub 远程：

```bash
git remote -v
```

预期形态：

```text
origin  https://github.com/lcleixyz-pixel/LIMS-zhj.git (fetch)
origin  https://github.com/lcleixyz-pixel/LIMS-zhj.git (push)
```

常用推送流程：

```bash
git status
git add <changed-files>
git commit -m "docs: align documentation with ThinkPHP 8 project"
git push -u origin <branch-name>
```

## 8. 参考项目更新策略

参考项目目录视为只读快照。若确需更新：

1. 从可信来源覆盖对应子目录。
2. 单独提交，例如 `chore: update flinkiso-lite reference to 2026-05-21`。
3. 在 `CHANGELOG.md` 记录来源、版本和目的。

避免把参考项目大规模覆盖与 `jewelry-qms` 功能开发混在同一提交中。
