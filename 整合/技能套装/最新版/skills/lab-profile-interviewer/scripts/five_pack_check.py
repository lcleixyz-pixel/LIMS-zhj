#!/usr/bin/env python3
# five_pack_check.py v1.1 — interviewer 五件套完整性检查
# v1.1 改进:C表检测三遍扫描(简式+表格行粗体+机器可读行),兼容markdown表格格式C表
# 检查: ①P表 ②C表 ③决策留痕 ④F卡替换包 ⑤未决项清单
# 用法: python five_pack_check.py [--json] <五件套目录或P表文件>
import re, sys, os, glob

try:
    _d = os.path.dirname(os.path.abspath(__file__))
    sys.path.insert(0, os.path.normpath(os.path.join(_d, '..', '..', '..', 'scripts')))
    from cli_utils import safe_read, standard_argparse, build_result, output
    HAS_UTILS = True
except ImportError:
    HAS_UTILS = False
    def safe_read(p): return open(p, encoding='utf-8').read()

# ── 检查函数 ──────────────────────────────────────────────────────
def check_P_table(text):
    """检查 P 表:每个 {{Pxx}} 有值或显式'待拍板'"""
    errs, warns = [], []
    params = re.findall(r'\{\{P(\d+)\}\}', text)
    filled = re.findall(r'\{\{P\d+:([^}]*)\}\}', text)
    if not params and not filled:
        errs.append('P表:未找到任何 {{Pxx}} 参数定义')
    for m in re.finditer(r'\{\{P(\d+)\}\}(?!:)', text):
        errs.append(f'P表:{{{{P{m.group(1)}}}}} 未填值(格式应为 {{{{P{m.group(1)}:值}}}} 或标"待拍板")')
    return errs, warns

def check_C_table(text):
    """检查 C 表:C1~C9 每个有取向+翻转影响。支持两种格式:
       ① 简式: ◇C1-A 或 C1-B
       ② 表格: | C1 | ... | A:xxx / **B:xxx** | ..."""
    errs, warns = [], []
    defined = {}

    # 先找所有C编号
    all_c = set(int(m) for m in re.findall(r'C(\d+)', text))

    # 第1遍:查找简式格式 ◇C1-A 或 C1: B
    for m in re.finditer(r'◇?C(\d+)[-:\s]+([AB])', text):
        c_num = int(m.group(1))
        side = m.group(2)
        if c_num not in defined:
            defined[c_num] = side

    # 第2遍:查找表格行格式 | C1 | ... | **B:**xxx 或 A:xxx
    for row in re.findall(r'\|[^|]*C(\d+)[^|]*\|.*\|', text):
        c_num = int(row.split('|')[0].replace('C', '').strip())
        if c_num in defined:
            continue
        # 在行中查找粗体取向标记 **B:** 或 **A:**
        bold_side = re.search(r'\*\*([AB])\*\*:', row)
        plain_side = re.search(r'(?<!\*\*)([AB]):(?!\*\*)', row)
        if bold_side:
            defined[c_num] = bold_side.group(1)
        elif plain_side:
            # "A:xxx" 格式 — 取第一个匹配的
            defined[c_num] = plain_side.group(1)

    # 第3遍:查找机器可读行 C1-B | C2-A | ...
    decl = re.search(r'C\d+-[AB](?:\s*\|\s*C\d+-[AB])*', text)
    if decl:
        for m in re.finditer(r'C(\d+)-([AB])', decl.group()):
            c_num = int(m.group(1))
            if c_num not in defined:
                defined[c_num] = m.group(2)

    # 报告:未定义的和未标取向的
    for i in range(1, 10):
        if i not in defined:
            if i in all_c:
                warns.append(f'C表:C{i} 已提及但无法识别取向(A/B)——请添加 ◇C{i}-A 或 **B:** 标记')
            else:
                warns.append(f'C表:C{i} 未在五件套中定义(如不适用请显式标注)')

    return errs, warns

def check_decision_log(text):
    """检查决策留痕:评估-建议-拍板三栏"""
    errs, warns = [], []
    # 查找决策留痕区域
    dm = re.search(r'(?:决策留痕|决策记录|评估意见)', text)
    if not dm:
        errs.append('决策留痕:未找到决策留痕区域(需要"评估-建议-拍板"三栏)')
        return errs, warns
    after = text[dm.start():dm.end()+2000] if dm.end()+2000 < len(text) else text[dm.start():]
    checks = ['评估', '建议', '拍板']
    for chk in checks:
        if chk not in after:
            errs.append(f'决策留痕:缺少"{chk}"栏')
    return errs, warns

def check_F_cards(text):
    """检查 F 卡替换包:编号对齐 F-01~F-30 或 F-31+"""
    errs, warns = [], []
    f_ids = set()
    for m in re.finditer(r'\*\*F-(\d+)\*\*|F-(\d+)', text):
        num = int(m.group(1) or m.group(2))
        f_ids.add(num)
    if not f_ids:
        errs.append('F卡替换包:未找到任何 F-xx 编号的卡片定义')
        return errs, warns
    # 检查编号连续性
    max_id = max(f_ids)
    gaps = sorted(set(range(1, max_id+1)) - f_ids)
    if gaps and max(gaps) <= 30:
        warns.append(f'F卡:F-{",F-".join(str(g) for g in gaps[:5])} 未定义(如属有意跳过请标注)')
    # 超出 F-30 的检查
    over_30 = [i for i in f_ids if i > 30]
    if over_30:
        warns.append(f'F卡:编号超过F-30({sorted(over_30)}),确认已按新增卡规则(F-31起)登记到F卡索引模板')
    return errs, warns

def check_open_items(text):
    """检查未决项清单:每项注明影响"""
    errs, warns = [], []
    oi = re.search(r'未决项', text)
    if not oi:
        errs.append('未决项清单:未找到"未决项"区域(即使为零也应显式声明"无未决项")')
        return errs, warns
    after = text[oi.start():oi.start()+3000]
    items = re.findall(r'[-*]\s+(.+)', after)
    if not items:
        warns.append('未决项清单:区域存在但无条目(应写"无未决项"或逐条列出)')
    else:
        for item in items[:10]:
            if len(item) < 10:
                warns.append(f'未决项过于简略: {item[:40]}')
            if not re.search(r'(影响|条款|主题|章节|要素)', item):
                warns.append(f'未决项未注明影响范围: {item[:40]}')
    return errs, warns


def main():
    if HAS_UTILS:
        p = standard_argparse('interviewer 五件套完整性检查')
        p.add_argument('path', help='五件套所在目录(或单个P表文件)')
        args = p.parse_args()
        path, use_json = args.path, args.json
    else:
        if len(sys.argv) < 2:
            print('用法: python five_pack_check.py [--json] <五件套目录>', file=sys.stderr)
            sys.exit(2)
        use_json = '--json' in sys.argv
        sargs = [a for a in sys.argv[1:] if a != '--json']
        path = sargs[0]

    # 收集文件
    files = {}
    if os.path.isfile(path):
        files['all'] = path
    elif os.path.isdir(path):
        for fname in os.listdir(path):
            if fname.endswith('.md'):
                key = fname.split('/')[-1].replace('.md','')
                files[key] = os.path.join(path, fname)
    else:
        print(f'❌ 路径不存在: {path}', file=sys.stderr); sys.exit(2)

    # 合并所有文本 或 按文件分别检查
    all_text = ''
    for k, fp in files.items():
        try:
            all_text += f'\n## _{k}_\n' + safe_read(fp)
        except SystemExit:
            continue

    if not all_text.strip():
        all_errors = ['未找到可读取的 .md 文件']
        result = build_result('five_pack_check', '1.0', errors=all_errors)
        output(result, use_json)
        return

    # 逐项检查
    all_errors, all_warns = [], []

    for name, fn in [
        ('P表', check_P_table), ('C表', check_C_table),
        ('决策留痕', check_decision_log), ('F卡替换包', check_F_cards),
        ('未决项清单', check_open_items)
    ]:
        errs, warns = fn(all_text)
        for e in errs: all_errors.append(f'{name}: {e}')
        for w in warns: all_warns.append(f'{name}: {w}')

    extra = {
        'files_scanned': len(files),
        'file_list': list(files.keys()),
        'checks': {
            'P_params_found': len(re.findall(r'\{\{P\d+', all_text)),
            'C_switches_found': len(set(re.findall(r'C(\d+)', all_text))),
            'F_cards_found': len(set(int(m.group(1)) for m in re.finditer(r'F-(\d+)', all_text))),
            'decision_log_found': bool(re.search(r'决策留痕|决策记录|评估意见', all_text)),
            'open_items_found': bool(re.search(r'未决项', all_text))
        }
    }

    result = build_result('five_pack_check', '1.0', errors=all_errors, warnings=all_warns, extra=extra)
    output(result, use_json)


if __name__ == '__main__':
    try:
        main()
    except Exception as e:
        print(f'❌ 脚本异常: {e}', file=sys.stderr)
        sys.exit(2)
