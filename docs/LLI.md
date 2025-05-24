# LibSQL Local Instance (LLI) Guide

This guide provides instructions for using MylibSQLAdmin with local LibSQL instances, allowing you to manage SQLite-compatible database files directly on your system through Docker.

## Overview

A Local LibSQL Instance (LLI) configuration runs MylibSQLAdmin with an embedded libSQL server inside Docker containers. This setup stores databases in Docker volumes and provides a web interface for database management without requiring any external database servers.

## Key Benefits

- **Zero Configuration** - Works out of the box with Docker
- **SQLite Compatibility** - Full compatibility with existing SQLite databases
- **Self-Contained** - Everything runs within Docker containers
- **Development Ready** - Perfect for local development environments
- **Easy Backup** - Simple volume-based backup and restore

## Installation

### Prerequisites

- Docker and Docker Compose installed
- Git (for cloning the repository)
- At least 2GB of free disk space

### Using the Installation Script (Recommended)

```bash
# Clone the repository
git clone https://github.com/darkterminal/mylibsqladmin.git
cd mylibsqladmin

# Run the installation script
./install.sh
```

When prompted during installation:

1. Select your environment (development/production)
2. Choose **Yes** when asked "Do you want to use a local LibSQL instance?"
3. The script will handle all configuration automatically

### Manual Setup

If you prefer manual configuration:

1. Clone the repository:

```bash
git clone https://github.com/darkterminal/mylibsqladmin.git
cd mylibsqladmin
```

2. Create environment files:

```bash
cp .env.example .env
cp admin/.env.example admin/.env
```

3. Edit `.env` and set:

```env
LIBSQL_LOCAL_INSTANCE=true
```

4. Start the services:

```bash
# For development (port 8001)
make compose-dev/up

# For production (port 8000)
make compose-prod/up
```

## Configuration

### Environment Variables

The main configuration file (`.env`) supports these variables:

| Variable                | Description             | Default       | Options                     |
| ----------------------- | ----------------------- | ------------- | --------------------------- |
| `LIBSQL_LOCAL_INSTANCE` | Use local libSQL server | `true`        | `true`, `false`             |
| `APP_ENVIRONMENT`       | Application environment | `development` | `development`, `production` |

### Laravel Application Settings

The `admin/.env` file contains Laravel-specific settings:

```env
APP_NAME=MylibSQLAdmin
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000

# Database connection (handled automatically by Docker)
DB_CONNECTION=libsql
DB_DATABASE=database.db

# Default admin credentials
DEFAULT_USER_EMAIL=admin@mylibsqladmin.test
DEFAULT_USER_PASSWORD=mylibsqladmin
```

### Docker Services

The local instance runs these services:

- **mylibsqladmin-admin**: Laravel application (PHP-FPM)
- **mylibsqladmin-web**: Nginx web server
- **mylibsqladmin-sqld**: LibSQL server instance

## Usage

### Accessing the Interface

After starting the services:

- **Development**: http://localhost:8001
- **Production**: http://localhost:8000

Default login credentials:

- **Email**: `admin@mylibsqladmin.test`
- **Password**: `mylibsqladmin`

> **Important**: Change these credentials after first login!

### Database Storage

Databases are stored in Docker volumes:

- **Development**: `mylibsqladmin_libsql_data_dev`
- **Production**: `mylibsqladmin_libsql_data_prod`

### Working with Databases

#### Creating a New Database

1. Log in to the web interface
2. Navigate to "Databases" in the sidebar
3. Click "Create Database"
4. Enter a database name (alphanumeric and underscores only)
5. Click "Create"

#### Importing Existing SQLite Databases

To import an existing SQLite database:

```bash
# Copy database to the Docker volume
docker cp mydatabase.db mylibsqladmin-sqld:/var/lib/libsql/

# Or using docker run
docker run --rm -v $(pwd):/src -v mylibsqladmin_libsql_data_dev:/dest alpine cp /src/mydatabase.db /dest/
```

The database will automatically appear in the web interface.

#### Executing SQL Queries

1. Select a database from the dropdown
2. Click "SQL Editor" in the sidebar
3. Write your SQL query
4. Press "Execute" or use Ctrl+Enter

## Backup and Restore

### Creating Backups

#### Method 1: Volume Backup

```bash
# Create backup directory
mkdir -p ./backups

# Backup entire volume
docker run --rm \
  -v mylibsqladmin_libsql_data_dev:/data \
  -v $(pwd)/backups:/backup \
  alpine tar czf /backup/libsql-backup-$(date +%Y%m%d-%H%M%S).tar.gz -C /data .
```

#### Method 2: Individual Database Export

```bash
# Export specific database
docker exec mylibsqladmin-sqld sqlite3 /var/lib/libsql/mydatabase.db .dump > mydatabase-backup.sql
```

### Restoring Backups

#### From Volume Backup

```bash
# Stop services first
docker compose down

# Restore volume
docker run --rm \
  -v mylibsqladmin_libsql_data_dev:/data \
  -v $(pwd)/backups:/backup \
  alpine tar xzf /backup/libsql-backup-20240101-120000.tar.gz -C /data

# Restart services
docker compose up -d
```

#### From SQL Dump

```bash
# Import SQL dump
docker exec -i mylibsqladmin-sqld sqlite3 /var/lib/libsql/mydatabase.db < mydatabase-backup.sql
```

## Troubleshooting

### Common Issues

#### Cannot Access Web Interface

1. Check if containers are running:

```bash
docker compose ps
```

2. Check container logs:

```bash
docker compose logs -f mylibsqladmin-admin
docker compose logs -f mylibsqladmin-web
```

3. Verify port availability:

```bash
# For Linux/Mac
lsof -i :8001

# For Windows
netstat -an | findstr :8001
```

#### Permission Errors

If you see permission-related errors:

```bash
# Fix volume permissions
docker exec mylibsqladmin-sqld chown -R 1000:1000 /var/lib/libsql
```

#### Container Fails to Start

1. Check Docker is running:

```bash
docker version
```

2. Clean up and restart:

```bash
# Stop and remove containers
docker compose down

# Remove volumes (WARNING: This deletes data!)
docker compose down -v

# Restart
docker compose up -d
```

### Viewing Logs

```bash
# View all logs
docker compose logs

# Follow specific service logs
docker compose logs -f mylibsqladmin-admin

# View last 100 lines
docker compose logs --tail=100
```

## Performance Tuning

### Memory Configuration

For better performance with large databases, adjust Docker memory limits in `docker-compose.yml`:

```yaml
services:
  sqld:
    mem_limit: 2g
    memswap_limit: 2g
```

### Database Optimization

Regular maintenance improves performance:

```bash
# Vacuum database
docker exec mylibsqladmin-sqld sqlite3 /var/lib/libsql/mydatabase.db "VACUUM;"

# Analyze for query optimization
docker exec mylibsqladmin-sqld sqlite3 /var/lib/libsql/mydatabase.db "ANALYZE;"
```

## Security Considerations

1. **Change Default Credentials**: Always change the default admin credentials after installation
2. **Network Isolation**: The libSQL server is not exposed outside Docker by default
3. **Regular Backups**: Implement automated backup procedures
4. **Updates**: Keep Docker images updated for security patches

## Next Steps

- [Remote Instance Guide](LRI.md) - For connecting to external libSQL servers
- [Docker Compose Reference](https://docs.docker.com/compose/) - Advanced Docker configuration
- [LibSQL Documentation](https://github.com/tursodatabase/libsql) - Understanding libSQL features
