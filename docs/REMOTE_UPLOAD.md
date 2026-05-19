# 远程仓库上传指南

本地已完成 Git 初始化、首次提交及标签 `v1.0.0`。因当前环境未安装 GitHub CLI（`gh`），请按下列方式之一将代码推送到远程。

## 方式一：GitHub

1. 在 GitHub 新建仓库（建议私有），名称如 `jewelry-lab-qms`，**不要**勾选「Initialize with README」
2. 在项目根目录执行：

```powershell
cd c:\Users\Martyr\Downloads\flinkiso-ver-2
git remote add origin https://github.com/<你的用户名>/jewelry-lab-qms.git
git branch -M main
git push -u origin main
git push origin v1.0.0
```

## 方式二：Gitee（码云）

```powershell
cd c:\Users\Martyr\Downloads\flinkiso-ver-2
git remote add origin https://gitee.com/<你的用户名>/jewelry-lab-qms.git
git branch -M main
git push -u origin main
git push origin v1.0.0
```

## 方式三：自建 Git 服务器

```powershell
git remote add origin git@your-server:/repos/flinkiso-ver-2.git
git push -u origin main --tags
```

## 验证

```powershell
git status
git remote -v
git log --oneline -3
git tag
```

## 仓库体积说明

本 Monorepo 含两个完整 FlinkISO 参考项目（约 1.1 万文件），首次推送体积较大，属正常现象。若仅需托管定制项目，可另建仅含 `jewelry-qms/` 的子仓库（需 `git subtree` 或拆分，联系开发协助）。

## 协作建议

- 主分支：`main` 保护，合并需评审
- 开发分支：`develop` 或 `feature/*`
- 敏感配置：勿提交真实 `database.php` 密码，使用 `database.php.example`（后续版本补充）
