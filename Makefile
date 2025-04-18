DOCKERIGNORE_DEV = ./admin/.docker/.dockerignore.local
DOCKERIGNORE_PROD = ./admin/.docker/.dockerignore.prod
DOCKERIGNORE_TARGET_DIR = ./admin/.dockerignore

.PHONY: help compose-dev/up compose-prod/up compose-dev/down composer-prod/down compose-dev/restart compose-prod/restart compose-dev/rebuild compose-prod/rebuild

help:
	@echo "Usage:"
	@echo "  make compose-dev/up"
	@echo "  make compose-prod/up"
	@echo "  make compose-dev/down"
	@echo "  make composer-prod/down"
	@echo "  make compose-dev/restart"
	@echo "  make compose-prod/restart"
	@echo "  make compose-dev/rebuild"
	@echo "  make compose-prod/rebuild"

compose-dev/up:
	cp $(DOCKERIGNORE_DEV) $(DOCKERIGNORE_TARGET_DIR)
	COMPOSE_BAKE=true docker compose --profile development up

compose-prod/up:
	cp $(DOCKERIGNORE_PROD) $(DOCKERIGNORE_TARGET_DIR)
	COMPOSE_BAKE=true docker compose --profile production up

compose-dev/down:
	COMPOSE_BAKE=true docker compose --profile development down

compose-prod/down:
	COMPOSE_BAKE=true docker compose --profile production down

compose-dev/restart:
	cp $(DOCKERIGNORE_DEV) $(DOCKERIGNORE_TARGET_DIR)
	COMPOSE_BAKE=true docker compose --profile development down
	COMPOSE_BAKE=true docker compose --profile development up

compose-prod/restart:
	cp $(DOCKERIGNORE_PROD) $(DOCKERIGNORE_TARGET_DIR)
	COMPOSE_BAKE=true docker compose --profile production down
	COMPOSE_BAKE=true docker compose --profile production up

compose-dev/rebuild:
	cp $(DOCKERIGNORE_DEV) $(DOCKERIGNORE_TARGET_DIR)
	docker compose --profile development down -v
	COMPOSE_BAKE=true docker compose --profile development build --no-cache
	COMPOSE_BAKE=true docker compose --profile development up

compose-prod/rebuild:
	cp $(DOCKERIGNORE_PROD) $(DOCKERIGNORE_TARGET_DIR)
	docker compose --profile production down -v
	COMPOSE_BAKE=true docker compose --profile production build --no-cache
	COMPOSE_BAKE=true docker compose --profile production up

