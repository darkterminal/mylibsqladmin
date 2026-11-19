## [Unreleased] - 2026-11-19

Accumulated since [1.5.6]. **Do not interpret as a release yet** - notes are
kept here until a maintainer cuts a tag. Highlights first, then the one item
that can lose data.

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
