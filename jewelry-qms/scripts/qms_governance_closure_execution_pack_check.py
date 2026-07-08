#!/usr/bin/env python3
"""Dry-run validation for the governance closure execution pack."""

from __future__ import annotations

import argparse
import csv
import datetime as dt
import json
import re
from pathlib import Path
from typing import Any


REQUIRED_FILES = {
    "manifest": "governance_closure_execution_manifest.json",
    "overview": "00-治理闭环执行包总览.md",
    "execution_batches": "01-闭环执行批次.csv",
    "signature_register": "02-岗位签核页模板.csv",
    "handoff_checklist": "03-交接复核清单.csv",
    "route_index": "04-回填路径索引.csv",
    "blocking_summary": "05-阻断批次摘要.md",
    "readme": "README.md",
}

REQUIRED_GUARDRAILS = [
    "不写数据库",
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

CNAS_OVERSTATEMENT_RE = re.compile(
    r"(本公司|公司|实验室|机构).{0,12}(已取得|已获|获得)\s*CNAS|CNAS\s*认可证书"
)


def read_csv(path: Path) -> list[dict[str, str]]:
    with path.open("r", encoding="utf-8-sig", newline="") as handle:
        return list(csv.DictReader(handle))


def fail(findings: list[dict[str, str]], rule_id: str, message: str, severity: str = "high") -> None:
    findings.append({"id": rule_id, "severity": severity, "message": message})


def int_count(value: Any) -> int:
    try:
        return int(value)
    except (TypeError, ValueError):
        return -1


def marker_checks(findings: list[dict[str, str]], row: dict[str, str], row_name: str, require_not_imported: bool = True) -> None:
    if row.get("not_real_record") != "yes":
        fail(findings, "not_real_marker_missing", row_name + " 必须保留 not_real_record=yes。")
    if require_not_imported and row.get("not_imported") != "yes":
        fail(findings, "not_imported_marker_missing", row_name + " 必须保留 not_imported=yes。")


def check_execution_pack(execution_dir: Path) -> dict[str, Any]:
    findings: list[dict[str, str]] = []
    manifest_path = execution_dir / REQUIRED_FILES["manifest"]
    if not manifest_path.exists():
        return {
            "generated_at": dt.datetime.now().isoformat(timespec="seconds"),
            "execution_dir": str(execution_dir),
            "status": "failed",
            "findings": [{"id": "missing_manifest", "severity": "high", "message": "缺少 governance_closure_execution_manifest.json"}],
        }

    manifest = json.loads(manifest_path.read_text(encoding="utf-8"))
    if manifest.get("status") != "governance_closure_execution_pack_no_database_write":
        fail(findings, "invalid_manifest_status", "manifest 状态必须为 governance_closure_execution_pack_no_database_write。")

    guardrail_text = "\n".join(str(item) for item in manifest.get("guardrails", []))
    for marker in REQUIRED_GUARDRAILS:
        if marker not in guardrail_text:
            fail(findings, "manifest_missing_guardrail", "manifest 缺少边界标识：" + marker)

    files = manifest.get("files", {})
    for key, filename in REQUIRED_FILES.items():
        actual = files.get(key, filename)
        if not (execution_dir / actual).exists():
            fail(findings, "missing_" + key, "缺少治理闭环执行包文件：" + actual)

    for path in list(execution_dir.rglob("*.sql")) + list(execution_dir.rglob("*.db")):
        fail(findings, "forbidden_database_artifact", "治理闭环执行包不应包含数据库/SQL 文件：" + path.name)

    batch_rows: list[dict[str, str]] = []
    signature_rows: list[dict[str, str]] = []
    handoff_rows: list[dict[str, str]] = []
    route_rows: list[dict[str, str]] = []
    if (execution_dir / files.get("execution_batches", REQUIRED_FILES["execution_batches"])).exists():
        batch_rows = read_csv(execution_dir / files.get("execution_batches", REQUIRED_FILES["execution_batches"]))
    if (execution_dir / files.get("signature_register", REQUIRED_FILES["signature_register"])).exists():
        signature_rows = read_csv(execution_dir / files.get("signature_register", REQUIRED_FILES["signature_register"]))
    if (execution_dir / files.get("handoff_checklist", REQUIRED_FILES["handoff_checklist"])).exists():
        handoff_rows = read_csv(execution_dir / files.get("handoff_checklist", REQUIRED_FILES["handoff_checklist"]))
    if (execution_dir / files.get("route_index", REQUIRED_FILES["route_index"])).exists():
        route_rows = read_csv(execution_dir / files.get("route_index", REQUIRED_FILES["route_index"]))

    batch_ids: set[str] = set()
    blocking_batch_count_sum = 0
    batch_by_key: set[tuple[str, str, str]] = set()
    for index, row in enumerate(batch_rows, start=2):
        batch_id = row.get("execution_batch_id", "").strip()
        row_name = batch_id or f"闭环执行批次第 {index} 行"
        if not batch_id:
            fail(findings, "blank_execution_batch_id", "闭环执行批次第 " + str(index) + " 行 execution_batch_id 为空。")
        elif batch_id in batch_ids:
            fail(findings, "duplicate_execution_batch_id", "闭环执行批次存在重复 execution_batch_id：" + batch_id)
        batch_ids.add(batch_id)
        marker_checks(findings, row, row_name)
        if row.get("execution_status") not in {"pending", "completed", "rejected"}:
            fail(findings, "execution_status_invalid", row_name + " execution_status 必须为 pending/completed/rejected。")
        blocking_batch_count_sum += max(int_count(row.get("blocking_count")), 0)
        batch_by_key.add((row.get("owner_role", ""), row.get("gate_id", ""), row.get("task_group", "")))

    pending_signature_rows = 0
    signature_ids: set[str] = set()
    for index, row in enumerate(signature_rows, start=2):
        signature_id = row.get("signature_id", "").strip()
        row_name = signature_id or f"岗位签核第 {index} 行"
        if not signature_id:
            fail(findings, "blank_signature_id", "岗位签核第 " + str(index) + " 行 signature_id 为空。")
        elif signature_id in signature_ids:
            fail(findings, "duplicate_signature_id", "岗位签核存在重复 signature_id：" + signature_id)
        signature_ids.add(signature_id)
        marker_checks(findings, row, row_name)
        status = row.get("signature_status", "")
        if status not in {"pending", "completed", "rejected"}:
            fail(findings, "signature_status_invalid", row_name + " signature_status 必须为 pending/completed/rejected。")
        if status == "pending":
            pending_signature_rows += 1
        if status == "completed":
            missing = [field for field in ["assigned_person", "reviewer", "actual_finish_date"] if not row.get(field, "").strip()]
            if missing:
                fail(findings, "completed_signature_missing_fields", row_name + " 已 completed 但缺少字段：" + "、".join(missing))
        if row.get("required_before_refresh") not in {"yes", "no"}:
            fail(findings, "signature_required_flag_invalid", row_name + " required_before_refresh 必须为 yes/no。")

    pending_handoff_checks = 0
    handoff_ids: set[str] = set()
    handoff_batch_ids: set[str] = set()
    for index, row in enumerate(handoff_rows, start=2):
        check_id = row.get("handoff_check_id", "").strip()
        row_name = check_id or f"交接复核第 {index} 行"
        if not check_id:
            fail(findings, "blank_handoff_check_id", "交接复核第 " + str(index) + " 行 handoff_check_id 为空。")
        elif check_id in handoff_ids:
            fail(findings, "duplicate_handoff_check_id", "交接复核存在重复 handoff_check_id：" + check_id)
        handoff_ids.add(check_id)
        handoff_batch_ids.add(row.get("execution_batch_id", ""))
        marker_checks(findings, row, row_name)
        if row.get("check_status") not in {"pending", "completed", "rejected"}:
            fail(findings, "handoff_check_status_invalid", row_name + " check_status 必须为 pending/completed/rejected。")
        if row.get("check_status") == "pending":
            pending_handoff_checks += 1
        if row.get("blocks_apply") not in {"yes", "no"}:
            fail(findings, "handoff_blocks_apply_invalid", row_name + " blocks_apply 必须为 yes/no。")
        if row.get("execution_batch_id", "") and row.get("execution_batch_id", "") not in batch_ids:
            fail(findings, "handoff_unknown_batch", row_name + " 指向不存在的 execution_batch_id。")

    route_ids: set[str] = set()
    pending_route_items = 0
    blocking_route_items = 0
    routes_without_batch = 0
    for index, row in enumerate(route_rows, start=2):
        closure_id = row.get("closure_item_id", "").strip()
        row_name = closure_id or f"回填路径第 {index} 行"
        if not closure_id:
            fail(findings, "blank_closure_item_id", "回填路径第 " + str(index) + " 行 closure_item_id 为空。")
        elif closure_id in route_ids:
            fail(findings, "duplicate_closure_item_id", "回填路径存在重复 closure_item_id：" + closure_id)
        route_ids.add(closure_id)
        marker_checks(findings, row, row_name)
        if row.get("route_status") not in {"pending", "ready", "rejected"}:
            fail(findings, "route_status_invalid", row_name + " route_status 必须为 pending/ready/rejected。")
        if row.get("route_status") == "pending":
            pending_route_items += 1
        if row.get("blocks_apply") == "yes":
            blocking_route_items += 1
        elif row.get("blocks_apply") != "no":
            fail(findings, "route_blocks_apply_invalid", row_name + " blocks_apply 必须为 yes/no。")
        batch_id = row.get("execution_batch_id", "")
        if not batch_id or batch_id not in batch_ids:
            routes_without_batch += 1
            fail(findings, "route_unknown_batch", row_name + " 未匹配到有效 execution_batch_id。")

    if handoff_batch_ids - batch_ids:
        fail(findings, "handoff_batch_set_mismatch", "交接复核清单存在不属于执行批次的 execution_batch_id。")

    counts = manifest.get("counts", {})
    actual_counts = {
        "execution_batches": len(batch_rows),
        "signature_rows": len(signature_rows),
        "handoff_checks": len(handoff_rows),
        "route_rows": len(route_rows),
        "source_closure_items": len(route_rows),
        "blocking_route_items": blocking_route_items,
        "pending_signature_rows": pending_signature_rows,
        "pending_handoff_checks": pending_handoff_checks,
        "pending_route_items": pending_route_items,
        "database_write_performed": int_count(counts.get("database_write_performed")),
    }
    for key, actual in actual_counts.items():
        if int_count(counts.get(key)) != actual:
            fail(findings, "manifest_count_mismatch_" + key, f"manifest {key}={counts.get(key)}，实际 {actual}。")

    if blocking_batch_count_sum != blocking_route_items:
        fail(findings, "blocking_count_sum_mismatch", f"执行批次 blocking_count 合计 {blocking_batch_count_sum}，回填路径 blocking_route_items 实际 {blocking_route_items}。")
    if routes_without_batch:
        fail(findings, "routes_without_batch", f"{routes_without_batch} 条回填路径未匹配到执行批次。")
    if actual_counts["database_write_performed"] != 0:
        fail(findings, "database_write_not_zero", "治理闭环执行包 database_write_performed 必须为 0。")
    if (
        pending_signature_rows > 0 or pending_handoff_checks > 0 or pending_route_items > 0
    ) and manifest.get("ready_for_governance_closure_preview") != "no":
        fail(findings, "ready_preview_flag_conflicts_with_pending_items", "仍有 pending 签核/交接/路径时 ready_for_governance_closure_preview 必须为 no。")
    if manifest.get("ready_for_lims_apply") == "yes":
        fail(findings, "execution_pack_cannot_authorize_lims_apply", "治理闭环执行包不能单独声明 ready_for_lims_apply=yes。")

    for key in ["overview", "blocking_summary", "readme"]:
        path = execution_dir / files.get(key, REQUIRED_FILES[key])
        if not path.exists():
            continue
        text = path.read_text(encoding="utf-8")
        if CNAS_OVERSTATEMENT_RE.search(text):
            fail(findings, "doc_overstates_cnas", f"{path.name} 疑似包含已取得 CNAS 的越权表述。")
        for marker in ["不写数据库", "不代表人工评审通过", "不写入质量手册正文"]:
            if marker not in text:
                fail(findings, "doc_missing_guardrail", f"{path.name} 缺少边界标识：{marker}")

    status = "passed" if not findings else "failed"
    readiness = manifest.get("readiness", "")
    return {
        "generated_at": dt.datetime.now().isoformat(timespec="seconds"),
        "execution_dir": str(execution_dir),
        "status": status,
        "readiness": readiness,
        "ready_for_governance_closure_preview": manifest.get("ready_for_governance_closure_preview", ""),
        "ready_for_lims_apply": manifest.get("ready_for_lims_apply", ""),
        "counts": {**actual_counts, "findings": len(findings)},
        "findings": findings,
    }


def render_markdown(result: dict[str, Any]) -> str:
    lines = [
        "# QMS 治理闭环执行包 dry-run 验证报告",
        "",
        f"生成时间：{result['generated_at']}",
        f"治理闭环执行包：`{result['execution_dir']}`",
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
    lines.append("")
    if result.get("findings"):
        lines.extend(["## 发现项", ""])
        for finding in result["findings"]:
            lines.append(f"- [{finding['severity']}] {finding['id']}：{finding['message']}")
    else:
        lines.extend(
            [
                "## 发现项",
                "",
                "未发现结构性问题。该结论不代表已人工评审通过、已完成真实培训、已受控发布或已授权写库。",
            ]
        )
    lines.append("")
    return "\n".join(lines)


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--execution-dir", required=True)
    parser.add_argument("--json-out")
    parser.add_argument("--md-out")
    args = parser.parse_args()

    result = check_execution_pack(Path(args.execution_dir))
    if args.json_out:
        Path(args.json_out).write_text(json.dumps(result, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
    if args.md_out:
        Path(args.md_out).write_text(render_markdown(result), encoding="utf-8")
    print(json.dumps(result, ensure_ascii=False, indent=2))
    return 0 if result["status"] == "passed" else 1


if __name__ == "__main__":
    raise SystemExit(main())
