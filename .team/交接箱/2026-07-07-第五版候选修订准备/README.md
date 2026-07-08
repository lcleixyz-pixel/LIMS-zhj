# 第五版候选修订准备包

生成日期：2026-07-07
阶段：修订草案和治理准备
边界：本目录只放候选修订准备材料，不是受控文件发布目录。

## 这包东西解决什么

本准备包把 QMS 工程包的“编制/审查能力”和 `jewelry-qms` 的“受控治理能力”接起来。当前不直接重写现用《质量手册（第四版）》；先完成画像、差距、证据链、LIMS 治理路径四件事。

## 阅读顺序

1. `00-阶段启动说明.md`：本阶段目标、边界和成功标准。
2. `01-XZTC机构画像参数表-待确认.md`：替换工程包虚构画像所需的真实机构参数。
3. `02-第五版修订任务清单.md`：从桥接检查转成可执行任务。
4. `03-质量手册第五版候选稿编制蓝图.md`：第五版草案应怎么编，而不是直接开始写。
5. `04-LIMS受控治理导入清单.md`：草案如何进入 LIMS 审批、发布、培训和留痕。
6. `05-工程包参数替换清单.md`：工程包里哪些默认假设必须改成 XZTC 事实。
7. `06-条款证据回链起步矩阵.md`：条款到工程包主题/卡片/程序的初始映射。
8. `07-用户拍板问题.md`：进入真正起草前，只需要用户确认的少数关键问题。
9. `08-拍板确认与起草口径.md`：用户已确认的第五版候选稿起草边界。
10. `09-第五版候选稿起草任务卡.md`：交给执行 agent 的自包含起草任务。
11. `10-质量手册第五版候选稿.md`：覆盖 4.1 至 8.9 的第五版候选手册正文。
12. `11-第四版到第五版候选修订说明.md`：供修订审批和差异评审使用。
13. `12-支持性程序目录-2022版.md`：按 LIMS 当前导出的 2022 程序清单形成支持性目录。
14. `13-记录模板包-候选清单.md`：条款到记录模板的候选清单和字段规则。
15. `14-jewelry-qms实施计划与验证方案.md`：建设中系统的后续纳入计划，手册正文不写入。
16. `15-条款程序记录LIMS验证矩阵.md`：条款、程序、记录和治理点的验证矩阵。
17. `16-依据现行性复核记录.md`：关键外来依据的现行性复核记录。
18. `17-候选修订包验证报告.md/json`：只读门禁脚本生成的验证结果。
19. `18-LIMS预导入包dry-run验证报告.md/json`：预导入包结构、编号和人工闸门验证结果。
20. `19-LIMS预导入字段确认清单.md`：写库或开发导入命令前必须确认的字段和边界。
21. `20-记录模板试填dry-run验证报告.md/json`：3 个记录模板模拟试填的字段覆盖和边界验证结果。
22. `21-LIMS预导入命令dry-run报告.md/json`：`jewelry-qms` 命令层读取预导入包的验证结果。
23. `22-LIMS预导入apply闸门验证报告.md/json`：未人工确认时 apply 被阻断的验证结果。
24. `23-LIMS预导入命令使用说明与阻断项.md`：后续如何运行命令、哪些阻断项先处理。
25. `24-LIMS人工评审包dry-run验证报告.md/json`：人工评审包结构、pending 决策和 apply 闸门验证结果。
26. `25-记录模板全量试填dry-run验证报告.md/json`：26 个记录模板全量模拟试填验证结果。
27. `26-LIMS预导入命令review-pack-dry-run报告.md/json`：命令层读取人工评审包的 dry-run 结果。
28. `27-LIMS预导入review-pack-apply闸门验证报告.md/json`：即使带 ack，人工评审包 pending 时仍阻断 apply 的验证结果。
29. `28-LIMS结构化块与追溯关系stage2-dry-run报告.md/json`：第二阶段结构化文件、手册块和追溯关系导入预检结果。
30. `29-LIMS人工评审工作台dry-run验证报告.md/json`：人工评审工作台结构、pending/空白决策和防误用边界验证结果。
31. `30-LIMS人工评审决策回填预览dry-run报告.md/json`：人工评审意见回填前的安全预览验证结果。
32. `31-记录模板字段字典dry-run验证报告.md/json`：26 个候选记录模板字段字典与全量试填一致性验证结果。
33. `32-LIMS预导入命令field-catalog-dry-run报告.md/json`：`qms:preimport-package` 命令层读取字段字典并对照预导入 schema 的 dry-run 结果。
34. `33-LIMS预导入命令field-catalog-stage2-dry-run报告.md/json`：字段字典、人工评审包和第二阶段结构化关系的组合 dry-run 结果。
35. `34-LIMS预导入命令field-catalog-apply闸门验证报告.md/json`：带字段字典和 ack 时，人工评审包 pending 仍阻断 apply 的验证结果。
36. `35-受控发布治理演练dry-run验证报告.md/json`：受控发布、培训、旧版处置和实施有效性演练包的只读验证结果。
37. `36-LIMS预导入命令release-plan-dry-run报告.md/json`：命令层读取受控发布演练包的 dry-run 结果。
38. `37-LIMS预导入命令release-plan-stage2-dry-run报告.md/json`：受控发布演练包、字段字典、人工评审包和第二阶段结构化关系的组合 dry-run 结果。
39. `38-LIMS预导入命令release-plan-apply闸门验证报告.md/json`：带 release plan 和 ack 时，人工评审包 pending 仍阻断 apply 的验证结果。
40. `39-发布执行记录模板dry-run验证报告.md/json`：发布执行记录候选模板包的字段、模拟试填和不写库边界验证结果。
41. `40-LIMS预导入命令release-execution-dry-run报告.md/json`：命令层读取发布执行记录模板包的 dry-run 结果。
42. `41-LIMS预导入命令release-execution-stage2-dry-run报告.md/json`：发布执行模板、受控发布演练、字段字典、人工评审包和第二阶段结构化关系的组合 dry-run 结果。
43. `42-LIMS预导入命令release-execution-apply闸门验证报告.md/json`：带 release execution 和 ack 时，人工评审包 pending 仍阻断 apply 的验证结果。
44. `43-人审通过模拟包dry-run验证报告.md/json`：模拟人审通过包的结构和防误用边界验证结果。
45. `44-LIMS预导入命令apply-rehearsal报告.md/json`：使用模拟人审包验证 apply 前置条件但不写数据库的演练结果。
46. `45-LIMS预导入命令simulated-review-apply阻断验证报告.md/json`：证明模拟人审包不能用于正式 `--apply` 的阻断报告。
47. `46-LIMS预导入命令write-preview-apply-rehearsal报告.md/json`：在 apply-rehearsal 中生成第一阶段写库行级预览包的报告。
48. `47-LIMS写库行级预览包dry-run验证报告.md/json`：独立校验写库行级预览包的文件、计数和边界。
49. `48-LIMS预导入命令真实待审包dry-run复核报告.md/json`：复核真实人工评审包仍为 67 项 pending，第二阶段仍被人审阻断。
50. `49-LIMS预导入命令真实待审包apply阻断验证报告.md/json`：证明真实待审包即使带 `--apply --ack-human-reviewed` 仍会被 67 项 pending 阻断。
51. `50-质量手册修订换版路径dry-run验证报告.md/json`：校验 `XZTC/SC` 既有文件修订/换版路径包的文件、计数和不写库边界。
52. `51-LIMS预导入命令manual-revision-dry-run报告.md/json`：命令层读取质量手册修订/换版路径包的 dry-run 结果。
53. `52-LIMS预导入命令manual-revision-stage2-dry-run报告.md/json`：手册修订路径、人工评审包、字段字典、受控发布演练、发布执行模板和第二阶段结构化关系的组合 dry-run 结果。
54. `53-LIMS预导入命令manual-revision-apply闸门验证报告.md/json`：证明真实待审状态下正式 `--apply` 会同时被 67 项人审 pending 和 5 项手册修订决策 pending 阻断。
55. `54-LIMS预导入命令manual-revision-apply-rehearsal闸门验证报告.md/json`：证明非写库 apply-rehearsal 也会读取手册修订路径包，并在真实待审包 pending 时阻断。
56. `55-机构人员学习实施包dry-run验证报告.md/json`：机构人员学习实施包的结构、边界、岗位任务、理解确认和反馈模板 dry-run 结果。
57. `56-LIMS预导入命令staff-training-dry-run报告.md/json`：`qms:preimport-package --staff-training-dir` 读取机构人员学习实施包的命令层 dry-run 结果。
58. `57-LIMS预导入命令staff-training-stage2-dry-run报告.md/json`：人员学习实施包、手册修订路径、人工评审包、字段字典、受控发布演练、发布执行模板和第二阶段结构化关系的组合 dry-run 结果。
59. `58-LIMS预导入命令staff-training-apply闸门验证报告.md/json`：证明真实待审状态下正式 `--apply` 会被人审 pending、手册修订决策 pending 和人员学习确认 pending 共同阻断。
60. `59-LIMS预导入命令stage2-write-preview报告.md/json`：`qms:preimport-package --stage2-preview-dir` 生成第二阶段结构化导入行级预览包的命令报告。
61. `60-LIMS第二阶段结构化导入行级预览包dry-run验证报告.md/json`：独立校验第二阶段预览包的文件、计数、解析关系和边界。
62. `61-第二阶段结构化导入人工复核工作台生成报告.md/json`：把第二阶段行级预览包整理成人工复核工作台的生成报告。
63. `62-第二阶段结构化导入人工复核工作台dry-run验证报告.md/json`：独立校验复核工作台的计数、pending 决策、空白回填和边界。
64. `63-第二阶段结构化复核意见回填预览生成报告.md/json`：从第二阶段人工复核工作台生成拟回填决策预览包的报告。
65. `64-第二阶段结构化复核意见回填预览dry-run验证报告.md/json`：独立校验二阶段复核意见回填预览包的计数、边界和阻断状态。
66. `65-LIMS预导入命令stage2-review-dry-run报告.md/json`：命令层读取第二阶段人工复核工作台的 dry-run 报告。
67. `66-LIMS预导入命令stage2-review-apply-rehearsal闸门验证报告.md/json`：证明二阶段复核 pending 时会阻断 apply-rehearsal 的报告。
68. `67-LIMS预导入命令stage2-review-preview-dry-run报告.md/json`：命令层读取第二阶段复核意见回填预览包的 dry-run 报告。
69. `68-LIMS预导入命令stage2-review-preview-apply-rehearsal闸门验证报告.md/json`：证明二阶段预览仍有阻断项时会阻断 apply-rehearsal 的报告。
70. `69-QMS治理就绪总览包生成报告.md/json`：汇总生成治理总览包的报告。
71. `70-QMS治理就绪总览包dry-run验证报告.md/json`：独立校验治理总览包文件、计数、边界和阻断状态的报告。
72. `71-LIMS预导入命令governance-readiness-dry-run报告.md/json`：命令层读取治理总览包的完整组合 dry-run 报告。
73. `72-LIMS预导入命令governance-readiness-apply-rehearsal闸门验证报告.md/json`：证明治理总览未 ready 时会阻断 apply-rehearsal 的报告。
74. `governance_readiness_dashboard/`：治理就绪总览包；把 13 个总闸门和 396 条人工处理任务汇总成可验收清单，当前仍不允许 LIMS apply。
75. `lims_preimport_package/`：文件、结构化文件、记录模板、追溯矩阵、手册块级索引和外来依据候选 CSV/JSON。
76. `lims_write_preview_package/`：LIMS 第一阶段行级写库预览包；只读展示 `documents`、`record_form_templates`、`qms_sources` 可能动作，不写数据库。
77. `lims_stage2_write_preview_package/`：LIMS 第二阶段结构化导入行级预览包；只读展示 `qms_structured_documents`、`qms_document_blocks`、`qms_document_block_links` 可能动作，不写数据库。
78. `stage2_structured_review_workbench/`：第二阶段结构化导入人工复核工作台；按手册块、块级链接、条款统计、目标反查和人工回填模板组织复核。
79. `stage2_structured_review_decision_preview/`：第二阶段结构化复核意见回填预览包；读取复核工作台的拟决策并判断哪些仍阻断，不修改复核工作台。
80. `manual_revision_path_pack/`：质量手册第五版候选稿的既有文件修订/换版路径包；只读列出 9 个修订闸门、5 个 LIMS 动作预览和 5 个人工决策项。
81. `staff_training_implementation_pack/`：机构人员学习实施包；把 29 个培训宣贯源条目拆成 88 个岗位学习任务、10 份岗位一页卡、理解确认题和反馈模板。
82. `record_template_trial_pack/`：公正性风险、合同评审、内审整改 3 个记录模板的代表性模拟试填包。
83. `record_template_full_trial_pack/`：26 个记录模板的全量模拟试填包。
84. `record_template_field_catalog/`：26 个候选记录模板的字段字典、字段级明细、通用字段矩阵和逐模板填写规则。
85. `controlled_release_rehearsal/`：受控发布、审批签核、培训宣贯、旧版处置和实施有效性检查的干运行准备包。
86. `release_execution_template_pack/`：审批签核、发布发放、培训、旧版处置、有效性检查和 jewelry-qms 试运行确认的发布执行记录候选模板包。
87. `human_review_pack/`：条款人工评审、记录模板评审、05-02 归属判定和 apply 前决策闸门。
88. `human_review_simulation_pack/`：apply-rehearsal 专用模拟人审通过包；不得作为正式 `--apply` 的 `--review-dir`。
89. `human_review_workbench/`：面向人工评审的总览、按角色清单、条款工作台、记录模板工作台、05-02 判定工作台和决策回填模板。
90. `human_review_decision_preview/`：决策回填预览包；不修改 `human_review_pack/`，不能作为 `--review-dir`。
91. `73-QMS治理关闭工作台生成报告.md/json`：从治理就绪总览生成治理关闭工作台的报告。
92. `74-QMS治理关闭工作台dry-run验证报告.md/json`：独立校验治理关闭工作台文件、计数、边界和阻断状态的报告。
93. `75-LIMS预导入命令governance-closure-dry-run报告.md/json`：命令层读取治理关闭工作台的完整组合 dry-run 报告。
94. `76-LIMS预导入命令governance-closure-apply-rehearsal闸门验证报告.md/json`：证明治理关闭工作台未 ready 时会阻断 apply-rehearsal 的报告。
95. `governance_closure_workbench/`：治理关闭工作台；把 396 条人工处理任务转成证据采集模板和拟关闭回填模板，当前 392 条阻断项仍未关闭。
96. `77-QMS治理关闭意见回填预览生成报告.md/json`：从治理关闭工作台生成拟关闭意见回填预览包的报告。
97. `78-QMS治理关闭意见回填预览dry-run验证报告.md/json`：独立校验治理关闭意见预览包文件、计数、边界和阻断状态的报告。
98. `79-LIMS预导入命令governance-closure-preview-dry-run报告.md/json`：命令层读取治理关闭意见预览包的完整组合 dry-run 报告。
99. `80-LIMS预导入命令governance-closure-preview-apply-rehearsal闸门验证报告.md/json`：证明治理关闭意见预览未 ready 时会阻断 apply-rehearsal 的报告。
100. `governance_closure_decision_preview/`：治理关闭意见回填预览包；读取拟关闭意见并生成仍阻断清单，当前 392 条仍阻断。
101. `81-QMS治理就绪刷新预览生成报告.md/json`：从治理总览和治理关闭意见预览生成治理就绪刷新预览包的报告。
102. `82-QMS治理就绪刷新预览dry-run验证报告.md/json`：独立校验治理就绪刷新预览包文件、计数、边界和阻断状态的报告。
103. `83-LIMS预导入命令governance-readiness-refresh-dry-run报告.md/json`：命令层读取治理就绪刷新预览包的完整组合 dry-run 报告。
104. `84-LIMS预导入命令governance-readiness-refresh-apply-rehearsal闸门验证报告.md/json`：证明治理就绪刷新预览仍未 ready 时会阻断 apply-rehearsal 的报告。
105. `governance_readiness_refresh_preview/`：治理就绪刷新预览包；读取关闭意见预览后模拟刷新总闸门状态，当前仍有 392 条任务阻断、11 个任务级闸门阻断，不写回治理总览。
106. `85-QMS治理闭环执行包生成报告.md/json`：从治理关闭工作台生成闭环执行包的报告。
107. `86-QMS治理闭环执行包dry-run验证报告.md/json`：独立校验治理闭环执行包批次、签核、交接和回填路径的报告。
108. `87-LIMS预导入命令governance-closure-execution-dry-run报告.md/json`：命令层读取治理闭环执行包的完整组合 dry-run 报告。
109. `88-LIMS预导入命令governance-closure-execution-apply-rehearsal闸门验证报告.md/json`：证明治理闭环执行包未签核、未交接、未回填时会阻断 apply-rehearsal 的报告。
110. `governance_closure_execution_pack/`：治理闭环执行包；把 396 条关闭项组织成 60 个执行批次、50 行岗位签核、60 条交接复核和 396 条回填路径，当前全部保持 pending，不写库。
111. `89-QMS治理关闭最小试点包生成报告.md/json`：从治理闭环执行包抽取少量低阻断批次形成最小人工试点工作面的报告。
112. `90-QMS治理关闭最小试点包dry-run验证报告.md/json`：独立校验治理关闭最小试点包批次、证据页、签核交接页和边界标识的报告。
113. `91-LIMS预导入命令governance-closure-pilot-dry-run报告.md/json`：命令层读取治理关闭最小试点包的完整组合 dry-run 报告。
114. `92-LIMS预导入命令governance-closure-pilot-apply-rehearsal闸门验证报告.md/json`：证明最小试点包仍有证据和签核交接 pending 时会阻断 apply-rehearsal 的报告。
115. `governance_closure_pilot_pack/`：治理关闭最小试点包；从执行包抽取 5 个低阻断批次、5 条试点证据填写页和 5 条签核交接页，供人工先试跑闭环，不写库、不代表真实关闭。
116. `93-QMS治理关闭试点回填预览生成报告.md/json`：把最小试点证据映射回治理关闭工作台对应关闭项的生成报告。
117. `94-QMS治理关闭试点回填预览dry-run验证报告.md/json`：独立校验试点回填预览包文件、计数、缺字段和不写库边界的报告。
118. `95-LIMS预导入命令governance-closure-pilot-return-dry-run报告.md/json`：命令层读取试点回填预览包的完整组合 dry-run 报告。
119. `96-LIMS预导入命令governance-closure-pilot-return-apply-rehearsal闸门验证报告.md/json`：证明试点结果缺字段、仍阻断时会阻断 apply-rehearsal 的报告。
120. `governance_closure_pilot_return_preview/`：治理关闭试点回填预览包；把 5 条试点证据映射回源工作台，生成拟回填源行预览和 55 条缺字段清单，不修改源工作台、不写库。
121. `97-QMS治理关闭试点源工作台回填补丁预演生成报告.md/json`：从试点回填预览和治理关闭工作台生成源工作台逐字段补丁预演的报告。
122. `98-QMS治理关闭试点源工作台回填补丁预演dry-run验证报告.md/json`：独立校验补丁预演包文件、计数、阻断补丁和不写库边界的报告。
123. `99-LIMS预导入命令governance-closure-pilot-source-update-dry-run报告.md/json`：命令层读取源工作台回填补丁预演包的完整组合 dry-run 报告。
124. `100-LIMS预导入命令governance-closure-pilot-source-update-apply-rehearsal闸门验证报告.md/json`：证明补丁仍阻断、源工作台未 ready 时会阻断 apply-rehearsal 的报告。
125. `governance_closure_pilot_source_update_rehearsal/`：治理关闭试点源工作台回填补丁预演包；把 10 条拟回填源行拆成 55 条逐字段补丁预演，当前 55 条全部阻断，不修改源工作台、不写库。
126. `101-QMS治理关闭试点人工执行工作簿生成报告.md/json`：从最小试点包、试点回填预览和源工作台补丁预演生成试点人工执行工作簿的报告。
127. `102-QMS治理关闭试点人工执行工作簿dry-run验证报告.md/json`：独立校验人工执行工作簿文件、计数、待办状态和不写库边界的报告。
128. `103-LIMS预导入命令governance-closure-pilot-operator-workbook-dry-run报告.md/json`：命令层读取人工执行工作簿的完整组合 dry-run 报告。
129. `104-LIMS预导入命令governance-closure-pilot-operator-workbook-apply-rehearsal闸门验证报告.md/json`：证明人工执行工作簿仍有主任务、字段和签核交接 pending 时会阻断 apply-rehearsal 的报告。
130. `governance_closure_pilot_operator_workbook/`：治理关闭试点人工执行工作簿；把 5 条试点主任务、55 条逐字段填写项、5 条签核交接项和 5 张任务卡合并成可人工执行的工作面，不修改试点包、源工作台或数据库。
131. `105-QMS治理关闭试点人工执行模拟完成包生成报告.md/json`：从人工执行工作簿生成“假如 5 条试点任务已由人员补齐”的模拟完成包生成报告。
132. `106-QMS治理关闭试点人工执行模拟完成包dry-run验证报告.md/json`：独立校验模拟完成包文件、计数、模拟标识和不写库边界的报告。
133. `107-LIMS预导入命令governance-closure-pilot-operator-completion-simulation-dry-run报告.md/json`：命令层读取模拟完成包的完整组合 dry-run 报告。
134. `108-LIMS预导入命令governance-closure-pilot-operator-completion-simulation-apply-rehearsal闸门验证报告.md/json`：证明模拟完成包在非写库演练中可被识别，但真实待办和其它闸门仍会阻断 apply-rehearsal 的报告。
135. `109-LIMS预导入命令governance-closure-pilot-operator-completion-simulation-apply阻断验证报告.md/json`：证明模拟完成包不得用于正式 apply 的阻断报告。
136. `governance_closure_pilot_operator_completion_simulation/`：治理关闭试点人工执行模拟完成包；仅用于验证“人工完成后命令链路如何判断”，所有行带模拟标识，不修改人工执行工作簿、源工作台或数据库，不代表真实执行完成。
137. `110-QMS治理关闭试点真实执行交回包生成报告.md/json`：从人工执行工作簿生成真实执行结果交回包的报告。
138. `111-QMS治理关闭试点真实执行交回包dry-run验证报告.md/json`：独立校验真实交回包文件、待完成状态、真实值要求和不写库边界的报告。
139. `112-LIMS预导入命令governance-closure-pilot-operator-handback-dry-run报告.md/json`：命令层读取真实交回包的完整组合 dry-run 报告。
140. `113-LIMS预导入命令governance-closure-pilot-operator-handback-apply-rehearsal闸门验证报告.md/json`：证明真实交回包仍有主任务、字段和签核交接 pending 时会阻断 apply-rehearsal 的报告。
141. `114-LIMS预导入命令governance-closure-pilot-operator-handback-apply阻断验证报告.md/json`：证明正式 apply 会同时阻断未完成真实交回和模拟完成包误用的报告。
142. `governance_closure_pilot_operator_handback/`：治理关闭试点真实执行交回包；用于接收人员真实填写、真实执行人、复核人、日期和交接结果，初始 5 条主任务、55 条字段、5 条签核交接均 pending，不修改人工执行工作簿、源工作台或数据库。

## 已引用的前置产物

- `../2026-07-07-qms工程包到LIMS桥接治理检查.md`
- `../2026-07-07-qms工程包到LIMS桥接治理检查.json`
- `../2026-07-07-现用质量手册工程包桥接验证报告.md`

## 当前判断

现用手册条款骨架完整，可以作为第五版候选修订的基线。用户已确认：资质状态按“已取得 CMA，CNAS 申请中”；程序目录以 LIMS 当前导出的 2022 程序清单为现行目录；`jewelry-qms` 作为建设中系统，不写入手册正文，仅写入实施计划。

第五版候选稿正文、修订说明、程序目录、记录模板、实施计划和验证矩阵已形成。只读门禁脚本验证通过：条款覆盖 29/29，LIMS 2022 清单编号 38/38（程序 37，编号附件/表单 1），发现项 0。

LIMS 预导入包已形成并通过 dry-run：文件控制/结构化文件 65 条、记录模板 26 条、追溯矩阵 29 条、手册块级索引 29 条、外来依据候选 4 条，发现项 0。该包仍是预导入草案，不写数据库。

记录模板模拟试填包已形成并通过 dry-run：公正性风险、合同评审、内审整改 3 个实例，Markdown 试填表 3 份，发现项 0。试填内容均带 `SIMULATED_TRIAL_NOT_REAL_RECORD` 标识，不作为真实运行记录，不得导入生产库，不得作为受控记录。

记录模板全量模拟试填包已形成并通过 dry-run：26 个记录模板全部生成 CSV/JSON/Markdown 试填实例，发现项 0。该包用于逐项检查字段可填性，仍不作为真实运行记录。

记录模板字段字典包已形成并通过 dry-run：26 个候选记录模板、437 条字段明细、26 份逐模板字段字典 Markdown，发现项 0。该包把 `field_schema_json` 转为可人工评审的字段字典，逐字段标出必填性、通用/专项分类、试填覆盖和人工确认点；仍不写数据库，不代表受控发布或真实记录形成。

`jewelry-qms` 已新增 `qms:preimport-package` 命令并通过容器 dry-run。命令层可识别 65 条文件候选、26 条记录模板候选、29 条追溯矩阵和 4 条外来依据；37 个现行程序文件已全部匹配，`XZTC/CX-05-02-2022` 已按编号附件/表单分流，`missing_reference_current_documents=0`。apply 闸门验证显示未提供人工确认时会被阻断，复跑 dry-run 证明未发生记录模板写库。

`qms:preimport-package` 已接入字段字典 dry-run：提供 `--field-catalog-dir` 后，命令层可读取 `record_template_field_catalog/`，并对照 `lims_preimport_package/record_form_templates_preimport.csv` 校验 26 个模板、437 条字段明细、26 份逐模板字段字典，发现项 0。该校验只证明字段字典与候选模板 schema 一致，不代表模板已经批准或写入 LIMS。

带 `--field-catalog-dir --stage2-check` 的组合 dry-run 已通过：字段字典状态 `passed`，第二阶段仍为 `blocked_by_human_review`。带 `--field-catalog-dir --apply --ack-human-reviewed --review-dir` 的 apply 闸门验证仍返回 `blocked`，阻断原因仍是人工评审包 67 个 pending；复跑 dry-run 显示 `existing_record_template_matches=0`，未发生记录模板写库。

LIMS 人工评审包已形成并通过 dry-run：条款人工评审 29 条、记录模板评审 26 条、05-02 归属判定 1 条、apply 前决策闸门 11 条，发现项 0。所有 `human_decision` 均保持 `pending`，不代表已经人工批准。

`qms:preimport-package` 已接入人工评审包闸门。带 `--review-dir` 的 dry-run 可读取 `human_review_pack/` 并显示 `review_pack_status=pending`、`review_pack_pending_decisions=67`；即使提供 `--apply --ack-human-reviewed --review-dir`，只要人工评审包未全部通过，命令仍返回 `blocked`，不会进入写库事务。

`qms:preimport-package --stage2-check` 已完成第二阶段结构化导入预检：LIMS 中 `qms_structured_documents`、`qms_document_blocks`、`qms_document_block_links` 目标表可用；65 条结构化文件、29 个手册块、29 条追溯矩阵可形成导入计划；手册块与追溯矩阵条款错配为 0；计划形成程序块链接 93 个、附件/表单块链接 2 个、记录模板块链接 30 个。当前阶段状态为 `blocked_by_human_review`，阻断原因仍是 67 个人工决策 pending，而不是结构化关系缺失。

LIMS 人工评审工作台已形成并通过 dry-run：总决策项 67 个，包含 29 个条款评审、26 个记录模板评审、1 个 05-02 归属判定和 11 个 apply 前闸门；按 10 类角色拆分；记录模板工作台链接 26 份全量模拟试填表。`decision_update_template.csv` 中 `new_human_decision` 初始为空，工作台不修改 `human_review_pack/`，不代表已经批准。

LIMS 人工评审决策回填预览包已形成并通过 dry-run：当前 `decision_update_template.csv` 尚无拟回填意见，预览状态为 `no_proposed_decisions`；67 项仍全部阻断，发现项 0。预览包会校验拟回填意见是否合法、是否有审查说明，并提示 `XZTC/CX-05-02-2022` 的处置结论不能简单等同于普通 `approved`。该预览包不写库、不修改 `human_review_pack/`，也不能作为 `qms:preimport-package --review-dir`。

受控发布治理演练包已形成并通过 dry-run：`controlled_release_rehearsal/` 覆盖 65 个发布对象、28 个审批签核演练项、29 个培训宣贯演练项、28 个旧版处置演练项、5 个口径闸门和 4 个实施有效性检查项，发现项 0。`qms:preimport-package --release-plan-dir` 已可读取该包；命令层 dry-run 显示 `release_plan_status=passed`、`release_plan_release_allowed_now=0`。带 release plan、字段字典、人工评审包和 `--stage2-check` 的组合 dry-run 通过，但第二阶段仍因 67 个人审 pending 处于 `blocked_by_human_review`。带 `--apply --ack-human-reviewed --review-dir --release-plan-dir` 的闸门验证返回 `blocked`，阻断原因仍是人工评审包 pending，不会进入写库事务。

发布执行记录模板包已形成并通过 dry-run：`release_execution_template_pack/` 覆盖 6 个发布执行候选记录模板、120 条字段明细、6 条模拟试填，来源对应 65 个发布对象、28 个审批项、29 个培训项、28 个旧版处置项和 4 个有效性检查项，发现项 0。`qms:preimport-package --release-execution-dir` 已可读取该包；命令层 dry-run 显示 `release_execution_status=passed`。带 release execution、release plan、字段字典、人工评审包和 `--stage2-check` 的组合 dry-run 通过，但第二阶段仍因 67 个人审 pending 处于 `blocked_by_human_review`。带 `--apply --ack-human-reviewed --review-dir --release-execution-dir` 的闸门验证返回 `blocked`，阻断原因仍是人工评审包 pending，不会进入写库事务，也不会形成真实运行记录。

人审通过模拟包已形成并通过 dry-run：`human_review_simulation_pack/` 将 67 个人工决策项设为模拟通过，并逐行写入 `SIMULATED_APPROVAL_NOT_REAL_REVIEW` 标识，发现项 0。`qms:preimport-package --apply-rehearsal --ack-human-reviewed --review-dir human_review_simulation_pack` 已完成非写库演练：`status=rehearsal_ready`、`review_pack.is_simulated=1`、`stage2.status=ready_after_phase1_apply`、`database_write_performed=0`。同一模拟包若用于真实 `--apply`，命令返回 `blocked`，阻断项为 `simulated_human_review_pack_not_allowed_for_apply`，因此模拟包不能绕过真实人工评审和用户授权。

LIMS 第一阶段写库行级预览包已形成并通过 dry-run：`lims_write_preview_package/` 展示 65 条 `documents` 行级动作、26 条 `record_form_templates` draft 预览和 4 条 `qms_sources` upsert 预览，发现项 0。预览结果显示：27 条候选文件可作为新增 draft，37 条 2022 现行程序只做既有 published 文件引用，`XZTC/SC` 第五版候选手册因同编号既有 published 文件存在，被单独标为 `plan_existing_document_revision`，必须走既有文件修订/换版治理路线，不能按新增草稿直接写入。真实人工评审包复核仍为 `review_pack_pending=67`；真实 `--apply --ack-human-reviewed` 仍返回 `blocked`，未进入写库事务。

质量手册修订/换版路径包已形成并通过 dry-run：`manual_revision_path_pack/` 针对 `XZTC/SC` 生成 1 条既有质量手册记录核对、9 个修订/换版闸门、5 个 LIMS 动作预览和 5 个人工决策项，发现项 0。该包确认当前路径不是同编号新增草稿，而是先经人工评审后，按 LIMS 既有 `/document/revise?id=<existing_documents.id>`、`document_revisions` 快照、结构化文件刷新和受控发布证据链处理。所有人工决策仍为 `pending`，不写数据库，不代表批准发布。

`qms:preimport-package` 已接入 `--manual-revision-dir`：命令层 dry-run 可读取 `manual_revision_path_pack/`，并对照当前 LIMS 既有 `XZTC/SC` published 文件和 `documents_preimport.csv` 中的第五版候选手册，确认路线为 `existing_document_revision_required`。带人工评审包、字段字典、受控发布演练、发布执行模板和 `--stage2-check` 的组合 dry-run 返回 `passed`；真实 `--apply --ack-human-reviewed` 与 `--apply-rehearsal` 在使用真实待审包时均返回 `blocked`，阻断项为 67 项人审 pending 和 5 项手册修订决策 pending，未写数据库。

机构人员学习实施包已形成并通过 dry-run：`staff_training_implementation_pack/` 将 29 个培训宣贯源条目拆成 88 个岗位学习任务、12 个学习材料入口、12 道理解确认题、8 行问题反馈模板和 10 份岗位一页卡，发现项 0。该包用于把候选手册、记录模板、受控发布、质量手册修订路径和 jewelry-qms 试运行边界交给机构人员学习确认；不写数据库，不代表真实培训完成、人工评审通过或受控发布。

`qms:preimport-package` 已接入 `--staff-training-dir`：命令层 dry-run 可读取 `staff_training_implementation_pack/`，确认 29 个培训源项、88 个岗位学习任务、12 道理解确认题、8 行反馈模板、10 份岗位卡均通过只读校验。带人工评审包、字段字典、受控发布演练、发布执行模板、质量手册修订路径和 `--stage2-check` 的组合 dry-run 返回 `passed`；真实 `--apply --ack-human-reviewed` 返回 `blocked`，阻断项为 67 项人审 pending、5 项手册修订决策 pending、88 项学习任务 pending、12 项理解确认 pending 和 8 项反馈回填空白，未写数据库。

LIMS 第二阶段结构化导入行级预览包已形成并通过 dry-run：`lims_stage2_write_preview_package/` 展示 65 条 `qms_structured_documents` 预览、29 条 `qms_document_blocks` 预览和 125 条 `qms_document_block_links` 预览，发现项 0，未解析目标 0。块级链接中程序文件链接 93 条、附件/表单链接 2 条、记录模板链接 30 条；所有预览行均保持 `write_now=no`。该包只摊开第二阶段影响面，不代表第二阶段已导入、人工评审通过或允许写库。

第二阶段结构化导入人工复核工作台已形成并通过 dry-run：`stage2_structured_review_workbench/` 把第二阶段预览包整理成 29 条手册块复核矩阵、125 条块级链接复核矩阵、29 条按条款目标统计、64 条目标文件/记录反查清单和 154 条人工复核意见回填模板，发现项 0。所有 `human_decision` 仍为 `pending`，回填意见为空，不写数据库，不代表人工评审通过。

第二阶段结构化复核意见回填预览包已形成并通过 dry-run：`stage2_structured_review_decision_preview/` 只读取 `stage2_structured_review_workbench/05-人工复核意见回填模板.csv` 的拟决策，当前预览状态为 `no_proposed_decisions`，154 条拟决策均为空，154 条仍全部阻断，发现项 0。该包不修改复核工作台，不写数据库，不代表人工评审通过；后续人工填写意见后应先重新生成本预览包，再讨论是否进入受控回填路径。

`qms:preimport-package` 已接入 `--stage2-review-dir`：命令层 dry-run 可读取 `stage2_structured_review_workbench/`，确认 29 条手册块、125 条块级链接、64 条目标反查和 154 条二阶段复核决策均结构可读；当前 `stage2_review_status=pending`，154 条复核决策仍为 pending，发现项 0。使用 `human_review_simulation_pack/` 执行 `--apply-rehearsal --stage2-check --stage2-review-dir` 时，基础人审模拟通过、stage2 结构预检 ready，但命令仍因 `stage2_review_not_approved` 返回 `blocked`，证明二阶段人工复核不能被模拟人审包绕过。

`qms:preimport-package` 已接入 `--stage2-review-preview-dir`：命令层 dry-run 可读取 `stage2_structured_review_decision_preview/`，确认 154 条预览结果结构可读，当前 `stage2_review_preview_status=passed`、`stage2_review_preview_readiness=no_proposed_decisions`、`stage2_review_preview_blocking_items=154`。使用 `human_review_simulation_pack/` 执行 `--apply-rehearsal --stage2-review-preview-dir` 时，命令因 `stage2_review_preview_has_blocking_items` 返回 `blocked`，证明二阶段复核意见预览未清零前不能进入后续导入路径。

治理就绪总览包已形成并通过 dry-run：`governance_readiness_dashboard/` 汇总 13 个总闸门和 396 条人工处理任务，当前 `ready_for_lims_apply=no`、阻断闸门 12 个、阻断任务 392 条，发现项 0。`qms:preimport-package --governance-readiness-dir` 已可读取该包；完整组合 dry-run 返回 `passed`，但使用模拟人审包执行 apply-rehearsal 时仍因质量手册修订决策、人员学习确认、第二阶段复核、第二阶段预览和治理总览阻断任务返回 `blocked`。该总览只帮助用户按闸门关闭任务，不代表人工评审通过、真实培训完成、受控发布或正式写库授权。

治理关闭工作台已形成并通过 dry-run：`governance_closure_workbench/` 将治理总览中的 396 条人工处理任务转成 396 条证据采集项和 396 条拟关闭回填项，当前 `ready_for_governance_readiness_refresh=no`、392 条阻断项仍未关闭、0 条已接受关闭，发现项 0。`qms:preimport-package --governance-closure-dir` 已可读取该包；完整组合 dry-run 返回 `passed`，但使用模拟人审包执行 apply-rehearsal 时仍因 `governance_closure_has_open_blocking_items` 和 `governance_closure_not_ready_for_refresh` 返回 `blocked`。该工作台只帮助人工补证据和拟关闭意见，不代表人工评审通过、真实培训完成、受控发布或正式写库授权。

治理闭环执行包已形成并通过 dry-run：`governance_closure_execution_pack/` 将治理关闭工作台中的 396 条关闭项组织成 60 个执行批次、50 行岗位签核、60 条交接复核和 396 条回填路径，当前 `ready_for_governance_closure_preview=no`、50 行岗位签核 pending、60 条交接复核 pending、396 条回填路径 pending，发现项 0。`qms:preimport-package --governance-closure-execution-dir` 已可读取该包；完整组合 dry-run 返回 `passed`，但使用模拟人审包执行 apply-rehearsal 时仍因 `governance_closure_execution_signatures_pending`、`governance_closure_execution_handoffs_pending`、`governance_closure_execution_routes_pending` 和 `governance_closure_execution_not_ready_for_preview` 返回 `blocked`。该执行包只帮助分派、签核和回填，不修改治理关闭工作台，不写数据库，不代表人工评审通过、真实培训完成、受控发布或正式写库授权。

治理关闭最小试点包已形成并通过 dry-run：`governance_closure_pilot_pack/` 从治理闭环执行包中按“阻断数量少、任务数量少、顺序靠前”的规则抽取 5 个小批次，形成 5 条试点证据填写页和 5 条签核交接页。`qms:preimport-package --governance-closure-pilot-dir` 已可读取该包；完整组合 dry-run 返回 `passed`，但使用模拟人审包执行 apply-rehearsal 时仍因 `governance_closure_pilot_evidence_pending`、`governance_closure_pilot_handoffs_pending` 和 `governance_closure_pilot_not_ready_for_preview` 返回 `blocked`。该试点包只帮助组织先跑通少量人工闭环，不修改治理关闭工作台，不写数据库，不代表人工评审通过、真实培训完成、受控发布或正式写库授权。

治理关闭试点回填预览包已形成并通过 dry-run：`governance_closure_pilot_return_preview/` 读取 `governance_closure_pilot_pack/` 的 5 条试点证据和 `governance_closure_workbench/` 的源证据/拟关闭行，生成 5 条试点到源工作台映射、10 条拟回填源行预览和 55 条缺字段清单。`qms:preimport-package --governance-closure-pilot-return-dir` 已可读取该包；完整组合 dry-run 返回 `passed`，但使用模拟人审包执行 apply-rehearsal 时仍因 `governance_closure_pilot_return_missing_fields`、`governance_closure_pilot_return_has_blocking_items` 和 `governance_closure_pilot_return_not_ready_for_preview` 返回 `blocked`。该预览包只检查试点结果能否安全回到源工作台，不修改试点包、源工作台、治理总览或数据库。

治理关闭试点源工作台回填补丁预演包已形成并通过 dry-run：`governance_closure_pilot_source_update_rehearsal/` 读取 `governance_closure_pilot_return_preview/` 的 10 条拟回填源行和 `governance_closure_workbench/` 的源表当前值，生成 55 条逐字段补丁预演和 55 条阻断补丁清单。`qms:preimport-package --governance-closure-pilot-source-update-dir` 已可读取该包；完整组合 dry-run 返回 `passed`，但使用模拟人审包执行 apply-rehearsal 时仍因 `governance_closure_pilot_source_update_has_blocked_patches` 和 `governance_closure_pilot_source_update_not_ready_for_source_update` 返回 `blocked`。该预演包只展示未来人工可能回填的源表字段，不修改源工作台、试点回填预览、治理总览或数据库。

治理关闭试点人工执行工作簿已形成并通过 dry-run：`governance_closure_pilot_operator_workbook/` 将 5 条试点主任务、55 条逐字段填写项、5 条签核交接项和 5 张任务卡合并成可人工执行的工作面。`qms:preimport-package --governance-closure-pilot-operator-workbook-dir` 已可读取该包；完整组合 dry-run 返回 `passed`，显示 `pending_workbook_items=5`、`pending_field_items=55`、`pending_handoff_items=5`；使用模拟人审包执行 apply-rehearsal 时会因 `governance_closure_pilot_operator_workbook_items_pending`、`governance_closure_pilot_operator_workbook_fields_pending`、`governance_closure_pilot_operator_workbook_handoffs_pending` 和 `governance_closure_pilot_operator_workbook_not_ready_for_return_preview` 返回 `blocked`。该工作簿只整理人工执行、字段填写和签核交接状态，不修改试点包、源工作台或数据库，不代表真实执行完成。

治理关闭试点人工执行模拟完成包已形成并通过 dry-run：`governance_closure_pilot_operator_completion_simulation/` 将 5 条试点主任务、55 条逐字段填写项和 5 条签核交接项临时标为模拟完成，并逐行写入 `SIMULATED_COMPLETION_NOT_REAL_EXECUTION`。`qms:preimport-package --governance-closure-pilot-operator-completion-simulation-dir` 已可读取该包；完整组合 dry-run 返回 `passed`，显示 `ready_for_pilot_return_preview=yes`、`pending_items=0`、`pending_fields=0`、`pending_handoffs=0`、`marker_rows=65`。使用模拟人审包执行 apply-rehearsal 时，该模拟包本身不再产生人工执行工作簿待办阻断，但真实工作簿、治理总览、闭环执行、回填预览、源工作台补丁等真实待办仍会阻断；正式 `--apply` 会因 `governance_closure_pilot_operator_completion_simulation_not_allowed_for_apply` 返回 `blocked`。该包只能作为命令链路验证材料，不能替代真实人员执行、签核或回填。

治理关闭试点真实执行交回包已形成并通过 dry-run：`governance_closure_pilot_operator_handback/` 用来承接人员真正完成工作簿后的交回结果，要求每个完成项具备真实证据值、执行人、复核人、完成日期和交接状态，不接受 `SIMULATED` 标识。`qms:preimport-package --governance-closure-pilot-operator-handback-dir` 已可读取该包；完整组合 dry-run 返回 `passed`，当前显示 5 条主任务 pending、55 条逐字段交回 pending、5 条签核交接 pending，`ready_for_pilot_return_preview=no`。使用模拟人审包执行 apply-rehearsal 时返回 `blocked`；正式 `--apply` 也会因为真实交回未完成和模拟完成包不得作为真实证据而返回 `blocked`，未写数据库。

治理关闭意见回填预览包已形成并通过 dry-run：`governance_closure_decision_preview/` 读取治理关闭工作台中的 396 条拟关闭意见，当前 `ready_for_governance_readiness_refresh=no`、0 条已接受预览、392 条仍阻断，发现项 0。`qms:preimport-package --governance-closure-preview-dir` 已可读取该包；完整组合 dry-run 返回 `passed`，但使用模拟人审包执行 apply-rehearsal 时仍因 `governance_closure_preview_has_blocking_items` 和 `governance_closure_preview_not_ready_for_refresh` 返回 `blocked`。该预览包不修改源工作台，不写库，不代表人工评审通过、真实培训完成、受控发布或正式写库授权。

治理就绪刷新预览包已形成并通过 dry-run：`governance_readiness_refresh_preview/` 读取治理就绪总览和治理关闭意见回填预览，模拟把已接受关闭项刷新回总闸门。当前关闭意见预览中 0 条被接受，因此刷新后仍有 392 条任务阻断、11 个任务级闸门阻断，`ready_for_lims_apply=no`，发现项 0。`qms:preimport-package --governance-readiness-refresh-dir` 已可读取该包；完整组合 dry-run 返回 `passed`，但使用模拟人审包执行 apply-rehearsal 时仍因 `governance_readiness_refresh_has_blocking_tasks` 和 `governance_readiness_refresh_not_ready_for_apply` 返回 `blocked`。该刷新预览不修改 `governance_readiness_dashboard/`，不写数据库，不代表人工评审通过、真实培训完成、受控发布或正式写库授权。

下一步建议进入“人工评审候选手册 + 核对试填结果 + 确认 05-02 归属 + 确认质量手册修订/换版路径 + 确认机构人员学习实施包 + 确认发布执行记录模板 + 复核第二阶段块级链接并回填预览 + 先用治理关闭最小试点人工执行工作簿补齐 5 个小批次 + 用真实执行交回包收回真实证据、执行人、复核人、日期和交接结果 + 仅把模拟完成包作为复跑命令链路参考 + 用试点回填预览确认真实结果能安全回到源工作台 + 用源工作台补丁预演确认回填字段不会改错 + 再按治理总览、治理关闭工作台、治理闭环执行包、治理关闭意见预览和治理就绪刷新预览关闭阻断项”。本包仍保持候选草案状态，不能直接发布为受控文件。

## 测试完成态演练

本交付包已追加一轮测试完成态演练：

- `115-QMS测试完成态生成报告.md/json`：按用户授权把本交付包内相关 CSV/manifest 标为测试完成态，用于验证链路，不代表生产记录。
- `116-QMS测试完成态真实交回包dry-run验证报告.md/json`：真实交回包测试态校验通过，5 条主任务、55 个字段、5 条签核交接均完成。
- `117-LIMS预导入命令test-completed-apply-rehearsal报告.md/json`：LIMS 全量非写库演练通过，`status=rehearsal_ready`、`findings=0`、`database_write_performed=0`。

注意：测试完成态只证明工程包、治理包和 LIMS 闸门可以形成闭环；不等同于真实人工评审、真实培训、真实受控发布或生产写库授权。
