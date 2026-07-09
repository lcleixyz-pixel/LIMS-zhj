#!/usr/bin/env python3
# cli_utils.py v1.0 — 实验室体系技能套装 脚本共享工具库
"""所有检查脚本共用的 CLI 基础设施：安全文件读取、JSON 输出、统一 argparse。"""

import sys, os, json, argparse

# ── 安全文件读取 ──────────────────────────────────────────────────
def safe_read(path, encoding='utf-8'):
    """读取文件，失败时打印错误并退出。"""
    try:
        with open(path, encoding=encoding) as f:
            return f.read()
    except FileNotFoundError:
        die(f'文件不存在: {path}')
    except PermissionError:
        die(f'无权限读取: {path}')
    except UnicodeDecodeError:
        die(f'编码错误（非UTF-8文件）: {path}')
    except OSError as e:
        die(f'读取失败: {path} ({e})')

def die(msg, code=2):
    print(f'❌ {msg}', file=sys.stderr)
    sys.exit(code)

# ── JSON 输出 ──────────────────────────────────────────────────────
def json_output(data):
    """输出结构化 JSON 并退出。"""
    print(json.dumps(data, ensure_ascii=False, indent=2))
    sys.exit(0 if data.get('passed', False) else 1)

# ── 统一 argparse ──────────────────────────────────────────────────
def standard_argparse(description, version='1.3'):
    """返回预配了 --json 和 --version 的 ArgumentParser。"""
    p = argparse.ArgumentParser(description=description)
    p.add_argument('--json', action='store_true', help='输出结构化 JSON')
    p.add_argument('--version', action='version', version=f'%(prog)s v{version}')
    return p

# ── 统一结果构建 ──────────────────────────────────────────────────
def build_result(tool, version, errors=None, warnings=None, extra=None):
    """构建标准的检查结果 dict。"""
    errors = errors or []
    warnings = warnings or []
    return {
        'tool': tool,
        'version': version,
        'errors': errors,
        'warnings': warnings,
        'extra': extra or {},
        'passed': len(errors) == 0
    }

# ── 统一输出调度 ──────────────────────────────────────────────────
def output(result, use_json):
    """根据 --json 标志调度输出。"""
    if use_json:
        json_output(result)
    else:
        tool = result['tool']
        ver = result['version']
        print(f'== {tool} v{ver} ==')
        for e in result.get('errors', []): print('  [FAIL]', e)
        for w in result.get('warnings', []): print('  [WARN]', w)
        for k, v in result.get('extra', {}).items():
            if isinstance(v, list):
                for item in v: print(f'  [{k}]', item)
            elif v is not None:
                print(f'  [{k}]', v)
        passed = result['passed']
        print('结论:', '✅ 通过' if passed else f'❌ 未通过({len(result.get("errors",[]))}项)')
        sys.exit(0 if passed else 1)
