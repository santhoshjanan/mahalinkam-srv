# Changelog

All notable changes to this project are documented here. The format is based on
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project
adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

The `release` job in `.github/workflows/ci.yml` uses the section matching a
pushed `vX.Y.Z` tag as the GitHub Release body, with GitHub's auto-generated
commit/contributor notes appended below it.

## [Unreleased]

## [0.1.3] - 2026-09-01

First tagged release. **mahalinkam** is a self-hosted, multi-user bookmark
manager with a companion browser extension; this repository is the **server** —
a Laravel application that is the single source of truth for all bookmarks,
folders and tags. It serves a web UI (Inertia + Vue, session auth) and a
token-authenticated JSON API for the browser extension from the same core, so a
bookmark saved from either client is the same record, deduplicated by normalized
URL.

### Added

- **Bookmarks** — create, edit, delete; idempotent save (saving the same URL
  twice updates, never duplicates), enforced by a `(user_id, normalized_url)`
  unique index and a shared URL-normalization contract.
- **Folders** — nested up to 10 levels deep, with cycle and subtree-height
  guards on move. Deleting a folder re-parents its child folders and orphans its
  bookmarks; it never deletes bookmarks.
- **Tags** — case-insensitive, with automatic orphan pruning.
- **Metadata fetching** — background jobs fetch title/description for new
  bookmarks behind an SSRF guard that blocks private-network targets; gated by
  `METADATA_FETCH_ENABLED`.
- **Import / export** — round-trips plain-text, CSV, JSON and Netscape-HTML
  bookmark files; large imports are chunked and error-capped.
- **API tokens** — per-user bearer tokens minted at `/settings/tokens`, shown
  once, revocable with immediate effect; API is rate-limited at 120 req/min.
- **Signup toggle** — `SIGNUPS_ENABLED=false` disables self-service
  registration; create users with `php artisan mahalinkam:make-user`.
- **Portable storage** — runs on SQLite (default), MySQL or PostgreSQL; CI runs
  the full suite against all three on every push.
- **Docker production stack** — `compose.prod.yaml` with an `app` web container
  on port 8080 and a `worker` container for the queue. No bundled edge TLS /
  reverse proxy — the operator points their own proxy at 8080.

[Unreleased]: https://github.com/santhoshjanan/mahalinkam-srv/compare/v0.1.3...HEAD
[0.1.3]: https://github.com/santhoshjanan/mahalinkam-srv/releases/tag/v0.1.3
