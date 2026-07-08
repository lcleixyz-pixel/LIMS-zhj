#!/usr/bin/env python3
"""Build a no-write governance closure execution pack from the closure workbench."""

from __future__ import annotations

import argparse
import csv
import datetime as dt
import json
from collections import Counter, defaultdict
from pathlib import Path
from typing import Any


WORKBENCH_FILES = {
    "manifest": "governance_closure_workbench_manifest.json",
    "role_task_pack": "02-按角色任务包.csv",
    "evidence_template": "03-证据采集模板.csv",
    "closure_template": "04-拟关闭回填模板.csv",
}

EXECUTION_FILES = {
    "manifest": "governance_closure_execution_manifest.json",
    "overview": "00-治理闭环执行包总览.md",
    "execution_batches": "01-闭环执行批次.csv",
    "signature_register": "02-岗位签核页模板.csv",
    "handoff_checklist": "03-交接复核清单.csv",
    "route_index": "04-回填路径索引.csv",
    "blocking_summary": "05-阻断批次摘要.md",
    "readme": "README.md",
}

GUARDRAILS = [
    "本执行包只读取 governance_closure_workbench 生成批次、签核和回填路径，不写数据库。",
    "本执行包不修改 governance_closure_workbench、governance_closure_decision_preview、governance_readiness_dashboard 或任何现用 Word 文件。",
    "本执行包不代表人工评审通过，不代表真实培训完成，不代表受控发布，不代表正式写库授权。",
    "所有签核、交接复核和回填路径默认 pending；只有人工填写责任人、复核人和日期后才可进入关闭意见预览。",
    "资质状态口径：已取得 CMA，CNAS 申请中；不得表述为已取得 CNAS。",
    "以 LIMS 当前导出的 2022 程序清单作为现行程序目录。",
    "jewelry-qms 仍为建设中系统，只进入实施计划、演练和治理准备材料，不写入质量手册正文。",
]

BATCH_FIELDS = [
    "execution_batch_id",
    "source_role_batch_id",
    "owner_role",
    "gate_id",
    "task_group",
    "task_count",
    "blocking_count",
    "evidence_rows",
    "closure_rows",
    "source_paths",
    "suggested_sequence",
    "execution_status",
    "next_source_evidence_file",
    "next_source_closure_file",
    "not_imported",
    "not_real_record",
]

SIGNATURE_FIELDS = [
    "signature_id",
    "owner_role",
    "task_batches",
    "total_tasks",
    "blocking_tasks",
    "assigned_person",
    "reviewer",
    "planned_finish_date",
    "actual_finish_date",
    "signature_status",
    "required_before_refresh",
    "not_imported",
    "not_real_record",
]

HANDOFF_FIELDS = [
    "handoff_check_id",
    "execution_batch_id",
    "owner_role",
    "gate_id",
    "task_group",
    "required_input_files",
    "required_fields",
    "downstream_preview",
    "downstream_refresh",
    "check_status",
    "blocks_apply",
    "not_imported",
    "not_real_record",
]

ROUTE_FIELDS = [
    "closure_item_id",
    "source_task_id",
    "execution_batch_id",
    "gate_id",
    "task_group",
    "object_code",
    "object_name",
    "owner_role",
    "source_evidence_file",
    "source_closure_file",
    "downstream_preview",
    "downstream_refresh",
    "route_status",
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


def batch_key(row: dict[str, str]) -> tuple[str, str, str]:
    return (row.get("owner_role", ""), row.get("gate_id", ""), row.get("task_group", ""))


def build_execution_batches(
    role_rows: list[dict[str, str]],
    evidence_rows: list[dict[str, str]],
    closure_rows: list[dict[str, str]],
) -> list[dict[str, Any]]:
    evidence_counts = Counter(batch_key(row) for row in evidence_rows)
    closure_counts = Counter(batch_key(row) for row in closure_rows)
    rows: list[dict[str, Any]] = []
    for index, role in enumerate(role_rows, start=1):
        key = batch_key(role)
        rows.append(
            {
                "execution_batch_id": f"GCEB-{index:03d}",
                "source_role_batch_id": role.get("role_batch_id", ""),
                "owner_role": role.get("owner_role", ""),
                "gate_id": role.get("gate_id", ""),
                "task_group": role.get("task_group", ""),
                "task_count": role.get("task_count", "0"),
                "blocking_count": role.get("blocking_count", "0"),
                "evidence_rows": evidence_counts.get(key, 0),
                "closure_rows": closure_counts.get(key, 0),
                "source_paths": role.get("source_paths", ""),
                "suggested_sequence": index,
                "execution_status": "pending",
                "next_source_evidence_file": "governance_closure_workbench/03-证据采集模板.csv",
                "next_source_closure_file": "governance_closure_workbench/04-拟关闭回填模板.csv",
                "not_imported": "yes",
                "not_real_record": "yes",
            }
        )
    return rows


def build_signature_rows(batch_rows: list[dict[str, Any]]) -> list[dict[str, Any]]:
    grouped: dict[str, list[dict[str, Any]]] = defaultdict(list)
    for row in batch_rows:
        grouped[str(row.get("owner_role", ""))].append(row)

    rows: list[dict[str, Any]] = []
    for index, (owner_role, items) in enumerate(sorted(grouped.items()), start=1):
        total_tasks = sum(int_value(item.get("task_count")) for item in items)
        blocking_tasks = sum(int_value(item.get("blocking_count")) for item in items)
        rows.append(
            {
                "signature_id": f"GCES-{index:03d}",
                "owner_role": owner_role,
                "task_batches": len(items),
                "total_tasks": total_tasks,
                "blocking_tasks": blocking_tasks,
                "assigned_person": "",
                "reviewer": "",
                "planned_finish_date": "",
                "actual_finish_date": "",
                "signature_status": "pending",
                "required_before_refresh": "yes" if blocking_tasks else "no",
                "not_imported": "yes",
                "not_real_record": "yes",
            }
        )
    return rows


def build_handoff_rows(batch_rows: list[dict[str, Any]]) -> list[dict[str, Any]]:
    rows: list[dict[str, Any]] = []
    for index, batch in enumerate(batch_rows, start=1):
        rows.append(
            {
                "handoff_check_id": f"GCEH-{index:03d}",
                "execution_batch_id": batch.get("execution_batch_id", ""),
                "owner_role": batch.get("owner_role", ""),
                "gate_id": batch.get("gate_id", ""),
                "task_group": batch.get("task_group", ""),
                "required_input_files": "03-证据采集模板.csv | 04-拟关闭回填模板.csv",
                "required_fields": "evidence_reference | closure_comment | reviewer | review_date",
                "downstream_preview": "governance_closure_decision_preview",
                "downstream_refresh": "governance_readiness_refresh_preview",
                "check_status": "pending",
                "blocks_apply": "yes" if int_value(batch.get("blocking_count")) > 0 else "no",
                "not_imported": "yes",
                "not_real_record": "yes",
            }
        )
    return rows


def build_route_rows(closure_rows: list[dict[str, str]], batch_rows: list[dict[str, Any]]) -> list[dict[str, Any]]:
    batch_by_key = {batch_key(row): row for row in batch_rows}  # type: ignore[arg-type]
    rows: list[dict[str, Any]] = []
    for closure in closure_rows:
        batch = batch_by_key.get(batch_key(closure), {})
        rows.append(
            {
                "closure_item_id": closure.get("closure_item_id", ""),
                "source_task_id": closure.get("source_task_id", ""),
                "execution_batch_id": batch.get("execution_batch_id", ""),
                "gate_id": closure.get("gate_id", ""),
                "task_group": closure.get("task_group", ""),
                "object_code": closure.get("object_code", ""),
                "object_name": closure.get("object_name", ""),
                "owner_role": closure.get("owner_role", ""),
                "source_evidence_file": "governance_closure_workbench/03-证据采集模板.csv",
                "source_closure_file": "governance_closure_workbench/04-拟关闭回填模板.csv",
                "downstream_preview": "governance_closure_decision_preview",
                "downstream_refresh": "governance_readiness_refresh_preview",
                "route_status": "pending",
                "blocks_apply": closure.get("blocks_apply", ""),
                "not_imported": "yes",
                "not_real_record": "yes",
            }
        )
    return rows


def write_overview(output_dir: Path, manifest: dict[str, Any], batch_rows: list[dict[str, Any]]) -> None:
    counts = manifest["counts"]
    top_batches = sorted(batch_rows, key=lambda row: int_value(row.get("blocking_count")), reverse=True)[:12]
    lines = [
        "# QMS 治理闭环执行包总览",
        "",
        f"生成时间：{manifest['generated_at']}",
        f"来源治理关闭工作台：`{manifest['source_closure_workbench_dir']}`",
        "",
        "## 结论",
        "",
        f"- readiness: {manifest['readiness']}",
        f"- ready_for_governance_closure_preview: {manifest['ready_for_governance_closure_preview']}",
        f"- ready_for_lims_apply: {manifest['ready_for_lims_apply']}",
        f"- execution_batches: {counts['execution_batches']}",
        f"- signature_rows: {counts['signature_rows']}",
        f"- route_rows: {counts['route_rows']}",
        f"- blocking_route_items: {counts['blocking_route_items']}",
        "",
        "该执行包把治理关闭工作台中的证据采集、拟关闭回填和角色任务包组织成可分派、可签核、可回填的闭环执行材料；它不写数据库、不修改源工作台、不代表人工评审通过、真实培训完成、受控发布或正式写库授权。",
        "",
        "## 边界",
        "",
    ]
    lines.extend(f"- {item}" for item in GUARDRAILS)
    lines.extend(["", "## 优先批次", ""])
    lines.extend(render_table(top_batches, ["execution_batch_id", "owner_role", "gate_id", "task_group", "task_count", "blocking_count"]))
    lines.append("")
    (output_dir / EXECUTION_FILES["overview"]).write_text("\n".join(lines), encoding="utf-8")


def write_blocking_summary(output_dir: Path, manifest: dict[str, Any], batch_rows: list[dict[str, Any]]) -> None:
    gate_counts = Counter(str(row.get("gate_id", "")) for row in batch_rows for _ in range(int_value(row.get("blocking_count"))))
    top_batches = sorted(batch_rows, key=lambda row: int_value(row.get("blocking_count")), reverse=True)[:20]
    lines = [
        "# 治理闭环执行阻断批次摘要",
        "",
        "本文件只用于安排人工闭环执行，不写数据库，不代表人工评审通过，不写入质量手册正文。",
        "",
        "## 按闸门阻断数量",
        "",
    ]
    for gate_id, count in sorted(gate_counts.items()):
        lines.append(f"- {gate_id}: {count}")
    lines.extend(["", "## 前 20 个优先批次", ""])
    lines.extend(render_table(top_batches, ["execution_batch_id", "owner_role", "gate_id", "task_group", "blocking_count", "source_paths"]))
    lines.append("")
    (output_dir / EXECUTION_FILES["blocking_summary"]).write_text("\n".join(lines), encoding="utf-8")


def write_readme(output_dir: Path, manifest: dict[str, Any]) -> None:
    lines = [
        "# governance_closure_execution_pack",
        "",
        "用途：把治理关闭工作台转成可分派、可签核、可回填的闭环执行包。",
        "",
        "## 文件",
        "",
    ]
    for key, filename in EXECUTION_FILES.items():
        lines.append(f"- `{filename}`: {key}")
    lines.extend(
        [
            "",
            "## 使用顺序",
            "",
            "1. 先看 `01-闭环执行批次.csv`，按 owner_role 和 gate_id 分派任务。",
            "2. 在 `02-岗位签核页模板.csv` 中补齐真实责任人、复核人和完成日期。",
            "3. 依据 `03-交接复核清单.csv` 回填治理关闭工作台的证据和拟关闭意见。",
            "4. 用 `04-回填路径索引.csv` 核对每个 closure_item_id 对应的回填路径。",
            "5. 重新生成 `governance_closure_decision_preview/` 和 `governance_readiness_refresh_preview/`。",
            "",
            "## 边界",
            "",
        ]
    )
    lines.extend(f"- {item}" for item in GUARDRAILS)
    lines.extend(
        [
            "",
            "## 当前状态",
            "",
            f"- readiness: {manifest['readiness']}",
            f"- pending_signature_rows: {manifest['counts']['pending_signature_rows']}",
            f"- pending_handoff_checks: {manifest['counts']['pending_handoff_checks']}",
            f"- pending_route_items: {manifest['counts']['pending_route_items']}",
            "",
        ]
    )
    (output_dir / EXECUTION_FILES["readme"]).write_text("\n".join(lines), encoding="utf-8")


def build_execution_pack(closure_workbench_dir: Path, output_dir: Path) -> dict[str, Any]:
    output_dir.mkdir(parents=True, exist_ok=True)
    manifest_path = closure_workbench_dir / WORKBENCH_FILES["manifest"]
    source_manifest = json.loads(manifest_path.read_text(encoding="utf-8")) if manifest_path.exists() else {}
    role_rows = read_csv(closure_workbench_dir / WORKBENCH_FILES["role_task_pack"])
    evidence_rows = read_csv(closure_workbench_dir / WORKBENCH_FILES["evidence_template"])
    closure_rows = read_csv(closure_workbench_dir / WORKBENCH_FILES["closure_template"])

    batch_rows = build_execution_batches(role_rows, evidence_rows, closure_rows)
    signature_rows = build_signature_rows(batch_rows)
    handoff_rows = build_handoff_rows(batch_rows)
    route_rows = build_route_rows(closure_rows, batch_rows)

    pending_signature_rows = sum(1 for row in signature_rows if row["signature_status"] == "pending")
    pending_handoff_checks = sum(1 for row in handoff_rows if row["check_status"] == "pending")
    pending_route_items = sum(1 for row in route_rows if row["route_status"] == "pending")
    blocking_route_items = sum(1 for row in route_rows if row["blocks_apply"] == "yes")

    ready_for_preview = "yes" if pending_signature_rows == 0 and pending_handoff_checks == 0 and pending_route_items == 0 else "no"
    readiness = "ready_for_governance_closure_preview" if ready_for_preview == "yes" else "blocked_by_unsigned_execution"
    manifest = {
        "generated_at": dt.datetime.now().isoformat(timespec="seconds"),
        "status": "governance_closure_execution_pack_no_database_write",
        "source_closure_workbench_dir": str(closure_workbench_dir),
        "source_workbench_status": source_manifest.get("status", ""),
        "readiness": readiness,
        "ready_for_governance_closure_preview": ready_for_preview,
        "ready_for_lims_apply": "no",
        "files": EXECUTION_FILES,
        "counts": {
            "execution_batches": len(batch_rows),
            "signature_rows": len(signature_rows),
            "handoff_checks": len(handoff_rows),
            "route_rows": len(route_rows),
            "source_closure_items": len(closure_rows),
            "blocking_route_items": blocking_route_items,
            "pending_signature_rows": pending_signature_rows,
            "pending_handoff_checks": pending_handoff_checks,
            "pending_route_items": pending_route_items,
            "database_write_performed": 0,
        },
        "source_counts": source_manifest.get("counts", {}),
        "guardrails": GUARDRAILS,
    }

    write_csv(output_dir / EXECUTION_FILES["execution_batches"], batch_rows, BATCH_FIELDS)
    write_csv(output_dir / EXECUTION_FILES["signature_register"], signature_rows, SIGNATURE_FIELDS)
    write_csv(output_dir / EXECUTION_FILES["handoff_checklist"], handoff_rows, HANDOFF_FIELDS)
    write_csv(output_dir / EXECUTION_FILES["route_index"], route_rows, ROUTE_FIELDS)
    write_overview(output_dir, manifest, batch_rows)
    write_blocking_summary(output_dir, manifest, batch_rows)
    write_readme(output_dir, manifest)
    (output_dir / EXECUTION_FILES["manifest"]).write_text(json.dumps(manifest, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
    return manifest


def render_report(manifest: dict[str, Any], output_dir: Path) -> str:
    counts = manifest["counts"]
    lines = [
        "# QMS 治理闭环执行包生成报告",
        "",
        f"生成时间：{manifest['generated_at']}",
        f"输出目录：`{output_dir}`",
        f"结论：{manifest['readiness']}",
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
            "本报告只说明执行包已经生成，不代表人工评审通过、真实培训完成、受控发布或正式写库授权。",
            "",
        ]
    )
    return "\n".join(lines)


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--closure-workbench-dir", required=True)
    parser.add_argument("--output-dir", required=True)
    parser.add_argument("--json-out")
    parser.add_argument("--md-out")
    args = parser.parse_args()

    manifest = build_execution_pack(Path(args.closure_workbench_dir), Path(args.output_dir))
    report = {
        "generated_at": manifest["generated_at"],
        "output_dir": args.output_dir,
        "status": "passed",
        "readiness": manifest["readiness"],
        "ready_for_governance_closure_preview": manifest["ready_for_governance_closure_preview"],
        "ready_for_lims_apply": manifest["ready_for_lims_apply"],
        "counts": manifest["counts"],
    }
    if args.json_out:
        Path(args.json_out).write_text(json.dumps(report, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
    if args.md_out:
        Path(args.md_out).write_text(render_report(manifest, Path(args.output_dir)), encoding="utf-8")
    print(json.dumps(report, ensure_ascii=False, indent=2))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
