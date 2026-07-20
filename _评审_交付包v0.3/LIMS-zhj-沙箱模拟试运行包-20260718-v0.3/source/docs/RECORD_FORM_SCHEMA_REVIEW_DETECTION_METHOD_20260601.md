# 检测方法记录表格 schema 小批量复核清单

本清单用于复核 2026-06-01 写入 `database/schemas/record_form_schemas.json` 的“检测方法的选择与确认程序”小批量 AI 重建结果。清单保留字段复核依据；当前批量构建会再经过来源文件、schema 覆盖、样例校验和打印入口检查，满足门槛后才发布为可填写模板。

## 1. 复核范围

| 项目 | 内容 |
| --- | --- |
| 模块 | XZTC/CX-22-2022 检测方法的选择与确认程序 |
| 注册表 | `jewelry-qms/database/schemas/record_form_schemas.json` |
| 源摘录目录 | `jewelry-qms/runtime/qms_structured/record_form/` |
| 本次候选模板 | `XZTC/BG-22-01`、`XZTC/BG-22-02`、`XZTC/BG-22-03` |
| 编号归并 | `待定-22-01` -> `XZTC/BG-22-03` |
| 当前处理状态建议 | 已通过可填写门槛的模板可进入 `completed` |

关键判断：

- `XZTC/BG-22-01` 和 `XZTC/BG-22-02` 的 AI schema 已明显优于原结构化 Markdown 中的通用启发式 schema，可进入人工字段复核。
- `待定-22-01` 是临时编号，源摘录尾部正式记录编号为 `XZTC/BG-22-03`。结合 CX-22 对标准查新和现行有效版本的要求，应按 `XZTC/BG-22-03 现行有效标准清单` 归并进入候选 schema。
- 本轮不建议全量扩展。后续遇到难判定记录时，应先沿手册、程序、记录清单、导入预览、运行模型查找体系链路位置，再决定归并、台账化或归档。

## 2. 证据索引

| 证据 | 路径 | 复核要点 |
| --- | --- | --- |
| AI 注册表 | `jewelry-qms/database/schemas/record_form_schemas.json` | 本次写入的候选字段 schema |
| BG-22-01 源摘录 | `jewelry-qms/runtime/qms_structured/record_form/XZTC_BG-22-01-A_0.md:73` | 原表字段从“检测方法代号名称”到“技术负责人/日期” |
| BG-22-02 源摘录 | `jewelry-qms/runtime/qms_structured/record_form/XZTC_BG-22-02-A_0.md:57` | 人员、设备、试剂标准、环境条件、结论和签名链 |
| 待定-22-01 源摘录 | `jewelry-qms/runtime/qms_structured/record_form/待定-22-01-A_0.md:51` | 现行有效标准清单明细，源内记录编号显示为 `XZTC/BG-22-03` |
| 导入预览 | `docs/import-preview/record-forms-import-preview.md:202` | 已按源内正式编号归并为 `XZTC/BG-22-03` |
| 跳过清单 | `docs/import-preview/record-forms-import-preview.md:214` | `XZTC/BG-22-03` 被标记为历史记录非模板、跳过 |
| CX-22 职责 | `jewelry-qms/runtime/qms_structured/procedure/XZTC_CX-22-2022-2022.md:253` | 技术负责人负责检测标准方法查新 |
| CX-22 方法选择 | `jewelry-qms/runtime/qms_structured/procedure/XZTC_CX-22-2022-2022.md:291` | 检测方法应确保所用标准为最新有效版本 |
| CX-22 频次 | `jewelry-qms/runtime/qms_structured/procedure/XZTC_CX-22-2022-2022.md:239` | 检测标准每半年查新一次 |

注意：三个源 Markdown 顶部的旧 `字段schema` 多为通用启发式结构，不应作为本轮字段判断的主要证据；应以“源文件Markdown摘录”和注册表 AI 候选结果进行人工比对。

## 3. 总览结论

| 编号 | 名称 | AI 字段数 | 复核结论 | 下一步 |
| --- | --- | ---: | --- | --- |
| `XZTC/BG-22-01` | 非标准方法确认评审表 | 20 | 可进入人工复核 | 确认必填性、签名日期字段和打印版式 |
| `XZTC/BG-22-02` | 标准方法确认记录 | 27 | 可进入人工复核 | 确认 checkbox/select 表达方式和签名链 |
| `XZTC/BG-22-03` | 现行有效标准清单 | 3 | 已按正式编号归并并补齐核查日期/核查人 | 确认与外部依据查新台账的衔接方式 |

已先行加固的候选规则：

- `XZTC/BG-22-01`：检测方法代号名称、评审人签名/日期、技术负责人签名/日期设为必填；两个日期 label 已改为“评审人日期”“技术负责人日期”。
- `XZTC/BG-22-02`：标准方法名称/代号、确认结论、确认人/复核者/技术负责人签名及日期设为必填。
- `XZTC/BG-22-03`：清单核查日期、填写/核查人、现行有效标准明细已纳入 schema；清单主表和序号、标准名称、标准原编号设为必填，备注保留选填。

## 4. `XZTC/BG-22-01` 非标准方法确认评审表

### 源字段覆盖

源摘录可识别字段包括：

- 检测方法代号名称
- 方案实施负责人
- 检测室或领域
- 评审依据
- 评审时间
- 检测方法培训情况：培训日期、培训方式、参培人员、培训效果、其他
- 参考的技术资料
- 采用的确认技术
- 采用仪器设备名称及编号
- 实际操作验证
- 评审意见、评审人、日期
- 确认意见、技术负责人、日期

AI 候选 schema 与上述字段基本一一对应。

### 候选字段清单

| # | key | label | type | 人工复核点 |
| ---: | --- | --- | --- | --- |
| 1 | `method_code_name` | 检测方法代号名称 | `text` | 可接受 |
| 2 | `project_leader` | 方案实施负责人 | `person` | 确认是否必须从员工表选择 |
| 3 | `testing_room_or_field` | 检测室或领域 | `text` | 可接受 |
| 4 | `review_basis` | 评审依据 | `textarea` | 可接受 |
| 5 | `review_date` | 评审时间 | `date` | 可接受 |
| 6 | `training_date` | 培训日期 | `date` | 可接受 |
| 7 | `training_method` | 培训方式 | `text` | 可接受 |
| 8 | `training_participants` | 参培人员 | `textarea` | 如需逐人签名，后续可改为明细表 |
| 9 | `training_effect` | 培训效果 | `textarea` | 可接受 |
| 10 | `training_other` | 其他 | `textarea` | 可接受 |
| 11 | `reference_materials` | 参考的技术资料 | `textarea` | 可接受 |
| 12 | `confirmation_technique` | 采用的确认技术 | `textarea` | 可接受 |
| 13 | `instrument_equipment` | 采用仪器设备名称及编号 | `textarea` | 如需设备台账关联，后续可增强 |
| 14 | `practical_operation_verification` | 实际操作验证 | `textarea` | 可接受 |
| 15 | `review_comments` | 评审意见 | `textarea` | 可接受 |
| 16 | `reviewer_signature` | 评审人 | `signature` | 可接受 |
| 17 | `reviewer_date` | 日期 | `date` | 需在 UI 中显示上下文，避免两个“日期”混淆 |
| 18 | `confirm_comments` | 确认意见 | `textarea` | 可接受 |
| 19 | `technical_director_signature` | 技术负责人 | `signature` | 可接受 |
| 20 | `technical_director_date` | 日期 | `date` | 需在 UI 中显示上下文，避免两个“日期”混淆 |

### 建议处理

- 当前 schema 已通过可填写门槛，可随批量构建进入 `completed`；人工复核仍可继续优化必填性和版式。
- 已先行将检测方法代号名称、评审人签名/日期、技术负责人签名/日期设为必填；人工复核时确认是否还需要把方案实施负责人、评审依据、评审时间等字段纳入必填。
- 打印模板或表单渲染时，两个“日期”字段应结合签名区上下文显示，不能只显示相同 label。

## 5. `XZTC/BG-22-02` 标准方法确认记录

### 源字段覆盖

源摘录可识别结构包括：

- 标准方法名称/代号（含年号）
- 确认组别/确认人
- 标准方法确认内容：人员、设备、试剂标准、环境条件
- 人员确认项：对标准原理的理解、是否进行过操作、熟悉程度
- 设备确认项：标准要求的主要设备/名称、设备能否满足要求
- 试剂标准确认项：试剂、标准品、满足性
- 环境条件确认项：环境是否满足、有无特殊要求、特殊要求描述
- 备注、确认结论、确认意见
- 确认人、复核者、专业领域技术负责人签名和日期

AI 候选 schema 覆盖完整，且 select 选项与源摘录的勾选项基本一致。

### 候选字段清单

| # | key | label | type | 人工复核点 |
| ---: | --- | --- | --- | --- |
| 1 | `method_name` | 标准方法名称/代号（含年号） | `text` | 可接受 |
| 2 | `confirmation_group_or_person` | 确认组别/确认人 | `text` | 可接受 |
| 3 | `confirm_personnel` | 人员 | `checkbox` | 当前为单独布尔勾选，可接受 |
| 4 | `confirm_equipment` | 设备 | `checkbox` | 当前为单独布尔勾选，可接受 |
| 5 | `confirm_reagent_standard` | 试剂标准 | `checkbox` | 当前为单独布尔勾选，可接受 |
| 6 | `confirm_environment` | 环境条件 | `checkbox` | 当前为单独布尔勾选，可接受 |
| 7 | `understanding_of_principle` | 对标准原理的理解 | `select` | 选项：理解、基本理解、不理解 |
| 8 | `operation_experience` | 对标准是否进行过操作 | `select` | 选项：已操作、没有操作 |
| 9 | `familiarity_with_operation` | 对标准操作过程的熟悉程度 | `select` | 选项：熟悉、基本熟悉、不熟悉 |
| 10 | `equipment_name` | 标准要求的主要设备/名称 | `textarea` | 可接受 |
| 11 | `equipment_satisfaction` | 设备能否满足标准的要求 | `select` | 选项：满足、基本满足、不满足 |
| 12 | `reagent_availability` | 是否有标准所要求的试剂 | `select` | 可接受 |
| 13 | `standard_availability` | 是否有标准所要求的标准品 | `select` | 包含“不需要标准品”，可接受 |
| 14 | `reagent_standard_satisfaction` | 试剂标准能否满足标准的要求 | `select` | 可接受 |
| 15 | `env_satisfaction` | 实验室环境条件能否满足标准方法的要求 | `select` | 可接受 |
| 16 | `env_special_requirement` | 标准方法对实验室环境有无特殊的要求 | `select` | 可接受 |
| 17 | `env_special_requirement_desc` | 如果对实验室环境有无特殊的要求，请描述 | `textarea` | 可接受 |
| 18 | `remarks` | 备注（其他需要说明与补充的内容） | `textarea` | 可接受 |
| 19 | `confirmation_conclusion` | 标准方法的确认结论 | `select` | 可接受 |
| 20 | `confirmation_opinion` | 标准方法的确认意见 | `textarea` | 可接受 |
| 21 | `confirmer_signature` | 确认人签名 | `signature` | 可接受 |
| 22 | `confirmer_date` | 确认人日期 | `date` | 可接受 |
| 23 | `reviewer_signature` | 复核者签名 | `signature` | 可接受 |
| 24 | `reviewer_date` | 复核者日期 | `date` | 可接受 |
| 25 | `tech_opinion` | 各专业领域技术负责人意见 | `textarea` | 可接受 |
| 26 | `tech_signature` | 技术负责人签名 | `signature` | 可接受 |
| 27 | `tech_date` | 技术负责人日期 | `date` | 可接受 |

### 建议处理

- 当前 schema 已通过可填写门槛，可随批量构建进入 `completed`；人工复核仍可继续优化 checkbox/select 表达和版式。
- 已先行将标准方法名称/代号、确认结论、确认人/复核者/技术负责人签名及日期设为必填；人工复核时确认确认组别/确认人和关键选择项是否也应必填。
- 人工复核时确认四个顶部 checkbox 是否需要保持为独立字段，还是改成一个多选字段。当前系统没有多选字段类型，因此独立 checkbox 更贴合现有渲染能力。
- 确认 `reagent_availability` 和 `standard_availability` 是否应该合并为同一组“试剂及标准品”，当前拆分更利于结构化检索。

## 6. `XZTC/BG-22-03` 现行有效标准清单

### 源字段覆盖

AI 候选 schema 已补齐清单核查日期、填写/核查人，并将标准明细识别为一个 `repeatable_table`：

| column key | label | type |
| --- | --- | --- |
| `seq` | 序号 | `number` |
| `standard_name` | 标准名称 | `text` |
| `original_code` | 标准原编号 | `text` |
| `remark` | 备注 | `text` |

字段抽取本身合理。`待定-22-01` 不应作为正式编号使用，应按源摘录中的 `记录编号：XZTC/BG-22-03` 归并。

### 体系链路

- 手册 7.2 要求检测人员使用的标准、规范、作业指导书均为现行最新有效版本。
- CX-22 职责明确技术负责人负责检测标准方法的查新。
- CX-22 方法选择要求采用适用检测方法，并确保所用标准为最新有效版本。
- CX-22 规定检测标准每半年查新一次。
- CX-22 记录清单包含《现行有效标准清单》 `XZTC/BG-22-03`。

这说明该表不是可忽略附件，而是在用检测标准有效性维护的运行记录。当前 registry 已移除 `待定-22-01` 和 `待定-22-01::待定-22-01-A_0`，并恢复为 `XZTC/BG-22-03` 和 `XZTC/BG-22-03::待定-22-01-A_0`。

### 建议处理

- 按 `XZTC/BG-22-03` 保留 AI 候选 schema，当前已通过可填写门槛，可随批量构建进入 `completed`。
- 已先行将清单主表和序号、标准名称、标准原编号设为必填，备注保留选填。
- 人工复核时确认它作为记录表格模板、外部依据台账视图，还是二者的衔接表。
- 与 `qms_sources` 的查新字段联动：清单回答“有哪些在用检测标准”，`qms_sources` 回答“每项标准最近何时查新、结论、证据和下次查新”。

## 7. 扩大执行前的门槛

继续扩大到完整“检测方法”模块前，建议至少完成以下检查：

| 门槛 | 通过标准 |
| --- | --- |
| 身份一致性 | 先沿体系链路查找位置；临时编号可归并到源内正式记录编号 |
| 类型一致性 | `select` 必须有 options；checkbox 仅用于布尔勾选 |
| 签名链 | 每个签名和日期都保留独立字段，label 不足时用 key 或 note 补上下文 |
| 必填性 | 不把 AI 的 `required=false` 直接视为最终规则 |
| 状态流转 | AI 写入后需通过重构准备、字段覆盖、样例校验和打印入口检查；未通过者保持候选或草稿，通过者可进入 `completed` |
| 清单类文件 | 先判断模板、台账或二者衔接，再决定运行入口 |

## 8. 建议下一步

1. 继续人工确认 `XZTC/BG-22-01` 和 `XZTC/BG-22-02` 的字段、必填性和打印版式。
2. 人工确认 `XZTC/BG-22-03` 与外部依据查新台账的职责边界，尤其是清单维护频次、查新证据字段和责任人。
3. 已在重建命令中增加“源摘录记录编号归并/冲突”检测：临时编号归并到源内正式编号；非临时编号冲突仍只输出冲突报告，不写入正式 registry。
4. 对已发布的源文件重构版继续逐表做版式高保真优化，不影响当前填写入口可用性。
