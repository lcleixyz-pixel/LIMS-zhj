#!/usr/bin/env python3
"""Dry-run validation for the QMS human-review pack."""

from __future__ import annotations

import argparse
import csv
import datetime as dt
import json
import re
from pathlib import Path
from typing import Any


EXPECTED_CLAUSES = {
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
}

EXPECTED_REQUIRED_GATES = {
    "GATE-01",
    "GATE-02",
    "GATE-03",
    "GATE-04",
    "GATE-05",
    "GATE-06",
    "GATE-07",
    "GATE-08",
    "GATE-09",
    "GATE-10",
    "GATE-11",
}


def read_text(path: Path) -> str:
    return path.read_text(encoding="utf-8")


def read_csv(path: Path) -> list[dict[str, str]]:
    with path.open("r", encoding="utf-8", newline="") as handle:
        return list(csv.DictReader(handle))


def fail(findings: list[dict[str, str]], rule_id: str, message: str, severity: str = "high") -> None:
    findings.append({"id": rule_id, "severity": severity, "message": message})


def load_rows(review_dir: Path, filename: str, findings: list[dict[str, str]], label: str) -> list[dict[str, str]]:
    path = review_dir / filename
    if not path.exists():
        fail(findings, "missing_" + label, f"缺少文件：{filename}")
        return []
    return read_csv(path)


def check_pending(rows: list[dict[str, str]], findings: list[dict[str, str]], label: str) -> None:
    for index, row in enumerate(rows, start=2):
        decision = row.get("human_decision", "")
        if decision != "pending":
            fail(findings, f"{label}_decision_not_pending", f"{label} 第 {index} 行 human_decision 必须为 pending，实际为 {decision}")
        if row.get("blocking_if_unresolved") != "yes":
            fail(findings, f"{label}_not_blocking", f"{label} 第 {index} 行未标明 unresolved 时阻断 apply。", "medium")


def check_no_approval_text(review_dir: Path, findings: list[dict[str, str]]) -> None:
    patterns = [
        r"已批准",
        r"批准发布",
        r"准许写库",
        r"可以写库",
        r"human_decision[,， ]*(approved|pass)",
    ]
    for path in review_dir.glob("*"):
        if not path.is_file() or path.suffix.lower() not in {".md", ".csv", ".json"}:
            continue
        text = read_text(path)
        for pattern in patterns:
            if re.search(pattern, text, flags=re.IGNORECASE):
                fail(findings, "approval_language_present", f"{path.name} 疑似包含批准或准许写库表述：{pattern}")


def check_review_pack(review_dir: Path, preimport_dir: Path | None = None) -> dict[str, Any]:
    findings: list[dict[str, str]] = []
    manifest_path = review_dir / "human_review_manifest.json"
    if not manifest_path.exists():
        return {
            "generated_at": dt.datetime.now().isoformat(timespec="seconds"),
            "review_dir": str(review_dir),
            "status": "failed",
            "findings": [{"id": "missing_manifest", "severity": "high", "message": "缺少 human_review_manifest.json"}],
        }
    manifest = json.loads(read_text(manifest_path))
    if manifest.get("status") != "human_review_required_no_database_write":
        fail(findings, "manifest_status_invalid", "human_review_manifest.json 状态必须为 human_review_required_no_database_write。")
    boundary_text = "\n".join(manifest.get("boundary", []))
    if "不写数据库" not in boundary_text or "不得 apply" not in boundary_text:
        fail(findings, "manifest_missing_boundary", "manifest 未清楚声明不写数据库和不得 apply。")

    files = manifest.get("files", {})
    clause_rows = load_rows(review_dir, files.get("manual_clause_review", "manual_clause_review_checklist.csv"), findings, "manual_clause_review")
    template_rows = load_rows(review_dir, files.get("record_template_review", "record_template_review_checklist.csv"), findings, "record_template_review")
    attachment_rows = load_rows(review_dir, files.get("attachment_disposition", "attachment_form_disposition.csv"), findings, "attachment_disposition")
    gate_rows = load_rows(review_dir, files.get("preapply_gate_register", "preapply_gate_register.csv"), findings, "preapply_gate_register")

    counts = manifest.get("counts", {})
    expected_counts = {
        "manual_clause_review_items": (clause_rows, 29),
        "record_template_review_items": (template_rows, 26),
        "attachment_disposition_items": (attachment_rows, 1),
        "preapply_gates": (gate_rows, 11),
    }
    for key, (rows, minimum) in expected_counts.items():
        manifest_value = int(counts.get(key, -1))
        if manifest_value != len(rows):
            fail(findings, "count_mismatch_" + key, f"{key} 计数不一致：manifest={manifest_value}, csv={len(rows)}")
        if len(rows) < minimum:
            fail(findings, "count_too_small_" + key, f"{key} 不应少于 {minimum}，实际 {len(rows)}")

    clauses = {row.get("clause", "") for row in clause_rows}
    missing_clauses = sorted(EXPECTED_CLAUSES - clauses)
    if missing_clauses:
        fail(findings, "manual_review_missing_clauses", "条款评审清单缺少：" + "、".join(missing_clauses))
    check_pending(clause_rows, findings, "manual_clause_review")
    check_pending(template_rows, findings, "record_template_review")
    check_pending(attachment_rows, findings, "attachment_disposition")
    check_pending(gate_rows, findings, "preapply_gate_register")

    attachment_codes = {row.get("doc_number", "") for row in attachment_rows}
    if "XZTC/CX-05-02-2022" not in attachment_codes:
        fail(findings, "missing_0502_disposition", "编号附件/表单归属判定缺少 XZTC/CX-05-02-2022。")
    for row in attachment_rows:
        if row.get("recommended_disposition") != "pending_human_review":
            fail(findings, "attachment_recommendation_not_pending", f"{row.get('doc_number')} 推荐处置必须保持 pending_human_review。")
        if not row.get("disposition_options"):
            fail(findings, "attachment_missing_options", f"{row.get('doc_number')} 缺少处置选项。")

    gate_ids = {row.get("gate_id", "") for row in gate_rows}
    missing_gates = sorted(EXPECTED_REQUIRED_GATES - gate_ids)
    if missing_gates:
        fail(findings, "missing_preapply_gates", "apply 前闸门缺少：" + "、".join(missing_gates))
    for row in gate_rows:
        if row.get("required_before_apply") != "yes":
            fail(findings, "gate_not_required_before_apply", f"{row.get('gate_id')} 未标明 apply 前必须处理。")

    for row in template_rows:
        if row.get("needs_retention_period") != "yes" or row.get("needs_confidentiality_level") != "yes":
            fail(findings, "template_missing_retention_confidentiality_gate", f"{row.get('doc_number')} 未保留保存期限/保密等级确认闸门。")
        if row.get("missing_common_schema_keys"):
            fail(findings, "template_missing_common_schema_keys", f"{row.get('doc_number')} 缺少通用 schema 字段：{row.get('missing_common_schema_keys')}")

    readme = review_dir / "README.md"
    guide = review_dir / files.get("review_guide", "人工评审操作说明.md")
    for path, label in [(readme, "README"), (guide, "review_guide")]:
        if not path.exists():
            fail(findings, "missing_" + label, f"缺少 {path.name}")
            continue
        text = read_text(path)
        if "不写数据库" not in text or "pending" not in text:
            fail(findings, label + "_missing_guardrail", f"{path.name} 缺少不写数据库或 pending 边界。")

    for path in list(review_dir.glob("*.sql")) + list(review_dir.glob("*.db")):
        fail(findings, "forbidden_database_artifact", f"人工评审包不应包含数据库/SQL 文件：{path.name}")

    if preimport_dir:
        template_source = preimport_dir / "record_form_templates_preimport.csv"
        trace_source = preimport_dir / "traceability_matrix_preimport.csv"
        if template_source.exists() and len(read_csv(template_source)) != len(template_rows):
            fail(findings, "template_source_count_mismatch", "记录模板评审清单与预导入模板数量不一致。")
        if trace_source.exists() and len(read_csv(trace_source)) != len(clause_rows):
            fail(findings, "traceability_source_count_mismatch", "条款评审清单与追溯矩阵数量不一致。")

    check_no_approval_text(review_dir, findings)

    status = "passed" if not findings else "failed"
    return {
        "generated_at": dt.datetime.now().isoformat(timespec="seconds"),
        "review_dir": str(review_dir),
        "preimport_dir": str(preimport_dir) if preimport_dir else None,
        "status": status,
        "counts": {
            "manual_clause_review_items": len(clause_rows),
            "record_template_review_items": len(template_rows),
            "attachment_disposition_items": len(attachment_rows),
            "preapply_gates": len(gate_rows),
            "findings": len(findings),
        },
        "findings": findings,
    }


def render_markdown(result: dict[str, Any]) -> str:
    lines = [
        "# LIMS 人工评审包 dry-run 验证报告",
        "",
        f"生成时间：{result['generated_at']}",
        f"验证对象：`{result['review_dir']}`",
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
                "未发现阻断性问题。该结论仅证明人工评审包结构完整、所有人工决策仍为 pending、apply 前闸门未被绕过；不代表已经人工批准或写入 LIMS。",
            ]
        )
    lines.append("")
    return "\n".join(lines)


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--review-dir", required=True)
    parser.add_argument("--preimport-dir")
    parser.add_argument("--json-out")
    parser.add_argument("--md-out")
    args = parser.parse_args()

    result = check_review_pack(
        Path(args.review_dir),
        Path(args.preimport_dir) if args.preimport_dir else None,
    )
    print(json.dumps(result, ensure_ascii=False, indent=2))
    if args.json_out:
        Path(args.json_out).write_text(json.dumps(result, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
    if args.md_out:
        Path(args.md_out).write_text(render_markdown(result), encoding="utf-8")
    return 0 if result["status"] == "passed" else 1


if __name__ == "__main__":
    raise SystemExit(main())
