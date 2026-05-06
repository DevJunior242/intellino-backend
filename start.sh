#!/bin/bash
php artisan config:clear
php artisan cache:clear
php artisan config:cache
php artisan route:cache
php artisan migrate --force
php artisan storage:link
php artisan queue:work --daemon &
php artisan serve --host=0.0.0.0 --port=8080