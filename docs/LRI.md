# LibSQL Remote Instance (LRI) Guide

This guide provides instructions for connecting MylibSQLAdmin to remote LibSQL servers, enabling team collaboration and production deployments.

## Overview

A LibSQL Remote Instance (LRI) configuration allows MylibSQLAdmin to connect to external LibSQL servers. This setup separates the database server from the administration interface, making it suitable for team environments and production deployments.

## Key Benefits

- **Team Collaboration** - Multiple users can access the same databases
- **Scalability** - Connect to dedicated database servers
- **Production Ready** - Suitable for production environments
- **Centralized Management** - Manage multiple remote databases from one interface

## Prerequisites

Before connecting to a remote LibSQL instance, you need:

1. A running LibSQL server (e.g., Turso, self-hosted libSQL server)
2. Connection credentials (URL and authentication token)
3. Network access to the LibSQL server

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
- Choose **No** when asked about using a local LibSQL instance
- Provide your remote LibSQL server details

### Manual Docker Compose Setup

1. Clone and prepare the environment:

```bash
git clone https://github.com/darkterminal/mylibsqladmin.git
cd mylibsqladmin
cp .env.example .env
```

2. Configure for remote instance in `.env`:

```env
LIBSQL_LOCAL_INSTANCE=false
LIBSQL_REMOTE_URL=libsql://your-database.turso.io
LIBSQL_AUTH_TOKEN=your-auth-token
```

3. Start the services:

```bash
# Development
docker compose -f docker-compose.dev.yml up -d

# Production
docker compose -f docker-compose.prod.yml up -d
```

## Configuration

### Required Environment Variables

| Variable                | Description                            | Example                |
| ----------------------- | -------------------------------------- | ---------------------- |
| `LIBSQL_LOCAL_INSTANCE` | Must be set to `false` for remote mode | `false`                |
| `LIBSQL_REMOTE_URL`     | URL of your LibSQL server              | `libsql://db.turso.io` |
| `LIBSQL_AUTH_TOKEN`     | Authentication token for the server    | `eyJ...`               |

### Connection URL Formats

MylibSQLAdmin supports various LibSQL connection formats:

- **Turso**: `libsql://[database]-[organization].turso.io`
- **Self-hosted**: `http://your-server:8080` or `https://your-server:8080`
- **WebSocket**: `wss://[database]-[organization].turso.io`

## Usage

### Accessing the Interface

After installation, access MylibSQLAdmin at:

- Development: `http://localhost:8001`
- Production: `http://localhost:8000`

Default credentials:

- Email: `admin@mylibsqladmin.test`
- Password: `mylibsqladmin`

### Connecting to Your Database

1. Log in to the web interface
2. Navigate to Database Settings
3. Enter your connection details:
   - Database URL
   - Authentication token
4. Click "Connect"

### Managing Remote Databases

Once connected, you can:

- Execute SQL queries
- Browse tables and data
- Manage database schema
- Import/export data
- Configure user permissions

## Working with Turso

If using Turso as your LibSQL provider:

1. Create a database on Turso:

```bash
turso db create my-database
```

2. Get connection details:

```bash
turso db show my-database --url
turso db tokens create my-database
```

3. Use these values in your MylibSQLAdmin configuration

## Security Considerations

### Authentication

- Always use HTTPS connections in production
- Keep authentication tokens secure
- Rotate tokens regularly
- Use environment variables for sensitive data

### Network Security

- Restrict database access to trusted IPs
- Use VPN for additional security
- Enable TLS/SSL for all connections

### Access Control

MylibSQLAdmin provides role-based access control:

- Configure user permissions appropriately
- Limit database access based on roles
- Regular audit user activities

## Troubleshooting

### Connection Failed

1. Verify connection URL format:

```bash
# Test connection with curl
curl -H "Authorization: Bearer YOUR_TOKEN" https://your-database.turso.io
```

2. Check authentication token:

- Ensure token is valid and not expired
- Verify token has necessary permissions

3. Network connectivity:

```bash
# Test network access
ping your-database-host
telnet your-database-host 8080
```

### Authentication Errors

If you see authentication errors:

1. Regenerate your authentication token
2. Update the token in your `.env` file
3. Restart MylibSQLAdmin:

```bash
docker compose restart
```

### Performance Issues

For optimal performance:

1. Ensure low latency to your LibSQL server
2. Use connection pooling (automatically enabled)
3. Consider geographic proximity to your database server

## Advanced Configuration

### Multiple Database Connections

To manage multiple remote databases:

1. Use the web interface to add additional connections
2. Switch between databases using the database selector
3. Each connection maintains its own session

### High Availability

For production deployments:

1. Use LibSQL's built-in replication features
2. Configure read replicas for load distribution
3. Implement proper backup strategies

## Migration Guide

### From Local to Remote

To migrate from a local instance to remote:

1. Export your local database:

```bash
# Export from local instance
docker exec mylibsqladmin-sqld sqld export > backup.sql
```

2. Import to remote LibSQL server:

```bash
# Import to Turso or remote server
turso db shell my-database < backup.sql
```

3. Update MylibSQLAdmin configuration to point to remote instance

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
