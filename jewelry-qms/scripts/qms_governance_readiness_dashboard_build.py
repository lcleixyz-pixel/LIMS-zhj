#!/usr/bin/env python3
"""Build a no-write governance readiness dashboard for the QMS preimport package."""

from __future__ import annotations

import argparse
import csv
import datetime as dt
import json
from pathlib import Path
from typing import Any


FILES = {
    "manifest": "governance_readiness_manifest.json",
    "overview": "00-治理就绪总览.md",
    "gate_register": "01-总闸门清单.csv",
    "human_task_register": "02-人工处理任务清单.csv",
    "command_checklist": "03-LIMS命令复核清单.md",
    "readme": "README.md",
}

GUARDRAILS = [
    "本包只汇总现有候选文件、模板、评审、发布演练、学习实施和第二阶段复核状态，不写数据库。",
    "本包不修改 human_review_pack、stage2_structured_review_workbench 或任何现用 Word 文件。",
    "本包不代表人工评审通过、真实培训完成、受控发布或正式写库授权。",
    "资质状态口径：已取得 CMA，CNAS 申请中；不得表述为已取得 CNAS。",
    "LIMS 当前导出的 2022 程序清单仍作为现行程序目录。",
    "jewelry-qms 仍为建设中系统，只进入实施计划、演练和治理准备材料，不写入质量手册正文。",
]

GATE_FIELDS = [
    "gate_id",
    "gate_group",
    "gate_name",
    "owner_role",
    "source_path",
    "total_items",
    "closed_items",
    "pending_items",
    "blocking_items",
    "current_status",
    "next_action",
    "evidence_needed",
    "blocks_apply",
    "not_real_record",
]

TASK_FIELDS = [
    "task_id",
    "gate_id",
    "task_group",
    "object_code",
    "object_name",
    "owner_role",
    "current_status",
    "source_path",
    "human_action",
    "evidence_to_check",
    "blocking_if_unresolved",
    "not_real_record",
]


def read_csv(path: Path) -> list[dict[str, str]]:
    if not path.exists():
        return []
    with path.open("r", encoding="utf-8-sig", newline="") as handle:
        return list(csv.DictReader(handle))


def read_json(path: Path) -> dict[str, Any]:
    if not path.exists():
        return {}
    return json.loads(path.read_text(encoding="utf-8"))


def write_csv(path: Path, rows: list[dict[str, Any]], fieldnames: list[str]) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    with path.open("w", encoding="utf-8", newline="") as handle:
        writer = csv.DictWriter(handle, fieldnames=fieldnames, extrasaction="ignore")
        writer.writeheader()
        for row in rows:
            writer.writerow({field: row.get(field, "") for field in fieldnames})


def rel(stage_dir: Path, path: Path) -> str:
    try:
        return path.relative_to(stage_dir).as_posix()
    except ValueError:
        return path.as_posix()


def count_pending(rows: list[dict[str, str]], field: str, pending_values: set[str] | None = None) -> int:
    pending_values = pending_values or {"", "pending", "pending_human_review"}
    return sum(1 for row in rows if row.get(field, "").strip() in pending_values)


def count_blocking(rows: list[dict[str, str]], field: str = "blocking_if_unresolved") -> int:
    return sum(1 for row in rows if row.get(field, "").strip().lower() in {"yes", "true", "1", "是"})


def count_int(rows: list[dict[str, str]], field: str) -> int:
    total = 0
    for row in rows:
        try:
            total += int(row.get(field, "0") or 0)
        except ValueError:
            continue
    return total


def gate(
    gate_id: str,
    gate_group: str,
    gate_name: str,
    owner_role: str,
    source_path: str,
    total_items: int,
    closed_items: int,
    pending_items: int,
    blocking_items: int,
    current_status: str,
    next_action: str,
    evidence_needed: str,
    blocks_apply: str = "yes",
) -> dict[str, Any]:
    return {
        "gate_id": gate_id,
        "gate_group": gate_group,
        "gate_name": gate_name,
        "owner_role": owner_role,
        "source_path": source_path,
        "total_items": total_items,
        "closed_items": closed_items,
        "pending_items": pending_items,
        "blocking_items": blocking_items,
        "current_status": current_status,
        "next_action": next_action,
        "evidence_needed": evidence_needed,
        "blocks_apply": blocks_apply,
        "not_real_record": "yes",
    }


def task(
    task_id: str,
    gate_id: str,
    task_group: str,
    object_code: str,
    object_name: str,
    owner_role: str,
    current_status: str,
    source_path: str,
    human_action: str,
    evidence_to_check: str,
    blocking_if_unresolved: str = "yes",
) -> dict[str, str]:
    return {
        "task_id": task_id,
        "gate_id": gate_id,
        "task_group": task_group,
        "object_code": object_code,
        "object_name": object_name,
        "owner_role": owner_role,
        "current_status": current_status,
        "source_path": source_path,
        "human_action": human_action,
        "evidence_to_check": evidence_to_check,
        "blocking_if_unresolved": blocking_if_unresolved,
        "not_real_record": "yes",
    }


def render_table(rows: list[dict[str, Any]], columns: list[str]) -> list[str]:
    lines = ["| " + " | ".join(columns) + " |", "|" + "|".join(["---"] * len(columns)) + "|"]
    for row in rows:
        values = [str(row.get(column, "")).replace("\n", " ").replace("|", "／") for column in columns]
        lines.append("| " + " | ".join(values) + " |")
    return lines


def status_from_pending(pending_items: int, blocking_items: int, missing: bool = False) -> str:
    if missing:
        return "missing_source"
    if blocking_items > 0:
        return "blocked_by_human_action"
    if pending_items > 0:
        return "pending_nonblocking"
    return "ready_after_review"


def build_dashboard(stage_dir: Path, output_dir: Path) -> dict[str, Any]:
    output_dir.mkdir(parents=True, exist_ok=True)

    preimport_manifest = read_json(stage_dir / "lims_preimport_package" / "preimport_manifest.json")
    write_preview_manifest = read_json(stage_dir / "lims_write_preview_package" / "write_preview_manifest.json")
    stage2_preview_manifest = read_json(stage_dir / "lims_stage2_write_preview_package" / "stage2_preview_manifest.json")
    stage2_decision_preview_manifest = read_json(
        stage_dir / "stage2_structured_review_decision_preview" / "stage2_review_decision_preview_manifest.json"
    )

    human_review_rows = read_csv(stage_dir / "human_review_workbench" / "decision_update_template.csv")
    field_template_rows = read_csv(stage_dir / "record_template_field_catalog" / "01-模板字段索引.csv")
    manual_decision_rows = read_csv(stage_dir / "manual_revision_path_pack" / "04-人工决策闸门.csv")
    release_approval_rows = read_csv(stage_dir / "controlled_release_rehearsal" / "02-审批签核演练清单.csv")
    release_execution_rows = read_csv(stage_dir / "release_execution_template_pack" / "01-发布执行记录模板索引.csv")
    staff_learning_rows = read_csv(stage_dir / "staff_training_implementation_pack" / "01-岗位学习任务矩阵.csv")
    question_rows = read_csv(stage_dir / "staff_training_implementation_pack" / "03-理解确认题库.csv")
    feedback_rows = read_csv(stage_dir / "staff_training_implementation_pack" / "04-问题反馈与修订回填模板.csv")
    stage2_review_rows = read_csv(stage_dir / "stage2_structured_review_workbench" / "05-人工复核意见回填模板.csv")

    gates: list[dict[str, Any]] = []
    tasks: list[dict[str, str]] = []

    preimport_counts = preimport_manifest.get("counts", {})
    preimport_total = sum(int(preimport_counts.get(key, 0) or 0) for key in [
        "documents",
        "record_form_templates",
        "traceability_rows",
        "manual_blocks",
        "external_sources",
    ])
    gates.append(gate(
        "GR-01",
        "候选包结构",
        "第五版候选文件、模板和追溯矩阵结构可识别",
        "文件管理员/质量负责人",
        "lims_preimport_package/preimport_manifest.json",
        preimport_total,
        preimport_total if preimport_manifest else 0,
        0 if preimport_manifest else 1,
        0 if preimport_manifest else 1,
        "ready_after_review" if preimport_manifest else "missing_source",
        "保持 dry-run 复核；后续只在人工评审完成后讨论 apply。",
        "17/18/21/24/26 等验证报告",
        "no",
    ))

    human_pending = count_pending(human_review_rows, "new_human_decision")
    human_blocking = sum(
        1 for row in human_review_rows
        if row.get("blocking_if_unresolved", "").strip().lower() == "yes"
        and row.get("new_human_decision", "").strip() == ""
    )
    gates.append(gate(
        "GR-02",
        "人工评审",
        "候选手册、记录模板、05-02 归属和 apply 前闸门人工评审",
        "最高管理者/质量负责人/文件管理员",
        "human_review_workbench/decision_update_template.csv",
        len(human_review_rows),
        len(human_review_rows) - human_pending,
        human_pending,
        human_blocking,
        status_from_pending(human_pending, human_blocking, not human_review_rows),
        "逐项填写 new_human_decision 和 review_comment，再生成回填预览。",
        "候选手册正文、现行程序、记录模板、真实运行证据和岗位责任",
    ))
    for row in human_review_rows:
        tasks.append(task(
            row.get("review_item_id", ""),
            "GR-02",
            row.get("review_type", ""),
            row.get("review_item_id", ""),
            row.get("display_name", ""),
            row.get("reviewer_role", ""),
            row.get("new_human_decision", "").strip() or row.get("current_decision", "pending"),
            "human_review_workbench/decision_update_template.csv",
            "填写 new_human_decision，并在 review_comment 写明依据或修订意见。",
            row.get("evidence_to_check", ""),
            row.get("blocking_if_unresolved", "yes"),
        ))

    field_pending = count_int(field_template_rows, "human_confirmation_field_count")
    gates.append(gate(
        "GR-03",
        "记录模板字段",
        "26 个候选记录模板字段逐项确认",
        "文件管理员/质量负责人/对应过程负责人",
        "record_template_field_catalog/01-模板字段索引.csv",
        field_pending,
        0,
        field_pending,
        field_pending,
        status_from_pending(field_pending, field_pending, not field_template_rows),
        "按模板确认字段含义、必填性、保存期限、保密等级、签核和现用表单一致性。",
        "record_template_field_catalog/02-字段级明细.csv 与全量试填表",
    ))
    for row in field_template_rows:
        tasks.append(task(
            "FIELD-" + row.get("template_code", ""),
            "GR-03",
            "record_template_field_confirmation",
            row.get("template_code", ""),
            row.get("template_name", ""),
            row.get("responsible_position", "文件管理员/质量负责人"),
            "pending",
            "record_template_field_catalog/01-模板字段索引.csv",
            "确认本模板字段字典和试填样表，必要时提出字段增删改名或保存规则调整。",
            f"需确认字段数 {row.get('human_confirmation_field_count', '')}；缺口：{row.get('missing_human_inputs', '')}",
            "yes",
        ))

    manual_pending = count_pending(manual_decision_rows, "decision_status")
    manual_blocking = count_blocking(manual_decision_rows)
    gates.append(gate(
        "GR-04",
        "质量手册换版",
        "XZTC/SC 第五版候选手册修订/换版路径确认",
        "最高管理者/质量负责人/文件管理员",
        "manual_revision_path_pack/04-人工决策闸门.csv",
        len(manual_decision_rows),
        len(manual_decision_rows) - manual_pending,
        manual_pending,
        manual_blocking,
        status_from_pending(manual_pending, manual_blocking, not manual_decision_rows),
        "确认同编号既有 published 文件的修订/换版路线、生效日期、旧版处置和 LIMS 同步动作。",
        "质量手册候选稿、第四版差异说明、文件控制程序和修订路径包",
    ))
    for row in manual_decision_rows:
        tasks.append(task(
            row.get("decision_id", ""),
            "GR-04",
            "manual_revision_decision",
            row.get("decision_id", ""),
            row.get("decision_topic", ""),
            row.get("responsible_role", ""),
            row.get("decision_status", "pending"),
            "manual_revision_path_pack/04-人工决策闸门.csv",
            "填写修订/换版路径决策，说明依据或需补充证据。",
            row.get("review_material", ""),
            row.get("blocking_if_unresolved", "yes"),
        ))

    approval_pending = count_pending(release_approval_rows, "human_decision")
    approval_blocking = count_blocking(release_approval_rows)
    gates.append(gate(
        "GR-05",
        "受控发布",
        "审批签核、发布发放和旧版处置演练确认",
        "文件管理员/质量负责人/最高管理者",
        "controlled_release_rehearsal/02-审批签核演练清单.csv",
        len(release_approval_rows),
        len(release_approval_rows) - approval_pending,
        approval_pending,
        approval_blocking,
        status_from_pending(approval_pending, approval_blocking, not release_approval_rows),
        "确认审批角色、签核步骤、发放范围、生效日期、旧版回收作废和留存要求。",
        "controlled_release_rehearsal/ 全包与 release_execution_template_pack/",
    ))
    for row in release_approval_rows:
        tasks.append(task(
            row.get("approval_item_id", ""),
            "GR-05",
            "controlled_release_approval",
            row.get("doc_number", ""),
            row.get("title", ""),
            row.get("approver_roles", ""),
            row.get("human_decision", "pending"),
            "controlled_release_rehearsal/02-审批签核演练清单.csv",
            "确认是否按所列步骤审批签核，并补充真实证据要求。",
            row.get("required_evidence", ""),
            row.get("blocking_if_unresolved", "yes"),
        ))

    release_execution_pending = count_pending(release_execution_rows, "review_status", {"pending_human_review", "pending", ""})
    gates.append(gate(
        "GR-06",
        "发布执行记录",
        "6 类发布执行记录候选模板人工确认",
        "文件管理员/质量负责人/系统管理员",
        "release_execution_template_pack/01-发布执行记录模板索引.csv",
        len(release_execution_rows),
        len(release_execution_rows) - release_execution_pending,
        release_execution_pending,
        release_execution_pending,
        status_from_pending(release_execution_pending, release_execution_pending, not release_execution_rows),
        "确认发布执行记录模板字段、保存/签核要求和 jewelry-qms 试运行确认记录是否适用。",
        "release_execution_template_pack/templates/*.md 与字段明细",
    ))
    for row in release_execution_rows:
        tasks.append(task(
            "REL-TPL-" + row.get("template_code", ""),
            "GR-06",
            "release_execution_template_review",
            row.get("template_code", ""),
            row.get("template_name", ""),
            row.get("responsible_position", ""),
            row.get("review_status", "pending_human_review"),
            "release_execution_template_pack/01-发布执行记录模板索引.csv",
            "确认候选发布执行记录模板是否保留、修改或延后启用。",
            row.get("markdown_file", ""),
            "yes",
        ))

    staff_pending = count_pending(staff_learning_rows, "human_confirmation_status")
    staff_blocking = sum(
        1 for row in staff_learning_rows
        if row.get("human_confirmation_status", "").strip() == "pending"
        and row.get("blocks_release_if_pending", "").strip().lower() == "yes"
    )
    gates.append(gate(
        "GR-07",
        "人员学习",
        "岗位学习任务和学习证据确认",
        "质量负责人/文件管理员/培训责任人",
        "staff_training_implementation_pack/01-岗位学习任务矩阵.csv",
        len(staff_learning_rows),
        len(staff_learning_rows) - staff_pending,
        staff_pending,
        staff_blocking,
        status_from_pending(staff_pending, staff_blocking, not staff_learning_rows),
        "确认真实岗位、学习材料、学习证据和发布前必须完成的学习任务。",
        "培训计划、签到、考核或效果确认、问题答疑记录",
    ))
    for row in staff_learning_rows:
        tasks.append(task(
            row.get("learning_task_id", ""),
            "GR-07",
            "staff_learning_task",
            row.get("source_training_item_id", ""),
            row.get("topic", ""),
            row.get("role_group", ""),
            row.get("human_confirmation_status", "pending"),
            "staff_training_implementation_pack/01-岗位学习任务矩阵.csv",
            "确认该岗位学习任务是否适用、是否发布前必须完成，以及需要收集哪些证据。",
            row.get("evidence_to_collect", ""),
            row.get("blocks_release_if_pending", "yes"),
        ))

    question_pending = count_pending(question_rows, "confirmation_status")
    gates.append(gate(
        "GR-08",
        "理解确认",
        "12 道理解确认题和人员理解证据",
        "质量负责人/文件管理员/培训责任人",
        "staff_training_implementation_pack/03-理解确认题库.csv",
        len(question_rows),
        len(question_rows) - question_pending,
        question_pending,
        question_pending,
        status_from_pending(question_pending, question_pending, not question_rows),
        "确认题目是否覆盖关键边界，并形成口头、书面或现场演示证据。",
        "理解确认、访谈记录、问题反馈和回填预览",
    ))
    for row in question_rows:
        tasks.append(task(
            row.get("question_id", ""),
            "GR-08",
            "comprehension_question",
            row.get("topic", ""),
            row.get("question", ""),
            row.get("responsible_roles", ""),
            row.get("confirmation_status", "pending"),
            "staff_training_implementation_pack/03-理解确认题库.csv",
            "确认该题是否保留，并记录人员理解确认方式。",
            row.get("expected_focus", ""),
            "yes",
        ))

    feedback_pending = sum(1 for row in feedback_rows if not row.get("human_decision", "").strip())
    feedback_blocking = count_blocking(feedback_rows)
    gates.append(gate(
        "GR-09",
        "问题反馈",
        "问题反馈与修订回填闭环",
        "质量负责人/文件管理员/对应过程负责人",
        "staff_training_implementation_pack/04-问题反馈与修订回填模板.csv",
        len(feedback_rows),
        len(feedback_rows) - feedback_pending,
        feedback_pending,
        feedback_blocking,
        status_from_pending(feedback_pending, feedback_blocking, not feedback_rows),
        "填写反馈处置、是否修订候选稿/模板/实施计划，并回填到对应评审项。",
        "问题反馈记录、修订说明、回填预览和验证报告",
    ))
    for row in feedback_rows:
        tasks.append(task(
            row.get("feedback_id", ""),
            "GR-09",
            "feedback_closure",
            row.get("feedback_topic", ""),
            row.get("source_material", ""),
            row.get("responsible_role", "待人工分配"),
            row.get("human_decision", "").strip() or "pending",
            "staff_training_implementation_pack/04-问题反馈与修订回填模板.csv",
            "填写 proposed_change、human_decision 和 review_comment，必要时联动修订包。",
            row.get("linked_review_item", ""),
            row.get("blocking_if_unresolved", "yes"),
        ))

    write_counts = write_preview_manifest.get("counts", {})
    write_total = sum(int(write_counts.get(key, 0) or 0) for key in [
        "documents_preview_rows",
        "record_template_preview_rows",
        "source_preview_rows",
    ])
    gates.append(gate(
        "GR-10",
        "第一阶段预览",
        "LIMS 第一阶段写库行级预览人工复核",
        "文件管理员/系统管理员/质量负责人",
        "lims_write_preview_package/write_preview_manifest.json",
        write_total,
        0,
        write_total,
        write_total,
        status_from_pending(write_total, write_total, not write_preview_manifest),
        "复核 27 条新增 draft、1 条质量手册修订路径、37 条现行程序引用、26 条记录模板和 4 条外来依据 upsert。",
        "lims_write_preview_package/*.csv 与 47 号 dry-run 报告",
    ))
    tasks.append(task(
        "WRITE-PREVIEW-REVIEW",
        "GR-10",
        "write_preview_review",
        "phase1",
        "第一阶段 documents/record_form_templates/qms_sources 行级预览",
        "系统管理员/文件管理员/质量负责人",
        "pending",
        "lims_write_preview_package/write_preview_manifest.json",
        "逐表确认预览动作是否符合受控文件和记录模板导入预期。",
        "46/47 号报告、write_preview_manifest.json 和三张预览 CSV",
        "yes",
    ))

    stage2_pending = count_pending(stage2_review_rows, "proposed_human_decision")
    stage2_blocking = sum(
        1 for row in stage2_review_rows
        if row.get("blocking_if_unresolved", "").strip().lower() == "yes"
        and not row.get("proposed_human_decision", "").strip()
    )
    gates.append(gate(
        "GR-11",
        "第二阶段复核",
        "手册块和块级链接人工复核",
        "质量负责人/技术负责人/文件管理员",
        "stage2_structured_review_workbench/05-人工复核意见回填模板.csv",
        len(stage2_review_rows),
        len(stage2_review_rows) - stage2_pending,
        stage2_pending,
        stage2_blocking,
        status_from_pending(stage2_pending, stage2_blocking, not stage2_review_rows),
        "逐条确认手册块、程序/附件/记录模板链接是否真实适用，再生成回填预览。",
        "stage2_structured_review_workbench/01-04 与 lims_stage2_write_preview_package/",
    ))
    for row in stage2_review_rows:
        tasks.append(task(
            row.get("decision_item_id", ""),
            "GR-11",
            "stage2_structured_review",
            row.get("target_code", ""),
            row.get("target_key", ""),
            "质量负责人/技术负责人/文件管理员",
            row.get("proposed_human_decision", "").strip() or "pending",
            "stage2_structured_review_workbench/05-人工复核意见回填模板.csv",
            "填写 proposed_human_decision 和 review_comment，确认链接保留、修订或移除。",
            row.get("required_evidence", ""),
            row.get("blocking_if_unresolved", "yes"),
        ))

    stage2_preview_counts = stage2_decision_preview_manifest.get("counts", {})
    stage2_preview_blocking = int(stage2_preview_counts.get("blocking_items", 0) or 0)
    gates.append(gate(
        "GR-12",
        "第二阶段回填预览",
        "第二阶段复核意见回填预览无阻断",
        "质量负责人/文件管理员/系统管理员",
        "stage2_structured_review_decision_preview/stage2_review_decision_preview_manifest.json",
        int(stage2_preview_counts.get("decision_rows", 0) or 0),
        int(stage2_preview_counts.get("accepted_for_preview", 0) or 0),
        stage2_preview_blocking,
        stage2_preview_blocking,
        status_from_pending(stage2_preview_blocking, stage2_preview_blocking, not stage2_decision_preview_manifest),
        "二阶段复核意见填写后重跑预览，确认无非法决策、缺少说明或仍阻断项。",
        "63/64/67/68 号报告和 stage2_review_decision_preview_manifest.json",
    ))

    gates.append(gate(
        "GR-13",
        "用户授权",
        "正式 apply 前用户明确授权",
        "用户/总控",
        ".team/任务板.md",
        1,
        0,
        1,
        1,
        "blocked_by_human_action",
        "只有在上述评审、培训、发布和预览闸门关闭后，才讨论是否授权正式 apply。",
        "用户明确授权、批准记录、完整 dry-run 证据",
    ))
    tasks.append(task(
        "USER-APPLY-AUTHORIZATION",
        "GR-13",
        "user_authorization",
        "apply",
        "正式 LIMS apply 授权",
        "用户/总控",
        "pending",
        ".team/任务板.md",
        "确认是否允许进入正式 apply；未确认前不得写生产库。",
        "完整人审、手册修订、人员学习、发布执行、二阶段复核和命令报告",
        "yes",
    ))

    blocking_gates = sum(1 for row in gates if int(row.get("blocking_items", 0) or 0) > 0 and row.get("blocks_apply") == "yes")
    blocking_tasks = sum(1 for row in tasks if row.get("blocking_if_unresolved", "").strip().lower() == "yes" and row.get("current_status", "") in {"", "pending", "pending_human_review"})
    ready_for_lims_apply = "yes" if blocking_gates == 0 and blocking_tasks == 0 else "no"

    manifest = {
        "generated_at": dt.datetime.now().isoformat(timespec="seconds"),
        "status": "governance_readiness_no_database_write",
        "stage_dir": str(stage_dir),
        "dashboard_dir": str(output_dir),
        "readiness": "ready_for_lims_apply" if ready_for_lims_apply == "yes" else "blocked_by_governance_open_items",
        "ready_for_lims_apply": ready_for_lims_apply,
        "guardrails": GUARDRAILS,
        "counts": {
            "gate_rows": len(gates),
            "blocking_gates": blocking_gates,
            "human_task_rows": len(tasks),
            "blocking_tasks": blocking_tasks,
            "human_review_pending": human_pending,
            "field_confirmation_items": field_pending,
            "manual_revision_pending": manual_pending,
            "release_approval_pending": approval_pending,
            "release_execution_templates_pending": release_execution_pending,
            "staff_learning_pending": staff_pending,
            "staff_learning_blocking": staff_blocking,
            "comprehension_questions_pending": question_pending,
            "feedback_pending": feedback_pending,
            "write_preview_rows_pending_review": write_total,
            "stage2_review_pending": stage2_pending,
            "stage2_preview_blocking": stage2_preview_blocking,
            "database_write_performed": 0,
        },
        "files": FILES,
    }

    write_csv(output_dir / FILES["gate_register"], gates, GATE_FIELDS)
    write_csv(output_dir / FILES["human_task_register"], tasks, TASK_FIELDS)
    (output_dir / FILES["overview"]).write_text(render_overview(manifest, gates), encoding="utf-8")
    (output_dir / FILES["command_checklist"]).write_text(render_command_checklist(manifest), encoding="utf-8")
    (output_dir / FILES["readme"]).write_text(render_readme(manifest), encoding="utf-8")
    (output_dir / FILES["manifest"]).write_text(json.dumps(manifest, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
    return manifest


def render_overview(manifest: dict[str, Any], gates: list[dict[str, Any]]) -> str:
    counts = manifest["counts"]
    lines = [
        "# QMS 治理就绪总览",
        "",
        "本文件把第五版候选修订包、LIMS 预导入、人工评审、字段确认、受控发布演练、人员学习实施和第二阶段结构化复核合并成一张总闸门表。",
        "它只用于治理准备和验收导航，不写数据库，不代表人工评审通过或受控发布。",
        "",
        "## 总体结论",
        "",
        f"- readiness：{manifest['readiness']}",
        f"- ready_for_lims_apply：{manifest['ready_for_lims_apply']}",
        f"- 总闸门：{counts['gate_rows']}",
        f"- 仍阻断闸门：{counts['blocking_gates']}",
        f"- 人工处理任务：{counts['human_task_rows']}",
        f"- 仍阻断任务：{counts['blocking_tasks']}",
        f"- 数据库写入：{counts['database_write_performed']}",
        "",
        "## 关键阻断数",
        "",
        f"- 人工评审 pending：{counts['human_review_pending']}",
        f"- 记录模板字段待确认：{counts['field_confirmation_items']}",
        f"- 质量手册修订/换版决策 pending：{counts['manual_revision_pending']}",
        f"- 审批签核 pending：{counts['release_approval_pending']}",
        f"- 发布执行记录模板待确认：{counts['release_execution_templates_pending']}",
        f"- 岗位学习任务 pending：{counts['staff_learning_pending']}，其中发布前阻断：{counts['staff_learning_blocking']}",
        f"- 理解确认题 pending：{counts['comprehension_questions_pending']}",
        f"- 问题反馈回填 pending：{counts['feedback_pending']}",
        f"- 第一阶段写库预览待复核行：{counts['write_preview_rows_pending_review']}",
        f"- 第二阶段人工复核 pending：{counts['stage2_review_pending']}",
        f"- 第二阶段回填预览阻断项：{counts['stage2_preview_blocking']}",
        "",
        "## 总闸门清单",
        "",
    ]
    lines.extend(render_table(
        gates,
        ["gate_id", "gate_group", "gate_name", "pending_items", "blocking_items", "current_status", "next_action"],
    ))
    lines.extend(["", "## 边界", ""])
    lines.extend(f"- {item}" for item in manifest["guardrails"])
    lines.append("")
    return "\n".join(lines)


def render_command_checklist(manifest: dict[str, Any]) -> str:
    lines = [
        "# LIMS 命令复核清单",
        "",
        "本清单用于后续复跑 `qms:preimport-package` 时确认所有治理包都被命令层读取。当前仍不得正式 apply。",
        "",
        "## 建议 dry-run 参数",
        "",
        "- `--review-dir human_review_pack`",
        "- `--field-catalog-dir record_template_field_catalog`",
        "- `--release-plan-dir controlled_release_rehearsal`",
        "- `--release-execution-dir release_execution_template_pack`",
        "- `--manual-revision-dir manual_revision_path_pack`",
        "- `--staff-training-dir staff_training_implementation_pack`",
        "- `--stage2-review-dir stage2_structured_review_workbench`",
        "- `--stage2-review-preview-dir stage2_structured_review_decision_preview`",
        "- `--governance-readiness-dir governance_readiness_dashboard`",
        "- `--stage2-check`",
        "",
        "## 期望状态",
        "",
        f"- governance_readiness_status：passed",
        f"- governance_readiness_ready_for_lims_apply：{manifest['ready_for_lims_apply']}",
        f"- governance_readiness_blocking_tasks：{manifest['counts']['blocking_tasks']}",
        "- dry-run 可以通过结构检查，但 apply/apply-rehearsal 应在阻断任务未关闭时返回 blocked。",
        "",
        "## 不允许事项",
        "",
    ]
    lines.extend(f"- {item}" for item in GUARDRAILS)
    lines.append("")
    return "\n".join(lines)


def render_readme(manifest: dict[str, Any]) -> str:
    return "\n".join([
        "# governance_readiness_dashboard",
        "",
        "用途：把第五版候选修订准备包的全部关键闸门汇总成一个只读治理就绪总览，方便用户、质量负责人和 LIMS 命令层同时判断下一步。",
        "",
        "## 文件",
        "",
        "- `governance_readiness_manifest.json`：机器可读清单。",
        "- `00-治理就绪总览.md`：给人看的总览和关键阻断数。",
        "- `01-总闸门清单.csv`：闸门级状态表。",
        "- `02-人工处理任务清单.csv`：人工要处理的任务列表。",
        "- `03-LIMS命令复核清单.md`：命令层复跑参数和期望状态。",
        "",
        "## 当前结论",
        "",
        f"- readiness：{manifest['readiness']}",
        f"- ready_for_lims_apply：{manifest['ready_for_lims_apply']}",
        f"- 阻断任务：{manifest['counts']['blocking_tasks']}",
        "",
        "## 边界",
        "",
        *[f"- {item}" for item in GUARDRAILS],
        "",
    ])


def render_report(manifest: dict[str, Any]) -> str:
    lines = [
        "# QMS 治理就绪总览包生成报告",
        "",
        f"生成时间：{manifest['generated_at']}",
        f"输出目录：`{manifest['dashboard_dir']}`",
        f"结论：{manifest['readiness']}",
        "",
        "## 计数",
        "",
    ]
    for key, value in manifest["counts"].items():
        lines.append(f"- {key}: {value}")
    lines.extend(["", "## 边界", ""])
    lines.extend(f"- {item}" for item in manifest["guardrails"])
    lines.append("")
    return "\n".join(lines)


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--stage-dir", required=True)
    parser.add_argument("--output-dir")
    parser.add_argument("--json-out")
    parser.add_argument("--md-out")
    args = parser.parse_args()

    stage_dir = Path(args.stage_dir)
    output_dir = Path(args.output_dir) if args.output_dir else stage_dir / "governance_readiness_dashboard"
    manifest = build_dashboard(stage_dir, output_dir)
    print(json.dumps(manifest, ensure_ascii=False, indent=2))
    if args.json_out:
        Path(args.json_out).write_text(json.dumps(manifest, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
    if args.md_out:
        Path(args.md_out).write_text(render_report(manifest), encoding="utf-8")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
