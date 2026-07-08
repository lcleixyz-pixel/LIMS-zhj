#!/usr/bin/env python3
"""Dry-run validation for the QMS human-review decision preview package."""

from __future__ import annotations

import argparse
import csv
import datetime as dt
import json
from pathlib import Path
from typing import Any


REQUIRED_FILES = {
    "overview": "01-决策回填预览总览.md",
    "status_preview": "02-待处理与异常预览.md",
    "source_impact": "03-源文件影响预览.md",
    "validation_csv": "decision_update_validation.csv",
    "overlay_preview": "review_pack_overlay_preview_not_for_import.csv",
    "manifest": "decision_preview_manifest.json",
    "readme": "README.md",
}

FORBIDDEN_REVIEW_PACK_FILENAMES = {
    "human_review_manifest.json",
    "manual_clause_review_checklist.csv",
    "record_template_review_checklist.csv",
    "attachment_form_disposition.csv",
    "preapply_gate_register.csv",
}

REQUIRED_BOUNDARY_MARKERS = [
    "不写数据库",
    "不修改 human_review_pack",
    "不能作为 qms:preimport-package --review-dir",
    "不代表",
]


def read_text(path: Path) -> str:
    return path.read_text(encoding="utf-8")


def read_csv(path: Path) -> list[dict[str, str]]:
    with path.open("r", encoding="utf-8", newline="") as handle:
        return list(csv.DictReader(handle))


def fail(findings: list[dict[str, str]], rule_id: str, message: str, severity: str = "high") -> None:
    findings.append({"id": rule_id, "severity": severity, "message": message})


def check_preview(preview_dir: Path) -> dict[str, Any]:
    findings: list[dict[str, str]] = []
    manifest_path = preview_dir / "decision_preview_manifest.json"
    if not manifest_path.exists():
        return {
            "generated_at": dt.datetime.now().isoformat(timespec="seconds"),
            "preview_dir": str(preview_dir),
            "status": "failed",
            "findings": [{"id": "missing_manifest", "severity": "high", "message": "缺少 decision_preview_manifest.json"}],
        }

    manifest = json.loads(read_text(manifest_path))
    if manifest.get("status") != "decision_preview_no_database_write":
        fail(findings, "invalid_manifest_status", "decision_preview_manifest.json 状态必须为 decision_preview_no_database_write。")

    boundary_text = "\n".join(str(item) for item in manifest.get("boundary", []))
    for marker in REQUIRED_BOUNDARY_MARKERS:
        if marker not in boundary_text:
            fail(findings, "manifest_missing_boundary", f"manifest boundary 缺少标识：{marker}")

    files = manifest.get("files", {})
    for key, default_filename in REQUIRED_FILES.items():
        filename = files.get(key, default_filename)
        if not (preview_dir / filename).exists():
            fail(findings, "missing_" + key, f"缺少预览文件：{filename}")

    forbidden_present = sorted(name for name in FORBIDDEN_REVIEW_PACK_FILENAMES if (preview_dir / name).exists())
    if forbidden_present:
        fail(findings, "forbidden_review_pack_files", "预览目录不应包含可被当作正式人工评审包的文件：" + "、".join(forbidden_present))

    for path in list(preview_dir.glob("*.sql")) + list(preview_dir.glob("*.db")):
        fail(findings, "forbidden_database_artifact", f"预览包不应包含数据库/SQL 文件：{path.name}")

    validation_path = preview_dir / files.get("validation_csv", "decision_update_validation.csv")
    validation_rows: list[dict[str, str]] = []
    if validation_path.exists():
        validation_rows = read_csv(validation_path)
        if len(validation_rows) != 67:
            fail(findings, "decision_count_mismatch", f"decision_update_validation.csv 应为 67 行，实际 {len(validation_rows)}。")
        ids: set[str] = set()
        type_counts: dict[str, int] = {}
        for index, row in enumerate(validation_rows, start=2):
            review_item_id = row.get("review_item_id", "")
            if not review_item_id:
                fail(findings, "blank_review_item_id", f"第 {index} 行 review_item_id 为空。")
            if review_item_id in ids:
                fail(findings, "duplicate_review_item_id", f"重复 review_item_id：{review_item_id}")
            ids.add(review_item_id)
            review_type = row.get("review_type", "")
            type_counts[review_type] = type_counts.get(review_type, 0) + 1
            if row.get("preview_status") in {"invalid_decision", "missing_comment"}:
                fail(
                    findings,
                    "invalid_decision_update",
                    f"{review_item_id} 的回填预览存在高风险问题：{row.get('preview_status')} {row.get('issue_message')}",
                )
            if row.get("would_satisfy_lims_approved_decision") == "yes" and row.get("comment_present") != "yes":
                fail(findings, "approved_without_comment", f"{review_item_id} 被视为通过但缺少 review_comment。")
        expected_counts = {
            "manual_clause": 29,
            "record_template": 26,
            "attachment_form_disposition": 1,
            "preapply_gate": 11,
        }
        for review_type, expected in expected_counts.items():
            actual = type_counts.get(review_type, 0)
            if actual != expected:
                fail(findings, "decision_type_count_mismatch", f"{review_type} 应为 {expected} 行，实际 {actual}。")

    overlay_path = preview_dir / files.get("overlay_preview", "review_pack_overlay_preview_not_for_import.csv")
    if overlay_path.exists():
        overlay_text = read_text(overlay_path)
        if "not_for_import" not in overlay_text:
            fail(findings, "overlay_missing_not_for_import", "overlay 预览文件缺少 not_for_import 标识。")

    for key in ["overview", "status_preview", "source_impact", "readme"]:
        path = preview_dir / files.get(key, REQUIRED_FILES[key])
        if not path.exists():
            continue
        text = read_text(path)
        for marker in ["不写数据库", "不修改 `human_review_pack/`", "不能作为 `--review-dir`"]:
            if marker not in text:
                fail(findings, "preview_doc_missing_guardrail", f"{path.name} 缺少边界标识：{marker}")

    counts = manifest.get("counts", {})
    if counts.get("decision_items_total") != len(validation_rows):
        fail(
            findings,
            "manifest_validation_count_mismatch",
            f"manifest decision_items_total 与 validation csv 不一致：{counts.get('decision_items_total')} vs {len(validation_rows)}",
        )
    high_issues = sum(1 for row in validation_rows if row.get("issue_severity") == "high")
    if counts.get("high_issues") != high_issues:
        fail(findings, "manifest_high_issue_count_mismatch", f"manifest high_issues 应为 {high_issues}，实际 {counts.get('high_issues')}。")

    status = "passed" if not findings else "failed"
    return {
        "generated_at": dt.datetime.now().isoformat(timespec="seconds"),
        "preview_dir": str(preview_dir),
        "status": status,
        "readiness": manifest.get("readiness", ""),
        "counts": {
            "decision_items_total": len(validation_rows),
            "proposed_changes": counts.get("proposed_changes", 0),
            "blocking_after_preview": counts.get("blocking_after_preview", 0),
            "high_issues": counts.get("high_issues", 0),
            "medium_issues": counts.get("medium_issues", 0),
            "findings": len(findings),
        },
        "findings": findings,
    }


def render_markdown(result: dict[str, Any]) -> str:
    lines = [
        "# LIMS 人工评审决策回填预览 dry-run 验证报告",
        "",
        f"生成时间：{result['generated_at']}",
        f"验证对象：`{result['preview_dir']}`",
        f"结论：{result['status']}",
        f"回填就绪度：{result.get('readiness', '')}",
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
        lines.append("未发现预览包结构性问题。该结论只证明回填预览可检查且未伪装成正式评审包；不代表已经人工批准、已修改 human_review_pack 或已写入 LIMS。")
    lines.append("")
    return "\n".join(lines)


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--preview-dir", required=True)
    parser.add_argument("--json-out")
    parser.add_argument("--md-out")
    args = parser.parse_args()

    result = check_preview(Path(args.preview_dir))
    print(json.dumps(result, ensure_ascii=False, indent=2))
    if args.json_out:
        Path(args.json_out).write_text(json.dumps(result, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
    if args.md_out:
        Path(args.md_out).write_text(render_markdown(result), encoding="utf-8")
    return 0 if result["status"] == "passed" else 1


if __name__ == "__main__":
    raise SystemExit(main())
