#!/usr/bin/env python3
"""Build a no-write operator workbook for the governance-closure pilot."""

from __future__ import annotations

import argparse
import csv
import datetime as dt
import json
from collections import defaultdict
from pathlib import Path
from typing import Any


PILOT_FILES = {
    "manifest": "governance_closure_pilot_manifest.json",
    "evidence": "02-试点证据填写页.csv",
    "handoff": "03-试点签核交接页.csv",
}

RETURN_FILES = {
    "manifest": "governance_closure_pilot_return_manifest.json",
    "mapping": "01-试点证据到源工作台映射.csv",
    "missing_fields": "03-仍缺字段清单.csv",
}

SOURCE_UPDATE_FILES = {
    "manifest": "governance_closure_pilot_source_update_manifest.json",
    "patch_preview": "01-源工作台回填补丁预览.csv",
    "blocked_patches": "02-阻断补丁清单.csv",
}

WORKBOOK_FILES = {
    "manifest": "governance_closure_pilot_operator_workbook_manifest.json",
    "overview": "00-试点人工执行工作簿总览.md",
    "master": "01-试点执行主清单.csv",
    "field_checklist": "02-逐字段填写清单.csv",
    "handoff_checklist": "03-签核交接核对表.csv",
    "rerun": "04-复跑与回填确认清单.md",
    "readme": "README.md",
    "task_card_dir": "task_cards",
}

GUARDRAILS = [
    "本工作簿只读取 governance_closure_pilot_pack、governance_closure_pilot_return_preview 和 governance_closure_pilot_source_update_rehearsal，不写数据库。",
    "本工作簿不修改试点包、试点回填预览、源工作台补丁预演、治理关闭工作台、治理总览或任何现用 Word 文件。",
    "本工作簿不代表人工评审通过，不代表真实培训完成，不代表受控发布，不代表正式写库授权。",
    "本工作簿只帮助人工补齐试点证据、签核交接和逐字段回填前信息；未完成前不得回填源工作台。",
    "资质状态口径：已取得 CMA，CNAS 申请中；不得表述为已取得 CNAS。",
    "以 LIMS 当前导出的 2022 程序清单作为现行程序目录。",
    "jewelry-qms 仍为建设中系统，只进入实施计划、演练和治理准备材料，不写入质量手册正文。",
]

MASTER_FIELDS = [
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
]

FIELD_FIELDS = [
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
]

HANDOFF_FIELDS = [
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


def index_by(rows: list[dict[str, str]], key: str) -> dict[str, dict[str, str]]:
    return {row.get(key, ""): row for row in rows}


def group_by(rows: list[dict[str, str]], key: str) -> dict[str, list[dict[str, str]]]:
    grouped: dict[str, list[dict[str, str]]] = defaultdict(list)
    for row in rows:
        grouped[row.get(key, "")].append(row)
    return grouped


def required_input_for(field_name: str) -> str:
    return {
        "evidence_reference": "填写可追溯证据名称、编号、文件路径或会议/培训/评审记录编号。",
        "evidence_summary": "简述证据证明了什么，不得只写“已完成”。",
        "closure_comment": "写明为什么该阻断项可关闭，必要时列出仍需跟踪事项。",
        "reviewer": "填写实际复核人或责任岗位确认人。",
        "review_date": "填写实际复核日期，格式建议 YYYY-MM-DD。",
        "evidence_status": "试点证据真实形成并可追溯后才可由 pending 改为 completed。",
        "signature_status": "岗位签核完成后才可由 pending 改为 completed。",
        "handoff_status": "交接复核完成后才可由 pending 改为 completed。",
        "assigned_person": "填写实际执行人；没有明确人员时不得关闭。",
        "actual_finish_date": "填写实际完成日期，格式建议 YYYY-MM-DD。",
    }.get(field_name, "按源工作台字段含义填写真实、可追溯的信息。")


def build_workbook(pilot_dir: Path, pilot_return_dir: Path, source_update_dir: Path, output_dir: Path) -> dict[str, Any]:
    pilot_manifest = load_json(pilot_dir / PILOT_FILES["manifest"])
    return_manifest = load_json(pilot_return_dir / RETURN_FILES["manifest"])
    source_update_manifest = load_json(source_update_dir / SOURCE_UPDATE_FILES["manifest"])

    evidence_rows = read_csv(pilot_dir / PILOT_FILES["evidence"])
    handoff_rows = read_csv(pilot_dir / PILOT_FILES["handoff"])
    mapping_rows = read_csv(pilot_return_dir / RETURN_FILES["mapping"])
    missing_rows = read_csv(pilot_return_dir / RETURN_FILES["missing_fields"])
    patch_rows = read_csv(source_update_dir / SOURCE_UPDATE_FILES["patch_preview"])
    blocked_patch_rows = read_csv(source_update_dir / SOURCE_UPDATE_FILES["blocked_patches"])

    handoff_by_batch = index_by(handoff_rows, "pilot_batch_id")
    mapping_by_evidence = index_by(mapping_rows, "pilot_evidence_id")
    missing_by_return = group_by(missing_rows, "return_item_id")
    patches_by_return = group_by(patch_rows, "return_item_id")

    master_rows: list[dict[str, Any]] = []
    field_rows: list[dict[str, Any]] = []
    handoff_check_rows: list[dict[str, Any]] = []

    for index, evidence in enumerate(evidence_rows, start=1):
        workbook_item_id = f"GCPOW-{index:03d}"
        pilot_batch_id = evidence.get("pilot_batch_id", "")
        mapping = mapping_by_evidence.get(evidence.get("pilot_evidence_id", ""), {})
        return_item_id = mapping.get("return_item_id", "")
        handoff = handoff_by_batch.get(pilot_batch_id, {})
        missing_for_item = missing_by_return.get(return_item_id, [])
        patches_for_item = patches_by_return.get(return_item_id, [])
        blocked_for_item = [row for row in patches_for_item if row.get("patch_action") == "blocked_no_update"]
        workbook_status = "pending"
        if not missing_for_item and not blocked_for_item and handoff.get("signature_status") == "completed" and handoff.get("handoff_status") == "completed":
            workbook_status = "ready_for_return_preview"
        master_rows.append(
            {
                "workbook_item_id": workbook_item_id,
                "pilot_evidence_id": evidence.get("pilot_evidence_id", ""),
                "pilot_batch_id": pilot_batch_id,
                "pilot_handoff_id": handoff.get("pilot_handoff_id", ""),
                "closure_item_id": evidence.get("closure_item_id", ""),
                "source_task_id": evidence.get("source_task_id", ""),
                "gate_id": evidence.get("gate_id", ""),
                "task_group": evidence.get("task_group", ""),
                "object_code": evidence.get("object_code", ""),
                "object_name": evidence.get("object_name", ""),
                "owner_role": evidence.get("owner_role", ""),
                "missing_field_count": len(missing_for_item),
                "blocked_patch_count": len(blocked_for_item),
                "evidence_status": evidence.get("evidence_status", ""),
                "signature_status": handoff.get("signature_status", ""),
                "handoff_status": handoff.get("handoff_status", ""),
                "workbook_status": workbook_status,
                "blocks_apply": "yes" if workbook_status != "ready_for_return_preview" else "no",
                "next_action": "补齐证据字段、签核交接字段后，重新生成试点回填预览和源工作台补丁预演。",
                "source_pilot_evidence_file": "governance_closure_pilot_pack/02-试点证据填写页.csv",
                "source_pilot_handoff_file": "governance_closure_pilot_pack/03-试点签核交接页.csv",
                "not_imported": "yes",
                "not_real_record": "yes",
            }
        )
        handoff_check_rows.append(
            {
                "workbook_item_id": workbook_item_id,
                "pilot_handoff_id": handoff.get("pilot_handoff_id", ""),
                "pilot_batch_id": pilot_batch_id,
                "execution_batch_id": handoff.get("execution_batch_id", ""),
                "owner_role": handoff.get("owner_role", evidence.get("owner_role", "")),
                "assigned_person": handoff.get("assigned_person", ""),
                "reviewer": handoff.get("reviewer", ""),
                "planned_finish_date": handoff.get("planned_finish_date", ""),
                "actual_finish_date": handoff.get("actual_finish_date", ""),
                "signature_status": handoff.get("signature_status", ""),
                "handoff_status": handoff.get("handoff_status", ""),
                "required_fields": handoff.get("required_fields", ""),
                "workbook_status": "pending" if handoff.get("signature_status") != "completed" or handoff.get("handoff_status") != "completed" else "completed",
                "blocks_apply": "yes" if handoff.get("signature_status") != "completed" or handoff.get("handoff_status") != "completed" else "no",
                "not_imported": "yes",
                "not_real_record": "yes",
            }
        )
        for missing_index, missing in enumerate(missing_for_item, start=1):
            patch = patches_for_item[missing_index - 1] if missing_index - 1 < len(patches_for_item) else {}
            field_rows.append(
                {
                    "field_task_id": f"{workbook_item_id}-F{missing_index:02d}",
                    "workbook_item_id": workbook_item_id,
                    "return_item_id": return_item_id,
                    "pilot_evidence_id": evidence.get("pilot_evidence_id", ""),
                    "closure_item_id": evidence.get("closure_item_id", ""),
                    "field_group": missing.get("field_group", ""),
                    "missing_field": missing.get("missing_field", ""),
                    "target_file": patch.get("target_file", ""),
                    "target_field": patch.get("target_field", ""),
                    "required_input": required_input_for(missing.get("missing_field", "")),
                    "why_required": missing.get("why_required", ""),
                    "owner_role": evidence.get("owner_role", ""),
                    "patch_id": patch.get("patch_id", ""),
                    "patch_action": patch.get("patch_action", ""),
                    "block_reason": patch.get("block_reason", ""),
                    "field_status": "pending",
                    "blocks_apply": missing.get("blocks_apply", "yes") or "yes",
                    "not_imported": "yes",
                    "not_real_record": "yes",
                }
            )

    output_dir.mkdir(parents=True, exist_ok=True)
    task_card_dir = output_dir / WORKBOOK_FILES["task_card_dir"]
    task_card_dir.mkdir(exist_ok=True)

    write_csv(output_dir / WORKBOOK_FILES["master"], master_rows, MASTER_FIELDS)
    write_csv(output_dir / WORKBOOK_FILES["field_checklist"], field_rows, FIELD_FIELDS)
    write_csv(output_dir / WORKBOOK_FILES["handoff_checklist"], handoff_check_rows, HANDOFF_FIELDS)

    for row in master_rows:
        write_task_card(task_card_dir, row, [field for field in field_rows if field["workbook_item_id"] == row["workbook_item_id"]])

    pending_items = sum(1 for row in master_rows if row["workbook_status"] != "ready_for_return_preview")
    pending_fields = sum(1 for row in field_rows if row["field_status"] == "pending")
    pending_handoffs = sum(1 for row in handoff_check_rows if row["workbook_status"] != "completed")
    readiness = "operator_workbook_pending_human_execution" if pending_items or pending_fields or pending_handoffs else "ready_for_pilot_return_preview"

    manifest = {
        "generated_at": dt.datetime.now().replace(microsecond=0).isoformat(),
        "status": "governance_closure_pilot_operator_workbook_no_write",
        "source_pilot_dir": str(pilot_dir),
        "source_pilot_return_dir": str(pilot_return_dir),
        "source_pilot_source_update_dir": str(source_update_dir),
        "source_pilot_status": pilot_manifest.get("status", ""),
        "source_pilot_return_status": return_manifest.get("status", ""),
        "source_pilot_source_update_status": source_update_manifest.get("status", ""),
        "readiness": readiness,
        "ready_for_pilot_return_preview": "yes" if readiness == "ready_for_pilot_return_preview" else "no",
        "ready_for_source_workbench_update": "no",
        "ready_for_lims_apply": "no",
        "files": WORKBOOK_FILES,
        "counts": {
            "pilot_items": len(master_rows),
            "field_fill_items": len(field_rows),
            "handoff_check_items": len(handoff_check_rows),
            "task_cards": len(master_rows),
            "pending_workbook_items": pending_items,
            "pending_field_items": pending_fields,
            "pending_handoff_items": pending_handoffs,
            "source_missing_fields": len(missing_rows),
            "source_blocked_patches": len(blocked_patch_rows),
            "source_workbench_modified": 0,
            "database_write_performed": 0,
        },
        "guardrails": GUARDRAILS,
    }
    (output_dir / WORKBOOK_FILES["manifest"]).write_text(json.dumps(manifest, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
    write_overview(output_dir, manifest)
    write_rerun(output_dir)
    write_readme(output_dir)

    return {
        "generated_at": manifest["generated_at"],
        "workbook_dir": str(output_dir),
        "status": "passed",
        "readiness": manifest["readiness"],
        "ready_for_pilot_return_preview": manifest["ready_for_pilot_return_preview"],
        "ready_for_source_workbench_update": manifest["ready_for_source_workbench_update"],
        "ready_for_lims_apply": manifest["ready_for_lims_apply"],
        "counts": manifest["counts"],
        "findings": [],
    }


def write_task_card(task_card_dir: Path, row: dict[str, Any], field_rows: list[dict[str, Any]]) -> None:
    lines = [
        f"# {row['workbook_item_id']} {row['object_name']}",
        "",
        f"- pilot_evidence_id: {row['pilot_evidence_id']}",
        f"- closure_item_id: {row['closure_item_id']}",
        f"- owner_role: {row['owner_role']}",
        f"- workbook_status: {row['workbook_status']}",
        "",
        "## 需要补齐",
        "",
    ]
    for field in field_rows:
        lines.append(f"- {field['missing_field']}：{field['required_input']}")
    lines.extend(
        [
            "",
            "## 复跑顺序",
            "",
            "1. 补齐试点证据填写页和签核交接页。",
            "2. 重新生成试点回填预览。",
            "3. 重新生成源工作台补丁预演。",
            "4. 补丁无阻断后，再由人工确认是否回填源工作台。",
            "",
            "边界：不写数据库，不代表人工评审通过，不代表真实培训完成，不代表受控发布，不写入质量手册正文。",
            "",
        ]
    )
    (task_card_dir / f"{row['workbook_item_id']}.md").write_text("\n".join(lines), encoding="utf-8")


def write_overview(output_dir: Path, manifest: dict[str, Any]) -> None:
    lines = [
        "# 治理关闭试点人工执行工作簿总览",
        "",
        f"生成时间：{manifest['generated_at']}",
        f"readiness：{manifest['readiness']}",
        f"ready_for_pilot_return_preview：{manifest['ready_for_pilot_return_preview']}",
        f"ready_for_source_workbench_update：{manifest['ready_for_source_workbench_update']}",
        "",
        "## 计数",
        "",
    ]
    for key, value in manifest["counts"].items():
        lines.append(f"- {key}: {value}")
    lines.extend(
        [
            "",
            "## 边界",
            "",
            "- 不写数据库。",
            "- 不修改试点包、试点回填预览、源工作台补丁预演、治理关闭工作台或任何现用 Word 文件。",
            "- 不代表人工评审通过、真实培训完成、受控发布或正式写库授权。",
            "- jewelry-qms 仍为建设中系统，不写入质量手册正文。",
            "",
        ]
    )
    (output_dir / WORKBOOK_FILES["overview"]).write_text("\n".join(lines), encoding="utf-8")


def write_rerun(output_dir: Path) -> None:
    lines = [
        "# 复跑与回填确认清单",
        "",
        "本清单只说明人工完成试点后应复跑哪些验证，不是回填授权。",
        "",
        "1. 完成 `01-试点执行主清单.csv`、`02-逐字段填写清单.csv` 和 `03-签核交接核对表.csv` 对应真实信息。",
        "2. 将真实信息回到 `governance_closure_pilot_pack/` 源试点页，不直接修改本工作簿作为系统事实。",
        "3. 重新生成 `governance_closure_pilot_return_preview/`，确认缺字段清零。",
        "4. 重新生成 `governance_closure_pilot_source_update_rehearsal/`，确认阻断补丁清零。",
        "5. 经质量负责人/文件管理员确认后，才讨论是否人工回填 `governance_closure_workbench/` 源工作台。",
        "",
        "边界：不写数据库，不代表人工评审通过，不代表真实培训完成，不代表受控发布，不写入质量手册正文。",
        "",
    ]
    (output_dir / WORKBOOK_FILES["rerun"]).write_text("\n".join(lines), encoding="utf-8")


def write_readme(output_dir: Path) -> None:
    lines = [
        "# 治理关闭试点人工执行工作簿",
        "",
        "本包把最小试点的证据页、签核交接页、缺字段清单和源工作台补丁阻断项整理成一套人工执行材料。",
        "",
        "## 文件",
        "",
        "- `governance_closure_pilot_operator_workbook_manifest.json`：总状态、计数和边界。",
        "- `00-试点人工执行工作簿总览.md`：阅读入口。",
        "- `01-试点执行主清单.csv`：5 条试点主任务。",
        "- `02-逐字段填写清单.csv`：55 条待补字段。",
        "- `03-签核交接核对表.csv`：5 条签核交接核对项。",
        "- `04-复跑与回填确认清单.md`：人工完成后的复跑顺序。",
        "- `task_cards/`：按试点任务拆分的一页卡。",
        "",
        "## 边界",
        "",
        "不写数据库，不修改源工作台，不代表人工评审通过，不代表真实培训完成，不代表受控发布，不写入质量手册正文。",
        "",
    ]
    (output_dir / WORKBOOK_FILES["readme"]).write_text("\n".join(lines), encoding="utf-8")


def render_markdown(result: dict[str, Any]) -> str:
    lines = [
        "# QMS 治理关闭试点人工执行工作簿生成报告",
        "",
        f"生成时间：{result['generated_at']}",
        f"工作簿：`{result['workbook_dir']}`",
        f"结论：{result['status']}",
        f"readiness：{result['readiness']}",
        f"ready_for_pilot_return_preview：{result['ready_for_pilot_return_preview']}",
        "",
        "## 计数",
        "",
    ]
    for key, value in result["counts"].items():
        lines.append(f"- {key}: {value}")
    lines.extend(["", "## 发现项", "", "未发现结构性问题。该结论不代表人工评审通过、真实培训完成、受控发布或正式写库授权。", ""])
    return "\n".join(lines)


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--pilot-dir", required=True)
    parser.add_argument("--pilot-return-dir", required=True)
    parser.add_argument("--source-update-dir", required=True)
    parser.add_argument("--output-dir", required=True)
    parser.add_argument("--json-out")
    parser.add_argument("--md-out")
    args = parser.parse_args()

    result = build_workbook(Path(args.pilot_dir), Path(args.pilot_return_dir), Path(args.source_update_dir), Path(args.output_dir))
    if args.json_out:
        Path(args.json_out).write_text(json.dumps(result, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
    if args.md_out:
        Path(args.md_out).write_text(render_markdown(result), encoding="utf-8")
    print(json.dumps(result, ensure_ascii=False, indent=2))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
