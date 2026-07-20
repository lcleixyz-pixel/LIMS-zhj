#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""skeleton_check.py v1.1 — 发布版手册骨架核验(lab-qms-compiler 第四道闸)

用法:
    python skeleton_check.py 发布版手册.md [--facts 事实对照表.md]

检查项(任一 FAIL 整体 FAIL,退出码1):
  S1 残留占位: {{ }}、⟦ ⟧、【…】、[K-、◇、<!-- 在发布版中必须为零
  S2 必备章齐全: 批准发布令/修订页/1 前言/1.1/2.1 质量方针/2.2 质量目标/
     3 手册管理/4~8 各章/附录 —— 标题缺失即 FAIL
  S3 事实一致(需 --facts): 事实对照表(| 槽号 | 值 | 两列markdown表)中每个值
     必须在成品中出现;且第1~3章(概况区)出现的数字串必须能溯源——命中对照表
     值集合,或命中白名单模式(日期/年份/版本号/章节号/文件编号)。
     发现"无源数字"即 FAIL —— 这是红线①:叙事段不许出现 F卡/P表之外的事实。
无 --facts 时 S3 跳过并 WARN(仅限阅读样稿;正式发布必须带对照表)。
"""
import argparse, re, sys

fails, warns = [], []

REQUIRED_HEADINGS = [
    r"批准发布令", r"修订页", r"^#\s*1\s*前言|^#+\s*1[\s..、]*前言", r"1\.1", r"2\.1[\s]*质量方针",
    r"2\.2[\s]*质量目标", r"3[\s..、]*手册管理", r"^#+\s*4[\s..、]", r"^#+\s*5[\s..、]",
    r"^#+\s*6[\s..、]", r"^#+\s*7[\s..、]", r"^#+\s*8[\s..、]", r"附录",
]
RESIDUE = [(r"\{\{", "{{参数槽"), (r"⟦", "⟦骨架槽"), (r"【[^】]{0,30}】", "【占位】"),
           (r"\[K-", "[K-回链"), (r"◇", "◇条件块"), (r"<!--", "<!--注释")]
# 白名单:仅日期/年份/文件编号。版本号、章节号在标题行,由 sec13_text 预先剔除;
# 禁止放行裸小数字——人数、目标值恰恰就是一两位数,放行=阉掉红线①。
WHITELIST = re.compile(
    r"^\d{4}[-年/.]\d{1,2}([-月/.]\d{1,2})?日?$|^(19|20)\d{2}年?$"
    r"|^(QM|CX|QR|JL)[-–]?\d+$"
    r"|^第\d+号$|^总局令第\d+号$")

def sec13_text(t):
    """截取第1~3章(概况区)正文:剔除标题行/表格分隔行(结构行的编号不算事实)。"""
    m1 = re.search(r"^#+\s*1[\s..、]*前言.*$", t, re.M)
    m4 = re.search(r"^#+\s*4[\s..、]", t, re.M)
    sec = t[m1.start():m4.start()] if (m1 and m4) else t
    keep = [l for l in sec.splitlines()
            if not l.lstrip().startswith("#") and not re.match(r"^\s*\|?[-\s|:]+\|?\s*$", l)
            and not re.match(r"^\s*\d+(\.\d+)*[\s..、]", l)          # 行首编号列表行
            and not re.search(r"GB/?T|CNAS[-–]|RB/?T|ISO|IEC|JJF|号公告|总局令|管理办法|监督管理办法", l)  # 依据引用行(标准代号/法规编号非事实)
            and "【" not in l]                                          # 占位行已由S1拦截,不重复报
    body = "\n".join(keep)
    # 剔除章节互引(路标不是事实):见1.5 / 1.6节 / 2.2章 / 1.6所列
    body = re.sub(r"见\s*\d{1,2}(\.\d{1,2})?|\d{1,2}\.\d{1,2}\s*(节|章|条|所列)", "", body)
    body = re.sub(r"[vV]\d+(\.\d+)+|\bP\d+[a-z]?\b", "", body)  # 版本号/参数槽号=元数据非事实
    return body

def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("manual"); ap.add_argument("--facts")
    a = ap.parse_args()
    try:
        t = open(a.manual, encoding="utf-8").read()
    except OSError as e:
        print("FAIL 无法读取:", e); sys.exit(1)

    # S1 残留
    for pat, name in RESIDUE:
        n = len(re.findall(pat, t))
        if n: fails.append(f"[S1] 残留 {name} ×{n}(发布版必须为零)")

    # S2 必备章
    for pat in REQUIRED_HEADINGS:
        if not re.search(pat, t, re.M):
            fails.append(f"[S2] 缺必备章:/{pat}/")

    # S3 事实一致
    if a.facts:
        vals = set()
        try:
            for line in open(a.facts, encoding="utf-8"):
                c = [x.strip() for x in line.strip().strip("|").split("|")]
                if len(c) >= 2 and c[1] and not set(c[1]) <= {"-", " "} and c[0] not in ("槽号", "---"):
                    vals.add(c[1])
        except OSError as e:
            print("FAIL 无法读取事实对照表:", e); sys.exit(1)
        missing = [v for v in vals if v not in t]
        for v in missing:
            fails.append(f"[S3] 对照表值未出现在成品:「{v}」(槽未灌或被改写)")
        sec = sec13_text(t)
        nums = set(re.findall(r"[0-9]{1,6}(?:\.[0-9]{1,4})?", sec))
        valstr = " ".join(vals)
        for n in sorted(nums):
            if WHITELIST.match(n):        # 日期/版本/章节号等
                continue
            if n in valstr:               # 能在事实值里找到来源
                continue
            # 上下文摘录帮助定位
            i = sec.find(n)
            ctx = sec[max(0, i-12):i+len(n)+12].replace("\n", " ")
            fails.append(f"[S3] 概况区无源数字「{n}」:…{ctx}…(红线①:事实须来自F卡/P表)")
    else:
        warns.append("[S3] 未提供 --facts,事实一致性未核(仅限阅读样稿;正式发布必须带对照表)")

    for x in fails: print("FAIL", x)
    for x in warns: print("WARN", x)
    if fails:
        print(f"\n结论:FAIL({len(fails)}项)——修复复检前禁止发布。"); sys.exit(1)
    print(f"\n结论:PASS(告警{len(warns)}项)。"); sys.exit(0)

if __name__ == "__main__":
    main()
