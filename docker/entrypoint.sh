#!/bin/sh

if [ ! -f /var/www/html/database/database.sqlite ] && [ ! -f /var/www/html/database/database.db ]; then
    touch /var/www/html/database/database.sqlite
fi

echo "🚀 Starting $APP_ENV server..."
if [ "$APP_ENV" = "production" ]; then
    exec php artisan octane:start --server=frankenphp --host=0.0.0.0 --port=8000
else
    exec composer run dev
fi
