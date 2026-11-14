## [Unreleased] - 2026-11

- chore: upgrade all components to current versions (Laravel 13.42.0, PHP ^8.4,
  Inertia Laravel 3.7, React 19.3.1, Vite 8.3.8, Tailwind 4.3.5, Octane 2.24,
  Sanctum 4.4, turso-driver-laravel 1.7.0). See branch upgrade/2026-11-laravel-13-components.
- fix: retire seeded fixture users with public credentials (security).
  Production bootstrap is now BootstrapSeeder only.
- fix(security): default-deny data-plane proxy authorization, hashed opaque tokens,
  CLI auth bound to Sanctum identity, tenant-scoped RBAC policies.

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
