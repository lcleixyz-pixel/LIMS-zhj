#!/usr/bin/env python3
"""Build a no-write preview for human-review decision updates."""

from __future__ import annotations

import argparse
import csv
import datetime as dt
import json
from collections import Counter, defaultdict
from pathlib import Path
from typing import Any


DEFAULT_STAGE_DIR = Path(
    "/Users/lc.leixyz/Documents/AI工作台/01-项目代码/LIMS-zhj/.team/交接箱/2026-07-07-第五版候选修订准备"
)

BOUNDARY = [
    "本预览包只校验 decision_update_template.csv 的拟回填意见，不写数据库。",
    "本预览包不修改 human_review_pack/ 中的任何 human_decision。",
    "本预览包不能作为 qms:preimport-package --review-dir 使用。",
    "本预览包不代表第五版候选稿、记录模板或 LIMS 预导入包已经审核批准。",
    "正式回填仍需要人工确认、受控修订记录和用户明确授权。",
]

GENERIC_DECISIONS = {
    "approved": "approved",
    "accepted": "approved",
    "pass": "approved",
    "passed": "approved",
    "yes": "approved",
    "同意": "approved",
    "通过": "approved",
    "批准": "approved",
    "确认通过": "approved",
    "needs_revision": "needs_revision",
    "need_revision": "needs_revision",
    "revision": "needs_revision",
    "需修订": "needs_revision",
    "需要修订": "needs_revision",
    "退回修改": "needs_revision",
    "rejected": "rejected",
    "reject": "rejected",
    "不通过": "rejected",
    "拒绝": "rejected",
    "deferred": "deferred",
    "defer": "deferred",
    "暂缓": "deferred",
    "延期": "deferred",
    "pending": "pending",
    "待定": "pending",
}

ATTACHMENT_DISPOSITIONS = {
    "程序附件": "程序附件",
    "记录模板": "记录模板",
    "历史附件保留": "历史附件保留",
    "作废不导入": "作废不导入",
    "待补录为受控文件": "待补录为受控文件",
}

VALIDATION_FIELDS = [
    "review_item_id",
    "review_type",
    "display_name",
    "source_file",
    "current_decision",
    "proposed_decision_raw",
    "normalized_decision",
    "preview_status",
    "requires_comment",
    "comment_present",
    "blocking_after_preview",
    "would_satisfy_lims_approved_decision",
    "issue_severity",
    "issue_message",
    "review_comment",
]

OVERLAY_FIELDS = [
    "not_for_import",
    "review_item_id",
    "review_type",
    "source_file",
    "current_decision",
    "proposed_decision_raw",
    "normalized_decision",
    "review_comment",
    "preview_status",
    "blocking_after_preview",
]


def read_text(path: Path) -> str:
    return path.read_text(encoding="utf-8")


def read_csv(path: Path) -> list[dict[str, str]]:
    with path.open("r", encoding="utf-8", newline="") as handle:
        return list(csv.DictReader(handle))


def write_csv(path: Path, rows: list[dict[str, Any]], fieldnames: list[str]) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    with path.open("w", encoding="utf-8", newline="") as handle:
        writer = csv.DictWriter(handle, fieldnames=fieldnames, extrasaction="ignore")
        writer.writeheader()
        for row in rows:
            writer.writerow({field: row.get(field, "") for field in fieldnames})


def normalize_generic(value: str) -> str | None:
    key = value.strip()
    if not key:
        return None
    return GENERIC_DECISIONS.get(key.lower().replace(" ", "_")) or GENERIC_DECISIONS.get(key)


def normalize_attachment(value: str) -> str | None:
    return ATTACHMENT_DISPOSITIONS.get(value.strip())


def is_yes(value: str) -> bool:
    return value.strip().lower() in {"yes", "true", "1", "是"}


def validate_decision_row(row: dict[str, str]) -> dict[str, Any]:
    review_type = row.get("review_type", "").strip()
    proposed = row.get("new_human_decision", "").strip()
    comment = row.get("review_comment", "").strip()
    current = row.get("current_decision", "").strip() or "pending"
    blocking_if_unresolved = is_yes(row.get("blocking_if_unresolved", ""))

    result: dict[str, Any] = {
        "review_item_id": row.get("review_item_id", "").strip(),
        "review_type": review_type,
        "display_name": row.get("display_name", "").strip(),
        "source_file": row.get("source_file", "").strip(),
        "current_decision": current,
        "proposed_decision_raw": proposed,
        "normalized_decision": "",
        "preview_status": "no_proposed_change",
        "requires_comment": "no",
        "comment_present": "yes" if comment else "no",
        "blocking_after_preview": "yes" if blocking_if_unresolved and current == "pending" else "no",
        "would_satisfy_lims_approved_decision": "no",
        "issue_severity": "",
        "issue_message": "",
        "review_comment": comment,
    }

    if proposed == "":
        return result

    result["requires_comment"] = "yes"
    if review_type == "attachment_form_disposition":
        disposition = normalize_attachment(proposed)
        generic = normalize_generic(proposed)
        if disposition:
            result["normalized_decision"] = disposition
            result["preview_status"] = "proposed_attachment_disposition_requires_schema_decision"
            result["blocking_after_preview"] = "yes"
            result["issue_severity"] = "medium"
            result["issue_message"] = (
                "05-02 的处置结论需要单独确认如何映射到 LIMS 人工评审包；"
                "当前命令层只把 approved/pass/accepted/通过/批准等视为通过状态。"
            )
            if not comment:
                result["preview_status"] = "missing_comment"
                result["issue_severity"] = "high"
                result["issue_message"] = "05-02 处置结论必须写明依据、字段/保存要求和与 JL-6.4-01/JL-6.5-01 的关系。"
            return result
        if generic == "approved":
            result["normalized_decision"] = generic
            result["preview_status"] = "invalid_decision"
            result["blocking_after_preview"] = "yes"
            result["issue_severity"] = "high"
            result["issue_message"] = "05-02 不能只填 approved；必须先给出程序附件/记录模板等处置结论。"
            return result
        if generic in {"deferred", "pending", "needs_revision", "rejected"}:
            result["normalized_decision"] = generic
            result["preview_status"] = "proposed_unapproved"
            result["blocking_after_preview"] = "yes"
            if not comment and generic != "pending":
                result["preview_status"] = "missing_comment"
                result["issue_severity"] = "high"
                result["issue_message"] = "非通过或暂缓类决策必须填写 review_comment。"
            return result
        result["preview_status"] = "invalid_decision"
        result["blocking_after_preview"] = "yes"
        result["issue_severity"] = "high"
        result["issue_message"] = "new_human_decision 不属于 05-02 归属判定允许选项。"
        return result

    generic = normalize_generic(proposed)
    if generic is None:
        result["preview_status"] = "invalid_decision"
        result["blocking_after_preview"] = "yes"
        result["issue_severity"] = "high"
        result["issue_message"] = "new_human_decision 不属于通用人工评审允许选项。"
        return result

    result["normalized_decision"] = generic
    if generic == "pending":
        result["preview_status"] = "no_proposed_change"
        result["blocking_after_preview"] = "yes" if blocking_if_unresolved else "no"
        result["requires_comment"] = "no"
        return result

    if not comment:
        result["preview_status"] = "missing_comment"
        result["blocking_after_preview"] = "yes"
        result["issue_severity"] = "high"
        result["issue_message"] = "所有拟回填的人工决策都必须填写 review_comment，说明审查依据或修订意见。"
        return result

    if generic == "approved":
        result["preview_status"] = "proposed_approved"
        result["blocking_after_preview"] = "no"
        result["would_satisfy_lims_approved_decision"] = "yes"
        return result

    result["preview_status"] = "proposed_unapproved"
    result["blocking_after_preview"] = "yes"
    return result


def render_table(rows: list[dict[str, Any]], columns: list[str]) -> list[str]:
    lines = ["| " + " | ".join(columns) + " |", "|" + "|".join(["---"] * len(columns)) + "|"]
    for row in rows:
        values = [str(row.get(column, "")).replace("\n", " ").replace("|", "／") for column in columns]
        lines.append("| " + " | ".join(values) + " |")
    return lines


def source_summary_rows(validation_rows: list[dict[str, Any]]) -> list[dict[str, Any]]:
    grouped: dict[str, list[dict[str, Any]]] = defaultdict(list)
    for row in validation_rows:
        grouped[str(row.get("source_file", ""))].append(row)
    result = []
    for source_file, rows in sorted(grouped.items()):
        result.append(
            {
                "source_file": source_file,
                "items": len(rows),
                "proposed_changes": sum(1 for item in rows if item.get("proposed_decision_raw")),
                "approved_candidates": sum(1 for item in rows if item.get("would_satisfy_lims_approved_decision") == "yes"),
                "blocking_after_preview": sum(1 for item in rows if item.get("blocking_after_preview") == "yes"),
                "high_issues": sum(1 for item in rows if item.get("issue_severity") == "high"),
            }
        )
    return result


def readiness_from_counts(counts: dict[str, int]) -> str:
    if counts["high_issues"] > 0:
        return "invalid_decision_updates"
    if counts["proposed_changes"] == 0:
        return "no_proposed_decisions"
    if counts["blocking_after_preview"] > 0:
        return "blocked_by_unresolved_or_schema_decisions"
    return "ready_for_controlled_review_pack_update_after_authorization"


def render_overview(manifest: dict[str, Any]) -> str:
    counts = manifest["counts"]
    lines = [
        "# LIMS 人工评审决策回填预览总览",
        "",
        "本文件用于检查人工评审意见将来能否安全回填；不写数据库，不修改 `human_review_pack/`，不能作为 `--review-dir`。",
        "",
        "## 结论",
        "",
        f"- 预览状态：{manifest['readiness']}",
        f"- 决策项总数：{counts['decision_items_total']}",
        f"- 拟回填项：{counts['proposed_changes']}",
        f"- 可满足 LIMS 通过语义的候选项：{counts['approved_candidates']}",
        f"- 预览后仍阻断项：{counts['blocking_after_preview']}",
        f"- 高风险填写问题：{counts['high_issues']}",
        f"- 05-02 处置语义待确认项：{counts['attachment_schema_decisions']}",
        "",
        "## 边界",
        "",
    ]
    lines.extend(f"- {item}" for item in manifest["boundary"])
    lines.extend(
        [
            "",
            "## 05-02 特别提示",
            "",
            "`XZTC/CX-05-02-2022` 的人工输入不是普通 approved/pass，而是“程序附件、记录模板、历史附件保留、作废不导入、待补录为受控文件”等处置结论。"
            "预览包会提示该处置结论还需要决定如何映射到正式评审包，避免命令层继续把它视为未通过。",
            "",
            "## 本预览包文件",
            "",
        ]
    )
    for label, filename in manifest["files"].items():
        lines.append(f"- `{filename}`：{label}")
    lines.append("")
    return "\n".join(lines)


def render_status_preview(validation_rows: list[dict[str, Any]]) -> str:
    lines = [
        "# 待处理与异常预览",
        "",
        "本文件只列出需要人工关注的回填状态；不写数据库，不修改 `human_review_pack/`，不能作为 `--review-dir`。",
        "",
    ]
    focus_rows = [
        row
        for row in validation_rows
        if row.get("preview_status") not in {"no_proposed_change", "proposed_approved"}
        or row.get("issue_severity")
    ]
    if not focus_rows:
        lines.append("未发现需关注项。")
    else:
        lines.extend(
            render_table(
                focus_rows[:120],
                [
                    "review_item_id",
                    "review_type",
                    "proposed_decision_raw",
                    "preview_status",
                    "blocking_after_preview",
                    "issue_severity",
                    "issue_message",
                ],
            )
        )
        if len(focus_rows) > 120:
            lines.append("")
            lines.append(f"仅显示前 120 项；实际需关注项 {len(focus_rows)}。")
    lines.append("")
    return "\n".join(lines)


def render_source_impact(source_rows: list[dict[str, Any]]) -> str:
    lines = [
        "# 源文件影响预览",
        "",
        "本文件按来源清单汇总拟回填影响；不写数据库，不修改 `human_review_pack/`，不能作为 `--review-dir`。",
        "",
    ]
    lines.extend(
        render_table(
            source_rows,
            [
                "source_file",
                "items",
                "proposed_changes",
                "approved_candidates",
                "blocking_after_preview",
                "high_issues",
            ],
        )
    )
    lines.append("")
    return "\n".join(lines)


def build_preview(stage_dir: Path, decision_csv: Path, output_dir: Path) -> dict[str, Any]:
    decision_rows = read_csv(decision_csv)
    validation_rows = [validate_decision_row(row) for row in decision_rows]
    status_counts = Counter(str(row.get("preview_status", "")) for row in validation_rows)
    proposed_rows = [row for row in validation_rows if row.get("proposed_decision_raw")]
    source_rows = source_summary_rows(validation_rows)

    counts = {
        "decision_items_total": len(validation_rows),
        "proposed_changes": len(proposed_rows),
        "no_proposed_change": status_counts.get("no_proposed_change", 0),
        "proposed_approved": status_counts.get("proposed_approved", 0),
        "proposed_unapproved": status_counts.get("proposed_unapproved", 0),
        "missing_comment": status_counts.get("missing_comment", 0),
        "invalid_decision": status_counts.get("invalid_decision", 0),
        "attachment_schema_decisions": status_counts.get("proposed_attachment_disposition_requires_schema_decision", 0),
        "approved_candidates": sum(1 for row in validation_rows if row.get("would_satisfy_lims_approved_decision") == "yes"),
        "blocking_after_preview": sum(1 for row in validation_rows if row.get("blocking_after_preview") == "yes"),
        "high_issues": sum(1 for row in validation_rows if row.get("issue_severity") == "high"),
        "medium_issues": sum(1 for row in validation_rows if row.get("issue_severity") == "medium"),
    }
    readiness = readiness_from_counts(counts)

    output_dir.mkdir(parents=True, exist_ok=True)
    files = {
        "overview": "01-决策回填预览总览.md",
        "status_preview": "02-待处理与异常预览.md",
        "source_impact": "03-源文件影响预览.md",
        "validation_csv": "decision_update_validation.csv",
        "overlay_preview": "review_pack_overlay_preview_not_for_import.csv",
        "manifest": "decision_preview_manifest.json",
        "readme": "README.md",
    }

    overlay_rows = []
    for row in proposed_rows:
        overlay = dict(row)
        overlay["not_for_import"] = "not_for_import"
        overlay_rows.append(overlay)

    manifest: dict[str, Any] = {
        "generated_at": dt.datetime.now().isoformat(timespec="seconds"),
        "status": "decision_preview_no_database_write",
        "readiness": readiness,
        "stage_dir": str(stage_dir),
        "decision_csv": str(decision_csv),
        "output_dir": str(output_dir),
        "boundary": BOUNDARY,
        "counts": counts,
        "status_counts": dict(status_counts),
        "files": files,
    }

    write_csv(output_dir / files["validation_csv"], validation_rows, VALIDATION_FIELDS)
    write_csv(output_dir / files["overlay_preview"], overlay_rows, OVERLAY_FIELDS)
    (output_dir / files["manifest"]).write_text(json.dumps(manifest, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
    (output_dir / files["overview"]).write_text(render_overview(manifest), encoding="utf-8")
    (output_dir / files["status_preview"]).write_text(render_status_preview(validation_rows), encoding="utf-8")
    (output_dir / files["source_impact"]).write_text(render_source_impact(source_rows), encoding="utf-8")
    (output_dir / files["readme"]).write_text(render_overview(manifest), encoding="utf-8")
    return manifest


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--stage-dir", default=str(DEFAULT_STAGE_DIR))
    parser.add_argument("--decision-csv")
    parser.add_argument("--output-dir")
    args = parser.parse_args()

    stage_dir = Path(args.stage_dir)
    decision_csv = Path(args.decision_csv) if args.decision_csv else stage_dir / "human_review_workbench" / "decision_update_template.csv"
    output_dir = Path(args.output_dir) if args.output_dir else stage_dir / "human_review_decision_preview"
    manifest = build_preview(stage_dir, decision_csv, output_dir)
    print(json.dumps(manifest, ensure_ascii=False, indent=2))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
