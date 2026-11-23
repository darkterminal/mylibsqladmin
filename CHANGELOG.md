## [1.6.3] - 2026-11-23

- Patch: clean LRI compose (no sqld `entrypoint`/`user` override), env-driven
  trusted proxies, `APP_KEY` fail-fast and a `WEB_SERVER` selector.
- Also ships the proxy hardening, CLI fixes and PsySH security patch already in
  the source tree. CLI version, PHAR and image tags aligned at `1.6.3`.
- Installer now emits `WEB_SERVER=artisan`, the proxy-trust env vars and the
  clean sqld service for both LLI and LRI; image tags default to `1.6.3`.
- Quick start commands (stack installer + `libsql3` binary download) are in
  `webapp/CHANGELOG.md` and the GitHub Release notes.
- Known issue: the FrankenPHP/Octane version mismatch is not fixed; use
  `WEB_SERVER=artisan` as the temporary fallback.
- Details + verification in `webapp/CHANGELOG.md`

## [1.6.2] - 2026-11-19

- Patch: Database Studio iframe pinned to libsqlstudio.com (no foreign-origin SQL execution); installer fixes (admin API host, auto seed, uid:gid, pre-owned data dir, optional IMAGE_TAG).
- Details + verification in `webapp/CHANGELOG.md`

## [1.6.1] - 2026-11-19

- HOTFIX for v1.6.0: fresh installs could not create a database. Every
  HTTP call to the sqld admin API resolved to `localhost:8081` (nothing
  listens there); only the CLI path worked. Fixed via `mylibsqladmin_env()`.
- v1.6.0 is affected for all fresh installs. Upgrading to v1.6.1 restores
  the documented install path.
- details + full verification in `webapp/CHANGELOG.md`

## [1.6.0] - 2026-11-19

- see `webapp/CHANGELOG.md` for the full release notes and the upgrade
  warning that applies to this release
- **backup before upgrading**: this release includes an irreversible,
  in-place migration that re-bases all written timestamps to UTC. See the
  changelog for details; the wall-clock shown in the UI is unchanged.
- feat: all storage is now UTC; display timezone configurable via
  `APP_TIMEZONE` env or `/settings/general` (Super Admin), display-only
- feat: container engine is pluggable in the dev Makefile; Docker stays the
  default, Kiri is opt-in for local development only
- fix: honest User Management (working search, Last Active column, password-
  confirmed force delete), Tokens one-time reveal, Groups honest reveal,
  Teams archives/invitations management, Dashboard truthful 24h metrics
- notes: this file is kept for pre-1.5 history mirrors; new entries are
  maintained in `webapp/CHANGELOG.md`

## [1.5.6] - 2026-11-16

- see `webapp/CHANGELOG.md` for the full 1.5.x release history.
- fix(critical): all releases since v1.5.0 shipped a blank white UI
  (React never mounted). Fixed in v1.5.6 - do not deploy v1.5.0..v1.5.5.
  In-place upgrades from inertia-laravel v2 must run `php artisan view:clear`.
- Note: this file is kept for pre-1.5 history; new entries are maintained in
  `webapp/CHANGELOG.md`.

## [1.4.2] - 2025-06-23

- fix: local instance proxy connection [Imam Ali Mustofa]
- docs: add instalation step using installer script [Imam Ali Mustofa]
- fix: make installer script work [Imam Ali Mustofa]
- docs: change the installation method [Imam Ali Mustofa]
- chore: shot [Imam Ali Mustofa]
- chore: whoops [Imam Ali Mustofa]
- chore: idk [Imam Ali Mustofa]
- fix: unbound env name error [Imam Ali Mustofa]
- docs: write the installation guide [Imam Ali Mustofa]
- feat: add the installer script [Imam Ali Mustofa]

## [1.4.0] - 2025-06-18

- refactor: using csrf token all over the places [Imam Ali Mustofa]
- feat: add platform auto-sync remote databases [Imam Ali Mustofa]
- refactor: use artisan serve in dev [Imam Ali Mustofa]

## [1.3.8] - 2025-06-14

- fix: stats event-listener and sqld service [Imam Ali Mustofa]

## [1.3.7] - 2025-06-12

- fix: libsql remote instance stats [Imam Ali Mustofa]

## [1.3.6] - 2025-06-12

- fix: proxy forgot the entrypoint [Imam Ali Mustofa]

## [1.3.5] - 2025-06-11

- refactor: proxy entrypoint [Imam Ali Mustofa]

## [1.3.4] - 2025-06-11

- refactor: Makefile losing control [Imam Ali Mustofa]

## [1.3.3] - 2025-06-11

- refactor: trying to be cool [Imam Ali Mustofa]

## [1.3.1] - 2025-06-10

- refactor: trying to be cool [Imam Ali Mustofa]

## [1.3.0] - 2025-06-08

- release: minor version 1.2.0 [Imam Ali Mustofa]
- release: minor version 1.2.0 [Imam Ali Mustofa]
- fix: mount volumes [Imam Ali Mustofa]
- docs: add demo video and screenshot [Imam Ali Mustofa]
- refactor: re-organize directory structure [Imam Ali Mustofa]

## [1.2.0] - 2025-06-08



## [1.2.0] - 2025-06-08

- fix: mount volumes [Imam Ali Mustofa]
- docs: add demo video and screenshot [Imam Ali Mustofa]
- refactor: re-organize directory structure [Imam Ali Mustofa]

## [1.1.16] - 2025-06-07

- refactor: use db service in production [Imam Ali Mustofa]

## [1.1.15] - 2025-06-07

- refactor: using app environment method [Imam Ali Mustofa]

## [1.1.14] - 2025-06-07

- fix: ensure correct host resolution for production CLI and web contexts [Imam Ali Mustofa]

## [1.1.13] - 2025-06-07

- build: use copy command [Imam Ali Mustofa]

## [1.1.12] - 2025-06-07

- build: use service name in production [Imam Ali Mustofa]

## [1.1.11] - 2025-06-07

- build: remove env_file from compose [Imam Ali Mustofa]

## [1.1.10] - 2025-06-07

- build: generate key using openssl [Imam Ali Mustofa]

## [1.1.9] - 2025-06-07

- build: create database file at runtime [Imam Ali Mustofa]

## [1.1.8] - 2025-06-07

- build: run key generate in entrypoint [Imam Ali Mustofa]

## [1.1.7] - 2025-06-07

- build: reorder build command [Imam Ali Mustofa]
- chore: update lock [Imam Ali Mustofa]

## [1.1.6] - 2025-06-04

- chore: commit save point [Imam Ali Mustofa]
- build: move migration and clear command in entrypoint [Imam Ali Mustofa]
- chore: update CHANGELOG.md for patch version 1.1.5 [Imam Ali Mustofa]
