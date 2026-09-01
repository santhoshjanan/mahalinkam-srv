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

# Refuse to boot a production container with debug output enabled — an operator
# who copied the dev .env would otherwise serve stack traces on :8080.
case "${APP_DEBUG:-false}" in
  true|True|TRUE|1|on|On)
    if [ "${APP_ENV:-production}" = "production" ]; then
      echo "FATAL: APP_DEBUG is enabled while APP_ENV=production. Set APP_DEBUG=false in .env." >&2
      exit 1
    fi
    echo "WARNING: APP_DEBUG is enabled." >&2
    ;;
esac

php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache

exec "$@"
