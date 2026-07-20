#!/usr/bin/env python3
"""Build a reader-friendly QMS human-review workbench from the review pack."""

from __future__ import annotations

import argparse
import csv
import datetime as dt
import json
import re
from collections import defaultdict
from pathlib import Path
from typing import Any


DEFAULT_STAGE_DIR = Path(
    "/Users/lc.leixyz/Documents/AI工作台/01-项目代码/LIMS-zhj/.team/交接箱/2026-07-07-第五版候选修订准备"
)

ALLOWED_DECISIONS = "approved/pass/accepted/通过/批准；needs_revision；rejected；deferred"
NO_DATABASE_WRITE = "本工作台只用于人工评审准备，不写数据库，不代表审核批准。"


def read_text(path: Path) -> str:
    return path.read_text(encoding="utf-8")


def read_json(path: Path) -> dict[str, Any]:
    return json.loads(read_text(path))


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


def split_roles(value: str) -> list[str]:
    roles = [part.strip() for part in re.split(r"[/／、,，]", value or "") if part.strip()]
    return roles or ["待分配"]


def stable_anchor(text: str) -> str:
    return re.sub(r"[^0-9A-Za-z\u4e00-\u9fff._-]+", "-", text).strip("-")


def md_link(label: str, target: str) -> str:
    return f"[{label}]({target})"


def rel(path: Path, base: Path) -> str:
    return str(path.relative_to(base))


def trial_file_by_code(trial_manifest: dict[str, Any]) -> dict[str, str]:
    result: dict[str, str] = {}
    for filename in trial_manifest.get("files", {}).get("markdown_files", []):
        code = filename.split("-", 3)
        if len(code) >= 3 and filename.startswith("JL-"):
            result["-".join(code[:3])] = filename
    return result


def decision_rows(
    clause_rows: list[dict[str, str]],
    template_rows: list[dict[str, str]],
    attachment_rows: list[dict[str, str]],
    gate_rows: list[dict[str, str]],
) -> list[dict[str, Any]]:
    rows: list[dict[str, Any]] = []
    for row in clause_rows:
        rows.append(
            {
                "review_item_id": row.get("review_item_id", ""),
                "review_type": "manual_clause",
                "display_name": f"{row.get('clause', '')} {row.get('manual_topic', '')}".strip(),
                "reviewer_role": row.get("reviewer_role", ""),
                "current_decision": row.get("human_decision", ""),
                "allowed_decisions": ALLOWED_DECISIONS,
                "new_human_decision": "",
                "review_comment": "",
                "evidence_to_check": row.get("required_evidence", ""),
                "blocking_if_unresolved": row.get("blocking_if_unresolved", ""),
                "source_file": "human_review_pack/manual_clause_review_checklist.csv",
            }
        )
    for row in template_rows:
        rows.append(
            {
                "review_item_id": row.get("review_item_id", ""),
                "review_type": "record_template",
                "display_name": f"{row.get('doc_number', '')} {row.get('name', '')}".strip(),
                "reviewer_role": row.get("reviewer_role", ""),
                "current_decision": row.get("human_decision", ""),
                "allowed_decisions": ALLOWED_DECISIONS,
                "new_human_decision": "",
                "review_comment": "",
                "evidence_to_check": "核对候选字段、全量试填表、保存期限、保密等级和签核规则。",
                "blocking_if_unresolved": row.get("blocking_if_unresolved", ""),
                "source_file": "human_review_pack/record_template_review_checklist.csv",
            }
        )
    for row in attachment_rows:
        rows.append(
            {
                "review_item_id": row.get("review_item_id", ""),
                "review_type": "attachment_form_disposition",
                "display_name": f"{row.get('doc_number', '')} {row.get('title', '')}".strip(),
                "reviewer_role": row.get("reviewer_role", ""),
                "current_decision": row.get("human_decision", ""),
                "allowed_decisions": "程序附件；记录模板；历史附件保留；作废不导入；待补录为受控文件；deferred",
                "new_human_decision": "",
                "review_comment": "",
                "evidence_to_check": "确认 05-02 归属、字段、保存要求和与 JL-6.4-01/JL-6.5-01 的关系。",
                "blocking_if_unresolved": row.get("blocking_if_unresolved", ""),
                "source_file": "human_review_pack/attachment_form_disposition.csv",
            }
        )
    for row in gate_rows:
        rows.append(
            {
                "review_item_id": row.get("gate_id", ""),
                "review_type": "preapply_gate",
                "display_name": row.get("gate_name", ""),
                "reviewer_role": row.get("reviewer_role", ""),
                "current_decision": row.get("human_decision", ""),
                "allowed_decisions": ALLOWED_DECISIONS,
                "new_human_decision": "",
                "review_comment": "",
                "evidence_to_check": row.get("current_evidence", ""),
                "blocking_if_unresolved": row.get("blocking_if_unresolved", ""),
                "source_file": "human_review_pack/preapply_gate_register.csv",
            }
        )
    return rows


def render_table(rows: list[dict[str, Any]], columns: list[str]) -> list[str]:
    lines = ["| " + " | ".join(columns) + " |", "|" + "|".join(["---"] * len(columns)) + "|"]
    for row in rows:
        values = [str(row.get(column, "")).replace("\n", " ").replace("|", "／") for column in columns]
        lines.append("| " + " | ".join(values) + " |")
    return lines


def render_overview(manifest: dict[str, Any]) -> str:
    counts = manifest["counts"]
    lines = [
        "# 人工评审工作台总览",
        "",
        NO_DATABASE_WRITE,
        "",
        "## 当前状态",
        "",
        f"- 总决策项：{counts['decision_items_total']}",
        f"- 条款评审：{counts['manual_clause_items']}",
        f"- 记录模板评审：{counts['record_template_items']}",
        f"- 05-02 归属判定：{counts['attachment_disposition_items']}",
        f"- apply 前闸门：{counts['preapply_gate_items']}",
        f"- 当前 pending：{counts['pending_decisions']}",
        f"- 可分配角色数：{counts['reviewer_roles']}",
        "",
        "## 建议评审节奏",
        "",
        "1. 先由文件管理员核对候选手册、修订说明、程序目录和文件控制边界。",
        "2. 再由质量负责人/最高管理者核对 4.1、4.2、5、8 章相关条款和职责。",
        "3. 再由技术负责人/过程负责人核对 6、7 章条款、记录模板和试填样表。",
        "4. 单独确认 `XZTC/CX-05-02-2022` 的归属。",
        "5. 全部决策回填到 `human_review_pack/` 后，再运行 LIMS dry-run 和 apply 闸门验证。",
        "",
        "## 本工作台文件",
        "",
    ]
    for label, filename in manifest["files"].items():
        lines.append(f"- `{filename}`：{label}")
    lines.append("")
    return "\n".join(lines)


def render_role_checklist(rows: list[dict[str, Any]]) -> str:
    grouped: dict[str, list[dict[str, Any]]] = defaultdict(list)
    for row in rows:
        for role in split_roles(str(row.get("reviewer_role", ""))):
            grouped[role].append(row)

    lines = ["# 按角色评审清单", "", NO_DATABASE_WRITE, ""]
    for role in sorted(grouped):
        items = grouped[role]
        lines.extend([f"## {role}", "", f"- 待看决策项：{len(items)}", ""])
        preview = [
            {
                "review_item_id": item["review_item_id"],
                "review_type": item["review_type"],
                "display_name": item["display_name"],
                "evidence_to_check": item["evidence_to_check"],
            }
            for item in items
        ]
        lines.extend(render_table(preview, ["review_item_id", "review_type", "display_name", "evidence_to_check"]))
        lines.append("")
    return "\n".join(lines)


def render_clause_workbench(clause_rows: list[dict[str, str]], stage_dir: Path) -> str:
    rows = []
    for row in clause_rows:
        rows.append(
            {
                "review_item_id": row.get("review_item_id", ""),
                "clause": row.get("clause", ""),
                "manual_topic": row.get("manual_topic", ""),
                "candidate_manual": md_link("候选手册", rel(stage_dir / "10-质量手册第五版候选稿.md", stage_dir)),
                "revision_note": md_link("修订说明", rel(stage_dir / "11-第四版到第五版候选修订说明.md", stage_dir)),
                "record_templates": row.get("record_template_numbers", ""),
                "reviewer_role": row.get("reviewer_role", ""),
                "human_decision": row.get("human_decision", ""),
            }
        )
    lines = ["# 条款评审工作台", "", NO_DATABASE_WRITE, ""]
    lines.extend(render_table(rows, ["review_item_id", "clause", "manual_topic", "candidate_manual", "revision_note", "record_templates", "reviewer_role", "human_decision"]))
    lines.append("")
    return "\n".join(lines)


def render_template_workbench(
    template_rows: list[dict[str, str]],
    stage_dir: Path,
    trial_manifest: dict[str, Any],
) -> str:
    trial_map = trial_file_by_code(trial_manifest)
    rows = []
    for row in template_rows:
        code = row.get("doc_number", "")
        trial_file = trial_map.get(code, "")
        rows.append(
            {
                "review_item_id": row.get("review_item_id", ""),
                "doc_number": code,
                "name": row.get("name", ""),
                "field_count": row.get("field_count", ""),
                "trial_form": md_link("全量试填", rel(stage_dir / "record_template_full_trial_pack" / trial_file, stage_dir)) if trial_file else "missing",
                "needs_retention_period": row.get("needs_retention_period", ""),
                "needs_confidentiality_level": row.get("needs_confidentiality_level", ""),
                "human_decision": row.get("human_decision", ""),
            }
        )
    lines = ["# 记录模板评审工作台", "", NO_DATABASE_WRITE, ""]
    lines.extend(
        render_table(
            rows,
            [
                "review_item_id",
                "doc_number",
                "name",
                "field_count",
                "trial_form",
                "needs_retention_period",
                "needs_confidentiality_level",
                "human_decision",
            ],
        )
    )
    lines.append("")
    return "\n".join(lines)


def render_attachment_workbench(attachment_rows: list[dict[str, str]]) -> str:
    lines = [
        "# 05-02 归属判定工作台",
        "",
        NO_DATABASE_WRITE,
        "",
        "## 判定原则",
        "",
        "- 不因编号像程序就自动归为程序文件。",
        "- 需确认它在现用文件中的实际用途、保存期限、填写责任和与设备/计量溯源记录的关系。",
        "- 未确认前不得写入 LIMS，也不得作为已发布模板。",
        "",
    ]
    lines.extend(
        render_table(
            attachment_rows,
            [
                "review_item_id",
                "doc_number",
                "title",
                "related_clauses",
                "related_record_templates",
                "disposition_options",
                "human_decision",
            ],
        )
    )
    lines.append("")
    return "\n".join(lines)


def render_gate_workbench(gate_rows: list[dict[str, str]]) -> str:
    lines = ["# apply 前闸门工作台", "", NO_DATABASE_WRITE, ""]
    lines.extend(render_table(gate_rows, ["gate_id", "gate_name", "gate_type", "current_evidence", "human_decision", "required_before_apply"]))
    lines.append("")
    return "\n".join(lines)


def build_workbench(
    stage_dir: Path,
    review_dir: Path,
    preimport_dir: Path,
    trial_dir: Path,
    output_dir: Path,
) -> dict[str, Any]:
    manifest = read_json(review_dir / "human_review_manifest.json")
    files = manifest.get("files", {})
    clause_rows = read_csv(review_dir / files.get("manual_clause_review", "manual_clause_review_checklist.csv"))
    template_rows = read_csv(review_dir / files.get("record_template_review", "record_template_review_checklist.csv"))
    attachment_rows = read_csv(review_dir / files.get("attachment_disposition", "attachment_form_disposition.csv"))
    gate_rows = read_csv(review_dir / files.get("preapply_gate_register", "preapply_gate_register.csv"))
    trial_manifest = read_json(trial_dir / "trial_manifest.json")

    decisions = decision_rows(clause_rows, template_rows, attachment_rows, gate_rows)
    role_names = sorted({role for row in decisions for role in split_roles(str(row.get("reviewer_role", "")))})

    output_dir.mkdir(parents=True, exist_ok=True)
    workbench_files = {
        "overview": "00-人工评审总览.md",
        "role_checklist": "01-按角色评审清单.md",
        "clause_workbench": "02-条款评审工作台.md",
        "template_workbench": "03-记录模板评审工作台.md",
        "attachment_workbench": "04-05-02归属判定工作台.md",
        "gate_workbench": "05-apply前闸门工作台.md",
        "decision_template": "decision_update_template.csv",
        "manifest": "workbench_manifest.json",
    }

    generated_at = dt.datetime.now().isoformat(timespec="seconds")
    workbench_manifest = {
        "generated_at": generated_at,
        "status": "human_review_workbench_pending_no_database_write",
        "stage_dir": str(stage_dir),
        "review_dir": str(review_dir),
        "preimport_dir": str(preimport_dir),
        "trial_dir": str(trial_dir),
        "workbench_dir": str(output_dir),
        "boundary": [
            "本工作台为人工评审辅助材料，不写数据库。",
            "本工作台不代表第五版候选稿、记录模板或 LIMS 预导入包已经审核批准。",
            "本工作台不修改 human_review_pack 中的 human_decision。",
            "decision_update_template.csv 的 current_decision 保持 pending，new_human_decision 初始为空，不能视为批准。",
            "正式 apply 仍必须以 human_review_pack 全部通过和用户明确授权为准。",
        ],
        "counts": {
            "decision_items_total": len(decisions),
            "manual_clause_items": len(clause_rows),
            "record_template_items": len(template_rows),
            "attachment_disposition_items": len(attachment_rows),
            "preapply_gate_items": len(gate_rows),
            "pending_decisions": sum(1 for row in decisions if row.get("current_decision") == "pending"),
            "reviewer_roles": len(role_names),
        },
        "reviewer_roles": role_names,
        "files": workbench_files,
    }

    write_csv(
        output_dir / workbench_files["decision_template"],
        decisions,
        [
            "review_item_id",
            "review_type",
            "display_name",
            "reviewer_role",
            "current_decision",
            "allowed_decisions",
            "new_human_decision",
            "review_comment",
            "evidence_to_check",
            "blocking_if_unresolved",
            "source_file",
        ],
    )
    (output_dir / workbench_files["manifest"]).write_text(
        json.dumps(workbench_manifest, ensure_ascii=False, indent=2) + "\n",
        encoding="utf-8",
    )
    (output_dir / workbench_files["overview"]).write_text(render_overview(workbench_manifest), encoding="utf-8")
    (output_dir / workbench_files["role_checklist"]).write_text(render_role_checklist(decisions), encoding="utf-8")
    (output_dir / workbench_files["clause_workbench"]).write_text(render_clause_workbench(clause_rows, stage_dir), encoding="utf-8")
    (output_dir / workbench_files["template_workbench"]).write_text(
        render_template_workbench(template_rows, stage_dir, trial_manifest),
        encoding="utf-8",
    )
    (output_dir / workbench_files["attachment_workbench"]).write_text(
        render_attachment_workbench(attachment_rows),
        encoding="utf-8",
    )
    (output_dir / workbench_files["gate_workbench"]).write_text(render_gate_workbench(gate_rows), encoding="utf-8")
    (output_dir / "README.md").write_text(render_overview(workbench_manifest), encoding="utf-8")
    return workbench_manifest


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--stage-dir", default=str(DEFAULT_STAGE_DIR))
    parser.add_argument("--review-dir")
    parser.add_argument("--preimport-dir")
    parser.add_argument("--trial-dir")
    parser.add_argument("--output-dir")
    args = parser.parse_args()

    stage_dir = Path(args.stage_dir)
    review_dir = Path(args.review_dir) if args.review_dir else stage_dir / "human_review_pack"
    preimport_dir = Path(args.preimport_dir) if args.preimport_dir else stage_dir / "lims_preimport_package"
    trial_dir = Path(args.trial_dir) if args.trial_dir else stage_dir / "record_template_full_trial_pack"
    output_dir = Path(args.output_dir) if args.output_dir else stage_dir / "human_review_workbench"

    manifest = build_workbench(stage_dir, review_dir, preimport_dir, trial_dir, output_dir)
    print(json.dumps(manifest, ensure_ascii=False, indent=2))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
