#!/usr/bin/env python3
"""Re-judge an existing wave1 raw smoke log with A1 criteria (no re-run)."""
from __future__ import annotations

import argparse
import re
from pathlib import Path

SECTION = re.compile(r"===== \[(\d+)(?:/\d+)?\] ([^\s=]+) =====\n")
SENTINEL = re.compile(r"(?i)(?<![A-Za-z])(passed|OK|smoke通过)(?![A-Za-z])")
BAD = re.compile(r"Exception\]|Fatal error")


def judge(exit_code: int | None, body: str) -> str:
    if BAD.search(body):
        return "FAIL"
    if exit_code is not None and exit_code != 0:
        return "FAIL"
    if exit_code is None:
        # no exit recorded → cannot PASS
        return "FAIL"
    if SENTINEL.search(body):
        return "PASS"
    return "FAIL"


def main() -> None:
    ap = argparse.ArgumentParser()
    ap.add_argument("raw_log")
    ap.add_argument("--summary", required=True)
    ap.add_argument("--rewritten-log", default="")
    args = ap.parse_args()

    text = Path(args.raw_log).read_text(encoding="utf-8", errors="replace")
    parts = SECTION.split(text)
    # preamble, num, name, body, num, name, body...
    rows: list[tuple[str, str, int | None, str, str]] = []
    for i in range(1, len(parts), 3):
        if i + 2 >= len(parts):
            break
        num, name, body = parts[i], parts[i + 1], parts[i + 2]
        m = re.search(r"(?m)^EXIT_CODE=(-?\d+)\s*$", body)
        exit_code = int(m.group(1)) if m else None
        # strip old RESULT line for judgment body
        body_for_judge = re.sub(r"(?m)^RESULT=.*\n?", "", body)
        result = judge(exit_code, body_for_judge)
        rows.append((num, name, exit_code, result, body_for_judge.rstrip() + "\n"))

    pass_n = sum(1 for r in rows if r[3] == "PASS")
    fail_n = len(rows) - pass_n
    fails = [r[1] for r in rows if r[3] == "FAIL"]

    rewritten = []
    for num, name, exit_code, result, body in rows:
        rewritten.append(f"===== [{num}/{len(rows)}] {name} =====\n")
        rewritten.append(body)
        if not body.endswith("\n"):
            rewritten.append("\n")
        if exit_code is not None and "EXIT_CODE=" not in body[-80:]:
            rewritten.append(f"EXIT_CODE={exit_code}\n")
        elif exit_code is not None and not re.search(r"(?m)^EXIT_CODE=", body):
            rewritten.append(f"EXIT_CODE={exit_code}\n")
        rewritten.append(f"RESULT={result}\n")

    if args.rewritten_log:
        Path(args.rewritten_log).write_text("".join(rewritten), encoding="utf-8")

    lines = [
        "# 全量 smoke 汇总（A1 判定口径复判）",
        "",
        f"- 源日志：`{Path(args.raw_log).name}`",
        "- 判定：PASS = exit=0 且哨兵(passed/OK/smoke通过)；输出含 Exception] 或 Fatal error → FAIL",
        f"- 统计：**PASS={pass_n} / FAIL={fail_n} / TOTAL={len(rows)}**",
        f"- 相对旧口径（exit-only PASS=86）：假 PASS 纠正后预期 ≤82；本次 PASS={pass_n}",
        "",
        "## FAIL 清单",
        "",
    ]
    if not fails:
        lines.append("（无）")
    else:
        for name in fails:
            lines.append(f"- `{name}`")
    lines.append("")
    Path(args.summary).write_text("\n".join(lines), encoding="utf-8")
    print(f"PASS={pass_n} FAIL={fail_n} TOTAL={len(rows)}")


if __name__ == "__main__":
    main()
