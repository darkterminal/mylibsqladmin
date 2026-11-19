# AGENTS.md — contributor orientation

Working with this repository means working with five things: the **web UI**
(Laravel + Inertia/React), the **libsql3 CLI**, the **nginx proxy**, the
**docker compose stacks** (Docker by default; Kiri is an opt-in for local
development only), and the **release process**.

## Repository layout

```
webapp/     the application (Laravel 13, Inertia.js, React 19, PHP ^8.4)
libsql3/    PHP CLI client for the same sqld instances
nginx/      optional proxy (hrana over :8080, gRPC auth-checked over :5001)
scripts/    installer.sh (production bootstrap helper)
docs/       installation and operations guides
examples/   minimal client snippets (PHP/JS/TS — read LIBSQL_AUTH_TOKEN from env)
```

## Container engines

- **Docker is the default.** `make compose-dev/upd` == `docker compose …`. CI and
  the release process are Docker, full stop; GHCR publish stays on `docker buildx`
  (`--push` targets are intentionally not routed through Kiri).
- **Kiri is opt-in for local development only**: `CONTAINER_ENGINE=kiri make <target>`
  (e.g. `CONTAINER_ENGINE=kiri make compose-dev/upd`). Requires Kiri installed; run
  `kiri build-with kiri` once per host if builds should also go through Kiri.
- Verified on Linux: pull (Docker Hub + GHCR), build, stack bring-up with health
  checks, web/vite/proxy end-to-end. Kiri's user-defined bridge networks need
  port 53 free — a host service pre-binding :53 (e.g. BIND9) will keep the stack down.
- Compose files, Dockerfiles and `.env` carry no Kiri-specific tweaks; the only
  engine-aware surface is the root `Makefile`.

## Develop

```bash
cd webapp
composer install
npm install --package-lock=true   # global npm config here sets package-lock=false
vendor/bin/pest                   # domain + unit tests; in-memory SQLite, fail-closed
npm run types                     # tsc --noEmit
npm run build                     # vite build
npm run test                      # vitest (jsdom) — permission/hook contracts
```

CI (`.github/workflows/ci.yml`) gates every push/PR: composer install + pest +
Composer audit, `npm ci` + `tsc` + `vite build`. `composer validate --strict`
also runs in CI — keep `composer.json` clean (no `version` field).

## Test conventions

- Feature tests use `RefreshDatabase` with in-memory SQLite — **no live sqld**
  needed. Fake the sqld admin API with `Http::fake()` and
  `Http::preventStrayRequests()`; fake notifications with `Notification::fake()`.
- PHPUnit env: `REGISTRATION_ENABLED=false` is deliberate; tests that need
  open registration flip `config()->set('app.registration_enabled', true)`.
- When touching RBAC, policies, tokens, or invitations: the test suite is the
  contract. `vendor/bin/pest --filter=<pattern>` for the focused suite.

## Environment gotchas

- **Docker build context is `./webapp`, never the repo root** (the Dockerfile
  expects `webapp/` as context; root builds fail).
- **Global npm config here sets `package-lock=false`** — regenerating
  `package-lock.json` requires `--package-lock=true`.
- The sqld **admin API** listens on `:8081` and is only reachable from the
  internal network. `LIBSQL_API_HOST` points at it (default `db` in compose);
  `LIBSQL_API_USE_HTTPS=true` when the target is remote.
- **Tokens are SHA-256 at rest** (since v1.5.0). Plaintext is only ever
  revealed at creation time (one-shot in responses). The
  `2026_11_14_000000_hash_existing_database_tokens` migration is
  irreversible — do not "re-encrypt" stored values.
- `APP_KEY` is generated per install; the production image has none baked in.
  Fresh installs run `php artisan migrate --force` at container start; the
  super admin is created only when `LIBSQL_SEED_DEFAULTS=true` (one-time
  `storage/app/bootstrap-credentials.txt`, mode 0600) — or manually via
  `php artisan db:seed --class=BootstrapSeeder` (idempotent).

## Release process

1. PR -> CI green -> merge to `main`.
2. Tag `vX.Y.Z` -> `publish.yml` builds and pushes production images to GHCR
   and creates a GitHub Release (prerelease when the tag contains `-`).
3. **Fresh-install smoke gate (mandatory).** Before any tag or widening to
   stable, verify the exact artifact about to ship on a throwaway stack —
   never on the primary development stack, never by code inspection alone.

   The gate covers the whole path, end to end:
   - boot logs show migrations applying and `BootstrapSeeder` emitting new
     bootstrap credentials (read once, then delete `storage/app/bootstrap-credentials.txt`);
   - login succeeds as the seeded super-admin;
   - the group create -> database create flow succeeds; the namespace is
     physically written to sqld (`data.sqld/dbs/<name>`), not merely a 2xx
     from the UI;
   - token creation renders the one-time reveal; a member can be created and
     archived through the UI; whole flow finishes with **0 console errors**.

   A pull of the exact images to ship (`<owner>/mylibsqladmin-web:<tag>` and
   `...-proxy:<tag>`) is required; "it worked in my local build" is not a
   substitute. If a release slips through without this gate (or the gate
   fails) — v1.6.0 fresh installs could not create a database — cut a hotfix
   **before anything else**, instead of announcing or widening the release.
   When in doubt, rollback is simpler than a silently broken image: do not
   publish a new `latest` pointer for a broken release. For in-place upgrades
   carrying an irreversible migration, prove the migration on a full copy of
   a production-like database and document the backup requirement in the
   release notes.

4. `latest` on GHCR moves on every publish — pin images by version tag for
   reproducible deployments.

## Security invariants (do not loosen)

- No seeded credentials anywhere; no `.env` in the image; `APP_DEBUG` off in
  production examples; registration disabled by default.
- Database tokens are opaque random bytes, hashed at rest; CLI auth binds to
  Sanctum identity (never to headers); invitation acceptance is bound to the
  invitee's email; deactivated users lose access immediately.
- `activity_logs` and audit trails record metadata, never raw tokens.

## Codebase conventions

- Controllers stay small; validation lives in form requests/controllers,
  authorization in policies + Gate. No `$this->authorize()` — the base
  `App\Http\Controllers\Controller` intentionally has no traits; use
  `auth()->user()->can(...)` with an explicit model.
- Inertia deferred props: the closures **contain** the computation.
- Frontend: shadcn/ui, Tailwind 4, TypeScript strict (`tsc --noEmit` clean).

## Where things stand

See `webapp/CHANGELOG.md` for release notes. The security hardening is
documented per release in the changelog, and every control change lands with
its tests in the same commit.
