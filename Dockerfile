FROM php:8.4-apache

RUN apt-get update && apt-get install -y \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    zip unzip curl git

RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY composer.json composer.lock ./

RUN php -d memory_limit=-1 /usr/bin/composer install \
    --no-dev \
    --no-scripts \
    --no-autoloader

COPY . .

RUN composer dump-autoload --optimize --no-dev --no-scripts

RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

EXPOSE 8080

# CMD php artisan migrate:fresh --seed --force && \
#     php artisan serve --host=0.0.0.0 --port=8080  && \
#     php artisan queue:work

CMD php artisan config:clear && \
        php artisan cache:clear && \
        php artisan config:cache && \
        php artisan route:cache && \
        php artisan view:cache && \
        php artisan migrate --force && \
        php artisan storage:link && \
        php artisan queue:work && \
        php artisan serve --host=0.0.0.0 --port=8080

 