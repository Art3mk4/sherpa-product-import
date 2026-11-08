# Импорт/Экспорт товаров

[![CI](https://github.com/Art3mk4/sherpa-product-import/actions/workflows/ci.yml/badge.svg)](https://github.com/Art3mk4/sherpa-product-import/actions/workflows/ci.yml)

Тестовое задание: заполнение базы товаров импортом из `.xlsx` — **Slim 4 + Doctrine ORM** на бэкенде, **Angular 19 + NgRx + Angular Material** на фронте, **PostgreSQL 16**, асинхронный импорт через **RabbitMQ** (Symfony Messenger).

## Быстрый старт

Требования: Docker 24+, Compose v2, `make` (для e2e — Node 22).

```bash
make up        # поднимет db, rabbitmq, миграции, app (php-fpm), worker и nginx
```

Открыть:

| Что | URL |
|---|---|
| Приложение | http://localhost:8080 |
| Swagger UI | http://localhost:8080/api/docs |
| RabbitMQ UI | http://localhost:15672 (app / app_secret) |

Логин по умолчанию: `admin@example.com` / `admin123` (из `.env`).

Демо без ручного UI — curl-сценарий импорта:

```bash
TOKEN=$(curl -s localhost:8080/api/auth/login -H 'Content-Type: application/json' \
  -d '{"email":"admin@example.com","password":"admin123"}' | jq -r .token)
JOB=$(curl -s -H "Authorization: Bearer $TOKEN" \
  -F file=@backend/tests/Fixtures/import_example.xlsx localhost:8080/api/imports | jq -r .id)
watch -n1 "curl -s -H \"Authorization: Bearer $TOKEN\" localhost:8080/api/imports/$JOB \
  | jq '{status, processedRows, totalRows, successCount, errorCount}'"
```

## Архитектура

```
                ┌──────────── nginx ────────────┐
   браузер ───► │  статика Angular (dist)       │
                │  /storage/images/*            │ ◄── volume storage (ro)
                │  /api/*  ── fastcgi ──► php-fpm (app)
                └───────────────────────────────┘
                        │                       │
                  PostgreSQL 16            RabbitMQ 3.13
                        ▲                       │
                        │      symfony/messenger│
                ┌───────┴─────── worker (php cli, тот же образ) ───┐
                │ XlsxReader (openspout, потоково)                  │
                │ RowMapper → RowValidator → ImportReport           │
                │ ImageDownloader (параллельно, кэш sha1)           │
                │ DBAL: INSERT … ON CONFLICT (upsert)               │
                └───────────────────────────────────────────────────┘
```

Поток импорта:

1. `POST /api/imports` (multipart) → валидация файла (расширение/MIME/структура zip/размер) →
   файл в `storage/imports/{uuid}.xlsx` → запись `import_jobs` → сообщение в очередь → **202 {id}**.
2. Воркер: первый проход считает строки (для прогресс-бара), затем обрабатывает постранично:
   валидация строки (ошибки — в отчёт, не прерывают), скачивание картинок **вне транзакции**,
   атомарная запись товара (upsert по `external_code` + полная замена атрибутов + синхронизация
   изображений). Прогресс обновляется каждые 5 строк.
3. Фронт опрашивает `GET /api/imports/{id}` раз в секунду: прогресс-бар, счётчики, отчёт
   об ошибках/предупреждениях.

Ключевые решения:

- **PostgreSQL**: `ON CONFLICT … RETURNING` для атомарного upsert; регистрозависимые строки —
  коды `AN-AZx…` и `an-azx…` остаются разными (в MySQL с `*_ci` коллацией они бы склеились).
- **Запись импорта через DBAL, не ORM**: долгоживущий воркер после исключения в ORM-транзакции
  терял бы `EntityManager` до конца процесса; DBAL-соединение продолжает работать. ORM — чтение и CRUD API.
- **Картинки**: один URL качается один раз (имя `sha1(url).ext`, шардирование `images/ab/…`),
  упавшие — warning в отчёте, строка всё равно импортируется; запись атомарна (`*.tmp` → rename).
- **Колонки со ссылками** («Ссылки на фото», «Ссылка на упаковку») сохраняются и как атрибуты
  (буквально по ТЗ — все столбцы «Доп. поле»), и как `product_images`.
- **Скидка** `(цена − закупка)/цена × 100` может быть отрицательной — это не ошибка.

## Структура

```
backend/                Slim 4: config/ (DI, routes), src/ (Controller, Service, Repository, Entity…)
  src/Service/Import/   сердце импорта: UploadValidator, XlsxReader, RowMapper, RowValidator,
                        PriceParser, DiscountCalculator, ImageDownloader, ImportService, ImportReport
  migrations/           одна миграция: products, product_attributes, product_images, import_jobs
  openapi.yaml          OpenAPI 3.0.3 (Swagger UI на /api/docs)
frontend/               Angular 19 standalone: models/, core/ (services, interceptors, guard),
                        store/products/ (NgRx), features/ (login, import, список, карточка)
e2e/                    Playwright: 5 сценариев (редирект, импорт, поиск/фильтр/пагинация, карточка, .txt)
docker/                 multi-stage Dockerfile для php (base/vendor/prod/dev) и nginx (Angular build → nginx)
```

## Makefile

| Команда | Что делает |
|---|---|
| `make up` | собрать и поднять стек (`docker compose up -d --build --wait`) |
| `make down` | остановить |
| `make fixtures` | 30 товаров с атрибутами и картинками (сидеры для всех таблиц) |
| `make test` | PHPUnit (unit + integration + api) в контейнере |
| `make stan` | PHPStan level 6 |
| `make cs` / `make cs-fix` | PHP CS Fixer (проверка / исправление) |
| `make e2e` | чистая БД + Playwright против docker-стека |
| `make logs` | логи app и worker |

## Переменные окружения

Все переменные с комментариями — в [`.env.example`](.env.example): БД, RabbitMQ, JWT, креды
админа, лимиты импорта (размер файла, rate limit, таймауты/размер/параллельность скачивания).
Особенности:

- `APP_TARGET=prod` — соберёт прод-образы (без dev-зависимостей, opcache без ревалидации).
- `DOCKER_DNS` — DNS для исходящих запросов контейнеров (скачивание картинок). По умолчанию
  `1.1.1.1`; если сеть блокирует публичный UDP/53, укажите локальный резолвер.
- В паролях `.env` не используйте `$` — compose интерполирует.

## API

Полная спека — `backend/openapi.yaml` (Swagger UI: `/api/docs`).

| Метод | Путь | Описание |
|---|---|---|
| GET | `/api/health` | health check (db + broker), без авторизации |
| POST | `/api/auth/login` | JWT по email/паролю (один админ из `.env`) |
| GET | `/api/products?page=&limit=&name=&price_min=&price_max=` | список: серверная пагинация, поиск, фильтр |
| POST/PUT/DELETE | `/api/products[/{id}]` | CRUD |
| POST | `/api/imports` | загрузка xlsx (multipart `file`), rate limit 5/мин |
| GET | `/api/imports/{id}`, `/api/imports/latest` | статус/прогресс/отчёт задачи |

Ошибки — единый JSON `{"error": {"code", "message", "details"}}`; 422 — валидация,
401 — нет токена, 429 — rate limit (заголовок `Retry-After`), 409 — занятый `external_code`.

## Тесты

- **PHPUnit (50)**: unit (PriceParser, DiscountCalculator, RowMapper, RowValidator, UploadValidator),
  integration (репозитории: CRUD/фильтры/upsert/sync; ImportService: реальный `import_example.xlsx`
  → 40 товаров, повтор → без дублей, битая строка → отчёт, нет столбцов → failed),
  api (in-process: 401/422/404/409/429, структура data/meta, сообщение в InMemory-транспорте).
  Тесты не ходят в интернет — HTTP-клиент замокан (`MockHttpClient`).
- **Playwright (5 сценариев)**: редирект неавторизованного, полный импорт с ожиданием «Завершён»,
  поиск «легинсы» + фильтр цены + пагинация, карточка (атрибуты/изображения), загрузка `.txt`.
- **CI**: GitHub Actions прогоняет все проверки при каждом push — см. раздел [CI (GitHub Actions)](#ci-github-actions).

## CI (GitHub Actions)

Воркфлоу [`.github/workflows/ci.yml`](.github/workflows/ci.yml) запускается при каждом `push` и `pull_request`
и закрывает требование ТЗ с запасом — прогоняются и тесты, и сборка образов:

| Job | Что делает |
|---|---|
| **backend** | postgres-сервис (16) → composer install → БД `app_test` → php-cs-fixer (dry-run) → PHPStan → PHPUnit |
| **frontend** | `npm ci` + production build (Node 22) |
| **e2e** | `needs: backend, frontend` — полный `docker compose up -d --build --wait` + Playwright; при падении прикладываются логи compose и артефакт `playwright-report` (7 дней) |
| **docker** | buildx-сборка php (`target=prod`) и nginx образов — проверка, что прод-образы собираются |

Локальный эквивалент полного CI перед пушем:

```bash
make up && make test && make stan && make cs && make e2e
```

Результаты — на вкладке **Actions** репозитория; статус последнего прогона — бейдж в шапке этого README.

## Соответствие ТЗ

| Требование | Где |
|---|---|
| Таблицы products / product_attributes / product_images | `backend/migrations/Version20261101000000.php` |
| discount по формуле, атрибуты из «Доп. поле», скачивание изображений | `src/Service/Import/*` |
| Асинхронный импорт Messenger + RabbitMQ | `ImportService`, `WorkerCommand`, `docker-compose.yml` |
| Ошибки строк не прерывают, отчёт клиенту | `ImportReport`, `GET /api/imports/{id}` |
| Повторный импорт — upsert без дублей | `ProductRepository::upsertFromImport` (`ON CONFLICT`) |
| Серверная пагинация, поиск/фильтры | `ProductRepository::paginate`, `ProductFilter` |
| NgRx (actions/effects/selectors, async pipe) | `frontend/src/app/store/products/`, список |
| Прогресс импорта + отчёт на карточке | `shared/import-status`, `features/products/product-card` |
| Service-слой, Repository, тонкие контроллеры, транзакция на строку | `src/Service`, `src/Repository`, `src/Controller` |
| Валидация файла MIME/размер/расширение | `UploadValidator` (+ структурная проверка zip) |
| Angular-сервисы, модели в `models/`, `app.routes.ts`, strict TS | `frontend/src/app/*`, `tsconfig.json` |
| Docker: app/db/nginx/rabbitmq, multi-stage, `.env.example` | `docker-compose.yml`, `docker/*` |
| Rate limiting, health check | `RateLimitMiddleware`, `HealthController` |
| Fixtures, CRUD-тесты, e2e | `ProductFixtures`, `tests/`, `frontend/e2e/` |
| README, Makefile, Swagger | этот файл, `Makefile`, `openapi.yaml` |
| UI-кит | Angular Material |
| HTTP-interceptor (auth + 401/500/429) | `core/interceptors/*` |
| Lazy loading | `loadComponent` в `app.routes.ts` |
| Login + guard + JWT | `features/auth`, `core/guards/auth.guard.ts` |
| PHPStan (level 6) + CS Fixer + GitHub Actions | `phpstan.neon`, `.php-cs-fixer.php`, воркфлоу — раздел [CI (GitHub Actions)](#ci-github-actions) |

Примечание: требование ТЗ «CanActivate» реализовано функциональным `CanActivateFn` —
современная форма того же механизма для standalone-приложений.
