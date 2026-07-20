#!/usr/bin/env python3
# run_golden_test.py v1.0 — golden files integrated regression test
import subprocess, sys, os, glob, hashlib

ROOT = os.path.dirname(os.path.abspath(__file__))
SKILL_ROOT = os.path.join(ROOT, '..')
SCRIPTS = os.path.join(SKILL_ROOT, 'scripts')
BUILDER = os.path.join(SKILL_ROOT, 'skills', 'lab-qms-builder', 'scripts')
GOLDEN = ROOT

PY = sys.executable
failed = 0
passed = 0

def run(label, cmd, cwd=GOLDEN):
    global failed, passed
    try:
        r = subprocess.run(cmd, capture_output=True, text=True, cwd=cwd)
    except Exception as e:
        print(f'  FAIL [{label}]: {e}')
        failed += 1
        return False
    ok = r.returncode == 0
    if ok:
        passed += 1
        print(f'  PASS [{label}]')
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
pub_path = os.path.join(GOLDEN, '质量手册-发布版-v1.0.md')
run('assemble_strip --check',
    [PY, os.path.join(SCRIPTS, 'assemble_strip.py'), '--json', '--check',
     expected[1], pub_path])

# 5. compliance_check.py
print('-- Gate 5: compliance_check.py --')
run('compliance_check (builder profile)',
    [PY, os.path.join(SCRIPTS, 'compliance_check.py'), '--json',
     '--profile', 'builder', expected[0]])

# Fingerprints
print('-- Fingerprints --')
for f in sorted(glob.glob(os.path.join(GOLDEN, '*.md'))):
    h = hashlib.md5(open(f, 'rb').read()).hexdigest()[:12]
    print(f'  md5:{h}  {os.path.basename(f)}')

# Conclusion
print()
total = passed + failed
print(f'=== Result: {passed}/{total} passed ===')
if failed == 0:
    print('ALL GREEN - golden regression test passed')
    # Clean up test artifact
    if os.path.exists(pub_path):
        os.remove(pub_path)
    sys.exit(0)
else:
    print(f'FAIL: {failed} gate(s) did not pass - check golden files')
    sys.exit(1)
