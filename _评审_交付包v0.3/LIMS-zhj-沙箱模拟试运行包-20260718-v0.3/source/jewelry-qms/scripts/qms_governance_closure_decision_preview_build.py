#!/usr/bin/env python3
"""Build a no-write preview for governance closure decisions."""

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
    "evidence_template": "03-证据采集模板.csv",
    "closure_template": "04-拟关闭回填模板.csv",
}

PREVIEW_FILES = {
    "manifest": "governance_closure_decision_preview_manifest.json",
    "overview": "00-治理关闭意见回填预览总览.md",
    "decision_preview": "01-拟关闭决策预览.csv",
    "blocking_items": "02-仍阻断关闭项.csv",
    "gate_summary": "03-按闸门关闭统计.csv",
    "readme": "README.md",
}

STATUS_ALIASES = {
    "": "pending",
    "pending": "pending",
    "open": "pending",
    "待确认": "pending",
    "待关闭": "pending",
    "closed": "closed",
    "close": "closed",
    "done": "closed",
    "resolved": "closed",
    "已关闭": "closed",
    "完成": "closed",
    "通过": "closed",
    "not_applicable": "not_applicable",
    "not-applicable": "not_applicable",
    "na": "not_applicable",
    "n/a": "not_applicable",
    "不适用": "not_applicable",
    "waived": "waived",
    "waive": "waived",
    "豁免": "waived",
    "rejected": "rejected",
    "reject": "rejected",
    "reopen": "rejected",
    "退回": "rejected",
}

TERMINAL_STATUSES = {"closed", "not_applicable", "waived"}

GUARDRAILS = [
    "本预览包只读取 governance_closure_workbench 中的证据采集和拟关闭回填意见，不写数据库。",
    "本预览包不修改 governance_closure_workbench、governance_readiness_dashboard、人工评审包、第二阶段复核工作台或任何现用 Word 文件。",
    "本预览包不代表人工评审通过，不代表真实培训完成，不代表受控发布，不代表正式写库授权。",
    "所有空白、pending、rejected、缺证据、缺意见、缺复核人或缺日期的阻断项保持阻断。",
    "资质状态口径：已取得 CMA，CNAS 申请中；不得表述为已取得 CNAS。",
    "以 LIMS 当前导出的 2022 程序清单作为现行程序目录。",
    "jewelry-qms 仍为建设中系统，只进入实施计划、演练和治理准备材料，不写入质量手册正文。",
]

PREVIEW_FIELDS = [
    "closure_item_id",
    "source_task_id",
    "gate_id",
    "task_group",
    "object_code",
    "object_name",
    "owner_role",
    "source_current_status",
    "proposed_closure_status",
    "normalized_closure_status",
    "closure_evidence_reference",
    "evidence_template_reference",
    "evidence_owner",
    "evidence_date",
    "evidence_result",
    "closure_comment",
    "reviewer",
    "review_date",
    "preview_result",
    "will_remain_blocking",
    "issue",
    "required_evidence",
    "not_imported",
]

BLOCKING_FIELDS = [
    "closure_item_id",
    "source_task_id",
    "gate_id",
    "task_group",
    "object_code",
    "object_name",
    "owner_role",
    "preview_result",
    "issue",
    "required_evidence",
    "not_imported",
]

SUMMARY_FIELDS = [
    "gate_id",
    "task_group",
    "decision_rows",
    "proposed_closures",
    "not_proposed",
    "accepted_for_preview",
    "invalid_closures",
    "missing_required_fields",
    "blocking_items",
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


def normalize_status(value: str) -> str:
    raw = value.strip()
    key = raw.lower().replace(" ", "_")
    return STATUS_ALIASES.get(key) or STATUS_ALIASES.get(raw) or key


def is_blocking(row: dict[str, str]) -> bool:
    return row.get("blocks_apply", "").strip().lower() == "yes"


def join_issue(parts: list[str]) -> str:
    return "; ".join(part for part in parts if part)


def validate_row(row: dict[str, str], evidence: dict[str, str] | None) -> dict[str, Any]:
    evidence = evidence or {}
    proposed = row.get("proposed_closure_status", "").strip()
    normalized = normalize_status(proposed)
    blocks_apply = is_blocking(row)
    closure_reference = row.get("evidence_reference", "").strip()
    evidence_reference = evidence.get("evidence_reference", "").strip()
    result = "not_proposed"
    issue_parts = ["待人工填写拟关闭意见"]
    remains_blocking = blocks_apply

    if normalized not in {"pending", "closed", "not_applicable", "waived", "rejected"}:
        result = "invalid_closure_status"
        issue_parts = ["拟关闭状态不在允许范围内"]
        remains_blocking = blocks_apply
    elif normalized == "rejected":
        result = "rejected_or_reopened"
        issue_parts = ["关闭意见为 rejected/reopen，仍保持阻断"]
        remains_blocking = blocks_apply
    elif normalized != "pending":
        missing_closure = [
            field
            for field in ["evidence_reference", "closure_comment", "reviewer", "review_date"]
            if not row.get(field, "").strip()
        ]
        missing_evidence = [
            field
            for field in ["evidence_reference", "evidence_owner", "evidence_date", "evidence_result"]
            if not evidence.get(field, "").strip()
        ]
        issue_parts = []
        if missing_closure:
            issue_parts.append("拟关闭模板缺少字段：" + "、".join(missing_closure))
        if missing_evidence:
            issue_parts.append("证据采集模板缺少字段：" + "、".join(missing_evidence))
        if closure_reference and evidence_reference and closure_reference != evidence_reference:
            issue_parts.append("拟关闭证据引用与证据采集模板不一致")
        if issue_parts:
            result = "missing_required_fields"
            remains_blocking = blocks_apply
        else:
            result = "accepted_for_preview"
            remains_blocking = False

    return {
        "closure_item_id": row.get("closure_item_id", "").strip(),
        "source_task_id": row.get("source_task_id", "").strip(),
        "gate_id": row.get("gate_id", "").strip(),
        "task_group": row.get("task_group", "").strip(),
        "object_code": row.get("object_code", "").strip(),
        "object_name": row.get("object_name", "").strip(),
        "owner_role": row.get("owner_role", "").strip(),
        "source_current_status": row.get("source_current_status", "").strip(),
        "proposed_closure_status": proposed,
        "normalized_closure_status": normalized,
        "closure_evidence_reference": closure_reference,
        "evidence_template_reference": evidence_reference,
        "evidence_owner": evidence.get("evidence_owner", "").strip(),
        "evidence_date": evidence.get("evidence_date", "").strip(),
        "evidence_result": evidence.get("evidence_result", "").strip(),
        "closure_comment": row.get("closure_comment", "").strip(),
        "reviewer": row.get("reviewer", "").strip(),
        "review_date": row.get("review_date", "").strip(),
        "preview_result": result,
        "will_remain_blocking": "yes" if remains_blocking else "no",
        "issue": join_issue(issue_parts),
        "required_evidence": row.get("closure_comment", "").strip() or evidence.get("evidence_to_check", "").strip(),
        "not_imported": "yes",
    }


def summarize(rows: list[dict[str, Any]]) -> list[dict[str, Any]]:
    grouped: dict[tuple[str, str], list[dict[str, Any]]] = defaultdict(list)
    for row in rows:
        grouped[(str(row.get("gate_id", "")), str(row.get("task_group", "")))].append(row)

    summary_rows: list[dict[str, Any]] = []
    for (gate_id, task_group), items in sorted(grouped.items()):
        counts = Counter(str(item.get("preview_result", "")) for item in items)
        summary_rows.append(
            {
                "gate_id": gate_id,
                "task_group": task_group,
                "decision_rows": len(items),
                "proposed_closures": sum(1 for item in items if item.get("proposed_closure_status")),
                "not_proposed": counts.get("not_proposed", 0),
                "accepted_for_preview": counts.get("accepted_for_preview", 0),
                "invalid_closures": counts.get("invalid_closure_status", 0) + counts.get("rejected_or_reopened", 0),
                "missing_required_fields": counts.get("missing_required_fields", 0),
                "blocking_items": sum(1 for item in items if item.get("will_remain_blocking") == "yes"),
            }
        )
    return summary_rows


def render_table(rows: list[dict[str, Any]], columns: list[str]) -> list[str]:
    lines = ["| " + " | ".join(columns) + " |", "|" + "|".join(["---"] * len(columns)) + "|"]
    for row in rows:
        values = [str(row.get(column, "")).replace("\n", " ").replace("|", "／") for column in columns]
        lines.append("| " + " | ".join(values) + " |")
    return lines


def write_overview(output_dir: Path, manifest: dict[str, Any], summary_rows: list[dict[str, Any]]) -> None:
    counts = manifest["counts"]
    lines = [
        "# QMS 治理关闭意见回填预览总览",
        "",
        f"生成时间：{manifest['generated_at']}",
        f"来源工作台：`{manifest['source_workbench_dir']}`",
        "",
        "## 结论",
        "",
        f"- readiness: {manifest['readiness']}",
        f"- ready_for_governance_readiness_refresh: {manifest['ready_for_governance_readiness_refresh']}",
        f"- decision_items: {counts['decision_items']}",
        f"- accepted_for_preview: {counts['accepted_for_preview']}",
        f"- blocking_items: {counts['blocking_items']}",
        "",
        "该预览包只检查拟关闭意见和证据字段是否足以进入后续复核，不修改源工作台，不写数据库，不代表人工评审通过、真实培训完成、受控发布或正式写库授权。",
        "",
        "## 边界",
        "",
    ]
    lines.extend(f"- {item}" for item in GUARDRAILS)
    lines.extend(["", "## 按闸门统计", ""])
    lines.extend(render_table(summary_rows, ["gate_id", "task_group", "decision_rows", "accepted_for_preview", "blocking_items"]))
    lines.append("")
    (output_dir / PREVIEW_FILES["overview"]).write_text("\n".join(lines), encoding="utf-8")


def write_readme(output_dir: Path, manifest: dict[str, Any]) -> None:
    lines = [
        "# governance_closure_decision_preview",
        "",
        "用途：读取治理关闭工作台中的拟关闭意见和证据采集字段，生成不写库的关闭回填预览。",
        "",
        "## 文件",
        "",
    ]
    for key, filename in PREVIEW_FILES.items():
        lines.append(f"- `{filename}`：{key}")
    lines.extend(["", "## 使用边界", ""])
    lines.extend(f"- {item}" for item in GUARDRAILS)
    lines.extend(
        [
            "",
            "## 初始状态",
            "",
            f"- decision_items: {manifest['counts']['decision_items']}",
            f"- blocking_items: {manifest['counts']['blocking_items']}",
            "- 任何真实关闭都需要人工填写，不能由本脚本自动确认。",
            "",
        ]
    )
    (output_dir / PREVIEW_FILES["readme"]).write_text("\n".join(lines), encoding="utf-8")


def build_preview(workbench_dir: Path, output_dir: Path) -> dict[str, Any]:
    manifest_path = workbench_dir / WORKBENCH_FILES["manifest"]
    if not manifest_path.exists():
        raise FileNotFoundError(f"缺少治理关闭工作台 manifest：{manifest_path}")
    workbench_manifest = json.loads(manifest_path.read_text(encoding="utf-8"))
    if workbench_manifest.get("status") != "governance_closure_workbench_no_database_write":
        raise ValueError("治理关闭工作台 manifest 状态不符合预期。")

    evidence_rows = read_csv(workbench_dir / WORKBENCH_FILES["evidence_template"])
    closure_rows = read_csv(workbench_dir / WORKBENCH_FILES["closure_template"])
    evidence_by_id = {row.get("closure_item_id", "").strip(): row for row in evidence_rows}
    output_dir.mkdir(parents=True, exist_ok=True)

    preview_rows = [validate_row(row, evidence_by_id.get(row.get("closure_item_id", "").strip())) for row in closure_rows]
    blocking_rows = [row for row in preview_rows if row.get("will_remain_blocking") == "yes"]
    summary_rows = summarize(preview_rows)

    write_csv(output_dir / PREVIEW_FILES["decision_preview"], preview_rows, PREVIEW_FIELDS)
    write_csv(output_dir / PREVIEW_FILES["blocking_items"], blocking_rows, BLOCKING_FIELDS)
    write_csv(output_dir / PREVIEW_FILES["gate_summary"], summary_rows, SUMMARY_FIELDS)

    counts = {
        "decision_items": len(preview_rows),
        "proposed_closures": sum(1 for row in preview_rows if row.get("proposed_closure_status")),
        "not_proposed": sum(1 for row in preview_rows if row.get("preview_result") == "not_proposed"),
        "accepted_for_preview": sum(1 for row in preview_rows if row.get("preview_result") == "accepted_for_preview"),
        "invalid_closures": sum(1 for row in preview_rows if row.get("preview_result") in {"invalid_closure_status", "rejected_or_reopened"}),
        "missing_required_fields": sum(1 for row in preview_rows if row.get("preview_result") == "missing_required_fields"),
        "blocking_items": len(blocking_rows),
        "database_write_performed": 0,
    }
    manifest = {
        "generated_at": dt.datetime.now().isoformat(timespec="seconds"),
        "status": "governance_closure_decision_preview_no_database_write",
        "source_workbench_dir": str(workbench_dir),
        "readiness": "ready_for_governance_readiness_refresh" if counts["blocking_items"] == 0 else "blocked_by_open_closures",
        "ready_for_governance_readiness_refresh": "yes" if counts["blocking_items"] == 0 else "no",
        "ready_for_lims_apply": "no",
        "files": PREVIEW_FILES,
        "counts": counts,
        "source_counts": workbench_manifest.get("counts", {}),
        "allowed_closure_statuses": ["closed", "not_applicable", "waived", "pending", "rejected"],
        "guardrails": GUARDRAILS,
    }
    (output_dir / PREVIEW_FILES["manifest"]).write_text(
        json.dumps(manifest, ensure_ascii=False, indent=2) + "\n",
        encoding="utf-8",
    )
    write_overview(output_dir, manifest, summary_rows)
    write_readme(output_dir, manifest)
    return manifest


def render_report(manifest: dict[str, Any]) -> str:
    lines = [
        "# QMS 治理关闭意见回填预览生成报告",
        "",
        f"生成时间：{manifest['generated_at']}",
        f"来源工作台：`{manifest['source_workbench_dir']}`",
        f"结论：{manifest['status']}",
        f"readiness：{manifest['readiness']}",
        f"ready_for_governance_readiness_refresh：{manifest['ready_for_governance_readiness_refresh']}",
        "",
        "## 计数",
        "",
    ]
    for key, value in manifest["counts"].items():
        lines.append(f"- {key}: {value}")
    lines.extend(["", "## 边界", ""])
    lines.extend(f"- {item}" for item in GUARDRAILS)
    lines.append("")
    return "\n".join(lines)


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--workbench-dir", required=True)
    parser.add_argument("--output-dir", required=True)
    parser.add_argument("--json-out")
    parser.add_argument("--md-out")
    args = parser.parse_args()

    manifest = build_preview(Path(args.workbench_dir), Path(args.output_dir))
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
