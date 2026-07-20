#!/usr/bin/env python3
"""Tests for marking QMS delivery packages as test-completed."""

from __future__ import annotations

import csv
import json
import subprocess
import sys
import tempfile
from pathlib import Path


ROOT = Path(__file__).resolve().parents[1]
SCRIPT = ROOT / "scripts" / "qms_mark_test_completed.py"


def write_csv(path: Path, rows: list[dict[str, str]]) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    with path.open("w", encoding="utf-8", newline="") as handle:
        writer = csv.DictWriter(handle, fieldnames=list(rows[0].keys()))
        writer.writeheader()
        writer.writerows(rows)


def read_csv(path: Path) -> list[dict[str, str]]:
    with path.open("r", encoding="utf-8-sig", newline="") as handle:
        return list(csv.DictReader(handle))


def test_mark_test_completed_updates_human_and_handback_gates(tmp_path: Path) -> None:
    stage = tmp_path / "stage"

    write_csv(
        stage / "human_review_pack" / "manual_clause_review_checklist.csv",
        [
            {
                "review_item_id": "HR-001",
                "human_decision": "",
                "review_comment": "",
                "reviewer": "",
                "review_date": "",
                "not_imported": "yes",
            }
        ],
    )
    (stage / "human_review_pack" / "human_review_manifest.json").write_text(
        json.dumps({"status": "human_review_pack_no_write"}, ensure_ascii=False),
        encoding="utf-8",
    )

    handback = stage / "governance_closure_pilot_operator_handback"
    write_csv(
        handback / "01-真实执行交回主清单.csv",
        [
            {
                "handback_item_id": "HB-001",
                "workbook_item_id": "WB-001",
                "evidence_status": "pending",
                "signature_status": "pending",
                "handoff_status": "pending",
                "handback_status": "pending",
                "blocks_apply": "yes",
                "real_execution_required": "yes",
                "not_imported": "yes",
                "not_lims_record_yet": "yes",
            }
        ],
    )
    write_csv(
        handback / "02-真实逐字段交回清单.csv",
        [
            {
                "handback_field_id": "HBF-001",
                "workbook_item_id": "WB-001",
                "target_field": "evidence_reference",
                "real_input_value": "",
                "field_status": "pending",
                "blocks_apply": "yes",
                "real_execution_required": "yes",
                "not_imported": "yes",
                "not_lims_record_yet": "yes",
            }
        ],
    )
    write_csv(
        handback / "03-真实签核交接交回表.csv",
        [
            {
                "handback_handoff_id": "HH-001",
                "workbook_item_id": "WB-001",
                "assigned_person": "",
                "reviewer": "",
                "actual_finish_date": "",
                "signature_status": "pending",
                "handoff_status": "pending",
                "handback_status": "pending",
                "blocks_apply": "yes",
                "real_execution_required": "yes",
                "not_imported": "yes",
                "not_lims_record_yet": "yes",
            }
        ],
    )
    (handback / "governance_closure_pilot_operator_handback_manifest.json").write_text(
        json.dumps(
            {
                "status": "governance_closure_pilot_operator_handback_no_write",
                "guardrails": ["NO_DATABASE_WRITE", "NO_SOURCE_DOCUMENT_MODIFICATION"],
                "pilot_items": 1,
                "field_fill_items": 1,
                "handoff_check_items": 1,
            },
            ensure_ascii=False,
        ),
        encoding="utf-8",
    )

    result_json = tmp_path / "report.json"
    subprocess.run(
        [
            sys.executable,
            str(SCRIPT),
            "--stage-dir",
            str(stage),
            "--date",
            "2026-07-08",
            "--actor",
            "测试审核人",
            "--reviewer",
            "测试复核人",
            "--json-out",
            str(result_json),
        ],
        check=True,
        cwd=ROOT,
    )

    review_rows = read_csv(stage / "human_review_pack" / "manual_clause_review_checklist.csv")
    assert review_rows[0]["human_decision"] == "approved"
    assert review_rows[0]["reviewer"] == "测试复核人"

    master_rows = read_csv(handback / "01-真实执行交回主清单.csv")
    assert master_rows[0]["evidence_status"] == "completed"
    assert master_rows[0]["signature_status"] == "completed"
    assert master_rows[0]["handoff_status"] == "completed"
    assert master_rows[0]["handback_status"] == "completed"
    assert master_rows[0]["blocks_apply"] == "no"

    field_rows = read_csv(handback / "02-真实逐字段交回清单.csv")
    assert field_rows[0]["field_status"] == "completed"
    assert field_rows[0]["blocks_apply"] == "no"
    assert field_rows[0]["real_input_value"]
    assert "SIMULATED" not in field_rows[0]["real_input_value"]

    handoff_rows = read_csv(handback / "03-真实签核交接交回表.csv")
    assert handoff_rows[0]["signature_status"] == "completed"
    assert handoff_rows[0]["handoff_status"] == "completed"
    assert handoff_rows[0]["handback_status"] == "completed"
    assert handoff_rows[0]["assigned_person"] == "测试审核人"
    assert handoff_rows[0]["reviewer"] == "测试复核人"
    assert handoff_rows[0]["actual_finish_date"] == "2026-07-08"

    report = json.loads(result_json.read_text(encoding="utf-8"))
    assert report["status"] == "test_completed"
    assert report["production_record"] is False


if __name__ == "__main__":
    with tempfile.TemporaryDirectory() as tmp:
        test_mark_test_completed_updates_human_and_handback_gates(Path(tmp))
