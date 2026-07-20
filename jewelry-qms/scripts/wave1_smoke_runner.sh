#!/usr/bin/env bash
# wave1 smoke runner — A1 判定口径
# PASS: exit=0 且输出含成功哨兵（passed / OK / smoke通过，大小写不敏感）
# FAIL: exit≠0，或输出含 Exception] / Fatal error，或缺哨兵
set -euo pipefail

LIST="${1:?smoke list file}"
OUT_LOG="${2:?raw output path}"
OUT_SUM="${3:?summary md path}"
PROJECT="${WAVE1_COMPOSE_PROJECT:-lims-zhj-wave1-smoke-20260720}"
COMPOSE_FILE="${WAVE1_COMPOSE_FILE:-compose.wave1-smoke.yaml}"
ROOT="$(cd "$(dirname "$0")/.." && pwd)"

judge_body() {
  local exit_code="$1"
  local body="$2"
  if echo "$body" | grep -E -q 'Exception\]|Fatal error'; then
    echo FAIL
    return
  fi
  if [[ "$exit_code" != "0" ]]; then
    echo FAIL
    return
  fi
  if echo "$body" | grep -E -iq '(^|[^A-Za-z])(passed|OK|smoke通过)([^A-Za-z]|$)'; then
    echo PASS
    return
  fi
  echo FAIL
}

: > "$OUT_LOG"
pass=0
fail=0
fail_list=()
total=0

while IFS= read -r name || [[ -n "$name" ]]; do
  [[ -z "$name" ]] && continue
  total=$((total + 1))
  {
    echo "===== [${total}] ${name} ====="
    set +e
    body=$(cd "$ROOT" && docker compose -f "$COMPOSE_FILE" -p "$PROJECT" exec -T app php "tests/${name}" </dev/null 2>&1)
    ec=$?
    set -e
    printf '%s\n' "$body"
    echo "EXIT_CODE=${ec}"
    result=$(judge_body "$ec" "$body")
    echo "RESULT=${result}"
  } | tee -a "$OUT_LOG" >/dev/null

  # re-read last RESULT from log tail for counting
  result=$(tail -n 5 "$OUT_LOG" | awk -F= '/^RESULT=/{print $2}' | tail -1)
  if [[ "$result" == "PASS" ]]; then
    pass=$((pass + 1))
  else
    fail=$((fail + 1))
    fail_list+=("$name")
  fi
done < "$LIST"

{
  echo "# 全量 smoke 汇总（A1 判定口径）"
  echo
  echo "- 清单：\`$(basename "$LIST")\`（${total}）"
  echo "- 判定：PASS = exit=0 且哨兵(passed/OK/smoke通过)；Exception]/Fatal error → FAIL"
  echo "- 统计：**PASS=${pass} / FAIL=${fail} / TOTAL=${total}**"
  echo "- 原始输出：\`$(basename "$OUT_LOG")\`"
  echo
  echo "## FAIL 清单"
  echo
  if ((${#fail_list[@]} == 0)); then
    echo "（无）"
  else
    for f in "${fail_list[@]}"; do
      echo "- \`$f\`"
    done
  fi
} > "$OUT_SUM"

echo "PASS=${pass} FAIL=${fail} TOTAL=${total}"
