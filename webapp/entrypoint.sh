#!/bin/sh
set -e

cd /var/www/html

if [ ! -f /var/www/html/database/database.sqlite ] && [ ! -f /var/www/html/database/database.db ]; then
    touch /var/www/html/database/database.sqlite

    echo "Bootstrapping fresh database: applying migrations..."
    APP_RUN_MIGRATIONS=true php artisan migrate --force

    if [ "${LIBSQL_SEED_DEFAULTS}" = "true" ]; then
        echo "LIBSQL_SEED_DEFAULTS=true — re-stamping with fresh bootstrap credentials..."
        php artisan db:seed --class=BootstrapSeeder --force
        echo "IMPORTANT: read credentials from storage/app/bootstrap-credentials.txt and delete it after use."
    else
        echo "No seed executed (default). No users exist — create the initial super-admin with:"
        echo "  php artisan db:seed --class=BootstrapSeeder"
    fi
else
    echo "Existing database found — skipping migration; run 'php artisan migrate --force' manually if needed."
fi

echo "Starting $APP_ENV server..."
if [ "$APP_ENV" = "production" ]; then
    exec php artisan octane:start --server=frankenphp --host=0.0.0.0 --port=8000
else
    exec composer run dev
fi
