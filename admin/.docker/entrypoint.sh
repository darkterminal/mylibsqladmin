#!/bin/sh

if [ ! -f /var/www/html/database/database.sqlite ] && [ ! -f /var/www/html/database/database.db ]; then
    touch /var/www/html/database/database.sqlite
fi

if [ "$APP_ENV" = "production" ]; then
    exec php artisan migrate --force
    exec php artisan sqld:remove-database-except-default
    exec php artisan octane:frankenphp --host=0.0.0.0 --port=8000
else
    exec composer run dev
fi
