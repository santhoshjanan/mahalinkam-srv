#!/usr/bin/env sh
set -e

# Ensure the SQLite database file exists before we try to migrate it.
if [ "${DB_CONNECTION:-sqlite}" = "sqlite" ]; then
    DB_FILE="${DB_DATABASE:-/var/www/html/database/database.sqlite}"
    [ -f "$DB_FILE" ] || touch "$DB_FILE"
fi

if [ -z "$APP_KEY" ]; then
    echo "WARNING: APP_KEY is not set. Generating an EPHEMERAL key — it changes on every" >&2
    echo "         container recreate, which invalidates sessions/signed URLs/encrypted data." >&2
    echo "         Set a persistent APP_KEY in .env for production." >&2
    php artisan key:generate --force
fi

php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache

exec "$@"
