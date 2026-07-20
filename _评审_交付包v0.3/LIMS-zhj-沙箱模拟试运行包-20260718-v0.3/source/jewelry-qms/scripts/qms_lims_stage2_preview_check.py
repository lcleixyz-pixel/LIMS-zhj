#!/usr/bin/env python3
"""Validate the read-only LIMS stage2 structured import preview package."""

from __future__ import annotations

import argparse
import csv
import datetime as dt
import json
import re
from pathlib import Path
from typing import Any


REQUIRED_FILES = {
    "manifest": "stage2_preview_manifest.json",
    "structured_documents_preview": "01-structured-documents-preview.csv",
    "document_blocks_preview": "02-document-blocks-preview.csv",
    "document_block_links_preview": "03-document-block-links-preview.csv",
    "summary": "04-stage2-preview-summary.md",
    "readme": "README.md",
}

EXPECTED_COUNTS = {
    "structured_documents_preview_rows": 65,
    "document_blocks_preview_rows": 29,
    "document_block_links_preview_rows": 125,
    "procedure_document_links": 93,
    "attachment_form_document_links": 2,
    "record_form_template_links": 30,
}

REQUIRED_GUARDRAILS = [
    "不写数据库",
    "不代表第二阶段已导入",
    "不代表人工评审通过",
    "第一阶段",
    "真实 apply",
    "已取得 CMA",
    "CNAS 申请中",
    "jewelry-qms 仍为建设中系统",
    "不写入质量手册正文",
]

ALLOWED_STRUCTURED_ACTIONS = {
    "plan_manual_structured_refresh_after_revision",
    "refresh_existing_structured_reference",
    "create_structured_reference",
    "refresh_existing_structured_candidate",
    "create_structured_candidate_after_phase1",
}

ALLOWED_BLOCK_ACTIONS = {"create_manual_block_after_manual_revision"}
ALLOWED_LINK_ACTIONS = {"create_block_link_after_block_apply"}
ALLOWED_TARGET_TYPES = {"procedure_document", "attachment_form_document", "record_form_template"}

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


def count_value(rows: list[dict[str, str]], field: str, value: str) -> int:
    return sum(1 for row in rows if row.get(field) == value)


def check_count(manifest_counts: dict[str, Any], key: str, actual: int, findings: list[dict[str, str]]) -> None:
    try:
        expected = int(manifest_counts.get(key, -1))
    except (TypeError, ValueError):
        expected = -1
    if expected != actual:
        fail(findings, "manifest_count_mismatch_" + key, f"manifest {key}={manifest_counts.get(key)}，实际 {actual}。")


def check_no_write_now(rows: list[dict[str, str]], label: str, findings: list[dict[str, str]]) -> None:
    for index, row in enumerate(rows, start=2):
        if row.get("write_now") != "no":
            fail(findings, "preview_row_allows_write", f"{label} 第 {index} 行 write_now 必须为 no。")


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


def check_pack(preview_dir: Path) -> dict[str, Any]:
    findings: list[dict[str, str]] = []
    manifest_path = preview_dir / REQUIRED_FILES["manifest"]
    if not manifest_path.exists():
        return {
            "generated_at": dt.datetime.now().isoformat(timespec="seconds"),
            "preview_dir": str(preview_dir),
            "status": "failed",
            "findings": [{"id": "missing_manifest", "severity": "high", "message": "缺少 stage2_preview_manifest.json"}],
        }

    manifest = json.loads(read_text(manifest_path))
    if manifest.get("status") != "stage2_write_preview_no_database_write":
        fail(findings, "invalid_manifest_status", "manifest 状态必须为 stage2_write_preview_no_database_write。")
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
            fail(findings, "missing_" + key, "缺少第二阶段预览包文件：" + filename)

    for path in list(preview_dir.rglob("*.sql")) + list(preview_dir.rglob("*.db")):
        fail(findings, "forbidden_database_artifact", "第二阶段预览包不应包含数据库/SQL 文件：" + path.name)

    structured_path = preview_dir / files.get("structured_documents_preview", REQUIRED_FILES["structured_documents_preview"])
    blocks_path = preview_dir / files.get("document_blocks_preview", REQUIRED_FILES["document_blocks_preview"])
    links_path = preview_dir / files.get("document_block_links_preview", REQUIRED_FILES["document_block_links_preview"])
    structured_rows = read_csv(structured_path) if structured_path.exists() else []
    block_rows = read_csv(blocks_path) if blocks_path.exists() else []
    link_rows = read_csv(links_path) if links_path.exists() else []

    check_no_write_now(structured_rows, "structured_documents_preview", findings)
    check_no_write_now(block_rows, "document_blocks_preview", findings)
    check_no_write_now(link_rows, "document_block_links_preview", findings)
    check_allowed_actions(structured_rows, ALLOWED_STRUCTURED_ACTIONS, "structured_documents_preview", findings)
    check_allowed_actions(block_rows, ALLOWED_BLOCK_ACTIONS, "document_blocks_preview", findings)
    check_allowed_actions(link_rows, ALLOWED_LINK_ACTIONS, "document_block_links_preview", findings)

    actual_counts = {
        "structured_documents_preview_rows": len(structured_rows),
        "document_blocks_preview_rows": len(block_rows),
        "document_block_links_preview_rows": len(link_rows),
        "procedure_document_links": count_value(link_rows, "target_type", "procedure_document"),
        "attachment_form_document_links": count_value(link_rows, "target_type", "attachment_form_document"),
        "record_form_template_links": count_value(link_rows, "target_type", "record_form_template"),
        "manual_revision_dependency_rows": sum(
            1 for row in structured_rows + block_rows + link_rows if row.get("phase_dependency") == "manual_revision_human_decision_required"
        ),
    }
    manifest_counts = manifest.get("counts", {})
    for key, actual in actual_counts.items():
        check_count(manifest_counts, key, actual, findings)
    for key, expected in EXPECTED_COUNTS.items():
        if actual_counts.get(key) != expected:
            fail(findings, "expected_count_drift_" + key, f"{key} 应为 {expected}，实际 {actual_counts.get(key)}。")

    manual_rows = [row for row in structured_rows if row.get("doc_number") == "XZTC/SC"]
    if len(manual_rows) != 1:
        fail(findings, "manual_structured_preview_missing", "结构化文件预览应包含唯一 XZTC/SC 质量手册行。")
    for row in manual_rows:
        if row.get("preview_action") != "plan_manual_structured_refresh_after_revision":
            fail(findings, "manual_structured_not_revision_plan", "XZTC/SC 结构化预览必须标为手册修订后刷新。")
        if row.get("publish_plan") != "0":
            fail(findings, "manual_structured_publish_not_zero", "XZTC/SC 候选结构化文件在批准前 publish_plan 必须为 0。")

    stable_keys = set()
    for index, row in enumerate(block_rows, start=2):
        stable_key = row.get("stable_key", "")
        if not stable_key:
            fail(findings, "blank_block_stable_key", f"document_blocks 第 {index} 行 stable_key 为空。")
        if stable_key in stable_keys:
            fail(findings, "duplicate_block_stable_key", f"document_blocks stable_key 重复：{stable_key}")
        stable_keys.add(stable_key)
        if row.get("link_confidence") != "review_required":
            fail(findings, "block_confidence_not_review_required", f"{stable_key} link_confidence 必须为 review_required。")
        if row.get("phase_dependency") != "manual_revision_human_decision_required":
            fail(findings, "block_missing_manual_revision_dependency", f"{stable_key} 必须受质量手册修订决策约束。")

    unresolved_targets = 0
    for index, row in enumerate(link_rows, start=2):
        target_type = row.get("target_type", "")
        if target_type not in ALLOWED_TARGET_TYPES:
            fail(findings, "invalid_link_target_type", f"document_block_links 第 {index} 行 target_type 非法：{target_type}")
        if row.get("confidence") != "review_required":
            fail(findings, "link_confidence_not_review_required", f"document_block_links 第 {index} 行 confidence 必须为 review_required。")
        if row.get("block_id_resolution", "").split(" -> ", 1)[0] not in stable_keys:
            fail(findings, "link_block_not_resolved", f"document_block_links 第 {index} 行 block_id_resolution 未对应预览块。")
        if row.get("target_id_resolution") in {"", "not_resolved_before_apply"}:
            unresolved_targets += 1
            fail(findings, "link_target_not_resolved", f"document_block_links 第 {index} 行 target_id_resolution 未解析：{row.get('target_code')}")
        if target_type == "record_form_template" and row.get("relation_type") != "requires_record":
            fail(findings, "record_link_relation_not_requires_record", f"{row.get('target_code')} 记录模板链接 relation_type 应为 requires_record。")

    for key in ["summary", "readme"]:
        filename = files.get(key, REQUIRED_FILES[key])
        path = preview_dir / filename
        if not path.exists():
            continue
        text = read_text(path)
        for marker in ["不写数据库", "不代表第二阶段已导入", "不代表人工评审通过", "真实 apply", "CNAS 申请中"]:
            if marker not in text:
                fail(findings, "doc_missing_guardrail", f"{filename} 缺少边界标识：{marker}")
        if CNAS_OVERSTATEMENT_RE.search(text):
            fail(findings, "doc_overstates_cnas", f"{filename} 疑似包含已取得 CNAS 的越权表述。")

    status = "passed" if not findings else "failed"
    return {
        "generated_at": dt.datetime.now().isoformat(timespec="seconds"),
        "preview_dir": str(preview_dir),
        "status": status,
        "counts": {**actual_counts, "unresolved_targets": unresolved_targets, "findings": len(findings)},
        "findings": findings,
    }


def render_markdown(result: dict[str, Any]) -> str:
    lines = [
        "# LIMS 第二阶段结构化导入行级预览包 dry-run 验证报告",
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
        lines.append("未发现结构性问题。该结论只证明第二阶段行级预览包可读、计数一致且边界明确；不代表真实人工评审、受控发布或正式写库授权。")
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
