#!/bin/sh
set -eu

for migration in /qms-migrations/*.sql; do
    [ -f "$migration" ] || continue
    echo "[jewelry-qms] applying migration: $(basename "$migration")"
    MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysql --protocol=socket -uroot --database="$MYSQL_DATABASE" < "$migration"
done
