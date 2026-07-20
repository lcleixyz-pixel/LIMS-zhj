#!/usr/bin/env bash
set -euo pipefail
DB_CONT="${1:?db container name}"
echo "== pre: normalize blank employee_number/email to NULL (test stack only) =="
docker exec -i "$DB_CONT" mysql -uroot jewelry_qms <<'SQL'
UPDATE employees SET employee_number = NULL WHERE employee_number = '';
UPDATE employees SET email = NULL WHERE email = '';
SQL
echo "== apply 20260720_must_change_password.sql =="
docker exec -i "$DB_CONT" sh -c 'mysql -uroot jewelry_qms < /qms-migrations/20260720_must_change_password.sql'
echo "== apply 20260720_docuseal_signing.sql =="
docker exec -i "$DB_CONT" sh -c 'mysql -uroot jewelry_qms < /qms-migrations/20260720_docuseal_signing.sql'
echo "== apply 20260720_wave1_uniqueness.sql (active-only) =="
docker exec -i "$DB_CONT" sh -c 'mysql -uroot jewelry_qms < /qms-migrations/20260720_wave1_uniqueness.sql'
echo "== verify =="
docker exec -i "$DB_CONT" mysql -uroot -N jewelry_qms <<'SQL'
SELECT CONCAT('col:', COLUMN_NAME) FROM information_schema.columns
 WHERE table_schema='jewelry_qms' AND table_name='users' AND column_name='must_change_password';
SELECT CONCAT('col:', COLUMN_NAME) FROM information_schema.columns
 WHERE table_schema='jewelry_qms' AND table_name='employees'
   AND COLUMN_NAME IN ('employee_number_active','email_active');
SELECT CONCAT('idx:', INDEX_NAME) FROM information_schema.statistics
 WHERE table_schema='jewelry_qms' AND table_name='employees'
   AND INDEX_NAME IN (
     'uq_employees_employee_number','uq_employees_email',
     'uq_employees_employee_number_active','uq_employees_email_active'
   )
 GROUP BY INDEX_NAME;
SELECT CONCAT('tbl:', TABLE_NAME) FROM information_schema.tables
 WHERE table_schema='jewelry_qms' AND table_name IN ('document_signing_rounds','equipment_period_checks');
SQL
echo "migrations applied OK"
