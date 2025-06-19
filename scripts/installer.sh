#!/bin/sh
set -eu

# Function to read from terminal
tty_read() {
  read -r input </dev/tty
  echo "$input"
}

prompt() {
  message="$1"
  default="$2"
  printf "%s [%s]: " "$message" "$default" >/dev/tty
  input=$(tty_read)
  if [ -z "$input" ]; then
    echo "$default"
  else
    echo "$input"
  fi
}

# Main script execution
(
  # Check Docker
  if ! command -v docker >/dev/null 2>&1; then
    echo "❌ Docker is not installed. Please install Docker first." >/dev/tty
    if [ "$(uname)" = "Darwin" ]; then
      echo "You can install Docker using Homebrew with:" >/dev/tty
      echo "brew install --cask docker" >/dev/tty
    elif [ "$(uname)" = "Linux" ]; then
      echo "You can install Docker using:" >/dev/tty
      echo "curl -fsSL https://get.docker.com | sh" >/dev/tty
    fi
    exit 1
  fi

  if ! docker info >/dev/null 2>&1; then
    echo "❌ Docker is not running. Please start Docker first." >/dev/tty
    exit 1
  fi

  # Check for existing compose files
  if [ -f "compose.yml" ] || [ -f "docker-compose.yml" ]; then
    echo "❌ Docker Compose file already exists. Please remove it first." >/dev/tty
    exit 1
  fi

  # Check OpenSSL
  if ! command -v openssl >/dev/null 2>&1; then
    echo "❌ openssl is not installed. Please install openssl first." >/dev/tty
    if [ "$(uname)" = "Darwin" ]; then
      echo "You can install openssl using Homebrew with:" >/dev/tty
      echo "brew install openssl" >/dev/tty
    elif [ "$(uname)" = "Linux" ]; then
      echo "You can install openssl using:" >/dev/tty
      echo "sudo apt-get install openssl" >/dev/tty
    fi
    exit 1
  fi

  # Collect user input
  APP_KEY="base64:$(openssl rand -base64 32 | tr -d '\n')"
  APP_TIMEZONE=$(prompt "Enter timezone" "Asia/Jakarta")
  APP_NAME=$(prompt "Enter application name" "MyLibSQLAdmin")
  LIBSQL_LOCAL_INSTANCE=$(prompt "Use local LibSQL instance? (true/false)" "true")

  if [ "$LIBSQL_LOCAL_INSTANCE" = "true" ]; then
    LIBSQL_HOST="proxy"
    LIBSQL_PORT="8080"
    LIBSQL_API_HOST="proxy"
    LIBSQL_API_PORT="8081"
    LIBSQL_API_USERNAME=""
    LIBSQL_API_PASSWORD=""
  else
    printf "Enter LibSQL host: " >/dev/tty
    LIBSQL_HOST=$(tty_read)
    printf "Enter LibSQL port [8080]: " >/dev/tty
    input_port=$(tty_read)
    LIBSQL_PORT="${input_port:-8080}"
    printf "Enter LibSQL API host: " >/dev/tty
    LIBSQL_API_HOST=$(tty_read)
    printf "Enter LibSQL API port [8081]: " >/dev/tty
    input_api_port=$(tty_read)
    LIBSQL_API_PORT="${input_api_port:-8081}"
    printf "Enter LibSQL API username (optional): " >/dev/tty
    LIBSQL_API_USERNAME=$(tty_read)
    printf "Enter LibSQL API password (optional): " >/dev/tty
    LIBSQL_API_PASSWORD=$(tty_read)
    echo >/dev/tty
  fi

  # Write compose.yml directly using echo
  if [ "$LIBSQL_LOCAL_INSTANCE" = "true" ]; then
    echo "services:" >compose.yml
    echo "  webui:" >>compose.yml
    echo "    container_name: mylibsqladmin-webui-prod" >>compose.yml
    echo "    image: ghcr.io/darkterminal/mylibsqladmin-web:latest" >>compose.yml
    echo "    ports:" >>compose.yml
    echo '      - "8000:8000"' >>compose.yml
    echo "    networks:" >>compose.yml
    echo "      - mylibsqladmin-network" >>compose.yml
    echo "    restart: unless-stopped" >>compose.yml
    echo "    environment:" >>compose.yml
    echo "      - APP_TIMEZONE=$APP_TIMEZONE" >>compose.yml
    echo "      - APP_KEY=$APP_KEY" >>compose.yml
    echo "      - DB_CONNECTION=libsql" >>compose.yml
    echo "      - SESSION_DRIVER=file" >>compose.yml
    echo "      - APP_NAME=$APP_NAME" >>compose.yml
    echo "      - REGISTRATION_ENABLED=false" >>compose.yml
    echo "      - LIBSQL_LOCAL_INSTANCE=$LIBSQL_LOCAL_INSTANCE" >>compose.yml
    echo "      - LIBSQL_HOST=$LIBSQL_HOST" >>compose.yml
    echo "      - LIBSQL_PORT=$LIBSQL_PORT" >>compose.yml
    echo "      - LIBSQL_API_HOST=$LIBSQL_API_HOST" >>compose.yml
    echo "      - LIBSQL_API_PORT=$LIBSQL_API_PORT" >>compose.yml
    echo "      - LIBSQL_API_USERNAME=$LIBSQL_API_USERNAME" >>compose.yml
    echo "      - LIBSQL_API_PASSWORD=$LIBSQL_API_PASSWORD" >>compose.yml
    echo "    depends_on:" >>compose.yml
    echo "      - db" >>compose.yml
    echo "" >>compose.yml
    echo "  proxy:" >>compose.yml
    echo "    container_name: mylibsqladmin-proxy" >>compose.yml
    echo "    image: ghcr.io/darkterminal/mylibsqladmin-proxy:latest" >>compose.yml
    echo "    environment:" >>compose.yml
    echo "      - APP_ENV=production" >>compose.yml
    echo "    ports:" >>compose.yml
    echo '      - "8080:8080"' >>compose.yml
    echo '      - "5001:5001"' >>compose.yml
    echo '      - "8081:8081"' >>compose.yml
    echo "    networks:" >>compose.yml
    echo "      - mylibsqladmin-network" >>compose.yml
    echo "    restart: unless-stopped" >>compose.yml
    echo "    depends_on:" >>compose.yml
    echo "      - webui" >>compose.yml
    echo "      - db" >>compose.yml
    echo "" >>compose.yml
    echo "  db:" >>compose.yml
    echo "    container_name: mylibsqladmin-db" >>compose.yml
    echo "    image: ghcr.io/tursodatabase/libsql-server:latest" >>compose.yml
    echo '    entrypoint: ["/bin/sqld"]' >>compose.yml
    echo "    command:" >>compose.yml
    echo '      - "--http-listen-addr"' >>compose.yml
    echo '      - "0.0.0.0:8080"' >>compose.yml
    echo '      - "--grpc-listen-addr"' >>compose.yml
    echo '      - "0.0.0.0:5001"' >>compose.yml
    echo '      - "--admin-listen-addr"' >>compose.yml
    echo '      - "0.0.0.0:8081"' >>compose.yml
    echo '      - "--enable-namespaces"' >>compose.yml
    echo '      - "--no-welcome"' >>compose.yml
    echo '    user: "1000:1000"' >>compose.yml
    echo "    volumes:" >>compose.yml
    echo "      - ./libsql-data:/var/lib/sqld" >>compose.yml
    echo "    restart: unless-stopped" >>compose.yml
    echo "    networks:" >>compose.yml
    echo "      - mylibsqladmin-network" >>compose.yml
    echo "" >>compose.yml
    echo "networks:" >>compose.yml
    echo "  mylibsqladmin-network:" >>compose.yml
    echo "    driver: bridge" >>compose.yml
    echo "    name: mylibsqladmin-network" >>compose.yml
  else
    echo "services:" >compose.yml
    echo "  webui:" >>compose.yml
    echo "    container_name: mylibsqladmin-webui" >>compose.yml
    echo "    image: ghcr.io/darkterminal/mylibsqladmin-web:latest" >>compose.yml
    echo "    ports:" >>compose.yml
    echo '      - "8000:8000"' >>compose.yml
    echo "    network_mode: host" >>compose.yml
    echo "    restart: unless-stopped" >>compose.yml
    echo "    environment:" >>compose.yml
    echo "      - APP_TIMEZONE=$APP_TIMEZONE" >>compose.yml
    echo "      - APP_KEY=$APP_KEY" >>compose.yml
    echo "      - DB_CONNECTION=libsql" >>compose.yml
    echo "      - SESSION_DRIVER=file" >>compose.yml
    echo "      - APP_NAME=$APP_NAME" >>compose.yml
    echo "      - REGISTRATION_ENABLED=false" >>compose.yml
    echo "      - LIBSQL_LOCAL_INSTANCE=false" >>compose.yml
    echo "      - LIBSQL_HOST=$LIBSQL_HOST" >>compose.yml
    echo "      - LIBSQL_PORT=$LIBSQL_PORT" >>compose.yml
    echo "      - LIBSQL_API_HOST=$LIBSQL_API_HOST" >>compose.yml
    echo "      - LIBSQL_API_PORT=$LIBSQL_API_PORT" >>compose.yml
    echo "      - LIBSQL_API_USERNAME=$LIBSQL_API_USERNAME" >>compose.yml
    echo "      - LIBSQL_API_PASSWORD=$LIBSQL_API_PASSWORD" >>compose.yml
  fi

  # Verify file creation
  if [ -s "compose.yml" ]; then
    echo >/dev/tty
    echo "✅ Docker Compose file created at: $(pwd)/compose.yml" >/dev/tty
    echo "👉 Run it with: docker compose -f compose.yml up -d" >/dev/tty
    echo >/dev/tty
  else
    echo "❌ Failed to create compose.yml" >/dev/tty
    exit 1
  fi

  # Ask to run
  printf "Run Docker Compose now? (y/n) " >/dev/tty
  run_reply=$(tty_read)
  case $run_reply in
  [yY]*)
    echo "Starting containers..." >/dev/tty
    docker compose -f compose.yml up -d >/dev/tty 2>&1
    echo "Containers started successfully!" >/dev/tty
    ;;
  *)
    echo "Skipping container startup" >/dev/tty
    ;;
  esac

  # Ask to delete script
  printf "Delete this script? (y/n) " >/dev/tty
  delete_reply=$(tty_read)
  case $delete_reply in
  [yY]*)
    script_name="$0"
    rm -f "$script_name"
    echo "Script deleted" >/dev/tty
    ;;
  *)
    echo "Script preserved" >/dev/tty
    ;;
  esac

  exit 0
)
