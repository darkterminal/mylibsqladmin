### libsql3 Command Line Interface

**Connect to MylibSQLAdmin Database Platform directly from your terminal.**

---

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
Databases for user@example.com
┌───────────┬──────────┬─────────┬──────────────────────┬─────────────────────┐
│ Name      │ Type     │ Group   │ Owner                │ Created At          │
├───────────┼──────────┼─────────┼──────────────────────┼─────────────────────┤
│ orders    │ Database │ retail  │ user@example.com     │ 2023-05-01 09:30:00 │
└───────────┴──────────┴─────────┴──────────────────────┴─────────────────────┘
1 database found
```

**Requirements**

-   Active login session (`auth:login` first)

---

### `db:shell`

Open an interactive SQL console for any database

**Usage**

```bash
mylibsqladmin@libsql3> db:shell <database-url> [--token=AUTH_TOKEN]
```

**Key Features**

-   Auto-installs required tools on first use
-   Multiple secure token sources:
    -   `--token` option (visible in process lists)
    -   Environment variables (`TURSO_AUTH`, `AUTH_TOKEN`, `TOKEN`)
-   Token injection into URL with security masking
-   Full terminal integration

**Connection Process**

1. **Tool Check**:

    - Automatically installs Turso CLI if missing

    ```bash
    <info>Turso CLI not found. Installing now...</info>
    ```

2. **Token Handling** (secure priority order):  
   | Source | Visibility | Security Level |  
   |--------|------------|----------------|  
   | Environment variables | Hidden | ★★★★★ |  
   | `--token` option | Visible in system | ★★☆☆☆ |

    ```bash
    <comment>Using authentication token from {source}</comment>
    ```

3. **Connection**:
    ```bash
    Opening Turso database shell for: https://your-database.example.com
    ```

**Examples**

```bash
# Connect using environment token (most secure)
mylibsqladmin@libsql3> db:shell https://orders-db.example.com

# Connect with explicit token (use temporarily)
mylibsqladmin@libsql3> db:shell https://inventory-db.example.com --token=your_token_here

# First-run installation + connection
mylibsqladmin@libsql3> db:shell https://new-db.example.com
Turso CLI not found. Installing now...
[... installation progress ...]
Turso CLI installed successfully!
Opening database shell for: https://new-db.example.com
```

**Security Recommendations**

```bash
# Recommended for production:
export TURSO_AUTH="your_token"
mylibsqladmin@libsql3> db:shell https://secure-db.example.com

# For temporary debugging only:
mylibsqladmin@libsql3> db:shell https://temp-db.example.com -t "temp_token"
```

**Notes**

-   Tokens are automatically removed from displayed URLs
-   Interactive shell supports all Turso CLI features
-   Maintains separate sessions for concurrent connections
-   Install requires `curl` and internet access

---

### `user:list`

View system users (Admin only)

**Usage**

```bash
mylibsqladmin@libsql3> user:list [--show-email] [--page=1] [--per-page=10]
```

**Options**

-   `--show-email` : Display full email addresses
-   `--page` : Page number for results
-   `--per-page` : Results per page

**Example output**

```
System Users
┌───┬───────────┬──────────────┬──────────────────────┬───────────────────┬─────────────────────┐
│ # │ Username  │ Name         │ Email                │ Roles             │ Created At          │
├───┼───────────┼──────────────┼──────────────────────┼───────────────────┼─────────────────────┤
│ 1 │ admin     │ Admin User   │ a****n@example.com   │ ROLE_SUPER_ADMIN  │ 2023-01-01 00:00:00 │
└───┴───────────┴──────────────┴──────────────────────┴───────────────────┴─────────────────────┘
Use --show-email to display email addresses
Page 1/1 | Users 1-1 of 1
```

**Privacy protection**

-   Email addresses masked by default
-   Requires administrator privileges

---

### `whoami`

Check current login status

**Usage**

```bash
mylibsqladmin@libsql3> whoami
```

**Examples**

```bash
# When logged in
mylibsqladmin@libsql3> whoami
You are: user@example.com

# When not logged in
mylibsqladmin@libsql3> whoami
No active session found. You are not logged in.
```

---

**Key Features**

-   Session management with automatic credential storage
-   Secure connection handling
-   Intuitive table-based data display
-   Privacy-conscious user information masking
-   Works offline for session operations

> **Tip**: Start with `auth:login` to begin your session. Use `whoami` anytime to check your login status.
