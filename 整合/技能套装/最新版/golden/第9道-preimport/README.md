# 第9道金样 · 预导入包构建 + manifest 完整性(套装侧 Gate 9)

> 对应《接口契约 v1.0 正版》§五 甲方金样第9道 + 附录A 待办 **T1(2026-07-09 已完成)**。
> 接入套装 `run_golden_test.py` 为 **Gate 9**,自包含、甲方环境可独立跑通。

## 这是什么

证明甲方侧能按契约§二产出合规预导入 CSV 包并校验 manifest 完整性——即"套装→CSV 包→manifest 校验"链路在甲方环境自洽可跑,不依赖乙方平台树。

## 目录(自包含,乙方构建器+校验器副本随包)

```
第9道-preimport/
├── stage/                                   # 青禾最小 stage 输入
│   ├── 13-记录模板包-候选清单.md            # 1 行候选记录模板
│   └── 15-条款程序记录LIMS验证矩阵.md       # 1 行追溯(主题=设施与环境条件)
├── knowledge/internal/procedures/
│   └── PROCEDURE_FILE_MANIFEST.json         # 最小 2022 程序清单(1 条)
├── qms_lims_preimport_build.py              # 乙方构建器副本(契约§2.2 指定)
├── validate_manifest.py                     # 乙方 manifest 校验器副本(含 G2 签字检查镜像)
└── package/                                 # Gate 9 运行时产出(6 CSV + manifest)
```

> `qms_lims_preimport_build.py` 与 `validate_manifest.py` 为乙方侧副本(源自 `jewelry-qms/scripts/` 与 `参考/甲方金样第9道/`)。乙方侧升级时须同步本副本,否则套装 Gate 9 会与平台行为分叉。

## 运行(由 run_golden_test.py Gate 9 自动执行)

```bash
cd 技能套装/最新版/golden
python run_golden_test.py          # Gate 9 = build + validate,期望 PASS
```

手动复跑:
```bash
B=第9道-preimport
python $B/qms_lims_preimport_build.py --stage-dir $B/stage --lims-root $B --output-dir $B/package
python $B/validate_manifest.py --package-dir $B/package     # 期望 ALL GREEN
```

## 校验器覆盖(镜像平台 dry-run 四项 + G2 签字)

`validate_manifest.py` 镜像 `QmsPreimportPackageService` 的 DB-free 校验:6 CSV 齐 / counts 一致 / record_form_templates 含 7 REQUIRED_SCHEMA_KEYS / traceability review_required 闸 / manual_blocks review_required 闸 / documents.doc_number 非空;**+ G2 semantic_signatures**(契约§2.1):必备主题=traceability 的 manual_topic∪element,缺失→跳过、不全→high。

## 实测(2026-07-09)

`run_golden_test.py` 全量:8/9 passed(1 warn,Gate6 skeleton_check 预期告警),**ALL GREEN**。Gate 9(build+validate)均 PASS。
