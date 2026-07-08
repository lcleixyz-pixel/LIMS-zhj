#!/usr/bin/env python3
"""Build a no-write manual revision path pack for the XZTC/SC quality manual."""

from __future__ import annotations

import argparse
import csv
import datetime as dt
import json
from pathlib import Path
from typing import Any


DEFAULT_STAGE_DIR = Path(
    "/Users/lc.leixyz/Documents/AI工作台/01-项目代码/LIMS-zhj/.team/交接箱/2026-07-07-第五版候选修订准备"
)

GUARDRAILS = [
    "本包只用于确认 XZTC/SC 质量手册第五版候选稿的修订/换版路径，不写数据库。",
    "本包不代表人工评审通过、文件批准、受控发布或写库授权。",
    "XZTC/SC 已存在同编号 published 受控文件，候选稿不得按同编号新增草稿直接写入。",
    "真实修订应走既有文件修订/换版治理路径，并保留旧版、修订原因、审批和发布证据。",
    "资质状态口径：已取得 CMA，CNAS 申请中；不得表述为已取得 CNAS。",
    "jewelry-qms 仍为建设中系统，只进入实施计划、演练和治理准备材料，不写入质量手册正文。",
]

EXISTING_FIELDS = [
    "doc_number",
    "candidate_title",
    "candidate_version",
    "preview_action",
    "existing_match",
    "existing_status",
    "candidate_status",
    "candidate_publish",
    "source_stage_file",
    "change_reason",
    "import_mode",
    "revision_route_decision",
    "lims_current_control_path",
    "no_write_marker",
]

CHECKLIST_FIELDS = [
    "gate_id",
    "topic",
    "iso_17025_clause",
    "owner_role",
    "required_decision",
    "current_status",
    "blocking_if_unresolved",
    "evidence_or_source",
]

ACTION_FIELDS = [
    "action_id",
    "target_table_or_module",
    "possible_lims_path",
    "source_evidence",
    "action_type",
    "allowed_now",
    "write_now",
    "blocked_by",
    "expected_record_effect_after_authorized_revision",
]

HUMAN_GATE_FIELDS = [
    "decision_id",
    "decision_topic",
    "responsible_role",
    "decision_status",
    "allowed_values",
    "blocking_if_unresolved",
    "review_material",
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


def find_row(rows: list[dict[str, str]], doc_number: str) -> dict[str, str]:
    for row in rows:
        if row.get("doc_number") == doc_number:
            return row
    return {}


def build_existing_row(preimport_row: dict[str, str], preview_row: dict[str, str]) -> dict[str, Any]:
    return {
        "doc_number": "XZTC/SC",
        "candidate_title": preimport_row.get("title") or preview_row.get("title", ""),
        "candidate_version": preimport_row.get("version") or preview_row.get("version", ""),
        "preview_action": preview_row.get("preview_action", ""),
        "existing_match": preview_row.get("existing_match", ""),
        "existing_status": preview_row.get("existing_status", ""),
        "candidate_status": preimport_row.get("status") or preview_row.get("status", ""),
        "candidate_publish": preimport_row.get("publish") or preview_row.get("publish", ""),
        "source_stage_file": preimport_row.get("source_stage_file") or preview_row.get("source_stage_file", ""),
        "change_reason": preimport_row.get("change_reason") or preview_row.get("change_reason", ""),
        "import_mode": preimport_row.get("import_mode") or preview_row.get("import_mode", ""),
        "revision_route_decision": "existing_document_revision_required",
        "lims_current_control_path": "/document/revise?id=<existing_documents.id>",
        "no_write_marker": "NO_DATABASE_WRITE_REHEARSAL_ONLY",
    }


def build_checklist_rows() -> list[dict[str, Any]]:
    return [
        {
            "gate_id": "MR-01",
            "topic": "确认 XZTC/SC 既有 published 受控文件存在",
            "iso_17025_clause": "8.3",
            "owner_role": "文件管理员",
            "required_decision": "使用既有文件修订/换版路径，不新增同编号草稿",
            "current_status": "passed_by_write_preview",
            "blocking_if_unresolved": "yes",
            "evidence_or_source": "lims_write_preview_package/01-documents-draft-preview.csv",
        },
        {
            "gate_id": "MR-02",
            "topic": "确认第五版候选手册正文和修订说明",
            "iso_17025_clause": "8.2,8.3",
            "owner_role": "质量负责人/最高管理者",
            "required_decision": "批准、退回修订、暂缓或拒绝",
            "current_status": "pending_human_review",
            "blocking_if_unresolved": "yes",
            "evidence_or_source": "10-质量手册第五版候选稿.md; 11-第四版到第五版候选修订说明.md",
        },
        {
            "gate_id": "MR-03",
            "topic": "确认版本标识、生效日期和修订编号策略",
            "iso_17025_clause": "8.3",
            "owner_role": "文件管理员/质量负责人",
            "required_decision": "确认第五版、A/0 或组织现行版本规则的映射",
            "current_status": "pending_human_review",
            "blocking_if_unresolved": "yes",
            "evidence_or_source": "现用文件控制程序; LIMS documents.version/revision 字段",
        },
        {
            "gate_id": "MR-04",
            "topic": "确认审核批准岗位和签核证据",
            "iso_17025_clause": "8.3",
            "owner_role": "最高管理者/质量负责人",
            "required_decision": "确认编制、审核、批准、发布日期和生效日期",
            "current_status": "pending_human_review",
            "blocking_if_unresolved": "yes",
            "evidence_or_source": "human_review_pack; controlled_release_rehearsal",
        },
        {
            "gate_id": "MR-05",
            "topic": "确认第四版作废回收、留存和防误用措施",
            "iso_17025_clause": "8.3,8.4",
            "owner_role": "文件管理员",
            "required_decision": "确认旧版状态识别、作废回收、保留期限和可追溯记录",
            "current_status": "pending_human_review",
            "blocking_if_unresolved": "yes",
            "evidence_or_source": "controlled_release_rehearsal/04-旧版处置演练清单.csv",
        },
        {
            "gate_id": "MR-06",
            "topic": "确认宣贯培训和人员可获得适用文件",
            "iso_17025_clause": "8.2.5,8.3",
            "owner_role": "质量负责人/文件管理员",
            "required_decision": "确认培训对象、培训证据和生效前完成条件",
            "current_status": "pending_human_review",
            "blocking_if_unresolved": "yes",
            "evidence_or_source": "controlled_release_rehearsal/03-培训宣贯演练清单.csv",
        },
        {
            "gate_id": "MR-07",
            "topic": "确认 LIMS 修订动作和电子记录边界",
            "iso_17025_clause": "7.11,8.3,8.4",
            "owner_role": "系统管理员/文件管理员/质量负责人",
            "required_decision": "确认是否通过 /document/revise?id=<existing_documents.id> 发起修订",
            "current_status": "pending_human_review",
            "blocking_if_unresolved": "yes",
            "evidence_or_source": "jewelry-qms/app/controller/Document.php revise; document_revisions table",
        },
        {
            "gate_id": "MR-08",
            "topic": "确认结构化文件和条款追溯关系影响",
            "iso_17025_clause": "7.11,8.3",
            "owner_role": "质量负责人/文件管理员/系统管理员",
            "required_decision": "确认修订后是否刷新结构化块、追溯关系和人工复核任务",
            "current_status": "pending_human_review",
            "blocking_if_unresolved": "yes",
            "evidence_or_source": "qms_structured_documents; qms_document_blocks; qms_document_block_links",
        },
        {
            "gate_id": "MR-09",
            "topic": "确认本包不写库、不发布、不替代人工批准",
            "iso_17025_clause": "7.11,8.3,8.4",
            "owner_role": "Codex/文件管理员",
            "required_decision": "保留预演边界",
            "current_status": "passed_guardrail",
            "blocking_if_unresolved": "yes",
            "evidence_or_source": "manual_revision_path_manifest.json",
        },
    ]


def build_action_rows() -> list[dict[str, Any]]:
    return [
        {
            "action_id": "ACT-01",
            "target_table_or_module": "documents",
            "possible_lims_path": "/document/revise?id=<existing_documents.id>",
            "source_evidence": "jewelry-qms/app/controller/Document.php::revise",
            "action_type": "update_existing_document_after_authorization",
            "allowed_now": "no",
            "write_now": "no",
            "blocked_by": "human_review_pending; revision_path_pending; user_authorization_required",
            "expected_record_effect_after_authorized_revision": "existing XZTC/SC set to draft with new version/revision and change_reason",
        },
        {
            "action_id": "ACT-02",
            "target_table_or_module": "document_revisions",
            "possible_lims_path": "DocumentRevision::create in revise transaction",
            "source_evidence": "database/jewelry_qms.sql; app/controller/Document.php::revise",
            "action_type": "archive_previous_version_snapshot",
            "allowed_now": "no",
            "write_now": "no",
            "blocked_by": "human_review_pending; user_authorization_required",
            "expected_record_effect_after_authorized_revision": "previous version, revision, file_path, file_name and change_reason preserved",
        },
        {
            "action_id": "ACT-03",
            "target_table_or_module": "qms_structured_documents",
            "possible_lims_path": "QmsDocumentStructureService::refreshControlledDocumentStructure",
            "source_evidence": "app/controller/Document.php::revise",
            "action_type": "refresh_structured_document_as_draft",
            "allowed_now": "no",
            "write_now": "no",
            "blocked_by": "human_review_pending; manual_revision_authorization_required",
            "expected_record_effect_after_authorized_revision": "structured quality manual draft refreshed for later block and trace review",
        },
        {
            "action_id": "ACT-04",
            "target_table_or_module": "qms_document_blocks/qms_document_block_links",
            "possible_lims_path": "stage2 after manual revision approval",
            "source_evidence": "28-LIMS结构化块与追溯关系stage2-dry-run报告.md",
            "action_type": "deferred_traceability_refresh",
            "allowed_now": "no",
            "write_now": "no",
            "blocked_by": "human_review_pending; stage2_manual_review_required",
            "expected_record_effect_after_authorized_revision": "manual blocks and trace links reviewed before controlled use",
        },
        {
            "action_id": "ACT-05",
            "target_table_or_module": "document_distributions/document_reviews/approval evidence",
            "possible_lims_path": "controlled release workflow after approval",
            "source_evidence": "controlled_release_rehearsal; release_execution_template_pack",
            "action_type": "controlled_release_evidence_after_approval",
            "allowed_now": "no",
            "write_now": "no",
            "blocked_by": "controlled_release_not_approved",
            "expected_record_effect_after_authorized_revision": "approval, distribution, training and old-version disposition evidence retained",
        },
    ]


def build_human_gate_rows() -> list[dict[str, Any]]:
    return [
        {
            "decision_id": "MRD-01",
            "decision_topic": "第五版候选手册是否可进入修订/换版流程",
            "responsible_role": "最高管理者/质量负责人",
            "decision_status": "pending",
            "allowed_values": "approved/pass/accepted/通过/批准; needs_revision; rejected; deferred",
            "blocking_if_unresolved": "yes",
            "review_material": "10-质量手册第五版候选稿.md; 11-第四版到第五版候选修订说明.md",
        },
        {
            "decision_id": "MRD-02",
            "decision_topic": "版本标识与生效日期策略",
            "responsible_role": "文件管理员/质量负责人",
            "decision_status": "pending",
            "allowed_values": "approved; needs_revision; deferred",
            "blocking_if_unresolved": "yes",
            "review_material": "documents.version; documents.revision; 文件控制程序",
        },
        {
            "decision_id": "MRD-03",
            "decision_topic": "第四版作废回收和留存策略",
            "responsible_role": "文件管理员",
            "decision_status": "pending",
            "allowed_values": "approved; needs_revision; deferred",
            "blocking_if_unresolved": "yes",
            "review_material": "controlled_release_rehearsal/04-旧版处置演练清单.csv",
        },
        {
            "decision_id": "MRD-04",
            "decision_topic": "LIMS 修订入口和结构化同步动作",
            "responsible_role": "系统管理员/文件管理员",
            "decision_status": "pending",
            "allowed_values": "approved; needs_revision; deferred",
            "blocking_if_unresolved": "yes",
            "review_material": "03-LIMS修订动作预览.csv",
        },
        {
            "decision_id": "MRD-05",
            "decision_topic": "受控发布、培训宣贯和实施有效性检查",
            "responsible_role": "质量负责人/文件管理员",
            "decision_status": "pending",
            "allowed_values": "approved; needs_revision; deferred",
            "blocking_if_unresolved": "yes",
            "review_material": "controlled_release_rehearsal; release_execution_template_pack",
        },
    ]


def render_overview(existing_rows: list[dict[str, Any]], checklist_rows: list[dict[str, Any]], action_rows: list[dict[str, Any]]) -> str:
    lines = [
        "# 质量手册修订/换版路径总览",
        "",
        "文件状态：候选治理准备材料，不写数据库，不代表批准发布。",
        "",
        "## 当前判断",
        "",
        "`XZTC/SC` 第五版候选手册不能按同编号新增草稿直接写入 LIMS。行级写库预览显示 LIMS 中已存在同编号 published 受控文件，因此应先确认既有文件修订/换版路径，再讨论真实 apply。",
        "",
        "## 既有记录核对",
        "",
        *render_table(existing_rows, ["doc_number", "candidate_title", "candidate_version", "preview_action", "existing_match", "existing_status", "revision_route_decision"]),
        "",
        "## 修订路径闸门",
        "",
        *render_table(checklist_rows, ["gate_id", "topic", "iso_17025_clause", "owner_role", "current_status", "blocking_if_unresolved"]),
        "",
        "## LIMS 动作预览",
        "",
        *render_table(action_rows, ["action_id", "target_table_or_module", "possible_lims_path", "action_type", "allowed_now", "write_now"]),
        "",
        "## 边界",
        "",
        *["- " + item for item in GUARDRAILS],
        "",
    ]
    return "\n".join(lines)


def render_lims_notes(action_rows: list[dict[str, Any]]) -> str:
    lines = [
        "# LIMS 修订动作说明",
        "",
        "文件状态：候选治理准备材料；不写数据库，不代表人工评审通过、受控发布或写库授权。",
        "",
        "本说明只用于人工评审和后续开发/执行确认，不触发任何写库。`XZTC/SC` 第五版候选稿不得按同编号新增草稿直接写入，后续应确认既有文件修订/换版路径。资质状态仍为已取得 CMA，CNAS 申请中。",
        "",
        "## 可用系统路径",
        "",
        "- `documents` 表已有 `version`、`revision`、`status`、`change_reason`、`publish` 字段，可承接既有文件修订状态。",
        "- `document_revisions` 表可保存修订前版本、修订号、文件路径、文件名和变更原因。",
        "- `Document::revise` 当前会在事务中创建 `document_revisions` 快照，再把既有文件更新为 draft 修订版本。",
        "- 修订后可调用 `QmsDocumentStructureService::refreshControlledDocumentStructure` 刷新结构化文件草稿，但后续块级追溯仍应人工复核。",
        "",
        "## 行级动作",
        "",
        *render_table(action_rows, ["action_id", "target_table_or_module", "action_type", "blocked_by", "expected_record_effect_after_authorized_revision"]),
        "",
        "## 不允许事项",
        "",
        "- 不允许把 `XZTC/SC` 第五版候选稿作为同编号新增草稿或同编号新增 `documents` draft 行直接写入。",
        "- 不允许在真实人工评审和用户授权前更新既有 `documents` 记录。",
        "- 不允许把本包中的路径预览当成受控发布、培训完成或旧版作废证据。",
        "",
    ]
    return "\n".join(lines)


def render_readme() -> str:
    lines = [
        "# 质量手册修订/换版路径包",
        "",
        "文件状态：候选治理准备材料；不写数据库、不代表人工评审通过、不代表受控发布。",
        "",
        "## 阅读顺序",
        "",
        "1. `00-质量手册修订换版路径总览.md`：看当前判断和总闸门。",
        "2. `01-既有质量手册记录核对.csv`：确认 `XZTC/SC` 不是新增草稿路线。",
        "3. `02-修订换版路径闸门清单.csv`：逐项确认文件控制、旧版处置、培训和 LIMS 动作。",
        "4. `03-LIMS修订动作预览.csv`：看 LIMS 可能涉及的表和模块。",
        "5. `04-人工决策闸门.csv`：供人工评审意见回填前使用。",
        "6. `05-LIMS修订动作说明.md`：给后续执行或开发人员看。",
        "",
        "## 关键边界",
        "",
        *["- " + item for item in GUARDRAILS],
        "",
    ]
    return "\n".join(lines)


def build_pack(stage_dir: Path, output_dir: Path) -> dict[str, Any]:
    preimport_dir = stage_dir / "lims_preimport_package"
    preview_dir = stage_dir / "lims_write_preview_package"
    preimport_rows = read_csv(preimport_dir / "documents_preimport.csv")
    preview_rows = read_csv(preview_dir / "01-documents-draft-preview.csv")
    preimport_row = find_row(preimport_rows, "XZTC/SC")
    preview_row = find_row(preview_rows, "XZTC/SC")

    output_dir.mkdir(parents=True, exist_ok=True)

    existing_rows = [build_existing_row(preimport_row, preview_row)]
    checklist_rows = build_checklist_rows()
    action_rows = build_action_rows()
    human_gate_rows = build_human_gate_rows()

    files = {
        "manifest": "manual_revision_path_manifest.json",
        "overview": "00-质量手册修订换版路径总览.md",
        "existing_manual": "01-既有质量手册记录核对.csv",
        "revision_checklist": "02-修订换版路径闸门清单.csv",
        "lims_action_preview": "03-LIMS修订动作预览.csv",
        "human_decision_gates": "04-人工决策闸门.csv",
        "lims_action_notes": "05-LIMS修订动作说明.md",
        "readme": "README.md",
    }

    write_csv(output_dir / files["existing_manual"], existing_rows, EXISTING_FIELDS)
    write_csv(output_dir / files["revision_checklist"], checklist_rows, CHECKLIST_FIELDS)
    write_csv(output_dir / files["lims_action_preview"], action_rows, ACTION_FIELDS)
    write_csv(output_dir / files["human_decision_gates"], human_gate_rows, HUMAN_GATE_FIELDS)
    (output_dir / files["overview"]).write_text(render_overview(existing_rows, checklist_rows, action_rows), encoding="utf-8")
    (output_dir / files["lims_action_notes"]).write_text(render_lims_notes(action_rows), encoding="utf-8")
    (output_dir / files["readme"]).write_text(render_readme(), encoding="utf-8")

    manifest = {
        "generated_at": dt.datetime.now().isoformat(timespec="seconds"),
        "status": "manual_revision_path_no_database_write",
        "stage_dir": str(stage_dir),
        "preimport_dir": str(preimport_dir),
        "write_preview_dir": str(preview_dir),
        "output_dir": str(output_dir),
        "target_doc_number": "XZTC/SC",
        "guardrails": GUARDRAILS,
        "files": files,
        "counts": {
            "existing_manual_rows": len(existing_rows),
            "revision_gates": len(checklist_rows),
            "lims_action_preview_rows": len(action_rows),
            "human_decision_gates": len(human_gate_rows),
            "pending_human_decisions": sum(1 for row in human_gate_rows if row["decision_status"] == "pending"),
            "database_write_performed": 0,
        },
    }
    (output_dir / files["manifest"]).write_text(json.dumps(manifest, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
    return manifest


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--stage-dir", default=str(DEFAULT_STAGE_DIR))
    parser.add_argument("--output-dir")
    args = parser.parse_args()

    stage_dir = Path(args.stage_dir)
    output_dir = Path(args.output_dir) if args.output_dir else stage_dir / "manual_revision_path_pack"
    manifest = build_pack(stage_dir, output_dir)
    print(json.dumps(manifest, ensure_ascii=False, indent=2))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
