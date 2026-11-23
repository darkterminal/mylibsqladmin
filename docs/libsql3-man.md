---
layout: manual
title: libsql3 CLI
description: Terminal client for the MylibSQLAdmin platform — authenticate, set local defaults, and administer databases from a REPL or one-shot commands.
section: CLI
eyebrow: Terminal client
permalink: /cli/
---

**Connect to MylibSQLAdmin Database Platform directly from your terminal.**

## Prerequisites

- PHP 8.4 with the `pdo_sqlite`, `mbstring`, and `intl` extensions.
- A reachable MylibSQLAdmin instance and a Super Admin account for CLI login.
- `turso` on `PATH` or at `~/.turso/turso` for database shells. It is never
  installed automatically; see the database-shell workflow below.
- Endpoint URLs use `https://`. Plain `http://` is accepted only for loopback
  hosts (`localhost`, `127.0.0.1`, `::1`) and prints a visible warning because
  credentials can be observed in transit. The same rule applies to
  `LIBSQL_DATABASE_ENDPOINT`; there is no implicit default for it.
- For a custom-domain deployment, `LIBSQL_DATABASE_ENDPOINT` must be the
  client-facing parent (for example `https://example.com`), not an IP literal
  or an internal container name. The shell connects to
  `<database>.<endpoint-host>`, which requires a DNS name. `db:shell` rejects
  an IP-literal host up front with an actionable error and exits before minting
  a token; the old `http://127.0.0.1:8080` value produced an unresolvable
  `cli-smoke-db.127.0.0.1` and a bare `502`. Use `http://localhost:8080` for a
  local proxy.

## Install / upgrade

Two supported paths:

1. **Verified PHAR.** Obtain `libsql3/build/libsql3` from the artifact uploaded
   by the build-only `libsql3 PHAR` workflow, then verify it:

   ```bash
   sha256sum libsql3/build/libsql3
   ```

   The value must match the SHA-256 recorded in `docs/README.md` under
   "libsql3 distribution status". The workflow artifact name includes the
   CLI version stamped from `Version::CURRENT`.

2. **Source install.** From a checkout:

   ```bash
   cd libsql3
   composer install
   php libsql3
   ```

   `composer.json` declares `"bin": ["libsql3"]`, so installing this package as
   a Composer dependency also exposes `vendor/bin/libsql3`.

To upgrade, replace the PHAR with a newer verified artifact, or pull the new
source and rerun `composer install`. The root `curl | sh` installer is described
in the repository `README.md`; this guide does not redefine it.

## Build from source

```bash
cd libsql3
composer install
BOX_REQUIREMENT_CHECKER=0 box compile
sha256sum build/libsql3
```

`BOX_REQUIREMENT_CHECKER=0` skips only the stale PHP requirement check embedded
in Box 3.8.4; compilation itself is unchanged. The build is reproducible, so the
printed SHA-256 must match the committed artifact recorded in `docs/README.md`.

Check that every bundled source file is byte-identical to the checkout:

```bash
php -r 'Phar::loadPhar("build/libsql3", "libsql3.phar"); foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator("src", FilesystemIterator::SKIP_DOTS)) as $f) { if ($f->getExtension() === "php") { $r = str_replace("src/", "", $f->getPathname()); if (hash_file("sha256", $f->getPathname()) !== hash_file("sha256", "phar://libsql3.phar/src/" . $r)) { echo "DIVERGENT: $r\n"; exit(1); } } } echo "all source files byte-identical\n";'
```

The tracked PHAR is rebuilt only by the build-only workflow. Do not hand-build a
replacement for it.

## First run

```bash
export LIBSQL_API_ENDPOINT=https://admin.example.com
php libsql3/libsql3
mylibsqladmin@libsql3> auth:login <username>
mylibsqladmin@libsql3> config:set DEFAULT_TEAM <team-id>
mylibsqladmin@libsql3> config:set DEFAULT_GROUP <group-id>
```

`auth:login` comes first. Configuration is local to the machine and needs no
authentication. Setting default team and group avoids selection prompts later.
`config:list` shows the stored state; `config:get` checks one key;
`config:delete` removes a key immediately and is not undone by the CLI.

### Display timezone

Timestamps are stored and returned by the platform in UTC. The CLI converts
them for display using the installation's effective display timezone — the same
value the web UI uses. `auth:login` caches it locally as
`LIBSQL3_DISPLAY_TIMEZONE`, so `team:list`, `db:list`, `user:list` and the other
table commands print local wall-clock time in both the REPL and one-shot mode.
Existing sessions without that cached value fall back to the machine zone
(`/etc/localtime` or `TZ`), then UTC.

To override the rendering for one shell, set `LIBSQL3_TIMEZONE` to a valid IANA
identifier:

```bash
export LIBSQL3_TIMEZONE=Asia/Jakarta
php libsql3/libsql3 team:list
```

An invalid override fails closed to UTC rather than guessing. This setting is
display-only: token expiry checks and stored data remain UTC.

## One-shot mode

Passing arguments runs one command without starting the REPL:

```bash
php libsql3/libsql3 config:list
php libsql3/libsql3 db:list
```

With no arguments, the interactive REPL is unchanged. One-shot input is never
interactive: commands that need a missing value exit with an input error instead
of prompting. `--version` prints one line and does not require
`LIBSQL_API_ENDPOINT`.

PsySH 0.12.24 treats a `.psysh.php` in the working directory as an untrusted
project feature. One-shot runs never load it. In the REPL, an interactive run
asks for trust first; a non-interactive run skips it with a `Restricted Mode`
warning. This blocks the CWD-poisoning issue in GHSA-4486-gxhx-5mg7.

Destructive commands that normally ask for confirmation accept `--yes` as the
explicit acknowledgement in one-shot mode. Without `--yes`, the confirmation
defaults to "no" and the command exits `4` without changing data:

```bash
php libsql3/libsql3 db:delete old-db --yes        # recoverable archive
php libsql3/libsql3 db:delete old-db --force      # permanent, not restorable
php libsql3/libsql3 db:restore old-db --yes
php libsql3/libsql3 team:restore 7 --yes
```

`--yes` only answers the prompt; it never changes what the command does.
`--force` keeps its command-specific meaning — for `db:delete` it switches to
the permanent endpoint, so the two options are not interchangeable there.

One-shot output is human-readable and is **not a stable machine contract**.
Scripts that need structured data must call the MylibSQLAdmin platform
API directly; do not parse CLI tables. There is no `--format=json` option in
this release.

## Command reference

Command names, descriptions, arguments, options, and defaults are generated from
Symfony Console metadata in the **CLI command reference** page (see the sidebar).
Regenerate it with `cd libsql3 && composer docs`; CI fails on an uncommitted
signature change. Command signatures are not duplicated here — the generated
file is the only authoritative list.

## Workflows

These are the parts of the CLI workflow that cannot be generated from command
metadata.

### Administration

1. Authenticate first. Without an active stored token, administrative commands
   fail with an explicit login hint.
2. Set local defaults for team and group; create operations then use them
   instead of asking for a selection.
3. Create databases, then verify with the list command. A normal database delete
   is recoverable during the archive window; in the REPL, answer the prompt, or
   in one-shot mode pass `--yes`. A `--force` delete is immediate and permanent.
   Check the archive list before restoring.
4. Teams are archived, not permanently deleted, by the CLI. Restoring an
   archived team reverses that soft delete; there is no CLI path that permanently
   deletes a team.

### Schema policy

A schema database is a template. Create it explicitly as a schema database, and
create child databases from it rather than editing the template in place. Set
default team and group to avoid repeated selection prompts. Archived schema
databases are read-only like any other archived database and retain their
parent/child relationships in the archive list.

### Tokens

Database tokens are opaque random bytes and are stored as SHA-256 hashes on the
platform. Minting prints the plaintext exactly once; save it then because no
command can show it again. The status command returns only a fingerprint, never
token material. Both `db:token:create` and `db:token:status` derive that
fingerprint from the stored hash, so the two values can be compared after a
rotation. Minting a fresh token replaces the previous CLI-minted token for that
database; re-minting is the response to a suspected leak.

Releases built before this artifact passed the database token in the database
shell child process arguments, where other local processes could observe it in
the process list. Treat tokens used with those builds as observed: after
upgrading, mint a fresh token for each affected database. Rotation is an
operator action; the CLI does not perform it automatically.

### Database shell

A database shell needs an active login, an explicit `LIBSQL_DATABASE_ENDPOINT`,
and `turso` already installed. Opting in to installation is explicit; without
it, a missing binary is an error and nothing is downloaded.

The connection flow is:

1. Validate the database name and endpoint. HTTPS is required except for
   loopback hosts; plain HTTP prints a visible warning. The endpoint host must
   be a DNS name, not an IP literal, because the connection uses
   `<database>.<host>`.
2. Mint a database-scoped token through the admin API. Both HTTP 200 and 201 are
   accepted.
3. Store the token in a private `0600` file inside a `0700` directory and start
   a loopback injector that adds `Authorization: Bearer <token>` to upstream
   requests.
4. Launch the Turso shell with a token-free URL and a loopback proxy. The child
   command contains no token and no token-bearing URL.
5. Stop the injector and delete the credential file on every exit path,
   including errors and SIGTERM/SIGINT/SIGHUP.

The SQL session itself is handled by Turso. End it with Ctrl+D or `.exit`.

## Exit codes

Inside the REPL these are command return values. In one-shot mode they are also
the process exit status.

| Code | Meaning | Typical cause |
| ---- | ------- | ------------- |
| `0` | Success | Command completed, or a confirmation was declined |
| `1` | General failure | No active session, invalid input, or an unexpected error |
| `2` | Authentication failure | Missing or invalid stored API token |
| `3` | Validation / input error | Invalid endpoint, wrong arguments, or API rejection |
| `4` | Declined confirmation | Non-interactive call missing a required explicit value |
| `5` | Remote / transport failure | Network or API error while minting a database token |
