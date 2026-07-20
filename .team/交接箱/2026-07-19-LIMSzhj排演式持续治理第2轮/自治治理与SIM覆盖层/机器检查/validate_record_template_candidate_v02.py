#!/usr/bin/env python3
"""校验第二轮记录模板逐字段治理候选 v0.2。

检查器只读候选、来源文件和冻结快照，不连接或写入数据库。
"""

from __future__ import annotations

import hashlib
import json
import sys
from pathlib import Path
from typing import Any


EXPECTED_DOC_NUMBERS = {
    "XZTC/BG-02-01",
    "XZTC/BG-03-01",
    "XZTC/BG-04-03",
    "XZTC/BG-09-01",
    "XZTC/BG-11-01",
    "XZTC/BG-19-01",
    "XZTC/BG-22-02",
    "XZTC/BG-26-01",
    "XZTC/BG-28-01",
    "XZTC/BG-29-02",
    "XZTC/BG-30-01",
    "XZTC/BG-30-03",
}
REQUIRED_FIELD_ATTRIBUTES = {
    "field_key",
    "field_name",
    "source_file",
    "source_file_sha256",
    "source_locator",
    "procedure_clause_or_frozen_fact",
    "business_meaning",
    "responsible_role",
    "trigger",
    "required_rule",
    "correction_rule",
    "associations",
    "retention_basis_status",
    "lims_mapping",
    "source_level",
    "candidate_action",
    "proposed_value",
}
NONEMPTY_FIELD_ATTRIBUTES = REQUIRED_FIELD_ATTRIBUTES - {
    "lims_mapping",
    "proposed_value",
}
ALLOWED_SOURCE_LEVELS = {
    "direct_source",
    "cross_source",
    "sim_extension",
    "evidence_insufficient",
}
BG04_VERIFIABLE_FIELDS = {
    "equipment_name",
    "model_spec",
    "equipment_code",
    "check_basis",
    "check_resources",
    "check_personnel",
    "process_record",
    "recorder",
    "record_date",
    "result_judgement",
    "checkers",
    "check_date",
    "reviewer_opinion",
    "reviewer",
    "review_date",
}
BG04_INSUFFICIENT_FIELDS = {
    "check_method",
    "acceptance_criteria",
    "measurement_data",
}


def sha256_file(path: Path) -> str:
    digest = hashlib.sha256()
    with path.open("rb") as handle:
        for chunk in iter(lambda: handle.read(1024 * 1024), b""):
            digest.update(chunk)
    return digest.hexdigest()


def repo_root(package_dir: Path) -> Path:
    for candidate in (package_dir, *package_dir.parents):
        if (candidate / ".git").exists():
            return candidate
    raise RuntimeError(f"无法从 {package_dir} 定位仓库根目录")


def finding(code: str, message: str, location: str = "") -> dict[str, str]:
    return {"code": code, "message": message, "location": location}


def resolve_repo_path(root: Path, value: str) -> Path:
    path = Path(value)
    return path if path.is_absolute() else root / path


def validate_candidate(candidate: dict[str, Any], package_dir: Path) -> list[dict[str, str]]:
    findings: list[dict[str, str]] = []
    root = repo_root(package_dir)

    if candidate.get("candidate_file_id") != "R2-FILE-003":
        findings.append(finding("wrong_file_id", "候选必须绑定 R2-FILE-003"))
    if candidate.get("file_state") != "draft_candidate":
        findings.append(finding("invalid_file_state", "独立验证前必须保持 draft_candidate"))
    if candidate.get("verification", {}).get("status") != "pending_independent_validation":
        findings.append(finding("invalid_verification_state", "必须等待独立验证"))
    if candidate.get("verification", {}).get("self_verified") is not False:
        findings.append(finding("self_verification_forbidden", "编制者不得自证"))
    if any(candidate.get("write_boundary", {}).values()):
        findings.append(finding("write_boundary_violation", "不得写8013、现用文件或产品代码"))

    snapshot_meta = candidate.get("fresh_snapshot", {})
    snapshot_path = package_dir / snapshot_meta.get("path", "")
    if not snapshot_path.is_file():
        findings.append(finding("snapshot_missing", "新鲜复算快照不存在", str(snapshot_path)))
        snapshot: dict[str, Any] = {}
    else:
        actual = sha256_file(snapshot_path)
        if actual != snapshot_meta.get("sha256"):
            findings.append(finding("snapshot_hash_mismatch", "新鲜复算快照哈希不匹配"))
        snapshot = json.loads(snapshot_path.read_text(encoding="utf-8"))
        if snapshot != candidate.get("fresh_recalculation"):
            findings.append(finding("snapshot_content_mismatch", "候选内嵌复算与冻结快照不一致"))

    recalculation = candidate.get("fresh_recalculation", {})
    expected_counts = {
        ("record_source_inventory", "file_count"): 161,
        ("template_population", "all_non_deleted"): 152,
        ("template_population", "current_baseline_trial_batch_null"): 145,
        ("template_population", "second_round_sim_fixtures"): 7,
        ("template_population", "baseline_distinct_exact_schema"): 94,
        ("template_population", "baseline_repeated_schema_groups"): 13,
        ("template_population", "baseline_repeated_schema_members"): 64,
        ("instance_population", "all_instances"): 103,
        ("instance_population", "instances_linked_to_current_baseline_templates"): 0,
        ("instance_population", "instances_linked_to_twelve_focus_current_templates"): 0,
    }
    for (section, key), expected in expected_counts.items():
        actual = recalculation.get(section, {}).get(key)
        if actual != expected:
            findings.append(
                finding(
                    "fresh_recalculation_count_mismatch",
                    f"{section}.{key} 应为 {expected}，实际 {actual}",
                )
            )
    if recalculation.get("write_performed") is not False:
        findings.append(finding("database_write_detected", "新鲜复算必须为只读取证"))

    records = candidate.get("record_candidates", [])
    doc_numbers = [record.get("doc_number") for record in records]
    if len(records) != 12 or set(doc_numbers) != EXPECTED_DOC_NUMBERS:
        findings.append(finding("record_set_incomplete", "必须完整覆盖指定12张重点表"))
    identities = [record.get("identity_key") for record in records]
    if len(identities) != len(set(identities)) or any(not value for value in identities):
        findings.append(finding("duplicate_record_identity", "12张表的候选身份必须唯一且非空"))

    total_fields = 0
    for record in records:
        record_location = record.get("doc_number", "?")
        source_path = resolve_repo_path(root, record.get("source_file", ""))
        if not source_path.is_file():
            findings.append(finding("source_file_missing", "源记录表不存在", str(source_path)))
        else:
            actual_hash = sha256_file(source_path)
            if actual_hash != record.get("source_file_sha256"):
                findings.append(finding("source_hash_mismatch", "源记录表 SHA256 不匹配", record_location))
        expected_identity = (
            f"{record.get('doc_number')}+{record.get('source_file_sha1')}+"
            f"{record.get('object_identity')}"
        )
        if record.get("identity_key") != expected_identity:
            findings.append(finding("record_identity_mismatch", "候选身份不符合三段式规则", record_location))

        fields = record.get("fields", [])
        total_fields += len(fields)
        field_keys = [field.get("field_key") for field in fields]
        if len(field_keys) != len(set(field_keys)):
            findings.append(finding("duplicate_field_key", "单表字段键不得重复", record_location))
        for field in fields:
            field_location = f"{record_location}.{field.get('field_key', '?')}"
            missing = [
                key
                for key in REQUIRED_FIELD_ATTRIBUTES
                if key not in field or (key in NONEMPTY_FIELD_ATTRIBUTES and not field.get(key))
            ]
            if missing:
                findings.append(
                    finding(
                        "missing_field_attribute",
                        f"逐字段属性缺失或为空：{','.join(sorted(missing))}",
                        field_location,
                    )
                )
            if field.get("source_level") not in ALLOWED_SOURCE_LEVELS:
                findings.append(finding("invalid_source_level", "来源等级不受控", field_location))
            if field.get("source_file") != record.get("source_file"):
                findings.append(finding("field_source_mismatch", "字段源文件与记录候选不一致", field_location))
            if field.get("source_file_sha256") != record.get("source_file_sha256"):
                findings.append(finding("field_source_hash_mismatch", "字段源哈希与记录候选不一致", field_location))
            if field.get("source_level") == "evidence_insufficient":
                if field.get("proposed_value"):
                    findings.append(
                        finding("unsupported_technical_value", "证据不足字段不得预填技术值", field_location)
                    )
                if field.get("lims_mapping"):
                    findings.append(
                        finding("unsupported_lims_mapping", "证据不足字段不得先行映射LIMS", field_location)
                    )
                if field.get("candidate_action") not in {
                    "block_until_basis",
                    "exclude_pending_carrier",
                }:
                    findings.append(
                        finding("unsupported_candidate_action", "证据不足字段必须阻断或暂不纳入", field_location)
                    )
    if total_fields < 160:
        findings.append(finding("field_coverage_too_small", f"逐字段总数不足：{total_fields}"))

    variants = candidate.get("bg_04_03_variants", [])
    if len(variants) != 19:
        findings.append(finding("variant_count_mismatch", "BG-04-03 必须逐一列出19个变体"))
    variant_identities = [variant.get("identity_key") for variant in variants]
    if len(variant_identities) != len(set(variant_identities)) or any(not value for value in variant_identities):
        findings.append(finding("duplicate_variant_identity", "BG-04-03变体身份必须唯一且非空"))
    for variant in variants:
        location = variant.get("object_identity", "?")
        source_path = resolve_repo_path(root, variant.get("source_file", ""))
        if not source_path.is_file():
            findings.append(finding("variant_source_missing", "BG-04-03变体源文件不存在", location))
        else:
            if sha256_file(source_path) != variant.get("source_file_sha256"):
                findings.append(finding("variant_source_hash_mismatch", "BG-04-03变体源哈希不匹配", location))
        expected_identity = (
            f"{variant.get('doc_number')}+{variant.get('source_file_sha1')}+"
            f"{variant.get('object_identity')}"
        )
        if variant.get("identity_key") != expected_identity:
            findings.append(finding("variant_identity_mismatch", "变体身份不符合三段式规则", location))
        if set(variant.get("verifiable_fields", [])) != BG04_VERIFIABLE_FIELDS:
            findings.append(finding("variant_verifiable_fields_mismatch", "变体可证字段集合不完整", location))
        actual_insufficient = {
            item.get("field_key") for item in variant.get("evidence_insufficient_fields", [])
        }
        if actual_insufficient != BG04_INSUFFICIENT_FIELDS:
            findings.append(finding("variant_insufficient_fields_mismatch", "变体证据不足字段集合不完整", location))
        if variant.get("technical_values_authorized_for_candidate") is not False:
            findings.append(finding("unsupported_technical_value", "变体不得授权预填技术参数", location))
        if not variant.get("current_schema_keys"):
            findings.append(finding("variant_schema_missing", "变体须记录现有schema键", location))

    migration = candidate.get("orphan_key_migration_preview", {})
    if migration.get("fresh_8013_baseline_instance_count") != 0:
        findings.append(
            finding("unexpected_baseline_instance_count", "现时重点基线实例数不是冻结的0")
        )
    if migration.get("identity_rule") != "doc_number+source_file_sha1+object_identity":
        findings.append(finding("migration_identity_rule_mismatch", "孤儿键预览未采用三段式身份"))
    if migration.get("preview_only") is not True or migration.get("database_write_performed") is not False:
        findings.append(finding("migration_write_forbidden", "孤儿键迁移只允许预览"))
    preview_docs = {
        item.get("doc_number") for item in migration.get("record_previews", [])
    }
    if preview_docs != EXPECTED_DOC_NUMBERS:
        findings.append(finding("migration_preview_incomplete", "孤儿键预览必须覆盖12张重点表"))
    for preview in migration.get("record_previews", []):
        for rule in preview.get("rules", []):
            if rule.get("write_allowed") is not False or not rule.get("legacy_key"):
                findings.append(finding("migration_rule_unsafe", "孤儿键规则不可写且须保留旧键"))

    return findings


def main() -> int:
    here = Path(__file__).resolve().parent
    package_dir = here.parent
    candidate_path = package_dir / "候选覆盖层" / "SIM-记录模板语义覆盖候选-v0.2.json"
    candidate = json.loads(candidate_path.read_text(encoding="utf-8"))
    findings = validate_candidate(candidate, package_dir)
    print(
        json.dumps(
            {
                "validator": Path(__file__).name,
                "candidate": str(candidate_path),
                "valid": not findings,
                "finding_count": len(findings),
                "findings": findings,
            },
            ensure_ascii=False,
            indent=2,
        )
    )
    return 1 if findings else 0


if __name__ == "__main__":
    sys.exit(main())
