#!/usr/bin/env python3
"""Validate a no-write governance-closure pilot operator workbook."""

from __future__ import annotations

import argparse
import csv
import datetime as dt
import json
from pathlib import Path
from typing import Any


WORKBOOK_FILES = {
    "manifest": "governance_closure_pilot_operator_workbook_manifest.json",
    "overview": "00-试点人工执行工作簿总览.md",
    "master": "01-试点执行主清单.csv",
    "field_checklist": "02-逐字段填写清单.csv",
    "handoff_checklist": "03-签核交接核对表.csv",
    "rerun": "04-复跑与回填确认清单.md",
    "readme": "README.md",
    "task_card_dir": "task_cards",
}

REQUIRED_GUARDRAILS = [
    "不写数据库",
    "不修改试点包",
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


def check_workbook(workbook_dir: Path) -> dict[str, Any]:
    findings: list[dict[str, str]] = []
    manifest_path = workbook_dir / WORKBOOK_FILES["manifest"]
    manifest: dict[str, Any] = {}
    if not manifest_path.is_file():
        add_finding(findings, "missing_governance_closure_pilot_operator_workbook_manifest", "试点人工执行工作簿缺少 manifest。")
    else:
        manifest = json.loads(manifest_path.read_text(encoding="utf-8"))
        if manifest.get("status") != "governance_closure_pilot_operator_workbook_no_write":
            add_finding(findings, "invalid_governance_closure_pilot_operator_workbook_manifest_status", "试点人工执行工作簿 manifest 状态不符合预期。")
        guardrail_text = "\n".join(str(item) for item in manifest.get("guardrails", []))
        for marker in REQUIRED_GUARDRAILS:
            if marker not in guardrail_text:
                add_finding(findings, "governance_closure_pilot_operator_workbook_manifest_missing_guardrail", f"manifest 缺少边界标识：{marker}")

    files = dict(WORKBOOK_FILES)
    files.update({key: str(value) for key, value in manifest.get("files", {}).items()})
    paths: dict[str, Path] = {}
    for key, filename in files.items():
        path = workbook_dir / filename
        paths[key] = path
        if key == "task_card_dir":
            if not path.is_dir():
                add_finding(findings, "missing_governance_closure_pilot_operator_workbook_task_card_dir", "试点人工执行工作簿缺少 task_cards 目录。")
        elif not path.is_file():
            add_finding(findings, f"missing_governance_closure_pilot_operator_workbook_{key}", f"试点人工执行工作簿缺少文件：{filename}")

    for path in forbidden_database_artifacts(workbook_dir):
        add_finding(findings, "governance_closure_pilot_operator_workbook_forbidden_database_artifact", f"工作簿不应包含数据库/SQL 文件：{Path(path).name}")

    master_rows = read_csv(paths["master"]) if paths.get("master", Path()).is_file() else []
    field_rows = read_csv(paths["field_checklist"]) if paths.get("field_checklist", Path()).is_file() else []
    handoff_rows = read_csv(paths["handoff_checklist"]) if paths.get("handoff_checklist", Path()).is_file() else []
    task_cards = sorted(paths["task_card_dir"].glob("*.md")) if paths.get("task_card_dir", Path()).is_dir() else []

    item_ids: set[str] = set()
    pending_items = 0
    for index, row in enumerate(master_rows, start=2):
        item_id = row.get("workbook_item_id", "").strip()
        label = item_id or f"主清单第 {index} 行"
        if not item_id:
            add_finding(findings, "governance_closure_pilot_operator_workbook_blank_item_id", "主清单 workbook_item_id 为空。")
        elif item_id in item_ids:
            add_finding(findings, "governance_closure_pilot_operator_workbook_duplicate_item_id", f"主清单 workbook_item_id 重复：{item_id}")
        item_ids.add(item_id)
        for field in ["not_imported", "not_real_record"]:
            if row.get(field, "") != "yes":
                add_finding(findings, "governance_closure_pilot_operator_workbook_marker_missing", f"{label} 必须保留 {field}=yes。")
        if row.get("workbook_status") not in {"pending", "ready_for_return_preview"}:
            add_finding(findings, "governance_closure_pilot_operator_workbook_status_invalid", f"{label} workbook_status 不合法。")
        if row.get("workbook_status") == "pending":
            pending_items += 1
            if row.get("blocks_apply") != "yes":
                add_finding(findings, "governance_closure_pilot_operator_workbook_pending_not_blocking", f"{label} pending 时 blocks_apply 应为 yes。")

    pending_fields = 0
    field_ids: set[str] = set()
    for index, row in enumerate(field_rows, start=2):
        field_id = row.get("field_task_id", "").strip()
        label = field_id or f"字段清单第 {index} 行"
        if not field_id:
            add_finding(findings, "governance_closure_pilot_operator_workbook_blank_field_task_id", "字段清单 field_task_id 为空。")
        elif field_id in field_ids:
            add_finding(findings, "governance_closure_pilot_operator_workbook_duplicate_field_task_id", f"字段清单 field_task_id 重复：{field_id}")
        field_ids.add(field_id)
        if row.get("workbook_item_id", "") not in item_ids:
            add_finding(findings, "governance_closure_pilot_operator_workbook_field_unknown_item", f"{label} 指向不存在的 workbook_item_id。")
        for field in ["not_imported", "not_real_record"]:
            if row.get(field, "") != "yes":
                add_finding(findings, "governance_closure_pilot_operator_workbook_field_marker_missing", f"{label} 必须保留 {field}=yes。")
        if row.get("field_status") not in {"pending", "completed"}:
            add_finding(findings, "governance_closure_pilot_operator_workbook_field_status_invalid", f"{label} field_status 不合法。")
        if row.get("field_status") == "pending":
            pending_fields += 1
            if row.get("blocks_apply") != "yes":
                add_finding(findings, "governance_closure_pilot_operator_workbook_field_pending_not_blocking", f"{label} pending 时 blocks_apply 应为 yes。")

    pending_handoffs = 0
    handoff_item_ids: set[str] = set()
    for index, row in enumerate(handoff_rows, start=2):
        label = row.get("pilot_handoff_id", "") or f"签核交接第 {index} 行"
        item_id = row.get("workbook_item_id", "")
        handoff_item_ids.add(item_id)
        if item_id not in item_ids:
            add_finding(findings, "governance_closure_pilot_operator_workbook_handoff_unknown_item", f"{label} 指向不存在的 workbook_item_id。")
        for field in ["not_imported", "not_real_record"]:
            if row.get(field, "") != "yes":
                add_finding(findings, "governance_closure_pilot_operator_workbook_handoff_marker_missing", f"{label} 必须保留 {field}=yes。")
        if row.get("signature_status") not in {"pending", "completed", "rejected"}:
            add_finding(findings, "governance_closure_pilot_operator_workbook_signature_status_invalid", f"{label} signature_status 不合法。")
        if row.get("handoff_status") not in {"pending", "completed", "rejected"}:
            add_finding(findings, "governance_closure_pilot_operator_workbook_handoff_status_invalid", f"{label} handoff_status 不合法。")
        if row.get("workbook_status") != "completed":
            pending_handoffs += 1
            if row.get("blocks_apply") != "yes":
                add_finding(findings, "governance_closure_pilot_operator_workbook_handoff_pending_not_blocking", f"{label} 未完成时 blocks_apply 应为 yes。")

    if handoff_item_ids and handoff_item_ids != item_ids:
        add_finding(findings, "governance_closure_pilot_operator_workbook_handoff_item_set_mismatch", "签核交接核对表未覆盖全部试点主任务。")

    actual_counts = {
        "pilot_items": len(master_rows),
        "field_fill_items": len(field_rows),
        "handoff_check_items": len(handoff_rows),
        "task_cards": len(task_cards),
        "pending_workbook_items": pending_items,
        "pending_field_items": pending_fields,
        "pending_handoff_items": pending_handoffs,
        "source_missing_fields": int(manifest.get("counts", {}).get("source_missing_fields", 0)),
        "source_blocked_patches": int(manifest.get("counts", {}).get("source_blocked_patches", 0)),
        "source_workbench_modified": int(manifest.get("counts", {}).get("source_workbench_modified", 0)),
        "database_write_performed": int(manifest.get("counts", {}).get("database_write_performed", 0)),
    }
    for key, actual in actual_counts.items():
        if key in manifest.get("counts", {}) and int(manifest["counts"][key]) != actual:
            add_finding(findings, f"governance_closure_pilot_operator_workbook_count_mismatch_{key}", f"{key} 计数不一致：manifest={manifest['counts'][key]}，actual={actual}")

    if actual_counts["source_workbench_modified"] != 0:
        add_finding(findings, "governance_closure_pilot_operator_workbook_source_modified_flagged", "source_workbench_modified 必须为 0。")
    if actual_counts["database_write_performed"] != 0:
        add_finding(findings, "governance_closure_pilot_operator_workbook_database_write_flagged", "database_write_performed 必须为 0。")

    ready_for_return = str(manifest.get("ready_for_pilot_return_preview", ""))
    ready_for_source = str(manifest.get("ready_for_source_workbench_update", ""))
    ready_for_apply = str(manifest.get("ready_for_lims_apply", ""))
    if (pending_items > 0 or pending_fields > 0 or pending_handoffs > 0) and ready_for_return != "no":
        add_finding(findings, "governance_closure_pilot_operator_workbook_ready_conflicts_with_pending", "仍有 pending 项时 ready_for_pilot_return_preview 必须为 no。")
    if ready_for_return != "yes" and ready_for_source == "yes":
        add_finding(findings, "governance_closure_pilot_operator_workbook_source_update_conflicts_with_return", "工作簿未 ready 时 ready_for_source_workbench_update 必须为 no。")
    if ready_for_apply == "yes":
        add_finding(findings, "governance_closure_pilot_operator_workbook_cannot_authorize_lims_apply", "试点人工执行工作簿不能单独声明 ready_for_lims_apply=yes。")

    for key in ["overview", "rerun", "readme"]:
        path = paths.get(key)
        if not path or not path.is_file():
            continue
        text = path.read_text(encoding="utf-8")
        for marker in ["不写数据库", "不代表人工评审通过", "不写入质量手册正文"]:
            if marker not in text:
                add_finding(findings, "governance_closure_pilot_operator_workbook_doc_missing_guardrail", f"{path.name} 缺少边界标识：{marker}")

    return {
        "generated_at": dt.datetime.now().replace(microsecond=0).isoformat(),
        "workbook_dir": str(workbook_dir),
        "status": "passed" if not findings else "failed",
        "readiness": str(manifest.get("readiness", "")),
        "ready_for_pilot_return_preview": ready_for_return,
        "ready_for_source_workbench_update": ready_for_source,
        "ready_for_lims_apply": ready_for_apply,
        "counts": {**actual_counts, "findings": len(findings)},
        "findings": findings,
    }


def render_markdown(result: dict[str, Any]) -> str:
    lines = [
        "# QMS 治理关闭试点人工执行工作簿 dry-run 验证报告",
        "",
        f"生成时间：{result['generated_at']}",
        f"工作簿：`{result['workbook_dir']}`",
        f"结论：{result['status']}",
        f"readiness：{result.get('readiness', '')}",
        f"ready_for_pilot_return_preview：{result.get('ready_for_pilot_return_preview', '')}",
        f"ready_for_source_workbench_update：{result.get('ready_for_source_workbench_update', '')}",
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
    parser.add_argument("--workbook-dir", required=True)
    parser.add_argument("--json-out")
    parser.add_argument("--md-out")
    args = parser.parse_args()

    result = check_workbook(Path(args.workbook_dir))
    if args.json_out:
        Path(args.json_out).write_text(json.dumps(result, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
    if args.md_out:
        Path(args.md_out).write_text(render_markdown(result), encoding="utf-8")
    print(json.dumps(result, ensure_ascii=False, indent=2))
    return 0 if result["status"] == "passed" else 1


if __name__ == "__main__":
    raise SystemExit(main())
