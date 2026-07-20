# 远程仓库与协作说明

当前仓库已初始化 Git，并已配置 GitHub 远程仓库。

## 当前远程

```bash
git remote -v
```

预期输出：

```text
origin  https://github.com/lcleixyz-pixel/LIMS-zhj.git (fetch)
origin  https://github.com/lcleixyz-pixel/LIMS-zhj.git (push)
```

## 推送当前分支

```bash
git status
git push -u origin <branch-name>
```

若需要创建新分支：

```bash
git switch -c feature/<name>
git push -u origin feature/<name>
```

## 标签

历史初始标签：

```text
v1.0.0
```

`v1.0.0` 表示首次纳入 FlinkISO 参考项目和 Jewelry QMS 初版。当前应用版本以 `CHANGELOG.md` 与 `jewelry-qms/config/qms.php` 为准。

后续发布标签示例：

```bash
git tag -a v2.1.0 -m "Jewelry QMS 2.1.0"
git push origin v2.1.0
```

## 验证命令

```bash
git status --short --branch
git remote -v
git log --oneline -5
git tag -n --list
```

## 协作建议

- 主分支：`main` 保持稳定可部署。
- 日常开发：使用 `feature/*`、`fix/*` 或 `codex/*` 分支。
- 参考项目目录只读使用，避免与主项目功能开发混在同一提交。
- 敏感配置不要提交：`.env`、真实数据库密码、上传文件、运行时缓存。

## 仓库体积说明

本 Monorepo 含两个完整 FlinkISO 参考项目，首次克隆和推送体积较大属正常现象。若后续只需要托管主项目，可另行拆分仅含 `jewelry-qms/` 的子仓库。
