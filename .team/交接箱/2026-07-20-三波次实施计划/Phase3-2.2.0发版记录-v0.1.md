# Phase 3 · 2.2.0 发版记录 v0.1

## 五处版本同步（已完成）

| # | 位置 | 结果 |
|---|------|------|
| 1 | `jewelry-qms/config/qms.php` | `2.2.0` |
| 2 | `docs/VERSIONING.md` | 工作区/jewelry-qms = 2.2.0 |
| 3 | `README.md` | 应用版本表 2.2.0 |
| 4 | `CHANGELOG.md` | 新增 `[2.2.0] - 2026-07-20` |
| 5 | `docs/ARCHITECTURE.md` | 当前版本叙述 2.2.0 |

流程图：`签批与换版流程图-v0.2.md`

台账：`docs/变更台账.md` 已追加发版准备行。

## 现用镜像重建（待单独授权）

计划要求含遗留 Poppler 的现用 Docker 镜像重建。**本会话未执行** `docker compose up --build` 于 8010，因属生产栈变更硬闸。

就绪命令（授权后在 `jewelry-qms/` 执行）：

```bash
cd jewelry-qms
# 先确认当前分支已合入拟发布提交
docker compose build app
docker compose up -d app
# 复验外部依据抽取与长表单保活；复跑冻结 smoke 子集
```

## 8010 数据对照

- Phase 0 dump 文件哈希会因 mysqldump 时间戳头变化，**不以单次 dump SHA 做稳定对照**。
- 稳定基线：`8010-checksums-phase0.txt`（CHECKSUM TABLE 全表）。
- 本会话对 8010 **零业务写**；代码仅在 `feature/wave1-defect-governance` worktree。

## Git 标签

待合入 `main` 后执行：`git tag -a v2.2.0 -m "Jewelry QMS 2.2.0"`（需用户授权推送）。
