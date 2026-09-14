SHELL := /bin/bash
.DEFAULT_GOAL := help

GITLEAKS := zricethezav/gitleaks:v8.18.4

.PHONY: help setup up down restart ps logs migrate seed build test types lint e2e secrets verify verify-clean clean

help: ## Muestra los comandos disponibles
	@echo ""
	@echo "  Reformas Leads"
	@echo ""
	@grep -E '^[a-zA-Z0-9_-]+:.*?## .*$$' $(MAKEFILE_LIST) \
		| awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-14s\033[0m %s\n", $$1, $$2}'
	@echo ""

# ---------------------------------------------------------------- entorno

setup: ## Primera vez: dependencias, .env (solo si no existe) y claves vacías
	composer install
	npm ci
	@if [ ! -f .env ]; then cp .env.example .env && echo "  .env creado desde .env.example"; fi
	@if ! grep -qE '^APP_KEY=.+' .env; then php artisan key:generate; fi
	@if ! grep -qE '^N8N_ENCRYPTION_KEY=.+' .env; then \
		sed -i "s/^N8N_ENCRYPTION_KEY=.*/N8N_ENCRYPTION_KEY=$$(openssl rand -hex 32)/" .env \
		&& echo "  N8N_ENCRYPTION_KEY generada"; fi
	npm run build

up: ## Levanta los servicios (app, queue, db, n8n) y aplica las migraciones
	docker compose up -d --build --wait
	docker compose exec -T app php artisan migrate --force
	@echo ""
	@echo "  app    http://127.0.0.1:8010"
	@echo "  n8n    http://localhost:5678"

down: ## Detiene los servicios (conserva los datos)
	docker compose down

restart: down up ## Reinicia los servicios

ps: ## Estado de los servicios
	docker compose ps

logs: ## Sigue los registros de todos los servicios
	docker compose logs -f

migrate: ## Aplica las migraciones pendientes
	docker compose exec -T app php artisan migrate --force

seed: ## Siembra el usuario de prueba (idempotente)
	docker compose exec -T app php artisan db:seed --force

build: ## Compila los assets del frontend
	npm run build

# ---------------------------------------------------------------- checks

test: ## Pint, PHPStan y Pest (sin base de datos real)
	composer test

types: ## Regenera los tipos de rutas y verifica TypeScript
	npm run types:check

lint: ## Lint y formato del frontend
	npm run check

e2e: ## Playwright contra los contenedores (requiere make up)
	npm run test:e2e

secrets: ## Busca credenciales filtradas en todo el historial
	docker run --rm -v "$$PWD:/repo:ro" $(GITLEAKS) detect --source=/repo --redact

# ---------------------------------------------------------------- verify

verify: ## Corre TODO lo que corre el CI (usar antes de cada push)
	@echo "==> 1/6 secrets"; $(MAKE) --no-print-directory secrets
	@echo "==> 2/6 test";    $(MAKE) --no-print-directory test
	@echo "==> 3/6 types";   $(MAKE) --no-print-directory types
	@echo "==> 4/6 lint";    $(MAKE) --no-print-directory lint
	@echo "==> 5/6 build";   $(MAKE) --no-print-directory build
	@echo "==> 6/6 e2e";     $(MAKE) --no-print-directory up e2e
	@echo ""
	@echo "  Todo en verde."

# No borra el volumen de la base: los datos locales se conservan. El caso de una base
# recién creada solo lo cubre el CI.
verify-clean: ## Como verify, pero borrando antes lo generado (simula un clon limpio)
	$(MAKE) --no-print-directory clean
	docker compose rm -sf app queue
	-docker image rm reformas-leads-app:dev
	composer install
	npm ci
	$(MAKE) --no-print-directory verify

clean: ## Borra dependencias, assets y código generado (no toca .env ni la base)
	rm -rf vendor node_modules public/build public/hot
	rm -rf resources/js/actions resources/js/routes resources/js/wayfinder
	rm -rf playwright-report test-results
