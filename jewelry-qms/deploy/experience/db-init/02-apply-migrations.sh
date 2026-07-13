#!/usr/bin/env bash
set -Eeuo pipefail

for migration in /qms-migrations/*.sql; do
    echo "[jewelry-qms] applying migration: $(basename "$migration")"
    docker_process_sql < "$migration"
done
