#!/bin/bash
# 只读走查：分角色登录后 GET 关键页面，保存 HTML 与状态码。不提交任何业务表单。
BASE=http://127.0.0.1:8010
DIR="$(cd "$(dirname "$0")" && pwd)"
ROLES="admin qm_test auditor_test head_test staff_test"

PAGES="
dashboard/index
calendar/index
notification/index
compliance/index
document/index
doc_template/index
record_form_template/index
record_form_instance/index
record_form_instance/create
record_form_instance/reviewDashboard
audit_plan/index
audit_finding/index
management_review/index
review_action/index
capa/index
nonconformity/index
complaint/index
equipment/index
calibration/index
reference_material/index
training_plan/index
training/index
training_record/index
competency_record/index
employee_certificate/index
supplier/index
supplier/qualified
planning/index
planning/elements
planning/objectives
planning/sources
planning/clauses
planning/structures
planning/traceability
planning/regulatory-candidates
planning/change-events
planning/responsibilities
user/index
employee/index
import/index
ai_settings/index
"

for role in $ROLES; do
  jar="$DIR/cookie_$role.txt"
  outdir="$DIR/$role"
  mkdir -p "$outdir"
  rm -f "$jar"
  curl -s -c "$jar" -o /dev/null "$BASE/login"
  code=$(curl -s -b "$jar" -c "$jar" -o /dev/null -w "%{http_code}" \
    -d "username=$role&password=password" "$BASE/login")
  echo "$role login=$code" >> "$DIR/status.tsv"
  for p in $PAGES; do
    fname=$(echo "$p" | tr '/' '_')
    http=$(curl -s -b "$jar" -c "$jar" -o "$outdir/$fname.html" -w "%{http_code}" -L "$BASE/$p")
    size=$(wc -c < "$outdir/$fname.html" | tr -d ' ')
    # 记录是否被重定向回登录页（会话失效/无权限跳转）
    marker=""
    if grep -q 'name="password"' "$outdir/$fname.html" 2>/dev/null; then marker="LOGIN_PAGE"; fi
    if grep -qi '无权限\|Forbidden\|权限不足' "$outdir/$fname.html" 2>/dev/null; then marker="${marker}DENIED"; fi
    echo -e "$role\t$p\t$http\t$size\t$marker" >> "$DIR/status.tsv"
  done
done
echo DONE
