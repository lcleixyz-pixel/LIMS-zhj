#!/bin/sh
set -eu

root=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)
compose_file="$root/compose.yaml"
experience_compose_file="$root/deploy/experience/compose.yaml"
experience_env_example="$root/deploy/experience/.env.example"

assert_app_environment() {
    expected=$1
    python3 -c '
import json
import sys
config = json.load(sys.stdin)
actual = str(config["services"]["app"]["environment"].get("REGULATORY_MONITOR_ENABLED", ""))
if actual != sys.argv[1]:
    raise SystemExit(f"app REGULATORY_MONITOR_ENABLED expected {sys.argv[1]}, got {actual!r}")
' "$expected"
}

default_config=$(env -u REGULATORY_MONITOR_ENABLED docker compose -f "$compose_file" config --format json)
printf '%s\n' "$default_config" | assert_app_environment 0 || {
    echo 'Compose must pass REGULATORY_MONITOR_ENABLED=0 when the host variable is missing' >&2
    exit 1
}

enabled_config=$(REGULATORY_MONITOR_ENABLED=1 docker compose -f "$compose_file" config --format json)
printf '%s\n' "$enabled_config" | assert_app_environment 1 || {
    echo 'Compose must pass REGULATORY_MONITOR_ENABLED=1 when explicitly enabled' >&2
    exit 1
}

experience_default_config=$(
    env -u REGULATORY_MONITOR_ENABLED \
        QMS_IMAGE_TAG=regulatory-monitor-test \
        MYSQL_PASSWORD=regulatory-monitor-test \
        MYSQL_ROOT_PASSWORD=regulatory-monitor-root-test \
        docker compose -f "$experience_compose_file" config --format json
)
printf '%s\n' "$experience_default_config" | assert_app_environment 0 || {
    echo 'Experience Compose must pass REGULATORY_MONITOR_ENABLED=0 by default' >&2
    exit 1
}

experience_enabled_config=$(
    env REGULATORY_MONITOR_ENABLED=1 \
        QMS_IMAGE_TAG=regulatory-monitor-test \
        MYSQL_PASSWORD=regulatory-monitor-test \
        MYSQL_ROOT_PASSWORD=regulatory-monitor-root-test \
        docker compose -f "$experience_compose_file" config --format json
)
printf '%s\n' "$experience_enabled_config" | assert_app_environment 1 || {
    echo 'Experience Compose must pass REGULATORY_MONITOR_ENABLED=1 when explicitly enabled' >&2
    exit 1
}
grep -F 'REGULATORY_MONITOR_ENABLED=0' "$experience_env_example" >/dev/null || {
    echo 'Experience environment example must document the safe disabled default' >&2
    exit 1
}

echo 'regulatory_monitor_compose_smoke passed'
