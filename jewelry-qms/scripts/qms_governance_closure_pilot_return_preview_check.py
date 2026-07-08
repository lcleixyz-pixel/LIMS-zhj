#!/usr/bin/env python3
"""Validate a no-write governance closure pilot return preview."""

from __future__ import annotations

import argparse
import csv
import datetime as dt
import json
from pathlib import Path
from typing import Any


RETURN_FILES = {
    "manifest": "governance_closure_pilot_return_manifest.json",
    "overview": "00-试点回填预览总览.md",
    "mapping": "01-试点证据到源工作台映射.csv",
    "source_preview": "02-拟回填源行预览.csv",
    "missing_fields": "03-仍缺字段清单.csv",
    "rerun_path": "04-复跑路径清单.md",
    "readme": "README.md",
}

REQUIRED_GUARDRAILS = [
    "不写数据库",
    "不修改 governance_closure_pilot_pack",
    "不代表人工评审通过",
    "不代表真实培训完成",
    "不代表受控发布",
    "已取得 CMA",
    "CNAS 申请中",
    "2022 程序清单",
    "jewelry-qms 仍为建设中系统",
    "不写入质量手册正文",
]


def read_csv(path: Path) -> list[dict[str, str]]:
    with path.open("r", encoding="utf-8-sig", newline="") as handle:
        return list(csv.DictReader(handle))


def add_finding(findings: list[dict[str, str]], finding_id: str, message: str, severity: str = "high") -> None:
    findings.append({"severity": severity, "id": finding_id, "message": message})


def forbidden_database_artifacts(base: Path) -> list[str]:
    return [
        str(path)
        for path in base.rglob("*")
        if path.is_file() and path.suffix.lower() in {".sql", ".db", ".sqlite", ".sqlite3"}
    ]


def marker_check(row: dict[str, str], label: str, findings: list[dict[str, str]], finding_id: str) -> None:
    for field in ["not_imported", "not_real_record"]:
        if row.get(field, "") != "yes":
            add_finding(findings, finding_id, f"{label} 必须保留 {field}=yes。")


def check_return_preview(preview_dir: Path) -> dict[str, Any]:
    findings: list[dict[str, str]] = []
    manifest_path = preview_dir / RETURN_FILES["manifest"]
    manifest: dict[str, Any] = {}
    if not manifest_path.is_file():
        add_finding(findings, "missing_governance_closure_pilot_return_manifest", "试点回填预览缺少 governance_closure_pilot_return_manifest.json。")
    else:
        manifest = json.loads(manifest_path.read_text(encoding="utf-8"))
        if manifest.get("status") != "governance_closure_pilot_return_preview_no_database_write":
            add_finding(findings, "invalid_governance_closure_pilot_return_manifest_status", "试点回填预览 manifest 状态不符合预期。")
        guardrail_text = "\n".join(str(item) for item in manifest.get("guardrails", []))
        for marker in REQUIRED_GUARDRAILS:
            if marker not in guardrail_text:
                add_finding(findings, "governance_closure_pilot_return_manifest_missing_guardrail", f"试点回填预览 manifest 缺少边界标识：{marker}")

    files = dict(RETURN_FILES)
    files.update({key: str(value) for key, value in manifest.get("files", {}).items()})
    paths: dict[str, Path] = {}
    for key, filename in files.items():
        path = preview_dir / filename
        paths[key] = path
        if not path.is_file():
            add_finding(findings, f"missing_governance_closure_pilot_return_{key}", f"试点回填预览缺少文件：{filename}")

    for path in forbidden_database_artifacts(preview_dir):
        add_finding(findings, "governance_closure_pilot_return_forbidden_database_artifact", f"试点回填预览不应包含数据库/SQL 文件：{Path(path).name}")

    mapping_rows = read_csv(paths["mapping"]) if paths.get("mapping", Path()).is_file() else []
    preview_rows = read_csv(paths["source_preview"]) if paths.get("source_preview", Path()).is_file() else []
    missing_rows = read_csv(paths["missing_fields"]) if paths.get("missing_fields", Path()).is_file() else []

    return_ids: set[str] = set()
    ready_return_items = 0
    blocking_return_items = 0
    for index, row in enumerate(mapping_rows, start=2):
        return_id = row.get("return_item_id", "").strip()
        label = return_id or f"回填映射第 {index} 行"
        if not return_id:
            add_finding(findings, "governance_closure_pilot_return_blank_return_id", "回填映射 return_item_id 为空。")
        elif return_id in return_ids:
            add_finding(findings, "governance_closure_pilot_return_duplicate_return_id", f"回填映射存在重复 return_item_id：{return_id}")
        return_ids.add(return_id)
        marker_check(row, label, findings, "governance_closure_pilot_return_mapping_marker_missing")
        if row.get("source_evidence_row_found") != "yes":
            add_finding(findings, "governance_closure_pilot_return_source_evidence_missing", f"{label} 未匹配源证据采集行。")
        if row.get("source_closure_row_found") != "yes":
            add_finding(findings, "governance_closure_pilot_return_source_closure_missing", f"{label} 未匹配源关闭回填行。")
        if row.get("pilot_handoff_found") != "yes":
            add_finding(findings, "governance_closure_pilot_return_handoff_missing", f"{label} 未匹配试点签核交接行。")
        if row.get("return_status") not in {"ready", "blocked"}:
            add_finding(findings, "governance_closure_pilot_return_status_invalid", f"{label} return_status 必须为 ready/blocked。")
        if row.get("return_status") == "ready":
            ready_return_items += 1
        else:
            blocking_return_items += 1
        if row.get("blocks_apply") not in {"yes", "no"}:
            add_finding(findings, "governance_closure_pilot_return_blocks_apply_invalid", f"{label} blocks_apply 必须为 yes/no。")

    ready_source_preview_rows = 0
    for index, row in enumerate(preview_rows, start=2):
        label = f"拟回填源行第 {index} 行"
        marker_check(row, label, findings, "governance_closure_pilot_return_source_preview_marker_missing")
        if row.get("return_item_id", "") not in return_ids:
            add_finding(findings, "governance_closure_pilot_return_source_preview_unknown_return", f"{label} 指向不存在的 return_item_id。")
        if row.get("target_file") not in {
            "governance_closure_workbench/03-证据采集模板.csv",
            "governance_closure_workbench/04-拟关闭回填模板.csv",
        }:
            add_finding(findings, "governance_closure_pilot_return_target_file_invalid", f"{label} target_file 不在允许范围内。")
        if row.get("ready_for_manual_source_update") not in {"yes", "no"}:
            add_finding(findings, "governance_closure_pilot_return_ready_flag_invalid", f"{label} ready_for_manual_source_update 必须为 yes/no。")
        if row.get("ready_for_manual_source_update") == "yes":
            ready_source_preview_rows += 1

    missing_ids: set[str] = set()
    for index, row in enumerate(missing_rows, start=2):
        missing_id = row.get("missing_id", "").strip()
        label = missing_id or f"缺字段第 {index} 行"
        if not missing_id:
            add_finding(findings, "governance_closure_pilot_return_blank_missing_id", "缺字段行 missing_id 为空。")
        elif missing_id in missing_ids:
            add_finding(findings, "governance_closure_pilot_return_duplicate_missing_id", f"缺字段存在重复 missing_id：{missing_id}")
        missing_ids.add(missing_id)
        marker_check(row, label, findings, "governance_closure_pilot_return_missing_marker_missing")
        if row.get("return_item_id", "") not in return_ids:
            add_finding(findings, "governance_closure_pilot_return_missing_unknown_return", f"{label} 指向不存在的 return_item_id。")
        if not row.get("missing_field", "").strip():
            add_finding(findings, "governance_closure_pilot_return_missing_field_blank", f"{label} missing_field 为空。")

    actual_counts = {
        "pilot_evidence_rows": len(mapping_rows),
        "mapping_rows": len(mapping_rows),
        "source_preview_rows": len(preview_rows),
        "missing_field_rows": len(missing_rows),
        "ready_return_items": ready_return_items,
        "blocking_return_items": blocking_return_items,
        "database_write_performed": int(manifest.get("counts", {}).get("database_write_performed", 0)),
    }
    for key, actual in actual_counts.items():
        if key in manifest.get("counts", {}) and int(manifest["counts"][key]) != actual:
            add_finding(findings, f"governance_closure_pilot_return_count_mismatch_{key}", f"试点回填预览 {key} 计数不一致：manifest={manifest['counts'][key]}，actual={actual}")
    if actual_counts["database_write_performed"] != 0:
        add_finding(findings, "governance_closure_pilot_return_database_write_flagged", "试点回填预览 database_write_performed 必须为 0。")
    if ready_source_preview_rows and ready_source_preview_rows != ready_return_items * 2:
        add_finding(findings, "governance_closure_pilot_return_ready_preview_row_mismatch", "ready 的源行预览数应等于 ready_return_items * 2。")

    ready_for_preview = str(manifest.get("ready_for_governance_closure_preview", ""))
    ready_for_apply = str(manifest.get("ready_for_lims_apply", ""))
    if (blocking_return_items > 0 or missing_rows) and ready_for_preview != "no":
        add_finding(findings, "governance_closure_pilot_return_ready_preview_conflicts_with_missing_fields", "仍有阻断项或缺字段时 ready_for_governance_closure_preview 必须为 no。")
    if ready_for_apply == "yes":
        add_finding(findings, "governance_closure_pilot_return_cannot_authorize_lims_apply", "试点回填预览不能单独声明 ready_for_lims_apply=yes。")

    for key in ["overview", "rerun_path", "readme"]:
        path = paths.get(key)
        if not path or not path.is_file():
            continue
        text = path.read_text(encoding="utf-8")
        for marker in ["不写数据库", "不代表人工评审通过", "不写入质量手册正文"]:
            if marker not in text:
                add_finding(findings, "governance_closure_pilot_return_doc_missing_guardrail", f"{path.name} 缺少边界标识：{marker}")

    return {
        "generated_at": dt.datetime.now().replace(microsecond=0).isoformat(),
        "preview_dir": str(preview_dir),
        "status": "passed" if not findings else "failed",
        "readiness": str(manifest.get("readiness", "")),
        "ready_for_governance_closure_preview": ready_for_preview,
        "ready_for_lims_apply": ready_for_apply,
        "counts": {**actual_counts, "findings": len(findings)},
        "findings": findings,
    }


def render_markdown(result: dict[str, Any]) -> str:
    lines = [
        "# QMS 治理关闭试点回填预览 dry-run 验证报告",
        "",
        f"生成时间：{result['generated_at']}",
        f"预览包：`{result['preview_dir']}`",
        f"结论：{result['status']}",
        f"readiness：{result.get('readiness', '')}",
        f"ready_for_governance_closure_preview：{result.get('ready_for_governance_closure_preview', '')}",
        f"ready_for_lims_apply：{result.get('ready_for_lims_apply', '')}",
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
        lines.append("未发现结构性问题。该结论不代表人工评审通过、真实培训完成、受控发布或正式写库授权。")
    lines.append("")
    return "\n".join(lines)


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--preview-dir", required=True)
    parser.add_argument("--json-out")
    parser.add_argument("--md-out")
    args = parser.parse_args()

    result = check_return_preview(Path(args.preview_dir))
    if args.json_out:
        Path(args.json_out).write_text(json.dumps(result, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
    if args.md_out:
        Path(args.md_out).write_text(render_markdown(result), encoding="utf-8")
    print(json.dumps(result, ensure_ascii=False, indent=2))
    return 0 if result["status"] == "passed" else 1


if __name__ == "__main__":
    raise SystemExit(main())
