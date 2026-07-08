#!/usr/bin/env python3
"""Dry-run validation for the governance closure decision preview package."""

from __future__ import annotations

import argparse
import csv
import datetime as dt
import json
import re
from pathlib import Path
from typing import Any


REQUIRED_FILES = {
    "manifest": "governance_closure_decision_preview_manifest.json",
    "overview": "00-治理关闭意见回填预览总览.md",
    "decision_preview": "01-拟关闭决策预览.csv",
    "blocking_items": "02-仍阻断关闭项.csv",
    "gate_summary": "03-按闸门关闭统计.csv",
    "readme": "README.md",
}

REQUIRED_GUARDRAILS = [
    "不写数据库",
    "不修改 governance_closure_workbench",
    "不代表人工评审通过",
    "不代表真实培训完成",
    "不代表受控发布",
    "已取得 CMA",
    "CNAS 申请中",
    "2022 程序清单",
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
            "findings": [{"id": "missing_manifest", "severity": "high", "message": "缺少 governance_closure_decision_preview_manifest.json"}],
        }

    manifest = json.loads(manifest_path.read_text(encoding="utf-8"))
    if manifest.get("status") != "governance_closure_decision_preview_no_database_write":
        fail(findings, "invalid_manifest_status", "manifest 状态必须为 governance_closure_decision_preview_no_database_write。")

    guardrail_text = "\n".join(str(item) for item in manifest.get("guardrails", []))
    for marker in REQUIRED_GUARDRAILS:
        if marker not in guardrail_text:
            fail(findings, "manifest_missing_guardrail", "manifest 缺少边界标识：" + marker)

    files = manifest.get("files", {})
    for key, filename in REQUIRED_FILES.items():
        actual = files.get(key, filename)
        if not (preview_dir / actual).exists():
            fail(findings, "missing_" + key, "缺少治理关闭预览文件：" + actual)

    for path in list(preview_dir.rglob("*.sql")) + list(preview_dir.rglob("*.db")):
        fail(findings, "forbidden_database_artifact", "治理关闭预览包不应包含数据库/SQL 文件：" + path.name)

    preview_rows: list[dict[str, str]] = []
    blocking_rows: list[dict[str, str]] = []
    summary_rows: list[dict[str, str]] = []
    if (preview_dir / files.get("decision_preview", REQUIRED_FILES["decision_preview"])).exists():
        preview_rows = read_csv(preview_dir / files.get("decision_preview", REQUIRED_FILES["decision_preview"]))
    if (preview_dir / files.get("blocking_items", REQUIRED_FILES["blocking_items"])).exists():
        blocking_rows = read_csv(preview_dir / files.get("blocking_items", REQUIRED_FILES["blocking_items"]))
    if (preview_dir / files.get("gate_summary", REQUIRED_FILES["gate_summary"])).exists():
        summary_rows = read_csv(preview_dir / files.get("gate_summary", REQUIRED_FILES["gate_summary"]))

    preview_ids: set[str] = set()
    accepted = 0
    proposed = 0
    not_proposed = 0
    invalid = 0
    missing_required = 0
    blocking = 0
    for index, row in enumerate(preview_rows, start=2):
        closure_id = row.get("closure_item_id", "").strip()
        if not closure_id:
            fail(findings, "blank_closure_item_id", f"拟关闭决策预览第 {index} 行 closure_item_id 为空。")
        elif closure_id in preview_ids:
            fail(findings, "duplicate_closure_item_id", f"拟关闭决策预览存在重复 closure_item_id：{closure_id}")
        preview_ids.add(closure_id)
        if row.get("not_imported") != "yes":
            fail(findings, "not_imported_marker_missing", f"{closure_id or index} 必须保留 not_imported=yes。")
        if row.get("will_remain_blocking") not in {"yes", "no"}:
            fail(findings, "blocking_flag_invalid", f"{closure_id or index} will_remain_blocking 必须为 yes/no。")

        result = row.get("preview_result", "")
        if row.get("proposed_closure_status", "").strip():
            proposed += 1
        if result == "not_proposed":
            not_proposed += 1
        elif result == "accepted_for_preview":
            accepted += 1
            missing = [
                field
                for field in [
                    "closure_evidence_reference",
                    "evidence_template_reference",
                    "evidence_owner",
                    "evidence_date",
                    "evidence_result",
                    "closure_comment",
                    "reviewer",
                    "review_date",
                ]
                if not row.get(field, "").strip()
            ]
            if missing:
                fail(findings, "accepted_preview_missing_evidence", f"{closure_id or index} 已接受但缺少字段：" + "、".join(missing))
        elif result in {"invalid_closure_status", "rejected_or_reopened"}:
            invalid += 1
        elif result == "missing_required_fields":
            missing_required += 1
        else:
            fail(findings, "unknown_preview_result", f"{closure_id or index} preview_result 未识别：{result}")
        if row.get("will_remain_blocking") == "yes":
            blocking += 1

    blocking_ids = {row.get("closure_item_id", "").strip() for row in blocking_rows}
    expected_blocking_ids = {row.get("closure_item_id", "").strip() for row in preview_rows if row.get("will_remain_blocking") == "yes"}
    if blocking_ids != expected_blocking_ids:
        fail(findings, "blocking_register_mismatch", "仍阻断关闭项清单与拟关闭决策预览中的 will_remain_blocking=yes 不一致。")

    summary_blocking = sum(int_count(row.get("blocking_items")) for row in summary_rows)
    if summary_rows and summary_blocking != blocking:
        fail(findings, "gate_summary_blocking_mismatch", f"按闸门统计 blocking_items={summary_blocking}，实际 {blocking}。")

    counts = manifest.get("counts", {})
    actual_counts = {
        "decision_items": len(preview_rows),
        "proposed_closures": proposed,
        "not_proposed": not_proposed,
        "accepted_for_preview": accepted,
        "invalid_closures": invalid,
        "missing_required_fields": missing_required,
        "blocking_items": blocking,
        "database_write_performed": int_count(counts.get("database_write_performed")),
    }
    for key, actual in actual_counts.items():
        if int_count(counts.get(key)) != actual:
            fail(findings, "manifest_count_mismatch_" + key, f"manifest {key}={counts.get(key)}，实际 {actual}。")

    if actual_counts["database_write_performed"] != 0:
        fail(findings, "database_write_not_zero", "治理关闭预览包 database_write_performed 必须为 0。")
    if actual_counts["blocking_items"] > 0 and manifest.get("ready_for_governance_readiness_refresh") != "no":
        fail(findings, "ready_refresh_flag_conflicts_with_blocking_items", "仍有阻断项时 ready_for_governance_readiness_refresh 必须为 no。")
    if manifest.get("ready_for_lims_apply") == "yes":
        fail(findings, "preview_cannot_authorize_lims_apply", "治理关闭预览包不能单独声明 ready_for_lims_apply=yes。")

    for key in ["overview", "readme"]:
        path = preview_dir / files.get(key, REQUIRED_FILES[key])
        if not path.exists():
            continue
        text = path.read_text(encoding="utf-8")
        if CNAS_OVERSTATEMENT_RE.search(text):
            fail(findings, "doc_overstates_cnas", f"{path.name} 疑似包含已取得 CNAS 的越权表述。")
        for marker in ["不写数据库", "不代表人工评审通过", "不写入质量手册正文"]:
            if marker not in text:
                fail(findings, "doc_missing_guardrail", f"{path.name} 缺少边界标识：{marker}")

    status = "passed" if not findings else "failed"
    return {
        "generated_at": dt.datetime.now().isoformat(timespec="seconds"),
        "preview_dir": str(preview_dir),
        "status": status,
        "readiness": manifest.get("readiness", ""),
        "ready_for_governance_readiness_refresh": manifest.get("ready_for_governance_readiness_refresh", ""),
        "ready_for_lims_apply": manifest.get("ready_for_lims_apply", ""),
        "counts": {**actual_counts, "findings": len(findings)},
        "findings": findings,
    }


def render_markdown(result: dict[str, Any]) -> str:
    lines = [
        "# QMS 治理关闭意见回填预览 dry-run 验证报告",
        "",
        f"生成时间：{result['generated_at']}",
        f"治理关闭预览包：`{result['preview_dir']}`",
        f"结论：{result['status']}",
        f"readiness：{result.get('readiness', '')}",
        f"ready_for_governance_readiness_refresh：{result.get('ready_for_governance_readiness_refresh', '')}",
        f"ready_for_lims_apply：{result.get('ready_for_lims_apply', '')}",
        "",
        "## 计数",
        "",
    ]
    for key, value in result.get("counts", {}).items():
        lines.append(f"- {key}: {value}")
    lines.append("")
    if result.get("findings"):
        lines.extend(["## 发现项", ""])
        for finding in result["findings"]:
            lines.append(f"- [{finding['severity']}] {finding['id']}：{finding['message']}")
    else:
        lines.extend(
            [
                "## 发现项",
                "",
                "未发现结构性问题。该结论不代表已人工评审通过、已完成真实培训、已受控发布或已授权写库。",
            ]
        )
    lines.append("")
    return "\n".join(lines)


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--preview-dir", required=True)
    parser.add_argument("--json-out")
    parser.add_argument("--md-out")
    args = parser.parse_args()

    result = check_preview(Path(args.preview_dir))
    if args.json_out:
        Path(args.json_out).write_text(json.dumps(result, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
    if args.md_out:
        Path(args.md_out).write_text(render_markdown(result), encoding="utf-8")
    print(json.dumps(result, ensure_ascii=False, indent=2))
    return 0 if result["status"] == "passed" else 1


if __name__ == "__main__":
    raise SystemExit(main())
