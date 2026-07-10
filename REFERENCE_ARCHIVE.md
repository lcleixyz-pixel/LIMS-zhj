# 参考系统归档说明

> 2026-07-09 拆仓时建立。本文件告诉团队：那些参考系统去哪了。

## 已移至独立归档仓

以下三个目录原在主仓根目录，已于 2026-07-09 移至独立归档仓：

| 原目录 | 是什么 | 为什么移走 |
|---|---|---|
| `flinkiso/` | CakePHP 旧 LIMS 本地部署版（参考实现） | 参考性质，主项目零依赖 |
| `flinkiso-lite-master/` | 旧 LIMS 精简版（参考实现） | 同上 |
| `jewelry-qms-legacy/` | jewelry-qms 的 CakePHP 前任 | 已被 ThinkPHP 现版取代 |

## 归档仓位置

`../LIMS-zhj-reference/`（与本仓同级，即 `01-项目代码/LIMS-zhj-reference/`）

归档仓是独立 git 仓库，保留这三套系统的完整文件（快照式，不带主仓历史）。要查旧 LIMS 怎么实现的，去那里翻。

## 主仓里的历史没丢

拆分用的是 `git rm`（不是历史改写），所以这三个目录在主仓 git log 里的完整历史仍然保留。需要查它们当初怎么进来的，`git log -- flinkiso/` 之类还能看到。

## 为什么拆

主仓要做团队审计。这三个目录合计约 1.1 万文件、173M，留在主仓会：干扰审计视线、拖慢克隆、让 `.git` 臃肿。它们是参考/历史，不是在用的代码，单独归档更清爽。

详见 `docs/superpowers/specs/2026-07-09-repo-split-archive-design.md`。
