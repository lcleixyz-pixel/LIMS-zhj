#!/usr/bin/env python3
# coverage_check.py v1.3 — 反向核验(防漏装):底稿§5合并条款用到的全部回链,是否都进了两册成品
# 用法: python coverage_check.py [--json] 手册.md 汇编.md 底稿1.md 底稿2.md ...
import re, sys, os

# ── 尝试加载 cli_utils ────────────────────────────────────────────
try:
    from cli_utils import safe_read, standard_argparse, build_result, output
    HAS_UTILS = True
except ImportError:
    HAS_UTILS = False
    def safe_read(p): return open(p, encoding='utf-8').read()

def links(text):
    out = set()
    for grp in re.findall(r'\[([^\]]+)\]', text):
        for tok in re.split(r'[,,、]\s*', grp):
            tok = tok.strip()
            if re.fullmatch(r'[KFGXIP]-[A-Za-z]?\d+[a-z]?', tok):
                out.add(tok)
    return out

def main():
    if HAS_UTILS:
        p = standard_argparse('反向核验底稿§5回链是否全部进入两册成品')
        p.add_argument('vol1', help='手册工作版.md')
        p.add_argument('vol2', help='汇编工作版.md')
        p.add_argument('drafts', nargs='+', help='底稿文件列表')
        args = p.parse_args()
        vol1_path, vol2_path, draft_paths = args.vol1, args.vol2, args.drafts
        use_json = args.json
    else:
        if len(sys.argv) < 4:
            print('用法: python coverage_check.py [--json] 手册.md 汇编.md 底稿1.md ...', file=sys.stderr)
            sys.exit(2)
        use_json = '--json' in sys.argv
        sargs = [a for a in sys.argv[1:] if a != '--json']
        vol1_path, vol2_path = sargs[0], sargs[1]
        draft_paths = sargs[2:]

    vol = links(safe_read(vol1_path)) | links(safe_read(vol2_path))
    miss = {}
    warns = []

    for fpath in draft_paths:
        try:
            t = safe_read(fpath)
        except SystemExit:
            warns.append(f'文件跳过: {fpath}')
            continue
        m = re.search(r'^## 5[\s\S]*?(?=^## 6)', t, re.M)
        if not m:
            warns.append(f'{os.path.basename(fpath)} 未找到§5节')
            continue
        for k in sorted(links(m.group(0)) - vol):
            miss.setdefault(k, []).append(os.path.basename(fpath))

    errors = []
    for k, fs in sorted(miss.items()):
        errors.append(f'漏装: {k} ← {fs[0]}')

    extra = {
        'vol_link_count': len(vol),
        'missing_count': len(miss),
        'missing_details': {k: fs for k, fs in miss.items()},
        'hint': '逐项判:真漏/条件件/已合并表述' if miss else None
    }

    result = build_result('coverage_check', '1.3', errors=errors, warnings=warns, extra=extra)
    output(result, use_json)

if __name__ == '__main__':
    try:
        main()
    except Exception as e:
        print(f'❌ 脚本异常: {e}', file=sys.stderr)
        sys.exit(2)
