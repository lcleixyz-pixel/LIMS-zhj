#!/usr/bin/env python3
"""Dry-run validation for the QMS controlled-release rehearsal pack."""

from __future__ import annotations

import argparse
import csv
import datetime as dt
import json
import re
from pathlib import Path
from typing import Any


REQUIRED_FILES = {
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

REQUIRED_GUARDRAILS = [
    "不写数据库",
    "不代表第五版候选稿",
    "已取得 CMA",
    "CNAS 申请中",
    "LIMS 当前导出的 2022 程序清单",
    "jewelry-qms 目前只作为建设中系统",
]

REQUIRED_GATE_IDS = {"POS-01", "POS-02", "POS-03", "POS-04", "POS-05"}


def read_text(path: Path) -> str:
    return path.read_text(encoding="utf-8")


def read_csv(path: Path) -> list[dict[str, str]]:
    with path.open("r", encoding="utf-8", newline="") as handle:
        return list(csv.DictReader(handle))


def fail(findings: list[dict[str, str]], rule_id: str, message: str, severity: str = "high") -> None:
    findings.append({"id": rule_id, "severity": severity, "message": message})


def check_manual_position(stage_dir: Path, findings: list[dict[str, str]]) -> None:
    manual_path = stage_dir / "10-质量手册第五版候选稿.md"
    if not manual_path.exists():
        fail(findings, "missing_candidate_manual", f"缺少候选手册：{manual_path}")
        return
    text = read_text(manual_path)
    if "已取得检验检测机构资质认定（CMA）" not in text:
        fail(findings, "manual_missing_cma_position", "候选手册未写明 CMA 已取得口径。")
    if "CNAS 认可申请" not in text:
        fail(findings, "manual_missing_cnas_application_position", "候选手册未写明 CNAS 申请中口径。")
    if re.search(r"(本公司|公司|实验室).{0,12}(已取得|已获|获得)\s*CNAS|CNAS\s*认可证书", text):
        fail(findings, "manual_overstates_cnas", "候选手册疑似把 CNAS 写成已取得。")
    if "jewelry-qms" in text:
        fail(findings, "manual_mentions_jewelry_qms", "候选手册正文出现 jewelry-qms。")
    if "LIMS 当前导出的 2022 程序清单" not in text:
        fail(findings, "manual_missing_lims_2022_catalog_basis", "候选手册未保留 LIMS 当前导出的 2022 程序清单依据。", "medium")


def check_rehearsal(
    release_dir: Path,
    stage_dir: Path | None = None,
    preimport_dir: Path | None = None,
) -> dict[str, Any]:
    findings: list[dict[str, str]] = []
    manifest_path = release_dir / "release_rehearsal_manifest.json"
    if not manifest_path.exists():
        return {
            "generated_at": dt.datetime.now().isoformat(timespec="seconds"),
            "release_dir": str(release_dir),
            "status": "failed",
            "findings": [{"id": "missing_manifest", "severity": "high", "message": "缺少 release_rehearsal_manifest.json"}],
        }

    manifest = json.loads(read_text(manifest_path))
    if manifest.get("status") != "release_rehearsal_no_database_write":
        fail(findings, "invalid_manifest_status", "release_rehearsal_manifest.json 状态必须为 release_rehearsal_no_database_write。")

    stage_dir = stage_dir or Path(str(manifest.get("stage_dir", "")))
    preimport_dir = preimport_dir or Path(str(manifest.get("preimport_dir", "")))

    guardrail_text = "\n".join(str(item) for item in manifest.get("guardrails", []))
    for marker in REQUIRED_GUARDRAILS:
        if marker not in guardrail_text:
            fail(findings, "manifest_missing_guardrail", f"manifest guardrails 缺少标识：{marker}")

    files = manifest.get("files", {})
    for key, default_name in REQUIRED_FILES.items():
        filename = files.get(key, default_name)
        if not (release_dir / filename).exists():
            fail(findings, "missing_" + key, f"缺少演练包文件：{filename}")

    for path in list(release_dir.glob("*.sql")) + list(release_dir.glob("*.db")):
        fail(findings, "forbidden_database_artifact", f"演练包不应包含数据库/SQL 文件：{path.name}")

    release_rows = read_csv(release_dir / files.get("release_objects", REQUIRED_FILES["release_objects"])) if (release_dir / files.get("release_objects", REQUIRED_FILES["release_objects"])).exists() else []
    approval_rows = read_csv(release_dir / files.get("approval_rehearsal", REQUIRED_FILES["approval_rehearsal"])) if (release_dir / files.get("approval_rehearsal", REQUIRED_FILES["approval_rehearsal"])).exists() else []
    training_rows = read_csv(release_dir / files.get("training_rehearsal", REQUIRED_FILES["training_rehearsal"])) if (release_dir / files.get("training_rehearsal", REQUIRED_FILES["training_rehearsal"])).exists() else []
    obsolete_rows = read_csv(release_dir / files.get("obsolete_disposition", REQUIRED_FILES["obsolete_disposition"])) if (release_dir / files.get("obsolete_disposition", REQUIRED_FILES["obsolete_disposition"])).exists() else []
    gate_rows = read_csv(release_dir / files.get("position_gates", REQUIRED_FILES["position_gates"])) if (release_dir / files.get("position_gates", REQUIRED_FILES["position_gates"])).exists() else []
    effectiveness_rows = read_csv(release_dir / files.get("effectiveness_checks", REQUIRED_FILES["effectiveness_checks"])) if (release_dir / files.get("effectiveness_checks", REQUIRED_FILES["effectiveness_checks"])).exists() else []

    if preimport_dir and (preimport_dir / "documents_preimport.csv").exists():
        source_rows = read_csv(preimport_dir / "documents_preimport.csv")
        if len(release_rows) != len(source_rows):
            fail(findings, "release_object_count_mismatch", f"发布对象清单应与 documents_preimport 行数一致：release={len(release_rows)}, source={len(source_rows)}")

    counts = manifest.get("counts", {})
    expected_counts = {
        "release_objects": len(release_rows),
        "approval_items": len(approval_rows),
        "training_items": len(training_rows),
        "obsolete_items": len(obsolete_rows),
        "position_gates": len(gate_rows),
        "effectiveness_items": len(effectiveness_rows),
    }
    for key, actual in expected_counts.items():
        if int(counts.get(key, -1)) != actual:
            fail(findings, "manifest_count_mismatch_" + key, f"manifest {key}={counts.get(key)}，实际 {actual}。")

    type_counts: dict[str, int] = {}
    for row in release_rows:
        type_counts[row.get("object_type", "")] = type_counts.get(row.get("object_type", ""), 0) + 1
        if row.get("release_allowed_now") != "no":
            fail(findings, "release_allowed_before_review", f"{row.get('doc_number')} release_allowed_now 必须为 no。")
        if "CNAS 申请中" not in row.get("qualification_scope_note", ""):
            fail(findings, "release_row_missing_cnas_boundary", f"{row.get('doc_number')} 缺少 CNAS 申请中边界。")

    expected_type_counts = {
        "candidate_manual": 1,
        "current_procedure_reference": 37,
        "candidate_record_template_document": 26,
        "numbered_attachment_form_pending": 1,
    }
    for key, expected in expected_type_counts.items():
        if type_counts.get(key, 0) != expected:
            fail(findings, "release_type_count_mismatch_" + key, f"{key} 应为 {expected}，实际 {type_counts.get(key, 0)}。")

    for row in approval_rows:
        if row.get("human_decision") != "pending":
            fail(findings, "approval_decision_not_pending", f"{row.get('approval_item_id')} 必须保持 pending。")
        if row.get("blocking_if_unresolved") != "yes":
            fail(findings, "approval_not_blocking", f"{row.get('approval_item_id')} 未设置阻断。")

    gate_ids = {row.get("gate_id", "") for row in gate_rows}
    missing_gate_ids = sorted(REQUIRED_GATE_IDS - gate_ids)
    if missing_gate_ids:
        fail(findings, "missing_position_gates", "口径闸门缺少：" + "、".join(missing_gate_ids))
    for row in gate_rows:
        if row.get("blocking_if_failed") != "yes":
            fail(findings, "position_gate_not_blocking", f"{row.get('gate_id')} 未设置失败阻断。")

    if not any(row.get("source_object") == "14-jewelry-qms实施计划与验证方案.md" for row in training_rows):
        fail(findings, "missing_jewelry_qms_training_boundary", "培训清单缺少 jewelry-qms 建设中系统边界培训项。")
    if not any("质量手册第四版" in row.get("object", "") for row in obsolete_rows):
        fail(findings, "missing_manual_obsolete_disposition", "旧版处置清单缺少质量手册第四版处置项。")
    if not any(row.get("process") == "jewelry-qms 试运行" for row in effectiveness_rows):
        fail(findings, "missing_jewelry_qms_effectiveness_check", "实施有效性清单缺少 jewelry-qms 试运行检查项。")

    for filename in [
        files.get("overview", REQUIRED_FILES["overview"]),
        files.get("lims_governance_matrix", REQUIRED_FILES["lims_governance_matrix"]),
        files.get("readme", REQUIRED_FILES["readme"]),
    ]:
        path = release_dir / filename
        if not path.exists():
            continue
        text = read_text(path)
        for marker in ["不写数据库", "不代表受控发布"]:
            if marker not in text:
                fail(findings, "doc_missing_guardrail", f"{filename} 缺少边界标识：{marker}")
        if re.search(r"已批准发布|可以写库|准许写库|正式运行记录|(本公司|公司|实验室).{0,12}(已取得|已获|获得)\s*CNAS|CNAS\s*认可证书", text):
            fail(findings, "doc_overstates_status", f"{filename} 疑似包含越权状态表述。")

    if stage_dir:
        check_manual_position(stage_dir, findings)

    status = "passed" if not findings else "failed"
    return {
        "generated_at": dt.datetime.now().isoformat(timespec="seconds"),
        "release_dir": str(release_dir),
        "stage_dir": str(stage_dir) if stage_dir else None,
        "preimport_dir": str(preimport_dir) if preimport_dir else None,
        "status": status,
        "counts": {
            "release_objects": len(release_rows),
            "approval_items": len(approval_rows),
            "training_items": len(training_rows),
            "obsolete_items": len(obsolete_rows),
            "position_gates": len(gate_rows),
            "effectiveness_items": len(effectiveness_rows),
            "candidate_manual_objects": type_counts.get("candidate_manual", 0),
            "current_procedure_references": type_counts.get("current_procedure_reference", 0),
            "candidate_record_template_documents": type_counts.get("candidate_record_template_document", 0),
            "attachment_form_pending": type_counts.get("numbered_attachment_form_pending", 0),
            "findings": len(findings),
        },
        "findings": findings,
    }


def render_markdown(result: dict[str, Any]) -> str:
    lines = [
        "# 受控发布治理演练 dry-run 验证报告",
        "",
        f"生成时间：{result['generated_at']}",
        f"验证对象：`{result['release_dir']}`",
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
        lines.append("未发现阻断性问题。该结论只证明受控发布演练包结构、口径闸门和不写库边界通过检查；不代表候选文件已批准、发布或写入 LIMS。")
    lines.append("")
    return "\n".join(lines)


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--release-dir", required=True)
    parser.add_argument("--stage-dir")
    parser.add_argument("--preimport-dir")
    parser.add_argument("--json-out")
    parser.add_argument("--md-out")
    args = parser.parse_args()

    result = check_rehearsal(
        Path(args.release_dir),
        Path(args.stage_dir) if args.stage_dir else None,
        Path(args.preimport_dir) if args.preimport_dir else None,
    )
    print(json.dumps(result, ensure_ascii=False, indent=2))
    if args.json_out:
        Path(args.json_out).write_text(json.dumps(result, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
    if args.md_out:
        Path(args.md_out).write_text(render_markdown(result), encoding="utf-8")
    return 0 if result["status"] == "passed" else 1


if __name__ == "__main__":
    raise SystemExit(main())
