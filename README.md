# mahalinkam

[![CI](https://github.com/santhoshjanan/mahalinkam-srv/actions/workflows/ci.yml/badge.svg)](https://github.com/santhoshjanan/mahalinkam-srv/actions/workflows/ci.yml)
[![Latest release](https://img.shields.io/github/v/release/santhoshjanan/mahalinkam-srv?sort=semver&label=release)](https://github.com/santhoshjanan/mahalinkam-srv/releases/latest)
[![Deploy: Docker](https://img.shields.io/badge/deploy-Docker-2496ED?logo=docker&logoColor=white)](#production-quick-start)
[![License: MIT](https://img.shields.io/badge/license-MIT-green)](https://github.com/santhoshjanan/mahalinkam-srv/blob/main/composer.json)

<!--
  CI is live now. The release badge populates once you push a `v*.*.*` tag (the
  `release` job in ci.yml cuts a GitHub Release with generated notes).
  If you publish a container image, add e.g.:
  [![Image](https://img.shields.io/badge/ghcr.io-mahalinkam--srv-blue?logo=github)](https://github.com/santhoshjanan/mahalinkam-srv/pkgs/container/mahalinkam-srv)
-->

Self-hosted, multi-user bookmark manager with a companion browser extension. This
repository is the **server** — a Laravel application that is the single source of
truth for all bookmarks, folders and tags. It serves a web UI (Inertia + Vue) for
humans and a token-authenticated JSON API for the browser extension; both talk to
the same core, so a bookmark saved from the extension and one saved in the web UI
are the same record, deduplicated by normalized URL.

---

## Production quick start

**Requirements:** Docker and Docker Compose **>= 2.24** (the `env_file` long-form
syntax in `compose.prod.yaml` needs it). Nothing else — no host PHP, Node or
database.

```bash
git clone <this-repo-url> mahalinkam-srv
cd mahalinkam-srv
cp .env.production.example .env
```

Edit `.env` (the production template already sets `APP_ENV=production`,
`APP_DEBUG=false`, `SESSION_SECURE_COOKIE=true` and `TRUSTED_PROXIES=*`):

```dotenv
APP_URL=https://your.domain
APP_KEY=            # set this — see below
```

The container refuses to start if `APP_DEBUG` is truthy while
`APP_ENV=production`, so a stray debug flag can't leak stack traces on :8080.

Generate a **persistent** `APP_KEY` once and paste it into `.env`. If you skip
this the entrypoint generates an ephemeral key on every container recreate, which
invalidates all sessions, signed URLs and encrypted data:

```bash
docker compose -f compose.prod.yaml run --rm app php artisan key:generate --show
# copy the base64:... value into .env as APP_KEY=base64:...
```

Start the stack (an `app` web container on port 8080 plus a `worker` container
for the queue):

```bash
docker compose -f compose.prod.yaml up -d
```

**TLS / reverse proxy:** this stack ships none. Point your own reverse proxy
(Traefik, Caddy, nginx, …) at port **8080** for TLS termination and routing. The
container's internal FPM + nginx is the application server, not an edge proxy.
Leave `TRUSTED_PROXIES` at its default `*` (or set it to your proxy's IP/CIDR)
so the app detects HTTPS from the proxy's `X-Forwarded-Proto` header — without
it `Request::isSecure()` is false and absolute URLs come out as `http://`.

Create the first user (there is no web installer):

```bash
docker compose -f compose.prod.yaml exec app \
  php artisan mahalinkam:make-user you@example.com "Your Name"
```

You will be prompted for a password (min 8 chars). Pass `--password=...` to skip
the prompt. The account is created already email-verified.

---

## Configuration

All settings have safe defaults, so a bare start works with no edits. Set these in
`.env`.

| Var | Default | Effect |
|---|---|---|
| `SIGNUPS_ENABLED` | `true` | When `false`, the self-service registration route and its UI are disabled — create users with `mahalinkam:make-user`. |
| `METADATA_FETCH_ENABLED` | `true` | When `false`, new bookmarks get `metadata_status=skipped` and no outbound fetch job is dispatched. |
| `METADATA_FETCH_TIMEOUT` | `8` | Total seconds allowed per metadata fetch. |
| `METADATA_FETCH_MAX_BYTES` | `524288` | Max response body read per metadata fetch (bytes). |
| `IMPORT_MAX_FILE_MB` | `20` | Upload size cap for import files (MB). |
| `TRUSTED_PROXIES` | `*` | Comma-separated proxy IPs/CIDRs whose `X-Forwarded-*` headers are trusted, or `*` when your reverse proxy is the only ingress. Needed so HTTPS is detected behind TLS termination — otherwise Laravel emits `http://` URLs and drops secure cookies. |
| `DB_CONNECTION` | `sqlite` | Database driver: `sqlite` \| `mysql` \| `pgsql`. |
| `QUEUE_CONNECTION` | `database` | Queue driver. The bundled `worker` container runs `queue:work`. |

Standard Laravel vars (`APP_URL`, `MAIL_*`, etc.) pass through untouched.

---

## Database

**SQLite by default.** No extra service; the file lives in the
`mahalinkam-database` volume at `/var/www/html/database/database.sqlite` and the
entrypoint creates and migrates it on first boot.

**To use Postgres:** in `compose.prod.yaml` uncomment the `db` service, the
`depends_on: [db]` line under `app`, and the `mahalinkam-pgdata` volume; then set
in `.env`:

```dotenv
DB_CONNECTION=pgsql
DB_HOST=db
DB_PORT=5432
DB_DATABASE=mahalinkam
DB_USERNAME=mahalinkam
DB_PASSWORD=change-me
```

MySQL works the same way with `DB_CONNECTION=mysql` and your own `mysql` service.
All migrations and queries are portable across the three engines.

---

## Backups

There is no application state outside the database and the `storage/` directory.
Back up:

- **The database** — the `mahalinkam-database` volume (SQLite), or a `pg_dump` /
  `mysqldump` of your external DB.
- **`storage/`** — the `mahalinkam-storage` volume (uploaded import files, logs).

Both volume names are defined at the bottom of `compose.prod.yaml`.

---

## Browser extension pairing

1. Sign in to the web UI.
2. Go to **`/settings/tokens`** and create a token. The plaintext token is shown
   **once** — copy it immediately.
3. In the extension's options, paste your server URL (the `APP_URL`) and the
   token.

Revoke a token from the same page; it takes effect immediately.

---

## Development

Via [Laravel Sail](https://laravel.com/docs/sail) — Docker only, no host PHP or
Node required. All commands run inside the Sail containers.

```bash
cp .env.example .env
./vendor/bin/sail up -d
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate
./vendor/bin/sail npm install
./vendor/bin/sail npm run dev          # Vite dev server
./vendor/bin/sail artisan queue:work   # process metadata / import jobs
```

First dev user:

```bash
./vendor/bin/sail artisan mahalinkam:make-user you@example.com "Your Name" --password=password
```

Run the test suite and the linter:

```bash
./vendor/bin/sail artisan test
./vendor/bin/sail composer lint
```

The dev stack (`compose.yaml`) also starts Postgres and MySQL services so the
suite can be run against all three engines locally; CI does this on every push.
