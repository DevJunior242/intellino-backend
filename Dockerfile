FROM php:8.2-apache

RUN apt-get update && apt-get install -y \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip unzip curl

RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY . .

RUN composer install --no-dev --optimize-autoloader

RUN chown -R www-data:www-data /var/www/html/storage

EXPOSE 8080

CMD php artisan config:clear && \
        php artisan cache:clear && \
        php artisan config:cache && \
        php artisan route:cache && \
        php artisan view:cache && \
        php artisan migrate --force && \
        php artisan storage:link && \
        php artisan serve --host=0.0.0.0 --port=8080

# CMD php artisan migrate:fresh --seed --force && php artisan serve --host=0.0.0.0 --port=8080
