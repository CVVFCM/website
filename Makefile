DOCKER_COMPOSE = EXTERNAL_USER_ID=$(shell id -u) docker compose

.PHONY: run clean_admin_assets clean ps build up down cli first_run logs reset reset-test test cc css hadolint cs stylelint psalm psalm_strict

run: .configured up

clean_admin_assets:
	@rm -rf public/build/admin

clean: clean_admin_assets
	@$(DOCKER_COMPOSE) down -v --remove-orphans
	@rm -rf \
		.configured \
		assets/admin/node_modules \
		infra/tls \
		data/weather/ml \
		ml/.venv \
		public/assets \
		public/build \
		public/uploads \
		vendor \
		var/cache \
		var/indexes \
		var/log

ps:
	@$(DOCKER_COMPOSE) ps

build:
	@$(DOCKER_COMPOSE) build

up:
	@$(DOCKER_COMPOSE) up -d --remove-orphans --wait php --wait consumer

down:
	@$(DOCKER_COMPOSE) down --remove-orphans

cli:
	@$(DOCKER_COMPOSE) exec php bash

ml_cli:
	@$(DOCKER_COMPOSE) run --rm ml bash

.configured:
	test -f $@ || make first_run
	@touch $@

first_run: infra/tls/cert.pem build var/ data/weather/ml vendor/ up reset public/build/admin/manifest.json data/weather/ml/model_pytorch.onnx

reset:
	@$(DOCKER_COMPOSE) exec php composer reset

reset-test:
	@$(DOCKER_COMPOSE) exec php composer reset-test

test:
	@$(DOCKER_COMPOSE) exec php ./vendor/bin/phpunit --colors=always --testdox

cc: ## Clear Symfony cache (website + admin)
	@$(DOCKER_COMPOSE) exec php bin/websiteconsole cache:clear
	@$(DOCKER_COMPOSE) exec php bin/adminconsole cache:clear

logs: ## Show live logs, pass the parameter "c=" to specify a container, example: make logs c=php
	@$(eval c ?= 'php')
	@$(eval tail ?= 100)
	@$(DOCKER_COMPOSE) logs $(c) --tail=$(tail) --follow

hadolint: ## Lint Dockerfile
	@docker pull hadolint/hadolint
	@docker run --rm -i hadolint/hadolint hadolint - < Dockerfile
	@docker run --rm -i hadolint/hadolint hadolint - < ml/Dockerfile

cs: ## Fix code style
	@$(DOCKER_COMPOSE) exec -T php ./vendor/bin/php-cs-fixer fix
	@$(DOCKER_COMPOSE) exec -T php ./vendor/bin/twig-cs-fixer fix templates

stylelint: node_modules/ ## Lint website CSS (pass fix=1 to auto-fix)
	@docker run --rm -v $(PWD):/app -w /app node:22-bookworm-slim sh -c "npm install --no-audit --no-fund && { npx stylelint 'assets/website/styles/**/*.css' $(if $(fix),--fix,); rc=\$$?; } ; chown -R $(shell id -u):$(shell id -g) node_modules ; exit \$$rc"

psalm: ## Run static analysis
	@$(DOCKER_COMPOSE) exec php ./vendor/bin/psalm --no-diff

psalm_strict: ## Run static analysis (strict mode)
	@$(DOCKER_COMPOSE) exec php ./vendor/bin/psalm --show-info=true --no-diff

ml_cs: ## Fix code style in ML code
	@$(DOCKER_COMPOSE) run --rm ml ruff check

var/:
	@mkdir -p var/cache var/indexes var/log

node_modules/:
	@npm i

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

data/weather/ml:
	@mkdir -p data/weather/ml

data/weather/ml/ml.csv: data/weather/ml
	@$(DOCKER_COMPOSE) exec php bin/console app:export:weather-for-ml > data/weather/ml/ml.csv

ml/.venv: ml/pyproject.toml
	@$(DOCKER_COMPOSE) run --rm ml poetry install --only main

data/weather/ml/model_pytorch.onnx: data/weather/ml/ml.csv ml/.venv
	@$(DOCKER_COMPOSE) run --rm ml python src/train_forecast_model.py

rebuild_model:
	@rm -f data/weather/ml/model_pytorch.onnx
	@make data/weather/ml/model_pytorch.onnx

view_model_accuracy: data/weather/ml/model_pytorch.onnx
	@$(DOCKER_COMPOSE) run --rm ml python src/view_model_accuracy.py
