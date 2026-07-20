# 乙方侧 预导入金样 dry-run 回归

对应《接口契约 v0.9 草案》§五:**乙方对应固定夹具包 dry-run 回归【平台侧建】**。
形态镜像套装侧 `技能套装/golden/run_golden_test.py`——固定夹具喂入 dry-run,断言发现项=0 且与钉定基线一致,ALL GREEN 才过。

## 这是什么

一套**固定的、版本化的预导入包**(fixture/),作为平台侧"金样"。每次平台侧改接口或改 `QmsPreimportPackageService` 校验逻辑后,跑 `run_preimport_golden.py`,确认同一夹具仍 dry-run 零发现项、输出与基线一致。

这是契约§五"双侧金样"的乙方侧(甲方侧=套装金样第9道)。任一侧改接口不升契约 → 对应金样应飘红。

## 目录

```
preimport_golden/
├── fixture/                              # 正式金样夹具(1 行实码,需 MySQL 跑全量)
│   ├── preimport_manifest.json
│   ├── documents_preimport.csv
│   ├── structured_documents_preimport.csv
│   ├── record_form_templates_preimport.csv   # field_schema_json 含 7 个必备 key
│   ├── traceability_matrix_preimport.csv     # human_review_required=yes / relation_confidence=review_required
│   ├── manual_blocks_preimport.csv           # link_confidence=review_required
│   └── external_sources_preimport.csv
├── smoke_dbfree/fixture/                 # DB-free 冒烟夹具(documents/external_sources 空、record 模板 doc_number 留空 → existingRows 短路不查库)
├── expected/
│   ├── expected_findings.json            # 钉定基线:期望发现项 id 集合(默认空=0 发现项)
│   └── _last_dryrun_report.json          # 上次 dry-run 的 JSON 报告(运行产物,可删)
└── run_preimport_golden.py               # 回归运行器(支持 --fixture-dir 切换夹具)
```

## 实跑状态(2026-07-09)

- ✅ `composer install` 已完成(`vendor/` 就位),`php think` 可启动,`qms:preimport-package` 命令已注册。
- ✅ **DB-free 冒烟已实测 ALL GREEN**:`python run_preimport_golden.py --fixture-dir smoke_dbfree/fixture` → `status=passed`、`findings=0`、退出 0。证明运行器 + 平台基础校验(`checkManifestCounts/checkRecordSchemas/checkTraceabilityRows/checkManualBlockRows`)端到端可跑且对正确夹具返回 0 发现项。
- ⏳ **正式金样 `fixture/`(1 行实码)的全量 run 仍需 MySQL**:其 `existingDocumentRows/existingRecordTemplateRows/existingSourceRows` 会查库。CSV 层校验已用 Python 复刻四项 check 模拟为 0 发现项;在空库(表已建、无重复码)下查库返回空,目标基线同为 0 发现项。

## 运行前置

dry-run 命令 `php think qms:preimport-package` 需要:
1. **composer 依赖**(`vendor/autoload.php`)——`composer install`;
2. **数据库可连**(正式 `fixture/`)——`buildSummary` 调 `existingRows` 查库;空库下返回空、不产生发现项。
   - 免库冒烟(`smoke_dbfree/fixture`)不需要 DB,但覆盖面较窄(不含 DB 去重检查)。

## 用法

```bash
cd jewelry-qms
composer install                       # 首次

# 免库冒烟(无需 MySQL,验证运行器+基础校验)
python tests/preimport_golden/run_preimport_golden.py --fixture-dir tests/preimport_golden/smoke_dbfree/fixture

# 正式金样(需 MySQL 可连、库已建)
python tests/preimport_golden/run_preimport_golden.py
# 或显式:--lims-root /path/to/jewelry-qms --fixture-dir .../fixture

# 首次跑通后把实际发现项钉定为基线(应保持空集)
python tests/preimport_golden/run_preimport_golden.py --pin
```

预期末行:`ALL GREEN — 乙方侧预导入金样 dry-run 回归通过`。

## 夹具设计依据(对应 QmsPreimportPackageService 基础校验)

| 校验 | 出处 | 夹具如何满足 |
|---|---|---|
| `missing_manifest` / `missing_<file>` | buildSummary | 7 文件齐 |
| `count_mismatch_*` | checkManifestCounts | manifest.counts 与各 CSV 行数一致 |
| `invalid_record_schema` / `record_schema_missing_required_keys` | checkRecordSchemas | field_schema_json 为 JSON 数组且含 7 必备 key |
| `traceability_missing_human_gate` / `traceability_confidence_not_review_required` | checkTraceabilityRows | human_review_required=yes、relation_confidence=review_required |
| `manual_block_confidence_not_review_required` | checkManualBlockRows | link_confidence=review_required |
| `blank_document_code` | buildSummary | documents.doc_number 非空(正式夹具) |
| `missing_reference_current_documents` | buildSummary | action=create(不触发 DB 既有文件反查) |

`smoke_dbfree` 的差异:documents/external_sources 为 0 行、record 模板 doc_number 留空,使 `rowsByCode` 因 codes 为空而短路(`existingRows` 不查库);代价是不覆盖 DB 去重检查,仅作机制冒烟。

## 红线

- 夹具内容为**最小虚构示例**,仅供 dry-run 回归,**不得**作为正式 `--apply` 包。
- 模拟完成不得冒充真实执行(与平台 `SIMULATED_COMPLETION` 阻断同源)。
- 改 fixture 内容=改金样,须同步更新 `expected/expected_findings.json` 并在契约留变更行;改平台校验导致飘红=接口变更,须先升契约(契约§五)。
