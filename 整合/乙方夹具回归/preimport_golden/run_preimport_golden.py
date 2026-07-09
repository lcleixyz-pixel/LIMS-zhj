#!/usr/bin/env python3
# run_preimport_golden.py v1.0 — 乙方侧固定夹具包 dry-run 回归
# 对应:接口契约 v0.9 §五"乙方对应固定夹具包 dry-run 回归【平台侧建】"
# 形态镜像套装侧 golden/run_golden_test.py:固定夹具 → dry-run → 发现项=0 + 报告稳定 → ALL GREEN
#
# 用法(在 jewelry-qms 平台根目录已装好 composer 依赖、且数据库可连时):
#   python tests/preimport_golden/run_preimport_golden.py
#   python tests/preimport_golden/run_preimport_golden.py --lims-root /path/to/jewelry-qms --php php
#   python tests/preimport_golden/run_preimport_golden.py --pin   # 首次:把实际发现项钉定为期望基线
#
# 回归语义:同一固定夹具,dry-run 的"状态 + 发现项 id 集合"必须与钉定基线一致。
# 平台侧改接口/改校验逻辑导致 dry-run 输出变化 → 本回归飘红(双侧金样,契约§五)。
import argparse
import subprocess
import sys
import os
import json
import hashlib

ROOT = os.path.dirname(os.path.abspath(__file__))
FIXTURE = os.path.join(ROOT, 'fixture')
EXPECTED = os.path.join(ROOT, 'expected')
EXPECTED_FINDINGS = os.path.join(EXPECTED, 'expected_findings.json')

REQUIRED_FILES = [
    'preimport_manifest.json',
    'documents_preimport.csv',
    'structured_documents_preimport.csv',
    'record_form_templates_preimport.csv',
    'traceability_matrix_preimport.csv',
    'manual_blocks_preimport.csv',
    'external_sources_preimport.csv',
]

failed = 0
passed = 0


def md5(path):
    return hashlib.md5(open(path, 'rb').read()).hexdigest()[:12]


def fail(msg):
    global failed
    failed += 1
    print(f'  FAIL: {msg}')


def ok(msg):
    global passed
    passed += 1
    print(f'  PASS: {msg}')


def locate_lims_root(arg):
    if arg:
        return arg
    # 默认:本脚本位于 jewelry-qms/tests/preimport_golden/,平台根在上两级
    cand = os.path.abspath(os.path.join(ROOT, '..', '..'))
    if os.path.isfile(os.path.join(cand, 'think')):
        return cand
    return None


def main():
    global FIXTURE
    ap = argparse.ArgumentParser(description='乙方侧预导入金样 dry-run 回归')
    ap.add_argument('--lims-root', default=None, help='jewelry-qms 平台根目录(含 think 入口)')
    ap.add_argument('--php', default='php', help='php 可执行文件')
    ap.add_argument('--fixture-dir', default=FIXTURE,
                    help='夹具目录(默认 fixture/;可用 smoke_dbfree/fixture 免库冒烟)')
    ap.add_argument('--pin', action='store_true', help='首次运行:把实际发现项 id 集合钉定为期望基线')
    args = ap.parse_args()

    FIXTURE = args.fixture_dir
    lims_root = locate_lims_root(args.lims_root)
    print('=== 乙方侧 预导入金样 dry-run 回归(契约§五双侧金样·乙方侧)===')
    print(f'平台根: {lims_root or "(未定位)"}')
    print(f'夹具目录: {FIXTURE}')
    print()

    if not lims_root or not os.path.isfile(os.path.join(lims_root, 'think')):
        fail('找不到 jewelry-qms 平台根目录(需含 think 入口)。用 --lims-root 指定,或把本目录置于 jewelry-qms/tests/preimport_golden/。')
        return report()

    think = os.path.join(lims_root, 'think')

    # 1. 夹具文件齐全性
    print('-- 检查 1:夹具文件齐全 --')
    for f in REQUIRED_FILES:
        p = os.path.join(FIXTURE, f)
        if os.path.isfile(p):
            ok(f)
        else:
            fail(f'缺失 {f}')
    if failed:
        return report()

    # 2. 夹具文件指纹(信息项,感知金样是否被改动;镜像套装侧 run_golden_test.py)
    print()
    print('-- 夹具文件指纹(信息项)--')
    for f in REQUIRED_FILES:
        print(f'  md5:{md5(os.path.join(FIXTURE, f))}  {f}')

    # 3. dry-run
    print()
    print('-- 检查 2:qms:preimport-package dry-run --')
    os.makedirs(EXPECTED, exist_ok=True)
    report_json = os.path.join(EXPECTED, '_last_dryrun_report.json')
    if os.path.exists(report_json):
        os.remove(report_json)
    cmd = [args.php, think, 'qms:preimport-package',
           '--package-dir', FIXTURE, '--json-out', report_json]
    try:
        r = subprocess.run(cmd, capture_output=True, text=True, cwd=lims_root)
    except FileNotFoundError:
        fail(f'无法执行 {args.php}(PHP 未安装或不在 PATH)')
        return report()
    print(f'  exit code: {r.returncode}')
    if r.returncode != 0:
        fail(f'dry-run 非零退出。stdout/stderr 末尾:\n{(r.stdout + r.stderr)[-800:]}')
        return report()
    ok('dry-run 零退出')

    if not os.path.isfile(report_json):
        fail('未生成 JSON 报告(--json-out)')
        return report()

    report_data = json.load(open(report_json, encoding='utf-8'))
    status = str(report_data.get('status', '-'))
    findings = report_data.get('findings', []) or []
    finding_ids = sorted({str(f.get('id', '-')) for f in findings})
    high = [f for f in findings if str(f.get('severity')) == 'high']
    print(f'  status: {status}')
    print(f'  findings: {len(findings)} 条 (high={len(high)}); ids={finding_ids}')

    if status in ('failed', 'blocked'):
        fail(f'dry-run 状态={status}(应为非 failed/blocked)')

    # 4. 发现项与钉定基线一致
    print()
    print('-- 检查 3:发现项与钉定基线一致 --')
    if os.path.isfile(EXPECTED_FINDINGS):
        base = json.load(open(EXPECTED_FINDINGS, encoding='utf-8'))
        expected_ids = sorted(base.get('finding_ids', []))
        if finding_ids == expected_ids:
            ok(f'发现项 id 集合与基线一致(={finding_ids})')
        else:
            fail(f'发现项漂移:基线={expected_ids} 实际={finding_ids}')
            for f in findings:
                print(f'    [{f.get("severity", "-")}] {f.get("id", "-")}: {f.get("message", "")}')
    else:
        print(f'  (尚未钉定基线,默认期望=空集;实际={finding_ids})')
        if finding_ids:
            fail('存在发现项但无基线可比对;先排查或用 --pin 钉定')
        else:
            ok('无发现项(可用 --pin 钉定空基线纳入后续回归)')
            if args.pin:
                pin = {"status_observed": status, "finding_ids": []}
                json.dump(pin, open(EXPECTED_FINDINGS, 'w', encoding='utf-8'), ensure_ascii=False, indent=2)
                print(f'  已钉定基线 → {EXPECTED_FINDINGS}')

    return report()


def report():
    print()
    if failed == 0:
        print('ALL GREEN — 乙方侧预导入金样 dry-run 回归通过')
        return 0
    print(f'FAIL: {failed} 项未通过(参考上方 FAIL 行)')
    return 1


if __name__ == '__main__':
    sys.exit(main())
