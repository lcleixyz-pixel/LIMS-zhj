#!/usr/bin/env python3
"""第二轮自治治理台账 v0.2 的状态、回链和哈希门禁。"""

from __future__ import annotations

import hashlib
import importlib.util
import json
import re
import subprocess
import sys
from pathlib import Path
from typing import Any


ISSUE_STATES = ["open", "accepted", "remediating", "verifying", "closed", "escalated"]
FILE_STATES = ["draft_candidate", "returned", "verified_candidate", "sim_applied"]
SHA256 = re.compile(r"^[0-9a-f]{64}$")


def sha256(path: Path) -> str:
    return hashlib.sha256(path.read_bytes()).hexdigest()


def finding(code: str, message: str, location: str = "") -> dict[str, str]:
    return {"code": code, "message": message, "location": location}


def repo_root(package: Path) -> Path:
    for path in (package, *package.parents):
        if (path / ".git").exists():
            return path
    raise RuntimeError("无法定位仓库根目录")


def source_path(source: dict[str, Any], package: Path, root: Path) -> Path:
    if source["path_scope"] == "absolute":
        return Path(source["path"])
    if source["path_scope"] == "package":
        return package / source["path"]
    if source["path_scope"] == "project":
        return root / source["path"]
    raise ValueError(source["path_scope"])


def git_object_sha256(source: dict[str, Any], root: Path) -> str:
    content = subprocess.check_output(
        ["git", "show", f"{source['git_commit']}:{source['path']}"],
        cwd=root,
    )
    return hashlib.sha256(content).hexdigest()


def validate_ledger(ledger: dict[str, Any], package: Path) -> list[dict[str, str]]:
    findings: list[dict[str, str]] = []
    root = repo_root(package)
    if ledger.get("ledger_id") != "SIM-GOV-R2-LEDGER-002":
        findings.append(finding("wrong_ledger_id", "必须校验v0.2台账"))
    if ledger.get("issue_state_enum") != ISSUE_STATES:
        findings.append(finding("issue_enum_mismatch", "问题状态枚举不匹配"))
    if ledger.get("file_state_enum") != FILE_STATES:
        findings.append(finding("file_enum_mismatch", "文件状态枚举不匹配"))
    boundary = ledger.get("boundary", {})
    for key in (
        "production_write",
        "current_file_overwrite",
        "formal_approval",
        "formal_release",
        "self_verification",
        "old_validation_inherited",
    ):
        if boundary.get(key) is not False:
            findings.append(finding("unsafe_boundary", f"{key} 必须为false"))
    if boundary.get("verified_candidate_requires_independent_pass") is not True:
        findings.append(finding("missing_independent_gate", "verified_candidate缺少独立验证门禁"))
    if boundary.get("sim_applied_requires_replay_gate") is not True:
        findings.append(finding("missing_replay_gate", "sim_applied缺少复演门禁"))

    sources = {}
    for item in ledger.get("sources", []):
        source_id = item.get("source_id")
        if not source_id or source_id in sources:
            findings.append(finding("duplicate_or_empty_source", "来源编号为空或重复"))
            continue
        sources[source_id] = item
        expected = item.get("sha256", "")
        if not SHA256.fullmatch(expected):
            findings.append(finding("invalid_source_hash", f"{source_id}哈希格式错误"))
            continue
        if item.get("path_scope") == "git_commit":
            try:
                actual_hash = git_object_sha256(item, root)
            except (KeyError, subprocess.CalledProcessError):
                findings.append(finding("source_missing", f"{source_id}冻结git对象不存在"))
                continue
            if actual_hash != expected:
                findings.append(finding("source_hash_mismatch", f"{source_id}冻结git对象哈希不一致"))
            continue
        try:
            path = source_path(item, package, root)
        except (KeyError, ValueError):
            findings.append(finding("invalid_source_scope", f"{source_id}路径域错误"))
            continue
        if not path.is_file():
            findings.append(finding("source_missing", f"{source_id}来源不存在", str(path)))
        elif sha256(path) != expected:
            findings.append(finding("source_hash_mismatch", f"{source_id}来源哈希不一致"))

    file_map = {item.get("candidate_file_id"): item for item in ledger.get("file_candidates", [])}
    if set(file_map) != {"R2-FILE-001", "R2-FILE-002", "R2-FILE-003"}:
        findings.append(finding("file_set_mismatch", "文件候选集合不完整"))
    for file_id, item in file_map.items():
        state = item.get("file_state")
        if state not in FILE_STATES:
            findings.append(finding("invalid_file_state", f"{file_id}状态非法"))
        if state == "sim_applied":
            findings.append(finding("premature_sim_apply", f"{file_id}尚不得sim_applied"))
        payload = package / item.get("payload_path", "")
        if not payload.is_file():
            findings.append(finding("payload_missing", f"{file_id}载荷不存在"))
        elif sha256(payload) != item.get("payload_sha256"):
            findings.append(finding("payload_hash_mismatch", f"{file_id}载荷哈希不一致"))
        if item.get("version_relation", {}).get("replaces_current") is not False:
            findings.append(finding("current_replacement_forbidden", f"{file_id}不得替换现用文件"))
        for source_id in item.get("source_material_ids", []):
            if source_id not in sources:
                findings.append(finding("unknown_candidate_source", f"{file_id}引用未知来源{source_id}"))

    for file_id in ("R2-FILE-001", "R2-FILE-002"):
        item = file_map.get(file_id, {})
        if item.get("file_state") != "verified_candidate":
            findings.append(finding("independent_pass_not_recorded", f"{file_id}应为verified_candidate"))
        verification = item.get("verification", {})
        if verification.get("status") != "passed":
            findings.append(finding("independent_pass_not_recorded", f"{file_id}独立验证未记pass"))
        links = verification.get("result_links", [])
        if len(links) != 1 or links[0].get("source_id") != "SRC-INDEPENDENT-VALIDATION-R1":
            findings.append(finding("missing_independent_result_link", f"{file_id}缺少独立结论回链"))
        if item.get("replay", {}).get("status") != "not_run":
            findings.append(finding("premature_replay_claim", f"{file_id}尚未复演"))

    file003 = file_map.get("R2-FILE-003", {})
    if file003.get("file_state") != "draft_candidate":
        findings.append(finding("file003_premature_advance", "FILE-003须保持draft_candidate"))
    if file003.get("verification", {}).get("status") != "pending_independent_validation":
        findings.append(finding("file003_premature_advance", "FILE-003须等待新的独立验证"))
    if file003.get("verification", {}).get("machine_precheck", {}).get("not_an_independent_pass") is not True:
        findings.append(finding("machine_check_misrepresented", "机器检查不得冒充独立验证"))
    machine_payload = package / file003.get("machine_payload_path", "")
    if not machine_payload.is_file():
        findings.append(finding("machine_payload_missing", "FILE-003机器载荷不存在"))
    elif sha256(machine_payload) != file003.get("machine_payload_sha256"):
        findings.append(finding("machine_payload_hash_mismatch", "FILE-003机器载荷哈希不一致"))

    finding_map = {item.get("finding_id"): item for item in ledger.get("findings", [])}
    for finding_id in ("R2-FIND-007", "R2-FIND-008"):
        item = finding_map.get(finding_id, {})
        if item.get("issue_state") != "remediating":
            findings.append(finding("template_finding_state_mismatch", f"{finding_id}须为remediating"))
        if item.get("verification", {}).get("status") != "pending_independent_validation":
            findings.append(finding("template_finding_premature_close", f"{finding_id}仍须等待独立验证"))
        transitions = [(step.get("from"), step.get("to")) for step in item.get("transitions", [])]
        if transitions[-2:] != [("open", "accepted"), ("accepted", "remediating")]:
            findings.append(finding("transition_chain_mismatch", f"{finding_id}状态链不完整"))
        if any(not step.get("reason") for step in item.get("transitions", [])):
            findings.append(finding("transition_reason_missing", f"{finding_id}状态转换缺少理由"))
    for item in ledger.get("findings", []):
        if item.get("issue_state") == "closed":
            if item.get("verification", {}).get("status") != "passed":
                findings.append(finding("closed_without_verification", f"{item.get('finding_id')}未验证即关闭"))
            if item.get("replay", {}).get("status") != "passed":
                findings.append(finding("closed_without_replay", f"{item.get('finding_id')}未复演即关闭"))

    return findings


def validate_package(package: Path) -> list[dict[str, str]]:
    ledger_path = package / "自治治理主台账-v0.2.json"
    ledger = json.loads(ledger_path.read_text(encoding="utf-8"))
    findings = validate_ledger(ledger, package)
    validator_path = Path(__file__).with_name("validate_record_template_candidate_v02.py")
    spec = importlib.util.spec_from_file_location("record_candidate_validator", validator_path)
    if spec is None or spec.loader is None:
        return findings + [finding("record_validator_unavailable", "无法加载记录候选检查器")]
    module = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(module)
    candidate = json.loads(
        (package / "候选覆盖层" / "SIM-记录模板语义覆盖候选-v0.2.json").read_text(
            encoding="utf-8"
        )
    )
    findings.extend(module.validate_candidate(candidate, package))
    return findings


def main() -> int:
    package = Path(__file__).resolve().parent.parent
    findings = validate_package(package)
    print(json.dumps({"valid": not findings, "finding_count": len(findings), "findings": findings}, ensure_ascii=False, indent=2))
    return 1 if findings else 0


if __name__ == "__main__":
    sys.exit(main())
