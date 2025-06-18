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

<p align="center">
    <a href="https://www.youtube.com/watch?v=dntNiEYA4mU" target="_blank">
        <img width="100%" src="https://github.com/user-attachments/assets/bfc06ec7-6265-481b-a493-ce5a0d440b05" />
    </a>
    <a href="https://www.youtube.com/watch?v=dntNiEYA4mU" target="_blank">View on YouTube</a>
</p>

## What Does MylibSQLAdmin Offer?

-   **Simple Database Statistics** - Get quick insights into your database performance with easy-to-understand stats.
-   **Database Management** - Create, edit, and manage your databases with intuitive tools.
-   **Token Management** - Secure and control access to your system using token-based authentication.
-   **Group Management** - Organize users into groups for better structure and permission handling.
-   **Team Management** - Collaborate efficiently by managing teams and their access levels.
-   **User Management** - Easily handle user accounts, roles, and activity logs.
-   **Member Invitation Management** - Invite new members and track their onboarding status with ease.

## Sponsors

<img title="Sponsor Here" src="https://i.imgur.com/R1zU0eW.png" width="100%" />

## Support this Project

-   via <a href="https://github.com/sponsors/darkterminal">GitHub Sponsor</a> (Global)
-   via <a href="https://saweria.co/darkterminal" target="_blank">Saweria</a> (Indonesian)

## Requirements

-   Docker & Docker Compose
-   openssl

## Getting Started

Create directory to save the `compose.yml` file in your desire location.

### LRI (libSQL Remote Instance)

Install MylibSQLAdmin Platform with your existing `libsql-server` instance

```yml
services:
    webui:
        container_name: mylibsqladmin-webui
        image: ghcr.io/darkterminal/mylibsqladmin-web:latest
        ports:
            - 8000:8000
        network_mode: host
        restart: unless-stopped
        environment:
            - APP_TIMEZONE=Asia/Jakarta
            - APP_KEY=base64:/BzpAudtYyAGmuxBNn0PISYvyj0ntEEtoF4MlRPpVbw=
            - DB_CONNECTION=libsql
            - SESSION_DRIVER=file
            - APP_NAME=MyLibSQLAdmin
            - REGISTRATION_ENABLED=false
            - LIBSQL_LOCAL_INSTANCE=false
            - LIBSQL_HOST=your-libsql-server-address
            - LIBSQL_PORT=your-libsql-server-port
            - LIBSQL_API_HOST=your-libsql-server-admin-address
            - LIBSQL_API_PORT=your-libsql-server-admin-port
            - LIBSQL_API_USERNAME=
            - LIBSQL_API_PASSWORD=
```

### LLI (libSQL Local Instance)

Install MyLibSQLAdmin Platform with libsql-server

```yml
services:
    webui:
        container_name: mylibsqladmin-webui-prod
        image: ghcr.io/darkterminal/mylibsqladmin-web:latest
        ports:
            - 8000:8000
        networks:
            - mylibsqladmin-network
        restart: unless-stopped
        environment:
            - APP_TIMEZONE=Asia/Jakarta
            - APP_KEY=base64:P7ExlKQb1odguP9Sv713EPxM/qkcbCkXuIXaAp24uMo=
            - DB_CONNECTION=libsql
            - SESSION_DRIVER=file
            - APP_NAME=MyLibSQLAdmin
            - REGISTRATION_ENABLED=false
            - LIBSQL_LOCAL_INSTANCE=true
            - LIBSQL_HOST=proxy
            - LIBSQL_PORT=8080
            - LIBSQL_API_HOST=proxy
            - LIBSQL_API_PORT=8081
            - LIBSQL_API_USERNAME=
            - LIBSQL_API_PASSWORD=
        depends_on:
            - db

    proxy:
        container_name: mylibsqladmin-proxy
        image: ghcr.io/darkterminal/mylibsqladmin-proxy:latest
        environment:
            - APP_ENV=production
        ports:
            - 8080:8080
            - 5001:5001
            - 8081:8081
        networks:
            - mylibsqladmin-network
        restart: unless-stopped
        depends_on:
            - webui
            - db

    db:
        container_name: mylibsqladmin-db
        image: ghcr.io/tursodatabase/libsql-server:latest
        entrypoint: [/bin/sqld]
        command:
            - --http-listen-addr
            - 0.0.0.0:8080
            - --grpc-listen-addr
            - 0.0.0.0:5001
            - --admin-listen-addr
            - 0.0.0.0:8081
            - --enable-namespaces
            - --no-welcome
        user: 1000:1000
        volumes:
            - ./libsql-data:/var/lib/sqld
        restart: unless-stopped
        networks:
            - mylibsqladmin-network

networks:
    mylibsqladmin-network:
        driver: bridge
        name: mylibsqladmin-network
```

### Run it Up!

```bash
docker compose up -d
```

## Contributors

![Contributors](https://contrib.nn.ci/api?no_bot=true&repo=darkterminal/mylibsqladmin)

## Star History

[![Star History Chart](https://api.star-history.com/svg?repos=darkterminal/mylibsqladmin&type=Date)](https://www.star-history.com/#darkterminal/mylibsqladmin&Date)
