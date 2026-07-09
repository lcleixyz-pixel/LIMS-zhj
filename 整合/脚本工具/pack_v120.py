"""打包 v1.20 实验室体系技能套装"""
import zipfile, os, sys

BASE = r"C:\Users\Martyr\OneDrive\桌面\参考\技能套装\最新版"
OUT = r"C:\Users\Martyr\OneDrive\桌面\参考\技能套装\历史版本\实验室体系技能套装-v1.20.zip"

EXCLUDE = {
    "评估报告-v1.16-全链路跑测.md",
    "评估报告-v1.18-test-prompts评估.md",
    "Darwin评估-v1.19-9维评分.md",
    "Darwin评估-v1.20-修复验证.md",
    "extract_zip.py",
    "pack_v120.py",
    "__pycache__",
}

count = 0
size_total = 0

with zipfile.ZipFile(OUT, 'w', zipfile.ZIP_DEFLATED) as zf:
    for root, dirs, files in os.walk(BASE):
        # Skip __pycache__
        dirs[:] = [d for d in dirs if d != "__pycache__"]

        for f in files:
            if f in EXCLUDE or f.endswith('.pyc'):
                continue

            full = os.path.join(root, f)
            arcname = os.path.relpath(full, BASE)

            # Ensure UTF-8 filenames in zip
            zf.write(full, arcname)
            count += 1
            size_total += os.path.getsize(full)
            print(f"  + {arcname}")

print(f"\n打包完成: {count} 文件, {size_total:,} bytes → {OUT}")
print(f"压缩后: {os.path.getsize(OUT):,} bytes")
