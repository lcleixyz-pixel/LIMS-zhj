#!/usr/bin/env python3
"""Build simulated trial-fill records from the QMS record template pre-import CSV."""

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
SELECTED_TEMPLATE_CODES = ["JL-4.1-01", "JL-7.1-01", "JL-8.8-01"]
MARKER = "SIMULATED_TRIAL_NOT_REAL_RECORD"


def read_csv(path: Path) -> list[dict[str, str]]:
    with path.open("r", encoding="utf-8", newline="") as handle:
        return list(csv.DictReader(handle))


def write_csv(path: Path, rows: list[dict[str, Any]], fieldnames: list[str]) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    with path.open("w", encoding="utf-8", newline="") as handle:
        writer = csv.DictWriter(handle, fieldnames=fieldnames, extrasaction="ignore")
        writer.writeheader()
        for row in rows:
            writer.writerow({key: row.get(key, "") for key in fieldnames})


def safe_filename(text: str) -> str:
    text = re.sub(r"[\\/:*?\"<>|]", "", text)
    text = re.sub(r"\s+", "", text)
    return text or "record"


def specific_value(doc_number: str, label: str) -> str:
    examples = {
        "JL-4.1-01": {
            "风险来源": "模拟：客户与检测人员存在熟人关系，真实运行时应按公正性风险准则核实。",
            "影响活动": "模拟：样品受理、检测安排、结果复核。",
            "风险等级": "模拟：中，待质量负责人按真实风险准则确认。",
            "措施": "模拟：相关人员回避、增加独立复核、记录客户沟通。",
            "责任人": "模拟：质量负责人/相关岗位待确认。",
            "有效性评价": "模拟：待一个运行周期后评价；本记录仅验证字段可填性。",
        },
        "JL-7.1-01": {
            "客户要求": "模拟：客户要求出具珠宝玉石检测报告，报告内容和交付时限待真实委托确认。",
            "方法": "模拟：采用现行有效标准方法，具体标准编号待业务记录确认。",
            "能力": "模拟：人员、设备、环境和授权范围待真实受理时核查。",
            "资源": "模拟：检测人员、仪器、复核人员和报告授权人待排班确认。",
            "时限": "模拟：按约定周期完成；如变更应重新评审并通知客户。",
            "符合性声明": "模拟：如需符合性声明，应先约定技术规范和决策规则。",
            "变更": "模拟：合同变更时重新评审并传达相关岗位。",
        },
        "JL-8.8-01": {
            "审核范围": "模拟：覆盖质量手册 4.1 至 8.9 相关过程，真实范围由年度内审方案确认。",
            "准则": "模拟：CNAS-CL01、CMA 现行评审准则、质量手册和程序文件。",
            "发现": "模拟：发现记录字段保存期限待确认，需由文件管理员补充。",
            "不符合": "模拟：暂按观察项处理；真实内审应按证据判定不符合等级。",
            "整改": "模拟：补充保存期限、复核职责和电子记录更正规则。",
            "验证": "模拟：整改完成后由内审员复核；本记录仅验证模板字段可承接闭环。",
        },
    }
    return examples.get(doc_number, {}).get(label, f"模拟：{label}待按真实业务填写。")


def field_values(template: dict[str, str], schema: list[dict[str, Any]]) -> dict[str, str]:
    doc_number = template["doc_number"]
    common = {
        "record_number": f"SIM-{doc_number}-20260707-001",
        "record_name": template["name"],
        "applicable_clause": template.get("applicable_clauses", ""),
        "related_procedure": template.get("procedure_doc_numbers", ""),
        "responsible_position": template.get("responsible_position", ""),
        "trigger_time": template.get("trigger_time", ""),
        "reviewer": "待人工确认",
        "storage_location": "待人工确认",
        "retention_period": "待人工确认",
        "confidentiality_level": "待确认",
        "correction_rule": (
            "保留原始信息、更正原因、更正日期、责任人和复核痕迹；"
            f"本试填为模拟数据，不作为正式更正规则。{MARKER}"
        ),
    }
    values: dict[str, str] = {}
    for item in schema:
        key = str(item.get("key", "")).strip()
        label = str(item.get("label", "")).strip()
        if not key:
            continue
        if key in common:
            values[key] = common[key]
        else:
            values[key] = specific_value(doc_number, label)
    return values


def render_markdown(instance: dict[str, Any], schema: list[dict[str, Any]], values: dict[str, str]) -> str:
    lines = [
        f"# {instance['template_code']} {instance['template_name']} 试填",
        "",
        f"文件状态：模拟试填草案  ",
        f"生成时间：{instance['generated_at']}  ",
        f"标识：`{MARKER}`",
        "",
        "> 模拟试填，不作为真实运行记录，不得导入生产库，不得作为受控记录。",
        "",
        "## 基本信息",
        "",
        f"- 模板编号：{instance['template_code']}",
        f"- 模板名称：{instance['template_name']}",
        f"- 适用条款：{instance['applicable_clauses']}",
        f"- 关联程序：{instance['procedure_doc_numbers']}",
        f"- 填写责任：{instance['responsible_position']}",
        f"- 形成时机：{instance['trigger_time']}",
        "",
        "## 字段试填",
        "",
        "| 字段 | 试填内容 | 必填 | 备注 |",
        "|---|---|---|---|",
    ]
    for item in schema:
        key = str(item.get("key", "")).strip()
        label = str(item.get("label", key)).strip()
        required = "是" if item.get("required") else "否"
        note = str(item.get("note", "")).strip()
        value = values.get(key, "")
        lines.append(f"| {label} | {value} | {required} | {note} |")
    lines.extend(
        [
            "",
            "## 试填结论",
            "",
            "- 字段可以形成一条模拟记录，但保存位置、保存期限、保密等级仍需人工确认。",
            "- 该记录只用于验证模板字段和 LIMS 治理链路，不证明发生过真实活动。",
            "- 后续如要导入 LIMS，应先通过人工评审，并使用带 `--dry-run / --apply` 闸门的导入命令。",
            "",
        ]
    )
    return "\n".join(lines)


def selected_template_codes(template_rows: list[dict[str, str]], include_all: bool, requested_codes: str | None) -> list[str]:
    available_codes = [row["doc_number"] for row in template_rows]
    if include_all:
        return available_codes
    if requested_codes:
        return [code.strip() for code in re.split(r"[,，;；\s]+", requested_codes) if code.strip()]
    return SELECTED_TEMPLATE_CODES


def build_trial_pack(
    stage_dir: Path,
    output_dir: Path,
    include_all_templates: bool = False,
    requested_template_codes: str | None = None,
) -> dict[str, Any]:
    preimport_dir = stage_dir / "lims_preimport_package"
    template_rows = read_csv(preimport_dir / "record_form_templates_preimport.csv")
    templates = {row["doc_number"]: row for row in template_rows}
    planned_codes = selected_template_codes(template_rows, include_all_templates, requested_template_codes)
    missing = [code for code in planned_codes if code not in templates]
    if missing:
        raise SystemExit("缺少记录模板：" + "、".join(missing))

    output_dir.mkdir(parents=True, exist_ok=True)
    generated_at = dt.datetime.now().isoformat(timespec="seconds")
    instances: list[dict[str, Any]] = []
    json_instances: list[dict[str, Any]] = []

    for code in planned_codes:
        template = templates[code]
        schema = json.loads(template["field_schema_json"])
        values = field_values(template, schema)
        filename = f"{code}-{safe_filename(template['name'])}-试填.md"
        instance = {
            "trial_marker": MARKER,
            "generated_at": generated_at,
            "source_template_csv": "lims_preimport_package/record_form_templates_preimport.csv",
            "template_code": code,
            "template_name": template["name"],
            "status": "draft",
            "review_status": "trial_only_pending_human_review",
            "applicable_clauses": template.get("applicable_clauses", ""),
            "procedure_doc_numbers": template.get("procedure_doc_numbers", ""),
            "responsible_position": template.get("responsible_position", ""),
            "trigger_time": template.get("trigger_time", ""),
            "field_values_json": json.dumps(values, ensure_ascii=False, separators=(",", ":")),
            "missing_human_inputs": "保存位置；保存期限；保密等级；现用表单一致性；责任岗位签核规则",
            "markdown_file": filename,
            "import_allowed": "no",
            "review_note": "模拟试填，不作为真实运行记录，不得导入生产库，不得作为受控记录。",
        }
        instances.append(instance)
        json_instances.append({**instance, "field_values": values, "field_schema": schema})
        (output_dir / filename).write_text(render_markdown(instance, schema, values), encoding="utf-8")

    write_csv(
        output_dir / "record_form_instances_trial.csv",
        instances,
        [
            "trial_marker",
            "generated_at",
            "source_template_csv",
            "template_code",
            "template_name",
            "status",
            "review_status",
            "applicable_clauses",
            "procedure_doc_numbers",
            "responsible_position",
            "trigger_time",
            "field_values_json",
            "missing_human_inputs",
            "markdown_file",
            "import_allowed",
            "review_note",
        ],
    )
    (output_dir / "record_form_instances_trial.json").write_text(
        json.dumps(json_instances, ensure_ascii=False, indent=2) + "\n",
        encoding="utf-8",
    )

    manifest = {
        "generated_at": generated_at,
        "stage_dir": str(stage_dir),
        "preimport_dir": str(preimport_dir),
        "trial_dir": str(output_dir),
        "trial_marker": MARKER,
        "status": "trial_only",
        "selection_mode": "all_templates" if include_all_templates else "selected_templates",
        "selected_template_codes": planned_codes,
        "source_template_count": len(template_rows),
        "counts": {"trial_instances": len(instances), "markdown_files": len(instances)},
        "files": {
            "instances_csv": "record_form_instances_trial.csv",
            "instances_json": "record_form_instances_trial.json",
            "markdown_files": [row["markdown_file"] for row in instances],
        },
        "guardrails": [
            "不写入 LIMS 数据库",
            "不作为真实运行记录",
            "不作为受控记录发布",
            "仅用于记录模板字段和治理链路试填验证",
        ],
    }
    (output_dir / "trial_manifest.json").write_text(
        json.dumps(manifest, ensure_ascii=False, indent=2) + "\n",
        encoding="utf-8",
    )
    (output_dir / "README.md").write_text(render_readme(manifest), encoding="utf-8")
    return manifest


def render_readme(manifest: dict[str, Any]) -> str:
    lines = [
        "# 记录模板模拟试填包",
        "",
        f"生成时间：{manifest['generated_at']}  ",
        f"状态：{manifest['status']}  ",
        f"标识：`{manifest['trial_marker']}`",
        "",
        "本目录只用于验证候选记录模板是否能被字段化填写，不是正式运行记录，不得导入生产库，不得作为受控记录。",
        "",
        "## 文件",
        "",
        "- `record_form_instances_trial.csv`：模拟试填实例清单。",
        "- `record_form_instances_trial.json`：模拟试填实例和字段 schema。",
        "- `trial_manifest.json`：本试填包清单和边界。",
    ]
    for filename in manifest["files"]["markdown_files"]:
        lines.append(f"- `{filename}`：单个记录模板模拟试填表。")
    lines.extend(
        [
            "",
            "## 下一步",
            "",
            "由文件管理员、质量负责人和对应过程负责人核对字段是否符合现用表单和真实运行；确认后再决定是否修订 `record_form_templates_preimport.csv`。",
            "",
        ]
    )
    return "\n".join(lines)


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--stage-dir", default=str(DEFAULT_STAGE_DIR))
    parser.add_argument("--output-dir")
    parser.add_argument("--all-templates", action="store_true", help="为预导入包中的全部记录模板生成模拟试填。")
    parser.add_argument("--template-codes", help="逗号/分号/空格分隔的记录模板编号；未提供时使用代表性 3 个模板。")
    args = parser.parse_args()

    stage_dir = Path(args.stage_dir)
    output_dir = Path(args.output_dir) if args.output_dir else stage_dir / "record_template_trial_pack"
    manifest = build_trial_pack(stage_dir, output_dir, args.all_templates, args.template_codes)
    print(json.dumps(manifest, ensure_ascii=False, indent=2))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
