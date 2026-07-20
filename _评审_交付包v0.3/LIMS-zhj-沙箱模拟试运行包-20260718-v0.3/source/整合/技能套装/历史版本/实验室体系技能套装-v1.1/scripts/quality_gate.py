#!/usr/bin/env python3
# quality_gate.py v1.0 — 产出质量自动评分(engineer收子技能产出时用)
# 检查:回链密度/必填字段填充率/待核率/自检表诚实度
# 用法: python quality_gate.py [--json] [--threshold 70] <底稿文件.md>
# 得分<阈值 → 标"待人工复核"
import re, sys, os

try:
    from cli_utils import safe_read, standard_argparse, build_result, output
    HAS_UTILS = True
except ImportError:
    HAS_UTILS = False
    def safe_read(p): return open(p, encoding='utf-8').read()

def count_chars(text):
    """非空白字符数"""
    return len(re.sub(r'\s', '', text))

def check_link_density(text):
    """回链密度:正文每200字至少1个回链。返回(回链数, 正文字符数, 密度分0-10)"""
    prose = re.split(r'^##\s+', text, flags=re.M)
    # 取合并条款区域
    merge_zone = ''
    for s in prose:
        if '合并条款' in s.split('\n')[0]:
            merge_zone = s
            break
    if not merge_zone:
        return 0, 0, 5, '未找到合并条款区域'

    links = re.findall(r'\[K-[^\]]+\]', merge_zone)
    chars = count_chars(merge_zone)
    if chars == 0:
        return 0, 0, 5, '合并条款区域无正文'

    ratio = len(links) * 200 / max(chars, 1)
    if ratio >= 2.0:
        score = 10
    elif ratio >= 1.0:
        score = 8
    elif ratio >= 0.5:
        score = 5
    else:
        score = 2
    return len(links), chars, score, f'{len(links)}回链/{chars}字={ratio:.1f}x'

def check_field_completeness(text):
    """K卡必填字段填充率。返回(完整卡数, 总卡数, 得分)"""
    k_cards = re.findall(r'[-|]\s*(K-\d+)\s*\|(.+?)(?=\n[-|]|\n\n|\Z)', text, re.S)
    if not k_cards:
        return 0, 0, 5, '未找到K卡定义'

    complete = 0
    incomplete_ids = []
    for k_id, body in k_cards:
        fields = [f.strip() for f in body.split('|')]
        non_empty = sum(1 for f in fields if f and f != '-' and f != '—')
        if non_empty >= 6:  # 来源/文本/文本性质/适用性/强制度/效力 至少6个非空
            complete += 1
        else:
            incomplete_ids.append(k_id)

    rate = complete / len(k_cards)
    if rate >= 0.95:
        score = 10
    elif rate >= 0.8:
        score = 7
    elif rate >= 0.5:
        score = 4
    else:
        score = 1
    return complete, len(k_cards), score, f'{complete}/{len(k_cards)}张字段完整' + (f' (缺:{incomplete_ids[:3]})' if incomplete_ids else '')

def check_pending_rate(text):
    """待核率:待核条款占比超过20%→告警"""
    total_clauses = len(re.findall(r'K-\d+', text))
    if total_clauses == 0:
        return 0, 0, 10, '无K卡'
    pending = len(re.findall(r'待核', text))
    rate = pending / total_clauses
    if rate > 0.3:
        score = 1
    elif rate > 0.2:
        score = 3
    elif rate > 0.1:
        score = 6
    elif rate > 0:
        score = 8
    else:
        score = 10
    return pending, total_clauses, score, f'待核{pending}条/{total_clauses}条款={rate:.0%}'

def check_selfcheck_honesty(text):
    """自检表诚实度:检查是否存在但全部打钩未附说明"""
    sc = re.search(r'自检表', text)
    if not sc:
        return 0, '未找到自检表,扣分'

    after = text[sc.start():sc.start()+2000]
    rows = re.findall(r'^\|.*\|$', after, re.M)
    filled = 0
    empty = 0
    for row in rows[2:]:
        cols = [c.strip() for c in row.split('|')[1:-1]]
        if len(cols) >= 2:
            if cols[1] and cols[1] not in ('—', '-', '是', '否', ''):
                filled += 1
            else:
                empty += 1

    if filled >= 5:
        return 10, f'自检表{filled}行有实质性说明'
    elif filled >= 3:
        return 6, f'自检表{filled}行有说明,{empty}行偏简'
    else:
        return 2, f'自检表仅{filled}行有内容,可能未诚实填写'

def main():
    if HAS_UTILS:
        p = standard_argparse('产出质量自动评分——engineer收子技能产出时用')
        p.add_argument('file', help='底稿/产出文件.md')
        p.add_argument('--threshold', type=int, default=70, help='最低通过分(默认70)')
        args = p.parse_args()
        fpath, threshold, use_json = args.file, args.threshold, args.json
    else:
        if len(sys.argv) < 2:
            print('用法: python quality_gate.py [--json] [--threshold 70] <文件.md>', file=sys.stderr)
            sys.exit(2)
        use_json = '--json' in sys.argv
        sargs = [a for a in sys.argv[1:] if a != '--json' and not a.startswith('--threshold')]
        fpath = sargs[0]
        threshold = 70
        for i, a in enumerate(sys.argv[1:]):
            if a == '--threshold' and i+2 < len(sys.argv):
                threshold = int(sys.argv[i+2]); break

    text = safe_read(fpath)

    # 四项检查
    links_n, chars_n, link_score, link_detail = check_link_density(text)
    complete_n, total_n, field_score, field_detail = check_field_completeness(text)
    pending_n, total_c, pending_score, pending_detail = check_pending_rate(text)
    sc_score, sc_detail = check_selfcheck_honesty(text)

    # 加权总分:回链密度30% + 字段完整性25% + 待核率25% + 自检表20%
    total_score = link_score * 3.0 + field_score * 2.5 + pending_score * 2.5 + sc_score * 2.0
    passed = total_score >= threshold

    errors = []
    warnings = []
    if not passed:
        errors.append(f'质量分{total_score:.0f}<阈值{threshold},建议人工复核')
    if pending_n > 0 and pending_n / max(total_c, 1) > 0.2:
        errors.append(f'待核率{pending_n/max(total_c,1):.0%}>20%——文件不完整,可能被评审员开不符合')
    if link_score < 5:
        warnings.append(f'回链密度偏低({link_detail}),正文溯源可能不足')

    extra = {
        'total_score': round(total_score, 1),
        'threshold': threshold,
        'passed': passed,
        'link_density': {'score': link_score, 'detail': link_detail},
        'field_completeness': {'score': field_score, 'complete': complete_n, 'total': total_n, 'detail': field_detail},
        'pending_rate': {'score': pending_score, 'pending': pending_n, 'total_clauses': total_c, 'detail': pending_detail},
        'selfcheck': {'score': sc_score, 'detail': sc_detail},
        'verdict': 'PASS 质量达标' if passed else 'WARN 待人工复核'
    }

    result = build_result('quality_gate', '1.0', errors=errors, warnings=warnings, extra=extra)
    output(result, use_json)


if __name__ == '__main__':
    try:
        main()
    except Exception as e:
        print(f'❌ 脚本异常: {e}', file=sys.stderr)
        sys.exit(2)
