#!/usr/bin/env sh
set -e

if [ -n "${DB_HOST:-}" ]; then
    echo "Waiting for database at ${DB_HOST}:${DB_PORT:-3306}..."
    until php -r '$host = getenv("DB_HOST"); $port = (int) (getenv("DB_PORT") ?: 3306); $socket = @fsockopen($host, $port, $errno, $errstr, 2); if ($socket) { fclose($socket); exit(0); } exit(1);'; do
        sleep 2
    done
fi

mkdir -p storage/app storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
chmod -R ug+rw storage bootstrap/cache

php artisan package:discover --ansi

if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
    php artisan migrate --force
fi

exec "$@"
