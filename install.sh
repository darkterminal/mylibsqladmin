#!/usr/bin/env bash
set -euo pipefail

# Colors and formatting
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
NC='\033[0m' # No Color
BOLD='\033[1m'

show_banner() {
    echo -e "${YELLOW}===============================================================================${NC}"
    echo -e "${BOLD}Welcome to MyLibSQLAdmin Installer${NC}"
    echo -e "Author: darkterminal"
    echo -e "${YELLOW}===============================================================================${NC}"
    echo
}

show_banner

# 1) OS detection
if [[ -f /etc/os-release ]]; then
    source /etc/os-release
    OS_ID=$ID
    OS_VER=$VERSION_ID
    echo "Your OS is: $OS_ID $OS_VER"
else
    echo "Error: Unable to detect OS."
    exit 1
fi

# 2) Install system packages (curl, git, unzip)
install_common_pkgs() {
    missing_pkgs=()
    for pkg in curl git unzip lsb-release apt-transport-https ca-certificates gnupg; do
        if ! dpkg -s "$pkg" >/dev/null 2>&1; then
            missing_pkgs+=("$pkg")
        fi
    done
    if [[ "${#missing_pkgs[@]}" -gt 0 ]]; then
        sudo apt-get update
        sudo apt-get install -y "${missing_pkgs[@]}"
    fi
}

# 3) Install Docker Engine & Compose plugin if missing
install_docker() {
    if ! command -v docker >/dev/null; then
        echo "Installing Docker..."
        curl -fsSL https://get.docker.com | bash
    fi
    if ! docker compose version >/dev/null; then
        echo "Installing Docker Compose plugin..."
        if ! dpkg -s docker-compose-plugin >/dev/null 2>&1; then
            sudo apt-get update
            sudo apt-get install -y docker-compose-plugin
        fi
    fi
}

# 4) Install Node.js (via NodeSource)
install_node() {
    if ! command -v node >/dev/null && ! command -v bun >/dev/null && ! command -v nvm >/dev/null; then
        echo "Installing Node.js..."
        curl -fsSL https://deb.nodesource.com/setup_22.x | bash -
        sudo apt-get install -y nodejs
    fi
}

# 5) Clone or update the repo
if [[ ! -d mylibsqladmin ]]; then
    echo "Cloning repository..."
    git clone https://github.com/darkterminal/mylibsqladmin.git
fi
cd mylibsqladmin

# 6) Environment setup
echo "Setting up environment..."
cp .env.example .env
cp admin/.env.example admin/.env

select_env_variable() {
    local var_name=$1
    local prompt_message=$2
    local options=("${@:3}")

    echo "$prompt_message"
    PS3="Enter your choice (1-${#options[@]}): "
    select opt in "${options[@]}"; do
        if [[ -n "$opt" ]]; then
            new_value="$opt"
            break
        else
            echo "Invalid option. Try again."
        fi
    done

    if grep -q "^$var_name=" .env; then
        sed -i "s|^$var_name=.*|$var_name=$new_value|" .env
    else
        echo "$var_name=$new_value" >>.env
    fi
}

select_boolean() {
    local var_name=$1
    local prompt_message=$2
    local default_value=$3

    local options=("true" "false")
    local default_index=0
    [[ "$default_value" == "false" ]] && default_index=1

    echo "$prompt_message"
    PS3="Enter your choice (1-2): "
    select opt in "Yes" "No"; do
        case $REPLY in
        1)
            new_value="true"
            break
            ;;
        2)
            new_value="false"
            break
            ;;
        *) echo "Invalid option. Try again." ;;
        esac
    done

    if grep -q "^$var_name=" .env; then
        sed -i "s|^$var_name=.*|$var_name=$new_value|" .env
    else
        echo "$var_name=$new_value" >>.env
    fi
}

# Prompt user to configure .env variables
echo "Configuring environment variables..."

# Configure APP_ENV with select menu
select_env_variable "APP_ENV" "Select application environment:" "production" "development"

# Configure timezone with select menu
echo "Select application timezone:"
tz_options=("Asia/Jakarta" "UTC" "America/New_York" "Europe/London" "Other")
select_env_variable "APP_TIMEZONE" "Select timezone:" "${tz_options[@]}"

# Configure boolean options with select menus
select_boolean "REGISTRATION_ENABLED" "Enable user registration?" "false"
select_boolean "LIBSQL_LOCAL_INSTANCE" "Use local LibSQL instance?" "true"

# Configure LibSQL credentials with select menu
echo "LibSQL API credentials:"
select_boolean "LIBSQL_CREDENTIALS" "Set LibSQL API credentials?" "false"

if [[ "$(grep '^LIBSQL_CREDENTIALS=' .env | cut -d'=' -f2)" == "true" ]]; then
    sed -i 's/^#\(LIBSQL_API_USERNAME=\)/\1/' .env
    sed -i 's/^#\(LIBSQL_API_PASSWORD=\)/\1/' .env
    read -p "Enter LibSQL API username: " username
    read -p "Enter LibSQL API password: " password
    sed -i "s|^LIBSQL_API_USERNAME=.*|LIBSQL_API_USERNAME=$username|" .env
    sed -i "s|^LIBSQL_API_PASSWORD=.*|LIBSQL_API_PASSWORD=$password|" .env
else
    sed -i 's/^LIBSQL_API_USERNAME=/#LIBSQL_API_USERNAME=/' .env
    sed -i 's/^LIBSQL_API_PASSWORD=/#LIBSQL_API_PASSWORD=/' .env
fi

# Run install_* functions
echo "Running install_* functions..."
install_common_pkgs
install_docker
install_node

echo "Change to admin directory..."
cd admin

# 7) PHP & Composer
echo "Installing Composer dependencies..."
composer install --no-interaction --prefer-dist
echo "Generating application key..."
php artisan key:generate

# 8) JavaScript dependencies
echo "Installing Node.js dependencies..."
npm install
npm run build || true

# Read APP_ENV from .env file before conditional check
APP_ENV=$(grep '^APP_ENV=' ../.env | cut -d '=' -f2)

# change APP_ENV value based on user input
if [[ "$APP_ENV" == "development" || "$APP_ENV" == "local" ]]; then
    APP_ENV="development"
    sed -i 's/^APP_ENV=production/APP_ENV=development/' .env
else
    APP_ENV="production"
    sed -i 's/^APP_ENV=development/APP_ENV=production/' .env
fi
echo "Reading APP_ENV from .env file... the value is: ${APP_ENV}"

# Rename .env file based on APP_ENV value
echo "Renaming .env file... to .env.${APP_ENV}"
echo "Current directory is: $(pwd)"
mv .env .env.${APP_ENV}
source .env.${APP_ENV}

echo "Moving from admin to root directory..."
cd ..
echo "Current directory is: $(pwd)"

# 9) Show summary and get confirmation
source .env

show_config_summary() {
    echo -e "${YELLOW}===============================================================================${NC}"
    echo -e "${BOLD}${CYAN}Configuration Summary:${NC}"
    echo
    echo -e "  ${BOLD}Application Environment:${NC} ${GREEN}$(grep '^APP_ENV=' .env | cut -d'=' -f2)${NC}"
    echo -e "  ${BOLD}Timezone:${NC} ${GREEN}$(grep '^APP_TIMEZONE=' .env | cut -d'=' -f2)${NC}"
    echo -e "  ${BOLD}User Registration:${NC} ${GREEN}$(grep '^REGISTRATION_ENABLED=' .env | cut -d'=' -f2)${NC}"
    echo -e "  ${BOLD}Local LibSQL Instance:${NC} ${GREEN}$(grep '^LIBSQL_LOCAL_INSTANCE=' .env | cut -d'=' -f2)${NC}"

    if [[ "$(grep '^LIBSQL_CREDENTIALS=' .env | cut -d'=' -f2)" == "true" ]]; then
        echo -e "  ${BOLD}LibSQL Credentials:${NC} ${GREEN}Username: $(grep '^LIBSQL_API_USERNAME=' .env | cut -d'=' -f2)${NC}"
        echo -e "  ${BOLD}                ${NC} ${GREEN}Password: ****** (hidden)${NC}"
    else
        echo -e "  ${BOLD}LibSQL Credentials:${NC} ${RED}Not configured${NC}"
    fi
    echo -e "${YELLOW}===============================================================================${NC}"
}

confirm_configuration() {
    while true; do
        echo -e "${BOLD}${CYAN}Proceed with these settings?${NC}"
        PS3=$'\e[1;34mChoose an option (1-2): \e[0m'
        select confirm in "Start Services" "Reconfigure Settings"; do
            case $REPLY in
            1) return 0 ;;
            2) return 1 ;;
            *) echo -e "${RED}⨯ Invalid choice. Please try again.${NC}" ;;
            esac
        done
    done
}

# Show summary and get confirmation
while true; do
    show_config_summary
    if confirm_configuration; then
        break
    else
        echo -e "${YELLOW}Resetting configuration...${NC}"

        # Reset environment files
        cp .env.example .env
        cp admin/.env.example admin/.env

        # Remove renamed env files
        rm -f admin/.env.* 2>/dev/null

        # Re-run configuration
        echo -e "${CYAN}${BOLD}Reconfiguring settings...${NC}"
        source "$0" --reconfigure
        exit $?
    fi
done

# 10) Start services
echo "Starting services..."
make compose-prod/up

# Final messages with support links
echo -e "${YELLOW}===============================================================================${NC}"
echo -e "${GREEN}${BOLD}✔ Installation complete!${NC}"
echo -e "${BOLD}You can access MylibSQLAdmin at: ${BLUE}http://localhost:8000${NC}"
echo
echo -e "${CYAN}${BOLD}Support Resources:${NC}"
echo -e "  • Documentation:  ${BLUE}https://deepwiki.com/darkterminal/mylibsqladmin${NC}"
echo -e "  • Report Issues:  ${BLUE}https://github.com/darkterminal/mylibsqladmin/issues${NC}"
echo -e "  • Community Chat: ${BLUE}https://discord.gg/wWDzy5Nt44${NC}"
echo -e "  • Sponsor / Donate: ${BLUE}https://github.com/sponsors/darkterminal${NC}"
echo
echo -e "${YELLOW}Need help? Please visit our support channels above.${NC}"
echo -e "${YELLOW}===============================================================================${NC}"
