FROM php:8.1-cli

WORKDIR /usr/src/app

# Установка зависимостей, если они есть (например, composer)
# COPY composer.json composer.lock ./
# RUN composer install --no-dev --no-scripts --no-progress

COPY index.php .

# Установка расширения, если нужно проверять подпись (например, hash_hmac)
# RUN docker-php-ext-install hash # Убираем, так как hash встроен

EXPOSE 8000

CMD ["php", "-S", "0.0.0.0:8000"]