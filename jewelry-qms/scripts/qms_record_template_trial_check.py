#!/usr/bin/env python3
"""Dry-run validation for the simulated QMS record template trial-fill package."""

from __future__ import annotations

import argparse
import csv
import datetime as dt
import json
import re
from pathlib import Path
from typing import Any


MARKER = "SIMULATED_TRIAL_NOT_REAL_RECORD"
REQUIRED_VALUE_KEYS = {
    "record_number",
    "record_name",
    "applicable_clause",
    "related_procedure",
    "responsible_position",
    "trigger_time",
    "correction_rule",
}


def read_text(path: Path) -> str:
    return path.read_text(encoding="utf-8")


def read_csv(path: Path) -> list[dict[str, str]]:
    with path.open("r", encoding="utf-8", newline="") as handle:
        return list(csv.DictReader(handle))


def fail(findings: list[dict[str, str]], rule_id: str, message: str, severity: str = "high") -> None:
    findings.append({"id": rule_id, "severity": severity, "message": message})


def load_template_schema(preimport_dir: Path, selected_template_codes: set[str]) -> dict[str, list[dict[str, Any]]]:
    rows = read_csv(preimport_dir / "record_form_templates_preimport.csv")
    result: dict[str, list[dict[str, Any]]] = {}
    for row in rows:
        if row.get("doc_number") in selected_template_codes:
            result[row["doc_number"]] = json.loads(row["field_schema_json"])
    return result


def check_trial_pack(trial_dir: Path, preimport_dir: Path) -> dict[str, Any]:
    findings: list[dict[str, str]] = []
    manifest_path = trial_dir / "trial_manifest.json"
    csv_path = trial_dir / "record_form_instances_trial.csv"
    json_path = trial_dir / "record_form_instances_trial.json"

    if not manifest_path.exists():
        return {
            "generated_at": dt.datetime.now().isoformat(timespec="seconds"),
            "trial_dir": str(trial_dir),
            "status": "failed",
            "findings": [{"id": "missing_manifest", "severity": "high", "message": "缺少 trial_manifest.json"}],
        }
    manifest = json.loads(read_text(manifest_path))

    if manifest.get("trial_marker") != MARKER:
        fail(findings, "manifest_missing_marker", "trial_manifest.json 未包含模拟试填标识。")
    if manifest.get("status") != "trial_only":
        fail(findings, "manifest_status_not_trial_only", "试填包状态必须为 trial_only。")
    selected_template_codes = {str(code) for code in manifest.get("selected_template_codes", [])}
    if not selected_template_codes:
        fail(findings, "selected_template_codes_empty", "试填包未声明 selected_template_codes。")

    if not csv_path.exists():
        fail(findings, "missing_instances_csv", "缺少 record_form_instances_trial.csv。")
        instance_rows: list[dict[str, str]] = []
    else:
        instance_rows = read_csv(csv_path)

    if not json_path.exists():
        fail(findings, "missing_instances_json", "缺少 record_form_instances_trial.json。")
        json_instances: list[dict[str, Any]] = []
    else:
        json_instances = json.loads(read_text(json_path))

    expected_count = len(selected_template_codes)
    if len(instance_rows) != expected_count:
        fail(findings, "trial_instance_count", f"CSV 试填实例数量应为 {expected_count}，实际为 {len(instance_rows)}。")
    if len(json_instances) != expected_count:
        fail(findings, "trial_json_count", f"JSON 试填实例数量应为 {expected_count}，实际为 {len(json_instances)}。")

    template_schemas = load_template_schema(preimport_dir, selected_template_codes)
    missing_templates = sorted(selected_template_codes - set(template_schemas))
    if missing_templates:
        fail(findings, "missing_source_templates", "预导入模板缺少：" + "、".join(missing_templates))

    seen_codes: set[str] = set()
    for index, row in enumerate(instance_rows, start=2):
        code = row.get("template_code", "")
        seen_codes.add(code)
        if code not in selected_template_codes:
            fail(findings, "unexpected_template_code", f"CSV 第 {index} 行出现非计划模板：{code}")
        if row.get("trial_marker") != MARKER:
            fail(findings, "row_missing_marker", f"{code} 未包含模拟试填标识。")
        if row.get("status") != "draft":
            fail(findings, "row_status_not_draft", f"{code} 状态必须为 draft。")
        if row.get("import_allowed") != "no":
            fail(findings, "row_import_allowed", f"{code} 必须标明 import_allowed=no。")
        if "不得导入生产库" not in row.get("review_note", ""):
            fail(findings, "row_missing_no_import_warning", f"{code} 未提示不得导入生产库。", "medium")
        try:
            values = json.loads(row.get("field_values_json", ""))
        except json.JSONDecodeError as exc:
            fail(findings, "invalid_field_values_json", f"{code} field_values_json 非法：{exc}")
            continue
        missing_values = [key for key in REQUIRED_VALUE_KEYS if not values.get(key)]
        if missing_values:
            fail(findings, "missing_required_trial_values", f"{code} 缺少必填试填值：" + "、".join(missing_values))
        if MARKER not in json.dumps(values, ensure_ascii=False):
            fail(findings, "values_missing_marker", f"{code} 字段值未包含模拟试填标识。")
        source_schema = template_schemas.get(code, [])
        source_keys = {str(item.get("key", "")) for item in source_schema}
        missing_schema_values = sorted(key for key in source_keys if key and key not in values)
        if missing_schema_values:
            fail(findings, "trial_values_do_not_cover_schema", f"{code} 试填值未覆盖字段：" + "、".join(missing_schema_values))
        md_file = row.get("markdown_file", "")
        if not md_file or not (trial_dir / md_file).exists():
            fail(findings, "missing_markdown_trial_file", f"{code} 缺少 Markdown 试填文件。")
        else:
            md_text = read_text(trial_dir / md_file)
            if MARKER not in md_text or "不得导入生产库" not in md_text or "不得作为受控记录" not in md_text:
                fail(findings, "markdown_missing_guardrail", f"{md_file} 缺少模拟/禁导入/非受控提示。")

    missing_rows = sorted(selected_template_codes - seen_codes)
    if missing_rows:
        fail(findings, "missing_trial_rows", "CSV 缺少试填行：" + "、".join(missing_rows))

    for path in list(trial_dir.glob("*.sql")) + list(trial_dir.glob("*.db")):
        fail(findings, "forbidden_database_artifact", f"试填包不应包含数据库/SQL 文件：{path.name}")

    all_text = "\n".join(path.read_text(encoding="utf-8") for path in trial_dir.glob("*.md"))
    positive_overstatement_patterns = [
        r"本记录为正式运行记录",
        r"真实活动已经实施",
        r"已完成内审",
        r"已实施整改",
        r"已批准发布",
    ]
    if any(re.search(pattern, all_text) for pattern in positive_overstatement_patterns):
        fail(findings, "trial_overstates_real_activity", "Markdown 中疑似把模拟试填写成真实活动。")

    status = "passed" if not findings else "failed"
    return {
        "generated_at": dt.datetime.now().isoformat(timespec="seconds"),
        "trial_dir": str(trial_dir),
        "preimport_dir": str(preimport_dir),
        "status": status,
        "counts": {
            "trial_instances": len(instance_rows),
            "json_instances": len(json_instances),
            "markdown_files": len(list(trial_dir.glob("JL-*-试填.md"))),
            "selected_template_codes": expected_count,
            "findings": len(findings),
        },
        "findings": findings,
    }


def render_markdown(result: dict[str, Any]) -> str:
    lines = [
        "# 记录模板试填 dry-run 验证报告",
        "",
        f"生成时间：{result['generated_at']}",
        f"验证对象：`{result['trial_dir']}`",
        f"结论：{result['status']}",
        "",
        "## 计数",
        "",
    ]
    for key, value in result.get("counts", {}).items():
        lines.append(f"- {key}: {value}")
    lines.append("")
    if result.get("findings"):
        lines.extend(["## 发现项", ""])
        for item in result["findings"]:
            lines.append(f"- [{item['severity']}] {item['id']}：{item['message']}")
    else:
        lines.extend(
            [
                "## 发现项",
                "",
                f"未发现阻断性问题。该结论仅证明 {result.get('counts', {}).get('selected_template_codes')} 个记录模板可以形成模拟试填实例，且已保持模拟、禁导入、非受控记录边界；不代表真实记录已经形成。",
            ]
        )
    lines.append("")
    return "\n".join(lines)


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--trial-dir", required=True)
    parser.add_argument("--preimport-dir", required=True)
    parser.add_argument("--json-out")
    parser.add_argument("--md-out")
    args = parser.parse_args()

    result = check_trial_pack(Path(args.trial_dir), Path(args.preimport_dir))
    if args.json_out:
        Path(args.json_out).write_text(json.dumps(result, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
    if args.md_out:
        Path(args.md_out).write_text(render_markdown(result), encoding="utf-8")
    print(json.dumps(result, ensure_ascii=False, indent=2))
    return 0 if result["status"] == "passed" else 1


if __name__ == "__main__":
    raise SystemExit(main())
