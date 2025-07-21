# libsql3 Command Line Interface

**Connect to MylibSQLAdmin Database Platform directly from your terminal.**

## Availables Commands

| Command         | Description                                   | Status |
| --------------- | --------------------------------------------- | ------ |
| `auth:login`    | Authenticate and store API token              | ✅     |
| `auth:logout`   | Logout from the API and remove local token    | ✅     |
| `config:set`    | Set a configuration value (local)             | ✅     |
| `config:get`    | Get a configuration value (local)             | ✅     |
| `config:list`   | List all configuration values (local)         | ✅     |
| `config:delete` | Delete a configuration value (local)          | ✅     |
| `db:shell`      | Open interactive shell for libSQL database    | ✅     |
| `db:list`       | List databases                                | ✅     |
| `db:create`     | Create new database                           | ✅     |
| `db:delete`     | Delete a database from MylibSQLAdmin Platform | ✅     |
| `db:archive`    | List all archived databases                   | ✅     |
| `db:restore`    | Restore a deleted database from archive       | ✅     |
| `group:list`    | List all available groups                     | ✅     |
| `group:create`  | Create a new group                            | ✅     |
| `group:edit`    | Edit a group                                  |        |
| `group:delete`  | Detele a group                                | ✅     |
| `team:list`     | List all available teams                      | ✅     |
| `team:create`   | Create a new team                             | ✅     |
| `team:edit`     | Edit an existing team                         | ✅     |
| `team:delete`   | Delete a team                                 | ✅     |
| `token:list`    | List all generated token                      |        |
| `token:create`  | Create a new token for a database or group    |        |
| `token:edit`    | Edit token for a database or group            |        |
| `token:delete`  | Delete token for a database or group          |        |
| `user:list`     | List all users                                | ✅     |
| `user:create`   | Create a new user                             |        |
| `user:edit`     | Edit a user                                   |        |
| `user:delete`   | Delete a user                                 |        |
| `whoami`        | Display the current user                      | ✅     |

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

# Set default api endpoint
mylibsqladmin@libsql3> config:set LIBSQL_API_ENDPOINT http://localhost:8000

# Successful confirmation
[OK] Configuration set: DEFAULT_TEAM = Engineering Team
```

**Common Settings**

| Key                        | Values                | Description           |
| -------------------------- | --------------------- | --------------------- |
| `DEFAULT_TEAM`             | Engineering Team      | Default team name     |
| `DEFAULT_GROUP`            | default               | Default group name    |
| `LIBSQL_API_ENDPOINT`      | http://localhost:8000 | API endpoint URL      |
| `LIBSQL_DATABASE_ENDPOINT` | http://localhost:8080 | Database endpoint URL |

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
  LIBSQL_API_ENDPOINT        http://localhost:8000
  LIBSQL_DATABASE_ENDPOINT   http://localhost:8080
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

Open an interactive SQL console for your database

**Usage**

```bash
mylibsqladmin@libsql3> db:shell <database_name>
```

**Key Features**

-   Direct SQL access to your databases
-   Auto-installs required tools on first use
-   Secure token handling with multiple authentication methods
-   Full terminal integration

**Requirements**

-   Active login session (`auth:login` first)
-   Internet access for initial setup

**Authentication Flow**

1. Verifies your login credentials
2. Automatically retrieves database-specific token
3. Securely injects credentials into connection

**First-Run Setup**

```bash
[INFO] Turso CLI not found. Installing now...
... installation progress ...
[OK] Turso CLI installed successfully!
```

**Connection Example**

```bash
mylibsqladmin@libsql3> db:shell orders_db
[OK] Opening database shell for: orders_db.example.com
[NOTE] Using authentication token from session
```

**Security Features**

-   Tokens never displayed in clear text
-   Credentials stored only in memory during session
-   All connections use encrypted channels

**Troubleshooting**

```bash
# No active session
[ERROR] There is no active session
[NOTE] Login first using: auth:login <username>

# Missing permissions
[ERROR] API returned status 403: Access denied
```

**Technical Details**

-   Uses libSQL's Turso CLI under the hood
-   Stores configuration in `~/.libsql3`
-   Timeout: 5 minutes for inactive sessions

**Best Practices**

```bash
# For production:
mylibsqladmin@libsql3> auth:login
mylibsqladmin@libsql3> db:shell production_db

# For quick debugging:
mylibsqladmin@libsql3> db:shell staging_db
```

**Notes**

-   All SQL commands are executed directly on your database
-   Press Ctrl+D or type `.exit` to end the session
-   Query history is not persisted for security

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
