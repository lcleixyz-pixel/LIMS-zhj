---
name: 三波次实施计划
overview: 按已拍板决策，将 jewelry-qms 缺陷治理→签批闸门→真实换版落成一份门禁制四阶段计划：本会话（Cursor）统一执行代码 + 任务卡/验收/台账/文档/发版编排；波次 1+2 完成后发 2.2.0，不定死日期。
todos:
  - id: phase0-ledger-branch
    content: Phase 0：补记变更台账（G4-R11/8013）+ feature/worktree/测试栈 + 冻结基线转储与 smoke 清单 + 批次任务卡模板 + 战役入口
    status: in_progress
  - id: batch-1-1
    content: 批次 1-1：本会话实现并自验 R-1（UUID/qms_next_number）+ R-2（五模块表单幂等）
    status: pending
  - id: batch-1-2
    content: 批次 1-2：R-3 模板机制三修 + R-4 留痕扩展 + R-6 枚举错配（管评校准合格率）
    status: pending
  - id: batch-1-3
    content: 批次 1-3：R-5 唯一性/合理性 + A 组 15 项（尊重 A-3→R-3、A-4→R-2 依赖）
    status: pending
  - id: batch-1-4-gate1
    content: 批次 1-4 M/E 组 + Phase 1 门禁（冻结 smoke 0 失败、8010 哈希一致）
    status: pending
  - id: batch-2-1-s
    content: 批次 2-1：S 安全六件套（GET 写改 POST+token 等，D 硬前置）
    status: pending
  - id: batch-2-2-docuseal
    content: 批次 2-2：DocuSeal 自托管 compose+SDK+账号对接（依赖 R-5）
    status: pending
  - id: batch-2-3-gate2
    content: 批次 2-3：签批闸门链路 + Phase 2 门禁（S-1/D-4/G-3）
    status: pending
  - id: phase3-220
    content: Phase 3：五处版本同步发 2.2.0 + 台账对账 + 流程图 v0.2 + 经授权重建镜像
    status: pending
  - id: phase4-pilot
    content: Phase 4：5 低阻断批次真实换版试点（人审→DocuSeal→文控，不倒沙箱）
    status: pending
isProject: false
---

# jewelry-qms 三波次实施计划（缺陷治理→签批闸门→真实换版）

> **面向执行者：** 本会话（Cursor）= 代码实现 + 批次任务卡 + 自验/门禁证据 + 台账/文档 + 发版编排。门禁制推进，不定死日期。步骤用复选框跟踪。
>
> **改口登记（2026-07-20）：** 原「Codex 执行代码、本会话只做任务包/验收」→ **本会话直接执行代码**（不再派 Codex）。波及：任务包改称「批次任务卡」（自用边界卡，非外包包）；Phase 1–2 全部由本会话在 worktree 内改代码+写 smoke。

**目标：** 一份计划管全三波次：先清 R/A/M/E 产品硬伤，再同批上 S 安全六件套 + D DocuSeal 签批闸门，发 2.2.0 后由人审主导真实换版试点。

**架构：** 隔离 worktree + 测试栈改代码；现用 8010 只读对照、前后转储哈希一致；SIMULATED 永不计入完成项。身份事实源 = QMS `users`；DocuSeal 控制台管理账号独立强口令。模板**内容**重做不进波次 1（只修机制），留给 P 组随人审消化。

**技术栈：** ThinkPHP8 / jewelry-qms、Docker Compose、PHP smoke、DocuSeal 自托管（新增）、Webhook 验签落 `qms_document_assets`。

**落盘（执行批准后）：**
- 主交付：`.team/交接箱/2026-07-20-三波次实施计划/`（`00-说明.md`、`版本台账.md`、`三波次实施计划-v0.1.md`、`批次任务卡模板-v0.1.md`、各批次实现记录与验收证据）
- 工程镜像：`docs/superpowers/plans/2026-07-20-jewelry-qms-三波次实施.md`

**缺陷编号映射（源）：** [`演练/LIMSzhj系统缺陷清单交开发团队v0.1.md`](演练/LIMSzhj系统缺陷清单交开发团队v0.1.md) 规律1–6 → R-1…R-6；P0 的 15 条 A 级 → A 组；[`不好用清单.md`](.team/不好用清单.md) 元数据项 → M 组。体系文件 UF 汇总（v0.3）属 P 组人审消化，不混入波次 1 代码门禁。

---

## 总序与门禁

```mermaid
flowchart LR
  P0[Phase0_开工准备] --> B11[批次1-1_R1R2]
  B11 --> B12[批次1-2_R3R4R6]
  B12 --> B13[批次1-3_R5与A组]
  B13 --> B14[批次1-4_M与E]
  B14 --> G1[Phase1门禁]
  G1 --> B21[批次2-1_S六件套]
  B21 --> B22[批次2-2_DocuSeal部署]
  B22 --> B23[批次2-3_签批闸门]
  B23 --> G2[Phase2门禁]
  G2 --> P3[Phase3_发2.2.0]
  P3 --> P4[Phase4_真实换版试点]
```

| 门禁 | 通过条件 |
|------|----------|
| Phase 1 | 本波次全部验收包通过 + **冻结 smoke 清单**隔离库 0 失败 |
| Phase 2 | S 专项（无 GET 写 / 无 token 拒绝）+ D-4 四分支 + G-3 非授权写 `approved/effective` 返回 blocked + 冻结 smoke 0 失败 |
| Phase 3 | 五处版本同步 + 台账对账 + 流程图 v0.2 + **经授权**重建现用镜像 |
| Phase 4 | 5 个低阻断批次人审闭环；不倒沙箱数据进现用库 |

**Smoke 口径修正（锁定）：** 台账历史写「75」；现行 [`jewelry-qms/tests/`](jewelry-qms/tests/) 有 **94** 个 `*smoke*.php`。Phase 0 冻结「基线清单文件」；门禁一律「冻结清单全通过」，禁止再写死 75。

---

## Phase 0 · 开工准备（本会话先行）

**产出目录：** `.team/交接箱/2026-07-20-三波次实施计划/`

- [ ] **0.1 补记变更台账断档** → [`docs/变更台账.md`](docs/变更台账.md)  
  至少补：G4-R11 系列（B1/B2/B3/C/E，证据见 [`.team/交接箱/2026-07-16-G4-R11试运行放行硬伤治理/`](.team/交接箱/2026-07-16-G4-R11试运行放行硬伤治理/)）、8013 演练栈相关修复。只增不改。
- [ ] **0.2 开 feature 分支 + worktree + 测试栈**  
  建议：`feature/wave1-defect-governance`；worktree 独立 compose（勿碰 8010）。基线哈希快照：8010 转储只读对照。
- [ ] **0.3 冻结测试库基线转储** + **冻结 smoke 清单**（文件名写入交接箱，如 `smoke-baseline-list-v0.1.txt`）
- [ ] **0.4 起草批次任务卡模板**（固定五段，本会话自用）：问题 / 改法 / 验收 / smoke / 禁区  
  禁区默认：不改 8010 现用库、不删批量文件、不发 2.2.0、不改模板业务正文内容、SIMULATED 不计完成。
- [ ] **0.5 更新** [`.team/当前战役.md`](.team/当前战役.md) 指挥入口指向本计划

---

## Phase 1 · 缺陷治理（本会话在 worktree 改代码 + 写 smoke + 自验归档）

### 批次 1-1：R-1 + R-2

| ID | 问题 | 锚点 | 验收要点 |
|----|------|------|----------|
| R-1 | 序号污染 UUID；`qms_next_number()` 年份重复 | [`jewelry-qms/app/common.php`](jewelry-qms/app/common.php) L42–57（`preg_match('/(\d+)$/')` 吞年份）；调用点 Capa/AuditFinding/Nonconformity/Complaint/ManagementReview/WorkflowService；redirect/外键回指排查 | 新建后跳转用 UUID；编号形如 `CP2026001` 不叠年；专项 smoke |
| R-2 | 五模块创建→编辑字段幂等 | 范本：[`TrainingPlan`](jewelry-qms/app/controller/TrainingPlan.php)→[`CrudBase`](jewelry-qms/app/controller/CrudBase.php)；对齐 audit_plan / capa / nonconformity / complaint / competency | 创建成功且编辑不丢必填、不把状态打回 draft；以表结构 NOT NULL 为准 |

- [ ] 写 `批次1-1-任务卡-v0.1.md` → 本会话实现 + 专项 smoke → 验收证据归档交接箱 → 用户门禁点头后进 1-2

### 批次 1-2（依赖 1-1）：R-3 机制 + R-4 + R-6

| ID | 范围 | 锚点 |
|----|------|------|
| R-3 | 空字段拒存 / 子表展示 / 打印键注册（**不含**模板内容重做） | 记录模板 Controller/Model + 打印键注册表 |
| R-4 | `FieldAuditService` 扩不符合/投诉/记录填报；修设备假留痕 | [`FieldAuditService.php`](jewelry-qms/app/service/FieldAuditService.php) |
| R-6 | 优先管评校准合格率：代码查英文 vs 数据存「合格」 | 管理评审统计查询路径 |

### 批次 1-3（可与 1-2 部分并行）：R-5 + A 组 15 项

- R-5：员工编号/邮箱唯一性、日期先后、校准缺机构不可「合格」、条款号存在性等（见缺陷清单规律5）
- A 组：P0 表 15 条；**A-3 依赖 R-3**，**A-4 依赖 R-2**——任务卡必须写清依赖，禁止提前合入门禁

### 批次 1-4：M 组 + E 组

- M：38 份文件元数据批量补录、重复模板治理、策划中心批量操作、无权限按钮不渲染（源：不好用清单 07-16 未修项）
- E：8013 缺口（含 `sim-` 前缀自动化等）——仅测试栈，不写现用库

### Phase 1 门禁

- [ ] 全部批次验收证据入交接箱
- [ ] `docker compose exec app php tests/<新增>_smoke.php` 0 失败
- [ ] 冻结清单全量隔离库 0 失败
- [ ] 8010 转储哈希与 Phase 0 基线一致

---

## Phase 2 · 签批闸门（S 为 D 硬前置）

### 批次 2-1：S 组安全六件套（D 硬前置）

锚点：[`jewelry-qms/route/app.php`](jewelry-qms/route/app.php)

已确认 GET 写语义路由（须改 POST+token 或等价防护）：
- `capa/advance`、`audit_plan/approve`、`audit_finding/createCapa`、`nonconformity/createCapa`、`complaint/createCapa`
- `management_review/complete`、`review_action/createCapa`、`training/complete`、`training_plan/approve|complete`
- `notification/read|markAllRead`、`supplier/qualified`
- 另：CRUD 循环 `Route::rule` 含 GET 的 add/edit/delete 全排查

六件套：
1. S-1 GET 批准/写改 POST+CSRF token；路由扫描脚本确认全站无 GET 写；无 token 被拒
2. S-2 AuditLog 全覆盖关键写路径
3. S-3 强制改密 + 限流
4. S-4 视图转义
5. S-5 RBAC 白名单（复用 [`RbacService`](jewelry-qms/app/service/RbacService.php)）
6. S-6 Cookie 属性（HttpOnly/Secure/SameSite）

### 批次 2-2（依赖 2-1 + R-5）：DocuSeal 自托管

- [`jewelry-qms/compose.yaml`](jewelry-qms/compose.yaml)：新增 docuseal 服务、独立卷、仅内网
- [`jewelry-qms/composer.json`](jewelry-qms/composer.json)：SDK 纳入
- 账号：QMS `users` 为身份事实源；QM/TM 邮箱真实唯一（依赖 R-5）；控制台管理账号独立强口令

### 批次 2-3（依赖 2-2）：签批闸门链路

- 新建 `DocuSealService`：建签
- Webhook：验签 / 防重放 / 哈希比对
- 已签件落 [`qms_document_assets`](jewelry-qms/database/jewelry_qms.sql)（Model: `QmsDocumentAsset`）
- 驳回 3 轮防振荡
- AI 硬阻断（非授权路径写 `approved`/`effective` → blocked）——可复用/扩展 [`ApprovalService`](jewelry-qms/app/service/ApprovalService.php)

### Phase 2 门禁专项

- [ ] S-1 路由扫描：无 GET 写；无 token 批准被拒
- [ ] D-4 smoke：错误签名 / 过期时间戳 / 篡改哈希 / 重放 → 拒绝或幂等
- [ ] G-3 smoke：非授权写 approved/effective → blocked
- [ ] 冻结 smoke 全通过；8010 哈希不变

---

## Phase 3 · 发布 2.2.0（本会话主导，须用户授权镜像重建）

当前版本锚点均为 **2.1.0**：[`CHANGELOG.md`](CHANGELOG.md)、[`docs/VERSIONING.md`](docs/VERSIONING.md)、[`jewelry-qms/config/qms.php`](jewelry-qms/config/qms.php)、交付说明/流程图（第五处以 VERSIONING 清单为准）。

- [ ] 五处版本同步缺一不可 → 2.2.0
- [ ] 台账对账：波次 1+2 条目与 CHANGELOG `[2.2.0]` 对齐
- [ ] 流程图升 v0.2（含签批闸门）
- [ ] **经用户明确授权**后重建现用镜像（含遗留 Poppler 改动，台账 2026-07-11 已记「镜像待授权」）
- [ ] 更新当前战役：2.2.0 已发

---

## Phase 4 · 真实换版试点（人审主导）

- [ ] 从治理关闭最小试点包抽 **5 个低阻断批次**起步
- [ ] 预览层审阅 → DocuSeal 签批 → 文控真实系统受控修订
- [ ] **不倒沙箱数据**进现用库
- [ ] 逐批消化：67 项人审 pending + 392 条治理阻断（源：第五版候选治理包闸门）
- [ ] 模板内容重做随人审批次消化（P 组），不回溯改波次 1 机制门禁定义

---

## 协作与禁区（全程）

| 角色 | 职责 |
|------|------|
| 本会话（Cursor） | Phase 0 全套；Phase 1–2 **代码 + smoke + 任务卡 + 自验证据**；台账/CHANGELOG/战役；Phase 3 发版编排；Phase 4 文档与闸门支持 |
| 用户 | 每批次/波次门禁验收；Phase 3 镜像重建授权；Phase 4 人审决策 |
| Codex | **不派活**（本战役改口后闲置，除非用户另行指定） |

**硬禁区：** 未授权不写 8010；不 force 发版；SIMULATED 不计完成；波次 1 不重做模板正文内容。

**执行节奏：** 批准计划后先跑 Phase 0；再按批次 1-1 → … 顺序实现，每批结束停一次等人审门禁，不连跳波次门禁。

---

## 自检（对照用户规格）

| 规格项 | 计划落点 |
|--------|----------|
| 全三波次一份计划 / 门禁制 | 本文 Phase 0–4 |
| R→A/M/E→S+D→P | Phase 1 批次序 + Phase 2 + 4 |
| 2.2.0 在波次 1+2 后 | Phase 3 |
| DocuSeal 复用 QMS 账号 | 批次 2-2 |
| 模板内容不进波次 1 | R-3 机制 only + Phase 4 |
| 每批 smoke + 冻结全量 | 各批次 + Phase 门禁 |
| S-1 / D-4 / G-3 / 8010 哈希 | Phase 2 门禁 + Phase 0/1 对照 |
