# LibSQL Remote Instance (LRI) Guide

This guide provides instructions for connecting MylibSQLAdmin to remote LibSQL servers, enabling team collaboration and production deployments.

## Overview

A LibSQL Remote Instance (LRI) configuration allows MylibSQLAdmin to connect to external LibSQL servers such as [Turso](https://turso.tech) or self-hosted libSQL instances. This setup is ideal for production environments, team collaboration, and scenarios requiring centralized database management.

## Key Benefits

- **Team Collaboration** - Multiple users can work with the same databases
- **Production Ready** - Suitable for production deployments
- **Scalability** - Leverage cloud infrastructure for better performance
- **High Availability** - Built-in replication and backup features (when using Turso)
- **Security** - Enterprise-grade security with authentication tokens

## Prerequisites

Before setting up a remote instance connection, you need:

1. A running LibSQL server:

   - [Turso](https://turso.tech) (managed service)
   - Self-hosted libSQL server
   - Or any LibSQL-compatible endpoint

2. Connection credentials:
   - Database URL
   - Authentication token (if required)

## Installation

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
2. Choose **No** when asked "Do you want to use a local LibSQL instance?"
3. Enter your remote LibSQL server details when prompted

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

3. Edit `.env` and configure for remote instance:

```env
LIBSQL_LOCAL_INSTANCE=false
```

4. Edit `admin/.env` and add your remote connection details:

```env
# LibSQL Remote Configuration
LIBSQL_DB_URL=libsql://your-database.turso.io
LIBSQL_AUTH_TOKEN=your-auth-token-here
```

5. Start the services:

```bash
# For development (port 8001)
make compose-dev/up

# For production (port 8000)
make compose-prod/up
```

## Configuration

### Environment Variables

The main configuration file (`.env`) supports:

| Variable                | Description                  | Default       | Options                     |
| ----------------------- | ---------------------------- | ------------- | --------------------------- |
| `LIBSQL_LOCAL_INSTANCE` | Use local or remote instance | `true`        | `true`, `false`             |
| `APP_ENVIRONMENT`       | Application environment      | `development` | `development`, `production` |

### Remote Connection Settings

In `admin/.env`, configure your remote connection:

| Variable            | Description                               | Example                     |
| ------------------- | ----------------------------------------- | --------------------------- |
| `LIBSQL_DB_URL`     | Remote LibSQL server URL                  | `libsql://db-name.turso.io` |
| `LIBSQL_AUTH_TOKEN` | Authentication token                      | `eyJ...` (your token)       |
| `LIBSQL_SYNC_URL`   | Sync URL for embedded replicas (optional) | `libsql://db-name.turso.io` |

### Connection URL Formats

MylibSQLAdmin supports various LibSQL URL formats:

- **Turso**: `libsql://[database]-[organization].turso.io`
- **Self-hosted HTTP**: `http://your-server:8080`
- **Self-hosted HTTPS**: `https://your-server:8080`
- **WebSocket**: `wss://[database]-[organization].turso.io`

## Working with Turso

[Turso](https://turso.tech) is a managed LibSQL service that provides:

- Global edge deployment
- Automatic replication
- Built-in backups
- Simple management

### Setting up Turso

1. Install the Turso CLI:

```bash
curl -sSfL https://get.tur.so/install.sh | bash
```

2. Authenticate:

```bash
turso auth login
```

3. Create a database:

```bash
turso db create my-database
```

4. Get your database URL:

```bash
turso db show my-database --url
```

5. Create an authentication token:

```bash
turso db tokens create my-database
```

6. Use these values in your MylibSQLAdmin configuration

### Example Turso Configuration

```env
# In admin/.env
LIBSQL_DB_URL=libsql://my-database-myorg.turso.io
LIBSQL_AUTH_TOKEN=eyJhbGciOiJFZERTQSIsInR5cCI6IkpXVCJ9...
```

## Usage

### Accessing the Interface

After starting the services:

- **Development**: http://localhost:8001
- **Production**: http://localhost:8000

Default login credentials:

- **Email**: `admin@mylibsqladmin.test`
- **Password**: `mylibsqladmin`

> **Important**: Change these credentials after first login!

### Managing Remote Databases

Once connected, you can:

- Execute SQL queries
- Browse and modify table structures
- Import/export data
- Manage indexes and constraints
- View query history
- Monitor performance (if supported by your server)

### Team Collaboration

MylibSQLAdmin provides user management features:

1. **Create Users**: Add team members with specific roles
2. **Manage Permissions**: Control access to databases and operations
3. **Activity Logs**: Track user actions and changes
4. **Session Management**: Monitor active sessions

## Security Best Practices

### Authentication Tokens

1. **Generate Strong Tokens**: Use long, random tokens
2. **Rotate Regularly**: Change tokens periodically
3. **Limit Scope**: Use tokens with minimal required permissions
4. **Secure Storage**: Never commit tokens to version control

### Network Security

1. **Use HTTPS**: Always use encrypted connections in production
2. **IP Restrictions**: Limit database access to known IPs (if supported)
3. **VPN Access**: Consider VPN for additional security
4. **Firewall Rules**: Configure appropriate firewall rules

### Environment Variables

```bash
# Never commit .env files with real credentials
echo "admin/.env" >> .gitignore

# Use environment-specific files
cp admin/.env admin/.env.production
cp admin/.env admin/.env.development
```

## Troubleshooting

### Connection Issues

#### "Connection Failed" Error

1. Verify your database URL:

```bash
# Test with curl (for HTTP endpoints)
curl -H "Authorization: Bearer YOUR_TOKEN" https://your-database.turso.io

# For Turso databases
turso db show your-database
```

2. Check authentication token:

   - Ensure token is valid and not expired
   - Verify token has correct permissions
   - Check for extra spaces or line breaks

3. Network connectivity:
   - Ensure your server can reach the LibSQL endpoint
   - Check firewall rules
   - Verify DNS resolution

#### "Invalid Token" Error

1. Regenerate your token:

```bash
# For Turso
turso db tokens create your-database
```

2. Update configuration:

```bash
# Edit admin/.env
nano admin/.env

# Restart services
docker compose restart
```

### Performance Issues

1. **Check Latency**: Ensure low latency to your LibSQL server
2. **Monitor Connections**: Don't exceed connection limits
3. **Query Optimization**: Use indexes and optimize queries
4. **Caching**: Enable query caching if available

### Viewing Logs

```bash
# Application logs
docker compose logs -f mylibsqladmin-admin

# Web server logs
docker compose logs -f mylibsqladmin-web

# All logs
docker compose logs --tail=100
```

## Migration

### From Local to Remote

To migrate from a local LibSQL instance to a remote one:

1. Export your local database:

```bash
# From local instance
docker exec mylibsqladmin-sqld sqlite3 /var/lib/libsql/mydatabase.db .dump > mydatabase.sql
```

2. Import to remote instance:

```bash
# Using Turso CLI
turso db shell your-database < mydatabase.sql

# Or through MylibSQLAdmin SQL editor
```

3. Update configuration to point to remote instance

### Between Remote Instances

1. Export from source:

   - Use MylibSQLAdmin export feature
   - Or use provider-specific tools

2. Import to destination:
   - Use MylibSQLAdmin import feature
   - Or use provider-specific tools

## Advanced Configuration

### Connection Pooling

MylibSQLAdmin automatically manages connection pooling for optimal performance. No additional configuration is required.

### Read Replicas

When using Turso or compatible services with read replicas:

```env
# Primary for writes
LIBSQL_DB_URL=libsql://primary.turso.io

# Can be configured for read operations
LIBSQL_SYNC_URL=libsql://replica.turso.io
```

### Embedded Replicas

For improved performance, you can use embedded replicas:

```env
# Enable embedded replica mode
LIBSQL_EMBEDDED_REPLICA=true
LIBSQL_SYNC_URL=libsql://your-database.turso.io
```

## Best Practices

1. **Use Environment Files**: Keep credentials in `.env` files, not in code
2. **Regular Backups**: Implement automated backup procedures
3. **Monitor Usage**: Track query performance and connection usage
4. **Update Regularly**: Keep MylibSQLAdmin and dependencies updated
5. **Document Configuration**: Maintain documentation of your setup

## Next Steps

- [Local Instance Guide](LLI.md) - For local development setups
- [Turso Documentation](https://docs.turso.tech) - Learn more about Turso
- [LibSQL Documentation](https://github.com/tursodatabase/libsql) - Understanding libSQL features Update MylibSQLAdmin configuration to point to remote instance

## Best Practices

1. **Connection Management**

   - Use connection pooling
   - Monitor connection health
   - Implement retry logic

2. **Security**

   - Regular token rotation
   - Audit access logs
   - Use least privilege principle

3. **Performance**
   - Cache frequently accessed data
   - Optimize queries
   - Monitor query performance

## Next Steps

- Set up monitoring and alerting
- Configure automated backups
- Implement disaster recovery procedures
- Explore advanced LibSQL features
