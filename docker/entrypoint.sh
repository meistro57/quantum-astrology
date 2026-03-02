#!/usr/bin/env bash
set -euo pipefail

APP_ROOT="/var/www/html"
DB_DRIVER_VALUE="${DB_DRIVER:-sqlite}"

if [ "$DB_DRIVER_VALUE" = "mysql" ]; then
    DB_HOST_VALUE="${DB_HOST:-db}"
    DB_PORT_VALUE="${DB_PORT:-3306}"
    echo "Waiting for MySQL at ${DB_HOST_VALUE}:${DB_PORT_VALUE}..."
    for _ in $(seq 1 60); do
        if nc -z "${DB_HOST_VALUE}" "${DB_PORT_VALUE}" >/dev/null 2>&1; then
            break
        fi
        sleep 2
    done
fi

if [ -f "${APP_ROOT}/tools/migrate.php" ]; then
    echo "Running database migrations..."
    php "${APP_ROOT}/tools/migrate.php"
fi

# Ensure report temp/cache folders are writable by web + CLI users.
mkdir -p \
    "${APP_ROOT}/storage/cache/mpdf" \
    "${APP_ROOT}/storage/cache/mpdf/mpdf" \
    "${APP_ROOT}/storage/cache/mpdf-runtime" \
    "${APP_ROOT}/storage/cache/mpdf-runtime/mpdf"
chmod -R 0777 "${APP_ROOT}/storage/cache/mpdf" "${APP_ROOT}/storage/cache/mpdf-runtime" || true

exec "$@"
