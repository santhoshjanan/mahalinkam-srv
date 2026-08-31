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
# Pinned by digest for reproducible builds (tag: v4.5.x, PHP 8.3.33).
FROM serversideup/php:8.3-fpm-nginx@sha256:9c97fa2a6f6f8b910d95a0c950ff949963a2d0326f642c171e73feb097f521ad

# Sane, overridable image defaults. The base image ships a HEALTHCHECK that
# curls http://localhost:${NGINX_HTTP_PORT}${HEALTHCHECK_PATH}; point it at
# Laravel's real health route so it exercises PHP-FPM, not just nginx.
ENV APP_ENV=production \
    APP_DEBUG=false \
    AUTORUN_ENABLED=false \
    PHP_OPCACHE_ENABLE=1 \
    HEALTHCHECK_PATH=/up

USER www-data
WORKDIR /var/www/html

# Application source, then the built artefacts from the earlier stages.
COPY --chown=www-data:www-data . .
COPY --chown=www-data:www-data --from=vendor /app/vendor ./vendor
COPY --chown=www-data:www-data --from=assets /app/public/build ./public/build

# fallback env / key:generate target; real env vars always override (immutable Dotenv)
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
