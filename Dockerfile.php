# ---- Frontend Builder Stage ----
FROM node:20-alpine AS frontend_builder
WORKDIR /app

COPY package.json package-lock.json ./
RUN npm install

COPY . .
RUN npm run build

# ---- Composer Builder Stage ----
FROM composer:2 AS composer_builder
WORKDIR /app

COPY --chown=1000:1000 . /app

RUN composer install --no-interaction --no-plugins --no-scripts --no-dev --prefer-dist --optimize-autoloader

# ---- Final Application Stage ----
FROM php:8.3-fpm-alpine AS app

WORKDIR /var/www/html

# Install system dependencies
RUN apk add --no-cache \
    nginx \
    supervisor \
    libzip-dev \
    zip \
    # Extensions needed for Laravel
    php83-fpm \
    php83-pgsql \
    php83-pdo_pgsql \
    php83-redis \
    php83-gd \
    php83-bcmath \
    php83-tokenizer \
    php83-xml \
    php83-curl

# Add a non-root user
RUN addgroup -g 1000 -S www && \
    adduser -u 1000 -S www -G www

RUN chown -R www:www /var/www/html

# Copy application code
COPY --chown=www:www . .

# Copy built assets and composer dependencies
COPY --from=composer_builder --chown=www:www /app/vendor /var/www/html/vendor
COPY --from=frontend_builder --chown=www:www /app/public/build /var/www/html/public/build

# Set permissions
RUN chown -R www:www /var/www/html
USER www

# Expose port 9000 and start php-fpm server
EXPOSE 9000
CMD ["php-fpm"]

# After building, optimize Laravel
RUN php artisan config:cache
RUN php artisan route:cache
RUN php artisan view:cache

USER root
COPY --chown=root:root docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
