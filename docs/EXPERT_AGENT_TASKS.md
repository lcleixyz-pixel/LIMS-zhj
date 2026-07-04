# 专家智能体任务单（Codex 评审/执行版）

> 配套 [EXPERT_AGENT_ROADMAP.md](EXPERT_AGENT_ROADMAP.md)。本文档为计划，每张任务卡 = 一个独立可评审、可回滚的工作单元（代码类任务 = 一个 PR）。
> 执行者：Codex / Claude Code 等编码智能体；评审者：人工（luo）。

## 使用约定

1. 任务按依赖顺序执行，卡内「出界」为禁止事项，防止范围蔓延；
2. 代码任务遵循仓库既有规范：migration 幂等（`information_schema` 判存在）、附件统一 `file_uploads`、UUID 主键、审批走 `approvals`；
3. 所有 AI 生成内容遵守铁律：**只出草稿，人审确认；引用必须带条款号；无知识卡片支持的引用标注「待核」**；
4. 验收标准全部为可客观核查项，评审时逐条打勾。

## 现状核查结论（任务单编制依据，已核实）

| 项目 | 状态 | 影响 |
|------|------|------|
| `AiChat/AiChatService/CopilotReadService/PageContextBuilder/DeepSeekService` | 已有代码骨架 | Phase 3a 起点是「对照 v3 规格核验补全」而非从零开发 |
| `ComplianceCheckService` | 已存在 | T2.5 缺口扫描在其上扩展 |
| `qms_clauses` / `qms_clause_texts` | 表已建，**SQL 无种子数据** | T2.1 条款库导入是 Phase 2 的第一前置 |
| `qms_structured_documents` / `qms_document_blocks` / `DocumentParserService` | 已有（AI 文档助理：Word→结构化入库） | T2.2 文件生成复用此管线反向 |
| `record_form_templates.field_schema`（text JSON）+ draft/published/obsolete 状态机 | 现成 | T2.3 表格生成产出物即 draft 状态模板 |
| `qms:seed-current-files`（`CurrentFilesSeed` 命令）+ `CurrentFilesSeedService` + `QmsDocumentStructureService` | 已有现用文件→结构化链路 | T0.4 **禁止另起炉灶**，复用/扩展此链路，knowledge/ 仅为 Git 导出层 |
| `CheckReminders` / `ComplianceAssess` / `RecordFormRebuildSchema` 等命令 | 代码已部分落地 | v2.2 路线图 todos 状态与代码不同步，各 Phase 开工前先做状态验收（见 ROADMAP「实现状态校准」） |
| 语料原料 | 程序文件 2018+2022 两套（2022 目录 42 个文件，其中编号文件 38 份，**最终数量以 T0.4 脚本枚举清单为准**）、记录表格 2017、质量手册第四版、作业指导书 | 需定基线：**默认以 2022 版程序文件 + 质量手册第四版为准**，2017/2018 仅作沿革参考 |
| 规范依据基线 | CNAS：`CNAS-CL01:2018` + `CNAS-CL01-G001` + `CNAS-CL01-A015:2018`（珠宝玉石、贵金属检测）；CMA：2023 版《检验检测机构资质认定评审准则》 | `RB/T 214-2017` 已于 2024-11-26 废止，仅作历史沿革参考，**不得作为差异映射主依据** |

---

## Phase 0：知识层与桌面技能（非系统代码，产出为 Markdown/脚本）

### T0.1 知识库目录规范与索引

- **依赖**：无。**输入**：ROADMAP 架构节。
- **步骤**：建 `knowledge/` 目录骨架（standards / internal / cases）；写 `knowledge/README.md` 定义卡片命名规则（如 `standards/17025/7.2.1.md`）、frontmatter 字段（clause、title、type、status、sources）、索引文件 `INDEX.md` 生成规则。
- **产出**：目录骨架 + README + 空索引。
- **验收**：
  - [x] 命名规则覆盖三类卡片且互不冲突；
  - [x] frontmatter 字段定义含条款号、shall/should 标记、来源标注「待核」机制；
  - [x] README 明确「不整篇收录标准原文，只收要点复述+解读」（版权约束）。
- **实施记录（2026-07-04）**：已建立 `knowledge/` 骨架、`knowledge/README.md`、`knowledge/INDEX.md` 和三类目录占位；未录入任何卡片内容。
- **出界**：不录入任何卡片内容。

### T0.2 ISO 17025 条款卡片库（4–8 章 + 附录 A/B）

- **依赖**：T0.1。**输入**：iso-17025-zh 技能的 `references/standard-structure.md`、标准文本（用户提供）。
- **步骤**：逐条款生成卡片：条款号、要点复述、shall/should 区分、所需证据（文件/记录/现场）、常见不符合项、关联条款。粒度到三级条款（如 7.2.1.1 归入 7.2.1 卡片内分节）。
- **产出**：约 60–80 张卡片 + 更新 INDEX。
- **验收**：
  - [ ] 4–8 章二级条款 100% 有卡片（对照 standard-structure.md 清单核对）；
  - [ ] 抽检 10 张：要点与标准原义无偏差、shall/should 标记正确（人工评审）；
  - [ ] 每张卡片「所需证据」非空且具体到记录类型。
- **出界**：不做 CNAS/CMA 差异（归 T0.3）。

### T0.3 CNAS / CMA 依据差异卡

- **依赖**：T0.2。
- **步骤**：按条款生成差异卡，依据基线：
  - CNAS：`CNAS-CL01:2018`（等同 17025）+ `CNAS-CL01-G001`（应用要求）+ `CNAS-CL01-A015:2018`（珠宝玉石、贵金属检测领域应用说明）；
  - CMA：2023 版《检验检测机构资质认定评审准则》（市监总局 2023 年第 21 号公告）为主依据，逐条映射到 17025；
  - `RB/T 214-2017` 已于 2024-11-26 废止：仅在卡片「沿革」字段记录旧条款对应关系，不作为映射主线；
  - 无差异条款在 17025 卡片标注「无附加要求」。
- **产出**：差异卡 + 17025 卡片的交叉引用回填。
- **验收**：
  - [ ] 2023 版评审准则全部条款有到 17025 的映射（或标注「CMA 独有」）；
  - [ ] A015 珠宝领域应用说明逐条单列，抽检 5 处与官方文本一致；
  - [ ] 全库检索无以 RB/T 214 为现行依据的表述（仅允许出现在「沿革」字段）。

### T0.4 内部文件结构化与 knowledge/ 导出（复用现有链路）

- **依赖**：T0.1。**输入**：`现用文件/程序文件/程序文件2022/`、`质量手册（第四版）.docx`、`作业指导书181201.docx`；现有 `qms:seed-current-files` 命令、`CurrentFilesSeedService`、`QmsDocumentStructureService`。
- **原则**：**优先复用/扩展现有「现用文件→结构化入库」链路，禁止另写平行的 docx 解析器**。`knowledge/internal/` 定位为结构化库（`qms_structured_documents`/`qms_document_blocks`）的 **Git 版知识资产导出层**，单向导出、可重新生成，避免系统库与 knowledge/ 双源分叉。
- **步骤**：
  1. 枚举脚本：扫描 2022 目录，产出编号文件清单（排除封面/目录/批准页/修改页），**此清单为后续所有任务的数量基准**（当前核对：目录 42 个文件、编号文件 38 份）；
  2. 评估现有链路对清单内文件的解析覆盖率，缺口处扩展 `QmsDocumentStructureService`（而非新写脚本）；
  3. 写导出命令：结构化库 → `knowledge/internal/procedures/`（一文件一卡）与 `knowledge/internal/manual/`（按章节），带 frontmatter；
  4. 生成转换报告（每份文件的段落数、表格数、疑似丢失项）。
- **产出**：枚举清单 + 链路扩展（如需）+ 导出命令 + 内部卡片 + 转换报告。
- **当前状态（2026-07-04）**：已落地首步枚举能力：`qms:seed-current-files --enumerate-procedures` 复用 `CurrentFilesSeedService` 解析 2022 程序文件，导出 `knowledge/internal/procedures/PROCEDURE_FILE_MANIFEST.md` 与 `.json`；当前脚本基线为目录 42 个文件、编号文件 38 份、排除封面/目录/批准页/修改页 4 份。已补 `qms:seed-current-files --export-knowledge-internal`，从 `qms_structured_documents`/`qms_document_blocks` 单向导出 `knowledge/internal/manual/`、`knowledge/internal/procedures/`、`INTERNAL_EXPORT_INDEX.md` 和 `CONVERSION_REPORT.*`；当前开发库导出质量手册 1 份、程序文件 37 份，`.doc` 程序文件已由同一解析器层的 UTF-16LE fallback 抽取，转换报告剩余 1 个待复核项：`XZTC/CX-05-02-2022` 为附件/表单边界项。后续仍需人工逐段抽检 5 份 docx/doc 原文并处理报告问题。
- **验收**：
  - [ ] 枚举清单内程序文件 100% 转换成功，导出命令幂等可重跑；编号附件/表单需显式列入报告边界项；
  - [ ] 抽检 5 份与 docx 原文逐段对照：标题层级正确、表格不丢行、无正文缺失；
  - [x] 转换报告列出需人工复核项（当前含 `05-02` 编号附件/表单边界项），且已标注；
  - [x] 修复只发生在结构化库或解析器层，`knowledge/internal/` 无手工直改（保证可重新导出）。
- **出界**：2017/2018 版不转换；不做条款映射（归 T0.5）。

### T0.5 内部文件 ↔ 17025 条款映射

- **依赖**：T0.2、T0.4。
- **步骤**：为每份程序文件卡片 frontmatter 回填对应条款号（如 08-文件控制程序 ↔ 8.3）；生成双向映射总表 `knowledge/internal/CLAUSE_MAP.md`（条款→文件、文件→条款）；识别无文件覆盖的条款列入「疑似缺口」清单。
- **验收**：
  - [ ] T0.4 枚举清单内程序文件映射 100% 完成，人工复核签字；
  - [ ] 双向映射表可从两个方向查询；
  - [ ] 疑似缺口清单产出（此即首次粗粒度差距分析副产品）。

### T0.6 案例库模板与首批卡片

- **依赖**：T0.1。
- **步骤**：定案例卡片模板（场景/涉及条款/不符合描述/原因/整改/教训/来源）；访谈式整理本所现有零散经验录入首批。
- **验收**：[ ] 模板定稿；[ ] 首批 ≥5 张，每张至少关联 1 个条款号。

### T0.7 桌面技能集拆扩（7 个 SKILL.md）

- **依赖**：T0.2–T0.6。**输入**：现有 `~/.agents/skills/iso-17025-zh/`。
- **步骤**：按 ROADMAP 技能表编写 7 个 SKILL.md（qms-dev-advisor / qms-gap-analysis / qms-doc-drafting / qms-record-schema / qms-audit-prep / qms-capa / knowledge-accumulation）。每个技能：触发条件、工作流步骤、强制「先查 knowledge/ 再作答」、输出格式模板、条款引用规则。iso-17025-zh 改为总入口（路由到子技能）。
- **验收**：
  - [ ] 7 个 SKILL.md 各自描述完整（触发/流程/输出/引用规则四要素齐全）;
  - [ ] qms-record-schema 的输出格式与 `record_form_templates.field_schema` 的 JSON 结构对齐（从 SQL/代码中提取实际 schema 规范写入技能）；
  - [ ] qms-doc-drafting 输出结构 = 目的范围→引用条款→职责→程序→记录→相关文件；
  - [ ] 依据基线同步：7 个技能及 iso-17025-zh 总入口中，CMA 依据统一为 2023 版评审准则，RB/T 214 仅作沿革表述。

### T0.8 技能验证运行（Phase 0 总验收）

- **依赖**：T0.7。
- **步骤**：每个技能用真实场景各跑 1 次：如 gap-analysis 跑 6.4 设备条款、doc-drafting 起草一份缺失程序、record-schema 从 04-期间核查程序推导表格、dev-advisor 为 v2.2 Phase 1B 出需求说明。
- **验收**：
  - [ ] 7 次运行留档（输入+输出存 `knowledge/cases/validation/`）；
  - [ ] 条款引用人工核对：错误引用率 = 0（错一处即整改技能提示词后重跑）；
  - [ ] doc-drafting 草稿经人评「修改后可用」及以上。

---

## Phase 1：专家指导 v2.2（流程型任务，随 v2.2 各 Phase 循环）

### T1.1 条款对标需求说明（每 v2.2 Phase 开工前）

- **步骤**：qms-dev-advisor 产出《XX 功能条款对标说明》：满足哪些条款、评审员查什么证据、对 v2.2 验收标准的补充项。
- **验收**：[ ] v2.2 每个启动的 Phase 有对应说明存 `docs/clause-specs/`；[ ] 说明中每条要求带条款号。

### T1.2 合规审查（每 v2.2 Phase 完工后）

- **步骤**：对照 T1.1 说明逐项核查实现，出审查记录；未满足项转 issue。
- **验收**：[ ] 审查记录与需求说明逐条对应；[ ] 结论三值：满足/部分满足（附 issue）/不适用（附理由）。

---

## Phase 2：半自动生成能力进系统（jewelry-qms 代码任务）

### T2.1 条款库种子数据导入（Phase 2 第一前置）

- **依赖**：T0.2、T0.3。**输入**：`knowledge/standards/` 卡片。
- **步骤**：写导入命令（`think` command）：解析卡片 frontmatter+正文 → 写入 `qms_sources` / `qms_clauses` / `qms_clause_texts`；支持重跑（按条款号 upsert）。
- **验收**：
  - [ ] 导入后条款数与卡片数一致，抽查 5 条内容无截断乱码；
  - [ ] 命令幂等：连跑两次数据不重复；
  - [ ] 体系策划中心页面可正常浏览条款（现有 PlanningClause 控制器读到数据）。
- **出界**：不改动 Planning 系列控制器逻辑。

### T2.2 体系文件生成（AI 起草→人审→受控入库）

- **依赖**：T2.1；v2.2 Phase 2.1 文件控制增强建议先行。**输入**：`AiAssistantService`/`DocumentParserService` 现有管线、qms-doc-drafting 技能提示词。
- **步骤**：在 AI 文档助理内新增「起草模式」：用户选条款/要素 → 后端组装上下文（条款文本 + 同类程序文件结构）→ DeepSeek 生成草稿 → 前端可编辑预览 → 确认后走现有结构化入库 + `approvals` 审批流，初始状态 draft。
- **验收**：
  - [ ] 生成的文件不经审批流不会出现在受控文件清单（8.3 合规）；
  - [ ] 草稿含条款引用节，引用的条款在 `qms_clauses` 中均存在（后端校验）；
  - [ ] 端到端演示：起草一份程序文件 → 人审修改 → 审批 → 发布为受控文件；
  - [ ] AI 调用与入库操作写入 `histories`/`field_change_logs` 审计。
- **出界**：不做批量生成；不改审批流本身。
- **增补（变更流水线 L4）**：起草模式需支持「redline 修订」子模式——输入现有受控文件 + 变更评估报告（T2.8 产出），输出与原文逐段对照的修订建议稿，走同一人审+审批链路。

### T2.3 记录表格 schema 生成

- **依赖**：T2.1、T2.2 的 AI 调用基建。
- **步骤**：新增入口：选择程序文件（`documents` 或 `qms_structured_documents`）→ AI 依据「应保存的记录」推导 `field_schema` JSON → 创建 `record_form_templates` 记录（status=draft，回填 `procedure_doc_id`/`element_id`）→ 人在现有模板编辑界面调整后发布。
- **验收**：
  - [ ] 生成的 field_schema 通过现有模板渲染器渲染无报错；
  - [ ] 字段与程序文件记录要求对应（人工评审：从 04-期间核查程序生成的表格覆盖核查项目/依据/结果/结论/核查人等要件）；
  - [ ] draft 状态不可创建实例，发布后可（沿用现有状态机）。

### T2.4 运行记录预填（草稿不落库）

- **依赖**：T2.1；沿用 v3「只填 DOM 草稿」原则。
- **步骤**:在内审 checklist（`audit_checklists`）、期间核查计划、管评输入（`management_reviews`）三处表单接入「AI 预填」按钮：后端组装上下文（条款+相关记录摘要）→ 返回 JSON → 前端填充表单，不自动保存。
- **验收**（口径：**不写业务记录、不发布、不自动创建任务；允许写会话/审计/快照类日志表**，如 `ai_chat_sessions`/`ai_chat_messages`、`histories`）：
  - [ ] 预填仅改 DOM；确认保存前，`audit_checklists`/`management_reviews` 等业务表无新增或变更行（数据库前后对比）；
  - [ ] 用户清空/修改后保存的是用户版本；
  - [ ] 三个场景各演示一次，预填内容条款引用正确。

### T2.5 合规缺口扫描 → 整改任务（变更流水线 L3 的特例：缺口 = 从未满足）

- **依赖**：T2.1、T0.5 映射表导入。**输入**：`ComplianceCheckService` 现有实现。
- **步骤**：扩展扫描：遍历条款 → 检查是否有映射文件/记录模板/近期记录 → 生成差距清单（条款、缺口类型、证据现状、建议）→ 支持一键转 `capas`（来源标记 ai_scan）或 `review_actions`，任务回链条款号。
- **验收**：
  - [ ] 扫描结果与 T0.5 人工缺口清单对比：召回率 ≥ 80%（人工清单为基准）；
  - [ ] 转任务后 CAPA 详情页可见来源条款链接；
  - [ ] 扫描口径：**不写业务记录、不自动转任务**；允许写 `compliance_checks`/`compliance_check_results`/`compliance_snapshots` 快照表及审计日志（现有 `ComplianceCheckService` 行为），除显式转任务外 `capas`/`review_actions` 等业务表无变更。

### T2.6 块级追溯建链（变更流水线 L1，最大单项投入）

- **依赖**：T2.1、T0.4、T0.5。**输入**：`qms_document_blocks`、`qms_document_block_links`、`qms_reference_procedure_matches` 现有表结构；新增专用候选表 `qms_block_link_suggestions` 承载 AI 预匹配结果；详见 [CHANGE_PIPELINE_FEASIBILITY.md](CHANGE_PIPELINE_FEASIBILITY.md)。
- **步骤**：
  1. 核查现有块拆分粒度是否支持「条款 ↔ 文件块」链接（不够则先扩展结构化拆分）；
  2. AI 预匹配：遍历文件块生成候选条款链接，写入 `qms_block_link_suggestions`（建议字段：`block_id`、`clause_id`、`relation_type`、`confidence`、`evidence_json`、`status=open/accepted/rejected`），不直接生效；
  3. 人工批量确认界面/流程：采纳、修改、拒绝三态，确认后落 `qms_document_block_links`；
  4. 记录表格字段层：正式块级关系落 `qms_document_block_links.record_form_template_id` + `relation_type=requires_record`；`record_form_templates.procedure_doc_id` 只作为文件级归属和兜底查询，不等同于块级挂接。
- **验收**：
  - [ ] T0.4 枚举清单内程序文件块级建链 100%（无链块需显式标注「无条款对应」）；
  - [ ] AI 预匹配采纳率有统计报表（用于评估建议质量）；
  - [ ] 任一条款可反查全部关联块，任一块可正查条款（双向）；
  - [ ] 候选链接全量可追踪：每条候选最终为 accepted/rejected，采纳后能追到正式 `qms_document_block_links`；
  - [ ] 人工抽检 5 份文件的链接准确性通过。
- **出界**：不做变更检测（归 T2.8）。

### T2.7 变更事件登记与公告监测（变更流水线 L2）

- **依赖**：无强依赖，可与 T2.6 并行。
- **当前实现记录（2026-07-04）**：已新增 `qms_external_change_events` 表、`PlanningChangeEvent` 规划中心入口、`ExternalChangeEventService`、`QmsExternalChangeEvent` 模型、附件复用与状态审计接入；已补 `tests/qms_external_change_event_smoke.php`。Docker Desktop 已启动，`docker compose run --rm app ...` 下 PHP lint、T2.7 smoke、规划中心 UI smoke、字段审计 smoke 均已通过。
- **步骤**：
  1. 新建 `qms_external_change_events`：登记来源（CNAS/SAMR/标准平台）、文号、来源 URL、旧依据 ID、新依据 ID、旧版本号、新版本号、发布日期、生效日期、事件摘要、`graph_snapshot_hash`、状态机（registered→assessing→revising→closed/exempted）、关闭/豁免理由；
  2. 新旧文本或公告附件统一走 `file_uploads`，约定 `model_name='QmsExternalChangeEvent'`、`record=event_id`；
  3. 状态流转写入 `histories`/`field_change_logs`；
  4. 可选脚本月度抓取公告清单生成待登记提醒（只提醒不自动登记）。
- **验收**：
  - [ ] 事件登记入口唯一，字段完整；
  - [ ] 每个事件能定位新旧依据和图快照 hash，作为 T2.8 可复算输入；
  - [ ] 附件复用 `file_uploads`，不新增平行附件字段；
  - [ ] 状态机流转有审计记录；
  - [ ] 公告监测漏报由月度人工核查兜底（写入操作规程，不承诺自动全覆盖）。
- **出界**：不自动抓取/解析标准原文全文。

### T2.8 变更影响分析与闭环（变更流水线 L3 + L5）

- **依赖**：T2.6、T2.7；从 T2.5/T2.8 抽出共用 `TraceGraphService`，避免把影响分析硬塞进 `ComplianceCheckService`。
- **步骤**：
  1. L3：对变更事件，AI 生成新旧条款 diff 摘要（人确认）→ `TraceGraphService` 按事件的 `graph_snapshot_hash` 遍历输出受影响块清单 → 生成《变更评估报告》（diff 摘要 + 影响清单 + 建议动作）；
  2. 影响项可一键转 CAPA/整改任务或发起 T2.2 redline 修订；
  3. L5：事件关闭前重跑失配检测，关闭条件 = 失配数归零或余项显式豁免（附理由）。
- **验收**：
  - [ ] 影响清单可复算：同一事件同一 `graph_snapshot_hash`，两次运行结果一致；
  - [ ] 报告三要素齐全（diff 摘要/影响清单/建议动作），影响项均带条款号与块引用；
  - [ ] 端到端演练一次（可用历史事件模拟，如某方法标准换版）：登记→评估→redline→审批→闭环全链路走通；
  - [ ] 未闭环事件在看板可见，豁免项留痕。

---

## Phase 3：内嵌智能体分级上线

### T3.1 Copilot v3 规格符合性核验（起点不是从零开发）

- **依赖**：无（可与 Phase 2 并行）。**输入**：`AI_CHAT_ASSISTANT_SPEC.md` v3、现有 AiChat 全家桶代码。
- **步骤**：逐条核对 v3 规格 vs 现实现（Key 加密存储、CSRF、会话隔离、draft 白名单、页面上下文服务端重建、90 天清理…），产出符合性矩阵 + 缺项补全 PR 清单；随后按清单补全。
- **验收**：[ ] 矩阵覆盖 v3 全部「已确认决策」；[ ] 缺项全部补全或降级说明；[ ] 规格中安全项（Key 不出前端、密文格式）实测验证。

### T3.2 知识层 RAG 打包与接入

- **依赖**：T0.x 完成、T3.1。
- **步骤**：写打包脚本：`knowledge/` → 检索语料（入库或索引文件）；`CopilotReadService`/`AiContextToolService` 接入：回答条款类问题前先检索卡片注入上下文；建立 knowledge/ 更新 → 重新打包的同步机制（手动命令即可）。
- **验收**：
  - [ ] 问「7.2.1 要求是什么」时上下文含对应卡片（日志可查）；
  - [ ] 无卡片支持时回答标注「待核」；
  - [ ] 同步命令幂等。

### T3.3 主动审查（定时合规报告）

- **依赖**：T2.5、T3.2。
- **步骤**：`think` 定时命令：周期运行缺口扫描 + 记录时效检查（校准到期、内审逾期等，复用提醒基建）→ 生成合规审查报告（存档 + 通知管理者）。
- **验收**：[ ] 报告含条款号、证据现状、建议三要素；[ ] 只读，不自动创建任务；[ ] 与 v2.2 Phase 1B 提醒机制不重复告警。

### T3.4 生成→审批→发布闭环（最后上线）

- **依赖**：T2.2、T2.3 稳定运行 + v2.2 Phase 1E 字段审计就绪。
- **步骤**：将 T2.2/T2.3 的「人工发起生成」升级为「审查报告缺口项 → 一键发起生成 → 审批 → 发布」全链路；每环节留审计。
- **验收**：[ ] 全链路演示通过；[ ] 任一环节可人工终止；[ ] 审计链完整（谁发起/谁审/何时发布）。

---

## 可行性评估（能否实现开发目标）

**总体判断：目标可实现，但各能力可达的「自动化程度」不同，按下表控制预期。**

| 目标 | 可行性 | 依据与瓶颈 |
|------|--------|-----------|
| 桌面专家技能集（Phase 0） | **高** | 纯提示词+语料工程，iso-17025-zh 已验证单技能可行；瓶颈仅在语料整理工时和条款卡片准确性人审 |
| 体系文件生成（T2.2） | **高** | DocumentParserService/结构化文档表现成，反向起草是同管线的自然延伸；「可用草稿」可达，「免审终稿」不可达也不应达（8.3 要求人审） |
| 记录表格生成（T2.3） | **中高** | field_schema 机制现成是最大利好；瓶颈是从 docx 程序文件推导字段的准确率，验收定为「渲染无错+人审通过」而非全自动 |
| 记录预填（T2.4） | **高** | v3 规格已设计此模式，纯上下文组装+JSON 返回 |
| 缺口扫描→任务（T2.5） | **中** | ComplianceCheckService 有雏形，但扫描质量取决于条款库（T2.1）与映射（T0.5）完备度——这是典型「语料决定上限」；召回率 80% 是现实目标，100% 不承诺 |
| 变更管理流水线（T2.6–T2.8） | **中高** | 数据模型已为 L1 预留，候选链接需补专表 `qms_block_link_suggestions`；L3 通过共用 `TraceGraphService` 做确定性图遍历；风险集中在 L1 建链质量（漏链=漏报）与建链工时，论证见 [CHANGE_PIPELINE_FEASIBILITY.md](CHANGE_PIPELINE_FEASIBILITY.md)；「自动感知+自动修订」已明确排除出范围 |
| Copilot 问答（3a） | **高** | 代码骨架已在，剩余是规格符合性收尾 |
| 主动审查（3b） | **中高** | 是 T2.5+提醒基建的组合，无新技术风险 |
| 生成入库闭环（3c） | **中（最高风险项）** | 技术上无障碍，风险在治理：AI 草稿质量波动 + 审批人可能形式化放行。缓解：最后上线、审批人培训、抽检制度 |

**关键风险与缓解：**

1. **语料质量是全局上限**——T0.4 转换和 T0.2 卡片的抽检不能省；建议 Phase 0 投入占比 ≥40%。
2. **DeepSeek 幻觉引用条款**——三重防线：技能强制先查卡片、T2.2 后端校验条款号存在性、无卡片标「待核」。
3. **保密**——体系文件全文喂外部 API 的边界需在 T2.2 前定：默认仅传条款卡片+文件结构摘要，不传含客户信息的记录数据。
4. **版本基线**——语料以 2022 程序文件+第四版手册为准；2017 记录表格与现行程序的不一致本身就是 T2.5 应暴露的缺口，不在语料阶段修复。
5. **精力分配**——专家线（Phase 0）不写系统代码，可与 v2.2 的 1B/1A 并行由不同会话推进，互不阻塞。

**结论**：Phase 0–2 的开发目标在现有代码基础上完全可达；Phase 3a/3b 可达；3c 建议保留为「验证成熟后的最终形态」，不设硬性时限。
