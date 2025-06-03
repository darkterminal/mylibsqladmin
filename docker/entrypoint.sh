#!/bin/sh

if [ ! -f /var/www/html/database/database.sqlite ] && [ ! -f /var/www/html/database/database.db ]; then
    touch /var/www/html/database/database.sqlite
fi

if [ "$APP_ENV" = "production" ]; then
    php artisan migrate:fresh --seed --force
    php artisan sqld:remove-database-except-default
    php artisan optimize
    php artisan cache:clear
    php artisan config:clear
    php artisan route:clear
    php artisan view:clear
    exec php artisan octane:start --server=frankenphp --host=0.0.0.0 --port=8000
else
    exec composer run dev
fi
