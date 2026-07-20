#!/usr/bin/env python3
"""Build a no-write controlled-release rehearsal pack for QMS candidate files."""

from __future__ import annotations

import argparse
import csv
import datetime as dt
import json
import re
from pathlib import Path
from typing import Any


DEFAULT_STAGE_DIR = Path(
    "/Users/lc.leixyz/Documents/AI工作台/01-项目代码/LIMS-zhj/.team/交接箱/2026-07-07-第五版候选修订准备"
)

GUARDRAILS = [
    "本演练包只用于受控发布、培训、旧版处置和实施有效性检查的准备，不写数据库。",
    "本演练包不代表第五版候选稿、记录模板或 jewelry-qms 已经审核批准或受控发布。",
    "资质状态按已取得 CMA、CNAS 申请中处理；不得写成已取得 CNAS。",
    "现行程序目录以 LIMS 当前导出的 2022 程序清单为准。",
    "jewelry-qms 目前只作为建设中系统，不写入手册正文，仅写入实施计划。",
]

RELEASE_FIELDS = [
    "release_item_id",
    "source_action",
    "object_type",
    "doc_number",
    "title",
    "source_stage_file",
    "current_status",
    "current_publish",
    "proposed_release_state",
    "release_allowed_now",
    "release_block_reason",
    "required_review_source",
    "training_required",
    "old_version_action",
    "lims_governance_action",
    "qualification_scope_note",
]

APPROVAL_FIELDS = [
    "approval_item_id",
    "release_item_id",
    "object_type",
    "doc_number",
    "title",
    "required_steps",
    "approver_roles",
    "human_decision",
    "blocking_if_unresolved",
    "required_evidence",
]

TRAINING_FIELDS = [
    "training_item_id",
    "topic",
    "source_object",
    "audience",
    "trigger",
    "required_before_effective",
    "training_evidence",
    "status",
    "not_real_record",
]

OBSOLETE_FIELDS = [
    "disposition_item_id",
    "object",
    "current_or_old_version",
    "proposed_action",
    "required_evidence",
    "status",
    "blocking_if_unresolved",
]

GATE_FIELDS = [
    "gate_id",
    "topic",
    "expected_position",
    "evidence_file",
    "check_method",
    "status",
    "blocking_if_failed",
]

EFFECTIVENESS_FIELDS = [
    "check_item_id",
    "process",
    "check_method",
    "sample_or_scope",
    "acceptance_criteria",
    "evidence",
    "status",
]


def read_csv(path: Path) -> list[dict[str, str]]:
    with path.open("r", encoding="utf-8", newline="") as handle:
        return list(csv.DictReader(handle))


def write_csv(path: Path, rows: list[dict[str, Any]], fieldnames: list[str]) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    with path.open("w", encoding="utf-8", newline="") as handle:
        writer = csv.DictWriter(handle, fieldnames=fieldnames, extrasaction="ignore")
        writer.writeheader()
        for row in rows:
            writer.writerow({field: row.get(field, "") for field in fieldnames})


def md_cell(value: Any) -> str:
    return str(value).replace("\n", " ").replace("|", "／")


def render_table(rows: list[dict[str, Any]], columns: list[str]) -> list[str]:
    lines = ["| " + " | ".join(columns) + " |", "|" + "|".join(["---"] * len(columns)) + "|"]
    for row in rows:
        lines.append("| " + " | ".join(md_cell(row.get(column, "")) for column in columns) + " |")
    return lines


def object_type_for_action(action: str) -> str:
    if action == "revision_candidate":
        return "candidate_manual"
    if action == "reference_existing_current":
        return "current_procedure_reference"
    if action == "reference_existing_attachment_form":
        return "numbered_attachment_form_pending"
    if action == "candidate_record_template_document":
        return "candidate_record_template_document"
    return "candidate_document"


def release_state_for_action(action: str) -> str:
    return {
        "revision_candidate": "draft_to_controlled_after_human_approval",
        "reference_existing_current": "current_reference_no_republish",
        "reference_existing_attachment_form": "disposition_pending_no_release",
        "candidate_record_template_document": "draft_template_to_controlled_after_human_approval",
    }.get(action, "draft_pending_review")


def training_required_for_type(object_type: str) -> str:
    if object_type in {"candidate_manual", "candidate_record_template_document"}:
        return "yes"
    if object_type == "current_procedure_reference":
        return "catalog_awareness_only_if_changed"
    return "pending_disposition"


def old_version_action_for_type(object_type: str) -> str:
    if object_type == "candidate_manual":
        return "第四版手册经第五版批准生效后作废回收；保留作废版本和修订记录"
    if object_type == "candidate_record_template_document":
        return "先与现用表单匹配；经批准后再决定替换、并行或不启用"
    if object_type == "numbered_attachment_form_pending":
        return "先确认 05-02 归属；未确认前不得发布或作废"
    return "现行 2022 程序作为目录基线；本演练不重新发布"


def lims_action_for_type(object_type: str) -> str:
    if object_type == "current_procedure_reference":
        return "match_existing_current_document_only"
    if object_type == "numbered_attachment_form_pending":
        return "hold_for_attachment_or_record_template_disposition"
    return "draft_metadata_only_until_human_approval"


def build_release_rows(document_rows: list[dict[str, str]]) -> list[dict[str, Any]]:
    rows: list[dict[str, Any]] = []
    for index, row in enumerate(document_rows, start=1):
        action = row.get("action", "")
        object_type = object_type_for_action(action)
        rows.append(
            {
                "release_item_id": f"REL-{index:03d}",
                "source_action": action,
                "object_type": object_type,
                "doc_number": row.get("doc_number", ""),
                "title": row.get("title", ""),
                "source_stage_file": row.get("source_stage_file", ""),
                "current_status": row.get("status", ""),
                "current_publish": row.get("publish", ""),
                "proposed_release_state": release_state_for_action(action),
                "release_allowed_now": "no",
                "release_block_reason": "human_review_pending; controlled_release_rehearsal_only",
                "required_review_source": "human_review_pack; release_rehearsal_review",
                "training_required": training_required_for_type(object_type),
                "old_version_action": old_version_action_for_type(object_type),
                "lims_governance_action": lims_action_for_type(object_type),
                "qualification_scope_note": "CMA 已取得；CNAS 申请中；不得表述为已取得 CNAS",
            }
        )
    return rows


def build_approval_rows(release_rows: list[dict[str, Any]]) -> list[dict[str, Any]]:
    rows: list[dict[str, Any]] = []
    for row in release_rows:
        if row["object_type"] == "current_procedure_reference":
            continue
        rows.append(
            {
                "approval_item_id": "APP-" + row["release_item_id"].split("-", 1)[1],
                "release_item_id": row["release_item_id"],
                "object_type": row["object_type"],
                "doc_number": row["doc_number"],
                "title": row["title"],
                "required_steps": "编制复核；技术复核；质量负责人审核；最高管理者或授权岗位批准；受控发布登记",
                "approver_roles": "文件管理员/技术负责人/质量负责人/最高管理者或授权批准人",
                "human_decision": "pending",
                "blocking_if_unresolved": "yes",
                "required_evidence": "审核意见、批准记录、发布日期、生效日期、发放范围、旧版处置记录",
            }
        )
    return rows


def build_training_rows(release_rows: list[dict[str, Any]]) -> list[dict[str, Any]]:
    rows: list[dict[str, Any]] = [
        {
            "training_item_id": "TRN-001",
            "topic": "第五版质量手册主要变化宣贯",
            "source_object": "XZTC/SC",
            "audience": "全体影响检测活动人员；管理层；授权签字人",
            "trigger": "第五版批准后、生效前",
            "required_before_effective": "yes",
            "training_evidence": "培训计划、签到、考核或效果确认、问题答疑记录",
            "status": "pending",
            "not_real_record": "yes",
        },
        {
            "training_item_id": "TRN-002",
            "topic": "LIMS 2022 程序目录现行状态确认",
            "source_object": "37 个现行 2022 程序文件",
            "audience": "文件管理员；质量负责人；相关过程负责人",
            "trigger": "第五版候选稿人工评审时",
            "required_before_effective": "yes",
            "training_evidence": "现行目录确认、发放范围核对、变更说明",
            "status": "pending",
            "not_real_record": "yes",
        },
        {
            "training_item_id": "TRN-003",
            "topic": "jewelry-qms 建设中系统使用边界",
            "source_object": "14-jewelry-qms实施计划与验证方案.md",
            "audience": "文件管理员；质量负责人；系统管理员；试运行人员",
            "trigger": "系统试运行前",
            "required_before_effective": "no",
            "training_evidence": "试运行说明、权限培训、数据备份恢复和审计追踪确认记录",
            "status": "pending",
            "not_real_record": "yes",
        },
    ]
    sequence = 4
    for row in release_rows:
        if row["object_type"] != "candidate_record_template_document":
            continue
        rows.append(
            {
                "training_item_id": f"TRN-{sequence:03d}",
                "topic": "候选记录模板填写与更正规则",
                "source_object": f"{row['doc_number']} {row['title']}",
                "audience": "对应过程填写人员；复核人员；文件管理员",
                "trigger": "记录模板批准启用前",
                "required_before_effective": "yes",
                "training_evidence": "模板填写说明、试填反馈、签核与保存规则确认",
                "status": "pending",
                "not_real_record": "yes",
            }
        )
        sequence += 1
    return rows


def build_obsolete_rows(release_rows: list[dict[str, Any]]) -> list[dict[str, Any]]:
    rows = [
        {
            "disposition_item_id": "OBS-001",
            "object": "质量手册第四版",
            "current_or_old_version": "第四版；现用受控文件",
            "proposed_action": "第五版批准生效后回收/标识作废；保留作废版本和修订审批记录",
            "required_evidence": "文件更改审批、发放回收记录、作废留存标识、培训宣贯记录",
            "status": "pending",
            "blocking_if_unresolved": "yes",
        }
    ]
    sequence = 2
    for row in release_rows:
        if row["object_type"] == "candidate_record_template_document":
            rows.append(
                {
                    "disposition_item_id": f"OBS-{sequence:03d}",
                    "object": f"{row['doc_number']} {row['title']}",
                    "current_or_old_version": "现用纸质或电子表单待人工匹配",
                    "proposed_action": "先核对现用表单；再决定替换、并行、修订或不启用",
                    "required_evidence": "现用表单样张、字段差异、保存期限、复核批准意见",
                    "status": "pending",
                    "blocking_if_unresolved": "yes",
                }
            )
            sequence += 1
    rows.append(
        {
            "disposition_item_id": f"OBS-{sequence:03d}",
            "object": "XZTC/CX-05-02-2022 仪器设备计量溯源结果确认表",
            "current_or_old_version": "LIMS 导出的编号附件/表单",
            "proposed_action": "确认归入程序附件、记录模板、历史附件保留、作废不导入或待补录为受控文件",
            "required_evidence": "05-02 原件、设备/溯源过程负责人意见、JL-6.4-01/JL-6.5-01 字段关系",
            "status": "pending",
            "blocking_if_unresolved": "yes",
        }
    )
    return rows


def build_gate_rows() -> list[dict[str, Any]]:
    return [
        {
            "gate_id": "POS-01",
            "topic": "资质状态",
            "expected_position": "已取得 CMA，CNAS 申请中",
            "evidence_file": "08-拍板确认与起草口径.md；10-质量手册第五版候选稿.md",
            "check_method": "手册可写 CMA 已取得和 CNAS 申请中；不得出现已取得 CNAS 的事实表述",
            "status": "locked",
            "blocking_if_failed": "yes",
        },
        {
            "gate_id": "POS-02",
            "topic": "现行程序目录",
            "expected_position": "以 LIMS 当前导出的 2022 程序清单作为现行程序目录",
            "evidence_file": "12-支持性程序目录-2022版.md；lims_preimport_package/documents_preimport.csv",
            "check_method": "37 个现行程序按 reference_existing_current 匹配；05-02 作为编号附件/表单分流",
            "status": "locked",
            "blocking_if_failed": "yes",
        },
        {
            "gate_id": "POS-03",
            "topic": "jewelry-qms 手册正文边界",
            "expected_position": "建设中系统，不写入手册正文",
            "evidence_file": "10-质量手册第五版候选稿.md；14-jewelry-qms实施计划与验证方案.md",
            "check_method": "手册正文不得出现 jewelry-qms；实施计划可出现并声明建设中",
            "status": "locked",
            "blocking_if_failed": "yes",
        },
        {
            "gate_id": "POS-04",
            "topic": "受控发布状态",
            "expected_position": "当前全部仍为候选/演练，不代表批准发布",
            "evidence_file": "controlled_release_rehearsal/release_rehearsal_manifest.json",
            "check_method": "release_allowed_now 必须全为 no；approval human_decision 必须 pending",
            "status": "locked",
            "blocking_if_failed": "yes",
        },
        {
            "gate_id": "POS-05",
            "topic": "数据库写入边界",
            "expected_position": "本包和 dry-run 均不写数据库",
            "evidence_file": "README.md；命令层 dry-run 报告",
            "check_method": "不得包含 SQL/DB 产物；apply 仍须人审通过和用户明确授权",
            "status": "locked",
            "blocking_if_failed": "yes",
        },
    ]


def build_effectiveness_rows() -> list[dict[str, Any]]:
    return [
        {
            "check_item_id": "EFF-01",
            "process": "文件控制",
            "check_method": "抽查第五版手册的编制、审核、批准、发放、作废回收记录",
            "sample_or_scope": "质量手册第五版及第四版作废记录",
            "acceptance_criteria": "有完整签核、发布日期、生效日期、发放范围和旧版处置记录",
            "evidence": "文件修订评审与发布记录；发放回收记录",
            "status": "pending",
        },
        {
            "check_item_id": "EFF-02",
            "process": "培训宣贯",
            "check_method": "抽查关键人员是否理解 CMA/CNAS 口径、2022 程序目录和系统建设边界",
            "sample_or_scope": "管理层、质量负责人、技术负责人、文件管理员、检测人员",
            "acceptance_criteria": "培训记录完整，访谈回答与手册口径一致",
            "evidence": "培训记录、签到、考核或访谈记录",
            "status": "pending",
        },
        {
            "check_item_id": "EFF-03",
            "process": "记录模板启用",
            "check_method": "选择 3 至 5 个模板试运行并核对字段、签核、保存和更正规则",
            "sample_or_scope": "JL-4.1-01、JL-7.11-01、JL-8.8-01 等",
            "acceptance_criteria": "字段可填、记录可追溯、保存期限和保密等级已确认",
            "evidence": "试运行记录、字段确认清单、问题整改记录",
            "status": "pending",
        },
        {
            "check_item_id": "EFF-04",
            "process": "jewelry-qms 试运行",
            "check_method": "仅在系统确认后验证权限、备份恢复、审计追踪和变更控制",
            "sample_or_scope": "建设中系统试点流程",
            "acceptance_criteria": "系统确认记录通过后，才可修订体系文件纳入正式运行",
            "evidence": "系统适用性确认、权限清单、备份恢复演练、审计追踪样例",
            "status": "pending",
        },
    ]


def render_overview(manifest: dict[str, Any]) -> str:
    counts = manifest["counts"]
    lines = [
        "# 受控发布治理演练总览",
        "",
        f"生成时间：{manifest['generated_at']}",
        "",
        "本包用于把第五版候选稿、记录模板和 LIMS 治理对象放入“审批、发布、培训、旧版处置、实施有效性检查”的干运行路径；不写数据库，不代表受控发布。",
        "",
        "## 已锁定口径",
        "",
    ]
    lines.extend(f"- {item}" for item in GUARDRAILS[2:])
    lines.extend(
        [
            "",
            "## 计数",
            "",
            f"- 发布对象：{counts['release_objects']}",
            f"- 审批签核演练项：{counts['approval_items']}",
            f"- 培训宣贯演练项：{counts['training_items']}",
            f"- 旧版处置演练项：{counts['obsolete_items']}",
            f"- 口径闸门：{counts['position_gates']}",
            f"- 实施有效性检查项：{counts['effectiveness_items']}",
            "",
            "## 文件清单",
            "",
        ]
    )
    for label, filename in manifest["files"].items():
        lines.append(f"- `{filename}`：{label}")
    lines.extend(["", "## 边界", ""])
    lines.extend(f"- {item}" for item in GUARDRAILS)
    lines.append("")
    return "\n".join(lines)


def render_matrix(release_rows: list[dict[str, Any]], gate_rows: list[dict[str, Any]]) -> str:
    action_counts: dict[str, int] = {}
    for row in release_rows:
        action_counts[row["object_type"]] = action_counts.get(row["object_type"], 0) + 1
    rows = [
        {
            "governance_stage": "候选准备",
            "lims_action": "预导入包和结构化关系 dry-run",
            "required_gate": "自动验证 findings=0；人工评审仍 pending",
            "write_boundary": "不写数据库",
        },
        {
            "governance_stage": "人工评审",
            "lims_action": "human_review_pack 与 workbench 供线下审查",
            "required_gate": "67 个人审项逐项回填并经预览校验",
            "write_boundary": "不修改 human_review_pack 原始清单",
        },
        {
            "governance_stage": "受控发布",
            "lims_action": "仅在批准后建立受控文件状态",
            "required_gate": "审批、培训、旧版处置均完成",
            "write_boundary": "未获授权不得 apply",
        },
        {
            "governance_stage": "实施有效性",
            "lims_action": "抽查记录、权限、备份、审计追踪和纠正措施闭环",
            "required_gate": "试运行证据和内审/管理评审输入",
            "write_boundary": "jewelry-qms 未确认前不得写入手册正文",
        },
    ]
    lines = [
        "# LIMS 治理动作矩阵",
        "",
        "本矩阵只描述治理路径，不写数据库，不代表受控发布。",
        "",
        "## 对象概览",
        "",
    ]
    for key in sorted(action_counts):
        lines.append(f"- {key}: {action_counts[key]}")
    lines.extend(["", "## 治理阶段", ""])
    lines.extend(render_table(rows, ["governance_stage", "lims_action", "required_gate", "write_boundary"]))
    lines.extend(["", "## 口径闸门", ""])
    lines.extend(render_table(gate_rows, ["gate_id", "topic", "expected_position", "status", "blocking_if_failed"]))
    lines.append("")
    return "\n".join(lines)


def build_rehearsal(stage_dir: Path, output_dir: Path) -> dict[str, Any]:
    preimport_dir = stage_dir / "lims_preimport_package"
    document_rows = read_csv(preimport_dir / "documents_preimport.csv")
    release_rows = build_release_rows(document_rows)
    approval_rows = build_approval_rows(release_rows)
    training_rows = build_training_rows(release_rows)
    obsolete_rows = build_obsolete_rows(release_rows)
    gate_rows = build_gate_rows()
    effectiveness_rows = build_effectiveness_rows()

    output_dir.mkdir(parents=True, exist_ok=True)
    files = {
        "overview": "00-受控发布演练总览.md",
        "release_objects": "01-发布对象清单.csv",
        "approval_rehearsal": "02-审批签核演练清单.csv",
        "training_rehearsal": "03-培训宣贯演练清单.csv",
        "obsolete_disposition": "04-旧版处置演练清单.csv",
        "lims_governance_matrix": "05-LIMS治理动作矩阵.md",
        "position_gates": "06-口径闸门检查表.csv",
        "effectiveness_checks": "07-实施有效性检查清单.csv",
        "manifest": "release_rehearsal_manifest.json",
        "readme": "README.md",
    }

    write_csv(output_dir / files["release_objects"], release_rows, RELEASE_FIELDS)
    write_csv(output_dir / files["approval_rehearsal"], approval_rows, APPROVAL_FIELDS)
    write_csv(output_dir / files["training_rehearsal"], training_rows, TRAINING_FIELDS)
    write_csv(output_dir / files["obsolete_disposition"], obsolete_rows, OBSOLETE_FIELDS)
    write_csv(output_dir / files["position_gates"], gate_rows, GATE_FIELDS)
    write_csv(output_dir / files["effectiveness_checks"], effectiveness_rows, EFFECTIVENESS_FIELDS)

    generated_at = dt.datetime.now().isoformat(timespec="seconds")
    manifest = {
        "generated_at": generated_at,
        "stage_dir": str(stage_dir),
        "preimport_dir": str(preimport_dir),
        "release_rehearsal_dir": str(output_dir),
        "status": "release_rehearsal_no_database_write",
        "guardrails": GUARDRAILS,
        "counts": {
            "release_objects": len(release_rows),
            "approval_items": len(approval_rows),
            "training_items": len(training_rows),
            "obsolete_items": len(obsolete_rows),
            "position_gates": len(gate_rows),
            "effectiveness_items": len(effectiveness_rows),
            "candidate_manual_objects": sum(1 for row in release_rows if row["object_type"] == "candidate_manual"),
            "current_procedure_references": sum(1 for row in release_rows if row["object_type"] == "current_procedure_reference"),
            "candidate_record_template_documents": sum(1 for row in release_rows if row["object_type"] == "candidate_record_template_document"),
            "attachment_form_pending": sum(1 for row in release_rows if row["object_type"] == "numbered_attachment_form_pending"),
        },
        "files": files,
    }
    (output_dir / files["manifest"]).write_text(json.dumps(manifest, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
    overview = render_overview(manifest)
    (output_dir / files["overview"]).write_text(overview, encoding="utf-8")
    (output_dir / files["lims_governance_matrix"]).write_text(render_matrix(release_rows, gate_rows), encoding="utf-8")
    readme_lines = [
        "# 受控发布治理演练包",
        "",
        "文件状态：干运行准备包，不写数据库，不代表受控发布。",
        "",
        "## 使用顺序",
        "",
        "1. 先看 `00-受控发布演练总览.md`。",
        "2. 再看 `01-发布对象清单.csv`，确认哪些只是现行目录引用、哪些是候选待发布对象。",
        "3. 用 `02-审批签核演练清单.csv`、`03-培训宣贯演练清单.csv`、`04-旧版处置演练清单.csv` 做人工评审前准备。",
        "4. 用 `06-口径闸门检查表.csv` 防止资质、程序目录和 jewelry-qms 边界写错。",
        "5. 用 `07-实施有效性检查清单.csv` 设计批准后的试运行和内审抽查。",
        "",
        "## 边界",
        "",
    ]
    readme_lines.extend(f"- {item}" for item in GUARDRAILS)
    readme_lines.append("")
    (output_dir / files["readme"]).write_text("\n".join(readme_lines), encoding="utf-8")
    return manifest


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--stage-dir", default=str(DEFAULT_STAGE_DIR))
    parser.add_argument("--output-dir")
    args = parser.parse_args()

    stage_dir = Path(args.stage_dir)
    output_dir = Path(args.output_dir) if args.output_dir else stage_dir / "controlled_release_rehearsal"
    manifest = build_rehearsal(stage_dir, output_dir)
    print(json.dumps(manifest, ensure_ascii=False, indent=2))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
