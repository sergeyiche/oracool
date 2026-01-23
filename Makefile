.PHONY: help build up down restart logs shell test migrate

# Цвета для вывода
GREEN  := $(shell tput -Txterm setaf 2)
YELLOW := $(shell tput -Txterm setaf 3)
RESET  := $(shell tput -Txterm sgr0)

help: ## Показать эту справку
	@echo '${GREEN}Oracle Platform - Доступные команды:${RESET}'
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "  ${YELLOW}%-20s${RESET} %s\n", $$1, $$2}'

build: ## Собрать Docker образы
	docker compose build

up: ## Запустить все сервисы
	docker compose up -d
	@echo "${GREEN}✓ Сервисы запущены${RESET}"
	@echo "  App: http://localhost:8000"
	@echo "  PostgreSQL: localhost:5432"
	@echo "  Redis: localhost:6379"

down: ## Остановить все сервисы
	docker compose down
	@echo "${GREEN}✓ Сервисы остановлены${RESET}"

restart: down up ## Перезапустить все сервисы

logs: ## Показать логи (ctrl+c для выхода)
	docker compose logs -f

logs-app: ## Логи приложения
	docker compose logs -f app

logs-nginx: ## Логи Nginx
	docker compose logs -f nginx

logs-postgres: ## Логи PostgreSQL
	docker compose logs -f postgres

shell: ## Войти в контейнер приложения
	docker compose exec app sh

shell-postgres: ## Войти в PostgreSQL
	docker compose exec postgres psql -U oracool -d oracool

composer-install: ## Установить зависимости Composer
	docker compose exec app composer install

migrate: ## Применить миграции БД
	docker compose exec app php bin/console doctrine:migrations:migrate --no-interaction
	@echo "${GREEN}✓ Миграции применены${RESET}"

migrate-status: ## Статус миграций
	docker compose exec app php bin/console doctrine:migrations:status

db-create: ## Создать базу данных
	docker compose exec app php bin/console doctrine:database:create --if-not-exists
	@echo "${GREEN}✓ База данных создана${RESET}"

cache-clear: ## Очистить кеш Symfony
	docker compose exec app php bin/console cache:clear
	@echo "${GREEN}✓ Кеш очищен${RESET}"

test-structure: ## Тест структуры проекта
	docker compose exec app php tests/test_structure.php

test-ai: ## Тест AI сервисов (требует Ollama)
	docker compose exec app php tests/test_ai_services.php

console: ## Symfony console
	docker compose exec app php bin/console $(filter-out $@,$(MAKECMDGOALS))

ps: ## Показать запущенные контейнеры
	docker compose ps

stats: ## Показать использование ресурсов
	docker stats --no-stream

clean: ## Удалить все контейнеры и volumes
	docker compose down -v
	@echo "${YELLOW}⚠ Все данные удалены${RESET}"

install: build db-create migrate ## Полная установка (build + db + migrate)
	@echo "${GREEN}✓ Установка завершена${RESET}"
	@echo "${YELLOW}Запустите: make up${RESET}"

.DEFAULT_GOAL := help
%:
	@:
