# SIM 覆盖应用清单 · 签批包落地 v0.1

> 轮次：`SIGNOFF-APPLY-20260720`  
> 环境：8013（`lims-zhj-rehearsal-r2-main-20260719`）  
> 应用方式：解释/检查覆盖层装入容器 `runtime/signoff-apply-20260720/` + 复演 smoke；**不**改 145 现用基线模板、**不**改现用 Word  
> 当前状态：三增量 `sim_applied`；正式侧仍 `pending_human_approval`

## 1. 候选应用登记

| 增量 | 文件 | SHA256 | sim_status | formal_status |
|---|---|---|---|---|
| SIGNOFF-FILE-001 | `覆盖增量/01-手册覆盖增量-签批包落地-v0.1.md` | `81d28a7ba41d5e840819ff20c5cc709721904e7ddd320ca9a3440b91eb61f50f` | `sim_applied` | `pending_human_approval` |
| SIGNOFF-FILE-002 | `覆盖增量/02-程序与结构覆盖增量-签批包落地-v0.1.md` | `115fbdd57adc772649c20119b8720dd0fae65aa97df0e4624a7ebd7037cd2116` | `sim_applied` | `pending_human_approval` |
| SIGNOFF-FILE-003 | `覆盖增量/03-模板语义覆盖增量-签批包落地-v0.1.md` | `f9bf7a33ed733eeb6e03e014b024f63ed1a06d41984961af44c930eb58e366c5` | `sim_applied` | `pending_human_approval` |

容器路径：`/app/runtime/signoff-apply-20260720/`（与交接箱内容一致）。

## 2. 复演结果

| 项 | 结果 |
|---|---|
| SIM 纯度守卫 | PASS |
| 业务策略（含 NEG） | PASS（9 assertions） |
| 旧全量种子拒绝 | PASS |
| 环境契约 | PASS |
| 半年 SIM v0.2 runtime | PASS（382 assertions；NEG-01…08 写前拒绝） |
| 内审 CAPA 管评 runtime smoke | SKIP（wrapper 未暴露 ready_to_apply；改用 SQL 计数核验） |
| SQL：内审计划/日程/检查表/发现/CAPA/管评 | 1 / 2 / 132 / 8 / 10 / 1（与 R2 一致） |
| 现用基线模板 null_batch | 落地前 145 = 落地后 145 |
| SIM batch 模板 | 15 未变 |
| 非 SIM 员工 | 0 |

## 3. 闸门

```text
SIGNOFF-FILE-001/002/003 = sim_applied
replay_core = passed
formal_release = false
current_baseline_templates_modified = false
protected_ports_8010_8011_8012 = no_write_this_round
```

## 4. 与正式签批的关系

本清单证明签批包队列 A 已在 8013 **样板房落地**并可复演。  
真人批准、文控换版、生效仍以 `文件治理/` 签批包为准。
