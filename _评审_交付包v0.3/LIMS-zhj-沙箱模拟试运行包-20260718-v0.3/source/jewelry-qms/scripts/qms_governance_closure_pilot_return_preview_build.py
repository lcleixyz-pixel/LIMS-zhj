#!/usr/bin/env python3
"""Build a no-write preview for returning pilot closure results to the source workbench."""

from __future__ import annotations

import argparse
import csv
import datetime as dt
import json
from pathlib import Path
from typing import Any


PILOT_FILES = {
    "manifest": "governance_closure_pilot_manifest.json",
    "pilot_evidence": "02-试点证据填写页.csv",
    "pilot_handoff": "03-试点签核交接页.csv",
}

WORKBENCH_FILES = {
    "manifest": "governance_closure_workbench_manifest.json",
    "evidence_template": "03-证据采集模板.csv",
    "closure_template": "04-拟关闭回填模板.csv",
}

RETURN_FILES = {
    "manifest": "governance_closure_pilot_return_manifest.json",
    "overview": "00-试点回填预览总览.md",
    "mapping": "01-试点证据到源工作台映射.csv",
    "source_preview": "02-拟回填源行预览.csv",
    "missing_fields": "03-仍缺字段清单.csv",
    "rerun_path": "04-复跑路径清单.md",
    "readme": "README.md",
}

GUARDRAILS = [
    "本回填预览只读取 governance_closure_pilot_pack 和 governance_closure_workbench，不写数据库。",
    "本回填预览不修改 governance_closure_pilot_pack、governance_closure_workbench、governance_closure_decision_preview、governance_readiness_dashboard 或任何现用 Word 文件。",
    "本回填预览不代表人工评审通过，不代表真实培训完成，不代表受控发布，不代表正式写库授权。",
    "试点证据、关闭意见、复核人、日期、签核和交接均齐备后，才可由人工回填源工作台并重新生成治理关闭意见预览。",
    "资质状态口径：已取得 CMA，CNAS 申请中；不得表述为已取得 CNAS。",
    "以 LIMS 当前导出的 2022 程序清单作为现行程序目录。",
    "jewelry-qms 仍为建设中系统，只进入实施计划、演练和治理准备材料，不写入质量手册正文。",
]

MAPPING_FIELDS = [
    "return_item_id",
    "pilot_evidence_id",
    "pilot_batch_id",
    "closure_item_id",
    "source_task_id",
    "gate_id",
    "task_group",
    "object_code",
    "object_name",
    "owner_role",
    "source_evidence_row_found",
    "source_closure_row_found",
    "pilot_handoff_found",
    "return_status",
    "blocks_apply",
    "not_imported",
    "not_real_record",
]

SOURCE_PREVIEW_FIELDS = [
    "return_item_id",
    "closure_item_id",
    "target_file",
    "target_row_key",
    "proposed_evidence_reference",
    "proposed_evidence_summary",
    "proposed_closure_comment",
    "proposed_reviewer",
    "proposed_review_date",
    "proposed_evidence_status",
    "proposed_signature_status",
    "proposed_handoff_status",
    "ready_for_manual_source_update",
    "not_imported",
    "not_real_record",
]

MISSING_FIELDS = [
    "missing_id",
    "return_item_id",
    "closure_item_id",
    "field_group",
    "missing_field",
    "why_required",
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


def render_table(rows: list[dict[str, Any]], columns: list[str]) -> list[str]:
    lines = ["| " + " | ".join(columns) + " |", "|" + "|".join(["---"] * len(columns)) + "|"]
    for row in rows:
        values = [str(row.get(column, "")).replace("\n", " ").replace("|", "／") for column in columns]
        lines.append("| " + " | ".join(values) + " |")
    return lines


def missing_for_item(pilot: dict[str, str], handoff: dict[str, str] | None) -> list[tuple[str, str, str]]:
    missing: list[tuple[str, str, str]] = []
    for field in ["evidence_reference", "evidence_summary", "closure_comment", "reviewer", "review_date"]:
        if not pilot.get(field, "").strip():
            missing.append(("pilot_evidence", field, "试点证据和关闭意见回填前必须形成可追溯证据、意见、复核人和日期。"))
    if pilot.get("evidence_status", "") != "ready":
        missing.append(("pilot_evidence", "evidence_status", "试点证据状态必须为 ready 后才可预览回填源工作台。"))
    if handoff is None:
        missing.append(("pilot_handoff", "pilot_handoff_id", "试点批次必须有对应签核交接页。"))
    else:
        if handoff.get("signature_status", "") != "completed":
            missing.append(("pilot_handoff", "signature_status", "岗位签核必须 completed 后才可回填源工作台。"))
        if handoff.get("handoff_status", "") != "completed":
            missing.append(("pilot_handoff", "handoff_status", "交接复核必须 completed 后才可回填源工作台。"))
        for field in ["assigned_person", "reviewer", "actual_finish_date"]:
            if not handoff.get(field, "").strip():
                missing.append(("pilot_handoff", field, "签核交接完成前必须保留执行人、复核人和完成日期。"))
    return missing


def build_return_preview(pilot_dir: Path, workbench_dir: Path, output_dir: Path) -> dict[str, Any]:
    pilot_manifest = json.loads((pilot_dir / PILOT_FILES["manifest"]).read_text(encoding="utf-8"))
    workbench_manifest = json.loads((workbench_dir / WORKBENCH_FILES["manifest"]).read_text(encoding="utf-8"))
    pilot_rows = read_csv(pilot_dir / PILOT_FILES["pilot_evidence"])
    handoff_rows = read_csv(pilot_dir / PILOT_FILES["pilot_handoff"])
    evidence_rows = read_csv(workbench_dir / WORKBENCH_FILES["evidence_template"])
    closure_rows = read_csv(workbench_dir / WORKBENCH_FILES["closure_template"])

    handoff_by_batch = {row.get("pilot_batch_id", ""): row for row in handoff_rows}
    evidence_ids = {row.get("closure_item_id", "") for row in evidence_rows}
    closure_ids = {row.get("closure_item_id", "") for row in closure_rows}

    mapping_rows: list[dict[str, Any]] = []
    source_preview_rows: list[dict[str, Any]] = []
    missing_rows: list[dict[str, Any]] = []
    ready_rows = 0

    for index, pilot in enumerate(pilot_rows, start=1):
        return_id = f"GCPR-{index:03d}"
        closure_id = pilot.get("closure_item_id", "")
        handoff = handoff_by_batch.get(pilot.get("pilot_batch_id", ""))
        missing = missing_for_item(pilot, handoff)
        source_evidence_found = "yes" if closure_id in evidence_ids else "no"
        source_closure_found = "yes" if closure_id in closure_ids else "no"
        if source_evidence_found == "no":
            missing.append(("source_workbench", "03-证据采集模板.csv", "源证据采集模板必须存在对应 closure_item_id。"))
        if source_closure_found == "no":
            missing.append(("source_workbench", "04-拟关闭回填模板.csv", "源拟关闭回填模板必须存在对应 closure_item_id。"))
        item_ready = missing == []
        if item_ready:
            ready_rows += 1
        mapping_rows.append(
            {
                "return_item_id": return_id,
                "pilot_evidence_id": pilot.get("pilot_evidence_id", ""),
                "pilot_batch_id": pilot.get("pilot_batch_id", ""),
                "closure_item_id": closure_id,
                "source_task_id": pilot.get("source_task_id", ""),
                "gate_id": pilot.get("gate_id", ""),
                "task_group": pilot.get("task_group", ""),
                "object_code": pilot.get("object_code", ""),
                "object_name": pilot.get("object_name", ""),
                "owner_role": pilot.get("owner_role", ""),
                "source_evidence_row_found": source_evidence_found,
                "source_closure_row_found": source_closure_found,
                "pilot_handoff_found": "yes" if handoff else "no",
                "return_status": "ready" if item_ready else "blocked",
                "blocks_apply": pilot.get("blocks_apply", "yes"),
                "not_imported": "yes",
                "not_real_record": "yes",
            }
        )
        for target_file in ["governance_closure_workbench/03-证据采集模板.csv", "governance_closure_workbench/04-拟关闭回填模板.csv"]:
            source_preview_rows.append(
                {
                    "return_item_id": return_id,
                    "closure_item_id": closure_id,
                    "target_file": target_file,
                    "target_row_key": closure_id,
                    "proposed_evidence_reference": pilot.get("evidence_reference", ""),
                    "proposed_evidence_summary": pilot.get("evidence_summary", ""),
                    "proposed_closure_comment": pilot.get("closure_comment", ""),
                    "proposed_reviewer": pilot.get("reviewer", ""),
                    "proposed_review_date": pilot.get("review_date", ""),
                    "proposed_evidence_status": pilot.get("evidence_status", ""),
                    "proposed_signature_status": handoff.get("signature_status", "") if handoff else "",
                    "proposed_handoff_status": handoff.get("handoff_status", "") if handoff else "",
                    "ready_for_manual_source_update": "yes" if item_ready else "no",
                    "not_imported": "yes",
                    "not_real_record": "yes",
                }
            )
        for missing_index, (group, field, reason) in enumerate(missing, start=1):
            missing_rows.append(
                {
                    "missing_id": f"{return_id}-M{missing_index:02d}",
                    "return_item_id": return_id,
                    "closure_item_id": closure_id,
                    "field_group": group,
                    "missing_field": field,
                    "why_required": reason,
                    "blocks_apply": pilot.get("blocks_apply", "yes"),
                    "not_imported": "yes",
                    "not_real_record": "yes",
                }
            )

    manifest = {
        "generated_at": dt.datetime.now().replace(microsecond=0).isoformat(),
        "status": "governance_closure_pilot_return_preview_no_database_write",
        "source_pilot_dir": str(pilot_dir),
        "source_closure_workbench_dir": str(workbench_dir),
        "source_pilot_status": pilot_manifest.get("status", ""),
        "source_workbench_status": workbench_manifest.get("status", ""),
        "readiness": "ready_for_manual_source_update" if ready_rows == len(pilot_rows) else "pilot_return_blocked_by_missing_fields",
        "ready_for_governance_closure_preview": "yes" if ready_rows == len(pilot_rows) and pilot_rows else "no",
        "ready_for_lims_apply": "no",
        "files": RETURN_FILES,
        "counts": {
            "pilot_evidence_rows": len(pilot_rows),
            "mapping_rows": len(mapping_rows),
            "source_preview_rows": len(source_preview_rows),
            "missing_field_rows": len(missing_rows),
            "ready_return_items": ready_rows,
            "blocking_return_items": len(pilot_rows) - ready_rows,
            "database_write_performed": 0,
        },
        "source_counts": {
            "pilot": pilot_manifest.get("counts", {}),
            "workbench": workbench_manifest.get("counts", {}),
        },
        "guardrails": GUARDRAILS,
    }

    output_dir.mkdir(parents=True, exist_ok=True)
    output_dir.joinpath(RETURN_FILES["manifest"]).write_text(json.dumps(manifest, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
    write_csv(output_dir / RETURN_FILES["mapping"], mapping_rows, MAPPING_FIELDS)
    write_csv(output_dir / RETURN_FILES["source_preview"], source_preview_rows, SOURCE_PREVIEW_FIELDS)
    write_csv(output_dir / RETURN_FILES["missing_fields"], missing_rows, MISSING_FIELDS)
    write_overview(output_dir, manifest, mapping_rows)
    write_rerun_path(output_dir)
    write_readme(output_dir)
    return manifest


def write_overview(output_dir: Path, manifest: dict[str, Any], mapping_rows: list[dict[str, Any]]) -> None:
    lines = [
        "# 治理关闭试点回填预览总览",
        "",
        "本包用于预览最小试点包中的人工结果是否足以回填到治理关闭工作台源模板。",
        "",
        "本包不写数据库，不修改源工作台，不代表人工评审通过、真实培训完成、受控发布或正式写库授权。",
        "",
        "## 关键计数",
        "",
    ]
    for key, value in manifest["counts"].items():
        lines.append(f"- {key}: {value}")
    lines.extend(
        [
            "",
            "## 试点回填状态",
            "",
            *render_table(
                mapping_rows,
                ["return_item_id", "closure_item_id", "object_code", "owner_role", "return_status", "blocks_apply"],
            ),
            "",
            "## 边界",
            "",
        ]
    )
    lines.extend(f"- {guardrail}" for guardrail in GUARDRAILS)
    output_dir.joinpath(RETURN_FILES["overview"]).write_text("\n".join(lines) + "\n", encoding="utf-8")


def write_rerun_path(output_dir: Path) -> None:
    lines = [
        "# 试点回填后复跑路径",
        "",
        "1. 人工在 `governance_closure_pilot_pack/02-试点证据填写页.csv` 和 `03-试点签核交接页.csv` 完成真实证据、意见、复核、签核和交接。",
        "2. 运行本回填预览，确认 `03-仍缺字段清单.csv` 清零。",
        "3. 由人工把可接受结果回填到 `governance_closure_workbench/03-证据采集模板.csv` 和 `04-拟关闭回填模板.csv` 对应 `closure_item_id`。",
        "4. 重新生成 `governance_closure_decision_preview/`。",
        "5. 再生成 `governance_readiness_refresh_preview/`。",
        "",
        "边界：不写数据库；不代表人工评审通过；不写入质量手册正文。",
    ]
    output_dir.joinpath(RETURN_FILES["rerun_path"]).write_text("\n".join(lines) + "\n", encoding="utf-8")


def write_readme(output_dir: Path) -> None:
    lines = [
        "# governance_closure_pilot_return_preview",
        "",
        "用途：预览最小试点包完成后，哪些字段可人工回填到治理关闭工作台，哪些字段仍缺失。",
        "",
        "红线：本目录不写数据库，不修改源工作台，不代表人工评审通过，不代表真实培训完成，不代表受控发布，不写入质量手册正文。",
    ]
    output_dir.joinpath(RETURN_FILES["readme"]).write_text("\n".join(lines) + "\n", encoding="utf-8")


def render_report(manifest: dict[str, Any]) -> str:
    lines = [
        "# QMS 治理关闭试点回填预览生成报告",
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
            "本生成报告只证明试点回填预览已经生成，不代表人工评审通过、真实培训完成、受控发布或正式写库授权。",
            "",
        ]
    )
    return "\n".join(lines)


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--pilot-dir", required=True)
    parser.add_argument("--closure-workbench-dir", required=True)
    parser.add_argument("--output-dir", required=True)
    parser.add_argument("--json-out")
    parser.add_argument("--md-out")
    args = parser.parse_args()

    manifest = build_return_preview(Path(args.pilot_dir), Path(args.closure_workbench_dir), Path(args.output_dir))
    report = {
        "generated_at": manifest["generated_at"],
        "preview_dir": args.output_dir,
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
