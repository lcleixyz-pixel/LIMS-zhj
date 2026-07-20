#!/usr/bin/env bash
# 本机只读预览服务（不写库）。浏览器打开打印的 URL。
set -euo pipefail
cd "$(dirname "$0")"
PORT="${1:-8765}"
echo "批准人预览层（只读）→ http://127.0.0.1:${PORT}/index.html"
echo "Ctrl+C 结束。请勿把此页当作正式批准入口。"
exec python3 -m http.server "$PORT" --bind 127.0.0.1
