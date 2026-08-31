# syntax=docker/dockerfile:1

# ---------------------------------------------------------------------------
# Stage 1 — install PHP dependencies without dev packages.
# (Runs first: the Vite build imports tightenco/ziggy from vendor/.)
# ---------------------------------------------------------------------------
FROM composer:2 AS vendor
WORKDIR /app

COPY composer.json composer.lock ./
COPY artisan ./artisan
COPY app/ ./app/
COPY bootstrap/ ./bootstrap/
COPY config/ ./config/
COPY database/ ./database/
COPY routes/ ./routes/
COPY public/ ./public/

RUN composer install \
        --no-dev \
        --no-interaction \
        --prefer-dist \
        --optimize-autoloader \
        --no-scripts

# ---------------------------------------------------------------------------
# Stage 2 — build the front-end assets (produces public/build/).
# ---------------------------------------------------------------------------
FROM node:22-alpine AS assets
WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci

COPY . .
COPY --from=vendor /app/vendor ./vendor
RUN npm run build

# ---------------------------------------------------------------------------
# Stage 3 — runtime: serversideup PHP 8.3 with bundled FPM + nginx.
# (This internal nginx IS the app server, not an edge reverse proxy.)
# ---------------------------------------------------------------------------
FROM serversideup/php:8.3-fpm-nginx

# Sane, overridable image defaults.
ENV APP_ENV=production \
    APP_DEBUG=false \
    AUTORUN_ENABLED=false \
    PHP_OPCACHE_ENABLE=1

USER www-data
WORKDIR /var/www/html

# Application source, then the built artefacts from the earlier stages.
COPY --chown=www-data:www-data . .
COPY --chown=www-data:www-data --from=vendor /app/vendor ./vendor
COPY --chown=www-data:www-data --from=assets /app/public/build ./public/build

# A writable .env so `key:generate --force` has a file to update when no
# APP_KEY is supplied. Real environment variables (compose env_file /
# environment) always take precedence over these file values.
RUN rm -f bootstrap/cache/packages.php bootstrap/cache/services.php \
    && cp .env.example .env \
    && php artisan package:discover --ansi

# Custom init: runs the three artisan cache/migration steps, then hands off
# to the serversideup entrypoint chain which starts FPM + nginx.
COPY --chown=www-data:www-data docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 8080

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["docker-php-serversideup-entrypoint", "/init"]
