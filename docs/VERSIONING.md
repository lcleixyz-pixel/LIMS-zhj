# 版本管理与 Git 规范

## 1. 仓库模型

本工作区采用 **单仓库（Monorepo）** 管理：

- `flinkiso/` — 参考快照
- `flinkiso-lite-master/` — 参考快照
- `jewelry-qms/` — 主开发目录
- `docs/` — 跨项目文档

## 2. 分支策略

| 分支 | 用途 |
|------|------|
| `main` | 稳定可部署版本 |
| `develop` | 日常开发集成（可选） |
| `feature/*` | 功能分支，如 `feature/doc-import` |
| `fix/*` | 缺陷修复 |

合并至 `main` 前须：可安装、SQL 可执行、默认路径文档已更新。

## 3. 版本号（SemVer）

格式：`MAJOR.MINOR.PATCH`

| 递增 | 场景 |
|------|------|
| MAJOR | 不兼容的数据库结构、API、审批规则破坏性变更 |
| MINOR | 新模块、新字段、向后兼容功能 |
| PATCH | Bug 修复、文档、样式 |

**标签示例**：

- `v1.0.0` — 工作区首次纳入三项目 + 基础文档
- `v1.1.0` — jewelry-qms 模块表单完善
- `jewelry-qms-v1.0.0` — 仅 QMS 子项目里程碑（可选）

打标签：

```bash
git tag -a v1.0.0 -m "Initial monorepo: reference projects + jewelry-qms"
git push origin v1.0.0
```

## 4. 提交信息规范

```
<type>(<scope>): <subject>

<body>
```

| type | 说明 |
|------|------|
| feat | 新功能 |
| fix | 修复 |
| docs | 仅文档 |
| refactor | 重构 |
| chore | 构建、gitignore、依赖 |

| scope 示例 | `jewelry-qms`, `docs`, `flinkiso-lite` |

示例：

```
feat(jewelry-qms): 文件控制支持按层级筛选
docs: 补充部署指南 Windows IIS 章节
```

## 5. 变更记录

在项目根目录维护 `CHANGELOG.md`（随版本更新）。

## 6. 勿提交内容

见根目录 `.gitignore`：

- `tmp/cache`、`tmp/logs` 运行时文件
- `webroot/files` 用户上传
- 含真实密码的 `database.local.php`、`.env`
- 大型临时压缩包

## 7. 远程仓库与上传

```bash
cd flinkiso-ver-2
git init
git add .
git commit -m "chore: initial monorepo with reference projects and jewelry-qms v1.0.0"
git branch -M main
git remote add origin <你的远程仓库 URL>
git push -u origin main
git push origin v1.0.0
```

使用 GitHub CLI 创建私有仓库示例：

```bash
gh repo create jewelry-lab-qms --private --source=. --remote=origin --push
```

## 8. 参考项目更新策略

参考项目目录视为**只读快照**。若需更新：

1. 从新来源覆盖对应子目录
2. 单独提交 `chore: update flinkiso-lite reference to <date>`
3. 在 `CHANGELOG.md` 记录来源与版本

避免与 `jewelry-qms` 代码混在同一提交中大规模覆盖。
