# 记录表格与运行证据说明

本文档说明记录表格模板、字段 schema、程序文件记录要求和运行证据之间的关系。它适用于记录表格批量构建、schema 复核、运行记录生成和追溯矩阵验证。

## 1. 设计目标

记录表格不是普通附件，而是质量体系运行证据的结构化入口。

目标包括：

- 从程序文件记录要求和现用表格原件共同构建 schema。
- 每个表格有主归属要素和来源程序文件。
- schema 字段可被人工复核、补充和延期处理。
- 运行填写时保留模板快照，形成可追溯证据。
- 追溯矩阵能说明某个要素由哪些记录表格和运行记录支撑。

## 2. 数据表

### `record_form_templates`

记录表格模板。

| 字段 | 说明 |
|------|------|
| `document_id` | 文件控制中受控原始附件对应的 `documents.id` |
| `element_id` | 主归属体系要素 |
| `procedure_doc_id` | 关联程序文件 |
| `doc_number` | 表格编号 |
| `name` | 表格名称 |
| `module` | 业务模块或用途 |
| `source_file_path` / `source_file_name` | 现用表格来源 |
| `source_file_sha1` | 来源文件哈希 |
| `print_template_key` | 打印模板键 |
| `field_schema` | 字段 schema JSON |
| `review_status` | schema 复核状态 |
| `review_note` | 复核说明 |

### `record_form_instances`

运行填写记录。

| 字段 | 说明 |
|------|------|
| `template_id` | 来源模板 |
| `template_name` / `template_module` / `template_version` | 生成时模板快照 |
| `template_field_schema` | 生成时 schema 快照 |
| `doc_number` | 记录编号 |
| `record_title` | 记录标题 |
| `field_values` | 实际填写值 |
| `status` | `draft`、`generated`、`locked`、`voided` |
| `generated_html_path` / `generated_pdf_path` | 生成输出 |

## 3. schema 构建来源

字段 schema 应来自三类证据：

1. 程序文件中的记录要求块。
2. 现用记录表格原件的字段、签名、日期、判定和备注。
3. 运行模块对实际填写、打印或输出的要求。

不能只按表名或模块名猜字段。缺少证据时应生成智能体建议或标记 `needs_fidelity`，等待人工复核。

## 4. 复核状态

`review_status` 取值：

| 状态 | 说明 |
|------|------|
| `pending` | 待复核 |
| `field_confirmed` | 字段已确认 |
| `needs_fidelity` | 需要提高与原表一致性 |
| `deferred` | 暂缓处理 |
| `completed` | 已完成复核 |

建议复核流程：

```text
待复核
-> 比对程序文件记录要求
-> 比对现用表格原件
-> 确认字段类型、必填性、选项和默认值
-> 生成或调整打印模板
-> 标记 completed
```

## 5. 页面入口

| 页面 | 路径 | 用途 |
|------|------|------|
| 记录表格模板 | `/record_form_template/index` | 表格模板列表 |
| 表格模板详情 | `/record_form_template/view?id=...` | 查看 schema、来源和追溯 |
| schema 复核 | `/record_form_template/review` | 集中复核待处理 schema |
| 来源文件 | `/record_form_template/source?id=...` | 查看来源文件信息 |
| 来源预览 | `/record_form_template/sourcePreview?id=...` | 预览结构化来源 |
| 记录填写 | `/record_form_instance/index` | 运行记录实例 |

## 6. 与程序文件的关系

程序文件中的 `record_requirement` 块应映射到记录表格模板。

映射后可以回答：

- 程序文件要求形成哪些记录？
- 每个记录要求是否已有表格模板承接？
- 表格模板是否有 schema？
- schema 字段是否覆盖记录要求？
- 是否已经形成运行实例？

缺口会体现在策划中心和智能体建议中。

## 7. 与体系要素的关系

每个记录表格模板应有主归属要素 `element_id`。当一个表格支撑多个要素时：

- 主归属写入 `record_form_templates.element_id`。
- 补充关系可通过结构化块级链接或要素追溯关系体现。
- 不建议为了一个表格复制多个模板。

例如：

| 表格 | 主归属要素 | 可能补充支撑 |
|------|------------|--------------|
| 人员培训记录 | 人员 | 管理评审、能力确认 |
| 设备期间核查记录 | 设备 | 方法确认、结果有效性 |
| 内审检查表 | 内部审核 | 纠正措施、管理评审 |

## 8. field_schema 建议结构

当前 schema 以 JSON 文本存放在 `field_schema`。字段建议至少包含：

| 属性 | 说明 |
|------|------|
| `key` | 字段稳定键 |
| `label` | 中文标签 |
| `type` | 字段类型，如 `text`、`date`、`select`、`number`、`person` |
| `required` | 是否必填 |
| `options` | 选项字段的可选值 |
| `default` | 默认值 |
| `note` | 字段说明或来源 |

示例：

```json
[
  {
    "key": "equipment_name",
    "label": "设备名称",
    "type": "text",
    "required": true,
    "note": "来自设备期间核查记录表"
  },
  {
    "key": "check_date",
    "label": "核查日期",
    "type": "date",
    "required": true
  }
]
```

## 9. 运行证据

运行证据来自 `record_form_instances`，而不是仅来自模板。

生成运行记录时应保留：

- 当时模板名称、版本、打印模板键。
- 当时字段 schema 快照。
- 实际填写值。
- HTML 或 PDF 输出路径。
- 记录状态。

后续模板变更不应破坏历史记录解释。

## 10. 智能体建议

记录表格相关建议通常有两类：

- 程序记录要求建议：程序文件要求形成记录，但缺少表格或映射。
- 记录 schema 建议：表格存在，但 schema 字段不足或需要人工复核。

建议状态由人工处理。采纳建议后仍需要通过表格编辑、schema 复核或结构化链接保存正式数据。

## 11. 验证清单

上线或批量构建后抽样检查：

- 表格编号、名称、版本与现用文件一致。
- `element_id` 和 `procedure_doc_id` 不为空，且关系合理。
- `field_schema` 是合法 JSON。
- 字段名称、类型、必填性和签名日期字段与原表一致。
- 程序文件记录要求块能追到表格。
- 表格详情能展示来源、要素和程序文件。
- 运行实例能生成 HTML / PDF。
- 追溯矩阵能统计到记录表格和运行证据。
