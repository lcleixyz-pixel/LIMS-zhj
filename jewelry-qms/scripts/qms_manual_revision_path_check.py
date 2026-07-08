#!/usr/bin/env python3
"""Dry-run validation for the XZTC/SC manual revision path pack."""

from __future__ import annotations

import argparse
import csv
import datetime as dt
import json
import re
from pathlib import Path
from typing import Any


REQUIRED_FILES = {
    "manifest": "manual_revision_path_manifest.json",
    "overview": "00-质量手册修订换版路径总览.md",
    "existing_manual": "01-既有质量手册记录核对.csv",
    "revision_checklist": "02-修订换版路径闸门清单.csv",
    "lims_action_preview": "03-LIMS修订动作预览.csv",
    "human_decision_gates": "04-人工决策闸门.csv",
    "lims_action_notes": "05-LIMS修订动作说明.md",
    "readme": "README.md",
}

REQUIRED_GUARDRAILS = [
    "不写数据库",
    "不代表人工评审通过",
    "不得按同编号新增草稿直接写入",
    "既有文件修订/换版治理路径",
    "已取得 CMA",
    "CNAS 申请中",
    "jewelry-qms 仍为建设中系统",
    "不写入质量手册正文",
]

REQUIRED_GATE_IDS = {f"MR-{index:02d}" for index in range(1, 10)}
REQUIRED_DECISION_IDS = {f"MRD-{index:02d}" for index in range(1, 6)}
REQUIRED_ACTION_MODULES = {
    "documents",
    "document_revisions",
    "qms_structured_documents",
    "qms_document_blocks/qms_document_block_links",
    "document_distributions/document_reviews/approval evidence",
}

CNAS_OVERSTATEMENT_RE = re.compile(
    r"(本公司|公司|实验室|机构).{0,12}(已取得|已获|获得)\s*CNAS|CNAS\s*认可证书"
)


def read_text(path: Path) -> str:
    return path.read_text(encoding="utf-8")


def read_csv(path: Path) -> list[dict[str, str]]:
    with path.open("r", encoding="utf-8", newline="") as handle:
        return list(csv.DictReader(handle))


def fail(findings: list[dict[str, str]], rule_id: str, message: str, severity: str = "high") -> None:
    findings.append({"id": rule_id, "severity": severity, "message": message})


def check_count(manifest_counts: dict[str, Any], key: str, actual: int, findings: list[dict[str, str]]) -> None:
    try:
        expected = int(manifest_counts.get(key, -1))
    except (TypeError, ValueError):
        expected = -1
    if expected != actual:
        fail(findings, "manifest_count_mismatch_" + key, f"manifest {key}={manifest_counts.get(key)}，实际 {actual}。")


def check_pack(pack_dir: Path) -> dict[str, Any]:
    findings: list[dict[str, str]] = []
    manifest_path = pack_dir / REQUIRED_FILES["manifest"]
    if not manifest_path.exists():
        return {
            "generated_at": dt.datetime.now().isoformat(timespec="seconds"),
            "pack_dir": str(pack_dir),
            "status": "failed",
            "findings": [{"id": "missing_manifest", "severity": "high", "message": "缺少 manual_revision_path_manifest.json"}],
        }

    manifest = json.loads(read_text(manifest_path))
    if manifest.get("status") != "manual_revision_path_no_database_write":
        fail(findings, "invalid_manifest_status", "manifest 状态必须为 manual_revision_path_no_database_write。")
    if str(manifest.get("target_doc_number", "")) != "XZTC/SC":
        fail(findings, "invalid_target_doc_number", "target_doc_number 必须为 XZTC/SC。")
    if int(manifest.get("counts", {}).get("database_write_performed", -1)) != 0:
        fail(findings, "database_write_not_zero", "manifest 必须声明 database_write_performed=0。")

    guardrail_text = "\n".join(str(item) for item in manifest.get("guardrails", []))
    for marker in REQUIRED_GUARDRAILS:
        if marker not in guardrail_text:
            fail(findings, "manifest_missing_guardrail", "manifest 缺少边界标识：" + marker)

    files = manifest.get("files", {})
    for key, default_name in REQUIRED_FILES.items():
        filename = files.get(key, default_name)
        if not (pack_dir / filename).exists():
            fail(findings, "missing_" + key, "缺少质量手册修订路径包文件：" + filename)

    for path in list(pack_dir.rglob("*.sql")) + list(pack_dir.rglob("*.db")):
        fail(findings, "forbidden_database_artifact", "修订路径包不应包含数据库/SQL 文件：" + path.name)

    existing_rows = read_csv(pack_dir / files.get("existing_manual", REQUIRED_FILES["existing_manual"])) if (pack_dir / files.get("existing_manual", REQUIRED_FILES["existing_manual"])).exists() else []
    checklist_rows = read_csv(pack_dir / files.get("revision_checklist", REQUIRED_FILES["revision_checklist"])) if (pack_dir / files.get("revision_checklist", REQUIRED_FILES["revision_checklist"])).exists() else []
    action_rows = read_csv(pack_dir / files.get("lims_action_preview", REQUIRED_FILES["lims_action_preview"])) if (pack_dir / files.get("lims_action_preview", REQUIRED_FILES["lims_action_preview"])).exists() else []
    decision_rows = read_csv(pack_dir / files.get("human_decision_gates", REQUIRED_FILES["human_decision_gates"])) if (pack_dir / files.get("human_decision_gates", REQUIRED_FILES["human_decision_gates"])).exists() else []

    counts = manifest.get("counts", {})
    check_count(counts, "existing_manual_rows", len(existing_rows), findings)
    check_count(counts, "revision_gates", len(checklist_rows), findings)
    check_count(counts, "lims_action_preview_rows", len(action_rows), findings)
    check_count(counts, "human_decision_gates", len(decision_rows), findings)
    check_count(counts, "pending_human_decisions", sum(1 for row in decision_rows if row.get("decision_status") == "pending"), findings)

    if len(existing_rows) != 1:
        fail(findings, "existing_manual_row_count", "既有质量手册记录核对应只有 1 行。")
    for row in existing_rows:
        if row.get("doc_number") != "XZTC/SC":
            fail(findings, "existing_manual_wrong_doc_number", "既有质量手册行必须为 XZTC/SC。")
        if row.get("preview_action") != "plan_existing_document_revision":
            fail(findings, "manual_not_revision_path", "XZTC/SC 必须为 plan_existing_document_revision。")
        if row.get("existing_match") != "yes" or row.get("existing_status") != "published":
            fail(findings, "manual_existing_match_not_published", "XZTC/SC 必须匹配既有 published 文件。")
        if row.get("candidate_status") != "draft" or row.get("candidate_publish") != "0":
            fail(findings, "manual_candidate_not_draft", "第五版候选口径必须保持 draft/publish=0。")
        if row.get("revision_route_decision") != "existing_document_revision_required":
            fail(findings, "manual_route_decision_invalid", "修订路径决策必须为 existing_document_revision_required。")
        if row.get("no_write_marker") != "NO_DATABASE_WRITE_REHEARSAL_ONLY":
            fail(findings, "manual_missing_no_write_marker", "既有记录核对缺少 NO_DATABASE_WRITE_REHEARSAL_ONLY。")

    gate_ids = {row.get("gate_id", "") for row in checklist_rows}
    missing_gate_ids = sorted(REQUIRED_GATE_IDS - gate_ids)
    if missing_gate_ids:
        fail(findings, "missing_revision_gates", "修订路径闸门缺少：" + "、".join(missing_gate_ids))
    for row in checklist_rows:
        if row.get("blocking_if_unresolved") != "yes":
            fail(findings, "revision_gate_not_blocking", f"{row.get('gate_id')} 未设置阻断。")
        if row.get("gate_id") not in {"MR-01", "MR-09"} and row.get("current_status") != "pending_human_review":
            fail(findings, "revision_gate_should_be_pending", f"{row.get('gate_id')} 应保持 pending_human_review。")

    modules = {row.get("target_table_or_module", "") for row in action_rows}
    missing_modules = sorted(REQUIRED_ACTION_MODULES - modules)
    if missing_modules:
        fail(findings, "missing_lims_action_modules", "LIMS 动作预览缺少：" + "、".join(missing_modules))
    for row in action_rows:
        if row.get("allowed_now") != "no" or row.get("write_now") != "no":
            fail(findings, "lims_action_allowed_too_early", f"{row.get('action_id')} 必须保持 allowed_now=no 且 write_now=no。")
        if not row.get("blocked_by"):
            fail(findings, "lims_action_missing_blocker", f"{row.get('action_id')} 缺少 blocked_by。")

    decision_ids = {row.get("decision_id", "") for row in decision_rows}
    missing_decision_ids = sorted(REQUIRED_DECISION_IDS - decision_ids)
    if missing_decision_ids:
        fail(findings, "missing_human_decision_gates", "人工决策闸门缺少：" + "、".join(missing_decision_ids))
    for row in decision_rows:
        if row.get("decision_status") != "pending":
            fail(findings, "human_decision_not_pending", f"{row.get('decision_id')} 必须保持 pending。")
        if row.get("blocking_if_unresolved") != "yes":
            fail(findings, "human_decision_not_blocking", f"{row.get('decision_id')} 未设置阻断。")

    for key in ["overview", "lims_action_notes", "readme"]:
        filename = files.get(key, REQUIRED_FILES[key])
        path = pack_dir / filename
        if not path.exists():
            continue
        text = read_text(path)
        for marker in ["不写数据库", "不代表", "同编号新增草稿", "修订/换版", "CNAS 申请中"]:
            if marker not in text:
                fail(findings, "doc_missing_guardrail", f"{filename} 缺少边界标识：{marker}")
        if CNAS_OVERSTATEMENT_RE.search(text):
            fail(findings, "doc_overstates_cnas", f"{filename} 疑似包含已取得 CNAS 的越权表述。")
        if re.search(r"已批准发布|可以写库|准许写库|真实运行记录已形成", text):
            fail(findings, "doc_overstates_release_status", f"{filename} 疑似包含越权发布/写库表述。")

    status = "passed" if not findings else "failed"
    return {
        "generated_at": dt.datetime.now().isoformat(timespec="seconds"),
        "pack_dir": str(pack_dir),
        "status": status,
        "counts": {
            "existing_manual_rows": len(existing_rows),
            "revision_gates": len(checklist_rows),
            "lims_action_preview_rows": len(action_rows),
            "human_decision_gates": len(decision_rows),
            "pending_human_decisions": sum(1 for row in decision_rows if row.get("decision_status") == "pending"),
            "findings": len(findings),
        },
        "findings": findings,
    }


def render_markdown(result: dict[str, Any]) -> str:
    lines = [
        "# 质量手册修订/换版路径 dry-run 验证报告",
        "",
        f"生成时间：{result['generated_at']}",
        f"验证对象：`{result['pack_dir']}`",
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
        lines.append("未发现结构性问题。该结论只证明修订/换版路径包可读、计数一致且边界明确；不代表真实人工评审、受控发布或正式写库授权。")
    lines.append("")
    return "\n".join(lines)


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--pack-dir", required=True)
    parser.add_argument("--json-out")
    parser.add_argument("--md-out")
    args = parser.parse_args()

    result = check_pack(Path(args.pack_dir))
    print(json.dumps(result, ensure_ascii=False, indent=2))
    if args.json_out:
        Path(args.json_out).write_text(json.dumps(result, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
    if args.md_out:
        Path(args.md_out).write_text(render_markdown(result), encoding="utf-8")
    return 0 if result["status"] == "passed" else 1


if __name__ == "__main__":
    raise SystemExit(main())
