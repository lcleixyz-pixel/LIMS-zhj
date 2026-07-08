#!/usr/bin/env python3
"""Dry-run validation for the governance readiness refresh preview package."""

from __future__ import annotations

import argparse
import csv
import datetime as dt
import json
import re
from pathlib import Path
from typing import Any


REQUIRED_FILES = {
    "manifest": "governance_readiness_refresh_preview_manifest.json",
    "overview": "00-治理就绪刷新预览总览.md",
    "gate_refresh_preview": "01-总闸门刷新预览.csv",
    "task_refresh_preview": "02-人工任务刷新预览.csv",
    "blocking_tasks": "03-仍阻断任务清单.csv",
    "change_summary": "04-刷新差异摘要.csv",
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


def check_refresh_preview(preview_dir: Path) -> dict[str, Any]:
    findings: list[dict[str, str]] = []
    manifest_path = preview_dir / REQUIRED_FILES["manifest"]
    if not manifest_path.exists():
        return {
            "generated_at": dt.datetime.now().isoformat(timespec="seconds"),
            "preview_dir": str(preview_dir),
            "status": "failed",
            "findings": [{"id": "missing_manifest", "severity": "high", "message": "缺少 governance_readiness_refresh_preview_manifest.json"}],
        }

    manifest = json.loads(manifest_path.read_text(encoding="utf-8"))
    if manifest.get("status") != "governance_readiness_refresh_preview_no_database_write":
        fail(findings, "invalid_manifest_status", "manifest 状态必须为 governance_readiness_refresh_preview_no_database_write。")

    guardrail_text = "\n".join(str(item) for item in manifest.get("guardrails", []))
    for marker in REQUIRED_GUARDRAILS:
        if marker not in guardrail_text:
            fail(findings, "manifest_missing_guardrail", "manifest 缺少边界标识：" + marker)

    files = manifest.get("files", {})
    for key, filename in REQUIRED_FILES.items():
        actual = files.get(key, filename)
        if not (preview_dir / actual).exists():
            fail(findings, "missing_" + key, "缺少治理就绪刷新预览文件：" + actual)

    for path in list(preview_dir.rglob("*.sql")) + list(preview_dir.rglob("*.db")):
        fail(findings, "forbidden_database_artifact", "治理就绪刷新预览包不应包含数据库/SQL 文件：" + path.name)

    gate_rows: list[dict[str, str]] = []
    task_rows: list[dict[str, str]] = []
    blocking_rows: list[dict[str, str]] = []
    if (preview_dir / files.get("gate_refresh_preview", REQUIRED_FILES["gate_refresh_preview"])).exists():
        gate_rows = read_csv(preview_dir / files.get("gate_refresh_preview", REQUIRED_FILES["gate_refresh_preview"]))
    if (preview_dir / files.get("task_refresh_preview", REQUIRED_FILES["task_refresh_preview"])).exists():
        task_rows = read_csv(preview_dir / files.get("task_refresh_preview", REQUIRED_FILES["task_refresh_preview"]))
    if (preview_dir / files.get("blocking_tasks", REQUIRED_FILES["blocking_tasks"])).exists():
        blocking_rows = read_csv(preview_dir / files.get("blocking_tasks", REQUIRED_FILES["blocking_tasks"]))

    task_ids: set[str] = set()
    accepted = 0
    refreshed_blocking = 0
    expected_blocking_ids: set[str] = set()
    for index, row in enumerate(task_rows, start=2):
        task_id = row.get("task_id", "").strip()
        if not task_id:
            fail(findings, "blank_task_id", f"人工任务刷新预览第 {index} 行 task_id 为空。")
        elif task_id in task_ids:
            fail(findings, "duplicate_task_id", f"人工任务刷新预览存在重复 task_id：{task_id}")
        task_ids.add(task_id)
        if row.get("not_real_record") != "yes":
            fail(findings, "task_not_real_marker_missing", f"{task_id or index} 必须保留 not_real_record=yes。")
        if row.get("not_imported") != "yes":
            fail(findings, "task_not_imported_marker_missing", f"{task_id or index} 必须保留 not_imported=yes。")
        if row.get("accepted_for_refresh") not in {"yes", "no"}:
            fail(findings, "accepted_flag_invalid", f"{task_id or index} accepted_for_refresh 必须为 yes/no。")
        if row.get("blocking_after_refresh") not in {"yes", "no"}:
            fail(findings, "blocking_after_flag_invalid", f"{task_id or index} blocking_after_refresh 必须为 yes/no。")
        if row.get("accepted_for_refresh") == "yes":
            accepted += 1
            if row.get("closure_preview_result") != "accepted_for_preview":
                fail(findings, "accepted_task_without_accepted_preview", f"{task_id or index} 被刷新关闭，但 closure_preview_result 不是 accepted_for_preview。")
            if row.get("blocking_after_refresh") != "no":
                fail(findings, "accepted_task_still_blocking", f"{task_id or index} 已接受关闭但 blocking_after_refresh 不是 no。")
        if row.get("blocking_after_refresh") == "yes":
            refreshed_blocking += 1
            expected_blocking_ids.add(task_id)

    actual_blocking_ids = {row.get("task_id", "").strip() for row in blocking_rows if row.get("task_id", "").strip()}
    if actual_blocking_ids != expected_blocking_ids:
        fail(findings, "blocking_register_mismatch", "仍阻断任务清单与人工任务刷新预览中的 blocking_after_refresh=yes 不一致。")

    refreshed_blocking_gates = 0
    gate_task_sum = 0
    gate_accepted_sum = 0
    gate_blocking_sum = 0
    for index, row in enumerate(gate_rows, start=2):
        gate_id = row.get("gate_id", "").strip()
        if row.get("not_real_record") != "yes":
            fail(findings, "gate_not_real_marker_missing", f"{gate_id or index} 必须保留 not_real_record=yes。")
        if row.get("not_imported") != "yes":
            fail(findings, "gate_not_imported_marker_missing", f"{gate_id or index} 必须保留 not_imported=yes。")
        if row.get("ready_for_refresh") not in {"yes", "no"}:
            fail(findings, "gate_ready_flag_invalid", f"{gate_id or index} ready_for_refresh 必须为 yes/no。")
        task_count = int_count(row.get("task_rows"))
        accepted_count = int_count(row.get("accepted_task_closures"))
        blocking_count = int_count(row.get("open_blocking_tasks_after_refresh"))
        gate_task_sum += max(task_count, 0)
        gate_accepted_sum += max(accepted_count, 0)
        gate_blocking_sum += max(blocking_count, 0)
        if blocking_count > 0:
            refreshed_blocking_gates += 1
            if row.get("ready_for_refresh") != "no":
                fail(findings, "gate_ready_conflicts_with_blocking_tasks", f"{gate_id or index} 仍有阻断任务时 ready_for_refresh 必须为 no。")

    if gate_task_sum != len(task_rows):
        fail(findings, "gate_task_sum_mismatch", f"总闸门 task_rows 合计 {gate_task_sum}，人工任务刷新预览实际 {len(task_rows)}。")
    if gate_accepted_sum != accepted:
        fail(findings, "gate_accepted_sum_mismatch", f"总闸门 accepted_task_closures 合计 {gate_accepted_sum}，实际 {accepted}。")
    if gate_blocking_sum != refreshed_blocking:
        fail(findings, "gate_blocking_sum_mismatch", f"总闸门 open_blocking_tasks_after_refresh 合计 {gate_blocking_sum}，实际 {refreshed_blocking}。")

    counts = manifest.get("counts", {})
    actual_counts = {
        "gate_rows": len(gate_rows),
        "task_preview_rows": len(task_rows),
        "accepted_task_closures": accepted,
        "refreshed_blocking_tasks": refreshed_blocking,
        "refreshed_blocking_gates": refreshed_blocking_gates,
        "database_write_performed": int_count(counts.get("database_write_performed")),
    }
    for key, actual in actual_counts.items():
        if int_count(counts.get(key)) != actual:
            fail(findings, "manifest_count_mismatch_" + key, f"manifest {key}={counts.get(key)}，实际 {actual}。")

    if actual_counts["database_write_performed"] != 0:
        fail(findings, "database_write_not_zero", "治理就绪刷新预览包 database_write_performed 必须为 0。")
    if actual_counts["refreshed_blocking_tasks"] > 0 and manifest.get("ready_for_lims_apply") != "no":
        fail(findings, "ready_flag_conflicts_with_blocking_tasks", "仍有刷新后阻断任务时 ready_for_lims_apply 必须为 no。")

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
        "ready_for_lims_apply": manifest.get("ready_for_lims_apply", ""),
        "counts": {**actual_counts, "findings": len(findings)},
        "findings": findings,
    }


def render_markdown(result: dict[str, Any]) -> str:
    lines = [
        "# QMS 治理就绪刷新预览 dry-run 验证报告",
        "",
        f"生成时间：{result['generated_at']}",
        f"治理就绪刷新预览包：`{result['preview_dir']}`",
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

    result = check_refresh_preview(Path(args.preview_dir))
    if args.json_out:
        Path(args.json_out).write_text(json.dumps(result, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
    if args.md_out:
        Path(args.md_out).write_text(render_markdown(result), encoding="utf-8")
    print(json.dumps(result, ensure_ascii=False, indent=2))
    return 0 if result["status"] == "passed" else 1


if __name__ == "__main__":
    raise SystemExit(main())
