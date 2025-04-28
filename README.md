> [!WARNING]
> Still in development process.

<p align="center">
  <picture>
    <source media="(prefers-color-scheme: dark)" srcset="https://raw.githubusercontent.com/darkterminal/darkterminal/master/projects/dark-mode.png">
    <source media="(prefers-color-scheme: light)" srcset="https://raw.githubusercontent.com/darkterminal/darkterminal/master/projects/light-mode.png">
    <img alt="Shows a black logo in light color mode and a white one in dark color mode." src="https://raw.githubusercontent.com/darkterminal/darkterminal/master/projects/dark-mode.png">
  </picture>
</p>
<p align="center">A Modern SQLite Database Management System</p>

<p align="center">
  <a href="https://deepwiki.com/darkterminal/mylibsqladmin" target="_blank">
    <img alt="Static Badge" src="https://img.shields.io/badge/DeepWiki-Docs?logo=wikibooks&label=Docs">
  </a>
  <a href="https://github,com/sponsors/darkterminal" target="_blank">
    <img alt="GitHub Sponsors" src="https://img.shields.io/github/sponsors/darkterminal?logo=githubsponsors">
  </a>
  <a href="https://discord.gg/wWDzy5Nt44" target="_blank">
    <img alt="Discord" src="https://img.shields.io/discord/1361788238561280101?logo=discord">
  </a>
  <img alt="GitHub commit activity" src="https://img.shields.io/github/commit-activity/w/darkterminal/mylibsqladmin">
  <img alt="GitHub License" src="https://img.shields.io/github/license/darkterminal/mylibsqladmin">
  <img alt="GitHub contributors" src="https://img.shields.io/github/contributors/darkterminal/mylibsqladmin">
</p>

<hr />

In the age of data-driven applications, the demand for lightweight, scalable, and modern database solutions has never been greater. **libSQL** is a powerful fork of SQLite designed for the modern era, offering advanced features such as serverless and server-based modes, fine-grained access control, and native branching capabilities.

**MylibSQLAdmin** is an open-source web GUI built specifically for managing libSQL databases. By harnessing the full potential of libSQL-server functionalities, this project provides an intuitive and comprehensive platform for database administration, making it easier than ever to manage your database systems through an accessible web interface. You can connect with `libsql-server` from **Docker Service** or use your existing `libsql-server` instance.

## What Does MylibSQLAdmin Offer?

- **Simple Database Statistics** - Get quick insights into your database performance with easy-to-understand stats.
- **Database Management** - Create, edit, and manage your databases with intuitive tools.
- **Token Management** - Secure and control access to your system using token-based authentication.
- **Group Management** - Organize users into groups for better structure and permission handling.
- **Team Management** - Collaborate efficiently by managing teams and their access levels.
- **User Management** - Easily handle user accounts, roles, and activity logs.
- **Member Invitation Management** - Invite new members and track their onboarding status with ease.

## Sponsors

<img title="Sponsor Here" src="https://i.imgur.com/R1zU0eW.png" width="100%" />

## Support this Project

- via <a href="https://github.com/sponsors/darkterminal">GitHub Sponsor</a> (Global)
- via <a href="https://saweria.co/darkterminal" target="_blank">Saweria</a> (Indonesian)

## Getting Started

For now there is no build images, but you can use clone this repository and use MylibSQLAdmin.

1. Clone MylibSQLAdmin

```bash
git clone git@github.com:darkterminal/mylibsqladmin.git
./setup
```

or

```bash
git clone https://github.com/darkterminal/mylibsqladmin.git
./setup
```

2. Access Web GUI of MylibSQLAdmin Platform

```
http://localhost:8000
```

## Using Existing libsql-server Instance

By default MylibSQLServer will use `libsql-server` from **Docker Service**. But if you want to try with your existing `libsql-server` instance you can do it too.

1. Clone MylibSQLAdmin

```bash
git clone git@github.com:darkterminal/mylibsqladmin.git
```

or

```bash
git clone https://github.com/darkterminal/mylibsqladmin.git
```

2. Setting Environment Variable File

```bash
cp .env.example .env
cp admin/.env.example admin/.env
```

3. adjust the settings

```bash
LIBSQL_LOCAL_INSTANCE=false

LIBSQL_HOST=<your-existing-libsql-server-host>
LIBSQL_PORT=<your-existing-libsql-server-port>

LIBSQL_API_HOST=<your-existing-libsql-server-admin-api-host>
LIBSQL_API_PORT=<your-existing-libsql-server-admin-api-port>
# Optional (you can leave username and password with empty value)
LIBSQL_API_USERNAME=<your-existing-libsql-server-admin-api-username>
LIBSQL_API_PASSWORD=<your-existing-libsql-server-admin-api-password>
```

4. Running MylibSQLAdmin Platform

```bash
cd admin
php artisan key:generate
composer install
npm install

cd ..

make compose-dev/up
```

5. Access Web GUI of MylibSQLAdmin Platform

```
http://localhost:8000
```

## Contributors

![Contributors](https://contrib.nn.ci/api?no_bot=true&repo=darkterminal/mylibsqladmin)

## Star History

[![Star History Chart](https://api.star-history.com/svg?repos=darkterminal/mylibsqladmin&type=Date)](https://www.star-history.com/#darkterminal/mylibsqladmin&Date)
