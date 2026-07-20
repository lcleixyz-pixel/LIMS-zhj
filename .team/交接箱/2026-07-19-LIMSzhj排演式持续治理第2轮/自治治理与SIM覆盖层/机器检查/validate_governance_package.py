#!/usr/bin/env python3
"""第二轮自治治理台账和 SIM 覆盖层的只读机器检查器。"""

from __future__ import annotations

import argparse
import hashlib
import json
import re
import sys
from pathlib import Path
from typing import Any


ISSUE_STATES = ["open", "accepted", "remediating", "verifying", "closed", "escalated"]
FILE_STATES = ["draft_candidate", "returned", "verified_candidate", "sim_applied"]
FINDING_ID = re.compile(r"^R2-FIND-\d{3}$")
FILE_ID = re.compile(r"^R2-FILE-\d{3}$")
CHANGE_ID = re.compile(r"^R2-CHG-[MPT]-\d{3}$")
SHA256 = re.compile(r"^[0-9a-f]{64}$")
FORBIDDEN_RELEASE_PATTERNS = [
    re.compile(r"已正式批准"),
    re.compile(r"已经正式批准"),
    re.compile(r"已正式发布"),
    re.compile(r"已经正式发布"),
    re.compile(r"已正式生效"),
    re.compile(r"已经正式生效"),
    re.compile(r"已替换现用文件"),
    re.compile(r"现用文件已被替换"),
    re.compile(r"已获CNAS认可", re.IGNORECASE),
    re.compile(r"已通过(?:CNAS|CMA)评审", re.IGNORECASE),
]


def finding(code: str, message: str, location: str = "") -> dict[str, str]:
    return {"code": code, "message": message, "location": location}


def file_sha256(path: Path) -> str:
    digest = hashlib.sha256()
    with path.open("rb") as handle:
        for chunk in iter(lambda: handle.read(1024 * 1024), b""):
            digest.update(chunk)
    return digest.hexdigest()


def project_root_from(package_dir: Path) -> Path | None:
    for candidate in [package_dir, *package_dir.parents]:
        if (candidate / ".git").exists():
            return candidate
    return None


def resolve_source_path(source: dict[str, Any], package_dir: Path, project_root: Path | None) -> Path | None:
    scope = source.get("path_scope")
    raw_path = source.get("path")
    if not isinstance(raw_path, str) or not raw_path:
        return None
    if scope == "absolute":
        return Path(raw_path)
    if scope == "package":
        return package_dir / raw_path
    if scope == "project" and project_root is not None:
        return project_root / raw_path
    return None


def require_nonempty(
    record: dict[str, Any],
    keys: list[str],
    code: str,
    location: str,
    findings: list[dict[str, str]],
) -> None:
    for key in keys:
        value = record.get(key)
        if value is None or value == "" or value == [] or value == {}:
            findings.append(finding(code, f"必填字段为空：{key}", location))


def validate_ledger(ledger: dict[str, Any], package_dir: Path) -> list[dict[str, str]]:
    findings: list[dict[str, str]] = []
    project_root = project_root_from(package_dir)

    if ledger.get("issue_state_enum") != ISSUE_STATES:
        findings.append(finding("issue_enum_mismatch", "问题状态枚举必须与冻结枚举完全一致。"))
    if ledger.get("file_state_enum") != FILE_STATES:
        findings.append(finding("file_enum_mismatch", "文件状态枚举必须与冻结枚举完全一致。"))

    boundary = ledger.get("boundary")
    if not isinstance(boundary, dict):
        findings.append(finding("missing_boundary", "缺少效力边界。"))
    else:
        for key in [
            "production_write",
            "current_file_overwrite",
            "formal_approval",
            "formal_release",
            "self_verification",
            "old_validation_inherited",
        ]:
            if boundary.get(key) is not False:
                findings.append(finding("unsafe_boundary", f"边界 {key} 必须为 false。"))
        if boundary.get("allowed_initial_file_states") != ["draft_candidate"]:
            findings.append(finding("unsafe_initial_state", "编制者初始文件状态只允许 draft_candidate。"))

    sources = ledger.get("sources")
    if not isinstance(sources, list) or not sources:
        findings.append(finding("missing_sources", "来源清单为空。"))
        sources = []
    source_by_id: dict[str, dict[str, Any]] = {}
    for index, source in enumerate(sources):
        location = f"sources[{index}]"
        require_nonempty(
            source,
            ["source_id", "title", "path_scope", "path", "sha256", "role"],
            "missing_source_field",
            location,
            findings,
        )
        source_id = source.get("source_id")
        if not isinstance(source_id, str):
            continue
        if source_id in source_by_id:
            findings.append(finding("duplicate_source_id", f"来源编号重复：{source_id}", location))
        source_by_id[source_id] = source
        expected_hash = source.get("sha256")
        if not isinstance(expected_hash, str) or not SHA256.match(expected_hash):
            findings.append(finding("invalid_source_hash", f"来源哈希格式错误：{source_id}", location))
            continue
        path = resolve_source_path(source, package_dir, project_root)
        if path is None:
            findings.append(finding("invalid_source_scope", f"无法解析来源路径：{source_id}", location))
        elif not path.is_file():
            findings.append(finding("missing_source_file", f"来源文件不存在：{path}", location))
        else:
            actual_hash = file_sha256(path)
            if actual_hash != expected_hash:
                findings.append(
                    finding(
                        "source_hash_mismatch",
                        f"来源哈希不一致：{source_id} expected={expected_hash} actual={actual_hash}",
                        location,
                    )
                )

    records = ledger.get("findings")
    if not isinstance(records, list) or not records:
        findings.append(finding("missing_findings", "治理发现为空。"))
        records = []
    finding_by_id: dict[str, dict[str, Any]] = {}
    for index, record in enumerate(records):
        location = f"findings[{index}]"
        require_nonempty(
            record,
            [
                "finding_id",
                "title",
                "classification",
                "severity",
                "issue_state",
                "objective_basis",
                "old_value",
                "proposed_new_value",
                "impact_objects",
                "candidate_file_ids",
                "verification",
                "replay",
            ],
            "missing_finding_field",
            location,
            findings,
        )
        finding_id = record.get("finding_id")
        if not isinstance(finding_id, str) or not FINDING_ID.match(finding_id):
            findings.append(finding("invalid_finding_id", f"发现编号格式错误：{finding_id}", location))
        elif finding_id in finding_by_id:
            findings.append(finding("duplicate_finding_id", f"发现编号重复：{finding_id}", location))
        else:
            finding_by_id[finding_id] = record
        if record.get("issue_state") not in ISSUE_STATES:
            findings.append(finding("invalid_issue_state", f"非法问题状态：{record.get('issue_state')}", location))
        basis = record.get("objective_basis")
        if not isinstance(basis, list) or not basis:
            findings.append(finding("missing_objective_basis", "发现必须具有客观依据回链。", location))
        else:
            for basis_index, item in enumerate(basis):
                basis_location = f"{location}.objective_basis[{basis_index}]"
                require_nonempty(
                    item,
                    ["source_id", "locator", "requirement"],
                    "missing_basis_field",
                    basis_location,
                    findings,
                )
                if item.get("source_id") not in source_by_id:
                    findings.append(
                        finding("unknown_basis_source", f"未知依据来源：{item.get('source_id')}", basis_location)
                    )
        verification = record.get("verification", {})
        replay = record.get("replay", {})
        if record.get("issue_state") == "closed":
            if verification.get("status") != "passed":
                findings.append(finding("closed_without_verification", "关闭项必须独立验证通过。", location))
            if replay.get("status") != "passed":
                findings.append(finding("closed_without_replay", "关闭项必须受影响场景复演通过。", location))
        for transition_index, transition in enumerate(record.get("transitions", [])):
            transition_location = f"{location}.transitions[{transition_index}]"
            if transition.get("from") not in ISSUE_STATES or transition.get("to") not in ISSUE_STATES:
                findings.append(finding("invalid_transition_state", "状态转换含非法状态。", transition_location))
            if not transition.get("reason"):
                findings.append(finding("missing_transition_reason", "状态转换缺少理由。", transition_location))

    file_candidates = ledger.get("file_candidates")
    if not isinstance(file_candidates, list) or not file_candidates:
        findings.append(finding("missing_file_candidates", "文件候选为空。"))
        file_candidates = []
    file_by_id: dict[str, dict[str, Any]] = {}
    for index, candidate in enumerate(file_candidates):
        location = f"file_candidates[{index}]"
        require_nonempty(
            candidate,
            [
                "candidate_file_id",
                "document_type",
                "target_document_number",
                "target_title",
                "file_state",
                "payload_path",
                "payload_sha256",
                "based_on_findings",
                "source_material_ids",
                "version_relation",
                "changes",
                "verification",
                "replay",
                "effect_boundary",
            ],
            "missing_file_candidate_field",
            location,
            findings,
        )
        file_id = candidate.get("candidate_file_id")
        if not isinstance(file_id, str) or not FILE_ID.match(file_id):
            findings.append(finding("invalid_file_id", f"文件候选编号格式错误：{file_id}", location))
        elif file_id in file_by_id:
            findings.append(finding("duplicate_file_id", f"文件候选编号重复：{file_id}", location))
        else:
            file_by_id[file_id] = candidate
        if candidate.get("file_state") not in FILE_STATES:
            findings.append(finding("invalid_file_state", f"非法文件状态：{candidate.get('file_state')}", location))
        elif candidate.get("file_state") != "draft_candidate":
            findings.append(
                finding("builder_advanced_file_state", "编制者子包中的候选必须保持 draft_candidate。", location)
            )
        version_relation = candidate.get("version_relation")
        if not isinstance(version_relation, dict):
            findings.append(finding("missing_version_relation", "缺少版本关系。", location))
        else:
            require_nonempty(
                version_relation,
                ["baseline", "material", "candidate"],
                "missing_version_relation",
                f"{location}.version_relation",
                findings,
            )
            if version_relation.get("replaces_current") is not False:
                findings.append(finding("current_replacement_forbidden", "候选不得替换现用文件。", location))
        for source_id in candidate.get("source_material_ids", []):
            if source_id not in source_by_id:
                findings.append(finding("unknown_candidate_source", f"未知候选来源：{source_id}", location))
        expected_hash = candidate.get("payload_sha256")
        payload_path = candidate.get("payload_path")
        if not isinstance(expected_hash, str) or not SHA256.match(expected_hash):
            findings.append(finding("invalid_payload_hash", f"payload哈希格式错误：{file_id}", location))
        if not isinstance(payload_path, str) or not payload_path:
            findings.append(finding("missing_payload", f"缺少payload：{file_id}", location))
        else:
            payload = package_dir / payload_path
            if not payload.is_file():
                findings.append(finding("missing_payload", f"payload不存在：{payload_path}", location))
            else:
                actual_hash = file_sha256(payload)
                if actual_hash != expected_hash:
                    findings.append(
                        finding(
                            "payload_hash_mismatch",
                            f"payload哈希不一致：{file_id} expected={expected_hash} actual={actual_hash}",
                            location,
                        )
                    )
                text = payload.read_text(encoding="utf-8")
                if "`draft_candidate`" not in text:
                    findings.append(finding("missing_candidate_state_marker", "payload缺少draft_candidate标记。", location))
                for pattern in FORBIDDEN_RELEASE_PATTERNS:
                    if pattern.search(text):
                        findings.append(
                            finding("forbidden_release_claim", f"payload含禁止的正式效力声明：{pattern.pattern}", location)
                        )
        for change_index, change in enumerate(candidate.get("changes", [])):
            change_location = f"{location}.changes[{change_index}]"
            require_nonempty(
                change,
                ["change_id", "source", "old_value", "new_value", "impact_objects", "field_sources"],
                "missing_change_field",
                change_location,
                findings,
            )
            change_id = change.get("change_id")
            if not isinstance(change_id, str) or not CHANGE_ID.match(change_id):
                findings.append(finding("invalid_change_id", f"变更编号格式错误：{change_id}", change_location))
            for source_id in change.get("field_sources", []):
                if source_id not in source_by_id:
                    findings.append(finding("unknown_field_source", f"未知字段来源：{source_id}", change_location))
        verification = candidate.get("verification", {})
        replay = candidate.get("replay", {})
        if verification.get("status") != "pending_independent_validation":
            findings.append(finding("self_verification_forbidden", "编制者候选必须等待独立验证。", location))
        if replay.get("status") != "not_run":
            findings.append(finding("replay_claim_forbidden", "编制者候选不得声称已经复演。", location))

    for record in records:
        for file_id in record.get("candidate_file_ids", []):
            if file_id not in file_by_id:
                findings.append(
                    finding("unknown_candidate_file", f"发现 {record.get('finding_id')} 引用了未知文件：{file_id}")
                )
    for candidate in file_candidates:
        for finding_id in candidate.get("based_on_findings", []):
            if finding_id not in finding_by_id:
                findings.append(
                    finding(
                        "unknown_source_finding",
                        f"文件 {candidate.get('candidate_file_id')} 引用了未知发现：{finding_id}",
                    )
                )

    independent = ledger.get("independent_validation")
    if not isinstance(independent, dict):
        findings.append(finding("missing_independent_validation_gate", "缺少独立验证闸门。"))
    else:
        if independent.get("required") is not True:
            findings.append(finding("independent_validation_not_required", "必须要求独立验证。"))
        if independent.get("builder_may_self_verify") is not False:
            findings.append(finding("builder_self_verification_allowed", "编制者不得自证。"))
        handoff = independent.get("handoff_path")
        if not isinstance(handoff, str) or not (package_dir / handoff).is_file():
            findings.append(finding("missing_validation_handoff", "独立验证交接文件不存在。"))

    return findings


def validate_package(package_dir: Path) -> list[dict[str, str]]:
    ledger_path = package_dir / "自治治理主台账-v0.1.json"
    if not ledger_path.is_file():
        return [finding("missing_ledger", f"台账不存在：{ledger_path}")]
    try:
        ledger = json.loads(ledger_path.read_text(encoding="utf-8"))
    except json.JSONDecodeError as exc:
        return [finding("invalid_json", f"台账JSON无效：{exc}", str(ledger_path))]
    if not isinstance(ledger, dict):
        return [finding("invalid_ledger_root", "台账JSON根节点必须为对象。", str(ledger_path))]
    return validate_ledger(ledger, package_dir)


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument(
        "package_dir",
        nargs="?",
        type=Path,
        default=Path(__file__).resolve().parent.parent,
        help="自治治理与SIM覆盖层目录",
    )
    args = parser.parse_args()
    package_dir = args.package_dir.resolve()
    results = validate_package(package_dir)
    output = {
        "valid": not results,
        "package_dir": str(package_dir),
        "finding_count": len(results),
        "findings": results,
    }
    print(json.dumps(output, ensure_ascii=False, indent=2))
    return 0 if not results else 1


if __name__ == "__main__":
    sys.exit(main())
