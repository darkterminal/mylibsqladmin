# LibSQL Remote Instance (LRI) Guide

This guide provides comprehensive instructions for setting up, configuring, and using MyLibSQLAdmin with remote LibSQL instances. The Remote Instance configuration enables you to connect to external LibSQL servers, offering enhanced scalability and collaboration features.

## Contents

- [Overview](#overview)
- [Features](#features)
- [Requirements](#requirements)
- [Installation](#installation)
- [Configuration](#configuration)
- [Connection Management](#connection-management)
- [Security](#security)
- [Advanced Usage](#advanced-usage)
- [Troubleshooting](#troubleshooting)
- [Performance Optimization](#performance-optimization)
- [Migration](#migration)
- [Resources](#resources)

## Overview

A LibSQL Remote Instance (LRI) configuration allows MyLibSQLAdmin to connect to externally hosted LibSQL servers. This approach separates the database server from the administration interface, enabling team collaboration, higher scalability, and access to advanced server-side features of LibSQL.

LRI is ideal for:

- Team environments with multiple users
- Production deployments
- Cloud-based applications
- Distributed systems
- High-availability requirements

## Features

### Core Capabilities

- **Remote Server Connectivity** - Connect to any LibSQL server over HTTP/HTTPS
- **Multi-User Support** - Multiple administrators can work simultaneously
- **Token-Based Authentication** - Secure access using authentication tokens
- **Replication Management** - Configure and monitor database replication
- **Server Monitoring** - Real-time performance and health metrics

### Technical Advantages

- **Scalability** - Connect to servers with higher resource allocations
- **High Availability** - Work with clustered LibSQL deployments
- **Network Isolation** - Separate database and application layers
- **Cloud Compatibility** - Work with hosted LibSQL services
- **Advanced Security** - TLS encryption and token-based authentication

## Requirements

### Client (MyLibSQLAdmin)

- **Hardware**:

  - CPU: Any modern x86/64 or ARM processor
  - RAM: 2GB minimum (4GB recommended)
  - Disk: 500MB+ available space
  - Network: Stable internet connection

- **Software**:
  - Docker Engine 20.10.0+ (for containerized deployment)
  - Modern web browser (Chrome, Firefox, Safari, Edge)

### Server (LibSQL)

- **Hardware**:

  - CPU: 2+ cores recommended
  - RAM: 4GB minimum (8GB+ recommended for production)
  - Disk: SSD storage recommended for optimal performance
  - Network: Public or private network access with stable connectivity

- **Software**:
  - LibSQL server 0.2.0+
  - TLS certificate for secure connections (recommended)

## Installation

Choose one of the following installation methods for MyLibSQLAdmin with remote LibSQL support:

### Method 1: Docker (Recommended)

The simplest way to get started with LRI is using our pre-built Docker image:

```bash
# Pull the official remote instance image
docker pull darkterminal/mylibsqladmin:remote

# Start the container
docker run -d \
  --name mylibsqladmin-remote \
  -p 8080:80 \
  darkterminal/mylibsqladmin:remote
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
       image: darkterminal/mylibsqladmin:remote
       ports:
         - "8080:80"
       volumes:
         - ./config:/etc/mylibsqladmin
       environment:
         - MYLIBSQL_ADMIN_PORT=80
         - LIBSQL_REMOTE_INSTANCE=true
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

2. Configure the remote instance:

   ```bash
   cp .env.example .env
   cp admin/.env.example admin/.env
   ```

3. Edit the `.env` file to enable remote instance:

   ```
   LIBSQL_REMOTE_INSTANCE=true
   ```

4. Start the application:
   ```bash
   make compose-dev/up
   ```

Access the web interface at: `http://localhost:8000`

## Configuration

### Environment Variables

Fine-tune your LRI with these environment variables:

| Variable                    | Description                                | Default | Example |
| --------------------------- | ------------------------------------------ | ------- | ------- |
| `LIBSQL_REMOTE_INSTANCE`    | Enable remote instance mode                | `true`  | `true`  |
| `LIBSQL_CONNECTION_TIMEOUT` | Connection timeout in seconds              | `30`    | `60`    |
| `LIBSQL_REQUEST_TIMEOUT`    | Request timeout in seconds                 | `60`    | `120`   |
| `LIBSQL_MAX_CONNECTIONS`    | Maximum number of simultaneous connections | `10`    | `20`    |
| `LIBSQL_TLS_VERIFY`         | Verify TLS certificates                    | `true`  | `false` |
| `MYLIBSQL_ADMIN_PORT`       | Web interface port                         | `80`    | `8000`  |
| `LIBSQL_AUTH_REQUIRED`      | Require authentication for connections     | `true`  | `true`  |
| `LIBSQL_CACHE_TTL`          | Schema cache time-to-live (seconds)        | `300`   | `600`   |

### Configuration File

For more advanced settings, mount a custom `config.json` file to `/etc/mylibsqladmin/config.json`:

```json
{
  "libsql": {
    "remote": {
      "connectionTimeout": 30,
      "requestTimeout": 60,
      "maxConnections": 10,
      "tlsVerify": true,
      "cacheTTL": 300,
      "compressionEnabled": true,
      "defaultConnections": [
        {
          "name": "Production DB",
          "url": "https://prod.example.com:8080",
          "tokenField": "x-libsql-token"
        }
      ]
    }
  },
  "admin": {
    "port": 80,
    "logLevel": "info",
    "enableAuth": true,
    "sessionTimeout": 3600
  }
}
```

## Connection Management

### Creating a New Connection

1. Navigate to the MyLibSQLAdmin web interface
2. Click "New Connection" in the sidebar
3. Select "Remote Instance" as the connection type
4. Enter the required connection details:
   - **Connection Name**: A descriptive name for this connection
   - **Server URL**: The URL of your LibSQL server (e.g., `https://example.com:8080`)
   - **Authentication Token**: Your LibSQL server access token (if required)
5. Click "Connect"

### Saving Connection Profiles

Save frequently used connections for quick access:

1. Configure a connection as described above
2. Check "Save connection profile" before connecting
3. Optionally set a connection password for added security
4. Click "Connect and Save"

Your connection profile will be available in the "Saved Connections" list.

### Managing Multiple Connections

MyLibSQLAdmin allows you to work with multiple remote connections simultaneously:

1. Open multiple connections by repeating the connection process
2. Switch between active connections using the connection dropdown in the sidebar
3. View connection status, including ping time and server version

## Security

### Authentication Methods

LibSQL Remote Instances support several authentication methods:

#### Token-Based Authentication

The most common method for securing remote connections:

1. Generate an authentication token on your LibSQL server
2. In MyLibSQLAdmin, enter the token in the "Authentication Token" field
3. Optionally specify a custom header name if your server uses a non-standard header

Example connection with custom token header:

```json
{
  "name": "Production DB",
  "url": "https://prod.example.com:8080",
  "token": "your-secret-token",
  "tokenField": "x-custom-auth-header"
}
```

#### Client Certificate Authentication

For enhanced security, use client certificates:

1. Generate client certificates using your preferred tool
2. In your Docker configuration, mount the certificates:
   ```yaml
   volumes:
     - ./certs:/etc/mylibsqladmin/certs
   ```
3. Configure certificate paths in the connection settings:
   ```json
   {
     "name": "Secure DB",
     "url": "https://secure.example.com:8080",
     "clientCert": "/etc/mylibsqladmin/certs/client.crt",
     "clientKey": "/etc/mylibsqladmin/certs/client.key",
     "caCert": "/etc/mylibsqladmin/certs/ca.crt"
   }
   ```

### Secure Communication

Always use HTTPS for remote LibSQL connections:

1. Ensure your LibSQL server has a valid TLS certificate
2. Connect using the `https://` protocol in the server URL
3. Set `LIBSQL_TLS_VERIFY=true` to validate certificates

For testing environments, you can disable certificate verification:

```
LIBSQL_TLS_VERIFY=false
```

**Warning**: Disabling TLS verification is not recommended for production use.

## Advanced Usage

### Working with Replicated Databases

LibSQL supports primary-replica replication:

1. Connect to the primary database as described above
2. Navigate to "Database Settings" > "Replication"
3. Configure replica settings:
   - Add replica URLs and authentication
   - Set replication mode (sync or async)
   - Configure conflict resolution strategy

Example replication configuration:

```json
{
  "replication": {
    "mode": "async",
    "replicas": [
      {
        "url": "https://replica1.example.com:8080",
        "token": "replica-token-1"
      },
      {
        "url": "https://replica2.example.com:8080",
        "token": "replica-token-2"
      }
    ],
    "conflictResolution": "primary-wins"
  }
}
```

### Monitoring and Statistics

Monitor your remote LibSQL server performance:

1. Connect to your remote database
2. Navigate to "Monitoring" in the sidebar
3. View real-time metrics:
   - Connection count
   - Query performance
   - Server load
   - Replication lag (if applicable)

### Batch Operations

Perform operations on multiple databases:

1. Connect to multiple remote databases
2. Navigate to "Batch Operations"
3. Select the target databases
4. Choose an operation:
   - Execute SQL across multiple databases
   - Compare schema differences
   - Copy data between databases

## Troubleshooting

### Common Issues

#### Connection Timeout

**Problem**:

```
Error: Connection timeout when connecting to server
```

**Solution**:

1. Verify the server URL is correct
2. Check network connectivity to the server
3. Increase connection timeout:
   ```
   LIBSQL_CONNECTION_TIMEOUT=60
   ```
4. Verify the LibSQL server is running and accessible

#### Authentication Failure

**Problem**:

```
Error: Authentication failed: Invalid token
```

**Solution**:

1. Verify your authentication token is correct
2. Check if the token has expired and generate a new one
3. Ensure you're using the correct token header name
4. Check server logs for authentication failures

#### TLS Certificate Issues

**Problem**:

```
Error: Unable to verify server certificate
```

**Solution**:

1. Ensure the server has a valid, non-expired certificate
2. Add the certificate authority to your trusted CAs
3. If using a self-signed certificate, configure the CA certificate:
   ```json
   {
     "tlsVerify": true,
     "caCert": "/path/to/ca.crt"
   }
   ```
4. For testing only, disable TLS verification:
   ```
   LIBSQL_TLS_VERIFY=false
   ```

### Diagnostic Steps

1. Check connectivity to the server:

   ```bash
   curl -I https://your-libsql-server.com:8080
   ```

2. Verify authentication with curl:

   ```bash
   curl -H "x-libsql-token: your-token" https://your-libsql-server.com:8080/health
   ```

3. Check MyLibSQLAdmin logs:

   ```bash
   docker logs mylibsqladmin-remote
   ```

4. Enable debug logging:
   ```
   # In your config.json
   {
     "admin": {
       "logLevel": "debug"
     }
   }
   ```

## Performance Optimization

### Connection Pooling

Optimize connection management for better performance:

1. Configure appropriate connection pool size:

   ```
   LIBSQL_MAX_CONNECTIONS=20
   ```

2. Enable connection reuse in your configuration:
   ```json
   {
     "remote": {
       "connectionPooling": true,
       "maxConnectionsPerHost": 5,
       "idleTimeout": 60
     }
   }
   ```

### Query Optimization

Improve query performance on remote instances:

1. Use prepared statements for repeated queries
2. Implement appropriate indexes on your database
3. Limit result sets when working with large tables
4. Use the query analyzer to identify slow queries

### Caching Strategies

Reduce network overhead with effective caching:

1. Enable schema caching:

   ```
   LIBSQL_CACHE_TTL=600
   ```

2. Configure query result caching:

   ```json
   {
     "remote": {
       "queryCache": {
         "enabled": true,
         "ttl": 300,
         "maxSize": 100
       }
     }
   }
   ```

3. Implement application-level caching for frequently accessed data

## Migration

### SQLite to Remote LibSQL Migration

Migrate from SQLite to a remote LibSQL server:

1. Export your SQLite database:

   ```bash
   sqlite3 local.db .dump > database_dump.sql
   ```

2. Connect to your remote LibSQL server in MyLibSQLAdmin
3. Import the SQL dump file via the "Import" function
4. Verify data integrity after migration
5. Update connection strings in your applications

### Server Migration

Move data between different LibSQL servers:

1. Connect to your source server in MyLibSQLAdmin
2. Export the database via "Export" > "SQL Format"
3. Connect to your target server
4. Import the exported SQL file
5. Verify all objects and data were transferred correctly

## Resources

- [LibSQL Documentation](https://libsql.org/docs)
- [LibSQL Server Setup Guide](https://libsql.org/docs/server-setup)
- [MyLibSQLAdmin GitHub Repository](https://github.com/darkterminal/mylibsqladmin)
- [DeepWiki Documentation](https://deepwiki.com/darkterminal/mylibsqladmin)
- [Discord Community](https://discord.gg/wWDzy5Nt44)

## Next Steps

For information on working with local LibSQL instances, please refer to [LibSQL Local Instance Guide](LLI.md).
