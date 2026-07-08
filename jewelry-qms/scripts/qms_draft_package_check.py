#!/usr/bin/env python3
"""Read-only gate for the QMS fifth-edition candidate draft package."""

from __future__ import annotations

import argparse
import datetime as dt
import json
import re
from pathlib import Path
from typing import Any


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

REQUIRED_STAGE_FILES = {
    "manual": "10-质量手册第五版候选稿.md",
    "revision_note": "11-第四版到第五版候选修订说明.md",
    "procedure_catalog": "12-支持性程序目录-2022版.md",
    "record_templates": "13-记录模板包-候选清单.md",
    "implementation_plan": "14-jewelry-qms实施计划与验证方案.md",
    "matrix": "15-条款程序记录LIMS验证矩阵.md",
    "basis": "16-依据现行性复核记录.md",
}

STALE_PATTERNS = {
    "stale_g003_2018": r"CNAS-CL01-G003:2018",
    "stale_rbt_045": r"R\s*B\s*/?\s*T\s*045-2020|RBT\s*045-2020",
    "stale_procedure_2018": r"XZTC/CX-\d+(?:-\d+)?-2018",
}


def read_text(path: Path) -> str:
    return path.read_text(encoding="utf-8")


def has_clause(text: str, clause: str) -> bool:
    return bool(re.search(rf"(?m)^#+\s+{re.escape(clause)}(?:\s|$)", text))


def extract_codes(text: str) -> list[str]:
    return sorted(set(re.findall(r"XZTC/CX-\d+(?:-\d+)?-\d{4}", text)))


def is_attachment_form(item: dict[str, str]) -> bool:
    return (
        item.get("document_kind") == "numbered_attachment"
        or item.get("reason") == "numbered_non_procedure"
    )


def load_manifest_items(manifest_path: Path) -> list[dict[str, str]]:
    data = json.loads(read_text(manifest_path))
    return [
        item
        for item in data.get("included", [])
        if item.get("doc_number") and item.get("year") == "2022"
    ]


def fail(rule_id: str, message: str, severity: str = "high") -> dict[str, str]:
    return {"id": rule_id, "severity": severity, "message": message}


def check_package(stage_dir: Path, lims_root: Path) -> dict[str, Any]:
    findings: list[dict[str, str]] = []
    files = {key: stage_dir / name for key, name in REQUIRED_STAGE_FILES.items()}

    for key, path in files.items():
        if not path.exists():
            findings.append(fail(f"missing_{key}", f"缺少文件：{path.name}"))

    if any(not path.exists() for path in files.values()):
        return {
            "generated_at": dt.datetime.now().isoformat(timespec="seconds"),
            "stage_dir": str(stage_dir),
            "status": "failed",
            "findings": findings,
        }

    manual = read_text(files["manual"])
    procedure_catalog = read_text(files["procedure_catalog"])
    record_templates = read_text(files["record_templates"])
    implementation_plan = read_text(files["implementation_plan"])
    matrix = read_text(files["matrix"])
    basis = read_text(files["basis"])

    missing_clauses = [clause for clause in EXPECTED_CLAUSES if not has_clause(manual, clause)]
    if missing_clauses:
        findings.append(fail("manual_missing_clauses", "候选手册缺少条款：" + ", ".join(missing_clauses)))

    if "文件状态：第五版候选草案" not in manual:
        findings.append(fail("manual_missing_draft_status", "候选手册未明确标注第五版候选草案。"))

    if "已取得检验检测机构资质认定（CMA）" not in manual:
        findings.append(fail("manual_missing_cma_status", "候选手册未写明已取得 CMA。"))

    if "CNAS 认可申请" not in manual:
        findings.append(fail("manual_missing_cnas_application", "候选手册未写明 CNAS 认可申请口径。"))

    if re.search(r"已取得\s*CNAS|CNAS\s*认可证书", manual):
        findings.append(fail("manual_overstates_cnas", "候选手册疑似把 CNAS 写成已取得。"))

    if "jewelry-qms" in manual:
        findings.append(fail("manual_mentions_jewelry_qms", "候选手册正文出现 jewelry-qms，应仅放实施计划。"))

    for rule_id, pattern in STALE_PATTERNS.items():
        if re.search(pattern, manual, flags=re.IGNORECASE):
            findings.append(fail(rule_id, f"候选手册命中过时/错误模式：{rule_id}"))

    if "CNAS-CL01-G003:2021" not in manual:
        findings.append(fail("manual_missing_current_g003", "候选手册未出现 CNAS-CL01-G003:2021 口径。"))

    if "当前状态" not in manual or "条件启用" not in manual:
        findings.append(fail("manual_sampling_not_split", "7.3 抽样未明确拆分当前状态和条件启用。"))

    manifest_path = lims_root / "knowledge/internal/procedures/PROCEDURE_FILE_MANIFEST.json"
    manifest_items = load_manifest_items(manifest_path)
    manifest_codes = sorted(item["doc_number"] for item in manifest_items)
    manifest_procedure_codes = sorted(item["doc_number"] for item in manifest_items if not is_attachment_form(item))
    manifest_attachment_codes = sorted(item["doc_number"] for item in manifest_items if is_attachment_form(item))
    catalog_codes = extract_codes(procedure_catalog)
    missing_catalog_codes = [code for code in manifest_codes if code not in catalog_codes]
    if missing_catalog_codes:
        findings.append(
            fail("catalog_missing_2022_codes", "支持性程序目录缺少 2022 编号：" + ", ".join(missing_catalog_codes))
        )

    manual_codes = extract_codes(manual)
    non_2022_manual_codes = [code for code in manual_codes if not code.endswith("-2022")]
    if non_2022_manual_codes:
        findings.append(
            fail("manual_non_2022_procedure_codes", "候选手册存在非 2022 程序编号：" + ", ".join(non_2022_manual_codes))
        )

    unknown_manual_codes = [code for code in manual_codes if code not in manifest_codes]
    if unknown_manual_codes:
        findings.append(
            fail("manual_unknown_procedure_codes", "候选手册引用了未在 LIMS 2022 清单中的编号：" + ", ".join(unknown_manual_codes))
        )

    for clause in EXPECTED_CLAUSES:
        if f"| {clause} " not in matrix and f"| {clause} |" not in matrix:
            findings.append(fail("matrix_missing_clause_" + clause, f"验证矩阵缺少条款 {clause}。"))

    required_template_tokens = ["JL-4.1-01", "JL-7.11-01", "JL-8.8-01", "JL-8.9-01"]
    for token in required_template_tokens:
        if token not in record_templates:
            findings.append(fail("record_template_missing_" + token, f"记录模板清单缺少 {token}。"))

    if "jewelry-qms" not in implementation_plan or "建设" not in implementation_plan:
        findings.append(fail("implementation_plan_missing_boundary", "实施计划未说明 jewelry-qms 建设中边界。"))

    for token in ["CNAS-CL01-G001:2024", "CNAS-CL01-G003:2021", "2023 年第 21 号", "RB/T 045-2020"]:
        if token not in basis:
            findings.append(fail("basis_missing_" + token, f"依据现行性记录缺少 {token}。", "medium"))

    status = "passed" if not findings else "failed"
    return {
        "generated_at": dt.datetime.now().isoformat(timespec="seconds"),
        "stage_dir": str(stage_dir),
        "status": status,
        "counts": {
            "expected_clauses": len(EXPECTED_CLAUSES),
            "manual_clauses_present": len(EXPECTED_CLAUSES) - len(missing_clauses),
            "manifest_2022_codes": len(manifest_codes),
            "manifest_2022_procedure_codes": len(manifest_procedure_codes),
            "manifest_2022_attachment_form_codes": len(manifest_attachment_codes),
            "catalog_codes": len(catalog_codes),
            "manual_xztc_codes": len(manual_codes),
            "findings": len(findings),
        },
        "findings": findings,
    }


def render_markdown(result: dict[str, Any]) -> str:
    lines = [
        "# 第五版候选修订包验证报告",
        "",
        f"生成时间：{result['generated_at']}",
        f"验证对象：`{result['stage_dir']}`",
        f"结论：{result['status']}",
        "",
    ]
    counts = result.get("counts", {})
    if counts:
        lines.extend(
            [
                "## 计数",
                "",
                f"- 条款覆盖：{counts.get('manual_clauses_present')}/{counts.get('expected_clauses')}",
                f"- LIMS 2022 编号清单：{counts.get('manifest_2022_codes')}（程序 {counts.get('manifest_2022_procedure_codes')}，编号附件/表单 {counts.get('manifest_2022_attachment_form_codes')}）",
                f"- 支持性程序目录编号：{counts.get('catalog_codes')}",
                f"- 手册引用 XZTC 编号：{counts.get('manual_xztc_codes')}",
                f"- 发现项：{counts.get('findings')}",
                "",
            ]
        )
    findings = result.get("findings", [])
    if findings:
        lines.extend(["## 发现项", ""])
        for item in findings:
            lines.append(f"- [{item['severity']}] {item['id']}：{item['message']}")
    else:
        lines.extend(
            [
                "## 发现项",
                "",
                "未发现阻断性问题。该结论仅证明候选修订包的文件结构、关键边界和编号一致性通过本脚本检查，不等于正式受控发布或体系运行有效。",
            ]
        )
    lines.append("")
    return "\n".join(lines)


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--stage-dir", required=True)
    parser.add_argument("--lims-root", default=str(Path(__file__).resolve().parents[2]))
    parser.add_argument("--json-out")
    parser.add_argument("--md-out")
    args = parser.parse_args()

    result = check_package(Path(args.stage_dir), Path(args.lims_root))
    print(json.dumps(result, ensure_ascii=False, indent=2))

    if args.json_out:
        Path(args.json_out).write_text(json.dumps(result, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
    if args.md_out:
        Path(args.md_out).write_text(render_markdown(result), encoding="utf-8")

    return 0 if result["status"] == "passed" else 1


if __name__ == "__main__":
    raise SystemExit(main())
