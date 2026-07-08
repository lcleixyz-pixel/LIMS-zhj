#!/usr/bin/env python3
"""Dry-run validation for the QMS governance closure workbench."""

from __future__ import annotations

import argparse
import csv
import datetime as dt
import json
import re
from pathlib import Path
from typing import Any


REQUIRED_FILES = {
    "manifest": "governance_closure_workbench_manifest.json",
    "overview": "00-治理关闭工作台总览.md",
    "gate_closure_matrix": "01-总闸门关闭矩阵.csv",
    "role_task_pack": "02-按角色任务包.csv",
    "evidence_template": "03-证据采集模板.csv",
    "closure_template": "04-拟关闭回填模板.csv",
    "priority_batches": "05-优先关闭批次.md",
    "readme": "README.md",
}

REQUIRED_GUARDRAILS = [
    "不写数据库",
    "不修改 governance_readiness_dashboard",
    "不代表人工评审通过",
    "不代表真实培训完成",
    "不代表受控发布",
    "已取得 CMA",
    "CNAS 申请中",
    "2022 程序清单",
    "jewelry-qms 仍为建设中系统",
    "不写入质量手册正文",
]

STATUS_ALIASES = {
    "": "pending",
    "pending": "pending",
    "open": "pending",
    "待确认": "pending",
    "待关闭": "pending",
    "closed": "closed",
    "close": "closed",
    "done": "closed",
    "resolved": "closed",
    "已关闭": "closed",
    "完成": "closed",
    "通过": "closed",
    "not_applicable": "not_applicable",
    "not-applicable": "not_applicable",
    "na": "not_applicable",
    "n/a": "not_applicable",
    "不适用": "not_applicable",
    "waived": "waived",
    "waive": "waived",
    "豁免": "waived",
    "rejected": "rejected",
    "reject": "rejected",
    "reopen": "rejected",
    "退回": "rejected",
}

TERMINAL_STATUSES = {"closed", "not_applicable", "waived"}
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


def normalize_status(value: str) -> str:
    raw = value.strip()
    key = raw.lower().replace(" ", "_")
    return STATUS_ALIASES.get(key) or STATUS_ALIASES.get(raw) or key


def validate_closure_row(row: dict[str, str]) -> tuple[str, bool, str]:
    status = normalize_status(row.get("proposed_closure_status", ""))
    blocks_apply = row.get("blocks_apply", "").strip() == "yes"
    issue = ""

    if status not in {"pending", "closed", "not_applicable", "waived", "rejected"}:
        return status, blocks_apply, "unknown_status"
    if status == "rejected":
        return status, blocks_apply, "rejected_or_reopened"
    if status == "pending":
        return status, blocks_apply, "pending"

    missing = [
        field
        for field in ["evidence_reference", "closure_comment", "reviewer", "review_date"]
        if not row.get(field, "").strip()
    ]
    if missing:
        issue = "missing_required_closure_fields:" + ",".join(missing)
        return status, True if blocks_apply else False, issue
    return status, False, ""


def check_workbench(workbench_dir: Path) -> dict[str, Any]:
    findings: list[dict[str, str]] = []
    manifest_path = workbench_dir / REQUIRED_FILES["manifest"]
    if not manifest_path.exists():
        return {
            "generated_at": dt.datetime.now().isoformat(timespec="seconds"),
            "workbench_dir": str(workbench_dir),
            "status": "failed",
            "findings": [{"id": "missing_manifest", "severity": "high", "message": "缺少 governance_closure_workbench_manifest.json"}],
        }

    manifest = json.loads(manifest_path.read_text(encoding="utf-8"))
    if manifest.get("status") != "governance_closure_workbench_no_database_write":
        fail(findings, "invalid_manifest_status", "manifest 状态必须为 governance_closure_workbench_no_database_write。")

    guardrail_text = "\n".join(str(item) for item in manifest.get("guardrails", []))
    for marker in REQUIRED_GUARDRAILS:
        if marker not in guardrail_text:
            fail(findings, "manifest_missing_guardrail", "manifest 缺少边界标识：" + marker)

    files = manifest.get("files", {})
    for key, filename in REQUIRED_FILES.items():
        actual = files.get(key, filename)
        if not (workbench_dir / actual).exists():
            fail(findings, "missing_" + key, "缺少治理关闭工作台文件：" + actual)

    for path in list(workbench_dir.rglob("*.sql")) + list(workbench_dir.rglob("*.db")):
        fail(findings, "forbidden_database_artifact", "治理关闭工作台不应包含数据库/SQL 文件：" + path.name)

    gate_rows: list[dict[str, str]] = []
    role_rows: list[dict[str, str]] = []
    evidence_rows: list[dict[str, str]] = []
    closure_rows: list[dict[str, str]] = []
    if (workbench_dir / files.get("gate_closure_matrix", REQUIRED_FILES["gate_closure_matrix"])).exists():
        gate_rows = read_csv(workbench_dir / files.get("gate_closure_matrix", REQUIRED_FILES["gate_closure_matrix"]))
    if (workbench_dir / files.get("role_task_pack", REQUIRED_FILES["role_task_pack"])).exists():
        role_rows = read_csv(workbench_dir / files.get("role_task_pack", REQUIRED_FILES["role_task_pack"]))
    if (workbench_dir / files.get("evidence_template", REQUIRED_FILES["evidence_template"])).exists():
        evidence_rows = read_csv(workbench_dir / files.get("evidence_template", REQUIRED_FILES["evidence_template"]))
    if (workbench_dir / files.get("closure_template", REQUIRED_FILES["closure_template"])).exists():
        closure_rows = read_csv(workbench_dir / files.get("closure_template", REQUIRED_FILES["closure_template"]))

    closure_ids: set[str] = set()
    evidence_ids = {row.get("closure_item_id", "") for row in evidence_rows}
    accepted_closures = 0
    pending_closures = 0
    open_blocking_items = 0
    invalid_closure_rows = 0

    for index, row in enumerate(closure_rows, start=2):
        closure_id = row.get("closure_item_id", "").strip()
        if not closure_id:
            fail(findings, "blank_closure_item_id", f"拟关闭回填模板第 {index} 行 closure_item_id 为空。")
        elif closure_id in closure_ids:
            fail(findings, "duplicate_closure_item_id", f"拟关闭回填模板存在重复 closure_item_id：{closure_id}")
        closure_ids.add(closure_id)
        if closure_id not in evidence_ids:
            fail(findings, "closure_missing_evidence_row", f"{closure_id} 缺少对应证据采集模板行。")
        if row.get("not_real_record") != "yes":
            fail(findings, "closure_not_real_marker_missing", f"{closure_id or index} 必须保留 not_real_record=yes。")
        if row.get("not_imported") != "yes":
            fail(findings, "closure_not_imported_marker_missing", f"{closure_id or index} 必须保留 not_imported=yes。")
        if row.get("blocks_apply") not in {"yes", "no"}:
            fail(findings, "closure_blocks_apply_invalid", f"{closure_id or index} blocks_apply 必须为 yes/no。")

        status, remains_blocking, issue = validate_closure_row(row)
        if status in TERMINAL_STATUSES and not issue:
            accepted_closures += 1
        elif status == "pending":
            pending_closures += 1
        if issue in {"unknown_status", "missing_required_closure_fields:review_date", "rejected_or_reopened"} or issue.startswith("missing_required_closure_fields"):
            invalid_closure_rows += 1
            if status != "pending":
                fail(findings, "invalid_closure_row", f"{closure_id or index} 拟关闭状态为 {status}，但存在问题：{issue}")
        if remains_blocking:
            open_blocking_items += 1

    for index, row in enumerate(evidence_rows, start=2):
        closure_id = row.get("closure_item_id", "").strip()
        if row.get("not_real_record") != "yes":
            fail(findings, "evidence_not_real_marker_missing", f"{closure_id or index} 证据采集行必须保留 not_real_record=yes。")
        if row.get("blocks_apply") not in {"yes", "no"}:
            fail(findings, "evidence_blocks_apply_invalid", f"{closure_id or index} blocks_apply 必须为 yes/no。")

    for index, row in enumerate(gate_rows, start=2):
        if row.get("not_real_record") != "yes":
            fail(findings, "gate_not_real_marker_missing", f"总闸门关闭矩阵第 {index} 行 not_real_record 必须为 yes。")

    counts = manifest.get("counts", {})
    actual_counts = {
        "gate_rows": len(gate_rows),
        "role_task_batches": len(role_rows),
        "evidence_rows": len(evidence_rows),
        "closure_rows": len(closure_rows),
        "blocking_closure_items": sum(1 for row in closure_rows if row.get("blocks_apply") == "yes"),
        "open_blocking_items": open_blocking_items,
        "accepted_closures": accepted_closures,
        "pending_closures": pending_closures,
        "database_write_performed": int_count(counts.get("database_write_performed")),
    }
    for key, actual in actual_counts.items():
        if int_count(counts.get(key)) != actual:
            fail(findings, "manifest_count_mismatch_" + key, f"manifest {key}={counts.get(key)}，实际 {actual}。")

    if actual_counts["database_write_performed"] != 0:
        fail(findings, "database_write_not_zero", "治理关闭工作台 database_write_performed 必须为 0。")
    if actual_counts["open_blocking_items"] > 0 and manifest.get("ready_for_governance_readiness_refresh") != "no":
        fail(findings, "ready_refresh_flag_conflicts_with_open_items", "仍有阻断项时 ready_for_governance_readiness_refresh 必须为 no。")
    if manifest.get("ready_for_lims_apply") == "yes":
        fail(findings, "closure_workbench_cannot_authorize_lims_apply", "治理关闭工作台不能单独声明 ready_for_lims_apply=yes。")

    for key in ["overview", "priority_batches", "readme"]:
        path = workbench_dir / files.get(key, REQUIRED_FILES[key])
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
        "workbench_dir": str(workbench_dir),
        "status": status,
        "readiness": manifest.get("readiness", ""),
        "ready_for_governance_readiness_refresh": manifest.get("ready_for_governance_readiness_refresh", ""),
        "ready_for_lims_apply": manifest.get("ready_for_lims_apply", ""),
        "counts": {**actual_counts, "findings": len(findings)},
        "findings": findings,
    }


def render_markdown(result: dict[str, Any]) -> str:
    lines = [
        "# QMS 治理关闭工作台 dry-run 验证报告",
        "",
        f"生成时间：{result['generated_at']}",
        f"治理关闭工作台：`{result['workbench_dir']}`",
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
    parser.add_argument("--workbench-dir", required=True)
    parser.add_argument("--json-out")
    parser.add_argument("--md-out")
    args = parser.parse_args()

    result = check_workbench(Path(args.workbench_dir))
    if args.json_out:
        Path(args.json_out).write_text(json.dumps(result, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
    if args.md_out:
        Path(args.md_out).write_text(render_markdown(result), encoding="utf-8")
    print(json.dumps(result, ensure_ascii=False, indent=2))
    return 0 if result["status"] == "passed" else 1


if __name__ == "__main__":
    raise SystemExit(main())
