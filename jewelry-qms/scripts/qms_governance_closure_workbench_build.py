#!/usr/bin/env python3
"""Build a no-write governance closure workbench from the readiness dashboard."""

from __future__ import annotations

import argparse
import csv
import datetime as dt
import json
from collections import Counter, defaultdict
from pathlib import Path
from typing import Any


DASHBOARD_FILES = {
    "manifest": "governance_readiness_manifest.json",
    "gate_register": "01-总闸门清单.csv",
    "human_task_register": "02-人工处理任务清单.csv",
}

WORKBENCH_FILES = {
    "manifest": "governance_closure_workbench_manifest.json",
    "overview": "00-治理关闭工作台总览.md",
    "gate_closure_matrix": "01-总闸门关闭矩阵.csv",
    "role_task_pack": "02-按角色任务包.csv",
    "evidence_template": "03-证据采集模板.csv",
    "closure_template": "04-拟关闭回填模板.csv",
    "priority_batches": "05-优先关闭批次.md",
    "readme": "README.md",
}

GUARDRAILS = [
    "本工作台只读取 governance_readiness_dashboard 生成治理关闭和证据回填清单，不写数据库。",
    "本工作台不修改 governance_readiness_dashboard、human_review_pack、stage2_structured_review_workbench 或任何现用 Word 文件。",
    "本工作台不代表人工评审通过，不代表真实培训完成，不代表受控发布，不代表正式写库授权。",
    "所有 closure_status 为空或 pending 的阻断项保持阻断；关闭必须由人工补齐证据、意见、复核人和日期。",
    "资质状态口径：已取得 CMA，CNAS 申请中；不得表述为已取得 CNAS。",
    "以 LIMS 当前导出的 2022 程序清单作为现行程序目录。",
    "jewelry-qms 仍为建设中系统，只进入实施计划、演练和治理准备材料，不写入质量手册正文。",
]

GATE_FIELDS = [
    "gate_id",
    "gate_group",
    "gate_name",
    "owner_role",
    "source_total_items",
    "source_pending_items",
    "source_blocking_items",
    "human_task_rows",
    "blocking_task_rows",
    "closure_readiness",
    "source_path",
    "next_action",
    "evidence_needed",
    "not_real_record",
]

ROLE_FIELDS = [
    "role_batch_id",
    "owner_role",
    "gate_id",
    "task_group",
    "task_count",
    "blocking_count",
    "source_paths",
    "first_object_code",
    "suggested_handling_order",
    "not_real_record",
]

EVIDENCE_FIELDS = [
    "closure_item_id",
    "source_task_id",
    "gate_id",
    "task_group",
    "object_code",
    "object_name",
    "owner_role",
    "human_action",
    "evidence_to_check",
    "evidence_type",
    "evidence_reference",
    "evidence_owner",
    "evidence_date",
    "evidence_result",
    "blocks_apply",
    "source_path",
    "not_real_record",
]

CLOSURE_FIELDS = [
    "closure_item_id",
    "source_task_id",
    "gate_id",
    "task_group",
    "object_code",
    "object_name",
    "owner_role",
    "source_current_status",
    "proposed_closure_status",
    "evidence_reference",
    "closure_comment",
    "reviewer",
    "review_date",
    "closure_result",
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


def is_blocking_task(row: dict[str, str]) -> bool:
    return row.get("blocking_if_unresolved", "").strip().lower() == "yes"


def evidence_type(task_group: str) -> str:
    mapping = {
        "manual_clause": "manual_clause_review_evidence",
        "record_template": "record_template_review_evidence",
        "record_template_field_confirmation": "field_confirmation_evidence",
        "manual_revision_decision": "manual_revision_decision_evidence",
        "controlled_release_approval": "release_approval_evidence",
        "release_execution_template_review": "release_execution_template_evidence",
        "staff_learning_task": "learning_confirmation_evidence",
        "comprehension_question": "comprehension_confirmation_evidence",
        "feedback_closure": "feedback_closure_evidence",
        "write_preview_review": "write_preview_review_evidence",
        "stage2_structured_review": "stage2_review_evidence",
        "preapply_gate": "preapply_authorization_evidence",
        "attachment_form_disposition": "attachment_disposition_evidence",
        "user_authorization": "user_authorization_evidence",
    }
    return mapping.get(task_group, "governance_evidence")


def render_table(rows: list[dict[str, Any]], columns: list[str]) -> list[str]:
    lines = ["| " + " | ".join(columns) + " |", "|" + "|".join(["---"] * len(columns)) + "|"]
    for row in rows:
        values = [str(row.get(column, "")).replace("\n", " ").replace("|", "／") for column in columns]
        lines.append("| " + " | ".join(values) + " |")
    return lines


def build_gate_matrix(gate_rows: list[dict[str, str]], task_rows: list[dict[str, str]]) -> list[dict[str, Any]]:
    by_gate: dict[str, list[dict[str, str]]] = defaultdict(list)
    for row in task_rows:
        by_gate[row.get("gate_id", "")].append(row)

    matrix: list[dict[str, Any]] = []
    for gate in gate_rows:
        gate_id = gate.get("gate_id", "")
        tasks = by_gate.get(gate_id, [])
        blocking_tasks = sum(1 for task in tasks if is_blocking_task(task))
        matrix.append(
            {
                "gate_id": gate_id,
                "gate_group": gate.get("gate_group", ""),
                "gate_name": gate.get("gate_name", ""),
                "owner_role": gate.get("owner_role", ""),
                "source_total_items": gate.get("total_items", ""),
                "source_pending_items": gate.get("pending_items", ""),
                "source_blocking_items": gate.get("blocking_items", ""),
                "human_task_rows": len(tasks),
                "blocking_task_rows": blocking_tasks,
                "closure_readiness": "blocked_by_open_closures" if blocking_tasks else "ready_after_review",
                "source_path": gate.get("source_path", ""),
                "next_action": gate.get("next_action", ""),
                "evidence_needed": gate.get("evidence_needed", ""),
                "not_real_record": "yes",
            }
        )
    return matrix


def build_role_pack(task_rows: list[dict[str, str]]) -> list[dict[str, Any]]:
    grouped: dict[tuple[str, str, str], list[dict[str, str]]] = defaultdict(list)
    for row in task_rows:
        grouped[(row.get("owner_role", ""), row.get("gate_id", ""), row.get("task_group", ""))].append(row)

    rows: list[dict[str, Any]] = []
    for index, ((owner_role, gate_id, task_group), items) in enumerate(sorted(grouped.items()), start=1):
        source_paths = sorted({item.get("source_path", "") for item in items if item.get("source_path")})
        rows.append(
            {
                "role_batch_id": f"GCB-{index:03d}",
                "owner_role": owner_role,
                "gate_id": gate_id,
                "task_group": task_group,
                "task_count": len(items),
                "blocking_count": sum(1 for item in items if is_blocking_task(item)),
                "source_paths": " | ".join(source_paths),
                "first_object_code": items[0].get("object_code", "") if items else "",
                "suggested_handling_order": "先补证据，再写拟关闭意见，最后由质量负责人复核。",
                "not_real_record": "yes",
            }
        )
    return rows


def build_evidence_rows(task_rows: list[dict[str, str]]) -> list[dict[str, Any]]:
    rows: list[dict[str, Any]] = []
    for index, task in enumerate(task_rows, start=1):
        task_group = task.get("task_group", "")
        rows.append(
            {
                "closure_item_id": f"GC-{index:04d}",
                "source_task_id": task.get("task_id", ""),
                "gate_id": task.get("gate_id", ""),
                "task_group": task_group,
                "object_code": task.get("object_code", ""),
                "object_name": task.get("object_name", ""),
                "owner_role": task.get("owner_role", ""),
                "human_action": task.get("human_action", ""),
                "evidence_to_check": task.get("evidence_to_check", ""),
                "evidence_type": evidence_type(task_group),
                "evidence_reference": "",
                "evidence_owner": "",
                "evidence_date": "",
                "evidence_result": "",
                "blocks_apply": "yes" if is_blocking_task(task) else "no",
                "source_path": task.get("source_path", ""),
                "not_real_record": "yes",
            }
        )
    return rows


def build_closure_rows(task_rows: list[dict[str, str]]) -> list[dict[str, Any]]:
    rows: list[dict[str, Any]] = []
    for index, task in enumerate(task_rows, start=1):
        rows.append(
            {
                "closure_item_id": f"GC-{index:04d}",
                "source_task_id": task.get("task_id", ""),
                "gate_id": task.get("gate_id", ""),
                "task_group": task.get("task_group", ""),
                "object_code": task.get("object_code", ""),
                "object_name": task.get("object_name", ""),
                "owner_role": task.get("owner_role", ""),
                "source_current_status": task.get("current_status", ""),
                "proposed_closure_status": "",
                "evidence_reference": "",
                "closure_comment": "",
                "reviewer": "",
                "review_date": "",
                "closure_result": "pending",
                "blocks_apply": "yes" if is_blocking_task(task) else "no",
                "not_imported": "yes",
                "not_real_record": "yes",
            }
        )
    return rows


def write_overview(output_dir: Path, manifest: dict[str, Any], gate_matrix: list[dict[str, Any]], role_rows: list[dict[str, Any]]) -> None:
    counts = manifest["counts"]
    lines = [
        "# QMS 治理关闭工作台总览",
        "",
        f"生成时间：{manifest['generated_at']}",
        f"来源总览：`{manifest['source_dashboard_dir']}`",
        "",
        "## 结论",
        "",
        f"- readiness: {manifest['readiness']}",
        f"- ready_for_governance_readiness_refresh: {manifest['ready_for_governance_readiness_refresh']}",
        f"- ready_for_lims_apply: {manifest['ready_for_lims_apply']}",
        f"- closure_rows: {counts['closure_rows']}",
        f"- blocking_closure_items: {counts['blocking_closure_items']}",
        f"- open_blocking_items: {counts['open_blocking_items']}",
        "",
        "该工作台把治理就绪总览中的人工任务拆成可填写的证据采集模板和拟关闭回填模板。它只支持人工逐项关灯，不写数据库，不代表人工评审通过、真实培训完成、受控发布或正式写库授权。",
        "",
        "## 关闭路径",
        "",
        "1. 对照 `03-证据采集模板.csv` 补齐 evidence_reference、evidence_owner、evidence_date 和 evidence_result。",
        "2. 对照 `04-拟关闭回填模板.csv` 填写 proposed_closure_status、closure_comment、reviewer 和 review_date。",
        "3. 仅当阻断项均有证据、意见、复核人和日期时，后续才可刷新治理就绪总览或进入 apply-rehearsal 复核。",
        "",
        "## 边界",
        "",
    ]
    lines.extend(f"- {item}" for item in GUARDRAILS)
    lines.extend(["", "## 按闸门摘要", ""])
    lines.extend(render_table(gate_matrix, ["gate_id", "gate_group", "human_task_rows", "blocking_task_rows", "closure_readiness"]))
    lines.extend(["", "## 角色任务包摘要", ""])
    lines.extend(render_table(role_rows[:20], ["role_batch_id", "owner_role", "gate_id", "task_group", "task_count", "blocking_count"]))
    if len(role_rows) > 20:
        lines.append("")
        lines.append(f"仅展示前 20 行；完整内容见 `02-按角色任务包.csv`，共 {len(role_rows)} 行。")
    lines.append("")
    (output_dir / WORKBENCH_FILES["overview"]).write_text("\n".join(lines), encoding="utf-8")


def write_priority_batches(output_dir: Path, gate_matrix: list[dict[str, Any]], role_rows: list[dict[str, Any]]) -> None:
    ranked_gates = sorted(
        gate_matrix,
        key=lambda row: (-int_value(row.get("blocking_task_rows")), row.get("gate_id", "")),
    )
    lines = [
        "# QMS 治理关闭优先批次",
        "",
        "本文件用于安排人工关闭顺序，不代表任何项目已通过。所有关闭动作都必须回填证据、意见、复核人和日期。",
        "",
        "## 建议顺序",
        "",
    ]
    for index, gate in enumerate(ranked_gates, start=1):
        if int_value(gate.get("human_task_rows")) == 0:
            continue
        lines.append(
            f"{index}. {gate.get('gate_id')} {gate.get('gate_group')}："
            f"{gate.get('blocking_task_rows')} 条阻断任务，负责人：{gate.get('owner_role')}。"
        )
        lines.append(f"   - 证据重点：{gate.get('evidence_needed')}")
        lines.append(f"   - 下一动作：{gate.get('next_action')}")
    lines.extend(["", "## 角色批次提示", ""])
    top_roles = sorted(role_rows, key=lambda row: (-int_value(row.get("blocking_count")), row.get("role_batch_id", "")))[:20]
    lines.extend(render_table(top_roles, ["role_batch_id", "owner_role", "gate_id", "task_group", "task_count", "blocking_count"]))
    lines.extend(["", "## 边界", ""])
    lines.extend(f"- {item}" for item in GUARDRAILS)
    lines.append("")
    (output_dir / WORKBENCH_FILES["priority_batches"]).write_text("\n".join(lines), encoding="utf-8")


def write_readme(output_dir: Path, manifest: dict[str, Any]) -> None:
    lines = [
        "# governance_closure_workbench",
        "",
        "用途：把治理就绪总览中的人工任务转成可人工关闭、可证据回填、可由 LIMS 命令层识别的工作台。",
        "",
        "## 文件",
        "",
    ]
    for key, filename in WORKBENCH_FILES.items():
        lines.append(f"- `{filename}`：{key}")
    lines.extend(
        [
            "",
            "## 使用边界",
            "",
        ]
    )
    lines.extend(f"- {item}" for item in GUARDRAILS)
    lines.extend(
        [
            "",
            "## 初始状态",
            "",
            f"- closure_rows: {manifest['counts']['closure_rows']}",
            f"- open_blocking_items: {manifest['counts']['open_blocking_items']}",
            "- 任何真实关闭都需要人工填写，不能由本脚本自动确认。",
            "",
        ]
    )
    (output_dir / WORKBENCH_FILES["readme"]).write_text("\n".join(lines), encoding="utf-8")


def build_workbench(dashboard_dir: Path, output_dir: Path) -> dict[str, Any]:
    manifest_path = dashboard_dir / DASHBOARD_FILES["manifest"]
    if not manifest_path.exists():
        raise FileNotFoundError(f"缺少治理就绪总览 manifest：{manifest_path}")
    source_manifest = json.loads(manifest_path.read_text(encoding="utf-8"))
    if source_manifest.get("status") != "governance_readiness_no_database_write":
        raise ValueError("治理就绪总览 manifest 状态不符合预期。")

    gate_rows = read_csv(dashboard_dir / DASHBOARD_FILES["gate_register"])
    task_rows = read_csv(dashboard_dir / DASHBOARD_FILES["human_task_register"])
    output_dir.mkdir(parents=True, exist_ok=True)

    gate_matrix = build_gate_matrix(gate_rows, task_rows)
    role_rows = build_role_pack(task_rows)
    evidence_rows = build_evidence_rows(task_rows)
    closure_rows = build_closure_rows(task_rows)

    write_csv(output_dir / WORKBENCH_FILES["gate_closure_matrix"], gate_matrix, GATE_FIELDS)
    write_csv(output_dir / WORKBENCH_FILES["role_task_pack"], role_rows, ROLE_FIELDS)
    write_csv(output_dir / WORKBENCH_FILES["evidence_template"], evidence_rows, EVIDENCE_FIELDS)
    write_csv(output_dir / WORKBENCH_FILES["closure_template"], closure_rows, CLOSURE_FIELDS)

    counts = {
        "gate_rows": len(gate_matrix),
        "role_task_batches": len(role_rows),
        "evidence_rows": len(evidence_rows),
        "closure_rows": len(closure_rows),
        "blocking_closure_items": sum(1 for row in closure_rows if row.get("blocks_apply") == "yes"),
        "open_blocking_items": sum(1 for row in closure_rows if row.get("blocks_apply") == "yes"),
        "accepted_closures": 0,
        "pending_closures": len(closure_rows),
        "database_write_performed": 0,
    }
    by_gate = Counter(row.get("gate_id", "") for row in closure_rows)
    blocking_by_gate = Counter(row.get("gate_id", "") for row in closure_rows if row.get("blocks_apply") == "yes")
    by_role = Counter(row.get("owner_role", "") for row in closure_rows)

    manifest = {
        "generated_at": dt.datetime.now().isoformat(timespec="seconds"),
        "status": "governance_closure_workbench_no_database_write",
        "source_dashboard_dir": str(dashboard_dir),
        "readiness": "blocked_by_open_closures" if counts["open_blocking_items"] else "ready_for_governance_readiness_refresh",
        "ready_for_governance_readiness_refresh": "no" if counts["open_blocking_items"] else "yes",
        "ready_for_lims_apply": "no",
        "files": WORKBENCH_FILES,
        "counts": counts,
        "source_counts": source_manifest.get("counts", {}),
        "closure_summary": {
            "by_gate": dict(sorted(by_gate.items())),
            "blocking_by_gate": dict(sorted(blocking_by_gate.items())),
            "by_owner_role": dict(sorted(by_role.items())),
        },
        "allowed_closure_statuses": ["closed", "not_applicable", "waived", "pending", "rejected"],
        "guardrails": GUARDRAILS,
    }
    (output_dir / WORKBENCH_FILES["manifest"]).write_text(
        json.dumps(manifest, ensure_ascii=False, indent=2) + "\n",
        encoding="utf-8",
    )
    write_overview(output_dir, manifest, gate_matrix, role_rows)
    write_priority_batches(output_dir, gate_matrix, role_rows)
    write_readme(output_dir, manifest)
    return manifest


def render_report(manifest: dict[str, Any]) -> str:
    lines = [
        "# QMS 治理关闭工作台生成报告",
        "",
        f"生成时间：{manifest['generated_at']}",
        f"来源总览：`{manifest['source_dashboard_dir']}`",
        f"结论：{manifest['status']}",
        f"readiness：{manifest['readiness']}",
        f"ready_for_governance_readiness_refresh：{manifest['ready_for_governance_readiness_refresh']}",
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
        ]
    )
    lines.extend(f"- {item}" for item in GUARDRAILS)
    lines.append("")
    return "\n".join(lines)


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--dashboard-dir", required=True)
    parser.add_argument("--output-dir", required=True)
    parser.add_argument("--json-out")
    parser.add_argument("--md-out")
    args = parser.parse_args()

    manifest = build_workbench(Path(args.dashboard_dir), Path(args.output_dir))
    report = {
        "generated_at": dt.datetime.now().isoformat(timespec="seconds"),
        "status": "passed",
        "output_dir": args.output_dir,
        "counts": manifest["counts"],
        "readiness": manifest["readiness"],
        "ready_for_governance_readiness_refresh": manifest["ready_for_governance_readiness_refresh"],
        "ready_for_lims_apply": manifest["ready_for_lims_apply"],
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
