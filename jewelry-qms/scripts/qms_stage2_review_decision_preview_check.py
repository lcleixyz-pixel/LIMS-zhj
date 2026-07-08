#!/usr/bin/env python3
"""Dry-run validation for the stage2 structured review decision preview package."""

from __future__ import annotations

import argparse
import csv
import datetime as dt
import json
import re
from pathlib import Path
from typing import Any


REQUIRED_FILES = {
    "manifest": "stage2_review_decision_preview_manifest.json",
    "overview": "00-第二阶段结构化复核意见回填预览总览.md",
    "decision_preview": "01-拟回填决策预览.csv",
    "blocking_items": "02-仍阻断项清单.csv",
    "scope_summary": "03-按范围统计.csv",
    "readme": "README.md",
}

REQUIRED_GUARDRAILS = [
    "不写数据库",
    "不修改 stage2_structured_review_workbench",
    "不代表第二阶段已导入",
    "不代表人工评审通过",
    "已取得 CMA",
    "CNAS 申请中",
    "jewelry-qms 仍为建设中系统",
    "不写入质量手册正文",
]

EXPECTED_DEFAULT_COUNTS = {
    "decision_rows": 154,
    "proposed_decisions": 0,
    "not_proposed": 154,
    "pending_decisions": 0,
    "accepted_for_preview": 0,
    "invalid_decisions": 0,
    "missing_review_comments": 0,
    "blocking_items": 154,
    "database_write_performed": 0,
}

CNAS_OVERSTATEMENT_RE = re.compile(
    r"(本公司|公司|实验室|机构).{0,12}(已取得|已获|获得)\s*CNAS|CNAS\s*认可证书"
)


def read_csv(path: Path) -> list[dict[str, str]]:
    with path.open("r", encoding="utf-8-sig", newline="") as handle:
        return list(csv.DictReader(handle))


def fail(findings: list[dict[str, str]], rule_id: str, message: str, severity: str = "high") -> None:
    findings.append({"id": rule_id, "severity": severity, "message": message})


def int_count(value: Any) -> int:
    try:
        return int(value)
    except (TypeError, ValueError):
        return -1


def check_preview(preview_dir: Path) -> dict[str, Any]:
    findings: list[dict[str, str]] = []
    manifest_path = preview_dir / REQUIRED_FILES["manifest"]
    if not manifest_path.exists():
        return {
            "generated_at": dt.datetime.now().isoformat(timespec="seconds"),
            "preview_dir": str(preview_dir),
            "status": "failed",
            "findings": [{"id": "missing_manifest", "severity": "high", "message": "缺少 stage2_review_decision_preview_manifest.json"}],
        }

    manifest = json.loads(manifest_path.read_text(encoding="utf-8"))
    if manifest.get("status") != "stage2_review_decision_preview_no_database_write":
        fail(findings, "invalid_manifest_status", "manifest 状态必须为 stage2_review_decision_preview_no_database_write。")

    guardrail_text = "\n".join(str(item) for item in manifest.get("guardrails", []))
    for marker in REQUIRED_GUARDRAILS:
        if marker not in guardrail_text:
            fail(findings, "manifest_missing_guardrail", "manifest 缺少边界标识：" + marker)

    files = manifest.get("files", {})
    for key, filename in REQUIRED_FILES.items():
        actual = files.get(key, filename)
        if not (preview_dir / actual).exists():
            fail(findings, "missing_" + key, "缺少预览文件：" + actual)

    for path in list(preview_dir.rglob("*.sql")) + list(preview_dir.rglob("*.db")):
        fail(findings, "forbidden_database_artifact", "预览包不应包含数据库/SQL 文件：" + path.name)

    decision_rows: list[dict[str, str]] = []
    blocking_rows: list[dict[str, str]] = []
    summary_rows: list[dict[str, str]] = []
    decision_path = preview_dir / files.get("decision_preview", REQUIRED_FILES["decision_preview"])
    blocking_path = preview_dir / files.get("blocking_items", REQUIRED_FILES["blocking_items"])
    summary_path = preview_dir / files.get("scope_summary", REQUIRED_FILES["scope_summary"])
    if decision_path.exists():
        decision_rows = read_csv(decision_path)
    if blocking_path.exists():
        blocking_rows = read_csv(blocking_path)
    if summary_path.exists():
        summary_rows = read_csv(summary_path)

    counts = manifest.get("counts", {})
    actual_counts = {
        "decision_rows": len(decision_rows),
        "proposed_decisions": sum(1 for row in decision_rows if row.get("proposed_human_decision", "") != ""),
        "not_proposed": sum(1 for row in decision_rows if row.get("preview_result") == "not_proposed"),
        "pending_decisions": sum(1 for row in decision_rows if row.get("preview_result") == "pending"),
        "accepted_for_preview": sum(1 for row in decision_rows if row.get("preview_result") == "accepted_for_preview"),
        "invalid_decisions": sum(1 for row in decision_rows if row.get("preview_result") == "invalid_decision"),
        "missing_review_comments": sum(1 for row in decision_rows if row.get("preview_result") == "missing_review_comment"),
        "blocking_items": sum(1 for row in decision_rows if row.get("will_remain_blocking") == "yes"),
        "database_write_performed": int_count(counts.get("database_write_performed")),
    }
    for key, actual in actual_counts.items():
        if int_count(counts.get(key)) != actual:
            fail(findings, "manifest_count_mismatch_" + key, f"manifest {key}={counts.get(key)}，实际 {actual}。")

    if len(blocking_rows) != actual_counts["blocking_items"]:
        fail(findings, "blocking_csv_count_mismatch", f"仍阻断项清单应为 {actual_counts['blocking_items']} 行，实际 {len(blocking_rows)}。")

    decision_ids: set[str] = set()
    for index, row in enumerate(decision_rows, start=2):
        decision_id = row.get("decision_item_id", "")
        if not decision_id:
            fail(findings, "blank_decision_item_id", f"拟回填决策预览第 {index} 行 decision_item_id 为空。")
        if decision_id in decision_ids:
            fail(findings, "duplicate_decision_item_id", f"重复 decision_item_id：{decision_id}")
        decision_ids.add(decision_id)
        if row.get("not_imported") != "yes":
            fail(findings, "row_not_marked_not_imported", f"{decision_id} 必须保留 not_imported=yes。")
        if row.get("preview_result") == "accepted_for_preview" and not row.get("review_comment", "").strip():
            fail(findings, "accepted_without_comment", f"{decision_id} 被视为可预览回填但缺少 review_comment。")

    if actual_counts == EXPECTED_DEFAULT_COUNTS and manifest.get("readiness") != "no_proposed_decisions":
        fail(findings, "default_readiness_mismatch", "当前全空白默认状态应为 no_proposed_decisions。")
    if actual_counts["proposed_decisions"] == 0 and actual_counts["blocking_items"] != actual_counts["decision_rows"]:
        fail(findings, "blank_decisions_not_blocking", "无拟决策时所有决策项都应保持阻断。")

    summary_total = sum(int_count(row.get("decision_rows")) for row in summary_rows)
    if summary_rows and summary_total != actual_counts["decision_rows"]:
        fail(findings, "summary_count_mismatch", f"范围统计合计 {summary_total}，拟回填决策预览 {actual_counts['decision_rows']}。")

    for key in ["overview", "readme"]:
        path = preview_dir / files.get(key, REQUIRED_FILES[key])
        if not path.exists():
            continue
        text = path.read_text(encoding="utf-8")
        for marker in ["不写数据库", "不修改 `stage2_structured_review_workbench/`", "不代表人工评审通过", "CNAS 申请中"]:
            if marker not in text:
                fail(findings, "doc_missing_guardrail", f"{path.name} 缺少边界标识：{marker}")
        if CNAS_OVERSTATEMENT_RE.search(text):
            fail(findings, "doc_overstates_cnas", f"{path.name} 疑似包含已取得 CNAS 的越权表述。")

    status = "passed" if not findings else "failed"
    return {
        "generated_at": dt.datetime.now().isoformat(timespec="seconds"),
        "preview_dir": str(preview_dir),
        "status": status,
        "readiness": manifest.get("readiness", ""),
        "counts": {**actual_counts, "scope_summary_rows": len(summary_rows), "findings": len(findings)},
        "findings": findings,
    }


def render_markdown(result: dict[str, Any]) -> str:
    lines = [
        "# 第二阶段结构化复核意见回填预览 dry-run 验证报告",
        "",
        f"生成时间：{result['generated_at']}",
        f"验证对象：`{result['preview_dir']}`",
        f"结论：{result['status']}",
        f"预览状态：{result.get('readiness', '')}",
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
        lines.append("未发现结构性问题。该结论只证明复核意见回填预览可检查且边界明确；不代表人工评审通过、第二阶段已导入或已写入 LIMS。")
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
