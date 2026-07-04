# QMS Knowledge Library

本目录是实验室 QMS 专家智能体的 Git 受控知识层，供桌面技能与 jewelry-qms 内嵌 Copilot 共用。

## 目录分工

| 目录 | 用途 | 命名规则 |
|---|---|---|
| `standards/` | ISO/IEC 17025、CNAS、CMA、珠宝检测领域标准要点卡片 | `standards/<basis>/<clause>.md`，例如 `standards/17025/7.2.1.md` |
| `internal/` | 本所质量手册、程序文件、作业指导书的结构化导出 | 由系统命令生成，程序文件进入 `internal/procedures/`，手册章节进入 `internal/manual/` |
| `cases/` | 评审案例、不符合项、整改经验与技能验证记录 | `cases/<topic>/<yyyy-mm-dd>-<slug>.md` |

## Frontmatter 字段

标准卡片使用以下字段：

```yaml
---
id: 17025-7.2.1
clause: "7.2.1"
title: 方法选择、验证和确认
type: standard
status: draft
shall_level: shall
sources:
  - basis: ISO/IEC 17025:2017
    locator: "7.2.1"
    quote_status: paraphrase
verification: 待核
---
```

内部文件卡片使用以下字段：

```yaml
---
id: XZTC-CX-08-2022
doc_number: XZTC/CX-08-2022
title: 文件控制程序
type: internal_procedure
status: generated
source_path: 现用文件/程序文件/程序文件2022/08-2022文件控制程序.docx
clause_refs: []
generated_from: qms_structured_documents
manual_edit: false
---
```

案例卡片使用以下字段：

```yaml
---
id: case-2026-01-example
title: 案例标题
type: case
status: draft
clause_refs: []
sources:
  - basis: internal_experience
    locator: 待核
verification: 待核
---
```

## 约束

- 不整篇收录 ISO、CNAS、CMA、GB/T 等受版权保护标准原文，只保存条款号、要点复述、合规解读和证据清单。
- 无知识卡片支持的条款引用必须标注 `待核`。
- `knowledge/internal/` 是结构化库的单向导出层，不作为人工维护源；修订应发生在结构化库或解析器层，再重新导出。
- `INDEX.md` 是导航索引，人工维护目录规则，后续可由脚本重建条目。
