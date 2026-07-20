#!/usr/bin/env python3
"""v1.8.1 打包脚本 —— 用正斜杠路径,确保 Linux/Mac 可解压"""
import zipfile, os, sys

root = os.path.dirname(os.path.abspath(__file__))
out = os.path.join(os.path.dirname(root), '实验室体系技能套装-v1.16.zip')

exclude_dirs = {'.git', '__pycache__'}
count = 0

with zipfile.ZipFile(out, 'w', zipfile.ZIP_DEFLATED) as zf:
    for dirpath, dirnames, filenames in os.walk(root):
        dirnames[:] = [d for d in dirnames if d not in exclude_dirs]
        for fname in filenames:
            if fname.endswith('.pyc') or fname == 'package.py':
                continue
            fpath = os.path.join(dirpath, fname)
            arcname = os.path.relpath(fpath, root).replace('\\', '/')
            zf.write(fpath, arcname)
            count += 1

size_kb = os.path.getsize(out) / 1024
# 验证: 检查 zip 内第一条路径不含反斜杠
with zipfile.ZipFile(out, 'r') as zf:
    first = zf.namelist()[0]
    if '\\' in first:
        print(f'FAIL: backslash detected in zip: {first}')
        sys.exit(1)

print(f'OK: {count} files -> {out}')
print(f'Size: {size_kb:.0f} KB')
print(f'First entry: {first}')
print('Path separator: forward slash (/)')
