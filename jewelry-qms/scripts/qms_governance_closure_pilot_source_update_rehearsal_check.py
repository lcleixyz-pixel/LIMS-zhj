#!/usr/bin/env python3
"""Validate a no-write source-workbench update rehearsal."""

from __future__ import annotations

import argparse
import csv
import datetime as dt
import json
from pathlib import Path
from typing import Any


REHEARSAL_FILES = {
    "manifest": "governance_closure_pilot_source_update_manifest.json",
    "overview": "00-源工作台回填补丁预演总览.md",
    "patch_preview": "01-源工作台回填补丁预览.csv",
    "blocked_patches": "02-阻断补丁清单.csv",
    "manual_instructions": "03-人工回填操作说明.md",
    "readme": "README.md",
}

REQUIRED_GUARDRAILS = [
    "不写数据库",
    "不修改 governance_closure_pilot_return_preview",
    "不修改 governance_closure_workbench",
    "不代表人工评审通过",
    "不代表真实培训完成",
    "不代表受控发布",
    "已取得 CMA",
    "CNAS 申请中",
    "2022 程序清单",
    "jewelry-qms 仍为建设中系统",
    "不写入质量手册正文",
]

ALLOWED_TARGETS = {
    "governance_closure_workbench/03-证据采集模板.csv": {
        "evidence_reference",
        "evidence_owner",
        "evidence_date",
        "evidence_result",
    },
    "governance_closure_workbench/04-拟关闭回填模板.csv": {
        "evidence_reference",
        "closure_comment",
        "reviewer",
        "review_date",
        "proposed_closure_status",
        "closure_result",
        "blocks_apply",
    },
}


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


def check_rehearsal(rehearsal_dir: Path) -> dict[str, Any]:
    findings: list[dict[str, str]] = []
    manifest_path = rehearsal_dir / REHEARSAL_FILES["manifest"]
    manifest: dict[str, Any] = {}
    if not manifest_path.is_file():
        add_finding(findings, "missing_governance_closure_pilot_source_update_manifest", "源工作台回填补丁预演缺少 manifest。")
    else:
        manifest = json.loads(manifest_path.read_text(encoding="utf-8"))
        if manifest.get("status") != "governance_closure_pilot_source_update_rehearsal_no_write":
            add_finding(findings, "invalid_governance_closure_pilot_source_update_manifest_status", "源工作台回填补丁预演 manifest 状态不符合预期。")
        guardrail_text = "\n".join(str(item) for item in manifest.get("guardrails", []))
        for marker in REQUIRED_GUARDRAILS:
            if marker not in guardrail_text:
                add_finding(findings, "governance_closure_pilot_source_update_manifest_missing_guardrail", f"manifest 缺少边界标识：{marker}")

    files = dict(REHEARSAL_FILES)
    files.update({key: str(value) for key, value in manifest.get("files", {}).items()})
    paths: dict[str, Path] = {}
    for key, filename in files.items():
        path = rehearsal_dir / filename
        paths[key] = path
        if not path.is_file():
            add_finding(findings, f"missing_governance_closure_pilot_source_update_{key}", f"源工作台回填补丁预演缺少文件：{filename}")

    for path in forbidden_database_artifacts(rehearsal_dir):
        add_finding(findings, "governance_closure_pilot_source_update_forbidden_database_artifact", f"补丁预演不应包含数据库/SQL 文件：{Path(path).name}")

    patch_rows = read_csv(paths["patch_preview"]) if paths.get("patch_preview", Path()).is_file() else []
    blocked_rows = read_csv(paths["blocked_patches"]) if paths.get("blocked_patches", Path()).is_file() else []

    patch_ids: set[str] = set()
    ready_rows = 0
    blocked_count = 0
    manual_candidate_rows = 0
    for index, row in enumerate(patch_rows, start=2):
        label = row.get("patch_id", "") or f"补丁第 {index} 行"
        patch_id = row.get("patch_id", "").strip()
        if not patch_id:
            add_finding(findings, "governance_closure_pilot_source_update_blank_patch_id", "补丁行 patch_id 为空。")
        elif patch_id in patch_ids:
            add_finding(findings, "governance_closure_pilot_source_update_duplicate_patch_id", f"补丁行 patch_id 重复：{patch_id}")
        patch_ids.add(patch_id)
        for field in ["not_imported", "not_real_record", "no_source_modified"]:
            if row.get(field, "") != "yes":
                add_finding(findings, "governance_closure_pilot_source_update_marker_missing", f"{label} 必须保留 {field}=yes。")
        target_file = row.get("target_file", "")
        target_field = row.get("target_field", "")
        if target_file not in ALLOWED_TARGETS:
            add_finding(findings, "governance_closure_pilot_source_update_target_file_invalid", f"{label} target_file 不在允许范围内。")
        elif target_field not in ALLOWED_TARGETS[target_file]:
            add_finding(findings, "governance_closure_pilot_source_update_target_field_invalid", f"{label} target_field 不在允许范围内：{target_field}")
        if row.get("patch_action") not in {"blocked_no_update", "manual_update_candidate", "no_change_candidate"}:
            add_finding(findings, "governance_closure_pilot_source_update_action_invalid", f"{label} patch_action 不合法。")
        if row.get("update_ready") not in {"yes", "no"}:
            add_finding(findings, "governance_closure_pilot_source_update_ready_invalid", f"{label} update_ready 必须为 yes/no。")
        if row.get("patch_action") == "blocked_no_update":
            blocked_count += 1
            if not row.get("block_reason", "").strip():
                add_finding(findings, "governance_closure_pilot_source_update_block_reason_blank", f"{label} 为阻断补丁但 block_reason 为空。")
        if row.get("patch_action") == "manual_update_candidate":
            manual_candidate_rows += 1
        if row.get("update_ready") == "yes":
            ready_rows += 1
            if row.get("patch_action") != "manual_update_candidate":
                add_finding(findings, "governance_closure_pilot_source_update_ready_action_mismatch", f"{label} update_ready=yes 时 patch_action 应为 manual_update_candidate。")

    blocked_ids = {row.get("patch_id", "") for row in blocked_rows}
    expected_blocked = {row.get("patch_id", "") for row in patch_rows if row.get("patch_action") == "blocked_no_update"}
    if blocked_ids != expected_blocked:
        add_finding(findings, "governance_closure_pilot_source_update_blocked_register_mismatch", "阻断补丁清单与补丁预览中的 blocked_no_update 行不一致。")

    actual_counts = {
        "source_preview_rows": int(manifest.get("counts", {}).get("source_preview_rows", 0)),
        "missing_field_rows": int(manifest.get("counts", {}).get("missing_field_rows", 0)),
        "patch_rows": len(patch_rows),
        "ready_patch_rows": ready_rows,
        "blocked_patch_rows": blocked_count,
        "manual_update_candidate_rows": manual_candidate_rows,
        "source_workbench_modified": int(manifest.get("counts", {}).get("source_workbench_modified", 0)),
        "database_write_performed": int(manifest.get("counts", {}).get("database_write_performed", 0)),
    }
    for key, actual in actual_counts.items():
        if key in manifest.get("counts", {}) and int(manifest["counts"][key]) != actual:
            add_finding(findings, f"governance_closure_pilot_source_update_count_mismatch_{key}", f"{key} 计数不一致：manifest={manifest['counts'][key]}，actual={actual}")
    if actual_counts["source_workbench_modified"] != 0:
        add_finding(findings, "governance_closure_pilot_source_update_modified_source_flagged", "source_workbench_modified 必须为 0。")
    if actual_counts["database_write_performed"] != 0:
        add_finding(findings, "governance_closure_pilot_source_update_database_write_flagged", "database_write_performed 必须为 0。")

    ready_for_update = str(manifest.get("ready_for_source_workbench_update", ""))
    ready_for_preview = str(manifest.get("ready_for_governance_closure_preview", ""))
    ready_for_apply = str(manifest.get("ready_for_lims_apply", ""))
    if blocked_count > 0 and ready_for_update != "no":
        add_finding(findings, "governance_closure_pilot_source_update_ready_conflicts_with_blocked", "仍有阻断补丁时 ready_for_source_workbench_update 必须为 no。")
    if ready_for_update != "yes" and ready_for_preview == "yes":
        add_finding(findings, "governance_closure_pilot_source_update_preview_conflicts_with_update", "源工作台更新未 ready 时 ready_for_governance_closure_preview 必须为 no。")
    if ready_for_apply == "yes":
        add_finding(findings, "governance_closure_pilot_source_update_cannot_authorize_lims_apply", "源工作台回填补丁预演不能单独声明 ready_for_lims_apply=yes。")

    for key in ["overview", "manual_instructions", "readme"]:
        path = paths.get(key)
        if not path or not path.is_file():
            continue
        text = path.read_text(encoding="utf-8")
        for marker in ["不写数据库", "不代表人工评审通过", "不写入质量手册正文"]:
            if marker not in text:
                add_finding(findings, "governance_closure_pilot_source_update_doc_missing_guardrail", f"{path.name} 缺少边界标识：{marker}")

    return {
        "generated_at": dt.datetime.now().replace(microsecond=0).isoformat(),
        "rehearsal_dir": str(rehearsal_dir),
        "status": "passed" if not findings else "failed",
        "readiness": str(manifest.get("readiness", "")),
        "ready_for_source_workbench_update": ready_for_update,
        "ready_for_governance_closure_preview": ready_for_preview,
        "ready_for_lims_apply": ready_for_apply,
        "counts": {**actual_counts, "findings": len(findings)},
        "findings": findings,
    }


def render_markdown(result: dict[str, Any]) -> str:
    lines = [
        "# QMS 治理关闭试点源工作台回填补丁预演 dry-run 验证报告",
        "",
        f"生成时间：{result['generated_at']}",
        f"预演包：`{result['rehearsal_dir']}`",
        f"结论：{result['status']}",
        f"readiness：{result.get('readiness', '')}",
        f"ready_for_source_workbench_update：{result.get('ready_for_source_workbench_update', '')}",
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
    parser.add_argument("--rehearsal-dir", required=True)
    parser.add_argument("--json-out")
    parser.add_argument("--md-out")
    args = parser.parse_args()

    result = check_rehearsal(Path(args.rehearsal_dir))
    if args.json_out:
        Path(args.json_out).write_text(json.dumps(result, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
    if args.md_out:
        Path(args.md_out).write_text(render_markdown(result), encoding="utf-8")
    print(json.dumps(result, ensure_ascii=False, indent=2))
    return 0 if result["status"] == "passed" else 1


if __name__ == "__main__":
    raise SystemExit(main())
