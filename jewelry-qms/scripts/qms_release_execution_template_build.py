#!/usr/bin/env python3
"""Build candidate record templates for controlled-release execution evidence."""

from __future__ import annotations

import argparse
import csv
import datetime as dt
import json
from pathlib import Path
from typing import Any


DEFAULT_STAGE_DIR = Path(
    "/Users/lc.leixyz/Documents/AI工作台/01-项目代码/LIMS-zhj/.team/交接箱/2026-07-07-第五版候选修订准备"
)

COMMON_FIELDS = [
    ("record_number", "记录编号", "text", True, ""),
    ("record_name", "记录名称", "text", True, ""),
    ("applicable_clause", "适用条款", "text", True, ""),
    ("related_procedure", "关联程序", "text", True, ""),
    ("responsible_position", "填写责任", "text", True, ""),
    ("trigger_time", "形成时机", "text", True, ""),
    ("reviewer", "复核/批准", "text", False, "待人工确认"),
    ("approval_status", "审批/确认状态", "select", True, "pending"),
    ("evidence_reference", "证据位置", "text", True, ""),
    ("storage_location", "保存位置", "text", False, "待人工确认"),
    ("retention_period", "保存期限", "text", False, "待人工确认"),
    ("confidentiality_level", "保密等级", "select", False, "待确认"),
    ("correction_rule", "更正规则", "text", True, "保留原始信息、更正原因、更正日期、责任人和复核痕迹。"),
    ("not_real_record_marker", "非真实记录标识", "text", True, "SIMULATED_TRIAL_NOT_REAL_RECORD"),
]

GUARDRAILS = [
    "本包仅用于受控发布执行记录模板评审和 LIMS 字段配置准备，不写数据库。",
    "本包不代表第五版候选稿、记录模板、培训记录、旧版处置或 jewelry-qms 已经批准、受控发布或正式运行。",
    "模拟试填均带 SIMULATED_TRIAL_NOT_REAL_RECORD 标识，不得作为真实运行记录。",
    "资质状态按已取得 CMA、CNAS 申请中处理；不得写成已取得 CNAS。",
    "jewelry-qms 仍为建设中系统，仅在实施计划、试运行和适用性确认模板中出现，不写入质量手册正文。",
]

TEMPLATE_INDEX_FIELDS = [
    "template_code",
    "template_name",
    "applicable_clauses",
    "related_procedures",
    "responsible_position",
    "trigger_time",
    "source_rehearsal_file",
    "source_rehearsal_rows",
    "field_count",
    "required_field_count",
    "review_status",
    "markdown_file",
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
    "review_focus",
]

TRIAL_FIELDS = [
    "template_code",
    "template_name",
    "record_number",
    "field_values_json",
    "markdown_file",
    "not_real_record",
]


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
    cleaned = "".join(ch if ch.isalnum() or ch in "-_." else "-" for ch in text).strip("-")
    return cleaned[:120] or "template"


def md_cell(value: Any) -> str:
    return str(value).replace("\n", " ").replace("|", "／")


def render_table(rows: list[dict[str, Any]], columns: list[str]) -> list[str]:
    lines = ["| " + " | ".join(columns) + " |", "|" + "|".join(["---"] * len(columns)) + "|"]
    for row in rows:
        lines.append("| " + " | ".join(md_cell(row.get(column, "")) for column in columns) + " |")
    return lines


def field(key: str, label: str, field_type: str = "text", required: bool = True, default: str = "", focus: str = "") -> dict[str, Any]:
    return {
        "key": key,
        "label": label,
        "type": field_type,
        "required": required,
        "default": default,
        "review_focus": focus or "需人工确认字段含义、填写责任、保存和复核要求。",
    }


def common_schema(defaults: dict[str, str]) -> list[dict[str, Any]]:
    result: list[dict[str, Any]] = []
    for key, label, field_type, required, default in COMMON_FIELDS:
        result.append(
            field(
                key,
                label,
                field_type,
                required,
                defaults.get(key, default),
                "通用治理字段，正式启用前需由文件管理员确认。",
            )
        )
    return result


def build_template_specs(source_counts: dict[str, int]) -> list[dict[str, Any]]:
    return [
        {
            "template_code": "JL-REL-01",
            "template_name": "体系文件审批签核记录",
            "applicable_clauses": "8.2、8.3、8.4",
            "related_procedures": "XZTC/CX-08-2022；XZTC/CX-19-2022",
            "responsible_position": "文件管理员/质量负责人",
            "trigger_time": "候选文件或记录模板提交审核批准时",
            "source_rehearsal_file": "02-审批签核演练清单.csv",
            "source_rehearsal_rows": source_counts["approval_items"],
            "specific_fields": [
                field("release_item_id", "发布对象编号"),
                field("object_doc_number", "文件或模板编号"),
                field("review_summary", "审核意见摘要"),
                field("approver_roles", "审批岗位"),
                field("approval_result", "批准结论", "select", True, "pending"),
                field("effective_date", "拟生效日期", "date", False, "待人工确认"),
            ],
        },
        {
            "template_code": "JL-REL-02",
            "template_name": "文件发布发放与现行状态确认记录",
            "applicable_clauses": "8.3、8.4",
            "related_procedures": "XZTC/CX-08-2022；XZTC/CX-19-2022",
            "responsible_position": "文件管理员",
            "trigger_time": "受控文件批准发布、发放、回收或现行状态确认时",
            "source_rehearsal_file": "01-发布对象清单.csv",
            "source_rehearsal_rows": source_counts["release_objects"],
            "specific_fields": [
                field("distribution_scope", "发放范围"),
                field("recipient_role", "接收岗位"),
                field("issue_method", "发放方式"),
                field("receipt_status", "接收确认状态", "select", True, "pending"),
                field("current_status_check", "现行状态核查结论"),
                field("old_version_link", "旧版处置关联编号", "text", False, "待人工确认"),
            ],
        },
        {
            "template_code": "JL-REL-03",
            "template_name": "体系文件培训宣贯与理解确认记录",
            "applicable_clauses": "6.2、8.2、8.3、8.4",
            "related_procedures": "XZTC/CX-01-2022；XZTC/CX-01-02-2022；XZTC/CX-08-2022；XZTC/CX-19-2022",
            "responsible_position": "质量负责人/文件管理员/培训责任人",
            "trigger_time": "新文件发布、生效前或记录模板启用前",
            "source_rehearsal_file": "03-培训宣贯演练清单.csv",
            "source_rehearsal_rows": source_counts["training_items"],
            "specific_fields": [
                field("training_topic", "培训主题"),
                field("trainee_group", "培训对象"),
                field("training_method", "培训方式"),
                field("comprehension_check", "理解确认方式"),
                field("questions_follow_up", "问题与跟踪"),
                field("trainer", "培训人"),
            ],
        },
        {
            "template_code": "JL-REL-04",
            "template_name": "旧版文件回收作废与留存记录",
            "applicable_clauses": "8.3、8.4",
            "related_procedures": "XZTC/CX-08-2022；XZTC/CX-19-2022",
            "responsible_position": "文件管理员",
            "trigger_time": "新版文件生效、旧版文件回收、作废留存或销毁时",
            "source_rehearsal_file": "04-旧版处置演练清单.csv",
            "source_rehearsal_rows": source_counts["obsolete_items"],
            "specific_fields": [
                field("obsolete_object", "作废对象"),
                field("old_version", "旧版版本/编号"),
                field("recycle_method", "回收或作废方式"),
                field("retained_copy_location", "作废留存位置", "text", False, "待人工确认"),
                field("disposal_result", "处置结果"),
                field("verifier", "验证人"),
            ],
        },
        {
            "template_code": "JL-REL-05",
            "template_name": "实施有效性检查与问题跟踪记录",
            "applicable_clauses": "8.6、8.8、8.9",
            "related_procedures": "XZTC/CX-18-2022；XZTC/CX-20-2022；XZTC/CX-21-2022",
            "responsible_position": "质量负责人/内审员/过程负责人",
            "trigger_time": "新版体系文件或记录模板试运行后、内审或管理评审输入前",
            "source_rehearsal_file": "07-实施有效性检查清单.csv",
            "source_rehearsal_rows": source_counts["effectiveness_items"],
            "specific_fields": [
                field("process_checked", "检查过程"),
                field("sample_scope", "抽查范围"),
                field("acceptance_criteria", "验收准则"),
                field("findings", "发现问题"),
                field("action_required", "需采取措施"),
                field("closure_status", "关闭状态", "select", True, "pending"),
            ],
        },
        {
            "template_code": "JL-REL-06",
            "template_name": "jewelry-qms试运行与适用性确认记录",
            "applicable_clauses": "7.11、8.3、8.4",
            "related_procedures": "XZTC/CX-08-2022；XZTC/CX-19-2022；XZTC/CX-26-2022",
            "responsible_position": "质量负责人/系统管理员/文件管理员",
            "trigger_time": "jewelry-qms 试运行、功能变更或拟纳入体系运行前",
            "source_rehearsal_file": "07-实施有效性检查清单.csv",
            "source_rehearsal_rows": source_counts["effectiveness_items"],
            "specific_fields": [
                field("system_function", "系统功能"),
                field("validation_scope", "适用性确认范围"),
                field("access_control_check", "权限控制检查"),
                field("audit_trail_check", "审计追踪检查"),
                field("backup_restore_check", "备份恢复检查"),
                field("go_live_recommendation", "纳入体系运行建议", "select", True, "pending"),
            ],
        },
    ]


def schema_for_template(spec: dict[str, Any]) -> list[dict[str, Any]]:
    defaults = {
        "record_name": spec["template_name"],
        "applicable_clause": spec["applicable_clauses"],
        "related_procedure": spec["related_procedures"],
        "responsible_position": spec["responsible_position"],
        "trigger_time": spec["trigger_time"],
        "evidence_reference": spec["source_rehearsal_file"],
    }
    return common_schema(defaults) + spec["specific_fields"]


def trial_values(spec: dict[str, Any], schema: list[dict[str, Any]]) -> dict[str, str]:
    values: dict[str, str] = {}
    for item in schema:
        key = item["key"]
        default = str(item.get("default", ""))
        if key == "record_number":
            values[key] = f"SIM-{spec['template_code']}-20260707-001"
        elif key == "approval_status":
            values[key] = "pending; SIMULATED_TRIAL_NOT_REAL_RECORD"
        elif key == "not_real_record_marker":
            values[key] = "SIMULATED_TRIAL_NOT_REAL_RECORD"
        elif key.startswith(("release_", "object_", "review_", "approver_", "effective_", "distribution_", "recipient_", "issue_", "receipt_", "current_", "old_", "training_", "trainee_", "comprehension_", "questions_", "trainer", "obsolete_", "recycle_", "retained_", "disposal_", "verifier", "process_", "sample_", "acceptance_", "findings", "action_", "closure_", "system_", "validation_", "access_", "audit_", "backup_", "go_live_")):
            values[key] = f"模拟：{item['label']}待按真实发布执行填写。SIMULATED_TRIAL_NOT_REAL_RECORD"
        elif default:
            values[key] = default
        else:
            values[key] = f"模拟：{item['label']}待人工确认。SIMULATED_TRIAL_NOT_REAL_RECORD"
    return values


def render_template_doc(spec: dict[str, Any], schema: list[dict[str, Any]], values: dict[str, str]) -> str:
    field_rows = []
    for index, item in enumerate(schema, start=1):
        field_rows.append(
            {
                "order": index,
                "key": item["key"],
                "label": item["label"],
                "type": item["type"],
                "required": "yes" if item["required"] else "no",
                "trial_value": values.get(item["key"], ""),
                "review_focus": item["review_focus"],
            }
        )
    lines = [
        f"# {spec['template_code']} {spec['template_name']}",
        "",
        "文件状态：发布执行记录候选模板，不写数据库，不代表受控发布或真实记录形成。",
        "",
        "## 模板信息",
        "",
        f"- 适用条款：{spec['applicable_clauses']}",
        f"- 关联程序：{spec['related_procedures']}",
        f"- 填写责任：{spec['responsible_position']}",
        f"- 形成时机：{spec['trigger_time']}",
        f"- 来源演练文件：{spec['source_rehearsal_file']}",
        f"- 来源演练行数：{spec['source_rehearsal_rows']}",
        "",
        "## 字段与模拟试填",
        "",
    ]
    lines.extend(render_table(field_rows, ["order", "key", "label", "type", "required", "trial_value", "review_focus"]))
    lines.extend(["", "## 边界", ""])
    lines.extend(f"- {item}" for item in GUARDRAILS)
    lines.append("")
    return "\n".join(lines)


def render_overview(manifest: dict[str, Any], index_rows: list[dict[str, Any]]) -> str:
    counts = manifest["counts"]
    lines = [
        "# 发布执行记录模板包总览",
        "",
        "本包把受控发布治理演练中的审批、发布发放、培训、旧版处置、实施有效性检查和 jewelry-qms 试运行确认转成候选记录模板；不写数据库，不代表受控发布或真实记录形成。",
        "",
        "## 计数",
        "",
        f"- 候选记录模板：{counts['templates']}",
        f"- 字段总数：{counts['fields']}",
        f"- 模拟试填记录：{counts['trial_instances']}",
        f"- 来源发布对象：{counts['source_release_objects']}",
        f"- 来源审批项：{counts['source_approval_items']}",
        f"- 来源培训项：{counts['source_training_items']}",
        f"- 来源旧版处置项：{counts['source_obsolete_items']}",
        f"- 来源有效性检查项：{counts['source_effectiveness_items']}",
        "",
        "## 模板索引",
        "",
    ]
    lines.extend(render_table(index_rows, ["template_code", "template_name", "applicable_clauses", "source_rehearsal_file", "source_rehearsal_rows", "field_count", "markdown_file"]))
    lines.extend(["", "## 边界", ""])
    lines.extend(f"- {item}" for item in GUARDRAILS)
    lines.append("")
    return "\n".join(lines)


def build_pack(stage_dir: Path, release_dir: Path, output_dir: Path) -> dict[str, Any]:
    source_counts = {
        "release_objects": len(read_csv(release_dir / "01-发布对象清单.csv")),
        "approval_items": len(read_csv(release_dir / "02-审批签核演练清单.csv")),
        "training_items": len(read_csv(release_dir / "03-培训宣贯演练清单.csv")),
        "obsolete_items": len(read_csv(release_dir / "04-旧版处置演练清单.csv")),
        "effectiveness_items": len(read_csv(release_dir / "07-实施有效性检查清单.csv")),
    }
    specs = build_template_specs(source_counts)
    output_dir.mkdir(parents=True, exist_ok=True)
    template_dir = output_dir / "templates"
    template_dir.mkdir(parents=True, exist_ok=True)

    index_rows: list[dict[str, Any]] = []
    detail_rows: list[dict[str, Any]] = []
    trial_rows: list[dict[str, Any]] = []
    trial_json: list[dict[str, Any]] = []

    for spec in specs:
        schema = schema_for_template(spec)
        values = trial_values(spec, schema)
        markdown_file = f"templates/{safe_filename(spec['template_code'] + '-' + spec['template_name'])}.md"
        (output_dir / markdown_file).write_text(render_template_doc(spec, schema, values), encoding="utf-8")
        index_rows.append(
            {
                "template_code": spec["template_code"],
                "template_name": spec["template_name"],
                "applicable_clauses": spec["applicable_clauses"],
                "related_procedures": spec["related_procedures"],
                "responsible_position": spec["responsible_position"],
                "trigger_time": spec["trigger_time"],
                "source_rehearsal_file": spec["source_rehearsal_file"],
                "source_rehearsal_rows": spec["source_rehearsal_rows"],
                "field_count": len(schema),
                "required_field_count": sum(1 for item in schema if item["required"]),
                "review_status": "pending_human_review",
                "markdown_file": markdown_file,
            }
        )
        for order, item in enumerate(schema, start=1):
            detail_rows.append(
                {
                    "template_code": spec["template_code"],
                    "template_name": spec["template_name"],
                    "field_order": order,
                    "field_key": item["key"],
                    "field_label": item["label"],
                    "field_type": item["type"],
                    "required": "yes" if item["required"] else "no",
                    "field_group": "common" if order <= len(COMMON_FIELDS) else "specific",
                    "default_value": item.get("default", ""),
                    "review_focus": item["review_focus"],
                }
            )
        trial_row = {
            "template_code": spec["template_code"],
            "template_name": spec["template_name"],
            "record_number": values["record_number"],
            "field_values_json": json.dumps(values, ensure_ascii=False),
            "markdown_file": markdown_file,
            "not_real_record": "yes",
        }
        trial_rows.append(trial_row)
        trial_json.append({"template": spec, "field_values": values, "markdown_file": markdown_file})

    files = {
        "overview": "00-发布执行记录模板总览.md",
        "template_index": "01-发布执行记录模板索引.csv",
        "field_detail": "02-发布执行字段明细.csv",
        "trial_csv": "03-发布执行模拟试填.csv",
        "trial_json": "03-发布执行模拟试填.json",
        "manifest": "release_execution_template_manifest.json",
        "readme": "README.md",
    }
    write_csv(output_dir / files["template_index"], index_rows, TEMPLATE_INDEX_FIELDS)
    write_csv(output_dir / files["field_detail"], detail_rows, FIELD_DETAIL_FIELDS)
    write_csv(output_dir / files["trial_csv"], trial_rows, TRIAL_FIELDS)
    (output_dir / files["trial_json"]).write_text(json.dumps(trial_json, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")

    generated_at = dt.datetime.now().isoformat(timespec="seconds")
    manifest = {
        "generated_at": generated_at,
        "stage_dir": str(stage_dir),
        "release_rehearsal_dir": str(release_dir),
        "release_execution_template_dir": str(output_dir),
        "status": "release_execution_templates_no_database_write",
        "guardrails": GUARDRAILS,
        "counts": {
            "templates": len(index_rows),
            "fields": len(detail_rows),
            "trial_instances": len(trial_rows),
            "template_markdown_files": len(list(template_dir.glob("*.md"))),
            "source_release_objects": source_counts["release_objects"],
            "source_approval_items": source_counts["approval_items"],
            "source_training_items": source_counts["training_items"],
            "source_obsolete_items": source_counts["obsolete_items"],
            "source_effectiveness_items": source_counts["effectiveness_items"],
        },
        "files": files,
    }
    (output_dir / files["manifest"]).write_text(json.dumps(manifest, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
    (output_dir / files["overview"]).write_text(render_overview(manifest, index_rows), encoding="utf-8")
    readme_lines = [
        "# 发布执行记录模板包",
        "",
        "文件状态：候选记录模板包，不写数据库，不代表受控发布或真实记录形成。",
        "",
        "## 使用顺序",
        "",
        "1. 先看 `00-发布执行记录模板总览.md`。",
        "2. 再逐项查看 `templates/` 中 6 个候选模板。",
        "3. 用 `02-发布执行字段明细.csv` 逐字段确认字段含义、保存期限、保密等级和签核要求。",
        "4. 用 `03-发布执行模拟试填.csv/json` 验证字段可填性；其中所有数据均为模拟数据。",
        "",
        "## 边界",
        "",
    ]
    readme_lines.extend(f"- {item}" for item in GUARDRAILS)
    readme_lines.append("")
    (output_dir / files["readme"]).write_text("\n".join(readme_lines), encoding="utf-8")
    return manifest


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--stage-dir", default=str(DEFAULT_STAGE_DIR))
    parser.add_argument("--release-dir")
    parser.add_argument("--output-dir")
    args = parser.parse_args()

    stage_dir = Path(args.stage_dir)
    release_dir = Path(args.release_dir) if args.release_dir else stage_dir / "controlled_release_rehearsal"
    output_dir = Path(args.output_dir) if args.output_dir else stage_dir / "release_execution_template_pack"
    manifest = build_pack(stage_dir, release_dir, output_dir)
    print(json.dumps(manifest, ensure_ascii=False, indent=2))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
