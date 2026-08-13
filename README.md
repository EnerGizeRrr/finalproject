<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/Laravel/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## API Управления Задачами

Этот проект представляет собой API для управления задачами, проектами и комментариями, разработанный с использованием Laravel 12. Он включает в себя аутентификацию, авторизацию, систему аудита, очереди с повторными попытками, интеграцию с веб-хуками и механизмы для обеспечения производительности и готовности к продакшену.

### Стек технологий

-   **Фреймворк:** Laravel 12
-   **Язык:** PHP 8.4 / 8.5
-   **База данных:** MySQL 8.0 (или PostgreSQL)
-   **Очереди/Кэш:** Redis
-   **Контейнеризация:** Docker Compose

### Запуск проекта

Проект запускается исключительно через Docker Compose.

1.  **Клонируйте репозиторий:**
    ```bash
    git clone <URL_ВАШЕГО_РЕПОЗИТОРИЯ>
    cd finalproject
    ```

2.  **Настройте переменные окружения:**
    Скопируйте файл `.env.example` в `.env` и настройте необходимые параметры (например, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`).
    ```bash
    cp .env.example .env
    ```

3.  **Запустите Docker Compose:**
    ```bash
    docker-compose up -d --build
    ```

4.  **Установите зависимости Composer и сгенерируйте ключ приложения:**
    ```bash
    docker-compose exec laravel.test composer install
    docker-compose exec laravel.test php artisan key:generate
    ```

5.  **Выполните миграции базы данных и сидеры:**
    ```bash
    docker-compose exec laravel.test php artisan migrate --seed
    ```

6.  **Запустите обработчик очередей (в отдельном терминале или как часть Docker Compose):**
    ```bash
    docker-compose exec laravel.test php artisan queue:work
    ```

### Основной функционал

-   **CRUD API:** Полный набор RESTful эндпоинтов для управления `Project`, `Task` и `Comment`.
-   **Аутентификация и Авторизация:**
    -   Используется Sanctum для аутентификации по токенам.
    -   Авторизация на основе ролей (`owner`, `member`) реализована через Policies.
-   **Аудит и События:**
    -   События (`TaskCreated`, `TaskStatusChanged`, `TaskCompleted`) генерируются при изменениях задач.
    -   Все значимые действия логируются в таблицу `audit_logs`.
-   **Очереди и Отказоустойчивость:**
    -   Уведомления и веб-хуки обрабатываются асинхронно через очереди.
    -   Механизм повторных попыток (retries) с экспоненциальной задержкой (backoff) для задач в очереди.
    -   Идемпотентность для предотвращения дублирования уведомлений.
-   **Внешняя интеграция (Webhooks):**
    -   Пользователи могут подписывать проекты на веб-хуки.
    -   Автоматическая отправка POST-запросов при событиях `TaskStatusChanged`/`TaskCompleted`.
    -   Подпись запросов (HMAC) и использование заголовка `Idempotency-Key`.
    -   Логирование всех попыток доставки веб-хуков в `webhook_attempts`.
    -   В Docker Compose добавлен сервис `webhook-receiver` для тестирования.
-   **Производительность:**
    -   Устранены проблемы N+1 запросов в списках.
    -   Добавлены индексы для полей фильтрации (`status`, `priority`, `due_date`, `project_id`).
    -   Реализована курсорная пагинация (keyset pagination) для списков задач.
-   **Наблюдаемость:**
    -   `Request-id` для сквозного отслеживания запросов в логах и ответах API.
    -   Эндпоинты `/health`, `/ready`, `/metrics` для мониторинга состояния приложения.

### Тестирование

Проект покрыт более чем 20 Feature и Unit тестами, обеспечивающими корректность работы основного функционала, аутентификации, авторизации, очередей и веб-хуков.

Для запуска тестов:
```bash
docker-compose exec laravel.test php artisan test
```

### Документация API

Полная интерактивная документация API доступна в формате OpenAPI (Swagger). Файл `openapi.yaml` находится в корне проекта и описывает все эндпоинты, модели данных, схемы ошибок, аутентификацию и веб-хуки.

Вы можете открыть `openapi.yaml` в Swagger Editor для просмотра и взаимодействия с документацией.

### Примеры использования

Примеры запросов Postman/curl будут предоставлены по запросу или могут быть сгенерированы из `openapi.yaml`.