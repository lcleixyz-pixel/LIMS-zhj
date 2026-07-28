# 版本管理与 Git 规范

## 1. 仓库模型

本仓库采用单一现行应用加配套文档/知识资产的结构：

- `jewelry-qms/`：主开发目录，ThinkPHP 8 珠宝检测实验室 QMS
- `docs/`：架构、部署、验证、操作和版本说明
- `knowledge/`：知识索引与系统生成导出
- `现用文件/`：机构现用体系文件，按受控边界处理
- `整合/`：契约、技能套装和整合材料
- `.team/`：作战室协作和交接材料

FlinkISO、FlinkISO Lite 和 CakePHP legacy 参考代码已拆至独立归档仓，不再是本仓目录。主项目开发、部署和版本说明均以 `jewelry-qms/` 当前代码为准。

## 2. 分支策略

| 分支 | 用途 |
|------|------|
| `main` | 稳定可部署版本 |
| `develop` | 日常开发集成（可选） |
| `feature/*` | 功能分支，如 `feature/doc-import` |
| `fix/*` | 缺陷修复 |
| `codex/*` | Codex 辅助修改分支 |
| `archive/*` | 交付包、验证材料或历史快照归档分支 |

合并至 `main` 前须确认：

- `jewelry-qms` 可安装依赖
- `database/jewelry_qms.sql` 可导入
- 默认部署路径和文档已更新
- 不包含 `.env`、上传文件、运行时缓存或真实密码

远端历史分支的现状、是否已被 `main` 吸收、以及可否清理，见 [BRANCH_GOVERNANCE.md](BRANCH_GOVERNANCE.md)。判断项目当前事实时，以 `main` 和该分支治理说明为准，不以旧的 `codex/*` 草案分支为准。

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
| 工作区候选版本 | 2.3.0-rc.1 | 文件治理、语义追溯、记录更正、模板换版与中文交互优化 |
| jewelry-qms 候选版本 | 2.3.0-rc.1 | 记录在 `config/qms.php` 的 `candidate_version` |
| 稳定运行版本 | 2.2.0 | `config/qms.php` 默认值；8010 本轮保持不变 |
| 8021 治理试运行 | 2.3.0-rc.1 | 由 `QMS_APP_VERSION` 环境变量注入 |

历史标签：

- `v1.0.0`：工作区首次纳入 FlinkISO 参考项目和 Jewelry QMS 初版
- `v2.2.0`：缺陷治理、签批闸门与三波次第 1、2 波稳定版本
- `v2.3.0-rc.1`：治理试运行候选版本；仅本地整理，未合并 `main`、未推送、未部署 8010

候选版转为稳定版后的标签示例：

```bash
git tag -a v2.3.0 -m "Jewelry QMS 2.3.0"
git push origin v2.3.0
```

候选标签不得作为正式发布证据。转为稳定版本前，应完成 8021 集中验收、确认候选缺陷关闭、同步五个版本锚点，并另行取得合并、推送和 8010 部署授权。

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
- `rbac`
- `verify`

示例：

```text
feat(jewelry-qms): 文件控制支持按层级筛选
docs: 对齐 ThinkPHP 8 部署说明
```

## 5. 变更记录

日常变更与版本发布采用双台账分工：

- `docs/变更台账.md`：唯一日常主台账。每次已批准并完成验证的变更逐条追加，记录级别、批准、测试、commit 和部署状态。
- `CHANGELOG.md`：版本发布汇总。平时只保留 `[Unreleased]` 提示；发布新版本号时，从日常台账选择对使用者有影响的大项，按主题归拢一次。

不得要求同一项日常变更在两个文件中逐行重复。版本号、发布日期和 Git 标签形成时，必须同步更新 `CHANGELOG.md`、`config/qms.php`、README 版本表和 `docs/VERSIONING.md`。

候选版与稳定版并行时，使用双锚点：

- `candidate_version` 记录仓库当前候选版本；
- `stable_version` 记录当前稳定版本；
- `version` 默认采用稳定版本，只允许隔离试运行环境通过 `QMS_APP_VERSION` 显示候选版本。

这样可避免 8021 与 8010 共用源码挂载时，整理候选版本误改 8010 的运行标识。

## 6. 勿提交内容

见根目录 `.gitignore` 与 `jewelry-qms/.gitignore`：

- `.env`、`.env.*`
- `jewelry-qms/vendor/`
- `jewelry-qms/runtime/*`
- `jewelry-qms/public/uploads/*`
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

## 8. 参考项目边界

参考代码不再回填主仓。若确需更新独立归档仓：

1. 在独立归档仓核对来源、许可证和版本。
2. 与 `jewelry-qms` 功能开发分开提交。
3. 只有形成适用于主项目的原创分析结论时，才把不含第三方源码的说明材料带回 `docs/参考分析/`。

禁止把第三方参考源码、标准原件或旧系统运行产物重新提交到主仓。
