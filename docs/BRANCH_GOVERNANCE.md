# 远端分支治理说明

本文档记录 `LIMS-zhj` 远端分支的当前用途、合并状态和后续维护建议。它面向项目负责人、协作者和 AI 代理，用来避免把历史分支、归档分支和主线开发混在一起。

状态核对时间：2026-07-08

核对来源：

- `git fetch --all --prune --tags`
- `git branch -r --merged origin/main`
- `git rev-list --count origin/main..origin/<branch>`
- GitHub PR 列表

## 1. 当前结论

- `main` 是唯一主线，最新远端提交为 `a40840c`，对应 PR `#22 Add QMS governance preimport rehearsal tooling`。
- 当前远端所有分支的分支头都已被 `origin/main` 包含，`origin/main..<branch>` 计数均为 `0`。
- `archive/qms-test-completed-2026-07-08` 原本用于保存 `.team/` 测试完成交付包；该分支已通过 PR `#21` 合入 `main`，因此 `.team/` 交付材料现在也存在于主线。
- `codex/qms-governance-core` 已通过 PR `#22` 合入 `main`，治理预导入、预演和测试完成标记工具已进入主线。
- 多个 `codex/qms-v22-*` 远端分支虽然对应的 PR 曾显示为关闭未合并，但分支提交已通过后续整合提交进入 `main`，当前不应再作为待合并开发分支处理。

## 2. 分支分类

| 分支 | 当前用途 | 状态 | 处理建议 |
|------|----------|------|----------|
| `main` | 稳定主线和默认协作目标 | 当前唯一主线 | 后续功能、文档和修复均以此为基准开分支 |
| `archive/qms-test-completed-2026-07-08` | QMS 测试完成交付包归档 | 已合入 `main` | 可保留作审计索引；若后续清理远端分支，先确认 `.team/` 是否仍需在主线保留 |
| `codex/qms-governance-core` | QMS 治理预导入和测试完成工具 | 已通过 PR `#22` 合入 `main` | 不再继续开发；新治理改动从 `main` 另开分支 |
| `codex/docker-dev-env` | Docker 开发环境和知识导出补充 | 已通过 PR `#20` 合入 `main` | 可保留作历史索引，或在确认后删除远端分支 |
| `codex/record-qms-product-flow` | 记录表单和只读 Copilot 上下文流 | 已通过 PR `#18` 合入 `main` | 不再作为活动开发分支 |
| `codex/qms-ai-chat-assistant` | QMS AI 聊天助手 | 已通过 PR `#17` 合入 `main` | 不再作为活动开发分支 |
| `codex/qms-current-files-seed` | 现用文件结构化导入修正 | 已通过 PR `#16` 合入 `main` | 不再作为活动开发分支 |
| `cursor/jewelry-qms-p1` | 依赖和中间件类型修正 | 已通过 PR `#19` 合入 `main` | 不再作为活动开发分支 |
| `codex/docs-align-thinkphp8` | ThinkPHP 8 文档和策划中心基线 | 已合入 `main` | 保留为早期基线索引即可 |
| `codex/qms-v22-*` | v2.2 分阶段草案分支 | 分支头已被 `main` 包含 | 不再逐个恢复 PR；若要重启某项能力，应从 `main` 重开新分支并重新验证 |

## 3. 后续协作规则

1. 以 `main` 为准判断项目当前状态，不再以旧的 `codex/*` 分支作为事实来源。
2. 新工作从 `main` 开短生命周期分支，例如 `codex/qms-doc-governance-refresh`。
3. 交付包、验证材料、`.team/` 资料如果只是过程证据，优先放入归档分支或发布附件；只有确需长期随项目一起保存时才进入 `main`。
4. 关闭但未合并的旧 PR 不等于代码缺失；先用 `git branch -r --merged origin/main` 和 `git rev-list --count origin/main..<branch>` 确认。
5. 删除远端历史分支前，先确认对应 PR、交付包和审计索引是否仍需要保留。

## 4. 建议的远端清理顺序

当前不自动删除任何远端分支。若后续需要整理 GitHub 分支列表，建议按以下顺序人工确认：

1. 保留 `main`。
2. 暂时保留 `archive/qms-test-completed-2026-07-08`，直到确认 `.team/` 在主线中的保留策略。
3. 确认可删除已合入的短期工作分支：
   - `codex/qms-governance-core`
   - `codex/docker-dev-env`
   - `codex/record-qms-product-flow`
   - `codex/qms-ai-chat-assistant`
   - `codex/qms-current-files-seed`
   - `cursor/jewelry-qms-p1`
4. 最后再评估旧的 `codex/qms-v22-*` 草案分支，因为它们曾承载阶段性设计讨论，删除前最好确认文档和 PR 说明已足够。

## 5. 常用核对命令

```bash
git fetch --all --prune --tags
git branch -r --merged origin/main
git branch -r --no-merged origin/main
git rev-list --count origin/main..origin/<branch>
gh pr list --repo lcleixyz-pixel/LIMS-zhj --state all --limit 30
```

如果 `git branch -r --no-merged origin/main` 没有输出，说明当前没有远端分支包含主线尚未吸收的提交。
