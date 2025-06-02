DOCKERIGNORE_DEV = ./docker/.dockerignore.local
DOCKERIGNORE_PROD = ./docker/.dockerignore.prod
DOCKERIGNORE_TARGET_DIR = ./.dockerignore

GET_LOCAL_INSTANCE = $(shell grep -E '^LIBSQL_LOCAL_INSTANCE=' .env | cut -d'=' -f2)
VERSION ?= $(shell git describe --tags --abbrev=0)

COMPOSE_PROFILES_DEV = --profile development $(if $(filter true,$(GET_LOCAL_INSTANCE)),--profile local-instance)
COMPOSE_PROFILES_PROD = --profile production $(if $(filter true,$(GET_LOCAL_INSTANCE)),--profile local-instance)

COMPOSE_FILE_BASE = compose.yml
COMPOSE_FILES = -f $(COMPOSE_FILE_BASE) $(if $(filter true,$(GET_LOCAL_INSTANCE)),-f compose.lli.yml,-f compose.lri.yml)

SETUP_NGINX_PROD = sed -i "s|proxy_pass http://web:8000/validate-subdomain;|proxy_pass http://web_prod:8000/validate-subdomain;|" nginx/nginx.conf
SETUP_NGINX_DEV = sed -i "s|proxy_pass http://web_prod:8000/validate-subdomain;|proxy_pass http://web:8000/validate-subdomain;|" nginx/nginx.conf

.PHONY: help compose-dev/build compose-prod/build compose-dev/up compose-prod/up compose-dev/down compose-prod/down compose-dev/restart compose-prod/restart compose-dev/rebuild compose-prod/rebuild compose-lli/build compose-lri/build compose-dev/upd compose-prod/upd compose-dev/restartd compose-prod/restartd compose-dev-lli/build compose-prod-lli/build compose-dev-lri/build compose-prod-lri/build

help:
	@echo "Usage:"
	@echo "  make compose-dev/build\t\tBuild development environment"
	@echo "  make compose-prod/build\t\tBuild production environment"
	@echo "  make compose-dev/up\t\t\tStart development environment"
	@echo "  make compose-dev/upd\t\t\tStart development environment in detached mode"
	@echo "  make compose-prod/up\t\t\tStart production environment"
	@echo "  make compose-prod/upd\t\t\tStart production environment in detached mode"
	@echo "  make compose-dev/down\t\t\tStop development environment"
	@echo "  make compose-prod/down\t\tStop production environment"
	@echo "  make compose-dev/restart\t\tRestart development environment"
	@echo "  make compose-dev/restartd\t\tRestart development environment in detached mode"
	@echo "  make compose-prod/restart\t\tRestart production environment"
	@echo "  make compose-prod/restartd\t\tRestart production environment in detached mode"
	@echo "  make compose-dev/rebuild\t\tRebuild development containers"
	@echo "  make compose-prod/rebuild\t\tRebuild production containers"
	@echo "  make compose-dev-lli/build\t\tBuild development environment with local instance"
	@echo "  make compose-prod-lli/build\t\tBuild production environment with local instance"
	@echo "  make compose-dev-lri/build\t\tBuild development environment with remote instance"
	@echo "  make compose-prod-lri/build\t\tBuild production environment with remote instance"

compose-prod/build:
	cp $(DOCKERIGNORE_PROD) $(DOCKERIGNORE_TARGET_DIR)
	$(SETUP_NGINX_PROD)
	docker buildx build --push -t ghcr.io/darkterminal/mylibsqladmin-web:$(VERSION)-production -f Dockerfile.production .

compose-prod-lli/build:
	cp $(DOCKERIGNORE_PROD) $(DOCKERIGNORE_TARGET_DIR)
	$(SETUP_NGINX_PROD)
	docker buildx build --push -t ghcr.io/darkterminal/mylibsqladmin-web:$(VERSION)-production-lli -f Dockerfile.production .

compose-prod-lri/build:
	cp $(DOCKERIGNORE_PROD) $(DOCKERIGNORE_TARGET_DIR)
	$(SETUP_NGINX_PROD)
	docker buildx build --push -t ghcr.io/darkterminal/mylibsqladmin-web:$(VERSION)-production-lri -f Dockerfile .

compose-dev/build:
	cp $(DOCKERIGNORE_DEV) $(DOCKERIGNORE_TARGET_DIR)
	$(SETUP_NGINX_DEV)
	docker buildx build --push -t ghcr.io/darkterminal/mylibsqladmin-web:development -f Dockerfile.local .

compose-dev-lli/build:
	cp $(DOCKERIGNORE_DEV) $(DOCKERIGNORE_TARGET_DIR)
	$(SETUP_NGINX_DEV)
	docker buildx build --push -t ghcr.io/darkterminal/mylibsqladmin-web:development-lli -f Dockerfile.local .

compose-dev-lri/build:
	cp $(DOCKERIGNORE_DEV) $(DOCKERIGNORE_TARGET_DIR)
	$(SETUP_NGINX_DEV)
	docker buildx build --push -t ghcr.io/darkterminal/mylibsqladmin-web:development-lri -f Dockerfile .

compose-dev/up:
	cp $(DOCKERIGNORE_DEV) $(DOCKERIGNORE_TARGET_DIR)
	$(SETUP_NGINX_DEV)
	COMPOSE_BAKE=true docker compose $(COMPOSE_FILES) $(COMPOSE_PROFILES_DEV) up

compose-dev/upd:
	cp $(DOCKERIGNORE_DEV) $(DOCKERIGNORE_TARGET_DIR)
	$(SETUP_NGINX_DEV)
	COMPOSE_BAKE=true docker compose $(COMPOSE_FILES) $(COMPOSE_PROFILES_DEV) up -d

compose-prod/up:
	cp $(DOCKERIGNORE_PROD) $(DOCKERIGNORE_TARGET_DIR)
	$(SETUP_NGINX_PROD)
	COMPOSE_BAKE=true docker compose $(COMPOSE_FILES) $(COMPOSE_PROFILES_PROD) up

compose-prod/upd:
	cp $(DOCKERIGNORE_PROD) $(DOCKERIGNORE_TARGET_DIR)
	$(SETUP_NGINX_PROD)
	COMPOSE_BAKE=true docker compose $(COMPOSE_FILES) $(COMPOSE_PROFILES_PROD) up -d

compose-dev/down:
	COMPOSE_BAKE=true docker compose $(COMPOSE_FILES) $(COMPOSE_PROFILES_DEV) down --remove-orphans

compose-prod/down:
	COMPOSE_BAKE=true docker compose $(COMPOSE_FILES) $(COMPOSE_PROFILES_PROD) down --remove-orphans

compose-dev/restart:
	cp $(DOCKERIGNORE_DEV) $(DOCKERIGNORE_TARGET_DIR)
	$(SETUP_NGINX_DEV)
	COMPOSE_BAKE=true docker compose $(COMPOSE_FILES) $(COMPOSE_PROFILES_DEV) down --remove-orphans
	COMPOSE_BAKE=true docker compose $(COMPOSE_FILES) $(COMPOSE_PROFILES_DEV) up

compose-dev/restartd:
	cp $(DOCKERIGNORE_DEV) $(DOCKERIGNORE_TARGET_DIR)
	$(SETUP_NGINX_DEV)
	COMPOSE_BAKE=true docker compose $(COMPOSE_FILES) $(COMPOSE_PROFILES_DEV) down --remove-orphans
	COMPOSE_BAKE=true docker compose $(COMPOSE_FILES) $(COMPOSE_PROFILES_DEV) up -d

compose-prod/restart:
	cp $(DOCKERIGNORE_PROD) $(DOCKERIGNORE_TARGET_DIR)
	$(SETUP_NGINX_PROD)
	COMPOSE_BAKE=true docker compose $(COMPOSE_FILES) $(COMPOSE_PROFILES_PROD) down --remove-orphans
	COMPOSE_BAKE=true docker compose $(COMPOSE_FILES) $(COMPOSE_PROFILES_PROD) up

compose-prod/restartd:
	cp $(DOCKERIGNORE_PROD) $(DOCKERIGNORE_TARGET_DIR)
	$(SETUP_NGINX_PROD)
	COMPOSE_BAKE=true docker compose $(COMPOSE_FILES) $(COMPOSE_PROFILES_PROD) down --remove-orphans
	COMPOSE_BAKE=true docker compose $(COMPOSE_FILES) $(COMPOSE_PROFILES_PROD) up -d

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

