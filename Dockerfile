# Stage 1: Composer builder
FROM composer:2 as composer_builder
WORKDIR /app
COPY composer.json composer.lock ./
# Set environment variables to avoid external service connections during build
ENV DB_CONNECTION=sqlite \
    CACHE_STORE=array \
    SESSION_DRIVER=array \
    QUEUE_CONNECTION=sync \
    REDIS_HOST=127.0.0.1 \
    REDIS_PORT=6379
RUN composer install --no-dev --optimize-autoloader --ignore-platform-reqs --no-scripts

# Stage 2: Node builder
FROM node:lts as node_builder
WORKDIR /app
COPY package.json package-lock.json vite.config.js ./
COPY resources ./resources
RUN npm install
RUN npm run build

# Stage 3: Final PHP-FPM runtime
FROM php:8.3-fpm
WORKDIR /var/www/html

# Copy composer
COPY --from=composer/composer:2 /usr/bin/composer /usr/bin/composer

# Copy application code
COPY . .

# Copy vendor from composer_builder
COPY --from=composer_builder /app/vendor ./vendor

# Copy built assets from node_builder
COPY --from=node_builder /app/public/build ./public/build

# Copy start.sh
COPY start.sh /usr/local/bin/start.sh
RUN chmod +x /usr/local/bin/start.sh

# Set permissions: change ownership to www-data
RUN chown -R www-data:www-data /var/www/html

# Optimize autoloader (disable scripts to avoid package:discover errors during build)
RUN composer dump-autoload --optimize --no-scripts \
    && rm /usr/bin/composer

# Set permissions on storage and bootstrap/cache (already owned by www-data due to above chown)
# But ensure they are writable
RUN chmod -R 775 storage bootstrap/cache

HEALTHCHECK --interval=30s --timeout=3s --start-period=5s --retries=3 \
  CMD curl -f http://localhost:${PORT:-8080}/ || exit 1

ENTRYPOINT ["/usr/local/bin/start.sh"]
EXPOSE 8080
# Note: Render will use the PORT environment variable, which we use in the nginx configuration.
