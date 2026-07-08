#!/usr/bin/env python3
"""Validate the stage2 structured import human review workbench."""

from __future__ import annotations

import argparse
import csv
import datetime as dt
import json
import re
from pathlib import Path
from typing import Any


REQUIRED_FILES = {
    "manifest": "stage2_review_workbench_manifest.json",
    "overview": "00-第二阶段结构化导入人工复核总览.md",
    "block_review_matrix": "01-手册块复核矩阵.csv",
    "link_review_matrix": "02-块级链接复核矩阵.csv",
    "clause_target_summary": "03-按条款目标统计.csv",
    "target_backreference": "04-目标文件记录反查清单.csv",
    "decision_template": "05-人工复核意见回填模板.csv",
    "readme": "README.md",
}

EXPECTED_COUNTS = {
    "block_review_rows": 29,
    "link_review_rows": 125,
    "clause_summary_rows": 29,
    "decision_rows": 154,
    "pending_decisions": 154,
    "procedure_document_links": 93,
    "attachment_form_document_links": 2,
    "record_form_template_links": 30,
    "unresolved_targets": 0,
    "database_write_performed": 0,
}

REQUIRED_GUARDRAILS = [
    "不写数据库",
    "不代表第二阶段已导入",
    "不代表人工评审通过",
    "pending",
    "已取得 CMA",
    "CNAS 申请中",
    "jewelry-qms 仍为建设中系统",
    "不写入质量手册正文",
]

CNAS_OVERSTATEMENT_RE = re.compile(
    r"(本公司|公司|实验室|机构).{0,12}(已取得|已获|获得)\s*CNAS|CNAS\s*认可证书"
)


def read_csv(path: Path) -> list[dict[str, str]]:
    with path.open("r", encoding="utf-8-sig", newline="") as handle:
        return list(csv.DictReader(handle))


def fail(findings: list[dict[str, str]], rule_id: str, message: str, severity: str = "high") -> None:
    findings.append({"id": rule_id, "severity": severity, "message": message})


def check_count(manifest_counts: dict[str, Any], key: str, actual: int, findings: list[dict[str, str]]) -> None:
    try:
        expected = int(manifest_counts.get(key, -1))
    except (TypeError, ValueError):
        expected = -1
    if expected != actual:
        fail(findings, "manifest_count_mismatch_" + key, f"manifest {key}={manifest_counts.get(key)}，实际 {actual}。")


def count_value(rows: list[dict[str, str]], field: str, value: str) -> int:
    return sum(1 for row in rows if row.get(field) == value)


def check_pending(rows: list[dict[str, str]], label: str, findings: list[dict[str, str]]) -> int:
    pending = 0
    for index, row in enumerate(rows, start=2):
        if row.get("human_decision") == "pending":
            pending += 1
        else:
            fail(findings, "human_decision_not_pending", f"{label} 第 {index} 行 human_decision 必须保持 pending。")
        if row.get("review_comment", "") != "":
            fail(findings, "review_comment_prefilled", f"{label} 第 {index} 行 review_comment 不得预填。")
        if row.get("blocking_if_unresolved") != "yes":
            fail(findings, "row_not_blocking", f"{label} 第 {index} 行 blocking_if_unresolved 必须为 yes。")
    return pending


def check_workbench(workbench_dir: Path) -> dict[str, Any]:
    findings: list[dict[str, str]] = []
    manifest_path = workbench_dir / REQUIRED_FILES["manifest"]
    if not manifest_path.exists():
        return {
            "generated_at": dt.datetime.now().isoformat(timespec="seconds"),
            "workbench_dir": str(workbench_dir),
            "status": "failed",
            "findings": [{"id": "missing_manifest", "severity": "high", "message": "缺少 stage2_review_workbench_manifest.json"}],
        }

    manifest = json.loads(manifest_path.read_text(encoding="utf-8"))
    if manifest.get("status") != "stage2_structured_review_workbench_no_database_write":
        fail(findings, "invalid_manifest_status", "manifest 状态必须为 stage2_structured_review_workbench_no_database_write。")
    guardrail_text = "\n".join(str(item) for item in manifest.get("guardrails", []))
    for marker in REQUIRED_GUARDRAILS:
        if marker not in guardrail_text:
            fail(findings, "manifest_missing_guardrail", "manifest 缺少边界标识：" + marker)

    files = manifest.get("files", {})
    for key, filename in REQUIRED_FILES.items():
        actual = files.get(key, filename)
        if not (workbench_dir / actual).exists():
            fail(findings, "missing_" + key, "缺少复核工作台文件：" + actual)

    for path in list(workbench_dir.rglob("*.sql")) + list(workbench_dir.rglob("*.db")):
        fail(findings, "forbidden_database_artifact", "复核工作台不应包含数据库/SQL 文件：" + path.name)

    block_rows = read_csv(workbench_dir / files.get("block_review_matrix", REQUIRED_FILES["block_review_matrix"]))
    link_rows = read_csv(workbench_dir / files.get("link_review_matrix", REQUIRED_FILES["link_review_matrix"]))
    summary_rows = read_csv(workbench_dir / files.get("clause_target_summary", REQUIRED_FILES["clause_target_summary"]))
    target_rows = read_csv(workbench_dir / files.get("target_backreference", REQUIRED_FILES["target_backreference"]))
    decision_rows = read_csv(workbench_dir / files.get("decision_template", REQUIRED_FILES["decision_template"]))

    block_pending = check_pending(block_rows, "手册块复核矩阵", findings)
    link_pending = check_pending(link_rows, "块级链接复核矩阵", findings)
    summary_pending = check_pending(summary_rows, "按条款目标统计", findings)
    target_pending = check_pending(target_rows, "目标文件记录反查清单", findings)

    decision_ids = set()
    pending_decisions = 0
    for index, row in enumerate(decision_rows, start=2):
        decision_id = row.get("decision_item_id", "")
        if not decision_id:
            fail(findings, "decision_id_blank", f"人工复核意见回填模板第 {index} 行 decision_item_id 为空。")
        if decision_id in decision_ids:
            fail(findings, "decision_id_duplicate", f"人工复核意见回填模板 decision_item_id 重复：{decision_id}")
        decision_ids.add(decision_id)
        if row.get("proposed_human_decision", "") != "":
            fail(findings, "proposed_decision_prefilled", f"{decision_id} proposed_human_decision 不得预填。")
        if row.get("review_comment", "") != "":
            fail(findings, "decision_review_comment_prefilled", f"{decision_id} review_comment 不得预填。")
        if row.get("blocking_if_unresolved") != "yes":
            fail(findings, "decision_not_blocking", f"{decision_id} blocking_if_unresolved 必须为 yes。")
        if row.get("not_imported") != "yes":
            fail(findings, "decision_not_marked_not_imported", f"{decision_id} 必须标记 not_imported=yes。")
        pending_decisions += 1

    unresolved_targets = sum(1 for row in link_rows if row.get("target_id_resolution") in {"", "not_resolved_before_apply"})
    actual_counts = {
        "block_review_rows": len(block_rows),
        "link_review_rows": len(link_rows),
        "clause_summary_rows": len(summary_rows),
        "target_backreference_rows": len(target_rows),
        "decision_rows": len(decision_rows),
        "pending_decisions": pending_decisions,
        "procedure_document_links": count_value(link_rows, "target_type", "procedure_document"),
        "attachment_form_document_links": count_value(link_rows, "target_type", "attachment_form_document"),
        "record_form_template_links": count_value(link_rows, "target_type", "record_form_template"),
        "unresolved_targets": unresolved_targets,
        "database_write_performed": int(manifest.get("counts", {}).get("database_write_performed", -1)),
    }
    manifest_counts = manifest.get("counts", {})
    for key, actual in actual_counts.items():
        check_count(manifest_counts, key, actual, findings)
    for key, expected in EXPECTED_COUNTS.items():
        if actual_counts.get(key) != expected:
            fail(findings, "expected_count_drift_" + key, f"{key} 应为 {expected}，实际 {actual_counts.get(key)}。")

    block_decision_ids = {row.get("review_item_id") for row in block_rows}
    link_decision_ids = {row.get("review_item_id") for row in link_rows}
    decision_template_ids = {row.get("decision_item_id") for row in decision_rows}
    missing_decisions = (block_decision_ids | link_decision_ids) - decision_template_ids
    extra_decisions = decision_template_ids - (block_decision_ids | link_decision_ids)
    if missing_decisions:
        fail(findings, "decision_template_missing_items", "人工复核意见回填模板缺少决策项：" + "、".join(sorted(missing_decisions)[:12]))
    if extra_decisions:
        fail(findings, "decision_template_extra_items", "人工复核意见回填模板存在额外决策项：" + "、".join(sorted(extra_decisions)[:12]))

    for key in ["overview", "readme"]:
        path = workbench_dir / files.get(key, REQUIRED_FILES[key])
        if not path.exists():
            continue
        text = path.read_text(encoding="utf-8")
        for marker in ["不写数据库", "不代表第二阶段已导入", "不代表人工评审通过", "CNAS 申请中"]:
            if marker not in text:
                fail(findings, "doc_missing_guardrail", f"{path.name} 缺少边界标识：{marker}")
        if CNAS_OVERSTATEMENT_RE.search(text):
            fail(findings, "doc_overstates_cnas", f"{path.name} 疑似包含已取得 CNAS 的越权表述。")

    status = "passed" if not findings else "failed"
    return {
        "generated_at": dt.datetime.now().isoformat(timespec="seconds"),
        "workbench_dir": str(workbench_dir),
        "status": status,
        "counts": {
            **actual_counts,
            "block_review_pending": block_pending,
            "link_review_pending": link_pending,
            "summary_review_pending": summary_pending,
            "target_review_pending": target_pending,
            "findings": len(findings),
        },
        "findings": findings,
    }


def render_markdown(result: dict[str, Any]) -> str:
    lines = [
        "# 第二阶段结构化导入人工复核工作台 dry-run 验证报告",
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
        lines.append("未发现结构性问题。该结论只证明复核工作台可读、计数一致且边界明确；不代表真实人工评审、受控发布或正式写库授权。")
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
