GET_LOCAL_INSTANCE = $(shell grep -E '^LIBSQL_LOCAL_INSTANCE=' .env | cut -d'=' -f2)
VERSION ?= $(shell git describe --tags --abbrev=0)

# Container engine selection. Docker remains the default; Kiri is opt-in.
# For builds to run on Kiri, run once per machine: kiri build-with kiri
CONTAINER_ENGINE ?= docker
COMPOSE := $(if $(filter kiri,$(CONTAINER_ENGINE)),kiri compose,docker compose)
BUILDX := docker buildx

COMPOSE_PROFILES_DEV = --profile development $(if $(filter true,$(GET_LOCAL_INSTANCE)),--profile local-instance)
COMPOSE_PROFILES_PROD = --profile production $(if $(filter true,$(GET_LOCAL_INSTANCE)),--profile local-instance)

COMPOSE_FILE_BASE = compose.yml
COMPOSE_FILES = -f $(COMPOSE_FILE_BASE) $(if $(filter true,$(GET_LOCAL_INSTANCE)),-f compose.lli.yml,-f compose.lri.yml)

# Dev override for the proxy's validate-subdomain upstream. Tracked file; the
# Makefile never edits tracked files.
NGINX_CONF_DEV = ./nginx/nginx.dev.conf
NGINX_CONF_PROD = ./nginx/nginx.conf

.PHONY: help compose-dev/build compose-prod/build compose-dev/up compose-prod/up compose-dev/down compose-prod/down compose-dev/restart compose-prod/restart compose-dev/rebuild compose-prod/rebuild compose-lli/build compose-lri/build compose-dev/upd compose-prod/upd compose-dev/restartd compose-prod/restartd compose-proxy/build proxy/test

help:
	@echo "Container engine:   $(CONTAINER_ENGINE)"
	@echo "Compose command:    $(COMPOSE)"
	@echo "To switch engines:  CONTAINER_ENGINE=kiri make <target>"
	@echo "(build targets via 'docker buildx' — set 'kiri build-with kiri' to route them to Kiri)"
	@echo ""
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
	@echo "  make compose-proxy/build\t\tBuild proxy container"
	@echo "  make proxy/test\t\t\tRun proxy Lua unit tests (Kiri by default)"

compose-prod/build:
	$(BUILDX) build --push -t ghcr.io/darkterminal/mylibsqladmin-web:latest -f webapp/Dockerfile.production ./webapp
	$(BUILDX) build --push -t ghcr.io/darkterminal/mylibsqladmin-web:$(VERSION)-production -f webapp/Dockerfile.production ./webapp

compose-dev/build:
	$(BUILDX) build --push -t ghcr.io/darkterminal/mylibsqladmin-web:nightly -f webapp/Dockerfile.local ./webapp

compose-proxy/build:
	$(BUILDX) build --push --build-arg NGINX_CONF=nginx.conf -t ghcr.io/darkterminal/mylibsqladmin-proxy:latest -f nginx/Dockerfile ./nginx
	$(BUILDX) build --push --build-arg NGINX_CONF=nginx.conf -t ghcr.io/darkterminal/mylibsqladmin-proxy:$(VERSION) -f nginx/Dockerfile ./nginx

proxy/test:
	CONTAINER_ENGINE=$(CONTAINER_ENGINE) sh nginx/tests/run.sh

compose-dev/up:
	@echo "engine: $(CONTAINER_ENGINE)"
	NGINX_CONF=$(NGINX_CONF_DEV) LIBSQL_VALIDATOR_UPSTREAM=mylibsqladmin-webui-dev:8000 COMPOSE_BAKE=true $(COMPOSE) $(COMPOSE_FILES) $(COMPOSE_PROFILES_DEV) up

compose-dev/upd:
	@echo "engine: $(CONTAINER_ENGINE)"
	NGINX_CONF=$(NGINX_CONF_DEV) LIBSQL_VALIDATOR_UPSTREAM=mylibsqladmin-webui-dev:8000 COMPOSE_BAKE=true $(COMPOSE) $(COMPOSE_FILES) $(COMPOSE_PROFILES_DEV) up -d

compose-prod/up:
	@echo "engine: $(CONTAINER_ENGINE)"
	NGINX_CONF=$(NGINX_CONF_PROD) LIBSQL_VALIDATOR_UPSTREAM=mylibsqladmin-webui-prod:8000 COMPOSE_BAKE=true $(COMPOSE) $(COMPOSE_FILES) $(COMPOSE_PROFILES_PROD) up

compose-prod/upd:
	@echo "engine: $(CONTAINER_ENGINE)"
	NGINX_CONF=$(NGINX_CONF_PROD) LIBSQL_VALIDATOR_UPSTREAM=mylibsqladmin-webui-prod:8000 COMPOSE_BAKE=true $(COMPOSE) $(COMPOSE_FILES) $(COMPOSE_PROFILES_PROD) up -d

compose-dev/down:
	COMPOSE_BAKE=true $(COMPOSE) $(COMPOSE_FILES) $(COMPOSE_PROFILES_DEV) down --remove-orphans

compose-prod/down:
	COMPOSE_BAKE=true $(COMPOSE) $(COMPOSE_FILES) $(COMPOSE_PROFILES_PROD) down --remove-orphans

compose-dev/restart:
	COMPOSE_BAKE=true $(COMPOSE) $(COMPOSE_FILES) $(COMPOSE_PROFILES_DEV) down --remove-orphans
	NGINX_CONF=$(NGINX_CONF_DEV) LIBSQL_VALIDATOR_UPSTREAM=mylibsqladmin-webui-dev:8000 COMPOSE_BAKE=true $(COMPOSE) $(COMPOSE_FILES) $(COMPOSE_PROFILES_DEV) up

compose-dev/restartd:
	COMPOSE_BAKE=true $(COMPOSE) $(COMPOSE_FILES) $(COMPOSE_PROFILES_DEV) down --remove-orphans
	NGINX_CONF=$(NGINX_CONF_DEV) LIBSQL_VALIDATOR_UPSTREAM=mylibsqladmin-webui-dev:8000 COMPOSE_BAKE=true $(COMPOSE) $(COMPOSE_FILES) $(COMPOSE_PROFILES_DEV) up -d

compose-prod/restart:
	COMPOSE_BAKE=true $(COMPOSE) $(COMPOSE_FILES) $(COMPOSE_PROFILES_PROD) down --remove-orphans
	NGINX_CONF=$(NGINX_CONF_PROD) LIBSQL_VALIDATOR_UPSTREAM=mylibsqladmin-webui-prod:8000 COMPOSE_BAKE=true $(COMPOSE) $(COMPOSE_FILES) $(COMPOSE_PROFILES_PROD) up

compose-prod/restartd:
	COMPOSE_BAKE=true $(COMPOSE) $(COMPOSE_FILES) $(COMPOSE_PROFILES_PROD) down --remove-orphans
	NGINX_CONF=$(NGINX_CONF_PROD) LIBSQL_VALIDATOR_UPSTREAM=mylibsqladmin-webui-prod:8000 COMPOSE_BAKE=true $(COMPOSE) $(COMPOSE_FILES) $(COMPOSE_PROFILES_PROD) up -d

compose-dev/rebuild:
	COMPOSE_BAKE=true $(COMPOSE) $(COMPOSE_FILES) $(COMPOSE_PROFILES_DEV) down -v --remove-orphans
	COMPOSE_BAKE=true $(COMPOSE) $(COMPOSE_FILES) $(COMPOSE_PROFILES_DEV) build --no-cache

compose-prod/rebuild:
	COMPOSE_BAKE=true $(COMPOSE) $(COMPOSE_FILES) $(COMPOSE_PROFILES_PROD) down -v --remove-orphans
	COMPOSE_BAKE=true $(COMPOSE) $(COMPOSE_FILES) $(COMPOSE_PROFILES_PROD) build --no-cache
