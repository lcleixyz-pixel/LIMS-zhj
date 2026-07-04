#!/usr/bin/env bash
set -euo pipefail

cd /app

if [[ ! -d vendor/topthink ]]; then
  composer install --no-interaction --prefer-dist
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

exec "$@"
