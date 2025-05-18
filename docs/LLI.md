# LibSQL Local Instance (LLI) Guide

This guide provides comprehensive instructions for setting up, configuring, and using MylibSQLAdmin with local LibSQL instances. The Local Instance configuration offers a self-contained solution for working with SQLite-compatible database files directly on your system.

## Contents

- [Overview](#overview)
- [Key Benefits](#key-benefits)
- [Requirements](#requirements)
- [Installation](#installation)
  - [Docker Installation](#docker-installation)
  - [Docker Compose Installation](#docker-compose-installation)
  - [Manual Installation](#manual-installation)
- [Configuration](#configuration)
  - [Environment Variables](#environment-variables)
  - [Advanced Configuration](#advanced-configuration)
- [Usage Guide](#usage-guide)
  - [Creating Databases](#creating-databases)
  - [Connecting to Existing Databases](#connecting-to-existing-databases)
  - [Common Database Operations](#common-database-operations)
- [Performance Optimization](#performance-optimization)
- [Troubleshooting](#troubleshooting)
- [Advanced Topics](#advanced-topics)
  - [Custom SQLite Extensions](#custom-sqlite-extensions)
  - [Backup and Recovery](#backup-and-recovery)
  - [Migration from SQLite](#migration-from-sqlite)
- [Resources](#resources)

## Overview

A Local LibSQL Instance (LLI) runs as an integrated component of your MylibSQLAdmin deployment. This configuration provides direct access to SQLite/LibSQL database files stored on your system or within Docker volumes without requiring external servers or network configuration.

LLI is ideal for:

- Development environments
- Testing and prototyping
- Offline database work
- Single-user applications
- Educational settings
- Small to medium-sized projects

The LLI mode launches a libSQL server instance that provides SQLite compatibility with enhanced features such as improved concurrency, extended SQL syntax, and better performance for certain operations.

## Key Benefits

Using MylibSQLAdmin with a Local LibSQL Instance provides several advantages:

- **Simple Setup** - No need for complex network configuration or external database servers
- **Direct File Access** - Work with database files directly on your file system
- **Enhanced SQLite Features** - Leverage libSQL improvements while maintaining compatibility
- **Offline Capability** - Work without internet connectivity
- **Minimal Resource Usage** - Efficient resource utilization compared to traditional database servers
- **Familiar Interface** - Modern web UI for managing traditional SQLite databases
- **Data Portability** - Export and import databases across different environments with ease

## Requirements

### Hardware Requirements

- **CPU**: Any modern x86/64 or ARM processor
- **RAM**: 2GB minimum (4GB recommended)
- **Disk**: 500MB+ available space (plus storage for your databases)

### Software Requirements

#### For Docker Installation

- Docker Engine 20.10.0+
- Docker Compose 2.0.0+ (for compose setup)
- Host system with Linux, macOS, or Windows with WSL2

#### For Manual Installation

- PHP 8.1+
- Composer
- Node.js 16+ and npm
- Git
- Linux, macOS, or Windows with WSL2

## Installation

Choose one of the following installation methods based on your needs:

### Docker Installation

The simplest way to get started with LLI is using our installation script:

```bash
# Clone the repository
git clone https://github.com/darkterminal/mylibsqladmin.git

# Navigate to the project directory
cd mylibsqladmin

# Run the installation script
./install.sh
```

During the installation, select the following options:

- When prompted for application environment, choose your preferred environment
- When prompted for "Use local LibSQL instance?", select **Yes**

The script will configure and start MylibSQLAdmin with a local libSQL instance automatically.

### Docker Compose Installation

For a more customizable setup with Docker Compose:

1. Clone the repository:

   ```bash
   git clone https://github.com/darkterminal/mylibsqladmin.git
   cd mylibsqladmin
   ```

2. Configure the environment:

   ```bash
   cp .env.example .env
   cp admin/.env.example admin/.env
   ```

3. Edit the `.env` file to enable local instance:

   ```
   LIBSQL_LOCAL_INSTANCE=true
   ```

4. Start the services:

   ```bash
   # For development
   make compose-dev/up

   # Or for production
   make compose-prod/up
   ```

### Manual Installation

For environments without Docker or if you need full control over the installation:

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

4. Install dependencies:

   ```bash
   cd admin
   composer install
   npm install
   php artisan key:generate
   cd ..
   ```

5. Start the libSQL server separately:

   ```bash
   # Install libSQL server (if not already installed)
   # See https://github.com/tursodatabase/libsql for installation instructions

   # Start libSQL server
   sqld --db-path ./data --http-listen-addr 0.0.0.0:8080
   ```

6. Start the MylibSQLAdmin application:

   ```bash
   cd admin
   php artisan serve

   # In another terminal window
   cd admin
   npm run dev
   ```

## Configuration

### Environment Variables

Fine-tune your LLI with these environment variables:

| Variable                 | Description                                | Default           | Example        |
| ------------------------ | ------------------------------------------ | ----------------- | -------------- |
| `LIBSQL_LOCAL_INSTANCE`  | Enable local instance mode                 | `true`            | `true`         |
| `LIBSQL_DATA_DIR`        | Directory to store database files          | `/var/lib/libsql` | `/data/sqlite` |
| `LIBSQL_MEMORY_ONLY`     | Run databases in memory only               | `false`           | `true`         |
| `LIBSQL_PAGE_SIZE`       | Database page size in bytes                | `4096`            | `8192`         |
| `LIBSQL_CACHE_SIZE`      | Cache size in KB                           | `2000`            | `4000`         |
| `LIBSQL_BUSY_TIMEOUT`    | Busy timeout in milliseconds               | `5000`            | `10000`        |
| `LIBSQL_JOURNAL_MODE`    | Journal mode                               | `WAL`             | `MEMORY`       |
| `LIBSQL_SYNC_MODE`       | Sync mode                                  | `NORMAL`          | `FULL`         |
| `LIBSQL_MAX_CONNECTIONS` | Maximum number of simultaneous connections | `10`              | `20`           |

### Advanced Configuration

For more advanced settings, you can modify the libSQL server configuration directly:

1. Create a custom configuration file:

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
       "extensions": []
     },
     "admin": {
       "port": 80,
       "logLevel": "info",
       "enableAuth": true,
       "sessionTimeout": 3600
     }
   }
   ```

2. Mount this configuration when using Docker:

   ```bash
   docker run -d \
     --name mylibsqladmin \
     -p 8080:80 \
     -v ./config.json:/etc/mylibsqladmin/config.json \
     -v ./data:/var/lib/libsql \
     darkterminal/mylibsqladmin:latest
   ```

## Usage Guide

### Creating Databases

#### Creating a New Database

1. Navigate to the MylibSQLAdmin web interface (default: http://localhost:8000)
2. Log in with your credentials (default: admin/admin)
3. Click "Databases" in the sidebar
4. Click "Create New Database"
5. Enter a name for your database
6. Optionally configure advanced settings (page size, journal mode, etc.)
7. Click "Create Database"

#### Database Creation Options

When creating a new database, you can configure several options:

- **Database Name**: Name of the database file (without extension)
- **Page Size**: Size of database pages in bytes (default: 4096)
- **Cache Size**: Amount of memory to use for caching (in KB)
- **Journal Mode**: Transaction journaling method (WAL recommended)
- **Sync Mode**: Disk synchronization level (NORMAL for balance of safety/performance)
- **Encoding**: Character encoding for text (default: UTF-8)

### Connecting to Existing Databases

#### Using the Web Interface

1. Navigate to the MylibSQLAdmin web interface
2. Click "Databases" in the sidebar
3. Click "Connect to Database"
4. Either:
   - Select a database from the list of detected databases
   - Click "Browse" to select a database file (if enabled)
   - Enter the path to the database file (e.g., `/var/lib/libsql/mydb.sqlite`)
5. Click "Connect"

#### Using Docker Volumes

When using Docker, place your database files in the mounted data directory:

```bash
# Copy an existing database into the mounted volume
cp my_existing_database.sqlite ./data/

# Then connect to it in the web interface using:
# /var/lib/libsql/my_existing_database.sqlite
```

### Common Database Operations

Once connected to a database, you can:

#### Execute SQL

Use the built-in SQL editor to run queries:

1. Click on "SQL Editor" in the sidebar
2. Enter your SQL query in the editor
3. Click "Execute" or press Ctrl+Enter
4. View results in the results pane below

Example queries:

```sql
-- Create a new table
CREATE TABLE users (
  id INTEGER PRIMARY KEY,
  username TEXT NOT NULL UNIQUE,
  email TEXT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Insert data
INSERT INTO users (username, email)
VALUES ('john_doe', 'john@example.com');

-- Query data
SELECT * FROM users;
```

#### Browse Tables

Navigate and explore your database structure:

1. Click "Tables" in the sidebar
2. Select a table to view its structure and data
3. Use the "Structure" tab to view columns, indexes, and constraints
4. Use the "Data" tab to browse table contents with pagination and filtering

#### Import/Export Data

Import or export data in various formats:

1. Click "Import/Export" in the sidebar
2. For importing:
   - Select the file format (SQL, CSV, JSON)
   - Upload your file or enter data directly
   - Configure import options
   - Click "Import"
3. For exporting:
   - Select the export format (SQL, CSV, JSON)
   - Configure export options (tables, data, structure)
   - Click "Export" to download the file

## Performance Optimization

### Optimizing for Different Workloads

#### Read-Heavy Workloads

For applications that perform many read operations:

```
LIBSQL_CACHE_SIZE=8000
LIBSQL_JOURNAL_MODE=WAL
LIBSQL_SYNC_MODE=NORMAL
```

#### Write-Heavy Workloads

For applications with frequent write operations:

```
LIBSQL_CACHE_SIZE=4000
LIBSQL_JOURNAL_MODE=WAL
LIBSQL_SYNC_MODE=FULL
LIBSQL_PAGE_SIZE=8192
```

#### Memory-Constrained Environments

For systems with limited memory:

```
LIBSQL_CACHE_SIZE=1000
LIBSQL_JOURNAL_MODE=DELETE
LIBSQL_SYNC_MODE=NORMAL
```

### Database Optimization

Regular maintenance tasks for optimal performance:

1. Run VACUUM to reclaim space:

   ```sql
   VACUUM;
   ```

2. Analyze tables for query optimization:

   ```sql
   ANALYZE;
   ```

3. Create indexes for frequently queried columns:
   ```sql
   CREATE INDEX idx_username ON users(username);
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

## Advanced Topics

### Custom SQLite Extensions

libSQL supports loading custom SQLite extensions to extend functionality:

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

4. Load the extension in your database:
   ```sql
   SELECT load_extension('/opt/mylibsqladmin/extensions/math');
   ```

### Backup and Recovery

Implement a robust backup strategy for your databases:

#### Automated Backups

Set up a cron job for regular backups:

```bash
# Add to crontab (runs daily at 2am)
0 2 * * * mkdir -p /backups/$(date +\%Y\%m\%d) && cp /var/lib/libsql/*.db /backups/$(date +\%Y\%m\%d)/
```

#### Backup Strategies

1. **Full database file backup**:

   ```bash
   cp /var/lib/libsql/mydatabase.db /backups/mydatabase_$(date +%Y%m%d).db
   ```

2. **SQL dump for version control**:

   ```bash
   sqlite3 mydatabase.db .dump > mydatabase_schema.sql
   ```

3. **Incremental WAL backup** (when using WAL mode):
   ```bash
   cp /var/lib/libsql/mydatabase.db-wal /backups/mydatabase_wal_$(date +%Y%m%d%H%M%S).wal
   ```

#### Recovery Process

To restore from a backup:

1. Stop the MylibSQLAdmin services:

   ```bash
   make compose-dev/down
   ```

2. Replace the database file:

   ```bash
   cp /backups/mydatabase_20240101.db /var/lib/libsql/mydatabase.db
   ```

3. Restart the services:
   ```bash
   make compose-dev/up
   ```

### Migration from SQLite

libSQL is fully compatible with SQLite databases. To migrate:

1. Simply connect to your existing SQLite database with MylibSQLAdmin
2. Verify functionality by running test queries
3. Set optimal configuration parameters for your workload
4. Begin using libSQL-specific features as needed

## Resources

- [MylibSQLAdmin GitHub Repository](https://github.com/darkterminal/mylibsqladmin)
- [libSQL Documentation](https://github.com/tursodatabase/libsql)
- [SQLite Documentation](https://sqlite.org/docs.html)
- [Discord Community](https://discord.gg/wWDzy5Nt44)
- [DeepWiki Documentation](https://deepwiki.com/darkterminal/mylibsqladmin)

## Next Steps

For information on working with remote LibSQL instances, please refer to [LibSQL Remote Instance Guide](LRI.md).
