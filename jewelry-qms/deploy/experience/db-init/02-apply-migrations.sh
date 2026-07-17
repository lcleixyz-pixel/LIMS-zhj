#!/usr/bin/env bash
set -Eeuo pipefail

database="${MYSQL_DATABASE:-jewelry_qms}"
mysql_args=(--protocol=socket -uroot -hlocalhost)
if [[ -n "${MYSQL_ROOT_PASSWORD:-}" ]]; then
    export MYSQL_PWD="$MYSQL_ROOT_PASSWORD"
fi

for migration in /qms-migrations/*.sql; do
    [[ -e "$migration" ]] || continue
    echo "[jewelry-qms] applying migration: $(basename "$migration")"
    mysql "${mysql_args[@]}" "$database" < "$migration"
done
