#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""ledger_check.py — 项目总台账机检闸口(lab-qms-engineer)

用法:
    python ledger_check.py 项目总台账.md [--drafts 底稿目录]

检查项(任一 FAIL 即整体 FAIL,退出码 1):
  C0 章节完整性:五个必备节齐全
  C1 状态取值合法(未启动/画像就绪/构建中/机判✅/语义已签/已总装/已发布/运行中)
  C2 状态≥机判✅ 时,机判列必须为 ✅
  C3 状态≥语义已签 时,语义签列必须是"日期/签字人"(待签、-、✅ 均不合格;机判✅冒充人签是重点打击项)
  C4 状态≥已总装 时,手册/汇编装入版均须非 -
  C5 阻塞列不得留空(无阻塞写 -);阻塞非 - 而状态≥已总装 → 带病推进
  C6 未清停点:来源指向的主题状态不得越过 机判✅(停点无权被进度豁免)
  C7 全局阶段=运行期 时,所有主题须≥已发布
  C8 (--drafts)台账登记的底稿版本须在盘上真实存在:要素模板-<主题>-v<版本>.md
仅告警(不判 FAIL):W1 未清停点存在;W2 未决项含"待拍板"。
"""
import argparse
import os
import re
import sys

STATE_ORDER = ["未启动", "画像就绪", "构建中", "机判✅", "语义已签", "已总装", "已发布", "运行中"]
STATE_RANK = {s: i for i, s in enumerate(STATE_ORDER)}
REQUIRED_SECTIONS = ["全局状态", "主题状态表", "停点登记", "未决项汇总", "版本事件日志"]
TOPIC_COLS = ["主题", "底稿版本", "机判", "语义签", "手册装入版", "汇编装入版", "状态", "阻塞"]

fails, warns = [], []


def fail(code, msg):
    fails.append(f"[{code}] {msg}")


def warn(code, msg):
    warns.append(f"[{code}] {msg}")


def split_sections(text):
    """按 ## 标题切分,返回 {标题: 正文}。"""
    sections, cur, buf = {}, None, []
    for line in text.splitlines():
        m = re.match(r"^##\s+(.+?)\s*$", line)
        if m:
            if cur is not None:
                sections[cur] = "\n".join(buf)
            cur, buf = m.group(1).strip(), []
        elif cur is not None:
            buf.append(line)
    if cur is not None:
        sections[cur] = "\n".join(buf)
    return sections


def parse_table(section_text):
    """解析 markdown 表:返回 (表头列表, 行字典列表)。找不到表返回 (None, [])。"""
    lines = [l for l in section_text.splitlines() if l.strip().startswith("|")]
    if len(lines) < 2:
        return None, []
    def cells(line):
        parts = line.strip().strip("|").split("|")
        return [c.strip() for c in parts]
    header = cells(lines[0])
    rows = []
    for l in lines[2:]:  # 跳过分隔行
        c = cells(l)
        if len(c) < len(header):
            c += ["" ] * (len(header) - len(c))
        rows.append(dict(zip(header, c[: len(header)])))
    return header, rows


def strip_comment(v):
    return re.sub(r"<!--.*?-->", "", v).strip()


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("ledger", help="项目总台账.md 路径")
    ap.add_argument("--drafts", help="底稿目录(核对底稿版本是否真实存在)")
    args = ap.parse_args()

    try:
        with open(args.ledger, encoding="utf-8") as f:
            text = f.read()
    except OSError as e:
        print(f"FAIL 无法读取台账:{e}")
        sys.exit(1)

    sections = split_sections(text)

    # C0 章节完整性
    for s in REQUIRED_SECTIONS:
        if s not in sections:
            fail("C0", f"缺必备节:## {s}")

    # 全局阶段
    phase = None
    m = re.search(r"阶段\s*[::]\s*([^\s<]+)", sections.get("全局状态", ""))
    if m:
        phase = strip_comment(m.group(1))
    else:
        fail("C0", "全局状态节缺“阶段:”字段")

    # 主题状态表
    topics = {}
    header, rows = parse_table(sections.get("主题状态表", ""))
    if header is None:
        fail("C0", "主题状态表缺表格")
        rows = []
    else:
        missing = [c for c in TOPIC_COLS if c not in header]
        if missing:
            fail("C0", f"主题状态表缺列(机检接口不得自创):{missing}")
            rows = []

    for r in rows:
        t = r.get("主题", "?")
        topics[t] = r
        state = r.get("状态", "")
        # C1
        if state not in STATE_RANK:
            fail("C1", f"主题「{t}」状态取值非法:“{state}”")
            continue
        rank = STATE_RANK[state]
        # C2
        if rank >= STATE_RANK["机判✅"] and r.get("机判") != "✅":
            fail("C2", f"主题「{t}」状态={state} 但机判列={r.get('机判')!r},疑跳级")
        # C3
        sig = r.get("语义签", "")
        if rank >= STATE_RANK["语义已签"]:
            if not re.match(r"^\d{4}-\d{2}-\d{2}\s*/\s*\S+", sig):
                fail("C3", f"主题「{t}」状态={state} 但语义签列={sig!r}(须为“日期/签字人”;机判✅不得冒充人签)")
        # C4
        if rank >= STATE_RANK["已总装"]:
            for col in ("手册装入版", "汇编装入版"):
                if r.get(col, "-") in ("-", ""):
                    fail("C4", f"主题「{t}」状态={state} 但{col}={r.get(col)!r}")
        # C5
        block = r.get("阻塞", "")
        if block == "":
            fail("C5", f"主题「{t}」阻塞列留空(无阻塞须写 -,空=没检查)")
        elif block != "-" and rank >= STATE_RANK["已总装"]:
            fail("C5", f"主题「{t}」带阻塞“{block}”仍推进到 {state}(带病推进)")
        # C8
        if args.drafts and r.get("底稿版本", "-") not in ("-", ""):
            fname = f"要素模板-{t}-{r['底稿版本']}.md"
            if not os.path.exists(os.path.join(args.drafts, fname)):
                fail("C8", f"台账登记底稿 {fname} 在 {args.drafts} 未找到(悬空版本)")

    # C6 / W1 停点
    _, stop_rows = parse_table(sections.get("停点登记", ""))
    for r in stop_rows:
        if r.get("状态") == "未清":
            warn("W1", f"未清停点 {r.get('编号','?')}:{r.get('描述','')}")
            src = r.get("来源", "")
            mt = re.search(r"/\s*(\S+)", src)
            if mt and mt.group(1) in topics:
                t = mt.group(1)
                st = topics[t].get("状态", "")
                if STATE_RANK.get(st, 0) > STATE_RANK["机判✅"]:
                    fail("C6", f"停点 {r.get('编号','?')} 未清,但来源主题「{t}」已推进到 {st}(停点无权被豁免)")

    # C7 全局阶段
    if phase == "运行期":
        lag = [t for t, r in topics.items()
               if STATE_RANK.get(r.get("状态", ""), -1) < STATE_RANK["已发布"]]
        if lag:
            fail("C7", f"全局阶段=运行期,但以下主题未达已发布:{lag}")
    elif phase not in (None, "构建期", "总装期", "运行期"):
        fail("C1", f"全局阶段取值非法:“{phase}”")

    # W2 未决项
    _, u_rows = parse_table(sections.get("未决项汇总", ""))
    for r in u_rows:
        if "待拍板" in r.get("状态", ""):
            warn("W2", f"未决项 {r.get('编号','?')} 待拍板:{r.get('内容','')}(影响:{r.get('影响主题','')})")

    # 汇总
    print(f"台账:{args.ledger}  主题数:{len(topics)}  全局阶段:{phase}")
    for x in fails:
        print("FAIL", x)
    for x in warns:
        print("WARN", x)
    if fails:
        print(f"\n结论:FAIL({len(fails)} 项)——修复复检前不得路由/播报/交付。")
        sys.exit(1)
    print(f"\n结论:PASS(告警 {len(warns)} 项)。")
    sys.exit(0)


if __name__ == "__main__":
    main()
