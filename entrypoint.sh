#!/bin/sh
set -e

# Switch to the html directory
cd /var/www/html

# Run optimizations. These commands are run here instead of in the Dockerfile
# so that they use the production environment variables.
echo "Caching configuration..."
php artisan config:cache

echo "Caching routes..."
php artisan route:cache

echo "Caching views..."
php artisan view:cache

# Start supervisord and services
exec /usr/bin/supervisord -c /etc/supervisord.conf
