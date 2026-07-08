#!/usr/bin/env python3
"""Dry-run validation for the QMS record-template field catalog."""

from __future__ import annotations

import argparse
import csv
import datetime as dt
import json
import re
from pathlib import Path
from typing import Any


REQUIRED_FILES = {
    "overview": "00-字段字典总览.md",
    "template_index": "01-模板字段索引.csv",
    "field_detail": "02-字段级明细.csv",
    "common_matrix": "03-通用字段覆盖矩阵.md",
    "manifest": "field_catalog_manifest.json",
    "readme": "README.md",
}

COMMON_FIELD_KEYS = {
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
}

REQUIRED_BOUNDARY_MARKERS = [
    "不写数据库",
    "不代表受控发布",
    "不代表真实记录",
]


def read_text(path: Path) -> str:
    return path.read_text(encoding="utf-8")


def read_csv(path: Path) -> list[dict[str, str]]:
    with path.open("r", encoding="utf-8", newline="") as handle:
        return list(csv.DictReader(handle))


def fail(findings: list[dict[str, str]], rule_id: str, message: str, severity: str = "high") -> None:
    findings.append({"id": rule_id, "severity": severity, "message": message})


def source_template_field_counts(preimport_dir: Path, findings: list[dict[str, str]]) -> dict[str, int]:
    path = preimport_dir / "record_form_templates_preimport.csv"
    if not path.exists():
        fail(findings, "missing_source_templates", f"缺少源模板文件：{path}")
        return {}
    result: dict[str, int] = {}
    for row in read_csv(path):
        code = row.get("doc_number", "")
        try:
            schema = json.loads(row.get("field_schema_json", "[]"))
        except json.JSONDecodeError as exc:
            fail(findings, "invalid_source_field_schema", f"{code} field_schema_json 非法：{exc}")
            schema = []
        result[code] = len(schema)
    return result


def trial_template_codes(trial_dir: Path) -> set[str]:
    path = trial_dir / "record_form_instances_trial.csv"
    if not path.exists():
        return set()
    return {row.get("template_code", "") for row in read_csv(path)}


def check_catalog(catalog_dir: Path, preimport_dir: Path | None = None, trial_dir: Path | None = None) -> dict[str, Any]:
    findings: list[dict[str, str]] = []
    manifest_path = catalog_dir / "field_catalog_manifest.json"
    if not manifest_path.exists():
        return {
            "generated_at": dt.datetime.now().isoformat(timespec="seconds"),
            "catalog_dir": str(catalog_dir),
            "status": "failed",
            "findings": [{"id": "missing_manifest", "severity": "high", "message": "缺少 field_catalog_manifest.json"}],
        }

    manifest = json.loads(read_text(manifest_path))
    if manifest.get("status") != "field_catalog_no_database_write":
        fail(findings, "invalid_manifest_status", "field_catalog_manifest.json 状态必须为 field_catalog_no_database_write。")

    preimport_dir = preimport_dir or Path(str(manifest.get("preimport_dir", "")))
    trial_dir = trial_dir or Path(str(manifest.get("trial_dir", "")))

    boundary_text = "\n".join(str(item) for item in manifest.get("guardrails", []))
    for marker in REQUIRED_BOUNDARY_MARKERS:
        if marker not in boundary_text:
            fail(findings, "manifest_missing_guardrail", f"manifest guardrails 缺少标识：{marker}")

    files = manifest.get("files", {})
    for key, default_filename in REQUIRED_FILES.items():
        filename = files.get(key, default_filename)
        if not (catalog_dir / filename).exists():
            fail(findings, "missing_" + key, f"缺少字段字典文件：{filename}")

    for path in list(catalog_dir.glob("*.sql")) + list(catalog_dir.glob("*.db")) + list((catalog_dir / "templates").glob("*.sql")) + list((catalog_dir / "templates").glob("*.db")):
        fail(findings, "forbidden_database_artifact", f"字段字典包不应包含数据库/SQL 文件：{path.name}")

    index_rows = read_csv(catalog_dir / files.get("template_index", "01-模板字段索引.csv")) if (catalog_dir / files.get("template_index", "01-模板字段索引.csv")).exists() else []
    detail_rows = read_csv(catalog_dir / files.get("field_detail", "02-字段级明细.csv")) if (catalog_dir / files.get("field_detail", "02-字段级明细.csv")).exists() else []

    if len(index_rows) != 26:
        fail(findings, "template_count_mismatch", f"模板索引应为 26 行，实际 {len(index_rows)}。")
    template_codes = [row.get("template_code", "") for row in index_rows]
    if len(template_codes) != len(set(template_codes)):
        fail(findings, "duplicate_template_code", "模板索引存在重复 template_code。")

    by_template: dict[str, list[dict[str, str]]] = {}
    for row in detail_rows:
        by_template.setdefault(row.get("template_code", ""), []).append(row)
    source_counts = source_template_field_counts(preimport_dir, findings) if preimport_dir else {}
    for row in index_rows:
        code = row.get("template_code", "")
        expected_fields = source_counts.get(code)
        actual_fields = len(by_template.get(code, []))
        if expected_fields is not None and actual_fields != expected_fields:
            fail(findings, "field_count_source_mismatch", f"{code} 字段数与源 schema 不一致：catalog={actual_fields}, source={expected_fields}")
        try:
            indexed_count = int(row.get("field_count", "-1"))
        except ValueError:
            indexed_count = -1
        if indexed_count != actual_fields:
            fail(findings, "field_count_index_mismatch", f"{code} 字段数与索引不一致：index={indexed_count}, detail={actual_fields}")
        keys = {field.get("field_key", "") for field in by_template.get(code, [])}
        missing_common = sorted(COMMON_FIELD_KEYS - keys)
        if missing_common:
            fail(findings, "missing_common_fields", f"{code} 缺少通用字段：" + "、".join(missing_common))
        template_doc = catalog_dir / row.get("catalog_markdown_file", "")
        if not template_doc.exists():
            fail(findings, "missing_template_doc", f"{code} 缺少逐模板字段字典：{row.get('catalog_markdown_file', '')}")
        else:
            text = read_text(template_doc)
            for marker in ["不写数据库", "不代表受控发布", "不代表真实记录"]:
                if marker not in text:
                    fail(findings, "template_doc_missing_guardrail", f"{template_doc.name} 缺少边界标识：{marker}")

    seen_field_keys: set[tuple[str, str]] = set()
    for index, row in enumerate(detail_rows, start=2):
        code = row.get("template_code", "")
        key = row.get("field_key", "")
        if not code or not key:
            fail(findings, "blank_field_identity", f"字段明细第 {index} 行 template_code 或 field_key 为空。")
        identity = (code, key)
        if identity in seen_field_keys:
            fail(findings, "duplicate_field_key", f"{code} 存在重复字段：{key}")
        seen_field_keys.add(identity)
        if row.get("required") not in {"yes", "no"}:
            fail(findings, "invalid_required_flag", f"{code}/{key} required 必须为 yes/no。")
        if row.get("trial_value_present") != "yes":
            fail(findings, "trial_value_missing", f"{code}/{key} 没有被全量试填值覆盖。")
        if row.get("field_group") not in {"common", "specific", "extended"}:
            fail(findings, "invalid_field_group", f"{code}/{key} field_group 非法：{row.get('field_group')}")

    trial_codes = trial_template_codes(trial_dir) if trial_dir else set()
    if trial_codes and set(template_codes) != trial_codes:
        fail(
            findings,
            "trial_catalog_template_mismatch",
            "字段字典模板与全量试填模板不一致："
            + "catalog_missing=" + "、".join(sorted(trial_codes - set(template_codes)))
            + "；trial_missing=" + "、".join(sorted(set(template_codes) - trial_codes)),
        )

    counts = manifest.get("counts", {})
    if counts.get("record_templates") != len(index_rows):
        fail(findings, "manifest_template_count_mismatch", f"manifest record_templates 应为 {len(index_rows)}，实际 {counts.get('record_templates')}。")
    if counts.get("fields") != len(detail_rows):
        fail(findings, "manifest_field_count_mismatch", f"manifest fields 应为 {len(detail_rows)}，实际 {counts.get('fields')}。")

    for filename in [files.get("overview", "00-字段字典总览.md"), files.get("common_matrix", "03-通用字段覆盖矩阵.md"), files.get("readme", "README.md")]:
        path = catalog_dir / filename
        if not path.exists():
            continue
        text = read_text(path)
        for marker in ["不写数据库", "不代表受控发布"]:
            if marker not in text:
                fail(findings, "catalog_doc_missing_guardrail", f"{filename} 缺少边界标识：{marker}")
        if re.search(r"已批准发布|可以写库|准许写库|正式运行记录", text):
            fail(findings, "catalog_doc_overstates_status", f"{filename} 疑似包含越权状态表述。")

    status = "passed" if not findings else "failed"
    return {
        "generated_at": dt.datetime.now().isoformat(timespec="seconds"),
        "catalog_dir": str(catalog_dir),
        "preimport_dir": str(preimport_dir) if preimport_dir else None,
        "trial_dir": str(trial_dir) if trial_dir else None,
        "status": status,
        "counts": {
            "record_templates": len(index_rows),
            "field_detail_rows": len(detail_rows),
            "human_confirmation_fields": sum(1 for row in detail_rows if row.get("human_confirmation_required") == "yes"),
            "template_markdown_files": len(list((catalog_dir / "templates").glob("*.md"))),
            "findings": len(findings),
        },
        "findings": findings,
    }


def render_markdown(result: dict[str, Any]) -> str:
    lines = [
        "# 记录模板字段字典 dry-run 验证报告",
        "",
        f"生成时间：{result['generated_at']}",
        f"验证对象：`{result['catalog_dir']}`",
        f"结论：{result['status']}",
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
        lines.append("未发现阻断性问题。该结论仅证明字段字典与预导入 schema、全量试填包一致，且保持不写库/非受控发布边界；不代表模板已经人工批准。")
    lines.append("")
    return "\n".join(lines)


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--catalog-dir", required=True)
    parser.add_argument("--preimport-dir")
    parser.add_argument("--trial-dir")
    parser.add_argument("--json-out")
    parser.add_argument("--md-out")
    args = parser.parse_args()

    result = check_catalog(
        Path(args.catalog_dir),
        Path(args.preimport_dir) if args.preimport_dir else None,
        Path(args.trial_dir) if args.trial_dir else None,
    )
    print(json.dumps(result, ensure_ascii=False, indent=2))
    if args.json_out:
        Path(args.json_out).write_text(json.dumps(result, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
    if args.md_out:
        Path(args.md_out).write_text(render_markdown(result), encoding="utf-8")
    return 0 if result["status"] == "passed" else 1


if __name__ == "__main__":
    raise SystemExit(main())
