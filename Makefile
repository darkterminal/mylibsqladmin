DOCKERIGNORE_DEV = ./docker/.dockerignore.local
DOCKERIGNORE_PROD = ./docker/.dockerignore.prod
DOCKERIGNORE_TARGET_DIR = ./dockerignore

# Read LIBSQL_LOCAL_INSTANCE from .env
GET_LOCAL_INSTANCE = $(shell grep -E '^LIBSQL_LOCAL_INSTANCE=' .env | cut -d'=' -f2)

# Common compose flags with conditional profile
COMPOSE_PROFILES_DEV = --profile development $(if $(filter true,$(GET_LOCAL_INSTANCE)),--profile local-instance)
COMPOSE_PROFILES_PROD = --profile production $(if $(filter true,$(GET_LOCAL_INSTANCE)),--profile local-instance)

# Conditionally select between lli/lri compose files based on actual .env value
COMPOSE_FILE_BASE = compose.yml
COMPOSE_FILES = -f $(COMPOSE_FILE_BASE) $(if $(filter true,$(GET_LOCAL_INSTANCE)),-f compose.lli.yml,-f compose.lri.yml)

# Fixed sed commands with proper delimiter and matching
SETUP_NGINX_PROD = sed -i "s|proxy_pass http://web:8000/validate-subdomain;|proxy_pass http://web_prod:8000/validate-subdomain;|" nginx/nginx.conf
SETUP_NGINX_DEV = sed -i "s|proxy_pass http://web_prod:8000/validate-subdomain;|proxy_pass http://web:8000/validate-subdomain;|" nginx/nginx.conf

.PHONY: help compose-dev/up compose-prod/up compose-dev/down compose-prod/down compose-dev/restart compose-prod/restart compose-dev/rebuild compose-prod/rebuild

help:
	@echo "Usage:"
	@echo "  make compose-dev/up\t\tStart development environment"
	@echo "  make compose-prod/up\t\tStart production environment"
	@echo "  make compose-dev/down\t\tStop development environment"
	@echo "  make compose-prod/down\tStop production environment"
	@echo "  make compose-dev/restart\tRestart development environment"
	@echo "  make compose-prod/restart\tRestart production environment"
	@echo "  make compose-dev/rebuild\tRebuild development containers"
	@echo "  make compose-prod/rebuild\tRebuild production containers"

compose-dev/up:
	cp $(DOCKERIGNORE_DEV) $(DOCKERIGNORE_TARGET_DIR)
	$(SETUP_NGINX_DEV)
	COMPOSE_BAKE=true docker compose $(COMPOSE_FILES) $(COMPOSE_PROFILES_DEV) up

compose-prod/up:
	cp $(DOCKERIGNORE_PROD) $(DOCKERIGNORE_TARGET_DIR)
	$(SETUP_NGINX_PROD)
	COMPOSE_BAKE=true docker compose $(COMPOSE_FILES) $(COMPOSE_PROFILES_PROD) up

compose-dev/down:
	COMPOSE_BAKE=true docker compose $(COMPOSE_FILES) $(COMPOSE_PROFILES_DEV) down --remove-orphans

compose-prod/down:
	COMPOSE_BAKE=true docker compose $(COMPOSE_FILES) $(COMPOSE_PROFILES_PROD) down --remove-orphans

compose-dev/restart:
	cp $(DOCKERIGNORE_DEV) $(DOCKERIGNORE_TARGET_DIR)
	$(SETUP_NGINX_DEV)
	COMPOSE_BAKE=true docker compose $(COMPOSE_FILES) $(COMPOSE_PROFILES_DEV) down --remove-orphans
	COMPOSE_BAKE=true docker compose $(COMPOSE_FILES) $(COMPOSE_PROFILES_DEV) up

compose-prod/restart:
	cp $(DOCKERIGNORE_PROD) $(DOCKERIGNORE_TARGET_DIR)
	$(SETUP_NGINX_PROD)
	COMPOSE_BAKE=true docker compose $(COMPOSE_FILES) $(COMPOSE_PROFILES_PROD) down --remove-orphans
	COMPOSE_BAKE=true docker compose $(COMPOSE_FILES) $(COMPOSE_PROFILES_PROD) up

compose-dev/rebuild:
	cp $(DOCKERIGNORE_DEV) $(DOCKERIGNORE_TARGET_DIR)
	$(SETUP_NGINX_DEV)
	COMPOSE_BAKE=true docker compose $(COMPOSE_FILES) $(COMPOSE_PROFILES_DEV) down -v --remove-orphans
	COMPOSE_BAKE=true docker compose $(COMPOSE_FILES) $(COMPOSE_PROFILES_DEV) build --no-cache

compose-prod/rebuild:
	cp $(DOCKERIGNORE_PROD) $(DOCKERIGNORE_TARGET_DIR)
	$(SETUP_NGINX_PROD)
	COMPOSE_BAKE=true docker compose $(COMPOSE_FILES) $(COMPOSE_PROFILES_PROD) down -v --remove-orphans
	COMPOSE_BAKE=true docker compose $(COMPOSE_FILES) $(COMPOSE_PROFILES_PROD) build --no-cache
