#!/usr/bin/env python3
"""Validate a no-write simulated completion package for the pilot operator workbook."""

from __future__ import annotations

import argparse
import csv
import datetime as dt
import json
from pathlib import Path
from typing import Any


SIMULATION_FILES = {
    "manifest": "governance_closure_pilot_operator_completion_simulation_manifest.json",
    "overview": "00-模拟完成总览.md",
    "master": "01-模拟完成主清单.csv",
    "field_checklist": "02-模拟逐字段完成清单.csv",
    "handoff_checklist": "03-模拟签核交接完成表.csv",
    "rerun": "04-复跑验证提示.md",
    "readme": "README.md",
    "task_card_dir": "task_cards",
}

SIMULATION_MARKER = "SIMULATED_COMPLETION_NOT_REAL_EXECUTION"

REQUIRED_GUARDRAILS = [
    "不写数据库",
    "不修改 governance_closure_pilot_operator_workbook",
    "不修改",
    "模拟完成",
    "不代表真实执行完成",
    "不代表人工评审通过",
    "不代表真实培训完成",
    "不代表受控发布",
    "已取得 CMA",
    "CNAS 申请中",
    "2022 程序清单",
    "jewelry-qms 仍为建设中系统",
    "不写入质量手册正文",
]


def read_csv(path: Path) -> list[dict[str, str]]:
    with path.open("r", encoding="utf-8-sig", newline="") as handle:
        return list(csv.DictReader(handle))


def add_finding(findings: list[dict[str, str]], finding_id: str, message: str, severity: str = "high") -> None:
    findings.append({"severity": severity, "id": finding_id, "message": message})


def forbidden_database_artifacts(base: Path) -> list[str]:
    return [
        str(path)
        for path in base.rglob("*")
        if path.is_file() and path.suffix.lower() in {".sql", ".db", ".sqlite", ".sqlite3"}
    ]


def has_marker(row: dict[str, str]) -> bool:
    return (
        row.get("not_imported", "") == "yes"
        and row.get("not_real_record", "") == "yes"
        and row.get("simulated_completion", "") == "yes"
        and row.get("simulation_marker", "") == SIMULATION_MARKER
    )


def check_simulation(simulation_dir: Path) -> dict[str, Any]:
    findings: list[dict[str, str]] = []
    manifest_path = simulation_dir / SIMULATION_FILES["manifest"]
    manifest: dict[str, Any] = {}
    if not manifest_path.is_file():
        add_finding(findings, "missing_governance_closure_pilot_operator_completion_simulation_manifest", "模拟完成包缺少 manifest。")
    else:
        manifest = json.loads(manifest_path.read_text(encoding="utf-8"))
        if manifest.get("status") != "governance_closure_pilot_operator_completion_simulation_no_write":
            add_finding(findings, "invalid_governance_closure_pilot_operator_completion_simulation_manifest_status", "模拟完成包 manifest 状态不符合预期。")
        guardrail_text = "\n".join(str(item) for item in manifest.get("guardrails", []))
        for marker in REQUIRED_GUARDRAILS:
            if marker not in guardrail_text:
                add_finding(findings, "governance_closure_pilot_operator_completion_simulation_manifest_missing_guardrail", f"manifest 缺少边界标识：{marker}")

    files = dict(SIMULATION_FILES)
    files.update({key: str(value) for key, value in manifest.get("files", {}).items()})
    paths: dict[str, Path] = {}
    for key, filename in files.items():
        path = simulation_dir / filename
        paths[key] = path
        if key == "task_card_dir":
            if not path.is_dir():
                add_finding(findings, "missing_governance_closure_pilot_operator_completion_simulation_task_card_dir", "模拟完成包缺少 task_cards 目录。")
        elif not path.is_file():
            add_finding(findings, f"missing_governance_closure_pilot_operator_completion_simulation_{key}", f"模拟完成包缺少文件：{filename}")

    for path in forbidden_database_artifacts(simulation_dir):
        add_finding(findings, "governance_closure_pilot_operator_completion_simulation_forbidden_database_artifact", f"模拟完成包不应包含数据库/SQL 文件：{Path(path).name}")

    master_rows = read_csv(paths["master"]) if paths.get("master", Path()).is_file() else []
    field_rows = read_csv(paths["field_checklist"]) if paths.get("field_checklist", Path()).is_file() else []
    handoff_rows = read_csv(paths["handoff_checklist"]) if paths.get("handoff_checklist", Path()).is_file() else []
    task_cards = sorted(paths["task_card_dir"].glob("*.md")) if paths.get("task_card_dir", Path()).is_dir() else []

    item_ids: set[str] = set()
    marker_rows = 0
    pending_items = 0
    for index, row in enumerate(master_rows, start=2):
        item_id = row.get("workbook_item_id", "").strip()
        label = row.get("simulation_item_id", "") or item_id or f"主清单第 {index} 行"
        if not item_id:
            add_finding(findings, "governance_closure_pilot_operator_completion_simulation_blank_item_id", "模拟完成主清单 workbook_item_id 为空。")
        elif item_id in item_ids:
            add_finding(findings, "governance_closure_pilot_operator_completion_simulation_duplicate_item_id", f"模拟完成主清单 workbook_item_id 重复：{item_id}")
        item_ids.add(item_id)
        if not has_marker(row):
            add_finding(findings, "governance_closure_pilot_operator_completion_simulation_marker_missing", f"{label} 必须保留模拟与非真实记录标识。")
        else:
            marker_rows += 1
        for field in ["evidence_status", "signature_status", "handoff_status"]:
            if row.get(field, "") != "completed":
                add_finding(findings, "governance_closure_pilot_operator_completion_simulation_master_status_invalid", f"{label} {field} 应为 completed。")
        if row.get("workbook_status", "") != "ready_for_return_preview":
            pending_items += 1
            add_finding(findings, "governance_closure_pilot_operator_completion_simulation_master_not_ready", f"{label} workbook_status 应为 ready_for_return_preview。")
        if row.get("blocks_apply", "") != "no":
            add_finding(findings, "governance_closure_pilot_operator_completion_simulation_master_blocks_apply_invalid", f"{label} blocks_apply 应为 no。")

    pending_fields = 0
    field_ids: set[str] = set()
    for index, row in enumerate(field_rows, start=2):
        field_id = row.get("field_task_id", "").strip()
        label = row.get("simulation_field_id", "") or field_id or f"字段清单第 {index} 行"
        if not field_id:
            add_finding(findings, "governance_closure_pilot_operator_completion_simulation_blank_field_id", "模拟逐字段完成清单 field_task_id 为空。")
        elif field_id in field_ids:
            add_finding(findings, "governance_closure_pilot_operator_completion_simulation_duplicate_field_id", f"模拟逐字段完成清单 field_task_id 重复：{field_id}")
        field_ids.add(field_id)
        if row.get("workbook_item_id", "") not in item_ids:
            add_finding(findings, "governance_closure_pilot_operator_completion_simulation_field_unknown_item", f"{label} 指向不存在的 workbook_item_id。")
        if not has_marker(row):
            add_finding(findings, "governance_closure_pilot_operator_completion_simulation_field_marker_missing", f"{label} 必须保留模拟与非真实记录标识。")
        else:
            marker_rows += 1
        if row.get("field_status", "") != "completed":
            pending_fields += 1
            add_finding(findings, "governance_closure_pilot_operator_completion_simulation_field_not_completed", f"{label} field_status 应为 completed。")
        if row.get("blocks_apply", "") != "no":
            add_finding(findings, "governance_closure_pilot_operator_completion_simulation_field_blocks_apply_invalid", f"{label} blocks_apply 应为 no。")
        if not row.get("simulated_input_value", "").strip():
            add_finding(findings, "governance_closure_pilot_operator_completion_simulation_field_value_blank", f"{label} simulated_input_value 为空。")

    pending_handoffs = 0
    handoff_item_ids: set[str] = set()
    for index, row in enumerate(handoff_rows, start=2):
        item_id = row.get("workbook_item_id", "")
        label = row.get("simulation_handoff_id", "") or row.get("pilot_handoff_id", "") or f"签核交接第 {index} 行"
        handoff_item_ids.add(item_id)
        if item_id not in item_ids:
            add_finding(findings, "governance_closure_pilot_operator_completion_simulation_handoff_unknown_item", f"{label} 指向不存在的 workbook_item_id。")
        if not has_marker(row):
            add_finding(findings, "governance_closure_pilot_operator_completion_simulation_handoff_marker_missing", f"{label} 必须保留模拟与非真实记录标识。")
        else:
            marker_rows += 1
        for field in ["signature_status", "handoff_status", "workbook_status"]:
            expected = "completed"
            if row.get(field, "") != expected:
                pending_handoffs += 1
                add_finding(findings, "governance_closure_pilot_operator_completion_simulation_handoff_status_invalid", f"{label} {field} 应为 completed。")
        if row.get("blocks_apply", "") != "no":
            add_finding(findings, "governance_closure_pilot_operator_completion_simulation_handoff_blocks_apply_invalid", f"{label} blocks_apply 应为 no。")
        if row.get("assigned_person", "") != "SIMULATED_PERSON_NOT_REAL_EXECUTOR":
            add_finding(findings, "governance_closure_pilot_operator_completion_simulation_handoff_assignee_invalid", f"{label} assigned_person 必须保留模拟执行人标识。")
        if row.get("reviewer", "") != "SIMULATED_REVIEWER_NOT_REAL_REVIEW":
            add_finding(findings, "governance_closure_pilot_operator_completion_simulation_handoff_reviewer_invalid", f"{label} reviewer 必须保留模拟复核人标识。")

    if handoff_item_ids and handoff_item_ids != item_ids:
        add_finding(findings, "governance_closure_pilot_operator_completion_simulation_handoff_item_set_mismatch", "模拟签核交接完成表未覆盖全部试点主任务。")

    actual_counts = {
        "pilot_items": len(master_rows),
        "field_fill_items": len(field_rows),
        "handoff_check_items": len(handoff_rows),
        "task_cards": len(task_cards),
        "pending_workbook_items": pending_items,
        "pending_field_items": pending_fields,
        "pending_handoff_items": pending_handoffs,
        "simulated_completion_rows": len(master_rows) + len(field_rows) + len(handoff_rows),
        "simulation_marker_rows": marker_rows,
        "source_missing_fields": int(manifest.get("counts", {}).get("source_missing_fields", 0)),
        "source_blocked_patches": int(manifest.get("counts", {}).get("source_blocked_patches", 0)),
        "source_workbench_modified": int(manifest.get("counts", {}).get("source_workbench_modified", 0)),
        "database_write_performed": int(manifest.get("counts", {}).get("database_write_performed", 0)),
    }
    for key, actual in actual_counts.items():
        if key in manifest.get("counts", {}) and int(manifest["counts"][key]) != actual:
            add_finding(findings, f"governance_closure_pilot_operator_completion_simulation_count_mismatch_{key}", f"{key} 计数不一致：manifest={manifest['counts'][key]}，actual={actual}")
    if actual_counts["source_workbench_modified"] != 0:
        add_finding(findings, "governance_closure_pilot_operator_completion_simulation_source_modified_flagged", "source_workbench_modified 必须为 0。")
    if actual_counts["database_write_performed"] != 0:
        add_finding(findings, "governance_closure_pilot_operator_completion_simulation_database_write_flagged", "database_write_performed 必须为 0。")

    readiness = str(manifest.get("readiness", ""))
    ready_for_return = str(manifest.get("ready_for_pilot_return_preview", ""))
    ready_for_source = str(manifest.get("ready_for_source_workbench_update", ""))
    ready_for_apply = str(manifest.get("ready_for_lims_apply", ""))
    is_simulated = int(manifest.get("is_simulated", 0))
    if readiness != "operator_completion_simulation_ready_for_return_preview":
        add_finding(findings, "governance_closure_pilot_operator_completion_simulation_readiness_invalid", "readiness 不符合模拟完成包预期。")
    if ready_for_return != "yes":
        add_finding(findings, "governance_closure_pilot_operator_completion_simulation_not_ready_for_return", "ready_for_pilot_return_preview 应为 yes。")
    if ready_for_source == "yes":
        add_finding(findings, "governance_closure_pilot_operator_completion_simulation_source_update_conflict", "模拟完成包不得声明 ready_for_source_workbench_update=yes。")
    if ready_for_apply == "yes":
        add_finding(findings, "governance_closure_pilot_operator_completion_simulation_cannot_authorize_lims_apply", "模拟完成包不得声明 ready_for_lims_apply=yes。")
    if is_simulated != 1:
        add_finding(findings, "governance_closure_pilot_operator_completion_simulation_marker_flag_invalid", "is_simulated 必须为 1。")

    for key in ["overview", "rerun", "readme"]:
        path = paths.get(key)
        if not path or not path.is_file():
            continue
        text = path.read_text(encoding="utf-8")
        for marker in ["模拟完成", "不写数据库", "不代表真实执行完成", "不写入质量手册正文"]:
            if marker not in text:
                add_finding(findings, "governance_closure_pilot_operator_completion_simulation_doc_missing_guardrail", f"{path.name} 缺少边界标识：{marker}")

    return {
        "generated_at": dt.datetime.now().replace(microsecond=0).isoformat(),
        "simulation_dir": str(simulation_dir),
        "status": "passed" if not findings else "failed",
        "readiness": readiness,
        "ready_for_pilot_return_preview": ready_for_return,
        "ready_for_source_workbench_update": ready_for_source,
        "ready_for_lims_apply": ready_for_apply,
        "is_simulated": is_simulated,
        "counts": {**actual_counts, "findings": len(findings)},
        "findings": findings,
    }


def render_markdown(result: dict[str, Any]) -> str:
    lines = [
        "# QMS 治理关闭试点人工执行模拟完成包 dry-run 验证报告",
        "",
        f"生成时间：{result['generated_at']}",
        f"模拟完成包：`{result['simulation_dir']}`",
        f"结论：{result['status']}",
        f"readiness：{result.get('readiness', '')}",
        f"ready_for_pilot_return_preview：{result.get('ready_for_pilot_return_preview', '')}",
        f"ready_for_source_workbench_update：{result.get('ready_for_source_workbench_update', '')}",
        f"ready_for_lims_apply：{result.get('ready_for_lims_apply', '')}",
        f"is_simulated：{result.get('is_simulated', '')}",
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
        lines.append("未发现结构性问题。该结论不代表真实执行完成、人工评审通过、真实培训完成、受控发布或正式写库授权。")
    lines.append("")
    return "\n".join(lines)


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--simulation-dir", required=True)
    parser.add_argument("--json-out")
    parser.add_argument("--md-out")
    args = parser.parse_args()

    result = check_simulation(Path(args.simulation_dir))
    if args.json_out:
        Path(args.json_out).write_text(json.dumps(result, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
    if args.md_out:
        Path(args.md_out).write_text(render_markdown(result), encoding="utf-8")
    print(json.dumps(result, ensure_ascii=False, indent=2))
    return 0 if result["status"] == "passed" else 1


if __name__ == "__main__":
    raise SystemExit(main())
