.PHONY: help up down logs test migrate seed clean

help:
	@echo "KIVARA API - Makefile Commands"
	@echo ""
	@echo "Development:"
	@echo "  make up              - Start all containers"
	@echo "  make down            - Stop all containers"
	@echo "  make logs            - Show logs (all services)"
	@echo "  make logs-web        - Show web server logs"
	@echo "  make logs-worker     - Show worker logs"
	@echo ""
	@echo "Database:"
	@echo "  make migrate         - Run migrations"
	@echo "  make migrate-fresh   - Fresh migrations (WARNING: deletes data)"
	@echo "  make seed            - Seed database"
	@echo "  make tinker          - Open Laravel tinker REPL"
	@echo ""
	@echo "Testing:"
	@echo "  make test            - Run all tests"
	@echo "  make test-unit       - Run unit tests only"
	@echo "  make test-feature    - Run feature tests only"
	@echo "  make test-coverage   - Generate test coverage report"
	@echo ""
	@echo "Code Quality:"
	@echo "  make lint            - Run PHP linter"
	@echo "  make format          - Format code (PSR-12)"
	@echo "  make analyze         - Static analysis with PHPStan"
	@echo ""
	@echo "Docker:"
	@echo "  make build           - Build Docker images"
	@echo "  make shell           - Access web container shell"
	@echo "  make clean           - Remove volumes & data (WARNING)"

# Development
up:
	docker compose up -d

down:
	docker compose down

restart:
	docker compose restart

logs:
	docker compose logs -f

logs-web:
	docker compose logs -f web

logs-worker:
	docker compose logs -f worker

# Database
migrate:
	docker compose exec web php artisan migrate

migrate-fresh:
	docker compose exec web php artisan migrate:fresh

seed:
	docker compose exec web php artisan db:seed

tinker:
	docker compose exec web php artisan tinker

# Testing
test:
	docker compose exec web php artisan test

test-unit:
	docker compose exec web php artisan test tests/Unit

test-feature:
	docker compose exec web php artisan test tests/Feature

test-coverage:
	docker compose exec web php artisan test --coverage

# Code Quality
lint:
	docker compose exec web ./vendor/bin/pint --check

format:
	docker compose exec web ./vendor/bin/pint

analyze:
	docker compose exec web ./vendor/bin/phpstan analyse

# Docker
build:
	docker compose build

shell:
	docker compose exec web sh

clean:
	docker compose down -v
	rm -rf storage/logs/*
	echo "✅ Cleanup complete"

# Production
build-prod:
	docker build -f Dockerfile -t kivara-api:latest .

push-prod:
	docker tag kivara-api:latest your-registry/kivara-api:latest
	docker push your-registry/kivara-api:latest

# Utilities
install-deps:
	docker compose exec web composer install

update-deps:
	docker compose exec web composer update

optimize:
	docker compose exec web php artisan optimize

cache-clear:
	docker compose exec web php artisan cache:clear
	docker compose exec web php artisan view:clear
	docker compose exec web php artisan route:clear

artisan:
	docker compose exec web php artisan $(CMD)
