#!/usr/bin/env python3
# assemble_strip.py v1.3 — 发布版生成器:剥回链[K-xx]/[F-xx]/[G-xx]、参数点{{Pxx:值}}→值、条件块删未选侧
# 用法: python assemble_strip.py [--json] 工作版.md 发布版.md [--check]
import re, sys, os
from collections import defaultdict

try:
    from cli_utils import safe_read, standard_argparse, build_result, output
    HAS_UTILS = True
except ImportError:
    HAS_UTILS = False
    def safe_read(p): return open(p, encoding='utf-8').read()

FORBIDDEN_WORDS = ['工作版','占位','【虚构】','待拍板','演练稿','阅读样稿','样例草拟']

def strip(text):
    text = re.sub(r'<!--WV-->[\s\S]*?<!--/WV-->\n?', '', text)
    text = re.sub(r'\s*\[[KFGXIP]-[A-Za-z]?\d+[^\]]*\]', '', text)
    text = re.sub(r'\{\{P\d+:([^}]*)\}\}', r'\1', text)
    text = re.sub(r'^◇C\d+-[AB][^\n]*\n', '', text, flags=re.M)
    return text

def check(text):
    errs = []
    for w in FORBIDDEN_WORDS:
        if w in text:
            errs.append(f'发布拒发词残留: {w}')
    for m in re.finditer(r'\{\{(?!P\d+:)[^}]*\}\}', text):
        errs.append(f'参数点格式错误: {m.group(0)[:40]}')
    ids = re.findall(r'◇(C\d+)-([AB])', text)
    d = defaultdict(set)
    for c, s in ids: d[c].add(s)
    for c, ss in d.items():
        if len(ss) > 1:
            errs.append(f'条件块 {c} 的 A/B 两侧并存,未完成取舍')
    return errs

def main():
    if HAS_UTILS:
        p = standard_argparse('发布版生成器:剥离回链和参数标记')
        p.add_argument('src', help='工作版.md')
        p.add_argument('dst', help='发布版.md(输出路径)')
        p.add_argument('--check', action='store_true', help='仅检查,不生成发布版')
        args = p.parse_args()
        src_path, dst_path = args.src, args.dst
        check_only = args.check
        use_json = args.json
    else:
        if len(sys.argv) < 3:
            print('用法: python assemble_strip.py [--json] 工作版.md 发布版.md [--check]', file=sys.stderr)
            sys.exit(2)
        use_json = '--json' in sys.argv
        sargs = [a for a in sys.argv[1:] if a != '--json']
        src_path, dst_path = sargs[0], sargs[1]
        check_only = '--check' in sys.argv

    src = safe_read(src_path)
    errs = check(src)

    if check_only:
        result = build_result('assemble_strip', '1.3', errors=errs, warnings=[],
            extra={'mode': 'check_only', 'forbidden_words_checked': FORBIDDEN_WORDS})
        output(result, use_json)
        return

    if errs:
        result = build_result('assemble_strip', '1.3', errors=errs, warnings=[],
            extra={'mode': 'strip_aborted', 'reason': '检查未通过,发布版未生成'})
        output(result, use_json)
        return

    try:
        with open(dst_path, 'w', encoding='utf-8') as f:
            f.write(strip(src))
    except OSError as e:
        print(f'❌ 写入失败: {dst_path} ({e})', file=sys.stderr)
        sys.exit(2)

    result = build_result('assemble_strip', '1.3', errors=[], warnings=[],
        extra={'mode': 'strip_done', 'output': dst_path})
    output(result, use_json)

if __name__ == '__main__':
    try:
        main()
    except Exception as e:
        print(f'❌ 脚本异常: {e}', file=sys.stderr)
        sys.exit(2)
