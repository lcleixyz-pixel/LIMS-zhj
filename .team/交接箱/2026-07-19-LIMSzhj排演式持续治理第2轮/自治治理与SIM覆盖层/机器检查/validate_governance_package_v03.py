#!/usr/bin/env python3
"""校验自治治理台账 v0.3 仅执行独立 PASS 授权的状态推进。"""

from __future__ import annotations

import copy
import hashlib
import json
import sys
from pathlib import Path
from typing import Any


HERE = Path(__file__).resolve().parent
PACKAGE = HERE.parent
ROUND_DIR = PACKAGE.parent
EXPECTED_REPORT_SHA256 = "ee8d59fc29eba6daaf14ed09afcdd98b22e051ad42af2e3f2add4dfb58c8e421"


def sha256(path: Path) -> str:
    return hashlib.sha256(path.read_bytes()).hexdigest()


def finding(code: str, message: str) -> dict[str, str]:
    return {"code": code, "message": message}


def file_map(ledger: dict[str, Any]) -> dict[str, dict[str, Any]]:
    return {item["candidate_file_id"]: item for item in ledger["file_candidates"]}


def issue_map(ledger: dict[str, Any]) -> dict[str, dict[str, Any]]:
    return {item["finding_id"]: item for item in ledger["findings"]}


def validate_ledger(v03: dict[str, Any], v02: dict[str, Any]) -> list[dict[str, str]]:
    findings: list[dict[str, str]] = []
    if v03.get("ledger_id") != "SIM-GOV-R2-LEDGER-003":
        findings.append(finding("wrong_ledger_id", "必须为v0.3台账"))
    previous = v03.get("previous_ledger", {})
    if previous.get("path") != "自治治理主台账-v0.2.json":
        findings.append(finding("previous_ledger_missing", "缺少v0.2版本关系"))
    if previous.get("sha256") != sha256(PACKAGE / "自治治理主台账-v0.2.json"):
        findings.append(finding("previous_ledger_hash_mismatch", "v0.2台账哈希不匹配"))

    report = ROUND_DIR / "独立验证" / "独立验证-记录模板语义覆盖候选-v0.2.md"
    if not report.is_file() or sha256(report) != EXPECTED_REPORT_SHA256:
        findings.append(finding("independent_report_hash_mismatch", "新独立验证报告缺失或哈希不匹配"))
    source = next(
        (
            item
            for item in v03.get("sources", [])
            if item.get("source_id") == "SRC-INDEPENDENT-TEMPLATE-V02-PASS"
        ),
        None,
    )
    if source is None or source.get("sha256") != EXPECTED_REPORT_SHA256:
        findings.append(finding("independent_report_not_registered", "独立PASS未作为冻结来源登记"))

    old_files = file_map(v02)
    new_files = file_map(v03)
    if set(new_files) != {"R2-FILE-001", "R2-FILE-002", "R2-FILE-003"}:
        findings.append(finding("file_set_mismatch", "文件候选集合错误"))
    for file_id in ("R2-FILE-001", "R2-FILE-002", "R2-FILE-003"):
        item = new_files.get(file_id, {})
        if item.get("file_state") != "verified_candidate":
            findings.append(finding("candidate_state_mismatch", f"{file_id}必须为verified_candidate"))
        if item.get("verification", {}).get("status") != "passed":
            findings.append(finding("candidate_pass_missing", f"{file_id}缺少独立PASS"))
        if item.get("replay", {}).get("status") != "not_run":
            findings.append(finding("premature_replay_claim", f"{file_id}不得宣称复演"))
        if item.get("file_state") == "sim_applied":
            findings.append(finding("premature_sim_apply", f"{file_id}不得sim_applied"))
        payload = PACKAGE / item.get("payload_path", "")
        if not payload.is_file() or sha256(payload) != item.get("payload_sha256"):
            findings.append(finding("candidate_payload_changed", f"{file_id}候选载荷改变"))
        if item.get("payload_sha256") != old_files.get(file_id, {}).get("payload_sha256"):
            findings.append(finding("candidate_payload_changed", f"{file_id}候选哈希相对v0.2改变"))

    for file_id in ("R2-FILE-001", "R2-FILE-002"):
        if new_files.get(file_id) != old_files.get(file_id):
            findings.append(finding("unapproved_file_change", f"{file_id}不应在v0.3发生变化"))

    file003 = new_files.get("R2-FILE-003", {})
    links = file003.get("verification", {}).get("result_links", [])
    if len(links) != 1 or links[0].get("sha256") != EXPECTED_REPORT_SHA256:
        findings.append(finding("file003_pass_link_missing", "FILE-003未回链新的独立PASS"))
    if "不授权sim_applied" not in file003.get("verification", {}).get("scope_limit", ""):
        findings.append(finding("file003_scope_limit_missing", "FILE-003缺少不得应用边界"))

    old_issues = issue_map(v02)
    new_issues = issue_map(v03)
    for finding_id in ("R2-FIND-007", "R2-FIND-008"):
        item = new_issues.get(finding_id, {})
        if item.get("issue_state") != "verifying":
            findings.append(finding("finding_state_mismatch", f"{finding_id}必须为verifying"))
        if item.get("verification", {}).get("status") != (
            "candidate_validation_passed_pending_sim_application_and_replay"
        ):
            findings.append(finding("finding_gate_mismatch", f"{finding_id}必须等待应用与复演"))
        if item.get("replay", {}).get("status") != "not_run":
            findings.append(finding("premature_replay_claim", f"{finding_id}不得宣称复演"))
        transitions = item.get("transitions", [])
        if not transitions or (
            transitions[-1].get("from"),
            transitions[-1].get("to"),
        ) != ("remediating", "verifying"):
            findings.append(finding("finding_transition_missing", f"{finding_id}转换链错误"))
        if transitions[:-1] != old_issues.get(finding_id, {}).get("transitions", []):
            findings.append(finding("finding_history_changed", f"{finding_id}历史转换被改写"))

    for item in v03.get("findings", []):
        if item.get("finding_id") in {"R2-FIND-007", "R2-FIND-008"} and item.get(
            "issue_state"
        ) == "closed":
            findings.append(finding("premature_finding_close", f"{item['finding_id']}不得关闭"))
    if any(item.get("file_state") == "sim_applied" for item in v03.get("file_candidates", [])):
        findings.append(finding("premature_sim_apply", "本轮不得存在sim_applied候选"))
    if v03.get("independent_validation", {}).get("no_candidate_sim_applied") is not True:
        findings.append(finding("application_boundary_missing", "台账未声明无候选已应用"))
    return findings


def validate_package() -> list[dict[str, str]]:
    v02 = json.loads((PACKAGE / "自治治理主台账-v0.2.json").read_text(encoding="utf-8"))
    v03 = json.loads((PACKAGE / "自治治理主台账-v0.3.json").read_text(encoding="utf-8"))
    return validate_ledger(v03, v02)


def main() -> int:
    findings = validate_package()
    print(
        json.dumps(
            {"valid": not findings, "finding_count": len(findings), "findings": findings},
            ensure_ascii=False,
            indent=2,
        )
    )
    return 1 if findings else 0


if __name__ == "__main__":
    sys.exit(main())
