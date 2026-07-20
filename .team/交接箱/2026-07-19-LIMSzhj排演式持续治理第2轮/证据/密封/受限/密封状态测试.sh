#!/usr/bin/env bash
set -Eeuo pipefail

db_container="lims-zhj-rehearsal-r2-blind-20260719-db-1"
app_container="lims-zhj-rehearsal-r2-blind-20260719-app-1"

fail() {
  echo "[FAIL] $1" >&2
  exit 1
}

expect_count() {
  local label="$1"
  local sql="$2"
  local expected="$3"
  local actual
  actual="$(docker exec "$db_container" mysql -uroot jewelry_qms -N -e "$sql")"
  [[ "$actual" == "$expected" ]] || fail "$label expected=$expected actual=$actual"
  echo "[PASS] $label"
}

env_json="$(docker inspect "$app_container" --format '{{json .Config.Env}}')"
echo "$env_json" | jq -e 'index("QMS_REHEARSAL_ROLE=blind") != null' >/dev/null ||
  fail "8014 role is not blind"
echo "$env_json" | jq -e 'index("QMS_REHEARSAL_RUN_ID=SIM-GOV-R2-20260719-BLIND") != null' >/dev/null ||
  fail "8014 run id mismatch"
expect_count "83 base tables" \
  "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='jewelry_qms' AND table_type='BASE TABLE'" \
  "83"

docker exec "$app_container" php tests/qms_rehearsal_sim_data_guard_smoke.php >/dev/null ||
  fail "SIM-only guard"
echo "[PASS] SIM-only guard"

expect_count "sealed templates installed" \
  "SELECT COUNT(*) FROM record_form_templates WHERE id IN ('SIM-BLIND-TPL-RPT-001','SIM-BLIND-TPL-RAW-001')" \
  "2"
expect_count "sealed report and record instances installed" \
  "SELECT COUNT(*) FROM record_form_instances WHERE id IN ('SIM-BLIND-RPT-DB65-001','SIM-BLIND-RPT-CNAS-001','SIM-BLIND-RPT-AUTH-001','SIM-BLIND-RAW-SEM-001') AND status='locked' AND is_simulation=1 AND trial_batch='SIM-GOV-R2-20260719-BLIND'" \
  "4"
expect_count "out-of-list report carries prohibited CMA state" \
  "SELECT COUNT(*) FROM record_form_instances WHERE id='SIM-BLIND-RPT-DB65-001' AND JSON_UNQUOTE(JSON_EXTRACT(field_values,'$.one_list_status'))='out_of_list' AND JSON_EXTRACT(field_values,'$.cma_mark')=true" \
  "1"
expect_count "initial-application report carries prohibited CNAS state" \
  "SELECT COUNT(*) FROM record_form_instances WHERE id='SIM-BLIND-RPT-CNAS-001' AND JSON_UNQUOTE(JSON_EXTRACT(field_values,'$.cnas_state'))='initial_application_not_submitted' AND JSON_EXTRACT(field_values,'$.cnas_mark')=true" \
  "1"
expect_count "expired or cross-scope authority was used for issuance" \
  "SELECT COUNT(*) FROM approvals a JOIN employee_appointments p ON p.employee_id='SIM-BLIND-EMP-SIGNER-001' WHERE a.id='SIM-BLIND-APPROVAL-AUTH-001' AND a.status='approved' AND p.appointment_key='SIM-BLIND-AUTH-SIGNER-001' AND p.valid_until<'2026-07-19' AND p.status='active' AND (JSON_UNQUOTE(JSON_EXTRACT(p.appointment_scope,'$.site'))<>JSON_UNQUOTE(JSON_EXTRACT((SELECT field_values FROM record_form_instances WHERE id='SIM-BLIND-RPT-AUTH-001'),'$.site')) OR JSON_SEARCH(JSON_EXTRACT(p.appointment_scope,'$.methods'),'one',JSON_UNQUOTE(JSON_EXTRACT((SELECT field_values FROM record_form_instances WHERE id='SIM-BLIND-RPT-AUTH-001'),'$.method_standard'))) IS NULL)" \
  "1"
expect_count "locked technical record lacks mandatory trace dimensions" \
  "SELECT COUNT(*) FROM record_form_instances WHERE id='SIM-BLIND-RAW-SEM-001' AND status='locked' AND JSON_EXTRACT(field_values,'$.sample_id') IS NULL AND JSON_EXTRACT(field_values,'$.method_standard') IS NULL AND JSON_EXTRACT(field_values,'$.equipment_id') IS NULL AND JSON_EXTRACT(field_values,'$.tester_id') IS NULL AND JSON_EXTRACT(field_values,'$.reviewer_id') IS NULL AND JSON_EXTRACT(field_values,'$.observations') IS NULL" \
  "1"
expect_count "sealed audit trail installed" \
  "SELECT COUNT(*) FROM histories WHERE id LIKE 'SIM-BLIND-HISTORY-%'" \
  "4"

echo "sealed state test passed"
