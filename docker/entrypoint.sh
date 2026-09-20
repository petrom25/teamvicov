#!/bin/sh
set -eu
if [ -z "${APP_KEY:-}" ]; then
    echo 'APP_KEY is required. Generate a stable key before deploying.' >&2
    exit 1
fi
mkdir -p storage/app/private storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs
chown -R www-data:www-data storage bootstrap/cache
php artisan config:clear
php artisan migrate --force
php artisan config:cache
php artisan view:cache
exec docker-php-entrypoint "$@"
