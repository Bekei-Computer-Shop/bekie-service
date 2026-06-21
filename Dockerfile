### Stage 1: Node — build Vite assets
FROM node:22-alpine AS node_builder
WORKDIR /app

COPY package*.json vite.config.* ./

# Delete lock file and reinstall cleanly to fix @tailwindcss/oxide native binding issue
RUN npm install --silent

COPY resources/ ./resources/
COPY public/ ./public/

ARG VITE_APP_URL=http://localhost
ENV VITE_APP_URL=${VITE_APP_URL}

RUN npm run build

### Stage 2: Composer — install PHP dependencies only
FROM composer:2 AS composer_builder
WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --no-interaction \
    --no-progress \
    --optimize-autoloader \
    --prefer-dist

### Stage 3: Final PHP-FPM runtime
FROM php:8.2-fpm

WORKDIR /var/www/html

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        libzip-dev libpng-dev libonig-dev libxml2-dev \
        libpq-dev libicu-dev zlib1g-dev ca-certificates \
    && docker-php-ext-install \
        pdo pdo_pgsql mbstring exif pcntl bcmath gd zip intl opcache \
    && pecl install redis-6.0.2 \
    && docker-php-ext-enable redis \
    && apt-get purge -y --auto-remove \
    && rm -rf /var/lib/apt/lists/* /tmp/pear

COPY . .
COPY --from=composer_builder /app/vendor ./vendor
COPY --from=node_builder /app/public/build ./public/build

RUN chown -R www-data:www-data storage bootstrap/cache public \
    && chmod -R 755 storage bootstrap/cache public

EXPOSE 9000
CMD ["php-fpm"]
