#!/usr/bin/env python3
# manual_check.py v1.3 — 总装闸口:①手册每条回链须在主题底稿中真实存在(防幻觉) ②参数点格式 ③章节骨架完整性(CL01 4~8)
import re, sys, glob, os

try:
    from cli_utils import safe_read, standard_argparse, build_result, output
    HAS_UTILS = True
except ImportError:
    HAS_UTILS = False
    def safe_read(p): return open(p, encoding='utf-8').read()

CL01_SKELETON = ['4.1','4.2','## 5 ','6.1','6.2','6.3','6.4','6.5','6.6'] \
    + [f'7.{i}' for i in range(1,12)] + [f'8.{i}' for i in range(1,10)]

def main():
    if HAS_UTILS:
        p = standard_argparse('总装闸口:防幻觉+参数点格式+章节骨架')
        p.add_argument('manual', help='手册工作版.md')
        p.add_argument('theme_dir', nargs='?', default=None, help='底稿目录(默认同手册目录)')
        p.add_argument('--vol2', action='store_true', help='汇编模式(跳过章节骨架检查)')
        args = p.parse_args()
        manual_path = args.manual
        theme_dir = args.theme_dir or os.path.dirname(manual_path)
        is_vol2 = args.vol2
        use_json = args.json
    else:
        if len(sys.argv) < 2:
            print('用法: python manual_check.py [--json] 手册.md [底稿目录] [--vol2]', file=sys.stderr)
            sys.exit(2)
        use_json = '--json' in sys.argv
        sargs = [a for a in sys.argv[1:] if a != '--json']
        manual_path = sargs[0]
        theme_dir = sargs[1] if len(sargs) > 1 else os.path.dirname(manual_path)
        is_vol2 = '--vol2' in sys.argv

    manual = safe_read(manual_path)

    # 底稿卡片全集
    corpus = ''
    draft_count = 0
    for f in glob.glob(os.path.join(theme_dir, '要素模板-*.md')):
        try:
            corpus += safe_read(f)
            draft_count += 1
        except SystemExit:
            continue

    defined = set(re.findall(r'\b([KFGXIP]-[A-Za-z]?\d+[a-z]?)\b', corpus))
    errors, warns = [], []

    # ① 回链核验
    refs = set()
    for grp in re.findall(r'\[([^\]]+)\]', manual):
        for tok in re.split(r'[,,、]\s*', grp):
            tok = tok.strip()
            if re.fullmatch(r'[KFGXIP]-[A-Za-z]?\d+[a-z]?', tok):
                refs.add(tok)
    for r in sorted(refs):
        if r not in defined:
            errors.append(f'幻觉回链: [{r}] 在任何主题底稿中不存在')

    # ② 参数点格式
    for m in re.finditer(r'\{\{(?!P\d+:)[^}]*\}\}', manual):
        errors.append(f'参数点格式错误: {m.group(0)[:40]}')

    # ③ 章节骨架(--vol2 跳过)
    if not is_vol2:
        for h in CL01_SKELETON:
            pat = h if h.startswith('##') else rf'#+\s*{re.escape(h)}\b'
            if not re.search(pat, manual):
                errors.append(f'章节缺失: {h}')

    # ④ 条件块配对提示
    pairs = set(re.findall(r'◇(C\d+)-', manual))
    for c in pairs:
        sides = set(re.findall(rf'◇{c}-([AB])', manual))
        if len(sides) == 1:
            warns.append(f'条件块 {c} 仅有 {sides} 一侧(如属单侧设计请忽略)')

    extra = {
        'ref_count': len(refs),
        'defined_count': len(defined),
        'drafts_scanned': draft_count,
        'vol2_mode': is_vol2,
        'hallucinated_refs': sorted([r for r in refs if r not in defined]) if any(r not in defined for r in refs) else []
    }

    result = build_result('manual_check', '1.3', errors=errors, warnings=warns, extra=extra)
    output(result, use_json)

if __name__ == '__main__':
    try:
        main()
    except Exception as e:
        print(f'❌ 脚本异常: {e}', file=sys.stderr)
        sys.exit(2)
