FROM php:8.4-fpm

RUN apt-get update && apt-get install -y \
    libfreetype6-dev libjpeg62-turbo-dev libpng-dev libzip-dev \
    zip unzip git libonig-dev libxml2-dev libcurl4-openssl-dev

RUN docker-php-ext-install gd zip pdo_mysql bcmath mbstring xml soap pcntl && \
    pecl install swoole redis && \
    docker-php-ext-enable swoole redis

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
WORKDIR /var/www/html

RUN mkdir -p /var/www/.config/psysh && chown -R www-data:www-data /var/www
USER www-data
EXPOSE 8000
CMD ["php", "artisan", "octane:start", "--server=swoole", "--host=0.0.0.0", "--port=8000"]
