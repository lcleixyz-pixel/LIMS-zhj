#!/usr/bin/env python3
"""Build a no-write decision preview for the stage2 structured review workbench."""

from __future__ import annotations

import argparse
import csv
import datetime as dt
import json
from collections import Counter, defaultdict
from pathlib import Path
from typing import Any


WORKBENCH_FILES = {
    "manifest": "stage2_review_workbench_manifest.json",
    "decision_template": "05-人工复核意见回填模板.csv",
}

PREVIEW_FILES = {
    "manifest": "stage2_review_decision_preview_manifest.json",
    "overview": "00-第二阶段结构化复核意见回填预览总览.md",
    "decision_preview": "01-拟回填决策预览.csv",
    "blocking_items": "02-仍阻断项清单.csv",
    "scope_summary": "03-按范围统计.csv",
    "readme": "README.md",
}

TERMINAL_DECISIONS = {"approved", "revise", "remove"}
KNOWN_ALIASES = {
    "approved": "approved",
    "approve": "approved",
    "accepted": "approved",
    "accept": "approved",
    "pass": "approved",
    "passed": "approved",
    "yes": "approved",
    "同意": "approved",
    "通过": "approved",
    "批准": "approved",
    "确认通过": "approved",
    "revise": "revise",
    "revision": "revise",
    "needs_revision": "revise",
    "need_revision": "revise",
    "修改": "revise",
    "修订": "revise",
    "需修订": "revise",
    "需要修订": "revise",
    "退回修改": "revise",
    "remove": "remove",
    "removed": "remove",
    "delete": "remove",
    "作废": "remove",
    "删除": "remove",
    "移除": "remove",
    "不导入": "remove",
    "pending": "pending",
    "待定": "pending",
    "待确认": "pending",
}

GUARDRAILS = [
    "本预览包只校验 stage2_structured_review_workbench/05-人工复核意见回填模板.csv 的拟回填意见，不写数据库。",
    "本预览包不修改 stage2_structured_review_workbench/ 中的任何文件或人工决策。",
    "本预览包不代表第二阶段已导入，不代表人工评审通过、受控发布或正式写库授权。",
    "所有空白或 pending 决策保持阻断，真实意见需人工填写、复核并另行授权。",
    "资质状态口径：已取得 CMA，CNAS 申请中；不得表述为已取得 CNAS。",
    "jewelry-qms 仍为建设中系统，只进入实施计划、演练和治理准备材料，不写入质量手册正文。",
]

PREVIEW_FIELDS = [
    "decision_item_id",
    "scope",
    "target_key",
    "section_number",
    "target_type",
    "target_code",
    "allowed_decisions",
    "proposed_human_decision",
    "normalized_decision",
    "review_comment",
    "preview_result",
    "will_remain_blocking",
    "issue",
    "required_evidence",
    "not_imported",
]

BLOCKING_FIELDS = [
    "decision_item_id",
    "scope",
    "target_key",
    "section_number",
    "target_type",
    "target_code",
    "preview_result",
    "issue",
    "required_evidence",
    "not_imported",
]

SUMMARY_FIELDS = [
    "scope",
    "target_type",
    "decision_rows",
    "proposed_decisions",
    "not_proposed",
    "pending_decisions",
    "accepted_for_preview",
    "invalid_decisions",
    "missing_review_comments",
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


def normalize_decision(value: str) -> str:
    raw = value.strip()
    if not raw:
        return ""
    key = raw.lower().replace(" ", "_")
    return KNOWN_ALIASES.get(key) or KNOWN_ALIASES.get(raw) or raw


def allowed_values(row: dict[str, str]) -> set[str]:
    values = {item.strip() for item in row.get("allowed_decisions", "").split("|") if item.strip()}
    return values or {"approved", "revise", "remove", "pending"}


def is_blocking(row: dict[str, str]) -> bool:
    return row.get("blocking_if_unresolved", "").strip().lower() in {"yes", "true", "1", "是"}


def validate_row(row: dict[str, str]) -> dict[str, Any]:
    proposed = row.get("proposed_human_decision", "").strip()
    comment = row.get("review_comment", "").strip()
    normalized = normalize_decision(proposed)
    allowed = allowed_values(row)
    preview_result = "not_proposed"
    issue = "待人工填写拟决策"
    remains_blocking = True

    if proposed:
        if normalized not in allowed:
            preview_result = "invalid_decision"
            issue = "拟决策不在 allowed_decisions 范围内"
            remains_blocking = True
        elif normalized == "pending":
            preview_result = "pending"
            issue = "拟决策仍为 pending，保持阻断"
            remains_blocking = is_blocking(row)
        elif normalized in TERMINAL_DECISIONS and not comment:
            preview_result = "missing_review_comment"
            issue = "approved/revise/remove 均需填写 review_comment 说明依据或处置意见"
            remains_blocking = True
        elif normalized in TERMINAL_DECISIONS:
            preview_result = "accepted_for_preview"
            issue = ""
            remains_blocking = False
        else:
            preview_result = "invalid_decision"
            issue = "拟决策未被当前预览规则识别"
            remains_blocking = True

    return {
        "decision_item_id": row.get("decision_item_id", "").strip(),
        "scope": row.get("scope", "").strip(),
        "target_key": row.get("target_key", "").strip(),
        "section_number": row.get("section_number", "").strip(),
        "target_type": row.get("target_type", "").strip(),
        "target_code": row.get("target_code", "").strip(),
        "allowed_decisions": row.get("allowed_decisions", "").strip(),
        "proposed_human_decision": proposed,
        "normalized_decision": normalized,
        "review_comment": comment,
        "preview_result": preview_result,
        "will_remain_blocking": "yes" if remains_blocking else "no",
        "issue": issue,
        "required_evidence": row.get("required_evidence", "").strip(),
        "not_imported": row.get("not_imported", "").strip(),
    }


def render_table(rows: list[dict[str, Any]], columns: list[str]) -> list[str]:
    lines = ["| " + " | ".join(columns) + " |", "|" + "|".join(["---"] * len(columns)) + "|"]
    for row in rows:
        values = [str(row.get(column, "")).replace("\n", " ").replace("|", "／") for column in columns]
        lines.append("| " + " | ".join(values) + " |")
    return lines


def summarize_rows(rows: list[dict[str, Any]]) -> list[dict[str, Any]]:
    grouped: dict[tuple[str, str], list[dict[str, Any]]] = defaultdict(list)
    for row in rows:
        grouped[(str(row.get("scope", "")), str(row.get("target_type", "")))].append(row)

    summary_rows: list[dict[str, Any]] = []
    for (scope, target_type), items in sorted(grouped.items()):
        status_counts = Counter(str(item.get("preview_result", "")) for item in items)
        summary_rows.append(
            {
                "scope": scope,
                "target_type": target_type,
                "decision_rows": len(items),
                "proposed_decisions": sum(1 for item in items if item.get("proposed_human_decision")),
                "not_proposed": status_counts.get("not_proposed", 0),
                "pending_decisions": status_counts.get("pending", 0),
                "accepted_for_preview": status_counts.get("accepted_for_preview", 0),
                "invalid_decisions": status_counts.get("invalid_decision", 0),
                "missing_review_comments": status_counts.get("missing_review_comment", 0),
                "blocking_items": sum(1 for item in items if item.get("will_remain_blocking") == "yes"),
            }
        )
    return summary_rows


def readiness_from_counts(counts: dict[str, int]) -> str:
    if counts["invalid_decisions"] > 0:
        return "invalid_decisions"
    if counts["missing_review_comments"] > 0:
        return "missing_review_comments"
    if counts["proposed_decisions"] == 0:
        return "no_proposed_decisions"
    if counts["blocking_items"] > 0:
        return "blocked_by_unresolved_decisions"
    return "ready_for_controlled_update_after_authorization"


def render_overview(manifest: dict[str, Any]) -> str:
    counts = manifest["counts"]
    lines = [
        "# 第二阶段结构化复核意见回填预览总览",
        "",
        "本文件用于检查第二阶段人工复核意见将来能否安全回填；不写数据库，不修改 `stage2_structured_review_workbench/`，不代表人工评审通过。",
        "",
        "## 结论",
        "",
        f"- 预览状态：{manifest['readiness']}",
        f"- 决策项总数：{counts['decision_rows']}",
        f"- 已填写拟决策：{counts['proposed_decisions']}",
        f"- 未填写拟决策：{counts['not_proposed']}",
        f"- pending 拟决策：{counts['pending_decisions']}",
        f"- 可进入回填预览的拟决策：{counts['accepted_for_preview']}",
        f"- 非法拟决策：{counts['invalid_decisions']}",
        f"- 缺少 review_comment：{counts['missing_review_comments']}",
        f"- 预览后仍阻断项：{counts['blocking_items']}",
        f"- 数据库写入：{counts['database_write_performed']}",
        "",
        "## 边界",
        "",
    ]
    lines.extend(f"- {item}" for item in manifest["guardrails"])
    lines.extend(
        [
            "",
            "## 本预览包文件",
            "",
        ]
    )
    for key, filename in manifest["files"].items():
        lines.append(f"- `{filename}`：{key}")
    lines.append("")
    return "\n".join(lines)


def render_readme(manifest: dict[str, Any]) -> str:
    lines = [
        "# 第二阶段结构化复核意见回填预览包",
        "",
        "用途：在人工填写 `05-人工复核意见回填模板.csv` 后，先做只读预览，判断哪些意见可进入后续受控回填路径。",
        "",
        "## 当前结论",
        "",
        f"- 预览状态：{manifest['readiness']}",
        f"- 决策项：{manifest['counts']['decision_rows']}",
        f"- 已填写拟决策：{manifest['counts']['proposed_decisions']}",
        f"- 仍阻断项：{manifest['counts']['blocking_items']}",
        "",
        "## 使用边界",
        "",
    ]
    lines.extend(f"- {item}" for item in manifest["guardrails"])
    lines.extend(
        [
            "- 明确不修改 `stage2_structured_review_workbench/`，仅从该工作台读取人工拟决策。",
            "",
            "",
            "## 文件说明",
            "",
            "- `00-第二阶段结构化复核意见回填预览总览.md`：管理层和总控先看的结论。",
            "- `01-拟回填决策预览.csv`：逐行预览每条人工决策的可接受状态。",
            "- `02-仍阻断项清单.csv`：预览后仍不能进入后续回填的行。",
            "- `03-按范围统计.csv`：按 block/link 与目标类型统计。",
            "- `stage2_review_decision_preview_manifest.json`：机器可读清单。",
            "",
            "下一步不是直接导入，而是由人工在工作台中填写拟决策和依据，再重新生成本预览包。",
            "",
        ]
    )
    return "\n".join(lines)


def build_preview(workbench_dir: Path, output_dir: Path) -> dict[str, Any]:
    workbench_manifest_path = workbench_dir / WORKBENCH_FILES["manifest"]
    decision_template_path = workbench_dir / WORKBENCH_FILES["decision_template"]
    workbench_manifest = json.loads(workbench_manifest_path.read_text(encoding="utf-8"))
    decision_rows = read_csv(decision_template_path)
    preview_rows = [validate_row(row) for row in decision_rows]
    blocking_rows = [row for row in preview_rows if row.get("will_remain_blocking") == "yes"]
    summary_rows = summarize_rows(preview_rows)
    result_counts = Counter(str(row.get("preview_result", "")) for row in preview_rows)

    counts = {
        "decision_rows": len(preview_rows),
        "proposed_decisions": sum(1 for row in preview_rows if row.get("proposed_human_decision")),
        "not_proposed": result_counts.get("not_proposed", 0),
        "pending_decisions": result_counts.get("pending", 0),
        "accepted_for_preview": result_counts.get("accepted_for_preview", 0),
        "invalid_decisions": result_counts.get("invalid_decision", 0),
        "missing_review_comments": result_counts.get("missing_review_comment", 0),
        "blocking_items": len(blocking_rows),
        "database_write_performed": 0,
    }
    generated_at = dt.datetime.now().isoformat(timespec="seconds")
    manifest = {
        "generated_at": generated_at,
        "status": "stage2_review_decision_preview_no_database_write",
        "readiness": readiness_from_counts(counts),
        "source_workbench_dir": str(workbench_dir),
        "source_workbench_status": workbench_manifest.get("status", ""),
        "preview_dir": str(output_dir),
        "guardrails": GUARDRAILS,
        "files": PREVIEW_FILES,
        "counts": counts,
        "workbench_counts": workbench_manifest.get("counts", {}),
    }

    output_dir.mkdir(parents=True, exist_ok=True)
    write_csv(output_dir / PREVIEW_FILES["decision_preview"], preview_rows, PREVIEW_FIELDS)
    write_csv(output_dir / PREVIEW_FILES["blocking_items"], blocking_rows, BLOCKING_FIELDS)
    write_csv(output_dir / PREVIEW_FILES["scope_summary"], summary_rows, SUMMARY_FIELDS)
    (output_dir / PREVIEW_FILES["overview"]).write_text(render_overview(manifest), encoding="utf-8")
    (output_dir / PREVIEW_FILES["readme"]).write_text(render_readme(manifest), encoding="utf-8")
    (output_dir / PREVIEW_FILES["manifest"]).write_text(
        json.dumps(manifest, ensure_ascii=False, indent=2) + "\n",
        encoding="utf-8",
    )
    return manifest


def render_markdown(manifest: dict[str, Any]) -> str:
    lines = [
        "# 第二阶段结构化复核意见回填预览生成报告",
        "",
        f"生成时间：{manifest['generated_at']}",
        f"复核工作台：`{manifest['source_workbench_dir']}`",
        f"预览目录：`{manifest['preview_dir']}`",
        f"结论：{manifest['readiness']}",
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
    lines.extend(f"- {item}" for item in manifest["guardrails"])
    lines.extend(
        [
            "",
            "## 说明",
            "",
            "当前结论只表示二阶段复核意见的回填预览状态；不代表第二阶段已导入、人工评审通过或已获得正式写库授权。",
            "",
        ]
    )
    return "\n".join(lines)


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--workbench-dir", required=True)
    parser.add_argument("--output-dir", required=True)
    parser.add_argument("--json-out")
    parser.add_argument("--md-out")
    args = parser.parse_args()

    manifest = build_preview(Path(args.workbench_dir), Path(args.output_dir))
    print(json.dumps(manifest, ensure_ascii=False, indent=2))
    if args.json_out:
        Path(args.json_out).write_text(json.dumps(manifest, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
    if args.md_out:
        Path(args.md_out).write_text(render_markdown(manifest), encoding="utf-8")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
