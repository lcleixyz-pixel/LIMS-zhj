#!/usr/bin/env python3
"""Build a no-write pilot pack for a small governance closure trial."""

from __future__ import annotations

import argparse
import csv
import datetime as dt
import json
from pathlib import Path
from typing import Any


EXECUTION_FILES = {
    "manifest": "governance_closure_execution_manifest.json",
    "execution_batches": "01-闭环执行批次.csv",
    "signature_register": "02-岗位签核页模板.csv",
    "handoff_checklist": "03-交接复核清单.csv",
    "route_index": "04-回填路径索引.csv",
}

PILOT_FILES = {
    "manifest": "governance_closure_pilot_manifest.json",
    "overview": "00-治理关闭最小试点总览.md",
    "pilot_batches": "01-试点批次选择.csv",
    "pilot_evidence": "02-试点证据填写页.csv",
    "pilot_handoff": "03-试点签核交接页.csv",
    "rerun_commands": "04-试点复跑命令清单.md",
    "readme": "README.md",
}

GUARDRAILS = [
    "本试点包只读取 governance_closure_execution_pack 选择少量试点批次，不写数据库。",
    "本试点包不修改 governance_closure_execution_pack、governance_closure_workbench、governance_closure_decision_preview、governance_readiness_dashboard 或任何现用 Word 文件。",
    "本试点包不代表人工评审通过，不代表真实培训完成，不代表受控发布，不代表正式写库授权。",
    "所有试点证据、签核和交接默认 pending；只有人工补齐证据、意见、复核人和日期后才可回到治理关闭工作台。",
    "资质状态口径：已取得 CMA，CNAS 申请中；不得表述为已取得 CNAS。",
    "以 LIMS 当前导出的 2022 程序清单作为现行程序目录。",
    "jewelry-qms 仍为建设中系统，只进入实施计划、演练和治理准备材料，不写入质量手册正文。",
]

BATCH_FIELDS = [
    "pilot_batch_id",
    "execution_batch_id",
    "owner_role",
    "gate_id",
    "task_group",
    "task_count",
    "blocking_count",
    "selection_reason",
    "source_paths",
    "pilot_status",
    "required_before_closure_preview",
    "not_imported",
    "not_real_record",
]

EVIDENCE_FIELDS = [
    "pilot_evidence_id",
    "pilot_batch_id",
    "closure_item_id",
    "source_task_id",
    "gate_id",
    "task_group",
    "object_code",
    "object_name",
    "owner_role",
    "evidence_reference",
    "evidence_summary",
    "closure_comment",
    "reviewer",
    "review_date",
    "evidence_status",
    "blocks_apply",
    "source_evidence_file",
    "source_closure_file",
    "not_imported",
    "not_real_record",
]

HANDOFF_FIELDS = [
    "pilot_handoff_id",
    "pilot_batch_id",
    "execution_batch_id",
    "owner_role",
    "required_fields",
    "assigned_person",
    "reviewer",
    "planned_finish_date",
    "actual_finish_date",
    "signature_status",
    "handoff_status",
    "rerun_after_completion",
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


def int_value(value: Any) -> int:
    try:
        return int(value)
    except (TypeError, ValueError):
        return 0


def render_table(rows: list[dict[str, Any]], columns: list[str]) -> list[str]:
    lines = ["| " + " | ".join(columns) + " |", "|" + "|".join(["---"] * len(columns)) + "|"]
    for row in rows:
        values = [str(row.get(column, "")).replace("\n", " ").replace("|", "／") for column in columns]
        lines.append("| " + " | ".join(values) + " |")
    return lines


def select_batches(batch_rows: list[dict[str, str]], max_batches: int) -> list[dict[str, str]]:
    candidates = [row for row in batch_rows if int_value(row.get("blocking_count")) > 0]
    candidates.sort(
        key=lambda row: (
            int_value(row.get("blocking_count")),
            int_value(row.get("task_count")),
            int_value(row.get("suggested_sequence")),
            row.get("execution_batch_id", ""),
        )
    )
    return candidates[:max_batches]


def build_pilot_batches(selected_batches: list[dict[str, str]]) -> list[dict[str, Any]]:
    rows: list[dict[str, Any]] = []
    for index, batch in enumerate(selected_batches, start=1):
        rows.append(
            {
                "pilot_batch_id": f"GCPB-{index:03d}",
                "execution_batch_id": batch.get("execution_batch_id", ""),
                "owner_role": batch.get("owner_role", ""),
                "gate_id": batch.get("gate_id", ""),
                "task_group": batch.get("task_group", ""),
                "task_count": batch.get("task_count", "0"),
                "blocking_count": batch.get("blocking_count", "0"),
                "selection_reason": "优先选择阻断数量少、便于人工试跑闭环的批次。",
                "source_paths": batch.get("source_paths", ""),
                "pilot_status": "pending",
                "required_before_closure_preview": "yes",
                "not_imported": "yes",
                "not_real_record": "yes",
            }
        )
    return rows


def build_evidence_rows(
    pilot_batches: list[dict[str, Any]],
    route_rows: list[dict[str, str]],
    max_routes: int,
) -> list[dict[str, Any]]:
    pilot_by_execution = {str(row["execution_batch_id"]): str(row["pilot_batch_id"]) for row in pilot_batches}
    selected_routes = [row for row in route_rows if row.get("execution_batch_id", "") in pilot_by_execution]
    selected_routes.sort(key=lambda row: (row.get("execution_batch_id", ""), row.get("closure_item_id", "")))
    selected_routes = selected_routes[:max_routes]

    rows: list[dict[str, Any]] = []
    for index, route in enumerate(selected_routes, start=1):
        rows.append(
            {
                "pilot_evidence_id": f'GCPE-{index:03d}',
                "pilot_batch_id": pilot_by_execution.get(route.get("execution_batch_id", ""), ""),
                "closure_item_id": route.get("closure_item_id", ""),
                "source_task_id": route.get("source_task_id", ""),
                "gate_id": route.get("gate_id", ""),
                "task_group": route.get("task_group", ""),
                "object_code": route.get("object_code", ""),
                "object_name": route.get("object_name", ""),
                "owner_role": route.get("owner_role", ""),
                "evidence_reference": "",
                "evidence_summary": "",
                "closure_comment": "",
                "reviewer": "",
                "review_date": "",
                "evidence_status": "pending",
                "blocks_apply": route.get("blocks_apply", "yes"),
                "source_evidence_file": route.get("source_evidence_file", "governance_closure_workbench/03-证据采集模板.csv"),
                "source_closure_file": route.get("source_closure_file", "governance_closure_workbench/04-拟关闭回填模板.csv"),
                "not_imported": "yes",
                "not_real_record": "yes",
            }
        )
    return rows


def build_handoff_rows(pilot_batches: list[dict[str, Any]]) -> list[dict[str, Any]]:
    rows: list[dict[str, Any]] = []
    for index, batch in enumerate(pilot_batches, start=1):
        rows.append(
            {
                "pilot_handoff_id": f"GCPH-{index:03d}",
                "pilot_batch_id": batch.get("pilot_batch_id", ""),
                "execution_batch_id": batch.get("execution_batch_id", ""),
                "owner_role": batch.get("owner_role", ""),
                "required_fields": "evidence_reference | evidence_summary | closure_comment | reviewer | review_date",
                "assigned_person": "",
                "reviewer": "",
                "planned_finish_date": "",
                "actual_finish_date": "",
                "signature_status": "pending",
                "handoff_status": "pending",
                "rerun_after_completion": "先回填 governance_closure_workbench，再生成 governance_closure_decision_preview 和 governance_readiness_refresh_preview。",
                "not_imported": "yes",
                "not_real_record": "yes",
            }
        )
    return rows


def write_overview(output_dir: Path, manifest: dict[str, Any], pilot_batches: list[dict[str, Any]]) -> None:
    lines = [
        "# 治理关闭最小试点总览",
        "",
        "本包用于把全量治理闭环执行包中的少量低阻断批次抽出，供人工先试跑“证据-意见-签核-交接-回填路径”闭环。",
        "",
        "本包不写数据库，不修改现用 Word 文件，不代表人工评审通过、真实培训完成、受控发布或正式写库授权。",
        "",
        "## 关键计数",
        "",
    ]
    for key, value in manifest["counts"].items():
        lines.append(f"- {key}: {value}")
    lines.extend(
        [
            "",
            "## 试点批次",
            "",
            *render_table(
                pilot_batches,
                ["pilot_batch_id", "execution_batch_id", "owner_role", "gate_id", "task_group", "blocking_count", "pilot_status"],
            ),
            "",
            "## 边界",
            "",
        ]
    )
    lines.extend(f"- {guardrail}" for guardrail in GUARDRAILS)
    output_dir.joinpath(PILOT_FILES["overview"]).write_text("\n".join(lines) + "\n", encoding="utf-8")


def write_commands(output_dir: Path, execution_dir: Path, pilot_dir: Path) -> None:
    lines = [
        "# 试点复跑命令清单",
        "",
        "以下命令用于试点包结构验证和 LIMS 命令层读取验证。命令不写数据库。",
        "",
        "```bash",
        "python3 jewelry-qms/scripts/qms_governance_closure_pilot_pack_check.py \\",
        f"  --pilot-dir {pilot_dir}",
        "```",
        "",
        "人工补齐试点证据后，不应直接修改本包为真实记录；应回到：",
        "",
        "- `governance_closure_workbench/03-证据采集模板.csv`",
        "- `governance_closure_workbench/04-拟关闭回填模板.csv`",
        "",
        "再依序复跑治理关闭意见预览和治理就绪刷新预览。",
        "",
        "来源执行包：",
        "",
        f"- `{execution_dir}`",
        "",
        "边界：不写数据库；不代表人工评审通过；不写入质量手册正文。",
    ]
    output_dir.joinpath(PILOT_FILES["rerun_commands"]).write_text("\n".join(lines) + "\n", encoding="utf-8")


def write_readme(output_dir: Path) -> None:
    lines = [
        "# governance_closure_pilot_pack",
        "",
        "用途：从治理闭环执行包中抽取少量低阻断批次，作为组织内部人工试跑闭环的工作面。",
        "",
        "使用方式：先人工填写真实证据、关闭意见、复核人和日期；确认后再回填到 `governance_closure_workbench/` 源模板，并重新生成后续预览包。",
        "",
        "红线：本目录不写数据库，不修改现用 Word 文件，不代表人工评审通过，不代表真实培训完成，不代表受控发布，不写入质量手册正文。",
    ]
    output_dir.joinpath(PILOT_FILES["readme"]).write_text("\n".join(lines) + "\n", encoding="utf-8")


def build_pilot_pack(execution_dir: Path, output_dir: Path, max_batches: int, max_routes: int) -> dict[str, Any]:
    manifest_path = execution_dir / EXECUTION_FILES["manifest"]
    execution_manifest = json.loads(manifest_path.read_text(encoding="utf-8"))
    batch_rows = read_csv(execution_dir / EXECUTION_FILES["execution_batches"])
    route_rows = read_csv(execution_dir / EXECUTION_FILES["route_index"])

    selected_batches = select_batches(batch_rows, max_batches)
    pilot_batches = build_pilot_batches(selected_batches)
    pilot_evidence = build_evidence_rows(pilot_batches, route_rows, max_routes)
    pilot_handoff = build_handoff_rows(pilot_batches)

    blocking_pilot_items = sum(1 for row in pilot_evidence if row.get("blocks_apply") == "yes")
    manifest = {
        "generated_at": dt.datetime.now().replace(microsecond=0).isoformat(),
        "status": "governance_closure_pilot_pack_no_database_write",
        "source_execution_dir": str(execution_dir),
        "source_execution_status": execution_manifest.get("status", ""),
        "selection_rule": "blocking_count asc, task_count asc, suggested_sequence asc; only blocking batches are selected.",
        "readiness": "pilot_pending_human_evidence",
        "ready_for_governance_closure_preview": "no",
        "ready_for_lims_apply": "no",
        "files": PILOT_FILES,
        "counts": {
            "pilot_batches": len(pilot_batches),
            "pilot_evidence_rows": len(pilot_evidence),
            "pilot_handoff_rows": len(pilot_handoff),
            "blocking_pilot_items": blocking_pilot_items,
            "pending_pilot_batches": sum(1 for row in pilot_batches if row.get("pilot_status") == "pending"),
            "pending_pilot_evidence": sum(1 for row in pilot_evidence if row.get("evidence_status") == "pending"),
            "pending_pilot_handoffs": sum(1 for row in pilot_handoff if row.get("handoff_status") == "pending"),
            "database_write_performed": 0,
        },
        "source_counts": execution_manifest.get("counts", {}),
        "guardrails": GUARDRAILS,
    }

    output_dir.mkdir(parents=True, exist_ok=True)
    output_dir.joinpath(PILOT_FILES["manifest"]).write_text(json.dumps(manifest, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
    write_csv(output_dir / PILOT_FILES["pilot_batches"], pilot_batches, BATCH_FIELDS)
    write_csv(output_dir / PILOT_FILES["pilot_evidence"], pilot_evidence, EVIDENCE_FIELDS)
    write_csv(output_dir / PILOT_FILES["pilot_handoff"], pilot_handoff, HANDOFF_FIELDS)
    write_overview(output_dir, manifest, pilot_batches)
    write_commands(output_dir, execution_dir, output_dir)
    write_readme(output_dir)
    return manifest


def render_report(manifest: dict[str, Any]) -> str:
    lines = [
        "# QMS 治理关闭最小试点包生成报告",
        "",
        f"生成时间：{manifest['generated_at']}",
        f"结论：{manifest['status']}",
        f"readiness：{manifest['readiness']}",
        f"ready_for_governance_closure_preview：{manifest['ready_for_governance_closure_preview']}",
        f"ready_for_lims_apply：{manifest['ready_for_lims_apply']}",
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
            "本生成报告只证明试点工作面已经生成，不代表人工评审通过、真实培训完成、受控发布或正式写库授权。",
            "",
        ]
    )
    return "\n".join(lines)


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--execution-dir", required=True)
    parser.add_argument("--output-dir", required=True)
    parser.add_argument("--max-batches", type=int, default=5)
    parser.add_argument("--max-routes", type=int, default=25)
    parser.add_argument("--json-out")
    parser.add_argument("--md-out")
    args = parser.parse_args()

    manifest = build_pilot_pack(Path(args.execution_dir), Path(args.output_dir), args.max_batches, args.max_routes)
    report = {
        "generated_at": manifest["generated_at"],
        "pilot_dir": args.output_dir,
        "status": "passed",
        "readiness": manifest["readiness"],
        "ready_for_governance_closure_preview": manifest["ready_for_governance_closure_preview"],
        "ready_for_lims_apply": manifest["ready_for_lims_apply"],
        "counts": manifest["counts"],
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
