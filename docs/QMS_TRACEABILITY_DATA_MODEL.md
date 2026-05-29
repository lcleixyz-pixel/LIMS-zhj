# 体系策划数据模型与追溯关系说明

本文档说明体系策划中心的核心数据表、字段归属和追溯关系。它用于数据库维护、二次开发、数据修复和智能体协作。

## 1. 总体模型

```text
qms_sources
  -> qms_clauses
      -> qms_clause_texts
      -> qms_element_clause_links
          -> qms_elements
              -> qms_manual_sections
              -> qms_element_documents -> documents
              -> record_form_templates
              -> qms_business_module_elements -> qms_business_modules
              -> qms_element_responsibilities -> qms_positions

qms_document_assets
  -> qms_structured_documents
      -> qms_document_blocks
          -> qms_document_block_links
              -> qms_elements / qms_clauses / qms_manual_sections
              -> documents / record_form_templates / qms_positions / qms_business_modules
```

正式追溯链路建议按以下方向理解：

```text
外部条款 -> 无编号要素 -> 手册章节 / 程序文件 -> 记录表格 / 运行模块 -> 岗位职责 / 运行证据
```

结构化文件提供更细粒度的块级证据，可以反向说明某个条款或要素由哪些文件段落承接。

## 2. 外部依据表

### `qms_sources`

表示外部依据主记录。

| 字段 | 说明 |
|------|------|
| `source_code` | 依据编号或规范化代码，例如 `GBT_27025`、`CNAS_CL01_A015` |
| `name` | 依据名称 |
| `source_type` | 来源类型，默认 `external_standard` |
| `version` | 版本号 |
| `effective_date` | 生效日期 |
| `attachment_file_path` / `attachment_file_name` | 上传或归档文件信息 |
| `freshness_checked_at` | 最近查新日期 |
| `freshness_result` | 查新结论 |
| `freshness_evidence` | 查新证据来源 |
| `next_freshness_due` | 下次查新日期 |
| `freshness_status` | `unknown`、`current`、`due`、`obsolete` |
| `status` | `draft`、`published`、`obsolete` |

使用原则：

- 外部依据应先归档，再抽取条款。
- 查新字段必须记录证据来源，不只写结论。
- 作废依据使用 `status=obsolete` 或 `freshness_status=obsolete`，不物理删除。

### `qms_clauses`

表示正式条款。

| 字段 | 说明 |
|------|------|
| `source_id` | 所属外部依据 |
| `parent_id` | 上级条款 |
| `clause_number` | 条款编号 |
| `title` | 条款标题 |
| `level` | 条款层级 |
| `page_number` / `locator` | 原文定位 |
| `applicability` | 适用性 |
| `review_status` | 条款复核状态 |
| `summary` | 条款摘要 |

条款编号只属于 `qms_clauses.clause_number`。不要将 `6.2`、`7.8` 等编号写入要素名称或要素 key。

### `qms_clause_texts`

保存条款原文。

| 字段 | 说明 |
|------|------|
| `clause_id` | 对应正式条款 |
| `original_text` | 条款原文 |
| `text_hash` | 原文哈希 |
| `extraction_method` | 抽取方式 |
| `review_status` | 文本复核状态 |

如果后续重抽取或人工修订条款原文，应保留来源、抽取方式和复核记录。

## 3. 无编号要素表

### `qms_elements`

表示体系概念节点。

| 字段 | 说明 |
|------|------|
| `key` | 不可见稳定键，用于系统关联 |
| `name` | 用户界面显示的中文名称 |
| `parent_id` | 上级要素 |
| `element_type` | `management` 或 `technical` |
| `applicability` | 适用性 |
| `owner_position_id` | 主责岗位 |
| `source_basis` | 初始化或设置来源说明 |
| `summary` | 要素摘要 |
| `status` | `draft`、`effective`、`under_review` |
| `sort_order` | 本地排序 |

使用原则：

- 用户界面显示 `name`，不显示 `key`。
- 要素可以独立改名、合并、拆分或新增。
- 要素改名不应影响追溯关系，因为关联使用 `id` 和 `key`。
- 要素不反向控制条款库，条款库仍以外部依据为准。

### `qms_element_clause_links`

表示要素与条款的映射。

| 字段 | 说明 |
|------|------|
| `element_id` | 体系要素 |
| `clause_id` | 外部条款 |
| `mapping_type` | `equivalent`、`partial`、`supplement`、`reference` |
| `is_primary` | 主 27025 条款，用于默认排序 |
| `note` | 映射说明 |

一个要素可以关联多个条款，一个条款原则上可以被多个要素引用，但应避免无证据的泛化映射。

## 4. 手册、程序、岗位和模块关系

### `qms_manual_sections`

表示质量手册章节。

| 字段 | 说明 |
|------|------|
| `document_id` | 所属手册文件 |
| `element_id` | 关联体系要素 |
| `parent_id` | 上级章节 |
| `section_number` | 手册章节编号 |
| `title` | 手册章节标题 |
| `level` | 章节层级 |
| `status` | `draft`、`effective`、`obsolete` |

手册章节编号只属于手册章节，不属于要素。

### `qms_element_documents`

表示要素与受控文件 `documents` 的关联。

| 字段 | 说明 |
|------|------|
| `element_id` | 体系要素 |
| `document_id` | 文件控制中的受控文件 |
| `relation_type` | `primary` 或 `reference` |
| `note` | 关系说明 |

程序文件通常通过此表承接要素要求。

### `qms_element_responsibilities`

表示要素与岗位职责的关系。

| 字段 | 说明 |
|------|------|
| `element_id` | 体系要素 |
| `position_id` | 岗位 |
| `responsibility_type` | `decision_owner`、`organizer`、`participant` |
| `note` | 职责说明 |

岗位来自 `qms_positions`，不是系统登录用户。岗位职责应先按体系文件和组织职责建模，再映射到具体人员。

### `qms_business_modules` 与 `qms_business_module_elements`

`qms_business_modules` 表示系统运行模块，例如记录表格模板、记录填写记录、设备、培训、内审、CAPA 等。`qms_business_module_elements` 表示模块与要素的主归属或补充关系。

| 字段 | 说明 |
|------|------|
| `qms_business_modules.code` | 模块代码 |
| `qms_business_modules.name` | 模块名称 |
| `controller_name` | 控制器名称 |
| `primary_element_id` | 主归属要素 |
| `url` | 模块入口 |
| `relation_type` | `primary` 或 `supporting` |

## 5. 文件结构化表

### `qms_document_assets`

表示原始文件资产和归档信息。

| 字段 | 说明 |
|------|------|
| `source_kind` | `external_basis`、`quality_manual`、`procedure`、`work_instruction`、`record_form`、`reference_file` |
| `document_id` | 受控文件 ID |
| `source_id` | 外部依据 ID |
| `record_form_template_id` | 记录表格模板 ID |
| `original_name` / `original_path` | 原始文件信息 |
| `normalized_name` | 规范化文件名 |
| `archived_path` | 归档路径 |
| `file_sha256` | 文件哈希 |
| `markdown_path` | 抽取或结构化后的 Markdown |
| `review_status` | `draft`、`structured`、`published`、`obsolete` |

### `qms_structured_documents`

表示结构化文件主记录。

| 字段 | 说明 |
|------|------|
| `source_asset_id` | 来源资产 |
| `document_id` | 受控文件 |
| `document_role` | 文件角色 |
| `doc_number` | 文件编号 |
| `title` | 文件标题 |
| `version` | 文件版本 |
| `markdown_path` | 当前 Markdown |
| `rendered_file_path` | 渲染输出 |
| `render_status` | `not_rendered`、`rendered`、`archived` |
| `status` | `draft`、`structured`、`published`、`obsolete` |

### `qms_document_blocks`

表示结构化文件内容块。

| 字段 | 说明 |
|------|------|
| `structured_document_id` | 所属结构化文件 |
| `parent_id` | 上级块 |
| `stable_key` | 块稳定键 |
| `section_number` | 段落或章节编号 |
| `title` | 块标题 |
| `block_type` | `section`、`purpose`、`scope`、`responsibility`、`process_step`、`control_requirement`、`record_requirement`、`form_schema`、`clause_trace`、`text` |
| `markdown` | 块内容 |
| `source_locator` | 来源定位 |
| `status` | `draft`、`effective`、`obsolete` |

块是未来精细追溯和局部修改的基本单位。

### `qms_document_block_links`

表示内容块到追溯对象的关系。

| 字段 | 说明 |
|------|------|
| `block_id` | 内容块 |
| `element_id` | 体系要素 |
| `clause_id` | 外部条款 |
| `manual_section_id` | 手册章节 |
| `procedure_document_id` | 程序文件 |
| `record_form_template_id` | 记录表格模板 |
| `position_id` | 岗位 |
| `business_module_id` | 运行模块 |
| `relation_type` | `basis`、`implements`、`mentions`、`responsible`、`requires_record`、`renders_to`、`supporting` |
| `confidence` | `high`、`medium`、`low`、`review_required` |
| `note` | 关系说明或证据 |

## 6. 记录表格与运行证据

### `record_form_templates`

表示记录表格模板和 schema。

| 字段 | 说明 |
|------|------|
| `document_id` | 受控原始附件对应 `documents.id` |
| `element_id` | 主归属体系要素 |
| `procedure_doc_id` | 关联程序文件 |
| `doc_number` | 表格编号 |
| `name` | 表格名称 |
| `module` | 业务模块说明 |
| `source_file_path` / `source_file_name` | 来源表格文件 |
| `field_schema` | 字段 schema JSON |
| `review_status` | `pending`、`field_confirmed`、`needs_fidelity`、`deferred`、`completed` |

### `record_form_instances`

表示运行中填写形成的记录证据。

| 字段 | 说明 |
|------|------|
| `template_id` | 来源模板 |
| `template_field_schema` | 生成时的 schema 快照 |
| `doc_number` | 记录编号 |
| `record_title` | 记录标题 |
| `field_values` | 字段值 |
| `status` | `draft`、`generated`、`locked`、`voided` |
| `generated_html_path` / `generated_pdf_path` | 输出路径 |

运行证据不应只依赖当前模板，应保留生成时快照，以支持历史追溯。

## 7. 智能体建议表

### `qms_agent_suggestions`

表示智能体或系统生成的建议。

| 字段 | 说明 |
|------|------|
| `element_id` | 关联要素，可为空 |
| `suggestion_type` | `gap`、`mapping`、`document`、`record`、`module`、`responsibility` |
| `title` | 建议标题 |
| `content` | 建议内容 |
| `evidence` | 证据 |
| `status` | `open`、`accepted`、`rejected` |
| `review_note` | 人工复核说明 |

建议表是缓冲层，不是正式体系数据。

## 8. 不再使用的旧模型

本轮重构移除或替换了旧候选流：

- `qms_import_batches`
- `qms_import_candidates`
- `qms_trace_links`
- `qms_requirement_elements`
- 旧 `qms_document_sections`
- 旧 `qms_responsibility_matrix`

开发新功能时不要重新依赖这些旧表和旧路由。
