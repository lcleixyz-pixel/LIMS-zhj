#!/usr/bin/env python3
"""Dry-run validation for the staff-facing QMS learning and implementation pack."""

from __future__ import annotations

import argparse
import csv
import datetime as dt
import json
import re
from pathlib import Path
from typing import Any


STATUS = "staff_training_implementation_no_database_write"

REQUIRED_FILES = {
    "manifest": "staff_training_manifest.json",
    "overview": "00-机构人员学习实施总览.md",
    "role_matrix": "01-岗位学习任务矩阵.csv",
    "material_index": "02-学习材料入口清单.csv",
    "question_bank": "03-理解确认题库.csv",
    "feedback_template": "04-问题反馈与修订回填模板.csv",
    "lims_boundary": "05-jewelry-qms试运行学习边界确认.md",
    "training_record_template": "06-体系文件学习实施与理解确认记录候选模板.md",
    "readme": "README.md",
}

REQUIRED_GUARDRAILS = [
    "不写数据库",
    "不代表真实培训完成",
    "不代表真实培训记录",
    "不代表人工评审通过",
    "已取得 CMA",
    "CNAS 申请中",
    "jewelry-qms 仍为建设中系统",
    "不写入质量手册正文",
]


def read_text(path: Path) -> str:
    return path.read_text(encoding="utf-8")


def read_csv(path: Path) -> list[dict[str, str]]:
    with path.open("r", encoding="utf-8", newline="") as handle:
        return list(csv.DictReader(handle))


def fail(findings: list[dict[str, str]], rule_id: str, message: str, severity: str = "high") -> None:
    findings.append({"id": rule_id, "severity": severity, "message": message})


def forbidden_database_artifacts(pack_dir: Path) -> list[Path]:
    if not pack_dir.exists():
        return []
    return [path for path in pack_dir.rglob("*") if path.is_file() and path.suffix.lower() in {".sql", ".db"}]


def split_materials(value: str) -> list[str]:
    return [part.strip() for part in re.split(r"[；;]", value or "") if part.strip()]


def check_doc_guardrails(pack_dir: Path, filenames: list[str], findings: list[dict[str, str]]) -> None:
    for filename in filenames:
        path = pack_dir / filename
        if not path.exists() or path.suffix.lower() != ".md":
            continue
        text = read_text(path)
        for marker in ["不写数据库", "不代表"]:
            if marker not in text:
                fail(findings, "doc_missing_guardrail", f"{filename} 缺少边界标识：{marker}")
        if re.search(r"已批准发布|可以写库|准许写库|真实培训已完成|正式培训记录|(本公司|公司|实验室).{0,12}(已取得|已获|获得)\s*CNAS|CNAS\s*认可证书", text):
            fail(findings, "doc_overstates_status", f"{filename} 疑似包含越权状态表述。")


def check_pack(pack_dir: Path) -> dict[str, Any]:
    findings: list[dict[str, str]] = []
    manifest_path = pack_dir / REQUIRED_FILES["manifest"]
    if not manifest_path.exists():
        return {
            "generated_at": dt.datetime.now().isoformat(timespec="seconds"),
            "pack_dir": str(pack_dir),
            "status": "failed",
            "findings": [{"id": "missing_manifest", "severity": "high", "message": "缺少 staff_training_manifest.json"}],
        }

    manifest = json.loads(read_text(manifest_path))
    if manifest.get("status") != STATUS:
        fail(findings, "invalid_manifest_status", f"manifest 状态必须为 {STATUS}。")
    files = manifest.get("files", {})
    for key, filename in REQUIRED_FILES.items():
        actual = files.get(key, filename)
        if not (pack_dir / actual).exists():
            fail(findings, "missing_" + key, f"缺少人员学习实施包文件：{actual}")

    role_cards_dir = pack_dir / files.get("role_cards_dir", "role_cards")
    role_card_files = list(role_cards_dir.glob("*.md")) if role_cards_dir.exists() else []
    if not role_card_files:
        fail(findings, "missing_role_cards", "缺少岗位一页卡 role_cards/*.md。")

    guardrail_text = "\n".join(str(item) for item in manifest.get("guardrails", []))
    for marker in REQUIRED_GUARDRAILS:
        if marker not in guardrail_text:
            fail(findings, "manifest_missing_guardrail", f"manifest guardrails 缺少标识：{marker}")

    for path in forbidden_database_artifacts(pack_dir):
        fail(findings, "forbidden_database_artifact", f"人员学习实施包不应包含数据库/SQL 文件：{path.relative_to(pack_dir)}")

    stage_dir = Path(str(manifest.get("stage_dir", "")))
    role_rows = read_csv(pack_dir / files.get("role_matrix", REQUIRED_FILES["role_matrix"])) if (pack_dir / files.get("role_matrix", REQUIRED_FILES["role_matrix"])).exists() else []
    material_rows = read_csv(pack_dir / files.get("material_index", REQUIRED_FILES["material_index"])) if (pack_dir / files.get("material_index", REQUIRED_FILES["material_index"])).exists() else []
    question_rows = read_csv(pack_dir / files.get("question_bank", REQUIRED_FILES["question_bank"])) if (pack_dir / files.get("question_bank", REQUIRED_FILES["question_bank"])).exists() else []
    feedback_rows = read_csv(pack_dir / files.get("feedback_template", REQUIRED_FILES["feedback_template"])) if (pack_dir / files.get("feedback_template", REQUIRED_FILES["feedback_template"])).exists() else []

    source_item_ids = {row.get("source_training_item_id", "") for row in role_rows if row.get("source_training_item_id")}
    for index, row in enumerate(role_rows, start=2):
        for field in ["learning_task_id", "source_training_item_id", "role_group", "topic", "learning_materials", "implementation_check"]:
            if not row.get(field):
                fail(findings, "role_matrix_blank_field", f"岗位学习任务矩阵第 {index} 行缺少 {field}。")
        if row.get("human_confirmation_status") != "pending":
            fail(findings, "role_matrix_status_not_pending", f"第 {index} 行 human_confirmation_status 必须为 pending。")
        if row.get("not_real_record") != "yes":
            fail(findings, "role_matrix_not_real_invalid", f"第 {index} 行 not_real_record 必须为 yes。")
        if row.get("required_before_effective") not in {"yes", "no"}:
            fail(findings, "role_matrix_required_flag_invalid", f"第 {index} 行 required_before_effective 必须为 yes/no。")
        if row.get("required_before_effective") == "yes" and row.get("blocks_release_if_pending") != "yes":
            fail(findings, "role_matrix_required_not_blocking", f"第 {index} 行生效前必需任务必须标记 blocks_release_if_pending=yes。")
        for material in split_materials(row.get("learning_materials", "")):
            if material.startswith("http"):
                continue
            if stage_dir and not (stage_dir / material).exists():
                fail(findings, "role_matrix_material_missing", f"第 {index} 行引用材料不存在：{material}", "medium")

    for index, row in enumerate(material_rows, start=2):
        for field in ["material_id", "category", "title", "path", "primary_audience", "purpose"]:
            if not row.get(field):
                fail(findings, "material_index_blank_field", f"学习材料入口第 {index} 行缺少 {field}。")
        path = row.get("path", "")
        if path and not (stage_dir / path).exists():
            fail(findings, "material_index_path_missing", f"学习材料入口第 {index} 行文件不存在：{path}")
        if row.get("must_read_before_effective") not in {"yes", "no"}:
            fail(findings, "material_index_required_flag_invalid", f"学习材料入口第 {index} 行 must_read_before_effective 必须为 yes/no。")

    if len(question_rows) < 12:
        fail(findings, "question_bank_too_small", f"理解确认题库至少应有 12 题，实际 {len(question_rows)}。")
    for index, row in enumerate(question_rows, start=2):
        if row.get("confirmation_status") != "pending":
            fail(findings, "question_status_not_pending", f"理解确认题第 {index} 行 confirmation_status 必须为 pending。")
        if row.get("not_real_record") != "yes":
            fail(findings, "question_not_real_invalid", f"理解确认题第 {index} 行 not_real_record 必须为 yes。")

    for index, row in enumerate(feedback_rows, start=2):
        if row.get("human_decision") or row.get("review_comment") or row.get("proposed_change"):
            fail(findings, "feedback_template_not_blank", f"反馈模板第 {index} 行拟回填字段应保持空白。")
        if row.get("blocking_if_unresolved") != "yes":
            fail(findings, "feedback_not_blocking", f"反馈模板第 {index} 行 blocking_if_unresolved 必须为 yes。")
        if row.get("not_real_record") != "yes":
            fail(findings, "feedback_not_real_invalid", f"反馈模板第 {index} 行 not_real_record 必须为 yes。")

    counts = manifest.get("counts", {})
    expected_counts = {
        "training_source_items": len(source_item_ids),
        "role_learning_tasks": len(role_rows),
        "learning_materials": len(material_rows),
        "comprehension_questions": len(question_rows),
        "feedback_rows": len(feedback_rows),
        "role_cards": len(role_card_files),
    }
    for key, actual in expected_counts.items():
        if int(counts.get(key, -1)) != actual:
            fail(findings, "manifest_count_mismatch_" + key, f"manifest {key}={counts.get(key)}，实际 {actual}。")
    if int(counts.get("database_write_performed", -1)) != 0:
        fail(findings, "database_write_marker_invalid", "manifest database_write_performed 必须为 0。")
    if counts.get("training_source_items") != 29:
        fail(findings, "training_source_count_invalid", f"培训宣贯源条目应为 29，实际 {counts.get('training_source_items')}。")

    check_doc_guardrails(
        pack_dir,
        [
            files.get("overview", REQUIRED_FILES["overview"]),
            files.get("lims_boundary", REQUIRED_FILES["lims_boundary"]),
            files.get("training_record_template", REQUIRED_FILES["training_record_template"]),
            files.get("readme", REQUIRED_FILES["readme"]),
            *[str(path.relative_to(pack_dir)) for path in role_card_files],
        ],
        findings,
    )

    status = "passed" if not findings else "failed"
    return {
        "generated_at": dt.datetime.now().isoformat(timespec="seconds"),
        "pack_dir": str(pack_dir),
        "status": status,
        "counts": {
            "training_source_items": len(source_item_ids),
            "role_learning_tasks": len(role_rows),
            "learning_materials": len(material_rows),
            "comprehension_questions": len(question_rows),
            "feedback_rows": len(feedback_rows),
            "role_cards": len(role_card_files),
            "findings": len(findings),
        },
        "findings": findings,
    }


def render_markdown(result: dict[str, Any]) -> str:
    lines = [
        "# 机构人员学习实施包 dry-run 验证报告",
        "",
        f"生成时间：{result['generated_at']}",
        f"验证对象：`{result['pack_dir']}`",
        f"结论：{result['status']}",
        "",
        "## 计数",
        "",
    ]
    for key, value in result.get("counts", {}).items():
        lines.append(f"- {key}: {value}")
    lines.extend(["", "## 发现项", ""])
    if result.get("findings"):
        for finding in result["findings"]:
            lines.append(f"- [{finding['severity']}] {finding['id']}：{finding['message']}")
    else:
        lines.append("未发现阻断性问题。该结论仅证明人员学习实施包结构完整、边界明确、学习/反馈状态仍为 pending/空白；不代表真实培训完成、人工批准或写入 LIMS。")
    lines.append("")
    return "\n".join(lines)


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--pack-dir", required=True)
    parser.add_argument("--json-out")
    parser.add_argument("--md-out")
    args = parser.parse_args()

    result = check_pack(Path(args.pack_dir))
    print(json.dumps(result, ensure_ascii=False, indent=2))
    if args.json_out:
        Path(args.json_out).write_text(json.dumps(result, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
    if args.md_out:
        Path(args.md_out).write_text(render_markdown(result), encoding="utf-8")
    return 0 if result["status"] == "passed" else 1


if __name__ == "__main__":
    raise SystemExit(main())
