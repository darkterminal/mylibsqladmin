# LibSQL Local Instance (LLI) Guide

This guide provides comprehensive instructions for setting up, configuring, and using MyLibSQLAdmin with local LibSQL instances. The Local Instance configuration offers a self-contained solution for working with SQLite-compatible database files directly on your system.

## Contents

- [Overview](#overview)
- [Features](#features)
- [Requirements](#requirements)
- [Installation](#installation)
- [Configuration](#configuration)
- [Usage](#usage)
- [Advanced Topics](#advanced-topics)
- [Troubleshooting](#troubleshooting)
- [Optimization](#optimization)
- [Migration](#migration)
- [Resources](#resources)

## Overview

A Local LibSQL Instance (LLI) runs as an integrated component of your MyLibSQLAdmin deployment. This configuration provides direct access to SQLite/LibSQL database files stored on your system or within Docker volumes without requiring external servers or network configuration.

LLI is ideal for:

- Development environments
- Testing and prototyping
- Offline database work
- Single-user applications
- Educational settings

## Features

### Core Capabilities

- **File-based Database Access** - Connect to any SQLite/LibSQL database file
- **Zero Network Configuration** - No need for complex networking setup
- **Offline Operation** - Work without internet connectivity
- **Direct File System Access** - Import/export databases from your file system
- **Full SQLite Compatibility** - Use all SQLite features and extensions

### Technical Advantages

- **Low Resource Footprint** - Minimal system requirements
- **Simple Deployment** - Quick setup with Docker or manual installation
- **Data Persistence** - Store databases in mounted volumes or host directories
- **Custom Extensions** - Support for SQLite extensions and custom modules

## Requirements

### Hardware

- CPU: Any modern x86/64 or ARM processor
- RAM: 2GB minimum (4GB recommended)
- Disk: 500MB+ available space (plus storage for your databases)

### Software

- **Docker Environment**:

  - Docker Engine 20.10.0+
  - Docker Compose 2.0.0+ (for compose setup)
  - Host system with Linux, macOS, or Windows with WSL2

- **Manual Installation**:
  - PHP 8.1+
  - Node.js 16+
  - npm 8+
  - Git

## Installation

Choose one of the following installation methods based on your requirements:

### Method 1: Docker (Recommended)

The simplest way to get started with LLI is using our pre-built Docker image:

```bash
# Pull the official image
docker pull darkterminal/mylibsqladmin:local

# Create a directory for your database files
mkdir -p ./libsql-data

# Start the container with mounted data directory
docker run -d \
  --name mylibsqladmin \
  -p 8080:80 \
  -v ./libsql-data:/var/lib/libsql \
  darkterminal/mylibsqladmin:local
```

Access the web interface at: `http://localhost:8080`

### Method 2: Docker Compose

For a more customizable setup:

1. Create a project directory and navigate into it:

   ```bash
   mkdir mylibsqladmin && cd mylibsqladmin
   ```

2. Create a `docker-compose.yml` file:

   ```yaml
   version: "3"

   services:
     mylibsqladmin:
       image: darkterminal/mylibsqladmin:local
       ports:
         - "8080:80"
       volumes:
         - ./data:/var/lib/libsql
         - ./config:/etc/mylibsqladmin
       environment:
         - LIBSQL_LOCAL_INSTANCE=true
         - MYLIBSQL_ADMIN_PORT=80
         - LIBSQL_CACHE_SIZE=4000
       restart: unless-stopped
   ```

3. Start the service:
   ```bash
   docker-compose up -d
   ```

Access the web interface at: `http://localhost:8080`

### Method 3: Manual Setup from Source

For development or custom installations:

1. Clone the repository:

   ```bash
   git clone https://github.com/darkterminal/mylibsqladmin.git
   cd mylibsqladmin
   ```

2. Configure the local instance:

   ```bash
   cp .env.example .env
   cp admin/.env.example admin/.env
   ```

3. Edit the `.env` file to enable local instance:

   ```
   LIBSQL_LOCAL_INSTANCE=true
   LIBSQL_DATA_DIR=./data
   ```

4. Start the application:
   ```bash
   make compose-dev/up
   ```

Access the web interface at: `http://localhost:8000`

## Configuration

### Environment Variables

Fine-tune your LLI with these environment variables:

| Variable                 | Description                                | Default           | Example        |
| ------------------------ | ------------------------------------------ | ----------------- | -------------- |
| `LIBSQL_LOCAL_INSTANCE`  | Enable local instance mode                 | `true`            | `true`         |
| `LIBSQL_DATA_DIR`        | Directory to store database files          | `/var/lib/libsql` | `/data/sqlite` |
| `LIBSQL_MEMORY_ONLY`     | Run databases in memory only               | `false`           | `true`         |
| `LIBSQL_MAX_CONNECTIONS` | Maximum number of simultaneous connections | `10`              | `20`           |
| `LIBSQL_PAGE_SIZE`       | Database page size in bytes                | `4096`            | `8192`         |
| `LIBSQL_CACHE_SIZE`      | Cache size in KB                           | `2000`            | `4000`         |
| `LIBSQL_BUSY_TIMEOUT`    | Busy timeout in milliseconds               | `5000`            | `10000`        |
| `LIBSQL_JOURNAL_MODE`    | Journal mode                               | `WAL`             | `MEMORY`       |
| `LIBSQL_SYNC_MODE`       | Sync mode                                  | `NORMAL`          | `FULL`         |

### Configuration File

For more advanced settings, mount a custom `config.json` file to `/etc/mylibsqladmin/config.json`:

```json
{
  "libsql": {
    "dataDir": "/var/lib/libsql",
    "memoryOnly": false,
    "maxConnections": 10,
    "pageSize": 4096,
    "cacheSize": 2000,
    "busyTimeout": 5000,
    "journalMode": "WAL",
    "syncMode": "NORMAL",
    "extensions": ["/opt/mylibsqladmin/extensions/math.so"]
  },
  "admin": {
    "port": 80,
    "logLevel": "info",
    "enableAuth": false,
    "sessionTimeout": 3600
  }
}
```

## Usage

### Creating a New Database

1. Navigate to the MyLibSQLAdmin web interface
2. Click "New Connection" in the sidebar
3. Select "Local Instance" as the connection type
4. Enter a name for your database
5. Click "Create New Database"

### Connecting to Existing Databases

#### Using the Web Interface

1. Navigate to the MyLibSQLAdmin web interface
2. Click "New Connection" in the sidebar
3. Select "Local Instance" as the connection type
4. Either:
   - Click "Browse" to select a database file (if enabled)
   - Enter the path to the database file (e.g., `/var/lib/libsql/mydb.sqlite`)
5. Click "Connect"

#### Using Docker Volumes

When using Docker, place your database files in the mounted data directory:

```bash
# Copy an existing database into the mounted volume
cp my_existing_database.sqlite ./libsql-data/

# Then connect to it in the web interface using:
# /var/lib/libsql/my_existing_database.sqlite
```

### Database Operations

Once connected to a database, you can:

- **Execute SQL** - Use the SQL editor to run custom queries
- **Browse Tables** - Navigate through tables, views, indexes, and triggers
- **View Data** - Browse table contents with pagination, filtering, and sorting
- **Import/Export** - Import from SQL files or export to SQL, CSV, or JSON
- **Backup/Restore** - Create and restore database backups

## Advanced Topics

### Custom SQLite Extensions

To use SQLite extensions with your local instance:

1. Create a directory for extensions:

   ```bash
   mkdir -p ./extensions
   ```

2. Place your `.so` or `.dll` extension files in this directory

3. Mount the extensions directory in your Docker container:

   ```yaml
   volumes:
     - ./data:/var/lib/libsql
     - ./extensions:/opt/mylibsqladmin/extensions
   ```

4. Update your configuration to load extensions:

   ```json
   {
     "libsql": {
       "extensions": [
         "/opt/mylibsqladmin/extensions/math.so",
         "/opt/mylibsqladmin/extensions/crypto.so"
       ]
     }
   }
   ```

5. Enable the extension in your database:
   ```sql
   SELECT load_extension('/opt/mylibsqladmin/extensions/math');
   ```

### Virtual Tables

Create and use virtual tables for advanced functionality:

```sql
-- Full-text search example
CREATE VIRTUAL TABLE search USING fts5(title, body);
INSERT INTO search VALUES('SQLite Tutorial', 'Learn how to use SQLite database');
SELECT * FROM search WHERE search MATCH 'sqlite';
```

### JSON Handling

LibSQL provides enhanced JSON support:

```sql
-- Create a table with JSON data
CREATE TABLE users (id INTEGER PRIMARY KEY, data JSON);
INSERT INTO users (data) VALUES ('{"name": "John", "age": 30, "email": "john@example.com"}');

-- Query JSON data
SELECT json_extract(data, '$.name') AS name FROM users;
```

## Troubleshooting

### Common Issues

#### Database Locked Errors

**Problem**:

```
Error: database is locked
```

**Solution**:

1. Increase busy timeout:
   ```
   LIBSQL_BUSY_TIMEOUT=10000
   ```
2. Ensure WAL journal mode is enabled:
   ```
   LIBSQL_JOURNAL_MODE=WAL
   ```
3. Check for other processes accessing the database file

#### Permission Issues

**Problem**:

```
Error: unable to open database file
```

**Solution**:

1. Check file permissions:
   ```bash
   chmod -R 755 ./data
   ```
2. Verify Docker volume mount:
   ```bash
   docker exec -it mylibsqladmin ls -la /var/lib/libsql
   ```
3. Check ownership of files (should match container user):
   ```bash
   chown -R 1000:1000 ./data
   ```

#### Memory Limitations

**Problem**:

```
Error: out of memory
```

**Solution**:

1. Increase Docker container memory limit
2. Reduce cache size if running on limited hardware:
   ```
   LIBSQL_CACHE_SIZE=1000
   ```
3. Use WAL journal mode for better memory management

### Diagnostic Steps

1. Check container logs:

   ```bash
   docker logs mylibsqladmin
   ```

2. Verify database file integrity:

   ```bash
   sqlite3 database.db "PRAGMA integrity_check;"
   ```

3. Test database connection directly:
   ```bash
   sqlite3 database.db ".tables"
   ```

## Optimization

### Performance Tuning

Optimize your local instance for specific workloads:

#### Read-Heavy Workloads

```
LIBSQL_CACHE_SIZE=8000
LIBSQL_JOURNAL_MODE=WAL
LIBSQL_SYNC_MODE=NORMAL
```

#### Write-Heavy Workloads

```
LIBSQL_CACHE_SIZE=4000
LIBSQL_JOURNAL_MODE=WAL
LIBSQL_SYNC_MODE=FULL
LIBSQL_PAGE_SIZE=8192
```

#### Memory-Constrained Environments

```
LIBSQL_CACHE_SIZE=1000
LIBSQL_JOURNAL_MODE=DELETE
LIBSQL_SYNC_MODE=NORMAL
```

### Journal Modes

Select the appropriate journal mode based on your needs:

- **WAL**: Best for concurrent access (default)
- **DELETE**: Most compatible but slower
- **MEMORY**: Fastest but less safe
- **OFF**: No journaling (not recommended for important data)

### Sync Modes

Choose a sync mode based on your durability requirements:

- **FULL**: Highest safety, lowest performance
- **NORMAL**: Good balance of safety and performance (default)
- **OFF**: Highest performance, risk of data loss on system crash

## Migration

### SQLite to LibSQL Migration

LibSQL is fully compatible with SQLite databases. To migrate:

1. Simply connect to your existing SQLite database with MyLibSQLAdmin
2. Verify functionality by running test queries
3. Set optimal configuration parameters for your workload
4. Begin using LibSQL-specific features as needed

### Backup Strategy

Implement a robust backup strategy for your databases:

1. **Regular file-based backups**:

   ```bash
   cp /var/lib/libsql/mydatabase.db /backups/mydatabase_$(date +%Y%m%d).db
   ```

2. **SQL dumps for version control**:

   ```bash
   sqlite3 mydatabase.db .dump > mydatabase_schema.sql
   ```

3. **Use the built-in backup functionality** in MyLibSQLAdmin

4. **Automated backups** with cron job:
   ```bash
   # Add to crontab (runs daily at 2am)
   0 2 * * * cp /var/lib/libsql/*.db /backups/$(date +\%Y\%m\%d)/
   ```

## Resources

- [LibSQL Documentation](https://libsql.org/docs)
- [SQLite Documentation](https://sqlite.org/docs.html)
- [MyLibSQLAdmin GitHub Repository](https://github.com/darkterminal/mylibsqladmin)
- [DeepWiki Documentation](https://deepwiki.com/darkterminal/mylibsqladmin)
- [Discord Community](https://discord.gg/wWDzy5Nt44)

## Next Steps

For information on working with remote LibSQL instances, please refer to [LibSQL Remote Instance Guide](LRI.md).
