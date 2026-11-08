# ---------- base ----------
FROM php:8.3-fpm-alpine AS base
COPY --from=mlocati/php-extension-installer:2 /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions pdo_pgsql amqp zip opcache intl
COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-app.ini
COPY docker/php/www.conf /usr/local/etc/php-fpm.d/zz-app.conf
WORKDIR /var/www

# ---------- composer deps (prod) ----------
FROM composer:2 AS vendor
WORKDIR /app
COPY backend/composer.json backend/composer.lock ./
# composer-образ не содержит ext-pdo_pgsql/ext-amqp (они есть в runtime-стадии base) —
# поэтому здесь игнорируем, реальная проверка платформы происходит в базовом образе
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist \
    --ignore-platform-req=ext-amqp --ignore-platform-req=ext-pdo_pgsql
COPY backend/ ./
RUN composer dump-autoload --optimize --classmap-authoritative --no-dev

# ---------- composer deps (dev) ----------
FROM vendor AS vendor-dev
RUN composer install --no-scripts --prefer-dist \
    --ignore-platform-req=ext-amqp --ignore-platform-req=ext-pdo_pgsql \
    && composer dump-autoload --optimize

# ---------- prod ----------
FROM base AS prod
COPY --from=vendor /app /var/www
RUN mkdir -p storage/images storage/imports var/cache && chown -R www-data:www-data storage var
USER www-data

# ---------- dev (для тестов/phpstan) ----------
FROM base AS dev
COPY --from=vendor-dev /app /var/www
RUN mkdir -p storage/images storage/imports var/cache && chown -R www-data:www-data storage var
USER www-data
