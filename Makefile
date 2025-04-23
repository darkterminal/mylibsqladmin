DOCKERIGNORE_DEV = ./admin/.docker/.dockerignore.local
DOCKERIGNORE_PROD = ./admin/.docker/.dockerignore.prod
DOCKERIGNORE_TARGET_DIR = ./admin/.dockerignore

# Helper to read LIBSQL_LOCAL_INSTANCE from .env (unused in this version)
GET_LOCAL_INSTANCE = $(shell grep -E '^LIBSQL_LOCAL_INSTANCE=' .env | cut -d'=' -f2)

# Common compose flags with conditional profile
COMPOSE_PROFILES_DEV = --profile development $(if $(filter true,$(LIBSQL_LOCAL_INSTANCE)),--profile local-instance)
COMPOSE_PROFILES_PROD = --profile production $(if $(filter true,$(LIBSQL_LOCAL_INSTANCE)),--profile local-instance)

# Conditionally select between lli/lri compose files
COMPOSE_FILE_BASE = compose.yml
COMPOSE_FILES = -f $(COMPOSE_FILE_BASE) $(if $(filter true,$(LIBSQL_LOCAL_INSTANCE)),-f compose.lli.yml,-f compose.lri.yml)

.PHONY: help compose-dev/up compose-prod/up compose-dev/down compose-prod/down compose-dev/restart compose-prod/restart compose-dev/rebuild compose-prod/rebuild

help:
	@echo "Usage:"
	@echo "  make compose-dev/up"
	@echo "  make compose-prod/up"
	@echo "  make compose-dev/down"
	@echo "  make compose-prod/down"
	@echo "  make compose-dev/restart"
	@echo "  make compose-prod/restart"
	@echo "  make compose-dev/rebuild"
	@echo "  make compose-prod/rebuild"

compose-dev/up:
	cp $(DOCKERIGNORE_DEV) $(DOCKERIGNORE_TARGET_DIR)
	COMPOSE_BAKE=true docker compose $(COMPOSE_FILES) $(COMPOSE_PROFILES_DEV) up

compose-prod/up:
	cp $(DOCKERIGNORE_PROD) $(DOCKERIGNORE_TARGET_DIR)
	COMPOSE_BAKE=true docker compose $(COMPOSE_FILES) $(COMPOSE_PROFILES_PROD) up

compose-dev/down:
	COMPOSE_BAKE=true docker compose $(COMPOSE_FILES) $(COMPOSE_PROFILES_DEV) down --remove-orphans

compose-prod/down:
	COMPOSE_BAKE=true docker compose $(COMPOSE_FILES) $(COMPOSE_PROFILES_PROD) down --remove-orphans

compose-dev/restart:
	cp $(DOCKERIGNORE_DEV) $(DOCKERIGNORE_TARGET_DIR)
	COMPOSE_BAKE=true docker compose $(COMPOSE_FILES) $(COMPOSE_PROFILES_DEV) down --remove-orphans
	COMPOSE_BAKE=true docker compose $(COMPOSE_FILES) $(COMPOSE_PROFILES_DEV) up

compose-prod/restart:
	cp $(DOCKERIGNORE_PROD) $(DOCKERIGNORE_TARGET_DIR)
	COMPOSE_BAKE=true docker compose $(COMPOSE_FILES) $(COMPOSE_PROFILES_PROD) down --remove-orphans
	COMPOSE_BAKE=true docker compose $(COMPOSE_FILES) $(COMPOSE_PROFILES_PROD) up

compose-dev/rebuild:
	cp $(DOCKERIGNORE_DEV) $(DOCKERIGNORE_TARGET_DIR)
	COMPOSE_BAKE=true docker compose $(COMPOSE_FILES) $(COMPOSE_PROFILES_DEV) down -v --remove-orphans
	COMPOSE_BAKE=true docker compose $(COMPOSE_FILES) $(COMPOSE_PROFILES_DEV) build --no-cache
	COMPOSE_BAKE=true docker compose $(COMPOSE_FILES) $(COMPOSE_PROFILES_DEV) up

compose-prod/rebuild:
	cp $(DOCKERIGNORE_PROD) $(DOCKERIGNORE_TARGET_DIR)
	COMPOSE_BAKE=true docker compose $(COMPOSE_FILES) $(COMPOSE_PROFILES_PROD) down -v --remove-orphans
	COMPOSE_BAKE=true docker compose $(COMPOSE_FILES) $(COMPOSE_PROFILES_PROD) build --no-cache
	COMPOSE_BAKE=true docker compose $(COMPOSE_FILES) $(COMPOSE_PROFILES_PROD) up
