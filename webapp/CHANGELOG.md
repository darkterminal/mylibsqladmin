## [Unreleased]

Not yet released or tagged. These changes are in the current source tree. The
web image and version tags are still v1.6.2; the proxy `:latest` image was
updated out-of-band on 2026-11-22 with the proxy fixes below (pinned tag
`1.6.2-proxy-f57`). No release was cut.

### Security — proxy (F-54)

- `POST /v2/pipeline` and `location /` now share one Lua auth gate. Missing or
  `none` access levels, write statements under read-only tokens and malformed
  bodies return an empty `204` instead of executing SQL.
- The database subdomain is derived from the original `Host` against
  `LIBSQL_PARENT_DOMAIN`, so `example.com`, `example.co.uk` and `example.id`
  work. Nested or foreign hosts are rejected. TLS terminates in front of the
  proxy; the edge must forward `Host`.
- Routing and validation now derive the subdomain from one canonical Host
  source in both nginx templates (F-57). An absolute-form request line with a
  conflicting `Host` can no longer make a token for database A authorize a
  query routed to database B; mismatches fail closed.
- Upstreams and the parent domain are rendered from env at startup into a
  writable config. The read-only template mount is no longer edited with
  `sed -i`, and `:5001` now has an auth-checked gRPC listener instead of a dead
  port. Added `make proxy/test` (31 Lua assertions across both templates).

### Fixes

- CLI token fingerprints from `db:token:create` and `db:token:status` agree
  (F-55): both derive from the stored SHA-256 hash.
- Webapp CSP honours `LIBSQL_PARENT_DOMAIN` when set, including ccTLDs; an
  explicitly malformed value fails closed. Unset keeps the legacy heuristic.
- CLI display timezone follows the platform setting with a `LIBSQL3_TIMEZONE`
  override (F-51); one-shot confirmations accept `--yes` without repurposing
  `--force` (F-52); `db:shell` credential cleanup is idempotent (F-53).

### Docs

- Installation guide covers custom-domain/TLS requirements, the proxy env
  contract and the published-image caveat. User manual and CLI guide describe
  the hash-derived fingerprint and explicit parent domain.
- TypeScript example now reads `LIBSQL_AUTH_TOKEN`/`LIBSQL_SYNC_URL` as its
  README has always claimed.
- Added a tracked contributor guide for the full 25-command CLI smoke run.

### Verification gaps

- LRI mode got a scoped smoke test on 2026-11-22: LRI render, web health,
  admin-API reachability and frontend `sqldHost` all passed with
  `LIBSQL_LOCAL_INSTANCE=false`. The remote endpoint was local sqld over the
  network, not a hosted instance, so a real LRI deployment remains unverified.
- Verified locally: the LLI/Kiri stack, the 31-assertion proxy Lua suite, the
  webapp and libsql3 test suites, and a Jekyll build. Dependabot alert #3 was
  confirmed stale (`form-data` was removed from the lockfile on 2026-11-15,
  before v1.6.2); no dependency change was needed.

## [1.6.2] - 2026-11-19

Patch release. Two fresh-install defects found and fixed; no schema or
internal-interface changes; upgrade is safe without a backup beyond normal
operational hygiene.

### Security fix — Database Studio iframe

- `window.addEventListener("message", …)` no longer accepts events from
  origins other than `https://libsqlstudio.com`. Previously **any window
  that could postMessage into the page** (all frames/openers on the page)
  could execute arbitrary SQL through the embedded libSQL client using the
  in-memory full-access token — a cross-origin SQL-execution channel.
- `postMessage(..., "*")` now targets `https://libsqlstudio.com` — query
  results no longer broadcast to arbitrary origins.
- The iframe now carries `sandbox="allow-scripts allow-same-origin"` — no
  top-level navigation, plugins, forms or popups from the embed.

### Installer fix — `scripts/installer.sh`

- **`LIBSQL_API_HOST=db`** (was `proxy`) — installer-generated fresh
  installs can now reach the sqld admin API (`db:8081`); previously fresh
  installer installs could never create a database.
- Dead `8081:8081` mapping on the proxy dropped (no listener; misleading).
- **`LIBSQL_SEED_DEFAULTS=true`** is now written to the generated compose —
  fresh installs produce a logged-in super-admin automatically (docs/README
  Option A), no manual artisan step.
- `db` service `user: "$(id -u):$(id -g)"` resolved at install time
  (was hard-coded `1000:1000`, wrong for any non-uid-1000 host).
- The installer now pre-creates `./libsql-data` owned by the invoking user
  (mode `0700`) **before** writing compose.yml; if the directory already
  exists and is unwritable, the installer fails fast with an actionable
  error naming the uid and the exact fix — `compose.yml` is not written.
- New optional **`IMAGE_TAG`** prompt (default `latest`) — web and proxy
  images can be pinned at install time (e.g. `1.6.2`) for reproducible
  deploys; sqld stays at `latest` (upstream).

### Tests

- `StudioTokenIssueTest` gains a Team Manager mint test (token mint
  for a database with no explicit `GrantedDatabase`), covering the
  role-vs-grant contract.

### Docs

- `AGENTS.md` — fresh-install smoke is promoted to a mandatory release
  gate; code inspection alone is not a valid substitute.

## [1.6.1] - 2026-11-19

v1.6.0 fresh installs could not create a database. This hotfix is required
for all new installations and existing fresh installs; in-place upgrades are
safe to apply once available (no migrations in this release).

### Critical fix

- fix(sqld): every HTTP call from the Octane/FrankenPHP worker to the sqld
  admin API resolved to `localhost:8081` (nothing listens there inside the
  container). No HTTP path could create a database, register credentials, or
  fetch metrics — only `php artisan` CLI worked. Root cause:
  `mylibsqladmin_env()` special-cased non-CLI production SAPIs to return
  `'localhost'`, ignoring the configured `LIBSQL_API_HOST`. The helper now
  honours the env var uniformly (`env('LIBSQL_*', 'db')`), matching what the
  production CLI path always did.
- fix(sqld): `useEndpoint('db')` simplified — admin calls always resolve
  `LIBSQL_API_HOST`/`LIBSQL_API_PORT` (the sqld admin API variables).
- fix(observability): generic-500 catch-alls now log the real exception
  (class, message, file, line). v1.6.0 failed silently; this hotfix is a
  direct consequence of hunting from an empty log.
- fix(subdomain): `GET /validate-subdomain` without `X-Subdomain` now returns
  400 with `X-Access-Level: none` (was 500 TypeError on a public route).

### Verification

- Fresh install (GHCR-equivalent local build, LLI docs topology
  `LIBSQL_HOST=proxy`, `LIBSQL_API_HOST=db`): boot → migrations → seed →
  login → create group → create database (sqld wrote the namespace to disk) →
  create token (one-time reveal) → create member → archive member — all PASS,
  0 console errors.
- `vendor/bin/pest`: 160 passed (605 assertions).
- CI green on PR #54.

## [1.6.0] - 2026-11-19

Highlights since [1.5.6]. The one item that can lose data is listed
explicitly below — read it before upgrading.

### Highlights

- feat(timezone): **all storage is now UTC, always**; `APP_TIMEZONE` is
  demoted to a display-only setting and is also configurable at runtime via
  the new Super Admin `/settings/general` page (env > UI setting; precedence:
  system setting > `APP_TIMEZONE` env > built-in UTC). Display timezone is
  applied to every rendered timestamp regardless of the viewer's browser
  locale. (PR #51)
- fix(users): honest User Management (working search, teamable filters,
  server-side self-guards, password-confirmed force delete, Last Active
  column, honest empty states) (PR #48); icon-only buttons are now
  accessible; deactivate confirmation moved from a 5s toast to a dialog
  (PR #49).
- feat(pages): Dashboard/Tokens/Groups/Teams rework toward honest data -
  real 24h baselines, one-time token reveal, rotation warnings, invitations
  management, archives, pagination and search (PRs #42-#47).
- feat(dx): container engine is now pluggable in `Makefile`; **Docker remains
  the default, Kiri is strictly opt-in** for local development
  (`CONTAINER_ENGINE=kiri make …`). CI/CD and release are Docker, unchanged.
  (PR #46)
- fix(users): tooltip on Tokens page no longer dead (Fragment under
  Radix Slot child) (PR #47).
- docs: Mailpit documented as the local email trap; development stack routes
  mail there instead of `laravel.log` (PR #50). See `CONTRIBUTING.md`.
- chore(test): local `pest` now matches CI exactly (153 passed / 591
  assertions) with no env overrides (PR #52).

### Beware - irreversible data migration in PR #51

Upgrading runs `2026_11_20_000001_convert_written_timestamps_to_utc`
automatically on first boot. Every naive datetime written in the previous
`APP_TIMEZONE` (default `Asia/Jakarta`) is re-based to UTC in one
fail-closed transaction. `jobs.failed_at` (SQLite `CURRENT_TIMESTAMP`,
already UTC) and INTEGER epoch columns are intentionally untouched.

**Take a database backup before upgrading.** The migration has no `down()`.
Existing installations see their wall-clock times in the interface stay
exactly as before (display honours `APP_TIMEZONE`), only storage moved.

### Not changed on purpose

- Unique validation still counts soft-deleted users (email/username reuse
  requires force-deleting the archived account).
- `.env.example` deliberately ships `MAIL_MAILER=log` (safe production
  default); dev installs switch to `smtp`/`mailpit` per `CONTRIBUTING.md`.
- Kiri is not the default engine anywhere; release/CI remain Docker.

### Verification snapshots

- `vendor/bin/pest`: 153 passed (591 assertions) - identical locally and on CI.
- `npm run types`: clean.
- Browser checks (Playwright): every admin page renders without console
  errors; tokens one-time reveal, teams flows, user lifecycle verified
  end-to-end on the dev stack.
- The full verification trail lives in the project's audit tracker
  (REMEDIATION-STATUS.md) maintained during the 2026-11-16..19 sessions.

## [1.5.6] - 2026-11-16

- fix(critical): the web UI rendered a blank white page in v1.5.0..v1.5.5 -
  React never mounted. Two root causes from the Laravel 13 / Inertia v2->v3
  upgrade (commit ac0bcb5), both fixed:
  1. resources/js/app.tsx lost its `resolve:` prop (the resolvePageComponent
     import was deleted). Recreated with Inertia v3 typing
     (import.meta.glob(..., { import: 'default' })).
  2. inertia-laravel v3 changed page delivery to
     `<script data-page="app" type="application/json">`. Installations carrying a
     stale compiled Blade cache (storage/framework/views) from inertia-laravel
     v2 kept rendering the v2 `<div id="app" data-page="...">` format, so
     getInitialPageFromDOM() returned null and the client crashed with
     "Cannot read properties of null (reading 'component')". Fresh installs and
     images built after this release compile views fresh; existing installs that
     upgrade in place must run `php artisan view:clear` once.
  3. Non-production CSP now allows the Vite dev server origin (read from
     public/hot) plus ws:// for HMR; the production policy is unchanged.
     Affects local development only.
- test: SecureHeadersCspTest pins the dev-origin allowance/absence behaviour.
  pest 117/117; vitest 8/8; tsc clean; vite build ok.
- note: curl smoke checks verify HTTP 200 only and cannot catch a client
  mount failure; UI releases require a browser check.
- **DO NOT deploy v1.5.0..v1.5.5 - all ship a blank UI. Upgrade to 1.5.6.**
  The stable v1.5.0..v1.5.5 releases are marked DO-NOT-DEPLOY on the GitHub
  releases page.

## [1.5.5] - 2026-11-15

- chore(libsql3): drop dead X-User-Identifier header sends (PR #33).
- refactor(team): TeamMembershipService - member/role/invite command side
  (PR #34); read side to TeamDashboardQuery; live 500 on teams/archives fixed
  (class-string can: vs instance policy gate) (PR #35).
- chore: DIR-01/02/03 resolved - man page matches reality, `db:token:create`
  CLI command added, vitest + testing-library adopted (PR #36).
- chore(rbac): boundary decisions D1-D4 pinned; manage-group-tokens global
  bypass removed; invitations = member administration; /activities requires
  team membership (PR #37).
- **DO NOT DEPLOY: blank white UI - see v1.5.6.**

## [1.5.4] - 2026-11-15

- refactor(frontend): deduplicate database pages (TECHDEBT-01), unify
  is_schema classification (TECHDEBT-07) (PR #20, PR #21).
- refactor: unify geo-IP + activity-log boilerplate (TECHDEBT-02) (PR #22);
  drop dead JS deps + starter-kit identity (TECHDEBT-05) (PR #23); single
  source of truth for currentTeamId (TECHDEBT-06) (PR #24).
- fix: enforce team_databases expires_at (TECHDEBT-09) (PR #25); delete dead
  PHP methods (TECHDEBT-04) (PR #26); Studio activity logging (TECHDEBT-08)
  (PR #27); middleware writes out of the HTTP lifecycle + controller dedup
  (TECHDEBT-10/03) (PR #28).
- fix: 5 live production bugs via new coverage - database delete/force-delete/
  restore usable again, remove-from-group 403, revokeInvite, /activities guard,
  invite "database-maintener" role typo (PR #29, PR #30).
- dx: clean builds, no tracked-file mutation (DX-01/02/03) (PR #31); build
  toolchain moved to devDependencies, app tree 350 MB -> 84 MB (DEPENDENCIES-02)
  (PR #32).
- **DO NOT DEPLOY: blank white UI - see v1.5.6.**

## [1.5.3] - 2026-11-15

- perf: collapse QueryMetric::summarized() N+1 digest query and fingerprint
  list tokens (PR #14).
- perf: real deferred props (PERF-01) + throttled remote sync (PERF-04)
  (PR #15); Studio stats fetch throttled 30 s/database (PERF-03) (PR #16).
- perf: de-duplicate setTeamDatabases on team switch (PERF-05) (PR #17);
  User $with/$appends dropped (PERF-08) (PR #18); metrics pruning, indexes,
  real debounce, slim team graph (PR #19).
- **DO NOT DEPLOY: blank white UI - see v1.5.6.**

## [1.5.2] - 2026-11-15

- test: CLI contract coverage + CLI identity/error fixes (PR #12).
- fix: grant-access endpoint restored - was 500/302-silent for everyone
  (PR #13).
- **DO NOT DEPLOY: blank white UI - see v1.5.6.**

## [1.5.1] - 2026-11-15

- test: domain security hardening coverage, first two batches (PR #10, PR #11).
- fix: invite-accept 500 after team attach (PR #11).
- **DO NOT DEPLOY: blank white UI - see v1.5.6.**

## [1.5.0] - 2026-11-15

- chore: upgrade all components to current versions (Laravel 13.42.0, PHP ^8.4,
  Inertia Laravel 3.7, React 19.3.1, Vite 8.3.8, Tailwind 4.3.5, Octane 2.24,
  Sanctum 4.4, turso-driver-laravel 1.7.0). See PR #5.
- fix: retire seeded fixture users with public credentials (security).
  Production bootstrap is now BootstrapSeeder only (idempotent; safe to re-run).
- fix(security): default-deny data-plane proxy authorization, hashed opaque tokens
  (SHA-256 at rest, existing tokens migrated in place), CLI auth bound to Sanctum
  identity, tenant-scoped RBAC policies.
- fix: BootstrapSeeder now creates the super-admin on fresh installs (beta.1/2
  regression, caught by publish smoke tests; fixed in v1.5.0-beta.3).
- fix: token mint no longer returns HTTP 500 (SEC-02 refactor regression).
- ci: publish workflow honours semver prerelease tags; one-command CI gate
  (pest + tsc + vite build) on push/PR.

**DO NOT deploy v1.5.0-beta.1 (broken bootstrap) or v1.5.0-beta.2 (token mint
500).** Both are marked as such on the GitHub releases page.
**DO NOT deploy v1.5.0 either: blank white UI - see v1.5.6.**

## [1.4.1] - 2025-06-23

- docs: add instalation step using installer script [Imam Ali Mustofa]
- fix: make installer script work [Imam Ali Mustofa]
- docs: change the installation method [Imam Ali Mustofa]
- chore: shot [Imam Ali Mustofa]
- chore: whoops [Imam Ali Mustofa]
- chore: idk [Imam Ali Mustofa]
- fix: unbound env name error [Imam Ali Mustofa]
- docs: write the installation guide [Imam Ali Mustofa]
- feat: add the installer script [Imam Ali Mustofa]

## [1.3.2] - 2025-06-11

- fix: proxy entrypoint [Imam Ali Mustofa]

## [1.3.0] - 2025-06-08

- release: minor version 1.2.0
