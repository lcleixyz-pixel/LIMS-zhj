#!/usr/bin/env python3
"""Smoke tests for the pilot operator real handback package scripts."""

from __future__ import annotations

import csv
import json
import subprocess
import sys
import tempfile
from pathlib import Path


ROOT = Path(__file__).resolve().parents[1]
BUILD_SCRIPT = ROOT / "scripts/qms_governance_closure_pilot_operator_handback_build.py"
CHECK_SCRIPT = ROOT / "scripts/qms_governance_closure_pilot_operator_handback_check.py"


def write_csv(path: Path, rows: list[dict[str, str]], fieldnames: list[str]) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    with path.open("w", encoding="utf-8", newline="") as handle:
        writer = csv.DictWriter(handle, fieldnames=fieldnames)
        writer.writeheader()
        writer.writerows(rows)


def make_minimal_workbook(base: Path) -> Path:
    workbook = base / "workbook"
    (workbook / "task_cards").mkdir(parents=True)
    (workbook / "task_cards/GCPOW-001.md").write_text("不写数据库\n不代表人工评审通过\n", encoding="utf-8")
    manifest = {
        "status": "governance_closure_pilot_operator_workbook_no_write",
        "readiness": "operator_workbook_pending_human_execution",
        "ready_for_pilot_return_preview": "no",
        "ready_for_source_workbench_update": "no",
        "ready_for_lims_apply": "no",
        "guardrails": [
            "不写数据库",
            "不修改试点包",
            "不代表人工评审通过",
            "不代表真实培训完成",
            "不代表受控发布",
            "已取得 CMA",
            "CNAS 申请中",
            "2022 程序清单",
            "jewelry-qms 仍为建设中系统",
            "不写入质量手册正文",
        ],
        "files": {
            "manifest": "governance_closure_pilot_operator_workbook_manifest.json",
            "master": "01-试点执行主清单.csv",
            "field_checklist": "02-逐字段填写清单.csv",
            "handoff_checklist": "03-签核交接核对表.csv",
            "task_card_dir": "task_cards",
        },
        "counts": {
            "pilot_items": 1,
            "field_fill_items": 2,
            "handoff_check_items": 1,
            "task_cards": 1,
            "source_missing_fields": 2,
            "source_blocked_patches": 2,
            "source_workbench_modified": 0,
            "database_write_performed": 0,
        },
    }
    (workbook / "governance_closure_pilot_operator_workbook_manifest.json").write_text(
        json.dumps(manifest, ensure_ascii=False, indent=2) + "\n",
        encoding="utf-8",
    )
    write_csv(
        workbook / "01-试点执行主清单.csv",
        [
            {
                "workbook_item_id": "GCPOW-001",
                "pilot_evidence_id": "GCPE-001",
                "pilot_batch_id": "GCPB-001",
                "pilot_handoff_id": "GCPH-001",
                "closure_item_id": "GC-001",
                "source_task_id": "TASK-001",
                "gate_id": "GR-01",
                "task_group": "field_confirmation",
                "object_code": "JL-001",
                "object_name": "测试记录",
                "owner_role": "技术负责人",
                "missing_field_count": "2",
                "blocked_patch_count": "2",
                "evidence_status": "pending",
                "signature_status": "pending",
                "handoff_status": "pending",
                "workbook_status": "pending",
                "blocks_apply": "yes",
                "next_action": "补齐真实证据。",
                "source_pilot_evidence_file": "pilot.csv",
                "source_pilot_handoff_file": "handoff.csv",
                "not_imported": "yes",
                "not_real_record": "yes",
            }
        ],
        [
            "workbook_item_id",
            "pilot_evidence_id",
            "pilot_batch_id",
            "pilot_handoff_id",
            "closure_item_id",
            "source_task_id",
            "gate_id",
            "task_group",
            "object_code",
            "object_name",
            "owner_role",
            "missing_field_count",
            "blocked_patch_count",
            "evidence_status",
            "signature_status",
            "handoff_status",
            "workbook_status",
            "blocks_apply",
            "next_action",
            "source_pilot_evidence_file",
            "source_pilot_handoff_file",
            "not_imported",
            "not_real_record",
        ],
    )
    write_csv(
        workbook / "02-逐字段填写清单.csv",
        [
            {
                "field_task_id": "GCPOW-001-F01",
                "workbook_item_id": "GCPOW-001",
                "return_item_id": "GCPR-001",
                "pilot_evidence_id": "GCPE-001",
                "closure_item_id": "GC-001",
                "field_group": "pilot_evidence",
                "missing_field": "evidence_reference",
                "target_file": "governance_closure_workbench/03-证据采集模板.csv",
                "target_field": "evidence_reference",
                "required_input": "填写真实证据编号。",
                "why_required": "需要可追溯证据。",
                "owner_role": "技术负责人",
                "patch_id": "GCPSU-001",
                "patch_action": "blocked_no_update",
                "block_reason": "pilot_return_not_ready",
                "field_status": "pending",
                "blocks_apply": "yes",
                "not_imported": "yes",
                "not_real_record": "yes",
            },
            {
                "field_task_id": "GCPOW-001-F02",
                "workbook_item_id": "GCPOW-001",
                "return_item_id": "GCPR-001",
                "pilot_evidence_id": "GCPE-001",
                "closure_item_id": "GC-001",
                "field_group": "closure_comment",
                "missing_field": "closure_comment",
                "target_file": "governance_closure_workbench/04-拟关闭回填模板.csv",
                "target_field": "closure_comment",
                "required_input": "填写真实关闭意见。",
                "why_required": "需要关闭意见。",
                "owner_role": "技术负责人",
                "patch_id": "GCPSU-002",
                "patch_action": "blocked_no_update",
                "block_reason": "pilot_return_not_ready",
                "field_status": "pending",
                "blocks_apply": "yes",
                "not_imported": "yes",
                "not_real_record": "yes",
            },
        ],
        [
            "field_task_id",
            "workbook_item_id",
            "return_item_id",
            "pilot_evidence_id",
            "closure_item_id",
            "field_group",
            "missing_field",
            "target_file",
            "target_field",
            "required_input",
            "why_required",
            "owner_role",
            "patch_id",
            "patch_action",
            "block_reason",
            "field_status",
            "blocks_apply",
            "not_imported",
            "not_real_record",
        ],
    )
    write_csv(
        workbook / "03-签核交接核对表.csv",
        [
            {
                "workbook_item_id": "GCPOW-001",
                "pilot_handoff_id": "GCPH-001",
                "pilot_batch_id": "GCPB-001",
                "execution_batch_id": "GCEB-001",
                "owner_role": "技术负责人",
                "assigned_person": "",
                "reviewer": "",
                "planned_finish_date": "",
                "actual_finish_date": "",
                "signature_status": "pending",
                "handoff_status": "pending",
                "required_fields": "evidence_reference | closure_comment",
                "workbook_status": "pending",
                "blocks_apply": "yes",
                "not_imported": "yes",
                "not_real_record": "yes",
            }
        ],
        [
            "workbook_item_id",
            "pilot_handoff_id",
            "pilot_batch_id",
            "execution_batch_id",
            "owner_role",
            "assigned_person",
            "reviewer",
            "planned_finish_date",
            "actual_finish_date",
            "signature_status",
            "handoff_status",
            "required_fields",
            "workbook_status",
            "blocks_apply",
            "not_imported",
            "not_real_record",
        ],
    )
    return workbook


def run_json(args: list[str], expect_success: bool = True) -> dict:
    completed = subprocess.run(args, text=True, capture_output=True, check=False)
    if expect_success and completed.returncode != 0:
        raise AssertionError(completed.stderr + completed.stdout)
    if not expect_success and completed.returncode == 0:
        raise AssertionError("expected command to fail")
    return json.loads(completed.stdout)


def test_build_and_check_initial_handback_package() -> None:
    with tempfile.TemporaryDirectory() as tmp:
        root = Path(tmp)
        workbook = make_minimal_workbook(root)
        handback = root / "handback"
        build_result = run_json([sys.executable, str(BUILD_SCRIPT), "--workbook-dir", str(workbook), "--output-dir", str(handback)])
        assert build_result["status"] == "governance_closure_pilot_operator_handback_no_write"
        assert build_result["ready_for_pilot_return_preview"] == "no"

        check_result = run_json([sys.executable, str(CHECK_SCRIPT), "--handback-dir", str(handback)])
        assert check_result["status"] == "passed"
        assert check_result["pending_field_items"] == 2
        assert check_result["ready_for_pilot_return_preview"] == "no"


def test_check_rejects_fake_completed_field_without_real_value() -> None:
    with tempfile.TemporaryDirectory() as tmp:
        root = Path(tmp)
        workbook = make_minimal_workbook(root)
        handback = root / "handback"
        run_json([sys.executable, str(BUILD_SCRIPT), "--workbook-dir", str(workbook), "--output-dir", str(handback)])

        field_path = handback / "02-真实逐字段交回清单.csv"
        with field_path.open("r", encoding="utf-8-sig", newline="") as handle:
            rows = list(csv.DictReader(handle))
            fieldnames = list(rows[0].keys())
        rows[0]["field_status"] = "completed"
        rows[0]["blocks_apply"] = "no"
        rows[0]["real_input_value"] = ""
        write_csv(field_path, rows, fieldnames)

        result = run_json([sys.executable, str(CHECK_SCRIPT), "--handback-dir", str(handback)], expect_success=False)
        finding_ids = {item["id"] for item in result["findings"]}
        assert "governance_closure_pilot_operator_handback_field_completed_without_real_value" in finding_ids


def main() -> int:
    test_build_and_check_initial_handback_package()
    test_check_rejects_fake_completed_field_without_real_value()
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
