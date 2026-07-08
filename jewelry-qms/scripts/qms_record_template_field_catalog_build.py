#!/usr/bin/env python3
"""Build a human-readable field catalog for QMS record templates."""

from __future__ import annotations

import argparse
import csv
import datetime as dt
import json
import re
from pathlib import Path
from typing import Any


DEFAULT_STAGE_DIR = Path(
    "/Users/lc.leixyz/Documents/AI工作台/01-项目代码/LIMS-zhj/.team/交接箱/2026-07-07-第五版候选修订准备"
)

COMMON_FIELD_KEYS = [
    "record_number",
    "record_name",
    "applicable_clause",
    "related_procedure",
    "responsible_position",
    "trigger_time",
    "reviewer",
    "storage_location",
    "retention_period",
    "confidentiality_level",
    "correction_rule",
]

GUARDRAILS = [
    "本字段字典包只用于候选记录模板评审和 LIMS 字段配置准备，不写数据库。",
    "本字段字典包不代表受控发布，也不代表真实记录已经形成。",
    "字段默认值、保存期限、保密等级、签核规则和 05-02 归属仍需人工评审确认。",
    "试填值来自模拟试填包，只验证字段可填性，不得作为真实运行记录导入。",
]

FIELD_INDEX_FIELDS = [
    "template_code",
    "template_name",
    "applicable_clauses",
    "procedure_doc_numbers",
    "responsible_position",
    "trigger_time",
    "field_count",
    "required_field_count",
    "common_field_count",
    "specific_field_count",
    "human_confirmation_field_count",
    "trial_markdown_file",
    "missing_human_inputs",
    "catalog_markdown_file",
]

FIELD_DETAIL_FIELDS = [
    "template_code",
    "template_name",
    "field_order",
    "field_key",
    "field_label",
    "field_type",
    "required",
    "field_group",
    "default_value",
    "note",
    "trial_value_present",
    "trial_value",
    "human_confirmation_required",
    "review_focus",
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


def safe_filename(text: str) -> str:
    cleaned = re.sub(r"[^0-9A-Za-z\u4e00-\u9fff._-]+", "-", text).strip("-")
    return cleaned[:120] or "template"


def short_value(value: Any, limit: int = 120) -> str:
    text = "" if value is None else str(value)
    text = text.replace("\n", " ").replace("|", "／")
    return text if len(text) <= limit else text[: limit - 1] + "..."


def read_trial_values(trial_dir: Path) -> dict[str, dict[str, Any]]:
    path = trial_dir / "record_form_instances_trial.csv"
    if not path.exists():
        return {}
    result: dict[str, dict[str, Any]] = {}
    for row in read_csv(path):
        code = row.get("template_code", "")
        try:
            values = json.loads(row.get("field_values_json", "{}"))
        except json.JSONDecodeError:
            values = {}
        result[code] = {
            "values": values,
            "markdown_file": row.get("markdown_file", ""),
            "missing_human_inputs": row.get("missing_human_inputs", ""),
        }
    return result


def needs_human_confirmation(field: dict[str, Any], trial_value: Any) -> tuple[str, str]:
    key = str(field.get("key", ""))
    label = str(field.get("label", ""))
    note = str(field.get("note", ""))
    default = str(field.get("default", ""))
    value = "" if trial_value is None else str(trial_value)
    haystack = " ".join([key, label, note, default, value])

    focus: list[str] = []
    if key in {"storage_location", "retention_period", "confidentiality_level", "reviewer"}:
        focus.append("需确认保存/保密/签核规则")
    if "待人工确认" in haystack or "待确认" in haystack:
        focus.append("试填值仍为待确认")
    if "正式启用前需由文件管理员确认" in haystack:
        focus.append("候选字段需文件管理员确认")
    if key.startswith("specific_"):
        focus.append("需过程负责人确认字段含义和现用表单一致性")
    if not focus:
        return "no", ""
    return "yes", "；".join(dict.fromkeys(focus))


def field_group(key: str) -> str:
    if key in COMMON_FIELD_KEYS:
        return "common"
    if key.startswith("specific_"):
        return "specific"
    return "extended"


def render_table(rows: list[dict[str, Any]], columns: list[str]) -> list[str]:
    lines = ["| " + " | ".join(columns) + " |", "|" + "|".join(["---"] * len(columns)) + "|"]
    for row in rows:
        lines.append("| " + " | ".join(short_value(row.get(column, "")) for column in columns) + " |")
    return lines


def render_template_doc(template: dict[str, str], fields: list[dict[str, Any]], catalog_rel_path: str) -> str:
    human_fields = [field for field in fields if field.get("human_confirmation_required") == "yes"]
    lines = [
        f"# {template['doc_number']} {template['name']}字段字典",
        "",
        "文件状态：候选记录模板字段字典，不写数据库，不代表受控发布。",
        "",
        "## 模板信息",
        "",
        f"- 适用条款：{template.get('applicable_clauses', '')}",
        f"- 关联程序：{template.get('procedure_doc_numbers', '')}",
        f"- 填写责任：{template.get('responsible_position', '')}",
        f"- 形成时机：{template.get('trigger_time', '')}",
        f"- 字段总数：{len(fields)}",
        f"- 需人工确认字段：{len(human_fields)}",
        "",
        "## 字段明细",
        "",
    ]
    lines.extend(
        render_table(
            fields,
            [
                "field_order",
                "field_key",
                "field_label",
                "field_type",
                "required",
                "field_group",
                "trial_value",
                "human_confirmation_required",
                "review_focus",
            ],
        )
    )
    lines.extend(["", "## 评审提示", ""])
    if human_fields:
        for field in human_fields:
            lines.append(f"- `{field['field_key']}`：{field.get('review_focus', '')}")
    else:
        lines.append("- 暂未识别到字段级人工确认提示；仍需结合现用表单和真实流程复核。")
    lines.extend(
        [
            "",
            "## 边界",
            "",
        ]
    )
    lines.extend(f"- {item}" for item in GUARDRAILS)
    lines.extend(["", f"字段明细 CSV 汇总：`{catalog_rel_path}`", ""])
    return "\n".join(lines)


def render_overview(manifest: dict[str, Any], index_rows: list[dict[str, Any]]) -> str:
    counts = manifest["counts"]
    lines = [
        "# 记录模板字段字典总览",
        "",
        "本包把 LIMS 预导入记录模板的 `field_schema_json` 转成可人工评审的字段字典；不写数据库，不代表受控发布。",
        "",
        "## 计数",
        "",
        f"- 记录模板：{counts['record_templates']}",
        f"- 字段总数：{counts['fields']}",
        f"- 必填字段：{counts['required_fields']}",
        f"- 通用字段：{counts['common_fields']}",
        f"- 专项字段：{counts['specific_fields']}",
        f"- 需人工确认字段：{counts['human_confirmation_fields']}",
        "",
        "## 模板索引",
        "",
    ]
    lines.extend(
        render_table(
            index_rows,
            [
                "template_code",
                "template_name",
                "field_count",
                "required_field_count",
                "human_confirmation_field_count",
                "trial_markdown_file",
                "catalog_markdown_file",
            ],
        )
    )
    lines.extend(["", "## 边界", ""])
    lines.extend(f"- {item}" for item in manifest["guardrails"])
    lines.append("")
    return "\n".join(lines)


def render_common_matrix(index_rows: list[dict[str, Any]], detail_rows: list[dict[str, Any]]) -> str:
    by_template: dict[str, set[str]] = {}
    for row in detail_rows:
        by_template.setdefault(str(row["template_code"]), set()).add(str(row["field_key"]))
    matrix_rows: list[dict[str, Any]] = []
    for template in index_rows:
        keys = by_template.get(str(template["template_code"]), set())
        row: dict[str, Any] = {
            "template_code": template["template_code"],
            "template_name": template["template_name"],
            "missing_common_fields": "；".join(key for key in COMMON_FIELD_KEYS if key not in keys),
        }
        for key in COMMON_FIELD_KEYS:
            row[key] = "yes" if key in keys else "missing"
        matrix_rows.append(row)
    columns = ["template_code", "template_name", *COMMON_FIELD_KEYS, "missing_common_fields"]
    lines = [
        "# 通用字段覆盖矩阵",
        "",
        "本矩阵用于确认 26 个候选记录模板是否都具备记录编号、适用条款、关联程序、责任岗位、保存和更正规则等通用治理字段；不写数据库，不代表受控发布。",
        "",
    ]
    lines.extend(render_table(matrix_rows, columns))
    lines.append("")
    return "\n".join(lines)


def build_catalog(stage_dir: Path, preimport_dir: Path, trial_dir: Path, output_dir: Path) -> dict[str, Any]:
    templates = read_csv(preimport_dir / "record_form_templates_preimport.csv")
    trial_values = read_trial_values(trial_dir)
    output_dir.mkdir(parents=True, exist_ok=True)
    template_dir = output_dir / "templates"
    template_dir.mkdir(parents=True, exist_ok=True)

    index_rows: list[dict[str, Any]] = []
    detail_rows: list[dict[str, Any]] = []
    generated_template_files: list[str] = []

    for template in templates:
        code = template.get("doc_number", "")
        name = template.get("name", "")
        try:
            schema = json.loads(template.get("field_schema_json", "[]"))
        except json.JSONDecodeError:
            schema = []
        trial = trial_values.get(code, {})
        values = trial.get("values", {})
        template_detail_rows: list[dict[str, Any]] = []
        for order, field in enumerate(schema, start=1):
            key = str(field.get("key", ""))
            trial_value = values.get(key, "")
            human_required, focus = needs_human_confirmation(field, trial_value)
            detail = {
                "template_code": code,
                "template_name": name,
                "field_order": order,
                "field_key": key,
                "field_label": field.get("label", ""),
                "field_type": field.get("type", ""),
                "required": "yes" if bool(field.get("required")) else "no",
                "field_group": field_group(key),
                "default_value": field.get("default", ""),
                "note": field.get("note", ""),
                "trial_value_present": "yes" if key in values else "no",
                "trial_value": short_value(trial_value),
                "human_confirmation_required": human_required,
                "review_focus": focus,
            }
            detail_rows.append(detail)
            template_detail_rows.append(detail)

        filename = f"{safe_filename(code + '-' + name)}-字段字典.md"
        rel_template_path = f"templates/{filename}"
        (template_dir / filename).write_text(
            render_template_doc(template, template_detail_rows, "02-字段级明细.csv"),
            encoding="utf-8",
        )
        generated_template_files.append(rel_template_path)
        index_rows.append(
            {
                "template_code": code,
                "template_name": name,
                "applicable_clauses": template.get("applicable_clauses", ""),
                "procedure_doc_numbers": template.get("procedure_doc_numbers", ""),
                "responsible_position": template.get("responsible_position", ""),
                "trigger_time": template.get("trigger_time", ""),
                "field_count": len(template_detail_rows),
                "required_field_count": sum(1 for row in template_detail_rows if row["required"] == "yes"),
                "common_field_count": sum(1 for row in template_detail_rows if row["field_group"] == "common"),
                "specific_field_count": sum(1 for row in template_detail_rows if row["field_group"] == "specific"),
                "human_confirmation_field_count": sum(1 for row in template_detail_rows if row["human_confirmation_required"] == "yes"),
                "trial_markdown_file": trial.get("markdown_file", ""),
                "missing_human_inputs": trial.get("missing_human_inputs", ""),
                "catalog_markdown_file": rel_template_path,
            }
        )

    counts = {
        "record_templates": len(index_rows),
        "fields": len(detail_rows),
        "required_fields": sum(1 for row in detail_rows if row["required"] == "yes"),
        "common_fields": sum(1 for row in detail_rows if row["field_group"] == "common"),
        "specific_fields": sum(1 for row in detail_rows if row["field_group"] == "specific"),
        "human_confirmation_fields": sum(1 for row in detail_rows if row["human_confirmation_required"] == "yes"),
        "templates_with_trial_links": sum(1 for row in index_rows if row.get("trial_markdown_file")),
    }

    files = {
        "overview": "00-字段字典总览.md",
        "template_index": "01-模板字段索引.csv",
        "field_detail": "02-字段级明细.csv",
        "common_matrix": "03-通用字段覆盖矩阵.md",
        "manifest": "field_catalog_manifest.json",
        "readme": "README.md",
        "template_docs_dir": "templates/",
        "template_docs": generated_template_files,
    }
    manifest = {
        "generated_at": dt.datetime.now().isoformat(timespec="seconds"),
        "status": "field_catalog_no_database_write",
        "stage_dir": str(stage_dir),
        "preimport_dir": str(preimport_dir),
        "trial_dir": str(trial_dir),
        "output_dir": str(output_dir),
        "counts": counts,
        "files": files,
        "common_field_keys": COMMON_FIELD_KEYS,
        "guardrails": GUARDRAILS,
    }

    write_csv(output_dir / files["template_index"], index_rows, FIELD_INDEX_FIELDS)
    write_csv(output_dir / files["field_detail"], detail_rows, FIELD_DETAIL_FIELDS)
    (output_dir / files["common_matrix"]).write_text(render_common_matrix(index_rows, detail_rows), encoding="utf-8")
    (output_dir / files["manifest"]).write_text(json.dumps(manifest, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
    overview = render_overview(manifest, index_rows)
    (output_dir / files["overview"]).write_text(overview, encoding="utf-8")
    (output_dir / files["readme"]).write_text(overview, encoding="utf-8")
    return manifest


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--stage-dir", default=str(DEFAULT_STAGE_DIR))
    parser.add_argument("--preimport-dir")
    parser.add_argument("--trial-dir")
    parser.add_argument("--output-dir")
    args = parser.parse_args()

    stage_dir = Path(args.stage_dir)
    preimport_dir = Path(args.preimport_dir) if args.preimport_dir else stage_dir / "lims_preimport_package"
    trial_dir = Path(args.trial_dir) if args.trial_dir else stage_dir / "record_template_full_trial_pack"
    output_dir = Path(args.output_dir) if args.output_dir else stage_dir / "record_template_field_catalog"
    manifest = build_catalog(stage_dir, preimport_dir, trial_dir, output_dir)
    print(json.dumps(manifest, ensure_ascii=False, indent=2))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
