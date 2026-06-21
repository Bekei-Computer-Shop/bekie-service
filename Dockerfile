# Stage 1: Composer builder
FROM composer:2 as composer_builder
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader --ignore-platform-reqs

# Stage 2: Node builder
FROM node:lts as node_builder
WORKDIR /app
COPY package.json package-lock.json vite.config.js ./
COPY resources ./resources
RUN npm install
RUN npm run build

# Stage 3: Final PHP-FPM runtime
FROM php:8.2-fpm
WORKDIR /var/www/html

# Install system dependencies
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

# Copy composer
COPY --from=composer/composer:2 /usr/bin/composer /usr/bin/composer

# Copy application code
COPY . .

# Copy vendor from composer_builder
COPY --from=composer_builder /app/vendor ./vendor

# Copy built assets from node_builder
COPY --from=node_builder /app/public/build ./public/build

# Optimize autoloader (disable scripts to avoid package:discover errors during build)
RUN composer dump-autoload --optimize --no-scripts \
    && rm /usr/bin/composer

# Set permissions
RUN chown -R www-data:www-data storage bootstrap/cache public \
    && chmod -R 755 storage bootstrap/cache public

EXPOSE 9000
CMD ["php-fpm"]