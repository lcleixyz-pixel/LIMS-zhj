# 甲方金样第9道 · 预导入包构建 + §2.2 manifest 完整性校验

> 对应《接口契约 v1.0 草案》§五 **甲方金样第9道(套装侧):按本契约§二打一个青禾最小 CSV 包并校验 manifest 完整性**。
> 2026-07-09 实测 **ALL GREEN**。本项为 §五 双侧金样的甲方侧,DB-free 可独立复现。

## 这是什么

证明 **甲方套装产物经平台侧 `scripts/qms_lims_preimport_build.py`(契约§2.2 指定的发包构建器)产出的 CSV 包,符合契约§2.2 的 manifest 完整性约束**——即平台 `QmsPreimportPackageService` 四项 DB-free 基础校验(`checkManifestCounts / checkRecordSchemas / checkTraceabilityRows / checkManualBlockRows`)+ `blank_document_code` 全部通过、0 发现项。

与乙方侧 `乙方夹具回归/preimport_golden/`(平台 dry-run 回归)互补:乙方验平台能正确消费,甲方验套装能正确产出。

## 目录

```
甲方金样第9道/
├── stage/                                   # 最小 stage 输入(青禾水质样例)
│   ├── 13-记录模板包-候选清单.md            # 1 行候选记录模板(供构建脚本读 record_templates 表)
│   └── 15-条款程序记录LIMS验证矩阵.md       # 1 行追溯(供构建脚本读 traceability 表)
├── package/                                 # 构建脚本产出(6 CSV + manifest + README)
│   ├── preimport_manifest.json
│   ├── documents_preimport.csv              # 40 行(1 候选手册 + 37 程序引用 + 1 附件 + 1 记录模板文档)
│   ├── structured_documents_preimport.csv
│   ├── record_form_templates_preimport.csv  # 1 行,field_schema_json 含 7 个 REQUIRED_SCHEMA_KEYS
│   ├── traceability_matrix_preimport.csv    # 1 行,human_review_required=yes / relation_confidence=review_required
│   ├── manual_blocks_preimport.csv          # 1 行,link_confidence=review_required
│   └── external_sources_preimport.csv       # 4 行(CNAS/SAMR,构建脚本内置)
└── validate_manifest.py                     # §2.2 manifest 完整性校验器(DB-free,镜像平台四项基础校验)
```

## 复现

```bash
cd 参考
PLAT=__work_lims/LIMS-zhj-minimal-runnable-20260708

# 1) 用构建脚本从青禾最小 stage 打 CSV 包(契约§2.2 指定构建器)
python "$PLAT/jewelry-qms/scripts/qms_lims_preimport_build.py" \
  --stage-dir 甲方金样第9道/stage \
  --lims-root "$PLAT" \
  --output-dir 甲方金样第9道/package

# 2) §2.2 manifest 完整性校验(DB-free)
python 甲方金样第9道/validate_manifest.py --package-dir 甲方金样第9道/package
# 期望末行:ALL GREEN —— manifest 完整性校验通过(0 发现项),exit 0
```

> Windows 控制台中文若乱码,设 `export PYTHONIOENCODING=utf-8 PYTHONUTF8=1` 再跑。

## 实测结果(2026-07-09)

| 包 | 来源 | 发现项 | 结果 |
|---|---|---|---|
| 甲方第9道构建包 | `qms_lims_preimport_build.py` 产出 | 0 | ALL GREEN |
| 乙方正式夹具 `fixture/` | 乙方金样 | 0 | ALL GREEN |
| 乙方 DB-free 冒烟 `smoke_dbfree/fixture/` | 乙方金样 | 0 | ALL GREEN |
| 负向(篡改 counts→999) | 甲方包副本 | 2(`count_mismatch_documents`、`count_mismatch_record_form_templates`) | RED,exit 1 |

校验器非空过:合规包 0 发现项,篡改包准确报出对应 `count_mismatch_*`。

## 覆盖边界(重要)

本第9道覆盖的是 **DB-free 的 §2.2 manifest 完整性校验**——不查库,可独立运行。以下仍需 MySQL,不在本第9道覆盖内:

- **契约转正版硬条件①**:用 v1.21a 青禾产物重跑平台 `php think qms:preimport-package` dry-run 发现项=0。本第9道构建包的 documents 含 37 行 `action=reference_existing_current`(引用 LIMS 2022 现行程序),dry-run 时会经 `existingDocumentRows` 查库反查——**需 MySQL**。
- **契约转正版硬条件②**:乙方正式夹具 `fixture/` 全量 dry-run(1 行实码)——需 MySQL。

即:第9道证明"套装→CSV 包→manifest 校验"链路 DB-free 可跑通且合规;真正的 dry-run 零发现项(①②)仍卡在 MySQL。MySQL 解锁步骤见同批交付。

## 红线

- `package/` 内 documents 的 `reference_existing_current` 行为 LIMS 2022 现行程序目录引用,**不是新建**,dry-run/apply 时查库校验存在性。
- 构建脚本 `external_sources` 4 行为内置 CNAS/SAMR 依据,非青禾实据;正式包须替换为甲方书架实际依据。
- 改 stage 输入或构建脚本逻辑=改金样,须同步重跑校验并在契约§五留变更行。
