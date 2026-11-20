# libsql3 Command Line Interface

**Connect to MylibSQLAdmin Database Platform directly from your terminal.**

## Prerequisites

- PHP 8.4 with the `pdo_sqlite`, `mbstring`, and `intl` extensions.
- A reachable MylibSQLAdmin instance and a Super Admin account for CLI login.
- `turso` on `PATH` or at `~/.turso/turso` for `db:shell`. It is never installed
  automatically; see the explicit opt-in described in the `db:shell` section.
- Endpoint URLs use `https://`. Plain `http://` is accepted only for loopback
  hosts (`localhost`, `127.0.0.1`, `::1`) and prints a visible warning because
  credentials can be observed in transit.

## Install / upgrade

Two supported paths:

1. **Verified PHAR.** Obtain `libsql3/build/libsql3` from the artifact uploaded
   by the build-only `libsql3 PHAR` workflow, then verify it:

   ```bash
   sha256sum libsql3/build/libsql3
   ```

   The value must match the SHA-256 recorded in `docs/README.md` under
   "libsql3 distribution status".

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
in the repository `README.md` (line 65); this guide does not redefine it.

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

`auth:login` comes first. `config:set DEFAULT_TEAM` and
`config:set DEFAULT_GROUP` are functional after Plan 004.

## Exit codes

These are the values commands return inside the REPL. The `libsql3` process exit
status follows the PsySH shell and is not the last command's return value.

| Code | Meaning | Typical cause |
| ---- | ------- | ------------- |
| `0` | Success | Command completed, or a confirmation was declined |
| `1` | General failure | No active session, invalid input, or an unexpected error |
| `2` | Authentication failure | Missing or invalid stored API token |
| `3` | Validation / input error | Invalid endpoint, wrong arguments, or API rejection |
| `4` | Declined confirmation | Non-interactive call missing a required explicit value |
| `5` | Remote / transport failure | Network or API error while minting a database token |

## Availables Commands

| Command            | Description                                            | Status |
| ------------------ | ------------------------------------------------------ | ------ |
| `auth:login`       | Authenticate and store API token                       | ✅     |
| `auth:logout`      | Logout from the API and remove local token             | ✅     |
| `config:set`       | Set a configuration value (local)                      | ✅     |
| `config:get`       | Get a configuration value (local)                      | ✅     |
| `config:list`      | List all configuration values (local)                  | ✅     |
| `config:delete`    | Delete a configuration value (local)                   | ✅     |
| `db:shell`         | Open interactive shell for a database                  | ✅     |
| `db:list`          | List databases                                         | ✅     |
| `db:create`        | Create new database                                    | ✅     |
| `db:delete`        | Delete a database from MylibSQLAdmin Platform          | ✅     |
| `db:archive`       | List all archived databases                            | ✅     |
| `db:restore`       | Restore a deleted database from archive                | ✅     |
| `db:token:create`  | Mint a full-access token for a database (shown once)   | ✅     |
| `db:token:status`  | Show whether a database has a token (fingerprint only) | ✅     |
| `group:list`       | List all available groups                              | ✅     |
| `group:create`     | Create a new group                                     | ✅     |
| `group:delete`     | Delete a group                                         | ✅     |
| `team:list`        | List all available teams                               | ✅     |
| `team:create`      | Create a new team                                      | ✅     |
| `team:edit`        | Edit an existing team                                  | ✅     |
| `team:delete`      | Delete a team                                          | ✅     |
| `team:restore`     | Restore an archived team (soft delete, not force delete) | ✅   |
| `team:find`        | Find a team by name and show its id                    | ✅     |
| `user:list`        | List all users                                         | ✅     |
| `whoami`           | Display the current user                               | ✅     |

Commands not listed above do not exist. Never trust a doc section that
describes `token:list`, `token:edit`, `token:delete`, `user:create`,
`user:edit`, `user:delete`, or `group:edit` — those are aspirational
sketches, not shipped commands. To mint a token, use `db:token:create`.

---

## User Manual

### `auth:login`

Log in to your MylibSQLAdmin account

**Usage**

```bash
mylibsqladmin@libsql3> auth:login <username> [password]
```

**What it does**

-   Securely authenticates your account
-   Saves session credentials locally
-   Detects active sessions to prevent redundant logins

**Examples**

```bash
# Secure login with hidden password prompt
mylibsqladmin@libsql3> auth:login user@example.com
Enter password: ********
Authentication successful!

# Existing session detection
mylibsqladmin@libsql3> auth:login user@example.com
Authentication successful!
You are already logged in until 2025-12-31 23:59:59.
```

**Notes**

-   Omit password argument for secure hidden input
-   Sessions remain valid until expiration

---

### `auth:logout`

End your current session

**Usage**

```bash
mylibsqladmin@libsql3> auth:logout
```

**What it does**

-   Ends active session
-   Removes local credentials
-   Works even without network connection

**Examples**

```bash
# Successful logout
mylibsqladmin@libsql3> auth:logout
Successfully logged out user: user@example.com

# No active session
mylibsqladmin@libsql3> auth:logout
No active session found. You are not logged in.
```

---

### `config:set`

Configure CLI tool settings locally

**Usage**

```bash
mylibsqladmin@libsql3> config:set <key> <value>
```

**What it does**

-   Stores custom settings for your CLI environment
-   Modifies local configuration only (doesn't affect server settings)
-   Instantly applies changes to subsequent commands

**Examples**

```bash
# Set default organization
mylibsqladmin@libsql3> config:set DEFAULT_TEAM "Engineering Team"

# Set the admin API endpoint (https for non-loopback hosts)
mylibsqladmin@libsql3> config:set LIBSQL_API_ENDPOINT https://admin.example.com

# Successful confirmation
[OK] Configuration set: DEFAULT_TEAM = Engineering Team
```

**Common Settings**

| Key                        | Values                     | Description           |
| -------------------------- | -------------------------- | --------------------- |
| `DEFAULT_TEAM`             | Engineering Team           | Default team name     |
| `DEFAULT_GROUP`            | default                    | Default group name    |
| `LIBSQL_API_ENDPOINT`      | https://admin.example.com  | Admin API endpoint    |
| `LIBSQL_DATABASE_ENDPOINT` | https://db.example.com     | Database endpoint     |

Endpoint values require `https://`. Plain `http://` is accepted only for
`localhost`, `127.0.0.1`, or `::1`, and emits a visible warning.

**Notes**

-   Changes persist between sessions
-   View current settings with `config:list` (not shown in code)
-   Configuration stored locally
-   Does not require authentication

---

### `config:get`

View saved configuration values

**Usage**

```bash
mylibsqladmin@libsql3> config:get <key>
```

**Behavior**

-   Prints requested value directly
-   Handles missing keys gracefully

**Examples**

```bash
# Get current setting
mylibsqladmin@libsql3> config:get DEFAULT_TEAM
Engineering Team

# Attempt to get unset key
mylibsqladmin@libsql3> config:get timezone
[WARNING] Configuration key 'timezone' not found
```

**Key Features**

-   Instant access to current configuration
-   Supports all keys set via `config:set`
-   Error-tolerant design

---

**Configuration Notes**

-   Changes stored locally
-   Use `config:set` for preferences, `config:get` for verification
-   No authentication required

---

### `config:list`

View all configured settings at once

**Usage**

```bash
mylibsqladmin@libsql3> config:list
```

**What it shows**

-   Complete list of all stored configuration keys
-   Current values for each setting
-   Formatted as an easy-to-read table

**Example Output**

```
 -------------------------- -----------------------
  Key                        Value
 -------------------------- -----------------------
  DEFAULT_TEAM               Engineering Team
  DEFAULT_GROUP              default
  LIBSQL_API_ENDPOINT        https://admin.example.com
  LIBSQL_DATABASE_ENDPOINT   https://db.example.com
 -------------------------- -----------------------
```

**When no configurations exist**

```bash
[WARNING] No configuration values found
```

**Key Features**

-   Displays the complete configuration state
-   Maintains same format as `config:set`/`config:get`
-   Works with all stored settings
-   Clear visual presentation

**Usage Tips**

```bash
# Verify changes after updates
mylibsqladmin@libsql3> config:set timezone UTC
mylibsqladmin@libsql3> config:list
```

**Technical Notes**

-   Pulls from same local storage as other config commands
-   Returns exit code 0 for empty results (warning only)
-   Preserves all security and privacy aspects of config system

---

### `config:delete`

Remove stored configuration settings

**Usage**

```bash
mylibsqladmin@libsql3> config:delete <key>
```

**What it does**

-   Permanently removes the specified configuration
-   Returns to default behavior for that setting
-   Provides clear success/failure feedback

**Examples**

```bash
# Successful deletion
mylibsqladmin@libsql3> config:delete LIBSQL_API_ENDPOINT
[OK] Configuration deleted: LIBSQL_API_ENDPOINT

# Attempt to delete non-existent key
mylibsqladmin@libsql3> config:delete non_existent_key
[WARNING] Configuration key 'non_existent_key' not found
```

**Common Use Cases**

-   Resetting preferences to defaults
-   Cleaning up old/unused settings
-   Removing temporary configurations

**Notes**

-   Changes take effect immediately
-   Cannot be undone (deletion is permanent)
-   Works with all keys viewable in `config:list`
-   No confirmation prompt (deletes immediately)

**Usage Tip**

```bash
# Verify deletion with config:list
mylibsqladmin@libsql3> config:delete LIBSQL_API_ENDPOINT
mylibsqladmin@libsql3> config:list
```

**Security**

-   Only affects local configuration
-   Never modifies server settings
-   Requires no authentication

---

### `db:list`

View your accessible databases

**Usage**

```bash
mylibsqladmin@libsql3> db:list
```

**What it shows**

-   Database names and types
-   Organization groups
-   Creation dates
-   Ownership information

**Example output**

```
Databases for superadmin
+-----------+----------------------+---------+------------+---------------------+---------------------+
| Name      | Type                 | Group   | Owner      | Created At          | Updated At          |
+-----------+----------------------+---------+------------+---------------------+---------------------+
| db-demo   | standalone           | default | superadmin | 2025-06-25 02:12:30 | 2025-06-25 02:12:30 |
| db-parent | schema database      | default | superadmin | 2025-07-03 10:51:10 | 2025-07-03 10:51:10 |
| db-child  | child of [db-parent] | default | superadmin | 2025-07-03 10:51:31 | 2025-07-03 10:51:31 |
+-----------+----------------------+---------+------------+---------------------+---------------------+
3 databases found
```

**Requirements**

-   Active login session (`auth:login` first)

---

### `db:shell`

Open an interactive SQL console for a database using the Turso CLI.

**Usage**

```bash
mylibsqladmin@libsql3> db:shell <database_name>
```

**Arguments and options**

| Item              | Kind     | Required | Description                                           |
| ----------------- | -------- | -------- | ----------------------------------------------------- |
| `database_name`   | argument | yes      | Letters, digits, `_` and `-` only                     |
| `--install-turso` | option   | no       | Explicitly allow installing Turso when it is missing  |

`LIBSQL3_ALLOW_TURSO_INSTALL=1` is equivalent to `--install-turso`. Without one
of those opt-ins, a missing `turso` binary is an error; nothing is downloaded.

**Requirements**

-   Active login session (`auth:login` first).
-   `turso` on `PATH` or at `~/.turso/turso`.
-   `LIBSQL_DATABASE_ENDPOINT` configured explicitly via the environment or
    `config:set`. There is no implicit default.

**Connection flow**

1. Validate the database name and the configured endpoint. HTTPS is required
   except for loopback hosts; plain HTTP prints a visible warning.
2. Mint a database-scoped token through `POST /api/cli/db/token/mint/<database>`.
   Both HTTP 200 and 201 are accepted.
3. Store the token in a private `0600` file inside a `0700` directory and start
   a loopback injector that adds `Authorization: Bearer <token>` to upstream
   requests.
4. Launch `turso db shell <url> --proxy http://127.0.0.1:<port>`. The child
   command contains no token and no token-bearing URL.
5. Stop the injector and delete the credential file on every exit path,
   including errors and SIGTERM/SIGINT/SIGHUP.

**Example**

```bash
mylibsqladmin@libsql3> db:shell orders_db
[OK] libsql3 for MylibSQLAdmin
[OK] Opening Turso database shell for: orders_db.example.com
```

**Token exposure in older builds**

Releases built before this artifact passed the database token in the Turso child
process arguments, where other local processes could observe it in the process
list. Treat tokens used with those builds as observed: after upgrading, run
`db:token:create <database_name>` once per database to replace them. Rotation is
an operator action; the CLI does not perform it automatically.

**Exit codes**

| Code | Meaning |
| ---- | ------- |
| `0` | Turso shell exited successfully |
| `1` | No active session, invalid input, or an unexpected error |
| `2` | No valid stored API token |
| `3` | `turso` missing without an explicit install opt-in |

**Notes**

-   Press Ctrl+D or type `.exit` to end the SQL session.
-   The SQL session itself is handled by the Turso CLI.

---

### `db:create`

Create a new database with interactive setup

**Usage**

```bash
mylibsqladmin@libsql3> db:create <name> [options]
```

**Key Features**

-   Interactive team/group selection
-   Schema database support
-   Smart defaults and auto-completion
-   Detailed success output

**Options**

| Option         | Description              |
| -------------- | ------------------------ |
| `--team/-t`    | Specify team ID or name  |
| `--group/-g`   | Specify group ID or name |
| `--schema`     | Mark as schema database  |
| `--use-schema` | Extend existing schema   |

**Interactive Flow**

1. Checks for active session
2. Presents available teams/groups
3. Confirms database type (schema/regular)
4. Creates database with provided configuration

**Examples**

```bash
# Simple creation (uses defaults)
mylibsqladmin@libsql3> db:create db-a
[OK] Database 'db-a' created successfully!

# With team/group selection
mylibsqladmin@libsql3> db:create inventory_db --team=retail --group=warehouse

# Create schema database
mylibsqladmin@libsql3> db:create product_schema --schema
```

**Success Output**

```

 [OK] Database 'db-a' created successfully!

 ------ ------ ------------------ --------- ------------ ---------------------
  ID     Name   Team               Group     Schema       Created At
 ------ ------ ------------------ --------- ------------ ---------------------
  1799   db-a   Engineering Team   default   standalone   2025-07-18 20:23:37
 ------ ------ ------------------ --------- ------------ ---------------------

```

**Error Handling**

```bash
# No session
[ERROR] No active session. Login first using: auth:login

# Invalid team
[ERROR] Team 'invalid_team' not found

# Creation failed
[ERROR] Database creation failed: Name already exists
```

**Best Practices**

-   Use `--schema` for template databases
-   Set default team/group in config to skip prompts
-   Combine with `db:list` to verify creation

**Notes**

-   Requires team/group admin privileges
-   Schema databases can't be modified directly
-   Names must be URL-safe (a-z, 0-9, \_-)

---

### `db:delete`

Permanently remove a database

**Usage**

```bash
mylibsqladmin@libsql3> db:delete <database_name> [--force]
```

**Key Features**

-   Interactive confirmation (unless forced)
-   Supports both normal and forced deletion
-   Verifies permissions before executing
-   Clear success/error messaging

**Options**  
| Option | Description |  
|--------|-------------|  
| `--force/-f` | Skip confirmation prompt |

**Deletion Process**

1. **Verification**

    - Checks active session
    - Validates database exists
    - Confirms permissions

2. **Confirmation** (unless forced)

    ```bash
    Are you sure you want to delete database 'orders_db'? (yes/no) [no]:
    ```

3. **Execution**
    - Regular delete (recoverable for 7 days)
    - Force delete (immediate and permanent)

**Examples**

```bash
# Interactive deletion
mylibsqladmin@libsql3> db:delete old_orders
[WARNING] Are you sure? (yes/no) [no]: yes
[OK] Database 'old_orders' deleted successfully!

# Force deletion
mylibsqladmin@libsql3> db:delete temp_data -f
[WARNING] PERMANENT deletion - are you sure? (yes/no) [no]: yes
[OK] Database 'temp_data' permanently deleted!
```

**Security Features**

-   Requires explicit confirmation
-   Separate force-delete pathway
-   Session validation
-   Permission checking

**Error Handling**

```bash
# No permissions
[ERROR] Database deletion failed: API returned status 403: Forbidden

# Non-existent database
[ERROR] Database deletion failed: API returned status 404: Not found
```

**Best Practices**

```bash
# First check what you're deleting
mylibsqladmin@libsql3> db:list

# Use regular delete first
mylibsqladmin@libsql3> db:delete old_data

# Only force delete when absolutely necessary
mylibsqladmin@libsql3> db:delete sensitive_data -f
```

**Notes**

-   Regular deletions can be recovered within 7 days
-   Force deletions are **immediately permanent**
-   Requires database owner or admin privileges
-   Audit logs track all deletion activity

---

### `db:archive`

View and manage archived databases

**Usage**

```bash
mylibsqladmin@libsql3> db:archive
```

**What it shows**

-   List of all archived databases
-   Scheduled deletion dates
-   Original ownership information
-   Database type (schema/standalone/child)

**Example Output**

```
Archived Databases for superadmin
+------------+----------------------+---------+------------+---------------------+-----------------------------+
| Name       | Type                 | Group   | Owner      | Created At          | Delete At                   |
+------------+----------------------+---------+------------+---------------------+-----------------------------+
| db-child-2 | child of [db-parent] | default | superadmin | 2025-07-18 21:43:25 | 2025-07-18T15:05:05.000000Z |
+------------+----------------------+---------+------------+---------------------+-----------------------------+
1 databases found
```

**Key Features**

-   Displays recovery window (Delete At)
-   Preserves original metadata
-   Clear visual distinction of database types
-   Works with both schema and regular databases

**Common Scenarios**

```bash
# Check what's archived
mylibsqladmin@libsql3> db:archive
```

**Error Handling**

```bash
# No session
[ERROR] There is no active session
[NOTE] Login first using auth:login <username>

# No permissions
[ERROR] API returned status 403: Forbidden
```

**Technical Notes**

-   Schema databases show their relationships
-   Requires "view archives" permission
-   Data is read-only in archived state

**Related Commands**

```bash
# Restore an archived database
mylibsqladmin@libsql3> db:restore <name>

# Permanently delete
mylibsqladmin@libsql3> db:delete --force <name>
```

---

### `db:restore`

Recover a previously deleted database from archive

**Usage**

```bash
mylibsqladmin@libsql3> db:restore <database_name> [--force]
```

**Key Features**

-   Preserves all original data and permissions
-   Interactive confirmation (unless forced)
-   Verifies restore eligibility

**Options**

| Option       | Description              |
| ------------ | ------------------------ |
| `--force/-f` | Skip confirmation prompt |

**Restoration Process**

1. **Verification**

    - Checks database exists in archive
    - Validates user permissions
    - Confirms no naming conflicts

2. **Confirmation** (unless forced)

    ```bash
    Are you sure you want to restore database 'orders_backup'? (yes/no) [no]:
    ```

3. **Execution**
    - Reactivates database with original configuration
    - Maintains all relationships (for schema databases)

**Examples**

```bash
# Interactive restore
mylibsqladmin@libsql3> db:restore orders_backup
[WARNING] Are you sure? (yes/no) [no]: yes
[OK] Database 'orders_backup' restored successfully!

# Force restore
mylibsqladmin@libsql3> db:restore critical_db -f
[OK] Database 'critical_db' restored immediately!
```

**Security Features**

-   Requires original owner or admin privileges
-   Separate confirmation for force restore
-   Session validation
-   Permission checking

**Error Handling**

```bash
# Database expired
[ERROR] Restoration failed: Database has been permanently deleted

# Name conflict
[ERROR] Restoration failed: Active database with this name exists

# Insufficient permissions
[ERROR] Restoration failed: API returned status 403
```

**Best Practices**

```bash
# First check archived databases
mylibsqladmin@libsql3> db:archive

# Verify restore eligibility
mylibsqladmin@libsql3> db:archive

# Use normal restore first
mylibsqladmin@libsql3> db:restore orders_backup
```

**Notes**

-   Restores to original team/group
-   May take several minutes for large databases
-   Schema relationships remain intact
-   Original deletion date determines availability

**Related Commands**

```bash
# View available archives
mylibsqladmin@libsql3> db:archive

# Check restored database
mylibsqladmin@libsql3> db:list
```

---

### `group:list`

View all available database groups

**Usage**

```bash
mylibsqladmin@libsql3> group:list
```

**What it shows**

-   Complete list of groups you can access
-   Group IDs and names
-   Member counts
-   Creation/modification timestamps

**Example Output**

```
Available Groups
================

 ---- --------- --------------- --------------------- ---------------------
  ID   Name      Members Count   Created At            Updated At
 ---- --------- --------------- --------------------- ---------------------
  1    default   4               2025-06-23 23:28:25   2025-06-23 23:28:25
 ---- --------- --------------- --------------------- ---------------------

 Total groups: 1
```

**Key Features**

-   Clear tabular display
-   Shows active membership counts
-   Helpful for database creation (`db:create --group`)
-   Works with both personal and team groups

**Common Use Cases**

```bash
# Find group ID for db:create
mylibsqladmin@libsql3> group:list

# Check group activity
mylibsqladmin@libsql3> group:list --sort=updated_at
```

**Requirements**

-   Active login session (`auth:login` first)
-   Group view permissions

**Error Handling**

```bash
# No session
[ERROR] There is no active session
[NOTE] Login first using: auth:login <username>

# No permissions
[ERROR] API returned status 403: Forbidden
```

**Technical Notes**

-   Sorted by creation date (oldest first)
-   Updated At shows last membership change
-   Group IDs are required for API operations

**Related Commands**

```bash
# Create database in specific group
mylibsqladmin@libsql3> db:create --group=<ID>

# View team members
mylibsqladmin@libsql3> team:members <team_id>
```

**Tips**

-   Use group IDs (not names) for scripting
-   Combine with `grep` to find specific groups
-   The "default" group appears if no others exist

---

### `group:delete`

Permanently remove a database group

**Usage**

```bash
mylibsqladmin@libsql3> group:delete [<group_name>] [--force]
```

**Key Features**

-   Interactive group selection (if name not provided)
-   Force option to skip confirmation
-   Validates group existence and permissions
-   Handles both direct and interactive deletion

**Options**  
| Option | Description |  
|--------|-------------|  
| `--force/-f` | Skip confirmation prompt |

**Deletion Process**

1. **Group Selection**

    - Directly by name: `group:delete analytics`
    - Interactively: `group:delete` shows selection menu

2. **Confirmation** (unless forced)

    ```bash
    Are you sure you want to permanently delete this group? (yes/no) [no]:
    ```

3. **Execution**
    - Removes group and all associations
    - Returns success message

**Examples**

```bash
# Direct deletion with confirmation
mylibsqladmin@libsql3> group:delete test_group
[NOTE] Deleting group: test_group (ID: grp_123)
[WARNING] Are you sure? (yes/no) [no]: yes
[OK] Group deleted successfully!

# Force deletion
mylibsqladmin@libsql3> group:delete temp_group -f
[OK] Group temp_group permanently deleted!

# Interactive selection
mylibsqladmin@libsql3> group:delete
1) analytics (ID: grp_abc)
2) ecommerce (ID: grp_def)
Select a group to delete: 1
[WARNING] Are you sure? (yes/no) [no]: yes
[OK] Group deleted successfully!
```

**Security Features**

-   Requires explicit confirmation
-   Validates admin privileges
-   Session verification

**Error Handling**

```bash
# No permissions
[ERROR] Group deletion failed: API returned status 403

# Non-existent group
[ERROR] Group deletion failed: Group 'missing' not found

# Groups with active databases
[ERROR] Group deletion failed: Group contains databases
```

---

### `db:token:create`

Mint a fresh full-access token for a database.

**Usage**

```bash
mylibsqladmin@libsql3> db:token:create my-database-name
```

**What it does**

-   Mints a new full-access opaque token (SHA-256 stored at rest server-side)
-   Prints the plaintext **exactly once** — it is never shown again
-   Replaces any previous `cli-shell-token` minted by this user for that database

**Example Output**

```
Full-access token minted for my-database-name
example-opaque-token-value-64-hex
Fingerprint: ••••••••12345678
Store this token now — it will never be shown again.
```

**Requirements**

-   Active login session (`auth:login` first)
-   Super Admin role (CLI mint path; matches the `auth:login` CLI token contract)

**Common Use Cases**

```bash
# Mint a token for CI to access a database
mylibsqladmin@libsql3> db:token:create metrics-db

# Re-mint after a leak (replaces the previous CLI token automatically)
mylibsqladmin@libsql3> db:token:create metrics-db
```

**Error Handling**

```bash
# No session
<error>There is no active session</error>

# Unknown database
<error>API returned status 404: Database not found</error>
```

---

### `db:token:status`

Report whether a database currently has an access token. Only a masked,
hash-derived fingerprint is shown; raw token material is never returned.

**Usage**

```bash
mylibsqladmin@libsql3> db:token:status <database_name>
```

| Item            | Kind     | Required | Description       |
| --------------- | -------- | -------- | ----------------- |
| `database_name` | argument | yes      | Database name     |

**Example**

```bash
mylibsqladmin@libsql3> db:token:status metrics-db
[OK] A token exists for database 'metrics-db'.
Fingerprint: ••••••••12345678
```

**Exit codes**: `0` status reported; `1` no active session; `2` no valid stored
API token; `3` API or response error.

---

### `group:create`

Create a group inside a team.

**Usage**

```bash
mylibsqladmin@libsql3> group:create <name> --team <team-id>
```

| Item    | Kind     | Required | Description                                   |
| ------- | -------- | -------- | --------------------------------------------- |
| `name`  | argument | yes*     | Group name                                    |
| `--team`, `-t` | option | yes*   | Team ID for the new group                     |

\* Required in non-interactive mode; prompts supply them in an interactive
terminal.

**Example**

```bash
mylibsqladmin@libsql3> group:create analytics --team 42
```

**Exit codes**: `0` created; `1` no active session; `2` no valid token;
`3` API error; `4` non-interactive call missing `name` or `--team`.

---

### `team:list`

List the teams available to the logged-in user.

**Usage**

```bash
mylibsqladmin@libsql3> team:list
```

No arguments or options.

**Example**

```bash
mylibsqladmin@libsql3> team:list
[INFO] No teams found        # or a table of teams
```

**Exit codes**: `0` list shown (including the empty case); `1` no active
session; `2` no valid token; `3` API or response error.

---

### `team:create`

Create a team. The creator becomes its owner.

**Usage**

```bash
mylibsqladmin@libsql3> team:create <name> [--description <text>]
```

| Item                   | Kind     | Required | Description        |
| ---------------------- | -------- | -------- | ------------------ |
| `name`                 | argument | yes      | Team name          |
| `--description`, `-d`  | option   | no       | Team description   |

**Example**

```bash
mylibsqladmin@libsql3> team:create Platform --description "Infrastructure"
```

**Exit codes**: `0` created; `1` no active session; `2` no valid token;
`3` API error.

---

### `team:edit`

Edit a team's name and/or description. In interactive mode, omitted values are
prompted with the current value as the default.

**Usage**

```bash
mylibsqladmin@libsql3> team:edit <id> [--name <new-name>] [--description <text>]
```

| Item             | Kind     | Required | Description                  |
| ---------------- | -------- | -------- | ---------------------------- |
| `id`             | argument | yes      | Team ID                      |
| `--name`         | option   | no       | New team name                |
| `--description`  | option   | no       | New team description         |

**Example**

```bash
mylibsqladmin@libsql3> team:edit 42 --name "Core Platform"
```

**Exit codes**: `0` saved (including no changes); `1` no active session;
`2` no valid token; `3` API error.

---

### `team:delete`

Archive a team. The server uses a soft delete: the record is retained and can
be brought back with `team:restore`.

**Usage**

```bash
mylibsqladmin@libsql3> team:delete <id> [--force]
```

| Item             | Kind     | Required | Description                                   |
| ---------------- | -------- | -------- | --------------------------------------------- |
| `id`             | argument | yes      | Team ID                                       |
| `--force`, `-f`  | option   | no       | Skip the confirmation prompt                  |

**Example**

```bash
mylibsqladmin@libsql3> team:delete 42 --force
mylibsqladmin@libsql3> team:restore 42      # undo an accidental archive
```

**Exit codes**: `0` archived, or confirmation declined; `1` no active session;
`2` no valid token; `3` API error.

---

### `team:restore`

Restore an **archived** team. This is the inverse of `team:delete`'s soft
delete, not a force delete. No CLI command permanently deletes a team (F-22).

**Usage**

```bash
mylibsqladmin@libsql3> team:restore [team_id] [--force]
```

| Item        | Kind     | Required | Description                                        |
| ----------- | -------- | -------- | -------------------------------------------------- |
| `team_id`   | argument | yes*     | Team ID to restore                                 |
| `--force`   | option   | yes*     | Confirm the restore without a prompt               |

\* In an interactive terminal the team can be picked and confirmed with
prompts. In non-interactive mode both the argument and `--force` are required.

**Example**

```bash
mylibsqladmin@libsql3> team:restore 42 --force
```

**Exit codes**: `0` restored, no archived teams, or confirmation declined;
`1` no active session; `2` no valid token; `3` API error; `4` non-interactive
call missing `team_id` or `--force`.

---

### `team:find`

Resolve a team name to its stable fields, including its ID.

**Usage**

```bash
mylibsqladmin@libsql3> team:find <team_name>
```

| Item        | Kind     | Required | Description  |
| ----------- | -------- | -------- | ------------ |
| `team_name` | argument | yes      | Team name    |

**Example**

```bash
mylibsqladmin@libsql3> team:find "Core Platform"
[OK] Team found successfully!
```

**Exit codes**: `0` team found; `1` no active session; `2` no valid token;
`3` API error or team not found.

---

### `user:list`

List users. Restricted to Super Admin accounts.

**Usage**

```bash
mylibsqladmin@libsql3> user:list [--show-email] [--page <n>] [--per-page <n>]
```

| Item              | Kind   | Default | Description                    |
| ----------------- | ------ | ------- | ------------------------------ |
| `--show-email`, `-s` | option | off  | Show full email addresses      |
| `--page`, `-p`    | option | `1`     | Page number (positive integer) |
| `--per-page`, `-l` | option | `10`   | Items per page (positive integer) |

**Example**

```bash
mylibsqladmin@libsql3> user:list --show-email --page 2 --per-page 20
```

**Exit codes**: `0` list shown; `1` no active session; `2` no valid token;
`3` API error; `4` `--page` or `--per-page` is not a positive integer.

---

### `whoami`

Print the identifier of the logged-in user, or report that there is no active
session. It never fails and never contacts the API.

**Usage**

```bash
mylibsqladmin@libsql3> whoami
```

No arguments or options.

**Example**

```bash
mylibsqladmin@libsql3> whoami
You are: admin@example.com
```

**Exit codes**: always `0`, including when there is no active session.

