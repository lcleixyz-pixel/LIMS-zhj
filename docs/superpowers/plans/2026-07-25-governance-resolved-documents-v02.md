# GOV-TRIAL/0.2 连续修订解析稿实施计划

> **For Codex:** REQUIRED SUB-SKILL: Use executing-plans to implement this plan task-by-task.

**Goal:** 在 8021 隔离环境生成“现用全文 + 已签认精确补丁”的连续可读解析稿，并以冲突阻断、来源哈希和 8010 不变性证明其可追溯、可试运行。

**Architecture:** 新增无副作用的补丁引擎和来源清单服务，先在内存中完成基线校验、补丁应用和完整性检查，再由解析稿服务生成交接包；只有显式 `--apply` 且通过 8021 环境闸门时才写入试运行数据库。现有 `QmsDocumentStructureService` 继续负责结构化块和连续渲染，不直接改写现用 Word 或 `knowledge/internal/`。

**Tech Stack:** PHP 8.2、ThinkPHP 8、MySQL 8、现有 QMS 服务与命令体系、Markdown/JSON 交接件、Docker Compose 8021 隔离环境。

---

## Task 1：建立确定性补丁引擎

**Files:**
- Create: `jewelry-qms/app/service/GovernedTrialPatchEngine.php`
- Test: `jewelry-qms/tests/qms_governed_trial_patch_engine_smoke.php`

**Step 1: Write the failing test**

覆盖 `replace_exact`、`insert_after_heading`、`delete_exact`、`append_record_requirement`，以及锚点缺失/多义、旧文哈希不符、未签认来源、无依据删除、补丁重叠和明确取代关系。

**Step 2: Run test to verify it fails**

Run:

```bash
docker exec lims-zhj-governance-trial-20260724-app-1 php tests/qms_governed_trial_patch_engine_smoke.php
```

Expected: FAIL，因为补丁引擎尚不存在。

**Step 3: Write minimal implementation**

实现只统一换行、不做模糊匹配的纯文本引擎。返回修订正文、已应用补丁、阻断冲突、提醒和未修改区段校验结果；单项冲突时保留该项原文。

**Step 4: Run test to verify it passes**

Run 同上。Expected: PASS。

**Step 5: Commit**

```bash
git add jewelry-qms/app/service/GovernedTrialPatchEngine.php jewelry-qms/tests/qms_governed_trial_patch_engine_smoke.php
git commit -m "feat(qms): 增加治理解析稿确定性补丁引擎"
```

## Task 2：建立签认来源清单与专项冲突审查

**Files:**
- Create: `jewelry-qms/app/service/GovernedTrialResolvedManifestService.php`
- Create: `jewelry-qms/app/service/GovernedTrialConflictReviewService.php`
- Test: `jewelry-qms/tests/qms_governed_trial_resolved_manifest_smoke.php`

**Step 1: Write the failing test**

要求所有自动补丁同时具有目标文件、操作、唯一锚点、旧文哈希、替换文字、内容来源和终局签认来源；候选、待审或来源哈希漂移必须阻断。专项审查覆盖设计中的 2018/2022、职责权限、抽样/分包/留样、记录编号、保存期限和依据退场问题。

**Step 2: Run test to verify it fails**

```bash
docker exec lims-zhj-governance-trial-20260724-app-1 php tests/qms_governed_trial_resolved_manifest_smoke.php
```

Expected: FAIL，因为清单和审查服务尚不存在。

**Step 3: Write minimal implementation**

从 G1 收官/终局签认和其明确引用的候选内容建立机器可读清单；内容文件与签认文件分别记录 SHA-256。无法唯一落点的已签认事项形成阻断冲突，不静默省略或猜测改写。

**Step 4: Run test to verify it passes**

Run 同上。Expected: PASS。

**Step 5: Commit**

```bash
git add jewelry-qms/app/service/GovernedTrialResolvedManifestService.php jewelry-qms/app/service/GovernedTrialConflictReviewService.php jewelry-qms/tests/qms_governed_trial_resolved_manifest_smoke.php
git commit -m "feat(qms): 建立签认补丁清单与冲突审查"
```

## Task 3：生成连续正文和治理交接包

**Files:**
- Create: `jewelry-qms/app/service/GovernedTrialResolvedDocumentService.php`
- Test: `jewelry-qms/tests/qms_governed_trial_resolved_documents_smoke.php`
- Output: `.team/交接箱/2026-07-25-8021治理体系试运行装配/GOV-TRIAL-0.2/`

**Step 1: Write the failing test**

要求只读预检能识别 1 份手册、37 份程序及附录 14～16；生成连续正文、逐项修订对照、冲突总表、装配清单、验证报告和版本台账，并在每份正文页首标注 SIM、版本、批次、冲突数量及非正式文件提示。

**Step 2: Run test to verify it fails**

```bash
docker exec lims-zhj-governance-trial-20260724-app-1 php tests/qms_governed_trial_resolved_documents_smoke.php
```

Expected: FAIL，因为解析稿服务尚不存在。

**Step 3: Write minimal implementation**

读取现用文件转换文本与来源哈希，在内存应用补丁，逐文件生成结果；采用临时目录后原子替换目标目录。单个文件冲突不妨碍其他文件生成，但该文件状态只能为 `draft`。

**Step 4: Run test to verify it passes**

Run 同上。Expected: PASS。

**Step 5: Commit**

```bash
git add jewelry-qms/app/service/GovernedTrialResolvedDocumentService.php jewelry-qms/tests/qms_governed_trial_resolved_documents_smoke.php
git commit -m "feat(qms): 生成治理连续正文与审查交接包"
```

## Task 4：接入 8021 命令、版本世系和最小页面入口

**Files:**
- Create: `jewelry-qms/app/command/QmsGovernedTrialResolve.php`
- Modify: `jewelry-qms/config/console.php`
- Modify: `jewelry-qms/app/controller/PlanningStructure.php`
- Modify: `jewelry-qms/app/view/planning_structure/view.html`
- Test: `jewelry-qms/tests/qms_governed_trial_resolved_runtime_smoke.php`

**Step 1: Write the failing test**

验证默认命令只预检；`--apply` 缺少 `QMS_TRIAL_MODE=true`、批次确认或 8021 数据库特征时拒绝写入；通过闸门后保留 `0.1` 并建立 `0.2`，有冲突文件为 `draft`，无冲突文件为 `trial_ready`，重复执行幂等。页面提供连续正文、修订对照和冲突审查入口，阻断冲突存在时不能提交审核。

**Step 2: Run test to verify it fails**

```bash
docker exec lims-zhj-governance-trial-20260724-app-1 php tests/qms_governed_trial_resolved_runtime_smoke.php
```

Expected: FAIL，因为命令和页面入口尚不存在。

**Step 3: Write minimal implementation**

新增 `qms:resolve-governed-trial`，显式参数为 `--apply`、`--ack-signed-governance` 和 `--output`。数据库写入前完成全部内存校验并开启事务；沿用结构化文件服务进行分块、渲染、存档和变更记录。页面只增加三个业务语言入口和阻断提示，不整体翻新。

**Step 4: Run test to verify it passes**

Run 同上，并运行相关现有结构化文件与签批测试。Expected: PASS。

**Step 5: Commit**

只暂存本任务确实修改的文件，保留工作区其他既有改动。

## Task 5：在 8021 装配并完成隔离验证

**Files:**
- Update: `.team/交接箱/2026-07-25-8021治理体系试运行装配/GOV-TRIAL-0.2/验证报告.md`
- Update: `.team/交接箱/2026-07-25-8021治理体系试运行装配/GOV-TRIAL-0.2/版本台账.md`

**Step 1: Freeze 8010 and 8021 fingerprints**

记录 8010/3307 的文件/版本/审批关键表行数及哈希，并记录 8021 装配前指纹。

**Step 2: Run inspect-only**

```bash
docker exec lims-zhj-governance-trial-20260724-app-1 php think qms:resolve-governed-trial
```

确认只生成预检信息，不写数据库。

**Step 3: Apply to 8021**

```bash
docker exec lims-zhj-governance-trial-20260724-app-1 php think qms:resolve-governed-trial --apply --ack-signed-governance
```

只允许写 8021/3319。存在阻断冲突的文件保留连续预览但不得进入模拟签批。

**Step 4: Verify**

运行四项新增测试和相关回归测试；核对 38 份连续正文、附录、来源哈希、冲突总表、版本世系、幂等性及页面入口。再次计算 8010/3307 指纹，必须与装配前一致。

**Step 5: Commit**

只提交代码、测试、计划和适合版本管理的 Markdown/JSON 交接件，不提交运行时临时文件或用户既有改动。

