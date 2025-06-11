<p align="center">
  <strong>A modern web interface for managing LibSQL databases</strong>
</p>

<p align="center">
  <picture>
    <source media="(prefers-color-scheme: dark)" srcset="https://raw.githubusercontent.com/darkterminal/darkterminal/master/projects/dark-mode.png">
    <source media="(prefers-color-scheme: light)" srcset="https://raw.githubusercontent.com/darkterminal/darkterminal/master/projects/light-mode.png">
    <img alt="Shows a black logo in light color mode and a white one in dark color mode." src="https://raw.githubusercontent.com/darkterminal/darkterminal/master/projects/dark-mode.png">
  </picture>
</p>

<p align="center">
  <a href="https://deepwiki.com/darkterminal/mylibsqladmin">Documentation</a> •
  <a href="https://discord.gg/wWDzy5Nt44">Discord</a> •
  <a href="https://github.com/sponsors/darkterminal">Sponsor</a>
</p>

<p align="center">
  <img alt="License" src="https://img.shields.io/github/license/darkterminal/mylibsqladmin">
  <img alt="GitHub stars" src="https://img.shields.io/github/stars/darkterminal/mylibsqladmin">
</p>

## What is MylibSQLAdmin?

MylibSQLAdmin is an open-source web-based database administration tool built specifically for [LibSQL](https://github.com/tursodatabase/libsql) databases. It provides an intuitive interface for managing SQLite-compatible databases, whether running locally in Docker or connecting to remote LibSQL servers like [Turso](https://turso.tech).

## ✨ Features

- **🗄️ Database Management** - Create, browse, and manage databases through an intuitive web interface
- **📝 SQL Editor** - Execute queries with syntax highlighting, auto-completion, and query history
- **👥 User Management** - Role-based access control for team collaboration
- **🔌 Flexible Deployment** - Support for both local SQLite files and remote LibSQL servers
- **📊 Data Operations** - Import/export data in multiple formats (SQL, CSV, JSON)
- **🔐 Security First** - Token-based authentication and secure connections
- **🐳 Docker Native** - Easy deployment with Docker Compose

## 🚀 Quick Start

### Prerequisites

- Docker and Docker Compose
- Git

### Installation (< 5 minutes)

```bash
# Clone the repository
git clone https://github.com/darkterminal/mylibsqladmin.git
cd mylibsqladmin

# Run the interactive installer
./install.sh
```

The installer will guide you through:

1. Choosing your environment (development/production)
2. Selecting local or remote LibSQL instance
3. Configuring connection details

Once complete, access MylibSQLAdmin at:

- **Development**: http://localhost:8001
- **Production**: http://localhost:8000

### Default Login

- **Email**: `admin@mylibsqladmin.test`
- **Password**: `mylibsqladmin`

> ⚠️ **Security**: Change these credentials immediately after first login!

## 📋 Configuration Options

### Local Instance Mode

Perfect for development and single-user scenarios:

```env
# .env
LIBSQL_LOCAL_INSTANCE=true
```

This mode:

- Runs LibSQL server inside Docker
- Stores databases in Docker volumes
- No external dependencies
- Works offline

[📖 Detailed Local Instance Guide](LLI.md)

### Remote Instance Mode

For production and team environments:

```env
# .env
LIBSQL_LOCAL_INSTANCE=false

# admin/.env
LIBSQL_DB_URL=libsql://your-database.turso.io
LIBSQL_AUTH_TOKEN=your-auth-token
```

Connect to:

- [Turso](https://turso.tech) managed databases
- Self-hosted LibSQL servers
- Any LibSQL-compatible endpoint

[📖 Detailed Remote Instance Guide](LRI.md)

## 🐳 Docker Commands

### Using Make

```bash
# Development
make compose-dev/up      # Start services
make compose-dev/down    # Stop services

# Production
make compose-prod/up     # Start services
make compose-prod/down   # Stop services
```

### Using Docker Compose Directly

```bash
# Development
docker compose -f docker-compose.dev.yml up -d
docker compose -f docker-compose.dev.yml logs -f

# Production
docker compose -f docker-compose.prod.yml up -d
docker compose -f docker-compose.prod.yml logs -f
```

## 🏗️ Architecture

MylibSQLAdmin is built with:

- **Laravel** - PHP web application framework
- **Alpine.js** - Lightweight JavaScript framework
- **LibSQL** - SQLite-compatible database engine
- **Docker** - Containerized deployment

## 📚 Documentation

- [Getting Started](https://deepwiki.com/darkterminal/mylibsqladmin)
- [Local Instance Guide](LLI.md) - Using with local SQLite files
- [Remote Instance Guide](LRI.md) - Connecting to remote servers

## 🤝 Contributing

We welcome contributions! Here's how to get started:

1. Fork the repository
2. Create a feature branch: `git checkout -b feature/amazing-feature`
3. Make your changes
4. Run tests: `cd admin && php artisan test`
5. Commit: `git commit -m 'Add amazing feature'`
6. Push: `git push origin feature/amazing-feature`
7. Open a Pull Request

See [CONTRIBUTING.md](https://github.com/darkterminal/mylibsqladmin/blob/main/CONTRIBUTING.md) for detailed guidelines.

## 🐛 Support

### Getting Help

- 💬 [Discord Community](https://discord.gg/wWDzy5Nt44) - Real-time chat
- 🐛 [GitHub Issues](https://github.com/darkterminal/mylibsqladmin/issues) - Bug reports
- 📖 [Documentation](https://deepwiki.com/darkterminal/mylibsqladmin) - Guides and tutorials

### Reporting Issues

When reporting issues, please include:

- MylibSQLAdmin version
- Docker version
- Steps to reproduce
- Error messages/logs
- Environment (OS, browser)

## ❤️ Support the Project

If MylibSQLAdmin helps your workflow, consider supporting development:

- ⭐ Star this repository
- 🐛 Report bugs and suggest features
- 🤝 Submit pull requests
- 💰 [GitHub Sponsors](https://github.com/sponsors/darkterminal)
- ☕ [Saweria](https://saweria.co/darkterminal) (Indonesia)

## 📊 Project Stats

![Star History Chart](https://api.star-history.com/svg?repos=darkterminal/mylibsqladmin&type=Date)

## 📄 License

MylibSQLAdmin is open source software licensed under the [Apache License 2.0](LICENSE).

## 🙏 Acknowledgments

- [LibSQL](https://github.com/tursodatabase/libsql) - The amazing SQLite fork
- [Turso](https://turso.tech) - LibSQL cloud platform
- [Laravel](https://laravel.com) - PHP framework
- [Alpine.js](https://alpinejs.dev) - Lightweight JavaScript framework
- All our [contributors](https://github.com/darkterminal/mylibsqladmin/graphs/contributors)

---

<p align="center">
  Made with ❤️ by <a href="https://github.com/darkterminal">darkterminal</a> and contributors
</p>
