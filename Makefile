.DEFAULT_GOAL := help

COMPOSE := docker compose
COMPOSE_DEV := docker compose -f compose.yaml -f compose.dev.yaml
TRIVY := docker run --rm -v /var/run/docker.sock:/var/run/docker.sock -v trivy-cache:/root/.cache/ aquasec/trivy:0.74.0
export UID := $(shell id -u)
export GID := $(shell id -g)

help: ## List available targets
	@grep -E '^[a-zA-Z_-]+:.*?## ' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-12s\033[0m %s\n", $$1, $$2}'

up: ## Build and start the production-shaped stack
	$(COMPOSE) up -d --build --wait

dev: ## Start the stack with the source mounted (APP_ENV=dev)
	$(COMPOSE_DEV) up -d --build --wait

down: ## Stop the stack, keep the database volume
	$(COMPOSE_DEV) down --remove-orphans

logs: ## Follow the logs of all services
	$(COMPOSE) logs -f --tail=100

sh: ## Open a shell in the dev php container
	$(COMPOSE_DEV) exec php sh

build-prod: ## Build the production image as fizzbuzz-api:local
	docker build --target prod -t fizzbuzz-api:local .

audit: build-prod ## Scan the three images for HIGH and CRITICAL vulnerabilities
	@for image in fizzbuzz-api:local nginx:1.30.5-alpine-slim mariadb:11.8.9; do \
		echo "== $$image"; \
		$(TRIVY) image --quiet --severity HIGH,CRITICAL --ignore-unfixed --exit-code 1 --skip-files usr/local/bin/gosu $$image || exit 1; \
	done

.PHONY: help up dev down logs sh build-prod audit
