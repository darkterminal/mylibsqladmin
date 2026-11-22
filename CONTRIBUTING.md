# Contribution Guidelines for MylibSQLAdmin

Thank you for considering contributing to **MylibSQLAdmin**—a modern, open-source platform for managing libSQL (SQLite-compatible) databases through a web GUI and the `libsql3` CLI. Your contributions help improve the project for everyone.

## Before You Start

- **Check Existing Issues**: Review [open issues](https://github.com/darkterminal/mylibsqladmin/issues) to avoid duplicating efforts.
- **Understand the Project**: Familiarize yourself with the project's structure and goals by reading the [README](https://github.com/darkterminal/mylibsqladmin/blob/main/README.md), the [libsql3 CLI manual](docs/libsql3-man.md), or [DeepWiki](https://deepwiki.com/darkterminal/mylibsqladmin).

## How to Contribute

1. **Fork the Repository**: Click the "Fork" button at the top right of the [repository page](https://github.com/darkterminal/mylibsqladmin).
2. **Clone Your Fork**:
   ```bash
   git clone https://github.com/darkterminal/mylibsqladmin.git
   ```
3. **Create a New Branch**:
   ```bash
   git checkout -b feature/your-feature-name
   ```
4. **Make Your Changes**: Implement your feature or fix.
5. **Test Your Changes**: Ensure your changes don't break existing functionality.
6. **Commit and Push**:
   ```bash
   git add .
   git commit -m "Describe your changes"
   git push origin feature/your-feature-name
   ```
7. **Submit a Pull Request**: Go to your fork on GitHub and click "Compare & pull request."

## Development Environment

- **Docker**: The project uses Docker for development. This is the **default** engine — CI, the release process and all published images stay on Docker. Refer to the `compose.yml` files for setup.
- **Kiri (opt-in, local only)**: The development stack can also run on the Kiri container runtime. Docker remains the default — Kiri is selected per invocation, never implicitly:

  ```bash
  # Docker (default)
  make compose-dev/upd

  # Kiri
  CONTAINER_ENGINE=kiri make compose-dev/up|upd|down|restart|...
  ```

  How it works:
  - `CONTAINER_ENGINE` defaults to `docker` in the `Makefile`; `kiri compose` is used only when you set it to `kiri`.
  - For builds to go through Kiri, run once per machine: `kiri build-with kiri`. `docker buildx` targets (`compose-dev/build`, `compose-prod/build`, `compose-proxy/build`) intentionally stay on Docker.
  - `kiri exec` / `kiri compose` / `kiri logs` accept the same flags as their Docker counterparts; substitute them in commands below.
  - Verified on Linux: pull (Docker Hub and GHCR), full image build (~400 MB build context), stack startup with health checks, web/vite/proxy/mailpit/db end-to-end. A host service pre-binding port 53 (e.g. BIND9) blocks Kiri's user-defined bridge networks — keep port 53 free or use the Docker default.
  - Do **not** use Kiri for CI/CD, releases, or production installs; those remain Docker.

- **Email (dev)**: No real email ever leaves your machine — the development stack traps all outbound mail with [Mailpit](https://github.com/axllent/mailpit) (`mailpit` service, SMTP `1025`, web UI `8025`). To use it, make sure your local `webapp/.env` points at it:

  ```bash
  MAIL_MAILER=smtp
  MAIL_HOST=mailpit
  MAIL_PORT=1025
  MAIL_SCHEME=null
  ```

  Then any email the app sends lands in Mailpit instead of `storage/logs/laravel.log`. Read captured mail at <http://localhost:8025>. Note: `.env.example` deliberately ships `MAIL_MAILER=log` — that is a safe default for production installs; switch to the values above only for local development.

- **Makefile**: Utilize the provided `Makefile` for common tasks.
- **Environment Variables**: Use `.env.example` as a template for your environment configuration.

## Working on the proxy

The nginx/OpenResty proxy has a Lua unit suite covering subdomain extraction and the HRANA auth gate:

```bash
make proxy/test                        # Docker (default)
CONTAINER_ENGINE=kiri make proxy/test   # Kiri, opt-in for local development
```

It mounts the Lua modules into `openresty/openresty:alpine`; no running stack or network is required. Add a case there whenever either Lua module changes.

## Working on libsql3

`libsql3` is the CLI client for the same admin API as the web application. It has its own Composer project and test suite:

```bash
cd libsql3
composer install
composer test
```

A few rules keep its contracts stable:

- The REPL is the default when no arguments are passed. One-shot argv dispatch is additive; do not change PsySH behaviour or edit `libsql3/vendor/**`.
- `libsql3/src/Version.php::CURRENT` is the single version source. Update it in the same commit that bumps the platform version; CI compares it with the latest `v*` tag.
- Command signatures are generated into `docs/libsql3-cli-reference.md`. After changing a command, run `composer docs` from `libsql3/` and commit the regenerated file. CI fails on a docs diff.
- The narrative manual in `docs/libsql3-man.md` owns workflows, first-run guidance, endpoint policy, and exit codes. Do not duplicate argument/option tables there.
- The committed PHAR is rebuilt by the build-only `.github/workflows/libsql3-phar.yml` gate and verified byte-for-byte against `libsql3/src/**`. Do not hand-edit the binary or silently swap it.
- `libsql3` output for one-shot commands is human-readable and is not a stable machine contract. Adding `--format=json` needs a named consumer and a new finding first.
- For a full end-to-end sweep against a disposable local stack, follow [the contributor CLI smoke guide](docs/cli-smoke-test.md). It walks all 25 commands, the negative cases, and the safe order of destructive operations.

## Code Standards

- **Language**: Ensure your code is clean and well-documented.
- **Style**: Follow consistent coding styles and conventions used in the project.
- **Commits**: Write clear and descriptive commit messages.

## 🧾 Licensing

By contributing, you agree that your contributions will be licensed under the [Apache-2.0 License](https://github.com/darkterminal/mylibsqladmin/blob/main/LICENSE).

## 📬 Need Help?

If you have questions or need assistance, feel free to open an issue or [join our community](https://discord.gg/wWDzy5Nt44) discussions.
