# Phase 4 · 真实换版试点启动包 v0.1

> 人审主导。本包只组织路径与闸门，**不**把 SIMULATED 标成完成，**不**倒沙箱数据进 8010。

## 起点（复用既有治理包）

| 材料 | 路径 |
|------|------|
| 最小试点 5 批次 | `.team/交接箱/2026-07-07-第五版候选修订准备/governance_closure_pilot_pack/` |
| 人工执行工作簿 | `.../governance_closure_pilot_operator_workbook/` |
| 真实执行交回 | `.../governance_closure_pilot_operator_handback/`（须真实证据，禁 SIMULATED） |
| 体系问题 UF 汇总 | `.team/交接箱/2026-07-20-两轮体系文件问题与修订候选汇总/体系文件问题与修订候选汇总-v0.3.md` |
| 签批预演稿参考 | `.team/交接箱/2026-07-19-LIMSzhj排演式持续治理第2轮/文件治理/待签批升版预演稿/` |

## 试点流程（每批）

1. **预览层审阅** — 使用治理预览 / 批准人可视化预览层（8013 或独立预览），记录意见。
2. **DocuSeal 签批** — 启用 compose profile `signing`；QM/TM 用 QMS 账号邮箱；控制台管理口令独立。
3. **文控真实受控修订** — 仅在人审通过且签批完成后，于**现用文控流程**登记；不从沙箱 dump 灌 8010。
4. **回填** — 按 `governance_closure_pilot_return_preview` → 源工作台补丁预演 → 人工回填。

## 消化目标（不假装已清零）

- 67 项人审 pending
- 392 条治理阻断任务
- 模板**内容**重做随人审批次进入（P 组），不回溯改波次 1 机制门禁

## 本会话已具备的系统前提

- G-3 状态守卫
- DocuSealService + Webhook D-4 四分支
- S-1 POST+token 写路径

## 下一步（等人）

选定 5 个低阻断批次责任人与日期；开启 signing profile；按工作簿填写真实证据。
