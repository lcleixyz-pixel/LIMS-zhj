#!/usr/bin/env python3
"""Build a no-write real handback intake package for the pilot operator workbook."""

from __future__ import annotations

import argparse
import csv
import datetime as dt
import json
import shutil
from pathlib import Path
from typing import Any


WORKBOOK_FILES = {
    "manifest": "governance_closure_pilot_operator_workbook_manifest.json",
    "master": "01-试点执行主清单.csv",
    "field_checklist": "02-逐字段填写清单.csv",
    "handoff_checklist": "03-签核交接核对表.csv",
    "task_card_dir": "task_cards",
}

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

GUARDRAILS = [
    "本包只读取 governance_closure_pilot_operator_workbook，不写数据库。",
    "本包不修改 governance_closure_pilot_operator_workbook、试点包、源工作台、治理关闭工作台或任何现用 Word 文件。",
    "本包用于真实人员交回后的验收准备，不代表人工评审通过，不代表真实培训完成，不代表受控发布。",
    "本包初始状态全部 pending；只有真实人员补齐证据值、执行人、复核人、日期和签核交接后，复跑校验才可能进入 ready_for_pilot_return_preview=yes。",
    "本包不得使用模拟执行人、模拟复核人或 SIMULATED 标识替代真实人员和真实证据。",
    "资质状态口径：已取得 CMA，CNAS 申请中；不得表述为已取得 CNAS。",
    "以 LIMS 当前导出的 2022 程序清单作为现行程序目录。",
    "jewelry-qms 仍为建设中系统，只进入实施计划、演练和治理准备材料，不写入质量手册正文。",
]

MASTER_FIELDS = [
    "handback_item_id",
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
    "required_field_count",
    "source_blocked_patch_count",
    "evidence_status",
    "signature_status",
    "handoff_status",
    "handback_status",
    "blocks_apply",
    "real_execution_required",
    "not_imported",
    "not_lims_record_yet",
    "source_workbook_status",
    "next_action",
]

FIELD_FIELDS = [
    "handback_field_id",
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
    "real_input_value",
    "why_required",
    "owner_role",
    "patch_id",
    "source_patch_action",
    "source_block_reason",
    "field_status",
    "blocks_apply",
    "real_execution_required",
    "not_imported",
    "not_lims_record_yet",
]

HANDOFF_FIELDS = [
    "handback_handoff_id",
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
    "handback_status",
    "blocks_apply",
    "real_execution_required",
    "not_imported",
    "not_lims_record_yet",
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


def write_text(path: Path, text: str) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text(text, encoding="utf-8")


def build_handback(workbook_dir: Path, output_dir: Path) -> dict[str, Any]:
    workbook_manifest = load_json(workbook_dir / WORKBOOK_FILES["manifest"])
    master_rows = read_csv(workbook_dir / WORKBOOK_FILES["master"])
    field_rows = read_csv(workbook_dir / WORKBOOK_FILES["field_checklist"])
    handoff_rows = read_csv(workbook_dir / WORKBOOK_FILES["handoff_checklist"])
    generated_at = dt.datetime.now().replace(microsecond=0).isoformat()

    if output_dir.exists():
        shutil.rmtree(output_dir)
    (output_dir / HANDBACK_FILES["task_card_dir"]).mkdir(parents=True)

    field_counts = group_count(field_rows, "workbook_item_id")
    handback_master: list[dict[str, Any]] = []
    for index, row in enumerate(master_rows, start=1):
        item_id = row.get("workbook_item_id", "")
        handback_master.append(
            {
                "handback_item_id": f"GCPOH-{index:03d}",
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
                "required_field_count": field_counts.get(item_id, 0),
                "source_blocked_patch_count": row.get("blocked_patch_count", ""),
                "evidence_status": "pending",
                "signature_status": "pending",
                "handoff_status": "pending",
                "handback_status": "pending",
                "blocks_apply": "yes",
                "real_execution_required": "yes",
                "not_imported": "yes",
                "not_lims_record_yet": "yes",
                "source_workbook_status": row.get("workbook_status", ""),
                "next_action": "由真实人员补齐逐字段证据、执行签核和交接复核后，复跑本包校验。",
            }
        )

    handback_fields: list[dict[str, Any]] = []
    for index, row in enumerate(field_rows, start=1):
        handback_fields.append(
            {
                "handback_field_id": f"GCPOH-F{index:03d}",
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
                "real_input_value": "",
                "why_required": row.get("why_required", ""),
                "owner_role": row.get("owner_role", ""),
                "patch_id": row.get("patch_id", ""),
                "source_patch_action": row.get("patch_action", ""),
                "source_block_reason": row.get("block_reason", ""),
                "field_status": "pending",
                "blocks_apply": "yes",
                "real_execution_required": "yes",
                "not_imported": "yes",
                "not_lims_record_yet": "yes",
            }
        )

    handback_handoffs: list[dict[str, Any]] = []
    for index, row in enumerate(handoff_rows, start=1):
        handback_handoffs.append(
            {
                "handback_handoff_id": f"GCPOH-H{index:03d}",
                "workbook_item_id": row.get("workbook_item_id", ""),
                "pilot_handoff_id": row.get("pilot_handoff_id", ""),
                "pilot_batch_id": row.get("pilot_batch_id", ""),
                "execution_batch_id": row.get("execution_batch_id", ""),
                "owner_role": row.get("owner_role", ""),
                "assigned_person": "",
                "reviewer": "",
                "planned_finish_date": row.get("planned_finish_date", ""),
                "actual_finish_date": "",
                "signature_status": "pending",
                "handoff_status": "pending",
                "required_fields": row.get("required_fields", ""),
                "handback_status": "pending",
                "blocks_apply": "yes",
                "real_execution_required": "yes",
                "not_imported": "yes",
                "not_lims_record_yet": "yes",
            }
        )

    task_card_dir = workbook_dir / WORKBOOK_FILES["task_card_dir"]
    for source_card in sorted(task_card_dir.glob("*.md")) if task_card_dir.is_dir() else []:
        text = source_card.read_text(encoding="utf-8")
        write_text(
            output_dir / HANDBACK_FILES["task_card_dir"] / source_card.name,
            text
            + "\n\n## 真实交回补充\n\n"
            + "- 只能填写真实执行人、真实复核人、真实完成日期和真实证据编号/路径。\n"
            + "- 不得使用模拟人、模拟复核人或 SIMULATED 标识。\n"
            + "- 填写后复跑 `qms_governance_closure_pilot_operator_handback_check.py`。\n",
        )

    counts = {
        "pilot_items": len(handback_master),
        "field_fill_items": len(handback_fields),
        "handoff_check_items": len(handback_handoffs),
        "task_cards": len(list((output_dir / HANDBACK_FILES["task_card_dir"]).glob("*.md"))),
        "initial_pending_workbook_items": len(handback_master),
        "initial_pending_field_items": len(handback_fields),
        "initial_pending_handoff_items": len(handback_handoffs),
        "source_missing_fields": int(workbook_manifest.get("counts", {}).get("source_missing_fields", 0)),
        "source_blocked_patches": int(workbook_manifest.get("counts", {}).get("source_blocked_patches", 0)),
        "source_workbench_modified": 0,
        "database_write_performed": 0,
    }
    manifest = {
        "generated_at": generated_at,
        "status": "governance_closure_pilot_operator_handback_no_write",
        "readiness": "operator_handback_pending_real_execution",
        "source_workbook_dir": str(workbook_dir),
        "source_workbook_status": workbook_manifest.get("status", ""),
        "ready_for_pilot_return_preview": "no",
        "ready_for_source_workbench_update": "no",
        "ready_for_lims_apply": "no",
        "guardrails": GUARDRAILS,
        "files": HANDBACK_FILES,
        "counts": counts,
    }

    write_csv(output_dir / HANDBACK_FILES["master"], handback_master, MASTER_FIELDS)
    write_csv(output_dir / HANDBACK_FILES["field_checklist"], handback_fields, FIELD_FIELDS)
    write_csv(output_dir / HANDBACK_FILES["handoff_checklist"], handback_handoffs, HANDOFF_FIELDS)
    (output_dir / HANDBACK_FILES["manifest"]).write_text(json.dumps(manifest, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
    write_text(output_dir / HANDBACK_FILES["overview"], render_overview(manifest))
    write_text(output_dir / HANDBACK_FILES["acceptance"], render_acceptance())
    write_text(output_dir / HANDBACK_FILES["readme"], render_readme(manifest))
    return manifest


def render_overview(manifest: dict[str, Any]) -> str:
    counts = manifest["counts"]
    return "\n".join(
        [
            "# 治理关闭试点真实执行交回包总览",
            "",
            "本包用于真实人员交回后的验收准备，不写数据库，不修改现用 Word 文件，不写入质量手册正文，不代表人工评审通过、真实培训完成、受控发布或正式写库授权。",
            "",
            f"生成时间：{manifest['generated_at']}",
            f"readiness：{manifest['readiness']}",
            f"ready_for_pilot_return_preview：{manifest['ready_for_pilot_return_preview']}",
            "",
            "## 初始待办",
            "",
            f"- 试点主任务：{counts['pilot_items']} 条",
            f"- 逐字段填写项：{counts['field_fill_items']} 条",
            f"- 签核交接项：{counts['handoff_check_items']} 条",
            "",
            "所有项目初始均为 pending。只有真实证据、执行人、复核人、日期和状态均补齐后，复跑校验才可能进入 ready_for_pilot_return_preview=yes。",
            "",
        ]
    )


def render_acceptance() -> str:
    return "\n".join(
        [
            "# 真实执行交回验收与复跑说明",
            "",
            "## 填写规则",
            "",
            "1. 在 `02-真实逐字段交回清单.csv` 的 `real_input_value` 填写真实证据编号、文件路径、关闭意见、复核人或日期。",
            "2. 逐字段确认完成后，将对应 `field_status` 改为 `completed`，并将 `blocks_apply` 改为 `no`。",
            "3. 在 `03-真实签核交接交回表.csv` 填写真实执行人、真实复核人和实际完成日期。",
            "4. 签核与交接均完成后，将 `signature_status`、`handoff_status`、`handback_status` 改为 `completed`，并将 `blocks_apply` 改为 `no`。",
            "5. 不得填写模拟人、模拟复核人或任何 `SIMULATED` 标识。",
            "",
            "## 复跑",
            "",
            "先运行真实交回包校验，确认 pending 和非法模拟值清零；再重新生成试点回填预览和源工作台补丁预演。",
            "",
            "本包不写数据库，不修改源工作台，不写入质量手册正文，不代表人工评审通过、真实培训完成、受控发布或正式写库授权。",
            "",
        ]
    )


def render_readme(manifest: dict[str, Any]) -> str:
    return "\n".join(
        [
            "# 治理关闭试点真实执行交回包",
            "",
            "用途：把试点人工执行工作簿转换成真实人员交回后的验收入口。",
            "",
            "边界：不写数据库；不修改试点包、源工作台、治理关闭工作台或现用 Word；不写入质量手册正文；不代表人工评审通过、真实培训完成、受控发布或正式写库授权。",
            "",
            f"来源工作簿：`{manifest['source_workbook_dir']}`",
            "",
            "文件：",
            "",
            "- `01-真实执行交回主清单.csv`",
            "- `02-真实逐字段交回清单.csv`",
            "- `03-真实签核交接交回表.csv`",
            "- `04-交回验收与复跑说明.md`",
            "- `task_cards/`",
            "",
        ]
    )


def render_report(manifest: dict[str, Any]) -> str:
    counts = manifest["counts"]
    lines = [
        "# QMS 治理关闭试点真实执行交回包生成报告",
        "",
        f"生成时间：{manifest['generated_at']}",
        f"结论：{manifest['status']}",
        f"readiness：{manifest['readiness']}",
        f"ready_for_pilot_return_preview：{manifest['ready_for_pilot_return_preview']}",
        f"ready_for_source_workbench_update：{manifest['ready_for_source_workbench_update']}",
        f"ready_for_lims_apply：{manifest['ready_for_lims_apply']}",
        "",
        "## 计数",
        "",
    ]
    for key, value in counts.items():
        lines.append(f"- {key}: {value}")
    lines.extend(
        [
            "",
            "## 边界",
            "",
            "- 本包只作为真实人员交回后的验收入口。",
            "- 不写数据库，不修改现用 Word，不写入质量手册正文。",
            "- 初始状态全部 pending，不代表人工评审通过、真实培训完成、受控发布或正式写库授权。",
            "",
        ]
    )
    return "\n".join(lines)


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--workbook-dir", required=True)
    parser.add_argument("--output-dir", required=True)
    parser.add_argument("--json-out")
    parser.add_argument("--md-out")
    args = parser.parse_args()
    result = build_handback(Path(args.workbook_dir), Path(args.output_dir))
    if args.json_out:
        Path(args.json_out).write_text(json.dumps(result, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
    if args.md_out:
        Path(args.md_out).write_text(render_report(result), encoding="utf-8")
    print(json.dumps(result, ensure_ascii=False, indent=2))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
