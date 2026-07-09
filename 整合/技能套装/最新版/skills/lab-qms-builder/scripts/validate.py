#!/usr/bin/env python3
# validate.py v1.4 — 要素模板质检闸口(lab-qms-builder 工具层)
# v1.4 新增: H点名册遍历闭合等式校验(源文条款数=点名册行数)
# 用法: python validate.py [--json] <要素模板.md>
# 检查: A章节完整性 B悬空回链 C无落点卡 D待核混入正文 E闭合声明标注 F K卡字段 G PDCA H 遍历闭合等式
import re, sys, os

try:
    # 从 builder 目录往上游找到共享 scripts/
    _d = os.path.dirname(os.path.abspath(__file__))
    sys.path.insert(0, os.path.normpath(os.path.join(_d, '..', '..', '..', 'scripts')))
    from cli_utils import safe_read, standard_argparse, build_result, output
    HAS_UTILS = True
except ImportError:
    HAS_UTILS = False
    def safe_read(p): return open(p, encoding='utf-8').read()

REQUIRED = ["书架","点名","卡片","连线","合并条款","PDCA|程序","接口","指针","对账","世系|待办","记录模板|记录表格","自审"]

def main():
    if HAS_UTILS:
        p = standard_argparse('要素模板句法层质检闸口')
        p.add_argument('template', help='要素模板-<主题>-vX.Y.md')
        args = p.parse_args()
        path = args.template
        use_json = args.json
    else:
        if len(sys.argv) < 2:
            print('用法: python validate.py [--json] <要素模板.md>', file=sys.stderr)
            sys.exit(2)
        use_json = '--json' in sys.argv
        sargs = [a for a in sys.argv[1:] if a != '--json']
        path = sargs[0]

    t = safe_read(path)
    secs = re.split(r"\n## ", t)
    findings, warns = [], []
    k_incomplete = 0
    steps_found = set()

    # A 章节完整性
    for req in REQUIRED:
        pat = req.split("|")
        if not any(any(p in s.splitlines()[0] for p in pat) for s in secs[1:]):
            if any(p in t and "不适用" in t for p in pat):
                warns.append(f"章节[{pat[0]}]以'不适用'豁免,请人工确认理由")
            else:
                findings.append(f"缺章节: {req}")

    def sec(*keys):
        return "\n".join(s for s in secs[1:] if any(k in s.splitlines()[0] for k in keys))

    cards_zone = sec("卡片","接口","指针","世系")
    prose_zone = sec("合并条款","PDCA","程序","记录模板","记录表格","接口")

    # 卡片定义
    defined = set(re.findall(r"\*\*([KGIPXF]-[A-Za-z0-9]+)\*\*", cards_zone))
    defined |= set(re.findall(r"^\s*[-|]\s*([KGIPXF]-[A-Za-z0-9]+)", cards_zone, re.M))

    # 正文引用
    refs = set()
    for grp in re.findall(r"\[([^\]\[]{1,120})\]", prose_zone):
        if not re.search(r"[KGIPXF]-|^[A-Z]?\d", grp):
            continue
        for tok in re.split(r"[,、;\s]+", grp):
            tok = tok.strip()
            if not tok:
                continue
            if re.fullmatch(r"[KGIPXF]-[A-Za-z0-9]+", tok):
                refs.add(tok)
            elif re.fullmatch(r"[A-Za-z]?\d+[a-z]?", tok):
                for pre in ("K-","K-E","G-","G-E"):
                    if pre+tok in defined:
                        refs.add(pre+tok); break
                else:
                    refs.add("?"+tok)

    # B 悬空回链
    for r in sorted(refs):
        if r.startswith("?"):
            findings.append(f"回链无法解析: [{r[1:]}] (缩写无匹配定义)")
        elif r not in defined:
            findings.append(f"悬空回链: [{r}] 正文引用但卡片区未定义")

    # C 无落点卡
    for d in sorted(x for x in defined if x.startswith("K-")):
        if d not in refs and d not in prose_zone:
            findings.append(f"无落点卡: {d} 已定义但正文/接口无引用(如属不适用须显式标注)")

    # D 准入门
    for zone_name in ("合并条款",):
        z = sec(zone_name)
        if "待核" in z:
            findings.append(f"准入门违规: '{zone_name}'区出现'待核'内容")

    # E 闭合声明
    census = sec("点名")
    if census and not re.search(r"(遍历闭合|窗证闭合)", census):
        warns.append("点名册未使用二级闭合标注(遍历闭合/窗证闭合)——v1.1新规,旧文件请复核")

    # F K卡字段完整性(v1.4 新增)
    # K卡格式: K-nn | 来源(代号+条款号) | 要求文本 | 文本性质 | 适用性 | 强制度 | 效力
    K_FIELDS = 7
    k_card_lines = re.findall(r'^\s*[-|]\s*(K-\d+)\b.*$', cards_zone, re.M)
    k_card_texts = re.split(r'\n(?=\s*[-|]\s*K-\d+\b)', cards_zone, flags=re.M)
    k_incomplete = 0
    for card_text in k_card_texts:
        k_id_match = re.match(r'\s*[-|]\s*(K-\d+)', card_text)
        if not k_id_match:
            continue
        k_id = k_id_match.group(1)
        # 计算管道分隔的字段数(表格式定义)
        pipe_count = card_text.count('|')
        if pipe_count < K_FIELDS:
            k_incomplete += 1
            if k_incomplete <= 5:
                findings.append(f"K卡字段不全: {k_id} 仅{pipe_count}个|分隔符(预期≥{K_FIELDS},7字段=来源/文本/文本性质/适用性/强制度/效力/备注)")
    if k_incomplete > 5:
        findings.append(f"K卡字段不全: 另有{k_incomplete-5}张卡同样缺字段(详见逐卡自查)")

    # G PDCA四步记录完整性(v1.4 新增)
    pdca_zone = sec("PDCA", "程序")
    if pdca_zone:
        # 查找 PDCA 表格行(跳表头和分隔行)
        pdca_rows = re.findall(r'^\|.*\|$', pdca_zone, re.M)
        for row in pdca_rows:
            # P/D/C/A 通常在表格第一列
            m = re.match(r'\|\s*([PDCA])\b', row)
            if m:
                steps_found.add(m.group(1))
        for step in ['P', 'D', 'C', 'A']:
            if step not in steps_found:
                findings.append(f"PDCA缺步: 缺少'{step}'步骤(四步各≥1条记录)")
        if len(pdca_rows) < 6:  # 表头+分隔+4步
            warns.append("PDCA记录行数偏少,每步至少应有1条记录")
    else:
        warns.append("未找到PDCA/程序区域(如本主题不涉及程序文件请显式标注'不适用')")

    # H 点名册遍历闭合等式校验(v1.4 新增)
    # 检查:源文条款数=点名册行数,闭合标注正确
    roster = sec("点名")
    if roster:
        # 统计点名册表格行(排除表头和分隔行)
        roster_rows = [l for l in roster.splitlines() if l.strip().startswith('|') and not re.match(r'^\|[-:\s|]+\|$', l)]
        # 排除表头(第一行)
        data_rows = roster_rows[1:] if roster_rows else []
        row_count = len(data_rows)

        # 查找闭合声明: "遍历闭合 N=M" 或 "窗证闭合"
        trav_match = re.search(r'遍历闭合\s*[=:：]\s*(\d+)\s*[=＝]\s*(\d+)', roster)
        win_match = re.search(r'窗证闭合', roster)

        if trav_match:
            declared_src = int(trav_match.group(1))
            declared_rows = int(trav_match.group(2))
            if declared_src != declared_rows:
                findings.append(f"遍历闭合等式不成立:声明{declared_src}={declared_rows},但点名册数据行={row_count}")
            if declared_rows != row_count:
                findings.append(f"遍历闭合行数不符:声明{declared_rows}行,实际点名册数据行={row_count}行")
        elif win_match:
            # 窗证闭合:只检查有标注,不强制等式
            pass
        else:
            warns.append(f"点名册未声明闭合类型(遍历闭合/窗证闭合)——当前{row_count}行数据,建议标注'遍历闭合 X=Y'或'窗证闭合(待复核)'")
    else:
        warns.append("未找到点名册区域——builder须含点名册(§2),缺此节无法验证普查完整性")

    extra = {
        'defined_cards': len(defined),
        'resolved_refs': len([r for r in refs if not r.startswith('?')]),
        'unresolved_refs': len([r for r in refs if r.startswith('?')]),
        'k_cards_incomplete': k_incomplete,
        'pdca_steps_found': len(steps_found),
        'file': os.path.basename(path),
        'note': '本检查仅句法层机判;语义正确性仍须人工时间分离复核'
    }

    result = build_result('validate', '1.4', errors=findings, warnings=warns, extra=extra)
    output(result, use_json)

if __name__ == "__main__":
    try:
        main()
    except Exception as e:
        print(f'❌ 脚本异常: {e}', file=sys.stderr)
        sys.exit(2)
