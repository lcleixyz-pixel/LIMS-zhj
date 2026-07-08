#!/usr/bin/env python3
"""Dry-run validation for the QMS governance readiness dashboard package."""

from __future__ import annotations

import argparse
import csv
import datetime as dt
import json
import re
from pathlib import Path
from typing import Any


REQUIRED_FILES = {
    "manifest": "governance_readiness_manifest.json",
    "overview": "00-治理就绪总览.md",
    "gate_register": "01-总闸门清单.csv",
    "human_task_register": "02-人工处理任务清单.csv",
    "command_checklist": "03-LIMS命令复核清单.md",
    "readme": "README.md",
}

REQUIRED_GUARDRAILS = [
    "不写数据库",
    "不修改 human_review_pack",
    "不代表人工评审通过",
    "已取得 CMA",
    "CNAS 申请中",
    "2022 程序清单",
    "jewelry-qms 仍为建设中系统",
    "不写入质量手册正文",
]

REQUIRED_GATE_IDS = {
    "GR-01",
    "GR-02",
    "GR-03",
    "GR-04",
    "GR-05",
    "GR-06",
    "GR-07",
    "GR-08",
    "GR-09",
    "GR-10",
    "GR-11",
    "GR-12",
    "GR-13",
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


def check_dashboard(dashboard_dir: Path) -> dict[str, Any]:
    findings: list[dict[str, str]] = []
    manifest_path = dashboard_dir / REQUIRED_FILES["manifest"]
    if not manifest_path.exists():
        return {
            "generated_at": dt.datetime.now().isoformat(timespec="seconds"),
            "dashboard_dir": str(dashboard_dir),
            "status": "failed",
            "findings": [{"id": "missing_manifest", "severity": "high", "message": "缺少 governance_readiness_manifest.json"}],
        }

    manifest = json.loads(manifest_path.read_text(encoding="utf-8"))
    if manifest.get("status") != "governance_readiness_no_database_write":
        fail(findings, "invalid_manifest_status", "manifest 状态必须为 governance_readiness_no_database_write。")

    guardrail_text = "\n".join(str(item) for item in manifest.get("guardrails", []))
    for marker in REQUIRED_GUARDRAILS:
        if marker not in guardrail_text:
            fail(findings, "manifest_missing_guardrail", "manifest 缺少边界标识：" + marker)

    files = manifest.get("files", {})
    for key, filename in REQUIRED_FILES.items():
        actual = files.get(key, filename)
        if not (dashboard_dir / actual).exists():
            fail(findings, "missing_" + key, "缺少治理总览文件：" + actual)

    for path in list(dashboard_dir.rglob("*.sql")) + list(dashboard_dir.rglob("*.db")):
        fail(findings, "forbidden_database_artifact", "治理总览包不应包含数据库/SQL 文件：" + path.name)

    gate_rows: list[dict[str, str]] = []
    task_rows: list[dict[str, str]] = []
    gate_path = dashboard_dir / files.get("gate_register", REQUIRED_FILES["gate_register"])
    task_path = dashboard_dir / files.get("human_task_register", REQUIRED_FILES["human_task_register"])
    if gate_path.exists():
        gate_rows = read_csv(gate_path)
    if task_path.exists():
        task_rows = read_csv(task_path)

    counts = manifest.get("counts", {})
    actual_counts = {
        "gate_rows": len(gate_rows),
        "blocking_gates": sum(
            1
            for row in gate_rows
            if int_count(row.get("blocking_items")) > 0 and row.get("blocks_apply") == "yes"
        ),
        "human_task_rows": len(task_rows),
        "blocking_tasks": sum(
            1
            for row in task_rows
            if row.get("blocking_if_unresolved") == "yes"
            and row.get("current_status") in {"", "pending", "pending_human_review"}
        ),
        "database_write_performed": int_count(counts.get("database_write_performed")),
    }
    for key, actual in actual_counts.items():
        if int_count(counts.get(key)) != actual:
            fail(findings, "manifest_count_mismatch_" + key, f"manifest {key}={counts.get(key)}，实际 {actual}。")

    gate_ids = {row.get("gate_id", "") for row in gate_rows}
    missing_gate_ids = sorted(REQUIRED_GATE_IDS - gate_ids)
    if missing_gate_ids:
        fail(findings, "missing_required_gates", "治理总览缺少闸门：" + "、".join(missing_gate_ids))

    for index, row in enumerate(gate_rows, start=2):
        if row.get("not_real_record") != "yes":
            fail(findings, "gate_not_real_marker_missing", f"总闸门清单第 {index} 行 not_real_record 必须为 yes。")
        if row.get("blocks_apply") not in {"yes", "no"}:
            fail(findings, "gate_blocks_apply_invalid", f"总闸门清单第 {index} 行 blocks_apply 必须为 yes/no。")
        if int_count(row.get("blocking_items")) > int_count(row.get("pending_items")) and row.get("current_status") != "missing_source":
            fail(findings, "gate_blocking_exceeds_pending", f"{row.get('gate_id')} blocking_items 不应大于 pending_items。")

    known_gate_ids = gate_ids
    for index, row in enumerate(task_rows, start=2):
        if row.get("gate_id") not in known_gate_ids:
            fail(findings, "task_unknown_gate", f"人工处理任务第 {index} 行引用未知 gate_id：{row.get('gate_id')}")
        if row.get("not_real_record") != "yes":
            fail(findings, "task_not_real_marker_missing", f"人工处理任务第 {index} 行 not_real_record 必须为 yes。")
        if row.get("blocking_if_unresolved") not in {"yes", "no"}:
            fail(findings, "task_blocking_flag_invalid", f"人工处理任务第 {index} 行 blocking_if_unresolved 必须为 yes/no。")

    if actual_counts["blocking_tasks"] > 0 and manifest.get("ready_for_lims_apply") != "no":
        fail(findings, "ready_flag_conflicts_with_blocking_tasks", "仍有阻断任务时 ready_for_lims_apply 必须为 no。")
    if manifest.get("ready_for_lims_apply") == "yes" and manifest.get("readiness") != "ready_for_lims_apply":
        fail(findings, "readiness_flag_mismatch", "ready_for_lims_apply=yes 时 readiness 必须为 ready_for_lims_apply。")
    if actual_counts["database_write_performed"] != 0:
        fail(findings, "database_write_not_zero", "治理总览包 database_write_performed 必须为 0。")

    for key in ["overview", "command_checklist", "readme"]:
        path = dashboard_dir / files.get(key, REQUIRED_FILES[key])
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
        "dashboard_dir": str(dashboard_dir),
        "status": status,
        "readiness": manifest.get("readiness", ""),
        "ready_for_lims_apply": manifest.get("ready_for_lims_apply", ""),
        "counts": {**actual_counts, "findings": len(findings)},
        "findings": findings,
    }


def render_markdown(result: dict[str, Any]) -> str:
    lines = [
        "# QMS 治理就绪总览包 dry-run 验证报告",
        "",
        f"生成时间：{result['generated_at']}",
        f"治理总览包：`{result['dashboard_dir']}`",
        f"结论：{result['status']}",
        f"readiness：{result.get('readiness', '')}",
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
        lines.extend([
            "## 发现项",
            "",
            "未发现结构性问题。该结论不代表已人工评审通过、已完成真实培训、已受控发布或已授权写库。",
        ])
    lines.append("")
    return "\n".join(lines)


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--dashboard-dir", required=True)
    parser.add_argument("--json-out")
    parser.add_argument("--md-out")
    args = parser.parse_args()

    result = check_dashboard(Path(args.dashboard_dir))
    print(json.dumps(result, ensure_ascii=False, indent=2))
    if args.json_out:
        Path(args.json_out).write_text(json.dumps(result, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
    if args.md_out:
        Path(args.md_out).write_text(render_markdown(result), encoding="utf-8")
    return 0 if result["status"] == "passed" else 1


if __name__ == "__main__":
    raise SystemExit(main())
