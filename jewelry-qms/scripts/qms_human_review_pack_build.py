#!/usr/bin/env python3
"""Build a human-review pack before any QMS pre-import package can be applied."""

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

EXPECTED_CLAUSES = [
    "4.1",
    "4.2",
    "5",
    "6.1",
    "6.2",
    "6.3",
    "6.4",
    "6.5",
    "6.6",
    "7.1",
    "7.2",
    "7.3",
    "7.4",
    "7.5",
    "7.6",
    "7.7",
    "7.8",
    "7.9",
    "7.10",
    "7.11",
    "8.1",
    "8.2",
    "8.3",
    "8.4",
    "8.5",
    "8.6",
    "8.7",
    "8.8",
    "8.9",
]

REQUIRED_SCHEMA_KEYS = {
    "record_number",
    "record_name",
    "applicable_clause",
    "related_procedure",
    "responsible_position",
    "trigger_time",
    "correction_rule",
}

NO_DATABASE_WRITE_NOTE = "人工评审包，不写数据库，不代表受控批准。"


def read_text(path: Path) -> str:
    return path.read_text(encoding="utf-8")


def read_json(path: Path) -> dict[str, Any]:
    if not path.exists():
        return {"status": "missing", "path": str(path)}
    return json.loads(read_text(path))


def read_csv(path: Path) -> list[dict[str, str]]:
    with path.open("r", encoding="utf-8", newline="") as handle:
        return list(csv.DictReader(handle))


def write_csv(path: Path, rows: list[dict[str, Any]], fieldnames: list[str]) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    with path.open("w", encoding="utf-8", newline="") as handle:
        writer = csv.DictWriter(handle, fieldnames=fieldnames, extrasaction="ignore")
        writer.writeheader()
        for row in rows:
            writer.writerow({key: row.get(key, "") for key in fieldnames})


def split_refs(value: str) -> list[str]:
    return [item.strip() for item in re.split(r"[;；]", value or "") if item.strip()]


def role_for_clause(clause: str, topic: str) -> str:
    if clause.startswith("6.4") or clause.startswith("6.5"):
        return "设备管理员/技术负责人/质量负责人"
    if clause.startswith("7."):
        return "技术负责人/相关过程负责人/质量负责人"
    if clause.startswith("8."):
        return "质量负责人/文件管理员/最高管理者"
    if clause in {"4.1", "4.2", "5"}:
        return "最高管理者/质量负责人"
    return "质量负责人/相关过程负责人"


def evidence_from_report(path: Path, expected_status: str) -> str:
    report = read_json(path)
    status = report.get("status", "missing")
    findings = report.get("counts", {}).get("findings", "-")
    if status == expected_status:
        return f"{path.name}: {status}, findings={findings}"
    return f"{path.name}: {status}, expected={expected_status}, findings={findings}"


def build_clause_review_rows(trace_rows: list[dict[str, str]]) -> list[dict[str, Any]]:
    rows: list[dict[str, Any]] = []
    for row in trace_rows:
        clause = row.get("clause", "")
        topic = row.get("manual_topic", "")
        rows.append(
            {
                "review_item_id": f"CLAUSE-{clause}",
                "review_type": "manual_clause",
                "clause": clause,
                "manual_topic": topic,
                "procedure_doc_numbers": row.get("procedure_doc_numbers", ""),
                "attachment_form_doc_numbers": row.get("attachment_form_doc_numbers", ""),
                "record_template_numbers": row.get("record_template_numbers", ""),
                "lims_governance_point": row.get("lims_governance_point", ""),
                "verification_method": row.get("verification_method", ""),
                "reviewer_role": role_for_clause(clause, topic),
                "human_decision": "pending",
                "required_evidence": "核对候选手册正文、现行程序、记录模板、真实运行证据和岗位责任。",
                "blocking_if_unresolved": "yes",
                "review_note": NO_DATABASE_WRITE_NOTE,
            }
        )
    return rows


def build_record_template_review_rows(template_rows: list[dict[str, str]]) -> list[dict[str, Any]]:
    rows: list[dict[str, Any]] = []
    for row in template_rows:
        try:
            schema = json.loads(row.get("field_schema_json", "[]"))
        except json.JSONDecodeError:
            schema = []
        keys = {str(item.get("key", "")) for item in schema if isinstance(item, dict)}
        missing_common = sorted(REQUIRED_SCHEMA_KEYS - keys)
        rows.append(
            {
                "review_item_id": f"TEMPLATE-{row.get('doc_number', '')}",
                "review_type": "record_template",
                "doc_number": row.get("doc_number", ""),
                "name": row.get("name", ""),
                "applicable_clauses": row.get("applicable_clauses", ""),
                "procedure_doc_numbers": row.get("procedure_doc_numbers", ""),
                "attachment_form_doc_numbers": row.get("attachment_form_doc_numbers", ""),
                "responsible_position": row.get("responsible_position", ""),
                "trigger_time": row.get("trigger_time", ""),
                "field_count": len(schema),
                "missing_common_schema_keys": "；".join(missing_common),
                "needs_current_form_match": "yes",
                "needs_retention_period": "yes",
                "needs_confidentiality_level": "yes",
                "reviewer_role": "文件管理员/相关过程负责人/质量负责人",
                "human_decision": "pending",
                "blocking_if_unresolved": "yes",
                "review_note": "字段 schema 为候选；需与现用表单、试填结果、保存期限和签核规则核对。",
            }
        )
    return rows


def related_clauses_for_attachment(trace_rows: list[dict[str, str]], code: str) -> tuple[list[str], list[str]]:
    clauses: list[str] = []
    records: list[str] = []
    for row in trace_rows:
        if code in split_refs(row.get("attachment_form_doc_numbers", "")):
            clauses.append(row.get("clause", ""))
            records.extend(split_refs(row.get("record_template_numbers", "")))
    return sorted(set(clauses)), sorted(set(records))


def build_attachment_disposition_rows(
    document_rows: list[dict[str, str]], trace_rows: list[dict[str, str]]
) -> list[dict[str, Any]]:
    rows: list[dict[str, Any]] = []
    for row in document_rows:
        if row.get("action") != "reference_existing_attachment_form":
            continue
        code = row.get("doc_number", "")
        clauses, records = related_clauses_for_attachment(trace_rows, code)
        rows.append(
            {
                "review_item_id": f"ATTACHMENT-{code}",
                "review_type": "attachment_form_disposition",
                "doc_number": code,
                "title": row.get("title", ""),
                "source_stage_file": row.get("source_stage_file", ""),
                "related_clauses": "；".join(clauses),
                "related_record_templates": "；".join(records),
                "disposition_options": "程序附件；记录模板；历史附件保留；作废不导入；待补录为受控文件",
                "recommended_disposition": "pending_human_review",
                "human_decision": "pending",
                "reviewer_role": "文件管理员/设备管理员/技术负责人/质量负责人",
                "blocking_if_unresolved": "yes",
                "review_note": "该编号已从现行程序匹配中分流；正式写库前必须确认归属。",
            }
        )
    return rows


def build_preapply_gate_rows(stage_dir: Path) -> list[dict[str, Any]]:
    gate_specs = [
        (
            "GATE-01",
            "候选修订包门禁",
            "automated_evidence",
            "17-候选修订包验证报告.json",
            "passed",
            "候选手册条款、编号、资质状态、jewelry-qms 边界。",
        ),
        (
            "GATE-02",
            "LIMS 预导入包结构 dry-run",
            "automated_evidence",
            "18-LIMS预导入包dry-run验证报告.json",
            "passed",
            "CSV/JSON 结构、编号、候选状态和人工闸门。",
        ),
        (
            "GATE-03",
            "记录模板模拟试填",
            "automated_evidence",
            "20-记录模板试填dry-run验证报告.json",
            "passed",
            "3 个代表性模板可模拟试填，且不作为真实记录。",
        ),
        (
            "GATE-04",
            "记录模板全量模拟试填",
            "automated_evidence",
            "25-记录模板全量试填dry-run验证报告.json",
            "passed",
            "26 个候选记录模板均可形成模拟试填实例，且不作为真实记录。",
        ),
        (
            "GATE-05",
            "LIMS 命令层 dry-run",
            "automated_evidence",
            "21-LIMS预导入命令dry-run报告.json",
            "passed",
            "37 个现行程序匹配，记录模板仍为候选待创建。",
        ),
        (
            "GATE-06",
            "未确认 apply 阻断",
            "automated_evidence",
            "22-LIMS预导入apply闸门验证报告.json",
            "blocked",
            "未带人工确认时必须阻断写库。",
        ),
    ]
    rows: list[dict[str, Any]] = []
    for gate_id, name, gate_type, report_name, expected_status, scope in gate_specs:
        rows.append(
            {
                "gate_id": gate_id,
                "gate_name": name,
                "gate_type": gate_type,
                "current_evidence": evidence_from_report(stage_dir / report_name, expected_status),
                "human_decision": "pending",
                "required_before_apply": "yes",
                "reviewer_role": "Codex dry-run/文件管理员复核",
                "scope": scope,
                "blocking_if_unresolved": "yes",
                "review_note": "自动证据只能证明候选包可检查，不代表已获人工批准。",
            }
        )
    manual_gates = [
        ("GATE-07", "候选手册人工评审", "文件管理员/质量负责人/最高管理者", "核对组织事实、职责、正文口径和修订说明。"),
        ("GATE-08", "26 个记录模板字段评审", "文件管理员/相关过程负责人/质量负责人", "核实现用表单、字段、签核、保存期限和保密等级。"),
        ("GATE-09", "05-02 编号附件/表单归属", "文件管理员/设备管理员/技术负责人", "决定归入程序附件、记录模板、历史附件或不导入。"),
        ("GATE-10", "外来依据台账人工查新", "文件管理员/质量负责人", "复核来源、版本、查新周期和是否纳入受控外来文件。"),
        ("GATE-11", "用户明确授权 apply", "用户/最高管理者授权角色", "只有明确批准后才可运行 --apply --ack-human-reviewed。"),
    ]
    for gate_id, name, role, scope in manual_gates:
        rows.append(
            {
                "gate_id": gate_id,
                "gate_name": name,
                "gate_type": "human_review",
                "current_evidence": "pending_human_review",
                "human_decision": "pending",
                "required_before_apply": "yes",
                "reviewer_role": role,
                "scope": scope,
                "blocking_if_unresolved": "yes",
                "review_note": NO_DATABASE_WRITE_NOTE,
            }
        )
    return rows


def render_markdown_table(rows: list[dict[str, Any]], columns: list[str]) -> list[str]:
    lines = ["| " + " | ".join(columns) + " |", "|" + "|".join(["---"] * len(columns)) + "|"]
    for row in rows:
        values = [str(row.get(column, "")).replace("\n", " ") for column in columns]
        lines.append("| " + " | ".join(values) + " |")
    return lines


def render_readme(manifest: dict[str, Any]) -> str:
    counts = manifest["counts"]
    lines = [
        "# LIMS 人工评审与导入前决策包",
        "",
        f"生成时间：{manifest['generated_at']}",
        "文件状态：人工评审准备包，不写数据库，不代表受控批准。",
        "",
        "## 包内容",
        "",
        f"- 条款人工评审清单：{counts['manual_clause_review_items']} 条",
        f"- 记录模板人工评审清单：{counts['record_template_review_items']} 条",
        f"- 编号附件/表单归属判定：{counts['attachment_disposition_items']} 条",
        f"- apply 前决策闸门：{counts['preapply_gates']} 条",
        "",
        "## 使用边界",
        "",
        "1. 本包用于人工评审第五版候选稿和 LIMS 预导入包。",
        "2. 本包不是写库批准，不得替代文件控制程序中的审核、批准和发布。",
        "3. 所有 `human_decision` 初始均为 `pending`；未完成人工评审前不得运行 `--apply --ack-human-reviewed`。",
        "4. `XZTC/CX-05-02-2022` 已按编号附件/表单分流，仍需人工确认归属。",
        "",
        "## 文件清单",
        "",
    ]
    for label, filename in manifest["files"].items():
        lines.append(f"- `{filename}`：{label}")
    lines.append("")
    return "\n".join(lines)


def render_review_guide(
    clause_rows: list[dict[str, Any]],
    template_rows: list[dict[str, Any]],
    attachment_rows: list[dict[str, Any]],
    gate_rows: list[dict[str, Any]],
) -> str:
    lines = [
        "# 人工评审操作说明",
        "",
        "文件状态：评审准备说明，不写数据库，不代表受控批准。",
        "",
        "## 评审顺序",
        "",
        "1. 先看候选手册与修订说明，确认 CMA/CNAS 口径、2022 清单口径、jewelry-qms 建设中边界。",
        "2. 再按 29 个条款逐项核对程序、记录、LIMS 治理点和真实运行证据。",
        "3. 再核对 26 个记录模板字段，并结合 `record_template_full_trial_pack/` 中 26 份全量模拟试填表检查字段可填性，特别是保存期限、保密等级、签核和更正规则。",
        "4. 单独确认 `XZTC/CX-05-02-2022` 归入程序附件还是记录模板。",
        "5. 只有所有 apply 前闸门通过且用户明确授权，才允许进入写库命令。",
        "",
        "## apply 前闸门摘要",
        "",
    ]
    lines.extend(render_markdown_table(gate_rows, ["gate_id", "gate_name", "gate_type", "current_evidence", "human_decision"]))
    lines.extend(["", "## 05-02 归属判定", ""])
    lines.extend(
        render_markdown_table(
            attachment_rows,
            ["doc_number", "title", "related_clauses", "related_record_templates", "recommended_disposition", "human_decision"],
        )
    )
    lines.extend(["", "## 抽样查看：条款评审前 5 项", ""])
    lines.extend(
        render_markdown_table(
            clause_rows[:5],
            ["clause", "manual_topic", "procedure_doc_numbers", "record_template_numbers", "reviewer_role", "human_decision"],
        )
    )
    lines.extend(["", "## 抽样查看：记录模板评审前 5 项", ""])
    lines.extend(
        render_markdown_table(
            template_rows[:5],
            ["doc_number", "name", "field_count", "needs_retention_period", "needs_confidentiality_level", "human_decision"],
        )
    )
    lines.append("")
    return "\n".join(lines)


def build_human_review_pack(stage_dir: Path, output_dir: Path) -> dict[str, Any]:
    preimport_dir = stage_dir / "lims_preimport_package"
    trace_rows = read_csv(preimport_dir / "traceability_matrix_preimport.csv")
    template_rows = read_csv(preimport_dir / "record_form_templates_preimport.csv")
    document_rows = read_csv(preimport_dir / "documents_preimport.csv")

    clause_review_rows = build_clause_review_rows(trace_rows)
    record_template_review_rows = build_record_template_review_rows(template_rows)
    attachment_disposition_rows = build_attachment_disposition_rows(document_rows, trace_rows)
    preapply_gate_rows = build_preapply_gate_rows(stage_dir)

    output_dir.mkdir(parents=True, exist_ok=True)
    files = {
        "manual_clause_review": "manual_clause_review_checklist.csv",
        "record_template_review": "record_template_review_checklist.csv",
        "attachment_disposition": "attachment_form_disposition.csv",
        "preapply_gate_register": "preapply_gate_register.csv",
        "review_guide": "人工评审操作说明.md",
        "manifest": "human_review_manifest.json",
    }
    write_csv(
        output_dir / files["manual_clause_review"],
        clause_review_rows,
        [
            "review_item_id",
            "review_type",
            "clause",
            "manual_topic",
            "procedure_doc_numbers",
            "attachment_form_doc_numbers",
            "record_template_numbers",
            "lims_governance_point",
            "verification_method",
            "reviewer_role",
            "human_decision",
            "required_evidence",
            "blocking_if_unresolved",
            "review_note",
        ],
    )
    write_csv(
        output_dir / files["record_template_review"],
        record_template_review_rows,
        [
            "review_item_id",
            "review_type",
            "doc_number",
            "name",
            "applicable_clauses",
            "procedure_doc_numbers",
            "attachment_form_doc_numbers",
            "responsible_position",
            "trigger_time",
            "field_count",
            "missing_common_schema_keys",
            "needs_current_form_match",
            "needs_retention_period",
            "needs_confidentiality_level",
            "reviewer_role",
            "human_decision",
            "blocking_if_unresolved",
            "review_note",
        ],
    )
    write_csv(
        output_dir / files["attachment_disposition"],
        attachment_disposition_rows,
        [
            "review_item_id",
            "review_type",
            "doc_number",
            "title",
            "source_stage_file",
            "related_clauses",
            "related_record_templates",
            "disposition_options",
            "recommended_disposition",
            "human_decision",
            "reviewer_role",
            "blocking_if_unresolved",
            "review_note",
        ],
    )
    write_csv(
        output_dir / files["preapply_gate_register"],
        preapply_gate_rows,
        [
            "gate_id",
            "gate_name",
            "gate_type",
            "current_evidence",
            "human_decision",
            "required_before_apply",
            "reviewer_role",
            "scope",
            "blocking_if_unresolved",
            "review_note",
        ],
    )

    generated_at = dt.datetime.now().isoformat(timespec="seconds")
    manifest = {
        "generated_at": generated_at,
        "stage_dir": str(stage_dir),
        "preimport_dir": str(preimport_dir),
        "review_pack_dir": str(output_dir),
        "status": "human_review_required_no_database_write",
        "boundary": [
            "本包仅用于人工评审和导入前决策，不写数据库。",
            "本包不代表第五版候选稿已经审核、批准或发布。",
            "所有 human_decision 初始为 pending；未人工确认前不得 apply。",
        ],
        "counts": {
            "manual_clause_review_items": len(clause_review_rows),
            "record_template_review_items": len(record_template_review_rows),
            "attachment_disposition_items": len(attachment_disposition_rows),
            "preapply_gates": len(preapply_gate_rows),
        },
        "expected_clauses": EXPECTED_CLAUSES,
        "files": files,
    }
    (output_dir / files["manifest"]).write_text(
        json.dumps(manifest, ensure_ascii=False, indent=2) + "\n",
        encoding="utf-8",
    )
    (output_dir / "README.md").write_text(render_readme(manifest), encoding="utf-8")
    (output_dir / files["review_guide"]).write_text(
        render_review_guide(clause_review_rows, record_template_review_rows, attachment_disposition_rows, preapply_gate_rows),
        encoding="utf-8",
    )
    return manifest


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--stage-dir", default=str(DEFAULT_STAGE_DIR))
    parser.add_argument("--output-dir")
    args = parser.parse_args()

    stage_dir = Path(args.stage_dir)
    output_dir = Path(args.output_dir) if args.output_dir else stage_dir / "human_review_pack"
    manifest = build_human_review_pack(stage_dir, output_dir)
    print(json.dumps(manifest, ensure_ascii=False, indent=2))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
