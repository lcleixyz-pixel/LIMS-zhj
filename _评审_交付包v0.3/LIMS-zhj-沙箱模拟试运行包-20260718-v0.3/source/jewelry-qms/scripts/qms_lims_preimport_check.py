#!/usr/bin/env python3
"""Dry-run validation for the QMS LIMS pre-import package."""

from __future__ import annotations

import argparse
import csv
import datetime as dt
import json
import re
from pathlib import Path
from typing import Any


EXPECTED_CLAUSES = [
    "4.1",
    "4.2",
    "5",
    "6.1",
    "6.2",
    "6.3",
    "6.4",
    "6.5",
    "6.6",
    "7.1",
    "7.2",
    "7.3",
    "7.4",
    "7.5",
    "7.6",
    "7.7",
    "7.8",
    "7.9",
    "7.10",
    "7.11",
    "8.1",
    "8.2",
    "8.3",
    "8.4",
    "8.5",
    "8.6",
    "8.7",
    "8.8",
    "8.9",
]

REQUIRED_SCHEMA_KEYS = {
    "record_number",
    "record_name",
    "applicable_clause",
    "related_procedure",
    "responsible_position",
    "trigger_time",
    "correction_rule",
}


def read_text(path: Path) -> str:
    return path.read_text(encoding="utf-8")


def read_csv(path: Path) -> list[dict[str, str]]:
    with path.open("r", encoding="utf-8", newline="") as handle:
        return list(csv.DictReader(handle))


def is_attachment_form(item: dict[str, str]) -> bool:
    return (
        item.get("document_kind") == "numbered_attachment"
        or item.get("reason") == "numbered_non_procedure"
    )


def load_manifest_codes(lims_root: Path) -> tuple[set[str], set[str]]:
    data = json.loads(read_text(lims_root / "knowledge/internal/procedures/PROCEDURE_FILE_MANIFEST.json"))
    current_items = [
        item
        for item in data.get("included", [])
        if item.get("doc_number") and item.get("year") == "2022"
    ]
    procedure_codes = {item["doc_number"] for item in current_items if not is_attachment_form(item)}
    attachment_codes = {item["doc_number"] for item in current_items if is_attachment_form(item)}
    return procedure_codes, attachment_codes


def fail(findings: list[dict[str, str]], rule_id: str, message: str, severity: str = "high") -> None:
    findings.append({"id": rule_id, "severity": severity, "message": message})


def split_refs(value: str) -> list[str]:
    return [item.strip() for item in re.split(r"[;；]", value or "") if item.strip()]


def check_package(package_dir: Path, lims_root: Path, stage_dir: Path | None = None) -> dict[str, Any]:
    findings: list[dict[str, str]] = []
    manifest_path = package_dir / "preimport_manifest.json"
    if not manifest_path.exists():
        return {
            "generated_at": dt.datetime.now().isoformat(timespec="seconds"),
            "package_dir": str(package_dir),
            "status": "failed",
            "findings": [{"id": "missing_manifest", "severity": "high", "message": "缺少 preimport_manifest.json"}],
        }

    manifest = json.loads(read_text(manifest_path))
    rows: dict[str, list[dict[str, str]]] = {}
    for label, filename in manifest.get("files", {}).items():
        path = package_dir / filename
        if not path.exists():
            fail(findings, "missing_" + label, f"缺少文件：{filename}")
            continue
        rows[label] = read_csv(path)

    for label, expected in {
        "documents": manifest.get("counts", {}).get("documents"),
        "structured_documents": manifest.get("counts", {}).get("structured_documents"),
        "record_form_templates": manifest.get("counts", {}).get("record_form_templates"),
        "traceability_matrix": manifest.get("counts", {}).get("traceability_rows"),
        "manual_blocks": manifest.get("counts", {}).get("manual_blocks"),
        "external_sources": manifest.get("counts", {}).get("external_sources"),
    }.items():
        if label in rows and expected is not None and len(rows[label]) != int(expected):
            fail(findings, "count_mismatch_" + label, f"{label} 计数不一致：manifest={expected}, csv={len(rows[label])}")

    procedure_codes, attachment_codes = load_manifest_codes(lims_root)
    current_catalog_codes = procedure_codes | attachment_codes
    document_rows = rows.get("documents", [])
    document_codes = {row["doc_number"] for row in document_rows}
    missing_catalog_docs = sorted(current_catalog_codes - document_codes)
    if missing_catalog_docs:
        fail(findings, "missing_current_catalog_documents", "documents_preimport 缺少 2022 清单编号：" + "、".join(missing_catalog_docs))

    valid_levels = {"1", "2", "3", "4"}
    valid_doc_status = {"draft", "reviewing", "approved", "published", "obsolete"}
    for index, row in enumerate(document_rows, start=2):
        if row.get("level") not in valid_levels:
            fail(findings, "invalid_document_level", f"documents 第 {index} 行 level 非法：{row.get('level')}")
        if row.get("status") not in valid_doc_status:
            fail(findings, "invalid_document_status", f"documents 第 {index} 行 status 非法：{row.get('status')}")
        if row.get("action") == "reference_existing_current" and row.get("doc_number") not in procedure_codes:
            fail(findings, "reference_current_not_procedure", f"documents 第 {index} 行不是程序文件，不应作为现行程序匹配：{row.get('doc_number')}")
        if row.get("action") == "reference_existing_attachment_form":
            if row.get("doc_number") not in attachment_codes:
                fail(findings, "attachment_form_not_in_manifest", f"documents 第 {index} 行不是清单中的编号附件/表单：{row.get('doc_number')}")
            if row.get("level") != "4" or row.get("status") != "draft" or row.get("publish") != "0":
                fail(findings, "attachment_form_not_review_candidate", f"documents 第 {index} 行编号附件/表单必须保持 level=4、draft、publish=0。")

    manual_rows = [row for row in document_rows if row.get("doc_number") == "XZTC/SC"]
    if not manual_rows:
        fail(findings, "missing_candidate_manual_document", "documents_preimport 缺少 XZTC/SC 候选手册行。")
    for row in manual_rows:
        if row.get("status") != "draft" or row.get("publish") != "0":
            fail(findings, "candidate_manual_not_draft", "候选手册预导入行必须保持 draft 且 publish=0。")

    trace_rows = rows.get("traceability_matrix", [])
    trace_clauses = {row.get("clause", "") for row in trace_rows}
    missing_clauses = [clause for clause in EXPECTED_CLAUSES if clause not in trace_clauses]
    if missing_clauses:
        fail(findings, "traceability_missing_clauses", "追溯矩阵缺少条款：" + "、".join(missing_clauses))

    record_template_rows = rows.get("record_form_templates", [])
    record_codes = {row["doc_number"] for row in record_template_rows}
    for row in trace_rows:
        unknown_procedures = [code for code in split_refs(row.get("procedure_doc_numbers", "")) if code not in procedure_codes]
        if unknown_procedures:
            fail(findings, "traceability_unknown_procedure", f"{row.get('clause')} 引用了未知程序：" + "、".join(unknown_procedures))
        unknown_attachments = [code for code in split_refs(row.get("attachment_form_doc_numbers", "")) if code not in attachment_codes]
        if unknown_attachments:
            fail(findings, "traceability_unknown_attachment_form", f"{row.get('clause')} 引用了未知编号附件/表单：" + "、".join(unknown_attachments))
        unknown_records = [code for code in split_refs(row.get("record_template_numbers", "")) if code not in record_codes]
        if unknown_records:
            fail(findings, "traceability_unknown_record_template", f"{row.get('clause')} 引用了未知记录模板：" + "、".join(unknown_records))
        if row.get("human_review_required") != "yes":
            fail(findings, "traceability_missing_human_gate", f"{row.get('clause')} 未标明人工复核闸门。", "medium")

    valid_template_status = {"draft", "published", "obsolete"}
    valid_review_status = {"pending", "ai_generated", "field_confirmed", "needs_fidelity", "deferred", "completed"}
    for index, row in enumerate(record_template_rows, start=2):
        if row.get("status") not in valid_template_status:
            fail(findings, "invalid_record_template_status", f"record_form_templates 第 {index} 行 status 非法。")
        if row.get("review_status") not in valid_review_status:
            fail(findings, "invalid_record_template_review_status", f"record_form_templates 第 {index} 行 review_status 非法。")
        unknown_attachments = [code for code in split_refs(row.get("attachment_form_doc_numbers", "")) if code not in attachment_codes]
        if unknown_attachments:
            fail(findings, "record_template_unknown_attachment_form", f"{row.get('doc_number')} 引用了未知编号附件/表单：" + "、".join(unknown_attachments))
        try:
            schema = json.loads(row.get("field_schema_json", ""))
        except json.JSONDecodeError as exc:
            fail(findings, "invalid_field_schema_json", f"{row.get('doc_number')} field_schema_json 非法：{exc}")
            continue
        if not isinstance(schema, list) or len(schema) < 8:
            fail(findings, "field_schema_too_small", f"{row.get('doc_number')} 字段 schema 过少。")
            continue
        keys = {str(item.get("key", "")) for item in schema if isinstance(item, dict)}
        missing_keys = REQUIRED_SCHEMA_KEYS - keys
        if missing_keys:
            fail(findings, "field_schema_missing_required_keys", f"{row.get('doc_number')} 缺少通用字段：" + "、".join(sorted(missing_keys)))

    manual_block_rows = rows.get("manual_blocks", [])
    block_sections = {row.get("section_number", "") for row in manual_block_rows}
    missing_blocks = [clause for clause in EXPECTED_CLAUSES if clause not in block_sections]
    if missing_blocks:
        fail(findings, "manual_blocks_missing_sections", "手册块级索引缺少：" + "、".join(missing_blocks))
    for row in manual_block_rows:
        if row.get("link_confidence") != "review_required":
            fail(findings, "manual_block_not_review_required", f"{row.get('section_number')} 未设置 review_required。", "medium")

    source_rows = rows.get("external_sources", [])
    obsolete_sources = [row for row in source_rows if row.get("source_code") == "RBT_045_2020"]
    if not obsolete_sources or obsolete_sources[0].get("status") != "obsolete":
        fail(findings, "rbt_045_not_obsolete", "RB/T 045-2020 未在外来依据候选中标为 obsolete。")

    sql_files = sorted(package_dir.glob("*.sql"))
    if sql_files:
        fail(findings, "sql_files_not_allowed", "预导入包不应包含 SQL 文件：" + "、".join(path.name for path in sql_files))

    if stage_dir:
        manual_path = stage_dir / "10-质量手册第五版候选稿.md"
        if manual_path.exists():
            manual_text = read_text(manual_path)
            if "jewelry-qms" in manual_text:
                fail(findings, "manual_mentions_jewelry_qms", "候选手册正文出现 jewelry-qms。")
            if re.search(r"已取得\s*CNAS|CNAS\s*认可证书", manual_text):
                fail(findings, "manual_overstates_cnas", "候选手册疑似把 CNAS 写成已取得。")

    status = "passed" if not findings else "failed"
    return {
        "generated_at": dt.datetime.now().isoformat(timespec="seconds"),
        "package_dir": str(package_dir),
        "stage_dir": str(stage_dir) if stage_dir else None,
        "lims_root": str(lims_root),
        "status": status,
        "counts": {
            "documents": len(rows.get("documents", [])),
            "structured_documents": len(rows.get("structured_documents", [])),
            "record_form_templates": len(rows.get("record_form_templates", [])),
            "traceability_rows": len(rows.get("traceability_matrix", [])),
            "manual_blocks": len(rows.get("manual_blocks", [])),
            "external_sources": len(rows.get("external_sources", [])),
            "findings": len(findings),
        },
        "findings": findings,
    }


def render_markdown(result: dict[str, Any]) -> str:
    lines = [
        "# LIMS 预导入包 dry-run 验证报告",
        "",
        f"生成时间：{result['generated_at']}",
        f"验证对象：`{result['package_dir']}`",
        f"结论：{result['status']}",
        "",
        "## 计数",
        "",
    ]
    for key, value in result.get("counts", {}).items():
        lines.append(f"- {key}: {value}")
    lines.append("")
    if result.get("findings"):
        lines.extend(["## 发现项", ""])
        for item in result["findings"]:
            lines.append(f"- [{item['severity']}] {item['id']}：{item['message']}")
    else:
        lines.extend(
            [
                "## 发现项",
                "",
                "未发现阻断性问题。该结论仅证明预导入包结构、编号、候选状态和人工闸门通过 dry-run 检查，不代表已经写入 LIMS 或正式发布受控文件。",
            ]
        )
    lines.append("")
    return "\n".join(lines)


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--package-dir", required=True)
    parser.add_argument("--lims-root", default="/Users/lc.leixyz/Documents/AI工作台/01-项目代码/LIMS-zhj")
    parser.add_argument("--stage-dir")
    parser.add_argument("--json-out")
    parser.add_argument("--md-out")
    args = parser.parse_args()

    result = check_package(
        Path(args.package_dir),
        Path(args.lims_root),
        Path(args.stage_dir) if args.stage_dir else None,
    )
    print(json.dumps(result, ensure_ascii=False, indent=2))
    if args.json_out:
        Path(args.json_out).write_text(json.dumps(result, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
    if args.md_out:
        Path(args.md_out).write_text(render_markdown(result), encoding="utf-8")
    return 0 if result["status"] == "passed" else 1


if __name__ == "__main__":
    raise SystemExit(main())
