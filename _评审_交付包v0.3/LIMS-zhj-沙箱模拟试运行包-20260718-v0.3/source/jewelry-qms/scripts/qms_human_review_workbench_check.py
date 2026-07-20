#!/usr/bin/env python3
"""Dry-run validation for the QMS human-review workbench."""

from __future__ import annotations

import argparse
import csv
import datetime as dt
import json
import re
from pathlib import Path
from typing import Any


REQUIRED_FILES = {
    "overview": "00-人工评审总览.md",
    "role_checklist": "01-按角色评审清单.md",
    "clause_workbench": "02-条款评审工作台.md",
    "template_workbench": "03-记录模板评审工作台.md",
    "attachment_workbench": "04-05-02归属判定工作台.md",
    "gate_workbench": "05-apply前闸门工作台.md",
    "decision_template": "decision_update_template.csv",
    "manifest": "workbench_manifest.json",
}

REQUIRED_MARKERS = [
    "不写数据库",
    "不代表",
    "pending",
]


def read_text(path: Path) -> str:
    return path.read_text(encoding="utf-8")


def read_csv(path: Path) -> list[dict[str, str]]:
    with path.open("r", encoding="utf-8", newline="") as handle:
        return list(csv.DictReader(handle))


def fail(findings: list[dict[str, str]], rule_id: str, message: str, severity: str = "high") -> None:
    findings.append({"id": rule_id, "severity": severity, "message": message})


def check_no_approval_text(workbench_dir: Path, findings: list[dict[str, str]]) -> None:
    patterns = [
        r"已批准",
        r"批准发布",
        r"准许写库",
        r"可以写库",
        r"new_human_decision[,， ]*(approved|pass|通过|批准)",
    ]
    for path in workbench_dir.glob("*"):
        if not path.is_file() or path.suffix.lower() not in {".md", ".csv", ".json"}:
            continue
        text = read_text(path)
        for pattern in patterns:
            if re.search(pattern, text, flags=re.IGNORECASE):
                fail(findings, "approval_language_present", f"{path.name} 疑似包含批准或准许写库表述：{pattern}")


def check_workbench(workbench_dir: Path) -> dict[str, Any]:
    findings: list[dict[str, str]] = []
    manifest_path = workbench_dir / "workbench_manifest.json"
    if not manifest_path.exists():
        return {
            "generated_at": dt.datetime.now().isoformat(timespec="seconds"),
            "workbench_dir": str(workbench_dir),
            "status": "failed",
            "findings": [{"id": "missing_manifest", "severity": "high", "message": "缺少 workbench_manifest.json"}],
        }

    manifest = json.loads(read_text(manifest_path))
    if manifest.get("status") != "human_review_workbench_pending_no_database_write":
        fail(findings, "invalid_manifest_status", "workbench_manifest.json 状态必须为 human_review_workbench_pending_no_database_write。")

    manifest_files = manifest.get("files", {})
    for key, filename in REQUIRED_FILES.items():
        actual = manifest_files.get(key, filename)
        path = workbench_dir / actual
        if not path.exists():
            fail(findings, "missing_" + key, f"缺少工作台文件：{actual}")

    boundary_text = "\n".join(manifest.get("boundary", []))
    for marker in REQUIRED_MARKERS:
        if marker not in boundary_text:
            fail(findings, "manifest_missing_guardrail", f"manifest boundary 缺少标识：{marker}")

    decision_path = workbench_dir / manifest_files.get("decision_template", "decision_update_template.csv")
    decision_rows: list[dict[str, str]] = []
    if decision_path.exists():
        decision_rows = read_csv(decision_path)
        if len(decision_rows) != 67:
            fail(findings, "decision_count_mismatch", f"decision_update_template.csv 应为 67 行，实际 {len(decision_rows)}。")
        type_counts: dict[str, int] = {}
        for index, row in enumerate(decision_rows, start=2):
            review_type = row.get("review_type", "")
            type_counts[review_type] = type_counts.get(review_type, 0) + 1
            if row.get("current_decision") != "pending":
                fail(findings, "current_decision_not_pending", f"第 {index} 行 current_decision 必须为 pending。")
            if row.get("new_human_decision", "") != "":
                fail(findings, "new_decision_not_blank", f"第 {index} 行 new_human_decision 应保持空白，实际为 {row.get('new_human_decision')}")
            if row.get("blocking_if_unresolved") != "yes":
                fail(findings, "decision_not_blocking", f"第 {index} 行未标明 unresolved 时阻断。", "medium")
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

    for filename in [
        manifest_files.get("overview", "00-人工评审总览.md"),
        manifest_files.get("role_checklist", "01-按角色评审清单.md"),
        manifest_files.get("clause_workbench", "02-条款评审工作台.md"),
        manifest_files.get("template_workbench", "03-记录模板评审工作台.md"),
        manifest_files.get("attachment_workbench", "04-05-02归属判定工作台.md"),
        manifest_files.get("gate_workbench", "05-apply前闸门工作台.md"),
    ]:
        path = workbench_dir / filename
        if not path.exists():
            continue
        text = read_text(path)
        for marker in REQUIRED_MARKERS:
            if marker not in text:
                fail(findings, "workbench_missing_guardrail", f"{filename} 缺少标识：{marker}")

    template_text_path = workbench_dir / manifest_files.get("template_workbench", "03-记录模板评审工作台.md")
    if template_text_path.exists():
        text = read_text(template_text_path)
        trial_links = text.count("全量试填")
        if trial_links < 26:
            fail(findings, "template_trial_links_missing", f"记录模板评审工作台应包含 26 个全量试填链接，实际 {trial_links}。")

    for path in list(workbench_dir.glob("*.sql")) + list(workbench_dir.glob("*.db")):
        fail(findings, "forbidden_database_artifact", f"工作台不应包含数据库/SQL 文件：{path.name}")

    check_no_approval_text(workbench_dir, findings)

    counts = manifest.get("counts", {})
    if counts.get("decision_items_total") != 67:
        fail(findings, "manifest_decision_total_invalid", f"manifest decision_items_total 应为 67，实际 {counts.get('decision_items_total')}。")
    if counts.get("pending_decisions") != 67:
        fail(findings, "manifest_pending_total_invalid", f"manifest pending_decisions 应为 67，实际 {counts.get('pending_decisions')}。")

    status = "passed" if not findings else "failed"
    return {
        "generated_at": dt.datetime.now().isoformat(timespec="seconds"),
        "workbench_dir": str(workbench_dir),
        "status": status,
        "counts": {
            "decision_items_total": len(decision_rows),
            "manual_clause_items": sum(1 for row in decision_rows if row.get("review_type") == "manual_clause"),
            "record_template_items": sum(1 for row in decision_rows if row.get("review_type") == "record_template"),
            "attachment_disposition_items": sum(1 for row in decision_rows if row.get("review_type") == "attachment_form_disposition"),
            "preapply_gate_items": sum(1 for row in decision_rows if row.get("review_type") == "preapply_gate"),
            "findings": len(findings),
        },
        "findings": findings,
    }


def render_markdown(result: dict[str, Any]) -> str:
    lines = [
        "# LIMS 人工评审工作台 dry-run 验证报告",
        "",
        f"生成时间：{result['generated_at']}",
        f"验证对象：`{result['workbench_dir']}`",
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
        lines.append("未发现阻断性问题。该结论仅证明工作台结构完整、全部决策仍为 pending/空白、未出现批准或写库语言；不代表已经人工批准或写入 LIMS。")
    lines.append("")
    return "\n".join(lines)


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--workbench-dir", required=True)
    parser.add_argument("--json-out")
    parser.add_argument("--md-out")
    args = parser.parse_args()

    result = check_workbench(Path(args.workbench_dir))
    print(json.dumps(result, ensure_ascii=False, indent=2))
    if args.json_out:
        Path(args.json_out).write_text(json.dumps(result, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
    if args.md_out:
        Path(args.md_out).write_text(render_markdown(result), encoding="utf-8")
    return 0 if result["status"] == "passed" else 1


if __name__ == "__main__":
    raise SystemExit(main())
