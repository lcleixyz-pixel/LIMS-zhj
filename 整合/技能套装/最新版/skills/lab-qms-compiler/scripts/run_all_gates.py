#!/usr/bin/env python3
# run_all_gates.py v1.3 — 一键总验证:跨平台、动态路径、指纹报告(v1.3修复变量名fp→fp_lines Bug)
# 用法: python run_all_gates.py --base-dir <底稿目录> [--themes <glob>] [--vols <glob>]
# 无 --base-dir 时默认脚本所在目录的 ../../
import subprocess, hashlib, datetime, os, sys, glob as gb, argparse

ap = argparse.ArgumentParser(description='一键总验证:跑全部闸口检查出指纹报告')
ap.add_argument('--base-dir', default=None,
    help='底稿与两册所在目录（默认:脚本所在目录向上两级，即技能套装根目录所在项目）')
ap.add_argument('--themes', default='要素模板-*.md',
    help='底稿文件 glob 模式（默认:要素模板-*.md）')
ap.add_argument('--vols', nargs='*', default=['质量手册-工作版-v*.md', '程序汇编-工作版-v*.md'],
    help='两册文件 glob 模式（默认:质量手册-工作版-*.md 程序汇编-工作版-*.md）')
args = ap.parse_args()

# ── 路径解析 ───────────────────────────────────────────────────
script_dir = os.path.dirname(os.path.abspath(__file__))
# 技能套装根目录: compiler/scripts → skills/lab-qms-compiler/scripts → ../../../
SKILL_ROOT = os.path.normpath(os.path.join(script_dir, '..', '..', '..'))
SHARED_SCRIPTS = os.path.join(SKILL_ROOT, 'scripts')
BUILDER_SCRIPTS = os.path.join(SKILL_ROOT, 'skills', 'lab-qms-builder', 'scripts')

if args.base_dir:
    BASE_DIR = os.path.abspath(args.base_dir)
else:
    # 默认:技能套装根目录（项目目录由 AI 挂载时指定）
    BASE_DIR = os.getcwd()

# ── 文件发现 ───────────────────────────────────────────────────
def resolve_glob(pattern, base_dir=None):
    """展开 glob 模式,取最新版本（按文件名倒序取第一个匹配）"""
    d = base_dir or BASE_DIR
    hits = sorted(gb.glob(os.path.join(d, pattern)))
    if not hits:
        # 递归搜索子目录
        hits = sorted(gb.glob(os.path.join(d, '**', pattern), recursive=True))
    if not hits:
        return []
    # 单文件模式:返回所有匹配
    return hits

THEMES = []
for pat in [args.themes] if isinstance(args.themes, str) else args.themes:
    THEMES.extend(resolve_glob(pat))

VOLS = []
for pat in args.vols:
    VOLS.extend(resolve_glob(pat))

# 回退:glob 未命中时尝试常见文件名
if not THEMES:
    fallback = gb.glob(os.path.join(BASE_DIR, '*.md'))
    THEMES = sorted([f for f in fallback if '要素模板' in os.path.basename(f) or '主题' in os.path.basename(f)])
if not VOLS:
    fallback2 = gb.glob(os.path.join(BASE_DIR, '*.md'))
    VOLS = sorted([f for f in fallback2 if '质量手册' in os.path.basename(f) or '程序汇编' in os.path.basename(f)])

# ── 脚本路径检查 ───────────────────────────────────────────────
PY = sys.executable
VALIDATE = os.path.join(BUILDER_SCRIPTS, 'validate.py')
MANUAL_CHECK = os.path.join(SHARED_SCRIPTS, 'manual_check.py')
COVERAGE_CHECK = os.path.join(SHARED_SCRIPTS, 'coverage_check.py')

for name, path in [('validate.py', VALIDATE), ('manual_check.py', MANUAL_CHECK),
                    ('coverage_check.py', COVERAGE_CHECK)]:
    if not os.path.exists(path):
        print(f'❌ 脚本缺失: {path}')
        # 尝试在旧位置查找（向后兼容）
        alt = os.path.join(script_dir, name)
        if os.path.exists(alt):
            print(f'   回退到旧位置: {alt}')

# ── 执行检查 ───────────────────────────────────────────────────
os.chdir(BASE_DIR)
lines = []
fail = 0

def run(cmd, cwd=None):
    global fail
    workdir = cwd or BASE_DIR
    r = subprocess.run(cmd, capture_output=True, text=True, cwd=workdir)
    ok = r.returncode == 0
    fail += (not ok)
    label = ' '.join(cmd[1:]) if cmd[0] == PY else ' '.join(cmd)
    lines.append(('✅' if ok else '❌') + ' `' + label[:80] + '`'
                 + ('' if ok else '\n```\n' + r.stdout[-400:] + '\n```'))
    return ok

miss = [f for f in THEMES + VOLS if not os.path.exists(f)]
if miss:
    lines.append('❌ 资产缺失: ' + ', '.join(miss))
    fail += 1

# 1. 各主题 validate
for f in THEMES:
    run([PY, VALIDATE, f])

# 2. 两册 manual_check
for v, extra in [(VOLS[0], []) if VOLS else (None, []),
                 (VOLS[1] if len(VOLS) > 1 else None, ['--vol2'])]:
    if v is None:
        break
    cmd = [PY, MANUAL_CHECK, v]
    if extra:
        cmd.extend(extra)
    run(cmd)

# 3. coverage_check
if VOLS and THEMES:
    run([PY, COVERAGE_CHECK] + VOLS + THEMES)

# ── 指纹报告 ───────────────────────────────────────────────────
today = datetime.date.today().isoformat()
fp_lines = []
for f in THEMES + VOLS:
    if os.path.exists(f):
        h = hashlib.md5(open(f, 'rb').read()).hexdigest()[:10]
        fp_lines.append(f'| {os.path.basename(f)} | {h} |')
    else:
        fp_lines.append(f'| {f} | ⚠ 文件缺失 |')

total_checks = (len(THEMES) if THEMES else 0) + (min(2, len(VOLS)) if VOLS else 0) + (1 if VOLS and THEMES else 0)
rep = f"""# 验证报告 {today}

> 口径:对下列**指纹锁定**的文件集,运行命令如列。任何"全绿"声明以本报告为凭。

结论:{'✅ 全绿' if not fail else f'❌ {fail} 项未过'}(共 {total_checks} 项检查)

## 文件指纹(md5前10位)
| 文件 | 指纹 |
|---|---|
{chr(10).join(fp_lines)}

## 逐项结果
""" + '\n'.join(lines)

outfile = os.path.join(BASE_DIR, f'验证报告-{today}.md')
with open(outfile, 'w', encoding='utf-8') as fout:
    fout.write(rep)

print(('✅ 全绿' if not fail else f'❌ {fail} 项未过') + f' → {outfile}')
sys.exit(1 if fail else 0)
