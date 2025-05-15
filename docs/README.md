# MyLibSQLAdmin

<p align="center">
  <picture>
    <source media="(prefers-color-scheme: dark)" srcset="https://raw.githubusercontent.com/darkterminal/darkterminal/master/projects/dark-mode.png">
    <source media="(prefers-color-scheme: light)" srcset="https://raw.githubusercontent.com/darkterminal/darkterminal/master/projects/light-mode.png">
    <img alt="MyLibSQLAdmin logo" src="https://raw.githubusercontent.com/darkterminal/darkterminal/master/projects/dark-mode.png">
  </picture>
</p>

<p align="center">A modern web interface for LibSQL and SQLite databases</p>

<p align="center">
  <a href="https://deepwiki.com/darkterminal/mylibsqladmin" target="_blank">
    <img alt="Documentation" src="https://img.shields.io/badge/DeepWiki-Docs?logo=wikibooks&label=Docs">
  </a>
  <a href="https://github.com/sponsors/darkterminal" target="_blank">
    <img alt="GitHub Sponsors" src="https://img.shields.io/github/sponsors/darkterminal?logo=githubsponsors">
  </a>
  <a href="https://discord.gg/wWDzy5Nt44" target="_blank">
    <img alt="Discord" src="https://img.shields.io/discord/1361788238561280101?logo=discord">
  </a>
  <img alt="GitHub commit activity" src="https://img.shields.io/github/commit-activity/w/darkterminal/mylibsqladmin">
  <img alt="GitHub License" src="https://img.shields.io/github/license/darkterminal/mylibsqladmin">
  <img alt="GitHub contributors" src="https://img.shields.io/github/contributors/darkterminal/mylibsqladmin">
</p>

> **Note**: This project is under active development.

## What is MyLibSQLAdmin?

MyLibSQLAdmin is an open-source web GUI designed specifically for managing [LibSQL](https://libsql.org) databases. LibSQL is a powerful fork of SQLite with enhanced features for modern applications, providing serverless and server-based modes, fine-grained access control, and native branching capabilities.

The project offers a comprehensive interface for database administration through a user-friendly web UI, making it easier to manage both local SQLite files and remote LibSQL servers.

## ✨ Key Features

### Database Management

- **Intuitive Database Interface** - Create, browse, and manage databases with ease
- **Performance Analytics** - View real-time database performance statistics
- **Automated Backups** - Create and restore database snapshots

### Data Operations

- **Table Browser** - Navigate through tables, views, and relationships
- **Advanced SQL Editor** - Execute queries with syntax highlighting and formatting
- **Data Import/Export** - Support for SQL, CSV, and JSON formats

### Security & Collaboration

- **Token-Based Authentication** - Generate and manage secure API access tokens
- **Team Management** - Organize users into groups with defined permissions
- **Access Control** - Configure fine-grained permissions for database resources

### Connectivity Options

- **Local LibSQL Instance** - Work with SQLite/LibSQL files on your local system
- **Remote LibSQL Server** - Connect to remote LibSQL server instances

## 🚀 Quick Start

### Option 1: Setup Script (Recommended)

```bash
git clone https://github.com/darkterminal/mylibsqladmin.git
cd mylibsqladmin
./setup
```

Access the web interface at: `http://localhost:8000`

### Option 2: Docker Containers

For Local LibSQL Instance:

```bash
docker pull darkterminal/mylibsqladmin:local
docker run -p 8080:80 -v ./data:/var/lib/libsql darkterminal/mylibsqladmin:local
```

For Remote LibSQL Instance:

```bash
docker pull darkterminal/mylibsqladmin:remote
docker run -p 8080:80 darkterminal/mylibsqladmin:remote
```

Access the web interface at: `http://localhost:8080`

## 📋 System Requirements

- **Server**:
  - PHP 8.1 or higher
  - Node.js 16+ and npm
  - Docker and Docker Compose (for containerized deployment)
  - 2GB RAM minimum (4GB recommended)
  - 500MB+ available disk space
- **Client**:
  - Modern web browser (Chrome, Firefox, Safari, Edge)
  - JavaScript enabled

## 📚 Documentation

Comprehensive documentation is available at [DeepWiki](https://deepwiki.com/darkterminal/mylibsqladmin).

Additional resources:

- [Local LibSQL Instance Guide](LLI.md) - Detailed setup for local database files
- [Remote LibSQL Instance Guide](LRI.md) - Connecting to remote LibSQL servers

## 🤝 Contributing

Contributions make the open-source community an amazing place to learn, inspire, and create. We welcome contributions of all sizes!

1. Fork the repository
2. Create your feature branch: `git checkout -b feature/amazing-feature`
3. Commit your changes: `git commit -m 'Add some amazing feature'`
4. Push to the branch: `git push origin feature/amazing-feature`
5. Open a Pull Request

For more details, see our [Contributing Guidelines](CONTRIBUTING.md).

## 🐛 Reporting Issues

Found a bug or have a feature request? Please open an issue on the [GitHub repository](https://github.com/darkterminal/mylibsqladmin/issues).

## 💬 Community

Join our [Discord community](https://discord.gg/wWDzy5Nt44) for discussions, support, and updates.

## ❤️ Support and Sponsorship

If you find MyLibSQLAdmin valuable, please consider supporting the project:

- [GitHub Sponsors](https://github.com/sponsors/darkterminal) (Global)
- [Saweria](https://saweria.co/darkterminal) (Indonesia)

## 📊 Project Stats

[![Star History Chart](https://api.star-history.com/svg?repos=darkterminal/mylibsqladmin&type=Date)](https://www.star-history.com/#darkterminal/mylibsqladmin&Date)

## 📝 License

MyLibSQLAdmin is open-source software licensed under the [MIT license](LICENSE).
