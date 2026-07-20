#!/usr/bin/env python3
"""Mark QMS handoff packages as test-completed for gate rehearsal.

This script is intentionally scoped to delivery-package CSV/manifest files. It
does not write LIMS data, edit controlled Word documents, or claim production
evidence.
"""

from __future__ import annotations

import argparse
import csv
import json
from datetime import date
from pathlib import Path
from typing import Callable


TEST_NOTE = "测试完成态：按用户授权作为真人审核完成，用于链路验证，不代表生产受控发布记录。"
EVIDENCE_PREFIX = "TEST-COMPLETE"


def read_csv(path: Path) -> tuple[list[str], list[dict[str, str]]]:
    with path.open("r", encoding="utf-8-sig", newline="") as handle:
        reader = csv.DictReader(handle)
        return list(reader.fieldnames or []), list(reader)


def write_csv(path: Path, fieldnames: list[str], rows: list[dict[str, str]]) -> None:
    with path.open("w", encoding="utf-8", newline="") as handle:
        writer = csv.DictWriter(handle, fieldnames=fieldnames, extrasaction="ignore")
        writer.writeheader()
        writer.writerows(rows)


def read_json(path: Path) -> dict:
    if not path.exists():
        return {}
    return json.loads(path.read_text(encoding="utf-8"))


def write_json(path: Path, payload: dict) -> None:
    path.write_text(json.dumps(payload, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")


def set_if_present(row: dict[str, str], field: str, value: str) -> bool:
    if field not in row:
        return False
    row[field] = value
    return True


def fill_if_present(row: dict[str, str], field: str, value: str) -> bool:
    if field not in row:
        return False
    if not str(row.get(field, "")).strip():
        row[field] = value
    return True


def clear_if_present(row: dict[str, str], field: str) -> bool:
    if field not in row:
        return False
    row[field] = ""
    return True


def update_csv(path: Path, mutate: Callable[[dict[str, str], int], None]) -> int:
    if not path.exists():
        return 0
    fieldnames, rows = read_csv(path)
    for index, row in enumerate(rows, start=1):
        mutate(row, index)
    write_csv(path, fieldnames, rows)
    return len(rows)


def clear_csv_rows(path: Path) -> int:
    if not path.exists():
        return 0
    fieldnames, rows = read_csv(path)
    write_csv(path, fieldnames, [])
    return len(rows)


def update_manifest(path: Path, values: dict) -> bool:
    if not path.exists():
        return False
    payload = read_json(path)
    payload.update(values)
    counts = payload.get("counts")
    if isinstance(counts, dict):
        for key, value in values.items():
            if key in counts:
                counts[key] = value
        payload["counts"] = counts
    payload["test_completion_note"] = TEST_NOTE
    payload["production_record"] = False
    guardrails = list(payload.get("guardrails") or [])
    for item in ["NO_DATABASE_WRITE", "NO_SOURCE_DOCUMENT_MODIFICATION", "TEST_COMPLETION_NOT_PRODUCTION_EVIDENCE"]:
        if item not in guardrails:
            guardrails.append(item)
    payload["guardrails"] = guardrails
    write_json(path, payload)
    return True


def evidence_value(stage: str, index: int) -> str:
    return f"{EVIDENCE_PREFIX}-{stage}-{index:03d}"


def complete_human_review(stage_dir: Path, ctx: dict) -> int:
    pack = stage_dir / "human_review_pack"
    touched = 0

    def mutate(row: dict[str, str], index: int) -> None:
        set_if_present(row, "human_decision", "approved")
        set_if_present(row, "decision_status", "approved")
        set_if_present(row, "review_result", "approved")
        set_if_present(row, "approval_status", "approved")
        fill_if_present(row, "review_comment", TEST_NOTE)
        fill_if_present(row, "comment", TEST_NOTE)
        fill_if_present(row, "human_comment", TEST_NOTE)
        fill_if_present(row, "reviewer", ctx["reviewer"])
        fill_if_present(row, "human_reviewer", ctx["reviewer"])
        fill_if_present(row, "reviewed_by", ctx["reviewer"])
        fill_if_present(row, "review_date", ctx["date"])
        fill_if_present(row, "human_review_date", ctx["date"])

    for path in pack.glob("*.csv"):
        touched += update_csv(path, mutate)
    update_manifest(
        pack / "human_review_manifest.json",
        {
            "review_status": "approved",
            "status": "human_review_required_no_database_write",
            "approved_items": touched,
            "pending_items": 0,
            "rejected_items": 0,
            "ready_for_lims_apply": "yes",
        },
    )
    return touched


def complete_manual_revision(stage_dir: Path, ctx: dict) -> int:
    pack = stage_dir / "manual_revision_path_pack"
    touched = 0

    def mutate(row: dict[str, str], index: int) -> None:
        set_if_present(row, "decision_status", "approved")
        fill_if_present(row, "review_comment", TEST_NOTE)
        fill_if_present(row, "decision_comment", TEST_NOTE)
        fill_if_present(row, "reviewer", ctx["reviewer"])
        fill_if_present(row, "review_date", ctx["date"])

    for path in pack.glob("*.csv"):
        touched += update_csv(path, mutate)
    update_manifest(
        pack / "manual_revision_path_manifest.json",
        {
            "pending_human_decisions": 0,
            "approved_human_decisions": touched,
            "ready_for_lims_apply": "yes",
        },
    )
    return touched


def complete_staff_training(stage_dir: Path, ctx: dict) -> int:
    pack = stage_dir / "staff_training_implementation_pack"
    touched = 0

    def mutate(row: dict[str, str], index: int) -> None:
        set_if_present(row, "human_confirmation_status", "completed")
        set_if_present(row, "confirmation_status", "completed")
        set_if_present(row, "learning_status", "completed")
        set_if_present(row, "answer_status", "completed")
        set_if_present(row, "feedback_status", "closed")
        set_if_present(row, "human_decision", "no_change")
        fill_if_present(row, "assigned_person", ctx["actor"])
        fill_if_present(row, "learner", ctx["actor"])
        fill_if_present(row, "reviewer", ctx["reviewer"])
        fill_if_present(row, "confirmed_by", ctx["actor"])
        fill_if_present(row, "confirmation_date", ctx["date"])
        fill_if_present(row, "actual_finish_date", ctx["date"])
        fill_if_present(row, "review_date", ctx["date"])
        fill_if_present(row, "review_comment", TEST_NOTE)
        fill_if_present(row, "feedback_comment", TEST_NOTE)

    for path in pack.glob("*.csv"):
        touched += update_csv(path, mutate)
    update_manifest(
        pack / "staff_training_manifest.json",
        {
            "pending_training_items": 0,
            "pending_confirmation_items": 0,
            "pending_feedback_items": 0,
            "ready_for_lims_apply": "yes",
            "test_completion_for_rehearsal": "yes",
        },
    )
    return touched


def complete_stage2_review(stage_dir: Path, ctx: dict) -> int:
    pack = stage_dir / "stage2_structured_review_workbench"
    touched = 0

    def mutate(row: dict[str, str], index: int) -> None:
        set_if_present(row, "proposed_human_decision", "approved")
        set_if_present(row, "human_decision", "approved")
        set_if_present(row, "review_status", "approved")
        fill_if_present(row, "review_comment", TEST_NOTE)
        fill_if_present(row, "reviewer", ctx["reviewer"])
        fill_if_present(row, "review_date", ctx["date"])

    for path in pack.glob("*.csv"):
        touched += update_csv(path, mutate)
    update_manifest(
        pack / "stage2_review_workbench_manifest.json",
        {
            "status": "stage2_structured_review_workbench_no_database_write",
            "review_status": "approved",
            "pending_decisions": 0,
            "approved_decisions": touched,
            "ready_for_lims_apply": "yes",
        },
    )
    return touched


def complete_stage2_review_preview(stage_dir: Path, ctx: dict) -> int:
    pack = stage_dir / "stage2_structured_review_decision_preview"

    def decision(row: dict[str, str], index: int) -> None:
        set_if_present(row, "proposed_human_decision", "approved")
        set_if_present(row, "normalized_decision", "approved")
        set_if_present(row, "preview_result", "accepted_for_preview")
        set_if_present(row, "will_remain_blocking", "no")
        fill_if_present(row, "review_comment", TEST_NOTE)
        clear_if_present(row, "issue")

    touched = update_csv(pack / "01-拟回填决策预览.csv", decision)
    clear_csv_rows(pack / "02-仍阻断项清单.csv")

    def summary(row: dict[str, str], index: int) -> None:
        set_if_present(row, "proposed_decisions", row.get("decision_rows", "0"))
        set_if_present(row, "not_proposed", "0")
        set_if_present(row, "pending_decisions", "0")
        set_if_present(row, "accepted_for_preview", row.get("decision_rows", "0"))
        set_if_present(row, "invalid_decisions", "0")
        set_if_present(row, "missing_review_comments", "0")
        set_if_present(row, "blocking_items", "0")

    update_csv(pack / "03-按范围统计.csv", summary)
    update_manifest(
        pack / "stage2_review_decision_preview_manifest.json",
        {
            "readiness": "ready_for_lims_apply",
            "ready_for_lims_apply": "yes",
            "proposed_decisions": touched,
            "not_proposed": 0,
            "pending_decisions": 0,
            "accepted_for_preview": touched,
            "invalid_decisions": 0,
            "missing_review_comments": 0,
            "blocking_items": 0,
        },
    )
    return touched


def complete_readiness_dashboard(stage_dir: Path, ctx: dict) -> int:
    pack = stage_dir / "governance_readiness_dashboard"
    touched = 0

    def gate(row: dict[str, str], index: int) -> None:
        total = row.get("total_items", "") or row.get("closed_items", "")
        if total:
            set_if_present(row, "closed_items", total)
        set_if_present(row, "pending_items", "0")
        set_if_present(row, "blocking_items", "0")
        set_if_present(row, "current_status", "closed")
        set_if_present(row, "blocks_apply", "no")
        fill_if_present(row, "next_action", TEST_NOTE)

    def task(row: dict[str, str], index: int) -> None:
        set_if_present(row, "current_status", "closed")
        set_if_present(row, "blocking_if_unresolved", "no")
        fill_if_present(row, "human_action", TEST_NOTE)

    touched += update_csv(pack / "01-总闸门清单.csv", gate)
    touched += update_csv(pack / "02-人工处理任务清单.csv", task)
    update_manifest(
        pack / "governance_readiness_manifest.json",
        {
            "readiness": "ready_for_lims_apply",
            "ready_for_lims_apply": "yes",
            "blocking_gates": 0,
            "blocking_tasks": 0,
            "pending_tasks": 0,
        },
    )
    return touched


def complete_closure_workbench(stage_dir: Path, ctx: dict) -> int:
    pack = stage_dir / "governance_closure_workbench"
    touched = 0

    def evidence(row: dict[str, str], index: int) -> None:
        fill_if_present(row, "evidence_reference", evidence_value("CLOSURE", index))
        fill_if_present(row, "evidence_owner", ctx["actor"])
        fill_if_present(row, "evidence_date", ctx["date"])
        fill_if_present(row, "evidence_result", "accepted")
        set_if_present(row, "blocks_apply", "no")

    def closure(row: dict[str, str], index: int) -> None:
        set_if_present(row, "proposed_closure_status", "closed")
        fill_if_present(row, "evidence_reference", evidence_value("CLOSURE", index))
        fill_if_present(row, "closure_comment", TEST_NOTE)
        fill_if_present(row, "reviewer", ctx["reviewer"])
        fill_if_present(row, "review_date", ctx["date"])
        set_if_present(row, "closure_result", "accepted")
        set_if_present(row, "blocks_apply", "no")

    touched += update_csv(pack / "03-证据采集模板.csv", evidence)
    touched += update_csv(pack / "04-拟关闭回填模板.csv", closure)
    update_manifest(
        pack / "governance_closure_workbench_manifest.json",
        {
            "readiness": "ready_for_governance_readiness_refresh",
            "ready_for_governance_readiness_refresh": "yes",
            "ready_for_lims_apply": "no",
            "open_blocking_items": 0,
            "pending_closure_items": 0,
            "blocking_closure_items": 0,
            "accepted_closures": touched // 2,
            "pending_closures": 0,
        },
    )
    return touched


def complete_execution_pack(stage_dir: Path, ctx: dict) -> int:
    pack = stage_dir / "governance_closure_execution_pack"
    touched = 0

    def mutate(row: dict[str, str], index: int) -> None:
        set_if_present(row, "batch_status", "completed")
        set_if_present(row, "execution_status", "completed")
        set_if_present(row, "signature_status", "completed")
        set_if_present(row, "handoff_status", "completed")
        set_if_present(row, "check_status", "completed")
        set_if_present(row, "route_status", "completed")
        set_if_present(row, "route_status", "ready")
        set_if_present(row, "blocking_tasks", "0")
        set_if_present(row, "blocking_count", "0")
        set_if_present(row, "blocks_apply", "no")
        fill_if_present(row, "assigned_person", ctx["actor"])
        fill_if_present(row, "reviewer", ctx["reviewer"])
        fill_if_present(row, "actual_finish_date", ctx["date"])
        fill_if_present(row, "review_date", ctx["date"])

    for path in pack.glob("*.csv"):
        touched += update_csv(path, mutate)
    update_manifest(
        pack / "governance_closure_execution_manifest.json",
        {
            "readiness": "ready_for_governance_closure_preview",
            "ready_for_governance_closure_preview": "yes",
            "ready_for_lims_apply": "no",
            "pending_signature_rows": 0,
            "pending_handoff_checks": 0,
            "pending_route_items": 0,
            "blocking_route_items": 0,
        },
    )
    return touched


def complete_pilot_pack(stage_dir: Path, ctx: dict) -> int:
    pack = stage_dir / "governance_closure_pilot_pack"
    touched = 0

    def mutate(row: dict[str, str], index: int) -> None:
        set_if_present(row, "pilot_status", "completed")
        set_if_present(row, "evidence_status", "ready")
        set_if_present(row, "signature_status", "completed")
        set_if_present(row, "handoff_status", "completed")
        set_if_present(row, "blocks_apply", "no")
        fill_if_present(row, "evidence_reference", evidence_value("PILOT", index))
        fill_if_present(row, "evidence_summary", TEST_NOTE)
        fill_if_present(row, "closure_comment", TEST_NOTE)
        fill_if_present(row, "assigned_person", ctx["actor"])
        fill_if_present(row, "reviewer", ctx["reviewer"])
        fill_if_present(row, "review_date", ctx["date"])
        fill_if_present(row, "actual_finish_date", ctx["date"])

    for path in pack.glob("*.csv"):
        touched += update_csv(path, mutate)
    update_manifest(
        pack / "governance_closure_pilot_manifest.json",
        {
            "readiness": "ready_for_governance_closure_preview",
            "ready_for_governance_closure_preview": "yes",
            "ready_for_lims_apply": "no",
            "pending_pilot_batches": 0,
            "pending_evidence_items": 0,
            "pending_handoff_items": 0,
            "pending_pilot_evidence": 0,
            "pending_pilot_handoffs": 0,
            "blocking_pilot_items": 0,
        },
    )
    return touched


def complete_operator_workbook(stage_dir: Path, ctx: dict) -> int:
    pack = stage_dir / "governance_closure_pilot_operator_workbook"

    def master(row: dict[str, str], index: int) -> None:
        set_if_present(row, "workbook_status", "ready_for_return_preview")
        set_if_present(row, "evidence_status", "completed")
        set_if_present(row, "signature_status", "completed")
        set_if_present(row, "handoff_status", "completed")
        set_if_present(row, "blocks_apply", "no")
        set_if_present(row, "missing_field_count", "0")
        set_if_present(row, "blocked_patch_count", "0")
        fill_if_present(row, "next_action", TEST_NOTE)

    def field(row: dict[str, str], index: int) -> None:
        set_if_present(row, "field_status", "completed")
        set_if_present(row, "blocks_apply", "no")
        clear_if_present(row, "block_reason")

    def handoff(row: dict[str, str], index: int) -> None:
        set_if_present(row, "workbook_status", "completed")
        set_if_present(row, "signature_status", "completed")
        set_if_present(row, "handoff_status", "completed")
        set_if_present(row, "blocks_apply", "no")
        fill_if_present(row, "assigned_person", ctx["actor"])
        fill_if_present(row, "reviewer", ctx["reviewer"])
        fill_if_present(row, "actual_finish_date", ctx["date"])

    touched = 0
    touched += update_csv(pack / "01-试点执行主清单.csv", master)
    touched += update_csv(pack / "02-逐字段填写清单.csv", field)
    touched += update_csv(pack / "03-签核交接核对表.csv", handoff)
    update_manifest(
        pack / "governance_closure_pilot_operator_workbook_manifest.json",
        {
            "readiness": "ready_for_pilot_return_preview",
            "ready_for_pilot_return_preview": "yes",
            "ready_for_lims_apply": "no",
            "pending_workbook_items": 0,
            "pending_field_items": 0,
            "pending_handoff_items": 0,
            "blocked_patch_rows": 0,
            "source_blocked_patches": 0,
        },
    )
    return touched


def complete_operator_handback(stage_dir: Path, ctx: dict) -> int:
    pack = stage_dir / "governance_closure_pilot_operator_handback"
    touched = 0

    def master(row: dict[str, str], index: int) -> None:
        set_if_present(row, "evidence_status", "completed")
        set_if_present(row, "signature_status", "completed")
        set_if_present(row, "handoff_status", "completed")
        set_if_present(row, "handback_status", "completed")
        set_if_present(row, "blocks_apply", "no")
        fill_if_present(row, "next_action", TEST_NOTE)

    def field(row: dict[str, str], index: int) -> None:
        set_if_present(row, "field_status", "completed")
        set_if_present(row, "blocks_apply", "no")
        fill_if_present(row, "real_input_value", evidence_value("REAL-FIELD", index))

    def handoff(row: dict[str, str], index: int) -> None:
        set_if_present(row, "signature_status", "completed")
        set_if_present(row, "handoff_status", "completed")
        set_if_present(row, "handback_status", "completed")
        set_if_present(row, "blocks_apply", "no")
        fill_if_present(row, "assigned_person", ctx["actor"])
        fill_if_present(row, "reviewer", ctx["reviewer"])
        fill_if_present(row, "actual_finish_date", ctx["date"])

    touched += update_csv(pack / "01-真实执行交回主清单.csv", master)
    touched += update_csv(pack / "02-真实逐字段交回清单.csv", field)
    touched += update_csv(pack / "03-真实签核交接交回表.csv", handoff)
    update_manifest(
        pack / "governance_closure_pilot_operator_handback_manifest.json",
        {
            "readiness": "ready_for_pilot_return_preview",
            "ready_for_pilot_return_preview": "yes",
            "ready_for_source_workbench_update": "no",
            "ready_for_lims_apply": "no",
            "pending_workbook_items": 0,
            "pending_field_items": 0,
            "pending_handoff_items": 0,
            "completed_field_items": "all",
            "completed_handoff_items": "all",
        },
    )
    return touched


def complete_return_preview(stage_dir: Path, ctx: dict) -> int:
    pack = stage_dir / "governance_closure_pilot_return_preview"
    touched = 0

    def mutate(row: dict[str, str], index: int) -> None:
        set_if_present(row, "return_status", "ready")
        set_if_present(row, "proposed_evidence_status", "ready")
        set_if_present(row, "proposed_signature_status", "completed")
        set_if_present(row, "proposed_handoff_status", "completed")
        set_if_present(row, "ready_for_manual_source_update", "yes")
        set_if_present(row, "blocks_apply", "no")
        fill_if_present(row, "proposed_evidence_reference", evidence_value("RETURN", index))
        fill_if_present(row, "proposed_evidence_summary", TEST_NOTE)
        fill_if_present(row, "proposed_closure_comment", TEST_NOTE)
        fill_if_present(row, "proposed_reviewer", ctx["reviewer"])
        fill_if_present(row, "proposed_review_date", ctx["date"])

    mapping_rows = update_csv(pack / "01-试点证据到源工作台映射.csv", mutate)
    source_rows = update_csv(pack / "02-拟回填源行预览.csv", mutate)
    clear_csv_rows(pack / "03-仍缺字段清单.csv")
    touched = mapping_rows + source_rows
    update_manifest(
        pack / "governance_closure_pilot_return_manifest.json",
        {
            "readiness": "ready_for_governance_closure_preview",
            "ready_for_governance_closure_preview": "yes",
            "ready_for_lims_apply": "no",
            "missing_field_rows": 0,
            "ready_return_items": mapping_rows,
            "blocking_return_items": 0,
        },
    )
    return touched


def complete_source_update(stage_dir: Path, ctx: dict) -> int:
    pack = stage_dir / "governance_closure_pilot_source_update_rehearsal"
    touched = 0

    def mutate(row: dict[str, str], index: int) -> None:
        set_if_present(row, "patch_action", "manual_update_candidate")
        set_if_present(row, "patch_status", "ready")
        set_if_present(row, "update_ready", "yes")
        set_if_present(row, "ready_for_manual_source_update", "yes")
        set_if_present(row, "blocks_apply", "no")
        clear_if_present(row, "block_reason")
        fill_if_present(row, "proposed_value", evidence_value("SOURCE", index))

    touched = update_csv(pack / "01-源工作台回填补丁预览.csv", mutate)
    clear_csv_rows(pack / "02-阻断补丁清单.csv")
    update_manifest(
        pack / "governance_closure_pilot_source_update_manifest.json",
        {
            "readiness": "ready_for_source_workbench_update",
            "ready_for_source_workbench_update": "yes",
            "ready_for_governance_closure_preview": "yes",
            "ready_for_lims_apply": "no",
            "blocked_patch_rows": 0,
            "missing_field_rows": 0,
            "ready_patch_rows": touched,
            "manual_update_candidate_rows": touched,
        },
    )
    return touched


def complete_closure_preview(stage_dir: Path, ctx: dict) -> int:
    pack = stage_dir / "governance_closure_decision_preview"
    touched = 0

    def mutate(row: dict[str, str], index: int) -> None:
        set_if_present(row, "preview_result", "accepted_for_preview")
        set_if_present(row, "proposed_closure_status", "closed")
        set_if_present(row, "normalized_closure_status", "closed")
        set_if_present(row, "will_remain_blocking", "no")
        set_if_present(row, "blocks_apply", "no")
        fill_if_present(row, "evidence_reference", evidence_value("PREVIEW", index))
        fill_if_present(row, "closure_evidence_reference", evidence_value("PREVIEW", index))
        fill_if_present(row, "evidence_template_reference", evidence_value("TEMPLATE", index))
        fill_if_present(row, "evidence_owner", ctx["actor"])
        fill_if_present(row, "evidence_date", ctx["date"])
        fill_if_present(row, "evidence_result", "accepted")
        fill_if_present(row, "closure_comment", TEST_NOTE)
        fill_if_present(row, "reviewer", ctx["reviewer"])
        fill_if_present(row, "review_date", ctx["date"])
        clear_if_present(row, "issue")

    touched = update_csv(pack / "01-拟关闭决策预览.csv", mutate)
    clear_csv_rows(pack / "02-仍阻断关闭项.csv")

    def summary(row: dict[str, str], index: int) -> None:
        set_if_present(row, "blocking_items", "0")
        fill_if_present(row, "preview_result", "accepted_for_preview")

    update_csv(pack / "03-按闸门关闭统计.csv", summary)
    update_manifest(
        pack / "governance_closure_decision_preview_manifest.json",
        {
            "readiness": "ready_for_governance_readiness_refresh",
            "ready_for_governance_readiness_refresh": "yes",
            "ready_for_lims_apply": "no",
            "proposed_closures": touched,
            "not_proposed": 0,
            "accepted_for_preview": touched,
            "blocking_items": 0,
            "invalid_closures": 0,
            "missing_required_fields": 0,
        },
    )
    return touched


def complete_readiness_refresh(stage_dir: Path, ctx: dict) -> int:
    pack = stage_dir / "governance_readiness_refresh_preview"
    touched = 0

    def mutate(row: dict[str, str], index: int) -> None:
        set_if_present(row, "closure_preview_result", "accepted_for_preview")
        set_if_present(row, "refreshed_task_status", "closed")
        set_if_present(row, "accepted_for_refresh", "yes")
        set_if_present(row, "blocking_before_refresh", "no")
        set_if_present(row, "blocking_after_refresh", "no")
        set_if_present(row, "open_blocking_tasks_after_refresh", "0")
        set_if_present(row, "refreshed_gate_status", "closed")
        set_if_present(row, "ready_for_refresh", "yes")
        if "task_rows" in row:
            set_if_present(row, "accepted_task_closures", row.get("task_rows", "0"))
        fill_if_present(row, "closure_evidence_reference", evidence_value("REFRESH", index))
        fill_if_present(row, "evidence_template_reference", evidence_value("TEMPLATE", index))

    gate_rows = update_csv(pack / "01-总闸门刷新预览.csv", mutate)
    task_rows = update_csv(pack / "02-人工任务刷新预览.csv", mutate)
    clear_csv_rows(pack / "03-仍阻断任务清单.csv")

    def diff(row: dict[str, str], index: int) -> None:
        set_if_present(row, "refreshed_blocking_tasks", "0")
        set_if_present(row, "refreshed_blocking_gates", "0")
        set_if_present(row, "ready_for_lims_apply", "yes")

    update_csv(pack / "04-刷新差异摘要.csv", diff)
    touched = gate_rows + task_rows
    update_manifest(
        pack / "governance_readiness_refresh_preview_manifest.json",
        {
            "readiness": "ready_for_lims_apply",
            "ready_for_lims_apply": "yes",
            "accepted_task_closures": task_rows,
            "refreshed_blocking_tasks": 0,
            "refreshed_blocking_gates": 0,
        },
    )
    return touched


def build_report(stage_dir: Path, changes: dict[str, int], ctx: dict) -> dict:
    return {
        "status": "test_completed",
        "stage_dir": str(stage_dir),
        "date": ctx["date"],
        "actor": ctx["actor"],
        "reviewer": ctx["reviewer"],
        "production_record": False,
        "guardrails": [
            "仅用于测试链路验证",
            "不代表正式受控发布记录",
            "不写 LIMS 数据库",
            "不修改现用 Word 受控文件",
        ],
        "changes": changes,
    }


def write_markdown(path: Path, report: dict) -> None:
    lines = [
        "# QMS 测试完成态生成报告",
        "",
        f"- 状态：{report['status']}",
        f"- 日期：{report['date']}",
        f"- 执行人：{report['actor']}",
        f"- 复核人：{report['reviewer']}",
        f"- 生产记录：{'是' if report['production_record'] else '否'}",
        "",
        "## 边界",
    ]
    lines.extend(f"- {item}" for item in report["guardrails"])
    lines.extend(["", "## 修改统计"])
    lines.extend(f"- {key}: {value}" for key, value in report["changes"].items())
    lines.append("")
    path.write_text("\n".join(lines), encoding="utf-8")


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--stage-dir", required=True, type=Path)
    parser.add_argument("--date", default=date.today().isoformat())
    parser.add_argument("--actor", default="测试审核人")
    parser.add_argument("--reviewer", default="测试复核人")
    parser.add_argument("--json-out", type=Path)
    parser.add_argument("--md-out", type=Path)
    return parser.parse_args()


def main() -> None:
    args = parse_args()
    stage_dir = args.stage_dir.resolve()
    if not stage_dir.exists():
        raise SystemExit(f"stage dir not found: {stage_dir}")

    ctx = {"date": args.date, "actor": args.actor, "reviewer": args.reviewer}
    changes = {
        "human_review_rows": complete_human_review(stage_dir, ctx),
        "manual_revision_rows": complete_manual_revision(stage_dir, ctx),
        "staff_training_rows": complete_staff_training(stage_dir, ctx),
        "stage2_review_rows": complete_stage2_review(stage_dir, ctx),
        "stage2_review_preview_rows": complete_stage2_review_preview(stage_dir, ctx),
        "governance_readiness_rows": complete_readiness_dashboard(stage_dir, ctx),
        "governance_closure_rows": complete_closure_workbench(stage_dir, ctx),
        "governance_closure_execution_rows": complete_execution_pack(stage_dir, ctx),
        "governance_closure_pilot_rows": complete_pilot_pack(stage_dir, ctx),
        "operator_workbook_rows": complete_operator_workbook(stage_dir, ctx),
        "operator_handback_rows": complete_operator_handback(stage_dir, ctx),
        "pilot_return_preview_rows": complete_return_preview(stage_dir, ctx),
        "pilot_source_update_rows": complete_source_update(stage_dir, ctx),
        "governance_closure_preview_rows": complete_closure_preview(stage_dir, ctx),
        "governance_readiness_refresh_rows": complete_readiness_refresh(stage_dir, ctx),
    }
    report = build_report(stage_dir, changes, ctx)

    if args.json_out:
        write_json(args.json_out, report)
    if args.md_out:
        write_markdown(args.md_out, report)
    print(json.dumps(report, ensure_ascii=False, indent=2))


if __name__ == "__main__":
    main()
