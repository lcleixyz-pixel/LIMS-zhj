#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd -P)"
PROJECT_DIR="$(cd "$SCRIPT_DIR/../.." && pwd -P)"
COMPOSE_FILE="$SCRIPT_DIR/compose.yaml"
ENV_FILE="${1:-${QMS_ENV_FILE:-/www/server/jewelry-qms-experience/shared/.env}}"

if [[ ! -f "$ENV_FILE" ]]; then
    echo "FAIL: environment file not found: $ENV_FILE" >&2
    exit 1
fi

HOST_PORT="$(awk -F= '/^[[:space:]]*QMS_HOST_PORT=/{gsub(/[[:space:]]/, "", $2); print $2; exit}' "$ENV_FILE")"
HOST_PORT="${HOST_PORT:-18010}"

compose=(docker compose --env-file "$ENV_FILE" -f "$COMPOSE_FILE")

echo "== Compose services =="
"${compose[@]}" ps

app_id="$("${compose[@]}" ps -q app)"
db_id="$("${compose[@]}" ps -q db)"

if [[ -z "$app_id" || -z "$db_id" ]]; then
    echo "FAIL: app or db container is not running" >&2
    exit 1
fi

echo "== Application loopback HTTP =="
http_code="$(curl -sS -o /dev/null -w '%{http_code}' "http://127.0.0.1:${HOST_PORT}/login/index")"
echo "HTTP $http_code http://127.0.0.1:${HOST_PORT}/login/index"
if [[ "$http_code" != "200" ]]; then
    echo "FAIL: application loopback endpoint did not return HTTP 200" >&2
    exit 1
fi

echo "== Database health =="
db_health="$(docker inspect --format '{{if .State.Health}}{{.State.Health.Status}}{{else}}none{{end}}' "$db_id")"
echo "db health=$db_health"
if [[ "$db_health" != "healthy" ]]; then
    echo "FAIL: database container is not healthy" >&2
    exit 1
fi

regulatory_table_count="$("${compose[@]}" exec -T db sh -lc 'MYSQL_PWD="$MYSQL_PASSWORD" mysql -u"$MYSQL_USER" "$MYSQL_DATABASE" -Nse "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='\''$MYSQL_DATABASE'\'' AND table_name IN ('\''qms_external_change_events'\'','\''qms_external_change_candidates'\'','\''qms_regulatory_monitor_runs'\'')"')"
echo "regulatory tables=$regulatory_table_count/3"
if [[ "$regulatory_table_count" != "3" ]]; then
    echo "FAIL: regulatory monitor database tables are incomplete" >&2
    exit 1
fi

echo "== Container PHP =="
"${compose[@]}" exec -T app php -v | sed -n '1,2p'

echo "== Published ports =="
app_port="$(docker port "$app_id" 8000/tcp)"
echo "app 8000/tcp -> $app_port"
if [[ "$app_port" != "127.0.0.1:${HOST_PORT}" ]]; then
    echo "FAIL: app must publish only to 127.0.0.1:${HOST_PORT}" >&2
    exit 1
fi

db_bindings="$(docker inspect --format '{{json .HostConfig.PortBindings}}' "$db_id")"
echo "db port bindings=$db_bindings"
if [[ "$db_bindings" != "null" && "$db_bindings" != "{}" ]]; then
    echo "FAIL: MySQL must not publish any host port" >&2
    exit 1
fi

echo "== Disk usage =="
df -h /
docker system df

echo "PASS: experience environment read-only verification completed"
