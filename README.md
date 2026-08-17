# Task Manager API

## Технологический стек

*   **Backend:** Laravel (PHP)
*   **База данных:** MySQL
*   **Кэширование/Очереди:** Redis
*   **Контейнеризация:** Docker, Docker Compose
*   **Аутентификация:** Laravel Sanctum
*   **API:** RESTful
*   **Тестирование:** PHPUnit
*   **Веб-сервер:** Nginx (предполагается, хотя в файле compose не указан явно, но часто используется с Laravel)

Этот проект представляет собой RESTful API для управления задачами, проектами и комментариями. API поддерживает аутентификацию, авторизацию, вебхуки, очереди задач и аудит.

## Установка и запуск

1.  **Клонируйте репозиторий:**
    ```bash
    git clone <URL_ВАШЕГО_РЕПОЗИТОРИЯ>
    cd finalproject
    ```

2.  **Скопируйте `.env.example` в `.env` и настройте переменные:**
    ```bash
    cp .env.example .env
    ```
    Обязательно укажите правильные настройки для подключения к БД (`DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`), Redis (`REDIS_HOST`, `REDIS_PASSWORD`) и внешних сервисов (например, `WEBHOOK_RECEIVER_URL`).

3.  **Сгенерируйте ключ приложения:**
    ```bash
    docker-compose exec laravel.test php artisan key:generate
    ```

4.  **Выполните миграции:**
    ```bash
    docker-compose exec laravel.test php artisan migrate
    ```

5.  **(Опционально) Запустите seeders для начальных данных:**
    ```bash
    docker-compose exec laravel.test php artisan db:seed
    ```

6.  **Приложение и воркер очередей запускаются автоматически при старте контейнера `laravel.test`.**

7.  **API будет доступен по адресу:**
    `http://localhost/api/v1` (или как настроено в `APP_URL` в `.env`)

## Команды

*   **Запуск тестов:**
    ```bash
    docker-compose exec laravel.test php artisan test
    ```
    > **Важно:** Некоторые тесты, связанные с обработкой очередей (например, проверка попадания задач в `failed_jobs`), могут требовать, чтобы воркер очередей `queue:work` был запущен вручную в отдельном терминале с тестовым окружением (`--env=testing`) для корректной проверки обработки задач. Автоматические тесты, использующие синхронную очередь или `Queue::fake()`, не требуют этого.

*   **Выполнение миграций:**
    ```bash
    docker-compose exec laravel.test php artisan migrate
    ```

*   **Откат миграций:**
    ```bash
    docker-compose exec laravel.test php artisan migrate:rollback
    ```

*   **Запуск воркера очередей (для ручного запуска, если нужно):**
    ```bash
    docker-compose exec laravel.test php artisan queue:work
    ```

*   **Просмотр логов:**
    ```bash
    tail -f storage/logs/laravel.log
    ```

## Примеры API запросов

### Регистрация

**Запрос:**

`POST http://localhost/api/v1/register`

```json
{
    "name": "API User",
    "email": "apiuser@example.com",
    "password": "password123",
    "password_confirmation": "password123"
}
```

**Ответ сервера:**

```json
{
    "access_token": "1|dRKukS6lAvltNFRo45HoWiGA6cCOkRQcAJlbNEVI5ab01d84",
    "token_type": "Bearer",
    "user": {
        "id": 1,
        "name": "API User",
        "email": "apiuser@example.com",
        "created_at": "2026-08-15T22:40:07.000000Z",
        "updated_at": "2026-08-15T22:40:07.000000Z"
    }
}
```

### Аутентификация

**Запрос:**

`POST http://localhost/api/v1/login`

```json
{
    "email": "apiuser@example.com",
    "password": "password123"
}
```

**Ответ сервера:**

```json
{
    "access_token": "2|another_token_here...",
    "token_type": "Bearer"
}
```

### Создание проекта

**Запрос:**

`POST http://localhost/api/v1/projects`

*Заголовки:*
`Authorization: Bearer YOUR_ACCESS_TOKEN`
`Content-Type: application/json`

```json
{
    "name": "Новый проект"
}
```

**Ответ сервера:**

```json
{
    "id": 1,
    "name": "Новый проект",
    "ownerId": 1,
    "createdAt": "2023-10-27T10:00:00Z",
    "updatedAt": "2023-10-27T10:00:00Z"
}
```

### Получение списка проектов

**Запрос:**

`GET http://localhost/api/v1/projects`

*Заголовки:*
`Authorization: Bearer YOUR_ACCESS_TOKEN`

**Ответ сервера:**

```json
{
    "data": [
        {
            "id": 1,
            "name": "Новый проект",
            "ownerId": 1,
            "createdAt": "2023-10-27T10:00:00Z",
            "updatedAt": "2023-10-27T10:00:00Z"
        }
    ],
    "links": {
        "first": "http://localhost/api/v1/projects?page=1",
        "last": "http://localhost/api/v1/projects?page=1",
        "...": "..."
    },
    "meta": {
        "current_page": 1,
        "from": 1,
        "last_page": 1,
        "...": "..."
    }
}
```

### Получение проекта по ID

**Запрос:**

`GET http://localhost/api/v1/projects/1`

*Заголовки:*
`Authorization: Bearer YOUR_ACCESS_TOKEN`

**Ответ сервера:**

```json
{
    "id": 1,
    "name": "Новый проект",
    "ownerId": 1,
    "createdAt": "2023-10-27T10:00:00Z",
    "updatedAt": "2023-10-27T10:00:00Z"
}
```

### Обновление проекта

**Запрос:**

`PUT http://localhost/api/v1/projects/1`

*Заголовки:*
`Authorization: Bearer YOUR_ACCESS_TOKEN`
`Content-Type: application/json`

```json
{
    "name": "Обновленный проект"
}
```

**Ответ сервера:**

```json
{
    "id": 1,
    "name": "Обновленный проект",
    "ownerId": 1,
    "createdAt": "2023-10-27T10:00:00Z",
    "updatedAt": "2023-10-27T11:00:00Z"
}
```

### Удаление проекта

**Запрос:**

`DELETE http://localhost/api/v1/projects/1`

*Заголовки:*
`Authorization: Bearer YOUR_ACCESS_TOKEN`

**Ответ сервера:**

*Статус: 204 No Content*

### Создание задачи в проекте

**Запрос:**

`POST http://localhost/api/v1/projects/1/tasks`

*Заголовки:*
`Authorization: Bearer YOUR_ACCESS_TOKEN`
`Content-Type: application/json`

```json
{
    "title": "Новая задача",
    "description": "Описание задачи",
    "status": "new",
    "priority": "normal",
    "due_date": "2023-12-31"
}
```

**Ответ сервера:**

```json
{
    "id": 1,
    "title": "Новая задача",
    "description": "Описание задачи",
    "status": "new",
    "priority": "normal",
    "dueDate": "2023-12-31",
    "projectId": 1,
    "createdAt": "2023-10-27T12:00:00Z",
    "updatedAt": "2023-10-27T12:00:00Z"
}
```

### Получение списка задач проекта

**Запрос:**

`GET http://localhost/api/v1/projects/1/tasks`

*Заголовки:*
`Authorization: Bearer YOUR_ACCESS_TOKEN`

**Ответ сервера:**

```json
{
    "data": [
        {
            "id": 1,
            "title": "Новая задача",
            "description": "Описание задачи",
            "status": "new",
            "priority": "normal",
            "dueDate": "2023-12-31",
            "projectId": 1,
            "createdAt": "2023-10-27T12:00:00Z",
            "updatedAt": "2023-10-27T12:00:00Z"
        }
    ],
    "links": {
        "first": "http://localhost/api/v1/projects/1/tasks?page=1",
        "last": "http://localhost/api/v1/projects/1/tasks?page=1",
        "...": "..."
    },
    "meta": {
        "current_page": 1,
        "from": 1,
        "last_page": 1,
        "...": "..."
    }
}
```

### Создание комментария к задаче

**Запрос:**

`POST http://localhost/api/v1/tasks/1/comments`

*Заголовки:*
`Authorization: Bearer YOUR_ACCESS_TOKEN`
`Content-Type: application/json`

```json
{
    "body": "Это комментарий к задаче."
}
```

**Ответ сервера:**

```json
{
    "id": 1,
    "body": "Это комментарий к задаче.",
    "taskId": 1,
    "userId": 1,
    "createdAt": "2023-10-27T13:00:00Z",
    "updatedAt": "2023-10-27T13:00:00Z"
}
```

### Получение списка комментариев задачи

**Запрос:**

`GET http://localhost/api/v1/tasks/1/comments`

*Заголовки:*
`Authorization: Bearer YOUR_ACCESS_TOKEN`

**Ответ сервера:**

```json
{
    "data": [
        {
            "id": 1,
            "body": "Это комментарий к задаче.",
            "taskId": 1,
            "userId": 1,
            "createdAt": "2023-10-27T13:00:00Z",
            "updatedAt": "2023-10-27T13:00:00Z"
        }
    ],
    "links": {
        "first": "http://localhost/api/v1/tasks/1/comments?page=1",
        "last": "http://localhost/api/v1/tasks/1/comments?page=1",
        "...": "..."
    },
    "meta": {
        "current_page": 1,
        "from": 1,
        "last_page": 1,
        "...": "..."
    }
}
```