# Production-ready multi-stage Dockerfile for Laravel (PHP 8.2)
# Builds frontend assets with Node, installs PHP dependencies with Composer,
# and produces a small runtime image.

### Node builder: builds Vite assets
FROM node:18 AS node_builder
WORKDIR /app
COPY package*.json ./
RUN npm ci --silent
COPY . .
RUN npm run build

### Composer builder: installs PHP deps
FROM composer:2 AS composer_builder
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-progress
COPY . .
RUN composer dump-autoload --optimize

### Final image: PHP-FPM runtime
FROM php:8.2-fpm
ARG WWWUSER=www-data
ARG WWWGROUP=www-data
WORKDIR /var/www/html

# System deps and PHP extensions
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        git unzip libzip-dev libpng-dev libonig-dev libxml2-dev libpq-dev libicu-dev zlib1g-dev ca-certificates \
    && docker-php-ext-install pdo pdo_pgsql mbstring exif pcntl bcmath gd zip intl opcache \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Copy application files
COPY --from=composer_builder /app/vendor ./vendor
COPY --from=node_builder /app/public/build ./public/build
COPY . .

# Permissions
RUN chown -R ${WWWUSER}:${WWWGROUP} /var/www/html/storage /var/www/html/bootstrap/cache /var/www/html/public \
    && chmod -R 755 /var/www/html/storage /var/www/html/bootstrap/cache /var/www/html/public

EXPOSE 9000
CMD ["php-fpm"]
