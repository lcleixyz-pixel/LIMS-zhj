#!/usr/bin/env python3
"""依据已冻结的独立验证结论生成第二轮自治治理台账 v0.2。"""

from __future__ import annotations

import copy
import hashlib
import json
from pathlib import Path
from typing import Any


HERE = Path(__file__).resolve().parent
PACKAGE = HERE.parent
ROUND_DIR = PACKAGE.parent
V01 = PACKAGE / "自治治理主台账-v0.1.json"
OUT_JSON = PACKAGE / "自治治理主台账-v0.2.json"
OUT_MD = PACKAGE / "自治治理主台账-v0.2.md"
INDEPENDENT_REPORT = ROUND_DIR / "独立验证" / "独立验证-半年运行与自治候选-v0.1.md"
CANDIDATE_JSON = PACKAGE / "候选覆盖层" / "SIM-记录模板语义覆盖候选-v0.2.json"
CANDIDATE_MD = PACKAGE / "候选覆盖层" / "SIM-记录模板语义覆盖候选-v0.2.md"
SNAPSHOT_JSON = PACKAGE / "候选覆盖层" / "8013记录模板新鲜复算-v0.2.json"


def sha256(path: Path) -> str:
    return hashlib.sha256(path.read_bytes()).hexdigest()


def project_relative(path: Path) -> str:
    for parent in (path.parent, *path.parents):
        if (parent / ".git").exists():
            return str(path.relative_to(parent))
    raise RuntimeError(f"无法定位仓库根目录：{path}")


def add_source(ledger: dict[str, Any], source: dict[str, Any]) -> None:
    ledger["sources"] = [
        item for item in ledger["sources"] if item["source_id"] != source["source_id"]
    ]
    ledger["sources"].append(source)


def transition(finding: dict[str, Any], to_state: str, basis: str) -> None:
    old_state = finding["issue_state"]
    finding["transitions"].append(
        {
            "from": old_state,
            "to": to_state,
            "reason": basis,
            "performed_by": "lab-qms-engineer-governance-router",
            "formal_effect": False,
        }
    )
    finding["issue_state"] = to_state


def render_markdown(ledger: dict[str, Any]) -> str:
    lines = [
        "# LIMS-zhj 第二轮自治治理主台账 v0.2",
        "",
        "> 状态：SIM-only 非受控治理记录。FILE-001/002 仅依据独立验证结论推进到 `verified_candidate`；FILE-003 仍为 `draft_candidate`。任何候选均未 `sim_applied`。",
        "",
        "## 1. 适用边界",
        "",
        "- 演练编号：`SIM-GOV-R2-20260719`",
        "- 不写 8013 数据库，不覆盖现用文件，不修改产品代码。",
        "- 不自证，不形成正式批准、发布或生效。",
        "- 旧轮记录候选只作素材，当前结论来自 161 个源文件、145 个现用模板的新鲜复算。",
        "",
        "## 2. 文件候选状态",
        "",
        "| 候选 | 类型 | 当前状态 | 独立验证 | 复演 | 说明 |",
        "|---|---|---|---|---|---|",
    ]
    for item in ledger["file_candidates"]:
        lines.append(
            "| {id} | {kind} | `{state}` | `{verification}` | `{replay}` | {title} |".format(
                id=item["candidate_file_id"],
                kind=item["document_type"],
                state=item["file_state"],
                verification=item["verification"]["status"],
                replay=item["replay"]["status"],
                title=item["target_title"],
            )
        )
    lines.extend(
        [
            "",
            "FILE-001/002 的 `verified_candidate` 只表示独立文件核查通过；尚未应用 SIM 覆盖层，也未完成受影响场景复演。FILE-003 的机器检查通过不等于独立验证通过。",
            "",
            "## 3. 问题状态",
            "",
            "| 问题 | 分类 | 风险 | 当前状态 | 验证状态 | 候选 |",
            "|---|---|---|---|---|---|",
        ]
    )
    for item in ledger["findings"]:
        lines.append(
            f"| {item['finding_id']} {item['title']} | {item['classification']} | "
            f"{item['severity']} | `{item['issue_state']}` | "
            f"`{item['verification']['status']}` | {', '.join(item['candidate_file_ids'])} |"
        )
    lines.extend(
        [
            "",
            "R2-FIND-007/008 已因新鲜复算和 v0.2 逐字段候选进入 `remediating`，但仍不得关闭；须等待新的独立验证。",
            "",
            "## 4. FILE-003 新增证据",
            "",
            "- 12 张重点表逐字段候选：209 个字段，逐字段记录来源、定位、条款或冻结事实、业务含义、岗位、触发、必填、更正、关联、保存依据状态、LIMS 映射、来源等级。",
            "- BG-04-03：19 个变体分别保留 `doc_number + source_file_sha1 + object_identity` 身份、SHA256、当前 schema 键、可证字段和证据不足字段。",
            "- 技术参数、单位、计算和限值：未建立到方法或作业指导书的字段保持 `evidence_insufficient`，候选值为空。",
            "- 孤儿键：8013 现有重点基线实例为 0，因此只形成迁移预览规则；不伪造迁移结果、不写数据库。",
            "",
            "## 5. 独立验证和后续闸门",
            "",
            "1. FILE-003 交给新的独立记录模板验证角色，只读检查冻结候选。",
            "2. 通过后才可由治理路由推进 `verified_candidate`。",
            "3. 之后才可应用为 SIM 覆盖层并复演受影响场景。",
            "4. 复演通过前，R2-FIND-007/008 不得转为 `closed`。",
            "",
            "机器可验完整台账见 `自治治理主台账-v0.2.json`。",
            "",
        ]
    )
    return "\n".join(lines)


def main() -> None:
    ledger = copy.deepcopy(json.loads(V01.read_text(encoding="utf-8")))
    ledger["schema_version"] = "1.1"
    ledger["ledger_id"] = "SIM-GOV-R2-LEDGER-002"
    ledger["generated_at"] = "2026-07-19"
    ledger["boundary"]["allowed_initial_file_states"] = [
        "draft_candidate",
        "verified_candidate",
    ]
    ledger["boundary"]["verified_candidate_requires_independent_pass"] = True
    ledger["boundary"]["sim_applied_requires_replay_gate"] = True
    for source in ledger["sources"]:
        if source["source_id"] == "SRC-TEMPLATE-SERVICE":
            source["path_scope"] = "git_commit"
            source["git_commit"] = "2503f15a34d3a0d079429353616d66c3a370eddc"
            source["role"] = "frozen_code_baseline_at_commit"

    independent_hash = sha256(INDEPENDENT_REPORT)
    candidate_json_hash = sha256(CANDIDATE_JSON)
    candidate_md_hash = sha256(CANDIDATE_MD)
    snapshot_hash = sha256(SNAPSHOT_JSON)
    add_source(
        ledger,
        {
            "source_id": "SRC-INDEPENDENT-VALIDATION-R1",
            "title": "独立验证-半年运行与自治候选 v0.1",
            "path_scope": "project",
            "path": project_relative(INDEPENDENT_REPORT),
            "sha256": independent_hash,
            "role": "independent_validation_result",
        },
    )
    add_source(
        ledger,
        {
            "source_id": "SRC-R2-TEMPLATE-FRESH-SNAPSHOT",
            "title": "8013记录模板新鲜复算 v0.2",
            "path_scope": "package",
            "path": str(SNAPSHOT_JSON.relative_to(PACKAGE)),
            "sha256": snapshot_hash,
            "role": "read_only_fresh_evidence",
        },
    )
    add_source(
        ledger,
        {
            "source_id": "SRC-R2-TEMPLATE-CANDIDATE-V02",
            "title": "SIM记录模板语义覆盖候选 v0.2",
            "path_scope": "package",
            "path": str(CANDIDATE_MD.relative_to(PACKAGE)),
            "sha256": candidate_md_hash,
            "machine_json_path": str(CANDIDATE_JSON.relative_to(PACKAGE)),
            "machine_json_sha256": candidate_json_hash,
            "role": "draft_candidate_pending_new_independent_validation",
        },
    )

    finding_map = {item["finding_id"]: item for item in ledger["findings"]}
    for finding_id in ("R2-FIND-007", "R2-FIND-008"):
        finding = finding_map[finding_id]
        transition(
            finding,
            "accepted",
            "8013只读新鲜复算已固定161源文件、145现用模板、94种schema、13重复组、64重复成员",
        )
        transition(
            finding,
            "remediating",
            "已形成12表209字段、BG-04-03 19变体和孤儿键零实例预览候选v0.2",
        )
        finding["verification"]["status"] = "pending_independent_validation"
        finding["verification"]["evidence_links"] = [
            {
                "source_id": "SRC-R2-TEMPLATE-FRESH-SNAPSHOT",
                "sha256": snapshot_hash,
            },
            {
                "source_id": "SRC-R2-TEMPLATE-CANDIDATE-V02",
                "sha256": candidate_md_hash,
            },
        ]

    file_map = {item["candidate_file_id"]: item for item in ledger["file_candidates"]}
    for candidate_id in ("R2-FILE-001", "R2-FILE-002"):
        item = file_map[candidate_id]
        item["file_state"] = "verified_candidate"
        item["verification"] = {
            "status": "passed",
            "validator_role": "independent_document_validator",
            "result_links": [
                {
                    "source_id": "SRC-INDEPENDENT-VALIDATION-R1",
                    "path": project_relative(INDEPENDENT_REPORT),
                    "sha256": independent_hash,
                }
            ],
            "scope_limit": "文件候选独立核查通过；不代表已sim_applied或复演通过",
        }
        item["replay"]["status"] = "not_run"

    file003 = file_map["R2-FILE-003"]
    file003["file_state"] = "draft_candidate"
    file003["payload_path"] = str(CANDIDATE_MD.relative_to(PACKAGE))
    file003["payload_sha256"] = candidate_md_hash
    file003["machine_payload_path"] = str(CANDIDATE_JSON.relative_to(PACKAGE))
    file003["machine_payload_sha256"] = candidate_json_hash
    file003["fresh_snapshot_path"] = str(SNAPSHOT_JSON.relative_to(PACKAGE))
    file003["fresh_snapshot_sha256"] = snapshot_hash
    file003["source_material_ids"] = list(
        dict.fromkeys(
            file003["source_material_ids"]
            + ["SRC-R2-TEMPLATE-FRESH-SNAPSHOT", "SRC-R2-TEMPLATE-CANDIDATE-V02"]
        )
    )
    file003["version_relation"] = {
        "baseline": "8013现用145模板与161个记录源文件（只读新鲜复算）",
        "material": "旧轮v0.2仅作素材，不继承结论",
        "candidate": "第二轮SIM记录模板语义覆盖v0.2",
        "replaces_current": False,
    }
    file003["changes"] = [
        {
            "change_id": "R2-CHG-T-001",
            "source": "161源文件、145现用模板新鲜复算及12份源表",
            "old_value": "v0.1仅列12张表治理方向",
            "new_value": "12张重点表共209字段的逐字段机器可验候选",
            "impact_objects": ["12张重点表", "模板版本", "SIM实例"],
            "field_sources": [
                "SRC-R2-TEMPLATE-FRESH-SNAPSHOT",
                "SRC-R2-TEMPLATE-CANDIDATE-V02",
            ],
        },
        {
            "change_id": "R2-CHG-T-002",
            "source": "19份BG-04-03源表及8013当前schema",
            "old_value": "同号变体身份和字段差异未逐一固定",
            "new_value": "19变体分别记录三段式身份、SHA256、可证字段和证据不足字段",
            "impact_objects": ["BG-04-03", "变体身份", "技术参数门禁"],
            "field_sources": [
                "SRC-R2-TEMPLATE-FRESH-SNAPSHOT",
                "SRC-R2-TEMPLATE-CANDIDATE-V02",
            ],
        },
        {
            "change_id": "R2-CHG-T-003",
            "source": "8013当前实例只读统计",
            "old_value": "孤儿键迁移证据缺失",
            "new_value": "确认重点基线实例为0并形成不写库的孤儿键迁移预览规则",
            "impact_objects": ["旧实例", "孤儿键", "模板升级"],
            "field_sources": ["SRC-R2-TEMPLATE-FRESH-SNAPSHOT"],
        },
    ]
    file003["verification"] = {
        "status": "pending_independent_validation",
        "validator_role": "independent_record_template_validator",
        "result_links": [],
        "machine_precheck": {
            "status": "passed",
            "validator": "机器检查/validate_record_template_candidate_v02.py",
            "unit_tests": "6/6 passed",
            "not_an_independent_pass": True,
        },
    }
    file003["replay"]["status"] = "not_run"

    ledger["independent_validation"] = {
        "required": True,
        "handoff_path": "独立验证交接说明-v0.2.md",
        "builder_may_self_verify": False,
        "candidate_files_may_advance_before_validation": False,
        "already_passed_candidates": ["R2-FILE-001", "R2-FILE-002"],
        "pending_candidate": "R2-FILE-003",
        "no_candidate_sim_applied": True,
    }

    OUT_JSON.write_text(
        json.dumps(ledger, ensure_ascii=False, indent=2) + "\n",
        encoding="utf-8",
    )
    OUT_MD.write_text(render_markdown(ledger), encoding="utf-8")
    print(
        json.dumps(
            {
                "ledger_json": str(OUT_JSON),
                "ledger_markdown": str(OUT_MD),
                "ledger_json_sha256": sha256(OUT_JSON),
                "ledger_markdown_sha256": sha256(OUT_MD),
                "file_states": {
                    item["candidate_file_id"]: item["file_state"]
                    for item in ledger["file_candidates"]
                },
                "finding_states": {
                    key: finding_map[key]["issue_state"]
                    for key in ("R2-FIND-007", "R2-FIND-008")
                },
            },
            ensure_ascii=False,
            indent=2,
        )
    )


if __name__ == "__main__":
    main()
