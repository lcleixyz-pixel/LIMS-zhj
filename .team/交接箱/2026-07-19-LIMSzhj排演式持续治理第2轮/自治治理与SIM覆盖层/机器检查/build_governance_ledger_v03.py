#!/usr/bin/env python3
"""根据冻结的 FILE-003 独立 PASS 报告生成自治治理台账 v0.3。"""

from __future__ import annotations

import copy
import hashlib
import json
from pathlib import Path
from typing import Any


HERE = Path(__file__).resolve().parent
PACKAGE = HERE.parent
ROUND_DIR = PACKAGE.parent
SOURCE_LEDGER = PACKAGE / "自治治理主台账-v0.2.json"
OUT_JSON = PACKAGE / "自治治理主台账-v0.3.json"
OUT_MD = PACKAGE / "自治治理主台账-v0.3.md"
REPORT = ROUND_DIR / "独立验证" / "独立验证-记录模板语义覆盖候选-v0.2.md"
EXPECTED_REPORT_SHA256 = "ee8d59fc29eba6daaf14ed09afcdd98b22e051ad42af2e3f2add4dfb58c8e421"


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


def render_markdown(ledger: dict[str, Any]) -> str:
    file_map = {item["candidate_file_id"]: item for item in ledger["file_candidates"]}
    finding_map = {item["finding_id"]: item for item in ledger["findings"]}
    lines = [
        "# LIMS-zhj 第二轮自治治理主台账 v0.3",
        "",
        "> 治理路由记录：依据新的独立验证 `PASS`，仅将 R2-FILE-003 从 `draft_candidate` 推进到 `verified_candidate`。本台账不执行候选应用、不写 8013/8014、不修改候选或现用文件。",
        "",
        "## 1. 状态推进依据",
        "",
        "- 独立报告：`../独立验证/独立验证-记录模板语义覆盖候选-v0.2.md`",
        f"- SHA256：`{EXPECTED_REPORT_SHA256}`",
        "- 独立授权：`draft_candidate -> verified_candidate`。",
        "- 明确禁止：不得直接推进 `sim_applied`。",
        "",
        "## 2. 文件候选状态",
        "",
        "| 候选 | 状态 | 独立验证 | SIM 应用 | 复演 |",
        "|---|---|---|---|---|",
    ]
    for file_id in ("R2-FILE-001", "R2-FILE-002", "R2-FILE-003"):
        item = file_map[file_id]
        lines.append(
            f"| {file_id} | `{item['file_state']}` | "
            f"`{item['verification']['status']}` | 未应用 | `{item['replay']['status']}` |"
        )
    lines.extend(
        [
            "",
            "三份候选均只是 `verified_candidate`；没有候选进入 `sim_applied`，也没有正式批准、发布、生效或替换现用文件。",
            "",
            "## 3. R2-FIND-007/008 状态",
            "",
            "| 问题 | 当前状态 | 候选验证 | 待完成闸门 |",
            "|---|---|---|---|",
        ]
    )
    for finding_id in ("R2-FIND-007", "R2-FIND-008"):
        item = finding_map[finding_id]
        lines.append(
            f"| {finding_id} {item['title']} | `{item['issue_state']}` | "
            f"`{item['verification']['status']}` | 8013 SIM覆盖层应用、受影响场景复演 |"
        )
    lines.extend(
        [
            "",
            "两项问题只推进到 `verifying`，不得关闭。若应用或复演发现字段丢失、变体错配、孤儿键覆盖、技术值自动填充或状态越权，应重开候选验证。",
            "",
            "## 4. 下一闸门",
            "",
            "1. 在新冻结的 8013 基线上应用仅供 SIM 的覆盖层。",
            "2. 验证应用过程不触碰现用文件、正式发布状态或 8014。",
            "3. 复演 `EX-7.1`、`EX-7.4`、`EX-7.5`、`EX-7.8`、`EX-8.4` 及相关反向场景。",
            "4. 复演通过后，才可讨论 `sim_applied` 和 R2-FIND-007/008 的关闭。",
            "",
            "机器可验完整状态、来源和哈希见 `自治治理主台账-v0.3.json`。",
            "",
        ]
    )
    return "\n".join(lines)


def main() -> None:
    actual_report_hash = sha256(REPORT)
    if actual_report_hash != EXPECTED_REPORT_SHA256:
        raise RuntimeError(
            f"独立报告哈希不匹配：expected={EXPECTED_REPORT_SHA256} actual={actual_report_hash}"
        )

    ledger = copy.deepcopy(json.loads(SOURCE_LEDGER.read_text(encoding="utf-8")))
    ledger["schema_version"] = "1.2"
    ledger["ledger_id"] = "SIM-GOV-R2-LEDGER-003"
    ledger["generated_at"] = "2026-07-19"
    ledger["previous_ledger"] = {
        "path": "自治治理主台账-v0.2.json",
        "sha256": sha256(SOURCE_LEDGER),
    }

    add_source(
        ledger,
        {
            "source_id": "SRC-INDEPENDENT-TEMPLATE-V02-PASS",
            "title": "独立验证：记录模板语义覆盖候选 v0.2",
            "path_scope": "project",
            "path": project_relative(REPORT),
            "sha256": actual_report_hash,
            "role": "independent_validation_pass_authorizing_verified_candidate_only",
        },
    )

    file_map = {item["candidate_file_id"]: item for item in ledger["file_candidates"]}
    file003 = file_map["R2-FILE-003"]
    if file003["file_state"] != "draft_candidate":
        raise RuntimeError("v0.2中FILE-003不是预期的draft_candidate")
    file003["file_state"] = "verified_candidate"
    file003["verification"] = {
        "status": "passed",
        "validator_role": "independent_record_template_validator",
        "result_links": [
            {
                "source_id": "SRC-INDEPENDENT-TEMPLATE-V02-PASS",
                "path": project_relative(REPORT),
                "sha256": actual_report_hash,
            }
        ],
        "scope_limit": "仅授权verified_candidate；不授权sim_applied、正式发布或问题关闭",
        "machine_precheck": file003["verification"]["machine_precheck"],
    }
    file003["replay"]["status"] = "not_run"

    for file_id in ("R2-FILE-001", "R2-FILE-002"):
        if file_map[file_id]["file_state"] != "verified_candidate":
            raise RuntimeError(f"{file_id}状态异常，不允许路由脚本修饰")
        file_map[file_id]["replay"]["status"] = "not_run"

    finding_map = {item["finding_id"]: item for item in ledger["findings"]}
    for finding_id in ("R2-FIND-007", "R2-FIND-008"):
        item = finding_map[finding_id]
        if item["issue_state"] != "remediating":
            raise RuntimeError(f"{finding_id}不是预期的remediating")
        item["transitions"].append(
            {
                "from": "remediating",
                "to": "verifying",
                "reason": "R2-FILE-003已获新的独立验证PASS；进入SIM应用与受影响场景复演闸门",
                "performed_by": "lab-qms-engineer-governance-router",
                "formal_effect": False,
            }
        )
        item["issue_state"] = "verifying"
        item["verification"] = {
            "status": "candidate_validation_passed_pending_sim_application_and_replay",
            "required_checks": item["verification"]["required_checks"],
            "evidence_links": item["verification"]["evidence_links"]
            + [
                {
                    "source_id": "SRC-INDEPENDENT-TEMPLATE-V02-PASS",
                    "sha256": actual_report_hash,
                }
            ],
        }
        item["replay"]["status"] = "not_run"

    ledger["independent_validation"] = {
        "required": True,
        "handoff_path": "独立验证交接说明-v0.2.md",
        "builder_may_self_verify": False,
        "candidate_files_may_advance_before_validation": False,
        "passed_candidates": ["R2-FILE-001", "R2-FILE-002", "R2-FILE-003"],
        "pending_candidates": [],
        "no_candidate_sim_applied": True,
        "next_gate": "8013_sim_overlay_application_and_affected_scenario_replay",
    }

    if any(item["file_state"] == "sim_applied" for item in ledger["file_candidates"]):
        raise RuntimeError("治理路由不得在本步骤写入sim_applied")
    if any(
        finding_map[item]["issue_state"] == "closed"
        for item in ("R2-FIND-007", "R2-FIND-008")
    ):
        raise RuntimeError("FIND-007/008不得关闭")

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
                "report_sha256": actual_report_hash,
                "file_states": {
                    item["candidate_file_id"]: item["file_state"]
                    for item in ledger["file_candidates"]
                },
                "finding_states": {
                    key: finding_map[key]["issue_state"]
                    for key in ("R2-FIND-007", "R2-FIND-008")
                },
                "any_sim_applied": any(
                    item["file_state"] == "sim_applied"
                    for item in ledger["file_candidates"]
                ),
            },
            ensure_ascii=False,
            indent=2,
        )
    )


if __name__ == "__main__":
    main()
