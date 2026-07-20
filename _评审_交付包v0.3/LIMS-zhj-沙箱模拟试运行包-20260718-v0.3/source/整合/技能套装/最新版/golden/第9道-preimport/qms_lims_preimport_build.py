#!/usr/bin/env python3
"""Build a read-only LIMS pre-import package from the QMS fifth-edition draft."""

from __future__ import annotations

import argparse
import csv
import datetime as dt
import hashlib
import json
import re
from pathlib import Path
from typing import Any


DEFAULT_STAGE_DIR = Path(
    "/Users/lc.leixyz/Documents/AI工作台/01-项目代码/LIMS-zhj/.team/交接箱/2026-07-07-第五版候选修订准备"
)
DEFAULT_LIMS_ROOT = Path("/Users/lc.leixyz/Documents/AI工作台/01-项目代码/LIMS-zhj")

EXPECTED_CLAUSES = [
    "4.1",
    "4.2",
    "5",
    "6.1",
    "6.2",
    "6.3",
    "6.4",
    "6.5",
    "6.6",
    "7.1",
    "7.2",
    "7.3",
    "7.4",
    "7.5",
    "7.6",
    "7.7",
    "7.8",
    "7.9",
    "7.10",
    "7.11",
    "8.1",
    "8.2",
    "8.3",
    "8.4",
    "8.5",
    "8.6",
    "8.7",
    "8.8",
    "8.9",
]

UNIVERSAL_SCHEMA_FIELDS = [
    ("record_number", "记录编号", "text", True),
    ("record_name", "记录名称", "text", True),
    ("applicable_clause", "适用条款", "text", True),
    ("related_procedure", "关联程序", "text", True),
    ("responsible_position", "填写责任", "text", True),
    ("trigger_time", "形成时机", "text", True),
    ("reviewer", "复核/批准", "text", False),
    ("storage_location", "保存位置", "text", False),
    ("retention_period", "保存期限", "text", False),
    ("confidentiality_level", "保密等级", "select", False),
    ("correction_rule", "更正规则", "text", True),
]


def read_text(path: Path) -> str:
    return path.read_text(encoding="utf-8")


def stable_id(prefix: str, *parts: str) -> str:
    raw = "\n".join(parts).encode("utf-8")
    return f"{prefix}_{hashlib.sha1(raw).hexdigest()[:12]}"


def split_md_row(line: str) -> list[str]:
    cells = line.strip().strip("|").split("|")
    return [re.sub(r"<br\s*/?>", "；", cell.strip()) for cell in cells]


def markdown_tables(text: str) -> list[list[dict[str, str]]]:
    lines = text.splitlines()
    tables: list[list[dict[str, str]]] = []
    i = 0
    while i < len(lines):
        if not lines[i].lstrip().startswith("|") or i + 1 >= len(lines):
            i += 1
            continue
        header = split_md_row(lines[i])
        separator = split_md_row(lines[i + 1])
        if not separator or not all(re.fullmatch(r":?-{3,}:?", cell.strip()) for cell in separator):
            i += 1
            continue
        rows: list[dict[str, str]] = []
        i += 2
        while i < len(lines) and lines[i].lstrip().startswith("|"):
            cells = split_md_row(lines[i])
            if len(cells) < len(header):
                cells += [""] * (len(header) - len(cells))
            rows.append(dict(zip(header, cells[: len(header)])))
            i += 1
        tables.append(rows)
    return tables


def clean_inline(text: str) -> str:
    text = re.sub(r"`([^`]+)`", r"\1", text)
    text = re.sub(r"\[([^\]]+)\]\([^)]+\)", r"\1", text)
    return text.strip()


def extract_codes(text: str, prefix: str) -> list[str]:
    if prefix == "procedure":
        return sorted(set(re.findall(r"XZTC/CX-\d+(?:-\d+)?-\d{4}", text)))
    if prefix == "record":
        return sorted(set(re.findall(r"JL-\d+(?:\.\d+)?-\d{2}", text)))
    return []


def slug_from_clause(clause: str) -> str:
    return clause.replace(".", "_")


def field_key(label: str, index: int) -> str:
    token = hashlib.sha1(label.encode("utf-8")).hexdigest()[:8]
    return f"specific_{index:02d}_{token}"


def build_field_schema(template: dict[str, str], related_procedures: list[str]) -> list[dict[str, Any]]:
    fields: list[dict[str, Any]] = []
    for key, label, field_type, required in UNIVERSAL_SCHEMA_FIELDS:
        item: dict[str, Any] = {
            "key": key,
            "label": label,
            "type": field_type,
            "required": required,
            "note": "候选模板通用字段，正式启用前需由文件管理员确认。",
        }
        if key == "confidentiality_level":
            item["options"] = ["普通", "内部", "客户敏感", "待确认"]
        if key == "applicable_clause":
            item["default"] = clean_inline(template.get("对应条款", ""))
        if key == "related_procedure":
            item["default"] = "；".join(related_procedures) if related_procedures else "待确认"
        if key == "responsible_position":
            item["default"] = clean_inline(template.get("责任岗位", ""))
        if key == "trigger_time":
            item["default"] = clean_inline(template.get("填写时机", ""))
        fields.append(item)

    raw_specifics = re.split(r"[、，,；;]", clean_inline(template.get("关键字段", "")))
    specifics = [item.strip() for item in raw_specifics if item.strip()]
    for index, label in enumerate(specifics, start=1):
        fields.append(
            {
                "key": field_key(label, index),
                "label": label,
                "type": "text",
                "required": True,
                "note": "来自候选记录模板清单的关键字段，需与现用表单或试填结果复核。",
            }
        )
    return fields


def is_attachment_form(item: dict[str, str]) -> bool:
    return (
        item.get("document_kind") == "numbered_attachment"
        or item.get("reason") == "numbered_non_procedure"
    )


def load_current_2022_manifest_items(lims_root: Path) -> list[dict[str, str]]:
    manifest = json.loads(read_text(lims_root / "knowledge/internal/procedures/PROCEDURE_FILE_MANIFEST.json"))
    return [
        item
        for item in manifest.get("included", [])
        if item.get("doc_number") and item.get("year") == "2022"
    ]


def load_stage_tables(stage_dir: Path) -> dict[str, list[dict[str, str]]]:
    record_tables = markdown_tables(read_text(stage_dir / "13-记录模板包-候选清单.md"))
    matrix_tables = markdown_tables(read_text(stage_dir / "15-条款程序记录LIMS验证矩阵.md"))
    return {
        "record_templates": next(table for table in record_tables if table and "编号建议" in table[0]),
        "traceability": next(table for table in matrix_tables if table and "条款" in table[0] and "支持性程序" in table[0]),
    }


def write_csv(path: Path, rows: list[dict[str, Any]], fieldnames: list[str]) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    with path.open("w", encoding="utf-8", newline="") as handle:
        writer = csv.DictWriter(handle, fieldnames=fieldnames, extrasaction="ignore")
        writer.writeheader()
        for row in rows:
            writer.writerow({key: row.get(key, "") for key in fieldnames})


def split_manifest_refs(codes: list[str], attachment_codes: set[str]) -> tuple[list[str], list[str]]:
    procedure_codes: list[str] = []
    attachment_refs: list[str] = []
    for code in codes:
        if code in attachment_codes:
            attachment_refs.append(code)
        else:
            procedure_codes.append(code)
    return sorted(set(procedure_codes)), sorted(set(attachment_refs))


def build_package(stage_dir: Path, lims_root: Path, output_dir: Path) -> dict[str, Any]:
    output_dir.mkdir(parents=True, exist_ok=True)
    tables = load_stage_tables(stage_dir)
    current_2022_items = load_current_2022_manifest_items(lims_root)
    attachment_items = [item for item in current_2022_items if is_attachment_form(item)]
    procedure_items = [item for item in current_2022_items if not is_attachment_form(item)]
    attachment_codes = {item["doc_number"] for item in attachment_items}
    trace_rows = tables["traceability"]
    trace_by_clause = {clean_inline(row["条款"]): row for row in trace_rows}

    documents: list[dict[str, Any]] = [
        {
            "action": "revision_candidate",
            "target_table": "documents",
            "level": 1,
            "doc_number": "XZTC/SC",
            "title": "质量手册（第五版候选稿）",
            "version": "第五版候选稿",
            "status": "draft",
            "publish": 0,
            "source_stage_file": "10-质量手册第五版候选稿.md",
            "change_reason": "第四版依据、程序目录、抽样、记录证据链和 LIMS 治理边界候选修订。",
            "import_mode": "manual_review_then_revision_flow",
        }
    ]
    for item in procedure_items:
        documents.append(
            {
                "action": "reference_existing_current",
                "target_table": "documents",
                "level": 2,
                "doc_number": item["doc_number"],
                "title": item["title"],
                "version": "2022",
                "status": "published",
                "publish": 1,
                "source_stage_file": item["relative_path"],
                "change_reason": "用户确认以 LIMS 当前导出的 2022 程序清单作为现行程序目录。",
                "import_mode": "match_or_upsert_after_file_control_review",
            }
        )
    for item in attachment_items:
        documents.append(
            {
                "action": "reference_existing_attachment_form",
                "target_table": "documents",
                "level": 4,
                "doc_number": item["doc_number"],
                "title": item["title"],
                "version": "2022",
                "status": "draft",
                "publish": 0,
                "source_stage_file": item["relative_path"],
                "change_reason": "LIMS 导出 2022 清单中的编号附件/表单；不作为程序文件自动匹配，需人工确认归入记录模板或程序附件。",
                "import_mode": "attachment_form_review_then_record_template_or_document",
            }
        )

    record_templates: list[dict[str, Any]] = []
    for row in tables["record_templates"]:
        doc_number = clean_inline(row["编号建议"])
        clause_text = clean_inline(row["对应条款"])
        clauses = [item.strip() for item in re.split(r"[、,，]", clause_text) if item.strip()]
        procedure_codes: list[str] = []
        attachment_refs: list[str] = []
        for clause in clauses:
            if clause in trace_by_clause:
                split_procedures, split_attachments = split_manifest_refs(
                    extract_codes(trace_by_clause[clause]["支持性程序"], "procedure"),
                    attachment_codes,
                )
                procedure_codes.extend(split_procedures)
                attachment_refs.extend(split_attachments)
        procedure_codes = sorted(set(procedure_codes))
        attachment_refs = sorted(set(attachment_refs))
        field_schema = build_field_schema(row, procedure_codes)
        review_note = "由第五版候选修订包生成；正式启用前需人工核实现用表单、保存期限和字段。"
        if attachment_refs:
            review_note += " 关联编号附件/表单需人工确认归属：" + "、".join(attachment_refs) + "。"
        record_templates.append(
            {
                "action": "candidate_record_template",
                "target_table": "record_form_templates",
                "doc_number": doc_number,
                "name": clean_inline(row["记录名称"]),
                "module": "QMS候选记录模板",
                "version": "候选",
                "status": "draft",
                "review_status": "pending",
                "print_template_key": "generic_record_form",
                "applicable_clauses": clause_text,
                "procedure_doc_numbers": "；".join(procedure_codes) if procedure_codes else "待确认",
                "attachment_form_doc_numbers": "；".join(attachment_refs),
                "responsible_position": clean_inline(row["责任岗位"]),
                "trigger_time": clean_inline(row["填写时机"]),
                "field_schema_json": json.dumps(field_schema, ensure_ascii=False, separators=(",", ":")),
                "review_note": review_note,
            }
        )
        documents.append(
            {
                "action": "candidate_record_template_document",
                "target_table": "documents",
                "level": 4,
                "doc_number": doc_number,
                "title": clean_inline(row["记录名称"]),
                "version": "候选",
                "status": "draft",
                "publish": 0,
                "source_stage_file": "13-记录模板包-候选清单.md",
                "change_reason": "第五版候选手册记录证据链候选模板。",
                "import_mode": "record_template_review_then_create",
            }
        )

    structured_documents: list[dict[str, Any]] = []
    for doc in documents:
        role = {1: "quality_manual", 2: "procedure", 4: "record_form"}.get(int(doc["level"]), "reference_file")
        structured_documents.append(
            {
                "action": doc["action"],
                "target_table": "qms_structured_documents",
                "document_role": role,
                "doc_number": doc["doc_number"],
                "title": doc["title"],
                "version": doc["version"],
                "source_status": "draft" if doc["status"] == "draft" else "current",
                "status": "draft" if doc["status"] == "draft" else "structured",
                "markdown_path": doc.get("source_stage_file", ""),
                "review_note": doc.get("change_reason", ""),
            }
        )

    traceability: list[dict[str, Any]] = []
    manual_blocks: list[dict[str, Any]] = []
    for index, row in enumerate(trace_rows, start=1):
        clause = clean_inline(row["条款"])
        procedure_codes, attachment_refs = split_manifest_refs(
            extract_codes(row["支持性程序"], "procedure"),
            attachment_codes,
        )
        record_codes = extract_codes(row["记录模板"], "record")
        traceability.append(
            {
                "clause": clause,
                "manual_topic": clean_inline(row["手册主题"]),
                "procedure_doc_numbers": "；".join(procedure_codes),
                "attachment_form_doc_numbers": "；".join(attachment_refs),
                "record_template_numbers": "；".join(record_codes),
                "lims_governance_point": clean_inline(row["后续 LIMS 治理点"]),
                "verification_method": clean_inline(row["验证方法"]),
                "relation_confidence": "review_required",
                "human_review_required": "yes",
            }
        )
        manual_blocks.append(
            {
                "target_table": "qms_document_blocks",
                "structured_doc_number": "XZTC/SC",
                "stable_key": f"manual_v5_candidate_{slug_from_clause(clause)}",
                "section_number": clause,
                "title": clean_inline(row["手册主题"]),
                "block_type": "control_requirement",
                "sort_order": index,
                "procedure_doc_numbers": "；".join(procedure_codes),
                "attachment_form_doc_numbers": "；".join(attachment_refs),
                "record_template_numbers": "；".join(record_codes),
                "link_relation_type": "implements",
                "link_confidence": "review_required",
                "source_locator": "10-质量手册第五版候选稿.md",
            }
        )

    external_sources = [
        {
            "source_code": "CNAS_CL01_G001_2024",
            "name": "CNAS-CL01-G001:2024《检测和校准实验室能力认可准则的应用要求》",
            "source_type": "external_standard",
            "version": "2024",
            "freshness_checked_at": "2026-07-07",
            "freshness_result": "官方页面可查，候选稿按现行应用要求使用。",
            "freshness_status": "current",
            "status": "published",
            "freshness_evidence": "https://www.cnas.org.cn/rkgf/sysrk/rkyyzz/art/2024/art_72245f37cbac480697079c8cf78d8b4b.html",
        },
        {
            "source_code": "CNAS_CL01_G003_2021_REV2023",
            "name": "CNAS-CL01-G003:2021《测量不确定度的要求》（2023-01-01 第一次修订）",
            "source_type": "external_standard",
            "version": "2021/2023修订",
            "freshness_checked_at": "2026-07-07",
            "freshness_result": "官方页面可查，替换第四版中的 2018 口径。",
            "freshness_status": "current",
            "status": "published",
            "freshness_evidence": "https://www.cnas.org.cn/rkgf/sysrk/rkyyzz/art/2024/art_69a6ecdcb8634b33ab6acbb6c335f56f.html",
        },
        {
            "source_code": "SAMR_CMA_APPRAISAL_RULES_2023_21",
            "name": "《检验检测机构资质认定评审准则》（国家市场监督管理总局公告 2023 年第 21 号）",
            "source_type": "regulatory_rule",
            "version": "2023",
            "freshness_checked_at": "2026-07-07",
            "freshness_result": "用于 CMA 已取得状态下的主要资质认定评审依据。",
            "freshness_status": "current",
            "status": "published",
            "freshness_evidence": "https://zwfw.samr.gov.cn/scjg/wyb/rzrkjyjc/2024082707005060849/",
        },
        {
            "source_code": "RBT_045_2020",
            "name": "RB/T 045-2020《检验检测机构管理和技术能力评价 内部审核要求》",
            "source_type": "industry_standard",
            "version": "2020",
            "freshness_checked_at": "2026-07-07",
            "freshness_result": "不作为第五版候选稿现行依据。",
            "freshness_status": "obsolete",
            "status": "obsolete",
            "freshness_evidence": "https://hbba.sacinfo.org.cn/snDetail/f696005a52113a9675dd8b535adfb30f622e2b50f81f5562a84c72d9c364020d",
        },
    ]

    files = {
        "documents": "documents_preimport.csv",
        "structured_documents": "structured_documents_preimport.csv",
        "record_form_templates": "record_form_templates_preimport.csv",
        "traceability_matrix": "traceability_matrix_preimport.csv",
        "manual_blocks": "manual_blocks_preimport.csv",
        "external_sources": "external_sources_preimport.csv",
    }
    write_csv(
        output_dir / files["documents"],
        documents,
        [
            "action",
            "target_table",
            "level",
            "doc_number",
            "title",
            "version",
            "status",
            "publish",
            "source_stage_file",
            "change_reason",
            "import_mode",
        ],
    )
    write_csv(
        output_dir / files["structured_documents"],
        structured_documents,
        [
            "action",
            "target_table",
            "document_role",
            "doc_number",
            "title",
            "version",
            "source_status",
            "status",
            "markdown_path",
            "review_note",
        ],
    )
    write_csv(
        output_dir / files["record_form_templates"],
        record_templates,
        [
            "action",
            "target_table",
            "doc_number",
            "name",
            "module",
            "version",
            "status",
            "review_status",
            "print_template_key",
            "applicable_clauses",
            "procedure_doc_numbers",
            "attachment_form_doc_numbers",
            "responsible_position",
            "trigger_time",
            "field_schema_json",
            "review_note",
        ],
    )
    write_csv(
        output_dir / files["traceability_matrix"],
        traceability,
        [
            "clause",
            "manual_topic",
            "procedure_doc_numbers",
            "attachment_form_doc_numbers",
            "record_template_numbers",
            "lims_governance_point",
            "verification_method",
            "relation_confidence",
            "human_review_required",
        ],
    )
    write_csv(
        output_dir / files["manual_blocks"],
        manual_blocks,
        [
            "target_table",
            "structured_doc_number",
            "stable_key",
            "section_number",
            "title",
            "block_type",
            "sort_order",
            "procedure_doc_numbers",
            "attachment_form_doc_numbers",
            "record_template_numbers",
            "link_relation_type",
            "link_confidence",
            "source_locator",
        ],
    )
    write_csv(
        output_dir / files["external_sources"],
        external_sources,
        [
            "source_code",
            "name",
            "source_type",
            "version",
            "freshness_checked_at",
            "freshness_result",
            "freshness_status",
            "status",
            "freshness_evidence",
        ],
    )

    manifest = {
        "generated_at": dt.datetime.now().isoformat(timespec="seconds"),
        "package_status": "preimport_draft_no_database_write",
        "stage_dir": str(stage_dir),
        "lims_root": str(lims_root),
        "output_dir": str(output_dir),
        "boundary": [
            "本包仅用于 LIMS 治理预导入评审，不写数据库。",
            "第五版候选手册仍为候选草案，未经审核批准不得作为受控文件执行。",
            "jewelry-qms 仍按建设中系统处理，正式纳入前需完成功能确认。",
        ],
        "counts": {
            "documents": len(documents),
            "structured_documents": len(structured_documents),
            "record_form_templates": len(record_templates),
            "traceability_rows": len(traceability),
            "manual_blocks": len(manual_blocks),
            "external_sources": len(external_sources),
            "current_2022_catalog_codes": len(current_2022_items),
            "current_2022_procedure_codes": len(procedure_items),
            "current_2022_attachment_form_codes": len(attachment_items),
        },
        "files": files,
        "source_files": [
            "10-质量手册第五版候选稿.md",
            "12-支持性程序目录-2022版.md",
            "13-记录模板包-候选清单.md",
            "15-条款程序记录LIMS验证矩阵.md",
            "16-依据现行性复核记录.md",
            "knowledge/internal/procedures/PROCEDURE_FILE_MANIFEST.json",
        ],
        "expected_clauses": EXPECTED_CLAUSES,
    }
    (output_dir / "preimport_manifest.json").write_text(
        json.dumps(manifest, ensure_ascii=False, indent=2) + "\n",
        encoding="utf-8",
    )

    return manifest


def render_readme(manifest: dict[str, Any]) -> str:
    counts = manifest["counts"]
    lines = [
        "# LIMS 预导入包",
        "",
        f"生成时间：{manifest['generated_at']}",
        "状态：预导入草案，不写数据库，不代表受控发布。",
        "",
        "## 包内容",
        "",
        f"- 文件控制预导入：{counts['documents']} 条",
        f"- 结构化文件预导入：{counts['structured_documents']} 条",
        f"- 记录模板预导入：{counts['record_form_templates']} 条",
        f"- 条款追溯矩阵：{counts['traceability_rows']} 条",
        f"- 手册块级索引：{counts['manual_blocks']} 条",
        f"- 外来依据台账候选：{counts['external_sources']} 条",
        f"- LIMS 2022 清单项：{counts['current_2022_catalog_codes']} 个（程序 {counts['current_2022_procedure_codes']} 个，编号附件/表单 {counts['current_2022_attachment_form_codes']} 个）",
        "",
        "## 使用边界",
        "",
        "1. 本包是给 LIMS 治理导入前评审用的 CSV/JSON，不是数据库迁移脚本。",
        "2. 现有 `ImportService` 只能直接处理基础文档 CSV；记录模板、结构化文件和块级追溯仍需后续开发或人工确认后导入。",
        "3. 所有记录模板字段均为候选 schema，正式启用前必须与现用表单或试填结果比对。",
        "4. 质量手册第五版仍为候选草案，不能作为现行受控文件执行。",
        "",
        "## 文件清单",
        "",
    ]
    for label, filename in manifest["files"].items():
        lines.append(f"- `{filename}`：{label}")
    lines.extend(
        [
            "- `preimport_manifest.json`：包元数据和计数。",
            "",
        ]
    )
    return "\n".join(lines)


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--stage-dir", default=str(DEFAULT_STAGE_DIR))
    parser.add_argument("--lims-root", default=str(DEFAULT_LIMS_ROOT))
    parser.add_argument("--output-dir")
    args = parser.parse_args()

    stage_dir = Path(args.stage_dir)
    lims_root = Path(args.lims_root)
    output_dir = Path(args.output_dir) if args.output_dir else stage_dir / "lims_preimport_package"
    manifest = build_package(stage_dir, lims_root, output_dir)
    (output_dir / "README.md").write_text(render_readme(manifest), encoding="utf-8")
    print(json.dumps(manifest, ensure_ascii=False, indent=2))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
