.DEFAULT_GOAL := help

COMPOSE := docker compose
COMPOSE_DEV := docker compose -f compose.yaml -f compose.dev.yaml
PHP := $(COMPOSE_DEV) run --rm --no-deps php
NGINX_IMAGE := nginx@sha256:f56e56413ea294b1532917c0b36fc676725f0af2ac766989f06f4b69cf83b12b
MARIADB_IMAGE := mariadb@sha256:8b5f33ebd85d1775657e974ed10434128bb493c80e826ceaa54074fd1a92a112
K6 := docker run --rm --network host -v $(CURDIR)/tests/load:/scripts:ro grafana/k6:2.2.0
TRIVY := docker run --rm -v /var/run/docker.sock:/var/run/docker.sock -v trivy-cache:/root/.cache/ aquasec/trivy:0.74.0
export UID := $(shell id -u)
export GID := $(shell id -g)

# STACK names the containers after what is running: fizzbuzz-prod-php, fizzbuzz-dev-php, fizzbuzz-loadtest-php
up migrate: export STACK := prod
dev sh install lint fix test: export STACK := dev
load-test: export STACK := loadtest

help: ## List available targets
	@grep -E '^[a-zA-Z_-]+:.*?## ' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-12s\033[0m %s\n", $$1, $$2}'

up: ## Build and start the production-shaped stack, then apply the migrations
	$(COMPOSE) up -d --build --wait
	$(MAKE) migrate

dev: ## Start the stack with the source mounted (APP_ENV=dev)
	$(COMPOSE_DEV) up -d --build --wait

down: ## Stop the stack, keep the database volume
	$(COMPOSE_DEV) down --remove-orphans

migrate: ## Apply the database migrations with the production image
	$(COMPOSE) run --rm php php -d mysqlnd.net_read_timeout=3600 bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration

logs: ## Follow the logs of all services
	$(COMPOSE) logs -f --tail=100

sh: ## Open a shell in the dev php container
	$(COMPOSE_DEV) exec php sh

install: ## Install composer dependencies in the dev container
	$(PHP) composer install --no-interaction --no-progress

vendor/autoload.php: composer.lock
	$(MAKE) install

lint: vendor/autoload.php ## Check coding standard and run static analysis
	$(PHP) vendor/bin/php-cs-fixer check --diff
	$(PHP) sh -c 'bin/console cache:warmup --quiet && vendor/bin/phpstan analyse --no-progress --memory-limit=512M'

fix: vendor/autoload.php ## Fix coding standard violations
	$(PHP) vendor/bin/php-cs-fixer fix

test: vendor/autoload.php ## Migrate the test database and run the test suite
	$(COMPOSE_DEV) up -d --wait db
	$(PHP) sh -c 'bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration --env=test && vendor/bin/phpunit'

load-test: ## Measure capacity with the per-address limits lifted (LIMIT, VUS, DURATION), then run `make up`
	$(COMPOSE) -f compose.yaml -f compose.loadtest.yaml up -d --build --wait
	$(K6) run -e LIMIT=$(or $(LIMIT),100) -e VUS=$(or $(VUS),50) -e DURATION=$(or $(DURATION),30s) /scripts/fizzbuzz.js

load-test-limited: up ## Same traffic against the real limits: most requests must be answered 429
	$(K6) run -e LIMIT=$(or $(LIMIT),100) -e VUS=$(or $(VUS),50) -e DURATION=$(or $(DURATION),30s) /scripts/fizzbuzz.js

build-prod: ## Build the production image as fizzbuzz-api:local
	docker build --target prod -t fizzbuzz-api:local .

audit: build-prod ## Scan the three images for HIGH and CRITICAL vulnerabilities
	@for image in fizzbuzz-api:local $(NGINX_IMAGE) $(MARIADB_IMAGE); do \
		echo "== $$image"; \
		$(TRIVY) image --quiet --severity HIGH,CRITICAL --ignore-unfixed --exit-code 1 --skip-files usr/local/bin/gosu $$image || exit 1; \
	done

.PHONY: help up dev down migrate logs sh install lint fix test load-test load-test-limited build-prod audit
