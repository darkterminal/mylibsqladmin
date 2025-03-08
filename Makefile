DOCKERIGNORE_DEV = ./admin/.docker/.dockerignore.local
DOCKERIGNORE_PROD = ./admin/.docker/.dockerignore.prod
DOCKERIGNORE_TARGET_DIR = ./admin/.dockerignore

.PHONY: helpdocker-up-dev docker-up-prod docker-down-dev docker-down-prod

help:
	@echo "Usage:"
	@echo "  make docker-up-dev"
	@echo "  make docker-up-prod"
	@echo "  make docker-down-dev"
	@echo "  make docker-down-prod"

docker-up-dev:
	cp $(DOCKERIGNORE_DEV) $(DOCKERIGNORE_TARGET_DIR)
	COMPOSE_BAKE=true docker compose --profile development up

docker-up-prod:
	cp $(DOCKERIGNORE_PROD) $(DOCKERIGNORE_TARGET_DIR)
	COMPOSE_BAKE=true docker compose --profile production up

docker-down-dev:
	COMPOSE_BAKE=true docker compose --profile development down

docker-down-prod:
	COMPOSE_BAKE=true docker compose --profile production down
