#!/usr/bin/env bash
set -euo pipefail

cd /app

if [[ ! -d vendor/topthink ]]; then
  composer install --no-interaction --prefer-dist
fi

# PDF 渲染依赖：容器内 Node 模块（与宿主机隔离的 named volume）
if [[ ! -x node_modules/.bin/playwright ]] && [[ -f package.json ]]; then
  echo "[jewelry-qms] installing npm dependencies for PDF render..."
  npm ci --omit=dev
fi

if [[ "${WAIT_FOR_DB:-1}" != "0" ]]; then
  php -r '
  $host = getenv("DB_HOST") ?: "db";
  $port = getenv("DB_PORT") ?: "3306";
  $name = getenv("DB_NAME") ?: "jewelry_qms";
  $user = getenv("DB_USER") ?: "root";
  $pass = getenv("DB_PASS") ?: "";
  $charset = getenv("DB_CHARSET") ?: "utf8mb4";

  for ($i = 0; $i < 60; $i++) {
      try {
          new PDO("mysql:host={$host};port={$port};dbname={$name};charset={$charset}", $user, $pass, [
              PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
          ]);
          exit(0);
      } catch (Throwable $e) {
          sleep(2);
      }
  }

  fwrite(STDERR, "Database is not ready after 120 seconds.\n");
  exit(1);
  '
fi

# B1 周期任务（2026-07-11）：容器内 busybox crond，每天 08:00 跑 check:reminders
if [[ -f docker/crontab ]]; then
  mkdir -p /etc/crontabs
  cp docker/crontab /etc/crontabs/root
  chmod 600 /etc/crontabs/root
  crond -b -l 2
  echo "[jewelry-qms] crond started — daily check:reminders at 08:00 (log: /tmp/reminders.log)"
fi

exec "$@"
