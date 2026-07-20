#!/usr/bin/env python3
"""Validate the read-only LIMS write preview package."""

from __future__ import annotations

import argparse
import csv
import datetime as dt
import json
import re
from pathlib import Path
from typing import Any


REQUIRED_FILES = {
    "manifest": "write_preview_manifest.json",
    "documents_preview": "01-documents-draft-preview.csv",
    "record_templates_preview": "02-record-form-templates-draft-preview.csv",
    "sources_preview": "03-qms-sources-upsert-preview.csv",
    "summary": "04-write-preview-summary.md",
    "readme": "README.md",
}

ALLOWED_DOCUMENT_ACTIONS = {
    "create_draft",
    "plan_existing_document_revision",
    "skip_reference_existing_current",
    "skip_existing_document",
    "skip_blank_doc_number",
}

ALLOWED_RECORD_TEMPLATE_ACTIONS = {
    "create_draft",
    "skip_existing_record_template",
    "skip_blank_doc_number",
}

ALLOWED_SOURCE_ACTIONS = {
    "create_source",
    "update_existing_source",
    "skip_blank_source_code",
}

EXPECTED_COUNTS = {
    "documents_preview_rows": 65,
    "documents_create_draft_rows": 27,
    "documents_revision_required_rows": 1,
    "documents_skip_reference_rows": 37,
    "record_template_preview_rows": 26,
    "record_template_create_draft_rows": 26,
    "source_preview_rows": 4,
}

REQUIRED_GUARDRAILS = [
    "不写数据库",
    "不代表人工评审通过",
    "真实 apply",
    "第一阶段",
    "已取得 CMA",
    "CNAS 申请中",
    "jewelry-qms 仍为建设中系统",
    "不写入质量手册正文",
]

CNAS_OVERSTATEMENT_RE = re.compile(
    r"(本公司|公司|实验室|机构).{0,12}(已取得|已获|获得)\s*CNAS|CNAS\s*认可证书"
)


def read_text(path: Path) -> str:
    return path.read_text(encoding="utf-8")


def read_csv(path: Path) -> list[dict[str, str]]:
    with path.open("r", encoding="utf-8", newline="") as handle:
        return list(csv.DictReader(handle))


def fail(findings: list[dict[str, str]], rule_id: str, message: str, severity: str = "high") -> None:
    findings.append({"id": rule_id, "severity": severity, "message": message})


def count_action(rows: list[dict[str, str]], action: str) -> int:
    return sum(1 for row in rows if row.get("preview_action") == action)


def check_allowed_actions(
    rows: list[dict[str, str]],
    allowed: set[str],
    label: str,
    findings: list[dict[str, str]],
) -> None:
    for index, row in enumerate(rows, start=2):
        action = row.get("preview_action", "")
        if action not in allowed:
            fail(findings, "invalid_preview_action", f"{label} 第 {index} 行 preview_action 非法：{action}")


def check_count(
    manifest_counts: dict[str, Any],
    key: str,
    actual: int,
    findings: list[dict[str, str]],
) -> None:
    try:
        expected = int(manifest_counts.get(key, -1))
    except (TypeError, ValueError):
        expected = -1
    if expected != actual:
        fail(findings, "manifest_count_mismatch_" + key, f"manifest {key}={manifest_counts.get(key)}，实际 {actual}。")


def check_pack(preview_dir: Path) -> dict[str, Any]:
    findings: list[dict[str, str]] = []
    manifest_path = preview_dir / REQUIRED_FILES["manifest"]
    if not manifest_path.exists():
        return {
            "generated_at": dt.datetime.now().isoformat(timespec="seconds"),
            "preview_dir": str(preview_dir),
            "status": "failed",
            "findings": [{"id": "missing_manifest", "severity": "high", "message": "缺少 write_preview_manifest.json"}],
        }

    manifest = json.loads(read_text(manifest_path))
    if manifest.get("status") != "write_preview_no_database_write":
        fail(findings, "invalid_manifest_status", "manifest 状态必须为 write_preview_no_database_write。")
    if int(manifest.get("database_write_performed", -1)) != 0:
        fail(findings, "database_write_not_zero", "manifest 必须声明 database_write_performed=0。")

    guardrail_text = "\n".join(str(item) for item in manifest.get("guardrails", []))
    for marker in REQUIRED_GUARDRAILS:
        if marker not in guardrail_text:
            fail(findings, "manifest_missing_guardrail", "manifest 缺少边界标识：" + marker)

    files = manifest.get("files", {})
    for key, default_name in REQUIRED_FILES.items():
        filename = files.get(key, default_name)
        if not (preview_dir / filename).exists():
            fail(findings, "missing_" + key, "缺少写库预览包文件：" + filename)

    for path in list(preview_dir.rglob("*.sql")) + list(preview_dir.rglob("*.db")):
        fail(findings, "forbidden_database_artifact", "写库预览包不应包含数据库/SQL 文件：" + path.name)

    documents_path = preview_dir / files.get("documents_preview", REQUIRED_FILES["documents_preview"])
    records_path = preview_dir / files.get("record_templates_preview", REQUIRED_FILES["record_templates_preview"])
    sources_path = preview_dir / files.get("sources_preview", REQUIRED_FILES["sources_preview"])
    document_rows = read_csv(documents_path) if documents_path.exists() else []
    record_rows = read_csv(records_path) if records_path.exists() else []
    source_rows = read_csv(sources_path) if sources_path.exists() else []

    check_allowed_actions(document_rows, ALLOWED_DOCUMENT_ACTIONS, "documents_preview", findings)
    check_allowed_actions(record_rows, ALLOWED_RECORD_TEMPLATE_ACTIONS, "record_templates_preview", findings)
    check_allowed_actions(source_rows, ALLOWED_SOURCE_ACTIONS, "sources_preview", findings)

    counts = manifest.get("counts", {})
    actual_counts = {
        "documents_preview_rows": len(document_rows),
        "documents_create_draft_rows": count_action(document_rows, "create_draft"),
        "documents_revision_required_rows": count_action(document_rows, "plan_existing_document_revision"),
        "documents_skip_reference_rows": count_action(document_rows, "skip_reference_existing_current"),
        "record_template_preview_rows": len(record_rows),
        "record_template_create_draft_rows": count_action(record_rows, "create_draft"),
        "source_preview_rows": len(source_rows),
        "source_create_rows": count_action(source_rows, "create_source"),
        "source_update_rows": count_action(source_rows, "update_existing_source"),
    }
    for key, actual in actual_counts.items():
        check_count(counts, key, actual, findings)
    for key, expected in EXPECTED_COUNTS.items():
        if actual_counts.get(key) != expected:
            fail(findings, "expected_count_drift_" + key, f"{key} 应为 {expected}，实际 {actual_counts.get(key)}。")

    manual_rows = [row for row in document_rows if row.get("doc_number") == "XZTC/SC"]
    if len(manual_rows) != 1:
        fail(findings, "manual_preview_row_missing", "documents 预览应包含唯一 XZTC/SC 质量手册候选行。")
    for row in manual_rows:
        if row.get("preview_action") != "plan_existing_document_revision" or row.get("existing_status") != "published":
            fail(findings, "manual_preview_not_revision_plan", "XZTC/SC 预览必须标为既有 published 文件的修订路径待办。")
        if row.get("status") != "draft" or row.get("publish") != "0":
            fail(findings, "manual_revision_candidate_not_draft", "XZTC/SC 候选修订口径仍必须保持 draft、publish=0。")

    for index, row in enumerate(document_rows, start=2):
        if row.get("preview_action") == "create_draft":
            for field in ["id_policy", "created_policy", "modified_policy"]:
                if not row.get(field):
                    fail(findings, "draft_document_missing_policy", f"documents 第 {index} 行缺少 {field}。")
        if row.get("preview_action") == "skip_reference_existing_current":
            if row.get("import_mode") not in {"reference_current_catalog", "match_or_upsert_after_file_control_review"}:
                fail(findings, "reference_row_missing_import_mode", f"documents 第 {index} 行现行程序引用 import_mode 不符合当前目录匹配口径。")
            if row.get("existing_match") != "yes":
                fail(findings, "reference_row_missing_existing_match", f"documents 第 {index} 行现行程序引用未匹配 existing document。")

    unresolved_document_links = 0
    unresolved_procedure_links = 0
    for index, row in enumerate(record_rows, start=2):
        if row.get("preview_action") == "create_draft":
            if row.get("status") != "draft" or row.get("review_status") != "pending" or row.get("publish") != "0":
                fail(findings, "record_template_not_pending_draft", f"record_templates 第 {index} 行必须为 draft/pending/publish=0。")
            if row.get("document_id_resolution") in {"", "not_resolved_before_apply"}:
                fail(findings, "record_template_document_not_resolved", f"{row.get('doc_number')} 未解析到配套 documents 行。")
        try:
            schema_len = int(row.get("field_schema_length", "0"))
        except ValueError:
            schema_len = 0
        if schema_len < 8:
            fail(findings, "record_template_schema_too_small", f"{row.get('doc_number')} field_schema_length 过小。")
        if not re.fullmatch(r"[0-9a-f]{40}", row.get("field_schema_sha1", "")):
            fail(findings, "record_template_schema_sha1_invalid", f"{row.get('doc_number')} field_schema_sha1 非法。")
        if row.get("document_id_resolution") in {"", "not_resolved_before_apply"}:
            unresolved_document_links += 1
        if row.get("procedure_doc_id_resolution") in {"", "not_resolved_before_apply"}:
            unresolved_procedure_links += 1

    for index, row in enumerate(source_rows, start=2):
        if not row.get("source_code"):
            fail(findings, "source_code_blank", f"sources 第 {index} 行 source_code 为空。")
        if row.get("soft_delete") != "0":
            fail(findings, "source_soft_delete_not_zero", f"sources 第 {index} 行 soft_delete 必须为 0。")
        if row.get("status") == "obsolete" and row.get("publish") != "0":
            fail(findings, "obsolete_source_not_unpublished", f"{row.get('source_code')} obsolete 时 publish 必须为 0。")

    for key in ["summary", "readme"]:
        filename = files.get(key, REQUIRED_FILES[key])
        path = preview_dir / filename
        if not path.exists():
            continue
        text = read_text(path)
        for marker in ["不写数据库", "不代表人工评审通过", "第一阶段", "真实 apply", "CNAS 申请中"]:
            if marker not in text:
                fail(findings, "doc_missing_guardrail", f"{filename} 缺少边界标识：{marker}")
        if CNAS_OVERSTATEMENT_RE.search(text):
            fail(findings, "doc_overstates_cnas", f"{filename} 疑似包含已取得 CNAS 的越权表述。")

    status = "passed" if not findings else "failed"
    return {
        "generated_at": dt.datetime.now().isoformat(timespec="seconds"),
        "preview_dir": str(preview_dir),
        "status": status,
        "counts": {
            **actual_counts,
            "record_template_unresolved_document_links": unresolved_document_links,
            "record_template_unresolved_procedure_links": unresolved_procedure_links,
            "findings": len(findings),
        },
        "findings": findings,
    }


def render_markdown(result: dict[str, Any]) -> str:
    lines = [
        "# LIMS 写库行级预览包 dry-run 验证报告",
        "",
        f"生成时间：{result['generated_at']}",
        f"验证对象：`{result['preview_dir']}`",
        f"结论：{result['status']}",
        "",
        "## 计数",
        "",
    ]
    for key, value in result.get("counts", {}).items():
        lines.append(f"- {key}: {value}")
    lines.extend(["", "## 发现项", ""])
    if result.get("findings"):
        for finding in result["findings"]:
            lines.append(f"- [{finding['severity']}] {finding['id']}：{finding['message']}")
    else:
        lines.append("未发现结构性问题。该结论只证明行级写库预览包可读、计数一致且边界明确；不代表真实人工评审、受控发布或正式写库授权。")
    lines.append("")
    return "\n".join(lines)


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--preview-dir", required=True)
    parser.add_argument("--json-out")
    parser.add_argument("--md-out")
    args = parser.parse_args()

    result = check_pack(Path(args.preview_dir))
    print(json.dumps(result, ensure_ascii=False, indent=2))
    if args.json_out:
        Path(args.json_out).write_text(json.dumps(result, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
    if args.md_out:
        Path(args.md_out).write_text(render_markdown(result), encoding="utf-8")
    return 0 if result["status"] == "passed" else 1


if __name__ == "__main__":
    raise SystemExit(main())
