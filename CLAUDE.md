# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## No host PHP

There is no PHP, Composer, Node or Pest on the host. **Every** command runs
through Laravel Sail:

```bash
./vendor/bin/sail artisan test
./vendor/bin/sail artisan test --filter='UrlNormalizer'
./vendor/bin/sail composer lint          # Pint in --test mode; must be clean
./vendor/bin/sail npm run dev             # Vite dev server
./vendor/bin/sail npm run build
./vendor/bin/sail artisan queue:work      # metadata + import jobs
./vendor/bin/sail artisan mahalinkam:make-user email "Name" --password=secret
```

`./vendor/bin/sail up -d` first if the containers aren't running.

## Architecture — the big picture

**Two clients, one core.** `app/Http/Controllers/Web/*` (Inertia pages, session
auth) and `app/Http/Controllers/Api/*` (`auth:sanctum` bearer tokens,
`throttle:120,1`) are thin. Both call the **same** `app/Services/*` classes and
the **same** `app/Policies/*`. Never implement a business rule in a controller —
if a rule exists in the Web path it must be the same code in the API path.

- **`app/Services/UrlNormalizer.php`** — the URL-dedup contract. Its behavior is
  pinned by `app/Support/url-normalizer-fixtures.php`, a shared expectation table
  the browser-extension port keeps a byte-identical copy of; changing
  normalization means changing both. `MAX_LENGTH = 766` matches the
  `bookmarks.normalized_url` column width (largest value that keeps the
  composite `(user_id, normalized_url)` unique index inside MySQL's utf8mb4
  3072-byte limit: `766 * 4 + 8` for the bigint `user_id`). Dedup is enforced by the
  `bookmarks (user_id, normalized_url)` unique index, and `BookmarkService::save()`
  is idempotent — saving the same URL twice updates, never duplicates.
- **`app/Services/FolderService.php`** — `MAX_DEPTH = 10`; move operations run a
  cycle guard (can't move a folder into itself or a descendant) and a subtree
  height check. Deleting a folder re-parents its child folders and sets its
  bookmarks' `folder_id` to `null` — it never deletes bookmarks.
- **`app/Services/TagService.php`** — tags are case-insensitive via a `name_lower`
  column maintained by the service; `pruneOrphans()` deletes a user's tags that
  no longer have bookmarks.
- **Queue jobs:** `app/Jobs/FetchBookmarkMetadata.php` (outbound fetch via
  `MetadataFetcher` + `PrivateNetworkGuard` SSRF checks, gated by
  `config('mahalinkam.metadata.enabled')`) and `app/Jobs/ProcessImport.php`
  (chunked bulk import, error-capped). **Tests that create bookmarks must
  `Queue::fake()`** or `Http::fake()` — otherwise they trigger a real metadata
  fetch.
- **`app/Support/Importers/*`** (txt, csv, json, Netscape-HTML) and
  **`app/Support/Exporters/*`** (html, csv, json) round-trip each other.

## Portability rules (spec §11)

Tests run on SQLite; CI also runs the full suite against MySQL and Postgres
(`.github/workflows/ci.yml`), so:

- No DB-specific SQL functions and no functional indexes. `lower()` in a `WHERE`
  or `ORDER BY` for search (as in `app/Queries/BookmarkListQuery.php`) is fine;
  case-insensitive uniqueness uses a plain `name_lower` column instead.
- No raw DB-engine features in migrations (no `ILIKE`, no
  `ON UPDATE CURRENT_TIMESTAMP`). Use `$table->json()` for JSON.
- Enum-like columns are stored as `string` and cast to a PHP enum
  (`app/Enums/MetadataStatus.php`), not `$table->enum()`.

## Docker

- **Dev:** Sail's `compose.yaml` (app + Postgres + MySQL).
- **Production:** `compose.prod.yaml` — an `app` container (internal FPM + nginx,
  one HTTP port **8080**) and a `worker` container (`queue:work`). **No bundled
  edge reverse proxy / TLS** — the operator points their own proxy at 8080. Image
  is `Dockerfile` (3 stages: composer vendor, npm assets, serversideup PHP 8.3
  runtime); `docker/entrypoint.sh` runs `migrate --force` then
  `config:cache && route:cache && view:cache`. The `worker` overrides the
  entrypoint so it never runs migrations.

## Spec and plan

Design spec and implementation plan live in the sibling root repo, not here:

- `../docs/superpowers/specs/2026-08-31-mahalinkam-design.md` (§9 env vars, §11 portability)
- `../docs/superpowers/plans/2026-08-31-mahalinkam-server.md`
