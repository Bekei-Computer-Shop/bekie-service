#!/bin/sh
set -e

cd /var/www/html

if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
    echo "Running database migrations..."
    php artisan migrate --force
fi

if [ -n "${APP_KEY:-}" ]; then
    echo "Caching Laravel configuration..."
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
fi

if [ "$1" = "php" ] || [ "$1" = "artisan" ] || [ "$1" = "/usr/local/bin/php" ]; then
    exec "$@"
fi

PORT_VALUE="${PORT:-8080}"
sed "s/\${PORT:-8080}/${PORT_VALUE}/g" /var/www/html/nginx.conf > /etc/nginx/conf.d/default.conf

php-fpm -D
nginx -g 'daemon off;'
