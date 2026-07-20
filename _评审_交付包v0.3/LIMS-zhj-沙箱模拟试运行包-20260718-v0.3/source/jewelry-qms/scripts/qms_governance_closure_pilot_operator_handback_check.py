#!/usr/bin/env python3
"""Validate a no-write real handback package for the pilot operator workbook."""

from __future__ import annotations

import argparse
import csv
import datetime as dt
import json
from pathlib import Path
from typing import Any


HANDBACK_FILES = {
    "manifest": "governance_closure_pilot_operator_handback_manifest.json",
    "overview": "00-真实执行交回总览.md",
    "master": "01-真实执行交回主清单.csv",
    "field_checklist": "02-真实逐字段交回清单.csv",
    "handoff_checklist": "03-真实签核交接交回表.csv",
    "acceptance": "04-交回验收与复跑说明.md",
    "readme": "README.md",
    "task_card_dir": "task_cards",
}

REQUIRED_GUARDRAILS = [
    "不写数据库",
    "不修改 governance_closure_pilot_operator_workbook",
    "真实人员交回",
    "不代表人工评审通过",
    "不代表真实培训完成",
    "不代表受控发布",
    "已取得 CMA",
    "CNAS 申请中",
    "2022 程序清单",
    "jewelry-qms 仍为建设中系统",
    "不写入质量手册正文",
]

SIMULATION_FORBIDDEN_MARKERS = [
    "SIMULATED",
    "模拟完成",
    "SIMULATED_COMPLETION_NOT_REAL_EXECUTION",
    "SIMULATED_PERSON_NOT_REAL_EXECUTOR",
    "SIMULATED_REVIEWER_NOT_REAL_REVIEW",
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


def contains_simulation_marker(value: str) -> bool:
    upper_value = value.upper()
    return any(marker.upper() in upper_value for marker in SIMULATION_FORBIDDEN_MARKERS)


def check_handback(handback_dir: Path) -> dict[str, Any]:
    findings: list[dict[str, str]] = []
    manifest_path = handback_dir / HANDBACK_FILES["manifest"]
    manifest: dict[str, Any] = {}
    if not manifest_path.is_file():
        add_finding(findings, "missing_governance_closure_pilot_operator_handback_manifest", "真实执行交回包缺少 manifest。")
    else:
        manifest = json.loads(manifest_path.read_text(encoding="utf-8"))
        if manifest.get("status") != "governance_closure_pilot_operator_handback_no_write":
            add_finding(findings, "invalid_governance_closure_pilot_operator_handback_manifest_status", "真实执行交回包 manifest 状态不符合预期。")
        guardrail_text = "\n".join(str(item) for item in manifest.get("guardrails", []))
        for marker in REQUIRED_GUARDRAILS:
            if marker not in guardrail_text:
                add_finding(findings, "governance_closure_pilot_operator_handback_manifest_missing_guardrail", f"manifest 缺少边界标识：{marker}")

    files = dict(HANDBACK_FILES)
    files.update({key: str(value) for key, value in manifest.get("files", {}).items()})
    paths: dict[str, Path] = {}
    for key, filename in files.items():
        path = handback_dir / filename
        paths[key] = path
        if key == "task_card_dir":
            if not path.is_dir():
                add_finding(findings, "missing_governance_closure_pilot_operator_handback_task_card_dir", "真实执行交回包缺少 task_cards 目录。")
        elif not path.is_file():
            add_finding(findings, f"missing_governance_closure_pilot_operator_handback_{key}", f"真实执行交回包缺少文件：{filename}")

    for path in forbidden_database_artifacts(handback_dir):
        add_finding(findings, "governance_closure_pilot_operator_handback_forbidden_database_artifact", f"真实执行交回包不应包含数据库/SQL 文件：{Path(path).name}")

    master_rows = read_csv(paths["master"]) if paths.get("master", Path()).is_file() else []
    field_rows = read_csv(paths["field_checklist"]) if paths.get("field_checklist", Path()).is_file() else []
    handoff_rows = read_csv(paths["handoff_checklist"]) if paths.get("handoff_checklist", Path()).is_file() else []
    task_cards = sorted(paths["task_card_dir"].glob("*.md")) if paths.get("task_card_dir", Path()).is_dir() else []

    item_ids: set[str] = set()
    pending_items = 0
    for index, row in enumerate(master_rows, start=2):
        item_id = row.get("workbook_item_id", "").strip()
        label = row.get("handback_item_id", "") or item_id or f"主清单第 {index} 行"
        if not item_id:
            add_finding(findings, "governance_closure_pilot_operator_handback_blank_item_id", "真实执行交回主清单 workbook_item_id 为空。")
        elif item_id in item_ids:
            add_finding(findings, "governance_closure_pilot_operator_handback_duplicate_item_id", f"真实执行交回主清单 workbook_item_id 重复：{item_id}")
        item_ids.add(item_id)
        for field in ["real_execution_required", "not_imported", "not_lims_record_yet"]:
            if row.get(field, "") != "yes":
                add_finding(findings, "governance_closure_pilot_operator_handback_marker_missing", f"{label} 必须保留 {field}=yes。")
        for field in ["evidence_status", "signature_status", "handoff_status", "handback_status"]:
            if row.get(field, "") not in {"pending", "completed", "rejected"}:
                add_finding(findings, "governance_closure_pilot_operator_handback_master_status_invalid", f"{label} {field} 不合法。")
        if any(row.get(field, "") != "completed" for field in ["evidence_status", "signature_status", "handoff_status", "handback_status"]):
            pending_items += 1
            if row.get("blocks_apply", "") != "yes":
                add_finding(findings, "governance_closure_pilot_operator_handback_pending_not_blocking", f"{label} 未完成时 blocks_apply 应为 yes。")

    pending_fields = 0
    field_ids: set[str] = set()
    completed_field_items = 0
    for index, row in enumerate(field_rows, start=2):
        field_id = row.get("field_task_id", "").strip()
        label = row.get("handback_field_id", "") or field_id or f"字段清单第 {index} 行"
        if not field_id:
            add_finding(findings, "governance_closure_pilot_operator_handback_blank_field_id", "真实逐字段交回清单 field_task_id 为空。")
        elif field_id in field_ids:
            add_finding(findings, "governance_closure_pilot_operator_handback_duplicate_field_id", f"真实逐字段交回清单 field_task_id 重复：{field_id}")
        field_ids.add(field_id)
        if row.get("workbook_item_id", "") not in item_ids:
            add_finding(findings, "governance_closure_pilot_operator_handback_field_unknown_item", f"{label} 指向不存在的 workbook_item_id。")
        for field in ["real_execution_required", "not_imported", "not_lims_record_yet"]:
            if row.get(field, "") != "yes":
                add_finding(findings, "governance_closure_pilot_operator_handback_field_marker_missing", f"{label} 必须保留 {field}=yes。")
        field_status = row.get("field_status", "")
        if field_status not in {"pending", "completed", "rejected"}:
            add_finding(findings, "governance_closure_pilot_operator_handback_field_status_invalid", f"{label} field_status 不合法。")
        real_value = row.get("real_input_value", "").strip()
        if contains_simulation_marker(real_value):
            add_finding(findings, "governance_closure_pilot_operator_handback_field_contains_simulation_marker", f"{label} real_input_value 含模拟标识，不能作为真实交回。")
        if field_status == "completed":
            completed_field_items += 1
            if not real_value:
                add_finding(findings, "governance_closure_pilot_operator_handback_field_completed_without_real_value", f"{label} 标为 completed 但 real_input_value 为空。")
            if row.get("blocks_apply", "") != "no":
                add_finding(findings, "governance_closure_pilot_operator_handback_field_completed_still_blocking", f"{label} completed 时 blocks_apply 应为 no。")
        else:
            pending_fields += 1
            if row.get("blocks_apply", "") != "yes":
                add_finding(findings, "governance_closure_pilot_operator_handback_field_pending_not_blocking", f"{label} 未完成时 blocks_apply 应为 yes。")

    pending_handoffs = 0
    handoff_item_ids: set[str] = set()
    completed_handoff_items = 0
    for index, row in enumerate(handoff_rows, start=2):
        item_id = row.get("workbook_item_id", "")
        label = row.get("handback_handoff_id", "") or row.get("pilot_handoff_id", "") or f"签核交接第 {index} 行"
        handoff_item_ids.add(item_id)
        if item_id not in item_ids:
            add_finding(findings, "governance_closure_pilot_operator_handback_handoff_unknown_item", f"{label} 指向不存在的 workbook_item_id。")
        for field in ["real_execution_required", "not_imported", "not_lims_record_yet"]:
            if row.get(field, "") != "yes":
                add_finding(findings, "governance_closure_pilot_operator_handback_handoff_marker_missing", f"{label} 必须保留 {field}=yes。")
        for field in ["signature_status", "handoff_status", "handback_status"]:
            if row.get(field, "") not in {"pending", "completed", "rejected"}:
                add_finding(findings, "governance_closure_pilot_operator_handback_handoff_status_invalid", f"{label} {field} 不合法。")
        for field in ["assigned_person", "reviewer", "actual_finish_date"]:
            if contains_simulation_marker(row.get(field, "")):
                add_finding(findings, "governance_closure_pilot_operator_handback_handoff_contains_simulation_marker", f"{label} {field} 含模拟标识，不能作为真实交回。")
        completed = all(row.get(field, "") == "completed" for field in ["signature_status", "handoff_status", "handback_status"])
        if completed:
            completed_handoff_items += 1
            for field in ["assigned_person", "reviewer", "actual_finish_date"]:
                if not row.get(field, "").strip():
                    add_finding(findings, "governance_closure_pilot_operator_handback_handoff_completed_without_required_value", f"{label} 标为 completed 但 {field} 为空。")
            if row.get("blocks_apply", "") != "no":
                add_finding(findings, "governance_closure_pilot_operator_handback_handoff_completed_still_blocking", f"{label} completed 时 blocks_apply 应为 no。")
        else:
            pending_handoffs += 1
            if row.get("blocks_apply", "") != "yes":
                add_finding(findings, "governance_closure_pilot_operator_handback_handoff_pending_not_blocking", f"{label} 未完成时 blocks_apply 应为 yes。")

    if handoff_item_ids and handoff_item_ids != item_ids:
        add_finding(findings, "governance_closure_pilot_operator_handback_handoff_item_set_mismatch", "真实签核交接交回表未覆盖全部试点主任务。")

    manifest_counts = manifest.get("counts", {})
    actual_counts = {
        "pilot_items": len(master_rows),
        "field_fill_items": len(field_rows),
        "handoff_check_items": len(handoff_rows),
        "task_cards": len(task_cards),
        "pending_workbook_items": pending_items,
        "pending_field_items": pending_fields,
        "pending_handoff_items": pending_handoffs,
        "completed_field_items": completed_field_items,
        "completed_handoff_items": completed_handoff_items,
        "source_workbench_modified": int(manifest_counts.get("source_workbench_modified", 0)),
        "database_write_performed": int(manifest_counts.get("database_write_performed", 0)),
    }
    for key in ["pilot_items", "field_fill_items", "handoff_check_items", "task_cards"]:
        if key in manifest_counts and int(manifest_counts[key]) != actual_counts[key]:
            add_finding(findings, f"governance_closure_pilot_operator_handback_count_mismatch_{key}", f"{key} 计数不一致：manifest={manifest_counts[key]}，actual={actual_counts[key]}")
    if actual_counts["source_workbench_modified"] != 0:
        add_finding(findings, "governance_closure_pilot_operator_handback_source_modified_flagged", "source_workbench_modified 必须为 0。")
    if actual_counts["database_write_performed"] != 0:
        add_finding(findings, "governance_closure_pilot_operator_handback_database_write_flagged", "database_write_performed 必须为 0。")

    ready_for_return = "yes" if not findings and pending_items == 0 and pending_fields == 0 and pending_handoffs == 0 else "no"
    readiness = "operator_handback_ready_for_return_preview" if ready_for_return == "yes" else "operator_handback_pending_real_execution"

    for key in ["overview", "acceptance", "readme"]:
        path = paths.get(key)
        if not path or not path.is_file():
            continue
        text = path.read_text(encoding="utf-8")
        for marker in ["真实", "不写数据库", "不代表人工评审通过", "不写入质量手册正文"]:
            if marker not in text:
                add_finding(findings, "governance_closure_pilot_operator_handback_doc_missing_guardrail", f"{path.name} 缺少边界标识：{marker}")

    result = {
        "generated_at": dt.datetime.now().replace(microsecond=0).isoformat(),
        "handback_dir": str(handback_dir),
        "status": "passed" if not findings else "failed",
        "readiness": readiness,
        "ready_for_pilot_return_preview": ready_for_return,
        "ready_for_source_workbench_update": "no",
        "ready_for_lims_apply": "no",
        **actual_counts,
        "findings": findings,
    }
    return result


def render_markdown(result: dict[str, Any]) -> str:
    lines = [
        "# QMS 治理关闭试点真实执行交回包 dry-run 验证报告",
        "",
        f"生成时间：{result['generated_at']}",
        f"交回包：`{result['handback_dir']}`",
        f"结论：{result['status']}",
        f"readiness：{result['readiness']}",
        f"ready_for_pilot_return_preview：{result['ready_for_pilot_return_preview']}",
        f"ready_for_source_workbench_update：{result['ready_for_source_workbench_update']}",
        f"ready_for_lims_apply：{result['ready_for_lims_apply']}",
        "",
        "## 计数",
        "",
    ]
    for key in [
        "pilot_items",
        "field_fill_items",
        "handoff_check_items",
        "pending_workbook_items",
        "pending_field_items",
        "pending_handoff_items",
        "completed_field_items",
        "completed_handoff_items",
        "database_write_performed",
    ]:
        lines.append(f"- {key}: {result.get(key, '')}")
    lines.extend(["", "## 发现项", ""])
    if result.get("findings"):
        for finding in result["findings"]:
            lines.append(f"- [{finding['severity']}] {finding['id']}：{finding['message']}")
    else:
        lines.append("未发现结构性问题。该结论只说明交回包结构可读，不代表人工评审通过、真实培训完成、受控发布或正式写库授权。")
    lines.append("")
    return "\n".join(lines)


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--handback-dir", required=True)
    parser.add_argument("--json-out")
    parser.add_argument("--md-out")
    args = parser.parse_args()

    result = check_handback(Path(args.handback_dir))
    if args.json_out:
        Path(args.json_out).write_text(json.dumps(result, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
    if args.md_out:
        Path(args.md_out).write_text(render_markdown(result), encoding="utf-8")
    print(json.dumps(result, ensure_ascii=False, indent=2))
    return 0 if result["status"] == "passed" else 1


if __name__ == "__main__":
    raise SystemExit(main())
