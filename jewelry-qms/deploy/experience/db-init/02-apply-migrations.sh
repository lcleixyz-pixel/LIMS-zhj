#!/usr/bin/env bash
set -Eeo pipefail

for migration in /qms-migrations/*.sql; do
    echo "[jewelry-qms] applying migration: $(basename "$migration")"
    docker_process_sql --database="$MYSQL_DATABASE" < "$migration"
done
