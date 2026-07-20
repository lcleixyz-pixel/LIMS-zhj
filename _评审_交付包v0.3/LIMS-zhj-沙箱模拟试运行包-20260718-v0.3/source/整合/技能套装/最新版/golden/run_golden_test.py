#!/usr/bin/env python3
# run_golden_test.py v1.1 — golden files integrated regression test (v1.1 +Gate6 skeleton_check)
import subprocess, sys, os, glob, hashlib

ROOT = os.path.dirname(os.path.abspath(__file__))
SKILL_ROOT = os.path.join(ROOT, '..')
SCRIPTS = os.path.join(SKILL_ROOT, 'scripts')
BUILDER = os.path.join(SKILL_ROOT, 'skills', 'lab-qms-builder', 'scripts')
GOLDEN = ROOT

PY = sys.executable
failed = 0
passed = 0
warnings_only = 0  # gates that are informational, not blocking

def run(label, cmd, cwd=GOLDEN, allow_fail=False):
    global failed, passed, warnings_only
    try:
        r = subprocess.run(cmd, capture_output=True, text=True, cwd=cwd)
    except Exception as e:
        print(f'  FAIL [{label}]: {e}')
        if not allow_fail:
            failed += 1
        else:
            warnings_only += 1
        return False
    ok = r.returncode == 0
    if ok:
        passed += 1
        print(f'  PASS [{label}]')
    elif allow_fail:
        warnings_only += 1
        print(f'  WARN [{label}] (expected: golden fixture is minimal, lacks front matter)')
    else:
        failed += 1
        print(f'  FAIL [{label}]')
        out = (r.stdout + r.stderr)[-300:]
        for line in out.split('\n')[-8:]:
            print(f'    {line}')
    return ok

print('=== Golden Files Integration Test ===')
print(f'Golden dir: {GOLDEN}')
print(f'Python:     {PY}')
print()

# Verify golden files exist
expected = [
    os.path.join(GOLDEN, '要素模板-设备主题-v1.0.md'),
    os.path.join(GOLDEN, '质量手册-工作版-v1.0.md'),
    os.path.join(GOLDEN, '程序汇编-工作版-v1.0.md'),
]
for f in expected:
    if not os.path.exists(f):
        print(f'  MISSING: {os.path.basename(f)}')
        failed += 1

if failed > 0:
    print(f'\nFAIL: {failed} golden files missing, aborting')
    sys.exit(1)

# 1. validate.py
print('-- Gate 1: validate.py --')
run('validate draft',
    [PY, os.path.join(BUILDER, 'validate.py'), '--json',
     expected[0]])

# 2. manual_check.py
print('-- Gate 2: manual_check.py --')
run('manual_check manual',
    [PY, os.path.join(SCRIPTS, 'manual_check.py'), '--json',
     expected[1], GOLDEN])
run('manual_check compilation',
    [PY, os.path.join(SCRIPTS, 'manual_check.py'), '--json',
     '--vol2', expected[2], GOLDEN])

# 3. coverage_check.py
print('-- Gate 3: coverage_check.py --')
run('coverage_check',
    [PY, os.path.join(SCRIPTS, 'coverage_check.py'), '--json',
     expected[1], expected[2], expected[0]])

# 4. assemble_strip.py
print('-- Gate 4: assemble_strip.py --')
pub_path_check = os.path.join(GOLDEN, '质量手册-发布版-v1.0.md')
run('assemble_strip --check',
    [PY, os.path.join(SCRIPTS, 'assemble_strip.py'), '--json', '--check',
     expected[1], pub_path_check])

# 5. compliance_check.py
print('-- Gate 5: compliance_check.py --')
run('compliance_check (builder profile)',
    [PY, os.path.join(SCRIPTS, 'compliance_check.py'), '--json',
     '--profile', 'builder', expected[0]])

# 6. skeleton_check.py (v1.1 新增)
print('-- Gate 6: skeleton_check.py --')
pub_path = os.path.join(GOLDEN, '质量手册-发布版-v1.0.md')
# 先生成发布版
print('  generating publish edition...')
r = subprocess.run(
    [PY, os.path.join(SCRIPTS, 'assemble_strip.py'), '--json',
     expected[1], pub_path],
    capture_output=True, text=True, cwd=GOLDEN)
if r.returncode == 0:
    # 对发布版运行骨架闸(S1残留+S2章节;跳过S3无事实表)
    run('skeleton_check S1+S2',
        [PY, os.path.join(SCRIPTS, 'skeleton_check.py'), pub_path],
        allow_fail=True)  # golden fixture 无前置章,预期S2告警
else:
    print('  FAIL [skeleton_check]: 发布版生成失败,跳过')
    failed += 1

# 7. 第9道:预导入包构建 + manifest 完整性(契约§二/§五;乙方构建器+校验器副本随包)
print('-- Gate 9: preimport package build + manifest (第9道) --')
gate9 = os.path.join(GOLDEN, '第9道-preimport')
gate9_pkg = os.path.join(gate9, 'package')
if not os.path.exists(gate9):
    print('  FAIL [gate9]: 缺 第9道-preimport/ 目录')
    failed += 1
else:
    run('build preimport package',
        [PY, os.path.join(gate9, 'qms_lims_preimport_build.py'),
         '--stage-dir', os.path.join(gate9, 'stage'),
         '--lims-root', gate9,
         '--output-dir', gate9_pkg], cwd=gate9)
    run('validate manifest',
        [PY, os.path.join(gate9, 'validate_manifest.py'),
         '--package-dir', gate9_pkg], cwd=gate9)

# Fingerprints
print('-- Fingerprints --')
for f in sorted(glob.glob(os.path.join(GOLDEN, '*.md'))):
    h = hashlib.md5(open(f, 'rb').read()).hexdigest()[:12]
    print(f'  md5:{h}  {os.path.basename(f)}')

# Conclusion
print()
total = passed + failed + warnings_only
print(f'=== Result: {passed}/{total} passed ({warnings_only} warn) ===')
# Clean up test artifact
for p in [pub_path_check, pub_path]:
    if os.path.exists(p):
        try:
            os.remove(p)
        except:
            pass
if failed == 0:
    print('ALL GREEN - golden regression test passed')
    sys.exit(0)
else:
    print(f'FAIL: {failed} gate(s) did not pass - check golden files')
    sys.exit(1)
