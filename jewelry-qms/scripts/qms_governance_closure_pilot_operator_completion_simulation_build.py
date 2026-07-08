#!/usr/bin/env python3
"""Build a no-write simulated completion package for the pilot operator workbook."""

from __future__ import annotations

import argparse
import csv
import datetime as dt
import json
from pathlib import Path
from typing import Any


WORKBOOK_FILES = {
    "manifest": "governance_closure_pilot_operator_workbook_manifest.json",
    "master": "01-试点执行主清单.csv",
    "field_checklist": "02-逐字段填写清单.csv",
    "handoff_checklist": "03-签核交接核对表.csv",
}

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

GUARDRAILS = [
    "本包只读取 governance_closure_pilot_operator_workbook，不写数据库。",
    "本包不修改 governance_closure_pilot_operator_workbook、试点包、源工作台、治理关闭工作台或任何现用 Word 文件。",
    "本包为模拟完成，不代表真实执行完成，不代表人工评审通过，不代表真实培训完成，不代表受控发布。",
    "本包只用于验证未来人工补齐后的命令链路能否识别人工执行工作簿闸门为可回填预览。",
    "资质状态口径：已取得 CMA，CNAS 申请中；不得表述为已取得 CNAS。",
    "以 LIMS 当前导出的 2022 程序清单作为现行程序目录。",
    "jewelry-qms 仍为建设中系统，只进入实施计划、演练和治理准备材料，不写入质量手册正文。",
]

MASTER_FIELDS = [
    "simulation_item_id",
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
    "source_missing_field_count",
    "source_blocked_patch_count",
    "simulated_completed_field_count",
    "evidence_status",
    "signature_status",
    "handoff_status",
    "workbook_status",
    "blocks_apply",
    "simulated_completion",
    "simulation_marker",
    "not_imported",
    "not_real_record",
    "source_workbook_status",
    "simulation_note",
]

FIELD_FIELDS = [
    "simulation_field_id",
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
    "simulated_input_value",
    "why_required",
    "owner_role",
    "patch_id",
    "source_patch_action",
    "source_block_reason",
    "field_status",
    "blocks_apply",
    "simulated_completion",
    "simulation_marker",
    "not_imported",
    "not_real_record",
]

HANDOFF_FIELDS = [
    "simulation_handoff_id",
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
    "simulated_completion",
    "simulation_marker",
    "not_imported",
    "not_real_record",
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


def load_json(path: Path) -> dict[str, Any]:
    return json.loads(path.read_text(encoding="utf-8"))


def group_count(rows: list[dict[str, str]], key: str) -> dict[str, int]:
    counts: dict[str, int] = {}
    for row in rows:
        value = row.get(key, "")
        counts[value] = counts.get(value, 0) + 1
    return counts


def simulated_value(field_name: str, item_id: str) -> str:
    values = {
        "evidence_reference": f"SIMULATED-EVIDENCE-{item_id}-NOT-REAL-RECORD",
        "evidence_summary": "模拟说明：该字段假定已由人工补齐；不代表真实证据已形成。",
        "closure_comment": "模拟意见：该阻断项假定经人工复核可进入回填预览；不代表真实关闭。",
        "reviewer": "SIMULATED_REVIEWER_NOT_REAL_REVIEW",
        "review_date": "2026-07-07",
        "evidence_status": "completed",
        "signature_status": "completed",
        "handoff_status": "completed",
        "assigned_person": "SIMULATED_PERSON_NOT_REAL_EXECUTOR",
        "actual_finish_date": "2026-07-07",
    }
    return values.get(field_name, f"SIMULATED_VALUE_FOR_{field_name.upper()}_NOT_REAL_RECORD")


def build_simulation(workbook_dir: Path, output_dir: Path) -> dict[str, Any]:
    workbook_manifest = load_json(workbook_dir / WORKBOOK_FILES["manifest"])
    master_rows = read_csv(workbook_dir / WORKBOOK_FILES["master"])
    field_rows = read_csv(workbook_dir / WORKBOOK_FILES["field_checklist"])
    handoff_rows = read_csv(workbook_dir / WORKBOOK_FILES["handoff_checklist"])

    field_counts = group_count(field_rows, "workbook_item_id")
    generated_at = dt.datetime.now().replace(microsecond=0).isoformat()

    simulated_master: list[dict[str, Any]] = []
    for index, row in enumerate(master_rows, start=1):
        item_id = row.get("workbook_item_id", "")
        simulated_master.append(
            {
                "simulation_item_id": f"GCPOCS-{index:03d}",
                "workbook_item_id": item_id,
                "pilot_evidence_id": row.get("pilot_evidence_id", ""),
                "pilot_batch_id": row.get("pilot_batch_id", ""),
                "pilot_handoff_id": row.get("pilot_handoff_id", ""),
                "closure_item_id": row.get("closure_item_id", ""),
                "source_task_id": row.get("source_task_id", ""),
                "gate_id": row.get("gate_id", ""),
                "task_group": row.get("task_group", ""),
                "object_code": row.get("object_code", ""),
                "object_name": row.get("object_name", ""),
                "owner_role": row.get("owner_role", ""),
                "source_missing_field_count": row.get("missing_field_count", ""),
                "source_blocked_patch_count": row.get("blocked_patch_count", ""),
                "simulated_completed_field_count": field_counts.get(item_id, 0),
                "evidence_status": "completed",
                "signature_status": "completed",
                "handoff_status": "completed",
                "workbook_status": "ready_for_return_preview",
                "blocks_apply": "no",
                "simulated_completion": "yes",
                "simulation_marker": SIMULATION_MARKER,
                "not_imported": "yes",
                "not_real_record": "yes",
                "source_workbook_status": row.get("workbook_status", ""),
                "simulation_note": "模拟已补齐，仅用于验证命令闸门；不得替代真实人工执行。",
            }
        )

    simulated_fields: list[dict[str, Any]] = []
    for index, row in enumerate(field_rows, start=1):
        simulated_fields.append(
            {
                "simulation_field_id": f"GCPOCS-F{index:03d}",
                "field_task_id": row.get("field_task_id", ""),
                "workbook_item_id": row.get("workbook_item_id", ""),
                "return_item_id": row.get("return_item_id", ""),
                "pilot_evidence_id": row.get("pilot_evidence_id", ""),
                "closure_item_id": row.get("closure_item_id", ""),
                "field_group": row.get("field_group", ""),
                "missing_field": row.get("missing_field", ""),
                "target_file": row.get("target_file", ""),
                "target_field": row.get("target_field", ""),
                "required_input": row.get("required_input", ""),
                "simulated_input_value": simulated_value(row.get("missing_field", ""), row.get("workbook_item_id", "")),
                "why_required": row.get("why_required", ""),
                "owner_role": row.get("owner_role", ""),
                "patch_id": row.get("patch_id", ""),
                "source_patch_action": row.get("patch_action", ""),
                "source_block_reason": row.get("block_reason", ""),
                "field_status": "completed",
                "blocks_apply": "no",
                "simulated_completion": "yes",
                "simulation_marker": SIMULATION_MARKER,
                "not_imported": "yes",
                "not_real_record": "yes",
            }
        )

    simulated_handoffs: list[dict[str, Any]] = []
    for index, row in enumerate(handoff_rows, start=1):
        simulated_handoffs.append(
            {
                "simulation_handoff_id": f"GCPOCS-H{index:03d}",
                "workbook_item_id": row.get("workbook_item_id", ""),
                "pilot_handoff_id": row.get("pilot_handoff_id", ""),
                "pilot_batch_id": row.get("pilot_batch_id", ""),
                "execution_batch_id": row.get("execution_batch_id", ""),
                "owner_role": row.get("owner_role", ""),
                "assigned_person": "SIMULATED_PERSON_NOT_REAL_EXECUTOR",
                "reviewer": "SIMULATED_REVIEWER_NOT_REAL_REVIEW",
                "planned_finish_date": "2026-07-07",
                "actual_finish_date": "2026-07-07",
                "signature_status": "completed",
                "handoff_status": "completed",
                "required_fields": row.get("required_fields", ""),
                "workbook_status": "completed",
                "blocks_apply": "no",
                "simulated_completion": "yes",
                "simulation_marker": SIMULATION_MARKER,
                "not_imported": "yes",
                "not_real_record": "yes",
            }
        )

    output_dir.mkdir(parents=True, exist_ok=True)
    task_card_dir = output_dir / SIMULATION_FILES["task_card_dir"]
    task_card_dir.mkdir(exist_ok=True)

    write_csv(output_dir / SIMULATION_FILES["master"], simulated_master, MASTER_FIELDS)
    write_csv(output_dir / SIMULATION_FILES["field_checklist"], simulated_fields, FIELD_FIELDS)
    write_csv(output_dir / SIMULATION_FILES["handoff_checklist"], simulated_handoffs, HANDOFF_FIELDS)

    fields_by_item: dict[str, list[dict[str, Any]]] = {}
    for row in simulated_fields:
        fields_by_item.setdefault(str(row["workbook_item_id"]), []).append(row)
    for row in simulated_master:
        write_task_card(task_card_dir, row, fields_by_item.get(str(row["workbook_item_id"]), []))

    counts = {
        "pilot_items": len(simulated_master),
        "field_fill_items": len(simulated_fields),
        "handoff_check_items": len(simulated_handoffs),
        "task_cards": len(simulated_master),
        "pending_workbook_items": 0,
        "pending_field_items": 0,
        "pending_handoff_items": 0,
        "simulated_completion_rows": len(simulated_master) + len(simulated_fields) + len(simulated_handoffs),
        "simulation_marker_rows": len(simulated_master) + len(simulated_fields) + len(simulated_handoffs),
        "source_missing_fields": int(workbook_manifest.get("counts", {}).get("source_missing_fields", len(field_rows))),
        "source_blocked_patches": int(workbook_manifest.get("counts", {}).get("source_blocked_patches", 0)),
        "source_workbench_modified": 0,
        "database_write_performed": 0,
    }
    manifest = {
        "generated_at": generated_at,
        "status": "governance_closure_pilot_operator_completion_simulation_no_write",
        "source_workbook_dir": str(workbook_dir),
        "source_workbook_status": workbook_manifest.get("status", ""),
        "source_workbook_readiness": workbook_manifest.get("readiness", ""),
        "readiness": "operator_completion_simulation_ready_for_return_preview",
        "ready_for_pilot_return_preview": "yes",
        "ready_for_source_workbench_update": "no",
        "ready_for_lims_apply": "no",
        "is_simulated": 1,
        "simulation_marker": SIMULATION_MARKER,
        "files": SIMULATION_FILES,
        "counts": counts,
        "guardrails": GUARDRAILS,
    }
    (output_dir / SIMULATION_FILES["manifest"]).write_text(json.dumps(manifest, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
    write_overview(output_dir, manifest)
    write_rerun(output_dir)
    write_readme(output_dir)

    return {
        "generated_at": generated_at,
        "simulation_dir": str(output_dir),
        "status": "passed",
        "readiness": manifest["readiness"],
        "ready_for_pilot_return_preview": manifest["ready_for_pilot_return_preview"],
        "ready_for_source_workbench_update": manifest["ready_for_source_workbench_update"],
        "ready_for_lims_apply": manifest["ready_for_lims_apply"],
        "is_simulated": 1,
        "counts": counts,
        "findings": [],
    }


def write_task_card(task_card_dir: Path, row: dict[str, Any], field_rows: list[dict[str, Any]]) -> None:
    lines = [
        f"# {row['simulation_item_id']} {row['object_name']}",
        "",
        f"- workbook_item_id: {row['workbook_item_id']}",
        f"- owner_role: {row['owner_role']}",
        f"- simulated_status: {row['workbook_status']}",
        f"- simulation_marker: {SIMULATION_MARKER}",
        "",
        "## 模拟补齐字段",
        "",
    ]
    for field in field_rows:
        lines.append(f"- {field['missing_field']} -> {field['simulated_input_value']}")
    lines.extend(
        [
            "",
            "## 边界",
            "",
            "本卡片为模拟完成，不写数据库，不代表真实执行完成，不代表人工评审通过，不代表真实培训完成，不代表受控发布，不写入质量手册正文。",
            "",
        ]
    )
    (task_card_dir / f"{row['simulation_item_id']}.md").write_text("\n".join(lines), encoding="utf-8")


def write_overview(output_dir: Path, manifest: dict[str, Any]) -> None:
    lines = [
        "# 治理关闭试点人工执行模拟完成总览",
        "",
        f"生成时间：{manifest['generated_at']}",
        f"readiness：{manifest['readiness']}",
        f"ready_for_pilot_return_preview：{manifest['ready_for_pilot_return_preview']}",
        f"ready_for_source_workbench_update：{manifest['ready_for_source_workbench_update']}",
        f"ready_for_lims_apply：{manifest['ready_for_lims_apply']}",
        f"simulation_marker：{manifest['simulation_marker']}",
        "",
        "## 计数",
        "",
    ]
    for key, value in manifest["counts"].items():
        lines.append(f"- {key}: {value}")
    lines.extend(["", "## 边界", ""])
    for guardrail in manifest["guardrails"]:
        lines.append(f"- {guardrail}")
    lines.append("")
    (output_dir / SIMULATION_FILES["overview"]).write_text("\n".join(lines), encoding="utf-8")


def write_rerun(output_dir: Path) -> None:
    lines = [
        "# 复跑验证提示",
        "",
        "本提示只说明该模拟完成包可用于哪类命令链路验证，不是回填授权。",
        "",
        "1. 先用本包校验命令层是否能识别 operator workbook gate 已模拟清零。",
        "2. 真实工作仍应回到原 `governance_closure_pilot_operator_workbook/` 和源试点材料，由人工补齐证据、签核、日期和复核人。",
        "3. 完成真实补齐后，应重新生成试点回填预览和源工作台补丁预演，而不是把本模拟包当作源事实。",
        "",
        "边界：模拟完成，不写数据库，不代表真实执行完成，不代表人工评审通过，不代表真实培训完成，不代表受控发布，不写入质量手册正文。",
        "",
    ]
    (output_dir / SIMULATION_FILES["rerun"]).write_text("\n".join(lines), encoding="utf-8")


def write_readme(output_dir: Path) -> None:
    lines = [
        "# 治理关闭试点人工执行模拟完成包",
        "",
        "本包把真实人工执行工作簿中的 pending 项独立转成模拟 completed 状态，用于验证未来人工补齐后的 LIMS 命令闸门识别路径。",
        "",
        "## 文件",
        "",
        "- `governance_closure_pilot_operator_completion_simulation_manifest.json`：总状态、计数、模拟标识和边界。",
        "- `00-模拟完成总览.md`：阅读入口。",
        "- `01-模拟完成主清单.csv`：5 条主任务的模拟完成状态。",
        "- `02-模拟逐字段完成清单.csv`：55 条字段的模拟补齐值。",
        "- `03-模拟签核交接完成表.csv`：5 条签核交接的模拟完成状态。",
        "- `04-复跑验证提示.md`：如何解释验证结果。",
        "- `task_cards/`：按试点任务拆分的模拟完成卡片。",
        "",
        "## 边界",
        "",
        "模拟完成，不写数据库，不修改真实工作簿，不代表真实执行完成，不代表人工评审通过，不代表真实培训完成，不代表受控发布，不写入质量手册正文。",
        "",
    ]
    (output_dir / SIMULATION_FILES["readme"]).write_text("\n".join(lines), encoding="utf-8")


def render_markdown(result: dict[str, Any]) -> str:
    lines = [
        "# QMS 治理关闭试点人工执行模拟完成包生成报告",
        "",
        f"生成时间：{result['generated_at']}",
        f"模拟完成包：`{result['simulation_dir']}`",
        f"结论：{result['status']}",
        f"readiness：{result['readiness']}",
        f"ready_for_pilot_return_preview：{result['ready_for_pilot_return_preview']}",
        f"ready_for_source_workbench_update：{result['ready_for_source_workbench_update']}",
        f"ready_for_lims_apply：{result['ready_for_lims_apply']}",
        f"is_simulated：{result['is_simulated']}",
        "",
        "## 计数",
        "",
    ]
    for key, value in result["counts"].items():
        lines.append(f"- {key}: {value}")
    lines.extend(["", "## 发现项", "", "未发现结构性问题。该结论只证明模拟完成包生成成功，不代表真实执行完成、人工评审通过、受控发布或正式写库授权。", ""])
    return "\n".join(lines)


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--workbook-dir", required=True)
    parser.add_argument("--output-dir", required=True)
    parser.add_argument("--json-out")
    parser.add_argument("--md-out")
    args = parser.parse_args()

    result = build_simulation(Path(args.workbook_dir), Path(args.output_dir))
    if args.json_out:
        Path(args.json_out).write_text(json.dumps(result, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
    if args.md_out:
        Path(args.md_out).write_text(render_markdown(result), encoding="utf-8")
    print(json.dumps(result, ensure_ascii=False, indent=2))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
