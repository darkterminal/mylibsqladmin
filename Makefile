DOCKERIGNORE_DEV = ./admin/.docker/.dockerignore.local
DOCKERIGNORE_PROD = ./admin/.docker/.dockerignore.prod
DOCKERIGNORE_TARGET_DIR = ./admin/.dockerignore

.PHONY: help compose-dev/up compose-prod/up compose-dev/down composer-prod/down

help:
	@echo "Usage:"
	@echo "  make compose-dev/up"
	@echo "  make compose-prod/up"
	@echo "  make compose-dev/down"
	@echo "  make composer-prod/down"

compose-dev/up:
	cp $(DOCKERIGNORE_DEV) $(DOCKERIGNORE_TARGET_DIR)
	COMPOSE_BAKE=true docker compose --profile development up

compose-prod/up:
	cp $(DOCKERIGNORE_PROD) $(DOCKERIGNORE_TARGET_DIR)
	COMPOSE_BAKE=true docker compose --profile production up

compose-dev/down:
	COMPOSE_BAKE=true docker compose --profile development down

composer-prod/down:
	COMPOSE_BAKE=true docker compose --profile production down
