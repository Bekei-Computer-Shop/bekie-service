#!/bin/sh
set -eu

cd /var/www/html

mkdir -p storage/app/public storage/app/private storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true
chmod -R 775 storage bootstrap/cache

if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
    echo "Running database migrations..."
    php artisan migrate --force
fi

php artisan storage:link --force >/dev/null 2>&1 || true

if [ -n "${APP_KEY:-}" ]; then
    echo "Caching Laravel configuration..."
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
fi

if [ "$#" -gt 0 ] && { [ "$1" = "php" ] || [ "$1" = "artisan" ] || [ "$1" = "/usr/local/bin/php" ]; }; then
    exec "$@"
fi

PORT_VALUE="${PORT:-8080}"
cat > /etc/nginx/conf.d/default.conf <<EOF
server {
    listen ${PORT_VALUE};
    server_name _;
    root /var/www/html/public;
    index index.php;
    client_max_body_size 20m;

    location ~ /build/.*\.php$ {
        deny all;
    }

    location / {
        try_files \$uri /index.php?\$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass 127.0.0.1:9000;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
    }
}
EOF

php-fpm -D
exec nginx -g 'daemon off;'
