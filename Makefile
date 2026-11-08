.PHONY: init up down build logs migrate fixtures test stan cs cs-fix e2e sh
init:      ; test -f .env || cp .env.example .env
up: init   ; docker compose up -d --build --wait
down:      ; docker compose down
build:     ; docker compose build
logs:      ; docker compose logs -f app worker
migrate:   ; docker compose run --rm migrate
fixtures:  ; docker compose exec app php bin/console app:fixtures:load --no-interaction
test:      ; docker compose exec -e APP_ENV=test app vendor/bin/phpunit
stan:      ; docker compose exec app vendor/bin/phpstan analyse --memory-limit=512M
cs:        ; docker compose exec app vendor/bin/php-cs-fixer fix --dry-run --diff --using-cache=no
cs-fix:    ; docker compose exec app vendor/bin/php-cs-fixer fix
e2e:
	docker compose exec db sh -c 'psql -U "$$POSTGRES_USER" -d "$$POSTGRES_DB" -c "TRUNCATE products, product_attributes, product_images, import_jobs RESTART IDENTITY CASCADE"'
	docker compose exec app sh -c 'rm -rf var/cache/ratelimit'   # сброс rate-limit окна, иначе импорт из e2e получит 429
	cd frontend && npx playwright test
sh:        ; docker compose exec app sh
