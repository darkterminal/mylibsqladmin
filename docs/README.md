# MyLibSQLAdmin

<p align="center">
  <picture>
    <source media="(prefers-color-scheme: dark)" srcset="https://raw.githubusercontent.com/darkterminal/darkterminal/master/projects/dark-mode.png">
    <source media="(prefers-color-scheme: light)" srcset="https://raw.githubusercontent.com/darkterminal/darkterminal/master/projects/light-mode.png">
    <img alt="MylibSQLAdmin logo" src="https://raw.githubusercontent.com/darkterminal/darkterminal/master/projects/dark-mode.png">
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

> [!NOTE]  
> This project is under active development.

## What is MylibSQLAdmin?

MylibSQLAdmin is an open-source web-based administration interface specifically designed for [libSQL](https://github.com/tursodatabase/libsql) databases. libSQL is a powerful fork of SQLite designed for modern applications, offering advanced features such as serverless and server-based modes, fine-grained access control, and native branching capabilities.

By harnessing the full potential of libSQL-server functionalities, MylibSQLAdmin provides an intuitive and comprehensive platform for database administration, making it easier than ever to manage your database systems through an accessible web interface.

## ✨ Key Features

### Database Management

- **Intuitive Database Interface** - Create, browse, and manage databases with ease
- **Performance Analytics** - View real-time database performance statistics with easy-to-understand metrics
- **Powerful SQL Editor** - Execute queries with syntax highlighting, formatting, and result visualization

### User & Access Management

- **Token Management** - Secure and control access to your system using token-based authentication
- **Group Management** - Organize users into groups for better structure and permission handling
- **Team Management** - Collaborate efficiently by managing teams and their access levels
- **User Management** - Easily handle user accounts, roles, and activity logs
- **Member Invitation Management** - Invite new members and track their onboarding status

### Connectivity Options

MylibSQLAdmin offers two primary deployment modes:

- **Local Instance (LLI)** - Connect to SQLite/libSQL database files on your system
- **Remote Instance (LRI)** - Connect to remote libSQL servers for collaborative database management

## 🚀 Quick Start

### Option 1: Using the Installation Script (Recommended)

The easiest way to get started is by using our installation script, which handles all dependencies and configuration:

```bash
# Clone the repository
git clone https://github.com/darkterminal/mylibsqladmin.git

# Navigate to the project directory
cd mylibsqladmin

# Run the installation script
./install.sh
```

The script will guide you through the configuration process and start the necessary services. Once complete, access the web interface at: `http://localhost:8000`

### Option 2: Manual Setup

If you prefer a manual setup or need more customization:

1. Clone the repository:

   ```bash
   git clone https://github.com/darkterminal/mylibsqladmin.git
   cd mylibsqladmin
   ```

2. Configure environment variables:

   ```bash
   cp .env.example .env
   cp admin/.env.example admin/.env
   ```

3. Edit the `.env` file to match your preferences:

   For local instance:
   ```
   LIBSQL_LOCAL_INSTANCE=true
   ```

   For remote instance:
   ```
   LIBSQL_LOCAL_INSTANCE=false
   LIBSQL_HOST=<your-libsql-server-host>
   LIBSQL_PORT=<your-libsql-server-port>
   LIBSQL_API_HOST=<your-libsql-server-admin-api-host>
   LIBSQL_API_PORT=<your-libsql-server-admin-api-port>
   ```

4. Install dependencies and generate application key:

   ```bash
   cd admin
   php artisan key:generate
   composer install
   npm install
   cd ..
   ```

5. Start the application:
   ```bash
   make compose-dev/up   # For development
   # or
   make compose-prod/up  # For production
   ```

Access the web interface at: `http://localhost:8000`

## 📋 System Requirements

### Server Requirements

- **PHP 8.1+**
- **Composer**
- **Node.js 16+** and npm
- **Docker** and Docker Compose (for containerized deployment)
- **Git**

### Hardware Requirements

- **CPU**: Any modern x86/64 or ARM processor
- **RAM**: 2GB minimum (4GB recommended)
- **Disk**: 500MB+ available space (plus storage for your databases)

### Client Requirements

- Modern web browser (Chrome, Firefox, Safari, Edge)
- JavaScript enabled

## 📚 Documentation

For detailed information on installation, configuration, and usage, please refer to our documentation:

- [Getting Started Guide](https://deepwiki.com/darkterminal/mylibsqladmin)
- [Local LibSQL Instance Guide (LLI)](LLI.md)
- [Remote LibSQL Instance Guide (LRI)](LRI.md)
- [Developer Documentation](https://deepwiki.com/darkterminal/mylibsqladmin/development)

## 🔄 Deployment Options

MylibSQLAdmin supports multiple deployment options:

### Docker Compose (Development)

```bash
make compose-dev/up
```

### Docker Compose (Production)

```bash
make compose-prod/up
```

### Manual Deployment

For environments without Docker, please refer to our [Manual Deployment Guide](https://deepwiki.com/darkterminal/mylibsqladmin/deployment/manual).

## 🤝 Contributing

We welcome contributions of all kinds! Here's how you can help:

1. Fork the repository
2. Create your feature branch: `git checkout -b feature/amazing-feature`
3. Commit your changes: `git commit -m 'Add some amazing feature'`
4. Push to the branch: `git push origin feature/amazing-feature`
5. Open a Pull Request

Please read our [Contributing Guidelines](CONTRIBUTING.md) for more details.

## 🐛 Reporting Issues

Found a bug or have a feature request? Please open an issue on the [GitHub repository](https://github.com/darkterminal/mylibsqladmin/issues).

## 💬 Community

Join our [Discord community](https://discord.gg/wWDzy5Nt44) for discussions, support, and updates.

## ❤️ Support and Sponsorship

If you find MylibSQLAdmin useful, please consider supporting the project:

- [GitHub Sponsors](https://github.com/sponsors/darkterminal) (Global)
- [Saweria](https://saweria.co/darkterminal) (Indonesia)

## 📊 Project Stats

[![Star History Chart](https://api.star-history.com/svg?repos=darkterminal/mylibsqladmin&type=Date)](https://www.star-history.com/#darkterminal/mylibsqladmin&Date)

## 📝 License

MylibSQLAdmin is open-source software licensed under the [Apache-2.0 License](LICENSE).
