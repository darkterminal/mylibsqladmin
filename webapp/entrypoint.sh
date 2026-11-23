#!/bin/sh
set -e

cd /var/www/html

# Fail fast on a malformed APP_KEY. /up answers 200 with a bad key, so a plain
# healthcheck cannot catch it; /login only fails later when the encrypter is
# resolved. Accept 16 or 32 decoded bytes (default cipher is AES-256-CBC).
php -r '
$k = (string) getenv("APP_KEY");
$prefixed = str_starts_with($k, "base64:");
$raw = $prefixed ? base64_decode(substr($k, 7), true) : $k;
$n = ($raw === false) ? -1 : strlen($raw);
if (! $prefixed && $n === 44 && ($dec = base64_decode($k, true)) !== false && strlen($dec) === 32) {
    fwrite(STDERR, "APP_KEY is bare base64: add the base64: prefix (it decodes to 32 bytes).\n");
    exit(1);
}
if ($n !== 16 && $n !== 32) {
    fwrite(STDERR, "APP_KEY is invalid: decoded length $n, expected 32 (or 16 for aes-128-*). Generate with: php artisan key:generate --show\n");
    exit(1);
}
' || exit 1

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
    # WEB_SERVER selects the server without a compose command override.
    # "artisan" is the F-60 smoke fallback (FrankenPHP 1.4.4 is older than
    # Octane 2.24 requires); it is not a production serving fix.
    case "${WEB_SERVER:-octane}" in
        artisan)
            exec php artisan serve --host=0.0.0.0 --port=8000
            ;;
        octane)
            exec php artisan octane:start --server=frankenphp --host=0.0.0.0 --port=8000
            ;;
        *)
            echo "Unknown WEB_SERVER='${WEB_SERVER}' (expected octane or artisan)" >&2
            exit 1
            ;;
    esac
else
    exec composer run dev
fi
