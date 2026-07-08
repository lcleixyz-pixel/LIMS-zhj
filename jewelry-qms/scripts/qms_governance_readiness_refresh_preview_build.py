#!/usr/bin/env python3
"""Build a no-write refresh preview for the governance readiness dashboard."""

from __future__ import annotations

import argparse
import csv
import datetime as dt
import json
from collections import Counter, defaultdict
from pathlib import Path
from typing import Any


DASHBOARD_FILES = {
    "manifest": "governance_readiness_manifest.json",
    "gate_register": "01-总闸门清单.csv",
    "human_task_register": "02-人工处理任务清单.csv",
}

CLOSURE_PREVIEW_FILES = {
    "manifest": "governance_closure_decision_preview_manifest.json",
    "decision_preview": "01-拟关闭决策预览.csv",
}

REFRESH_FILES = {
    "manifest": "governance_readiness_refresh_preview_manifest.json",
    "overview": "00-治理就绪刷新预览总览.md",
    "gate_refresh_preview": "01-总闸门刷新预览.csv",
    "task_refresh_preview": "02-人工任务刷新预览.csv",
    "blocking_tasks": "03-仍阻断任务清单.csv",
    "change_summary": "04-刷新差异摘要.csv",
    "readme": "README.md",
}

GUARDRAILS = [
    "本预览包只读取 governance_readiness_dashboard 和 governance_closure_decision_preview 推导刷新结果，不写数据库。",
    "本预览包不修改 governance_readiness_dashboard、governance_closure_workbench、governance_closure_decision_preview、人工评审包、第二阶段复核工作台或任何现用 Word 文件。",
    "本预览包不代表人工评审通过，不代表真实培训完成，不代表受控发布，不代表正式写库授权。",
    "只有治理关闭意见预览中 accepted_for_preview 的任务才会在本预览中模拟关闭；其它 pending、空白、缺证据或仍阻断项保持阻断。",
    "资质状态口径：已取得 CMA，CNAS 申请中；不得表述为已取得 CNAS。",
    "以 LIMS 当前导出的 2022 程序清单作为现行程序目录。",
    "jewelry-qms 仍为建设中系统，只进入实施计划、演练和治理准备材料，不写入质量手册正文。",
]

TASK_FIELDS = [
    "task_id",
    "gate_id",
    "task_group",
    "object_code",
    "object_name",
    "owner_role",
    "source_current_status",
    "closure_preview_result",
    "refreshed_task_status",
    "accepted_for_refresh",
    "blocking_before_refresh",
    "blocking_after_refresh",
    "closure_evidence_reference",
    "evidence_template_reference",
    "source_path",
    "not_imported",
    "not_real_record",
]

GATE_FIELDS = [
    "gate_id",
    "gate_group",
    "gate_name",
    "owner_role",
    "source_total_items",
    "source_blocking_items",
    "task_rows",
    "accepted_task_closures",
    "open_blocking_tasks_after_refresh",
    "refreshed_gate_status",
    "ready_for_refresh",
    "source_path",
    "not_imported",
    "not_real_record",
]

CHANGE_FIELDS = [
    "gate_id",
    "source_blocking_tasks",
    "refreshed_blocking_tasks",
    "accepted_task_closures",
    "change_summary",
    "not_imported",
]


def read_csv(path: Path) -> list[dict[str, str]]:
    with path.open("r", encoding="utf-8-sig", newline="") as handle:
        return list(csv.DictReader(handle))


def write_csv(path: Path, rows: list[dict[str, Any]], fieldnames: list[str]) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    with path.open("w", encoding="utf-8", newline="") as handle:
        writer = csv.DictWriter(handle, fieldnames=fieldnames, extrasaction="ignore")
        writer.writeheader()
        for row in rows:
            writer.writerow({field: row.get(field, "") for field in fieldnames})


def int_value(value: Any) -> int:
    try:
        return int(value)
    except (TypeError, ValueError):
        return 0


def is_source_blocking(row: dict[str, str]) -> bool:
    return row.get("blocking_if_unresolved", "").strip().lower() == "yes" and row.get("current_status", "").strip() in {"", "pending", "pending_human_review"}


def render_table(rows: list[dict[str, Any]], columns: list[str]) -> list[str]:
    lines = ["| " + " | ".join(columns) + " |", "|" + "|".join(["---"] * len(columns)) + "|"]
    for row in rows:
        values = [str(row.get(column, "")).replace("\n", " ").replace("|", "／") for column in columns]
        lines.append("| " + " | ".join(values) + " |")
    return lines


def build_task_rows(task_rows: list[dict[str, str]], closure_preview_rows: list[dict[str, str]]) -> list[dict[str, Any]]:
    preview_by_task = {
        row.get("source_task_id", "").strip(): row
        for row in closure_preview_rows
        if row.get("source_task_id", "").strip()
    }
    rows: list[dict[str, Any]] = []
    for task in task_rows:
        task_id = task.get("task_id", "").strip()
        preview = preview_by_task.get(task_id, {})
        accepted = preview.get("preview_result", "") == "accepted_for_preview"
        blocking_before = is_source_blocking(task)
        blocking_after = False if accepted else blocking_before
        rows.append(
            {
                "task_id": task_id,
                "gate_id": task.get("gate_id", ""),
                "task_group": task.get("task_group", ""),
                "object_code": task.get("object_code", ""),
                "object_name": task.get("object_name", ""),
                "owner_role": task.get("owner_role", ""),
                "source_current_status": task.get("current_status", ""),
                "closure_preview_result": preview.get("preview_result", "not_in_closure_preview"),
                "refreshed_task_status": "closed_by_governance_closure_preview" if accepted else task.get("current_status", ""),
                "accepted_for_refresh": "yes" if accepted else "no",
                "blocking_before_refresh": "yes" if blocking_before else "no",
                "blocking_after_refresh": "yes" if blocking_after else "no",
                "closure_evidence_reference": preview.get("closure_evidence_reference", ""),
                "evidence_template_reference": preview.get("evidence_template_reference", ""),
                "source_path": task.get("source_path", ""),
                "not_imported": "yes",
                "not_real_record": "yes",
            }
        )
    return rows


def build_gate_rows(gate_rows: list[dict[str, str]], task_preview_rows: list[dict[str, Any]]) -> list[dict[str, Any]]:
    tasks_by_gate: dict[str, list[dict[str, Any]]] = defaultdict(list)
    for row in task_preview_rows:
        tasks_by_gate[str(row.get("gate_id", ""))].append(row)

    rows: list[dict[str, Any]] = []
    for gate in gate_rows:
        gate_id = gate.get("gate_id", "")
        tasks = tasks_by_gate.get(gate_id, [])
        accepted = sum(1 for task in tasks if task.get("accepted_for_refresh") == "yes")
        open_blocking = sum(1 for task in tasks if task.get("blocking_after_refresh") == "yes")
        if open_blocking > 0:
            status = "blocked_by_open_tasks"
        elif tasks:
            status = "ready_after_task_refresh_preview"
        else:
            status = gate.get("current_status", "")
        rows.append(
            {
                "gate_id": gate_id,
                "gate_group": gate.get("gate_group", ""),
                "gate_name": gate.get("gate_name", ""),
                "owner_role": gate.get("owner_role", ""),
                "source_total_items": gate.get("total_items", ""),
                "source_blocking_items": gate.get("blocking_items", ""),
                "task_rows": len(tasks),
                "accepted_task_closures": accepted,
                "open_blocking_tasks_after_refresh": open_blocking,
                "refreshed_gate_status": status,
                "ready_for_refresh": "yes" if open_blocking == 0 else "no",
                "source_path": gate.get("source_path", ""),
                "not_imported": "yes",
                "not_real_record": "yes",
            }
        )
    return rows


def build_change_summary(gate_rows: list[dict[str, Any]], task_preview_rows: list[dict[str, Any]]) -> list[dict[str, Any]]:
    before_by_gate = Counter(str(row.get("gate_id", "")) for row in task_preview_rows if row.get("blocking_before_refresh") == "yes")
    after_by_gate = Counter(str(row.get("gate_id", "")) for row in task_preview_rows if row.get("blocking_after_refresh") == "yes")
    accepted_by_gate = Counter(str(row.get("gate_id", "")) for row in task_preview_rows if row.get("accepted_for_refresh") == "yes")
    rows: list[dict[str, Any]] = []
    for gate in gate_rows:
        gate_id = str(gate.get("gate_id", ""))
        before = before_by_gate.get(gate_id, 0)
        after = after_by_gate.get(gate_id, 0)
        accepted = accepted_by_gate.get(gate_id, 0)
        if accepted:
            summary = f"预览关闭 {accepted} 条任务，阻断任务从 {before} 降至 {after}。"
        else:
            summary = f"未接受新的关闭项，阻断任务保持 {after} 条。"
        rows.append(
            {
                "gate_id": gate_id,
                "source_blocking_tasks": before,
                "refreshed_blocking_tasks": after,
                "accepted_task_closures": accepted,
                "change_summary": summary,
                "not_imported": "yes",
            }
        )
    return rows


def write_overview(output_dir: Path, manifest: dict[str, Any], gate_rows: list[dict[str, Any]]) -> None:
    counts = manifest["counts"]
    lines = [
        "# QMS 治理就绪刷新预览总览",
        "",
        f"生成时间：{manifest['generated_at']}",
        f"来源总览：`{manifest['source_dashboard_dir']}`",
        f"来源关闭预览：`{manifest['source_closure_preview_dir']}`",
        "",
        "## 结论",
        "",
        f"- readiness: {manifest['readiness']}",
        f"- ready_for_lims_apply: {manifest['ready_for_lims_apply']}",
        f"- task_preview_rows: {counts['task_preview_rows']}",
        f"- accepted_task_closures: {counts['accepted_task_closures']}",
        f"- refreshed_blocking_tasks: {counts['refreshed_blocking_tasks']}",
        "",
        "该预览包只模拟关闭意见被接受后治理总览会如何刷新；它不修改源总览、不写数据库、不代表人工评审通过、真实培训完成、受控发布或正式写库授权。",
        "",
        "## 边界",
        "",
    ]
    lines.extend(f"- {item}" for item in GUARDRAILS)
    lines.extend(["", "## 闸门刷新预览", ""])
    lines.extend(render_table(gate_rows, ["gate_id", "gate_group", "task_rows", "accepted_task_closures", "open_blocking_tasks_after_refresh", "refreshed_gate_status"]))
    lines.append("")
    (output_dir / REFRESH_FILES["overview"]).write_text("\n".join(lines), encoding="utf-8")


def write_readme(output_dir: Path, manifest: dict[str, Any]) -> None:
    lines = [
        "# governance_readiness_refresh_preview",
        "",
        "用途：读取治理就绪总览和治理关闭意见回填预览，生成不写库、不回写源文件的总闸门刷新预览。",
        "",
        "## 文件",
        "",
    ]
    for key, filename in REFRESH_FILES.items():
        lines.append(f"- `{filename}`：{key}")
    lines.extend(["", "## 使用边界", ""])
    lines.extend(f"- {item}" for item in GUARDRAILS)
    lines.extend(
        [
            "",
            "## 初始状态",
            "",
            f"- task_preview_rows: {manifest['counts']['task_preview_rows']}",
            f"- accepted_task_closures: {manifest['counts']['accepted_task_closures']}",
            f"- refreshed_blocking_tasks: {manifest['counts']['refreshed_blocking_tasks']}",
            "- 当前预览不会替代真实人工关闭、真实培训、受控发布或正式写库授权。",
            "",
        ]
    )
    (output_dir / REFRESH_FILES["readme"]).write_text("\n".join(lines), encoding="utf-8")


def build_refresh_preview(dashboard_dir: Path, closure_preview_dir: Path, output_dir: Path) -> dict[str, Any]:
    dashboard_manifest_path = dashboard_dir / DASHBOARD_FILES["manifest"]
    closure_manifest_path = closure_preview_dir / CLOSURE_PREVIEW_FILES["manifest"]
    if not dashboard_manifest_path.exists():
        raise FileNotFoundError(f"缺少治理就绪总览 manifest：{dashboard_manifest_path}")
    if not closure_manifest_path.exists():
        raise FileNotFoundError(f"缺少治理关闭意见预览 manifest：{closure_manifest_path}")
    dashboard_manifest = json.loads(dashboard_manifest_path.read_text(encoding="utf-8"))
    closure_manifest = json.loads(closure_manifest_path.read_text(encoding="utf-8"))
    if dashboard_manifest.get("status") != "governance_readiness_no_database_write":
        raise ValueError("治理就绪总览 manifest 状态不符合预期。")
    if closure_manifest.get("status") != "governance_closure_decision_preview_no_database_write":
        raise ValueError("治理关闭意见预览 manifest 状态不符合预期。")

    gate_source_rows = read_csv(dashboard_dir / DASHBOARD_FILES["gate_register"])
    task_source_rows = read_csv(dashboard_dir / DASHBOARD_FILES["human_task_register"])
    closure_preview_rows = read_csv(closure_preview_dir / CLOSURE_PREVIEW_FILES["decision_preview"])
    output_dir.mkdir(parents=True, exist_ok=True)

    task_preview_rows = build_task_rows(task_source_rows, closure_preview_rows)
    gate_preview_rows = build_gate_rows(gate_source_rows, task_preview_rows)
    blocking_rows = [row for row in task_preview_rows if row.get("blocking_after_refresh") == "yes"]
    change_rows = build_change_summary(gate_preview_rows, task_preview_rows)

    write_csv(output_dir / REFRESH_FILES["task_refresh_preview"], task_preview_rows, TASK_FIELDS)
    write_csv(output_dir / REFRESH_FILES["gate_refresh_preview"], gate_preview_rows, GATE_FIELDS)
    write_csv(output_dir / REFRESH_FILES["blocking_tasks"], blocking_rows, TASK_FIELDS)
    write_csv(output_dir / REFRESH_FILES["change_summary"], change_rows, CHANGE_FIELDS)

    accepted = sum(1 for row in task_preview_rows if row.get("accepted_for_refresh") == "yes")
    refreshed_blocking = len(blocking_rows)
    refreshed_blocking_gates = sum(1 for row in gate_preview_rows if int_value(row.get("open_blocking_tasks_after_refresh")) > 0)
    counts = {
        "gate_rows": len(gate_preview_rows),
        "task_preview_rows": len(task_preview_rows),
        "accepted_task_closures": accepted,
        "refreshed_blocking_tasks": refreshed_blocking,
        "refreshed_blocking_gates": refreshed_blocking_gates,
        "database_write_performed": 0,
    }
    manifest = {
        "generated_at": dt.datetime.now().isoformat(timespec="seconds"),
        "status": "governance_readiness_refresh_preview_no_database_write",
        "source_dashboard_dir": str(dashboard_dir),
        "source_closure_preview_dir": str(closure_preview_dir),
        "readiness": "ready_for_lims_apply" if refreshed_blocking == 0 else "blocked_by_refresh_open_items",
        "ready_for_lims_apply": "yes" if refreshed_blocking == 0 else "no",
        "files": REFRESH_FILES,
        "counts": counts,
        "source_counts": {
            "governance_readiness": dashboard_manifest.get("counts", {}),
            "governance_closure_preview": closure_manifest.get("counts", {}),
        },
        "guardrails": GUARDRAILS,
    }
    (output_dir / REFRESH_FILES["manifest"]).write_text(
        json.dumps(manifest, ensure_ascii=False, indent=2) + "\n",
        encoding="utf-8",
    )
    write_overview(output_dir, manifest, gate_preview_rows)
    write_readme(output_dir, manifest)
    return manifest


def render_report(manifest: dict[str, Any]) -> str:
    lines = [
        "# QMS 治理就绪刷新预览生成报告",
        "",
        f"生成时间：{manifest['generated_at']}",
        f"来源总览：`{manifest['source_dashboard_dir']}`",
        f"来源关闭预览：`{manifest['source_closure_preview_dir']}`",
        f"结论：{manifest['status']}",
        f"readiness：{manifest['readiness']}",
        f"ready_for_lims_apply：{manifest['ready_for_lims_apply']}",
        "",
        "## 计数",
        "",
    ]
    for key, value in manifest["counts"].items():
        lines.append(f"- {key}: {value}")
    lines.extend(["", "## 边界", ""])
    lines.extend(f"- {item}" for item in GUARDRAILS)
    lines.append("")
    return "\n".join(lines)


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--dashboard-dir", required=True)
    parser.add_argument("--closure-preview-dir", required=True)
    parser.add_argument("--output-dir", required=True)
    parser.add_argument("--json-out")
    parser.add_argument("--md-out")
    args = parser.parse_args()

    manifest = build_refresh_preview(
        Path(args.dashboard_dir),
        Path(args.closure_preview_dir),
        Path(args.output_dir),
    )
    report = {
        "generated_at": dt.datetime.now().isoformat(timespec="seconds"),
        "status": "passed",
        "output_dir": args.output_dir,
        "counts": manifest["counts"],
        "readiness": manifest["readiness"],
        "ready_for_lims_apply": manifest["ready_for_lims_apply"],
        "findings": [],
    }
    if args.json_out:
        Path(args.json_out).write_text(json.dumps(report, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
    if args.md_out:
        Path(args.md_out).write_text(render_report(manifest), encoding="utf-8")
    print(json.dumps(report, ensure_ascii=False, indent=2))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
