# LIMS 预导入命令使用说明与阻断项

文件状态：实施准备说明
适用阶段：修订草案和治理准备
边界：本说明用于指导后续在 `jewelry-qms` 中执行候选文件和记录模板导入；不是写库批准。

## 已新增命令

命令：`php think qms:preimport-package`

用途：

- 校验 `lims_preimport_package/` 是否可被 LIMS 命令层识别。
- 对照当前数据库，检查现行 2022 程序编号是否能匹配。
- 评估候选文件、候选记录模板和外来依据的写入准备度。
- 使用 `--stage2-check` 时，额外预检结构化文件、手册块和块级追溯关系的第二阶段导入准备度。
- 使用 `--field-catalog-dir` 时，额外校验 `record_template_field_catalog/` 是否与候选记录模板 schema 一致。
- 使用 `--release-plan-dir` 时，额外校验 `controlled_release_rehearsal/` 中受控发布、审批签核、培训宣贯、旧版处置和实施有效性检查准备是否保持不写库、非受控发布边界。
- 使用 `--release-execution-dir` 时，额外校验 `release_execution_template_pack/` 中发布执行记录候选模板、字段明细和模拟试填是否保持不写库、非真实记录边界。
- 使用 `--manual-revision-dir` 时，额外校验 `manual_revision_path_pack/` 中 `XZTC/SC` 第五版候选手册是否走既有 published 受控文件的修订/换版路径，而不是同编号新增草稿。
- 使用 `--stage2-review-dir` 时，额外校验 `stage2_structured_review_workbench/` 中第二阶段结构化导入人工复核决策是否已全部通过。
- 使用 `--stage2-review-preview-dir` 时，额外校验 `stage2_structured_review_decision_preview/` 中第二阶段复核意见回填预览是否仍有阻断项。
- 使用 `--governance-readiness-dir` 时，额外校验 `governance_readiness_dashboard/` 中全量治理闸门、人工处理任务和 `ready_for_lims_apply` 状态。
- 使用 `--governance-readiness-refresh-dir` 时，额外校验 `governance_readiness_refresh_preview/` 中治理关闭意见刷新后的总闸门预览、阻断任务和 `ready_for_lims_apply` 状态。
- 使用 `--governance-closure-execution-dir` 时，额外校验 `governance_closure_execution_pack/` 中治理关闭执行批次、岗位签核、交接复核和回填路径是否仍 pending。
- 使用 `--governance-closure-pilot-dir` 时，额外校验 `governance_closure_pilot_pack/` 中最小试点批次、证据填写页和签核交接页是否仍 pending。
- 使用 `--governance-closure-pilot-return-dir` 时，额外校验 `governance_closure_pilot_return_preview/` 中试点结果回填到治理关闭工作台前的缺字段、阻断项和 `ready_for_governance_closure_preview` 状态。
- 使用 `--governance-closure-pilot-source-update-dir` 时，额外校验 `governance_closure_pilot_source_update_rehearsal/` 中源工作台逐字段补丁、阻断补丁和 `ready_for_source_workbench_update` 状态。
- 使用 `--governance-closure-pilot-operator-workbook-dir` 时，额外校验 `governance_closure_pilot_operator_workbook/` 中试点主任务、逐字段填写项、签核交接项和 `ready_for_pilot_return_preview` 状态。
- 使用 `--governance-closure-pilot-operator-handback-dir` 时，额外校验 `governance_closure_pilot_operator_handback/` 中真实执行交回结果、真实值、执行人、复核人、日期和交接状态。
- 使用 `--apply-rehearsal` 时，模拟真实 apply 前置闸门但不写数据库；该模式可使用 `human_review_simulation_pack/`，用于验证“人审通过后命令链路是否可走通”。
- 使用 `--write-preview-dir` 时，输出 LIMS 第一阶段写库行级预览包；只允许 dry-run 或 `--apply-rehearsal`，禁止与正式 `--apply` 同时使用。
- 默认 dry-run，不写数据库。
- apply 必须同时提供 `--apply`、`--ack-human-reviewed` 和 `--review-dir`。
- `--review-dir` 指向 `human_review_pack/`；只有人工评审包中全部 `human_decision` 为通过/批准类结果时，才允许进入写库逻辑。
- `human_review_simulation_pack/` 只能用于 `--apply-rehearsal`，不得作为正式 `--apply` 的 `--review-dir`。

## dry-run 命令

在当前 Docker 开发环境中，因 `.team/` 位于 `jewelry-qms` 上级目录，需临时挂载项目根：

```bash
docker compose run --rm \
  -v '/Users/lc.leixyz/Documents/AI工作台/01-项目代码/LIMS-zhj:/workspace' \
  app php think qms:preimport-package \
  --package-dir '/workspace/.team/交接箱/2026-07-07-第五版候选修订准备/lims_preimport_package' \
  --json-out '/workspace/.team/交接箱/2026-07-07-第五版候选修订准备/21-LIMS预导入命令dry-run报告.json' \
  --md-out '/workspace/.team/交接箱/2026-07-07-第五版候选修订准备/21-LIMS预导入命令dry-run报告.md'
```

当前 dry-run 结论：

- 命令层可读取预导入包。
- 文件候选 65 条、记录模板候选 26 条、追溯矩阵 29 条均能被命令识别。
- 当前数据库已匹配 37 个现行 2022 程序文件，`missing_reference_current_documents=0`。
- `XZTC/CX-05-02-2022` 已按编号附件/表单分流，不作为现行程序文件自动匹配。
- 候选记录模板当前库匹配数为 0，说明 apply 闸门验证没有写入记录模板。

## 带字段字典的 dry-run 命令

```bash
docker compose run --rm \
  -v '/Users/lc.leixyz/Documents/AI工作台/01-项目代码/LIMS-zhj:/workspace' \
  app php think qms:preimport-package \
  --package-dir '/workspace/.team/交接箱/2026-07-07-第五版候选修订准备/lims_preimport_package' \
  --review-dir '/workspace/.team/交接箱/2026-07-07-第五版候选修订准备/human_review_pack' \
  --field-catalog-dir '/workspace/.team/交接箱/2026-07-07-第五版候选修订准备/record_template_field_catalog' \
  --json-out '/workspace/.team/交接箱/2026-07-07-第五版候选修订准备/32-LIMS预导入命令field-catalog-dry-run报告.json' \
  --md-out '/workspace/.team/交接箱/2026-07-07-第五版候选修订准备/32-LIMS预导入命令field-catalog-dry-run报告.md'
```

当前结论：

- 命令返回 `passed`，`field_catalog_status=passed`。
- 字段字典覆盖 26 个候选记录模板、437 条字段明细、26 份逐模板字段字典。
- 字段字典与 `record_form_templates_preimport.csv` 的候选 schema 一致，发现项 0。
- 该校验只证明字段字典可被 LIMS 命令层识别并与候选 schema 对齐；不代表记录模板已经人工批准、受控发布或写库。
- 该参数可与 `--stage2-check` 组合运行；当前组合 dry-run 仍显示第二阶段状态为 `blocked_by_human_review`，不是字段字典结构阻断。

## 带受控发布演练包的 dry-run 命令

```bash
docker compose run --rm \
  -v '/Users/lc.leixyz/Documents/AI工作台/01-项目代码/LIMS-zhj:/workspace' \
  app php think qms:preimport-package \
  --package-dir '/workspace/.team/交接箱/2026-07-07-第五版候选修订准备/lims_preimport_package' \
  --review-dir '/workspace/.team/交接箱/2026-07-07-第五版候选修订准备/human_review_pack' \
  --field-catalog-dir '/workspace/.team/交接箱/2026-07-07-第五版候选修订准备/record_template_field_catalog' \
  --release-plan-dir '/workspace/.team/交接箱/2026-07-07-第五版候选修订准备/controlled_release_rehearsal' \
  --json-out '/workspace/.team/交接箱/2026-07-07-第五版候选修订准备/36-LIMS预导入命令release-plan-dry-run报告.json' \
  --md-out '/workspace/.team/交接箱/2026-07-07-第五版候选修订准备/36-LIMS预导入命令release-plan-dry-run报告.md'
```

当前结论：

- 命令返回 `passed`，`release_plan_status=passed`。
- 受控发布演练包覆盖 65 个发布对象、28 个审批签核项、29 个培训宣贯项、28 个旧版处置项、5 个口径闸门和 4 个实施有效性检查项。
- `release_plan_release_allowed_now=0`，说明演练包没有把任何候选对象标为当前可发布。
- 该参数只证明受控发布准备材料可被命令层识别并保持边界，不代表已经批准发布、完成培训、完成旧版处置或允许写库。

## 带受控发布演练包的第二阶段组合 dry-run

```bash
docker compose run --rm \
  -v '/Users/lc.leixyz/Documents/AI工作台/01-项目代码/LIMS-zhj:/workspace' \
  app php think qms:preimport-package \
  --package-dir '/workspace/.team/交接箱/2026-07-07-第五版候选修订准备/lims_preimport_package' \
  --review-dir '/workspace/.team/交接箱/2026-07-07-第五版候选修订准备/human_review_pack' \
  --field-catalog-dir '/workspace/.team/交接箱/2026-07-07-第五版候选修订准备/record_template_field_catalog' \
  --release-plan-dir '/workspace/.team/交接箱/2026-07-07-第五版候选修订准备/controlled_release_rehearsal' \
  --stage2-check \
  --json-out '/workspace/.team/交接箱/2026-07-07-第五版候选修订准备/37-LIMS预导入命令release-plan-stage2-dry-run报告.json' \
  --md-out '/workspace/.team/交接箱/2026-07-07-第五版候选修订准备/37-LIMS预导入命令release-plan-stage2-dry-run报告.md'
```

当前结论：命令返回 `passed`；字段字典状态为 `passed`，受控发布演练包状态为 `passed`，第二阶段结构化关系仍为 `blocked_by_human_review`。阻断原因仍是人工评审包 67 个 pending，而不是 release plan 结构问题。

## 带发布执行记录模板包的 dry-run 命令

```bash
docker compose run --rm \
  -v '/Users/lc.leixyz/Documents/AI工作台/01-项目代码/LIMS-zhj:/workspace' \
  app php think qms:preimport-package \
  --package-dir '/workspace/.team/交接箱/2026-07-07-第五版候选修订准备/lims_preimport_package' \
  --review-dir '/workspace/.team/交接箱/2026-07-07-第五版候选修订准备/human_review_pack' \
  --field-catalog-dir '/workspace/.team/交接箱/2026-07-07-第五版候选修订准备/record_template_field_catalog' \
  --release-plan-dir '/workspace/.team/交接箱/2026-07-07-第五版候选修订准备/controlled_release_rehearsal' \
  --release-execution-dir '/workspace/.team/交接箱/2026-07-07-第五版候选修订准备/release_execution_template_pack' \
  --json-out '/workspace/.team/交接箱/2026-07-07-第五版候选修订准备/40-LIMS预导入命令release-execution-dry-run报告.json' \
  --md-out '/workspace/.team/交接箱/2026-07-07-第五版候选修订准备/40-LIMS预导入命令release-execution-dry-run报告.md'
```

当前结论：

- 命令返回 `passed`，`release_execution_status=passed`。
- 发布执行记录模板包覆盖 6 个候选模板、120 条字段明细、6 条模拟试填。
- 来源计数与受控发布演练包一致：65 个发布对象、28 个审批项、29 个培训项、28 个旧版处置项、4 个有效性检查项。
- 该参数只证明发布执行记录候选模板可被命令层识别，并保持不写数据库、不代表受控发布、不形成真实运行记录的边界。

## 带发布执行记录模板包的第二阶段组合 dry-run

```bash
docker compose run --rm \
  -v '/Users/lc.leixyz/Documents/AI工作台/01-项目代码/LIMS-zhj:/workspace' \
  app php think qms:preimport-package \
  --package-dir '/workspace/.team/交接箱/2026-07-07-第五版候选修订准备/lims_preimport_package' \
  --review-dir '/workspace/.team/交接箱/2026-07-07-第五版候选修订准备/human_review_pack' \
  --field-catalog-dir '/workspace/.team/交接箱/2026-07-07-第五版候选修订准备/record_template_field_catalog' \
  --release-plan-dir '/workspace/.team/交接箱/2026-07-07-第五版候选修订准备/controlled_release_rehearsal' \
  --release-execution-dir '/workspace/.team/交接箱/2026-07-07-第五版候选修订准备/release_execution_template_pack' \
  --stage2-check \
  --json-out '/workspace/.team/交接箱/2026-07-07-第五版候选修订准备/41-LIMS预导入命令release-execution-stage2-dry-run报告.json' \
  --md-out '/workspace/.team/交接箱/2026-07-07-第五版候选修订准备/41-LIMS预导入命令release-execution-stage2-dry-run报告.md'
```

当前结论：命令返回 `passed`；字段字典、受控发布演练包、发布执行记录模板包均为 `passed`，第二阶段结构化关系仍为 `blocked_by_human_review`。阻断原因仍是人工评审包 67 个 pending，而不是发布执行模板结构问题。

## 带质量手册修订路径包的 dry-run 命令

```bash
docker compose run --rm \
  -v '/Users/lc.leixyz/Documents/AI工作台/01-项目代码/LIMS-zhj:/workspace' \
  app php think qms:preimport-package \
  --package-dir '/workspace/.team/交接箱/2026-07-07-第五版候选修订准备/lims_preimport_package' \
  --manual-revision-dir '/workspace/.team/交接箱/2026-07-07-第五版候选修订准备/manual_revision_path_pack' \
  --json-out '/workspace/.team/交接箱/2026-07-07-第五版候选修订准备/51-LIMS预导入命令manual-revision-dry-run报告.json' \
  --md-out '/workspace/.team/交接箱/2026-07-07-第五版候选修订准备/51-LIMS预导入命令manual-revision-dry-run报告.md'
```

当前结论：

- 命令返回 `passed`，`manual_revision_status=passed`。
- 命令层确认 LIMS 当前存在 `XZTC/SC` published 受控文件，候选手册在 `documents_preimport.csv` 中为 `revision_candidate`。
- `manual_revision_existing_route=existing_document_revision_required`，说明第五版候选手册不得按同编号新增草稿直接写入。
- 手册修订路径包包含 9 个修订/换版闸门、5 个 LIMS 动作预览和 5 个人工决策项；5 个人工决策仍为 `pending`。
- 该校验不写数据库，不代表质量手册已批准、已发布或已进入正式修订流程。

## 带质量手册修订路径包的全量组合 dry-run

```bash
docker compose run --rm \
  -v '/Users/lc.leixyz/Documents/AI工作台/01-项目代码/LIMS-zhj:/workspace' \
  app php think qms:preimport-package \
  --package-dir '/workspace/.team/交接箱/2026-07-07-第五版候选修订准备/lims_preimport_package' \
  --review-dir '/workspace/.team/交接箱/2026-07-07-第五版候选修订准备/human_review_pack' \
  --field-catalog-dir '/workspace/.team/交接箱/2026-07-07-第五版候选修订准备/record_template_field_catalog' \
  --release-plan-dir '/workspace/.team/交接箱/2026-07-07-第五版候选修订准备/controlled_release_rehearsal' \
  --release-execution-dir '/workspace/.team/交接箱/2026-07-07-第五版候选修订准备/release_execution_template_pack' \
  --manual-revision-dir '/workspace/.team/交接箱/2026-07-07-第五版候选修订准备/manual_revision_path_pack' \
  --stage2-check \
  --json-out '/workspace/.team/交接箱/2026-07-07-第五版候选修订准备/52-LIMS预导入命令manual-revision-stage2-dry-run报告.json' \
  --md-out '/workspace/.team/交接箱/2026-07-07-第五版候选修订准备/52-LIMS预导入命令manual-revision-stage2-dry-run报告.md'
```

当前结论：命令返回 `passed`；字段字典、受控发布演练、发布执行模板和手册修订路径包均为 `passed`；第二阶段仍为 `blocked_by_human_review`。阻断原因仍是人工评审包 67 个 pending，不是手册修订路径包结构问题。

## 带质量手册修订路径包的 apply 闸门验证

真实待审包下，即使提供 `--apply --ack-human-reviewed`，命令仍返回 `blocked`：

- `human_review_pack_not_approved`：人工评审包尚有 67 项 pending。
- `manual_revision_human_decisions_pending`：质量手册修订/换版路径尚有 5 项人工决策 pending。

同样，使用真实待审包执行 `--apply-rehearsal --ack-human-reviewed --manual-revision-dir` 也会返回 `blocked`。这证明手册修订路径闸门已进入命令层，不会被 rehearsal 绕过。

## 带人工评审包的 dry-run 命令

```bash
docker compose run --rm \
  -v '/Users/lc.leixyz/Documents/AI工作台/01-项目代码/LIMS-zhj:/workspace' \
  app php think qms:preimport-package \
  --package-dir '/workspace/.team/交接箱/2026-07-07-第五版候选修订准备/lims_preimport_package' \
  --review-dir '/workspace/.team/交接箱/2026-07-07-第五版候选修订准备/human_review_pack' \
  --json-out '/workspace/.team/交接箱/2026-07-07-第五版候选修订准备/26-LIMS预导入命令review-pack-dry-run报告.json' \
  --md-out '/workspace/.team/交接箱/2026-07-07-第五版候选修订准备/26-LIMS预导入命令review-pack-dry-run报告.md'
```

当前结论：命令返回 `passed`，但 `review_pack_status=pending`，`review_pack_pending_decisions=67`。这表示命令能读取人工评审包，但人工评审尚未完成，不允许 apply。

## 第二阶段结构化导入预检命令

```bash
docker compose run --rm \
  -v '/Users/lc.leixyz/Documents/AI工作台/01-项目代码/LIMS-zhj:/workspace' \
  app php think qms:preimport-package \
  --package-dir '/workspace/.team/交接箱/2026-07-07-第五版候选修订准备/lims_preimport_package' \
  --review-dir '/workspace/.team/交接箱/2026-07-07-第五版候选修订准备/human_review_pack' \
  --stage2-check \
  --json-out '/workspace/.team/交接箱/2026-07-07-第五版候选修订准备/28-LIMS结构化块与追溯关系stage2-dry-run报告.json' \
  --md-out '/workspace/.team/交接箱/2026-07-07-第五版候选修订准备/28-LIMS结构化块与追溯关系stage2-dry-run报告.md'
```

当前结论：命令返回 `passed`，第二阶段状态为 `blocked_by_human_review`。这表示结构化导入关系本身没有发现阻断，但 67 个人工决策仍为 pending，不能进入后续写库设计。

已预检通过的第二阶段关系：

- LIMS 目标表 `qms_structured_documents`、`qms_document_blocks`、`qms_document_block_links` 均存在。
- 计划结构化文件 65 条、手册块 29 条、追溯矩阵 29 条。
- 手册块与追溯矩阵条款错配数为 0。
- 计划程序块链接 93 个、附件/表单块链接 2 个、记录模板块链接 30 个。

## 第二阶段人工复核工作台命令层闸门

`--stage2-review-dir` 用于让 LIMS 命令层读取 `stage2_structured_review_workbench/`，检查 29 个手册块、125 条块级链接和 154 条人工复核决策模板是否结构一致。该参数不写数据库，不代表第二阶段已导入。

dry-run 命令：

```bash
docker compose run --rm \
  -v '/Users/lc.leixyz/Documents/AI工作台/01-项目代码/LIMS-zhj:/workspace' \
  app php think qms:preimport-package \
  --package-dir '/workspace/.team/交接箱/2026-07-07-第五版候选修订准备/lims_preimport_package' \
  --stage2-review-dir '/workspace/.team/交接箱/2026-07-07-第五版候选修订准备/stage2_structured_review_workbench' \
  --json-out '/workspace/.team/交接箱/2026-07-07-第五版候选修订准备/65-LIMS预导入命令stage2-review-dry-run报告.json' \
  --md-out '/workspace/.team/交接箱/2026-07-07-第五版候选修订准备/65-LIMS预导入命令stage2-review-dry-run报告.md'
```

当前结论：

- 命令返回 `passed`，`stage2_review_status=pending`。
- 复核工作台可被命令层读取：手册块 29 条、块级链接 125 条、条款统计 29 条、目标反查 64 条。
- 人工复核决策 154 条，其中 approved 0、pending 154、revise 0、remove 0，发现项 0。
- 该结论只证明二阶段复核工作台结构可读；不代表人工复核通过。

apply-rehearsal 闸门验证：

```bash
docker compose run --rm \
  -v '/Users/lc.leixyz/Documents/AI工作台/01-项目代码/LIMS-zhj:/workspace' \
  app php think qms:preimport-package \
  --package-dir '/workspace/.team/交接箱/2026-07-07-第五版候选修订准备/lims_preimport_package' \
  --apply-rehearsal \
  --ack-human-reviewed \
  --review-dir '/workspace/.team/交接箱/2026-07-07-第五版候选修订准备/human_review_simulation_pack' \
  --stage2-check \
  --stage2-review-dir '/workspace/.team/交接箱/2026-07-07-第五版候选修订准备/stage2_structured_review_workbench' \
  --json-out '/workspace/.team/交接箱/2026-07-07-第五版候选修订准备/66-LIMS预导入命令stage2-review-apply-rehearsal闸门验证报告.json' \
  --md-out '/workspace/.team/交接箱/2026-07-07-第五版候选修订准备/66-LIMS预导入命令stage2-review-apply-rehearsal闸门验证报告.md'
```

当前结论：即使使用 `human_review_simulation_pack/` 让基础人审包模拟通过，命令仍返回 `blocked`。阻断项为 `stage2_review_not_approved`，因为第二阶段结构化导入人工复核仍有 154 条 pending。该验证证明二阶段复核工作台已经成为独立命令闸门，不能被模拟人审包绕过。

## 第二阶段复核意见预览包命令层闸门

`--stage2-review-preview-dir` 用于让 LIMS 命令层读取 `stage2_structured_review_decision_preview/`，检查二阶段复核意见预览是否结构完整、计数一致、仍有无阻断项。该参数不修改复核工作台，不写数据库，不代表第二阶段已导入。

dry-run 命令：

```bash
docker compose run --rm \
  -v '/Users/lc.leixyz/Documents/AI工作台/01-项目代码/LIMS-zhj:/workspace' \
  app php think qms:preimport-package \
  --package-dir '/workspace/.team/交接箱/2026-07-07-第五版候选修订准备/lims_preimport_package' \
  --stage2-review-preview-dir '/workspace/.team/交接箱/2026-07-07-第五版候选修订准备/stage2_structured_review_decision_preview' \
  --json-out '/workspace/.team/交接箱/2026-07-07-第五版候选修订准备/67-LIMS预导入命令stage2-review-preview-dry-run报告.json' \
  --md-out '/workspace/.team/交接箱/2026-07-07-第五版候选修订准备/67-LIMS预导入命令stage2-review-preview-dry-run报告.md'
```

当前结论：

- 命令返回 `passed`，`stage2_review_preview_status=passed`。
- 预览包可被命令层读取：决策预览 154 条，范围统计 4 行，发现项 0。
- 当前 `stage2_review_preview_readiness=no_proposed_decisions`，`stage2_review_preview_blocking_items=154`。
- 该结论只证明预览包结构可读；不代表二阶段复核意见已经满足放行条件。

apply-rehearsal 闸门验证：

```bash
docker compose run --rm \
  -v '/Users/lc.leixyz/Documents/AI工作台/01-项目代码/LIMS-zhj:/workspace' \
  app php think qms:preimport-package \
  --package-dir '/workspace/.team/交接箱/2026-07-07-第五版候选修订准备/lims_preimport_package' \
  --apply-rehearsal \
  --ack-human-reviewed \
  --review-dir '/workspace/.team/交接箱/2026-07-07-第五版候选修订准备/human_review_simulation_pack' \
  --stage2-review-preview-dir '/workspace/.team/交接箱/2026-07-07-第五版候选修订准备/stage2_structured_review_decision_preview' \
  --json-out '/workspace/.team/交接箱/2026-07-07-第五版候选修订准备/68-LIMS预导入命令stage2-review-preview-apply-rehearsal闸门验证报告.json' \
  --md-out '/workspace/.team/交接箱/2026-07-07-第五版候选修订准备/68-LIMS预导入命令stage2-review-preview-apply-rehearsal闸门验证报告.md'
```

当前结论：即使使用 `human_review_simulation_pack/` 让基础人审包模拟通过，命令仍返回 `blocked`。阻断项为 `stage2_review_preview_has_blocking_items`，因为第二阶段复核意见预览仍有 154 条 blocking items。该验证证明“预览包阻断项未清零”已经成为独立命令闸门。

## 人工评审意见回填预览

人工评审意见不应直接改写 `human_review_pack/`。建议先把意见整理到 `human_review_workbench/decision_update_template.csv`，再生成 `human_review_decision_preview/` 做预览验证。

当前预览结论：

- 预览包结构验证 `passed`。
- `decision_update_template.csv` 尚无拟回填意见，回填就绪度为 `no_proposed_decisions`。
- 67 个决策项仍全部阻断，未修改 `human_review_pack/`。
- 预览包会检查非法决策、缺少 `review_comment`、以及 `XZTC/CX-05-02-2022` 处置结论与 LIMS 通过语义的差异。
- `human_review_decision_preview/` 不能作为 `qms:preimport-package --review-dir`。

## apply-rehearsal 非写库演练

为验证“如果真实人工评审全部通过，LIMS 命令链路是否能接住候选包”，已生成 `human_review_simulation_pack/`。该包把 67 个人工决策项设为模拟通过，并逐行写入 `SIMULATED_APPROVAL_NOT_REAL_REVIEW` 标识。

允许用途：

- 只允许用于 `--apply-rehearsal`。
- 用于验证候选文件、记录模板、字段字典、受控发布演练、发布执行记录模板和第二阶段结构化关系在“人审通过”条件下是否存在命令层阻断。

禁止用途：

- 不代表真实人工评审。
- 不代表质量手册、记录模板或受控发布已批准。
- 不得作为正式 `--apply` 的 `--review-dir`。

演练命令：

```bash
docker compose run --rm \
  -v '/Users/lc.leixyz/Documents/AI工作台/01-项目代码/LIMS-zhj:/workspace' \
  app php think qms:preimport-package \
  --package-dir '/workspace/.team/交接箱/2026-07-07-第五版候选修订准备/lims_preimport_package' \
  --review-dir '/workspace/.team/交接箱/2026-07-07-第五版候选修订准备/human_review_simulation_pack' \
  --field-catalog-dir '/workspace/.team/交接箱/2026-07-07-第五版候选修订准备/record_template_field_catalog' \
  --release-plan-dir '/workspace/.team/交接箱/2026-07-07-第五版候选修订准备/controlled_release_rehearsal' \
  --release-execution-dir '/workspace/.team/交接箱/2026-07-07-第五版候选修订准备/release_execution_template_pack' \
  --stage2-check \
  --apply-rehearsal \
  --ack-human-reviewed \
  --json-out '/workspace/.team/交接箱/2026-07-07-第五版候选修订准备/44-LIMS预导入命令apply-rehearsal报告.json' \
  --md-out '/workspace/.team/交接箱/2026-07-07-第五版候选修订准备/44-LIMS预导入命令apply-rehearsal报告.md'
```

当前结论：

- 命令返回 `rehearsal_ready`。
- `review_pack.is_simulated=1`，`simulation_marker_rows=67`。
- `stage2.status=ready_after_phase1_apply`。
- `rehearsal_plan.database_write_performed=0`。
- 演练只证明命令链路在模拟条件下无结构阻断，不代表可以正式写库。

## LIMS 第一阶段写库行级预览

`--write-preview-dir` 用于把命令层将要评估的第一阶段写库动作导出成只读预览包。该参数只允许在 dry-run 或 `--apply-rehearsal` 使用；若与正式 `--apply` 同时提供，命令会直接拒绝并退出。

演练命令：

```bash
docker compose run --rm \
  -v '/Users/lc.leixyz/Documents/AI工作台/01-项目代码/LIMS-zhj:/workspace' \
  app php think qms:preimport-package \
  --package-dir '/workspace/.team/交接箱/2026-07-07-第五版候选修订准备/lims_preimport_package' \
  --review-dir '/workspace/.team/交接箱/2026-07-07-第五版候选修订准备/human_review_simulation_pack' \
  --field-catalog-dir '/workspace/.team/交接箱/2026-07-07-第五版候选修订准备/record_template_field_catalog' \
  --release-plan-dir '/workspace/.team/交接箱/2026-07-07-第五版候选修订准备/controlled_release_rehearsal' \
  --release-execution-dir '/workspace/.team/交接箱/2026-07-07-第五版候选修订准备/release_execution_template_pack' \
  --stage2-check \
  --apply-rehearsal \
  --ack-human-reviewed \
  --write-preview-dir '/workspace/.team/交接箱/2026-07-07-第五版候选修订准备/lims_write_preview_package' \
  --json-out '/workspace/.team/交接箱/2026-07-07-第五版候选修订准备/46-LIMS预导入命令write-preview-apply-rehearsal报告.json' \
  --md-out '/workspace/.team/交接箱/2026-07-07-第五版候选修订准备/46-LIMS预导入命令write-preview-apply-rehearsal报告.md'
```

当前结论：

- 命令返回 `rehearsal_ready`，`write_preview.status=passed`，`database_write_performed=0`。
- `lims_write_preview_package/` 已生成并通过独立 dry-run 校验，发现项 0。
- `documents_preview_rows=65`：27 条可新增 draft，1 条需走既有文件修订/换版路径，37 条现行程序只做 published 既有文件引用。
- `record_template_preview_rows=26`：26 条记录模板均为 `create_draft`，且记录模板到候选 `documents` 行、2022 程序文件的关联解析均无未解析项。
- `source_preview_rows=4`：4 条外来依据为 upsert 预览，当前不写数据库。
- `XZTC/SC` 第五版候选手册因同编号既有 published 文件存在，被标为 `plan_existing_document_revision`；后续必须设计既有文件修订/换版路径，不能按新增草稿直接写入。

反向门禁：

- `--apply --write-preview-dir` 会被命令直接拒绝。
- 真实待审 `human_review_pack/` 仍为 `review_pack_pending=67`；即使提供 `--apply --ack-human-reviewed`，命令仍返回 `blocked`，不会进入写库事务。

## 质量手册修订/换版路径包

`lims_write_preview_package/` 已确认 `XZTC/SC` 第五版候选手册不是普通新增 draft 路线。后续确认质量手册路径时，应先阅读 `manual_revision_path_pack/`：

- `01-既有质量手册记录核对.csv`：确认同编号既有 published 文件存在。
- `02-修订换版路径闸门清单.csv`：确认 9 个修订/换版闸门。
- `03-LIMS修订动作预览.csv`：确认可能涉及 `documents`、`document_revisions`、结构化文件刷新和受控发布证据。
- `04-人工决策闸门.csv`：确认 5 个质量手册修订路径决策仍为 pending。

当前结论：

- `manual_revision_path_pack/` dry-run 验证 `passed`，发现项 0。
- 该包只证明修订/换版路径可被人工评审，不写数据库。
- 即使后续 `human_review_pack/` 全部通过，`XZTC/SC` 也不应按同编号新增草稿直接写入；应先按既有文件修订/换版路径执行或开发对应受控流程。

## apply 闸门验证

已执行不带人工确认的 apply 验证：

```bash
docker compose run --rm \
  -v '/Users/lc.leixyz/Documents/AI工作台/01-项目代码/LIMS-zhj:/workspace' \
  app php think qms:preimport-package \
  --package-dir '/workspace/.team/交接箱/2026-07-07-第五版候选修订准备/lims_preimport_package' \
  --apply \
  --json-out '/workspace/.team/交接箱/2026-07-07-第五版候选修订准备/22-LIMS预导入apply闸门验证报告.json' \
  --md-out '/workspace/.team/交接箱/2026-07-07-第五版候选修订准备/22-LIMS预导入apply闸门验证报告.md'
```

当前结论：命令返回 `blocked`。阻断原因：

- 未提供 `--ack-human-reviewed`。

已进一步执行带 `--ack-human-reviewed` 和 `--review-dir`、但人工评审包仍为 pending 的 apply 闸门验证：

```bash
docker compose run --rm \
  -v '/Users/lc.leixyz/Documents/AI工作台/01-项目代码/LIMS-zhj:/workspace' \
  app php think qms:preimport-package \
  --package-dir '/workspace/.team/交接箱/2026-07-07-第五版候选修订准备/lims_preimport_package' \
  --review-dir '/workspace/.team/交接箱/2026-07-07-第五版候选修订准备/human_review_pack' \
  --apply \
  --ack-human-reviewed \
  --json-out '/workspace/.team/交接箱/2026-07-07-第五版候选修订准备/27-LIMS预导入review-pack-apply闸门验证报告.json' \
  --md-out '/workspace/.team/交接箱/2026-07-07-第五版候选修订准备/27-LIMS预导入review-pack-apply闸门验证报告.md'
```

当前结论：命令返回 `blocked`。阻断原因：

- 人工评审包尚未全部通过：`pending=67`，`unapproved=0`。

带 `--field-catalog-dir` 后再次执行 apply 闸门验证，结论仍为 `blocked`。字段字典状态为 `passed`，但人工评审包仍有 67 个 pending 决策，因此不会进入写库事务。

带 `--release-plan-dir` 后再次执行 apply 闸门验证，结论仍为 `blocked`。受控发布演练包状态为 `passed`，但 `release_plan_release_allowed_now=0`，人工评审包仍有 67 个 pending 决策，因此不会进入写库事务。

带 `--release-execution-dir` 后再次执行 apply 闸门验证，结论仍为 `blocked`。发布执行记录模板包状态为 `passed`，但人工评审包仍有 67 个 pending 决策，因此不会进入写库事务，也不会生成真实发布执行记录。

带 `human_review_simulation_pack/` 执行真实 `--apply` 时，结论仍为 `blocked`。阻断原因：

- `simulated_human_review_pack_not_allowed_for_apply`：检测到 `SIMULATED_APPROVAL_NOT_REAL_REVIEW` 标识；模拟包只能用于 `--apply-rehearsal`，不得作为正式 `--apply` 的 `--review-dir`。

## 治理就绪总览包与总闸门

`governance_readiness_dashboard/` 用于把分散在人工评审、字段确认、手册换版、发布演练、人员学习、第一阶段预览、第二阶段复核和用户授权中的任务汇成一个总闸门清单。该包不写数据库，不修改任何评审包或 Word 原件，不代表人工评审通过、真实培训完成、受控发布或正式写库授权。

生成与独立校验结果：

- `69-QMS治理就绪总览包生成报告.md/json`：生成 13 个总闸门、396 条人工处理任务。
- `70-QMS治理就绪总览包dry-run验证报告.md/json`：结构校验通过，发现项 0。
- 当前 `ready_for_lims_apply=no`。
- 当前阻断闸门 12 个、阻断任务 392 条。

完整组合 dry-run 命令：

```bash
docker compose run --rm \
  -v '/Users/lc.leixyz/Documents/AI工作台/01-项目代码/LIMS-zhj:/workspace' \
  app php think qms:preimport-package \
  --package-dir '/workspace/.team/交接箱/2026-07-07-第五版候选修订准备/lims_preimport_package' \
  --review-dir '/workspace/.team/交接箱/2026-07-07-第五版候选修订准备/human_review_pack' \
  --field-catalog-dir '/workspace/.team/交接箱/2026-07-07-第五版候选修订准备/record_template_field_catalog' \
  --release-plan-dir '/workspace/.team/交接箱/2026-07-07-第五版候选修订准备/controlled_release_rehearsal' \
  --release-execution-dir '/workspace/.team/交接箱/2026-07-07-第五版候选修订准备/release_execution_template_pack' \
  --manual-revision-dir '/workspace/.team/交接箱/2026-07-07-第五版候选修订准备/manual_revision_path_pack' \
  --staff-training-dir '/workspace/.team/交接箱/2026-07-07-第五版候选修订准备/staff_training_implementation_pack' \
  --stage2-check \
  --stage2-review-dir '/workspace/.team/交接箱/2026-07-07-第五版候选修订准备/stage2_structured_review_workbench' \
  --stage2-review-preview-dir '/workspace/.team/交接箱/2026-07-07-第五版候选修订准备/stage2_structured_review_decision_preview' \
  --governance-readiness-dir '/workspace/.team/交接箱/2026-07-07-第五版候选修订准备/governance_readiness_dashboard' \
  --json-out '/workspace/.team/交接箱/2026-07-07-第五版候选修订准备/71-LIMS预导入命令governance-readiness-dry-run报告.json' \
  --md-out '/workspace/.team/交接箱/2026-07-07-第五版候选修订准备/71-LIMS预导入命令governance-readiness-dry-run报告.md'
```

当前结论：命令返回 `passed`，说明结构可读；同时显示 `governance_readiness_ready_for_lims_apply=no`、`governance_readiness_blocking_tasks=392`。这不是放行结论。

使用模拟人审包执行 apply-rehearsal 时，命令返回 `blocked`，新增治理阻断项包括：

- `governance_readiness_has_blocking_tasks`：治理就绪总览仍存在 392 条阻断任务。
- `governance_readiness_not_ready_for_apply`：总览未达到 `ready_for_lims_apply=yes`。

## 治理关闭工作台与证据回填

`governance_closure_workbench/` 用于把 `governance_readiness_dashboard/02-人工处理任务清单.csv` 中的 396 条人工任务转成可填写的证据采集模板和拟关闭回填模板。该工作台不写数据库，不修改治理总览、人工评审包、第二阶段复核工作台或 Word 原件，不代表人工评审通过、真实培训完成、受控发布或正式写库授权。

生成与独立校验结果：

- `73-QMS治理关闭工作台生成报告.md/json`：生成 13 个闸门关闭矩阵、60 个角色任务批次、396 条证据采集项和 396 条拟关闭回填项。
- `74-QMS治理关闭工作台dry-run验证报告.md/json`：结构校验通过，发现项 0。
- 当前 `ready_for_governance_readiness_refresh=no`。
- 当前 392 条阻断项仍未关闭，0 条已接受关闭。

完整组合 dry-run 命令在治理就绪总览命令基础上增加：

```bash
  --governance-closure-dir '/workspace/.team/交接箱/2026-07-07-第五版候选修订准备/governance_closure_workbench' \
  --json-out '/workspace/.team/交接箱/2026-07-07-第五版候选修订准备/75-LIMS预导入命令governance-closure-dry-run报告.json' \
  --md-out '/workspace/.team/交接箱/2026-07-07-第五版候选修订准备/75-LIMS预导入命令governance-closure-dry-run报告.md'
```

当前结论：命令返回 `passed`，说明工作台结构可读；同时显示 `governance_closure_ready_for_governance_readiness_refresh=no`、`governance_closure_open_blocking_items=392`。这不是放行结论。

使用模拟人审包执行 apply-rehearsal 时，命令返回 `blocked`，新增治理关闭阻断项包括：

- `governance_closure_has_open_blocking_items`：治理关闭工作台仍存在 392 条未关闭阻断项。
- `governance_closure_not_ready_for_refresh`：治理关闭工作台未达到 `ready_for_governance_readiness_refresh=yes`。

## 治理闭环执行包

`governance_closure_execution_pack/` 用于把 `governance_closure_workbench/` 中的证据采集、拟关闭回填和角色任务包整理成可分派、可签核、可回填的执行材料。该执行包不写数据库，不修改治理关闭工作台，不代表人工评审通过、真实培训完成、受控发布或正式写库授权。

生成与独立校验结果：

- `85-QMS治理闭环执行包生成报告.md/json`：生成 60 个执行批次、50 行岗位签核、60 条交接复核和 396 条回填路径。
- `86-QMS治理闭环执行包dry-run验证报告.md/json`：结构校验通过，发现项 0。
- 当前 `ready_for_governance_closure_preview=no`。
- 当前 50 行岗位签核 pending、60 条交接复核 pending、396 条回填路径 pending，392 条回填路径会阻断 apply。

完整组合 dry-run 命令在治理关闭工作台命令基础上增加：

```bash
  --governance-closure-execution-dir '/workspace/.team/交接箱/2026-07-07-第五版候选修订准备/governance_closure_execution_pack' \
  --json-out '/workspace/.team/交接箱/2026-07-07-第五版候选修订准备/87-LIMS预导入命令governance-closure-execution-dry-run报告.json' \
  --md-out '/workspace/.team/交接箱/2026-07-07-第五版候选修订准备/87-LIMS预导入命令governance-closure-execution-dry-run报告.md'
```

当前结论：命令返回 `passed`，说明执行包结构可读；同时显示 `governance_closure_execution_ready_for_preview=no`、`governance_closure_execution_pending_signatures=50`、`governance_closure_execution_pending_handoffs=60`、`governance_closure_execution_pending_routes=396`。这不是放行结论。

使用模拟人审包执行 apply-rehearsal 时，命令返回 `blocked`，新增治理闭环执行阻断项包括：

- `governance_closure_execution_signatures_pending`：治理闭环执行包仍有 50 行岗位签核未完成。
- `governance_closure_execution_handoffs_pending`：治理闭环执行包仍有 60 条交接复核未完成。
- `governance_closure_execution_routes_pending`：治理闭环执行包仍有 396 条回填路径未完成。
- `governance_closure_execution_not_ready_for_preview`：执行包未达到 `ready_for_governance_closure_preview=yes`。

## 治理关闭最小试点包

`governance_closure_pilot_pack/` 用于从 `governance_closure_execution_pack/` 中抽取少量低阻断批次，供组织先试跑“证据、意见、签核、交接、回填路径”的人工闭环。该试点包不写数据库，不修改治理闭环执行包或治理关闭工作台，不代表人工评审通过、真实培训完成、受控发布或正式写库授权。

生成与独立校验结果：

- `89-QMS治理关闭最小试点包生成报告.md/json`：抽取 5 个低阻断试点批次、5 条试点证据填写页和 5 条签核交接页。
- `90-QMS治理关闭最小试点包dry-run验证报告.md/json`：结构校验通过，发现项 0。
- 当前 `ready_for_governance_closure_preview=no`。
- 当前 5 个试点批次 pending、5 条证据填写 pending、5 条签核交接 pending，5 条试点项会阻断 apply。

完整组合 dry-run 命令在治理闭环执行包命令基础上增加：

```bash
  --governance-closure-pilot-dir '/workspace/.team/交接箱/2026-07-07-第五版候选修订准备/governance_closure_pilot_pack' \
  --json-out '/workspace/.team/交接箱/2026-07-07-第五版候选修订准备/91-LIMS预导入命令governance-closure-pilot-dry-run报告.json' \
  --md-out '/workspace/.team/交接箱/2026-07-07-第五版候选修订准备/91-LIMS预导入命令governance-closure-pilot-dry-run报告.md'
```

当前结论：命令返回 `passed`，说明最小试点包结构可读；同时显示 `governance_closure_pilot_ready_for_preview=no`、`governance_closure_pilot_pending_evidence=5`、`governance_closure_pilot_pending_handoffs=5`。这不是放行结论。

使用模拟人审包执行 apply-rehearsal 时，命令返回 `blocked`，新增治理关闭最小试点阻断项包括：

- `governance_closure_pilot_evidence_pending`：治理关闭最小试点包仍有 5 条证据填写未完成。
- `governance_closure_pilot_handoffs_pending`：治理关闭最小试点包仍有 5 条签核/交接未完成。
- `governance_closure_pilot_not_ready_for_preview`：试点包未达到 `ready_for_governance_closure_preview=yes`。

## 治理关闭试点回填预览

`governance_closure_pilot_return_preview/` 用于把 `governance_closure_pilot_pack/02-试点证据填写页.csv` 中的试点结果映射回 `governance_closure_workbench/` 对应关闭项，先生成源行回填预览和缺字段清单。该预览包不写数据库，不修改试点包、治理关闭工作台、治理总览或 Word 原件，不代表人工评审通过、真实培训完成、受控发布或正式写库授权。

生成与独立校验结果：

- `93-QMS治理关闭试点回填预览生成报告.md/json`：生成 5 条试点到源工作台映射、10 条拟回填源行预览和 55 条缺字段清单。
- `94-QMS治理关闭试点回填预览dry-run验证报告.md/json`：结构校验通过，发现项 0。
- 当前 `ready_for_governance_closure_preview=no`。
- 当前 5 条试点回填项仍阻断，55 个字段仍需人工补齐或签核确认。

完整组合 dry-run 命令在治理关闭最小试点包命令基础上增加：

```bash
  --governance-closure-pilot-return-dir '/workspace/.team/交接箱/2026-07-07-第五版候选修订准备/governance_closure_pilot_return_preview' \
  --json-out '/workspace/.team/交接箱/2026-07-07-第五版候选修订准备/95-LIMS预导入命令governance-closure-pilot-return-dry-run报告.json' \
  --md-out '/workspace/.team/交接箱/2026-07-07-第五版候选修订准备/95-LIMS预导入命令governance-closure-pilot-return-dry-run报告.md'
```

当前结论：命令返回 `passed`，说明试点回填预览包结构可读；同时显示 `governance_closure_pilot_return_ready_for_preview=no`、`governance_closure_pilot_return_missing_fields=55`、`governance_closure_pilot_return_blocking_items=5`。这不是放行结论。

使用模拟人审包执行 apply-rehearsal 时，命令返回 `blocked`，新增治理关闭试点回填阻断项包括：

- `governance_closure_pilot_return_missing_fields`：治理关闭试点回填预览仍有 55 个字段未补齐。
- `governance_closure_pilot_return_has_blocking_items`：治理关闭试点回填预览仍存在 5 条阻断回填项。
- `governance_closure_pilot_return_not_ready_for_preview`：试点回填预览未达到 `ready_for_governance_closure_preview=yes`。

## 治理关闭试点源工作台回填补丁预演

`governance_closure_pilot_source_update_rehearsal/` 用于把 `governance_closure_pilot_return_preview/02-拟回填源行预览.csv` 拆成 `governance_closure_workbench/` 两张源表的逐字段补丁预演。该包不写数据库，不修改试点回填预览、治理关闭工作台、治理总览或 Word 原件，不代表人工评审通过、真实培训完成、受控发布或正式写库授权。

生成与独立校验结果：

- `97-QMS治理关闭试点源工作台回填补丁预演生成报告.md/json`：生成 55 条逐字段补丁预演和 55 条阻断补丁清单。
- `98-QMS治理关闭试点源工作台回填补丁预演dry-run验证报告.md/json`：结构校验通过，发现项 0。
- 当前 `ready_for_source_workbench_update=no`。
- 当前 55 条补丁全部阻断，0 条可人工回填候选。

完整组合 dry-run 命令在治理关闭试点回填预览命令基础上增加：

```bash
  --governance-closure-pilot-source-update-dir '/workspace/.team/交接箱/2026-07-07-第五版候选修订准备/governance_closure_pilot_source_update_rehearsal' \
  --json-out '/workspace/.team/交接箱/2026-07-07-第五版候选修订准备/99-LIMS预导入命令governance-closure-pilot-source-update-dry-run报告.json' \
  --md-out '/workspace/.team/交接箱/2026-07-07-第五版候选修订准备/99-LIMS预导入命令governance-closure-pilot-source-update-dry-run报告.md'
```

当前结论：命令返回 `passed`，说明补丁预演包结构可读；同时显示 `governance_closure_pilot_source_update_blocked_patches=55`、`governance_closure_pilot_source_update_ready_patches=0`、`governance_closure_pilot_source_update_ready_for_source_workbench_update=no`。这不是放行结论。

使用模拟人审包执行 apply-rehearsal 时，命令返回 `blocked`，新增源工作台补丁预演阻断项包括：

- `governance_closure_pilot_source_update_has_blocked_patches`：源工作台补丁预演仍有 55 条阻断补丁。
- `governance_closure_pilot_source_update_not_ready_for_source_update`：补丁预演未达到 `ready_for_source_workbench_update=yes`。

## 治理关闭试点人工执行工作簿

`governance_closure_pilot_operator_workbook/` 用于把最小试点包、试点回填预览和源工作台补丁预演中的待办合并成一个人工可执行工作面。该工作簿不写数据库，不修改试点包、源工作台、治理关闭工作台、治理总览或 Word 原件，不代表人工评审通过、真实培训完成、受控发布或正式写库授权。

生成与独立校验结果：

- `101-QMS治理关闭试点人工执行工作簿生成报告.md/json`：生成 5 条试点主任务、55 条逐字段填写项、5 条签核交接项和 5 张任务卡。
- `102-QMS治理关闭试点人工执行工作簿dry-run验证报告.md/json`：结构校验通过，发现项 0。
- 当前 `ready_for_pilot_return_preview=no`，`ready_for_source_workbench_update=no`，`ready_for_lims_apply=no`。
- 当前 5 条主任务 pending、55 条逐字段填写项 pending、5 条签核交接项 pending。

完整组合 dry-run 命令在治理关闭试点源工作台补丁预演命令基础上增加：

```bash
  --governance-closure-pilot-operator-workbook-dir '/workspace/.team/交接箱/2026-07-07-第五版候选修订准备/governance_closure_pilot_operator_workbook' \
  --json-out '/workspace/.team/交接箱/2026-07-07-第五版候选修订准备/103-LIMS预导入命令governance-closure-pilot-operator-workbook-dry-run报告.json' \
  --md-out '/workspace/.team/交接箱/2026-07-07-第五版候选修订准备/103-LIMS预导入命令governance-closure-pilot-operator-workbook-dry-run报告.md'
```

当前结论：命令返回 `passed`，说明人工执行工作簿结构可读；同时显示 `governance_closure_pilot_operator_workbook_pending_items=5`、`governance_closure_pilot_operator_workbook_pending_fields=55`、`governance_closure_pilot_operator_workbook_pending_handoffs=5`、`governance_closure_pilot_operator_workbook_ready_for_pilot_return_preview=no`。这不是放行结论。

使用模拟人审包执行 apply-rehearsal 时，命令返回 `blocked`，新增人工执行工作簿阻断项包括：

- `governance_closure_pilot_operator_workbook_items_pending`：治理关闭试点人工执行工作簿仍有 5 条主任务未完成。
- `governance_closure_pilot_operator_workbook_fields_pending`：治理关闭试点人工执行工作簿仍有 55 条逐字段填写项未完成。
- `governance_closure_pilot_operator_workbook_handoffs_pending`：治理关闭试点人工执行工作簿仍有 5 条签核交接项未完成。
- `governance_closure_pilot_operator_workbook_not_ready_for_return_preview`：工作簿未达到 `ready_for_pilot_return_preview=yes`。

## 治理关闭试点真实执行交回包

`governance_closure_pilot_operator_handback/` 用于接收人员按人工执行工作簿真正完成后的交回结果。它和模拟完成包的区别是：这里必须填写真实证据值、真实执行人、复核人、完成日期和交接状态；不得出现 `SIMULATED` 标识。该包不写数据库，不修改人工执行工作簿、试点包、源工作台、治理总览或 Word 原件，不代表已经完成受控发布或正式写库授权。

生成与独立校验结果：

- `110-QMS治理关闭试点真实执行交回包生成报告.md/json`：生成 5 条试点主任务、55 条逐字段交回项、5 条签核交接项和 5 张任务卡。
- `111-QMS治理关闭试点真实执行交回包dry-run验证报告.md/json`：结构校验通过，发现项 0。
- 当前 `ready_for_pilot_return_preview=no`，`ready_for_source_workbench_update=no`，`ready_for_lims_apply=no`。
- 当前 5 条主任务 pending、55 条逐字段交回项 pending、5 条签核交接项 pending。

完整组合 dry-run 命令在治理关闭试点人工执行工作簿命令基础上增加：

```bash
  --governance-closure-pilot-operator-handback-dir '/workspace/.team/交接箱/2026-07-07-第五版候选修订准备/governance_closure_pilot_operator_handback' \
  --json-out '/workspace/.team/交接箱/2026-07-07-第五版候选修订准备/112-LIMS预导入命令governance-closure-pilot-operator-handback-dry-run报告.json' \
  --md-out '/workspace/.team/交接箱/2026-07-07-第五版候选修订准备/112-LIMS预导入命令governance-closure-pilot-operator-handback-dry-run报告.md'
```

当前结论：命令返回 `passed`，说明真实执行交回包结构可读；同时显示 `governance_closure_pilot_operator_handback_pending_items=5`、`governance_closure_pilot_operator_handback_pending_fields=55`、`governance_closure_pilot_operator_handback_pending_handoffs=5`、`governance_closure_pilot_operator_handback_ready_for_pilot_return_preview=no`。这不是放行结论。

使用模拟人审包执行 apply-rehearsal 时，命令返回 `blocked`，新增真实执行交回阻断项包括：

- `governance_closure_pilot_operator_handback_items_pending`：治理关闭试点真实执行交回包仍有 5 条主任务未完成。
- `governance_closure_pilot_operator_handback_fields_pending`：治理关闭试点真实执行交回包仍有 55 条逐字段交回项未完成。
- `governance_closure_pilot_operator_handback_handoffs_pending`：治理关闭试点真实执行交回包仍有 5 条签核交接项未完成。
- `governance_closure_pilot_operator_handback_not_ready_for_return_preview`：真实交回包未达到 `ready_for_pilot_return_preview=yes`。

## 治理关闭试点人工执行模拟完成包

`governance_closure_pilot_operator_completion_simulation/` 用于验证“如果人员已经按工作簿补齐 5 条试点任务、55 个逐字段填写项和 5 条签核交接项，LIMS 命令层会如何识别后续链路”。该包所有明细均带 `SIMULATED_COMPLETION_NOT_REAL_EXECUTION` 标识，不写数据库，不修改人工执行工作簿、试点包、源工作台、治理总览或 Word 原件，不代表真实人员执行完成。

生成与独立校验结果：

- `105-QMS治理关闭试点人工执行模拟完成包生成报告.md/json`：生成 5 条模拟完成主任务、55 条模拟完成字段项、5 条模拟签核交接项和 5 张任务卡。
- `106-QMS治理关闭试点人工执行模拟完成包dry-run验证报告.md/json`：结构校验通过，发现项 0。
- 当前模拟包 `ready_for_pilot_return_preview=yes`，但 `ready_for_source_workbench_update=no`、`ready_for_lims_apply=no`。
- 当前模拟包 `pending_items=0`、`pending_fields=0`、`pending_handoffs=0`、`marker_rows=65`。

完整组合 dry-run 命令在治理关闭试点人工执行工作簿命令基础上增加：

```bash
  --governance-closure-pilot-operator-completion-simulation-dir '/workspace/.team/交接箱/2026-07-07-第五版候选修订准备/governance_closure_pilot_operator_completion_simulation' \
  --json-out '/workspace/.team/交接箱/2026-07-07-第五版候选修订准备/107-LIMS预导入命令governance-closure-pilot-operator-completion-simulation-dry-run报告.json' \
  --md-out '/workspace/.team/交接箱/2026-07-07-第五版候选修订准备/107-LIMS预导入命令governance-closure-pilot-operator-completion-simulation-dry-run报告.md'
```

当前结论：命令返回 `passed`，说明模拟完成包结构可读；同时显示 `governance_closure_pilot_operator_completion_simulation_status=passed`、`governance_closure_pilot_operator_completion_simulation_ready_for_pilot_return_preview=yes`、`governance_closure_pilot_operator_completion_simulation_pending_items=0`、`governance_closure_pilot_operator_completion_simulation_marker_rows=65`。这只证明命令链路能识别模拟完成态，不是放行结论。

使用模拟人审包执行 apply-rehearsal 时，命令返回 `blocked`：模拟完成包本身通过非写库演练识别，但真实人工执行工作簿、治理关闭工作台、闭环执行包、试点回填预览、源工作台补丁预演等真实待办仍会阻断。正式 `--apply` 使用该模拟包时，命令返回 `blocked`，阻断项包括：

- `governance_closure_pilot_operator_completion_simulation_not_allowed_for_apply`：模拟完成包不得作为正式写库或正式受控治理证据。

## 治理关闭意见回填预览

`governance_closure_decision_preview/` 用于读取 `governance_closure_workbench/04-拟关闭回填模板.csv` 和 `03-证据采集模板.csv`，判断拟关闭意见是否证据充分、字段完整、可进入后续治理就绪刷新。该预览包不写数据库，不修改源工作台，不代表人工评审通过、真实培训完成、受控发布或正式写库授权。

生成与独立校验结果：

- `77-QMS治理关闭意见回填预览生成报告.md/json`：生成 396 条拟关闭决策预览、392 条仍阻断关闭项和按闸门统计。
- `78-QMS治理关闭意见回填预览dry-run验证报告.md/json`：结构校验通过，发现项 0。
- 当前 `ready_for_governance_readiness_refresh=no`。
- 当前 392 条仍阻断，0 条已接受预览。

完整组合 dry-run 命令在治理关闭工作台命令基础上增加：

```bash
  --governance-closure-preview-dir '/workspace/.team/交接箱/2026-07-07-第五版候选修订准备/governance_closure_decision_preview' \
  --json-out '/workspace/.team/交接箱/2026-07-07-第五版候选修订准备/79-LIMS预导入命令governance-closure-preview-dry-run报告.json' \
  --md-out '/workspace/.team/交接箱/2026-07-07-第五版候选修订准备/79-LIMS预导入命令governance-closure-preview-dry-run报告.md'
```

当前结论：命令返回 `passed`，说明预览包结构可读；同时显示 `governance_closure_preview_ready_for_governance_readiness_refresh=no`、`governance_closure_preview_blocking_items=392`。这不是放行结论。

使用模拟人审包执行 apply-rehearsal 时，命令返回 `blocked`，新增治理关闭预览阻断项包括：

- `governance_closure_preview_has_blocking_items`：治理关闭意见回填预览仍存在 392 条阻断项。
- `governance_closure_preview_not_ready_for_refresh`：治理关闭意见回填预览未达到 `ready_for_governance_readiness_refresh=yes`。

## 治理就绪刷新预览

`governance_readiness_refresh_preview/` 用于读取 `governance_readiness_dashboard/` 和 `governance_closure_decision_preview/`，模拟把已接受关闭项刷新回总闸门。该预览包不写数据库，不修改治理总览源文件，不代表人工评审通过、真实培训完成、受控发布或正式写库授权。

生成与独立校验结果：

- `81-QMS治理就绪刷新预览生成报告.md/json`：生成 13 条总闸门刷新预览、396 条人工任务刷新预览、392 条仍阻断任务和刷新差异摘要。
- `82-QMS治理就绪刷新预览dry-run验证报告.md/json`：结构校验通过，发现项 0。
- 当前 `ready_for_lims_apply=no`。
- 当前 0 条关闭项被接受刷新，392 条任务仍阻断，11 个任务级闸门仍阻断。

完整组合 dry-run 命令在治理关闭意见预览命令基础上增加：

```bash
  --governance-readiness-refresh-dir '/workspace/.team/交接箱/2026-07-07-第五版候选修订准备/governance_readiness_refresh_preview' \
  --json-out '/workspace/.team/交接箱/2026-07-07-第五版候选修订准备/83-LIMS预导入命令governance-readiness-refresh-dry-run报告.json' \
  --md-out '/workspace/.team/交接箱/2026-07-07-第五版候选修订准备/83-LIMS预导入命令governance-readiness-refresh-dry-run报告.md'
```

当前结论：命令返回 `passed`，说明刷新预览包结构可读；同时显示 `governance_readiness_refresh_ready_for_lims_apply=no`、`governance_readiness_refresh_blocking_tasks=392`。这不是放行结论。

使用模拟人审包执行 apply-rehearsal 时，命令返回 `blocked`，新增治理就绪刷新阻断项包括：

- `governance_readiness_refresh_has_blocking_tasks`：治理就绪刷新预览仍存在 392 条阻断任务。
- `governance_readiness_refresh_not_ready_for_apply`：刷新预览未达到 `ready_for_lims_apply=yes`。

## 写库前必须处理

| 阻断项 | 影响 | 建议处理 |
|---|---|---|
| 治理总览仍有阻断任务 | 防止各局部清单通过后遗漏总闸门 | 先按 `governance_readiness_dashboard/01-总闸门清单.csv` 和 `02-人工处理任务清单.csv` 关闭阻断任务，再复跑 `--governance-readiness-dir` |
| 治理关闭工作台仍有未关闭阻断项 | 防止“知道阻断项”但没有证据、意见、复核人和日期就进入导入 | 先在 `governance_closure_workbench/03-证据采集模板.csv` 和 `04-拟关闭回填模板.csv` 中补齐真实证据和关闭意见，再复跑 `--governance-closure-dir` |
| 治理闭环执行包仍未签核/交接/回填 | 防止有证据模板但没有责任人签核和交接复核就进入关闭预览 | 依据 `governance_closure_execution_pack/01-闭环执行批次.csv` 和 `02-岗位签核页模板.csv` 分派并签核，再用 `--governance-closure-execution-dir` 确认 pending 清零 |
| 治理关闭最小试点包仍未填证据/签核/交接 | 防止全量关闭任务过多而没有先验证组织闭环路径 | 先依据 `governance_closure_pilot_pack/02-试点证据填写页.csv` 和 `03-试点签核交接页.csv` 试跑 5 个小批次；确认做法后再回填到 `governance_closure_workbench/` 源模板 |
| 治理关闭试点人工执行工作簿仍有待完成项 | 防止人员不知道 5 个试点批次具体要补哪些证据字段和签核交接信息 | 先依据 `governance_closure_pilot_operator_workbook/01-试点执行主清单.csv`、`02-逐字段填写清单.csv`、`03-签核交接核对表.csv` 和 `task_cards/` 补齐 5 条主任务、55 个字段和 5 条签核交接，再复跑 `--governance-closure-pilot-operator-workbook-dir` |
| 治理关闭试点真实执行交回包仍有待完成项 | 防止人员只把状态改成完成，但没有真实证据、执行人、复核人、日期和交接结果 | 在 `governance_closure_pilot_operator_handback/01-真实执行交回主清单.csv`、`02-真实逐字段交回清单.csv`、`03-真实签核交接交回表.csv` 中填写真实值，不得使用 `SIMULATED`，再复跑 `--governance-closure-pilot-operator-handback-dir` |
| 模拟完成包误认为真实执行完成 | 防止演练材料替代真实人员执行、签核和源工作台回填 | `governance_closure_pilot_operator_completion_simulation/` 只能用于 dry-run 或 `--apply-rehearsal` 验证命令链路；真实来源仍是人工执行工作簿和源工作台，正式 `--apply` 已阻断该模拟包 |
| 治理关闭试点回填预览仍有缺字段/阻断项 | 防止试点结果未补齐证据、意见、复核人、日期、签核和交接就误回填源工作台 | 先重新生成 `governance_closure_pilot_return_preview/`，并用 `--governance-closure-pilot-return-dir` 确认 `missing_fields=0`、`blocking_items=0` 且 `ready_for_governance_closure_preview=yes` |
| 治理关闭试点源工作台补丁预演仍有阻断补丁 | 防止试点结果未通过逐字段预演就误改源工作台 | 重新生成 `governance_closure_pilot_source_update_rehearsal/`，确认 `blocked_patch_rows=0`、`manual_update_candidate_rows` 与人工预期一致且 `ready_for_source_workbench_update=yes` |
| 治理关闭意见预览仍有阻断项 | 防止填了源表但未经过预览校验就刷新治理就绪状态 | 重新生成 `governance_closure_decision_preview/`，并用 `--governance-closure-preview-dir` 确认 `blocking_items=0` |
| 治理就绪刷新预览仍有阻断任务 | 防止关闭意见预览未清零时误把治理总览刷新为 ready | 重新生成 `governance_readiness_refresh_preview/`，并用 `--governance-readiness-refresh-dir` 确认 `blocking_tasks=0` 且 `ready_for_lims_apply=yes` |
| 人工评审未确认 | 防止 AI 候选草案直接成为系统事实 | 由文件管理员、质量负责人、相关过程负责人评审候选手册、26 个记录模板和全量试填包，并把评审包 decision 从 pending 改为通过/批准类结果 |
| 人工评审回填未预览 | 防止非法决策、缺少说明或误把预览包当正式评审包 | 先运行 `human_review_decision_preview/` 预览验证，再决定是否正式回填 `human_review_pack/` |
| 第二阶段人工复核未通过 | 防止块级链接未经人工确认就进入结构化导入 | 依据 `stage2_structured_review_workbench/` 完成 154 条复核决策，并先运行 `stage2_structured_review_decision_preview/` 与 `--stage2-review-dir` 命令层校验 |
| 第二阶段复核意见预览仍有阻断项 | 防止未填写、缺说明或非法的二阶段意见进入后续导入 | 重新生成 `stage2_structured_review_decision_preview/`，并用 `--stage2-review-preview-dir` 确认 `blocking_items=0` |
| 模拟人审包误用于正式 apply | 防止演练材料绕过真实人工评审 | LIMS 命令已阻断 `human_review_simulation_pack/` 的真实 `--apply`；该包只能用于 `--apply-rehearsal` |
| 写库预览包误认为已写库 | 防止把行级预览当成正式导入结果 | `lims_write_preview_package/` 只用于查看第一阶段候选写库动作；真实 `--apply` 不允许同时使用 `--write-preview-dir` |
| `XZTC/SC` 同编号修订路径未设计 | 当前 LIMS 控制器禁止同一 `doc_number` 的非删除文件并存；第五版候选手册不能作为同编号新增草稿直接写入 | 先确认既有质量手册的修订/换版治理路径：在既有 `documents` 记录上进入修订流程、设计版本草稿机制，或经组织批准采用明确候选编号策略 |
| `XZTC/CX-05-02-2022` 归属未确认 | 该编号在 LIMS 导出清单中属于编号附件/表单，不应混作程序文件；正式写入前需决定归入程序附件还是记录模板 | 由文件管理员和设备/计量溯源责任人确认归属、字段、保存要求，并决定处置结论如何映射为 LIMS 评审通过语义 |
| 字段字典仍需人工确认 | 命令可验证字段字典结构一致，但不能替代组织对字段含义、保存期限、保密等级和签核规则的确认 | 依据 `record_template_field_catalog/` 逐字段确认后，再决定是否正式回填人工评审包 |
| 受控发布演练仍需人工确认 | 命令可验证发布演练包结构，但不能替代审核批准、培训宣贯和旧版处置 | 依据 `controlled_release_rehearsal/` 确认审批签核、培训对象、旧版作废回收和实施有效性检查安排 |
| 发布执行记录模板仍需人工确认 | 命令可验证候选模板结构、字段和模拟试填，但不能替代真实审批签核、培训签到、发放回收和有效性检查记录 | 依据 `release_execution_template_pack/` 确认 6 类执行记录模板是否符合组织真实流程和 LIMS 字段配置需要 |
| 结构化块和追溯关系仍 deferred | 块级关系过细，容易过度关联 | 人工确认 stable_key、条款、程序、记录、岗位关系后，再开发第二阶段导入 |
| 保存期限和保密等级仍待确认 | 不能由 AI 猜测 | 写入记录模板前补齐或标明组织批准规则 |

## apply 的允许边界

即使后续使用 `--apply --ack-human-reviewed --review-dir` 且人工评审包全部通过，第一阶段命令也只应写入：

- 27 条可新增候选文件/候选记录模板对应的 `documents` draft 行。
- `XZTC/SC` 第五版候选手册不应按同编号新增草稿直接写入；应先完成既有质量手册修订/换版路径设计。
- 26 个 `record_form_templates` draft 模板。
- 4 条外来依据 `qms_sources` 查新状态。

第一阶段命令不应写入：

- 已发布状态的 2022 程序文件。
- 真实 `record_form_instances` 运行记录。
- 结构化块级追溯关系。
- 受控发布、培训宣贯、旧版作废记录。
- 发布执行记录候选模板和模拟试填记录。

## 建议下一步

1. 人工确认 `XZTC/CX-05-02-2022` 归入程序附件还是记录模板。
2. 人工评审 `10-质量手册第五版候选稿.md` 与 `11-第四版到第五版候选修订说明.md`。
3. 人工核对 `record_template_trial_pack/` 中 3 个代表性模拟试填表，以及 `record_template_full_trial_pack/` 中 26 个全量模拟试填表。
4. 根据评审意见修订记录模板字段 schema。
5. 先把拟回填意见写入 `human_review_workbench/decision_update_template.csv`，并生成 `human_review_decision_preview/` 做回填预览。
6. 带 `--field-catalog-dir` 运行 LIMS dry-run，确认字段字典与候选模板 schema 一致。
7. 带 `--release-plan-dir` 运行 LIMS dry-run，确认发布、培训、旧版处置和实施有效性准备不越界。
8. 带 `--release-execution-dir` 运行 LIMS dry-run，确认发布执行记录候选模板、字段和模拟试填不越界。
9. 可用 `human_review_simulation_pack/` 运行 `--apply-rehearsal`，验证人审通过后的命令链路；该步骤不写库，不能替代真实人审。
10. 运行 `--write-preview-dir` 查看第一阶段行级写库预览，重点确认 `XZTC/SC` 修订路径、27 条新增 draft、26 条记录模板和 4 条外来依据 upsert 是否符合组织预期。
11. 完成第二阶段块级链接人工复核后，先运行 `stage2_structured_review_decision_preview/`、`--stage2-review-dir` 和 `--stage2-review-preview-dir` 命令层 dry-run，确认 154 条复核决策无非法项、缺说明项、pending 项或 blocking items。
12. 按 `governance_readiness_dashboard/` 查看总闸门阻断任务，并用 `governance_closure_workbench/` 补齐证据和拟关闭意见。
13. 按 `governance_closure_execution_pack/` 分派执行批次、补齐岗位签核、交接复核和回填路径状态。
14. 先用 `governance_closure_pilot_pack/` 选定 5 个低阻断批次，再用 `governance_closure_pilot_operator_workbook/` 补齐主任务、逐字段填写项、签核交接和任务卡。
15. 将真实完成结果整理到 `governance_closure_pilot_operator_handback/`，确认每个完成项都有真实证据值、执行人、复核人、日期和交接结果。
16. 复跑 `--governance-closure-pilot-operator-workbook-dir --governance-closure-pilot-operator-handback-dir`，确认 5 条主任务、55 个字段和 5 条签核交接不再 pending。
17. 重新生成 `governance_closure_pilot_return_preview/`，确认 5 条试点结果回到源工作台前没有缺字段或阻断项。
18. 重新生成 `governance_closure_pilot_source_update_rehearsal/`，确认逐字段补丁预演没有阻断补丁，且不会改错源表/源字段。
19. 重新生成 `governance_closure_decision_preview/`，确认拟关闭意见没有非法状态、缺证据、缺意见、缺复核人或缺日期。
20. 重新生成 `governance_readiness_refresh_preview/`，确认关闭意见刷新后没有剩余阻断任务。

补充：`governance_closure_pilot_operator_completion_simulation/` 只用于验证上述第 16 步清零后的命令链路，不作为第 14-16 步的替代输入，也不作为真实执行、签核、回填或正式 apply 的证据。
21. 用 `--governance-readiness-dir --governance-closure-dir --governance-closure-execution-dir --governance-closure-pilot-dir --governance-closure-pilot-return-dir --governance-closure-pilot-source-update-dir --governance-closure-pilot-operator-workbook-dir --governance-closure-pilot-operator-handback-dir --governance-closure-preview-dir --governance-readiness-refresh-dir` 复核 `ready_for_lims_apply`、`ready_for_governance_readiness_refresh`、`ready_for_pilot_return_preview` 和 `ready_for_source_workbench_update` 状态。
22. 预览无阻断且经用户明确授权后，再将 `human_review_pack/` 中适用项的 `human_decision` 从 `pending` 改为通过/批准类结果。
23. 仅在用户明确批准后，再运行带 `--ack-human-reviewed --review-dir` 的 apply。
