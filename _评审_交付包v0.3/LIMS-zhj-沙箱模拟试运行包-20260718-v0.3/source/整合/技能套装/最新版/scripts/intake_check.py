#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""intake_check.py v1.4(+全员废止扫描,防清单外旧版潜伏) — 依据库入库自检(依据活水系统·机检口)

用法:
    python intake_check.py --dir 依据库目录 --checklist domain-checklists.md \
        [--domain 化学] [--cma-only] [--revocations known-revocations.md] [--json]

检查项:
  I1 覆盖核对: 清单(通用表+领域表)逐行在目录文件名中找 → 缺"必备"=黄,缺"推荐"=提示
  I2 名实一致: 命中的文件读头部内容,代号或名称关键词应出现在内容中;
     过小(<2KB)或无法解码 → 红(空壳/疑损坏);名与内容对不上 → 红(张冠李戴)
  I3 版本时效: 文件名/内容中的年份 vs 清单"现行版本年":
     文件年 < 现行年 → 红(疑过时,提示官方自取);文件年 > 现行年 → 黄(比清单新,清单待更新——如实两面报)
  I4 废止对照: 文件名命中 known-revocations 中的废止代号 → 红
退出码: 0=全绿  1=有黄  2=有红(红优先)。--json 输出机读结果。
只核对、只提醒,不代下载。
"""
import argparse, json, os, re, sys

def norm(s):
    return re.sub(r"[\s\-_/、::·..《》()()]+", "", s).lower()

def parse_tables(text, wanted_headings):
    """取 '## 机读表·xxx' 节下的 | 代号 | 名称关键词 | 现行版本年 | 级别 | 表。"""
    rows = []
    for h in wanted_headings:
        m = re.search(rf"^##\s*机读表·{re.escape(h)}.*?$", text, re.M)
        if not m:
            continue
        seg = text[m.end():]
        nxt = re.search(r"^##\s", seg, re.M)
        seg = seg[:nxt.start()] if nxt else seg
        for line in seg.splitlines():
            c = [x.strip() for x in line.strip().strip("|").split("|")]
            if len(c) >= 4 and c[0] not in ("代号", "") and not set(c[0]) <= {"-"}:
                rows.append({"code": c[0], "kw": c[1], "year": c[2], "level": c[3]})
    return rows

def file_year(name, content):
    ys = re.findall(r"(19|20)\d{2}", name)
    if ys:
        m = re.search(r"((?:19|20)\d{2})", name)
        return m.group(1)
    m = re.search(r"((?:19|20)\d{2})", content[:2000])
    return m.group(1) if m else None

def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--dir")
    ap.add_argument("--list", dest="listfile", help="文件名清单txt(每行一个文件名;仅做I1覆盖/I3文件名时效/I4废止,I2名实核验跳过并如实声明)")
    ap.add_argument("--checklist", required=True)
    ap.add_argument("--domain", default=None)
    ap.add_argument("--cma-only", action="store_true")
    ap.add_argument("--revocations", default=None)
    ap.add_argument("--json", action="store_true")
    a = ap.parse_args()

    cl = open(a.checklist, encoding="utf-8").read()
    headings = ["通用"] + ([f"领域·{a.domain}"] if a.domain else [])
    rows = parse_tables(cl, headings)
    if a.domain and not any(True for _ in rows[14:]):  # 领域表可能未命中
        pass
    if not rows:
        print("FAIL 清单机读表未解析到任何行,检查 --checklist/--domain"); sys.exit(2)
    if a.cma_only:
        rows = [r for r in rows if not r["code"].upper().startswith("CNAS")]

    if a.listfile:
        files = [l.strip() for l in open(a.listfile, encoding="utf-8") if l.strip()]
    elif a.dir:
        files = [f for f in os.listdir(a.dir)
                 if os.path.isfile(os.path.join(a.dir, f)) and not f.startswith(".")]
    else:
        print("FAIL 需提供 --dir 或 --list"); sys.exit(2)
    nfiles = {f: norm(f) for f in files}

    revo = []
    if a.revocations and os.path.exists(a.revocations):
        # known-revocations 是表格:| 旧依据 | 事件 | 替代依据 | ... —— 只取第一列(旧依据)
        revo = []
        for line in open(a.revocations, encoding="utf-8"):
            cells = [x.strip() for x in line.strip().strip("|").split("|")]
            if len(cells) >= 3 and cells[0] not in ("旧依据", "") and not set(cells[0]) <= {"-"}:
                m = re.search(r"(CNAS-[A-Z0-9\-]+|RB/?T\s?\d+[--]?\d*)[::\s]*((?:19|20)\d{2})?", cells[0])
                if m:
                    revo.append((m.group(1), m.group(2)))

    green, yellow, red = [], [], []
    pool = dict(nfiles)  # 一件只配一行
    # 两轮认领:第一轮只认代号(长代号优先,防前缀撞车);第二轮剩余行才用名称关键词
    claims = {}
    for r in sorted(rows, key=lambda x: -len(norm(x["code"]))):
        code_n = norm(r["code"])
        hit = next((f for f, fn in pool.items() if code_n in fn), None)
        if hit:
            claims[id(r)] = hit; pool.pop(hit)
    for r in sorted(rows, key=lambda x: -len(norm(x["kw"]))):
        if id(r) in claims: continue
        kw_n = norm(r["kw"])
        hit = next((f for f, fn in pool.items() if kw_n in fn), None)
        if hit:
            claims[id(r)] = hit; pool.pop(hit)
    for r in rows:
        code_n, kw_n = norm(r["code"]), norm(r["kw"])
        hit = claims.get(id(r))
        if not hit:
            (yellow if r["level"] == "必备" else green).append(
                {"级": "黄" if r["level"] == "必备" else "提示",
                 "项": r["code"], "情况": f"缺件({r['level']}),请到官方渠道自行获取{r['year']}版上传"})
            continue
        if a.listfile:
            # 清单模式:无文件本体,I2跳过(报告尾部统一声明)
            fy = file_year(hit, "")
            if fy and r["year"].isdigit():
                if int(fy) < int(r["year"]):
                    red.append({"级": "红", "项": hit, "情况": f"疑过时:文件名为{fy}版,清单现行{r['year']}版——请到官方渠道自行获取现行版上传"}); continue
                if int(fy) > int(r["year"]):
                    yellow.append({"级": "黄", "项": hit, "情况": f"文件({fy})比清单({r['year']})更新:以官方为准,同时请更新清单库"}); continue
            fy2 = file_year(hit, "")
            if any(norm(c) in nfiles[hit] and y and fy2 == y for c, y in revo):
                red.append({"级": "红", "项": hit, "情况": "该版本已废止,请获取替代版本"}); continue
            green.append({"级": "绿", "项": hit, "情况": f"就位({r['code']},名实未核)"}); continue
        p = os.path.join(a.dir, hit)
        if hit.lower().endswith(".pdf"):
            yellow.append({"级": "黄", "项": hit, "情况": f"PDF无法自动文本核验名实({r['code']}),请人工抽查首页"}); continue
        try:
            raw = open(p, "rb").read()
            if len(raw) < 2048:
                red.append({"级": "红", "项": hit, "情况": "文件过小(<2KB),疑空壳/损坏"}); continue
            content = raw[:60000].decode("utf-8", "ignore")
        except OSError:
            red.append({"级": "红", "项": hit, "情况": "无法读取"}); continue
        # I2 名实一致:代号或关键词需出现在内容里
        cn = norm(content)
        if code_n not in cn and kw_n not in cn:
            red.append({"级": "红", "项": hit,
                        "情况": f"名实不符:内容中未见「{r['code']}」或「{r['kw']}」,疑张冠李戴"}); continue
        # I3 时效
        fy = file_year(hit, content)
        if fy and r["year"].isdigit():
            if int(fy) < int(r["year"]):
                red.append({"级": "红", "项": hit,
                            "情况": f"疑过时:文件为{fy}版,清单现行{r['year']}版——请到官方渠道自行获取现行版上传"}); continue
            if int(fy) > int(r["year"]):
                yellow.append({"级": "黄", "项": hit,
                               "情况": f"文件({fy})比清单({r['year']})更新:以官方为准,同时请更新清单库"}); continue
        # I4 废止
        if any(norm(c) in nfiles[hit] and y and (file_year(hit, content) == y) for c, y in revo):
            red.append({"级": "红", "项": hit, "情况": "该版本已废止,请获取替代版本"}); continue
        green.append({"级": "绿", "项": hit, "情况": f"就位({r['code']})"})

    # 全员废止扫描:未被清单认领的文件同样不许是已废止版本(防旧版潜伏)
    for f in pool:
        fy = file_year(f, "")
        if any(norm(c) in nfiles[f] and y and fy == y for c, y in revo):
            red.append({"级": "红", "项": f, "情况": "该版本已废止(清单外潜伏旧版),请获取替代版本并移出依据库"})

    out = {"绿": len([g for g in green if g["级"] == "绿"]),
           "黄": len(yellow), "红": len(red),
           "明细": red + yellow + green}
    if a.json:
        print(json.dumps(out, ensure_ascii=False, indent=1))
    else:
        for x in red + yellow:
            print(f"[{x['级']}] {x['项']}:{x['情况']}")
        if a.listfile:
            print("[声明] 清单模式:仅核对文件名(覆盖/时效/废止),名实一致未核验——需文件本体或人工抽查。")
        print(f"\n结论:绿{out['绿']} 黄{out['黄']} 红{out['红']}"
              +("——存在红项,处置前该依据不得作为现行有效使用" if red else ""))
    sys.exit(2 if red else (1 if yellow else 0))

if __name__ == "__main__":
    main()
