DOCKER_COMPOSE = EXTERNAL_USER_ID=$(shell id -u) docker compose

.PHONY: run clean_admin_assets clean ps build up down cli first_run logs reset cc hadolint cs psalm psalm_strict

run: .configured up

clean_admin_assets:
	@rm -rf public/build/admin

clean: clean_admin_assets
	@$(DOCKER_COMPOSE) down -v --remove-orphans
	@rm -rf .configured assets/admin/node_modules infra/tls public/assets public/build public/uploads vendor var/cache var/indexes var/log

ps:
	@$(DOCKER_COMPOSE) ps

build:
	@$(DOCKER_COMPOSE) build

up:
	@$(DOCKER_COMPOSE) up -d --remove-orphans

down:
	@$(DOCKER_COMPOSE) down --remove-orphans

cli:
	@$(DOCKER_COMPOSE) exec php bash

.configured:
	test -f $@ || make first_run
	@touch $@

first_run: infra/tls/cert.pem build vendor/ up reset public/build/admin/manifest.json

reset:
	@$(DOCKER_COMPOSE) exec php composer reset

cc:
	@$(DOCKER_COMPOSE) exec php bin/websiteconsole cache:clear
	@$(DOCKER_COMPOSE) exec php bin/adminconsole cache:clear

logs: ## Show live logs, pass the parameter "c=" to specify a container, example: make logs c=php
	@$(eval c ?= 'php')
	@$(eval tail ?= 100)
	@$(DOCKER_COMPOSE) logs $(c) --tail=$(tail) --follow

hadolint: ## Lint Dockerfile
	@docker pull hadolint/hadolint
	@docker run --rm -i hadolint/hadolint hadolint - < Dockerfile

cs: ## Fix code style
	@docker compose exec -T php ./vendor/bin/php-cs-fixer fix
	@docker compose exec -T php ./vendor/bin/twig-cs-fixer fix templates

psalm: ## Run static analysis
	@$(DOCKER_COMPOSE) exec php ./vendor/bin/psalm --no-diff

psalm_strict: ## Run static analysis (strict mode)
	@$(DOCKER_COMPOSE) exec php ./vendor/bin/psalm --show-info=true --no-diff

vendor/:
	@$(DOCKER_COMPOSE) run --rm php composer install

public/build/admin/manifest.json: assets/admin/package.json assets/admin/package-lock.json assets/admin/app.js
	@docker run --rm -v $(PWD):/app -w /app/assets/admin node:22-bookworm-slim npm install
	@docker run --rm -v $(PWD):/app -w /app/assets/admin node:22-bookworm-slim npm run build
	@docker run --rm -v $(PWD):/app -w /app/assets/admin node:22-bookworm-slim chown -R $(shell id -u):$(shell id -g) .
	@docker run --rm -v $(PWD):/app -w /app node:22-bookworm-slim chown -R $(shell id -u):$(shell id -g) public/build

infra/tls/cert.pem:
	@mkdir -p infra/tls
	@mkcert -key-file infra/tls/key.pem -cert-file infra/tls/cert.pem localhost
