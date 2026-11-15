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
