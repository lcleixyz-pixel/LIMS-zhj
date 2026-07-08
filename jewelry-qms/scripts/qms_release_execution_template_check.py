#!/usr/bin/env python3
"""Dry-run validation for controlled-release execution record templates."""

from __future__ import annotations

import argparse
import csv
import datetime as dt
import json
import re
from pathlib import Path
from typing import Any


REQUIRED_FILES = {
    "overview": "00-发布执行记录模板总览.md",
    "template_index": "01-发布执行记录模板索引.csv",
    "field_detail": "02-发布执行字段明细.csv",
    "trial_csv": "03-发布执行模拟试填.csv",
    "trial_json": "03-发布执行模拟试填.json",
    "manifest": "release_execution_template_manifest.json",
    "readme": "README.md",
}

EXPECTED_TEMPLATE_CODES = {
    "JL-REL-01",
    "JL-REL-02",
    "JL-REL-03",
    "JL-REL-04",
    "JL-REL-05",
    "JL-REL-06",
}

COMMON_FIELD_KEYS = {
    "record_number",
    "record_name",
    "applicable_clause",
    "related_procedure",
    "responsible_position",
    "trigger_time",
    "reviewer",
    "approval_status",
    "evidence_reference",
    "storage_location",
    "retention_period",
    "confidentiality_level",
    "correction_rule",
    "not_real_record_marker",
}

REQUIRED_GUARDRAILS = [
    "不写数据库",
    "不代表第五版候选稿",
    "SIMULATED_TRIAL_NOT_REAL_RECORD",
    "已取得 CMA",
    "CNAS 申请中",
    "jewelry-qms 仍为建设中系统",
]


def read_text(path: Path) -> str:
    return path.read_text(encoding="utf-8")


def read_csv(path: Path) -> list[dict[str, str]]:
    with path.open("r", encoding="utf-8", newline="") as handle:
        return list(csv.DictReader(handle))


def fail(findings: list[dict[str, str]], rule_id: str, message: str, severity: str = "high") -> None:
    findings.append({"id": rule_id, "severity": severity, "message": message})


def release_source_counts(release_dir: Path, findings: list[dict[str, str]]) -> dict[str, int]:
    files = {
        "source_release_objects": "01-发布对象清单.csv",
        "source_approval_items": "02-审批签核演练清单.csv",
        "source_training_items": "03-培训宣贯演练清单.csv",
        "source_obsolete_items": "04-旧版处置演练清单.csv",
        "source_effectiveness_items": "07-实施有效性检查清单.csv",
    }
    counts: dict[str, int] = {}
    for key, filename in files.items():
        path = release_dir / filename
        if not path.exists():
            fail(findings, "missing_release_source_" + key, f"缺少受控发布演练源文件：{path}")
            counts[key] = 0
            continue
        counts[key] = len(read_csv(path))
    return counts


def check_pack(template_dir: Path, release_dir: Path | None = None) -> dict[str, Any]:
    findings: list[dict[str, str]] = []
    manifest_path = template_dir / "release_execution_template_manifest.json"
    if not manifest_path.exists():
        return {
            "generated_at": dt.datetime.now().isoformat(timespec="seconds"),
            "template_dir": str(template_dir),
            "status": "failed",
            "findings": [{"id": "missing_manifest", "severity": "high", "message": "缺少 release_execution_template_manifest.json"}],
        }

    manifest = json.loads(read_text(manifest_path))
    if manifest.get("status") != "release_execution_templates_no_database_write":
        fail(findings, "invalid_manifest_status", "manifest 状态必须为 release_execution_templates_no_database_write。")

    release_dir = release_dir or Path(str(manifest.get("release_rehearsal_dir", "")))

    guardrail_text = "\n".join(str(item) for item in manifest.get("guardrails", []))
    for marker in REQUIRED_GUARDRAILS:
        if marker not in guardrail_text:
            fail(findings, "manifest_missing_guardrail", f"manifest 缺少边界标识：{marker}")

    files = manifest.get("files", {})
    for key, default_name in REQUIRED_FILES.items():
        filename = files.get(key, default_name)
        if not (template_dir / filename).exists():
            fail(findings, "missing_" + key, f"缺少发布执行模板包文件：{filename}")

    for path in list(template_dir.glob("*.sql")) + list(template_dir.glob("*.db")) + list((template_dir / "templates").glob("*.sql")) + list((template_dir / "templates").glob("*.db")):
        fail(findings, "forbidden_database_artifact", f"发布执行模板包不应包含数据库/SQL 文件：{path.name}")

    index_path = template_dir / files.get("template_index", REQUIRED_FILES["template_index"])
    detail_path = template_dir / files.get("field_detail", REQUIRED_FILES["field_detail"])
    trial_path = template_dir / files.get("trial_csv", REQUIRED_FILES["trial_csv"])
    index_rows = read_csv(index_path) if index_path.exists() else []
    detail_rows = read_csv(detail_path) if detail_path.exists() else []
    trial_rows = read_csv(trial_path) if trial_path.exists() else []

    template_codes = {row.get("template_code", "") for row in index_rows}
    missing_templates = sorted(EXPECTED_TEMPLATE_CODES - template_codes)
    extra_templates = sorted(template_codes - EXPECTED_TEMPLATE_CODES)
    if missing_templates:
        fail(findings, "missing_template_codes", "缺少发布执行模板：" + "、".join(missing_templates))
    if extra_templates:
        fail(findings, "extra_template_codes", "存在非预期发布执行模板：" + "、".join(extra_templates))

    details_by_code: dict[str, list[dict[str, str]]] = {}
    for row in detail_rows:
        details_by_code.setdefault(row.get("template_code", ""), []).append(row)
        if row.get("required") not in {"yes", "no"}:
            fail(findings, "invalid_required_flag", f"{row.get('template_code')}/{row.get('field_key')} required 必须为 yes/no。")
        if row.get("field_group") not in {"common", "specific"}:
            fail(findings, "invalid_field_group", f"{row.get('template_code')}/{row.get('field_key')} field_group 非法。")

    for row in index_rows:
        code = row.get("template_code", "")
        rows_for_code = details_by_code.get(code, [])
        keys = {item.get("field_key", "") for item in rows_for_code}
        missing_common = sorted(COMMON_FIELD_KEYS - keys)
        if missing_common:
            fail(findings, "missing_common_fields", f"{code} 缺少通用字段：" + "、".join(missing_common))
        try:
            indexed_count = int(row.get("field_count", "-1"))
        except ValueError:
            indexed_count = -1
        if indexed_count != len(rows_for_code):
            fail(findings, "field_count_mismatch", f"{code} 字段数不一致：index={indexed_count}, detail={len(rows_for_code)}")
        markdown_file = row.get("markdown_file", "")
        if not markdown_file or not (template_dir / markdown_file).exists():
            fail(findings, "missing_template_markdown", f"{code} 缺少模板 Markdown：{markdown_file}")

    trial_codes = {row.get("template_code", "") for row in trial_rows}
    if trial_codes != template_codes:
        fail(
            findings,
            "trial_template_mismatch",
            "模拟试填模板与索引不一致：trial_missing=" + "、".join(sorted(template_codes - trial_codes))
            + "；index_missing=" + "、".join(sorted(trial_codes - template_codes)),
        )
    for row in trial_rows:
        if row.get("not_real_record") != "yes":
            fail(findings, "trial_not_marked_not_real", f"{row.get('template_code')} 模拟试填未标记 not_real_record=yes。")
        try:
            values = json.loads(row.get("field_values_json", "{}"))
        except json.JSONDecodeError as exc:
            fail(findings, "invalid_trial_json", f"{row.get('template_code')} field_values_json 非法：{exc}")
            continue
        if "SIMULATED_TRIAL_NOT_REAL_RECORD" not in json.dumps(values, ensure_ascii=False):
            fail(findings, "trial_missing_simulated_marker", f"{row.get('template_code')} 模拟试填缺少 SIMULATED 标识。")
        keys = {item.get("field_key", "") for item in details_by_code.get(row.get("template_code", ""), [])}
        missing_values = sorted(keys - set(values))
        if missing_values:
            fail(findings, "trial_missing_field_values", f"{row.get('template_code')} 模拟试填缺少字段值：" + "、".join(missing_values[:12]))

    counts = manifest.get("counts", {})
    expected_counts = {
        "templates": len(index_rows),
        "fields": len(detail_rows),
        "trial_instances": len(trial_rows),
        "template_markdown_files": len(list((template_dir / "templates").glob("*.md"))),
    }
    for key, actual in expected_counts.items():
        if int(counts.get(key, -1)) != actual:
            fail(findings, "manifest_count_mismatch_" + key, f"manifest {key}={counts.get(key)}，实际 {actual}。")

    source_counts = release_source_counts(release_dir, findings) if release_dir else {}
    for key, actual in source_counts.items():
        if int(counts.get(key, -1)) != actual:
            fail(findings, "source_count_mismatch_" + key, f"manifest {key}={counts.get(key)}，源文件实际 {actual}。")

    for filename in [
        files.get("overview", REQUIRED_FILES["overview"]),
        files.get("readme", REQUIRED_FILES["readme"]),
        *[row.get("markdown_file", "") for row in index_rows],
    ]:
        if not filename:
            continue
        path = template_dir / filename
        if not path.exists():
            continue
        text = read_text(path)
        for marker in ["不写数据库", "不代表受控发布", "SIMULATED_TRIAL_NOT_REAL_RECORD"]:
            if marker not in text:
                fail(findings, "doc_missing_guardrail", f"{filename} 缺少边界标识：{marker}")
        if re.search(r"已批准发布|可以写库|准许写库|正式运行记录|(本公司|公司|实验室).{0,12}(已取得|已获|获得)\s*CNAS|CNAS\s*认可证书", text):
            fail(findings, "doc_overstates_status", f"{filename} 疑似包含越权状态表述。")

    status = "passed" if not findings else "failed"
    return {
        "generated_at": dt.datetime.now().isoformat(timespec="seconds"),
        "template_dir": str(template_dir),
        "release_dir": str(release_dir) if release_dir else None,
        "status": status,
        "counts": {
            "templates": len(index_rows),
            "fields": len(detail_rows),
            "trial_instances": len(trial_rows),
            "template_markdown_files": len(list((template_dir / "templates").glob("*.md"))),
            "source_release_objects": source_counts.get("source_release_objects", 0),
            "source_approval_items": source_counts.get("source_approval_items", 0),
            "source_training_items": source_counts.get("source_training_items", 0),
            "source_obsolete_items": source_counts.get("source_obsolete_items", 0),
            "source_effectiveness_items": source_counts.get("source_effectiveness_items", 0),
            "findings": len(findings),
        },
        "findings": findings,
    }


def render_markdown(result: dict[str, Any]) -> str:
    lines = [
        "# 发布执行记录模板 dry-run 验证报告",
        "",
        f"生成时间：{result['generated_at']}",
        f"验证对象：`{result['template_dir']}`",
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
        lines.append("未发现阻断性问题。该结论只证明发布执行记录候选模板结构、字段覆盖、模拟试填和不写库边界通过检查；不代表已经人工批准、发布或形成真实记录。")
    lines.append("")
    return "\n".join(lines)


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--template-dir", required=True)
    parser.add_argument("--release-dir")
    parser.add_argument("--json-out")
    parser.add_argument("--md-out")
    args = parser.parse_args()

    result = check_pack(Path(args.template_dir), Path(args.release_dir) if args.release_dir else None)
    print(json.dumps(result, ensure_ascii=False, indent=2))
    if args.json_out:
        Path(args.json_out).write_text(json.dumps(result, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
    if args.md_out:
        Path(args.md_out).write_text(render_markdown(result), encoding="utf-8")
    return 0 if result["status"] == "passed" else 1


if __name__ == "__main__":
    raise SystemExit(main())
