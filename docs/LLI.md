# LibSQL Local Instance (LLI) Guide

This guide provides instructions for using MylibSQLAdmin with local LibSQL instances, allowing you to manage SQLite-compatible database files directly on your system.

## Overview

A Local LibSQL Instance (LLI) configuration enables MylibSQLAdmin to work with database files stored locally within Docker volumes or mounted directories. This setup is ideal for development environments and single-user scenarios where you need direct control over your database files.

## Key Benefits

- **Simple Setup** - No external database server required
- **SQLite Compatibility** - Work with existing SQLite databases
- **Offline Capability** - No internet connection needed
- **Direct File Access** - Manage database files on your file system
- **Minimal Resources** - Lightweight compared to traditional database servers

## Installation

### Using the Installation Script (Recommended)

```bash
# Clone the repository
git clone https://github.com/darkterminal/mylibsqladmin.git
cd mylibsqladmin

# Run the installation script
./install.sh
```

When prompted:

- Select your preferred environment (development/production)
- Choose **Yes** when asked about using a local LibSQL instance
- Follow the remaining prompts to complete setup

### Manual Docker Compose Setup

1. Clone and prepare the environment:

```bash
git clone https://github.com/darkterminal/mylibsqladmin.git
cd mylibsqladmin
cp .env.example .env
```

2. Configure for local instance in `.env`:

```env
LIBSQL_LOCAL_INSTANCE=true
```

3. Start the services:

```bash
# Development
docker compose -f docker-compose.dev.yml up -d

# Production
docker compose -f docker-compose.prod.yml up -d
```

## Configuration

### Basic Configuration

The local instance mode is controlled by a single environment variable:

| Variable                | Description                | Default | Required |
| ----------------------- | -------------------------- | ------- | -------- |
| `LIBSQL_LOCAL_INSTANCE` | Enable local instance mode | `true`  | Yes      |

When set to `true`, MylibSQLAdmin will:

- Start an embedded libSQL server
- Store databases in Docker volumes
- Provide a web interface for database management

### Docker Volumes

Local databases are stored in Docker volumes:

- **Development**: `mylibsqladmin_libsql_data_dev`
- **Production**: `mylibsqladmin_libsql_data_prod`

To access database files directly:

```bash
# List databases in development volume
docker run --rm -v mylibsqladmin_libsql_data_dev:/data alpine ls -la /data

# Copy a database file out of the volume
docker run --rm -v mylibsqladmin_libsql_data_dev:/data -v $(pwd):/backup alpine cp /data/mydatabase.db /backup/
```

## Usage

### Accessing the Interface

After installation, access MylibSQLAdmin at:

- Development: `http://localhost:8001`
- Production: `http://localhost:8000`

Default credentials:

- Email: `admin@mylibsqladmin.test`
- Password: `mylibsqladmin`

### Creating a Database

1. Log in to the web interface
2. Navigate to the Databases section
3. Click "Create Database"
4. Enter a database name
5. Click "Create"

### Managing Databases

The interface provides:

- SQL query editor with syntax highlighting
- Table browser and structure viewer
- Data import/export capabilities
- User and permission management

### Connecting Existing SQLite Databases

To use existing SQLite database files:

1. Copy your database file to the Docker volume:

```bash
# For development
docker run --rm -v $(pwd):/source -v mylibsqladmin_libsql_data_dev:/data alpine cp /source/existing.db /data/
```

2. The database will appear in the MylibSQLAdmin interface

## Backup and Restore

### Backing Up Databases

```bash
# Create a backup directory
mkdir -p ./backups

# Backup all databases from development
docker run --rm -v mylibsqladmin_libsql_data_dev:/data -v $(pwd)/backups:/backup alpine tar -czf /backup/databases-$(date +%Y%m%d).tar.gz -C /data .
```

### Restoring Databases

```bash
# Restore databases to development volume
docker run --rm -v mylibsqladmin_libsql_data_dev:/data -v $(pwd)/backups:/backup alpine tar -xzf /backup/databases-20240101.tar.gz -C /data
```

## Troubleshooting

### Cannot Access the Interface

1. Check if containers are running:

```bash
docker compose ps
```

2. View container logs:

```bash
docker compose logs -f
```

3. Ensure ports are not in use:

```bash
# Check if port 8001 (dev) or 8000 (prod) is available
lsof -i :8001
```

### Database File Permissions

If you encounter permission issues:

```bash
# Fix permissions on the volume
docker run --rm -v mylibsqladmin_libsql_data_dev:/data alpine chown -R 1000:1000 /data
```

### Container Won't Start

1. Check Docker daemon is running:

```bash
docker info
```

2. Clean up and restart:

```bash
docker compose down -v
docker compose up -d
```

## Migration from SQLite

LibSQL is fully compatible with SQLite databases. To migrate:

1. Copy your `.sqlite` or `.db` files to the Docker volume
2. Access them through the MylibSQLAdmin interface
3. All SQLite features remain available

## Next Steps

- Explore the web interface and its features
- Set up regular backups for your databases
- Configure additional users and permissions
- For multi-user or remote access needs, see the [Remote Instance Guide](LRI.md)
