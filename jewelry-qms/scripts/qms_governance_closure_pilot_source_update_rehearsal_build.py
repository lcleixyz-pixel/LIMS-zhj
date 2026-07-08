#!/usr/bin/env python3
"""Build a no-write source-workbench update rehearsal from pilot return preview."""

from __future__ import annotations

import argparse
import csv
import datetime as dt
import json
from pathlib import Path
from typing import Any


RETURN_FILES = {
    "manifest": "governance_closure_pilot_return_manifest.json",
    "source_preview": "02-拟回填源行预览.csv",
    "missing_fields": "03-仍缺字段清单.csv",
}

WORKBENCH_FILES = {
    "manifest": "governance_closure_workbench_manifest.json",
    "evidence_template": "03-证据采集模板.csv",
    "closure_template": "04-拟关闭回填模板.csv",
}

REHEARSAL_FILES = {
    "manifest": "governance_closure_pilot_source_update_manifest.json",
    "overview": "00-源工作台回填补丁预演总览.md",
    "patch_preview": "01-源工作台回填补丁预览.csv",
    "blocked_patches": "02-阻断补丁清单.csv",
    "manual_instructions": "03-人工回填操作说明.md",
    "readme": "README.md",
}

GUARDRAILS = [
    "本补丁预演只读取 governance_closure_pilot_return_preview 和 governance_closure_workbench，不写数据库。",
    "本补丁预演不修改 governance_closure_pilot_return_preview，不修改 governance_closure_workbench，不修改 governance_closure_decision_preview、governance_readiness_dashboard 或任何现用 Word 文件。",
    "本补丁预演不代表人工评审通过，不代表真实培训完成，不代表受控发布，不代表正式写库授权。",
    "本补丁预演只显示人工将来可能回填的源表字段；缺字段或阻断项未清零时不得回填源工作台。",
    "资质状态口径：已取得 CMA，CNAS 申请中；不得表述为已取得 CNAS。",
    "以 LIMS 当前导出的 2022 程序清单作为现行程序目录。",
    "jewelry-qms 仍为建设中系统，只进入实施计划、演练和治理准备材料，不写入质量手册正文。",
]

PATCH_FIELDS = [
    "patch_id",
    "return_item_id",
    "closure_item_id",
    "target_file",
    "target_row_key",
    "target_field",
    "current_value",
    "proposed_value",
    "proposed_value_source",
    "patch_action",
    "update_ready",
    "block_reason",
    "ready_for_manual_source_update",
    "not_imported",
    "not_real_record",
    "no_source_modified",
]

TARGET_FIELD_MAP = {
    "governance_closure_workbench/03-证据采集模板.csv": [
        ("evidence_reference", "proposed_evidence_reference"),
        ("evidence_owner", "proposed_reviewer"),
        ("evidence_date", "proposed_review_date"),
        ("evidence_result", "proposed_evidence_summary"),
    ],
    "governance_closure_workbench/04-拟关闭回填模板.csv": [
        ("evidence_reference", "proposed_evidence_reference"),
        ("closure_comment", "proposed_closure_comment"),
        ("reviewer", "proposed_reviewer"),
        ("review_date", "proposed_review_date"),
        ("proposed_closure_status", "__closed_when_ready__"),
        ("closure_result", "__closed_when_ready__"),
        ("blocks_apply", "__no_when_ready__"),
    ],
}


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


def source_rows_by_id(workbench_dir: Path, target_file: str) -> dict[str, dict[str, str]]:
    filename = target_file.split("/", 1)[1]
    return {row.get("closure_item_id", ""): row for row in read_csv(workbench_dir / filename)}


def proposed_value(source_preview: dict[str, str], source_key: str, ready: bool) -> str:
    if source_key == "__closed_when_ready__":
        return "closed" if ready else ""
    if source_key == "__no_when_ready__":
        return "no" if ready else ""
    return source_preview.get(source_key, "")


def build_rehearsal(pilot_return_dir: Path, workbench_dir: Path, output_dir: Path) -> dict[str, Any]:
    return_manifest = json.loads((pilot_return_dir / RETURN_FILES["manifest"]).read_text(encoding="utf-8"))
    workbench_manifest = json.loads((workbench_dir / WORKBENCH_FILES["manifest"]).read_text(encoding="utf-8"))
    source_preview_rows = read_csv(pilot_return_dir / RETURN_FILES["source_preview"])
    missing_rows = read_csv(pilot_return_dir / RETURN_FILES["missing_fields"])

    evidence_by_id = source_rows_by_id(workbench_dir, "governance_closure_workbench/03-证据采集模板.csv")
    closure_by_id = source_rows_by_id(workbench_dir, "governance_closure_workbench/04-拟关闭回填模板.csv")
    source_lookup = {
        "governance_closure_workbench/03-证据采集模板.csv": evidence_by_id,
        "governance_closure_workbench/04-拟关闭回填模板.csv": closure_by_id,
    }

    patch_rows: list[dict[str, Any]] = []
    for preview in source_preview_rows:
        target_file = preview.get("target_file", "")
        target_fields = TARGET_FIELD_MAP.get(target_file, [])
        source_row = source_lookup.get(target_file, {}).get(preview.get("closure_item_id", ""))
        ready = preview.get("ready_for_manual_source_update", "") == "yes"
        for target_field, source_key in target_fields:
            proposed = proposed_value(preview, source_key, ready)
            current = source_row.get(target_field, "") if source_row else ""
            reasons: list[str] = []
            if source_row is None:
                reasons.append("source_row_missing")
            if not ready:
                reasons.append("pilot_return_not_ready")
            if ready and proposed == "":
                reasons.append("proposed_value_blank")
            update_ready = ready and source_row is not None and proposed != "" and proposed != current
            if reasons:
                action = "blocked_no_update"
            elif update_ready:
                action = "manual_update_candidate"
            else:
                action = "no_change_candidate"
            patch_rows.append(
                {
                    "patch_id": f"GCPSU-{len(patch_rows) + 1:03d}",
                    "return_item_id": preview.get("return_item_id", ""),
                    "closure_item_id": preview.get("closure_item_id", ""),
                    "target_file": target_file,
                    "target_row_key": preview.get("target_row_key", ""),
                    "target_field": target_field,
                    "current_value": current,
                    "proposed_value": proposed,
                    "proposed_value_source": source_key,
                    "patch_action": action,
                    "update_ready": "yes" if update_ready else "no",
                    "block_reason": ";".join(reasons),
                    "ready_for_manual_source_update": preview.get("ready_for_manual_source_update", ""),
                    "not_imported": "yes",
                    "not_real_record": "yes",
                    "no_source_modified": "yes",
                }
            )

    blocked_rows = [row for row in patch_rows if row["patch_action"] == "blocked_no_update"]
    ready_rows = [row for row in patch_rows if row["update_ready"] == "yes"]
    readiness = "ready_for_manual_source_update" if patch_rows and not blocked_rows else "source_update_blocked_by_pilot_return"
    ready_for_source_update = "yes" if patch_rows and not blocked_rows else "no"

    manifest = {
        "generated_at": dt.datetime.now().replace(microsecond=0).isoformat(),
        "status": "governance_closure_pilot_source_update_rehearsal_no_write",
        "source_pilot_return_dir": str(pilot_return_dir),
        "source_closure_workbench_dir": str(workbench_dir),
        "source_pilot_return_status": return_manifest.get("status", ""),
        "source_workbench_status": workbench_manifest.get("status", ""),
        "readiness": readiness,
        "ready_for_source_workbench_update": ready_for_source_update,
        "ready_for_governance_closure_preview": "yes" if ready_for_source_update == "yes" else "no",
        "ready_for_lims_apply": "no",
        "files": REHEARSAL_FILES,
        "counts": {
            "source_preview_rows": len(source_preview_rows),
            "missing_field_rows": len(missing_rows),
            "patch_rows": len(patch_rows),
            "ready_patch_rows": len(ready_rows),
            "blocked_patch_rows": len(blocked_rows),
            "manual_update_candidate_rows": sum(1 for row in patch_rows if row["patch_action"] == "manual_update_candidate"),
            "source_workbench_modified": 0,
            "database_write_performed": 0,
        },
        "source_counts": {
            "pilot_return": return_manifest.get("counts", {}),
            "workbench": workbench_manifest.get("counts", {}),
        },
        "guardrails": GUARDRAILS,
    }

    output_dir.mkdir(parents=True, exist_ok=True)
    (output_dir / REHEARSAL_FILES["manifest"]).write_text(json.dumps(manifest, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
    write_csv(output_dir / REHEARSAL_FILES["patch_preview"], patch_rows, PATCH_FIELDS)
    write_csv(output_dir / REHEARSAL_FILES["blocked_patches"], blocked_rows, PATCH_FIELDS)
    write_overview(output_dir, manifest)
    write_manual_instructions(output_dir)
    write_readme(output_dir)
    return {
        "generated_at": manifest["generated_at"],
        "rehearsal_dir": str(output_dir),
        "status": "passed",
        "readiness": manifest["readiness"],
        "ready_for_source_workbench_update": manifest["ready_for_source_workbench_update"],
        "ready_for_governance_closure_preview": manifest["ready_for_governance_closure_preview"],
        "ready_for_lims_apply": manifest["ready_for_lims_apply"],
        "counts": manifest["counts"],
        "findings": [],
    }


def write_overview(output_dir: Path, manifest: dict[str, Any]) -> None:
    counts = manifest["counts"]
    lines = [
        "# 治理关闭试点源工作台回填补丁预演总览",
        "",
        f"生成时间：{manifest['generated_at']}",
        f"readiness：{manifest['readiness']}",
        f"ready_for_source_workbench_update：{manifest['ready_for_source_workbench_update']}",
        f"ready_for_governance_closure_preview：{manifest['ready_for_governance_closure_preview']}",
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
            "- 不写数据库。",
            "- 不修改 governance_closure_pilot_return_preview、governance_closure_workbench 或任何现用 Word 文件。",
            "- 不代表人工评审通过、真实培训完成、受控发布或正式写库授权。",
            "- jewelry-qms 仍为建设中系统，不写入质量手册正文。",
            "",
            "当前试点回填预览仍存在缺字段和阻断项，因此本预演中的补丁均不得执行。",
            "",
        ]
    )
    (output_dir / REHEARSAL_FILES["overview"]).write_text("\n".join(lines), encoding="utf-8")


def write_manual_instructions(output_dir: Path) -> None:
    lines = [
        "# 人工回填操作说明",
        "",
        "本文件只说明未来人工回填源工作台时的复核顺序，不是授权执行记录。",
        "",
        "1. 先补齐 `governance_closure_pilot_pack/02-试点证据填写页.csv` 中的证据引用、证据摘要、关闭意见、复核人和日期。",
        "2. 再补齐 `governance_closure_pilot_pack/03-试点签核交接页.csv` 中的执行人、复核人、完成日期、签核状态和交接状态。",
        "3. 重新生成 `governance_closure_pilot_return_preview/`，确认缺字段和阻断项清零。",
        "4. 重新生成本补丁预演包，确认 `01-源工作台回填补丁预览.csv` 中仅出现可人工更新候选行。",
        "5. 经质量负责人/文件管理员确认后，才可由人工按源工作台字段回填；本包本身不修改任何源文件。",
        "",
        "边界：不写数据库，不代表人工评审通过，不代表真实培训完成，不代表受控发布，不写入质量手册正文。",
        "",
    ]
    (output_dir / REHEARSAL_FILES["manual_instructions"]).write_text("\n".join(lines), encoding="utf-8")


def write_readme(output_dir: Path) -> None:
    lines = [
        "# 治理关闭试点源工作台回填补丁预演包",
        "",
        "本包把试点回填预览进一步拆成源工作台逐字段补丁预演，用于人工回填前检查会改哪张表、哪一行、哪一列。",
        "",
        "## 文件",
        "",
        "- `governance_closure_pilot_source_update_manifest.json`：总状态、计数和边界。",
        "- `00-源工作台回填补丁预演总览.md`：阅读入口。",
        "- `01-源工作台回填补丁预览.csv`：逐字段补丁预演。",
        "- `02-阻断补丁清单.csv`：当前不得执行的补丁行。",
        "- `03-人工回填操作说明.md`：后续人工补齐和复跑顺序。",
        "",
        "## 边界",
        "",
        "不写数据库，不修改源工作台，不代表人工评审通过，不代表真实培训完成，不代表受控发布，不写入质量手册正文。",
        "",
    ]
    (output_dir / REHEARSAL_FILES["readme"]).write_text("\n".join(lines), encoding="utf-8")


def render_markdown(result: dict[str, Any]) -> str:
    lines = [
        "# QMS 治理关闭试点源工作台回填补丁预演生成报告",
        "",
        f"生成时间：{result['generated_at']}",
        f"预演包：`{result['rehearsal_dir']}`",
        f"结论：{result['status']}",
        f"readiness：{result['readiness']}",
        f"ready_for_source_workbench_update：{result['ready_for_source_workbench_update']}",
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
    parser.add_argument("--pilot-return-dir", required=True)
    parser.add_argument("--closure-workbench-dir", required=True)
    parser.add_argument("--output-dir", required=True)
    parser.add_argument("--json-out")
    parser.add_argument("--md-out")
    args = parser.parse_args()

    result = build_rehearsal(Path(args.pilot_return_dir), Path(args.closure_workbench_dir), Path(args.output_dir))
    if args.json_out:
        Path(args.json_out).write_text(json.dumps(result, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
    if args.md_out:
        Path(args.md_out).write_text(render_markdown(result), encoding="utf-8")
    print(json.dumps(result, ensure_ascii=False, indent=2))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
