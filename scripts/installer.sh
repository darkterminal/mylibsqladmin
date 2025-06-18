#!/bin/bash
set -euo pipefail

# Function to prompt user with default
prompt() {
    local message="$1"
    local default="$2"
    read -p "$message [$default]: " input
    echo "${input:-$default}"
}

# Check if Docker is installed
if ! command -v docker &>/dev/null; then
    echo "❌ Docker is not installed. Please install Docker first."
    if [[ "$(uname)" == "Darwin" ]]; then
        echo "You can install Docker using Homebrew with the following command:"
        echo "brew cask install docker"
    elif [[ "$(uname)" == "Linux" ]]; then
        echo "You can install Docker using the following command:"
        echo "curl -fsSL https://get.docker.com | sh"
    fi
    exit 1
fi

# Check if Docker is running
if ! docker info &>/dev/null; then
    echo "❌ Docker is not running. Please start Docker first."
    exit 1
fi

# Check if Docker Compose file already exists
if [[ -f "compose.yml" || -f "docker-compose.yml" ]]; then
    echo "❌ Docker Compose file already exists. Please remove it first."
    exit 1
fi

# Check if openssl is installed
if ! command -v openssl &>/dev/null; then
    echo "❌ openssl is not installed. Please install openssl first."
    if [[ "$(uname)" == "Darwin" ]]; then
        echo "You can install openssl using Homebrew with the following command:"
        echo "brew install openssl"
    elif [[ "$(uname)" == "Linux" ]]; then
        echo "You can install openssl using the following command:"
        echo "sudo apt-get install openssl"
    fi
    exit 1
fi

# Collect user input
APP_KEY="base64:$(openssl rand -base64 32 | tr -d '\n')"
APP_TIMEZONE=$(prompt "Enter timezone" "Asia/Jakarta")
APP_NAME=$(prompt "Enter application name" "MyLibSQLAdmin")
LIBSQL_LOCAL_INSTANCE=$(prompt "Use local LibSQL instance? (true/false)" "true")

# Defaults for local instance
if [[ "$LIBSQL_LOCAL_INSTANCE" == "true" ]]; then
    LIBSQL_HOST="proxy"
    LIBSQL_PORT="8080"
    LIBSQL_API_HOST="proxy"
    LIBSQL_API_PORT="8081"
    LIBSQL_API_USERNAME=""
    LIBSQL_API_PASSWORD=""
else
    read -p "Enter LibSQL host: " LIBSQL_HOST
    read -p "Enter LibSQL port [8080]: " input_port
    LIBSQL_PORT="${input_port:-8080}"
    read -p "Enter LibSQL API host: " LIBSQL_API_HOST
    read -p "Enter LibSQL API port [8081]: " input_api_port
    LIBSQL_API_PORT="${input_api_port:-8081}"
    read -p "Enter LibSQL API username (optional): " LIBSQL_API_USERNAME
    read -s -p "Enter LibSQL API password (optional): " LIBSQL_API_PASSWORD
    echo
fi

# Compose templates
COMPOSE_TEMPLATE_WEBUI_LOCAL_INSTANCE=$(
    echo "services:
  webui:
    container_name: mylibsqladmin-webui-prod
    image: ghcr.io/darkterminal/mylibsqladmin-web:latest
    ports:
      - "8000:8000"
    networks:
      - mylibsqladmin-network
    restart: unless-stopped
    environment:
      - APP_TIMEZONE=$APP_TIMEZONE
      - APP_KEY=$APP_KEY
      - DB_CONNECTION=libsql
      - SESSION_DRIVER=file
      - APP_NAME=$APP_NAME
      - REGISTRATION_ENABLED=false
      - LIBSQL_LOCAL_INSTANCE=$LIBSQL_LOCAL_INSTANCE
      - LIBSQL_HOST=$LIBSQL_HOST
      - LIBSQL_PORT=$LIBSQL_PORT
      - LIBSQL_API_HOST=$LIBSQL_API_HOST
      - LIBSQL_API_PORT=$LIBSQL_API_PORT
      - LIBSQL_API_USERNAME=$LIBSQL_API_USERNAME
      - LIBSQL_API_PASSWORD=$LIBSQL_API_PASSWORD
    depends_on:
      - db
"
)

COMPOSE_TEMPLATE_WEBUI_REMOTE_INSTANCE=$(
    echo "services:
  webui:
    container_name: mylibsqladmin-webui
    image: ghcr.io/darkterminal/mylibsqladmin-web:latest
    ports:
      - "8000:8000"
    network_mode: host
    restart: unless-stopped
    environment:
      - APP_TIMEZONE=$APP_TIMEZONE
      - APP_KEY=$APP_KEY
      - DB_CONNECTION=libsql
      - SESSION_DRIVER=file
      - APP_NAME=$APP_NAME
      - REGISTRATION_ENABLED=false
      - LIBSQL_LOCAL_INSTANCE=false
      - LIBSQL_HOST=$LIBSQL_HOST
      - LIBSQL_PORT=$LIBSQL_PORT
      - LIBSQL_API_HOST=$LIBSQL_API_HOST
      - LIBSQL_API_PORT=$LIBSQL_API_PORT
      - LIBSQL_API_USERNAME=$LIBSQL_API_USERNAME
      - LIBSQL_API_PASSWORD=$LIBSQL_API_PASSWORD
"
)

COMPOSE_TEMPLATE_PROXY=$(
    echo "
  proxy:
    container_name: mylibsqladmin-proxy
    image: ghcr.io/darkterminal/mylibsqladmin-proxy:latest
    environment:
      - APP_ENV=production
    ports:
      - "8080:8080"
      - "5001:5001"
      - "8081:8081"
    networks:
      - mylibsqladmin-network
    restart: unless-stopped
    depends_on:
      - webui
      - db
"
)

COMPOSE_TEMPLATE_DB=$(
    echo "
  db:
    container_name: mylibsqladmin-db
    image: ghcr.io/tursodatabase/libsql-server:latest
    entrypoint: ["/bin/sqld"]
    command:
      - "--http-listen-addr"
      - "0.0.0.0:8080"
      - "--grpc-listen-addr"
      - "0.0.0.0:5001"
      - "--admin-listen-addr"
      - "0.0.0.0:8081"
      - "--enable-namespaces"
      - "--no-welcome"
    user: "1000:1000"
    volumes:
      - ./libsql-data:/var/lib/sqld
    restart: unless-stopped
    networks:
      - mylibsqladmin-network
"
)

COMPOSE_TEMPLATE_NETWORK=$(
    echo "
networks:
  mylibsqladmin-network:
    driver: bridge
    name: mylibsqladmin-network
"
)

# Write to compose.yml
if [[ "$LIBSQL_LOCAL_INSTANCE" == "true" ]]; then
    {
        echo "$COMPOSE_TEMPLATE_WEBUI_LOCAL_INSTANCE"
        echo "$COMPOSE_TEMPLATE_PROXY"
        echo "$COMPOSE_TEMPLATE_DB"
        echo "$COMPOSE_TEMPLATE_NETWORK"
    } >compose.yml
else
    {
        echo "$COMPOSE_TEMPLATE_WEBUI_REMOTE_INSTANCE"
    } >compose.yml
fi

echo -e "\n✅ Docker Compose file created at: compose.yml"
echo "👉 Run it with: docker compose -f compose.yml up -d"

# Ask to run
read -p "Run Docker Compose now? (y/n) " -n 1 -r
echo
if [[ $REPLY =~ ^[Yy]$ ]]; then
    docker compose -f compose.yml up -d
fi

# Ask to self-delete script
read -p "Delete this script? (y/n) " -n 1 -r
echo
if [[ $REPLY =~ ^[Yy]$ ]]; then
    rm -rf "$0"
fi
