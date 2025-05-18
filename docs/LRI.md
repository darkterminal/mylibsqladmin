# LibSQL Remote Instance (LRI) Guide

This guide provides comprehensive instructions for setting up, configuring, and using MylibSQLAdmin with remote LibSQL instances. The Remote Instance configuration enables you to connect to external LibSQL servers, offering enhanced scalability and collaboration features.

## Contents

- [Overview](#overview)
- [Key Benefits](#key-benefits)
- [Architecture](#architecture)
- [Requirements](#requirements)
- [Installation](#installation)
  - [Docker Installation](#docker-installation)
  - [Docker Compose Installation](#docker-compose-installation)
  - [Manual Installation](#manual-installation)
- [Configuration](#configuration)
  - [Environment Variables](#environment-variables)
  - [Advanced Configuration](#advanced-configuration)
- [Connection Management](#connection-management)
  - [Establishing Connections](#establishing-connections)
  - [Authentication Methods](#authentication-methods)
  - [Connection Profiles](#connection-profiles)
  - [Multi-User Access](#multi-user-access)
- [Security Best Practices](#security-best-practices)
- [Advanced Features](#advanced-features)
  - [Replication](#replication)
  - [Monitoring and Statistics](#monitoring-and-statistics)
  - [Batch Operations](#batch-operations)
- [Troubleshooting](#troubleshooting)
- [Performance Optimization](#performance-optimization)
- [Resources](#resources)

## Overview

A LibSQL Remote Instance (LRI) configuration allows MylibSQLAdmin to connect to externally hosted LibSQL servers. This approach separates the database server from the administration interface, enabling team collaboration, higher scalability, and access to advanced server-side features of LibSQL.

LRI is ideal for:

- Team environments with multiple users
- Production deployments
- Cloud-based applications
- Distributed systems
- High-availability requirements
- Scenarios requiring database replication

## Key Benefits

Using MylibSQLAdmin with Remote LibSQL Instances provides several advantages:

- **Multi-User Collaboration** - Multiple team members can work with the same database simultaneously
- **Scalability** - Connect to servers with dedicated resources for better performance
- **Security** - Enforce access control and authentication at the server level
- **Replication Support** - Manage database replicas for high-availability scenarios
- **Network Isolation** - Separate database and application layers for enhanced security
- **Centralized Administration** - Manage multiple databases across different servers from a single interface
- **Advanced Monitoring** - Gain insights into server performance, connections, and resource usage

## Architecture

The LRI architecture consists of several components working together:

1. **MylibSQLAdmin Web Interface**: The user-facing frontend that provides management capabilities
2. **libSQL Server**: The remote database server running in server mode
3. **HTTP/HTTPS API Layer**: Communication protocol between MylibSQLAdmin and libSQL server
4. **Authentication System**: Token-based or certificate-based security

The typical data flow is:

```
Client Browser → MylibSQLAdmin Web Interface → HTTP/HTTPS → libSQL Server → Database Files
```

## Requirements

### Client (MylibSQLAdmin)

- **Hardware**:

  - CPU: Any modern x86/64 or ARM processor
  - RAM: 2GB minimum (4GB recommended)
  - Disk: 500MB+ available space
  - Network: Stable internet connection with access to the libSQL server

- **Software**:
  - Docker Engine 20.10.0+ (for containerized deployment)
  - Modern web browser (Chrome, Firefox, Safari, Edge)
  - PHP 8.1+ (for manual installation)
  - Composer and Node.js (for manual installation)

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

Choose one of the following installation methods for MylibSQLAdmin with remote LibSQL support:

### Docker Installation

The simplest way to get started with LRI is using our installation script:

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
- When prompted for "Use local LibSQL instance?", select **No**
- Enter your remote libSQL server details when prompted

The script will configure and start MylibSQLAdmin with remote libSQL instance support.

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

3. Edit the `.env` file to enable remote instance:

   ```
   LIBSQL_LOCAL_INSTANCE=false
   LIBSQL_HOST=<your-libsql-server-host>
   LIBSQL_PORT=<your-libsql-server-port>
   LIBSQL_API_HOST=<your-libsql-server-admin-api-host>
   LIBSQL_API_PORT=<your-libsql-server-admin-api-port>
   ```

4. Optionally, configure authentication:

   ```
   LIBSQL_API_USERNAME=<your-libsql-server-admin-api-username>
   LIBSQL_API_PASSWORD=<your-libsql-server-admin-api-password>
   ```

5. Start the services:

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

2. Configure the remote instance:

   ```bash
   cp .env.example .env
   cp admin/.env.example admin/.env
   ```

3. Edit the `.env` file to enable remote instance:

   ```
   LIBSQL_LOCAL_INSTANCE=false
   LIBSQL_HOST=<your-libsql-server-host>
   LIBSQL_PORT=<your-libsql-server-port>
   LIBSQL_API_HOST=<your-libsql-server-admin-api-host>
   LIBSQL_API_PORT=<your-libsql-server-admin-api-port>
   ```

4. Install dependencies:

   ```bash
   cd admin
   composer install
   npm install
   php artisan key:generate
   cd ..
   ```

5. Start the MylibSQLAdmin application:

   ```bash
   cd admin
   php artisan serve

   # In another terminal window
   cd admin
   npm run dev
   ```

## Configuration

### Environment Variables

Fine-tune your LRI with these environment variables:

| Variable                    | Description                                | Default | Example                   |
| --------------------------- | ------------------------------------------ | ------- | ------------------------- |
| `LIBSQL_LOCAL_INSTANCE`     | Disable for remote instance                | `true`  | `false`                   |
| `LIBSQL_HOST`               | Hostname of the libSQL server              | -       | `libsql.example.com`      |
| `LIBSQL_PORT`               | Port for libSQL HTTP connections           | `8080`  | `8080`                    |
| `LIBSQL_API_HOST`           | Hostname for libSQL admin API              | -       | `api.libsql.example.com`  |
| `LIBSQL_API_PORT`           | Port for libSQL admin API                  | `8081`  | `8081`                    |
| `LIBSQL_API_USERNAME`       | Username for API authentication (optional) | -       | `admin`                   |
| `LIBSQL_API_PASSWORD`       | Password for API authentication (optional) | -       | `secure_password`         |
| `LIBSQL_CONNECTION_TIMEOUT` | Connection timeout in seconds              | `30`    | `60`                      |
| `LIBSQL_REQUEST_TIMEOUT`    | Request timeout in seconds                 | `60`    | `120`                     |
| `LIBSQL_MAX_CONNECTIONS`    | Maximum number of simultaneous connections | `10`    | `20`                      |
| `LIBSQL_TLS_VERIFY`         | Verify TLS certificates                    | `true`  | `false` (not recommended) |
| `LIBSQL_CACHE_TTL`          | Schema cache time-to-live (seconds)        | `300`   | `600`                     |

### Advanced Configuration

For more advanced settings, you can create a custom configuration file:

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
          "tokenField": "x-libsql-token",
          "token": "your-secret-token"
        },
        {
          "name": "Testing DB",
          "url": "https://test.example.com:8080",
          "tokenField": "x-libsql-token",
          "token": "your-testing-token"
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

When using Docker, mount this configuration:

```bash
docker run -d \
  --name mylibsqladmin-remote \
  -p 8080:80 \
  -v ./config.json:/etc/mylibsqladmin/config.json \
  darkterminal/mylibsqladmin:remote
```

## Connection Management

### Establishing Connections

#### Creating a New Connection

1. Navigate to the MylibSQLAdmin web interface (default: http://localhost:8000)
2. Log in with your credentials (default: admin/admin)
3. Click "Connections" in the sidebar
4. Click "New Connection"
5. Select "Remote Instance" as the connection type
6. Enter the required connection details:
   - **Connection Name**: A descriptive name for this connection
   - **Server URL**: The URL of your LibSQL server (e.g., `https://example.com:8080`)
   - **Authentication Token**: Your LibSQL server access token (if required)
7. Click "Connect"

#### Connection URL Formats

MylibSQLAdmin supports several URL formats for connecting to remote LibSQL servers:

- **HTTP/HTTPS**: `http://hostname:port` or `https://hostname:port`
- **LibSQL Protocol**: `libsql://hostname:port`

Examples:

```
https://libsql.example.com:8080
libsql://db.mycompany.com:8080
```

### Authentication Methods

LibSQL Remote Instances support several authentication methods:

#### Token-Based Authentication

The most common method for securing remote connections:

1. Generate an authentication token on your LibSQL server
2. In MylibSQLAdmin, enter the token in the "Authentication Token" field
3. Optionally specify a custom header name if your server uses a non-standard header

Example connection configuration with custom token header:

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

### Connection Profiles

Save frequently used connections for quick access:

#### Saving a Connection Profile

1. Configure a connection as described above
2. Check "Save connection profile" before connecting
3. Optionally set a connection password for added security
4. Click "Connect and Save"

Your connection profile will be saved and available in the "Saved Connections" list.

#### Managing Connection Profiles

To manage your saved connections:

1. Click "Connections" in the sidebar
2. Click "Manage Profiles"
3. From here you can:
   - Edit existing profiles
   - Delete profiles
   - Export profiles (for backup or sharing)
   - Import profiles

### Multi-User Access

MylibSQLAdmin allows multiple users to work with remote connections:

#### Sharing Connections

To share a connection with other users:

1. Create and save a connection profile as described above
2. Click "Connections" → "Manage Profiles"
3. Select the profile you want to share
4. Click "Share"
5. Choose the users or groups to share with
6. Set permission levels (Read-Only, Edit, Full Access)
7. Click "Share Connection"

#### Working with Multiple Connections

Switch between active connections:

1. Open multiple connections by repeating the connection process
2. Use the connection dropdown in the sidebar to switch between them
3. View connection status, including ping time and server version

## Security Best Practices

### Secure Communication

Always use HTTPS for remote LibSQL connections:

1. Ensure your LibSQL server has a valid TLS certificate
2. Connect using the `https://` protocol in the server URL
3. Set `LIBSQL_TLS_VERIFY=true` to validate certificates

For testing environments only, you can disable certificate verification:

```
LIBSQL_TLS_VERIFY=false
```

**Warning**: Disabling TLS verification is not recommended for production use.

### Token Security

Best practices for token-based authentication:

1. **Use Strong Tokens**: Generate long, random tokens (at least 32 characters)
2. **Rotate Regularly**: Change tokens periodically (every 30-90 days)
3. **Restrict Permissions**: Assign the minimum necessary permissions to each token
4. **Avoid Sharing**: Each user or service should have its own token
5. **Secure Storage**: Store tokens securely, never in plain text or public repositories

### Network Security

Additional measures to secure your remote connections:

1. **IP Restrictions**: Limit server access to specific IP addresses or ranges
2. **Firewall Rules**: Configure firewall to allow only necessary connections
3. **VPN/Private Network**: Consider running libSQL server on a private network
4. **Rate Limiting**: Implement rate limiting to prevent brute force attacks

## Advanced Features

### Replication

LibSQL supports primary-replica replication:

#### Configuring Replication

1. Connect to the primary database server
2. Navigate to "Database Settings" → "Replication"
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

#### Monitoring Replication

To monitor replication status:

1. Connect to your primary database
2. Navigate to "Monitoring" → "Replication"
3. View replication metrics:
   - Replica lag
   - Sync status
   - Last successful replication timestamp
   - Error logs (if any)

### Monitoring and Statistics

Monitor your remote LibSQL server performance:

1. Connect to your remote database
2. Navigate to "Monitoring" in the sidebar
3. View real-time metrics:
   - Connection count
   - Query performance
   - Server load
   - Memory usage
   - Disk I/O
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
   - Schedule routine maintenance tasks

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

3. Check MylibSQLAdmin logs:

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

## Resources

- [MylibSQLAdmin GitHub Repository](https://github.com/darkterminal/mylibsqladmin)
- [libSQL Documentation](https://github.com/tursodatabase/libsql)
- [libSQL Remote Protocol Reference](https://github.com/tursodatabase/libsql/tree/main/docs/remote-protocol)
- [Discord Community](https://discord.gg/wWDzy5Nt44)
- [DeepWiki Documentation](https://deepwiki.com/darkterminal/mylibsqladmin)

## Next Steps

For information on working with local LibSQL instances, please refer to [LibSQL Local Instance Guide](LLI.md).
