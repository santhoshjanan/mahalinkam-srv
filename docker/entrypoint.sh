#!/usr/bin/env sh
set -e

# Ensure the SQLite database file exists before we try to migrate it.
if [ "${DB_CONNECTION:-sqlite}" = "sqlite" ]; then
    DB_FILE="${DB_DATABASE:-/var/www/html/database/database.sqlite}"
    [ -f "$DB_FILE" ] || touch "$DB_FILE"
fi

if [ -z "$APP_KEY" ]; then
    php artisan key:generate --force
fi

php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache

exec "$@"
