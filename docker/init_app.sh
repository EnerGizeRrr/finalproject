#!/bin/bash
set -e

# Проверяем, существует ли composer.lock и vendor директория
if [ -f composer.lock ] && [ ! -d vendor ]; then
    echo "Composer lock file exists but vendor directory does not. Installing dependencies..."
    composer install --no-interaction
elif [ ! -f composer.lock ]; then
    echo "Composer lock file does not exist. Running composer install..."
    composer install --no-interaction
else
    # Если composer.lock и vendor существуют, проверим, нужно ли обновлять
    echo "Checking if composer install is needed..."
    # Опционально: можно добавить проверку на изменения в composer.json по сравнению с lock
    # Для простоты сейчас просто запустим install --dry-run и посмотрим вывод, или просто пропустим
    echo "Vendor directory exists. Skipping composer install unless forced."
fi

# Запускаем artisan serve
exec php artisan serve --host=0.0.0.0 --port=80