# MylibSQLAdmin Installation Guides

Thank you for choosing MylibSQLAdmin as your `libsql-server` Database Management Platform. In this document you will be guided how to install and configure MylibSQLAdmin in your server.

## Support this Project

<p align="center">
    <a href="mailto:darkterminal@duck.com" target="_blank"><img title="Sponsor Here" src="https://i.imgur.com/R1zU0eW.png" width="100%" /></a>
    via <a href="https://github.com/sponsors/darkterminal">GitHub Sponsor</a> (Global) / via <a href="https://saweria.co/darkterminal" target="_blank">Saweria</a> (Indonesian)
</p>

---

## Requirements

-   Docker & Docker Compose
-   openssl

### Install using Installer Script (Recommended)

Make sure the installer script is running inside your prefered directory!

```
curl --proto '=https' --tlsv1.2 -LsSf https://raw.githubusercontent.com/darkterminal/mylibsqladmin/refs/heads/main/scripts/installer.sh | sh
```

### Manual Installation

Create directory to save the `compose.yml` file in your desire location.

#### LRI (libSQL Remote Instance)

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

#### LLI (libSQL Local Instance)

Install MyLibSQLAdmin Platform with `libsql-server` instance

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

#### Run it Up!

```bash
docker compose up -d
```

### Default Credentials

-   Super Admin = username: `superadmin` password: `superadmin12345`
-   Manager = username: `manager` password: `manager12345`
-   Database Maintainer = username: `database-maintainer` password: `database-maintainer12345`
-   Member = username: `member` password: `member`
