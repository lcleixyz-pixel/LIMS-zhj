#!/usr/bin/env python3
"""Build a human review workbench from the LIMS stage2 write preview package."""

from __future__ import annotations

import argparse
import csv
import datetime as dt
import json
from collections import Counter, defaultdict
from pathlib import Path
from typing import Any


PREVIEW_FILES = {
    "manifest": "stage2_preview_manifest.json",
    "structured": "01-structured-documents-preview.csv",
    "blocks": "02-document-blocks-preview.csv",
    "links": "03-document-block-links-preview.csv",
}

WORKBENCH_FILES = {
    "manifest": "stage2_review_workbench_manifest.json",
    "overview": "00-第二阶段结构化导入人工复核总览.md",
    "block_review_matrix": "01-手册块复核矩阵.csv",
    "link_review_matrix": "02-块级链接复核矩阵.csv",
    "clause_target_summary": "03-按条款目标统计.csv",
    "target_backreference": "04-目标文件记录反查清单.csv",
    "decision_template": "05-人工复核意见回填模板.csv",
    "readme": "README.md",
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


def block_key_from_resolution(value: str) -> str:
    return value.split(" -> ", 1)[0].strip()


def review_focus(target_type: str) -> str:
    if target_type == "procedure_document":
        return "确认该程序确实支撑本手册条款，且仍为 LIMS 当前 2022 现行程序。"
    if target_type == "attachment_form_document":
        return "确认该编号附件/表单的归属、保存方式和后续是否纳入记录模板。"
    if target_type == "record_form_template":
        return "确认候选记录模板字段、触发时机、责任岗位和保存要求适用于本条款。"
    return "确认链接目标和关系类型是否正确。"


def build_workbench(preview_dir: Path, output_dir: Path) -> dict[str, Any]:
    preview_manifest = json.loads((preview_dir / PREVIEW_FILES["manifest"]).read_text(encoding="utf-8"))
    structured_rows = read_csv(preview_dir / PREVIEW_FILES["structured"])
    block_rows = read_csv(preview_dir / PREVIEW_FILES["blocks"])
    link_rows = read_csv(preview_dir / PREVIEW_FILES["links"])

    output_dir.mkdir(parents=True, exist_ok=True)
    generated_at = dt.datetime.now().isoformat(timespec="seconds")

    block_by_key = {row.get("stable_key", ""): row for row in block_rows}
    links_by_block: dict[str, list[dict[str, str]]] = defaultdict(list)
    for row in link_rows:
        links_by_block[block_key_from_resolution(row.get("block_id_resolution", ""))].append(row)

    block_review_rows: list[dict[str, Any]] = []
    clause_summary_rows: list[dict[str, Any]] = []
    decision_rows: list[dict[str, Any]] = []
    for index, block in enumerate(block_rows, start=1):
        stable_key = block.get("stable_key", "")
        linked = links_by_block.get(stable_key, [])
        counts = Counter(row.get("target_type", "") for row in linked)
        total_links = len(linked)
        review_id = f"STG2-BLOCK-{index:03d}"
        block_review_rows.append(
            {
                "review_item_id": review_id,
                "stable_key": stable_key,
                "section_number": block.get("section_number", ""),
                "title": block.get("title", ""),
                "block_type": block.get("block_type", ""),
                "procedure_document_links": counts.get("procedure_document", 0),
                "attachment_form_document_links": counts.get("attachment_form_document", 0),
                "record_form_template_links": counts.get("record_form_template", 0),
                "total_links": total_links,
                "link_confidence": block.get("link_confidence", ""),
                "phase_dependency": block.get("phase_dependency", ""),
                "human_decision": "pending",
                "reviewer_role": "质量负责人；文件管理员；相关过程负责人",
                "review_comment": "",
                "blocking_if_unresolved": "yes",
                "not_imported": "yes",
            }
        )
        clause_summary_rows.append(
            {
                "section_number": block.get("section_number", ""),
                "title": block.get("title", ""),
                "stable_key": stable_key,
                "procedure_document_links": counts.get("procedure_document", 0),
                "attachment_form_document_links": counts.get("attachment_form_document", 0),
                "record_form_template_links": counts.get("record_form_template", 0),
                "total_links": total_links,
                "unresolved_targets": sum(1 for row in linked if row.get("target_id_resolution") in {"", "not_resolved_before_apply"}),
                "review_focus": "确认本条款与程序、附件/表单、记录模板之间的证据链是否符合组织真实运行。",
                "human_decision": "pending",
                "blocking_if_unresolved": "yes",
            }
        )
        decision_rows.append(
            {
                "decision_item_id": review_id,
                "scope": "block",
                "target_key": stable_key,
                "section_number": block.get("section_number", ""),
                "target_type": "manual_block",
                "target_code": stable_key,
                "allowed_decisions": "approved|revise|remove|pending",
                "proposed_human_decision": "",
                "review_comment": "",
                "required_evidence": "确认条款块标题、类型、排序、来源和后续生效条件。",
                "blocking_if_unresolved": "yes",
                "not_imported": "yes",
            }
        )

    link_review_rows: list[dict[str, Any]] = []
    target_map: dict[tuple[str, str], dict[str, Any]] = {}
    for index, link in enumerate(link_rows, start=1):
        stable_key = block_key_from_resolution(link.get("block_id_resolution", ""))
        block = block_by_key.get(stable_key, {})
        review_id = f"STG2-LINK-{index:03d}"
        target_type = link.get("target_type", "")
        target_code = link.get("target_code", "")
        target_key = (target_type, target_code)
        target = target_map.setdefault(
            target_key,
            {
                "target_type": target_type,
                "target_code": target_code,
                "target_id_resolution": link.get("target_id_resolution", ""),
                "linked_block_count": 0,
                "linked_sections": [],
                "linked_stable_keys": [],
                "requires_confirmation": "yes",
                "human_decision": "pending",
                "review_comment": "",
                "blocking_if_unresolved": "yes",
                "not_imported": "yes",
            },
        )
        target["linked_block_count"] += 1
        if block.get("section_number", "") not in target["linked_sections"]:
            target["linked_sections"].append(block.get("section_number", ""))
        if stable_key not in target["linked_stable_keys"]:
            target["linked_stable_keys"].append(stable_key)
        link_review_rows.append(
            {
                "review_item_id": review_id,
                "stable_key": stable_key,
                "section_number": block.get("section_number", ""),
                "block_title": block.get("title", ""),
                "target_type": target_type,
                "target_code": target_code,
                "target_id_resolution": link.get("target_id_resolution", ""),
                "relation_type": link.get("relation_type", ""),
                "confidence": link.get("confidence", ""),
                "source_column": link.get("source_column", ""),
                "review_focus": review_focus(target_type),
                "human_decision": "pending",
                "reviewer_role": "质量负责人；文件管理员；相关过程负责人",
                "review_comment": "",
                "blocking_if_unresolved": "yes",
                "not_imported": "yes",
            }
        )
        decision_rows.append(
            {
                "decision_item_id": review_id,
                "scope": "link",
                "target_key": stable_key + "->" + target_type + ":" + target_code,
                "section_number": block.get("section_number", ""),
                "target_type": target_type,
                "target_code": target_code,
                "allowed_decisions": "approved|revise|remove|pending",
                "proposed_human_decision": "",
                "review_comment": "",
                "required_evidence": review_focus(target_type),
                "blocking_if_unresolved": "yes",
                "not_imported": "yes",
            }
        )

    target_backreference_rows = []
    for target in sorted(target_map.values(), key=lambda item: (item["target_type"], item["target_code"])):
        target_backreference_rows.append(
            {
                **target,
                "linked_sections": "；".join(target["linked_sections"]),
                "linked_stable_keys": "；".join(target["linked_stable_keys"]),
            }
        )

    files = WORKBENCH_FILES.copy()
    counts = {
        "structured_documents_preview_rows": len(structured_rows),
        "block_review_rows": len(block_review_rows),
        "link_review_rows": len(link_review_rows),
        "clause_summary_rows": len(clause_summary_rows),
        "target_backreference_rows": len(target_backreference_rows),
        "decision_rows": len(decision_rows),
        "pending_decisions": len(decision_rows),
        "procedure_document_links": sum(1 for row in link_review_rows if row["target_type"] == "procedure_document"),
        "attachment_form_document_links": sum(1 for row in link_review_rows if row["target_type"] == "attachment_form_document"),
        "record_form_template_links": sum(1 for row in link_review_rows if row["target_type"] == "record_form_template"),
        "unresolved_targets": sum(1 for row in link_review_rows if row["target_id_resolution"] in {"", "not_resolved_before_apply"}),
        "database_write_performed": 0,
    }
    manifest = {
        "generated_at": generated_at,
        "status": "stage2_structured_review_workbench_no_database_write",
        "source_preview_dir": str(preview_dir),
        "source_preview_status": preview_manifest.get("status", ""),
        "workbench_dir": str(output_dir),
        "guardrails": [
            "本工作台只用于第二阶段结构化导入人工复核准备，不写数据库。",
            "本工作台不代表第二阶段已导入，不代表人工评审通过、受控发布或正式写库授权。",
            "所有人工决策保持 pending/空白，真实意见需人工填写并另行复核。",
            "第二阶段必须先完成人工评审、第一阶段文件/模板写入、质量手册修订/换版路径确认和人员学习实施确认。",
            "资质状态口径：已取得 CMA，CNAS 申请中；不得表述为已取得 CNAS。",
            "jewelry-qms 仍为建设中系统，只进入实施计划、演练和治理准备材料，不写入质量手册正文。",
        ],
        "counts": counts,
        "files": files,
    }

    write_csv(
        output_dir / files["block_review_matrix"],
        block_review_rows,
        [
            "review_item_id",
            "stable_key",
            "section_number",
            "title",
            "block_type",
            "procedure_document_links",
            "attachment_form_document_links",
            "record_form_template_links",
            "total_links",
            "link_confidence",
            "phase_dependency",
            "human_decision",
            "reviewer_role",
            "review_comment",
            "blocking_if_unresolved",
            "not_imported",
        ],
    )
    write_csv(
        output_dir / files["link_review_matrix"],
        link_review_rows,
        [
            "review_item_id",
            "stable_key",
            "section_number",
            "block_title",
            "target_type",
            "target_code",
            "target_id_resolution",
            "relation_type",
            "confidence",
            "source_column",
            "review_focus",
            "human_decision",
            "reviewer_role",
            "review_comment",
            "blocking_if_unresolved",
            "not_imported",
        ],
    )
    write_csv(
        output_dir / files["clause_target_summary"],
        clause_summary_rows,
        [
            "section_number",
            "title",
            "stable_key",
            "procedure_document_links",
            "attachment_form_document_links",
            "record_form_template_links",
            "total_links",
            "unresolved_targets",
            "review_focus",
            "human_decision",
            "blocking_if_unresolved",
        ],
    )
    write_csv(
        output_dir / files["target_backreference"],
        target_backreference_rows,
        [
            "target_type",
            "target_code",
            "target_id_resolution",
            "linked_block_count",
            "linked_sections",
            "linked_stable_keys",
            "requires_confirmation",
            "human_decision",
            "review_comment",
            "blocking_if_unresolved",
            "not_imported",
        ],
    )
    write_csv(
        output_dir / files["decision_template"],
        decision_rows,
        [
            "decision_item_id",
            "scope",
            "target_key",
            "section_number",
            "target_type",
            "target_code",
            "allowed_decisions",
            "proposed_human_decision",
            "review_comment",
            "required_evidence",
            "blocking_if_unresolved",
            "not_imported",
        ],
    )
    (output_dir / files["manifest"]).write_text(json.dumps(manifest, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
    (output_dir / files["overview"]).write_text(render_overview(manifest), encoding="utf-8")
    (output_dir / files["readme"]).write_text(render_readme(manifest), encoding="utf-8")

    return {
        "generated_at": generated_at,
        "status": "passed",
        "workbench_dir": str(output_dir),
        "counts": counts,
        "files": files,
        "findings": [],
    }


def render_overview(manifest: dict[str, Any]) -> str:
    lines = [
        "# 第二阶段结构化导入人工复核总览",
        "",
        "生成时间：" + str(manifest.get("generated_at", "")),
        "结论：" + str(manifest.get("status", "")),
        "来源预览包：`" + str(manifest.get("source_preview_dir", "")) + "`",
        "",
        "## 计数",
        "",
    ]
    for key, value in manifest.get("counts", {}).items():
        lines.append(f"- {key}: {value}")
    lines.extend(
        [
            "",
            "## 使用边界",
            "",
        ]
    )
    for guardrail in manifest.get("guardrails", []):
        lines.append("- " + str(guardrail))
    lines.extend(
        [
            "",
            "## 复核重点",
            "",
            "- 先核对 `01-手册块复核矩阵.csv` 中每个条款块是否应进入第二阶段结构化导入。",
            "- 再核对 `02-块级链接复核矩阵.csv` 中每条程序、附件/表单、记录模板链接是否真实支撑对应条款。",
            "- 对争议链接在 `05-人工复核意见回填模板.csv` 中填写拟处理意见，不能直接修改预览包或写库。",
            "",
        ]
    )
    return "\n".join(lines)


def render_readme(manifest: dict[str, Any]) -> str:
    lines = [
        "# 第二阶段结构化导入人工复核工作台",
        "",
        "文件状态：人工复核准备材料，不写数据库，不代表第二阶段已导入或人工评审通过。",
        "",
        "## 阅读顺序",
        "",
        "1. `00-第二阶段结构化导入人工复核总览.md`",
        "2. `01-手册块复核矩阵.csv`",
        "3. `02-块级链接复核矩阵.csv`",
        "4. `03-按条款目标统计.csv`",
        "5. `04-目标文件记录反查清单.csv`",
        "6. `05-人工复核意见回填模板.csv`",
        "",
        "## 禁止事项",
        "",
        "- 不写数据库。",
        "- 不代表第二阶段已导入。",
        "- 不代表人工评审通过、文件批准或受控发布。",
        "- 不得把 jewelry-qms 写入质量手册正文作为已正式投用系统。",
        "- 不得把 CNAS 申请中写成已取得 CNAS。",
        "",
        "## 边界",
        "",
    ]
    for guardrail in manifest.get("guardrails", []):
        lines.append("- " + str(guardrail))
    lines.append("")
    return "\n".join(lines)


def render_report(result: dict[str, Any]) -> str:
    lines = [
        "# 第二阶段结构化导入人工复核工作台生成报告",
        "",
        "生成时间：" + str(result.get("generated_at", "")),
        "结论：" + str(result.get("status", "")),
        "工作台目录：`" + str(result.get("workbench_dir", "")) + "`",
        "",
        "## 计数",
        "",
    ]
    for key, value in result.get("counts", {}).items():
        lines.append(f"- {key}: {value}")
    lines.extend(["", "## 发现项", ""])
    if result.get("findings"):
        for finding in result["findings"]:
            lines.append(f"- [{finding['severity']}] {finding['id']}：{finding['message']}")
    else:
        lines.append("生成过程未发现结构性问题。该结论不代表第二阶段已导入、人工评审通过或正式写库授权。")
    lines.append("")
    return "\n".join(lines)


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--preview-dir", required=True)
    parser.add_argument("--output-dir", required=True)
    parser.add_argument("--json-out")
    parser.add_argument("--md-out")
    args = parser.parse_args()

    result = build_workbench(Path(args.preview_dir), Path(args.output_dir))
    print(json.dumps(result, ensure_ascii=False, indent=2))
    if args.json_out:
        Path(args.json_out).write_text(json.dumps(result, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
    if args.md_out:
        Path(args.md_out).write_text(render_report(result), encoding="utf-8")
    return 0 if result["status"] == "passed" else 1


if __name__ == "__main__":
    raise SystemExit(main())
