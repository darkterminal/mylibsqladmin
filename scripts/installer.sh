#!/bin/sh
set -eu

# Function to prompt user with default (POSIX-compliant)
prompt() {
  message="$1"
  default="$2"
  printf "%s [%s]: " "$message" "$default"
  read input
  if [ -z "$input" ]; then
    echo "$default"
  else
    echo "$input"
  fi
}

# Check if Docker is installed
if ! command -v docker >/dev/null 2>&1; then
  echo "❌ Docker is not installed. Please install Docker first."
  if [ "$(uname)" = "Darwin" ]; then
    echo "You can install Docker using Homebrew with the following command:"
    echo "brew install --cask docker"
  elif [ "$(uname)" = "Linux" ]; then
    echo "You can install Docker using the following command:"
    echo "curl -fsSL https://get.docker.com | sh"
  fi
  exit 1
fi

# Check if Docker is running
if ! docker info >/dev/null 2>&1; then
  echo "❌ Docker is not running. Please start Docker first."
  exit 1
fi

# Check if Docker Compose file already exists
if [ -f "compose.yml" ] || [ -f "docker-compose.yml" ]; then
  echo "❌ Docker Compose file already exists. Please remove it first."
  exit 1
fi

# Check if openssl is installed
if ! command -v openssl >/dev/null 2>&1; then
  echo "❌ openssl is not installed. Please install openssl first."
  if [ "$(uname)" = "Darwin" ]; then
    echo "You can install openssl using Homebrew with the following command:"
    echo "brew install openssl"
  elif [ "$(uname)" = "Linux" ]; then
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
if [ "$LIBSQL_LOCAL_INSTANCE" = "true" ]; then
  LIBSQL_HOST="proxy"
  LIBSQL_PORT="8080"
  LIBSQL_API_HOST="proxy"
  LIBSQL_API_PORT="8081"
  LIBSQL_API_USERNAME=""
  LIBSQL_API_PASSWORD=""
else
  printf "Enter LibSQL host: "
  read LIBSQL_HOST
  printf "Enter LibSQL port [8080]: "
  read input_port
  LIBSQL_PORT="${input_port:-8080}"
  printf "Enter LibSQL API host: "
  read LIBSQL_API_HOST
  printf "Enter LibSQL API port [8081]: "
  read input_api_port
  LIBSQL_API_PORT="${input_api_port:-8081}"
  printf "Enter LibSQL API username (optional): "
  read LIBSQL_API_USERNAME
  printf "Enter LibSQL API password (optional): "
  stty -echo
  read LIBSQL_API_PASSWORD
  stty echo
  echo
fi

# Compose templates
COMPOSE_TEMPLATE_WEBUI_LOCAL_INSTANCE=$(
  cat <<EOF
services:
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
EOF
)

COMPOSE_TEMPLATE_WEBUI_REMOTE_INSTANCE=$(
  cat <<EOF
services:
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
EOF
)

COMPOSE_TEMPLATE_PROXY=$(
  cat <<EOF
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
EOF
)

COMPOSE_TEMPLATE_DB=$(
  cat <<EOF
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
EOF
)

COMPOSE_TEMPLATE_NETWORK=$(
  cat <<EOF
networks:
  mylibsqladmin-network:
    driver: bridge
    name: mylibsqladmin-network
EOF
)

# Write to compose.yml
if [ "$LIBSQL_LOCAL_INSTANCE" = "true" ]; then
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

printf "\n✅ Docker Compose file created at: compose.yml\n"
printf "👉 Run it with: docker compose -f compose.yml up -d\n"

# Ask to run
printf "Run Docker Compose now? (y/n) "
read run_reply
case $run_reply in
[yY]*) docker compose -f compose.yml up -d ;;
esac

# Ask to self-delete script
printf "Delete this script? (y/n) "
read delete_reply
case $delete_reply in
[yY]*) rm -f "$0" ;;
esac

printf "\n🎉 Installation completed successfully!\n"
