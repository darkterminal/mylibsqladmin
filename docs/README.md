# MylibSQLAdmin Installation Guides

Thank you for choosing MylibSQLAdmin as your `libsql-server` Database Management Platform. In this document you will be guided how to install and configure MylibSQLAdmin in your server.

## Support this Project

<p align="center">
    <a href="mailto:darkterminal@duck.com" target="_blank"><img title="Sponsor Here" src="https://i.imgur.com/R1zU0eW.png" width="100%" /></a>
    via <a href="https://github.com/sponsors/darkterminal">GitHub Sponsor</a> (Global) / via <a href="https://saweria.co/darkterminal" target="_blank">Saweria</a> (Indonesian)
</p>

---

## Requirements

-   Docker & Docker Compose
-   openssl

### Install using Installer Script (Recommended)

Make sure the installer script is running inside your prefered directory!

```
curl --proto '=https' --tlsv1.2 -LsSf https://raw.githubusercontent.com/darkterminal/mylibsqladmin/refs/heads/main/scripts/installer.sh | sh
```

### Manual Installation

Create directory to save the `compose.yml` file in your desire location.

#### LRI (libSQL Remote Instance)

> **Verification status:** the repository's `compose.lri.yml` development
> stack has **not** been re-tested against the current source changes (proxy
> `Host` routing and env contract, CSP parent domain, CLI token fingerprints,
> display timezone and `--yes`). The local-instance (LLI) development stack is
> the verified path. Treat LRI as untested until it gets its own smoke run;
> no release has been cut and the published `:latest` images are unchanged.

Install MylibSQLAdmin Platform with your existing `libsql-server` instance

```yml
services:
    webui:
        container_name: mylibsqladmin-webui
        image: ghcr.io/darkterminal/mylibsqladmin-web:latest
        ports:
            - 8000:8000
        network_mode: host
        restart: unless-stopped
        environment:
            - APP_TIMEZONE=Asia/Jakarta
            - APP_KEY=base64:REPLACE_WITH_YOUR_OWN_KEY # generate with: openssl rand -base64 32
            - DB_CONNECTION=libsql
            - SESSION_DRIVER=file
            - APP_NAME=MyLibSQLAdmin
            - REGISTRATION_ENABLED=false
            - LIBSQL_LOCAL_INSTANCE=false
            - LIBSQL_HOST=your-libsql-server-address
            - LIBSQL_PORT=your-libsql-server-port
            # For a custom domain, set this on the webapp too (see below).
            # - LIBSQL_PARENT_DOMAIN=example.com
            - LIBSQL_API_HOST=your-libsql-server-admin-address
            - LIBSQL_API_PORT=your-libsql-server-admin-port
            - LIBSQL_API_USERNAME=
            - LIBSQL_API_PASSWORD=
```

#### LLI (libSQL Local Instance)

Install MyLibSQLAdmin Platform with `libsql-server` instance

```yml
services:
    webui:
        container_name: mylibsqladmin-webui-prod
        image: ghcr.io/darkterminal/mylibsqladmin-web:latest
        ports:
            - 8000:8000
        networks:
            - mylibsqladmin-network
        restart: unless-stopped
        environment:
            - APP_TIMEZONE=Asia/Jakarta
            - APP_KEY=base64:REPLACE_WITH_YOUR_OWN_KEY # generate with: openssl rand -base64 32
            - DB_CONNECTION=libsql
            - SESSION_DRIVER=file
            - APP_NAME=MyLibSQLAdmin
            - REGISTRATION_ENABLED=false
            - LIBSQL_LOCAL_INSTANCE=true
            - LIBSQL_HOST=proxy
            - LIBSQL_PORT=8080
            - LIBSQL_PARENT_DOMAIN=localhost
            - LIBSQL_API_HOST=db
            - LIBSQL_API_PORT=8081
            - LIBSQL_API_USERNAME=
            - LIBSQL_API_PASSWORD=
        depends_on:
            - db

    proxy:
        container_name: mylibsqladmin-proxy
        image: ghcr.io/darkterminal/mylibsqladmin-proxy:latest
        environment:
            # Kept for images <= 1.6.2; ignored by the current proxy.
            - APP_ENV=production
            - LIBSQL_PARENT_DOMAIN=example.com
            - LIBSQL_VALIDATOR_UPSTREAM=mylibsqladmin-webui-prod:8000
            - LIBSQL_DB_HTTP_UPSTREAM=db:8080
            - LIBSQL_DB_GRPC_UPSTREAM=db:5001
        ports:
            - 8080:8080
            - 5001:5001
        networks:
            - mylibsqladmin-network
        restart: unless-stopped
        depends_on:
            - webui
            - db

    db:
        container_name: mylibsqladmin-db
        image: ghcr.io/tursodatabase/libsql-server:latest
        entrypoint: [/bin/sqld]
        command:
            - --http-listen-addr
            - 0.0.0.0:8080
            - --grpc-listen-addr
            - 0.0.0.0:5001
            - --admin-listen-addr
            - 0.0.0.0:8081
            - --enable-namespaces
            - --no-welcome
        volumes:
            - ./libsql-data:/var/lib/sqld
        restart: unless-stopped
        networks:
            - mylibsqladmin-network

networks:
    mylibsqladmin-network:
        driver: bridge
        name: mylibsqladmin-network
```

#### Run it Up!

```bash
docker compose up -d
```

#### Custom domain + TLS

Host-based routing and these environment variables are part of the **current
source tree**. The image published as `:latest` is still v1.6.2, which uses the
old `.localhost` config and ignores `LIBSQL_PARENT_DOMAIN`. Build the proxy from
this checkout and pin the resulting tag until a release includes the change:

```bash
docker build -t mylibsqladmin-proxy:custom -f nginx/Dockerfile ./nginx
```

Replace the proxy `image:` line in the LLI example above with
`mylibsqladmin-proxy:custom`. The installer script generates a localhost-only
stack; use the manual compose example for a custom domain.

The proxy routes by the `Host` header. It does **not** terminate TLS; put a
TLS-terminating reverse proxy (Caddy, Traefik, nginx) in front of `:8080` and
make sure it forwards the original `Host`. If the edge rewrites `Host` to
`proxy:8080`, the database name is lost and every request is denied.

For `https://admin.example.com` with databases at `https://<db>.example.com`:

1. Point a wildcard DNS record `*.example.com` at the TLS terminator.
2. Set `LIBSQL_PARENT_DOMAIN=example.com` on **both** the webapp and proxy
   containers. The proxy uses it to extract the subdomain; the webapp uses it
   to allow `https://*.example.com` in the Content Security Policy. Multi-label
   parents such as `example.co.uk` and ccTLDs such as `example.id` are
   supported when set explicitly.
3. Set the webapp's `APP_URL=https://admin.example.com`, `LIBSQL_HOST=example.com`
   and `LIBSQL_PORT=443`. `LIBSQL_HOST` is the **client-facing** host used by
   the browser, not an internal Docker service name.
4. Set `LIBSQL_VALIDATOR_UPSTREAM` to the webapp's internal `host:port`
   (for example `mylibsqladmin-webui-prod:8000`). In the current image this
   replaces the old `APP_ENV`-based `sed` rewrite: the mounted template stays
   read-only and is rendered at startup into a writable path. `APP_ENV` can
   remain in the compose file for compatibility with older images.

Keep `:8080` and the sqld admin API `:8081` off the public internet. Only the
TLS terminator should be exposed.

### First-time Bootstrap (fresh installs)

Fresh installs since v1.5.0 do **not** ship any seeded user accounts. The
container entrypoint applies migrations automatically on first boot. To create
the single super-admin, either:

**Option A — automatic (recommended):** set `LIBSQL_SEED_DEFAULTS=true` in
`environment:` before the first `docker compose up`. The entrypoint writes
`storage/app/bootstrap-credentials.txt` (mode `0600`) containing the generated
credentials. Read it once, then delete it:

```bash
docker compose exec webui cat storage/app/bootstrap-credentials.txt
docker compose exec webui rm storage/app/bootstrap-credentials.txt
```

**Option B — manual:** run the seeder yourself (idempotent; safe to re-run to
rotate the password):

```bash
docker compose exec webui php artisan db:seed --class=BootstrapSeeder
```

To set a specific password instead of a generated one, set
`SUPER_ADMIN_PASSWORD` in `environment:`. The seeder never stores the
plaintext; it is only written to the credentials file when generated.

### Upgrading from v1.4.x to v1.5.0

- **Seeded fixture accounts are gone.** `superadmin`, `manager`,
  `database-maintainer`, `member` (with the previously documented public
  passwords) are no longer created on fresh installs, and their passwords
  have never been valid for new bootstraps. Existing installs keep their
  current accounts.
- **Tokens are stored hashed (irreversible).** The migration
  `2026_11_14_000000_hash_existing_database_tokens` converts any plaintext
  tokens in place (SHA-256). Existing bearer tokens keep working — each one
  is hashed identically on both sides. This migration cannot be reversed.
- **Deactivated users can no longer sign in** (web and CLI), and their API
  tokens are revoked on deactivation.
- **CSP / SecureHeaders are now sent by the app.** If you embed MylibSQLAdmin
  inside an `<iframe>`, adjust `frame-ancestors` in
  `app/Http/Middleware/SecureHeaders.php`.
- **Registration is disabled by default** (`REGISTRATION_ENABLED=false`).
- **The sqld admin API (`:8081`) is no longer exposed** through the proxy
  container; the webapp reaches it via the internal Docker network.
- Pin images for reproducible deploys, e.g.
  `ghcr.io/darkterminal/mylibsqladmin-web:1.5.0-production` and
  `ghcr.io/darkterminal/mylibsqladmin-proxy:1.5.0`. `latest` still moves.

### Default Credentials

Fresh production installs create **no** default user with a documented password.
Bootstrap your super-admin with:

```bash
docker compose exec webui php artisan db:seed --class=BootstrapSeeder
```

If `LIBSQL_SEED_DEMO_USERS=true` is set in the environment, the demo users are
created for local testing only — **never set it in production**. Credentials
are generated per install and written to `storage/app/bootstrap-credentials.txt`
by the entrypoint on first boot (re-read it once, then delete it).

## Documentation site (Jekyll)

This directory is also a Jekyll site: `index.html` is the product landing page,
`user-manual/` is the role-based user manual (Super Admin, Team Manager,
Database Maintainer, Member) with screenshots, and `libsql3-man.md` plus the
generated `libsql3-cli-reference.md` are published as the CLI section.

```bash
# from the repository root
gem install jekyll          # once
jekyll build -s docs -d /tmp/mylibsqladmin-docs
jekyll serve -s docs --livereload   # http://127.0.0.1:4000
```

The site is self-contained: no theme gem, no plugins, no CDN for styles or
fonts. GitHub Pages can serve it straight from `/docs` on `main`
(Settings → Pages → Deploy from a branch → `main` / `/docs`).

Layout of the site source:

- `_data/nav.yml` — sidebar sections and previous/next order
- `_layouts/manual.html` — manual shell (header, sidebar, content, TOC, pager)
- `_includes/` — head, header, sidebar, footer
- `assets/css/site.css` — styles for the manual
- `assets/css/landing.css` — styles for the landing page
- `assets/js/site.js` — manual theme toggle, sidebar, TOC, search
- `assets/js/landing.js` — theme toggle, mobile nav, copy button, GitHub stats
- `assets/fonts/` — self-hosted Instrument Sans (SIL OFL, see `OFL.txt`)
- `images/app-logo-icon.svg` — the app's `AppLogoIcon`, used by both headers
- `favicon-light.*` / `favicon-dark.*` — the app's real favicons, theme-aware
- `images/` — screenshots referenced by both the landing page and the manual
- `libsql3-man.md` — narrative CLI guide, published at `/cli/`
- `libsql3-cli-reference.md` — generated command reference, published at `/cli/reference/`

`README.md` is excluded from the Jekyll build and stays repository-facing. The
Jekyll front matter on `libsql3-cli-reference.md` is emitted by
`libsql3/bin/generate-cli-reference.php`; do not add it by hand or regeneration
will undo it. Both CLI pages are reachable from the `_data/nav.yml` sidebar.

### libsql3 distribution status

`libsql3/build/libsql3` was rebuilt on 2026-11-22 from the current `libsql3/src/`
tree and is the CLI artifact tracked in this repository. It contains all 25
commands, reports version `1.6.2`, and is byte-identical to the sources it ships.

Verify it with the build-only gate in `.github/workflows/libsql3-phar.yml`.
The exact rebuild-and-check command is:

```bash
cd libsql3 && BOX_REQUIREMENT_CHECKER=0 box compile && sha256sum build/libsql3
```

It must print `78eaee946be63311ca6b621c991554633963921b0b63fb0eba8ccabc1871e0ae`.
The workflow performs the same rebuild from a clean checkout using pinned Box
3.8.4, compares that SHA-256 against the committed binary, and checks every
source file inside the PHAR byte-for-byte. A mismatch fails CI.

The PHAR is rebuilt only by that workflow. Do not hand-build or silently replace
the tracked binary; commit it together with the source change that justifies the
rebuild so the gate can confirm it.

