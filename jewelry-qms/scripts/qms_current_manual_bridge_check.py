#!/usr/bin/env python3
"""Read-only bridge check between current LIMS QMS exports and a QMS engineering package."""

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

STALE_BASIS_RULES = [
    {
        "id": "basis-cnas-g003-2018",
        "pattern": r"CNAS-CL01-G003:2018",
        "severity": "high",
        "message": "现用手册出现 CNAS-CL01-G003:2018；应核对并替换为 CNAS-CL01-G003:2021 及 2023 第一次修订口径。",
    },
    {
        "id": "basis-rbt-045-2020",
        "pattern": r"R\s*B\s*/?\s*T\s*045-2020|RBT\s*045-2020",
        "severity": "high",
        "message": "现用手册出现 RB/T 045-2020；全国标准信息公共服务平台显示其已于 2024-11-26 废止。",
    },
    {
        "id": "basis-rbt-214",
        "pattern": r"R\s*B\s*/?\s*T\s*214(?:-2017)?|RBT\s*214(?:-2017)?",
        "severity": "medium",
        "message": "出现 RB/T 214；当前 CMA 文件中只能作为历史参考或旧版对照，不宜作为现行主依据。",
    },
    {
        "id": "basis-rbt-195-196",
        "pattern": r"R\s*B\s*/?\s*T\s*(195|196)(?:-2015)?|RBT\s*(195|196)(?:-2015)?",
        "severity": "medium",
        "message": "出现 RB/T 195 或 RB/T 196；作为现行依据使用前应复核有效状态。",
    },
]

OK_BASIS_HINTS = [
    ("CNAS-CL01-G001:2024", "已出现 CNAS-CL01-G001:2024。"),
    ("《检验检测机构资质认定评审准则》（2023年版）", "已出现 CMA 2023 年版评审准则口径。"),
    ("CNAS-CL01-A015:2018", "已出现珠宝玉石、贵金属检测领域 CNAS 应用说明。"),
]


def read_text(path: Path) -> str:
    return path.read_text(encoding="utf-8")


def find_manual(lims_root: Path, manual_path: str | None) -> Path:
    if manual_path:
        path = Path(manual_path).expanduser()
        if not path.is_absolute():
            path = lims_root / path
        return path
    manual_dir = lims_root / "knowledge/internal/manual"
    matches = sorted(manual_dir.glob("*.md"))
    if not matches:
        raise FileNotFoundError(f"No markdown manual found under {manual_dir}")
    if len(matches) > 1:
        preferred = [p for p in matches if "质量手册" in p.name]
        if preferred:
            return preferred[0]
    return matches[0]


def line_number(text: str, offset: int) -> int:
    return text.count("\n", 0, offset) + 1


def has_clause(text: str, clause: str) -> bool:
    return bool(
        re.search(rf"(?m)^#+\s+{re.escape(clause)}(?:\s|$)", text)
        or re.search(rf"(?m)^{re.escape(clause)}(?:\s|$)", text)
    )


def extract_card_refs(text: str) -> list[str]:
    refs: set[str] = set()
    for group in re.findall(r"\[([^\]]+)\]", text):
        for token in re.split(r"[,，、]\s*", group):
            token = token.strip()
            if re.fullmatch(r"[KFGXIP]-[A-Za-z]?\d+[a-z]?", token):
                refs.add(token)
    return sorted(refs)


def extract_package_cards(package_root: Path) -> list[str]:
    cards: set[str] = set()
    for template in sorted(package_root.glob("要素模板-*.md")):
        text = read_text(template)
        cards.update(re.findall(r"\b([KFGXIP]-[A-Za-z]?\d+[a-z]?)\b", text))
    return sorted(cards)


def extract_manual_procedure_codes(text: str) -> list[str]:
    return sorted(set(re.findall(r"XZTC/CX-\d+(?:-\d+)?-\d{4}", text)))


def extract_exported_procedure_codes(procedure_dir: Path) -> list[str]:
    codes: set[str] = set()
    for path in sorted(procedure_dir.glob("XZTC_CX-*.md")):
        match = re.match(r"XZTC_CX-(\d+(?:-\d+)?-\d{4})-", path.name)
        if match:
            codes.add("XZTC/CX-" + match.group(1))
    return sorted(codes)


def code_base(code: str) -> str:
    return re.sub(r"-\d{4}$", "", code)


def compare_procedure_codes(manual_codes: list[str], exported_codes: list[str]) -> dict[str, Any]:
    manual_set = set(manual_codes)
    exported_set = set(exported_codes)
    manual_by_base = {code_base(code): code for code in manual_codes}
    exported_by_base = {code_base(code): code for code in exported_codes}
    year_mismatches = []
    for base in sorted(set(manual_by_base) & set(exported_by_base)):
        if manual_by_base[base] != exported_by_base[base]:
            year_mismatches.append(
                {
                    "base": base,
                    "manual": manual_by_base[base],
                    "exported": exported_by_base[base],
                }
            )
    return {
        "manual_count": len(manual_codes),
        "exported_count": len(exported_codes),
        "exact_matches": sorted(manual_set & exported_set),
        "manual_only": sorted(manual_set - exported_set),
        "exported_only": sorted(exported_set - manual_set),
        "year_mismatches": year_mismatches,
        "exported_not_referenced_by_base": [
            exported_by_base[base]
            for base in sorted(set(exported_by_base) - set(manual_by_base))
        ],
    }


def collect_basis_findings(text: str) -> dict[str, Any]:
    stale = []
    for rule in STALE_BASIS_RULES:
        hits = []
        for match in re.finditer(rule["pattern"], text, flags=re.IGNORECASE):
            hits.append({"line": line_number(text, match.start()), "match": match.group(0)})
        if hits:
            stale.append(
                {
                    "id": rule["id"],
                    "severity": rule["severity"],
                    "message": rule["message"],
                    "hits": hits,
                }
            )

    ok = []
    for pattern, message in OK_BASIS_HINTS:
        count = text.count(pattern)
        if count:
            ok.append({"pattern": pattern, "message": message, "count": count})
    return {"stale": stale, "recognized_current": ok}


def collect_sampling_findings(text: str) -> list[dict[str, Any]]:
    findings = []
    sampling_section = ""
    match = re.search(r"(?ms)^#+\s+7\.3\b.*?(?=^#+\s+7\.4\b|\Z)", text)
    if match:
        sampling_section = match.group(0)
        start_line = line_number(text, match.start())
    else:
        start_line = None
    if sampling_section and re.search(r"不(进行|从事).*抽样", sampling_section) and re.search(
        r"抽样计划|抽样方案|抽样记录|统计方法", sampling_section
    ):
        findings.append(
            {
                "id": "sampling-mixed-current-and-conditional",
                "severity": "medium",
                "line": start_line,
                "message": "7.3 抽样条款混写了当前不开展抽样和条件性抽样控制，建议拆成当前规则与启用条件。",
            }
        )
    return findings


def collect_package_profile_findings(package_root: Path) -> list[dict[str, Any]]:
    findings = []
    package_manual = package_root / "质量手册-工作版-v0.1.md"
    if not package_manual.exists():
        return findings
    text = read_text(package_manual)
    profile_patterns = [
        ("package-fictional-org", r"虚构|星辰宝玉石检测有限公司", "工程包仍包含虚构机构画像。"),
        ("package-single-site", r"单一固定场所", "工程包假设为单一固定场所，而现用手册为多场所。"),
        ("package-future-lims", r"未来引入\s*LIMS", "工程包把 LIMS 写成未来状态，但当前项目已存在 jewelry-qms 治理系统。"),
    ]
    for item_id, pattern, message in profile_patterns:
        hits = []
        for match in re.finditer(pattern, text):
            hits.append({"line": line_number(text, match.start()), "match": match.group(0)})
        if hits:
            findings.append({"id": item_id, "severity": "medium", "message": message, "hits": hits})
    return findings


def risk_summary(result: dict[str, Any]) -> dict[str, int]:
    counts = {"high": 0, "medium": 0, "low": 0}
    if result["card_bridge"]["manual_card_ref_count"] == 0:
        counts["high"] += 1
    if result["procedure_bridge"]["year_mismatches"]:
        counts["high"] += 1
    for finding in result["basis"]["stale"]:
        counts[finding["severity"]] = counts.get(finding["severity"], 0) + 1
    for finding in result["sampling_findings"]:
        counts[finding["severity"]] = counts.get(finding["severity"], 0) + 1
    for finding in result["package_profile_findings"]:
        counts[finding["severity"]] = counts.get(finding["severity"], 0) + 1
    if result["clause_coverage"]["missing"]:
        counts["high"] += 1
    return counts


def build_markdown(result: dict[str, Any]) -> str:
    procedure = result["procedure_bridge"]
    basis = result["basis"]
    risk = result["risk_summary"]
    lines = [
        "# QMS 工程包到 LIMS 桥接治理检查结果",
        "",
        f"生成时间：{result['generated_at']}",
        f"现用手册导出：`{result['manual_path']}`",
        f"工程包：`{result['package_root']}`",
        "",
        "## 总览",
        "",
        f"- 条款骨架：{result['clause_coverage']['found_count']}/{result['clause_coverage']['expected_count']}；缺失：{', '.join(result['clause_coverage']['missing']) or '无'}。",
        f"- 工程包证据卡：工程包 {result['card_bridge']['package_card_count']} 个；现用手册回链 {result['card_bridge']['manual_card_ref_count']} 个。",
        f"- 支撑程序：手册引用 {procedure['manual_count']} 个；LIMS 导出 {procedure['exported_count']} 个；精确匹配 {len(procedure['exact_matches'])} 个；同编号不同年份 {len(procedure['year_mismatches'])} 个。",
        f"- 风险计数：高 {risk.get('high', 0)} / 中 {risk.get('medium', 0)} / 低 {risk.get('low', 0)}。",
        "",
        "## 结论",
        "",
    ]
    if risk.get("high", 0):
        lines.append("当前适合进入“修订草案和治理准备”阶段，不适合直接声明现用手册已通过工程包强校验。")
    else:
        lines.append("当前未发现高风险桥接问题，可进入更细的正文条款审查。")
    lines.extend(
        [
            "",
            "## 高优先级差距",
            "",
        ]
    )
    if result["card_bridge"]["manual_card_ref_count"] == 0:
        lines.extend(
            [
                "### 1. 现用手册没有工程包证据卡回链",
                "",
                "影响：工程包只能确认章节骨架，无法证明条款与底稿事实、依据、控制点之间形成证据链。",
                "",
                "建议：先建立条款到 K/F/G 卡的映射矩阵，再生成第五版候选草案。",
                "",
            ]
        )
    if procedure["year_mismatches"]:
        lines.extend(
            [
                "### 2. 手册支撑程序年份与 LIMS 导出程序不闭合",
                "",
                "影响：可能构成文件控制与现行版本识别风险。",
                "",
                "| 编号基底 | 手册引用 | LIMS 导出 |",
                "|---|---|---|",
            ]
        )
        for item in procedure["year_mismatches"][:50]:
            lines.append(f"| {item['base']} | {item['manual']} | {item['exported']} |")
        if len(procedure["year_mismatches"]) > 50:
            lines.append(f"| ... | 其余 {len(procedure['year_mismatches']) - 50} 项 | ... |")
        lines.append("")
    if basis["stale"]:
        lines.extend(["### 3. 依据现行性需处理", "", "| 风险 | 位置 | 命中 | 说明 |", "|---|---:|---|---|"])
        for finding in basis["stale"]:
            for hit in finding["hits"]:
                lines.append(f"| {finding['severity']} | {hit['line']} | {hit['match']} | {finding['message']} |")
        lines.append("")
    if not (result["card_bridge"]["manual_card_ref_count"] == 0 or procedure["year_mismatches"] or basis["stale"]):
        lines.append("未发现高优先级差距。")
        lines.append("")

    lines.extend(["## 中优先级差距", ""])
    for finding in result["sampling_findings"]:
        lines.append(f"- {finding['message']}（约第 {finding.get('line') or '未知'} 行）")
    for finding in result["package_profile_findings"]:
        hit_text = ", ".join(f"{hit['match']}@{hit['line']}" for hit in finding["hits"][:3])
        lines.append(f"- {finding['message']}（{hit_text}）")
    if not result["sampling_findings"] and not result["package_profile_findings"]:
        lines.append("未发现中优先级差距。")
    lines.extend(
        [
            "",
            "## LIMS 治理落地建议",
            "",
            "1. 把本报告作为“第五版候选修订”的输入，不直接替换现用受控文件。",
            "2. 先在工程包中建立 XZTC 机构画像参数，去除虚构机构、单场所和未来 LIMS 假设。",
            "3. 建立条款到证据卡、程序文件、记录表单的追溯矩阵。",
            "4. 由 LIMS 文件控制模块承接修订、审批、发布、培训宣贯和旧版作废。",
            "5. 重新导出 `knowledge/internal` 后再次运行本脚本，直到高风险项清零。",
            "",
            "## 后续可自动化增强",
            "",
            "- 每个主条款最低证据卡覆盖率。",
            "- 支撑程序正文与手册条款的语义一致性检查。",
            "- 外来文件依据现行状态自动台账。",
            "- 第五版候选稿与第四版差异包。",
        ]
    )
    return "\n".join(lines) + "\n"


def build_result(lims_root: Path, package_root: Path, manual_path: Path) -> dict[str, Any]:
    manual_text = read_text(manual_path)
    procedure_dir = lims_root / "knowledge/internal/procedures"
    card_refs = extract_card_refs(manual_text)
    package_cards = extract_package_cards(package_root)
    manual_codes = extract_manual_procedure_codes(manual_text)
    exported_codes = extract_exported_procedure_codes(procedure_dir)
    missing_clauses = [clause for clause in EXPECTED_CLAUSES if not has_clause(manual_text, clause)]

    result: dict[str, Any] = {
        "generated_at": dt.datetime.now().isoformat(timespec="seconds"),
        "lims_root": str(lims_root),
        "package_root": str(package_root),
        "manual_path": str(manual_path),
        "clause_coverage": {
            "expected_count": len(EXPECTED_CLAUSES),
            "found_count": len(EXPECTED_CLAUSES) - len(missing_clauses),
            "missing": missing_clauses,
        },
        "card_bridge": {
            "manual_card_ref_count": len(card_refs),
            "manual_card_refs": card_refs,
            "package_card_count": len(package_cards),
            "unknown_manual_refs": sorted(set(card_refs) - set(package_cards)),
        },
        "procedure_bridge": compare_procedure_codes(manual_codes, exported_codes),
        "basis": collect_basis_findings(manual_text),
        "sampling_findings": collect_sampling_findings(manual_text),
        "package_profile_findings": collect_package_profile_findings(package_root),
    }
    result["risk_summary"] = risk_summary(result)
    return result


def main() -> int:
    parser = argparse.ArgumentParser(description="Check current LIMS QMS manual against QMS package governance gates.")
    parser.add_argument("--lims-root", default="..", help="Path to LIMS-zhj root. Defaults to parent of jewelry-qms.")
    parser.add_argument("--package-root", required=True, help="Path to QMS engineering package.")
    parser.add_argument("--manual", help="Manual markdown path, absolute or relative to LIMS root.")
    parser.add_argument("--out-dir", required=True, help="Directory for JSON and Markdown reports.")
    parser.add_argument("--prefix", default="qms-bridge-check", help="Output file prefix.")
    args = parser.parse_args()

    script_dir = Path(__file__).resolve().parent
    default_lims_root = (script_dir / ".." / "..").resolve()
    lims_root = Path(args.lims_root).expanduser()
    if args.lims_root == "..":
        lims_root = default_lims_root
    else:
        lims_root = lims_root.resolve()
    package_root = Path(args.package_root).expanduser().resolve()
    out_dir = Path(args.out_dir).expanduser().resolve()
    out_dir.mkdir(parents=True, exist_ok=True)

    manual_path = find_manual(lims_root, args.manual).resolve()
    result = build_result(lims_root, package_root, manual_path)

    json_path = out_dir / f"{args.prefix}.json"
    md_path = out_dir / f"{args.prefix}.md"
    json_path.write_text(json.dumps(result, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
    md_path.write_text(build_markdown(result), encoding="utf-8")

    risk = result["risk_summary"]
    print(f"Wrote {json_path}")
    print(f"Wrote {md_path}")
    print(
        "Summary: clauses "
        f"{result['clause_coverage']['found_count']}/{result['clause_coverage']['expected_count']}, "
        f"manual card refs {result['card_bridge']['manual_card_ref_count']}, "
        f"procedure year mismatches {len(result['procedure_bridge']['year_mismatches'])}, "
        f"risks high/medium/low {risk.get('high', 0)}/{risk.get('medium', 0)}/{risk.get('low', 0)}"
    )
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
