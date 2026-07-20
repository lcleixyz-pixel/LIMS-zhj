#!/usr/bin/env python3
"""从抓取的 HTML 提取正文文本摘要（剔除 ThinkPHP trace 与导航），每角色每页一段。"""
import re, html, os, sys

BASE = os.path.dirname(os.path.abspath(__file__))
ROLES = ["admin", "qm_test", "auditor_test", "head_test", "staff_test"]

def page_text(path):
    t = open(path, encoding="utf-8", errors="replace").read()
    # 剔除 ThinkPHP trace 面板
    t = re.split(r'<div id="think_page_trace"', t)[0]
    t = re.sub(r"<script.*?</script>", "", t, flags=re.S)
    t = re.sub(r"<style.*?</style>", "", t, flags=re.S)
    # 分离导航与主体
    m = re.search(r"<main.*?>(.*)", t, flags=re.S)
    body = m.group(1) if m else t
    nav = t[: m.start()] if m else ""
    def clean(x):
        x = re.sub(r"<[^>]+>", "\n", x)
        x = html.unescape(x)
        lines = [re.sub(r"\s+", " ", l).strip() for l in x.split("\n")]
        return [l for l in lines if l]
    return clean(nav), clean(body)

def main():
    for role in ROLES:
        d = os.path.join(BASE, role)
        if not os.path.isdir(d):
            continue
        out = []
        for f in sorted(os.listdir(d)):
            if not f.endswith(".html"):
                continue
            nav, body = page_text(os.path.join(d, f))
            out.append(f"##### {f}\nNAV: {' | '.join(nav)}\nBODY: {' | '.join(body)}\n")
        open(os.path.join(BASE, f"digest_{role}.txt"), "w", encoding="utf-8").write("\n".join(out))
        print(role, "done")

if __name__ == "__main__":
    main()
