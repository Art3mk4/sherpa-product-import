# build-arg из compose/CI (в CI — secrets.GITHUB_TOKEN джобы); при локальной сборке пуст.
# Глобальный ARG до FROM виден только в инструкциях FROM — в стадии vendor переобъявлен.
ARG GITHUB_TOKEN=""

# ---------- base ----------
FROM php:8.3-fpm-alpine AS base
COPY --from=mlocati/php-extension-installer:2 /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions pdo_pgsql amqp zip opcache intl
COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-app.ini
COPY docker/php/www.conf /usr/local/etc/php-fpm.d/zz-app.conf
WORKDIR /var/www

# ---------- composer deps (prod) ----------
FROM composer:2 AS vendor
ARG GITHUB_TOKEN
# api.github.com (dist-зипы symfony/* и др.) с IP CI-раннеров флакает двумя способами:
#  - curl 28 (connect-таймаут, 0 байт) — лечится COMPOSER_CONNECT_TIMEOUT=30;
#  - curl 56 «Connection died» на середине передачи — composer ретраит URL 5 раз,
#    но при троттлинге общего IP раннера (60 запр/час без авторизации) этого мало.
# Меры: (1) в CI сюда передаётся GITHUB_TOKEN → запросы к api.github.com авторизованы
# (auth.json ниже, в финальные образы не попадает — из стадии копируется только /app);
# (2) ретрай на весь install — кэш пакетов живёт внутри RUN, повтор до-качивает только упавшее.
ENV COMPOSER_CONNECT_TIMEOUT=30
WORKDIR /app
COPY backend/composer.json backend/composer.lock ./
# composer-образ не содержит ext-pdo_pgsql/ext-amqp (они есть в runtime-стадии base) —
# поэтому здесь игнорируем, реальная проверка платформы происходит в базовом образе
RUN if [ -n "$GITHUB_TOKEN" ]; then composer config --global github-oauth.github.com "$GITHUB_TOKEN"; fi
RUN for i in 1 2 3; do \
      composer install --no-dev --no-scripts --no-autoloader --prefer-dist \
        --ignore-platform-req=ext-amqp --ignore-platform-req=ext-pdo_pgsql && break; \
      [ "$i" -eq 3 ] && exit 1; \
      echo "composer install failed (attempt $i/3), retry in 15s"; sleep 15; \
    done
COPY backend/ ./
RUN composer dump-autoload --optimize --classmap-authoritative --no-dev

# ---------- composer deps (dev) ----------
FROM vendor AS vendor-dev
RUN for i in 1 2 3; do \
      composer install --no-scripts --prefer-dist \
        --ignore-platform-req=ext-amqp --ignore-platform-req=ext-pdo_pgsql \
        && composer dump-autoload --optimize && break; \
      [ "$i" -eq 3 ] && exit 1; \
      echo "composer install (dev) failed (attempt $i/3), retry in 15s"; sleep 15; \
    done

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
