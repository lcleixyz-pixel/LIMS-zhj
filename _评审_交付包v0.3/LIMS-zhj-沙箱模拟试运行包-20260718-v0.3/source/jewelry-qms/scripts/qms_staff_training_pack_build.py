#!/usr/bin/env python3
"""Build a staff-facing QMS learning and implementation pack."""

from __future__ import annotations

import argparse
import csv
import datetime as dt
import json
import re
from collections import defaultdict
from pathlib import Path
from typing import Any


DEFAULT_STAGE_DIR = Path(
    "/Users/lc.leixyz/Documents/AI工作台/01-项目代码/LIMS-zhj/.team/交接箱/2026-07-07-第五版候选修订准备"
)

STATUS = "staff_training_implementation_no_database_write"
NOT_REAL_MARKER = "SIMULATED_TRAINING_NOT_REAL_RECORD"


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


def write_text(path: Path, text: str) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text(text, encoding="utf-8")


def rel(path: Path, base: Path) -> str:
    return str(path.relative_to(base))


def stable_slug(value: str) -> str:
    slug = re.sub(r"[^0-9A-Za-z\u4e00-\u9fff._-]+", "-", value).strip("-")
    return slug or "untitled"


def split_audience(value: str) -> list[str]:
    roles = [part.strip() for part in re.split(r"[；;、,，]", value or "") if part.strip()]
    return roles or ["待分配"]


def record_template_code(source_object: str) -> str:
    match = re.search(r"(JL-\d+(?:\.\d+)?-\d+)", source_object)
    return match.group(1) if match else ""


def file_map_by_code(stage_dir: Path, folder: str, suffix: str) -> dict[str, str]:
    result: dict[str, str] = {}
    for path in (stage_dir / folder).glob(f"JL-*{suffix}"):
        code = "-".join(path.name.split("-", 3)[:3])
        result[code] = f"{folder}/{path.name}"
    return result


def material_paths_for_training(
    row: dict[str, str],
    field_catalog_files: dict[str, str],
    trial_files: dict[str, str],
) -> str:
    source = row.get("source_object", "")
    topic = row.get("topic", "")
    code = record_template_code(source)
    materials = []
    if source == "XZTC/SC" or "质量手册" in topic:
        materials.extend(
            [
                "10-质量手册第五版候选稿.md",
                "11-第四版到第五版候选修订说明.md",
                "manual_revision_path_pack/00-质量手册修订换版路径总览.md",
                "lims_write_preview_package/04-write-preview-summary.md",
            ]
        )
    elif "程序目录" in topic or "2022 程序" in source:
        materials.extend(
            [
                "12-支持性程序目录-2022版.md",
                "controlled_release_rehearsal/01-发布对象清单.csv",
                "controlled_release_rehearsal/06-口径闸门检查表.csv",
            ]
        )
    elif "jewelry-qms" in source or "jewelry-qms" in topic:
        materials.extend(
            [
                "14-jewelry-qms实施计划与验证方案.md",
                "release_execution_template_pack/templates/JL-REL-06-jewelry-qms试运行与适用性确认记录.md",
                "23-LIMS预导入命令使用说明与阻断项.md",
            ]
        )
    elif code:
        materials.extend(
            [
                "13-记录模板包-候选清单.md",
                field_catalog_files.get(code, f"record_template_field_catalog/templates/{code}-字段字典.md"),
                trial_files.get(code, f"record_template_full_trial_pack/{code}-试填.md"),
                "record_template_field_catalog/03-通用字段覆盖矩阵.md",
            ]
        )
    else:
        materials.extend(
            [
                "human_review_workbench/00-人工评审总览.md",
                "controlled_release_rehearsal/00-受控发布演练总览.md",
            ]
        )
    return "；".join(dict.fromkeys(materials))


def implementation_check_for_training(row: dict[str, str]) -> str:
    topic = row.get("topic", "")
    if "质量手册" in topic:
        return "能说明第五版候选稿仍需人工评审、修订批准、发布宣贯和旧版作废，不能直接替代第四版。"
    if "程序目录" in topic:
        return "能确认 37 个 2022 程序为现行目录基线，并知道 05-02 仍需单独归属判定。"
    if "jewelry-qms" in topic:
        return "能说明 jewelry-qms 目前仅用于实施计划、试运行和治理准备，不写入质量手册正文。"
    return "能按字段字典和试填样表说明记录何时填写、谁填写、谁复核、如何更正、保存在哪里。"


def build_role_matrix(
    training_rows: list[dict[str, str]],
    field_catalog_files: dict[str, str],
    trial_files: dict[str, str],
) -> list[dict[str, str]]:
    role_rows: list[dict[str, str]] = []
    for row in training_rows:
        item_id = row.get("training_item_id", "")
        for index, role in enumerate(split_audience(row.get("audience", "")), start=1):
            required = row.get("required_before_effective", "")
            role_rows.append(
                {
                    "learning_task_id": f"{item_id}-R{index:02d}",
                    "source_training_item_id": item_id,
                    "role_group": role,
                    "topic": row.get("topic", ""),
                    "source_object": row.get("source_object", ""),
                    "trigger": row.get("trigger", ""),
                    "required_before_effective": required,
                    "blocks_release_if_pending": "yes" if required == "yes" else "no",
                    "learning_materials": material_paths_for_training(row, field_catalog_files, trial_files),
                    "implementation_check": implementation_check_for_training(row),
                    "evidence_to_collect": row.get("training_evidence", ""),
                    "human_confirmation_status": "pending",
                    "not_real_record": "yes",
                    "not_real_record_marker": NOT_REAL_MARKER,
                }
            )
    return role_rows


def build_material_index() -> list[dict[str, str]]:
    return [
        {
            "material_id": "MAT-001",
            "category": "manual",
            "title": "质量手册第五版候选稿",
            "path": "10-质量手册第五版候选稿.md",
            "primary_audience": "质量负责人；文件管理员；管理层；授权签字人",
            "purpose": "理解候选手册正文和条款覆盖，不代表已批准发布。",
            "must_read_before_effective": "yes",
        },
        {
            "material_id": "MAT-002",
            "category": "revision",
            "title": "第四版到第五版候选修订说明",
            "path": "11-第四版到第五版候选修订说明.md",
            "primary_audience": "质量负责人；文件管理员；最高管理者",
            "purpose": "核对修订原因、影响范围和批准前待确认事项。",
            "must_read_before_effective": "yes",
        },
        {
            "material_id": "MAT-003",
            "category": "procedure_catalog",
            "title": "支持性程序目录 2022 版",
            "path": "12-支持性程序目录-2022版.md",
            "primary_audience": "文件管理员；质量负责人；过程负责人",
            "purpose": "确认当前程序目录以 LIMS 导出的 2022 清单为基线。",
            "must_read_before_effective": "yes",
        },
        {
            "material_id": "MAT-004",
            "category": "record_templates",
            "title": "记录模板候选清单",
            "path": "13-记录模板包-候选清单.md",
            "primary_audience": "质量负责人；过程负责人；填写人员；复核人员",
            "purpose": "了解 26 个候选记录模板的适用条款和用途。",
            "must_read_before_effective": "yes",
        },
        {
            "material_id": "MAT-005",
            "category": "field_catalog",
            "title": "记录模板字段字典",
            "path": "record_template_field_catalog/00-字段字典总览.md",
            "primary_audience": "文件管理员；过程负责人；填写人员；复核人员",
            "purpose": "逐字段确认填写责任、必填性、更正规则、保存期限和保密等级。",
            "must_read_before_effective": "yes",
        },
        {
            "material_id": "MAT-006",
            "category": "full_trial",
            "title": "26 个记录模板全量模拟试填",
            "path": "record_template_full_trial_pack/README.md",
            "primary_audience": "填写人员；复核人员；文件管理员",
            "purpose": "用模拟样表检查字段可填性，不作为真实运行记录。",
            "must_read_before_effective": "yes",
        },
        {
            "material_id": "MAT-007",
            "category": "manual_revision",
            "title": "质量手册修订/换版路径包",
            "path": "manual_revision_path_pack/00-质量手册修订换版路径总览.md",
            "primary_audience": "文件管理员；质量负责人；最高管理者",
            "purpose": "确认 XZTC/SC 必须走既有文件修订/换版路径。",
            "must_read_before_effective": "yes",
        },
        {
            "material_id": "MAT-008",
            "category": "release",
            "title": "受控发布治理演练包",
            "path": "controlled_release_rehearsal/00-受控发布演练总览.md",
            "primary_audience": "文件管理员；质量负责人；培训责任人",
            "purpose": "确认审批签核、培训宣贯、旧版处置和有效性检查安排。",
            "must_read_before_effective": "yes",
        },
        {
            "material_id": "MAT-009",
            "category": "execution_records",
            "title": "发布执行记录模板包",
            "path": "release_execution_template_pack/00-发布执行记录模板总览.md",
            "primary_audience": "文件管理员；培训责任人；系统管理员",
            "purpose": "确认发布执行时应留下哪些记录模板和字段。",
            "must_read_before_effective": "yes",
        },
        {
            "material_id": "MAT-010",
            "category": "lims",
            "title": "jewelry-qms 实施计划与验证方案",
            "path": "14-jewelry-qms实施计划与验证方案.md",
            "primary_audience": "系统管理员；质量负责人；试运行人员",
            "purpose": "明确 jewelry-qms 当前只作为建设中系统和试运行对象。",
            "must_read_before_effective": "no",
        },
        {
            "material_id": "MAT-011",
            "category": "human_review",
            "title": "人工评审工作台",
            "path": "human_review_workbench/00-人工评审总览.md",
            "primary_audience": "所有评审角色",
            "purpose": "承接 67 个 pending 决策，作为正式回填前的人审工作面。",
            "must_read_before_effective": "yes",
        },
        {
            "material_id": "MAT-012",
            "category": "command_gate",
            "title": "LIMS 预导入命令使用说明与阻断项",
            "path": "23-LIMS预导入命令使用说明与阻断项.md",
            "primary_audience": "文件管理员；系统管理员；质量负责人",
            "purpose": "理解 dry-run、apply、rehearsal、write-preview 的边界和阻断项。",
            "must_read_before_effective": "yes",
        },
    ]


def build_question_bank() -> list[dict[str, str]]:
    rows = [
        ("Q-001", "质量手册", "第五版候选稿目前能否直接替代第四版？", "应回答不能；需人工评审、批准、生效、发放、宣贯和旧版作废。"),
        ("Q-002", "资质状态", "手册和培训材料中 CNAS 状态应如何表述？", "应回答已取得 CMA，CNAS 申请中；不得写已取得 CNAS。"),
        ("Q-003", "程序目录", "当前程序目录基线是什么？", "应回答以 LIMS 当前导出的 2022 程序清单为现行目录。"),
        ("Q-004", "05-02", "XZTC/CX-05-02-2022 当前为什么不能直接当普通程序发布？", "应回答其为编号附件/表单归属待判定项，需确认是程序附件、记录模板或其他处置。"),
        ("Q-005", "记录模板", "候选记录模板启用前至少要确认哪些字段问题？", "应回答字段含义、必填性、填写责任、复核、保存期限、保密等级和更正规则。"),
        ("Q-006", "模拟试填", "全量试填样表是否可以作为真实运行记录？", "应回答不能；带 SIMULATED_TRIAL_NOT_REAL_RECORD 标识。"),
        ("Q-007", "质量手册修订", "XZTC/SC 第五版候选稿为什么不能按新增同编号草稿直接写入？", "应回答 LIMS 已存在同编号 published 受控文件，应走既有文件修订/换版路径。"),
        ("Q-008", "受控发布", "文件批准后还需留下哪些发布证据？", "应回答审批签核、发放范围、培训宣贯、旧版作废回收、实施有效性检查等。"),
        ("Q-009", "jewelry-qms", "jewelry-qms 当前可否写入质量手册正文作为正式系统？", "应回答不能；目前只进入实施计划、试运行和治理准备材料。"),
        ("Q-010", "LIMS 命令", "真实 apply 的前置条件是什么？", "应回答需人工评审包全部通过、ack、相关包校验通过和用户明确授权。"),
        ("Q-011", "电子记录", "LIMS/电子记录投入使用前应确认哪些方面？", "应回答权限、审计追踪、备份恢复、数据完整性、适用性确认和更正留痕。"),
        ("Q-012", "问题反馈", "发现候选稿或模板问题时应如何回填？", "应回答先进入反馈/决策预览，不直接改受控文件或写库。"),
    ]
    return [
        {
            "question_id": qid,
            "topic": topic,
            "question": question,
            "expected_focus": focus,
            "answer_type": "口头确认/书面确认/现场演示均可",
            "responsible_roles": "质量负责人；文件管理员；相关过程负责人",
            "evidence_reference": "培训签到、理解确认、问题反馈和回填预览",
            "confirmation_status": "pending",
            "not_real_record": "yes",
        }
        for qid, topic, question, focus in rows
    ]


def build_feedback_template() -> list[dict[str, str]]:
    topics = [
        ("FB-001", "第五版候选手册正文事实错误或职责不匹配", "10-质量手册第五版候选稿.md", "manual_clause"),
        ("FB-002", "第四版到第五版修订说明需补充", "11-第四版到第五版候选修订说明.md", "manual_revision"),
        ("FB-003", "2022 程序目录或 05-02 归属有疑问", "12-支持性程序目录-2022版.md", "procedure_catalog"),
        ("FB-004", "候选记录模板字段需新增/删除/改名", "record_template_field_catalog/02-字段级明细.csv", "record_template_field"),
        ("FB-005", "保存期限、保密等级或保存位置待确认", "record_template_field_catalog/02-字段级明细.csv", "record_control"),
        ("FB-006", "质量手册修订/换版路径需调整", "manual_revision_path_pack/04-人工决策闸门.csv", "manual_revision_path"),
        ("FB-007", "受控发布、培训或旧版处置安排需调整", "controlled_release_rehearsal/03-培训宣贯演练清单.csv", "controlled_release"),
        ("FB-008", "jewelry-qms 试运行边界或系统字段需调整", "14-jewelry-qms实施计划与验证方案.md", "lims_trial"),
    ]
    return [
        {
            "feedback_id": feedback_id,
            "feedback_topic": topic,
            "source_material": material,
            "issue_type": issue_type,
            "responsible_role": "待人工分配",
            "proposed_change": "",
            "human_decision": "",
            "review_comment": "",
            "linked_review_item": "",
            "blocking_if_unresolved": "yes",
            "not_real_record": "yes",
        }
        for feedback_id, topic, material, issue_type in topics
    ]


def render_table(rows: list[dict[str, Any]], columns: list[str]) -> list[str]:
    lines = ["| " + " | ".join(columns) + " |", "|" + "|".join(["---"] * len(columns)) + "|"]
    for row in rows:
        values = [str(row.get(column, "")).replace("\n", " ").replace("|", "／") for column in columns]
        lines.append("| " + " | ".join(values) + " |")
    return lines


def render_overview(manifest: dict[str, Any]) -> str:
    counts = manifest["counts"]
    lines = [
        "# 机构人员学习实施包总览",
        "",
        "文件状态：候选学习实施材料，不写数据库，不代表真实培训完成、人工评审通过或受控发布。",
        "",
        "## 当前用途",
        "",
        "本包把第五版候选手册、2022 程序目录、记录模板字段字典、受控发布演练、质量手册修订路径和 jewelry-qms 试运行边界，整理成机构人员可执行的学习和确认工作面。",
        "",
        "## 计数",
        "",
        f"- 培训宣贯源条目：{counts['training_source_items']}",
        f"- 岗位学习任务：{counts['role_learning_tasks']}",
        f"- 学习材料入口：{counts['learning_materials']}",
        f"- 理解确认题：{counts['comprehension_questions']}",
        f"- 问题反馈模板行：{counts['feedback_rows']}",
        f"- 岗位一页卡：{counts['role_cards']}",
        f"- 生效前必需源培训项：{counts['required_before_effective_source_items']}",
        "",
        "## 建议实施顺序",
        "",
        "1. 文件管理员先核对 `01-岗位学习任务矩阵.csv`，确认每类人员是否覆盖。",
        "2. 各角色按 `role_cards/` 中的一页卡阅读材料并记录问题。",
        "3. 质量负责人组织理解确认，可使用 `03-理解确认题库.csv`。",
        "4. 发现问题先填 `04-问题反馈与修订回填模板.csv`，再进入人工评审回填预览。",
        "5. 只有人工评审、修订路径、受控发布和培训证据均关闭后，才讨论 LIMS 正式 apply。",
        "",
        "## 边界",
        "",
    ]
    for guardrail in manifest["guardrails"]:
        lines.append(f"- {guardrail}")
    lines.append("")
    return "\n".join(lines)


def render_lims_boundary() -> str:
    return "\n".join(
        [
            "# jewelry-qms 试运行学习边界确认",
            "",
            "文件状态：学习实施候选材料，不写数据库，不代表 jewelry-qms 已正式投用。",
            "",
            "## 必须讲清的边界",
            "",
            "- 资质状态按已取得 CMA、CNAS 申请中处理；不得写成已取得 CNAS。",
            "- jewelry-qms 仍为建设中系统，只进入实施计划、试运行和治理准备材料，不写入质量手册正文。",
            "- 试运行数据、模拟试填、apply-rehearsal 和 write-preview 均不等同于真实运行记录。",
            "- 真实使用前应完成权限、审计追踪、备份恢复、数据更正留痕和适用性确认。",
            "- 任何正式写库、发布、旧版作废和培训完成结论，均需人工评审和用户明确授权。",
            "",
            "## 现场确认问题",
            "",
            "1. 哪些岗位需要登录 jewelry-qms 试运行环境？",
            "2. 哪些记录仍以纸质或现用表格为准？",
            "3. 哪些 LIMS 字段需要与现用记录表逐项对应？",
            "4. 谁负责确认备份恢复、权限和审计追踪？",
            "5. 试运行问题如何反馈到人工评审回填预览？",
            "",
        ]
    )


def render_training_record_template() -> str:
    return "\n".join(
        [
            "# 体系文件学习实施与理解确认记录候选模板",
            "",
            "文件状态：候选记录模板，不写数据库，不代表真实培训记录形成。",
            "",
            "## 适用场景",
            "",
            "用于第五版候选手册、记录模板、受控发布演练、质量手册修订路径和 jewelry-qms 试运行边界的学习宣贯。正式启用前，应由文件管理员确认编号、保存位置、保存期限、保密等级和签核要求。",
            "",
            "## 候选字段",
            "",
            "| 字段 | 填写要求 |",
            "|---|---|",
            "| record_number | 记录编号，正式启用前由文件管理员确认编号规则。 |",
            "| training_topic | 学习主题，对应 `03-培训宣贯演练清单.csv` 或岗位一页卡。 |",
            "| trainee_group | 参加人员或岗位组。 |",
            "| learning_materials | 已阅读材料路径。 |",
            "| comprehension_check | 理解确认方式，可为口头问答、书面确认、试填演示或现场问答。 |",
            "| questions_follow_up | 未理解或需修订的问题。 |",
            "| trainer | 培训/宣贯责任人。 |",
            "| reviewer | 复核人，待组织确认。 |",
            "| approval_status | pending/approved/needs_revision/deferred，真实启用前不得预填 approved。 |",
            "| evidence_reference | 签到、确认、问题反馈、回填预览或 LIMS 试运行证据位置。 |",
            "| correction_rule | 更正时保留原始信息、更正原因、更正日期、责任人和复核痕迹。 |",
            "| not_real_record_marker | 本候选模板使用时应标识 `SIMULATED_TRAINING_NOT_REAL_RECORD`，防止误作真实培训记录。 |",
            "",
            "## 边界",
            "",
            "- 本模板不写数据库，不代表真实培训完成。",
            "- 本模板不代表人工评审通过、文件批准、受控发布或 jewelry-qms 正式投用。",
            "- 资质状态按已取得 CMA、CNAS 申请中处理；不得写成已取得 CNAS。",
            "- jewelry-qms 仍为建设中系统，不写入质量手册正文。",
            "",
        ]
    )


def render_role_cards(output_dir: Path, role_rows: list[dict[str, str]]) -> int:
    grouped: dict[str, list[dict[str, str]]] = defaultdict(list)
    for row in role_rows:
        grouped[row["role_group"]].append(row)
    role_dir = output_dir / "role_cards"
    role_dir.mkdir(parents=True, exist_ok=True)
    for role, rows in sorted(grouped.items()):
        slug = stable_slug(role)
        lines = [
            f"# {role} 学习实施一页卡",
            "",
            "文件状态：候选学习材料，不写数据库，不代表真实培训完成或人工批准。",
            "",
            "## 要完成的学习任务",
            "",
        ]
        lines.extend(
            render_table(
                rows,
                [
                    "learning_task_id",
                    "topic",
                    "source_object",
                    "required_before_effective",
                    "learning_materials",
                    "implementation_check",
                ],
            )
        )
        lines.extend(
            [
                "",
                "## 边界",
                "",
                "- 只用于学习和人工确认准备，不代表真实培训记录。",
                "- 发现问题先填 `04-问题反馈与修订回填模板.csv`，不得直接改受控文件或写库。",
                "- 资质状态按已取得 CMA、CNAS 申请中处理；不得写成已取得 CNAS。",
                "",
            ]
        )
        write_text(role_dir / f"{slug}.md", "\n".join(lines))
    return len(grouped)


def render_readme() -> str:
    return "\n".join(
        [
            "# 机构人员学习实施包",
            "",
            "本目录用于把候选体系文件和 LIMS 治理材料转成机构人员能执行的学习、理解确认和问题反馈工作面。",
            "",
            "## 阅读顺序",
            "",
            "1. `00-机构人员学习实施总览.md`",
            "2. `01-岗位学习任务矩阵.csv`",
            "3. `role_cards/` 中对应岗位的一页卡",
            "4. `02-学习材料入口清单.csv`",
            "5. `03-理解确认题库.csv`",
            "6. `04-问题反馈与修订回填模板.csv`",
            "7. `05-jewelry-qms试运行学习边界确认.md`",
            "8. `06-体系文件学习实施与理解确认记录候选模板.md`",
            "",
            "## 禁止事项",
            "",
            "- 不写数据库。",
            "- 不代表真实培训完成。",
            "- 不代表人工评审通过、文件批准或受控发布。",
            "- 不得把 jewelry-qms 写入质量手册正文作为已正式投用系统。",
            "- 不得把 CNAS 申请中写成已取得 CNAS。",
            "",
        ]
    )


def build_pack(stage_dir: Path, output_dir: Path) -> dict[str, Any]:
    release_dir = stage_dir / "controlled_release_rehearsal"
    training_rows = read_csv(release_dir / "03-培训宣贯演练清单.csv")
    field_catalog_files = file_map_by_code(stage_dir, "record_template_field_catalog/templates", "-字段字典.md")
    trial_files = file_map_by_code(stage_dir, "record_template_full_trial_pack", "-试填.md")
    role_rows = build_role_matrix(training_rows, field_catalog_files, trial_files)
    material_rows = build_material_index()
    question_rows = build_question_bank()
    feedback_rows = build_feedback_template()

    output_dir.mkdir(parents=True, exist_ok=True)
    role_card_count = render_role_cards(output_dir, role_rows)

    files = {
        "manifest": "staff_training_manifest.json",
        "overview": "00-机构人员学习实施总览.md",
        "role_matrix": "01-岗位学习任务矩阵.csv",
        "material_index": "02-学习材料入口清单.csv",
        "question_bank": "03-理解确认题库.csv",
        "feedback_template": "04-问题反馈与修订回填模板.csv",
        "lims_boundary": "05-jewelry-qms试运行学习边界确认.md",
        "training_record_template": "06-体系文件学习实施与理解确认记录候选模板.md",
        "readme": "README.md",
        "role_cards_dir": "role_cards",
    }
    manifest = {
        "generated_at": dt.datetime.now().isoformat(timespec="seconds"),
        "status": STATUS,
        "stage_dir": str(stage_dir),
        "output_dir": str(output_dir),
        "source_files": {
            "training_rehearsal": "controlled_release_rehearsal/03-培训宣贯演练清单.csv",
            "human_review_workbench": "human_review_workbench/00-人工评审总览.md",
            "field_catalog": "record_template_field_catalog/00-字段字典总览.md",
            "manual_revision_path": "manual_revision_path_pack/00-质量手册修订换版路径总览.md",
            "release_execution_template": "release_execution_template_pack/templates/JL-REL-03-体系文件培训宣贯与理解确认记录.md",
        },
        "files": files,
        "counts": {
            "training_source_items": len(training_rows),
            "role_learning_tasks": len(role_rows),
            "learning_materials": len(material_rows),
            "comprehension_questions": len(question_rows),
            "feedback_rows": len(feedback_rows),
            "role_cards": role_card_count,
            "required_before_effective_source_items": sum(1 for row in training_rows if row.get("required_before_effective") == "yes"),
            "database_write_performed": 0,
        },
        "guardrails": [
            "本包只用于机构人员学习、理解确认和问题反馈准备，不写数据库。",
            "本包不代表真实培训完成、人工评审通过、文件批准、受控发布或写库授权。",
            "本包不代表真实培训记录形成。",
            "本包不代表人工评审通过。",
            "所有学习任务和反馈模板保持 pending/空白，真实意见需进入人工评审回填预览。",
            "资质状态口径：已取得 CMA，CNAS 申请中；不得表述为已取得 CNAS。",
            "jewelry-qms 仍为建设中系统，只进入实施计划、试运行和治理准备材料，不写入质量手册正文。",
            "模拟学习和理解确认材料不得作为真实培训记录；如需试填，必须保留 SIMULATED_TRAINING_NOT_REAL_RECORD 标识。",
        ],
    }

    write_csv(
        output_dir / files["role_matrix"],
        role_rows,
        [
            "learning_task_id",
            "source_training_item_id",
            "role_group",
            "topic",
            "source_object",
            "trigger",
            "required_before_effective",
            "blocks_release_if_pending",
            "learning_materials",
            "implementation_check",
            "evidence_to_collect",
            "human_confirmation_status",
            "not_real_record",
            "not_real_record_marker",
        ],
    )
    write_csv(
        output_dir / files["material_index"],
        material_rows,
        ["material_id", "category", "title", "path", "primary_audience", "purpose", "must_read_before_effective"],
    )
    write_csv(
        output_dir / files["question_bank"],
        question_rows,
        [
            "question_id",
            "topic",
            "question",
            "expected_focus",
            "answer_type",
            "responsible_roles",
            "evidence_reference",
            "confirmation_status",
            "not_real_record",
        ],
    )
    write_csv(
        output_dir / files["feedback_template"],
        feedback_rows,
        [
            "feedback_id",
            "feedback_topic",
            "source_material",
            "issue_type",
            "responsible_role",
            "proposed_change",
            "human_decision",
            "review_comment",
            "linked_review_item",
            "blocking_if_unresolved",
            "not_real_record",
        ],
    )
    write_text(output_dir / files["overview"], render_overview(manifest))
    write_text(output_dir / files["lims_boundary"], render_lims_boundary())
    write_text(output_dir / files["training_record_template"], render_training_record_template())
    write_text(output_dir / files["readme"], render_readme())
    write_text(output_dir / files["manifest"], json.dumps(manifest, ensure_ascii=False, indent=2) + "\n")
    return manifest


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--stage-dir", default=str(DEFAULT_STAGE_DIR))
    parser.add_argument("--output-dir")
    args = parser.parse_args()

    stage_dir = Path(args.stage_dir)
    output_dir = Path(args.output_dir) if args.output_dir else stage_dir / "staff_training_implementation_pack"
    manifest = build_pack(stage_dir, output_dir)
    print(json.dumps({"status": manifest["status"], "output_dir": str(output_dir), "counts": manifest["counts"]}, ensure_ascii=False, indent=2))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
