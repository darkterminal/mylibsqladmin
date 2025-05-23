# MylibSQLAdmin

<p align="center">
  <picture>
    <source media="(prefers-color-scheme: dark)" srcset="https://raw.githubusercontent.com/darkterminal/darkterminal/master/projects/dark-mode.png">
    <source media="(prefers-color-scheme: light)" srcset="https://raw.githubusercontent.com/darkterminal/darkterminal/master/projects/light-mode.png">
    <img alt="Shows a black logo in light color mode and a white one in dark color mode." src="https://raw.githubusercontent.com/darkterminal/darkterminal/master/projects/dark-mode.png">
  </picture>
</p>

<p align="center">
  <strong>A modern web interface for managing LibSQL databases</strong>
</p>

<p align="center">
  <a href="https://deepwiki.com/darkterminal/mylibsqladmin">Documentation</a> •
  <a href="https://discord.gg/wWDzy5Nt44">Discord</a> •
  <a href="https://github.com/sponsors/darkterminal">Sponsor</a>
</p>

<p align="center">
  <img alt="License" src="https://img.shields.io/github/license/darkterminal/mylibsqladmin">
  <img alt="GitHub stars" src="https://img.shields.io/github/stars/darkterminal/mylibsqladmin">
  <img alt="Docker Pulls" src="https://img.shields.io/docker/pulls/darkterminal/mylibsqladmin">
</p>

## What is MylibSQLAdmin?

MylibSQLAdmin is a web-based database management tool designed specifically for [LibSQL](https://github.com/tursodatabase/libsql) databases. It provides an intuitive interface for managing both local SQLite-compatible databases and remote LibSQL servers, making database administration accessible through your web browser.

## ✨ Features

- **🗄️ Database Management** - Create, browse, and manage databases with an intuitive interface
- **📝 SQL Editor** - Execute queries with syntax highlighting and auto-completion
- **👥 User Management** - Control access with role-based permissions
- **🔌 Flexible Connectivity** - Support for both local and remote LibSQL instances
- **📊 Data Visualization** - View and export query results in multiple formats
- **🔐 Secure by Default** - Built-in authentication and authorization
- **🐳 Docker Ready** - Easy deployment with Docker and Docker Compose

## 🚀 Quick Start

### Prerequisites

- Docker and Docker Compose installed
- Git (for cloning the repository)

### Installation

The easiest way to get started is using the installation script:

```bash
# Clone the repository
git clone https://github.com/darkterminal/mylibsqladmin.git
cd mylibsqladmin

# Run the installation script
./install.sh
```

The script will guide you through:

1. Choosing between development or production environment
2. Selecting local or remote LibSQL instance
3. Configuring connection details (for remote instances)

Once complete, access MylibSQLAdmin at:

- Development: `http://localhost:8001`
- Production: `http://localhost:8000`

### Default Credentials

- **Email**: `admin@mylibsqladmin.test`
- **Password**: `mylibsqladmin`

> **Important**: Change the default credentials after your first login!

## 📋 Configuration

### Local Instance Mode

For managing SQLite databases on your local system:

```env
LIBSQL_LOCAL_INSTANCE=true
```

This mode:

- Starts an embedded LibSQL server
- Stores databases in Docker volumes
- No external dependencies required

[📖 Full Local Instance Guide](LLI.md)

### Remote Instance Mode

For connecting to external LibSQL servers (Turso, self-hosted):

```env
LIBSQL_LOCAL_INSTANCE=false
LIBSQL_REMOTE_URL=libsql://your-database.turso.io
LIBSQL_AUTH_TOKEN=your-auth-token
```

This mode:

- Connects to existing LibSQL servers
- Supports team collaboration
- Ideal for production use

[📖 Full Remote Instance Guide](LRI.md)

## 🐳 Docker Deployment

### Development Environment

```bash
# Start services
docker compose -f docker-compose.dev.yml up -d

# View logs
docker compose -f docker-compose.dev.yml logs -f

# Stop services
docker compose -f docker-compose.dev.yml down
```

### Production Environment

```bash
# Start services
docker compose -f docker-compose.prod.yml up -d

# With custom configuration
docker compose -f docker-compose.prod.yml --env-file .env.production up -d
```

### Using Make Commands

The project includes a Makefile for common operations:

```bash
make compose-dev/up     # Start development environment
make compose-prod/up    # Start production environment
make compose-dev/down   # Stop development environment
make compose-prod/down  # Stop production environment
```

## 🏗️ Architecture

MylibSQLAdmin consists of:

- **Web Interface**: Modern, responsive UI built with Laravel and Alpine.js
- **API Layer**: RESTful API for database operations
- **LibSQL Integration**: Native support for LibSQL features
- **Authentication**: Built-in user management and access control

## 📚 Documentation

- [Getting Started](https://deepwiki.com/darkterminal/mylibsqladmin)
- [Local Instance Guide](LLI.md)
- [Remote Instance Guide](LRI.md)
- [API Documentation](https://deepwiki.com/darkterminal/mylibsqladmin/api)
- [Troubleshooting](https://deepwiki.com/darkterminal/mylibsqladmin/troubleshooting)

## 🤝 Contributing

We welcome contributions! Please see our [Contributing Guide](CONTRIBUTING.md) for details.

### Development Setup

```bash
# Clone the repository
git clone https://github.com/darkterminal/mylibsqladmin.git
cd mylibsqladmin

# Install dependencies
cd admin
composer install
npm install

# Run tests
php artisan test
```

## 🐛 Reporting Issues

Found a bug? Please [open an issue](https://github.com/darkterminal/mylibsqladmin/issues) with:

- Clear description of the problem
- Steps to reproduce
- Expected vs actual behavior
- Environment details (OS, Docker version, etc.)

## 💬 Community

- [Discord Server](https://discord.gg/wWDzy5Nt44) - Get help and discuss features
- [GitHub Discussions](https://github.com/darkterminal/mylibsqladmin/discussions) - Share ideas and feedback

## ❤️ Support the Project

If you find MylibSQLAdmin useful, consider supporting its development:

- ⭐ Star the repository
- 🐛 Report bugs and request features
- 🤝 Contribute code or documentation
- 💰 [Sponsor on GitHub](https://github.com/sponsors/darkterminal)
- ☕ [Buy me a coffee](https://saweria.co/darkterminal) (Indonesia)

## 📄 License

MylibSQLAdmin is open-source software licensed under the [Apache License 2.0](LICENSE).

## 🙏 Acknowledgments

- [LibSQL](https://github.com/tursodatabase/libsql) team for the amazing database
- [Laravel](https://laravel.com) for the web framework
- All contributors and supporters of this project

---

<p align="center">
  Made with ❤️ by <a href="https://github.com/darkterminal">darkterminal</a> and contributors
</p>
