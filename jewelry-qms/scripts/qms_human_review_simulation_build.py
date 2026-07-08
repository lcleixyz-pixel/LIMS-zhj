#!/usr/bin/env python3
"""Build a simulated approved human-review pack for apply-rehearsal only."""

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

REQUIRED_REVIEW_FILES = {
    "manual_clause_review": "manual_clause_review_checklist.csv",
    "record_template_review": "record_template_review_checklist.csv",
    "attachment_disposition": "attachment_form_disposition.csv",
    "preapply_gate_register": "preapply_gate_register.csv",
}

SIMULATION_MARKER = "SIMULATED_APPROVAL_NOT_REAL_REVIEW"
SIMULATED_DECISION = "确认通过"

GUARDRAILS = [
    "本包仅用于 qms:preimport-package --apply-rehearsal 非写库演练。",
    "本包不代表真实人工评审、审核批准、受控发布或正式写库授权。",
    "本包不修改 human_review_pack/，不得作为正式 --apply 的 --review-dir。",
    f"所有模拟决策均带 {SIMULATION_MARKER} 标识。",
    "资质状态按已取得 CMA、CNAS 申请中处理；不得写成已取得 CNAS。",
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


def simulate_rows(rows: list[dict[str, str]], file_key: str) -> list[dict[str, str]]:
    simulated: list[dict[str, str]] = []
    for row in rows:
        next_row = dict(row)
        next_row["human_decision"] = SIMULATED_DECISION
        note = next_row.get("review_note", "").strip()
        simulation_note = (
            f"{SIMULATION_MARKER}: 仅用于 apply-rehearsal 非写库演练；"
            "不代表真实人工批准，不得作为正式写库依据。"
        )
        if file_key == "attachment_disposition":
            simulation_note += " 05-02 归属在真实人审中仍需单独确认。"
        next_row["review_note"] = (note + "；" + simulation_note) if note else simulation_note
        simulated.append(next_row)
    return simulated


def render_readme(manifest: dict[str, Any]) -> str:
    counts = manifest["counts"]
    lines = [
        "# 人审通过模拟包",
        "",
        "文件状态：apply-rehearsal 专用模拟包，不是真实人工评审结果。",
        "",
        "## 计数",
        "",
        f"- 条款评审模拟通过项：{counts['manual_clause_review_items']}",
        f"- 记录模板评审模拟通过项：{counts['record_template_review_items']}",
        f"- 05-02 归属模拟通过项：{counts['attachment_disposition_items']}",
        f"- apply 前闸门模拟通过项：{counts['preapply_gates']}",
        f"- 总模拟决策项：{counts['total_simulated_decisions']}",
        "",
        "## 使用边界",
        "",
    ]
    lines.extend(f"- {item}" for item in GUARDRAILS)
    lines.extend(
        [
            "",
            "## 用途",
            "",
            "本包只用于验证 LIMS 命令在“人审全部通过”条件下是否能完成 apply 前置检查、stage2 关系预检和安全边界判断。真实受控写库仍必须使用经人工评审后正式回填的 `human_review_pack/`。",
            "",
        ]
    )
    return "\n".join(lines)


def render_guide() -> str:
    lines = [
        "# 人审通过模拟包使用说明",
        "",
        f"本包中所有 `human_decision` 均被设为 `{SIMULATED_DECISION}`，并在 `review_note` 中写入 `{SIMULATION_MARKER}`。",
        "本包不代表真实人工评审，不代表审核批准、受控发布或正式写库授权。",
        "",
        "## 允许用途",
        "",
        "- 允许用于 `qms:preimport-package --apply-rehearsal`。",
        "- 允许用于检查候选文件、记录模板、字段字典、受控发布演练和发布执行记录模板在“人审通过”条件下的命令链路。",
        "",
        "## 禁止用途",
        "",
        "- 不得作为真实人工评审结论。",
        "- 不得作为正式 `--apply` 的 `--review-dir`。",
        "- 不得作为质量手册、程序、记录模板已批准或已发布的证据。",
        "- 不得据此形成真实培训、发放、旧版回收、实施有效性或 jewelry-qms 试运行记录。",
        "",
    ]
    return "\n".join(lines)


def build_simulation_pack(source_review_dir: Path, output_dir: Path) -> dict[str, Any]:
    output_dir.mkdir(parents=True, exist_ok=True)
    source_manifest_path = source_review_dir / "human_review_manifest.json"
    source_manifest = json.loads(source_manifest_path.read_text(encoding="utf-8"))
    generated_at = dt.datetime.now().isoformat(timespec="seconds")

    counts: dict[str, int] = {}
    files = dict(REQUIRED_REVIEW_FILES)
    for key, filename in REQUIRED_REVIEW_FILES.items():
        source_path = source_review_dir / filename
        rows = read_csv(source_path)
        simulated_rows = simulate_rows(rows, key)
        counts_key = {
            "manual_clause_review": "manual_clause_review_items",
            "record_template_review": "record_template_review_items",
            "attachment_disposition": "attachment_disposition_items",
            "preapply_gate_register": "preapply_gates",
        }[key]
        counts[counts_key] = len(simulated_rows)
        fieldnames = list(rows[0].keys()) if rows else []
        write_csv(output_dir / filename, simulated_rows, fieldnames)

    counts["total_simulated_decisions"] = sum(counts.values())
    files.update(
        {
            "review_guide": "人工评审模拟说明.md",
            "readme": "README.md",
            "manifest": "human_review_manifest.json",
        }
    )
    manifest = {
        "generated_at": generated_at,
        "source_review_dir": str(source_review_dir),
        "review_pack_dir": str(output_dir),
        "status": "human_review_simulation_no_database_write",
        "simulation_marker": SIMULATION_MARKER,
        "simulated_decision": SIMULATED_DECISION,
        "source_manifest_status": source_manifest.get("status"),
        "guardrails": GUARDRAILS,
        "counts": counts,
        "files": files,
    }
    (output_dir / files["manifest"]).write_text(json.dumps(manifest, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
    (output_dir / files["readme"]).write_text(render_readme(manifest), encoding="utf-8")
    (output_dir / files["review_guide"]).write_text(render_guide(), encoding="utf-8")
    return manifest


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--stage-dir", default=str(DEFAULT_STAGE_DIR))
    parser.add_argument("--source-review-dir")
    parser.add_argument("--output-dir")
    args = parser.parse_args()

    stage_dir = Path(args.stage_dir)
    source_review_dir = Path(args.source_review_dir) if args.source_review_dir else stage_dir / "human_review_pack"
    output_dir = Path(args.output_dir) if args.output_dir else stage_dir / "human_review_simulation_pack"
    manifest = build_simulation_pack(source_review_dir, output_dir)
    print(json.dumps(manifest, ensure_ascii=False, indent=2))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
