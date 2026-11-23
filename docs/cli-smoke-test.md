# libsql3 manual CLI smoke test (contributors)

This is a **repository-facing contributor guide**. It is excluded from the
Jekyll build via `docs/_config.yml`; end users should read the
[CLI manual](libsql3-man.md) and the
[generated command reference](libsql3-cli-reference.md) instead.

Automated tests are the primary gate (`composer test` in `libsql3/`, including
the route/call parity checks in `libsql3/tests/ContractTest.php`). This guide
covers the end-to-end path those tests cannot prove: every `libsql3` command
against a real webapp + proxy + sqld stack.

## Safety rules

Read these before running anything.

- Use a **disposable local stack**. Never point `LIBSQL_API_ENDPOINT` at a
  staging or production deployment.
- Never paste a token, password, or bootstrap credential into this file, an
  issue, or a commit.
- Use an isolated `HOME`; the CLI stores its login token in
  `$HOME/.mylibsqladmin/libsql3.db`.
- Prefer the recoverable paths. `--force` on `db:delete` is permanent and is
  kept in a separate optional section below.
- This guide does not ask you to push, tag, open a PR, or publish anything.

## 1. Prerequisites

From the repository root:

- Docker Engine with Compose v2. **Kiri is opt-in for local development**:
  set `CONTAINER_ENGINE=kiri` only if you deliberately want to use it.
- PHP 8.4 with `pdo_sqlite`, `mbstring`, and `intl` for running the CLI from
  source.
- A super-admin account. On a fresh stack, bootstrap one and read the generated
  credentials once.
- Optional: `turso` on `PATH` (or at `~/.turso/turso`) for the `db:shell` check.
  The CLI never installs it automatically.

Check the stack first:

```bash
cd "$(git rev-parse --show-toplevel)"

ENGINE=${CONTAINER_ENGINE:-docker}
if [ "$ENGINE" = kiri ]; then
    COMPOSE="kiri compose"
else
    COMPOSE="docker compose"
fi

FILES="-f compose.yml -f compose.lli.yml"
PROFILES="--profile development --profile local-instance"
WEB_SERVICE=web   # dev container: mylibsqladmin-webui-dev

$COMPOSE $FILES $PROFILES ps
```

If there is no super-admin yet:

```bash
$COMPOSE $FILES $PROFILES exec "$WEB_SERVICE" php artisan db:seed --class=BootstrapSeeder
$COMPOSE $FILES $PROFILES exec "$WEB_SERVICE" cat storage/app/bootstrap-credentials.txt
$COMPOSE $FILES $PROFILES exec "$WEB_SERVICE" rm storage/app/bootstrap-credentials.txt
```

Store the password in your password manager, then delete the credentials file.
Do not write it into a shell history, a scratch file, or this guide.

## 2. Isolate the CLI session

```bash
REAL_HOME=$HOME
export TEST_HOME=$(mktemp -d)
if [ -x "$REAL_HOME/.turso/turso" ]; then
    ln -sfn "$REAL_HOME/.turso" "$TEST_HOME/.turso"
fi
export HOME="$TEST_HOME"

export LIBSQL_API_ENDPOINT=http://127.0.0.1:8000
# The proxy derives the database subdomain from the original Host header.
# Use the client-facing parent host, not an IP literal.
export LIBSQL_DATABASE_ENDPOINT=http://localhost:8080

php libsql3/libsql3 --version       # libsql3 1.6.3
```

The number comes from `libsql3/src/Version.php::CURRENT`; check that constant
rather than hardcoding an expectation in a test. `--version` is answered before
the application boots, so it does not need an endpoint. Plain `http://` on a
loopback host is accepted and prints a warning;
that warning is expected, not a failure. For a custom domain the endpoint must
be `https://` and the proxy/webapp must share the same `LIBSQL_PARENT_DOMAIN`
(see the [installation guide](README.md#custom-domain--tls)).

## 3. The 25 commands

Built-in Symfony Console commands (`list`, `help`, `completion`, `_complete`)
are not part of these 25, but `list` is useful as a sanity check.

| Group | Commands |
|---|---|
| auth | `auth:login`, `whoami`, `auth:logout` |
| config | `config:set`, `config:get`, `config:list`, `config:delete` |
| team | `team:list`, `team:create`, `team:find`, `team:edit`, `team:delete`, `team:restore` |
| group | `group:list`, `group:create`, `group:delete` |
| database | `db:create`, `db:list`, `db:archive`, `db:restore`, `db:delete`, `db:shell` |
| token | `db:token:status`, `db:token:create` |
| user | `user:list` |

The generated reference owns signatures and defaults. Do not duplicate them
here; when a command signature changes, regenerate it with
`cd libsql3 && composer docs`.

## 4. Execution

### 4.1 Metadata

```bash
php libsql3/libsql3 list
php libsql3/libsql3 help db:create
```

Expect 28 visible commands: the 25 libsql3 commands plus `completion`, `help`,
and `list`. The generated reference has 29 sections because it also documents
Symfony's hidden `_complete` command. `help db:create` must show `name`,
`--team/-t`, `--group/-g`, `--schema`, and `--use-schema`.

### 4.2 Local config, before login

```bash
php libsql3/libsql3 config:set DEFAULT_TEAM "Engineering"
php libsql3/libsql3 config:get DEFAULT_TEAM
php libsql3/libsql3 config:set LIBSQL_DATABASE_ENDPOINT "http://localhost:8080"
php libsql3/libsql3 config:list
php libsql3/libsql3 config:delete DEFAULT_TEAM
php libsql3/libsql3 config:get DEFAULT_TEAM
```

The last `config:get` should print no value. These keys live only in the
isolated SQLite store.

### 4.3 Login

Use the interactive REPL; it needs a real TTY, not a pipe:

```bash
php libsql3/libsql3
# mylibsqladmin@libsql3> auth:login <superadmin-username>
# Enter password: <hidden>
# mylibsqladmin@libsql3> whoami
# mylibsqladmin@libsql3> exit
```

Expect `Authentication successful!` and then `You are: <username>`. The CLI
does **not** print the plaintext token; it stores it locally with `0600`
permissions.

One-shot mode is non-interactive and therefore needs a token up front:

```bash
export LIBSQL3_AUTH_TOKEN='<token obtained from a local login>'
php libsql3/libsql3 auth:login <superadmin-username> --token "$LIBSQL3_AUTH_TOKEN"
php libsql3/libsql3 whoami
```

`LIBSQL3_AUTH_TOKEN` is a fallback token for commands that call the API.
`whoami` reads identity from the local store, so it still prints "not logged
in" if the store is empty. Prefer the REPL unless you are testing one-shot
behaviour explicitly.

### 4.4 Teams (6 commands)

```bash
php libsql3/libsql3 team:list
php libsql3/libsql3 team:create "CLI Smoke Team" --description "manual smoke"
php libsql3/libsql3 team:find "CLI Smoke Team"
php libsql3/libsql3 team:list
```

Copy the id from `team:find` into `TEAM_ID`, then:

```bash
TEAM_ID=<id-from-team:find>
php libsql3/libsql3 team:edit "$TEAM_ID" --name "CLI Smoke Team Renamed"
php libsql3/libsql3 team:find "CLI Smoke Team Renamed"
php libsql3/libsql3 team:delete "$TEAM_ID" --yes
php libsql3/libsql3 team:list
php libsql3/libsql3 team:restore "$TEAM_ID" --yes
php libsql3/libsql3 team:list
```

`team:delete` archives, it does not permanently delete. `--yes` answers the
non-interactive confirmation; it does not change what the command does.

### 4.5 Groups (3 commands)

```bash
php libsql3/libsql3 group:list
php libsql3/libsql3 group:list --sort name
php libsql3/libsql3 group:create "cli-smoke-group" --team "$TEAM_ID"
php libsql3/libsql3 group:list
php libsql3/libsql3 group:delete "cli-smoke-group" --yes
php libsql3/libsql3 group:list
```

Without `--team` and without a configured default, the REPL asks for a
selection and one-shot mode stops with an input error.

### 4.6 Database, tokens, and shell (9 commands)

```bash
php libsql3/libsql3 group:create "cli-smoke-dbgroup" --team "$TEAM_ID"
php libsql3/libsql3 db:create cli-smoke-db --team "$TEAM_ID" --group cli-smoke-dbgroup
php libsql3/libsql3 db:list

php libsql3/libsql3 db:token:status cli-smoke-db
php libsql3/libsql3 db:token:create cli-smoke-db
php libsql3/libsql3 db:token:status cli-smoke-db
```

The plaintext token appears only at creation. Create and status must show the
**same hash-derived fingerprint**, so a rotation can be confirmed without
revealing the credential. Re-minting replaces the previous CLI-minted token.

Exercise the recoverable delete path, then leave the database in place for the
shell check:

```bash
php libsql3/libsql3 db:delete cli-smoke-db --yes
php libsql3/libsql3 db:archive
php libsql3/libsql3 db:restore cli-smoke-db --yes
php libsql3/libsql3 db:list
```

For `db:shell`, `turso` must be discoverable:

```bash
php libsql3/libsql3 db:shell cli-smoke-db
# end the session with .exit or Ctrl+D
```

Expect a SQLite shell. The token must not appear in the child process
arguments; a loopback injector adds the `Authorization` header and removes the
credential file on exit. If `turso` is unavailable, skip this check and say so
in your report rather than installing it implicitly.

If `db:shell` reports that `LIBSQL_DATABASE_ENDPOINT` uses an IP literal, set a
resolvable hostname and retry: using `http://127.0.0.1:8080` would make the
client request `cli-smoke-db.127.0.0.1`, which cannot resolve. Use
`http://localhost:8080` for a local proxy. The CLI now fails before minting a
token instead of surfacing a bare `502` from the injector.

### 4.7 Users and logout

```bash
php libsql3/libsql3 user:list
php libsql3/libsql3 user:list --show-email --page 1 --per-page 5

php libsql3/libsql3 auth:logout
php libsql3/libsql3 whoami
php libsql3/libsql3 team:list
```

After logout, `whoami` reports no session and `team:list` fails with a login
hint (exit `2`).

### 4.8 Optional destructive check — disposable stack only

Only on a throwaway database whose loss does not matter:

```bash
php libsql3/libsql3 db:delete cli-smoke-db --force
php libsql3/libsql3 db:archive
```

`--force` switches `db:delete` to the permanent endpoint. It is not equivalent
to `--yes`. Never run this against a shared instance.

## 5. Negative checks

Run these with the isolated `HOME` still active. The numbers below are observed
per-command return values, not a global convention.

```bash
php libsql3/libsql3 no:such:command ; echo "exit=$?"          # 1
php libsql3/libsql3 db:create ; echo "exit=$?"                # 1, missing required arg
php libsql3/libsql3 team:restore ; echo "exit=$?"             # 4, no TTY confirmation
LIBSQL3_AUTH_TOKEN=bogus php libsql3/libsql3 whoami ; echo "exit=$?"  # 0, "not logged in"

env -u LIBSQL_API_ENDPOINT php libsql3/libsql3 config:list ; echo "exit=$?"  # 1

# Re-establish the isolated session if you already ran the logout in 4.7.
php libsql3/libsql3 auth:login <superadmin-username> --token "$LIBSQL3_AUTH_TOKEN"

# With a stored session but a dead endpoint -> API exception is caught:
LIBSQL_API_ENDPOINT=http://127.0.0.1:9 php libsql3/libsql3 team:list ; echo "exit=$?"  # 3
# Without a stored session, the same endpoint fails earlier at the session gate:
env -u LIBSQL3_AUTH_TOKEN HOME="$(mktemp -d)" LIBSQL_API_ENDPOINT=http://127.0.0.1:9 \
    php libsql3/libsql3 team:list ; echo "exit=$?"  # 1
```

| Code | Typical meaning |
|---|---|
| `0` | Success, no data, or a deliberately declined confirmation |
| `1` | General failure: no session, unknown command, missing required argument, unset endpoint |
| `2` | Token unavailable or local token storage failed |
| `3` | API exception or server-side validation rejection |
| `4` | Non-interactive guard: missing id or unanswered confirmation |
| `5` | Transport failure while minting a database token |

`whoami` always exits `0`; it only reads local state. Symfony Console rejects
unknown commands and missing required arguments before `execute()` runs, hence
`1` rather than `3`.

## 6. Report template

Copy this into your PR description or issue comment:

| Area | Result | Notes |
|---|---|---|
| Metadata / config / login | pass / fail | |
| Teams (6) | pass / fail | |
| Groups (3) | pass / fail | |
| Database + tokens + shell | pass / fail | note if `turso` skipped |
| Users / logout | pass / fail | |
| Negative checks | pass / fail | observed exit codes |
| Stack | Docker / Kiri | profile and version |
| CLI | source / PHAR | `php libsql3/libsql3 --version` |

Mention any deviation from the expected exit codes and the exact command that
produced it. "It looked fine" is not a reproducible report.

## 7. Cleanup

```bash
php libsql3/libsql3 auth:logout 2>/dev/null || true
rm -rf "$TEST_HOME"
unset LIBSQL3_AUTH_TOKEN
unset LIBSQL_DATABASE_ENDPOINT
export HOME="$REAL_HOME"
unset TEST_HOME
```

Leaving `HOME` unset would break unrelated commands in the same shell; always
restore it.

`rm -rf "$TEST_HOME"` removes only the isolated CLI session. Test data created
on the server (team, group, database, tokens) is still there; remove it through
the CLI/UI or discard the disposable stack entirely. Do not run destructive
cleanup against a stack someone else is using.
