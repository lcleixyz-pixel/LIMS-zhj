#!/usr/bin/env bash
set -euo pipefail

: "${QMS_DB_CONTAINER:?QMS_DB_CONTAINER is required}"
: "${QMS_DB_NAME:?QMS_DB_NAME is required}"
: "${QMS_DB_USER:?QMS_DB_USER is required}"
: "${QMS_MIGRATION_SHA256:?QMS_MIGRATION_SHA256 is required}"

migration="${QMS_MIGRATION_FILE:-database/migrations/20260717_gr14_controlled_trial.sql}"
[[ -f "$migration" ]] || { echo "迁移文件不存在：$migration" >&2; exit 1; }
migration_copy="$(mktemp "${TMPDIR:-/tmp}/qms-gr14-migration.XXXXXX.sql")"
trap 'rm -f "$migration_copy"' EXIT
cp "$migration" "$migration_copy"
actual_migration_sha="$(shasum -a 256 "$migration_copy" | awk '{print $1}')"
if [[ "$actual_migration_sha" != "$QMS_MIGRATION_SHA256" ]]; then
  echo "迁移 SHA256 不一致：expected=$QMS_MIGRATION_SHA256 actual=$actual_migration_sha" >&2
  exit 1
fi

password_arg=""
if [[ -n "${QMS_DB_PASSWORD_FILE:-}" ]]; then
  [[ -f "$QMS_DB_PASSWORD_FILE" ]] || { echo "数据库密码文件不存在" >&2; exit 1; }
  password_arg="--password=$(<"$QMS_DB_PASSWORD_FILE")"
fi

if [[ -n "$password_arg" ]]; then
  docker exec -i "$QMS_DB_CONTAINER" \
    mysql -u"$QMS_DB_USER" "$password_arg" "$QMS_DB_NAME" < "$migration_copy"
else
  docker exec -i "$QMS_DB_CONTAINER" \
    mysql -u"$QMS_DB_USER" "$QMS_DB_NAME" < "$migration_copy"
fi
echo "G-R14 数据库迁移已执行并由 SQL 自身完成幂等校验"
