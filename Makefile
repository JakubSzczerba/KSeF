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
	docker-compose exec php bash

tests:
	docker compose exec php php bin/phpunit

composer install:
	docker compose exec php composer install

fixtures:
	docker compose exec php php bin/console doctrine:fixtures:load --no-interaction

cc:
	docker compose exec php php bin/console cache:clear

migrate:
	docker compose exec php php bin/console doctrine:migrations:migrate --no-interaction

migration-status:
	docker compose exec php php bin/console doctrine:migrations:status

make-migration:
	docker compose exec php php bin/console make:migration

phpstan:
	docker compose exec php ./vendor/bin/phpstan analyse -l 10 -c phpstan.neon --memory-limit=512M

csfixer:
	docker compose exec php ./vendor/bin/php-cs-fixer fix --dry-run --dif

#debug
challenge:
	docker compose exec php php bin/console ksef:test --step=challenge

auth:
	docker compose exec php php bin/console ksef:test --step=auth
