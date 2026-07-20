#!/usr/bin/env python3
"""Validate a no-write governance closure pilot pack."""

from __future__ import annotations

import argparse
import csv
import datetime as dt
import json
from pathlib import Path
from typing import Any


PILOT_FILES = {
    "manifest": "governance_closure_pilot_manifest.json",
    "overview": "00-治理关闭最小试点总览.md",
    "pilot_batches": "01-试点批次选择.csv",
    "pilot_evidence": "02-试点证据填写页.csv",
    "pilot_handoff": "03-试点签核交接页.csv",
    "rerun_commands": "04-试点复跑命令清单.md",
    "readme": "README.md",
}

REQUIRED_GUARDRAILS = [
    "不写数据库",
    "不修改 governance_closure_execution_pack",
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
    for field, expected in {"not_imported": "yes", "not_real_record": "yes"}.items():
        if row.get(field, "") != expected:
            add_finding(findings, finding_id, f"{label} 必须保留 {field}=yes。")


def check_pilot_pack(pilot_dir: Path) -> dict[str, Any]:
    findings: list[dict[str, str]] = []
    manifest_path = pilot_dir / PILOT_FILES["manifest"]
    manifest: dict[str, Any] = {}
    if not manifest_path.is_file():
        add_finding(findings, "missing_governance_closure_pilot_manifest", "试点包缺少 governance_closure_pilot_manifest.json。")
    else:
        manifest = json.loads(manifest_path.read_text(encoding="utf-8"))
        if manifest.get("status") != "governance_closure_pilot_pack_no_database_write":
            add_finding(findings, "invalid_governance_closure_pilot_manifest_status", "试点包 manifest 状态不符合预期。")
        guardrail_text = "\n".join(str(item) for item in manifest.get("guardrails", []))
        for marker in REQUIRED_GUARDRAILS:
            if marker not in guardrail_text:
                add_finding(findings, "governance_closure_pilot_manifest_missing_guardrail", f"试点包 manifest 缺少边界标识：{marker}")

    files = dict(PILOT_FILES)
    files.update({key: str(value) for key, value in manifest.get("files", {}).items()})
    paths: dict[str, Path] = {}
    for key, filename in files.items():
        path = pilot_dir / filename
        paths[key] = path
        if not path.is_file():
            add_finding(findings, f"missing_governance_closure_pilot_{key}", f"试点包缺少文件：{filename}")

    for path in forbidden_database_artifacts(pilot_dir):
        add_finding(findings, "governance_closure_pilot_forbidden_database_artifact", f"试点包不应包含数据库/SQL 文件：{Path(path).name}")

    batch_rows = read_csv(paths["pilot_batches"]) if paths.get("pilot_batches", Path()).is_file() else []
    evidence_rows = read_csv(paths["pilot_evidence"]) if paths.get("pilot_evidence", Path()).is_file() else []
    handoff_rows = read_csv(paths["pilot_handoff"]) if paths.get("pilot_handoff", Path()).is_file() else []

    batch_ids: set[str] = set()
    pending_batches = 0
    for index, row in enumerate(batch_rows, start=2):
        batch_id = row.get("pilot_batch_id", "").strip()
        label = batch_id or f"试点批次第 {index} 行"
        if not batch_id:
            add_finding(findings, "governance_closure_pilot_blank_batch_id", "试点批次 pilot_batch_id 为空。")
        elif batch_id in batch_ids:
            add_finding(findings, "governance_closure_pilot_duplicate_batch_id", f"试点批次存在重复 pilot_batch_id：{batch_id}")
        batch_ids.add(batch_id)
        marker_check(row, label, findings, "governance_closure_pilot_batch_marker_missing")
        status = row.get("pilot_status", "")
        if status not in {"pending", "completed", "rejected"}:
            add_finding(findings, "governance_closure_pilot_batch_status_invalid", f"{label} pilot_status 必须为 pending/completed/rejected。")
        if status == "pending":
            pending_batches += 1

    evidence_ids: set[str] = set()
    pending_evidence = 0
    blocking_pilot_items = 0
    for index, row in enumerate(evidence_rows, start=2):
        evidence_id = row.get("pilot_evidence_id", "").strip()
        label = evidence_id or f"试点证据第 {index} 行"
        if not evidence_id:
            add_finding(findings, "governance_closure_pilot_blank_evidence_id", "试点证据 pilot_evidence_id 为空。")
        elif evidence_id in evidence_ids:
            add_finding(findings, "governance_closure_pilot_duplicate_evidence_id", f"试点证据存在重复 pilot_evidence_id：{evidence_id}")
        evidence_ids.add(evidence_id)
        marker_check(row, label, findings, "governance_closure_pilot_evidence_marker_missing")
        batch_id = row.get("pilot_batch_id", "").strip()
        if batch_id not in batch_ids:
            add_finding(findings, "governance_closure_pilot_evidence_unknown_batch", f"{label} 指向不存在的 pilot_batch_id。")
        status = row.get("evidence_status", "")
        if status not in {"pending", "ready", "rejected"}:
            add_finding(findings, "governance_closure_pilot_evidence_status_invalid", f"{label} evidence_status 必须为 pending/ready/rejected。")
        if status == "pending":
            pending_evidence += 1
        if status == "ready":
            missing_fields = [
                field
                for field in ["evidence_reference", "evidence_summary", "closure_comment", "reviewer", "review_date"]
                if not row.get(field, "").strip()
            ]
            if missing_fields:
                add_finding(
                    findings,
                    "governance_closure_pilot_ready_evidence_missing_fields",
                    f"{label} 已 ready 但缺少字段：{'、'.join(missing_fields)}",
                )
        if row.get("blocks_apply", "") == "yes":
            blocking_pilot_items += 1
        elif row.get("blocks_apply", "") != "no":
            add_finding(findings, "governance_closure_pilot_blocks_apply_invalid", f"{label} blocks_apply 必须为 yes/no。")

    handoff_ids: set[str] = set()
    pending_handoffs = 0
    for index, row in enumerate(handoff_rows, start=2):
        handoff_id = row.get("pilot_handoff_id", "").strip()
        label = handoff_id or f"试点签核交接第 {index} 行"
        if not handoff_id:
            add_finding(findings, "governance_closure_pilot_blank_handoff_id", "试点签核交接 pilot_handoff_id 为空。")
        elif handoff_id in handoff_ids:
            add_finding(findings, "governance_closure_pilot_duplicate_handoff_id", f"试点签核交接存在重复 pilot_handoff_id：{handoff_id}")
        handoff_ids.add(handoff_id)
        marker_check(row, label, findings, "governance_closure_pilot_handoff_marker_missing")
        batch_id = row.get("pilot_batch_id", "").strip()
        if batch_id not in batch_ids:
            add_finding(findings, "governance_closure_pilot_handoff_unknown_batch", f"{label} 指向不存在的 pilot_batch_id。")
        for field in ["signature_status", "handoff_status"]:
            status = row.get(field, "")
            if status not in {"pending", "completed", "rejected"}:
                add_finding(findings, "governance_closure_pilot_handoff_status_invalid", f"{label} {field} 必须为 pending/completed/rejected。")
            if status == "pending" and field == "handoff_status":
                pending_handoffs += 1
        if row.get("signature_status") == "completed" or row.get("handoff_status") == "completed":
            missing_fields = [
                field
                for field in ["assigned_person", "reviewer", "actual_finish_date"]
                if not row.get(field, "").strip()
            ]
            if missing_fields:
                add_finding(
                    findings,
                    "governance_closure_pilot_completed_handoff_missing_fields",
                    f"{label} 已 completed 但缺少字段：{'、'.join(missing_fields)}",
                )

    actual_counts = {
        "pilot_batches": len(batch_rows),
        "pilot_evidence_rows": len(evidence_rows),
        "pilot_handoff_rows": len(handoff_rows),
        "blocking_pilot_items": blocking_pilot_items,
        "pending_pilot_batches": pending_batches,
        "pending_pilot_evidence": pending_evidence,
        "pending_pilot_handoffs": pending_handoffs,
        "database_write_performed": int(manifest.get("counts", {}).get("database_write_performed", 0)),
    }
    for key, actual in actual_counts.items():
        if key in manifest.get("counts", {}) and int(manifest["counts"][key]) != actual:
            add_finding(findings, f"governance_closure_pilot_count_mismatch_{key}", f"试点包 {key} 计数不一致：manifest={manifest['counts'][key]}，actual={actual}")
    if actual_counts["database_write_performed"] != 0:
        add_finding(findings, "governance_closure_pilot_database_write_flagged", "试点包 database_write_performed 必须为 0。")

    ready_for_preview = str(manifest.get("ready_for_governance_closure_preview", ""))
    ready_for_apply = str(manifest.get("ready_for_lims_apply", ""))
    if (pending_batches or pending_evidence or pending_handoffs) and ready_for_preview != "no":
        add_finding(findings, "governance_closure_pilot_ready_preview_conflicts_with_pending_items", "仍有 pending 试点项时 ready_for_governance_closure_preview 必须为 no。")
    if ready_for_apply == "yes":
        add_finding(findings, "governance_closure_pilot_cannot_authorize_lims_apply", "试点包不能单独声明 ready_for_lims_apply=yes。")

    for key in ["overview", "rerun_commands", "readme"]:
        path = paths.get(key)
        if not path or not path.is_file():
            continue
        text = path.read_text(encoding="utf-8")
        for marker in ["不写数据库", "不代表人工评审通过", "不写入质量手册正文"]:
            if marker not in text:
                add_finding(findings, "governance_closure_pilot_doc_missing_guardrail", f"{path.name} 缺少边界标识：{marker}")

    return {
        "generated_at": dt.datetime.now().replace(microsecond=0).isoformat(),
        "pilot_dir": str(pilot_dir),
        "status": "passed" if not findings else "failed",
        "readiness": str(manifest.get("readiness", "")),
        "ready_for_governance_closure_preview": ready_for_preview,
        "ready_for_lims_apply": ready_for_apply,
        "counts": {**actual_counts, "findings": len(findings)},
        "findings": findings,
    }


def render_markdown(result: dict[str, Any]) -> str:
    lines = [
        "# QMS 治理关闭最小试点包 dry-run 验证报告",
        "",
        f"生成时间：{result['generated_at']}",
        f"试点包：`{result['pilot_dir']}`",
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
    parser.add_argument("--pilot-dir", required=True)
    parser.add_argument("--json-out")
    parser.add_argument("--md-out")
    args = parser.parse_args()

    result = check_pilot_pack(Path(args.pilot_dir))
    if args.json_out:
        Path(args.json_out).write_text(json.dumps(result, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
    if args.md_out:
        Path(args.md_out).write_text(render_markdown(result), encoding="utf-8")
    print(json.dumps(result, ensure_ascii=False, indent=2))
    return 0 if result["status"] == "passed" else 1


if __name__ == "__main__":
    raise SystemExit(main())
