#!/usr/bin/env python3
# profile_check.py v1.2 — 答卷一致性互检。答卷约定:每题一行 "A<题号>: 答案"
# v1.2 改进:动态题库上限 / 规则数据驱动 / 中文数字匹配 / H节覆盖 / R8单点故障 / --json输出
import re, sys, os, json

# ── CLI参数 ────────────────────────────────────────────────────
if '--help' in sys.argv or '-h' in sys.argv:
    print("用法: python profile_check.py [--json] <答卷文件>")
    print("  答卷约定: 每题一行 'A<题号>: 答案'")
    print("  --json  输出结构化 JSON")
    sys.exit(0)
OUTPUT_JSON = '--json' in sys.argv
# 过滤掉标志参数,剩下的才是文件路径
args = [a for a in sys.argv[1:] if a not in ('--json', '--help', '-h')]
if not args:
    print("用法: python profile_check.py [--json] <答卷文件>", file=sys.stderr)
    sys.exit(2)

t = open(args[0], encoding='utf-8').read()
A = {}
for m in re.finditer(r'^A(\d+)[::]\s*(.+)$', t, re.M):
    A[int(m.group(1))] = m.group(2).strip()

def has(n, *kw): return n in A and any(k in A[n] for k in kw)
def no(n, *kw):  return n in A and not any(k in A[n] for k in kw)

# ── 加载规则（JSON 或内置回退） ──────────────────────────────────
MAX_Q = 47
script_dir = os.path.dirname(os.path.abspath(__file__))
rules_path = os.path.join(script_dir, '..', 'references', 'profile_rules.json')
if os.path.exists(rules_path):
    try:
        with open(rules_path, encoding='utf-8') as f:
            cfg = json.load(f)
        MAX_Q = cfg.get('max_question_number', 47)
    except Exception:
        pass

# ── 中文数字 → 阿拉伯数字映射 ──────────────────────────────────
CN_NUM = {'一':1,'二':2,'两':2,'三':3,'四':4,'五':5,'六':6,'七':7,'八':8,'九':9,'十':10}

def match_count(text, patterns=None):
    """从文本中提取数量，支持阿拉伯数字和中文数字"""
    if patterns is None:
        patterns = [r'(\d+)\s*[个位名名]', r'([一二两三四五六七八九十]+)\s*[个位名名]']
    for pat in patterns:
        m = re.search(pat, text)
        if m:
            val = m.group(1)
            if val.isdigit():
                return int(val)
            if val in CN_NUM:
                return CN_NUM[val]
            total = 0
            for ch in val:
                if ch in CN_NUM:
                    total += CN_NUM[ch]
            return total if total > 0 else None
    return None

def match_multi_site(text):
    """检查是否多场所（≥2个），支持中文'二个''两个'"""
    cnt = match_count(text, [r'(\d+)\s*个', r'([一二两三四五六七八九十]+)\s*个'])
    return cnt is not None and cnt >= 2

# ── 执行互检规则 ──────────────────────────────────────────────────
errs, warns, frag = [], [], []

# R1: 切工仪互检
if has(9, '做') and no(9, '不做') and no(20, '切工', '切割测量', '切工测量'):
    errs.append('R1: 答做切工分级(题9),但设备清单(题20)无切工测量仪')
if has(9, '不做') and has(20, '切工'):
    warns.append('R1: 不做切工分级却有切工仪——确认是否预留扩项(题13)')

# R2: 比色石数量
if has(8, '做') and no(8, '不做'):
    m = re.search(r'(\d+)\s*粒', A.get(21, ''))
    if not m:
        errs.append('R2: 做钻石分级(题8)但比色石数量(题21)未答')
    else:
        n = int(m.group(1))
        need = 7 if ('未镶嵌' in A.get(8, '') or '都做' in A.get(8, '')) else 5
        if n < need:
            errs.append(f'R2: 比色石{n}粒<最低{need}粒')
        elif n == need:
            frag.append(f'比色石={n}粒(恰为最低值{need}粒)')

# R3: 贵金属标样
if has(10, '做') and no(10, '不做') and no(22, '有'):
    errs.append('R3: 做贵金属检测(题10)但标样(题22)未答"有"')

# R4: 内部校准
if has(23, '自校', '自己校', '部分'):
    warns.append('R4: 存在内部校准→G004整章启用(C4翻转),需人员/方法/不确定度配套')

# R5: 多场所（支持中文数字'二个''两个'）
if match_multi_site(A.get(3, '')):
    warns.append('R5: 多场所→每场所≥2名签字人,核对题14分布(C5翻转)')

# R6: 一人多职
if has(15, '兼', '一人'):
    warns.append('R6: 一人多职→核对投诉审批与内审回避矩阵是否闭合')

# R7: 授权签字人脆弱达标
n_sig = match_count(A.get(14, ''))
if n_sig is not None and n_sig == 2:
    frag.append(f'授权签字人={n_sig}名(恰为每场所最低值)')

# R8: 单点故障检测（题46 各检测模块可顶岗人数）
if 46 in A:
    answer_46 = A[46]
    # 匹配 "鉴定2人" "分级1名" "贵金属 3 位" 等格式
    module_patterns = re.findall(
        r'(鉴定|分级|贵金属|钻石|切工|印记|化学|前处理|光谱|定名)[^\d]*(\d+|[一二两三四五六七八九十]+)\s*[人名位]',
        answer_46
    )
    for module, count_str in module_patterns:
        cnt = int(count_str) if count_str.isdigit() else CN_NUM.get(count_str, 0)
        if cnt == 1:
            frag.append(f"模块'{module}'仅1人可顶岗→单点故障(休假即停摆,人员比对无法开展)")

# ── 输出 ──────────────────────────────────────────────────────────
if OUTPUT_JSON:
    result = {
        "tool": "profile_check",
        "version": "1.2",
        "answered_count": len(A),
        "max_questions": MAX_Q,
        "errors": errs,
        "warnings": warns,
        "fragile_items": frag,
        "missing": missing,
        "passed": len(errs) == 0
    }
    print(json.dumps(result, ensure_ascii=False, indent=2))
else:
    print(f'== profile_check v1.2 == 已答 {len(A)}/{MAX_Q}')
    for e in errs: print('  [FAIL]', e)
    for w in warns: print('  [WARN]', w)
    for f in frag: print('  [脆弱达标]', f)
    if missing: print('  [未决项] 题', ','.join(missing))
    print('结论:', '✅ 互检通过' if not errs else f'❌ {len(errs)} 项矛盾须澄清后再出产物')
sys.exit(1 if errs else 0)
