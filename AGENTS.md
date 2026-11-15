# AGENTS.md — contributor orientation

Working with this repository means working with five things: the **web UI**
(Laravel + Inertia/React), the **libsql3 CLI**, the **nginx proxy**, the
**docker compose stacks**, and the **release process**.

## Repository layout

```
webapp/     the application (Laravel 13, Inertia.js, React 19, PHP ^8.4)
libsql3/    PHP CLI client for the same sqld instances
nginx/      optional proxy (hrana over :8080, gRPC auth-checked over :5001)
scripts/    installer.sh (production bootstrap helper)
docs/       installation and operations guides
examples/   minimal client snippets (PHP/JS/TS — read LIBSQL_AUTH_TOKEN from env)
```

## Develop

```bash
cd webapp
composer install
npm install --package-lock=true   # global npm config here sets package-lock=false
vendor/bin/pest                   # domain + unit tests; in-memory SQLite, fail-closed
npm run types                     # tsc --noEmit
npm run build                     # vite build
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
3. Smoke-test before widening to stable: fresh install -> `BootstrapSeeder`
   -> login -> create database -> create token -> delete user.
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
