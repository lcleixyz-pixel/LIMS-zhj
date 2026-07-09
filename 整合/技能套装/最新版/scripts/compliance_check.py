#!/usr/bin/env python3
# compliance_check.py v1.0 — 弱模型规程形式化检查
# 覆盖:规程四(模板结构符合性) + 规程五(自检表存在+非空)
# 用法: python compliance_check.py [--json] [--profile builder|compiler|any] <产出文件.md>
import re, sys, os

try:
    from cli_utils import safe_read, standard_argparse, build_result, output
    HAS_UTILS = True
except ImportError:
    HAS_UTILS = False
    def safe_read(p): return open(p, encoding='utf-8').read()

# ── 模板结构定义(按技能/产物类型) ───────────────────────────────
TEMPLATES = {
    'builder': {
        'name': '要素模板底稿',
        'sections': [
            '书架', '点名', '卡片', '连线', '合并条款',
            'PDCA', '程序', '接口', '指针', '对账', '世系', '待办',
            '记录模板', '记录表格', '自审'
        ],
        'forbidden_words': ['待核', '一般机构', '通常'],
        'must_have_clauses': ['遍历闭合', '窗证闭合']
    },
    'compiler': {
        'name': '手册/汇编',
        'sections': ['4.1', '4.2', '5', '6.1', '6.2', '6.3', '6.4', '6.5', '6.6',
                     '7.1', '7.2', '7.3', '7.4', '7.5', '7.6', '7.7', '7.8', '7.9',
                     '7.10', '7.11', '8.1', '8.2', '8.3', '8.4', '8.5', '8.6', '8.7',
                     '8.8', '8.9'],
        'forbidden_words': ['工作版', '占位', '【虚构】', '待拍板'],
        'must_have_clauses': []
    },
    'any': {
        'name': '通用(仅检查自检表)',
        'sections': [],
        'forbidden_words': [],
        'must_have_clauses': []
    }
}

# ── 自检表要求 ──────────────────────────────────────────────────
SELFCHECK_ROWS = [
    '强制停点',  # 对照"🔴强制停点"
    '出处编号',  # 每句是否带出处
    '编造内容',  # 是否存在编造/推测
    '留空',      # 未回答处是否留空
    '版本号',    # 版本号是否升级
    '检查脚本'   # 检查脚本运行状态
]

def check_selfcheck_table(text):
    """检查文件末尾是否存在自检表并逐行非空。"""
    errors, warns = [], []

    # 查找自检表区域
    # 支持多种变体: ## 自检表 / ### 自检表 / 自检表
    m = re.search(r'(?:^#{1,3}\s*)?自检表\s*$', text, re.M)
    if not m:
        errors.append('规程五:文件末尾缺少"自检表"节(弱模型规程五要求产出文末必附自检表)')
        return errors, warns

    # 取自检表之后的内容
    after = text[m.start():]

    # 统计表格行
    rows = re.findall(r'^\|.*\|$', after, re.M)
    if len(rows) < 3:  # 表头+分隔行+至少1行数据
        errors.append(f'规程五:自检表只有{len(rows)}行,至少需要6行检查项')
        return errors, warns

    # 检查关键检查项是否存在
    found_checks = 0
    for row in rows[2:]:  # 跳表头和分隔行
        cols = [c.strip() for c in row.split('|')[1:-1]]
        if len(cols) >= 2:
            check_item = cols[0]
            result = cols[1] if len(cols) > 1 else ''
            if check_item and result:
                found_checks += 1
            # 空结果列 → 可能 AI 蒙混过关
            if check_item and not result:
                warns.append(f'规程五:自检行"{check_item[:20]}"结果列为空——可能未诚实填写')

    if found_checks < 4:
        warns.append(f'规程五:自检表仅{found_checks}行有内容,建议逐项如实填写(弱模型规程要求6项)')

    return errors, warns


def check_template_structure(text, profile):
    """检查模板结构符合性(规程四)。"""
    errors, warns = [], []
    tmpl = TEMPLATES.get(profile, TEMPLATES['any'])
    sections = tmpl.get('sections', [])

    if not sections:
        return errors, warns

    # 查找所有二级标题
    headers = re.findall(r'^##\s+(.+)$', text, re.M)
    header_text = ' '.join(headers)

    missing = []
    for sec in sections:
        # 检查是否以标题形式存在
        found = any(sec in h for h in headers) or any(sec in h for h in re.findall(r'^#+\s*(.+)$', text, re.M))
        if not found:
            # 放宽: 检查正文是否提及该章节(如"不适用+理由")
            if re.search(rf'{re.escape(sec)}.*不适用', text):
                warns.append(f'章节"{sec}"以"不适用"豁免——请人工确认理由')
            else:
                missing.append(sec)

    if missing:
        errors.append(f'规程四:缺少模板规定的章节: {", ".join(missing[:8])}'
                      + ('...' if len(missing) > 8 else '')
                      + f' (共{len(missing)}节缺失)')

    # 检查禁止词(仅对 compiler 发布版)
    forbidden = tmpl.get('forbidden_words', [])
    for word in forbidden:
        if re.search(re.escape(word), text):
            errors.append(f'规程六(停点):产出中含禁止词"{word}"——应停下确认')

    # 检查闭合声明
    must_have = tmpl.get('must_have_clauses', [])
    for clause in must_have:
        if clause not in text:
            warns.append(f'缺少闭合声明标注:应包含"{clause}"')

    return errors, warns


def main():
    if HAS_UTILS:
        p = standard_argparse('弱模型规程形式化检查:模板结构+自检表')
        p.add_argument('file', help='要检查的产出文件.md')
        p.add_argument('--profile', choices=['builder', 'compiler', 'any'], default='any',
                       help='按哪种产物类型检查模板结构(默认 any=仅查自检表)')
        args = p.parse_args()
        fpath, profile, use_json = args.file, args.profile, args.json
    else:
        if len(sys.argv) < 2:
            print('用法: python compliance_check.py [--json] [--profile builder|compiler|any] <文件.md>',
                  file=sys.stderr)
            sys.exit(2)
        use_json = '--json' in sys.argv
        sargs = [a for a in sys.argv[1:] if a != '--json']
        fpath = sargs[0]
        profile = 'any'
        for i, a in enumerate(sargs):
            if a == '--profile' and i+1 < len(sargs):
                profile = sargs[i+1]; break

    text = safe_read(fpath)

    # 规程四: 模板结构
    tmpl_errs, tmpl_warns = check_template_structure(text, profile)

    # 规程五: 自检表
    sc_errs, sc_warns = check_selfcheck_table(text)

    all_errors = tmpl_errs + sc_errs
    all_warns = tmpl_warns + sc_warns

    extra = {
        'file': os.path.basename(fpath),
        'profile': profile,
        'profile_name': TEMPLATES.get(profile, TEMPLATES['any'])['name'],
        'selfcheck_found': bool(re.search(r'自检表', text)),
        'template_sections_checked': len(TEMPLATES.get(profile, {})['sections']),
        'note': '规程一/二/三/七为对话行为约束,无法静态检查。本脚本仅覆盖规程四(模板结构)和规程五(自检表)。'
    }

    result = build_result('compliance_check', '1.0', errors=all_errors, warnings=all_warns, extra=extra)
    output(result, use_json)


if __name__ == '__main__':
    try:
        main()
    except Exception as e:
        print(f'❌ 脚本异常: {e}', file=sys.stderr)
        sys.exit(2)
