.PHONY: start up down rebuild logs tests cc router phpstan csfixer

start: up composer
	@echo "✅ Project is ready!"

up:
	docker compose up --build -d

down:
	docker compose down

rebuild:
	docker compose build --no-cache

logs:
	docker compose logs -f

php bash:
	docker compose exec php bash

tests:
	docker compose exec php php bin/phpunit

composer install:
	docker compose exec php composer install

cc:
	docker compose exec php php bin/console cache:clear

router:
	docker compose exec php php bin/console debug:router

phpstan:
	docker compose exec php ./vendor/bin/phpstan analyse -l 8 -c phpstan.neon --memory-limit=512M

csfixer:
	docker compose exec php ./vendor/bin/php-cs-fixer fix --dry-run --dif
