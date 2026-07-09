#!/usr/bin/env python3
"""甲方金样第9道 · 预导入包 §2.2 manifest 完整性校验器(DB-free)。

镜像平台 `QmsPreimportPackageService` 的四项 DB-free 基础校验:
  checkManifestCounts / checkRecordSchemas / checkTraceabilityRows / checkManualBlockRows
+ documents.doc_number 非空(blank_document_code)。不查库,可独立运行。

对应《接口契约 v1.0 草案》§2.2 的导入强校验项。退出码 0=ALL GREEN(0 发现项),1=有发现项。
"""

from __future__ import annotations

import argparse
import csv
import json
import sys
from pathlib import Path

REQUIRED_FILES = [
    "documents_preimport.csv",
    "structured_documents_preimport.csv",
    "record_form_templates_preimport.csv",
    "traceability_matrix_preimport.csv",
    "manual_blocks_preimport.csv",
    "external_sources_preimport.csv",
]

# 契约§2.2 + 平台 QmsPreimportPackageService::REQUIRED_SCHEMA_KEYS
REQUIRED_SCHEMA_KEYS = [
    "record_number",
    "record_name",
    "applicable_clause",
    "related_procedure",
    "responsible_position",
    "trigger_time",
    "correction_rule",
]

COUNT_KEYS = {
    "documents_preimport.csv": "documents",
    "structured_documents_preimport.csv": "structured_documents",
    "record_form_templates_preimport.csv": "record_form_templates",
    "traceability_matrix_preimport.csv": "traceability_rows",
    "manual_blocks_preimport.csv": "manual_blocks",
    "external_sources_preimport.csv": "external_sources",
}


def read_csv_rows(path: Path) -> list[dict[str, str]]:
    with path.open(encoding="utf-8-sig", newline="") as handle:
        return list(csv.DictReader(handle))


def validate(pkg: Path) -> list[dict[str, object]]:
    findings: list[dict[str, object]] = []

    # 1. 必备文件齐
    manifest_path = pkg / "preimport_manifest.json"
    if not manifest_path.exists():
        findings.append({"id": "missing_manifest", "severity": "high",
                         "detail": f"{manifest_path} 不存在"})
        return findings
    for name in REQUIRED_FILES:
        if not (pkg / name).exists():
            findings.append({"id": f"missing_{name}", "severity": "high",
                             "detail": f"缺必备 CSV:{name}"})

    manifest = json.loads(manifest_path.read_text(encoding="utf-8"))
    counts = manifest.get("counts", {})

    # 2. counts 与各 CSV 行数一致(checkManifestCounts)
    for csv_name, count_key in COUNT_KEYS.items():
        path = pkg / csv_name
        if not path.exists():
            continue
        actual = len(read_csv_rows(path))
        declared = counts.get(count_key)
        if declared is None:
            findings.append({"id": f"count_missing_{count_key}", "severity": "medium",
                             "detail": f"manifest.counts 缺键 {count_key}"})
        elif actual != declared:
            findings.append({"id": f"count_mismatch_{count_key}", "severity": "high",
                             "detail": f"{csv_name}: 实际 {actual} 行 ≠ manifest 声明 {declared}"})

    # 3. documents.doc_number 非空(blank_document_code)
    docs_path = pkg / "documents_preimport.csv"
    if docs_path.exists():
        for i, row in enumerate(read_csv_rows(docs_path), start=2):  # 行号从表头下一行起
            if not (row.get("doc_number") or "").strip():
                findings.append({"id": "blank_document_code", "severity": "high",
                                 "detail": f"documents_preimport.csv 第{i}行 doc_number 为空"})

    # 4. record_form_templates: field_schema_json 合法 + 7 REQUIRED_SCHEMA_KEYS(checkRecordSchemas)
    rft_path = pkg / "record_form_templates_preimport.csv"
    if rft_path.exists():
        for i, row in enumerate(read_csv_rows(rft_path), start=2):
            raw = (row.get("field_schema_json") or "").strip()
            try:
                schema = json.loads(raw)
            except json.JSONDecodeError as exc:
                findings.append({"id": "invalid_record_schema", "severity": "high",
                                 "detail": f"record_form_templates 第{i}行 field_schema_json 非合法 JSON:{exc}"})
                continue
            if not isinstance(schema, list) or not schema:
                findings.append({"id": "invalid_record_schema", "severity": "high",
                                 "detail": f"record_form_templates 第{i}行 field_schema_json 非非空数组"})
                continue
            keys = {item.get("key") for item in schema if isinstance(item, dict)}
            missing = [k for k in REQUIRED_SCHEMA_KEYS if k not in keys]
            if missing:
                findings.append({"id": "record_schema_missing_required_keys", "severity": "high",
                                 "detail": f"record_form_templates 第{i}行 缺必备 key:{missing}"})

    # 5. traceability: human_review_required=yes + relation_confidence=review_required(checkTraceabilityRows)
    tr_path = pkg / "traceability_matrix_preimport.csv"
    if tr_path.exists():
        for i, row in enumerate(read_csv_rows(tr_path), start=2):
            if (row.get("human_review_required") or "").strip().lower() != "yes":
                findings.append({"id": "traceability_missing_human_gate", "severity": "high",
                                 "detail": f"traceability_matrix 第{i}行 human_review_required≠yes"})
            if (row.get("relation_confidence") or "").strip() != "review_required":
                findings.append({"id": "traceability_confidence_not_review_required", "severity": "high",
                                 "detail": f"traceability_matrix 第{i}行 relation_confidence≠review_required"})

    # 6. manual_blocks: link_confidence=review_required(checkManualBlockRows)
    mb_path = pkg / "manual_blocks_preimport.csv"
    if mb_path.exists():
        for i, row in enumerate(read_csv_rows(mb_path), start=2):
            if (row.get("link_confidence") or "").strip() != "review_required":
                findings.append({"id": "manual_block_confidence_not_review_required", "severity": "high",
                                 "detail": f"manual_blocks 第{i}行 link_confidence≠review_required"})

    # 7. semantic_signatures:契约§2.1(G2)——镜像平台 checkManifestSemanticSignatures
    #    必备主题=traceability 的 manual_topic ∪ element;缺失→跳过(不破坏无此字段夹具);
    #    存在但缺主题/字段空→high。
    check_semantic_signatures(manifest, pkg, findings)

    return findings


def check_semantic_signatures(manifest: dict, pkg: Path, findings: list) -> None:
    required_topics = []
    tr_path = pkg / "traceability_matrix_preimport.csv"
    if tr_path.exists():
        for row in read_csv_rows(tr_path):
            for col in ("manual_topic", "element"):
                val = (row.get(col) or "").strip()
                if val and val not in required_topics:
                    required_topics.append(val)
    sigs = manifest.get("semantic_signatures") or []
    if not sigs:
        return  # 缺失→跳过(与平台 dry-run 一致,不破坏无此字段夹具)
    covered = {}
    for idx, sig in enumerate(sigs, start=1):
        topic = (sig.get("topic") or "").strip() if isinstance(sig, dict) else ""
        signer = (sig.get("signer") or "").strip() if isinstance(sig, dict) else ""
        date = (sig.get("date") or "").strip() if isinstance(sig, dict) else ""
        if not topic:
            findings.append({"id": "semantic_signature_missing_topic", "severity": "high",
                             "detail": f"semantic_signatures 第{idx}行缺 topic"})
            continue
        if not signer or not date:
            findings.append({"id": "semantic_signature_field_missing", "severity": "high",
                             "detail": f"semantic_signatures 主题「{topic}」缺 signer 或 date"})
            continue
        covered[topic] = True
    missing = [t for t in required_topics if t not in covered]
    if missing:
        findings.append({"id": "semantic_signatures_incomplete", "severity": "high",
                         "detail": f"语义签字清单不全,缺主题:{'、'.join(missing[:12])}"})


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--package-dir", required=True, help="预导入包目录(含 6 CSV + manifest)")
    args = parser.parse_args()
    pkg = Path(args.package_dir)

    findings = validate(pkg)
    print(f"包目录:{pkg}")
    print(f"发现项数:{len(findings)}")
    for f in findings:
        print(f"  [{f['severity']}] {f['id']}: {f['detail']}")
    if findings:
        print("结果:RED —— manifest 完整性校验未通过")
        return 1
    print("结果:ALL GREEN —— manifest 完整性校验通过(0 发现项)")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
