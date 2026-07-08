#!/usr/bin/env python3
"""Dry-run validation for the simulated approved human-review pack."""

from __future__ import annotations

import argparse
import csv
import datetime as dt
import json
import re
from pathlib import Path
from typing import Any


REQUIRED_REVIEW_FILES = {
    "manual_clause_review": "manual_clause_review_checklist.csv",
    "record_template_review": "record_template_review_checklist.csv",
    "attachment_disposition": "attachment_form_disposition.csv",
    "preapply_gate_register": "preapply_gate_register.csv",
}

SIMULATION_MARKER = "SIMULATED_APPROVAL_NOT_REAL_REVIEW"
SIMULATED_DECISION = "确认通过"


def read_csv(path: Path) -> list[dict[str, str]]:
    with path.open("r", encoding="utf-8", newline="") as handle:
        return list(csv.DictReader(handle))


def fail(findings: list[dict[str, str]], rule_id: str, message: str, severity: str = "high") -> None:
    findings.append({"id": rule_id, "severity": severity, "message": message})


def check_simulation_pack(simulation_dir: Path) -> dict[str, Any]:
    findings: list[dict[str, str]] = []
    manifest_path = simulation_dir / "human_review_manifest.json"
    if not manifest_path.exists():
        return {
            "generated_at": dt.datetime.now().isoformat(timespec="seconds"),
            "simulation_dir": str(simulation_dir),
            "status": "failed",
            "findings": [{"id": "missing_manifest", "severity": "high", "message": "缺少 human_review_manifest.json"}],
        }
    manifest = json.loads(manifest_path.read_text(encoding="utf-8"))
    if manifest.get("status") != "human_review_simulation_no_database_write":
        fail(findings, "invalid_manifest_status", "human_review_manifest.json 状态必须为 human_review_simulation_no_database_write。")
    if manifest.get("simulation_marker") != SIMULATION_MARKER:
        fail(findings, "missing_manifest_simulation_marker", f"manifest 缺少 {SIMULATION_MARKER} 标识。")

    guardrail_text = "\n".join(str(item) for item in manifest.get("guardrails", []))
    for marker in ["apply-rehearsal", "不代表真实人工评审", "不得作为正式 --apply", SIMULATION_MARKER, "CNAS 申请中"]:
        if marker not in guardrail_text:
            fail(findings, "manifest_missing_guardrail", "manifest 缺少边界标识：" + marker)

    total_rows = 0
    approved_rows = 0
    marker_rows = 0
    file_counts: dict[str, int] = {}
    for key, filename in REQUIRED_REVIEW_FILES.items():
        path = simulation_dir / filename
        if not path.exists():
            fail(findings, "missing_" + key, "缺少模拟评审文件：" + filename)
            file_counts[key] = 0
            continue
        rows = read_csv(path)
        file_counts[key] = len(rows)
        for index, row in enumerate(rows, start=2):
            total_rows += 1
            decision = row.get("human_decision", "")
            note = row.get("review_note", "")
            if decision == SIMULATED_DECISION:
                approved_rows += 1
            else:
                fail(findings, "decision_not_simulated_approved", f"{filename} 第 {index} 行 human_decision 应为 {SIMULATED_DECISION}，实际为 {decision}")
            if SIMULATION_MARKER in note:
                marker_rows += 1
            else:
                fail(findings, "row_missing_simulation_marker", f"{filename} 第 {index} 行 review_note 缺少 {SIMULATION_MARKER}。")
            if row.get("blocking_if_unresolved") == "yes" and decision != SIMULATED_DECISION:
                fail(findings, "blocking_row_not_simulated", f"{filename} 第 {index} 行阻断项未模拟通过。")

    counts = manifest.get("counts", {})
    expected = {
        "manual_clause_review_items": file_counts.get("manual_clause_review", 0),
        "record_template_review_items": file_counts.get("record_template_review", 0),
        "attachment_disposition_items": file_counts.get("attachment_disposition", 0),
        "preapply_gates": file_counts.get("preapply_gate_register", 0),
        "total_simulated_decisions": total_rows,
    }
    for key, actual in expected.items():
        if int(counts.get(key, -1)) != actual:
            fail(findings, "manifest_count_mismatch_" + key, f"manifest {key}={counts.get(key)}，实际 {actual}。")

    for path in list(simulation_dir.glob("*.sql")) + list(simulation_dir.glob("*.db")):
        fail(findings, "forbidden_database_artifact", "模拟人审包不应包含数据库/SQL 文件：" + path.name)

    for filename in ["README.md", "人工评审模拟说明.md"]:
        path = simulation_dir / filename
        if not path.exists():
            fail(findings, "missing_doc_" + filename, "缺少说明文件：" + filename)
            continue
        text = path.read_text(encoding="utf-8")
        for marker in ["不代表真实人工评审", "不得作为正式", SIMULATION_MARKER]:
            if marker not in text:
                fail(findings, "doc_missing_guardrail", f"{filename} 缺少边界标识：{marker}")
        if re.search(r"本公司已取得\s*CNAS|公司已取得\s*CNAS|实验室已取得\s*CNAS|已获\s*CNAS\s*认可|获得\s*CNAS\s*认可", text):
            fail(findings, "doc_overstates_cnas", f"{filename} 疑似包含已取得 CNAS 的越权表述。")

    status = "passed" if not findings else "failed"
    return {
        "generated_at": dt.datetime.now().isoformat(timespec="seconds"),
        "simulation_dir": str(simulation_dir),
        "status": status,
        "counts": {
            "total_decisions": total_rows,
            "simulated_approved_decisions": approved_rows,
            "simulation_marker_rows": marker_rows,
            "manual_clause_review_items": file_counts.get("manual_clause_review", 0),
            "record_template_review_items": file_counts.get("record_template_review", 0),
            "attachment_disposition_items": file_counts.get("attachment_disposition", 0),
            "preapply_gates": file_counts.get("preapply_gate_register", 0),
            "findings": len(findings),
        },
        "findings": findings,
    }


def render_markdown(result: dict[str, Any]) -> str:
    lines = [
        "# 人审通过模拟包 dry-run 验证报告",
        "",
        f"生成时间：{result['generated_at']}",
        f"验证对象：`{result['simulation_dir']}`",
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
        lines.append("未发现结构性问题。该结论只证明模拟人审包可用于 apply-rehearsal 非写库演练；不代表真实人工评审、批准、发布或写库授权。")
    lines.append("")
    return "\n".join(lines)


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--simulation-dir", required=True)
    parser.add_argument("--json-out")
    parser.add_argument("--md-out")
    args = parser.parse_args()

    result = check_simulation_pack(Path(args.simulation_dir))
    print(json.dumps(result, ensure_ascii=False, indent=2))
    if args.json_out:
        Path(args.json_out).write_text(json.dumps(result, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
    if args.md_out:
        Path(args.md_out).write_text(render_markdown(result), encoding="utf-8")
    return 0 if result["status"] == "passed" else 1


if __name__ == "__main__":
    raise SystemExit(main())
